# Advanced Usage

```php
Symbiote\Ralph\Ralph::enable(array(
	// Enable to dump instrumented code to /ralph/src_generated (for debugging this profiler/debugger and getting an idea of what its doing)
	'dump_file' => false,
	// Disable/Enable this to work in the CMS.
	'enable_cms' => false,
	// NOTE: This hasn't been tested since recent upgrades and won't work unless those classes
	//		 are instantiated via the Injector.
	'classes' => array(
		// Classes to instrument
		'Page' => array(
			// Functions to instrument
			'init'
		),
	),
));
```

# How does it work?

When you enable it this module `Symbiote\Ralph\Ralph::enable();`, it will automatically apply pull pre and post instrumentation code from `Ralph::preFunctionCall` and `Ralph::postFunctionCall`, these are then used to create a class that extends the original class but all instrumented functions are wrapped. This new class is then run through `eval()` and loaded. (If you want to inspect the code, enable `dump_file`, load page, and check "src_generated")

Once the new class is evaluated, the config of the Injector is updated on-the-fly so that any DataList objects created via the Injector will use Ralph's custom instrumented version.

Finally, the Injector is updated again so a `RequestFilter` can insert additional HTML to the bottom of the page, this HTML is the profiling information.
