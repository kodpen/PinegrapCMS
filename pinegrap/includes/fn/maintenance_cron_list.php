<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance, the Scheduled jobs row opened: one
 * row a job of pg_cron_jobs() with one line of what it does, its schedule and
 * its switch, and a drawer of numbered steps that gives the job a scheduled
 * task of its own. The command, line, row and step builders are pure but for
 * lang(); the _html() functions draw them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The command a scheduled task runs a job script with. Windows takes the
 * php.exe form; every other family (Linux, Darwin, BSD, Solaris) is
 * unix-like and takes the same command.
 *
 * @param string $family PHP_OS_FAMILY as the settings read it
 * @param string $dir    the software root (PG_FUNCTIONS_DIR)
 * @param string $script the job's file, from pg_cron_jobs()
 * @return string
 */
function pg_maintenance_cron_command($family, $dir, $script)
{
    if ($family === 'Windows') {
        return 'C:\PHP\php.exe -q ' . $dir . '\\' . $script;
    }

    return '/usr/local/bin/php -q ' . $dir . '/' . $script . ' >/dev/null 2>&1';
}

/**
 * One line of what a job does.
 *
 * @param string $name a key of pg_cron_jobs()
 * @return string '' for a job without one
 */
function pg_maintenance_cron_job_line($name)
{
    switch ($name) {
        case 'email_campaign_job':
            return lang('Sends the e-mail campaigns that are ready, and the ones scheduled for later.');
        case 'recurring_payment_job':
            return lang('Acts on a recurring payment profile that was suspended, cancelled or has expired.');
        case 'membership_job':
            return lang('Warns members before their membership expires and takes expired ones out of the group.');
        case 'update_exchange_rates':
            return lang('Updates the exchange rates of the store\'s currencies.');
        case 'update_search_index':
            return lang('Adds new and changed pages to the site search.');
        case 'seo_score_job':
            return lang('Works out the SEO score of every page, product and product group again.');
        case 'seo_analyze_job':
            return lang('Renders each page and checks its headings, image texts and links.');
        case 'seo_a11y_job':
            return lang('Builds the accessibility report of the site\'s pages.');
        case 'security_scan_job':
            return lang('Walks the site\'s files for Security Scan.');
        case 'auto_backup':
            return lang('Writes a backup of the files and the database.');
        case 'waf_ranges_job':
            return lang('Fetches the address lists that search and AI bots publish.');
        case 'api_webhook_job':
            return lang('Sends your integrations the events they asked to hear about.');
        case 'api_sync_job':
            return lang('Keeps the connected marketplaces in step with the store.');
        case 'push_job':
            return lang('Sends notifications to the phones and computers of the operators.');
        case 'erp_overdue_job':
            return lang('Sends reminders for receivables past their due date.');
        case 'erp_accountant_job':
            return lang('Builds last month\'s pack for the accountant.');
        case 'erp_invoice_recurring_job':
            return lang('Writes the repeating invoices that have come due.');
        case 'erp_stock_alert_job':
            return lang('Announces products at their minimum stock and quotes about to run out.');
        case 'erp_stock_plan_job':
            return lang('Records each day\'s sales and stock for stock planning.');
        case 'erp_expense_recurring_job':
            return lang('Writes the repeating expenses that have come due.');
        case 'erp_order_profit_job':
            return lang('Works out the costs and the net profit of each finished order.');
        case 'workspace_recurring_job':
            return lang('Hands out the Workspace\'s repeating tasks and retries waiting assistant requests.');
        case 'translation_job':
            return lang('Finishes the translation jobs started from the panel.');
        case 'seo_search_job':
            return lang('Brings in the figures of Google Search Console.');
        case 'google_analytics_job':
            return lang('Brings in the figures of Google Analytics for the dashboard.');
        case 'return_reminder_job':
            return lang('Reminds the staff of return refunds falling due.');
        case 'product_feed_job':
            return lang('Rebuilds the product feeds that are switched on.');
        case 'iys_job':
            return lang('Sends e-mail consents to İYS and brings back the refusals given there.');
        case 'shipment_tracking_job':
            return lang('Asks the carriers where open parcels stand.');
    }

    return '';
}

/**
 * The id of a job's steps drawer.
 *
 * @param string $name a key of pg_cron_jobs()
 * @return string
 */
function pg_maintenance_cron_guide_id($name)
{
    return 'pgset-cron-' . str_replace('_', '-', (string) $name) . '-guide';
}

