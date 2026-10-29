<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Google Merchant Center part of the Product Feeds card
 * (includes/fn/merchant_center.php): connect a Google account, choose the
 * Merchant Center account and the country, then register the Google TSV feed
 * as a data source Google fetches every day and see the product issues read
 * from the account.
 *
 * Runs in the scope of includes/settings/seo.php and leaves the markup in
 * $product_feeds_merchant; the Connect form goes to $pg_settings_modals,
 * outside the settings form. seo.save.php hands the posted fields to
 * pg_merchant_save_from_screen().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

$product_feeds_merchant = '';

if (pg_merchant_ready()) {

    // Back from connect.php: the connection's own message.
    if (isset($_GET['merchant_connect'])) {
        $merchant_flash = pg_connect_flash_take();

        if ($merchant_flash && ($merchant_flash['kind'] === 'error')) {
            $liveform->add_error(h($merchant_flash['text']));
        } elseif ($merchant_flash) {
            $liveform->add_notice(h($merchant_flash['text']) . ' ' . h(lang('Choose the Merchant Center account and save.')));
        }
    }

    $merchant_state = pg_merchant_state();
    $merchant_connection = pg_merchant_connection($merchant_state);
    $merchant_app_ready = pg_connect_can_seal() && pg_connect_app_ready(pg_connect_app('google'));
    $merchant_body = '';

    $merchant_country = ($merchant_state['country'] !== '')
        ? $merchant_state['country']
        : pg_merchant_default_country(defined('ORGANIZATION_COUNTRY') ? ORGANIZATION_COUNTRY : '', defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '');

    $merchant_country_field = '
                                            <div class="pg-f-sm">
                                                <label for="merchant_country" class="form-label">' . lang('Country') . '</label>
                                                <input type="text" name="merchant_country" id="merchant_country" class="form-control text-uppercase" maxlength="2" pattern="[A-Za-z]{2}" value="' . h($merchant_country) . '" placeholder="TR"/>
                                                <div class="form-text">' . lang('Two letters, the country the products are sold in.') . '</div>
                                            </div>';

    if (!is_array($merchant_connection)) {

        if ($merchant_app_ready) {
            $merchant_body = '
                                            <div class="col-12">
                                                <button type="submit" form="pgset_merchant_connect" class="btn btn-sm btn-outline theme-primary rounded-pill"><i class="bi bi-google me-1" aria-hidden="true"></i>' . lang('Connect a Google account') . '</button>
                                            </div>';

            // Forms cannot be nested, so the button above submits this one
            // by its form attribute.
            $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="pgset_merchant_connect" class="disable_shortcut d-none">
                ' . pg_connect_start_fields('google', 'merchant_center', pg_settings_url('seo') . '?merchant_connect=1#pgset-feeds') . '
            </form>';
        } else {
            $merchant_body = '
                                            <div class="col-12">
                                                <div class="form-text">' . h(lang('To connect Merchant Center, set up the Google application first.')) . ' <a href="connections.php">' . h(lang('Connected accounts')) . '</a></div>
                                            </div>';
        }

    } elseif ($merchant_state['account'] === '') {

        $merchant_offered = pg_merchant_accounts($merchant_connection['id']);
        $merchant_options = '';

        foreach ($merchant_offered['accounts'] as $merchant_id => $merchant_name) {
            $merchant_options .= '<option value="' . h($merchant_id) . '">' . h($merchant_name . (($merchant_name !== (string) $merchant_id) ? ' (' . $merchant_id . ')' : '')) . '</option>';
        }

        if ($merchant_options !== '') {
            $merchant_body = '
                                            <div class="pg-f-md">
                                                <label for="merchant_account" class="form-label">' . lang('Merchant Center account') . '</label>
                                                <select name="merchant_account" id="merchant_account" class="form-control"><option value="">' . lang('Choose') . '</option>' . $merchant_options . '</select>
                                                <div class="form-text">' . h(lang(array('string' => 'Connected as {var:1}.', 'vars' => $merchant_connection['label']))) . '</div>
                                            </div>' . $merchant_country_field . '
                                            <div class="col-12">
                                                <div class="form-text">' . lang('When you save, Google is given the address of the Google Merchant Center feed and fetches it every day.') . '</div>
                                            </div>';
        } else {
            $merchant_body = '
                                            <div class="col-12">
                                                <div class="form-text fg-warning">' . h(($merchant_offered['error'] !== '')
                                                    ? lang(array('string' => 'The Merchant Center accounts could not be read: {var:1}', 'vars' => $merchant_offered['error']))
                                                    : lang('The connected Google account has no Merchant Center account.')) . '</div>
                                            </div>';
        }

    } else {

        // The data source line: registered, failed, or not yet.
        if ($merchant_state['register_error'] !== '') {
            $merchant_source = '<span class="fg-danger">' . h(lang(array('string' => 'The feed could not be registered in Merchant Center: {var:1}', 'vars' => $merchant_state['register_error']))) . '</span>';
        } elseif ($merchant_state['data_source'] !== '') {
            $merchant_source = h(lang(array('string' => 'Merchant Center fetches the feed every day (registered {var:1}).', 'vars' => get_relative_time(array('timestamp' => $merchant_state['registered_at'], 'format' => 'plain_text')))));
        } else {
            $merchant_source = h(lang('The feed is not registered in Merchant Center yet.'));
        }

        // The issues line: the last reading, or why there is none.
        $merchant_counts = $merchant_state['issues']['counts'];

        if ($merchant_state['check_error'] !== '') {
            $merchant_issues = '<span class="fg-warning">' . h(lang(array('string' => 'The product issues could not be read: {var:1}', 'vars' => $merchant_state['check_error']))) . '</span>';
        } elseif ($merchant_state['checked_at'] > 0) {
            $merchant_issues = h(lang(array('string' => 'Issues: {var:1} disapproving, {var:2} demoting, {var:3} warnings (read {var:4}).', 'vars' => array(
                (int) $merchant_counts['DISAPPROVED'],
                (int) $merchant_counts['DEMOTED'],
                (int) $merchant_counts['NOT_IMPACTED'],
                get_relative_time(array('timestamp' => $merchant_state['checked_at'], 'format' => 'plain_text')),
            ))));

            $merchant_list = '';

            foreach ($merchant_state['issues']['top'] as $merchant_issue) {
                $merchant_text = h($merchant_issue['description']) . ' · ' . h(lang(array('string' => '{var:1} product(s)', 'vars' => (int) $merchant_issue['products'])));

                $merchant_list .= '<li>' . (($merchant_issue['help'] !== '')
                    ? '<a href="' . h($merchant_issue['help']) . '" target="_blank" rel="noopener noreferrer">' . $merchant_text . '</a>'
                    : $merchant_text) . '</li>';
            }

            if ($merchant_list !== '') {
                $merchant_issues .= '<ul class="mb-0 mt-1">' . $merchant_list . '</ul>';
            }
        } else {
            $merchant_issues = h(lang('The product issues have not been read yet.'));
        }

        $merchant_body = '
                                            <div class="col-12">
                                                <div class="form-text">' . h(lang('Merchant Center account')) . ': <a href="' . h(pg_merchant_center_url($merchant_state['account'])) . '" target="_blank" rel="noopener noreferrer">' . h($merchant_state['account_name'] . (($merchant_state['account_name'] !== $merchant_state['account']) ? ' (' . $merchant_state['account'] . ')' : '')) . '</a></div>
                                                <div class="form-text">' . $merchant_source . '</div>
                                                <div class="form-text">' . $merchant_issues . '</div>
                                            </div>' . $merchant_country_field . '
                                            <div class="col-12">
                                                <button type="submit" name="merchant_register" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-cloud-upload me-1" aria-hidden="true"></i>' . lang('Register the feed again') . '</button>
                                                <button type="submit" name="merchant_check" value="1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>' . lang('Check issues now') . '</button>
                                                <button type="submit" name="merchant_unlink" value="1" class="btn btn-sm btn-outline theme-warning" onclick="return confirm(' . h(json_encode(lang('Stop using this Merchant Center account? The data source stays in Merchant Center until you delete it there.'))) . ')"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . lang('Stop using this account') . '</button>
                                            </div>';
    }

    $product_feeds_merchant = '
                                        <div class="col-12">
                                            <h6 class="mb-1">' . lang('Google Merchant Center') . '</h6>
                                            <div class="form-text">' . lang('Gives Google the address of the product feed to fetch every day and shows the product problems Merchant Center reports.') . '</div>
                                        </div>' . $merchant_body . '
                                        <input type="hidden" name="merchant_card" value="1"/>';
}
