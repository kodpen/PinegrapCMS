<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The İYS (İleti Yönetim Sistemi) client: the brand list, adding one consent
 * and reading the changes recipients made through İYS, and the daily job
 * that pushes the e-mail marketing answers of consent_log to İYS and pulls
 * the refusals back.
 *
 * The account is the connect_apps row "iys", as the SMS providers keep
 * theirs: the API user is client_id, its password sealed with the site key;
 * options hold the İYS code, the brand code, the environment ("test" for the
 * sandbox), "live" (false or missing is a dry run), the e-mail switch and the
 * job's two cursors (push_id, pull_after). The account is read only here.
 *
 * A dry run calls nothing: it logs what a live run would push. It is the
 * default until an account has been tried against İYS.
 *
 * Request builders and response readers are plain functions of their
 * arguments, so the tests check them without an İYS account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The İYS account, or null when none is set up.
 *
 * @return array|null user, password, iys_code, brand_code, env, live, email,
 *                    enabled, push_id, pull_after
 */
function pg_iys_app()
{
    $app = function_exists('pg_connect_app') ? pg_connect_app('iys') : null;

    if (!is_array($app)) {
        return null;
    }

    $options = $app['options'];
    $text = function ($key) use ($options) {
        return (isset($options[$key]) && is_scalar($options[$key])) ? trim((string) $options[$key]) : '';
    };

    return array(
        'user'       => (string) $app['client_id'],
        'password'   => (string) $app['client_secret'],
        'iys_code'   => $text('iys_code'),
        'brand_code' => $text('brand_code'),
        'env'        => ($text('env') === 'test') ? 'test' : 'live',
        'live'       => !empty($options['live']),
        'email'      => !empty($options['email']),
        'enabled'    => (bool) $app['enabled'],
        'push_id'    => isset($options['push_id']) ? max(0, (int) $options['push_id']) : 0,
        'pull_after' => $text('pull_after'),
    );
}

/**
 * Whether the account has what a call needs.
 *
 * @param array|null $app pg_iys_app()
 * @return bool
 */
function pg_iys_app_complete($app)
{
    return is_array($app) && $app['enabled'] && ($app['user'] !== '') && ($app['password'] !== '')
        && (preg_match('/^[0-9]{1,16}$/', $app['iys_code']) === 1)
        && (preg_match('/^[0-9]{1,16}$/', $app['brand_code']) === 1);
}

/**
 * Whether commercial e-mail checks İYS: the account is on with its e-mail
 * switch, and the contacts carry the status column. Read once per request.
 *
 * @return bool
 */
function pg_iys_email_on()
{
    static $on = null;

    if ($on === null) {
        $app = pg_iys_app();
        $on = is_array($app) && $app['enabled'] && $app['email']
            && function_exists('pg_schema_has') && pg_schema_has('contacts', 'iys_email_status');
    }

    return $on;
}

/**
 * Whether İYS holds a refusal for an address on the e-mail channel, as the
 * last push or pull recorded it. False while İYS is off for e-mail.
 *
 * @param string $email
 * @return bool
 */
