<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the panel's AJAX door (api.php, every action named ws_*).
 *
 * api.php has already checked the session and the form token; everything the
 * workspace itself decides - who may read, write, manage - is decided here and
 * in access.php. Every answer is JSON, errors included.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * @param string $message
 * @param string $field
 * @return array
 */
function ws_action_error($message, $field = '')
{
    return array('status' => 'error', 'message' => $message, 'field' => $field);
}

/**
 * @param array $data
 * @return array
 */
function ws_action_ok($data = array())
{
    return array('status' => 'success', 'data' => $data);
}

/**
 * A guest link as an inline SVG QR code, for the screen that shows the link
 * once. Drawn here so the token never goes into an image URL.
 *
 * @param string $url
 * @return string '' for an empty link (an ended one).
 */
function ws_action_link_qr($url)
{
    $url = (string) $url;

    return ($url !== '') ? pg_qr_svg($url, 0, array('label' => lang('QR code for the link'), 'class' => 'd-block w-100')) : '';
}

/**
 * The channel an action is about, checked for what the action needs.
 *
 * @param array  $viewer
 * @param array  $request
 * @param string $need read | post | manage
 * @return array|string the channel, or the error message
 */
function ws_action_channel($viewer, $request, $need = 'read')
{
    $channel = ws_channel((int) ($request['channel_id'] ?? 0));

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        return lang('That channel could not be found.');
    }

    if (($need === 'post') && !ws_can_post_channel($viewer, $channel)) {
        return lang('You cannot post in that channel.');
    }

    if (($need === 'manage') && !ws_can_manage_channel($viewer, $channel)) {
        return lang('Only the owner of the channel can change it.');
    }

    return $channel;
}

/**
 * May this person override a clash with somebody's leave on this task?
 *
 * @param array $viewer
 * @param int   $department_id
 * @return bool
 */
function ws_may_override($viewer, $department_id)
{
    return $viewer['board'] || ($viewer['role'] < 3)
        || (((int) $department_id > 0) && in_array((int) $department_id, ws_lead_departments($viewer['id']), true));
}

/**
 * Only the fields a task save may carry, in the shape ws_task_create() and
 * ws_task_update() read.
 *
 * @param array $request
 * @return array
 */
function ws_action_task_fields($request)
{
    $fields = array();

    foreach (array('title', 'description', 'status', 'priority', 'start_date', 'due_date', 'due_time', 'remind_minutes', 'estimate_minutes', 'department_id', 'channel_id') as $field) {
        if (array_key_exists($field, $request)) {
            $fields[$field] = $request[$field];
        }
    }

    if (isset($request['assignees'])) {
        $fields['assignees'] = array_values(array_filter(array_map('intval', (array) $request['assignees'])));
    }

    if (isset($request['refs'])) {
        $fields['refs'] = (array) $request['refs'];
    }

    if (!empty($request['checklist_message_id'])) {
        $fields['checklist_message_id'] = (int) $request['checklist_message_id'];
    }

    return $fields;
}

/**
 * The entry point api.php calls.
 *
 * @param string $action
 * @param array  $request
 * @return array
 */
