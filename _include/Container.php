<?php
/**
 * Simple DI container to be used in legacy code.
 *
 * @copyright (C) 2023-2024 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Layout\LayoutMatcherFactory;
use S2\Cms\Logger\Logger;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerPostgres;
use S2\Cms\Pdo\DbLayerSqlite;
use S2\Cms\Queue\QueueConsumer;
use S2\Cms\Queue\QueuePublisher;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Finder;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\PorterStemmerEnglish;
use S2\Rose\Stemmer\PorterStemmerRussian;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Container
{
    private static array $instances = [];

    public static function get(string $className): object
    {
        return self::$instances[$className] ?? (self::$instances[$className] = self::instantiate($className));
    }

    public static function getIfInstantiated(string $className): ?object
    {
        return self::$instances[$className] ?? null;
    }

    /** @noinspection PhpParamsInspection */
    private static function instantiate(string $className): object
    {
        global $db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix, $p_connect;

        switch ($className) {
            case DbLayer::class:
                return match ($db_type) {
                    'mysql' => new DbLayer(self::get(\PDO::class), $db_prefix),
                    'sqlite' => new DbLayerSqlite(self::get(\PDO::class), $db_prefix),
                    'pgsql' => new DbLayerPostgres(self::get(\PDO::class), $db_prefix),
                    default => throw new RuntimeException(sprintf('Unsupported db_type="%s"', $db_type)),
                };

            case \PDO::class:
                if (!class_exists(\PDO::class)) {
                    throw new RuntimeException('This PHP environment does not have PDO support built in. PDO support is required. Consult the PHP documentation for further assistance.');
                }

                if (!is_string($db_type)) {
                    throw new RuntimeException('$db_type must be a string.');
                }

                if (!in_array($db_type, PDO::getAvailableDrivers(), true)) {
                    throw new RuntimeException('This PHP environment does not have PDO "%s" support built in. It is required if you want to use this type of database. Consult the PHP documentation for further assistance.');
                }

                return match ($db_type) {
                    'mysql' => new \S2\Cms\Pdo\PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_username, $db_password),
                    'sqlite' => self::createPdoForSqlite($db_name, $p_connect),
                    'pgsql' => new \S2\Cms\Pdo\PDO("pgsql:host=$db_host;dbname=$db_name", $db_username, $db_password),
                    default => throw new RuntimeException(sprintf('Unsupported db_type="%s"', $db_type)),
                };

            case PdoStorage::class:
                return new PdoStorage(self::get(\PDO::class), $db_prefix . 's2_search_idx_');

            case StemmerInterface::class:
                return new PorterStemmerRussian(new PorterStemmerEnglish());

            case Finder::class:
                return (new Finder(self::get(PdoStorage::class), self::get(StemmerInterface::class)))
                    ->setHighlightTemplate('<span class="s2_search_highlight">%s</span>')
                    ->setSnippetLineSeparator(' ⋄&nbsp;')
                ;

            case ThumbnailGenerator::class:
                return new ThumbnailGenerator(
                    self::get(QueuePublisher::class),
                    S2_PATH . '/' . S2_IMG_DIR,
                    S2_IMG_PATH
                );

            case LoggerInterface::class:
                return new Logger((defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR) . 'app.log', 'app',  LogLevel::INFO);

            case 'recommendations_logger':
                return new Logger((defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR) . 'recommendations.log', 'recommendations',  LogLevel::INFO);

            case 'recommendations_cache':
                return new FilesystemAdapter('recommendations', 0, S2_CACHE_DIR);

            case QueuePublisher::class:
                return new QueuePublisher(self::get(\PDO::class));

            case QueueConsumer::class:
                return new QueueConsumer(
                    self::get(\PDO::class),
                    self::get(LoggerInterface::class),
                    self::get(RecommendationProvider::class),
                    self::get(ThumbnailGenerator::class)
                );

            case RecommendationProvider::class:
                return new RecommendationProvider(
                    self::get(PdoStorage::class),
                    LayoutMatcherFactory::getFourColumns(self::get('recommendations_logger')),
                    self::get('recommendations_cache'),
                    self::get(QueuePublisher::class)
                );

            case ExtractorInterface::class:
                return new \S2\Cms\Rose\CustomExtractor(self::get(LoggerInterface::class));

            case Indexer::class:
                return new Indexer(
                    self::get(PdoStorage::class),
                    self::get(StemmerInterface::class),
                    self::get(ExtractorInterface::class),
                    self::get(LoggerInterface::class),
                );

            case \s2_extensions\s2_search\IndexManager::class:
                return new \s2_extensions\s2_search\IndexManager(
                    S2_CACHE_DIR,
                    new \s2_extensions\s2_search\Fetcher(self::get(DbLayer::class)),
                    self::get(Indexer::class),
                    self::get(PdoStorage::class),
                    self::get('recommendations_cache'),
                    self::get(LoggerInterface::class)
                );
        }

        throw new InvalidArgumentException(sprintf('Unknown service "%s" requested.', $className));
    }

    private static function createPdoForSqlite(string $dbName, bool $persistentConnection): \S2\Cms\Pdo\PDO
    {
        if (!defined('S2_ROOT')) {
            throw new LogicException('S2_ROOT constant must be defined.');
        }

        $dbFilename = S2_ROOT . $dbName;

        if (!file_exists($dbFilename)) {
            @touch($dbFilename);
            @chmod($dbFilename, 0666);
            if (!file_exists($dbFilename)) {
                throw new RuntimeException('Unable to create new database file \'' . $dbFilename . '\'. Permission denied. Please allow write permissions for the \'' . dirname($dbFilename) . '\' directory.');
            }
        }

        if (!is_readable($dbFilename)) {
            throw new RuntimeException('Unable to open database \'' . $dbFilename . '\' for reading. Permission denied');
        }

        if (!is_writable($dbFilename)) {
            throw new RuntimeException('Unable to open database \'' . $dbFilename . '\' for writing. Permission denied');
        }

        if (!is_writable(dirname($dbFilename))) {
            throw new RuntimeException('Unable to write files in the \'' . dirname($dbFilename) . '\' directory. Permission denied');
        }

        if ($persistentConnection) {
            return new \S2\Cms\Pdo\PDO('sqlite:' . $dbFilename, "", "", [PDO::ATTR_PERSISTENT => true]);
        }
        return new \S2\Cms\Pdo\PDO('sqlite:' . $dbFilename);
    }
}
