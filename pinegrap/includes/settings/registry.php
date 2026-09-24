<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one list of settings screens: the eight the site settings are split into,
 * and the neighbouring screens that live under the Settings menu.
 *
 * Read by three places -- the dialog's sidebar and the link helper
 * (includes/fn/output.php), the category screens (includes/settings/screen.php)
 * and the command palette (api.php). One list, because they all name the same
 * screens with the same gates, and copies of that answer drift: a screen added
 * to one is missing from the others and nothing says so.
 *
 * Gates are data here and they decide VISIBILITY only. Every screen keeps its
 * own validate_*_access() call at the top of the file, which is what actually
 * grants access -- a button that is not drawn is not a permission check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY') && !defined('PG_SETTINGS_MENU')) {
    exit;
}


/**
 * The search terms of one settings section, from the comma-separated list
 * lang() returns for it.
 *
 * The list in the source is English. Its translation is the whole list in the
 * panel language: the English terms kept, because an operator types "ssl" or
 * "waf" whatever the language, and the local words added, spelling variants
 * included. One string per section keeps it a single tr.json value rather
 * than an array of keys, and splitting here keeps the consumers unchanged.
 *
 * @param string $terms comma-separated terms
 * @return array lower-case terms, empty entries and repeats dropped
 */
function pg_settings_keywords($terms)
{
    $keywords = array();

    foreach (explode(',', (string) $terms) as $term) {
        $term = mb_strtolower(trim($term), 'UTF-8');
        if ($term !== '') {
            $keywords[$term] = true;
        }
    }

    return array_keys($keywords);
}


/**
 * The categories, in the order they are read.
 *
 * Each section id is the id the card carries on its screen, so it is at once
 * the scroll target, the #hash a deep link arrives on and what the toolbar chip
 * points at. Moving a card between screens is a line here and a block moved in
 * includes/settings/<key>.php.
 *
 * The keywords are the field names of a section rather than its title, because
 * nobody searches for "Feature Options" when they are looking for the cart. The
 * hub search and the command palette both read them. Each list is written in
 * English and translated as a whole through lang(); pg_settings_keywords()
 * turns the translated list back into an array.
 *
 * @return array key => array(label, icon, description, sections, keywords)
 */
