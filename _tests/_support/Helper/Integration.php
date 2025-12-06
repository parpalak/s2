<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace Helper;

use Codeception\TestInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\Cms\Admin\AdminAjaxRequestHandler;
use S2\Cms\Admin\AdminExtension;
use S2\Cms\Admin\AdminRequestHandler;
use S2\Cms\CmsExtension;
use S2\Cms\Comment\SpamDetectorComment;
use S2\Cms\Comment\SpamDetectorInterface;
use S2\Cms\Comment\SpamDetectorReport;
use S2\Cms\Config\StringProxy;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Extensions\ExtensionManager;
use S2\Cms\Framework\Application;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\StatefulServiceInterface;
use S2\Cms\Model\Installer;
use S2\Cms\Model\PermissionChecker;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use s2_extensions\s2_blog\Manifest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Support\Helper\AbstractBrowserModule;

// "Tests\Support\Helper\AbstractBrowserModule" is loaded in AdminYard via autoload-dev and is not available here
require_once __DIR__ . '/../../../_vendor/s2/admin-yard/tests/Support/Helper/AbstractBrowserModule.php';

class Integration extends AbstractBrowserModule
{
    protected const ROOT_DIR = __DIR__ . '/../../../';
    protected ?Application $publicApplication = null;
    protected ?Application $adminApplication = null;
    protected ?Session $session;
    protected ?\PDO $pdo;
    private bool $spamDetectorDecorated = false;
    private bool $commentMailerDecorated = false;
    private array $spamResponses = [];
    private array $moderatorMails = [];

    /**
     * @throws ContainerExceptionInterface
     * @throws DbLayerException
     * @throws NotFoundExceptionInterface
     */
    public function _initialize()
    {
        parent::_initialize();
        $this->clearConfigCache();
        $this->publicApplication = $this->createApplication();
        $this->pdo               = $this->publicApplication->container->get(\PDO::class);

        $this->adminApplication = $this->createAdminApplication();
        $this->adminApplication->container->decorate(\PDO::class, function () {
            return $this->pdo;
        });
        $this->decorateSpamDetector();
        $this->decorateCommentMailer();

        $adminDbLayer = $this->adminApplication->container->get(DbLayer::class);
        (new Manifest())->uninstall($adminDbLayer, $this->adminApplication->container);
        (new \s2_extensions\s2_search\Manifest())->uninstall($adminDbLayer, $this->adminApplication->container);
        $installer = new Installer($adminDbLayer);
        $installer->dropTables();
        $installer->createTables();

        $installer->insertConfigData('Test site', 'admin@example.com', 'English', 19);
        $installer->insertMainPage('Main page', time());
        $this->createUsers();

        $this->session = new Session(new MockArraySessionStorage());

        /**
         * Install extensions here since CREATE TABLE triggers implicit commit on test transactions in MySQL
         */
        /** @var ExtensionManager $extensionManager */
        $extensionManager = $this->adminApplication->container->get(ExtensionManager::class);
        $extensionManager->installExtension('s2_blog');
        $extensionManager->installExtension('s2_search');
        $this->clearConfigCache();
    }

    public function _before(TestInterface $test)
    {
        $this->clearConfigCache();
        $this->pdo->beginTransaction();
        $this->session->clear();
        $this->spamResponses  = [];
        $this->moderatorMails = [];
    }

    public function _after(TestInterface $test)
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function createApplication(): Application
    {
        $application = new Application();
        $application->addExtension(new CmsExtension());
        $application->addExtension(new \s2_extensions\s2_blog\Extension());
        $application->addExtension(new \s2_extensions\s2_search\Extension());
        $application->boot($this->collectParameters());

        return $application;
    }

    public function createAdminApplication(): Application
    {
        $application = new Application();
        $application->addExtension(new CmsExtension());
        $application->addExtension(new AdminExtension());
        $application->addExtension(new \s2_extensions\s2_blog\Extension());
        $application->addExtension(new \s2_extensions\s2_blog\AdminExtension());
        $application->addExtension(new \s2_extensions\s2_search\Extension());
        $application->addExtension(new \s2_extensions\s2_search\AdminExtension());
        $application->boot($this->collectParameters());

        return $application;
    }

    public function grabService(string $serviceName): mixed
    {
        return $this->publicApplication->container->get($serviceName);
    }

    public function grabAdminService(string $serviceName): mixed
    {
        return $this->adminApplication->container->get($serviceName);
    }

