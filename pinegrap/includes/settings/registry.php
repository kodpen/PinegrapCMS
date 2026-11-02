<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one list of settings screens: the categories the site settings are split
 * into, and the neighbouring screens that live beside them.
 *
 * Read by four places -- the dialog's sidebar and the link helper
 * (includes/fn/output.php), the main menu's System tools row
 * (output_menu(), same file), the category screens
 * (includes/settings/screen.php) and the command palette (api.php). One list, because they all name the same
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
 * Every term comes back a string. The repeats are dropped through the keys of
 * an array, and PHP turns a key that reads as a number ("503") into an
 * integer; sent on as a number, it broke the command palette, whose matcher
 * lower-cases every term and stopped at the first that had no toLowerCase().
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

    return array_map('strval', array_keys($keywords));
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
 * A category whose cards draw a field as a list names it under 'lists', with
 * how many levels of keys it carries; every other field holds one value and a
 * save that posts it as a list is refused (pg_settings_refuse_lists()). A
 * card that moves takes its list fields with it.
 *
 * 'group' names the heading the category sits under in the dialog's list
 * (pg_settings_groups()). The groups follow the order of this list, so a
 * category joins a group by its place here as well as by its key.
 *
 * @return array key => array(label, group, icon, description, sections, keywords, lists)
 */
