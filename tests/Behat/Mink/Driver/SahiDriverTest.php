<?php

namespace Tests\Behat\Mink\Driver;

use Behat\Mink\Driver\SahiDriver;
use Behat\SahiClient\Client;
use Behat\SahiClient\Connection;

/**
 * @group sahidriver
 */
class SahiDriverTest extends JavascriptDriverTest
{
    protected static function getDriver()
    {
        $connection = new Connection(null, $_SERVER['DRIVER_HOST'], 9999);

        return new SahiDriver($_SERVER['WEB_FIXTURES_BROWSER'], new Client($connection));
    }

    /**
     * @group issue131
     */
    public function testIssue131()
    {
        $this->getSession()->visit($this->pathTo('/issue131.php'));
        $page = $this->getSession()->getPage();

        $page->selectFieldOption('foobar', 'Gimme some accentués characters');
    }

    /**
     * @dataProvider prepareXPathDataProvider
     */
    public function testPrepareXPath($expected, $input)
    {
        $driver = $this->getSession()->getDriver();

        // Make the method accessible for testing purposes
        $method = new \ReflectionMethod('Behat\Mink\Driver\SahiDriver', 'prepareXPath');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invokeArgs($driver, array($input)));
    }

    public function prepareXPathDataProvider()
    {
        return array(
            array('No quotes', 'No quotes'),
            array("Single quote'", "Single quote'"),
            array('Double quote\"', 'Double quote"'),
            array('Multi\nline', "Multi\nline"),
        );
    }

    public function testIFrame()
    {
        $this->markTestSkipped('Sahi doesn\'t support iFrames switching');
    }

    public function testWindow()
    {
        $this->markTestSkipped('Sahi doesn\'t support window switching');
    }
}
