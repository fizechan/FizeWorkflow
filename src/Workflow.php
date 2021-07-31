<?php

namespace fize\workflow;

/**
 * 工作流
 *
 * 通过该静态类统一对外提供接口
 */
class Workflow
{

    /**
     * @var array 配置
     */
    protected static $config;

    public function __construct($config)
    {
        $db_type = $config['db']['type'];
        $db_config = $config['db']['config'];
        $db_mode = $config['db']['mode'] ?? null;
        new Db($db_type, $db_config, $db_mode);
    }

    public static function initialize()
    {

    }
}
