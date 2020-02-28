<?php


namespace util\workflow\model;

use think\Db;

/**
 * 节点操作
 * 节点操作非必要项，可以直接定义在节点前端页面上，然后传值的时候记得带上既可
 */
class NodeAction
{

    /**
     * 获取指定节点的所有操作
     * @param int $node_id 节点ID
     * @return array
     */
    public static function getList($node_id)
    {
        $rows = Db::name('workflow_node_action')->where('node_id', '=', $node_id)->order(['id' => 'ASC', 'sort' => 'DESC'])->select();
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
        $data = [
            'node_id'     => $node_id,
            'action_type' => $action_type,
            'action_name' => $action_name,
            'sort'        => $sort
        ];
        $id = Db::name('workflow_node_action')->insertGetId($data);
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
     * 默认初始化
     * 建立4个默认操作
     * @param int $node_id 节点ID
     */
    public static function initialize($node_id)
    {
        self::add($node_id, Operation::ACTION_TYPE_ADOPT, '通过');
        self::add($node_id, Operation::ACTION_TYPE_REJECT, '否决');
        self::add($node_id, Operation::ACTION_TYPE_GOBACK, '退回');
        //self::add($node_id, Operation::ACTION_TYPE_HANGUP, '挂起');
    }
}
