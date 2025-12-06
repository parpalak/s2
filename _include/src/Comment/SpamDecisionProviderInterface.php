<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

interface SpamDecisionProviderInterface
{
    public function getVerdict(SpamDetectorComment $comment, string $clientIp): SpamDecision;
}
