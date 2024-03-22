<?php
/**
 * LaTeX
 *
 * Integrates site with i.upmath.me service
 *
 * @copyright 2011-2024 Roman Parpalak
 * @license MIT
 * @package s2_latex
 */

declare(strict_types=1);

namespace s2_extensions\s2_latex;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'LaTeX';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'Allows to write LaTeX formulas.';
    }

    public function getVersion(): string
    {
        return '2.0dev';
    }
}
