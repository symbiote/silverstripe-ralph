<?php

namespace SilbinaryWolf\Ralph;

//
// A compatibility layer so that Ralph can work on SS3 and SS4 projects seamlessly.
//
// WARNING: "ClassName" can not be used to determine the correct class during ConfigManifest
//			building. So *do not* use this values in "const" or "private static" variables.
//

if (class_exists('SilverStripe\Core\CoreKernel')) {
	class ClassName_4 {
		const DataList = SilverStripe\ORM\DataList::class;
		const RequestProcessor = '';
		const ArrayList = '';
	}
	class_alias(ClassName_4::class, 'SilbinaryWolf\Ralph\ClassName_Compat');
} else {
	// NOTE(Jake): 2018-02-11
	//
	// Not using ::class for PHP 5.3+ support
	//
	class ClassName_3 {
		const DataList = 'DataList';
		const RequestProcessor = 'RequestProcessor';
		const ArrayList = 'ArrayList';
		const SSViewer = 'SSViewer';
	}
	class_alias('SilbinaryWolf\Ralph\ClassName_3', 'SilbinaryWolf\Ralph\ClassName_Compat');
}

class ClassName extends ClassName_Compat {}
