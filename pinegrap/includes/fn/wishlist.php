<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Favourites (wishlist): the products a visitor saved with the heart of a
 * catalog card, kept in wishlist_items (2026.11.0, 11.0.14).
 *
 * Whose list a row belongs to, its owner:
 *
 *   account   user_id = the account, cookie_token = ''
 *   visitor   user_id = 0, cookie_token = sha256 of the pg_wishlist cookie
 *
 * A visitor who is not signed in gets the cookie the first time they save a
 * product, never on a page view: 64 random hex characters, HttpOnly, Lax.
 * It runs out a year after the newest product in the list, the day the daily
 * trim removes the list. The table keeps only its sha256, so a row read out
 * of the database cannot be replayed as a cookie. The cookie is strictly
 * necessary (it holds what the visitor asked the site to keep), so it is
 * listed under necessary in the cookie notice. The session keeps the cookie
 * it started until the browser sends it back (pg_wishlist_visitor_token()),
 * so saves sent before the first answer arrived share one list.
 *
 * Once the visitor is signed in, the first wishlist read or write of the
 * request moves the cookie's list into the account (pg_wishlist_merge()) and
 * clears the cookie. Merging lazily, rather than in each sign-in path, covers
 * every way in at once (form, social sign-in, two-step, remember-me, sign-up)
 * and the cross-site return of Apple's sign-in, which carries no Lax cookie.
 * A member of staff signed in as a customer (login_as_user.php) never hands
 * their own browser's list to that customer.
 *
 * What other code uses:
 *
 *   pg_wishlist_ready()      false until the table exists; everything below
 *                            answers empty or refuses then
 *   pg_wishlist_ids()        the product ids in the visitor's list, newest
 *                            first, only products the catalog still shows
 *   pg_wishlist_has($id)     whether a product is in the visitor's list
 *   pg_wishlist_set($id, $on) add or remove one product; the new state, or
 *                            null when refused
 *   pg_wishlist_owner()      the owner of the request's list, or null
 *
 * wishlist.php is the endpoint the heart buttons use; a page holding one
 * gets their script (assets/js/pg_wishlist.js) from pg_wishlist_inject().
 * A daily pass
 * (pg_wishlist_trim_daily(), from job.php) removes visitor lists no cookie
 * can name any more and rows of deleted products and accounts; deleting an
 * account or a product removes its rows at once (pg_wishlist_forget_user(),
 * pg_wishlist_forget_product()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The visitor's cookie, its lifetime, and the visitor list's lifetime: a
// list is kept as long as a browser can still hold its cookie.
define('PG_WISHLIST_COOKIE', 'pg_wishlist');
define('PG_WISHLIST_LIFETIME', 31536000);

// Writes accepted from one address (its waf_ip_subject()) in the window: a
// person clicking hearts through a catalog stays far below it, a script
// filling the table does not.
define('PG_WISHLIST_WRITE_LIMIT', 60);
define('PG_WISHLIST_WRITE_WINDOW', 600);

// The daily trim removes at most this many batches of this many rows of each
// kind; what is left waits for the next day.
define('PG_WISHLIST_TRIM_ROWS', 1000);
define('PG_WISHLIST_TRIM_BATCHES', 20);

/**
 * Whether the wishlist table of 2026.11.0 (11.0.14) is there.
 *
 * @return bool
 */
function pg_wishlist_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('wishlist_items', 'cookie_token')
            && pg_schema_has('wishlist_items', 'added_at');
    }

    return $ready;
}

/**
 * Whether a value has the shape of a visitor cookie, and of the key stored
 * for it: exactly 64 lowercase hexadecimal characters.
 *
 * @param mixed $value
 * @return bool
 */
function pg_wishlist_token_valid($value)
{
    return is_string($value) && (preg_match('/^[a-f0-9]{64}$/D', $value) === 1);
}

/**
 * What wishlist_items.cookie_token keeps for a visitor cookie: its sha256,
 * so a row leaked from the database does not open the list it belongs to.
 *
 * @param string $value a valid cookie value
 * @return string 64 hex characters, or '' for anything that is not one
 */
