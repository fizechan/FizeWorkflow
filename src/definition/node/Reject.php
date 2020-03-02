<?php


namespace util\workflow\realization\node;

use util\workflow\definition\Scheme;
use util\workflow\definition\Node;
use think\Db;
use Exception;
use util\workflow\model\Operation;
use util\workflow\ExceptionHandle;

/**
 * 节点审批否决
 */
trait Reject
{
    /**
     * @var Scheme
     */
    private $rejectScheme;

    /**
     * @var Node
     */
    private $rejectNextNode;

    /**
     * 审核否决
     * 否决后默认是执行了方案否决方法，但是也可以重写该方法来执行特殊事务
     * @param int $operation_id 操作ID
     * @param array $form 表单数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function reject($operation_id, array $form)
    {
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

            //直接执行方案[审批否决]操作
            $this->rejectScheme = new $scheme['class']();
            if (!$this->rejectScheme->reject($instance['id'])) {
                $this->errMsg = $this->rejectScheme->getLastErrMsg();
                Db::rollback();
                return false;
            }
            $this->rejectScheme = null;
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
