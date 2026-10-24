<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments system widget (includes/fn/widgets_comments.php):
 * the per-pass record of the pages whose thread a widget drew, which the
 * classic comments block of get_page_content.php reads to leave those pages
 * alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// -- slice 8: the hand-off ----------------------------------------------------

// A page is claimed once per pass; a second widget on it gets false. The
// record a pass kept comes back when the outer pass restores its own.
function test_comments_widget_registry_claims_once()
{
	$found = pg_comments_widget_pages(array());

	pg_assert_true(pg_comments_widget_claim(12), 'first claim');
	pg_assert_false(pg_comments_widget_claim(12), 'second claim of the same page');
	pg_assert_false(pg_comments_widget_claim('12'), 'the same page as a digit string');
	pg_assert_true(pg_comments_widget_drawn(12), 'drawn');
	pg_assert_true(pg_comments_widget_drawn('12'), 'drawn, asked with a digit string');
	pg_assert_false(pg_comments_widget_drawn(13), 'another page');

	foreach (array(0, -3, 'x', '12abc', '', ' 12', array(5), true, null, 1.5) as $bad) {
		pg_assert_false(pg_comments_widget_claim($bad), 'not a page id: ' . var_export($bad, true));
		pg_assert_false(pg_comments_widget_drawn($bad), 'never drawn: ' . var_export($bad, true));
	}

	pg_assert_true(pg_comments_widget_claim('14'), 'a digit string is a page id');

	$prev = pg_comments_widget_pages(array());
	pg_assert_same(array(12 => true, 14 => true), $prev, 'the pass kept its pages');
	pg_assert_false(pg_comments_widget_drawn(12), 'a new pass starts empty');
	pg_assert_true(pg_comments_widget_claim(12), 'and claims again');

	$pass = pg_comments_widget_pages($prev);
	pg_assert_same(array(12 => true), $pass, 'the inner pass hands back its own record');
	pg_assert_true(pg_comments_widget_drawn(12, $pass), 'drawn in the record handed over');
	pg_assert_false(pg_comments_widget_drawn(14, $pass), 'not in that record');
	pg_assert_true(pg_comments_widget_drawn(14), 'the outer record is back');
	pg_assert_false(pg_comments_widget_drawn(14, array()), 'an empty record names nothing');

	pg_comments_widget_pages($found);
}

// get_page_content.php tells the widgets which page is drawn, keeps one
// record per pass and lets the classic block skip a page a widget drew.
function test_comments_widget_page_hand_off_wiring()
{
	$page = comments_widget_test_source('get_page_content.php');

	$context = strpos($page, 'pg_sw_render_context(array(');
	$close = ($context === false) ? false : strpos($page, ';', $context);
	pg_assert_true(($context !== false) && ($close !== false), 'the context is set');
	if (($context !== false) && ($close !== false)) {
		pg_assert_contains("'page_id' => (int)\$page_id", substr($page, $context, $close - $context), 'the drawn page goes into the context');
	}

	$open = strpos($page, 'pg_comments_widget_pages(array())');
	$expand = strpos($page, '_expand_system_widgets($content');
	$nested = strpos($page, 'pg_expand_nested_components($content');
	$restore = strpos($page, 'pg_comments_widget_pages($pg_cw_prev)');
	pg_assert_true(($open !== false) && ($expand !== false) && ($open < $expand), 'a fresh record before the widget pass');
	pg_assert_true(($restore !== false) && ($nested !== false) && ($nested < $restore), 'the outer record back after the nested pass');
	pg_assert_same(1, substr_count($page, '$pg_cw_pages = array();'), 'the pass starts with no page');

	$skip = "pg_comments_widget_drawn(\$system_region_properties['page_id'], \$pg_cw_pages)";
	pg_assert_same(1, substr_count($page, $skip), 'the classic block asks once');
	$record = strpos($page, 'pg_sw_record_comment_context($system_region_properties');
	$at = strpos($page, $skip);
	$item = strpos($page, "\$item_id = '0';");
	pg_assert_true(($record !== false) && ($at !== false) && ($item !== false) && ($record < $at) && ($at < $item), 'in the condition that opens the block');

	pg_assert_contains("str_replace('<!--pg-comments-block-->', \$comments_block_html", $page, 'a leftover marker is still emptied');
}

// -- slice 10: display options, order, links, flags ---------------------------

function comments_widget_test_ids($entries)
{
	$ids = array();
	foreach ($entries as $entry) {
		$ids[] = $entry['id'];
	}
	return $ids;
}

function comments_widget_test_column($entries, $key)
{
	$out = array();
	foreach ($entries as $entry) {
		$out[] = $entry[$key];
	}
	return $out;
}

function test_comments_widget_options_defaults_and_clamps()
{
	$defaults = array('order' => 'oldest', 'featured' => 'first', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0);
	pg_assert_same($defaults, pg_comments_widget_options(array()), 'defaults');
	pg_assert_same($defaults, pg_comments_widget_options('x'), 'not an array');
	pg_assert_same($defaults, pg_comments_widget_options(array('order' => 1, 'featured' => true, 'layout' => array('threaded'))), 'enums that are not strings');
	pg_assert_same($defaults, pg_comments_widget_options(array('order' => 'random', 'featured' => 'pinned', 'layout' => 'tree')), 'unknown words');

	$kept = pg_comments_widget_options(array('order' => 'newest', 'featured' => 'filter', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20));
	pg_assert_same(array('order' => 'newest', 'featured' => 'filter', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20), $kept, 'every option kept');
	pg_assert_same('none', pg_comments_widget_options(array('featured' => 'none'))['featured'], 'featured none');

	foreach (array(array(0, 1), array(9, 3), array('2', 2), array('x', 1), array(-1, 1), array('-1', 1), array(true, 1), array(2.5, 1), array(3, 3)) as $case) {
		pg_assert_same($case[1], pg_comments_widget_options(array('max_depth' => $case[0]))['max_depth'], 'max_depth ' . var_export($case[0], true));
	}

	foreach (array(array(20, 20), array(15, 0), array('50', 50), array('10', 10), array('x', 0), array(-10, 0), array(0, 0), array(true, 0)) as $case) {
		pg_assert_same($case[1], pg_comments_widget_options(array('per_page' => $case[0]))['per_page'], 'per_page ' . var_export($case[0], true));
	}
}

