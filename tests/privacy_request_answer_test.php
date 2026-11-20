<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Prepare the answer", the Answer tab of a privacy request's
 * drawer (includes/fn/privacy_requests.php): the state of its four numbered
 * steps, what the gather step lists for an export and an erase dry run, the
 * kind's answer text and the stepper's markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

// Until staff checks who asks, only the first step is open and nothing is gathered.
function test_privacy_request_answer_steps_before_the_check()
{
	$out = pg_privacy_request_answer_steps(array('status' => 'open', 'verified_at' => 0), null);

	pg_assert_same(array('check' => 'current', 'gather' => 'later', 'write' => 'later', 'send' => 'later'), $out['steps'], 'states');
	pg_assert_same('check', $out['open'], 'the check is open');
	pg_assert_false($out['can_send'], 'nothing to send yet');
}

// Checked and gathered: the gather step is open to read, writing is next.
function test_privacy_request_answer_steps_after_the_gather()
{
	$out = pg_privacy_request_answer_steps(array('status' => 'open', 'verified_at' => 5), array('status' => 'ok'));

	pg_assert_same(array('check' => 'done', 'gather' => 'done', 'write' => 'current', 'send' => 'later'), $out['steps'], 'states');
	pg_assert_same('gather', $out['open'], 'the gathered data is shown first');
	pg_assert_true($out['can_send'], 'the answer can go');

	$none = pg_privacy_request_answer_steps(array('status' => 'open', 'verified_at' => 5), array('status' => 'not_found'));
	pg_assert_true($none['can_send'], 'nothing found still gets an answer');
}

// A person the operator may not export or erase stops at the gather step.
function test_privacy_request_answer_steps_refused_gather()
{
	$out = pg_privacy_request_answer_steps(array('status' => 'open', 'verified_at' => 5), array('status' => 'refused'));

	pg_assert_same(array('check' => 'done', 'gather' => 'current', 'write' => 'later', 'send' => 'later'), $out['steps'], 'states');
	pg_assert_same('gather', $out['open'], 'the reason is shown');
	pg_assert_false($out['can_send'], 'no answer from this operator');
}

// An erasure gathers the erase dry run, every other kind the export.
function test_privacy_request_gather_mode()
{
	pg_assert_same('erase', pg_privacy_request_gather_mode('erasure'), 'erasure');

	foreach (array('information', 'correction', 'objection', 'other') as $kind) {
		pg_assert_same('export', pg_privacy_request_gather_mode($kind), $kind);
	}
}

// The export's sections with rows, in the pack's order, by their names.
function test_privacy_request_gather_lists_export()
{
	$sections = pg_privacy_export_sections();
	$lists = pg_privacy_request_gather_lists('export', array('counts' => array('orders' => 3, 'contact' => 1, 'sms' => 0)));

	pg_assert_same(array('found'), array_keys($lists), 'one list');
	pg_assert_same(array($sections['contact'] => 1, $sections['orders'] => 3), $lists['found'], 'empty sections left out, pack order');
}

// The dry run split into what is deleted and what is kept anonymised.
function test_privacy_request_gather_lists_erase()
{
	$labels = pg_privacy_erase_labels();
	$lists = pg_privacy_request_gather_lists('erase', array('tables' => array(
		'orders'   => array('action' => 'anonymise', 'rows' => 2),
		'wishlist_items' => array('action' => 'delete', 'rows' => 4),
		'contacts' => array('action' => 'delete', 'rows' => 1),
	)));

	pg_assert_same(array($labels['wishlist_items'] => 4, $labels['contacts'] => 1), $lists['deleted'], 'what goes');
	pg_assert_same(array($labels['orders'] => 2), $lists['kept'], 'what the law keeps');
}

