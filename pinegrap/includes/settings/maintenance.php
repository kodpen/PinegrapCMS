<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Updates and maintenance cards: which releases the site takes, what runs on a schedule, how the site is backed up, who is told when something stops working and the maintenance screen.
 *
 * Fills $pg_settings_cards with one entry: the cards as folded rows
 * (includes/settings/fold.php), each with its line of state from
 * includes/fn/maintenance_settings_fold.php. Which cards belong here is said
 * once, in includes/settings/registry.php; this file holds them, and
 * <key>.save.php writes what they edit. The variables they read are prepared
 * by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_settings_fold.php');

// Each part: its card's markup and its row's line, pill and attention.
$maintenance_parts = array();
$maintenance_now = time();

// ── Update Channel ──
$maintenance_parts['channel'] = array('html' => '
    <div id="pgset-channel" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
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
                    </div>' . ((defined('PG_VENDOR_API_V1') && (PG_VENDOR_API_V1 === true)) ? '
                    <div class="col-12">
                        <div class="form-text">' . lang('License and update checks send Kodpen this site\'s Hostname, the server\'s IP address and the versions of the software, PHP, the database and the web server.') . '</div>
                    </div>' : '') . ($update_backup_ready ? '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($update_backup ? ' checked="checked"' : '') . ' class="" type="checkbox" id="update_backup" name="update_backup"/></div>
                            <label class="" for="update_backup">' . lang('Back up before updating') . '</label><div class="form-text">' . lang('Before an update or a repair replaces the software files, the files it replaces and the database are written to data/backups/pre-update-….zip; the three newest are kept. Repair Software rolls the files back from it, and the installation screen restores its database. Switch it off only for a site too large to back up within one request.') . '</div>
                        </div>
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_channel($software_update_channel, $update_backup_ready, $update_backup);

// ── Scheduled jobs ──
//
// The general job's command on one line, then one row a job of the catalogue
// (includes/fn/maintenance_cron_list.php). Each row's "How to schedule it"
// opens that job's numbered steps; the drawers are printed outside the form,
// so they travel in $pg_settings_modals.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/maintenance_cron_list.php');

$maintenance_cron_runs = pg_cron_last_runs();
$maintenance_cron_jobs = pg_cron_jobs();
$maintenance_cron_inactive = array();
$maintenance_cron_warnings = array();

if (!extension_loaded('pdo_mysql')) {
    $maintenance_cron_warnings['auto_backup'] = lang('pdo_mysql.dll is not enabled. Please enable it for Auto Backup feature.');
}

// The structure pass parses rendered markup with DOMDocument; without it the
// job records its run and analyzes nothing.
if (!class_exists('DOMDocument')) {
    $maintenance_cron_warnings['seo_analyze_job'] = lang('The PHP DOM extension is not enabled. Please enable it for the SEO structure analysis job.');
}

foreach ($maintenance_cron_jobs as $maintenance_cron_name => $maintenance_cron_job) {
    if (!empty($maintenance_cron_job['dispatch']) && !pg_cron_job_active($maintenance_cron_name)) {
        $maintenance_cron_inactive[] = $maintenance_cron_name;
    }
}

$maintenance_cron_general = pg_maintenance_cron_command($output_os_family, PG_FUNCTIONS_DIR, $maintenance_cron_jobs['job']['script']);

