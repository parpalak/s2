<?php
/**
 * Blog posts for a year.
 *
 * @copyright (C) 2007-2014 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_blog
 */

namespace s2_extensions\s2_blog;
use \Lang;
use S2\Cms\Pdo\DbLayer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class Page_Year extends Page_HTML implements \Page_Routable
{
    public function body (Request $request): ?Response
    {
        $params = $request->attributes->all();

		if ($this->inTemplate('<!-- s2_blog_calendar -->'))
			$this->page['s2_blog_calendar'] = Lib::calendar($params['year'], '', 0);

		$this->page['title'] = '';

		// Posts of a year
		$this->page = $this->year_posts($params['year']) + $this->page;

		// Bread crumbs
		$this->page['path'][] = array(
			'title' => \Model::main_page_title(),
			'link'  => s2_link('/'),
		);
		if (S2_BLOG_URL)
		{
			$this->page['path'][] = array(
				'title' => Lang::get('Blog', 's2_blog'),
				'link' => S2_BLOG_PATH,
			);
		}

		$this->page['path'][] = array(
			'title' => $params['year']
		);

        return null;
	}

	private function year_posts ($year)
	{
        /** @var DbLayer $s2_db */
        $s2_db = \Container::get(DbLayer::class);

		$start_time = mktime(0, 0, 0, 1, 1, $year);
		$end_time = mktime(0, 0, 0, 1, 1, $year + 1);

		$page['head_title'] = $page['title'] =  sprintf(Lang::get('Year', 's2_blog'), $year);

		$page['link_navigation']['up'] = S2_BLOG_PATH;
		if ($year > S2_START_YEAR)
		{
			$page['title'] = '<a href="'.S2_BLOG_PATH.($year - 1).'/">&larr;</a> '.$page['title'];
			$page['link_navigation']['prev'] = S2_BLOG_PATH.($year - 1).'/';
		}
		if ($year < date('Y'))
		{
			$page['title'] .= ' <a href="'.S2_BLOG_PATH.($year + 1).'/">&rarr;</a>';
			$page['link_navigation']['next'] = S2_BLOG_PATH.($year + 1).'/';
		}

		$query = array(
			'SELECT'	=> 'create_time, url',
			'FROM'		=> 's2_blog_posts',
			'WHERE'		=> 'create_time < '.$end_time.' AND create_time >= '.$start_time.' AND published = 1'
		);
		($hook = s2_hook('fn_s2_blog_year_posts_pre_get_days_qr')) ? eval($hook) : null;
		$result = $s2_db->buildAndQuery($query);

		$dayUrlsArray = array_fill(1, 12, []);
		while ($row = $s2_db->fetchRow($result)) {
            $dayUrlsArray[(int)date('m', $row[0])][(int)date('j', $row[0])][] = $row[1];
        }

		$content = [];
		for ($i = 1; $i <= 12; $i++) {
            $content[] = Lib::calendar($year, Lib::extend_number($i), '-1', '', $dayUrlsArray[$i]);
        }

		$page['text'] = $this->renderPartial('year', array(
			'content' => $content
		));
		return $page;
	}
}
