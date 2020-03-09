<?php


namespace fize\workflow\model;

use fize\workflow\Db;

/**
 * 工作流用户
 */
class User
{

    /**
     * 取得所有用户
     * @param int $pid 指定父用户ID
     * @param int $role_id 指定角色ID
     * @param string $kwd 搜索关键字
     * @return array
     */
    public static function getList($pid = null, $role_id = null, $kwd = null)
    {
        $map = [];
        if ($pid) {
            $map['t_user.pid'] = $pid;
        }
        if ($role_id) {
            $map['t_user.role_id'] = $role_id;
        }
        if ($kwd) {
            $map['t_user.name'] = ['LIKE', "%{$kwd}%"];
        }

        $rows = Db::table('workflow_user')
            ->alias('t_user')
            ->leftJoin(['workflow_role', 't_role'], 't_role.id = t_user.role_id')
            ->leftJoin(['workflow_user', 't_puser'], 't_puser.id = t_user.pid')
            ->field('t_user.*, t_role.name AS role_name, t_puser.name AS pname')
            ->select();

        return $rows;
    }

    /**
     * 取得用户分页
     * @param int $page 指定页码
     * @param int $size 每页数量
     * @param int $pid 指定父用户ID
     * @param int $role_id 指定角色ID
     * @param string $kwd 搜索关键字
     * @return array [$total, $row]
     */
    public static function getPage($page, $size = 10, $pid = null, $role_id = null, $kwd = null)
    {
        $map = [];
        if ($pid) {
            $map['t_user.pid'] = $pid;
        }
        if ($role_id) {
            $map['t_user.role_id'] = $role_id;
        }
        if ($kwd) {
            $map['t_user.name'] = ['LIKE', "%{$kwd}%"];
        }

        $result = Db::table('workflow_user')
            ->alias('t_user')
            ->leftJoin(['workflow_role', 't_role'], 't_role.id = t_user.role_id')
            ->leftJoin(['workflow_user', 't_puser'], 't_puser.id = t_user.pid')
            ->field('t_user.*, t_role.name AS role_name, t_puser.name AS pname')
            ->paginate($page, $size);

        return $result;
    }

    /**
     * 取得角色为$role_id的父角色的所有用户
     * @param int $role_id 角色ID
     * @return array
     */
    public static function getProleUsers($role_id)
    {
        $role = Db::table('workflow_role')->where(['id' => $role_id])->find();
        $users = Db::table('workflow_user')->where(['role_id' => $role['pid']])->select();
        if (!$users) {
            return [];
        }
        return $users;
    }

    /**
     * 添加
     * @param int $extend_id 用户外部ID
     * @param int $role_id 角色ID
     * @param string $name 名称
     * @param int $pid 指定上级用户ID
     * @param float $extend_quota 最高审批额度，为null时表示不指定
     * @return int
     */
    public static function add($extend_id, $role_id, $name, $pid = 0, $extend_quota = null)
    {
//        $find = Db::name('workflow_user')->where('extend_id', '=', $extend_id)->find();
//        if($find){
//            //只允许绑定一个工作流账号
//            return 0;
//        }
        $data = [
            'pid'          => $pid,
            'role_id'      => $role_id,
            'name'         => $name,
            'extend_id'    => $extend_id,
            'extend_quota' => $extend_quota
        ];
        $id = Db::table('workflow_user')->insertGetId($data);
        return $id;
    }

    /**
     * @param int $id 用户ID
     * @param int $extend_id 外部ID
     * @param int $role_id 角色ID
     * @param string $name 名称
     * @param int $pid 指定上级用户ID
     * @param mixed $extend_quota 最高审批额度，为null时表示不指定,false表示不修改
     * @return bool
     */
    public static function edit($id, $extend_id = null, $role_id = null, $name = null, $pid = null, $extend_quota = false)
    {
//        $map = [
//            ['id', '<>', $id],
//            ['extend_id', '=', $extend_id]
//        ];
//        $find = Db::name('workflow_user')->where($map)->find();
//        if($find){
//            //只允许绑定一个工作流账号
//            return 0;
//        }
        $data = [
            'update_on' => date('Y-m-d H:i:s')
        ];
        if (!is_null($extend_id)) {
            $data['extend_id'] = $extend_id;
        }
        if (!is_null($role_id)) {
            $data['role_id'] = $role_id;
        }
        if (!is_null($name)) {
            $data['name'] = $name;
        }
        if (!is_null($pid)) {
            $data['pid'] = $pid;
        }
        if ($extend_quota !== false) {
            $data['extend_quota'] = $extend_quota;
        }

        $result = Db::table('workflow_user')->where(['id' => $id])->update($data);
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id 角色ID
     * @return bool
     */
    public static function delete($id)
    {
        $child = Db::table('workflow_user')->where(['pid' => $id])->find();
        if ($child) {
            //不允许删除带子用户的记录
            return false;
        }
        $result = Db::table('workflow_user')->where(['id' => $id])->delete();
        return $result ? true : false;
    }
}
