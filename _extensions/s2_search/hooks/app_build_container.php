<?php
/**
 * Hook app_build_container
 *
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 *
 * @var \S2\Cms\Application $this
 */

declare(strict_types=1);

use S2\Cms\Template\HtmlTemplateCreatedEvent;

if (!defined('S2_ROOT')) {
    die;
}

/** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */
$eventDispatcher = $this->container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);

$eventDispatcher->addListener(HtmlTemplateCreatedEvent::class, function (HtmlTemplateCreatedEvent $event) {
    Lang::load('s2_search', function () {
        if (file_exists(S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php'))
            return require S2_ROOT . '/_extensions/s2_search' . '/lang/' . S2_LANGUAGE . '.php';
        else
            return require S2_ROOT . '/_extensions/s2_search' . '/lang/English.php';
    });
    $event->htmlTemplate->registerPlaceholder('<!-- s2_search_field -->', '<form class="s2_search_form" method="get" action="' . (S2_URL_PREFIX ? S2_PATH . S2_URL_PREFIX : S2_PATH . '/search') . '">' . (S2_URL_PREFIX ? '<input type="hidden" name="search" value="1" />' : '') . '<input type="text" name="q" id="s2_search_input" placeholder="' . Lang::get('Search', 's2_search') . '"/></form>');
});
