<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the General cards: where the site is, what the software is, which releases it takes, the time it keeps and what runs on a schedule.
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

// ── Server & Domain ──
$pg_settings_cards[] = '
    <div id="pgset-server" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Server & Domain') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="pg-f-lg">
                        <label for="ip" class="form-label">' . lang('Website IP Address') . '</label>
                        <input type="text" name="ip" id="ip" readonly="true" class="disabled form-control" value="' . h($server_addr) . '" inputmode="numeric" data-inputmask-alias="ip"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="hostname" class="form-label">' . lang('Hostname') . '</label>
                        <input type="text" name="hostname" id="hostname" maxlength="255" class="form-control" value="' . h($hostname) . '" />
                    </div>
                    <div class="pg-f-lg">
                        <label for="email_address" class="form-label">' . lang('Support E-mail Address') . '</label>
                        <input type="text" name="email_address" id="email_address" autocomplete="off" class="form-control" value="' . h($email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>
                    <div class="pg-f-lg">
                        <label for="proxy_address" class="form-label">' . lang('Proxy Address') . '</label>
                        <input type="text" name="proxy_address" id="proxy_address" maxlength="255" class="form-control" value="' . h($proxy_address) . '" />
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Software ──
$pg_settings_cards[] = '
    <div id="pgset-software" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Software') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    ' . $output_software_language . '
                    <div class="pg-f-lg">
                        <label for="subscription_id" class="form-label">' . lang('Subscription ID') . '</label>
                        <input type="text" name="subscription_id" id="subscription_id" maxlength="10" autocomplete="false" class="form-control" value="' . SUBSCRIPTION_ID . '" />
                    </div>
                    <div class="pg-f-lg">
                        <label for="subscription_key" class="form-label">' . lang('Subscription Key') . '</label>
                        <input type="text" name="subscription_key" id="subscription_key" maxlength="19" autocomplete="false" class="form-control input-mask-key-code" value="' . SUBSCRIPTION_KEY . '" />
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input value="1"' . $debug_checked . ' class="form-check-input" type="checkbox" id="debug" name="debug"/>
                            <label class="form-check-label" for="debug">' . lang('Verbose Database Errors') . '</label>
                            <div class="form-text">' . lang('Keep this on only while developing; the error text is shown to visitors.') . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Update Channel ──
$pg_settings_cards[] = '
    <div id="pgset-channel" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                ' . lang('Update Channel') . '
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-12">
                        <select name="software_update_channel" id="software_update_channel" class="form-select" aria-label="' . h(lang('Update Channel')) . '">
                            <option value="stable"' . (($software_update_channel === 'stable') ? ' selected="selected"' : '') . '>' . lang('Stable') . '</option>
                            <option value="beta"' . (($software_update_channel === 'beta') ? ' selected="selected"' : '') . '>' . lang('Beta') . '</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert' . (($software_update_channel === 'beta') ? ' alert-warning' : ' alert-secondary') . ' mb-0 py-2 small">' . lang('Stable is the release everyone runs. Beta receives new versions earlier, before they have been through as many sites; use it on a test installation or when you are working with us on a fix. Switching back to stable does not remove a beta already installed — the site stays on it until a stable release passes it.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

