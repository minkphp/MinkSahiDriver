Mink Sahi.JS Driver
===================

[![Latest Stable Version](https://poser.pugx.org/behat/mink-sahi-driver/v/stable.png)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Total Downloads](https://poser.pugx.org/behat/mink-sahi-driver/downloads.png)](https://packagist.org/packages/behat/mink-sahi-driver)
[![Build
Status](https://travis-ci.org/Behat/MinkSahiDriver.png?branch=master)](https://travis-ci.org/Behat/MinkSahiDriver)

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
