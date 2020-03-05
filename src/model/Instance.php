<?php

namespace fize\workflow\model;

use RuntimeException;
use fize\misc\Preg;
use fize\workflow\Db;
use fize\workflow\SchemeInterface;
use fize\workflow\NodeInterface;

/**
 * 实例
 *
 * 【实例】是【方案】的实例化表现
 */
class Instance
{
    /**
     * 状态：执行中
     */
    const STATUS_EXECUTING = 0;

    /**
     * 状态：已通过
     */
    const STATUS_ADOPT = 1;

    /**
     * 状态：已否决
     */
    const STATUS_REJECT = 2;

    /**
     * 状态：已退回
     */
    const STATUS_GOBACK = 3;

    /**
     * 状态：已挂起
     */
    const STATUS_HANGUP = 4;

    /**
     * 状态：中断中
     */
    const STATUS_INTERRUPT = 5;

    /**
     * 状态：已取消
     */
    const STATUS_CANCEL = 8;

    /**
     * 创建
     * @param string $name 名称
     * @param int $scheme_id 方案ID
     * @param array $fields 传入的表单参数数组
     * @param int $instance_id 实例ID，指定该参数时表示重新提交
     * @return array ['instance_id' => $instance_id, 'submit_id' => $submit_id]
     */
    public static function submit($name, $scheme_id, $fields, $instance_id = null)
    {
        Db::startTrans();

        if ($instance_id) {  //再次提交
            $submit_times = Db::table('workflow_submit')->where(['instance_id' => $instance_id])->count() + 1;
        } else {  //首次提交
            $submit_times = 1;

            $data_instance = [
                'scheme_id' => $scheme_id,
                'name'      => $name,
                'status'    => Instance::STATUS_EXECUTING,
                'is_finish' => 0
            ];
            $instance_id = Db::table('workflow_instance')->insertGetId($data_instance);
        }

        $data_submit = [
            'instance_id' => $instance_id,
            'create_time' => date('Y-m-d H:i:s')
        ];
        $submit_id = Db::table('workflow_submit')->insertGetId($data_submit);

        foreach ($fields as $n => $v) {
            $field = Db::table('workflow_scheme_field')->where(['scheme_id' => $scheme_id, 'name' => $n])->find();
            if ($field['is_required'] && $v === "") {
                Db::rollback();
                throw new RuntimeException("字段{$n}必须填写");
            }
            if ($field['regex_match']) {
                if (!Preg::match($field['regex_match'], $v)) {
                    Db::rollback();
                    throw new RuntimeException("字段{$n}不符合规则");
                }
            }

            $data_submit_field = [
                'submit_id' => $submit_id,
                'name'      => $n,
                'value'     => $v
            ];
            Db::table('workflow_submit_field')->insert($data_submit_field);
        }

        //产生operation
        $data_operation = [
            'scheme_id'       => $scheme_id,
            'instance_id'     => $instance_id,
            'submit_id'       => $submit_id,
            'user_id'         => 0,  //0代表系统操作
            'node_id'         => 0,  //0代表非实际节点
            'node_name'       => '提交',
            'create_time'     => date('Y-m-d H:i:s'),
            'distribute_time' => date('Y-m-d H:i:s'),
            'action_id'       => 0,
            'action_name'     => "第{$submit_times}次提交",
            'action_type'     => Operation::ACTION_TYPE_SUBMIT,
            'action_time'     => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->insert($data_operation);

        Db::commit();

        if ($submit_times == 1) {
            self::start($instance_id);
        } else {
            self::reset($instance_id, $submit_id);
        }

        return ['instance_id' => $instance_id, 'submit_id' => $submit_id];
    }

    /**
     * 开始
     * @param int $instance_id 实例ID
     */
    public static function start($instance_id)
    {
        $submit_id = Db::table('workflow_submit')->where(['instance_id' => $instance_id])->value('id');
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
                Operation::create($submit_id, $lv1node['id']);
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
        Db::startTrans();
        try {
            //忽略之前所有未操作
            $map = [
                ['instance_id', '=', $instance_id],
                ['action_type', '=', Operation::ACTION_TYPE_UNEXECUTED]
            ];
            $data = [
                'action_id'   => 0,
                'action_name' => '无需操作',
                'action_type' => Operation::ACTION_TYPE_DISUSE,
                'action_time' => date('Y-m-d H:i:s')
            ];
            Db::table('workflow_operation')->where($map)->update($data);

            if (is_null($submit_id)) {
                $submit_id = Db::table('workflow_submit')->where(['instance_id' => $instance_id])->order(['create_time' => 'DESC'])->value('id', 0);
            }

            //更新之前的提交状态为已处理
            $map = [
                'instance_id' => ['=', $instance_id],
                'id'          => ['<>', $submit_id]
            ];
            Db::table('workflow_submit')->where($map)->update(['is_finish' => 1]);

            $data_instance = [
                'status'      => Instance::STATUS_EXECUTING,
                'is_finish'   => 0,
                'update_time' => date('Y-m-d H:i:s')
            ];
            Db::table('workflow_instance')->where(['id' => $instance_id])->update($data_instance);

            Db::commit();

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
                    Operation::create($submit_id, $lv1node['id']);
                }
            }
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 审批通过
     * @param int $instance_id 方案实例ID
     */
    public static function adopt($instance_id)
    {
        $data_instance = [
            'status'    => Instance::STATUS_ADOPT,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data_instance);

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::adopt($instance_id);
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

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::reject($instance_id);
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

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::goback($instance_id);
    }

    /**
     * 审批挂起
     * @param int $instance_id 实例ID
     */
    public static function hangup($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_HANGUP,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::hangup($instance_id);
    }

    /**
     * 审批中断
     * @param int $instance_id 实例ID
     */
    public static function interrupt($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_INTERRUPT,
            'is_finish' => 0
        ];
        Db::table('workflow_instance')->where(['id' => $instance_id])->update($data);

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::interrupt($instance_id);
    }

