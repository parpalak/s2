<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model\User;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;

readonly class UserProvider
{
    public function __construct(
        private DbLayer $dbLayer
    ) {
    }

    /**
     * @return Moderator[]
     * @throws DbLayerException
     */
    public function getModerators(array $includeEmails = [], array $excludeEmails = []): array
    {
        $query  = [
            'SELECT' => 'login, email',
            'FROM'   => 'users',
            'WHERE'  => 'hide_comments = 1 AND email <> \'\''
        ];
        $params = [];

        if (\count($includeEmails) > 0) {
            $keys = [];
            foreach ($includeEmails as $key => $email) {
                $keys[]                 = ':email' . $key;
                $params['email' . $key] = $email;
            }
            $query['WHERE'] .= ' AND email IN (' . implode(',', $keys) . ')';
        }

        foreach ($excludeEmails as $key => $email) {
            $query['WHERE']            .= ' AND email <> :no_email' . $key;
            $params['no_email' . $key] = $email;
        }

        $result = $this->dbLayer->buildAndQuery($query, $params);

        $moderators = [];
        while ($moderatorRow = $this->dbLayer->fetchAssoc($result)) {
            $moderators[] = new Moderator($moderatorRow['login'], $moderatorRow['email']);
        }

        return $moderators;
    }
}
