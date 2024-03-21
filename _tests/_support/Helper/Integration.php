<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace Helper;

use Codeception\Module;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use S2\Cms\CmsExtension;
use S2\Cms\Framework\Application;
use S2\Cms\Model\Installer;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Integration extends Module
{
    protected ?Application $application = null;
    protected ?Response $response = null;

    /**
     * @throws ContainerExceptionInterface
     * @throws DbLayerException
     * @throws NotFoundExceptionInterface
     */
    public function _initialize()
    {
        $this->application = new Application();
        $this->application->addExtension(new CmsExtension());
        $this->application->boot($this->collectParameters());
        \Container::setContainer($this->application->container);
        $installer = new Installer($this->application->container->get(DbLayer::class));
        $installer->createTables();
    }

    public function amOnPage(string $url): void
    {
        $request        = new Request([], [], [], [], [], ['REQUEST_URI' => $url]);
        $this->response = $this->application->handle($request);
    }

    public function see(string $text): void
    {
        $this->assertTrue(str_contains($this->response->getContent(), $text));
    }

    public function seeResponseCodeIs(int $code): void
    {
        $this->assertEquals($code, $this->response->getStatusCode());
    }

    public function seeLocationIs(string $location): void
    {
        $this->assertEquals($location, $this->response->headers->get('Location'));
    }

    protected function collectParameters(): array
    {
        $result = [
            'root_dir'      => './',
            'cache_dir'     => '_cache/test/',
            'log_dir'       => '_cache/test/',
            'disable_cache' => false,
            'base_url'      => 'http://s2.localhost',
            'base_path'     => '',
            'url_prefix'    => '',
            'debug'         => false,
            'debug_view'    => false,
            'show_queries'  => false,
            'redirect_map'  => [
                '#^/redirect$#' => '/redirected',
            ],

            'db_host'     => '127.0.0.1',
            'db_name'     => 's2_test',
            'db_prefix'   => '',
            'p_connect'   => false,
            ...(match (getenv('APP_DB_TYPE')) {
                'sqlite' => ['db_type' => 'sqlite', 'db_username' => '', 'db_password' => ''],
                'pgsql'  => ['db_type' => 'pgsql', 'db_username' => 'postgres', 'db_password' => '12345'],
                default => ['db_type' => 'mysql', 'db_username' => 'root', 'db_password' => ''],
            })
        ];

        return $result;
    }
}
