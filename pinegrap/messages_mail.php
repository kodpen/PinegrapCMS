<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Incoming e-mail: the e-mail conversations of the channels
 * and mailbox rooms the signed-in person may read, newest mail first, in
 * three views (Incoming, Sent, Not sent), narrowed by status, holder, an
 * overdue first answer, the mailbox and a search, a page at a time. A row
 * opens the conversation in the reading pane beside the list, where it is
 * answered and its status and holder changed: its address is this screen
 * with ?conversation=, whose pane is drawn here, so a row never leads into
 * a channel, script or not. New e-mail writes one
 * (includes/workspace/mail_compose.php). What the list reads and draws is in
 * includes/workspace/mail_list.php, the pane in mail_pane.php, the unread
 * marks in mail_reads.php. The tab shows to whoever reads a mailbox
 * (ws_mail_list_visible()), campaign rights or not; workspace_mail.php is
 * the door from the old address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!ws_mail_reads_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

if (!ws_mail_list_visible($viewer)) {
    log_activity(lang('access denied to the workspace'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$now = time();
$filters = ws_mail_list_filters($_GET);
$base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
$open = (isset($_GET['conversation']) && is_scalar($_GET['conversation']) && ctype_digit((string) $_GET['conversation'])) ? (int) $_GET['conversation'] : 0;
$pane = '';

// The conversation of the address is opened (and read) before the list is
// drawn, so its row is already read.
if ($open > 0) {
    $pane = ws_mail_pane_page_html(ws_mail_pane_open($viewer, $open), $base, ws_mail_list_page_url($filters));
}

$list = ws_mail_list($viewer, $filters, $now);
$boxes = ws_mail_list_boxes($viewer);
$transports = ws_mail_list_transports($viewer);

echo pg_page_shell(array(
    'title'               => lang('Incoming e-mail'),
    'extra classes'       => 'workspace workspace_mail messages-area',
    'icon'                => 'campaign',
    'heading'             => lang('Communication'),
    // A customer's WhatsApp messages and SMS are listed here too, once any came.
    'heading_description' => (count($transports) > 1)
        ? lang('The e-mail, WhatsApp and SMS conversations of the channels you can read. Open one to read it, answer it or change its status and holder.')
        : lang('The e-mail conversations of the channels you can read. Open one to read it, answer it or change its status and holder.'),
)) . '

<main id="content" class="container-fluid"><div class="row"><div class="col-12">
' . pg_messages_tab_strip_for('mail', $user) . '
<div class="ws-shell">
<div class="ws-shell-main">
    <div id="ws-root" class="ws-mail-screen' . (($pane !== '') ? ' ws-mail-reading' : '') . '">
        ' . ws_mail_list_filters_html($filters, $boxes, ws_mail_send_ready() && (bool) ws_mail_send_boxes($viewer), ws_mail_list_support($boxes, ws_mail_plain_box_ids()), $transports) . '
        <div class="ws-mail-columns">
            <div class="ws-mail-list">' . ws_mail_list_html($list, $filters, $now, $open) . '</div>
            <section class="ws-mail-pane" data-ws-mail-pane aria-label="' . h(lang('E-mail conversation')) . '"' . (($pane !== '') ? '' : ' hidden') . '>' . $pane . '</section>
        </div>
    </div>
</div>
</div>
</div></div></main>' . ws_screen_assets($viewer, 'mail', array('mail' => array('strings' => ws_mail_screen_js_strings()))) . output_footer();
