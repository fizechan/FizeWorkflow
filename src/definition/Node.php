<?php

namespace fize\workflow\definition;


use fize\db\Db;
use fize\crypt\Json;
use fize\workflow\model\Action as ActionModel;
use fize\workflow\model\Operation as OperationModel;
use fize\workflow\model\Node as NodeModel;
use Exception;

/**
 * 节点
 * “节点”实例化后即为“操作”
 */
class Node
{
    /**
     * @var int 操作ID
     */
    protected $operationId;

    /**
     * @var string 最后错误信息
     */
    protected $errMsg = '';

    /**
     * 构造
     * @param int $operation_id 操作ID
     */
    public function __construct($operation_id)
    {
        $this->operationId = $operation_id;
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
     * 取出一个适合的用户ID用于任务分发
     * 子类复写该方法可以任意指定要分配的用户
     * @todo 可用方案，目前先使用随机分配给其可用账号，暂未考虑其已有未完成任务的情况
     * @return mixed 有适合的用户ID则返回，没有则返回null
     */
    protected function getSuitableUserId()
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->find();
        $sql = <<<EOF
SELECT wf_workflow_user_role.user_id
FROM wf_workflow_user_role
LEFT JOIN wf_workflow_node_role ON wf_workflow_node_role.role_id = wf_workflow_user_role.role_id
WHERE wf_workflow_node_role.node_id = {$operation['node_id']}
AND wf_workflow_user_role.user_id IS NOT NULL
ORDER BY RAND()
LIMIT 1
EOF;
        $users = Db::query($sql);
        if (!$users) {
            return null;
        }
        return $users[0]['user_id'];
    }

    /**
     * 成功分配用户后该方法将触发
     * 子类复写该方法以实现分配后的相关操作(如通知等)
     */
    protected function afterDistribute()
    {
    }

    /**
     * 分配用户
     * @param int $user_id 指定接收用户ID
     * @return bool 操作成功返回true，失败返回false
     */
    public function distribute($user_id = null)
    {
        if (is_null($user_id)) {
            $user_id = $this->getSuitableUserId();
            if (!$user_id) {
                $this->errMsg = '找不到该合适的用户！';
                return false;
            }
        }
        $user = Db::table('wf_workflow_user', '')->where(['id' => $user_id])->find();
        $operation_data = [
            'user_id'         => $user['id'],
            'user_extend_id'  => $user['extend_id'],
            'distribute_time' => date('Y-m-d H:i:s')
        ];
        Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->update($operation_data);
        $this->afterDistribute();
        return true;
    }

