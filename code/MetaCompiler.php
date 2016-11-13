<?php

namespace SilbinaryWolf\Ralph;
use SS_ClassLoader;
use Debug;

class MetaCompiler {
	public function postDataListConstructCall() {
		singleton('Ralph')->dataListConstructor($this);
	}

	public function preFunctionCall() {
		$timeDifference = microtime(true);
	}

	public function postFunctionCall() {
		$timeDifference = microtime(true) - $timeDifference;
		singleton('Ralph')->profilerStore($this, __FUNCTION__, $timeDifference);
	}

	public function process() {
		$dumpToFiles = false;
		$classes = array('DataList', 'ManyManyList', 'HasManyList');
		foreach ($classes as $class) {
			$code = $this->generateClassCode($class, array(
				'__construct' => array(null, 'postDataListConstructCall'), 
				'toArray',
				'count',
				'max',
				'min',
				'avg',
				'sum',
				'toNestedArray', 
				'limit',
			));
			if ($dumpToFiles) {
				// Used to debug custom instrumented code
				$filepath = BASE_PATH.'/ralph/code_generated/'.$class.'.php';
				file_put_contents($filepath, $code);
			}
			eval(str_replace('<?php', '', $code));
		}
		foreach ($classes as $class) {
			singleton('Ralph')->useCustomClass($class, 'SilbinaryWolf\\Ralph\\Generated\\'.$class);
		}
	}

	/**
	 * Experimental idea, replace class manifest path with /code_generated/
	 *
	public function monkeyPatch() {
		$includedFiles = get_included_files();
		$includedFiles = array_flip($includedFiles);
		$loader = SS_ClassLoader::instance();
		$classManifest = $loader->getManifest();
		$classes = $classManifest->getClasses();
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			// Fix Windows
			$filepath = str_replace('/', '\\', $filepath);
		}
		//if (isset($includedFiles[$filepath])) {
		//	throw new Exception("Cannot monkeypatch");
		//}
		$classes['datalist'] = $filepath;
		singleton('Ralph')->useCustomClass('DataList', 'SilbinaryWolf\Ralph\DataList');
	}*/

	public function generateClassCode($class, array $functions) {
		$thisClass = get_class();
		$defaultPreFunctionCall = $this->getMethodBody($thisClass, 'preFunctionCall');
		$defaultPostFunctionCall = $this->getMethodBody($thisClass, 'postFunctionCall');

		$indent = "\t";
		$indent2x = $indent.$indent;

		$newClass = '';
		$newClass .= '<?php'."\n\n";
		$newClass .= '// Warning: This file is auto-generated by MetaCompiler in the Ralph module.'."\n\n";
		$newClass .= 'namespace SilbinaryWolf\Ralph\Generated;'."\n";
		$newClass .= "\n";
		$newClass .= 'class '.$class.' extends \\'.$class.' {'."\n";
		foreach ($functions as $function => $info) {
			$preFunctionCall = $defaultPreFunctionCall;
			$postFunctionCall = $defaultPostFunctionCall;
			if (is_numeric($function)) {
				$function = $info;
			} else {
				$preFunctionCall = $info[0];
				if ($preFunctionCall) {
					$preFunctionCall = $this->getMethodBody($thisClass, $preFunctionCall);
				} else {
					$preFunctionCall = '';
				}
				$postFunctionCall = $info[1];
				if ($postFunctionCall) {
					$postFunctionCall = $this->getMethodBody($thisClass, $postFunctionCall);
				} else {
					$postFunctionCall = '';
				}
			}
			$reflect = new \ReflectionMethod($class, $function);
			$newClass .= $indent.'public function '.$function.'(';
			$parameters = $reflect->getParameters();
			foreach ($parameters as $i => $field) {
				if ($i > 0) {
					$newClass.=',';
				}
				$value = null;
				try {
					$value = $field->getDefaultValue();
				} catch (\ReflectionException $e) {}
				$newClass .= '$'.$field->name;
				if ($value !== null) {
					if (is_array($value)) {
						$newClass .= ' = array(';
						$isFirst = true;
						foreach ($value as $i => $subval) {
							if ($isFirst === false) {
								$newClass .= ',';
							}
							$newClass .= $subval;
							$isFirst = false;
						}
						$newClass .= ')';
					} else {
						$newClass .= ' = '.$value;
					}
				}
			}
			$newClass .= ')';
			$newClass .= "{\n";
			$newClass .= $indent2x.$preFunctionCall."\n";
			// todo(Jake): add params
			$newClass .= $indent2x.'$___result = parent::'.$function.'(';
			foreach ($parameters as $i => $field) {
				if ($i > 0) {
					$newClass.=',';
				}
				$newClass .= '$'.$field->name;
			}
			$newClass .= ");\n";
			$newClass .= $indent2x.$postFunctionCall."\n";
			$newClass .= $indent2x.'return $___result;'."\n";
			$newClass .= $indent."}\n\n";
		}
		$newClass .= "}\n";
		return $newClass;
	}

	public function getMethodBody($class, $function) {
		$func = new \ReflectionMethod($class, $function);
		$filename = $func->getFileName();
		$start_line = $func->getStartLine() - 1;
		$end_line = $func->getEndLine();
		$length = $end_line - $start_line;

		$source = file($filename);
		$body = implode('', array_slice($source, $start_line, $length));
		$startPos = strpos($body, '{');
		$endPos = strrpos($body, '}');

		$body = trim(substr($body, $startPos + 1, ($endPos-$startPos) - 1));
		return $body;
	}
}