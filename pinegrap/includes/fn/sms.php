<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Text messages (SMS) through the site's own provider account.
 *
 * One function sends, whatever the provider: pg_sms_send($to, $text, $kind).
 * The provider is a driver under includes/sms/<key>.php, listed in
 * pg_sms_providers(), with two functions: pg_sms_<key>_send() and
 * pg_sms_<key>_senders(). A driver is loaded only when a message or a check
 * needs it.
 *
 * The account is kept in connect_apps under 'sms_<key>', like the carriers'
 * API applications: the user code as client_id, the password sealed with the
 * site's key and never drawn back into a form, the sender name among the
 * options and the Communication settings' switch as enabled. A provider added
 * later needs no schema change.
 *
 * Every message has a kind. Transactional kinds (an order, a shipment, a
 * sign-in code, a reminder, the one back-in-stock notice a customer asked
 * for) go out as informational messages; a commercial
 * one needs the recipient's consent recorded in İYS, and until the consent
 * records exist pg_sms_send() refuses it.
 *
 * pg_sms_send() makes the network call itself and waits for the answer, so it
 * belongs in a background job or behind an operator's button (the settings'
 * test message), never in a request a visitor waits on. A request calls
 * pg_sms_enqueue() instead: the message waits in sms_outbox and the general
 * job sends it (pg_sms_queue_run()). A queued message is sent again only when
 * the provider's answer shows it never took it; when the provider may have
 * it (a timeout, a 5xx, a run that stopped while sending) it is left
 * 'uncertain', since a text message sent twice cannot be taken back.
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
 * The SMS providers, in the order the settings offer them.
 *
 * @return array key => label, file (under includes/sms/), portal
 */
function pg_sms_providers()
{
    return array(
        'netgsm' => array(
            'label'  => 'Netgsm',
            'file'   => 'netgsm.php',
            'portal' => 'https://www.netgsm.com.tr/',
        ),
    );
}

/**
 * @param string $provider
 * @return array|null
 */
function pg_sms_provider_definition($provider)
{
    $providers = pg_sms_providers();

    return (is_string($provider) && isset($providers[$provider])) ? $providers[$provider] : null;
}

/**
 * The connect_apps key a provider's account is kept under. Prefixed, so a
 * provider file added later for a sign-in with the same company does not
 * share it.
 *
 * @param string $provider
 * @return string
 */
function pg_sms_app_key($provider)
{
    return 'sms_' . $provider;
}

/**
 * A provider's account on this site.
 *
 * @param string $provider
 * @return array user, password ('' when none or when the site's key cannot
 *               read it), password_stored, sender, enabled
 */
function pg_sms_app($provider)
{
    $app = function_exists('pg_connect_app') ? pg_connect_app(pg_sms_app_key($provider)) : null;

    if (!is_array($app)) {
        return array(
            'user'            => '',
            'password'        => '',
            'password_stored' => false,
            'sender'          => '',
            'enabled'         => false,
        );
    }

    return array(
        'user'            => (string) $app['client_id'],
        'password'        => (string) $app['client_secret'],
        'password_stored' => (bool) $app['secret_stored'],
        'sender'          => (isset($app['options']['sender']) && is_scalar($app['options']['sender'])) ? (string) $app['options']['sender'] : '',
        'enabled'         => (bool) $app['enabled'],
    );
}

/**
 * Whether an account has everything a message needs.
 *
 * @param array $app pg_sms_app()
 * @return bool
 */
function pg_sms_app_complete($app)
{
    return ($app['user'] !== '') && ($app['password'] !== '') && ($app['sender'] !== '');
}

/**
 * The provider messages go through: the first one switched on with a
 * complete account.
 *
 * @return string provider key, '' when SMS is not set up
 */
function pg_sms_provider()
{
    foreach (array_keys(pg_sms_providers()) as $provider) {
        $app = pg_sms_app($provider);

        if ($app['enabled'] && pg_sms_app_complete($app)) {
            return $provider;
        }
    }

    return '';
}

/**
 * Whether the site can send SMS.
 *
 * @return bool
 */
function pg_sms_ready()
{
    return pg_sms_provider() !== '';
}

/**
 * The kinds of message, and whether each is commercial (İYS checked).
 *
 * @return array kind => commercial
 */
function pg_sms_kinds()
{
    return array(
        'test'      => false,
        'order'     => false,
        'shipping'  => false,
        'otp'       => false,
        'reminder'  => false,
        'stock'     => false,
        'marketing' => true,
    );
}

/**
 * A mobile number as the drivers take it: the international form in digits,
 * without the plus (E.164). Spaces, dashes, dots, slashes and brackets are
 * dropped. A number without a country code is read as a Turkish mobile
 * number (05xx…, 5xx…, 90 5xx…); any other one needs its "+" or "00".
 *
 * @param mixed $input
 * @return string '' when it is not such a number
 */
function pg_sms_number($input)
{
    if (!is_scalar($input)) {
        return '';
    }

    $plain = preg_replace('/[\s\x{00A0}().\/-]+/u', '', trim((string) $input));

    if (!is_string($plain) || !preg_match('/^\+?\d+$/', $plain)) {
        return '';
    }

    if ($plain[0] === '+') {
        $digits = substr($plain, 1);
    } elseif (strpos($plain, '00') === 0) {
        $digits = substr($plain, 2);
    } elseif (preg_match('/^(?:90|0)?(5\d{9})$/', $plain, $match)) {
        $digits = '90' . $match[1];
    } else {
        return '';
    }

    // Turkey: the trunk zero people keep after the country code is dropped,
    // and only a mobile number (5xx) can take a text message.
    if (strpos($digits, '90') === 0) {
        $digits = preg_replace('/^900(?=5)/', '90', $digits);

        return preg_match('/^905\d{9}$/', $digits) ? $digits : '';
    }

    // A country code never starts with 0; E.164 allows fifteen digits.
    return preg_match('/^[1-9]\d{7,14}$/', $digits) ? $digits : '';
}

