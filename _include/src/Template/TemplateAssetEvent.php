<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

declare(strict_types=1);

namespace S2\Cms\Template;

use S2\Cms\Asset\AssetPack;

readonly class TemplateAssetEvent
{
    public function __construct(public AssetPack $assetPack)
    {
    }
}
