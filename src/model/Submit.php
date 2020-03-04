<?php


namespace fize\workflow\model;

use fize\crypt\Json;
use fize\workflow\Db;
use fize\workflow\SchemeInterface;

/**
 * 提交
 */
class Submit
{

    /**
     * 返回表单字段
     * @param int $submit_id 提交ID
     * @param bool $with_value 是否附带值
     * @param bool $key_name 是否将name作为键名
     * @return array
     */
    public static function getFields($submit_id, $with_value = true, $key_name = false)
    {
        $submit = Db::table('workflow_submit')->where(['id' => $submit_id])->find();
        $instance = Db::table('workflow_instance')->where(['id' => $submit['instance_id']])->find();
        $fields = Db::table('workflow_scheme_field')->where(['scheme_id' => $instance['scheme_id']])->order(['sort' => 'ASC', 'create_time' => 'ASC'])->select();
        foreach ($fields as $index => $field) {
            if ($field['attrs']) {
                $fields[$index]['attrs'] = Json::decode($field['attrs']);
            }
            if ($field['attrs']) {
                $fields[$index]['extend'] = Json::decode($field['extend']);
            }
        }

        if ($with_value) {
            $values = Db::table('workflow_submit_field')->where(['submit_id' => $submit_id])->select();
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

    /**
     * 返回差异字段
     * @param int $submit_id 提交ID
     * @param int $original_submit_id 对比提交ID
     * @return array [$name => ['title' => *, 'type' => *, 'new' => *, 'old' => *]]
     */
    protected static function getContrasts($submit_id, $original_submit_id = null)
    {
        $submit = Db::table('workflow_submit')->where(['id' => $submit_id])->find();
        $instance = Db::table('workflow_instance')->where(['id' => $submit['instance_id']])->find();
        $scheme = Db::table('workflow_scheme')->where(['id' => $instance['scheme_id']])->find();

        if (is_null($original_submit_id)) {
            $original_submit_id = Db::table('workflow_submit')
                ->where([
                    'instance_id' => $submit['instance_id'],
                    'create_time' => ['<', $submit['create_time']]
                ])
                ->order(['create_time' => 'DESC', 'id' => 'DESC'])
                ->value('id');
        }

        if (is_null($original_submit_id)) {
            return [];
        }

        $fields = self::getFields($submit_id, true, true);
        $original_fields = self::getFields($original_submit_id, true, true);

        /**
         * @var SchemeInterface $class
         */
        $class = $scheme['class'];
        return $class::getSubmitContrasts($fields, $original_fields);
    }
}
