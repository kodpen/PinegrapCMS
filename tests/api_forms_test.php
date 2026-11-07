<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's forms in includes/api/resources/forms.php:
 * which forms an owner reaches - every one from the manager role up, below
 * that the ones on pages in folders it may edit, as on the submitted-form
 * screens - the condition that keeps a query to them, and that every forms
 * route goes through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/forms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// An owner as api_load_owner() builds it.
function api_forms_test_owner($role, $manage_forms = false)
{
	return array(
		'id' => 9, 'username' => 'editor', 'role' => $role,
		'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => $manage_forms,
	);
}

// The lines of a function, to read which rule it applies.
function api_forms_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The submitted-form screens open every form from the manager role up, so
// those queries carry no condition and stay as they were.
function test_api_forms_manager_role_up_reaches_every_form()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_same('', api_form_owner_filter(api_forms_test_owner($role, false), 'forms.page_id'), 'role ' . $role);
	}
}

// Below that the form's page has to be filed in one of the folders the
// account may edit.
function test_api_forms_folder_filter_keeps_the_forms_in_the_folders_given()
{
	pg_assert_same(
		"forms.page_id IN (SELECT page_id FROM page WHERE page_folder IN (4,12))",
		api_form_folder_filter(array('12', '4'), 'forms.page_id'),
		'ids as the folder query returns them, in order'
	);

	pg_assert_same(
		"form_fields.page_id IN (SELECT page_id FROM page WHERE page_folder IN (7))",
		api_form_folder_filter(array(7, '7', 0, -3, 'x'), 'form_fields.page_id'),
		'a repeated id once, nothing that is not a folder id'
	);
}

// An account with the forms right but edit rights on no folder opens no form
// on the screens, so no row passes.
function test_api_forms_folder_filter_without_a_folder_lets_no_row_through()
{
	pg_assert_same('0', api_form_folder_filter(array(), 'forms.page_id'), 'no folder');
	pg_assert_same('0', api_form_folder_filter(null, 'forms.page_id'), 'nothing read');
	pg_assert_same('0', api_form_folder_filter(array(0, '0'), 'forms.page_id'), 'only the empty id');
}

// The user role is offered the forms scopes only with the forms right; the
// folder rule is what narrows them after that.
function test_api_forms_scope_follows_the_forms_right()
{
	pg_assert_false(in_array('forms:read', api_owner_scopes(api_forms_test_owner(3, false)), true), 'user role without the forms right');
	pg_assert_true(in_array('forms:read', api_owner_scopes(api_forms_test_owner(3, true)), true), 'user role with the forms right reads');
	pg_assert_true(in_array('forms:write', api_owner_scopes(api_forms_test_owner(3, true)), true), 'user role with the forms right writes');
	pg_assert_true(in_array('forms:write', api_owner_scopes(api_forms_test_owner(2, false)), true), 'manager');
}

// Every forms route answers only for the forms its owner may open: the
// handler builds the condition itself, or loads its submission through
// api_form_submission_owned(), which does.
function test_api_forms_every_route_applies_the_folder_rule()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'forms.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		if (!function_exists($route['handler'])) {
			continue;
		}

		$source = api_forms_test_function_source($route['handler']);

		pg_assert_true(
			(strpos($source, 'api_form_owner_filter(') !== false) || (strpos($source, 'api_form_submission_owned(') !== false),
			$route['id'] . ': ' . $route['handler'] . '() applies the folder rule'
		);

		pg_assert_same(($route['method'] === 'GET') ? 'forms:read' : 'forms:write', $route['scope'], $route['id'] . ' scope');
	}

	pg_assert_same(9, $found, 'list, get, fields, submissions, one submission, its create, update and both deletes');

	pg_assert_contains('api_form_owner_filter(', api_forms_test_function_source('api_form_submission_owned'), 'the submission loader');
}

