<?php 

// todo(Jake): Rename Profiler to Ralph
namespace SilbinaryWolf\Ralph;

class DataList extends \DataList {
	/**
	 * {@inheritdoc}
	 */
	public function __construct($dataClass) {
		parent::__construct($dataClass);
		singleton('Ralph')->dataListConstructor($this);
	}

	/**
     * {@inheritDoc}
     */
	public function toArray() {
		$microtime = microtime(true);
		$result = parent::toArray();
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}

	/**
     * {@inheritDoc}
     */
	public function first() {
		$microtime = microtime(true);
		$result = parent::first();
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}

	/**
     * {@inheritDoc}
     */
	public function limit($limit, $offset = 0) {
		$microtime = microtime(true);
		$result = parent::limit($limit, $offset);
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}

	/**
     * {@inheritDoc}
     */
	public function toNestedArray() {
		$microtime = microtime(true);
		$result = parent::toNestedArray();
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}

	/**
     * {@inheritDoc}
     */
	public function count() {
		$microtime = microtime(true);
		$result = parent::count();
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}

	/**
     * {@inheritDoc}
     */
	public function max($fieldName) {
		$microtime = microtime(true);
		$result = parent::max($fieldName);
		$microtime = microtime(true) - $microtime;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $microtime);
		return $result;
	}
}