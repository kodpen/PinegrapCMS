<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the General cards: where the site is, what the software is, which releases it takes, the time it keeps, what runs on a schedule and how it is backed up.
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
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Server & Domain') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
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
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Software') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
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
                        <div class="form-field">
                            <div class="switch"><input value="1"' . $debug_checked . ' class="" type="checkbox" id="debug" name="debug"/></div>
                            <label class="" for="debug">' . lang('Verbose Database Errors') . '</label>
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

// ── Date & Time ──
$pg_settings_cards[] = '
                        <div id="pgset-datetime" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Date & Time') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-5">
                                        <div class="col-12 ">
                                            <label class="form-label">' . lang('Current Site Time') . '</label>
                                            <div class="w-100">' . get_absolute_time(array('timestamp' => time(), 'timezone_type' => 'site')) . '</div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="timezone" class="form-label">' . lang('Timezone') . '</label>
                                            <select class="form-control" id="timezone" name="timezone">' . $output_timezone_options . '</select>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Date Format') . '</label>
                                            <div class="form-field">
                                                <input value="month_day" class="radio" type="radio" id="date_format_month_day" name="date_format" ' . $date_format_month_day_checked . '>
                                                <label class="" for="date_format_month_day">'. lang('month') . '/'. lang('day') . '/'. lang('year') . ' (2/14/' . date('Y') . ')</label>
                                            </div>
                                            <div class="form-field">
                                                <input value="day_month" class="radio" type="radio" id="date_format_day_month" name="date_format" ' . $date_format_day_month_checked . '>
                                                <label class="" for="date_format_day_month">'. lang('day') . '/'. lang('month') . '/'. lang('year') . ' (14/2/' . date('Y') . ')</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Time Format') . '</label>
                                            <div class="form-field">
                                                <input value="twelve_hours" class="radio" type="radio" id="time_format_twelve_hours" name="time_format" ' . $time_format_twelve_hours_checked . '>
                                                <label class="" for="time_format_twelve_hours">'. lang('hour') . ':'. lang('minute') . ' am/pm (11:30 pm)</label>
                                            </div>
                                            <div class="form-field">
                                                <input value="twenty_four_hours" class="radio" type="radio" id="time_format_twenty_four_hours" name="time_format" ' . $time_format_twenty_four_hours_checked . '>
                                                <label class="" for="time_format_twenty_four_hours">'. lang('hour') . ':'. lang('minute') . ' (23:30)</label>
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
                                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Cron Jobs') . ' (' . $output_os_family . ')</span>
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
                                                    <textarea id="cron_job_general_dispatch">' . $cron_job_general . '</textarea>
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

// ── Backups ──
//
// What the automatic backup keeps and where its copy goes. The controls exist
// only once the 2026.4.8 columns do; before that the card says so, and the
// save module writes nothing for it either.
$output_backup_settings = '<div class="col-12"><div class="alert theme-secondary mb-0">' . lang('Run the upgrade to use this setting.') . '</div></div>';