// A record with a fee says whether it is paid and the order's number; one
// without a fee answers null for both, and a pre-upgrade row has neither key.
function test_api_forms_submission_payment_fields()
{
	$row = array(
		'id' => 5, 'page_id' => 3, 'reference_code' => 'R', 'complete' => 1, 'contact_id' => 0,
		'quiz_score' => 0, 'submitted_timestamp' => 0, 'last_modified_timestamp' => 0,
	);

	$none = api_form_submission_present($row, array());
	pg_assert_same(null, $none['order_number'], 'no fee: no order number');
	pg_assert_same(null, $none['paid'], 'no fee: paid is null');

	$unpaid = api_form_submission_present($row + array('order_id' => 9, 'paid_at' => 0, 'order_number' => '0'), array());
	pg_assert_same(false, $unpaid['paid'], 'a fee order not yet paid');
	pg_assert_same(null, $unpaid['order_number'], 'an incomplete order has no number');

	$paid = api_form_submission_present($row + array('order_id' => 9, 'paid_at' => 1700000000, 'order_number' => '1009'), array());
	pg_assert_same(true, $paid['paid'], 'paid');
	pg_assert_same('1009', $paid['order_number'], 'the order number');

	$schema = api_form_submission_schema();
	pg_assert_same('string?', $schema['order_number'], 'declared');
	pg_assert_same('boolean?', $schema['paid'], 'declared');
}

// The fields of a choice form, as pg_submitted_form_fields() reads them.
function api_forms_test_fields()
{
	return array(
		array('id' => 1, 'name' => 'email', 'type' => 'email address', 'multiple' => 0),
		array('id' => 2, 'name' => 'topic', 'type' => 'pick list', 'multiple' => 0),
		array('id' => 3, 'name' => 'tags', 'type' => 'check box', 'multiple' => 0),
		array('id' => 4, 'name' => 'cv', 'type' => 'file upload', 'multiple' => 0),
		array('id' => 5, 'name' => 'sizes', 'type' => 'pick list', 'multiple' => 1),
		array('id' => 6, 'name' => 'sign', 'type' => 'signature', 'multiple' => 0),
	);
}

// Answers are keyed by field id: one string a field, a list for a field that
// takes several, and a choice must be one the field offers.
function test_api_forms_submission_answers_by_field()
{
	$choices = array(2 => array('sales', 'support'), 3 => array('a', 'b'), 5 => array('s', 'm'));

	$answers = api_form_submission_answers(array(
		array('name' => 'email', 'value' => " ada@example.com \r\n"),
		array('name' => 'topic', 'value' => 'support'),
		array('name' => 'tags', 'value' => 'a'),
		array('name' => 'tags', 'value' => 'b'),
		array('name' => 'sizes', 'value' => 'm'),
	), api_forms_test_fields(), $choices);

	pg_assert_same(array(1 => 'ada@example.com', 2 => 'support', 3 => array('a', 'b'), 5 => array('m')), $answers, 'answers');
	pg_assert_same(array(), api_form_submission_answers(array(), api_forms_test_fields(), $choices), 'nothing sent');
	pg_assert_same(array(2 => ''), api_form_submission_answers(array(array('name' => 'topic', 'value' => null)), api_forms_test_fields(), $choices), 'an empty choice is no answer');
}

// What a new submission cannot carry is refused with a sentence, not stored.
function test_api_forms_submission_answers_refusals()
{
	$choices = array(2 => array('sales', 'support'), 3 => array('a', 'b'));
	$fields = api_forms_test_fields();

	$refused = array(
		'unknown field' => array(array('name' => 'phone', 'value' => '1')),
		'file upload'   => array(array('name' => 'cv', 'value' => 'x.pdf')),
		'signature'     => array(array('name' => 'sign', 'value' => 'data:image/png;base64,AA')),
		'not a choice'  => array(array('name' => 'topic', 'value' => 'billing')),
		'twice'         => array(array('name' => 'email', 'value' => 'a@b.co'), array('name' => 'email', 'value' => 'c@d.co')),
		'not an object' => array('email'),
		'array value'   => array(array('name' => 'email', 'value' => array('a'))),
		'too long'      => array(array('name' => 'email', 'value' => str_repeat('a', 10001))),
	);

	foreach ($refused as $label => $items) {
		pg_assert_true(is_string(api_form_submission_answers($items, $fields, $choices)), $label);
	}
}

