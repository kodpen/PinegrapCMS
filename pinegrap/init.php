<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This file bootstraps every entry point and it is not idempotent: it requires
// functions.php outright, so a second load would fatal on the first redeclared
// function. The general job can run another scheduled job in the same process
// and every one of those scripts starts by requiring this file, so loading it
// twice has to be a no-op instead.
if (defined('PG_INIT_LOADED')) {
    return;
}

define('PG_INIT_LOADED', true);

try {
    if (!@include_once(dirname(__FILE__) . '/data/config.php')) {
        if (!file_exists(dirname(__FILE__) . '/data/config.php')) {
            if (!file_exists(dirname(__FILE__) . '/data')) {
                mkdir(dirname(__FILE__) . '/data', 0755);
            }

            $config_file = fopen(dirname(__FILE__) . '/data/config.php', 'w');
            fwrite($config_file, '<?php ?>');
            fclose($config_file);
        } else {
            require_once(dirname(__FILE__) . '/data/config.php');
        }
    }
} catch (Exception $e) {
    echo "Message : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo " Code : " . (int) $e->getCode();
}

require(dirname(__FILE__) . '/functions.php');

// Capture per-request performance baseline as early as possible.
// The actual recording happens in a register_shutdown_function() handler
// after fastcgi_finish_request(), so this call adds no user-visible latency.
perf_monitor_init();

// Autoload the liveform class so it is only loaded if it is used,
// and so that we don't have to manually load it when necessary.
// It is not used for many front-end pages, so we don't just want to always load it.
spl_autoload_register('autoload_liveform');

// If we have not already updated the PHP settings in the router.php script,
// then update them now.
if (!defined('PHP_SETTINGS_UPDATED')) {

    // Pinegrap error reporting: suppress notices and deprecated warnings.
    // Set SET_ERROR_REPORTING to false in config.php to let php.ini take over.
    if (!defined('SET_ERROR_REPORTING') or SET_ERROR_REPORTING) {
        ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    }


    ini_set('default_charset', 'utf-8');
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
}

// get software directory

// if this server is on Windows, then path delimiter is a backslash
if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {
    $delimiter = '\\';

    // else this server is not on Windows, so path delimiter is a forward slash
} else {
    $delimiter = '/';
}
$path_parts = explode($delimiter, dirname(__FILE__));
if (!defined('SOFTWARE_DIRECTORY')) {
    define('SOFTWARE_DIRECTORY', $path_parts[count($path_parts) - 1]);
}


// prepare escaped version of software directory because we will use this a lot
if (!defined('OUTPUT_SOFTWARE_DIRECTORY')) {
    define('OUTPUT_SOFTWARE_DIRECTORY', h(SOFTWARE_DIRECTORY));
}


// if this request was made over the web (i.e. not a cron job),
// then get the path (e.g. /~example) to the software root (i.e. the level above the software directory)
// For cron jobs, we will set the path later from the value that we get from the database
if ($_SERVER['HTTP_HOST'] != '') {
    // get the url path parts in order to get the file name
    $url_path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    $file_name = $url_path_parts[count($url_path_parts) - 1];

    // if the index.php in the software root was requested, then get the path in a certain way
    if (
        ($file_name == 'index.php')
        && (defined('NON_ROOT_INDEX') == FALSE)
    ) {
        // get the path without the file name on the end
        $url_path = dirname($_SERVER['SCRIPT_NAME']);

        // convert backslashes to forward slashes
        // backslashes seem to only appear on Windows when only the root is left (e.g. \).
        $url_path = str_replace('\\', '/', $url_path);

        // else the software root index.php file was not requested, so get the path in a different way
    } else {
        // if this is at least PHP 5.0.0, then use strrpos to get the position of the last occurrence of the software directory in the path
        // PHP 4 does not support multicharacter needle for strrpos
        if (version_compare(PHP_VERSION, '5.0.0', '>=') == TRUE) {
            $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);

            // else this is PHP 4, so use mb_strrpos, which supports a multicharacter needle
        } else {
            $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);
        }

        // if the software directory could not be found in the path (should never happen), then set the path to /
        if ($position === FALSE) {
            $url_path = '/';

            // else the software directory was found, so set the path to everything up to the software directory
        } else {
            $url_path = mb_substr($_SERVER['SCRIPT_NAME'], 0, $position);
        }
    }

    // if the path is not the root, then add a slash on the end
    if ($url_path != '/') {
        $url_path .= '/';
    }
    if (!defined('PATH')) {
        define('PATH', $url_path);
    }
    // prepare escaped version of path because we will use this a lot
    define('OUTPUT_PATH', h(PATH));

    // If the REQUEST_URL has not been defined by the router already,
    // then set it (e.g. for control panel screens).
    if (!defined('REQUEST_URL')) {
        define('REQUEST_URL', get_request_uri());
    }
}

// If a config file path is not set, then set it to the default which is a path
// inside the software directory. A custom config file path is used when
// an adminstrator wants the config file to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('CONFIG_FILE_PATH') == false) {
    define('CONFIG_FILE_PATH', dirname(__FILE__) . '/data/config.php');
}

// If a file directory path is not set, then set it to the default which is a path
// inside the software directory. A custom file directory path is used when
// an adminstrator wants the file directory to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('FILE_DIRECTORY_PATH') == false) {
    define('FILE_DIRECTORY_PATH', dirname(__FILE__) . '/data/files');
}

