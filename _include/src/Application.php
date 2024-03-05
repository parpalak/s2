<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms;

use S2\Cms\Controller\ControllerInterface;
use S2\Cms\Controller\NotFoundController;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\Exception\NotFoundException;
use S2\Cms\Framework\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Application
{
    public Framework\Container $container;
    private ?RouteCollection $routes = null;
    /** @var ExtensionInterface[] */
    private array $extensions = [];

    public function addExtension(ExtensionInterface $extension): static
    {
        $this->extensions[] = $extension;

        return $this;
    }

    public function boot(array $params): void
    {
        $this->container = new Container($params);

        $eventDispatcher = new EventDispatcher();
        $this->container->set(EventDispatcherInterface::class, $eventDispatcher);

        foreach ($this->extensions as $extension) {
            $extension->buildContainer($this->container);
            $extension->registerListeners($eventDispatcher, $this->container);
        }
    }

    /**
     * @note Maybe this method must be a part of framework, but it depends on NotFoundController.
     */
    public function handle(Request $request): Response
    {
        $attributes      = $this->matchRequest($request);
        $controllerClass = $attributes['_controller'];
        if (!$this->container->has($controllerClass)) {
            throw new \LogicException(sprintf('Controller "%s" must be defined in container.', $controllerClass));
        }

        $controller = $this->container->get($controllerClass);
        if (!$controller instanceof ControllerInterface) {
            throw new \LogicException(sprintf('Controller "%s" must implement "%s".', $controllerClass, ControllerInterface::class));
        }

        try {
            $response = $controller->handle($request);
            if (\extension_loaded('newrelic')) {
                newrelic_name_transaction($controllerClass . ($response->isRedirection() ? '_' . $response->getStatusCode() : ''));
            }
        } catch (NotFoundException $e) {
            /** @var NotFoundController $errorController */
            $errorController = $this->container->get(NotFoundController::class);
            $response        = $errorController->handle($request);

            if (\extension_loaded('newrelic')) {
                newrelic_name_transaction($controllerClass . '_' . $response->getStatusCode());
            }
        }

        return $response;
    }

    private function addRoutes(): void
    {
        $routes = new RouteCollection();

        foreach ($this->extensions as $extension) {
            $extension->registerRoutes($routes, $this->container);
        }

        $this->routes = $routes;
    }

    private function matchRequest(Request $request): array
    {
        if ($this->routes === null) {
            $this->addRoutes();
        }

        $context = new RequestContext();
        $context->fromRequest($request);

        $matcher = new UrlMatcher($this->routes, $context);

        $attributes = $matcher->matchRequest($request);
        $request->attributes->add($attributes);

        return $attributes;
    }
}
