<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

class SpamDetectorComment
{
    public function __construct(
        public string $name,
        public string $email,
        public string $text,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