// If a layout directory path is not set, then set it to the default which is a path
// inside the software directory. A custom layout directory path is used when
// an adminstrator wants the layout directory to be located in a different area.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (!defined('LAYOUT_DIRECTORY_PATH')) {
    define('LAYOUT_DIRECTORY_PATH', dirname(__FILE__) . '/data/layouts');
}

// If an htaccess file path is not set, then set it to the default which is a path
// in the web root. A custom htaccess file path is used when the .htaccess file is
// is located in a separate location from the software.
// For example, this is required under a multitenant architecture where multiple sites
// are using the same software directory.
if (defined('HTACCESS_FILE_PATH') == false) {
    // We are using stristr instead of mb_stristr because mb_stristr requires PHP 5.2,
    // and we still have some sites on PHP 5.1 (probably won't cause any utf-8 issue).

    // If the web server is IIS then set the htaccess file info to the httpd.ini location.
    if (stristr($_SERVER['SERVER_SOFTWARE'], 'iis')) {
        define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../httpd.ini');
        define('HTACCESS_FILE_NAME', 'httpd.ini');

        // Otherwise the web server is Apache, so set the htaccess file info to the .htaccess location.
    } else {
        define('HTACCESS_FILE_PATH', dirname(__FILE__) . '/../.htaccess');
        define('HTACCESS_FILE_NAME', '.htaccess');
    }
}

// if DB_HOST is not defined, then config.php is not setup properly,
// so installation has probably not been completed, so output error
if (defined('DB_HOST') == false) {
    print lang('config.php is not configured properly. This probably means that the software has not been installed. Please install the software, or configure config.php properly.');
    exit();
}

// If the ENVIRONMENT constant is set to "development", then set the ENVIRONMENT_SUFFIX to "src".
// This allows us to use source files instead of minified files during development.
if (defined('ENVIRONMENT') and ENVIRONMENT == 'development') {
    define('ENVIRONMENT_SUFFIX', 'src');

    // Otherwise the ENVIRONMENT constant is not defined or set to something else, so set the ENVIRONMENT_SUFFIX to "min".
} else {
    define('ENVIRONMENT_SUFFIX', 'min');
}

// If we have not already connected to the database in the router.php script, then connect to it.
if (!defined('DB_CONNECTED') or DB_CONNECTED !== true) {
    db_connect();
}

// get configuration constants from database
$query = "SELECT * FROM config";
$result = mysqli_query(db::$con, $query) or exit(mysqli_error(db::$con));
$row = mysqli_fetch_assoc($result);

// Hand the firewall the config row we just read, so it does not query the
// same table again. Loading it here (rather than at waf_run below) also means
// the settings screen can call waf_* helpers without a second read.
require_once(dirname(__FILE__) . '/waf.php');
waf_prime_config($row);

// change type of values that should be boolean
settype($row['private_label'], 'boolean');
settype($row['software_update_available'], 'boolean');
settype($row['forgot_password_link'], 'boolean');
settype($row['mobile'], 'boolean');
settype($row['social_networking'], 'boolean');
settype($row['social_networking_facebook'], 'boolean');
settype($row['social_networking_twitter'], 'boolean');
settype($row['social_networking_addthis'], 'boolean');
settype($row['social_networking_plusone'], 'boolean');
settype($row['social_networking_linkedin'], 'boolean');
settype($row['captcha'], 'boolean');
settype($row['auto_dialogs'], 'boolean');
settype($row['mass_deletion'], 'boolean');
settype($row['strong_password'], 'boolean');
settype($row['password_hint'], 'boolean');
settype($row['remember_me'], 'boolean');
settype($row['membership_expiration_warning_email'], 'boolean');
settype($row['visitor_tracking'], 'boolean');
settype($row['google_analytics'], 'boolean');
settype($row['ecommerce'], 'boolean');
settype($row['ecommerce_multicurrency'], 'boolean');
settype($row['ecommerce_tax'], 'boolean');
settype($row['ecommerce_tax_exempt'], 'boolean');
settype($row['ecommerce_shipping'], 'boolean');
settype($row['ecommerce_address_verification'], 'boolean');
settype($row['ups'], 'boolean');
settype($row['fedex'], 'boolean');
settype($row['ecommerce_gift_card'], 'boolean');
settype($row['ecommerce_givex'], 'boolean');
settype($row['ecommerce_credit_debit_card'], 'boolean');
settype($row['ecommerce_american_express'], 'boolean');
settype($row['ecommerce_diners_club'], 'boolean');
settype($row['ecommerce_discover_card'], 'boolean');
settype($row['ecommerce_mastercard'], 'boolean');
settype($row['ecommerce_visa'], 'boolean');
settype($row['ecommerce_troy'], 'boolean');
settype($row['ecommerce_paypal_express_checkout'], 'boolean');
settype($row['ecommerce_offline_payment'], 'boolean');
settype($row['ecommerce_iyzipay_threeds'], 'boolean');
settype($row['ecommerce_pay_with_iyzico'], 'boolean');
settype($row['ecommerce_offline_payment_only_specific_orders'], 'boolean');
settype($row['ecommerce_reward_program'], 'boolean');
settype($row['ecommerce_reward_program_membership'], 'boolean');
settype($row['ecommerce_reward_program_email'], 'boolean');
settype($row['forms'], 'boolean');
settype($row['calendars'], 'boolean');
settype($row['ads'], 'boolean');
settype($row['affiliate_program'], 'boolean');
settype($row['affiliate_automatic_approval'], 'boolean');
settype($row['mailchimp'], 'boolean');
settype($row['debug'], 'boolean');
settype($row['strutured_data'], 'boolean');
settype($row['advanced_visual_effects'], 'boolean');
settype($row['enable_parasut'], 'boolean');
settype($row['enable_iyzipay_protected_currency'], 'boolean');

