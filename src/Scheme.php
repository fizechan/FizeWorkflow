<?php


namespace fize\workflow;

/**
 * 方案
 */
class Scheme extends Common
{

    /**
     * 返回表单字段
     * @param int $scheme_id 方案ID
     * @return array
     */
    public static function getFields($scheme_id)
    {
        return self::db('workflow_scheme_field')->where(['scheme_id' => $scheme_id])->order("sort ASC")->select();
    }

    /**
     * 一键复制
     * @param $scheme_id
     */
    public static function copy($scheme_id)
    {

    }
}
