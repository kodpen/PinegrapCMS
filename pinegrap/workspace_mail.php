<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - E-mails: the e-mail conversations of the channels the
 * signed-in person may read, newest mail first, narrowed by status, holder
 * and an overdue first answer. A row opens the conversation's card in its
 * channel, where it is answered and changed. What the list reads and draws
 * is in includes/workspace/mail_list.php.
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

if (!ws_mail_ready()) {
    output_error(lang('The workspace is not installed yet: the database has to be updated first.'));
}

$now = time();
$filters = ws_mail_list_filters($_GET);
$rows = ws_mail_list($viewer, $filters, $now);
$base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

echo pg_page_shell(array(
    'title'               => lang('E-mails'),
    'icon'                => 'workspace',
    'heading'             => lang('E-mails'),
    'heading_description' => lang('The e-mail conversations of the channels you can read. Open one to answer it or to change its status and holder.'),
)) . '

<main id="content" class="container-fluid">
    <div class="row"><div class="col-12">
        ' . ws_mail_list_filters_html($filters) . '
        ' . ws_mail_list_html($rows, $base, $now) . '
        ' . ((count($rows) >= WS_MAIL_LIST_MAX)
            ? '<p class="small fg-secondary">' . h(lang(array('string' => 'The newest {var:1} are shown; narrow the list to see others.', 'vars' => WS_MAIL_LIST_MAX))) . '</p>'
            : '') . '
    </div></div>
</main>' . output_footer();
