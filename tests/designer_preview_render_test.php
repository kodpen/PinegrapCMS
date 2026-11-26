<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the server side of the Preview's "Preview as"
 * (includes/fn/designer_preview_render.php): the operator's right per
 * record source, the record statements (SELECT only, every value bound or
 * whole), the search text, and the scope a widget is drawn in, which
 * writes nothing: no session, no counter, no visit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_preview_render_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_preview_render_source_right_forms()
{
	pg_assert_same('all', pg_preview_as_source_right('form', array('role' => 0)));
	pg_assert_same('all', pg_preview_as_source_right('form', array('role' => 2)));
	pg_assert_same('own', pg_preview_as_source_right('form', array('role' => 3, 'manage_forms' => true)));
	pg_assert_same('own', pg_preview_as_source_right('form', array('role' => 3, 'manage_forms' => 'yes')));
	pg_assert_same('none', pg_preview_as_source_right('form', array('role' => 3, 'manage_forms' => false)));
	pg_assert_same('none', pg_preview_as_source_right('form', array('role' => 3)));
}

function test_preview_render_source_right_products_and_events()
{
	pg_assert_same('all', pg_preview_as_source_right('product', array('role' => 1)));
	pg_assert_same('all', pg_preview_as_source_right('product', array('role' => 3, 'manage_ecommerce' => true)));
	pg_assert_same('none', pg_preview_as_source_right('product', array('role' => 3, 'manage_ecommerce' => false)));
	pg_assert_same('all', pg_preview_as_source_right('event', array('role' => 2)));
	pg_assert_same('own', pg_preview_as_source_right('event', array('role' => 3, 'manage_calendars' => true, 'publish_calendar_events' => true)));
	// The Preview draws published events only; a user who may not publish
	// opens none of them.
	pg_assert_same('none', pg_preview_as_source_right('event', array('role' => 3, 'manage_calendars' => true, 'publish_calendar_events' => false)));
	pg_assert_same('none', pg_preview_as_source_right('event', array('role' => 3)));
}

function test_preview_render_source_right_refuses_the_unknown()
{
	pg_assert_same('none', pg_preview_as_source_right('form', null));
	pg_assert_same('none', pg_preview_as_source_right('form', array()));
	pg_assert_same('none', pg_preview_as_source_right('', array('role' => 0)));
	pg_assert_same('none', pg_preview_as_source_right('page', array('role' => 0)));
}

function test_preview_render_search_term()
{
	pg_assert_same('', pg_preview_as_search_term(null));
	pg_assert_same('', pg_preview_as_search_term(array('x')));
	pg_assert_same('', pg_preview_as_search_term('   '));
	pg_assert_same('red shoe', pg_preview_as_search_term("  red\tshoe "));
	pg_assert_same('a b', pg_preview_as_search_term("a\nb"));
	pg_assert_same(60, mb_strlen(pg_preview_as_search_term(str_repeat('ş', 200)), 'UTF-8'));
	pg_assert_same('', pg_preview_as_search_term("\xC3\x28"));
}

function test_preview_render_like_pattern()
{
	pg_assert_same('%%', pg_preview_as_like(''));
	pg_assert_same('%shoe%', pg_preview_as_like('shoe'));
	pg_assert_same('%50\\%\\_off%', pg_preview_as_like('50%_off'));
	pg_assert_same('%a\\\\b%', pg_preview_as_like('a\\b'));
}

