<?php

namespace Behat\Mink\Tests\Driver\Custom;

use Behat\Mink\Tests\Driver\TestCase;

// TODO move these tests upstream
class ExtraTest extends TestCase
{
    /**
     * @group test-only
     */
    public function testIssue32()
    {
        $session = $this->getSession();
        $session->visit($this->pathTo('/advanced_form.html'));
        $page = $session->getPage();

        $sex = $page->find('xpath', '//*[@name = "sex"]' . "\n|\n" . '//*[@id = "sex"]');
        $this->assertNotNull($sex, 'xpath with line ending works');

        $sex->setValue('m');
        $this->assertEquals('m', $sex->getValue(), 'no double xpath escaping during radio button value change');
    }
}
