<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Database access, escaping, JSON, dates, strings, HTTP answers and the other helpers every screen relies on. Loaded first.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// Fixes an issue that broke access to the settings page that occurred in some php versions.(php version < 7.3)
if (!function_exists("array_key_last")) {
    function array_key_last($array)
    {
        if (!is_array($array) || empty($array)) {
            return NULL;
        }
        return array_keys($array)[count($array) - 1];
    }
}

function array_map_recursive($callback, $array)
{
    // If not an array, apply callback directly (handles scalars and objects gracefully).
    if (!is_array($array)) {
        return is_scalar($array) || is_null($array) ? $callback($array) : $array;
    }
    $r = array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $r[$key] = array_map_recursive($callback, $value);
        } else {
            // apply callback for scalar values; leave objects/other types untouched
            if (is_scalar($value) || is_null($value)) {
                $r[$key] = $callback($value);
            } else {
                $r[$key] = $value;
            }
        }
    }
    return $r;
}
function array_stripslashes($array)
{
    if (is_array($array)) {
        return array_map_recursive('stripslashes', $array);
    }
    if (is_scalar($array) || is_null($array)) {
        return stripslashes($array);
    }
    return $array;
}
function db_connect()
{
    // Create a class to hold the MySQL connection object so that we can access it anywhere.
    // We check if class already exists, because something might run this function multiple times.
    if (!class_exists('db')) {
        class db
        {
            public static $con;
        }
    }

    // If there's already a working connection, return early.
    if (isset(db::$con) && db::$con && function_exists('mysqli_ping')) {
        $ping = @mysqli_ping(db::$con);
        if ($ping) {
            return;
        }
    }

    // Attempt connection.
    //
    // The try/catch is load-bearing, not defensive decoration. Since PHP 8.1
    // mysqli reports errors as exceptions by default, and @ suppresses
    // diagnostics but not exceptions -- so on any modern PHP the else branch
    // below had become unreachable and a failed connection ended the request
    // as an uncaught fatal with a full stack trace. During a connection
    // exhaustion incident that is one stack trace per request, which fills the
    // error log faster than anyone can read it.
    $connect_errno = 0;
    $connect_error = '';

    try {
        db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    } catch (Exception $e) {
        db::$con = false;
        $connect_errno = (int) $e->getCode();
        $connect_error = $e->getMessage();
    }

    if (db::$con) {
        // initialize charset and session settings
        init_mysql_charset();
        if (!defined('DB_CONNECTED')) {
            define('DB_CONNECTED', true);
        }

        // Healthy again: let the next request through without the stat-plus-503
        // detour. No-op unless a breaker file is actually there.
        if (function_exists('pg_db_guard_clear')) {
            pg_db_guard_clear();
        }
    } else {
        if (!defined('DB_CONNECTED')) {
            define('DB_CONNECTED', false);
        }

        // Prefer to report mysqli error when available
        if ($connect_errno === 0 && function_exists('mysqli_connect_errno')) {
            $connect_errno = (int) mysqli_connect_errno();
        }

        if ($connect_error === '' && function_exists('mysqli_connect_error')) {
            $connect_error = (string) mysqli_connect_error();
        }

        // Overload and unreachable-server failures are transient, and every
        // further attempt is work the saturated server spends rejecting
        // instead of recovering. Back off so the pool can drain, and answer
        // 503 + Retry-After: a crawler told to come back later keeps the
        // ranking a blank page would cost.
        //
        // Credentials and missing databases deliberately fall through to the
        // message below. Waiting does not fix those, and hiding them behind
        // "try again later" hides them from the one person who can.
        if (function_exists('pg_db_guard_is_overload') && pg_db_guard_is_overload($connect_errno)) {
            pg_db_guard_trip();
            pg_db_guard_log('database unavailable (' . $connect_errno . '): ' . $connect_error);
            pg_db_guard_unavailable();
        }

        $err = $connect_error;
        output_error(lang('Sorry, this website could not connect to the database. The server administrator should check the status of the database.  If there is not a problem with the database, then the server administrator should verify that the database information in the config.php file in the software directory is correct.') . ($err ? ' (' . h($err) . ')' : ''));
    }

    // Pin the session sql_mode instead of inheriting the server default, which differs
    // between MySQL 5.7, MySQL 8.0 and MariaDB. Pinegrap relies on non-strict writes,
    // so only NO_ENGINE_SUBSTITUTION is kept.
    if (db::$con) {
        @mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    }

    // Legacy mysql extension support (only if explicitly enabled and functions exist).
    if (defined('DB_LEGACY') && DB_LEGACY === true && function_exists('mysql_connect')) {
        $link = @mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        if ($link && @mysql_select_db(DB_DATABASE)) {
            $mysql_version = preg_replace('#[^0-9\.]#', '', mysql_get_server_info());
            if (version_compare($mysql_version, '5.5.3', '>=') === true) {
                $mysql_character_set = 'utf8mb4';
            } else {
                $mysql_character_set = 'utf8';
            }
            if (function_exists('mysql_set_charset')) {
                @mysql_set_charset($mysql_character_set);
            }
            if (function_exists('mysql_query')) {
                @mysql_query("SET NAMES '" . $mysql_character_set . "' COLLATE '" . $mysql_character_set . "_unicode_ci'");
            }
        }
    }
}

function init_mysql_charset()
{
    if (!isset(db::$con) || !db::$con) {
        return;
    }
    $mysql_version = @preg_replace('#[^0-9\.]#', '', @mysqli_get_server_info(db::$con));
    if ($mysql_version === null || $mysql_version === '') {
        $mysql_character_set = 'utf8';
    } else {
        if (version_compare($mysql_version, '5.5.3', '>=') === true) {
            $mysql_character_set = 'utf8mb4';
        } else {
            $mysql_character_set = 'utf8';
        }
    }

    // Use mysqli_set_charset if available
    if (function_exists('mysqli_set_charset')) {
        @mysqli_set_charset(db::$con, $mysql_character_set);
    }

    // Ensure connection variables are set consistently (collation as unicode_ci)
    @mysqli_query(db::$con, "SET NAMES '" . $mysql_character_set . "' COLLATE '" . $mysql_character_set . "_unicode_ci'");
}

// Use this to run any type of general db query (e.g. select, insert, update, etc.).
function db($query)
{
    if (!isset(db::$con) || !db::$con) {
        output_error(lang('No database connection.'));
    }

    $result = @mysqli_query(db::$con, $query);
    if ($result === false) {
        // Provide mysqli error to output_error for debugging (output_error may suppress details based on DEBUG).
        $err = mysqli_error(db::$con);
        // While the upgrade runner is active (install/index.php, includes/migrations/runner.php)
        // a failed schema statement can be waved through as "already applied", and anything
        // else becomes an exception the runner reports with the statement.  Outside a run the
        // hook is inactive and the failure is fatal, as it always was.
        if ((function_exists('install_query_failed')) && (install_query_failed($query, mysqli_errno(db::$con), $err))) {
            return true;
        }
        output_error(lang('Query failed.') . ' ' . h($err));
    }

    // If this was a query like an insert or update and it was successful (true), return the result (true).
    if ($result === true) {
        return true;
    }

    // Otherwise a result set should have been returned.
    $number_of_rows = mysqli_num_rows($result);

    if (!$number_of_rows) {
        return '';
    }

    if ($number_of_rows == 1) {
        if (mysqli_num_fields($result) == 1) {
            $row = mysqli_fetch_row($result);
            return $row[0];
        } else {
            return mysqli_fetch_assoc($result);
        }
    } else {
        if (mysqli_num_fields($result) == 1) {
            $values = array();
            while ($row = mysqli_fetch_row($result)) {
                $values[] = $row[0];
            }
            return $values;
        } else {
            $items = array();
            while ($item = mysqli_fetch_assoc($result)) {
                $items[] = $item;
            }
            return $items;
        }
    }
}

function db_value($query)
{
    if (!isset(db::$con) || !db::$con) {
        output_error(lang('No database connection.'));
    }
    $result = @mysqli_query(db::$con, $query);
    if ($result === false) {
        $err = mysqli_error(db::$con);
        // While the upgrade runner is active (install/index.php, includes/migrations/runner.php)
        // a failed schema statement can be waved through as "already applied", and anything
        // else becomes an exception the runner reports with the statement.  Outside a run the
        // hook is inactive and the failure is fatal, as it always was.
        if ((function_exists('install_query_failed')) && (install_query_failed($query, mysqli_errno(db::$con), $err))) {
            return null;
        }
        output_error(lang('Query failed.') . ' ' . h($err));
    }
    $row = mysqli_fetch_row($result);
    return isset($row[0]) ? $row[0] : null;
}

function db_values($query)
{
    if (!isset(db::$con) || !db::$con) {
        output_error(lang('No database connection.'));
    }
    $result = @mysqli_query(db::$con, $query);
    if ($result === false) {
        $err = mysqli_error(db::$con);
        // While the upgrade runner is active (install/index.php, includes/migrations/runner.php)
        // a failed schema statement can be waved through as "already applied", and anything
        // else becomes an exception the runner reports with the statement.  Outside a run the
        // hook is inactive and the failure is fatal, as it always was.
        if ((function_exists('install_query_failed')) && (install_query_failed($query, mysqli_errno(db::$con), $err))) {
            return array();
        }
        output_error(lang('Query failed.') . ' ' . h($err));
    }
    $values = array();
    while ($row = mysqli_fetch_row($result)) {
        $values[] = $row[0];
    }
    return $values;
}

function db_item($query)
{
    if (!isset(db::$con) || !db::$con) {
        output_error(lang('No database connection.'));
    }
    $result = @mysqli_query(db::$con, $query);
    if ($result === false) {
        $err = mysqli_error(db::$con);
        // While the upgrade runner is active (install/index.php, includes/migrations/runner.php)
        // a failed schema statement can be waved through as "already applied", and anything
        // else becomes an exception the runner reports with the statement.  Outside a run the
        // hook is inactive and the failure is fatal, as it always was.
        if ((function_exists('install_query_failed')) && (install_query_failed($query, mysqli_errno(db::$con), $err))) {
            return null;
        }
        output_error(lang('Query failed.') . ' ' . h($err));
    }
    return mysqli_fetch_assoc($result);
}

function db_items($query, $key_column = '')
{
    if (!isset(db::$con) || !db::$con) {
        output_error(lang('No database connection.'));
    }
    $result = @mysqli_query(db::$con, $query);
    if ($result === false) {
        $err = mysqli_error(db::$con);
        // While the upgrade runner is active (install/index.php, includes/migrations/runner.php)
        // a failed schema statement can be waved through as "already applied", and anything
        // else becomes an exception the runner reports with the statement.  Outside a run the
        // hook is inactive and the failure is fatal, as it always was.
        if ((function_exists('install_query_failed')) && (install_query_failed($query, mysqli_errno(db::$con), $err))) {
            return array();
        }
        output_error(lang('Query failed.') . ' ' . h($err));
    }
    $items = array();
    if ($key_column != '') {
        while ($item = mysqli_fetch_assoc($result)) {
            if (isset($item[$key_column])) {
                $items[$item[$key_column]] = $item;
            } else {
                $items[] = $item;
            }
        }
    } else {
        while ($item = mysqli_fetch_assoc($result)) {
            $items[] = $item;
        }
    }
    return $items;
}

function h($content)
{
    return htmlspecialchars($content, ENT_QUOTES | ENT_HTML401, 'UTF-8');
}

function escape($string)
{
    if (isset(db::$con) && db::$con) {
        return mysqli_real_escape_string(db::$con, $string);
    }
    // Fallback for when DB is not connected: best-effort escaping
    return addslashes($string);
}

function e($string)
{
    return escape($string);
}

// Whitelists an ORDER BY direction that came from user input (?order= or a
// session value filled from it). escape() cannot protect a bare keyword
// position, so only the literals 'asc' and 'desc' are ever returned; anything
// else becomes $default. Pass '' as $default when the caller keeps its own
// "direction not set" fallback logic.
function sql_order_direction($direction, $default = 'asc')
{
    $direction = strtolower(trim((string) $direction));
    if (($direction === 'asc') || ($direction === 'desc')) {
        return $direction;
    }
    return $default;
}

function escape_like($string)
{
    $string = str_replace('%', '\%', $string);
    $string = str_replace('_', '\_', $string);
    return $string;
}

function escape_javascript($string)
{
    if (!is_scalar($string) && !is_null($string)) {
        return $string;
    }
    // escape backslashes
    $string = str_replace('\\', '\\\\', $string);
    // escape single quotes
    $string = str_replace("'", "\\'", $string);
    // escape double quotes
    $string = str_replace('"', '\\"', $string);
    // escape new line
    $string = str_replace(array("\r\n", "\n", "\r"), '\n', $string);
    // prevent breaking out of script tag
    $string = str_ireplace('</script>', '<\/script>', $string);
    return $string;
}

function escape_csv($string)
{
    return str_replace('"', '""', $string);
}

/**
 * Make a redirect target safe to place after URL_SCHEME . HOSTNAME.
 *
 * Around seventy places in this software finish a form by sending the visitor
 * back where they came from, written as:
 *
 *     header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);
 *
 * With no check on $send_to that concatenation is an open redirect, because
 * the attacker controls what follows the host name rather than what follows a
 * slash:
 *
 *     send_to = /account        https://example.com/account          intended
 *     send_to = @evil.com/x     https://example.com@evil.com/x       everything
 *                                                                    before @ is
 *                                                                    userinfo -
 *                                                                    the browser
 *                                                                    goes to
 *                                                                    evil.com
 *     send_to = .evil.com/x     https://example.com.evil.com/x       attacker's
 *                                                                    own domain
 *
 * The link starts with the real site in both attacks, which is the whole point:
 * a phishing page reached through a URL that begins with the shop the customer
 * trusts, from a sign-in form they were about to type a password into.
 *
 * The rule is a prefix test, and only a prefix test. Once a value starts with a
 * single slash the authority is settled and nothing later in the string can
 * move it, so everything after the first character is passed through untouched:
 *
 *   - [ and ] survive. They are legal in a path, they appear in array-style
 *     query parameters (fifty-four places in this software use name="x[]"), and
 *     pg_ascii_file_name() deliberately keeps them in file names for version
 *     suffixes. Stripping them is the classic way this fix gets broken.
 *   - ? # & = % and UTF-8 survive. send_to routinely carries a query string and
 *     a fragment: /product/x?previous_url_id=2#software_add_comment
 *
 * What is rejected:
 *
 *   - anything not starting with /            (the @evil.com and .evil.com cases)
 *   - // and /\                               (protocol-relative; a browser reads
 *                                              the rest as a host name, and \ is
 *                                              normalised to / on the way)
 *   - control characters                      (CR/LF would split the header;
 *                                              PHP blocks that since 5.1.2, but
 *                                              a guard that depends on another
 *                                              guard is not one)
 *
 * escape_url() below is NOT the same thing and is deliberately left alone: it
 * validates URLs for display in href attributes, where an external address is
 * often correct - a referring page, a calendar event link. Absolute URLs are
 * legitimate there and forbidden here.
 *
 * @param  mixed  $url      Untrusted redirect target
 * @param  string $fallback Where to send instead; site root when empty
 * @return string           A path beginning with a single slash
 */
