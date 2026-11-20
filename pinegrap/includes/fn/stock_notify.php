<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Back-in-stock notices: a visitor asks to be told when a product they
 * cannot buy for want of stock is back, and gets one e-mail when it is, or
 * one text message to the mobile number they gave while the notice can go
 * out by SMS. Kept in stock_notify (2026.11.0, 11.0.14): the product, the
 * address typed (or the account's, read when the notice goes out), phone
 * ('' for none), created_at, and sent_at, stamped once the notice went out.
 *
 * Asking (stock_notify.php, pg_stock_notify_request()): only for a product
 * the catalog shows that tracks stock, has none left and takes no back
 * order, the rule of the catalog widgets' __out_of_stock. One waiting
 * request per address and product, and per mobile number and product; an
 * address or a number may wait for at most PG_STOCK_NOTIFY_PENDING_LIMIT
 * products at once.
 *
 * Telling (pg_stock_notify_heard()): every announcer of inventory.changed
 * calls the listener - pg_announce() for the site's own writers (checkout,
 * local sale, product screens, barcode, bulk edit, file manager), the
 * Workspace and ERP documents, and the stock API after its own
 * announcement. A product import, a marketplace order import and an ERP
 * stock count announce nothing, so they mail nobody. When the product can be
 * bought again, each waiting row is claimed (sent_at stamped) and then
 * mailed, so two announcements at once mail it once. The mail goes to the
 * general job's queue; on a site whose job does not run it is sent in the
 * request, so fewer are taken there and the rest wait for the product's
 * next announcement.
 *
 * The waiting list of a full event date (11.0.248) lives in the same table:
 * a row with product_id 0 and the event and its date (recurrence_number) is
 * a request to be told when a place frees (pg_stock_notify_event_request()).
 * A product's notice reads only rows of its product id, an event's only rows
 * of product_id 0 (pg_stock_notify_waiting_where()), so neither mails the
 * other's requests. Places free when a cancelled order gives them back or
 * staff raise the spots (pg_stock_notify_event_freed()); everyone waiting for
 * the date is mailed once and nobody's place is held.
 *
 * A daily pass (pg_stock_notify_trim_daily(), from job.php) removes notices
 * sent a month ago and requests that waited a year; deleting an account or a
 * product removes its rows at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Requests accepted from one address (its waf_ip_subject()) in the window.
define('PG_STOCK_NOTIFY_WRITE_LIMIT', 10);
define('PG_STOCK_NOTIFY_WRITE_WINDOW', 600);

// The products one e-mail address, or one mobile number, may wait for at
// once.
define('PG_STOCK_NOTIFY_PENDING_LIMIT', 50);

// Notices taken per announcement: queued for the general job, or sent in the
// request when the job does not run.
define('PG_STOCK_NOTIFY_SEND_QUEUED', 500);
define('PG_STOCK_NOTIFY_SEND_INLINE', 10);

// How long a sent notice is kept (it answers "already" until then), how long
// a request waits, and the trim's batches.
define('PG_STOCK_NOTIFY_KEEP_SENT', 2592000);
define('PG_STOCK_NOTIFY_KEEP_WAITING', 31536000);
define('PG_STOCK_NOTIFY_TRIM_ROWS', 1000);
define('PG_STOCK_NOTIFY_TRIM_BATCHES', 20);

// The id of the hidden form a notice's "Stop waiting" button posts
// (pg_stock_notify_withdraw_form_html()).
define('PG_STOCK_NOTIFY_STOP_FORM', 'pg-stock-notify-stop');

/**
 * Whether the stock_notify table of 2026.11.0 (11.0.14) is there.
 *
 * @return bool
 */
function pg_stock_notify_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('stock_notify', 'email_address')
            && pg_schema_has('stock_notify', 'sent_at');
    }

    return $ready;
}

/**
 * Whether a product can be asked for: the catalog shows it, it tracks stock,
 * none is left and it takes no back order (the catalog widgets'
 * __out_of_stock).
 *
 * @param array|null $row enabled, inventory, inventory_quantity, backorder
 * @return bool
 */
function pg_stock_notify_product_out($row)
{
    if (!is_array($row) || !isset($row['enabled'], $row['inventory'], $row['inventory_quantity'])) {
        return false;
    }

    return ((string) $row['enabled'] === '1')
        && ((string) $row['inventory'] === '1')
        && ((int) $row['inventory_quantity'] <= 0)
        && empty($row['backorder']);
}

/**
 * Whether a product can be bought from stock again: the catalog shows it and
 * it has stock, or no longer tracks it. A back order is no stock.
 *
 * @param array|null $row enabled, inventory, inventory_quantity
 * @return bool
 */
function pg_stock_notify_product_back($row)
{
    if (!is_array($row) || !isset($row['enabled'], $row['inventory'], $row['inventory_quantity'])) {
        return false;
    }

    return ((string) $row['enabled'] === '1')
        && (((string) $row['inventory'] !== '1') || ((int) $row['inventory_quantity'] > 0));
}

/**
 * The address a request keeps: trimmed, a valid address on one line, within
 * the column's 100 characters.
 *
 * @param mixed $value
 * @return string '' when it is none
 */
function pg_stock_notify_email($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    if (($value === '') || (strlen($value) > 100) || (strpbrk($value, "\r\n") !== false) || !validate_email_address($value)) {
        return '';
    }

    return $value;
}

/**
 * The mobile number a request keeps for the text message: the international
 * form in digits (pg_sms_number()), '' when none was typed or it is no
 * number the SMS provider can text (pg_sms_reachable(): Netgsm texts Turkish
 * mobile numbers only). The text goes instead of the mail, so a number the
 * provider refuses only when the text is sent would leave the visitor told
 * nothing at all.
 *
 * @param mixed       $value
 * @param string|null $provider the provider asked, null for the one messages
 *                              go through (pg_sms_provider())
 * @return string
 */
function pg_stock_notify_phone($value, $provider = null)
{
    if (!is_string($value) || (trim($value) === '') || !function_exists('pg_sms_number') || !function_exists('pg_sms_reachable')) {
        return '';
    }

    $number = pg_sms_number($value);

    if ($number === '') {
        return '';
    }

    return pg_sms_reachable($number, ($provider === null) ? pg_sms_provider() : (string) $provider) ? $number : '';
}

/**
 * Whether a notice can go out as a text message: the Back in stock event of
 * the SMS Messages card is on and SMS is set up. Off, the form shows no
 * number field and a number sent anyway is not kept.
 *
 * @return bool
 */
function pg_stock_notify_sms_on()
{
    if (!function_exists('pg_sms_message_settings') || !function_exists('pg_sms_ready')) {
        return false;
    }

    $settings = pg_sms_message_settings();

    return !empty($settings['stock_back']['on']) && pg_sms_ready();
}

/**
 * Whether the request form asks for a mobile number: while the notice can go
 * out as a text message, or as a WhatsApp message (the WhatsApp Messages
 * card's Product back in stock event, pg_whatsapp_stock_back_on()).
 *
 * @return bool
 */
function pg_stock_notify_phone_asked()
{
    return pg_stock_notify_sms_on() || (function_exists('pg_whatsapp_stock_back_on') && pg_whatsapp_stock_back_on());
}

/**
 * Whether a notice goes out as a text message now: the SMS event is on and
 * the general job sends the SMS queue (pg_sms_job_alive()). Without the job
 * the text would only wait there and expire, so the sender mails instead,
 * and the visitor's answer has to say so.
 *
 * @return bool
 */
function pg_stock_notify_sms_sends()
{
    return pg_stock_notify_sms_on() && function_exists('pg_sms_job_alive') && pg_sms_job_alive();
}

