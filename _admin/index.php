<?php
/**
 * Front controller for the admin panel.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

use S2\Cms\Admin\AdminRequestHandler;
use Symfony\Component\HttpFoundation\Request;

// NOTE: find a more elegant way to boot the application with the AdminExtension
const S2_ADMIN_MODE = true;

define('S2_ROOT', '../');
require S2_ROOT . '_include/common.php';

$request = Request::createFromGlobals();
/** @var AdminRequestHandler $handler */
$handler  = $app->container->get(AdminRequestHandler::class);
$response = $handler->handle($request);

// direct call of header() to override default PHP header
header('X-Powered-By: S2/' . $app->container->getParameter('version'));
$response->send();
