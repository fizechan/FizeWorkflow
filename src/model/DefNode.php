<?php

namespace fize\workflow\model;

use fize\workflow\Db;

/**
 * 定义：节点
 */
class DefNode
{

    /**
     * 方案所有节点
     * @param int $def_scheme_id 方案ID
     * @return array 返回所有节点，以层级信息分布
     */
    public static function getListBySchemeId(int $def_scheme_id): array
    {
        $rows = Db::table('workflow_def_node')
            ->where(['def_scheme_id' => $def_scheme_id])
            ->order(['is_start' => 'DESC', 'is_end' => 'ASC'])
            ->select();
        $levels_nodes = [];
        foreach ($rows as $row) {
            $levels_nodes[$row['level'] - 1][] = $row;
        }
        return $levels_nodes;
    }

    /**
     * 返回指定节点的前所有节点
     * @param int $def_node_id 节点ID
     * @return array
     */
    public static function previous(int $def_node_id): array
    {
        $node = Db::table('workflow_def_node')->where(['id' => $def_node_id])->find();
        $rows = Db::table('workflow_def_node')
            ->where([
                'scheme_id' => $node['scheme_id'],
                'level'     => ['<', $node['level']]
            ])
            ->order(['level' => 'ASC'])
            ->select();
        return $rows;
    }

    /**
     * 创建节点
     * @param int   $def_scheme_id 方案ID
     * @param array $levels_nodes  以层级信息分布的待设置的所有节点
     */
    public static function build(int $def_scheme_id, array $levels_nodes)
    {
        Db::table('workflow_def_node')->where(['def_scheme_id' => $def_scheme_id])->delete();
        $datas = [];
        foreach ($levels_nodes as $index => $nodes) {
            foreach ($nodes as $node) {
                $datas[] = [
                    'def_scheme_id' => $def_scheme_id,
                    'level'         => $index + 1,
                    'name'          => $node['name'],
                    'class'         => $node['class']
                ];
            }
        }
        Db::table('workflow_def_node')->insertAll($datas);
    }

    /**
     * 删除
     * @param int $def_node_id 节点ID
     * @todo 需要添加软删除功能
     */
    public static function delete(int $def_node_id)
    {
        $ist_action_ids = Db::table('workflow_ist_action')
            ->where(['def_node_id' => $def_node_id])
            ->column('id');
        Db::table('workflow_ist_action_field')->where(['ist_action_id' => ['IN', $ist_action_ids]])->delete();
        Db::table('workflow_ist_action')->where(['def_node_id' => $def_node_id])->delete();
        Db::table('workflow_def_node_to')
            ->where([
                'from_def_node_id' => ['=', $def_node_id],
                'to_def_node_id'   => ['=', $def_node_id, 'OR']
            ])
            ->delete();
        Db::table('workflow_def_node_role')->where(['def_node_id' => $def_node_id])->delete();
        Db::table('workflow_def_node_field')->where(['def_node_id' => $def_node_id])->delete();
        Db::table('workflow_def_node_action')->where(['def_node_id' => $def_node_id])->delete();
        Db::table('workflow_def_node')->where(['id' => $def_node_id])->delete();
    }

    public static function setTos()
    {

    }
}
