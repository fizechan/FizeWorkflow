<?php


namespace util\workflow\realization\scheme;

use think\Db;

/**
 * 方案分配
 */
trait Distribute
{

    /**
     * 为指定用户分配指定方案的工作流任务
     * @param int $user_id 工作流用户ID
     * @param int $scheme_id 方案ID
     * @return bool 成功返回true，失败返回false
     * @todo 启用自动分配后该方法不需要再调用,可删除
     */
    public function distribute($user_id, $scheme_id)
    {
        $user = Db::name('workflow_user')
            ->alias('t_u')
            ->where("t_u.id", '=', $user_id)
            ->find();
        if (!$user) {
            $this->errMsg = '未找到该工作流用户';
            return false;
        }
        $sql = <<<EOF
SELECT t_operation.id
FROM gm_workflow_operation AS t_operation
LEFT JOIN gm_workflow_instance AS t_instance ON t_instance.id = t_operation.instance_id
WHERE
t_operation.user_id IS NULL
AND t_instance.scheme_id = ?
AND (
EXISTS (SELECT 1 FROM gm_workflow_node_role WHERE node_id = t_operation.node_id AND role_id = ?)
OR EXISTS (SELECT 1 FROM gm_workflow_node_user WHERE node_id = t_operation.node_id AND user_id = ?)
)
ORDER BY RAND()
LIMIT 1
EOF;
        $params = [
            $scheme_id, $user['role_id'], $user['id']
        ];

        $operations = Db::query($sql, $params);

        if (empty($operations)) {
            $this->errMsg = '没有待办事宜需要分配';
            return false;
        }

        $data = [
            'user_id'         => $user['id'],
            'user_extend_id'  => $user['extend_id'],
            'distribute_time' => date('Y-m-d H:i:s'),
        ];
        Db::name('workflow_operation')->where('id', '=', $operations[0]['id'])->update($data);
        return true;
    }
}