function test_comments_widget_order_featured_modes()
{
	$rows = array(
		array('id' => '1', 'featured' => '0'),
		array('id' => '2', 'featured' => '0'),
		array('id' => '3', 'featured' => '1'),
		array('id' => '4', 'featured' => '0'),
	);

	$first = pg_comments_widget_order($rows, array());
	pg_assert_same(array(3, 1, 2, 4), comments_widget_test_ids($first), 'first: featured pinned to the top');
	pg_assert_same(array(true, false, false, false), comments_widget_test_column($first, 'featured'), 'first: the pinned one is marked');
	pg_assert_same(array('row', 'id', 'parent_id', 'depth', 'root_id', 'replies', 'featured'), array_keys($first[0]), 'entry keys');
	pg_assert_same(array(0, 0, 0, 0), comments_widget_test_column($first, 'depth'), 'flat for now');

	pg_assert_same(array(3, 4, 2, 1), comments_widget_test_ids(pg_comments_widget_order($rows, array('order' => 'newest'))), 'newest first, featured still on top');

	$none = pg_comments_widget_order($rows, array('featured' => 'none'));
	pg_assert_same(array(1, 2, 3, 4), comments_widget_test_ids($none), 'none: the order read');
	pg_assert_same(array(false, false, false, false), comments_widget_test_column($none, 'featured'), 'none: nothing is featured');

	$filter = pg_comments_widget_order($rows, array('featured' => 'filter'));
	pg_assert_same(array(1, 2, 3, 4), comments_widget_test_ids($filter), 'filter: the order read');
	pg_assert_same(array(false, false, true, false), comments_widget_test_column($filter, 'featured'), 'filter: marked');

	pg_assert_same(array(1), comments_widget_test_ids(pg_comments_widget_order(array('x', $rows[0], null), array())), 'entries that are not rows are skipped');
	pg_assert_same(array(), pg_comments_widget_order('x', array()), 'no rows');

	// A featured reply stays in its place; only a top-level one is pinned.
	$replies = array(
		array('id' => '1', 'featured' => '0', 'parent_id' => '0'),
		array('id' => '2', 'featured' => '1', 'parent_id' => '1'),
		array('id' => '3', 'featured' => '1', 'parent_id' => '0'),
	);
	pg_assert_same(array(3, 1, 2), comments_widget_test_ids(pg_comments_widget_order($replies, array())), 'a featured reply is not pinned');
}

function test_comments_widget_view_urls()
{
	$urls = pg_comments_widget_view_urls('/forum/topic', array('comments' => 'all', 'r' => 'ab c', 'x' => array('1'), 'q' => 'ü&'));
	pg_assert_same('/forum/topic?r=ab+c&q=%C3%BC%26&comments=all#software_comments', $urls['all'], 'show all keeps the other values');
	pg_assert_same('/forum/topic?r=ab+c&q=%C3%BC%26#software_comments', $urls['featured'], 'show featured drops ?comments');

	$bare = pg_comments_widget_view_urls('/p', array('comments' => 'featured'));
	pg_assert_same('/p?comments=all#software_comments', $bare['all'], 'a ? when nothing else is left');
	pg_assert_same('/p#software_comments', $bare['featured'], 'no ? at all');

	pg_assert_same(array('all' => '/p?comments=all#software_comments', 'featured' => '/p#software_comments'), pg_comments_widget_view_urls('/p', 'x'), 'params that are not an array');
}

function comments_widget_test_facts($over = array())
{
	return $over + array(
		'allow_new' => true,
		'signed_in' => false,
		'require_login' => false,
		'search_index' => false,
		'count' => 2,
		'featured_view' => false,
		'has_featured' => false,
		'featured_mode' => 'first',
		'staff' => false,
		'name_mode' => 'typed',
		'rating_on' => false,
		'files_on' => false,
		'watching_on' => false,
	);
}

function test_comments_widget_thread_flags_table()
{
	$open = pg_comments_widget_thread_flags(comments_widget_test_facts());
	$keys = array('comments_open', 'comments_closed', 'can_comment', 'must_sign_in', 'is_signed_in', 'is_signed_out', 'has_comments', 'no_comments',
		'featured_view', 'has_featured', 'is_staff', 'name_is_typed', 'name_is_chosen', 'name_is_fixed', 'rating_on', 'files_on', 'watching_on');
	pg_assert_same($keys, array_keys($open), 'exactly the 17 thread flags');
	pg_assert_true($open['comments_open'] && $open['can_comment'] && $open['is_signed_out'] && $open['has_comments'] && $open['name_is_typed'], 'open, signed out');
	pg_assert_false($open['comments_closed'] || $open['must_sign_in'] || $open['is_signed_in'] || $open['no_comments'], 'open, signed out: the rest');

	$closed = pg_comments_widget_thread_flags(comments_widget_test_facts(array('allow_new' => false, 'require_login' => true)));
	pg_assert_true($closed['comments_closed'], 'closed');
	pg_assert_false($closed['comments_open'] || $closed['can_comment'] || $closed['must_sign_in'], 'closed: no form and no sign-in note');
	pg_assert_false($closed['name_is_typed'], 'closed: no name control');

	$login = pg_comments_widget_thread_flags(comments_widget_test_facts(array('require_login' => true)));
	pg_assert_true($login['must_sign_in'], 'sign-in required, signed out');
	pg_assert_false($login['can_comment'], 'sign-in required, signed out: no form');

	$member = pg_comments_widget_thread_flags(comments_widget_test_facts(array('require_login' => true, 'signed_in' => true, 'name_mode' => 'chosen')));
	pg_assert_true($member['can_comment'] && $member['is_signed_in'] && $member['name_is_chosen'], 'signed in');
	pg_assert_false($member['must_sign_in'] || $member['is_signed_out'] || $member['name_is_typed'] || $member['name_is_fixed'], 'signed in: the rest');

	$index = pg_comments_widget_thread_flags(comments_widget_test_facts(array('search_index' => true, 'signed_in' => true, 'staff' => true, 'require_login' => true)));
	pg_assert_false($index['can_comment'] || $index['must_sign_in'] || $index['is_signed_in'] || $index['is_staff'] || $index['name_is_typed'], 'search index: no form, signed out, not staff');
	pg_assert_true($index['is_signed_out'] && $index['comments_open'], 'search index: read as a visitor');

	$staff = pg_comments_widget_thread_flags(comments_widget_test_facts(array('staff' => true, 'signed_in' => true)));
	pg_assert_true($staff['is_staff'], 'staff');

	$none = pg_comments_widget_thread_flags(comments_widget_test_facts(array('has_featured' => true, 'featured_mode' => 'none')));
	pg_assert_false($none['has_featured'], 'none ignores featured comments');
	$first = pg_comments_widget_thread_flags(comments_widget_test_facts(array('has_featured' => true, 'featured_view' => true)));
	pg_assert_true($first['has_featured'] && $first['featured_view'], 'featured, as given');

	$empty = pg_comments_widget_thread_flags(comments_widget_test_facts(array('count' => 0, 'rating_on' => true, 'files_on' => true, 'watching_on' => true, 'name_mode' => 'fixed')));
	pg_assert_true($empty['no_comments'] && $empty['rating_on'] && $empty['files_on'] && $empty['watching_on'] && $empty['name_is_fixed'], 'no comments yet, features on');
	pg_assert_false($empty['has_comments'], 'no comments yet');

	pg_assert_same($keys, array_keys(pg_comments_widget_thread_flags('x')), 'facts that are not an array');
}

