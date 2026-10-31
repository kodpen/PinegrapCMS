<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for drawing the page form again after a refused save
 * (includes/forms/page_form.php): what the refusal stored, the screen's own
 * fields and check boxes, the type it opens on and the type settings, read
 * from the stored copy instead of $_POST; and that add_page.php and
 * edit_page.php store the post before every refusal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

function test_page_form_posted_reads_the_stored_copy()
{
	$saved_session = isset($_SESSION) ? $_SESSION : null;
	$saved_request = $_REQUEST;
	$_SESSION = array();

	try {
		$form = new liveform('page_form_redraw_test');
		$form->add_notice('kept');
		pg_assert_same(array(), pg_page_form_posted($form), 'nothing stored before a refusal');

		$_REQUEST = array('id' => '7', 'name' => ' about us ', 'sitemap' => '1', 'tags' => array('a', 'b'));
		$form->add_fields_to_session();
		$form->mark_error('name', 'taken');

		$posted = pg_page_form_posted($form);
		pg_assert_same('about us', $posted['name']);
		pg_assert_same('1', $posted['sitemap']);
		pg_assert_same(array('a', 'b'), $posted['tags']);
		pg_assert_true(!array_key_exists('notices', $posted), 'messages are not fields');

		pg_assert_same('7', pg_page_form_posted($form, 7)['id'], 'the same page');
		pg_assert_same(array(), pg_page_form_posted($form, 8), 'another page draws its stored values');
	} finally {
		$_REQUEST = $saved_request;
		if ($saved_session === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $saved_session;
		}
	}
}

function test_page_form_redraw_fields()
{
	$current = array('name' => 'old', 'title' => 'Old', 'sitemap' => '1', 'noindex' => '1', 'home' => 'yes');

	pg_assert_same($current, pg_page_form_redraw_fields($current, array(), array('sitemap', 'noindex', 'home')), 'nothing refused');

	$redrawn = pg_page_form_redraw_fields($current, array('name' => 'new', 'noindex' => '1', 'title' => array('x')), array('sitemap', 'noindex', 'home'));
	pg_assert_same('new', $redrawn['name']);
	pg_assert_same('Old', $redrawn['title'], 'an array never replaces a text');
	pg_assert_same('0', $redrawn['sitemap'], 'an unticked box is not posted');
	pg_assert_same('1', $redrawn['noindex']);
	pg_assert_same('0', $redrawn['home']);

	pg_assert_same('1', pg_page_form_redraw_fields(array('sitemap' => '1'), array('name' => 'x'))['sitemap'], 'a box the form did not print keeps its value');
}

function test_page_form_redraw_type()
{
	$admin = array('role' => '0');
	$basic = array('role' => '3');

	pg_assert_same('standard', pg_page_form_redraw_type(array(), 'standard', $admin), 'nothing refused');
	pg_assert_same('custom form', pg_page_form_redraw_type(array('type' => 'custom form'), 'standard', $admin));
	pg_assert_same('catalog', pg_page_form_redraw_type(array('type' => 'no such type'), 'catalog', $admin), 'an unknown type');
	pg_assert_same('standard', pg_page_form_redraw_type(array('type' => 'custom form'), 'standard', $basic), 'a type the user was not given');
}

function test_page_form_type_properties_from_a_source()
{
	$saved = $_POST;
	$_POST = array('custom_form_form_name' => 'From post');

	try {
		$source = array('custom_form_form_name' => 'Contact', 'custom_form_confirmation_type' => 'page', 'custom_form_enabled' => '1');
		$properties = pg_page_form_type_properties_from_post('custom form', 0, $source);
		pg_assert_same('Contact', $properties['form_name'], 'read from the source');
		pg_assert_same('page', $properties['confirmation_type']);
		pg_assert_same('1', $properties['enabled']);
		pg_assert_same(0, $properties['quiz'], 'an unticked box');

		pg_assert_same('From post', pg_page_form_type_properties_from_post('custom form', 0)['form_name'], '$_POST by default');
	} finally {
		$_POST = $saved;
	}
}

function test_page_form_redraw_type_properties()
{
	$defaults = pg_page_form_new_page_properties();

	pg_assert_same($defaults, pg_page_form_redraw_type_properties($defaults, 'custom form', array()), 'nothing refused');

	$posted = array(
		'custom_form_form_name' => 'Contact',
		'custom_form_confirmation_type' => 'page',
		'custom_form_confirmation_page_id' => '',
		'custom_form_return_type' => 'message',
		'custom_form_share_image_field_id' => '12',
		'custom_form_offer_id' => '3',
		'custom_form_offer' => '1',
		'custom_form_offer_days' => '5',
		'custom_form_offer_eligibility' => 'everyone',
	);
	$redrawn = pg_page_form_redraw_type_properties($defaults, 'custom form', $posted);
	$form = $redrawn['custom form'];

	pg_assert_same('Contact', $form['form_name']);
	pg_assert_same('page', $form['confirmation_type'], 'typed over the default');
	pg_assert_same('message', $form['return_type']);
	pg_assert_same('', $form['submitter_email_format'], 'a field missing from the copy comes back as the save would read it');
	pg_assert_true(!array_key_exists('page_id', $form));
	pg_assert_same('1', $form['offer']);
	pg_assert_same('3', $form['offer_id']);
	pg_assert_same('5', $form['offer_days']);
	pg_assert_same('everyone', $form['offer_eligibility']);
	pg_assert_same($defaults['catalog'], $redrawn['catalog'], 'other types keep their values');

	if (array_key_exists('share_image_field_id', $form)) {
		pg_assert_same(12, $form['share_image_field_id'], 'the choice comes back without a query');
	}

	pg_assert_true(!array_key_exists('offer', pg_page_form_redraw_type_properties(array(), 'custom form', array('custom_form_form_name' => 'x'))['custom form']), 'no offer rows printed');
	pg_assert_same(array(), pg_page_form_redraw_type_properties(array(), 'standard', array('name' => 'x')), 'a type without properties');
}

function test_page_form_refusals_store_the_post()
{
	$add = file_get_contents(PG_FUNCTIONS_DIR . '/add_page.php');
	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php');

	// The blank name, the name in use; pg_page_form_refuse() stores it for '/' and the custom form.
	pg_assert_same(2, substr_count($add, '$liveform_add_page->add_fields_to_session();'), 'add_page.php stores the post');
	pg_assert_same(3, substr_count($edit, '$liveform->add_fields_to_session();'), 'edit_page.php stores the post (blank name, canonical/snippet, name in use)');

	$refuse = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
	$start = strpos($refuse, 'function pg_page_form_refuse(');
	pg_assert_contains('$liveform->add_fields_to_session();', substr($refuse, $start, 600), 'pg_page_form_refuse() stores the post');

	foreach (array('add_page.php' => $add, 'edit_page.php' => $edit) as $path => $source) {
		pg_assert_contains('pg_page_form_posted(', $source, $path . ' reads the stored copy');
		pg_assert_contains('pg_page_form_redraw_fields(', $source, $path . ' draws its fields from it');
		pg_assert_contains('pg_page_form_redraw_type(', $source, $path . ' opens on the typed type');
		pg_assert_contains('pg_page_form_redraw_type_properties(', $source, $path . ' draws the type settings from it');
	}

	pg_assert_contains('$liveform_add_page->remove_form();', $add, 'typed values do not come back on the next visit');
	pg_assert_true(strpos($add, 'unmark_errors(') === false, 'add_page.php clears the whole form');
}