function pg_settings_categories()
{
    $categories = array(

        'general' => array(
            'label' => lang('General'),
            'icon'  => 'bi-sliders',
            'description' => lang('Server, domain, licence, update channel, date and time.'),
            'sections' => array(
                'pgset-server'   => lang('Server & Domain'),
                'pgset-software' => lang('Software'),
                'pgset-channel'  => lang('Update Channel'),
                'pgset-datetime' => lang('Date & Time'),
                'pgset-cron'     => lang('Cron Jobs'),
            ),
            'keywords' => array(
                'pgset-server'   => pg_settings_keywords(lang('hostname, domain, ip, ssl, https, secure mode, secure, email, support, proxy')),
                'pgset-software' => pg_settings_keywords(lang('software, language, licence, license, subscription, error, debug')),
                'pgset-channel'  => pg_settings_keywords(lang('update, channel, beta, stable, version')),
                'pgset-datetime' => pg_settings_keywords(lang('date, time, timezone, format')),
                'pgset-cron'     => pg_settings_keywords(lang('cron, scheduled, task, job, schedule, automatic')),
            ),
        ),

        'appearance' => array(
            'label' => lang('Appearance'),
            'icon'  => 'bi-palette',
            'description' => lang('The panel theme and the rich-text editor.'),
            'sections' => array(
                'pgset-theme'  => lang('Theme'),
                'pgset-editor' => lang('Rich-text Editor'),
            ),
            'keywords' => array(
                'pgset-theme'  => pg_settings_keywords(lang('theme, appearance, color, custom css, acrylic')),
                'pgset-editor' => pg_settings_keywords(lang('editor, text, wysiwyg, rich, font')),
            ),
        ),

        'features' => array(
            'label' => lang('Features'),
            'icon'  => 'bi-toggles',
            'description' => lang('Which features are on, and how images are handled.'),
            'sections' => array(
                'pgset-features' => lang('Feature Options'),
                'pgset-images'   => lang('Image Optimization'),
                'pgset-signature' => lang('Signature Time Stamp'),
            ),
            'keywords' => array(
                'pgset-features' => pg_settings_keywords(lang('feature, cart, comment, blog, calendar, form, ads, sharing, search, performance, mobile')),
                'pgset-images'   => pg_settings_keywords(lang('image, photo, optimization, webp, quality, size, upload, watermark')),
                'pgset-signature' => pg_settings_keywords(lang('signature, timestamp, time stamp, tsa, rfc 3161, credit, kamusm, kamu sm, e-guven, e-tugra, contract, evidence, proof')),
            ),
        ),

        'seo' => array(
            'label' => 'SEO',
            'icon'  => 'bi-graph-up-arrow',
            'description' => lang('Search engines, structured data and visitor tracking.'),
            'sections' => array(
                'pgset-seo'       => lang('Search Engine Optimization'),
                'pgset-analytics' => lang('Visitor Tracking'),
            ),
            'keywords' => array(
                'pgset-seo'       => pg_settings_keywords(lang('seo, meta, robots, sitemap, title, description, jsonld, json-ld, canonical, indexnow, sharing, social, og, merchant, structured data')),
                'pgset-analytics' => pg_settings_keywords(lang('visitor, analytics, tracking code, tracking, statistics, google analytics')),
            ),
        ),

        'contact' => array(
            'label' => lang('Communication'),
            'icon'  => 'bi-chat-dots',
            'description' => lang('E-mail campaigns, live chat and MailChimp.'),
            'sections' => array(
                'pgset-campaigns' => lang('Campaigns'),
                'pgset-chat'      => lang('Chat'),
                'pgset-mailchimp' => lang('MailChimp'),
            ),
            'keywords' => array(
                'pgset-campaigns' => pg_settings_keywords(lang('campaign, newsletter, subscribe, organization, address')),
                'pgset-chat'      => pg_settings_keywords(lang('chat, live support, operator, bubble, message')),
                'pgset-mailchimp' => pg_settings_keywords(lang('mailchimp, mail chimp, list, list id, api key, key, sync, automation, store')),
            ),
        ),

        'security' => array(
            'label' => lang('Security'),
            'icon'  => 'bi-shield-lock',
            'description' => lang('Passwords, devices, Google sign-in, registration and membership.'),
            'sections' => array(
                'pgset-session'    => lang('Session & Password'),
                'pgset-device'     => lang('Device'),
                'pgset-signin'     => lang('Sign in with Google'),
                'pgset-membership' => lang('Registration & Membership'),
            ),
            'keywords' => array(
                'pgset-session'    => pg_settings_keywords(lang('security, password, session, captcha, login, throttle, bulk delete')),
                'pgset-device'     => pg_settings_keywords(lang('device, remember me, remember, limit')),
                'pgset-signin'     => pg_settings_keywords(lang('google, oauth, sign in with google, sso, client id, client secret')),
                'pgset-membership' => pg_settings_keywords(lang('membership, registration, sign up, signup, approval, verification, member number')),
            ),
        ),

        'firewall' => array(
            'label' => lang('Firewall'),
            'icon'  => 'bi-shield-exclamation',
            'description' => lang('The request filter, bot policy and address lists.'),
            'sections' => array(
                'pgset-waf'     => lang('Firewall'),
                'pgset-bots'    => lang('Bot Filtering'),
                'pgset-iplists' => lang('IP Lists'),
                'pgset-headers' => lang('HTTPS and Security Headers'),
            ),
            'keywords' => array(
                'pgset-waf'     => pg_settings_keywords(lang('waf, firewall, attack, signature, rate limit, ban')),
                'pgset-bots'    => pg_settings_keywords(lang('bot, crawler, googlebot, ai, gptbot, claudebot')),
                'pgset-iplists' => pg_settings_keywords(lang('ip, ip block, block, allow, allow list, blocked email, banned')),
                'pgset-headers' => pg_settings_keywords(lang('https, ssl, secure mode, certificate, header, csp, content security policy, hsts, frame, clickjacking, fail2ban, iptables, nosniff, referrer')),
            ),
        ),

        'commerce' => array(
            'label' => lang('Ecommerce'),
            'icon'  => 'bi-shop',
            'description' => lang('Store, shipping, gift cards, e-invoice, payment and affiliates.'),
            'sections' => array(
                'pgset-store'     => lang('Store'),
                'pgset-shipping'  => lang('Shipping'),
                'pgset-giftcards' => lang('Gift Cards & Rewards'),
                'pgset-invoice'   => lang('E-Invoice'),
                'pgset-erp'       => lang('ERP'),
                'pgset-payments'  => lang('Payment Methods'),
                'pgset-affiliate' => lang('Affiliate Program'),
            ),
            'keywords' => array(
                'pgset-store'     => pg_settings_keywords(lang('store, commerce, ecommerce, order number, tax, vat, barcode, currency')),
                'pgset-shipping'  => pg_settings_keywords(lang('shipping, cargo, delivery, ups, fedex, usps, recipient, address verification')),
                'pgset-giftcards' => pg_settings_keywords(lang('gift card, points, reward, givex')),
                'pgset-invoice'   => pg_settings_keywords(lang('invoice, e-invoice, e-archive, e-document, provider, parasut, isbasi, logo, accounting')),
                'pgset-erp'       => pg_settings_keywords(lang('erp, account, cash, bank, bookkeeping, waybill, collection, overdue, reminder')),
                'pgset-payments'  => pg_settings_keywords(lang('payment, card, credit card, iyzico, iyzipay, paypal, stripe, installment, wire transfer, cash on delivery, 3d secure')),
                'pgset-affiliate' => pg_settings_keywords(lang('affiliate, commission, referral')),
            ),
        ),

        'api' => array(
            'label' => lang('API'),
            'icon'  => 'bi-plug',
            'description' => lang('The application API: the master switch, HTTPS, the public description, log retention and the upload folder.'),
            'sections' => array(
                'pgset-api'         => lang('Application API'),
                'pgset-api-storage' => lang('Uploads & Logs'),
            ),
            'keywords' => array(
                'pgset-api'         => pg_settings_keywords(lang('api, application, key, https, openapi, swagger, webhook, integration')),
                'pgset-api-storage' => pg_settings_keywords(lang('log, retention, upload folder, file')),
            ),
        ),

    );

    // The e-invoice card carries Turkish e-document providers only; outside
    // Turkey, with none in use, it is left out of the list (the card is still
    // drawn, hidden, so a save never finds its fields missing).
    if (function_exists('pg_edoc_settings_shown') && !pg_edoc_settings_shown()) {
        unset($categories['commerce']['sections']['pgset-invoice'], $categories['commerce']['keywords']['pgset-invoice']);
        $categories['commerce']['description'] = lang('Store, shipping, gift cards, payment and affiliates.');
    }

    return $categories;
}


