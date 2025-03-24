<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace acceptance;

use AcceptanceTester;
use Codeception\Example;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\HttpClient\HttpClientException;

/**
 * @group http
 */
class HttpClientCest
{
    /**
     * @dataProvider configProvider
     */
    public function tryToTest(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);

        if (isset($example['exception'])) {
            $I->expectThrowable(new HttpClientException($example['exception']), function () use ($client, $example) {
                $client->fetch($example['file']);
            });
        } else {
            $response = $client->fetch($example['file']);
            $I->assertEquals($example['code'], $response->statusCode);
            $I->assertEquals($example['body'], $response->content);
            if (isset($example['error'])) {
                $I->assertEquals($example['error'], $response->error);
            }
        }
    }

    protected function configProvider(): array
    {
        $cases     = [];
        $urlPrefix = 'http://localhost:8881/_tests/_resources/http_client_mocks/';
        foreach ([HttpClient::TRANSPORT_CURL, HttpClient::TRANSPORT_FSOCKOPEN, HttpClient::TRANSPORT_FILE_GET_CONTENTS] as $transport) {
            foreach ([
                         ['code' => 200, 'file' => $urlPrefix . '200.php', 'body' => 'Success!',],
                         ['code' => 200, 'file' => $urlPrefix . '302.php', 'body' => 'Redirected!',],
                         ['code' => 200, 'file' => $urlPrefix . 'multiredirect.php?redirects=10', 'body' => 'Redirected!',],
                         ['code' => 0, 'file' => $urlPrefix . 'multiredirect.php?redirects=11', 'exception' => 'Too many redirects'],
                         ['code' => 404, 'file' => $urlPrefix . '404.php', 'body' => 'Not found!',],
                         ['code' => 500, 'file' => $urlPrefix . '500.php', 'body' => 'Internal Server Error!',],
                         ['code' => 0, 'file' => '/', 'exception' => 'Invalid URL: /',],
                     ] as $example) {
                $cases[] = ['transport' => $transport, ...$example];
            }
        }
        return $cases;
    }
}
