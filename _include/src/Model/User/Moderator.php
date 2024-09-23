<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model\User;

readonly class Moderator
{
    public function __construct(
        public string $login,
        public string $email,
    ) {
    }
}
