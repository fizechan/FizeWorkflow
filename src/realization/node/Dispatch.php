<?php


namespace util\workflow\realization\node;

use think\Db;
use Exception;
use util\workflow\model\Operation;
use util\workflow\ExceptionHandle;

/**
 * 节点审批调度
 */
trait Dispatch
{

    /**
     * 任务调度
     * @param int $operation_id 操作ID
     * @param int $user_id 接收调度的用户ID
     * @param array $form 附加数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function dispatch($operation_id, $user_id, array $form = [])
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

        Db::startTrans();
        try {
            //更新本节点实际操作
            $operation_data = [
                'action_id'   => 0,
                'action_name' => '已调度',
                'action_type' => Operation::ACTION_TYPE_DISPATCH,
                'action_time' => date('Y-m-d H:i:s')
            ];
            $operation_data = array_merge($operation_data, $form);
            Db::name('workflow_operation')->where(['id' => $operation_id])->update($operation_data);

            $to_operation_id = $this->createUserOperation($operation['instance_id'], $operation['contrast_id'], $user_id, $operation['node_id']);
            $this->ignoreBeforeOperation($to_operation_id);

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