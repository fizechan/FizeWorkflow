<?php


namespace util\workflow\realization\node;

use util\workflow\definition\Scheme;
use util\workflow\definition\Node;
use think\Db;
use Exception;
use util\workflow\model\Operation;
use util\workflow\ExceptionHandle;

/**
 * 节点审批通过
 */
trait Adopt
{
    /**
     * @var Scheme
     */
    private $adoptScheme;

    /**
     * @var Node
     */
    private $adoptNextNode;

    /**
     * 用于判断是否可以进行下级[审批通过]任务分发
     * 通过改写该方法来实现审批通过后是否马上生成下一层级操作
     * @param int $operation_id 操作ID
     * @return bool
     */
    protected function canNextAdopt($operation_id)
    {
        return true;
    }

    /**
     * 审批通过
     * @param int $operation_id 操作ID
     * @param array $form 提交的完整表单
     * @param array $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @return bool 操作成功返回true，失败返回false
     */
    public function adopt($operation_id, array $form, array $node_user_tos = [])
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
        $node = Db::name('workflow_node')->where(['id' => $operation['node_id']])->find();
        if (!$node) {
            $this->errMsg = '找不到该操作对应节点记录！';
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

            $map = [
                ['scheme_id', '=', $instance['scheme_id']],
                ['level', '=', $node['level'] + 1]
            ];
            $next_nodes = Db::name('workflow_node')->where($map)->select();
            if (!$next_nodes) {  //最后一个节点，则执行方案审批通过操作
                if ($this->canNextAdopt($operation_id)) {
                    $this->adoptScheme = new $scheme['class']();
                    if (!$this->adoptScheme->adopt($instance['id'])) {
                        $this->errMsg = $this->adoptScheme->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $this->adoptScheme = null;
                }
            } else {
                if ($this->canNextAdopt($operation_id)) {
                    if ($node_user_tos) {
                        //直接指定了下级接收者，则马上进行分配
                        foreach ($node_user_tos as $to_node_id => $to_user_id) {
                            $this->createUserOperation($instance['id'], $operation['contrast_id'], $to_user_id, $to_node_id);
                        }
                    } else {
                        foreach ($next_nodes as $next_node) {
                            $this->adoptNextNode = new $next_node['class']();
                            if ($this->adoptNextNode->access($instance['id'], $operation_id, $next_node['id'])) {
                                $this->adoptNextNode->createOperation($instance['id'], $operation['contrast_id'], $next_node['id']);
                            }
                            $this->adoptNextNode = null;
                        }
                    }
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
