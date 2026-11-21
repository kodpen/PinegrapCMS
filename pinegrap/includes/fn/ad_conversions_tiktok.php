<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * TikTok Events API (Events API 2.0): the sender of ad_conversion_events rows
 * for the service tiktok_events, the body it posts, how TikTok's answers are
 * read, the settings and sealed keys typed on
 * connections.php?service=tiktok_events, and that page's "Connection details"
 * card.
 *
 * The settings share config_text row ad_conversions_tiktok_events with what
 * ad_conversions.php reads (connected, events, person_notice_off,
 * without_person) and add pixel_code, token (the access token, sealed with
 * pg_connect_seal()) and test_event_code. The test event code is sent only
 * with "Send a test event", never with a queued event.
 *
 * TikTok hashes the phone number in E.164 form, with its leading +, where
 * Meta hashes the digits alone; so the phone is hashed here, from the order,
 * and never taken from the shared event's phone hash.
 *
 * The request's facts (address, version, body, answers) are read from
 * TikTok's own documents and kept in docs/memory/api/tiktok-events-facts.md.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The address events are posted to (Events API 2.0, API version v1.3).
define('PG_AD_CONVERSION_TIKTOK_URL', 'https://business-api.tiktok.com/open_api/v1.3/event/track/');

// The config_text row of the service.
define('PG_AD_CONVERSION_TIKTOK_ROW', 'ad_conversions_tiktok_events');

/**
 * The events the page can tick, our name => TikTok's standard event name.
 * Pure.
 *
 * @return array
 */
function pg_ad_conversion_tiktok_events()
{
    return array(
        'purchase' => 'Purchase',
        'add_to_cart' => 'AddToCart',
        'begin_checkout' => 'InitiateCheckout',
        'complete_registration' => 'CompleteRegistration',
        'lead' => 'Lead',
    );
}

/**
 * TikTok's name of one of our events, '' for one TikTok has no event for (a
 * refund). Pure.
 *
 * @param string $event
 * @return string
 */
function pg_ad_conversion_tiktok_event_name($event)
{
    $names = pg_ad_conversion_tiktok_events();

    return isset($names[$event]) ? $names[$event] : '';
}

/**
 * Whether a pixel code has the shape Events Manager shows (C4ABCDEF…):
 * 10 to 32 capital letters and digits. Pure.
 *
 * @param mixed $code
 * @return bool
 */
function pg_ad_conversion_tiktok_pixel_valid($code)
{
    return is_string($code) && (bool) preg_match('/^[A-Z0-9]{10,32}$/D', $code);
}

/**
 * Whether an access token has a token's shape: 20 to 512 letters, digits,
 * dashes and underscores. Pure.
 *
 * @param mixed $token
 * @return bool
 */
function pg_ad_conversion_tiktok_token_valid($token)
{
    return is_string($token) && (bool) preg_match('/^[A-Za-z0-9_\-]{20,512}$/D', $token);
}

/**
 * Whether a test event code has the shape Events Manager shows (TEST12345):
 * up to 40 letters and digits, '' allowed. Pure.
 *
 * @param mixed $code
 * @return bool
 */
function pg_ad_conversion_tiktok_test_code_valid($code)
{
    return is_string($code) && (bool) preg_match('/^[A-Za-z0-9]{0,40}$/D', $code);
}

/**
 * The stored settings row as an array, every key present. Pure.
 *
 * @param mixed $raw the stored JSON
 * @return array the keys of pg_ad_conversion_settings_parse() and
 *               pixel_code, token (sealed), test_event_code
 */
function pg_ad_conversion_tiktok_stored($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : array();

    return pg_ad_conversion_settings_parse($raw) + array(
        'pixel_code' => (string) ($data['pixel_code'] ?? ''),
        'token' => (string) ($data['token'] ?? ''),
        'test_event_code' => (string) ($data['test_event_code'] ?? ''),
    );
}

/**
 * The settings the page's form writes, from what is stored and what was
 * typed. An empty token field keeps the stored token; the choices are taken
 * as posted (pg_ad_conversion_choices_merge()). Pure.
 *
 * @param array  $stored pg_ad_conversion_tiktok_stored()
 * @param array  $post   pixel_code, test_event_code and the choices of
 *                       pg_ad_conversion_choices_merge()
 * @param string $sealed the newly sealed token, '' to keep the stored one
 * @return array what is saved as JSON
 */