function test_preview_render_record_sql_is_select_only()
{
	$args = array(
		'form_page_id' => 7, 'join' => '', 'where' => '', 'group' => 3,
		'calendar_ids' => array(2, 5), 'id' => 9, 'like' => '%x%', 'limit' => 20, 'now' => '2026-11-25 10:00:00',
	);
	$picks = array(
		'form'    => array('default', 'chosen', 'longest', 'search'),
		'product' => array('default', 'chosen', 'longest', 'sold_out', 'search'),
		'event'   => array('default', 'chosen', 'longest', 'search'),
	);
	foreach ($picks as $source => $list) {
		foreach ($list as $pick) {
			$sql = pg_preview_as_record_sql($source, $pick, $args);
			pg_assert_true(strpos($sql, 'SELECT ') === 0, $source . ' ' . $pick . ' is a SELECT');
			pg_assert_false((bool) preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\b/i', $sql), $source . ' ' . $pick . ' writes nothing');
			pg_assert_contains('LIMIT', $sql, $source . ' ' . $pick . ' is limited');
		}
	}
}

function test_preview_render_record_sql_picks()
{
	$form = array('form_page_id' => 7, 'join' => ' JOIN j', 'where' => ' AND (w)', 'id' => 9);
	$sql = pg_preview_as_record_sql('form', 'chosen', $form);
	pg_assert_contains("forms.page_id = '7'", $sql);
	pg_assert_contains(' JOIN j', $sql, 'the widget filters join');
	pg_assert_contains(' AND (w)', $sql, 'the widget filters apply');
	pg_assert_contains("forms.id = '9'", $sql);
	pg_assert_contains('SUM(CHAR_LENGTH(form_data.data))', pg_preview_as_record_sql('form', 'longest', $form));
	pg_assert_contains('ORDER BY forms.id DESC LIMIT 1', pg_preview_as_record_sql('form', 'default', $form));

	pg_assert_contains("products_groups_xref.product_group = '3'", pg_preview_as_record_sql('product', 'default', array('group' => 3)));
	pg_assert_false(strpos(pg_preview_as_record_sql('product', 'default', array('group' => 0)), 'products_groups_xref') !== false, 'no group: every product');
	$sold = pg_preview_as_record_sql('product', 'sold_out', array('group' => 0));
	pg_assert_contains("products.inventory = '1' AND products.inventory_quantity = '0' AND products.backorder = '0'", $sold);
	pg_assert_contains("products.enabled = '1'", $sold, 'a disabled product is never drawn');

	$ev = pg_preview_as_record_sql('event', 'default', array('calendar_ids' => array(2, '5', 0, -1, 2), 'now' => '2026-11-25 10:00:00'));
	pg_assert_contains('calendar_id IN (2,5)', $ev, 'whole positive calendar ids, once each');
	pg_assert_contains("calendar_events.published = '1'", $ev);
	pg_assert_contains("'2026-11-25 10:00:00'", $ev);
	$ev = pg_preview_as_record_sql('event', 'default', array('calendar_ids' => array(2), 'now' => "x' OR 1=1"));
	pg_assert_false(strpos($ev, "OR 1=1") !== false, 'a malformed time is not put in');
}

function test_preview_render_record_sql_refuses()
{
	pg_assert_same('', pg_preview_as_record_sql('form', 'default', array('form_page_id' => 0)), 'no form');
	pg_assert_same('', pg_preview_as_record_sql('form', 'chosen', array('form_page_id' => 7, 'id' => 0)), 'no chosen id');
	pg_assert_same('', pg_preview_as_record_sql('form', 'sold_out', array('form_page_id' => 7)), 'a form has no sold-out pick');
	pg_assert_same('', pg_preview_as_record_sql('event', 'default', array('calendar_ids' => array())), 'no calendar');
	pg_assert_same('', pg_preview_as_record_sql('event', 'sold_out', array('calendar_ids' => array(1))));
	pg_assert_same('', pg_preview_as_record_sql('page', 'default', array()));
	pg_assert_contains('LIMIT 50', pg_preview_as_record_sql('product', 'search', array('limit' => 999)), 'the list is capped');
}

function test_preview_render_record_target_without_record()
{
	$none = array('get' => array(), 'address' => '', 'id' => 0);
	pg_assert_same($none, pg_preview_as_record_target(pg_preview_as_setup('catalog_listing', array()), array(), 0, null));
	pg_assert_same($none, pg_preview_as_record_target(null, array(), 0, null));
}

function test_preview_render_current_and_flags()
{
	pg_assert_same(array(), pg_preview_as_current(), 'nothing held outside a Preview widget');
	pg_assert_false(pg_preview_as_flag('empty'));
	$seen = pg_preview_as_while(pg_preview_as_setup('form_list_view', array('case' => 'empty')), function () {
		return array(pg_preview_as_flag('empty'), pg_preview_as_flag('long'), pg_preview_as_current());
	});
	pg_assert_true($seen[0], 'the renderer reads the case');
	pg_assert_false($seen[1]);
	pg_assert_same('empty', $seen[2]['case']);
	pg_assert_same(array(), pg_preview_as_current(), 'cleared after the widget');
}

// The never-writes guarantees of one Preview widget: a background render,
// no counter, no visit fact; the session is put back as it was, also when
// the renderer throws.
function test_preview_render_scope_writes_nothing()
{
	pg_assert_false(pg_seo_rendering());
	$inside = pg_preview_as_while(array(), function () {
		return array(
			'background' => pg_seo_rendering(),
			'throws'     => pg_error_throws(),
			'search'     => pg_site_search_countable(),
			'visit'      => pg_page_fact_background(false),
		);
	});
	pg_assert_true($inside['background'], 'drawn as a background render');
	pg_assert_true($inside['throws'], 'an error throws instead of printing a page');
	pg_assert_false($inside['search'], 'no site search is counted');
	pg_assert_true($inside['visit'], 'belongs to no visit');
	pg_assert_false(pg_seo_rendering(), 'restored');
	pg_assert_false(pg_error_throws(), 'restored');
}

function test_preview_render_scope_puts_the_session_back()
{
	$had     = isset($_SESSION);
	$before  = $had ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array('x' => array('error' => 'kept'))), 'cart' => 1);
	pg_preview_as_while(array(), function () {
		unset($_SESSION['software']['liveforms']['x']);
		$_SESSION['visit'] = 'written';
		$_SESSION['cart']  = 2;
	});
	pg_assert_same(array('software' => array('liveforms' => array('x' => array('error' => 'kept'))), 'cart' => 1), $_SESSION, 'the session is as before');

	$threw = false;
	try {
		pg_preview_as_while(pg_preview_as_setup('custom_form', array('case' => 'error')), function () {
			$_SESSION['order'] = 5;
			throw new Exception('render failed');
		});
	} catch (Exception $e) {
		$threw = true;
	}
	pg_assert_true($threw);
	pg_assert_false(isset($_SESSION['order']), 'put back after a throw too');
	pg_assert_same(array(), pg_preview_as_current());
	pg_assert_false(pg_seo_rendering());

	if ($had) {
		$_SESSION = $before;
	} else {
		unset($_SESSION);
	}
}

