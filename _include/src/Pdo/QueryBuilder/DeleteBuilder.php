<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo\QueryBuilder;

use S2\Cms\Pdo\DbLayerException;

class DeleteBuilder
{
    use ParamsExecutableTrait;
    use WhereTrait;

    private ?string $table = null;

    public function __construct(
        private readonly DeleteCompilerInterface $compiler,
        private readonly QueryExecutorInterface  $queryExecutor,
    ) {
    }

    public function delete(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @throws DbLayerException
     */
    public function getTable(): string
    {
        if ($this->table === null) {
            throw new DbLayerException('No table to update has been specified.');
        }
        return $this->table;
    }
}
