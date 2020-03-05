<?php


namespace fize\workflow\model;

use fize\workflow\Db;

/**
 * 节点
 */
class Node
{

    /**
     * 方案当前的所有节点
     * @param int $scheme_id 方案ID
     * @return array 返回所有节点，以层级信息分布
     */
    public static function current($scheme_id)
    {
        $rows = Db::name('workflow_node')->where('scheme_id', '=', $scheme_id)->order('level', 'ASC')->select();
        if (!$rows) {
            return [];
        }
        $levels_nodes = [];
        foreach ($rows as $row) {
            $levels_nodes[$row['level'] - 1][] = $row;
        }
        return $levels_nodes;
    }

    /**
     * 返回指定节点的前所有节点
     * @param int $node_id 节点ID
     * @return array
     */
    public static function previous($node_id)
    {
        $node = Db::name('workflow_node')->where('id', '=', $node_id)->find();
        $rows = Db::name('workflow_node')
            ->where('scheme_id', '=', $node['scheme_id'])
            ->where('level', '<', $node['level'])
            ->order('level', 'ASC')
            ->select();
        return $rows;
    }

    /**
     * 创建节点
     * @param int $scheme_id 方案ID
     * @param array $levels_nodes 以层级信息分布的待设置的所有节点
     */
    public static function build($scheme_id, array $levels_nodes)
    {
        Db::name('workflow_node')->where('scheme_id', '=', $scheme_id)->delete();
        $datas = [];
        foreach ($levels_nodes as $index => $nodes) {
            foreach ($nodes as $node) {
                $datas[] = [
                    'scheme_id' => $scheme_id,
                    'level'     => $index + 1,
                    'name'      => $node['name'],
                    'class'     => $node['class']
                ];
            }
        }
        Db::name('workflow_node')->insertAll($datas);
    }

    /**
     * 删除
     * @param int $id ID
     */
    public static function delete($id)
    {
        Db::name('workflow_node_action')->where('node_id', '=', $id)->delete();
        Db::name('workflow_node_role')->where('node_id', '=', $id)->delete();
        Db::name('workflow_node_user')->where('node_id', '=', $id)->delete();
        Db::name('workflow_node')->where('id', '=', $id)->delete();
        Db::name('workflow_operation')->where('node_id', '=', $id)->delete();
    }
}