// The Preview draws every widget inside the scope, and the record chooser's
// endpoint reads only (a clean page-cache action, the content operator's
// list, the rights check).
function test_preview_render_wiring()
{
	$designer = designer_preview_render_test_source('includes/fn/designer.php');
	$start    = (int) strpos($designer, 'function pg_designer_preview_widgets(');
	$body     = substr($designer, $start, (int) strpos($designer, "\nfunction ", $start + 10) - $start);
	pg_assert_contains('pg_preview_as_while(', $body, 'each widget is drawn in the scope');
	pg_assert_contains('pg_preview_as_choice(', $body, 'the choice is read back against the page');
	pg_assert_contains('pg_preview_as_record_target(', $body, 'the record is picked with the rights');
	pg_assert_true(strpos($body, '_expand_system_widgets(') > strpos($body, 'pg_preview_as_while('), 'the widget is expanded inside the scope');

	$actions = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('preview_records', $actions['designer']['clean'], true), 'the search leaves stored pages alone');
	pg_assert_true(in_array('preview_widgets', $actions['designer']['clean'], true));

	$panel = designer_preview_render_test_source('includes/panel/designer.php');
	$case  = substr($panel, (int) strpos($panel, "case 'preview_records':"), 1600);
	pg_assert_contains('pg_designer_page_access(', $case, 'a hidden page filters as unsaved');
	pg_assert_contains('pg_preview_as_record_search(', $case);
	pg_assert_contains('$user', $case, 'the search is the operator\'s');
	$wcase = substr($panel, (int) strpos($panel, "case 'preview_widgets':"), 1800);
	pg_assert_contains("'preview_as'", $wcase, 'the Preview takes the choice');
	pg_assert_contains("'setups'", $wcase, 'and says what it drew');
}

// What a renderer reads of the case: outside a Preview widget every reader
// gives back what the renderer had, so a live visitor sees what they saw.
function test_preview_render_readers_outside_the_scope()
{
	$rows = array(array('name' => 'a'), array('name' => 'a much longer name'));
	pg_assert_same('', pg_preview_as_empty_where());
	pg_assert_same('', pg_preview_as_order('long', 'CHAR_LENGTH(x)'));
	pg_assert_same('', pg_preview_as_order('sold_out', 'x = 0'));
	pg_assert_same($rows, pg_preview_as_rows($rows));
	pg_assert_same(null, pg_preview_as_rows(null));
}

function test_preview_render_readers_empty()
{
	$rows = array(array('name' => 'a'), array('name' => 'b'));
	$seen = pg_preview_as_while(pg_preview_as_setup('form_list_view', array('case' => 'empty')), function () use ($rows) {
		return array(pg_preview_as_empty_where(), pg_preview_as_rows($rows), pg_preview_as_order('long', 'x'));
	});
	pg_assert_same(' AND (TRUE = FALSE) ', $seen[0], 'the list statement matches nothing');
	pg_assert_same(array(), $seen[1], 'rows read are dropped');
	pg_assert_same('', $seen[2]);
}

