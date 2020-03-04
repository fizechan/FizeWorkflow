<?php


namespace fize\workflow\model;

use fize\crypt\Json;
use fize\workflow\Db;
use fize\workflow\Field;
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
            'name' => $name,
            'class' => $class,
            'type' => $type
        ];
        return Db::table('workflow_scheme')->insertGetId($data);
    }

    /**
     * 返回定义的表单字段
     * @param int $scheme_id 方案ID
     * @return Field[]
     */
    public static function getFields($scheme_id)
    {
        $rows = Db::table('workflow_scheme_field')->where(['scheme_id' => $scheme_id])->order(['sort' => 'ASC', 'create_time' => 'ASC'])->select();
        $fields = [];
        foreach ($rows as $row) {
            $field = new Field();

            $field->title = $row['title'];
            $field->name = $row['name'];
            $field->type = $row['type'];
            $field->isRequired = (int)$row['is_required'];
            $field->regexMatch = $row['regex_match'];
            $field->preload = $row['preload'];
            $field->value = $row['value'];
            $field->hint = $row['hint'];
            $field->attrs = $row['attrs'] ? Json::decode($row['attrs']) : null;
            $field->extend = $row['extend'] ? Json::decode($row['extend']) : null;
            $field->sort = $row['sort'];

            $fields[] = $field;
        }
        return $fields;
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
     * @todo 待修改
     * @param int $id ID
     */
    public static function delete($id)
    {
        $instance = Db::table('workflow_instance')->where('scheme_id', '=', $id)->find();
        Db::table('workflow_contrast')->where('instance_id', '=', $instance['id'])->delete();
        Db::table('workflow_instance')->where('id', '=', $instance['id'])->delete();
        $nodes = Db::table('workflow_node')->where('scheme_id', '=', $id)->select();
        if ($nodes) {
            foreach ($nodes as $node) {
                Db::table('workflow_node_action')->where('node_id', '=', $node['id'])->delete();
                Db::table('workflow_node_role')->where('node_id', '=', $node['id'])->delete();
                Db::table('workflow_node_user')->where('node_id', '=', $node['id'])->delete();
                Db::table('workflow_node')->where('id', '=', $node['id'])->delete();
            }
        }
        Db::table('workflow_operation')->where('instance_id', '=', $instance['id'])->delete();
        Db::table('workflow_scheme')->where('id', '=', $id)->delete();
    }
}
