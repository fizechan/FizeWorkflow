<?php


namespace util\workflow;

use Exception;
use think\facade\Request;
use think\facade\Log;
use think\Exception as ThinkException;

/**
 * 工作流异常处理器
 */
class ExceptionHandle
{

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     * @access protected
     * @param Exception $exception
     * @return array                 异常类定义的扩展数据
     */
    protected static function getExtendData(Exception $exception)
    {
        $data = [];

        if ($exception instanceof ThinkException) {
            $data = $exception->getData();
        }

        return $data;
    }

    /**
     * 错误记录
     * @param Exception $exception
     */
    public static function report(Exception $exception)
    {
        $errmsg = $exception->getMessage() . "\n";

        $extend_data = self::getExtendData($exception);
        if (isset($extend_data['Database Status']['Error SQL'])) {
            $errmsg .= '[ sql ] ' . $extend_data['Database Status']['Error SQL'] . "\n";
        }

        $errmsg .= '[ file ] ' . $exception->getFile() . " ";
        $errmsg .= '[ line ] ' . $exception->getLine() . " ";
        $errmsg .= '[ code ] ' . $exception->getCode() . "\n";
        $errmsg .= '[ IP ] ' . Request::ip() . "\n";
        $errmsg .= '[ user-agent ] ' . Request::header('user-agent') . "\n";
        $errmsg .= "----------trace----------\n";
        foreach ($exception->getTrace() as $trace) {
            $file = isset($trace['file']) ? $trace['file'] : '';
            $line = isset($trace['line']) ? $trace['line'] : '';
            $function = (isset($trace['class']) ? $trace['class'] : '') . (isset($trace['type']) ? $trace['type'] : '') . (isset($trace['function']) ? $trace['function'] : '');
            $errmsg .= '[ file ] ' . $file . " ";
            $errmsg .= '[ line ] ' . $line . " ";
            $errmsg .= '[ function ] ' . $function . "\n";
        }
        Log::write($errmsg, 'error');
        Log::save();
    }
}
