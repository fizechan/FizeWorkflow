<?php

namespace Fize\Workflow\Define;

use Fize\Crypt\Json;
use Fize\Workflow\Db;
use Fize\Workflow\Field;
use RuntimeException;

/**
 * 方案-字段定义
 */
class SchemeField
{

    /**
     * 添加
     * @param int   $scheme_id 方案ID
     * @param array $field     字段属性
     * @return int 返回字段ID
     */
    public static function add(int $scheme_id, array $field): int
    {
        if (!in_array($field['type'], array_keys(Field::getAvailableFieldTypes()))) {
            throw new RuntimeException("不可用的字段类型：{$field['type']}！");
        }
        $data = [
            'scheme_id'   => $scheme_id,
            'title'       => $field['title'],
            'name'        => $field['name'],
            'type'        => $field['type'],
            'is_required' => $field['is_required'],
            'regex_match' => $field['regex_match'],
            'preload'     => $field['preload'],
            'value'       => $field['value'],
            'hint'        => $field['hint'],
            'attrs'       => is_array($field['attrs']) ? Json::encode($field['attrs']) : null,
            'extend'      => is_array($field['extend']) ? Json::encode($field['extend']) : null,
            'sort'        => $field['sort'],
            'create_time' => date('Y-m-d H:i:s')
        ];
        return Db::table('workflow_scheme_field')->insertGetId($data);
    }
}
