<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use Symfony\Contracts\EventDispatcher\Event;

class TemplateEvent extends Event
{
    public const EVENT_CREATED     = 'template.created';
    public const EVENT_PRE_REPLACE = 'template.pre_replace';

    public function __construct(public readonly HtmlTemplate $htmlTemplate)
    {
    }
}
