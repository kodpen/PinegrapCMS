<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the General cards: who runs the site, where it is, what the software is, the time it keeps and how it is backed up.
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

// ── Organization ──
// Who runs the site, in one place: the name and address the campaigns, the
// invoices and the legal texts print, the logo of the structured data, the
// address system mail comes from and the seller's tax number. Each field
// keeps its column; general.save.php writes them all.
$pg_settings_cards[] = '
    <div id="pgset-organization" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . lang('Organization') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-md">
                        <label for="organization_name" class="form-label">' . lang('Organization Name') . '</label>
                        <input type="text" name="organization_name" id="organization_name" class="form-control" value="' . h($organization_name) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_address_1" class="form-label">' . lang('Organization Address') . ' 1</label>
                        <input type="text" name="organization_address_1" id="organization_address_1" class="form-control" value="' . h($organization_address_1) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_address_2" class="form-label">' . lang('Organization Address') . ' 2</label>
                        <input type="text" name="organization_address_2" id="organization_address_2" class="form-control" value="' . h($organization_address_2) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_city" class="form-label">' . lang('Organization City') . '</label>
                        <input type="text" name="organization_city" id="organization_city" class="form-control" value="' . h($organization_city) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_state" class="form-label">' . lang('Organization State') . '</label>
                        <input type="text" name="organization_state" id="organization_state" class="form-control" value="' . h($organization_state) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_zip_code" class="form-label">' . lang('Organization Zip Code') . '</label>
                        <input type="text" name="organization_zip_code" id="organization_zip_code" class="form-control" value="' . h($organization_zip_code) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="organization_country" class="form-label">' . lang('Organization Country') . '</label>
                        <input type="text" name="organization_country" id="organization_country" class="form-control" value="' . h($organization_country) . '"/>
                    </div>
                    <div class="pg-f-md">
                        <label for="setting_email_address" class="form-label">' . lang('Support E-mail Address') . '</label>
                        <input type="text" name="email_address" id="setting_email_address" autocomplete="off" class="form-control" value="' . h($email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                    </div>' . $output_organization_logo_picker . '
                    <div class="pg-f-md">
                        ' . ((pg_erp_store_country() === 'TR')
                            ? '<label class="form-label" for="erp_seller_vkn">' . lang('Seller VKN / TCKN') . '</label>
                        <input type="text" class="form-control" id="erp_seller_vkn" name="erp_seller_vkn" value="' . h($erp_seller_vkn) . '" maxlength="11" inputmode="numeric" autocomplete="off" />
                        <div class="form-text">' . lang('Tax number (10 digits) or ID number (11 digits) of the company that issues the invoices') . '</div>'
                            : '<label class="form-label" for="erp_seller_vkn">' . lang('Seller tax number') . '</label>
                        <input type="text" class="form-control" id="erp_seller_vkn" name="erp_seller_vkn" value="' . h($erp_seller_vkn) . '" maxlength="32" autocomplete="off" />
                        <div class="form-text">' . lang('The tax number of the company that issues the invoices (VAT id, EIN, ...), as it is printed on them.') . '</div>') . '
                    </div>
                    <div class="pg-f-md' . ((pg_erp_store_country() === 'TR') ? '' : ' d-none') . '">
                        <label class="form-label" for="erp_seller_tax_office">' . lang('Seller Tax Office') . '</label>
                        <input type="text" class="form-control" id="erp_seller_tax_office" name="erp_seller_tax_office" value="' . h($erp_seller_tax_office) . '" maxlength="100" autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>
    </div>';

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
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="hostname" id="hostname" maxlength="255" class="form-control" value="' . h($hostname) . '" />
                    </div>
                    <div class="pg-f-lg">
                        <label for="proxy_address" class="form-label">' . lang('Proxy Address') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="proxy_address" id="proxy_address" maxlength="255" class="form-control" value="' . h($proxy_address) . '" />
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
                                            <div class="form-label">' . lang('Current Site Time') . '</div>
                                            <div class="w-100">' . get_absolute_time(array('timestamp' => time(), 'timezone_type' => 'site')) . '</div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="timezone" class="form-label">' . lang('Timezone') . '</label>
                                            <select class="form-control" id="timezone" name="timezone">' . $output_timezone_options . '</select>
                                        </div>
                                        <div class="col-12 ">
                                            <fieldset><legend class="form-label fs-md">'. lang('Date Format') . '</legend>
                                            <div class="form-field">
                                                <input value="month_day" class="radio" type="radio" id="date_format_month_day" name="date_format" ' . $date_format_month_day_checked . '>
                                                <label class="" for="date_format_month_day">'. lang('month') . '/'. lang('day') . '/'. lang('year') . ' (2/14/' . date('Y') . ')</label>
                                            </div>
                                            <div class="form-field">
                                                <input value="day_month" class="radio" type="radio" id="date_format_day_month" name="date_format" ' . $date_format_day_month_checked . '>
                                                <label class="" for="date_format_day_month">'. lang('day') . '/'. lang('month') . '/'. lang('year') . ' (14/2/' . date('Y') . ')</label>
                                            </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-12 ">
                                            <fieldset><legend class="form-label fs-md">'. lang('Time Format') . '</legend>
                                            <div class="form-field">
                                                <input value="twelve_hours" class="radio" type="radio" id="time_format_twelve_hours" name="time_format" ' . $time_format_twelve_hours_checked . '>
                                                <label class="" for="time_format_twelve_hours">'. lang('hour') . ':'. lang('minute') . ' am/pm (11:30 pm)</label>
                                            </div>
                                            <div class="form-field">
                                                <input value="twenty_four_hours" class="radio" type="radio" id="time_format_twenty_four_hours" name="time_format" ' . $time_format_twenty_four_hours_checked . '>
                                                <label class="" for="time_format_twenty_four_hours">'. lang('hour') . ':'. lang('minute') . ' (23:30)</label>
                                            </div>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

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