function test_preview_render_readers_long()
{
	$rows = array(
		array('name' => 'short', 'id' => '1'),
		array('name' => '<b>longest of all</b>', 'id' => '2'),
		array('name' => 'medium one', 'id' => '3'),
		array('name' => 'mediumtwo!', 'id' => '4'),
	);
	$seen = pg_preview_as_while(pg_preview_as_setup('catalog_listing', array('case' => 'long')), function () use ($rows) {
		return array(pg_preview_as_rows($rows, array('name')), pg_preview_as_order('long', 'CHAR_LENGTH(p.name)'), pg_preview_as_empty_where());
	});
	$ids = array();
	foreach ($seen[0] as $r) $ids[] = $r['id'];
	pg_assert_same(array('2', '3', '4', '1'), $ids, 'most characters first, ties keep their order, markup not counted');
	pg_assert_same('(CHAR_LENGTH(p.name)) DESC, ', $seen[1]);
	pg_assert_same('', $seen[2]);

	$keyed = pg_preview_as_while(pg_preview_as_setup('catalog_listing', array('case' => 'long')), function () {
		return pg_preview_as_rows(array('x' => array('t' => 'a'), 'y' => array('t' => 'abc')));
	});
	pg_assert_same(array('y', 'x'), array_keys($keyed), 'keyed rows keep their keys');

	$sold = pg_preview_as_while(pg_preview_as_setup('catalog_listing', array('case' => 'sold_out')), function () {
		return array(pg_preview_as_order('sold_out', 'p.q = 0'), pg_preview_as_order('long', 'x'));
	});
	pg_assert_same(array('(p.q = 0) DESC, ', ''), $sold);
}

function test_preview_render_row_length()
{
	pg_assert_same(0, pg_preview_as_row_length(null));
	pg_assert_same(5, pg_preview_as_row_length(array('a' => 'ab', 'b' => 'çde', 'n' => 4)));
	pg_assert_same(2, pg_preview_as_row_length(array('a' => 'ab', 'b' => 'cde'), array('a', 'missing')));
}

// A liveform stand-in: what the case "Error" marks.
class designer_preview_render_test_form
{
	public $marked = array();
	function mark_error($field, $message = '')
	{
		$this->marked[] = array($field, $message);
	}
}

function test_preview_render_form_error_fields()
{
	foreach (array('login', 'forgot_password', 'register', 'affiliate_sign_up_form', 'shipping_address_and_arrival', 'billing_information') as $form) {
		$mark = pg_preview_as_form_error_field($form);
		pg_assert_true(is_array($mark) && $mark['field'] !== '' && $mark['message'] !== '', $form . ' has a required field');
	}
	pg_assert_same('billing_first_name', pg_preview_as_form_error_field('billing_information')['field']);
	pg_assert_same(null, pg_preview_as_form_error_field('search_results'));

	$map = array(
		'office' => array('id' => 7, 'required' => 1, 'office_use_only' => 1, 'label' => 'Staff', 'type' => 'text box'),
		'note'   => array('id' => 8, 'required' => 0, 'office_use_only' => 0, 'label' => 'Note', 'type' => 'text box'),
		'name'   => array('id' => 9, 'required' => 1, 'office_use_only' => 0, 'label' => 'Name', 'type' => 'text box'),
	);
	pg_assert_same(9, pg_preview_as_custom_form_required($map)['id'], 'the first required field a visitor fills in');
	pg_assert_same(null, pg_preview_as_custom_form_required(array('note' => $map['note'])));
}

function test_preview_render_form_error_marks_only_in_the_case()
{
	$lf = new designer_preview_render_test_form();
	pg_preview_as_form_error($lf, 'login');
	pg_preview_as_custom_form_error($lf, array(array('id' => 9, 'required' => 1, 'label' => 'Name', 'type' => 'text box')));
	pg_assert_same(array(), $lf->marked, 'nothing outside the Preview');

	pg_preview_as_while(pg_preview_as_setup('custom_form', array('case' => 'typical')), function () use ($lf) {
		pg_preview_as_form_error($lf, 'login');
	});
	pg_assert_same(array(), $lf->marked, 'nothing in the typical case');

	pg_preview_as_while(pg_preview_as_setup('custom_form', array('case' => 'error')), function () use ($lf) {
		pg_preview_as_form_error($lf, 'login');
		pg_preview_as_custom_form_error($lf, array(array('id' => 9, 'required' => 1, 'label' => 'Name', 'type' => 'text box')));
	});
	pg_assert_same('email', $lf->marked[0][0]);
	pg_assert_same(9, $lf->marked[1][0]);
	pg_assert_contains('Name', $lf->marked[1][1]);
}

