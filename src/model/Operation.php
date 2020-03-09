<?php


namespace fize\workflow\model;

use RuntimeException;
use fize\misc\Preg;
use fize\workflow\Db;
use fize\workflow\NodeInterface;
use fize\workflow\SchemeInterface;

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
     * 创建操作
     * @param int $submit_id 提交ID
     * @param int $node_id 节点ID
     * @param int $user_id 用户ID
     * @param bool $notice 是否发送提醒
     * @return int 返回操作ID
     */
    public static function create($submit_id, $node_id, $user_id = null, $notice = true)
    {
        $instance_id = Db::table('workflow_submit')->where(['id' => $submit_id])->value('instance_id');
        $node = Db::table('workflow_node')->where(['id' => $node_id])->find();
        $data = [
            'scheme_id'       => $node['scheme_id'],
            'instance_id'     => $instance_id,
            'submit_id'       => $submit_id,
            'user_id'         => $user_id,
            'node_id'         => $node['id'],
            'node_name'       => $node['name'],
            'create_time'     => date('Y-m-d H:i:s'),
            'distribute_time' => date('Y-m-d H:i:s')
        ];
        $operation_id = Db::table('workflow_operation')->insertGetId($data);
        self::ignoreBefore($operation_id);
        if ($user_id && $notice) {
            /**
             * @var NodeInterface $node_class
             */
            $node_class = $node['class'];
            $node_class::notice($operation_id);
        }
        return $operation_id;
    }

    /**
     * 分配用户
     * @param int $operation_id 操作ID
     * @param int $user_id 指定接收用户ID
     * @return int 返回用户ID
     */
    public function distribute($operation_id, $user_id = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $node = Db::table('workflow_node')->where(['id' => $operation['node_id']])->find();
        /**
         * @var NodeInterface $node_class
         */
        $node_class = $node['class'];

        if (is_null($user_id)) {
            $user_id = $node_class::getSuitableUserId($operation_id);
            if (!$user_id) {
                throw new RuntimeException('找不到分配该任务的合适用户！');
            }
        }
        $operation_data = [
            'user_id'         => $user_id,
            'distribute_time' => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where(['id' => $operation_id])->update($operation_data);
        $node_class::notice($operation_id);
        return $user_id;
    }

    /**
     * 审批通过
     * @param int $operation_id 操作ID
     * @param array $fields 提交的表单数据
     * @param array $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @todo 参数$node_user_tos考虑移除
     */
    public function adopt($operation_id, $fields, $node_user_tos = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Operation::ACTION_TYPE_UNEXECUTED, Operation::ACTION_TYPE_HANGUP])) {
            throw new RuntimeException('该操作节点已进行过操作，无法再次执行！');
        }
        $instance = Db::table('workflow_instance')->where(['id' => $operation['instance_id']])->find();
        $node = Db::table('workflow_node')->where(['id' => $operation['node_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $instance['scheme_id']])->find();

        /**
         * @var NodeInterface $node_class
         */
        $node_class = $node['class'];

        Db::startTrans();
        try {

            // 执行节点[审批通过]操作
            self::saveFields($operation_id, $fields);
            self::ignoreBefore($operation_id);
            $node_class::adopt($operation_id, $fields, $node_user_tos);

            // 执行后续操作
            $map = [
                ['scheme_id', '=', $instance['scheme_id']],
                ['level', '=', $node['level'] + 1]
            ];
            $next_nodes = Db::table('workflow_node')->where($map)->select();
            if (!$next_nodes) {  //最后一个节点，则执行方案审批通过操作
                if ($node_class::canNextAdopt($operation_id)) {
                    /**
                     * @var SchemeInterface $scheme_class
                     */
                    $scheme_class = $scheme['class'];
                    $scheme_class::adopt($operation['instance_id']);
                }
            } else {
                if ($node_class::canNextAdopt($operation_id)) {
                    if ($node_user_tos) {
                        //直接指定了下级接收者，则马上进行分配
                        foreach ($node_user_tos as $to_node_id => $to_user_id) {
                            self::create($operation['submit_id'], $to_node_id, $to_user_id);
                        }
                    } else {
                        foreach ($next_nodes as $next_node) {
                            /**
                             * @var NodeInterface $next_node_class
                             */
                            $next_node_class = $next_node['class'];
                            if ($next_node_class::access($operation['instance_id'], $operation['id'], $next_node['id'])) {
                                self::create($operation['submit_id'], $next_node['id']);
                            }
                        }
                    }
                }
            }
            Db::commit();
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 审核否决
     * 否决后默认是执行了方案否决方法，但是也可以重写该方法来执行特殊事务
     * @param int $operation_id 操作ID
     * @param array $fields 表单数组
     */
    public function reject($operation_id, $fields)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Operation::ACTION_TYPE_UNEXECUTED, Operation::ACTION_TYPE_HANGUP])) {
            throw new RuntimeException('该操作节点已进行过操作，无法再次执行！');
        }

        $instance = Db::table('workflow_instance')->where(['id' => $operation['instance_id']])->find();
        $node = Db::table('workflow_node')->where(['id' => $operation['node_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $instance['scheme_id']])->find();

        Db::startTrans();
        try {
            self::saveFields($operation_id, $fields);
            self::ignoreBefore($operation_id);

            // 执行节点[审批否决]操作
            /**
             * @var NodeInterface $node_class
             */
            $node_class = $node['class'];
            $node_class::reject($operation_id, $fields);

            // 执行方案[审批否决]操作
            /**
             * @var SchemeInterface $scheme_class
             */
            $scheme_class = $scheme['class'];
            $scheme_class::reject($operation['instance_id']);

            Db::commit();
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 审核退回
     * 一般是退回上一个节点，但是也可以重写该方法来执行特殊事务
     * @param int $operation_id 操作ID
     * @param array $fields 数据数组
     * @param int $to_node_id 返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @todo 参数$to_node_id考虑移除，添加参数$to_user_id
     */
    public function goback($operation_id, $fields, $to_node_id = null, $to_operation_id = null)
    {
        if (is_null($to_node_id) && is_null($to_operation_id)) {
            throw new RuntimeException('节点ID和操作ID必须指定1个！');
        }
        if (!is_null($to_node_id) && !is_null($to_operation_id)) {
            throw new RuntimeException('节点ID和操作ID不能同时指定！');
        }

        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Operation::ACTION_TYPE_UNEXECUTED, Operation::ACTION_TYPE_HANGUP])) {
            throw new RuntimeException('该操作节点已进行过操作，无法再次执行！');
        }

        $instance = Db::table('workflow_instance')->where(['id' => $operation['instance_id']])->find();
        $node = Db::table('workflow_node')->where(['id' => $operation['node_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $instance['scheme_id']])->find();

        Db::startTrans();
        try {
            self::saveFields($operation_id, $fields);
            self::ignoreBefore($operation_id);

            // 执行节点[审核退回]操作
            /**
             * @var NodeInterface $node_class
             */
            $node_class = $node['class'];
            $node_class::goback($operation_id, $fields, $to_node_id, $to_operation_id);

            // 执行后续操作
            if (is_numeric($to_node_id)) {  // 以节点ID来进行退回操作
                if ($to_node_id == 0) {  // 项目退回
                    /**
                     * @var SchemeInterface $scheme_class
                     */
                    $scheme_class = $scheme['class'];
                    $scheme_class::goback($operation['instance_id']);
                } else {  // 退回到指定节点
                    self::create($operation['submit_id'], $to_node_id);
                }
            } else {  // 以操作ID来进行退回操作
                if ($to_operation_id == 0) {  // 项目退回
                    /**
                     * @var SchemeInterface $scheme_class
                     */
                    $scheme_class = $scheme['class'];
                    $scheme_class::goback($operation['instance_id']);
                } else {  // 退回到指定操作点
                    //直接指定为原来的用户
                    $to_operation = Db::table('workflow_operation')->where(['id' => $to_operation_id])->find();
                    self::create($operation['submit_id'], $to_operation['node_id'], $to_operation['user_id']);
                }
            }

            Db::commit();
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 审核挂起
     * 挂起方法一般为外部使用，目前就挂起操作而言，没有实际意义，仅产生一条挂起记录
     * @param int $operation_id 操作ID
     * @param array $fields 数据数组
     */
    public function hangup($operation_id, $fields = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Operation::ACTION_TYPE_UNEXECUTED])) {
            throw new RuntimeException('该操作节点已进行过操作，无法再次执行！');
        }

        $instance = Db::table('workflow_instance')->where(['id' => $operation['instance_id']])->find();
        $node = Db::table('workflow_node')->where(['id' => $operation['node_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $instance['scheme_id']])->find();

        Db::startTrans();
        try {
            self::saveFields($operation_id, $fields);
            self::ignoreBefore($operation_id);

            // 执行节点[审批挂起]操作
            /**
             * @var NodeInterface $node_class
             */
            $node_class = $node['class'];
            $node_class::hangup($operation_id, $fields);

            // 执行方案[审批挂起]操作
            /**
             * @var SchemeInterface $scheme_class
             */
            $scheme_class = $scheme['class'];
            $scheme_class::hangup($operation['instance_id']);

            Db::commit();
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 任务调度
     * @param int $operation_id 操作ID
     * @param int $user_id 接收调度的用户ID
     * @param array $fields 附加数据数组
     */
    public function dispatch($operation_id, $user_id, $fields = null)
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

    /**
     * 对指定操作ID相关的之前操作节点进行无需操作处理
     * @param int $operation_id 操作ID
     */
    protected static function ignoreBefore($operation_id)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $map = [
            'instance_id' => ['=', $operation['instance_id']],
            'create_time' => ['<', $operation['create_time']],
            'action_type' => ['=', Operation::ACTION_TYPE_UNEXECUTED]
        ];
        $data = [
            'action_id'   => 0,
            'action_name' => '无需操作',
            'action_type' => Operation::ACTION_TYPE_DISUSE,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where($map)->update($data);
    }

    /**
     * 保存动作
     * @param int $operation_id 操作记录ID
     * @param int $action_id 动作ID
     */
    protected static function saveAction($operation_id, $action_id)
    {
        $data_operation = [
            'action_id'       => $action_id,
            'action_time'     => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where(['id' => $operation_id])->update($data_operation);
    }

    /**
     * 保存数据
     * @param int $operation_id 操作记录ID
     * @param array $fields 提交表单数据
     */
    protected static function saveFields($operation_id, $fields)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $data_operation_fields = [];
        foreach ($fields as $n => $v) {
            $field = Db::table('workflow_node_field')->where(['node_id' => $operation['node_id'], 'name' => $n])->find();
            if ($field['is_required'] && $v === "") {
                throw new RuntimeException("字段{$n}必须填写");
            }
            if ($field['regex_match']) {
                if (!Preg::match($field['regex_match'], $v)) {
                    throw new RuntimeException("字段{$n}不符合规则");
                }
            }

            $data_operation_fields[] = [
                'operation_id' => $operation_id,
                'name'      => $n,
                'value'     => $v
            ];

        }
        Db::table('workflow_operation_field')->insertAll($data_operation_fields);
    }

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
    public static function distribute2($limit = 0)
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
    public static function getMyPage2($offset, $limit, $id, $is_extend_id = false, $where = "", array $params = [], $order = null)
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
}
