<?php

namespace Fize\Workflow\Instance;

use Fize\Workflow\Action as Common;
use Fize\Workflow\Db;
use Fize\Workflow\NodeInterface;
use Fize\Workflow\SchemeInterface;
use RuntimeException;

/**
 * 模型：操作记录
 */
class Action extends Common
{

    /**
     * 创建操作
     * @param int      $submit_id 提交ID
     * @param int      $node_id   节点ID
     * @param int|null $user_id   用户ID
     * @param bool     $notice    是否发送提醒
     * @return int 返回操作ID
     */
    public static function create(int $submit_id, int $node_id, int $user_id = null, bool $notice = true): int
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
     * @param int      $operation_id 操作ID
     * @param int|null $user_id      指定接收用户ID
     * @return int|null 返回用户ID,无法分配时返回null
     */
    public static function distribute(int $operation_id, int $user_id = null)
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
                return null;
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
     * 为当前所有未执行工作流分配用户
     * @param int $limit 分配数量，为0表示所有
     * @return int 返回分配个数
     */
    public static function distributeAll(int $limit = 0): int
    {
        $count = 0;

        $no_distribute_operations = Db::table('workflow_operation')
            ->field(['id'])
            ->where(['node_id' => ['<>', 0], 'user_id' => null]);
        if ($limit) {
            $no_distribute_operations = $no_distribute_operations->limit($limit);
        }
        $no_distribute_operations = $no_distribute_operations->select();
        foreach ($no_distribute_operations as $no_distribute_operation) {
            if (self::distribute($no_distribute_operation['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 审批通过
     * @param int        $operation_id  操作ID
     * @param array      $fields        提交的表单数据
     * @param array|null $node_user_tos 指定要接收的下级节点及用户,如果指定，则马上进行下级任务分发
     * @todo 参数$node_user_tos考虑移除
     */
    public static function adopt(int $operation_id, array $fields, array $node_user_tos = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Action::TYPE_UNEXECUTED, Action::TYPE_HANGUP])) {
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
     * @param int   $operation_id 操作ID
     * @param array $fields       表单数组
     */
    public static function reject(int $operation_id, array $fields)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Action::TYPE_UNEXECUTED, Action::TYPE_HANGUP])) {
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
     * @param int      $operation_id    操作ID
     * @param array    $fields          数据数组
     * @param int|null $to_node_id      返回到指定节点ID，如果为0，则执行方案的退回操作
     * @param int|null $to_operation_id 返回到指定操作ID，如果为0，则执行方案的退回操作
     * @todo 参数$to_node_id考虑移除，添加参数$to_user_id
     */
    public static function goback(int $operation_id, array $fields, int $to_node_id = null, int $to_operation_id = null)
    {
        if (is_null($to_node_id) && is_null($to_operation_id)) {
            throw new RuntimeException('节点ID和操作ID必须指定1个！');
        }
        if (!is_null($to_node_id) && !is_null($to_operation_id)) {
            throw new RuntimeException('节点ID和操作ID不能同时指定！');
        }

        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Action::TYPE_UNEXECUTED, Action::TYPE_HANGUP])) {
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
     * @param int        $operation_id 操作ID
     * @param array|null $fields       数据数组
     */
    public static function hangup(int $operation_id, array $fields = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Action::TYPE_UNEXECUTED])) {
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
     * @param int        $operation_id 操作ID
     * @param int        $user_id      接收调度的用户ID
     * @param array|null $fields       附加数据数组
     */
    public static function dispatch(int $operation_id, int $user_id, array $fields = null)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        if (!in_array((int)$operation['action_type'], [Action::TYPE_UNEXECUTED, Action::TYPE_HANGUP])) {
            throw new RuntimeException('该操作节点已进行过操作，无法再次执行！');
        }

        Db::startTrans();
        try {
            if ($fields) {
                self::saveFields($operation_id, $fields);
            }

            self::saveAction($operation_id, 0, Action::TYPE_DISPATCH, '已调度');

            $to_operation_id = self::create($operation['submit_id'], $operation['node_id'], $user_id);
            self::ignoreBefore($to_operation_id);

            Db::commit();
        } catch (RuntimeException $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 统一执行接口
     * @param int   $operation_id 操作ID
     * @param array $fields       数据表单
     * @param int   $action_id    动作ID
     * @todo 如何统一传入其他参数
     */
    public static function action(int $operation_id, array $fields, int $action_id)
    {
        $action = Db::table('workflow_action')->where(['id' => $action_id])->find();
        self::saveAction($operation_id, $action['id'], $action['type'], $action['name']);

        if ($action['type'] == Action::TYPE_ADOPT) {  // 通过
            self::adopt($operation_id, $fields);
        } elseif ($action['type'] == Action::TYPE_REJECT) {  // 否决
            self::reject($operation_id, $fields);
        } elseif ($action['type'] == Action::TYPE_GOBACK) {  // 退回
            self::goback($operation_id, $fields);
        } elseif ($action['type'] == Action::TYPE_HANGUP) {  // 挂起
            self::hangup($operation_id, $fields);
        }

        throw new RuntimeException("不支持的操作类型:{$action['type']}");
    }

    /**
     * 对指定操作ID相关的之前操作节点进行无需操作处理
     * @param int $operation_id 操作ID
     */
    protected static function ignoreBefore(int $operation_id)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $map = [
            'instance_id' => ['=', $operation['instance_id']],
            'create_time' => ['<', $operation['create_time']],
            'action_type' => ['=', Action::TYPE_UNEXECUTED]
        ];
        $data = [
            'action_id'   => 0,
            'action_name' => '无需操作',
            'action_type' => Action::TYPE_DISUSE,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where($map)->update($data);
    }

    /**
     * 保存动作
     * @param int         $operation_id 操作记录ID
     * @param int         $action_id    动作ID
     * @param int|null    $action_type  动作类型
     * @param string|null $action_name  动作描述
     */
    protected static function saveAction(int $operation_id, int $action_id, int $action_type = null, string $action_name = null)
    {
        if (is_null($action_type)) {
            $action_type = Db::table('workflow_action')->where(['id' => $action_id])->value('type');
        }
        if (is_null($action_name)) {
            $action_name = Db::table('workflow_action')->where(['id' => $action_id])->value('name');
        }
        $data_operation = [
            'action_id'   => $action_id,
            'action_type' => $action_type,
            'action_name' => $action_name,
            'action_time' => date('Y-m-d H:i:s')
        ];
        Db::table('workflow_operation')->where(['id' => $operation_id])->update($data_operation);
    }

    /**
     * 保存数据
     * @param int   $operation_id 操作记录ID
     * @param array $fields       提交表单数据
     */
    protected static function saveFields(int $operation_id, array $fields)
    {
        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();
        $data_operation_fields = [];
        foreach ($fields as $n => $v) {
            $field = Db::table('workflow_node_field')->where(['node_id' => $operation['node_id'], 'name' => $n])->find();
            if ($field['is_required'] && $v === "") {
                throw new RuntimeException("字段{$n}必须填写");
            }
            if ($field['regex_match']) {
                if (!preg_match($field['regex_match'], $v)) {
                    throw new RuntimeException("字段{$n}不符合规则");
                }
            }

            $data_operation_fields[] = [
                'operation_id' => $operation_id,
                'name'         => $n,
                'value'        => $v
            ];

        }
        Db::table('workflow_operation_field')->insertAll($data_operation_fields);
    }

    /**
     * @param int $instance_id 实例ID
     * @return array
     * @todo 待验证必要性
     *                         取得工作流实例所有直线操作记录
     */
    public static function getPrevJson(int $instance_id): array
    {
        $map = [
            'instance_id' => ['=', $instance_id],
            'action_type' => ['<>', Action::TYPE_UNEXECUTED]
        ];
        $operations = Db::table('workflow_operation')
            ->where($map)
            ->order(['action_time' => 'ASC'])
            ->select();
        if (!$operations) {
            $operations = [];
        }
        return $operations;
    }

    /**
     * 列表分页
     * @param int          $page  页码
     * @param int          $size  每页记录数量，默认每页10个
     * @param mixed        $where 条件
     * @param array|string $order 排序
     * @return array [记录个数, 总页数、记录数组]
     */
    public static function getPage(int $page, int $size = 10, $where = null, $order = null): array
    {
        $result = Db::table('workflow_operation')
            ->alias('t_operation')
            ->leftJoin(['workflow_scheme', 't_scheme'], 't_scheme.id = t_operation.scheme_id')
            ->leftJoin(['workflow_instance', 't_instance'], 't_instance.id = t_operation.instance_id')
            ->leftJoin(['workflow_submit', 't_submit'], 't_submit.id = t_operation.submit_id')
            ->leftJoin(['workflow_user', 't_user'], 't_user.id = t_operation.user_id')
            ->field([
                't_operation.*',
                'scheme_type'        => 't_instance.scheme_type',
                'instance_name'      => 't_instance.name',
                'instance_status'    => 't_instance.status',
                'instance_is_finish' => 't_instance.is_finish',
                'scheme_name'        => 't_scheme.name',
                'user_name'          => 't_user.name'
            ]);
        if ($where) {
            $result = $result->where($where);
        }
        if (!$order) {
            $order = ['t_operation.distribute_time' => 'DESC'];
        }

        return $result->order($order)->paginate($page, $size);
    }

    /**
     * 返回操作ID对应的项目之前的操作记录
     * @param int  $operation_id 操作记录
     * @param bool $with_self    是否包含自身记录
     * @return array
     */
    public static function getPreviousOperations(int $operation_id, bool $with_self): array
    {
        $operator = "<";
        if ($with_self) {
            $operator .= "=";
        }

        $operation = Db::table('workflow_operation')->where(['id' => $operation_id])->find();

        $rows = Db::table('workflow_operation')
            ->alias('t_operation')
            ->leftJoin(['workflow_user', 't_user'], 't_user.id = t_operation.user_id')
            ->field([
                't_operation.*',
                'user_name' => 't_user.name'
            ])
            ->where([
                't_operation.instance_id' => $operation['instance_id'],
                't_operation.create_time' => ['<=', $operation['create_time']],
                't_operation.id'          => [$operator, $operation['id']]
            ])
            ->order(['t_operation.create_time' => 'ASC', 't_operation.id' => 'ASC'])
            ->select();

        return $rows;
    }

    /**
     * 根据提交ID返回操作记录
     * @param int $submit_id 提交ID
     * @return array
     */
    public static function getListBySubmitId(int $submit_id): array
    {
        $rows = Db::table('workflow_operation')
            ->alias('t_operation')
            ->leftJoin(['workflow_user', 't_user'], 't_user.id = t_operation.user_id')
            ->field([
                't_operation.*',
                'user_name' => 't_user.name'
            ])
            ->where([
                't_operation.submit_id' => $submit_id,
            ])
            ->order(['t_operation.create_time' => 'ASC', 't_operation.id' => 'ASC'])
            ->select();

        return $rows;
    }

    /**
     * 根据实例ID返回操作记录
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getListByInstanceId(int $instance_id): array
    {
        $rows = Db::table('workflow_operation')
            ->alias('t_operation')
            ->leftJoin(['workflow_user', 't_user'], 't_user.id = t_operation.user_id')
            ->field([
                't_operation.*',
                'user_name' => 't_user.name'
            ])
            ->where([
                't_operation.instance_id' => $instance_id,
            ])
            ->order(['t_operation.create_time' => 'ASC', 't_operation.id' => 'ASC'])
            ->select();

        return $rows;
    }
}
