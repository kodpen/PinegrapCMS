<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Updates and maintenance cards: which releases the site takes, what runs on a schedule, who is told when something stops working and the maintenance screen.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// ── Update Channel ──
$pg_settings_cards[] = '
    <div id="pgset-channel" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Update Channel') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <select name="software_update_channel" id="software_update_channel" class="form-control" aria-label="' . h(lang('Update Channel')) . '">
                            <option value="stable"' . (($software_update_channel === 'stable') ? ' selected="selected"' : '') . '>' . lang('Stable') . '</option>
                            <option value="beta"' . (($software_update_channel === 'beta') ? ' selected="selected"' : '') . '>' . lang('Beta') . '</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert' . (($software_update_channel === 'beta') ? ' theme-warning' : ' theme-secondary') . ' mb-0 py-3 small">' . lang('Stable is the release everyone runs. Beta receives new versions earlier, before they have been through as many sites; use it on a test installation or when you are working with us on a fix. Switching back to stable does not remove a beta already installed — the site stays on it until a stable release passes it.') . '</div>
                    </div>' . ($update_backup_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($update_backup ? ' checked="checked"' : '') . ' class="" type="checkbox" id="update_backup" name="update_backup"/></div>
                            <label class="" for="update_backup">' . lang('Back up before updating') . '<br/><span class="form-text">' . lang('Before an update or a repair replaces the software files, the files it replaces and the database are written to data/backups/pre-update-….zip; the three newest are kept. Repair Software rolls the files back from it, and the installation screen restores its database. Switch it off only for a site too large to back up within one request.') . '</span></label>
                        </div>
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>';

// ── Cron Jobs ──
$pg_settings_cards[] = '
                        <div id="pgset-cron" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Cron Jobs') . ' (<span lang="en">' . h($output_os_family) . '</span>)</span>
                                    <a href="#!" class="btn btn-sm btn-outline theme-secondary" data-bs-toggle="dialog" data-bs-target="#cron_jobs"><i class="bi bi-clock-history me-1"></i>' . lang('Setup Instructions') . '</a>
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <div class="form-field">
                                                <div class="switch"><input value="1"' . $job_dispatch_enabled_checked . ' class="" type="checkbox" id="job_dispatch_enabled" name="job_dispatch_enabled"/></div>
                                                <label class="" for="job_dispatch_enabled">' . lang('Run Cron Jobs Automatically') . '<br/><span class="form-text">' . lang('The jobs selected below run together with the general job instead of needing a scheduled task of their own. One job per turn, the one waiting longest first, so a single turn never takes longer than one job. Long jobs (backup, SEO, search index) and short ones take turns in two separate lanes, so a running backup does not hold up the short jobs. Leaving a job\'s own scheduled task in place is safe: every job records when it last finished, whoever started it, so one that is already running on its own schedule is never started a second time from here.') . '</span></label>
                                            </div>
                                        </div>
                                        <div class="col-12" id="job_dispatch_jobs">
                                           <div class="row gy-5">
                                                <div class="col-12 ">
                                                    <div class="alert theme-secondary ">' . lang('The general job must have a scheduled task of its own for this to work, and it is the only one that then needs one. Its command for this server is below.') . '</div>
                                                    <textarea id="cron_job_general_dispatch" aria-label="' . h(lang('General job command')) . '">' . $cron_job_general . '</textarea>
                                                    ' . pg_code_editor_attach(array('id' => 'cron_job_general_dispatch', 'code_type' => 'plain','readonly'=>true )) . '
                                                    <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                                </div>' . $output_job_dispatch_switches . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            (function () {
                                var master = document.getElementById("job_dispatch_enabled");
                                var list = document.getElementById("job_dispatch_jobs");

                                if (!master || !list) {
                                    return;
                                }

                                // The selection stays posted while hidden on purpose: switching
                                // the master off and saving must not clear what was chosen.
                                function sync() {
                                    list.style.display = master.checked ? "" : "none";
                                }

                                master.addEventListener("change", sync);
                                sync();
                            })();
                        </script>';

