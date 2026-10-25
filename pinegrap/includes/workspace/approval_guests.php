<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - approval requests answered from outside the team: a customer,
 * or anybody else with no account on the site, asked by e-mail.
 *
 * Staff (roles 0-2) name them on a request - a name and an address - beside
 * the people of the channel or instead of them. Each one gets a link of
 * their own by e-mail (the outgoing mail queue): a random token is the
 * credential, kept only as its SHA-256 (ws_approval_guests.token_hash), so
 * neither the database nor a backup of it opens anything. The link leads to
 * a page of its own (workspace_approval.php) that has no session and shows
 * nothing of the panel: the title and the details of the request, who asks
 * and until when, and Approve or Reject with a note; a refusal says why.
 * Opening the page decides nothing, so a mail scanner that fetches the
 * address spends nothing; the answer is a POST, and it spends the link: one
 * answer per person, and the first one counts.
 *
 * A link lasts until the request's deadline, or WS_APPROVAL_GUEST_DAYS when
 * it has none, and never longer than WS_APPROVAL_GUEST_LONGEST. While the
 * person has not answered, staff can send a new link, which replaces the
 * old one.
 *
 * An answer counts as a team member's does (ws_approval_outcome()): a
 * refusal settles the request at once, "any" is met by the first approval,
 * "all" by everybody's. It is kept with where it came from (address and
 * browser) and through which link (the token's first characters), on the
 * row and in the site log. The person who asked is told; a settled request
 * is written into the channel, and onto the task it is about as a note.
 *
 * What the outsider writes is never read as the panel's: tags and markup are
 * taken out of the note, and the note that lands on a task never calls an
 * assistant.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most people from outside the team one request asks. */
define('WS_APPROVAL_MAX_GUESTS', 5);

/** How long a link lasts when the request has no deadline, in days. */
define('WS_APPROVAL_GUEST_DAYS', 30);

/** The longest a link lasts, whatever the deadline, in seconds. */
define('WS_APPROVAL_GUEST_LONGEST', 90 * 86400);

/** The longest note an outsider leaves with an answer. */
define('WS_APPROVAL_GUEST_NOTE_MAX', 500);

/**
 * Is the table there (2026.4.10, 10.84)?
 *
 * @return bool
 */
function ws_approval_guests_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_approvals_ready() && ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_approval_guests'") === 1);
    }

    return $ready;
}

/**
 * May this person ask somebody outside the team? Staff only, as for talking
 * with a guest: the site sends the e-mail in its own name.
 *
 * @param array $viewer
 * @return bool
 */
function ws_approval_can_ask_guests($viewer)
{
    return ws_approval_guests_ready() && !empty($viewer['member']) && ((int) ($viewer['role'] ?? 3) <= 2)
        && ((int) ($viewer['guest_id'] ?? 0) === 0);
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The people from outside the team as a form or the API sends them, made fit
 * to keep: a name on one line of at most 60 characters, an address in lower
 * case, each address once. A row with neither is a row of the form left
 * empty and is dropped. Pure.
 *
 * @param array $given list of [name, email]
 * @param int   $max
 * @return array guests (list of [name, email]), problem: '' | name | email | twice | many
 */
function ws_approval_guests_clean($given, $max = WS_APPROVAL_MAX_GUESTS)
{
    $guests = array();
    $seen = array();
    $fail = function ($problem) {
        return array('guests' => array(), 'problem' => $problem);
    };

    foreach ((array) $given as $item) {
        if (!is_array($item)) {
            return $fail('email');
        }

        $name = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($item['name'] ?? ''));
        $name = trim(preg_replace('/\s+/u', ' ', str_replace(array('<', '>', '*', '"'), '', (string) $name)));
        $email = strtolower(trim((string) ($item['email'] ?? '')));

        if (($name === '') && ($email === '')) {
            continue;
        }

        if (($email === '') || (strlen($email) > 255) || (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return $fail('email');
        }

        if ($name === '') {
            return $fail('name');
        }

        if (isset($seen[$email])) {
            return $fail('twice');
        }

        $seen[$email] = true;
        $guests[] = array('name' => mb_substr($name, 0, 60), 'email' => $email);
    }

    if (count($guests) > (int) $max) {
        return $fail('many');
    }

    return array('guests' => $guests, 'problem' => '');
}

/**
 * Until when a link given now opens: the request's deadline, or
 * WS_APPROVAL_GUEST_DAYS from now without one; never longer than
 * WS_APPROVAL_GUEST_LONGEST. Pure.
 *
 * @param int $now
 * @param int $closes_at 0 = no deadline
 * @return int
 */
function ws_approval_guest_expiry($now, $closes_at)
{
    $now = (int) $now;
    $closes_at = (int) $closes_at;

    if ($closes_at > $now) {
        return min($closes_at, $now + WS_APPROVAL_GUEST_LONGEST);
    }

    return $now + (WS_APPROVAL_GUEST_DAYS * 86400);
}

/**
 * An outsider's note as it is kept: one line, no tags, nothing that reads
 * as markup of the panel, at most WS_APPROVAL_GUEST_NOTE_MAX characters.
 * Pure.
 *
 * @param string $note
 * @return string
 */
function ws_approval_guest_note_clean($note)
{
    $note = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $note);

    // Tags (<@user:1>, <#order:5>, <!…>) are the panel's: an outsider writes none.
    $note = preg_replace('/<[@#!][a-z_]*:?\d*>/i', '', (string) $note);
    $note = str_replace(array('<', '>'), array('‹', '›'), (string) $note);
    $note = trim(preg_replace('/\s+/u', ' ', (string) $note));

    return mb_substr($note, 0, WS_APPROVAL_GUEST_NOTE_MAX);
}

