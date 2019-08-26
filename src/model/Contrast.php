<?php


namespace util\workflow\model;

use think\Db;

/**
 * 工作流实例提交模型
 * @todo extend字段无用，将删除
 */
class Contrast
{

    /**
     * 根据实例ID获取提交记录列表
     * @param int $instance_id 实例ID
     * @param int $last_contrast_id 指定最后一个提交ID，在该ID之前(含该ID)，否则返回全部记录
     * @return array
     */
    public static function getList($instance_id, $last_contrast_id = null)
    {
        $map = [
            ['instance_id', '=', $instance_id]
        ];
        if($last_contrast_id) {
            $map[] = ['id', '<=', $last_contrast_id];
        }
        $rows = Db::name('workflow_contrast')->where($map)->order('create_on', 'DESC')->select();
        if(!$rows){
            return [];
        }
        return $rows;
    }


    /**
     * 获取提交记录的最后一个JSON字段数组值
     * @param int $instance_id 实例ID
     * @param string $key JSON字段名(不含_json后缀)
     * @return array
     */
    private static function getLastJson($instance_id, $key)
    {
        $contrast = Db::name('workflow_contrast')->where('instance_id', '=', $instance_id)->order('create_on', 'DESC')->find();
        if(!$contrast){
            return [];
        }
        if(!$contrast["{$key}_json"]){
            return [];
        }
        $json = json_decode($contrast["{$key}_json"], true);
        if(!$json){
            return [];
        }
        return $json;
    }

    /**
     * 获取提交记录的最后一个提交表单数组值
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getLastForm($instance_id)
    {
        return self::getLastJson($instance_id, 'form');
    }

    /**
     * 获取提交记录的最后一个扩展信息数组值
     * @param int $instance_id 实例ID
     * @return array
     */
    public static function getLastExtend($instance_id)
    {
        return self::getLastJson($instance_id, 'extend');
    }

    /**
     * 获取提交记录的JSON字段数组值
     * @param int $id 提交ID
     * @param string $key JSON字段名(不含_json后缀)
     * @return array
     */
    private static function getJson($id, $key)
    {
        $contrast = Db::name('workflow_contrast')->where('id', '=', $id)->find();
        if(!$contrast){
            return [];
        }
        if(!$contrast["{$key}_json"]){
            return [];
        }
        $json = json_decode($contrast["{$key}_json"], true);
        if(!$json){
            return [];
        }
        return $json;
    }

    /**
     * 获取提交记录的提交表单数组值
     * @param int $id 提交ID
     * @return array
     */
    public static function getForm($id)
    {
        return self::getJson($id, 'form');
    }

    /**
     * 获取提交记录的扩展信息数组值
     * @param int $id 提交ID
     * @return array
     */
    public static function getExtend($id)
    {
        return self::getJson($id, 'extend');
    }
}