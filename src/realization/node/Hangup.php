<?php


namespace util\workflow\realization\node;

use util\workflow\definition\Scheme;
use think\Db;
use Exception;
use util\workflow\ExceptionHandle;

/**
 * 节点审批挂起
 */
trait Hangup
{

    /**
     * @var Scheme
     */
    private $hangupScheme;

    /**
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param int $operation_id 操作ID
     * @param array $form 数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function hangup($operation_id, array $form = null)
    {
        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [0])) {
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

            //项目挂起
            $this->hangupScheme = new $scheme['class']();
            if (!$this->hangupScheme->hangup($instance['id'])) {
                $this->errMsg = $this->hangupScheme->getLastErrMsg();
                Db::rollback();
                return false;
            }
            $this->hangupScheme = null;
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
