<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/wishlist.php and wishlist.php: the visitor cookie and
 * the key the table keeps for it, whose list a request reads, the SQL of the
 * owner, the merge into an account and the daily trim, how the endpoint reads
 * a request and in which order it checks it, and the places outside the
 * module that know the wishlist (cookie notice, personal data export, the
 * general job, the page cache).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function, for the checks that read how it is written.
function wishlist_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A cookie value is 64 lowercase hex characters, nothing else.
function test_wishlist_token_valid()
{
	$token = str_repeat('a1', 32);

	pg_assert_true(pg_wishlist_token_valid($token), '64 hex');
	pg_assert_true(pg_wishlist_token_valid(str_repeat('0', 64)), 'digits only');
	pg_assert_false(pg_wishlist_token_valid(substr($token, 1)), '63 characters');
	pg_assert_false(pg_wishlist_token_valid($token . 'a'), '65 characters');
	pg_assert_false(pg_wishlist_token_valid(strtoupper($token)), 'upper case');
	pg_assert_false(pg_wishlist_token_valid(str_repeat('g', 64)), 'not hex');
	pg_assert_false(pg_wishlist_token_valid($token . "\n"), 'a trailing new line');
	pg_assert_false(pg_wishlist_token_valid(''), 'empty');
	pg_assert_false(pg_wishlist_token_valid(array($token)), 'an array');
	pg_assert_false(pg_wishlist_token_valid(null), 'null');
	pg_assert_false(pg_wishlist_token_valid(123), 'a number');
}

// The table keeps the cookie's sha256, never the cookie.
function test_wishlist_token_key()
{
	$token = str_repeat('5f', 32);
	$key = pg_wishlist_token_key($token);

	pg_assert_same(hash('sha256', $token), $key, 'sha256 of the cookie');
	pg_assert_same($key, pg_wishlist_token_key($token), 'the same every time');
	pg_assert_true(pg_wishlist_token_valid($key), 'fits cookie_token VARCHAR(64)');
	pg_assert_true($key !== $token, 'not the cookie itself');
	pg_assert_same('', pg_wishlist_token_key('nope'), 'not a cookie');
	pg_assert_same('', pg_wishlist_token_key(array()), 'an array');
}

// A new cookie is 64 random hex characters, a different one each time.
function test_wishlist_token_new()
{
	$first = pg_wishlist_token_new();
	$second = pg_wishlist_token_new();

	pg_assert_true(pg_wishlist_token_valid($first), 'a valid cookie');
	pg_assert_true($first !== $second, 'random');
}

// An account owns its list by id; a visitor by the key of a valid cookie;
// a visitor without one has no list.
function test_wishlist_owner_for()
{
	$token = str_repeat('c3', 32);

	pg_assert_same(array('user_id' => 7, 'cookie_token' => ''), pg_wishlist_owner_for(7, ''), 'an account');
	pg_assert_same(array('user_id' => 7, 'cookie_token' => ''), pg_wishlist_owner_for('7', $token), 'an account, whatever cookie it carries');
	pg_assert_same(array('user_id' => 0, 'cookie_token' => hash('sha256', $token)), pg_wishlist_owner_for(0, $token), 'a visitor');
	pg_assert_same(null, pg_wishlist_owner_for(0, ''), 'a visitor without a cookie');
	pg_assert_same(null, pg_wishlist_owner_for(0, 'forged'), 'a visitor with a cookie of another shape');
	pg_assert_same(null, pg_wishlist_owner_for(-3, ''), 'a negative id');
}

