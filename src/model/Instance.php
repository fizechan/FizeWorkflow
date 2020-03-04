<?php


namespace fize\workflow\model;


use RuntimeException;
use fize\crypt\Json;
use fize\misc\Preg;
use fize\workflow\Db;
use fize\workflow\SchemeInterface;
use fize\workflow\NodeInterface;
use util\workflow\definition\Scheme;

/**
 * 实例
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
     * @var Scheme
     */
    private static $scheme;

    /**
     *  取得实例当前的流程状态
     * @param int $id 实例ID
     * @return array
     * @todo 待修改
     */
    public static function getProcess($id)
    {
        $instance = Db::name('workflow_instance')->where('id', '=', $id)->find();
        $last_operation = Db::name('workflow_operation')
            ->where('instance_id', '=', $instance['id'])
            ->where('action_type', '<>', Operation::ACTION_TYPE_SUBMIT)
            ->order('create_time', 'DESC')
            ->find();

        $nodes = Db::name('workflow_node')
            ->where('scheme_id', '=', $instance['scheme_id'])
            ->order('level', 'ASC')
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

    /**
     * 绑定外部关联ID
     * @param int $instance_id 工作流实例ID
     * @param string $extend_relation 外部关联字段值
     * @todo 待修改
     */
    public static function bindExtendRelation($instance_id, $extend_relation)
    {
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update(['extend_relation' => $extend_relation]);
        Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->update(['extend_relation' => $extend_relation]);
    }

    /**
     * 判断是否有同类型的工作流未完成
     * @param int $extend_id 外部ID
     * @param string $scheme_type 方案类型
     * @return bool
     */
    public static function hasNoFinished($extend_id, $scheme_type)
    {
        $map = [
            ['scheme_type', '=', $scheme_type],
            ['extend_id', '=', $extend_id],
            ['is_finish', '=', 0]
        ];
        $row = Db::name('workflow_instance')->where($map)->find();
        if ($row) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 取消实例，并终止所有审批
     * @param int $instance_id 实例ID
     */
    public static function cancel($instance_id)
    {
        Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->update(['is_finish' => 1]);

        $map = [
            ['instance_id', '=', $instance_id],
            ['action_type', '=', Operation::ACTION_TYPE_UNEXECUTED]
        ];
        $data_operation = [
            'action_id'   => 0,
            'action_name' => '已取消',
            'action_type' => Operation::ACTION_TYPE_CANCEL,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::name('workflow_operation')->where($map)->update($data_operation);

        $data_instance = [
            'status'    => Operation::ACTION_TYPE_CANCEL,
            'is_finish' => 1
        ];
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data_instance);
    }


    /**
     * 再次分配最后执行节点
     * @param int $instance_id 实例ID
     * @param bool $org_user 是否分配给原操作者，默认true
     * @return array [$result, $errmsg]
     */
    public static function again($instance_id, $org_user = true)
    {
        $scheme_id = Db::name('workflow_instance')->where('id', '=', $instance_id)->value('scheme_id');
        $scheme = Db::name('workflow_scheme')->where('id', '=', $scheme_id)->find();
        self::$scheme = new $scheme['class']();
        $result = self::$scheme->again($instance_id, $org_user);
        return [$result, self::$scheme->getLastErrMsg()];
    }
}
