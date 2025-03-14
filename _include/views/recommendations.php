<?php
/**
 * @var callable $trans
 * @var callable $dateAndTime
 * @var array  $raw
 * @var array  $log
 * @var ?array $content
 */

use S2\Cms\Image\ImgDto;
use S2\Cms\Image\ThumbnailGenerator;

if ($content === null) {
    return;
}

function getImgMarkup(ImgDto $imgDto, int $columnNum): string
{
    $percent         = 100.0 * $imgDto->getRatio();
    $src             = $imgDto->getSrc();
    $class           = $imgDto->getClass();
    $fallbackHandler = '';

    if ($class === 'right') {
        $height = $percent * 0.35;

        return "<div class='recommendation-img-right-wrapper' style=\"width: 35%; padding-top: {$height}%\"><img loading='lazy' src=\"$src\" class='recommendation-img' alt='' {$fallbackHandler}></div>";
    }

    if ($class === 'right2') {
        $height = $percent * 0.18;

        return "<div class='recommendation-img-right-wrapper' style=\"width: 18%; padding-top: {$height}%\"><img loading='lazy' src=\"$src\" class='recommendation-img' alt='' {$fallbackHandler}></div>";
    }

    if ($class === 'thumb') {
        $h = 120.0 * $imgDto->getRatio();
        $w = 120;

        return "<div class='recommendation-img-thumb-wrapper' style='height: {$h}px; width: {$w}px;'><img loading='lazy' class='recommendation-img' src='$src' alt='' {$fallbackHandler}></div><br clear='left'>";
    }

    $class = '';
    if (strpos($src, 'youtube.com')) {
        if ($columnNum === 1) {
            $src = str_replace('hq720', 'sddefault', $src);
            $fallbackHandler = 'onload="if (!this.flipped && this.naturalWidth < 640) { this.flipped = true; this.src = this.src.replace(\'sddefault\', \'hqdefault\') }"';
        } else {
            $fallbackHandler = 'onload="if (!this.flipped && this.naturalWidth < 1280) { this.flipped = true; this.src = this.src.replace(\'hq720\', \'hqdefault\') }"';
        }
        $class = 'recommendation-video-wrapper';
    }

    return "<div class='recommendation-img-wrapper {$class}' style='padding-top: $percent%'><img loading='lazy' class='recommendation-img' src='$src' alt='' {$fallbackHandler}></div>";
}

function getColumnsNumFromGridArea(string $area): int
{
    $parts = explode('/', $area);
    if (count($parts) === 4 && ctype_digit($parts[3]) && ctype_digit($parts[1])) {
        return (int)$parts[3] - (int)$parts[1];
    }

    return 1;
}

/** @var ThumbnailGenerator $th */
$th = Container::get(ThumbnailGenerator::class);

$maxLine = 0;
foreach ($content as $recommendation) {
    $pos = $recommendation['position'];
    $posPieces = explode('/', $pos);
    if (count($posPieces) === 2) {
        $maxLine = max($maxLine, $posPieces[1] + 1);
    } elseif (count($posPieces) === 4) {
        $maxLine = max($maxLine, $posPieces[3]);
    }
}

?>
<h2 class="recommendation-title" id="recommendations"><?php echo $trans('Read next'); ?></h2>
<!-- <?php echo end($log); ?> -->
<div class="recommendations" style="<?php if ($maxLine > 5) {echo 'grid-template-columns: repeat(' . ($maxLine - 1) . ', 1fr);'; } ?>">
    <?php foreach ($content as $recommendation) : ?>
        <div class="recommendation" style="grid-area: <?php echo $recommendation['position'] ?: 'auto'; ?>">
            <a class="recommendation-link" href="<?= s2_link($recommendation['url']) ?>">
                <?php
                if ($recommendation['image'] !== null) {
                    $columnNum = getColumnsNumFromGridArea($recommendation['position']);

                    $imgDto = $th->getReducedImg($recommendation['image']);

                    echo $img = getImgMarkup($imgDto, $columnNum);
                }
                ?>
                <span class="recommendation-header recommendation-header-<?= $recommendation['headingSize'] ?>"><?php echo s2_htmlencode($recommendation['title']); ?></span>
            </a>
            <div class="recommendation-snippet"><?= $recommendation['snippet'] ?></div>
            <div class="recommendation-date"
                 title="<?php echo $recommendation['date'] ? $dateAndTime($recommendation['date']->getTimestamp()) : ''; ?>">
                <?= $recommendation['date'] ? $recommendation['date']->format('Y') : '' ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php

return;

?>

<div style="display: grid; gap: 1em; grid-template-columns: repeat(5, 1fr);">
    <?php foreach ($raw as $recommendation) : ?>
        <div class="s2_blog_recommendation <?= $recommendation['relevance'] < 0.0 ? 'gray' : '' ?>">
            <?php
            /** @var \S2\Rose\Entity\TocEntryWithMetadata $tocWithMetadata */
            $tocWithMetadata = $recommendation['tocWithMetadata'];

            $tocEntry = $tocWithMetadata->getTocEntry();
            $link     = s2_link($tocEntry->getUrl());

            foreach ($tocWithMetadata->getImgCollection() as $image) {
                $img = $th->getImgHtml($image, 120, 60);
                echo '<a class="preview-link" href="', $link, '">', $img, '</a>';
            }
            ?>

            <h3 class="header s2_blog_recommendation">
                <a href="<?= $link ?>"><?php echo $tocEntry->getTitle(); ?></a>
            </h3>
            <?= $recommendation['snippet'] ?> <?= $recommendation['snippet2'] ?><br>
            <span style="color: #999"><?= str_replace(',', ' ', $recommendation['names']) ?></span><br>
            <?= $recommendation['relevance'] ?>
            <?= $recommendation['word_count'] ?>
            <?= $tocEntry->getDate() ? $tocEntry->getDate()->format('Y') : ''; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php

// echo '<pre>', implode('<br>', $log), '</pre>';
