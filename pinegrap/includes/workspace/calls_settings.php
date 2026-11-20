<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Calls settings: the switch (off until someone turns it on,
 * so an upgraded site gets no call buttons by surprise), the STUN addresses
 * the browsers ask for their public route, and an optional TURN relay.
 *
 * Kept in config_text, no schema of its own: `ws_calls` holds the plain
 * settings as JSON (on, stun, turn_url, turn_user) and `ws_calls_turn_secret`
 * the relay's shared secret, sealed with encrypt_string_with_iv() and listed
 * in pg_reseal_registry(). The secret is never drawn back, never in an API
 * or MCP answer and never logged; a call's members get time-limited
 * credentials made from it (the TURN REST scheme: username
 * "<expiry>:<user>", password base64(HMAC-SHA1(secret, username))).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The STUN address used while the card names none: a third party the browsers reach. */
define('WS_CALL_DEFAULT_STUN', 'stun:stun.l.google.com:19302');

/** Seconds a member's TURN credentials stay valid. */
define('WS_CALL_TURN_LIFE', 21600);

/** The most STUN or TURN addresses the card keeps. */
define('WS_CALL_ICE_MAX', 5);

/**
 * The plain settings read from their stored JSON. Unknown keys, a secret
 * among them, are dropped. Missing or broken JSON reads as off. Pure.
 *
 * @param string $json
 * @return array on (bool), stun (string[]), turn_url (string[]), turn_user (string)
 */
function ws_calls_settings_parse($json)
{
    $data = json_decode((string) $json, true);
    $data = is_array($data) ? $data : array();
    $list = function ($value) {
        return array_values(array_filter(array_map('strval', is_array($value) ? $value : array()), 'strlen'));
    };

    return array(
        'on'        => !empty($data['on']),
        'stun'      => $list($data['stun'] ?? null),
        'turn_url'  => $list($data['turn_url'] ?? null),
        'turn_user' => (string) ($data['turn_user'] ?? ''),
    );
}

/**
 * The site's call settings, with whether a TURN secret is stored (never the
 * secret itself).
 *
 * @return array ws_calls_settings_parse() + secret_set (bool)
 */
function ws_calls_settings()
{
    $settings = ws_calls_settings_parse(pg_config_text('ws_calls'));
    $settings['secret_set'] = (pg_config_text('ws_calls_turn_secret') !== '');

    return $settings;
}

/**
 * Are calls on: the tables are there and the card's switch is on.
 *
 * @return bool
 */
function ws_calls_on()
{
    if (!ws_calls_ready()) {
        return false;
    }

    $settings = ws_calls_settings();

    return $settings['on'];
}

/**
 * May this person change the Calls card: a team member with the Workspace
 * settings right.
 *
 * @param array $viewer ws_viewer()
 * @return bool
 */
function ws_calls_settings_allowed($viewer)
{
    return !empty($viewer['member']) && !empty($viewer['settings']);
}

/**
 * May this person change the relay (TURN) part and ask for a test with its
 * credentials: an administrator, as with the other services' keys.
 *
 * @param array $viewer ws_viewer()
 * @return bool
 */
function ws_calls_turn_allowed($viewer)
{
    return ws_calls_settings_allowed($viewer) && isset($viewer['role']) && ((int) $viewer['role'] === 0);
}

/**
 * The addresses of one box, one a line (commas and spaces split too),
 * checked against the scheme(s) allowed: host[:port], an optional
 * ?transport=udp|tcp. Pure.
 *
 * @param string   $text
 * @param string[] $schemes e.g. array('stun', 'stuns')
 * @return array list (string[], the valid ones), bad (string[], the refused ones)
 */
function ws_call_ice_lines($text, $schemes)
{
    $list = array();
    $bad = array();
    $pattern = '/^(' . implode('|', array_map('preg_quote', $schemes)) . '):(\[[0-9a-f:.]+\]|[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?)(:[0-9]{1,5})?(\?transport=(udp|tcp))?$/i';

    foreach (preg_split('/[\s,]+/', trim((string) $text)) as $line) {
        if ($line === '') {
            continue;
        }

        $port = preg_match('/:([0-9]{1,5})(\?|$)/', $line, $match) ? (int) $match[1] : 1;

        if (preg_match($pattern, $line) && ($port >= 1) && ($port <= 65535)) {
            if (!in_array($line, $list, true)) {
                $list[] = $line;
            }
        } else {
            $bad[] = $line;
        }
    }

    if (count($list) > WS_CALL_ICE_MAX) {
        $bad = array_merge($bad, array_slice($list, WS_CALL_ICE_MAX));
        $list = array_slice($list, 0, WS_CALL_ICE_MAX);
    }

    return array('list' => $list, 'bad' => $bad);
}

