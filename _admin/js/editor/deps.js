/**
 * Editor dependency registry for S2.
 *
 * @copyright 2025-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

const editorDeps = {
    s2_codemirror: null,
    PopupMessages: null,
    PopupWindow: null,
    Preview: null,
    imageUtils: null,
    runOptipng: null,
    autoComplete: null,
    s2_lang: null
};

function setEditorDeps(nextDeps) {
    if (!nextDeps) {
        return;
    }
    Object.keys(nextDeps).forEach(function (key) {
        if (Object.prototype.hasOwnProperty.call(editorDeps, key)) {
            editorDeps[key] = nextDeps[key];
        }
    });
}

export {editorDeps, setEditorDeps};