// Every renderer of a list, a cart or a form reads the case, and the cart
// writes nothing in a background render.
function test_preview_render_renderers_read_the_case()
{
	$reads = array(
		'includes/fn/widgets.php'                     => array('pg_preview_as_empty_where()', "pg_preview_as_order('long'", "pg_preview_as_flag('empty')", 'pg_preview_as_custom_form_error(', "pg_preview_as_form_error(\$lf, 'login')", "pg_preview_as_form_error(\$lf, 'register')", "pg_preview_as_form_error(\$lf, 'forgot_password')", 'pg_preview_as_rows($events)'),
		'includes/fn/widgets_catalog.php'             => array('pg_preview_as_empty_where()', "pg_preview_as_order('long'", "pg_preview_as_order('sold_out'"),
		'includes/fn/widgets_cart.php'                => array("pg_preview_as_flag('empty')", 'pg_preview_as_rows($rows', 'pg_preview_as_rows($items', '!pg_seo_rendering()'),
		'includes/fn/widgets_photo_gallery.php'       => array('pg_preview_as_rows($rows)'),
		'includes/fn/widgets_folder_view.php'         => array('pg_preview_as_rows($top)'),
		'includes/fn/widgets_comments.php'            => array("pg_preview_as_flag('empty')"),
		'includes/fn/widgets_comments_recent.php'     => array("pg_preview_as_flag('empty')"),
		'includes/fn/widgets_affiliate_sign_up.php'   => array("pg_preview_as_form_error(\$lf, 'affiliate_sign_up_form')"),
		'includes/fn/widgets_billing_information.php' => array("pg_preview_as_form_error(\$lf, 'billing_information')"),
		'includes/fn/widgets_shipping_address.php'    => array("pg_preview_as_form_error(\$lf, 'shipping_address_and_arrival')"),
	);
	foreach ($reads as $path => $needles) {
		$src = designer_preview_render_test_source($path);
		foreach ($needles as $needle) pg_assert_contains($needle, $src, $path . ' reads ' . $needle);
	}
	$cart = designer_preview_render_test_source('includes/fn/widgets_cart.php');
	$at   = (int) strpos($cart, 'db("UPDATE order_items');
	pg_assert_true($at > 0 && strpos(substr($cart, $at - 400, 400), '!pg_seo_rendering()') !== false, 'the recurring defaults are not written by a background render');
}

// The visitor of a Preview widget (PW2c): one reader in place of the
// USER_LOGGED_IN family; outside the scope it is the request's constants.
function test_preview_render_viewer_outside_is_the_request()
{
	$viewer = pg_preview_as_viewer();
	pg_assert_false($viewer['preview']);
	pg_assert_false($viewer['sample']);
	pg_assert_same(defined('USER_LOGGED_IN') && USER_LOGGED_IN === true, $viewer['signed_in']);
	pg_assert_same(defined('USER_ID') ? (int) USER_ID : 0, $viewer['id']);
	pg_assert_same(defined('USER_EMAIL_ADDRESS') ? (string) USER_EMAIL_ADDRESS : '', $viewer['email']);
	pg_assert_same(defined('USER_MEMBER') && USER_MEMBER === true, $viewer['member']);
	pg_assert_same(array(), pg_preview_as_current(), 'no scope held');
}

function test_preview_render_viewer_per_visitor()
{
	$seen = array();
	foreach (array('signed_out', 'signed_in', 'member') as $visitor) {
		$seen[$visitor] = pg_preview_as_while(pg_preview_as_setup('my_account', array('visitor' => $visitor)), function () {
			return pg_preview_as_viewer();
		});
	}
	pg_assert_true($seen['signed_out']['preview']);
	pg_assert_false($seen['signed_out']['signed_in']);
	pg_assert_same('', $seen['signed_out']['email']);
	pg_assert_same(null, $seen['signed_out']['role']);

	pg_assert_true($seen['signed_in']['signed_in']);
	pg_assert_true($seen['signed_in']['sample'], 'the sample account');
	pg_assert_same(0, $seen['signed_in']['id'], 'no user row: id 0');
	pg_assert_same(0, $seen['signed_in']['contact_id']);
	pg_assert_same(3, $seen['signed_in']['role'], 'a plain user');
	pg_assert_same('alex', $seen['signed_in']['username']);
	pg_assert_same('alex@example.com', $seen['signed_in']['email']);
	pg_assert_false($seen['signed_in']['member']);

	pg_assert_true($seen['member']['member']);
	pg_assert_true($seen['member']['signed_in']);
	pg_assert_same(0, $seen['member']['id']);

	pg_assert_false(pg_preview_as_viewer()['preview'], 'cleared after the widget');
}