/**
 * Time-limited TURN credentials made from the shared secret (the TURN REST
 * scheme coturn's use-auth-secret and most relay services take). Pure.
 *
 * @param string $secret
 * @param string $user  the label after the expiry ('' gives "pinegrap")
 * @param int    $now
 * @param int    $life  seconds
 * @return array username, credential
 */
function ws_call_turn_credentials($secret, $user, $now, $life = WS_CALL_TURN_LIFE)
{
    $user = preg_replace('/[^A-Za-z0-9_.@-]/', '', (string) $user);
    $username = ((int) $now + (int) $life) . ':' . (($user !== '') ? $user : 'pinegrap');

    return array(
        'username'   => $username,
        'credential' => base64_encode(hash_hmac('sha1', $username, (string) $secret, true)),
    );
}

/**
 * The RTCIceServer list for given settings: the STUN addresses (the default
 * when none), then the TURN addresses with credentials when a secret is
 * given. Without a secret no TURN entry is made. Pure.
 *
 * @param array  $settings ws_calls_settings_parse()
 * @param string $secret   the opened TURN secret, '' for none
 * @param int    $now
 * @return array[]
 */
function ws_call_ice_servers_from($settings, $secret, $now)
{
    $stun = !empty($settings['stun']) ? array_values($settings['stun']) : array(WS_CALL_DEFAULT_STUN);
    $servers = array(array('urls' => $stun));

    if (((string) $secret !== '') && !empty($settings['turn_url'])) {
        $servers[] = array('urls' => array_values($settings['turn_url'])) + ws_call_turn_credentials($secret, $settings['turn_user'] ?? '', $now);
    }

    return $servers;
}

/**
 * The stored TURN secret, opened. Only for a call's member's credentials
 * and the card's test; never printed.
 *
 * @return string '' when none is stored or it does not open
 */
function ws_calls_turn_secret()
{
    $stored = pg_config_text('ws_calls_turn_secret');

    if (($stored === '') || (strpos($stored, ':') === false) || !function_exists('decode_ssl_keys') || !defined('ENCRYPTION_KEY')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $stored, 2);

    return trim((string) decode_ssl_keys($cipher, $iv));
}

/**
 * The ICE servers of a call's member: STUN and, with a relay, fresh TURN
 * credentials. Called only after a start or join was granted (the panel's
 * ws_call_start / ws_call_join, the guest page's call_join).
 *
 * @return array[]
 */
function ws_call_member_ice()
{
    return ws_call_ice_servers_from(ws_calls_settings(), ws_calls_turn_secret(), time());
}

/**
 * The card on Site Settings › Channels. Off, STUN, the relay (an
 * administrator's) and the browser's test.
 *
 * @param array $viewer ws_viewer()
 * @return string HTML, '' when the person may not change it or calls are not in the database
 */