/**
 * What a link opens to: the form while it may still be answered; the
 * answer once given; the end once the request is settled; a note that the
 * link ran out. Pure.
 *
 * @param array $guest    ws_approval_guests row
 * @param array $approval ws_approvals row
 * @param int   $now
 * @return string open | answered | closed | expired
 */
function ws_approval_guest_state($guest, $approval, $now)
{
    if ((string) $guest['decision'] !== 'pending') {
        return 'answered';
    }

    if (((int) $approval['closed_at'] > 0) || (((int) $approval['closes_at'] > 0) && ((int) $approval['closes_at'] <= (int) $now))) {
        return 'closed';
    }

    if ((int) $guest['expires_at'] <= (int) $now) {
        return 'expired';
    }

    return 'open';
}

/**
 * Could this be a token of a link? Pure.
 *
 * @param string $token
 * @return bool
 */
function ws_approval_guest_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

/* ---------------------------------------------------------------------------
   Rows and links
   --------------------------------------------------------------------------- */

/**
 * The people outside the team one request asked, in the order they were
 * named.
 *
 * @param int $approval_id
 * @return array[]
 */
function ws_approval_guests_of($approval_id)
{
    if (!ws_approval_guests_ready()) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_approval_guests WHERE approval_id = '" . (int) $approval_id . "' ORDER BY id");
}

/**
 * The address a token opens.
 *
 * @param string $token
 * @return string
 */
function ws_approval_guest_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_approval.php?t=' . $token;
}

/**
 * The row a token opens, if any.
 *
 * @param string $token
 * @return array|null
 */
function ws_approval_guest_for_token($token)
{
    if (!ws_approval_guests_ready() || !ws_approval_guest_token_shape($token)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_approval_guests WHERE token_hash = '" . hash('sha256', (string) $token) . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The details of a request as the outsider reads them: the message without
 * the title line it starts with, a person by name, a record by its kind
 * alone, nothing that leads into the panel (ws_guest_render()).
 *
 * @param string $body the request's message
 * @return string HTML, '' without details
 */
function ws_approval_guest_details_html($body)
{
    $text = trim((string) preg_replace('/^\*\*[^\n]*\*\*\n*/u', '', (string) $body));

    return ($text === '') ? '' : ws_guest_render($text);
}

/**
 * The same details as plain text, for the e-mail: a list's items and a
 * quote's lines on lines of their own ("- item", "1. item", "> line"). A
 * line break is one line break: nl2br() leaves its own newline after each
 * <br>, which used to double every break of the mail.
 *
 * @param string $body
 * @return string
 */
function ws_approval_guest_details_text($body)
{
    $html = ws_approval_guest_details_html($body);

    if ($html === '') {
        return '';
    }

    $text = preg_replace('#<br\s*/?>\n?#', "\n", ws_html_blocks_text($html));

    return trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));
}

/**
 * Names one more person outside the team on a request and sends them their
 * link.
 *
 * @param array $sender   the member asking
 * @param array $approval ws_approvals row
 * @param array $guest    name, email (ws_approval_guests_clean())
 * @return array ok, error, guest_id, sent
 */
