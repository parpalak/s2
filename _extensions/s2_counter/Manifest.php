<?php
/**
 * Counter
 *
 * Adds a simple hits/hosts and RSS subscribers counter.
 *
 * @copyright 2007-2024 Roman Parpalak
 * @license MIT
 * @package s2_counter
 */

declare(strict_types=1);

namespace s2_extensions\s2_counter;

use S2\Cms\Extensions\ManifestInterface;
use S2\Cms\Extensions\ManifestTrait;

class Manifest implements ManifestInterface
{
    use ManifestTrait;

    public function getTitle(): string
    {
        return 'Counter';
    }

    public function getAuthor(): string
    {
        return 'Roman Parpalak';
    }

    public function getDescription(): string
    {
        return 'A simple hits/hosts and RSS subscribers counter.';
    }

    public function getVersion(): string
    {
        return '2.0';
    }

    public function isAdminAffected(): bool
    {
        return true;
    }

    public function getInstallationNote(): ?string
    {
        return 'Do not forget to set write permissions (e. g. 777) to folder “_extensions/s2_counter/data/”.';
    }
}
