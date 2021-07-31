<?php

namespace fize\workflow\model;

use fize\workflow\Action;
use fize\workflow\Db;
use RuntimeException;

/**
 * 动作
 */
class DefNodeAction extends Action
{

    /**
     * 获取指定节点的所有动作
     * @param int $def_node_id 节点ID
     * @return array
     */
    public static function getListByNodeId(int $def_node_id): array
    {
        $rows = Db::table('workflow_def_node_action')->where(['def_node_id' => $def_node_id])->order(['sort' => 'ASC', 'id' => 'ASC'])->select();
        return $rows;
    }

    /**
     * 添加
     * @param int    $node_id     节点ID
     * @param int    $action_type 操作类型
     * @param string $action_name 操作名称
     * @param int    $sort        排序，值小靠前
     * @return int
     */
    public static function add(int $node_id, int $action_type, string $action_name, int $sort = 0): int
    {
        self::checkActionType($action_type);
        $data = [
            'node_id'     => $node_id,
            'action_type' => $action_type,
            'action_name' => $action_name,
            'sort'        => $sort,
            'create_time' => date('Y-m-d H:i:s')
        ];
        $id = Db::table('workflow_action')->insertGetId($data);
        return $id;
    }

    /**
     * 编辑
     * 不允许修改节点ID
     * @param int         $id          ID
     * @param int|null    $action_type 操作类型
     * @param string|null $action_name 操作名称
     * @param int|null    $sort        排序，值小靠前
     * @return bool
     */
    public static function edit(int $id, int $action_type = null, string $action_name = null, int $sort = null): bool
    {
        $data = [
            'update_on' => date('Y-m-d H:i:s')
        ];
        if (!is_null($action_type)) {
            self::checkActionType($action_type);
            $data['action_type'] = $action_type;
        }
        if (!is_null($action_name)) {
            $data['action_name'] = $action_name;
        }
        if (!is_null($sort)) {
            $data['sort'] = $sort;
        }

        $result = Db::table('workflow_action')->where(['id' => $id])->update($data);
        return (bool)$result;
    }

    /**
     * 删除
     * @param int $id ID
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $result = Db::table('workflow_node_action')->where(['id' => $id])->delete();
        return (bool)$result;
    }

    /**
     * 检测操作类型是否合法
     * @param int $action_type
     */
    protected static function checkActionType(int $action_type)
    {
        $allow_action_types = [
            Action::TYPE_ADOPT,
            Action::TYPE_REJECT,
            Action::TYPE_GOBACK,
            Action::TYPE_HANGUP
        ];
        if (in_array($action_type, $allow_action_types)) {
            throw new RuntimeException("非法的操作类型");
        }
    }
}
