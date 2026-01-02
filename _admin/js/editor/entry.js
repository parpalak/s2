/**
 * Editor bootstrap and global exports for S2.
 *
 * @copyright 2025-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {initArticleEditForm} from './form.js';
import {initHtmlTextarea, initHtmlToolbar, SmartParagraphs} from './shortcuts.js';
import {initTagsAutocomplete} from './tags.js';
import {initImagePipeline, optimizeAndUploadFile, uploadBlobToPictureDir} from './images/pipeline.js';
import {s2_codemirror} from './codemirror.js';
import {ReturnImage, ClosePictureDialog} from './dialogs.js';
import {setEditorDeps} from './deps.js';

setEditorDeps({
    PopupMessages: window.PopupMessages,
    autoComplete: window.autoComplete,
    s2_lang: window.s2_lang
});

window.initHtmlTextarea = initHtmlTextarea;
window.initHtmlToolbar = initHtmlToolbar;
window.SmartParagraphs = SmartParagraphs;
window.optimizeAndUploadFile = optimizeAndUploadFile;
window.uploadBlobToPictureDir = uploadBlobToPictureDir;
window.initTagsAutocomplete = initTagsAutocomplete;
window.ReturnImage = ReturnImage;
window.ClosePictureDialog = ClosePictureDialog;

const config = window.S2_EDITOR_CONFIG || {};
if (config.sUrl) {
    window.sUrl = config.sUrl;
}

function bindDatalists(form, datalists) {
    if (!form || !Array.isArray(datalists)) {
        return;
    }
    datalists.forEach(function (item) {
        if (!item || !item.inputName || !item.listId) {
            return;
        }
        const input = form.elements[item.inputName];
        if (!input) {
            return;
        }
        input.setAttribute('list', item.listId);
        if (item.placeholder) {
            input.setAttribute('placeholder', item.placeholder);
        }
    });
}

function initHtmlEditors() {
    document.querySelectorAll('.html-textarea-with-preview-wrapper textarea').forEach(function (textarea) {
        initHtmlTextarea(textarea);
    });

    document.querySelectorAll('.html-toolbar').forEach(function (toolbar) {
        initHtmlToolbar(toolbar);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const formName = config.formName || 'article-form';
    const form = document.forms[formName] || document.getElementById(formName);
    if (form) {
        bindDatalists(form, config.datalists);
    }

    initHtmlEditors();
    initImagePipeline();

    if (form && config.statusData && config.entityName && config.textareaName) {
        initArticleEditForm(form, config.statusData, config.entityName, config.textareaName, config.templateId);
    }

    if (config.tagsInputId && Array.isArray(config.tagsList)) {
        initTagsAutocomplete(config.tagsInputId, config.tagsList);
    }
});
