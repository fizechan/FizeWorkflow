<?php

namespace Fize\Workflow\Define;

use Fize\Codec\Json;
use Fize\Workflow\Db;
use Fize\Workflow\Scheme as WorkflowScheme;

/**
 * 方案
 */
class Scheme
{

    /**
     * 创建
     * @param string      $name  名称
     * @param string|null $class 逻辑类全限定名
     * @param string|null $type  自定义类型
     * @return int 返回方案ID
     */
    public static function create(string $name, string $class = null, string $type = null): int
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
     * @param int $def_scheme_id 方案ID
     * @return array
     */
    public static function getFields(int $def_scheme_id): array
    {
        $rows = Db::table('workflow_scheme_field')->where(['scheme_id' => $def_scheme_id])->order(['sort' => 'ASC', 'create_time' => 'ASC'])->select();
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
     * @param int $def_scheme_id 方案ID
     * @return int 新的方案ID
     */
    public static function copy(int $def_scheme_id): int
    {
        $scheme = Db::table('def_workflow_scheme')->where(['id' => $def_scheme_id])->find();

        $data_scheme = $scheme;
        unset($data_scheme['id']);
        $new_def_scheme_id = Db::table('def_workflow_scheme')->insertGetId($data_scheme);

        $scheme_fields = Db::table('def_workflow_scheme_field')->where(['def_scheme_id' => $def_scheme_id])->select();
        $data_scheme_fields = [];
        foreach ($scheme_fields as $scheme_field) {
            unset($scheme_field['id']);
            $scheme_field['def_scheme_id'] = $new_def_scheme_id;
            $scheme_fields['create_time'] = date('Y-m-d H:i:s');
            $data_scheme_fields[] = $scheme_field;
        }
        Db::table('def_workflow_scheme_field')->insertAll($data_scheme_fields);

        $nodes = Db::table('def_workflow_node')->where(['def_scheme_id' => $def_scheme_id])->select();

        $map_node = [];
        foreach ($nodes as $node) {
            $data_node = $node;
            unset($data_node['id']);
            $data_node['def_scheme_id'] = $new_def_scheme_id;
            $new_def_node_id = Db::table('def_workflow_node')->insertGetId($data_node);

            $map_node[(string)$node['id']] = $new_def_node_id;

            $actions = Db::table('def_workflow_node_action')->where(['def_node_id' => $node['id']])->select();
            $data_actions = [];
            foreach ($actions as $action) {
                $data_action = $action;
                unset($data_action['id']);
                $data_action['def_node_id'] = $new_def_node_id;
                $data_action['create_time'] = date('Y-m-d H:i:s');
                $data_actions[] = $data_action;
            }
            Db::table('def_workflow_node_action')->insertAll($data_scheme_fields);

            $node_fields = Db::table('def_workflow_node_field')->where(['def_node_id' => $node['id']])->select();
            $data_node_fields = [];
            foreach ($node_fields as $node_field) {
                $data_node_field = $node_field;
                unset($data_node_field['id']);
                $data_node_field['def_node_id'] = $new_def_node_id;
                $data_node_field['create_time'] = date('Y-m-d H:i:s');
                $data_node_fields[] = $data_node_field;
            }
            Db::table('def_workflow_node_field')->insertAll($data_node_fields);

            $node_roles = Db::table('def_workflow_node_field')->where(['def_node_id' => $node['id']])->select();
            $data_node_roles = [];
            foreach ($node_roles as $node_role) {
                $data_node_role = $node_role;
                unset($data_node_role['id']);
                $data_node_role['def_node_id'] = $new_def_node_id;
                $data_node_role['create_time'] = date('Y-m-d H:i:s');
                $data_node_roles[] = $data_node_role;
            }
            Db::table('def_workflow_node_role')->insertAll($data_node_roles);
        }

        $node_tos = Db::table('def_workflow_node_to')->where(['def_scheme_id' => $def_scheme_id])->select();
        $data_node_tos = [];
        foreach ($node_tos as $node_to) {
            $data_node_to = [
                'def_scheme_id'    => $new_def_scheme_id,
                'from_def_node_id' => $map_node[(string)$node_to['from_def_node_id']],
                'to_def_node_id'   => $map_node[(string)$node_to['to_def_node_id']],
                'entry_condition'  => $node_to['entry_condition']
            ];
            $data_node_tos[] = $data_node_to;
        }
        Db::table('def_workflow_node_to')->insertAll($data_node_tos);

        return $new_def_scheme_id;
    }

    /**
     * 删除
     * @param int $id ID
     * @todo 待修改
     */
    public static function delete(int $id)
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
