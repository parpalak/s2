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
use s2_extensions\s2_blog\Controller\DayPageController;
use s2_extensions\s2_blog\Controller\FavoritePageController;
use s2_extensions\s2_blog\Controller\MainPageController;
use s2_extensions\s2_blog\Controller\MonthPageController;
use s2_extensions\s2_blog\Controller\PostPageController;
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
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
        $container->get(\S2\Cms\Template\Viewer::class),
        $provider->get('S2_TAGS_URL'),
        $provider->get('S2_BLOG_URL'),
        $provider->get('S2_BLOG_TITLE'),
    );
});
