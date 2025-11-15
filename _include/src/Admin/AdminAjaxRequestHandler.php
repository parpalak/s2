<?php
/**
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use S2\AdminYard\Translator;
use S2\AdminYard\Translator as T;
use S2\Cms\Admin\Event\AdminAjaxControllerMapEvent;
use S2\Cms\Admin\Picture\PictureManager;
use S2\Cms\Extensions\ExtensionManagerAdapter;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\Container as C;
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Model\ArticleManager;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\AuthManager;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Model\PermissionChecker as P;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplateProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse as Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as R;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdminAjaxRequestHandler
{
    public function __construct(
        public RequestStack             $requestStack,
        public AuthManager              $authManager,
        public PermissionChecker        $permissionChecker,
        public Translator               $translator,
        public EventDispatcherInterface $eventDispatcher,
        public Container                $container,
    ) {
    }

    /**
     * @throws DbLayerException
     */
    public function handle(Request $request): Response
    {
        array_map(static function (StatefulServiceInterface $service) {
            $service->clearState();
        }, $this->container->getByTagIfInstantiated(StatefulServiceInterface::class));

        $request->setSession(new Session());
        $request->attributes->set(AuthManager::FORCE_AJAX_RESPONSE, true);
        $this->requestStack->push($request);

        $response = $this->authManager->checkAuth($request);
        if ($response !== null) {
            $this->requestStack->pop();

            return $response;
        }

        $controllerMap = [
            // Articles tree
            'move'                => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$r->request->has('source_id') || !$r->request->has('new_parent_id') || !$r->request->has('new_pos')) {
                    return new Json(['success' => false, 'message' => 'Parameters "source_id", "new_parent_id" and "new_pos" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->moveBranch(
                    (int)$r->request->get('source_id'),
                    (int)$r->request->get('new_parent_id'),
                    (int)$r->request->get('new_pos'),
                    $r->request->get('csrf_token', '')
                );

                return new Json(['success' => true]);
            },
            'delete'              => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->deleteBranch((int)$r->query->get('id'), $r->request->get('csrf_token', ''));

                return new Json(['success' => true]);
            },
            'create'              => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$p->isGrantedAny(P::PERMISSION_CREATE_ARTICLES)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id') || !$r->request->has('title')) {
                    return new Json(['success' => false, 'message' => 'Parameters "id" and "title" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am        = $c->get(ArticleManager::class);
                $parentId  = (int)$r->query->get('id');
                $newId     = $am->createArticle($parentId, (string)$r->request->get('title'), (string)$r->request->get('csrf_token', ''));

                return new Json(['success' => true, 'id' => $newId, 'csrfToken' => $am->getCsrfToken($newId)]);
            },
            'rename'              => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$r->query->has('id') || !$r->request->has('title')) {
                    return new Json(['success' => false, 'message' => 'Parameters "id" and "title" are required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);
                $am->renameArticle((int)$r->query->get('id'), $r->request->get('title'), $r->request->get('csrf_token', ''));

                return new Json(['success' => true]);
            },
            'load_tree'           => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ArticleManager $am */
                $am = $c->get(ArticleManager::class);

                return new Json($am->getChildBranches((int)$r->query->get('id'), $r->query->get('search')));
            },


            // Extensions
            'flip_extension'      => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManagerAdapter $em */
                $em    = $c->get(ExtensionManagerAdapter::class);
                $error = $em->flipExtension($r->query->get('id'), $r->request->get('csrf_token', ''));

                return new Json(['success' => $error === null, 'message' => $error]);
            },
            'install_extension'   => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManagerAdapter $em */
                $em     = $c->get(ExtensionManagerAdapter::class);
                $errors = $em->installExtension($r->query->get('id'), $r->request->get('csrf_token', ''));

                return new Json(['success' => $errors === [], 'message' => implode("\n", $errors)]);
            },
            'uninstall_extension' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }
                if (!$p->isGranted(P::PERMISSION_EDIT_USERS)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('id')) {
                    return new Json(['success' => false, 'message' => 'Parameter "id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                /** @var ExtensionManagerAdapter $em */
                $em    = $c->get(ExtensionManagerAdapter::class);
                $error = $em->uninstallExtension($r->query->get('id'), $r->request->get('csrf_token', ''));

                return new Json(['success' => $error === null, 'message' => $error]);
            },

            'phpinfo' => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_VIEW_HIDDEN)) {
                    return new Response($t->trans('No permission'), Response::HTTP_FORBIDDEN);
                }

                return new StreamedResponse(static function () {
                    /** @noinspection ForgottenDebugOutputInspection */
                    phpinfo();
                });
            },

            // pictures
            'preview' => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('file')) {
                    return new Json(['success' => false, 'message' => 'Parameter "file" is required.'], Response::HTTP_BAD_REQUEST);
                }

                $file = (string)$r->query->get('file');
                if (str_contains($file, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid file name.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);

                $response = $pictureManager->getThumbnailResponse($file, 200);
                $response->setPublic();
                $response->setExpires(new \DateTimeImmutable('1 year'));

                return $response;
            },

            'load_folders' => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);

                try {
                    return new Json($pictureManager->getDirContentRecursive($path));
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'picture_csrf_token' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->request->has('path')) {
                    return new Json(['success' => false, 'message' => 'Parameter "path" is required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->request->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);

                return new Json(['success' => true, 'csrf_token' => $pictureManager->getFolderCsrfToken($path)]);
            },

            'create_subfolder' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_CREATE_ARTICLES)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path') || !$r->query->has('name')) {
                    return new Json(['success' => false, 'message' => 'Parameters "path" and "name" are required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                $name = $r->query->getString('name');
                if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
                    return new Json(['success' => false, 'message' => 'Invalid name.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                try {
                    $pictureManager->assertFolderCsrfToken($path, (string)$r->request->get('csrf_token', ''));
                    $newName = $pictureManager->createSubfolder($path, $name);
                    $newPath = $path . '/' . $newName;
                    return new Json([
                        'success'    => true,
                        'name'       => $newName,
                        'path'       => $newPath,
                        'csrf_token' => $pictureManager->getFolderCsrfToken($newPath),
                    ]);
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'delete_folder' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path')) {
                    return new Json(['success' => false, 'message' => 'Parameter "path" is required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                $pictureManager->assertFolderCsrfToken($path, (string)$r->request->get('csrf_token', ''));

                if ($path !== '') {
                    $pictureManager->deleteFolder($path);
                }

                return new Json(['success' => true]);
            },

            'delete_files' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path') || !$r->query->has('fname')) {
                    return new Json(['success' => false, 'message' => 'Parameters "path" and "fname" are required.'], Response::HTTP_BAD_REQUEST);
                }

                try {
                    $fileNames = $r->query->all('fname');
                } catch (BadRequestException $e) {
                    return new Json(['success' => false, 'message' => 'Parameter "fname" must be an array.'], Response::HTTP_BAD_REQUEST);
                }

                $dir = $r->query->get('path');
                if (str_contains($dir, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                $pictureManager->assertFolderCsrfToken($dir, (string)$r->request->get('csrf_token', ''));

                foreach ($fileNames as $fileName) {
                    $path = $dir . '/' . ((string)$fileName);
                    while (str_contains($path, '..')) {
                        $path = str_replace('..', '', $path);
                    }

                    $pictureManager->deleteFile($path);
                }

                return new Json(['success' => true]);
            },

            'rename_folder' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path') || !$r->query->has('name')) {
                    return new Json(['success' => false, 'message' => 'Parameters "path" and "name" are required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                $name = $r->query->getString('name');
                if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
                    return new Json(['success' => false, 'message' => 'Invalid name.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                try {
                    $pictureManager->assertFolderCsrfToken($path, (string)$r->request->get('csrf_token', ''));
                    $newName = $pictureManager->renameFolder($path, $name);
                    return new Json([
                        'success'    => true,
                        'new_path'   => $newName,
                        'csrf_token' => $pictureManager->getFolderCsrfToken($newName),
                    ]);
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'rename_file' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path') || !$r->query->has('name')) {
                    return new Json(['success' => false, 'message' => 'Parameters "path" and "name" are required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                $filename = $r->query->getString('name');
                if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
                    return new Json(['success' => false, 'message' => 'Invalid name.'], Response::HTTP_BAD_REQUEST);
                }

                $extension = '';
                if (($ext_pos = strrpos($filename, '.')) !== false) {
                    $extension = substr($filename, $ext_pos + 1);
                }

                $allowedExtensions = $c->getParameter('allowed_extensions');
                if (
                    $extension !== ''
                    && $allowedExtensions !== ''
                    && !$p->isGranted(P::PERMISSION_EDIT_USERS)
                    && !str_contains(' ' . $allowedExtensions . ' ', ' ' . $extension . ' ')
                ) {
                    return new Json(['success' => false, 'message' => $t->trans('Forbidden extension', ['{{ ext }}' => $extension])], Response::HTTP_FORBIDDEN);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                try {
                    $pictureManager->assertFileCsrfToken($path, (string)$r->request->get('csrf_token', ''));
                    $newName = $pictureManager->renameFile($path, $filename);
                    return new Json(['success' => true, 'new_name' => $newName]);
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'move_folder' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('spath') || !$r->query->has('dpath')) {
                    return new Json(['success' => false, 'message' => 'Parameters "spath" and "dpath" are required.'], Response::HTTP_BAD_REQUEST);
                }

                $sourcePath = $r->query->getString('spath');
                if (str_contains($sourcePath, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid source path.'], Response::HTTP_BAD_REQUEST);
                }

                $destinationPath = $r->query->getString('dpath');
                if (str_contains($destinationPath, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid destination path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                try {
                    $pictureManager->assertFolderCsrfToken($sourcePath, (string)$r->request->get('csrf_token', ''));
                    $pictureManager->assertFolderCsrfToken($destinationPath, (string)$r->request->get('destination_csrf_token', ''));
                    $newPath = $pictureManager->moveFolder($sourcePath, $destinationPath);
                    return new Json([
                        'success'    => true,
                        'new_path'   => $newPath,
                        'csrf_token' => $pictureManager->getFolderCsrfToken($newPath),
                    ]);
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'move_files' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_EDIT_SITE)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (
                    !$r->query->has('spath')
                    || !$r->query->has('dpath')
                    || !$r->query->has('fname')
                ) {
                    return new Json(['success' => false, 'message' => 'Parameters "spath", "dpath", and "fname" are required.'], Response::HTTP_BAD_REQUEST);
                }

                try {
                    $fileNames = $r->query->all('fname');
                } catch (BadRequestException $e) {
                    return new Json(['success' => false, 'message' => 'Parameter "fname" must be an array.'], Response::HTTP_BAD_REQUEST);
                }

                $sourcePath = $r->query->getString('spath');
                if (str_contains($sourcePath, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid source path.'], Response::HTTP_BAD_REQUEST);
                }

                $destinationPath = $r->query->getString('dpath');
                if (str_contains($destinationPath, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid destination path.'], Response::HTTP_BAD_REQUEST);
                }

                foreach ($fileNames as $fileName) {
                    if (str_contains($fileName, '..')) {
                        return new Json(['success' => false, 'message' => 'Invalid file name.'], Response::HTTP_BAD_REQUEST);
                    }
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                try {
                    $pictureManager->assertFolderCsrfToken($sourcePath, (string)$r->request->get('csrf_token', ''));
                    $pictureManager->assertFolderCsrfToken($destinationPath, (string)$r->request->get('destination_csrf_token', ''));
                    $pictureManager->moveFiles($sourcePath, $destinationPath, $fileNames);
                    return new Json(['success' => true]);
                } catch (\RuntimeException $e) {
                    return new Json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            },

            'load_files' => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_VIEW)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->query->has('path')) {
                    return new Json(['success' => false, 'message' => 'Parameter "path" is required.'], Response::HTTP_BAD_REQUEST);
                }

                $path = $r->query->getString('path');
                if (str_contains($path, '..')) {
                    return new Json(['success' => false, 'message' => 'Invalid path.'], Response::HTTP_BAD_REQUEST);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                $files          = $pictureManager->getFiles($path);
                return new Json($files);
            },

            'upload' => static function (P $p, R $r, C $c, T $t) {
                if ($r->getRealMethod() !== 'POST') {
                    return new Json(['success' => false, 'message' => 'Only POST requests are allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
                }

                if (!$p->isGranted(P::PERMISSION_CREATE_ARTICLES)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }

                if (!$r->request->has('dir')) {
                    return new Json(['success' => false, 'message' => $t->trans('No POST data')], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $path = $r->request->get('dir');
                if (str_contains($path, '..') || str_contains($path, "\0")) {
                    return new Json(['success' => false, 'message' => 'Invalid dir.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if (!$r->files->has('pictures')) {
                    return new Json(['success' => false, 'message' => $t->trans('No file')], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $uploadedFiles = $r->files->get('pictures');
                if (\count($uploadedFiles) === 0) {
                    return new Json(['success' => false, 'message' => $t->trans('Empty files')], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                /** @var PictureManager $pictureManager */
                $pictureManager = $c->get(PictureManager::class);
                $pictureManager->assertFolderCsrfToken($path, (string)$r->request->get('csrf_token', ''));

                $errors = [];

                $lastFileName = null;
                foreach ($uploadedFiles as $uploadedFile) {
                    try {
                        $lastFileName = $pictureManager->processUploadedFile($uploadedFile, $path, (bool)$r->request->get('create_dir'));
                    } catch (\RuntimeException $e) {
                        $errors[] = $e->getMessage();
                    }
                }

                if (\count($errors) > 0) {
                    return new Json(['success' => false, 'errors' => $errors]);
                }

                return new Json([
                    'success'   => true,
                    'file_path' => $c->getParameter('image_path') . $lastFileName,
                    ...$r->request->has('return_image_info') && $lastFileName !== null ? ['image_info' => $pictureManager->getImageInfo($lastFileName)] : [],
                ]);
            },

            // article helpers
            'load_template' => static function (P $p, R $r, C $c, T $t) {
                if (!$p->isGranted(P::PERMISSION_CREATE_ARTICLES)) {
                    return new Json(['success' => false, 'message' => $t->trans('No permission')], Response::HTTP_FORBIDDEN);
                }
                if (!$r->query->has('article_id') && !$r->query->has('template_id')) {
                    return new Json(['success' => false, 'message' => 'One of parameters "article_id" or "template_id" is required.'], Response::HTTP_BAD_REQUEST);
                }
                $templateId = $r->query->getString('template_id');
                if ($templateId === '') {
                    $articleId = $r->query->getInt('article_id');
                    /** @var ArticleProvider $articleProvider */
                    $articleProvider = $c->get(ArticleProvider::class);
                    $templateId      = $articleProvider->findInheritedTemplate($articleId, false);
                }

                if ($templateId === '') {
                    $templateId = 'site.php';
                }

                /** @var HtmlTemplateProvider $htmlTemplateProvider */
                $htmlTemplateProvider = $c->get(HtmlTemplateProvider::class);
                $template             = $htmlTemplateProvider->getRawTemplateContent($templateId, null);

                if ($template === '') {
                    return new Json(['success' => false, 'message' => 'Template not found.'], Response::HTTP_NOT_FOUND);
                }
                return new Json(['success' => true, 'template' => $template]);
            },
        ];

        $this->eventDispatcher->dispatch($event = new AdminAjaxControllerMapEvent($controllerMap));

        $action     = $request->get('action', '');
        $controller = $event->controllerMap[$action] ?? static function () {
            return new Json(['success' => false, 'message' => 'Unknown action.'], Response::HTTP_BAD_REQUEST);
        };

        try {
            $response = $controller($this->permissionChecker, $request, $this->container, $this->translator);
        } catch (AccessDeniedException $e) {
            $response = new Json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (NotFoundException $e) {
            $response = new Json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return $response;
    }
}
