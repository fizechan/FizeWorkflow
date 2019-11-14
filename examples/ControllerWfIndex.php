<?php
namespace app\controller;

use fize\db\realization\mysql\Db;
use fize\db\realization\mysql\db\Pdo;
use huarui\workflow\scheme\ProjectSetup;
use fize\workflow\NodeInterface;

/**
 * 工作流调试
 */
class ControllerWfIndex
{
    /**
     * @var Pdo
     */
    protected $orm;

    /**
     * @var NodeInterface
     */
    protected $nodeObj;

    public function __construct()
    {
        $this->orm = Db::pdo('localhost', 'root', '123456', 'cfz_test');
    }

    /**
     * 步骤:预备
     */
    public function actionIndex()
    {
        $html = <<<EOF
预备步骤<br/>
-->登录超级管理员账号<br/>
-->添加其他管理员账号(用于登录)<br/>
-->设置工作流组别<br/>
-->设置工作流角色<br/>
-->添加工作流用户(实际工作流使用该表ID)<br/>
-->根据情况添加工作流用户扩展信息(与实际业务挂钩，需要进行硬编码)<br/>

EOF;
        echo $html;
    }

    /**
     * 步骤:分配
     */
    public function actionDistribute()
    {
        echo "项目审批分配<br/>\r\n";
        $scheme = new ProjectSetup();
        $result = $scheme->distribute();
        var_dump($result);
    }

    /**
     * 步骤:初审通过
     */
    public function actionAdopt()
    {
        $operation_id = 4;
        $operation = $this->orm->table('gm_workflow_operation')->where(['id' => $operation_id])->find();
        $node = $this->orm->table('gm_workflow_node')->where(['id' => $operation['node_id']])->find();
        $this->nodeObj = new $node['class']();

        //通过$operation_id找到的可用填充资料
        $project = $this->orm->table('gm_project')->where(['workflow_instance_id' => $operation['instance_id']])->find();
        $form = [
            'project_id' => $project['id'],
            'action_time' => date('Y-m-d H:i:s'),
            'node_id' => $node['id'],
            'node_name' => $node['name'],
            'action_type' => 1,
            'view' => '审核通过',
            'inner_view' => '该项目无明显风险，可执行。',
            'admin_id' => $operation['user_id'],
            'admin_name' => '冗余名称'
        ];
        $result = $this->nodeObj->adopt($operation['user_id'], $operation_id, $form);
        var_dump($result);
    }
}