<?php


namespace fize\workflow;

/**
 * 接口：方案
 */
interface SchemeInterface
{

    /**
     * 返回提交的差异字段
     *
     * 通过改写该方法可以进行差异字段自定义
     * 字段格式为 [$name => $field]，$name 为字段名, $field 含所有的字段属性
     * @param array $fields 新提交字段
     * @param array $original_fields 原提交字段
     * @return array [$name => ['title' => *, 'type' => *, 'new' => *, 'old' => *]]
     */
    public static function getSubmitContrasts($fields, $original_fields);

    /**
     * 开始
     * @param int $instance_id 实例ID
     */
    public static function start($instance_id);

    /**
     * 审批通过
     * @param int $instance_id 实例ID
     */
    public static function adopt($instance_id);

    /**
     * 审批否决
     * @param int $instance_id 实例ID
     */
    public static function reject($instance_id);

    /**
     * 审批退回
     * @param int $instance_id 实例ID
     */
    public static function goback($instance_id);

    /**
     * 审批挂起
     * @param int $instance_id 实例ID
     */
    public static function hangup($instance_id);

    /**
     * 审批中断
     * @param int $instance_id 实例ID
     */
    public static function interrupt($instance_id);

    /**
     * 审批取消
     * @param int $instance_id 实例ID
     */
    public static function cancel($instance_id);

    /**
     * 继续执行
     * @param int $instance_id 实例ID
     */
    public static function goon($instance_id);

    /**
     * 重置到最开始节点
     * @param int $instance_id 实例ID
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     */
    public static function reset($instance_id, $contrast_id = null);

    /**
     * 任意追加符合要求的操作
     * @param int $instance_id 实例ID
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @return int 成功返回插入的记录ID
     */
    public static function append($instance_id, $node_id, $user_id = null);

    /**
     * 再次分配最后执行节点
     * @param int $instance_id 实例ID
     * @param bool $original_user 是否分配给原操作者，默认true
     * @return int 成功返回插入的记录ID
     */
    public static function again($instance_id, $original_user = true);
}
