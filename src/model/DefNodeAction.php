<?php

namespace fize\workflow\model;

use RuntimeException;
use fize\workflow\Action;
use fize\workflow\Db;

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
    public static function getListByNodeId($def_node_id)
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
    public static function add($node_id, $action_type, $action_name, $sort = 0)
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
     * @param int    $id          ID
     * @param int    $action_type 操作类型
     * @param string $action_name 操作名称
     * @param int    $sort        排序，值小靠前
     * @return bool
     */
    public static function edit($id, $action_type = null, $action_name = null, $sort = null)
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
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id ID
     * @return bool
     */
    public static function delete($id)
    {
        $result = Db::table('workflow_node_action')->where(['id' => $id])->delete();
        return $result ? true : false;
    }

    /**
     * 检测操作类型是否合法
     * @param int $action_type
     */
    protected static function checkActionType($action_type)
    {
        $allow_action_types = [
            DefNodeAction::TYPE_ADOPT,
            DefNodeAction::TYPE_REJECT,
            DefNodeAction::TYPE_GOBACK,
            DefNodeAction::TYPE_HANGUP
        ];
        if (in_array($action_type, $allow_action_types)) {
            throw new RuntimeException("非法的操作类型");
        }
    }
}
