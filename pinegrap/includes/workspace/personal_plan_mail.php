<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Add to my calendar: a mail of a mailbox opens the New plan
 * drawer filled from it (its subject as the title, its day), and the plan
 * saved from it is tied to the mail's conversation. Nothing goes back to the
 * sender and nothing outside the person's own plan is written. The plan
 * itself is saved by personal_plans.php, which alone names the plan tables.
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
 * The mail a message offers to add to the reader's calendar: the mail's own
 * text, of a mail that is no delivery failure and no phone message, for a
 * team member, once the plan tables are there. Pure.
 *
 * @param array      $row    ws_messages row
 * @param array|null $item   ws_mail_items_map() entry
 * @param array      $viewer
 * @param bool       $ready  ws_personal_plans_ready()
 * @return int the mail's id, 0 for none
 */
function ws_personal_plan_mail_offer($row, $item, $viewer, $ready)
{
    if (!$ready || empty($viewer['member']) || !is_array($item) || ((int) $row['file_id'] > 0)
        || ((int) ($item['message_id'] ?? 0) !== (int) $row['id'])
        || (function_exists('ws_mail_item_is_bounce') && ws_mail_item_is_bounce($item))
        || (function_exists('ws_mail_item_is_phone') && ws_mail_item_is_phone($item))) {
        return 0;
    }

    return (int) $item['id'];
}

/**
 * The reading pane's mails, each marked whether it may be added to the
 * reader's calendar, and an invitation's card buttons for this reader. Pure
 * but for the words.
 *
 * @param array   $pane  ws_mail_pane()
 * @param bool    $may   a team member, with the plan tables there
 * @param array[] $plans ws_personal_plan_mail_plans() of the reader
 * @return array the pane
 */
function ws_personal_plan_mail_pane($pane, $may, $plans = array())
{
    foreach ($pane['items'] as $index => $item) {
        $may_item = $may && ((int) $item['message_id'] > 0)
            && !(function_exists('ws_mail_item_is_bounce') && ws_mail_item_is_bounce($item));
        $invitation = $may_item ? ws_mail_invitation_of($item) : null;

        $pane['items'][$index]['calendar'] = $may_item;
        $pane['items'][$index]['calendar_tools'] = $invitation
            ? ws_mail_invitation_tools_html((int) $item['id'], ws_personal_plan_mail_state($invitation, $plans[(int) ($item['conversation_id'] ?? 0)] ?? null))
            : '';
    }

    return $pane;
}

/**
 * A subject without the answer and forward marks before it ("Re:", "Fwd:",
 * "AW:", "YNT:" and the like, repeated). Pure.
 *
 * @param string $subject
 * @return string
 */
function ws_personal_plan_mail_title($subject)
{
    $subject = trim(preg_replace('/\s+/u', ' ', (string) $subject));

    while (preg_match('/^(re|fw|fwd|aw|wg|sv|vs|tr|ynt|ilt|rif|enc|res)\s*(\[[0-9]+\])?\s*:\s*/iu', $subject, $match)) {
        $subject = trim(mb_substr($subject, mb_strlen($match[0])));
    }

    return mb_substr($subject, 0, 255);
}

/**
 * What the New plan drawer is filled with from a mail: its invitation's
 * event (title, days, times, the place as the note) when it has one, else
 * its subject as the title (the conversation's when the mail has none) and
 * the mail's day, all day. Pure but for the site's time zone.
 *
 * @param array      $item         ws_mail_items row
 * @param array|null $conversation ws_mail_conversations row
 * @return array title, note, start, end, all_day, start_time, end_time, mail_item
 */
function ws_personal_plan_mail_fill($item, $conversation = null)
{
    $invitation = ws_mail_invitation_of($item);
    $title = $invitation ? ws_personal_plan_mail_title((string) $invitation['summary']) : '';
    $title = ($title !== '') ? $title : ws_personal_plan_mail_title((string) ($item['subject'] ?? ''));

    if (($title === '') && is_array($conversation)) {
        $title = ws_personal_plan_mail_title((string) ($conversation['subject'] ?? ''));
    }

    $fill = array('title' => $title, 'note' => '', 'all_day' => true, 'start_time' => '', 'end_time' => '', 'mail_item' => (int) ($item['id'] ?? 0));

    if ($invitation) {
        $starts = (int) $invitation['start'];
        $ends = max($starts, (int) $invitation['end']);
        $all_day = !empty($invitation['all_day']);

        // An all-day event ends at midnight after its last day.
        return array_merge($fill, array(
            'note'       => (string) ($invitation['location'] ?? ''),
            'start'      => date('Y-m-d', $starts),
            'end'        => date('Y-m-d', $all_day ? max($starts, $ends - 1) : $ends),
            'all_day'    => $all_day,
            'start_time' => $all_day ? '' : date('H:i', $starts),
            'end_time'   => $all_day ? '' : date('H:i', $ends),
        ));
    }

    $time = ((int) ($item['sent_at'] ?? 0) > 0) ? (int) $item['sent_at'] : (int) ($item['created_at'] ?? 0);
    $day = ($time > 0) ? date('Y-m-d', $time) : date('Y-m-d');

    return array_merge($fill, array('start' => $day, 'end' => $day));
}

