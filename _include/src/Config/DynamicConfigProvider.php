<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Config;

use Psr\Cache\InvalidArgumentException;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Pdo\DbLayer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DynamicConfigProvider implements StatefulServiceInterface
{
    private const DYNAMIC_CONFIG_CACHE_KEY = 'dynamic_config';
    private ?array $params = null;

    public function __construct(
        private readonly DbLayer        $dbLayer,
        private readonly CacheInterface $cache,
        private readonly string         $cacheDir,
    ) {

    }

    public function clearState(): void
    {
        $this->params = null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $paramName): mixed
    {
        if ($this->params === null) {
            $this->params = $this->loadParams();
        }

        if (!isset($this->params[$paramName])) {
            throw new \LogicException(sprintf('Param "%s" does not exist.', $paramName));
        }

        return $this->params[$paramName];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function regenerate(): void
    {
        $this->cache->delete(self::DYNAMIC_CONFIG_CACHE_KEY);
        $this->loadParams();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function loadParams(): mixed
    {
        return $this->cache->get(self::DYNAMIC_CONFIG_CACHE_KEY, function (ItemInterface $item) {
            // Get the config from the DB
            $query = [
                'SELECT' => 'c.*',
                'FROM'   => 'config AS c'
            ];

            $statement = $this->dbLayer->buildAndQuery($query);

            $result = [];

            $legacyConfigOutput = '';
            while ($row = $this->dbLayer->fetchRow($statement)) {
                $legacyConfigOutput .= 'define(\'' . $row[0] . '\', \'' . str_replace(array('\\', '\''), array('\\\\', '\\\''), $row[1]) . '\');' . "\n";
                $result[$row[0]]    = $row[1];
            }

            // Deprecated. Remove when all values are accessed through this class, not global constants.
            try {
                s2_overwrite_file_skip_locked($this->cacheDir . 'cache_config.php', '<?php' . "\n\n" . 'define(\'S2_CONFIG_LOADED\', 1);' . "\n\n" . $legacyConfigOutput . "\n");
            } catch (\RuntimeException $e) {
                error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \'' . $this->cacheDir . '\'.', __FILE__, __LINE__);
            }

            return $result;
        });
    }
}
