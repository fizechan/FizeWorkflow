<?php

namespace Fize\Workflow;

/**
 * 接口：节点
 */
interface NodeInterface
{

    /**
     * 返回节点所属的方案
     * @return SchemeInterface
     */
    public function getScheme(): SchemeInterface;

    /**
     * 判断节点准入条件
     * @param int $instance_id       工作流实例ID
     * @param int $prev_operation_id 前一个操作ID,为0表示没有前操作ID
     * @param int $node_id           节点ID
     * @return bool 可以进入返回true，不能进入返回false
     */
    public static function access(int $instance_id, int $prev_operation_id, int $node_id): bool;

    /**
     * 取出一个适合的用户ID用于任务分发
     * 改写该方法可以任意指定要分配的用户
     * @param int $operation_id 操作ID
     * @return int|null 有适合的用户ID则返回，没有则返回null
     */
    public static function getSuitableUserId(int $operation_id);

    /**
     * 执行通知
     * @param int $operation_id 操作ID
     * @todo 待移除至外部实现
     */
    public static function notice(int $operation_id);

    /**
     * 用于判断是否可以进行下级[审批通过]任务分发
     * 通过改写该方法来实现审批通过后是否马上生成下一层级操作
     * @param int $operation_id 操作ID
     * @return bool
     */
    public static function canNextAdopt(int $operation_id): bool;

    /**
     * 审核通过
     * @param int        $operation_id  操作ID
     * @param array      $fields        提交的完整表单
     * @param array|null $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @todo 参数$node_user_tos考虑移除
     */
    public static function adopt(int $operation_id, array $fields, array $node_user_tos = null);

    /**
     * 审核否决
     * @param int   $operation_id 操作ID
     * @param array $fields       表单数组
     */
    public static function reject(int $operation_id, array $fields);

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param int      $operation_id    操作ID
     * @param array    $fields          数据数组
     * @param int|null $to_node_id      返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int|null $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @todo 参数$to_node_id考虑移除，添加参数$to_user_id
     */
    public static function goback(int $operation_id, array $fields, int $to_node_id = null, int $to_operation_id = null);

    /**
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param int        $operation_id 操作ID
     * @param array|null $fields       数据数组
     */
    public static function hangup(int $operation_id, array $fields = null);
}
