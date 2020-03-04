<?php


namespace fize\workflow;

/**
 * 接口：节点
 */
interface NodeInterface
{
    /**
     * 初始化
     * @param int $operation_id 操作ID
     */
    public function __construct($operation_id);

    /**
     * 分配用户
     * @param int $user_id 指定接收用户ID
     * @return bool 操作成功返回true，失败返回false
     */
    public function distribute($user_id = null);

    /**
     * 通过
     * @param array $fields 提交的完整表单
     * @param array $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @return bool 操作成功返回true，失败返回false
     */
    public function adopt(array $fields, array $node_user_tos = null);

    /**
     * 审核否决
     * 否决后默认是执行了方案否决方法，但是也可以重写该方法来执行特殊事务
     * @param array $fields 表单数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function reject(array $fields);

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param array $fields 数据数组
     * @param int $to_node_id 返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @return bool 操作成功返回true，失败返回false
     */
    public function goback(array $fields, $to_node_id = null, $to_operation_id = null);

    /**
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param array $fields 数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function hangup(array $fields = null);

    /**
     * 任务调度
     * @param int $user_id 接收调度的用户ID
     * @param array $fields 附加数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function dispatch($user_id, array $fields = null);
}
