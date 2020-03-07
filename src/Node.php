<?php


namespace fize\workflow;

/**
 * 内置节点定义
 */
class Node implements NodeInterface
{
    /**
     * 判断节点准入条件
     * @param int $instance_id 工作流实例ID
     * @param int $prev_operation_id 前一个操作ID,为0表示没有前操作ID
     * @param int $node_id 节点ID
     * @return bool 可以进入返回true，不能进入返回false
     */
    public static function access($instance_id, $prev_operation_id, $node_id)
    {
        //可以在后续版本进行判断
        return true;
    }

    /**
     * 取出一个适合的用户ID用于任务分发
     * 改写该方法可以任意指定要分配的用户
     * @param int $operation_id 操作ID
     * @return int|null 有适合的用户ID则返回，没有则返回null
     * @todo 可用方案，目前先使用随机分配给其可用账号，暂未考虑其已有未完成任务的情况，可复写该方法来指定
     */
    public static function getSuitableUserId($operation_id)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $sql = <<<SQL
SELECT wf_workflow_user_role.user_id
FROM wf_workflow_user_role
LEFT JOIN wf_workflow_node_role ON wf_workflow_node_role.role_id = wf_workflow_user_role.role_id
WHERE
wf_workflow_node_role.node_id = {$operation['node_id']} AND wf_workflow_user_role.user_id IS NOT NULL
ORDER BY RAND()
LIMIT 1
SQL;
        $users = Db::query($sql);
        if (!$users) {
            return null;
        }

        return $users[0]['user_id'];
    }

    /**
     * 执行通知
     * @param int $operation_id 操作ID
     */
    public static function notice($operation_id)
    {
        //nothing
    }

    /**
     * 用于判断是否可以进行下级[审批通过]任务分发
     * 通过改写该方法来实现审批通过后是否马上生成下一层级操作
     * @param int $operation_id 操作ID
     * @return bool
     */
    public static function canNextAdopt($operation_id)
    {
        return true;
    }
}
