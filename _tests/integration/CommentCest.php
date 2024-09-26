<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

class CommentCest
{
    public function testNonExistentPage(\IntegrationTester $I): void
    {
        $I->sendPost('https://localhost/some-non-existent-url', [
            'name'     => 'Name',
            'email'    => 'a@example.com',
            'text'     => 'text',
            'key'      => '111111111111111111111',
            'question' => '12'
        ]);
        $I->see('The destination page cannot be detected due to an error');
    }
}
