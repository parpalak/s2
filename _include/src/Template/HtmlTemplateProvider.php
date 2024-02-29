<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class HtmlTemplateProvider
{
    public function __construct(
        private readonly Viewer                   $viewer,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getTemplate(string $templateId): HtmlTemplate
    {
        $htmlTemplate = new HtmlTemplate(s2_get_template($templateId), $this->viewer);

        $this->dispatcher->dispatch(new HtmlTemplateCreatedEvent($htmlTemplate));

        return $htmlTemplate;
    }
}
