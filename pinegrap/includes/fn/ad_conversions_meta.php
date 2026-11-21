<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Meta Conversions API: the sender of ad_conversion_events rows for the
 * service meta_capi, the body it posts, how Meta's answers are read, the
 * settings and sealed keys typed on connections.php?service=meta_capi, and
 * that page's "Connection details" card.
 *
 * The settings share config_text row ad_conversions_meta_capi with what
 * ad_conversions.php reads (connected, events, person_notice_off,
 * without_person) and add pixel_id, token (the access token, sealed with
 * pg_connect_seal()) and test_event_code. The test event code is sent only
 * with "Send a test event", never with a queued event: Meta asks for it to be
 * left out of real events.
 *
 * The request's facts (address, version, body, limits, answers) are read from
 * Meta's own documents and kept in docs/memory/api/meta-capi-facts.md.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The Graph API version the events are posted to.
define('PG_AD_CONVERSION_META_VERSION', 'v27.0');

// Meta refuses the whole request when an event is older than 7 days; a row
// older than this is given up without a call.
define('PG_AD_CONVERSION_META_MAX_AGE', 7 * 86400 - 600);

// The config_text row of the service.
define('PG_AD_CONVERSION_META_ROW', 'ad_conversions_meta_capi');

/**
 * The events the page can tick, our name => Meta's standard event name.
 * Pure.
 *
 * @return array
 */
function pg_ad_conversion_meta_events()
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
 * Meta's name of one of our events, '' for one Meta has no event for (a
 * refund). Pure.
 *
 * @param string $event
 * @return string
 */
function pg_ad_conversion_meta_event_name($event)
{
    $names = pg_ad_conversion_meta_events();

    return isset($names[$event]) ? $names[$event] : '';
}

/**
 * Whether a pixel (dataset) id has the shape Meta gives: digits only. Pure.
 *
 * @param mixed $id
 * @return bool
 */
function pg_ad_conversion_meta_pixel_valid($id)
{
    return is_string($id) && (bool) preg_match('/^\d{5,20}$/D', $id);
}

/**
 * Whether an access token has a token's shape: 20 to 512 letters, digits,
 * dashes and underscores. Pure.
 *
 * @param mixed $token
 * @return bool
 */
function pg_ad_conversion_meta_token_valid($token)
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
function pg_ad_conversion_meta_test_code_valid($code)
{
    return is_string($code) && (bool) preg_match('/^[A-Za-z0-9]{0,40}$/D', $code);
}

/**
 * The stored settings row as an array, every key present. Pure.
 *
 * @param mixed $raw the stored JSON
 * @return array the keys of pg_ad_conversion_settings_parse() and pixel_id,
 *               token (sealed), test_event_code
 */
function pg_ad_conversion_meta_stored($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : array();

    return pg_ad_conversion_settings_parse($raw) + array(
        'pixel_id' => (string) ($data['pixel_id'] ?? ''),
        'token' => (string) ($data['token'] ?? ''),
        'test_event_code' => (string) ($data['test_event_code'] ?? ''),
    );
}

/**
 * The settings the page's form writes, from what is stored and what was
 * typed. An empty token field keeps the stored token; the choices are taken
 * as posted (pg_ad_conversion_choices_merge()). Pure.
 *
 * @param array  $stored pg_ad_conversion_meta_stored()
 * @param array  $post   pixel_id, test_event_code and the choices of
 *                       pg_ad_conversion_choices_merge()
 * @param string $sealed the newly sealed token, '' to keep the stored one
 * @return array what is saved as JSON
 */
function pg_ad_conversion_meta_settings_merge(array $stored, array $post, $sealed)
{
    $token = ((string) $sealed !== '') ? (string) $sealed : (string) ($stored['token'] ?? '');
    $pixel = trim((string) ($post['pixel_id'] ?? ''));

    return array(
        'connected' => ($pixel !== '') && ($token !== ''),
        'pixel_id' => $pixel,
        'token' => $token,
        'test_event_code' => trim((string) ($post['test_event_code'] ?? '')),
        // Meta takes no counter sale (pg_ad_conversion_counter_sale_offered()).
        'counter_sales' => false,
    ) + pg_ad_conversion_choices_merge(array_keys(pg_ad_conversion_meta_events()), $post);
}