function pg_wishlist_token_key($value)
{
    if (!pg_wishlist_token_valid($value)) {
        return '';
    }

    return hash('sha256', $value);
}

/**
 * A new random cookie value.
 *
 * @return string 64 hex characters, or '' when the server has no source of
 *                randomness strong enough for it
 */
function pg_wishlist_token_new()
{
    try {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }
    } catch (Exception $error) {
        // Falls through to OpenSSL.
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes = openssl_random_pseudo_bytes(32, $strong);

        if (($bytes !== false) && $strong) {
            return bin2hex($bytes);
        }
    }

    return '';
}

/**
 * Sends the visitor cookie: the site's path, HttpOnly, Lax, Secure on https.
 * pg_wishlist_cookie_follow() sends it after each write to the list, with the
 * expiry the list gives.
 *
 * @param string   $value   a valid cookie value
 * @param int|null $expires when it runs out; null for a year from now
 * @return bool false when it could not be sent (headers already out, or not a
 *              valid value)
 */
function pg_wishlist_cookie_write($value, $expires = null)
{
    if (!pg_wishlist_token_valid($value) || headers_sent()) {
        return false;
    }

    $expires = ($expires === null) ? (time() + PG_WISHLIST_LIFETIME) : (int) $expires;
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_WISHLIST_COOKIE, $value, array('expires' => $expires, 'path' => PATH, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie(PG_WISHLIST_COOKIE, $value, $expires, PATH . '; samesite=Lax', '', $secure, true);
    }

    $_COOKIE[PG_WISHLIST_COOKIE] = $value;

    return true;
}

/**
 * When a visitor's cookie runs out: a year after the newest product in the
 * list was saved, the moment pg_wishlist_trim() may remove the list
 * (pg_wishlist_trim_before()). Saving a product the list holds already
 * changes neither.
 *
 * @param mixed $newest MAX(added_at) of the list, null when it is empty
 * @return int a unix time; 0 for an empty list, whose cookie is cleared
 */
function pg_wishlist_cookie_expires($newest)
{
    if (!is_numeric($newest) || ((int) $newest <= 0)) {
        return 0;
    }

    return (int) $newest + PG_WISHLIST_LIFETIME;
}

/**
 * Removes the visitor cookie, with the same path and flags it was sent with,
 * and the session's copy of it.
 *
 * @return void
 */
function pg_wishlist_cookie_clear()
{
    unset($_COOKIE[PG_WISHLIST_COOKIE]);

    // A merged or emptied list is not started again from the session.
    unset($_SESSION['software']['wishlist_cookie']);

    if (headers_sent()) {
        return;
    }

    $expires = time() - 3600;
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_WISHLIST_COOKIE, '', array('expires' => $expires, 'path' => PATH, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie(PG_WISHLIST_COOKIE, '', $expires, PATH . '; samesite=Lax', '', $secure, true);
    }
}

/**
 * The visitor cookie the request carries, when it is a valid one.
 *
 * @return string '' without one
 */
function pg_wishlist_cookie_value()
{
    $value = isset($_COOKIE[PG_WISHLIST_COOKIE]) ? $_COOKIE[PG_WISHLIST_COOKIE] : '';

    return pg_wishlist_token_valid($value) ? $value : '';
}

/**
 * The visitor's cookie: the one the request carries, or else the one this
 * session started (pg_wishlist_owner()) and the browser has not sent back
 * yet. Two saves a visitor without a cookie sends at once both arrive
 * without one; PHP's file sessions lock, so the second runs once the first
 * has finished and finds its cookie here. Starting a list of its own instead,
 * it would answer with a cookie the browser keeps in place of the first one,
 * and the first product would be lost.
 *
 * @return string '' without one
 */
function pg_wishlist_visitor_token()
{
    $cookie = pg_wishlist_cookie_value();

    if ($cookie !== '') {
        return $cookie;
    }

    $started = isset($_SESSION['software']['wishlist_cookie']) ? $_SESSION['software']['wishlist_cookie'] : '';

    return pg_wishlist_token_valid($started) ? $started : '';
}

