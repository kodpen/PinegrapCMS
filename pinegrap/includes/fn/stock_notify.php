<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Back-in-stock notices: a visitor asks to be told when a product they
 * cannot buy for want of stock is back, and gets one e-mail when it is.
 * Kept in stock_notify (2026.11.0, 11.0.14): the product, the address typed
 * (or the account's, read when the notice goes out), created_at, and
 * sent_at, stamped once the notice went out. phone stays empty until the
 * notice goes out by SMS too.
 *
 * Asking (stock_notify.php, pg_stock_notify_request()): only for a product
 * the catalog shows that tracks stock, has none left and takes no back
 * order, the rule of the catalog widgets' __out_of_stock. One waiting
 * request per address and product; an address may wait for at most
 * PG_STOCK_NOTIFY_PENDING_LIMIT products at once.
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

// The products one e-mail address may wait for at once.
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
 * mobile number a text can reach.
 *
 * @param mixed $value
 * @return string
 */
function pg_stock_notify_phone($value)
{
    if (!is_string($value) || (trim($value) === '') || !function_exists('pg_sms_number')) {
        return '';
    }

    return pg_sms_number($value);
}

/**
 * What a request comes to, in the order it is checked.
 *
 * @param bool       $ready   pg_stock_notify_ready()
 * @param string     $email   pg_stock_notify_email()
 * @param array|null $product the product row, null when there is none
 * @param bool       $already the address waits for this product already
 * @param int        $waiting the products the address waits for
 * @return string added, already, limit, in_stock, product, email, unavailable
 */
function pg_stock_notify_request_status($ready, $email, $product, $already, $waiting)
{
    if (!$ready) {
        return 'unavailable';
    }

    if ($email === '') {
        return 'email';
    }

    if (!is_array($product) || ((string) ($product['enabled'] ?? '') !== '1')) {
        return 'product';
    }

    if (!pg_stock_notify_product_out($product)) {
        return 'in_stock';
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
 * @return string
 */
function pg_stock_notify_status_message($status)
{
    switch ($status) {
        case 'added':
            return lang('We will e-mail you when this product is back in stock.');
        case 'already':
            return lang('You have already asked to be told when this product is back in stock.');
        case 'in_stock':
            return lang('This product is in stock now, so you can order it.');
        case 'product':
            return lang('Sorry, this product is not available right now.');
        case 'email':
            return lang('Please enter a valid e-mail address.');
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
 * @return string pg_stock_notify_request_status()
 */
function pg_stock_notify_request($product_id, $email, $user_id)
{
    $product_id = (int) $product_id;
    $user_id = max(0, (int) $user_id);

    if (!pg_stock_notify_ready()) {
        return 'unavailable';
    }

    if (($email === '') && ($user_id > 0) && defined('USER_EMAIL_ADDRESS')) {
        $email = (string) USER_EMAIL_ADDRESS;
    }

    $email = pg_stock_notify_email($email);
    $product = null;
    $already = false;
    $waiting = 0;

    if (($email !== '') && ($product_id > 0)) {
        $product = db_item("SELECT enabled, inventory, inventory_quantity, backorder FROM products WHERE id = '" . $product_id . "' LIMIT 1");

        // The address typed, or the account's own requests whatever address
        // it typed then.
        $already = db_value(
            "SELECT id FROM stock_notify
            WHERE (product_id = '" . $product_id . "') AND (sent_at = 0)
            AND ((email_address = '" . e($email) . "')" . (($user_id > 0) ? " OR (user_id = '" . $user_id . "')" : '') . ")
            LIMIT 1") !== null;

        $waiting = (int) db_value("SELECT COUNT(*) FROM stock_notify WHERE (email_address = '" . e($email) . "') AND (sent_at = 0)");
    }

    $status = pg_stock_notify_request_status(true, $email, $product ? $product : null, $already, $waiting);

    if ($status === 'added') {
        db("INSERT INTO stock_notify (product_id, email_address, user_id, phone, created_at, sent_at)
            VALUES ('" . $product_id . "', '" . e($email) . "', '" . $user_id . "', '', UNIX_TIMESTAMP(), 0)");
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
        waf_text_log('DENY', $ip, array('rule' => 'rate-stock-notify'));
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
            && (db_value("SELECT id FROM stock_notify WHERE (product_id = '" . $product_id . "') AND (sent_at = 0) LIMIT 1") !== null)) {
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
 * Sends the notices of a product that is back: each waiting row is claimed
 * first and mailed only by the request that claimed it. One address that
 * waits on two rows (asked as a guest and from its account) gets one mail.
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
        "SELECT stock_notify.id, stock_notify.email_address, user.user_email
        FROM stock_notify
        LEFT JOIN user ON (stock_notify.user_id > 0) AND (user.user_id = stock_notify.user_id)
        WHERE (stock_notify.product_id = '" . $product_id . "') AND (stock_notify.sent_at = 0)
        ORDER BY stock_notify.id
        LIMIT " . $limit);

    $name = ((string) $product['short_description'] !== '') ? (string) $product['short_description'] : (string) $product['name'];
    $detail_page = function_exists('pg_sw_catalog_detail_page_name') ? (string) pg_sw_catalog_detail_page_name(0) : '';
    $mail = pg_stock_notify_mail($name, pg_stock_notify_url(URL_SCHEME . HOSTNAME_SETTING . PATH, $detail_page, $product['address_name']));

    $mailed = array();
    $sent = 0;

    foreach ($rows as $row) {
        db("UPDATE stock_notify SET sent_at = UNIX_TIMESTAMP() WHERE (id = '" . (int) $row['id'] . "') AND (sent_at = 0)");

        if (mysqli_affected_rows(db::$con) !== 1) {
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

        email(array(
            'to'                 => $to,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => $mail['subject'],
            'format'             => 'html',
            'body'               => $mail['body'],
            'queue'              => $queued,
        ));

        $sent++;
    }

    return $sent;
}

/**
 * The statement that removes the requests of a deleted account or product.
 *
 * @param string $column user_id or product_id
 * @param mixed  $id
 * @return string '' for anything else, so guest requests (user_id 0) are
 *                never touched
 */
function pg_stock_notify_forget_sql($column, $id)
{
    $id = is_scalar($id) ? (int) $id : 0;

    if (!in_array($column, array('user_id', 'product_id'), true) || ($id <= 0)) {
        return '';
    }

    return "DELETE FROM stock_notify WHERE " . $column . " = '" . $id . "'";
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
    $delete = pg_stock_notify_forget_sql('user_id', $user_id);

    if (($delete !== '') && pg_stock_notify_ready()) {
        db($delete);
    }
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
    $delete = pg_stock_notify_forget_sql('product_id', $product_id);

    if (($delete !== '') && pg_stock_notify_ready()) {
        db($delete);
    }
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
