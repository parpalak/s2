<?php

declare(strict_types=1);

namespace S2\Cms\Admin;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use S2\AdminYard\AdminPanel;
use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\Database\PdoDataProvider;
use S2\AdminYard\Form\FormFactory;
use S2\AdminYard\SettingStorage\SettingStorageInterface;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Transformer\ViewTransformer;
use S2\AdminYard\Translator;
use S2\Cms\AdminYard\CustomMenuGenerator;
use S2\Cms\Framework\Container;
use S2\Cms\Model\PermissionChecker;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds fresh AdminPanel instances per request to keep admin config and menu up to date.
 */
readonly class AdminPanelFactory
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function create(): AdminPanel
    {
        /** @var AdminConfigProvider $adminConfigProvider */
        $adminConfigProvider = $this->container->get(AdminConfigProvider::class);
        $adminConfig         = $adminConfigProvider->getAdminConfig();

        $eventDispatcher = new EventDispatcher();
        $this->registerEntityListeners($eventDispatcher, $adminConfig);

        $menuGenerator = new CustomMenuGenerator(
            $adminConfig,
            $this->container->get(TemplateRenderer::class),
            $this->container->get(PermissionChecker::class),
            $this->container->get(EventDispatcherInterface::class),
        );

        $adminPanel = new AdminPanel(
            $adminConfig,
            $eventDispatcher,
            $this->container->get(PdoDataProvider::class),
            new ViewTransformer(),
            $menuGenerator,
            $this->container->get(Translator::class),
            $this->container->get(TemplateRenderer::class),
            $this->container->get(FormFactory::class),
            $this->container->get(SettingStorageInterface::class),
        );
        $adminPanel->setLogger($this->container->get(LoggerInterface::class));

        return $adminPanel;
    }

    private function registerEntityListeners(EventDispatcher $eventDispatcher, AdminConfig $adminConfig): void
    {
        foreach ($adminConfig->getEntities() as $entityConfig) {
            foreach ($entityConfig->getListeners() as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $eventDispatcher->addListener('adminyard.' . $eventName, $listener);
                }
            }
        }
    }
}
