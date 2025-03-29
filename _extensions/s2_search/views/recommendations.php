<?php
/**
 * @var callable $trans
 * @var callable $makeLink
 * @var callable $dateAndTime
 * @var array  $raw
 * @var array  $log
 * @var ?array $content
 */

use s2_extensions\s2_search\Layout\ImgDto;
use s2_extensions\s2_search\Rose\CustomExtractor;

if ($content === null) {
    return;
}

$getImgMarkup = static function (ImgDto $imgDto, int $columnNum): string
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
};

$getColumnsNumFromGridArea = static function (string $area): int
{
    $parts = explode('/', $area);
    if (count($parts) === 4 && ctype_digit($parts[3]) && ctype_digit($parts[1])) {
        return (int)$parts[3] - (int)$parts[1];
    }

    return 1;
};

$getReducedImg = function (ImgDto $img): ImgDto
{
    $src = $img->getSrc();
    if (str_starts_with($src, CustomExtractor::YOUTUBE_PROTOCOL)) {
        return (new ImgDto(
            'https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg',
            640,
            360,
            $img->getClass()
        ))/*->addSrc('https://img.youtube.com/vi/' . substr($src, \strlen(CustomExtractor::YOUTUBE_PROTOCOL)) . '/hq720.jpg')*/ ;
    }

    return $img;
};

$maxLine = 0;
foreach ($content as $recommendation) {
    $pos = $recommendation['position'];
    $posPieces = explode('/', $pos);
    if (count($posPieces) === 2) {
        $maxLine = max($maxLine, (int)$posPieces[1] + 1);
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
            <a class="recommendation-link" href="<?= $makeLink($recommendation['url']) ?>">
                <?php
                if ($recommendation['image'] !== null) {
                    $columnNum = $getColumnsNumFromGridArea($recommendation['position']);

                    $imgDto = $getReducedImg($recommendation['image']);

                    echo $getImgMarkup($imgDto, $columnNum);
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
