<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recent comments mode of the comments widget
 * (includes/fn/widgets_comments_recent.php): its options, which source pages
 * add comments and why one adds none, the record and audience gates written
 * into SQL, the statements and what a closed gate sends, the merge of the
 * sources, the flags and the link tokens, what each mode switches off, the
 * draw with every form and control off, the default layout against the
 * editor's starter, and the wiring (the early branch of the thread renderer,
 * no claim, no form state).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_recent_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function comments_recent_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// Whether the functions a test calls exist; a missing one fails the test
// once instead of stopping the run.
function comments_recent_test_has($names)
{
	$ok = true;

	foreach ((array) $names as $name) {
		$ok = pg_assert_true(function_exists($name), $name . '() exists') && $ok;
	}

	return $ok;
}

// The source of a PHP function, '' when it does not exist.
function comments_recent_test_php_body($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());

	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

// Runs functions of style_designer.js and a script under Node. Returns the
// decoded JSON the script prints, or null when Node is missing (the test is
// then skipped) or a function is missing (counted as a failure).
function comments_recent_test_node($names, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = comments_recent_test_source('assets/js/style_designer.js');
	$code_js = '';
	$found = true;

	foreach ($names as $name) {
		$start = strpos($js, "\n    function " . $name . '(');
		$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);
		$body = (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
		$found = pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()') && $found;
		$code_js .= $body . "\n";
	}

	if (!$found) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgcrt');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($res), 'the script answers');

	return is_array($res) ? $res : null;
}

// -- the kind of a widget --------------------------------------------------------

// A comments widget in the recent mode is the kind 'comments_recent'; the
// stored regionType stays 'comments'. The editor's twin answers the same.
function test_comments_recent_widget_kind_twin()
{
	if (!comments_recent_test_has('pg_sw_widget_kind')) {
		return;
	}

	$sets = array(
		'thread' => array('regionType' => 'comments'),
		'thread mode' => array('regionType' => 'comments', 'mode' => 'thread'),
		'recent' => array('regionType' => 'comments', 'mode' => 'recent'),
		'junk mode' => array('regionType' => 'comments', 'mode' => 'x'),
		'mode as a number' => array('regionType' => 'comments', 'mode' => 1),
		'Recent' => array('regionType' => 'comments', 'mode' => 'Recent'),
		'record view' => array('regionType' => 'form_item_view'),
		'record view, recent mode' => array('regionType' => 'form_item_view', 'mode' => 'recent'),
		'blank' => array('regionType' => ''),
		'no kind' => array('mode' => 'recent'),
		'kind not text' => array('regionType' => 5),
		'null' => null,
		'text' => 'comments',
		'number' => 5,
	);

	pg_assert_same('comments', pg_sw_widget_kind($sets['thread']), 'a thread');
	pg_assert_same('comments_recent', pg_sw_widget_kind($sets['recent']), 'the recent mode');
	pg_assert_same('comments', pg_sw_widget_kind($sets['junk mode']), 'an unknown mode reads as the thread');
	pg_assert_same('form_item_view', pg_sw_widget_kind($sets['record view, recent mode']), 'the mode belongs to comments only');
	pg_assert_same('', pg_sw_widget_kind($sets['no kind']), 'no kind');
	pg_assert_same('', pg_sw_widget_kind(null), 'not a config');

	$res = comments_recent_test_node(array('_swKindOf'),
		'var sets = ' . json_encode($sets) . ";\nvar out = {};\nObject.keys(sets).forEach(function (k) { out[k] = _swKindOf(sets[k]); });\nprocess.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	foreach ($sets as $name => $cfg) {
		pg_assert_same(pg_sw_widget_kind($cfg), isset($res[$name]) ? $res[$name] : null, 'twin: ' . $name);
	}
}

// -- the options ---------------------------------------------------------------

function test_comments_recent_options()
{
	if (!comments_recent_test_has('pg_comments_recent_options')) {
		return;
	}

	pg_assert_same(array('show' => 'published', 'page_ids' => array(), 'limit' => 5, 'order' => 'newest', 'filters' => array()), pg_comments_recent_options(array()), 'defaults');
	pg_assert_same(pg_comments_recent_options(array()), pg_comments_recent_options('x'), 'not a config');

	foreach (array('published', 'mine', 'pending') as $show) {
		pg_assert_same($show, pg_comments_recent_options(array('recent_show' => $show))['show'], 'shows ' . $show);
	}
	pg_assert_same('published', pg_comments_recent_options(array('recent_show' => 'all'))['show'], 'an unknown scope reads published');
	pg_assert_same('published', pg_comments_recent_options(array('recent_show' => array('mine')))['show'], 'not text');
	pg_assert_same('oldest', pg_comments_recent_options(array('recent_order' => 'oldest'))['order'], 'oldest first');
	pg_assert_same('newest', pg_comments_recent_options(array('recent_order' => 'OLDEST'))['order'], 'an unknown order reads newest');

	foreach (array(5, 10, 20, 50) as $n) {
		pg_assert_same($n, pg_comments_recent_options(array('recent_limit' => (string) $n))['limit'], 'limit ' . $n . ' as text');
		pg_assert_same($n, pg_comments_recent_options(array('recent_limit' => $n))['limit'], 'limit ' . $n);
	}
	foreach (array('7', 0, '0', 100, '-5', '5.0', true, array(5), '') as $bad) {
		pg_assert_same(5, pg_comments_recent_options(array('recent_limit' => $bad))['limit'], 'a limit outside the list reads 5: ' . json_encode($bad));
	}

	$ids = pg_comments_recent_options(array('recent_page_ids' => array('12', 12, 7, 'tab:topic', 0, -3, '1.5', ' 8', array(9), true, null, 3.0, '40', 41, 42, 43, 44)))['page_ids'];
	pg_assert_same(array(12, 7, 40, 41, 42), $ids, 'whole numbers above 0, each once, the first five');
	pg_assert_same(array(), pg_comments_recent_options(array('recent_page_ids' => '12'))['page_ids'], 'not a list');
	pg_assert_same(array(), pg_comments_recent_options(array('recent_page_ids' => array('{{tab:topic}}')))['page_ids'], 'a template reference left unresolved');

	// The list's record filters are its own key: a form list's 'filters'
	// carried over by Change type is never read, so none applies unseen.
	$board = array('field' => 'board', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'board');
	pg_assert_same(array(), pg_comments_recent_options(array('filters' => array($board)))['filters'], 'another kind\'s filters are not read');
	pg_assert_same(array($board), pg_comments_recent_options(array('recent_filters' => array($board)))['filters'], 'the list\'s own filters');
	pg_assert_same(array($board, array('field' => '')), pg_comments_recent_options(array('recent_filters' => array(3 => $board, 'x', null, 7 => array('field' => ''))))['filters'], 'rows only, in order, numbered again');
	pg_assert_same(array(), pg_comments_recent_options(array('recent_filters' => 'board'))['filters'], 'not a list');
}

// -- which pages add comments ----------------------------------------------------

function comments_recent_test_facts($over = array())
{
	return $over + array(
		'exists' => true,
		'binned' => false,
		'archived' => false,
		'staff' => false,
		'view' => true,
		'comments_on' => true,
		'page_type' => 'standard',
		'record' => true,
		'form_id' => 31,
		'nested' => false,
		'viewer_filter' => false,
		'personal' => false,
		'show' => 'published',
		'signed_in' => false,
		'sign_in_page' => false,
		'list_filters' => false,
		'sources' => 1,
	);
}

function test_comments_recent_source_decision_table()
{
	if (!comments_recent_test_has('pg_comments_recent_source_decision')) {
		return;
	}

	$refused = array(
		'missing' => array('exists' => false),
		'binned' => array('binned' => true),
		'archived for a visitor' => array('archived' => true),
		'a folder the visitor may not open' => array('view' => false),
		'comments off' => array('comments_on' => false),
		'a form item view page' => array('page_type' => 'form item view'),
		'catalog detail without a record widget' => array('page_type' => 'catalog detail', 'record' => false),
		'calendar event view without a record widget' => array('page_type' => 'calendar event view', 'record' => false),
		'a nested record widget on a page source' => array('record' => false, 'nested' => true),
		'a record view without a form' => array('form_id' => 0),
		'a viewer filter for a visitor' => array('viewer_filter' => true),
		'a viewer filter in a background render' => array('viewer_filter' => true, 'signed_in' => false, 'personal' => false),
		'pending without edit rights' => array('show' => 'pending'),
		'mine signed out' => array('show' => 'mine'),
		'a page that sends a visitor to sign in' => array('record' => false, 'sign_in_page' => true),
		'a page that sends a visitor to sign in, drawn in the background' => array('record' => false, 'sign_in_page' => true, 'signed_in' => false, 'personal' => false),
		'a page that sends a visitor to sign in, for staff' => array('record' => false, 'sign_in_page' => true, 'staff' => true, 'show' => 'pending'),
		'record filters on a page source' => array('record' => false, 'list_filters' => true),
		'record filters over two pages' => array('list_filters' => true, 'sources' => 2),
		'record filters with no page counted' => array('list_filters' => true, 'sources' => 0),
	);
	$reasons = array(
		'missing' => 'missing',
		'binned' => 'binned',
		'archived for a visitor' => 'archived',
		'a folder the visitor may not open' => 'closed',
		'comments off' => 'comments_off',
		'a form item view page' => 'classic',
		'catalog detail without a record widget' => 'classic',
		'calendar event view without a record widget' => 'classic',
		'a nested record widget on a page source' => 'nested',
		'a record view without a form' => 'no_form',
		'a viewer filter for a visitor' => 'viewer_filter',
		'a viewer filter in a background render' => 'viewer_filter',
		'pending without edit rights' => 'not_staff',
		'mine signed out' => 'signed_out',
		'a page that sends a visitor to sign in' => 'sign_in',
		'a page that sends a visitor to sign in, drawn in the background' => 'sign_in',
		'a page that sends a visitor to sign in, for staff' => 'sign_in',
		'record filters on a page source' => 'filters',
		'record filters over two pages' => 'filters',
		'record filters with no page counted' => 'filters',
	);

	foreach ($refused as $name => $over) {
		$decision = pg_comments_recent_source_decision(comments_recent_test_facts($over));
		pg_assert_false($decision['use'], $name . ': adds nothing');
		pg_assert_same($reasons[$name], $decision['reason'], $name . ': the reason');
	}

	$used = array(
		'archived for an editor' => array(array('archived' => true, 'staff' => true), 'record'),
		'catalog detail with a top-level record widget' => array(array('page_type' => 'catalog detail'), 'record'),
		'a standard page' => array(array('record' => false), 'page'),
		'a page source with a nested record view is fine when it is a record source' => array(array('nested' => true), 'record'),
		'a viewer filter for a signed-in member' => array(array('viewer_filter' => true, 'personal' => true, 'signed_in' => true), 'record'),
		'pending for staff' => array(array('show' => 'pending', 'staff' => true), 'record'),
		'mine signed in' => array(array('show' => 'mine', 'signed_in' => true), 'record'),
		'a page source with a form id left over' => array(array('record' => false, 'form_id' => 0), 'page'),
		'a page that asks a visitor to sign in, for a signed-in member' => array(array('record' => false, 'sign_in_page' => true, 'signed_in' => true, 'personal' => true), 'page'),
		'record filters on the one record source' => array(array('list_filters' => true), 'record'),
		'two pages without record filters' => array(array('sources' => 2), 'record'),
		'a page source over two pages without record filters' => array(array('record' => false, 'sources' => 2), 'page'),
	);

	foreach ($used as $name => $case) {
		$decision = pg_comments_recent_source_decision(comments_recent_test_facts($case[0]));
		pg_assert_true($decision['use'], $name . ': used');
		pg_assert_same($case[1], $decision['kind'], $name . ': kind');
		pg_assert_same('', $decision['reason'], $name . ': no reason');
	}

	pg_assert_false(pg_comments_recent_source_decision(null)['use'], 'no facts: nothing');
	pg_assert_same('missing', pg_comments_recent_source_decision('x')['reason'], 'not facts: missing');
}