/**
 * What a request comes to, in the order it is checked. A mobile number typed
 * is held like the address, whatever address comes with it: one waiting
 * request per product and PG_STOCK_NOTIFY_PENDING_LIMIT products, since each
 * one kept is a paid text message later.
 *
 * @param bool       $ready   pg_stock_notify_ready()
 * @param string     $email   pg_stock_notify_email()
 * @param array|null $product the product row, null when there is none
 * @param bool       $already the address waits for this product already
 * @param int        $waiting the products the address waits for
 * @param array|null $number  the mobile number typed, null for none:
 *                            reachable (a text message can reach it),
 *                            already (it waits for this product already),
 *                            waiting (the products it waits for)
 * @return string added, already, limit, in_stock, product, phone, email,
 *                unavailable
 */
function pg_stock_notify_request_status($ready, $email, $product, $already, $waiting, $number = null)
{
    // No number typed: nothing of it holds the request back.
    $number = is_array($number)
        ? $number + array('reachable' => false, 'already' => false, 'waiting' => 0)
        : array('reachable' => true, 'already' => false, 'waiting' => 0);

    if (!$ready) {
        return 'unavailable';
    }

    if ($email === '') {
        return 'email';
    }

    if (empty($number['reachable'])) {
        return 'phone';
    }

    if (!is_array($product) || ((string) ($product['enabled'] ?? '') !== '1')) {
        return 'product';
    }

    if (!pg_stock_notify_product_out($product)) {
        return 'in_stock';
    }

    if ($already || !empty($number['already'])) {
        return 'already';
    }

    if (max((int) $waiting, (int) $number['waiting']) >= PG_STOCK_NOTIFY_PENDING_LIMIT) {
        return 'limit';
    }

    return 'added';
}

/**
 * Whether an answer means the visitor will be told.
 *
 * @param string $status pg_stock_notify_request_status()
 * @return bool
 */
function pg_stock_notify_status_ok($status)
{
    return in_array($status, array('added', 'already'), true);
}

/**
 * The sentence the visitor reads for an answer.
 *
 * @param string $status pg_stock_notify_request_status()
 * @param bool   $by_sms the notice goes out as a text message
 * @return string
 */
function pg_stock_notify_status_message($status, $by_sms = false)
{
    switch ($status) {
        case 'added':
            return $by_sms
                ? lang('We will send you one text message when this product is back in stock.')
                : lang('We will send you one e-mail when this product is back in stock.');
        case 'already':
            return lang('You have already asked to be told when this product is back in stock.');
        case 'in_stock':
            return lang('This product is in stock now, so you can order it.');
        case 'product':
            return lang('Sorry, this product is not available right now.');
        case 'email':
            return lang('Please enter a valid e-mail address.');
        case 'phone':
            return lang('Please enter a mobile number a text message can reach, or leave it empty.');
        case 'limit':
            return lang('You are already waiting for many products. Please try again later.');
        case 'unavailable':
            return lang('Back-in-stock notices are not available on this site yet.');
    }

    return lang('Something went wrong. Please try again.');
}

/**
 * Keeps a request to be told when a product is back.
 *
 * @param int    $product_id
 * @param string $email   the address typed; '' takes the signed-in account's
 * @param int    $user_id the signed-in account, 0 for a guest
 * @param string $phone   the mobile number typed, '' for none; kept only
 *                        while the notice can go out as a text message and
 *                        the provider can text it
 * @param string $push_secret this browser's visitor notification secret,
 *                        '' for none: the device then waits for the notice
 *                        too (pg_stock_notify_push_watch())
 * @return string pg_stock_notify_request_status()
 */
function pg_stock_notify_request($product_id, $email, $user_id, $phone = '', $push_secret = '')
{
    $product_id = (int) $product_id;
    $user_id = max(0, (int) $user_id);
    $typed = is_string($phone) && (trim($phone) !== '') && pg_stock_notify_phone_asked();

    // Asked for WhatsApp alone, the number is any mobile number WhatsApp can
    // take; the SMS provider's own limit applies only while it texts.
    $phone = $typed ? (pg_stock_notify_sms_on() ? pg_stock_notify_phone($phone) : pg_sms_number($phone)) : '';

    if (!pg_stock_notify_ready()) {
        return 'unavailable';
    }

    if (($email === '') && ($user_id > 0) && defined('USER_EMAIL_ADDRESS')) {
        $email = (string) USER_EMAIL_ADDRESS;
    }

    $email = pg_stock_notify_email($email);
    $product = null;
    $already = false;
    $already_id = 0;
    $waiting = 0;
    $number = $typed ? array('reachable' => ($phone !== ''), 'already' => false, 'waiting' => 0) : null;

    if (($email !== '') && ($product_id > 0)) {
        $product = db_item("SELECT enabled, inventory, inventory_quantity, backorder FROM products WHERE id = '" . $product_id . "' LIMIT 1");

        // The address typed, or the account's own requests whatever address
        // it typed then.
        $already_id = (int) db_value(
            "SELECT id FROM stock_notify
            WHERE (product_id = '" . $product_id . "') AND (sent_at = 0)
            AND ((email_address = '" . e($email) . "')" . (($user_id > 0) ? " OR (user_id = '" . $user_id . "')" : '') . ")
            LIMIT 1");
        $already = ($already_id > 0);

        $waiting = (int) db_value("SELECT COUNT(*) FROM stock_notify WHERE (email_address = '" . e($email) . "') AND (sent_at = 0)");

        // The number's own requests, whatever address they came with: without
        // them one number, typed beside a fresh address each time, could wait
        // on every product and be texted for each.
        if ($phone !== '') {
            $number['already'] = db_value(
                "SELECT id FROM stock_notify
                WHERE (product_id = '" . $product_id . "') AND (sent_at = 0) AND (phone = '" . e($phone) . "')
                LIMIT 1") !== null;

            $number['waiting'] = (int) db_value("SELECT COUNT(*) FROM stock_notify WHERE (phone = '" . e($phone) . "') AND (sent_at = 0)");
        }
    }

    $status = pg_stock_notify_request_status(true, $email, $product ? $product : null, $already, $waiting, $number);

    if ($status === 'added') {
        db("INSERT INTO stock_notify (product_id, email_address, user_id, phone, created_at, sent_at)
            VALUES ('" . $product_id . "', '" . e($email) . "', '" . $user_id . "', '" . e($phone) . "', UNIX_TIMESTAMP(), 0)");
        $already_id = (int) mysqli_insert_id(db::$con);
    }

    // The device asked from waits for the row, a new one or the one already
    // waiting: pressing again on another device adds that device.
    if (pg_stock_notify_status_ok($status)) {
        pg_stock_notify_push_watch($push_secret, $already_id);
    }

    return $status;
}

/**
 * Whether one address has asked too often in the window. Counted per
 * address with the firewall's counters, which work with the firewall off.
 *
 * @return bool
 */
function pg_stock_notify_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'stock_notify', PG_STOCK_NOTIFY_WRITE_LIMIT, PG_STOCK_NOTIFY_WRITE_WINDOW);

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    if (!$exceeded) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-stock-notify', 'rate', 5, 'stock_notify.php', PG_STOCK_NOTIFY_WRITE_LIMIT . '/' . (int) round(PG_STOCK_NOTIFY_WRITE_WINDOW / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-stock-notify'));
    }

    return true;
}

/**
 * The product an announcement may bring back: an inventory.changed that
 * leaves the product not out of stock. A sale (checkout, local sale) only
 * takes stock away, so it brings nothing back, and the visitor's checkout
 * never waits on somebody else's mail.
 *
 * @param string $event
 * @param mixed  $payload
 * @return int 0 when there is nothing to do
 */