// The modal explains every job the card can switch on. It is printed outside
// the form, so it travels in its own variable.
$pg_settings_modals[] = '
                <dialog class="dialog dialog-xl" id="cron_jobs" aria-labelledby="cron_jobs_title">
                            <div class="dialog-header">
                                <h5 class="dialog-title" id="cron_jobs_title">' . lang('Cron Jobs') . ' (<span lang="en">' . h($output_os_family) . '</span>)</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                            </div>
                            <div class="dialog-body ">
                               <div class="row gy-5">
                                    <div class="col-12">
                                        <div class="alert theme-success form-text" role="alert">
                                            ' . lang('There are several optional Pinegrap programs or "jobs" which can be scheduled to run automatically on your web server. The setup of these jobs (commonly referred to as "scheduled tasks" or "cron jobs") is optional depending on which Pinegrap features that are going to be used.') . '
                                        </div>
                                        <div class="alert theme-secondary form-text" role="alert">
                                            ' . lang('You do not have to schedule every one of them separately. Turn on "Run Cron Jobs Automatically" further down this page and the general job runs the ones you select, one per turn - then the general job is the only one that needs a scheduled task of its own.') . '
                                        </div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_general_title">' . lang('General Jobs') . '</h5>
                                        <p>' . lang('The general job is an optional feature which only needs to be enabled if you are using the scheduled comment feature to publish comments at a future date &amp; time.') . '</p>
                                        <textarea id="cron_job_general" aria-labelledby="cron_job_general_title">' . $cron_job_general . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_general', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_webhook_title">' . lang('Webhook Delivery Jobs') . '</h5>
                                        <p>' . lang('The webhook delivery job sends the events your integrations asked to hear about: a new order, a cancelled order, a stock change. It usually needs no scheduled task of its own - the general job runs it on every turn as long as "Webhook delivery" is selected in the list further down this page, and on a site with no subscriptions that costs a single indexed read.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Schedule the command below only if you want an event delivered within a minute of it happening, without waiting for the general job. It is the same work either way; a dedicated task simply runs it more often.') . '</div>
                                        <textarea id="cron_job_webhook" aria-labelledby="cron_job_webhook_title">' . $cron_job_webhook . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_webhook', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_push_title">' . lang('Device Notification Jobs') . '</h5>
                                        <p>' . lang('The device notification job wakes the phones and computers your operators have turned notifications on for: a new order, a submitted form, a new comment, an unanswered chat message. Like the webhook job it runs inside the general job on every turn as long as "Device notifications" is selected in the list further down this page, so it needs no scheduled task of its own.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: A chat message waits a minute before it is sent, so that a message somebody has already read on screen does not arrive on their phone as well. The delay an operator feels is that wait plus however often this job runs.') . '</div>
                                        <textarea id="cron_job_push" aria-labelledby="cron_job_push_title">' . $cron_job_push . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_push', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_exchange_rates_title">' . lang('Exchange Rates Jobs') . '</h5>
                                        <p>' . lang('The exchange rates job is an optional feature which only needs to be enabled if you are using the multi-currency e-commerce feature and want exchange rates for currencies to be updated automatically. Exchange rates can be manually updated via the Admin Panel.') . '</p>
                                        <textarea id="cron_job_exchange_rates" aria-labelledby="cron_job_exchange_rates_title">' . $cron_job_exchange_rates . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_exchange_rates', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_email_campaign_title">' . lang('Email Campaign Jobs') . '</h5>
                                        <p>' . lang(array('string' => 'The e-mail campaign job is an alternative to users manually sending e-mail campaigns from the Software. It is a script that can be scheduled to automatically send e-mail Campaigns. Also, the e-mail campaign job allows e-mail campaigns to be scheduled to be sent at a later time. If you are interested in using the e-mail campaign job, please complete {var:1}.', 'vars' => array('<a class="pg-link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/smtp_settings.php">' . lang('campaign smtp settings') . '</a>'))) . '</p>
                                        <div class="alert theme-danger">' . lang('WARNING: Once the e-mail campaign job is enabled, it will send e-mails for all campaigns where the status was "Ready to Send", so please make sure you update the status for old, incomplete, e-mail campaigns to be "Cancelled" before you enable the e-mail campaign job.') . '</div>
                                        <textarea id="cron_job_email_campaign" aria-labelledby="cron_job_email_campaign_title">' . $cron_job_email_campaign . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_email_campaign', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_search_index_title">' . lang('Update Search Index Jobs') . '</h5>
                                        <p>' . lang('The update search index job is an alternative to clicking the Update Search Index button on the Pages tab. It does the spidering of your website and updates the search index with any new or changed content it finds. Since this is an intensive script that may slow down your site while it runs, you should not run it more than once an hour at the most. Less frequently is even better.') . '</p>
                                        <textarea id="cron_job_search_index" aria-labelledby="cron_job_search_index_title">' . $cron_job_search_index . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_search_index', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_seo_score_title">' . lang('SEO Score Jobs') . '</h5>
                                        <p>' . lang('The SEO score job recalculates the SEO score of every page, product and product group. It only reads database columns, so it is quick, but it has to cover the whole site because the duplicate title and duplicate description checks depend on every other record. Schedule it before the SEO structure analysis job.') . '</p>
                                        <textarea id="cron_job_seo_score" aria-labelledby="cron_job_seo_score_title">' . $cron_job_seo_score . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_seo_score', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_seo_analyze_title">' . lang('SEO Structure Analysis Jobs') . '</h5>
                                        ' . $output_warnings_for_seo_analyze . '
                                        <p>' . lang('The SEO structure analysis job renders each page in the background and examines the resulting HTML: heading order, image alt text, internal links and orphan pages. This is the expensive half of the SEO score, so it works within a time budget and may need several runs to get through a large site for the first time.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Schedule this job to run after the SEO score job, because a catalog page is scored from the products it lists.') . '</div>
                                        <textarea id="cron_job_seo_analyze" aria-labelledby="cron_job_seo_analyze_title">' . $cron_job_seo_analyze . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_seo_analyze', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_requrring_payment_title">' . lang('Recurring Payment Jobs') . '</h5>
                                        <p>' . lang('The recurring payment job is an optional feature which only needs to be enabled if you want actions to be performed when a recurring payment profile is disabled (i.e. suspended, cancelled, or expired) (e.g. credit card declined). This job requires the PayPal Website Payments Pro payment gateway. The following actions can be performed when the recurring payment profile is disabled. These options can be set in the properties for the product that creates the recurring payment profile.') . '<br>
                                        ' . lang('Expire membership.') . '<br>
                                        ' . lang('Revoke private access.') . '<br>
                                        ' . lang('Send an e-mail to the customer.') . '<br>
                                        ' . lang('For example, if you have a membership product which has a monthly recurring payment, you might want to expire a persons membership if his/her payment fails (e.g. credit card declined). Also, you might want to send an e-mail to the member with a link to order new membership.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: The Recurring Payment Job is NOT REQUIRED for setting up Recurring Products. That is handled through your payment gateway automatically once an Order is submitted for one or more recurring products through your Pinegrap website.') . '</div>
                                        <textarea id="cron_job_requrring_payment" aria-labelledby="cron_job_requrring_payment_title">' . $cron_job_requrring_payment . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_requrring_payment', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_membership_title">' . lang('Membership Jobs') . '</h5>
                                        <p>' . lang('The membership job is an optional feature which only needs to be enabled if you want one or more of the features below. Send membership expiration warning e-mail to members whose membership is about to expire. You can enable and configure this feature via the Settings Page. Remove contacts from the Membership Contact Group when a contact\'s membership is no longer valid (e.g. membership has expired). This feature runs automatically once a scheduled task is setup for the membership job.') . '</p>
                                        <textarea id="cron_job_membership" aria-labelledby="cron_job_membership_title">' . $cron_job_membership . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_membership', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5 id="cron_job_auto_backup_title">' . lang('Auto Backup Jobs') . '</h5>
                                        ' . $output_warnings_for_auto_backup . '
                                        <p>' . lang('The auto backup job is an optional feature that must be enabled only if you want the automatic backup feature.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Auto Backup feature is available by default but can be disabled by a developer from the config.php file. This feature can only be operated once a day.') . '</div>
                                        <textarea id="cron_job_auto_backup" aria-labelledby="cron_job_auto_backup_title">' . $cron_job_auto_backup . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_auto_backup', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a week') . '</div>
                                    </div>
                                </div>
                            </div>
                </dialog>';