/**
 * One category, or null when the key is not one of ours.
 *
 * @param string $key
 * @return array|null
 */
function pg_settings_category($key)
{
    $categories = pg_settings_categories();

    return isset($categories[$key]) ? $categories[$key] : null;
}


/**
 * The script that renders a category.
 *
 * @param string $key
 * @return string
 */
function pg_settings_url($key)
{
    return 'settings_' . $key . '.php';
}


/**
 * Where a link written before the split should land.
 *
 * A #hash never reaches the server, so an old settings.php#pgset-waf cannot be
 * redirected here; the hub carries a script that reads the fragment and uses
 * this map. The known callers inside the software were updated as well -- this
 * is for the bookmarks, and for any link that was missed.
 *
 * @return array old section id => array(category key, section id there)
 */
function pg_settings_legacy_anchors()
{
    return array(
        'pgset-general'    => array('general',    'pgset-server'),
        'pgset-datetime'   => array('general',    'pgset-datetime'),
        'pgset-theme'      => array('appearance', 'pgset-theme'),
        'pgset-editor'     => array('appearance', 'pgset-editor'),
        'pgset-features'   => array('features',   'pgset-features'),
        'pgset-images'     => array('features',   'pgset-images'),
        'pgset-signature'  => array('features',   'pgset-signature'),
        'pgset-seo'        => array('seo',        'pgset-seo'),
        'pgset-analytics'  => array('seo',        'pgset-analytics'),
        'pgset-campaigns'  => array('contact',    'pgset-campaigns'),
        'pgset-chat'       => array('contact',    'pgset-chat'),
        'pgset-security'   => array('security',   'pgset-session'),
        'pgsub-devices'    => array('security',   'pgset-device'),
        'pgsub-signin'     => array('security',   'pgset-signin'),
        'pgset-membership' => array('security',   'pgset-membership'),
        'pgset-waf'        => array('firewall',   'pgset-waf'),
        'pgsub-bots'       => array('firewall',   'pgset-bots'),
        'pgsub-iplists'    => array('firewall',   'pgset-iplists'),
        'pgsub-headers'    => array('firewall',   'pgset-headers'),
        'pgset-ecommerce'  => array('commerce',   'pgset-store'),
        'pgsub-store'      => array('commerce',   'pgset-store'),
        'pgsub-shipping'   => array('commerce',   'pgset-shipping'),
        'pgsub-giftcards'  => array('commerce',   'pgset-giftcards'),
        'pgsub-invoice'    => array('commerce',   'pgset-invoice'),
        'pgsub-payments'   => array('commerce',   'pgset-payments'),
        'pgset-affiliate'  => array('commerce',   'pgset-affiliate'),
        'pgset-cron'       => array('general',    'pgset-cron'),
    );
}


