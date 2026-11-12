<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The outgoing mail queue (mail_outbox, includes/fn/mail_queue.php): what is
 * waiting, what was sent and what was given up on, with a retry and a delete
 * per message and a button that works the queue now. The SMS tab shows the
 * text message queue (sms_outbox, includes/fn/sms.php) the same way.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('mail_queue');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

$self_url = PATH . SOFTWARE_DIRECTORY . '/mail_queue.php';

// The SMS tab is offered while SMS is set up or its queue still holds
// messages; asked for by its address, it opens whenever the queue exists.
$mail_queue_tab = (isset($_GET['tab']) && ($_GET['tab'] === 'sms') && pg_sms_queue_ready()) ? 'sms' : 'mail';

$mail_queue_tabs = ($mail_queue_tab === 'sms') || pg_sms_queue_shown();

if ($_POST) {

    validate_token_field();

    $sms_queue_action = (pg_sms_queue_ready() && isset($_POST['sms_action']) && is_string($_POST['sms_action'])) ? $_POST['sms_action'] : '';

    if ($sms_queue_action === 'run_now') {

        // Each message is a paid call to the provider, so a press sends no
        // more than the general job does in one run.
        $sms_queue_result = pg_sms_queue_run(10, 20);

        $liveform->add_notice(lang(array(
            'string' => 'SMS queue worked: {var:1} sent, {var:2} not sent.',
            'vars'   => array($sms_queue_result['sent'], $sms_queue_result['failed']),
        )));

    } elseif (preg_match('/^(retry|delete):([0-9]+)$/', $sms_queue_action, $sms_queue_match)) {

        if ($sms_queue_match[1] === 'retry') {

            if (pg_sms_queue_retry((int) $sms_queue_match[2])) {
                $liveform->add_notice(lang('The message will be sent on the next run of the general job.'));
            }

        } elseif (pg_sms_queue_delete((int) $sms_queue_match[2])) {

            $liveform->add_notice(lang('The message was deleted from the queue.'));
        }
    }

    if ($sms_queue_action !== '') {
        go($self_url . '?tab=sms');
    }

    $mail_queue_action = (pg_mail_queue_ready() && isset($_POST['mail_action']) && is_string($_POST['mail_action'])) ? $_POST['mail_action'] : '';

    if ($mail_queue_action === 'run_now') {

        $mail_queue_result = pg_mail_queue_run(10, 20);

        $liveform->add_notice(lang(array(
            'string' => 'Mail queue worked: {var:1} sent, {var:2} not sent.',
            'vars'   => array($mail_queue_result['sent'], $mail_queue_result['failed']),
        )));

    } elseif (preg_match('/^(retry|delete):([0-9]+)$/', $mail_queue_action, $mail_queue_match)) {

        $mail_queue_id = (int) $mail_queue_match[2];

        if ($mail_queue_match[1] === 'retry') {

            // A message being sent right now is left alone, and a sent one is
            // not sent again.
            db(
                "UPDATE mail_outbox
                SET status = 'queued', send_after = 0, attempts = 0
                WHERE
                    (id = '" . $mail_queue_id . "')
                    AND (status IN ('queued', 'failed'))");

            if (mysqli_affected_rows(db::$con) > 0) {

                if (pg_mail_job_alive()) {
                    $liveform->add_notice(lang('The message will be sent on the next run of the general job or mail_job.php.'));

                // No scheduled job works the queue, so nothing would pick the
                // message up: it is sent now, on its own.
                } elseif (pg_mail_queue_run(1, 20, $mail_queue_id)['sent'] > 0) {
                    $liveform->add_notice(lang('The message was sent.'));
                } else {
                    $liveform->add_warning(lang('The message could not be sent; its last error is in the list. While the queue is inactive it waits until Run now is pressed.'));
                }
            }

        } else {

            db("DELETE FROM mail_outbox WHERE id = '" . $mail_queue_id . "'");

            if (mysqli_affected_rows(db::$con) > 0) {
                $liveform->add_notice(lang('The message was deleted from the queue.'));
            }
        }
    }

    go($self_url);
}

$output_content = '';

if ($mail_queue_tab === 'sms') {

    $sms_queue_counts = pg_sms_queue_counts();

    // The list table driver prints the first rows; the next ones come from
    // its list_table_rows action (includes/panel/lists.php) as the list
    // scrolls, so a message far down the queue can still be found.
    $sms_queue_list_source = pg_sms_queue_list_source($user, array());
    $sms_queue_list_page = ($sms_queue_list_source !== null) ? pg_list_table_page($sms_queue_list_source, array(), null) : false;

    if ($sms_queue_list_page === false) {
        output_error(lang('Query failed.'));
    }

    $sms_queue_stats = '';

    foreach (
        array(
            array(lang('Queued'), 'queued'),
            array(lang('Sending'), 'sending'),
            array(lang('Sent in the last 24 hours'), 'sent'),
            array(lang('Failed'), 'failed'),
            array(lang('Outcome unknown'), 'uncertain'),
        ) as $sms_queue_stat
    ) {
        $sms_queue_colour = '';

        if ($sms_queue_counts[$sms_queue_stat[1]] > 0) {
            if ($sms_queue_stat[1] === 'failed') {
                $sms_queue_colour = ' fg-danger';
            } elseif ($sms_queue_stat[1] === 'uncertain') {
                $sms_queue_colour = ' fg-emphasis-warning';
            }
        }

        $sms_queue_stats .= '
                <div class="col-6 md:col-4 xl:col">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . h($sms_queue_stat[0]) . '</div>
                            <div class="fs-2xl fw-bold' . $sms_queue_colour . '">' . pg_format_number($sms_queue_counts[$sms_queue_stat[1]], 0) . '</div>
                        </div>
                    </div>
                </div>';
    }

    if (!pg_sms_ready()) {
        $sms_queue_state = '<span class="badge theme-secondary">' . lang('Not set up') . '</span> <a class="small" href="' . h(pg_settings_link('contact', 'pgset-sms')) . '">' . lang('SMS settings') . '</a>';
    } elseif (pg_sms_job_alive()) {
        $sms_queue_state = '<span class="badge theme-success">' . lang('Queue active') . '</span> <span class="small fg-secondary">' . lang('The general job sends text messages.') . '</span>';
    } else {
        $sms_queue_state = '<span class="badge theme-warning">' . lang('Queue inactive') . '</span> <span class="small fg-secondary">' . lang('The general job has not run in the last 15 minutes, so text messages wait in the queue.') . '</span>';
    }

    $sms_queue_runs = pg_cron_last_runs();

    $sms_queue_job_run = (is_array($sms_queue_runs) && isset($sms_queue_runs['job'])) ? (int) $sms_queue_runs['job'] : 0;

    $sms_queue_job_text = ($sms_queue_job_run > 0)
        ? prepare_form_data_for_output(date('Y-m-d H:i:s', $sms_queue_job_run), 'date and time')
        : lang('Never');

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <form action="mail_queue.php?tab=sms" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <button type="submit" name="sms_action" value="run_now" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Run now') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" data-pg-list-search="mail_queue_sms" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                </label>
                <div class="small">' . $sms_queue_state . '</div>
            </nav>

            <div class="row g-5 my-3">' . $sms_queue_stats . '
            </div>

            <p class="small fg-secondary mt-5 mb-0">' . lang('Last run of the general job') . ': ' . $sms_queue_job_text . '</p>

            <form action="mail_queue.php?tab=sms" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($sms_queue_list_source, $sms_queue_list_page, lang('SMS')) . '
            </form>';

} elseif (!pg_mail_queue_ready()) {

    $output_content = '<div class="alert theme-info">' . lang('The mail queue table is not available yet; run the upgrade.') . '</div>';

} else {

    $mail_queue_counts = db_item(
        "SELECT
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
            SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) AS sending,
            SUM(CASE WHEN (status = 'sent') AND (sent_at >= " . (time() - 86400) . ") THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
        FROM mail_outbox");

    $mail_queue_list_source = pg_mail_queue_list_source($user, array());
    $mail_queue_list_page = ($mail_queue_list_source !== null) ? pg_list_table_page($mail_queue_list_source, array(), null) : false;

    if ($mail_queue_list_page === false) {
        output_error(lang('Query failed.'));
    }

    $mail_queue_runs = pg_cron_last_runs();

    // Both the general job and mail_job.php record 'mail_job' after working
    // the queue, so this row is the queue's last run whichever one is
    // scheduled.
    $mail_queue_job_run = (is_array($mail_queue_runs) && isset($mail_queue_runs['mail_job'])) ? (int) $mail_queue_runs['mail_job'] : 0;

    $mail_queue_stats = '';

    foreach (
        array(
            array(lang('Queued'), 'queued'),
            array(lang('Sending'), 'sending'),
            array(lang('Sent in the last 24 hours'), 'sent'),
            array(lang('Failed'), 'failed'),
        ) as $mail_queue_stat
    ) {
        $mail_queue_stats .= '
                <div class="col-6 md:col-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small fg-secondary">' . h($mail_queue_stat[0]) . '</div>
                            <div class="fs-2xl fw-bold' . ((($mail_queue_stat[1] === 'failed') && ((int) $mail_queue_counts['failed'] > 0)) ? ' fg-danger' : '') . '">' . pg_format_number((int) $mail_queue_counts[$mail_queue_stat[1]], 0) . '</div>
                        </div>
                    </div>
                </div>';
    }

    if (pg_mail_job_alive()) {
        $mail_queue_state = '<span class="badge theme-success">' . lang('Queue active') . '</span> <span class="small fg-secondary">' . lang('E-mail is handed to the scheduled job (the general job or mail_job.php).') . '</span>';
    } else {
        $mail_queue_state = '<span class="badge theme-secondary">' . lang('Queue inactive') . '</span> <span class="small fg-secondary">' . lang('Neither the general job nor mail_job.php has run in the last 15 minutes, so e-mail is sent immediately.') . '</span>';
    }

    $mail_queue_job_text = ($mail_queue_job_run > 0)
        ? prepare_form_data_for_output(date('Y-m-d H:i:s', $mail_queue_job_run), 'date and time')
        : lang('Never');

    // The newest message the queue tried: while it failed because PHP mail()
    // cannot send, nothing goes out until an SMTP server is set up.
    $mail_queue_transport_warning = '';

    if (pg_mail_transport_warning_shown(db_item(
        "SELECT status, last_error
        FROM mail_outbox
        WHERE (status = 'sent') OR (attempts > 0)
        ORDER BY id DESC
        LIMIT 1"))) {
        $mail_queue_transport_warning = '
            <div class="alert theme-warning d-flex flex-wrap align-items-center gap-3 mt-5" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span>' . h(pg_mail_transport_failure_sentence()) . '</span>
                <a class="btn btn-sm btn-solid theme-warning ms-auto" href="' . h(PATH . SOFTWARE_DIRECTORY . '/smtp_settings.php') . '">' . h(lang('Open SMTP Settings')) . '</a>
            </div>';
    }

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <form action="mail_queue.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <button type="submit" name="mail_action" value="run_now" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Run now') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" data-pg-list-search="mail_queue" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                </label>
                <div class="small">' . $mail_queue_state . '</div>
            </nav>
' . $mail_queue_transport_warning . '
            <div class="row g-5 my-3">' . $mail_queue_stats . '
            </div>

            <p class="small fg-secondary mt-5 mb-0">' . lang('Last run of the mail queue') . ': ' . $mail_queue_job_text . '</p>

            <form action="mail_queue.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($mail_queue_list_source, $mail_queue_list_page, lang('Mail queue')) . '
            </form>';
}

$mail_queue_tab_nav = '';

if ($mail_queue_tabs) {

    foreach (
        array(
            'mail' => array($self_url, 'bi-envelope', lang('E-mail')),
            'sms'  => array($self_url . '?tab=sms', 'bi-chat-text', lang('SMS')),
        ) as $mail_queue_tab_key => $mail_queue_tab_link
    ) {
        $mail_queue_tab_current = ($mail_queue_tab_key === $mail_queue_tab);

        $mail_queue_tab_nav .= '
                <li class="nav-item">
                    <a class="nav-link' . ($mail_queue_tab_current ? ' active' : '') . '"' . ($mail_queue_tab_current ? ' aria-current="page"' : '') . ' href="' . h($mail_queue_tab_link[0]) . '"><i class="bi ' . $mail_queue_tab_link[1] . ' me-3" aria-hidden="true"></i>' . h($mail_queue_tab_link[2]) . '</a>
                </li>';
    }

    $mail_queue_tab_nav = '
            <ul class="nav nav-tabs mb-5">' . $mail_queue_tab_nav . '
            </ul>';
}

echo
pg_page_shell(array(
        'title'               => lang('Mail queue'),
        'extra classes'       => 'setting pg-measure-list',
        'icon'                => 'setting',
        'heading'             => lang('Mail queue'),
        'heading_description' => ($mail_queue_tab === 'sms')
            ? lang('Text messages waiting for the general job, sent in the last week and not sent in the last month.')
            : lang('E-mail waiting for the general job or mail_job.php, sent in the last week and given up on in the last month.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $mail_queue_tab_nav . '
            ' . $output_content . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
output_footer();

$liveform->remove_form();