// define all constants 

define('VERSION', $row['version']);
if (!defined('EDITION')) {
    define('EDITION', 'PRE');
}

$private_label = $row['private_label'];
define('LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP', $row['last_software_update_check_timestamp']);
$current_timestamp = time();
if (
    (defined('SOFTWARE_UPDATE_CHECK') == false or SOFTWARE_UPDATE_CHECK == true)
    and ($current_timestamp >= (LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP + 259200))
) {

    define('SOFTWARE_UPDATE_CHECK_NEEDED', true);
}else{
    define('SOFTWARE_UPDATE_CHECK_NEEDED', false);
}

// don't define constant yet for SOFTWARE_UPDATE_AVAILABLE, because we might check further below if a software update is available
// just set value in variable
$original_software_update_available = $row['software_update_available'];

define('URL_SCHEME', $row['url_scheme']);

// if script was requested from a browser, then define $_SERVER['HTTP_HOST'] for hostname
if (isset($_SERVER['HTTP_HOST']) == true) {
    define('HOSTNAME', $_SERVER['HTTP_HOST']);

    // else if script was not requested from a browser (e.g. scheduled task), then define hostname setting for hostname
} else {
    define('HOSTNAME', $row['hostname']);
}

// also define the hostname setting because in some areas we need to know the exact setting
define('HOSTNAME_SETTING', $row['hostname']);