function ws_calls_settings_card($viewer)
{
    if (!ws_calls_ready() || !ws_calls_settings_allowed($viewer)) {
        return '';
    }

    return ws_calls_settings_card_html(ws_calls_settings(), ws_calls_turn_allowed($viewer), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php');
}

/**
 * The card's markup for given settings. The secret is never an input's
 * value: the box is drawn empty and says whether one is stored.
 *
 * @param array  $settings ws_calls_settings()
 * @param bool   $admin    may change the relay
 * @param string $api_url
 * @return string
 */
function ws_calls_settings_card_html($settings, $admin, $api_url)
{
    $disabled = $admin ? '' : ' disabled';
    $state = $settings['on']
        ? '<span class="pg-pill pg-pill-success">' . h(lang('On')) . '</span>'
        : '<span class="pg-pill">' . h(lang('Off')) . '</span>';
    $secret_help = $settings['secret_set']
        ? lang('A secret is stored. Type a new one to replace it.')
        : lang('The shared secret of the relay (static-auth-secret).');

    return '
    <div id="pgset-ws-calls" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="pg-title-section ws-grow">' . h(lang('Calls')) . '</span>
                ' . $state . '
            </div>
            <div class="card-body" data-ws-calls-card data-api="' . h($api_url) . '">
                <input type="hidden" name="ws_calls_card" value="1">
                <div class="d-flex align-items-start gap-3 mb-5">
                    <input class="pg-switch" type="checkbox" name="ws_calls_on" value="1" id="ws_calls_on" aria-describedby="ws_calls_on-help"' . ($settings['on'] ? ' checked' : '') . '>
                    <div><label class="pg-text-2" for="ws_calls_on">' . h(lang('Voice and video calls in the channels')) . '</label>
                    <div class="pg-field-help" id="ws_calls_on-help">' . h(lang('Sound and picture go from browser to browser; the site carries only who is in a call and the browsers\' connection messages. Nothing is recorded. Off, the call buttons are not shown and no call can be started.')) . '</div></div>
                </div>
                <div class="mb-5">
                    <label class="pg-field-label" for="ws_calls_stun">' . h(lang('STUN addresses')) . '</label>
                    <textarea class="pg-input pg-input-sm font-monospace" name="ws_calls_stun" id="ws_calls_stun" rows="2" placeholder="' . h(WS_CALL_DEFAULT_STUN) . '" aria-describedby="ws_calls_stun-help">' . h(implode("\n", $settings['stun'])) . '</textarea>
                    <div class="pg-field-help" id="ws_calls_stun-help">' . h(lang(array(
                        'string' => 'One a line. The browsers ask these for their public address. Left empty, {var:1} is used: a service of Google that every browser in a call reaches.',
                        'vars'   => WS_CALL_DEFAULT_STUN,
                    ))) . '</div>
                </div>
                <h3 class="pg-group-label mt-7 mb-3">' . h(lang('Relay (TURN)')) . '</h3>
                <div class="pg-field-help mt-0 mb-5">' . h(lang('About one person in ten cannot connect directly and needs a relay. Optional: a relay service of your own, with a shared secret. Its members get credentials valid for a few hours only.')) . '</div>
                <div class="mb-5">
                    <label class="pg-field-label" for="ws_calls_turn_url">' . h(lang('Relay addresses')) . '</label>
                    <textarea class="pg-input pg-input-sm font-monospace" name="ws_calls_turn_url" id="ws_calls_turn_url" rows="2" placeholder="turn:turn.example.com:3478" autocomplete="off"' . $disabled . '>' . h(implode("\n", $settings['turn_url'])) . '</textarea>
                </div>
                <div class="row g-5 mb-5">
                    <div class="sm:col-6">
                        <label class="pg-field-label" for="ws_calls_turn_user">' . h(lang('User')) . '</label>
                        <input class="pg-input pg-input-sm font-monospace" type="text" name="ws_calls_turn_user" id="ws_calls_turn_user" value="' . h($settings['turn_user']) . '" maxlength="64" placeholder="pinegrap" autocomplete="off"' . $disabled . '>
                    </div>
                    <div class="sm:col-6">
                        <label class="pg-field-label" for="ws_calls_turn_secret">' . h(lang('Secret')) . '</label>
                        <input class="pg-input pg-input-sm font-monospace" type="password" name="ws_calls_turn_secret" id="ws_calls_turn_secret" value="" autocomplete="new-password" aria-describedby="ws_calls_turn_secret-help"' . $disabled . '>
                        <div class="pg-field-help" id="ws_calls_turn_secret-help">' . h($secret_help) . '</div>
                        ' . (($admin && $settings['secret_set']) ? '<label class="pg-text-2" for="ws_calls_turn_secret_clear"><input class="pg-checkbox" type="checkbox" name="ws_calls_turn_secret_clear" value="1" id="ws_calls_turn_secret_clear"> <span>' . h(lang('Remove the stored secret')) . '</span></label>' : '') . '
                    </div>
                </div>
                ' . ($admin ? '' : '<div class="pg-field-help mb-5">' . h(lang('Only an administrator can change the relay.')) . '</div>') . '
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-calls-test>' . h(lang('Test the connection')) . '</button>
                    <span class="pg-text-2" data-ws-calls-result role="status" aria-live="polite"></span>
                </div>
            </div>
        </div>
    </div>' . ws_calls_settings_script();
}

/**
 * The card's test: asks the server for the ICE servers of what the boxes
 * hold, gathers candidates in this browser for a few seconds and says what
 * it found (a public address through STUN, a relay through TURN).
 *
 * @return string
 */
