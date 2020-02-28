<?php


namespace util\workflow\realization\node;

use util\workflow\definition\Scheme;
use think\Db;
use Exception;
use util\workflow\model\Operation;
use util\workflow\ExceptionHandle;

/**
 * 节点审批退回
 */
trait Goback
{

    /**
     * @var Scheme
     */
    private $gobackScheme;

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param int $operation_id 操作ID
     * @param array $form 数据数组
     * @param int $to_node_id 返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @return bool 操作成功返回true，失败返回false
     */
    public function goback($operation_id, array $form, $to_node_id = null, $to_operation_id = null)
    {
        if (is_null($to_node_id) && is_null($to_operation_id)) {
            $this->errMsg = '节点ID和操作ID必须指定1个！';
            return false;
        }
        if (!is_null($to_node_id) && !is_null($to_operation_id)) {
            $this->errMsg = '节点ID和操作ID不能同时指定！';
            return false;
        }

        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [Operation::ACTION_TYPE_UNEXECUTED, Operation::ACTION_TYPE_HANGUP])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }

        $instance = Db::name('workflow_instance')->where('id', '=', $operation['instance_id'])->find();
        if (!$instance) {
            $this->errMsg = '找不到该操作对应工作流实例！';
            return false;
        }
        $scheme = Db::name('workflow_scheme')->where('id', '=', $instance['scheme_id'])->find();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        Db::startTrans();
        try {
            $this->dealAction($operation_id, $form);
            $this->ignoreBeforeOperation($operation_id);

            if (is_numeric($to_node_id)) {
                //以节点ID来进行退回操作
                if ($to_node_id == 0) {
                    //项目退回
                    $this->gobackScheme = new $scheme['class']();
                    if (!$this->gobackScheme->goback($instance['id'])) {
                        $this->errMsg = $this->gobackScheme->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $this->gobackScheme = null;
                } else {
                    //退回到指定节点

                    //直接指定为原来的用户
                    $to_operation = Db::name('workflow_operation')->where('node_id', '=', $to_node_id)->order('action_time', 'DESC')->find();
                    if (!$to_operation) {
                        $this->errMsg = '找不到该退回目标操作记录！';
                        return false;
                    }
                    //实时分配
                    $this->createUserOperation($to_operation['instance_id'], $to_operation['contrast_id'], $to_operation['user_id'], $to_operation['node_id']);
                }
            } else {
                //以操作ID来进行退回操作
                if ($to_operation_id == 0) {
                    //项目退回
                    $this->gobackScheme = new $scheme['class']();
                    if (!$this->gobackScheme->goback($instance['id'])) {
                        $this->errMsg = $this->gobackScheme->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $this->gobackScheme = null;
                } else {
                    //退回到指定操作点
                    $to_operation = Db::name('workflow_operation')->where([['id', '=', $operation_id], ['instance_id', '=', $operation['instance_id']]])->find();
                    if (!$to_operation) {
                        $this->errMsg = '找不到该退回目标操作记录！';
                        return false;
                    }
                    //实时分配
                    $this->createUserOperation($to_operation['instance_id'], $to_operation['contrast_id'], $to_operation['user_id'], $to_operation['node_id']);
                }
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
}
