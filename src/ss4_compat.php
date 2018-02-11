<?php

if (class_exists('SilverStripe\Core\CoreKernel')) {
	// SilverStripe 4 compatibility
	/*class_alias('SilverStripe\\Admin\\LeftAndMain', 'LeftAndMain');
	class_alias('SilverStripe\\View\\ViewableData', 'ViewableData');
	class_alias('SilverStripe\\Control\\RequestFilter', 'RequestFilter');
	class_alias('SilverStripe\\Core\\Config\\Config', 'Config');
	class_alias('SilverStripe\\ORM\\DataList', 'DataList');
	class_alias('SilverStripe\\ORM\\ArrayList', 'ArrayList');
	class_alias('SilverStripe\\Core\\Injector\\Injector', 'Injector');
	class_alias('SilverStripe\\Core\\ClassInfo', 'ClassInfo');*/

	// NOTE(Jake): 2018-02-11
	//
	// We use class_alias() to make references to old classes work via the .upgrade.yml files.
	//
	// In practice this probably makes Ralph too slow to keep this module in the repo during production, 
	// but since this module is meant for local debugging purposes only, I figure it's OK.
	//
	$frameworkFile = Symfony\Component\Yaml\Yaml::parseFile(BASE_PATH.'\vendor\silverstripe\framework\.upgrade.yml');
	if (!isset($frameworkFile['mappings'])) {
		throw new Exception('Missing "vendor\silverstripe\framework\.upgrade.yml". This is required for Ralph\'s cross-compatibility layer.');
	}
	$cmsFile = Symfony\Component\Yaml\Yaml::parseFile(BASE_PATH.'\vendor\silverstripe\cms\.upgrade.yml');
	if (!isset($cmsFile['mappings'])) {
		throw new Exception('Missing "vendor\silverstripe\cms\.upgrade.yml". This is required for Ralph\'s cross-compatibility layer.');
	}
	$classSS3toSS4 = array_merge($frameworkFile['mappings'], $cmsFile['mappings']);
	foreach ($classSS3toSS4 as $ss3ClassName => $ss4ClassName) {
		if (!class_exists($ss4ClassName) ||
			class_exists($ss3ClassName)) {
			// !class_exists($ss4ClassName) - This is to ignore template remapping and other misc non-classes.
			// class_exists($ss3ClassName - This is to stop redeclarations of SITETREE/CMSSIteTreeFilter_PublishedPages
			continue;
		}
		class_alias($ss4ClassName, $ss3ClassName);
	}
	
	include_once(dirname(__FILE__).'/Ralph/V4/RequestFilter.php');
} else {
	// Silverstripe 3 compatibility
	include_once(dirname(__FILE__).'/Ralph/V3/RequestFilter.php');
}
