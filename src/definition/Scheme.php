<?php

namespace fize\workflow\definition;

use fize\db\Db;
use fize\workflow\model\Instance as InstanceModel;
use fize\workflow\model\Action as ActionModel;
use fize\workflow\model\Node as NodeModel;
use Exception;

/**
 * 方案
 * “方案”实例化后即为“实例”
 */
class Scheme
{
    /**
     * @var int 实例ID
     */
    protected $instanceId;

    /**
     * @var string 最后错误信息
     */
    protected $errMsg = '';

    /**
     * 构造
     * @param int $instance_id 实例ID
     */
    public function __construct($instance_id)
    {
        $this->instanceId = $instance_id;
    }

    /**
     * 获取最后的错误信息
     * @return string
     */
    public function getLastErrMsg()
    {
        return $this->errMsg;
    }

    /**
     * 审批通过
     * @return bool 成功返回true，失败返回false
     */
    public function adopt()
    {
        Db::table('wf_workflow_contrast', '')->where(['instance_id' => $this->instanceId])->update(['is_finish' => 1]);

        $data_instance = [
            'status'    => InstanceModel::STATUS_ADOPT,
            'is_finish' => 1
        ];
        Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->update($data_instance);
        return true;
    }

    /**
     * 审批否决
     * @return bool 成功返回true，失败返回false
     */
    public function reject()
    {
        Db::table('wf_workflow_contrast', '')->where(['instance_id' => $this->instanceId])->update(['is_finish' => 1]);

        $data_instance = [
            'status'    => InstanceModel::STATUS_REJECT,
            'is_finish' => 1
        ];
        Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->update($data_instance);
        return true;
    }

    /**
     * 审批退回
     * @return bool 成功返回true，失败返回false
     */
    public function goback()
    {
        $data_instance = [
            'status'    => InstanceModel::STATUS_GOBACK,
            'is_finish' => 0
        ];
        Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->update($data_instance);
        return true;
    }

    /**
     * 审批挂起
     * @return bool 成功返回true，失败返回false
     */
    public function hangup()
    {
        $data_instance = [
            'status'    => InstanceModel::STATUS_HANGUP,
            'is_finish' => 0
        ];
        Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->update($data_instance);
        return true;
    }

