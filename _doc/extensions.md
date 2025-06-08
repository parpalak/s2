# Developing Extensions for S2

Extensions are pluggable components that enhance the functionality of the S2 CMS.
Each extension resides in its own directory under the `_extensions/` folder and must include a single mandatory file,
with several optional files that are automatically recognized and used by the system.

## Required File: `Manifest.php`

Each extension **must** include a `Manifest.php` file,
containing a `Manifest` class that implements `S2\Cms\Extensions\ManifestInterface`.

Manifest describes the extension and may contain the code that will be executed
when the extension is installed, updated, or uninstalled.

Example:

```php
<?php

declare(strict_types=1);

namespace s2_extensions\extension_name;

use S2\Cms\Extensions\Manifest;
use S2\Cms\Extensions\ManifestInterface;

class Manifest extends Manifest implements ManifestInterface
{
    public function getName(): string
    {
        return 'Your Extension Name';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    // ...
    
    public function install(DbLayer $dbLayer, Container $container, ?string $currentVersion): void
    {
        // Setup posts table
        if (!$dbLayer->tableExists('extension_name_table')) {
            $schema = [
                'FIELDS'       => [
                    'id'          => [
                        'datatype'   => 'SERIAL',
                        'allow_null' => false
                    ],
                    'name'       => [
                        'datatype'   => 'VARCHAR(255)',
                        'allow_null' => false,
                        'default'    => '\'\''
                    ],
                ],
                'PRIMARY KEY'  => ['id'],
                'INDEXES'      => [
                    'name_idx' => ['name'],
                ],
            ];
        }

        // Add extension options to the config table
        if ($currentVersion === null || version_compare($currentVersion, '0.1', '<')) {
            $config = [
                'EXTENSION_NAME_PARAM1' => 'value1',
            ];
    
            foreach ($config as $confName => $confValue) {
                $query = [
                    'INSERT' => 'name, value',
                    'INTO'   => 'config',
                    'VALUES' => '\'' . $confName . '\', \'' . $confValue . '\''
                ];
    
                $dbLayer->buildAndQuery($query);
            }
        }
    }

    public function uninstall(DbLayer $dbLayer, Container $container): void
    {
        if ($dbLayer->tableExists('config')) {
            $dbLayer->buildAndQuery([
                'DELETE' => 'config',
                'WHERE'  => 'name in (\'EXTENSION_NAME_PARAM1\')',
            ]);
        }

        $dbLayer->dropTable('extension_name_table');
    }
}
```

You can use `S2\Cms\Extensions\ManifestTrait` to get a reasonable default implementation for some methods. 

## S2 Application Extensions

If present, these files are automatically discovered and registered by the S2 application:
- `Extension.php` – used for the **public pages**.
- `AdminExtension.php` – used in the **control panel**.

They must define classes implementing `S2\Cms\Framework\ExtensionInterface`:
- Define new services in the DI container via `buildContainer()`
- Register event listeners via `registerListeners()`
- Add public routes via `registerRoutes()`

The most important part of the extension is the `registerListeners()` method,
which registers event listeners to the events fired by the S2 core and other extensions.
There is no documented events list due to the active development.

Let's take a look at a simple example:

