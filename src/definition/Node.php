<?php

namespace util\workflow\definition;

use think\Db;
use util\workflow\model\Operation;
use util\workflow\realization\node\Distribute;
use util\workflow\realization\node\Adopt;
use util\workflow\realization\node\Reject;
use util\workflow\realization\node\Goback;
use util\workflow\realization\node\Hangup;
use util\workflow\realization\node\Dispatch;

/**
 * 工作流节点操作抽象类
 */
class Node
{

    use Distribute;
    use Adopt;
    use Reject;
    use Goback;
    use Hangup;
    use Dispatch;

    /**
     * 执行操作的统一处理
     * 通过改写该方法来实现执行操作的一系列操作
     * @param int $operation_id 操作ID
     * @param array $form 表单数据
     */
    protected function dealAction($operation_id, array $form)
    {
        $action_id = isset($form['workflow_action_id']) ? $form['workflow_action_id'] : 0;
        Operation::action($operation_id, $action_id, $form);
    }

    /**
     * 工作流动作统一执行
     * @param int $operation_id 操作ID
     * @param array $form 表单数据(含自定义)
     * @param int $action_id 自定义操作ID,可以为0表示任意自定义
     * @param int $action_type 操作类型，请从NodeAction中选择
     * @param string $action_name 自定义操作名称
     * @return bool
     */
    public function execute($operation_id, array $form, $action_id = null, $action_type = null, $action_name = null)
    {
        if (!is_null($action_id)) {
            $form['workflow_action_id'] = $action_id;
        }
        $form['workflow_action_id'] = isset($form['workflow_action_id']) ? $form['workflow_action_id'] : 0;
        if ($form['workflow_action_id']) {  //指定了action
            $node_action = Db::name('workflow_node_action')->where('id', '=', $form['workflow_action_id'])->find();
            $form['workflow_action_type'] = $node_action['action_type'];
            $form['workflow_action_name'] = $node_action['action_name'];
        }
        $form['workflow_action_type'] = isset($form['workflow_action_type']) ? $form['workflow_action_type'] : Operation::ACTION_TYPE_UNEXECUTED;
        if (!is_null($action_type)) {
            $form['workflow_action_type'] = $action_type;
        }
        if (!is_null($action_name)) {
            $form['workflow_action_name'] = $action_name;
        }

        if ($form['workflow_action_type'] == Operation::ACTION_TYPE_ADOPT) {  //通过
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '通过';
            return $this->adopt($operation_id, $form);
        } elseif ($form['workflow_action_type'] == Operation::ACTION_TYPE_REJECT) {  //否决
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '否决';
            return $this->reject($operation_id, $form);
        } elseif ($form['workflow_action_type'] == Operation::ACTION_TYPE_GOBACK) {  //退回
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '退回';
            $form['workflow_back_node'] = isset($form['workflow_back_node']) ? $form['workflow_back_node'] : 0;
            $to_node_id = isset($form['workflow_back_node']) ? $form['workflow_back_node'] : null;
            $to_operation_id = isset($form['workflow_to_operation_id']) ? $form['workflow_to_operation_id'] : null;
            return $this->goback($operation_id, $form, $to_node_id, $to_operation_id);
        } elseif ($form['workflow_action_type'] == Operation::ACTION_TYPE_HANGUP) {  //挂起
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '挂起';
            return $this->hangup($operation_id, $form);
        } elseif ($form['workflow_action_type'] == Operation::ACTION_TYPE_DISPATCH) {  //调度
            if (!isset($form['workflow_user_id']) || empty($form['workflow_user_id'])) {
                $this->errMsg = "调度操作必须指定接收用户ID";
                return false;
            }
            return $this->dispatch($operation_id, $form['workflow_user_id'], $form);
        }

        $this->errMsg = "不支持的操作类型:{$form['workflow_action_type']}";
        return false;
    }
}
