<?php


namespace fize\workflow;

/**
 * 接口：方案
 */
interface SchemeInterface
{

    /**
     * 初始化
     * @param int $instance_id 实例ID
     */
    public function __construct($instance_id);

    /**
     * 开始
     */
    public function start();

    /**
     * 审批通过
     */
    public function adopt();

    /**
     * 审批否决
     */
    public function reject();

    /**
     * 审批退回
     */
    public function goback();

    /**
     * 审批挂起
     */
    public function hangup();

    /**
     * 审批中断
     */
    public function interrupt();

    /**
     * 审批取消
     */
    public function cancel();

    /**
     * 继续执行
     */
    public function goon();

    /**
     * 重置到最开始节点
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     */
    public function reset($contrast_id = null);

    /**
     * 任意追加符合要求的操作
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @return int 成功返回插入的记录ID
     */
    public function append($node_id, $user_id = null);

    /**
     * 再次分配最后执行节点
     * @param bool $original_user 是否分配给原操作者，默认true
     * @return int 成功返回插入的记录ID
     */
    public function again($original_user = true);
}
