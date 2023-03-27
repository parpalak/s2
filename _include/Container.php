<?php
/**
 * Simple DI container to be used in legacy code.
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use johnykvsky\Utils\JKLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use S2\Cms\Image\ThumbnailGenerator;
use S2\Cms\Layout\LayoutMatcherFactory;
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
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

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
            case 'db':
                try {
                    $s2_db = DBLayer_Abstract::getInstance($db_type, $db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
                } catch (Exception $e) {
                    error($e->getMessage(), $e->getFile(), $e->getLine());
                }
                return $s2_db;

            case \PDO::class:
                // TODO use $db_type
                return new \S2\Cms\Pdo\PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_username, $db_password);

            case PdoStorage::class:
                return new PdoStorage(self::get(\PDO::class), $db_prefix . 's2_search_idx_');

            case StemmerInterface::class:
                return new PorterStemmerRussian(new PorterStemmerEnglish());

            case Finder::class:
                return (new Finder(self::get(PdoStorage::class), self::get(StemmerInterface::class)))
                    ->setHighlightTemplate('<i class="s2_search_highlight">%s</i>')
                    ->setSnippetLineSeparator(' ⋄&nbsp;')
                ;

            case ThumbnailGenerator::class:
                return new ThumbnailGenerator(
                    self::get(QueuePublisher::class),
                    S2_PATH . '/' . S2_IMG_DIR,
                    S2_IMG_PATH
                );

            case LoggerInterface::class:
                return new JKLogger(defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR, LogLevel::INFO, ['prefix' => 'log_', 'extension' => 'log']);

            case 'recommendations_logger':
                return new JKLogger(defined('S2_LOG_DIR') ? S2_LOG_DIR : S2_CACHE_DIR, LogLevel::DEBUG, ['prefix' => 'recommendations_', 'extension' => 'log']);

            case 'recommendations_cache':
                return new FilesystemTagAwareAdapter('recommendations', 0, S2_CACHE_DIR);

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
                    new \s2_extensions\s2_search\Fetcher(self::get('db')),
                    self::get(Indexer::class),
                    self::get(PdoStorage::class),
                    self::get('recommendations_cache'),
                    self::get(LoggerInterface::class)
                );
        }

        throw new InvalidArgumentException(sprintf('Unknown service "%s" requested.', $className));
    }
}