function pg_settings_categories()
{
    $categories = array(

        'general' => array(
            'label' => lang('General'),
            'group' => 'site',
            'icon'  => 'bi-sliders',
            'description' => lang('The organization, server, domain, licence, and date and time.'),
            'sections' => array(
                'pgset-organization' => lang('Organization'),
                'pgset-server'   => lang('Server & Domain'),
                'pgset-software' => lang('Software'),
                'pgset-datetime' => lang('Date & Time'),
            ),
            'keywords' => array(
                'pgset-organization' => pg_settings_keywords(lang('organization, company, name, address, logo, support, email, tax number, tax office, vkn, seller')),
                'pgset-server'   => pg_settings_keywords(lang('hostname, domain, ip, ssl, https, secure mode, secure, proxy')),
                'pgset-software' => pg_settings_keywords(lang('software, language, licence, license, subscription, error, debug')),
                'pgset-datetime' => pg_settings_keywords(lang('date, time, timezone, format')),
            ),
        ),

        'appearance' => array(
            'label' => lang('Appearance'),
            'group' => 'site',
            'icon'  => 'bi-brush',
            'description' => lang('The panel theme, the dashboard and the rich-text editor.'),
            'sections' => array(
                'pgset-theme'     => lang('Theme'),
                'pgset-dashboard' => lang('Dashboard'),
                'pgset-editor'    => lang('Rich-text Editor'),
                'pgset-private-label' => lang('Private Label Branding'),
            ),
            'keywords' => array(
                'pgset-theme'     => pg_settings_keywords(lang('theme, appearance, color, custom css, acrylic')),
                'pgset-dashboard' => pg_settings_keywords(lang('dashboard, widget, card style, backdrop, refresh, reset widgets, welcome')),
                'pgset-editor'    => pg_settings_keywords(lang('editor, text, wysiwyg, rich, font')),
                'pgset-private-label' => pg_settings_keywords(lang('private label, branding, white label, logo, stylesheet, footer links')),
            ),
        ),

        'features' => array(
            'label' => lang('Features'),
            'group' => 'site',
            'icon'  => 'bi-toggles',
            'description' => lang('Which features are on, and how images are handled.'),
            'sections' => array(
                'pgset-features' => lang('Feature Options'),
                'pgset-images'   => lang('Image Optimization'),
                'pgset-revisions' => lang('Page Revisions'),
                'pgset-page-cache' => lang('Page Cache'),
                'pgset-signature' => lang('Signature Time Stamp'),
            ),
            'keywords' => array(
                'pgset-features' => pg_settings_keywords(lang('feature, cart, comment, blog, calendar, form, ads, sharing, search, performance, mobile, prefetch, speculation rules, preload')),
                'pgset-images'   => pg_settings_keywords(lang('image, photo, optimization, webp, avif, responsive, quality, size, upload, watermark')),
                'pgset-revisions' => pg_settings_keywords(lang('revision, revisions, history, version, versions, restore, retention, page, designer')),
                'pgset-page-cache' => pg_settings_keywords(lang('cache, page cache, cdn, proxy, varnish, nginx, cloudflare, speed, performance, s-maxage, cache-control')),
                'pgset-signature' => pg_settings_keywords(lang('signature, timestamp, time stamp, tsa, rfc 3161, credit, kamusm, kamu sm, e-guven, e-tugra, contract, evidence, proof')),
            ),
        ),

        'seo' => array(
            'label' => 'SEO',
            'group' => 'site',
            'icon'  => 'bi-search',
            'description' => lang('Search engines, the site icon, social sharing, structured data and AI crawlers.'),
            'sections' => array(
                'pgset-seo'       => lang('Search Engine Optimization'),
                'pgset-site-icon' => lang('Site Icon'),
                'pgset-social'    => lang('Social Sharing'),
                'pgset-structured' => lang('Structured Data'),
                'pgset-ai'        => lang('AI Crawlers'),
            ),
            'keywords' => array(
                'pgset-seo'       => pg_settings_keywords(lang('seo, meta, robots, sitemap, title, description, canonical, indexnow')),
                'pgset-site-icon' => pg_settings_keywords(lang('site icon, favicon, app icon, installed application, home screen')),
                'pgset-social'    => pg_settings_keywords(lang('sharing, social, og, open graph, share image')),
                'pgset-structured' => pg_settings_keywords(lang('structured data, jsonld, json-ld, schema, merchant, shipping, returns')),
                'pgset-ai'        => pg_settings_keywords(lang('ai, artificial intelligence, llms.txt, robots, crawler, bot, gptbot, claudebot, chatgpt, content signal, training')),
            ),
        ),

        'privacy' => array(
            'label' => lang('Visitors and privacy'),
            'group' => 'site',
            'icon'  => 'bi-incognito',
            'description' => lang('Visitor tracking, cookie consent, the checkout\'s legal documents and the accessibility statement.'),
            'sections' => array(
                'pgset-analytics' => lang('Visitor Tracking'),
                'pgset-cookies'   => lang('Cookie Consent'),
                'pgset-legal'     => lang('Legal Documents'),
                'pgset-a11y-statement' => lang('Accessibility Statement'),
            ),
            'keywords' => array(
                'pgset-analytics' => pg_settings_keywords(lang('visitor, analytics, tracking code, tracking, statistics, google analytics')),
                'pgset-cookies'   => pg_settings_keywords(lang('cookie, cookies, consent, gdpr, kvkk, privacy, cookie policy, banner, notice')),
                'pgset-legal'     => pg_settings_keywords(lang('legal, contract, distance sales contract, pre-information form, terms, withdrawal, consumer')),
                'pgset-a11y-statement' => pg_settings_keywords(lang('accessibility, statement, accessibility statement, wcag, en 301 549, european accessibility act, disability')),
            ),
        ),

        'languages' => array(
            'label' => lang('Languages and Translation'),
            'group' => 'site',
            'icon'  => 'bi-globe',
            'description' => lang('The language the pages are written in, the languages they are served in, and the engines that translate them.'),
            'sections' => array(
                'pgset-languages' => lang('Site Languages'),
                'pgset-language-redirect' => lang('Automatic Language Redirect'),
                'pgset-translation-engines' => lang('Translation Engines'),
            ),
            'keywords' => array(
                'pgset-languages' => pg_settings_keywords(lang('language, languages, translation, translate, multilingual, prefix, hreflang, locale, source language')),
                'pgset-language-redirect' => pg_settings_keywords(lang('redirect, automatic, browser language, accept-language, location, country, geolocation, geo ip, visitor language')),
                'pgset-translation-engines' => pg_settings_keywords(lang('google translate, google cloud, api key, chrome, translator, engine, machine translation, attribution, style note')),
            ),
            'lists' => array(
                'languages' => 2,
            ),
        ),

        'contact' => array(
            'label' => lang('Communication'),
            'group' => 'site',
            'icon'  => 'bi-chat-dots',
            'description' => lang('E-mail campaigns, live chat, MailChimp and SMS.'),
            'sections' => array(
                'pgset-campaigns' => lang('Campaigns'),
                'pgset-chat'      => lang('Chat'),
                'pgset-mailchimp' => lang('MailChimp'),
                'pgset-sms'       => lang('SMS'),
                'pgset-sms-messages' => lang('SMS Messages'),
                'pgset-iys'       => lang('İYS'),
            ),
            'keywords' => array(
                'pgset-campaigns' => pg_settings_keywords(lang('campaign, newsletter, subscribe, opt-in, footer')),
                'pgset-chat'      => pg_settings_keywords(lang('chat, live support, operator, bubble, message')),
                'pgset-mailchimp' => pg_settings_keywords(lang('mailchimp, mail chimp, list, list id, api key, key, sync, automation, store')),
                'pgset-sms'       => pg_settings_keywords(lang('sms, text message, netgsm, sender name, header, msgheader, mobile, phone, iys, test message')),
                'pgset-sms-messages' => pg_settings_keywords(lang('sms, text message, order received, order shipped, order delivered, tracking number, template')),
                'pgset-iys'       => pg_settings_keywords(lang('iys, ileti yönetim sistemi, consent, permission, brand, marketing, opt-in, refusal')),
            ),
        ),

        'security' => array(
            'label' => lang('Accounts and sign-in'),
            'group' => 'security',
            'icon'  => 'bi-shield-lock',
            'description' => lang('Passwords, devices, sign-in with Google, Microsoft and Apple, registration and membership, spam protection and the encryption key.'),
            'sections' => array(
                'pgset-session'    => lang('Session & Password'),
                'pgset-captcha'    => lang('Spam Protection'),
                'pgset-device'     => lang('Device'),
                'pgset-mfa'        => lang('Two-Step Verification'),
                'pgset-audit'      => lang('Audit Trail'),
                'pgset-signin'     => lang('Sign in with Google, Microsoft and Apple'),
                'pgset-membership' => lang('Registration & Membership'),
                'pgset-encryption' => lang('Encryption Key'),
            ),
            'keywords' => array(
                'pgset-session'    => pg_settings_keywords(lang('security, password, session, captcha, login, throttle, bulk delete')),
                'pgset-captcha'    => pg_settings_keywords(lang('captcha, spam, robot, bot, turnstile, cloudflare, hcaptcha, site key, secret key, stopforumspam, form limits, blocked email')),
                'pgset-device'     => pg_settings_keywords(lang('device, remember me, remember, limit')),
                'pgset-mfa'        => pg_settings_keywords(lang('two-step, two factor, 2fa, totp, authenticator, mfa, verification code')),
                'pgset-audit'      => pg_settings_keywords(lang('audit, audit trail, changes, history, retention, kvkk, gdpr, who changed')),
                'pgset-signin'     => pg_settings_keywords(lang('google, oauth, sign in with google, sso, client id, client secret')
                    . ', ' . lang('microsoft, entra, azure, outlook, office 365, sign in with microsoft, sso')
                    . ', ' . lang('apple, icloud, sign in with apple, services id, team id, key id, p8')),
                'pgset-membership' => pg_settings_keywords(lang('membership, registration, sign up, signup, approval, verification, member number')),
                'pgset-encryption' => pg_settings_keywords(lang('encryption, encryption key, config.php, card number, two-step, secret, openssl')),
            ),
        ),

        'firewall' => array(
            'label' => lang('Firewall and HTTPS'),
            'group' => 'security',
            'icon'  => 'bi-bricks',
            'description' => lang('The request filter, trusted proxies, bot policy, address lists, HTTPS and security headers.'),
            'sections' => array(
                'pgset-waf'     => lang('Firewall'),
                'pgset-proxies' => lang('Trusted Proxies'),
                'pgset-bots'    => lang('Bot Filtering'),
                'pgset-iplists' => lang('IP Lists'),
                'pgset-countries' => lang('Country Restriction'),
                'pgset-headers' => lang('HTTPS and Security Headers'),
            ),
            'keywords' => array(
                'pgset-waf'     => pg_settings_keywords(lang('waf, firewall, attack, signature, rate limit, ban')),
                'pgset-proxies' => pg_settings_keywords(lang('proxy, trusted proxy, load balancer, cdn, x-forwarded-for, visitor address, real ip')),
                'pgset-bots'    => pg_settings_keywords(lang('bot, crawler, googlebot, ai, gptbot, claudebot')),
                'pgset-iplists' => pg_settings_keywords(lang('ip, ip block, block, allow, allow list, banned')),
                'pgset-countries' => pg_settings_keywords(lang('country, countries, geo, geoblocking, location, region, tor, block country, allow country')),
                'pgset-headers' => pg_settings_keywords(lang('https, ssl, secure mode, certificate, header, csp, content security policy, hsts, frame, clickjacking, fail2ban, iptables, nosniff, referrer')),
            ),
        ),

        'commerce' => array(
            'label' => lang('Ecommerce'),
            'group' => 'selling',
            'icon'  => 'bi-shop',
            'description' => lang('Store, shipping, gift cards, payment, product feeds and affiliates.'),
            'sections' => array(
                'pgset-store'     => lang('Store'),
                'pgset-shipping'  => lang('Shipping'),
                'pgset-giftcards' => lang('Gift Cards & Rewards'),
                'pgset-payments'  => lang('Payment Methods'),
                'pgset-reference-price' => lang('Reference Price'),
                'pgset-feeds'     => lang('Product Feeds'),
                'pgset-affiliate' => lang('Affiliate Program'),
            ),
            'keywords' => array(
                'pgset-store'     => pg_settings_keywords(lang('store, commerce, ecommerce, order number, tax, vat, barcode, currency')),
                'pgset-shipping'  => pg_settings_keywords(lang('shipping, cargo, delivery, ups, fedex, usps, recipient, address verification')),
                'pgset-giftcards' => pg_settings_keywords(lang('gift card, points, reward, givex')),
                'pgset-payments'  => pg_settings_keywords(lang('payment, card, credit card, iyzico, iyzipay, paypal, stripe, installment, wire transfer, cash on delivery, 3d secure')),
                'pgset-reference-price' => pg_settings_keywords(lang('reference price, price before discount, lowest price, 30 days, 10 days, compare-at price, omnibus, price label')),
                'pgset-feeds'     => pg_settings_keywords(lang('product feed, feed, merchant center, google shopping, openai, chatgpt, shopping agent, jsonl, csv, tsv')),
                'pgset-affiliate' => pg_settings_keywords(lang('affiliate, commission, referral')),
            ),
            'lists' => array(
                'product_feed_excluded_groups' => 1,
            ),
        ),

        'accounting' => array(
            'label' => lang('Accounting'),
            'group' => 'selling',
            'icon'  => 'bi-calculator',
            'description' => lang('The ERP, the e-document provider, the accounting rules and the document templates.'),
            'sections' => array(
                'pgset-erp'       => lang('ERP'),
                'pgset-invoice'   => lang('E-Invoice'),
                'pgset-erp-rules' => lang('Accounting rules'),
                'pgset-late-fee'  => lang('Late payment fee'),
                'pgset-efatura-threshold' => lang('e-Fatura threshold'),
                'pgset-erp-templates' => lang('Document Templates'),
            ),
            'keywords' => array(
                'pgset-erp'       => pg_settings_keywords(lang('erp, account, cash, bank, bookkeeping, waybill, collection, overdue, reminder, automatic invoice')),
                'pgset-invoice'   => pg_settings_keywords(lang('invoice, e-invoice, e-archive, e-document, provider, parasut, isbasi, accounting')),
                'pgset-erp-rules' => pg_settings_keywords(lang('accounting rules, vat, withholding, exemption code, zero-rated, expense category')),
                'pgset-late-fee'  => pg_settings_keywords(lang('late payment fee, late fee, interest, overdue, default interest')),
                'pgset-efatura-threshold' => pg_settings_keywords(lang('e-fatura, threshold, limit, gross sales, system status')),
                'pgset-erp-templates' => pg_settings_keywords(lang('template, invoice template, delivery note, reconciliation letter, pdf, print, html')),
            ),
            'lists' => array(
                'erp_fx_currencies' => 1,
            ),
        ),

        'api' => array(
            'label' => lang('Integrations'),
            'group' => 'running',
            'icon'  => 'bi-plug',
            'description' => lang('The application API, its uploads and logs, Google reviews, and the screens that connect the site to other services.'),
            'sections' => array(
                'pgset-api'         => lang('Application API'),
                'pgset-api-storage' => lang('Uploads & Logs'),
                'pgset-google-reviews' => lang('Google Reviews'),
                'pgset-integration-links' => lang('Related screens'),
            ),
            'keywords' => array(
                'pgset-api'         => pg_settings_keywords(lang('api, application, key, https, openapi, swagger, webhook, integration')),
                'pgset-api-storage' => pg_settings_keywords(lang('log, retention, upload folder, file')),
                'pgset-google-reviews' => pg_settings_keywords(lang('google reviews, reviews, rating, google maps, places api, place id, business profile')),
                'pgset-integration-links' => pg_settings_keywords(lang('application access, connected accounts, oauth, marketplaces, workspace settings, integration')),
            ),
        ),

        'maintenance' => array(
            'label' => lang('Updates and maintenance'),
            'group' => 'running',
            'icon'  => 'bi-wrench-adjustable',
            'description' => lang('The update channel, scheduled jobs, backups, operations alerts and maintenance mode.'),
            'sections' => array(
                'pgset-channel'     => lang('Update Channel'),
                'pgset-cron'        => lang('Cron Jobs'),
                'pgset-backup'      => lang('Backups'),
                'pgset-alerts'      => lang('Alerts'),
                'pgset-maintenance' => lang('Maintenance Mode'),
            ),
            'keywords' => array(
                'pgset-channel'     => pg_settings_keywords(lang('update, channel, beta, stable, version')),
                'pgset-cron'        => pg_settings_keywords(lang('cron, scheduled, task, job, schedule, automatic')),
                'pgset-backup'      => pg_settings_keywords(lang('backup, zip, ftp, s3, retention, remote, archive')),
                'pgset-alerts'      => pg_settings_keywords(lang('alert, alerts, notification, e-mail, push, monitoring, backup failed, cron stopped, integrity, warning')),
                'pgset-maintenance' => pg_settings_keywords(lang('maintenance, offline, closed, 503, coming soon, health, uptime, monitor')),
            ),
            'lists' => array(
                'job_dispatch_job' => 1,
            ),
        ),

    );

    // The e-invoice card carries Turkish e-document providers only, and the
    // e-Fatura threshold is a Turkish rule; outside Turkey, with no provider
    // in use, both are left out of the list (the cards are still drawn,
    // hidden, so a save never finds their fields missing).
    if (function_exists('pg_edoc_settings_shown') && !pg_edoc_settings_shown()) {
        unset($categories['accounting']['sections']['pgset-invoice'], $categories['accounting']['keywords']['pgset-invoice']);
        unset($categories['accounting']['sections']['pgset-efatura-threshold'], $categories['accounting']['keywords']['pgset-efatura-threshold']);
        $categories['accounting']['description'] = lang('The ERP, the accounting rules and the document templates.');
    }

    // The Dashboard card is drawn for designers and administrators only
    // (pg_dashboard_appearance_allowed()); for anyone else, a manager opening
    // Appearance included, no chip or search result points at it.
    if (!pg_dashboard_appearance_allowed(defined('USER_ROLE') ? USER_ROLE : null)) {
        unset($categories['appearance']['sections']['pgset-dashboard'], $categories['appearance']['keywords']['pgset-dashboard']);
        $categories['appearance']['description'] = lang('The panel theme and the rich-text editor.');
    }

    // Private Label Branding is drawn and saved for administrators only, as
    // private_label.php was; for anyone else no chip or search result points
    // at it. An administrator also sees the Dashboard card, so the
    // description names all four cards.
    if (!defined('USER_ROLE') || ((int) USER_ROLE !== 0)) {
        unset($categories['appearance']['sections']['pgset-private-label'], $categories['appearance']['keywords']['pgset-private-label']);
    } else {
        $categories['appearance']['description'] = lang('The panel theme, the dashboard, the rich-text editor and private label branding.');
    }

    return $categories;
}


