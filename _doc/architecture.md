# S2 Architecture Overview

## Config Parameters
There are two types of S2 config parameters:

| Static                                                                                          | Dynamic                                                                                          |
|-------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------|
| [Stored in the `config.php`](https://github.com/parpalak/s2/wiki/Configuration)                 | Stored in the database (`config` table)                                                          |
| Created during the installation process                                                         | Created from default values and user input during installation                                   |
| Can only be edited later by modifying the file on the server                                    | Editable in [the control panel](https://github.com/parpalak/s2/wiki/Control-Panel#configuration) |
| Loaded into the container, accessible via `$container->getParameter()` in container definitions | Accessible via the `DynamicConfigProvider` service that fetches them from cache or the DB        |
| No additional cache is required                                                                 | Cached in a file by `DynamicConfigProvider` service                                              |
| –                                                                                               | Affect routing, i.e. routes matching is possible only after the container is initialized         |

## Components
- [**Application**](../_include/src/Framework/Application.php) – part of the framework, does not contain S2 CMS logic.
    - Gathers the following information from extensions:
        - Container definitions
        - Event listeners
        - Routing
    - Has a method `public function handle(Request $request): Response`, which converts
      any requests to the public pages into a response.

- **Application Extensions** – classes that implement the [`ExtensionInterface`](../_include/src/Framework/ExtensionInterface.php).
    - There is [`CmsExtension`](../_include/src/CmsExtension.php) that contains the core S2 CMS logic for public pages.
    - There is [`AdminExtension`](../_include/src/Admin/AdminExtension.php) that defines additional control panel services and events. This separation is for performance reasons.
    - [S2 extensions](extensions.md#s2-application-extensions) can also implement `ExtensionInterface` for public pages and for the control panel.

- **Controllers**
    - Implement the method `public function handle(Request $request): Response` from [`ControllerInterface`](../_include/src/Framework/ControllerInterface.php).
    - Do not check whether they should process the request; this logic is fully delegated to the router.
    - Convert a request into a response.

- [**Page Templates**](https://github.com/parpalak/s2/wiki/Templates)
    - Contain HTML markup defining the presence and layout of major page blocks.
    - Blocks are marked with special placeholder tags.
    - The controller selects the template type and retrieves its content via [`HtmlTemplateProvider::getTemplate()`](../_include/src/Template/HtmlTemplateProvider.php). Template lookup logic:
        - `_styles/{style_name}/templates/`
        - `_extensions/{extension_id}/templates/` (if an additional `$extraDir` parameter is provided)
        - Otherwise, `_include/templates/` (if no `$extraDir` is specified).

- [**Views**](https://github.com/parpalak/s2/wiki/Views)
    - **Global views**: Defined in `_include/` and overridden in theme folders. Cannot be overridden by extensions (to avoid conflicts when multiple extensions attempt to override the same view).
    - **Extension-level views**: Require explicitly specifying the directory in `Viewer::render()`. Can also be overridden in themes.

## Extensions
- Must contain a class named `Manifest` that implement [`ManifestInterface`](../_include/src/Extensions/ManifestInterface.php) (provides extension metadata).
- Whether an extension is enabled is stored in the DB (the `extensions.disabled` field) and is not accessible via `DynamicConfigProvider`).