// A record view's filters that compare a field with the viewer read '' for a
// visitor who is not signed in, so they would match every record sent while
// signed out: the search index and the recent list both refuse such a view.
function test_comments_recent_viewer_filters()
{
	if (!comments_recent_test_has('pg_sw_filters_compare_viewer')) {
		return;
	}

	pg_assert_false(pg_sw_filters_compare_viewer(array()), 'no filter');
	pg_assert_false(pg_sw_filters_compare_viewer('x'), 'not a list');
	pg_assert_true(pg_sw_filters_compare_viewer(array(array('field' => 'submitter', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'viewer'))), 'the submitter is the viewer');
	pg_assert_true(pg_sw_filters_compare_viewer(array(array('field' => 'email', 'value' => '', 'dynamic_value' => 'viewers email address'))), 'the viewer\'s e-mail address');
	pg_assert_false(pg_sw_filters_compare_viewer(array(array('field' => 'submitter', 'value' => 'jane', 'dynamic_value' => 'viewer'))), 'a fixed value wins over the dynamic one');
	pg_assert_false(pg_sw_filters_compare_viewer(array(array('field' => '', 'value' => '', 'dynamic_value' => 'viewer'))), 'a filter with no field is not applied');
	pg_assert_false(pg_sw_filters_compare_viewer(array(array('field' => 'board', 'value' => '', 'dynamic_value' => 'url parameter'))), 'an address value');
	pg_assert_false(pg_sw_filters_compare_viewer(array('x', null)), 'entries that are not filters');

	// The search index asks the same question.
	$view = array('regionType' => 'form_item_view', 'filters' => array(array('field' => 'submitter', 'value' => '', 'dynamic_value' => 'viewer')));
	pg_assert_false(pg_sw_search_index_eligible(array(), $view), 'the search index leaves such a view out');
	pg_assert_contains('pg_sw_filters_compare_viewer(', comments_recent_test_php_body('pg_sw_search_index_eligible'), 'through the shared helper');
}

// get_page.php sends a visitor who is not signed in to the sign-in screen
// before it asks the folder for these pages, so a list must not show their
// comments to such a visitor either.
function test_comments_recent_sign_in_pages()
{
	if (!comments_recent_test_has('pg_comments_recent_sign_in_page')) {
		return;
	}

	foreach (array('my account', 'my account profile', 'view order', 'affiliate sign up form') as $type) {
		pg_assert_true(pg_comments_recent_sign_in_page($type, null), $type . ': asks for a sign-in');
	}
	foreach (array('standard', 'form item view', 'form list view', 'registration entrance', 'membership entrance', 'catalog detail', '', null, 5) as $type) {
		pg_assert_false(pg_comments_recent_sign_in_page($type, null), json_encode($type) . ': open to a visitor');
	}

	$form = array('auto_registration' => '0', 'membership' => '0', 'membership_days' => '0', 'private' => '0', 'private_folder_id' => '0');
	pg_assert_false(pg_comments_recent_sign_in_page('custom form', $form), 'a plain custom form');
	pg_assert_true(pg_comments_recent_sign_in_page('custom form', array('membership' => '1', 'membership_days' => '30') + $form), 'a form that grants a trial membership');
	pg_assert_false(pg_comments_recent_sign_in_page('custom form', array('membership' => '1', 'membership_days' => '0') + $form), 'a membership of no days');
	pg_assert_true(pg_comments_recent_sign_in_page('custom form', array('private' => '1', 'private_folder_id' => '8') + $form), 'a form that grants private access');
	pg_assert_false(pg_comments_recent_sign_in_page('custom form', array('private' => '1', 'private_folder_id' => '0') + $form), 'private access to no folder');
	pg_assert_false(pg_comments_recent_sign_in_page('custom form', array('auto_registration' => '1', 'membership' => '1', 'membership_days' => '30') + $form), 'one that registers the visitor itself');
	pg_assert_true(pg_comments_recent_sign_in_page('custom form', array('membership' => 1, 'membership_days' => 30, 'auto_registration' => 0)), 'numbers, as read');
	pg_assert_false(pg_comments_recent_sign_in_page('custom form', null), 'a custom form without its settings row');
	pg_assert_false(pg_comments_recent_sign_in_page('standard', array('membership' => '1', 'membership_days' => '30') + $form), 'settings of another type are not read');

	$sources = comments_recent_test_php_body('pg_comments_recent_sources');
	pg_assert_contains('pg_comments_recent_sign_in_page(', $sources, 'the sources ask it');
	pg_assert_contains("get_page_type_properties(\$page_id, 'custom form')", $sources, 'a custom form\'s settings are read');
}

// -- the gates in SQL ----------------------------------------------------------

function test_comments_recent_access_sql()
{
	if (!comments_recent_test_has('pg_comments_recent_access_sql')) {
		return;
	}

	$visitor = array('signed_in' => false, 'user_id' => 0, 'live' => true, 'form_staff' => false);
	$member = array('signed_in' => true, 'user_id' => 42, 'live' => true, 'form_staff' => false);
	$editor = array('signed_in' => true, 'user_id' => 7, 'live' => true, 'form_staff' => true);
	$background = array('signed_in' => false, 'user_id' => 0, 'live' => false, 'form_staff' => false);

	foreach (array('public', '', 'other', null) as $access) {
		pg_assert_same('', pg_comments_recent_access_sql($access, $visitor), 'open: ' . json_encode($access));
		pg_assert_same('', pg_comments_recent_access_sql($access, $background), 'open in the background: ' . json_encode($access));
	}

	pg_assert_same('', pg_comments_recent_access_sql('logged_in', $member), 'signed in: open');
	pg_assert_same(null, pg_comments_recent_access_sql('logged_in', $visitor), 'signed out: closed');
	pg_assert_same(null, pg_comments_recent_access_sql('logged_in', $background), 'a background render: closed');
	pg_assert_same(null, pg_comments_recent_access_sql('logged_in', array('signed_in' => true, 'user_id' => 42, 'live' => false)), 'signed in but drawn in the background: closed');

	pg_assert_same('', pg_comments_recent_access_sql('submitter_only', $editor), 'edit rights on the form: open');
	pg_assert_same(" AND ((forms.user_id = '42') OR (forms.form_editor_user_id = '42'))", pg_comments_recent_access_sql('submitter_only', $member), 'a member: their own and the ones they edit');
	pg_assert_same(" AND ((forms.user_id = '42') OR (forms.form_editor_user_id = '42'))",
		pg_comments_recent_access_sql('submitter_only', array('signed_in' => true, 'user_id' => "42' OR '1", 'live' => true)), 'the user id is a number');
	pg_assert_same(null, pg_comments_recent_access_sql('submitter_only', $visitor), 'signed out: closed');
	pg_assert_same(null, pg_comments_recent_access_sql('submitter_only', $background), 'a background render: closed');
	pg_assert_same(null, pg_comments_recent_access_sql('submitter_only', array('signed_in' => true, 'user_id' => 0, 'live' => true)), 'no user id: closed');
	pg_assert_same(null, pg_comments_recent_access_sql('submitter_only', array('live' => false, 'form_staff' => true, 'signed_in' => true, 'user_id' => 7)), 'staff rights are not read in the background');
	pg_assert_same(null, pg_comments_recent_access_sql('submitter_only', 'x'), 'no viewer: closed');

	pg_assert_false(strpos((string) pg_comments_recent_access_sql('submitter_only', $member), 'reference_code'), 'session reference codes never open a record');
}

function test_comments_recent_audience_sql()
{
	if (!comments_recent_test_has('pg_comments_recent_audience_sql')) {
		return;
	}

	pg_assert_same(" AND (comments.published = '1')", pg_comments_recent_audience_sql('published', 0), 'published');
	pg_assert_same(" AND (comments.published = '1')", pg_comments_recent_audience_sql('published', 42), 'published for a member too');
	pg_assert_same(" AND (comments.created_user_id = '42')", pg_comments_recent_audience_sql('mine', 42), 'mine: the account\'s own');
	pg_assert_same(" AND (comments.created_user_id = '42')", pg_comments_recent_audience_sql('mine', '42'), 'mine: the id as text');
	pg_assert_same(null, pg_comments_recent_audience_sql('mine', 0), 'mine signed out: nothing');
	pg_assert_same(null, pg_comments_recent_audience_sql('mine', 'x'), 'mine without a number');
	pg_assert_same(" AND (comments.published = '0')", pg_comments_recent_audience_sql('pending', 0), 'pending');
	pg_assert_same(" AND (comments.published = '1')", pg_comments_recent_audience_sql('other', 3), 'an unknown scope reads published');
	pg_assert_false(strpos((string) pg_comments_recent_audience_sql('mine', 42), 'comments.id'), 'never the session\'s comment ids');

	// An account id a deleted account had may be handed out again (MySQL 5.7
	// resets AUTO_INCREMENT on a restart); the comments that account wrote
	// are older than the new account, so 'mine' starts at its creation.
	pg_assert_same(" AND (comments.created_user_id = '42') AND (comments.created_timestamp >= '1700000000')",
		pg_comments_recent_audience_sql('mine', 42, 1700000000), 'mine: only what the account wrote since it was made');
	pg_assert_same(" AND (comments.created_user_id = '42') AND (comments.created_timestamp >= '1700000000')",
		pg_comments_recent_audience_sql('mine', 42, '1700000000'), 'the time as text');
	foreach (array(0, '0', -5, 'x', '17e8', null, true, 1.5) as $unknown) {
		pg_assert_same(" AND (comments.created_user_id = '42')", pg_comments_recent_audience_sql('mine', 42, $unknown), 'an unknown creation time adds no bound: ' . json_encode($unknown));
	}
	pg_assert_same(" AND (comments.published = '1')", pg_comments_recent_audience_sql('published', 42, 1700000000), 'published has no bound');
	pg_assert_same(" AND (comments.published = '0')", pg_comments_recent_audience_sql('pending', 42, 1700000000), 'pending neither');
	pg_assert_same(null, pg_comments_recent_audience_sql('mine', 0, 1700000000), 'mine signed out stays nothing');
}

