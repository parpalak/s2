<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2
 */

declare(strict_types=1);

namespace S2\Cms\Comment;

interface SpamDetectorInterface
{
    public function getReport(SpamDetectorComment $comment, string $clientIp): SpamDetectorReport;
}
