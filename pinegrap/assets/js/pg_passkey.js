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
 *   PgPasskey.signIn({url, token})   the second step of a sign-in: resolves
 *                                    with the credential JSON to post
 *   PgPasskey.register({url, token, name, currentPassword, code})
 *                                    adds a passkey to the signed-in account:
 *                                    the current password (when the account
 *                                    has one) and a code from the
 *                                    authenticator app or a recovery code go
 *                                    with the request for the options, which
 *                                    the server answers only when both are
 *                                    right; resolves with the kept passkey
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
        return post(settings.url, { action: 'signin_options', token: settings.token }).then(function (answer) {
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
            code: settings.code || ''
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
            return answer.passkey;
        });
    }

    window.PgPasskey = {
        supported: supported,
        signIn: signIn,
        register: register
    };
})(window);