function pg_safe_redirect_path($url, $fallback = '')
{
    if ($fallback === '') {
        $fallback = defined('PATH') ? PATH : '/';
    }

    if (!is_scalar($url)) {
        return $fallback;
    }

    $url = trim((string) $url);

    if ($url === '') {
        return $fallback;
    }

    // Header splitting, and anything else that has no business in a URL.
    if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        return $fallback;
    }

    if (mb_substr($url, 0, 1) !== '/') {
        return $fallback;
    }

    $second = mb_substr($url, 1, 1);

    if (($second === '/') || ($second === '\\')) {
        return $fallback;
    }

    return $url;
}

function escape_url($url)
{
    if (!is_scalar($url) || $url === '') {
        return false;
    }
    // Allow common safe URL forms
    if ((mb_substr($url, 0, 1) === '/') || (mb_substr($url, 0, 3) === '../') || (mb_substr($url, 0, 2) === '//') || (mb_substr($url, 0, 7) === 'http://') || (mb_substr($url, 0, 8) === 'https://')) {
        return $url;
    }
    return false;
}

function unhtmlspecialchars($string)
{
    if (function_exists('htmlspecialchars_decode')) {
        return htmlspecialchars_decode($string, ENT_QUOTES);
    } else {
        $string = str_replace('&amp;', '&', $string);
        $string = str_replace('&quot;', '"', $string);
        $string = str_replace('&#39;', '\'', $string);
        $string = str_replace('&#039;', '\'', $string);
        $string = str_replace('&lt;', '<', $string);
        $string = str_replace('&gt;', '>', $string);
        return $string;
    }
}