/**
 * A mail the person may read, with its conversation: its message is not
 * deleted, and its channel or its conversation's channel is open to them.
 *
 * @param array $viewer
 * @param int   $item_id ws_mail_items id
 * @return array|null item, conversation
 */
function ws_personal_plan_mail_item($viewer, $item_id)
{
    if (empty($viewer['member']) || ((int) $item_id <= 0)) {
        return null;
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . (int) $item_id . "'");
    $message = is_array($item) ? ws_message((int) $item['message_id']) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || ws_mail_item_is_bounce($item)) {
        return null;
    }

    $conversation = ((int) $item['conversation_id'] > 0)
        ? db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $item['conversation_id'] . "'") : null;

    foreach (array((int) $message['channel_id'], is_array($conversation) ? (int) $conversation['channel_id'] : 0) as $channel_id) {
        $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;

        if ($channel && ws_can_read_channel($viewer, $channel)) {
            return array('item' => $item, 'conversation' => is_array($conversation) ? $conversation : null);
        }
    }

    return null;
}

/**
 * What an invitation's card offers this person (one plan a person for an
 * invitation): add (no plan yet), in (their plan has it), update (a changed
 * invitation their plan does not follow yet), remove (a cancelled one with
 * their plan), or nothing (a changed or cancelled one without a plan).
 * Pure but for the site's time zone.
 *
 * @param array      $invitation ws_mail_invitation_of()
 * @param array|null $plan       the person's plan row of the
 *                               conversation, or null
 * @return string add | in | update | remove | ''
 */
function ws_personal_plan_mail_state($invitation, $plan)
{
    if (($invitation['method'] ?? '') === 'CANCEL') {
        return $plan ? 'remove' : '';
    }

    if (!$plan) {
        return empty($invitation['changed']) ? 'add' : '';
    }

    if (empty($invitation['changed'])) {
        return 'in';
    }

    $clean = ws_personal_plan_clean(ws_personal_plan_mail_fill(array('invitation' => json_encode($invitation))));
    $same = !isset($clean['error']) && ((int) $clean['starts_at'] === (int) $plan['starts_at']) && ((int) $clean['ends_at'] === (int) $plan['ends_at'])
        && ((string) $clean['title'] === (string) $plan['title']);

    return $same ? 'in' : 'update';
}

/**
 * The buttons of an invitation's card for one person (ws_personal_plan_mail_state()),
 * and the line that no answer goes to the organiser.
 *
 * @param int    $item_id
 * @param string $state
 * @return string HTML
 */
function ws_mail_invitation_tools_html($item_id, $state)
{
    $button = function ($mode, $icon, $label) use ($item_id) {
        return '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-calendar="' . (int) $item_id . '" data-ws-mail-calendar-mode="' . h($mode) . '">'
            . '<i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($label) . '</button>';
    };
    $tools = array(
        'add'    => $button('', 'bi-calendar-plus', lang('Add to my calendar')),
        'in'     => '<span class="small"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>' . h(lang('In your calendar')) . '</span>'
            . '<button type="button" class="btn btn-sm btn-text theme-primary px-0" data-ws-mail-calendar="' . (int) $item_id . '" data-ws-mail-calendar-mode="open">' . h(lang('Open the plan')) . '</button>',
        'update' => $button('update', 'bi-calendar2-week', lang('Update my plan')),
        'remove' => $button('remove', 'bi-calendar-x', lang('Remove from my calendar')),
    );

    return '<div class="ws-mail-invite-tools mt-2">' . ($tools[$state] ?? '')
        . '<span class="small fg-secondary">' . h(lang('No answer is sent to the organiser.')) . '</span></div>';
}

/**
 * The card buttons of a channel's mail message for one reader, '' for none.
 *
 * @param array      $row    ws_messages row
 * @param array|null $item   ws_mail_items_map() entry
 * @param array      $viewer
 * @return string HTML
 */
