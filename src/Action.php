<?php

namespace Fize\Workflow;

/**
 * 操作
 */
class Action
{
    /**
     * 操作：未操作
     */
    const TYPE_UNEXECUTED = 0;

    /**
     * 操作：通过
     */
    const TYPE_ADOPT = 1;

    /**
     * 操作：否决
     */
    const TYPE_REJECT = 2;

    /**
     * 操作：退回
     */
    const TYPE_GOBACK = 3;

    /**
     * 操作：挂起
     */
    const TYPE_HANGUP = 4;

    /**
     * 操作：无需操作
     */
    const TYPE_DISUSE = 5;

    /**
     * 操作：调度
     */
    const TYPE_DISPATCH = 6;

    /**
     * 操作：提交
     */
    const TYPE_SUBMIT = 7;

    /**
     * 操作：取消
     */
    const TYPE_CANCEL = 8;
}