/**
 * The neighbouring screens, grouped the way the Settings menu groups them.
 *
 * The groups are the point: what the operator is looking for is either a screen
 * that WATCHES the site, one that CONNECTS it to something, a job that CHANGES
 * something, or one that keeps the INFRASTRUCTURE. Every gate here is the gate
 * the menu already applied, so the hub and the menu show the same rows.
 *
 * @param array $user the row validate_user() returned
 * @return array group label => array of array(label, icon, url, target, confirm)
 */
function pg_settings_tool_groups($user)
{
    $role = isset($user['role']) ? (int) $user['role'] : 3;

    $ecommerce = (defined('ECOMMERCE') && ECOMMERCE === true);

    $groups = array();

    // Watching: the log and who is signed in.
    $groups[] = array(
        'label' => lang('Monitoring'),
        'icon'  => 'bi-activity',
        'items' => array(
            array('label' => lang('Site Log'), 'icon' => 'bi-journal-text', 'url' => 'view_log.php'),
            array('label' => lang('Sessions'), 'icon' => 'bi-shield-lock',  'url' => 'view_sessions.php'),
        ),
    );

    // Connections: mail, lists, apps.
    $connections = array(
        array('label' => lang('SMTP Settings'),      'icon' => 'bi-envelope-at', 'url' => 'smtp_settings.php'),
        array('label' => lang('Application Access'), 'icon' => 'bi-key',         'url' => 'api_settings.php'),
    );

    if ($ecommerce) {
        $connections[] = array('label' => lang('Marketplaces'), 'icon' => 'bi-shop', 'url' => 'marketplace_settings.php');
    }

    $groups[] = array('label' => lang('Connections'), 'icon' => 'bi-plug', 'items' => $connections);

    // Commerce lists. USER_MANAGE_ECOMMERCE is the panel's ecommerce rule
    // (role < 3, or a role-3 user carrying the flag); the menu has always
    // applied it together with the feature switch.
    if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE && $ecommerce) {
        $groups[] = array(
            'label' => lang('Ecommerce'),
            'icon'  => 'bi-shop',
            'items' => array(
                array('label' => lang('All Referral Sources'), 'icon' => 'bi-signpost-2',        'url' => 'view_referral_sources.php'),
                array('label' => lang('All Currencies'),       'icon' => 'bi-currency-exchange', 'url' => 'view_currencies.php'),
            ),
        );
    }

    // Jobs. The ones that change or remove something sit here together, and
    // they wear the same colour as every other row: severity is carried by the
    // icon and by the confirmation, not by a tint.
    $tools = array(
        array('label' => lang('Check for Updates'),   'icon' => 'bi-arrow-repeat', 'url' => 'software_update.php'),
        array('label' => lang('System Informations'), 'icon' => 'bi-info-circle',  'url' => 'si.php', 'target' => '_blank'),
    );

    // Only while monitoring is on: with it off the screen is empty, and a
    // control that leads nowhere is worse than no control.
    if (defined('PERF_MONITOR_ENABLED') && (((int) PERF_MONITOR_ENABLED) === 1)) {
        $tools[] = array('label' => lang('Performance Log'), 'icon' => 'bi-stopwatch', 'url' => 'view_performance_log.php');
    }

    $tools[] = array('label' => lang('Clean Up'), 'icon' => 'bi-eraser', 'url' => 'clean_up.php');

    $tools[] = array(
        'label'   => lang('Purge Cache'),
        'icon'    => 'bi-trash3',
        'url'     => 'purge_cache.php?token=' . urlencode((string) (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '')),
        'confirm' => lang('All server-side caches will be cleared.'),
    );

    // Reinstall needs the installer to still be on disk; once it is deleted
    // (which the panel recommends) the link would 404.
    if (is_dir(PG_FUNCTIONS_DIR . '/install')) {
        $tools[] = array('label' => lang('Reinstall or Upgrade'), 'icon' => 'bi-box-arrow-in-down', 'url' => 'install');
    }

    $groups[] = array('label' => lang('Jobs'), 'icon' => 'bi-tools', 'items' => $tools);

    // Infrastructure: backups, and Cloudflare when it is configured.
    $infrastructure = array(
        array('label' => lang('Backup Manager'), 'icon' => 'bi-archive', 'url' => 'backups.php'),
    );

    if (
        $role < 1
        && defined('CLOUDFLARE_API_TOKEN') && trim(CLOUDFLARE_API_TOKEN) != ''
        && defined('CLOUDFLARE_ZONE_ID') && trim(CLOUDFLARE_ZONE_ID) != ''
    ) {
        $infrastructure[] = array('label' => lang('Cloudflare Tools'), 'icon' => 'bi-cloud', 'url' => 'cloudflare.php');
    }

    if ($role == 0) {
        $infrastructure[] = array('label' => lang('Edit Config'), 'icon' => 'bi-file-earmark-code', 'url' => 'edit_config.php');
    }

    $groups[] = array('label' => lang('Infrastructure'), 'icon' => 'bi-hdd-stack', 'items' => $infrastructure);

    return $groups;
}


