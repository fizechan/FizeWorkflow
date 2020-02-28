<?php

namespace util\workflow\definition;

use util\workflow\realization\scheme\Instance;
use util\workflow\realization\scheme\Distribute;
use util\workflow\realization\scheme\Action;
use util\workflow\realization\scheme\Dispatch;

/**
 * 工作流方案抽象类
 */
class Scheme
{
    use Instance;
    use Distribute;
    use Action;
    use Dispatch;

    /**
     * @var string 最后错误信息
     */
    protected $errMsg = '';

    /**
     * 获取最后的错误信息
     * @return string
     */
    public function getLastErrMsg()
    {
        return $this->errMsg;
    }
}