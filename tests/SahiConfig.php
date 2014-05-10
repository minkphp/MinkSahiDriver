<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\SahiDriver;
use Behat\SahiClient\Client;
use Behat\SahiClient\Connection;

class SahiConfig extends AbstractConfig
{
    public static function getInstance()
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function createDriver()
    {
        $connection = new Connection(null, $_SERVER['DRIVER_HOST'], 9999);

        return new SahiDriver($_SERVER['WEB_FIXTURES_BROWSER'], new Client($connection));
    }
}
