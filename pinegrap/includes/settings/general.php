<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the General cards: who runs the site, where it is, what the software is and the time it keeps.
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
                        <input type="text" name="subscription_key" id="subscription_key" maxlength="19" autocomplete="false" spellcheck="false" autocapitalize="characters" class="form-control input-mask-key-code" value="' . h(SUBSCRIPTION_KEY) . '"' . (($license_site_warning !== '') ? ' aria-describedby="subscription_key_site"' : '') . ' />' . $license_site_warning_html . '
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
