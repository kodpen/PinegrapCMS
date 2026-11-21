<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the General cards: who runs the site, where it is, what the software is and the time it keeps.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card;
 * the rows' order, names and lines are includes/fn/general_settings_fold.php.
 * Fills $pg_settings_cards with the one list of rows. Which cards belong here
 * is said once, in includes/settings/registry.php; this file holds them, and
 * general.save.php writes what they edit. The variables they read are
 * prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/general_settings_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// Each part's card and its line of state, by row key; drawn as rows at the
// end in pg_general_fold_order().
$general_parts = array();

// ── Organization ──
// Who runs the site, in one place: the name and address the campaigns, the
// invoices and the legal texts print, the address system mail comes from,
// and the seller: the logo of the structured data and the tax number. Each
// field keeps its column; general.save.php writes them all.
$general_parts['organization'] = array('html' => '
    <div id="pgset-organization" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Organization') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Name and address') . '</h3></div>
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
                    <div class="col-12"><label for="setting_email_address" class="pg-group-label d-block mb-0">' . lang('Support E-mail Address') . '</label></div>
                    <div class="pg-f-md">
                        <input type="text" name="email_address" id="setting_email_address" autocomplete="off" class="form-control" value="' . h($email_address) . '" inputmode="email" aria-describedby="setting_email_address_help"/>
                        <div class="form-text" id="setting_email_address_help">' . h(lang('System mail is sent from this address; DKIM signs with its domain when none is set.')) . '</div>
                    </div>
                    <div class="col-12"><h3 class="pg-group-label mb-0">' . lang('Seller') . '</h3></div>' . $output_organization_logo_picker . (($output_organization_logo_picker !== '') ? pg_picker_script() : '') . '
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
    </div>') + pg_general_fold_organization($organization_name, $organization_city);

// ── Site address ──
// The Hostname, this server's IP as a line, and the outgoing proxy
// (PROXY_ADDRESS): the proxy this site's own requests go out through, not
// the trusted proxies in front of it, which Firewall keeps.
$general_parts['server'] = array('html' => '
    <div id="pgset-server" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Site address') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="pg-f-lg">
                        <label for="hostname" class="form-label">' . lang('Hostname') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="hostname" id="hostname" maxlength="255" class="form-control" value="' . h($hostname) . '" aria-describedby="hostname_ip"/>
                        <div class="pg-text-meta mt-2" id="hostname_ip">' . h(lang(array('string' => 'This server\'s IP: {var:1}', 'vars' => $server_addr))) . '</div>
                    </div>
                    <div class="pg-f-lg">
                        <label for="proxy_address" class="form-label">' . lang('Outgoing proxy') . '</label>
                        <input type="text" inputmode="url" spellcheck="false" autocapitalize="off" name="proxy_address" id="proxy_address" maxlength="255" class="form-control" value="' . h($proxy_address) . '" aria-describedby="proxy_address_help"/>
                        <div class="form-text" id="proxy_address_help">' . lang(array('string' => 'Requests this site sends to payment gateways, updates and some connected services go through it. Proxies in front of the site are set under {var:1}.', 'vars' => '<a href="#settings/firewall/pgset-proxies">' . h(lang('Trusted proxies')) . '</a>')) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_general_fold_site($hostname);

// ── Software ──
// With the Kodpen API on, the licence binds to the Hostname: a name only this
// machine or its network knows still spends a site right, and no valid name
// fails every check.
$license_site_warning = (defined('PG_VENDOR_API_V1') && (PG_VENDOR_API_V1 === true) && (SUBSCRIPTION_KEY !== ''))
    ? pg_license_site_warning(HOSTNAME_SETTING)
    : '';
$license_site_warning_html = '';
if ($license_site_warning === 'local') {
    $license_site_warning_html = '<div id="subscription_key_site" class="form-text fg-warning">' . lang('This Hostname is known only on this machine or its network; the license check will still spend one of the license\'s site rights on it.') . '</div>';
} elseif ($license_site_warning === 'none') {
    $license_site_warning_html = '<div id="subscription_key_site" class="form-text fg-danger">' . lang('The Hostname is not a valid site name, so license checks will fail until it is corrected.') . '</div>';
}