$maintenance_parts['cron'] = array('html' => '
                        <div id="pgset-cron" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 pg-title-section">' . lang('Scheduled jobs') . '</div>
                                <div class="card-body">
                                    <h4 class="pg-group-label">' . lang('General job') . '</h4>
                                    <p class="d-flex align-items-center gap-2 mb-1"><code class="text-break" id="cron_job_general_command">' . h($maintenance_cron_general) . '</code><span class="pg-copy">' . pg_copy_button($maintenance_cron_general, lang('Copy the command'), 'cron_job_general_command') . '</span></p>
                                    <p class="pg-text-meta mb-5">' . lang('Every minute') . ' &middot; <span lang="en">' . h($output_os_family) . '</span> &middot; ' . lang('Every site needs this task: it keeps the alerts and the health address working.') . '</p>
                                    <div class="form-field">
                                        <div class="switch"><input value="1"' . $job_dispatch_enabled_checked . ' type="checkbox" id="job_dispatch_enabled" name="job_dispatch_enabled"/></div>
                                        <label for="job_dispatch_enabled">' . lang('Run the jobs with the general job') . '</label><div class="form-text">' . lang('A job switched on below runs with the general job, one at a time, and needs no task of its own.') . '</div>
                                    </div>
                                    <h4 class="pg-group-label mt-5">' . lang('Jobs') . '</h4>
                                    ' . pg_maintenance_cron_list_html(pg_maintenance_cron_rows($maintenance_cron_jobs, $job_dispatch_selection, $maintenance_cron_inactive), ($job_dispatch_enabled == 1)) . '
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

                                // A job\'s switch shows only while the master is on. It is
                                // hidden, never disabled: the selection stays posted, so
                                // switching the master off and saving keeps what was chosen.
                                function sync() {
                                    list.querySelectorAll("[data-pg-cron-switch]").forEach(function (box) {
                                        box.classList.toggle("d-none", !master.checked);
                                    });
                                }

                                master.addEventListener("change", sync);
                                sync();
                            })();
                        </script>') + pg_maintenance_fold_cron(
    (is_array($maintenance_cron_runs) && !empty($maintenance_cron_runs['job'])) ? (int) $maintenance_cron_runs['job'] : 0,
    $maintenance_now,
    (int) $maintenance_cron_jobs['job']['stale_after'],
    ($job_dispatch_enabled == 1),
    pg_maintenance_fold_cron_count($job_dispatch_selection, $maintenance_cron_jobs)
);

foreach ($maintenance_cron_jobs as $maintenance_cron_name => $maintenance_cron_job) {
    if (!empty($maintenance_cron_job['dispatch'])) {
        $pg_settings_modals[] = pg_maintenance_cron_guide_drawer(
            $maintenance_cron_name,
            $maintenance_cron_job,
            $output_os_family,
            pg_maintenance_cron_command($output_os_family, PG_FUNCTIONS_DIR, $maintenance_cron_job['script']),
            !empty($pg_settings_in_dialog),
            $maintenance_cron_warnings[$maintenance_cron_name] ?? ''
        );
    }
}

// ── Backups ──
//
// What the automatic backup keeps and where its copy goes. The controls exist
// only once the 2026.4.8 columns do; before that the card says so, and the
// save module writes nothing for it either.
$output_backup_settings = '<div class="col-12"><div class="alert theme-secondary mb-0 py-3 small">' . lang('Run the upgrade to use this setting.') . '</div></div>';

