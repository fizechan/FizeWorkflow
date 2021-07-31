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
     * @param array $fields          新提交字段
     * @param array $original_fields 原提交字段
     * @return array [$name => ['title' => *, 'type' => *, 'new' => *, 'old' => *]]
     */
    public static function getSubmitContrasts(array $fields, array $original_fields): array;

    /**
     * 审批通过
     * @param int $instance_id 实例ID
     */
    public static function adopt(int $instance_id);

    /**
     * 审批否决
     * @param int $instance_id 实例ID
     */
    public static function reject(int $instance_id);

    /**
     * 审批退回
     * @param int $instance_id 实例ID
     */
    public static function goback(int $instance_id);

    /**
     * 审批挂起
     * @param int $instance_id 实例ID
     */
    public static function hangup(int $instance_id);

    /**
     * 审批中断
     * @param int $instance_id 实例ID
     */
    public static function interrupt(int $instance_id);

    /**
     * 审批取消
     * @param int $instance_id 实例ID
     */
    public static function cancel(int $instance_id);
}
