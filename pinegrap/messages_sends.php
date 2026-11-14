<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends: every message the site sends in one list, the
 * e-mail, SMS and WhatsApp queues' single messages and the campaigns
 * (includes/fn/messages_sends.php), with the tiles, the channel, source,
 * state and period filters, Run now, and each row's Retry and Delete under
 * its own queue's rules. The queue rows are for role <= 2 (they hold
 * password resets and receipts); a role 3 campaign maker sees their own
 * campaigns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php');

$user = validate_user();
validate_email_access($user);

include_once('liveform.class.php');
$liveform = new liveform('messages_sends');

$sends_filters = pg_messages_sends_filters($_GET);
$sends_self = PATH . SOFTWARE_DIRECTORY . '/' . pg_messages_sends_url($sends_filters);
$sends_manager = ((int) $user['role'] <= 2);

// A campaign row's fold asks for its recipients.
if (isset($_GET['recipients'])) {
    $sends_fold = pg_messages_sends_recipients_html($user, $_GET['recipients']);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(($sends_fold === null) ? array('status' => 'error') : array('status' => 'success', 'html' => $sends_fold));
    exit;
}

if ($_POST) {

    validate_token_field();

    $sends_action = (isset($_POST['sends_action']) && is_string($_POST['sends_action'])) ? $_POST['sends_action'] : '';

    if (($sends_action === 'run_now') && $sends_manager) {

        // Each text is a paid call to the provider, so a press sends no more
        // than the general job does in one run; the same for e-mail.
        $sends_mail = pg_mail_queue_ready() ? pg_mail_queue_run(10, 20) : array('sent' => 0, 'failed' => 0);
        $sends_sms = pg_sms_queue_ready() ? pg_sms_queue_run(10, 20) : array('sent' => 0, 'failed' => 0);

        $liveform->add_notice(lang(array(
            'string' => 'Queues worked: {var:1} sent, {var:2} not sent.',
            'vars'   => array($sends_mail['sent'] + $sends_sms['sent'], $sends_mail['failed'] + $sends_sms['failed']),
        )));

    } else {

        $sends_outcome = pg_messages_sends_action($user, $sends_action);

        // E-mail is worked by the general job or mail_job.php, text
        // messages by the general job only.
        if ($sends_outcome === 'queued') {
            $liveform->add_notice(lang('The message will be sent on the next run of the general job or mail_job.php.'));
        } elseif ($sends_outcome === 'queued_sms') {
            $liveform->add_notice(lang('The message will be sent on the next run of the general job.'));
        } elseif ($sends_outcome === 'sent') {
            $liveform->add_notice(lang('The message was sent.'));
        } elseif ($sends_outcome === 'unsent') {
            $liveform->add_warning(lang('The message could not be sent; its last error is in the list. While the queue is inactive it waits until Run now is pressed.'));
        } elseif ($sends_outcome === 'deleted') {
            $liveform->add_notice(lang('Deleted.'));
        }
    }

    go($sends_self);
}

$sends_now = time();
$sends_source = pg_messages_sends_list_source($user, array('filters' => $sends_filters), $sends_now);
$sends_page = ($sends_source !== null) ? pg_list_table_page($sends_source, array(), null) : false;

if ($sends_page === false) {
    output_error(lang('Query failed.'));
}

$sends_counts = pg_messages_sends_tiles($user, $sends_now);
$sends_figures = array();

foreach (
    array(
        array(lang('Queued'), 'queued', 'bi-hourglass-split', 'blue'),
        array(lang('Sending'), 'sending', 'bi-arrow-repeat', 'violet'),
        array(lang('Sent in the last 24 hours'), 'sent', 'bi-check2-all', 'teal'),
        array(lang('Failed'), 'failed', 'bi-x-octagon', 'rose'),
        array(lang('Outcome unknown'), 'uncertain', 'bi-question-circle', 'amber'),
    ) as $sends_tile
) {
    // A campaign maker's figures count their campaigns' waiting recipients
    // only; the queue figures are not theirs to see.
    if (!$sends_manager && ($sends_tile[1] !== 'queued')) {
        continue;
    }

    $sends_figures[] = array('label' => $sends_tile[0], 'icon' => $sends_tile[2], 'tone' => $sends_tile[3], 'value' => pg_format_number($sends_counts[$sends_tile[1]], 0));
}

// The toolbar as the drawing has it: search, the channel as a segmented
// control, then the source, state and period, Run now at the right.
$sends_keep = array('channel', 'source', 'state', 'period');
$sends_keep_values = array();

foreach ($sends_keep as $sends_key) {
    $sends_keep_values[$sends_key] = ($sends_key === 'period' && $sends_filters['period'] === '7') ? '' : $sends_filters[$sends_key];
}

