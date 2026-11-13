<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the start box "when a web address is called" (kind web): a
 * system outside the site (a form service, a shop, an automation tool)
 * posts to the flow's own address, flow_start.php?flow=<id>&key=<secret>,
 * and the flow starts in its owner's name with the body's fields as
 * {{trigger.<field>}}. The box declares the fields it reads; the secret is
 * one per flow, 32 random bytes kept sealed with the site's key in
 * config_text (ws_flow_web_<flow id>), shown and renewed only by those who
 * may edit the flow; a renewed secret ends the old address. Only a
 * published, live flow whose live version starts with this box starts.
 * The root file counts 60 calls a minute per address through the
 * firewall's rate store and reads at most 64 KB of body.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The fields a box may declare, and the length a text field is cut to.
define('WS_FLOW_WEB_FIELDS_MAX', 20);
define('WS_FLOW_WEB_TEXT_MAX', 2000);

// Calls a minute per address, and the largest body read.
define('WS_FLOW_WEB_PER_MINUTE', 60);
define('WS_FLOW_WEB_BODY_MAX', 65536);

/**
 * The box's settings as kept: the fields it reads of a body, each a key
 * (lower case letters, digits and _, not starting with a digit, at most 32)
 * and a type (text or number); a key once, at most WS_FLOW_WEB_FIELDS_MAX.
 * Pure.
 *
 * @param mixed $config
 * @return array fields
 */
function ws_flow_web_normalize($config)
{
    $config = is_array($config) ? $config : array();
    $fields = array();
    $seen = array();

    foreach ((is_array($config['fields'] ?? null) ? $config['fields'] : array()) as $field) {
        if (!is_array($field) || !is_string($field['key'] ?? null)) {
            continue;
        }

        $key = strtolower(trim($field['key']));

        if (!preg_match('/^[a-z_][a-z0-9_]{0,31}$/', $key) || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $fields[] = array('key' => $key, 'type' => (($field['type'] ?? '') === 'number') ? 'number' : 'text');

        if (count($fields) >= WS_FLOW_WEB_FIELDS_MAX) {
            break;
        }
    }

    return array('fields' => $fields);
}

/**
 * What the box gives the boxes after it: each field it declares, by its
 * own name. Pure.
 *
 * @param array $config
 * @return array[] key, type, label
 */
function ws_flow_web_outputs($config)
{
    $out = array();

    foreach (ws_flow_web_normalize($config)['fields'] as $field) {
        $out[] = array('key' => $field['key'], 'type' => $field['type'], 'label' => $field['key']);
    }

    return $out;
}

/**
 * The fields of a body: a JSON object's top-level values (a body that
 * reads as an object is JSON whatever its type says), else the form's
 * fields. A JSON body that is not an object is unreadable. Pure.
 *
 * @param string $content_type
 * @param string $raw  the body as sent
 * @param array  $form $_POST
 * @return array|null null when unreadable
 */
function ws_flow_web_body($content_type, $raw, $form)
{
    $raw = (string) $raw;
    $json = (stripos((string) $content_type, 'json') !== false) || (substr(ltrim($raw), 0, 1) === '{');

    if (!$json) {
        return is_array($form) ? $form : array();
    }

    if (trim($raw) === '') {
        return array();
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || (!empty($data) && (array_keys($data) === range(0, count($data) - 1))) || (substr(ltrim($raw), 0, 1) !== '{')) {
        return null;
    }

    return $data;
}

/**
 * The values of the declared fields from a body's fields: a number field a
 * finite number as sent, else empty; a text field its text cut to
 * WS_FLOW_WEB_TEXT_MAX characters (true is 1, false 0); a list, an object,
 * text that is not UTF-8 or a missing key is empty. Pure.
 *
 * @param array $config the box's settings
 * @param array $body   ws_flow_web_body()
 * @return array key => text
 */
function ws_flow_web_values($config, $body)
{
    $out = array();

    foreach (ws_flow_web_normalize($config)['fields'] as $field) {
        $value = $body[$field['key']] ?? null;

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            $out[$field['key']] = '';

            continue;
        }

        $value = (string) $value;

        if ($field['type'] === 'number') {
            $out[$field['key']] = (is_numeric($value) && is_finite((float) $value)) ? trim($value) : '';

            continue;
        }

        $out[$field['key']] = mb_check_encoding($value, 'UTF-8') ? mb_substr($value, 0, WS_FLOW_WEB_TEXT_MAX, 'UTF-8') : '';
    }

    return $out;
}