// The information answer lists the records found and signs with the site.
function test_privacy_request_answer_text_information()
{
	$text = pg_privacy_request_answer_text('information', 'Ayşe Demir', 'Example Shop', array('found' => array('Orders' => 3, 'Contact' => 1)));

	pg_assert_same(0, strpos($text, 'Hello Ayşe Demir,'), 'greets by name');
	pg_assert_contains('- Orders: 3', $text, 'a line a record kind');
	pg_assert_contains('- Contact: 1', $text, 'every kind');
	pg_assert_same('Example Shop', substr($text, -strlen('Example Shop')), 'signed');

	$empty = pg_privacy_request_answer_text('information', '', 'Example Shop', array('found' => array()));
	pg_assert_same(0, strpos($empty, 'Hello,'), 'no name');
	pg_assert_contains('We keep no personal data about you.', $empty, 'nothing found');
}

// The erasure answer says what is kept only when something is.
function test_privacy_request_answer_text_erasure()
{
	$kept = pg_privacy_request_answer_text('erasure', '', 'Example Shop', array('deleted' => array('Contact' => 1), 'kept' => array('Orders' => 2)));
	$none = pg_privacy_request_answer_text('erasure', '', 'Example Shop', array('deleted' => array('Contact' => 1), 'kept' => array()));

	pg_assert_contains('We have erased your personal data.', $kept, 'erased');
	pg_assert_contains('- Orders: 2', $kept, 'what is kept');
	pg_assert_same(false, strpos($none, '- Orders'), 'no kept list when nothing is kept');

	$nothing = pg_privacy_request_answer_text('erasure', '', 'Example Shop', array('deleted' => array(), 'kept' => array()));
	pg_assert_same(false, strpos($nothing, 'We have erased your personal data.'), 'nothing found: nothing claimed erased');
}

// Every kind has its own text; an unknown kind falls back to the general one.
function test_privacy_request_answer_text_kinds()
{
	$texts = array();

	foreach (pg_privacy_request_kinds() as $kind) {
		$texts[$kind] = pg_privacy_request_answer_text($kind, '', 'S', array());
	}

	pg_assert_same(count($texts), count(array_unique($texts)), 'one text a kind');
	pg_assert_same($texts['other'], pg_privacy_request_answer_text('unknown', '', 'S', array()), 'fallback');
}

// The stepper: Bootstrap's ol.stepper.pg-stepper-cards; done steps green, the
// current one active and marked, later ones plain; one step open at a time.
function test_privacy_request_answer_stepper_markup()
{
	$html = pg_privacy_request_answer_stepper(
		array('check' => 'done', 'gather' => 'done', 'write' => 'current', 'send' => 'later'),
		'gather',
		array(
			'check'  => array('title' => 'Check who asks', 'line' => 'L1', 'body' => '<p>B1</p>'),
			'gather' => array('title' => 'Gather', 'line' => 'L2', 'body' => '<p>B2</p>'),
			'write'  => array('title' => 'Write the answer', 'line' => 'L3', 'body' => '<p>B3</p>'),
			'send'   => array('title' => 'Send', 'line' => 'L4', 'body' => '<p>B4</p>'),
		)
	);

	pg_assert_same(0, strpos($html, '<ol class="stepper pg-stepper-cards'), 'Bootstrap stepper with cards');
	pg_assert_same(2, substr_count($html, 'stepper-item active theme-success'), 'two done');
	pg_assert_same(1, substr_count($html, 'aria-current="step"'), 'one current');
	pg_assert_contains('<li class="stepper-item active" aria-current="step">', $html, 'the current one active');
	pg_assert_contains('<li class="stepper-item">', $html, 'later plain');
	pg_assert_same(4, substr_count($html, '<details name="privacy_answer_step"'), 'one open at a time');
	pg_assert_same(1, substr_count($html, ' open>'), 'one open');
	pg_assert_true(strpos($html, 'B2') > strpos($html, ' open>'), 'the open one is the gather step');
	pg_assert_same(false, strpos($html, '>1<'), 'the number is the counter, never written');
}

// History reads the columns audit_log has: user_id and username, never actor_name.
function test_privacy_request_history_query_columns()
{
	$sql = pg_privacy_request_history_query(7);

	pg_assert_contains('SELECT created_at, action, user_id, username, after_json FROM audit_log', $sql, 'the columns');
	pg_assert_same(false, strpos($sql, 'actor_name'), 'no actor_name');
	pg_assert_contains("(entity_id = '7')", $sql, 'one request');
}
