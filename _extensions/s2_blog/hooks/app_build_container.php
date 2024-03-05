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

use S2\Cms\Config\DynamicConfigProvider;
use S2\Cms\Framework\Container;
use S2\Cms\Pdo\DbLayer;
use S2\Cms\Recommendation\RecommendationProvider;
use S2\Cms\Template\HtmlTemplateProvider;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\Controller\BlogRss;
use s2_extensions\s2_blog\Controller\DayPageController;
use s2_extensions\s2_blog\Controller\FavoritePageController;
use s2_extensions\s2_blog\Controller\MainPageController;
use s2_extensions\s2_blog\Controller\MonthPageController;
use s2_extensions\s2_blog\Controller\PostPageController;
use s2_extensions\s2_blog\Controller\Sitemap;
use s2_extensions\s2_blog\Controller\TagPageController;
use s2_extensions\s2_blog\Controller\TagsPageController;
use s2_extensions\s2_blog\Controller\YearPageController;

if (!isset($this)) {
    die;
}

if (!$this instanceof \S2\Cms\Application) {
    throw new LogicException('This hook must be called inside Application class.');
}

$this->container->set(MainPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new MainPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
        (int)$provider->get('S2_MAX_ITEMS')
    );
});
$this->container->set(DayPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new DayPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
    );
});
$this->container->set(MonthPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new MonthPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
        $provider->get('S2_START_YEAR'),
    );
});
$this->container->set(YearPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new YearPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
        $provider->get('S2_START_YEAR'),
    );
});
$this->container->set(PostPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new PostPageController(
        $container->get(DbLayer::class),
        $container->get(RecommendationProvider::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
        $provider->get('S2_SHOW_COMMENTS') === '1',
    );
});
$this->container->set(TagsPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new TagsPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
    );
});
$this->container->set(TagPageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new TagPageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
        $provider->get('S2_USE_HIERARCHY') === '1',
    );
});
$this->container->set(FavoritePageController::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new FavoritePageController(
        $container->get(DbLayer::class),
        $container->get(HtmlTemplateProvider::class),
        $container->get(Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
    );
});
$this->container->set(BlogRss::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new BlogRss(
        $container->get('strict_viewer'),
        $container->getParameter('base_url'),
        $provider->get('S2_WEBMASTER'),
        $provider->get('S2_SITE_NAME'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
    );
});
$this->container->set(Sitemap::class, function (Container $container) {
    /** @var DynamicConfigProvider $provider */
    $provider = $container->get(DynamicConfigProvider::class);
    return new Sitemap(
        $container->get(DbLayer::class),
        $container->get('strict_viewer'),
        $provider->get('S2_BLOG_URL'),
    );
});

/** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher */
$eventDispatcher = $this->container->get(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);

$container = $this->container;

$eventDispatcher->addListener(\S2\Cms\Template\HtmlTemplateCreatedEvent::class, function (\S2\Cms\Template\HtmlTemplateCreatedEvent $event) use ($container) {
    $blogPlaceholders = [];
    $template         = $event->htmlTemplate;

    foreach (['s2_blog_last_comments', 's2_blog_last_discussions', 's2_blog_last_post'] as $blogPlaceholder) {
        if ($template->hasPlaceholder('<!-- ' . $blogPlaceholder . ' -->')) {
            $blogPlaceholders[$blogPlaceholder] = 1;
        }
    }

    if (count($blogPlaceholders) === 0) {
        return;
    }

    Lang::load('s2_blog', function () {
        if (file_exists(S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php'))
            return require S2_ROOT . '/_extensions/s2_blog' . '/lang/' . S2_LANGUAGE . '.php';
        else
            return require S2_ROOT . '/_extensions/s2_blog' . '/lang/English.php';
    });

    /** @var Viewer $viewer */
    $viewer = $container->get(Viewer::class);

    if (isset($blogPlaceholders['s2_blog_last_comments'])) {
        $recentComments = s2_extensions\s2_blog\Placeholder::recent_comments();

        $template->registerPlaceholder('<!-- s2_blog_last_comments -->', empty($recentComments) ? '' : $viewer->render('menu_comments', [
            'title' => Lang::get('Last comments', 's2_blog'),
            'menu'  => $recentComments,
        ]));
    }

    if (isset($blogPlaceholders['s2_blog_last_discussions'])) {
        $lastDiscussions = s2_extensions\s2_blog\Placeholder::recent_discussions();

        $template->registerPlaceholder('<!-- s2_blog_last_discussions -->', empty($lastDiscussions) ? '' : $viewer->render('menu_block', [
            'title' => Lang::get('Last discussions', 's2_blog'),
            'menu'  => $lastDiscussions,
            'class' => 's2_blog_last_discussions',
        ]));
    }
    if (isset($blogPlaceholders['s2_blog_last_post'])) {
        $lastPosts = s2_extensions\s2_blog\Lib::last_posts_array(1);

        foreach ($lastPosts as &$s2_blog_post) {
            $s2_blog_post = $viewer->render('post_short', $s2_blog_post, 's2_blog');
        }
        unset($s2_blog_post);
        $template->registerPlaceholder('<!-- s2_blog_last_post -->', implode('', $lastPosts));
    }
});
