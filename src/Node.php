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
     * 执行通知
     * @param int $operation_id 操作ID
     */
    public static function notice($operation_id)
    {
        //nothing
    }
}