// ── Alerts ──
//
// Who is told when a backup fails, a scheduled task stops or the software
// files change, and by which channel (includes/fn/ops_alerts.php). The bell is
// not a choice: an alert is a notification row, and the row is what makes it
// once a day. Before the 2026.11.0 columns the card says so, and the save
// module writes nothing for it either.
$output_ops_alert_settings = '<div class="col-12"><div class="alert theme-secondary mb-0">' . lang('Run the upgrade to use this setting.') . '</div></div>';

if ($ops_alerts_ready) {

    $output_ops_alert_settings = '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($ops_alerts ? ' checked="checked"' : '') . ' class="" type="checkbox" id="ops_alerts" name="ops_alerts"/></div>
                            <label class="" for="ops_alerts">' . lang('Operations alerts') . '<br/><span class="form-text">' . lang('Tells the people who run the site when something stops working: an automatic backup fails or goes stale, a scheduled task stops running, the software files no longer match the release, PHP errors pile up, the disk runs full, e-mails or event notifications stop going out, or the site\'s certificate is about to run out. Each problem is told at most once a day while it lasts, on the panel bell of managers and administrators and by the channels below. System Status lists the alerts of the last seven days.') . '</span></label>
                        </div>
                    </div>
                    <div class="col-12" id="ops_alert_fields">
                        <div class="row gy-5">
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . ($ops_alert_email ? ' checked="checked"' : '') . ' class="" type="checkbox" id="ops_alert_email" name="ops_alert_email"/></div>
                                    <label class="" for="ops_alert_email">' . lang('Send alerts by e-mail') . '</label>
                                </div>
                            </div>
                            <div class="pg-f-lg">
                                <label for="ops_alert_recipients" class="form-label">' . lang('Alert recipients') . '</label>
                                <input type="text" name="ops_alert_recipients" id="ops_alert_recipients" maxlength="1000" class="form-control" value="' . h($ops_alert_recipients) . '" placeholder="' . h($email_address) . '" inputmode="email" autocomplete="off" />
                                <div class="form-text">' . lang('Separate several addresses with commas. Left empty, the alerts go to the site e-mail address.') . '</div>
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . ($ops_alert_push ? ' checked="checked"' : '') . ' class="" type="checkbox" id="ops_alert_push" name="ops_alert_push"/></div>
                                    <label class="" for="ops_alert_push">' . lang('Send alerts to subscribed devices') . '<br/><span class="form-text">' . lang('Phones and computers on which a manager or administrator turned notifications on. Delivered by the Device notifications job, which has to be selected under Cron Jobs.') . '</span></label>
                                </div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="ops_alert_error_rate" class="form-label">' . lang('PHP errors in an hour') . '</label>
                                <input type="number" name="ops_alert_error_rate" id="ops_alert_error_rate" min="0" max="100000" step="1" class="form-control" value="' . (int) $ops_alert_error_rate . '" />
                            </div>
                            <div class="pg-f-xs">
                                <label for="ops_alert_disk_percent" class="form-label">' . lang('Free disk space below %') . '</label>
                                <div class="form-range pg-range-bubble" data-pg-range="' . h(lang('{var:1}%')) . '">
                                    <input type="range" name="ops_alert_disk_percent" id="ops_alert_disk_percent" min="0" max="50" step="1" class="form-range-input" value="' . (int) $ops_alert_disk_percent . '" />
                                </div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="ops_alert_mail_failures" class="form-label">' . lang('Failed e-mails in a day') . '</label>
                                <input type="number" name="ops_alert_mail_failures" id="ops_alert_mail_failures" min="0" max="100000" step="1" class="form-control" value="' . (int) $ops_alert_mail_failures . '" />
                            </div>
                            <div class="pg-f-xs">
                                <label for="ops_alert_cert_days" class="form-label">' . lang('Certificate days left') . '</label>
                                <input type="number" name="ops_alert_cert_days" id="ops_alert_cert_days" min="0" max="60" step="1" class="form-control" value="' . (int) $ops_alert_cert_days . '" />
                            </div>
                            <div class="col-12">
                                <div class="form-text">' . lang('An alert goes out when the number is reached: as many PHP errors recorded in an hour, free disk space under this share of the disk (or under 1 GB), as many e-mails of the last day failing, or the site\'s certificate ending within as many days. 0 switches that check off. The certificate is checked once a day, only while the site is served over https.') . '</div>
                            </div>
                        </div>
                    </div>';

    // The channels mean nothing while the master switch is off. They stay
    // in the form and are posted while hidden, so switching alerts off and
    // saving keeps the recipients for next time.
    $pg_settings_scripts[] = '
    <script>
        (function () {
            var master = document.getElementById("ops_alerts");
            var fields = document.getElementById("ops_alert_fields");

            if (!master || !fields) {
                return;
            }

            function sync() {
                fields.style.display = master.checked ? "" : "none";
            }

            master.addEventListener("change", sync);
            sync();
        })();
    </script>';
}

