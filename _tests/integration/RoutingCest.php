<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use IntegrationTester;
use Symfony\Component\HttpFoundation\Response;

class RoutingCest
{
    public function methodNotAllowedReturns405ForUnsupportedVerb(IntegrationTester $I): void
    {
        $I->sendRequestWithMethod('DELETE', 'https://localhost/some-path');
        $I->seeResponseCodeIs(Response::HTTP_METHOD_NOT_ALLOWED);

        $allow = $I->grabHttpHeader('Allow');
        $I->assertNotNull($allow, 'Allow header must be present on a 405 response');
        $I->assertStringContainsString('GET', $allow);
        $I->assertStringContainsString('POST', $allow);
    }

    public function methodNotAllowedReturns405ForPutOnGetOnlyRoute(IntegrationTester $I): void
    {
        $I->sendRequestWithMethod('PUT', 'https://localhost/comment_unsubscribe');
        $I->seeResponseCodeIs(Response::HTTP_METHOD_NOT_ALLOWED);

        $allow = $I->grabHttpHeader('Allow');
        $I->assertNotNull($allow, 'Allow header must be present on a 405 response');
        $I->assertStringContainsString('GET', $allow);
    }

    public function getOnExistingRouteStillWorks(IntegrationTester $I): void
    {
        $I->amOnPage('/');
        $I->seeResponseCodeIs(Response::HTTP_OK);
    }
}
