<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/comments.php: which thread a comment posted to a page
 * may join (page type x record view widget x posted item type), the record a
 * record view page holds a post to, the product, product group or calendar
 * event a classic page holds a post to, and that add_comment.php asks before
 * it stores anything and sends a refused post back to its form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The decision table. Each row: page type, record view widget on the page,
// posted item type, expected refusal ('' = accepted). The posted id is 12, the
// widget shows record 12 to this visitor and the page shows item 12, so only
// the type decides. 'other' is an item type no page names.
function test_comments_thread_decision_table()
{
	$types = array('', 'submitted_form', 'product', 'product_group', 'calendar_event', 'other');
	$expected = array(
		// A legacy form item view page takes a submitted form only, widget or not.
		'form item view|0' => array('' => 'page_type', 'submitted_form' => '', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		'form item view|1' => array('' => 'page_type', 'submitted_form' => '', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		// A page with a record view widget takes its record only.
		'standard|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type', 'other' => 'item_type'),
		'catalog|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type', 'other' => 'item_type'),
		'catalog detail|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type', 'other' => 'item_type'),
		'calendar event view|1' => array('' => 'item_type', 'submitted_form' => '', 'product' => 'item_type', 'product_group' => 'item_type', 'calendar_event' => 'item_type', 'other' => 'item_type'),
		// Any other page takes the page itself and the item types its own
		// comment block names (get_page_content.php), nothing else.
		'standard|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		'calendar view|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		'catalog|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => 'page_type', 'product_group' => '', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		'catalog detail|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => '', 'product_group' => '', 'calendar_event' => 'page_type', 'other' => 'page_type'),
		'calendar event view|0' => array('' => '', 'submitted_form' => 'page_type', 'product' => 'page_type', 'product_group' => 'page_type', 'calendar_event' => '', 'other' => 'page_type'),
	);

	foreach ($expected as $key => $row) {
		list($page_type, $widget) = explode('|', $key);

		foreach ($types as $type) {
			$decision = pg_comment_thread_decision($page_type, $widget === '1', $type, '12', 12, true, true);
			$label = $page_type . ($widget === '1' ? ' with' : ' without') . ' a record view, item type "' . $type . '"';

			pg_assert_same($row[$type], $decision['refusal'], $label);
			pg_assert_same($row[$type] === '', $decision['accept'], $label . ' (accept)');
		}
	}
}

// The symptom: a comment posted with an empty item type to a designed record
// page landed on the page's own thread, which that page never shows.
function test_comments_thread_page_thread_refused_on_record_page()
{
	$decision = pg_comment_thread_decision('standard', true, '', '0', 0, false);

	pg_assert_false($decision['accept'], 'the page itself');
	pg_assert_same('item_type', $decision['refusal']);

	$decision = pg_comment_thread_decision('standard', true, '', '12', 12, true);

	pg_assert_same('item_type', $decision['refusal'], 'even with the record id beside it');
}

// On a record view page the post joins the record the widget shows, by the
// record's own id; anything else is refused as 'record'.
function test_comments_thread_record_rules()
{
	$decision = pg_comment_thread_decision('standard', true, 'submitted_form', '12', 12, true);

	pg_assert_true($decision['accept'], 'the record the widget shows');
	pg_assert_same('submitted_form', $decision['item_type']);
	pg_assert_same(12, $decision['item_id'], 'stored under the record id');

	$refused = array(
		'a record the visitor may not see' => array('12', 12, false),
		'a record of another form, or none' => array('12', 0, false),
		'no record id' => array('0', 0, false),
		'an empty record id' => array('', 0, false),
		'a negative id' => array('-12', 0, false),
		'an id the widget did not resolve' => array('12', 13, true),
		'an id that is not a value' => array(array('12'), 12, true),
	);

	foreach ($refused as $label => $case) {
		$decision = pg_comment_thread_decision('standard', true, 'submitted_form', $case[0], $case[1], $case[2]);

		pg_assert_false($decision['accept'], $label);
		pg_assert_same('record', $decision['refusal'], $label);
	}

	// A record id read from the database is a string.
	$decision = pg_comment_thread_decision('standard', true, 'submitted_form', '12', '12', true);

	pg_assert_true($decision['accept'], 'a record id as a string');
	pg_assert_same(12, $decision['item_id']);
}

