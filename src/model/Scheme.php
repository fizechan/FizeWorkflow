<?php


namespace util\workflow\model;

use fize\db\Db;

/**
 * 方案
 */
class Scheme
{

    /**
     * 实例化
     * @param string $name 实例名称
     * @param int $scheme_id 方案ID
     * @return int 实例ID
     */
    public static function instance($name, $scheme_id)
    {
        $scheme = Db::table('wf_workflow_scheme', '')->where('id = ?', [$scheme_id])->find();
        $data_instance = [
            'scheme_type' => $scheme['type'],
            'scheme_id'   => $scheme['id'],
            'name'        => $name,
            'status'      => 0,
            'is_finish'   => 0
        ];

        $instance_id = Db::table('wf_workflow_instance', '')->insert($data_instance);
        return $instance_id;
    }

    /**
     * 为指定用户分配方案任务
     * @param int $user_id 用户ID
     * @param int $scheme_id 方案ID
     * @return bool 有任务分配时返回true，否则返回false
     */
    public function distribute($user_id, $scheme_id)
    {
        $user = Db::table('wf_workflow_user', '')->where('id = ?', [$user_id])->find();
        $sql = <<<EOF
SELECT t_operation.id
FROM wf_workflow_operation AS t_operation
LEFT JOIN wf_workflow_instance AS t_instance ON t_instance.id = t_operation.instance_id
LEFT JOIN wf_workflow_node_role AS t_noderole ON t_noderole.node_id = t_operation.node_id
WHERE
t_operation.user_id IS NULL
AND t_instance.scheme_id = ?
AND EXISTS ( SELECT 1 FROM  wf_workflow_user_role WHERE user_id = ? AND role_id = t_noderole.role_id)
ORDER BY RAND()
LIMIT 1
EOF;
        $params = [
            $scheme_id, $user['id']
        ];

        $operations = Db::query($sql, $params);

        if (empty($operations)) {
            return false;
        }

        $data = [
            'user_id'         => $user['id'],
            'user_extend_id'  => $user['extend_id'],
            'distribute_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('wf_workflow_operation', '')->where('id = ?', [$operations[0]['id']])->update($data);
        return true;
    }

    /**
     * 删除
     * @param int $scheme_id 方案ID
     */
    public static function delete($scheme_id)
    {
        $instance = Db::table('wf_workflow_instance', '')->where(['scheme_id' => [$scheme_id]])->find();
        Db::table('wf_workflow_contrast', '')->where(['instance_id' => $instance['id']])->delete();
        Db::table('wf_workflow_instance', '')->where(['id' => $instance['id']])->delete();
        $nodes = Db::table('wf_workflow_node', '')->where(['scheme_id' => $scheme_id])->select();
        if ($nodes) {
            foreach ($nodes as $node) {
                Db::table('wf_workflow_node_action', '')->where(['node_id' => $node['id']])->delete();
                Db::table('wf_workflow_node_role', '')->where(['node_id' => $node['id']])->delete();
                Db::table('wf_workflow_node_user', '')->where(['node_id' => $node['id']])->delete();
                Db::table('wf_workflow_node', '')->where(['id' => $node['id']])->delete();
            }
        }
        Db::table('wf_workflow_operation', '')->where(['instance_id' => $instance['id']])->delete();
        Db::table('wf_workflow_scheme', '')->where(['id' => $scheme_id])->delete();
    }
}