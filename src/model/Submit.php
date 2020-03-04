<?php


namespace fize\workflow\model;


class Submit
{

    /**
     * 返回表单字段
     * @param int $instance_id 实例ID
     * @param bool $with_value 是否附带值
     * @param bool $key_name 是否将name作为键名
     * @return array
     */
    public static function getFields($instance_id, $with_value = true, $key_name = false)
    {
        $instance = self::db('workflow_instance')->where(['id' => $instance_id])->find();
        $fields = self::db('workflow_scheme_field')->where(['scheme_id' => $instance['scheme_id']])->order("sort ASC")->select();
        if ($with_value) {
            $values = self::db('workflow_instance_field')->where(['instance_id' => $instance_id])->select();
            foreach ($fields as $index => $field) {
                foreach ($values as $value) {
                    if ($value['name'] == $field['name']) {
                        $fields[$index]['value'] = $value['value'];
                        break;
                    }
                }
            }
        }

        if (!$key_name) {
            return $fields;
        }

        $items = [];
        foreach ($fields as $field) {
            $items[$field['name']] = $field;
        }
        return $items;
    }
}
