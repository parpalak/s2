<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

readonly class QueryResult
{
    public function __construct(private \PDOStatement $pdoStatement)
    {
    }

    public function fetchAssocAll(): array
    {
        return $this->pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function result($row = 0, $col = 0): mixed
    {
        for ($i = $row; $i--;) {
            $curRow = $this->pdoStatement->fetch();
            if ($curRow === false) {
                return false;
            }
        }

        $curRow = $this->pdoStatement->fetch();
        if ($curRow === false) {
            return false;
        }

        return $curRow[$col] ?? false;
    }

    public function fetchAssoc(): array|false
    {
        return $this->pdoStatement->fetch(\PDO::FETCH_ASSOC);
    }


    public function fetchRow(): array|false
    {
        return $this->pdoStatement->fetch(\PDO::FETCH_NUM);
    }

    public function fetchColumn(): array
    {
        return $this->pdoStatement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function affectedRows(): int
    {
        return $this->pdoStatement->rowCount();
    }

    public function freeResult(): true
    {
        $this->pdoStatement->closeCursor();
        return true;
    }

    public function fetchKeyPair(): array
    {
        return $this->pdoStatement->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    }
}
