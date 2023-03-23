<?php

define('S2_ROOT', 1);

if (!defined('S2_COUNTER_FUNCTIONS_LOADED')) {
    include '_extensions/s2_counter/functions.php';
}

class RssUserAgentParserTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testParseRssReadersUserAgents()
    {
        $log = file_get_contents('_tests/_resources/rss.log');
        $this->assertEquals(203, s2_counter_get_total_readers($log));
    }
}
