<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

readonly class AuthProvider
{
    public function __construct(
        private DbLayer $dbLayer,
        private string  $cookieName
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function isOnline(string $email): bool
    {
        $result = $this->dbLayer
            ->select('COUNT(*)')
            ->from('users AS u')
            ->innerJoin('users_online AS o', 'o.login = u.login')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->execute()
        ;

        $count = $result->result();

        return $count > 0;
    }

    /**
     * @throws DbLayerException
     * @throws BadRequestException
     */
    public function getAuthenticatedModeratorEmail(Request $request): ?string
    {
        $cookie = $request->cookies->get($this->cookieName . '_c', '');

        $result = $this->dbLayer
            ->select('email')
            ->from('users AS u')
            ->innerJoin('users_online AS o', 'o.login = u.login')
            ->where('u.edit_comments = 1')
            ->andWhere('o.comment_cookie = :cookie')
            ->setParameter('cookie', $cookie)
            ->execute()
        ;

        $email = $result->result();

        return \is_string($email) && $email !== '' ? $email : null;
    }
}
