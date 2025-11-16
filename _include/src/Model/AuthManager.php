<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model;

use Random\RandomException;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
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

    private const CHALLENGE_EXPIRE_TIMEOUT  = 24 * 60 * 60;
    private const CHALLENGE_STATUS_LOST     = 'Lost';
    private const CHALLENGE_STATUS_OK       = 'Ok';
    private const CHALLENGE_STATUS_EXPIRED  = 'Expired';
    private const CHALLENGE_STATUS_WRONG_IP = 'Wrong_IP';
    private const LEGACY_PASSWORD_PEPPER    = 'Life is not so easy :-)';

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
        private int               $loginTimeoutMinutes,
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

        $challenge = $request->cookies->get($this->cookieName, '');

        if ($request->query->get('action') === 'logout') {
            $this->deleteChallenge($challenge);
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

        if ($challenge === '') {
            if ($request->query->get('action') === 'login') {
                return $this->processLoginForm($request);
            }

            // New session
            return $this->createUnauthorizedResponse($request);
        }

        // Existed session
        return $this->authenticateUser($request, $challenge);
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

        $challenge = $request->cookies->get($this->cookieName, '');
        if ($challenge === '') {
            return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
        }

        $status = $this->checkAndUpdateCurrentUserChallenge($request, $challenge);
        if ($status !== self::CHALLENGE_STATUS_OK || !$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW)) {
            return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
        }

        return null;
    }

    public function getCurrentChallenge(): string
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
            ->setParameter('challenge', $this->getCurrentChallenge())
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
            ->andWhere('login IS NULL')
            ->setParameter('time', time() - self::CHALLENGE_EXPIRE_TIMEOUT)
            ->execute()
        ;

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
    private function authenticateUser(Request $request, string $challenge): ?Response
    {
        $status = $this->checkAndUpdateCurrentUserChallenge($request, $challenge);

        if ($status === self::CHALLENGE_STATUS_OK) {
            if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW)) {
                return new Response($this->templateRenderer->render('_admin/templates/access-denied.php.inc'));
            }

            return null;
        }

        // Some error detected
        $this->deleteChallenge($challenge);

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
    private function touchChallenge(Request $request, string $challenge): void
    {
        $this->dbLayer
            ->update('users_online')
            ->set('time', ':time')->setParameter('time', time())
            ->set('ua', ':ua')
            ->setParameter('ua', $request->headers->get('User-Agent'))
            ->set('ip', ':ip')
            ->setParameter('ip', $request->getClientIp())
            ->where('challenge = :challenge')
            ->setParameter('challenge', $challenge)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function processLoginForm(Request $request): Response
    {
        $login     = $request->request->get('login', '');
        $password  = $request->request->get('pass', '');
        $challenge = $request->request->get('challenge', '');

        if (!$this->challengeExists($challenge)) {
            $challenge = $this->generateNewChallenge();

            return new JsonResponse([
                'success'   => false,
                'status'    => 'NEW_SALT',
                'challenge' => $challenge,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($login === '') {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        if ($password === '') {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        // Getting user password
        $userRow = $this->getUserRow($login);
        if ($userRow === null) {
            return $this->createAjaxErrorLoginPasswordResponse();
        }
        $pass = $userRow['password'];

        // Verifying password
        $isValid = password_verify($password, $pass);
        $legacyHash = md5($password . self::LEGACY_PASSWORD_PEPPER);
        if (!$isValid && !hash_equals($pass, $legacyHash)) {
            return $this->createAjaxErrorLoginPasswordResponse();
        }

        if (!$isValid) {
            // Legacy password matched, migrate to modern hash
            $this->updatePasswordHash($login, $password);
        } elseif (password_needs_rehash($pass, PASSWORD_DEFAULT)) {
            $this->updatePasswordHash($login, $password);
        }

        // Everything is Ok.
        return $this->successLogin($request, $login, $challenge);
    }

    /**
     * @throws DbLayerException
     */
    private function challengeExists(string $challenge): bool
    {
        $result = $this->dbLayer
            ->select('challenge')
            ->from('users_online')
            ->where('challenge = :challenge')->setParameter('challenge', $challenge)
            ->execute()
        ;

        return $result->result() !== null;
    }

    /**
     * @throws DbLayerException
     */
    private function getUserRow(string $login): ?array
    {
        $result = $this->dbLayer
            ->select('id, password')
            ->from('users')
            ->where('login = :login')
            ->setParameter('login', $login)
            ->execute()
        ;

        $row = $result->fetchAssoc();

        return $row ?: null;
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
    private function deleteChallenge(string $challenge): void
    {
        $this->dbLayer
            ->delete('users_online')
            ->where('challenge = :challenge')
            ->setParameter('challenge', $challenge)
            ->execute()
        ;
    }

    /**
     * @throws DbLayerException
     */
    private function successLogin(Request $request, $login, $challenge): JsonResponse
    {
        $time          = time();
        $commentCookie = md5(uniqid('comment_cookie', true) . $time);

        // Link the challenge to the user
        $this->dbLayer
            ->update('users_online')
            ->set('login', ':login')->setParameter('login', $login)
            ->set('time', ':time')->setParameter('time', $time)
            ->set('ua', ':ua')->setParameter('ua', $request->headers->get('User-Agent'))
            ->set('ip', ':ip')->setParameter('ip', $request->getClientIp())
            ->set('comment_cookie', ':comment_cookie')->setParameter('comment_cookie', $commentCookie)
            ->where('challenge = :challenge')->setParameter('challenge', $challenge)
            ->execute()
        ;

        $response = new JsonResponse(['success' => true]);

        $response->headers->setCookie(Cookie::create(
            name: $this->cookieName,
            value: $challenge,
            expire: $time + $this->cookieExpireTimeout(),
            path: $this->basePath . '/_admin/',
            secure: $this->forceAdminHttps,
        ));
        $response->headers->setCookie($this->createCommentCookie($commentCookie));

        return $response;
    }

    /**
     * @throws DbLayerException
     */
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

    /**
     * @throws DbLayerException
     */
    private function generateNewChallenge(): string
    {
        $time = time();

        try {
            $challenge = bin2hex(random_bytes(16));
        } catch (RandomException) {
            $challenge = md5(uniqid((string)mt_rand(), true) . microtime(true));
        }

        // TODO check unique constraint violation
        $this->dbLayer
            ->insert('users_online')
            ->setValue('challenge', ':challenge')
            ->setValue('time', ':time')
            ->setParameter('challenge', $challenge)
            ->setParameter('time', $time)
            ->execute()
        ;

        return $challenge;
    }

    private function createAjaxErrorLoginPasswordResponse(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->translator->trans('Error login page'),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @throws DbLayerException
     */
    private function createLoginFormResponse(string $errorMessage = ''): Response
    {
        $challenge = $this->generateNewChallenge();

        $content = $this->templateRenderer->render('_admin/templates/login.php.inc', [
            'challenge'    => $challenge,
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
        return (max($this->loginTimeoutMinutes, 1)) * 60;
    }

    /**
     * @throws DbLayerException
     */
    private function checkAndUpdateCurrentUserChallenge(Request $request, string $challenge): string
    {
        // Check if the challenge exists and isn't expired
        $result = $this->dbLayer
            ->select('login, time, ip')
            ->from('users_online')
            ->where('challenge = :challenge')->setParameter('challenge', $challenge)
            ->execute()
        ;
        if (!($row = $result->fetchRow())) {
            return self::CHALLENGE_STATUS_LOST;
        }

        [$login, $time, $ip] = $row;

        $now = time();

        if ($now > $time + $this->loginExpireTimeout()) {
            return self::CHALLENGE_STATUS_EXPIRED;
        }

        if ($ip !== $request->getClientIp()) {
            return self::CHALLENGE_STATUS_WRONG_IP;
        }

        // Ok, we keep it fresh every 5 seconds.
        if ($now > $time + 5) {
            $this->touchChallenge($request, $challenge);
        }
        $this->permissionChecker->setUser($this->getUserInfo($login));

        return self::CHALLENGE_STATUS_OK;
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
