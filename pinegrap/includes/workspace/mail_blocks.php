<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: a mailbox's block list. A sender's address or whole domain is
 * blocked for one box or, with box_id 0, for every box (ws_mail_blocks). A
 * mail from a blocked sender is kept by ws_mail_ingest() as an item with
 * blocked_at set and no conversation: its head and its .eml only, no card,
 * no files and no HTML, so the automatic answer, notices, flows and the
 * customer's line never run. The item and its .eml are swept together after
 * WS_MAIL_BLOCK_DAYS.
 *
 * Who may answer in the box blocks for that box; a block for every box, and
 * Allow again on the Mailboxes card, need the Workspace settings right. Each
 * change is a Site Log line. Phone numbers (WhatsApp, SMS) stay out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Days a blocked mail is kept before the sweep removes it with its .eml. */
define('WS_MAIL_BLOCK_DAYS', 30);

/** The most blocks the Mailboxes card lists. */
define('WS_MAIL_BLOCK_LIST_MAX', 500);

/**
 * Whether the block list's table and column are there (step 11.0.200).
 *
 * @return bool
 */
function ws_mail_blocks_ready()
{
    return pg_schema_has('ws_mail_blocks', 'value') && pg_schema_has('ws_mail_items', 'blocked_at');
}

/**
 * The value a block stores for an address: the address itself, or its
 * domain, in lower case. Pure.
 *
 * @param string $email
 * @param string $kind address | domain
 * @return string '' when the address has no usable domain
 */
function ws_mail_block_value($email, $kind)
{
    $email = mb_strtolower(trim((string) $email));
    $at = strrpos($email, '@');

    if (($at === false) || ($at === 0) || (strlen($email) > 255)) {
        return '';
    }

    $domain = trim(substr($email, $at + 1), '.');

    if (($domain === '') || (strpos($domain, '.') === false) || (substr_count($email, '@') !== 1) || preg_match('/[\s<>,;"]/', $email)) {
        return '';
    }

    return ($kind === 'domain') ? $domain : $email;
}

/**
 * The domains a sender's address falls under: its own and every parent of
 * at least two labels (a.mail.example.com, mail.example.com, example.com),
 * so a blocked domain holds its subdomains too. Pure.
 *
 * @param string $email
 * @return string[]
 */
function ws_mail_block_domains($email)
{
    $domain = ws_mail_block_value($email, 'domain');
    $out = array();

    if ($domain === '') {
        return $out;
    }

    $labels = explode('.', $domain);

    while (count($labels) >= 2) {
        $out[] = implode('.', $labels);
        array_shift($labels);
    }

    return $out;
}

/**
 * The block that holds a sender in a box, among the given rows. Pure.
 *
 * @param string  $email  the sender's address
 * @param int     $box_id
 * @param array[] $blocks ws_mail_blocks rows (box_id, kind, value)
 * @return array|null the first matching row
 */
function ws_mail_block_match($email, $box_id, $blocks)
{
    $address = ws_mail_block_value($email, 'address');

    if ($address === '') {
        return null;
    }

    $domains = ws_mail_block_domains($address);

    foreach ((array) $blocks as $block) {
        if (!in_array((int) $block['box_id'], array(0, (int) $box_id), true)) {
            continue;
        }

        $value = mb_strtolower((string) $block['value']);

        if ((((string) $block['kind'] === 'address') && ($value === $address))
            || (((string) $block['kind'] === 'domain') && in_array($value, $domains, true))) {
            return $block;
        }
    }

    return null;
}

/**
 * The block that holds a sender in a box, read from the list.
 *
 * @param int    $box_id
 * @param string $email
 * @return array|null
 */
