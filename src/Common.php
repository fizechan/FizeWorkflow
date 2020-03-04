<?php


namespace fize\workflow;

use fize\db\Db;
use fize\db\definition\Db as DbDefinition;


class Common
{

    private static $db;

    /**
     * @var array 配置
     */
    protected static $config;

    public function __construct(array $config)
    {
        self::$config = $config;
    }

    /**
     * @param string $name 表名
     * @param string $prefix 表前缀
     * @return DbDefinition
     */
    protected static function db($name = null, $prefix = null)
    {
        if (!self::$db) {
            $type = self::$config['db']['type'];
            $config = self::$config['db']['config'];
            $mode = isset(self::$config['db']['mode']) ? self::$config['db']['mode'] : null;
            self::$db = Db::connect($type, $config, $mode);
        }
        if (is_null($name)) {
            return self::$db;
        }
        return self::$db->table($name, $prefix);
    }
}
