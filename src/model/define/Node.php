<?php


namespace fize\workflow\model\define;

use fize\workflow\Db;

/**
 * 节点
 */
class Node
{

    /**
     * 方案当前的所有节点
     * @param int $def_scheme_id 方案ID
     * @return array 返回所有节点，以层级信息分布
     */
    public static function current($def_scheme_id)
    {
        $rows = Db::table('workflow_def_node')->where(['def_scheme_id' => $def_scheme_id])->order(['level' => 'ASC'])->select();
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
        $node = Db::table('workflow_node')->where(['id' => $node_id])->find();
        $rows = Db::table('workflow_node')
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
     * @param int $scheme_id 方案ID
     * @param array $levels_nodes 以层级信息分布的待设置的所有节点
     */
    public static function build($scheme_id, $levels_nodes)
    {
        Db::table('workflow_node')->where(['scheme_id' => $scheme_id])->delete();
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
        Db::table('workflow_node')->insertAll($datas);
    }

    /**
     * 删除
     * @todo 需要添加软删除功能
     * @param int $node_id 节点ID
     */
    public static function delete($node_id)
    {
        Db::table('workflow_action')->where(['node_id' => $node_id])->delete();
        Db::table('workflow_node_role')->where(['node_id' => $node_id])->delete();
        Db::table('workflow_node')->where(['id' => $node_id])->delete();
        Db::table('workflow_operation')->where(['node_id' => $node_id])->delete();
    }

    public static function setTos()
    {

    }
}
