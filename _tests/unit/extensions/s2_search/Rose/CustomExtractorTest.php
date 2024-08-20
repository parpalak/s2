<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace unit\extensions\s2_search\Rose;

use Codeception\Test\Unit;
use s2_extensions\s2_latex\Extension;
use s2_extensions\s2_search\Event\TextNodeExtractEvent;
use s2_extensions\s2_search\Rose\CustomExtractor;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @group extract
 */
class CustomExtractorTest extends Unit
{
    private ?CustomExtractor $domExtractor = null;
    private ?EventDispatcher $eventDispatcher = null;

    /**
     * {@inheritdoc}
     */
    public function _before()
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addListener(TextNodeExtractEvent::class, [Extension::class, 'textNodeExtractListener']);

        $this->domExtractor = new CustomExtractor($this->eventDispatcher);
    }

    /**
     * @dataProvider htmlTextProvider
     */
    public function testCustomExtractor(string $htmlText, string $resultText, ?array $words = null, $images = null): void
    {
        $extractionResult = $this->domExtractor->extract($htmlText);

        $sentenceMap = $extractionResult->getContentWithMetadata()->getSentenceMap();
        self::assertEquals($resultText, $sentenceMap->toSentenceCollection()->getText());
        if ($words !== null) {
            self::assertEquals($words, $sentenceMap->toSentenceCollection()->getWordsArray());
        }
        if ($images !== null) {
            self::assertEquals($images, $extractionResult->getContentWithMetadata()->getImageCollection()->toJson());
        }
    }

    public function htmlTextProvider(): array
    {
        return [
            ['Some <nobr>self-test</nobr>.', 'Some self-test.'],
            ['Some <noindex>self-test</noindex>.', 'Some self-test.'],
            ['Some text.<BR>Another text.<hr />Any other text.', 'Some text. Another text. Any other text.'],
            ['<p>This <table><tr><td>is broken</td><td>HTML.</td></tr></table>I <b>want <i>to</b> test a</i> real-word <img><unknown-tag>example</p>', 'This is broken HTML. I \bwant \ito\I\B test a real-word example', ['this', 'is', 'broken', 'html', 'i', 'want', 'to', 'test', 'a', 'real-word', 'example']],
            [
                '<P><i>This</i> sentence&nbsp;contains entities like &#43;, &plus;, &planck;, &amp;, &lt;, &quot;, &#8212;, &laquo;, &#x2603;, &#x1D306;, &#xA9;, &copy;. &amp;plus; is not an entity.</p>',
                '\\iThis\\I sentence contains entities like +, +, ℏ, &, <, ", —, «, ☃, 𝌆, ©, ©. &plus; is not an entity.',
                ['this', 'sentence', 'contains', 'entities', 'like', 'ℏ', 'plus', 'is', 'not', 'an', 'entity'],
            ],
            [
                '
<p>Как-то мы должны были рассчитать сопротивление между точками <em>A</em> и <em>B</em>, если сопротивление каждого резистора 300 Ом:</p>

<p>$$\begin{circuitikz}
\draw
(0,0) node[above] {$A$} to[short, o-*] ++(0.7,0) coordinate (A) to[generic, *-*] ++(2,0)
coordinate (B) to[generic, *-*] ++(2,0)
coordinate (E) to[generic, *-*] ++(2,0)
coordinate (D) to[short, *-o] ++(0.7,0) node[above] {$B$};
\draw (A)-- ++(0,-0.7)-| (E) (B)-- ++(0,0.7)-| (D);
\end{circuitikz}$$</p>

<p>Внешнее <img src="1.jpg" width="10" height="10"> кольцо позволяет пренебречь.</p>

<p>$$
skipped
$$(1a)
</p>

<p>Ошибка <i>астатически</i> даёт более простую систему.</p>

<p>Еще 1 раз проверим, как gt работает защита против &lt;script&gt;alert();&lt;/script&gt; xss-уязвимостей.</p>',
                'Как-то мы должны были рассчитать сопротивление между точками \\iA\\I и \\iB\\I, если сопротивление каждого резистора 300 Ом: Внешнее кольцо позволяет пренебречь. Ошибка \\iастатически\\I даёт более простую систему. Еще 1 раз проверим, как gt работает защита против <script>alert();</script> xss-уязвимостей.',
                null,
                '[{"src":"upmath:\/\/\\\\begin{circuitikz}\n\\\\draw\n(0,0) node[above] {$A$} to[short, o-*] ++(0.7,0) coordinate (A) to[generic, *-*] ++(2,0)\ncoordinate (B) to[generic, *-*] ++(2,0)\ncoordinate (E) to[generic, *-*] ++(2,0)\ncoordinate (D) to[short, *-o] ++(0.7,0) node[above] {$B$};\n\\\\draw (A)-- ++(0,-0.7)-| (E) (B)-- ++(0,0.7)-| (D);\n\\\\end{circuitikz}","width":"","height":"","alt":""},{"src":"1.jpg","width":"10","height":"10","alt":""}]'
            ],
        ];
    }
}