function test_preview_render_view_rule()
{
	$out    = array('signed_in' => false, 'member' => false);
	$in     = array('signed_in' => true, 'member' => false);
	$member = array('signed_in' => true, 'member' => true);
	pg_assert_true(pg_preview_as_view_rule('public', $out));
	pg_assert_false(pg_preview_as_view_rule('registration', $out));
	pg_assert_true(pg_preview_as_view_rule('registration', $out, true), 'anybody can get in');
	pg_assert_true(pg_preview_as_view_rule('guest', $in));
	pg_assert_false(pg_preview_as_view_rule('membership', $in));
	pg_assert_false(pg_preview_as_view_rule('private', $in));
	pg_assert_true(pg_preview_as_view_rule('membership', $member));
	pg_assert_true(pg_preview_as_view_rule('private', $member));
	pg_assert_false(pg_preview_as_view_rule('membership', $out, true));
}

function test_preview_render_sample_account_agrees_with_the_canvas()
{
	$sample = pg_preview_as_sample_account();
	pg_assert_same('alex', $sample['account']['username']);
	pg_assert_same('alex@example.com', $sample['contact']['email_address']);
	pg_assert_same(2, count($sample['recipients']), 'two saved recipients');
	pg_assert_same('97201', $sample['recipients'][0]['zip_code'], 'the order\'s shipping address first');
	foreach (pg_sw_profile_fields() as $f) {
		pg_assert_true(array_key_exists($f, $sample['contact']), 'profile field ' . $f);
	}
	$first = $sample['recipients'][0];
	pg_assert_same($first, pg_preview_as_sample_recipient($first['ship_to_name']));
	pg_assert_same(null, pg_preview_as_sample_recipient('nobody'));
}

function test_preview_render_mask()
{
	pg_assert_same('j•••@example.org', pg_preview_as_mask('jane.doe@example.org', 'email'));
	pg_assert_same('•••33', pg_preview_as_mask('+90 555 111 22 33', 'phone'));
	pg_assert_same('H•••', pg_preview_as_mask('Harbour Road 12', 'address'));
	pg_assert_same('', pg_preview_as_mask('  ', 'email'));
	pg_assert_same('email', pg_preview_as_mask_kind('email address', ''));
	pg_assert_same('phone', pg_preview_as_mask_kind('text box', 'mobile_phone'));
	pg_assert_same('address', pg_preview_as_mask_kind('text box', 'business_address_1'));
	pg_assert_same('address', pg_preview_as_mask_kind('text box', 'business_zip_code'));
	pg_assert_same('', pg_preview_as_mask_kind('text box', 'first_name'));
	$rows = pg_preview_as_mask_rows(array(
		array('data' => 'jane@example.org', 'field_type' => 'email address', 'field_contact' => ''),
		array('data' => 'Jane', 'field_type' => 'text box', 'field_contact' => 'first_name'),
	));
	pg_assert_same('j•••@example.org', $rows[0]['data']);
	pg_assert_same('Jane', $rows[1]['data']);
}

// The never-writes guarantees hold for every visitor.
function test_preview_render_scope_writes_nothing_per_visitor()
{
	foreach (array('signed_out', 'signed_in', 'member') as $visitor) {
		$_SESSION = array('cart' => 1);
		$inside = pg_preview_as_while(pg_preview_as_setup('login_region', array('visitor' => $visitor)), function () {
			$_SESSION['software']['user_id'] = 99;
			return array('background' => pg_seo_rendering(), 'search' => pg_site_search_countable(), 'visit' => pg_page_fact_background(false));
		});
		pg_assert_true($inside['background'], $visitor);
		pg_assert_false($inside['search'], $visitor);
		pg_assert_true($inside['visit'], $visitor);
		pg_assert_same(array('cart' => 1), $_SESSION, $visitor . ': the session is as before');
	}
	$_SESSION = array();
}

