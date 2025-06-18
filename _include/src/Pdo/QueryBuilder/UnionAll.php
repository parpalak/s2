<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class UnionAll
{
    public array $selects;

    public function __construct(SelectBuilder ...$selects)
    {
        $this->selects = $selects;
    }
}