/**
 * The headings over the dialog's list of categories, in their order.
 *
 * Labels, not links: they only break the list into landmarks, which matters
 * most in the phone drawer, where thirteen rows have nothing else to hold on
 * to. A category names its heading with the 'group' key of
 * pg_settings_categories(); the System tools row closes the last one.
 *
 * @return array key => label
 */
function pg_settings_groups()
{
    return array(
        'site'     => lang('Site'),
        'security' => lang('Security'),
        'selling'  => lang('Selling'),
        'running'  => lang('Running the site'),
    );
}


/**
 * The categories under their headings.
 *
 * A heading with no category the reader may open is left out; one with a
 * single category keeps its heading. A category that names no known group
 * goes under the last heading rather than out of the list.
 *
 * @param array $categories as pg_settings_categories() returns them
 * @return array group key => array(label, categories: list of category keys)
 */
function pg_settings_grouped($categories)
{
    $groups = pg_settings_groups();
    $keys = array_keys($groups);
    $last = end($keys);
    $grouped = array();

    foreach ($groups as $group => $label) {
        $grouped[$group] = array('label' => $label, 'categories' => array());
    }

    foreach ($categories as $key => $category) {
        $group = (isset($category['group']) && isset($groups[$category['group']])) ? $category['group'] : $last;
        $grouped[$group]['categories'][] = $key;
    }

    return array_filter($grouped, function ($group) {
        return !empty($group['categories']);
    });
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
 * Refuse a save that posts a list in a field the cards draw as one value.
 *
 * The save modules read every other field as one value. A list stopped the
 * save with a TypeError in escape() or trim(), stored "Array" where (string)
 * read it (the host name, the firewall lists, Mailchimp's key) and counted as
 * ticked in a checkbox. "Not given" is no answer here either: a module writes
 * an empty value for a field it does not find. So the save is refused the way
 * a module refuses a field it marks -- every field is marked, nothing of the
 * category is written and every stored setting stays as it was. Only a
 * hand-made request gets here; the cards never post a list where they draw
 * none.
 *
 * Runs before the category's module, in the dialog's endpoint and in the
 * page path alike.
 *
 * @param string $key
 * @param object $liveform
 * @return bool true when the save was refused
 */
function pg_settings_refuse_lists($key, $liveform)
{
    $category = pg_settings_category($key);

    // The page path's decoy field (includes/settings/screen.php) is posted by
    // every category.
    $lists = array('fake_user' => 1) + ((($category !== null) && isset($category['lists'])) ? $category['lists'] : array());

    foreach (pg_request_unexpected_lists($_POST, $lists) as $name) {
        $liveform->mark_error($name, lang(array('string' => 'Sorry, the value you entered for {var:1} is not valid.', 'vars' => h($name))));
    }

    return $liveform->check_form_errors();
}


/**
 * The fields a refused save marked, by name, each with its sentence ('' when
 * it was marked without one), so the dialog can mark them in view. Read from
 * the liveform's state: the form-level errors (_form_error_N) and the notice
 * and warning lists belong to no field and are left out.
 *
 * @param array $state the liveform's session entry
 * @return array field name => sentence
 */
function pg_settings_marked_fields($state)
{
    $fields = array();

    foreach ((array) $state as $name => $field) {

        if (!is_string($name) || ($name === 'notices') || ($name === 'warnings') || (strpos($name, '_form_error_') === 0)) {
            continue;
        }

        if (!is_array($field) || empty($field['error'])) {
            continue;
        }

        $fields[$name] = isset($field['error_message']) ? (string) $field['error_message'] : '';
    }

    return $fields;
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
        'pgsub-site-icon'  => array('seo',        'pgset-site-icon'),
        'pgsub-app'        => array('seo',        'pgset-site-icon'),
        'pgsub-social'     => array('seo',        'pgset-social'),
        'pgsub-structured' => array('seo',        'pgset-structured'),
        'pgset-feeds'      => array('commerce',   'pgset-feeds'),
        'pgset-analytics'  => array('privacy',    'pgset-analytics'),
        'pgset-cookies'    => array('privacy',    'pgset-cookies'),
        'pgset-legal'      => array('privacy',    'pgset-legal'),
        'pgset-campaigns'  => array('contact',    'pgset-campaigns'),
        'pgset-organization' => array('general',  'pgset-organization'),
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
        'pgsub-invoice'    => array('accounting', 'pgset-invoice'),
        'pgset-invoice'    => array('accounting', 'pgset-invoice'),
        'pgset-erp'        => array('accounting', 'pgset-erp'),
        'pgsub-payments'   => array('commerce',   'pgset-payments'),
        'pgset-affiliate'  => array('commerce',   'pgset-affiliate'),
        'pgset-channel'    => array('maintenance', 'pgset-channel'),
        'pgset-cron'       => array('maintenance', 'pgset-cron'),
        'pgset-alerts'     => array('maintenance', 'pgset-alerts'),
        'pgset-maintenance' => array('maintenance', 'pgset-maintenance'),
        'pgset-backup'     => array('maintenance', 'pgset-backup'),
        'pgset-google-reviews' => array('api',    'pgset-google-reviews'),
    );
}


/**
 * The screens the Integrations category links, each under its own gate.
 *
 * Application Access, Connected accounts and Marketplaces open for a manager
 * (validate_area_access($user, 'manager')), Marketplaces only while the store
 * is on; Workspace Settings for a member holding the workspace settings right
 * (ws_screen_gate()). The facts that need the database are passed in, or read
 * here when they are not, so the list can be tested without one.
 *
 * @param array $user  the row validate_user() returned
 * @param array $facts optional: 'ecommerce' and 'workspace_settings' (bool)
 * @return array of array(label, icon, url)
 */
function pg_settings_integration_links($user, $facts = null)
{
    $role = isset($user['role']) ? (int) $user['role'] : 3;

    // The settings dialog itself is a manager's door.
    if ($role > 2) {
        return array();
    }

    if (!is_array($facts)) {
        $facts = array(
            'ecommerce'          => (defined('ECOMMERCE') && ECOMMERCE === true),
            'workspace_settings' => pg_settings_workspace_settings_allowed($user),
        );
    }

    $links = array(
        array('label' => lang('Application Access'), 'icon' => 'bi-key',         'url' => 'api_settings.php'),
        array('label' => lang('Connected accounts'), 'icon' => 'bi-cloud-check', 'url' => 'connections.php'),
    );

    if (!empty($facts['ecommerce'])) {
        $links[] = array('label' => lang('Marketplaces'), 'icon' => 'bi-buildings', 'url' => 'marketplace_settings.php');
    }

    if (!empty($facts['workspace_settings'])) {
        $links[] = array('label' => lang('Workspace Settings'), 'icon' => 'bi-people', 'url' => 'workspace_settings.php');
    }

    return $links;
}


/**
 * Whether workspace_settings.php would open for this user: the module on and
 * installed, a member of the team with the settings right (ws_screen_gate()).
 *
 * @param array $user the row validate_user() returned
 * @return bool
 */
function pg_settings_workspace_settings_allowed($user)
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !defined('PG_FUNCTIONS_DIR')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if (!ws_ready() || !ws_enabled()) {
        return false;
    }

    $rights = ws_rights($user);

    return !empty($rights['member']) && !empty($rights['settings']);
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

    // Watching: the logs and who is signed in.
    $groups[] = array(
        'label' => lang('Monitoring'),
        'icon'  => 'bi-activity',
        'items' => array(
            array('label' => lang('Site Log'), 'icon' => 'bi-card-list', 'url' => 'view_log.php'),
            array('label' => lang('Audit trail'), 'icon' => 'bi-clipboard-check', 'url' => 'view_audit_log.php'),
            array('label' => lang('Sessions'), 'icon' => 'bi-laptop',    'url' => 'view_sessions.php'),
        ),
    );

    // Connections: mail, lists, apps.
    $connections = array(
        array('label' => lang('SMTP Settings'),      'icon' => 'bi-envelope-at', 'url' => 'smtp_settings.php'),
        array('label' => lang('Application Access'), 'icon' => 'bi-key',         'url' => 'api_settings.php'),
        array('label' => lang('Connected accounts'), 'icon' => 'bi-cloud-check', 'url' => 'connections.php'),
    );

    if ($ecommerce) {
        $connections[] = array('label' => lang('Marketplaces'), 'icon' => 'bi-buildings', 'url' => 'marketplace_settings.php');
    }

    $groups[] = array('label' => lang('Connections'), 'icon' => 'bi-plug', 'items' => $connections);

    // Commerce lists. USER_MANAGE_ECOMMERCE is the panel's ecommerce rule
    // (role < 3, or a role-3 user carrying the flag); the menu has always
    // applied it together with the feature switch.
    if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE && $ecommerce) {
        // Named apart from the Ecommerce category: the menu lists both as
        // entries of one panel.
        $groups[] = array(
            'label' => lang('Store Lists'),
            'icon'  => 'bi-bag',
            'items' => array(
                array('label' => lang('All Referral Sources'), 'icon' => 'bi-signpost-2',        'url' => 'view_referral_sources.php'),
                array('label' => lang('All Currencies'),       'icon' => 'bi-coin',              'url' => 'view_currencies.php'),
            ),
        );
    }

    // Jobs. The ones that change or remove something sit here together, and
    // they wear the same colour as every other row: severity is carried by the
    // icon and by the confirmation, not by a tint.
    // On a hosted site the hosting platform updates and repairs the software,
    // and both screens refuse (pg_hosted()).
    $tools = array();

    if (!pg_hosted()) {
        $tools[] = array('label' => lang('Check for Updates'), 'icon' => 'bi-arrow-repeat', 'url' => 'software_update.php');
    }

    // Writing the release package over the software is an administrator's
    // job, like the screen's own door.
    if (($role === 0) && !pg_hosted()) {
        $tools[] = array('label' => lang('Repair Software'), 'icon' => 'bi-bandaid', 'url' => 'software_repair.php');
    }

    $tools[] = array('label' => lang('System Informations'), 'icon' => 'bi-info-circle', 'url' => 'si.php', 'target' => '_blank');

    // Only while monitoring is on: with it off the screen is empty, and a
    // control that leads nowhere is worse than no control.
    if (defined('PERF_MONITOR_ENABLED') && (((int) PERF_MONITOR_ENABLED) === 1)) {
        $tools[] = array('label' => lang('Performance Log'), 'icon' => 'bi-stopwatch', 'url' => 'view_performance_log.php');
    }

    $tools[] = array('label' => lang('Clean Up'), 'icon' => 'bi-eraser', 'url' => 'clean_up.php');

    $tools[] = array('label' => lang('Database Engine'), 'icon' => 'bi-database-gear', 'url' => 'database_engine.php');

    $tools[] = array(
        'label'   => lang('Purge Cache'),
        'icon'    => 'bi-trash3',
        'url'     => 'purge_cache.php?token=' . urlencode((string) (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '')),
        'confirm' => lang('All server-side caches will be cleared.'),
    );

    $tools[] = array('label' => lang('Mail queue'), 'icon' => 'bi-envelope-arrow-up', 'url' => 'mail_queue.php');

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

    $groups[] = array('label' => lang('Infrastructure'), 'icon' => 'bi-hdd-stack', 'items' => $infrastructure);

    return $groups;
}


