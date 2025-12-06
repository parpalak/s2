<?php
/**
 * RSS feed for blog.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Model;

use S2\Cms\Config\StringProxy;
use S2\Cms\Controller\Rss\FeedDto;
use S2\Cms\Controller\Rss\FeedItemDto;
use S2\Cms\Controller\Rss\RssStrategyInterface;
use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\Viewer;
use s2_extensions\s2_blog\BlogUrlBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class BlogRssStrategy implements RssStrategyInterface
{
    public function __construct(
        private PostProvider        $postProvider,
        private BlogUrlBuilder      $blogUrlBuilder,
        private TranslatorInterface $translator,
        private Viewer              $viewer,
        private StringProxy         $blogTitle,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedInfo(): FeedDto
    {
        $blogTitle = $this->blogTitle->get();

        return new FeedDto(
            $blogTitle,
            \sprintf($this->translator->trans('RSS blog description'), $blogTitle),
            $this->blogUrlBuilder->absMain(),
        );
    }

    /**
     * @throws DbLayerException
     */
    public function getFeedItems(): array
    {
        $posts  = $this->postProvider->lastPostsArray();
        $viewer = $this->viewer;
        $items  = [];
        foreach ($posts as $post) {
            $items[] = new FeedItemDto(
                $post['title'],
                $post['author'],
                $this->blogUrlBuilder->absPostFromTimestamp($post['create_time'], $post['url']),
                $post['text'] .
                (empty($post['see_also']) ? '' : $viewer->render('see_also', [
                    'see_also' => $post['see_also']
                ], 's2_blog')) .
                (empty($post['tags']) ? '' : $viewer->render('tags', [
                    'title' => $this->translator->trans('Tags'),
                    'tags'  => $post['tags'],
                ], 's2_blog')),
                $post['create_time'],
                $post['modify_time']
            );
        }

        return $items;
    }
}
