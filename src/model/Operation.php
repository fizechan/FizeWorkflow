<?php


namespace util\workflow\model;

use think\Db;
use think\facade\Log;
use util\workflow\definition\Node;
use util\workflow\definition\Scheme;
use think\facade\Cache;

/**
 * 模型：操作记录
 */
class Operation
{
    /**
     * 操作：未操作
     */
    const ACTION_TYPE_UNEXECUTED = 0;

    /**
     * 操作：通过
     */
    const ACTION_TYPE_ADOPT = 1;

    /**
     * 操作：否决
     */
    const ACTION_TYPE_REJECT = 2;

    /**
     * 操作：退回
     */
    const ACTION_TYPE_GOBACK = 3;

    /**
     * 操作：挂起
     */
    const ACTION_TYPE_HANGUP = 4;

    /**
     * 操作：无需操作
     */
    const ACTION_TYPE_DISUSE = 5;

    /**
     * 操作：调度
     */
    const ACTION_TYPE_DISPATCH = 6;

    /**
     * 操作：提交
     */
    const ACTION_TYPE_SUBMIT = 7;

    /**
     * 操作：取消
     */
    const ACTION_TYPE_CANCEL = 8;

    /**
     * @var Node
     */
    private static $node;

    /**
     * @var Scheme
     */
    private static $scheme;

    /**
     * 取得工作流实例所有直线操作记录组成的JSON字符串
     * @param int $instance_id 实例ID
     * @return string
     */
    public static function getPrevJson($instance_id)
    {
        $map = [
            ['instance_id', '=', $instance_id],
            ['action_type', '<>', self::ACTION_TYPE_UNEXECUTED]
        ];
        $operations = Db::name('workflow_operation')
            ->where($map)
            ->field(['prev_json'], true)
            ->order('action_time', 'ASC')
            ->select();
        if (!$operations) {
            $operations = [];
        }
        return json_encode($operations);
    }

    /**
     * 执行动作
     * @param int $operation_id 操作记录ID
     * @param int $action_id 动作ID
     * @param array $form 提交表单
     * @param array $extend 重要存储数据
     */
    public static function action($operation_id, $action_id, array $form, array $extend = [])
    {
        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();

        $action_name = isset($form['workflow_action_name']) ? $form['workflow_action_name'] : '';
        $action_type = isset($form['workflow_action_type']) ? $form['workflow_action_type'] : 0;
        if (isset($form['workflow_action_id']) && empty($action_id)) {
            $action_id = $form['workflow_action_id'];
        }

        $action = Db::name('workflow_node_action')->where('id', '=', $action_id)->find();
        if ($action) {
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
            'prev_json'       => self::getPrevJson($operation['instance_id']),
            'form_json'       => json_encode($form),
            'extend_json'     => json_encode($extend)
        ];

        Db::name('workflow_operation')->where('id', '=', $operation_id)->update($data);
    }

    /**
     * 统一执行接口
     * @param int $operation_id 操作ID
     * @param array $form 数据表单
     * @param int $action_id 动作ID
     * @param int $action_type 动作类型
     * @param string $action_name 动作名称
     * @return array
     */
    public static function execute($operation_id, array $form, $action_id = null, $action_type = null, $action_name = null)
    {
        if (!is_null($action_id)) {
            $form['workflow_action_id'] = $action_id;
        }
        if (isset($form['workflow_action_id']) && !empty($form['workflow_action_id'])) {
            $node_action = Db::name('workflow_node_action')->where('id', '=', $form['workflow_action_id'])->find();
            $node = Db::name('workflow_node')->where('id', '=', $node_action['node_id'])->find();
            if (!$node) {
                return [false, '没有找到该动作记录'];
            }
            self::$node = new $node['class']();
        } else {
            //使用默认Node类
            self::$node = new Node();
        }
        $result = self::$node->execute($operation_id, $form, $action_id, $action_type, $action_name);
        return [$result, self::$node->getLastErrMsg()];
    }