function pg_stock_notify_heard_product($event, $payload)
{
    if (($event !== 'inventory.changed') || !is_array($payload) || !empty($payload['out_of_stock'])) {
        return 0;
    }

    if (isset($payload['source']) && in_array($payload['source'], array('checkout', 'local_sale'), true)) {
        return 0;
    }

    $product_id = isset($payload['product_id']) && is_scalar($payload['product_id']) ? (int) $payload['product_id'] : 0;

    return max(0, $product_id);
}

/**
 * The listener every announcer of inventory.changed calls. One indexed read
 * when nobody waits for the product. A failure here is logged and swallowed:
 * nothing in it may break the write that announced.
 *
 * @param string $event
 * @param mixed  $payload
 * @return void
 */
function pg_stock_notify_heard($event, $payload)
{
    $product_id = pg_stock_notify_heard_product($event, $payload);

    if (($product_id <= 0) || !defined('DB_CONNECTED')) {
        return;
    }

    $throws = pg_error_throws();
    pg_error_throws(true);

    try {
        if (pg_stock_notify_ready()
            && (db_value("SELECT id FROM stock_notify WHERE " . pg_stock_notify_waiting_where('product', $product_id) . " LIMIT 1") !== null)) {
            pg_stock_notify_send($product_id);
        }
    } catch (Throwable $error) {
        error_log('Pinegrap back-in-stock notice for product ' . $product_id . ': ' . $error->getMessage());
    } finally {
        pg_error_throws($throws);
    }
}

/**
 * The product's address on the site.
 *
 * @param string $base_url    scheme, host and path of the site
 * @param string $detail_page the catalog detail page's name
 * @param string $address_name the product's address name
 * @return string '' without a page or an address name
 */
function pg_stock_notify_url($base_url, $detail_page, $address_name)
{
    $detail_page = trim((string) $detail_page);
    $address_name = trim((string) $address_name);

    if (($detail_page === '') || ($address_name === '')) {
        return '';
    }

    return rtrim((string) $base_url, '/') . '/' . encode_url_path($detail_page) . '/' . encode_url_path($address_name);
}

/**
 * The notice: its subject (text) and HTML body.
 *
 * @param string $name the product's name as visitors read it
 * @param string $url  its address, '' for none
 * @return array subject, body
 */
function pg_stock_notify_mail($name, $url)
{
    $name = trim(preg_replace('/[\r\n]+/', ' ', (string) $name));

    $body = '<p>' . lang(array('string' => 'Good news: {var:1}, which you asked us about, is back in stock.', 'vars' => array('<strong>' . h($name) . '</strong>'))) . '</p>';

    if ((string) $url !== '') {
        $body .= '<p><a href="' . h($url) . '">' . h(lang('View the product')) . '</a></p>';
    }

    $body .= '<p>' . h(lang('You asked to be told once, so we will not write about this product again.')) . '</p>';

    return array(
        'subject' => lang(array('string' => '{var:1} is back in stock', 'vars' => array($name))),
        'body'    => $body,
    );
}

/**
 * The visitor notification kind of a back-in-stock notice
 * (site_push_message.kind, site_push_watch.kind).
 */
define('PG_SITE_PUSH_KIND_STOCK', 2);

/**
 * The device notification of a browser that asked: the name as its title,
 * cut to the title's limit rather than refused, a fixed sentence in the
 * subscription's language, and the product's path. Pure.
 *
 * @param string $name     the product's name as visitors read it
 * @param string $path     its path on the site, '' for none (the home page)
 * @param string $language the subscription's language ('' the site's)
 * @return array title, body, url
 */
function pg_stock_notify_push_input($name, $path, $language)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $in = ((string) $language !== '') ? array('language' => (string) $language) : array();

    return array(
        'title' => rtrim(mb_substr($name, 0, 60, 'UTF-8')),
        'body'  => mb_substr(lang(array('string' => 'Back in stock. You asked to be told once.') + $in), 0, 200, 'UTF-8'),
        'url'   => ((string) $path !== '') ? (string) $path : '/',
    );
}

/**
 * Makes the browser named by its secret wait for a request's notice
 * (site_push_watch, kind 2). An unknown secret, or visitor notifications
 * off, is ignored: the mail or text still goes.
 *
 * @param string $secret
 * @param int    $row_id the stock_notify row
 * @return int rows written
 */
function pg_stock_notify_push_watch($secret, $row_id)
{
    if (((string) $secret === '') || ((int) $row_id < 1) || !function_exists('pg_site_push_watch_ready')
        || !pg_site_push_enabled() || !pg_site_push_watch_ready()) {
        return 0;
    }

    $subscription = pg_site_push_subscription_by_secret((string) $secret);

    return $subscription ? pg_site_push_watch_add(PG_SITE_PUSH_KIND_STOCK, array((int) $row_id), (int) $subscription['id']) : 0;
}

/**
 * Tells the browsers waiting for one claimed request: one message each,
 * beside the mail or the text, the wait taken (deleted) first so it goes
 * once.
 *
 * @param int    $row_id the stock_notify row
 * @param string $name   the product's name
 * @param string $path   its path on the site
 * @return int messages written
 */
