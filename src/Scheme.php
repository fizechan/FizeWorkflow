<?php


namespace fize\workflow;

use fize\workflow\model\Instance;


/**
 * 方案
 */
class Scheme implements SchemeInterface
{

    /**
     * @var int 实例ID
     */
    protected $instanceId;

    /**
     * 初始化
     * @param int $instance_id 实例ID
     */
    public function __construct($instance_id)
    {
        $this->instanceId = $instance_id;
    }

    /**
     * 开始
     */
    public function start()
    {
        $instance = Db::table('workflow_instance')->where('id', '=', $instance_id)->find();
        $map = [
            ['scheme_id', '=', $instance['scheme_id']],
            ['level', '=', 1]
        ];
        $lv1nodes = Db::name('workflow_node')->where($map)->select();
        foreach ($lv1nodes as $lv1node) {
            $this->instanceNode = new $lv1node['class']();
            if ($this->instanceNode->access($instance_id, 0, $lv1node['id'])) {
                $this->instanceNode->createOperation($instance_id, $contrast_id, $lv1node['id']);
            }
            $this->instanceNode = null;
        }
    }

    /**
     * 审批通过
     */
    public function adopt()
    {
        $data = [
            'status'    => Instance::STATUS_ADOPT,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' => $this->instanceId])->update($data);
    }

    /**
     * 审批否决
     */
    public function reject()
    {
        $data = [
            'status'    => Instance::STATUS_REJECT,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' => $this->instanceId])->update($data);
    }

    /**
     * 审批退回
     */
    public function goback()
    {
        $data = [
            'status'    => Instance::STATUS_GOBACK,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $this->instanceId])->update($data);
    }

    public function hangup()
    {
        $data = [
            'status'    => Instance::STATUS_HANGUP,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $this->instanceId])->update($data);
    }

    public function interrupt()
    {
        // TODO: Implement interrupt() method.
    }

    public function cancel()
    {
        // TODO: Implement cancel() method.
    }

    public function reset($contrast_id = null)
    {
        // TODO: Implement reset() method.
    }

    public function goon()
    {
        // TODO: Implement goon() method.
    }

    public function append($node_id, $user_id = null)
    {
        // TODO: Implement append() method.
    }

    public function again($original_user = true)
    {
        // TODO: Implement again() method.
    }
}