$sends_filters_html = '<div class="pg-messages-filters">'
    . pg_messages_seg_filter('channel', array('' => lang('All')) + pg_messages_sends_channels(), $sends_filters['channel'], '', 'messages_sends.php', $sends_keep_values, lang('Channel'))
    . pg_filter_select('source', array('' => lang('All sources')) + pg_messages_sends_sources(), $sends_filters['source'], array('keep' => $sends_keep, 'label' => lang('Source')))
    . pg_filter_select('state', array('' => lang('All states')) + pg_mail_queue_list_status_labels(pg_messages_sends_states()), $sends_filters['state'], array('keep' => $sends_keep, 'label' => lang('Status')))
    . pg_filter_select('period', pg_messages_sends_periods(), $sends_filters['period'], array('keep' => $sends_keep, 'label' => lang('Period'), 'icon' => 'calendar3'))
    . '</div>';
$sends_run_form = '';

$sends_state_line = '';
$sends_transport_warning = '';

if ($sends_manager) {
    $sends_run_form = '
            <form action="' . h($sends_self) . '" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <button type="submit" name="sends_action" value="run_now" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-play" aria-hidden="true"></i>' . lang('Run now') . '</button>
            </form>';

    $sends_state_line = pg_mail_job_alive()
        ? '<span class="pg-pill pg-tone-teal">' . lang('Queue active') . '</span> ' . lang('E-mail is handed to the scheduled job (the general job or mail_job.php).')
        : '<span class="pg-pill pg-tone-gray">' . lang('Queue inactive') . '</span> ' . lang('Neither the general job nor mail_job.php has run in the last 15 minutes, so e-mail is sent immediately.');

    $sends_runs = pg_cron_last_runs();
    $sends_run = (is_array($sends_runs) && isset($sends_runs['mail_job'])) ? (int) $sends_runs['mail_job'] : 0;

    // The newest message the queue tried: while it failed because PHP mail()
    // cannot send, nothing goes out until an SMTP server is set up.
    if (pg_mail_queue_ready() && pg_mail_transport_warning_shown(db_item(
        "SELECT status, last_error
        FROM mail_outbox
        WHERE (status = 'sent') OR (attempts > 0)
        ORDER BY id DESC
        LIMIT 1"))) {
        $sends_transport_warning = pg_messages_note(pg_mail_transport_failure_sentence(), lang('Open SMTP Settings'), PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php', 'amber', 'alert');
    }

    $sends_state_line = $sends_transport_warning . '<p class="pg-text-meta pg-messages-state">' . $sends_state_line . ' · ' . lang('Last run of the mail queue') . ': '
        . (($sends_run > 0) ? prepare_form_data_for_output(date('Y-m-d H:i:s', $sends_run), 'date and time') : lang('Never')) . '</p>';
}

echo
pg_page_shell(array(
        'title'               => lang('Sends'),
        'extra classes'       => 'campaign messages-area pg-measure-list',
        'icon'                => 'campaign',
        'heading'             => lang('Communication'),
        'heading_description' => lang('Every message the site sends, with its state.'),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_messages_tab_strip_for('sends', $user) . '
            ' . pg_screen_toolbar(array(
                'grow'     => $sends_filters_html,
                'search'   => pg_list_table_search_box('messages_sends'),
                'overflow' => $sends_run_form,
            ), array('class' => 'pg-messages-toolbar')) . '
            ' . pg_messages_sends_figures_html($sends_figures) . '
            ' . $sends_state_line . '
            <form action="' . h($sends_self) . '" method="post" class="disable_shortcut" data-pg-sends-loading="' . h(lang('Loading…')) . '" data-pg-sends-failed="' . h(lang('The recipients could not be read.')) . '">
                ' . get_token_field() . '
                ' . pg_list_table_html($sends_source, $sends_page, lang('Sends')) . '
            </form>
        </div>
    </div>
</main>
<script>
    // A campaign row\'s recipients are read when its fold first opens. The
    // toggle event does not bubble, so it is caught on the way down; rows
    // drawn later are found the same way.
    document.addEventListener("toggle", function (event) {
        var fold = event.target;

        if (!fold || !fold.matches || !fold.matches("details[data-pg-sends-fold]") || !fold.open || fold.hasAttribute("data-pg-sends-read")) {
            return;
        }

        fold.setAttribute("data-pg-sends-read", "1");

        var body = fold.querySelector(".pg-sends-fold-body");
        var texts = fold.closest("[data-pg-sends-failed]");

        body.textContent = (texts && texts.getAttribute("data-pg-sends-loading")) || "";

        window.fetch("messages_sends.php?recipients=" + encodeURIComponent(fold.getAttribute("data-pg-sends-fold")), { credentials: "same-origin" })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (answer && (answer.status === "success")) {
                    body.innerHTML = answer.html;
                } else {
                    throw new Error("refused");
                }
            })
            .catch(function () {
                body.textContent = (texts && texts.getAttribute("data-pg-sends-failed")) || "";
                fold.removeAttribute("data-pg-sends-read");
            });
    }, true);
</script>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form();
