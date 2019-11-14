<?php
namespace huarui\workflow\scheme;

use fize\workflow\SchemeInterface;
use fize\db\realization\mysql\Db;
use fize\db\realization\mysql\db\Pdo;
use fize\db\realization\mysql\Query;
use fize\loger\Log;
use Exception;

/**
 * 项目立项审核方案
 */
class ProjectSetup implements SchemeInterface
{
    /**
     * @var Pdo
     */
    protected $orm;

    /**
     * @var string
     */
    private $errmsg = '';

    /**
     * 构造
     */
    public function __construct()
    {
        $this->orm = Db::pdo('localhost', 'root', '123456', 'cfz_test');
        Log::init('file', ['path' => './data/log/workflow']);
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        if($this->errmsg){
            Log::write($this->errmsg, 'ERR');
        }
    }

    public function getList($where = null)
    {
        return [];
    }

    public function getPage($page, $size = 10, $where = null)
    {
        return [];
    }

    /**
     * 分配初始工作流
     * @param int $user_id 指定接收用户ID
     * @return bool
     */
    public function distribute($user_id = null)
    {
        //-->找出本方案
        //$scheme = $this->orm->query("SELECT * FROM `gm_workflow_scheme` WHERE `code`='TOB_auditing'");
        $scheme = $this->orm->table('gm_workflow_scheme')->where("`code`='TOB_auditing'")->find();
        if(!$scheme){
            $this->errmsg = '分配工作流任务时发生错误：找不到方案！';
            return false;
        }

        //-->找出尚未分配的审核的项目
        //本次仅模拟分配一个，可以根据具体业务需求，进行多个分配或者其他自定义分配。
        $project_undo = $this->orm->table('gm_project')->where(['status' => ['IN', [2]], 'workflow_instance_id' => 0])->order("rand()")->find();
        if(!$project_undo){
            $this->errmsg = '分配工作流任务时发生错误：没有可供分配的项目！';
            return false;
        }

        //-->找出方案第一操作节点(即入口节点)
        $first_node = $this->orm->table('gm_workflow_node')->where(['pid' => 0, 'scheme_id' => $scheme['id'], 'is_first' => 1])->find();
        if(!$first_node){
            $this->errmsg = '分配工作流任务时发生错误：找不到该工作流方案的入口节点！';
            return false;
        }

        //-->找出可进入操作节点的用户规则
        $group_ids = explode(',', $first_node['group_ids']);
        $role_ids = explode(',', $first_node['role_ids']);
        $user_ids = explode(',', $first_node['user_ids']);

        if($user_id){
            //指定接收任务的用户ID
            //-->判断用户可用性
            $user = $this->orm->table('gm_workflow_user')->where(['id' => $user_id])->find();
            if(!$user){
                $this->errmsg = '分配工作流任务时发生错误：找不到该指定用户！';
                return false;
            }

            if(!in_array($user['group_id'], $group_ids) && !in_array($user['role_id'], $role_ids) && !in_array($user['id'], $user_ids)){
                $this->errmsg = '分配工作流任务时发生错误：找不到该指定用户不允许操作本入口节点！';
                return false;
            }

            //-->判断审核额度
            $user_quota = $this->orm->table('gm_workflow_user_quota')->where(['user_id' => $user_id])->find();
            if(!$user_quota){
                $this->errmsg = '分配工作流任务时发生错误：该指定用户尚未指定审核额度，无法分配！';
                return false;
            }
            if($user_quota['current_quota'] < $project_undo['amount']){
                $this->errmsg = '分配工作流任务时发生错误：该项目额度超过用户审核额度，无法分配！';
                return false;
            }
        }else{
            //-->找出合适的任务接收用户ID
            $map1 = (new Query('t_user.group_id'))->isIn($group_ids);
            $map2 = (new Query('t_user.role_id'))->isIn($role_ids);
            $map3 = (new Query('t_user.id'))->isIn($user_ids);
            $map4 = (new Query('t_quota.current_quota'))->egt($project_undo['amount']);
            $where = Query::qAnd( Query::qOr($map1, $map2, $map3), $map4);
            $user = $this->orm->table('gm_workflow_user')
                ->alias("t_user")
                ->leftJoin(['t_quota' => 'gm_workflow_user_quota'], 't_quota.user_id = t_user.id')
                ->where($where)->order("rand()")->find();
            if(!$user){
                $this->errmsg = '分配工作流任务时发生错误：找不到合适的操作用户！';
                return false;
            }
        }

        //-->开始任务分配
        $this->orm->startTrans();
        try{
            //插入工作流实例
            $instance_data = [
                'name' => $project_undo['name'],  //直接使用项目名称，可再自定义
                'scheme_id' => $scheme['id'],
                'current_node_id' => 0,  //本处为初始化值
                'current_operation_id' => 0,  //本处为初始化值
                'is_finish' => 0
            ];
            $instance_id = $this->orm->table('gm_workflow_instance')->insert($instance_data);
            if ($instance_id === false){
                $this->errmsg = '分配工作流任务时发生错误：插入工作流实例时发生错误！';
                $this->orm->rollback();
                return false;
            }

            //更新项目记录
            $this->orm->table('gm_project')->where(['id' => $project_undo['id']])->update(['status' => 5, 'workflow_instance_id' => $instance_id]);

            //插入实例操作记录
            $operation_data = [
                'instance_id' => $instance_id,
                'user_id' => $user['id'],
                'node_id' => $first_node['id'],
                'distribute_time' => date('Y-m-d H:i:s'),
                'operation' => 0
            ];
            $operation_id = $this->orm->table('gm_workflow_operation')->insert($operation_data);
            if ($operation_id === false){
                $this->errmsg = '分配工作流任务时发生错误：插入工作流实例操作记录时发生错误！';
                $this->orm->rollback();
                return false;
            }

            //更新工作流实例
            $this->orm->table('gm_workflow_instance')->where(['id' => $instance_id])->update(['current_node_id' => $first_node['id'], 'current_operation_id' => $operation_id]);

            $this->orm->commit();

            return true;
        }
        catch (Exception $ex){
            $this->orm->rollback();
            var_dump($ex);
            $this->errmsg = '分配工作流任务时发生错误：SQL事务处理失败！' . $ex->getMessage();
            return false;
        }
    }

    /**
     * 审批通过
     * @param $instance_id
     * @return mixed
     */
    public function adopt($instance_id)
    {
        $this->orm->table('gm_project')->where(['workflow_instance_id' => $instance_id])->update(['status' => 6]);
    }

    /**
     * 审批否决
     * @param $instance_id
     * @return mixed
     */
    public function reject($instance_id)
    {
        $this->orm->table('gm_project')->where(['workflow_instance_id' => $instance_id])->update(['status' => 7]);
    }
}