function ws_approval_guest_add($sender, $approval, $guest)
{
    $now = time();
    $token = pg_short_link_new_token();
    $expires = ws_approval_guest_expiry($now, (int) $approval['closes_at']);

    db("INSERT INTO ws_approval_guests (approval_id, name, email, token_hash, token_hint, expires_at, sent_at, sent_by)
        VALUES ('" . (int) $approval['id'] . "', '" . e((string) $guest['name']) . "', '" . e((string) $guest['email']) . "',
            '" . hash('sha256', $token) . "', '" . e(substr($token, 0, 6)) . "', '" . $expires . "', '" . $now . "', '" . (int) $sender['id'] . "')");

    $guest_id = (int) mysqli_insert_id(db::$con);

    if ($guest_id <= 0) {
        return array('ok' => false, 'error' => lang('The approval request could not be sent.'), 'guest_id' => 0, 'sent' => false);
    }

    $sent = ws_approval_guest_mail($approval, array('name' => (string) $guest['name'], 'email' => (string) $guest['email']), ws_approval_guest_url($token), $expires);

    log_activity(lang(array('string' => 'a workspace approval link was sent to {var:1} ({var:2}): {var:3}', 'vars' => array((string) $guest['name'], (string) $guest['email'], (string) $approval['title']))));

    return array('ok' => true, 'error' => '', 'guest_id' => $guest_id, 'sent' => $sent);
}

/**
 * Sends a person who has not answered a new link; the one before stops
 * opening. Staff only, while the request is open.
 *
 * @param array $viewer
 * @param array $approval
 * @param int   $guest_id
 * @return array ok, error
 */
function ws_approval_guest_resend($viewer, $approval, $guest_id)
{
    if (!ws_approval_can_ask_guests($viewer)) {
        return array('ok' => false, 'error' => lang('Only staff can ask people outside the team to approve.'));
    }

    if ((int) $approval['closed_at'] > 0) {
        return array('ok' => false, 'error' => lang('The approval request is closed.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($approval['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $guest = db_item("SELECT * FROM ws_approval_guests WHERE id = '" . (int) $guest_id . "' AND approval_id = '" . (int) $approval['id'] . "'");

    if (!is_array($guest)) {
        return array('ok' => false, 'error' => lang('That approval request could not be found.'));
    }

    if ((string) $guest['decision'] !== 'pending') {
        return array('ok' => false, 'error' => lang('They have already answered.'));
    }

    $now = time();
    $token = pg_short_link_new_token();
    $expires = ws_approval_guest_expiry($now, (int) $approval['closes_at']);

    // Pending first: an answer that came in meanwhile is not undone by a new link.
    db("UPDATE ws_approval_guests SET token_hash = '" . hash('sha256', $token) . "', token_hint = '" . e(substr($token, 0, 6)) . "',
            expires_at = '" . $expires . "', sent_at = '" . $now . "', sent_by = '" . (int) $viewer['id'] . "'
        WHERE id = '" . (int) $guest['id'] . "' AND decision = 'pending'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('They have already answered.'));
    }

    ws_approval_guest_mail($approval, $guest, ws_approval_guest_url($token), $expires);
    ws_message_touch($approval['message_id']);

    log_activity(lang(array('string' => 'a new workspace approval link was sent to {var:1} ({var:2}): {var:3}', 'vars' => array((string) $guest['name'], (string) $guest['email'], (string) $approval['title']))));

    return array('ok' => true, 'error' => '');
}

/**
 * The e-mail that carries a link, in the site's name, through the outgoing
 * mail queue. An answer by e-mail goes to the person who asked.
 *
 * @param array  $approval
 * @param array  $guest   name, email
 * @param string $url
 * @param int    $expires
 * @return bool handed over
 */
function ws_approval_guest_mail($approval, $guest, $url, $expires)
{
    $message = ws_message($approval['message_id']);
    $details = $message ? ws_approval_guest_details_text($message['body']) : '';
    $site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
    $asker = (array) pg_load_user_row((int) $approval['created_by']);
    $asker_name = ws_person_name($approval['created_by']);

    $lines = array(
        lang(array('string' => 'Hello {var:1},', 'vars' => (string) $guest['name'])),
        ($site !== '')
            ? lang(array('string' => '{var:1} of {var:2} asks you to approve:', 'vars' => array($asker_name, $site)))
            : lang(array('string' => '{var:1} asks you to approve:', 'vars' => $asker_name)),
        (string) $approval['title'] . (($details !== '') ? "\n\n" . $details : ''),
        lang('To approve or reject it, open this link:') . "\n" . $url,
        lang(array('string' => 'The link is yours alone and takes one answer. It works until {var:1}; no account is needed.', 'vars' => date('d.m.Y H:i', (int) $expires))),
    );

    $properties = array(
        'to'                 => (string) $guest['email'],
        'to_name'            => (string) $guest['name'],
        'from_name'          => $site,
        'from_email_address' => defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '',
        'subject'            => lang(array('string' => 'Approval requested: {var:1}', 'vars' => (string) $approval['title'])),
        'format'             => 'plain_text',
        'body'               => implode("\n\n", $lines) . "\n",
        'queue'              => true,
    );

    if (filter_var((string) ($asker['email_address'] ?? ''), FILTER_VALIDATE_EMAIL) !== false) {
        $properties['reply_to'] = (string) $asker['email_address'];
    }

    $ok = (email($properties) !== false);

    if (!$ok) {
        log_activity(lang(array('string' => 'The approval link for {var:1} could not be sent.', 'vars' => (string) $guest['email'])));
    }

    return $ok;
}

/* ---------------------------------------------------------------------------
   The page behind the link (workspace_approval.php)
   --------------------------------------------------------------------------- */

/**
 * What the page shows for a token.
 *
 * @param string $token
 * @param bool   $count a token that opens nothing counts against the address
 *                      (false when the same request was counted already)
 * @return array state (invalid | open | answered | closed | expired), and for
 *               a valid link: title, details, asker, closes, expires, name,
 *               decision, note, decided, outcome
 */
function ws_approval_guest_view($token, $count = true)
{
    $invalid = array('state' => 'invalid');

    if (!ws_enabled()) {
        return $invalid;
    }

    $guest = ws_approval_guest_for_token($token);
    $approval = $guest ? ws_approval($guest['approval_id']) : null;
    $message = $approval ? ws_message($approval['message_id']) : null;

    // A token that opens no row counts against the address, the way a wrong
    // guest link does; a request deleted since is nobody's fault.
    if (!$guest) {
        if ($count && ((string) $token !== '')) {
            ws_guest_offence();
        }

        return $invalid;
    }

    if (!$approval || !$message || ((int) $message['deleted_at'] > 0)) {
        return $invalid;
    }

    // A deadline that passed is settled before the page says where it stands.
    if (((int) $approval['closed_at'] === 0) && ((int) $approval['closes_at'] > 0) && ((int) $approval['closes_at'] <= time())) {
        ws_approval_evaluate($approval);
        $approval = ws_approval($approval['id']);
    }

    $outcomes = array(
        'approved'  => lang('Approved'),
        'rejected'  => lang('Rejected'),
        'expired'   => lang('Expired'),
        'withdrawn' => lang('Closed without a decision'),
    );

    return array(
        'state'    => ws_approval_guest_state($guest, $approval, time()),
        'title'    => (string) $approval['title'],
        'details'  => ws_approval_guest_details_html($message['body']),
        'asker'    => ws_person_name($approval['created_by']),
        'closes'   => ((int) $approval['closes_at'] > 0) ? date('d.m.Y H:i', (int) $approval['closes_at']) : '',
        'expires'  => date('d.m.Y H:i', (int) $guest['expires_at']),
        'name'     => (string) $guest['name'],
        'decision' => (string) $guest['decision'],
        'note'     => (string) $guest['note'],
        'decided'  => ((int) $guest['decided_at'] > 0) ? date('d.m.Y H:i', (int) $guest['decided_at']) : '',
        'outcome'  => $outcomes[(string) $approval['outcome']] ?? '',
    );
}

/**
 * The answer that comes through a link. The link is spent by it: the row
 * moves from pending once, the first answer is the one that counts.
 *
 * @param string $token
 * @param string $decision approve | reject
 * @param string $note     a refusal needs one
 * @return array ok, error, field
 */
function ws_approval_guest_decide($token, $decision, $note)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    $view = ws_approval_guest_view($token);

    if ($view['state'] === 'invalid') {
        return $fail(lang('This link is not valid.'));
    }

    if ($view['state'] === 'answered') {
        return $fail(lang('You have already answered.'));
    }

    if ($view['state'] === 'closed') {
        return $fail(lang('The approval request is closed.'));
    }

    if ($view['state'] === 'expired') {
        return $fail(lang('This link is no longer valid.'));
    }

    if (!in_array($decision, array('approve', 'reject'), true)) {
        return $fail(lang('Invalid request.'));
    }

    $note = ws_approval_guest_note_clean($note);

    if (($decision === 'reject') && ($note === '')) {
        return $fail(lang('Please say why you reject it.'), 'note');
    }

    $guest = ws_approval_guest_for_token($token);
    $approval = ws_approval($guest['approval_id']);
    $now = time();
    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

    // Pending, this very link and still open: two answers sent together count once.
    db("UPDATE ws_approval_guests SET decision = '" . (($decision === 'approve') ? 'approved' : 'rejected') . "',
            note = '" . e($note) . "', decided_at = '" . $now . "', decided_ip = '" . e(mb_substr($ip, 0, 45)) . "',
            decided_agent = '" . e($agent) . "', decided_hint = token_hint
        WHERE id = '" . (int) $guest['id'] . "' AND decision = 'pending'
            AND token_hash = '" . hash('sha256', (string) $token) . "' AND expires_at > '" . $now . "'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return $fail(lang('You have already answered.'));
    }

    $vars = array((string) $approval['title'], (string) $guest['name'], (string) $guest['email'], (string) $guest['token_hint']);

    log_activity(($decision === 'approve')
        ? lang(array('string' => 'workspace approval request "{var:1}" was approved by {var:2} ({var:3}) from outside the team, through the link {var:4}…', 'vars' => $vars))
        : lang(array('string' => 'workspace approval request "{var:1}" was rejected by {var:2} ({var:3}) from outside the team, through the link {var:4}…', 'vars' => $vars)));

    ws_message_touch($approval['message_id']);

    // The person who asked hears of an answer that did not settle it; a
    // settled request tells everybody concerned (ws_approval_settle()).
    if (ws_approval_evaluate($approval) === 'open') {
        ws_notify((int) $approval['created_by'], 'approval_answered', array('channel_id' => (int) $approval['channel_id'], 'message_id' => (int) $approval['message_id'], 'actor_id' => 0));
    }

    return array('ok' => true, 'error' => '', 'field' => '');
}

/* ---------------------------------------------------------------------------
   Tasks
   --------------------------------------------------------------------------- */

/**
 * The approval requests about a task, for its drawer: newest first, only the
 * ones in channels the reader may read; and whether the reader may ask a
 * customer from here. null when the task cannot have one: it is in no
 * channel, or the table is not there yet.
 *
 * @param array $viewer
 * @param array $task
 * @return array|null can_ask, channel, items
 */
function ws_task_approvals_detail($viewer, $task)
{
    if (!ws_approval_guests_ready() || ((int) $task['channel_id'] <= 0)) {
        return null;
    }

    $channel = ws_channel($task['channel_id']);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        return null;
    }

    $rows = (array) db_items("SELECT a.id, a.message_id, a.channel_id FROM ws_approvals a
        INNER JOIN ws_messages m ON m.id = a.message_id AND m.deleted_at = 0
        WHERE a.record_type = 'task' AND a.record_id = '" . (int) $task['id'] . "'
        ORDER BY a.id DESC LIMIT 10");

    $message_ids = array();

    foreach ($rows as $row) {
        $other = ((int) $row['channel_id'] === (int) $channel['id']) ? $channel : ws_channel($row['channel_id']);

        if ($other && ws_can_read_channel($viewer, $other)) {
            $message_ids[] = (int) $row['message_id'];
        }
    }

    $cards = ws_approvals_map($message_ids, $viewer);
    $labels = array(
        'approved'  => lang('Approved'),
        'rejected'  => lang('Rejected'),
        'expired'   => lang('Expired'),
        'withdrawn' => lang('Closed without a decision'),
    );
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $items = array();

    foreach ($rows as $row) {
        $card = $cards[(int) $row['message_id']] ?? null;

        if (!$card) {
            continue;
        }

        $items[] = array(
            'id'      => (int) $card['id'],
            'title'   => (string) $card['title'],
            'open'    => !empty($card['open']),
            'outcome' => (string) $card['outcome'],
            'state'   => $card['open'] ? lang('Waiting') : ($labels[(string) $card['outcome']] ?? ''),
            'closes'  => (string) $card['closes'],
            'people'  => $card['people'],
            'url'     => $base . '?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
        );
    }

    return array(
        'can_ask' => ws_approval_can_ask_guests($viewer) && ws_can_post_channel($viewer, $channel),
        'channel' => (string) $channel['name'],
        'items'   => $items,
    );
}

/**
 * Asks a customer to approve a task: a request in the task's channel, about
 * the task, with the customer as the one asked.
 *
 * @param array $viewer
 * @param array $task
 * @param array $data title, text, guests (list of [name, email]), closes_at
 * @return array ok, error, field, approval_id
 */
function ws_task_approval_create($viewer, $task, $data)
{
    if (!ws_can_see_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('That task could not be found.'), 'field' => '', 'approval_id' => 0);
    }

    $channel = ((int) $task['channel_id'] > 0) ? ws_channel($task['channel_id']) : null;

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('A customer is asked in the channel of the task: you cannot post in it.'), 'field' => '', 'approval_id' => 0);
    }

    $title = trim((string) ($data['title'] ?? ''));

    $result = ws_approval_create($viewer, $channel, array(
        'title'     => ($title !== '') ? $title : (string) $task['title'],
        'text'      => (string) ($data['text'] ?? ''),
        'approvers' => array(),
        'guests'    => (array) ($data['guests'] ?? array()),
        'rule'      => 'any',
        'closes_at' => (int) ($data['closes_at'] ?? 0),
        'task_id'   => (int) $task['id'],
    ));

    return array('ok' => $result['ok'], 'error' => $result['error'], 'field' => $result['field'], 'approval_id' => (int) $result['approval_id']);
}