// The item types each classic page type's comment block names, as
// get_page_content.php picks them: a catalog page a product group (the one in
// the address, the page's own or the top-level one), a catalog detail page the
// product or product group in the address, a calendar event view page the
// event in ?id=. Every other page type comments on the page itself.
function test_comments_thread_classic_item_types()
{
	pg_assert_same(array('product_group'), pg_comment_classic_item_types('catalog'));
	pg_assert_same(array('product', 'product_group'), pg_comment_classic_item_types('catalog detail'));
	pg_assert_same(array('calendar_event'), pg_comment_classic_item_types('calendar event view'));

	foreach (array('standard', 'calendar view', 'form item view', 'form list view', 'search results', '', 'Catalog') as $page_type) {
		pg_assert_same(array(), pg_comment_classic_item_types($page_type), 'page type "' . $page_type . '"');
	}

	pg_assert_same(array(), pg_comment_classic_item_types(array('catalog')), 'a page type that is not a value');
	pg_assert_same(array(), pg_comment_classic_item_types(null), 'no page type');
}

// On a classic page a product, product group or calendar event of the right
// type joins only an item the page shows ($item_shown, looked up by
// pg_comment_thread_verify()); it is stored under the item's id.
function test_comments_thread_classic_item_rules()
{
	$decision = pg_comment_thread_decision('catalog detail', false, 'product', '7', 0, false, true);

	pg_assert_true($decision['accept'], 'a product the page shows');
	pg_assert_same('', $decision['refusal']);
	pg_assert_same('product', $decision['item_type']);
	pg_assert_same(7, $decision['item_id'], 'stored under the item id');

	$decision = pg_comment_thread_decision('catalog', false, 'product_group', '07', 0, false, true);

	pg_assert_true($decision['accept'], 'a product group the catalog page shows');
	pg_assert_same(7, $decision['item_id'], 'the id the database compares, not the posted text');

	$decision = pg_comment_thread_decision('calendar event view', false, 'calendar_event', 31, 0, false, true);

	pg_assert_true($decision['accept'], 'an event the view shows');
	pg_assert_same('calendar_event', $decision['item_type']);
	pg_assert_same(31, $decision['item_id']);

	$refused = array(
		'a product the page does not show' => array('catalog detail', 'product', '7', false),
		'a product group the page does not show' => array('catalog', 'product_group', '7', false),
		'an event the view does not show' => array('calendar event view', 'calendar_event', '31', false),
		'no item id' => array('catalog detail', 'product', '0', true),
		'an empty item id' => array('catalog', 'product_group', '', true),
		'a negative id' => array('catalog detail', 'product_group', '-7', true),
		'an id that is no number' => array('calendar event view', 'calendar_event', 'abc', true),
		'an id that is not a value' => array('catalog detail', 'product', array('7'), true),
	);

	foreach ($refused as $label => $case) {
		$decision = pg_comment_thread_decision($case[0], false, $case[1], $case[2], 0, false, $case[3]);

		pg_assert_false($decision['accept'], $label);
		pg_assert_same('item', $decision['refusal'], $label);
	}

	// The type is decided first: an item the page type does not name is
	// refused for its type, whatever the lookup said.
	pg_assert_same('page_type', pg_comment_thread_decision('calendar event view', false, 'product', '7', 0, false, true)['refusal'], 'a product on a calendar event view page');
	pg_assert_same('page_type', pg_comment_thread_decision('standard', false, 'product', '7', 0, false, true)['refusal'], 'a product on a page about itself');
	pg_assert_same('page_type', pg_comment_thread_decision('catalog', false, 'product', '7', 0, false, true)['refusal'], 'a catalog page names product groups only');

	// A record view page does not take an item, shown or not.
	pg_assert_same('item_type', pg_comment_thread_decision('catalog detail', true, 'product', '7', 12, true, true)['refusal'], 'a record view on a catalog detail page');
}

// The page's own thread on a classic page, and a legacy form item view
// page's record, keep the posted values as they came.
function test_comments_thread_classic_keeps_posted_values()
{
	$decision = pg_comment_thread_decision('catalog detail', false, '', '0', 0, false);

	pg_assert_true($decision['accept'], 'a catalog detail page opened without an item');
	pg_assert_same('', $decision['item_type']);
	pg_assert_same('0', $decision['item_id'], 'the posted id, unchanged');

	// A post without the field is about the page itself, as before.
	$decision = pg_comment_thread_decision('calendar event view', false, null, null, 0, false);

	pg_assert_true($decision['accept'], 'no item type posted');
	pg_assert_same(null, $decision['item_type']);

	$decision = pg_comment_thread_decision('standard', false, '', '0', 0, false);

	pg_assert_same('', $decision['item_type'], 'the page itself');
	pg_assert_same('0', $decision['item_id']);

	// The legacy page's own row decides who may see the record; the widget
	// numbers are not asked there.
	$decision = pg_comment_thread_decision('form item view', false, 'submitted_form', '31', 0, false);

	pg_assert_true($decision['accept'], 'a legacy form item view page');
	pg_assert_same('31', $decision['item_id']);
}