/**
 * The address of the site's pages, the event_source_url Meta asks of a
 * website event.
 *
 * @return string
 */
function pg_ad_conversion_meta_source_url()
{
    if (!defined('URL_SCHEME') || !defined('HOSTNAME_SETTING')) {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/');
}

/**
 * One event as Meta's server event (a member of the body's data array):
 * the hashed e-mail and phone, the browser and click ids, the address and
 * the browser's name only when the event carries the person; amounts as
 * numbers; action_source website. Pure.
 *
 * @param array  $event      pg_ad_conversion_event()
 * @param string $source_url
 * @return array
 */
function pg_ad_conversion_meta_server_event(array $event, $source_url)
{
    $person = (array) ($event['user'] ?? array());
    $user = array();

    if (($person['email_sha256'] ?? '') !== '') {
        $user['em'] = array((string) $person['email_sha256']);
    }

    if (($person['phone_sha256'] ?? '') !== '') {
        $user['ph'] = array((string) $person['phone_sha256']);
    }

    // Never hashed, as Meta asks: the browser id, the click id, the address
    // and the browser's name.
    $plain = array('fbp' => 'fbp', 'fbc' => 'fbc', 'ip' => 'client_ip_address', 'user_agent' => 'client_user_agent');

    foreach ($plain as $ours => $theirs) {
        if (isset($person[$ours]) && is_string($person[$ours]) && ($person[$ours] !== '')) {
            $user[$theirs] = $person[$ours];
        }
    }

    $contents = array();

    foreach ((array) ($event['contents'] ?? array()) as $line) {
        $contents[] = array(
            'id' => (string) ($line['id'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 0),
            'item_price' => (float) ($line['price'] ?? 0),
        );
    }

    // A Lead or a registration has no amount: no value and no currency.
    $custom = array();

    if ((string) ($event['currency'] ?? '') !== '') {
        $custom['value'] = (float) ($event['value'] ?? 0);
        $custom['currency'] = strtoupper((string) $event['currency']);
    }

    if ($contents) {
        $custom['contents'] = $contents;
        $custom['content_type'] = 'product';
    }

    if ((string) ($event['order_number'] ?? '') !== '') {
        $custom['order_id'] = (string) $event['order_number'];
    }

    $server = array(
        'event_name' => pg_ad_conversion_meta_event_name((string) ($event['event'] ?? '')),
        'event_time' => (int) ($event['time'] ?? 0),
        'event_id' => (string) ($event['event_id'] ?? ''),
        'action_source' => 'website',
        'user_data' => $user ? $user : new stdClass(),
    );

    if ($custom) {
        $server['custom_data'] = $custom;
    }

    if ((string) $source_url !== '') {
        $server['event_source_url'] = (string) $source_url;
    }

    return $server;
}

/**
 * The body posted to /<pixel>/events: one event in data, the access token
 * as a body parameter (kept out of the address and so out of any log), the
 * test event code only for a test. Pure.
 *
 * @param array  $server    pg_ad_conversion_meta_server_event()
 * @param string $token
 * @param string $test_code
 * @return array
 */
function pg_ad_conversion_meta_body(array $server, $token, $test_code = '')
{
    $body = array('data' => array($server), 'access_token' => (string) $token);

    if ((string) $test_code !== '') {
        $body['test_event_code'] = (string) $test_code;
    }

    return $body;
}

/**
 * The address events are posted to. Pure.
 *
 * @param string $pixel_id
 * @return string
 */
function pg_ad_conversion_meta_url($pixel_id)
{
    return 'https://graph.facebook.com/' . PG_AD_CONVERSION_META_VERSION . '/' . rawurlencode((string) $pixel_id) . '/events';
}

/**
 * Meta's answer as the queue reads a send (ok, status, error). A 2xx with
 * events_received is sent. A Graph error gives its message, code and trace
 * id; the codes Meta names as temporary (down or throttled) are answered as
 * 429, so the row is tried again, though Meta sends them with a 400; any
 * other 400 is a refusal and is not tried again. Pure.
 *
 * @param array $http api_http_post()'s answer (ok, status, body, error)
 * @return array ok, status, error
 */
function pg_ad_conversion_meta_answer(array $http)
{
    $status = (int) ($http['status'] ?? 0);

    if ($status === 0) {
        return array('ok' => false, 'status' => 0, 'error' => (string) (($http['error'] ?? '') !== '' ? $http['error'] : 'Meta did not answer.'));
    }

    $data = json_decode((string) ($http['body'] ?? ''), true);
    $data = is_array($data) ? $data : array();

    if (($status >= 200) && ($status < 300)) {
        if ((int) ($data['events_received'] ?? 0) >= 1) {
            return array('ok' => true, 'status' => $status, 'error' => '');
        }

        return array('ok' => false, 'status' => 400, 'error' => 'Meta received no event.');
    }

    $error = (isset($data['error']) && is_array($data['error'])) ? $data['error'] : array();
    $code = (int) ($error['code'] ?? 0);
    $message = trim((string) ($error['error_user_msg'] ?? ($error['message'] ?? '')));
    $message = ($message !== '') ? $message : 'HTTP ' . $status;

    if ($code > 0) {
        $message .= ' (code ' . $code . (isset($error['error_subcode']) ? ', ' . (int) $error['error_subcode'] : '') . ')';
    }

    if ((string) ($error['fbtrace_id'] ?? '') !== '') {
        $message .= ' fbtrace_id ' . substr((string) $error['fbtrace_id'], 0, 60);
    }

    // Down or throttled: 1 and 2 (downtime), 4, 17, 32, 341, 613 (rate
    // limits), as Meta's error handling guide lists them.
    if (in_array($code, array(1, 2, 4, 17, 32, 341, 613), true)) {
        $status = 429;
    }

    return array('ok' => false, 'status' => $status, 'error' => substr($message, 0, 500));
}

/**
 * The pixel id and the opened access token, '' each when not stored or the
 * token does not open.
 *
 * @return array pixel_id, token, test_event_code
 */
function pg_ad_conversion_meta_keys()
{
    $stored = pg_ad_conversion_meta_stored(pg_config_text(PG_AD_CONVERSION_META_ROW));
    $open = ($stored['token'] !== '') ? pg_connect_unseal($stored['token']) : array();

    return array(
        'pixel_id' => $stored['pixel_id'],
        'token' => (string) ($open['token'] ?? ''),
        'test_event_code' => $stored['test_event_code'],
    );
}

/**
 * Whether Meta is connected (a pixel id and a token are stored): the
 * registry's state reader.
 *
 * @return bool
 */
function pg_ad_conversion_meta_connected()
{
    $stored = pg_ad_conversion_meta_stored(pg_config_text(PG_AD_CONVERSION_META_ROW));

    return $stored['connected'] && ($stored['pixel_id'] !== '') && ($stored['token'] !== '');
}

/**
 * One post to Meta.
 *
 * @param string $pixel_id
 * @param array  $body pg_ad_conversion_meta_body()
 * @return array ok, status, error
 */
function pg_ad_conversion_meta_post($pixel_id, array $body)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $http = api_http_post(pg_ad_conversion_meta_url($pixel_id), json_encode($body), array('Content-Type: application/json'), 10);

    return pg_ad_conversion_meta_answer(is_array($http) ? $http : array());
}

/**
 * Why an event cannot go to Meta, '' when it can: keys missing, an event
 * Meta has no name for, an event older than Meta takes. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @param array $keys  pixel_id, token
 * @param int   $now
 * @return string
 */
function pg_ad_conversion_meta_refusal(array $event, array $keys, $now)
{
    if (($keys['pixel_id'] ?? '') === '' || ($keys['token'] ?? '') === '') {
        return 'The pixel ID or the access token is missing.';
    }

    if (pg_ad_conversion_meta_event_name((string) ($event['event'] ?? '')) === '') {
        return 'Meta has no event for this.';
    }

    if (((int) ($event['time'] ?? 0) <= 0) || (((int) $now - (int) $event['time']) > PG_AD_CONVERSION_META_MAX_AGE)) {
        return 'Meta takes events up to 7 days old.';
    }

    return '';
}

/**
 * The sender pg_ad_conversion_dispatch() calls for a due meta_capi row. A
 * row Meta could not take is answered as a refusal (400) without a call, so
 * it is given up.
 *
 * @param array $event    pg_ad_conversion_event()
 * @param array $settings pg_ad_conversion_settings_parse()
 * @return array ok, status, error
 */
function pg_ad_conversion_send_meta_capi(array $event, array $settings)
{
    $keys = pg_ad_conversion_meta_keys();
    $refusal = pg_ad_conversion_meta_refusal($event, $keys, time());

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    $server = pg_ad_conversion_meta_server_event($event, pg_ad_conversion_meta_source_url());

    return pg_ad_conversion_meta_post($keys['pixel_id'], pg_ad_conversion_meta_body($server, $keys['token']));
}

/**
 * The test event "Send a test event" posts: a purchase of 1.00 in the
 * site's currency without the person, with an id of its own. Pure.
 *
 * @param int    $now
 * @param string $currency
 * @return array as pg_ad_conversion_event()
 */
function pg_ad_conversion_meta_test_event($now, $currency)
{
    return array(
        'event' => 'purchase',
        'event_id' => 'pg-test-' . (int) $now,
        'time' => (int) $now,
        'value' => '1.00',
        'currency' => (string) $currency,
        'order_number' => '',
        'contents' => array(),
        'user' => array(),
    );
}

/**
 * "Send a test event": the test purchase with the stored test event code,
 * so it shows in Events Manager's Test events and is left out of the
 * figures.
 *
 * @return array ok, status, error
 */
function pg_ad_conversion_meta_send_test()
{
    $keys = pg_ad_conversion_meta_keys();
    $now = time();
    $event = pg_ad_conversion_meta_test_event($now, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD');
    $refusal = pg_ad_conversion_meta_refusal($event, $keys, $now);

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    $server = pg_ad_conversion_meta_server_event($event, pg_ad_conversion_meta_source_url());

    return pg_ad_conversion_meta_post($keys['pixel_id'], pg_ad_conversion_meta_body($server, $keys['token'], $keys['test_event_code']));
}

/**
 * The page's write: save (and send a test), send a test, or disconnect.
 * Errors and notices go to the live form; ticking or clearing the notice-off
 * box writes an activity log line.
 *
 * @param string $action   meta_capi_save, meta_capi_test, meta_capi_remove
 * @param array  $post     $_POST
 * @param object $liveform
 * @return void
 */
function pg_ad_conversion_meta_post_page($action, array $post, $liveform)
{
    $name = 'Meta Conversions API';
    $stored = pg_ad_conversion_meta_stored(pg_config_text(PG_AD_CONVERSION_META_ROW));

    if ($action === 'meta_capi_remove') {
        pg_config_text_save(PG_AD_CONVERSION_META_ROW, '');
        $liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => $name)));
        log_activity(lang(array('string' => 'removed the {var:1} connection', 'vars' => $name)), $_SESSION['sessionusername']);

        return;
    }

    if ($action === 'meta_capi_save') {
        $pixel = trim((string) ($post['pixel_id'] ?? ''));
        $token = trim((string) ($post['access_token'] ?? ''));
        $code = trim((string) ($post['test_event_code'] ?? ''));

        if (!pg_ad_conversion_meta_pixel_valid($pixel)) {
            $liveform->add_error(lang('The pixel ID is the dataset\'s number from Events Manager, digits only.'));

            return;
        }

        if (($token === '') && ($stored['token'] === '')) {
            $liveform->add_error(lang('Paste the access token.'));

            return;
        }

        if (($token !== '') && !pg_ad_conversion_meta_token_valid($token)) {
            $liveform->add_error(lang('The access token is 20 to 512 letters and digits without spaces.'));

            return;
        }

        if (!pg_ad_conversion_meta_test_code_valid($code)) {
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

        $saved = pg_ad_conversion_meta_settings_merge($stored, array(
            'pixel_id' => $pixel,
            'test_event_code' => $code,
            'events' => $post['ad_events'] ?? array(),
            'person_notice_off' => !empty($post['person_notice_off']),
            'without_person' => $post['ad_without_person'] ?? array(),
            'lead_forms' => $post['ad_lead_forms'] ?? array(),
        ), $sealed);

        pg_config_text_save(PG_AD_CONVERSION_META_ROW, json_encode($saved));
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
    }

    $try = pg_ad_conversion_meta_send_test();

    if ($try['ok']) {
        $liveform->add_notice(lang('Meta received the test event: it shows under Test events in Events Manager.'));
    } else {
        // Meta's own words, escaped whole.
        $liveform->add_error(h((string) $try['error']));
    }
}

