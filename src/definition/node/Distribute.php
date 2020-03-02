<?php


namespace util\workflow\realization\node;

use think\Db;
use think\facade\Log;

/**
 * 节点任务分配
 */
trait Distribute
{

    /**
     * 取出一个适合的用户ID用于任务分发
     * 改写该方法可以任意指定要分配的用户
     * @param int $operation_id 操作ID
     * @return mixed 有适合的用户ID则返回，没有则返回null
     */
    protected function getSuitableUserId($operation_id)
    {
        //todo 可用方案，目前先使用随机分配给其可用账号，暂未考虑其已有未完成任务的情况，可复写该方法来指定
        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        $sql = <<<EOF
SELECT gm_workflow_user.id
FROM gm_workflow_user
LEFT JOIN gm_workflow_node_role ON gm_workflow_node_role.role_id = gm_workflow_user.role_id
LEFT JOIN gm_workflow_node_user ON gm_workflow_node_user.user_id = gm_workflow_user.id
WHERE
gm_workflow_node_role.node_id = {$operation['node_id']} OR gm_workflow_node_user.node_id = {$operation['node_id']}
ORDER BY RAND()
LIMIT 1
EOF;
        $users = Db::query($sql);
        if (!$users) {
            return null;
        }

        return $users[0]['id'];
    }

    /**
     * 分配工作流操作用户
     * @param int $operation_id 操作ID
     * @param int $user_id 指定接收用户ID
     * @return bool 操作成功返回true，失败返回false
     */
    public function distributeUser($operation_id, $user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = $this->getSuitableUserId($operation_id);
            if (!$user_id) {
                $this->errMsg = '找不到该合适的用户！';
                Log::write("[operation : {$operation_id}] 找不到分配该任务的合适用户！", 'workflow');
                return false;
            }
        }
        $user = Db::name('workflow_user')->where('id', '=', $user_id)->find();
        $operation_data = [
            'user_id'         => $user['id'],
            'user_extend_id'  => $user['extend_id'],
            'distribute_time' => date('Y-m-d H:i:s')
        ];
        Db::name('workflow_operation')->where('id', '=', $operation_id)->update($operation_data);
        Log::write("[operation : {$operation_id}] 任务分配给用户[user : {$user['id']}]。", 'workflow');
        $this->notice($operation_id);
        return true;
    }
}
