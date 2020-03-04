<?php


namespace fize\workflow\model;

use RuntimeException;
use fize\crypt\Json;
use fize\workflow\Db;
use fize\workflow\Field;

/**
 * 方案-字段定义
 */
class SchemeField
{

    /**
     * 添加
     * @param int $scheme_id 方案ID
     * @param Field $field 字段
     * @return int 返回字段ID
     */
    public static function add($scheme_id, Field $field)
    {
        if (!in_array($field->type, array_keys(Field::getAvailableFieldTypes()))) {
            throw new RuntimeException("不可用的字段类型：{$field->type}！");
        }
        $data = [
            'scheme_id'   => $scheme_id,
            'title'       => $field->title,
            'name'        => $field->name,
            'type'        => $field->type,
            'is_required' => $field->isRequired,
            'regex_match' => $field->regexMatch,
            'preload'     => $field->preload,
            'value'       => $field->value,
            'hint'        => $field->hint,
            'attrs'       => is_array($field->attrs) ? Json::encode($field->attrs) : null,
            'extend'      => is_array($field->extend) ? Json::encode($field->extend) : null,
            'sort'        => $field->sort,
            'create_time' => date('Y-m-d H:i:s')
        ];
        return Db::table('workflow_scheme_field')->insertGetId($data);
    }
}