// The create and delete handlers go through the write path the screens use,
// stop a rehearsal above it, and announce through pg_announce().
function test_api_forms_submission_writes_share_the_screen_path()
{
	$create = api_forms_test_function_source('api_form_submission_create');

	pg_assert_contains('pg_submitted_form_create_check(', $create, 'the screen\'s checks');
	pg_assert_contains('pg_submitted_form_create(', $create, 'the screen\'s write');
	pg_assert_contains("pg_announce('form.submitted'", $create, 'announced');
	pg_assert_true(strpos($create, 'api_dry_run_stop(') < strpos($create, 'pg_submitted_form_create($liveform'), 'rehearsal stops above the write');
	pg_assert_true(strpos($create, 'pg_submitted_form_create_check(') < strpos($create, 'api_dry_run_stop('), 'checks run in a rehearsal');

	$delete = api_forms_test_function_source('api_form_submission_delete');

	pg_assert_contains('pg_submitted_form_delete(', $delete, 'the screen\'s delete');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'pg_submitted_form_delete('), 'rehearsal stops above the delete');

	$writes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
	pg_assert_contains("pg_announce('form.submission.deleted'", $writes, 'the delete announces');

	require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
	$events = api_webhook_events();
	pg_assert_true(isset($events['form.submission.deleted']), 'the event is in the catalogue');
	pg_assert_true(isset($events['form.submitted']), 'form.submitted stays');
}

// A check box group and a multiple pick list take several answers.
function test_api_forms_field_takes_many()
{
	pg_assert_true(api_form_field_takes_many(array('type' => 'check box', 'multiple' => 0)), 'check box');
	pg_assert_true(api_form_field_takes_many(array('type' => 'pick list', 'multiple' => 1)), 'multiple pick list');
	pg_assert_false(api_form_field_takes_many(array('type' => 'pick list', 'multiple' => 0)), 'pick list');
	pg_assert_false(api_form_field_takes_many(array('type' => 'radio button', 'multiple' => 1)), 'radio button');
}

// A field row as api_form_fields_present() reads it.
function api_forms_test_field_row($type, $wysiwyg)
{
	return array(
		'id' => 4, 'name' => 'body', 'label' => 'Body', 'type' => $type, 'required' => 1,
		'sort_order' => 2, 'multiple' => 0, 'office_use_only' => 0, 'contact_field' => '',
		'default_value' => '', 'wysiwyg' => $wysiwyg,
	);
}

// Only a text area drawn with the rich-text editor takes HTML; the other
// fields carry null for its facts and keep their options.
function test_api_forms_rich_text_field_facts()
{
	pg_assert_true(api_form_field_is_rich_text(api_forms_test_field_row('text area', 1)), 'rich text area');
	pg_assert_false(api_form_field_is_rich_text(api_forms_test_field_row('text area', 0)), 'plain text area');
	pg_assert_false(api_form_field_is_rich_text(api_forms_test_field_row('text box', 1)), 'a text box is never rich');

	$rich = api_form_field_present(api_forms_test_field_row('text area', 1));
	pg_assert_true($rich['rich_text'], 'rich_text');
	pg_assert_same(api_form_html_max_length(), $rich['max_length'], 'max_length');
	pg_assert_true(is_array($rich['html_allowed']) && in_array('p', $rich['html_allowed']['tags'], true), 'html_allowed');

	$options = array(array('id' => 1, 'label' => 'Yes', 'value' => 'yes', 'selected' => false));
	$plain = api_form_field_present(api_forms_test_field_row('pick list', 0), $options);
	pg_assert_false($plain['rich_text'], 'pick list is not rich');
	pg_assert_same(null, $plain['html_allowed'], 'no html_allowed');
	pg_assert_same(null, $plain['max_length'], 'no max_length');
	pg_assert_same($options, $plain['options'], 'the choices stay');
}

// html_allowed is the sanitiser's own list under the visitor profile: class
// and rel are not offered, an image must be on the site's host.
function test_api_forms_html_allowed_follows_the_sanitiser()
{
	$rules = pg_rich_text_allow_list();
	$allowed = api_form_html_allowed('www.example.com');

	pg_assert_same(array_keys($rules['tags']), $allowed['tags'], 'tags');
	pg_assert_same(array_keys($rules['url_schemes']), $allowed['url_schemes'], 'schemes');
	pg_assert_same(array_keys($rules['visitor_style_properties']), $allowed['style_properties'], 'style properties');
	pg_assert_same(array('www.example.com'), $allowed['image_hosts'], 'image hosts');
	pg_assert_same(array(), api_form_html_allowed('')['image_hosts'], 'no host, no list');
	pg_assert_false(in_array('class', $allowed['attributes'], true), 'class is removed for visitors');
	pg_assert_true(in_array('script', $allowed['dropped_tags'], true), 'script is dropped');

	$by_tag = array();
	foreach ($allowed['tag_attributes'] as $entry) {
		$by_tag[$entry['tag']] = $entry['attributes'];
	}
	pg_assert_same(array('href', 'target'), $by_tag['a'], 'a: rel is written by the site');
	pg_assert_same(array('src'), $by_tag['img'], 'img');
}

