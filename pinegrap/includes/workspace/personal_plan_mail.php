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
 * What the New plan drawer is filled with from a mail: its subject as the
 * title (the conversation's when the mail has none) and the mail's day, all
 * day. Pure.
 *
 * @param array      $item         ws_mail_items row
 * @param array|null $conversation ws_mail_conversations row
 * @return array title, start, end, all_day, start_time, end_time, mail_item
 */
function ws_personal_plan_mail_fill($item, $conversation = null)
{
    $title = ws_personal_plan_mail_title((string) ($item['subject'] ?? ''));

    if (($title === '') && is_array($conversation)) {
        $title = ws_personal_plan_mail_title((string) ($conversation['subject'] ?? ''));
    }

    $time = ((int) ($item['sent_at'] ?? 0) > 0) ? (int) $item['sent_at'] : (int) ($item['created_at'] ?? 0);
    $day = ($time > 0) ? date('Y-m-d', $time) : date('Y-m-d');

    return array(
        'title'      => $title,
        'start'      => $day,
        'end'        => $day,
        'all_day'    => true,
        'start_time' => '',
        'end_time'   => '',
        'mail_item'  => (int) ($item['id'] ?? 0),
    );
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
 * person may read.
 *
 * @param array $viewer
 * @param int   $item_id
 * @return array fill + strings, or error
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

    return array(
        'fill'    => ws_personal_plan_mail_fill($found['item'], $found['conversation']),
        'strings' => ws_personal_plan_js_strings(),
    );
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