// The census of PW2c: the renderers that draw differently signed in or out,
// or read rows by the visitor's id, read the visitor through the reader.
// A bare USER_ID or USER_LOGGED_IN left in one of them fails here.
function test_preview_render_visitor_wiring()
{
	$census = array(
		'includes/fn/widgets.php' => array('pg_sw_submitted_form_visible', '_render_system_widget_my_account', '_render_system_widget_order_view', 'pg_sw_auto_catalog_detail_page'),
		'includes/fn/widgets_account.php' => array('_pg_acct_signed_in', '_pg_member_session_signed_in', 'pg_sw_page_type_widget_url', '_render_system_widget_logout',
			'_render_system_widget_change_password', '_render_system_widget_account_profile', '_render_system_widget_email_preferences',
			'_render_system_widget_address_book', '_pg_my_account_extras', '_render_system_widget_login_region', '_pg_acct_staff_url'),
		'includes/fn/widgets_billing_information.php' => array('_render_system_widget_billing_information'),
		'includes/fn/widgets_booking.php' => array('pg_booking_widget_visitor'),
		'includes/fn/widgets_express_order.php' => array('_eo_render_saved_address_select', '_eo_sms_box_shown', '_eo_prefill_billing_fields'),
		'includes/fn/widgets_folder_view.php' => array(),
		'includes/fn/widgets_comments_recent.php' => array(),
		'includes/fn/widgets_shipping_address.php' => array(),
		'includes/fn/widgets_catalog.php' => array(),
		'includes/fn/widgets_comments.php' => array(),
	);
	foreach ($census as $path => $functions) {
		$source = designer_preview_render_test_source($path);
		pg_assert_contains('pg_preview_as_viewer()', $source, $path . ' reads the viewer');
		foreach ($functions as $fn) {
			$start = strpos($source, 'function ' . $fn . '(');
			pg_assert_true($start !== false, $fn . ' found');
			$end  = strpos($source, "\n}\n", $start);
			$body = substr($source, $start, $end - $start);
			pg_assert_false((bool) preg_match('/(?<![\'\w])USER_(ID|LOGGED_IN|CONTACT_ID|EMAIL_ADDRESS|USERNAME)\b/', $body), $fn . ' reads the visitor through pg_preview_as_viewer()');
		}
	}
	$billing = designer_preview_render_test_source('includes/fn/widgets_billing_information.php');
	pg_assert_false((bool) preg_match('/(?<![\'\w])USER_(ID|LOGGED_IN|CONTACT_ID)\b/', $billing), 'billing reads no constant');
	foreach (array('widgets_booking.php', 'widgets_shipping_address.php', 'widgets_comments_recent.php', 'widgets_catalog.php') as $file) {
		pg_assert_false((bool) preg_match('/(?<![\'\w])USER_(ID|LOGGED_IN|EMAIL_ADDRESS)\b/', designer_preview_render_test_source('includes/fn/' . $file)), $file);
	}
}

// First visit and returning: inside a Preview widget the audience reader of
// the visit answers the chosen visitor, never the pg_ret cookie, whatever
// the browser holds; the member token answers the chosen member.
function test_preview_render_visit_per_case()
{
	$saved = $_COOKIE;
	$old   = (string) (time() - 86400);
	foreach (array(null, $old) as $cookie) {
		if ($cookie === null) {
			unset($_COOKIE['pg_ret']);
		} else {
			$_COOKIE['pg_ret'] = $cookie;
		}
		foreach (pg_preview_as_visitors() as $visitor) {
			$seen = pg_preview_as_while(pg_preview_as_setup('login_region', array('visitor' => $visitor)), function () {
				return array(
					'viewer'     => pg_preview_as_viewer(),
					'returning'  => pg_audience_value('__audience_returning', true),
					'live'       => pg_audience_value('__audience_returning', false),
					'member'     => pg_audience_value('__audience_member', true),
					'country'    => pg_audience_value('__audience_country', true),
				);
			});
			$label = $visitor . ($cookie === null ? ', no cookie' : ', an old cookie');
			$want  = ($visitor === 'returning') ? '1' : '';
			pg_assert_same($visitor === 'returning', $seen['viewer']['returning'], $label);
			pg_assert_same($want, $seen['returning'], $label . ': the chosen case, not the cookie');
			pg_assert_same($want, $seen['live'], $label . ': the same read for a visit');
			pg_assert_same(($visitor === 'member') ? '1' : '', $seen['member'], $label . ': member');
			pg_assert_same('', $seen['country'], $label . ': no request value in a Preview');
			if ($visitor === 'first_visit' || $visitor === 'returning') {
				pg_assert_false($seen['viewer']['signed_in'], $label . ': signed out');
				pg_assert_false($seen['viewer']['member'], $label);
			}
		}
	}
	$_COOKIE = $saved;
}

