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
        $qb = $this->dbLayer
            ->select('login, email')
            ->from('users')
            ->where('hide_comments = 1')
            ->andWhere('email <> \'\'')
        ;

        if (\count($includeEmails) > 0) {
            $keys = [];
            foreach ($includeEmails as $key => $email) {
                $keys[] = ':email' . $key;
                $qb->setParameter('email' . $key, $email);
            }
            $qb->andWhere('email IN (' . implode(',', $keys) . ')');
        }

        foreach ($excludeEmails as $key => $email) {
            $qb->andWhere('email <> :no_email' . $key);
            $qb->setParameter('no_email' . $key, $email);
        }

        $result = $qb->execute();

        $moderators = [];
        while ($moderatorRow = $result->fetchAssoc()) {
            $moderators[] = new Moderator($moderatorRow['login'], $moderatorRow['email']);
        }

        return $moderators;
    }
}