/**
 * A number kept in the form pg_sms_number() gives (E.164 digits without the
 * plus), written so that pg_sms_number() reads it back unchanged. Without the
 * plus a foreign number of ten digits that starts with 5 (Cuba +53 5…,
 * Belize +501…, Panama +507…) would be read as a Turkish mobile, 90 5….
 *
 * @param string $number E.164 digits
 * @return string
 */
function pg_sms_dial($number)
{
    $number = (string) $number;

    return ($number === '') ? '' : '+' . $number;
}

/**
 * A message as it is sent: line breaks as \n, a tab as a space, no other
 * control character, no spaces around it.
 *
 * @param mixed $text
 * @return string '' for text that is not valid UTF-8
 */
function pg_sms_text($text)
{
    $text = is_scalar($text) ? (string) $text : '';

    if (!mb_check_encoding($text, 'UTF-8')) {
        return '';
    }

    $text = str_replace(array("\r\n", "\r", "\t"), array("\n", "\n", ' '), $text);
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);

    return trim((string) $text);
}

/**
 * The longest message passed on, in characters. No message of the site's
 * comes near it; it stops a mistake (a whole e-mail body handed over as the
 * text) from going out as a dozen paid parts.
 *
 * @return int
 */
function pg_sms_max_length()
{
    return 1000;
}

/**
 * A failed send in the shape every send returns.
 *
 * @param string $error
 * @param bool   $retry
 * @return array ok, id, code, error, retry, uncertain
 */
function pg_sms_failure($error, $retry = false)
{
    return array('ok' => false, 'id' => '', 'code' => '', 'error' => (string) $error, 'retry' => (bool) $retry, 'uncertain' => false);
}

/**
 * What every message is checked for before any account is read: a known kind
 * that is not commercial (until İYS consent records exist), a number an SMS
 * can be sent to, a text that is not empty and not longer than
 * pg_sms_max_length().
 *
 * @param mixed $to
 * @param mixed $text
 * @param mixed $kind
 * @return array ok, number (E.164 digits), text (as it is sent), error
 */
function pg_sms_check($to, $text, $kind)
{
    $kinds = pg_sms_kinds();
    $refused = array('ok' => false, 'number' => '', 'text' => '', 'error' => '');

    if (!is_string($kind) || !isset($kinds[$kind])) {
        $refused['error'] = lang('Unknown kind of SMS message.');
        return $refused;
    }

    if ($kinds[$kind]) {
        $refused['error'] = lang('Marketing messages are not sent by SMS: they need the recipient\'s consent recorded in İYS.');
        return $refused;
    }

    $number = pg_sms_number($to);

    if ($number === '') {
        $refused['error'] = lang('This is not a mobile number an SMS can be sent to.');
        return $refused;
    }

    $text = pg_sms_text($text);

    if ($text === '') {
        $refused['error'] = lang('The message is empty.');
        return $refused;
    }

    if (mb_strlen($text, 'UTF-8') > pg_sms_max_length()) {
        $refused['error'] = lang(array('string' => 'The message is longer than {var:1} characters.', 'vars' => array(pg_sms_max_length())));
        return $refused;
    }

    return array('ok' => true, 'number' => $number, 'text' => $text, 'error' => '');
}

/**
 * Loads a provider's driver.
 *
 * @param string $provider
 * @return bool whether its functions are there
 */