if ($backup_settings_ready) {

    $backup_secret_placeholder_ftp = $backup_ftp_password_stored ? lang('Saved') : '';
    $backup_secret_placeholder_s3  = $backup_s3_secret_stored ? lang('Saved') : '';
    $backup_secret_help = ($backup_ftp_password_stored || $backup_s3_secret_stored)
        ? lang('The secret and the password are stored and are not shown. Leave these boxes empty to keep them.')
        : lang('The secret and the password are stored encrypted and are never shown again after saving.');

    // Google Drive, OneDrive and Dropbox go through an account connected for
    // backups, connected on the service's page of Connections. A connection
    // started there from this card comes back with the destination it was
    // started for, which is shown chosen (not yet saved) together with the
    // new account.
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
                                    'string' => 'The {var:1} application is not set up yet. Enter it on its page of Connections first.',
                                    'vars'   => $backup_cloud_provider['label'],
                                ))) . ' <a href="' . h(pg_backup_cloud_app_url($backup_cloud_type)) . '">' . h(lang(array('string' => 'Set up the {var:1} application', 'vars' => $backup_cloud_provider['label']))) . '</a></div>
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
                            </div>
                            <div class="col-12">
                                <a class="pg-button pg-button-quiet pg-button-sm" href="' . h(pg_backup_cloud_connections_url($backup_cloud_type)) . '">
                                    <i class="bi ' . h($backup_cloud_provider['icon']) . '" aria-hidden="true"></i>' . h($backup_cloud_accounts ? lang('Manage in Connections') : lang('Connect in Connections')) . '</a>
                            </div>
                        </div>
                    </div>';
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
                        <input type="number" name="backup_keep" id="backup_keep" min="0" max="9999" step="1" class="form-control" value="' . h($backup_keep) . '" />
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
                                <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="backup_ftp_host" id="backup_ftp_host" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['host']) . '" placeholder="ftp.example.com" />
                            </div>
                            <div class="pg-f-xs">
                                <label for="backup_ftp_port" class="form-label">' . lang('Port') . '</label>
                                <input type="number" name="backup_ftp_port" id="backup_ftp_port" min="1" max="65535" class="form-control" value="' . h($backup_remote_ftp['port']) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_user" class="form-label">' . lang('Username') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_ftp_user" id="backup_ftp_user" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['user']) . '" autocomplete="off" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_password" class="form-label">' . lang('Password') . '</label>
                                <input type="password" name="backup_ftp_password" id="backup_ftp_password" class="form-control" value="" autocomplete="new-password" placeholder="' . h($backup_secret_placeholder_ftp) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_ftp_path" class="form-label">' . lang('Folder on the server') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_ftp_path" id="backup_ftp_path" maxlength="255" class="form-control" value="' . h($backup_remote_ftp['path']) . '" placeholder="/backups" />
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
                                <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="backup_s3_endpoint" id="backup_s3_endpoint" maxlength="255" class="form-control" value="' . h($backup_remote_s3['endpoint']) . '" placeholder="https://s3.eu-central-1.amazonaws.com" />
                                <div class="form-text">' . lang('Leave empty for Amazon S3 in the region below.') . '</div>
                            </div>
                            <div class="pg-f-sm">
                                <label for="backup_s3_region" class="form-label">' . lang('Region') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_s3_region" id="backup_s3_region" maxlength="64" class="form-control" value="' . h($backup_remote_s3['region']) . '" placeholder="us-east-1" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_bucket" class="form-label">' . lang('Bucket name') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_s3_bucket" id="backup_s3_bucket" maxlength="255" class="form-control" value="' . h($backup_remote_s3['bucket']) . '" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_prefix" class="form-label">' . lang('Folder in the bucket') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_s3_prefix" id="backup_s3_prefix" maxlength="255" class="form-control" value="' . h($backup_remote_s3['prefix']) . '" placeholder="backups" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_access_key" class="form-label">' . lang('Access Key') . '</label>
                                <input type="text" spellcheck="false" autocapitalize="off" name="backup_s3_access_key" id="backup_s3_access_key" maxlength="255" class="form-control" value="' . h($backup_remote_s3['access_key']) . '" autocomplete="off" />
                            </div>
                            <div class="pg-f-md">
                                <label for="backup_s3_secret_key" class="form-label">' . lang('Secret Key') . '</label>
                                <input type="password" name="backup_s3_secret_key" id="backup_s3_secret_key" class="form-control" value="" autocomplete="new-password" placeholder="' . h($backup_secret_placeholder_s3) . '" />
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . ($backup_remote_s3['path_style'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="backup_s3_path_style" name="backup_s3_path_style"/></div>
                                    <label class="" for="backup_s3_path_style">' . lang('Path-style addresses') . '</label><div class="form-text">' . lang('The bucket goes in the path instead of the host name. MinIO and most self-hosted stores need this; Amazon S3 does not.') . '</div>
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
                                <button type="submit" name="backup_remote_test" value="1" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plug" aria-hidden="true"></i>' . lang('Save and test the connection') . '</button>
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

$maintenance_backup_newest = function_exists('pg_backup_newest') ? pg_backup_newest() : array('newest' => 0);

$maintenance_parts['backup'] = array('html' => '
    <div id="pgset-backup" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                <span class="pg-title-section">' . lang('Backups') . '</span>
                <a href="backups.php" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-archive" aria-hidden="true"></i>' . lang('Backup Manager') . '</a>
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_backup_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_backup($backup_remote_type, $backup_remote_error, (int) $maintenance_backup_newest['newest'], $maintenance_now);

// ── Storage ──
//
// The S3-compatible object store (includes/fn/object_storage.php): any
// service that speaks the S3 API, named by its endpoint. Kept in one sealed
// connect_apps entry, so the save module writes it only when this card was
// drawn (object_storage_card). The secret is drawn empty; an empty box keeps
// it. "Save and test" saves first, then writes, reads and deletes one small
// file.
//
// The row's facts: a store in use, its bucket, the move under way and how
// many files it still has to carry, the last test.
$maintenance_storage = array('used' => false, 'bucket' => '', 'direction' => '', 'paused' => false, 'left' => 0, 'test_ok' => null);

if (!$object_storage_ready) {

    $output_object_storage_settings = '
                    <div class="col-12"><div class="alert theme-secondary mb-0 py-3 small">' . lang('The storage keeps its secret key encrypted, which needs the site\'s encryption key and the connected accounts tables. Run the upgrade, and check that the encryption key is set.') . '</div></div>';

} else {

    // The last "Save and test" result, kept until the next test.
    $object_storage_last_test = function_exists('pg_os_health_test_last') ? pg_os_health_test_last() : null;
    $output_object_storage_last_test = '';

    if ($object_storage_last_test) {
        $maintenance_storage['test_ok'] = (bool) $object_storage_last_test['ok'];
        $object_storage_last_when = get_relative_time(array('timestamp' => $object_storage_last_test['at'], 'format' => 'plain_text'));
        $output_object_storage_last_test = '
                        <div class="form-text ' . ($object_storage_last_test['ok'] ? 'fg-success' : 'fg-danger') . '">' . ($object_storage_last_test['ok']
            ? h(lang(array('string' => 'Last test {var:1}: passed.', 'vars' => array($object_storage_last_when))))
            : h(lang(array('string' => 'Last test {var:1}: failed. {var:2}', 'vars' => array($object_storage_last_when, $object_storage_last_test['message']))))) . '</div>';
    }

    // Uploaded files (includes/fn/file_storage.php): where they are now, the
    // walk the general job is on and its failures, and the buttons that start,
    // pause, resume or turn it round. Only with the storage columns and a
    // saved store that is switched on: before that nothing could move.
    $output_object_storage_files = '';

    if (function_exists('pg_file_storage_ready') && pg_file_storage_ready() && pg_os_ready($object_storage)) {

        $object_storage_switch = pg_config_text('object_storage_files');
        $object_storage_mode = pg_file_storage_mode($object_storage_switch);
        $object_storage_usage = pg_file_storage_usage();

        $maintenance_storage = array(
            'used'      => true,
            'bucket'    => (string) $object_storage['bucket'],
            'direction' => $object_storage_mode['direction'],
            'paused'    => $object_storage_mode['paused'],
            'left'      => ($object_storage_mode['direction'] === 'back') ? (int) $object_storage_usage['stored_files'] : (int) $object_storage_usage['local_files'],
            'test_ok'   => $maintenance_storage['test_ok'],
        );

        $object_storage_status = lang('Files stay on the disk.');

        if ($object_storage_mode['direction'] === 'to') {
            $object_storage_status = $object_storage_mode['paused'] ? lang('Moving files to the storage is paused.') : lang('Files are being moved to the storage a few at a time; new uploads follow.');
        } elseif ($object_storage_mode['direction'] === 'back') {
            $object_storage_status = $object_storage_mode['paused'] ? lang('Bringing files back is paused.') : lang('Files are being brought back to the disk a few at a time; new uploads stay on the disk.');
        }

        $object_storage_failed = '';

        if ($object_storage_mode['direction'] !== '') {
            $object_storage_failures = pg_os_health_move_failures(
                pg_file_move_state_normalize(json_decode((string) pg_config_text(pg_file_move_state_key($object_storage_mode['direction'])), true)),
                pg_file_move_attempts()
            );

            if ($object_storage_failures['failing'] + $object_storage_failures['given_up'] > 0) {
                $object_storage_failed = '
                        <div class="form-text fg-warning">' . h(lang(array(
                    'string' => 'Failed: {var:1} being retried, {var:2} left where they are after three attempts (named in the Site Log).',
                    'vars'   => array(pg_format_number($object_storage_failures['failing'], 0), pg_format_number($object_storage_failures['given_up'], 0)),
                ))) . '</div>';
            }
        }

        $object_storage_buttons = array(
            'move'   => array('bi-cloud-arrow-up', lang('Move existing files')),
            'back'   => array('bi-cloud-arrow-down', lang('Bring back')),
            'pause'  => array('bi-pause-fill', lang('Pause')),
            'resume' => array('bi-play-fill', lang('Resume')),
        );
        $output_object_storage_buttons = '';

        foreach (pg_file_storage_actions($object_storage_switch) as $object_storage_action) {
            $output_object_storage_buttons .= '
                        <button type="submit" name="object_storage_files_action" value="' . $object_storage_action . '" class="pg-button pg-button-quiet pg-button-sm me-2"><i class="bi ' . $object_storage_buttons[$object_storage_action][0] . '" aria-hidden="true"></i>' . h($object_storage_buttons[$object_storage_action][1]) . '</button>';
        }

        $output_object_storage_files = '
                    <div class="col-12">
                        <div class="form-label">' . lang('Uploaded files') . '</div>
                        <div>' . h(lang(array(
            'string' => 'On the disk: {var:1} files ({var:2}). In the storage: {var:3} files ({var:4}).',
            'vars'   => array(
                pg_format_number($object_storage_usage['local_files'], 0),
                convert_bytes_to_string($object_storage_usage['local_bytes'], 1),
                pg_format_number($object_storage_usage['stored_files'], 0),
                convert_bytes_to_string($object_storage_usage['stored_bytes'], 1),
            ),
        ))) . '</div>
                        <div class="form-text">' . h($object_storage_status) . ' ' . lang('Design and theme files, style sheets and scripts always stay on the disk.') . '</div>' . $object_storage_failed . '
                        <div class="mt-3">' . $output_object_storage_buttons . '
                        </div>
                    </div>';
    }

    $output_object_storage_settings = '
                    <div class="col-12">
                        <input type="hidden" name="object_storage_card" value="1"/>
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($object_storage['enabled'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="object_storage_enabled" name="object_storage_enabled"/></div>
                            <label class="" for="object_storage_enabled">' . lang('Use object storage') . '</label><div class="form-text">' . lang('A bucket on any service with an S3-compatible API, hosted or on your own server. Switched off, nothing is sent there.') . '</div>
                        </div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="object_storage_endpoint" class="form-label">' . lang('Endpoint address') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="object_storage_endpoint" id="object_storage_endpoint" maxlength="255" class="form-control" value="' . h($object_storage['endpoint']) . '" placeholder="https://s3.example.com" />
                        <div class="form-text">' . lang('The service\'s S3 API address, as its documentation gives it. Plain http only for a store on the same server or network.') . '</div>
                    </div>
                    <div class="pg-f-sm">
                        <label for="object_storage_region" class="form-label">' . lang('Region') . '</label>
                        <input type="text" spellcheck="false" autocapitalize="off" name="object_storage_region" id="object_storage_region" maxlength="32" class="form-control" value="' . h($object_storage['region']) . '" placeholder="us-east-1" />
                    </div>
                    <div class="pg-f-md">
                        <label for="object_storage_bucket" class="form-label">' . lang('Bucket name') . '</label>
                        <input type="text" spellcheck="false" autocapitalize="off" name="object_storage_bucket" id="object_storage_bucket" maxlength="63" class="form-control" value="' . h($object_storage['bucket']) . '" />
                    </div>
                    <div class="pg-f-md">
                        <label for="object_storage_prefix" class="form-label">' . lang('Folder in the bucket') . '</label>
                        <input type="text" spellcheck="false" autocapitalize="off" name="object_storage_prefix" id="object_storage_prefix" maxlength="200" class="form-control" value="' . h($object_storage['prefix']) . '" placeholder="pinegrap" />
                    </div>
                    <div class="pg-f-md">
                        <label for="object_storage_key_id" class="form-label">' . lang('Access Key') . '</label>
                        <input type="text" spellcheck="false" autocapitalize="off" name="object_storage_key_id" id="object_storage_key_id" maxlength="255" class="form-control" value="' . h($object_storage['key_id']) . '" autocomplete="off" />
                    </div>
                    <div class="pg-f-md">
                        <label for="object_storage_secret" class="form-label">' . lang('Secret Key') . '</label>
                        <input type="password" name="object_storage_secret" id="object_storage_secret" class="form-control" value="" autocomplete="new-password" placeholder="' . h($object_storage['secret_stored'] ? lang('Saved') : '') . '" />
                        <div class="form-text">' . lang('Stored encrypted and never shown again. Leave empty to keep the saved key.') . '</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($object_storage['path_style'] ? ' checked="checked"' : '') . ' class="" type="checkbox" id="object_storage_path_style" name="object_storage_path_style"/></div>
                            <label class="" for="object_storage_path_style">' . lang('Path-style addresses') . '</label><div class="form-text">' . lang('The bucket goes in the path instead of the host name. MinIO and most self-hosted stores need this; Amazon S3 does not.') . '</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="object_storage_test" value="1" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plug" aria-hidden="true"></i>' . lang('Save and test the connection') . '</button>
                        <div class="form-text">' . lang('Writes a small file to the bucket, reads it back and deletes it.') . '</div>' . $output_object_storage_last_test . '
                    </div>' . $output_object_storage_files;
}

