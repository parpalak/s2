<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Translation;

interface TranslationProviderInterface
{
    public function getTranslations(string $language, string $locale): array;
}
