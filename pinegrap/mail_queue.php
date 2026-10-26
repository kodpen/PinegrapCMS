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

    $mail_queue_action = pg_mail_queue_ready() ? (string) ($_POST['mail_action'] ?? '') : '';

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
                $liveform->add_notice(lang('The message will be sent on the next run of the general job.'));
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

    $sms_queue_status_labels = array(
        'queued'    => array(lang('Queued'), 'text-bg-secondary'),
        'sending'   => array(lang('Sending'), 'text-bg-info'),
        'sent'      => array(lang('Sent'), 'text-bg-success'),
        'failed'    => array(lang('Failed'), 'text-bg-danger'),
        'uncertain' => array(lang('Outcome unknown'), 'text-bg-warning'),
    );

    $sms_queue_kind_labels = array(
        'test'      => lang('Test'),
        'order'     => lang('Order'),
        'shipping'  => lang('Shipping update'),
        'otp'       => lang('Sign-in code'),
        'reminder'  => lang('Reminder'),
        'marketing' => lang('Marketing'),
    );

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
                $sms_queue_colour = ' text-danger';
            } elseif ($sms_queue_stat[1] === 'uncertain') {
                $sms_queue_colour = ' text-warning-emphasis';
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

    $sms_queue_rows = '';

    foreach (db_items(
        "SELECT id, created_at, send_after, expires_at, status, attempts, last_error, sent_at, kind, recipient, body, reference, provider, provider_id
        FROM sms_outbox
        ORDER BY id DESC
        LIMIT 100") as $sms_queue_row) {

        $sms_queue_status = isset($sms_queue_status_labels[$sms_queue_row['status']])
            ? $sms_queue_status_labels[$sms_queue_row['status']]
            : array($sms_queue_row['status'], 'text-bg-light');

        $sms_queue_expired = ((int) $sms_queue_row['expires_at'] > 0) && ((int) $sms_queue_row['expires_at'] <= time());

        // When it went, or when it goes next.
        if ($sms_queue_row['status'] === 'sent') {
            $sms_queue_when = prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $sms_queue_row['sent_at']), 'date and time');
        } elseif ($sms_queue_row['status'] === 'queued') {
            $sms_queue_when = ((int) $sms_queue_row['send_after'] > time())
                ? prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $sms_queue_row['send_after']), 'date and time')
                : lang('On the next run');
        } else {
            $sms_queue_when = '';
        }

        // Who took it, under which id: what a question to the provider about
        // this message needs.
        $sms_queue_provider = '';

        if ((string) $sms_queue_row['provider'] !== '') {
            $sms_queue_definition = pg_sms_provider_definition($sms_queue_row['provider']);
            $sms_queue_provider = ($sms_queue_definition !== null) ? $sms_queue_definition['label'] : (string) $sms_queue_row['provider'];

            if ((string) $sms_queue_row['provider_id'] !== '') {
                $sms_queue_provider .= ' ' . $sms_queue_row['provider_id'];
            }
        }

        $sms_queue_error = (string) $sms_queue_row['last_error'];

        $sms_queue_reference = (string) $sms_queue_row['reference'];

        $sms_queue_buttons = '';

        if (in_array($sms_queue_row['status'], array('queued', 'failed', 'uncertain'), true) && !$sms_queue_expired) {

            // A message whose outcome is unknown may already be on the
            // recipient's phone; sending it again is the operator's call,
            // made after a look at the provider's report.
            $sms_queue_confirm = ($sms_queue_row['status'] === 'uncertain')
                ? ' data-confirm-content="' . h(lang('The provider may already have delivered this message, and sent again it would arrive twice. Check the provider\'s report first.')) . '"'
                : '';

            $sms_queue_buttons .= '<button type="submit" name="sms_action" value="retry:' . (int) $sms_queue_row['id'] . '" class="btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"' . $sms_queue_confirm . '><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
        }

        $sms_queue_buttons .= '<button type="submit" name="sms_action" value="delete:' . (int) $sms_queue_row['id'] . '" class="btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(lang('The message will be deleted from the queue and not sent.')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

        $sms_queue_rows .= '
                        <tr>
                            <td class="align-middle text-nowrap">' . $sms_queue_buttons . '</td>
                            <td class="align-middle">' . (int) $sms_queue_row['id'] . '</td>
                            <td class="align-middle"><span class="badge ' . $sms_queue_status[1] . '">' . h($sms_queue_status[0]) . '</span></td>
                            <td class="align-middle">' . h(isset($sms_queue_kind_labels[$sms_queue_row['kind']]) ? $sms_queue_kind_labels[$sms_queue_row['kind']] : $sms_queue_row['kind']) . '</td>
                            <td class="align-middle text-nowrap">+' . h($sms_queue_row['recipient']) . '</td>
                            <td class="align-middle" title="' . h($sms_queue_row['body']) . '">' . h(truncate($sms_queue_row['body'], 80)) . (($sms_queue_reference !== '') ? '<div class="small fg-secondary">' . h($sms_queue_reference) . '</div>' : '') . '</td>
                            <td class="align-middle">' . (int) $sms_queue_row['attempts'] . '</td>
                            <td class="align-middle small fg-danger" title="' . h($sms_queue_error) . '">' . h(truncate($sms_queue_error, 80)) . '</td>
                            <td class="align-middle small text-nowrap">' . h($sms_queue_provider) . '</td>
                            <td class="align-middle text-nowrap">' . prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $sms_queue_row['created_at']), 'date and time') . '</td>
                            <td class="align-middle text-nowrap">' . $sms_queue_when . '</td>
                        </tr>';
    }

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <form action="mail_queue.php?tab=sms" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <button type="submit" name="sms_action" value="run_now" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Run now') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <div class="small">' . $sms_queue_state . '</div>
            </nav>

            <div class="row g-5 my-3">' . $sms_queue_stats . '
            </div>

            <p class="small fg-secondary mt-5 mb-0">' . lang('Last run of the general job') . ': ' . $sms_queue_job_text . '</p>

            <form action="mail_queue.php?tab=sms" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none;">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>#</th>
                                    <th>' . lang('Status') . '</th>
                                    <th>' . lang('Kind') . '</th>
                                    <th>' . lang('Recipient') . '</th>
                                    <th>' . lang('Message') . '</th>
                                    <th>' . lang('Attempts') . '</th>
                                    <th>' . lang('Last error') . '</th>
                                    <th>' . lang('Provider') . '</th>
                                    <th>' . lang('Created') . '</th>
                                    <th>' . lang('Next attempt or sent') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $sms_queue_rows . '</tbody>
                        </table>
                    </div>
                </div>
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

    $mail_queue_runs = pg_cron_last_runs();

    $mail_queue_job_run = (is_array($mail_queue_runs) && isset($mail_queue_runs['job'])) ? (int) $mail_queue_runs['job'] : 0;

    $mail_queue_status_labels = array(
        'queued'  => array(lang('Queued'), 'text-bg-secondary'),
        'sending' => array(lang('Sending'), 'text-bg-info'),
        'sent'    => array(lang('Sent'), 'text-bg-success'),
        'failed'  => array(lang('Failed'), 'text-bg-danger'),
    );

    $mail_queue_type_labels = array(
        'system'   => lang('System'),
        'campaign' => lang('Campaign'),
    );

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
                            <div class="fs-2xl fw-bold' . ((($mail_queue_stat[1] === 'failed') && ((int) $mail_queue_counts['failed'] > 0)) ? ' text-danger' : '') . '">' . pg_format_number((int) $mail_queue_counts[$mail_queue_stat[1]], 0) . '</div>
                        </div>
                    </div>
                </div>';
    }

    if (pg_mail_job_alive()) {
        $mail_queue_state = '<span class="badge theme-success">' . lang('Queue active') . '</span> <span class="small fg-secondary">' . lang('E-mail is handed to the general job.') . '</span>';
    } else {
        $mail_queue_state = '<span class="badge theme-secondary">' . lang('Queue inactive') . '</span> <span class="small fg-secondary">' . lang('The general job has not run in the last 15 minutes, so e-mail is sent immediately.') . '</span>';
    }

    $mail_queue_job_text = ($mail_queue_job_run > 0)
        ? prepare_form_data_for_output(date('Y-m-d H:i:s', $mail_queue_job_run), 'date and time')
        : lang('Never');

    $mail_queue_rows = '';

    foreach (db_items(
        "SELECT id, created_at, send_after, status, attempts, last_error, claimed_at, sent_at, mail_type, recipient, subject
        FROM mail_outbox
        ORDER BY id DESC
        LIMIT 100") as $mail_queue_row) {

        $mail_queue_status = isset($mail_queue_status_labels[$mail_queue_row['status']])
            ? $mail_queue_status_labels[$mail_queue_row['status']]
            : array($mail_queue_row['status'], 'text-bg-light');

        // When it went, or when it goes next.
        if ($mail_queue_row['status'] === 'sent') {
            $mail_queue_when = prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail_queue_row['sent_at']), 'date and time');
        } elseif ($mail_queue_row['status'] === 'queued') {
            $mail_queue_when = ((int) $mail_queue_row['send_after'] > time())
                ? prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail_queue_row['send_after']), 'date and time')
                : lang('On the next run');
        } else {
            $mail_queue_when = '';
        }

        $mail_queue_error = (string) $mail_queue_row['last_error'];

        // Given up at once: what is wrong is in the message itself, so a
        // retry fails the same way until the cause is fixed where it was
        // written.
        $mail_queue_permanent = pg_mail_failed_permanently($mail_queue_row['status'], $mail_queue_row['attempts'])
            ? ' <span class="badge text-bg-dark" title="' . h(lang('Not tried again: sending it again would fail the same way.')) . '">' . h(lang('Permanent')) . '</span>'
            : '';

        $mail_queue_buttons = '';

        if (in_array($mail_queue_row['status'], array('queued', 'failed'), true)) {
            $mail_queue_buttons .= '<button type="submit" name="mail_action" value="retry:' . (int) $mail_queue_row['id'] . '" class="btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
        }

        $mail_queue_buttons .= '<button type="submit" name="mail_action" value="delete:' . (int) $mail_queue_row['id'] . '" class="btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(lang('The message will be deleted from the queue and not sent.')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

        $mail_queue_rows .= '
                        <tr>
                            <td class="align-middle text-nowrap">' . $mail_queue_buttons . '</td>
                            <td class="align-middle">' . (int) $mail_queue_row['id'] . '</td>
                            <td class="align-middle text-nowrap"><span class="badge ' . $mail_queue_status[1] . '">' . h($mail_queue_status[0]) . '</span>' . $mail_queue_permanent . '</td>
                            <td class="align-middle">' . h(isset($mail_queue_type_labels[$mail_queue_row['mail_type']]) ? $mail_queue_type_labels[$mail_queue_row['mail_type']] : $mail_queue_row['mail_type']) . '</td>
                            <td class="align-middle">' . h($mail_queue_row['recipient']) . '</td>
                            <td class="align-middle">' . h($mail_queue_row['subject']) . '</td>
                            <td class="align-middle">' . (int) $mail_queue_row['attempts'] . '</td>
                            <td class="align-middle small fg-danger" title="' . h($mail_queue_error) . '">' . h(truncate($mail_queue_error, 80)) . '</td>
                            <td class="align-middle text-nowrap">' . prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail_queue_row['created_at']), 'date and time') . '</td>
                            <td class="align-middle text-nowrap">' . $mail_queue_when . '</td>
                        </tr>';
    }

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <form action="mail_queue.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <button type="submit" name="mail_action" value="run_now" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Run now') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <div class="small">' . $mail_queue_state . '</div>
            </nav>

            <div class="row g-5 my-3">' . $mail_queue_stats . '
            </div>

            <p class="small fg-secondary mt-5 mb-0">' . lang('Last run of the general job') . ': ' . $mail_queue_job_text . '</p>

            <form action="mail_queue.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none;">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>#</th>
                                    <th>' . lang('Status') . '</th>
                                    <th>' . lang('Type') . '</th>
                                    <th>' . lang('Recipient') . '</th>
                                    <th>' . lang('Subject') . '</th>
                                    <th>' . lang('Attempts') . '</th>
                                    <th>' . lang('Last error') . '</th>
                                    <th>' . lang('Created') . '</th>
                                    <th>' . lang('Next attempt or sent') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $mail_queue_rows . '</tbody>
                        </table>
                    </div>
                </div>
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
        'extra classes'       => 'setting',
        'icon'                => 'setting',
        'heading'             => lang('Mail queue'),
        'heading_description' => ($mail_queue_tab === 'sms')
            ? lang('Text messages waiting for the general job, sent in the last week and not sent in the last month.')
            : lang('E-mail waiting for the general job, sent in the last week and given up on in the last month.'),
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
' .
output_footer();

$liveform->remove_form();
