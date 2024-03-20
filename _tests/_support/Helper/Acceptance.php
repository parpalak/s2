<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace Helper;

use Codeception\Module;
use Codeception\Module\PhpBrowser;

class Acceptance extends Module
{
    public function grabHeaders(): array
    {
        /** @var PhpBrowser $browser */
        $browser = $this->getModule('PhpBrowser');

        return $browser->client->getInternalResponse()->getHeaders();
    }
}
