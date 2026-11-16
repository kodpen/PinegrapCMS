<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guest's door for a comment on a prototype opened by its share link
 * (includes/fn/prototype_comments.php).
 *
 * The viewer around the prototype's frame posts here, never the frame: the
 * frame only names a place, and the viewer sends it with the name and the
 * text the guest typed. A post names the link by its id (l), never by its
 * token, and is taken only
 *  - as a POST from this site's own pages (pg_consent_answer_same_origin()),
 *  - with the session's form token, the one the viewer was drawn with,
 *  - from the session that opened the link past its password and team gates
 *    (pg_proto_comment_session_opens(): the mark prototype.php writes after
 *    them, the token's hash; the password's cookie is set for prototype.php
 *    alone and never reaches this file) and, for a team link, still signed
 *    in as role 0-2,
 *  - for a link that opens now and has "Allow comments" on,
 *  - about a draft page of the link's design that the link shows,
 *  - within the burst per address (a burst counts a firewall offence) and the
 *    open comments a link may hold.
 * The answer is JSON: the comment written (201), or error and message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

/**
 * Answers a refusal and ends the request.
 *
 * @param string $code
 * @return void
 */
function pg_proto_comment_refuse($code)
{
    if ($code === 'method') {
        header('Allow: POST');
    }

    http_response_code(pg_proto_comment_error_status($code));
    echo json_encode(array('error' => (string) $code, 'message' => pg_proto_comment_error_message($code)));
    exit;
}

$pg_pc_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

if ($pg_pc_method !== 'POST') {
    pg_proto_comment_refuse('method');
}

if (pg_post_body_discarded()) {
    pg_proto_comment_refuse('size');
}

$pg_pc_now = time();
$pg_pc_link_id = pg_proto_comment_id(isset($_POST['l']) ? $_POST['l'] : null);
$pg_pc_page_id = pg_proto_comment_id(isset($_POST['page_id']) ? $_POST['page_id'] : null);
$pg_pc_link = (($pg_pc_link_id > 0) && pg_proto_links_ready()) ? pg_proto_link_get($pg_pc_link_id) : null;
$pg_pc_state = pg_proto_link_state($pg_pc_link, $pg_pc_now);
$pg_pc_facts = array(
    'method' => $pg_pc_method,
    'same_origin' => pg_consent_answer_same_origin($_SERVER),
    'token_ok' => pg_proto_comment_token_ok(isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '', isset($_POST['token']) ? $_POST['token'] : ''),
    'state' => $pg_pc_state,
);

if (($pg_pc_state === 'ok') && $pg_pc_facts['same_origin'] && $pg_pc_facts['token_ok']) {
    $pg_pc_pages = (pg_proto_link_design_name($pg_pc_link['style_id']) !== null) ? pg_proto_design_pages($pg_pc_link['style_id']) : array();

    if (!$pg_pc_pages) {
        $pg_pc_facts['state'] = 'gone';
    }

    $pg_pc_facts['session'] = pg_proto_comment_session_opens(isset($_SESSION) ? $_SESSION : array(), $pg_pc_link);
    $pg_pc_facts['team_ok'] = ((string) $pg_pc_link['audience'] !== 'team')
        || (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE') && pg_proto_viewer_role_allowed(USER_ROLE));
    $pg_pc_facts['allowed'] = pg_proto_link_comments_allowed($pg_pc_link);

    $pg_pc_shown = false;

    foreach ($pg_pc_pages as $pg_pc_page) {
        if ((int) $pg_pc_page['id'] === $pg_pc_page_id) {
            $pg_pc_shown = pg_proto_link_page_shown((string) $pg_pc_link['audience'], pg_proto_link_page_access($pg_pc_page_id));
            break;
        }
    }

    $pg_pc_facts['page_shown'] = $pg_pc_shown;
}

$pg_pc_refusal = pg_proto_comment_door($pg_pc_facts);

if ($pg_pc_refusal !== '') {
    pg_proto_comment_refuse($pg_pc_refusal);
}

$pg_pc_input = array(
    'page_id' => $pg_pc_page_id,
    'node' => isset($_POST['node']) ? $_POST['node'] : null,
    'x' => isset($_POST['x']) ? $_POST['x'] : null,
    'y' => isset($_POST['y']) ? $_POST['y'] : null,
    'name' => isset($_POST['name']) ? $_POST['name'] : null,
    'text' => isset($_POST['text']) ? $_POST['text'] : null,
    'parent' => isset($_POST['parent']) ? $_POST['parent'] : null,
);
$pg_pc_parent_id = pg_proto_comment_id($pg_pc_input['parent']);
$pg_pc_checked = pg_proto_comment_check($pg_pc_input, $pg_pc_link, ($pg_pc_parent_id > 0) ? pg_proto_comment_get($pg_pc_parent_id) : null);

if (!$pg_pc_checked['ok']) {
    pg_proto_comment_refuse($pg_pc_checked['error']);
}

$pg_pc_limit = pg_proto_comment_limit_refusal(pg_proto_comment_burst(), pg_proto_comments_open_count($pg_pc_link['id']));

if ($pg_pc_limit !== '') {
    pg_proto_comment_refuse($pg_pc_limit);
}

$pg_pc_id = pg_proto_comment_save($pg_pc_checked['row'], $pg_pc_now);
$pg_pc_row = ($pg_pc_id > 0) ? pg_proto_comment_get($pg_pc_id) : null;

if (!$pg_pc_row) {
    http_response_code(500);
    echo json_encode(array('error' => 'save', 'message' => lang('Sorry, we could not accept your request.')));
    exit;
}

http_response_code(201);
echo json_encode(array('comment' => pg_proto_comment_public($pg_pc_row)));