function ws_mail_block_find($box_id, $email)
{
    if (!ws_mail_blocks_ready()) {
        return null;
    }

    $address = ws_mail_block_value($email, 'address');

    if ($address === '') {
        return null;
    }

    $domains = ws_mail_block_domains($address);
    $marks = implode(', ', array_fill(0, count($domains), '?'));
    $rows = db_q("SELECT id, box_id, kind, value FROM ws_mail_blocks
        WHERE box_id IN (0, ?) AND ((kind = 'address' AND value = ?) OR (kind = 'domain' AND value IN (" . $marks . ")))
        ORDER BY id LIMIT 20", array_merge(array((int) $box_id, $address), $domains));
    $blocks = array();

    if ($rows) {
        while ($row = mysqli_fetch_assoc($rows)) {
            $blocks[] = $row;
        }
    }

    return ws_mail_block_match($address, $box_id, $blocks);
}

/**
 * Keeps a blocked sender's mail: the item's head (sender, To, Cc, subject,
 * times) with blocked_at set and no conversation, and its .eml for "Not
 * blocked". No text, files or HTML are kept, and nothing is written in a
 * channel.
 *
 * @param string $raw
 * @param array  $box
 * @param array  $parsed pg_mail_parse()
 * @param string $hash   ws_mail_hash()
 * @param string $from_email
 * @param int    $now
 * @return array ws_mail_ingest()'s result: blocked (item_id), duplicate or refused
 */
function ws_mail_block_store($raw, $box, $parsed, $hash, $from_email, $now)
{
    $box_id = (int) $box['id'];
    $result = array('status' => 'refused', 'reason' => 'not_saved', 'item_id' => 0, 'conversation_id' => 0, 'message_id' => 0);
    $list = function ($addresses) {
        return implode(', ', array_map(function ($address) {
            return (string) $address['email'];
        }, (array) $addresses));
    };

    $written = db_q("INSERT INTO ws_mail_items (box_id, direction, rfc_message_id, rfc_message_hash, from_email, from_name, to_list, cc_list,
            subject, body_text, auth, sent_at, created_at, blocked_at)
        VALUES (?, 'in', ?, ?, ?, ?, ?, ?, ?, '', 'none', ?, ?, ?)", array(
        $box_id, mb_substr((string) $parsed['message_id'], 0, 255), $hash, $from_email, mb_substr((string) ($parsed['from']['name'] ?? ''), 0, 255),
        $list($parsed['to']), $list($parsed['cc']), mb_substr((string) $parsed['subject'], 0, 255), max(0, (int) $parsed['date']), $now, $now,
    ));

    $item_id = $written ? (int) mysqli_insert_id(db::$con) : 0;

    if ($item_id <= 0) {
        $existing = (int) db_value("SELECT id FROM ws_mail_items WHERE box_id = '" . $box_id . "' AND rfc_message_hash = '" . e($hash) . "'");

        return ($existing > 0) ? array_merge($result, array('status' => 'duplicate', 'reason' => '', 'item_id' => $existing)) : $result;
    }

    $raw_path = ws_mail_eml_store(ws_mail_eml_path($box_id, $hash, $now), $raw);

    if ($raw_path !== '') {
        db("UPDATE ws_mail_items SET raw_path = '" . e($raw_path) . "' WHERE id = '" . $item_id . "'");
    }

    return array_merge($result, array('status' => 'blocked', 'reason' => '', 'item_id' => $item_id));
}

/**
 * Removes blocked mails older than WS_MAIL_BLOCK_DAYS, row and .eml together.
 * Run by workspace_recurring_job.php before ws_mail_eml_sweep(), which leaves
 * blocked mails alone.
 *
 * @param int $limit
 * @return int how many were removed
 */
