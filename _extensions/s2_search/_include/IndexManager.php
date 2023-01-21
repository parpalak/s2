<?php
/**
 * Creates search index
 *
 * @copyright (C) 2010-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_search
 */

namespace s2_extensions\s2_search;

use Psr\Log\LoggerInterface;
use S2\Rose\Entity\Indexable;
use S2\Rose\Exception\RuntimeException;
use S2\Rose\Indexer;
use S2\Rose\Storage\Database\PdoStorage;

class IndexManager
{
    private const FILE_PROCESS_STATE  = 's2_search_state.txt';
    private const FILE_BUFFER_CONTENT = 's2_search_buffer.txt';
    private const FILE_BUFFER_POINTER = 's2_search_pointer.txt';

    private string $dir;
    private GenericFetcher $fetcher;
    private Indexer $indexer;
    private PdoStorage $pdoStorage;
    private LoggerInterface $logger;

    public function __construct(string $dir, GenericFetcher $fetcher, Indexer $indexer, PdoStorage $pdoStorage, LoggerInterface $logger)
    {
        $this->dir        = $dir;
        $this->fetcher    = $fetcher;
        $this->indexer    = $indexer;
        $this->pdoStorage = $pdoStorage;
        $this->logger     = $logger;
    }

    public function index(): string
    {
        if (!is_file($this->dir . self::FILE_PROCESS_STATE) || !($state = file_get_contents($this->dir . self::FILE_PROCESS_STATE))) {
            $state = 'start';
        }

        if ($state === 'start') {
            // First stage: export all texts to buffer_name file

            $this->pdoStorage->erase();
            @unlink($this->dir . self::FILE_BUFFER_CONTENT);
            @unlink($this->dir . self::FILE_BUFFER_POINTER);
            @unlink($this->dir . self::FILE_PROCESS_STATE);

            file_put_contents($this->dir . self::FILE_BUFFER_POINTER, '0');

            file_put_contents($this->dir . self::FILE_BUFFER_CONTENT, '');
            foreach ($this->fetcher->process() as $indexable) {
                file_put_contents($this->dir . self::FILE_BUFFER_CONTENT, base64_encode(serialize($indexable)) . "\n", FILE_APPEND);
            }

            file_put_contents($this->dir . self::FILE_PROCESS_STATE, 'step');

            clearstatcache();

            return 'go_20';
        }

        if ($state === 'step') {
            // Second stage: go through all exported data and add to index
            $start = microtime(true);

            $bufferFilePointer = file_get_contents($this->dir . self::FILE_BUFFER_POINTER);

            $bufferFile = fopen($this->dir . self::FILE_BUFFER_CONTENT, 'rb');
            fseek($bufferFile, $bufferFilePointer);

            do {
                $data = fgets($bufferFile);

                if (!$data) {
                    // All indexed, no more data
                    fclose($bufferFile);
                    file_put_contents($this->dir . self::FILE_BUFFER_CONTENT, '');
                    file_put_contents($this->dir . self::FILE_BUFFER_POINTER, '');
                    file_put_contents($this->dir . self::FILE_PROCESS_STATE, '');
                    return 'stop';
                }

                $bufferFilePointer += strlen($data);

                $indexable = unserialize(base64_decode($data));
                if ($indexable instanceof Indexable) {
                    try {
                        $this->indexer->index($indexable);
                    } catch (RuntimeException $e) {
                        file_put_contents($this->dir . self::FILE_PROCESS_STATE, '');
                        $this->logger->error($e->getMessage(), ['exception' => $e]);
                    }
                }
            } while ($start + 4.0 > microtime(1));

            fclose($bufferFile);
            file_put_contents($this->dir . self::FILE_BUFFER_POINTER, $bufferFilePointer);

            return 'go_' . (20 + (int)(80.0 * $bufferFilePointer / filesize($this->dir . self::FILE_BUFFER_CONTENT)));
        }

        file_put_contents($this->dir . self::FILE_PROCESS_STATE, '');

        return 'unknown state';
    }

    public function refresh($chapter): void
    {
        $indexable = $this->fetcher->chapter($chapter);
        if ($indexable !== null) {
            $this->indexer->removeById($chapter, null);
            $this->indexer->index($indexable);
        }
    }
}