/**
 * "Last modified 5 hours ago by admin", or an empty string.
 *
 * Shown by the hub and by the category screens, from one place: the hub is
 * often opened to find out what somebody else changed, and the answer must not
 * differ between the screens that give it.
 *
 * @param array $row the config row
 * @return string
 */
function pg_settings_last_modified($row)
{
    $timestamp = isset($row['last_modified_timestamp']) ? (int) $row['last_modified_timestamp'] : 0;

    if ($timestamp <= 0) {
        return '';
    }

    $output = lang('Last modified') . ' ' . get_relative_time(array('timestamp' => $timestamp)) . ' ';

    $user_id = isset($row['last_modified_user_id']) ? (int) $row['last_modified_user_id'] : 0;

    if ($user_id > 0) {

        $username = db_value("SELECT user_username FROM user WHERE user_id = '" . $user_id . "'");

        if ($username != '') {
            $output .= lang(array('string' => 'by {var:1}', 'vars' => array(h($username))));
        }
    }

    return $output;
}


/**
 * The one line under a tile that says what state the category is in.
 *
 * Only facts that are already in the config row or in a constant: the hub is a
 * screen an operator crosses to get somewhere, and it should not run a query per
 * tile to decorate itself. A fact that cannot be had that cheaply is left out
 * rather than guessed at.
 *
 * @param string $key
 * @param array $row the config row
 * @return array array(state, text) where state is ok|warn|off|''
 */
