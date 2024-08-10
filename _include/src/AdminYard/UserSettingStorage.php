<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
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
    public function has(string $string): bool
    {
        $this->ensureParamsAreLoaded();

        return isset($this->params[$this->permissionChecker->getUserId()][$string]);
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
            $this->dbLayer->buildAndQuery([
                'UPSERT' => 'user_id, name, value',
                'INTO'   => self::TABLE_NAME,
                'UNIQUE' => 'user_id, name',
                'VALUES' => ':user_id, :name, :value',
            ], [
                'user_id' => $userId,
                'name'    => $key,
                'value'   => json_encode($data, JSON_THROW_ON_ERROR),
            ]);
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

        $this->dbLayer->buildAndQuery([
            'DELETE' => self::TABLE_NAME,
            'WHERE'  => 'user_id = :user_id AND name = :name',
        ], [
            'user_id' => $userId,
            'name'    => $key,
        ]);
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

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'name, value',
            'FROM'   => self::TABLE_NAME,
            'WHERE'  => 'user_id = :user_id',
        ], [
            'user_id' => $userId
        ]);

        $this->params[$userId] = $result->fetchAll(\PDO::FETCH_KEY_PAIR);
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