// The condition of an owner's rows; nothing for an owner that is not well
// formed, so no query can run without one.
function test_wishlist_owner_sql()
{
	$key = hash('sha256', 'x');

	pg_assert_same("(wishlist_items.user_id = '7') AND (wishlist_items.cookie_token = '')", pg_wishlist_owner_sql(array('user_id' => 7, 'cookie_token' => '')));
	pg_assert_same("(wishlist_items.user_id = '0') AND (wishlist_items.cookie_token = '" . $key . "')", pg_wishlist_owner_sql(array('user_id' => 0, 'cookie_token' => $key)));
	pg_assert_same('', pg_wishlist_owner_sql(null), 'no owner');
	pg_assert_same('', pg_wishlist_owner_sql(array('user_id' => 0, 'cookie_token' => '')), 'nobody');
	pg_assert_same('', pg_wishlist_owner_sql(array('user_id' => 0, 'cookie_token' => "' OR '1'='1")), 'a key that is no key');
	pg_assert_same('', pg_wishlist_owner_sql(array('user_id' => 7, 'cookie_token' => $key)), 'both at once');
	pg_assert_same('', pg_wishlist_owner_sql(array('user_id' => 7)), 'a key missing');
}

// The merge copies with INSERT IGNORE (a product the account holds already
// would stop a plain UPDATE at the unique key) and then deletes the visitor's
// rows; nothing for an account or a key that is not well formed.
function test_wishlist_merge_sql()
{
	$key = hash('sha256', 'visitor');
	$statements = pg_wishlist_merge_sql(12, $key);

	pg_assert_same(2, count($statements), 'two statements');
	pg_assert_contains('INSERT IGNORE INTO wishlist_items (user_id, cookie_token, product_id, added_at)', $statements[0], 'copied first');
	pg_assert_contains("SELECT '12', '', product_id, added_at", $statements[0], 'into the account, keeping when each was saved');
	pg_assert_contains("(user_id = '0') AND (cookie_token = '" . $key . "')", $statements[0], 'from the visitor list');
	pg_assert_same("DELETE FROM wishlist_items WHERE (user_id = '0') AND (cookie_token = '" . $key . "')", $statements[1], 'then the visitor rows go');
	pg_assert_same(array(), pg_wishlist_merge_sql(0, $key), 'no account');
	pg_assert_same(array(), pg_wishlist_merge_sql(12, 'cookie'), 'no key');
}

// The merge runs for a signed-in visitor with a cookie, never while staff
// are signed in as a customer, and clears the cookie once the rows moved.
function test_wishlist_merge_guards()
{
	$source = wishlist_test_function_source('pg_wishlist_merge');

	$guard = strpos($source, "\$_SESSION['software']['logged_in_as_different_user']");
	$write = strpos($source, 'pg_wishlist_merge_sql(');
	$clear = strpos($source, 'pg_wishlist_cookie_clear()');

	pg_assert_true($guard !== false, 'skips impersonation');
	pg_assert_true(($guard !== false) && ($write !== false) && ($guard < $write), 'before any write');
	pg_assert_true(($write !== false) && ($clear !== false) && ($write < $clear), 'the cookie is cleared after the rows moved');
	pg_assert_contains('static $done', $source, 'once a request');
	pg_assert_contains('pg_wishlist_merge()', wishlist_test_function_source('pg_wishlist_owner'), 'every read and write of an account merges first');
}

// The cookie is written as the language cookie is: a year, HttpOnly, Lax,
// Secure on https, on PHP before 7.3 too.
function test_wishlist_cookie_flags()
{
	foreach (array('pg_wishlist_cookie_write', 'pg_wishlist_cookie_clear') as $name) {
		$source = wishlist_test_function_source($name);

		pg_assert_contains("'httponly' => true, 'samesite' => 'Lax'", $source, $name . ' 7.3+');
		pg_assert_contains("PATH . '; samesite=Lax', '', \$secure, true", $source, $name . ' before 7.3');
		pg_assert_contains('pg_request_is_https()', $source, $name . ' Secure on https');
		pg_assert_contains('headers_sent()', $source, $name . ' not after the headers');
	}

	pg_assert_same('pg_wishlist', PG_WISHLIST_COOKIE);
	pg_assert_same(31536000, PG_WISHLIST_LIFETIME, 'a year');
}

