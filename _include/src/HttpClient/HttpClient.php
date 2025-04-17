<?php /** @noinspection PhpComposerExtensionStubsInspection */
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\HttpClient;

readonly class HttpClient
{
    public const  TRANSPORT_CURL              = 'curl';
    public const  TRANSPORT_FSOCKOPEN         = 'fsockopen';
    public const  TRANSPORT_FILE_GET_CONTENTS = 'file_get_contents';
    public const  CONNECT_TIMEOUT             = 'connect_timeout';
    public const  READ_TIMEOUT                = 'read_timeout';
    private const ALLOWED_METHODS             = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    public function __construct(
        private int     $timeout = 10,
        private int     $maxRedirects = 10,
        private string  $userAgent = 'S2',
        private bool    $verifySsl = false,
        private ?string $preferredTransport = null,
    ) {
        if ($preferredTransport !== null && !\in_array($this->preferredTransport, [
                self::TRANSPORT_CURL,
                self::TRANSPORT_FSOCKOPEN,
                self::TRANSPORT_FILE_GET_CONTENTS
            ], true)) {
            throw new \InvalidArgumentException(\sprintf('Transport "%s" is not supported', $this->preferredTransport));
        }
    }

    /**
     * @throws HttpClientException
     */
    public function request(
        string  $method,
        string  $url,
        array   $headers = [],
        ?string $body = null,
        array   $options = [],
    ): HttpResponse {
        $method = strtoupper($method);

        if (!\in_array($method, self::ALLOWED_METHODS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported HTTP method: %s', $method));
        }

        $url = $this->normalizeUrl($url);

        foreach ($headers as $k => $v) {
            if (!\is_string($v)) {
                throw new \InvalidArgumentException(\sprintf('Header "%s" must be a string', $k));
            }
        }

        return match ($this->getPreferredTransport()) {
            self::TRANSPORT_CURL => $this->requestWithCurl($method, $url, $headers, $body, $options),
            self::TRANSPORT_FSOCKOPEN => $this->requestWithFsockopen($method, $url, $headers, $body, $options),
            self::TRANSPORT_FILE_GET_CONTENTS => $this->requestWithFileGetContents($method, $url, $headers, $body, $options),
            default => throw new HttpClientException('No available method to fetch the URL'),
        };
    }

    /**
     * @throws HttpClientException
     */
    public function fetch(string $url): HttpResponse
    {
        return $this->request('GET', $url);
    }

    /**
     * @throws HttpClientException
     * @throws \JsonException
     */
    public function postJson(string $url, array $body, array $options = []): HttpResponse
    {
        return $this->request(
            'POST',
            $url,
            ['Content-Type' => 'application/json'],
            \json_encode($body, JSON_THROW_ON_ERROR),
            $options
        );
    }

    /**
     * @throws HttpClientException
     */
    public function post(string $url, array $body, array $options = []): HttpResponse
    {
        return $this->request(
            'POST',
            $url,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($body),
            $options
        );
    }

    /**
     * @throws HttpClientException
     */
    private function normalizeUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            throw new HttpClientException('Invalid URL: ' . $url);
        }
        if (!isset($parsedUrl['scheme'])) {
            $url = 'https://' . ltrim($url, '/');
        }
        return $url;
    }

    private static function newUrlFromLocation(string $location, string $currentUrl): string
    {
        $parsedCurrentUrl = parse_url($currentUrl);
        $parsedLocation   = parse_url($location);

        return self::unparseUrl(array_merge($parsedCurrentUrl, $parsedLocation));
    }

    private static function unparseUrl(array $parsed): string
    {
        $pass      = $parsed['pass'] ?? null;
        $user      = $parsed['user'] ?? null;
        $userinfo  = $pass !== null ? "$user:$pass" : $user;
        $port      = $parsed['port'] ?? 0;
        $scheme    = $parsed['scheme'] ?? "";
        $query     = $parsed['query'] ?? "";
        $fragment  = $parsed['fragment'] ?? "";
        $authority = (
            ($userinfo !== null ? "$userinfo@" : "") .
            ($parsed['host'] ?? "") .
            ($port ? ":$port" : "")
        );

        return (
            ($scheme !== '' ? "$scheme:" : "") .
            ($authority !== '' ? "//$authority" : "") .
            ($parsed['path'] ?? "") .
            ($query !== '' ? "?$query" : "") .
            ($fragment !== '' ? "#$fragment" : "")
        );
    }

    /**
     * @throws HttpClientException
     */
    private function requestWithCurl(
        string  $method,
        string  $url,
        array   $requestHeaders,
        ?string $requestBody,
        array   $options = [],
        int     $redirects = 0
    ): HttpResponse {
        if ($redirects > $this->maxRedirects) {
            throw new HttpClientException('Too many redirects');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $options[self::CONNECT_TIMEOUT] ?? $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($options[self::CONNECT_TIMEOUT], $options[self::READ_TIMEOUT]) ? $options[self::CONNECT_TIMEOUT] + $options[self::READ_TIMEOUT] : $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($requestBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        }

        if (!empty($requestHeaders)) {
            $formattedHeaders = array_map(static fn($k, $v) => "$k: $v", array_keys($requestHeaders), $requestHeaders);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        }

        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            /** @noinspection CurlSslServerSpoofingInspection */
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        }

        $content      = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorNum     = curl_errno($ch);
        $error        = $errorNum ? curl_error($ch) : null;
        curl_close($ch);

        if ($content === false || $error !== null) {
            throw new HttpClientException($error ?? 'Unknown error', match ($errorNum) {
                CURLE_OPERATION_TIMEOUTED => HttpClientException::REASON_TIMEOUT,
                CURLE_COULDNT_RESOLVE_HOST => HttpClientException::REASON_HOST_RESOLVE_FAILURE,
                default => null
            });
        }

        $contentStart = strpos($content, "\r\n\r\n");
        if ($contentStart !== false) {
            [$rawHeaders, $content] = explode("\r\n\r\n", $content, 2);
        } else {
            $rawHeaders = '';
        }
        $responseHeaders = explode("\r\n", $rawHeaders);

        if (preg_match('/^Location:\s*(.*)/mi', $rawHeaders, $matches)) {
            return $this->requestWithCurl($method, self::newUrlFromLocation(trim($matches[1]), $url), $requestHeaders, $requestBody, $options, $redirects + 1);
        }

        return $this->createResponse($responseCode, $responseHeaders, $content);
    }

    /**
     * @throws HttpClientException
     */
    private function requestWithFsockopen(
        string  $method,
        string  $url,
        array   $requestHeaders = [],
        ?string $requestBody = null,
        array   $options = [],
        int     $redirects = 0
    ): HttpResponse {
        if ($redirects > $this->maxRedirects) {
            throw new HttpClientException('Too many redirects');
        }

        $connectTimeout = $options[self::CONNECT_TIMEOUT] ?? $this->timeout;
        $readTimeout    = $options[self::READ_TIMEOUT] ?? $this->timeout;

        $parsedUrl = parse_url($url);
        $scheme    = $parsedUrl['scheme'] ?? 'http';
        $host      = $parsedUrl['host'] ?? '';
        $port      = $parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80);
        $remote    = @fsockopen(($scheme === 'https' ? 'ssl://' : '') . $host, $port, $errno, $errstr, $connectTimeout);

        if (!$remote) {
            throw new HttpClientException($errstr ?: 'Connection failed', match (true) {
                $errno === 110 => HttpClientException::REASON_TIMEOUT,
                str_contains($errstr, 'getaddrinfo') => HttpClientException::REASON_HOST_RESOLVE_FAILURE,
                default => null
            });
        }

        stream_set_timeout($remote, $readTimeout);

        $path = ($parsedUrl['path'] ?? '/')
            . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '')
            . (isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '');

        $request = strtoupper($method) . ' ' . $path . " HTTP/1.0\r\n";
        $request .= "Host: $host\r\n";
        $request .= "User-Agent: {$this->userAgent}\r\n";
        $request .= "Connection: Close\r\n";

        foreach ($requestHeaders as $name => $value) {
            $request .= "$name: $value\r\n";
        }

        if ($requestBody !== null) {
            $request .= "Content-Length: " . \strlen($requestBody) . "\r\n";
            $request .= "\r\n" . $requestBody;
        } else {
            $request .= "\r\n";
        }

        fwrite($remote, $request);

        $content = stream_get_contents($remote);
        $meta    = stream_get_meta_data($remote);
        fclose($remote);

        if ($meta['timed_out']) {
            throw new HttpClientException('Read timed out', HttpClientException::REASON_TIMEOUT);
        }

        $contentStart = strpos($content, "\r\n\r\n");
        if ($contentStart !== false) {
            [$rawHeaders, $content] = explode("\r\n\r\n", $content, 2);
        } else {
            $rawHeaders = '';
        }
        $responseHeaders = explode("\r\n", $rawHeaders);

        if (preg_match('/^Location:\s*(.*)/mi', $rawHeaders, $matches)) {
            return $this->requestWithFsockopen($method, self::newUrlFromLocation(trim($matches[1]), $url), $requestHeaders, $requestBody, $options, $redirects + 1);
        }

        $responseCode = isset($responseHeaders[0]) && preg_match('/\d{3}/', $responseHeaders[0], $matches) ? (int)$matches[0] : 0;

        return $this->createResponse($responseCode, $responseHeaders, $content);
    }

    /**
     * @throws HttpClientException
     */
    private function requestWithFileGetContents(
        string  $method,
        string  $url,
        array   $requestHeaders,
        ?string $requestBody,
        array   $options = [],
    ): HttpResponse {

        // NOTE: it seems like file_get_contents does not support connection timeout
        $connectTimeout = $options[self::CONNECT_TIMEOUT] ?? $this->timeout;
        $readTimeout    = $options[self::READ_TIMEOUT] ?? $this->timeout;

        $headerLines = array_map(static fn($k, $v) => "$k: $v", array_keys($requestHeaders), $requestHeaders);
        $context     = stream_context_create([
            'http' => [
                'method'        => strtoupper($method),
                'header'        => implode("\r\n", $headerLines),
                'content'       => $requestBody ?? '',
                'user_agent'    => $this->userAgent,
                'max_redirects' => $this->maxRedirects + 1,
                'timeout'       => $readTimeout,
                'ignore_errors' => true,
            ]
        ]);

        $start   = microtime(true);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $errorMessage = error_get_last()['message'];
            throw new HttpClientException($errorMessage, match (true) {
                preg_match('/timed?[\s_-]?out/i', $errorMessage) === 1 => HttpClientException::REASON_TIMEOUT,
                str_contains($errorMessage, 'HTTP request failed') && (microtime(true) - $start >= $readTimeout) => HttpClientException::REASON_TIMEOUT,
                str_contains($errorMessage, 'getaddrinfo') => HttpClientException::REASON_HOST_RESOLVE_FAILURE,
                default => null
            });
        }

        $responseCode    = 0;
        $responseHeaders = [];
        foreach ($http_response_header as $value) {
            if (preg_match('#^HTTP/1.[01] (\d{3})#', $value, $matches)) {
                $responseCode    = (int)$matches[1];
                $responseHeaders = []; // Reset old headers from previous request
            }
            $responseHeaders[] = $value;
        }
        if ($responseCode >= 300 && $responseCode < 400) {
            throw new HttpClientException('Too many redirects');
        }

        return $this->createResponse($responseCode, $responseHeaders, $content);
    }

    private function createResponse(int $responseCode, array $headers, string $content): HttpResponse
    {
        return new HttpResponse(
            headers: $headers,
            statusCode: $responseCode,
            content: $content,
            error: $responseCode >= 400 ? "HTTP Error $responseCode" : null
        );
    }

    private function getPreferredTransport(): ?string
    {
        if ($this->preferredTransport !== null) {
            return $this->preferredTransport;
        }

        if (\function_exists('curl_init')) {
            return self::TRANSPORT_CURL;
        }

        if (\function_exists('fsockopen')) {
            return self::TRANSPORT_FSOCKOPEN;
        }

        if (\in_array(strtolower(\ini_get('allow_url_fopen')), ['on', 'true', '1'], true)) {
            return self::TRANSPORT_FILE_GET_CONTENTS;
        }

        return null;
    }
}
