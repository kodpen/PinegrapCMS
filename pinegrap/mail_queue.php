<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The outgoing mail queue (mail_outbox, includes/fn/mail_queue.php): what is
 * waiting, what was sent and what was given up on, with a retry and a delete
 * per message and a button that works the queue now.
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

if ($_POST) {

    validate_token_field();

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

if (!pg_mail_queue_ready()) {

    $output_content = '<div class="alert alert-info">' . lang('The mail queue table is not available yet; run the upgrade.') . '</div>';

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
                <div class="col-6 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-body-secondary">' . h($mail_queue_stat[0]) . '</div>
                            <div class="fs-3 fw-bold' . ((($mail_queue_stat[1] === 'failed') && ((int) $mail_queue_counts['failed'] > 0)) ? ' text-danger' : '') . '">' . pg_format_number((int) $mail_queue_counts[$mail_queue_stat[1]], 0) . '</div>
                        </div>
                    </div>
                </div>';
    }

    if (pg_mail_job_alive()) {
        $mail_queue_state = '<span class="badge text-bg-success">' . lang('Queue active') . '</span> <span class="small text-body-secondary">' . lang('E-mail is handed to the general job.') . '</span>';
    } else {
        $mail_queue_state = '<span class="badge text-bg-secondary">' . lang('Queue inactive') . '</span> <span class="small text-body-secondary">' . lang('The general job has not run in the last 15 minutes, so e-mail is sent immediately.') . '</span>';
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

        $mail_queue_buttons = '';

        if (in_array($mail_queue_row['status'], array('queued', 'failed'), true)) {
            $mail_queue_buttons .= '<button type="submit" name="mail_action" value="retry:' . (int) $mail_queue_row['id'] . '" class="btn btn-sm btn-outline-secondary me-1" title="' . h(lang('Retry')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
        }

        $mail_queue_buttons .= '<button type="submit" name="mail_action" value="delete:' . (int) $mail_queue_row['id'] . '" class="btn btn-sm btn-outline-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(lang('The message will be deleted from the queue and not sent.')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

        $mail_queue_rows .= '
                        <tr>
                            <td class="align-middle text-nowrap">' . $mail_queue_buttons . '</td>
                            <td class="align-middle">' . (int) $mail_queue_row['id'] . '</td>
                            <td class="align-middle"><span class="badge ' . $mail_queue_status[1] . '">' . h($mail_queue_status[0]) . '</span></td>
                            <td class="align-middle">' . h(isset($mail_queue_type_labels[$mail_queue_row['mail_type']]) ? $mail_queue_type_labels[$mail_queue_row['mail_type']] : $mail_queue_row['mail_type']) . '</td>
                            <td class="align-middle">' . h($mail_queue_row['recipient']) . '</td>
                            <td class="align-middle">' . h($mail_queue_row['subject']) . '</td>
                            <td class="align-middle">' . (int) $mail_queue_row['attempts'] . '</td>
                            <td class="align-middle small text-danger" title="' . h($mail_queue_error) . '">' . h(truncate($mail_queue_error, 80)) . '</td>
                            <td class="align-middle text-nowrap">' . prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail_queue_row['created_at']), 'date and time') . '</td>
                            <td class="align-middle text-nowrap">' . $mail_queue_when . '</td>
                        </tr>';
    }

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <form action="mail_queue.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <button type="submit" name="mail_action" value="run_now" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Run now') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <div class="small">' . $mail_queue_state . '</div>
            </nav>

            <div class="row g-3 my-2">' . $mail_queue_stats . '
            </div>

            <p class="small text-body-secondary mt-3 mb-0">' . lang('Last run of the general job') . ': ' . $mail_queue_job_text . '</p>

            <form action="mail_queue.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <div class="card my-4">
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

echo
pg_page_shell(array(
        'title'               => lang('Mail queue'),
        'extra classes'       => 'setting',
        'icon'                => 'setting',
        'heading'             => lang('Mail queue'),
        'heading_description' => lang('E-mail waiting for the general job, sent in the last week and given up on in the last month.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_content . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
