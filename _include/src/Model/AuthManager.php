<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use S2\AdminYard\Helper\RandomHelper;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Config\IntProxy;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

readonly class AuthManager
{
    public const FORCE_AJAX_RESPONSE = '_force_ajax_response';

    private const SESSION_STATUS_LOST     = 'Lost';
    private const SESSION_STATUS_OK       = 'Ok';
    private const SESSION_STATUS_EXPIRED  = 'Expired';
    private const SESSION_STATUS_WRONG_IP = 'Wrong_IP';
    private const LEGACY_PASSWORD_PEPPER  = 'Life is not so easy :-)';

    public function __construct(
        private DbLayer           $dbLayer,
        private PermissionChecker $permissionChecker,
        private RequestStack      $requestStack,
        private TemplateRenderer  $templateRenderer,
        private Translator        $translator,
        private string            $basePath,
        private string            $urlPrefix,
        private string            $cookieName,
        private bool              $forceAdminHttps,
        private IntProxy          $loginTimeoutMinutes,
    ) {
    }

    /**
     * Checks credentials, processes login form, handles logout and returns unauthorized response if needed
     * (JSON for AJAX or login form for non-AJAX).
     *
     * Supposed to be used for admin main page and AJAX page controllers.
     *
     * @throws DbLayerException
     */
    public function checkAuth(Request $request): ?Response
    {
        if ($this->forceAdminHttps && !$request->isSecure()) {
            $uri       = $request->getUri();
            $secureUri = preg_replace('/^http:/i', 'https:', $uri);

            return new RedirectResponse($secureUri);
        }

        $this->cleanupExpiredSessions();

        $sessionId = $request->cookies->get($this->cookieName, '');

        if ($request->query->get('action') === 'logout') {
            $this->deleteSession($sessionId);
            $response = new RedirectResponse($request->getSchemeAndHttpHost() . $request->getBaseUrl());
            $response->headers->setCookie(Cookie::create(
                name: $this->cookieName,
                value: '',
                path: $this->basePath . '/_admin/',
                secure: $this->forceAdminHttps,
            ));
            $response->headers->setCookie($this->createCommentCookie(''));
            return $response;
        }

        if ($sessionId === '') {
            if ($request->query->get('action') === 'login') {
                return $this->processLoginForm($request);
            }

            // New session
            return $this->createUnauthorizedResponse($request);
        }

        // Existed session
        return $this->authenticateUser($request, $sessionId);
    }

    /**
     * Checks credentials and returns unauthorized response if required.
     *
     * Supposed to be used in the admin front page controllers not covered by AdminYard pages.
     *
     * @throws DbLayerException
     */
    public function checkAuthenticatedUser(Request $request): ?Response
    {
        if ($this->forceAdminHttps && !$request->isSecure()) {
            $uri       = $request->getUri();
            $secureUri = preg_replace('/^http:/i', 'https:', $uri);

            return new RedirectResponse($secureUri);
        }

        $sessionId = $request->cookies->get($this->cookieName, '');
        if ($sessionId === '') {
            return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
        }

        $status = $this->checkAndUpdateCurrentUserSession($request, $sessionId);
        if ($status !== self::SESSION_STATUS_OK || !$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW)) {
            return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
        }

        return null;
    }

    public function getCurrentSessionId(): string
    {
        return $this->requestStack->getMainRequest()->cookies->get($this->cookieName, '');
    }

    /**
     * @throws DbLayerException
     */
    public function getTotalUserSessionsCount(): int
    {
        $result = $this->dbLayer
            ->select('COUNT(*)')
            ->from('users_online AS u1')
            ->innerJoin('users_online AS u2', 'u1.login = u2.login')
            ->where('u1.challenge = :challenge')
            ->setParameter('challenge', $this->getCurrentSessionId())
            ->execute()
        ;

        return $result->result();
    }

    /**
     * @throws DbLayerException
     */
    private function cleanupExpiredSessions(): void
    {
        $this->dbLayer
            ->delete('users_online')
            ->where('time < :time')
            ->andWhere('login IS NOT NULL')
            ->setParameter('time', time() - $this->cookieExpireTimeout())
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function authenticateUser(Request $request, string $sessionId): ?Response
    {
        $status = $this->checkAndUpdateCurrentUserSession($request, $sessionId);

        if ($status === self::SESSION_STATUS_OK) {
            if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW)) {
                return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
            }

            return null;
        }

        // Some error detected
        $this->deleteSession($sessionId);

        if ($request->isXmlHttpRequest() || $request->attributes->get(self::FORCE_AJAX_RESPONSE)) {
            $response = new JsonResponse([
                'success' => false,
                'status'  => $status,
                'message' => $this->translator->trans($status . ' session ajax'),
            ], Response::HTTP_UNAUTHORIZED);
        } else {
            $response = $this->createLoginFormResponse($this->translator->trans($status . ' session'));
        }

        $response->headers->setCookie(Cookie::create(
            name: $this->cookieName,
            value: '',
            path: $this->basePath . '/_admin/',
            secure: $this->forceAdminHttps,
        ));
        $response->headers->setCookie($this->createCommentCookie(''));

        return $response;
    }

    /**
     * @throws DbLayerException
     */
    private function getUserInfo(string $login): ?array
    {
        $result = $this->dbLayer
            ->select('*')
            ->from('users')
            ->where('login = :login')
            ->setParameter('login', $login)
            ->execute()
        ;

        return $result->fetchAssoc() ?: null;
    }

    /**
     * @throws DbLayerException
     */
    private function touchSession(Request $request, string $sessionId): void
    {
        $this->dbLayer
            ->update('users_online')
            ->set('time', ':time')->setParameter('time', time())
            ->set('ua', ':ua')
            ->setParameter('ua', $request->headers->get('User-Agent'))
            ->set('ip', ':ip')
            ->setParameter('ip', $request->getClientIp())
            ->where('challenge = :challenge')
            ->setParameter('challenge', $sessionId)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function processLoginForm(Request $request): Response
    {
        $login    = $request->request->get('login', '');
        $password = $request->request->get('pass', '');

        if ($login === '') {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        if ($password === '') {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        // Getting user password hash
        $passwordHash = $this->getPasswordHash($login);
        if ($passwordHash === null) {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        // Verifying password
        $hashMatches    = password_verify($password, $passwordHash);
        $oldHashMatches = hash_equals($passwordHash, md5($password . self::LEGACY_PASSWORD_PEPPER));
        if (!$hashMatches && !$oldHashMatches) {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        if (!$hashMatches || password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
            $this->updatePasswordHash($login, $password);
        }

        // Everything is Ok.
        return $this->successLogin($request, $login);
    }

    /**
     * @throws DbLayerException
     */
    private function getPasswordHash(string $login): ?string
    {
        $result = $this->dbLayer
            ->select('password')
            ->from('users')
            ->where('login = :login')
            ->setParameter('login', $login)
            ->execute()
        ;

        $row = $result->fetchRow();

        return $row === false ? null : (string)$row[0];
    }

    /**
     * @throws DbLayerException
     */
    private function updatePasswordHash(string $login, string $password): void
    {
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $this->dbLayer
            ->update('users')
            ->set('password', ':password')->setParameter('password', $newHash)
            ->where('login = :login')->setParameter('login', $login)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function deleteSession(string $sessionId): void
    {
        $this->dbLayer
            ->delete('users_online')
            ->where('challenge = :challenge')
            ->setParameter('challenge', $sessionId)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function successLogin(Request $request, string $login): JsonResponse
    {
        $time          = time();
        $sessionId     = RandomHelper::getRandomHexString32();
        $commentCookie = RandomHelper::getRandomHexString32();

        // Create user session
        // TODO check unique constraint violation
        $this->dbLayer
            ->insert('users_online')
            ->setValue('login', ':login')->setParameter('login', $login)
            ->setValue('challenge', ':challenge')->setParameter('challenge', $sessionId)
            ->setValue('time', ':time')->setParameter('time', $time)
            ->setValue('ua', ':ua')->setParameter('ua', $request->headers->get('User-Agent'))
            ->setValue('ip', ':ip')->setParameter('ip', $request->getClientIp())
            ->setValue('comment_cookie', ':comment_cookie')->setParameter('comment_cookie', $commentCookie)
            ->execute()
        ;

        $response = new JsonResponse(['success' => true]);

        $response->headers->setCookie(Cookie::create(
            name: $this->cookieName,
            value: $sessionId,
            expire: $time + $this->cookieExpireTimeout(),
            path: $this->basePath . '/_admin/',
            secure: $this->forceAdminHttps,
        ));
        $response->headers->setCookie($this->createCommentCookie($commentCookie));

        return $response;
    }

    private function createUnauthorizedResponse(Request $request): Response
    {
        if ($request->isXmlHttpRequest() || $request->attributes->get(self::FORCE_AJAX_RESPONSE)) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('Lost session'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->createLoginFormResponse();
    }

    private function createAjaxErrorLoginPasswordResponse(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->translator->trans('Error login page'),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function createLoginFormResponse(string $errorMessage = ''): Response
    {
        $content = $this->templateRenderer->render('_admin/templates/login.php.inc', [
            'errorMessage' => $errorMessage,
        ]);

        return new Response($content);
    }

    private function cookieExpireTimeout(): int
    {
        return max(14 * 86400, $this->loginExpireTimeout());
    }

    private function loginExpireTimeout(): int
    {
        return (max($this->loginTimeoutMinutes->get(), 1)) * 60;
    }

    /**
     * @throws DbLayerException
     */
    private function checkAndUpdateCurrentUserSession(Request $request, string $sessionId): string
    {
        // Check if the session exists and isn't expired
        $result = $this->dbLayer
            ->select('login, time, ip')
            ->from('users_online')
            ->where('challenge = :challenge')->setParameter('challenge', $sessionId)
            ->execute()
        ;
        if (!($row = $result->fetchRow())) {
            return self::SESSION_STATUS_LOST;
        }

        [$login, $time, $ip] = $row;

        $now = time();

        if ($now > $time + $this->loginExpireTimeout()) {
            return self::SESSION_STATUS_EXPIRED;
        }

        if ($ip !== $request->getClientIp()) {
            return self::SESSION_STATUS_WRONG_IP;
        }

        // Ok, we keep it fresh every 5 seconds.
        if ($now > $time + 5) {
            $this->touchSession($request, $sessionId);
        }
        $this->permissionChecker->setUser($this->getUserInfo($login));

        return self::SESSION_STATUS_OK;
    }

    /**
     * Special cookie to mark that a user is logged in.
     * If this user has a permission, his comment will be published even in pre-moderation mode.
     */
    private function createCommentCookie(string $value): Cookie
    {
        return Cookie::create(
            name: $this->cookieName . '_c',
            value: $value,
            expire: $value !== '' ? $this->cookieExpireTimeout() + time() : 0,
            path: $this->basePath . ($this->urlPrefix === '' ? '/comment_sent' : '/'),
            secure: false,
        );
    }
}
