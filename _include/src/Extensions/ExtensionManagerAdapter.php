<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Extensions;

use Psr\Cache\InvalidArgumentException;
use S2\AdminYard\Config\AdminConfig;
use S2\AdminYard\Config\FieldConfig;
use S2\AdminYard\Form\FormParams;
use S2\AdminYard\TemplateRenderer;
use S2\AdminYard\Translator;
use S2\Cms\Admin\AdminConfigExtenderInterface;
use S2\Cms\Framework\Exception\AccessDeniedException;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ExtensionManagerAdapter implements AdminConfigExtenderInterface
{
    public function  __construct(
        private ExtensionManager  $extensionManager,
        private PermissionChecker $permissionChecker,
        private Translator        $translator,
        private RequestStack      $requestStack,
        private TemplateRenderer  $templateRenderer,
    ) {
    }

    public function extend(AdminConfig $adminConfig): void
    {
        if (!$this->permissionChecker->isGranted(PermissionChecker::PERMISSION_VIEW_HIDDEN)) {
            return;
        }

        $adminConfig
            ->setServicePage('Extension', function () {
                return $this->getExtensionList();
            }, 60, $this->translator->trans('Extensions'))
        ;
    }

    /**
     * @throws DbLayerException
     */
    public function getExtensionList(): string
    {
        return $this->templateRenderer->render('_admin/templates/extension/extension.php.inc', [
            ... $this->extensionManager->getExtensionList(),
            'csrfTokenGenerator' => function (string $id) {
                return $this->getCsrfToken($id);
            },
        ]);
    }

    /**
     * @throws DbLayerException
     * @throws InvalidArgumentException
     */
    public function installExtension(string $id, string $csrfToken): array
    {
        $id = $this->cleanupExtensionId($id);

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        return $this->extensionManager->installExtension($id);
    }

    /**
     * @throws DbLayerException
     */
    public function uninstallExtension(string $id, string $csrfToken): ?string
    {
        $id = $this->cleanupExtensionId($id);

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        return $this->extensionManager->uninstallExtension($id);
    }

    /**
     * @throws DbLayerException
     */
    public function flipExtension(string $id, string $csrfToken): ?string
    {
        $id = $this->cleanupExtensionId($id);

        if ($csrfToken !== $this->getCsrfToken($id)) {
            throw new AccessDeniedException('Invalid CSRF token!');
        }

        return $this->extensionManager->flipExtension($id);
    }

    private function getCsrfToken(string $id): string
    {
        // This token is used for every action in the extension actions.
        // I chose to use ACTION_DELETE since then it would be compatible with the AdminYard delete token.
        $formParams = new FormParams('Extension', [], $this->requestStack->getMainRequest(), FieldConfig::ACTION_DELETE, ['id' => $id]);

        return $formParams->getCsrfToken();
    }

    private function cleanupExtensionId(string $id): string
    {
        return preg_replace('/[^0-9a-z_]/', '', $id);
    }
}
