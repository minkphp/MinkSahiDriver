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

    public function testIFrame()
    {
        $this->markTestSkipped('Sahi doesn\'t support iFrames switching');
    }

    public function testWindow()
    {
        $this->markTestSkipped('Sahi doesn\'t support window switching');
    }

    /**
     * @group test-only
     */
    public function testIssue32()
    {
        $session = $this->getSession();
        $session->visit($this->pathTo('/advanced_form.php'));
        $page = $session->getPage();

        $sex = $page->find('xpath', '//*[@name = "sex"]' . "\n|\n" . '//*[@id = "sex"]');
        $this->assertNotNull($sex, 'xpath with line ending works');

        $sex->setValue('m');
        $this->assertEquals('m', $sex->getValue(), 'no double xpath escaping during radio button value change');
    }
}
