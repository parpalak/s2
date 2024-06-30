<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

readonly class Signal
{
    public function __construct(public string $text, public string $title, public string $url)
    {
    }

    public static function createEmpty(string $title): Signal
    {
        return new self('', $title, '');
    }

    public function isEmpty(): bool
    {
        return $this->text === '' && $this->url === '';
    }
}
