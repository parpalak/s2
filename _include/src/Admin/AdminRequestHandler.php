<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Admin;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\AdminYard\AdminPanel;
use S2\Cms\Framework\Container;
use S2\Cms\Model\AuthManager;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

readonly class AdminRequestHandler
{
    public function __construct(
        public RequestStack $requestStack,
        public AuthManager  $authManager,
        public Container    $container,
    ) {
    }

    /**
     * @throws DbLayerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(Request $request): Response
    {
        $request->setSession(new Session());
        $this->requestStack->push($request);

        $response = $this->authManager->checkAuth($request);
        if ($response === null) {
            // NOTE: Initialization of the AdminPanel is delayed since its factory is relied on the RequestStack to be populated
            /** @var AdminPanel $adminPanel */
            $adminPanel = $this->container->get(AdminPanel::class);
            $response   = $adminPanel->handleRequest($request);
        }

        $this->requestStack->pop();
        $response->headers->set('X-Powered-By', 'S2/' . S2_VERSION);

        return $response;
    }
}
