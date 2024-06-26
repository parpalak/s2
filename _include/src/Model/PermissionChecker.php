<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

class PermissionChecker
{
    public const PERMISSION_VIEW            = 'view';
    public const PERMISSION_CREATE_ARTICLES = 'create_articles';
    public const PERMISSION_EDIT_SITE       = 'edit_site';
    public const PERMISSION_EDIT_USERS      = 'edit_users';

    private ?array $user = null;

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function isGranted(string $permission): bool
    {
        return (bool)($this->user[$permission] ?? false);
    }

    public function isGrantedAny(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->isGranted($permission)) {
                return true;
            }
        }
        return false;
    }

    public function getUserId(): ?int
    {
        return $this->user['id'] ?? null;
    }
}
