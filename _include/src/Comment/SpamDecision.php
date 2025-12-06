<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

readonly class SpamDecision
{
    public static function empty(): self
    {
        return new self(SpamDetectorReport::disabled(), false, false, false);
    }

    public function __construct(
        private SpamDetectorReport $report,
        private bool               $rejectLinks,
        private bool               $rejectSpam,
        private bool               $forceModeration,
    ) {
    }

    public function shouldRejectLinks(): bool
    {
        return $this->rejectLinks;
    }

    public function shouldRejectAsSpam(): bool
    {
        return $this->rejectSpam;
    }

    public function shouldModerate(bool $premoderationEnabled): bool
    {
        if ($this->forceModeration) {
            return true;
        }

        return match ($this->report->status) {
            SpamDetectorReport::STATUS_FAILED,
            SpamDetectorReport::STATUS_DISABLED => $premoderationEnabled,
            SpamDetectorReport::STATUS_HAM => false,
            default => true,
        };
    }

    public function getReport(): SpamDetectorReport
    {
        return $this->report;
    }

    public function getStatus(): string
    {
        return $this->report->status;
    }
}