function pg_ad_conversion_tiktok_settings_merge(array $stored, array $post, $sealed)
{
    $token = ((string) $sealed !== '') ? (string) $sealed : (string) ($stored['token'] ?? '');
    $pixel = strtoupper(trim((string) ($post['pixel_code'] ?? '')));

    return array(
        'connected' => ($pixel !== '') && ($token !== ''),
        'pixel_code' => $pixel,
        'token' => $token,
        'test_event_code' => trim((string) ($post['test_event_code'] ?? '')),
    ) + pg_ad_conversion_choices_merge(array_keys(pg_ad_conversion_tiktok_events()), $post);
}

/**
 * A phone number as TikTok asks it: E.164 (a + and the digits with the
 * country code; a leading 00 dropped), hashed with SHA-256; '' for fewer than
 * 7 digits. Pure.
 *
 * @param mixed $phone
 * @return string
 */
function pg_ad_conversion_tiktok_hash_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    return (strlen($digits) >= 7) ? hash('sha256', '+' . $digits) : '';
}

/**
 * One event as TikTok's web event (a member of the body's data array): the
 * hashed e-mail and phone, the browser and click ids, the address and the
 * browser's name only when the event carries the person; amounts as numbers;
 * the page the site's address. Pure.
 *
 * @param array  $event      pg_ad_conversion_event()
 * @param string $phone_hash pg_ad_conversion_tiktok_hash_phone() of the
 *                           order's phone, '' for none
 * @param string $page_url
 * @return array
 */
