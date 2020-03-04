<?php


namespace fize\workflow\common;

/**
 * 表单字段定义
 */
class Field
{

    /**
     * @var string 显示名
     */
    public $title;

    /**
     * @var string 字段名
     */
    public $name;

    /**
     * @var string 类型
     */
    public $type;

    /**
     * @var int 是否必填
     */
    public $isRequired;

    /**
     * 全部可用字段类型
     * @return string[]
     */
    public static function getAvailableFieldTypes()
    {
        return [
            // button 标签
            'button-button' => '按钮',
            'button-reset' => '重置按钮',
            'button-submit' => '提交按钮',

            // input 标签
            'input-button' => '按钮',
            'input-checkbox' => '复选框',
            'input-color' => '拾色器',
            'input-date' => '日期',
            'input-datetime' => '时间日期(UTC)',
            'input-datetime-local' => '时间日期',
            'input-email' => 'Email',
            'input-file' => '文件',
            'input-hidden' => '隐藏域',
            'input-image' => '图像按钮',
            'input-month' => '年-月',
            'input-number' => '数字',
            'input-password' => '密码',
            'input-radio' => '单选框',
            'input-range' => '范围',
            'input-reset' => '重置按钮',
            'input-search' => '搜索',
            'input-submit' => '提交按钮',
            'input-tel' => '电话',
            'input-text' => '文本',
            'input-time' => '时间',
            'input-url' => '链接',
            'input-week' => '年-周',

            // select 标签
            'select' => '下拉框',

            // textarea 标签
            'textarea' => '多行文本',

            // 自定义
            'editor' => '编辑器',
            'file' => '文件上传',
            'files' => '多文件上传',
            'image' => '图片上传',
            'images' => '多图片上传',
            'json' => 'JSON格式'
        ];
    }

    public function testjson()
    {
        $json = [

        ];
    }
}