// A visitor's cookie runs out a year after the newest product in the list,
// the moment the daily trim may remove the list; an empty list keeps none.
function test_wishlist_cookie_expires()
{
	pg_assert_same(5000 + 31536000, pg_wishlist_cookie_expires('5000'), 'a year from the newest product');
	pg_assert_same(5000 + 31536000, pg_wishlist_cookie_expires(5000));
	pg_assert_same(0, pg_wishlist_cookie_expires(null), 'an empty list');
	pg_assert_same(0, pg_wishlist_cookie_expires('0'));
	pg_assert_same(0, pg_wishlist_cookie_expires('x'));

	$now = 100000000;

	foreach (array(-1, 0, 1) as $offset) {
		$newest = pg_wishlist_trim_before($now) + $offset;

		pg_assert_same($newest < pg_wishlist_trim_before($now), pg_wishlist_cookie_expires($newest) < $now, 'the cookie runs out when the trim may remove the list (' . $offset . ')');
	}
}

// Each write sends the visitor's cookie again with the expiry the list gives,
// never a year from now: saving a product the list holds already, or removing
// the newest one, leaves the cookie and the trim in step.
function test_wishlist_cookie_follows_list()
{
	$set = wishlist_test_function_source('pg_wishlist_set');

	$delete = strpos($set, 'DELETE FROM wishlist_items');
	$insert = strpos($set, 'INSERT IGNORE INTO wishlist_items');
	$first = strpos($set, 'pg_wishlist_cookie_follow($owner)');
	$last = strrpos($set, 'pg_wishlist_cookie_follow($owner)');

	pg_assert_same(2, substr_count($set, 'pg_wishlist_cookie_follow($owner)'), 'after removing and after adding');
	pg_assert_true(($delete !== false) && ($first !== false) && ($delete < $first), 'once the row is removed');
	pg_assert_true(($insert !== false) && ($last !== false) && ($insert < $last), 'once the row is written');

	$follow = wishlist_test_function_source('pg_wishlist_cookie_follow');

	pg_assert_contains("(int) \$owner['user_id'] !== 0", $follow, 'a visitor list only');
	pg_assert_contains('SELECT MAX(wishlist_items.added_at) FROM wishlist_items WHERE', $follow, 'from the newest product in the list');
	pg_assert_contains('pg_wishlist_cookie_expires(', $follow);
	pg_assert_contains('pg_wishlist_cookie_clear()', $follow, 'an emptied list clears it');
	pg_assert_contains('pg_wishlist_cookie_write(pg_wishlist_visitor_token(), $expires)', $follow, 'only the visitor\'s own cookie, the one this session started included');
	pg_assert_false(strpos($follow, 'time()') !== false, 'never a year from now');
}

// Only saving a product starts a visitor's cookie. Reading the list (the
// state call of every cached catalog page) never does: that is why the
// cookie counts as necessary.
function test_wishlist_reads_start_no_cookie()
{
	$owner = wishlist_test_function_source('pg_wishlist_owner');

	$create = strpos($owner, 'if ($create');
	$new = strpos($owner, 'pg_wishlist_token_new()');

	pg_assert_true(($create !== false) && ($new !== false) && ($create < $new), 'a new cookie only when asked to start one');

	foreach (array('pg_wishlist_ids', 'pg_wishlist_has') as $name) {
		$source = wishlist_test_function_source($name);

		pg_assert_contains('pg_wishlist_owner(false)', $source, $name);
		pg_assert_false(strpos($source, 'pg_wishlist_owner(true)') !== false, $name . ' starts nothing');
	}

	// Which functions of the module make each call.
	$module = realpath(PG_FUNCTIONS_DIR . '/includes/fn/wishlist.php');
	$callers = array('pg_wishlist_owner(true)' => array(), 'pg_wishlist_cookie_follow(' => array(), 'pg_wishlist_cookie_write(' => array());

	foreach (get_defined_functions()['user'] as $name) {
		$function = new ReflectionFunction($name);

		if (realpath($function->getFileName()) !== $module) {
			continue;
		}

		$source = str_replace('function ' . $function->getName() . '(', '', wishlist_test_function_source($name));

		foreach (array_keys($callers) as $call) {
			if (strpos($source, $call) !== false) {
				$callers[$call][] = $function->getName();
			}
		}
	}

	pg_assert_same(array('pg_wishlist_set'), $callers['pg_wishlist_owner(true)'], 'only saving starts a list');
	pg_assert_same(array('pg_wishlist_set'), $callers['pg_wishlist_cookie_follow('], 'the cookie is sent after a write');
	pg_assert_same(array('pg_wishlist_cookie_follow'), $callers['pg_wishlist_cookie_write('], 'and nowhere else');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/wishlist.php');

	foreach (array_keys($callers) as $call) {
		pg_assert_same(0, substr_count($endpoint, $call), 'the endpoint goes through pg_wishlist_set(): ' . $call);
	}
}

