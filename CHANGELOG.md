1.2.1 / 2016-03-05
==================

Bug fixes:

* Fixed the handling of cookies with semicolon in the value

Testsuite:

* Added testing on PHP 7

1.2.0 / 2015-09-21
==================

BC break:

* Changed the behavior of `getValue` for checkboxes according to the BC break in Mink 1.6

New features:

* Updated the driver to use findElementsXpaths for Mink 1.7 and forward compatibility with Mink 2
* Implemented `submitForm`
* Implemented `isSelected`
* Return the condition in the `wait` method
* Allow to specify alternative (not always 'localhost') Sahi server url
* Implemented `getOuterHtml`

Bug fixes:

* Fixed `getValue` for multi-select without `name` attribute
* Fixed the returned value for missing cookies
* Fixed the removal of cookies
* Fixed double xpath escaping in `selectRadioOption`
* Fixed the escaping of multiline XPath queries
* Fixed double JS escaping of XPath strings when executing scripts
* Fixed the evaluation of scripts
* Fixed `executeScript` for scripts with trailing `;` or `return`
* Improved the escaping of variables used in JS code

Testsuite:

* Updated the testsuite to use the new Mink 1.6 driver testsuite
* Added testing on HHVM

Misc:

* Updated the repository structure to PSR-4
