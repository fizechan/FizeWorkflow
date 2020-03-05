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
        $sql = <<<EOF
SELECT t_user.*, t_role.name AS role_name, t_puser.name AS pname
FROM
gm_workflow_user AS t_user
LEFT JOIN gm_workflow_role AS t_role ON t_role.id = t_user.role_id
LEFT JOIN gm_workflow_user AS t_puser ON t_puser.id = t_user.pid
WHERE t_user.id <> 0
EOF;
        $params = [];
        if (!is_null($pid)) {
            $params[] = $pid;
            $sql .= " AND t_user.pid = ?";
        }
        if (!is_null($role_id)) {
            $params[] = $role_id;
            $sql .= " AND t_user.role_id = ?";
        }
        if (!is_null($kwd)) {
            $params[] = "%{$kwd}%";
            $sql .= " AND t_user.name LIKE ?";
        }
        $rows = Db::query($sql, $params);
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 取得用户分页
     * @param int $page 指定页码
     * @param int $limit 每页数量
     * @param int $pid 指定父用户ID
     * @param int $role_id 指定角色ID
     * @param string $kwd 搜索关键字
     * @return array [$total, $row]
     */
    public static function getPage($page, $limit = 10, $pid = null, $role_id = null, $kwd = null)
    {
        $sql = <<<EOF
SELECT t_user.*, t_role.name AS role_name, t_puser.name AS pname
FROM
gm_workflow_user AS t_user
LEFT JOIN gm_workflow_role AS t_role ON t_role.id = t_user.role_id
LEFT JOIN gm_workflow_user AS t_puser ON t_puser.id = t_user.pid
WHERE t_user.id <> 0
EOF;
        $params = [];
        if (!is_null($pid)) {
            $params[] = $pid;
            $sql .= " AND t_user.pid = ?";
        }
        if (!is_null($role_id)) {
            $params[] = $role_id;
            $sql .= " AND t_user.role_id = ?";
        }
        if (!is_null($kwd)) {
            $params[] = "%{$kwd}%";
            $sql .= " AND t_user.name LIKE ?";
        }
        $full_sql = substr_replace($sql, " SQL_CALC_FOUND_ROWS ", 6, 0);
        $offset = ($page - 1) * $limit;
        $full_sql .= " LIMIT {$offset},{$limit}";
        $row = Db::query($full_sql, $params);
        $cout_sql = 'SELECT FOUND_ROWS() AS `hr_count`';
        $crw = Db::query($cout_sql);
        $total = $crw[0]['hr_count'];
        return [$total, $row];
    }

    /**
     * 取得角色为$role_id的父角色的所有用户
     * @param int $role_id 角色ID
     * @return array
     */
    public static function getProleUsers($role_id)
    {
        $role = Db::name('workflow_role')->where('id', '=', $role_id)->find();
        $users = Db::name('workflow_user')->where('role_id', '=', $role['pid'])->select();
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
        $id = Db::name('workflow_user')->insertGetId($data);
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

        $result = Db::name('workflow_user')->where('id', '=', $id)->update($data);
        return $result ? true : false;
    }

    /**
     * 删除
     * @param int $id 角色ID
     * @return bool
     */
    public static function delete($id)
    {
        $child = Db::name('workflow_user')->where('pid', '=', $id)->find();
        if ($child) {
            //不允许删除带子用户的记录
            return false;
        }
        $result = Db::name('workflow_user')->where('id', '=', $id)->delete();
        return $result ? true : false;
    }
}