    /**
     * 对之前操作节点进行[无需操作]处理
     * @param int $operation_id 操作ID,未指定则为当前操作ID
     */
    public function ignoreBefore($operation_id = null)
    {
        $operation_id = $operation_id ? $operation_id : $this->operationId;
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $operation_id])->find();
        $map = [
            'instance_id' => ['=', $operation['instance_id']],
            'create_time' => ['<', $operation['create_time']],
            'action_type' => ['=', ActionModel::TYPE_UNEXECUTED]
        ];
        $data = [
            'action_id'   => 0,
            'action_name' => '无需操作',
            'action_type' => ActionModel::TYPE_DISUSE,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::table('wf_workflow_operation', '')->where($map)->update($data);
    }

    /**
     * 用于判断是否可以进行下级任务分发
     * 通过改写该方法来实现操作后是否马上生成下一层级操作
     * @return bool
     */
    protected function canNextDistribute()
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->find();
        $current_node = Db::table('wf_workflow_node', '')->where(['id' => $operation['node_id']])->find();
        $map = [
            'scheme_id' => $operation['scheme_id'],
            'level'     => $current_node['level']
        ];
        $node_ids = Db::table('wf_workflow_node', '')->where($map)->column('id');
        $allow_actiontypes = [
            ActionModel::TYPE_ADOPT,
            ActionModel::TYPE_DISUSE
        ];
        $map = [
            'instance_id' => ['=', $operation['instance_id']],
            'node_id' => ['IN', $node_ids],
            'action_type' => ['NOT IN', $allow_actiontypes]
        ];

        $dirty_rows = Db::table('wf_workflow_operation', '')->where($map)->findOrNull();
        if($dirty_rows){
            return false;
        }
        return true;
    }

    /**
     * 用于判断是否可以进入指定的下级节点任务分发
     * @param int $node_id 节点ID
     * @return bool
     */
    public function canEnterNextNode($node_id)
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->find();
        $current_node = Db::table('wf_workflow_node', '')->where(['id' => $operation['node_id']])->find();
        $map = [
            'scheme_id' => $operation['scheme_id'],
            'level'     => $current_node['level'] + 1,
            'id'        => $node_id
        ];
        $next_node = Db::table('wf_workflow_node', '')->where($map)->findOrNull();
        if(!$next_node){
            return false;
        }
        return true;
    }

    /**
     * 审批通过
     * @param array $form 提交的完整表单
     * @param array $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @return bool 操作成功返回true，失败返回false
     */
    public function adopt(array $form, array $node_user_tos = [])
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->findOrNull();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [ActionModel::TYPE_UNEXECUTED, ActionModel::TYPE_HANGUP])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }
        $instance = Db::table('wf_workflow_instance', '')->where(['id' => $operation['instance_id']])->findOrNull();
        if (!$instance) {
            $this->errMsg = '找不到该操作对应工作流实例！';
            return false;
        }
        $node = Db::table('wf_workflow_node', '')->where(['id' => $operation['node_id']])->findOrNull();
        if (!$node) {
            $this->errMsg = '找不到该操作对应节点记录！';
            return false;
        }
        $scheme = Db::table('wf_workflow_scheme', '')->where(['id' => $instance['scheme_id']])->findOrNull();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        Db::startTrans();
        try {
            $this->action($form);
            $this->ignoreBefore();

            $map = [
                'scheme_id' => [ '=', $instance['scheme_id']],
                'level'     => [ '=', $node['level'] + 1]
            ];
            $next_nodes = Db::table('wf_workflow_node', '')->where($map)->select();
            if (!$next_nodes) {  //最后一个节点，则执行方案审批通过操作
                if ($this->canNextDistribute()) {
                    /**
                     * @var $current_scheme_obj Scheme
                     */
                    $current_scheme_obj = new $scheme['class']($instance['id']);
                    if (!$current_scheme_obj->adopt()) {
                        $this->errMsg = $current_scheme_obj->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $current_scheme_obj = null;
                }
            } else {
                if ($this->canNextDistribute()) {

                    foreach ($next_nodes as $next_node) {
                        if($this->canEnterNextNode($next_node['id'])) {
                            if(isset($node_user_tos[$next_node['id']])) {  //直接指定了下级接收者，则马上进行分配
                                $next_operation_id = NodeModel::createOperationForUser($operation['contrast_id'], $next_node['id'], $node_user_tos[$next_node['id']]);
                                /**
                                 * @var $to_node_obj Node
                                 */
                                $to_node_obj = new $next_node['class']($next_operation_id);
                                $to_node_obj->distribute($node_user_tos[$next_node['id']]);
                                $to_node_obj = null;
                            } else {
                                NodeModel::createOperation($operation['contrast_id'], $next_node['id']);
                            }
                        }
                    }
                }
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
     * 审核否决
     * 否决后默认是执行了方案否决方法，但是也可以重写该方法来执行特殊事务
     * @param array $form 表单数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function reject(array $form)
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->findOrNull();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [ActionModel::TYPE_UNEXECUTED, ActionModel::TYPE_HANGUP])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }
        $instance = Db::table('wf_workflow_instance', '')->where(['id' => $operation['instance_id']])->findOrNull();
        if (!$instance) {
            $this->errMsg = '找不到该操作对应工作流实例！';
            return false;
        }
        $scheme = Db::table('wf_workflow_scheme', '')->where(['id' => $instance['scheme_id']])->findOrNull();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }
        Db::startTrans();
        try {
            $this->action($form);
            $this->ignoreBefore();

            //直接执行方案[审批否决]操作
            /**
             * @var $current_scheme_obj Scheme
             */
            $current_scheme_obj = new $scheme['class']($instance['id']);
            if (!$current_scheme_obj->reject()) {
                $this->errMsg = $current_scheme_obj->getLastErrMsg();
                Db::rollback();
                return false;
            }
            $current_scheme_obj = null;

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param array $form 数据数组
     * @param int $to_node_id 返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @return bool 操作成功返回true，失败返回false
     */
    public function goback(array $form, $to_node_id = null, $to_operation_id = null)
    {
        if(is_null($to_node_id) && is_null($to_operation_id)){
            $this->errMsg = '节点ID和操作ID必须指定1个！';
            return false;
        }
        if(!is_null($to_node_id) && !is_null($to_operation_id)){
            $this->errMsg = '节点ID和操作ID不能同时指定！';
            return false;
        }

        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->findOrNull();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [ActionModel::TYPE_UNEXECUTED, ActionModel::TYPE_HANGUP])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }

        $instance = Db::table('wf_workflow_instance', '')->where(['id' => $operation['instance_id']])->findOrNull();
        if (!$instance) {
            $this->errMsg = '找不到该操作对应工作流实例！';
            return false;
        }
        $scheme = Db::table('wf_workflow_scheme', '')->where(['id' => $instance['scheme_id']])->findOrNull();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        Db::startTrans();
        try {
            $this->action($form);
            $this->ignoreBefore();

            if (is_numeric($to_node_id)) {  //以节点ID来进行退回操作
                if ($to_node_id == 0) {  //项目退回
                    /**
                     * @var $current_scheme_obj Scheme
                     */
                    $current_scheme_obj = new $scheme['class']($instance['id']);
                    if (!$current_scheme_obj->goback()) {
                        $this->errMsg = $current_scheme_obj->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $current_scheme_obj = null;
                } else {  //退回到指定节点
                    $to_operation = Db::table('wf_workflow_operation', '')->where(['node_id' => $to_node_id])->order(['action_time' => 'DESC'])->findOrNull();
                    if (!$to_operation) {
                        $this->errMsg = '找不到该退回目标操作记录！';
                        return false;
                    }

                    //默认是分配个最后一个操作人员
                    $real_to_operation_id = NodeModel::createOperationForUser($to_operation['contrast_id'], $to_operation['node_id'], $to_operation['user_id']);
                    $to_node = Db::table('wf_workflow_node', '')->where(['id' => $to_operation['node_id']])->find();

                    /**
                     * @var $to_node_obj Node
                     */
                    $to_node_obj = new $to_node['class']($real_to_operation_id);
                    $to_node_obj->distribute($to_operation['user_id']);
                    $to_node_obj = null;
                }
            } else {  //以操作ID来进行退回操作
                if ($to_operation_id == 0) {  //项目退回
                    /**
                     * @var $current_scheme_obj Scheme
                     */
                    $current_scheme_obj = new $scheme['class']($instance['id']);
                    if (!$current_scheme_obj->goback()) {
                        $this->errMsg = $current_scheme_obj->getLastErrMsg();
                        Db::rollback();
                        return false;
                    }
                    $current_scheme_obj = null;
                } else {  //退回到指定操作点
                    $to_operation = Db::table('wf_workflow_operation', '')->where(['id' => $to_operation_id, 'instance_id' => $operation['instance_id']])->find();
                    if (!$to_operation) {
                        $this->errMsg = '找不到该退回目标操作记录！';
                        return false;
                    }
                    $real_to_operation_id = NodeModel::createOperationForUser($to_operation['contrast_id'], $to_operation['node_id'], $to_operation['user_id']);
                    $to_node = Db::table('wf_workflow_node', '')->where(['id' => $to_operation['node_id']])->find();

                    /**
                     * @var $to_node_obj Node
                     */
                    $to_node_obj = new $to_node['class']($real_to_operation_id);
                    $to_node_obj->distribute($to_operation['user_id']);
                    $to_node_obj = null;
                }
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
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param array $form 数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function hangup(array $form = null)
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->findOrNull();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [ActionModel::TYPE_UNEXECUTED])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }

        $instance = Db::table('wf_workflow_instance', '')->where(['id' => $operation['instance_id']])->findOrNull();
        if (!$instance) {
            $this->errMsg = '找不到该操作对应工作流实例！';
            return false;
        }
        $scheme = Db::table('wf_workflow_scheme', '')->where(['id' => $instance['scheme_id']])->findOrNull();
        if (!$scheme) {
            $this->errMsg = '找不到该操作对应工作流方案！';
            return false;
        }

        Db::startTrans();
        try {
            $this->action($form);
            $this->ignoreBefore();

            //项目挂起
            /**
             * @var $current_scheme_obj Scheme
             */
            $current_scheme_obj = new $scheme['class']($instance['id']);
            if (!$current_scheme_obj->hangup()) {
                $this->errMsg = $current_scheme_obj->getLastErrMsg();
                Db::rollback();
                return false;
            }
            $current_scheme_obj = null;

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 任务调度
     * @param int $user_id 接收调度的用户ID
     * @param array $form 附加数据数组
     * @return bool 操作成功返回true，失败返回false
     */
    public function dispatch($user_id, array $form = [])
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->findOrNull();
        if (!$operation) {
            $this->errMsg = '找不到该操作记录！';
            return false;
        }
        if (!in_array((int)$operation['action_type'], [ActionModel::TYPE_UNEXECUTED, ActionModel::TYPE_HANGUP])) {
            $this->errMsg = '该操作节点已进行过操作，无法再次执行！';
            return false;
        }

        Db::startTrans();
        try {
            //更新本节点实际操作
            $operation_data = [
                'action_id'   => 0,
                'action_name' => '已调度',
                'action_type' => ActionModel::TYPE_DISPATCH,
                'action_time' => date('Y-m-d H:i:s')
            ];
            $operation_data = array_merge($operation_data, $form);
            Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId])->update($operation_data);
            $to_operation_id = NodeModel::createOperationForUser($operation['contrast_id'], $operation['node_id'], $user_id);
            $this->ignoreBefore($to_operation_id);

            $to_node = Db::table('wf_workflow_node', '')->where(['id' => $operation['node_id']])->find();

            /**
             * @var $to_node_obj Node
             */
            $to_node_obj = new $to_node['class']($to_operation_id);
            $to_node_obj->distribute($user_id);
            $to_node_obj = null;

            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $this->errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 执行动作
     * @param array $form 提交表单
     */
    protected function action(array $form)
    {
        $operation = Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId ])->find();

        $action_id = isset($form['workflow_action_id']) ? $form['workflow_action_id'] : 0;
        $action_name = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '';
        $action_type = isset($form['workflow_action_type']) ? $form['workflow_action_type'] : 0;

        $action = Db::table('wf_workflow_node_action', '')->where('id = ?', [ $action_id ])->findOrNull();
        if($action){
            $action_name = empty($action['action_name']) ? $action_name : $action['action_name'];
            $action_type = empty($action['action_type']) ? $action_type : $action['action_type'];
        }

        //常用字段使用workflow_前缀来区分
        $view = isset($form['workflow_view']) ? $form['workflow_view'] : '';
        $inner_view = isset($form['workflow_inner_view']) ? $form['workflow_inner_view'] : '';
        $back_node = isset($form['workflow_back_node']) ? $form['workflow_back_node'] : 0;
        $dispatch_reason = isset($form['workflow_dispatch_reason']) ? $form['workflow_dispatch_reason'] : '';

        $data = [
            'action_id'       => $action_id,
            'action_name'     => $action_name,
            'action_type'     => $action_type,
            'action_time'     => date('Y-m-d H:i:s'),
            'view'            => $view,
            'inner_view'      => $inner_view,
            'back_node'       => $back_node,
            'dispatch_reason' => $dispatch_reason,
            'prev_json'       => OperationModel::getPrevJson($operation['instance_id']),
            'form_json'       => Json::encode($form)
        ];

        Db::table('wf_workflow_operation', '')->where(['id' => $this->operationId ])->update($data);
    }

    /**
     * 工作流动作统一执行
     * @param array $form 表单数据(含自定义)
     * @param int $action_id 自定义操作ID,可以为0表示任意自定义
     * @param int $action_type 操作类型，请从NodeAction中选择
     * @param string $action_name 自定义操作名称
     * @return bool
     */
    public function execute(array $form, $action_id = null, $action_type = null, $action_name = null)
    {
        if(!is_null($action_id)){
            $form['workflow_action_id'] = $action_id;
        }
        $form['workflow_action_id'] = isset($form['workflow_action_id']) ? $form['workflow_action_id'] : 0;
        if($form['workflow_action_id']){  //指定了action
            $node_action = Db::table('wf_workflow_node_action', '')->where(['id' => $form['workflow_action_id']])->find();
            $form['workflow_action_type'] = $node_action['action_type'];
            $form['workflow_action_name'] = $node_action['action_name'];
        }
        $form['workflow_action_type'] = isset($form['workflow_action_type']) ? $form['workflow_action_type'] : ActionModel::TYPE_UNEXECUTED;
        if(!is_null($action_type)){
            $form['workflow_action_type'] = $action_type;
        }
        if(!is_null($action_name)){
            $form['workflow_action_name'] = $action_name;
        }

        if($form['workflow_action_type'] == ActionModel::TYPE_ADOPT){  //通过
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '通过';
            return $this->adopt($form);
        } elseif ($form['workflow_action_type'] == ActionModel::TYPE_REJECT){  //否决
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '否决';
            return $this->reject($form);
        } elseif ($form['workflow_action_type'] == ActionModel::TYPE_GOBACK){  //退回
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '退回';
            $form['workflow_back_node'] = isset($form['workflow_back_node']) ? $form['workflow_back_node'] : 0;
            $to_node_id = isset($form['workflow_back_node']) ? $form['workflow_back_node'] : null;
            $to_operation_id = isset($form['workflow_to_operation_id']) ? $form['workflow_to_operation_id'] : null;
            return $this->goback($form, $to_node_id, $to_operation_id);
        } elseif ($form['workflow_action_type'] == ActionModel::TYPE_HANGUP) {  //挂起
            $form['workflow_action_name'] = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '挂起';
            return $this->hangup($form);
        } elseif ($form['workflow_action_type'] == ActionModel::TYPE_DISPATCH) {  //调度
            if(!isset($form['workflow_user_id']) || empty($form['workflow_user_id'])){
                $this->errMsg = "调度操作必须指定接收用户ID";
                return false;
            }
            return $this->dispatch($form['workflow_user_id'], $form);
        }

        $this->errMsg = "不支持的操作类型:{$form['workflow_action_type']}";
        return false;
    }
}