// ── Date & Time ──
$pg_settings_cards[] = '
                        <div id="pgset-datetime" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Date & Time') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <label class="form-label">' . lang('Current Site Time') . '</label>
                                            <div class="w-100">' . get_absolute_time(array('timestamp' => time(), 'timezone_type' => 'site')) . '</div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="timezone" class="form-label">' . lang('Timezone') . '</label>
                                            <select class="form-select" id="timezone" name="timezone">' . $output_timezone_options . '</select>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Date Format') . '</label>
                                            <div class="form-check">
                                                <input value="month_day" class="form-check-input" type="radio" id="date_format_month_day" name="date_format" ' . $date_format_month_day_checked . '>
                                                <label class="form-check-label" for="date_format_month_day">'. lang('month') . '/'. lang('day') . '/'. lang('year') . ' (2/14/' . date('Y') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="day_month" class="form-check-input" type="radio" id="date_format_day_month" name="date_format" ' . $date_format_day_month_checked . '>
                                                <label class="form-check-label" for="date_format_day_month">'. lang('day') . '/'. lang('month') . '/'. lang('year') . ' (14/2/' . date('Y') . ')</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Time Format') . '</label>
                                            <div class="form-check">
                                                <input value="twelve_hours" class="form-check-input" type="radio" id="time_format_twelve_hours" name="time_format" ' . $time_format_twelve_hours_checked . '>
                                                <label class="form-check-label" for="time_format_twelve_hours">'. lang('hour') . ':'. lang('minute') . ' am/pm (11:30 pm)</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="twenty_four_hours" class="form-check-input" type="radio" id="time_format_twenty_four_hours" name="time_format" ' . $time_format_twenty_four_hours_checked . '>
                                                <label class="form-check-label" for="time_format_twenty_four_hours">'. lang('hour') . ':'. lang('minute') . ' (23:30)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Cron Jobs ──
$pg_settings_cards[] = '
                        <div id="pgset-cron" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Cron Jobs') . ' (' . $output_os_family . ')</span>
                                    <a href="#!" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cron_jobs"><i class="bi bi-clock-history me-1"></i>' . lang('Setup Instructions') . '</a>
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $job_dispatch_enabled_checked . ' class="form-check-input" type="checkbox" id="job_dispatch_enabled" name="job_dispatch_enabled"/>
                                                <label class="form-check-label" for="job_dispatch_enabled">' . lang('Run Cron Jobs Automatically') . '<br/><span class="form-text">' . lang('The jobs selected below run together with the general job instead of needing a scheduled task of their own. One job per turn, the one waiting longest first, so a single turn never takes longer than one job. Leaving a job\'s own scheduled task in place is safe: every job records when it last finished, whoever started it, so one that is already running on its own schedule is never started a second time from here.') . '</span></label>
                                            </div>
                                        </div>
                                        <div class="col-12" id="job_dispatch_jobs">
                                           <div class="row gy-3">
                                                <div class="col-12 ">
                                                    <div class="alert alert-secondary ">' . lang('The general job must have a scheduled task of its own for this to work, and it is the only one that then needs one. Its command for this server is below.') . '</div>
                                                    <textarea id="cron_job_general_dispatch">' . $cron_job_general . '</textarea>
                                                    ' . get_codemirror_javascript(array('id' => 'cron_job_general_dispatch', 'code_type' => 'plain','readonly'=>true )) . '
                                                    <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
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
                <div class="modal fade" id="cron_jobs" tabindex="-1" aria-labelledby="cron_jobs" aria-hidden="true">
                    <div class="modal-dialog modal-xl ">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">' . lang('Cron Jobs') . ' (' . $output_os_family . ')</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body ">
                               <div class="row gy-3">
                                    <div class="col-12">
                                        <div class="alert alert-success form-text" role="alert">
                                            ' . lang('There are several optional Pinegrap programs or "jobs" which can be scheduled to run automatically on your web server. The setup of these jobs (commonly referred to as "scheduled tasks" or "cron jobs") is optional depending on which Pinegrap features that are going to be used.') . '
                                        </div>
                                        <div class="alert alert-secondary form-text" role="alert">
                                            ' . lang('You do not have to schedule every one of them separately. Turn on "Run Cron Jobs Automatically" further down this page and the general job runs the ones you select, one per turn - then the general job is the only one that needs a scheduled task of its own.') . '
                                        </div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('General Jobs') . '</h5>
                                        <p>' . lang('The general job is an optional feature which only needs to be enabled if you are using the scheduled comment feature to publish comments at a future date &amp; time.') . '</p>
                                        <textarea id="cron_job_general">' . $cron_job_general . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_general', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Webhook Delivery Jobs') . '</h5>
                                        <p>' . lang('The webhook delivery job sends the events your integrations asked to hear about: a new order, a cancelled order, a stock change. It usually needs no scheduled task of its own - the general job runs it on every turn as long as "Webhook delivery" is selected in the list further down this page, and on a site with no subscriptions that costs a single indexed read.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: Schedule the command below only if you want an event delivered within a minute of it happening, without waiting for the general job. It is the same work either way; a dedicated task simply runs it more often.') . '</div>
                                        <textarea id="cron_job_webhook">' . $cron_job_webhook . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_webhook', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Device Notification Jobs') . '</h5>
                                        <p>' . lang('The device notification job wakes the phones and computers your operators have turned notifications on for: a new order, a submitted form, a new comment, an unanswered chat message. Like the webhook job it runs inside the general job on every turn as long as "Device notifications" is selected in the list further down this page, so it needs no scheduled task of its own.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: A chat message waits a minute before it is sent, so that a message somebody has already read on screen does not arrive on their phone as well. The delay an operator feels is that wait plus however often this job runs.') . '</div>
                                        <textarea id="cron_job_push">' . $cron_job_push . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_push', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Exchange Rates Jobs') . '</h5>
                                        <p>' . lang('The exchange rates job is an optional feature which only needs to be enabled if you are using the multi-currency e-commerce feature and want exchange rates for currencies to be updated automatically. Exchange rates can be manually updated via the Admin Panel.') . '</p>
                                        <textarea id="cron_job_exchange_rates">' . $cron_job_exchange_rates . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_exchange_rates', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Email Campaign Jobs') . '</h5>
                                        <p>' . lang(array('string' => 'The e-mail campaign job is an alternative to users manually sending e-mail campaigns from the Software. It is a script that can be scheduled to automatically send e-mail Campaigns. Also, the e-mail campaign job allows e-mail campaigns to be scheduled to be sent at a later time. If you are interested in using the e-mail campaign job, please complete {var:1}.', 'vars' => array('<a class="link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/smtp_settings.php">' . lang('campaign smtp settings') . '</a>'))) . '</p>
                                        <div class="alert alert-danger">' . lang('WARNING: Once the e-mail campaign job is enabled, it will send e-mails for all campaigns where the status was "Ready to Send", so please make sure you update the status for old, incomplete, e-mail campaigns to be "Cancelled" before you enable the e-mail campaign job.') . '</div>
                                        <textarea id="cron_job_email_campaign">' . $cron_job_email_campaign . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_email_campaign', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Update Search Index Jobs') . '</h5>
                                        <p>' . lang('The update search index job is an alternative to clicking the Update Search Index button on the Pages tab. It does the spidering of your website and updates the search index with any new or changed content it finds. Since this is an intensive script that may slow down your site while it runs, you should not run it more than once an hour at the most. Less frequently is even better.') . '</p>
                                        <textarea id="cron_job_search_index">' . $cron_job_search_index . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_search_index', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('SEO Score Jobs') . '</h5>
                                        <p>' . lang('The SEO score job recalculates the SEO score of every page, product and product group. It only reads database columns, so it is quick, but it has to cover the whole site because the duplicate title and duplicate description checks depend on every other record. Schedule it before the SEO structure analysis job.') . '</p>
                                        <textarea id="cron_job_seo_score">' . $cron_job_seo_score . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_seo_score', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('SEO Structure Analysis Jobs') . '</h5>
                                        ' . $output_warnings_for_seo_analyze . '
                                        <p>' . lang('The SEO structure analysis job renders each page in the background and examines the resulting HTML: heading order, image alt text, internal links and orphan pages. This is the expensive half of the SEO score, so it works within a time budget and may need several runs to get through a large site for the first time.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: Schedule this job to run after the SEO score job, because a catalog page is scored from the products it lists.') . '</div>
                                        <textarea id="cron_job_seo_analyze">' . $cron_job_seo_analyze . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_seo_analyze', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Recurring Payment Jobs') . '</h5>
                                        <p>' . lang('The recurring payment job is an optional feature which only needs to be enabled if you want actions to be performed when a recurring payment profile is disabled (i.e. suspended, cancelled, or expired) (e.g. credit card declined). This job requires the PayPal Website Payments Pro payment gateway. The following actions can be performed when the recurring payment profile is disabled. These options can be set in the properties for the product that creates the recurring payment profile.') . '<br>
                                        ' . lang('Expire membership.') . '<br>
                                        ' . lang('Revoke private access.') . '<br>
                                        ' . lang('Send an e-mail to the customer.') . '<br>
                                        ' . lang('For example, if you have a membership product which has a monthly recurring payment, you might want to expire a persons membership if his/her payment fails (e.g. credit card declined). Also, you might want to send an e-mail to the member with a link to order new membership.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: The Recurring Payment Job is NOT REQUIRED for setting up Recurring Products. That is handled through your payment gateway automatically once an Order is submitted for one or more recurring products through your Pinegrap website.') . '</div>
                                        <textarea id="cron_job_requrring_payment">' . $cron_job_requrring_payment . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_requrring_payment', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Membership Jobs') . '</h5>
                                        <p>' . lang('The membership job is an optional feature which only needs to be enabled if you want one or more of the features below. Send membership expiration warning e-mail to members whose membership is about to expire. You can enable and configure this feature via the Settings Page. Remove contacts from the Membership Contact Group when a contact\'s membership is no longer valid (e.g. membership has expired). This feature runs automatically once a scheduled task is setup for the membership job.') . '</p>
                                        <textarea id="cron_job_membership">' . $cron_job_membership . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_membership', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Auto Backup Jobs') . '</h5>
                                        ' . $output_warnings_for_auto_backup . '
                                        <p>' . lang('The auto backup job is an optional feature that must be enabled only if you want the automatic backup feature.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: Auto Backup feature is available by default but can be disabled by a developer from the config.php file. This feature can only be operated once a day.') . '</div>
                                        <textarea id="cron_job_auto_backup">' . $cron_job_auto_backup . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_auto_backup', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a week') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