define('EMAIL_ADDRESS', $row['email_address']);
define('TITLE', $row['title']);
define('META_DESCRIPTION', $row['meta_description']);
define('META_KEYWORDS', $row['meta_keywords']);
define('REMEMBER_ME', $row['remember_me']);
define('FORGOT_PASSWORD_LINK', $row['forgot_password_link']);
define('MOBILE', $row['mobile']);
define('SEARCH_TYPE', $row['search_type']);
define('SOCIAL_NETWORKING', $row['social_networking']);
define('SOCIAL_NETWORKING_TYPE', $row['social_networking_type']);
define('SOCIAL_NETWORKING_PLUSONE', $row['social_networking_plusone']);
define('SOCIAL_NETWORKING_ADDTHIS', $row['social_networking_addthis']);
define('SOCIAL_NETWORKING_FACEBOOK', $row['social_networking_facebook']);
define('SOCIAL_NETWORKING_TWITTER', $row['social_networking_twitter']);
define('SOCIAL_NETWORKING_LINKEDIN', $row['social_networking_linkedin']);
define('SOCIAL_NETWORKING_WHATSAPP', $row['social_networking_whatsapp']);
define('SOCIAL_NETWORKING_TELEGRAM', $row['social_networking_telegram']);
define('SOCIAL_NETWORKING_PINTEREST', $row['social_networking_pinterest']);
define('SOCIAL_NETWORKING_REDDIT', $row['social_networking_reddit']);
define('SOCIAL_NETWORKING_EMAIL', $row['social_networking_email']);
define('CAPTCHA', $row['captcha']);
define('AUTO_DIALOGS', $row['auto_dialogs']);
define('MASS_DELETION', $row['mass_deletion']);
define('STRONG_PASSWORD', $row['strong_password']);
define('PASSWORD_HINT', $row['password_hint']);
define('PROXY_ADDRESS', $row['proxy_address']);
define('BADGE_LABEL', $row['badge_label']);
define('TIMEZONE', $row['timezone']);
define('DATE_FORMAT', $row['date_format']);
define('TIME_FORMAT', $row['time_format']);
define('ORGANIZATION_NAME', $row['organization_name']);
define('ORGANIZATION_ADDRESS_1', $row['organization_address_1']);
define('ORGANIZATION_ADDRESS_2', $row['organization_address_2']);
define('ORGANIZATION_CITY', $row['organization_city']);
define('ORGANIZATION_STATE', $row['organization_state']);
define('ORGANIZATION_ZIP_CODE', $row['organization_zip_code']);
define('ORGANIZATION_COUNTRY', $row['organization_country']);
define('OPT_IN_LABEL', $row['opt_in_label']);
define('VISITOR_TRACKING', $row['visitor_tracking']);
// Firewall master switch, defined from the config row exactly like every
// other feature flag (ECOMMERCE, ADS, VISITOR_TRACKING). Anything that needs
// to know whether the firewall is armed reads this constant rather than
// querying, so the answer is identical everywhere in the request.
define('WAF_ENABLED', isset($row['waf_enabled']) ? (int) $row['waf_enabled'] : 0);
// Performance monitor switch, same pattern. Read here rather than acted on in
// perf_monitor_init(): that runs before this config row is loaded, so the
// shutdown handler is where the setting can actually be consulted. What it
// gates is all of the cost — one upsert — while the init half is a few
// microseconds of timers that are not worth a second config read to avoid.
define('PERF_MONITOR_ENABLED', isset($row['perf_monitor']) ? (int) $row['perf_monitor'] : 1);
// Live chat switches, following the WAF_ENABLED pattern: the code may be
// newer than the database — when the column is missing the feature acts
// disabled, the page does not break. CHAT_ENABLED is absolute: while off,
// both the launcher and the actions are fully disabled (chat.php / api.php
// read this constant).
define('CHAT_ENABLED', isset($row['chat_enabled']) ? (int) $row['chat_enabled'] : 0);
define('CHAT_SITE_ENABLED', isset($row['chat_site_enabled']) ? (int) $row['chat_site_enabled'] : 0);
define('CHAT_OPERATOR_USER_ID', isset($row['chat_operator_user_id']) ? (int) $row['chat_operator_user_id'] : 0);
define('CHAT_WELCOME_MESSAGE', isset($row['chat_welcome_message']) ? $row['chat_welcome_message'] : '');
define('CHAT_OFFLINE_EMAIL', isset($row['chat_offline_email']) ? (int) $row['chat_offline_email'] : 1);
define('CHAT_CAPTCHA', isset($row['chat_captcha']) ? (int) $row['chat_captcha'] : 1);
define('CHAT_RETENTION_DAYS', isset($row['chat_retention_days']) ? (int) $row['chat_retention_days'] : 60);
// Site widget appearance settings (2026.4.3): theme, accent color, icon.
define('CHAT_WIDGET_THEME', isset($row['chat_widget_theme']) ? $row['chat_widget_theme'] : 'auto');
define('CHAT_WIDGET_COLOR', isset($row['chat_widget_color']) ? $row['chat_widget_color'] : '#0d6efd');
define('CHAT_WIDGET_ICON', isset($row['chat_widget_icon']) ? $row['chat_widget_icon'] : 'chat');
// Widget label (2026.4.6): empty = "Live Support" from the language file.
define('CHAT_WIDGET_TITLE', isset($row['chat_widget_title']) ? $row['chat_widget_title'] : '');
// Chat attachments (2026.4.4): off by default — no upload endpoint works
// until the operator enables them.
define('CHAT_ALLOW_FILES', isset($row['chat_allow_files']) ? (int) $row['chat_allow_files'] : 0);
define('CHAT_ALLOW_IMAGES', isset($row['chat_allow_images']) ? (int) $row['chat_allow_images'] : 0);
define('CHAT_VISITOR_IMAGE_LIMIT', isset($row['chat_visitor_image_limit']) ? (int) $row['chat_visitor_image_limit'] : 5);
// Scheduled-job dispatch (2026.4.17), same pattern: off when the columns are
// not there yet, so the general job on an installation that has not run the
// upgrade simply dispatches nothing. JOB_DISPATCH is a comma separated list of
// job names; the master switch and the list are read separately so that
// turning the switch off keeps the operator's selection for next time.
define('JOB_DISPATCH_ENABLED', isset($row['job_dispatch_enabled']) ? (int) $row['job_dispatch_enabled'] : 0);
define('JOB_DISPATCH', isset($row['job_dispatch']) ? $row['job_dispatch'] : '');
define('ALLOWED_BOTS', $row['allowed_bots']);
define('BLOCK_UNKNOWN_BOTS', (int) $row['block_unknown_bots']);
define('PAY_PER_CLICK_FLAG', $row['pay_per_click_flag']);
define('STATS_URL', $row['stats_url']);
define('GOOGLE_ANALYTICS', $row['google_analytics']);
define('GOOGLE_ANALYTICS_WEB_PROPERTY_ID', $row['google_analytics_web_property_id']);
define('REGISTRATION_CONTACT_GROUP_ID', $row['registration_contact_group_id']);
define('REGISTRATION_EMAIL_ADDRESS', $row['registration_email_address']);
define('MEMBER_ID_LABEL', $row['member_id_label']);
define('MEMBERSHIP_CONTACT_GROUP_ID', $row['membership_contact_group_id']);
define('MEMBERSHIP_EMAIL_ADDRESS', $row['membership_email_address']);
define('MEMBERSHIP_EXPIRATION_WARNING_EMAIL', $row['membership_expiration_warning_email']);
define('MEMBERSHIP_EXPIRATION_WARNING_EMAIL_SUBJECT', $row['membership_expiration_warning_email_subject']);
define('MEMBERSHIP_EXPIRATION_WARNING_EMAIL_PAGE_ID', $row['membership_expiration_warning_email_page_id']);
define('MEMBERSHIP_EXPIRATION_WARNING_EMAIL_DAYS_BEFORE_EXPIRATION', $row['membership_expiration_warning_email_days_before_expiration']);
define('ECOMMERCE', $row['ecommerce']);
define('ECOMMERCE_MULTICURRENCY', $row['ecommerce_multicurrency']);
define('ECOMMERCE_TAX', $row['ecommerce_tax']);
define('ECOMMERCE_TAX_EXEMPT', $row['ecommerce_tax_exempt']);
define('ECOMMERCE_TAX_EXEMPT_LABEL', $row['ecommerce_tax_exempt_label']);
define('ECOMMERCE_SHIPPING', $row['ecommerce_shipping']);
define('ECOMMERCE_RECIPIENT_MODE', $row['ecommerce_recipient_mode']);
define('USPS_USER_ID', $row['usps_user_id']);
define('ECOMMERCE_ADDRESS_VERIFICATION', $row['ecommerce_address_verification']);
define('ECOMMERCE_ADDRESS_VERIFICATION_ENFORCEMENT_TYPE', $row['ecommerce_address_verification_enforcement_type']);
define('UPS', $row['ups']);
define('FEDEX', $row['fedex']);
define('ECOMMERCE_PRODUCT_RESTRICTION_MESSAGE', $row['ecommerce_product_restriction_message']);
define('ECOMMERCE_NO_SHIPPING_METHODS_MESSAGE', $row['ecommerce_no_shipping_methods_message']);
define('ECOMMERCE_END_OF_DAY_TIME', mb_substr($row['ecommerce_end_of_day_time'], 0, 5));
define('ECOMMERCE_EMAIL_ADDRESS', $row['ecommerce_email_address']);
define('ECOMMERCE_GIFT_CARD', $row['ecommerce_gift_card']);
define('ECOMMERCE_GIFT_CARD_VALIDITY_DAYS', $row['ecommerce_gift_card_validity_days']);
define('ECOMMERCE_GIVEX', $row['ecommerce_givex']);
define('ECOMMERCE_GIVEX_PRIMARY_HOSTNAME', $row['ecommerce_givex_primary_hostname']);
define('ECOMMERCE_GIVEX_SECONDARY_HOSTNAME', $row['ecommerce_givex_secondary_hostname']);
define('ECOMMERCE_GIVEX_USER_ID', $row['ecommerce_givex_user_id']);
define('ECOMMERCE_GIVEX_PASSWORD', $row['ecommerce_givex_password']);
define('ECOMMERCE_CREDIT_DEBIT_CARD', $row['ecommerce_credit_debit_card']);
define('ECOMMERCE_AMERICAN_EXPRESS', $row['ecommerce_american_express']);
define('ECOMMERCE_DINERS_CLUB', $row['ecommerce_diners_club']);
define('ECOMMERCE_DISCOVER_CARD', $row['ecommerce_discover_card']);
define('ECOMMERCE_MASTERCARD', $row['ecommerce_mastercard']);
define('ECOMMERCE_VISA', $row['ecommerce_visa']);
define('ECOMMERCE_TROY', $row['ecommerce_troy']);
define('ECOMMERCE_PAYMENT_GATEWAY', $row['ecommerce_payment_gateway']);
define('ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE', $row['ecommerce_payment_gateway_transaction_type']);
define('ECOMMERCE_PAYMENT_GATEWAY_MODE', $row['ecommerce_payment_gateway_mode']);
define('ECOMMERCE_AUTHORIZENET_API_LOGIN_ID', $row['ecommerce_authorizenet_api_login_id']);
define('ECOMMERCE_AUTHORIZENET_TRANSACTION_KEY', $row['ecommerce_authorizenet_transaction_key']);
define('ECOMMERCE_CLEARCOMMERCE_CLIENT_ID', $row['ecommerce_clearcommerce_client_id']);
define('ECOMMERCE_CLEARCOMMERCE_USER_ID', $row['ecommerce_clearcommerce_user_id']);
define('ECOMMERCE_CLEARCOMMERCE_PASSWORD', $row['ecommerce_clearcommerce_password']);
define('ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_STORE_NUMBER', $row['ecommerce_first_data_global_gateway_store_number']);
define('ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_PEM_FILE_NAME', $row['ecommerce_first_data_global_gateway_pem_file_name']);
define('ECOMMERCE_PAYPAL_PAYFLOW_PRO_PARTNER', $row['ecommerce_paypal_payflow_pro_partner']);
define('ECOMMERCE_PAYPAL_PAYFLOW_PRO_MERCHANT_LOGIN', $row['ecommerce_paypal_payflow_pro_merchant_login']);
define('ECOMMERCE_PAYPAL_PAYFLOW_PRO_USER', $row['ecommerce_paypal_payflow_pro_user']);
define('ECOMMERCE_PAYPAL_PAYFLOW_PRO_PASSWORD', $row['ecommerce_paypal_payflow_pro_password']);
define('ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_USERNAME', $row['ecommerce_paypal_payments_pro_api_username']);
define('ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_PASSWORD', $row['ecommerce_paypal_payments_pro_api_password']);
define('ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_SIGNATURE', $row['ecommerce_paypal_payments_pro_api_signature']);
define('ECOMMERCE_SAGE_MERCHANT_ID', $row['ecommerce_sage_merchant_id']);
define('ECOMMERCE_SAGE_MERCHANT_KEY', $row['ecommerce_sage_merchant_key']);
define('ECOMMERCE_STRIPE_API_KEY', $row['ecommerce_stripe_api_key']);
define('ECOMMERCE_SURCHARGE_PERCENTAGE', $row['ecommerce_surcharge_percentage']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT', $row['ecommerce_paypal_express_checkout']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_TRANSACTION_TYPE', $row['ecommerce_paypal_express_checkout_transaction_type']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE', $row['ecommerce_paypal_express_checkout_mode']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_USERNAME', $row['ecommerce_paypal_express_checkout_api_username']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_PASSWORD', $row['ecommerce_paypal_express_checkout_api_password']);
define('ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_SIGNATURE', $row['ecommerce_paypal_express_checkout_api_signature']);
define('ECOMMERCE_OFFLINE_PAYMENT', $row['ecommerce_offline_payment']);
define('ECOMMERCE_OFFLINE_PAYMENT_ONLY_SPECIFIC_ORDERS', $row['ecommerce_offline_payment_only_specific_orders']);
define('ECOMMERCE_PRIVATE_FOLDER_ID', $row['ecommerce_private_folder_id']);
define('ECOMMERCE_RETRIEVE_ORDER_NEXT_PAGE_ID', $row['ecommerce_retrieve_order_next_page_id']);
define('ECOMMERCE_REWARD_PROGRAM', $row['ecommerce_reward_program']);
define('ECOMMERCE_REWARD_PROGRAM_POINTS', $row['ecommerce_reward_program_points']);
define('ECOMMERCE_REWARD_PROGRAM_MEMBERSHIP', $row['ecommerce_reward_program_membership']);
define('ECOMMERCE_REWARD_PROGRAM_MEMBERSHIP_DAYS', $row['ecommerce_reward_program_membership_days']);
define('ECOMMERCE_REWARD_PROGRAM_EMAIL', $row['ecommerce_reward_program_email']);
define('ECOMMERCE_REWARD_PROGRAM_EMAIL_BCC_EMAIL_ADDRESS', $row['ecommerce_reward_program_email_bcc_email_address']);
define('ECOMMERCE_REWARD_PROGRAM_EMAIL_SUBJECT', $row['ecommerce_reward_program_email_subject']);
define('ECOMMERCE_REWARD_PROGRAM_EMAIL_PAGE_ID', $row['ecommerce_reward_program_email_page_id']);
define('ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL', $row['ecommerce_custom_product_field_1_label']);
define('ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL', $row['ecommerce_custom_product_field_2_label']);
define('ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL', $row['ecommerce_custom_product_field_3_label']);
define('ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL', $row['ecommerce_custom_product_field_4_label']);
define('ECOMMERCE_IYZIPAY_API_KEY', $row['ecommerce_iyzipay_api_key']);
define('ECOMMERCE_IYZIPAY_SECRET_KEY', $row['ecommerce_iyzipay_secret_key']);
define('ECOMMERCE_IYZIPAY_INSTALLMENT', $row['ecommerce_iyzipay_installment']);
define('ECOMMERCE_IYZIPAY_THREEDS', $row['ecommerce_iyzipay_threeds']);
define('ECOMMERCE_PAY_WITH_IYZICO', $row['ecommerce_pay_with_iyzico']);
define('ECOMMERCE_SHOW_PRODUCT_IMAGES', $row['ecommerce_show_product_images']);
define('FORMS', $row['forms']);
define('CALENDARS', $row['calendars']);
define('ADS', $row['ads']);
define('AFFILIATE_PROGRAM', $row['affiliate_program']);
define('AFFILIATE_DEFAULT_COMMISSION_RATE', $row['affiliate_default_commission_rate']);
define('AFFILIATE_AUTOMATIC_APPROVAL', $row['affiliate_automatic_approval']);
define('AFFILIATE_CONTACT_GROUP_ID', $row['affiliate_contact_group_id']);
define('AFFILIATE_EMAIL_ADDRESS', $row['affiliate_email_address']);
define('AFFILIATE_GROUP_OFFER_ID', $row['affiliate_group_offer_id']);
define('MAILCHIMP', $row['mailchimp']);
define('DEBUG', $row['debug']);
define('SEO_ANALYSES', $row['number_of_queries']);
define('NUMBER_OF_EMAIL_RECIPIENTS', $row['number_of_email_recipients']);
define('SPECIAL_USER_ID', $row['special_user_id']);
define('LAST_SITEMAP_CHECK_TIMESTAMP', $row['last_sitemap_check_timestamp']);
define('LAST_SITEMAP_CHECK_HASH', $row['last_sitemap_check_hash']);
define('LAST_RECURRING_COMMISSION_CHECK_TIMESTAMP', $row['last_recurring_commission_check_timestamp']);
define('INSTALLER', $row['installer']);
define('SUBSCRIPTION_ID', $row['subscription_id']);
define('SUBSCRIPTION_KEY', $row['subscription_key']);
define('STRUTURED_DATA', $row['strutured_data']);
define('ADVANCED_VISUAL_EFFECTS', $row['advanced_visual_effects']);
define('LAST_SOFTWARE_AUTO_BACKUP', $row['last_software_auto_backup']);
define('ENABLE_PARASUT', $row['enable_parasut']);
define('PARASUT_TC_IN_FIELD', $row['parasut_tc_in_field']);
define('PARASUT_CLIENT_ID', $row['parasut_client_id'] ?? '');
define('PARASUT_CLIENT_SECRET', $row['parasut_client_secret'] ?? '');
define('PARASUT_USERNAME', $row['parasut_username'] ?? '');
define('PARASUT_PASSWORD', $row['parasut_password'] ?? '');
define('PARASUT_COMPANY_ID', $row['parasut_company_id'] ?? '');
define('PARASUT_USE_SANDBOX', !empty($row['parasut_use_sandbox']));
define('PARASUT_DEFAULT_PRODUCT_ID', $row['parasut_default_product_id'] ?? '');
define('PARASUT_DEFAULT_WAREHOUSE_ID', $row['parasut_default_warehouse_id'] ?? '');
define('PARASUT_API_BASE', 'https://api.parasut.com');
define('PARASUT_TOKEN_CACHE', dirname(__FILE__) . '/data/parasut_token.json');
define('ENABLE_IYZIPAY_PROTECTED_CURRENCY', $row['enable_iyzipay_protected_currency']);
define('IYZIPAY_PROTECTED_CURRENCY_CODE', $row['iyzipay_protected_currency_code']);
define('INDEXNOW_KEY', $row['indexnow_key']);
define('BARCODE_ENABLED', !empty($row['barcode_enabled']));
define('BARCODE_DEFAULT_TYPE', $row['barcode_default_type'] ?? 'CODE128');
define('BARCODE_LABEL_WIDTH', $row['barcode_label_width'] ?? 60);
define('BARCODE_LABEL_HEIGHT', $row['barcode_label_height'] ?? 40);
define('BARCODE_LABEL_TEMPLATE', $row['barcode_label_template'] ?? '');


if (!defined('SOFTWARE_LANGUAGE')) {
    $lang = isset($row['software_language']) && $row['software_language'] !== '' && $row['software_language'] !== 'undefined'
        ? $row['software_language']
        : (defined('DEFAULT_SOFTWARE_LANGUAGE') ? DEFAULT_SOFTWARE_LANGUAGE : null);

    if ($lang !== null) {
        define('SOFTWARE_LANGUAGE', $lang);
    }
}


// If PHP is at least v5.1.3, then update timezone in PHP and MySQL.
// The timezone functions were added in v5.1.0, however the date('P') feature that we are using
// below to update MySQL was not added until v5.1.3, so that is why we are choosing that version.
if (version_compare(PHP_VERSION, '5.1.3', '>=') == true) {
    // Define the server timezone before we update the default timezone, so that we can know the server's default
    // timezone for the default option in the site settings.
    define('SERVER_TIMEZONE', @date_default_timezone_get());

    // If there is a timezone set in the site settings, then update PHP to use that.
    if (TIMEZONE != '') {
        date_default_timezone_set(TIMEZONE);

        // Otherwise there is not a timezone set, so update PHP to use server's timezone.
    } else {
        date_default_timezone_set(SERVER_TIMEZONE);
    }

    // Update timezone in MySQL to match PHP timezone.
    db("SET time_zone='" . date('P') . "'");

    // Otherwise this is a site with an old PHP version, so just set the server timezone to empty string.
} else {
    define('SERVER_TIMEZONE', '');
}

// if the path is not defined yet, then that means this request was made from a cron job,
// so set path to value from the database
if (defined('PATH') == FALSE) {
    define('PATH', $row['path']);

    // prepare escaped version of path because we will use this a lot
    define('OUTPUT_PATH', h(PATH));

    // else the path is defined, so if the defined path is different from the path in the database,
// then update the path in the database, so that the next time the e-mail campaign job runs,
// it will have the correct path
} else if (PATH != $row['path']) {
    $query = "UPDATE config SET path = '" . escape(PATH) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

// Check if we need to set a default value for logo_url
if (!defined('LOGO_URL')) {
    define('LOGO_URL', PATH . SOFTWARE_DIRECTORY . '/assets/images/logo.png?v=' . @filemtime(dirname(__FILE__) . '/assets/images/logo.png'));
}

// Check if we need to set a default value for control_panel_stylesheet_url
if (!defined('CONTROL_PANEL_STYLESHEET_URL')) {
    define('CONTROL_PANEL_STYLESHEET_URL', PATH . SOFTWARE_DIRECTORY . '/assets/backend.src.css?v=' . @filemtime(dirname(__FILE__) . '/assets/backend.src.css'));
}


// PHP+8 depricated//..
// if magic quotes is on, remove slashes from data so our data is clean
if (
    (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc())
    || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off"))
) {

    $_REQUEST = array_stripslashes($_REQUEST);
    $_GET = array_stripslashes($_GET);
    $_POST = array_stripslashes($_POST);
    $_COOKIE = array_stripslashes($_COOKIE);
}


// If this request was made over the web instead of a cron job,
// then deal with forcing secure mode and starting session.
if (!empty($_SERVER['HTTP_HOST'])) {

    // Determine if request is secure or not.
    $secure_request = check_if_request_is_secure();

    // Get secure_mode parameter safely (default: null)
    $secure_mode = isset($_GET['secure_mode']) ? $_GET['secure_mode'] : null;

    // If secure mode is enabled, and the visitor is not in secure mode,
    // and REQUIRE_SECURE_MODE has not been disabled in the config.php file,
    // then don't complete this request for the visitor.
    if (
        (URL_SCHEME === 'https://')
        && ($secure_request === false)
        && (
            (defined('REQUIRE_SECURE_MODE') === false)
            || (REQUIRE_SECURE_MODE !== false)
        )
        && ($secure_mode !== 'false')
    ) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            output_error(
                lang('Sorry, this website does not allow insecure requests. Please submit the form to a secure address (i.e. "https" instead of "http").'),
                403
            );

            // Redirect loop guard. A proxy or CDN is telling us the visitor already
            // arrived over HTTPS, but TRUST_PROXY_SSL_HEADERS is off so that claim was
            // not honoured above. Redirecting to HTTPS would come straight back to this
            // point over the proxy's plain HTTP leg and loop forever - the classic
            // Cloudflare "Flexible" SSL lockout. Stop and explain instead.
        } elseif (check_proxy_ssl_headers() === true) {
            output_error(
                lang('Secure Mode is enabled and your connection appears to be secure, but this server cannot verify it because SSL is terminated by a proxy or CDN (for example Cloudflare Flexible SSL). Enable TRUST_PROXY_SSL_HEADERS in the config file, or turn off Secure Mode.'),
                500
            );
        } else {
            // Use a temporary redirect. A 301 is cached by the browser, so a
            // misconfiguration keeps looping from cache even after the server side
            // has been corrected.
            header('Location: https://' . HOSTNAME_SETTING . REQUEST_URL, true, 302);
            exit();
        }

        // Otherwise if the request is secure, and the secure_mode query string value is set to false,
        // then redirect visitor to non-secure URL.
    } elseif (
        ($secure_request === true)
        && ($secure_mode === 'false')
    ) {
        header('Location: http://' . HOSTNAME_SETTING . REQUEST_URL, true, 301);
        exit();
    }

    // If secure mode is enabled, then setup the session cookie
    if (URL_SCHEME === 'https://') {
        ini_set('session.cookie_secure', '1');
    }

    // If PHP version is greater or equal to 5.2.0 then set HttpOnly flag
    if (version_compare(PHP_VERSION, '5.2.0', '>=')) {
        ini_set('session.cookie_httponly', '1');
    }

    // Start session after secure mode checks
    session_start();
}




