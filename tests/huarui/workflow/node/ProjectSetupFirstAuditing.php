<?php
namespace huarui\workflow\node;

use fize\workflow\NodeLine;

/**
 * 项目立项初审
 */
class ProjectSetupFirstAuditing extends NodeLine
{

    /**
     * 用于进行审批通过前的一系列操作
     * 通过改写该方法来实现审批前的条件判断
     * @param $user_id
     * @param $operation_id
     * @param array $form
     * @param array $node_user_tos
     * @return bool 返回false时将不再继续执行adopt流程
     */
    protected function beforeAdopt($user_id, $operation_id, array $form = [], array $node_user_tos = [])
    {
        $user_quota = $this->orm->table('gm_workflow_user_quota')->where(['user_id' => $user_id])->find();
        if(!$user_quota){
            $this->errmsg = '分配工作流任务时发生错误：该指定用户尚未指定审核额度，无法分配！';
            return false;
        }
        $operation = $this->orm->table('gm_workflow_operation')->where(['id' => $operation_id])->find();
        if(!$operation){
            $this->errmsg = '分配工作流任务时发生错误：该指定用户尚未指定节点操作记录，无法分配！';
            return false;
        }

        $project = $this->orm->table('gm_project')->where(['workflow_instance_id' => $operation['instance_id']])->find();
        if($user_quota['current_quota'] < $project['amount']){
            $this->errmsg = '分配工作流任务时发生错误：该项目额度超过用户审核额度，无法分配！';
            return false;
        }
        return true;
    }

    /**
     * 用于进行审批通过后的一系列操作
     * 通过改写该方法来实现审批通过后的一系列操作
     * @param $user_id
     * @param $operation_id
     * @param array $form
     * @param array $node_user_tos
     * @return bool 返回false表示执行不成功
     */
    protected function afterAdopt($user_id, $operation_id, array $form = [], array $node_user_tos = [])
    {
        $this->orm->table('gm_project_auditing_log')->insert($form);
        return true;
    }

    /**
     * 用于下级任务分发前的条件判断
     * @param $current_node
     * @param $next_node
     * @param $current_user_id
     * @param $next_user_id
     * @return bool
     */
    protected function beforeDistribute($current_node, $next_node, $current_user_id, $next_user_id)
    {
        return true;
    }

    /**
     * 用于下级任务分发后的执行动作
     * @param $current_node
     * @param $next_node
     * @param $current_user_id
     * @param $next_user_id
     * @return bool
     */
    protected function afterDistribute($current_node, $next_node, $current_user_id, $next_user_id)
    {
        return true;
    }
}