// What the list promises is what the sanitiser keeps: every listed tag and
// tag attribute survives, and an attribute off the list does not.
function test_api_forms_html_allowed_is_what_the_sanitiser_keeps()
{
	$allowed = api_form_html_allowed('');

	foreach ($allowed['tags'] as $tag) {
		$void = in_array($tag, array('br', 'hr', 'img', 'col'), true);
		$html = $void ? '<' . $tag . ' title="t">' : '<' . $tag . ' title="t">x</' . $tag . '>';
		if ($tag === 'img') { $html = '<img src="/a.png" title="t">'; }
		if (in_array($tag, array('li', 'dt', 'dd', 'tr', 'td', 'th', 'caption', 'thead', 'tbody', 'tfoot', 'colgroup', 'col'), true)) { continue; }
		pg_assert_contains('<' . $tag . ' ', pg_sanitize_rich_text($html), 'kept: ' . $tag);
	}

	$link = pg_sanitize_rich_text('<a href="https://example.org/" target="_blank" onclick="x()" id="i">x</a>');
	pg_assert_contains('href="https://example.org/"', $link, 'href');
	pg_assert_contains('target="_blank"', $link, 'target');
	pg_assert_false(strpos($link, 'onclick') !== false, 'event handler removed');
	pg_assert_false(strpos($link, 'id="i"') !== false, 'id removed');

	pg_assert_contains('type="a"', pg_sanitize_rich_text('<ol type="a"><li>x</li></ol>'), 'ol type');
	pg_assert_false(strpos(pg_sanitize_rich_text('<a href="javascript:alert(1)">x</a>'), 'href') !== false, 'scheme off the list');
	pg_assert_contains('href="tel:+1"', pg_sanitize_rich_text('<a href="tel:+1">x</a>'), 'tel on the list');
}

// A record is read at /<page>/{address_name} on a form with pretty
// addresses, /<page>?r={reference_code} otherwise.
function test_api_forms_record_url_pattern()
{
	pg_assert_same('https://example.com/blog-post/{address_name}', api_form_record_url_pattern('https://example.com/', 'blog-post', true), 'pretty');
	pg_assert_same('https://example.com/blog-post?r={reference_code}', api_form_record_url_pattern('https://example.com/', 'blog-post', false), 'reference code');
	pg_assert_same('/my%20post?r={reference_code}', api_form_record_url_pattern('/', 'my post', false), 'page name encoded');
}

// GET /forms/{id} carries the facts; the list does not run their queries.
function test_api_forms_get_carries_the_form_facts()
{
	pg_assert_contains('api_form_facts(', api_forms_test_function_source('api_forms_get'), 'single form');
	pg_assert_false(strpos(api_forms_test_function_source('api_forms_list'), 'api_form_facts(') !== false, 'not per row of the list');
	pg_assert_contains('wysiwyg', api_forms_test_function_source('api_form_fields_present'), 'the rich-text flag is read');
}

// A form shown only on a classic form item view page still gets its record
// address; a page limited to the record's submitter does not count.
function test_api_forms_record_page_falls_back_to_classic_pages()
{
	$facts = api_forms_test_function_source('api_form_facts');
	pg_assert_true(strpos($facts, 'pg_ap_record_page(') < strpos($facts, 'api_form_classic_record_page('), 'designed record view first, classic page after');

	$classic = api_forms_test_function_source('api_form_classic_record_page');
	pg_assert_contains("page.page_type = 'form item view'", $classic, 'classic record pages');
	pg_assert_contains("submitter_security = '0'", $classic, 'not limited to the submitter');
	pg_assert_contains("get_access_control_type(\$folder) !== 'public'", $classic, 'public folders only');
}