/**
 * The "Connection details" card of Meta's page, and Disconnect in Status.
 *
 * @param array $page     the page's parts (connections.php)
 * @param bool  $may_manage who may open Visitors and privacy
 * @param bool  $can_seal
 * @return array the page's parts
 */
function pg_ad_conversion_meta_page(array $page, $may_manage, $can_seal)
{
    $stored = pg_ad_conversion_meta_stored(pg_config_text(PG_AD_CONVERSION_META_ROW));
    $notice_on = function_exists('pg_consent_enabled') && pg_consent_enabled();
    $page['here'] = true;

    if ($may_manage && !in_array('details', $page['cards'], true)) {
        array_splice($page['cards'], max(0, (int) array_search('uses', $page['cards'], true)), 0, array('details'));
    }

    if ($may_manage) {
        $page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('Events are sent from the server to the Meta pixel, after the visitor accepted marketing cookies.')) . '</p>
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="meta_capi_save">
				<input type="hidden" name="connections_return" value="meta_capi">
				<div>
					<label class="pg-field-label" for="meta_capi_pixel_id">' . h(lang('Pixel ID')) . '</label>
					<input type="text" class="pg-input font-monospace" id="meta_capi_pixel_id" name="pixel_id" value="' . h($stored['pixel_id']) . '" maxlength="20" inputmode="numeric" autocomplete="off" spellcheck="false">
					<div class="pg-field-help">' . h(lang('Events Manager › Data sources › your pixel: the dataset ID.')) . '</div>
				</div>
				<div>
					<label class="pg-field-label" for="meta_capi_access_token">' . h(lang('Access token')) . '</label>
					<input type="password" class="pg-input font-monospace" id="meta_capi_access_token" name="access_token" value="" maxlength="512" autocomplete="new-password" spellcheck="false"
						placeholder="' . h(($stored['token'] !== '') ? lang('Stored. Leave empty to keep it.') : '') . '">
					<div class="pg-field-help">' . h(lang('Events Manager › your pixel › Settings › Conversions API › Generate access token. It is stored encrypted and never shown again.')) . '</div>
				</div>
				<div>
					<label class="pg-field-label" for="meta_capi_test_event_code">' . h(lang('Test event code')) . '</label>
					<input type="text" class="pg-input font-monospace" id="meta_capi_test_event_code" name="test_event_code" value="' . h($stored['test_event_code']) . '" maxlength="40" autocomplete="off" spellcheck="false">
					<div class="pg-field-help">' . h(lang('From Events Manager › Test events. Used only by Send a test event, never with real events.')) . '</div>
				</div>
				' . pg_ad_conversion_choices_fields('meta_capi', pg_ad_conversion_meta_events(), $stored, $notice_on) . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
					<button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Send a test event')) . '</button>
				</div>
			</form>
		</section>';

        if ($stored['token'] !== '') {
            $page['status_extra'] = '
			<div class="mt-4">
				<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action" data-action="meta_capi_remove" data-id="0"
					data-confirm="' . h(lang('Meta Conversions API is disconnected: no event goes to Meta, and the events waiting are given up.')) . '">
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
					<span class="connections-row-side">' . connections_state_pill(pg_ad_conversion_meta_connected() ? 'connected' : 'not_connected') . '</span>
				</div>';

    return $page;
}