/**
 * A job's schedule as its row says it.
 *
 * @param array $job one pg_cron_jobs() entry
 * @return string
 */
function pg_maintenance_cron_schedule($job)
{
    if (!empty($job['inline'])) {
        return lang('With every run of the general job');
    }

    return lang(array('string' => 'Recommended: {var:1}', 'vars' => pg_cron_interval_label((int) $job['interval'])));
}

/**
 * The rows of the list: every job that can run with the general job, in the
 * catalogue's order.
 *
 * @param array    $jobs      pg_cron_jobs()
 * @param string[] $selection the job names saved in job_dispatch
 * @param array    $inactive  names config.php has switched off
 * @return array name => name, line, schedule, on, active
 */
function pg_maintenance_cron_rows($jobs, $selection, $inactive = array())
{
    $rows = array();

    foreach ($jobs as $name => $job) {
        if (empty($job['dispatch'])) {
            continue;
        }

        $rows[$name] = array(
            'name'     => (string) $job['label'],
            'line'     => pg_maintenance_cron_job_line($name),
            'schedule' => pg_maintenance_cron_schedule($job),
            'on'       => in_array($name, (array) $selection, true),
            'active'   => !in_array($name, (array) $inactive, true),
        );
    }

    return $rows;
}

/**
 * The notes of a job as short steps, after its schedule.
 *
 * @param string $name a key of pg_cron_jobs()
 * @return array[] title, text
 */
function pg_maintenance_cron_notes($name)
{
    switch ($name) {
        case 'email_campaign_job':
            return array(array(
                'title' => lang('Cancel old campaigns first'),
                'text'  => lang('Once it runs, every campaign marked Ready to Send is sent: mark old, unfinished ones Cancelled before.'),
            ));
        case 'update_search_index':
            return array(array(
                'title' => lang('Not more than once an hour'),
                'text'  => lang('It reads the whole site and can slow it down while it runs; once a day is better.'),
            ));
        case 'seo_analyze_job':
            return array(array(
                'title' => lang('After the SEO score job'),
                'text'  => lang('A catalogue page is scored from the products it lists, so run it after the SEO score job.'),
            ));
        case 'recurring_payment_job':
            return array(array(
                'title' => lang('Only for stopped payment profiles'),
                'text'  => lang('Recurring products work without it: the payment gateway charges them. It needs PayPal Website Payments Pro.'),
            ));
        case 'membership_job':
            return array(array(
                'title' => lang('Expiry warnings'),
                'text'  => lang('Switch them on under Accounts and sign-in › Registration & Membership.'),
            ));
        case 'update_exchange_rates':
            return array(array(
                'title' => lang('Only with more than one currency'),
                'text'  => lang('The rates can also be updated by hand in the panel.'),
            ));
        case 'auto_backup':
            return array(array(
                'title' => lang('Once a day at most'),
                'text'  => lang('It writes one backup a day at most; a developer can switch it off in config.php.'),
            ));
        case 'push_job':
            return array(array(
                'title' => lang('A chat message waits a minute'),
                'text'  => lang('So that one already read on screen does not reach the phone as well.'),
            ));
    }

    return array();
}

/**
 * The numbered steps that give a job a scheduled task of its own.
 *
 * @param string $name    a key of pg_cron_jobs()
 * @param array  $job     its pg_cron_jobs() entry
 * @param string $family  PHP_OS_FAMILY as the settings read it
 * @param string $command pg_maintenance_cron_command() for it
 * @return array[] title, text, and command on the step that adds it
 */
function pg_maintenance_cron_steps($name, $job, $family, $command)
{
    $steps = array();

    if (!empty($job['inline'])) {
        $steps[] = array(
            'title' => lang('Usually not needed'),
            'text'  => lang('The general job runs it on every run while it is switched on here; a task of its own only delivers sooner.'),
        );
    }

    $steps[] = array(
        'title' => lang('Open the scheduled tasks'),
        'text'  => ($family === 'Windows')
            ? lang('Open Task Scheduler on the server.')
            : lang('Open the cron jobs of your hosting panel, or run crontab -e on the server.'),
    );

    $steps[] = array(
        'title'   => lang('Add this command'),
        'text'    => lang('Check the path to PHP: on your server it may be another one.'),
        'command' => (string) $command,
    );

    $steps[] = array(
        'title' => lang('Set its schedule'),
        'text'  => lang(array('string' => 'Run it {var:1}.', 'vars' => pg_cron_interval_label((int) $job['interval']))),
    );

    foreach (pg_maintenance_cron_notes($name) as $note) {
        $steps[] = $note;
    }

    if (empty($job['inline'])) {
        $steps[] = array(
            'title' => lang('Or leave it to the general job'),
            'text'  => lang('Switch it on in the list instead: then it needs no task of its own.'),
        );
    }

    return $steps;
}

