<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace Helper;

use Codeception\Module;
use S2\Cms\CmsExtension;
use S2\Cms\Framework\Application;
use S2\Cms\Model\Installer;
use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Integration extends Module
{
    protected ?Application $application = null;
    protected ?Response $response = null;

    public function _initialize()
    {
        $this->application = new Application();
        $this->application->addExtension(new CmsExtension());
        $this->application->boot($this->collectParameters());
        $installer = new Installer($this->application->container->get(DbLayer::class));
        $installer->createTables();
    }

    public function _after(\Codeception\TestInterface $test)
    {
        // Освобождаем ресурсы, если это необходимо
    }

    public function amOnPage(string $url)
    {
        $request        = new Request([], [], [], [], [], ['REQUEST_URI' => $url]);
        $this->response = $this->application->handle($request);
    }

    public function see($text)
    {
        // Метод для проверки наличия текста на странице
        // Используем сохраненный ранее response
        $this->assertTrue(str_contains($this->response->getContent(), $text));
    }

    // Метод для инициализации Request и получения Response
    public function sendRequest($url)
    {
        $request        = new Request($url);
        $this->response = $this->application->handle($request);

        return $this->response;
    }

    protected function collectParameters(): array
    {
        $result = [
            'root_dir'     => '../',
            'cache_dir'    => '_cache/',
            'log_dir'      => '_cache/',
            'base_url'     => 'http://localhost:8881',
            'debug'        => false,
            'debug_view'   => false,
            'show_queries' => false,
            'redirect_map' => [],

            'db_type'     => 'mysql',
            'db_host'     => '127.0.0.1',
            'db_name'     => 's2_test',
            'db_username' => 'root',
            'db_password' => '',
            'db_prefix'   => '',
            'p_connect'   => false,
        ];

        return $result;
    }
}
