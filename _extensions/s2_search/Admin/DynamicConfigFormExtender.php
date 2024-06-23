<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Admin;

use S2\Cms\Admin\DynamicConfigFormExtenderInterface;

class DynamicConfigFormExtender implements DynamicConfigFormExtenderInterface
{
    public function getExtraParamTypes(): array
    {
        return [
            'Search config'   => 'title',
            'S2_SEARCH_QUICK' => 'boolean',
        ];
    }
}