function ws_personal_plan_mail_tools_for($row, $item, $viewer)
{
    $invitation = is_array($item) ? ws_mail_invitation_of($item) : null;

    if (!$invitation || !ws_personal_plan_mail_offer($row, $item, $viewer, ws_personal_plans_ready())) {
        return '';
    }

    return ws_mail_invitation_tools_html((int) $item['id'],
        ws_personal_plan_mail_state($invitation, ws_personal_plan_of_mail((int) $viewer['id'], (int) ($item['conversation_id'] ?? 0))));
}

/**
 * Add to my calendar: the drawer's fill and its words, for a mail the
 * person may read. One plan a person for a conversation: once they have
 * one, the mail opens it (open), a changed invitation opens it with what
 * this mail says filled in (update), a cancelled one hands it over to be
 * deleted after a confirmation (remove). Nothing changes until they save or
 * confirm. tools gives the card's buttons as they now stand.
 *
 * @param array  $viewer
 * @param int    $item_id
 * @param string $mode '' | open | update | remove | tools
 * @return array fill + strings, plan + strings (+ remove), tools, or error
 */
function ws_personal_plan_mail_draft($viewer, $item_id, $mode = '')
{
    if (!ws_personal_plans_ready()) {
        return array('error' => lang('Access denied'));
    }

    $found = ws_personal_plan_mail_item($viewer, $item_id);

    if (!$found) {
        return array('error' => lang('This e-mail cannot be found.'));
    }

    $invitation = ws_mail_invitation_of($found['item']);
    $row = ws_personal_plan_of_mail((int) $viewer['id'], (int) $found['item']['conversation_id']);

    if ($mode === 'tools') {
        return array('tools' => $invitation ? ws_mail_invitation_tools_html((int) $found['item']['id'], ws_personal_plan_mail_state($invitation, $row)) : '');
    }

    $fill = ws_personal_plan_mail_fill($found['item'], $found['conversation']);
    // The drawer's words, its roadmap's included (personal_plan_steps.php).
    $strings = ws_personal_plan_js_strings() + ws_personal_plan_steps_js_strings();

    if ($row) {
        $plan = ws_personal_plan_for_reader((int) $viewer['id'], $row, $viewer);

        if ($mode === 'remove') {
            return array('plan' => $plan, 'remove' => true, 'strings' => $strings);
        }

        if (($mode === 'update') && $invitation && (($invitation['method'] ?? '') !== 'CANCEL')) {
            $plan = array_merge($plan, array_intersect_key($fill, array_flip(array('title', 'start', 'end', 'all_day', 'start_time', 'end_time'))));
        }

        return array('plan' => $plan, 'strings' => $strings);
    }

    if (($mode !== '') || (($invitation['method'] ?? '') === 'CANCEL')) {
        return array('error' => lang('No plan of yours was made from this invitation.'));
    }

    return array('fill' => $fill, 'strings' => $strings);
}

/**
 * The mail line of a saved plan: the conversation's subject, for a reader
 * who may read the conversation's channel.
 *
 * @param array $viewer
 * @param int   $conversation_id
 * @return array|null conversation_id, subject
 */
function ws_personal_plan_mail_line($viewer, $conversation_id)
{
    $conversation = ((int) $conversation_id > 0) ? db_item("SELECT id, channel_id, subject FROM ws_mail_conversations WHERE id = '" . (int) $conversation_id . "'") : null;
    $channel = $conversation ? ws_channel((int) $conversation['channel_id']) : null;

    if (empty($viewer['member']) || !$channel || !ws_can_read_channel($viewer, $channel)) {
        return null;
    }

    $subject = trim((string) $conversation['subject']);

    return array('conversation_id' => (int) $conversation['id'], 'subject' => ($subject !== '') ? $subject : lang('E-mail'));
}

/**
 * Whether ws_mail_items keeps a mail's invitation (2026.11.0, the step of
 * upgrade_2026_11_0_ws_mail_invitation()).
 *
 * @return bool
 */
function ws_mail_invitation_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_mail_items', 'invitation');
    }

    return $ready;
}

/**
 * A mail's first calendar part: text/calendar or application/ics, named or
 * not, or a part named *.ics. Pure.
 *
 * @param array[] $attachments pg_mail_parse() attachments
 * @return array|null
 */
function ws_mail_invitation_part($attachments)
{
    foreach ((array) $attachments as $attachment) {
        $type = strtolower(trim((string) ($attachment['content_type'] ?? '')));
        $name = strtolower((string) ($attachment['filename'] ?? ''));

        if (in_array($type, array('text/calendar', 'application/ics'), true) || (substr($name, -4) === '.ics')) {
            return $attachment;
        }
    }

    return null;
}