/**
 * Writes how a request about a task ended onto the task, as a note in the
 * name of the person who asked. What it quotes may come from outside the
 * team, so the note never calls an assistant.
 *
 * @param array  $approval
 * @param string $body the sentence written into the channel
 */
function ws_approval_task_note($approval, $body)
{
    if (((string) $approval['record_type'] !== 'task') || ((int) $approval['record_id'] <= 0) || ((string) $body === '')
        || !function_exists('ws_task_note_add')) {
        return;
    }

    $task = ws_task((int) $approval['record_id']);
    $asker = ws_viewer((array) pg_load_user_row((int) $approval['created_by']));

    if (!$task || empty($asker['member']) || !ws_can_see_task($asker, $task)) {
        return;
    }

    ws_task_note_add($asker, $task, $body, 0, false);
}

/* ---------------------------------------------------------------------------
   Screen texts
   --------------------------------------------------------------------------- */

/**
 * The texts of the screens: the request form, the card and the task drawer.
 *
 * @return array key => text
 */
function ws_approval_guests_js_strings()
{
    return array(
        'apv_guests'        => lang('Customers outside the team'),
        'apv_guests_help'   => lang('Optional. Each one gets a link of their own by e-mail and answers with no account; no reminder is sent to them. When customers are asked, the people of the channel may be left out.'),
        'apv_guest_name'    => lang('Name'),
        'apv_guest_email'   => lang('E-mail'),
        'apv_guest_add'     => lang('Add a customer'),
        'apv_guest_remove'  => lang('Remove'),
        'apv_guest_badge'   => lang('customer'),
        'apv_guest_resend'  => lang('Send the link again'),
        'apv_guest_resent'  => lang('A new link was sent. The one before no longer opens.'),
        'tap_title'         => lang('Customer approval'),
        'tap_none'          => lang('Nobody has been asked to approve this task yet.'),
        'tap_ask'           => lang('Ask the customer to approve'),
        'tap_subject'       => lang('What is to be approved'),
        'tap_text'          => lang('Message to the customer'),
        'tap_text_help'     => lang('Optional. Links can be added here, for example to a draft or a design.'),
        'tap_deadline'      => lang('Deadline'),
        'tap_deadline_help' => lang(array('string' => 'Left empty, the link works for {var:1} days. The customer gets no reminder.', 'vars' => WS_APPROVAL_GUEST_DAYS)),
        'tap_help'          => lang('The customer gets a link by e-mail and approves or rejects without an account. The answer is written onto this task as a note and the request appears in the channel of the task.'),
        'tap_send'          => lang('Send'),
        'tap_sent'          => lang('The approval request was sent.'),
        'tap_open'          => lang('Open in the channel'),
    );
}
