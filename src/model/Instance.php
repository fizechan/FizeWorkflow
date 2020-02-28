<?php


namespace util\workflow\model;


use think\Db;
use util\workflow\definition\Scheme;

/**
 * 工作流实例
 */
class Instance
{

    /**
     * 状态：执行中
     */
    const STATUS_DOING = 0;

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
     * @var Scheme
     */
    private static $scheme;

    /**
     *  取得实例当前的流程状态
     * @param int $id 实例ID
     * @return array
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
     */
    public static function bindExtendRelation($instance_id, $extend_relation)
    {
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update(['extend_relation' => $extend_relation]);
        Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->update(['extend_relation' => $extend_relation]);
    }

    /**
     * 提交一个审核实例，含重新提交
     * @param int $scheme_id 指定方案ID
     * @param array $form 传入的表单参数数组
     * @param array $attachs 可选的附件列表
     * @param callable $beforeDone 实例执行工作流前回调方法
     * @param string $instance_name 指定实例名，重新提交时该参数无效
     * @param int $instance_id 实例ID，指定该参数时表示重新提交
     * @return array ['instance_id' => $val, 'contrast_id' => $val]
     */
    public static function submit($scheme_id, array $form, array $attachs = null, callable $beforeDone = null, $instance_name = '', $instance_id = null)
    {
        $scheme = Db::name('workflow_scheme')->where('id', '=', $scheme_id)->find();
        self::$scheme = new $scheme['class']();

        if ($instance_id) {  //退回的再次提交
            $contrast_id = self::$scheme->instanceContrast($instance_id, $form, $attachs);
            self::$scheme->reset($instance_id, $contrast_id);
        } else {  //首次提交
            $instance_id = self::$scheme->instance($instance_name, $scheme_id);
            $contrast_id = self::$scheme->instanceContrast($instance_id, $form, $attachs);
            if ($beforeDone) {
                $beforeDone();
            }
            self::$scheme->instanceDone($instance_id, $contrast_id);
        }
        return ['instance_id' => $instance_id, 'contrast_id' => $contrast_id];
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
     * 实例重置到最开始节点
     * @param int $instance_id 实例ID
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     * @return array [$result, $errmsg]
     */
    public static function reset($instance_id, $contrast_id = null)
    {
        $scheme_id = Db::name('workflow_instance')->where('id', '=', $instance_id)->value('scheme_id');
        $scheme = Db::name('workflow_scheme')->where('id', '=', $scheme_id)->find();
        self::$scheme = new $scheme['class']();
        $result = self::$scheme->reset($instance_id, $contrast_id);
        return [$result, self::$scheme->getLastErrMsg()];
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
