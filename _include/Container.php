<?php
/**
 * Simple DI container to be used in legacy code.
 *
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */


use Katzgrau\KLogger\Logger;
use Psr\Log\LoggerInterface;
use S2\Rose\Extractor\ExtractorInterface;
use S2\Rose\Extractor\HtmlDom\DomExtractor;
use S2\Rose\Finder;
use S2\Rose\Indexer;
use S2\Rose\Stemmer\PorterStemmerEnglish;
use S2\Rose\Stemmer\PorterStemmerRussian;
use S2\Rose\Stemmer\StemmerInterface;
use S2\Rose\Storage\Database\PdoStorage;

class Container
{
    private static array $instances = [];

    public static function get(string $className): object
    {
        return self::$instances[$className] ?? (self::$instances[$className] = self::instantiate($className));
    }

    /** @noinspection PhpParamsInspection */
    private static function instantiate(string $className): object
    {
        global $db_host, $db_name, $db_username, $db_password, $db_prefix;

        switch ($className) {
            case \PDO::class:
                $pdo = new \S2\Core\Pdo\PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_username, $db_password);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                return $pdo;

            case PdoStorage::class:
                return new PdoStorage(self::get(\PDO::class), $db_prefix . 's2_search_idx_');

            case StemmerInterface::class:
                return new PorterStemmerRussian(new PorterStemmerEnglish());

            case Finder::class:
                return (new Finder(self::get(PdoStorage::class), self::get(StemmerInterface::class)))
                    ->setHighlightTemplate('<i class="s2_search_highlight">%s</i>')
                    ->setSnippetLineSeparator(' ⋄ ')
                ;

            case LoggerInterface::class:
                return new Logger(S2_CACHE_DIR);

            case ExtractorInterface::class:
                return new DomExtractor(self::get(LoggerInterface::class));

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
                    new \s2_extensions\s2_search\Fetcher(),
                    self::get(Indexer::class),
                    self::get(PdoStorage::class),
                    self::get(LoggerInterface::class)
                );
        }

        throw new InvalidArgumentException(sprintf('Unknown service "%s" requested.', $className));
    }
}
