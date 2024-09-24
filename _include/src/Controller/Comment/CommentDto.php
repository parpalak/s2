<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller\Comment;

readonly class CommentDto
{
    public function __construct(
        public int    $id,
        public int    $targetId,
        public string $name,
        public string $email,
        public string $text,
    ) {
    }
}
