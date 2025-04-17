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
     * @dataProvider requestConfigProvider
     * @throws HttpClientException
     */
    public function testRequest(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);

        if (isset($example['exception'])) {
            $I->expectThrowable(new HttpClientException($example['exception']), function () use ($client, $example) {
                $client->request($example['method'], $example['file'], [], $example['request_body'] ?? null);
            });
        } else {
            $response = $client->request($example['method'], $example['file'], [], $example['request_body'] ?? null);
            $I->assertEquals($example['code'], $response->statusCode);
            $I->assertEquals($example['body'], $response->content);
            if (isset($example['error'])) {
                $I->assertEquals($example['error'], $response->error);
            }
        }
    }

    protected function requestConfigProvider(): array
    {
        $cases     = [];
        $urlPrefix = 'http://localhost:8881/_tests/_resources/http_client_mocks/';
        foreach ([HttpClient::TRANSPORT_CURL, HttpClient::TRANSPORT_FSOCKOPEN, HttpClient::TRANSPORT_FILE_GET_CONTENTS] as $transport) {
            foreach ([
                         ['method' => 'POST', 'code' => 200, 'file' => $urlPrefix . 'mirror.php', 'request_body' => 'some_input', 'body' => 'some_input',],
                         ['method' => 'HEAD', 'code' => 200, 'file' => $urlPrefix . '200.php', 'body' => '',],
                         ['method' => 'GET', 'code' => 200, 'file' => $urlPrefix . '200.php', 'body' => 'Success!',],
                         ['method' => 'GET', 'code' => 200, 'file' => $urlPrefix . '302.php', 'body' => 'Redirected!',],
                         ['method' => 'GET', 'code' => 200, 'file' => $urlPrefix . 'multiredirect.php?redirects=10', 'body' => 'Redirected!',],
                         ['method' => 'GET', 'code' => 0, 'file' => $urlPrefix . 'multiredirect.php?redirects=11', 'exception' => 'Too many redirects'],
                         ['method' => 'GET', 'code' => 404, 'file' => $urlPrefix . '404.php', 'body' => 'Not found!',],
                         ['method' => 'GET', 'code' => 500, 'file' => $urlPrefix . '500.php', 'body' => 'Internal Server Error!',],
                         ['method' => 'GET', 'code' => 0, 'file' => '/', 'exception' => 'Invalid URL: /',],
                     ] as $example) {
                $cases[] = ['transport' => $transport, ...$example];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider transportProvider
     * @throws HttpClientException
     * @throws \JsonException
     */
    public function testPost(AcceptanceTester $I, Example $example): void
    {
        $client   = new HttpClient(preferredTransport: $example['transport']);
        $data     = [
            'var1'     => 'val1',
            'var3'     => ['a', 'b', 'c'],
            'var5'     => ['a' => '1', 'b' => '2', 'c' => '3'],
            'var6'     => ['a' => ['a' => '1', 'b' => '2', 'c' => '3'], 'b' => ['a' => '1', 'b' => '2', 'c' => '3'], 'c' => ['a' => '1', 'b' => '2', 'c' => '3']],
            'int_var'  => 123,
            'bool_var' => true,
        ];
        $response = $client->postJson('http://localhost:8881/_tests/_resources/http_client_mocks/post_only.php', $data);
        $I->assertEquals(400, $response->statusCode);

        $response = $client->post('http://localhost:8881/_tests/_resources/http_client_mocks/post_only.php', $data);
        $I->assertEquals(200, $response->statusCode);
        $expected             = $data;
        $expected['bool_var'] = '1'; // boolean is converted to string during request
        $expected['int_var']  = (string)$expected['int_var']; // int is converted to string during request
        $I->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $response->content);
    }

    /**
     * @dataProvider transportProvider
     * @throws HttpClientException
     * @throws \JsonException
     */
    public function testPostJson(AcceptanceTester $I, Example $example): void
    {
        $client   = new HttpClient(preferredTransport: $example['transport']);
        $data     = [
            'var1'     => 'val1',
            'var3'     => ['a', 'b', 'c'],
            'var4'     => ['a' => 1, 'b' => 2.718281828, 'c' => true],
            'var5'     => ['a' => '1', 'b' => '2', 'c' => '3'],
            'var6'     => ['a' => ['a' => '1', 'b' => '2', 'c' => '3'], 'b' => ['a' => '1', 'b' => '2', 'c' => '3'], 'c' => ['a' => '1', 'b' => '2', 'c' => '3']],
            'int_var'  => 123,
            'bool_var' => true,
        ];
        $response = $client->post('http://localhost:8881/_tests/_resources/http_client_mocks/json_only.php', $data);
        $I->assertEquals(400, $response->statusCode);

        $response = $client->postJson('http://localhost:8881/_tests/_resources/http_client_mocks/json_only.php', $data);
        $I->assertEquals(200, $response->statusCode);
        $I->assertEquals(json_encode($data, JSON_THROW_ON_ERROR), $response->content);
    }

    /**
     * @dataProvider transportProvider
     * @throws HttpClientException
     */
    public function testHeaders(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);

        $response = $client->request('GET', 'http://localhost:8881/_tests/_resources/http_client_mocks/200.php', [
            'X-Test' => 'Some test',
        ]);
        $I->assertEquals(200, $response->statusCode);
        $I->assertEquals('Some test', $response->getHeader('X-Test'));
        $I->assertEquals('Some test', $response->getHeader('x-test'));
        $I->assertEquals('Some test', $response->getHeader('X-TEST-2'));
    }

    /**
     * @dataProvider transportProvider
     */
    public function testHostResolveError(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);

        $e = null;
        try {
            $client->request(
                'GET',
                'http://this-domain-should-not-exist-12345.test',
                options: [HttpClient::CONNECT_TIMEOUT => 1],
            );
        } catch (HttpClientException $e) {
        }
        $I->assertNotNull($e);
        $I->assertEquals(HttpClientException::REASON_HOST_RESOLVE_FAILURE, $e->reason);
    }

    /**
     * @dataProvider transportProvider
     */
    public function testConnectTimeout(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);

        $now = microtime(true);
        $e = null;
        try {
            $client->request(
                'GET',
                'http://192.0.2.1',
                options: [HttpClient::CONNECT_TIMEOUT => 1],
            );
        } catch (HttpClientException $e) {
        }
        $I->assertNotNull($e);
        $I->assertEquals(HttpClientException::REASON_TIMEOUT, $e->reason);
        if ($example['transport'] !== HttpClient::TRANSPORT_FILE_GET_CONTENTS) {
            // Cannot control the timeout value in file_get_contents
            $I->assertLessThan(1.5, microtime(true) - $now);
        }

    }

    /**
     * @dataProvider transportProvider
     */
    public function testReadTimeout(AcceptanceTester $I, Example $example): void
    {
        $client = new HttpClient(preferredTransport: $example['transport']);
        $now = microtime(true);
        $e = null;
        try {
            $client->request(
                'GET',
                'http://localhost:8881/_tests/_resources/http_client_mocks/sleep.php?time=2',
                options: [HttpClient::READ_TIMEOUT => 1, HttpClient::CONNECT_TIMEOUT => 1],
            );
        } catch (HttpClientException $e) {
        }
        $I->assertNotNull($e);
        $I->assertEquals(HttpClientException::REASON_TIMEOUT, $e->reason);
        // Curl total timeout is set to connect+read = 2
        $I->assertLessThan(2.5, microtime(true) - $now);
    }

    protected function transportProvider(): array
    {
        $cases = [];
        foreach ([HttpClient::TRANSPORT_CURL, HttpClient::TRANSPORT_FSOCKOPEN, HttpClient::TRANSPORT_FILE_GET_CONTENTS] as $transport) {
            $cases[] = ['transport' => $transport];
        }
        return $cases;
    }
}