    /**
     * 实例重置到最开始节点
     * @param int $contrast_id 指定提交ID，不指定则为原提交ID
     * @return bool 成功true，错误返回false
     */
    public function reset($contrast_id = null)
    {
        Db::startTrans();
        try {
            //忽略之前所有未操作
            $map = [
                'instance_id' => $this->instanceId,
                'action_type' => ActionModel::TYPE_UNEXECUTED
            ];
            $data = [
                'action_id'   => 0,
                'action_name' => '无需操作',
                'action_type' => ActionModel::TYPE_DISUSE,
                'action_time' => date('Y-m-d H:i:s')
            ];
            Db::table('wf_workflow_operation', '')->where($map)->update($data);

            if(is_null($contrast_id)){
                $contrast_id = Db::table('wf_workflow_contrast', '')->where(['instance_id' => $this->instanceId])->order(['create_time' => 'DESC'])->value('id', 0);
            }
            //更新之前的提交状态为已处理
            $map = [
                'instance_id' => ['=', $this->instanceId],
                'id'          => ['<>', $contrast_id]
            ];
            Db::table('wf_workflow_contrast', '')->where($map)->update(['is_finish' => 1]);

            $data = [
                'status'    => 0,
                'is_finish' => 0,
                'update_on' => date('Y-m-d H:i:s')
            ];
            Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->update($data);

            $instance = Db::table('wf_workflow_instance', '')->where(['id' => $this->instanceId])->find();
            $map = [
                'scheme_id' => $instance['scheme_id'],
                'level'     => 1
            ];
            $lv1nodes = Db::table('wf_workflow_node', '')->where($map)->select();
            foreach ($lv1nodes as $lv1node){
                NodeModel::createOperation($contrast_id, $lv1node['id']);
            }
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 继续执行方案实例工作流
     * @todo 似乎没有这个方法存在的必要
     * @return bool 成功返回true，失败返回false
     */
    public function goon()
    {
        $current_operation = Db::table('wf_workflow_operation', '')->where(['instance_id' => $this->instanceId])->order(['create_time' => 'DESC'])->findOrNull();
        if(!$current_operation){
            $this->errMsg = '找不到该实例最后操作记录！';
            return false;
        }
        if($current_operation['action_type'] != ActionModel::TYPE_ADOPT){
            $this->errMsg = 'goon操作仅允许最后操作节点为通过！';
            return false;
        }

        $current_node = Db::table('wf_workflow_node', '')->where(['id' => $current_operation['node_id']])->findOrNull();
        if(!$current_node){
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        $scheme = Db::table('wf_workflow_scheme', '')->where(['id' => $current_node['scheme_id']])->findOrNull();
        if(!$scheme){
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        $next_nodes = Db::table('wf_workflow_node')->where(['scheme_id' => $scheme['id'], 'level' => $current_node['level'] + 1])->select();
        if(!$next_nodes){
            //最后一个节点，则执行方案审批通过操作
            $this->adopt();
        }else{

            /**
             * @var $current_node_obj Node
             */
            $current_node_obj = new $current_node['class']($current_operation['id']);
            foreach ($next_nodes as $next_node){
                if($current_node_obj->canEnterNextNode($next_node['id'])) {
                    NodeModel::createOperation($current_operation['contrast_id'], $next_node['id']);
                }
            }
            $current_node_obj = null;
        }
        return true;
    }

    /**
     * 任意追加符合要求的操作
     * @param int $node_id 节点ID
     * @param int $user_id 指定工作流用户ID，默认不指定
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function append($node_id, $user_id = null)
    {
        $current_operation = Db::table('wf_workflow_operation', '')->where(['instance_id' => $this->instanceId])->order(['create_time' => 'DESC'])->findOrNull();
        if(!$current_operation){
            $this->errMsg = '找不到该实例最后操作记录！';
            return false;
        }

        $node = Db::table('wf_workflow_node', '')->where(['id' => $node_id])->findOrNull();
        if(!$node){
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        Db::startTrans();
        try {
            if($user_id){
                $next_operation_id = NodeModel::createOperationForUser($current_operation['contrast_id'], $node['id'], $user_id);

                /**
                 * @var $to_node_obj Node
                 */
                $to_node_obj = new $node['class']($next_operation_id);
                $to_node_obj->ignoreBefore();
                $to_node_obj->distribute($user_id);
                $to_node_obj = null;
            }else{
                $next_operation_id = NodeModel::createOperation($current_operation['contrast_id'], $node['id']);

                /**
                 * @var $to_node_obj Node
                 */
                $to_node_obj = new $node['class']($next_operation_id);
                $to_node_obj->ignoreBefore();
                $to_node_obj = null;
            }

            Db::commit();
            return $next_operation_id;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 再次分配最后执行节点
     * @param bool $org_user 是否分配给原操作者，默认true
     * @return mixed 成功返回插入的记录ID，失败返回false
     */
    public function again($org_user = true)
    {
        $last_operation = Db::table('wf_workflow_operation', '')->where(['instance_id' => $this->instanceId])->order(['create_time' =>  'DESC'])->findOrNull();

        if(!$last_operation || empty($last_operation['node_id'])){
            $this->errMsg = '尚未给该实例添加执行动作';
            return false;
        }

        $node = Db::table('wf_workflow_node', '')->where(['id' => $last_operation['node_id']])->findOrNull();
        if(!$node){
            $this->errMsg = '找不到该实例最后操作对应节点！';
            return false;
        }

        Db::startTrans();
        try {
            if($org_user){  //马上分配给原操作者
                $next_operation_id = NodeModel::createOperationForUser($last_operation['contrast_id'], $node['id'], $last_operation['user_id']);

                /**
                 * @var $to_node_obj Node
                 */
                $to_node_obj = new $node['class']($next_operation_id);
                $to_node_obj->ignoreBefore();
                $to_node_obj->distribute($last_operation['user_id']);
                $to_node_obj = null;
            }else{
                $next_operation_id = NodeModel::createOperation($last_operation['contrast_id'], $node['id']);

                /**
                 * @var $to_node_obj Node
                 */
                $to_node_obj = new $node['class']($next_operation_id);
                $to_node_obj->ignoreBefore();
                $to_node_obj = null;
            }
            Db::commit();
            return $next_operation_id;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }
}