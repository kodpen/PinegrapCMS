<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the message pinned to the top of a channel. A channel has one
 * at most, shown under the channel's greeting until the reader closes it;
 * closed, it stays closed for that reader until another message is pinned.
 * Whoever may write in the channel may pin, or take the pin off, and the
 * channel says who did.
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
 * Has the database the pinned messages (2026.4.5, 5.86)?
 *
 * @return bool
 */
function ws_pins_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_channels', 'pinned_message_id')
            && waf_table_has_column('ws_channel_members', 'pin_hidden');
    }

    return $ready;
}

/**
 * May this person pin a message in the channel, or take the pin off?
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_can_pin($viewer, $channel)
{
    return ws_pins_ready() && is_array($channel) && ((int) $channel['archived_at'] === 0) && ws_can_post_channel($viewer, $channel);
}

/**
 * Pins a message to the top of its channel, in place of the one before; 0
 * takes the pin off.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $message_id
 * @return array ok, error
 */
function ws_channel_pin_message($viewer, $channel, $message_id)
{
    if (!ws_can_pin($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $message_id = (int) $message_id;
    $actor = '<@user:' . (int) $viewer['id'] . '>';

    if ($message_id <= 0) {
        if ((int) ($channel['pinned_message_id'] ?? 0) === 0) {
            return array('ok' => true, 'error' => '');
        }

        db("UPDATE ws_channels SET pinned_message_id = 0, pinned_by = '" . (int) $viewer['id'] . "', pinned_at = '" . time() . "' WHERE id = '" . (int) $channel['id'] . "'");
        ws_message_system($channel['id'], lang(array('string' => '{var:1} took the pinned message off the top of the channel.', 'vars' => $actor)));

        return array('ok' => true, 'error' => '');
    }

    $message = ws_message($message_id);

    if (!$message || ((int) $message['channel_id'] !== (int) $channel['id']) || ((int) $message['deleted_at'] > 0)
        || !in_array($message['kind'], array('message', 'note', 'decision', 'task'), true)
        || (function_exists('ws_message_in_past') && ws_message_in_past($message))) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    db("UPDATE ws_channels SET pinned_message_id = '" . $message_id . "', pinned_by = '" . (int) $viewer['id'] . "', pinned_at = '" . time() . "' WHERE id = '" . (int) $channel['id'] . "'");
    ws_message_system($channel['id'], lang(array('string' => '{var:1} pinned a message to the top of the channel.', 'vars' => $actor)));

    return array('ok' => true, 'error' => '');
}

/**
 * Closes the pinned message for this reader: it stays closed until another
 * one is pinned. Kept in the reader's membership; somebody reading a public
 * channel they are not in closes it on their browser only.
 *
 * @param array $viewer
 * @param array $channel
 * @return array ok, error
 */
function ws_channel_pin_hide($viewer, $channel)
{
    if (!ws_pins_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    db("UPDATE ws_channel_members SET pin_hidden = '" . (int) ($channel['pinned_message_id'] ?? 0) . "'
        WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");
    ws_channel_membership_forget();

    return array('ok' => true, 'error' => '');
}

/**
 * The pinned message of a channel as its screen shows it, or null.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array|null $membership
 * @return array|null id, sender, text, time, pinned_by, hidden
 */
function ws_channel_pin_present($viewer, $channel, $membership)
{
    if (!ws_pins_ready() || ((int) ($channel['pinned_message_id'] ?? 0) <= 0)) {
        return null;
    }

    $message = ws_message((int) $channel['pinned_message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0) || ((int) $message['channel_id'] !== (int) $channel['id'])) {
        return null;
    }

    $sender = null;

    if ($message['sender_kind'] === 'user') {
        $people = ws_people(array((int) $message['sender_id']));
        $sender = $people[(int) $message['sender_id']] ?? null;
    } elseif ($message['sender_kind'] === 'app') {
        $sender = ws_app_sender((int) $message['sender_id']);
    } elseif (($message['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = ws_guest_sender((int) $message['sender_id']);
    }

    $text = ws_plain_excerpt($viewer, (string) $message['body'], 280);

    if (($text === '') && ((string) $message['file_name'] !== '')) {
        $text = (string) $message['file_name'];
    }

    return array(
        'id'        => (int) $message['id'],
        'sender'    => $sender,
        'text'      => $text,
        'time'      => ws_time_label($message['created_at']),
        'pinned_by' => ws_person_name($channel['pinned_by'] ?? 0),
        'hidden'    => is_array($membership) && ((int) ($membership['pin_hidden'] ?? 0) === (int) $message['id']),
    );
}

/**
 * The words the screen needs for pinned messages.
 *
 * @return array
 */
function ws_pins_js_strings()
{
    return array(
        'pin_message'   => lang('Pin to the top'),
        'unpin_message' => lang('Take the pin off'),
        'pin_replace'   => lang('Pin this message to the top of the channel? It takes the place of the one pinned now.'),
        'pin_title'     => lang('Pinned to the top'),
        'pin_go'        => lang('Go to the message'),
        'pin_close'     => lang('Close'),
        'pin_by'        => ws_js_template('pinned by {var:1}', 1),
    );
}