/**
 * The list of jobs, one row a job. Each switch keeps the name and id the
 * save reads and is only hidden, never disabled, while the master is off: the
 * selection stays posted, so switching the master off and saving does not
 * clear what was chosen.
 *
 * @param array $rows      pg_maintenance_cron_rows()
 * @param bool  $master_on Run the jobs with the general job
 * @return string HTML
 */
function pg_maintenance_cron_list_html($rows, $master_on)
{
    $items = '';

    foreach ($rows as $name => $row) {
        $id = 'job_dispatch_job_' . $name;
        $guide = pg_maintenance_cron_guide_id($name);
        $off = empty($row['active']) ? ' <span class="fg-emphasis-warning">&middot; ' . h(lang('disabled in config.php')) . '</span>' : '';

        $items .= '
                <li class="d-flex flex-wrap align-items-center gap-3 py-3 border-bottom">
                    <div class="flex-grow-1 min-w-0">
                        <div class="pg-field-label" id="' . h($id . '_name') . '">' . h($row['name']) . $off . '</div>' . (($row['line'] !== '') ? '
                        <div class="pg-text-2 fg-secondary">' . h($row['line']) . '</div>' : '') . '
                        <div class="pg-text-meta">' . h($row['schedule']) . '</div>
                    </div>
                    <button type="button" class="pg-button pg-button-ghost pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . h($guide) . '" aria-controls="' . h($guide) . '">' . h(lang('How to schedule it')) . '</button>
                    <div data-pg-cron-switch class="' . ($master_on ? '' : 'd-none ') . 'switch"><input value="1"' . (!empty($row['on']) ? ' checked="checked"' : '') . ' type="checkbox" id="' . h($id) . '" name="job_dispatch_job[' . h($name) . ']" aria-labelledby="' . h($id . '_name') . '"/></div>
                </li>';
    }

    return '<ul class="list-unstyled mb-0" id="job_dispatch_jobs">' . $items . '
            </ul>';
}

/**
 * A job's steps drawer: a missing PHP extension at its top, then the steps
 * numbered in the stepper of the assistants' guides, the command with Copy.
 *
 * @param string $name      a key of pg_cron_jobs()
 * @param array  $job       its pg_cron_jobs() entry
 * @param string $family    PHP_OS_FAMILY as the settings read it
 * @param string $command   pg_maintenance_cron_command() for it
 * @param bool   $in_dialog drawn in the settings dialog
 * @param string $warning   a sentence on what the server lacks, '' for none
 * @return string HTML
 */
function pg_maintenance_cron_guide_drawer($name, $job, $family, $command, $in_dialog, $warning = '')
{
    $id = pg_maintenance_cron_guide_id($name);
    $items = '';

    foreach (pg_maintenance_cron_steps($name, $job, $family, $command) as $index => $step) {
        $code = '';

        if (!empty($step['command'])) {
            $code_id = $id . '-command';
            $code = '
                                <p class="d-flex align-items-center gap-2 mt-3 mb-0"><code class="text-break" id="' . h($code_id) . '">' . h($step['command']) . '</code><span class="pg-copy">' . pg_copy_button($step['command'], lang('Copy the command'), $code_id) . '</span></p>';
        }

        $items .= '
                        <li class="stepper-item active">
                            <div>
                                <div class="pg-field-label">' . h($step['title']) . '</div>
                                <div class="pg-text-2 fg-secondary">' . h($step['text']) . '</div>' . $code . '
                            </div>
                        </li>';
    }

    $top = ($warning !== '') ? '<div class="alert theme-warning py-3 small">' . h($warning) . '</div>' : '';
    $close = '<button type="button" class="pg-button pg-button-quiet pg-button-sm ms-auto" ' . ($in_dialog ? 'data-pgdrawer-close' : 'data-bs-dismiss="drawer"') . '>' . h(lang('Close')) . '</button>';

    return pg_settings_drawer($id, h($job['label']), $top . '<ol class="stepper pg-stepper-cards mb-0">' . $items . '</ol>', $in_dialog, $close, h(pg_maintenance_cron_schedule($job)));
}