$pg_settings_cards[] = '
    <div id="pgset-alerts" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Alerts') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_ops_alert_settings . '
                </div>
            </div>
        </div>
    </div>';

// ── Maintenance Mode ──
//
// What a visitor sees while the site is being worked on
// (includes/fn/maintenance_mode.php), and the address an uptime monitor
// polls. Before the 2026.11.0 columns the card says so and the save module
// writes nothing for it.
$output_maintenance_settings = '<div class="col-12"><div class="alert theme-secondary mb-0">' . lang('Run the upgrade to use this setting.') . '</div></div>';

if ($maintenance_ready) {

    $maintenance_state = '';

    if ($maintenance_mode && pg_maintenance_holds(true, $maintenance_until, time())) {
        $maintenance_state = '
                    <div class="col-12">
                        <div class="alert theme-warning mb-0 py-3 small">' . lang('Maintenance mode is on: visitors see the maintenance screen.') . '</div>
                    </div>';
    } elseif ($maintenance_mode) {
        $maintenance_state = '
                    <div class="col-12">
                        <div class="alert theme-secondary mb-0 py-3 small">' . lang('The end time of maintenance mode has passed, so visitors see the site again. Switch it off, or set a new end time.') . '</div>
                    </div>';
    }

    $output_maintenance_settings = '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($maintenance_mode ? ' checked="checked"' : '') . ' class="" type="checkbox" id="maintenance_mode" name="maintenance_mode"/></div>
                            <label class="" for="maintenance_mode">' . lang('Maintenance Mode') . '<br/><span class="form-text">' . lang('Visitors who open a page of the site see the maintenance screen - the designed error page, or the classic one - with the 503 answer that tells search engines to come back later. Signed-in managers and administrators, and the addresses below, see the site as usual. Files, the control panel and payment callbacks keep working.') . '</span></label>
                        </div>
                    </div>' . $maintenance_state . '
                    <div class="pg-f-sm">
                        <label for="maintenance_until" class="form-label">' . lang('Ends at') . '</label>
                        <input type="datetime-local" data-pg-datetime name="maintenance_until" id="maintenance_until" class="form-control" value="' . h($maintenance_until_value) . '" />' . get_date_picker_format() . pg_fields_script() . '
                    </div>
                    <div class="col-12">
                        <div class="form-text">' . lang('Optional. At this time the site opens to visitors again by itself, and search engines are told to come back then.') . '</div>
                    </div>
                    <div class="col-12">
                        <label for="maintenance_allow_ips" class="form-label">' . lang('Addresses that see the site') . '</label>
                        <textarea name="maintenance_allow_ips" id="maintenance_allow_ips" rows="2" class="form-control" spellcheck="false">' . h($maintenance_allow_ips) . '</textarea>
                        <div class="form-text">' . lang(array('string' => 'Addresses or ranges (CIDR), separated by commas or one per line. Your address: {var:1}', 'vars' => array(h($maintenance_my_ip)))) . '</div>'
                        // Behind a tunnel or a proxy that is not declared,
                        // the address shown is the one every visitor has.
                        . ($maintenance_my_ip_shared ? '
                        <div class="alert theme-warning mt-3 mb-0 py-3 small">' . lang('This is the server or a proxy, not your own address: every visitor reaches the site through it, so listing it lets everyone see the site. Add it to Trusted Proxies on the Firewall card first; then this line shows your own address.') . '</div>' : '') . '
                    </div>
                    <div class="col-12">
                        <div class="form-text">' . lang('Uptime monitors can poll this address. It answers 200 while the site is up and its general job runs, and 503 during maintenance or when the general job has stopped:') . ' <code>' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/health.php') . '</code></div>
                    </div>';
}

$pg_settings_cards[] = '
    <div id="pgset-maintenance" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Maintenance Mode') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_maintenance_settings . '
                </div>
            </div>
        </div>
    </div>';