/**
 * The values a run started by the address kept, {{trigger.<field>}}. Pure.
 *
 * @param array $context ws_flow_run_context()
 * @return array key => text
 */
function ws_flow_web_context_values($context)
{
    $out = array();

    foreach ((is_array($context['web'] ?? null) ? $context['web'] : array()) as $key => $value) {
        if (is_string($key) && is_scalar($value)) {
            $out[$key] = (string) $value;
        }
    }

    return $out;
}

/**
 * A new secret: 32 random bytes as 64 hex characters.
 *
 * @return string
 */
function ws_flow_web_secret_new()
{
    return bin2hex(random_bytes(32));
}

/**
 * Whether a key has a secret's shape. Pure.
 *
 * @param mixed $secret
 * @return bool
 */
function ws_flow_web_secret_shape($secret)
{
    return is_string($secret) && (preg_match('/^[0-9a-f]{64}$/', $secret) === 1);
}

/**
 * Whether the key sent is the flow's secret, compared in constant time; an
 * empty kept secret or a key of another shape never matches. Pure.
 *
 * @param string $kept
 * @param mixed  $given
 * @return bool
 */
function ws_flow_web_secret_matches($kept, $given)
{
    return ws_flow_web_secret_shape($kept) && ws_flow_web_secret_shape($given) && hash_equals((string) $kept, (string) $given);
}

/**
 * The config_text row a flow's sealed secret is kept in. Pure.
 *
 * @param int $flow_id
 * @return string
 */
function ws_flow_web_secret_name($flow_id)
{
    return 'ws_flow_web_' . (int) $flow_id;
}

/**
 * The address that starts a flow. Pure.
 *
 * @param string $base   the site's software address, without the last /
 * @param int    $flow_id
 * @param string $secret
 * @return string
 */
function ws_flow_web_url_of($base, $flow_id, $secret)
{
    return (string) $base . '/flow_start.php?flow=' . (int) $flow_id . '&key=' . rawurlencode((string) $secret);
}

/**
 * The flow's secret, unsealed; '' when it has none or it cannot be read
 * (the site's key changed without resealing, or there is none).
 *
 * @param int $flow_id
 * @return string
 */
