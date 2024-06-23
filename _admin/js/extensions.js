/**
 * Extensions management in the admin panel.
 *
 * @copyright 2009-2024 Roman Parpalak
 * @license MIT
 * @package S2
 */

function loadingIndicator(state) {
    document.getElementById('loading').style.display = state ? 'block' : 'none';
    document.body.style.cursor = state ? 'progress' : 'inherit';
}

function RefreshHooks() {
    loadingIndicator(true)
    fetch(sUrl + 'action=refresh_hooks').finally(() => loadingIndicator(false));
    return false;
}

function changeExtension(sAction, sId, sMessage, iAdminAffected) {
    if (sAction === 'install_extension') {
        if (!confirm((sMessage !== '' ? s2_lang.install_message.replaceAll('%s', sMessage) : '') + s2_lang.install_extension.replaceAll('%s', sId))) {
            return false;
        }
    } else if (sAction === 'uninstall_extension') {
        if (!confirm(s2_lang.delete_extension.replaceAll('%s', sId))) {
            return false;
        }

        if (sMessage !== '' && !confirm(s2_lang.uninstall_message.replaceAll('%s', sMessage))) {
            return false;
        }
    }

    loadingIndicator(true)
    fetch(sUrl + 'action=' + sAction + '&id=' + sId).then(() => window.location.reload()).finally(() => loadingIndicator(false));

    return false;
}
