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
 * reader's calendar. Pure.
 *
 * @param array $pane  ws_mail_pane()
 * @param bool  $may   a team member, with the plan tables there
 * @return array the pane
 */
function ws_personal_plan_mail_pane($pane, $may)
{
    foreach ($pane['items'] as $index => $item) {
        $pane['items'][$index]['calendar'] = $may && ((int) $item['message_id'] > 0)
            && !(function_exists('ws_mail_item_is_bounce') && ws_mail_item_is_bounce($item));
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
 * Add to my calendar: the drawer's fill and its words, for a mail the
 * person may read. An invitation the person already made a plan from opens
 * that plan, with what this mail says filled in when it changed it; a
 * cancelled one opens it to be deleted. Nothing changes until they save.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array fill + strings, or plan + strings, or error
 */
function ws_personal_plan_mail_draft($viewer, $item_id)
{
    if (!ws_personal_plans_ready()) {
        return array('error' => lang('Access denied'));
    }

    $found = ws_personal_plan_mail_item($viewer, $item_id);

    if (!$found) {
        return array('error' => lang('This e-mail cannot be found.'));
    }

    $fill = ws_personal_plan_mail_fill($found['item'], $found['conversation']);
    $invitation = ws_mail_invitation_of($found['item']);
    $plan = $invitation ? ws_personal_plan_of_mail((int) $viewer['id'], (int) $found['item']['conversation_id']) : null;
    $strings = ws_personal_plan_js_strings();

    if ($plan) {
        $plan = ws_personal_plan_for_reader((int) $viewer['id'], $plan);

        return array('plan' => (($invitation['method'] ?? '') === 'CANCEL') ? $plan
            : array_merge($plan, array_intersect_key($fill, array_flip(array('title', 'start', 'end', 'all_day', 'start_time', 'end_time')))), 'strings' => $strings);
    }

    if (($invitation['method'] ?? '') === 'CANCEL') {
        return array('error' => lang('No plan of yours was made from this invitation.'));
    }

    return array('fill' => $fill, 'strings' => $strings);
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
    $text = null;

    foreach ((array) $attachments as $attachment) {
        $type = strtolower(trim((string) ($attachment['content_type'] ?? '')));
        $name = strtolower((string) ($attachment['filename'] ?? ''));

        if (in_array($type, array('text/calendar', 'application/ics'), true) || (substr($name, -4) === '.ics')) {
            $text = (string) ($attachment['content'] ?? '');
            break;
        }
    }

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
 */
function ws_mail_invitation_keep($item_id, $box_id, $attachments)
{
    if (((int) $item_id <= 0) || !ws_mail_invitation_ready()) {
        return;
    }

    $invitation = ws_mail_invitation_read($attachments);

    if ($invitation && ($invitation['uid'] !== '') && ($invitation['method'] !== 'CANCEL')) {
        // A later invitation of an event the box already had changes it.
        $uid = substr((string) json_encode(array('uid' => $invitation['uid']), JSON_UNESCAPED_UNICODE), 1, -1);
        $invitation['changed'] = ((int) db_value("SELECT COUNT(*) FROM ws_mail_items
            WHERE box_id = '" . (int) $box_id . "'
            AND id < '" . (int) $item_id . "' AND invitation LIKE '%" . e(addcslashes($uid, '%_\\')) . "%'") > 0);
    }

    if ($invitation) {
        db("UPDATE ws_mail_items SET invitation = '" . e((string) json_encode($invitation, JSON_UNESCAPED_UNICODE)) . "' WHERE id = '" . (int) $item_id . "'");
    }
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
 * When a kept invitation is, in words: its day (and last day), and its
 * times when it is not all day. Pure but for the site's time zone.
 *
 * @param array $invitation ws_mail_invitation_of()
 * @return string
 */
function ws_mail_invitation_when($invitation)
{
    $fill = ws_personal_plan_mail_fill(array('invitation' => json_encode($invitation)));
    $days = ws_day_label($fill['start']) . (($fill['end'] !== $fill['start']) ? ' – ' . ws_day_label($fill['end']) : '');

    return $fill['all_day'] ? $days . ' · ' . lang('All day') : $days . ' · ' . $fill['start_time'] . '–' . $fill['end_time'];
}

/**
 * A mail's invitation drawn as a card: whether it invites, changes or
 * cancels, its title, when, where and who sent it, and Add to my calendar.
 * No answer goes to the organiser, and the card says so. Pure but for the
 * words.
 *
 * @param array $item  ws_mail_items row
 * @param bool  $offer whether Add to my calendar is drawn
 * @return string HTML, '' for a mail without an invitation
 */
function ws_mail_invitation_card_html($item, $offer)
{
    $invitation = ws_mail_invitation_of($item);

    if (!$invitation) {
        return '';
    }

    $cancel = (($invitation['method'] ?? '') === 'CANCEL');
    $state = $cancel ? lang('Cancelled invitation') : (!empty($invitation['changed']) ? lang('Changed invitation') : lang('Invitation'));
    $organizer = (array) ($invitation['organizer'] ?? array());
    $who = trim((string) ($organizer['name'] ?? '')) !== '' ? (string) $organizer['name'] : (string) ($organizer['email'] ?? '');
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

    if ($offer) {
        $html .= '<div class="ws-mail-invite-tools mt-2"><button type="button" class="pg-button pg-button-quiet pg-button-sm" data-ws-mail-calendar="' . (int) ($item['id'] ?? 0) . '">'
            . '<i class="bi ' . ($cancel ? 'bi-calendar-x' : 'bi-calendar-plus') . ' me-1" aria-hidden="true"></i>' . h($cancel ? lang('Open my plan') : lang('Add to my calendar')) . '</button>'
            . '<span class="small fg-secondary">' . h(lang('No answer is sent to the organiser.')) . '</span></div>';
    }

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
