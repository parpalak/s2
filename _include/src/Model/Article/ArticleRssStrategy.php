<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model\Article;

use S2\Cms\Controller\Rss\FeedDto;
use S2\Cms\Controller\Rss\FeedItemDto;
use S2\Cms\Controller\Rss\RssStrategyInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Pdo\DbLayerException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ArticleRssStrategy implements RssStrategyInterface
{
    public function __construct(
        private ArticleProvider     $articleProvider,
        private UrlBuilder          $urlBuilder,
        private TranslatorInterface $translator,
        private string              $siteName
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedInfo(): FeedDto
    {
        return new FeedDto(
            $this->siteName,
            sprintf($this->translator->trans('RSS description'), $this->siteName),
            $this->urlBuilder->absLink('/')
        );
    }

    /**
     * {@inheritdoc}
     * @throws DbLayerException
     */
    public function getFeedItems(): array
    {
        $result = [];
        foreach ($this->articleProvider->lastArticlesList(10) as $article) {
            $result[] = new FeedItemDto(
                $article['title'],
                $article['author'],
                $this->urlBuilder->absLink($article['rel_path']),
                $article['text'],
                $article['time'],
                $article['modify_time'],
            );
        }
        return $result;
    }
}
