<?php 

namespace SilbinaryWolf\Profiler;

class FunctionCall {
	public $time = 0;

	public $class = '?class?';

	public $function = '?fn?';

	public $line = 0;
}

class DataList extends \DataList {
	public static $profiler_initialized = false;

	public static $profiler_enabled = true;

	public static $profiler_data = array(
	);

	protected $profilerCaller = null;

	public static function profiler_shutdown() {
		$list = array();
		$listSortOrder = array();

		$output = '<pre>';
		foreach (static::$profiler_data as $dataListFunctionName => $dataListFunctionData) {
			foreach ($dataListFunctionData as $class => $classData) {
				foreach ($classData as $function => $functionData) {
					foreach ($functionData as $line => $data) {
						$totalTime = 0;
						$callCount = 0;
						/** @var FunctionCall $track */
						foreach ($data as $i => $track) {
							$totalTime += $track->time;
							++$callCount;
						}
						$totalTime *= 1000;
						$list[] = array(
							'str' => 'DataList::'.$dataListFunctionName.'->'.$class.'::'.$function.'('.$line.'): '.$totalTime.'ms (Count: '.$callCount.')',
							'time' => $totalTime
						);
						/*foreach ($data as $i => $track) {
							echo '-- '.$track->class.'::'.$track->function.'<br/>';
						}*/
					}
				}
			}
		}

		// Sort by time
		$time = array();
		foreach ($list as $key => $val) {
		    $time[$key] = $val['time'];
		}
		array_multisort($time, SORT_DESC, $list);
		unset($time);

		foreach ($list as $i => $str) {
			$output .= $i.'.) '.$str['str'].'<br/>';
		}
		$output .= '</pre>';
		echo $output;
	}

	/**
	 * {@inheritdoc}
	 */
	public function __construct($dataClass) {
		if (static::$profiler_enabled) {
			$bt = debug_backtrace();
			$caller = $bt[1];
			//foreach ($bt as $i => $stackItem) { unset($bt[$i]['object']); }
			foreach ($bt as $i => $stackItem) {
				if ($stackItem['class'] === 'Object' && $stackItem['function'] === 'create') {
					$i++; 
					$caller = $bt[$i];
					while (isset($caller['class']) && in_array($caller['class'], array('File', 'Object', 'DataModel', 'Hierarchy', 'DataObject', 'Versioned'))) {
						$i++;
						$caller = $bt[$i];

						// Backtrace can't figure out the class in this instance, so assume.
						if (!isset($caller['class'])) {
							if (basename($caller['file']) === 'Object.php') {
								$caller['class'] = 'Object';
							}
						}
					}
					/*if ($caller['function'] === 'call_user_func_array') {
						echo '<pre>'.print_r($bt, true).'</pre>'; exit;
					}*/

					$caller['line'] = isset($bt[$i-1]['line']) ? $bt[$i-1]['line'] : '?';
					/*if (!isset($caller['class'])) {
						Debug::dump($caller); exit;
						if (basename($caller['file']) === 'Object.php' && $caller['function'] === 'call_user_func_array') {
							// If calling extension
							$caller = $bt[$i+1];
						}
						$caller = $bt[$i+1]; exit;
						$caller['class'] = basename($caller['file']);
					}*/
					break;
				}
			} 
			$this->profilerCaller = $caller;

			if (!static::$profiler_initialized) {
				register_shutdown_function(array(__CLASS__, 'profiler_shutdown'));
				static::$profiler_initialized = true;
			}
		}
		parent::__construct($dataClass);
	}

	public function profilerStore($dataListFunctionName, $time) {
		if (!$this->profilerCaller) {
			throw new Exception('Should have caller information.');
		}
		$class = isset($this->profilerCaller['class']) ? $this->profilerCaller['class'] : '?class?';
		$function = $this->profilerCaller['function'];
		$line = isset($this->profilerCaller['line']) ? $this->profilerCaller['line'] : '?line?';

		$bt = debug_backtrace();
		$caller = $bt[2];
		foreach ($bt as $i => $stackItem) {
			if (!isset($stackItem['class']) || 
				(!in_array($stackItem['class'], array(__CLASS__, 'Object', 'SS_ListDecorator', 'DataObject', 'DataList', 'Versioned')))) {
				$caller = $stackItem;
				break;
			}
		}
		$caller['line'] = isset($bt[2]['line']) ? $bt[2]['line'] : '?';

		$track = new FunctionCall;
		$track->time = $time;
		if (isset($caller['class'])) {
			$track->class = $caller['class'];
		}
		if (isset($caller['function'])) {
			$track->function = $caller['function'];
		}
		if (isset($caller['line'])) {
			$track->line = $caller['line'];
		}

		static::$profiler_data[$dataListFunctionName][$class][$function][$line][] = $track;
	}

	/**
	 *	
	 */
	public function toArray() {
		if (!static::$profiler_enabled) {
			return parent::toArray();
		}
		$microtime = microtime(true);
		$result = parent::toArray();
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}

	public function first() {
		if (!static::$profiler_enabled) {
			return parent::first();
		}
		$microtime = microtime(true);
		$result = parent::first();
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}

	public function limit($limit, $offset = 0) {
		if (!static::$profiler_enabled) {
			return parent::limit($limit, $offset);
		}
		$microtime = microtime(true);
		$result = parent::limit($limit, $offset);
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}

	public function toNestedArray() {
		if (!static::$profiler_enabled) {
			return parent::toNestedArray();
		}
		$microtime = microtime(true);
		$result = parent::toNestedArray();
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}

	public function count() {
		if (!static::$profiler_enabled) {
			return parent::count();
		}
		$microtime = microtime(true);
		$result = parent::count();
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}

	public function max($fieldName) {
		if (!static::$profiler_enabled) {
			return parent::max($fieldName);
		}
		$microtime = microtime(true);
		$result = parent::max($fieldName);
		$this->profilerStore(__FUNCTION__, microtime(true) - $microtime);
		return $result;
	}
}