function comments_widget_test_state($over = array())
{
	return $over + array('is_staff' => false, 'sees_pending' => false, 'own_ids' => array(), 'rating_on' => true, 'badge_label' => '');
}

function test_comments_widget_row_flags_table()
{
	$zero = '0000-00-00 00:00:00';
	$keys = array('comment_is_featured', 'comment_is_pending', 'comment_is_own_pending', 'comment_is_scheduled', 'comment_has_rating',
		'comment_has_file', 'comment_has_badge', 'comment_is_verified', 'comment_has_seller_reply', 'comment_can_edit');

	$plain = pg_comments_widget_row_flags(array('id' => '5', 'published' => '1', 'publish_date_and_time' => $zero), false, comments_widget_test_state());
	pg_assert_same($keys, array_keys($plain), 'exactly the 10 row flags');
	pg_assert_same(array_fill_keys($keys, false), $plain, 'a plain published comment');
	pg_assert_true(pg_comments_widget_row_flags(array('id' => '5'), true, comments_widget_test_state())['comment_is_featured'], 'featured as given');

	$own = pg_comments_widget_row_flags(array('id' => '5', 'published' => '0', 'publish_date_and_time' => $zero), false, comments_widget_test_state(array('own_ids' => array(5))));
	pg_assert_true($own['comment_is_pending'] && $own['comment_is_own_pending'], 'own pending');
	pg_assert_false($own['comment_is_scheduled'], 'own pending is not scheduled');

	$other = pg_comments_widget_row_flags(array('id' => '6', 'published' => '0', 'publish_date_and_time' => $zero), false, comments_widget_test_state(array('own_ids' => array(5))));
	pg_assert_false($other['comment_is_own_pending'], 'another visitor\'s pending comment is not own');

	$scheduled = array('id' => '5', 'published' => '0', 'publish_date_and_time' => '2026-11-01 10:00:00');
	$staff = pg_comments_widget_row_flags($scheduled, false, comments_widget_test_state(array('is_staff' => true, 'sees_pending' => true, 'own_ids' => array(5))));
	pg_assert_true($staff['comment_is_scheduled'] && $staff['comment_can_edit'], 'scheduled, for staff');
	pg_assert_false($staff['comment_is_own_pending'], 'a scheduled comment is not awaiting approval');
	pg_assert_false(pg_comments_widget_row_flags($scheduled, false, comments_widget_test_state())['comment_is_scheduled'], 'scheduled shows only to who sees pending comments');

	foreach (array(array('0', true, false), array('3', true, true), array('7', true, false), array('3', false, false), array(array(3), true, false)) as $case) {
		$flags = pg_comments_widget_row_flags(array('rating' => $case[0]), false, comments_widget_test_state(array('rating_on' => $case[1])));
		pg_assert_same($case[2], $flags['comment_has_rating'], 'rating ' . var_export($case[0], true) . ($case[1] ? ' on' : ' off'));
	}

	pg_assert_true(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => 'Mod'), false, comments_widget_test_state())['comment_has_badge'], 'badge with its own label');
	pg_assert_true(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => ''), false, comments_widget_test_state(array('badge_label' => 'Pro')))['comment_has_badge'], 'badge with the site label');
	pg_assert_false(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => ''), false, comments_widget_test_state())['comment_has_badge'], 'badge without a label');
	pg_assert_false(pg_comments_widget_row_flags(array('user_badge' => '0', 'user_badge_label' => 'Mod'), false, comments_widget_test_state())['comment_has_badge'], 'no badge');

	pg_assert_true(pg_comments_widget_row_flags(array('verified_order_id' => '12'), false, comments_widget_test_state())['comment_is_verified'], 'verified purchase');
	pg_assert_false(pg_comments_widget_row_flags(array('verified_order_id' => '0'), false, comments_widget_test_state())['comment_is_verified'], 'not verified');
	pg_assert_false(pg_comments_widget_row_flags(array('seller_reply' => "  \n "), false, comments_widget_test_state())['comment_has_seller_reply'], 'a blank reply');
	pg_assert_true(pg_comments_widget_row_flags(array('seller_reply' => 'Thanks'), false, comments_widget_test_state())['comment_has_seller_reply'], 'a reply');
	pg_assert_true(pg_comments_widget_row_flags(array('file_name' => 'a.pdf'), false, comments_widget_test_state())['comment_has_file'], 'a file');
	pg_assert_false(pg_comments_widget_row_flags(array('published' => array('0'), 'file_name' => array('x')), false, comments_widget_test_state())['comment_is_pending'], 'columns that are not values read off');
	pg_assert_same(array_fill_keys($keys, false), pg_comments_widget_row_flags('x', false, 'y'), 'a row that is not an array');
}

function test_comments_widget_status_text()
{
	$own = array('comment_is_pending' => true, 'comment_is_own_pending' => true, 'comment_is_scheduled' => false);
	pg_assert_same('Your comment is awaiting approval', pg_comments_widget_status_text($own, false, 'comment', '', false), 'own pending');

	$scheduled = array('comment_is_pending' => true, 'comment_is_own_pending' => false, 'comment_is_scheduled' => true);
	pg_assert_same('Not Published (scheduled for in 2 hours, unless a new review is added first)', pg_comments_widget_status_text($scheduled, true, 'review', 'in 2 hours', true), 'staff, scheduled, cancel');
	pg_assert_same('Not Published (scheduled for in 2 hours)', pg_comments_widget_status_text($scheduled, true, 'review', 'in 2 hours', false), 'staff, scheduled');

	$pending = array('comment_is_pending' => true, 'comment_is_own_pending' => false, 'comment_is_scheduled' => false);
	pg_assert_same('Not Published', pg_comments_widget_status_text($pending, true, 'comment', '', false), 'staff, pending');
	pg_assert_same('', pg_comments_widget_status_text($pending, false, 'comment', '', false), 'a visitor reads nothing');
	pg_assert_same('', pg_comments_widget_status_text(array(), true, 'comment', '', false), 'published');
}

function test_comments_widget_stars_html()
{
	$three = pg_comments_stars_html(3);
	pg_assert_same(3, substr_count($three, 'class="bi bi-star-fill"'), 'three filled');
	pg_assert_same(2, substr_count($three, 'class="bi bi-star"'), 'two empty');
	pg_assert_same(5, substr_count($three, 'aria-hidden="true"'), 'each drawing is hidden from screen readers');
	pg_assert_contains('role="img" aria-label="Rated 3 out of 5"', $three, 'the rating is said once');
	pg_assert_same(0, strpos($three, '<span class="pg-comment-stars"'), 'one span around them');
	pg_assert_false(strpos($three, 'style'), 'no inline style');
	pg_assert_same(4, substr_count(pg_comments_stars_html('4'), 'bi-star-fill'), 'a digit string');

	foreach (array(0, 6, -1, 'x', array(3), null) as $bad) {
		pg_assert_same('', pg_comments_stars_html($bad), 'no stars for ' . var_export($bad, true));
	}
}