function mysqli_fetch_items($result)
{
    $items = array();
    if (!($result instanceof mysqli_result)) {
        return $items;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}
// Used for critical errors which aborts any page rendering
// that we might have done so far and shows an error.
/**
 * Whether a page is being rendered by the SEO structure pass right now.
 *
 * Set around the render in pg_seo_analyze_page() and read by output_error():
 * inside the pass, a render that cannot complete has to surface as an
 * exception the pass can catch, not as an error screen followed by exit().
 * The pass walks hundreds of pages in one process, and one page that cannot
 * render standalone must cost that page its score, not the run.
 *
 * @param bool|null $set
 * @return bool
 */
function pg_seo_rendering($set = null)
{
    static $value = false;

    if ($set !== null) {
        $value = (bool) $set;
    }

    return $value;
}

/**
 * While this is on, output_error() throws instead of printing its screen.
 *
 * For a caller that is not a browser asking for a page: a JSON endpoint has to
 * answer in JSON, and an HTML error screen reaches it as "unexpected token <"
 * -- the operator is then told nothing at all. The endpoint turns this on,
 * catches, and reports the message its own way. Off by default, so an ordinary
 * request still gets the error screen.
 *
 * @param bool|null $set
 * @return bool
 */
function pg_error_throws($set = null)
{
    static $value = false;

    if ($set !== null) {
        $value = (bool) $set;
    }

    return $value;
}


function output_error($error_message, $response_code = 0)
{
    // Not an error screen when the SEO pass is rendering: that pass runs
    // hundreds of renders in one process and would otherwise end - with this
    // page's error screen as its output - on the first page that cannot be
    // built standalone. Thrown instead, caught by the pass, recorded against
    // the page. Same for a caller that asked for JSON.
    if (pg_seo_rendering() || pg_error_throws()) {
        throw new RuntimeException(trim(strip_tags((string) $error_message)));
    }

    set_response_code($response_code);
    $mysql_error = '';
    if (db::$con) {
        $mysql_error = mysqli_error(db::$con);
    }
    // If debug is enabled or if the error was generated by the install/update script
    // and there is a MySQL error, then output the error.
    if (((defined('DEBUG') and DEBUG) || (defined('INSTALL_OR_UPDATE') and INSTALL_OR_UPDATE)) && ($mysql_error)) {
        $debug_message = h($mysql_error) . '<br />';
    } elseif ($mysql_error) {
        $debug_message = lang('The administrator can turn on Verbose Database Errors in the settings in order to see more information about this error the next time it occurs.');
    } else {
        $debug_message = '';
    }

    $content = '<div style="color: red">' . lang('Error') . ': ' . $error_message . '<br />' . $debug_message . '</div>';

    // If the db connection failed or there was an error, then just output error message content
    // without error page, in order to avoid infinite errors.
    if (!db::$con or $mysql_error != '') {
        echo $content;
        exit();
        // else there is not a MySQL error, so output error message content on error page
    } else {
        // If we don't know if the user is logged in (maybe because the error happened
        // early), then set that the user is not logged,
        // We need to set this constant, because other logic might run
        // during the output of the error screen, that assumes the constant is set.
        // For example, we had issue where setTimezone would try to use USER_TIMEZONE
        // and a PHP error would occur, because the constant was not set.
        if (!defined('USER_LOGGED_IN')) {
            define('USER_LOGGED_IN', false);
        }
        // The error screen renders the full front-end page pipeline, and that
        // pipeline reads $_SESSION in a lot of places. output_error() can fire
        // before init.php gets as far as session_start() (a settings or database
        // failure, a redirect check that aborts), and then $_SESSION does not
        // exist at all. Define it as an empty array so those reads see the same
        // "nothing in the session yet" state a brand new visitor produces,
        // instead of "Undefined global variable $_SESSION". We exit() right
        // after this, so this never races a later session_start().
        if (!isset($_SESSION)) {
            $_SESSION = array();
        }
        echo get_error_screen($content);
        exit();
    }
}
// The following function is used to return our standard error styling around
// an error message.  This is used when we want to show an error on the page
// that generated the error, so an editor can continue to interact with the page
// via the toolbar and etc., instead of using output_error() which is generally
// used for critical errors which aborts the current page rendering
// and shows the error page, which does not have a toolbar and etc.
function error($content, $response_code = 0)
{
    set_response_code($response_code);
    return '<div class="software_error">
            <img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/icon_error.png" alt="Error" title="" class="icon" style="float: none; display: inline-block; height: 1em; width: 1em; min-height: 16px; min-width: 16px">
            <div style="display: inline; vertical-align: top">
                <span class="description">' . lang('An error occurred') . ':</span> <span class="error">' . $content . '</span>
            </div>
        </div>';
}
// we should check sometime to see if we can use the built-in function preg_quote() instead of this
// PHP 4 and 5 both appear to support preg_quote()
// if we start using preg_quote(), make sure to also pass the delimiter also (e.g. preg_quote($string, '/'))
function escape_regex($string)
{
    // set patterns to replace
    $patterns = array(
        '/\//',
        '/\^/',
        '/\./',
        '/\$/',
        '/\|/',
        '/\(/',
        '/\)/',
        '/\[/',
        '/\]/',
        '/\*/',
        '/\+/',
        '/\?/',
        '/\{/',
        '/\}/',
        '/\,/'
    );
    // set the replacement characters
    $replace = array(
        '\/',
        '\^',
        '\.',
        '\$',
        '\|',
        '\(',
        '\)',
        '\[',
        '\]',
        '\*',
        '\+',
        '\?',
        '\{',
        '\}',
        '\,'
    );
    // return the modified string
    return preg_replace($patterns, $replace, $string);
}

/**
 * Where an edit screen sends the operator back to.
 *
 * The screens that open from the File Manager, the store and the user list all
 * carry "send_to" so that Cancel and the save both land back where the operator
 * actually came from, instead of the default list. This is the one place that
 * reads it, so every screen agrees on what a valid one is.
 *
 * It is a request value, so it may only ever be a path on this site: one leading
 * slash and no more. An absolute URL, a "//host" that borrows our scheme, or a
 * "javascript:" that would run the moment Cancel is clicked are all refused and
 * the screen's own default is used instead. A newline is refused for the same
 * reason a header must not carry one.
 *
 * @param  string $fallback where to go when there is no usable send_to
 * @return string
 */
function pg_send_to_url($fallback = '')
{
    $send_to = isset($_REQUEST['send_to']) ? trim((string) $_REQUEST['send_to']) : '';

    if ($send_to === '') {
        return $fallback;
    }

    if ((strpbrk($send_to, "\r\n") !== false) || (mb_substr($send_to, 0, 1) !== '/') || (mb_substr($send_to, 0, 2) === '//')) {
        return $fallback;
    }

    return $send_to;
}
// Create function that will allow us to get a time, like "5 days ago", from a timestamp.
// Properties:
// timestamp: The Unix timestamp for the time that you want to compare to now.
// format: "html" (default) or "plain_text"
// type: "date_and_time" (default) or "date".  This property is used in order to indicate
// if the timestamp that was passed was for a general date or for a specific date & time.
// This information is used in order to determine whether a date & time or just a date is
// outputted for the absolute time tooltip.
// timezone_type: "user" (default) or "site".  This property allows you output a time
// for the user's timezone (if user has one set) or the site's timezone.
function get_relative_time($properties)
{
    $timestamp = '';
    if (isset($properties['timestamp'])) {
        $timestamp = $properties['timestamp'];
    }
    $format = '';
    if (isset($properties['format'])) {
        $format = $properties['format'];
    }
    $type = '';
    if (isset($properties['type'])) {
        $type = $properties['type'];
    }

    // If the timestamp is blank, then set it to 0.  We have to do this in order
    // to avoid PHP exceptions when we call new DateTime('@' . $timestamp) below.
    // This can happen when timestamp has a null value in the db for some reason.
    if ($timestamp == '') {
        $timestamp = 0;
    }
    // If the type is "date_and_time" and if the timezone type is not "site",
    // and the visitor is logged in, and the user has a specific timezone set,
    // then get time in user's timezone.
    if (($type != 'date') && (isset($properties['timezone_type']) && $properties['timezone_type'] != 'site') && (USER_LOGGED_IN) && (USER_TIMEZONE != '')) {
        $timezone_type = 'user';
        // Otherwise get the time for the site's timezone.
    } else {
        $timezone_type = 'site';
    }
    $current_timestamp = time();
    // If the time format is twelve hour system, then use that format.
    if (TIME_FORMAT == 'twelve_hours') {
        $hour_system_format = 'g:i A';
        // Otherwise the time format is twenty hour system, so use that format.
    } else {
        $hour_system_format = 'H:i';
    }
    // if the timestamp is in the past or right now, then prepare values for the past
    if ($current_timestamp >= $timestamp) {
        $difference_in_seconds = $current_timestamp - $timestamp;
        $past_or_future_message = lang('ago');
        // else the timestamp is in the future, so prepare values for the future
    } else {
        $difference_in_seconds = $timestamp - $current_timestamp;
        $past_or_future_message = lang('from now');
    }
    // if the difference is less than 1 minute, then return "a moment ago/from now"
    if ($difference_in_seconds < 60) {
        $relative_time = lang(array('string' => 'A moment {var:1}', 'vars' => array($past_or_future_message)));
        // else if the difference is less than 1 hour, then return minutes
    } else if ($difference_in_seconds < 3600) {
        $difference_in_minutes = round($difference_in_seconds / 60);
        $plural_suffix = '';
        // if the difference is more than 1 minute, then prepare plural suffix
        if ($difference_in_minutes > 1) {
            $plural_suffix = 's';
        }
        $relative_time = lang(array('string' => '{var:1} minute{suffix:1} {var:2}', 'vars' => array($difference_in_minutes, $past_or_future_message), 'suffix' => array($plural_suffix)));
        // else if the difference is less than 1 day, then return hours
    } else if ($difference_in_seconds < 86400) {
        $difference_in_hours = round($difference_in_seconds / 60 / 60);
        $plural_suffix = '';
        // if the difference is more than 1 hour, then prepare plural suffix
        if ($difference_in_hours > 1) {
            $plural_suffix = 's';
        }
        $relative_time = lang(array('string' => '{var:1} hour{suffix:1} {var:2}', 'vars' => array($difference_in_hours, $past_or_future_message), 'suffix' => array($plural_suffix)));
        // else if the difference is less than 1 month, then return days.
    } else if ($difference_in_seconds < 2592000) {
        // Please note that we calculate the difference in calendar days below,
        // based on the current date for the appropriate time zone.
        // We no longer calculate the difference in days by considering a day to be 24 hours.
        // For example, if the logged time is Mon @ 11:59 PM and the current time is Wed @ 12:00 AM,
        // that is considered 2 days, even though it technically about 1 day of time.
        // If we should use site's timezone, then do that.
        if ($timezone_type == 'site') {
            $current_date = date('Y-m-d', $current_timestamp);
            $date = date('Y-m-d', $timestamp);
            $current_date_timestamp = strtotime($current_date);
            $date_timestamp = strtotime($date);
            // Otherwise we should use user's timezone, so do that.
        } else {
            $current_date = new DateTime('@' . $current_timestamp);
            $current_date->setTimezone(new DateTimeZone(USER_TIMEZONE));
            $date = new DateTime('@' . $timestamp);
            $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
            $current_date_timestamp = strtotime($current_date->format('Y-m-d'));
            $date_timestamp = strtotime($date->format('Y-m-d'));
        }
        $difference_in_days = round(abs($current_date_timestamp - $date_timestamp) / 86400);
        $plural_suffix = '';
        // if the difference is more than 1 day, then prepare plural suffix
        if ($difference_in_days > 1) {
            $plural_suffix = 's';
        }
        $relative_time = lang(array('string' => '{var:1} day{suffix:1} {var:2}', 'vars' => array($difference_in_days, $past_or_future_message), 'suffix' => array($plural_suffix)));
        // else the difference is at least 1 month, so return the actual date.
    } else {
        // If the date format is month and then day, then use that format.
        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'M j';
            // Otherwise the date format is day and then month, so use that format.
        } else {
            $month_and_day_format = 'j M';
        }
        // If we should output date for site's timezone, then do that.
        if ($timezone_type == 'site') {
            $relative_time = date($month_and_day_format . ' Y', $timestamp);
            // Otherwise we should output date for user's timezone, so do that.
        } else {
            $date = new DateTime('@' . $timestamp);
            $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
            $relative_time = $date->format($month_and_day_format . ' Y');
        }
    }

    $search = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
    $replace = array(lang("Jan"), lang("Feb"), lang("Mar"), lang("Apr"), lang("May"), lang("Jun"), lang("Jul"), lang("Aug"), lang("Sep"), lang("Oct"), lang("Nov"), lang("Dec"));
    // strtr(), not str_replace().
    //
    // str_replace() with two arrays walks the pairs one after another over the
    // result of the last one, so a short name eats a long one and a finished
    // translation gets translated again. Both happened here: 'Mo' ran before
    // 'Mon', turning "Mon" into "Pt" plus a stray "n" -> "Ptn"; 'Sa' then found
    // the "Sa" inside "Salı" and made it "Ctlı"; and 'May' matched the "May" it
    // had just written into "Mayıs", giving "Mayısıs".
    //
    // strtr() with one map takes the longest key that fits at each position and
    // never looks at what it has written, which is exactly the rule a name
    // table needs.
    $relative_time = strtr($relative_time, array_combine($search, $replace));
    // If the format is plain text, then just return the relative time without a time tag around it.
    if ($format == 'plain_text') {
        return $relative_time;
        // Otherwise the format is html, so add time tag with tooltip for absolute time around relative time.
    } else {
        // If the date format is month and then day, then use that format.
        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'M j';
            // Otherwise the date format is day and then month, so use that format.
        } else {
            $month_and_day_format = 'j M';
        }
        // If the type is just date, then prepare datetime and title attributes with just the date.
        if ($type == 'date') {
            $datetime = date('Y-m-d', $timestamp);
            $title = date('D ' . $month_and_day_format . ' Y', $timestamp);
            // Otherwise the type is date & time, so prepare datetime and title attributes with both the date and time.
        } else {
            $datetime = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
            // If we should output time for site's timezone, then do that.
            if ($timezone_type == 'site') {
                $title = date('D ' . $month_and_day_format . ' Y ' . $hour_system_format . ' T', $timestamp);
                // Otherwise we should output time for user's timezone, so do that.
            } else {
                $date = new DateTime('@' . $timestamp);
                $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
                $title = $date->format('D ' . $month_and_day_format . ' Y ' . $hour_system_format . ' T');
            }
        }
        return '<time datetime="' . $datetime . '" title="' . $title . '">' . $relative_time . '</time>';
    }
}
// Create function that will allow us to get an absolute time (e.g. Mon Feb 3 2020 12:00 PM CST).
// Properties:
// timestamp: The Unix timestamp for the absolute time.
// format: "html" (default) or "plain_text".
// type: "date_and_time" (default) or "time" or "date".  This property is used in order to indicate
// if the timestamp that was passed was for a general date or for a specific date & time.
// This information is used in order to determine whether a date & time or time or just a date is outputted.
// size: "short" (default) or "long".
// timezone_type: "user" (default) or "site".  This property allows you output a time
// for the user's timezone (if user has one set) or the site's timezone.
// year: true (default) or false. Include year or not.  There are some types of dates, like
// estimated shipping delivery date, where the year is obvious and unnecessary.
function get_absolute_time($properties)
{
    $timestamp = '';
    if (isset($properties['timestamp'])) {
        $timestamp = $properties['timestamp'];
    }
    $format = '';
    if (isset($properties['format'])) {
        $format = $properties['format'];
    }
    $type = '';
    if (isset($properties['type'])) {
        $type = $properties['type'];
    }
    $size = '';
    if (isset($properties['size'])) {
        $size = $properties['size'];
    }
    if (isset($properties['year'])) {
        $year = $properties['year'];
    } else {
        $year = true;
    }
    // If the timestamp is blank, then set it to 0.  We have to do this in order
    // to avoid PHP exceptions when we call new DateTime('@' . $timestamp) below.
    // This can happen when timestamp has a null value in the db for some reason.
    if ($timestamp == '') {
        $timestamp = 0;
    }
    // If the time format is twelve hour system, then use that format.
    if (TIME_FORMAT == 'twelve_hours') {
        $hour_system_format = 'g:i A';
        // Otherwise the time format is twenty hour system, so use that format.
    } else {
        $hour_system_format = 'H:i';
    }
    // If the type is just date, then just get a date.
    if ($type == 'date') {
        if ($size == 'long') {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'F j';
                // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j F';
            }
            $year_format = '';
            if ($year) {
                $year_format = ', Y';
            }
            $absolute_time = date('l, ' . $month_and_day_format . $year_format, $timestamp);
        } else {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'M j';
                // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j M';
            }
            $year_format = '';
            if ($year) {
                $year_format = ' Y';
            }
            $absolute_time = date('D ' . $month_and_day_format . $year_format, $timestamp);
        }
        // else If the type is just time, then just get a time.
    } elseif ($type == 'time') {
        // If the timezone type is not "site", and the visitor is logged in,
        // and the user has a specific timezone set,
        // then get time in user's timezone.
        if ((($properties['timezone_type'] ?? '') != 'site') && (USER_LOGGED_IN) && (USER_TIMEZONE != '')) {
            $timezone_type = 'user';
            // Otherwise get the time for the site's timezone.
        } else {
            $timezone_type = 'site';
        }
        // If we should output time for site's timezone, then do that.
        if ($timezone_type == 'site') {
            $absolute_time = date($hour_system_format, $timestamp);
            // Otherwise we should output time for user's timezone, so do that.
        } else {
            $date = new DateTime('@' . $timestamp);
            $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
            $absolute_time = $date->format($hour_system_format);
        }
        // Otherwise the type is date & time, so get both of those.
    } else {
        // If the timezone type is not "site", and the visitor is logged in,
        // and the user has a specific timezone set,
        // then get time in user's timezone.
        if ((($properties['timezone_type'] ?? '') != 'site') && (USER_LOGGED_IN) && (USER_TIMEZONE != '')) {
            $timezone_type = 'user';
            // Otherwise get the time for the site's timezone.
        } else {
            $timezone_type = 'site';
        }
        if ($size == 'long') {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'F j';
                // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j F';
            }
            $year_format = '';
            if ($year) {
                $year_format = ', Y';
            }
            // If we should output time for site's timezone, then do that.
            if ($timezone_type == 'site') {
                $absolute_time = date('l, ' . $month_and_day_format . $year_format . ' \a\t ' . $hour_system_format . ' T', $timestamp);
                // Otherwise we should output time for user's timezone, so do that.
            } else {

                $date = new DateTime('@' . $timestamp);
                $date->setTimezone(new DateTimeZone(USER_TIMEZONE));

                $absolute_time = $date->format('l, ' . $month_and_day_format . $year_format . ' \a\t ' . $hour_system_format . ' T');
            }
        } else {
            // If the date format is month and then day, then use that format.
            if (DATE_FORMAT == 'month_day') {
                $month_and_day_format = 'M j';
                // Otherwise the date format is day and then month, so use that format.
            } else {
                $month_and_day_format = 'j M';
            }
            $year_format = '';
            if ($year) {
                $year_format = ' Y';
            }
            // If we should output time for site's timezone, then do that.
            if ($timezone_type == 'site') {
                $absolute_time = date('D ' . $month_and_day_format . $year_format . ' ' . $hour_system_format . ' T', $timestamp);
                // Otherwise we should output time for user's timezone, so do that.
            } else {
                $date = new DateTime('@' . $timestamp);
                $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
                $absolute_time = $date->format('D ' . $month_and_day_format . $year_format . ' ' . $hour_system_format . ' T');
            }
        }
    }

    $search = array(
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
        'Sat',
        'Su',
        'Mo',
        'Tu',
        'We',
        'Th',
        'Fr',
        'Sa',
        'Sun',
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
    );
    $replace = array(
        lang('January'),
        lang('February'),
        lang('March'),
        lang('April'),
        lang('May'),
        lang('June'),
        lang('July'),
        lang('August'),
        lang('September'),
        lang('October'),
        lang('November'),
        lang('December'),
        lang('Sunday'),
        lang('Monday'),
        lang('Tuesday'),
        lang('Wednesday'),
        lang('Thursday'),
        lang('Friday'),
        lang('Saturday'),
        lang('Jan'),
        lang('Feb'),
        lang('Mar'),
        lang('Apr'),
        lang('May'),
        lang('Jun'),
        lang('Jul'),
        lang('Aug'),
        lang('Sep'),
        lang('Oct'),
        lang('Nov'),
        lang('Dec'),
        lang('Sat'),
        lang('Su'),
        lang('Mo'),
        lang('Tu'),
        lang('We'),
        lang('Th'),
        lang('Fr'),
        lang('Sa'),
        lang('Sun'),
        lang('Mon'),
        lang('Tue'),
        lang('Wed'),
        lang('Thu'),
        lang('Fri'),

    );
    // strtr(), not str_replace().
    //
    // str_replace() with two arrays walks the pairs one after another over the
    // result of the last one, so a short name eats a long one and a finished
    // translation gets translated again. Both happened here: 'Mo' ran before
    // 'Mon', turning "Mon" into "Pt" plus a stray "n" -> "Ptn"; 'Sa' then found
    // the "Sa" inside "Salı" and made it "Ctlı"; and 'May' matched the "May" it
    // had just written into "Mayıs", giving "Mayısıs".
    //
    // strtr() with one map takes the longest key that fits at each position and
    // never looks at what it has written, which is exactly the rule a name
    // table needs.
    $absolute_time = strtr($absolute_time, array_combine($search, $replace));

    // If the format is plain text, then just return the absolute time without a time tag around it.
    if ($format == 'plain_text') {
        return $absolute_time;
        // Otherwise the format is html, so add time tag around absolute time.
    } else {
        // If the type is just date, then prepare datetime attribute with just the date.
        if ($type == 'date') {
            $datetime = date('Y-m-d', $timestamp);
            // elseIf the type is just time, then prepare datetime attribute with just the time.
        } elseif ($type == 'time') {
            $datetime = date('\TH:i:s\Z', $timestamp);
            // Otherwise the type is date & time, so prepare datetime attribute with both the date and time.
        } else {
            $datetime = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
        }
        return '<time datetime="' . $datetime . '">' . $absolute_time . '</time>';
    }
}
// Determine whether the current request reached the visitor over TLS.
//
// Direct TLS termination on the origin is detected via $_SERVER['HTTPS'] / SERVER_PORT.
//
// When the site sits behind a reverse proxy or CDN that terminates TLS itself
// (Cloudflare "Flexible" SSL, nginx/HAProxy offloading, load balancers), the
// origin leg is plain HTTP on port 80 and neither of those variables is set,
// even though the visitor's connection is encrypted. In that topology the only
// evidence is a forwarded header. Those headers are trivially spoofable by any
// client that can reach the origin directly, so they are honoured ONLY when the
// administrator opts in with TRUST_PROXY_SSL_HEADERS in the config file.
// Enable it only when the origin is not directly reachable (firewalled to the
// proxy's IP ranges, or Cloudflare "Authenticated Origin Pulls").
function check_if_request_is_secure()
{
    // Direct TLS on the origin. Always trusted - set by the web server, not the client.
    if (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')
    ) {
        return true;
    }

    // Everything below is client-supplied, so require the administrator to opt in.
    if (!defined('TRUST_PROXY_SSL_HEADERS') || TRUST_PROXY_SSL_HEADERS !== true) {
        return false;
    }

    return check_proxy_ssl_headers();
}

// Inspect the forwarded-protocol headers set by reverse proxies and CDNs.
// Split out from check_if_request_is_secure() so that the diagnostics page can
// report what the origin sees without having to duplicate the parsing rules.
// NOTE: these headers are attacker-controlled unless the origin only accepts
// traffic from the proxy - see check_if_request_is_secure().
function check_proxy_ssl_headers()
{
    // Standard de-facto header, also sent by Cloudflare. May contain a comma
    // separated chain when several proxies are involved ("https, http") - the
    // left-most entry is the one the visitor actually used.
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded_proto = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if (strtolower(trim($forwarded_proto[0])) === 'https') {
            return true;
        }
    }

    // Cloudflare specific, e.g. {"scheme":"https"}
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) {
        return true;
    }

    // RFC 7239 Forwarded header, e.g. for=1.2.3.4;proto=https
    if (!empty($_SERVER['HTTP_FORWARDED']) && preg_match('/proto\s*=\s*"?https"?/i', $_SERVER['HTTP_FORWARDED'])) {
        return true;
    }

    // Older nginx / AWS ELB style header.
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }

    // Microsoft ISA/TMG and IIS ARR.
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') {
        return true;
    }

    // Some proxies rewrite the port instead of the scheme.
    if (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443') {
        return true;
    }

    return false;
}



