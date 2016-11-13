<?php

class Ralph {
	/**
	 * @var boolean
	 */
	private static $is_enabled = false;

	/**
	 * Store function time
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Enables Ralph profiler, by default it will not be enabled in the CMS or in developer tools
	 *
	 * @return null
	 */
	public static function enable($inCMS = false) {
		if (!static::$is_enabled) {
			if ($inCMS === false && (self::in_cms() || self::in_dev())) {
				return;
			}
			$ralph = singleton('Ralph');
			$ralph->init();
			static::$is_enabled = true;
		}
	}

	/**
	 * Initialize the Ralph profiler
	 *
	 * @return null
	 */
	public function init() {
        $config = Config::inst()->get('Injector', 'RequestProcessor');
        $config['properties']['filters'][] = '%$SilbinaryWolf\Ralph\RequestFilter';
        Config::inst()->update('Injector', 'RequestProcessor', $config);

		$cmp = new SilbinaryWolf\Ralph\MetaCompiler;
		$cmp->process();
	}

	/**
	 * This is a workaround for the injector affecting subclasses
	 * basically force any subclasses of DataList to use their original
	 * class leave the Injector behaviour in-tact.
	 *
	 * @return void
	 */
	public function useCustomClass($oldClass, $newClass) {
		$subclasses = ClassInfo::subclassesFor($oldClass);
		unset($subclasses[$oldClass]);
		$originalInjectorInfo = array();
		foreach ($subclasses as $class => $v) {
			$originalInjectorInfo[$class] = Config::inst()->get('Injector', $class);
		}

		$injector = Config::inst()->get('Injector', $oldClass);
		if (isset($injector['class']) && $injector['class'] !== $oldClass) {
			throw new Exception('The class "'.$oldClass.'" is already being overriden by "'.$newClass.'".');
		}
		$injector['class'] = $newClass;
		Config::inst()->update('Injector', $oldClass, $injector);

		foreach ($subclasses as $class => $v) {
			$originalInjector = $originalInjectorInfo[$class];
			if (!isset($originalInjector[$class]['class'])) {
				$originalInjector[$class]['class'] = $class;
			}
			Config::inst()->update('Injector', $class, $originalInjector[$class]);
		}
	}

	/**
	 * To be inserted into and called from a DataList::__construct function.
	 *
	 * @return null
	 */
	public function dataListConstructor(DataList $dataList) {
		$bt = debug_backtrace();
		$caller = $bt[2];
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
		$dataList->profilerCaller = $caller;
	}

	/**
	 * Detect if user is in the CMS or not.
	 *
	 * @return boolean
	 */
	public static function in_cms() {
		// NOTE(Jake): Might need to remove 'Director::absoluteBaseURL()' from beginning of $url for certain cases later
		$url = (isset($_GET['url']) && php_sapi_name() !== 'cli-server') ? $_GET['url'] : $_SERVER['REQUEST_URI'];
		return (strpos($url, '/admin') === 0);
	}


	/**
	 * Detect if user is in the developer build tools or not
	 *
	 * @return boolean
	 */
	public static function in_dev() {
		$url = (isset($_GET['url']) && php_sapi_name() !== 'cli-server') ? $_GET['url'] : $_SERVER['REQUEST_URI'];
		return (strpos($url, '/dev') === 0);
	}

	public function profilerStore($object, $functionName, $time) {
		if (!$object->profilerCaller || !isset($object->profilerCaller)) {
			throw new Exception('Should have caller information.');
		}
		$profilerCaller = $object->profilerCaller;
		$callerClass = isset($profilerCaller['class']) ? $profilerCaller['class'] : '?class?';
		$function = $profilerCaller['function'];
		$line = isset($profilerCaller['line']) ? $profilerCaller['line'] : '?line?';

		$bt = debug_backtrace();
		$caller = $bt[2];
		foreach ($bt as $i => $stackItem) {
			if (!isset($stackItem['class']) || 
				(!in_array($stackItem['class'], array('IteratorIterator', 'Object', 'SS_ListDecorator', 'DataObject', 'DataList', 'Versioned')))) {
				$caller = $stackItem;
				break;
			}
		}
		$caller['line'] = isset($bt[2]['line']) ? $bt[2]['line'] : '?';

		$track = new SilbinaryWolf\Ralph\FunctionCall;
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

		$this->data[$functionName][$callerClass][$function][$line][] = $track;
	}

	/**
	 * 
	 */ 
	public function forTemplate() {
		$list = array();
		$listSortOrder = array();

		foreach ($this->data as $dataListFunctionName => $dataListFunctionData) {
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
						$str = 'DataList::'.$dataListFunctionName.'->'.$class.'::'.$function.'('.$line.'): '.$totalTime.'ms (Count: '.$callCount.')';
						/*foreach ($data as $i => $track) {
							$str .= "<br/>".'-- '.$track->class.'::'.$track->function.'('.$track->line.')';
						}*/
						$list[] = array(
							'str' => $str,
							'time' => $totalTime
						);
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

		$result = '';
		foreach ($list as $i => $str) {
			$result .= $i.'.) '.$str['str'].'<br/>';
		}
		echo '<pre>'.$result.'</pre>';
	}
}