/**
 * The account signed in for this request, as a list owner sees it.
 *
 * @return int 0 when nobody is signed in
 */
function pg_wishlist_user_id()
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN || !defined('USER_ID')) {
        return 0;
    }

    return max(0, (int) USER_ID);
}

/**
 * The owner of a list: the account when one is signed in, otherwise the
 * visitor whose cookie this is.
 *
 * @param int    $user_id the signed-in account, 0 for none
 * @param string $cookie  the visitor cookie, '' for none
 * @return array|null user_id, cookie_token; null for a visitor with no
 *                    valid cookie
 */
function pg_wishlist_owner_for($user_id, $cookie)
{
    $user_id = (int) $user_id;

    if ($user_id > 0) {
        return array('user_id' => $user_id, 'cookie_token' => '');
    }

    $key = pg_wishlist_token_key($cookie);

    if ($key === '') {
        return null;
    }

    return array('user_id' => 0, 'cookie_token' => $key);
}

/**
 * The owner of the request's list. For a signed-in visitor it first moves a
 * cookie list into the account (pg_wishlist_merge()), so every read and
 * write sees one list. A visitor without a cookie (pg_wishlist_visitor_token())
 * gets a new one only when $create is true, which only pg_wishlist_set() asks
 * for when it saves a product, and only while the headers can still carry
 * it: it is sent once the row is written (pg_wishlist_cookie_follow()), and
 * the session keeps it until the browser sends it back.
 *
 * @param bool $create
 * @return array|null user_id, cookie_token; null for a visitor with no list
 *                    (or none that could be started)
 */
function pg_wishlist_owner($create = false)
{
    $user_id = pg_wishlist_user_id();

    if ($user_id > 0) {
        pg_wishlist_merge();

        return pg_wishlist_owner_for($user_id, '');
    }

    $cookie = pg_wishlist_visitor_token();

    if ($create && ($cookie === '') && !headers_sent()) {
        $cookie = pg_wishlist_token_new();

        if ($cookie !== '') {
            $_COOKIE[PG_WISHLIST_COOKIE] = $cookie;
            $_SESSION['software']['wishlist_cookie'] = $cookie;
        }
    }

    return pg_wishlist_owner_for(0, $cookie);
}

/**
 * Sends a visitor's cookie again after a write to their list, to run out when
 * the list does (pg_wishlist_cookie_expires()): a year from its newest
 * product, not from this request, so saving a product the list holds already
 * or removing the newest one keeps the cookie and the daily trim in step. An
 * emptied list clears the cookie. Only the visitor's own cookie is sent
 * (pg_wishlist_visitor_token()), the one this session started included.
 *
 * @param array|null $owner the owner whose list was written
 * @return void
 */
function pg_wishlist_cookie_follow($owner)
{
    $where = pg_wishlist_owner_sql($owner);

    if (($where === '') || ((int) $owner['user_id'] !== 0)) {
        return;
    }

    $expires = pg_wishlist_cookie_expires(db_value("SELECT MAX(wishlist_items.added_at) FROM wishlist_items WHERE " . $where));

    if ($expires === 0) {
        pg_wishlist_cookie_clear();

        return;
    }

    pg_wishlist_cookie_write(pg_wishlist_visitor_token(), $expires);
}

/**
 * The SQL condition wishlist_items rows of an owner meet.
 *
 * @param array|null $owner pg_wishlist_owner_for()
 * @return string '' for no owner or one that is not well formed
 */
function pg_wishlist_owner_sql($owner)
{
    if (!is_array($owner) || !isset($owner['user_id'], $owner['cookie_token'])) {
        return '';
    }

    $user_id = (int) $owner['user_id'];
    $key = $owner['cookie_token'];

    if (($user_id > 0) && ($key === '')) {
        return "(wishlist_items.user_id = '" . $user_id . "') AND (wishlist_items.cookie_token = '')";
    }

    if (($user_id === 0) && pg_wishlist_token_valid($key)) {
        return "(wishlist_items.user_id = '0') AND (wishlist_items.cookie_token = '" . $key . "')";
    }

    return '';
}