function ws_flow_web_secret($flow_id)
{
    $sealed = pg_config_text(ws_flow_web_secret_name($flow_id));

    if (($sealed === '') || (strpos($sealed, ':') === false) || !defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_decrypt')) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);
    $secret = (string) decode_ssl_keys($cipher, $iv);

    return ws_flow_web_secret_shape($secret) ? $secret : '';
}

/**
 * The flow's address for someone who may edit it, '' while it has none.
 *
 * @param array $flow
 * @return string
 */
function ws_flow_web_address($flow)
{
    $secret = ws_flow_web_secret((int) $flow['id']);

    return ($secret !== '') ? ws_flow_web_url_of(URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY, (int) $flow['id'], $secret) : '';
}

/**
 * Makes the flow a new secret, sealed with the site's key; the old address
 * stops at once. The caller has checked the person may edit the flow.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field, url
 */
function ws_flow_web_renew($viewer, $flow)
{
    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '') || !function_exists('openssl_encrypt') || !pg_schema_has('config_text')) {
        return ws_flow_refusal(lang('The site has no encryption key, so a flow cannot have a web address.')) + array('url' => '');
    }

    $secret = ws_flow_web_secret_new();
    list($cipher, $iv) = encrypt_string_with_iv($secret);

    if (!pg_config_text_save(ws_flow_web_secret_name((int) $flow['id']), $cipher . ':' . $iv)) {
        return ws_flow_refusal(lang('The web address could not be saved.')) + array('url' => '');
    }

    log_activity(lang(array('string' => 'workspace flow ({var:1}) got a new web address', 'vars' => $flow['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '',
        'url' => ws_flow_web_url_of(URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY, (int) $flow['id'], $secret));
}

/**
 * Starts a run of the flow from its address, as its owner: the flow live,
 * its live version started by this box, the owner on the staff, the open
 * runs and the starts of the hour within their limits. The caller has
 * matched the secret.
 *
 * @param array $flow
 * @param array $body ws_flow_web_body()
 * @return array ok, error, status, run_id
 */
function ws_flow_web_start($flow, $body)
{
    $fail = function ($error, $status = 422) {
        return array('ok' => false, 'error' => $error, 'status' => $status, 'run_id' => 0);
    };

    $version = (((string) $flow['status'] === 'live') && ((int) $flow['version_id'] > 0)) ? ws_flow_version($flow['version_id']) : null;
    $trigger = is_array($version) ? ws_flow_trigger_node(ws_flow_graph_of($version)) : null;

    if (!$trigger) {
        return $fail(lang('Only a published flow that is live can be run.'), 409);
    }

    if ((string) $trigger['kind'] !== 'web') {
        return $fail(lang('This flow is not started by a web address.'), 409);
    }

    $owner = ws_change_viewer_for_id($flow['owner_id']);

    if (!is_array($owner) || (ws_flow_builder_kind($owner) !== 'staff') || !ws_flow_builder($owner)) {
        return $fail(lang('The owner of this flow is no longer on the staff, so it cannot run.'), 409);
    }

    $flow_id = (int) $flow['id'];
    $open = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND status IN ('running', 'waiting')");
    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND started_at > '" . (time() - 3600) . "'");

    switch (ws_flow_start_refusal($open, $recent)) {
        case 'open_runs':
            return $fail(lang(array('string' => 'This flow has {var:1} runs open already; it starts again once some of them end.', 'vars' => WS_FLOW_MAX_OPEN_RUNS)), 429);

        case 'hourly':
            return $fail(lang(array('string' => 'This flow was started {var:1} times in the last hour; try again later.', 'vars' => WS_FLOW_STARTS_HOURLY)), 429);
    }

    $context = array(
        'trigger'    => 'web',
        'node'       => (string) $trigger['id'],
        'channel_id' => max(0, (int) $flow['channel_id']),
        'flow_name'  => (string) $flow['name'],
        'web'        => ws_flow_web_values($trigger['config'] ?? array(), $body),
    );
    $now = time();

    $written = db_q("INSERT INTO ws_flow_runs (flow_id, version_id, status, started_by, actor_id, start_key,
            subject_type, subject_id, context, depth, boxes_run, started_at, updated_at, finished_at)
        SELECT ?, ?, 'running', ?, ?, ?, '', 0, ?, 0, 0, ?, ?, 0
        FROM ws_flows WHERE id = ? AND status = 'live'",
        array($flow_id, (int) $version['id'], (int) $flow['owner_id'], (int) $flow['owner_id'], 'web:' . bin2hex(random_bytes(16)),
            (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now, $now, $flow_id));

    if (!$written || ((int) mysqli_affected_rows(db::$con) !== 1)) {
        return $fail(lang('The run could not be started.'), 409);
    }

    $run_id = (int) mysqli_insert_id(db::$con);

    db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
        VALUES ('" . $run_id . "', '" . e((string) $trigger['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
            '" . e(ws_flow_result_pack(lang('Started by its web address.'))) . "')");

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was started by its web address, run {var:2}', 'vars' => array($flow['name'], $run_id))), '');

    ws_flow_run_refresh($run_id);
    ws_flow_advance(WS_FLOW_BUDGET_TICK, $run_id);

    return array('ok' => true, 'error' => '', 'status' => 201, 'run_id' => $run_id);
}

/**
 * Sends the answer as JSON, dropping whatever was printed before, and ends
 * the request.
 *
 * @param int   $http
 * @param array $data
 */
function ws_flow_web_answer($http, $data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

/**
 * Counts a wrong key as a firewall offence.
 */
function ws_flow_web_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * Whether the calling address is over WS_FLOW_WEB_PER_MINUTE calls a
 * minute, counted per address (the /64 for IPv6) in the firewall's rate
 * store; counted here, as the firewall's own limits are off in monitor
 * mode. A refusal is logged and registered once a minute.
 *
 * @return bool
 */
function ws_flow_web_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || !waf_ip_is_counted($ip) || !waf_rate_exceeded(waf_ip_subject($ip), 'flow_web', WS_FLOW_WEB_PER_MINUTE, 60)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-flow-web', 'rate', 5, 'flow_start.php', WS_FLOW_WEB_PER_MINUTE . '/1min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-flow-web'));
    }

    if (function_exists('waf_register_offence') && function_exists('waf_mode')) {
        waf_register_offence($ip, 5, (waf_mode() === 'block'), true);
    }

    return true;
}

/**
 * Answers one call: flow_start.php?flow=<id>&key=<secret>.
 *
 * 405 not POST; 429 over the allowance or the flow's own limits; 503 the
 * flows not ready; 401 no such flow, no address or a wrong key (all alike,
 * an offence for the firewall); 413 a body over WS_FLOW_WEB_BODY_MAX; 400
 * a body that cannot be read; 409 a flow that is not live, not started by
 * this box or whose owner left; 201 with the run started.
 */
function ws_flow_web_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

    if ($method !== 'POST') {
        header('Allow: POST');
        ws_flow_web_answer(405, array('ok' => false, 'error' => 'method_not_allowed'));
    }

    if (ws_flow_web_throttled()) {
        header('Retry-After: 60');
        ws_flow_web_answer(429, array('ok' => false, 'error' => 'rate_limited'));
    }

    // Flows' own gate, the Workspace on or off (pg_flows_enabled()).
    if (!pg_flows_enabled() || !ws_flows_ready() || !pg_schema_has('config_text')) {
        ws_flow_web_answer(503, array('ok' => false, 'error' => 'not_ready'));
    }

    $flow_id = (isset($_GET['flow']) && is_string($_GET['flow']) && ctype_digit($_GET['flow'])) ? (int) $_GET['flow'] : 0;
    $given = $_GET['key'] ?? ($_SERVER['HTTP_X_FLOW_KEY'] ?? null);
    $flow = ($flow_id > 0) ? ws_flow($flow_id) : null;

    if (!is_array($flow) || ((string) $flow['status'] === 'binned') || !ws_flow_web_secret_matches(ws_flow_web_secret($flow_id), $given)) {
        ws_flow_web_offence();
        ws_flow_web_answer(401, array('ok' => false, 'error' => 'wrong_key'));
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $raw = ($length > WS_FLOW_WEB_BODY_MAX) ? false : @file_get_contents('php://input', false, null, 0, WS_FLOW_WEB_BODY_MAX + 1);

    if (($raw === false) || (strlen($raw) > WS_FLOW_WEB_BODY_MAX)) {
        ws_flow_web_answer(413, array('ok' => false, 'error' => 'too_large'));
    }

    $body = ws_flow_web_body((string) ($_SERVER['CONTENT_TYPE'] ?? ''), $raw, $_POST);

    if ($body === null) {
        ws_flow_web_answer(400, array('ok' => false, 'error' => 'unreadable'));
    }

    $started = ws_flow_web_start($flow, $body);

    if (!$started['ok']) {
        ws_flow_web_answer((int) $started['status'], array('ok' => false, 'error' => (string) $started['error']));
    }

    ws_flow_web_answer(201, array('ok' => true, 'run_id' => (int) $started['run_id']));
}