function test_comments_widget_row_root_id()
{
	pg_assert_same('<div id="c-7" class="card"><span id="x"></span></div>', pg_comments_row_root_id('<div id="card_pgw5r7" class="card"><span id="x"></span></div>', 7), 'replaces the suffixed id');
	pg_assert_same('<div id="c-7" class="card">x</div>', pg_comments_row_root_id('<div class="card">x</div>', 7), 'adds one');
	pg_assert_same("  <!--pg-vis:x:1-->\n<article id=\"c-7\" class=\"a\">y</article>", pg_comments_row_root_id("  <!--pg-vis:x:1-->\n<article class=\"a\">y</article>", '7'), 'skips leading whitespace and comments');
	pg_assert_same('<div id="c-7" data-id="3" title=\'a id="b"\'>z</div>', pg_comments_row_root_id('<div data-id="3" title=\'a id="b"\'>z</div>', 7), 'only the id attribute');
	pg_assert_same('<br id="c-7" />', pg_comments_row_root_id('<br />', 7), 'a void element');
	pg_assert_same('just text', pg_comments_row_root_id('just text', 7), 'text only');
	pg_assert_same('<div class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 0), 'no id');
	pg_assert_same('<div class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 'x'), 'not an id');
}

function test_comments_widget_escape_carets_once()
{
	$out = pg_comments_widget_escape(array(
		'__comment_name' => '<b>^^__x^^',
		'__comment_message_html' => '<p>^^__y^^ <?php echo 1; ?></p>',
		'__comment_rating' => 3,
	));
	pg_assert_same('&lt;b&gt;&#94;&#94;__x&#94;&#94;', $out['__comment_name'], 'plain text: escaped, carets as entities');
	pg_assert_same('<p>&#94;&#94;__y&#94;&#94; &lt;?php echo 1; ?></p>', $out['__comment_message_html'], 'raw: markup kept, PHP defused, carets as entities');
	pg_assert_same('3', $out['__comment_rating'], 'a number as text');
	pg_assert_same(array(), pg_comments_widget_escape('x'), 'not an array');

	$raw = pg_sw_raw_token_keys();
	foreach (array('__comment_message_html', '__comment_stars_html', '__comment_seller_reply_html') as $key) {
		pg_assert_true(isset($raw[$key]), $key . ' is raw');
	}
	pg_assert_false(isset($raw['__comment_name']), 'the name is not raw');
}

function test_comments_widget_nested_record_decision()
{
	$record = array(1 => 'form_item_view');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(), $record), 'a record widget on the page itself');
	pg_assert_true(pg_comments_nested_record_decision(array(1), array(1 => array(2)), array(1 => '', 2 => 'form_item_view')), 'one level down');
	pg_assert_true(pg_comments_nested_record_decision(array(1), array(1 => array(2), 2 => array(3)), array(1 => 'form_list_view', 2 => '', 3 => 'form_item_view')), 'two levels down');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(1 => array(2), 2 => array(1)), array(1 => '', 2 => '')), 'a loop ends');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(1 => array(5)), array(1 => '')), 'a child that was not read');
	pg_assert_false(pg_comments_nested_record_decision(array(1, 2), array(1 => array(2)), array(1 => '', 2 => 'form_item_view')), 'also placed on the page itself');
	pg_assert_false(pg_comments_nested_record_decision(array(), array(), array()), 'nothing placed');
	pg_assert_false(pg_comments_nested_record_decision('x', 'y', 'z'), 'not arrays');

	$children = array();
	$types = array();
	for ($i = 1; $i <= 10; $i++) {
		$children[$i] = array($i + 1);
		$types[$i] = '';
	}
	$deep = $types;
	$deep[10] = 'form_item_view';
	pg_assert_false(pg_comments_nested_record_decision(array(1), $children, $deep), 'nine levels down is past the cap');
	$eight = $types;
	$eight[9] = 'form_item_view';
	pg_assert_true(pg_comments_nested_record_decision(array(1), $children, $eight), 'eight levels down is read');
}

function test_comments_widget_name_mode_table()
{
	pg_assert_same('typed', pg_comments_widget_name_mode(false, '', false, false, true), 'signed out');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '0', false, false, true), 'administrator');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, 1, false, false, false), 'designer');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '2', false, false, false), 'manager');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '3', true, false, true), 'a user with access to the folder');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '3', false, true, true), 'the form editor');
	pg_assert_same('chosen', pg_comments_widget_name_mode(true, '3', false, false, true), 'a member who may choose');
	pg_assert_same('fixed', pg_comments_widget_name_mode(true, '3', false, false, false), 'a member who may not');
	pg_assert_same('fixed', pg_comments_widget_name_mode(true, '', false, false, false), 'no role');
}

// -- slice 11: the add-comment form ------------------------------------------

function comments_widget_test_form_state($over = array())
{
	return $over + array('files_on' => true, 'watcher_box' => true, 'name_mode' => 'typed', 'rating_on' => true, 'captcha_on' => true, 'can_publish' => true);
}

function comments_widget_test_bound($kind, $value, $tag = 'div')
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, '_bindings' => array($kind => $value)), 'children' => array());
}

function test_comments_widget_binding_disabled_table()
{
	$on = comments_widget_test_form_state();
	$off = comments_widget_test_form_state(array('files_on' => false, 'watcher_box' => false, 'name_mode' => 'fixed', 'rating_on' => false, 'captcha_on' => false, 'can_publish' => false));
	$rows = array(
		array('value', 'comment_file'),
		array('value', 'comment_watcher'),
		array('value', 'comment_name'),
		array('value', 'comment_name_type'),
		array('section', 'comment_rating_input'),
		array('section', 'comment_captcha'),
		array('section', 'comment_publish'),
	);
	foreach ($rows as $row) {
		$node = comments_widget_test_bound($row[0], $row[1]);
		$state = ($row[1] === 'comment_name_type') ? comments_widget_test_form_state(array('name_mode' => 'chosen')) : $on;
		pg_assert_false(_pg_comments_binding_disabled($node, $state), $row[1] . ' on');
		pg_assert_true(_pg_comments_binding_disabled($node, $off), $row[1] . ' off');
	}

	$chosen = comments_widget_test_form_state(array('name_mode' => 'chosen'));
	pg_assert_true(_pg_comments_binding_disabled(comments_widget_test_bound('value', 'comment_name'), $chosen), 'a typed name while choosing');
	pg_assert_true(_pg_comments_binding_disabled(comments_widget_test_bound('value', 'comment_name_type'), $on), 'the select while typing');

	foreach (array(array('value', 'comment_message'), array('section', 'captcha'), array('action', 'comment_submit'), array('action', 'comment_form'), array('eo_visible_if', 'files_on')) as $row) {
		pg_assert_false(_pg_comments_binding_disabled(comments_widget_test_bound($row[0], $row[1]), $off), $row[1] . ' is never switched off');
	}
	pg_assert_false(_pg_comments_binding_disabled(array('type' => 'semantic', 'props' => array('tag' => 'div')), $off), 'a node without bindings');
	pg_assert_false(_pg_comments_binding_disabled('x', $off), 'not a node');
}