// The time an account was made, for 'mine': user.user_created, written once
// by every screen that makes an account; 0 (unknown) for accounts made before
// the column, and before the upgrade that adds it.
function test_comments_recent_account_since()
{
	if (!comments_recent_test_has('pg_comments_recent_account_since')) {
		return;
	}

	$body = comments_recent_test_php_body('pg_comments_recent_account_since');
	pg_assert_contains("pg_schema_has('user', 'user_created')", $body, 'probed before it is read');
	pg_assert_contains('SELECT user_created FROM user WHERE user_id', $body, 'read by the account\'s id');
	pg_assert_same(0, pg_comments_recent_account_since(0), 'no account: unknown');
	pg_assert_same(0, pg_comments_recent_account_since(-3), 'not an account: unknown');

	$recent = comments_recent_test_php_body('_render_system_widget_comments_recent');
	pg_assert_contains('pg_comments_recent_account_since(', $recent, 'the list reads it for mine');
	pg_assert_contains("'since' =>", $recent, 'and hands it to the reader');
}

// -- the statements ---------------------------------------------------------------

function comments_recent_test_squash($sql)
{
	return trim(preg_replace('/\s+/', ' ', (string) $sql));
}

function test_comments_recent_sql_text()
{
	if (!comments_recent_test_has(array('pg_comments_recent_candidates_sql', 'pg_comments_recent_latest_sql', 'pg_comments_recent_pages_sql',
		'pg_comments_recent_direct_sql', 'pg_comments_recent_rows_sql', 'pg_comments_recent_titles_sql'))) {
		return;
	}

	comments_recent_test_db_stub();

	$gate = array('join' => ' LEFT JOIN user AS submitter ON forms.user_id = submitter.user_id', 'where' => " AND (submitter.user_username = 'x')");
	$access = " AND ((forms.user_id = '42') OR (forms.form_editor_user_id = '42'))";

	$candidates = comments_recent_test_squash(pg_comments_recent_candidates_sql(12, 31, $gate, $access, 6));
	foreach (array(
		'FROM submitted_form_info AS recent_info',
		"INNER JOIN forms ON (forms.id = recent_info.submitted_form_id) AND (forms.page_id = '31')",
		"WHERE (recent_info.page_id = '12') AND (recent_info.newest_comment_id > 0)",
		'(forms.complete = 1)',
		'ORDER BY recent_info.newest_comment_id DESC',
		'LIMIT 12',
	) as $needle) {
		pg_assert_contains($needle, $candidates, 'candidates: ' . $needle);
	}
	$join_at = strpos($candidates, 'LEFT JOIN user AS submitter');
	$where_at = strpos($candidates, 'WHERE');
	$complete_at = strpos($candidates, '(forms.complete = 1)');
	$access_at = strpos($candidates, 'forms.form_editor_user_id');
	$gate_at = strpos($candidates, "submitter.user_username = 'x'");
	$order_at = strpos($candidates, 'ORDER BY');
	pg_assert_true(($join_at !== false) && ($join_at < $where_at), 'the gate join before the WHERE');
	pg_assert_true(($complete_at < $access_at) && ($access_at < $gate_at) && ($gate_at < $order_at), 'complete, access, gate where, then the order');
	pg_assert_contains('recent_info.submitted_form_id', $candidates, 'the record');
	pg_assert_contains('forms.reference_code', $candidates, 'its address');
	pg_assert_contains('forms.address_name', $candidates, 'its pretty name');

	$latest = comments_recent_test_squash(pg_comments_recent_latest_sql(12, array(5, '6', 7), 6));
	pg_assert_same(3, substr_count($latest, "(comments.item_type = 'submitted_form')"), 'one part per record');
	pg_assert_same(2, substr_count($latest, ' UNION ALL '), 'joined by UNION ALL');
	pg_assert_same(3, substr_count($latest, "(comments.published = '1')"), 'published only');
	pg_assert_same(3, substr_count($latest, 'ORDER BY comments.created_timestamp DESC LIMIT 6)'), 'each the newest k, in parentheses');
	pg_assert_contains("(comments.page_id = '12')", $latest, 'on the source page');
	pg_assert_contains("(comments.item_id = '6')", $latest, 'the record ids');
	pg_assert_same(0, strpos($latest, '(SELECT'), 'parenthesised');
	pg_assert_same('', pg_comments_recent_latest_sql(12, array(), 6), 'no record, no statement');
	pg_assert_same('', pg_comments_recent_latest_sql(12, array(0, -1, 'x'), 6), 'no valid record, no statement');

	$pages = comments_recent_test_squash(pg_comments_recent_pages_sql(array(3, 4), " AND (comments.published = '0')", 'oldest', 11));
	pg_assert_same(2, substr_count($pages, "(comments.item_type = '') AND (comments.item_id = '0')"), 'the page thread of each page');
	// item_id = 0 and published = 1 match most of the site's comments:
	// without the hint the optimizer intersects one of those keys with
	// page_id and sorts the page's whole thread instead of reading
	// idx_thread backward. Both keys are in every install, so the hint never
	// names a missing key.
	pg_assert_same(2, substr_count($pages, 'FROM comments IGNORE INDEX (item_id, published) WHERE'), 'each part reads past the item_id and published keys');
	foreach (array('english_default', 'turkish_default') as $dump) {
		$sql = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $dump . '/sql.sql');
		$at = strpos($sql, 'CREATE TABLE `comments` (');
		$table = ($at === false) ? '' : substr($sql, $at, strpos($sql, 'ENGINE=', $at) - $at);
		pg_assert_contains('KEY `item_id` (`item_id`)', $table, $dump . ' installs the item_id key');
		pg_assert_contains('KEY `published` (`published`)', $table, $dump . ' installs the published key');
	}
	pg_assert_false(strpos($pages, 'FORCE INDEX'), 'never forces idx_thread, which an install without the 11.0.31 step lacks');
	pg_assert_same(2, substr_count($pages, "(comments.published = '0')"), 'the audience in each part');
	pg_assert_same(2, substr_count($pages, 'ORDER BY comments.created_timestamp ASC LIMIT 11)'), 'oldest first');
	pg_assert_contains("(comments.page_id = '4')", $pages, 'each page');
	pg_assert_same('', pg_comments_recent_pages_sql(array(), " AND (comments.published = '1')", 'newest', 6), 'no page, no statement');
	pg_assert_contains('DESC', pg_comments_recent_pages_sql(array(3), " AND (comments.published = '1')", 'newest', 6), 'newest first');

	$direct = comments_recent_test_squash(pg_comments_recent_direct_sql(12, 31, $gate, '', " AND (comments.created_user_id = '42')", 'newest', 21));
	foreach (array(
		'FROM comments',
		"INNER JOIN forms ON (forms.id = comments.item_id) AND (forms.page_id = '31')",
		"(comments.page_id = '12') AND (comments.item_type = 'submitted_form')",
		"(comments.created_user_id = '42')",
		'(forms.complete = 1)',
		'ORDER BY comments.created_timestamp DESC LIMIT 21',
	) as $needle) {
		pg_assert_contains($needle, $direct, 'direct: ' . $needle);
	}
	pg_assert_false(strpos($direct, 'comments.id DESC'), 'no id tie-break');
	pg_assert_false(strpos($direct, 'comments.id ASC'), 'no id tie-break either way');
	pg_assert_contains('ORDER BY comments.created_timestamp ASC', pg_comments_recent_direct_sql(12, 31, $gate, '', '', 'oldest', 21), 'oldest first');

	$rows = comments_recent_test_squash(pg_comments_recent_rows_sql(array(9, '10', 'x', 0, 9), " AND (comments.published = '1')"));
	pg_assert_contains("WHERE (comments.id IN (9,10)) AND (comments.published = '1')", $rows, 'whole numbers, each once, under the audience');
	pg_assert_contains('LEFT JOIN user ON comments.created_user_id = user.user_id', $rows, 'the writer\'s badge');
	pg_assert_false(strpos($rows, 'files'), 'no attachment read');
	pg_assert_same('', pg_comments_recent_rows_sql(array('x'), ''), 'no id, no statement');

	$titles = comments_recent_test_squash(pg_comments_recent_titles_sql(array(5, 6), array('Title', "it's")));
	pg_assert_contains('WHERE (form_data.form_id IN (5,6))', $titles, 'the drawn records only');
	pg_assert_contains("(form_fields.rss_field = 'title') OR (form_fields.name IN ('title','it\\'s'))", $titles, 'the RSS title and the escaped title fields');
	pg_assert_contains('ORDER BY form_data.form_id ASC, form_fields.sort_order ASC, form_data.id ASC', $titles, 'in field order');

	// A record view without a title field (the default) has only the RSS
	// title: an empty IN () would be refused by the server.
	$plain = comments_recent_test_squash(pg_comments_recent_titles_sql(array(5), array()));
	pg_assert_contains("AND (form_fields.rss_field = 'title')", $plain, 'only the RSS title');
	pg_assert_false(strpos($plain, 'IN ()'), 'no empty list');
	pg_assert_false(strpos($plain, 'form_fields.name IN'), 'no field list');
	pg_assert_false(strpos(pg_comments_recent_titles_sql(array(5), array('', ' ')), 'form_fields.name IN'), 'blank names are no field');
	pg_assert_same('', pg_comments_recent_titles_sql(array(), array('title')), 'no record, no statement');
}

// A stub of db_items(): every statement is recorded and answered from a list.
function comments_recent_test_fetcher(&$log, $answers)
{
	return function ($sql) use (&$log, $answers) {
		$log[] = $sql;
		foreach ($answers as $needle => $rows) {
			if (strpos($sql, $needle) !== false) {
				return $rows;
			}
		}
		return array();
	};
}

