<?php


namespace fize\workflow\model;

use fize\crypt\Json;
use fize\workflow\Db;
use fize\workflow\Scheme as WorkflowScheme;

/**
 * 方案
 */
class Scheme
{

    /**
     * 创建
     * @param string $name 名称
     * @param string $class 逻辑类全限定名
     * @param string $type 自定义类型
     * @return int 返回方案ID
     */
    public static function create($name, $class = null, $type = null)
    {
        if (is_null($class)) {
            $class = WorkflowScheme::class;
        }
        $data = [
            'name'  => $name,
            'class' => $class,
            'type'  => $type
        ];
        return Db::table('workflow_scheme')->insertGetId($data);
    }

    /**
     * 返回定义的表单字段
     * @param int $scheme_id 方案ID
     * @return array
     */
    public static function getFields($scheme_id)
    {
        $rows = Db::table('workflow_scheme_field')->where(['scheme_id' => $scheme_id])->order(['sort' => 'ASC', 'create_time' => 'ASC'])->select();
        foreach ($rows as $index => $row) {
            if ($row['attrs']) {
                $rows[$index]['attrs'] = Json::decode($row['attrs']);
            }
            if ($row['extend']) {
                $rows[$index]['extend'] = Json::decode($row['extend']);
            }
        }
        return $rows;
    }

    /**
     * 一键复制
     * @param $scheme_id
     */
    public static function copy($scheme_id)
    {

    }

    /**
     * 删除
     * @param int $id ID
     * @todo 待修改
     */
    public static function delete($id)
    {
        $instance = Db::table('workflow_instance')->where(['scheme_id' => $id])->find();
        Db::table('workflow_contrast')->where(['instance_id' => $instance['id']])->delete();
        Db::table('workflow_instance')->where(['id' => $instance['id']])->delete();
        $nodes = Db::table('workflow_node')->where(['scheme_id' => $id])->select();
        if ($nodes) {
            foreach ($nodes as $node) {
                Db::table('workflow_node_action')->where(['node_id' => $node['id']])->delete();
                Db::table('workflow_node_role')->where(['node_id' => $node['id']])->delete();
                Db::table('workflow_node_user')->where(['node_id' => $node['id']])->delete();
                Db::table('workflow_node')->where(['id' => $node['id']])->delete();
            }
        }
        Db::table('workflow_operation')->where(['instance_id' => $instance['id']])->delete();
        Db::table('workflow_scheme')->where(['id' => $id])->delete();
    }
}