// Two saves a visitor without a cookie sends at once both arrive without one.
// The session's file lock runs them one after the other, and the second
// writes into the list the first one started, whose cookie the session keeps
// until the browser sends it, instead of starting a list of its own whose
// cookie the browser would keep in place of the first one's.
function test_wishlist_first_saves_share_one_list()
{
	$saved_cookie = $_COOKIE;
	$saved_session = $_SESSION ?? null;

	$started = str_repeat('7e', 32);
	$sent = str_repeat('4b', 32);
	$owner = array('user_id' => 0, 'cookie_token' => hash('sha256', $started));

	try {
		$_COOKIE = array();
		$_SESSION = array('software' => array('wishlist_cookie' => $started));

		pg_assert_same($started, pg_wishlist_visitor_token(), 'the cookie this session started, before the browser sends it');
		pg_assert_same($owner, pg_wishlist_owner(true), 'a second save writes into that list');
		pg_assert_same($started, $_SESSION['software']['wishlist_cookie'], 'and starts no list of its own');
		pg_assert_same($owner, pg_wishlist_owner(false), 'a read or a removal sees it too');

		$_COOKIE = array(PG_WISHLIST_COOKIE => $sent);
		pg_assert_same($sent, pg_wishlist_visitor_token(), 'the cookie the request carries comes first');

		$_COOKIE = array();
		$_SESSION['software']['wishlist_cookie'] = 'forged';
		pg_assert_same('', pg_wishlist_visitor_token(), 'a session value of another shape names no list');
		pg_assert_same(null, pg_wishlist_owner(false));

		$_SESSION = array();
		pg_assert_same('', pg_wishlist_visitor_token(), 'nothing started');
	} finally {
		$_COOKIE = $saved_cookie;
		$_SESSION = $saved_session;
	}

	$owner_source = wishlist_test_function_source('pg_wishlist_owner');

	$read = strpos($owner_source, 'pg_wishlist_visitor_token()');
	$new = strpos($owner_source, 'pg_wishlist_token_new()');
	$keep = strpos($owner_source, "\$_SESSION['software']['wishlist_cookie'] = \$cookie;");

	pg_assert_true(($read !== false) && ($new !== false) && ($read < $new), 'a new cookie only when the session started none');
	pg_assert_true(($new !== false) && ($keep !== false) && ($new < $keep), 'the session keeps the cookie it started');
	pg_assert_contains('pg_wishlist_visitor_token()', wishlist_test_function_source('pg_wishlist_merge'), 'signing in moves that list too');

	$clear = wishlist_test_function_source('pg_wishlist_cookie_clear');
	$forget = strpos($clear, "unset(\$_SESSION['software']['wishlist_cookie']);");
	$sent_check = strpos($clear, 'headers_sent()');

	pg_assert_true(($forget !== false) && ($sent_check !== false) && ($forget < $sent_check), 'a merged or emptied list is not started again from the session');

	// A guest who saved something still gets the catalog from the page cache:
	// the key stands for the pg_wishlist cookie, which the cache does not look
	// at either, and the hearts are filled in by script.
	pg_assert_true(pg_page_cache_session_anonymous(array('software' => array('token' => 'x', 'wishlist_cookie' => $started))), 'the session stays anonymous');
}

