<?php
/**
 * @copyright 2024-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\Translation;

use S2\Cms\Framework\StatefulServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

class ExtensibleTranslator implements TranslatorInterface, StatefulServiceInterface
{
    use TranslatorTrait {
        TranslatorTrait::trans as protected parentTrans;
    }

    /**
     * @var array<string, \Closure>
     */
    private array $loaders = [];
    private array $translations = [];
    private ?array $loadingQueue = null;

    public function __construct(private readonly string $language)
    {
    }

    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($this->loadingQueue !== null) {
            foreach ($this->loadingQueue as $namespace => $required) {
                if (isset($this->loaders[$namespace])) {
                    $this->translations = array_merge($this->loaders[$namespace]($this->language, $this), $this->translations);
                }
            }
            $this->loadingQueue = null;
        }

        $id = isset($this->translations[$id]) ? (string)$this->translations[$id] : $id;

        return $this->parentTrans($id, $parameters, $domain, $locale);
    }

    public function attachLoader(string $namespace, \Closure $closure): void
    {
        if (isset($this->loaders[$namespace])) {
            return;
        }

        $this->loaders[$namespace] = $closure;
        $this->markAsRequired($namespace);
    }

    private function markAsRequired(string $namespace): void
    {
        if ($this->loadingQueue === null) {
            $this->loadingQueue = [$namespace => true];
        } else {
            $this->loadingQueue[$namespace] = true;
        }
    }

    public function clearState(): void
    {
        $this->translations = [];
        foreach ($this->loaders as $namespace => $loader) {
            $this->markAsRequired($namespace);
        }
    }
}