function comments_widget_test_control($tag, $value, $extra = array())
{
	$props = $extra + array('tag' => $tag, 'cssClass' => 'form-control', '_bindings' => array('value' => $value));
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function comments_widget_test_label($for, $text)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => $text, '_attrs' => array(array('name' => 'for', 'value' => $for))), 'children' => array());
}

function comments_widget_test_div($children, $class = 'mb-3')
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => $class), 'children' => $children);
}

function comments_widget_test_form_tree()
{
	$form = array('type' => 'semantic', 'props' => array('tag' => 'form', 'cssClass' => 'card card-body', 'id' => 'mine',
		'_attrs' => array(array('name' => 'action', 'value' => '/elsewhere'), array('name' => 'novalidate', 'value' => '')),
		'_bindings' => array('action' => 'comment_form')), 'children' => array(
		comments_widget_test_div(array(comments_widget_test_label('comment_message', 'Comment'), comments_widget_test_control('textarea', 'comment_message', array('id' => 'comment_message')))),
		comments_widget_test_div(array(comments_widget_test_label('my_name', 'Name'), comments_widget_test_control('input', 'comment_name', array('id' => 'my_name')))),
		comments_widget_test_div(array(comments_widget_test_control('select', 'comment_name_type', array('id' => 'comment_name_type')))),
		comments_widget_test_div(array(comments_widget_test_label('comment_file', 'Attach a File'), comments_widget_test_control('input', 'comment_file', array('id' => 'comment_file')))),
		comments_widget_test_div(array(comments_widget_test_control('input', 'comment_watcher', array('cssClass' => 'form-check-input')), comments_widget_test_label('comment_watcher', 'Notify me')), 'form-check'),
		comments_widget_test_div(array(array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Add', '_bindings' => array('action' => 'comment_submit')), 'children' => array())), ''),
	));
	$second = array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_form')), 'children' => array(
		comments_widget_test_control('textarea', 'comment_message'),
	));

	return array('type' => 'root', 'props' => array(), 'children' => array(
		$form,
		$second,
		comments_widget_test_control('input', 'comment_message', array('id' => 'stray')),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Go', '_bindings' => array('action' => 'comment_submit')), 'children' => array()),
		comments_widget_test_control('div', 'comment_message'),
	));
}

function comments_widget_test_form_ctx($over = array())
{
	return $over + array(
		'can_comment' => true,
		'action_url' => '/software/add_comment.php',
		'hidden_html' => '<input type="hidden" name="page_id" value="5">',
		'multipart' => false,
		'label_slot' => 'pg_comment_form_label',
		'watcher_checked' => true,
		'state' => comments_widget_test_form_state(array('files_on' => false)),
	);
}

function test_comments_widget_form_bindings()
{
	$tree = comments_widget_test_form_tree();
	pg_assert_same(1, pg_comments_apply_form_bindings($tree, comments_widget_test_form_ctx()), 'one form wired');
	pg_assert_same('custom_html', $tree['children'][0]['children'][0]['props']['contentType'], 'the hidden fields come first');
	pg_assert_same('<input type="hidden" name="page_id" value="5">', $tree['children'][0]['children'][0]['props']['html'], 'as given');

	$html = _render_tree_node($tree, 0, 0);
	pg_assert_same(1, substr_count($html, '<form'), 'a second comment form is dropped');
	pg_assert_contains('<form id="software_add_comment" class="card card-body"', $html, 'the handler\'s anchor as its id');
	pg_assert_contains('action="/software/add_comment.php" method="post"', $html, 'posts to add_comment.php');
	pg_assert_contains('data-pg-comment-form="" data-pg-comment-label="^^pg_comment_form_label^^"', $html, 'the frontend hooks');
	pg_assert_false(strpos($html, 'enctype'), 'no multipart without attachments');
	pg_assert_false(strpos($html, 'novalidate'), 'the browser checks the required message');
	pg_assert_false(strpos($html, '/elsewhere'), 'the designer\'s action is replaced');
	pg_assert_false(strpos($html, 'id="mine"'), 'the designer\'s form id is replaced');

	pg_assert_same(1, substr_count($html, 'name="message"'), 'one message box');
	pg_assert_true((bool) preg_match('#<textarea id="comment_message"[^>]*name="message"[^>]*required=""[^>]*>\^\^pg_comment_form_message\^\^</textarea>#', $html), 'the message box: name, required, its text slot');
	pg_assert_true((bool) preg_match('#<input id="my_name"[^>]*name="name"[^>]*type="text"[^>]*maxlength="50"[^>]*value="\^\^pg_comment_form_name\^\^"#', $html), 'the name box keeps the designer\'s id');
	pg_assert_false(strpos($html, 'name_type'), 'no name select while the name is typed');
	pg_assert_false(strpos($html, 'comment_file'), 'the attachment box is dropped');
	pg_assert_false(strpos($html, 'Attach a File'), 'with its label');
	pg_assert_true((bool) preg_match('#<input id="comment_watcher"[^>]*name="watcher"[^>]*type="checkbox"[^>]*value="1"[^>]*checked=""#', $html), 'the watcher box, ticked, with the default id');
	pg_assert_true((bool) preg_match('#<button[^>]*type="submit"[^>]*data-pg-comment-submit=""#', $html), 'the submit button');
	pg_assert_same(1, substr_count($html, 'data-pg-comment-submit'), 'a submit outside the form is dropped');
	pg_assert_false(strpos($html, 'stray'), 'a message box outside the form is dropped');
	pg_assert_false(strpos($html, '<div class="form-control"'), 'a value binding on an element that is no control is dropped');
	pg_assert_same(3, substr_count($html, '<div class="mb-3">') + substr_count($html, '<div class="form-check">'), 'the emptied wrappers went with their controls');

	$multi = comments_widget_test_form_tree();
	pg_comments_apply_form_bindings($multi, comments_widget_test_form_ctx(array('multipart' => true, 'watcher_checked' => false, 'state' => comments_widget_test_form_state(array('name_mode' => 'chosen')))));
	$multi_html = _render_tree_node($multi, 0, 0);
	pg_assert_true((bool) preg_match('#<select id="comment_name_type"[^>]*name="name_type"[^>]*>\s*\^\^pg_comment_form_name_options\^\^\s*</select>#', $multi_html), 'the name select gets its options slot');
	pg_assert_false(strpos($multi_html, 'my_name'), 'no typed name while it is chosen');
	pg_assert_contains('enctype="multipart/form-data"', $multi_html, 'multipart with attachments');
	pg_assert_true((bool) preg_match('#<input id="comment_file"[^>]*name="file"[^>]*type="file"#', $multi_html), 'the attachment box');
	pg_assert_false((bool) preg_match('#name="watcher"[^>]*checked#', $multi_html), 'the watcher box unticked');

	$closed = comments_widget_test_form_tree();
	pg_assert_same(0, pg_comments_apply_form_bindings($closed, comments_widget_test_form_ctx(array('can_comment' => false))), 'nothing wired');
	$closed_html = _render_tree_node($closed, 0, 0);
	pg_assert_false(strpos($closed_html, '<form'), 'no form when the visitor cannot comment');
	pg_assert_false(strpos($closed_html, 'name="message"'), 'and no stray control');
}

