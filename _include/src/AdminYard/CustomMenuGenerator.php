<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\MenuGenerator;
use S2\AdminYard\TemplateRenderer;
use S2\Cms\Model\PermissionChecker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class CustomMenuGenerator extends MenuGenerator
{
    public function __construct(
        private AdminConfig              $config,
        private PermissionChecker        $permissionChecker,
        private TemplateRenderer         $templateRenderer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function generateMainMenu(string $baseUrl, ?string $currentEntity = null): string
    {
        $links = $this->config->getPriorities();
        asort($links);

        $event = new CustomMenuGeneratorEvent(array_keys($links));
        $this->eventDispatcher->dispatch($event);
        $signals = $event->getSignals();

        foreach ($this->config->getEntities() as $entity) {
            $name = $entity->getName();
            if (!$entity->isAllowedAction(FieldConfig::ACTION_LIST)) {
                unset($links[$name]);
                continue;
            }
            $links[$name] = [
                'name'    => $entity->getPluralName(),
                'url'     => $baseUrl . '?entity=' . urlencode($name) . '&action=list',
                'active'  => $currentEntity === $name,
                'signals' => $signals[$name] ?? [],
            ];
        }

        foreach ($this->config->getServicePageNames() as $page) {
            $links[$page] = [
                'name'    => $this->config->getReadableName($page),
                'url'     => $baseUrl . '?entity=' . urlencode($page),
                'active'  => $currentEntity === $page,
                'signals' => $signals[$page] ?? [],
            ];
        }

        return $this->templateRenderer->render($this->config->getMenuTemplate(), [
            'links'    => $links,
            'login'    => $this->permissionChecker->getUserLogin(),
            'userId'   => $this->permissionChecker->getUserId(),
            'seeUsers' => $this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN),
        ]);
    }
}