// A type that is not a value is no submitted form.
function test_comments_thread_non_scalar_type()
{
	pg_assert_same('item_type', pg_comment_thread_decision('standard', true, array('submitted_form'), '12', 12, true)['refusal'], 'on a record view page');
	pg_assert_same('page_type', pg_comment_thread_decision('form item view', false, array('submitted_form'), '12', 0, false)['refusal'], 'on a legacy form item view page');
	pg_assert_same('page_type', pg_comment_thread_decision('catalog detail', false, array('product'), '12', 0, false, true)['refusal'], 'on a catalog detail page');
}

// Without a page there is no widget to ask, and no query is run.
function test_comments_thread_verify_without_page()
{
	$verify = pg_comment_thread_verify(0, 'standard', '', '0');

	pg_assert_true($verify['accept']);
	pg_assert_same(null, $verify['record']);

	$verify = pg_comment_thread_verify(0, 'form item view', '', '0');

	pg_assert_same('page_type', $verify['refusal']);
	pg_assert_same(null, $verify['record']);

	// No page shows no item: refused without a lookup.
	$verify = pg_comment_thread_verify(0, 'catalog detail', 'product', '7');

	pg_assert_same('item', $verify['refusal'], 'a product without a page');
	pg_assert_same(null, $verify['record']);

	pg_assert_same('item', pg_comment_thread_verify(0, 'calendar event view', 'calendar_event', '31')['refusal'], 'an event without a page');
	pg_assert_same('page_type', pg_comment_thread_verify(0, 'standard', 'product', '7')['refusal'], 'a product on a page about itself');

	// The lookup answers false without a page, an id or a type it knows,
	// before any query.
	pg_assert_false(pg_comment_classic_item_shown(0, 'product', 7), 'no page');
	pg_assert_false(pg_comment_classic_item_shown(5, 'product', 0), 'no id');
	pg_assert_false(pg_comment_classic_item_shown(5, 'other', 7), 'an item type no page names');
	pg_assert_false(pg_comment_classic_item_shown(5, array('product'), 7), 'a type that is not a value');
}