function pg_settings_status($key, $row)
{
    $on = function ($column) use ($row) {
        return (isset($row[$column]) && ((string) $row[$column] === '1'));
    };

    switch ($key) {

        case 'general':
            $channel = ((isset($row['software_update_channel']) && $row['software_update_channel'] === 'beta')
                ? lang('Beta') : lang('Stable'));
            return array(
                'ok',
                (isset($row['hostname']) && $row['hostname'] !== '' ? $row['hostname'] : lang('Hostname')) . ' · ' . $channel);

        case 'features':
            $library  = extension_loaded('imagick') ? 'Imagick' : (extension_loaded('gd') ? 'GD' : '');
            $optimize = (!isset($row['image_product_optimize']) || $on('image_product_optimize'));
            return array(
                ($library === '') ? 'warn' : 'ok',
                ($library === '')
                    ? lang('No image library is installed')
                    : (($optimize ? lang('Product images are optimized') : lang('Product images are not optimized')) . ' (' . $library . ')'));

        case 'seo':
            $indexnow = (isset($row['indexnow_key']) && trim((string) $row['indexnow_key']) !== '');
            return array(
                'ok',
                ($indexnow ? lang('IndexNow is on') : lang('IndexNow is off'))
                . ' · ' . ($on('visitor_tracking') ? lang('Visitor tracking is on') : lang('Visitor tracking is off')));

        case 'contact':
            return array('ok', $on('chat_enabled') ? lang('Chat is on') : lang('Chat is off'));

        case 'security':
            return array(
                'ok',
                ($on('captcha') ? lang('CAPTCHA is on') : lang('CAPTCHA is off'))
                . ' · ' . ($on('oauth_google_enabled') ? lang('Google sign-in is on') : lang('Google sign-in is off')));

        case 'firewall':
            $enabled = (!isset($row['waf_enabled']) || $on('waf_enabled'));
            $mode    = (isset($row['waf_mode']) && $row['waf_mode'] === 'monitor') ? lang('Monitor') : lang('Block');
            $secure  = (isset($row['url_scheme']) && $row['url_scheme'] === 'https://');
            return array(
                ($enabled && $secure) ? 'ok' : 'warn',
                ($enabled ? lang('Firewall is on') : lang('Firewall is off')) . ' · ' . $mode
                    . ' · ' . ($secure ? lang('Secure Mode is on') : lang('Secure Mode is off')));

        case 'commerce':
            if (!$on('ecommerce')) {
                return array('off', lang('Ecommerce is off'));
            }
            $methods = 0;
            foreach (array('ecommerce_credit_debit_card', 'ecommerce_offline_payment', 'ecommerce_paypal_express_checkout', 'ecommerce_pay_with_iyzico', 'ecommerce_gift_card') as $method) {
                if ($on($method)) $methods++;
            }
            return array(
                'ok',
                lang('Ecommerce is on') . ' · ' . lang(array(
                    'string' => '{var:1} payment method{suffix:1}',
                    'vars'   => $methods,
                    'suffix' => (($methods == 1) ? '' : 's'))));

        case 'api':
            if (!isset($row['api_enabled'])) {
                return array('', '');
            }
            if (!$on('api_enabled')) {
                return array('off', lang('API is off'));
            }
            return array(
                'ok',
                lang('API is on') . ' · ' . ($on('api_require_https') ? lang('HTTPS required') : lang('HTTP allowed')));

        case 'system':
            $auto = $on('job_dispatch_enabled');
            $jobs = 0;
            if (isset($row['job_dispatch']) && trim((string) $row['job_dispatch']) !== '') {
                $jobs = count(array_filter(array_map('trim', explode(',', (string) $row['job_dispatch']))));
            }
            return array(
                $auto ? 'ok' : 'off',
                ($auto ? lang('Jobs run automatically') : lang('Jobs are scheduled by hand'))
                . (($auto && $jobs > 0) ? ' · ' . lang(array('string' => '{var:1} job{suffix:1}', 'vars' => $jobs, 'suffix' => (($jobs == 1) ? '' : 's'))) : ''));
    }

    // Appearance has no state worth a line: what it holds is what it looks
    // like, and the tile already shows that.
    return array('', '');
}


