<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

// NOTE: find a more elegant way to boot the application with the AdminExtension
const S2_ADMIN_MODE = true;

define('S2_ROOT', '../');
require S2_ROOT . '_include/common.php';

// TODO check auth

$request = Request::createFromGlobals();
$request->setSession(new Session());
/** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
$requestStack = $app->container->get(\Symfony\Component\HttpFoundation\RequestStack::class);
$requestStack->push($request);

/** @var \S2\AdminYard\AdminPanel $adminPanel */
$adminPanel = $app->container->get(\S2\AdminYard\AdminPanel::class);
$response = $adminPanel->handleRequest($request);

$requestStack->pop();
$response->send();
