<?php


namespace util\workflow\realization\scheme;

use util\workflow\model\Instance;
use think\Db;

/**
 * 方案动作
 */
trait Action
{
    /**
     * 审批通过
     * @param int $instance_id 方案实例ID
     * @return bool 成功返回true，失败返回false
     */
    public function adopt($instance_id)
    {
        Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->update(['is_finish' => 1]);

        $data_instance = [
            'status'    => Instance::STATUS_ADOPT,
            'is_finish' => 1
        ];
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data_instance);
        return true;
    }

    /**
     * 审批否决
     * @param int $instance_id 方案实例ID
     * @return bool 成功返回true，失败返回false
     */
    public function reject($instance_id)
    {
        Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->update(['is_finish' => 1]);

        $data = [
            'status'    => Instance::STATUS_REJECT,
            'is_finish' => 1
        ];
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data);
        return true;
    }

    /**
     * 审批退回
     * @param int $instance_id 方案实例ID
     * @return bool 成功返回true，失败返回false
     */
    public function goback($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_GOBACK,
            'is_finish' => 0
        ];
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data);
        return true;
    }

    /**
     * 审批挂起
     * @param int $instance_id 方案实例ID
     * @return bool 成功返回true，失败返回false
     */
    public function hangup($instance_id)
    {
        $data = [
            'status'    => Instance::STATUS_HANGUP,
            'is_finish' => 0
        ];
        Db::name('workflow_instance')->where('id', '=', $instance_id)->update($data);
        return true;
    }
}