function comments_recent_test_source_row($over = array())
{
	return $over + array(
		'page_id' => 12,
		'kind' => 'record',
		'page_name' => 'topic',
		'title' => 'Topic',
		'staff' => false,
		'settings' => pg_comments_settings(array('comments' => '1', 'comments_show_submitted_date_and_time' => 1)),
		'url' => '/topic',
		'form_id' => 31,
		'view_cfg' => array('regionType' => 'form_item_view', 'title_field' => 'title'),
		'pretty' => false,
		'access' => '',
		'gate' => array('join' => '', 'where' => ''),
	);
}

// The reader sends the statements of each scope, and none for a source whose
// gate or audience is closed.
function test_comments_recent_read_statements()
{
	if (!comments_recent_test_has(array('pg_comments_recent_read', 'pg_comments_recent_rows', 'pg_comments_recent_titles'))) {
		return;
	}

	comments_recent_test_db_stub();

	$member = array('signed_in' => true, 'user_id' => 42, 'live' => true);
	$visitor = array('signed_in' => false, 'user_id' => 0, 'live' => true);
	$options = array('show' => 'published', 'page_ids' => array(12), 'limit' => 5, 'order' => 'oldest');

	$log = array();
	$fetch = comments_recent_test_fetcher($log, array(
		'FROM submitted_form_info' => array(
			array('submitted_form_id' => '5', 'newest_comment_id' => '90', 'reference_code' => 'AB', 'address_name' => ''),
			array('submitted_form_id' => '5', 'newest_comment_id' => '90', 'reference_code' => 'AB', 'address_name' => ''),
			array('submitted_form_id' => '6', 'newest_comment_id' => '80', 'reference_code' => 'CD', 'address_name' => 'cd'),
		),
		'UNION ALL' => array(
			array('id' => '90', 'page_id' => '12', 'item_id' => '5', 'created_timestamp' => '1000'),
			array('id' => '80', 'page_id' => '12', 'item_id' => '6', 'created_timestamp' => '900'),
			array('id' => '70', 'page_id' => '12', 'item_id' => '5', 'created_timestamp' => '900'),
		),
	));
	$read = pg_comments_recent_read(array(comments_recent_test_source_row()), $options, $visitor, $fetch);
	pg_assert_same(2, count($log), 'published: the candidates, then the newest comments of the records kept');
	pg_assert_contains('FROM submitted_form_info AS recent_info', $log[0], 'the candidates first');
	pg_assert_contains("(comments.item_id = '5')", $log[1], 'the records in it');
	pg_assert_same(1, substr_count($log[1], "(comments.item_id = '5')"), 'a record listed twice is read once');
	pg_assert_same(array(90, 80, 70), array_map(function ($e) { return $e['id']; }, $read['entries']), 'newest first whatever the stored order; a tie by id');
	pg_assert_same('AB', $read['entries'][0]['reference_code'], 'each comment carries its record\'s address');
	pg_assert_same(5, $read['entries'][0]['record_id'], 'and its record');
	pg_assert_same(12, $read['entries'][0]['source'], 'and its page');
	pg_assert_false($read['more'], 'no more than the limit');

	// mine: one direct statement per record source; signed out: none.
	$log = array();
	pg_comments_recent_read(array(comments_recent_test_source_row()), array('show' => 'mine') + $options, $member, comments_recent_test_fetcher($log, array()));
	pg_assert_same(1, count($log), 'mine: one statement');
	pg_assert_contains("(comments.created_user_id = '42')", $log[0], 'the account\'s comments');
	pg_assert_contains('ORDER BY comments.created_timestamp ASC', $log[0], 'in the order chosen');

	$log = array();
	$none = pg_comments_recent_read(array(comments_recent_test_source_row()), array('show' => 'mine') + $options, $visitor, comments_recent_test_fetcher($log, array()));
	pg_assert_same(array(), $log, 'mine signed out sends nothing');
	pg_assert_same(array(), $none['entries'], 'and lists nothing');

	// A closed gate or access sends nothing for that source.
	$log = array();
	pg_comments_recent_read(array(
		comments_recent_test_source_row(array('access' => null)),
		comments_recent_test_source_row(array('gate' => null, 'page_id' => 13)),
	), $options, $visitor, comments_recent_test_fetcher($log, array()));
	pg_assert_same(array(), $log, 'closed sources send nothing');

	// Page sources share one statement.
	$log = array();
	pg_comments_recent_read(array(
		comments_recent_test_source_row(array('kind' => 'page', 'page_id' => 3)),
		comments_recent_test_source_row(array('kind' => 'page', 'page_id' => 4)),
	), array('show' => 'pending') + $options, $visitor, comments_recent_test_fetcher($log, array()));
	pg_assert_same(1, count($log), 'page sources: one statement');
	pg_assert_same(2, substr_count($log[0], "(comments.item_type = '')"), 'one part per page');
	pg_assert_contains("(comments.published = '0')", $log[0], 'pending');
	pg_assert_contains('ASC', $log[0], 'oldest first for pending');

	// The published scope is always newest first.
	$log = array();
	pg_comments_recent_read(array(comments_recent_test_source_row(array('kind' => 'page'))), $options, $visitor, comments_recent_test_fetcher($log, array()));
	pg_assert_contains('DESC', $log[0], 'published reads the newest first');

	// The rows of the drawn comments, under the audience.
	$log = array();
	$rows = pg_comments_recent_rows(array(9, 10), " AND (comments.published = '1')", comments_recent_test_fetcher($log, array('comments.id IN' => array(array('id' => '10', 'message' => 'x'), array('id' => '9', 'message' => 'y')))));
	pg_assert_same(array(10, 9), array_keys($rows), 'by id');
	pg_assert_same(1, count($log), 'one statement');
	$log = array();
	pg_assert_same(array(), pg_comments_recent_rows(array(), '', comments_recent_test_fetcher($log, array())), 'no id');
	pg_assert_same(array(), $log, 'sends nothing');

	// Titles: a view without a title field still reads the RSS title, and
	// the source page names a record without one.
	$sources = array(
		12 => comments_recent_test_source_row(array('view_cfg' => array('regionType' => 'form_item_view'))),
		13 => comments_recent_test_source_row(array('page_id' => 13, 'title' => 'Docs', 'view_cfg' => array('regionType' => 'form_item_view', 'title_field' => 'subject', 'title_suffix' => '| Forum'))),
		3 => comments_recent_test_source_row(array('page_id' => 3, 'kind' => 'page', 'title' => 'About us')),
	);
	$entries = array(
		array('id' => 90, 'source' => 12, 'record_id' => 5, 'reference_code' => 'AB'),
		array('id' => 80, 'source' => 13, 'record_id' => 6, 'reference_code' => 'CD'),
		array('id' => 70, 'source' => 12, 'record_id' => 7, 'reference_code' => 'EF'),
		array('id' => 60, 'source' => 3, 'record_id' => 0, 'reference_code' => ''),
	);
	$log = array();
	$titles = pg_comments_recent_titles($entries, $sources, comments_recent_test_fetcher($log, array('FROM form_data' => array(
		array('form_id' => '5', 'name' => 'title', 'rss_field' => 'title', 'data' => 'How do I reset my password?', 'type' => 'text box'),
		array('form_id' => '6', 'name' => 'subject', 'rss_field' => '', 'data' => 'Exports <b>fail</b>', 'type' => 'text box'),
		array('form_id' => '6', 'name' => 'title', 'rss_field' => 'title', 'data' => 'RSS words', 'type' => 'text box'),
	))));
	pg_assert_same(1, count($log), 'one statement for every record drawn');
	pg_assert_contains("form_fields.name IN ('subject')", $log[0], 'the title fields the views name');
	pg_assert_same('How do I reset my password?', $titles['12:5'], 'the RSS title of a view without a title field');
	pg_assert_same('Exports <b>fail</b>', $titles['13:6'], 'the title field, without the page\'s title suffix; raw, escaped by the draw');
	pg_assert_same('Topic', $titles['12:7'], 'a record without a title: its page');
	pg_assert_same('About us', $titles['3:0'], 'a page comment: its page');

	$log = array();
	pg_comments_recent_titles(array($entries[3]), $sources, comments_recent_test_fetcher($log, array()));
	pg_assert_same(array(), $log, 'page comments read no record');
}

// job.php publishes a scheduled comment by giving it the current time and
// writes its (older, lower) id as the record's newest_comment_id: the record
// ranks below records whose newest comment is older. Every record among the
// 2k candidates is read, so such a record is listed while it is among them.
function test_comments_recent_read_keeps_every_candidate()
{
	if (!comments_recent_test_has('pg_comments_recent_read')) {
		return;
	}

	comments_recent_test_db_stub();

	$visitor = array('signed_in' => false, 'user_id' => 0, 'live' => true);
	$options = array('show' => 'published', 'page_ids' => array(12), 'limit' => 1, 'order' => 'newest');
	$log = array();
	$read = pg_comments_recent_read(array(comments_recent_test_source_row()), $options, $visitor, comments_recent_test_fetcher($log, array(
		'FROM submitted_form_info' => array(
			array('submitted_form_id' => '11', 'newest_comment_id' => '400', 'reference_code' => 'K1', 'address_name' => ''),
			array('submitted_form_id' => '12', 'newest_comment_id' => '300', 'reference_code' => 'K2', 'address_name' => ''),
			array('submitted_form_id' => '13', 'newest_comment_id' => '200', 'reference_code' => 'K3', 'address_name' => ''),
			array('submitted_form_id' => '14', 'newest_comment_id' => '5', 'reference_code' => 'K4', 'address_name' => ''),
		),
		'UNION ALL' => array(
			array('id' => '400', 'page_id' => '12', 'item_id' => '11', 'created_timestamp' => '4000'),
			array('id' => '300', 'page_id' => '12', 'item_id' => '12', 'created_timestamp' => '3000'),
			array('id' => '200', 'page_id' => '12', 'item_id' => '13', 'created_timestamp' => '2000'),
			array('id' => '5', 'page_id' => '12', 'item_id' => '14', 'created_timestamp' => '9000'),
		),
	)));

	pg_assert_same(2, count($log), 'the candidates, then their newest comments');
	pg_assert_contains('LIMIT 4', $log[0], '2k candidates (k = limit + 1)');
	foreach (array(11, 12, 13, 14) as $record_id) {
		pg_assert_contains("(comments.item_id = '" . $record_id . "')", $log[1], 'record ' . $record_id . ' among the candidates is read');
	}
	pg_assert_same(array(5), array_map(function ($e) { return $e['id']; }, $read['entries']), 'the comment job.php published last is the newest');
	pg_assert_same(14, $read['entries'][0]['record_id'], 'on its record');
	pg_assert_same('K4', $read['entries'][0]['reference_code'], 'with its address');
	pg_assert_true($read['more'], 'more remain');

	// 'mine' starts at the account's creation, in the statement that picks.
	$log = array();
	pg_comments_recent_read(array(comments_recent_test_source_row(), comments_recent_test_source_row(array('kind' => 'page', 'page_id' => 3))),
		array('show' => 'mine') + $options, array('signed_in' => true, 'user_id' => 42, 'live' => true, 'since' => 1700000000), comments_recent_test_fetcher($log, array()));
	pg_assert_same(2, count($log), 'a record and a page statement');
	foreach ($log as $sql) {
		pg_assert_contains("(comments.created_user_id = '42') AND (comments.created_timestamp >= '1700000000')", $sql, 'bounded by the account\'s creation');
	}
}