function test_comments_widget_hidden_html()
{
	$html = pg_comments_form_hidden_html('<input type="hidden" name="token" value="t">', '/p?x=^^a^^&y="1"', '7', 'submitted_form', '12', array(3));
	$order = array('name="token"', 'name="send_to"', 'name="page_id"', 'name="item_id"', 'name="item_type"', 'name="parent_id"');
	$previous = -1;
	foreach ($order as $needle) {
		$at = strpos($html, $needle);
		pg_assert_true(($at !== false) && ($at > $previous), 'in order: ' . $needle);
		$previous = ($at === false) ? $previous : $at;
	}
	pg_assert_contains('name="send_to" value="/p?x=&#94;&#94;a&#94;&#94;&amp;y=&quot;1&quot;"', $html, 'the address escaped, carets as entities');
	pg_assert_contains('name="page_id" value="7"', $html, 'the page');
	pg_assert_contains('name="item_id" value="12"', $html, 'the item');
	pg_assert_contains('name="item_type" value="submitted_form"', $html, 'the item type');
	pg_assert_contains('name="parent_id" value="0"', $html, 'an id that is not a value is 0');
	pg_assert_contains('name="page_id" value="0"', pg_comments_form_hidden_html('', '', true, '', '', 0), 'a bool is no page');
}

function test_comments_widget_rating_input_html()
{
	$default = pg_comments_rating_input_html(null);
	pg_assert_same(5, substr_count($default, 'type="radio" class="btn-check" name="rating"'), 'five radios named rating');
	for ($i = 1; $i <= 5; $i++) {
		pg_assert_contains('id="comment_rating_' . $i . '" value="' . $i . '"', $default, 'radio ' . $i);
		pg_assert_contains('for="comment_rating_' . $i . '"', $default, 'label ' . $i);
	}
	pg_assert_same(1, substr_count($default, ' checked'), 'one checked');
	pg_assert_contains('value="5" autocomplete="off" checked', $default, 'five by default');
	pg_assert_contains('role="group" aria-label="Rating"', $default, 'a labelled group');
	pg_assert_contains('<span class="visually-hidden">3 out of 5</span>', $default, 'each number said in words');
	pg_assert_false(strpos($default, 'style'), 'no style');

	pg_assert_contains('value="3" autocomplete="off" checked', pg_comments_rating_input_html(3), 'three');
	pg_assert_contains('value="2" autocomplete="off" checked', pg_comments_rating_input_html('2'), 'a digit string');
	pg_assert_contains('value="5" autocomplete="off" checked', pg_comments_rating_input_html(9), 'out of range');
	pg_assert_contains('value="5" autocomplete="off" checked', pg_comments_rating_input_html('x'), 'not a number');
}

function test_comments_widget_name_options_html()
{
	$choices = array('username' => 'Jane Doe', 'first_name' => 'Jane', 'first_name_last_initial' => 'Jane D.', 'full_name' => 'Jane Doe', 'anonymous' => 'Anonymous');
	$html = pg_comments_name_options_html($choices, 'first_name', 'Anonim');
	pg_assert_same(1, substr_count($html, '>Jane Doe</option>'), 'equal labels collapse');
	pg_assert_same(0, strpos($html, '<option value="full_name">Jane Doe</option>'), 'the later value, the first place');
	pg_assert_contains('<option value="first_name" selected>Jane</option>', $html, 'the chosen one');
	pg_assert_contains('<option value="anonymous">Anonim</option>', $html, 'anonymous in the given words');
	pg_assert_same(4, substr_count($html, '<option'), 'four options');

	$odd = pg_comments_name_options_html(array('username' => 'A^^b^^ <i>'), '', 'Anonymous');
	pg_assert_same('<option value="username">A&#94;&#94;b&#94;&#94; &lt;i&gt;</option>', $odd, 'escaped, carets as entities');
	pg_assert_same('', pg_comments_name_options_html('x', '', 'Anonymous'), 'no choices');
}

function test_comments_widget_publish_html()
{
	$html = pg_comments_publish_html('schedule', '10/24/2026 3:00 PM', true, 'comment', '<script>init()</script>');
	pg_assert_same(0, strpos($html, '<div class="pg-comment-publish" data-pg-comment-publish>'), 'the wrapper hook');
	pg_assert_contains('id="comment_publish" name="publish" data-pg-comment-publish-select>', $html, 'the select hook');
	pg_assert_contains('<option value="schedule" selected>At a Scheduled Time</option>', $html, 'the chosen option');
	pg_assert_contains('<option value="now">Now</option>', $html, 'now');
	pg_assert_contains('<option value="manual">Maybe Later</option>', $html, 'later');
	pg_assert_contains('<span class="ms-2" data-pg-comment-publish-schedule>', $html, 'the schedule shows');
	pg_assert_contains('name="publish_date_and_time" maxlength="19" size="21" value="10/24/2026 3:00 PM"', $html, 'the date');
	pg_assert_true((bool) preg_match('#name="publish_cancel" value="1" checked#', $html), 'cancel ticked');
	pg_assert_contains('Cancel if a new comment is added first.', $html, 'its label');
	pg_assert_same(strlen($html) - strlen('<script>init()</script></div>'), strpos($html, '<script>init()</script></div>'), 'the scripts last');
	pg_assert_false(strpos($html, 'style='), 'no inline style');

	$now = pg_comments_publish_html('now', '"><b>^^x^^', false, 'comment', '');
	pg_assert_contains('data-pg-comment-publish-schedule hidden>', $now, 'the schedule hidden');
	pg_assert_contains('value="&quot;&gt;&lt;b&gt;&#94;&#94;x&#94;&#94;"', $now, 'the date escaped');
	pg_assert_false((bool) preg_match('#name="publish_cancel" value="1" checked#', $now), 'cancel unticked');
	pg_assert_contains('<option value="now" selected>', $now, 'now chosen');
}

function test_comments_widget_watcher_box()
{
	pg_assert_true(pg_comments_watcher_box(true, true, false, false), 'watching on, signed in, not watching, not notified');
	pg_assert_false(pg_comments_watcher_box(false, true, false, false), 'watching off');
	pg_assert_false(pg_comments_watcher_box(true, false, false, false), 'signed out');
	pg_assert_false(pg_comments_watcher_box(true, true, true, false), 'already watching');
	pg_assert_false(pg_comments_watcher_box(true, true, false, true), 'notified another way');
}

