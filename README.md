Ralph
====================================

![ralph](https://cloud.githubusercontent.com/assets/3859574/20237062/ffcbf366-a91b-11e6-9b22-81869b6260b6.jpg)

A drop-in module for profiling DataList instances in Silverstripe

## Installation
```composer require silbinarywolf/silverstripe-ralph ~1.1.0```

## How to use

Put in _config.php
```
Ralph::enable(array(
	'classes' => array(
		// Classes to instrument
		'Page' => array(
			// Functions to instrument
			'init'
		),
	),
	// By default, this is configured to profile DataList, set this array to be empty to turn off.
	//'default_classes' => array(),
	// Enable to dump instrumented code to /ralph/code_generated (for debugging this profiler/debugger)
	'dump_file' => false,
));
```

## How does it work?

1) Checks your settings
2) Pulls the pre and post instrumentation code from Ralph::preFunctionCall and Ralph::postFunctionCall.
3) Generates a new version of the class that extends the original and runs 'eval' with the original function wrapped.
4) Prints out in order from most time taken to least using the provided 'Ralph.ss' template.

For DataLists, it also tracks where the DataList was constructed.

## Requirements
- SilverStripe 3.2 or higher
- PHP 5.4 or higher