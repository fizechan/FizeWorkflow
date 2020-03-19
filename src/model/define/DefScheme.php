<?php


namespace fize\workflow\model\define;

use fize\crypt\Json;
use fize\workflow\Db;
use fize\workflow\Scheme as WorkflowScheme;

/**
 * 方案
 */
class DefScheme
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
     * @param int $scheme_id 方案ID
     * @return int 新的方案ID
     */
    public static function copy($scheme_id)
    {
        $scheme = Db::table('workflow_scheme')->where(['id' => $scheme_id])->find();

        $data_scheme = $scheme;
        unset($data_scheme['id']);
        $new_scheme_id = Db::table('workflow_scheme')->insertGetId($data_scheme);

        $scheme_fields = Db::table('workflow_scheme_field')->where(['scheme_id' => $scheme_id])->select();
        $data_scheme_fields = [];
        foreach ($scheme_fields as $scheme_field) {
            unset($scheme_field['id']);
            $scheme_field['scheme_id'] = $new_scheme_id;
            $scheme_fields['create_time'] = date('Y-m-d H:i:s');
            $data_scheme_fields[] = $scheme_field;
        }
        Db::table('workflow_scheme_field')->insertAll($data_scheme_fields);

        $nodes = Db::table('workflow_node')->where(['scheme_id' => $scheme_id])->select();

        $map_node = [];
        foreach ($nodes as $node) {
            $data_node = $node;
            unset($data_node['id']);
            $data_node['scheme_id'] = $new_scheme_id;
            $new_node_id = Db::table('workflow_node')->insertGetId($data_node);

            $map_node[(string)$new_node_id] = $node['id'];

            $actions = Db::table('workflow_node_action')->where(['node_id' => $node['id']])->select();
            $data_actions = [];
            foreach ($actions as $action) {
                $data_action = $action;
                unset($data_action['id']);
                $data_action['node_id'] = $new_node_id;
                $data_action['create_time'] = date('Y-m-d H:i:s');
                $data_actions[] = $data_action;
            }
            Db::table('workflow_node_action')->insertAll($data_scheme_fields);

            $node_fields = Db::table('workflow_node_field')->where(['node_id' => $node['id']])->select();
            $node_roles = Db::table('workflow_node_field')->where(['node_id' => $node['id']])->select();
            $node_tos = Db::table('workflow_node_field')->where(['node_id' => $node['id']])->select();
        }



        //更新tos
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