function pg_stock_notify_push_send($row_id, $name, $path)
{
    $sent = 0;

    foreach (pg_site_push_watchers(PG_SITE_PUSH_KIND_STOCK, $row_id) as $watcher) {
        if (!pg_site_push_watch_take(PG_SITE_PUSH_KIND_STOCK, $row_id, (int) $watcher['subscription_id'], 0, true)) {
            continue;
        }

        if (pg_site_push_message_to_one(pg_stock_notify_push_input($name, $path, (string) $watcher['language']), PG_SITE_PUSH_KIND_STOCK, (int) $watcher['subscription_id'])) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * The fields of the text message's template.
 *
 * @param string $name the product's name as visitors read it
 * @param string $url  its address, '' for none
 * @return array product, url
 */
function pg_stock_notify_sms_fields($name, $url)
{
    return array(
        'product' => trim(preg_replace('/\s+/u', ' ', (string) $name)),
        'url'     => (string) $url,
    );
}

/**
 * Sends the notices of a product that is back: each waiting row is claimed
 * first and told only by the request that claimed it. One address that
 * waits on two rows (asked as a guest and from its account) gets one notice.
 * A row with a mobile number gets one text message instead of the mail while
 * the SMS event is on and the general job sends the SMS queue
 * (pg_sms_job_alive()): without the job the text would only wait there and
 * expire, so the mail goes. A text that cannot be queued falls back to the
 * mail too.
 *
 * @param int $product_id
 * @return int the notices handed on
 */
function pg_stock_notify_send($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !pg_stock_notify_ready()) {
        return 0;
    }

    $product = db_item("SELECT name, short_description, address_name, enabled, inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

    if (!pg_stock_notify_product_back($product)) {
        return 0;
    }

    $queued = function_exists('pg_mail_job_alive') && pg_mail_job_alive();
    $limit = $queued ? PG_STOCK_NOTIFY_SEND_QUEUED : PG_STOCK_NOTIFY_SEND_INLINE;

    $rows = db_items(
        "SELECT stock_notify.id, stock_notify.email_address, stock_notify.phone, user.user_email
        FROM stock_notify
        LEFT JOIN user ON (stock_notify.user_id > 0) AND (user.user_id = stock_notify.user_id)
        WHERE " . pg_stock_notify_waiting_where('product', $product_id) . "
        ORDER BY stock_notify.id
        LIMIT " . $limit);

    $name = ((string) $product['short_description'] !== '') ? (string) $product['short_description'] : (string) $product['name'];
    $detail_page = function_exists('pg_sw_catalog_detail_page_name') ? (string) pg_sw_catalog_detail_page_name(0) : '';
    $url = pg_stock_notify_url(URL_SCHEME . HOSTNAME_SETTING . PATH, $detail_page, $product['address_name']);
    $mail = pg_stock_notify_mail($name, $url);
    $sms_on = pg_stock_notify_sms_sends();
    $provider = $sms_on ? pg_sms_provider() : '';

    $whatsapp_on = function_exists('pg_whatsapp_stock_back_on') && pg_whatsapp_stock_back_on();

    $push_on = function_exists('pg_site_push_watch_ready') && pg_site_push_enabled() && pg_site_push_watch_ready();
    $path = ($push_on && ($url !== '')) ? pg_site_push_url_path($url, pg_site_push_hosts()) : '';

    $mailed = array();
    $whatsapped = array();
    $sent = 0;

    foreach ($rows as $row) {
        db("UPDATE stock_notify SET sent_at = UNIX_TIMESTAMP() WHERE (id = '" . (int) $row['id'] . "') AND (sent_at = 0)");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        // The devices that asked are told beside the mail or the text,
        // never instead: a notification unseen for a day is dropped.
        $pushed = $push_on ? pg_stock_notify_push_send((int) $row['id'], $name, $path) : 0;

        $to = pg_stock_notify_email((string) $row['email_address']);

        if ($to === '') {
            $to = pg_stock_notify_email((string) $row['user_email']);
        }

        // Kept as E.164 digits: read back with the plus, or a foreign number
        // could pass for a Turkish one. The provider is asked again: it may
        // have changed since the request.
        $phone = ($provider !== '') ? pg_stock_notify_phone(pg_sms_dial((string) $row['phone']), $provider) : '';

        // The WhatsApp message goes once per number, beside the text or the
        // mail and never in place of the mail.
        $whatsapp = $whatsapp_on ? pg_sms_number(pg_sms_dial((string) $row['phone'])) : '';
        $told = false;

        if (($whatsapp !== '') && !isset($whatsapped[$whatsapp])) {
            $whatsapped[$whatsapp] = true;
            $told = pg_whatsapp_message_queue('stock_back', pg_sms_dial($whatsapp), pg_stock_notify_sms_fields($name, $url), 'stock:' . (int) $row['id']);
        }

        if ((($to === '') && ($phone === '')) || (($to !== '') && isset($mailed[strtolower($to)])) || (($phone !== '') && isset($mailed[$phone]))) {
            $sent += $told ? 1 : 0;
            continue;
        }

        if ($to !== '') {
            $mailed[strtolower($to)] = true;
        }

        if ($phone !== '') {
            $mailed[$phone] = true;

            if (pg_sms_message_queue('stock_back', pg_sms_dial($phone), pg_stock_notify_sms_fields($name, $url), 'stock:' . (int) $row['id'])) {
                $sent++;
                continue;
            }
        }

        if ($to === '') {
            $sent += $told ? 1 : 0;
            continue;
        }

        $went = email(array(
            'to'                 => $to,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $mail['subject'],
            'format'             => 'html',
            'body'               => $mail['body'],
            'queue'              => $queued,
        ));

        // A mail that failed in the request told nobody: the row waits for
        // the product's next announcement, unless a device or WhatsApp
        // message told the visitor already.
        if (!$told && ($pushed === 0) && pg_stock_notify_mail_unsent($went, function_exists('pg_mail_last_error_permanent') && pg_mail_last_error_permanent())) {
            pg_stock_notify_unclaim((int) $row['id']);
            unset($mailed[strtolower($to)]);
            continue;
        }

        $sent++;
    }

    return $sent;
}

/**
 * The statement that removes the requests of a deleted account, product or
 * calendar event. An event's are its waiting list rows (product_id 0).
 *
 * @param string $column user_id, product_id or calendar_event_id
 * @param mixed  $id
 * @return string '' for anything else, so guest requests (user_id 0) are
 *                never touched
 */
function pg_stock_notify_forget_sql($column, $id)
{
    $id = is_scalar($id) ? (int) $id : 0;

    if (!in_array($column, array('user_id', 'product_id', 'calendar_event_id'), true) || ($id <= 0)) {
        return '';
    }

    return "DELETE FROM stock_notify WHERE " . $column . " = '" . $id . "'"
        . (($column === 'calendar_event_id') ? " AND product_id = 0" : '');
}

/**
 * The statement that removes the device waits (site_push_watch, kind 2) of
 * a deleted account's or product's requests; run before the requests go.
 * Pure.
 *
 * @param string $column user_id or product_id
 * @param mixed  $id
 * @return string '' for anything else
 */
function pg_stock_notify_forget_watch_sql($column, $id)
{
    if (pg_stock_notify_forget_sql($column, $id) === '') {
        return '';
    }

    return "DELETE w FROM site_push_watch AS w INNER JOIN stock_notify AS n ON (w.kind = '" . PG_SITE_PUSH_KIND_STOCK . "') AND (n.id = w.record_id) WHERE n." . $column . " = '" . (int) $id . "'";
}

/**
 * Runs the forget statements of an account or a product: the device waits
 * first, then the requests.
 *
 * @param string $column user_id or product_id
 * @param mixed  $id
 * @return void
 */
function pg_stock_notify_forget($column, $id)
{
    $delete = pg_stock_notify_forget_sql($column, $id);

    if (($delete === '') || !pg_stock_notify_ready()) {
        return;
    }

    // An event's rows exist only once 11.0.248 added the column.
    if (($column === 'calendar_event_id') && !pg_stock_notify_event_ready()) {
        return;
    }

    if (function_exists('pg_site_push_watch_ready') && pg_site_push_watch_ready()) {
        db(pg_stock_notify_forget_watch_sql($column, $id));
    }

    db($delete);
}

/**
 * Removes a deleted account's requests with the account (delete_users.php,
 * edit_user.php): MySQL 5.7 may hand the id to a new account after a
 * restart, which would get the old one's notices.
 *
 * @param mixed $user_id
 * @return void
 */
function pg_stock_notify_forget_user($user_id)
{
    // pg_stock_notify_forget_watch_sql() runs inside, before the requests go.
    pg_stock_notify_forget('user_id', $user_id);
}

/**
 * Removes the requests of a product deleted for good (pg_pb_delete_product()),
 * so a new product given the same id mails nobody who asked for the old one.
 *
 * @param mixed $product_id
 * @return void
 */
function pg_stock_notify_forget_product($product_id)
{
    // pg_stock_notify_forget_watch_sql() runs inside, before the requests go.
    pg_stock_notify_forget('product_id', $product_id);
}

/**
 * Removes the waiting list of a deleted calendar event (Edit Event's Delete,
 * the API, the Workspace), so an event given the same id after a MySQL 5.7
 * restart mails nobody who waited for the old one.
 *
 * @param mixed $calendar_event_id
 * @return void
 */
function pg_stock_notify_forget_event($calendar_event_id)
{
    pg_stock_notify_forget('calendar_event_id', $calendar_event_id);
}

/**
 * The trim's statements, one batch each: notices sent more than
 * PG_STOCK_NOTIFY_KEEP_SENT ago and requests that waited
 * PG_STOCK_NOTIFY_KEEP_WAITING.
 *
 * @param int $now
 * @param int $rows batch size
 * @return array
 */
function pg_stock_notify_trim_queries($now, $rows)
{
    $now = (int) $now;
    $rows = max(1, (int) $rows);

    return array(
        "DELETE FROM stock_notify WHERE (sent_at > 0) AND (sent_at < '" . ($now - PG_STOCK_NOTIFY_KEEP_SENT) . "') LIMIT " . $rows,
        "DELETE FROM stock_notify WHERE (sent_at = 0) AND (created_at < '" . ($now - PG_STOCK_NOTIFY_KEEP_WAITING) . "') LIMIT " . $rows,
    );
}

/**
 * The general job's daily trim (pg_stock_notify_trim_queries()). A tick
 * between passes reads one small file under data/temp.
 *
 * @return int the rows removed
 */
function pg_stock_notify_trim_daily()
{
    if (!pg_stock_notify_ready()) {
        return 0;
    }

    $path = PG_FUNCTIONS_DIR . '/data/temp/stock_notify_trimmed.txt';
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;
    $now = time();

    if (($now - $last) < 86400) {
        return 0;
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    $removed = 0;

    foreach (pg_stock_notify_trim_queries($now, PG_STOCK_NOTIFY_TRIM_ROWS) as $delete) {
        for ($batch = 0; $batch < PG_STOCK_NOTIFY_TRIM_BATCHES; $batch++) {
            db($delete);
            $affected = max(0, (int) mysqli_affected_rows(db::$con));
            $removed += $affected;

            if ($affected < PG_STOCK_NOTIFY_TRIM_ROWS) {
                break;
            }
        }
    }

    return $removed;
}

/**
 * The waiting rows of one product, or of one event's date, as a WHERE
 * clause on stock_notify. A product's rows are those of its own id, which is
 * never 0; an event's are pinned to product_id 0, so a product's notice never
 * reads an event's requests and an event's never reads a product's. Pure.
 *
 * @param string $kind       product or event
 * @param int    $id         the product's or the event's id
 * @param int    $recurrence the event's date (pg_stock_notify_event_date())
 * @return string '' for anything else
 */
function pg_stock_notify_waiting_where($kind, $id, $recurrence = 0)
{
    $id = is_scalar($id) ? (int) $id : 0;

    if ($id <= 0) {
        return '';
    }

    if ($kind === 'product') {
        return "(stock_notify.product_id = '" . $id . "') AND (stock_notify.sent_at = 0)";
    }

    if ($kind === 'event') {
        return "(stock_notify.product_id = 0) AND (stock_notify.calendar_event_id = '" . $id . "')"
            . " AND (stock_notify.recurrence_number = '" . max(0, (int) $recurrence) . "') AND (stock_notify.sent_at = 0)";
    }

    return '';
}

/**
 * Whether the waiting list's columns of 2026.11.0 (11.0.248) are there.
 *
 * @return bool
 */
function pg_stock_notify_event_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_stock_notify_ready()
            && pg_schema_has('stock_notify', 'calendar_event_id')
            && pg_schema_has('stock_notify', 'recurrence_number');
    }

    return $ready;
}

/**
 * Whether the waiting list is offered: it belongs to event tickets, so the
 * switch Tickets (pg_event_tickets_on()) and the calendars must be on.
 *
 * @return bool
 */
function pg_stock_notify_event_on()
{
    return defined('CALENDARS') && CALENDARS
        && function_exists('pg_event_tickets_on') && pg_event_tickets_on()
        && pg_stock_notify_event_ready();
}

/**
 * The date an event's places are counted on: its own occurrence for a
 * recurring event with separate spots, 0 otherwise (the rule of
 * get_calendar_event() and reserve_calendar_event.php). Pure.
 *
 * @param array $event      recurrence, separate_reservations
 * @param int   $recurrence the occurrence asked for
 * @return int
 */
function pg_stock_notify_event_date(array $event, $recurrence)
{
    if (((int) ($event['recurrence'] ?? 0) === 1) && ((int) ($event['separate_reservations'] ?? 0) === 1)) {
        return max(0, (int) $recurrence);
    }

    return 0;
}

/**
 * Whether a recurring event's dates share their places (separate
 * reservations off): its waiting rows are on date 0, which names no single
 * date. Pure.
 *
 * @param array $event recurrence, separate_reservations
 * @return bool
 */
function pg_stock_notify_event_shared(array $event)
{
    return ((int) ($event['recurrence'] ?? 0) === 1) && ((int) ($event['separate_reservations'] ?? 0) !== 1);
}

/**
 * The occurrence a freed place is read on: the date itself, but the last
 * date of the series (its stored repeat count) for a recurring event whose
 * dates share their places, since such a place can be taken on any date
 * until the series ends. Read on date 0, the first date, the list of a
 * series whose first date was over was never mailed. Pure.
 *
 * @param array $event recurrence, separate_reservations,
 *                     total_recurrence_number
 * @param int   $date  the date the places are counted on
 * @return int
 */
function pg_stock_notify_event_read_occurrence(array $event, $date)
{
    if (pg_stock_notify_event_shared($event)) {
        return max(0, (int) ($event['total_recurrence_number'] ?? 0));
    }

    return max(0, (int) $date);
}

/**
 * What an event's date is for the waiting list: none (it takes no
 * reservations, its product is gone or off, it has ended), full (its places
 * are limited and none is left, while its product could be sold) or open
 * (a reservation can be made). A product out of stock is the product's own
 * notice, not a place: such a date is none. Pure.
 *
 * @param array|null $event get_calendar_event()'s row
 * @param int        $now
 * @return string none, full or open
 */
function pg_stock_notify_event_state($event, $now)
{
    if (!is_array($event) || ((int) ($event['reservations'] ?? 0) !== 1)
        || ((string) ($event['product_id'] ?? '') === '') || ((int) ($event['product_enabled'] ?? 0) !== 1)
        || ((int) ($event['published'] ?? 1) !== 1)) {
        return 'none';
    }

    $end = strtotime((string) ($event['end_date_and_time'] ?? ''));

    if (($end === false) || ($end < (int) $now)) {
        return 'none';
    }

    if (((int) ($event['inventory'] ?? 0) === 1) && ((int) ($event['inventory_quantity'] ?? 0) <= 0) && ((int) ($event['backorder'] ?? 0) !== 1)) {
        return 'none';
    }

    if (((int) ($event['limit_reservations'] ?? 0) === 1) && ((int) ($event['number_of_remaining_spots'] ?? 0) <= 0)) {
        return 'full';
    }

    return 'open';
}

/**
 * What a waiting list request comes to, in the order it is checked. Pure.
 *
 * @param bool   $ready   pg_stock_notify_event_on()
 * @param string $email   pg_stock_notify_email()
 * @param string $state   pg_stock_notify_event_state()
 * @param bool   $already the address, or the account, waits for this date
 * @param int    $waiting the requests the address waits on
 * @return string added, already, limit, open, event, email, unavailable
 */
function pg_stock_notify_event_request_status($ready, $email, $state, $already, $waiting)
{
    if (!$ready) {
        return 'unavailable';
    }

    if ($email === '') {
        return 'email';
    }

    if ($state === 'open') {
        return 'open';
    }

    if ($state !== 'full') {
        return 'event';
    }

    if ($already) {
        return 'already';
    }

    if ((int) $waiting >= PG_STOCK_NOTIFY_PENDING_LIMIT) {
        return 'limit';
    }

    return 'added';
}

/**
 * The sentence the visitor reads for a waiting list answer.
 *
 * @param string $status pg_stock_notify_event_request_status()
 * @return string
 */
function pg_stock_notify_event_status_message($status)
{
    switch ($status) {
        case 'added':
            return lang('We will send you one e-mail when a place frees on this date. The place goes to whoever orders first.');
        case 'already':
            return lang('You are already on the waiting list for this date.');
        case 'open':
            return lang('There are places left on this date, so you can reserve now.');
        case 'event':
            return lang('Sorry, this date cannot be reserved.');
        case 'email':
            return lang('Please enter a valid e-mail address.');
        case 'limit':
            return lang('You are already on many waiting lists. Please try again later.');
        case 'unavailable':
            return lang('The waiting list is not available on this site.');
    }

    return lang('Something went wrong. Please try again.');
}

/**
 * Keeps a request to be told when a place frees on an event's date. One
 * waiting request per address (or account) and date; the address's
 * requests for products and dates count together against
 * PG_STOCK_NOTIFY_PENDING_LIMIT.
 *
 * @param int    $event_id
 * @param int    $recurrence the occurrence asked for
 * @param string $email      the address typed; '' takes the signed-in
 *                           account's
 * @param int    $user_id    the signed-in account, 0 for a guest
 * @return string pg_stock_notify_event_request_status()
 */
function pg_stock_notify_event_request($event_id, $recurrence, $email, $user_id)
{
    $event_id = (int) $event_id;
    $user_id = max(0, (int) $user_id);

    if (!pg_stock_notify_event_on()) {
        return 'unavailable';
    }

    if (($email === '') && ($user_id > 0) && defined('USER_EMAIL_ADDRESS')) {
        $email = (string) USER_EMAIL_ADDRESS;
    }

    $email = pg_stock_notify_email($email);
    $state = 'none';
    $date = 0;
    $already = false;
    $waiting = 0;

    if (($email !== '') && ($event_id > 0)) {
        $event = get_calendar_event($event_id, max(0, (int) $recurrence));
        $state = pg_stock_notify_event_state($event ? $event : null, time());
        $date = is_array($event) ? pg_stock_notify_event_date($event, $recurrence) : 0;

        if ($state === 'full') {
            $already = db_value(
                "SELECT id FROM stock_notify
                WHERE " . pg_stock_notify_waiting_where('event', $event_id, $date) . "
                AND ((email_address = '" . e($email) . "')" . (($user_id > 0) ? " OR (user_id = '" . $user_id . "')" : '') . ")
                LIMIT 1") !== null;

            $waiting = (int) db_value("SELECT COUNT(*) FROM stock_notify WHERE (email_address = '" . e($email) . "') AND (sent_at = 0)");
        }
    }

    $status = pg_stock_notify_event_request_status(true, $email, $state, $already, $waiting);

    if ($status === 'added') {
        db("INSERT INTO stock_notify (product_id, calendar_event_id, recurrence_number, email_address, user_id, phone, created_at, sent_at)
            VALUES (0, '" . $event_id . "', '" . $date . "', '" . e($email) . "', '" . $user_id . "', '', UNIX_TIMESTAMP(), 0)");
    }

    return $status;
}

/**
 * The mail of a freed place: its subject (text) and HTML body.
 *
 * @param string $name the event's name
 * @param string $when the date as the visitor reads it
 * @param string $url  the site's address, '' for none
 * @return array subject, body
 */
function pg_stock_notify_event_mail($name, $when, $url)
{
    $name = trim(preg_replace('/[\r\n]+/', ' ', (string) $name));
    $when = trim(preg_replace('/[\r\n]+/', ' ', (string) $when));
    $label = ($when !== '') ? $name . ' (' . $when . ')' : $name;

    $body = '<p>' . lang(array('string' => 'Good news: a place has freed for {var:1}, which you asked us about.', 'vars' => array('<strong>' . h($label) . '</strong>'))) . '</p>'
        . '<p>' . h(lang('No place is held for you: it goes to whoever orders first.')) . '</p>';

    if ((string) $url !== '') {
        $body .= '<p><a href="' . h($url) . '">' . h(lang('Visit the site')) . '</a></p>';
    }

    $body .= '<p>' . h(lang('You asked to be told once, so we will not write about this date again.')) . '</p>';

    return array(
        'subject' => lang(array('string' => 'A place has freed for {var:1}', 'vars' => array($label))),
        'body'    => $body,
    );
}

/**
 * Mails everyone waiting for an event's date once a place is free there:
 * each row is claimed first (sent_at stamped by a conditional UPDATE) and
 * mailed only by the request that claimed it, so two frees at once mail a
 * request once. One address waiting on two rows (guest and account) gets
 * one mail. Queued while the general job runs, else a few in the request,
 * as a product's notice.
 *
 * @param int $event_id
 * @param int $date the date the places are counted on
 * @return int the mails handed on
 */
function pg_stock_notify_event_send($event_id, $date)
{
    $event_id = (int) $event_id;
    $date = max(0, (int) $date);

    if (($event_id <= 0) || !pg_stock_notify_event_ready()) {
        return 0;
    }

    $event = get_calendar_event($event_id, $date);
    $shared = is_array($event) && pg_stock_notify_event_shared($event);

    if ($shared) {
        $event = get_calendar_event($event_id, pg_stock_notify_event_read_occurrence($event, $date));
    }

    if (pg_stock_notify_event_state($event ? $event : null, time()) !== 'open') {
        return 0;
    }

    $queued = function_exists('pg_mail_job_alive') && pg_mail_job_alive();

    $rows = db_items(
        "SELECT stock_notify.id, stock_notify.email_address, user.user_email
        FROM stock_notify
        LEFT JOIN user ON (stock_notify.user_id > 0) AND (user.user_id = stock_notify.user_id)
        WHERE " . pg_stock_notify_waiting_where('event', $event_id, $date) . "
        ORDER BY stock_notify.id
        LIMIT " . ($queued ? PG_STOCK_NOTIFY_SEND_QUEUED : PG_STOCK_NOTIFY_SEND_INLINE));

    // Shared places were asked for on no single date: the mail names the
    // event alone.
    $start = $shared ? false : strtotime((string) $event['start_date_and_time']);
    $when = ($start !== false) ? (function_exists('get_absolute_time') ? get_absolute_time(array('timestamp' => $start, 'size' => 'long')) : date('Y-m-d H:i', $start)) : '';
    $mail = pg_stock_notify_event_mail((string) $event['name'], (string) $when, URL_SCHEME . HOSTNAME_SETTING . PATH);
    $mailed = array();
    $sent = 0;

    foreach ($rows as $row) {
        if (pg_stock_notify_claim((int) $row['id']) !== 1) {
            continue;
        }

        $to = pg_stock_notify_email((string) $row['email_address']);

        if ($to === '') {
            $to = pg_stock_notify_email((string) $row['user_email']);
        }

        if (($to === '') || isset($mailed[strtolower($to)])) {
            continue;
        }

        $mailed[strtolower($to)] = true;

        $went = email(array(
            'to'                 => $to,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $mail['subject'],
            'format'             => 'html',
            'body'               => $mail['body'],
            'queue'              => $queued,
        ));

        // A mail that failed in the request told nobody: the row waits for
        // the next freed place.
        if (pg_stock_notify_mail_unsent($went, function_exists('pg_mail_last_error_permanent') && pg_mail_last_error_permanent())) {
            pg_stock_notify_unclaim((int) $row['id']);
            unset($mailed[strtolower($to)]);
            continue;
        }

        $sent++;
    }

    return $sent;
}

/**
 * Claims one waiting row: stamps sent_at only while it is 0, so of two
 * senders at once one gets the row.
 *
 * @param int $row_id
 * @return int 1 when this call claimed it
 */
function pg_stock_notify_claim($row_id)
{
    db("UPDATE stock_notify SET sent_at = UNIX_TIMESTAMP() WHERE (id = '" . (int) $row_id . "') AND (sent_at = 0)");

    return (int) mysqli_affected_rows(db::$con);
}

/**
 * Puts a claimed row back to waiting: its mail could not go out, so the
 * next announcement tries it again. Only the sender that claimed it calls
 * this, so the row is still its own.
 *
 * @param int $row_id
 * @return void
 */
function pg_stock_notify_unclaim($row_id)
{
    db("UPDATE stock_notify SET sent_at = 0 WHERE (id = '" . (int) $row_id . "') AND (sent_at > 0)");
}

/**
 * Whether a claimed row's mail did not go out and may be tried again: email()
 * failed, sending in the request without the queue, and not for good (a
 * failure for good, such as no way to send at all, would only fail again).
 * Pure.
 *
 * @param bool $sent      email()'s answer
 * @param bool $permanent pg_mail_last_error_permanent()
 * @return bool
 */
function pg_stock_notify_mail_unsent($sent, $permanent)
{
    return !$sent && !$permanent;
}

/**
 * Places have freed on these dates (a cancelled order gave them back, staff
 * raised the spots): those waiting are mailed. One indexed read for a date
 * nobody waits on. A failure is logged and swallowed: it may never break the
 * cancel or the save that freed them.
 *
 * @param array $dates list of array(calendar_event_id, recurrence_number)
 * @return int the mails handed on
 */
function pg_stock_notify_event_freed(array $dates)
{
    if (!$dates || !defined('DB_CONNECTED') || !pg_stock_notify_event_on()) {
        return 0;
    }

    $throws = pg_error_throws();
    pg_error_throws(true);
    $sent = 0;

    try {
        foreach ($dates as $date) {
            $where = pg_stock_notify_waiting_where('event', (int) ($date[0] ?? 0), (int) ($date[1] ?? 0));

            if (($where !== '') && (db_value("SELECT id FROM stock_notify WHERE " . $where . " LIMIT 1") !== null)) {
                $sent += pg_stock_notify_event_send((int) $date[0], (int) $date[1]);
            }
        }
    } catch (Throwable $error) {
        error_log('Pinegrap event waiting list: ' . $error->getMessage());
    } finally {
        pg_error_throws($throws);
    }

    return $sent;
}

/**
 * The reserve page's line under a full date: "Tell me when a place frees",
 * an address field (the signed-in visitor's filled in) and a button, posted
 * to reserve_calendar_event.php with waiting_list=1.
 *
 * @param string $action   reserve_calendar_event.php's address
 * @param string $token    the CSRF field (get_token_field())
 * @param string $send_to  the page to come back to
 * @param int    $page_id
 * @param int    $event_id
 * @param int    $recurrence
 * @param string $email    the address to fill in, '' for none
 * @return string
 */
function pg_stock_notify_event_form_html($action, $token, $send_to, $page_id, $event_id, $recurrence, $email)
{
    $field = 'pg-waiting-email-' . (int) $event_id . '-' . (int) $recurrence;

    return '<form action="' . h($action) . '" method="post" class="pg-waiting-list d-flex flex-wrap align-items-center gap-2 mt-2">'
        . $token
        . '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
        . '<input type="hidden" name="page_id" value="' . (int) $page_id . '">'
        . '<input type="hidden" name="calendar_event_id" value="' . (int) $event_id . '">'
        . '<input type="hidden" name="recurrence_number" value="' . (int) $recurrence . '">'
        . '<input type="hidden" name="waiting_list" value="1">'
        . '<input type="hidden" name="require_cookies" value="true">'
        . '<label for="' . $field . '" class="w-100">' . h(lang('Tell me when a place frees')) . '</label>'
        . '<input type="email" id="' . $field . '" name="email_address" value="' . h($email) . '" maxlength="100" required autocomplete="email" class="form-control w-auto">'
        . '<button type="submit" class="btn btn-outline-primary">' . h(lang('Tell me')) . '</button>'
        . '</form>';
}

/**
 * The waiting line for an event's date as a page draws it: '' unless the
 * waiting list is on and the date is full.
 *
 * @param array  $event      get_calendar_event()'s row
 * @param int    $recurrence the occurrence shown
 * @param int    $page_id    the page drawn
 * @param string $send_to    the page to come back to
 * @return string
 */
function pg_stock_notify_event_line(array $event, $recurrence, $page_id, $send_to)
{
    if ((pg_stock_notify_event_state($event, time()) !== 'full') || !pg_stock_notify_event_on()) {
        return '';
    }

    // The hidden form of the notice's "Stop waiting" button.
    $stop = pg_stock_notify_withdraw_form_once('event', OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/stock_notify.php', $send_to, (int) $event['id']);

    return pg_stock_notify_event_form_html(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/reserve_calendar_event.php',
        get_token_field(), $send_to, $page_id, (int) $event['id'], $recurrence,
        defined('USER_EMAIL_ADDRESS') ? (string) USER_EMAIL_ADDRESS : '')
        . $stop;
}

/**
 * The start of an event's date as get_calendar_event() writes it (the
 * site's zone), '' when the event or the date is gone, or when the event's
 * dates share their places.
 *
 * @param int $event_id
 * @param int $date
 * @return string
 */
function pg_stock_notify_event_start($event_id, $date)
{
    $event = ((int) $event_id > 0) ? get_calendar_event((int) $event_id, max(0, (int) $date)) : false;

    // A series sharing its places was asked for on no single date.
    return (is_array($event) && !pg_stock_notify_event_shared($event)) ? (string) $event['start_date_and_time'] : '';
}

/**
 * The personal data export's waiting list rows: each event row gets
 * event_date, its date's start, read once per event and date; a product's
 * row is left as it is. Pure but for $start.
 *
 * @param array    $rows  stock_notify rows (calendar_event_id,
 *                        recurrence_number)
 * @param callable $start (event id, date) => the start, '' for none
 * @return array
 */
function pg_stock_notify_export_event_dates(array $rows, $start)
{
    $seen = array();

    foreach ($rows as $index => $row) {
        $event_id = (int) ($row['calendar_event_id'] ?? 0);

        if ($event_id <= 0) {
            continue;
        }

        $key = $event_id . ':' . (int) ($row['recurrence_number'] ?? 0);

        if (!isset($seen[$key])) {
            $seen[$key] = (string) call_user_func($start, $event_id, (int) ($row['recurrence_number'] ?? 0));
        }

        $rows[$index]['event_date'] = $seen[$key];
    }

    return $rows;
}

/**
 * Which waiting rows a withdraw removes. Signed in, the account's rows,
 * whatever address it typed; a guest, the rows of the address typed, since
 * a request is kept without proof of the address and taking one back asks
 * for no more. Only waiting rows (sent_at 0) of that product, or of that
 * event's date, are taken: a sent row is never touched. Whether a row was
 * there changes nothing in the answer. Pure.
 *
 * @param bool   $ready   the table (and, for an event, its columns) is there
 * @param string $kind    product or event
 * @param int    $id      the product's or the event's id
 * @param int    $date    the event's date (pg_stock_notify_event_date())
 * @param string $email   pg_stock_notify_email() of the address typed
 * @param int    $user_id the signed-in account, 0 for a guest
 * @param array  $rows    candidate stock_notify rows (id, product_id,
 *                        calendar_event_id, recurrence_number,
 *                        email_address, user_id, sent_at)
 * @return array status (withdrawn, email, unavailable), ids (to delete)
 */
function pg_stock_notify_withdraw_status($ready, $kind, $id, $date, $email, $user_id, array $rows)
{
    $user_id = max(0, (int) $user_id);
    $email = (string) $email;

    if (!$ready) {
        return array('status' => 'unavailable', 'ids' => array());
    }

    if (($user_id === 0) && ($email === '')) {
        return array('status' => 'email', 'ids' => array());
    }

    $id = (int) $id;
    $ids = array();

    foreach ($rows as $row) {
        if (!is_array($row) || ((int) ($row['sent_at'] ?? 1) !== 0) || ($id <= 0)) {
            continue;
        }

        if ($kind === 'product') {
            $same = ((int) ($row['product_id'] ?? 0) === $id);
        } elseif ($kind === 'event') {
            $same = ((int) ($row['product_id'] ?? 0) === 0)
                && ((int) ($row['calendar_event_id'] ?? 0) === $id)
                && ((int) ($row['recurrence_number'] ?? 0) === max(0, (int) $date));
        } else {
            $same = false;
        }

        $owner = ($user_id > 0)
            ? ((int) ($row['user_id'] ?? 0) === $user_id)
            : (strcasecmp((string) ($row['email_address'] ?? ''), $email) === 0);

        if ($same && $owner && ((int) ($row['id'] ?? 0) > 0)) {
            $ids[] = (int) $row['id'];
        }
    }

    return array('status' => 'withdrawn', 'ids' => $ids);
}

/**
 * The sentence of a withdraw, the same whether a row was there or not.
 *
 * @param string $kind product or event
 * @return string
 */
function pg_stock_notify_withdraw_message($kind)
{
    return ($kind === 'event')
        ? lang('You will get no message about this date.')
        : lang('You will get no message about this product.');
}

/**
 * Takes back a waiting request for a product or an event's date
 * (stock_notify.php, withdraw=1).
 *
 * @param string $kind       product or event
 * @param int    $id         the product's or the event's id
 * @param int    $recurrence the event's occurrence asked for
 * @param string $email      the address typed; ignored when signed in
 * @param int    $user_id    the signed-in account, 0 for a guest
 * @return string withdrawn, email, unavailable
 */
function pg_stock_notify_withdraw($kind, $id, $recurrence, $email, $user_id)
{
    $id = (int) $id;
    $user_id = max(0, (int) $user_id);
    $email = pg_stock_notify_email($email);
    $ready = ($kind === 'event') ? pg_stock_notify_event_ready() : pg_stock_notify_ready();
    $date = 0;
    $rows = array();

    if ($ready && ($id > 0) && (($user_id > 0) || ($email !== ''))) {
        if ($kind === 'event') {
            $event = get_calendar_event($id, max(0, (int) $recurrence));
            $date = is_array($event) ? pg_stock_notify_event_date($event, $recurrence) : 0;
        }

        $where = pg_stock_notify_waiting_where($kind, $id, $date);

        if ($where !== '') {
            $rows = db_items(
                "SELECT id, product_id, " . (($kind === 'event') ? 'calendar_event_id, recurrence_number' : "0 AS calendar_event_id, 0 AS recurrence_number") . ", email_address, user_id, sent_at
                FROM stock_notify
                WHERE " . $where . "
                AND " . (($user_id > 0) ? "(user_id = '" . $user_id . "')" : "(email_address = '" . e($email) . "')"));
        }
    }

    $result = pg_stock_notify_withdraw_status($ready, $kind, $id, $date, $email, $user_id, is_array($rows) ? $rows : array());

    if ($result['ids']) {
        $list = implode(', ', array_map('intval', $result['ids']));

        // The row's device waits go first; a row the sender claimed meanwhile
        // (sent_at no longer 0) is left as it is.
        if (function_exists('pg_site_push_watch_ready') && pg_site_push_watch_ready()) {
            db("DELETE w FROM site_push_watch AS w INNER JOIN stock_notify AS n ON (w.kind = '" . PG_SITE_PUSH_KIND_STOCK . "') AND (n.id = w.record_id)
                WHERE n.id IN (" . $list . ") AND (n.sent_at = 0)");
        }

        db("DELETE FROM stock_notify WHERE id IN (" . $list . ") AND (sent_at = 0)");
    }

    return $result['status'];
}

/**
 * Remembers in the session what a notice's "Stop waiting" button takes
 * back: the request the visitor has just made, or found waiting. The page
 * that shows the notice prints the hidden form from it
 * (pg_stock_notify_withdraw_form_html()).
 *
 * @param string $kind       product or event
 * @param int    $id
 * @param int    $recurrence the event's occurrence, 0 for a product
 * @param string $email      the address typed
 * @return void
 */
function pg_stock_notify_withdraw_offer($kind, $id, $recurrence, $email)
{
    $_SESSION['software']['stock_notify_stop'] = array(
        'kind'       => ($kind === 'event') ? 'event' : 'product',
        'id'         => (int) $id,
        'recurrence' => max(0, (int) $recurrence),
        'email'      => pg_stock_notify_email($email),
    );
}

/**
 * The request the session offers to take back, null for none.
 *
 * @return array|null kind, id, recurrence, email
 */
function pg_stock_notify_withdraw_offered()
{
    $offer = $_SESSION['software']['stock_notify_stop'] ?? null;

    return is_array($offer) ? $offer : null;
}

/**
 * Forgets the offer once the request is taken back.
 *
 * @return void
 */
function pg_stock_notify_withdraw_forget()
{
    unset($_SESSION['software']['stock_notify_stop']);
}

/**
 * The hidden form a notice's "Stop waiting" button posts. It sits outside
 * the page's own forms (a notice may be drawn inside the add-to-cart form);
 * the button names it with form="". Pure.
 *
 * @param string     $action  stock_notify.php's address
 * @param string     $token   the CSRF field (get_token_field())
 * @param string     $send_to the page to come back to
 * @param array|null $offer   pg_stock_notify_withdraw_offered()
 * @return string '' without an offer
 */
function pg_stock_notify_withdraw_form_html($action, $token, $send_to, $offer)
{
    if (!is_array($offer) || ((int) ($offer['id'] ?? 0) <= 0)) {
        return '';
    }

    $event = (($offer['kind'] ?? '') === 'event');

    return '<form id="' . PG_STOCK_NOTIFY_STOP_FORM . '" action="' . h($action) . '" method="post" class="d-none">'
        . $token
        . '<input type="hidden" name="withdraw" value="1">'
        . '<input type="hidden" name="product_id" value="' . ($event ? 0 : (int) $offer['id']) . '">'
        . ($event
            ? '<input type="hidden" name="calendar_event_id" value="' . (int) $offer['id'] . '">'
                . '<input type="hidden" name="recurrence_number" value="' . max(0, (int) ($offer['recurrence'] ?? 0)) . '">'
            : '')
        . '<input type="hidden" name="email_address" value="' . h((string) ($offer['email'] ?? '')) . '">'
        . '<input type="hidden" name="send_to" value="' . h($send_to) . '">'
        . '</form>';
}

/**
 * The "Stop waiting" button a notice carries beside its sentence. Pure.
 *
 * @return string
 */
function pg_stock_notify_withdraw_button_html()
{
    return '<button type="submit" form="' . PG_STOCK_NOTIFY_STOP_FORM . '" class="btn btn-link btn-sm p-0 ms-1 align-baseline">'
        . h(lang('Stop waiting')) . '</button>';
}

/**
 * The hidden stop form for a page drawing the request's own controls, once
 * per page and only while the session offers a request of that kind.
 *
 * @param string $kind    product or event
 * @param string $action  stock_notify.php's address
 * @param string $send_to the page to come back to
 * @param int    $id      only for this product or event, 0 for any
 * @return string
 */
function pg_stock_notify_withdraw_form_once($kind, $action, $send_to, $id = 0)
{
    static $printed = false;
    $offer = pg_stock_notify_withdraw_offered();

    if ($printed || !$offer || (($offer['kind'] ?? '') !== $kind)
        || (((int) $id > 0) && ((int) ($offer['id'] ?? 0) !== (int) $id))) {
        return '';
    }

    $html = pg_stock_notify_withdraw_form_html($action, get_token_field(), $send_to, $offer);
    $printed = ($html !== '');

    return $html;
}
