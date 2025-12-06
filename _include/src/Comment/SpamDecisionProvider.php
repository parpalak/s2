<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

readonly class SpamDecisionProvider implements SpamDecisionProviderInterface
{
    public function __construct(private SpamDetectorInterface $detector)
    {
    }

    public function getVerdict(SpamDetectorComment $comment, string $clientIp): SpamDecision
    {
        $report    = $this->detector->getReport($comment, $clientIp);
        $linkCount = self::linkCount($comment->text);
        $hasHtml   = self::hasHtmlTags($comment->text);

        $rejectLinks     = $linkCount > 0 && !$report->isHam();
        $rejectSpam      = $report->isBlatant();
        $forceModeration = $report->isHam() && ($linkCount > 0 || $hasHtml);

        return new SpamDecision($report, $rejectLinks, $rejectSpam, $forceModeration);
    }

    private static function linkCount(string $text): int
    {
        return preg_match_all('#(https?://\S{2,}?)(?=[\s),\'><\]]|&lt;|&gt;|[.;:](?:\s|$)|$)#u', $text) ?: 0;
    }

    private static function hasHtmlTags(string $text): bool
    {
        return preg_match('#</?[a-z][^>]*>#i', $text) === 1;
    }
}