function ws_mail_block_sweep($limit = 500)
{
    if (!ws_mail_blocks_ready()) {
        return 0;
    }

    // created_at is indexed (idx_created); a blocked mail is written at once,
    // so its created_at is its blocked_at.
    $rows = db_items("SELECT id, raw_path FROM ws_mail_items
        WHERE created_at < '" . (time() - (WS_MAIL_BLOCK_DAYS * 86400)) . "' AND blocked_at > 0
        ORDER BY id
        LIMIT " . max(1, (int) $limit));

    $done = 0;

    foreach ((array) $rows as $row) {
        $relative = (string) $row['raw_path'];

        if ((strpos($relative, 'mail/') === 0) && (strpos($relative, '..') === false)) {
            @unlink(PG_FUNCTIONS_DIR . '/data/' . $relative);
        }

        db("DELETE FROM ws_mail_items WHERE id = '" . (int) $row['id'] . "' AND blocked_at > 0");
        $done++;
    }

    return $done;
}

/**
 * Block sender as posted: what to block and where. Pure.
 *
 * @param array $input kind (address | domain), scope (box | all)
 * @return array kind, scope
 */
function ws_mail_block_input($input)
{
    $kind = is_scalar($input['kind'] ?? null) ? (string) $input['kind'] : '';
    $scope = is_scalar($input['scope'] ?? null) ? (string) $input['scope'] : '';

    return array(
        'kind'  => ($kind === 'domain') ? 'domain' : 'address',
        'scope' => ($scope === 'all') ? 'all' : 'box',
    );
}

/**
 * Whether a person may block a mail's sender, and what would be stored.
 * This box asks who may answer in the conversation's place
 * (ws_mail_can_answer()); every box asks the Workspace settings right. Pure
 * but for the words.
 *
 * @param array      $item      ws_mail_items row
 * @param array|null $box       ws_mail_boxes row
 * @param bool       $can_answer
 * @param bool       $settings  the Workspace settings right
 * @param array      $input     ws_mail_block_input()
 * @return array ok, error, box_id, kind, value
 */
function ws_mail_block_decide($item, $box, $can_answer, $settings, $input)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'box_id' => 0, 'kind' => '', 'value' => '');
    };

    if (!is_array($item) || !is_array($box) || ((string) $item['direction'] !== 'in') || ws_mail_box_is_phone($box)
        || ((int) ($item['user_id'] ?? 0) > 0) || ((int) ($item['blocked_at'] ?? 0) > 0)) {
        return $refuse(lang('This sender cannot be blocked.'));
    }

    if (!$can_answer) {
        return $refuse(lang('Only someone who may answer in this mailbox can block a sender.'));
    }

    if (($input['scope'] === 'all') && !$settings) {
        return $refuse(lang('Blocking a sender for every mailbox needs the Workspace settings right.'));
    }

    $value = ws_mail_block_value((string) $item['from_email'], $input['kind']);

    if ($value === '') {
        return $refuse(lang('This sender cannot be blocked.'));
    }

    return array(
        'ok'     => true,
        'error'  => '',
        'box_id' => ($input['scope'] === 'all') ? 0 : (int) $box['id'],
        'kind'   => $input['kind'],
        'value'  => $value,
    );
}

/**
 * The Site Log line of a block or of Allow again. Pure but for the words.
 *
 * @param bool   $added
 * @param string $kind     address | domain
 * @param string $value
 * @param string $box_name '' for every mailbox
 * @return string
 */
function ws_mail_block_log_line($added, $kind, $value, $box_name)
{
    $what = ($kind === 'domain') ? '@' . $value : $value;

    if ($box_name === '') {
        return $added
            ? lang(array('string' => 'the sender {var:1} was blocked for every mailbox', 'vars' => $what))
            : lang(array('string' => 'the sender {var:1} was allowed again for every mailbox', 'vars' => $what));
    }

    return $added
        ? lang(array('string' => 'the sender {var:1} was blocked in the mailbox {var:2}', 'vars' => array($what, $box_name)))
        : lang(array('string' => 'the sender {var:1} was allowed again in the mailbox {var:2}', 'vars' => array($what, $box_name)));
}

/**
 * Block sender, from a mail of the E-mails screen's pane.
 *
 * @param array $viewer
 * @param int   $item_id
 * @param array $input  kind, scope
 * @param string $username for the Site Log line
 * @return array ok, error, text (what happened, for the screen)
 */
function ws_mail_block_add($viewer, $item_id, $input, $username)
{
    if (!ws_mail_blocks_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'text' => '');
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "'");
    $conversation = ($item && ((int) $item['conversation_id'] > 0))
        ? db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $item['conversation_id'] . "'") : null;
    $channel = $conversation ? ws_channel((int) $conversation['channel_id']) : null;
    $box = $item ? ws_mail_box((int) $item['box_id']) : null;
    // Team accounts only: a guest of the channel blocks nothing.
    $can_answer = !empty($viewer['member']) && $channel && ws_can_read_channel($viewer, $channel) && ws_mail_can_answer($viewer, $channel);
    $decided = ws_mail_block_decide($item, $box, $can_answer, !empty($viewer['settings']), ws_mail_block_input($input));

    if (!$decided['ok']) {
        return array('ok' => false, 'error' => $decided['error'], 'text' => '');
    }

    $exists = (int) db_value("SELECT id FROM ws_mail_blocks WHERE box_id = '" . $decided['box_id'] . "' AND kind = '" . e($decided['kind']) . "'
        AND value = '" . e($decided['value']) . "'");

    if ($exists === 0) {
        db_q("INSERT INTO ws_mail_blocks (box_id, kind, value, created_by, created_at) VALUES (?, ?, ?, ?, ?)",
            array($decided['box_id'], $decided['kind'], $decided['value'], (int) $viewer['id'], time()));

        log_activity(ws_mail_block_log_line(true, $decided['kind'], $decided['value'], ($decided['box_id'] > 0) ? (string) $box['name'] : ''), $username);
    }

    return array('ok' => true, 'error' => '', 'text' => lang('The sender is blocked. Their next e-mails open no conversation and tell nobody.'));
}

