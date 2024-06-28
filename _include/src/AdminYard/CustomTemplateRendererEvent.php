<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard;

class CustomTemplateRendererEvent
{
    public array $extraStyles = [];
    public array $extraScripts = [];

    public function __construct(public readonly string $basePath)
    {
    }
}
