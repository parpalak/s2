<?php
/**
 * Custom extraction logic for indexing.
 *
 * @copyright 2023-2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Rose;

use Psr\Log\LoggerInterface;
use S2\Rose\Extractor\ExtractionErrors;
use S2\Rose\Extractor\HtmlDom\DomExtractor;
use S2\Rose\Extractor\HtmlDom\DomState;
use s2_extensions\s2_search\Event\TextNodeExtractEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomExtractor extends DomExtractor
{
    public const YOUTUBE_PROTOCOL = 'youtube://';

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        ?LoggerInterface                 $logger = null,
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function processTextNode(\DOMText $domNode, DomState $domState, ExtractionErrors $extractionErrors, int $level): void
    {
        $textContent = $domNode->textContent;

        $this->checkContentForWarnings($level, $textContent, $extractionErrors, $domNode);

        $event = new TextNodeExtractEvent($domNode->parentNode, $domState, $textContent, $domNode->getNodePath());
        $this->dispatcher->dispatch($event);

        if (!$event->isPropagationStopped()) {
            $domState->attachContent($domNode->getNodePath(), $textContent);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function processDomElement(\DOMNode $domNode, DomState $domState): string
    {
        switch ($domNode->nodeName) {
            case 'iframe':
                if (($youtubeId = self::getYoutubeId($domNode->getAttribute('src'))) !== null) {
                    $domState->attachImg(self::YOUTUBE_PROTOCOL . $youtubeId, $domNode->getAttribute('width') ?? '', $domNode->getAttribute('height') ?? '', '');
                }
        }

        return parent::processDomElement($domNode, $domState);
    }

    /**
     * https://stackoverflow.com/questions/5830387/how-do-i-find-all-youtube-video-ids-in-a-string-using-a-regex
     */
    protected static function getYoutubeId(string $src): ?string
    {
        if (preg_match('~^(?#!js YouTubeId Rev:20160125_1800)
            # Match non-linked youtube URL in the wild. (Rev:20130823)
            (?:https?:)?//     # Optional scheme. Either http or https.
            (?:[0-9A-Z-]+\.)?  # Optional subdomain.
            (?:                # Group host alternatives.
              youtu\.be/       # Either youtu.be,
            | youtube          # or youtube.com or
              (?:-nocookie)?   # youtube-nocookie.com
              \.com            # followed by
              \S*?             # Allow anything up to VIDEO_ID,
              [^\w\s-]         # but char before ID is non-ID char.
            )                  # End host alternatives.
            ([\w-]{11})        # $1: VIDEO_ID is exactly 11 chars.
            (?=[^\w-]|$)       # Assert next char is non-ID or EOS.
            (?!                # Assert URL is not pre-linked.
              [?=&+%\w.-]*     # Allow URL (query) remainder.
              (?:              # Group pre-linked alternatives.
                [\'"][^<>]*>   # Either inside a start tag,
              | </a>           # or inside <a> element text contents.
              )                # End recognized pre-linked alts.
            )                  # End negative lookahead assertion.
            [?=&+%\w.-]*       # Consume any URL (query) remainder.
        ~ix', $src, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