function pg_sms_driver($provider)
{
    $definition = pg_sms_provider_definition($provider);

    if ($definition === null) {
        return false;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/sms/' . $definition['file'];

    if (!function_exists('pg_sms_' . $provider . '_send') && is_file($file)) {
        require_once($file);
    }

    return function_exists('pg_sms_' . $provider . '_send') && function_exists('pg_sms_' . $provider . '_senders');
}

/**
 * Whether a provider can text a number. A driver names the numbers it takes
 * with pg_sms_<key>_number() ('' for one it does not take): Netgsm takes
 * Turkish mobile numbers only, its international sending being a separate
 * permission on the account. A number kept for a message sent later (a
 * back-in-stock request) is asked about first: queued as it is, it would be
 * refused only when the general job sends it. A driver without that function
 * takes every number pg_sms_number() reads.
 *
 * @param string $number   E.164 digits, as pg_sms_number() gives them
 * @param string $provider a pg_sms_providers() key: pg_sms_provider() for the
 *                         one messages go through, '' when SMS is not set up
 * @return bool false without a provider
 */
function pg_sms_reachable($number, $provider)
{
    $number = (string) $number;

    // Only the stored form: digits that pg_sms_dial() reads back unchanged.
    if (($number === '') || (pg_sms_number(pg_sms_dial($number)) !== $number) || !pg_sms_driver((string) $provider)) {
        return false;
    }

    $rule = 'pg_sms_' . $provider . '_number';

    return !function_exists($rule) || ($rule($number) !== '');
}

/**
 * Sends one message.
 *
 * @param string $to   a mobile number in any form pg_sms_number() reads
 * @param string $text
 * @param string $kind one of pg_sms_kinds()
 * @return array ok, id (the provider's id for the message), code (the
 *               provider's result code), error (a sentence for the operator),
 *               retry (whether sending it again cannot deliver it twice, so
 *               trying later may succeed), uncertain (the provider may have
 *               taken it although no success came back: sending it again
 *               could deliver it twice)
 */
function pg_sms_send($to, $text, $kind)
{
    $check = pg_sms_check($to, $text, $kind);

    if (!$check['ok']) {
        return pg_sms_failure($check['error']);
    }

    $number = $check['number'];
    $text = $check['text'];

    // Anyone can sign in to a demonstration site, so it sends nothing, as it
    // sends no e-mail (email() in includes/fn/mail.php).
    if (function_exists('pg_demo') && pg_demo()) {
        return pg_sms_failure(lang('No text message is sent from a demonstration site.'));
    }

    $provider = pg_sms_provider();

    if ($provider === '') {
        return pg_sms_failure(lang('SMS is not set up.'));
    }

    if (!pg_sms_driver($provider)) {
        return pg_sms_failure(lang('The SMS provider could not be loaded.'));
    }

    $send = 'pg_sms_' . $provider . '_send';

    return $send(pg_sms_app($provider), $number, $text, false);
}

/**
 * The sender names approved on an account: the check of a user code and
 * password that costs no credit.
 *
 * @param string $provider
 * @param array  $app pg_sms_app() shape
 * @return array ok, senders, code, error, refused (the provider answered and
 *               refused the account; false when it could not be asked)
 */
function pg_sms_senders($provider, $app)
{
    if (!pg_sms_driver($provider)) {
        return array('ok' => false, 'senders' => array(), 'code' => '', 'error' => lang('The SMS provider could not be loaded.'), 'refused' => false);
    }

    $senders = 'pg_sms_' . $provider . '_senders';

    return $senders($app);
}

/**
 * The approved spelling of a sender name, compared without regard to case.
 * The account's own number (the user code) is a sender too, listed or not.
 *
 * @param string $sender
 * @param array  $senders the provider's list
 * @param string $user    the account's user code
 * @return string '' when it is not among them
 */
function pg_sms_sender_match($sender, $senders, $user = '')
{
    if (((string) $sender !== '') && ((string) $sender === (string) $user)) {
        return (string) $sender;
    }

    $wanted = mb_strtolower(trim((string) $sender), 'UTF-8');

    foreach ($senders as $approved) {
        if ($approved === $sender) {
            return $approved;
        }
    }

    foreach ($senders as $approved) {
        if (mb_strtolower($approved, 'UTF-8') === $wanted) {
            return $approved;
        }
    }

    return '';
}

/**
 * A driver's HTTP call. The provider's address is fixed in its driver, never
 * typed by anyone, so this is a fixed-host call (pg_curl_run()) with the
 * certificate checks the carriers and the update channel use; a redirect is
 * not followed. The cURL error number, a proxy's answer to the tunnel and the
 * bytes of the request that went out tell a caller whether the request can
 * have reached the provider (pg_sms_http_never_sent()).
 *
 * @param string $method
 * @param array  $request url, headers, body (optional)
 * @return array status (0 when nothing came back), body, error (a transport
 *               problem, '' when the provider answered), errno (cURL's error
 *               number, 0 when the provider answered), connect (the HTTP
 *               status a proxy answered the tunnel request with, 0 without a
 *               proxy), request_size (bytes of request headers written; 0
 *               when no request went out)
 */
function pg_sms_http($method, $request)
{
    // Nothing left the server: reported as cURL's CURLE_FAILED_INIT, which
    // pg_sms_http_never_sent() counts as a request that reached nobody.
    if (!function_exists('curl_init')) {
        return array('status' => 0, 'body' => '', 'error' => lang('cURL is not available on this server.'), 'errno' => 2, 'connect' => 0, 'request_size' => 0);
    }

    $options = array(
        'headers'         => (array) $request['headers'],
        'timeout'         => 20,
        'connect_timeout' => 10,
        'user_agent'      => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+sms)',
    );

    if (isset($request['body']) && ((string) $request['body'] !== '')) {
        $options['body'] = (string) $request['body'];
    }

    $answer = pg_curl_run((string) $method, (string) $request['url'], $options);

    if ($answer['errno'] !== 0) {
        return array(
            'status'  => 0,
            'body'    => '',
            'error'   => trim($answer['error'] . pg_curl_tls_hint($answer['errno'])),
            'errno'   => $answer['errno'],
            'connect' => $answer['connect'],
            'request_size' => $answer['request_size'],
        );
    }

    return array('status' => $answer['status'], 'body' => $answer['body'], 'error' => '', 'errno' => 0, 'connect' => $answer['connect'], 'request_size' => $answer['request_size']);
}

/**
 * Whether a transport error came before anything reached the provider: cURL
 * could not start, the proxy or the provider's name could not be resolved,
 * no connection was made, the TLS handshake failed, a proxy refused the
 * tunnel to the provider, or not a byte of the request went out. After any
 * other one (a timeout once the request was written above all) the provider
 * may have taken the request, so sending it again could deliver a message
 * twice.
 *
 * @param int $errno        cURL's error number
 * @param int $connect      the status a proxy answered the tunnel request
 *                          with (pg_sms_http()), 0 without a proxy
 * @param int $request_size bytes of request headers written (pg_sms_http()),
 *                          -1 when not known
 * @return bool
 */
function pg_sms_http_never_sent($errno, $connect = 0, $request_size = -1)
{
    $errno = (int) $errno;

    // CURLE_FAILED_INIT, CURLE_COULDNT_RESOLVE_PROXY,
    // CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT.
    if (in_array($errno, array(2, 5, 6, 7), true)) {
        return true;
    }

    // The TLS handshake and the certificate checks end before the request is
    // written: CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM,
    // CURLE_SSL_CIPHER, CURLE_PEER_FAILED_VERIFICATION,
    // CURLE_SSL_ENGINE_INITFAILED, CURLE_SSL_CACERT_BADFILE,
    // CURLE_SSL_ISSUER_ERROR, CURLE_SSL_PINNEDPUBKEYNOTMATCH,
    // CURLE_SSL_INVALIDCERTSTATUS. A server without an up-to-date CA bundle
    // fails here on every message until the bundle is fixed.
    if (in_array($errno, array(35, 58, 59, 60, 66, 77, 83, 90, 91), true)) {
        return true;
    }

    // No request was written: a connection that timed out before it was
    // made among others. cURL 8 does not count a proxy's tunnel request
    // here; older versions do, which only makes this answer more cautious.
    if (($errno !== 0) && ((int) $request_size === 0)) {
        return true;
    }

    // A proxy that answered the tunnel request with a refusal opened no way
    // to the provider, whatever number cURL reports it under (cURL 8.5 says
    // 56, a receive error that on its own could come after the request).
    return ($errno !== 0) && ((int) $connect >= 300);
}


/* ---------------------------------------------------------------------------
   The queue (sms_outbox)
   --------------------------------------------------------------------------- */

/**
 * Whether the 2026.11.0 upgrade has created sms_outbox.
 *
 * @return bool
 */
function pg_sms_queue_ready()
{
    return pg_schema_has('sms_outbox');
}

/**
 * Whether queued messages are being sent: the general job, the only job that
 * works this queue, finished within the last fifteen minutes. Asked once per
 * request.
 *
 * @return bool
 */
