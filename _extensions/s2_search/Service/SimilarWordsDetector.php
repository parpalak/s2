<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Service;

use S2\Rose\Stemmer\StemmerInterface;

readonly class SimilarWordsDetector
{
    public function __construct(private StemmerInterface $stemmer)
    {
    }

    public function wordIsSimilarToOtherWords(string $word, array $otherWords): bool
    {
        $checkingWords = explode(' ', $word);

        foreach ($checkingWords as $wordToCheck) {
            if (mb_strlen($wordToCheck) < 3) {
                continue;
            }
            $stemToCheck = $this->stemmer->stemWord($wordToCheck);
            foreach ($otherWords as $otherWord) {
                if ($otherWord === $stemToCheck || (str_starts_with($stemToCheck, $otherWord) && mb_strlen($otherWord) >= 5)) {
                    return true;
                }
            }
        }

        return false;
    }
}
