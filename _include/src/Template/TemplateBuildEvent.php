<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

class TemplateBuildEvent
{
    public const EVENT_START = 'template_build.start';
    public const EVENT_END   = 'template_build.end';

    public function __construct(
        public readonly string $styleName,
        public readonly string $templateId,
        public ?string &$path
    ) {
    }
}