// add_comment.php asks before it validates and stores, and answers a refusal
// only after the page's own gates (comments on, new comments allowed, login,
// view access): a visitor who may not comment on the page gets the same
// answer whatever record id was posted. Another item type on a record view
// page goes back to its form with what was typed, without the posted thread.
// A record the page does not show gets an error page with the form state
// dropped: that record's page draws no comment form to carry the message,
// and the typed text must not turn up on another record's form.
function test_comments_thread_add_comment_wiring()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	$verify = strpos($add, 'pg_comment_thread_verify($page_id, $page_type, $item_type, $item_id)');
	$gates = array(
		'comments on' => strpos($add, 'if ($comments == 0)'),
		'new comments allowed' => strpos($add, 'if ($allow_new_comments == FALSE)'),
		'login' => strpos($add, '($comments_require_login_to_comment == 1)'),
		'view access' => strpos($add, 'if (check_view_access($folder_id) == false)'),
	);
	$type_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'item_type')");
	$record_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'record')");
	$page_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'page_type')");
	$insert = strpos($add, 'INSERT INTO comments');
	$found = ($verify !== false) && ($type_refusal !== false) && ($record_refusal !== false) && ($page_refusal !== false) && ($insert !== false);

	pg_assert_true($found, 'add_comment.php asks and answers each refusal');
	pg_assert_same(1, substr_count($add, "\$comment_thread['refusal'] === 'item_type'"), 'the item type refusal is answered in one place');
	pg_assert_same(1, substr_count($add, "\$comment_thread['refusal'] === 'record'"), 'the record refusal too');

	foreach ($gates as $label => $gate) {
		pg_assert_true($found && ($gate !== false) && ($verify < $gate) && ($gate < $type_refusal) && ($gate < $record_refusal), 'a refusal is answered after the gate: ' . $label);
	}

	pg_assert_true($found && ($type_refusal < $record_refusal) && ($record_refusal < $page_refusal) && ($page_refusal < $insert), 'before anything is stored');

	if (!$found || ($record_refusal < $type_refusal) || ($page_refusal < $record_refusal)) {
		return;
	}

	$type_block = substr($add, $type_refusal, $record_refusal - $type_refusal);

	pg_assert_contains('$liveform->add_error(', $type_block, 'another item type: a form error');
	pg_assert_contains("\$liveform->set_field('item_type', array())", $type_block, 'the posted type is dropped from the form state');
	pg_assert_contains("\$liveform->set_field('item_id', array())", $type_block, 'the posted id too');
	pg_assert_contains("header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(", $type_block, 'back to the form');
	pg_assert_contains('exit();', $type_block, 'and nothing after it');

	$record_block = substr($add, $record_refusal, $page_refusal - $record_refusal);
	$remove = strpos($record_block, '$liveform->remove_form();');
	$error = strpos($record_block, 'output_error(');

	pg_assert_true(($remove !== false) && ($error !== false) && ($remove < $error), 'a record the page does not show: the form state dropped, then an error page');
	pg_assert_false(strpos($record_block, 'add_error(') !== false, 'no form error a page without a form would keep');
	pg_assert_false(strpos($record_block, 'header(') !== false, 'no redirect to a page without a form');

	pg_assert_false(strpos($add, "\$comment_record['visible'] == false") !== false, 'record visibility is decided by pg_comment_thread_verify() only');
	pg_assert_false(strpos($add, "(\$item_type == 'submitted_form') && function_exists('pg_sw_record_comment_context')") !== false, 'the widget is no longer asked only for a posted submitted form');

	// A product, product group or calendar event the classic page does not
	// show: answered after the gates and before the review gate and the
	// INSERT, with the form state dropped before the error page, so the
	// refused item and the typed text do not reach the form of the next item.
	$item_refusal = strpos($add, "if (\$comment_thread['refusal'] === 'item')");
	$review_gate = strpos($add, 'pg_review_gate_applies($item_type, $item_id)');

	pg_assert_same(1, substr_count($add, "\$comment_thread['refusal'] === 'item'"), 'the item refusal is answered in one place');
	pg_assert_true(($item_refusal !== false) && ($review_gate !== false) && ($page_refusal < $item_refusal) && ($item_refusal < $review_gate) && ($item_refusal < $insert), 'the item refusal after the other refusals, before the review gate and the INSERT');

	foreach ($gates as $label => $gate) {
		pg_assert_true(($item_refusal !== false) && ($gate !== false) && ($gate < $item_refusal), 'the item refusal is answered after the gate: ' . $label);
	}

	if (($item_refusal === false) || ($review_gate === false) || ($review_gate < $item_refusal)) {
		return;
	}

	$item_block = substr($add, $item_refusal, $review_gate - $item_refusal);
	$item_block = substr($item_block, 0, (int) strpos($item_block, "\n}\n") + 2);
	$remove = strpos($item_block, '$liveform->remove_form();');
	$error = strpos($item_block, 'output_error(');

	pg_assert_true(($remove !== false) && ($error !== false) && ($remove < $error), 'an item the page does not show: the form state dropped, then an error page');
	pg_assert_false(strpos($item_block, 'add_error(') !== false, 'no form error that would come back with the refused item');
	pg_assert_false(strpos($item_block, 'header(') !== false, 'no redirect back to the form');
}

// A member's address is a value like any other: validate_email_address()
// accepts an apostrophe, and USER_EMAIL_ADDRESS holds the address as
// stored. Quoted into SQL raw, it broke the watcher queries of
// add_or_remove_watcher.php and the classic comments block ("Query
// failed."), and a crafted address turned the self-remove DELETE into one
// that removed every e-mail watcher of the thread. No PHP file under
// pinegrap/ quotes the constant into a string without escaping it.
function test_comments_watcher_queries_escape_the_address()
{
	$raw = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = $file->getPathname();

		if (substr($path, -4) !== '.php') {
			continue;
		}

		$source = (string) file_get_contents($path);

		if ((strpos($source, 'USER_EMAIL_ADDRESS') === false) || !preg_match_all('/\'"\s*\.\s*USER_EMAIL_ADDRESS\s*\.\s*"\'/', $source, $found, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($found[0] as $hit) {
			$raw[] = substr($path, strlen(PG_FUNCTIONS_DIR) + 1) . ':' . (substr_count($source, "\n", 0, $hit[1]) + 1);
		}
	}

	pg_assert_same(array(), $raw, 'USER_EMAIL_ADDRESS quoted into SQL without escape()');
	// The scan above passes as well when the handler stops matching by
	// address at all, so the escaped match must still be there.
	pg_assert_true((bool) preg_match('/email_address\s*=\s*\'"\s*\.\s*(?:e|escape)\(\s*USER_EMAIL_ADDRESS\s*\)\s*\.\s*"\'/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_or_remove_watcher.php')), 'the handler still matches the member by address');
}
