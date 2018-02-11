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
use DataList;

require_once(dirname(__FILE__).'/../ss4_compat.php');

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

	/**
	 * @var boolean
	 */
	private $isSilverStripe4 = false;

	public function __construct() {
		$this->class = get_class();
		$this->isSilverStripe4 = class_exists('SilverStripe\Core\CoreKernel');
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
		$config = null;

		$requestProcessorClass = $this->isSilverStripe4 ? 'SilverStripe\Control\RequestProcessor' : 'RequestProcessor';
		$injectorClass = $this->isSilverStripe4 ? 'SilverStripe\Core\Injector\Injector' : 'Injector';

        $config = Config::inst()->get($injectorClass, $requestProcessorClass);
        $config['properties']['filters'][] = '%$SilbinaryWolf\Ralph\RequestFilter';
        if ($this->isSilverStripe4) {
        	Config::modify()->set($injectorClass, $requestProcessorClass, $config);
        } else {
    	    Config::inst()->update($injectorClass, $requestProcessorClass, $config);
    	}

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
		$injectorClass = $this->isSilverStripe4 ? \SilverStripe\Core\Injector\Injector::class : 'Injector';

		$subclasses = ClassInfo::subclassesFor($oldClass);
		unset($subclasses[$oldClass]);
		$originalInjectorInfo = array();
		foreach ($subclasses as $class => $v) {
			$originalInjectorInfo[$class] = Config::inst()->get($injectorClass, $class);
		}

		$injector = Config::inst()->get($injectorClass, $oldClass);
		$injector['class'] = $newClass;
		if ($this->isSilverStripe4) {
			Config::modify()->set($injectorClass, $oldClass, $injector);
		} else {
			Config::inst()->update($injectorClass, $oldClass, $injector);
		}

		foreach ($subclasses as $class => $v) {
			$originalInjector = $originalInjectorInfo[$class];
			if (!isset($originalInjector[$class]['class'])) {
				$originalInjector[$class]['class'] = $class;
			}
			if ($this->isSilverStripe4) {
				Config::modify()->set($injectorClass, $class, $originalInjector[$class]);
			} else {
				Config::inst()->update($injectorClass, $class, $originalInjector[$class]);
			}
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

		$goUpStackIfClass = array(
			// SS3 (and SS4 via the compatibility layer)
			'File', 
			'DataModel', 
			'Hierarchy', 
			'DataObject', 
			'Versioned',
			// SS3-only
			'Object',
			// SS4-only,
			'SilverStripe\ORM\DataList',
			'SilverStripe\Core\Injector\InjectionCreator',
			'SilverStripe\Core\Injector\Injector',
			'SilverStripe\View\ViewableData',
			'SilverStripe\ORM\DataObject',
		);

		$caller = $bt[2];
		foreach ($bt as $i => $stackItem) {
			if (
				// Silverstripe 3
				$stackItem['class'] === 'Object' && $stackItem['function'] === 'create' ||
				// Silverstripe 4
				$stackItem['class'] === 'ReflectionClass' && $stackItem['function'] === 'newInstanceArgs'
			) {
				$i++; 
				$caller = $bt[$i];
				while (isset($caller['class']) && in_array($caller['class'], $goUpStackIfClass)) {
					$i++;
					$caller = $bt[$i];

					// SS3-only: Handle case where 'call_user_func_array' is called in Object
					if (!isset($caller['class'])) {
						$nextCaller = isset($bt[$i+1]) ? $bt[$i+1] : null;
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
			unset($btVal['object']);
			unset($btVal);
		}
		unset($bt[0]); // ignore self
		unset($bt[1]); // ignore 1st level replacement/wrapper function

		$goUpStackIfClass = array(
			$this->class,
			// SS3-only
			'DataList', 
			'Hierarchy', 
			'ViewableData', 
			'SSViewer',
			'SSViewer_Scope', 
			'SSViewer_DataPresenter', 

			'PaginatedList', 
			'IteratorIterator', 
			'Object', 
			'SS_ListDecorator', 
			'DataObject', 
			
			'Versioned',
			//
			// SS4-only
			//
			'SilverStripe\View\ViewableData',
			//'SilverStripe\View\SSViewer',
			'SilverStripe\View\SSViewer_Scope',
			'SilverStripe\View\SSViewer_DataPresenter',

			//'SilverStripe\Control\RequestHandler',
			//'SilverStripe\Control\Director',
			//'SilverStripe\Control\Controller',
			//'SilverStripe\CMS\Controllers\ContentController',
			//'SilverStripe\CMS\Controllers\ModelAsController',

			'SilverStripe\ORM\DataObject',
			'SilverStripe\ORM\DataList',

			//'SilverStripe\Versioned\VersionedHTTPMiddleware',
			//'SilverStripe\Security\AuthenticationMiddleware',
			//'SilverStripe\Control\Middleware\CanonicalURLMiddleware',

			// SS4 Multisites
			'Symbiote\Multisites\Control\MultisitesRootController',
		);

		$caller = array();
		$callerIndex = -1;
		foreach ($bt as $i => $stackItem) {
			$callerIndex = $i;
			if (!isset($caller['class'])) {
				if ($stackItem['function'] === 'include') {
					// If calling "include()" from SSViewer, assume template rendering
					if (isset($stackItem['file']) && 
						strpos($stackItem['file'], 'SSViewer.php') !== FALSE) {
						$caller = $bt[$i-1];
						$caller['class'] = '';
						$caller['function'] = basename($caller['file']);
						break;
					}
				}
				if (// SS3-only: Added to skip Object calling call_user_func_array
					$stackItem['function'] === 'call_user_func_array' ||
					// Added to skip PaginatedList::getTotalItems(), calls 'count()' on DataList
					$stackItem['function'] === 'count') {
					continue;
				}
			}
			// Fuzzy logic to wind up the stack up to just print the *.ss template where a function/method is being called.
			if (($stackItem['class'] === 'SSViewer' && $stackItem['function'] === 'execute_template') ||
				($stackItem['class'] === 'SSViewer_Scope' && $stackItem['function'] === 'next')) {
				$caller = $stackItem;
				$caller['class'] = '';
				$caller['function'] = basename($caller['file']);
				break;
			}
			if (!isset($stackItem['class']) || 
				(!in_array($stackItem['class'], $goUpStackIfClass))) {
				$caller = $stackItem;
				$caller['line'] = isset($bt[$callerIndex-1]['line']) ? $bt[$callerIndex-1]['line'] : -1;
				break;
			}
		}
		if (!$caller) {
			throw new Exception('Class ignore rules are too strict. Unable to determine a caller.');
		}

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
	 * Render profiling information.
	 *
	 * NOTE: This is called by RequestFilter and automatically appended to the bottom
	 * 		 of your HTML.
	 *
	 * @return HTMLText
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
					$list[] = array(
						'Class' => $class,
						'Function' => $function,
						'Line' => $line,
						'Records' => new ArrayList($profileRecordSet),
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

		$templates = array();
		if ($this->isSilverStripe4) {
			$templates = SSViewer::get_templates_by_class(static::class, '', __CLASS__);
		} else {
			$classNameWithoutNamespace = substr(__CLASS__, strrpos(__CLASS__, '\\') + 1);
			$templates[] = $classNameWithoutNamespace;
		}

		$template = new SSViewer($templates);
		$html = $template->process(new ArrayData(array('Results' => new ArrayList($list))), null);
		return $html;
	}
}