    public function setSpamResponses(array $statuses): void
    {
        $this->spamResponses = $statuses;
    }

    public function grabModeratorMails(): array
    {
        return $this->moderatorMails;
    }

    public function shiftSpamResponse(): string
    {
        return array_shift($this->spamResponses) ?? SpamDetectorReport::STATUS_HAM;
    }

    public function recordModeratorMail(array $mail): void
    {
        $this->moderatorMails[] = $mail;
    }

    /**
     * Set config value and regenerate config cache for both applications.
     */
    public function setConfigValue(string $name, string $value): void
    {
        $statement = $this->pdo->prepare('UPDATE config SET value = :value WHERE name = :name');
        $statement->execute([':value' => $value, ':name' => $name]);

        $this->publicApplication->container->get(DynamicConfigProvider::class)->regenerate();
        $this->adminApplication->container->get(DynamicConfigProvider::class)->regenerate();

        $this->clearStatefulServices($this->publicApplication->container);
        $this->clearStatefulServices($this->adminApplication->container);
        $this->publicApplication->container->clearByTag('dynamic_config_dependent');
        $this->adminApplication->container->clearByTag('dynamic_config_dependent');
    }

    private function clearStatefulServices(Container $container): void
    {
        foreach ($container->getByTagIfInstantiated(StatefulServiceInterface::class) as $service) {
            /** @var StatefulServiceInterface $service */
            $service->clearState();
        }
    }

    protected function collectParameters(): array
    {
        $imgDir = '_tests/_output/images';

        $result = [
            'root_dir'           => self::ROOT_DIR,
            'cache_dir'          => '_cache/test/',
            'log_dir'            => '_cache/test/',
            'image_dir'          => self::ROOT_DIR . $imgDir . '/', // filesystem
            'image_path'         => '/' . $imgDir, // web URL prefix
            'allowed_extensions' => 'gif bmp jpg jpeg png ico svg mp3 wav ogg flac mp4 avi flv mpg mpeg mkv zip 7z rar doc docx ppt pptx odt odt odp ods xlsx xls pdf txt rtf csv',
            'disable_cache'      => false,
            'base_url'           => 'http://s2.localhost',
            'base_path'          => '',
            'url_prefix'         => '',
            'debug'              => false,
            'debug_view'         => false,
            'show_queries'       => false,
            'redirect_map'       => [
                '#^/redirect$#' => '/redirected',
            ],
            'version'            => '2.0dev',
            'canonical_url'      => null,

            'cookie_name'       => 's2_cookie_904732485',
            'force_admin_https' => true,
            'db_host'           => '127.0.0.1',
            'db_name'           => 's2_test',
            'db_prefix'         => '',
            'p_connect'         => false,
            ...(match (getenv('APP_DB_TYPE')) {
                'sqlite' => ['db_type' => 'sqlite', 'db_username' => '', 'db_password' => ''],
                'pgsql' => ['db_type' => 'pgsql', 'db_username' => 'postgres', 'db_password' => '12345'],
                default => ['db_type' => 'mysql', 'db_username' => 'root', 'db_password' => ''],
            })
        ];

        return $result;
    }