function pg_iys_email_refused($email)
{
    return pg_iys_email_on() && (bool) db_value(
        "SELECT id
        FROM contacts
        WHERE
            (email_address = '" . e((string) $email) . "')
            AND (iys_email_status = 'refused')
        LIMIT 1");
}

/**
 * Where the API is.
 *
 * @param array $app pg_iys_app()
 * @return string
 */
function pg_iys_host($app)
{
    return ($app['env'] === 'test') ? 'https://api.sandbox.iys.org.tr' : 'https://api.iys.org.tr';
}

/**
 * The request for an access token.
 *
 * @param array $app pg_iys_app()
 * @return array url, headers, body
 */
function pg_iys_token_request($app)
{
    return array(
        'url'     => pg_iys_host($app) . '/oauth2/token',
        'headers' => array('Content-Type: application/json', 'Accept: application/json'),
        'body'    => (string) json_encode(array(
            'username'   => (string) $app['user'],
            'password'   => (string) $app['password'],
            'grant_type' => 'password',
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * The headers of a call made with a token.
 *
 * @param string $token
 * @param bool   $has_body
 * @return string[]
 */
function pg_iys_headers($token, $has_body)
{
    $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');

    if ($has_body) {
        $headers[] = 'Content-Type: application/json';
    }

    return $headers;
}

/**
 * The path of the brand's consents.
 *
 * @param array $app pg_iys_app()
 * @return string
 */
function pg_iys_consents_url($app)
{
    return pg_iys_host($app) . '/sps/' . rawurlencode($app['iys_code']) . '/brands/' . rawurlencode($app['brand_code']) . '/consents';
}

/**
 * The request that lists the brands of the İYS code.
 *
 * @param array  $app   pg_iys_app()
 * @param string $token
 * @return array url, headers
 */
function pg_iys_brands_request($app, $token)
{
    return array(
        'url'     => pg_iys_host($app) . '/sps/' . rawurlencode($app['iys_code']) . '/brands',
        'headers' => pg_iys_headers($token, false),
    );
}

/**
 * The request that adds one consent.
 *
 * @param array  $app     pg_iys_app()
 * @param string $token
 * @param array  $consent pg_iys_consent_body()
 * @return array url, headers, body
 */
function pg_iys_consent_request($app, $token, $consent)
{
    return array(
        'url'     => pg_iys_consents_url($app),
        'headers' => pg_iys_headers($token, true),
        'body'    => (string) json_encode($consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * The request for the changes recipients made after a cursor.
 *
 * @param array  $app   pg_iys_app()
 * @param string $token
 * @param string $after the cursor of the last page read, '' for the start
 * @param int    $limit at most 999
 * @return array url, headers
 */
function pg_iys_changes_request($app, $token, $after, $limit)
{
    $query = array('limit' => max(1, min(999, (int) $limit)));

    if ((string) $after !== '') {
        $query['after'] = (string) $after;
    }

    return array(
        'url'     => pg_iys_consents_url($app) . '/changes?' . http_build_query($query),
        'headers' => pg_iys_headers($token, false),
    );
}

/**
 * İYS keeps dates as Turkish local time.
 *
 * @param int $timestamp
 * @return string Y-m-d H:i:s
 */
function pg_iys_date($timestamp)
{
    $date = new DateTime('@' . (int) $timestamp);
    $date->setTimezone(new DateTimeZone('Europe/Istanbul'));

    return $date->format('Y-m-d H:i:s');
}

/**
 * The timestamp of an İYS date, 0 when it cannot be read.
 *
 * @param mixed $date
 * @return int
 */
function pg_iys_timestamp($date)
{
    if (!is_string($date)) {
        return 0;
    }

    $read = DateTime::createFromFormat('Y-m-d H:i:s', substr($date, 0, 19), new DateTimeZone('Europe/Istanbul'));

    return $read ? max(0, $read->getTimestamp()) : 0;
}

/**
 * The consent İYS is sent for one consent_log row: an e-mail answer given on
 * the web, by a person (BIREYSEL). A refusal is RET, a grant ONAY.
 *
 * @param string $email
 * @param string $status     consent_log status: granted or refused
 * @param int    $created_at when the answer was given
 * @return array|null null when the row is not one İYS takes
 */
function pg_iys_consent_body($email, $status, $created_at)
{
    $email = trim((string) $email);

    if (($email === '') || (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        return null;
    }

    if (!in_array($status, array('granted', 'refused'), true) || ((int) $created_at <= 0)) {
        return null;
    }

    return array(
        'consentDate'   => pg_iys_date($created_at),
        'source'        => 'HS_WEB',
        'recipient'     => $email,
        'recipientType' => 'BIREYSEL',
        'status'        => ($status === 'granted') ? 'ONAY' : 'RET',
        'type'          => 'EPOSTA',
    );
}

/**
 * The first error İYS gave in a body, as "code: message", cut short.
 *
 * @param mixed $data the decoded body
 * @return string
 */
function pg_iys_error_text($data)
{
    $text = '';

    if (is_array($data) && isset($data['errors'][0]) && is_array($data['errors'][0])) {
        $error = $data['errors'][0];
        $code = (isset($error['code']) && is_scalar($error['code'])) ? (string) $error['code'] : '';
        $message = (isset($error['message']) && is_scalar($error['message'])) ? (string) $error['message'] : '';
        $text = trim($code . (($code !== '' && $message !== '') ? ': ' : '') . $message);
    } elseif (is_array($data) && isset($data['message']) && is_scalar($data['message'])) {
        $text = (string) $data['message'];
    }

    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

    return function_exists('mb_substr') ? mb_substr($text, 0, 200, 'UTF-8') : substr($text, 0, 200);
}

/**
 * Reads an answer.
 *
 * outcome: ok; rejected (İYS read the request and refused this one, the
 * next may pass); stop (credentials, a limit, an outage, no answer: nothing
 * after it would pass either, so the job ends the run and tries the same
 * rows again next time).
 *
 * @param array $response pg_sms_http()
 * @return array outcome, data, error
 */
function pg_iys_answer($response)
{
    $status = (int) $response['status'];
    $data = ((string) $response['body'] !== '') ? json_decode((string) $response['body'], true) : null;

    if ($status === 0) {
        return array('outcome' => 'stop', 'data' => null, 'error' => (string) $response['error']);
    }

    if (($status >= 200) && ($status < 300)) {
        return array('outcome' => 'ok', 'data' => $data, 'error' => '');
    }

    $error = pg_iys_error_text($data);

    if ($error === '') {
        $error = 'HTTP ' . $status;
    }

    if (in_array($status, array(401, 403, 408, 429), true) || ($status >= 500) || ($status < 400)) {
        return array('outcome' => 'stop', 'data' => $data, 'error' => $error);
    }

    return array('outcome' => 'rejected', 'data' => $data, 'error' => $error);
}

/**
 * The access token in a token answer, '' when there is none.
 *
 * @param array $answer pg_iys_answer()
 * @return string
 */
function pg_iys_token_parse($answer)
{
    $data = $answer['data'];

    return (($answer['outcome'] === 'ok') && is_array($data) && isset($data['access_token']) && is_string($data['access_token']))
        ? $data['access_token'] : '';
}

/**
 * The brands in a brand list answer.
 *
 * @param array $answer pg_iys_answer()
 * @return array brand code => name
 */
function pg_iys_brands_parse($answer)
{
    $brands = array();

    if (($answer['outcome'] !== 'ok') || !is_array($answer['data'])) {
        return $brands;
    }

    $list = isset($answer['data']['list']) ? $answer['data']['list'] : $answer['data'];

    foreach ((array) $list as $brand) {
        if (is_array($brand) && isset($brand['brandCode']) && is_scalar($brand['brandCode'])) {
            $brands[(string) $brand['brandCode']] = (isset($brand['name']) && is_scalar($brand['name'])) ? (string) $brand['name'] : '';
        }
    }

    return $brands;
}

/**
 * The e-mail changes of a changes answer and the cursor after them. Other
 * channels are left out: no SMS or call consent is kept here yet. read counts
 * every item of the page, the left-out ones too: a page of SMS changes alone
 * is no end of the list.
 *
 * @param array $answer pg_iys_answer()
 * @return array changes (email, status approved|refused, at), after, read
 */
function pg_iys_changes_parse($answer)
{
    $data = $answer['data'];
    $result = array('changes' => array(), 'after' => '', 'read' => 0);

    if (($answer['outcome'] !== 'ok') || !is_array($data)) {
        return $result;
    }

    if (isset($data['after']) && is_scalar($data['after'])) {
        $result['after'] = (string) $data['after'];
    }

    foreach ((isset($data['list']) && is_array($data['list'])) ? $data['list'] : array() as $item) {
        $result['read']++;

        if (!is_array($item) || !isset($item['type'], $item['status'], $item['recipient']) || ($item['type'] !== 'EPOSTA')) {
            continue;
        }

        $email = is_string($item['recipient']) ? trim($item['recipient']) : '';

        if (($email === '') || !in_array($item['status'], array('ONAY', 'RET'), true)) {
            continue;
        }

        $result['changes'][] = array(
            'email'  => $email,
            'status' => ($item['status'] === 'ONAY') ? 'approved' : 'refused',
            'at'     => pg_iys_timestamp(isset($item['consentDate']) ? $item['consentDate'] : null),
        );
    }

    return $result;
}

/**
 * One call. GET without a body, POST with one.
 *
 * @param array $request url, headers, optional body
 * @return array pg_iys_answer()
 */
function pg_iys_call($request)
{
    $method = isset($request['body']) ? 'POST' : 'GET';

    return pg_iys_answer(pg_sms_http($method, $request));
}

/**
 * An access token for the account, asked once per run.
 *
 * @param array $app pg_iys_app()
 * @return array token ('' on failure), error
 */
function pg_iys_token($app)
{
    static $tokens = array();

    $key = $app['env'] . '|' . $app['user'];

    if (!isset($tokens[$key])) {
        $answer = pg_iys_call(pg_iys_token_request($app));
        $token = pg_iys_token_parse($answer);
        $tokens[$key] = array('token' => $token, 'error' => ($token === '') ? (($answer['error'] !== '') ? $answer['error'] : 'no access token') : '');
    }

    return $tokens[$key];
}

/**
 * The brands of the account's İYS code, for the settings to offer.
 *
 * @param array $app pg_iys_app()
 * @return array ok, brands (code => name), error
 */
function pg_iys_brands($app)
{
    $token = pg_iys_token($app);

    if ($token['token'] === '') {
        return array('ok' => false, 'brands' => array(), 'error' => $token['error']);
    }

    $answer = pg_iys_call(pg_iys_brands_request($app, $token['token']));

    return array('ok' => ($answer['outcome'] === 'ok'), 'brands' => pg_iys_brands_parse($answer), 'error' => $answer['error']);
}

/**
 * Stores the job's cursors in the account's options, leaving the rest.
 *
 * @param array $state push_id and/or pull_after
 * @return void
 */
function pg_iys_state_save($state)
{
    $options = json_decode((string) db_value("SELECT options FROM connect_apps WHERE provider = 'iys'"), true);

    if (!is_array($options)) {
        return;
    }

    db("UPDATE connect_apps SET options = '" . e(json_encode(array_merge($options, $state))) . "' WHERE provider = 'iys'");
}

/**
 * The e-mail marketing answers not pushed yet, oldest first. Only a person's
 * own answer (pg_consent_log_answers()) is in the log: a contact opted in by
 * a default, an import or the API has no row and is never pushed. Rows
 * pulled from İYS are not sent back.
 *
 * @param int $after_id
 * @param int $limit
 * @return array rows: id, created_at, status, email
 */
function pg_iys_push_rows($after_id, $limit)
{
    return db_items(
        "SELECT
            consent_log.id,
            consent_log.created_at,
            consent_log.status,
            COALESCE(NULLIF(contacts.email_address, ''), user.user_email, '') AS email
        FROM consent_log
        LEFT JOIN contacts ON (consent_log.contact_id > 0) AND (contacts.id = consent_log.contact_id)
        LEFT JOIN user ON (consent_log.user_id > 0) AND (user.user_id = consent_log.user_id)
        WHERE
            (consent_log.id > '" . (int) $after_id . "')
            AND (consent_log.channel = 'email')
            AND (consent_log.purpose = 'marketing')
            AND (consent_log.status IN ('granted', 'refused'))
            AND (consent_log.source <> 'iys')
        ORDER BY consent_log.id
        LIMIT " . (int) $limit);
}

/**
 * Pushes the answers given since the last run. A row İYS refuses is logged
 * and passed over; a stop keeps the cursor before it for the next run.
 *
 * @param array $app   pg_iys_app()
 * @param int   $limit rows per run
 * @return array pushed, rejected, skipped, last_id, error, dry_run
 */
function pg_iys_push($app, $limit = 500)
{
    $result = array('pushed' => 0, 'rejected' => 0, 'skipped' => 0, 'last_id' => $app['push_id'], 'error' => '', 'dry_run' => !$app['live']);
    $rows = pg_iys_push_rows($app['push_id'], $limit);

    if (!$rows) {
        return $result;
    }

    // A dry run leaves the cursor where it is, so the rows are pushed once
    // the account goes live.
    if (!$app['live']) {
        $result['pushed'] = count($rows);
        $result['last_id'] = (int) $rows[count($rows) - 1]['id'];

        return $result;
    }

    $token = pg_iys_token($app);

    if ($token['token'] === '') {
        $result['error'] = $token['error'];

        return $result;
    }

    foreach ($rows as $row) {
        $consent = pg_iys_consent_body($row['email'], $row['status'], $row['created_at']);

        if ($consent !== null) {
            $answer = pg_iys_call(pg_iys_consent_request($app, $token['token'], $consent));

            if ($answer['outcome'] === 'stop') {
                $result['error'] = $answer['error'];
                break;
            }

            if ($answer['outcome'] === 'ok') {
                $result['pushed']++;
                $audit_before = pg_audit_fetch_by('contacts', 'email_address', $consent['recipient']);
                db("UPDATE contacts SET iys_email_status = '" . (($consent['status'] === 'ONAY') ? 'approved' : 'refused') . "'
                    WHERE email_address = '" . e($consent['recipient']) . "'");
                pg_audit_record_many('update', 'contacts', $audit_before);
            } else {
                $result['rejected']++;

                if ($result['error'] === '') {
                    $result['error'] = $answer['error'];
                }
            }
        } else {
            $result['skipped']++;
        }

        $result['last_id'] = (int) $row['id'];
    }

    if ($result['last_id'] !== $app['push_id']) {
        pg_iys_state_save(array('push_id' => $result['last_id']));
    }

    return $result;
}

/**
 * Applies the changes recipients made through İYS. A refusal stops
 * commercial e-mail at once: the contacts with the address are opted out
 * and the refusal is written to the consent log (source iys, never pushed
 * back). An approval only records the status: whether the person wants the
 * mails is their own answer here, which a late page of changes must not undo.
 *
 * @param array $app   pg_iys_app()
 * @param int   $pages pages of 999 changes per run
 * @return array approved, refused, error
 */
function pg_iys_pull($app, $pages = 10)
{
    $result = array('approved' => 0, 'refused' => 0, 'error' => '');
    $token = pg_iys_token($app);

    if ($token['token'] === '') {
        $result['error'] = $token['error'];

        return $result;
    }

    $after = $app['pull_after'];

    for ($page = 0; $page < $pages; $page++) {
        $answer = pg_iys_call(pg_iys_changes_request($app, $token['token'], $after, 999));

        if ($answer['outcome'] !== 'ok') {
            $result['error'] = $answer['error'];
            break;
        }

        $read = pg_iys_changes_parse($answer);

        foreach ($read['changes'] as $change) {
            pg_iys_apply_change($change);
            $result[$change['status']]++;
        }

        if (($read['after'] === '') || ($read['after'] === $after)) {
            break;
        }

        $after = $read['after'];
        pg_iys_state_save(array('pull_after' => $after));

        // An empty page ends the list. A page of other channels' changes
        // alone does not: an e-mail refusal behind it is applied this run.
        if ($read['read'] === 0) {
            break;
        }
    }

    return $result;
}

/**
 * Writes one pulled change to the contacts with its address.
 *
 * @param array $change pg_iys_changes_parse() change
 * @return void
 */
function pg_iys_apply_change($change)
{
    $where = "email_address = '" . e($change['email']) . "'";
    $audit_before = pg_audit_fetch_by('contacts', 'email_address', $change['email']);

    if ($change['status'] === 'refused') {
        $contact_id = (int) db_value("SELECT id FROM contacts WHERE " . $where . " AND (opt_in = '1') ORDER BY id LIMIT 1");

        db("UPDATE contacts SET opt_in = '0' WHERE " . $where . " AND (opt_in = '1')");

        if ($contact_id && function_exists('pg_consent_log_record')) {
            pg_consent_log_record(array(
                'channel'    => 'email',
                'purpose'    => 'marketing',
                'status'     => 'refused',
                'source'     => 'iys',
                'email'      => $change['email'],
                'contact_id' => $contact_id,
                'created_at' => ($change['at'] > 0) ? $change['at'] : time(),
                'ip'         => '',
                'user_agent' => '',
            ));
        }
    }

    db("UPDATE contacts SET iys_email_status = '" . e($change['status']) . "' WHERE " . $where);

    // One line per contact with the address, the opt-out and the status together.
    pg_audit_record_many('update', 'contacts', $audit_before);
}

/**
 * The daily run: push, then pull, so a refusal given here before the pull
 * is in İYS before İYS is read. A dry run calls nothing and logs what it
 * would push.
 *
 * @return array ran, reason, push, pull
 */
function pg_iys_run()
{
    $app = pg_iys_app();

    if (!pg_iys_app_complete($app) || !$app['email']) {
        return array('ran' => false, 'reason' => 'off', 'push' => null, 'pull' => null);
    }

    if (!pg_consent_log_ready() || !pg_schema_has('contacts', 'iys_email_status')) {
        return array('ran' => false, 'reason' => 'schema', 'push' => null, 'pull' => null);
    }

    // The contact changes are the sync's own, also when a manager starts the
    // run from the panel.
    $was = pg_audit_as_job(true);
    $push = pg_iys_push($app);

    if (!$app['live']) {
        pg_audit_as_job($was);

        if ($push['pushed'] > 0) {
            log_activity('İYS dry run: ' . $push['pushed'] . ' e-mail consent answer(s) would be pushed (consent log rows ' . ($app['push_id'] + 1) . '-' . $push['last_id'] . ').', 'UNKNOWN');
        }

        return array('ran' => true, 'reason' => 'dry_run', 'push' => $push, 'pull' => null);
    }

    $pull = pg_iys_pull($app);
    pg_audit_as_job($was);

    if ($push['rejected'] || ($push['error'] !== '') || ($pull['error'] !== '')) {
        log_activity('İYS: ' . $push['pushed'] . ' consent(s) pushed, ' . $push['rejected'] . ' refused by İYS, '
            . $pull['refused'] . ' refusal(s) pulled. ' . trim($push['error'] . ' ' . $pull['error']), 'UNKNOWN');
    }

    return array('ran' => true, 'reason' => 'live', 'push' => $push, 'pull' => $pull);
}
