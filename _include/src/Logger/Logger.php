<?php
/**
 * PSR logger.
 * Forked to include previous exceptions and to make exception traces more pretty.
 *
 * @copyright (C) 2024 Roman Parpalak, partially based on code (C) 2017 Mark Rogoyski
 * @license MIT
 * @see https://github.com/markrogoyski/simplelog-php
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * Simple Logger
 * Powerful PSR-3 logging so easy it's simple!
 *
 * Implements PHP Standard Recommendation interface: PSR-3 \Psr\Log\LoggerInterface
 *
 * Log the following severities: debug, info, notice, warning, error, critical, alert, emergency.
 * Log format: YYYY-mm-dd HH:ii:ss.uuuuuu  [loglevel]  [channel]  Log message content  {"Optional":"JSON Contextual Support Data"}  {"Optional":"Exception Data"}
 *
 * Standard usage - default options:
 *   $logger = new SimpleLog\Logger('logfile.log', 'channelname');
 *   $logger->info('Normal informational event happened.');
 *   $logger->error('Something bad happened.', ['key1' => 'value that gives context', 'key2' => 'some more context', 'exception' => $e]);
 *
 * Optional constructor option: Set default lowest log level (Example error and above):
 *   $logger = new SimpleLog\Logger('logfile.log', 'channelname', \Psr\Log\LogLevel::ERROR);
 *   $logger->error('This will get logged');
 *   $logger->info('This is below the minimum log level and will not get logged');
 *
 * To log an exception, set as data context array key 'exception'
 *   $logger->error('Something exceptional happened.', ['exception' => $e]);
 *
 * To set output to standard out (STDOUT) as well as a log file:
 *   $logger->setOutput(true);
 *
 * To change the channel after construction:
 *   $logger->setChannel('newname')
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * Lowest log level to log.
     */
    private int $logLevel;

    /**
     * Whether to log to standard out.
     */
    private bool $stdout = false;

    /**
     * Log fields separated by tabs to form a TSV (CSV with tabs).
     */
    private const TAB = "\t";

    /**
     * Special minimum log level which will not log any log levels.
     */
    public const LOG_LEVEL_NONE = 'none';

    /**
     * Log level hierarchy
     */
    public const LEVELS = [
        self::LOG_LEVEL_NONE => -1,
        LogLevel::DEBUG      => 0,
        LogLevel::INFO       => 1,
        LogLevel::NOTICE     => 2,
        LogLevel::WARNING    => 3,
        LogLevel::ERROR      => 4,
        LogLevel::CRITICAL   => 5,
        LogLevel::ALERT      => 6,
        LogLevel::EMERGENCY  => 7,
    ];

    /**
     * @param string $log_file File name and path of log file.
     * @param string $channel Logger channel ("namespace") associated with this logger.
     * @param string $logLevel (optional) Lowest log level to log.
     */
    public function __construct(
        private readonly string $log_file,
        private string          $channel,
        string                  $logLevel = LogLevel::DEBUG
    ) {
        $this->setLogLevel($logLevel);
    }

    /**
     * Set the lowest log level to log.
     */
    public function setLogLevel(string $logLevel): void
    {
        if (!isset(self::LEVELS[$logLevel])) {
            throw new \DomainException("Log level $logLevel is not a valid log level. Must be one of (" . implode(', ', array_keys(self::LEVELS)) . ')');
        }

        $this->logLevel = self::LEVELS[$logLevel];
    }

    /**
     * Set the log channel which identifies the log line.
     */
    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * Set the standard out option on or off.
     * If set to true, log lines will also be printed to standard out.
     */
    public function setOutput(bool $stdout): void
    {
        $this->stdout = $stdout;
    }

    /**
     * Log a message.
     * Generic log routine that all severity levels use to log an event.
     *
     * @throws \RuntimeException when log file cannot be opened for writing.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$this->logAtThisLevel($level)) {
            return;
        }

        // Build log line
        /** @var string $formattedException */
        [$formattedException, $contextData] = $this->handleException($context);
        try {
            $formattedContext = json_encode($contextData, JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            $formattedContext = '{}';
        }
        $logLine = $this->formatLogLine($level, $message, $formattedContext, $formattedException);

        // Log to file
        try {
            $fh = fopen($this->log_file, 'ab');
            if ($fh === false) {
                throw new \RuntimeException('fopen failed');
            }
            fwrite($fh, $logLine);
            fclose($fh);
        } catch (\Throwable $e) {
            /** @noinspection ForgottenDebugOutputInspection */
            error_log("Could not open log file {$this->log_file} for writing to log channel {$this->channel}!");
        }

        // Log to stdout if option set to do so.
        if ($this->stdout) {
            print($logLine);
        }
    }

    /**
     * Determine if the logger should log at a certain log level.
     *
     * @return bool True if we log at this level; false otherwise.
     */
    private function logAtThisLevel(string $level): bool
    {
        return self::LEVELS[$level] >= $this->logLevel;
    }

    /**
     * Handle an exception in the data context array.
     * If an exception is included in the data context array, extract it.
     *
     * @param mixed[] $context
     *
     * @return mixed[]  [exception, data (without exception)]
     */
    private function handleException(array $context): array
    {
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception      = $context['exception'];
            $exception_data = self::buildExceptionData($exception);
            unset($context['exception']);
        } else {
            $exception_data = '';
        }

        return [$exception_data, $context];
    }

    /**
     * Build the exception log data.
     *
     * @param \Throwable $e
     *
     * @return string JSON {message, code, file, line, trace}
     */
    private static function buildExceptionData(\Throwable $e): string
    {
        try {
            $str = json_encode([
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ], JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . \PHP_EOL .
                '#0 ' . $e->getFile() . ':' . $e->getLine() . \PHP_EOL .
                self::formatTrace($e->getTrace());

            if ($e->getPrevious() !== null) {
                $str .= \PHP_EOL . 'Previous Exception: ' . self::buildExceptionData($e->getPrevious());
            }
            return $str;
        } catch (\JsonException $e) {
            return '{"message":"' . $e->getMessage() . '"}';
        }
    }

    private static function formatTrace(array $trace): string
    {
        $stack = '';
        $i     = 1;
        foreach ($trace as $node) {
            $stack .= "#$i " . $node['file'] . ":" . $node['line'] . " ";
            if (isset($node['class'])) {
                $stack .= $node['class'] . "->";
            }
            $stack .= $node['function'] . "()" . PHP_EOL;
            $i++;
        }
        return $stack;
    }

    /**
     * Format the log line.
     * ```
     * YYYY-mm-dd HH:ii:ss.uuuuuu  [loglevel]  [channel]  Log message content  {"Optional":"JSON Contextual Support Data"}  {"Optional":"Exception Data"}
     * Exception Trace if any
     * ```
     */
    private function formatLogLine(string $level, string $message, string $data, string $formattedException): string
    {
        return
            $this->getTime() . self::TAB .
            "[$level]" . self::TAB .
            "[{$this->channel}]" . self::TAB .
            str_replace(\PHP_EOL, '   ', trim($message)) . self::TAB .
            str_replace(\PHP_EOL, '   ', $data) . self::TAB .
            $formattedException . \PHP_EOL;
    }

    /**
     * Get current date time, with microsecond precision.
     * Format: YYYY-mm-dd HH:ii:ss.uuuuuu
     *
     * date('...') does not support microseconds (u)
     */
    private function getTime(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
    }
}
