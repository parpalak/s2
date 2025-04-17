<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\HttpClient;

class HttpClientException extends \RuntimeException
{
    public const REASON_TIMEOUT              = 'timeout';
    public const REASON_HOST_RESOLVE_FAILURE = 'host_resolve_failure';

    public function __construct(string $message = '', public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }
}