function ws_calls_settings_script()
{
    $words = array(
        'running'   => lang('Testing…'),
        'none'      => lang('Nothing answered: the browser found no public address. Check the STUN addresses, or a firewall that blocks UDP.'),
        'stun'      => lang('STUN works: the browser found its public address.'),
        'no_stun'   => lang('STUN did not answer.'),
        'turn'      => lang('The relay works: the browser got a relayed address.'),
        'no_turn'   => lang('The relay did not answer: check its address, the user and the secret.'),
        'no_browser' => lang('This browser cannot test calls.'),
    );

    return '
        <script>
        (function () {
            if (window.pgWsCallsTestBound) {
                return;
            }

            window.pgWsCallsTestBound = true;
            var words = ' . json_encode($words, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';

            function gather(servers) {
                return new Promise(function (resolve) {
                    var found = { srflx: false, relay: false };
                    var pc = new RTCPeerConnection({ iceServers: servers });
                    var done = function () {
                        try { pc.close(); } catch (e) {}
                        resolve(found);
                    };

                    pc.onicecandidate = function (event) {
                        if (!event.candidate) {
                            done();
                            return;
                        }

                        var type = event.candidate.type || ((/ typ (\w+)/.exec(event.candidate.candidate || \'\') || [])[1]);

                        if (type === \'srflx\' || type === \'relay\') {
                            found[type] = true;
                        }
                    };
                    pc.createDataChannel(\'test\');
                    pc.createOffer().then(function (offer) { return pc.setLocalDescription(offer); }).catch(done);
                    setTimeout(done, 8000);
                });
            }

            document.addEventListener(\'click\', function (event) {
                var button = event.target.closest ? event.target.closest(\'[data-ws-calls-test]\') : null;
                var card = button ? button.closest(\'[data-ws-calls-card]\') : null;

                if (!card) {
                    return;
                }

                var result = card.querySelector(\'[data-ws-calls-result]\');
                var form = card.closest(\'form\');
                var token = (form && form.querySelector(\'input[name="token"]\')) || document.querySelector(\'input[name="token"]\');
                var value = function (name) {
                    var field = card.querySelector(\'[name="\' + name + \'"]\');
                    return field ? field.value : \'\';
                };

                if (!window.RTCPeerConnection) {
                    result.textContent = words.no_browser;
                    return;
                }

                button.disabled = true;
                result.textContent = words.running;
                fetch(card.getAttribute(\'data-api\'), {
                    method: \'POST\',
                    credentials: \'same-origin\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify({
                        action: \'ws_calls_test_ice\',
                        token: token ? token.value : \'\',
                        stun: value(\'ws_calls_stun\'),
                        turn_url: value(\'ws_calls_turn_url\'),
                        turn_user: value(\'ws_calls_turn_user\'),
                        turn_secret: value(\'ws_calls_turn_secret\')
                    })
                }).then(function (response) {
                    return response.json();
                }).then(function (json) {
                    if (!json || json.status !== \'success\') {
                        throw new Error((json && json.message) || \'\');
                    }

                    var data = json.data || {};

                    return gather(data.ice || []).then(function (found) {
                        var lines = [found.srflx ? words.stun : (found.relay ? words.no_stun : words.none)];

                        if (data.turn) {
                            lines.push(found.relay ? words.turn : words.no_turn);
                        }

                        result.textContent = lines.join(\' \');
                    });
                }).catch(function (error) {
                    result.textContent = (error && error.message) || words.none;
                }).then(function () {
                    button.disabled = false;
                });
            });
        })();
        </script>';
}

/**
 * The ICE servers the card's test gathers against, from the boxes as typed:
 * a typed secret, else the stored one. The relay part only for an
 * administrator. The credentials go to the person testing, nowhere else.
 *
 * @param array $viewer  ws_viewer()
 * @param array $request stun, turn_url, turn_user, turn_secret
 * @return array ok, error, ice, turn (bool: a relay is being tested)
 */
function ws_calls_test_ice($viewer, $request)
{
    if (!ws_calls_settings_allowed($viewer)) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    $stun = ws_call_ice_lines((string) ($request['stun'] ?? ''), array('stun', 'stuns'));

    if (!empty($stun['bad'])) {
        return array('ok' => false, 'error' => lang(array('string' => 'Not a STUN address: {var:1}', 'vars' => $stun['bad'][0])));
    }

    $settings = array('stun' => $stun['list'], 'turn_url' => array(), 'turn_user' => '');
    $secret = '';

    if (ws_calls_turn_allowed($viewer)) {
        $turn = ws_call_ice_lines((string) ($request['turn_url'] ?? ''), array('turn', 'turns'));

        if (!empty($turn['bad'])) {
            return array('ok' => false, 'error' => lang(array('string' => 'Not a relay address: {var:1}', 'vars' => $turn['bad'][0])));
        }

        $settings['turn_url'] = $turn['list'];
        $settings['turn_user'] = trim((string) ($request['turn_user'] ?? ''));
        $secret = (string) ($request['turn_secret'] ?? '');
        $secret = ($secret !== '') ? $secret : ws_calls_turn_secret();
    }

    $ice = ws_call_ice_servers_from($settings, $secret, time());

    return array('ok' => true, 'error' => '', 'ice' => $ice, 'turn' => count($ice) > 1);
}

/**
 * What a save of the card writes, from the posted fields and what is
 * stored: the JSON row and the secret's fate. Pure.
 *
 * @param array $stored ws_calls_settings()
 * @param array $post
 * @param bool  $admin  may change the relay
 * @return array ok, field, error, settings (to store), secret ('keep', 'clear' or the new secret), changed (bool)
 */
function ws_calls_settings_from_post($stored, $post, $admin)
{
    $stun = ws_call_ice_lines((string) ($post['ws_calls_stun'] ?? ''), array('stun', 'stuns'));

    if (!empty($stun['bad'])) {
        return array('ok' => false, 'field' => 'ws_calls_stun', 'error' => lang(array('string' => 'Not a STUN address: {var:1}', 'vars' => $stun['bad'][0])));
    }

    $settings = array(
        'on'        => !empty($post['ws_calls_on']),
        'stun'      => $stun['list'],
        'turn_url'  => $stored['turn_url'],
        'turn_user' => $stored['turn_user'],
    );
    $secret = 'keep';

    if ($admin) {
        $turn = ws_call_ice_lines((string) ($post['ws_calls_turn_url'] ?? ''), array('turn', 'turns'));

        if (!empty($turn['bad'])) {
            return array('ok' => false, 'field' => 'ws_calls_turn_url', 'error' => lang(array('string' => 'Not a relay address: {var:1}', 'vars' => $turn['bad'][0])));
        }

        $settings['turn_url'] = $turn['list'];
        $settings['turn_user'] = preg_replace('/[^A-Za-z0-9_.@-]/', '', trim((string) ($post['ws_calls_turn_user'] ?? '')));
        $typed = (string) ($post['ws_calls_turn_secret'] ?? '');

        if ($typed !== '') {
            $secret = $typed;
        } elseif (!empty($post['ws_calls_turn_secret_clear'])) {
            $secret = 'clear';
        }

        if (!empty($settings['turn_url']) && ($secret === 'clear' || (($secret === 'keep') && empty($stored['secret_set'])))) {
            return array('ok' => false, 'field' => 'ws_calls_turn_secret', 'error' => lang('A relay needs its secret.'));
        }
    }

    $before = array('on' => $stored['on'], 'stun' => $stored['stun'], 'turn_url' => $stored['turn_url'], 'turn_user' => $stored['turn_user']);
    $changed = ($settings !== $before) || ($secret !== 'keep');

    return array('ok' => true, 'field' => '', 'error' => '', 'settings' => $settings, 'secret' => $secret, 'changed' => $changed);
}

/**
 * The card's save, with the pane's Save: nothing when the card was not in
 * the form or the person may not change it. A refused box is marked and
 * nothing of the card is written. The Site Log line and the audit row name
 * what changed, never the secret.
 *
 * @param array    $viewer ws_viewer()
 * @param array    $post
 * @param callable $refuse function ($field, $sentence)
 * @return bool whether it was written
 */
function ws_calls_settings_save($viewer, $post, $refuse)
{
    if (empty($post['ws_calls_card']) || !ws_calls_ready() || !ws_calls_settings_allowed($viewer)) {
        return false;
    }

    $stored = ws_calls_settings();
    $result = ws_calls_settings_from_post($stored, $post, ws_calls_turn_allowed($viewer));

    if (!$result['ok']) {
        $refuse($result['field'], $result['error']);

        return false;
    }

    if (!$result['changed']) {
        return false;
    }

    if ($result['secret'] === 'clear') {
        pg_config_text_save('ws_calls_turn_secret', '');
    } elseif ($result['secret'] !== 'keep') {
        list($cipher, $iv) = encrypt_string_with_iv($result['secret']);
        pg_config_text_save('ws_calls_turn_secret', $cipher . ':' . $iv);
    }

    pg_config_text_save('ws_calls', json_encode($result['settings'], JSON_UNESCAPED_SLASHES));

    $after = $result['settings'];
    $after['turn_secret'] = ($result['secret'] === 'keep') ? 'kept' : (($result['secret'] === 'clear') ? 'removed' : 'replaced');
    log_activity('Workspace call settings were changed (calls ' . ($after['on'] ? 'on' : 'off') . ').');

    if (function_exists('pg_audit')) {
        pg_audit('update', 'settings', 0, array('on' => $stored['on'], 'stun' => $stored['stun'], 'turn_url' => $stored['turn_url'], 'turn_user' => $stored['turn_user']), $after, 'workspace_calls');
    }

    return true;
}
