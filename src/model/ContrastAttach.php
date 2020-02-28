<?php


namespace util\workflow\model;

use think\Db;

/**
 * 工作流提交附件
 * @todo 附件将作为表单的一部分使用，不再独立
 */
class ContrastAttach
{

    /**
     * 根据提交ID，返回相应提交记录附件列表
     * @param int $contrast_id 提交ID
     * @param mixed $type 指定类型，多个以数组形式
     * @return array
     */
    public static function getListByContrastId($contrast_id, $type = null)
    {
        $map = [
            ['contrast_id', '=', $contrast_id]
        ];
        if (!is_null($type)) {
            if (is_array($type)) {
                $map[] = ['type', 'IN', $type];
            } else {
                $map[] = ['type', '=', $type];
            }
        }
        $rows = Db::name('workflow_contrast_attach')->where($map)->order('create_on', 'ASC')->select();
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 根据实例ID，返回相应实例记录附件列表
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getListByInstanceId($instance_id)
    {
        $sql = <<<EOF
SELECT t_attach.*, t_admin.nickname AS admin_nickname
FROM gm_workflow_contrast_attach AS t_attach
LEFT JOIN gm_workflow_contrast AS t_contrast ON t_contrast.id = t_attach.contrast_id
LEFT JOIN gm_admin AS t_admin ON t_admin.id = t_attach.create_by
WHERE t_contrast.instance_id = {$instance_id}
ORDER BY t_attach.create_on ASC
EOF;
        $rows = Db::query($sql);
        if (!$rows) {
            return [];
        }
        return $rows;
    }

    /**
     * 进行非必要上传文件清理
     * @param array $attachs 全部上传文件列表
     * @return array 返回可上传的文件列表
     */
    public static function cleanUpAndFormat(array $attachs)
    {
        foreach ($attachs as $index => $attach) {
            $attach = json_decode($attach, true);
            if (isset($attach['id'])) {
                $orig_attach = Db::name('workflow_contrast_attach')->where('id', '=', $attach['id'])->find();
                if ($orig_attach && $orig_attach['title'] == $attach['title']) {
                    unset($attachs[$index]);  //原先已上传的无需再次上传
                    continue;
                }
            }
            $attach['type'] = 'file';
            $attachs[$index] = $attach;
        }
        return $attachs;
    }
}
