<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\HttpClient;

readonly class HttpResponse
{
    public function __construct(
        public array   $headers = [],
        public int     $statusCode = 0,
        public ?string $content = null,
        public ?string $error = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