// Deleting a product for good takes it out of every list in the same request:
// MySQL 5.7 hands the highest id out again after a restart, a new product
// with that id would show as saved in each of them, and the trim no longer
// sees those rows as orphaned once the id is in use. A product in the
// Recycle Bin is not deleted and keeps its place in the lists.
function test_wishlist_forget_product()
{
	pg_assert_same("DELETE FROM wishlist_items WHERE product_id = '812'", pg_wishlist_forget_product_sql(812));
	pg_assert_same("DELETE FROM wishlist_items WHERE product_id = '812'", pg_wishlist_forget_product_sql('812'));
	pg_assert_same('', pg_wishlist_forget_product_sql(0), 'no product');
	pg_assert_same('', pg_wishlist_forget_product_sql('0'));
	pg_assert_same('', pg_wishlist_forget_product_sql(-4));
	pg_assert_same('', pg_wishlist_forget_product_sql('abc'));
	pg_assert_same('', pg_wishlist_forget_product_sql(array(812)), 'an array');
	pg_assert_same('', pg_wishlist_forget_product_sql(null));

	$forget = wishlist_test_function_source('pg_wishlist_forget_product');

	pg_assert_contains('pg_wishlist_forget_product_sql(', $forget);
	pg_assert_contains('pg_wishlist_ready()', $forget, 'only where the table exists');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$from = strpos($source, 'function pg_pb_delete_product(');
	$body = ($from !== false) ? substr($source, $from, strpos($source, "\n}\n", $from) - $from) : '';

	$delete = strpos($body, "DELETE FROM products WHERE");
	$call = strpos($body, 'pg_wishlist_forget_product($product_id);');

	pg_assert_true($call !== false, 'every permanent delete of a product removes it from the lists');
	pg_assert_true(($delete !== false) && ($call !== false) && ($delete < $call), 'with the product row');

	foreach (pg_catalog_bin_statements('product', 5, array(5), 0, 1) as $statement) {
		pg_assert_false(strpos($statement, 'wishlist_items') !== false, 'the Recycle Bin keeps the lists');
	}
}