    /**
     * 为当前所有未执行工作流分配人员
     * @param int $limit 分配数量，为0表示所有
     */
    public static function distribute($limit = 0)
    {
        $offset_operation_id = Cache::has('global_workflow_current_distribute_operation_id') ? Cache::get('global_workflow_current_distribute_operation_id') : 0;
        $sql = <<<EOF
SELECT gm_workflow_operation.id, gm_workflow_node.class AS node_class
FROM gm_workflow_operation
LEFT JOIN gm_workflow_node ON gm_workflow_node.id = gm_workflow_operation.node_id
WHERE
gm_workflow_operation.node_id <> 0 AND gm_workflow_operation.user_id IS NULL
AND gm_workflow_operation.id > {$offset_operation_id}
ORDER BY gm_workflow_operation.id ASC
EOF;
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $no_distribute_operations = Db::query($sql);
        if ($no_distribute_operations) {
            foreach ($no_distribute_operations as $no_distribute_operation) {
                self::$node = new $no_distribute_operation['node_class']();
                self::$node->distributeUser($no_distribute_operation['id']);
                Cache::set('global_workflow_current_distribute_operation_id', $no_distribute_operation['id']);
            }
        } else {
            Cache::set('global_workflow_current_distribute_operation_id', 0);  //复位从头开始轮询
            Log::write("暂无未执行工作流需要分配", 'workflow');
        }
    }

    /**
     * 用户拉取可操作工作流
     * @param int $user_id 用户ID
     * @param array $scheme_ids 指定方案ID
     * @return array [$bool, $errmsg]
     */
    public static function pull($user_id, array $scheme_ids = null)
    {
        if ($scheme_ids) {
            $schemes = Db::name('workflow_scheme')->where('id', 'IN', $scheme_ids)->select();
        } else {
            $schemes = Db::name('workflow_scheme')->select();
        }
        if (!$schemes) {
            return [false, '没有可用工作流方案'];
        }
        $errmsg = '';
        foreach ($schemes as $scheme) {
            self::$scheme = new $scheme['class']();
            $result = self::$scheme->distribute($user_id, $scheme['id']);
            if ($result) {
                return [true, ''];
            }
            $errmsg = self::$scheme->getLastErrMsg();
            self::$scheme = null;
        }
        return [false, $errmsg];
    }

    /**
     * 取得分页
     * @param int $offset 偏移量
     * @param int $limit 每页数量
     * @param string $where 条件，支持占位符
     * @param array $params SQL占位参数
     * @param string $order 排序
     * @return array [$total, $row]
     */
    public static function getPage($offset, $limit, $where = "", array $params = [], $order = null)
    {
        $sql = <<<EOF
SELECT t_operation.*, t_instance.scheme_type AS scheme_type, t_instance.extend_relation AS extend_relation,
t_instance.name AS instance_name, t_instance.status AS instance_status, t_instance.is_finish AS instance_is_finish,
t_contrast.form_json,
t_scheme.name AS scheme_name,
t_user.name AS user_name
FROM gm_workflow_operation AS t_operation
LEFT JOIN gm_workflow_instance AS t_instance ON t_instance.id = t_operation.instance_id
LEFT JOIN gm_workflow_contrast AS t_contrast ON t_contrast.id = t_operation.contrast_id
LEFT JOIN gm_workflow_scheme AS t_scheme ON t_scheme.id = t_operation.scheme_id
LEFT JOIN gm_workflow_user AS t_user ON t_user.id = t_operation.user_id
EOF;
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        if (!$order) {
            $order = 't_operation.distribute_time DESC';
        }
        $sql .= " ORDER BY {$order}";

        $full_sql = substr_replace($sql, " SQL_CALC_FOUND_ROWS ", 6, 0);
        $full_sql .= " LIMIT {$offset},{$limit}";
        $row = Db::query($full_sql, $params);
        $cout_sql = 'SELECT FOUND_ROWS() AS `hr_count`';
        $crw = Db::query($cout_sql);
        $total = $crw[0]['hr_count'];
        return [$total, $row];
    }