/**
 * The statements that move a visitor's list into an account: every row is
 * copied to the account, a product the account already holds is skipped
 * (INSERT IGNORE; a plain UPDATE would stop at the unique key and leave the
 * whole list behind), then the visitor's rows go.
 *
 * @param int    $user_id the account
 * @param string $key     the visitor's cookie_token
 * @return array the statements in the order they run; empty when either is
 *               not well formed
 */
function pg_wishlist_merge_sql($user_id, $key)
{
    $user_id = (int) $user_id;

    if (($user_id <= 0) || !pg_wishlist_token_valid($key)) {
        return array();
    }

    return array(
        "INSERT IGNORE INTO wishlist_items (user_id, cookie_token, product_id, added_at)
        SELECT '" . $user_id . "', '', product_id, added_at
        FROM wishlist_items
        WHERE (user_id = '0') AND (cookie_token = '" . $key . "')",

        "DELETE FROM wishlist_items WHERE (user_id = '0') AND (cookie_token = '" . $key . "')",
    );
}

/**
 * Moves the visitor cookie's list (pg_wishlist_visitor_token()) into the
 * signed-in account and clears the cookie. Runs once a request, and costs
 * nothing without a cookie. Skipped while a member of staff is signed in as
 * another account: the list in their browser is theirs, not the customer's.
 *
 * @return void
 */
function pg_wishlist_merge()
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    $user_id = pg_wishlist_user_id();
    $cookie = pg_wishlist_visitor_token();

    if (($user_id <= 0) || ($cookie === '') || !pg_wishlist_ready()) {
        return;
    }

    if (!empty($_SESSION['software']['logged_in_as_different_user'])) {
        return;
    }

    foreach (pg_wishlist_merge_sql($user_id, pg_wishlist_token_key($cookie)) as $statement) {
        db($statement);
    }

    pg_wishlist_cookie_clear();
}

/**
 * The SQL condition a product row meets when the catalog shows it: switched
 * on. Putting a product in the Recycle Bin switches it off
 * (pg_catalog_bin_statements()), so a binned product does not meet it.
 *
 * @return string
 */
function pg_wishlist_product_live_sql()
{
    return '(products.enabled = 1)';
}

/**
 * Whether a product may be saved: it exists and the catalog shows it.
 *
 * @param int $product_id
 * @return bool
 */
function pg_wishlist_product_ok($product_id)
{
    $product_id = (int) $product_id;

    if ($product_id <= 0) {
        return false;
    }

    return db_value("SELECT 1 FROM products WHERE (products.id = '" . $product_id . "') AND " . pg_wishlist_product_live_sql()) !== null;
}

/**
 * Whether a product is in the visitor's list, whatever its state in the
 * catalog now.
 *
 * @param int $product_id
 * @return bool
 */
function pg_wishlist_has($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !pg_wishlist_ready()) {
        return false;
    }

    $where = pg_wishlist_owner_sql(pg_wishlist_owner(false));

    if ($where === '') {
        return false;
    }

    return db_value("SELECT 1 FROM wishlist_items WHERE " . $where . " AND (wishlist_items.product_id = '" . $product_id . "') LIMIT 1") !== null;
}

/**
 * Adds a product to the visitor's list or takes it out. Adding is refused
 * for a product the catalog does not show, and starts a visitor's cookie when
 * there is none; removing works for any product, so one switched off since
 * can still leave the list. A visitor's cookie is sent again after either
 * (pg_wishlist_cookie_follow()).
 *
 * @param int  $product_id
 * @param bool $on true to add, false to remove
 * @return bool|null the product's state in the list now, or null when refused
 *                   (no table, no such product, no list that could be started)
 */