// Deleting an account takes its favourites in the same request: MySQL 5.7
// hands the highest id out again after a restart, and the next account would
// inherit the list. Never the visitor lists (user_id 0).
function test_wishlist_forget_user()
{
	pg_assert_same("DELETE FROM wishlist_items WHERE user_id = '12'", pg_wishlist_forget_user_sql(12));
	pg_assert_same("DELETE FROM wishlist_items WHERE user_id = '12'", pg_wishlist_forget_user_sql('12'));
	pg_assert_same('', pg_wishlist_forget_user_sql(0), 'never the visitor lists');
	pg_assert_same('', pg_wishlist_forget_user_sql('0'));
	pg_assert_same('', pg_wishlist_forget_user_sql(-4));
	pg_assert_same('', pg_wishlist_forget_user_sql('abc'));
	pg_assert_same('', pg_wishlist_forget_user_sql(array(12)), 'an array');
	pg_assert_same('', pg_wishlist_forget_user_sql(null));

	pg_assert_contains('pg_wishlist_forget_user_sql(', wishlist_test_function_source('pg_wishlist_forget_user'));

	foreach (array('delete_users.php', 'edit_user.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		$forget = strpos($source, 'pg_wishlist_forget_user(');
		$delete = strpos($source, 'DELETE FROM user WHERE');

		pg_assert_true($forget !== false, $file . ' removes the favourites');
		pg_assert_true(($forget !== false) && ($delete !== false) && ($forget < $delete), $file . ' with the rest of the account');
	}
}

// The endpoint's reading of a request.
function test_wishlist_request_values()
{
	pg_assert_same('state', pg_wishlist_action('state'));
	pg_assert_same('toggle', pg_wishlist_action(' Toggle '));
	pg_assert_same('', pg_wishlist_action('delete'), 'an unknown action');
	pg_assert_same('', pg_wishlist_action(array('add')), 'an array');

	pg_assert_same(42, pg_wishlist_product_id('42'));
	pg_assert_same(42, pg_wishlist_product_id(42));
	pg_assert_same(0, pg_wishlist_product_id('0'));
	pg_assert_same(0, pg_wishlist_product_id('-5'));
	pg_assert_same(0, pg_wishlist_product_id('4x'));
	pg_assert_same(0, pg_wishlist_product_id('1.5'));
	pg_assert_same(0, pg_wishlist_product_id('99999999999'), 'beyond the column');
	pg_assert_same(0, pg_wishlist_product_id(array(4)), 'an array');

	pg_assert_true(pg_wishlist_new_state('add', true));
	pg_assert_true(pg_wishlist_new_state('add', false));
	pg_assert_false(pg_wishlist_new_state('remove', true));
	pg_assert_true(pg_wishlist_new_state('toggle', false));
	pg_assert_false(pg_wishlist_new_state('toggle', true));
	pg_assert_same(null, pg_wishlist_new_state('state', false), 'a read');
}

// Adding is checked against the catalog and starts a cookie; removing needs
// neither, so a product switched off since can still leave the list.
function test_wishlist_set_rules()
{
	$source = wishlist_test_function_source('pg_wishlist_set');

	$remove = strpos($source, 'if (!$on)');
	$check = strpos($source, 'pg_wishlist_product_ok($product_id)');
	$create = strpos($source, 'pg_wishlist_owner(true)');

	pg_assert_true(($remove !== false) && ($check !== false) && ($remove < $check), 'removing comes before the catalog check');
	pg_assert_true(($check !== false) && ($create !== false) && ($check < $create), 'no cookie for a product that is refused');
	pg_assert_contains('INSERT IGNORE INTO wishlist_items', $source, 'saving twice keeps one row');
	pg_assert_contains('UNIX_TIMESTAMP()', $source, 'when it was saved');
	pg_assert_same('(products.enabled = 1)', pg_wishlist_product_live_sql(), 'what the catalog widgets show');
	pg_assert_contains('pg_wishlist_product_live_sql()', wishlist_test_function_source('pg_wishlist_ids'), 'the list shows only those');
}

// A visitor list is kept while a browser may hold its cookie; the trim reads
// in batches and removes only what it read.
function test_wishlist_trim_sql()
{
	pg_assert_same(1000 - 31536000, pg_wishlist_trim_before(1000));

	$queries = pg_wishlist_trim_queries(5000, 100);

	pg_assert_same(array('visitors', 'products', 'accounts'), array_keys($queries));
	pg_assert_same('cookie_token', $queries['visitors'][0]);
	pg_assert_contains("HAVING MAX(added_at) < '5000'", $queries['visitors'][1], 'the last save of the list');
	pg_assert_contains("WHERE user_id = '0'", $queries['visitors'][1], 'visitor lists only');
	pg_assert_contains('LEFT JOIN products ON products.id = wishlist_items.product_id', $queries['products'][1]);
	pg_assert_contains('LEFT JOIN user ON user.user_id = wishlist_items.user_id', $queries['accounts'][1]);
	pg_assert_contains('(wishlist_items.user_id > 0)', $queries['accounts'][1], 'never a visitor row');

	foreach ($queries as $kind => $query) {
		pg_assert_contains('LIMIT 100', $query[1], $kind . ' in batches');
	}

	pg_assert_same("DELETE FROM wishlist_items WHERE id IN ('3', '9')", pg_wishlist_trim_delete_sql('id', array('3', '9', '0', 'x', '3')));
	$key = hash('sha256', 'old');
	pg_assert_same("DELETE FROM wishlist_items WHERE (user_id = '0') AND (cookie_token IN ('" . $key . "'))", pg_wishlist_trim_delete_sql('cookie_token', array($key, "x' OR '1'='1")));
	pg_assert_same('', pg_wishlist_trim_delete_sql('id', array()), 'nothing read');
}

// Once a day.
function test_wishlist_trim_due()
{
	pg_assert_true(pg_wishlist_trim_due(0, 1000000), 'never ran');
	pg_assert_false(pg_wishlist_trim_due(1000000, 1000000 + 86399), 'within the day');
	pg_assert_true(pg_wishlist_trim_due(1000000, 1000000 + 86400), 'a day later');
	pg_assert_contains("pg_wishlist_trim_daily();", (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'the general job runs it');
}

// The endpoint: the state is JSON no cache keeps; a write checks the token
// with hash_equals() first, then the limit, and only then writes.
function test_wishlist_endpoint_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/wishlist.php');

	pg_assert_contains("include('init.php');", $source);
	pg_assert_contains('pg_post_body_discarded()', $source, 'a body over post_max_size');
	pg_assert_contains("header('Cache-Control: private, no-store');", $source, 'never cached');
	pg_assert_contains("'ids'", $source, 'the state names the products');
	pg_assert_contains("'token'", $source, 'and the form token a cached page lacks');

	$token = strpos($source, 'hash_equals($session_token, $post_token)');
	$limit = strpos($source, 'pg_wishlist_write_throttled()');
	$merge = strrpos($source, 'pg_wishlist_merge()');
	$write = strpos($source, 'pg_wishlist_set(');

	pg_assert_true($token !== false, 'the token is compared with hash_equals()');
	pg_assert_true(($token !== false) && ($limit !== false) && ($token < $limit), 'the token before the limit');
	pg_assert_true(($limit !== false) && ($merge !== false) && ($limit < $merge), 'the limit before the merge of a write');
	pg_assert_true(($merge !== false) && ($write !== false) && ($merge < $write), 'the merge before the write');
	pg_assert_same(1, substr_count($source, 'pg_wishlist_set('), 'one write');
	pg_assert_contains('pg_safe_redirect_path(', $source, 'a form without a script goes back where it came from');
	pg_assert_contains('pg_error_throws(true)', $source, 'a failed query answers in JSON');
}

// The limit is counted per address in the firewall's buckets and logged.
function test_wishlist_write_limit()
{
	$source = wishlist_test_function_source('pg_wishlist_write_throttled');

	pg_assert_contains("waf_rate_exceeded(waf_ip_subject(\$ip), 'wishlist', PG_WISHLIST_WRITE_LIMIT, PG_WISHLIST_WRITE_WINDOW)", $source);
	pg_assert_contains("waf_log_event('rate', 'rate-wishlist'", $source, 'in the firewall log');
	pg_assert_contains("waf_text_log('DENY'", $source, 'in data/firewall.log');
	pg_assert_same(60, PG_WISHLIST_WRITE_LIMIT);
	pg_assert_same(600, PG_WISHLIST_WRITE_WINDOW);
}

// The cookie is listed as necessary in the notice, only where the table
// exists (it does not in the test runner).
function test_wishlist_consent_row()
{
	$names = array();

	foreach (pg_consent_catalogue('<p>page</p>')['necessary'] as $row) {
		$names[] = $row[0];
	}

	pg_assert_false(in_array(PG_WISHLIST_COOKIE, $names, true), 'not without the table');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');
	$from = strpos($source, 'function pg_consent_catalogue(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains('pg_wishlist_ready()', $section, 'only where the table exists');
	pg_assert_contains("\$catalogue['necessary'][] = array(PG_WISHLIST_COOKIE, true, lang('", $section, 'as a necessary cookie');
	pg_assert_true(in_array('Remembers the products you saved to your favourites before signing in.', pg_consent_ui_keys(), true), 'its words are in the cookie window list');
}

// A person's export has their favourites, by account.
function test_wishlist_privacy_export_section()
{
	$sections = pg_privacy_export_sections();

	pg_assert_true(isset($sections['wishlist']), 'a section');

	$source = wishlist_test_function_source('pg_privacy_export_collect');

	pg_assert_contains("\$sections['wishlist']", $source, 'read');
	pg_assert_contains('FROM wishlist_items', $source);
	pg_assert_contains("_pg_privacy_export_in('wishlist_items.user_id', \$user_ids)", $source, 'by account');
}

// A heart click of a signed-in member changes no page anyone else is sent,
// so it leaves the page cache as it is.
function test_wishlist_page_cache_untouched()
{
	pg_assert_false(pg_page_cache_request_dirties('POST', '/pinegrap/wishlist.php', true, false));
}
