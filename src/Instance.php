<?php


namespace fize\workflow;

use RuntimeException;
use fize\misc\Preg;
use fize\crypt\Json;

/**
 * 实例
 */
class Instance extends Common
{

    /**
     * 状态：执行中
     */
    const STATUS_EXECUTING = 0;

    /**
     * 状态：已通过
     */
    const STATUS_ADOPT = 1;

    /**
     * 状态：已否决
     */
    const STATUS_REJECT = 2;

    /**
     * 状态：已退回
     */
    const STATUS_GOBACK = 3;

    /**
     * 状态：已挂起
     */
    const STATUS_HANGUP = 4;

    /**
     * 状态：中断中
     */
    const STATUS_INTERRUPT = 5;

    /**
     * 状态：已取消
     */
    const STATUS_CANCEL = 8;

    /**
     * 创建
     * @param string $name 名称
     * @param int $scheme_id 方案ID
     * @return int 实例ID
     */
    public static function create($name, $scheme_id, array $fields, $original_instance_id = null)
    {
        self::db()->startTrans();

        $data_instance = [
            'scheme_id' => $scheme_id,
            'name'      => $name,
            'status'    => Instance::STATUS_EXECUTING,
            'is_finish' => 0
        ];

        $instance_id = self::db('workflow_instance')->insertGetId($data_instance);

        foreach ($fields as $n => $v) {
            $field = self::db('workflow_scheme_field')->where(['scheme_id' => $scheme_id, 'name' => $n])->find();
            if ($field['is_required'] && $v === "") {
                self::db()->rollback();
                throw new RuntimeException("字段{$n}必须填写");
            }
            if ($field['regex_match']) {
                if (!Preg::match($field['regex_match'], $v)) {
                    self::db()->rollback();
                    throw new RuntimeException("字段{$n}不符合规则");
                }
            }

            $data_instance_field = [
                'instance_id' => $instance_id,
                'name'        => $n,
                'value'       => $v
            ];
            self::db('workflow_instance_field')->insert($data_instance_field);
        }

        if ($original_instance_id) {
            $contrasts = self::getContrasts($instance_id, $original_instance_id);
            self::db('workflow_instance')->where(['id' => $instance_id])->update(['contrasts' => Json::encode($contrasts)]);
        }

        self::db()->commit();

        return $instance_id;
    }

    /**
     * 返回差异字段
     * @param int $instance_id 实例ID
     * @param int $original_instance_id 对比实例ID
     * @return array [$name => ['title' => *, 'type' => *, 'new' => *, 'old' => *]]
     */
    protected static function getContrasts($instance_id, $original_instance_id)
    {
        $fields = self::getFields($instance_id, true, true);
        $original_fields = self::getFields($original_instance_id, true, true);
        $contrasts = [];
        foreach ($fields as $name => $field) {
            if ($field['value'] != $original_fields[$name]['value']) {
                $contrasts[$name] = [
                    'title' => $field['title'],
                    'type'  => $field['type'],
                    'new'   => $field['value'],
                    'old'   => $original_fields[$name]['value']
                ];
            }
        }
        return $contrasts;
    }

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
