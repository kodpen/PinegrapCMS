/**
 * Pinegrap - Enterprise Website Platform
 *
 * The browser's half of a passkey ceremony with passkey.php: the options are
 * asked for, handed to navigator.credentials, and the credential comes back
 * as the JSON PublicKeyCredential.toJSON() writes, which the server reads.
 * The base64url conversions are done here rather than with
 * parseCreationOptionsFromJSON() / toJSON(), which browsers that have
 * WebAuthn do not all have yet.
 *
 *   PgPasskey.supported()            whether this browser can use passkeys
 *   PgPasskey.signIn({url, token, passwordless})
 *                                    the second step of a sign-in, or with
 *                                    passwordless a sign-in with the passkey
 *                                    alone: resolves with the credential
 *                                    JSON to post
 *   PgPasskey.register({url, token, name, currentPassword, code, emailCode})
 *                                    adds a passkey to the signed-in account:
 *                                    the current password (when the account
 *                                    has one) and a code from the
 *                                    authenticator app or a recovery code,
 *                                    or without two-step verification the
 *                                    e-mailed code, go with the request for
 *                                    the options, which the server answers
 *                                    only when they are right; resolves with
 *                                    the kept passkey, whose recoveryCodes
 *                                    lists the codes to show once (empty
 *                                    unless the account just got them)
 *   PgPasskey.sendEmailCode({url, token})
 *                                    has the server e-mail that code;
 *                                    resolves with the sentence to show
 *   PgPasskey.showRecoveryCodes(form, codes, texts)
 *                                    puts the recovery codes, a download
 *                                    link and a Continue button (which
 *                                    reloads the page) in place of the form
 *
 * On load it also binds the "Sign in with a passkey" buttons of the sign-in
 * screens (pg_passkey_signin_button()) and, where a button carries
 * data-autofill, has the browser offer passkeys in the autofill of its
 * form's email field (conditional mediation).
 *
 * A refusal rejects with an Error whose code is the server's (or the
 * browser's name: NotAllowedError when the person cancelled or the time ran
 * out) and whose message is the sentence to show, when the server gave one.
 * An answer that is not JSON with status 429 (the sign-in throttle's page
 * for the attempt that locks the account out) rejects with the code
 * attempts and no message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (window) {
    'use strict';

    function toBase64url(buffer) {
        var bytes = new Uint8Array(buffer);
        var text = '';

        for (var i = 0; i < bytes.length; i++) {
            text += String.fromCharCode(bytes[i]);
        }

        return window.btoa(text).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function fromBase64url(text) {
        var base64 = String(text).replace(/-/g, '+').replace(/_/g, '/');

        while (base64.length % 4) {
            base64 += '=';
        }

        var binary = window.atob(base64);
        var bytes = new Uint8Array(binary.length);

        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes.buffer;
    }

    function copy(object) {
        var result = {};

        Object.keys(object || {}).forEach(function (key) {
            result[key] = object[key];
        });

        return result;
    }

    function descriptors(list) {
        return (list || []).map(function (item) {
            var descriptor = { type: item.type, id: fromBase64url(item.id) };

            if (item.transports) {
                descriptor.transports = item.transports;
            }

            return descriptor;
        });
    }

    function creationOptions(json) {
        var options = copy(json);

        options.challenge = fromBase64url(json.challenge);
        options.user = { id: fromBase64url(json.user.id), name: json.user.name, displayName: json.user.displayName };
        options.excludeCredentials = descriptors(json.excludeCredentials);

        return options;
    }

    function requestOptions(json) {
        var options = copy(json);

        options.challenge = fromBase64url(json.challenge);
        options.allowCredentials = descriptors(json.allowCredentials);

        return options;
    }

    function credentialJSON(credential) {
        var response = credential.response;
        var json = {
            id: credential.id,
            rawId: toBase64url(credential.rawId),
            type: credential.type,
            authenticatorAttachment: credential.authenticatorAttachment || null,
            clientExtensionResults: credential.getClientExtensionResults ? credential.getClientExtensionResults() : {},
            response: { clientDataJSON: toBase64url(response.clientDataJSON) }
        };

        if (response.attestationObject) {
            json.response.attestationObject = toBase64url(response.attestationObject);
            json.response.transports = response.getTransports ? response.getTransports() : [];
        } else {
            json.response.authenticatorData = toBase64url(response.authenticatorData);
            json.response.signature = toBase64url(response.signature);
            json.response.userHandle = response.userHandle ? toBase64url(response.userHandle) : null;
        }

        return json;
    }

    function failure(code, message) {
        var error = new Error(message || '');

        error.code = code || 'response';

        return error;
    }

    function post(url, fields) {
        var body = new window.FormData();

        Object.keys(fields).forEach(function (key) {
            body.append(key, fields[key]);
        });

        return window.fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () {
                return { ok: false, error: (response.status === 429) ? 'attempts' : 'response', message: '' };
            });
        }).then(function (answer) {
            if (!answer || !answer.ok) {
                throw failure(answer && answer.error, answer && answer.message);
            }

            return answer;
        });
    }

    // A browser refusal (cancelled, timed out, wrong site) keeps its own name
    // as the code and has no message for the visitor.
    function browserFailure(error) {
        if (error && error.code && (typeof error.code === 'string')) {
            throw error;
        }

        throw failure((error && error.name) || 'browser', '');
    }

    function supported() {
        return !!(window.PublicKeyCredential && window.navigator.credentials && window.navigator.credentials.get
            && window.navigator.credentials.create && window.fetch && window.FormData && window.isSecureContext !== false);
    }

    function signIn(settings) {
        var fields = { action: 'signin_options', token: settings.token };

        if (settings.passwordless) {
            fields.passwordless = '1';
        }

        return post(settings.url, fields).then(function (answer) {
            return window.navigator.credentials.get({ publicKey: requestOptions(answer.options) }).catch(browserFailure);
        }).then(function (credential) {
            if (!credential) {
                throw failure('NotAllowedError', '');
            }

            return credentialJSON(credential);
        });
    }

    function register(settings) {
        return post(settings.url, {
            action: 'register_options',
            token: settings.token,
            current_password: settings.currentPassword || '',
            code: settings.code || '',
            email_code: settings.emailCode || ''
        }).then(function (answer) {
            return window.navigator.credentials.create({ publicKey: creationOptions(answer.options) }).catch(browserFailure);
        }).then(function (credential) {
            if (!credential) {
                throw failure('NotAllowedError', '');
            }

            return post(settings.url, {
                action: 'register',
                token: settings.token,
                name: settings.name || '',
                credential: JSON.stringify(credentialJSON(credential))
            });
        }).then(function (answer) {
            var passkey = answer.passkey || {};

            passkey.recoveryCodes = answer.recovery_codes || [];

            return passkey;
        });
    }

    function sendEmailCode(settings) {
        return post(settings.url, { action: 'email_code', token: settings.token }).then(function (answer) {
            return answer.message || '';
        });
    }

    // texts: title, intro, download, continue (the server's sentences).
    function showRecoveryCodes(form, codes, texts) {
        var document = window.document;
        var block = document.createElement('div');
        var list = codes.join('\n');
        var element = function (tag, text) {
            var node = document.createElement(tag);

            node.textContent = text;
            block.appendChild(node);

            return node;
        };

        element('strong', texts.title);
        element('p', texts.intro);
        element('pre', list).style.letterSpacing = '.08em';

        var download = element('a', texts.download);

        download.setAttribute('download', 'recovery-codes.txt');
        download.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(list + '\n');
        download.style.marginRight = '1em';

        var button = element('button', texts['continue']);

        button.type = 'button';
        button.className = 'software_input_submit_primary';
        button.addEventListener('click', function () {
            window.location.reload();
        });

        form.parentNode.insertBefore(block, form);
        form.hidden = true;
        form.style.display = 'none';
    }

    // Posts a passkey sign-in answer to index.php in a form built here: the
    // button usually sits inside the password form, which must not be sent.
    function submitSignIn(button, credential) {
        var outer = button.form;
        var remember = outer && outer.elements.remember_me;
        var form = window.document.createElement('form');
        var fields = {
            token: button.getAttribute('data-token'),
            pg_passkey_signin: '1',
            credential: JSON.stringify(credential),
            send_to: button.getAttribute('data-send-to') || '',
            return_to: button.getAttribute('data-return-to') || '',
            remember_me: (remember && remember.checked) ? '1' : ''
        };

        form.method = 'post';
        form.action = button.getAttribute('data-action');
        form.style.display = 'none';

        Object.keys(fields).forEach(function (key) {
            var input = window.document.createElement('input');

            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });

        window.document.body.appendChild(form);
        form.submit();
    }

    // The pending autofill request (conditional mediation), if any; a click
    // on a button aborts it, since a browser runs one request at a time.
    // optionsSent settles once its options were answered: the server keeps
    // one sign-in ceremony per session, so a button asks for its own only
    // after that, or the later answer would replace the button's.
    var autofill = null;
    var optionsSent = null;

    // Has the browser offer the site's passkeys in the autofill of the email
    // field of the button's form (data-autofill: a screen that only signs
    // in). Silent on any failure: the field and the button still work.
    function startAutofill(button) {
        var field = button.form && button.form.elements.email;

        if (autofill || !field || !field.getAttribute || !window.AbortController
            || !window.PublicKeyCredential.isConditionalMediationAvailable) {
            return;
        }

        window.PublicKeyCredential.isConditionalMediationAvailable().then(function (available) {
            if (!available || autofill || button.disabled) {
                return;
            }

            // The webauthn token must come last in autocomplete.
            var tokens = (field.getAttribute('autocomplete') || 'username').split(/\s+/).filter(function (token) {
                return token !== '' && token !== 'webauthn';
            });

            field.setAttribute('autocomplete', tokens.concat('webauthn').join(' '));

            var controller = new window.AbortController();

            autofill = controller;

            var options = post(button.getAttribute('data-options-url'), {
                action: 'signin_options',
                token: button.getAttribute('data-token'),
                passwordless: '1'
            });

            optionsSent = options.catch(function () {
                return null;
            });

            return options.then(function (answer) {
                return window.navigator.credentials.get({
                    publicKey: requestOptions(answer.options),
                    mediation: 'conditional',
                    signal: controller.signal
                });
            }).then(function (credential) {
                if (credential && autofill === controller) {
                    submitSignIn(button, credentialJSON(credential));
                }
            });
        }).catch(function () {
            autofill = null;
        });
    }

    // The "Sign in with a passkey" buttons (pg_passkey_signin_button()): shown
    // when the browser can use a passkey; the answer is posted to index.php.
    function bindSignInButtons() {
        if (!supported()) {
            return;
        }

        var buttons = window.document.querySelectorAll('button[data-pg-passkey-signin]');

        Array.prototype.forEach.call(buttons, function (button) {
            if (button.getAttribute('data-pg-passkey-bound')) {
                return;
            }

            button.setAttribute('data-pg-passkey-bound', '1');

            var box = button.parentNode;
            var notice = box.querySelector('.pg-passkey-signin-error');
            var texts = {};

            try {
                texts = JSON.parse(button.getAttribute('data-texts') || '{}');
            } catch (ignore) {
                texts = {};
            }

            box.hidden = false;

            if (button.getAttribute('data-autofill')) {
                startAutofill(button);
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                notice.hidden = true;

                if (autofill) {
                    autofill.abort();
                    autofill = null;
                }

                (optionsSent || window.Promise.resolve()).then(function () {
                    return signIn({
                        url: button.getAttribute('data-options-url'),
                        token: button.getAttribute('data-token'),
                        passwordless: true
                    });
                }).then(function (credential) {
                    submitSignIn(button, credential);
                }).catch(function (error) {
                    button.disabled = false;
                    notice.textContent = error.message || ((error.code === 'NotAllowedError' || error.code === 'AbortError') ? texts.cancelled : texts.failed) || '';
                    notice.hidden = false;
                });
            });
        });
    }

    window.PgPasskey = {
        supported: supported,
        signIn: signIn,
        register: register,
        sendEmailCode: sendEmailCode,
        showRecoveryCodes: showRecoveryCodes
    };

    if (window.document.readyState === 'loading') {
        window.document.addEventListener('DOMContentLoaded', bindSignInButtons);
    } else {
        bindSignInButtons();
    }
})(window);
