<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

use S2\AdminYard\SettingStorage\SettingStorageInterface;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

class UserSettingStorage implements SettingStorageInterface, StatefulServiceInterface
{
    public const TABLE_NAME = 'user_settings';
    private array $params = [];

    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        private readonly DbLayer           $dbLayer,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function has(string $key): bool
    {
        $this->ensureParamsAreLoaded();

        return isset($this->params[$this->permissionChecker->getUserId()][$key]);
    }

    /**
     * @throws DbLayerException
     */
    public function get(string $key): array|string|int|float|bool|null
    {
        $this->ensureParamsAreLoaded();

        return $this->params[$this->permissionChecker->getUserId()][$key] ?? null;
    }

    /**
     * @throws DbLayerException
     */
    public function set(string $key, array|string|int|float|bool|null $data): void
    {
        $this->ensureParamsAreLoaded();
        $userId = $this->permissionChecker->getUserId();
        if ($userId === null) {
            throw new \RuntimeException('No authenticated user found.');
        }

        if (($this->params[$userId][$key] ?? null) === $data) {
            return;
        }

        $this->params[$userId][$key] = $data;

        try {
            $this->dbLayer
                ->upsert(self::TABLE_NAME)
                ->setKey('user_id', ':user_id')->setParameter('user_id', $userId)
                ->setKey('name', ':name')->setParameter('name', $key)
                ->setValue('value', ':value')->setParameter('value', json_encode($data, JSON_THROW_ON_ERROR))
                ->execute()
            ;
        } catch (\JsonException $e) {
            throw new \LogicException('Failed to encode user settings.', 0, $e);
        }
    }

    /**
     * @throws DbLayerException
     */
    public function remove(string $key): void
    {
        $userId = $this->permissionChecker->getUserId();
        if ($userId === null) {
            throw new \RuntimeException('No authenticated user found.');
        }

        $this->dbLayer
            ->delete(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->andWhere('name = :name')
            ->setParameter('name', $key)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function ensureParamsAreLoaded(): void
    {
        $userId = $this->permissionChecker->getUserId();
        if ($userId === null) {
            throw new \RuntimeException('No authenticated user found.');
        }

        if (isset($this->params[$userId])) {
            return;
        }

        $result = $this->dbLayer
            ->select('name, value')
            ->from(self::TABLE_NAME)
            ->where('user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->execute()
        ;

        $this->params[$userId] = $result->fetchKeyPair();
        foreach ($this->params[$userId] as $key => $value) {
            try {
                $this->params[$userId][$key] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \LogicException('Failed to decode user settings.', 0, $e);
            }
        }
    }

    public function clearState(): void
    {
        $this->params = [];
    }
}
