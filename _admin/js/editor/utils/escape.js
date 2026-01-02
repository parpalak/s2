/**
 * HTML escaping helpers for editor modules in S2.
 *
 * @copyright 2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/`/g, '&#96;');
}

function sanitizeUrlForAttribute(url) {
    return escapeHtml(encodeURI(String(url)));
}

export {escapeHtml, sanitizeUrlForAttribute};