// -- the merge -----------------------------------------------------------------

function test_comments_recent_merge()
{
	if (!comments_recent_test_has('pg_comments_recent_merge')) {
		return;
	}

	$e = function ($id, $time) {
		return array('id' => $id, 'created_timestamp' => $time);
	};
	$ids = function ($merged) {
		return array_map(function ($entry) { return $entry['id']; }, $merged['entries']);
	};
	$lists = array(
		array($e(5, 100), $e(3, 300), $e(9, 200)),
		array($e(7, 300), $e(5, 100), $e(1, 50)),
		'not a list',
		array('not an entry', $e(0, 400), $e('x', 500)),
	);

	$newest = pg_comments_recent_merge($lists, 'newest', 3);
	pg_assert_same(array(7, 3, 9), $ids($newest), 'newest first; the same second by the higher id');
	pg_assert_true($newest['more'], 'more remain');

	$oldest = pg_comments_recent_merge($lists, 'oldest', 3);
	pg_assert_same(array(1, 5, 9), $ids($oldest), 'oldest first');

	$all = pg_comments_recent_merge($lists, 'oldest', 10);
	pg_assert_same(array(1, 5, 9, 3, 7), $ids($all), 'each comment once; the same second by the lower id');
	pg_assert_false($all['more'], 'nothing more');

	$exact = pg_comments_recent_merge(array(array($e(1, 1), $e(2, 2))), 'newest', 2);
	pg_assert_false($exact['more'], 'exactly the limit: no more');

	pg_assert_same(array('entries' => array(), 'more' => false), pg_comments_recent_merge(array(), 'newest', 5), 'no list');
	pg_assert_same(array('entries' => array(), 'more' => false), pg_comments_recent_merge(null, 'newest', 5), 'not lists');
	pg_assert_same(array(2, 1), $ids(pg_comments_recent_merge(array(array($e(1, 1), $e(2, 2))), 'sideways', 5)), 'an unknown order reads newest');
}

// -- flags and tokens ---------------------------------------------------------

function test_comments_recent_flags_and_rows()
{
	if (!comments_recent_test_has(array('pg_comments_recent_flags', 'pg_comments_recent_row_flags'))) {
		return;
	}

	$flags = pg_comments_recent_flags(array('count' => 3, 'more' => true, 'signed_in' => true, 'staff' => false));
	pg_assert_same(array('has_comments', 'no_comments', 'has_more_pages', 'is_signed_in', 'is_signed_out', 'is_staff'), array_keys($flags), 'the six list flags');
	pg_assert_same(array('has_comments' => true, 'no_comments' => false, 'has_more_pages' => true, 'is_signed_in' => true, 'is_signed_out' => false, 'is_staff' => false), $flags, 'their values');

	$empty = pg_comments_recent_flags(array('count' => 0, 'more' => false, 'signed_in' => false, 'staff' => true));
	pg_assert_true($empty['no_comments'] && !$empty['has_comments'], 'no comments');
	pg_assert_true($empty['is_signed_out'] && !$empty['is_signed_in'], 'signed out');
	pg_assert_true($empty['is_staff'], 'staff of a source page');
	pg_assert_same(pg_comments_recent_flags(array()), pg_comments_recent_flags('x'), 'not facts');
	pg_assert_true(pg_comments_recent_flags(array('count' => '2'))['has_comments'], 'a count as text');

	pg_assert_same(array('comment_on_record' => true, 'comment_on_page' => false), pg_comments_recent_row_flags('record'), 'a record\'s comment');
	pg_assert_same(array('comment_on_record' => false, 'comment_on_page' => true), pg_comments_recent_row_flags('page'), 'a page\'s comment');
	pg_assert_same(array('comment_on_record' => false, 'comment_on_page' => false), pg_comments_recent_row_flags('x'), 'neither');

	// The row state per source page: staff values only for that page's staff.
	$row = array('id' => '9', 'name' => 'Jane', 'message' => 'Hello there', 'published' => '1', 'rating' => '4', 'username' => 'jane',
		'created_timestamp' => (string) (time() - 3600), 'publish_date_and_time' => '0000-00-00 00:00:00', 'featured' => '0');
	$state = array('is_staff' => false, 'sees_pending' => false, 'own_ids' => array(), 'rating_on' => false, 'badge_label' => '');
	$row_flags = pg_comments_widget_row_flags($row, false, $state, array('depth' => 0));
	$values = pg_comments_widget_row_values($row, $row_flags, array('is_staff' => false, 'sees_pending' => false, 'show_date' => false, 'label_lower' => 'reply', 'badge_label' => ''));
	pg_assert_same('', $values['__comment_staff_name'], 'no user name for a visitor');
	pg_assert_same('', $values['__comment_edit_url'], 'no edit link');
	pg_assert_same('', $values['__comment_date'], 'no date when the page hides it');
	pg_assert_same('', $values['__comment_rating'], 'no rating when the page has them off');
	pg_assert_same('', $values['__comment_rating_label'], 'nor its words');
	pg_assert_same('', $values['__comment_stars_text'], 'nor its stars');
	pg_assert_false($row_flags['comment_has_rating'], 'and no rating flag');
	pg_assert_same('Hello there', $values['__comment_excerpt'], 'the excerpt in both modes');

	$staff_flags = pg_comments_widget_row_flags($row, false, array('is_staff' => true, 'sees_pending' => true, 'rating_on' => true) + $state, array('depth' => 0));
	$staff_values = pg_comments_widget_row_values($row, $staff_flags, array('is_staff' => true, 'sees_pending' => true, 'show_date' => false, 'label_lower' => 'reply', 'badge_label' => ''));
	pg_assert_same('jane', $staff_values['__comment_staff_name'], 'the user name for that page\'s staff');
	pg_assert_contains('edit_comment.php?id=9', $staff_values['__comment_edit_url'], 'the edit link');
	pg_assert_true($staff_flags['comment_has_rating'], 'stars with the page\'s stars on');

	$long = str_repeat('word ', 60);
	$long_values = pg_comments_widget_row_values(array('message' => $long) + $row, $row_flags, array('is_staff' => false, 'sees_pending' => false, 'show_date' => false, 'label_lower' => 'reply', 'badge_label' => ''));
	pg_assert_true(mb_strlen($long_values['__comment_excerpt']) <= 161, 'the excerpt is cut at 160 characters');
	pg_assert_same('…', mb_substr($long_values['__comment_excerpt'], -1), 'with an ellipsis');
}

function test_comments_recent_link_values()
{
	if (!comments_recent_test_has(array('pg_comments_recent_link_values', 'pg_comments_recent_title'))) {
		return;
	}

	$record = pg_comments_recent_link_values(5, '/topic?r=AB12', 'How do I reset?', 'Reply');
	pg_assert_same('/topic?r=AB12&comments=all&comment=5#c-5', $record['__comment_url'], 'the permalink on the record page');
	pg_assert_same(pg_comment_view_query('/topic?r=AB12', 5) . '#c-5', $record['__comment_url'], 'as the e-mails write it');
	pg_assert_same('', $record['__comment_anchor'], 'no anchor: the comment is not on this page');
	pg_assert_same('/topic?r=AB12', $record['__comment_thread_url'], 'the thread');
	pg_assert_same('How do I reset?', $record['__comment_thread_title'], 'its title');
	pg_assert_same('Reply', $record['__comment_label'], 'the label of its page');

	pg_assert_same('/topic/my-topic?comments=all&comment=5#c-5', pg_comments_recent_link_values(5, '/topic/my-topic', 't', 'l')['__comment_url'], 'a pretty address');
	pg_assert_same('/about?comments=all&comment=7#c-7', pg_comments_recent_link_values(7, '/about', 't', 'l')['__comment_url'], 'a page');
	pg_assert_same('', pg_comments_recent_link_values(7, '', 't', 'l')['__comment_url'], 'no address, no link');
	pg_assert_same('', pg_comments_recent_link_values(0, '/about', 't', 'l')['__comment_url'], 'no comment, no link');

	$rows = array(
		array('name' => 'title', 'rss_field' => 'title', 'data' => 'RSS title', 'type' => 'text box'),
		array('name' => 'subject', 'rss_field' => '', 'data' => "  Subject   line ", 'type' => 'text box'),
	);
	pg_assert_same('Subject line', pg_comments_recent_title(array('title_field' => 'subject', 'title_suffix' => '| Forum'), $rows, 'AB', 'Topic', 'topic'), 'the title field, the suffix left out');
	pg_assert_same('RSS title', pg_comments_recent_title(array(), $rows, 'AB', 'Topic', 'topic'), 'else the RSS title');
	pg_assert_same('AB', pg_comments_recent_title(array('title_field' => 'reference_code'), array(), 'AB', 'Topic', 'topic'), 'a standard field');
	pg_assert_same('Topic', pg_comments_recent_title(array('title_field' => 'subject'), array(), 'AB', 'Topic', 'topic'), 'else the page title');
	pg_assert_same('topic', pg_comments_recent_title(array(), array(), '', '', 'topic'), 'else the page name');
	pg_assert_same('Topic', pg_comments_recent_title(null, null, '', 'Topic', 'topic'), 'a page comment');
	pg_assert_same(255, mb_strlen(pg_comments_recent_title(array('title_field' => 'subject'), array(array('name' => 'subject', 'data' => str_repeat('é', 300))), '', 'T', 't')), 'cut to 255');
}

// The draw escapes every value once: typed words never form a token.
function test_comments_recent_escape_once()
{
	if (!comments_recent_test_has('pg_comments_recent_link_values')) {
		return;
	}

	$values = pg_comments_widget_escape(pg_comments_recent_link_values(5, '/t?r=a&b', '^^__comment_edit_url^^ <b>x</b>', 'R&D'));
	pg_assert_same('&#94;&#94;__comment_edit_url&#94;&#94; &lt;b&gt;x&lt;/b&gt;', $values['__comment_thread_title'], 'the title escaped once, carets as entities');
	pg_assert_same('R&amp;D', $values['__comment_label'], 'the label');
	pg_assert_same('/t?r=a&amp;b', $values['__comment_thread_url'], 'the address');
}

