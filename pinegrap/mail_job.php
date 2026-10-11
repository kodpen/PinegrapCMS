<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Sends whatever queued e-mail is due (includes/fn/mail_queue.php). The
// general job already does this on every run; this entry point is for an
// operator who wants a cron entry of its own for mail, so a slow SMTP server
// delays only the mail and the general job keeps its own pace.

require('init.php');

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in
// manager, the same gate the scheduled jobs settings sit behind; without it
// anybody could start the job from a browser.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

$result = pg_mail_queue_run(50, 50);

// Scheduled-task health, the same as the other jobs record. Only once the
// queue exists: before the upgrade there is nothing this job does.
if (pg_mail_queue_ready()) {
    pg_cron_ran('mail_job');
}

if (php_sapi_name() === 'cli') {
    print 'sent: ' . $result['sent'] . ', not sent: ' . $result['failed'] . "\n";
}
