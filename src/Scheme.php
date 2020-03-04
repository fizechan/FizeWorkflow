<?php


namespace fize\workflow;

use fize\workflow\model\Instance;


/**
 * 方案
 */
class Scheme implements SchemeInterface
{

    /**
     * 开始
     * @param int $instance_id 实例ID
     */
    public static function start($instance_id)
    {
        $instance = Db::table('workflow_instance')->where(['id' => $instance_id])->find();
        $map = [
            ['scheme_id', '=', $instance['scheme_id']],
            ['level', '=', 1]
        ];
        $lv1nodes = Db::table('workflow_node')->where($map)->select();
        foreach ($lv1nodes as $lv1node) {
            /**
             * @var NodeInterface $node
             */
            $node = $lv1node['class'];
            if ($node::access($instance_id, 0, $lv1node['id'])) {
                $node::create($contrast_id, $lv1node['id']);
            }
        }
    }

    /**
     * 审批通过
     * @param int $instance_id 实例ID
     */
    public static function adopt($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_ADOPT,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);
    }

    /**
     * 审批否决
     * @param int $instance_id 实例ID
     */
    public static function reject($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_REJECT,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);
    }

    /**
     * 审批退回
     * @param int $instance_id 实例ID
     */
    public static function goback($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_GOBACK,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);
    }

    public static function hangup($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_HANGUP,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);
    }

    public static function interrupt($instance_id)
    {
        // TODO: Implement interrupt() method.
    }

    public static function cancel($instance_id)
    {
        // TODO: Implement cancel() method.
    }

    public static function reset($instance_id, $contrast_id = null)
    {
        // TODO: Implement reset() method.
    }

    public static function goon($instance_id)
    {
        // TODO: Implement goon() method.
    }

    public static function append($instance_id, $node_id, $user_id = null)
    {
        // TODO: Implement append() method.
    }

    public static function again($instance_id, $original_user = true)
    {
        // TODO: Implement again() method.
    }
}
