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
