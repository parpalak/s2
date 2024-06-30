<?php

declare(strict_types=1);

/** @var array $links */
/** @var callable $trans */

use S2\Cms\AdminYard\Signal;

$echoSignal = static function (Signal $signal) use ($trans): void {
    ?>
    <li class="main-menu-subitem">
        <a class="main-menu-sublink"
           title="<?= htmlspecialchars($trans($signal->title), ENT_QUOTES, 'UTF-8') ?>"
           href="<?= htmlspecialchars($signal->url, ENT_QUOTES, 'UTF-8') ?>"
        >
            <?= htmlspecialchars($trans($signal->text), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </li>
    <?php
}

?>
<ul class="main-menu-list">
    <?php foreach ($links as $link): ?>
        <li class="main-menu-item <?= $link['active'] ? 'active' : '' ?>">
            <a class="main-menu-link" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($trans($link['name']), ENT_QUOTES, 'UTF-8') ?>
            </a>

            <?php if (!empty($link['signals'])): ?>
                <ul class="main-menu-sublist">
                    <?php
                    /** @var Signal[] $signals */
                    $signals = $link['signals'];
                    $allEmpty = true;
                    $titles = [];
                    foreach ($signals as $signal) {
                        if (!$signal->isEmpty()) {
                            $allEmpty = false;
                            break;
                        }
                        $titles[] = $signal->title;
                    }
                    if ($allEmpty) {
                        $echoSignal(new Signal((string)count($signals), implode("\n", $titles), $link['url']));
                    } else {
                        foreach ($signals as $signal) {
                            $echoSignal($signal);
                        }
                    }
                    ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
