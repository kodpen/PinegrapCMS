<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the site's end of a live page's data bridge. A page asks its
 * shell (pg_guarded_frame_bridge_script()), the shell asks the panel, and
 * the page card (assets/js/workspace_page_card.js) sends the panel action
 * ws_page_call with the id of the canvas whose card made that shell: the
 * page never names a canvas. Here a call is checked and answered with the
 * rights of the person looking.
 *
 * The calls: who (the reader's display name). The tables of a canvas come
 * with its bindings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');

/**
 * The calls a page may make.
 *
 * @return string[]
 */
function ws_page_bridge_calls()
{
    return array('who');
}

/**
 * A refused call as the bridge answers it.
 *
 * @param string $code invalid, limit, unknown_call, not_found
 * @param string $text
 * @return array ok false, error {code, text}
 */
function ws_page_bridge_error($code, $text = '')
{
    return array('ok' => false, 'error' => array('code' => (string) $code, 'text' => (string) $text));
}

/**
 * Checks a call before it is answered: its name and its arguments, sent by
 * the panel as JSON text (the shell already bounded them; the site checks
 * again, as it trusts no browser). Pure.
 *
 * @param mixed $call
 * @param mixed $args_json
 * @return array ok true with call and args (array), or ws_page_bridge_error()
 */
function ws_page_bridge_request($call, $args_json)
{
    if (!is_string($call) || !preg_match('/^[a-z][a-z_]{0,23}\z/', $call)) {
        return ws_page_bridge_error('invalid', lang('The request is not valid.'));
    }

    $args_json = is_string($args_json) ? $args_json : '';

    if ($args_json === '') {
        $args_json = '{}';
    }

    if (strlen($args_json) > PG_GUARDED_FRAME_BRIDGE_MAX_ARGS) {
        return ws_page_bridge_error('limit', lang('The request is too large.'));
    }

    $args = json_decode($args_json, true);

    // A JSON object, possibly empty; a list or a scalar is refused.
    if (!is_array($args) || (substr(ltrim($args_json), 0, 1) !== '{')) {
        return ws_page_bridge_error('invalid', lang('The request is not valid.'));
    }

    if (!in_array($call, ws_page_bridge_calls(), true)) {
        return ws_page_bridge_error('unknown_call', lang('A live page cannot ask for that.'));
    }

    return array('ok' => true, 'call' => $call, 'args' => $args);
}

/**
 * Answers one call of a live page for the person looking, after
 * ws_page_bridge_request(). The message is the canvas the panel names,
 * never one the page names.
 *
 * @param array $viewer  ws_rights()
 * @param array $message ws_message()
 * @param mixed $call
 * @param mixed $args_json
 * @return array ok true with data, or ws_page_bridge_error()
 */
function ws_page_bridge_answer($viewer, $message, $call, $args_json)
{
    if (!is_array($message) || ((int) ($message['deleted_at'] ?? 0) > 0) || ((int) ($message['file_id'] ?? 0) <= 0)
        || !$viewer['member'] || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))
        || !ws_page_is_page((int) $message['file_id'])) {
        return ws_page_bridge_error('not_found', lang('That message could not be found.'));
    }

    $request = ws_page_bridge_request($call, $args_json);

    if (!$request['ok']) {
        return $request;
    }

    // who: the name the reader is shown by, nothing else of them.
    return array('ok' => true, 'data' => array('name' => ws_person_name((int) $viewer['id'])));
}

/**
 * The texts of the bridge on the page card.
 *
 * @return array key => text
 */
function ws_page_bridge_js_strings()
{
    return array(
        'page_stopped'    => lang('The page was stopped: it asked the site too often.'),
        'page_open_again' => lang('Open again'),
    );
}
