<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

trait JoinTrait
{
    public const JOIN_TYPE_INNER = 'inner';
    public const JOIN_TYPE_LEFT  = 'left';

    /**
     * @var array{type: string, table: string, condition: string}[]
     */
    private array $joins = [];

    public function innerJoin(string $table, string $condition): self
    {
        $this->joins[] = [
            'type'      => self::JOIN_TYPE_INNER,
            'table'     => $table,
            'condition' => $condition,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $condition): self
    {
        $this->joins[] = [
            'type'      => self::JOIN_TYPE_LEFT,
            'table'     => $table,
            'condition' => $condition,
        ];

        return $this;
    }

    /**
     * @return array{type: string, table: string, condition: string}[]
     */
    public function getJoins(): array
    {
        return $this->joins;
    }
}