// -- the default layout in three places ----------------------------------------

// The starter's case block in style_designer.js.
function comments_widget_test_starter_block()
{
	$js = comments_widget_test_source('assets/js/style_designer.js');
	$start = strpos($js, "case 'comments': {");
	$end = ($start === false) ? false : strpos($js, 'default: {', $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start);
}

// Each binding object of the starter, as sorted key:value pairs, and
// 'messages' for the Messages node, in source order.
function comments_widget_test_js_bindings($block)
{
	preg_match_all("/_bindings:\\s*\\{([^}]*)\\}|contentType:\\s*'messages'/", $block, $hits, PREG_SET_ORDER);
	$out = array();

	foreach ($hits as $hit) {
		if (strpos($hit[0], '_bindings') !== 0) {
			$out[] = 'messages';
			continue;
		}

		preg_match_all("/(\\w+):\\s*'([^']*)'/", $hit[1], $pairs, PREG_SET_ORDER);
		$list = array();
		foreach ($pairs as $pair) {
			$list[] = $pair[1] . ':' . $pair[2];
		}
		sort($list);
		$out[] = implode(',', $list);
	}

	return $out;
}

// The same, walking the PHP tree depth first; with $key, that prop instead.
function comments_widget_test_php_walk($node, &$out, $key = '')
{
	if (!is_array($node)) {
		return;
	}

	if ($key !== '') {
		if (isset($node['props'][$key])) {
			$out[] = (string) $node['props'][$key];
		}
	} else {
		if (isset($node['props']['contentType']) && ($node['props']['contentType'] === 'messages')) {
			$out[] = 'messages';
		}

		if (!empty($node['props']['_bindings'])) {
			$list = array();
			foreach ($node['props']['_bindings'] as $name => $value) {
				$list[] = $name . ':' . $value;
			}
			sort($list);
			$out[] = implode(',', $list);
		}
	}

	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		comments_widget_test_php_walk($child, $out, $key);
	}
}

function test_comments_widget_default_tree_matches_starter()
{
	$block = comments_widget_test_starter_block();
	pg_assert_true($block !== '', 'the starter is found');

	$js = comments_widget_test_js_bindings($block);
	$php = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php);
	pg_assert_true(count($js) > 30, 'the starter binds its elements: ' . count($js));
	pg_assert_same($js, $php, 'the same bindings in the same order');

	$form = array_search('action:comment_form,eo_visible_if:can_comment', $php, true);
	pg_assert_true(($form !== false) && isset($php[$form + 1]) && ($php[$form + 1] === 'messages'), 'the Messages node comes first in the form');
	pg_assert_same(1, count(array_keys($php, 'messages', true)), 'one Messages node');

	preg_match_all("/cssClass:\\s*'([^']*)'/", $block, $classes);
	$php_classes = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php_classes, 'cssClass');
	pg_assert_same($classes[1], $php_classes, 'the same classes in the same order');

	preg_match_all("/\\btag:\\s*'([^']*)'/", $block, $tags);
	$php_tags = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php_tags, 'tag');
	pg_assert_same($tags[1], $php_tags, 'the same elements in the same order');

	$split = _split_widget_tree(pg_comments_default_tree());
	pg_assert_true(is_array($split['loop_children']) && (count($split['loop_children']) === 1), 'one comment card repeats');
	pg_assert_false(strpos($block, 'createNode(\'loop_area\', {}, []'), 'the starter\'s loop_area is not empty');
}

// -- the draw ------------------------------------------------------------------

function comments_widget_test_draw_data($over = array())
{
	$facts = (isset($over['facts']) ? $over['facts'] : array()) + array('count' => 1);
	$thread = pg_comments_widget_thread_flags(comments_widget_test_facts($facts));
	$row = array('id' => '7', 'published' => '1', 'publish_date_and_time' => '0000-00-00 00:00:00');
	$flags = pg_comments_widget_row_flags($row, false, comments_widget_test_state(array('rating_on' => false)));

	return (isset($over['data']) ? $over['data'] : array()) + array(
		'widget_id' => 5,
		'thread_flags' => $thread,
		'thread_values' => array(
			'__comments_title' => '1 Comment',
			'__comments_count' => '1',
			'__comments_label' => 'Comment',
			'__comments_label_plural' => 'Comments',
			'__comments_intro' => '',
			'__comments_closed_message' => 'No longer open',
			'__comments_show_all_url' => '',
			'__comments_show_featured_url' => '',
			'__login_url' => '',
			'__comment_author_name' => '',
		),
		'rows' => array(array(
			'id' => 7,
			'flags' => $flags,
			'values' => array(
				'__comment_id' => '7',
				'__comment_anchor' => 'c-7',
				'__comment_name' => '^^__comments_count^^ <b>',
				'__comment_staff_name' => '',
				'__comment_message_html' => 'Hi ^^__login_url^^',
				'__comment_date' => '',
				'__comment_edit_url' => '',
			),
			'datetime' => '',
		)),
		'form' => comments_widget_test_form_ctx(array('state' => comments_widget_test_form_state(array('files_on' => false, 'watcher_box' => false, 'rating_on' => false, 'captcha_on' => false, 'can_publish' => false)))),
		'sections' => array('comment_captcha' => '', 'comment_rating_input' => '', 'comment_publish' => ''),
		'slots' => array('pg_comment_form_message' => 'Typed &#94;&#94;x&#94;&#94;', 'pg_comment_form_name' => '', 'pg_comment_form_name_options' => '', 'pg_comment_form_label' => 'comment'),
		'edit_chips' => false,
	);
}

function comments_widget_test_stamped_tree()
{
	$tree = pg_comments_default_tree();
	_pg_inject_messages_node($tree, 'add_comment');
	return $tree;
}

