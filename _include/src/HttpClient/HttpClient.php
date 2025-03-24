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
    public const TRANSPORT_CURL              = 'curl';
    public const TRANSPORT_FSOCKOPEN         = 'fsockopen';
    public const TRANSPORT_FILE_GET_CONTENTS = 'file_get_contents';

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
    public function fetch(string $url, bool $headOnly = false): HttpResponse
    {
        $url = $this->normalizeUrl($url);

        return match ($this->getPreferredTransport()) {
            self::TRANSPORT_CURL => $this->fetchWithCurl($url, $headOnly),
            self::TRANSPORT_FSOCKOPEN => $this->fetchWithFsockopen($url, $headOnly),
            self::TRANSPORT_FILE_GET_CONTENTS => $this->fetchWithFileGetContents($url, $headOnly),
            default => throw new HttpClientException('No available method to fetch the URL'),
        };
    }

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

    private function fetchWithCurl(string $url, bool $headOnly, int $redirects = 0): HttpResponse
    {
        if ($redirects > $this->maxRedirects) {
            throw new HttpClientException('Too many redirects');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, $headOnly);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            /** @noinspection CurlSslServerSpoofingInspection */
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        }

        $content      = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($content === false || $error !== null) {
            throw new HttpClientException($error ?? 'Unknown error');
        }

        $contentStart = strpos($content, "\r\n\r\n");
        if ($contentStart !== false) {
            [$rawHeaders, $content] = explode("\r\n\r\n", $content, 2);
        } else {
            $rawHeaders = '';
        }
        $headers = explode("\r\n", $rawHeaders);

        if (preg_match('/^Location:\s*(.*)/mi', $rawHeaders, $matches)) {
            return $this->fetchWithCurl(self::newUrlFromLocation(trim($matches[1]), $url), $headOnly, $redirects + 1);
        }

        return $this->createResponse($responseCode, $headers, $content);
    }

    private function fetchWithFsockopen(string $url, bool $headOnly, int $redirects = 0): HttpResponse
    {
        if ($redirects > $this->maxRedirects) {
            throw new HttpClientException('Too many redirects');
        }

        $parsedUrl = parse_url($url);
        $port      = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80);
        $hostname  = ($parsedUrl['scheme'] === 'https' ? 'ssl://' : '') . $parsedUrl['host'];
        $remote    = @fsockopen($hostname, $port, $errno, $errstr, $this->timeout);

        if (!$remote) {
            throw new HttpClientException($errstr);
        }

        $requestUri = ($parsedUrl['path'] ?? '/')
            . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '')
            . (isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '');

        $request = ($headOnly ? 'HEAD' : 'GET') . ' ' . $requestUri . " HTTP/1.0\r\n";
        $request .= 'Host: ' . $parsedUrl['host'] . "\r\n";
        $request .= 'User-Agent: ' . $this->userAgent . "\r\n";
        $request .= 'Connection: Close' . "\r\n\r\n";
        fwrite($remote, $request);

        $content = stream_get_contents($remote);
        fclose($remote);

        $contentStart = strpos($content, "\r\n\r\n");
        if ($contentStart !== false) {
            [$rawHeaders, $content] = explode("\r\n\r\n", $content, 2);
        } else {
            $rawHeaders = '';
        }
        $headers = explode("\r\n", $rawHeaders);

        if (preg_match('/^Location:\s*(.*)/mi', $rawHeaders, $matches)) {
            return $this->fetchWithFsockopen(self::newUrlFromLocation(trim($matches[1]), $url), $headOnly, $redirects + 1);
        }

        $responseCode = isset($headers[0]) && preg_match('/\d{3}/', $headers[0], $matches) ? (int)$matches[0] : 0;

        return $this->createResponse($responseCode, $headers, $content, null);
    }

    private function fetchWithFileGetContents(string $url, bool $headOnly): HttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'method'        => $headOnly ? 'HEAD' : 'GET',
                'user_agent'    => $this->userAgent,
                'max_redirects' => $this->maxRedirects + 1,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            throw new HttpClientException(error_get_last()['message']);
        }

        $responseCode = 0;
        $headers      = [];
        foreach ($http_response_header as $value) {
            if (preg_match('#^HTTP/1.[01] (\d{3})#', $value, $matches)) {
                $responseCode = (int)$matches[1];
                $headers      = []; // Reset old headers from previous request
            }
            $headers[] = $value;
        }
        if ($responseCode >= 300 && $responseCode < 400) {
            throw new HttpClientException('Too many redirects');
        }

        return $this->createResponse($responseCode, $headers, $content);
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
