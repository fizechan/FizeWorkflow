<?php

namespace Fize\Workflow;

use Fize\Database\Core\Db as Driver;
use Fize\Database\Db as FizeDb;

/**
 * 数据库
 * @internal 内部使用
 */
class Db
{

    /**
     * @var Driver
     */
    protected static $db;

    /**
     * 初始化
     * @param string      $type   数据库类型
     * @param array       $config 数据库配置项
     * @param string|null $mode   连接模式
     */
    public function __construct(string $type, array $config, string $mode = null)
    {
        self::$db = FizeDb::connect($type, $config, $mode);
    }

    /**
     * 执行一个SQL语句并返回相应结果
     * @param string        $sql      SQL语句，支持原生的pdo问号预处理
     * @param array         $params   可选的绑定参数
     * @param callable|null $callback 如果定义该记录集回调函数则不返回数组而直接进行循环回调
     * @return array 返回数组
     */
    public static function query(string $sql, array $params = [], callable $callback = null): array
    {
        return self::$db->query($sql, $params, $callback);
    }

    /**
     * 执行一个SQL语句
     * @param string $sql    SQL语句，支持问号预处理语句
     * @param array  $params 可选的绑定参数
     * @return int 返回受影响行数
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::$db->execute($sql, $params);
    }

    /**
     * 开始事务
     */
    public static function startTrans()
    {
        self::$db->startTrans();
    }

    /**
     * 执行事务
     */
    public static function commit()
    {
        self::$db->commit();
    }

    /**
     * 回滚事务
     */
    public static function rollback()
    {
        self::$db->rollback();
    }

    /**
     * 指定当前要操作的表,支持链式调用
     * @param string      $name   表名
     * @param string|null $prefix 表前缀，默认为null表示使用当前前缀
     * @return Driver
     */
    public static function table(string $name, string $prefix = null): Driver
    {
        return self::$db->table($name, $prefix);
    }

    /**
     * 获取最后运行的SQL
     *
     * 仅供日志使用的SQL语句，由于本身存在SQL危险请不要真正用于执行
     * @param bool $real 是否返回最终SQL语句而非预处理语句
     * @return string
     */
    public static function getLastSql(bool $real = false): string
    {
        return self::$db->getLastSql($real);
    }
}