function pg_wishlist_set($product_id, $on)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !pg_wishlist_ready()) {
        return null;
    }

    if (!$on) {
        $owner = pg_wishlist_owner(false);
        $where = pg_wishlist_owner_sql($owner);

        if ($where !== '') {
            db("DELETE FROM wishlist_items WHERE " . $where . " AND (wishlist_items.product_id = '" . $product_id . "')");

            pg_wishlist_cookie_follow($owner);
        }

        return false;
    }

    if (!pg_wishlist_product_ok($product_id)) {
        return null;
    }

    $owner = pg_wishlist_owner(true);

    if (pg_wishlist_owner_sql($owner) === '') {
        return null;
    }

    db("INSERT IGNORE INTO wishlist_items (user_id, cookie_token, product_id, added_at)
        VALUES ('" . (int) $owner['user_id'] . "', '" . e($owner['cookie_token']) . "', '" . $product_id . "', UNIX_TIMESTAMP())");

    pg_wishlist_cookie_follow($owner);

    return true;
}

/**
 * The product ids in the visitor's list that the catalog still shows, newest
 * first.
 *
 * @return int[]
 */
function pg_wishlist_ids()
{
    if (!pg_wishlist_ready()) {
        return array();
    }

    $where = pg_wishlist_owner_sql(pg_wishlist_owner(false));

    if ($where === '') {
        return array();
    }

    $ids = db_values(
        "SELECT wishlist_items.product_id
        FROM wishlist_items
        INNER JOIN products ON products.id = wishlist_items.product_id
        WHERE " . $where . " AND " . pg_wishlist_product_live_sql() . "
        ORDER BY wishlist_items.added_at DESC, wishlist_items.id DESC");

    return array_map('intval', $ids);
}

/**
 * The action a request names, as the endpoint reads it.
 *
 * @param mixed $value
 * @return string 'state', 'add', 'remove', 'toggle', or '' for anything else
 */
function pg_wishlist_action($value)
{
    $value = is_string($value) ? strtolower(trim($value)) : '';

    return in_array($value, array('state', 'add', 'remove', 'toggle'), true) ? $value : '';
}

/**
 * A product id as a request carries it.
 *
 * @param mixed $value
 * @return int 0 for anything that is not a whole, positive number
 */
function pg_wishlist_product_id($value)
{
    if (is_int($value)) {
        return max(0, $value);
    }

    if (!is_string($value) || (preg_match('/^[0-9]{1,10}$/D', $value) !== 1)) {
        return 0;
    }

    $id = (int) $value;

    return (($id > 0) && ($id <= 4294967295)) ? $id : 0;
}

/**
 * The state a write action leaves a product in.
 *
 * @param string $action  pg_wishlist_action()
 * @param bool   $current whether the product is in the list now (toggle)
 * @return bool|null null for an action that writes nothing
 */
function pg_wishlist_new_state($action, $current)
{
    if ($action === 'add') {
        return true;
    }

    if ($action === 'remove') {
        return false;
    }

    if ($action === 'toggle') {
        return !$current;
    }

    return null;
}

/**
 * Whether the request's address has used up its writes for the window
 * (PG_WISHLIST_WRITE_LIMIT in PG_WISHLIST_WRITE_WINDOW seconds). Counted in
 * the firewall's rate buckets, which work with the firewall off; a refusal is
 * written to the firewall log and data/firewall.log. No offence is counted
 * toward a ban: an office or a mobile network shares one address.
 *
 * The firewall's sensitive limit comes on top of this one: like every POST
 * outside api.php, a write counts toward waf_rate_limit_sensitive (30 a
 * minute per address, shared with sign-in, cart and checkout, staff exempt),
 * which in blocking mode answers waf_deny()'s 429 page and counts an offence.
 *
 * @return bool
 */
function pg_wishlist_write_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'wishlist', PG_WISHLIST_WRITE_LIMIT, PG_WISHLIST_WRITE_WINDOW);

    // The firewall's sweep runs only while the firewall is on; these counters
    // work without it, so they bring their own, as the form limits do.
    if (function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    if (!$exceeded) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-wishlist', 'rate', 5, 'wishlist.php', PG_WISHLIST_WRITE_LIMIT . '/' . (int) round(PG_WISHLIST_WRITE_WINDOW / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('rule' => 'rate-wishlist'));
    }

    return true;
}

