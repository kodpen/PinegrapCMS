<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - E-mails: the e-mail conversations of the channels the
 * signed-in person may read, newest mail first, in three views (Incoming,
 * Sent, Not sent), narrowed by status, holder, an overdue first answer, the
 * mailbox and a search, a page at a time. A row opens the conversation in
 * the reading pane beside the list, where it is answered and its status and
 * holder changed: its address is this screen with ?conversation=, whose
 * pane is drawn here, so a row never leads into a channel, script or not.
 * The menu counts the unread ones. New e-mail writes one
 * (includes/workspace/mail_compose.php). What the list reads and
 * draws is in includes/workspace/mail_list.php, the pane in mail_pane.php,
 * the unread marks in mail_reads.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_mail_reads_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

$now = time();
$filters = ws_mail_list_filters($_GET);
$base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
$open = (isset($_GET['conversation']) && is_scalar($_GET['conversation']) && ctype_digit((string) $_GET['conversation'])) ? (int) $_GET['conversation'] : 0;
$pane = '';

// The conversation of the address is opened (and read) before the list and
// the menu's count are drawn, so its row and the count are already read.
if ($open > 0) {
    $pane = ws_mail_pane_page_html(ws_mail_pane_open($viewer, $open), $base, ws_mail_list_page_url($filters));
}

$list = ws_mail_list($viewer, $filters, $now);

echo pg_page_shell(array(
    'title'               => lang('E-mails'),
    'extra classes'       => 'workspace workspace_mail',
    'icon'                => 'workspace',
    'heading'             => lang('E-mails'),
    'heading_description' => lang('The e-mail conversations of the channels you can read. Open one to read it, answer it or change its status and holder.'),
)) . '

<main id="content" class="container-fluid p-0">
<div class="ws-shell">
<div class="ws-shell-main">
    <div id="ws-root" class="ws-mail-screen' . (($pane !== '') ? ' ws-mail-reading' : '') . '">
        ' . ws_mail_list_filters_html($filters, ws_mail_list_boxes($viewer), ws_mail_send_ready() && (bool) ws_mail_send_boxes($viewer)) . '
        <div class="ws-mail-columns">
            <div class="ws-mail-list">' . ws_mail_list_html($list, $filters, $now, $open) . '</div>
            <section class="ws-mail-pane" data-ws-mail-pane aria-label="' . h(lang('E-mail conversation')) . '"' . (($pane !== '') ? '' : ' hidden') . '>' . $pane . '</section>
        </div>
    </div>
</div>
</div>
</main>' . ws_screen_assets($viewer, 'mail', array('mail' => array('strings' => ws_mail_screen_js_strings()))) . output_footer();
