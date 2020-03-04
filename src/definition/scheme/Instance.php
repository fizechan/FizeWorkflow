<?php


namespace util\workflow\realization\scheme;

use think\Db;
use util\workflow\definition\Node;
use util\workflow\model\Operation;

/**
 * 方案实例化
 * @todo 待删除
 */
trait Instance
{
    /**
     * @var Node 实例LV1节点
     */
    private $instanceNode;

    /**
     * 方案实例化
     * @param string $name 实例名称
     * @param int $scheme_id 方案ID
     * @return int 实例ID
     */
    public function instance($name, $scheme_id)
    {
        $scheme = Db::name('workflow_scheme')->where('id', '=', $scheme_id)->find();
        $data_instance = [
            'scheme_type' => $scheme['type'],
            'scheme_id'   => $scheme['id'],
            'name'        => $name,
            'status'      => 0,
            'is_finish'   => 0
        ];

        $instance_id = Db::name('workflow_instance')->insertGetId($data_instance);
        return $instance_id;
    }

    /**
     * 指定提交时要检查的参数
     * 通过改写该方法可以自定义要检查的参数
     * @return array [参数名 => 参数描述]
     */
    protected function instanceContrastCheckParams()
    {
        return [];
    }

    /**
     * 根据键名取得值
     * @param array $array 数组，可以是多维
     * @param string $key 键名，多维以英文字符“.”分割
     * @return mixed 不存在返回null
     */
    protected function getArrayValueBykey($array, $key)
    {
        $keys = explode('.', $key);  //以“.”进行层级分割
        $result = $array;
        foreach ($keys as $key) {
            if (is_numeric($key)) {
                $key = (int)$key;
            }
            if (!isset($result[$key])) {
                return null;
            }
            $result = $result[$key];
        }
        return $result;
    }

    /**
     * 根据新提交的表单内容，取得表单改变的HTML代码段
     * 通过改写该方法可以自定义HTML代码段
     * @param array $new_form 新表单
     * @param array $old_form 旧表单
     * @return string HTML代码段
     */
    protected function instanceContrastContent(array $new_form, array $old_form)
    {
        $check_params = $this->instanceContrastCheckParams();
        if (empty($check_params)) {
            return '';
        }

        $content = '';
        foreach ($check_params as $key => $mark) {
            $new_val = $this->getArrayValueBykey($new_form, $key);
            $old_val = $this->getArrayValueBykey($old_form, $key);
            if ($new_val != $old_val) {
                $content .= "<tr><td><b>{$mark}</b></td><td>{$old_val}</td><td>{$new_val}</td></tr>";
            }
        }

        if ($content) {
            $content = <<<EOF
<table class="table table-bordered">
    <tr>
        <th width="20%">名称</th>
        <th width="40%">前次提交</th>
        <th width="40%">本次提交</th>
    </tr>
    {$content}
</table>
EOF;
        }
        return $content;
    }

    /**
     * 默认表单改变的HTML代码段
     * @param array $new_form 新字段表单数组
     * @param int $instance_id 实例ID
     * @return string
     */
    protected function instanceContrastContentInit(array $new_form, $instance_id)
    {
        return '';
    }

