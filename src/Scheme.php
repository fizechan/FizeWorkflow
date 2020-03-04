<?php


namespace fize\workflow;

use fize\workflow\model\Instance;


/**
 * 方案
 */
class Scheme implements SchemeInterface
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
    public static function getSubmitContrasts($fields, $original_fields)
    {
        $contrasts = [];
        foreach ($fields as $name => $field) {
            if ($field['value'] != $original_fields[$name]['value']) {
                $contrasts[$name] = [
                    'title' => $field['title'],
                    'type'  => $field['type'],
                    'new'   => $field['value'],
                    'old'   => $original_fields[$name]['value']
                ];
            }
        }
        return $contrasts;
    }

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
     * 重置到最开始节点
     * @param int $instance_id 实例ID
     * @param int $submit_id 提交ID，不指定则为原提交ID
     */
    public static function reset($instance_id, $submit_id = null)
    {
        // TODO: Implement reset() method.
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