if ($backup_settings_ready) {

    $backup_secret_placeholder_ftp = $backup_ftp_password_stored ? lang('Saved') : '';
    $backup_secret_placeholder_s3  = $backup_s3_secret_stored ? lang('Saved') : '';
    $backup_secret_help = ($backup_ftp_password_stored || $backup_s3_secret_stored)
        ? lang('The secret and the password are stored and are not shown. Leave these boxes empty to keep them.')
        : lang('The secret and the password are stored encrypted and are never shown again after saving.');

    // Google Drive, OneDrive and Dropbox go through an account connected for
    // backups. A connection started here comes back with the destination it
    // was started for, which is shown chosen (not yet saved) together with
    // the new account.
    $backup_connect_type = isset($_GET['backup_connect']) ? pg_backup_remote_type($_GET['backup_connect']) : '';
    $backup_remote_type_shown = $backup_remote_type;

    if (pg_backup_cloud_is_type($backup_connect_type)) {

        $backup_connect_flash = pg_connect_flash_take();

        if ($backup_connect_flash && ($backup_connect_flash['kind'] === 'error')) {
            $liveform->add_error(h($backup_connect_flash['text']));
        } elseif ($backup_connect_flash) {
            $liveform->add_notice(h($backup_connect_flash['text']) . ' ' . lang('Save the settings to start sending the backups there.'));
            $backup_remote_type_shown = $backup_connect_type;
        }
    }

    $backup_connect_ready = pg_connect_schema_ready() && pg_connect_can_seal();
    $backup_cloud_groups = '';

    foreach (pg_backup_cloud_types() as $backup_cloud_type => $backup_cloud) {

        $backup_cloud_provider = pg_connect_provider($backup_cloud['provider']);

        if (!is_array($backup_cloud_provider)) {
            continue;
        }

        $backup_cloud_ready = $backup_connect_ready && pg_connect_app_ready(pg_connect_app($backup_cloud['provider']));

        $backup_cloud_accounts = pg_connect_schema_ready()
            ? pg_connect_accounts(array('user_id' => 0, 'purpose' => 'backup', 'provider' => $backup_cloud['provider']))
            : array();

        $backup_cloud_current = $backup_remote_cloud[$backup_cloud_type]['connection_id'];
        $backup_cloud_ids = array();

        foreach ($backup_cloud_accounts as $backup_cloud_account) {
            $backup_cloud_ids[] = $backup_cloud_account['id'];
        }

        // Back from connecting: the account just connected, the newest one,
        // unless the destination already uses one that is still there.
        if (($backup_remote_type_shown === $backup_cloud_type) && ($backup_connect_type === $backup_cloud_type)
            && !in_array($backup_cloud_current, $backup_cloud_ids, true) && $backup_cloud_ids
        ) {
            $backup_cloud_current = $backup_cloud_ids[0];
        }

        $backup_cloud_options = '<option value="0">' . h(lang('Choose a connected account')) . '</option>';

        foreach ($backup_cloud_accounts as $backup_cloud_account) {
            $backup_cloud_options .= '<option value="' . (int) $backup_cloud_account['id'] . '"'
                . (($backup_cloud_account['id'] === $backup_cloud_current) ? ' selected="selected"' : '') . '>'
                . h($backup_cloud_account['label'] . (($backup_cloud_account['status'] !== 'active') ? ' - ' . lang('Connect again') : ''))
                . '</option>';
        }

        if ($backup_cloud_type === 'gdrive') {
            $backup_cloud_help = lang('A folder of this name in My Drive. The site sees only the files it put there itself, so a folder made by hand is not used. Weeks beyond the number kept are deleted there as well.');
        } else {
            $backup_cloud_help = lang(array(
                'string' => 'A folder inside the application\'s own folder under Apps in {var:1}. The site sees nothing else in the account. Weeks beyond the number kept are deleted there as well.',
                'vars'   => $backup_cloud['label'],
            ));
        }

        $backup_cloud_groups .= '
                    <div class="col-12" id="backup_remote_' . $backup_cloud_type . '_fields">
                        <div class="row gy-5">' . ($backup_cloud_ready ? '' : '
                            <div class="col-12">
                                <div class="alert theme-secondary mb-0 py-3 small">' . h(lang(array(
                                    'string' => 'The {var:1} application is not set up yet. Enter it on Connected accounts first.',
                                    'vars'   => $backup_cloud_provider['label'],
                                ))) . ' <a href="connections.php">' . h(lang('Connected accounts')) . '</a></div>
                            </div>') . '
                            <div class="pg-f-md">
                                <label for="backup_' . $backup_cloud_type . '_connection" class="form-label">' . lang('Account') . '</label>
                                <select name="backup_' . $backup_cloud_type . '_connection" id="backup_' . $backup_cloud_type . '_connection" class="form-control">' . $backup_cloud_options . '</select>' . ($backup_cloud_accounts ? '' : '
                                <div class="form-text">' . h(lang('No account is connected for backups yet.')) . '</div>') . '
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_' . $backup_cloud_type . '_folder" class="form-label">' . lang('Folder') . '</label>
                                <input type="text" name="backup_' . $backup_cloud_type . '_folder" id="backup_' . $backup_cloud_type . '_folder" maxlength="200" class="form-control" value="' . h($backup_remote_cloud[$backup_cloud_type]['folder']) . '" placeholder="' . h(pg_backup_cloud_default_folder(HOSTNAME_SETTING)) . '" />
                            </div>
                            <div class="col-12">
                                <div class="form-text">' . h($backup_cloud_help) . '</div>
                            </div>' . ($backup_cloud_ready ? '
                            <div class="col-12">
                                <button type="submit" form="pgset_backup_connect_' . $backup_cloud_type . '" class="btn btn-sm btn-outline theme-primary rounded-pill">
                                    <i class="bi ' . h($backup_cloud_provider['icon']) . ' me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect a {var:1} account', 'vars' => $backup_cloud_provider['label']))) . '</button>
                            </div>' : '') . '
                        </div>
                    </div>';

        // The button above submits this form by its form attribute: it
        // lives outside the settings form, since forms cannot be nested.
        if ($backup_cloud_ready) {
            $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="pgset_backup_connect_' . $backup_cloud_type . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($backup_cloud['provider'], 'backup', pg_settings_url('general') . '?backup_connect=' . $backup_cloud_type . '#pgset-backup') . '
            </form>';
        }
    }

    $backup_remote_status = '';

    // The status of the destination that is saved, not of one only shown.
    if (($backup_remote_type !== '') && ($backup_remote_type === $backup_remote_type_shown)) {

        if ($backup_remote_error !== '') {
            $backup_remote_status = '<div class="alert theme-warning mb-0 py-3 small">' . lang('The last remote backup copy failed.') . ' ' . h($backup_remote_error) . '</div>';
        } elseif ($backup_remote_sent_at > 0) {
            $backup_remote_status = '<div class="alert theme-success mb-0 py-3 small">' . lang(array('string' => 'The last remote copy was sent {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $backup_remote_sent_at, 'format' => 'plain_text'))))) . '</div>';
        } else {
            $backup_remote_status = '<div class="alert theme-secondary mb-0 py-3 small">' . lang('No remote copy has been sent yet.') . '</div>';
        }
    }

    $output_backup_settings = '
                    <div class="pg-f-xs">
                        <label for="backup_keep" class="form-label">' . lang('Automatic backups to keep') . '</label>
                        <input type="number" name="backup_keep" id="backup_keep" min="0" step="1" class="form-control" value="' . h($backup_keep) . '" />
                    </div>
                    <div class="col-12">
                        <div class="form-text">' . lang('The automatic backup keeps one backup per week; every run in the same week replaces it. Older weeks beyond this number are deleted. 0 keeps all of them. Backups taken by hand and the install dumps are never deleted.') . '</div>
                        <div class="form-text">' . lang('Database backups leave out the rows of visit records, page view counts, form item view counts, the firewall, performance and API request logs and the Search Console figures: those tables are backed up empty. The daily visitor summary and the hourly visitor chart are backed up in full; the Search Console figures of the last 90 days are downloaded again after a restore.') . '</div>
                    </div>' . (class_exists('ZipArchive') ? '' : '
                    <div class="col-12">
                        <div class="alert theme-warning mb-0 py-3 small">' . lang('The ZipArchive extension of PHP is not available: automatic backups stay folders and no remote copy is made.') . '</div>
                    </div>') . '
                    <div class="pg-f-md">
                        <label for="backup_remote_type" class="form-label">' . lang('Remote copy') . '</label>
                        <select name="backup_remote_type" id="backup_remote_type" class="form-control">
                            <option value=""' . (($backup_remote_type_shown === '') ? ' selected="selected"' : '') . '>' . lang('None') . '</option>
                            <option value="ftp"' . (($backup_remote_type_shown === 'ftp') ? ' selected="selected"' : '') . '>FTP</option>
                            <option value="s3"' . (($backup_remote_type_shown === 's3') ? ' selected="selected"' : '') . '>' . lang('S3-compatible storage') . '</option>
                            <option value="gdrive"' . (($backup_remote_type_shown === 'gdrive') ? ' selected="selected"' : '') . '>Google Drive</option>
                            <option value="onedrive"' . (($backup_remote_type_shown === 'onedrive') ? ' selected="selected"' : '') . '>OneDrive</option>
                            <option value="dropbox"' . (($backup_remote_type_shown === 'dropbox') ? ' selected="selected"' : '') . '>Dropbox</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-text">' . lang('After every automatic backup the archive is sent here as well. A backup kept in a folder is not sent. Choosing None forgets the saved connection details.') . '</div>
                    </div>
                    <div class="col-12" id="backup_remote_ftp_fields">
                        <div class="row gy-5">' . (extension_loaded('ftp') ? '' : '
                            <div class="col-12">
                                <div class="alert theme-warning mb-0 py-3 small">' . lang('The FTP extension of PHP is not available on this server.') . '</div>
                            </div>') . '
                            <div class="pg-f-md">
                                <label for="backup_ftp_host" class="form-label">' . lang('FTP server') . '</label>
                                <input type="text" name="backup_ftp_host" id="backup_ftp_host" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['host']) . '" placeholder="ftp.example.com" />
                            </div>
                            <div class="pg-f-xs">
                                <label for="backup_ftp_port" class="form-label">' . lang('Port') . '</label>
                                <input type="number" name="backup_ftp_port" id="backup_ftp_port" min="1" max="65535" class="form-control" value="' . h($backup_remote_ftp['port']) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_user" class="form-label">' . lang('Username') . '</label>
                                <input type="text" name="backup_ftp_user" id="backup_ftp_user" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['user']) . '" autocomplete="off" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_password" class="form-label">' . lang('Password') . '</label>
                                <input type="password" name="backup_ftp_password" id="backup_ftp_password" class="form-control" value="" autocomplete="new-password" placeholder="' . h($backup_secret_placeholder_ftp) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_path" class="form-label">' . lang('Folder on the server') . '</label>
                                <input type="text" name="backup_ftp_path" id="backup_ftp_path" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['path']) . '" placeholder="/backups" />
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . ($backup_remote_ftp['tls'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="backup_ftp_tls" name="backup_ftp_tls"/></div>
                                    <label class="" for="backup_ftp_tls">' . lang('Encrypted connection (explicit FTPS)') . '</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12" id="backup_remote_s3_fields">
                        <div class="row gy-5">
                            <div class="pg-f-lg">
                                <label for="backup_s3_endpoint" class="form-label">' . lang('Endpoint address') . '</label>
                                <input type="text" name="backup_s3_endpoint" id="backup_s3_endpoint" maxlength="255" class="form-control" value="' . h($backup_remote_s3['endpoint']) . '" placeholder="https://s3.eu-central-1.amazonaws.com" />
                                <div class="form-text">' . lang('Leave empty for Amazon S3 in the region below.') . '</div>
                            </div>
                            <div class="pg-f-sm">
                                <label for="backup_s3_region" class="form-label">' . lang('Region') . '</label>
                                <input type="text" name="backup_s3_region" id="backup_s3_region" maxlength="64" class="form-control" value="' . h($backup_remote_s3['region']) . '" placeholder="us-east-1" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_bucket" class="form-label">' . lang('Bucket name') . '</label>
                                <input type="text" name="backup_s3_bucket" id="backup_s3_bucket" maxlength="255" class="form-control" value="' . h($backup_remote_s3['bucket']) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_prefix" class="form-label">' . lang('Folder in the bucket') . '</label>
                                <input type="text" name="backup_s3_prefix" id="backup_s3_prefix" maxlength="255" class="form-control" value="' . h($backup_remote_s3['prefix']) . '" placeholder="backups" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_access_key" class="form-label">' . lang('Access Key') . '</label>
                                <input type="text" name="backup_s3_access_key" id="backup_s3_access_key" maxlength="255" class="form-control" value="' . h($backup_remote_s3['access_key']) . '" autocomplete="off" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_secret_key" class="form-label">' . lang('Secret Key') . '</label>
                                <input type="password" name="backup_s3_secret_key" id="backup_s3_secret_key" class="form-control" value="" autocomplete="new-password" placeholder="' . h($backup_secret_placeholder_s3) . '" />
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . ($backup_remote_s3['path_style'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="backup_s3_path_style" name="backup_s3_path_style"/></div>
                                    <label class="" for="backup_s3_path_style">' . lang('Path-style addresses') . '<br/><span class="form-text">' . lang('The bucket goes in the path instead of the host name. MinIO and most self-hosted stores need this; Amazon S3 does not.') . '</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
' . $backup_cloud_groups . '
                    <div class="col-12" id="backup_remote_shared">
                        <div class="row gy-5">
                            <div class="col-12" id="backup_remote_secret_help">
                                <div class="form-text">' . h($backup_secret_help) . '</div>
                            </div>' . (($backup_remote_status !== '') ? '
                            <div class="col-12">' . $backup_remote_status . '</div>' : '') . '
                            <div class="col-12">
                                <button type="submit" name="backup_remote_test" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . lang('Save and test the connection') . '</button>
                            </div>
                        </div>
                    </div>';

    // Shows the fields of the destination that is chosen. The others stay in
    // the form and are posted, so switching back does not lose them.
    $pg_settings_scripts[] = '
    <script>
        (function () {
            var type = document.getElementById("backup_remote_type");

            if (!type) {
                return;
            }

            function show(id, visible) {
                var element = document.getElementById(id);

                if (element) {
                    element.style.display = visible ? "" : "none";
                }
            }

            function sync() {
                ["ftp", "s3", "gdrive", "onedrive", "dropbox"].forEach(function (key) {
                    show("backup_remote_" + key + "_fields", type.value === key);
                });

                show("backup_remote_shared", type.value !== "");

                // A cloud destination keeps no password or secret here.
                show("backup_remote_secret_help", (type.value === "ftp") || (type.value === "s3"));
            }

            type.addEventListener("change", sync);
            sync();
        })();
    </script>';
}

$pg_settings_cards[] = '
    <div id="pgset-backup" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Backups') . '</span>
                <a href="backups.php" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . lang('Backup Manager') . '</a>
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_backup_settings . '
                </div>
            </div>
        </div>
    </div>';

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
                                <div class="form-range pg-range-bubble" data-pg-range="%">
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
                        <input type="datetime-local" name="maintenance_until" id="maintenance_until" class="form-control" value="' . h($maintenance_until_value) . '" />
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

// The modal explains every job the card can switch on. It is printed outside
// the form, so it travels in its own variable.
$pg_settings_modals[] = '
                <dialog class="dialog dialog-xl" id="cron_jobs" aria-labelledby="cron_jobs">
                            <div class="dialog-header">
                                <h5 class="dialog-title">' . lang('Cron Jobs') . ' (' . $output_os_family . ')</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="dialog" aria-label="Close"></button>
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
                                        <h5>' . lang('General Jobs') . '</h5>
                                        <p>' . lang('The general job is an optional feature which only needs to be enabled if you are using the scheduled comment feature to publish comments at a future date &amp; time.') . '</p>
                                        <textarea id="cron_job_general">' . $cron_job_general . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_general', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Webhook Delivery Jobs') . '</h5>
                                        <p>' . lang('The webhook delivery job sends the events your integrations asked to hear about: a new order, a cancelled order, a stock change. It usually needs no scheduled task of its own - the general job runs it on every turn as long as "Webhook delivery" is selected in the list further down this page, and on a site with no subscriptions that costs a single indexed read.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Schedule the command below only if you want an event delivered within a minute of it happening, without waiting for the general job. It is the same work either way; a dedicated task simply runs it more often.') . '</div>
                                        <textarea id="cron_job_webhook">' . $cron_job_webhook . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_webhook', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Device Notification Jobs') . '</h5>
                                        <p>' . lang('The device notification job wakes the phones and computers your operators have turned notifications on for: a new order, a submitted form, a new comment, an unanswered chat message. Like the webhook job it runs inside the general job on every turn as long as "Device notifications" is selected in the list further down this page, so it needs no scheduled task of its own.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: A chat message waits a minute before it is sent, so that a message somebody has already read on screen does not arrive on their phone as well. The delay an operator feels is that wait plus however often this job runs.') . '</div>
                                        <textarea id="cron_job_push">' . $cron_job_push . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_push', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every Minute') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Exchange Rates Jobs') . '</h5>
                                        <p>' . lang('The exchange rates job is an optional feature which only needs to be enabled if you are using the multi-currency e-commerce feature and want exchange rates for currencies to be updated automatically. Exchange rates can be manually updated via the Admin Panel.') . '</p>
                                        <textarea id="cron_job_exchange_rates">' . $cron_job_exchange_rates . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_exchange_rates', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Email Campaign Jobs') . '</h5>
                                        <p>' . lang(array('string' => 'The e-mail campaign job is an alternative to users manually sending e-mail campaigns from the Software. It is a script that can be scheduled to automatically send e-mail Campaigns. Also, the e-mail campaign job allows e-mail campaigns to be scheduled to be sent at a later time. If you are interested in using the e-mail campaign job, please complete {var:1}.', 'vars' => array('<a class="pg-link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/smtp_settings.php">' . lang('campaign smtp settings') . '</a>'))) . '</p>
                                        <div class="alert theme-danger">' . lang('WARNING: Once the e-mail campaign job is enabled, it will send e-mails for all campaigns where the status was "Ready to Send", so please make sure you update the status for old, incomplete, e-mail campaigns to be "Cancelled" before you enable the e-mail campaign job.') . '</div>
                                        <textarea id="cron_job_email_campaign">' . $cron_job_email_campaign . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_email_campaign', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Update Search Index Jobs') . '</h5>
                                        <p>' . lang('The update search index job is an alternative to clicking the Update Search Index button on the Pages tab. It does the spidering of your website and updates the search index with any new or changed content it finds. Since this is an intensive script that may slow down your site while it runs, you should not run it more than once an hour at the most. Less frequently is even better.') . '</p>
                                        <textarea id="cron_job_search_index">' . $cron_job_search_index . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_search_index', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('SEO Score Jobs') . '</h5>
                                        <p>' . lang('The SEO score job recalculates the SEO score of every page, product and product group. It only reads database columns, so it is quick, but it has to cover the whole site because the duplicate title and duplicate description checks depend on every other record. Schedule it before the SEO structure analysis job.') . '</p>
                                        <textarea id="cron_job_seo_score">' . $cron_job_seo_score . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_seo_score', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('SEO Structure Analysis Jobs') . '</h5>
                                        ' . $output_warnings_for_seo_analyze . '
                                        <p>' . lang('The SEO structure analysis job renders each page in the background and examines the resulting HTML: heading order, image alt text, internal links and orphan pages. This is the expensive half of the SEO score, so it works within a time budget and may need several runs to get through a large site for the first time.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Schedule this job to run after the SEO score job, because a catalog page is scored from the products it lists.') . '</div>
                                        <textarea id="cron_job_seo_analyze">' . $cron_job_seo_analyze . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_seo_analyze', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Recurring Payment Jobs') . '</h5>
                                        <p>' . lang('The recurring payment job is an optional feature which only needs to be enabled if you want actions to be performed when a recurring payment profile is disabled (i.e. suspended, cancelled, or expired) (e.g. credit card declined). This job requires the PayPal Website Payments Pro payment gateway. The following actions can be performed when the recurring payment profile is disabled. These options can be set in the properties for the product that creates the recurring payment profile.') . '<br>
                                        ' . lang('Expire membership.') . '<br>
                                        ' . lang('Revoke private access.') . '<br>
                                        ' . lang('Send an e-mail to the customer.') . '<br>
                                        ' . lang('For example, if you have a membership product which has a monthly recurring payment, you might want to expire a persons membership if his/her payment fails (e.g. credit card declined). Also, you might want to send an e-mail to the member with a link to order new membership.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: The Recurring Payment Job is NOT REQUIRED for setting up Recurring Products. That is handled through your payment gateway automatically once an Order is submitted for one or more recurring products through your Pinegrap website.') . '</div>
                                        <textarea id="cron_job_requrring_payment">' . $cron_job_requrring_payment . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_requrring_payment', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Membership Jobs') . '</h5>
                                        <p>' . lang('The membership job is an optional feature which only needs to be enabled if you want one or more of the features below. Send membership expiration warning e-mail to members whose membership is about to expire. You can enable and configure this feature via the Settings Page. Remove contacts from the Membership Contact Group when a contact\'s membership is no longer valid (e.g. membership has expired). This feature runs automatically once a scheduled task is setup for the membership job.') . '</p>
                                        <textarea id="cron_job_membership">' . $cron_job_membership . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_membership', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 ">
                                        <h5>' . lang('Auto Backup Jobs') . '</h5>
                                        ' . $output_warnings_for_auto_backup . '
                                        <p>' . lang('The auto backup job is an optional feature that must be enabled only if you want the automatic backup feature.') . '</p>
                                        <div class="alert theme-secondary">' . lang('NOTE: Auto Backup feature is available by default but can be disabled by a developer from the config.php file. This feature can only be operated once a day.') . '</div>
                                        <textarea id="cron_job_auto_backup">' . $cron_job_auto_backup . '</textarea>
                                        ' . pg_code_editor_attach(array('id' => 'cron_job_auto_backup', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a week') . '</div>
                                    </div>
                                </div>
                            </div>
                </dialog>';