function pg_ad_conversion_tiktok_web_event(array $event, $phone_hash, $page_url)
{
    $person = (array) ($event['user'] ?? array());
    $user = array();

    if ($person) {
        if (($person['email_sha256'] ?? '') !== '') {
            $user['email'] = (string) $person['email_sha256'];
        }

        if ((string) $phone_hash !== '') {
            $user['phone'] = (string) $phone_hash;
        }

        // Never hashed: the browser id (_ttp), the click id, the address and
        // the browser's name.
        $plain = array('ttp' => 'ttp', 'ttclid' => 'ttclid', 'ip' => 'ip', 'user_agent' => 'user_agent');

        foreach ($plain as $ours => $theirs) {
            if (isset($person[$ours]) && is_string($person[$ours]) && ($person[$ours] !== '')) {
                $user[$theirs] = $person[$ours];
            }
        }
    }

    $contents = array();

    foreach ((array) ($event['contents'] ?? array()) as $line) {
        $contents[] = array(
            'content_id' => (string) ($line['id'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 0),
            'price' => (float) ($line['price'] ?? 0),
        );
    }

    // A Lead or a registration has no amount: no value and no currency.
    $properties = array();

    if ((string) ($event['currency'] ?? '') !== '') {
        $properties['value'] = (float) ($event['value'] ?? 0);
        $properties['currency'] = strtoupper((string) $event['currency']);
    }

    if ($contents) {
        $properties['contents'] = $contents;
        $properties['content_type'] = 'product';
    }

    if ((string) ($event['order_number'] ?? '') !== '') {
        $properties['order_id'] = (string) $event['order_number'];
    }

    $web = array(
        'event' => pg_ad_conversion_tiktok_event_name((string) ($event['event'] ?? '')),
        'event_time' => (int) ($event['time'] ?? 0),
        'event_id' => (string) ($event['event_id'] ?? ''),
        'user' => $user ? $user : new stdClass(),
        'properties' => $properties ? $properties : new stdClass(),
    );

    if ((string) $page_url !== '') {
        $web['page'] = array('url' => (string) $page_url);
    }

    return $web;
}

/**
 * The body posted to /event/track/: event_source web, the pixel code as
 * event_source_id, one event in data, the test event code only for a test.
 * The access token goes in the Access-Token header, never in the body or the
 * address. Pure.
 *
 * @param array  $web        pg_ad_conversion_tiktok_web_event()
 * @param string $pixel_code
 * @param string $test_code
 * @return array
 */
function pg_ad_conversion_tiktok_body(array $web, $pixel_code, $test_code = '')
{
    $body = array(
        'event_source' => 'web',
        'event_source_id' => (string) $pixel_code,
        'data' => array($web),
    );

    if ((string) $test_code !== '') {
        $body['test_event_code'] = (string) $test_code;
    }

    return $body;
}

/**
 * TikTok's answer as the queue reads a send (ok, status, error). TikTok
 * answers HTTP 200 with its own code: 0 is sent; any other code is an error
 * with its message and request id. 40100 (too many requests) and the 5xxxx
 * codes (TikTok's own fault) are answered as 429 so the row is tried again;
 * any other code is a refusal (400) and is not tried again. An HTTP error
 * keeps its status. Pure.
 *
 * @param array $http api_http_post()'s answer (ok, status, body, error)
 * @return array ok, status, error
 */
function pg_ad_conversion_tiktok_answer(array $http)
{
    $status = (int) ($http['status'] ?? 0);

    if ($status === 0) {
        return array('ok' => false, 'status' => 0, 'error' => (string) (($http['error'] ?? '') !== '' ? $http['error'] : 'TikTok did not answer.'));
    }

    $data = json_decode((string) ($http['body'] ?? ''), true);
    $data = is_array($data) ? $data : array();
    $has_code = array_key_exists('code', $data) && is_numeric($data['code']);
    $code = $has_code ? (int) $data['code'] : -1;

    if (($status >= 200) && ($status < 300) && ($code === 0)) {
        return array('ok' => true, 'status' => $status, 'error' => '');
    }

    $message = trim((string) ($data['message'] ?? ''));
    $message = ($message !== '') ? $message : 'HTTP ' . $status;

    if ($has_code) {
        $message .= ' (code ' . $code . ')';
    }

    if ((string) ($data['request_id'] ?? '') !== '') {
        $message .= ' request_id ' . substr((string) $data['request_id'], 0, 60);
    }

    if (($status >= 200) && ($status < 300)) {
        // A 200 that is not code 0: throttled or TikTok's own fault is tried
        // again; any other code is a refusal of the body or the keys.
        $status = (($code === 40100) || ($code >= 50000)) ? 429 : 400;
    }

    return array('ok' => false, 'status' => $status, 'error' => substr($message, 0, 500));
}

/**
 * The pixel code and the opened access token, '' each when not stored or the
 * token does not open.
 *
 * @return array pixel_code, token, test_event_code
 */
function pg_ad_conversion_tiktok_keys()
{
    $stored = pg_ad_conversion_tiktok_stored(pg_config_text(PG_AD_CONVERSION_TIKTOK_ROW));
    $open = ($stored['token'] !== '') ? pg_connect_unseal($stored['token']) : array();

    return array(
        'pixel_code' => $stored['pixel_code'],
        'token' => (string) ($open['token'] ?? ''),
        'test_event_code' => $stored['test_event_code'],
    );
}

/**
 * Whether TikTok is connected (a pixel code and a token are stored): the
 * registry's state reader.
 *
 * @return bool
 */
function pg_ad_conversion_tiktok_connected()
{
    $stored = pg_ad_conversion_tiktok_stored(pg_config_text(PG_AD_CONVERSION_TIKTOK_ROW));

    return $stored['connected'] && ($stored['pixel_code'] !== '') && ($stored['token'] !== '');
}

/**
 * One post to TikTok, the token in the Access-Token header.
 *
 * @param string $token
 * @param array  $body pg_ad_conversion_tiktok_body()
 * @return array ok, status, error
 */
function pg_ad_conversion_tiktok_post($token, array $body)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $http = api_http_post(PG_AD_CONVERSION_TIKTOK_URL, json_encode($body),
        array('Content-Type: application/json', 'Access-Token: ' . (string) $token), 10);

    return pg_ad_conversion_tiktok_answer(is_array($http) ? $http : array());
}

/**
 * Why an event cannot go to TikTok, '' when it can: keys missing, or an
 * event TikTok has no name for. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @param array $keys  pixel_code, token
 * @return string
 */
function pg_ad_conversion_tiktok_refusal(array $event, array $keys)
{
    if (($keys['pixel_code'] ?? '') === '' || ($keys['token'] ?? '') === '') {
        return 'The pixel code or the access token is missing.';
    }

    if (pg_ad_conversion_tiktok_event_name((string) ($event['event'] ?? '')) === '') {
        return 'TikTok has no event for this.';
    }

    return '';
}

/**
 * The order's phone hashed as TikTok asks, for an event that carries the
 * person: the purchase's event id is the order's reference code.
 *
 * @param array $event pg_ad_conversion_event()
 * @return string
 */
function pg_ad_conversion_tiktok_order_phone(array $event)
{
    if (((string) ($event['user']['phone_sha256'] ?? '') === '') || ((string) ($event['event_id'] ?? '') === '')) {
        return '';
    }

    $result = db_q("SELECT billing_phone_number FROM orders WHERE reference_code = ? LIMIT 1", array((string) $event['event_id']));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($row) ? pg_ad_conversion_tiktok_hash_phone($row['billing_phone_number'] ?? '') : '';
}

