<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller\Comment;

use Symfony\Component\HttpFoundation\Request;

interface CommentStrategyInterface
{
    /**
     * @return TargetDto|null Info about the entity to be commented
     */
    public function getTargetByRequest(Request $request): ?TargetDto;

    public function getTargetById(int $targetId): ?TargetDto;

    public function save(int $targetId, string $name, string $email, bool $showEmail, bool $subscribed, string $text, string $ip): int;

    public function notifySubscribers(int $commentId): void;

    public function getHashForPublishedComment(int $targetId): ?string;

    public function getRecentComment(string $hash, string $ip): ?CommentDto;

    public function publishComment(int $commentId);

    public function unsubscribe(int $targetId, string $email, string $code): bool;
}