// Create function that can be used to build a URL.  You can pass in properties for each part of the URL
// and/or pass in a URL that you want this function to pull default parts from.
// Properties:
// url: an optional URL string that you want to use as a default.  If specific properties are not passed
// then the parts of this URL will be used.  You can pass all types of URL's for this property.
// (e.g. http://www.example.com, /example, example.html).
// scheme: http:// or https://
// hostname: example.com
// path: /example
// parameters: an array of name/value pairs.  If you include a url property that contains query string parameters
// then these parameters will be added to the passed url parameters.  If the same parameter appears in both then
// the parameter for this property will be used.  Don't urlencode the names or values (that is done for you).
// fragment: a string for the bookmark that will appear as "#example" (don't include "#").
function build_url($properties)
{
    // Safely extract values from $properties array
    $url = isset($properties['url']) ? $properties['url'] : '';
    $scheme = isset($properties['scheme']) ? $properties['scheme'] : '';
    $hostname = isset($properties['hostname']) ? $properties['hostname'] : '';
    $path = isset($properties['path']) ? $properties['path'] : '';
    $parameters = isset($properties['parameters']) ? $properties['parameters'] : array();
    $fragment = isset($properties['fragment']) ? $properties['fragment'] : '';

    // Get url parts in order to prepare URL
    $url_parts = parse_url($url);
    $url_scheme = '';

    // Use provided scheme or fallback to parsed scheme
    if ($scheme != '') {
        $url_scheme = $scheme;
    } else if (isset($url_parts['scheme'])) {
        $url_scheme = $url_parts['scheme'] . '://';
    }

    $url_hostname = '';
    if ($hostname != '') {
        $url_hostname = $hostname;
    } else if (isset($url_parts['host'])) {
        $url_hostname = $url_parts['host'];
    }

    $url_path = '';
    if ($path != '') {
        $url_path = $path;
    } else if (isset($url_parts['path'])) {
        $url_path = $url_parts['path'];
    }

    $url_query_string = '';
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_string_parameters);
        foreach ($query_string_parameters as $name => $value) {
            if (!is_array($parameters) || !array_key_exists($name, $parameters)) {
                $url_query_string .= ($url_query_string == '' ? '?' : '&') . urlencode($name) . '=' . urlencode($value);
            }
        }
    }

    if (is_array($parameters)) {
        foreach ($parameters as $name => $value) {
            $url_query_string .= ($url_query_string == '' ? '?' : '&') . urlencode($name) . '=' . urlencode($value);
        }
    }

    $url_fragment = '';
    if ($fragment != '') {
        $url_fragment = '#' . urlencode($fragment);
    } else if (isset($url_parts['fragment'])) {
        $url_fragment = '#' . $url_parts['fragment'];
    }

    if ($url_path == '' && ($url_query_string != '' || $url_fragment != '')) {
        $url_path = '/';
    }

    return $url_scheme . $url_hostname . $url_path . $url_query_string . $url_fragment;
}

// Create function to take a PHP array and create JSON content.
// json_encode() is part of core PHP and is always available on the supported versions
// (PHP 7.0+), so there is no library fallback any more.
function encode_json($value)
{
    // Force UTF-8 characters to remain unescaped
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}


// Create function to take JSON content and convert it to a PHP array.
// json_decode() is part of core PHP and is always available on the supported versions
// (PHP 7.0+), so there is no library fallback any more.
function decode_json($content)
{
    return json_decode($content, true);
}
// Create function that is used to encode the path part of a URL (e.g. /example/example)
// This is used in order to prepare valid URLs for page names or file names
// that might contain special characters.  We do not encode forward slashes
// because Apache will return a 404 error.
function encode_url_path($path)
{
    return str_replace('%2F', '/', rawurlencode($path));
}
// Create a function that will strip out the beginning <?php tag before PHP code
// is executed via eval.  This is used for hook code and dynamic regions
// so that an error won't occur if someone accidentally leaves the starting PHP tag in their code.
// It is helpful to leave the starting PHP tag in case someone is developing the code in a local
// text editor and copying back and forth into the system.  It appears that PHP
// does not require that the ending/closing PHP tag be removed, so we don't bother to remove it.
function prepare_for_eval($content)
{
    if (mb_strtolower(mb_substr($content, 0, 5)) == '<?php') {
        return mb_substr($content, 5);
    } else {
        return $content;
    }
}
function get_date_format_help()
{
    if (DATE_FORMAT == 'month_day') {
        return 'm/d/yyyy';
    } else {
        return 'd/m/yyyy';
    }
}
// This function is used in order to get the correct order of the month
// and day, based on the date format, which can be used in a date() call.
function get_date_format_code()
{
    // If the date format is month and then day, then return code for that.
    if (DATE_FORMAT == 'month_day') {
        return 'n/j';
        // Otherwise the date format is day and then month, so return code for that.
    } else {
        return 'j/n';
    }
}
// Create a function that is used to return JavaScript
// that sets the date picker format variable.
function get_date_picker_format()
{
    if (DATE_FORMAT == 'month_day') {
        $output_date_picker_format = 'm/d/yy';
    } else {
        $output_date_picker_format = 'd/m/yy';
    }
    $output_first_day = '';
    if (lang(array('info' => '')) == 'tr') {
        $output_first_day = 'firstDay: 1,';
    }

    return '<script>var datetimepicker_options = {
        dateFormat: "' . $output_date_picker_format . '",
        prevText: "' . lang('previous') . '",
        nextText: "' . lang('next') . '",
        timeFormat:  "h:mm TT",
        monthNames: [ "' . lang('January') . '","' . lang('February') . '","' . lang('March') . '","' . lang('April') . '","' . lang('May') . '","' . lang('June') . '","' . lang('July') . '","' . lang('August') . '","' . lang('September') . '","' . lang('October') . '","' . lang('November') . '","' . lang('December') . '"],
        monthNamesShort: [ "' . lang('Jan') . '","' . lang('Feb') . '","' . lang('Mar') . '","' . lang('Apr') . '","' . lang('May') . '","' . lang('Jun') . '","' . lang('Jul') . '","' . lang('Aug') . '","' . lang('Sep') . '","' . lang('Oct') . '","' . lang('Nov') . '","' . lang('Dec') . '"],
        dayNames: [ "' . lang('Sunday') . '","' . lang('Monday') . '","' . lang('Tuesday') . '","' . lang('Wednesday') . '","' . lang('Thursday') . '","' . lang('Friday') . '","' . lang('Saturday') . '"],
        dayNamesShort: ["' . lang('Sun') . '","' . lang('Mon') . '","' . lang('Tue') . '","' . lang('Wed') . '","' . lang('Thu') . '","' . lang('Fri') . '","' . lang('Sat') . '"],
        dayNamesMin: ["' . lang('Su') . '","' . lang('Mo') . '","' . lang('Tu') . '","' . lang('We') . '","' . lang('Th') . '","' . lang('Fr') . '","' . lang('Sa') . '"],
        currentText: "' . lang('Now') . '",
        closeText: "' . lang('Ok') . '",
        ' . $output_first_day . '
        weekHeader: "' . lang('Wk') . '"};
        var date_picker_format = "' . $output_date_picker_format . '";
        </script>';
}
// Create a function that is used to return JavaScript
// that sets the date and time picker format variable.
function get_date_time_picker_format()
{
    if (DATE_FORMAT == 'month_day') {
        $output_date_picker_format = 'm/d/yy';
    } else {
        $output_date_picker_format = 'd/m/yy';
    }
    $output_first_day = '';
    if (lang(array('info' => '')) == 'tr') {
        $output_first_day = 'firstDay: 1,';
    }

    return '<script>var datetimepicker_options = {
        dateFormat: "' . $output_date_picker_format . '",
        timeFormat:  "h:mm TT",
        prevText: "' . lang('previous') . '",
        nextText: "' . lang('next') . '",
        monthNames: [ "' . lang('January') . '","' . lang('February') . '","' . lang('March') . '","' . lang('April') . '","' . lang('May') . '","' . lang('June') . '","' . lang('July') . '","' . lang('August') . '","' . lang('September') . '","' . lang('October') . '","' . lang('November') . '","' . lang('December') . '"],
        monthNamesShort: [ "' . lang('Jan') . '","' . lang('Feb') . '","' . lang('Mar') . '","' . lang('Apr') . '","' . lang('May') . '","' . lang('Jun') . '","' . lang('Jul') . '","' . lang('Aug') . '","' . lang('Sep') . '","' . lang('Oct') . '","' . lang('Nov') . '","' . lang('Dec') . '"],
        dayNames: [ "' . lang('Sunday') . '","' . lang('Monday') . '","' . lang('Tuesday') . '","' . lang('Wednesday') . '","' . lang('Thursday') . '","' . lang('Friday') . '","' . lang('Saturday') . '"],
        dayNamesMin: ["' . lang('Su') . '","' . lang('Mo') . '","' . lang('Tu') . '","' . lang('We') . '","' . lang('Th') . '","' . lang('Fr') . '","' . lang('Sa') . '"],
        timeText: "' . lang('Time') . '",
        hourText: "' . lang('Hour') . '",
        minuteText: "' . lang('Minute') . '",
        currentText: "' . lang('Now') . '",
        closeText: "' . lang('Ok') . '",
        ' . $output_first_day . '
        weekHeader: "' . lang('Wk') . '"};
        var date_picker_format = "' . $output_date_picker_format . '";</script>';
}

// Create a function that is used to return JavaScript
// that sets the time picker format variable.
function get_time_picker_format()
{
    return '<script>var timepicker_options = {
    timeOnlyTitle: "' . lang('Select Time') . '",
    timeText: "' . lang('Time') . '",
    hourText: "' . lang('Hour') . '",
    minuteText: "' . lang('Minute') . '",
    secondText: "' . lang('Second') . '",
    timezoneText: "' . lang('Timezone') . '",
    currentText: "' . lang('Now') . '",
    closeText: "' . lang('Ok') . '",
    isRTL: false,
    timeFormat:  "h:mm TT"};</script>';
}


