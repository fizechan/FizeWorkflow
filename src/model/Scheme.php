<?php


namespace util\workflow\model;

use think\Db;

/**
 * 工作流方案
 */
class Scheme
{
    /**
     * 删除
     * @param int $id ID
     */
    public static function delete($id)
    {
        $instance = Db::name('workflow_instance')->where('scheme_id', '=', $id)->find();
        Db::name('workflow_contrast')->where('instance_id', '=', $instance['id'])->delete();
        Db::name('workflow_instance')->where('id', '=', $instance['id'])->delete();
        $nodes = Db::name('workflow_node')->where('scheme_id', '=', $id)->select();
        if ($nodes) {
            foreach ($nodes as $node) {
                Db::name('workflow_node_action')->where('node_id', '=', $node['id'])->delete();
                Db::name('workflow_node_role')->where('node_id', '=', $node['id'])->delete();
                Db::name('workflow_node_user')->where('node_id', '=', $node['id'])->delete();
                Db::name('workflow_node')->where('id', '=', $node['id'])->delete();
            }
        }
        Db::name('workflow_operation')->where('instance_id', '=', $instance['id'])->delete();
        Db::name('workflow_scheme')->where('id', '=', $id)->delete();
    }
}
