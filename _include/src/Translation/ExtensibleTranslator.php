<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class ExtensibleTranslator implements TranslatorInterface
{
    use TranslatorTrait {
        trans as protected parentTrans;
    }

    private array $namespaces = [];

    public function __construct(private array $translations, private readonly string $language, string $locale)
    {
        $this->setLocale($locale);
    }

    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $id = isset($this->translations[$id]) ? (string)$this->translations[$id] : $id;

        return $this->parentTrans($id, $parameters, $domain, $locale);
    }

    public function load(string $namespace, \Closure $param): void
    {
        if (isset($this->namespaces[$namespace])) {
            return;
        }

        $this->namespaces[$namespace] = true;
        $this->translations           = array_merge($param($this->language), $this->translations);
    }
}
