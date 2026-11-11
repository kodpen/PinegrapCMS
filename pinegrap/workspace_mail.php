<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - E-mails: the e-mail conversations of the channels the
 * signed-in person may read, newest mail first, in three views (Incoming,
 * Sent, Not sent), narrowed by status, holder, an overdue first answer, the
 * mailbox and a search, a page at a time. A row opens the conversation in
 * the reading pane beside the list, where it is answered and its status and
 * holder changed; the menu counts the unread ones. What the list reads and
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
$list = ws_mail_list($viewer, $filters, $now);
$base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

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
    <div id="ws-root" class="ws-mail-screen">
        ' . ws_mail_list_filters_html($filters, ws_mail_list_boxes($viewer)) . '
        <div class="ws-mail-columns">
            <div class="ws-mail-list">' . ws_mail_list_html($list, $filters, $base, $now) . '</div>
            <section class="ws-mail-pane" data-ws-mail-pane aria-label="' . h(lang('E-mail conversation')) . '" hidden></section>
        </div>
    </div>
</div>
</div>
</main>' . ws_screen_assets($viewer, 'mail', array('mail' => array('strings' => ws_mail_screen_js_strings()))) . output_footer();
