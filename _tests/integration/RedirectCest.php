<?php

declare(strict_types=1);

namespace integration;

use \IntegrationTester;
use Symfony\Component\HttpFoundation\Response;

class RedirectCest
{
    public function _before(IntegrationTester $I)
    {
    }

    // tests
    public function tryToTest(IntegrationTester $I)
    {
        $I->amOnPage('/redirect');
        $I->seeResponseCodeIs(Response::HTTP_MOVED_PERMANENTLY);
        $I->seeLocationIs('/redirected');
    }
}
