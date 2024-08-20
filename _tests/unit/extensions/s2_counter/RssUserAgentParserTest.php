<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace unit\extensions\s2_counter;

use Codeception\Test\Unit;

define('S2_ROOT', 1);

if (!defined('S2_COUNTER_FUNCTIONS_LOADED')) {
    include '_extensions/s2_counter/functions.php';
}

class RssUserAgentParserTest extends Unit
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
    public function testParseRssReadersUserAgents(): void
    {
        $log = file_get_contents('_tests/_resources/rss.log');
        $this->assertEquals(203, s2_counter_get_total_readers($log));
    }
}