// -- what each mode switches off ------------------------------------------------

function comments_recent_test_text_node($token, $name)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'span', 'customName' => $name, '_bindings' => array('text' => $token)), 'children' => array());
}

function comments_recent_test_names($node, &$out)
{
	if (!is_array($node)) {
		return;
	}
	if (isset($node['props']['customName'])) {
		$out[] = $node['props']['customName'];
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		comments_recent_test_names($child, $out);
	}
}

function test_comments_recent_mode_off_tables()
{
	if (!comments_recent_test_has(array('pg_comments_mode_off_bindings', 'pg_comments_drop_mode_bindings'))) {
		return;
	}

	$recent = pg_comments_mode_off_bindings('recent');
	$thread = pg_comments_mode_off_bindings('thread');
	pg_assert_same(array('actions', 'values', 'sections', 'tokens', 'flags'), array_keys($recent), 'five lists');
	foreach (array('comment_form', 'comment_submit', 'comment_reply', 'comment_reply_cancel', 'comment_watch_form', 'comment_watch', 'comment_unwatch', 'comment_allow_new_form', 'comment_close', 'comment_reopen') as $action) {
		pg_assert_true(in_array($action, $recent['actions'], true), 'recent: ' . $action . ' off');
	}
	pg_assert_false(in_array('comment_share', $recent['actions'], true), 'recent: sharing a comment stays');
	pg_assert_same(array('comment_message', 'comment_name', 'comment_name_type', 'comment_file', 'comment_watcher'), $recent['values'], 'recent: no form control');
	pg_assert_same(array('comment_captcha', 'comment_rating_input', 'comment_publish', 'comment_watchers', 'comment_pagination'), $recent['sections'], 'recent: no section');
	foreach (array('__comment_quote', '__comment_reply_url', '__comment_parent_name', '__comment_file_url', '__comment_seller_reply_html', '__comments_title', '__login_url', '__reply_to_name', '__watcher_count_text') as $token) {
		pg_assert_true(in_array($token, $recent['tokens'], true), 'recent: ' . $token . ' off');
	}
	foreach (array('__comment_thread_title', '__comment_thread_url', '__comment_label', '__comment_excerpt', '__comment_url', '__comment_name', '__comments_count') as $token) {
		pg_assert_false(in_array($token, $recent['tokens'], true), 'recent: ' . $token . ' kept');
	}
	foreach (array('can_comment', 'comments_open', 'is_replying', 'watching_on', 'comment_is_reply', 'comment_has_quote', 'comment_has_file', 'comment_has_image') as $flag) {
		pg_assert_true(in_array($flag, $recent['flags'], true), 'recent: ' . $flag . ' off');
	}
	foreach (array('has_comments', 'no_comments', 'has_more_pages', 'is_staff', 'is_signed_in', 'comment_is_pending', 'comment_on_record') as $flag) {
		pg_assert_false(in_array($flag, $recent['flags'], true), 'recent: ' . $flag . ' kept');
	}
	// Every thread flag a list does not fill is off there (is_email too): a
	// list leaves a node shown only when it out, so the canvas fades it.
	if (comments_recent_test_has(array('pg_comments_widget_thread_flags', 'pg_comments_recent_flags'))) {
		$list_flags = pg_comments_recent_flags(array());
		foreach (array_keys(pg_comments_widget_thread_flags(array())) as $flag) {
			if (!array_key_exists($flag, $list_flags)) {
				pg_assert_true(in_array($flag, $recent['flags'], true), 'recent: the thread flag ' . $flag . ' is off');
			}
		}
	}

	pg_assert_same(array(), $thread['actions'], 'thread: every action');
	pg_assert_same(array('__comment_thread_title', '__comment_thread_url', '__comment_label'), $thread['tokens'], 'thread: the three tokens of a list');
	pg_assert_same(array('comment_on_record', 'comment_on_page'), $thread['flags'], 'thread: the two flags of a list');
	pg_assert_same(pg_comments_mode_off_bindings('thread'), pg_comments_mode_off_bindings('x'), 'an unknown mode reads the thread');

	// Dropped from the tree on the site.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		comments_recent_test_text_node('__comment_quote', 'quote'),
		comments_recent_test_text_node('__comment_thread_title', 'title'),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'customName' => 'reply link', '_bindings' => array('href' => '__comment_reply_url', 'text' => '__comment_name')), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'customName' => 'share', '_bindings' => array('action' => 'comment_share')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'img', 'customName' => 'file', '_bindings' => array('src' => '__comment_file_url')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'customName' => 'box'), 'children' => array(comments_recent_test_text_node('__comment_label', 'label'))),
	));

	$recent_tree = $tree;
	pg_assert_same(3, pg_comments_drop_mode_bindings($recent_tree, 'recent'), 'recent: three nodes dropped');
	$names = array();
	comments_recent_test_names($recent_tree, $names);
	pg_assert_same(array('title', 'share', 'box', 'label'), $names, 'recent keeps the list tokens and the share link');

	$thread_tree = $tree;
	pg_assert_same(2, pg_comments_drop_mode_bindings($thread_tree, 'thread'), 'thread: two nodes dropped');
	$names = array();
	comments_recent_test_names($thread_tree, $names);
	pg_assert_same(array('quote', 'reply link', 'share', 'file', 'box'), $names, 'the thread drops the list tokens');

	// The canvas twin.
	$script = "function _sdT(s) { return s; }\nvar out = { recent: _sdCommentsModeOffBindings('recent'), thread: _sdCommentsModeOffBindings('thread'), other: _sdCommentsModeOffBindings('x') };\n"
		. "function n(b, tag) { return { type: 'semantic', props: { tag: tag || 'span', _bindings: b }, children: [] }; }\n"
		. "out.fades = {\n"
		. "  quote: _sdCommentsModeOff(n({ text: '__comment_quote' }), 'comments_recent'),\n"
		. "  form: _sdCommentsModeOff(n({ action: 'comment_form' }, 'form'), 'comments_recent'),\n"
		. "  message: _sdCommentsModeOff(n({ value: 'comment_message' }, 'textarea'), 'comments_recent'),\n"
		. "  captcha: _sdCommentsModeOff(n({ section: 'comment_captcha' }, 'div'), 'comments_recent'),\n"
		. "  open: _sdCommentsModeOff(n({ eo_visible_if: 'comments_open' }, 'div'), 'comments_recent'),\n"
		. "  share: _sdCommentsModeOff(n({ action: 'comment_share' }, 'a'), 'comments_recent'),\n"
		. "  title: _sdCommentsModeOff(n({ text: '__comment_thread_title' }), 'comments_recent'),\n"
		. "  staff: _sdCommentsModeOff(n({ eo_visible_if: 'is_staff' }), 'comments_recent'),\n"
		. "  thread_title: _sdCommentsModeOff(n({ text: '__comment_thread_title' }), 'comments'),\n"
		. "  thread_on_page: _sdCommentsModeOff(n({ eo_visible_if: 'comment_on_page' }), 'comments'),\n"
		. "  thread_quote: _sdCommentsModeOff(n({ text: '__comment_quote' }), 'comments'),\n"
		. "  other_kind: _sdCommentsModeOff(n({ text: '__comment_quote' }), 'form_item_view')\n"
		. "};\nprocess.stdout.write(JSON.stringify(out));\n";
	$res = comments_recent_test_node(array('_sdCommentsModeOffBindings', '_sdCommentsModeOff'), $script);
	if ($res === null) {
		return;
	}

	pg_assert_same($recent, $res['recent'], 'twin: the recent lists');
	pg_assert_same($thread, $res['thread'], 'twin: the thread lists');
	pg_assert_same($thread, $res['other'], 'twin: an unknown mode');
	foreach (array('quote', 'form', 'message', 'captcha', 'open') as $faded) {
		pg_assert_same('Off — a list of recent comments has no form or thread controls', $res['fades'][$faded], 'recent fades ' . $faded);
	}
	foreach (array('share', 'title', 'staff', 'thread_quote', 'other_kind') as $kept) {
		pg_assert_same('', $res['fades'][$kept], 'kept: ' . $kept);
	}
	pg_assert_same('Off — filled only in a list of recent comments', $res['fades']['thread_title'], 'the thread fades a list token');
	pg_assert_same('Off — filled only in a list of recent comments', $res['fades']['thread_on_page'], 'and a list flag');
}

// -- the draw ------------------------------------------------------------------

