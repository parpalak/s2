<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
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
        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'count(*)',
            'FROM'   => 'users AS u',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'users_online AS o',
                    'ON'         => 'o.login = u.login'
                ],
            ],
            'WHERE'  => 'u.email = :email'
        ], [
            'email' => $email,
        ]);

        $isOnline = $this->dbLayer->result($result) > 0;

        return $isOnline;
    }

    /**
     * @throws DbLayerException
     */
    public function getAuthenticatedModeratorEmail(Request $request): ?string
    {
        $cookie = $request->cookies->get($this->cookieName . '_c', '');

        $result = $this->dbLayer->buildAndQuery([
            'SELECT' => 'email',
            'FROM'   => 'users AS u',
            'JOINS'  => [
                [
                    'INNER JOIN' => 'users_online AS o',
                    'ON'         => 'o.login = u.login'
                ],
            ],
            'WHERE'  => 'u.edit_comments = 1 AND o.comment_cookie = :cookie'
        ], [
            'cookie' => $cookie,
        ]);

        $email = $this->dbLayer->result($result);

        return \is_string($email) && $email !== '' ? $email : null;
    }
}