// Used to find all custom formats in a theme.
// This deals with <custom_formats>, <add_custom_formats>, and <remove_custom_formats>.
// This function calls get_custom_formats_for_type() in order to get each of those 3 types listed above.
function get_custom_formats($theme_name)
{
    $custom_formats = array();
    $css = @file_get_contents(FILE_DIRECTORY_PATH . '/' . $theme_name);
    if ($css) {
        // Get the custom formats from the standard <custom_formats> area.
        $custom_formats = get_custom_formats_for_type(array(
            'css' => $css,
            'type' => 'custom_formats'
        ));
        // Get the custom formats from the <add_custom_formats> area.
        $add_custom_formats = get_custom_formats_for_type(array(
            'css' => $css,
            'type' => 'add_custom_formats'
        ));
        // Loop through the add custom formats in order to add them.
        foreach ($add_custom_formats as $custom_format) {
            // If this custom format has not already been added, then add it.
            if (isset($custom_formats[$custom_format['name']]) == false) {
                $custom_formats[$custom_format['name']] = $custom_format;
            }
        }
        // Get the custom formats from the <remove_custom_formats> area.
        $remove_custom_formats = get_custom_formats_for_type(array(
            'css' => $css,
            'type' => 'remove_custom_formats'
        ));
        // Loop through the remove custom formats in order to remove them.
        foreach ($remove_custom_formats as $custom_format) {
            unset($custom_formats[$custom_format['name']]);
        }
        sort($custom_formats);
    }
    return $custom_formats;
}
// Called by get_custom_formats() in order to get 3 different types of custom formats.
// Properties:
// css: the css content from the theme
// type: "custom_formats", "add_custom_formats", or "remove_custom_formats"
function get_custom_formats_for_type($properties)
{
    $css = $properties['css'];
    $type = $properties['type'];
    $custom_formats = array();
    // Find where the custom formats are located at.
    $chunkStart = mb_strpos($css, "/* <" . $type . "> */");
    $chunkEnd = mb_strpos($css, "/* </" . $type . "> */");
    // If we found our comments
    if ($chunkStart !== false) {
        // Capture the text before the comment
        $chunkBefore = mb_substr($css, 0, $chunkStart + mb_strlen("/* <" . $type . "> */"));
        // Capture the text inside the comment
        $chunkMain = mb_substr($css, $chunkStart + mb_strlen("/* <" . $type . "> */"), $chunkEnd - ($chunkStart + mb_strlen("/* <" . $type . "> */")));
        // Capture the text after the comments.
        $chunkAfter = mb_substr($css, $chunkEnd);
        // Split each entry found inside our comments
        $css_split = explode("}", $chunkMain);
        // Loop through each result
        foreach ($css_split as $currentRow) {
            // Check to see if it contains a valid class
            // We are using stristr instead of mb_stristr because mb_stristr requires PHP 5.2,
            // and we still have some sites on PHP 5.1 (probably won't cause any utf-8 issue).
            if (stristr(mb_substr(trim($currentRow), 0, mb_strpos(trim($currentRow), "{")), ".")) {
                $full_css_class_name = trim(mb_substr(trim($currentRow), 0, mb_strpos(trim($currentRow), "{")));
                // If the class name contains a comma, then it's more than one class so split them up!
                if (stristr($full_css_class_name, ',')) {
                    // Do the actual splitting
                    $temp_css_class = explode(",", $full_css_class_name);
                    // Loop through each returned result of the split
                    foreach ($temp_css_class as $temporary_class_name) {
                        // Remove beginning and ending spaces
                        $temporary_class_name = trim($temporary_class_name);
                        if (stristr($temporary_class_name, ".")) {
                            $name = mb_substr($temporary_class_name, mb_strpos($temporary_class_name, '.') + 1);
                            // If it is not in the array, then add it!
                            if (isset($custom_formats[$name]) == false) {
                                $parts = explode('.', $temporary_class_name);
                                $element = mb_strtolower($parts[0]);
                                if ($element != '') {
                                    $space_position = mb_strpos($element, ' ');
                                    if ($space_position !== false) {
                                        $element = trim(mb_substr($element, $space_position + 1));
                                    }
                                }
                                // If the class begins with a period, remove it to make hte frontend look better
                                $custom_formats[$name] = array(
                                    'name' => $name,
                                    'element' => $element
                                );
                            }
                        }
                    }
                    // Else, there is only a single class this time.
                } else {
                    $name = mb_substr($full_css_class_name, mb_strpos($full_css_class_name, '.') + 1);
                    // If it is not in the array, then add it!
                    if (isset($custom_formats[$name]) == false) {
                        $parts = explode('.', $full_css_class_name);
                        $element = mb_strtolower($parts[0]);
                        if ($element != '') {
                            $space_position = mb_strpos($element, ' ');
                            if ($space_position !== false) {
                                $element = trim(mb_substr($element, $space_position + 1));
                            }
                        }
                        // If the class begins with a period, remove it to make hte frontend look better
                        $custom_formats[$name] = array(
                            'name' => $name,
                            'element' => $element
                        );
                    }
                }
            }
        }
    }
    return $custom_formats;
}
// Create a function that will update the address names for all submitted forms
// for one particular custom form.  This function is run when pretty URLs are enabled
// from the edit page screen or when RSS title is set on edit field screen.
function update_multiple_submitted_form_address_names($custom_form_page_id)
{
    $submitted_forms = db_items("SELECT id

        FROM forms

        WHERE page_id = '" . escape($custom_form_page_id) . "'

        ORDER BY submitted_timestamp ASC");
    foreach ($submitted_forms as $submitted_form) {
        update_submitted_form_address_name($submitted_form['id']);
    }
}
// Create a function that will update the address name for a submitted form
// for pretty URL feature.
function update_submitted_form_address_name($submitted_form_id)
{
    // Get non-blank title value for this submitted form.  The title field is the one where the RSS title
    // setting is enabled for the field.  For pretty URLs we do not support multiple title fields.
    $title = db_value("SELECT form_data.data

        FROM form_data

        LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

        WHERE

            (form_data.form_id = '" . escape($submitted_form_id) . "')

            AND (form_fields.rss_field = 'title')

            AND (form_data.data != '')

        ORDER BY form_fields.sort_order ASC

        LIMIT 1");
    $address_name = create_address_name($title);
    // If the address name is not blank, then get a unique name.
    // We do this in case pretty URLs were just enabled and there happen
    // to be multiple submitted forms with the same title (so the submitted forms
    // are still accessible).
    if ($address_name != '') {
        $custom_form_page_id = db_value("SELECT page_id FROM forms WHERE id = '" . escape($submitted_form_id) . "'");
        $address_name = get_unique_submitted_form_address_name(array(
            'address_name' => $address_name,
            'custom_form_page_id' => $custom_form_page_id,
            'submitted_form_id' => $submitted_form_id
        ));
    }
    db("UPDATE forms SET address_name = '" . escape($address_name) . "' WHERE id = '" . escape($submitted_form_id) . "'");
    return $address_name;
}
// Create a function that will take content and prepare a name that will appear in a URL.
// For example, this is used in order to convert a submitted form title into a string of
// content that is friendly for URLs.  This function is not currently used to create the
// address name for catalog items, however we should do that in the future.
function create_address_name($content)
{
    // Transliterate before lowercasing, not after. mb_strtolower('İ') does not
    // give a plain 'i'; it gives 'i' followed by a combining dot, and that mark
    // then falls outside the allowed set below and becomes a separator - the
    // letter would survive the table only to be split in two by it. "İçin" came
    // out as "i-cin" that way.
    //
    // The table this replaced was dead. Its keys had been mangled into U+FFFD
    // by an encoding conversion somewhere in this file's history, so all
    // sixty-six of them were the same string and strtr() matched none of them.
    // Turkish letters were never in it to begin with. Everything outside
    // a-z0-9 then hit the rule further down and became a dash, which is how a
    // Turkish title lost its keywords entirely: "Bulut Bilişimin Avantajları"
    // produced "bulut-bili-imin-avantajlar".
    //
    // pg_transliterate_to_ascii() is the table catalog addresses already use,
    // so a product and a post now spell the same word the same way.
    $address_name = pg_transliterate_to_ascii($content);
    $address_name = mb_strtolower($address_name);
    // Convert a few symbols to their english equivalent.
    $address_name = str_replace('@', ' at ', $address_name);
    $address_name = str_replace('%', ' percent ', $address_name);
    $address_name = str_replace('&', ' and ', $address_name);
    // Remove single and double quotes.
    // We don't want to replace these with dashes because we gets things like: it-s-a-boy.
    $address_name = str_replace('"', '', $address_name);
    $address_name = str_replace("'", '', $address_name);
    // Replace all non-alphanumeric characters with a dash.
    $address_name = preg_replace('/[^a-z0-9]/', '-', $address_name);
    // Replace multiple dashes in a row with one dash.
    $address_name = preg_replace('/-{2,}/', '-', $address_name);
    // Remove dashes from the beginning and end.
    $address_name = trim($address_name, '-');
    return $address_name;
}
// Create a function that is used to get a unique version of a
// submitted form address name in case there are other submitted forms
// with that same address name.  This is used when pretty URLs are enabled
// or when a title field is address.  If necessary, a unique number is added
// on the end of the name.
// Properties:
// address_name: the address name that you want to use.
// custom_form_page_id: for the submitted form you are dealing with.
// submitted_form_id: for the submitted form that you are dealing with.
function get_unique_submitted_form_address_name($properties)
{
    $address_name = $properties['address_name'];
    $custom_form_page_id = $properties['custom_form_page_id'];
    $submitted_form_id = $properties['submitted_form_id'];
    // If this name is already in use, then prepare a unique name.
    if (
        db_value("SELECT COUNT(*)

            FROM forms

            WHERE

                (page_id = '" . escape($custom_form_page_id) . "')

                AND (id != '" . escape($submitted_form_id) . "')

                AND (address_name = '" . escape($address_name) . "')") > 0
    ) {
        // If there is already a number on the end of the name,
        // then just increase number.
        if (preg_match('/(.*?)-(\d+)$/', $address_name, $matches) == 1) {
            $new_address_name = $matches[1] . '-' . ($matches[2] + 1);
            // Otherwise there is not already a number area on the end of the name,
            // so add 1.
        } else {
            $new_address_name = $address_name . '-1';
        }
        // Use recursion to check if this new address name is unique
        // and get a different name if necessary.
        return get_unique_submitted_form_address_name(array(
            'address_name' => $new_address_name,
            'custom_form_page_id' => $custom_form_page_id,
            'submitted_form_id' => $submitted_form_id
        ));
        // Otherwise the name is not already in use, so it can be used,
        // so just return it.
    } else {
        return $address_name;
    }
}
function check_if_pretty_urls_are_enabled($custom_form_page_id)
{
    $pretty_urls = db_value("SELECT pretty_urls FROM custom_form_pages WHERE page_id = '" . escape($custom_form_page_id) . "'");
    // If pretty URLs are enabled for the custom form then check if there is a title field.
    if ($pretty_urls == 1) {
        // If there is at least one title field, then return true.
        if (db_value("SELECT COUNT(*) FROM form_fields WHERE (page_id = '" . escape($custom_form_page_id) . "') AND (rss_field = 'title')") > 0) {
            return true;
            // Otherwise there is not a title field, so return false.
        } else {
            return false;
        }
        // Otherwise pretty URLs are not enabled, so return false.
    } else {
        return false;
    }
}
// Create a function that is used in order to check if a page, file, short link,
// or physical file or directory is already using a name, so that multiple items
// won't have the same name and conflict with each other.  This function returns true
// if name is available or false if name is in use.
// Properties:
// name: The name of the item you want to use.
// ignore_item_id: The optional id of the item that you want to ignore for this check.
//     This is used when editing an item because we don't care if the item
//     that we are editing already has this name.
// ignore_item_type: The type of the item that was passed for ignore_item_id.
function check_name_availability($properties)
{
    $name = '';
    $ignore_item_id = '';
    $ignore_item_type = '';


    if (isset($properties['name'])) {
        $name = $properties['name'];
    }
    if (isset($properties['ignore_item_id'])) {
        $ignore_item_id = $properties['ignore_item_id'];
    }
    if (isset($properties['ignore_item_type'])) {
        $ignore_item_type = $properties['ignore_item_type'];
    }

    $name = mb_strtolower($name);

    // Don't allow sitemap.xml or robots.txt for name,
    // because we generate our own dynamic content for those names.
    if (($name == 'sitemap.xml') || ($name == 'robots.txt')) {
        return false;
    }
    $sql_ignore = "";
    // If an ignore item was passed, and that ignore item is a page,
    // then prepare SQL to ignore that item.
    if (($ignore_item_id) && ($ignore_item_type == 'page')) {
        $sql_ignore = " AND (page_id != '" . escape($ignore_item_id) . "')";
    }
    // If a page exists for that name, then return false.
    if (db_value("SELECT COUNT(*) FROM page WHERE (page_name = '" . escape($name) . "')$sql_ignore") > 0) {
        return false;
    }
    $sql_ignore = "";
    // If an ignore item was passed, and that ignore item is a file,
    // then prepare SQL to ignore that item.
    if (($ignore_item_id) && ($ignore_item_type == 'file')) {
        $sql_ignore = " AND (id != '" . escape($ignore_item_id) . "')";
    }
    // If a file exists for that name, then return false.
    if (db_value("SELECT COUNT(*) FROM files WHERE (name = '" . escape($name) . "')$sql_ignore") > 0) {
        return false;
    }
    $sql_ignore = "";
    // If an ignore item was passed, and that ignore item is a short link,
    // then prepare SQL to ignore that item.
    if (($ignore_item_id) && ($ignore_item_type == 'short_link')) {
        $sql_ignore = " AND (id != '" . escape($ignore_item_id) . "')";
    }
    // If a short link exists for that name, then return false.
    if (db_value("SELECT COUNT(*) FROM short_links WHERE (name = '" . escape($name) . "')$sql_ignore") > 0) {
        return false;
    }
    // If a file or directory exists according to PHP, then return false.
    // It appears that file_exists() is case sensitive on Unix and case-insensitive on Windows.
    if (file_exists('../' . $name) == true) {
        return false;
    }
    // We also need to manually loop through all of the files in the root directory
    // in order to do a case-insensitive check.
    $handle = opendir('../');
    // Loop through root directory in order to check files and directories in it.
    while (false !== ($file_name = readdir($handle))) {
        // If this is a valid file or directory and the lowercase version of the name
        // matches the lowercase version of the name that we are testing,
        // then return false.
        if (($file_name != '.') && ($file_name != '..') && (mb_strtolower($file_name) == $name)) {
            closedir($handle);
            return false;
        }
    }
    closedir($handle);
    // If we have gotten here then that means there are no name conflicts, so return true.
    return true;
}
// Create a function that is just a shorthand version of a header location call.
// You can pass in a full URL with the scheme and hostname or one with just the path,
// but the path should start with "/".  This function will automatically convert
// URL to be a full URL in order to comply with HTTP requirements.
// If an empty url is supplied, then the visitor will be forwarded to the home page.
function go($url)
{
    $url = is_scalar($url) ? trim((string) $url) : '';

    // If the URL is empty, then set it to the home page.
    if ($url === '') {
        $url = PATH;
    }

    // A URL that carries a scheme is honoured only when it points back at this
    // site. Every caller inside the software builds those from
    // URL_SCHEME . HOSTNAME, so a foreign host at this point means the value
    // arrived in a request parameter. The prefix is compared as a string
    // rather than taken apart with parse_url(), so nothing in the path or the
    // query string is rewritten on the way through.
    if (preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*:~', $url) === 1) {
        $own = array(URL_SCHEME . HOSTNAME);
        if (!empty($_SERVER['HTTP_HOST'])) {
            $own[] = URL_SCHEME . $_SERVER['HTTP_HOST'];
        }

        $url_is_ours = false;
        foreach ($own as $prefix) {
            if (strncasecmp($url, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $rest = substr($url, strlen($prefix));
            // Reject "https://example.com.evil.com/x", which shares the prefix
            // but not the host. Only a path, a query or a fragment may follow.
            if (($rest === '') || (strpos('/?#', $rest[0]) !== false)) {
                $url = ($rest === '') ? PATH : $rest;
                $url_is_ours = true;
                break;
            }
        }

        if (!$url_is_ours) {
            $url = PATH;
        }

    // A path with no leading slash is relative to the directory of the running
    // script, which is how a browser resolves it in an href. Resolving it the
    // same way here keeps pg_safe_back_url()'s "view_files.php" form working.
    // Pasting it straight onto the host name instead would turn the equally
    // well-formed "x.evil.com/a.php" into https://example.comx.evil.com/a.php,
    // a host name the attacker can register.
    } elseif (mb_substr($url, 0, 1) !== '/') {
        $script = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '';
        $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $url = $directory . '/' . $url;
    }

    // Everything reaching this point is a path. pg_safe_redirect_path() is what
    // makes the concatenation below safe; it only inspects the first two
    // characters, so brackets, query strings and UTF-8 pass through unaltered.
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($url));
    exit();
}
function generate_gift_card_code()
{
    $code = '';
    for ($i = 1; $i <= 16; $i++) {
        $code .= mt_rand(0, 9);
    }
    // If code is already in use, use recursion to generate a new code.
    if (db_value("SELECT COUNT(*) FROM gift_cards WHERE code = '" . $code . "'") > 0) {
        return generate_gift_card_code();
        // Otherwise code is not already in use, so return code.
    } else {
        return $code;
    }
}
// Add dashes to gift card code so it is easier to read.
function output_gift_card_code($code)
{
    return mb_substr($code, 0, 4) . '-' . mb_substr($code, 4, 4) . '-' . mb_substr($code, 8, 4) . '-' . mb_substr($code, 12, 4);
}
// Create a function that gets timezones which are used to populate
// global timezone in site settings and user timezone in my account profile.
function get_timezones()
{
    return array(
        '(UTC-11:00) Midway Island' => 'Pacific/Midway',
        '(UTC-11:00) Samoa' => 'Pacific/Samoa',
        '(UTC-10:00) Hawaii' => 'Pacific/Honolulu',
        '(UTC-09:00) Alaska' => 'US/Alaska',
        '(UTC-08:00) Pacific Time (US & Canada)' => 'America/Los_Angeles',
        '(UTC-08:00) Tijuana' => 'America/Tijuana',
        '(UTC-07:00) Arizona' => 'US/Arizona',
        '(UTC-07:00) Chihuahua' => 'America/Chihuahua',
        '(UTC-07:00) Mazatlan' => 'America/Mazatlan',
        '(UTC-07:00) Mountain Time (US & Canada)' => 'US/Mountain',
        '(UTC-06:00) Central America' => 'America/Managua',
        '(UTC-06:00) Central Time (US & Canada)' => 'US/Central',
        '(UTC-06:00) Mexico City' => 'America/Mexico_City',
        '(UTC-06:00) Monterrey' => 'America/Monterrey',
        '(UTC-06:00) Saskatchewan' => 'Canada/Saskatchewan',
        '(UTC-05:00) Bogota' => 'America/Bogota',
        '(UTC-05:00) Eastern Time (US & Canada)' => 'US/Eastern',
        '(UTC-05:00) Indiana (East)' => 'US/East-Indiana',
        '(UTC-05:00) Lima' => 'America/Lima',
        '(UTC-05:00) Quito' => 'America/Guayaquil',
        '(UTC-04:00) Atlantic Time (Canada)' => 'Canada/Atlantic',
        '(UTC-04:30) Caracas' => 'America/Caracas',
        '(UTC-04:00) La Paz' => 'America/La_Paz',
        '(UTC-04:00) Santiago' => 'America/Santiago',
        '(UTC-03:30) Newfoundland' => 'Canada/Newfoundland',
        '(UTC-03:00) Brasilia' => 'America/Sao_Paulo',
        '(UTC-03:00) Buenos Aires' => 'America/Argentina/Buenos_Aires',
        '(UTC-03:00) Greenland' => 'America/Godthab',
        '(UTC-02:00) Mid-Atlantic' => 'America/Noronha',
        '(UTC-01:00) Azores' => 'Atlantic/Azores',
        '(UTC-01:00) Cape Verde Is.' => 'Atlantic/Cape_Verde',
        '(UTC+00:00) Casablanca' => 'Africa/Casablanca',
        '(UTC+00:00) Greenwich Mean Time : Dublin' => 'Etc/Greenwich',
        '(UTC+00:00) Lisbon' => 'Europe/Lisbon',
        '(UTC+00:00) London' => 'Europe/London',
        '(UTC+00:00) Monrovia' => 'Africa/Monrovia',
        '(UTC+00:00) UTC' => 'UTC',
        '(UTC+01:00) Amsterdam' => 'Europe/Amsterdam',
        '(UTC+01:00) Belgrade' => 'Europe/Belgrade',
        '(UTC+01:00) Berlin' => 'Europe/Berlin',
        '(UTC+01:00) Bratislava' => 'Europe/Bratislava',
        '(UTC+01:00) Brussels' => 'Europe/Brussels',
        '(UTC+01:00) Budapest' => 'Europe/Budapest',
        '(UTC+01:00) Copenhagen' => 'Europe/Copenhagen',
        '(UTC+01:00) Ljubljana' => 'Europe/Ljubljana',
        '(UTC+01:00) Madrid' => 'Europe/Madrid',
        '(UTC+01:00) Paris' => 'Europe/Paris',
        '(UTC+01:00) Prague' => 'Europe/Prague',
        '(UTC+01:00) Rome' => 'Europe/Rome',
        '(UTC+01:00) Sarajevo' => 'Europe/Sarajevo',
        '(UTC+01:00) Skopje' => 'Europe/Skopje',
        '(UTC+01:00) Stockholm' => 'Europe/Stockholm',
        '(UTC+01:00) Vienna' => 'Europe/Vienna',
        '(UTC+01:00) Warsaw' => 'Europe/Warsaw',
        '(UTC+01:00) West Central Africa' => 'Africa/Lagos',
        '(UTC+01:00) Zagreb' => 'Europe/Zagreb',
        '(UTC+02:00) Athens' => 'Europe/Athens',
        '(UTC+02:00) Bucharest' => 'Europe/Bucharest',
        '(UTC+02:00) Cairo' => 'Africa/Cairo',
        '(UTC+02:00) Harare' => 'Africa/Harare',
        '(UTC+02:00) Helsinki' => 'Europe/Helsinki',
        '(UTC+02:00) Jerusalem' => 'Asia/Jerusalem',
        '(UTC+02:00) Kyiv' => 'Europe/Helsinki',
        '(UTC+02:00) Pretoria' => 'Africa/Johannesburg',
        '(UTC+02:00) Riga' => 'Europe/Riga',
        '(UTC+02:00) Sofia' => 'Europe/Sofia',
        '(UTC+02:00) Tallinn' => 'Europe/Tallinn',
        '(UTC+02:00) Vilnius' => 'Europe/Vilnius',
        '(UTC+03:00) Istanbul' => 'Europe/Istanbul',
        '(UTC+03:00) Baghdad' => 'Asia/Baghdad',
        '(UTC+03:00) Kuwait' => 'Asia/Kuwait',
        '(UTC+03:00) Minsk' => 'Europe/Minsk',
        '(UTC+03:00) Nairobi' => 'Africa/Nairobi',
        '(UTC+03:00) Riyadh' => 'Asia/Riyadh',
        '(UTC+03:00) Volgograd' => 'Europe/Volgograd',
        '(UTC+03:30) Tehran' => 'Asia/Tehran',
        '(UTC+04:00) Baku' => 'Asia/Baku',
        '(UTC+04:00) Moscow' => 'Europe/Moscow',
        '(UTC+04:00) Muscat' => 'Asia/Muscat',
        '(UTC+04:00) Tbilisi' => 'Asia/Tbilisi',
        '(UTC+04:00) Yerevan' => 'Asia/Yerevan',
        '(UTC+04:30) Kabul' => 'Asia/Kabul',
        '(UTC+05:00) Karachi' => 'Asia/Karachi',
        '(UTC+05:00) Tashkent' => 'Asia/Tashkent',
        '(UTC+05:30) Kolkata' => 'Asia/Kolkata',
        '(UTC+05:45) Kathmandu' => 'Asia/Katmandu',
        '(UTC+06:00) Almaty' => 'Asia/Almaty',
        '(UTC+06:00) Dhaka' => 'Asia/Dhaka',
        '(UTC+06:00) Ekaterinburg' => 'Asia/Yekaterinburg',
        '(UTC+06:30) Rangoon' => 'Asia/Rangoon',
        '(UTC+07:00) Bangkok' => 'Asia/Bangkok',
        '(UTC+07:00) Jakarta' => 'Asia/Jakarta',
        '(UTC+07:00) Novosibirsk' => 'Asia/Novosibirsk',
        '(UTC+08:00) Chongqing' => 'Asia/Chongqing',
        '(UTC+08:00) Hong Kong' => 'Asia/Hong_Kong',
        '(UTC+08:00) Krasnoyarsk' => 'Asia/Krasnoyarsk',
        '(UTC+08:00) Kuala Lumpur' => 'Asia/Kuala_Lumpur',
        '(UTC+08:00) Perth' => 'Australia/Perth',
        '(UTC+08:00) Singapore' => 'Asia/Singapore',
        '(UTC+08:00) Taipei' => 'Asia/Taipei',
        '(UTC+08:00) Ulaan Bataar' => 'Asia/Ulan_Bator',
        '(UTC+08:00) Urumqi' => 'Asia/Urumqi',
        '(UTC+09:00) Irkutsk' => 'Asia/Irkutsk',
        '(UTC+09:00) Seoul' => 'Asia/Seoul',
        '(UTC+09:00) Tokyo' => 'Asia/Tokyo',
        '(UTC+09:30) Adelaide' => 'Australia/Adelaide',
        '(UTC+09:30) Darwin' => 'Australia/Darwin',
        '(UTC+10:00) Brisbane' => 'Australia/Brisbane',
        '(UTC+10:00) Canberra' => 'Australia/Canberra',
        '(UTC+10:00) Guam' => 'Pacific/Guam',
        '(UTC+10:00) Hobart' => 'Australia/Hobart',
        '(UTC+10:00) Melbourne' => 'Australia/Melbourne',
        '(UTC+10:00) Port Moresby' => 'Pacific/Port_Moresby',
        '(UTC+10:00) Sydney' => 'Australia/Sydney',
        '(UTC+10:00) Yakutsk' => 'Asia/Yakutsk',
        '(UTC+11:00) Vladivostok' => 'Asia/Vladivostok',
        '(UTC+11:00) New Caledonia' => 'Pacific/Noumea',
        '(UTC+12:00) Auckland' => 'Pacific/Auckland',
        '(UTC+12:00) Fiji' => 'Pacific/Fiji',
        '(UTC+12:00) International Date Line West' => 'Pacific/Kwajalein',
        '(UTC+12:00) Kamchatka' => 'Asia/Kamchatka',
        '(UTC+12:00) Magadan' => 'Asia/Magadan',
        '(UTC+13:00) Nuku\'alofa' => 'Pacific/Tongatapu'
    );
}


// Used by the auto-registration feature for custom forms and orders
// to create a unique username.
function get_unique_username($username, $number = 0)
{
    // If this is the first time this function has run, then just use the username
    // as the test username.
    if ($number == 0) {
        $test_username = $username;
        // Otherwise this is not the first time this function has run so add the number
        // to the end of the username.
    } else {
        $test_username = $username . $number;
    }
    // If the username is not already in use, then return it.
    if (db_value("SELECT COUNT(*) FROM user WHERE user_username = '" . escape($test_username) . "'") == 0) {
        return $test_username;
        // Otherwise the username is already in use, so test again with an increased number.
    } else {
        return get_unique_username($username, $number + 1);
    }
}
function get_submitted_form_title($id)
{
    // Get title values for this submitted form.  The title field is the one where the RSS title
    // setting is enabled for the field.  There can be multiple title fields (e.g. first name, last name).
    $titles = db_items("SELECT

            form_data.data,

            form_data.type

        FROM form_data

        LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

        WHERE

            (form_data.form_id = '" . e($id) . "')

            AND (form_fields.rss_field = 'title')

        ORDER BY form_fields.sort_order ASC");
    $combined_title = '';
    // Loop through the title values, in order to prepare the combined title.
    foreach ($titles as $title) {
        // If the title contains HTML, from a rich-text editor field,
        // and the title is not blank, then convert HTML to plain-text,
        // because HTML is not supported in the title tag.  It was necessary
        // to do this because some systems, like Facebook, will show the HTML tags
        // as plain text (e.g. <bold>example</bold>).
        if (($title['type'] == 'html') && ($title['data'] != '')) {
            $title['data'] = trim(convert_html_to_text($title['data']));
        }
        // If the title is not blank, then add it to combined title.
        if ($title['data'] != '') {
            // If this is not the first title then add a space for separation.
            if ($combined_title != '') {
                $combined_title .= ' ';
            }
            $combined_title .= $title['data'];
        }
    }
    return $combined_title;
}
function autoload_liveform($class)
{
    if ($class == 'liveform') {
        require(PG_FUNCTIONS_DIR . '/liveform.class.php');
    }
}
// Takes a comment label, sets it to the default ("Comment") if blank,
// and pluralizes it if necessary.
function get_comment_label($properties)
{
    $label = $properties['label'];
    if (isset($properties['number'])) {
        $number = $properties['number'];
    }
    if ($label == '') {
        $label = lang('Comment');
    }
    if (isset($number)) {
        $label = pluralize(array(
            'word' => $label,
            'number' => $number
        ));
    }
    return $label;
}
// Takes a word, in singular form, and the number of items
// and it will convert it to plural if necessary.
// Only supports English. Does not support all plural cases.
function pluralize($properties)
{
    $word = $properties['word'];
    $number = $properties['number'];
    if (lang(array('info' => '')) == 'en') {
        if ($number == 1) {
            return $word;
        }
        $last_character = mb_substr($word, -1);
        $last_two_characters = mb_substr($word, -2);
        if (($last_character == 'x') || ($last_character == 's') || ($last_character == 'z') || ($last_two_characters == 'sh') || ($last_two_characters == 'ch')) {
            return $word . 'es';
        }
        if ($last_character == 'y') {
            $second_to_last_character = mb_substr($word, -2, 1);
            if (($second_to_last_character != 'a') && ($second_to_last_character != 'e') && ($second_to_last_character != 'i') && ($second_to_last_character != 'o') && ($second_to_last_character != 'u')) {
                return mb_substr($word, 0, -1) . 'ies';
            }
        }
        return $word . 's';
    } else {
        /*
        turkish language requre no pluralize
        example: 
        1 command = 1 yorum
        5 commands = 5 yorum
        Commands = Yorumlar
        check this area for other languages
        */

        return $word;
    }
}
// Looks through a multi-dimensional array and returns the first item with a matching property and
// value.
function array_finder($items, $property, $value)
{
    foreach ($items as $item) {
        if ($item[$property] == $value) {
            return $item;
        }
    }
    return false;
}
function error_response($message = '')
{
    return array(
        'status' => 'error',
        'message' => $message
    );
}
function success_response($message = '')
{
    return array(
        'status' => 'success',
        'message' => $message
    );
}
function set_response_code($response_code)
{
    if (!$response_code) {
        return false;
    }
    // If this is PHP 5.4+ which has a function to do response codes, then use that.
    if (function_exists('http_response_code')) {
        http_response_code($response_code);
        // Otherwise create our own code.
    } else {
        switch ($response_code) {
            case 403:
                header('HTTP/1.1 403 Forbidden');
                break;
            case 404:
                header('HTTP/1.1 404 Not Found');
                break;
            case 410:
                header('HTTP/1.1 410 Gone');
                break;
        }
    }
    return true;
}

// Used to bust cache for uploaded files.  Pass the file name and it will return the file name
// with a unique query string containing the last modified timestamp (e.g. example.css?v=123).
// This will force browsers to ignore cache and download a new file.  Only works for files
// that users have uploaded.  Does not work with Pinegrap system files. Example below, but remove
// last backslash, which is just required in these comments.
// <link rel="stylesheet" href="/<?=smart_cache('example.css')?\>">
function smart_cache($file_name)
{
    $last_modified_timestamp = @filemtime(FILE_DIRECTORY_PATH . '/' . $file_name);
    // If a timestamp was found, then return file name along with query string with timestamp.
    if ($last_modified_timestamp) {
        return $file_name . '?v=' . $last_modified_timestamp;
        // Otherwise a timestamp was not found (file might have been deleted), so just return the file
        // name so we don't break any code.
    } else {
        return $file_name;
    }
}

// This function checks who is online in this site with last-seen status.
// If you don't store user_id in the session it will fall back to sessionusername.
function who_is_online($check_time = 50)
{
    // normalize check time (default 50 seconds)
    $ct = (int) $check_time;
    if ($ct <= 0) {
        $ct = 50;
    }

    if ((USER_LOGGED_IN) && (defined('DB_CONNECTED'))) {
        // Prefer session user id if available
        if (isset($_SESSION['sessionuserid']) && $_SESSION['sessionuserid'] !== '') {
            $user_id = (int) $_SESSION['sessionuserid'];
            $query = "UPDATE user
                      SET user_online_timestamp = UNIX_TIMESTAMP()
                      WHERE user_id = '" . $user_id . "'
                        AND (UNIX_TIMESTAMP() - user_online_timestamp) > " . $ct;
            @mysqli_query(db::$con, $query);
            return;
        }

        // Fallback to username if user_id not stored in session
        if (isset($_SESSION['sessionusername']) && $_SESSION['sessionusername'] !== '') {
            $username = escape($_SESSION['sessionusername']);
            $query = "SELECT user_id, user_online_timestamp
                      FROM user
                      WHERE user_username = '" . $username . "'
                      LIMIT 1";
            $result = mysqli_query(db::$con, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $user_id = (int) $row['user_id'];
                $user_online_timestamp = (int) $row['user_online_timestamp'];
                if ((time() - $user_online_timestamp) > $ct) {
                    $update = "UPDATE user
                               SET user_online_timestamp = UNIX_TIMESTAMP()
                               WHERE user_id = '" . $user_id . "'";
                    @mysqli_query(db::$con, $update);
                }
            }
        }
    }
}


/**
 * Create and store a notification record.
 *
 * This helper inserts a notification row into the "notifications" table using the
 * provided properties. The function expects a single associative array argument
 * ($properties) that declares the notification attributes. At minimum, the
 * 'action' and 'title' keys are required; other keys are optional.
 *
 * Behavior and side-effects:
 * - If 'user' is not provided and $_SESSION['sessionusername'] exists, that value
 *   will be used as the user.
 * - Numeric identifier values (product_id, order_id, form_id, comment_id) are
 *   normalized to integers when provided; empty strings are left as empty.
 * - The function uses an escape() helper to sanitize values and inserts via
 *   mysqli_query against db::$con. The notification row sets 'readed' = 0 and
 *   'timestamp' = UNIX_TIMESTAMP().
 * - Returns the inserted row ID on success or boolean false on failure.
 *
 * Expected keys in $properties (all keys optional except action and title):
 * - action (string)    : REQUIRED. Short action identifier (e.g. "new_order",
 *                        "software_update", "custom", etc.).
 * - title (string)     : REQUIRED. The notification title/content (may contain
 *                        HTML if appropriate).
 * - type (string)      : Optional. Notification type/level (default: "info").
 *                        Typical values: "info", "success", "warning", "error".
 * - product_id (int|string) : Optional. Product identifier (will be cast to int).
 * - form_id (int|string)    : Optional. Form identifier (will be cast to int).
 * - order_id (int|string)   : Optional. Order identifier (will be cast to int).
 * - order_total (string)    : Optional. Order total or amount (kept as string).
 * - comment_id (int|string) : Optional. Comment identifier (will be cast to int).
 * - user (string)           : Optional. Username or label for who triggered the notification.
 * - send_to (string)        : Optional. Optional recipient identifier or routing info.
 *
 * Return values:
 * - int  : The mysqli_insert_id for the newly created notification on success.
 * - false: If input validation fails (not an array, missing action/title) or
 *          if the database insert fails.
 *
 * Notes / Preconditions:
 * - The function relies on an available mysqli connection in db::$con and a
 *   global escape() helper for value escaping/quoting. Ensure these are loaded.
 * - The function does not throw exceptions; it returns false on error.
 *
 * Examples:
 *
 * create_notification(array(
 *     'action' => 'software_update',
 *     'type'   => 'success',
 *     'title'  => 'Update available',
 *     'user'   => 'Software'
 * ));
 *
 * create_notification(array(
 *     'action'      => 'new_order',
 *     'order_id'    => 1234,
 *     'order_total' => '$12.33',
 *     'type'        => 'success',
 *     'title'       => '#1123'
 * ));
 *
 * create_notification(array(
 *     'action'     => 'out_stock',
 *     'type'       => 'warning',
 *     'product_id' => 1234,
 *     'title'      => 'C1123 - Short Description'
 * ));
 *
 * create_notification(array(
 *     'action' => 'custom',
 *     'type'   => 'error',
 *     'title'  => 'Something went wrong'
 * ));
 *
 * @param array $properties Associative array of notification properties (see above).
 * @return int|false Inserted notification ID on success, or false on failure.
 */
function create_notification($properties)
{
    // Expected properties (all optional except action and title):
    // action, type, title, product_id, form_id, order_id, order_total, comment_id, user, send_to
    if (!is_array($properties)) {
        return false;
    }

    $action = isset($properties['action']) ? trim($properties['action']) : '';
    $type = isset($properties['type']) ? trim($properties['type']) : 'info';
    $title = isset($properties['title']) ? $properties['title'] : '';
    $product_id = isset($properties['product_id']) ? $properties['product_id'] : '';
    $order_id = isset($properties['order_id']) ? $properties['order_id'] : '';
    $form_id = isset($properties['form_id']) ? $properties['form_id'] : '';
    $order_total = isset($properties['order_total']) ? $properties['order_total'] : '';
    $comment_id = isset($properties['comment_id']) ? $properties['comment_id'] : '';
    $user = isset($properties['user']) ? $properties['user'] : '';
    $send_to = isset($properties['send_to']) ? $properties['send_to'] : '';

    // Use session username when user not provided
    if (empty($user) && isset($_SESSION['sessionusername'])) {
        $user = $_SESSION['sessionusername'];
    }

    // Require at least action and title
    if ($action === '' || $title === '') {
        return false;
    }

    // Normalize numeric IDs to integers when provided
    $product_id = ($product_id === '' ? '' : (int) $product_id);
    $order_id = ($order_id === '' ? '' : (int) $order_id);
    $form_id = ($form_id === '' ? '' : (int) $form_id);
    $comment_id = ($comment_id === '' ? '' : (int) $comment_id);

    // Prepare query (use existing escape() helper)
    $query = "INSERT INTO notifications (
                action,
                type,
                title,
                product_id,
                form_id,
                order_id,
                comment_id,
                order_total,
                user,
                send_to,
                readed,
                timestamp
            ) VALUES (
                '" . escape($action) . "',
                '" . escape($type) . "',
                '" . escape($title) . "',
                '" . escape($product_id) . "',
                '" . escape($form_id) . "',
                '" . escape($order_id) . "',
                '" . escape($comment_id) . "',
                '" . escape($order_total) . "',
                '" . escape($user) . "',
                '" . escape($send_to) . "',
                '0',
                UNIX_TIMESTAMP()
            )";

    $result = mysqli_query(db::$con, $query);

    if ($result === false) {
        return false;
    }

    $notification_id = mysqli_insert_id(db::$con);

    // Queue a device notification for everybody allowed to see this one.
    //
    // Three of the five kinds go out. An out-of-stock line and an available
    // update are things to find when the panel is next opened; a new order, a
    // submitted form and a comment are the ones somebody is waiting on. Nothing
    // is sent from here - this only writes the queue rows, because the request
    // that caused the notification may be a customer's checkout and must not
    // wait on a push service.
    if (($action == 'new_order') || ($action == 'form_submited') || ($action == 'new_comment')) {

        include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

        pg_push_enqueue_notification(array(
            'id'         => $notification_id,
            'action'     => $action,
            'comment_id' => $comment_id
        ));
    }

    return $notification_id;
}

/**
 * Truncate a string to a specified length and optionally append a suffix (default "...").
 *
 * Safety and multibyte support:
 * - Casts inputs to expected types for safety.
 * - Uses mbstring functions when available (UTF-8).
 * - Falls back to single-byte functions when mbstring is not available (compatible with PHP 7.0+ and PHP 8+).
 *
 * Behavior:
 * - If $length <= 0 returns an empty string.
 * - If the original string length is <= $length returns the original string.
 * - If $dots length does not fit into $length, returns the first $length characters of $dots.
 *
 * Examples:
 *   truncate('A long text', 8) => 'A lo...'
 *   truncate('Short', 10)      => 'Short'
 *
 * @param string $string Text to truncate
 * @param int    $length Maximum length (including the dots)
 * @param string $dots   Suffix to append when truncated (default "...")
 * @return string Truncated or original text
 */
function truncate($string, $length, $dots = "...")
{
    // Type coercion for safety
    $string = (string) $string;
    $length = (int) $length;
    $dots = (string) $dots;

    if ($length <= 0) {
        return '';
    }

    // Use mbstring when available for multibyte-safe operations
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        $encoding = 'UTF-8';
        $strLen = mb_strlen($string, $encoding);
        $dotsLen = mb_strlen($dots, $encoding);

        if ($strLen <= $length) {
            return $string;
        }

        $keep = $length - $dotsLen;

        if ($keep <= 0) {
            // If the dots themselves exceed the target length, return the first $length chars of dots
            return mb_substr($dots, 0, $length, $encoding);
        }

        return mb_substr($string, 0, $keep, $encoding) . $dots;
    }

    // Fallback when mbstring is not available (assumes single-byte encoding)
    $strLen = strlen($string);
    $dotsLen = strlen($dots);

    if ($strLen <= $length) {
        return $string;
    }

    $keep = $length - $dotsLen;

    if ($keep <= 0) {
        return substr($dots, 0, $length);
    }

    return substr($string, 0, $keep) . $dots;
}

/**
 * Get size of a directory (bytes). Uses SPL iterators, skips unreadable files.
 * Compatible with PHP 7.0+ and PHP 8.x (no type hints / return types).
 *
 * @param string $dir
 * @param bool   $follow_symlinks
 * @return int Bytes (cast to int; on 32-bit platforms very large dirs may overflow)
 */
function folderSize($dir, $follow_symlinks = false)
{
    $dir = rtrim((string) $dir, "/\\");
    if ($dir === '' || !is_dir($dir)) {
        return 0;
    }

    $size = 0.0; // use float to reduce risk of intermediate overflow on 32-bit

    // Base flags: skip dot entries
    $flags = FilesystemIterator::SKIP_DOTS;

    // Try to add FOLLOW_SYMLINKS if requested and the constant exists.
    // Use constant() with error suppression to remain compatible across PHP versions.
    if ($follow_symlinks) {
        $followConst = @constant('FilesystemIterator::FOLLOW_SYMLINKS');
        if ($followConst !== null && $followConst !== false) {
            $flags |= $followConst;
        }
    }

    try {
        $rdi = new RecursiveDirectoryIterator($dir, $flags);
        $it = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($it as $file) {
            // Ensure we have an SplFileInfo-like object
            if (!($file instanceof SplFileInfo)) {
                continue;
            }

            // Only count regular files (skip directories, links themselves, etc.)
            if ($file->isFile()) {
                // Determine readability in a safe way (method may not exist on some custom objects)
                $readable = false;
                if (is_callable(array($file, 'isReadable'))) {
                    $readable = $file->isReadable();
                } else {
                    $pathname = $file->getPathname();
                    $readable = is_readable($pathname);
                }

                // Use getSize() when possible; suppress warnings and ensure integer cast
                $fileSize = 0;
                if ($readable) {
                    $tmp = @$file->getSize();
                    $fileSize = ($tmp === false || $tmp === null) ? 0 : (int) $tmp;
                }

                $size += (float) $fileSize;
            }
        }
    } catch (UnexpectedValueException $e) {
        // Directory not accessible (permissions, broken symlink, etc.)
        return 0;
    } catch (\Exception $e) {
        return 0;
    }

    // Return as int. On 32-bit this may overflow for very large totals.
    return (int) $size;
}

/**
 * Determine that URL exists or not.
 *
 * Compatible with PHP 7.0 - 8.5 Uses cURL when available, falls back to get_headers().
 *
 * @param string $url Absolute URL to check.
 * @return bool True if the resource exists (HTTP status 2xx or 3xx), false otherwise.
 */
function url_exists($url)
{
    if (empty($url)) {
        return false;
    }

    // Validate URL
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ($url === false) {
        return false;
    }

    // Prefer cURL if available
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        // Identify this installation on outgoing requests. Sent with no
        // User-Agent, a request looks like an anonymous client to the receiving
        // server's firewall and gets rejected — which is how Pinegrap ended up
        // blocking its own licence and update checks.
        curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

        // Basic options: don't fetch body, return transfer, small timeouts
        $options = array(
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            // Keep verification off to preserve prior behavior across environments.
            // If you want stricter TLS checks, set these to true/2.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        );

        // CURLOPT_FOLLOWLOCATION cannot be used when open_basedir is set.
        // Only set it when safe to do so.
        $openBaseDir = ini_get('open_basedir');
        if (empty($openBaseDir)) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
            // Limit number of redirects to avoid infinite loops
            $options[CURLOPT_MAXREDIRS] = 5;
        }

        curl_setopt_array($ch, $options);

        // Execute
        $exec = @curl_exec($ch);

        // Get HTTP status code
        $httpCode = 0;
        if ($ch) {
            $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        // Consider resource existing if status is 2xx or 3xx
        return ($httpCode >= 200 && $httpCode < 400);
    }

    // Fallback: get_headers (works without cURL)
    $headers = @get_headers($url);
    if ($headers === false || count($headers) === 0) {
        return false;
    }

    // First header contains the HTTP status line, e.g. "HTTP/1.1 200 OK"
    $statusLine = $headers[0];
    if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#i', $statusLine, $m)) {
        $code = (int) $m[1];
        return ($code >= 200 && $code < 400);
    }

    return false;
}

// Software localization / translation helper.
// Usage scenarios:
//   echo lang('Hello World');
//   echo lang(array('string' => 'Hello {var:1}', 'vars' => array('World')));
//   echo lang(array('string' => 'Welcome {var:1|c}', 'vars' => array('john doe'))); // capitalize variable
//   echo lang(array('string' => 'Count: {var:1}', 'vars' => 5));
//   $lang = lang(array('info' => true)); // returns enforced or configured SOFTWARE_LANGUAGE (e.g. 'en' or 'tr')
//
// Notes:
// - Works on PHP 7.0 up to PHP 8.5.
// - Looks for JSON translation files in includes/local/{lang}.json relative to this file or cwd.
// - If mbstring is not available, falls back to single-byte string helpers.
// - in english language file you can overwrite strings.
function lang($properties = false)
{
    static $translations = array(); // cache translations per language

    // Helper: safe multibyte case conversion with fallbacks
    $safe_case = function ($text, $mode) {
        if (!is_string($text)) {
            $text = (string) $text;
        }
        if (function_exists('mb_convert_case')) {
            switch ($mode) {
                case 'title':
                    return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
                case 'upper':
                    return mb_convert_case($text, MB_CASE_UPPER, "UTF-8");
                case 'lower':
                    return mb_convert_case($text, MB_CASE_LOWER, "UTF-8");
                default:
                    return $text;
            }
        }
        // Fallbacks when mbstring not available
        switch ($mode) {
            case 'title':
                return ucwords(strtolower($text));
            case 'upper':
                return strtoupper($text);
            case 'lower':
                return strtolower($text);
            default:
                return $text;
        }
    };

    // If explicit info request (get current software language)
    if (is_array($properties) && isset($properties['info'])) {
        if (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE) {
            return ENFORCEMENT_SOFTWARE_LANGUAGE;
        }
        if (defined('SOFTWARE_LANGUAGE') && SOFTWARE_LANGUAGE) {
            return SOFTWARE_LANGUAGE;
        }
        return 'en';
    }

    // Normalize input: allow lang('text') or lang(array(...))
    $string = '';
    $vars = null;
    $suffixes = null;
    if (is_array($properties)) {
        if (isset($properties['string'])) {
            $string = (string) $properties['string'];
        }
        if (isset($properties['vars'])) {
            $vars = $properties['vars'];
        }
        if (isset($properties['suffix'])) {
            $suffixes = $properties['suffix'];
        }
    } else {
        // properties may be scalar string or null/false
        $string = ($properties === null || $properties === false) ? '' : (string) $properties;
    }

    // quick return for empty string
    if ($string === '') {
        return '';
    }

    // Determine selected language (en default)
    $selected_software_language = 'en';
    if (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE) {
        $selected_software_language = ENFORCEMENT_SOFTWARE_LANGUAGE;
    } elseif (defined('SOFTWARE_LANGUAGE') && SOFTWARE_LANGUAGE) {
        $selected_software_language = SOFTWARE_LANGUAGE;
    }

    // Load translations for language (cache)
    if (!isset($translations[$selected_software_language])) {
        $translations[$selected_software_language] = array();
        $json = false;

        // Prefer absolute path relative to this file
        $pathsToTry = array(
            PG_FUNCTIONS_DIR . '/includes/local/' . $selected_software_language . '.json',
            'includes/local/' . $selected_software_language . '.json',
            PG_FUNCTIONS_DIR . '/includes/local/' . $selected_software_language . '.json'
        );

        foreach ($pathsToTry as $path) {
            if (file_exists($path) && is_readable($path)) {
                $json = @file_get_contents($path);
                if ($json !== false) {
                    break;
                }
            }
        }

        if ($json !== false) {
            $decoded = @json_decode($json, true);
            if (is_array($decoded)) {
                $translations[$selected_software_language] = $decoded;
            } else {
                // keep empty array on decode failure
                $translations[$selected_software_language] = array();
            }
        }
    }

    // If translation exists, replace string
    if (isset($translations[$selected_software_language][$string])) {
        $string = $translations[$selected_software_language][$string];
    }


    // Replace variables {var}, {var:1}, with optional modifiers |c |u |l
    if ($vars !== null) {
        // Normalize single scalar to first var
        if (!is_array($vars)) {
            $vars = array($vars);
        }

        foreach ($vars as $index => $value) {
            $pos = $index + 1;
            $replacement = $value;

            // Look for modifier patterns {var:N|c} {var:N|u} {var:N|l}
            if (strpos($string, '{var:' . $pos . '|c}') !== false || strpos($string, '{var|' . ($pos) . '|c}') !== false) {
                $replacement = $safe_case($replacement, 'title');
                $string = str_replace('{var:' . $pos . '|c}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|c}', '{var:' . $pos . '}', $string);
            } elseif (strpos($string, '{var:' . $pos . '|u}') !== false || strpos($string, '{var|' . ($pos) . '|u}') !== false) {
                $replacement = $safe_case($replacement, 'upper');
                $string = str_replace('{var:' . $pos . '|u}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|u}', '{var:' . $pos . '}', $string);
            } elseif (strpos($string, '{var:' . $pos . '|l}') !== false || strpos($string, '{var|' . ($pos) . '|l}') !== false) {
                $replacement = $safe_case($replacement, 'lower');
                $string = str_replace('{var:' . $pos . '|l}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|l}', '{var:' . $pos . '}', $string);
            }

            // Also support shorthand {var|c} or {var} when only one var placeholder exists
            if ($pos === 1) {
                if (strpos($string, '{var|c}') !== false) {
                    $replacement = $safe_case($replacement, 'title');
                    $string = str_replace('{var|c}', '{var:1}', $string);
                } elseif (strpos($string, '{var|u}') !== false) {
                    $replacement = $safe_case($replacement, 'upper');
                    $string = str_replace('{var|u}', '{var:1}', $string);
                } elseif (strpos($string, '{var|l}') !== false) {
                    $replacement = $safe_case($replacement, 'lower');
                    $string = str_replace('{var|l}', '{var:1}', $string);
                }
            }

            // Final replacements
            $string = str_replace('{var:' . $pos . '}', $replacement, $string);
        }
    }

    // Replace suffix placeholders {suffix} and {suffix:N}
    if ($suffixes !== null) {
        if (!is_array($suffixes)) {
            $suffixes = array($suffixes);
        }
        foreach ($suffixes as $index => $sval) {
            $pos = $index + 1;
            $string = str_replace('{suffix:' . $pos . '}', $sval, $string);
        }
        // generic {suffix} => first suffix if exists
        if (strpos($string, '{suffix}') !== false) {
            $first = isset($suffixes[0]) ? $suffixes[0] : '';
            $string = str_replace('{suffix}', $first, $string);
        }
    }

    return $string;
}

// This function get mime type name from file extention
// example: get_mime_type('gif') returns that : image/gif
function get_mime_type($file_extention)
{
    // Generic binary type for extensions not listed below.
    $output = 'application/octet-stream';
    switch ($file_extention) {
        case 'jpeg':
        case 'jpg':
        case 'jpe':
            $output = 'image/jpeg';
            break;
        case 'gif':
            $output = 'image/gif';
            break;
        case 'tif':
        case 'tiff':
            $output = 'image/tiff';
            break;
        case 'psd':
            $output = 'image/vnd.adobe.photoshop';
            break;
        case 'webp':
            $output = 'image/webp';
            break;
        case 'svg':
        case 'svgz':
            $output = 'image/svg+xml';
            break;
        case 'bmp':
            $output = 'image/bmp';
            break;
        case 'ico':
            $output = 'image/x-icon';
            break;
        case 'png':
            $output = 'image/png';
            break;
        case 'pdf':
            $output = 'application/pdf';
            break;
        case 'mpeg':
        case 'mp2':
        case 'mp2a':
        case 'mp3':
        case 'mpga':
            $output = 'audio/mpeg';
            break;
        case 'midi':
        case 'mid':
            $output = 'audio/midi';
            break;
        case 'avi':
            $output = 'video/x-msvideo';
            break;
        case 'mov':
            $output = 'video/quicktime';
            break;
        case 'mp4':
        case 'mp4v':
        case 'mpg4':
            $output = 'video/mp4';
            break;
    }
    return $output;
}

// Detect mime type from file path using mime_content_type, finfo, or fallback
function detect_mime_type($file_path)
{
    if (function_exists('mime_content_type')) {
        return mime_content_type($file_path);
    }

    if (function_exists('finfo_open')) {
        $const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_NONE;
        $finfo = @finfo_open($const);
        if ($finfo) {
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            if ($mime !== false) {
                return $mime;
            }
        }
    }

    // Fallback: guess by extension
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/**
 * Get a short version string derived from VERSION constant.
 *
 * Improvements for PHP 7.0 - 8.5 compatibility:
 * - Accepts VERSION values like "1.2.3", "v1.2", "12", "SETUP" and preserves fallback behavior.
 * - Safely handles non-numeric versions (falls back to last 2 chars of the major segment).
 * - Uses mb_substr when available to be multibyte-safe.
 * - Defensive casts and sanitization to avoid warnings on older PHP builds.
 *
 * Returns either "XX.Y" (major last 2 digits + dot + minor) or "XX" (major last 2 digits or full major).
 */
function get_short_version()
{
    if (!defined('VERSION')) {
        define('VERSION', 'SETUP');
    }

    // Ensure we are working with a string
    $version = (string) VERSION;

    // Helper: multibyte-safe substr wrapper
    $sub = function ($str, $start, $length = null) {
        if (function_exists('mb_substr')) {
            if ($length === null) {
                return mb_substr($str, $start);
            }
            return mb_substr($str, $start, $length);
        }
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    };

    // Trim and remove any leading non-digit characters (e.g. leading "v")
    $version_trimmed = trim($version);
    $version_trimmed = preg_replace('/^[^\d]*/', '', $version_trimmed);

    // If trimming removed everything (e.g. "SETUP"), fall back to original
    if ($version_trimmed === '') {
        $version_trimmed = $version;
    }

    $parts = explode('.', $version_trimmed);
    $major_raw = isset($parts[0]) ? (string) $parts[0] : '';
    $minor_raw = isset($parts[1]) ? (string) $parts[1] : null;

    // Try to extract digits from major; if none, we'll use the raw major string later
    $major_digits = preg_replace('/\D+/', '', $major_raw);

    if ($major_digits === '') {
        // no numeric digits found, use last 2 chars of raw major (mimics original behavior)
        $major_short = $sub($major_raw, max(0, strlen($major_raw) - 2));
    } else {
        // use last two digits if available
        $major_short = (strlen($major_digits) > 2) ? $sub($major_digits, -2) : $major_digits;
    }

    if ($minor_raw !== null && $minor_raw !== '') {
        // sanitize minor to remove non-digits but keep a fallback to raw minor
        $minor_digits = preg_replace('/\D+/', '', $minor_raw);
        $minor_output = ($minor_digits === '') ? $minor_raw : $minor_digits;
        return $major_short . '.' . $minor_output;
    }

    return $major_short;
}

// Editing define() lines in data/config.php. Shared by the settings screens
// that rewrite the file in place (edit_config.php, smtp_settings.php,
// private_label.php).
//
// The patterns avoid the /s modifier and a lazy '(.*?)'. A hand-edited config
// file mixes LF and CRLF line endings; a lazy value match followed by a
// mandatory "\r\n" runs past an LF-terminated line and swallows every define
// up to the next CRLF one. Here the value class stops at the first unescaped
// quote and never crosses a line break, and the trailing line ending is
// optional, so a match is always confined to the key's own line.
function pg_config_define_value_pattern() {
    // A single-quoted PHP string literal: no bare quote, no line break,
    // backslash escapes allowed (update_config_define() writes \' for quotes).
    return "'(?:[^'\\\\\r\n]|\\\\.)*'";
}

// Update or insert a define() line
function update_config_define($content, $key, $value, $type = 'string') {
    $safe_key = preg_quote($key, '/');

    if ($type === 'boolean') {
        $bool_val = ($value === 'true' || $value === true || $value === '1') ? 'true' : 'false';
        // Always remove any malformed string-quoted boolean defines first (e.g. define('KEY', 'false'))
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*'(?:true|false)'\s*\);\r?\n?/i",
            '',
            $content
        );
        // Now update existing proper boolean define, or append a new one
        if (preg_match("/define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);/i", $content)) {
            return preg_replace(
                "/define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);/i",
                "define('" . $key . "', " . $bool_val . ");",
                $content
            );
        }
        return str_replace('?>', "define('" . $key . "', " . $bool_val . ");\r\n?>", $content);
    }

    // Backslashes are escaped along with the quote: a value ending in a
    // backslash would otherwise escape the closing quote and leave config.php
    // with an unterminated string, which takes the whole site down.
    $safe_value = addcslashes($value, "\\'");
    $define = "define('" . $key . "', '" . $safe_value . "');";
    $line = "/define\s*\(\s*'" . $safe_key . "'\s*,\s*" . pg_config_define_value_pattern() . "\s*\);/i";
    if (preg_match($line, $content)) {
        // The replacement is user data, so "$1" and "\\" in it must stay
        // literal rather than being read as back-references.
        return preg_replace($line, addcslashes($define, '\\$'), $content);
    }
    return str_replace('?>', $define . "\r\n?>", $content);
}

// Replace data/config.php with $content.
//
// The text goes to a sibling temp file first and is renamed over the original,
// so a request that includes config.php while a save is in progress never sees
// a truncated or empty file. Empty content is refused: every caller builds the
// new text from a read of the current file, and a failed read must not become
// an empty config. When the data directory itself is not writable but the file
// is (the documented 777-on-the-file setup), the write falls back to in place.
// Returns true when config.php holds the new content.
function pg_write_config_file($content) {
    if (!is_string($content) || (trim($content) === '')) {
        return false;
    }

    $length = strlen($content);
    $temp = dirname(CONFIG_FILE_PATH) . '/config.' . uniqid('tmp') . '.php';

    if (@file_put_contents($temp, $content, LOCK_EX) === $length) {
        // Keep the mode the operator gave config.php; the temp file was
        // created with the default umask.
        $mode = @fileperms(CONFIG_FILE_PATH);
        if ($mode !== false) {
            @chmod($temp, $mode & 0777);
        }
        if (@rename($temp, CONFIG_FILE_PATH)) {
            return true;
        }
    }
    @unlink($temp);

    return (@file_put_contents(CONFIG_FILE_PATH, $content, LOCK_EX) === $length);
}

// Remove a define() line entirely (called when value is empty)
function remove_config_define($content, $key, $type = 'string') {
    $safe_key = preg_quote($key, '/');
    if ($type === 'boolean') {
        // Remove proper boolean define
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*(?:true|false)\s*\);\r?\n?/i",
            '',
            $content
        );
        // Also remove malformed string-quoted boolean define
        $content = preg_replace(
            "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*'(?:true|false)'\s*\);\r?\n?/i",
            '',
            $content
        );
        return $content;
    }
    return preg_replace(
        "/[ \t]*define\s*\(\s*'" . $safe_key . "'\s*,\s*" . pg_config_define_value_pattern() . "\s*\);\r?\n?/i",
        '',
        $content
    );
}
