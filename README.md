Mink Sahi.JS Driver
===================

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
    "requires": {
        "behat/mink":              "1.4.*",
        "behat/mink-sahi-driver":  "*"
    }
}
```

``` bash
curl http://getcomposer.org/installer | php
php composer.phar install
```

Copyright
---------

Copyright (c) 2012 Konstantin Kudryashov (ever.zet). See LICENSE for details.

Maintainers
-----------

* Konstantin Kudryashov [everzet](http://github.com/everzet)
