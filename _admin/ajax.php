<?php
/**
 * Front controller for custom ajax requests in the admin panel.
 *
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

use S2\Cms\Admin\AdminAjaxRequestHandler;
use Symfony\Component\HttpFoundation\Request;

// NOTE: find a more elegant way to boot the application with the AdminExtension
const S2_ADMIN_MODE = true;

define('S2_ROOT', '../');
require S2_ROOT . '_include/common.php';

$request = Request::createFromGlobals();
/** @var AdminAjaxRequestHandler $handler */
$handler  = $app->container->get(AdminAjaxRequestHandler::class);
$response = $handler->handle($request);
$response->send();