    /**
     * 获取用户的分页
     * @param int $offset 偏移量
     * @param int $limit 每页数量
     * @param int $id 用户工作流账号ID或者外部ID
     * @param bool $is_extend_id 账号是否是外部ID
     * @param string $where 条件，支持占位符
     * @param array $params SQL占位参数
     * @param string $order 排序
     * @return array [$total, $row]
     */
    public static function getMyPage($offset, $limit, $id, $is_extend_id = false, $where = "", array $params = [], $order = null)
    {
        if ($is_extend_id) {
            $where_part = "t_operation.user_extend_id = {$id}";
        } else {
            $where_part = "t_operation.user_id = {$id}";
        }
        if ($where) {
            $where = "$where_part AND $where";
        } else {
            $where = $where_part;
        }
        return self::getPage($offset, $limit, $where, $params, $order);
    }

    /**
     * 根据操作ID获取可使用的动作列表
     * @param int $operation_id 操作ID
     * @return array
     */
    public static function getNodeActions($operation_id)
    {
        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        return NodeAction::getList($operation['node_id']);
    }

    /**
     * 返回操作ID对应的项目之前的操作记录
     * @param int $operation_id 操作记录
     * @param bool $with_self 是否包含自身记录
     * @return array
     */
    public static function getPreviousOperations($operation_id, $with_self = false)
    {
        $operator = "<";
        if ($with_self) {
            $operator .= "=";
        }

        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        $sql = <<<EOF
SELECT t_operation.*, t_user.name AS user_name, t_admin.fullname AS user_admin_fullname, t_admin.nickname AS user_admin_nickname
FROM gm_workflow_operation AS t_operation
LEFT JOIN gm_workflow_user AS t_user ON t_user.id = t_operation.user_id
LEFT JOIN gm_admin AS t_admin ON t_admin.id = t_user.extend_id
WHERE t_operation.instance_id = {$operation['instance_id']} AND t_operation.create_time <= '{$operation['create_time']}' AND t_operation.id {$operator} {$operation['id']}
ORDER BY t_operation.create_time ASC, t_operation.id ASC
EOF;
        $rows = Db::query($sql);
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 根据提交ID返回操作记录
     * @param int $contrast_id 提交ID
     * @return array
     */
    public static function getListByContrastId($contrast_id)
    {
        $sql = <<<EOF
SELECT t_operation.*, t_user.name AS user_name, t_admin.fullname AS user_admin_fullname, t_admin.nickname AS user_admin_nickname
FROM gm_workflow_operation AS t_operation
LEFT JOIN gm_workflow_user AS t_user ON t_user.id = t_operation.user_id
LEFT JOIN gm_admin AS t_admin ON t_admin.id = t_user.extend_id
WHERE t_operation.contrast_id = {$contrast_id}
ORDER BY t_operation.create_time ASC, t_operation.id ASC
EOF;
        $rows = Db::query($sql);
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 根据实例ID返回操作记录
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getListByInstanceId($instance_id)
    {
        $sql = <<<EOF
SELECT t_operation.*, t_user.name AS user_name, t_admin.fullname AS user_admin_fullname, t_admin.nickname AS user_admin_nickname
FROM gm_workflow_operation AS t_operation
LEFT JOIN gm_workflow_user AS t_user ON t_user.id = t_operation.user_id
LEFT JOIN gm_admin AS t_admin ON t_admin.id = t_user.extend_id
WHERE t_operation.instance_id = {$instance_id}
ORDER BY t_operation.create_time ASC, t_operation.id ASC
EOF;
        $rows = Db::query($sql);
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 任务调度
     * @param int $operation_id 操作ID
     * @param int $user_id 接收调度的用户ID
     * @return array [$result, $errmsg]
     */
    public static function dispatch($operation_id, $user_id)
    {
        $operation = Db::name('workflow_operation')->where('id', '=', $operation_id)->find();
        if (!$operation || !isset($operation['node_id'])) {
            return [false, '操作ID无效'];
        }

        $node = Db::name('workflow_node')->where('id', '=', $operation['node_id'])->find();
        if (!$node) {
            return [false, '节点ID无效'];
        }
        self::$node = new $node['class']();
        $result = self::$node->dispatch($operation_id, $user_id);
        return [$result, self::$node->getLastErrMsg()];
    }
}