/**
 * Search words of each system tool, by its script, in Turkish and English.
 * A tool missing here is still found by its own label. The command palette
 * and the Site Settings search both read them.
 *
 * @return array script => list of words
 */
function pg_settings_tool_keys()
{
    return array(
        'system_tools.php'          => array('sistem araclari', 'sistem araçları', 'araclar', 'araçlar', 'system tools', 'tools', 'sis'),
        'view_log.php'              => array('log', 'kayit', 'site log', 'journal', 'akt'),
        'view_audit_log.php'        => array('denetim', 'denetim izi', 'audit', 'audit trail', 'kim degistirdi', 'den'),
        'view_sessions.php'         => array('oturum', 'oturumlar', 'session', 'sessions', 'giris yapan', 'otu'),
        'smtp_settings.php'         => array('smtp', 'mail ayar', 'email ayar', 'smtpayar'),
        'api_settings.php'          => array('uygulama erisimi', 'uygulama erişimi', 'api anahtar', 'application access', 'api key', 'token', 'uyg'),
        'connections.php'           => array('bagli hesap', 'connected account', 'google', 'microsoft', 'oauth', 'bagla', 'connect'),
        'marketplace_settings.php'  => array('pazaryeri', 'pazar yeri', 'marketplace', 'trendyol', 'hepsiburada', 'n11', 'paz'),
        'view_referral_sources.php' => array('yonlendiren', 'yönlendiren', 'referans kaynak', 'referral', 'referral source', 'yon'),
        'view_currencies.php'       => array('para', 'doviz', 'döviz', 'currency', 'cur'),
        'software_update.php'       => array('guncelle', 'güncelle', 'guncelleme', 'güncelleme', 'update', 'updates', 'gun'),
        'software_repair.php'       => array('onar', 'onarim', 'onarım', 'repair', 'yazilim onar', 'ona'),
        'si.php'                    => array('sistem bilgi', 'system info', 'php info', 'phpinfo', 'sunucu bilgi', 'server info'),
        'view_performance_log.php'  => array('performans', 'performance', 'yavas', 'slow', 'per'),
        'clean_up.php'              => array('temizle', 'temizlik', 'clean', 'clean up', 'eski dosya', 'tem'),
        'database_engine.php'       => array('veritabani', 'veritabanı', 'database', 'innodb', 'myisam', 'tablo onar', 'motor', 'vt'),
        'purge_cache.php'           => array('onbellek', 'önbellek', 'cache', 'purge', 'purge cache', 'temizle cache', 'onb'),
        'mail_queue.php'            => array('posta kuyrugu', 'posta kuyruğu', 'mail kuyruk', 'mail queue', 'queue', 'giden posta', 'kuy'),
        'install'                   => array('yeniden kur', 'kurulum', 'reinstall', 'upgrade', 'install', 'kur'),
        'backups.php'               => array('yedek', 'yedekler', 'backup', 'backups', 'bak', 'yed'),
        'cloudflare.php'            => array('cloudflare', 'cdn', 'clo'),
    );
}


