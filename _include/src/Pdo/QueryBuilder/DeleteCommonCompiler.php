<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

readonly class DeleteCommonCompiler implements DeleteCompilerInterface
{
    public function __construct(private string $prefix)
    {
    }

    public function getSql(DeleteBuilder $builder): string
    {
        $sql = \sprintf('DELETE FROM %s%s', $this->prefix, $builder->getTable());

        $whereConditions = $builder->getWhere();
        if (\count($whereConditions) > 0) {
            $sql .= ' WHERE (' . implode(') AND (', $whereConditions). ')';
        }

        return $sql;
    }
}
