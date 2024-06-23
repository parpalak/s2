<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_blog
 */

declare(strict_types=1);

namespace s2_extensions\s2_blog\Admin;

use S2\Cms\Admin\DynamicConfigFormExtenderInterface;

class DynamicConfigFormExtender implements DynamicConfigFormExtenderInterface
{
    public function getExtraParamTypes(): array
    {
        return [
            'Blog config'   => 'title',
            'S2_BLOG_TITLE' => 'string',
            'S2_BLOG_URL'   => 'string',
        ];
    }
}
