<?php

namespace Symbiote\Ralph;

require_once(dirname(__FILE__).'/../ss4_compat.php');

class FunctionCallRecord extends \ViewableData {
	/**
	 * @var string
	 */
	public $Class = '?class?';

	/**
	 * @var string
	 */
	public $Function = '?fn?';

	/**
	 * @var int
	 */
	public $Time = 0;

	/**
	 * @var int
	 */
	public $Line = -1;

	/** 
	 * Either '::' or '->'
	 *
	 * @var
	 */
	//public $Type = '?';
}