/**
 * The block list, newest first, each with its mailbox's name ('' for every
 * mailbox).
 *
 * @return array[]
 */
function ws_mail_blocks_list()
{
    if (!ws_mail_blocks_ready()) {
        return array();
    }

    return (array) db_items("SELECT k.id, k.box_id, k.kind, k.value, k.created_at, COALESCE(b.name, '') AS box_name
        FROM ws_mail_blocks k LEFT JOIN ws_mail_boxes b ON b.id = k.box_id
        ORDER BY k.id DESC LIMIT " . WS_MAIL_BLOCK_LIST_MAX);
}

/**
 * Allow again, from the Mailboxes card: the block is removed and logged.
 * The card itself is behind the Workspace settings right.
 *
 * @param array  $viewer
 * @param int    $block_id
 * @param string $username
 * @return array ok, error
 */
function ws_mail_block_allow($viewer, $block_id, $username)
{
    if (!ws_mail_blocks_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'));
    }

    if (empty($viewer['settings'])) {
        return array('ok' => false, 'error' => lang('You do not have access to Workspace settings.'));
    }

    $block = db_item("SELECT k.*, COALESCE(b.name, '') AS box_name FROM ws_mail_blocks k LEFT JOIN ws_mail_boxes b ON b.id = k.box_id
        WHERE k.id = '" . (int) $block_id . "'");

    if (!$block) {
        return array('ok' => false, 'error' => lang('That block could not be found.'));
    }

    db("DELETE FROM ws_mail_blocks WHERE id = '" . (int) $block['id'] . "'");
    log_activity(ws_mail_block_log_line(false, (string) $block['kind'], (string) $block['value'], ((int) $block['box_id'] > 0) ? (string) $block['box_name'] : ''), $username);

    return array('ok' => true, 'error' => '');
}

/**
 * The block list on the Mailboxes card: each blocked address or domain, the
 * mailbox it holds for, and Allow again, a submit button of the card's form.
 * Pure but for the words.
 *
 * @param array[] $blocks ws_mail_blocks_list()
 * @param string  $form   the settings pane's form id
 * @return string HTML, '' without blocks
 */
function ws_mail_blocks_html($blocks, $form)
{
    if (!$blocks) {
        return '';
    }

    $rows = '';

    foreach ($blocks as $block) {
        $what = ((string) $block['kind'] === 'domain') ? '@' . (string) $block['value'] : (string) $block['value'];
        $where = ((int) $block['box_id'] === 0)
            ? lang('Every mailbox')
            : ((trim((string) $block['box_name']) !== '') ? (string) $block['box_name'] : lang('A mailbox that was deleted'));
        $kind = ((string) $block['kind'] === 'domain') ? lang('The whole domain') : lang('This address');

        $rows .= '<div class="pg-list-row"><span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><span class="bi bi-slash-circle"></span></span>'
            . '<div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title text-break">' . h($what) . '</span>'
            . '<span class="pg-list-row-time">' . h(date('d.m.Y', (int) $block['created_at'])) . '</span></div>'
            . '<div class="pg-list-row-text">' . h($kind) . ' · ' . h($where) . '</div>'
            . (($form !== '') ? '<div class="pg-list-row-actions"><button type="submit" name="ws_mail_allow" value="' . (int) $block['id'] . '" form="' . h($form) . '" class="pg-button pg-button-quiet pg-button-sm">'
                . h(lang('Allow again')) . '</button></div>' : '')
            . '</div></div>';
    }

    return '<div class="mt-7" id="pgset-ws-mail-blocks"><div class="pg-title-section">' . h(lang('Blocked senders')) . '</div>'
        . '<p class="pg-card-sub">' . h(lang('E-mail from these senders opens no conversation, tells nobody and is kept for 30 days.')) . '</p>'
        . '<div class="pg-look-rows">' . $rows . '</div></div>';
}

/**
 * Block sender offered on a pane's mails: an incoming mail of an e-mail box
 * (no delivery failure) when the person may answer there; every box only
 * with the Workspace settings right.
 *
 * @param array $pane   ws_mail_pane()
 * @param array $viewer
 * @return array the pane, each item with block: null or address, domain, every
 */
function ws_mail_block_pane($pane, $viewer)
{
    $box = ws_mail_box((int) $pane['conversation']['box_id']);
    $may = ws_mail_blocks_ready() && !empty($viewer['member']) && !empty($pane['can_post']) && is_array($box) && !ws_mail_box_is_phone($box);

    foreach ($pane['items'] as $index => $item) {
        $address = ws_mail_block_value((string) $item['from_email'], 'address');
        $offer = $may && ((string) $item['direction'] === 'in') && !ws_mail_item_is_bounce($item) && ($address !== '');

        $pane['items'][$index]['block'] = $offer ? array(
            'address' => $address,
            'domain'  => ws_mail_block_value($address, 'domain'),
            'every'   => !empty($viewer['settings']),
        ) : null;
    }

    return $pane;
}

/**
 * Block sender under a mail of the pane: the button and its form, opened in
 * place. The form posts ws_mail_block through the screen's script
 * (workspace_mail.js). Pure but for the words.
 *
 * @param array $item ws_mail_block_pane() item
 * @return string HTML, '' when not offered
 */
function ws_mail_block_item_html($item)
{
    if (empty($item['block'])) {
        return '';
    }

    $id = (int) $item['id'];
    $block = $item['block'];
    $seg = function ($name, $choices, $label) {
        $html = '<span class="pg-seg" role="group" aria-label="' . h($label) . '" data-ws-mail-block-choice="' . h($name) . '">';
        $first = true;

        foreach ($choices as $value => $text) {
            $html .= '<button type="button" class="pg-seg-item text-break" aria-pressed="' . ($first ? 'true' : 'false') . '" data-value="' . h($value) . '">' . h($text) . '</button>';
            $first = false;
        }

        return $html . '</span>';
    };
    $scopes = array('box' => lang('This mailbox'));

    if ($block['every']) {
        $scopes['all'] = lang('Every mailbox');
    }

    return '<button type="button" class="btn btn-sm btn-text theme-secondary px-0 ms-3" data-bs-toggle="collapse" data-bs-target="#ws-mail-block-' . $id . '"'
        . ' aria-expanded="false" aria-controls="ws-mail-block-' . $id . '"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>' . h(lang('Block sender')) . '</button>'
        . '<div class="collapse" id="ws-mail-block-' . $id . '"><form class="ws-mail-block pg-card-inner mt-2 p-3" data-ws-mail-block="' . $id . '">'
        . '<div class="pg-field-label">' . h(lang('Block')) . '</div>'
        . $seg('kind', array('address' => $block['address'], 'domain' => lang(array('string' => 'Everyone at {var:1}', 'vars' => $block['domain']))), lang('Block'))
        . '<div class="pg-field-label mt-3">' . h(lang('Where')) . '</div>'
        . $seg('scope', $scopes, lang('Where'))
        . '<div class="pg-field-help">' . h(lang('Their e-mail opens no conversation, tells nobody and gets no automatic answer. It is kept for 30 days.')) . '</div>'
        . '<div class="d-flex gap-2 mt-3"><button type="submit" class="pg-button pg-button-fill pg-button-sm">' . h(lang('Block sender')) . '</button>'
        . '<button type="button" class="pg-button pg-button-ghost pg-button-sm" data-bs-toggle="collapse" data-bs-target="#ws-mail-block-' . $id . '">' . h(lang('Cancel')) . '</button></div>'
        . '</form></div>';
}