    /**
     * 产生提交修改记录
     * 通过改写该方法可以实现自定义contrast
     * @param int $instance_id 实例ID
     * @param array $form 表单数据
     * @param array $attachs 相关附件
     * @return int 记录ID
     */
    public function instanceContrast($instance_id, array $form, array $attachs = null)
    {
        $instance = Db::name('workflow_instance')->where('id', '=', $instance_id)->find();

        $last_contrast = Db::name('workflow_contrast')
            ->where('instance_id', '=', $instance_id)
            ->order('create_on', 'DESC')
            ->find();

        if (!$last_contrast) {  //首次提交
            $data = [
                'instance_id' => $instance_id,
                'scheme_id'   => $instance['scheme_id'],
                'scheme_type' => $instance['scheme_type'],
                'action_name' => '首次提交',
                'content'     => $this->instanceContrastContentInit($form, $instance_id),
                'form_json'   => json_encode($form),
                'create_by'   => isset($form['create_by']) ? $form['create_by'] : 0  //提交者
            ];
        } else {  //再次提交
            $old_form = json_decode($last_contrast['form_json'], true);
            $data = [
                'instance_id' => $instance_id,
                'scheme_id'   => $instance['scheme_id'],
                'scheme_type' => $instance['scheme_type'],
                'action_name' => '再次提交',
                'content'     => $this->instanceContrastContent($form, $old_form),
                'form_json'   => json_encode($form),
                'create_by'   => isset($form['create_by']) ? $form['create_by'] : 0  //提交者
            ];
        }
        $contrast_id = Db::name('workflow_contrast')->insertGetId($data);

        //插入相关附件记录
        if ($attachs) {
            $datas = [];
            foreach ($attachs as $attach) {
                if (!is_array($attach)) {
                    $attach = json_decode($attach, true);
                }

                $datas[] = [
                    'contrast_id'   => $contrast_id,
                    'type'          => isset($attach['type']) ? $attach['type'] : '',
                    'original_file' => isset($attach['original_file']) ? $attach['original_file'] : '',
                    'title'         => isset($attach['title']) ? $attach['title'] : '',
                    'url'           => isset($attach['url']) ? $attach['url'] : '',
                    'path'          => isset($attach['path']) ? $attach['path'] : '',
                    'extension'     => isset($attach['extension']) ? $attach['extension'] : '',
                    'sort'          => isset($attach['sort']) ? $attach['sort'] : 0,
                    'remarks'       => isset($attach['remarks']) ? $attach['remarks'] : '',
                    'is_delete'     => isset($attach['is_delete']) ? $attach['is_delete'] : 0,
                    'extend_json'   => isset($attach['extend_json']) ? $attach['extend_json'] : null
                ];
            }
            Db::name('workflow_contrast_attach')->insertAll($datas);
        }

        //产生operation
        if (!$last_contrast) {  //首次提交
            $data_operation = [
                'scheme_id'       => $instance['scheme_id'],
                'instance_id'     => $instance_id,
                'contrast_id'     => $contrast_id,
                'user_id'         => 0,  //0代表系统操作
                'user_extend_id'  => 0,  //0代表系统操作
                'node_id'         => 0,  //0代表非实际节点
                'node_name'       => '提交',
                'create_time'     => date('Y-m-d H:i:s'),
                'distribute_time' => date('Y-m-d H:i:s'),
                'action_id'       => 0,
                'action_name'     => '首次提交',
                'action_type'     => Operation::ACTION_TYPE_SUBMIT,
                'action_time'     => date('Y-m-d H:i:s'),
                'prev_json'       => Operation::getPrevJson($instance_id),
                'form_json'       => json_encode($form)
            ];
        } else {  //再次提交
            $data_operation = [
                'scheme_id'       => $instance['scheme_id'],
                'instance_id'     => $instance_id,
                'contrast_id'     => $contrast_id,
                'user_id'         => 0,  //0代表系统操作
                'user_extend_id'  => 0,  //0代表系统操作
                'node_id'         => 0,  //0代表非实际节点
                'node_name'       => '提交',
                'create_time'     => date('Y-m-d H:i:s'),
                'distribute_time' => date('Y-m-d H:i:s'),
                'action_id'       => 0,
                'action_name'     => '再次提交',
                'action_type'     => Operation::ACTION_TYPE_SUBMIT,
                'action_time'     => date('Y-m-d H:i:s'),
                'prev_json'       => Operation::getPrevJson($instance_id),
                'form_json'       => json_encode($form)
            ];
        }
        Db::name('workflow_operation')->insert($data_operation);

        return $contrast_id;
    }

    /**
     * 方案实例化取得实例化ID并关联后执行该方法
     * 该方法触发方案的LV1节点分配
     * @param int $instance_id 实例ID
     * @param int $contrast_id 提交ID
     */
    public final function instanceDone($instance_id, $contrast_id)
    {
        $instance = Db::name('workflow_instance')->where('id', '=', $instance_id)->find();
        $map = [
            ['scheme_id', '=', $instance['scheme_id']],
            ['level', '=', 1]
        ];
        $lv1nodes = Db::name('workflow_node')->where($map)->select();
        foreach ($lv1nodes as $lv1node) {
            $this->instanceNode = new $lv1node['class']();
            if ($this->instanceNode->access($instance_id, 0, $lv1node['id'])) {
                $this->instanceNode->createOperation($instance_id, $contrast_id, $lv1node['id']);
            }
            $this->instanceNode = null;
        }
    }

}
