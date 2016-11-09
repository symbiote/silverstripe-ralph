<?php 

// This is a workaround for the injector affecting subclasses
// basically force any subclasses of DataList to use their original
// class leave the Injector behaviour in-tact.

$subclasses = ClassInfo::subclassesFor('DataList');
unset($subclasses['DataList']);
$originalInjectorInfo = array();
foreach ($subclasses as $class => $v) {
	$originalInjectorInfo[$class] = Config::inst()->get('Injector', $class);
}

$injector = Config::inst()->get('Injector', 'DataList');
$injector['class'] = 'SilbinaryWolf\Profiler\DataList';
Config::inst()->update('Injector', 'DataList', $injector);

foreach ($subclasses as $class => $v) {
	$originalInjector = $originalInjectorInfo[$class];
	if (!isset($originalInjector[$class]['class'])) {
		$originalInjector[$class]['class'] = $class;
	}
	Config::inst()->update('Injector', $class, $originalInjector[$class]);
}
