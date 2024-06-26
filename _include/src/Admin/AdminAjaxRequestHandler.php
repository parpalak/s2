<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\Cms\Extensions\ExtensionManager;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\Container as C;
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Model\ArticleManager;
use S2\Cms\Model\AuthManager;
use S2\Cms\Model\ExtensionCache;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Model\PermissionChecker as P;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\JsonResponse as Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as R;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class AdminAjaxRequestHandler
{
    public function __construct(
        public RequestStack      $requestStack,
        public AuthManager       $authManager,
        public PermissionChecker $permissionChecker,
        public Container         $container,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        $request->setSession(new Session());
        $this->requestStack->push($request);

        $response = $this->authManager->checkAuth($request);
        if ($response !== null) {
            $this->requestStack->pop();
            $response->headers->set('X-Powered-By', 'S2/' . S2_VERSION);

            return $response;
        }

        $controllerMap = [
            // Articles tree
            'move'                => static function (P $p, R $r, C $c) {
                if (!$p->isGrantedAny(P::PERMISSION_CREATE_ARTICLES, P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('source_id') || !$r->query->has('new_parent_id') || !$r->query->has('new_pos')) {
                    return new Json(['success' => false, 'message' => 'Parameters "source_id", "new_parent_id" and "new_pos" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->moveBranch(
                    (int)$r->query->get('source_id'),
                    (int)$r->query->get('new_parent_id'),
                    (int)$r->query->get('new_pos')
                );

                return new Json(['success' => true]);
            },
            'delete'              => static function (P $p, R $r, C $c) {
                if (!$p->isGrantedAny(P::PERMISSION_CREATE_ARTICLES, P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->deleteBranch((int)$r->query->get('id'));

                return new Json(['success' => true]);
            },
            'create'              => static function (P $p, R $r, C $c) {
                if (!$p->isGrantedAny(P::PERMISSION_CREATE_ARTICLES)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id') || !$r->query->has('title')) {
                    return new Json(['success' => false, 'message' => 'Parameters "id" and "title" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $newId = $am->createArticle((int)$r->query->get('id'), $r->query->get('title'));

                return new Json(['success' => true, 'id' => $newId]);
            },
            'rename'              => static function (P $p, R $r, C $c) {
                if (!$p->isGrantedAny(P::PERMISSION_CREATE_ARTICLES, P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id') || !$r->request->has('title')) {
                    return new Json(['success' => false, 'message' => 'Parameters "id" and "title" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->renameArticle((int)$r->query->get('id'), $r->request->get('title'));

                return new Json(['success' => true]);
            },
            'load_tree'           => static function (P $p, R $r, C $c) {
                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);

                return new Json($am->getChildBranches((int)$r->query->get('id'), $r->query->get('search')));
            },


            // Extensions
            'flip_extension'      => static function (P $p, R $r, C $c) {
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManager $em */
                $em    = $c->get(ExtensionManager::class);
                $error = $em->flipExtension($r->query->get('id'));

                return new Json(['success' => $error === null, 'message' => $error]);
            },
            'refresh_hooks'       => static function (P $p, R $request, C $container) {
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                /** @var ExtensionCache $extensionManager */
                $cache = $container->get(ExtensionCache::class);
                // Regenerate the hooks cache
                $cache->generateHooks();
                $cache->generateEnabledExtensionClassNames();

                return new Json(['success' => true]);
            },
            'install_extension'   => static function (P $p, R $r, C $c) {
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManager $em */
                $em     = $c->get(ExtensionManager::class);
                $errors = $em->installExtension($r->query->get('id'));

                return new Json(['success' => $errors === [], 'message' => implode("\n", $errors)]);
            },
            'uninstall_extension' => static function (P $p, R $r, C $c) {
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => 'Permission denied.'], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManager $em */
                $em    = $c->get(ExtensionManager::class);
                $error = $em->uninstallExtension($r->query->get('id'));

                return new Json(['success' => $error === null, 'message' => $error]);
            },
        ];

        $action     = $request->get('action', '');
        $controller = $controllerMap[$action] ?? static function () {
            return new Json(['success' => false, 'message' => 'Unknown action.'], Response::HTTP_BAD_REQUEST);
        };

        try {
            $response = $controller($this->permissionChecker, $request, $this->container);
        } catch (AccessDeniedException $e) {
            $response = new Json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (NotFoundException $e) {
            $response = new Json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $response->headers->set('X-Powered-By', 'S2/' . S2_VERSION);

        return $response;
    }
}
