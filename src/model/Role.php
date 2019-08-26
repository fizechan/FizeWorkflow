<?php


namespace util\workflow\model;

use think\Db;

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
        $sql = <<<EOF
SELECT t_role.*, t_prole.`name` AS pname
FROM
gm_workflow_role AS t_role
LEFT JOIN gm_workflow_role AS t_prole ON t_prole.id = t_role.pid
EOF;
        $rows = Db::query($sql);
        if(!$rows){
            return [];
        }
        return $rows;
    }

    /**
     * 取得指定角色
     * @param $id
     * @return array
     */
    public static function getOne($id)
    {
        $row = Db::name('workflow_role')->where('id', '=', $id)->find();
        return $row;
    }

    /**
     * 添加
     * @param string $name 名称
     * @param int $pid 指定上级角色ID
     * @return int 新增角色ID
     */
    public static function add($name, $pid = 0)
    {
        $data = [
            'pid' => $pid,
            'name' => $name,
        ];

        $id = Db::name('workflow_role')->insertGetId($data);
        return $id;
    }

    /**
     * 编辑
     * @param int $id 角色ID
     * @param string $name 名称
     * @param int $pid 指定上级角色ID
     * @return bool
     */
    public static function edit($id, $name = null, $pid = null)
    {
        $data = [
            'update_on' => date('Y-m-d H:i:s')
        ];
        if(!is_null($name)){
            $data['name'] = $name;
        }
        if(!is_null($pid)){
            $data['pid'] = $pid;
        }

        $result = Db::name('workflow_role')->where('id', '=', $id)->update($data);
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id 角色ID
     * @return bool
     */
    public static function delete($id)
    {
        $child = Db::name('workflow_role')->where('pid', '=', $id)->find();
        if($child){
            //不允许删除带子角色的记录
            return false;
        }
        $result = Db::name('workflow_role')->where('id', '=', $id)->delete();
        return $result ? true : false;
    }
}