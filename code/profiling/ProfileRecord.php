<?php

namespace SilbinaryWolf\Ralph;

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