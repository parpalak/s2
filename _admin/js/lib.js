/**
 * Helper functions
 *
 * @copyright 2007-2026 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

function loadingIndicator(bState) {
    const eDiv = document.getElementById('loading');
    if (!eDiv) {
        return;
    }
    eDiv.style.display = bState ? 'block' : 'none';
    document.body.style.cursor = bState ? 'progress' : 'inherit';
}

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
    if (!resource.includes('action=delete')) {
        // By default, AdminYard deletes records via fetch but does it only to display a confirmation dialog.
        // After that, it refreshes the page and displays the flash message.
        // Header 'X-Requested-With' switches from flash messages to JSON responses. So we do not add
        // 'X-Requested-With' in a universal fetch interceptor for action=delete.
        config.headers['X-Requested-With'] = 'XMLHttpRequest';
    }

    loadingIndicator(true);
    try {
        const response = await originalFetch(resource, config);

        if (response.ok || response.status === 422 || response.status === 409 || response.status === 503) {
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
            } else if (response.status === 403) {
                const data = await response.json();

                if (data.message) {
                    PopupMessages.show(data.message, null, null);
                } else if (data.errors) {
                    Array.from(data.errors).forEach(function (error) {
                        // TODO array_merge
                        PopupMessages.show(error);
                    });
                }
            } else {
                const txt = await response.text();
                try {
                    const data = JSON.parse(txt);

                    if (data.message) {
                        PopupMessages.show(data.message, null, null);
                    } else if (data.errors) {
                        Array.from(data.errors).forEach(function (error) {
                            // TODO array_merge
                            PopupMessages.show(error);
                        });
                    } else {
                        DisplayError(txt);
                    }
                } catch (e) {
                    DisplayError(txt);
                }
            }
        } catch (error) {
            PopupMessages.show(error);
        }
    } finally {
        loadingIndicator(false);
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

function DisplayError(sError) {
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

    const blob = new Blob([sError], {type: 'text/html'});
    iframe.src = URL.createObjectURL(blob);
    dialog.showModal();

    closeButton.addEventListener('click', function () {
        dialog.close();
        URL.revokeObjectURL(iframe.src);
    });

    closeButton.focus();
}

// Ajax login form processing

let shakeTimerId = null;

async function SendLoginData(eForm, fOk, fFail) {
    try {
        let response = await originalFetch('?action=login', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                login: eForm.login.value,
                pass: eForm.pass.value,
            })
        });

        let result = await response.json();
        if (result.success) {
            fOk();
        } else {
            fFail(result.message);
        }
    } catch (error) {
        fFail('An error occurred: ' + error.message);
    }
}

function SendLoginForm() {
    const form = document.forms['loginform'];

    function shift(time) {
        form.style.transform = `translateX(${-150.0 * Math.exp(-time * 0.006) * Math.sin(0.026179938 * time)}px)`;
    }

    let animationStartedAt = null;

    function animateShake(timestamp) {
        if (!animationStartedAt) {
            animationStartedAt = timestamp;
        }
        let duration = timestamp - animationStartedAt;

        if (duration > 835) {
            shift(0);
            cancelAnimationFrame(shakeTimerId);
            shakeTimerId = null;
        } else {
            shift(duration);
            shakeTimerId = requestAnimationFrame(animateShake);
        }
    }

    shift(0);

    SendLoginData(form, function () {
        document.location.reload();
    }, function (sText) {
        document.getElementById('message').innerHTML = sText;
        if (shakeTimerId === null) {
            animationStartedAt = null;
            shakeTimerId = requestAnimationFrame(animateShake);
        }
    });
}

function LoginInit() {
    const form = document.forms['loginform'];
    const eLogin = form.elements['login'];
    const ePass = form.elements['pass'];

    eLogin.focus();
    ePass.removeAttribute('disabled');

    let login = '', password = '';

    eLogin.onkeyup = ePass.onkeyup = function () {
        if (shakeTimerId !== null) {
            return;
        }

        if (login !== eLogin.value || password !== ePass.value) {
            document.getElementById('message').innerHTML = '';
            login = eLogin.value;
            password = ePass.value;
        }
    };
}

document.addEventListener('DOMContentLoaded', () => {
    document.body.addEventListener('keydown', function(e) {
        // Disable sending form on Enter on new and edit forms to prevent partial submission
        if (e.key === 'Enter' && (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT')) {
            if (e.target.closest('.edit-content') || e.target.closest('.new-content')) {
                e.preventDefault();

                const formElements = e.target.form.elements;
                let index = Array.prototype.indexOf.call(formElements, e.target);

                if (index < 0) {
                    return;
                }
                while (index < formElements.length - 1) {
                    index++;
                    if (formElements[index].tabIndex !== -1) {
                        formElements[index].focus();
                        break;
                    }
                }
            }
        }
    });
})
