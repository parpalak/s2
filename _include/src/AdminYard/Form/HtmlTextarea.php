<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace S2\Cms\AdminYard\Form;

use S2\AdminYard\Form\Textarea;

class HtmlTextarea extends Textarea
{
    public function getHtml(?string $id = null): string
    {
        $escapedFileName = htmlspecialchars($this->fieldName, ENT_QUOTES, 'UTF-8');
        $escapedValue    = htmlspecialchars($this->value, ENT_QUOTES, 'UTF-8');
        $idAttr          = $id !== null ? ' id="' . $id . '"' : '';
        return <<<HTML
<textarea name="{$escapedFileName}"{$idAttr}>{$escapedValue}</textarea>
HTML;
    }

    public function getHtmlWithWrapper(callable $trans, string $id): string
    {
        return <<<HTML
<div class="toolbar" id="{$id}-toolbar">
    <button type="button" tabindex="-1" class="b" title="{$trans('Bold')}"></button>
    <button type="button" tabindex="-1" class="i" title="{$trans('Italic')}"></button>
    <button type="button" tabindex="-1" class="strike" title="{$trans('Strike')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="big" title="{$trans('BIG')}"></button>
    <button type="button" tabindex="-1" class="small" title="{$trans('SMALL')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="sup" title="{$trans('SUP')}"></button>
    <button type="button" tabindex="-1" class="sub" title="{$trans('SUB')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="nobr" title="{$trans('NOBR')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="a" title="{$trans('Link')}"></button>
    <button type="button" tabindex="-1" class="img" title="{$trans('Image')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="h2" title="{$trans('Header 2')}"></button>
    <button type="button" tabindex="-1" class="h3" title="{$trans('Header 3')}"></button>
    <button type="button" tabindex="-1" class="h4" title="{$trans('Header 4')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="left" title="{$trans('Left')}"></button>
    <button type="button" tabindex="-1" class="center" title="{$trans('Center')}"></button>
    <button type="button" tabindex="-1" class="right" title="{$trans('Right')}"></button>
    <button type="button" tabindex="-1" class="justify" title="{$trans('Justify')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="quote" title="{$trans('Quote')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="ul" title="{$trans('UL')}"></button>
    <button type="button" tabindex="-1" class="ol" title="{$trans('OL')}"></button>
    <button type="button" tabindex="-1" class="li" title="{$trans('LI')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="pre" title="{$trans('PRE')}"></button>
    <button type="button" tabindex="-1" class="code" title="{$trans('CODE')}"></button>
    <span class="separator"></span>
    <button type="button" tabindex="-1" class="parag" title="{$trans('Paragraphs info')}"></button>
    <button type="button" tabindex="-1" class="fullscreen" title="{$trans('Fullscreen')}"></button>
</div>
<div class="html-textarea-with-preview-wrapper">
    <div class="html-textarea-wrapper">
{$this->getHtml($id)}
    </div>
    <div class="html-preview-wrapper">
        <iframe src="" frameborder="0" class="preview-frame" id="$id-preview-frame" name="preview_frame"></iframe>
    </div>
</div>
<script>
    initHtmlTextarea(document.getElementById('$id'), '$id');
    initHtmlToolbar(document.getElementById('$id-toolbar'));
</script>
HTML;
    }
}
