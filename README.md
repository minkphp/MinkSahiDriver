Mink Sahi.JS Driver
===================

[![Latest Stable Version](https://poser.pugx.org/behat/mink-sahi-driver/v/stable.svg)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Latest Unstable Version](https://poser.pugx.org/behat/mink-sahi-driver/v/unstable.svg)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Total Downloads](https://poser.pugx.org/behat/mink-sahi-driver/downloads.svg)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Build Status](https://travis-ci.org/minkphp/MinkSahiDriver.svg?branch=master)](https://travis-ci.org/minkphp/MinkSahiDriver)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/minkphp/MinkSahiDriver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/minkphp/MinkSahiDriver/)
[![Code Coverage](https://scrutinizer-ci.com/g/minkphp/MinkSahiDriver/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/minkphp/MinkSahiDriver/)
[![License](https://poser.pugx.org/behat/mink-sahi-driver/license.svg)](https://packagist.org/packages/behat/mink-sahi-driver)

Usage Example
-------------

``` php
<?php

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\SahiDriver;

$startUrl = 'http://example.com';

$mink = new Mink(array(
    'sahi' => new Session(new SahiDriver('firefox')),
));

$mink->getSession('sahi')->getPage()->findLink('Chat')->click();
```

Installation
------------

``` json
{
    "require": {
        "behat/mink":              "~1.5",
        "behat/mink-sahi-driver":  "~1.1"
    }
}
```

``` bash
$> curl -sS https://getcomposer.org/installer | php
$> php composer.phar install
```

Maintainers
-----------

* Konstantin Kudryashov [everzet](http://github.com/everzet)
