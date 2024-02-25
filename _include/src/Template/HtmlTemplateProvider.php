<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

class HtmlTemplateProvider
{
    public function __construct(private readonly Viewer $viewer)
    {
    }

    public function getTemplate(string $templateId): HtmlTemplate
    {
        return new HtmlTemplate(s2_get_template($templateId), $this->viewer);
    }
}
