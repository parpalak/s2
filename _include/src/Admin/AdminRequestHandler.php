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
use S2\Cms\Admin\Event\RedirectFromPublicEvent;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Model\AuthManager;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class AdminRequestHandler
{
    public function __construct(
        private RequestStack             $requestStack,
        private AuthManager              $authManager,
        private EventDispatcherInterface $eventDispatcher,
        private Container                $container,
    ) {
    }

    /**
     * @throws DbLayerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(Request $request): Response
    {
        array_map(static function (StatefulServiceInterface $service) {
            $service->clearState();
        }, $this->container->getByTagIfInstantiated(StatefulServiceInterface::class));

        $request->setSession(new Session());
        $this->requestStack->push($request);

        $response = $this->authManager->checkAuth($request);
        if ($response === null) {
            if ($request->query->has('path') && !$request->query->has('entity')) {
                // Redirect from public pages to the admin panel.
                // Listeners must modify the request if they recognize the path.
                $this->eventDispatcher->dispatch(new RedirectFromPublicEvent($request, $request->query->get('path')));
            }
            // NOTE: Initialization of the AdminPanel is delayed since its factory is relied on the RequestStack to be populated
            /** @var AdminPanel $adminPanel */
            $adminPanel = $this->container->get(AdminPanel::class);
            $response   = $adminPanel->handleRequest($request);
        }

        $this->requestStack->pop();

        return $response;
    }
}