/**
 * What a mail's invitation says: its first calendar part (text/calendar or
 * application/ics, named or not) and that part's first VEVENT. No attendee
 * list, description or alarm is read; text is cut to 255 characters. Pure
 * but for the site's time zone.
 *
 * @param array[] $attachments pg_mail_parse() attachments, with content
 * @return array|null uid, method (REQUEST, CANCEL...), summary, start, end
 *                    (moments; end exclusive), all_day, location,
 *                    organizer (name, email); null when none reads
 */
function ws_mail_invitation_read($attachments)
{
    $part = ws_mail_invitation_part($attachments);
    $text = $part ? (string) ($part['content'] ?? '') : null;

    $events = ($text !== null) ? ws_ical_vevents($text) : array();
    $event = $events ? $events[0] : null;
    $start = isset($event['DTSTART']) ? ws_personal_ical_moment($event['DTSTART']) : null;

    if (!$start) {
        return null;
    }

    $cut = function ($value) {
        return mb_substr(ws_ical_text((string) $value), 0, 255);
    };
    $all_day = (bool) preg_match('/^\d{8}$/', trim((string) $event['DTSTART']['value']));
    $end = isset($event['DTEND']) ? ws_personal_ical_moment($event['DTEND']) : null;
    $starts_at = $start->getTimestamp();
    $ends_at = $end ? $end->getTimestamp() : 0;

    if ($ends_at <= $starts_at) {
        $length = isset($event['DURATION']) ? ws_ical_duration((string) $event['DURATION']['value']) : 0;
        $ends_at = $starts_at + (($length > 0) ? $length : ($all_day ? 86400 : 3600));
    }

    // The calendar's METHOD; an event marked cancelled reads as CANCEL.
    $method = preg_match('/^METHOD:([A-Za-z-]+)\s*$/mi', $text, $match) ? strtoupper($match[1]) : '';
    $method = (strtoupper(trim((string) ($event['STATUS']['value'] ?? ''))) === 'CANCELLED') ? 'CANCEL' : $method;
    $organizer = isset($event['ORGANIZER']) ? $event['ORGANIZER'] : array('value' => '', 'params' => '');
    $email = preg_replace('/^mailto:/i', '', trim((string) $organizer['value']));
    $person = preg_match('/(?:^|;)CN="?([^";]*)"?/i', (string) $organizer['params'], $cn) ? $cn[1] : '';

    return array(
        'uid'       => mb_substr(trim((string) ($event['UID']['value'] ?? '')), 0, 255),
        'method'    => mb_substr($method, 0, 32),
        'summary'   => $cut($event['SUMMARY']['value'] ?? ''),
        'start'     => $starts_at,
        'end'       => $ends_at,
        'all_day'   => $all_day,
        'location'  => $cut($event['LOCATION']['value'] ?? ''),
        'organizer' => array(
            'name'  => $cut($person),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr(strtolower($email), 0, 255) : '',
        ),
    );
}

/**
 * Keeps a mail's invitation on its row at ingest, once the column is there.
 * A part that does not read leaves the column empty and the mail as it is.
 *
 * @param int     $item_id
 * @param int     $box_id      its mailbox: an earlier invitation of the same
 *                             event there marks this one changed
 * @param array[] $attachments pg_mail_parse() attachments, with content
 * @return string the type of a nameless calendar part read into the card,
 *                which ws_mail_attachments_sort() named among the refused;
 *                '' otherwise
 */
