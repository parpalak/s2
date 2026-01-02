/**
 * Text formatting helpers for S2.
 *
 * @copyright 2007-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

export function smartParagraphs(sText) {
    sText = sText.replace(/(\r\n|\r|\n)/g, '\n');
    const asParag = sText.split(/\n{2,}/);

    for (let i = asParag.length; i--;) {
        if (asParag[i].replace(/^\s+|\s+$/g, '') === '') {
            continue;
        }

        asParag[i] = asParag[i].replace(/\s+$/gm, '');

        if (/<\/?(?:pre|script|style|ol|ul|li|cut)[^>]*>/.test(asParag[i])) {
            continue;
        }

        asParag[i] = asParag[i].replace(/<br \/>$/gm, '').
            replace(/$/gm, '-').
            replace(/(<\/(?:blockquote|p|h[2-4])>)?-$/gm, function ($0, $1) {
                return $1 ? $1 : '<br />';
            }).
            replace(/(?:<br \/>)?$/g, '');

        if (!/<\/?(?:blockquote|h[2-4])[^>]*>/.test(asParag[i])) {
            if (!/<\/p>\s*$/.test(asParag[i])) {
                asParag[i] = asParag[i].replace(/\s*$/g, '</p>');
            }
            if (!/^\s*<p[^>]*>/.test(asParag[i])) {
                asParag[i] = asParag[i].replace(/^\s*/g, '<p>');
            }
        }
    }

    return asParag.join("\n\n");
}
