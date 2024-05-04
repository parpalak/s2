<?php
/**
 * Creates RSS feeds.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Controller;

use S2\Cms\Framework\ControllerInterface;
use S2\Cms\Model\ArticleProvider;
use S2\Cms\Template\Viewer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class Rss implements ControllerInterface
{
    public function __construct(
        protected ArticleProvider $articleProvider,
        protected Viewer $viewer,
        protected string $baseUrl,
        protected string $webmaster,
        protected string $siteName,
    ) {
    }

    public function handle(Request $request): Response
    {
        $return = ($hook = s2_hook('pr_render_start')) ? eval($hook) : null;
        if ($return) {
            return $return;
        }

        $content = [
            'rss_title'       => $this->title(),
            'rss_link'        => s2_abs_link($this->link()),
            'self_link'       => $request->getPathInfo(),
            'rss_description' => $this->description(),
        ];

        $modifiedSince   = $request->headers->get('If-Modified-Since');
        $lastRequestTime = $modifiedSince !== null ? strtotime($modifiedSince) : 0;

        $maxContentTime = 0;
        $items          = '';

        ($hook = s2_hook('pr_render_pre_get_content')) ? eval($hook) : null;

        foreach ($this->content() as $item) {
            $itemUpdatedAt = max($item['modify_time'], $item['time']);
            if ($itemUpdatedAt <= $lastRequestTime) {
                // We have already sent this item in the previous response
                continue;
            }

            $maxContentTime = max($maxContentTime, $itemUpdatedAt);

            // Fixing URLs without a domain
            $item['text'] = str_replace('href="' . S2_PATH . '/', 'href="' . $this->baseUrl . '/', $item['text']);
            $item['text'] = str_replace('src="' . S2_PATH . '/', 'src="' . $this->baseUrl . '/', $item['text']);

            if (empty($item['author']) && $this->webmaster) {
                $item['author'] = $this->webmaster;
            }

            $item['link'] = s2_abs_link($item['rel_path']);

            ($hook = s2_hook('pr_render_pre_item_render')) ? eval($hook) : null;

            $items .= $this->viewer->render('rss_item', $item);
        }

        if (!$items && $lastRequestTime > 0) {
            return new Response(null, Response::HTTP_NOT_MODIFIED);
        }

        $output = $this->viewer->render('rss', $content + compact('items', 'maxContentTime'));

        $response = new Response($output);
        $response->headers->set('Content-Length', (string)\strlen($output));
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setLastModified(new \DateTimeImmutable('@' . $maxContentTime));

        return $response;
    }

    protected function content(): array
    {
        return $this->articleProvider->lastArticlesList(10);
    }

    protected function title(): string
    {
        return $this->siteName;
    }

    protected function link(): string
    {
        return '/';
    }

    protected function description(): string
    {
        return sprintf(\Lang::get('RSS description'), $this->siteName);
    }
}