function test_comments_widget_draw_end_to_end()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), comments_widget_test_draw_data());
	pg_assert_same(0, strpos($html, '<a id="software_comments"></a>'), 'the #software_comments anchor first');
	pg_assert_same(1, substr_count($html, 'id="c-7"'), 'the comment\'s anchor once');
	pg_assert_contains('&#94;&#94;__comments_count&#94;&#94; &lt;b&gt;', $html, 'the name escaped once, carets as entities');
	pg_assert_contains('Hi &#94;&#94;__login_url&#94;&#94;', $html, 'a token typed into the text stays text');
	pg_assert_false(strpos($html, '<b>'), 'no markup from the visitor');
	pg_assert_false(strpos($html, '^^'), 'no token left');
	pg_assert_contains('<h2 class="h5 mb-0"', $html, 'the heading');
	pg_assert_contains('>1 Comment</h2>', $html, 'with the title');
	pg_assert_false(strpos($html, 'class="small text-body-secondary"'), 'no staff name for a visitor');
	pg_assert_false(strpos($html, 'No comments yet.'), 'no empty note with a comment');
	pg_assert_false(strpos($html, 'No longer open'), 'no closed note while open');
	pg_assert_contains('<form id="software_add_comment"', $html, 'the form');
	pg_assert_true((bool) preg_match('#<textarea id="comment_message"[^>]*name="message"[^>]*required=""[^>]*>Typed &\\#94;&\\#94;x&\\#94;&\\#94;</textarea>#', $html), 'the message box keeps the typed text');
	pg_assert_contains('data-pg-comment-label="comment"', $html, 'the label for the leave warning');
	pg_assert_contains('name="page_id" value="5"', $html, 'the hidden fields');
	pg_assert_false(strpos($html, 'rel="nofollow"'), 'no sign-in link when the visitor may comment');
	pg_assert_false(strpos($html, 'name="watcher"'), 'no watch box when it is off');
	pg_assert_false(strpos($html, 'Featured Links'), 'customName is not printed');
	pg_assert_false(strpos($html, 'pg-comments-list'), 'the designed loop is used');

	$closed = pg_comments_widget_draw(comments_widget_test_stamped_tree(), comments_widget_test_draw_data(array(
		'facts' => array('allow_new' => false),
		'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
	)));
	pg_assert_false(strpos($closed, '<form'), 'no form when closed');
	pg_assert_contains('No longer open', $closed, 'the closed note');
	pg_assert_false(strpos($closed, 'Add a comment</h3>'), 'no form heading when closed');

	$login_data = comments_widget_test_draw_data(array(
		'facts' => array('require_login' => true),
		'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
	));
	$login_data['thread_values']['__login_url'] = '/sign-in?send_to=%2Fp%23software_add_comment';
	$login = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $login_data);
	pg_assert_true((bool) preg_match('#<a href="/sign-in\\?send_to=%2Fp%23software_add_comment" class="fw-semibold" rel="nofollow"#', $login), 'the sign-in link, nofollow');
	pg_assert_false(strpos($login, '<form'), 'no form until signed in');

	// A layout without a loop_area draws its comments in the default card.
	$bare = comments_widget_test_stamped_tree();
	$bare['children'][0]['children'][1]['children'] = array();
	$fallback = pg_comments_widget_draw($bare, comments_widget_test_draw_data());
	pg_assert_contains('<div class="pg-comments-list">', $fallback, 'the fallback list');
	pg_assert_same(1, substr_count($fallback, 'id="c-7"'), 'with the comment');
	pg_assert_true(strpos($fallback, 'pg-comments-list') < strpos($fallback, 'software_add_comment'), 'before the rest of the layout');

	$empty_loop = comments_widget_test_stamped_tree();
	$empty_loop['children'][0]['children'][1]['children'] = array(array('type' => 'loop_area', 'props' => array(), 'children' => array()));
	$slot = pg_comments_widget_draw($empty_loop, comments_widget_test_draw_data());
	pg_assert_true((bool) preg_match('#<div>\\s*<div class="pg-comments-list">#', $slot), 'an empty loop_area takes the fallback in its place');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The loop_area of a layout that shows a comment's date in a format of the
// design's, and its name.
function comments_widget_test_dated_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'article', 'id' => 'card'), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'span', '_bindings' => array('text' => '__comment_datetime'), '_bindFormats' => array('text' => 'Y')), 'children' => array()),
				array('type' => 'semantic', 'props' => array('tag' => 'span', '_bindings' => array('text' => '__comment_name')), 'children' => array()),
			)),
		)),
	));
}

// Staff get the edit pencil on each comment; the anchor stays the comment's
// own.
function test_comments_widget_draw_edit_chip()
{
	$data = comments_widget_test_draw_data();
	$data['rows'][0]['values']['__comment_edit_url'] = '/software/edit_comment.php?id=7&send_to=%2Fp';
	$data['edit_chips'] = true;

	$html = pg_comments_widget_draw(comments_widget_test_dated_tree(), $data);
	pg_assert_true((bool) preg_match('#<article id="c-7" class="pg-edit-host"><a class="pg-edit-chip#', $html), 'the pencil in the comment, the anchor on it');
	pg_assert_contains('href="/software/edit_comment.php?id=7&amp;send_to=%2Fp"', $html, 'to the comment\'s edit screen');
	pg_assert_same(1, substr_count($html, 'id="c-7"'), 'one anchor');

	$data['edit_chips'] = false;
	pg_assert_false(strpos(pg_comments_widget_draw(comments_widget_test_dated_tree(), $data), 'pg-edit-chip'), 'no pencil for anyone else');
}

// The date takes the design's format before the visitor's text goes in, so a
// token typed into a name is never read as the date. Drawn in a PHP process
// of its own: formatting a date reads USER_LOGGED_IN and USER_TIMEZONE, which
// the tests after this one must not find defined.
function test_comments_widget_draw_datetime()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$data = comments_widget_test_draw_data();
	$data['rows'][0]['datetime'] = '2026-10-24 10:00:00';
	$data['rows'][0]['values']['__comment_name'] = '^^__comment_datetime^^';
	$file = @tempnam(sys_get_temp_dir(), 'pgcomments');

	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('USER_LOGGED_IN', false);\n"
		. "define('USER_TIMEZONE', '');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'echo pg_comments_widget_draw(' . var_export(comments_widget_test_dated_tree(), true) . ', ' . var_export($data, true) . ");\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$html = implode("\n", $out);

	pg_assert_same(0, $code, 'drawn: ' . $html);
	pg_assert_contains('>2026</span>', $html, 'the date in the design\'s format');
	pg_assert_contains('>&#94;&#94;__comment_datetime&#94;&#94;</span>', $html, 'a typed token is not a date');
}

// -- wiring --------------------------------------------------------------------

function test_comments_widget_module_wiring()
{
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments.php');", comments_widget_test_source('functions.php'), 'loaded with the other widgets');

	$designer = comments_widget_test_source('includes/fn/designer.php');
	pg_assert_contains("if (\$region_type === 'comments') {", $designer, 'dispatched');
	pg_assert_contains('return _render_system_widget_comments($tree_json, $sid, $cfg, $mode);', $designer, 'to its renderer');

	$module = comments_widget_test_source('includes/fn/widgets_comments.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'add_comment');", $module, 'its Messages node shows the add_comment errors');
	pg_assert_same('Comments', pg_sw_type_labels()['comments'], 'named for the panel');

	foreach (array('frontend.src.js', 'frontend.min.js') as $file) {
		$js = comments_widget_test_source($file);
		foreach (array('init_comment_forms', 'data-pg-comment-form', 'data-pg-comment-submit', 'data-pg-comment-publish-select', 'data-pg-comment-publish-schedule', 'data-pg-comment-label') as $needle) {
			pg_assert_contains($needle, $js, $file . ': ' . $needle);
		}
		pg_assert_contains('init_add_comment_publish', $js, $file . ': the classic form keeps its own');
	}
}
