<?php

namespace SilbinaryWolf\Ralph;

spl_autoload_register('SilbinaryWolf\Ralph\ss3_and_4_compat_autoloader');

function ss3_and_4_compat_autoloader($classname) {
	$classname = strtolower($classname);
	if($classname !== "silbinarywolf\\ralph\\classname") {
		return;
	}
	if (class_exists('SilverStripe\Core\CoreKernel')) {
		require_once dirname(__FILE__).'/ClassName_4.php';
		return;
	}
	require_once dirname(__FILE__).'/ClassName_3.php';
}
