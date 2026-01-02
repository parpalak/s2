/**
 * Article editor form logic for S2.
 *
 * @copyright 2007-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

import {editorDeps} from './deps.js';
import {hex_md5} from './hash.js';
import {Preview, initPreviewSync} from './preview.js';
import {PopupWindow} from './dialogs.js';
import {s2_codemirror} from './codemirror.js';

export function initArticleEditForm(eForm, statusData, sEntityName, sTextareaName, sTemplateId) {
    const sLowerEntityName = sEntityName.toLowerCase();

    function decorateForm(statusData) {
        const urlWrapper = eForm.querySelector('.field-url');
        const urlLabel = eForm.querySelector('label[for="id-url"]');
        urlWrapper.setAttribute('data-url-status', statusData['urlStatus']);
        urlWrapper.title = statusData['urlTitle'];
        urlLabel.title = statusData['urlTitle'];
        if (statusData['urlStatus'] === 'mainpage') {
            urlWrapper.querySelector('input').setAttribute('disabled', 'disabled');
        }

        const isPublished = eForm.elements['published'].checked;
        eForm.querySelector('.field-published').setAttribute('data-published-status', isPublished ? '1' : '0');

        const ePreviewLink = eForm.querySelector('#preview_link');
        ePreviewLink.href = statusData['url'];
        ePreviewLink.style.display = isPublished ? 'inline' : 'none';
    }

    decorateForm(statusData);
    initPreviewSync(eForm, sTextareaName);

    async function saveForm(event) {
        event.preventDefault();

        document.dispatchEvent(new Event('save_article_start.s2'));

        function successHandler(statusData) {
            editorDeps.PopupMessages.hide(sLowerEntityName + '-save');
            document.dispatchEvent(new Event('save_article_end.s2'));

            eForm.elements['revision'].value = statusData['revision'];
            decorateForm(statusData);
        }

        function errorHandler(data) {
            Array.from(data.errors).forEach(function (error) {
                // TODO array_merge
                editorDeps.PopupMessages.show(error, null, null, sLowerEntityName + '-save');
            });
            console.warn('Form submission failed');
        }

        function getTempCsrfToken() {
            return document.cookie
                .split('; ')
                .find((row) => row.startsWith('adminyard_temp_csrf_token='))
                ?.split('=')[1] || '';
        }

        try {
            const formData = new FormData(eForm);
            const headers = {'X-Requested-With': 'XMLHttpRequest'};
            const tempCsrfToken = getTempCsrfToken();
            if (tempCsrfToken !== '') {
                headers['X-AdminYard-CSRF-Token'] = tempCsrfToken;
            }
            const response = await fetch(eForm.action, {method: 'POST', headers: headers, body: formData});

            if (response.ok) {
                successHandler(await response.json());
            } else if (response.status === 422) {
                const data = await response.json();
                if (data.invalid_csrf_token) {
                    const response2 = await fetch(eForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-AdminYard-CSRF-Token': getTempCsrfToken()
                        },
                        body: formData
                    });

                    if (response2.ok) {
                        successHandler(await response2.json());
                    } else if (response2.status === 422) {
                        errorHandler(await response2.json());
                    }
                } else {
                    errorHandler(data);
                }
            }
        } catch (error) {
            console.warn('An error occurred:', error);
        }
    }

    eForm.addEventListener('submit', saveForm);
    document.addEventListener('save_form.s2', saveForm);

    document.addEventListener('return_image.s2', function (e) {
        let w = e.detail.width;
        let h = e.detail.height;
        let s = e.detail.file_path;
        s = encodeURI(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#039;').replace(/"/g, '&quot;');

        const sOpenTag = '<img src="' + s + '" width="' + w + '" height="' + h + '" ' + 'loading="lazy" alt="',
            sCloseTag = '" />';
        document.dispatchEvent(new CustomEvent('insert_tag.s2', {detail: {sStart: sOpenTag, sEnd: sCloseTag}}));

        const dialog = document.getElementById('picture_dialog');
        if (dialog) {
            dialog.close();
        }
    });

    var Changes = (function () {
        let savedText = '';
        let previousText = '';
        let currentFormHash = '';

        function checkChanges() {
            document.dispatchEvent(new Event('check_changes_start.s2'));

            const currentText = eForm.elements[sTextareaName].value;

            if (previousText !== currentText) {
                const absoluteUrl = new URL(eForm.action);
                const id = absoluteUrl.searchParams.get('id');
                Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value, id, sTemplateId || eForm.elements['template'].value);
                previousText = currentText;

                if (savedText !== currentText) {
                    localStorage.setItem('s2_curr_text', currentText);
                } else {
                    localStorage.removeItem('s2_curr_text');
                }
            }
        }

        function wireLivePreview() {
            const cm = s2_codemirror.get_current && s2_codemirror.get_current();
            if (!cm || !cm.on) {
                return;
            }

            const updatePreview = debounceWithMaxWait(function () {
                s2_codemirror.flip();
                checkChanges();
            }, 300, 3000);

            cm.on('change', updatePreview);
        }

        const recoveredText = localStorage.getItem('s2_curr_text');
        setInterval(checkChanges, 5000);
        wireLivePreview();

        if (recoveredText) {
            editorDeps.PopupMessages.show(editorDeps.s2_lang.recovered_text_alert, [{
                name: editorDeps.s2_lang.recovered_open,
                action: function () {
                    PopupWindow(editorDeps.s2_lang.recovered_text_alert, editorDeps.s2_lang.recovered_text, editorDeps.s2_lang.recovered_text_info, recoveredText);
                }
            }]);
        }

        function getFormHash() {
            const formData = new FormData(eForm);
            const visibleFormData = new FormData();

            for (const [key, value] of formData.entries()) {
                const inputElement = eForm.elements[key];
                if (inputElement.type !== 'hidden') {
                    visibleFormData.append(key, value);
                }
            }

            const serializedData = Array.from(visibleFormData).map(function (pair) {
                return pair[0] + '=' + pair[1];
            }).join('&');

            return hex_md5(serializedData);
        }

        function handleChanges() {
            currentFormHash = getFormHash();
            localStorage.removeItem('s2_curr_text');
            savedText = eForm.elements[sTextareaName].value;
        }

        const absoluteUrl = new URL(eForm.action);
        const id = absoluteUrl.searchParams.get('id');
        Preview(eForm.elements['title'].value, eForm.elements[sTextareaName].value, id, sTemplateId || eForm.elements['template'].value);
        handleChanges();
        document.addEventListener('save_article_end.s2', handleChanges);

        return {
            present: function () {
                document.dispatchEvent(new Event('changes_present.s2'));

                return currentFormHash !== getFormHash();
            }
        };
    })();

    window.onbeforeunload = function () {
        if (Changes.present()) {
            return editorDeps.s2_lang.unsaved_exit;
        }
    };
}

function debounceWithMaxWait(fn, wait, maxWait) {
    let timerId = null;
    let lastInvoke = 0;

    return function () {
        const now = Date.now();
        const elapsed = now - lastInvoke;

        if (maxWait && elapsed >= maxWait) {
            lastInvoke = now;
            if (timerId) {
                clearTimeout(timerId);
                timerId = null;
            }
            fn();
            return;
        }

        if (timerId) {
            clearTimeout(timerId);
        }

        timerId = setTimeout(function () {
            lastInvoke = Date.now();
            timerId = null;
            fn();
        }, wait);
    };
}