/**
 * The sender pg_ad_conversion_dispatch() calls for a due tiktok_events row.
 * A row TikTok could not take is answered as a refusal (400) without a
 * call, so it is given up.
 *
 * @param array $event    pg_ad_conversion_event()
 * @param array $settings pg_ad_conversion_settings_parse()
 * @return array ok, status, error
 */
function pg_ad_conversion_send_tiktok_events(array $event, array $settings)
{
    $keys = pg_ad_conversion_tiktok_keys();
    $refusal = pg_ad_conversion_tiktok_refusal($event, $keys);

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    $web = pg_ad_conversion_tiktok_web_event($event, pg_ad_conversion_tiktok_order_phone($event), pg_ad_conversion_meta_source_url());

    return pg_ad_conversion_tiktok_post($keys['token'], pg_ad_conversion_tiktok_body($web, $keys['pixel_code']));
}

/**
 * "Send a test event": the test purchase (pg_ad_conversion_meta_test_event())
 * with the stored test event code, so it shows in Events Manager's Test
 * events and is left out of the figures.
 *
 * @return array ok, status, error
 */
function pg_ad_conversion_tiktok_send_test()
{
    $keys = pg_ad_conversion_tiktok_keys();
    $event = pg_ad_conversion_meta_test_event(time(), defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD');
    $refusal = pg_ad_conversion_tiktok_refusal($event, $keys);

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    $web = pg_ad_conversion_tiktok_web_event($event, '', pg_ad_conversion_meta_source_url());

    return pg_ad_conversion_tiktok_post($keys['token'], pg_ad_conversion_tiktok_body($web, $keys['pixel_code'], $keys['test_event_code']));
}

/**
 * The page's write: save (and send a test), or disconnect. Errors and
 * notices go to the live form; ticking or clearing the notice-off box writes
 * an activity log line.
 *
 * @param string $action   tiktok_events_save, tiktok_events_remove
 * @param array  $post     $_POST
 * @param object $liveform
 * @return void
 */
function pg_ad_conversion_tiktok_post_page($action, array $post, $liveform)
{
    $name = 'TikTok Events API';
    $stored = pg_ad_conversion_tiktok_stored(pg_config_text(PG_AD_CONVERSION_TIKTOK_ROW));

    if ($action === 'tiktok_events_remove') {
        pg_config_text_save(PG_AD_CONVERSION_TIKTOK_ROW, '');
        $liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => $name)));
        log_activity(lang(array('string' => 'removed the {var:1} connection', 'vars' => $name)), $_SESSION['sessionusername']);

        return;
    }

    if ($action !== 'tiktok_events_save') {
        return;
    }

    $pixel = strtoupper(trim((string) ($post['pixel_code'] ?? '')));
    $token = trim((string) ($post['access_token'] ?? ''));
    $code = trim((string) ($post['test_event_code'] ?? ''));

    if (!pg_ad_conversion_tiktok_pixel_valid($pixel)) {
        $liveform->add_error(lang('The pixel code is the code Events Manager shows for the pixel, capital letters and digits.'));

        return;
    }

    if (($token === '') && ($stored['token'] === '')) {
        $liveform->add_error(lang('Paste the access token.'));

        return;
    }

    if (($token !== '') && !pg_ad_conversion_tiktok_token_valid($token)) {
        $liveform->add_error(lang('The access token is 20 to 512 letters and digits without spaces.'));

        return;
    }

    if (!pg_ad_conversion_tiktok_test_code_valid($code)) {
        $liveform->add_error(lang('The test event code is up to 40 letters and digits, as Events Manager shows it.'));

        return;
    }

    $sealed = '';

    if ($token !== '') {
        $sealed = pg_connect_seal(array('token' => $token));

        if ($sealed === '') {
            $liveform->add_error(lang('This site has no encryption key, so it cannot keep the key.'));

            return;
        }
    }

    $saved = pg_ad_conversion_tiktok_settings_merge($stored, array(
        'pixel_code' => $pixel,
        'test_event_code' => $code,
        'events' => $post['ad_events'] ?? array(),
        'person_notice_off' => !empty($post['person_notice_off']),
        'without_person' => $post['ad_without_person'] ?? array(),
        'lead_forms' => $post['ad_lead_forms'] ?? array(),
        'counter_sales' => !empty($post['counter_sales']),
    ), $sealed);

    pg_config_text_save(PG_AD_CONVERSION_TIKTOK_ROW, json_encode($saved));
    $liveform->add_notice(lang(array('string' => 'The {var:1} settings were saved.', 'vars' => $name)));
    log_activity(lang(array('string' => 'changed the {var:1} connection', 'vars' => $name)), $_SESSION['sessionusername']);

    if ($saved['person_notice_off'] !== $stored['person_notice_off']) {
        log_activity(lang(array(
            'string' => $saved['person_notice_off']
                ? 'turned on sending personal data to {var:1} while the cookie notice is off'
                : 'turned off sending personal data to {var:1} while the cookie notice is off',
            'vars' => $name,
        )), $_SESSION['sessionusername']);
    }

    if (empty($post['submit_try'])) {
        return;
    }

    $try = pg_ad_conversion_tiktok_send_test();

    if ($try['ok']) {
        $liveform->add_notice(lang('TikTok received the test event: it shows under Test events in Events Manager.'));
    } else {
        // TikTok's own words, escaped whole.
        $liveform->add_error(h((string) $try['error']));
    }
}