    /**
     * @throws DbLayerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function doRealRequest(Request $request): Response
    {
        if ($request->getPathInfo() === '/_admin/index.php') {
            /** @var AdminRequestHandler $handler */
            $handler = $this->adminApplication->container->get(AdminRequestHandler::class);
            return $handler->handle($request);
        }

        if ($request->getPathInfo() === '/_admin/ajax.php') {
            /** @var AdminAjaxRequestHandler $handler */
            $handler = $this->adminApplication->container->get(AdminAjaxRequestHandler::class);
            return $handler->handle($request);
        }

        return $this->publicApplication->handle($request);
    }

    private function createUsers(): void
    {
        $roleMapping = [
            'nobody'          => '',
            'guest'           => PermissionChecker::PERMISSION_VIEW,
            'power_guest'     => PermissionChecker::PERMISSION_VIEW_HIDDEN,
            'moderator'       => PermissionChecker::PERMISSION_HIDE_COMMENTS,
            'power_moderator' => PermissionChecker::PERMISSION_EDIT_COMMENTS,
            'author'          => PermissionChecker::PERMISSION_CREATE_ARTICLES,
            'editor'          => PermissionChecker::PERMISSION_EDIT_SITE,
            'admin'           => PermissionChecker::PERMISSION_EDIT_USERS,
        ];
        foreach ($roleMapping as $role => $enabledPermission) {
            $fields = [
                'login'           => $role,
                'password'        => password_hash($role, PASSWORD_DEFAULT),
                'email'           => $role . '@example.com',
                'view'            => $enabledPermission !== '' ? 1 : 0,
                'view_hidden'     => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_VIEW_HIDDEN ? 1 : 0,
                'hide_comments'   => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_HIDE_COMMENTS ? 1 : 0,
                'edit_comments'   => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_EDIT_COMMENTS ? 1 : 0,
                'create_articles' => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_CREATE_ARTICLES ? 1 : 0,
                'edit_site'       => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_EDIT_SITE ? 1 : 0,
                'edit_users'      => $role === 'admin' || $enabledPermission === PermissionChecker::PERMISSION_EDIT_USERS ? 1 : 0,
            ];


            $statement = $this->pdo->prepare('INSERT INTO users (' . implode(', ', array_keys($fields)) . ') VALUES (' . implode(', ', array_fill(0, \count($fields), '?')) . ')');
            $statement->execute(array_values($fields));
        }
    }

    private static function deleteRecursive($dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $array = scandir($dir);
        if ($array === false) {
            return false;
        }
        $files = array_diff($array, array('.', '..'));
        foreach ($files as $file) {
            is_dir("$dir/$file") ? self::deleteRecursive("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }

    private function clearConfigCache(): void
    {
        @self::deleteRecursive(self::ROOT_DIR . '_cache/test/config/');
        @unlink(self::ROOT_DIR . '_cache/test/cache_config.php');
    }

    private function decorateSpamDetector(): void
    {
        if ($this->spamDetectorDecorated) {
            return;
        }

        $decorator = function (Container $container, callable $factory) {
            return new class($this) implements SpamDetectorInterface {
                public function __construct(private Integration $helper)
                {
                }

                public function getReport(SpamDetectorComment $comment, string $clientIp): SpamDetectorReport
                {
                    $status = $this->helper->shiftSpamResponse();
                    return match ($status) {
                        SpamDetectorReport::STATUS_SPAM => SpamDetectorReport::spam(),
                        SpamDetectorReport::STATUS_BLATANT => SpamDetectorReport::blatant(),
                        SpamDetectorReport::STATUS_FAILED => SpamDetectorReport::failed(),
                        SpamDetectorReport::STATUS_DISABLED => SpamDetectorReport::disabled(),
                        default => SpamDetectorReport::ham(),
                    };
                }
            };
        };

        $this->publicApplication->container->decorate(SpamDetectorInterface::class, $decorator);
        $this->adminApplication->container->decorate(SpamDetectorInterface::class, $decorator);
        $this->spamDetectorDecorated = true;
    }

    private function decorateCommentMailer(): void
    {
        if ($this->commentMailerDecorated) {
            return;
        }

        $decorator = function (Container $container, callable $factory) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);

            return new IntegrationCommentMailer(
                $container->get('comments_translator'),
                $provider->getStringProxy('S2_WEBMASTER'),
                $provider->getStringProxy('S2_WEBMASTER_EMAIL'),
                $this
            );
        };

        $this->publicApplication->container->decorate(\S2\Cms\Mail\CommentMailer::class, $decorator);
        $this->commentMailerDecorated = true;
    }
}

readonly class IntegrationCommentMailer extends \S2\Cms\Mail\CommentMailer
{
    public function __construct(
        \Symfony\Contracts\Translation\TranslatorInterface $translator,
        StringProxy                                        $webmasterName,
        StringProxy                                        $webmasterEmail,
        private Integration                                $helper
    ) {
        parent::__construct($translator, $webmasterName, $webmasterEmail);
    }

    public function mailToModerator(
        string $moderatorName,
        string $moderatorEmail,
        string $text,
        string $title,
        string $url,
        string $authorName,
        string $authorEmail,
        bool   $isPublished,
        string $spamReportStatus,
    ): bool {
        $this->helper->recordModeratorMail(compact(
            'moderatorName',
            'moderatorEmail',
            'text',
            'title',
            'url',
            'authorName',
            'authorEmail',
            'isPublished',
            'spamReportStatus'
        ));
        return true;
    }

    public function mailToSubscriber(
        string $subscriberName,
        string $subscriberEmail,
        string $text,
        string $title,
        string $url,
        string $authorName,
        string $unsubscribeLink
    ): bool {
        return true;
    }
}
