<?php
/**
 * Database layer exception
 *
 * @copyright (C) 2014-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class DbLayerException extends \Exception
{
    protected string $failedQuery = '';

    public function __construct(string $message = '', int $code = 0, string $query = '', ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->failedQuery = $query;
    }

    public function getQuery(): string
    {
        return $this->failedQuery;
    }
}
