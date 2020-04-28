<?php

namespace fize\workflow\model;

use fize\workflow\Db;

/**
 * 工作流角色
 */
class Role
{

    /**
     * 取得所有角色
     * @return array
     */
    public static function getList()
    {
        $rows = Db::table('workflow_role')
            ->alias('t_role')
            ->leftJoin(['gm_workflow_role', 't_prole'], 't_prole.id = t_role.pid')
            ->field('t_role.*, t_prole.`name` AS pname')
            ->select();

        return $rows;
    }

    /**
     * 取得指定角色
     * @param int $id 角色ID
     * @return array
     */
    public static function getOne($id)
    {
        $row = Db::table('workflow_role')->where(['id' => $id])->find();
        return $row;
    }

    /**
     * 添加
     * @param string $name 名称
     * @param int    $pid  指定上级角色ID
     * @return int 新增角色ID
     */
    public static function add($name, $pid = 0)
    {
        $data = [
            'pid'  => $pid,
            'name' => $name,
        ];

        $id = Db::table('workflow_role')->insertGetId($data);
        return $id;
    }

    /**
     * 编辑
     * @param int    $id   角色ID
     * @param string $name 名称
     * @param int    $pid  指定上级角色ID
     * @return bool
     */
    public static function edit($id, $name = null, $pid = null)
    {
        $data = [
            'update_on' => date('Y-m-d H:i:s')
        ];
        if (!is_null($name)) {
            $data['name'] = $name;
        }
        if (!is_null($pid)) {
            $data['pid'] = $pid;
        }

        $result = Db::table('workflow_role')->where(['id' => $id])->update($data);
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id 角色ID
     * @return bool
     */
    public static function delete($id)
    {
        $child = Db::table('workflow_role')->where(['pid' => $id])->find();
        if ($child) {
            //不允许删除带子角色的记录
            return false;
        }
        Db::table('workflow_role')->where(['id' => $id])->delete();
        Db::table('workflow_node_role')->where(['role_id' => $id])->delete();
        return true;
    }
}