    /**
     * 审批取消
     * @param int $instance_id 实例ID
     */
    public static function cancel($instance_id)
    {
        $map = [
            'instance_id' => $instance_id,
            'action_type' => Operation::ACTION_TYPE_UNEXECUTED
        ];
        $data_operation = [
            'action_id'   => 0,
            'action_name' => '已取消',
            'action_type' => Operation::ACTION_TYPE_CANCEL,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where($map)->update($data_operation);

        $data_instance = [
            'status'    => Instance::STATUS_CANCEL,
            'is_finish' => 1
        ];
        Db::table('workflow_instance')->where(['id' > $instance_id])->update($data_instance);

        $scheme_id = Db::table('workflow_instance')->where(['id' => $instance_id])->value('scheme_id');
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();
        /**
         * @var SchemeInterface $scheme_class
         */
        $scheme_class = $scheme['class'];
        $scheme_class::cancel($instance_id);
    }

    /**
     * 继续执行方案实例工作流
     * @param int $instance_id 实例ID
     */
    public static function goon($instance_id)
    {
        $current_operation = Db::table('workflow_operation')->where(['instance_id' => $instance_id])->order(['create_time' => 'DESC'])->find();
        if ($current_operation['action_type'] != Operation::ACTION_TYPE_ADOPT) {
            throw new RuntimeException('goon操作仅允许最后操作节点为通过！');
        }

        $current_node = Db::table('workflow_node')->where(['id' => $current_operation['node_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $current_node['scheme_id']])->find();
        $next_nodes = Db::table('workflow_node')->where([['scheme_id', '=', $scheme['id']], ['level', '=', $current_node['level'] + 1]])->select();
        if (!$next_nodes) {
            //最后一个节点，则执行方案审批通过操作
            self::adopt($instance_id);
        } else {
            foreach ($next_nodes as $next_node) {
                /**
                 * @var NodeInterface $node_class
                 */
                $node_class = $next_node['class'];
                if ($node_class::access($instance_id, 0, $next_node['id'])) {
                    Operation::create($current_operation['submit_id'], $next_node['id']);
                }
            }
        }
    }

    /**
     * 任意追加符合要求的操作
     * @param int $instance_id 实例ID
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @param bool $notice 是否发送提醒
     * @return int 返回操作ID
     */
    public function append($instance_id, $node_id, $user_id = null, $notice = true)
    {
        $current_operation = Db::table('workflow_operation')->where(['instance_id' => $instance_id])->order(['create_time' => 'DESC'])->find();
        $node = Db::table('workflow_node')->where(['id' => $node_id])->find();

        return Operation::create($current_operation['submit_id'], $node['id'], $user_id, $notice);
    }

    /**
     * 再次分配最后执行节点
     * @param int $instance_id 实例ID
     * @param bool $original_user 是否分配给原操作者，默认true
     * @return int 返回操作ID
     */
    public static function again($instance_id, $original_user = true)
    {
        $last_operation = Db::table('workflow_operation')->where(['instance_id' => $instance_id])->order(['create_time' => 'DESC'])->find();

        if (empty($last_operation['node_id'])) {
            throw new RuntimeException('尚未给该实例添加执行动作');
        }

        if ($original_user) {
            return Operation::create($last_operation['submit_id'], $last_operation['node_id'], $last_operation['user_id']);
        } else {
            return Operation::create($last_operation['submit_id'], $last_operation['node_id']);
        }
    }

    /**
     *  取得实例当前的流程状态
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getProcess($instance_id)
    {
        $instance = Db::table('workflow_instance')->where(['id' => $instance_id])->find();
        $last_operation = Db::table('workflow_operation')
            ->where([
                'instance_id' => $instance['id'],
                'action_type' => ['<>', Operation::ACTION_TYPE_SUBMIT]
            ])
            ->order(['create_time' => 'DESC'])
            ->find();

        $nodes = Db::table('workflow_node')
            ->where(['scheme_id' => $instance['scheme_id']])
            ->order(['level' => 'ASC'])
            ->select();

        $processes = [];
        $fire = false;
        foreach ($nodes as $node) {
            if ($node['id'] == $last_operation['node_id']) {
                if ($last_operation['action_type'] != 0) {
                    $processes[] = [
                        'name'   => $node['name'],
                        'active' => true,
                        'done'   => true,
                    ];
                } else {
                    $processes[] = [
                        'name'   => $node['name'],
                        'active' => true,
                        'done'   => false,
                    ];
                }
                $fire = true;
            } else {
                if ($fire) {
                    $processes[] = [
                        'name'   => $node['name'],
                        'active' => false,
                        'done'   => false,
                    ];
                } else {
                    $processes[] = [
                        'name'   => $node['name'],
                        'active' => true,
                        'done'   => true,
                    ];
                }
            }
        }

        return $processes;
    }
}