/**
 * The "Connection details" card of TikTok's page, and Disconnect in Status.
 *
 * @param array $page       the page's parts (connections.php)
 * @param bool  $may_manage who may open Visitors and privacy
 * @param bool  $can_seal
 * @return array the page's parts
 */
function pg_ad_conversion_tiktok_page(array $page, $may_manage, $can_seal)
{
    $stored = pg_ad_conversion_tiktok_stored(pg_config_text(PG_AD_CONVERSION_TIKTOK_ROW));
    $notice_on = function_exists('pg_consent_enabled') && pg_consent_enabled();
    $page['here'] = true;

    if ($may_manage && !in_array('details', $page['cards'], true)) {
        array_splice($page['cards'], max(0, (int) array_search('uses', $page['cards'], true)), 0, array('details'));
    }

    if ($may_manage) {
        $page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('Events are sent from the server to the TikTok pixel, after the visitor accepted marketing cookies.')) . '</p>
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="tiktok_events_save">
				<input type="hidden" name="connections_return" value="tiktok_events">
				<div>
					<label class="pg-field-label" for="tiktok_events_pixel_code">' . h(lang('Pixel code')) . '</label>
					<input type="text" class="pg-input font-monospace" id="tiktok_events_pixel_code" name="pixel_code" value="' . h($stored['pixel_code']) . '" maxlength="32" autocomplete="off" spellcheck="false">
					<div class="pg-field-help">' . h(lang('TikTok Events Manager › Web › your pixel: the code under its name.')) . '</div>
				</div>
				<div>
					<label class="pg-field-label" for="tiktok_events_access_token">' . h(lang('Access token')) . '</label>
					<input type="password" class="pg-input font-monospace" id="tiktok_events_access_token" name="access_token" value="" maxlength="512" autocomplete="new-password" spellcheck="false"
						placeholder="' . h(($stored['token'] !== '') ? lang('Stored. Leave empty to keep it.') : '') . '">
					<div class="pg-field-help">' . h(lang('TikTok Events Manager › your pixel › Settings › Events API › Generate access token. It is stored encrypted and never shown again.')) . '</div>
				</div>
				<div>
					<label class="pg-field-label" for="tiktok_events_test_event_code">' . h(lang('Test event code')) . '</label>
					<input type="text" class="pg-input font-monospace" id="tiktok_events_test_event_code" name="test_event_code" value="' . h($stored['test_event_code']) . '" maxlength="40" autocomplete="off" spellcheck="false">
					<div class="pg-field-help">' . h(lang('From Events Manager › Test events. Used only by Send a test event, never with real events.')) . '</div>
				</div>
				' . pg_ad_conversion_choices_fields('tiktok_events', pg_ad_conversion_tiktok_events(), $stored, $notice_on) . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
					<button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Send a test event')) . '</button>
				</div>
			</form>
		</section>';

        if ($stored['token'] !== '') {
            $page['status_extra'] = '
			<div class="mt-4">
				<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action" data-action="tiktok_events_remove" data-id="0"
					data-confirm="' . h(lang('TikTok Events API is disconnected: no event goes to TikTok, and the events waiting are given up.')) . '">
					<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
			</div>';
        }
    }

    $page['uses'] = '
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(lang('Purchases sent from the server')) . '</span>
						<span class="pg-text-meta d-block">' . h(lang('On this page')) . '</span>
					</span>
					<span class="connections-row-side">' . connections_state_pill(pg_ad_conversion_tiktok_connected() ? 'connected' : 'not_connected') . '</span>
				</div>';

    return $page;
}