/**
 * How many links the tool groups hold, over all groups.
 *
 * @param array $groups pg_settings_tool_groups()
 * @return int
 */
function pg_settings_tool_count($groups)
{
    $count = 0;

    foreach ((array) $groups as $group) {
        $count += isset($group['items']) ? count($group['items']) : 0;
    }

    return $count;
}


/**
 * The System tools screen's body: one card per group, its links under it.
 *
 * Each link keeps what the group gave it: its target, and its question
 * (data-confirm-content, which the panel's confirm handler asks before the
 * link is followed). A group with no link is not drawn.
 *
 * @param array  $groups   pg_settings_tool_groups()
 * @param string $base_url the software directory, ending in a slash
 * @return string
 */
function pg_settings_tool_cards($groups, $base_url)
{
    $output = '';

    foreach ((array) $groups as $group) {

        if (empty($group['items'])) {
            continue;
        }

        $links = '';

        foreach ($group['items'] as $item) {

            $target  = isset($item['target']) ? ' target="' . h($item['target']) . '" rel="noopener"' : '';
            $confirm = isset($item['confirm']) ? ' data-confirm-content="' . h($item['confirm']) . '"' : '';
            $away    = isset($item['target']) ? '<i class="bi bi-box-arrow-up-right ms-auto fg-secondary" aria-hidden="true"></i>' : '';

            $links .= '
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-5" href="' . h($base_url . $item['url']) . '"' . $target . $confirm . '>
                            <i class="bi ' . h($item['icon']) . '" aria-hidden="true"></i>
                            <span>' . h($item['label']) . '</span>' . $away . '
                        </a>';
        }

        $output .= '
            <div class="col-12 md:col-6 xl:col-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-5">
                        <i class="bi ' . h($group['icon']) . '" aria-hidden="true"></i>
                        <h2 class="h6 mb-0">' . h($group['label']) . '</h2>
                    </div>
                    <div class="list-group list-group-flush">' . $links . '
                    </div>
                </div>
            </div>';
    }

    return ($output === '') ? '' : '
        <div class="row g-8 my-12">' . $output . '
        </div>';
}


/**
 * The "System tools" row at the end of the dialog's list, or an empty string
 * when the user has no tool. It leaves the dialog, like the rows it replaced.
 *
 * @param array $groups pg_settings_tool_groups()
 * @return string
 */
function pg_settings_tools_link($groups)
{
    if (pg_settings_tool_count($groups) === 0) {
        return '';
    }

    return
        '<a class="pg-sm-link pg-sm-away" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/system_tools.php') . '">'
        . '<i class="bi bi-tools" aria-hidden="true"></i>'
        . '<span>' . h(lang('System tools')) . '</span>'
        . '<i class="bi bi-chevron-right pg-sm-out" aria-hidden="true"></i></a>';
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
