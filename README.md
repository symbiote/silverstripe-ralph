# Ralph

[![Latest Stable Version](https://poser.pugx.org/silbinarywolf/silverstripe-ralph/version.svg)](https://github.com/silbinarywolf/silverstripe-ralph/releases)
[![Latest Unstable Version](https://poser.pugx.org/silbinarywolf/silverstripe-ralph/v/unstable.svg)](https://packagist.org/packages/silbinarywolf/silverstripe-ralph)
[![Total Downloads](https://poser.pugx.org/silbinarywolf/silverstripe-ralph/downloads.svg)](https://packagist.org/packages/silbinarywolf/silverstripe-ralph)
[![License](https://poser.pugx.org/silbinarywolf/silverstripe-ralph/license.svg)](https://github.com/silbinarywolf/silverstripe-ralph/blob/master/LICENSE.md)

A drop-in module that allows simple and easy profiling of DataList's in SilverStripe.

**WARNING:** This module uses [potentially slow include methods](https://github.com/silbinarywolf/silverstripe-ralph/blob/master/src/ss4_compat.php#L14) and should not be installed in production builds.

![ralph](https://cloud.githubusercontent.com/assets/3859574/20237062/ffcbf366-a91b-11e6-9b22-81869b6260b6.jpg)

## Why not just use XHProf or similar?

Tracking down DataList slowdowns in XHProf or similar can be very tedious as they could be executed in templates or in a context that is far removed from where you initially created the list. This simplifies the data presented to you so that you can easily identify what is causing the slowdown, when it was initially created and where it was executed.

Another benefit of this module is that you can do a quick 5-10 minute profile of your website but simply dropping in and enabling this module.

## Composer Install

```
composer require silbinarywolf/silverstripe-ralph:~2.0
```

## Requirements

* SilverStripe 3.X and 4.X (cross-compatible)

Please note: This module has only recently been tested to work in SilverStripe 3.6 and 4.0.

## Documentation

* [Quick Start](docs/en/quick-start.md)
* [Advanced Usage](docs/en/advanced-usage.md)
* [License](LICENSE.md)
* [Contributing](CONTRIBUTING.md)
