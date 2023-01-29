<?php declare(strict_types=1);
/**
 * @copyright (C) 2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package S2
 */

namespace S2\Cms\Layout;

class MatchingContext
{
    private bool $hasMatch;
    private ?array $image = null;
    private string $snippet = '';

    public function __construct(bool $hasMatch)
    {
        $this->hasMatch = $hasMatch;
    }

    public function hasMatch(): bool
    {
        return $this->hasMatch;
    }

    public function getImage(): ?array
    {
        return $this->image;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function addImage(array $image, string $class = ''): self
    {
        $this->image          = $image;
        $this->image['class'] = $class;

        return $this;
    }

    public function addSnippet(string $snippet): self
    {
        $this->snippet = $snippet;

        return $this;
    }
}
