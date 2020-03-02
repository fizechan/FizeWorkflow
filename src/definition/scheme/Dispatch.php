<?php


namespace util\workflow\realization\scheme;

use think\Db;
use Exception;
use util\workflow\definition\Node;
use util\workflow\model\Operation;
use util\workflow\ExceptionHandle;

/**
 * Trait 方案调度
 */
trait Dispatch
{

    /**
     * @var Node
     */
    private $dispatchNode;

    /**
     * 实例重置到最开始节点
     * @param int $instance_id 实例ID
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     * @return bool 成功true，错误返回false
     */
    public function reset($instance_id, $contrast_id = null)
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
            Db::name('workflow_operation')->where($map)->update($data);

            if (is_null($contrast_id)) {
                $contrast_id = Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->order('create_on', 'DESC')->value('id', 0);
            }
            //更新之前的提交状态为已处理
            $map = [
                ['instance_id', '=', $instance_id],
                ['id', '<>', $contrast_id]
            ];
            Db::name('workflow_contrast')->where($map)->update(['is_finish' => 1]);

            $data = [
                'status'    => 0,
                'is_finish' => 0,
                'update_on' => date('Y-m-d H:i:s')
            ];
            Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data);

            $instance = Db::name('workflow_instance')->where('id', '=', $instance_id)->find();
            $map = [
                ['scheme_id', '=', $instance['scheme_id']],
                ['level', '=', 1]
            ];
            $lv1nodes = Db::name('workflow_node')->where($map)->select();
            foreach ($lv1nodes as $lv1node) {
                $this->dispatchNode = new $lv1node['class']();
                if ($this->dispatchNode->access($instance_id, 0, $lv1node['id'])) {
                    $this->dispatchNode->createOperation($instance_id, $contrast_id, $lv1node['id']);
                }
                $this->dispatchNode = null;
            }
            Db::commit();
            return true;
        } catch (Exception $e) {
            ExceptionHandle::report($e);
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 继续执行方案实例工作流
     * @param int $instance_id 实例ID
     * @return bool 成功返回true，失败返回false
     */
    public function goon($instance_id)
    {
        $current_operation = Db::name('workflow_operation')->where(['instance_id' => $instance_id])->order('create_time', 'DESC')->find();
        if (!$current_operation) {
            $this->errMsg = '找不到该实例最后操作记录！';
            return false;
        }
        if ($current_operation['action_type'] != Operation::ACTION_TYPE_ADOPT) {
            $this->errMsg = 'goon操作仅允许最后操作节点为通过！';
            return false;
        }

        $current_node = Db::name('workflow_node')->where('id', '=', $current_operation['node_id'])->find();
        if (!$current_node) {
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        $scheme = Db::name('workflow_scheme')->where('id', '=', $current_node['scheme_id'])->find();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        $next_nodes = Db::name('workflow_node')->where([['scheme_id', '=', $scheme['id']], ['level', '=', $current_node['level'] + 1]])->select();
        if (!$next_nodes) {
            //最后一个节点，则执行方案审批通过操作
            $this->adopt($instance_id);
        } else {
            foreach ($next_nodes as $next_node) {
                $this->dispatchNode = new $next_node['class']();
                if ($this->dispatchNode->access($instance_id, 0, $next_node['id'])) {
                    $this->dispatchNode->createOperation($instance_id, $current_operation['contrast_id'], $next_node['id']);
                }
                $this->dispatchNode = null;
            }
        }
        return true;
    }

    /**
     * 任意追加符合要求的操作
     * @param int $instance_id 实例ID
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function append($instance_id, $node_id, $user_id = null)
    {
        $current_operation = Db::name('workflow_operation')->where(['instance_id' => $instance_id])->order('create_time', 'DESC')->find();
        if (!$current_operation) {
            $this->errMsg = '找不到该实例最后操作记录！';
            return false;
        }

        $node = Db::name('workflow_node')->where('id', '=', $node_id)->find();
        if (!$node) {
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        Db::startTrans();
        try {
            $this->dispatchNode = new $node['class']();

            if ($user_id) {
                $operation_id = $this->dispatchNode->createUserOperation($instance_id, $current_operation['contrast_id'], $user_id, $node['id']);
            } else {
                $operation_id = $this->dispatchNode->createOperation($instance_id, $current_operation['contrast_id'], $node['id']);
            }
            $this->dispatchNode->ignoreBeforeOperation($operation_id);
            $this->dispatchNode = null;
            Db::commit();
            return $operation_id;
        } catch (Exception $e) {
            ExceptionHandle::report($e);
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 再次分配最后执行节点
     * @param int $instance_id 实例ID
     * @param bool $org_user 是否分配给原操作者，默认true
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function again($instance_id, $org_user = true)
    {
        $last_operation = Db::name('workflow_operation')->where('instance_id', '=', $instance_id)->order('create_time', 'DESC')->find();

        if (!$last_operation || empty($last_operation['node_id'])) {
            $this->errMsg = '尚未给该实例添加执行动作';
            return false;
        }

        $node = Db::name('workflow_node')->where('id', '=', $last_operation['node_id'])->find();
        if (!$node) {
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        Db::startTrans();
        try {
            $this->dispatchNode = new $node['class']();

            if ($org_user) {  //马上分配给原操作者
                $operation_id = $this->dispatchNode->createUserOperation($instance_id, $last_operation['contrast_id'], $last_operation['user_id'], $node['id']);
            } else {
                $operation_id = $this->dispatchNode->createOperation($instance_id, $last_operation['contrast_id'], $node['id']);
            }
            Db::commit();
            $this->dispatchNode->ignoreBeforeOperation($operation_id);
            return $operation_id;
        } catch (Exception $e) {
            ExceptionHandle::report($e);
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }
}