/**
 * Two or three facts about a category, for its tile on the hub.
 *
 * Read straight off the config row, like the status line and for the same
 * reason: no query per tile.
 *
 * @param string $key
 * @param array $row the config row
 * @return array label => value, already plain text
 */
function pg_settings_facts($key, $row)
{
    $dash = lang('Not Selected');

    $value = function ($column, $fallback) use ($row) {
        $v = isset($row[$column]) ? trim((string) $row[$column]) : '';
        return ($v === '') ? $fallback : $v;
    };

    $yesno = function ($column) use ($row) {
        return (isset($row[$column]) && ((string) $row[$column] === '1')) ? lang('On') : lang('Off');
    };

    switch ($key) {

        case 'general':
            return array(
                lang('Hostname')               => $value('hostname', $dash),
                lang('Support E-mail Address') => $value('email_address', $dash),
                lang('Timezone')               => $value('timezone', $dash),
            );

        case 'appearance':
            return array(
                lang('Rich-text Editor')                  => $value('page_editor_version', $dash),
                lang('Enable Transparent Acrylic Effect') => $yesno('advanced_visual_effects'),
            );

        case 'features':
            return array(
                lang('Site Search Type')              => $value('search_type', $dash),
                lang('Product Image Limit')           => $value('image_product_max_dimension', $dash) . ' px',
                lang('Enable Performance Monitoring') => $yesno('perf_monitor'),
                lang('Workspace')                     => $yesno('workspace_enabled'),
            );

        case 'seo':
            return array(
                lang('Site Title')      => $value('title', $dash),
                lang('Structured Data') => $yesno('strutured_data'),
            );

        case 'contact':
            return array(
                lang('Organization Name') => $value('organization_name', $dash),
                lang('Chat')              => $yesno('chat_enabled'),
            );

        case 'security':
            return array(
                lang('Strong Password')   => $yesno('strong_password'),
                lang('Allow Remember Me') => $yesno('remember_me'),
                lang('Member ID Label')   => $value('member_id_label', $dash),
            );

        case 'firewall':
            return array(
                lang('Secure Mode')        => (isset($row['url_scheme']) && $row['url_scheme'] === 'https://') ? lang('Yes') : lang('No'),
                lang('Sensitivity')        => $value('waf_sensitivity', $dash),
                lang('Block Unknown Bots') => $yesno('block_unknown_bots'),
            );

        case 'commerce':
            return array(
                lang('Payment Gateway') => $value('ecommerce_payment_gateway', $dash),
                lang('Tax')             => $yesno('ecommerce_tax'),
                lang('Shipping')        => $yesno('ecommerce_shipping'),
            );

        case 'api':
            return array(
                lang('Public API description') => $yesno('api_openapi_public'),
                lang('Log Retention')          => (isset($row['api_log_retention_days']) ? (int) $row['api_log_retention_days'] : 30) . ' ' . lang('day(s)'),
                lang('Upload Folder')          => ((isset($row['api_upload_folder_id']) && ((int) $row['api_upload_folder_id'] > 0)) ? lang('Selected') : $dash),
            );

        case 'system':
            $jobs = 0;
            if (isset($row['job_dispatch']) && trim((string) $row['job_dispatch']) !== '') {
                $jobs = count(array_filter(array_map('trim', explode(',', (string) $row['job_dispatch']))));
            }
            return array(
                lang('Run Cron Jobs Automatically') => $yesno('job_dispatch_enabled'),
                lang('Selected Jobs')               => (string) $jobs,
            );
    }

    return array();
}


/**
 * The config row, read once per request.
 *
 * Every settings surface reads the same row -- the modal's panes, the tiles,
 * the save -- and the row is one line of one table. Memoized so opening a pane
 * and saving it in the same request do not each go and ask.
 *
 * @param bool $again re-read after a write
 * @return array
 */
function pg_settings_config_row($again = false)
{
    static $row = null;

    if ($row === null || $again) {

        $result = mysqli_query(db::$con, "SELECT * FROM config");

        if (!$result) {
            output_error('Query failed.');
        }

        $row = mysqli_fetch_assoc($result);
    }

    return $row;
}
