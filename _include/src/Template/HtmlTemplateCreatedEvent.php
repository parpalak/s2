<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use Symfony\Contracts\EventDispatcher\Event;

class HtmlTemplateCreatedEvent extends Event
{
    public function __construct(public readonly HtmlTemplate $htmlTemplate)
    {
    }
}
