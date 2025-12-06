<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

class SpamDetectorReport
{
    public const STATUS_FAILED   = 'failed'; // API call to a spam detection service failed
    public const STATUS_DISABLED = 'disabled'; // Spam detection service is disabled in the config
    public const STATUS_HAM      = 'ham'; // The comment is not spam
    public const STATUS_SPAM     = 'spam'; // The comment is spam
    public const STATUS_BLATANT  = 'blatant'; // The comment is blatant spam that can be safely dropped

    private function __construct(public string $status)
    {
        if (!\in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_DISABLED,
            self::STATUS_HAM,
            self::STATUS_SPAM,
            self::STATUS_BLATANT,
        ])) {
            throw new \InvalidArgumentException(\sprintf('Unknown status "%s"', $this->status));
        }
    }

    public static function failed(): self
    {
        return new self(self::STATUS_FAILED);
    }

    public static function ham(): self
    {
        return new self(self::STATUS_HAM);

    }

    public static function disabled(): self
    {
        return new self(self::STATUS_DISABLED);
    }

    public static function spam(): self
    {
        return new self(self::STATUS_SPAM);
    }

    public static function blatant(): self
    {
        return new self(self::STATUS_BLATANT);
    }

    public function isBlatant(): bool
    {
        return $this->status === self::STATUS_BLATANT;
    }

    public function isHam(): bool
    {
        return $this->status === self::STATUS_HAM;
    }

    public function isSpam(): bool
    {
        return $this->status === self::STATUS_SPAM;
    }
}
