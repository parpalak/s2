<?php

/** @var callable $trans */
/** @var string $basePath */
/** @var string $challenge */
/** @var string $salt */
/** @var string $errorMessage */

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $trans('Admin panel'), S2_SITE_NAME ? ' - ' . S2_SITE_NAME : ''; ?></title>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/admin-override.css">
    <script src="js/ajax.js"></script>
    <script>
        let shakeTimerId = null;

        async function SendLoginData(eForm, fOk, fFail) {
            const key = hex_md5(hex_md5(eForm.pass.value + 'Life is not so easy :-)') + ';-)' + eForm.getAttribute('data-salt'));
            try {
                let response = await fetch('?action=login', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams('login=' + eForm.login.value + '&key=' + key + '&challenge=' + eForm.challenge.value)
                });

                let result = await response.json();
                if (result.success) {
                    fOk();

                } else if (result.status === 'NEW_SALT') {
                    eForm.setAttribute('data-salt', result.salt);
                    eForm.challenge.value = result.challenge;
                    setTimeout(() => {
                        SendLoginData(eForm, fOk, fFail);
                    }, 0);

                } else {
                    fFail(result.message);
                }
            } catch (error) {
                fFail('An error occurred: ' + error.message);
            }
        }

        function SendForm() {
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
    </script>
</head>
<body class="login_page" onload="LoginInit();">
<div id="login_wrap">
    <noscript><p><?php echo $trans('Noscript'); ?></p></noscript>
    <form name="loginform" class="loginform" method="post" action="" data-salt="<?php echo $salt ?>"
          onsubmit="SendForm(); return false;">
        <p>
            <label>
                <span><?php echo $trans('Login'); ?></span>
                <input type="text" name="login" size="30" maxlength="255">
            </label>
            <label>
                <span><?php echo $trans('Password'); ?></span>
                <input type="password" name="pass" size="30" maxlength="255" disabled="disabled">
            </label>
        </p>
        <p>
            <input type="submit" name="button" value="<?php echo $trans('Log in'); ?>"/>
            <input type="hidden" name="key" value=""/>
            <input type="hidden" name="challenge" value="<?php echo $challenge ?>"/>
        </p>
    </form>
    <p id="message" class="message"><?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
</div>
</body>
</html>
