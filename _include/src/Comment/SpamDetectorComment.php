<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

class SpamDetectorComment
{
    public function __construct(
        public string  $name,
        public string  $email,
        public string  $text,
        public ?string $userAgent = null,
        public ?string $referrer = null,
        public ?string $permalink = null,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
