<?php


namespace fize\workflow\model;

use RuntimeException;
use fize\workflow\Db;

/**
 * 节点操作
 */
class NodeAction
{

    /**
     * 获取指定节点的所有操作
     * @param int $node_id 节点ID
     * @return array
     */
    public static function getListByNodeId($node_id)
    {
        $rows = Db::table('workflow_node_action')->where(['node_id' => $node_id])->order(['id' => 'ASC', 'sort' => 'DESC'])->select();
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 添加
     * @param int $node_id 节点ID
     * @param int $action_type 操作类型
     * @param string $action_name 操作名称
     * @param int $sort 排序，值大靠前
     * @return int
     */
    public static function add($node_id, $action_type, $action_name, $sort = 0)
    {
        self::checkActionType($action_type);
        $data = [
            'node_id'     => $node_id,
            'action_type' => $action_type,
            'action_name' => $action_name,
            'sort'        => $sort
        ];
        $id = Db::table('workflow_node_action')->insertGetId($data);
        return $id;
    }

    /**
     * 编辑
     * 不允许修改节点ID
     * @param int $id 操作ID
     * @param int $action_type 操作类型
     * @param string $action_name 操作名称
     * @param int $sort 排序，值大靠前
     * @return bool
     */
    public static function edit($id, $action_type = null, $action_name = null, $sort = null)
    {
        $data = [
            'update_on' => date('Y-m-d H:i:s')
        ];
        if (!is_null($action_type)) {
            $data['action_type'] = $action_type;
        }
        if (!is_null($action_name)) {
            $data['action_name'] = $action_name;
        }
        if (!is_null($sort)) {
            $data['sort'] = $sort;
        }

        $result = Db::name('workflow_node_action')->where('id', '=', $id)->update($data);
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id 操作ID
     * @return bool
     */
    public static function delete($id)
    {
        $result = Db::name('workflow_node_action')->where('id', '=', $id)->delete();
        return $result ? true : false;
    }

    /**
     * 检测操作类型是否合法
     * @param int $action_type
     */
    protected static function checkActionType($action_type)
    {
        $allow_action_types = [
            Operation::ACTION_TYPE_ADOPT, Operation::ACTION_TYPE_REJECT,
            Operation::ACTION_TYPE_GOBACK, Operation::ACTION_TYPE_HANGUP
        ];
        if (in_array($action_type, $allow_action_types)) {
            throw new RuntimeException("非法的操作类型");
        }
    }
}
