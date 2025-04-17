<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

use Psr\Log\LoggerInterface;
use S2\Cms\HttpClient\HttpClient;
use S2\Cms\HttpClient\HttpClientException;
use S2\Cms\Model\UrlBuilder;

readonly class AkismetProxy implements SpamDetectorInterface
{
    private const SERVICE_ENDPOINT = "https://rest.akismet.com/1.1/comment-check";
    private const TYPE_COMMENT     = 'comment';

    public function __construct(
        private HttpClient      $httpClient,
        private UrlBuilder      $urlBuilder,
        private LoggerInterface $logger,
        private string          $apiKey,
    ) {
    }

    public function getReport(SpamDetectorComment $comment, string $clientIp): SpamDetectorReport
    {
        if ($this->apiKey === '') {
            return SpamDetectorReport::disabled();
        }

        $data = [
            'api_key'              => $this->apiKey,
            'blog'                 => $this->urlBuilder->rawAbsLink('/'),
            'user_ip'              => $clientIp,
            // NOTE: try to add this in case of mistakes
            // 'user_agent'           => $request->headers->get('User-Agent'),
            // 'referrer'             => $request->headers->get('Referer'),
            'comment_type'         => self::TYPE_COMMENT,
            'comment_author'       => $comment->name,
            'comment_author_email' => $comment->email,
            'comment_content'      => $comment->text,
        ];

        $this->logger->info('Sending comment to Akismet', $comment->toArray());
        try {
            $response = $this->httpClient->post(self::SERVICE_ENDPOINT, $data, [
                HttpClient::CONNECT_TIMEOUT => 2,
                HttpClient::READ_TIMEOUT    => 2,
            ]);
        } catch (HttpClientException $e) {
            $this->logger->error(\sprintf('Error requesting Akismet: %s', $e->getMessage()), ['exception' => $e]);

            return SpamDetectorReport::failed();
        }
        $this->logger->info('Akismet response', [
            'headers' => $response->headers,
            'body'    => $response->content,
        ]);

        if ($response->isSuccessful()) {
            if (trim($response->content) === 'true') {
                return $response->getHeader('X-akismet-pro-tip') === 'discard'
                    ? SpamDetectorReport::blatant()
                    : SpamDetectorReport::spam();
            }
            if (trim($response->content) === 'false') {
                return SpamDetectorReport::ham();
            }
        }

        return SpamDetectorReport::failed();
    }
}
