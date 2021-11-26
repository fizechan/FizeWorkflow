<?php

namespace Fize\Workflow;

/**
 * 内置节点定义
 */
class Node implements NodeInterface
{
    /**
     * 判断节点准入条件
     * @param int $instance_id       工作流实例ID
     * @param int $prev_operation_id 前一个操作ID,为0表示没有前操作ID
     * @param int $node_id           节点ID
     * @return bool 可以进入返回true，不能进入返回false
     */
    public static function access(int $instance_id, int $prev_operation_id, int $node_id): bool
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
    public static function getSuitableUserId(int $operation_id)
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
     * @todo 通知事宜应进行接口优化移至外部实现
     */
    public static function notice(int $operation_id)
    {
        //nothing
    }

    /**
     * 用于判断是否可以进行下级[审批通过]任务分发
     * 通过改写该方法来实现审批通过后是否马上生成下一层级操作
     * @param int $operation_id 操作ID
     * @return bool
     */
    public static function canNextAdopt(int $operation_id): bool
    {
        return true;
    }

    /**
     * 审核通过
     * @param int        $operation_id  操作ID
     * @param array      $fields        提交的完整表单
     * @param array|null $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @todo 参数$node_user_tos考虑移除
     */
    public static function adopt(int $operation_id, array $fields, array $node_user_tos = null)
    {
        // 内置的节点逻辑不需要做其他逻辑
        // 外部节点逻辑可以复写该方法实现自身逻辑
    }

    /**
     * 审核否决
     * 否决后默认是执行了方案否决方法，但是也可以重写该方法来执行特殊事务
     * @param int   $operation_id 操作ID
     * @param array $fields       表单数组
     */
    public static function reject(int $operation_id, array $fields)
    {
        // 内置的节点逻辑不需要做其他逻辑
        // 外部节点逻辑可以复写该方法实现自身逻辑
    }

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param int      $operation_id    操作ID
     * @param array    $fields          数据数组
     * @param int|null $to_node_id      返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int|null $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @todo 参数$to_node_id考虑移除，添加参数$to_user_id
     */
    public static function goback(int $operation_id, array $fields, int $to_node_id = null, int $to_operation_id = null)
    {
        // 内置的节点逻辑不需要做其他逻辑
        // 外部节点逻辑可以复写该方法实现自身逻辑
    }

    /**
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param int        $operation_id 操作ID
     * @param array|null $fields       数据数组
     */
    public static function hangup(int $operation_id, array $fields = null)
    {
        // 内置的节点逻辑不需要做其他逻辑
        // 外部节点逻辑可以复写该方法实现自身逻辑
    }
}
