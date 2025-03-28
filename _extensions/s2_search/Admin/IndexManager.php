<?php
/**
 * Creates search index
 *
 * @copyright 2010-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

namespace s2_extensions\s2_search\Admin;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use S2\Rose\Entity\Indexable;
use S2\Rose\Exception\RuntimeException;
use S2\Rose\Indexer;
use S2\Rose\Storage\Database\PdoStorage;
use s2_extensions\s2_search\Service\BulkIndexingProviderInterface;
use s2_extensions\s2_search\Service\RecommendationProvider;

class IndexManager
{
    private const FILE_PROCESS_STATE  = 's2_search_state.txt';
    private const FILE_BUFFER_CONTENT = 's2_search_buffer.txt';
    private const FILE_BUFFER_POINTER = 's2_search_pointer.txt';

    /**
     * @var BulkIndexingProviderInterface[]
     */
    private array $bulkIndexingProviders;

    public function __construct(
        private readonly string                 $cacheDir,
        private readonly Indexer                $indexer,
        private readonly PdoStorage             $pdoStorage,
        private readonly CacheItemPoolInterface $recommendationsCache,
        private readonly LoggerInterface        $logger,
        BulkIndexingProviderInterface           ...$bulkIndexingProviders,
    ) {
        $this->bulkIndexingProviders = $bulkIndexingProviders;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function index(): string
    {
        if (!is_file($this->cacheDir . self::FILE_PROCESS_STATE) || !($state = file_get_contents($this->cacheDir . self::FILE_PROCESS_STATE))) {
            $state = 'start';
        }

        if ($state === 'start') {
            // First stage: export all texts to buffer_name file

            $this->pdoStorage->erase();
            @unlink($this->cacheDir . self::FILE_BUFFER_CONTENT);
            @unlink($this->cacheDir . self::FILE_BUFFER_POINTER);
            @unlink($this->cacheDir . self::FILE_PROCESS_STATE);

            file_put_contents($this->cacheDir . self::FILE_BUFFER_POINTER, '0');

            file_put_contents($this->cacheDir . self::FILE_BUFFER_CONTENT, '');
            foreach ($this->bulkIndexingProviders as $bulkIndexingProvider) {
                foreach ($bulkIndexingProvider->getIndexables() as $indexable) {
                    file_put_contents($this->cacheDir . self::FILE_BUFFER_CONTENT, base64_encode(serialize($indexable)) . "\n", FILE_APPEND);
                }
            }

            file_put_contents($this->cacheDir . self::FILE_PROCESS_STATE, 'step');

            clearstatcache();

            $this->invalidateRecommendationsCache();

            return 'go_20';
        }

        if ($state === 'step') {
            // Second stage: go through all exported data and add to index
            $start = microtime(true);

            $bufferFilePointer = file_get_contents($this->cacheDir . self::FILE_BUFFER_POINTER);

            $bufferFile = fopen($this->cacheDir . self::FILE_BUFFER_CONTENT, 'rb');
            fseek($bufferFile, $bufferFilePointer);

            do {
                $data = fgets($bufferFile);

                if (!$data) {
                    // All indexed, no more data
                    fclose($bufferFile);
                    file_put_contents($this->cacheDir . self::FILE_BUFFER_CONTENT, '');
                    file_put_contents($this->cacheDir . self::FILE_BUFFER_POINTER, '');
                    file_put_contents($this->cacheDir . self::FILE_PROCESS_STATE, '');

                    $this->invalidateRecommendationsCache();

                    return 'stop';
                }

                $bufferFilePointer += strlen($data);

                $indexable = unserialize(base64_decode($data));
                if ($indexable instanceof Indexable) {
                    try {
                        $this->indexer->index($indexable);
                    } catch (RuntimeException $e) {
                        file_put_contents($this->cacheDir . self::FILE_PROCESS_STATE, '');
                        $this->logger->error($e->getMessage(), ['exception' => $e]);
                    }
                }
            } while ($start + 2.0 > microtime(true));

            fclose($bufferFile);
            file_put_contents($this->cacheDir . self::FILE_BUFFER_POINTER, $bufferFilePointer);

            $this->invalidateRecommendationsCache();

            return 'go_' . (20 + (int)(80.0 * $bufferFilePointer / filesize($this->cacheDir . self::FILE_BUFFER_CONTENT)));
        }

        file_put_contents($this->cacheDir . self::FILE_PROCESS_STATE, '');

        return 'unknown state';
    }

    /**
     * @throws InvalidArgumentException
     */
    private function invalidateRecommendationsCache(): void
    {
        $this->recommendationsCache->deleteItem(RecommendationProvider::INVALIDATED_AT);
    }
}
