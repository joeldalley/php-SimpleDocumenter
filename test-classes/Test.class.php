<?php
/**
 * Test php doc comments in various states of well-formedness.
 */
class Test {
    const A = 1;
    const B = 2;

    var $_a = 1;

    /**
     * @example Here is an example.
     * @example
     *   Multi-line, indented.
     *   Line 2.
     */
    var $a = 1;

    /** @var */
    var $aa = 1;

    /**
     * @var
     */
    var $aaa = 1;

    /** @var int */
    public $b = 2;

    /** @var $c */
    public $c = 4;

    /** @var $d This is an int. */
    public $d = 8;

    /** @var int   This is an int. */
    public $e = 16;

    /** @var int $f This is an int. */
    public $f = 32;

    public function a() {}

    /** @return */
    public function aa() {}
 
    /** @return void */
    public function b() {}

    /**
     * @return void
     */
    public function c($a) {}

    /**
     * @param
     * @return void
     */
    public function d($a) {}

    /**
     * @param int
     * @return void
     */
    public function e($a) {}

    /**
     * @param $a
     * @return void
     */
    public function f($a) {}

    /**
     * @param int $a
     * @return void
     */
    public function g($a) {}

    /**
     * @param int $a A is an integer.
     * @return void Nothing to return here.
     */
    public function h($a) {}

    /**
     * With a comment.
     * @param int $a A is an integer.
     * @return void Nothing to return here.
     */
    public function i($a) {}

    /**
     * With a mult-line comment.
     * Here's line 2.
     * @param int $a A is an integer.
     * @return void Nothing to return here.
     */
    public function j($a) {}

    /**
     * With a mult-line comment.
     * Here's line 2.
     * @example Two line comment.
     *     Here is line 2.
     * @param int $a A is an integer.
     * @return void Nothing to return here.
     */
    public function jj($a) {}

    /**
     * With a mult-line comment.
     * Here's line 2.
     * @example A simple example.
     * @example
     *
     * A more complex example.
     *    - Indented.
     *        (a) multi-level indent.
     *            * This
     *            * That
     *    - Back to this level.
     *
     * @param int $a A is an integer.
     * @return void Nothing to return here.
     */
    public function k($a) {}
}