// A tree that binds every form, control and section the thread knows, inside
// and outside the comment.
function comments_recent_test_hostile_tree()
{
	$node = function ($type, $props, $children = array()) {
		return array('type' => $type, 'props' => $props, 'children' => $children);
	};
	$sem = function ($tag, $bindings, $children = array(), $extra = array()) use ($node) {
		return $node('semantic', array('tag' => $tag, '_bindings' => $bindings) + $extra, $children);
	};
	$attrs = function ($name, $value) {
		return array(array('name' => $name, 'value' => $value));
	};

	$form = $sem('form', array('action' => 'comment_form'), array(
		$node('semantic', array('tag' => 'label', 'text' => 'Your words', '_attrs' => $attrs('for', 'comment_message')), array()),
		$sem('textarea', array('value' => 'comment_message'), array(), array('id' => 'comment_message')),
		$sem('div', array('section' => 'comment_captcha')),
		$sem('button', array('action' => 'comment_submit'), array(), array('text' => 'Send')),
	));

	return $node('root', array(), array(
		$node('container', array(), array(
			$sem('h2', array('text' => '__comments_title')),
			$sem('p', array('eo_visible_if' => 'can_comment'), array(), array('text' => 'You can comment')),
			$sem('p', array('eo_visible_if' => 'comments_open'), array(), array('text' => 'Open thread')),
			$sem('p', array('eo_visible_if' => 'is_replying'), array(), array('text' => 'Replying now')),
			$sem('p', array('eo_visible_if' => 'no_comments'), array(), array('text' => 'Nothing yet')),
			$sem('div', array(), array($node('loop_area', array(), array(
				$sem('article', array(), array(
					$sem('span', array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'), array(), array('cssClass' => 'pg-hide-if-empty status')),
					$node('content', array('contentType' => 'link', 'text' => 'T', 'href' => '#', '_bindings' => array('href' => '__comment_url', 'text' => '__comment_thread_title')), array()),
					$sem('span', array('text' => '__comment_name')),
					$sem('span', array('text' => '__comment_excerpt')),
					$node('content', array('contentType' => 'link', 'text' => 'Reply', 'href' => '#', '_bindings' => array('action' => 'comment_reply')), array()),
					$node('content', array('contentType' => 'link', 'text' => 'Share', 'href' => '#', '_bindings' => array('action' => 'comment_share')), array()),
					$sem('div', array('section' => 'comment_pagination')),
					$sem('span', array('text' => '__comment_quote'), array(), array('cssClass' => 'quote')),
				)),
			)))),
			$form,
			$sem('form', array('action' => 'comment_watch_form'), array($sem('button', array('action' => 'comment_watch'), array(), array('text' => 'Watch')))),
			$sem('form', array('action' => 'comment_allow_new_form'), array($sem('button', array('action' => 'comment_close'), array(), array('text' => 'Close')))),
			$sem('div', array('section' => 'comment_watchers')),
			$sem('div', array('section' => 'comment_pagination')),
			$node('content', array('contentType' => 'link', 'text' => 'Sign in', 'href' => '#', '_bindings' => array('href' => '__login_url')), array()),
		)),
	));
}

function comments_recent_test_draw_rows($over = array())
{
	$row = array('id' => '5', 'name' => '^^__comment_edit_url^^ <b>Ann</b>', 'message' => 'Hello ^^__comments_count^^', 'published' => '1', 'publish_date_and_time' => '0000-00-00 00:00:00', 'featured' => '0', 'created_timestamp' => '1000');
	$flags = pg_comments_widget_row_flags($row, false, array('is_staff' => false, 'sees_pending' => false, 'own_ids' => array(), 'rating_on' => false), array('depth' => 0))
		+ pg_comments_recent_row_flags('record');
	$values = pg_comments_recent_link_values(5, '/topic?r=AB', '^^__login_url^^ <i>Topic</i>', 'Reply')
		+ pg_comments_widget_row_values($row, $flags, array('is_staff' => false, 'sees_pending' => false, 'show_date' => false, 'label_lower' => 'reply', 'badge_label' => ''));

	return array_merge(array(array('id' => 5, 'flags' => $flags, 'values' => $values, 'datetime' => '', 'depth' => 0)), $over);
}

function comments_recent_test_draw_data($over = array())
{
	return $over + array(
		'recent' => true,
		'default_tree' => function_exists('pg_comments_recent_default_tree') ? pg_comments_recent_default_tree() : array(),
		'widget_id' => 8,
		'thread_flags' => pg_comments_recent_flags(array('count' => 1, 'more' => false, 'signed_in' => false, 'staff' => false)),
		'thread_values' => array('__comments_count' => '1'),
		'rows' => comments_recent_test_draw_rows(),
		'form' => array('can_comment' => false),
		'controls' => array('can_reply' => false, 'can_watch' => false, 'is_staff' => false, 'share_copied' => 'Link copied.', 'share_prompt' => 'Copy the link to this comment:'),
		'sections' => array(),
		'slots' => array(),
		'edit_chips' => false,
	);
}

function test_comments_recent_draw()
{
	if (!comments_recent_test_has(array('pg_comments_recent_flags', 'pg_comments_recent_link_values', 'pg_comments_drop_mode_bindings', 'pg_comments_recent_default_tree'))) {
		return;
	}

	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array('add_comment' => array(3 => array('message' => array('error' => true, 'error_message' => 'A comment is required.'))))));

	$tree = comments_recent_test_hostile_tree();
	pg_comments_drop_mode_bindings($tree, 'recent');
	_pg_inject_messages_node($tree, 'comments_recent');
	$html = pg_comments_widget_draw($tree, comments_recent_test_draw_data());

	pg_assert_false(strpos($html, 'software_comments'), 'no thread anchor');
	pg_assert_false(strpos($html, 'id="c-'), 'no comment anchor: the comment is on another page');
	pg_assert_false(strpos($html, 'data-pg-comment-depth'), 'no depth');
	foreach (array('<form', '<input', '<textarea', 'name="token"', 'captcha', 'Your words', 'Send', 'Watch', 'Close', 'Reply<', 'Sign in', 'pg-sw-pagination') as $needle) {
		pg_assert_false(strpos($html, $needle), 'nothing of a form or a thread control: ' . $needle);
	}
	foreach (array('You can comment', 'Open thread', 'Replying now', 'class="quote"') as $needle) {
		pg_assert_false(strpos($html, $needle), 'thread flags and tokens are off: ' . $needle);
	}
	pg_assert_false(strpos($html, 'Nothing yet'), 'with a comment, no empty note');
	pg_assert_contains('&#94;&#94;__login_url&#94;&#94; &lt;i&gt;Topic&lt;/i&gt;', $html, 'the title escaped once');
	pg_assert_contains('&#94;&#94;__comment_edit_url&#94;&#94; &lt;b&gt;Ann&lt;/b&gt;', $html, 'the name escaped once');
	pg_assert_contains('Hello &#94;&#94;__comments_count&#94;&#94;', $html, 'the excerpt escaped once');
	pg_assert_false(strpos($html, '^^'), 'no token left');
	pg_assert_contains('href="/topic?r=AB&amp;comments=all&amp;comment=5#c-5"', $html, 'the link to the comment on its page');
	pg_assert_contains('data-pg-comment-share', $html, 'sharing is wired');
	pg_assert_false(strpos($html, 'status'), 'an empty status leaves');
	pg_assert_false(strpos($html, 'A comment is required.'), 'the add_comment error is not printed');
	pg_assert_true(isset($_SESSION['software']['liveforms']['add_comment']), 'and is left for the thread');

	$empty = comments_recent_test_draw_data(array(
		'rows' => array(),
		'thread_flags' => pg_comments_recent_flags(array('count' => 0)),
		'thread_values' => array('__comments_count' => '0'),
	));
	pg_assert_contains('Nothing yet', pg_comments_widget_draw(comments_recent_test_hostile_tree(), $empty), 'no comments: the empty note');

	// The edit pencil only on a row with an edit address.
	$staff = comments_recent_test_draw_data(array('edit_chips' => true));
	$staff['rows'][0]['values']['__comment_edit_url'] = '/software/edit_comment.php?id=5';
	$second = $staff['rows'][0];
	$second['id'] = 6;
	$second['values']['__comment_edit_url'] = '';
	$staff['rows'][] = $second;
	$chips = pg_comments_widget_draw(comments_recent_test_hostile_tree(), $staff);
	pg_assert_same(1, substr_count($chips, 'class="pg-edit-chip'), 'one pencil');
	pg_assert_contains('edit_comment.php?id=5', $chips, 'for the row staff may edit');

	// A layout without a list takes the default one.
	$bare = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array(), 'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'p', 'text' => 'Latest'), 'children' => array()))),
	));
	$fallback = pg_comments_widget_draw($bare, comments_recent_test_draw_data());
	pg_assert_contains('<div class="pg-comments-list list-group list-group-flush">', $fallback, 'the default list');
	pg_assert_contains('list-group-item', $fallback, 'with the default card');
	pg_assert_true(strpos($fallback, 'pg-comments-list') < strpos($fallback, 'Latest'), 'in front of the rest');

	// The thread's draw is as before.
	$thread = comments_recent_test_draw_data(array('recent' => false));
	unset($thread['default_tree']);
	pg_assert_same(0, strpos(pg_comments_widget_draw(pg_comments_default_tree(), $thread), '<a id="software_comments"></a>'), 'the thread keeps its anchor');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// -- the default layout in three places ----------------------------------------

// Each binding object of a starter block, as sorted key:value pairs, and
// 'messages' for a Messages node, in source order.
function comments_recent_test_js_bindings($block)
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

// The same, walking a PHP tree depth first; with $key, that prop instead.
function comments_recent_test_php_walk($node, &$out, $key = '')
{
	// A default part: the starter calls its editor twin, which
	// tests/widget_default_parts_test.php compares with the server's.
	if (!is_array($node) || (isset($node['props']['cssClass']) && $node['props']['cssClass'] === 'pg-comment-stars')) {
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
		comments_recent_test_php_walk($child, $out, $key);
	}
}

