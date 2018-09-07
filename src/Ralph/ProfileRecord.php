<?php

namespace Symbiote\Ralph;

require_once(dirname(__FILE__).'/../ss4_compat.php');

class ProfileRecord extends \ViewableData {
	/**
	 * @var FunctionCallRecord
	 */
	public $Constructor = null;

	/**
	 * @var FunctionCallRecord
	 */
	public $Caller = null;
}