<?php


namespace fize\workflow\model;


use fize\db\Db;

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
        if(!$rows){
            return [];
        }
        $levels_nodes = [];
        foreach ($rows as $row){
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
        foreach ($levels_nodes as $index => $nodes){
            foreach ($nodes as $node){
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

    /**
     * 根据实例ID和节点ID创建待操作记录
     * @param int $contrast_id 提交ID
     * @param int $node_id 节点ID
     * @return int 操作记录ID
     */
    public static function createOperation($contrast_id, $node_id)
    {
        $contrast = Db::table('wf_workflow_contrast', '')->where(['id' => $contrast_id])->find();
        $node = Db::table('wf_workflow_node', '')->where(['id' => $node_id])->find();
        $data = [
            'scheme_id'   => $node['scheme_id'],
            'instance_id' => $contrast['instance_id'],
            'contrast_id' => $contrast_id,
            //user_id不指定
            //user_extend_id不指定
            'node_id'     => $node['id'],
            'node_name'   => $node['name'],
            'create_time' => date('Y-m-d H:i:s')
        ];
        $id = Db::table('wf_workflow_operation', '')->insert($data);
        return $id;
    }

    /**
     * 根据提交ID和节点ID创建指定用户待操作记录
     * @param int $contrast_id 提交ID
     * @param int $node_id 节点ID
     * @param int $user_id 用户ID
     * @return int 操作记录ID
     */
    public static function createOperationForUser($contrast_id, $node_id, $user_id)
    {
        $contrast = Db::table('wf_workflow_contrast', '')->where(['id' => $contrast_id])->find();
        $node = Db::table('wf_workflow_node', '')->where(['id' => $node_id])->find();
        $user = Db::table('wf_workflow_user', '')->where(['id' => $user_id])->find();
        $data = [
            'scheme_id'       => $node['scheme_id'],
            'instance_id'     => $contrast['instance_id'],
            'contrast_id'     => $contrast_id,
            'user_id'         => $user['id'],
            'user_extend_id'  => $user['extend_id'],
            'node_id'         => $node['id'],
            'node_name'       => $node['name'],
            'create_time'     => date('Y-m-d H:i:s'),
            'distribute_time' => date('Y-m-d H:i:s')
        ];
        $id = Db::table('wf_workflow_operation', '')->insert($data);
        return $id;
    }
}