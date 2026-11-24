/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - one renewal of a stale form token. A Workspace page keeps the
 * form token it was drawn with. When the server drops an idle session (a
 * page left open overnight), the remember-me cookie signs the person in
 * again on the next request and that sign-in renews the token, so the
 * page's writes are refused with code 'token' although the person is still
 * signed in. The request is then sent once more with the session's current
 * token (api.php's session_token action). A refusal of the renewal itself
 * (code 'session': the sign-in really ended) is handed to the caller, which
 * keeps what the person typed.
 *
 * window.PGWsTokenRetry, used by api() and apiUpload() in
 * assets/js/workspace.js. Also loaded by Node in
 * tests/workspace_token_retry_test.php.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // Whether a refused request is worth sending again after a renewal:
    // only a token refusal, never a refusal of the session or anything else.
    function stale(error) {
        return !!error && (error.code === 'token');
    }

    // fetchToken() resolves to the session's current token; apply(token)
    // puts it where the next request reads it. Requests refused at the same
    // time share one renewal; an answer without a token is a failure.
    function renewer(fetchToken, apply) {
        var running = null;

        return function () {
            if (!running) {
                running = Promise.resolve().then(fetchToken).then(function (token) {
                    running = null;

                    if ((typeof token !== 'string') || (token === '')) {
                        throw new Error('');
                    }

                    apply(token);
                }, function (error) {
                    running = null;
                    throw error;
                });
            }

            return running;
        };
    }

    // send() makes the request with the token in force when it is called and
    // returns a promise. A token refusal renews the token and sends once
    // more; a second refusal, or a failed renewal, rejects. When the renewal
    // itself fails without saying why, the first refusal is what the caller
    // is told.
    function send(request, renew) {
        return request().catch(function (error) {
            if (!stale(error)) {
                throw error;
            }

            return renew().then(function () {
                return request();
            }, function (renewError) {
                throw (renewError && renewError.code) ? renewError : error;
            });
        });
    }

    var api = { stale: stale, renewer: renewer, send: send };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.PGWsTokenRetry = api;
    }
}(typeof window !== 'undefined' ? window : this));