function ws_mail_invitation_keep($item_id, $box_id, $attachments)
{
    if (((int) $item_id <= 0) || !ws_mail_invitation_ready()) {
        return '';
    }

    $invitation = ws_mail_invitation_read($attachments);

    if ($invitation && ($invitation['uid'] !== '') && ($invitation['method'] !== 'CANCEL')) {
        // A later invitation of an event the box already had changes it.
        $uid = substr((string) json_encode(array('uid' => $invitation['uid']), JSON_UNESCAPED_UNICODE), 1, -1);
        $invitation['changed'] = ((int) db_value("SELECT COUNT(*) FROM ws_mail_items
            WHERE box_id = '" . (int) $box_id . "'
            AND id < '" . (int) $item_id . "' AND invitation LIKE '%" . e(addcslashes($uid, '%_\\')) . "%'") > 0);
    }

    if (!$invitation) {
        return '';
    }

    db("UPDATE ws_mail_items SET invitation = '" . e((string) json_encode($invitation, JSON_UNESCAPED_UNICODE)) . "' WHERE id = '" . (int) $item_id . "'");
    $part = ws_mail_invitation_part($attachments);

    return ((string) ($part['filename'] ?? '') === '') ? (string) ($part['content_type'] ?? '') : '';
}

/**
 * A kept invitation read back from its column. Pure.
 *
 * @param array $item ws_mail_items row
 * @return array|null ws_mail_invitation_read()
 */
function ws_mail_invitation_of($item)
{
    $data = json_decode((string) ($item['invitation'] ?? ''), true);

    return (is_array($data) && ((int) ($data['start'] ?? 0) > 0)) ? $data : null;
}

/**
 * A day in words: weekday and day.month, and the year when it is not this
 * year's. Pure but for the clock.
 *
 * @param string $date Y-m-d
 * @param string $year this year (Y)
 * @return string
 */
function ws_mail_invitation_day($date, $year)
{
    $label = ws_day_label($date);

    return (substr((string) $date, 0, 4) !== (string) $year) ? $label . '.' . substr((string) $date, 0, 4) : $label;
}

/**
 * When a kept invitation is, in words, in the site's time zone: its day and
 * hours, or its days without hours when it is all day or runs over several
 * days. Pure but for the site's time zone.
 *
 * @param array  $invitation ws_mail_invitation_of()
 * @param string $year       this year (Y)
 * @return string
 */
function ws_mail_invitation_when($invitation, $year = '')
{
    $year = ($year !== '') ? $year : date('Y');
    $fill = ws_personal_plan_mail_fill(array('invitation' => json_encode($invitation)));
    $days = ws_mail_invitation_day($fill['start'], $year) . (($fill['end'] !== $fill['start']) ? ' – ' . ws_mail_invitation_day($fill['end'], $year) : '');

    return ($fill['all_day'] || ($fill['end'] !== $fill['start'])) ? $days : $days . ' · ' . $fill['start_time'] . '–' . $fill['end_time'];
}

/**
 * A mail's invitation drawn as a card: whether it invites, changes or
 * cancels, its title, when, where and who sent it (their address beside the
 * name when it is not the mail's sender), and the reader's buttons. Every
 * word from outside is plain escaped text. Pure but for the words.
 *
 * @param array       $item  ws_mail_items row
 * @param string|null $tools ws_mail_invitation_tools_html(), '' for none, or
 *                           null for the place a channel's script fills
 *                           for each reader
 * @return string HTML, '' for a mail without an invitation
 */
function ws_mail_invitation_card_html($item, $tools)
{
    $invitation = ws_mail_invitation_of($item);

    if (!$invitation) {
        return '';
    }

    $cancel = (($invitation['method'] ?? '') === 'CANCEL');
    $state = $cancel ? lang('Cancelled invitation') : (!empty($invitation['changed']) ? lang('Changed invitation') : lang('Invitation'));
    $organizer = (array) ($invitation['organizer'] ?? array());
    $name = trim((string) ($organizer['name'] ?? ''));
    $email = (string) ($organizer['email'] ?? '');
    $who = ($name === '') ? $email
        : ((($email !== '') && ($email !== strtolower(trim((string) ($item['from_email'] ?? ''))))) ? $name . ' <' . $email . '>' : $name);
    $html = '<div class="ws-mail-invite mt-2' . ($cancel ? ' ws-mail-invite-cancel' : '') . '">'
        . '<div class="ws-mail-invite-head small fg-secondary mb-1"><i class="bi ' . ($cancel ? 'bi-calendar-x' : 'bi-calendar-event') . ' me-1" aria-hidden="true"></i>' . h($state) . '</div>'
        . '<div class="fw-semibold' . ($cancel ? ' text-decoration-line-through' : '') . '">' . h(((string) $invitation['summary'] !== '') ? (string) $invitation['summary'] : (string) ($item['subject'] ?? '')) . '</div>'
        . '<div class="small">' . h(ws_mail_invitation_when($invitation)) . '</div>';

    if ((string) ($invitation['location'] ?? '') !== '') {
        $html .= '<div class="small fg-secondary"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' . h((string) $invitation['location']) . '</div>';
    }

    if ($who !== '') {
        $html .= '<div class="small fg-secondary">' . h(lang(array('string' => 'Organiser: {var:1}', 'vars' => $who))) . '</div>';
    }

    $html .= ($tools === null) ? '<div data-ws-mail-invite-tools="' . (int) ($item['id'] ?? 0) . '"></div>' : (string) $tools;

    return $html . '</div>';
}

/**
 * The words of Add to my calendar (assets/js/workspace.js, workspace_mail.js).
 *
 * @return array key => text
 */
function ws_personal_plan_mail_js_strings()
{
    return array(
        'mail_calendar' => lang('Add to my calendar'),
    );
}