$maintenance_parts['storage'] = array('html' => '
    <div id="pgset-storage" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Storage') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_object_storage_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_storage(
    $maintenance_storage['used'],
    $maintenance_storage['bucket'],
    $maintenance_storage['direction'],
    $maintenance_storage['paused'],
    $maintenance_storage['left'],
    $maintenance_storage['test_ok']
);

// ── Alerts ──
//
// Who is told when a backup fails, a scheduled task stops or the software
// files change, and by which channel (includes/fn/ops_alerts.php). The bell is
// not a choice: an alert is a notification row, and the row is what makes it
// once a day. Before the 2026.11.0 columns the card says so, and the save
// module writes nothing for it either.
$output_ops_alert_settings = '<div class="col-12"><div class="alert theme-secondary mb-0 py-3 small">' . lang('Run the upgrade to use this setting.') . '</div></div>';

if ($ops_alerts_ready) {

    $output_ops_alert_settings = '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($ops_alerts ? ' checked="checked"' : '') . ' class="" type="checkbox" id="ops_alerts" name="ops_alerts"/></div>
                            <label class="" for="ops_alerts">' . lang('Operations alerts') . '</label><div class="form-text">' . lang('Tells the people who run the site when something stops working: an automatic backup fails or goes stale, a scheduled task stops running, the software files no longer match the release, PHP errors pile up, the disk runs full, e-mails or event notifications stop going out, or the site\'s certificate is about to run out. Each problem is told at most once a day while it lasts, on the panel bell of managers and administrators and by the channels below. System Status lists the alerts of the last seven days.') . '</div>
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
                                    <label class="" for="ops_alert_push">' . lang('Send alerts to subscribed devices') . '</label><div class="form-text">' . lang('Phones and computers on which a manager or administrator turned notifications on. Delivered by the Device notifications job, which has to be selected under Cron Jobs.') . '</div>
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

            // The Sales watch card stops with this switch: its fields hide
            // and its note leads back here.
            var sales = document.getElementById("sales_watch_fields");
            var salesOff = document.getElementById("sales_watch_off");

            function sync() {
                fields.style.display = master.checked ? "" : "none";

                if (sales) {
                    sales.style.display = master.checked ? "" : "none";
                }

                if (salesOff) {
                    salesOff.style.display = master.checked ? "none" : "";
                }
            }

            master.addEventListener("change", sync);
            sync();
        })();
    </script>';
}

