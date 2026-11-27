<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A customer's own support requests: the conversations of the Workspace's
 * support boxes they wrote in, listed on My Account (section "requests",
 * flag has_requests) for a signed-in member tied to them by user_id, and
 * opened on support_request.php by a signed link (?c=<id>&t=<token>) that
 * the box's automatic answer also carries, so a guest reaches the same page.
 *
 * The page shows the mails in from the customer and the answers that went
 * to them, never the channel's notes, drafts or mails of another person,
 * and its Reply box hands the customer's answer to the box's own intake
 * (ws_mail_ingest()) as a mail tagged [#T<id>], so it threads back, opens
 * the request again and tells the team as any mail does. No table and no
 * column: the link is an HMAC under ENCRYPTION_KEY, as booking and
 * volunteer links are. Loaded where used, not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most requests the My Account section lists. */
define('PG_SUPPORT_REQUEST_ACCOUNT_LIMIT', 50);

/** The longest answer the Reply box takes, in characters. */
define('PG_SUPPORT_REQUEST_REPLY_MAX', 10000);

/** The most mails the request's page draws, newest kept. */
define('PG_SUPPORT_REQUEST_PAGE_ITEMS', 200);

/**
 * The signed link's token of a conversation (pure): HMAC-SHA256 of its id
 * and creation time, so a link never opens a later conversation that took
 * a deleted one's id.
 *
 * @param int    $conversation_id
 * @param int    $created_at
 * @param string $key
 * @return string 64 hex, '' without an id or a key
 */
function pg_support_request_token($conversation_id, $created_at, $key)
{
    $conversation_id = (int) $conversation_id;
    $key = (string) $key;

    if (($conversation_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'support-request:' . $conversation_id . ':' . (int) $created_at, $key);
}

/**
 * Whether a link's token is the conversation's (pure).
 *
 * @param int    $conversation_id
 * @param int    $created_at
 * @param mixed  $token
 * @param string $key
 * @return bool
 */
function pg_support_request_token_valid($conversation_id, $created_at, $token, $key)
{
    $expected = pg_support_request_token($conversation_id, $created_at, $key);

    return ($expected !== '') && is_string($token) && hash_equals($expected, $token);
}

/**
 * The key the links are signed with: the site's encryption key.
 *
 * @return string
 */
function pg_support_request_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * Whether the Workspace's mail tables are there; loads the module.
 *
 * @return bool
 */
function pg_support_request_ready()
{
    if (!pg_schema_has('ws_mail_conversations', 'id') || !is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return ws_ready() && function_exists('ws_mail_ingest');
}

/**
 * Whether a box's conversations are requests the customer sees (pure): a
 * support box answered by e-mail. A box switched off keeps showing them.
 *
 * @param array|null $box ws_mail_boxes row
 * @return bool
 */
function pg_support_request_box_shown($box)
{
    if (!is_array($box) || in_array((string) ($box['transport'] ?? ''), array('whatsapp', 'sms'), true)) {
        return false;
    }

    $settings = json_decode((string) ($box['settings'] ?? ''), true);

    return !is_array($settings) || !array_key_exists('support', $settings) || !empty($settings['support']);
}

/**
 * The address of the request's page, '…/support_request.php'.
 *
 * @param bool $absolute with the scheme and host, for a mail
 * @return string
 */
function pg_support_request_base($absolute)
{
    if (!defined('OUTPUT_PATH')) {
        return '';
    }

    $path = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/support_request.php';

    return ($absolute && defined('URL_SCHEME')) ? URL_SCHEME . HOSTNAME_SETTING . $path : $path;
}

/**
 * The signed link to a conversation's page, '' without the key.
 *
 * @param array $conversation ws_mail_conversations row (id, created_at)
 * @param bool  $absolute
 * @return string
 */
function pg_support_request_url($conversation, $absolute = true)
{
    $token = pg_support_request_token((int) ($conversation['id'] ?? 0), (int) ($conversation['created_at'] ?? 0), pg_support_request_key());

    return ($token === '') ? '' : pg_support_request_base($absolute) . '?c=' . (int) $conversation['id'] . '&t=' . $token;
}

/**
 * The automatic answer's text with the line that leads to the request's
 * page (pure); the text as it was without a link.
 *
 * @param string $text
 * @param string $url
 * @return string
 */
function pg_support_request_auto_reply_text($text, $url)
{
    if ((string) $url === '') {
        return (string) $text;
    }

    return rtrim((string) $text) . "\n\n" . lang('You can follow your request and answer it here:') . "\n" . $url;
}

/**
 * Whether one address opened or answered requests too often (the
 * firewall's counters, which work with the firewall off): 120 opens in
 * five minutes, 20 answers in an hour.
 *
 * @param bool $post an answer
 * @return int 0, or the window in seconds to wait
 */
function pg_support_request_throttled($post)
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return 0;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip))) {
        return 0;
    }

    $rule = $post ? array('support_request_reply', 20, 3600) : array('support_request_page', 120, 300);

    if (!waf_rate_exceeded(waf_ip_subject($ip), $rule[0], $rule[1], $rule[2])) {
        return 0;
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-' . str_replace('_', '-', $rule[0])));
    }

    return $rule[2];
}