```php
<?php

declare(strict_types=1);

namespace s2_extensions\extension_name;

use S2\Cms\Asset\AssetPack;
use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Framework\ExtensionInterface;
use S2\Cms\Model\Article\ArticleRenderedEvent;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\TemplateAssetEvent;
use S2\Cms\Template\Viewer;
use S2\Cms\Translation\ExtensibleTranslator;
use s2_extensions\extension_name\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

class Extension implements ExtensionInterface
{
    public function buildContainer(Container $container): void
    {
        // Example for defining custom translations
        $container->set('extension_name_translator', static function (Container $container) {
            /** @var ExtensibleTranslator $translator */
            $translator = $container->get('translator');
            $translator->attachLoader('extension_name', static function (string $lang) {
                return require ($dir = __DIR__ . '/lang/') . (file_exists($dir . $lang . '.php') ? $lang : 'English') . '.php';
            });

            return $translator;
        });
        
        // Example for defining a new controller
        $container->set(Controller::class, static function (Container $container) {
            /** @var DynamicConfigProvider $provider */
            $provider = $container->get(DynamicConfigProvider::class);
            return new Controller(
                $container->get(DbLayer::class),
                $container->get('extension_name_translator'),
                $container->get(Viewer::class),
                $container->get(RequestStack::class),
                $container->get('config_cache'),
                $provider->get('S2_SHOW_COMMENTS') === '1', // bool parameter
                (int)$provider->get('S2_MAX_ITEMS'),
                $container->getParameter('url_prefix'),
            );
        });
    }

    public function registerListeners(EventDispatcherInterface $eventDispatcher, Container $container): void
    {
        // Example for defining a new placeholder
        $eventDispatcher->addListener(ArticleRenderedEvent::class, static function (ArticleRenderedEvent $event) use ($container) {
            if ($event->template->hasPlaceholder('<!-- extension_name_placeholder -->')) {
                /** @var Viewer $viewer */
                $viewer = $container->get(Viewer::class);
                /** @var TranslatorInterface $translator */
                $translator = $container->get('extension_name_translator');
                /** @var YourService $provider */
                $provider = $container->get(YourService::class);

                $data = $provider->getData($event->articleId);
                $event->template->registerPlaceholder('<!-- extension_name_placeholder -->', empty($data) ? '' : $viewer->render('some_view', [
                    'title' => $translator->trans('Some title'),
                    'data'  => $data,
                ]));
            }
        });

        // Example for adding extension assets
        $eventDispatcher->addListener(TemplateAssetEvent::class, static function (TemplateAssetEvent $event) {
            $event->assetPack->addCss('../../_extensions/extension_name/style.css', [AssetPack::OPTION_MERGE]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function registerRoutes(RouteCollection $routes, Container $container): void
    {
        // Example for adding a new page or section with a custom controller
        $routes->add('extension_name_page', new Route(
            '/new_section/',
            ['_controller' => Controller::class],
            options: ['utf8' => true],
            methods: ['GET'],
        ));
    }
}
```

You can look at the source code of other extensions for more advanced examples.

## Language Files

Extensions can define its own translator and provide custom translation strings.
In example above, the translator is defined in the `buildContainer()` method.
It assumes that the language files are located in the `_extensions/extension_name/lang/` directory.
For example, a language file can be in `_extensions/extension_name/lang/English.php` with the following content:

```php
<?php

return [
    'Some title' => 'Title of a custom widget',
];
```

## Templates and Views

Extensions can add new [templates and views](https://github.com/parpalak/s2/wiki/Styles#html-page-generation-with-templates-and-views).
They must be placed in the `_extensions/extension_name/templates/`
and `_extensions/extension_name/views/` directories, respectively.

To use new templates and views, you must specify the extension directory
when getting the template content and rendering the view:

```php
$template = $this->templateProvider->getTemplate('template.php', 'extension_name');
$template->putInPlaceholder('text', $this->viewer->render('view.php', [...], 'extension_name'));
```

## Versioning

Extension versions follow [Semantic Versioning](https://semver.org/):
- The format is 1.2.3, where 1 is the major version, 2 is the minor version and 3 is the release.
- Extensions in beta stages should be marked as 0.x.x.
- After each time new functionality is added, the minor number should be increased.
- When there are breaking changes, the major number should be increased.
- Small bugfixes should increase the release number.

## Extensions Must Be Able to Be Disabled

Extensions in S2 can be disabled from the control panel.
This action does not run uninstall code,
it only disables the usage of `Extension` and `AdminExtension` classes.
It is important to keep in mind this situation when developing your extension.
As a result, in general you cannot perform destructive actions on the core database
(eg: delete a core configuration value, shrink a column to an unusable size, drop a table or column, etc.).

It is also important to make sure that any files in your extension that are accessed directly
return some form of error message if they are accessed when the extension is disabled.

## Extensible Extensions

You can split your extension into smaller extensions and connect them using dependencies.
For example, a donation feature could be a separate extension that relies on a payment extension.
This way, the payment extension can be used on its own or with other extensions,
without the donation extension. 

You can also design your extensions to be extendable.
By adding events, you allow other developers to modify or enhance your extension’s behavior.
To ensure compatibility, include any required extensions as dependencies in your manifest.

## Best Practices

- Use namespaces with the following `s2_extensions\{extension_name}` pattern.
- Keep business logic in separate service classes.
- Prefer DI to static calls whenever possible.
- Leverage dependency injection rather than global state.
- Always check for table existence in migrations/uninstalls.
- Keep logic for public pages and control panel in separate classes (`Extension` and `AdminExtension`).
