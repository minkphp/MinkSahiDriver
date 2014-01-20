Mink Sahi.JS Driver
===================

[![Latest Stable Version](https://poser.pugx.org/behat/mink-sahi-driver/v/stable.png)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Total Downloads](https://poser.pugx.org/behat/mink-sahi-driver/downloads.png)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Build
Status](https://travis-ci.org/Behat/MinkSahiDriver.png?branch=master)](https://travis-ci.org/Behat/MinkSahiDriver)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/Behat/MinkSahiDriver/badges/quality-score.png?s=89b0864e22c3da5eb41fc58ae362683b8a3d46d2)](https://scrutinizer-ci.com/g/Behat/MinkSahiDriver/)
[![Code Coverage](https://scrutinizer-ci.com/g/Behat/MinkSahiDriver/badges/coverage.png?s=5b8d6093eab2f70418b855fc2f888ce49e30eff1)](https://scrutinizer-ci.com/g/Behat/MinkSahiDriver/)

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
        "behat/mink":              "1.4.*",
        "behat/mink-sahi-driver":  "1.0.*"
    }
}
```

``` bash
$> curl http://getcomposer.org/installer | php
$> php composer.phar install
```

Maintainers
-----------

* Konstantin Kudryashov [everzet](http://github.com/everzet)
