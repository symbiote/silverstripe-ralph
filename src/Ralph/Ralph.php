<?php

namespace SilbinaryWolf\Ralph;

use SilbinaryWolf\Ralph\FunctionCallRecord;
use SilbinaryWolf\Ralph\ProfileRecord;
use SilbinaryWolf\Ralph\ClassName;
use Config;
use ClassInfo;
use SSViewer;
use ArrayList;
use ArrayData;

class Ralph {
	const MODULE_DIR = 'ralph';

	/**
	 * @var boolean
	 */
	private static $is_enabled = false;

	/**
	 * @var array
	 */
	private static $default_settings = array(
		'enable_cms' => false,
		'dump_file' => false,
		'classes' => array(),
		'default_classes' => array(
			'DataList' => array(
				'__construct' => array(null, 'postConstructCall'), 
				'toArray', 'count', 'max', 'min', 'avg', 'sum', 'toNestedArray', 'limit',
			),
		),
	);

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * @var string
	 */
	protected $class = '';

	/**
	 * Store function time
	 *
	 * @var array
	 */
	protected $data = array();

	public function __construct() {
		$this->class = get_class();
	}

	/**
	 * Enables Ralph profiler, by default it will not be enabled in the CMS or in developer tools
	 *
	 * @return null
	 */
	public static function enable($settings = array()) {
		if (!static::$is_enabled) {
			$settings = array_merge(static::$default_settings, $settings);
			$inCMS = $settings['enable_cms'];
			if ($inCMS === false && (static::in_cms() || static::in_dev())) {
				return;
			}
			$ralph = singleton('SilbinaryWolf\\Ralph\\Ralph');
			$ralph->settings = $settings;
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
        $config = Config::inst()->get('Injector', ClassName::RequestProcessor);
        $config['properties']['filters'][] = '%$SilbinaryWolf\Ralph\RequestFilter';
        Config::inst()->update('Injector', ClassName::RequestProcessor, $config);

		$cmp = new MetaCompiler;
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
	 * Whether to dump to /ralph/src_generated/* folder or not.
	 *
	 * This exists to test and ensure instrumentation of functions is working, however
	 * this comes at a disk IO penalty.
	 *
	 * @var boolean
	 */
	public function getDumpToFile() {
		return (bool)$this->settings['dump_file'];
	}

	/** 
	 * Get classes to instrument with profiling time code.
	 *
	 * @return null
	 */
	public function getClasses() {
		$classes = array_merge($this->settings['default_classes'], $this->settings['classes']);
		return $classes;
	}

	/**
	 * To be inserted into and called from a DataList::__construct function.
	 *
	 * @return null
	 */
	public function constructorStore($object) {
		// Get backtrace (ie. remove 'object' and 'args' as it makes make echoing/dumping it easier to read)
		$bt = debug_backtrace();
		foreach ($bt as &$btVal) {
			unset($btVal['args']);
			unset($btVal['object']);
			unset($btVal);
		}

		$caller = $bt[2];
		foreach ($bt as $i => $stackItem) {
			if ($stackItem['class'] === 'Object' && $stackItem['function'] === 'create') {
				$i++; 
				$caller = $bt[$i];
				while (isset($caller['class']) && in_array($caller['class'], array('File', 'Object', 'DataModel', 'Hierarchy', 'DataObject', 'Versioned'))) {
					$i++;
					$caller = $bt[$i];

					if (!isset($caller['class'])) {
						$nextCaller = isset($bt[$i+1]) ? $bt[$i+1] : null;
						// Handle case where 'call_user_func_array' is called in Object
						if ($nextCaller && $nextCaller['function'] === '__call' && $nextCaller['class'] === 'Object') {
							$i++;
							$caller = $bt[$i];
						}
					}
				}
				$caller['line'] = isset($bt[$i-1]['line']) ? $bt[$i-1]['line'] : '?';
				break;
			}
		}

		$functionCallRecord = new FunctionCallRecord;
		$functionCallRecord->Class = isset($caller['class']) ? $caller['class'] : '';
		$functionCallRecord->Function = isset($caller['function']) ? $caller['function'] : '';
		$functionCallRecord->Line = isset($caller['line']) ? $caller['line'] : -1;
		$object->__constructorFunctionCall = $functionCallRecord;
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
		// Get backtrace (ie. remove 'object' and 'args' as it makes make echoing/dumping it easier to read)
		$bt = debug_backtrace();
		foreach ($bt as &$btVal) {
			unset($btVal['args']);
			unset($btVal['object']);
			unset($btVal);
		}
		unset($bt[0]); // ignore self
		unset($bt[1]); // ignore 1st level replacement function

		$caller = array();
		$callerIndex = -1;
		foreach ($bt as $i => $stackItem) {
			$callerIndex = $i;
			if (!isset($caller['class']) && 
				(
					// Added to skip SSViewer::includeGeneratedTemplate()
					$stackItem['function'] === 'include' || 
					// Added to skip Object calling call_user_func_array
					$stackItem['function'] === 'call_user_func_array' ||
					// Added to skip PaginatedList::getTotalItems(), calls 'count()' on DataList
					$stackItem['function'] === 'count')
				) {
				continue;
			}
			if (($stackItem['function'] === 'execute_template' && $stackItem['class'] === 'SSViewer') ||
				($stackItem['function'] === 'next' && $stackItem['class'] === 'SSViewer_Scope')) {
				$caller = $stackItem;
				$caller['class'] = '';
				$caller['function'] = basename($stackItem['file']);
				break;
			}
			if (!isset($stackItem['class']) || 
				(!in_array($stackItem['class'], array($this->class, 'PaginatedList', 'ViewableData', 'SSViewer', 'SSViewer_Scope', 'SSViewer_DataPresenter', 'Hierarchy', 'IteratorIterator', 'Object', 'SS_ListDecorator', 'DataObject', 'DataList', 'Versioned')))) {
				$caller = $stackItem;
				$caller['line'] = isset($bt[$callerIndex-1]['line']) ? $bt[$callerIndex-1]['line'] : -1;
				break;
			}
		}
		if (!$caller) {
			throw new Exception('Class ignore rules are too strict. Unable to determine a caller.');
		}
		/*if ($caller['function'] === 'count') {
			Debug::dump($caller); exit;
		}*/
		/*if (isset($caller['class']) && $caller['class'] === 'SSViewer_Scope') {
			Debug::dump($bt); exit;
		}*/
		
		$functionCallRecord = new FunctionCallRecord;
		$functionCallRecord->Class = isset($caller['class']) ? $caller['class'] : '';
		$functionCallRecord->Function = isset($caller['function']) ? $caller['function'] : '';
		$functionCallRecord->Time = $time * 1000; // Convert seconds to milliseconds
		$functionCallRecord->Line = isset($caller['line']) ? $caller['line'] : -1;
		$profileRecord = new ProfileRecord;
		if (isset($object->__constructorFunctionCall)) {
			$profileRecord->Constructor = $object->__constructorFunctionCall;
		}
		$profileRecord->Caller = $functionCallRecord;

		// todo(Jake): Allow sorting by constructor or caller
		//$functionCallRecord = $profileRecord->Constructor;
		$this->data[$functionCallRecord->Class][$functionCallRecord->Function][0][] = $profileRecord;
	}

	/**
	 * @return array
	 */ 
	public function getData() {
		return $this->data;
	}

	/**
	 * @return null
	 */ 
	public function forTemplate() {
		$list = array();
		$listSortOrder = array();

		foreach ($this->data as $class => $functionData) {
			foreach ($functionData as $function => $lineData) {
				foreach ($lineData as $line => $profileRecordSet) {
					$totalTime = 0;
					$callCount = 0;
					foreach ($profileRecordSet as $i => $profileRecord) {
						$totalTime += $profileRecord->Caller->Time;
						++$callCount;
					}
					$str = $class.'::'.$function.'('.$line.'): '.$totalTime.'ms (Count: '.$callCount.')';
					//foreach ($data as $i => $track) {
					//	$str .= "<br/>".'-- '.$track->class.'::'.$track->function.'('.$track->line.')';
					//}
					$arrayListClass = ClassName::ArrayList;
					$list[] = array(
						'Class' => $class,
						'Function' => $function,
						'Line' => $line,
						'Records' => new $arrayListClass($profileRecordSet),
						'Time' => $totalTime
					);
				}
			}
		}

		// Sort by time
		$time = array();
		foreach ($list as $key => $val) {
		    $time[$key] = $val['Time'];
		}
		array_multisort($time, SORT_DESC, $list);
		unset($time);

		$baseClassName = substr(__CLASS__, strrpos(__CLASS__, '\\') + 1);

		$template = new SSViewer(array($baseClassName));
		$html = $template->process(new ArrayData(array('Results' => new ArrayList($list))), null);
		return $html;
	}
}