<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Event;

use S2\Rose\Extractor\HtmlDom\DomState;
use Symfony\Contracts\EventDispatcher\Event;

class TextNodeExtractEvent extends Event
{
    public function __construct(
        readonly public \DOMNode $parentNode,
        readonly public DomState $domState,
        readonly public string   $textContent,
        readonly public string   $path
    ) {
    }
}
