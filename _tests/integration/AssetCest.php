<?php
/**
 * @copyright 2026 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use S2\Cms\Asset\AssetMerge;
use S2\Cms\Asset\AssetMergeFactory;
use S2\Cms\HttpClient\HttpClient;

class AssetCest
{
    public function testAssetMergeUsesSslVerifyingHttpClient(\IntegrationTester $I): void
    {
        /** @var HttpClient $assetHttpClient */
        $assetHttpClient = $I->grabService('asset_http_client');
        $I->assertTrue($this->isHttpClientSslVerificationEnabled($assetHttpClient));

        /** @var AssetMergeFactory $factory */
        $factory = $I->grabService(AssetMergeFactory::class);
        $merge   = $factory->create('test_scripts', AssetMerge::TYPE_JS);

        $httpClientProperty = new \ReflectionProperty(AssetMerge::class, 'httpClient');
        $httpClientProperty->setAccessible(true);

        $I->assertSame($assetHttpClient, $httpClientProperty->getValue($merge));
    }

    private function isHttpClientSslVerificationEnabled(HttpClient $httpClient): bool
    {
        $verifySslProperty = new \ReflectionProperty(HttpClient::class, 'verifySsl');
        $verifySslProperty->setAccessible(true);

        return $verifySslProperty->getValue($httpClient);
    }
}