$maintenance_parts['alerts'] = array('html' => '
    <div id="pgset-alerts" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Alerts') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_ops_alert_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_alerts($ops_alerts_ready, (bool) $ops_alerts, (bool) $ops_alert_email, $ops_alert_recipients, (bool) $ops_alert_push);

// ── Sales watch ──
//
// A card of its own that still stops with Operations alerts: its fields
// hide with that switch and a note leads to its row. The shop's own alerts
// (includes/fn/sales_watch.php), one config_text row. The save module writes
// it only when this card was drawn (sales_watch_card).
if ($ops_alerts_ready && $sales_watch_ready) {

    $sales_watch_codes = pg_ops_alert_codes();
    $sales_watch_alerts = '';

    foreach (pg_sales_watch_alert_names() as $sales_watch_name) {

        // An alert is offered once its code is in the catalogue.
        if (!isset($sales_watch_codes[$sales_watch_name])) {
            continue;
        }

        $sales_watch_alerts .= '
                                    <div class="col-12 md:col-6">
                                        <div class="form-field">
                                            <input value="' . h($sales_watch_name) . '"' . (!empty($sales_watch[$sales_watch_name]) ? ' checked="checked"' : '') . ' class="check" type="checkbox" id="sales_watch_alert_' . h($sales_watch_name) . '" name="sales_watch_alerts[]"/>
                                            <label for="sales_watch_alert_' . h($sales_watch_name) . '">' . h($sales_watch_codes[$sales_watch_name]['label']) . '</label>
                                        </div>
                                    </div>';
    }

    $sales_watch_level_options = '';

    foreach (array('low' => lang('Low (0.1%)'), 'normal' => lang('Normal (1%)'), 'high' => lang('High (5%)')) as $sales_watch_level => $sales_watch_level_label) {
        $sales_watch_level_options .= '<option value="' . h($sales_watch_level) . '"' . (($sales_watch['level'] === $sales_watch_level) ? ' selected="selected"' : '') . '>' . h($sales_watch_level_label) . '</option>';
    }

    $sales_watch_pauses = '';
    $sales_watch_now = time();

    foreach (array_merge(array('store' => lang('The whole store')), $sales_watch_channels) as $sales_watch_key => $sales_watch_label) {

        // A pause ends at the midnight after its last day; the box shows that day.
        $sales_watch_until = isset($sales_watch['paused'][$sales_watch_key]) && ($sales_watch['paused'][$sales_watch_key] > $sales_watch_now)
            ? date('Y-m-d', $sales_watch['paused'][$sales_watch_key] - 1)
            : '';

        $sales_watch_pauses .= '
                                    <div class="pg-f-sm">
                                        <label for="sales_watch_pause_' . h($sales_watch_key) . '" class="form-label">' . h($sales_watch_label) . '</label>
                                        <input type="date" data-pg-date name="sales_watch_pause[' . h($sales_watch_key) . ']" id="sales_watch_pause_' . h($sales_watch_key) . '" class="form-control" value="' . h($sales_watch_until) . '" />
                                    </div>';
    }

    $output_sales_watch_settings = '
                    <div class="col-12" id="sales_watch_off">
                        <div class="form-text">' . lang('Off with Operations alerts: the watch tells nothing while they are switched off.') . '</div>
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm mt-3" data-pg-set-goto="pgset-alerts"><i class="bi bi-bell" aria-hidden="true"></i>' . lang('Open Operations alerts') . '</button>
                    </div>
                    <div class="col-12" id="sales_watch_fields">
                        <input type="hidden" name="sales_watch_card" value="1"/>
                        <div class="row gy-5">
                            <div class="col-12">
                                <div class="form-text">' . lang('The store learns how many orders each channel - the site and every marketplace account - usually brings in each hour of the week, and says when the shop stops working as usual: orders stop coming in, or carts reach payment but are not completed. Counter sales are left out. A channel with fewer than 20 orders in eight weeks is not watched yet.') . '</div>
                            </div>
                            <div class="col-12">
                                <div class="row gy-5">' . $sales_watch_alerts . '
                                </div>
                            </div>
                            <div class="pg-f-sm">
                                <label for="sales_watch_level" class="form-label">' . lang('Sensitivity') . '</label>
                                <select name="sales_watch_level" id="sales_watch_level" class="form-control">' . $sales_watch_level_options . '</select>
                            </div>
                            <div class="col-12">
                                <div class="form-text">' . lang('How unlikely a quiet spell has to be before it is told. High tells sooner and is wrong more often; low tells only what is almost certainly a problem.') . '</div>
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input value="1"' . (!empty($sales_watch['close_products']) ? ' checked="checked"' : '') . ' class="" type="checkbox" id="sales_watch_close_products" name="sales_watch_close_products"/></div>
                                    <label class="" for="sales_watch_close_products">' . lang('Take a product off sale on a price error') . '</label><div class="form-text">' . lang('A product selling fast at far below its usual price is switched off until someone switches it on again. Stock is never changed. At most 5 products a check and 20 a day; when more look wrong at once, none is switched off and the alert asks whether a campaign is running.') . '</div>
                                </div>
                            </div>
                            <div class="pg-f-xs">
                                <label for="sales_watch_price_drop" class="form-label">' . lang('Price error below %') . '</label>
                                <input type="number" name="sales_watch_price_drop" id="sales_watch_price_drop" min="10" max="90" step="1" class="form-control" value="' . (int) round($sales_watch['price_drop'] * 100) . '" />
                            </div>
                            <div class="col-12">
                                <div class="form-text">' . lang('A price under this share of the product\'s usual price of the last 30 days counts as a possible error.') . '</div>
                            </div>
                            <div class="col-12">
                                <div class="form-label mb-0">' . lang('Pause the watch until') . '</div>
                                <div class="form-text">' . lang('For a holiday or a planned shutdown: nothing is told for the store or the channel through the end of the chosen day. Leave empty to watch.') . '</div>
                            </div>' . $sales_watch_pauses . '
                            <div class="d-none">' . get_date_picker_format() . pg_fields_script() . '</div>
                        </div>
                    </div>';

    $maintenance_parts['sales-watch'] = array('html' => '
    <div id="pgset-sales-watch" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Sales watch') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_sales_watch_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_sales((bool) $ops_alerts, $sales_watch, $maintenance_now);
}

// ── Chat notifications ──
//
// No target here: Slack's, Teams' and Telegram's targets are set on each
// service's page of Connections (includes/fn/notify_channels_page.php); the
// card says how many each has and leads there.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/notify_channels_page.php');

$output_notify_settings = pg_notify_settings_card_html(pg_notify_target_counts($notify_targets));

$maintenance_parts['chat-notify'] = array('html' => '
    <div id="pgset-chat-notify" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Chat notifications') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_notify_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_chat($notify_targets, pg_notify_channel_kinds());

// ── Maintenance Mode ──
//
// What a visitor sees while the site is being worked on
// (includes/fn/maintenance_mode.php), and the address an uptime monitor
// polls. Before the 2026.11.0 columns the card says so and the save module
// writes nothing for it.
$output_maintenance_settings = '<div class="col-12"><div class="alert theme-secondary mb-0 py-3 small">' . lang('Run the upgrade to use this setting.') . '</div></div>';

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
                            <label class="" for="maintenance_mode">' . lang('Maintenance Mode') . '</label><div class="form-text">' . lang('Visitors who open a page of the site see the maintenance screen - the designed error page, or the classic one - with the 503 answer that tells search engines to come back later. Signed-in managers and administrators, and the addresses below, see the site as usual. Files, the control panel and payment callbacks keep working.') . '</div>
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

$maintenance_parts['maintenance'] = array('html' => '
    <div id="pgset-maintenance" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Maintenance Mode') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $output_maintenance_settings . '
                </div>
            </div>
        </div>
    </div>') + pg_maintenance_fold_mode($maintenance_ready, (bool) $maintenance_mode, $maintenance_until, $maintenance_now);

// ── The rows ──
$maintenance_rows = array();

foreach (pg_maintenance_fold_order() as $maintenance_key) {
    if (isset($maintenance_parts[$maintenance_key])) {
        $maintenance_rows[] = array('key' => $maintenance_key) + pg_maintenance_fold_face($maintenance_key) + $maintenance_parts[$maintenance_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($maintenance_rows);
