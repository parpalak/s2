<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Model\Article;

use S2\Cms\Template\HtmlTemplate;

readonly class ArticleRenderedEvent
{
    public function __construct(public HtmlTemplate $template, public int $articleId)
    {
    }
}