// The reader writes nothing: the cookie is neither set nor changed, and a
// drawn page's audience spans leave no cache note.
function test_preview_render_visit_leaves_the_cookie()
{
	$saved = $_COOKIE;
	unset($_COOKIE['pg_ret']);
	foreach (array('first_visit', 'returning') as $visitor) {
		pg_preview_as_while(pg_preview_as_setup('login_region', array('visitor' => $visitor)), function () {
			pg_audience_value('__audience_returning', false);
			pg_audience_values_for('<!--pg-pf:0123456789ab:' . rtrim(strtr(base64_encode('[{"t":"__audience_returning"}]'), '+/', '-_'), '=') . '-->x<!--/pg-pf:0123456789ab-->', true);
		});
		pg_assert_false(isset($_COOKIE['pg_ret']), $visitor . ': no cookie set');
	}
	$_COOKIE['pg_ret'] = '1700000000';
	pg_preview_as_while(pg_preview_as_setup('login_region', array('visitor' => 'first_visit')), function () {
		pg_audience_value('__audience_returning', false);
	});
	pg_assert_same('1700000000', $_COOKIE['pg_ret'], 'the browser\'s cookie is left as it was');
	foreach (headers_list() as $header) {
		pg_assert_false(stripos($header, 'pg_ret=') !== false, 'no Set-Cookie');
	}
	$_COOKIE = $saved;
}

// Outside a Preview widget the viewer's returning is the cookie's, as the
// audience reader reads it for a visit.
function test_preview_render_visit_outside_is_the_cookie()
{
	$saved = $_COOKIE;
	unset($_COOKIE['pg_ret']);
	pg_assert_false(pg_preview_as_viewer()['returning'], 'no cookie: a first visit');
	$_COOKIE['pg_ret'] = (string) (time() - 86400);
	pg_assert_true(pg_preview_as_viewer()['returning'], 'an old cookie: returning');
	$_COOKIE['pg_ret'] = (string) time();
	pg_assert_false(pg_preview_as_viewer()['returning'], 'a cookie of this visit: a first visit');
	$_COOKIE = $saved;
}

// The audience value of a chosen visitor, pure.
function test_preview_render_audience_value()
{
	pg_assert_same('1', pg_preview_as_audience_value('returning', array('returning' => true)));
	pg_assert_same('', pg_preview_as_audience_value('returning', array('returning' => false)));
	pg_assert_same('1', pg_preview_as_audience_value('member', array('signed_in' => true, 'member' => true)));
	pg_assert_same('', pg_preview_as_audience_value('member', array('signed_in' => false, 'member' => true)));
	foreach (array('group', 'country', 'device', 'utm_source', 'utm_campaign', 'experiment', '') as $kind) {
		pg_assert_same('', pg_preview_as_audience_value($kind, array('returning' => true, 'signed_in' => true, 'member' => true)), $kind);
	}
}

// A comments widget on a record page shows the thread of the record the
// page's record widget draws: that widget's own chosen record and case
// count, not only the page's.
function test_preview_render_row_choice()
{
	$page = array('record' => 0, 'visitor' => 'signed_in', 'case' => 'typical');
	pg_assert_same(array('record' => 42, 'visitor' => 'signed_in', 'case' => 'typical'), pg_preview_as_row_choice($page, array('record' => 42, 'case' => '')));
	pg_assert_same(array('record' => 0, 'visitor' => 'signed_in', 'case' => 'long'), pg_preview_as_row_choice($page, array('record' => 0, 'case' => 'long')));
	pg_assert_same($page, pg_preview_as_row_choice($page, array()), 'no row: the page\'s choice');
	pg_assert_same($page, pg_preview_as_row_choice($page, null));
	pg_assert_same(7, pg_preview_as_row_choice(array('record' => 7), array('record' => 0))['record'], 'no record of its own: the page\'s');

	$designer = designer_preview_render_test_source('includes/fn/designer.php');
	$start    = (int) strpos($designer, 'function pg_designer_preview_widgets(');
	$body     = substr($designer, $start, (int) strpos($designer, "\nfunction ", $start + 10) - $start);
	pg_assert_contains('$own  = pg_preview_as_row_choice($choice, $ov);', $body, 'a widget\'s own record and case');
	pg_assert_contains("pg_preview_as_setup('form_item_view', pg_preview_as_row_choice(\$choice, isset(\$overrides[(int)\$fiv['id']]) ? \$overrides[(int)\$fiv['id']] : null))", $body, 'the thread follows the record widget\'s row');
}
