<?php


namespace fize\workflow;


class Workflow
{

    /**
     * @var array 配置
     */
    protected static $config;

    public function __construct(array $config)
    {
        $db_type = $config['db']['type'];
        $db_config = $config['db']['config'];
        $db_mode = isset($config['db']['mode']) ? $config['db']['mode'] : null;
        new Db($db_type, $db_config, $db_mode);
    }

    public static function initialize()
    {

    }
}
