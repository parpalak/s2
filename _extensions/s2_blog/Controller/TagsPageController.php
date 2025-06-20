<?php
/**
 * List of blog tags.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

namespace s2_extensions\s2_blog\Controller;

use S2\Cms\Pdo\DbLayerException;
use S2\Cms\Template\HtmlTemplate;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TagsPageController extends BlogController
{
    /**
     * @throws DbLayerException
     */
    public function body(Request $request, HtmlTemplate $template): ?Response
    {
        if ($request->attributes->get('slash') !== '/') {
            return new RedirectResponse($this->urlBuilder->link($request->getPathInfo() . '/'), Response::HTTP_MOVED_PERMANENTLY);
        }

        $template->registerPlaceholder('<!-- s2_blog_navigation -->', '');

        if ($template->hasPlaceholder('<!-- s2_blog_calendar -->')) {
            $template->registerPlaceholder('<!-- s2_blog_calendar -->', $this->calendarBuilder->calendar());
        }

        $result = $this->dbLayer
            ->select('id AS tag_id, name, url')
            ->from('tags')
            ->execute()
        ;

        $tag_name = $tag_url = $tag_count = [];
        while ($row = $result->fetchAssoc()) {
            $tag_name[$row['tag_id']]  = $row['name'];
            $tag_url[$row['tag_id']]   = $row['url'];
            $tag_count[$row['tag_id']] = 0;
        }

        $result = $this->dbLayer
            ->select('pt.tag_id')
            ->from('s2_blog_post_tag AS pt')
            ->innerJoin('s2_blog_posts AS p', 'p.id = pt.post_id')
            ->where('p.published = 1')
            ->execute()
        ;

        while ($row = $result->fetchRow()) {
            $tag_count[$row[0]] = 1 + ($tag_count[$row[0]] ?? 0);
        }

        arsort($tag_count);

        $tags = [];
        foreach ($tag_count as $id => $num) {
            if ($num) {
                $tags[] = [
                    'title' => $tag_name[$id],
                    'link'  => $this->blogUrlBuilder->tag($tag_url[$id]),
                    'num'   => $num,
                ];
            }
        }

        $template->putInPlaceholder('text', $this->viewer->render('tags_list', ['tags' => $tags]));

        $template->addBreadCrumb($this->articleProvider->mainPageTitle(), $this->urlBuilder->link('/'));
        if (!$this->blogUrlBuilder->blogIsOnTheSiteRoot()) {
            $template->addBreadCrumb($this->translator->trans('Blog'), $this->blogUrlBuilder->main());
        }
        $template->addBreadCrumb($this->translator->trans('Tags'));

        $template
            ->putInPlaceholder('head_title', $this->translator->trans('Tags'))
            ->putInPlaceholder('title', $this->translator->trans('Tags'))
            ->setLink('up', $this->blogUrlBuilder->main())
        ;

        return null;
    }
}
