<?php declare(strict_types=1);

use S2\Cms\Asset\AssetPack;

return (new AssetPack(__DIR__))
    ->addCss('site.css', [AssetPack::OPTION_MERGE])
// Here is an example how to add Google Analytics code:
//    ->addHeadJs('https://www.googletagmanager.com/gtag/js?id=...', [AssetPack::OPTION_ASYNC])
//    ->addHeadInlineJs("<script>  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', '...');</script>")
    ->addJs('script.js', [AssetPack::OPTION_MERGE, AssetPack::OPTION_DEFER])
    ->setFavIcon('favicon.ico')
;