// A case block of _buildStarterTree() in style_designer.js: from its case
// line to the next case or the default.
function comments_recent_test_starter_block($kind)
{
	$js = comments_recent_test_source('assets/js/style_designer.js');
	$start = strpos($js, "\n            case '" . $kind . "': {");
	if ($start === false) {
		return '';
	}

	$rest = substr($js, $start + 1);
	$next = preg_match("/\n            (?:case '|default: \\{)/", $rest, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;

	return ($next === false) ? '' : substr($rest, 0, $next);
}

function test_comments_recent_default_tree_matches_starter()
{
	if (!comments_recent_test_has('pg_comments_recent_default_tree')) {
		return;
	}

	$block = comments_recent_test_starter_block('comments_recent');
	pg_assert_true($block !== '', 'the starter is found');
	pg_assert_same(1, substr_count($block, "case '"), 'one case in the block');

	$js = comments_recent_test_js_bindings($block);
	$php = array();
	comments_recent_test_php_walk(pg_comments_recent_default_tree(), $php);
	pg_assert_true(count($js) >= 8, 'the starter binds its elements: ' . count($js));
	pg_assert_same($js, $php, 'the same bindings in the same order');
	pg_assert_false(in_array('messages', $php, true), 'no Messages node');

	foreach ($php as $binding) {
		pg_assert_false((bool) preg_match('/\b(action|value|section):/', $binding), 'no form, control or section: ' . $binding);
	}
	foreach (array('href:__comment_url,text:__comment_thread_title', 'text:__comment_excerpt', 'text:__comment_name', 'eo_visible_if:no_comments') as $binding) {
		pg_assert_true(in_array($binding, $php, true), 'the default layout binds ' . $binding);
	}

	preg_match_all("/cssClass:\\s*'([^']*)'/", $block, $classes);
	$php_classes = array();
	comments_recent_test_php_walk(pg_comments_recent_default_tree(), $php_classes, 'cssClass');
	pg_assert_same($classes[1], $php_classes, 'the same classes in the same order');

	preg_match_all("/\\btag:\\s*'([^']*)'/", $block, $tags);
	$php_tags = array();
	comments_recent_test_php_walk(pg_comments_recent_default_tree(), $php_tags, 'tag');
	pg_assert_same($tags[1], $php_tags, 'the same elements in the same order');

	pg_assert_false(strpos($block, 'style:'), 'no inline style');
	pg_assert_false(strpos(json_encode(pg_comments_recent_default_tree()), '"style"'), 'none in the PHP tree either');

	$split = _split_widget_tree(pg_comments_recent_default_tree());
	pg_assert_true(is_array($split['loop_children']) && (count($split['loop_children']) === 1), 'one comment card repeats');
	pg_assert_contains('list-group-item', $split['loop_children'][0]['props']['cssClass'], 'a list item, as the fallback list expects');

	// The thread's starter block ends where the next case starts.
	$thread = comments_recent_test_starter_block('comments');
	pg_assert_same(1, substr_count($thread, "case '"), 'the thread\'s block holds its own case only');
	pg_assert_false(strpos($thread, '__comment_thread_title'), 'not the list\'s');
}

// -- the wiring ----------------------------------------------------------------

function test_comments_recent_wiring()
{
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments_recent.php');", comments_recent_test_source('functions.php'), 'loaded with the other widgets');
	$functions = comments_recent_test_source('functions.php');
	pg_assert_true(strpos($functions, 'widgets_comments.php') < strpos($functions, 'widgets_comments_recent.php'), 'right after the thread\'s module');

	$module = comments_recent_test_source('includes/fn/widgets_comments_recent.php');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR'))", $module, 'gated');
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $module, 'the Pinegrap header');

	$thread = comments_recent_test_php_body('_render_system_widget_comments');
	$branch = strpos($thread, "pg_sw_widget_kind(\$cfg) === 'comments_recent'");
	pg_assert_true($branch !== false, 'the thread renderer hands a list over');
	foreach (array('$page_id =', 'pg_comments_widget_classic_thread(', 'pg_comments_widget_claim(', 'new liveform') as $later) {
		$at = strpos($thread, $later);
		pg_assert_true(($branch !== false) && ($at !== false) && ($branch < $at), 'before ' . $later);
	}
	$drop = strpos($thread, "pg_comments_drop_mode_bindings(\$tree, 'thread');");
	$inject = strpos($thread, "_pg_inject_messages_node(\$tree, 'add_comment');");
	pg_assert_true(($drop !== false) && ($inject !== false) && ($drop < $inject), 'the thread drops the list\'s tokens before its Messages node');

	$recent = comments_recent_test_php_body('_render_system_widget_comments_recent');
	pg_assert_true($recent !== '', 'the list renderer');
	foreach (array('new liveform', 'get_token_field(', 'get_captcha_fields(', 'pg_comments_widget_claim(', 'remove_form(', 'added_comments', 'pg_comments_stamp_form_messages(') as $never) {
		pg_assert_false(strpos($recent, $never), 'the list never calls ' . $never);
	}
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'comments_recent');", $recent, 'its Messages node has a name nothing posts to');
	pg_assert_contains("pg_comments_drop_mode_bindings(\$tree, 'recent');", $recent, 'it drops the thread\'s tokens');
	pg_assert_contains("UPDATE_SEARCH_INDEX", $recent, 'it prints nothing for the search index');
	pg_assert_contains("'recent' => true", $recent, 'it draws as a list');
	pg_assert_contains("'can_comment' => false", $recent, 'with no form');
	pg_assert_false(strpos($module, "\$cfg['filters']"), 'a form list\'s filters are never read in this mode');
	pg_assert_contains("\$cfg['recent_filters']", comments_recent_test_php_body('pg_comments_recent_options'), 'the list\'s own key');
	pg_assert_contains("pg_comments_recent_sources(\$options['page_ids'], \$options['show'], \$viewer, 'db_items', \$options['filters'])", $recent, 'the renderer hands the filters to the sources');
	$sources = comments_recent_test_php_body('pg_comments_recent_sources');
	pg_assert_contains("pg_sw_list_record_filter_clause(\$form_id, array('filters' => \$list_filters), \$view_cfg, \$page_id, pg_sw_filter_request())", $sources, 'the one record source\'s gate holds the list\'s filters after the view\'s');
	pg_assert_contains("pg_sw_filters_compare_viewer(array_merge(pg_sw_record_view_filters(\$view_cfg), \$list_filters))", $sources, 'a list filter on the viewer counts as the view\'s would');
	pg_assert_contains("'list_filters' => \$filtered", $sources, 'the decision knows the list is filtered');
	pg_assert_contains("'sources' => count(\$ids)", $sources, 'and over how many pages');
	pg_assert_false(strpos(comments_recent_test_php_body('pg_comments_recent_read'), 'count($records) >= $k'), 'no candidate record is dropped after it is read');

	pg_assert_contains('pg_sw_widget_kind(', comments_recent_test_php_body('pg_comments_component_census'), 'the census records the kind');
	pg_assert_contains('pg_sw_widget_kind(', comments_recent_test_php_body('pg_design_template_starter_kinds'), 'a template starter by kind');
	pg_assert_contains('pg_sw_widget_kind(', comments_recent_test_php_body('pg_design_template_install'), 'and its install');
	$preview = comments_recent_test_php_body('pg_designer_preview_widgets');
	pg_assert_contains('pg_sw_widget_kind(', $preview, 'the Preview reads the kind');
	pg_assert_contains("elseif (\$type === 'comments' && \$page_id > 0)", $preview, 'the record of the page only for a thread');

	pg_assert_same('Recent Comments', pg_sw_type_labels()['comments_recent'], 'named for the panel');
	$labels = array_keys(pg_sw_type_labels());
	pg_assert_same(array_search('comments', $labels, true) + 1, array_search('comments_recent', $labels, true), 'right after the thread');
	pg_assert_contains('pg_sw_widget_kind(', comments_recent_test_source('includes/designer_screen.php'), 'the component list names the kind');

	// check_bindings sees the new names.
	foreach (array("'__comment_thread_title' =>", "'__comment_thread_url' =>", "'__comment_label' =>") as $needle) {
		pg_assert_contains($needle, $module, 'a literal key: ' . $needle);
	}
	pg_assert_contains("'__comment_excerpt' =>", comments_recent_test_php_body('pg_comments_widget_row_values'), 'the excerpt in the row values');
	pg_assert_contains('$comments_thread_flags = array(', comments_recent_test_php_body('pg_comments_recent_flags'), 'the list flags as the literal the draw passes');
	pg_assert_contains('$comments_row_flags = array(', comments_recent_test_php_body('pg_comments_recent_row_flags'), 'the row flags too');
}

// The census, the save warnings and the conversion count only thread
// widgets: a recent list on a topic page is no second thread.
function test_comments_recent_census_ignores_lists()
{
	if (!comments_recent_test_has('pg_comments_page_census_facts')) {
		return;
	}

	$components = array(
		5 => array('type' => 'comments', 'census' => array('refs' => array(), 'blocks' => 0)),
		6 => array('type' => 'comments_recent', 'census' => array('refs' => array(), 'blocks' => 0)),
	);
	$facts = pg_comments_page_census_facts(array('refs' => array(5 => 1, 6 => 2), 'blocks' => 1), $components);
	pg_assert_same(1, $facts['widgets'], 'the list is not counted');
	pg_assert_same(array('block'), pg_comments_save_warning_codes($facts + array('comments_on' => true)), 'no "two widgets" warning');

	$only_list = pg_comments_page_census_facts(array('refs' => array(6 => 1), 'blocks' => 1), $components);
	pg_assert_same(array(), pg_comments_save_warning_codes($only_list + array('comments_on' => false)), 'a page with only a list: no warning');
}

// The source pages are a list of page ids, so every path that turns a page
// into a tab key and back walks the list: a template's export, its install,
// Publish and the Preview of an unsaved tab.
function test_comments_recent_page_ids_tab_round_trip()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	if (!comments_recent_test_has(array('pg_dtc_tokenize_config', '_pg_tpl_fill', 'pg_designer_resolve_tab_values', '_pg_preview_drop_tab_refs'))) {
		return;
	}

	$cfg = array('regionType' => 'comments', 'mode' => 'recent', 'recent_page_ids' => array(31, 99, '40'));
	$exported = pg_dtc_tokenize_config($cfg, array('page_ids' => array(31 => 'topic', 40 => 'doc')));
	pg_assert_same(array('{{tab:topic}}', 99, '{{tab:doc}}'), $exported['recent_page_ids'], 'export: the design\'s pages by their tab, another page as it is');

	$installed = _pg_tpl_fill($exported, array('tabs' => array('topic' => 'tpl_topic')));
	pg_assert_same(array('tab:tpl_topic', 99, '0'), $installed['recent_page_ids'], 'install: the editor\'s tab key, a tab the template lacks as 0');
	pg_assert_same(array(99), pg_comments_recent_options($installed)['page_ids'], 'before Publish only the page with an id is read');

	$published = pg_designer_resolve_tab_values($installed, array('tpl_topic' => 31));
	pg_assert_same(array(31, 99, '0'), $published['recent_page_ids'], 'Publish: each tab in the list becomes its id');
	pg_assert_same(array(31, 99), pg_comments_recent_options($published)['page_ids'], 'and is read');

	$preview = _pg_preview_drop_tab_refs(array('recent_page_ids' => array('tab:new', 12)));
	pg_assert_same(array(0, 12), $preview['recent_page_ids'], 'the Preview drops a tab not saved yet');
}

// A list of recent comments draws nothing in an e-mail, also in a comment
// e-mail that names a comment: the thread renderer hands a list over before
// its e-mail branch. Reached first, that branch drew the named comment in
// the list's layout and claimed the e-mail page, so the page's own thread
// widget drew nothing and the e-mail lost its fixed markup.
function test_comments_recent_nothing_in_a_comment_email()
{
	if (!comments_recent_test_has(array('_render_system_widget_comments', '_render_system_widget_comments_recent', 'pg_comments_recent_default_tree', 'pg_comments_widget_pages', 'pg_sw_render_context'))) {
		return;
	}

	$thread = comments_recent_test_php_body('_render_system_widget_comments');
	$list = strpos($thread, "pg_sw_widget_kind(\$cfg) === 'comments_recent'");
	$email = strpos($thread, "if (!empty(\$ctx['email'])) {");

	// Out of order the e-mail branch would read the comment from the
	// database, which the unit tests do not have.
	if (!pg_assert_true(($list !== false) && ($email !== false) && ($list < $email), 'a list is handed over before the e-mail branch')) {
		return;
	}

	$tree = json_encode(pg_comments_recent_default_tree());
	$cfg = array('regionType' => 'comments', 'mode' => 'recent', 'recent_page_ids' => array(5));
	$found = pg_comments_widget_pages(array());
	$prev = pg_sw_render_context(array('email' => true, 'page_id' => 9, 'comment_id' => 12));
	$html = _render_system_widget_comments($tree, 4, $cfg, 'preview');
	$record = pg_comments_widget_pages($found);
	pg_sw_render_context($prev);

	pg_assert_same('', $html, 'a list in a comment e-mail draws nothing');
	pg_assert_same(array(), $record, 'and claims nothing: a thread widget on the e-mail page still draws the comment');
}