/**
 * The statement that removes a deleted account's list.
 *
 * @param mixed $user_id
 * @return string '' for anything that is not an account id, so a visitor
 *                list (user_id 0) is never touched
 */
function pg_wishlist_forget_user_sql($user_id)
{
    $user_id = is_scalar($user_id) ? (int) $user_id : 0;

    if ($user_id <= 0) {
        return '';
    }

    return "DELETE FROM wishlist_items WHERE user_id = '" . $user_id . "'";
}

/**
 * Removes a deleted account's list with the account (delete_users.php,
 * edit_user.php). The daily trim would find it too, but only until the id is
 * used again: MySQL 5.7 hands the highest id out again after a restart, and
 * the next account would inherit the list.
 *
 * @param mixed $user_id
 * @return void
 */
function pg_wishlist_forget_user($user_id)
{
    $delete = pg_wishlist_forget_user_sql($user_id);

    if (($delete !== '') && pg_wishlist_ready()) {
        db($delete);
    }
}

/**
 * The statement that removes a deleted product from every list.
 *
 * @param mixed $product_id
 * @return string '' for anything that is not a product id
 */
function pg_wishlist_forget_product_sql($product_id)
{
    $product_id = is_scalar($product_id) ? (int) $product_id : 0;

    if ($product_id <= 0) {
        return '';
    }

    return "DELETE FROM wishlist_items WHERE product_id = '" . $product_id . "'";
}

/**
 * Removes a product deleted for good from every list (pg_pb_delete_product(),
 * which every permanent delete runs, emptying the Recycle Bin included). The
 * daily trim cannot be left to it: MySQL 5.7 hands the highest id out again
 * after a restart, and once a new product holds the id its rows no longer
 * look orphaned, so every list would show the new product as saved. A
 * product put in the Recycle Bin is not deleted and keeps its place.
 *
 * @param mixed $product_id
 * @return void
 */
function pg_wishlist_forget_product($product_id)
{
    $delete = pg_wishlist_forget_product_sql($product_id);

    if (($delete !== '') && pg_wishlist_ready()) {
        db($delete);
    }
}

/**
 * The moment before which a visitor list was last added to, when no browser
 * can hold its cookie any more.
 *
 * @param int $now
 * @return int
 */
function pg_wishlist_trim_before($now)
{
    return (int) $now - PG_WISHLIST_LIFETIME;
}

/**
 * The trim's three reads, each giving the next batch to remove: visitor lists
 * whose last product was saved before $before (by cookie_token), rows of
 * products that no longer exist and rows of accounts that no longer exist
 * (by id). A product in the Recycle Bin still exists: restoring it brings it
 * back to the lists it was in.
 *
 * @param int $before pg_wishlist_trim_before()
 * @param int $rows   batch size
 * @return array kind => array(column, SELECT)
 */