define('PRIVATE_LABEL', $private_label);

// Initialize user in order to handle remember me and set user information if user is logged in.
initialize_user();

// ── Web Application Firewall (second pass) ───────────────────────────────
// Runs after initialize_user() so a "remember me" administrator is already
// recognised. Anything the router pass already did is latched and skipped;
// what happens here that could not happen there is content inspection, which
// needs to know whether the request comes from an authenticated designer
// (who edits HTML, JavaScript and SQL for a living) or from a stranger.
waf_run('init');

// Set constant to value from database. Updated by async check after it completes.
define('SOFTWARE_UPDATE_AVAILABLE', $original_software_update_available);


// detect and set device type (i.e. desktop or mobile) in session and cookie if it has not already been done
initialize_device_type();

// Generate token and add it to session for visitor if it has not already been done in order to prevent CSRF attacks.
// We use this token in so many places (for post and get requests) so that is why we do this here.
initialize_token();

// If ecommerce is enabled, then check order and prepare currency values.
if (ECOMMERCE == true) {

    // If there is an order in this visitor's session, then check if order is still an incomplete
    // order that is allowed to be active for visitor.  This is important, because there might
    // be multiple users accessing the same order, at the same time.  So, if one user completes
    // the order, then we need to remove the order from the session for the other user.  This
    // will prevent the cart region, cart, and etc. from showing the completed order for the
    // other user.

    if (isset($_SESSION['ecommerce']['order_id']) && ($_SESSION['ecommerce']['order_id'] ?? '')) {

        $order = db_item(
            "SELECT status FROM orders WHERE id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'"
        );

        // If order was not found (e.g. deleted), or has already been completed, then this order
        // is not allowed to be active for the visitor, so remove it from session.
        if (!$order or $order['status'] != 'incomplete') {
            unset($_SESSION['ecommerce']['order_id']);
        }
    }

    // Get base currency
    $base_currency = db_item("
        SELECT
            id,
            code,
            symbol,
            exchange_rate
        FROM currencies
        WHERE base = '1'
    ");

    // If a base currency was found, then set constants for that currency
    if (is_array($base_currency) && isset($base_currency['id']) && $base_currency['id'] !== '') {
        define('BASE_CURRENCY_ID', $base_currency['id']);
        define('BASE_CURRENCY_CODE', $base_currency['code']);
        define('BASE_CURRENCY_SYMBOL', $base_currency['symbol']);
    } else {
        // Otherwise a base currency was not found, so use USD
        define('BASE_CURRENCY_ID', 0);
        define('BASE_CURRENCY_CODE', 'USD');
        define('BASE_CURRENCY_SYMBOL', '$');
    }

    define('BASE_CURRENCY_EXCHANGE_RATE', 1);

    $visitor_currency = array();

    // If multi-currency is enabled in the site settings,
    // and the visitor has updated the currency,
    // and the updated currency is different from the base currency,
    // then get info about the visitor's currency.
    if (
        (ECOMMERCE_MULTICURRENCY == true)
        && (isset($_SESSION['ecommerce']['currency_id']) == true)
        && ($_SESSION['ecommerce']['currency_id'] != BASE_CURRENCY_ID)
    ) {
        $visitor_currency = db_item(
            "SELECT
                id,
                code,
                symbol,
                exchange_rate
            FROM currencies
            WHERE id = '" . escape($_SESSION['ecommerce']['currency_id']) . "'"
        );
    }

    // If a visitor currency was found, then prepare constants.
    if (isset($visitor_currency['id']) && $visitor_currency['id'] != '') {
        define('VISITOR_CURRENCY_ID', $visitor_currency['id']);
        define('VISITOR_CURRENCY_CODE', $visitor_currency['code']);
        define('VISITOR_CURRENCY_CODE_FOR_OUTPUT', ' ' . $visitor_currency['code']);
        define('VISITOR_CURRENCY_SYMBOL', $visitor_currency['symbol']);
        define('VISITOR_CURRENCY_EXCHANGE_RATE', $visitor_currency['exchange_rate']);

        // Otherwise a visitor currency was not found, so just use base currency for visitor currency.
    } else {
        define('VISITOR_CURRENCY_ID', BASE_CURRENCY_ID);
        define('VISITOR_CURRENCY_CODE', BASE_CURRENCY_CODE);
        define('VISITOR_CURRENCY_CODE_FOR_OUTPUT', '');
        define('VISITOR_CURRENCY_SYMBOL', BASE_CURRENCY_SYMBOL);
        define('VISITOR_CURRENCY_EXCHANGE_RATE', BASE_CURRENCY_EXCHANGE_RATE);
    }
}



//initialize the developer security page redirect for pin locked pages.
initialize_developer_security();


// who_is_online check has been moved to api.php (action: user_online_check)
// and is now called via AJAX heartbeat from backend.src.js every 50 seconds.
// This avoids a synchronous DB query on every page load.