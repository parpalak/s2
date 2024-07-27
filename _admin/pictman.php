<?php
/**
 * Picture manager
 *
 * Maintain picture displaying and management
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

use S2\AdminYard\TemplateRenderer;
use S2\Cms\Model\AuthManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

define('S2_ADMIN_MODE', true);
define('S2_ROOT', '../');
require S2_ROOT . '_include/common.php';

$request = Request::createFromGlobals();

/** @var AuthManager $authManager */
$authManager = $app->container->get(AuthManager::class);
$response    = $authManager->checkAuthenticatedUser($request);
if ($response === null) {
    /** @var TemplateRenderer $templateRenderer */
    $templateRenderer = $app->container->get(TemplateRenderer::class);
    $content          = $templateRenderer->render('_admin/templates/picture-manager.php.inc', [
        'imagePath' => $app->container->getParameter('image_path'),
    ]);
    $response         = new Response($content);
}

// direct call of header() to override default PHP header
header('X-Powered-By: S2/' . $app->container->getParameter('version'));
$response->send();
