<?php
/**
 * Creates RSS feeds.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license   MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Controller\Rss\FeedItemRenderEvent;
use S2\Cms\Controller\Rss\FeedRenderEvent;
use S2\Cms\Controller\Rss\RssStrategyInterface;
use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\UrlBuilder;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class RssController implements ControllerInterface
{
    public function __construct(
        private RssStrategyInterface     $rssStrategy,
        private UrlBuilder               $urlBuilder,
        private Viewer                   $viewer,
        private EventDispatcherInterface $eventDispatcher,
        private string                   $basePath,
        private string                   $baseUrl,
        private string                   $webmaster,
    ) {
    }

    public function handle(Request $request): Response
    {
        ($hook = s2_hook('pr_render_start')) ? eval($hook) : null;

        $modifiedSince   = $request->headers->get('If-Modified-Since');
        $lastRequestTime = $modifiedSince !== null ? strtotime($modifiedSince) : 0;

        $maxContentTime = 0;
        $items          = '';

        foreach ($this->rssStrategy->getFeedItems() as $item) {
            $itemUpdatedAt = max($item->modifyTime, $item->time);
            if ($itemUpdatedAt <= $lastRequestTime) {
                // We have already sent this item in the previous response
                continue;
            }

            $maxContentTime = max($maxContentTime, $itemUpdatedAt);

            // Fixing URLs without a domain
            $item->text = str_replace('href="' . $this->basePath . '/', 'href="' . $this->baseUrl . '/', $item->text);
            $item->text = str_replace('src="' . $this->basePath . '/', 'src="' . $this->baseUrl . '/', $item->text);

            if (empty($item->author) && $this->webmaster) {
                $item->author = $this->webmaster;
            }

            $this->eventDispatcher->dispatch(new FeedItemRenderEvent($item));

            $items .= $this->viewer->render('rss_item', ['item' => $item]);
        }

        if ($items === '' && $lastRequestTime > 0) {
            return new Response(null, Response::HTTP_NOT_MODIFIED);
        }

        $feedInfo = $this->rssStrategy->getFeedInfo();
        $selfLink = $this->urlBuilder->absLink($request->getPathInfo());

        $this->eventDispatcher->dispatch(new FeedRenderEvent($feedInfo));

        $output = $this->viewer->render('rss', compact('items', 'maxContentTime', 'feedInfo', 'selfLink') + ['baseUrl' => $this->baseUrl]);

        $response = new Response($output);
        $response->headers->set('Content-Length', (string)\strlen($output));
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setLastModified(new \DateTimeImmutable('@' . $maxContentTime));

        return $response;
    }
}
