<?php
/**
 * Application class.
 * Handles HTTP requests after building container definitions and registering event listeners.
 *
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Framework;

use S2\Cms\Framework\Event\NotFoundEvent;
use S2\Cms\Framework\Exception\NotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Application
{
    public Container $container;
    private ?RouteCollection $routes = null;
    private ?array $compiledRoutes = null;
    /** @var ExtensionInterface[] */
    private array $extensions = [];
    private ?string $cachedRoutesFilename = null;

    public function addExtension(ExtensionInterface $extension): static
    {
        $this->extensions[] = $extension;

        return $this;
    }

    /**
     * Boots the application by initializing the container definitions
     * and registering event listeners described in the extensions.
     *
     * This method can be called again to reinitialize the application.
     */
    public function boot(array $params): void
    {
        $this->routes         = null;
        $this->compiledRoutes = null;

        $this->container = new Container($params);

        $eventDispatcher = new EventDispatcher();
        $this->container->set(EventDispatcherInterface::class, $eventDispatcher);

        foreach ($this->extensions as $extension) {
            $extension->buildContainer($this->container);
            $extension->registerListeners($eventDispatcher, $this->container);
        }
    }

    /**
     * Set the filename where the cached routes will be stored.
     */
    public function setCachedRoutesFilename(string $file): void
    {
        $this->cachedRoutesFilename = $file;
    }

    /**
     * Method of an HTTP kernel interface.
     *
     * 1. Detects a controller based on the routes defined in the extensions.
     * 2. Pushes the request onto the RequestStack if available.
     * 3. Executes the controller and returns the response.
     * 4. If the controller throws NotFoundException, it can be handled via NotFoundEvent.
     */
    public function handle(Request $request): Response
    {
        array_map(static function (StatefulServiceInterface $service) {
            $service->clearState();
        }, $this->container->getByTagIfInstantiated(StatefulServiceInterface::class));

        $attributes      = $this->matchRequest($request);
        $controllerClass = $attributes['_controller'];
        if (!$this->container->has($controllerClass)) {
            throw new \LogicException(sprintf('Controller "%s" must be defined in container.', $controllerClass));
        }

        $controller = $this->container->get($controllerClass);
        if (!$controller instanceof ControllerInterface) {
            throw new \LogicException(sprintf('Controller "%s" must implement "%s".', $controllerClass, ControllerInterface::class));
        }

        /** @var RequestStack $requestStack */
        $requestStack = $this->container->has(RequestStack::class) ? $this->container->get(RequestStack::class) : null;
        $requestStack?->push($request);

        $response = null;
        try {
            $response = $controller->handle($request);
            if ($response->isNotFound()) {
                throw new NotFoundException();
            }
            if (\extension_loaded('newrelic')) {
                newrelic_name_transaction($controllerClass . (!$response->isSuccessful() ? '_' . $response->getStatusCode() : ''));
            }
        } catch (NotFoundException $e) {
            /** @var EventDispatcherInterface $eventDispatcher */
            $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
            $notFoundEvent   = new NotFoundEvent($request, $response);
            $eventDispatcher->dispatch($notFoundEvent);
            $response = $notFoundEvent->response;
            if ($response === null) {
                $response = new Response('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
</head>
<body>
    <h1>404 Not Found</h1>
    <p>The page you are looking for does not exist.</p>
</body>
</html>', Response::HTTP_NOT_FOUND);
            }

            if (\extension_loaded('newrelic')) {
                newrelic_name_transaction($controllerClass . '_' . $response->getStatusCode());
            }
        } finally {
            $requestStack?->pop();
        }

        return $response;
    }

    private function getRoutes(): RouteCollection
    {
        if ($this->routes === null) {
            $this->routes = new RouteCollection();
            foreach ($this->extensions as $extension) {
                $extension->registerRoutes($this->routes, $this->container);
            }
        }

        return $this->routes;
    }

    private function matchRequest(Request $request): array
    {
        $context = new RequestContext();
        $context->fromRequest($request);

        if ($this->cachedRoutesFilename !== null) {
            $matcher = $this->getCompiledUrlMatcher($context);
        } else {
            $matcher = $this->getUrlMatcher($context);
        }

        $attributes = $matcher->matchRequest($request);

        $request->attributes->add($attributes);

        return $attributes;
    }

    private function getCompiledUrlMatcher(RequestContext $context): CompiledUrlMatcher
    {
        if ($this->compiledRoutes === null) {
            if (file_exists($this->cachedRoutesFilename)) {
                $compiledRoutes = @include $this->cachedRoutesFilename;
                if (\is_array($compiledRoutes)) {
                    $this->compiledRoutes = $compiledRoutes;
                }
            }

            if ($this->compiledRoutes === null) {
                $compiledUrlMatcherDumper = new CompiledUrlMatcherDumper($this->getRoutes());
                $this->compiledRoutes     = $compiledUrlMatcherDumper->getCompiledRoutes();
                s2_overwrite_file_skip_locked($this->cachedRoutesFilename, $compiledUrlMatcherDumper->dump());
            }
        }

        return new CompiledUrlMatcher($this->compiledRoutes, $context);
    }

    private function getUrlMatcher(RequestContext $context): UrlMatcher
    {
        return new UrlMatcher($this->getRoutes(), $context);
    }
}