function pg_wishlist_trim_queries($before, $rows)
{
    $rows = max(1, (int) $rows);

    return array(
        'visitors' => array('cookie_token',
            "SELECT cookie_token
            FROM wishlist_items
            WHERE user_id = '0'
            GROUP BY cookie_token
            HAVING MAX(added_at) < '" . (int) $before . "'
            LIMIT " . $rows),

        'products' => array('id',
            "SELECT wishlist_items.id
            FROM wishlist_items
            LEFT JOIN products ON products.id = wishlist_items.product_id
            WHERE products.id IS NULL
            LIMIT " . $rows),

        'accounts' => array('id',
            "SELECT wishlist_items.id
            FROM wishlist_items
            LEFT JOIN user ON user.user_id = wishlist_items.user_id
            WHERE (wishlist_items.user_id > 0) AND (user.user_id IS NULL)
            LIMIT " . $rows),
    );
}

/**
 * The statement that removes one batch the trim read.
 *
 * @param string $column 'id' or 'cookie_token'
 * @param array  $values what the read gave
 * @return string '' when there is nothing to remove
 */
function pg_wishlist_trim_delete_sql($column, array $values)
{
    $list = array();

    foreach ($values as $value) {
        if ($column === 'id') {
            if ((int) $value > 0) {
                $list[(int) $value] = "'" . (int) $value . "'";
            }
        } elseif (is_string($value) && (preg_match('/^[a-f0-9]{0,64}$/D', $value) === 1)) {
            $list[$value] = "'" . $value . "'";
        }
    }

    if (!$list) {
        return '';
    }

    if ($column === 'id') {
        return "DELETE FROM wishlist_items WHERE id IN (" . implode(', ', $list) . ")";
    }

    return "DELETE FROM wishlist_items WHERE (user_id = '0') AND (cookie_token IN (" . implode(', ', $list) . "))";
}

/**
 * Removes visitor lists no cookie can name any more and the rows of deleted
 * products and accounts, in batches on indexed columns.
 *
 * @param int|null $now null for the time now
 * @return int the rows removed
 */
function pg_wishlist_trim($now = null)
{
    if (!pg_wishlist_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;
    $removed = 0;

    foreach (pg_wishlist_trim_queries(pg_wishlist_trim_before($now), PG_WISHLIST_TRIM_ROWS) as $query) {
        list($column, $select) = $query;

        for ($batch = 0; $batch < PG_WISHLIST_TRIM_BATCHES; $batch++) {
            $values = db_values($select);
            $delete = pg_wishlist_trim_delete_sql($column, $values);

            if ($delete === '') {
                break;
            }

            db($delete);
            $removed += max(0, (int) mysqli_affected_rows(db::$con));

            if (count($values) < PG_WISHLIST_TRIM_ROWS) {
                break;
            }
        }
    }

    return $removed;
}

/**
 * Where the time of the last trim is kept. A small file under data/temp:
 * swept with the rest of it, the next tick just trims again.
 *
 * @return string
 */
function pg_wishlist_trim_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/wishlist_trimmed.txt';
}

/**
 * Whether the daily trim is due.
 *
 * @param int $last when it last ran, 0 for never
 * @param int $now
 * @return bool
 */
function pg_wishlist_trim_due($last, $now)
{
    return ((int) $now - (int) $last) >= 86400;
}

/**
 * The general job's daily trim (pg_wishlist_trim()). A tick between passes
 * reads one small file.
 *
 * @return int the rows removed
 */
function pg_wishlist_trim_daily()
{
    if (!pg_wishlist_ready()) {
        return 0;
    }

    $path = pg_wishlist_trim_state_path();
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;

    if (!pg_wishlist_trim_due($last, time())) {
        return 0;
    }

    @file_put_contents($path, (string) time(), LOCK_EX);

    return pg_wishlist_trim();
}

/**
 * The favourite hearts' script (assets/js/pg_wishlist.js), for a page whose
 * HTML holds a heart: a catalog widget's button bound to catalog_wishlist
 * or wishlist, which the binding pass marks with data-pg-wishlist. Asked
 * once every shared component and system widget is drawn in
 * (get_page_content.php), so the file follows the heart wherever it was
 * placed. Its settings, the endpoint's address and the words the script may
 * show in the page's language, are the same for every visitor: the page can
 * still come from the page cache, and the script asks the endpoint for the
 * visitor's list.
 *
 * @param string      $content page HTML
 * @param string|null $src     the script's address; null builds it from the
 *                             software directory and the file's time
 * @param string|null $url     the endpoint's address; null builds it
 * @return string
 */
function pg_wishlist_inject($content, $src = null, $url = null)
{
    if (!preg_match('/\sdata-pg-wishlist="/', $content)) {
        return $content;
    }

    if (strpos($content, '/assets/js/pg_wishlist.js') !== false) {
        return $content;
    }

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_wishlist.js?v='
            . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_wishlist.js');
    }

    if ($url === null) {
        $url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/wishlist.php';
    }

    $settings = array(
        'url'    => (string) $url,
        'labels' => array(
            // Read out when a change fails and the server gave no words.
            'failed' => lang('Something went wrong. Please try again.'),
        ),
    );

    $file = '<script>window.pgWishlist = '
        . json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . ';</script>' . "\n"
        . '<script src="' . h($src) . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');

    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}