/**
 * The conversation a link opens: the token checked against the row, which
 * must be a request of a support box answered by e-mail; null otherwise.
 *
 * @param mixed $conversation_id ?c=
 * @param mixed $token           ?t=
 * @return array|null array(conversation, box)
 */
function pg_support_request_find($conversation_id, $token)
{
    if (!is_scalar($conversation_id) || !preg_match('/^\d{1,10}$/', (string) $conversation_id) || !is_string($token)
        || !preg_match('/^[0-9a-f]{64}$/', $token) || !pg_support_request_ready()) {
        return null;
    }

    $conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $conversation_id . "'");

    if (!is_array($conversation) || !pg_support_request_token_valid((int) $conversation['id'], (int) $conversation['created_at'], $token, pg_support_request_key())) {
        return null;
    }

    $box = ws_mail_box((int) $conversation['box_id']);

    if (!pg_support_request_box_shown($box) || !filter_var((string) $conversation['customer_email'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return array('conversation' => $conversation, 'box' => $box);
}

/**
 * Whether the customer sees a mail of their request (pure): their own mail
 * in, or an answer sent or being sent to them (To or Cc). A draft, a mail
 * waiting to be sent or one that failed, a mail of somebody else and an
 * answer that went only to somebody else stay with the team.
 *
 * @param array  $item           ws_mail_items row
 * @param string $customer_email
 * @return bool
 */
function pg_support_request_item_visible($item, $customer_email)
{
    $customer = mb_strtolower(trim((string) $customer_email));

    if ($customer === '') {
        return false;
    }

    $direction = (string) ($item['direction'] ?? '');

    if ($direction === 'in') {
        return mb_strtolower(trim((string) ($item['from_email'] ?? ''))) === $customer;
    }

    if (($direction !== 'out') || !in_array((string) ($item['send_state'] ?? ''), array('', 'queued', 'sent', 'external'), true)) {
        return false;
    }

    $addresses = mb_strtolower((string) ($item['to_list'] ?? '') . ',' . (string) ($item['cc_list'] ?? ''));

    return (bool) preg_match('/(^|[\s,;<"])' . preg_quote($customer, '/') . '($|[\s,;>"])/u', $addresses);
}

/**
 * The mails a request's page shows, oldest first.
 *
 * @param array $conversation
 * @return array ws_mail_items rows
 */
function pg_support_request_items($conversation)
{
    $columns = 'id, direction, from_email, from_name, to_list, cc_list, body_text, sent_at, created_at'
        . (pg_schema_has('ws_mail_items', 'send_state') ? ', send_state' : '');
    $rows = (array) db_items("SELECT " . $columns . " FROM ws_mail_items
        WHERE conversation_id = '" . (int) $conversation['id'] . "'
        ORDER BY id DESC
        LIMIT " . PG_SUPPORT_REQUEST_PAGE_ITEMS);
    $email = (string) $conversation['customer_email'];

    return array_values(array_filter(array_reverse($rows), function ($item) use ($email) {
        return pg_support_request_item_visible($item, $email);
    }));
}

/**
 * A request's state in the customer's words, with the panel pill's tone
 * and the site badge's (pure).
 *
 * @param string $status ws_mail_conversations.status
 * @return array state, label, tone, site_tone
 */
function pg_support_request_state($status)
{
    $states = array(
        'open'    => array(lang('Open'), 'teal', 'success'),
        'waiting' => array(lang('Waiting for your answer'), 'amber', 'warning'),
        'closed'  => array(lang('Closed'), 'gray', 'secondary'),
    );
    $status = isset($states[(string) $status]) ? (string) $status : 'open';

    return array('state' => $status, 'label' => $states[$status][0], 'tone' => $states[$status][1], 'site_tone' => $states[$status][2]);
}

/**
 * The number the customer sees, as the answers' subjects carry it (pure).
 *
 * @param int $conversation_id
 * @return string
 */
function pg_support_request_number($conversation_id)
{
    return '#T' . (int) $conversation_id;
}

/**
 * The signed-in member's requests, last written first: conversations tied
 * to the user of support boxes answered by e-mail.
 *
 * @param int $user_id
 * @return array ws_mail_conversations rows
 */
function pg_support_request_account_rows($user_id)
{
    if (((int) $user_id <= 0) || !pg_schema_has('ws_mail_conversations', 'user_id')) {
        return array();
    }

    // Most members never wrote: one cheap read before the module loads.
    if (!db_value("SELECT id FROM ws_mail_conversations WHERE user_id = '" . (int) $user_id . "' LIMIT 1") || !pg_support_request_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT c.id, c.box_id, c.subject, c.status, c.created_at, c.last_in_at, c.last_out_at, b.transport, b.settings
        FROM ws_mail_conversations AS c
        JOIN ws_mail_boxes AS b ON b.id = c.box_id
        WHERE c.user_id = '" . (int) $user_id . "'
        ORDER BY GREATEST(c.last_in_at, c.last_out_at, c.created_at) DESC, c.id DESC
        LIMIT " . (PG_SUPPORT_REQUEST_ACCOUNT_LIMIT * 2));

    return array_slice(array_values(array_filter($rows, 'pg_support_request_box_shown')), 0, PG_SUPPORT_REQUEST_ACCOUNT_LIMIT);
}

/**
 * The My Account view of those requests (pure): number, subject, state,
 * the last answer's date and the signed link.
 *
 * @param array  $rows        pg_support_request_account_rows()
 * @param string $key
 * @param string $base        the page's address, '…/support_request.php'
 * @param string $date_format
 * @return array rows of id, number, subject, state, label, tone, last_answer, url
 */
function pg_support_request_account_view(array $rows, $key, $base, $date_format)
{
    $view = array();

    foreach ($rows as $row) {
        $token = pg_support_request_token((int) $row['id'], (int) $row['created_at'], $key);

        if ($token === '') {
            continue;
        }

        $state = pg_support_request_state((string) $row['status']);
        $subject = trim((string) $row['subject']);

        $view[] = array(
            'id'          => (int) $row['id'],
            'number'      => pg_support_request_number((int) $row['id']),
            'subject'     => ($subject !== '') ? $subject : lang('(no subject)'),
            'state'       => $state['state'],
            'label'       => $state['label'],
            'tone'        => $state['site_tone'],
            'last_answer' => ((int) $row['last_out_at'] > 0) ? date($date_format, (int) $row['last_out_at']) : '',
            'url'         => $base . '?c=' . (int) $row['id'] . '&t=' . $token,
        );
    }

    return $view;
}

/**
 * What the Reply box sent, checked (pure).
 *
 * @param mixed $text
 * @return array text, error ('' when it may go)
 */
function pg_support_request_reply_check($text)
{
    if (!is_string($text)) {
        return array('text' => '', 'error' => lang('Write your answer.'));
    }

    $text = trim(str_replace("\r\n", "\n", $text));

    if ($text === '') {
        return array('text' => '', 'error' => lang('Write your answer.'));
    }

    if (mb_strlen($text) > PG_SUPPORT_REQUEST_REPLY_MAX) {
        return array('text' => $text, 'error' => lang(array('string' => 'Your answer is longer than {var:1} characters.', 'vars' => array(PG_SUPPORT_REQUEST_REPLY_MAX))));
    }

    return array('text' => $text, 'error' => '');
}

/**
 * The mail the customer's answer is taken in as (pure but for the
 * Workspace's subject rule): from the customer to the box, the request's
 * subject with its [#T<id>] tag, so the intake threads it back.
 *
 * @param array  $conversation
 * @param array  $box
 * @param string $text
 * @param string $message_id
 * @param int    $date
 * @return string raw RFC 822 mail
 */
function pg_support_request_reply_raw($conversation, $box, $text, $message_id, $date)
{
    return pg_support_form_mail_raw(array(
        'from_email' => (string) $conversation['customer_email'],
        'from_name'  => (string) $conversation['customer_name'],
        'to'         => (string) $box['address'],
        'subject'    => ws_mail_reply_subject(preg_replace('/[\r\n\t]+/', ' ', (string) $conversation['subject']), (int) $conversation['id']),
        'text'       => (string) $text,
        'message_id' => (string) $message_id,
        'date'       => (int) $date,
    ));
}

/**
 * Hands the customer's answer to the box's intake, as a mail of theirs.
 *
 * @param array  $conversation
 * @param array  $box
 * @param string $text checked by pg_support_request_reply_check()
 * @return bool taken in
 */
function pg_support_request_reply($conversation, $box, $text)
{
    $host = function_exists('pg_mail_hostname') ? pg_mail_hostname() : '';

    if ($host === '') {
        $host = (string) substr(strrchr((string) $box['address'], '@'), 1);
    }

    $raw = pg_support_request_reply_raw($conversation, $box, $text, 'pg-request-' . (int) $conversation['id'] . '-' . bin2hex(random_bytes(8)) . '@' . $host, time());

    try {
        $result = ws_mail_ingest($raw, $box);
    } catch (Throwable $error) {
        $result = array('status' => 'refused', 'reason' => 'error');
    }

    if (($result['status'] ?? '') !== 'stored') {
        log_activity(pg_log_lang(array('string' => 'support request ({var:1}) answer on its page not taken in: {var:2}',
            'vars' => array(pg_support_request_number((int) $conversation['id']), trim(($result['status'] ?? '') . ' ' . ($result['reason'] ?? ''))))));

        return false;
    }

    return true;
}

/**
 * The request's page (look A, light and dark): number, subject and state,
 * the mails oldest first, then the Reply box. Pure.
 *
 * @param array $conversation ws_mail_conversations row
 * @param array $items        pg_support_request_items()
 * @param array $form         action, token_field, text, error, sent, date_format
 * @return string HTML
 */
function pg_support_request_page_html(array $conversation, array $items, array $form)
{
    $state = pg_support_request_state((string) ($conversation['status'] ?? 'open'));
    $subject = trim((string) ($conversation['subject'] ?? ''));

    $html = '<main class="container py-7 md:py-12 pg-request" style="max-width: 44rem;">'
        . '<div class="mb-7"><h1 class="h4 mb-3"><i class="bi bi-life-preserver me-3" aria-hidden="true"></i>' . h(($subject !== '') ? $subject : lang('(no subject)')) . '</h1>'
        . '<div class="d-flex flex-wrap align-items-center gap-3"><span class="pg-pill pg-tone-' . $state['tone'] . '">' . h($state['label']) . '</span>'
        . '<span class="pg-text-meta">' . h(lang(array('string' => 'Request {var:1}', 'vars' => array(pg_support_request_number((int) ($conversation['id'] ?? 0)))))) . '</span></div></div>';

    if (!empty($form['sent'])) {
        $html .= '<div class="pg-result mb-7" role="status"><span class="pg-icon-tile pg-tone-teal"><i class="bi bi-check-lg" aria-hidden="true"></i></span>'
            . '<div><div class="pg-result-title">' . h(lang('Your answer was sent.')) . '</div>'
            . '<div class="pg-result-line">' . h(lang('We will write to you by e-mail too.')) . '</div></div></div>';
    }

    foreach ($items as $item) {
        $mine = ((string) $item['direction'] === 'in');
        $when = ((int) $item['sent_at'] > 0) ? (int) $item['sent_at'] : (int) $item['created_at'];
        $name = $mine ? lang('You') : trim((string) $item['from_name']);
        $html .= '<article class="pg-card mb-5' . ($mine ? ' pg-request-mine' : '') . '"><div class="p-7">'
            . '<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><span class="fw-semibold">' . h(($name !== '') ? $name : lang('Answer')) . '</span>'
            . '<span class="pg-text-meta">' . h(($when > 0) ? date((string) $form['date_format'], $when) : '') . '</span></div>'
            . '<div class="pg-text-2 pg-request-text">' . nl2br(h(trim((string) $item['body_text']))) . '</div>'
            . '</div></article>';
    }

    $error = (string) ($form['error'] ?? '');
    $html .= '<form method="post" action="' . h((string) $form['action']) . '" class="pg-card mt-7"><div class="p-7">'
        . (string) ($form['token_field'] ?? '')
        . '<label class="pg-field-label" for="pg_request_reply">' . h(lang('Your answer')) . '</label>'
        . '<textarea class="pg-input" id="pg_request_reply" name="reply" rows="6" maxlength="' . PG_SUPPORT_REQUEST_REPLY_MAX . '" required'
        . (($error !== '') ? ' aria-invalid="true" aria-describedby="pg_request_reply_help"' : '') . '>' . h((string) ($form['text'] ?? '')) . '</textarea>'
        . (($error !== '') ? '<div class="pg-field-help pg-field-error" id="pg_request_reply_help">' . h($error) . '</div>'
            : (($state['state'] === 'closed') ? '<div class="pg-field-help">' . h(lang('Writing again opens this request again.')) . '</div>' : ''))
        . '<div class="mt-5"><button type="submit" class="pg-button pg-button-fill"><i class="bi bi-send me-3" aria-hidden="true"></i>' . h(lang('Send')) . '</button></div>'
        . '</div></form>'
        . '<p class="pg-text-meta mt-7">' . h(lang('Keep this link to yourself: whoever holds it can read and answer this request.')) . '</p>';

    return $html . '</main>';
}

/**
 * The page's own rules: a mail's text keeps its lines and wraps long words,
 * the customer's own mails sit a little in.
 *
 * @return string a <style> element
 */
function pg_support_request_page_style()
{
    return '<style>.pg-request-text{overflow-wrap:anywhere}.pg-request-mine{margin-inline-start:2rem}'
        . '.pg-request textarea.pg-input{width:100%;min-height:8rem}</style>';
}
