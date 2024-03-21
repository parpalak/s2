<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Pdo;

class PdoSqliteFactory
{
    public static function create(string $dbFilename, bool $persistentConnection): PDO
    {
        if (!file_exists($dbFilename)) {
            @touch($dbFilename);
            @chmod($dbFilename, 0666);
            if (!file_exists($dbFilename)) {
                throw new \RuntimeException('Unable to create new database file \'' . $dbFilename . '\'. Permission denied. Please allow write permissions for the \'' . \dirname($dbFilename) . '\' directory.');
            }
        }

        if (!is_readable($dbFilename)) {
            throw new \RuntimeException('Unable to open database \'' . $dbFilename . '\' for reading. Permission denied');
        }

        if (!is_writable($dbFilename)) {
            throw new \RuntimeException('Unable to open database \'' . $dbFilename . '\' for writing. Permission denied');
        }

        if (!is_writable(\dirname($dbFilename))) {
            throw new \RuntimeException('Unable to write files in the \'' . \dirname($dbFilename) . '\' directory. Permission denied');
        }

        if ($persistentConnection) {
            return new PDO('sqlite:' . $dbFilename, "", "", [\PDO::ATTR_PERSISTENT => true]);
        }
        return new PDO('sqlite:' . $dbFilename);
    }
}