function pg_sms_job_alive()
{
    static $alive = null;

    if ($alive === null) {
        $alive = pg_schema_has('cron_runs')
            && pg_mail_should_queue((int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = 'job'"), time());
    }

    return $alive;
}

/**
 * The row pg_sms_enqueue() writes, from a checked message and the caller's
 * options; no database.
 *
 * A reference names what the message is about (order:438:received): letters,
 * digits and : . _ - /, at most 64. One that is not such a name is refused
 * rather than dropped, since dropping it would lose the guard against
 * queueing the same message twice.
 *
 * @param array  $check   a pg_sms_check() that passed
 * @param string $kind
 * @param array  $options reference, send_after, expires_at
 * @param int    $now
 * @return array created_at, send_after, expires_at, status, kind, recipient,
 *               body, reference (null for none); error alone when an option
 *               is refused
 */
function pg_sms_queue_row($check, $kind, $options, $now)
{
    $now = (int) $now;
    $options = is_array($options) ? $options : array();

    $reference = (isset($options['reference']) && is_scalar($options['reference'])) ? trim((string) $options['reference']) : '';

    if (($reference !== '') && !preg_match('#^[A-Za-z0-9:._/-]{1,64}$#D', $reference)) {
        return array('error' => lang('The reference of an SMS message has at most 64 letters, digits and the characters : . _ - /'));
    }

    $send_after = isset($options['send_after']) ? max($now, (int) $options['send_after']) : $now;
    $expires_at = isset($options['expires_at']) ? max(0, (int) $options['expires_at']) : 0;

    if (($expires_at > 0) && ($expires_at <= $send_after)) {
        return array('error' => lang('The message would expire before it could be sent.'));
    }

    return array(
        'created_at' => $now,
        'send_after' => $send_after,
        'expires_at' => $expires_at,
        'status'     => 'queued',
        'kind'       => (string) $kind,
        'recipient'  => (string) $check['number'],
        'body'       => (string) $check['text'],
        'reference'  => ($reference === '') ? null : $reference,
    );
}

/**
 * Puts one message in the queue for the general job (pg_sms_queue_run()).
 * This is what a request calls: it makes no network call and does not wait
 * for the provider. What pg_sms_send() would refuse is refused here, and so
 * is any message while SMS is not set up, so nothing collects in the queue
 * of a site without SMS.
 *
 * db_q() rather than db(): db() ends the request on a failed query,
 * and a request that only wanted to send a text message must not die because
 * the queue refused a row.
 *
 * @param string $to
 * @param string $text
 * @param string $kind    one of pg_sms_kinds()
 * @param array  $options reference (what the message is about; while its
 *                        row is kept, a second message with the same one is
 *                        not queued), send_after (not before this time),
 *                        expires_at (not after it; 0 for no limit)
 * @return array ok, row (the queue's id), duplicate (a message with this
 *               reference was already queued; row is that one), error
 */
function pg_sms_enqueue($to, $text, $kind, $options = array())
{
    $refused = array('ok' => false, 'row' => 0, 'duplicate' => false, 'error' => '');

    $check = pg_sms_check($to, $text, $kind);

    if (!$check['ok']) {
        $refused['error'] = $check['error'];
        return $refused;
    }

    if (function_exists('pg_demo') && pg_demo()) {
        $refused['error'] = lang('No text message is sent from a demonstration site.');
        return $refused;
    }

    if (!pg_sms_queue_ready()) {
        $refused['error'] = lang('The SMS queue is not available yet; run the upgrade.');
        return $refused;
    }

    if (!pg_sms_ready()) {
        $refused['error'] = lang('SMS is not set up.');
        return $refused;
    }

    $row = pg_sms_queue_row($check, $kind, $options, time());

    if (isset($row['error'])) {
        $refused['error'] = $row['error'];
        return $refused;
    }

    $result = db_q(
        "INSERT INTO sms_outbox (created_at, send_after, expires_at, status, kind, recipient, body, reference)
        VALUES (?, ?, ?, 'queued', ?, ?, ?, ?)",
        array(
            (string) (int) $row['created_at'],
            (string) (int) $row['send_after'],
            (string) (int) $row['expires_at'],
            (string) $row['kind'],
            (string) $row['recipient'],
            (string) $row['body'],
            ($row['reference'] === null) ? null : (string) $row['reference'],
        ));

    if ($result !== false) {
        return array('ok' => true, 'row' => (int) mysqli_insert_id(db::$con), 'duplicate' => false, 'error' => '');
    }

    // ER_DUP_ENTRY on the reference: this message is already in the queue.
    if (($row['reference'] !== null) && ((int) mysqli_errno(db::$con) === 1062)) {
        $existing = (int) db_value("SELECT id FROM sms_outbox WHERE reference = '" . e($row['reference']) . "'");

        return array('ok' => true, 'row' => $existing, 'duplicate' => true, 'error' => '');
    }

    $refused['error'] = lang('The message could not be queued.');
    return $refused;
}

/**
 * What becomes of a claimed message after its send; no database.
 *
 * Sent: the provider took it. Queued again: only when the answer says
 * sending it again cannot deliver it twice (retry), on the mail queue's
 * ladder (pg_mail_backoff()), and only while the next attempt comes before
 * the message expires. Uncertain: the provider may have taken it although no
 * success came back; it is not sent again by itself. Failed: refused, given
 * up after the last attempt, or expired before it was sent.
 *
 * @param array      $row  attempts, send_after, expires_at
 * @param array|null $send pg_sms_send(); null when the message expired
 *                         before it was sent
 * @param int        $now
 * @return array status, attempts, send_after, sent_at, last_error,
 *               provider_id
 */
function pg_sms_queue_outcome($row, $send, $now)
{
    $now = (int) $now;
    $expires_at = (int) $row['expires_at'];

    $outcome = array(
        'status'      => 'failed',
        'attempts'    => (int) $row['attempts'],
        'send_after'  => (int) $row['send_after'],
        'sent_at'     => 0,
        'last_error'  => '',
        'provider_id' => '',
    );

    if ($send === null) {
        $outcome['last_error'] = lang('The message expired before it could be sent.');
        return $outcome;
    }

    $outcome['attempts']++;

    if (!empty($send['ok'])) {
        $outcome['status'] = 'sent';
        $outcome['sent_at'] = $now;
        $outcome['provider_id'] = (string) $send['id'];
        return $outcome;
    }

    $outcome['last_error'] = (string) $send['error'];

    if (!empty($send['uncertain'])) {
        $outcome['status'] = 'uncertain';
        return $outcome;
    }

    if (empty($send['retry'])) {
        return $outcome;
    }

    $wait = pg_mail_backoff($outcome['attempts']);

    if (($outcome['attempts'] >= pg_mail_max_attempts()) || ($wait === 0)) {
        return $outcome;
    }

    if (($expires_at > 0) && (($now + $wait) >= $expires_at)) {
        $outcome['last_error'] = lang(array(
            'string' => '{var:1} Not tried again: the message expires before the next attempt.',
            'vars'   => array((string) $send['error'])));
        return $outcome;
    }

    $outcome['status'] = 'queued';
    $outcome['send_after'] = $now + $wait;

    return $outcome;
}

/**
 * Sends what is due in the queue. Called by job.php on every run and by the
 * Run now button of the queue screen. Bounded by a row count and wall-clock
 * time: a provider that does not answer costs its timeout per message, and
 * this must not hold the general job past its next tick.
 *
 * @param int $limit
 * @param int $seconds
 * @return array sent, failed (sends that did not get through in this run,
 *               tried again later or not)
 */
function pg_sms_queue_run($limit = 10, $seconds = 15)
{
    $result = array('sent' => 0, 'failed' => 0);

    if (!pg_sms_queue_ready()) {
        return $result;
    }

    // An empty queue, the case of every site that sends no SMS, costs this
    // one read.
    if (!db_value("SELECT id FROM sms_outbox LIMIT 1")) {
        return $result;
    }

    // A run that stopped while sending (a fatal error, a killed process)
    // leaves its row in 'sending'. Unlike an e-mail it is not queued again:
    // the provider may have taken the message. Fifteen minutes is far longer
    // than a send takes.
    db(
        "UPDATE sms_outbox
        SET
            status = 'uncertain',
            claimed_at = 0,
            last_error = '" . e(pg_mail_clip(lang('The run that was sending this message stopped before the provider\'s answer was recorded, so it may have been sent.'), 500)) . "'
        WHERE
            (status = 'sending')
            AND (claimed_at < " . (time() - 900) . ")");

    $provider = pg_sms_provider();

    $deadline = microtime(true) + $seconds;

    for ($looked = 0; ($looked < (int) $limit) && (microtime(true) < $deadline); $looked++) {

        $id = (int) db_value(
            "SELECT id
            FROM sms_outbox
            WHERE
                (status = 'queued')
                AND (send_after <= UNIX_TIMESTAMP())
            ORDER BY send_after, id
            LIMIT 1");

        if (!$id) {
            break;
        }

        // Claimed before it is sent. The condition on status makes the UPDATE
        // the arbiter: InnoDB locks the row for the statement, so when two
        // runs overlap exactly one of them changes it, and the other sees no
        // affected row and moves on.
        db(
            "UPDATE sms_outbox
            SET status = 'sending', claimed_at = UNIX_TIMESTAMP()
            WHERE
                (id = '" . $id . "')
                AND (status = 'queued')");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $row = db_item("SELECT id, attempts, send_after, expires_at, kind, recipient, body FROM sms_outbox WHERE id = '" . $id . "'");

        // Deleted from the queue screen in the moment between.
        if (!is_array($row)) {
            continue;
        }

        $expired = ((int) $row['expires_at'] > 0) && ((int) $row['expires_at'] <= time());

        $send = $expired ? null : pg_sms_send(pg_sms_dial($row['recipient']), $row['body'], $row['kind']);

        $outcome = pg_sms_queue_outcome($row, $send, time());

        db(
            "UPDATE sms_outbox
            SET
                status = '" . e($outcome['status']) . "',
                attempts = '" . (int) $outcome['attempts'] . "',
                send_after = '" . (int) $outcome['send_after'] . "',
                sent_at = '" . (int) $outcome['sent_at'] . "',
                last_error = '" . e(pg_mail_clip($outcome['last_error'], 500)) . "',
                provider = '" . e(($send === null) ? '' : pg_mail_clip($provider, 32)) . "',
                provider_id = '" . e(pg_mail_clip($outcome['provider_id'], 64)) . "',
                claimed_at = 0
            WHERE id = '" . $id . "'");

        if ($outcome['status'] === 'sent') {
            $result['sent']++;
            continue;
        }

        $result['failed']++;

        if ($outcome['status'] === 'uncertain') {
            log_activity(lang(array(
                'string' => 'The SMS provider may have taken a text message to +{var:1}; it is not sent again by itself: {var:2}',
                'vars'   => array($row['recipient'], $outcome['last_error']),
            )), 'UNKNOWN');
        } elseif (($outcome['status'] === 'failed') && !$expired) {
            log_activity(lang(array(
                'string' => 'A text message to +{var:1} was not sent: {var:2}',
                'vars'   => array($row['recipient'], $outcome['last_error']),
            )), 'UNKNOWN');
        }
    }

    // Sent messages are kept a week and the others a month, as the mail queue
    // keeps its rows, so the queue screen can show what happened; then they
    // are swept.
    db("DELETE FROM sms_outbox WHERE (status = 'sent') AND (sent_at < " . (time() - 604800) . ")");
    db("DELETE FROM sms_outbox WHERE (status IN ('failed', 'uncertain')) AND (created_at < " . (time() - 2592000) . ")");

    return $result;
}

/**
 * Sends a message on the next run: one waiting for a later attempt, one that
 * failed, or one whose outcome was uncertain (after the operator checked with
 * the provider that it did not arrive). One being sent, one that was sent and
 * one past its expiry are left alone.
 *
 * @param int $id
 * @return bool whether the message was put back in the queue
 */
function pg_sms_queue_retry($id)
{
    db(
        "UPDATE sms_outbox
        SET status = 'queued', send_after = 0, attempts = 0, claimed_at = 0
        WHERE
            (id = '" . (int) $id . "')
            AND (status IN ('queued', 'failed', 'uncertain'))
            AND ((expires_at = 0) OR (expires_at > UNIX_TIMESTAMP()))");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * Removes a message from the queue, sent or not.
 *
 * @param int $id
 * @return bool whether a message was removed
 */
function pg_sms_queue_delete($id)
{
    db("DELETE FROM sms_outbox WHERE id = '" . (int) $id . "'");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * The queue's figures for its screen.
 *
 * @return array queued, sending, sent (in the last 24 hours), failed,
 *               uncertain
 */
function pg_sms_queue_counts()
{
    $row = db_item(
        "SELECT
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
            SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) AS sending,
            SUM(CASE WHEN (status = 'sent') AND (sent_at >= " . (time() - 86400) . ") THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain
        FROM sms_outbox");

    $counts = array();

    foreach (array('queued', 'sending', 'sent', 'failed', 'uncertain') as $status) {
        $counts[$status] = (is_array($row) && isset($row[$status])) ? (int) $row[$status] : 0;
    }

    return $counts;
}

/**
 * Whether the queue screen has an SMS tab: SMS is set up, or the queue still
 * holds messages from when it was.
 *
 * @return bool
 */
function pg_sms_queue_shown()
{
    return pg_sms_queue_ready() && (pg_sms_ready() || (bool) db_value("SELECT id FROM sms_outbox LIMIT 1"));
}


/* ---------------------------------------------------------------------------
   Settings › Communication › SMS
   --------------------------------------------------------------------------- */

/**
 * A posted text field, '' when absent or not text.
 *
 * @param array  $post
 * @param string $name
 * @return string
 */
function pg_sms_post($post, $name)
{
    return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
}

/**
 * Whether a sender name can be one: at most eleven characters, no control
 * characters. The provider's list is what says it is approved.
 *
 * @param string $sender
 * @return bool
 */
function pg_sms_sender_valid($sender)
{
    $sender = (string) $sender;

    return ($sender !== '') && (mb_strlen($sender, 'UTF-8') <= 11) && !preg_match('/[\x00-\x1F\x7F]/', $sender);
}

/**
 * The provider whose account the SMS card shows: the one sending, otherwise
 * the first with a stored account, otherwise the first.
 *
 * @return string
 */
function pg_sms_settings_provider()
{
    $active = pg_sms_provider();

    if ($active !== '') {
        return $active;
    }

    foreach (array_keys(pg_sms_providers()) as $provider) {
        if (pg_sms_app($provider)['user'] !== '') {
            return $provider;
        }
    }

    $providers = array_keys(pg_sms_providers());

    return $providers[0];
}

/**
 * The fields of the SMS card: the switch, the provider and its account (the
 * password never drawn back) and the test message.
 *
 * @return string
 */
function pg_sms_settings_fields()
{
    $shown = pg_sms_settings_provider();
    $app = pg_sms_app($shown);
    $enabled = $app['enabled'];

    $warning = pg_sms_settings_refusal();

    if (($warning === '') && $enabled && !pg_sms_app_complete($app)) {
        $warning = $app['password_stored'] && ($app['password'] === '')
            ? lang('SMS is switched on but cannot send: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang('SMS is switched on but cannot send until the account is complete.');
    }

    // Messages go out through the general job; without it they only wait.
    if (($warning === '') && $enabled && pg_sms_queue_ready() && !pg_sms_job_alive()) {
        $warning = lang('The general job has not run in the last 15 minutes, so text messages wait in the queue until it runs. Schedule it under Cron Jobs.');
    }

    $options = '';

    foreach (pg_sms_providers() as $key => $definition) {
        $options .= '<option value="' . h($key) . '"' . (($key === $shown) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
    }

    $html = '
                                        <div class="col-12">
                                            <div class="form-field">
                                                <div class="switch"><input type="checkbox" name="sms_enabled" id="sms_enabled" value="1"' . ($enabled ? ' checked="checked"' : '') . ' class="collapse-switcher" data-bs-target="#sms_row"/></div>
                                                <label class="" for="sms_enabled">' . lang('Send SMS') . '<br/><span class="form-text">' . lang('Text messages through an SMS provider account of the site. Only transactional messages are sent and they carry no promotion: a marketing message needs the recipient\'s consent recorded in İYS.') . '</span></label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0" id="sms_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-5">';

    if ($warning !== '') {
        $html .= '
                                                        <div class="col-12"><div class="alert theme-warning mb-0">' . h($warning) . '</div></div>';
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="sms_provider" class="form-label">' . lang('Provider') . '</label>
                                                            <select name="sms_provider" id="sms_provider" class="form-control">' . $options . '</select>
                                                        </div>';

    foreach (pg_sms_providers() as $key => $definition) {
        $html .= pg_sms_settings_account_fields($key, $definition, ($key === $shown) ? $app : pg_sms_app($key));
    }

    if (pg_sms_queue_ready()) {
        $html .= '
                                                        <div class="col-12 form-text">' . lang(array(
                                                            'string' => 'Messages wait in a queue that the scheduled general job sends; they are listed on the SMS tab of the {var:1}.',
                                                            'vars'   => array('<a href="' . h(PATH . SOFTWARE_DIRECTORY . '/mail_queue.php?tab=sms') . '">' . h(lang('Mail queue')) . '</a>'))) . '</div>';
    }

    $html .= '
                                                        <div class="pg-f-md">
                                                            <label for="sms_test_to" class="form-label">' . lang('Test Number') . '</label>
                                                            <input type="tel" name="sms_test_to" id="sms_test_to" class="form-control" value="" maxlength="30" autocomplete="off" inputmode="tel"/>
                                                            <div class="form-text">' . lang('A mobile number. The message goes out at once and the provider charges it.') . '</div>
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="submit" name="sms_test" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Save and send a test message') . '</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';

    return $html;
}

/**
 * One provider's account fields.
 *
 * @param string $provider
 * @param array  $definition
 * @param array  $app pg_sms_app()
 * @return string
 */
function pg_sms_settings_account_fields($provider, $definition, $app)
{
    $name = 'sms_' . $provider . '_';

    // "Stored" only for a password this site can still read; the card's
    // warning says what to do with one it cannot.
    $placeholder = ($app['password_stored'] && ($app['password'] !== ''))
        ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"'
        : '';

    return '
                                                        <div class="col-12 form-text">' . lang(array(
                                                            'string' => 'Enter the user code and password of an API user of your {var:1} account, and a sender name approved on it. Saving checks them with {var:1}. To remove the account from this site, switch SMS off and empty the user code.',
                                                            'vars'   => array('<a href="' . h($definition['portal']) . '" target="_blank" rel="noopener">' . h($definition['label']) . '</a>'))) . '</div>
                                                        <div class="pg-f-md">
                                                            <label for="' . h($name) . 'user" class="form-label">' . lang('User Code') . '</label>
                                                            <input type="text" name="' . h($name) . 'user" id="' . h($name) . 'user" class="form-control" value="' . h($app['user']) . '" maxlength="255" autocomplete="off"/>
                                                        </div>
                                                        <div class="pg-f-md">
                                                            <label for="' . h($name) . 'password" class="form-label">' . lang('Password') . '</label>
                                                            <input type="password" name="' . h($name) . 'password" id="' . h($name) . 'password" class="form-control" value="" maxlength="255" autocomplete="new-password"' . $placeholder . '/>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="' . h($name) . 'sender" class="form-label">' . lang('Sender Name') . '</label>
                                                            <input type="text" name="' . h($name) . 'sender" id="' . h($name) . 'sender" class="form-control" value="' . h($app['sender']) . '" maxlength="11" autocomplete="off"/>
                                                        </div>';
}

/**
 * The provider a post of the SMS card is about.
 *
 * @param array $post
 * @return string|null null when the post has no SMS card, or not the fields
 *                     of the provider it names (a pane that does not draw
 *                     them says nothing about that account)
 */
function pg_sms_settings_posted_provider($post)
{
    if (!isset($post['sms_provider'])) {
        return null;
    }

    $provider = pg_sms_post($post, 'sms_provider');

    if (pg_sms_provider_definition($provider) === null) {
        return null;
    }

    return isset($post['sms_' . $provider . '_user']) ? $provider : null;
}

/**
 * What a post of the SMS card asks for, decided from the post and the stored
 * account alone: no database, no provider. pg_sms_settings_check() marks what
 * this refuses and asks the provider when this says so.
 *
 * Only a change is written. An account left as it was is not written again,
 * whatever else the Communication pane saves, so an account this site can no
 * longer read does not stop the chat or campaign settings from being saved.
 *
 * @param array  $post
 * @param string $provider pg_sms_settings_posted_provider()
 * @param array  $before   pg_sms_app() of that provider
 * @param string $refusal  why no account can be kept here now (database not
 *                         upgraded, no encryption key), '' when one can
 * @return array provider, user, password (as typed), sender, enabled,
 *               action ('none', 'save', 'disable' or 'forget'), ask (whether
 *               the provider is to check the account), password_effective
 *               (the typed or the stored one), test_to (E.164 digits or ''),
 *               marks (field => sentence), notices
 */
function pg_sms_settings_decide($post, $provider, $before, $refusal)
{
    $name = 'sms_' . $provider . '_';

    $decision = array(
        'provider'           => $provider,
        'user'               => pg_sms_post($post, $name . 'user'),
        'password'           => pg_sms_post($post, $name . 'password'),
        'sender'             => pg_sms_post($post, $name . 'sender'),
        'enabled'            => !empty($post['sms_enabled']),
        'action'             => 'none',
        'ask'                => false,
        'password_effective' => '',
        'test_to'            => '',
        'marks'              => array(),
        'notices'            => array(),
    );

    // The test number is read only when its button was pressed. Enter in any
    // field of the pane presses it too (it is the form's only submit button),
    // so an empty number is a plain save.
    if (!empty($post['sms_test']) && (pg_sms_post($post, 'sms_test_to') !== '')) {
        $decision['test_to'] = pg_sms_number(pg_sms_post($post, 'sms_test_to'));

        if ($decision['test_to'] === '') {
            $decision['marks']['sms_test_to'] = lang('This is not a mobile number an SMS can be sent to.');
        }
    }

    $stored = ($before['user'] !== '') || $before['password_stored'];

    if (!$decision['enabled'] && ($decision['user'] === '')) {
        // Off with no user code: the stored account, if any, is removed.
        $decision['action'] = $stored ? 'forget' : 'none';
        return $decision;
    }

    $unchanged = ($decision['user'] === $before['user'])
        && ($decision['password'] === '')
        && ($decision['sender'] === $before['sender']);

    if ($unchanged && ($decision['enabled'] === $before['enabled'])) {
        return $decision;
    }

    // Switching off writes the switch alone, which needs no sealing.
    if ($unchanged && !$decision['enabled']) {
        $decision['action'] = 'disable';
        return $decision;
    }

    if ($refusal !== '') {
        if ($decision['enabled']) {
            $decision['marks']['sms_enabled'] = $refusal;
        } else {
            $decision['action'] = $before['enabled'] ? 'disable' : 'none';
            $decision['notices'][] = lang(array('string' => 'The SMS account was not saved: {var:1}', 'vars' => array($refusal)));
        }

        return $decision;
    }

    $decision['action'] = 'save';

    if (!$decision['enabled']) {
        return $decision;
    }

    $decision['password_effective'] = ($decision['password'] !== '') ? $decision['password'] : $before['password'];

    if ($decision['user'] === '') {
        $decision['marks'][$name . 'user'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('User Code'))));
    }

    if ($decision['password_effective'] === '') {
        $decision['marks'][$name . 'password'] = $before['password_stored']
            ? lang('SMS is switched on but cannot send: the stored password cannot be read with this site\'s encryption key. Enter it again.')
            : lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Password'))));
    }

    if ($decision['sender'] === '') {
        $decision['marks'][$name . 'sender'] = lang(array('string' => '{var:1} is required.', 'vars' => array(lang('Sender Name'))));
    } elseif (!pg_sms_sender_valid($decision['sender'])) {
        $decision['marks'][$name . 'sender'] = lang('A sender name has at most 11 characters.');
    }

    $decision['ask'] = !$decision['marks'];

    return $decision;
}

/**
 * Why no SMS account can be set up on this site now, '' when one can. A
 * demonstration site, which anyone can sign in to, takes none: it would keep
 * what visitors type and ask the provider about it.
 *
 * @return string
 */
function pg_sms_settings_refusal()
{
    if (function_exists('pg_demo') && pg_demo()) {
        return lang('This is a demonstration site: no SMS provider can be set up here.');
    }

    if (!pg_connect_schema_ready()) {
        return lang('The database has not been upgraded yet. Run the software update first.');
    }

    if (!pg_connect_can_seal()) {
        return lang('This site has no encryption key, so it cannot keep the password of an SMS account.');
    }

    return '';
}

/**
 * Checks the SMS card's fields before the Communication settings are
 * written, and asks the provider about a changed account. A refusal is marked
 * on its field, so nothing of the category is written.
 *
 * @param array    $post     $_POST
 * @param liveform $liveform
 * @return array|null what pg_sms_settings_save() writes; null when the
 *                    posted pane had no SMS card
 */
function pg_sms_settings_check($post, $liveform)
{
    $provider = pg_sms_settings_posted_provider($post);

    if ($provider === null) {
        return null;
    }

    $decision = pg_sms_settings_decide($post, $provider, pg_sms_app($provider), pg_sms_settings_refusal());

    foreach ($decision['marks'] as $field => $sentence) {
        $liveform->mark_error($field, $sentence);
    }

    // A category another card already refused is not written, so the
    // provider is not kept waiting for.
    if (!$decision['ask'] || $liveform->check_form_errors()) {
        return $decision;
    }

    $name = 'sms_' . $provider . '_';
    $label = h(pg_sms_provider_definition($provider)['label']);

    $check = pg_sms_senders($provider, array(
        'user'     => $decision['user'],
        'password' => $decision['password_effective'],
        'sender'   => $decision['sender']));

    if ($check['ok']) {
        $approved = pg_sms_sender_match($decision['sender'], $check['senders'], $decision['user']);

        if ($approved !== '') {
            $decision['sender'] = $approved;
        } elseif ($check['senders']) {
            $liveform->mark_error($name . 'sender', lang(array(
                'string' => 'The sender name {var:1} is not approved on this {var:2} account. Approved sender names: {var:3}',
                'vars'   => array(h($decision['sender']), $label, h(implode(', ', $check['senders']))))));
        } else {
            $liveform->mark_error($name . 'sender', lang(array(
                'string' => 'This {var:1} account has no approved sender name yet.',
                'vars'   => array($label))));
        }
    } elseif ($check['refused']) {
        $liveform->mark_error(($check['code'] === '30') ? $name . 'password' : $name . 'user', h($check['error']));
    } else {
        // The provider could not be asked (no answer, an unreadable one, an
        // outage, a limit): the account is kept unchecked rather than the
        // settings refused for it.
        $decision['notices'][] = lang(array(
            'string' => 'The SMS account was saved without being checked: {var:1}',
            'vars'   => array(h($check['error']))));
    }

    return $decision;
}

/**
 * Writes what pg_sms_settings_check() decided, then sends the test message
 * when its button was pressed. Runs after the Communication settings were
 * written, and only when nothing was refused.
 *
 * @param array|null $decision
 * @param int        $user_id
 * @param liveform   $liveform
 */
function pg_sms_settings_save($decision, $user_id, $liveform)
{
    if (!is_array($decision)) {
        return;
    }

    $key = pg_sms_app_key($decision['provider']);

    if ($decision['action'] === 'forget') {
        db("DELETE FROM connect_apps WHERE provider = '" . e($key) . "'");
    } elseif ($decision['action'] === 'disable') {
        db("UPDATE connect_apps
            SET
                enabled = 0,
                updated_user_id = '" . (int) $user_id . "',
                updated_timestamp = UNIX_TIMESTAMP()
            WHERE provider = '" . e($key) . "'");
    } elseif ($decision['action'] === 'save') {
        pg_connect_app_save(
            $key,
            $decision['user'],
            ($decision['password'] !== '') ? $decision['password'] : null,
            array('sender' => $decision['sender']),
            $decision['enabled'],
            $user_id);

        // One provider sends: another one's account stays stored, off.
        if ($decision['enabled']) {
            foreach (array_keys(pg_sms_providers()) as $provider) {
                if (($provider !== $decision['provider']) && pg_sms_app($provider)['enabled']) {
                    db("UPDATE connect_apps SET enabled = 0 WHERE provider = '" . e(pg_sms_app_key($provider)) . "'");
                }
            }
        }
    }

    foreach ($decision['notices'] as $notice) {
        $liveform->add_notice($notice);
    }

    if ($decision['test_to'] !== '') {
        pg_sms_settings_test($decision['test_to'], $liveform);
    }
}

/**
 * Sends the settings' test message.
 *
 * @param string   $number pg_sms_number()
 * @param liveform $liveform
 */
function pg_sms_settings_test($number, $liveform)
{
    $provider = pg_sms_provider();

    if ($provider === '') {
        $liveform->add_notice(lang('The test message was not sent: SMS is not set up.'));
        return;
    }

    // Every test is a paid message: a ceiling per site and clock hour,
    // counted in the firewall's rate buckets (fails open when they are not
    // there). Every press counts, a refused one too.
    if (function_exists('waf_rate_exceeded') && isset(db::$con) && db::$con
        && waf_rate_exceeded('site', 'sms-test', 10, 3600)) {
        $liveform->add_notice(lang('The test message was not sent: the limit of ten tests an hour was reached. Try again in the next hour.'));
        return;
    }

    $result = pg_sms_send(pg_sms_dial($number), lang(array(
        'string' => 'Test message from {var:1}. SMS sending works.',
        'vars'   => array(HOSTNAME_SETTING))), 'test');

    if ($result['ok']) {
        $liveform->add_notice(lang(array(
            'string' => '{var:1} accepted the test message to {var:2} (job {var:3}). It may take a minute to arrive.',
            'vars'   => array(h(pg_sms_provider_definition($provider)['label']), '+' . $number, h($result['id'])))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'The test message was not sent: {var:1}',
            'vars'   => array(h($result['error'])))));
    }
}
