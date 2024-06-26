// Fetch interceptor
const {fetch: originalFetch} = window;
window.fetch = async (...args) => {
    let [resource, config] = args;

    if (!config) {
        config = {};
    }
    if (!config.headers) {
        config.headers = {};
    }
    config.headers['X-Requested-With'] = 'XMLHttpRequest';

    const response = await originalFetch(resource, config);

    if (response.ok || response.status === 422) {
        return response;
    }

    try {
        if (response.status === 401) {
            const data = await response.json();

            if (data.message) {
                PopupMessages.show(data.message, null, null, 'login');
            } else {
                DisplayError(JSON.stringify(data));
            }
        } else {
            const txt = await response.text();
            DisplayError(txt);
        }
    } catch (error) {
        PopupMessages.show(error);
    }
    console.warn('Form submission failed');

    return Promise.reject(response);
};

window.PopupMessages = {

    show: function (sMessage, aActions, iTime, sId) {
        let eMessage;
        let ePopup = document.getElementById('popup_message');
        let eList, eCross;

        if (!ePopup) {
            eCross = document.createElement('a');
            eCross.setAttribute('class', 'cross');
            eCross.setAttribute('href', '#');
            eCross.setAttribute('tabindex', '0');
            eCross.addEventListener('click', function (e) {
                ePopup.remove();
                e.preventDefault();
            });

            eList = document.createElement('div');
            eList.setAttribute('class', 'message-list');
            eList.appendChild(eCross);

            ePopup = document.createElement('div');
            ePopup.setAttribute('id', 'popup_message');
            ePopup.appendChild(eList);
            document.body.appendChild(ePopup);
        } else {
            eList = ePopup.children[0];
            eCross = eList.children[0];
        }

        eCross.focus();

        if (sId) {
            eMessage = eList.querySelector('div[data-id="' + sId + '"]');
            if (eMessage) {
                eMessage.style.opacity = 0;
                setTimeout(function () {
                    eMessage.style.opacity = 1;
                }, 100);
                setTimeout(function () {
                    eMessage.style.opacity = 0;
                }, 200);
                setTimeout(function () {
                    eMessage.style.opacity = 1;
                }, 300);
                return;
            }
        }

        eMessage = document.createElement('div');
        eMessage.setAttribute('class', 'message');
        eMessage.setAttribute('data-id', sId || '');
        eList.appendChild(eMessage);

        if (iTime) {
            setTimeout(function () {
                eMessage.remove();
                if (!eList.querySelector('.message')) {
                    ePopup.remove();
                }
            }, iTime * 1000);
        }

        eMessage.innerHTML = sMessage;

        if (aActions) {
            for (let i = 0; i < aActions.length; i++) {
                const eA = document.createElement('a');
                eA.setAttribute('class', 'action');
                eA.setAttribute('href', '#');
                eA.setAttribute('tabindex', '0');
                eA.textContent = aActions[i].name;
                (function (action, once) {
                    eA.addEventListener('click', function () {
                        action();
                        if (once) {
                            eMessage.remove();
                            if (!eList.querySelector('.message')) {
                                ePopup.remove();
                            }
                        }
                        return false;
                    });
                }(aActions[i].action, aActions[i].once));
                eMessage.appendChild(document.createTextNode('\u00a0'));
                eMessage.appendChild(eA);
            }
        }
    },

    showUnique: function (sMessage, sId) {
        this.show(sMessage, null, null, sId);
    },

    hide: function (sId) {
        if (!sId) {
            return;
        }

        const popup = document.getElementById('popup_message');
        if (!popup) {
            return;
        }

        const list = popup.children[0];

        const eMessage = list.querySelector('div[data-id="' + sId + '"]');
        if (eMessage) {
            eMessage.remove();
            if (!list.querySelector('.message')) {
                popup.remove();
            }
        }
    }
};

function DisplayError (sError)
{
    function isJson(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }

    const dialog = document.getElementById('error-dialog');
    const closeButton = document.getElementById('error-dialog-close');
    const iframe = document.getElementById('error-iframe');

    if (isJson(sError)) {
        sError = JSON.stringify(JSON.parse(sError), null, 4);
        sError = '<pre>' + sError.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;') + '</pre>';
    }

    const blob = new Blob([sError], { type: 'text/html' });
    iframe.src = URL.createObjectURL(blob);
    dialog.showModal();

    closeButton.addEventListener('click', function() {
        dialog.close();
        URL.revokeObjectURL(iframe.src);
    });

    closeButton.focus();
}