$general_parts['software'] = array('html' => '
    <div id="pgset-software" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Software') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    ' . $output_software_language . '
                    <div class="pg-f-lg">
                        <label for="subscription_key" class="form-label">' . lang('Subscription Key') . '</label>
                        <input type="text" name="subscription_key" id="subscription_key" maxlength="19" spellcheck="false" autocapitalize="characters" class="form-control input-mask-key-code" value="' . h(SUBSCRIPTION_KEY) . '"' . (($license_site_warning !== '') ? ' aria-describedby="subscription_key_site"' : '') . ' />' . $license_site_warning_html . '
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . $debug_checked . ' id="debug" name="debug" aria-describedby="debug_help"/>
                            <label class="pg-text-2" for="debug">' . lang('Verbose database errors') . '</label>
                        </div>
                        <div class="form-text fg-warning" id="debug_help">' . lang('Keep this on only while developing; the error text is shown to visitors.') . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_general_fold_software(SUBSCRIPTION_KEY !== '', $debug_checked !== '');

// ── Date and time ──
// A page served in another language prints dates in that language's own
// format (pg_sw_default_date_format()), set under Languages.
$general_parts['datetime'] = array('html' => '
    <div id="pgset-datetime" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 pg-title-section">
                ' . lang('Date and time') . '
            </div>
            <div class="card-body">
                <div class="row gy-5">
                    <div class="col-12">
                        <div class="pg-text-meta">' . lang('Current Site Time') . ': ' . get_absolute_time(array('timestamp' => time(), 'timezone_type' => 'site')) . '</div>
                    </div>
                    <div class="col-12">
                        <label for="timezone" class="form-label">' . lang('Timezone') . '</label>
                        <select class="form-control" id="timezone" name="timezone">' . $output_timezone_options . '</select>
                    </div>
                    <div class="col-12">
                        <fieldset aria-describedby="date_format_help"><legend class="form-label pg-field-label">' . lang('Date Format') . '</legend>
                        <div class="pg-seg flex-wrap">
                            <label class="pg-seg-item" for="date_format_month_day"><input value="month_day" class="visually-hidden" type="radio" id="date_format_month_day" name="date_format" ' . $date_format_month_day_checked . '>' . lang('month') . '/' . lang('day') . '/' . lang('year') . ' (2/14/' . date('Y') . ')</label>
                            <label class="pg-seg-item" for="date_format_day_month"><input value="day_month" class="visually-hidden" type="radio" id="date_format_day_month" name="date_format" ' . $date_format_day_month_checked . '>' . lang('day') . '/' . lang('month') . '/' . lang('year') . ' (14/2/' . date('Y') . ')</label>
                        </div>
                        <div class="form-text" id="date_format_help">' . h(lang('A page served in another language uses that language\'s date format')) . ' (<a href="#settings/languages/pgset-languages">' . h(lang('Languages')) . '</a>)</div>
                        </fieldset>
                    </div>
                    <div class="col-12">
                        <fieldset><legend class="form-label pg-field-label">' . lang('Time Format') . '</legend>
                        <div class="pg-seg flex-wrap">
                            <label class="pg-seg-item" for="time_format_twelve_hours"><input value="twelve_hours" class="visually-hidden" type="radio" id="time_format_twelve_hours" name="time_format" ' . $time_format_twelve_hours_checked . '>' . lang('hour') . ':' . lang('minute') . ' am/pm (11:30 pm)</label>
                            <label class="pg-seg-item" for="time_format_twenty_four_hours"><input value="twenty_four_hours" class="visually-hidden" type="radio" id="time_format_twenty_four_hours" name="time_format" ' . $time_format_twenty_four_hours_checked . '>' . lang('hour') . ':' . lang('minute') . ' (23:30)</label>
                        </div>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>') + pg_general_fold_datetime(($timezone !== '') ? $timezone : SERVER_TIMEZONE, $date_format, time());

// ── The rows ──
$general_rows = array();

foreach (pg_general_fold_order() as $general_key) {
    if (isset($general_parts[$general_key])) {
        $general_rows[] = array('key' => $general_key) + pg_general_fold_face($general_key) + $general_parts[$general_key];
    }
}

$pg_settings_cards[] = pg_settings_fold($general_rows);
