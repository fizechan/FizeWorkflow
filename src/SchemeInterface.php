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
     * 审批通过
     * @return bool 成功返回true，失败返回false
     */
    public function adopt();

    /**
     * 审批否决
     * @return bool 成功返回true，失败返回false
     */
    public function reject();

    /**
     * 审批退回
     * @return bool 成功返回true，失败返回false
     */
    public function goback();

    /**
     * 审批挂起
     * @return bool 成功返回true，失败返回false
     */
    public function hangup();

    /**
     * 审批中断
     * @return bool 成功返回true，失败返回false
     */
    public function interrupt();

    /**
     * 审批取消
     * @return bool 成功返回true，失败返回false
     */
    public function cancel();

    /**
     * 重置到最开始节点
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     * @return bool 成功true，错误返回false
     */
    public function reset($contrast_id = null);

    /**
     * 继续执行
     * @return bool 成功返回true，失败返回false
     */
    public function goon();

    /**
     * 任意追加符合要求的操作
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function append($node_id, $user_id = null);

    /**
     * 再次分配最后执行节点
     * @param bool $org_user 是否分配给原操作者，默认true
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function again($org_user = true);

    /**
     * 创建
     * @param string $name 方案名称
     * @param string $class 指定方案逻辑处理类
     * @param string $type 方案类型
     * @return int 方案ID
     */
    public static function create($name, $class = null, $type = null);
}