function ws_handle_action($action, $request)
{
    if (!ws_enabled()) {
        return ws_action_error(lang('The workspace is not switched on.'));
    }

    $user = validate_user();
    $viewer = ws_viewer($user);

    if (!$viewer['member']) {
        return ws_action_error(lang('Access denied.'));
    }

    // The session lock is only needed by the actions that write to it.
    if (!in_array($action, array('ws_channel_audit'), true)) {
        session_write_close();
    }

    $request = is_array($request) ? $request : array();

    // An earlier version of a conversation is kept as it was: nothing in it
    // is edited, marked, reacted to, ticked or voted on any more. Deleting a
    // message there for oneself only still hides it from one's own view.
    if (function_exists('ws_message_in_past') && ws_eras_ready()) {
        $touched = null;

        if (in_array($action, array('ws_edit', 'ws_mark', 'ws_react', 'ws_check'), true)
            || (($action === 'ws_delete') && ((string) ($request['scope'] ?? '') !== 'me'))) {
            $touched = ws_message((int) ($request['message_id'] ?? 0));
        } elseif (in_array($action, array('ws_poll_edit', 'ws_poll_vote', 'ws_poll_close'), true)) {
            $poll = db_item("SELECT message_id FROM ws_polls WHERE id = '" . (int) ($request['poll_id'] ?? 0) . "'");
            $touched = is_array($poll) ? ws_message((int) $poll['message_id']) : null;
        }

        if ($touched && ws_message_in_past($touched)) {
            return ws_action_error(lang('This message is in an earlier version of the conversation, which is kept as it was.'));
        }
    }

    switch ($action) {

        // What runs where no scheduler does: repeating tasks come due, and
        // requests to Claude that waited are sent. The screen asks for it once
        // it is drawn, so neither holds up its first answer (a call to
        // claude.ai may take seconds).
        case 'ws_tick':
            if (function_exists('ws_recurrence_tick')) {
                ws_recurrence_tick();
            }

            if (function_exists('ws_claude_tick')) {
                ws_claude_tick();
            }

            // Pinegrap AI's watchdog only: its model calls come from
            // ws_ai_kick, never on the way to a screen.
            if (function_exists('ws_ai_tick')) {
                ws_ai_tick();
            }

            // Scheduled actions whose time has come (scheduled.php).
            if (function_exists('ws_scheduled_run')) {
                ws_scheduled_run();
            }

            // Task reminders whose time has come (reminders.php).
            if (function_exists('ws_task_reminders_run')) {
                ws_task_reminders_run();
            }

            // Concluded discussions whose time is up (threads.php).
            if (function_exists('ws_threads_purge')) {
                ws_threads_purge();
            }

            // Bulk changes a closed screen left half done (bulk.php).
            if (function_exists('ws_bulk_continue')) {
                ws_bulk_continue(1);
            }

            return ws_action_ok();

        // Scheduled actions (includes/workspace/scheduled.php): staff only.
        case 'ws_scheduled_list':
            if (!ws_can_schedule($viewer)) {
                return ws_action_error(lang('Only staff can schedule actions.'));
            }

            return ws_action_ok(array('items' => ws_scheduled_list($viewer, array(
                'status'     => (string) ($request['status'] ?? ''),
                'channel_id' => (int) ($request['channel_id'] ?? 0),
            ))));

        // The actions a follow-up can start, for the form.
        case 'ws_scheduled_targets':
            if (!ws_can_schedule($viewer)) {
                return ws_action_error(lang('Only staff can schedule actions.'));
            }

            return ws_action_ok(array('items' => ws_scheduled_targets($viewer)));

        case 'ws_scheduled_get':
            $scheduled = ws_can_schedule($viewer) ? ws_scheduled_action((int) ($request['id'] ?? 0)) : null;

            if (!$scheduled) {
                return ws_action_error(lang('That scheduled action could not be found.'));
            }

            return ws_action_ok(array('item' => ws_scheduled_present($viewer, $scheduled, true)));

        case 'ws_scheduled_save':
            $result = ws_scheduled_save($viewer, array(
                'id'          => (int) ($request['id'] ?? 0),
                'name'        => (string) ($request['name'] ?? ''),
                'rules'       => is_array($request['rules'] ?? null) ? $request['rules'] : array(),
                // "action" names the request itself; what is done comes as main_action.
                'action'      => is_array($request['main_action'] ?? null) ? $request['main_action'] : null,
                'then_action' => is_array($request['then_action'] ?? null) ? $request['then_action'] : null,
                // What follows, by how the action went (5.85); without it the
                // one "then" above.
                'follow'      => is_array($request['follow'] ?? null) ? $request['follow'] : null,
                'channel_id'  => (int) ($request['channel_id'] ?? 0),
                'note_id'     => (int) ($request['note_id'] ?? 0),
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            return ws_action_ok(array('id' => $result['id'], 'message_id' => $result['message_id'], 'item' => ws_scheduled_present($viewer, ws_scheduled($result['id']), true)));

        // What the form would save, in words, as it is filled in.
        case 'ws_scheduled_preview':
            $result = ws_scheduled_preview($viewer, array(
                'id'          => (int) ($request['id'] ?? 0),
                'name'        => (string) ($request['name'] ?? ''),
                'rules'       => is_array($request['rules'] ?? null) ? $request['rules'] : array(),
                'action'      => is_array($request['main_action'] ?? null) ? $request['main_action'] : null,
                'then_action' => is_array($request['then_action'] ?? null) ? $request['then_action'] : null,
                'follow'      => is_array($request['follow'] ?? null) ? $request['follow'] : null,
                'channel_id'  => (int) ($request['channel_id'] ?? 0),
                'note_id'     => (int) ($request['note_id'] ?? 0),
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            unset($result['ok'], $result['error'], $result['field']);

            return ws_action_ok($result);

        case 'ws_scheduled_status':
            $scheduled = ws_can_schedule($viewer) ? ws_scheduled_action((int) ($request['id'] ?? 0)) : null;

            if (!$scheduled) {
                return ws_action_error(lang('That scheduled action could not be found.'));
            }

            $result = ws_scheduled_set_status($viewer, $scheduled, (string) ($request['status'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('item' => ws_scheduled_present($viewer, ws_scheduled($scheduled['id']), true))) : ws_action_error($result['error']);

        case 'ws_scheduled_run_now':
            $scheduled = ws_can_schedule($viewer) ? ws_scheduled_action((int) ($request['id'] ?? 0)) : null;

            if (!$scheduled) {
                return ws_action_error(lang('That scheduled action could not be found.'));
            }

            $ran = ws_scheduled_execute($scheduled['id'], true);

            if ($ran === '') {
                return ws_action_error(lang('It is running already, or it cannot run any more.'));
            }

            return ws_action_ok(array('result' => $ran, 'item' => ws_scheduled_present($viewer, ws_scheduled($scheduled['id']), true)));

        // Messages written now and posted later (scheduled_messages.php):
        // anybody in the team, each their own.
        case 'ws_scheduled_message_save':
            $result = ws_scheduled_message_save($viewer, array(
                'id'         => (int) ($request['id'] ?? 0),
                'channel_id' => (int) ($request['channel_id'] ?? 0),
                'body'       => (string) ($request['body'] ?? ''),
                'parent_id'  => isset($request['parent_id']) ? (int) $request['parent_id'] : null,
                'date'       => (string) ($request['date'] ?? ''),
                'time'       => (string) ($request['time'] ?? ''),
                'in_minutes' => (int) ($request['in_minutes'] ?? 0),
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            $saved = ws_scheduled_message_mine($viewer, $result['id']);

            return ws_action_ok(array('item' => $saved ? ws_scheduled_message_present($viewer, $saved) : null));

        case 'ws_scheduled_messages':
            return ws_action_ok(array('items' => ws_scheduled_messages_list($viewer, (int) ($request['channel_id'] ?? 0))));

        case 'ws_scheduled_message_delete':
        case 'ws_scheduled_message_send':
            $scheduled = ws_scheduled_message_mine($viewer, (int) ($request['id'] ?? 0));

            if (!$scheduled) {
                return ws_action_error(lang('That scheduled message could not be found.'));
            }

            if ($action === 'ws_scheduled_message_delete') {
                $result = ws_scheduled_message_delete($viewer, $scheduled);

                return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);
            }

            $ran = ws_scheduled_execute($scheduled['id'], true);

            if ($ran !== 'ran') {
                $again = ws_scheduled_message_mine($viewer, $scheduled['id']);

                return ws_action_error(($again && ($again['status'] === 'failed')) ? ws_scheduled_message_present($viewer, $again)['error'] : lang('It is being posted right now.'));
            }

            return ws_action_ok();

        // Works out a table as it is typed (the table window), the lines of a
        // writing box that end with "=", or draws a text the way a message
        // would show it, formulas worked out.
        case 'ws_calc':
            if (isset($request['lines']) && is_array($request['lines'])) {
                return ws_action_ok(array('lines' => ws_calc_inline_lines($request['lines'])));
            }

            if (isset($request['grid']) && is_array($request['grid'])) {
                $cells = array();

                foreach (ws_calc_grid($request['grid']) as $r => $row) {
                    foreach ($row as $c => $cell) {
                        if ($cell['formula'] || ($cell['value'] !== null)) {
                            $cells[] = array(
                                'r'       => (int) $r,
                                'c'       => (int) $c,
                                'formula' => $cell['formula'],
                                'display' => $cell['formula'] ? $cell['display'] : ws_calc_format($cell['value']),
                                'error'   => ($cell['error'] !== '') ? ws_calc_error_help($cell['error']) : '',
                            );
                        }
                    }
                }

                return ws_action_ok(array('cells' => $cells));
            }

            // The blocks of a note (a table, a calculation, a list, code), each
            // drawn as in a message, for the note's writing box to show them.
            if (isset($request['blocks']) && is_array($request['blocks'])) {
                $blocks = array();
                $left = WS_NOTE_MAX;

                foreach (array_slice(array_values($request['blocks']), 0, 100) as $block) {
                    $block = is_string($block) ? mb_substr($block, 0, max(0, $left)) : '';
                    $left -= mb_strlen($block);
                    $blocks[] = $block;
                }

                $refs = ws_refs_resolve($viewer, ws_tokens(implode("\n", $blocks)));
                $html = array();

                foreach ($blocks as $block) {
                    $html[] = ($block === '') ? '' : ws_render_body($block, $refs);
                }

                return ws_action_ok(array('blocks' => $html));
            }

            $body = mb_substr((string) ($request['body'] ?? ''), 0, WS_NOTE_MAX);

            // A Markdown file's headings read as bold lines; a message has no
            // headings, and "#" in it tags a record.
            if (!empty($request['markdown'])) {
                $body = ws_markdown_headings($body);
            }

            return ws_action_ok(array('html' => ws_render_body($body, ws_refs_resolve($viewer, ws_tokens($body)))));

        // Personal notes (includes/workspace/notes.php): the person's own and
        // the ones shared with them; nobody else's, whatever their role.
        case 'ws_notes':
            return ws_action_ok(array('ready' => ws_notes_ready()) + ws_notes_list($viewer, (string) ($request['search'] ?? '')));

        case 'ws_note_get':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'view');

            if (!$note) {
                return ws_action_error(lang('That note could not be found.'));
            }

            // Open in front of the person: what the inbox said about it is read.
            if (!empty($request['seen'])) {
                ws_note_mark_seen($viewer, $note['id']);
            }

            $present = ws_note_present($viewer, $note);
            $present['scheduled'] = function_exists('ws_scheduled_for_note') ? ws_scheduled_for_note($viewer, $note['id']) : array();

            return ws_action_ok(array('note' => $present));

        case 'ws_note_save':
            $data = array('note_id' => (int) ($request['note_id'] ?? 0));

            foreach (array('title', 'body', 'pinned', 'base') as $field) {
                if (array_key_exists($field, $request)) {
                    $data[$field] = $request[$field];
                }
            }

            $result = ws_note_save($viewer, $data);

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['conflict'] ? 'conflict' : '');
            }

            return ws_action_ok(array('note' => ws_note_present($viewer, ws_note_for($viewer, $result['note_id'], 'view'))));

        case 'ws_note_delete':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'owner');

            if (!$note) {
                return ws_action_error(lang('Only the one who wrote a note can delete it.'));
            }

            $result = ws_note_delete($viewer, $note);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_note_from_message':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_note_from_message($viewer, $message);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            return ws_action_ok(array('note_id' => $result['note_id'], 'existing' => $result['existing']));

        // Shared with colleagues (to read or to edit), or in a channel (to be
        // read there); a share taken back, or left by the one it was for.
        case 'ws_note_share':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'owner');

            if (!$note) {
                return ws_action_error(lang('Only the one who wrote a note can share it.'));
            }

            if ((int) ($request['channel_id'] ?? 0) > 0) {
                $channel = ws_action_channel($viewer, $request, 'post');

                if (!is_array($channel)) {
                    return ws_action_error($channel);
                }

                $result = ws_note_share_channel($viewer, $note, $channel);

                return $result['ok'] ? ws_action_ok(array('channel_id' => (int) $channel['id'], 'message_id' => $result['message_id'])) : ws_action_error($result['error']);
            }

            $result = ws_note_share_people($viewer, $note, (array) ($request['user_ids'] ?? array()), !empty($request['can_edit']));

            return $result['ok'] ? ws_action_ok(array('shared' => $result['shared'])) : ws_action_error($result['error']);

        case 'ws_note_unshare':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'view');

            if (!$note) {
                return ws_action_error(lang('That note could not be found.'));
            }

            $result = ws_note_unshare($viewer, $note, (int) ($request['share_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_note_channel':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'owner');

            if (!$note) {
                return ws_action_error(lang('Only the one who wrote a note can share it.'));
            }

            $result = ws_note_to_channel($viewer, $note, array(
                'name'          => $request['name'] ?? '',
                'kind'          => $request['kind'] ?? 'public',
                'topic'         => $request['topic'] ?? '',
                'contact_id'    => $request['contact_id'] ?? 0,
                'customer_type' => $request['customer_type'] ?? '',
                'customer_id'   => $request['customer_id'] ?? 0,
                'department_id' => $request['department_id'] ?? 0,
                'members'       => (array) ($request['members'] ?? array()),
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            return ws_action_ok(array('channel_id' => $result['channel_id'], 'warning' => $result['error']));

        // A file attached to a note, kept for those who may read it.
        case 'ws_note_file':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'edit');

            if (!$note) {
                return ws_action_error(lang('That note could not be found.'));
            }

            $result = ws_note_file_store($viewer, $note, (string) ($request['name'] ?? ''), (string) ($request['data'] ?? ''));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            return ws_action_ok(array('file' => array('id' => $result['id'], 'name' => $result['name'], 'url' => $result['url'], 'image' => $result['image'])));

        // Claude asked in a note, and its answer written into it.
        case 'ws_note_claude':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'edit');

            if (!$note) {
                return ws_action_error(lang('That note could not be found.'));
            }

            $asked = array();

            foreach (array_slice((array) ($request['lines'] ?? array()), 0, 5) as $line) {
                $result = ws_note_claude_ask($viewer, $note, (string) $line);

                if (!$result['ok']) {
                    return ws_action_error($result['error']);
                }

                $asked[] = $result['request_id'];
            }

            if (!empty($asked) && function_exists('ws_claude_dispatch')) {
                ws_claude_dispatch();
            }

            return ws_action_ok(array('requests' => $asked, 'claude' => ws_note_claude_state($viewer, $note['id'])));

        case 'ws_note_claude_deliver':
            $note = ws_note_for($viewer, (int) ($request['note_id'] ?? 0), 'edit');

            if (!$note) {
                return ws_action_error(lang('That note could not be found.'));
            }

            $changed = ws_note_claude_deliver($viewer, $note, (int) ($request['request_id'] ?? 0));

            return ws_action_ok(array('changed' => $changed, 'note' => ws_note_present($viewer, ws_note_for($viewer, $note['id'], 'view'))));

        // Text files in a conversation (includes/workspace/file_edits.php):
        // read, and saved over the person's own file or sent as an edited
        // copy of somebody else's.
        case 'ws_file_text':
            $target = ws_file_edit_target($viewer, (int) ($request['message_id'] ?? 0), 'text');

            if (!is_array($target)) {
                return ws_action_error($target);
            }

            list($message, $channel) = $target;
            $text = ws_file_text_read((int) $message['file_id'], true);

            if ($text === null) {
                return ws_action_error(lang('This file is too large, or is not text, to be opened here.'));
            }

            return ws_action_ok(array(
                'name'        => (string) $message['file_name'],
                'text'        => $text,
                'markdown'    => ws_file_is_markdown($message['file_name']),
                'can_replace' => ws_file_can_replace($viewer, $message, $channel),
                'can_send'    => ws_can_post_channel($viewer, $channel),
            ));

        case 'ws_file_text_save':
            $target = ws_file_edit_target($viewer, (int) ($request['message_id'] ?? 0), 'text');

            if (!is_array($target)) {
                return ws_action_error($target);
            }

            list($message, $channel) = $target;
            $text = str_replace("\r\n", "\n", (string) ($request['text'] ?? ''));

            if (strlen($text) > WS_FILE_TEXT_MAX) {
                return ws_action_error(lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => round(WS_FILE_TEXT_MAX / 1048576, 1))));
            }

            if (trim($text) === '') {
                return ws_action_error(lang('The file is empty.'));
            }

            if (($request['as'] ?? '') === 'replace') {
                $result = ws_message_file_replace($viewer, $message, $channel, (string) $message['file_name'], base64_encode($text));

                if (!$result['ok']) {
                    return ws_action_error($result['error']);
                }

                $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

                return ws_action_ok(array('message' => $payload[0]));
            }

            $result = ws_message_file_send($viewer, $message, $channel, (string) $message['file_name'], base64_encode($text));

            return $result['ok'] ? ws_action_ok(array('message_id' => $result['message_id'])) : ws_action_error($result['error']);

        case 'ws_file_history':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
                return ws_action_error(lang('That message could not be found.'));
            }

            return ws_action_ok(array('versions' => ws_file_history($message['id'])));

        // The overview: what is coming, what was written to the person, the
        // latest decisions and the channels they could join.
        case 'ws_home':
            return ws_action_ok(ws_home($viewer));

        case 'ws_bootstrap':
            $me = ws_people(array($viewer['id']));

            return ws_action_ok(array(
                'me'          => ($me[$viewer['id']] ?? array()) + array('rights' => array(
                    'assign'   => $viewer['assign'],
                    'board'    => $viewer['board'],
                    'settings' => $viewer['settings'],
                    'staff'    => ($viewer['role'] < 3),
                    'leads'    => ws_lead_departments($viewer['id']),
                )),
                'people'      => ws_team_members(),
                'departments' => array_values(ws_departments(true)),
                'channels'    => ws_channels_for($viewer),
                'groups'      => ws_groups_for($viewer),
                'threads'     => ws_threads_for($viewer),
                'inbox'       => ws_inbox_unread_count($viewer['id']),
                'statuses'    => ws_task_statuses(),
                'priorities'  => ws_task_priorities(),
                'ref_types'   => ws_ref_types($viewer),
                'commands'    => ws_command_help(),
                'upload'      => array('max' => ws_upload_max_bytes(), 'accept' => ''),
                'interact'    => ws_interact_ready(),
                'reactions'   => ws_reaction_quick(),
                'claude'      => function_exists('ws_claude_boot') ? ws_claude_boot() : null,
                'ai'          => function_exists('ws_ai_boot') ? ws_ai_boot() : null,
                'now'         => time(),
            ));

        case 'ws_channels':
            return ws_action_ok(array(
                'channels' => ws_channels_for($viewer),
                'groups'   => ws_groups_for($viewer),
                'threads'  => ws_threads_for($viewer),
                'archived' => !empty($request['archived']) ? ws_channels_for($viewer, true) : array(),
            ));

        // A channel's settings, asked for from the sidebar without opening
        // the conversation.
        case 'ws_channel_get':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            return ws_action_ok(array('channel' => ws_channel_detail($viewer, $channel)));

        case 'ws_channel_open':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            // A discussion opens beside its channel (threads.php): asked for
            // as a channel - a link, a search, a mention - it says where.
            if (ws_channel_is_thread($channel) && empty($request['as_thread'])) {
                return ws_action_ok(array('thread_of' => array(
                    'channel_id' => ws_thread_parent_id($channel),
                    'thread_id'  => (int) $channel['id'],
                    'message_id' => (int) ($request['message_id'] ?? 0),
                )));
            }

            ws_polls_autoclose($channel['id']);

            $around = (int) ($request['message_id'] ?? 0);

            // An earlier version of the conversation, asked for or holding
            // the message a link points at: read as it was, nothing marked
            // read (includes/workspace/eras.php).
            $era = ((int) ($request['era_id'] ?? 0) > 0) ? ws_channel_era($channel['id'], (int) $request['era_id']) : (($around > 0) ? ws_channel_era_of($channel['id'], $around) : null);

            if ($era) {
                $rows = ($around > 0) ? ws_messages_era_around($era, $around) : ws_messages_era_page($era, 0, 50);
                $detail = ws_channel_detail($viewer, $channel);
                $detail['can_post'] = false;

                return ws_action_ok(array(
                    'briefing' => null,
                    'channel'  => $detail,
                    'era'      => ws_era_present($era),
                    'messages' => ws_message_payloads($viewer, $rows),
                    'has_more' => !empty($rows) && ws_era_has_more($era, (int) $rows[0]['id']),
                    'last_id'  => empty($rows) ? 0 : (int) $rows[count($rows) - 1]['id'],
                    'now'      => time(),
                ));
            }

            $rows = ($around > 0) ? ws_messages_around($channel['id'], $around) : ws_messages_page($channel['id'], 0, 50);
            $last_id = empty($rows) ? 0 : (int) $rows[count($rows) - 1]['id'];

            if ($around > 0) {
                $newest = (int) db_value("SELECT MAX(id) FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "'");

                if ($newest > $last_id) {
                    $rows = array_merge($rows, ws_messages_since($channel['id'], $last_id));
                    $last_id = empty($rows) ? 0 : (int) $rows[count($rows) - 1]['id'];
                }
            }

            // The greeting reads the visit as it was before this one marked
            // anything read.
            $membership_before = ws_channel_membership($channel['id'], $viewer['id']);
            $briefing = ws_channel_briefing($viewer, $channel, $membership_before);

            if ($membership_before) {
                ws_channel_mark_read($channel['id'], $viewer['id'], $last_id);
            }

            return ws_action_ok(array(
                'briefing' => $briefing,
                'channel'  => ws_channel_detail($viewer, $channel),
                'messages' => ws_message_payloads($viewer, $rows),
                'has_more' => !empty($rows) && ((int) db_value("SELECT COUNT(*) FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "' AND id < '" . (int) $rows[0]['id'] . "' AND id > '" . ws_channel_floor($channel['id']) . "'") > 0),
                'era'      => null,
                'last_id'  => $last_id,
                'now'      => time(),
            ));

        case 'ws_sync':
            $out = array('messages' => array(), 'tasks' => array(), 'now' => time());
            $channel_id = (int) ($request['channel_id'] ?? 0);

            // The screen is in front of this person (see ws_notify()), and
            // banners whose wait is over go out.
            ws_touch_seen($viewer['id']);
            ws_push_due_run();

            if ($channel_id > 0) {
                $channel = ws_channel($channel_id);

                if ($channel && ws_can_read_channel($viewer, $channel)) {
                    ws_polls_autoclose($channel_id);

                    // An earlier version open on the screen takes nothing new.
                    $rows = ((int) ($request['era_id'] ?? 0) > 0) ? array() : ws_messages_since($channel_id, (int) ($request['since_id'] ?? 0));

                    // The pinned message, as somebody may have changed it.
                    $out['pin'] = ws_channel_pin_present($viewer, $channel, ws_channel_membership($channel_id, $viewer['id']));

                    // The marks on the tabs, every so often (the screen asks).
                    if (!empty($request['counts'])) {
                        $out['tab_counts'] = ws_channel_tab_counts($channel);
                    }

                    // The board open on the screen: a mark that moves when a
                    // card of it would (channel_board.php).
                    if (!empty($request['board']) && ws_channel_board_available($channel)) {
                        $out['board_stamp'] = ws_channel_board_stamp($channel);
                    }

                    // The reader's own messages waiting to be posted here.
                    if (ws_can_schedule_messages($viewer)) {
                        $out['scheduled_mine'] = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_actions
                            WHERE channel_id = '" . (int) $channel_id . "' AND kind = 'message' AND created_by = '" . (int) $viewer['id'] . "' AND status IN ('active', 'failed')");
                    }

                    // A guest's link and whether they are here (guests.php).
                    if (((string) $channel['kind'] === 'guest') && function_exists('ws_guest_channel_state')) {
                        $out['guest'] = ws_guest_channel_state($viewer, $channel);
                    } elseif (function_exists('ws_channel_shares_state')) {
                        $out['shares'] = ws_channel_shares_state($viewer, $channel);
                    }
                    $out['messages'] = ws_message_payloads($viewer, $rows);

                    // Messages the reader already has that changed since the
                    // last look: an edit, a reaction, a tick, a vote.
                    $out['changed'] = ws_message_payloads($viewer, ws_messages_touched($channel_id, (int) ($request['since_ts'] ?? 0), (int) ($request['since_id'] ?? 0)));

                    if (!empty($rows) && !empty($request['read']) && ws_channel_membership($channel_id, $viewer['id'])) {
                        ws_channel_mark_read($channel_id, $viewer['id'], (int) $rows[count($rows) - 1]['id']);
                    }

                    // Task cards in this channel that changed since the last
                    // look, drawn again with their new state.
                    $since = (int) ($request['since_ts'] ?? 0);

                    if ($since > 0) {
                        $changed = (array) db_items("SELECT t.* FROM ws_tasks t
                            INNER JOIN ws_messages m ON m.task_id = t.id AND m.channel_id = '" . $channel_id . "' AND m.kind = 'task'
                            WHERE t.updated_at >= '" . $since . "'
                            LIMIT 50");

                        $ids = array();

                        foreach ($changed as $task) {
                            $ids[] = (int) $task['id'];
                        }

                        $assignees = ws_task_assignees_map($ids);

                        foreach ($changed as $task) {
                            $out['tasks'][] = array('id' => (int) $task['id'], 'html' => ws_task_card_html($viewer, $task, $assignees[(int) $task['id']] ?? array()));
                        }
                    }
                } else {
                    $out['gone'] = true;
                }
            }

            // The discussion panel beside a channel leaves the sidebar to the
            // channel screen's own look.
            if (empty($request['panel'])) {
                $out['channels'] = ws_channels_for($viewer);
                $out['groups'] = ws_groups_for($viewer);
                $out['threads'] = ws_threads_for($viewer);
                $out['inbox'] = ws_inbox_unread_count($viewer['id']);
            }

            // A scheduled action is due: the screen starts a run (ws_tick)
            // rather than this answer waiting for an e-mail to go out.
            $out['scheduled_due'] = (function_exists('ws_scheduled_due') && ws_scheduled_due())
                || (function_exists('ws_task_reminders_due') && ws_task_reminders_due());

            return ws_action_ok($out);

        case 'ws_messages_before':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $era = ((int) ($request['era_id'] ?? 0) > 0) ? ws_channel_era($channel['id'], (int) $request['era_id']) : null;

            if ($era) {
                $rows = ws_messages_era_page($era, (int) ($request['before_id'] ?? 0), 50);

                return ws_action_ok(array(
                    'messages' => ws_message_payloads($viewer, $rows),
                    'has_more' => !empty($rows) && ws_era_has_more($era, (int) $rows[0]['id']),
                ));
            }

            $rows = ws_messages_page($channel['id'], (int) ($request['before_id'] ?? 0), 50);

            return ws_action_ok(array(
                'messages' => ws_message_payloads($viewer, $rows),
                'has_more' => !empty($rows) && ((int) db_value("SELECT COUNT(*) FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "' AND id < '" . (int) $rows[0]['id'] . "' AND id > '" . ws_channel_floor($channel['id']) . "'") > 0),
            ));

        case 'ws_send':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $body = (string) ($request['body'] ?? '');

            // "/claude ..." asks Claude, like writing @Claude.
            if (function_exists('ws_claude_command_body')) {
                $body = ws_claude_command_body($body);
            }

            // "/ai ..." asks Pinegrap AI, like writing @ai.
            if (function_exists('ws_ai_command_body')) {
                $body = ws_ai_command_body($body);
            }

            $command = ws_command_run($viewer, $channel, $body, array(
                'confirm'   => !empty($request['confirm']),
                'force'     => !empty($request['force']),
                'assignees' => isset($request['assignees']) ? (array) $request['assignees'] : null,
            ));

            if ($command !== null) {
                if ($command['type'] === 'error') {
                    return ws_action_error($command['error']);
                }

                return ws_action_ok(array('command' => $command));
            }

            $result = ws_message_send($viewer, $channel, $body, array(
                'parent_id' => (int) ($request['parent_id'] ?? 0),
                // An answer to several: the others after the parent.
                'quote_ids' => is_array($request['quote_ids'] ?? null) ? $request['quote_ids'] : array(),
            ));

            // A request to Claude is queued here and started by the screen's
            // next call (ws_claude_kick), so the message is on the screen
            // without waiting for claude.ai.
            $claude = false;

            if ($result['ok'] && function_exists('ws_claude_after_send')) {
                $claude = ws_claude_after_send($viewer, $channel, $result['message_id'], ws_tokens_normalise(trim($body)));
            }

            // The same for Pinegrap AI (ai.php): queued here, carried on by
            // the screen's next call (ws_ai_kick).
            $ai = false;

            if ($result['ok'] && function_exists('ws_ai_after_send')) {
                $ai = ws_ai_after_send($viewer, $channel, $result['message_id'], ws_tokens_normalise(trim($body)));
            }

            // Asked in a discussion: its channel's message says so.
            if (($claude || $ai) && ws_channel_is_thread($channel)) {
                ws_thread_assistant_asked($channel, $claude ? 'claude' : 'ai');
            }

            return $result['ok'] ? ws_action_ok(array('message_id' => $result['message_id'], 'claude' => $claude, 'ai' => $ai)) : ws_action_error($result['error']);

        case 'ws_edit':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_message_edit($viewer, $message, (string) ($request['body'] ?? ''));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_delete':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            // For the person alone, or for everyone (without a trace).
            $result = ((string) ($request['scope'] ?? '') === 'me') ? ws_message_hide($viewer, $message) : ws_message_delete($viewer, $message);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // Who has read as far as a message.
        case 'ws_message_seen':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_message_seen_by($viewer, $message);

            return $result['ok'] ? ws_action_ok(array('seen' => $result['seen'], 'unseen' => $result['unseen'])) : ws_action_error($result['error']);

        case 'ws_mark':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_message_mark($viewer, $message, (string) ($request['kind'] ?? ''));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_react':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
                return ws_action_error(lang('That message could not be found.'));
            }

            // "on" leaves the emoji whatever was there (the picker); without
            // it a click on an emoji left before takes it back.
            $on = isset($request['on']) ? !empty($request['on']) : null;
            $result = ws_reaction_set($viewer, $message, (string) ($request['emoji'] ?? ''), 0, $on);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_check':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_check_set($viewer, $message, (int) ($request['item'] ?? -1), !empty($request['checked']));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_poll_create':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            // The closing moment comes as a day and a time, as the form asks.
            $closes_at = 0;
            $closes_day = ws_date_or_null((string) ($request['closes_date'] ?? ''));

            if ($closes_day === false) {
                return ws_action_error(lang('Write the date as day.month.year.'), 'closes_date');
            }

            if ($closes_day !== null) {
                $closes_time = preg_match('/^[0-9]{1,2}:[0-9]{2}$/', (string) ($request['closes_time'] ?? '')) ? $request['closes_time'] : '18:00';
                $closes_at = (int) strtotime($closes_day . ' ' . $closes_time . ':00');
            }

            $result = ws_poll_create($viewer, $channel, array(
                'question'  => ws_tokens_normalise((string) ($request['question'] ?? '')),
                'options'   => (array) ($request['options'] ?? array()),
                'multiple'  => !empty($request['multiple']),
                'anonymous' => !empty($request['anonymous']),
                'closes_at' => $closes_at,
            ));

            return $result['ok']
                ? ws_action_ok(array('message_id' => $result['message_id'], 'poll_id' => $result['poll_id']))
                : ws_action_error($result['error'], $result['field']);

        case 'ws_poll_edit':
            $poll = ws_poll((int) ($request['poll_id'] ?? 0));

            if (!$poll || !ws_can_read_channel($viewer, ws_channel($poll['channel_id']))) {
                return ws_action_error(lang('That poll could not be found.'));
            }

            // The closing moment comes as a day and a time, as the form asks;
            // no day leaves it open until somebody closes it.
            $closes_at = 0;
            $closes_day = ws_date_or_null((string) ($request['closes_date'] ?? ''));

            if ($closes_day === false) {
                return ws_action_error(lang('Write the date as day.month.year.'), 'closes_date');
            }

            if ($closes_day !== null) {
                $closes_time = preg_match('/^[0-9]{1,2}:[0-9]{2}$/', (string) ($request['closes_time'] ?? '')) ? $request['closes_time'] : '18:00';
                $closes_at = (int) strtotime($closes_day . ' ' . $closes_time . ':00');
            }

            $result = ws_poll_edit($viewer, $poll, array(
                'question'  => (string) ($request['question'] ?? ''),
                'options'   => (array) ($request['options'] ?? array()),
                'multiple'  => !empty($request['multiple']),
                'anonymous' => !empty($request['anonymous']),
                'closes_at' => $closes_at,
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($poll['message_id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_poll_vote':
        case 'ws_poll_close':
            $poll = ws_poll((int) ($request['poll_id'] ?? 0));

            if (!$poll || !ws_can_read_channel($viewer, ws_channel($poll['channel_id']))) {
                return ws_action_error(lang('That poll could not be found.'));
            }

            $result = ($action === 'ws_poll_vote')
                ? ws_poll_vote($viewer, $poll, (array) ($request['option_ids'] ?? array()))
                : ws_poll_close($viewer, $poll);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($poll['message_id'])));

            return ws_action_ok(array('message' => $payload[0]));

        case 'ws_attach':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $stored = ws_store_upload($viewer, $channel, (string) ($request['name'] ?? ''), (string) ($request['data'] ?? ''));

            if (!$stored['ok']) {
                return ws_action_error($stored['error']);
            }

            $result = ws_message_send($viewer, $channel, (string) ($request['body'] ?? ''), array(
                'file_id'   => $stored['file_id'],
                'file_name' => $stored['name'],
            ));

            return $result['ok'] ? ws_action_ok(array('message_id' => $result['message_id'])) : ws_action_error($result['error']);

        // A conversation with a guest (guests.php): started, a new link
        // given, ended. Staff in the room only; the functions check.
        case 'ws_guest_start':
            $result = ws_guest_start($viewer, array(
                'guest_name' => $request['guest_name'] ?? '',
                'topic'      => $request['topic'] ?? '',
                'mode'       => $request['mode'] ?? 'once',
                'duration'   => $request['duration'] ?? 0,
                'members'    => (array) ($request['members'] ?? array()),
            ));

            return $result['ok'] ? ws_action_ok(array('channel_id' => $result['channel_id'], 'url' => $result['url'], 'qr' => ws_action_link_qr($result['url']))) : ws_action_error($result['error'], $result['field']);

        case 'ws_guest_relink':
        case 'ws_guest_end':
            $channel = ws_action_channel($viewer, $request, 'read');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ($action === 'ws_guest_relink')
                ? ws_guest_relink($viewer, $channel, (string) ($request['mode'] ?? 'once'), (int) ($request['duration'] ?? 0))
                : ws_guest_end($viewer, $channel);

            return $result['ok'] ? ws_action_ok(array('url' => (string) ($result['url'] ?? ''), 'qr' => ws_action_link_qr($result['url'] ?? ''))) : ws_action_error($result['error']);

        // Discussions (threads.php): started from a message, joined by staff
        // who read the channel, concluded, renamed, left.
        case 'ws_thread_start':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_thread_start($viewer, $message, (string) ($request['title'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('thread_id' => $result['thread_id'], 'existing' => $result['existing'])) : ws_action_error($result['error']);

        case 'ws_thread_join':
        case 'ws_thread_close':
        case 'ws_thread_rename':
        case 'ws_thread_leave':
            $channel = ws_channel((int) ($request['thread_id'] ?? 0));

            if (!ws_channel_is_thread($channel) || !ws_can_read_channel($viewer, ws_thread_parent_channel($channel))) {
                return ws_action_error(lang('That discussion could not be found.'));
            }

            if ($action === 'ws_thread_join') {
                $result = ws_thread_join($viewer, $channel);
            } elseif ($action === 'ws_thread_close') {
                $result = ws_thread_close($viewer, $channel);
            } elseif ($action === 'ws_thread_rename') {
                $result = ws_thread_rename($viewer, $channel, (string) ($request['title'] ?? ''));
            } else {
                $result = ws_channel_leave($viewer, $channel);
                $thread = ws_thread($channel['id']);

                // Nobody left in it: concluded, as if the last one had.
                if ($result['ok'] && $thread && ((int) $thread['closed_at'] === 0)
                    && ((int) db_value("SELECT COUNT(*) FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'") === 0)) {
                    db("UPDATE ws_threads SET closed_at = '" . time() . "', closed_by = '" . (int) $viewer['id'] . "',
                            purge_at = '" . strtotime('+' . WS_THREAD_KEEP_DAYS . ' days') . "'
                        WHERE channel_id = '" . (int) $channel['id'] . "'");
                    db("UPDATE ws_channels SET archived_at = '" . time() . "' WHERE id = '" . (int) $channel['id'] . "'");
                }

                if ($result['ok'] && $thread) {
                    ws_message_touch((int) $thread['message_id']);
                }
            }

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // A channel of the team shared with a guest (guests.php): shared, a
        // guest given a new link, a share ended.
        case 'ws_channel_share':
            $channel = ws_action_channel($viewer, $request, 'manage');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_share($viewer, $channel, array(
                'guest_name' => $request['guest_name'] ?? '',
                'access'     => $request['access'] ?? 'read',
                'mode'       => $request['mode'] ?? 'once',
                'duration'   => $request['duration'] ?? 0,
            ));

            return $result['ok'] ? ws_action_ok(array('url' => $result['url'], 'qr' => ws_action_link_qr($result['url']))) : ws_action_error($result['error'], $result['field']);

        case 'ws_share_relink':
        case 'ws_share_end':
            $channel = ws_action_channel($viewer, $request, 'manage');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_share_change($viewer, $channel, (int) ($request['guest_id'] ?? 0), ($action === 'ws_share_relink') ? 'relink' : 'end',
                (string) ($request['mode'] ?? 'once'), (int) ($request['duration'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('url' => (string) $result['url'], 'qr' => ws_action_link_qr($result['url']))) : ws_action_error($result['error']);

        case 'ws_channel_create':
            // Made from a template (templates.php): the template is looked up
            // before the channel is made, and applied once it is.
            $template = null;

            if (((string) ($request['template_id'] ?? '') !== '') && ((string) $request['template_id'] !== '0')) {
                $template = ws_template((string) $request['template_id']);

                if (!$template || $template['archived']) {
                    return ws_action_error(lang('That template could not be found.'));
                }
            }

            $result = ws_channel_create($viewer, array(
                'name'       => $request['name'] ?? '',
                'kind'       => $request['kind'] ?? 'public',
                'topic'      => $request['topic'] ?? '',
                'contact_id' => $request['contact_id'] ?? 0,
                'customer_type' => $request['customer_type'] ?? '',
                'customer_id' => $request['customer_id'] ?? 0,
                'department_id' => $request['department_id'] ?? 0,
                'members'    => (array) ($request['members'] ?? array()),
                'color'      => $request['color'] ?? 0,
                'group_id'   => $request['group_id'] ?? 0,
            ));

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            $made = array('channel_id' => $result['channel_id']);

            if ($template) {
                $applied = ws_template_apply($viewer, ws_channel($result['channel_id']), $template, array('new_channel' => true));
                $made[($applied['ok'] && !$applied['warning']) ? 'notice' : 'warning'] = $applied['ok'] ? $applied['message'] : $applied['error'];
            }

            return ws_action_ok($made);

        // Channel templates (includes/workspace/templates.php): the list for
        // the pickers, applying one to an open channel, and the draft a
        // channel makes for the template screen.
        case 'ws_templates':
            return ws_action_ok(array(
                'templates' => array_map('ws_template_present', ws_templates_list($viewer)),
                'manage'    => ws_can_write_templates($viewer),
            ));

        case 'ws_template_apply':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $template = ws_template((string) ($request['template_id'] ?? ''));

            if (!$template || $template['archived']) {
                return ws_action_error(lang('That template could not be found.'));
            }

            $result = ws_template_apply($viewer, $channel, $template);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            return ws_action_ok(array(
                'message'    => $result['message'],
                'warning'    => $result['warning'],
                'tasks'      => $result['tasks'],
                'notes'      => $result['notes'],
                'unassigned' => $result['unassigned'],
                'task_ids'   => $result['task_ids'],
            ));

        case 'ws_template_from_channel':
            if (!ws_can_write_templates($viewer)) {
                return ws_action_error(lang('Only staff and the people who may change the workspace settings can keep templates.'));
            }

            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            return ws_action_ok(array(
                'draft' => ws_template_from_channel($viewer, $channel),
                'url'   => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace_template.php?channel=' . (int) $channel['id'],
            ));

        case 'ws_channel_update':
            $channel = ws_action_channel($viewer, $request, 'manage');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $data = array();

            foreach (array('name', 'topic', 'contact_id', 'customer_type', 'customer_id', 'department_id', 'color', 'watch') as $field) {
                if (array_key_exists($field, $request)) {
                    $data[$field] = $request[$field];
                }
            }

            $result = ws_channel_update($viewer, $channel, $data);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error'], $result['field']);

        // A customer and the records tied to it, for the channel form to show
        // before it is saved (includes/workspace/customer.php).
        case 'ws_customer_links':
            $type = (string) ($request['type'] ?? '');

            if (!isset(ws_ref_types($viewer)[$type]) || !in_array($type, ws_customer_types(), true)) {
                return ws_action_error(lang('That record could not be found.'));
            }

            return ws_action_ok(array('customer' => ws_customer_present($viewer, $type, (int) ($request['id'] ?? 0))));

        case 'ws_channel_members_add':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            // Anyone in a private channel may bring a colleague in; nobody
            // outside it can.
            if (($channel['kind'] !== 'public') && !ws_channel_membership($channel['id'], $viewer['id'])) {
                return ws_action_error(lang('Access denied.'));
            }

            $added = ws_channel_add_members($viewer, $channel, (array) ($request['user_ids'] ?? array()));

            return ws_action_ok(array('added' => $added));

        // The people a message mentions who were not in its channel, brought
        // in; the mention reaches them with it.
        case 'ws_mention_invite':
            $message = ws_message((int) ($request['message_id'] ?? 0));

            if (!$message) {
                return ws_action_error(lang('That message could not be found.'));
            }

            $result = ws_message_mention_invite($viewer, $message, isset($request['user_ids']) ? (array) $request['user_ids'] : null);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $payload = ws_message_payloads($viewer, array(ws_message($message['id'])));

            return ws_action_ok(array('added' => $result['added'], 'message' => $payload[0]));

        case 'ws_channel_member_remove':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_leave($viewer, $channel, (int) ($request['user_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_join':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_join($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_leave':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_leave($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_make_public':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_make_public($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_make_private':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_make_private($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // The message pinned to the top of a channel (pins.php).
        case 'ws_pin':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_pin_message($viewer, $channel, (int) ($request['message_id'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('pin' => ws_channel_pin_present($viewer, ws_channel($channel['id']), ws_channel_membership($channel['id'], $viewer['id'])))) : ws_action_error($result['error']);

        case 'ws_pin_hide':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_pin_hide($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // Titled blocks: found by their title, pulled as a copy (blocks.php).
        case 'ws_block_search':
            return ws_action_ok(array('items' => ws_blocks_search($viewer, (string) ($request['q'] ?? ''), 20)));

        case 'ws_block_pull':
            $result = ws_block_pull($viewer, (int) ($request['id'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('kind' => $result['kind'], 'markup' => $result['markup'], 'from' => $result['from'])) : ws_action_error($result['error']);

        // Messages chosen together, forwarded (forward.php).
        case 'ws_forward':
            $ids = is_array($request['message_ids'] ?? null) ? $request['message_ids'] : array();

            if ((string) ($request['target'] ?? 'channel') === 'note') {
                $result = ws_forward_to_note($viewer, $ids, (int) ($request['note_id'] ?? 0), (string) ($request['comment'] ?? ''));

                return $result['ok'] ? ws_action_ok(array('note_id' => $result['note_id'])) : ws_action_error($result['error']);
            }

            $target = ws_channel((int) ($request['channel_id'] ?? 0));

            if (!$target || !ws_can_read_channel($viewer, $target)) {
                return ws_action_error(lang('That channel could not be found.'));
            }

            $result = ws_forward_to_channel($viewer, $ids, $target, (string) ($request['comment'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('message_id' => $result['message_id'], 'channel_id' => (int) $target['id'])) : ws_action_error($result['error']);

        // Versions of a conversation (includes/workspace/eras.php).
        case 'ws_channel_eras':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $first = (int) db_value("SELECT MIN(id) FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "' AND id > '" . ws_channel_floor($channel['id']) . "'");

            $since = ($first > 0) ? (int) db_value("SELECT created_at FROM ws_messages WHERE id = '" . $first . "'") : 0;

            return ws_action_ok(array(
                'eras'      => array_map('ws_era_present', ws_channel_eras($channel['id'])),
                'since'     => $since,
                'since_text' => ($since > 0) ? trim(strip_tags(get_absolute_time(array('timestamp' => $since, 'type' => 'date', 'size' => 'short')))) : '',
                'can_clear' => ws_can_clear_channel($viewer, $channel),
            ));

        case 'ws_channel_clear':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_clear($viewer, $channel, (string) ($request['title'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('era' => $result['era'])) : ws_action_error($result['error']);

        case 'ws_channel_era_rename':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $era = ws_channel_era($channel['id'], (int) ($request['era_id'] ?? 0));

            if (!$era) {
                return ws_action_error(lang('That version of the conversation could not be found.'));
            }

            $result = ws_channel_era_rename($viewer, $channel, $era, (string) ($request['title'] ?? ''));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // Groups of channels (includes/workspace/groups.php).
        case 'ws_group_save':
            $result = ws_group_save($viewer, array_intersect_key($request, array_flip(array('id', 'name', 'color', 'parent_id'))));

            return $result['ok'] ? ws_action_ok(array('id' => $result['id'], 'groups' => ws_groups_for($viewer))) : ws_action_error($result['error'], $result['field']);

        case 'ws_group_move':
            $result = ws_group_move($viewer, (int) ($request['group_id'] ?? 0), (int) ($request['parent_id'] ?? 0), (int) ($request['before_id'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('groups' => ws_groups_for($viewer), 'channels' => ws_channels_for($viewer))) : ws_action_error($result['error']);

        case 'ws_group_remove':
            $result = ws_group_remove($viewer, (int) ($request['group_id'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('groups' => ws_groups_for($viewer), 'channels' => ws_channels_for($viewer))) : ws_action_error($result['error']);

        case 'ws_group_access':
            if (!ws_can_manage_groups($viewer)) {
                return ws_action_error(lang('Only staff can give access on a group.'));
            }

            $group = ws_group((int) ($request['group_id'] ?? 0));

            if (!$group) {
                return ws_action_error(lang('That group could not be found.'));
            }

            $above = array();

            foreach (array_slice(ws_group_chain($group['id']), 1) as $parent_id) {
                foreach (ws_group_access_people($parent_id) as $person) {
                    $above[] = $person + array('group' => ws_group_path($parent_id));
                }
            }

            // Who would come in if these were given access: asked while the
            // people are being chosen.
            $preview = ws_group_access_preview($group['id'], (array) ($request['user_ids'] ?? array()));

            return ws_action_ok(array(
                'people'  => ws_group_access_people($group['id']),
                'above'   => $above,
                'reach'   => $preview['reach'],
                'joining' => $preview['joining'],
                'path'    => ws_group_path($group['id']),
            ));

        case 'ws_group_access_grant':
            $result = ws_group_access_grant($viewer, (int) ($request['group_id'] ?? 0), (array) ($request['user_ids'] ?? array()));

            return $result['ok'] ? ws_action_ok(array('added' => $result['added'], 'groups' => ws_groups_for($viewer))) : ws_action_error($result['error']);

        case 'ws_group_access_revoke':
            $result = ws_group_access_revoke($viewer, (int) ($request['group_id'] ?? 0), (array) ($request['user_ids'] ?? array()));

            return $result['ok'] ? ws_action_ok(array('removed' => $result['removed'], 'groups' => ws_groups_for($viewer))) : ws_action_error($result['error']);

        case 'ws_channel_group':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_move_group($viewer, $channel, (int) ($request['group_id'] ?? 0), !empty($request['preview']));

            return $result['ok']
                ? ws_action_ok(array('joining' => $result['joining'], 'leaving' => $result['leaving'], 'path' => $result['path'], 'private' => ($channel['kind'] === 'private')))
                : ws_action_error($result['error']);

        case 'ws_channel_archive':
            $channel = ws_channel((int) ($request['channel_id'] ?? 0));

            if (!$channel || !ws_can_read_channel($viewer, $channel)) {
                return ws_action_error(lang('That channel could not be found.'));
            }

            $result = ws_channel_archive($viewer, $channel, !empty($request['archive']));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_audit':
            $channel = ws_channel((int) ($request['channel_id'] ?? 0));

            if (!$channel) {
                return ws_action_error(lang('That channel could not be found.'));
            }

            $result = ws_channel_audit_open($viewer, $channel);

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_audit_list':
            return ws_action_ok(array('channels' => ws_private_channels_for_audit($viewer)));

        // The decision timeline: a page of decisions from every channel the
        // person may read, and on the first page what the filters cover.
        case 'ws_timeline':
            return ws_action_ok(ws_timeline_page($viewer, $request));

        case 'ws_channel_pin':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_pin($viewer, $channel, !empty($request['pinned']));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_order':
            $result = ws_channel_order($viewer, array_map('intval', (array) ($request['ids'] ?? array())));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_notify':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_set_notify($viewer, $channel, (string) ($request['notify'] ?? ''));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // Whether Claude may be asked in a channel.
        case 'ws_channel_claude':
            $channel = ws_action_channel($viewer, $request, 'manage');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_claude_channel_set($viewer, $channel, !empty($request['allowed']));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // A task Claude proposed, opened or set aside by somebody in the channel.
        case 'ws_ai_draft_accept':
            $result = ws_claude_draft_accept($viewer, (int) ($request['draft_id'] ?? 0));

            return $result['ok'] ? ws_action_ok(array('task_id' => $result['task_id'])) : ws_action_error($result['error']);

        case 'ws_ai_draft_dismiss':
            $result = ws_claude_draft_dismiss($viewer, (int) ($request['draft_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // A record change Claude proposed: applied by the person who asked,
        // with their own rights, or set aside.
        case 'ws_ai_change_apply':
            $result = ws_change_apply($viewer, (int) ($request['change_id'] ?? 0));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            return ws_action_ok(array('decision_id' => $result['decision_id']));

        // The next slice of a bulk change being applied (bulk.php): the
        // screen of the person who applied it carries it on.
        case 'ws_ai_bulk_step':
            $change = db_item("SELECT requested_by FROM ws_ai_changes WHERE id = '" . (int) ($request['change_id'] ?? 0) . "' AND action = 'bulk'");

            if (!is_array($change) || ((int) $change['requested_by'] !== (int) $viewer['id'])) {
                return ws_action_error(lang('That proposal could not be found.'));
            }

            return ws_action_ok(ws_bulk_step((int) ($request['change_id'] ?? 0)));

        case 'ws_ai_change_dismiss':
            $result = ws_change_dismiss($viewer, (int) ($request['change_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // A page change an answer proposes: written to the saved page by the
        // person who asked, taken back by whoever applied it, or set aside.
        case 'ws_ai_design_apply':
        case 'ws_ai_design_revert':
        case 'ws_ai_design_dismiss':
            if (!ws_design_ai()) {
                return ws_action_error(lang('Sorry, we could not accept your request.'));
            }

            $result = pg_design_ai_ws_action($viewer, substr($action, strlen('ws_ai_design_')), (int) ($request['proposal_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        // Starts a run for what is queued, after a message asked Claude. Does
        // nothing when the queue is empty or a run is under way, so a second
        // call costs a query, never a second run.
        case 'ws_claude_kick':
            $dispatch = function_exists('ws_claude_dispatch') ? ws_claude_dispatch() : array('status' => 'idle');

            return ws_action_ok(array('status' => (string) $dispatch['status']));

        // Carries the requests to Pinegrap AI on: one model call, then back.
        // A call that finds the work under way on another screen returns at
        // once (ai.php).
        case 'ws_ai_kick':
            $kick = function_exists('ws_ai_kick') ? ws_ai_kick() : array('status' => 'idle');

            return ws_action_ok(array('status' => (string) $kick['status']));

        // Whether Pinegrap AI may be asked in a channel.
        case 'ws_channel_ai':
            $channel = ws_action_channel($viewer, $request, 'manage');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            if (!function_exists('ws_ai_channel_set')) {
                return ws_action_error(lang('Invalid request.'));
            }

            $result = ws_ai_channel_set($viewer, $channel, !empty($request['allowed']));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_channel_summary':
            $channel = ws_action_channel($viewer, $request, 'post');

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            $result = ws_channel_set_summary($viewer, $channel, (string) ($request['summary'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('channel' => ws_channel_detail($viewer, ws_channel($channel['id'])))) : ws_action_error($result['error']);

        case 'ws_channel_decisions':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            return ws_action_ok(array('messages' => ws_channel_decisions($viewer, $channel['id'])));

        case 'ws_channel_files':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            return ws_action_ok(array('messages' => ws_channel_files($viewer, $channel['id'])));

        case 'ws_channel_tasks':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            return ws_action_ok(array('tasks' => ws_tasks_list($viewer, array(
                'scope'      => 'channel',
                'channel_id' => $channel['id'],
                'status'     => (string) ($request['status'] ?? 'all'),
            ))));

        case 'ws_search':
            return ws_action_ok(array('messages' => ws_message_search($viewer, (string) ($request['q'] ?? ''), (int) ($request['channel_id'] ?? 0))));

        // The search box of every workspace screen (palette.php): channel_id
        // keeps it to one channel, here is the channel open on the screen.
        case 'ws_palette':
            return ws_action_ok(ws_palette_search($viewer, (string) ($request['q'] ?? ''), array(
                'channel_id' => (int) ($request['channel_id'] ?? 0),
                'here'       => (int) ($request['here'] ?? 0),
                'types'      => is_array($request['types'] ?? null) ? $request['types'] : array(),
                'limit'      => (int) ($request['limit'] ?? WS_PALETTE_LIMIT),
            )));

        // A channel's tasks, decisions and files as a board
        // (channel_board.php).
        case 'ws_channel_board':
            $channel = ws_action_channel($viewer, $request);

            if (!is_array($channel)) {
                return ws_action_error($channel);
            }

            if (!ws_channel_board_available($channel)) {
                return ws_action_error(lang('This channel has no board.'));
            }

            return ws_action_ok(ws_channel_board($viewer, $channel, array(
                'group_by'  => (string) ($request['group_by'] ?? 'status'),
                'priority'  => (string) ($request['priority'] ?? ''),
                'person'    => (int) ($request['person'] ?? 0),
                'mine'      => !empty($request['mine']),
                'q'         => (string) ($request['q'] ?? ''),
                'cancelled' => !empty($request['cancelled']),
                'done_all'  => !empty($request['done_all']),
            )));

        case 'ws_ref_search':
            $only = is_array($request['types'] ?? null) ? array_values(array_map('strval', $request['types'])) : null;

            return ws_action_ok(array('items' => ws_ref_search($viewer, (string) ($request['type'] ?? ''), (string) ($request['q'] ?? ''), 8, $only)));

        case 'ws_record_refs':
            $type = (string) ($request['type'] ?? '');

            if (!in_array($type, ws_record_type_keys(), true)) {
                return ws_action_error(lang('Invalid request.'));
            }

            $refs = ws_record_refs($viewer, $type, (int) ($request['id'] ?? 0));

            // The channels this person could share the record in.
            $refs['post_channels'] = array();

            foreach (ws_channels_for($viewer) as $channel) {
                if ($channel['joined'] || ($channel['kind'] === 'public')) {
                    $refs['post_channels'][] = array('id' => $channel['id'], 'name' => $channel['name'], 'kind' => $channel['kind']);
                }
            }

            return ws_action_ok($refs);

        case 'ws_task_get':
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That task could not be found.'));
            }

            return ws_action_ok(array('task' => ws_task_detail($viewer, $task)));

        case 'ws_task_check':
            $fields = ws_action_task_fields($request);
            $task = array(
                'id'               => (int) ($request['task_id'] ?? 0),
                'start_date'       => ws_date_or_null($fields['start_date'] ?? '') ?: null,
                'due_date'         => ws_date_or_null($fields['due_date'] ?? '') ?: null,
                'estimate_minutes' => (int) ($fields['estimate_minutes'] ?? 0),
                'department_id'    => (int) ($fields['department_id'] ?? 0),
                'title'            => (string) ($fields['title'] ?? ''),
            );

            $check = ws_assignment_check($viewer, $task, $fields['assignees'] ?? array());
            $check['may_override'] = ws_may_override($viewer, $task['department_id']);

            return ws_action_ok(array('check' => $check));

        case 'ws_task_save':
            $fields = ws_action_task_fields($request);
            $task_id = (int) ($request['task_id'] ?? 0);
            $current = ($task_id > 0) ? ws_task($task_id) : null;

            if (($task_id > 0) && (!$current || !ws_can_see_task($viewer, $current))) {
                return ws_action_error(lang('That task could not be found.'));
            }

            // The repeat is checked before anything is written, and set once
            // the task is saved (ws_recurrence_save()).
            $recurrence = (array_key_exists('recurrence', $request) && function_exists('ws_recurrence_validate'))
                ? $request['recurrence'] : null;

            if ($recurrence !== null) {
                $recurrence_check = ws_recurrence_validate($recurrence);

                if (!$recurrence_check['ok']) {
                    return ws_action_error($recurrence_check['error'], $recurrence_check['field']);
                }
            }

            // What the task would look like after the save, for the check.
            $after = array(
                'id'               => $task_id,
                'start_date'       => array_key_exists('start_date', $fields) ? (ws_date_or_null($fields['start_date']) ?: null) : ($current['start_date'] ?? null),
                'due_date'         => array_key_exists('due_date', $fields) ? (ws_date_or_null($fields['due_date']) ?: null) : ($current['due_date'] ?? null),
                'estimate_minutes' => array_key_exists('estimate_minutes', $fields) ? (int) $fields['estimate_minutes'] : (int) ($current['estimate_minutes'] ?? 0),
                'department_id'    => array_key_exists('department_id', $fields) ? (int) $fields['department_id'] : (int) ($current['department_id'] ?? 0),
                'title'            => (string) ($fields['title'] ?? ($current['title'] ?? '')),
            );
            $people = array_key_exists('assignees', $fields) ? $fields['assignees'] : ($current ? ws_task_assignee_ids($task_id) : array());
            $status = (string) ($fields['status'] ?? ($current['status'] ?? 'todo'));

            if (ws_task_is_open($status) && empty($request['force'])) {
                $check = ws_assignment_check($viewer, $after, $people);

                if (!empty($check['items'])) {
                    $check['may_override'] = ws_may_override($viewer, $after['department_id']);

                    return ws_action_ok(array('needs_confirm' => true, 'check' => $check));
                }
            } elseif (ws_task_is_open($status)) {
                $check = ws_assignment_check($viewer, $after, $people);

                if ($check['hard'] && !ws_may_override($viewer, $after['department_id'])) {
                    return ws_action_error(lang('Somebody on this task is away then. Only a lead or somebody with the team board can hand it over anyway.'));
                }
            }

            if ($current) {
                $result = ws_task_update($viewer, $current, $fields);

                // The task as it was goes along: a due date moved on the
                // newest copy moves its series.
                if ($result['ok'] && ($recurrence !== null)) {
                    $repeat = ws_recurrence_save($viewer, ws_task($task_id), $recurrence, $current);

                    if (!$repeat['ok']) {
                        return ws_action_error($repeat['error'], $repeat['field']);
                    }
                }

                // Whether every copy of the series is reminded (reminders.php).
                if ($result['ok'] && array_key_exists('remind_each', $request) && function_exists('ws_task_reminder_series_set')) {
                    ws_task_reminder_series_set($task_id, !empty($request['remind_each']));
                }

                return $result['ok'] ? ws_action_ok(array('task_id' => $task_id)) : ws_action_error($result['error'], $result['field']);
            }

            $result = ws_task_create($viewer, $fields);

            if (!$result['ok']) {
                return ws_action_error($result['error'], $result['field']);
            }

            if ($recurrence !== null) {
                $repeat = ws_recurrence_save($viewer, ws_task($result['task_id']), $recurrence);

                if (!$repeat['ok']) {
                    return ws_action_error($repeat['error'], $repeat['field']);
                }
            }

            if (array_key_exists('remind_each', $request) && function_exists('ws_task_reminder_series_set')) {
                ws_task_reminder_series_set($result['task_id'], !empty($request['remind_each']));
            }

            // Made from a channel's task tab or from a record's drawer: the
            // channel gets the card. A checklist that became the task gets it
            // as a reply, under the list.
            $channel_id = (int) ($fields['channel_id'] ?? 0);

            if ($channel_id > 0) {
                $channel = ws_channel($channel_id);
                $thread_id = (int) ($request['thread_id'] ?? 0);

                // Made in a discussion of that channel: the card is posted in
                // the discussion, and ws_thread_copy_sync() writes its copy
                // into the channel and makes the copy the task's message.
                if (($thread_id > 0) && function_exists('ws_channel_is_thread')) {
                    $thread = ws_channel($thread_id);

                    if (ws_channel_is_thread($thread) && (ws_thread_parent_id($thread) === $channel_id) && ws_can_post_channel($viewer, $thread)) {
                        $channel = $thread;
                    }
                }

                $list_id = (int) ($fields['checklist_message_id'] ?? 0);
                $parent_id = (($list_id > 0) && is_array($channel) && ((int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . $list_id . "'") === (int) $channel['id'])) ? $list_id : 0;
                $sent = ws_message_send($viewer, $channel, '', array('kind' => 'task', 'task_id' => $result['task_id'], 'parent_id' => $parent_id));

                // In a discussion the task's message is the channel's copy
                // of the card (threads.php), which outlives the discussion.
                if ($sent['ok'] && !ws_channel_is_thread($channel)) {
                    db("UPDATE ws_tasks SET source_message_id = '" . (int) $sent['message_id'] . "' WHERE id = '" . (int) $result['task_id'] . "'");
                }
            }

            return ws_action_ok(array('task_id' => $result['task_id']));

        // A tick on a task's own checklist (its description's items).
        case 'ws_task_tick':
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That task could not be found.'));
            }

            $result = ws_task_check_set($viewer, $task, (int) ($request['item'] ?? -1), !empty($request['checked']));

            return $result['ok'] ? ws_action_ok(array('task' => ws_task_detail($viewer, ws_task($task['id'])))) : ws_action_error($result['error']);

        // One more item at the end of the task's own checklist.
        case 'ws_task_item_add':
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That task could not be found.'));
            }

            $result = ws_task_item_add($viewer, $task, (string) ($request['text'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('task' => ws_task_detail($viewer, ws_task($task['id'])))) : ws_action_error($result['error'], 'text');

        case 'ws_task_note_add':
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That task could not be found.'));
            }

            $result = ws_task_note_add($viewer, $task, (string) ($request['body'] ?? ''));

            return $result['ok'] ? ws_action_ok(array('task' => ws_task_detail($viewer, ws_task($task['id'])))) : ws_action_error($result['error'], 'body');

        case 'ws_task_note_edit':
        case 'ws_task_note_delete':
            $note = ws_task_note((int) ($request['note_id'] ?? 0));
            $task = $note ? ws_task($note['task_id']) : null;

            if (!$note || ((int) $note['deleted_at'] > 0) || !$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That note could not be found.'));
            }

            $result = ($action === 'ws_task_note_edit')
                ? ws_task_note_edit($viewer, $task, $note, (string) ($request['body'] ?? ''))
                : ws_task_note_delete($viewer, $task, $note);

            return $result['ok'] ? ws_action_ok(array('task' => ws_task_detail($viewer, ws_task($task['id'])))) : ws_action_error($result['error'], 'body');

        case 'ws_task_recurrence_end':
            // Completing a repeating task for good, or only stopping its
            // copies (includes/workspace/recurrence.php).
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task) || !function_exists('ws_recurrence_end')) {
                return ws_action_error(lang('That task could not be found.'));
            }

            $result = ws_recurrence_end($viewer, $task, ((string) ($request['mode'] ?? '') === 'complete') ? 'complete' : 'stop');

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            return ws_action_ok(array('task' => ws_task_detail($viewer, ws_task($task['id']))));

        case 'ws_task_status':
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_see_task($viewer, $task)) {
                return ws_action_error(lang('That task could not be found.'));
            }

            $result = ws_task_set_status($viewer, $task, (string) ($request['status'] ?? ''));

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            $task = ws_task($task['id']);

            return ws_action_ok(array(
                'task' => ws_task_brief($task, ws_task_assignee_ids($task['id'])),
                'html' => ws_task_card_html($viewer, $task, ws_task_assignee_ids($task['id'])),
            ));

        case 'ws_task_move':
            // The board's drag and drop: onto another day, or onto another
            // person's row. series 'following' takes the copies after the
            // newest copy of a repeating task along.
            $task = ws_task((int) ($request['task_id'] ?? 0));

            if (!$task || !ws_can_edit_task($viewer, $task)) {
                return ws_action_error(lang('You cannot change this task.'));
            }

            $date = ws_date_or_null($request['date'] ?? '');

            if (!$date) {
                return ws_action_error(lang('Invalid request.'));
            }

            $fields = array('due_date' => $date);

            if ($task['start_date'] && $task['due_date']) {
                $shift = (int) round((strtotime($date . ' 12:00:00') - strtotime($task['due_date'] . ' 12:00:00')) / 86400);
                $fields['start_date'] = date('Y-m-d', strtotime($task['start_date'] . ' 12:00:00 ' . (($shift >= 0) ? '+' : '') . $shift . ' days'));
            } elseif ($task['start_date']) {
                $fields['start_date'] = null;
            }

            $people = ws_task_assignee_ids($task['id']);
            $from_user = (int) ($request['from_user_id'] ?? 0);
            $to_user = (int) ($request['to_user_id'] ?? 0);

            if (($to_user > 0) && ($to_user !== $from_user)) {
                $people = array_values(array_unique(array_merge(array_diff($people, array($from_user)), array($to_user))));
                $fields['assignees'] = $people;
            }

            $after = array_merge($task, $fields);

            if (empty($request['force'])) {
                $check = ws_assignment_check($viewer, $after, $people);

                if (!empty($check['items'])) {
                    $check['may_override'] = ws_may_override($viewer, $task['department_id']);

                    return ws_action_ok(array('needs_confirm' => true, 'check' => $check));
                }
            }

            $result = ws_task_update($viewer, $task, $fields);

            if (!$result['ok']) {
                return ws_action_error($result['error']);
            }

            if (function_exists('ws_recurrence_moved')) {
                ws_recurrence_moved($viewer, $task, ws_task($task['id']), ((string) ($request['series'] ?? '') === 'following'));
            }

            return ws_action_ok();

        case 'ws_tasks':
            $filters = array(
                'scope'         => (string) ($request['scope'] ?? 'mine'),
                'status'        => (string) ($request['status'] ?? 'open'),
                'department_id' => (int) ($request['department_id'] ?? 0),
                'search'        => (string) ($request['search'] ?? ''),
                'priority'      => (string) ($request['priority'] ?? ''),
                'soon'          => min(90, max(0, (int) ($request['soon'] ?? 0))),
            );

            // "Everything" is the board holder's view.
            if (($filters['scope'] === 'all') && !$viewer['board']) {
                $filters['scope'] = 'mine';
            }

            return ws_action_ok(array('tasks' => ws_tasks_list($viewer, $filters)));

        case 'ws_board':
            return ws_action_ok(ws_board($viewer, (string) ($request['from'] ?? ''), (int) ($request['days'] ?? 7), (int) ($request['department_id'] ?? 0), !empty($request['only_me']), (int) ($request['person_id'] ?? 0)));

        case 'ws_calendar':
            return ws_action_ok(ws_calendar($viewer, (string) ($request['month'] ?? ''), (int) ($request['person_id'] ?? 0), (int) ($request['department_id'] ?? 0)));

        case 'ws_conflicts':
            $from = ws_date_or_null($request['from'] ?? '') ?: date('Y-m-d');
            $to = ws_date_or_null($request['to'] ?? '') ?: date('Y-m-d', strtotime('+13 days'));

            return ws_action_ok(array('conflicts' => ws_conflicts($viewer, $from, $to)));

        case 'ws_event_save':
            $result = ws_event_save($viewer, $request);

            return $result['ok'] ? ws_action_ok(array('event_id' => $result['event_id'])) : ws_action_error($result['error'], $result['field']);

        case 'ws_event_get':
            $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) ($request['event_id'] ?? 0) . "'");

            if (!is_array($event)) {
                return ws_action_error(lang('Invalid request.'));
            }

            $event['people'] = array_map('intval', (array) db_values("SELECT user_id FROM ws_event_people WHERE event_id = '" . (int) $event['id'] . "'"));
            $event['start'] = date('Y-m-d', (int) $event['starts_at']);
            $event['end'] = date('Y-m-d', (int) $event['ends_at']);
            $event['start_time'] = date('H:i', (int) $event['starts_at']);
            $event['end_time'] = date('H:i', (int) $event['ends_at']);
            $event['can_edit'] = ws_can_edit_event($viewer, $event);

            return ws_action_ok(array('event' => $event));

        case 'ws_event_delete':
            $result = ws_event_delete($viewer, (int) ($request['event_id'] ?? 0));

            return $result['ok'] ? ws_action_ok() : ws_action_error($result['error']);

        case 'ws_inbox':
            return ws_action_ok(array('items' => ws_inbox_list($viewer), 'unread' => ws_inbox_unread_count($viewer['id'])));

        case 'ws_inbox_read':
            ws_inbox_mark_read($viewer['id'], (($request['ids'] ?? '') === 'all') ? 'all' : (array) ($request['ids'] ?? array()));

            return ws_action_ok();
    }

    return ws_action_error(lang('Invalid request.'));
}
