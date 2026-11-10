<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the searchable file and image pickers
 * (includes/fn/file_picker_search.php, file_picker_search.php): who may
 * search which kind, the files condition each kind builds, the folded name
 * match, the markup hooks and the stored choice, and the screens wired to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_picker_search_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The settings kinds stay behind the settings gate; the file kind is open to
// a role 3 user only with edit rights; an unknown or listed kind is unknown.
function test_file_picker_access_verdict()
{
	foreach (array(0, 1, 2) as $role) {
		foreach (array('site_icon', 'public_image', 'file') as $kind) {
			pg_assert_same('ok', pg_file_picker_access_verdict($kind, $role, false), $kind . ' for role ' . $role);
		}
	}

	pg_assert_same('denied', pg_file_picker_access_verdict('site_icon', 3, true), 'a User never searches the settings pictures');
	pg_assert_same('denied', pg_file_picker_access_verdict('public_image', '3', true));
	pg_assert_same('ok', pg_file_picker_access_verdict('file', 3, true), 'a User with edit rights searches files');
	pg_assert_same('denied', pg_file_picker_access_verdict('file', 3, false), 'a User without edit rights does not');
	pg_assert_same('unknown', pg_file_picker_access_verdict('', 0, false));
	pg_assert_same('unknown', pg_file_picker_access_verdict('attachments', 0, false), 'no kind the table lacks');
	pg_assert_same('unknown', pg_file_picker_access_verdict(array('file'), 0, false), 'a kind posted as a list');
	pg_assert_same('unknown', pg_file_picker_access_verdict('page', 0, false), 'the picker kinds added later stay unknown here');
	pg_assert_same('unknown', pg_file_picker_access_verdict('image', 0, false));
}

function test_file_picker_where()
{
	$kinds = pg_file_picker_kinds();

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (type IN ('png', 'svg')) AND ((attachment = 0) AND (folder IN (1, 4))) AND (folder NOT IN (9, 10))",
		pg_file_picker_where($kinds['site_icon'], '(attachment = 0) AND (folder IN (1, 4))', array(9, '10', 9), null),
		'site icon: its types, the public condition, never the bin');

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (folder NOT IN (9)) AND (folder IN (3, 5))",
		pg_file_picker_where($kinds['file'], '', array(9), array('3', 5, 3)),
		'file: every type and design file, a User kept to their folders');

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (folder IN (-1))",
		pg_file_picker_where($kinds['file'], '', array(), array()),
		'a User who may edit no folder finds nothing');

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (type IN ('jpg')) AND (folder IN (-1))",
		pg_file_picker_where(array('types' => array('JPG', "png') OR (1=1"), 'public' => true, 'design' => true), '', array(), null),
		'a public kind without its condition lists nothing; only plain types are kept');

	pg_assert_same(
		"(name != '') AND (attachment = 0) AND (design = 0)",
		pg_file_picker_where(array('types' => array(), 'public' => false, 'design' => false), '', array(), null),
		'design files left out when the kind says so');

	pg_assert_same(pg_public_image_file_types(), $kinds['public_image']['types']);
	pg_assert_same('id', $kinds['file']['value'], 'a short link stores the file id');
	pg_assert_same('name', $kinds['public_image']['value'], 'the settings store the name');
}

// The column's collation keeps i and ı apart; every i of the folded text is
// a one-letter wildcard and LIKE's own characters are escaped.
function test_file_picker_like_term()
{
	pg_assert_same('logo', pg_file_picker_like_term('Logo'));
	pg_assert_same('_s_k', pg_file_picker_like_term('Işık'));
	pg_assert_same('_stanbul', pg_file_picker_like_term(' İstanbul '));
	pg_assert_same('cicek', str_replace('_', 'i', pg_file_picker_like_term('ÇİÇEK')), 'accents dropped');
	pg_assert_same('50\\%\\_off', pg_file_picker_like_term('50%_off'));
	pg_assert_same('', pg_file_picker_like_term('   '));
}

function test_file_picker_results()
{
	$rows = array(
		array('id' => '7', 'name' => 'acon.png'),
		array('id' => '3', 'name' => 'dark-icon.png'),
		array('id' => '5', 'name' => 'Işık-icon.svg'),
		array('id' => '8', 'name' => 'icon.png'),
		array('id' => '9', 'name' => ''),
	);

	pg_assert_same(
		array(
			array('id' => 'icon.png', 'text' => 'icon.png'),
			array('id' => 'dark-icon.png', 'text' => 'dark-icon.png'),
			array('id' => 'Işık-icon.svg', 'text' => 'Işık-icon.svg'),
		),
		pg_file_picker_results($rows, 'İCON', 'name', 30),
		'folded match, names starting with the text first, wildcard misses dropped');

	pg_assert_same(
		array(array('id' => 5, 'text' => 'Işık-icon.svg')),
		pg_file_picker_results($rows, 'isik', 'id', 30),
		'"isik" finds "Işık", answered by id');

	pg_assert_same(array(array('id' => 'icon.png', 'text' => 'icon.png')), pg_file_picker_results($rows, 'icon', 'name', 1), 'capped');
	pg_assert_same(array(), pg_file_picker_results($rows, '  ', 'name', 30), 'no text, no answer');
	pg_assert_same(30, pg_file_picker_limit());
}

function test_file_picker_markup_hooks()
{
	pg_assert_same('/pinegrap/file_picker_search.php?kind=public_image', pg_file_picker_source('public_image', '/pinegrap/'));
	pg_assert_same(
		array('data-pg-combobox' => '', 'data-pg-source' => '/p/file_picker_search.php?kind=file', 'data-pg-min-length' => '1'),
		pg_file_picker_field_attributes('file', '/p/'));
	pg_assert_same(
		' data-pg-combobox data-pg-source="/p/file_picker_search.php?kind=site_icon" data-pg-min-length="1"',
		pg_file_picker_attributes('site_icon', '/p/'));
}

// Only the stored choice is printed; the search brings the rest.
function test_file_picker_stored_choice()
{
	pg_assert_same('<option value="">(none &amp; all)</option>', pg_file_picker_public_options('', '(none & all)'), 'nothing stored');
	pg_assert_same(
		'<option value="">-</option><option value="https://cdn.example.com/a.png" selected="selected">https://cdn.example.com/a.png</option>',
		pg_file_picker_public_options('https://cdn.example.com/a.png', '-'),
		'an old typed address stays chosen');

	pg_assert_same(array('-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('File')))) . '-' => ''), pg_file_picker_id_options(''), 'no file chosen');
	pg_assert_same(1, count(pg_file_picker_id_options(array(4))), 'an id posted as a list is none');

	pg_assert_same('a.pdf', pg_file_picker_id_label('a.pdf', false));
	pg_assert_same('a.pdf [' . lang('IN RECYCLE BIN') . ']', pg_file_picker_id_label('pgbin_4_a.pdf', true, 'a.pdf'), 'the name it had, marked');
	pg_assert_same('b.pdf [' . lang('IN RECYCLE BIN') . ']', pg_file_picker_id_label('b.pdf', true), 'no parked name');
}

// The endpoint refuses in JSON before validate_user(), reads one value per
// parameter and asks the verdict; the module is loaded for every screen.
function test_file_picker_endpoint_wired()
{
	$endpoint = file_picker_search_test_source('file_picker_search.php');

	pg_assert_true(strpos($endpoint, "(USER_LOGGED_IN !== true)") < strpos($endpoint, '$user = validate_user();'), 'no session answers in JSON, not with the sign-in redirect');
	pg_assert_contains("(isset(\$_GET['kind']) && is_string(\$_GET['kind']))", $endpoint);
	pg_assert_contains("(isset(\$_GET['q']) && is_string(\$_GET['q']))", $endpoint);
	pg_assert_contains("pg_file_picker_access_verdict(\$kind, \$user['role'], ((int) \$user['role'] >= 3) && no_acl_check(\$user['id']))", $endpoint);
	pg_assert_contains("header('Content-Type: application/json; charset=utf-8');", $endpoint);
	pg_assert_contains('pg_file_picker_search($kind, $query, $user)', $endpoint);

	// The search answers through the picker's registry and its search.
	$search = file_picker_search_test_source('includes/fn/file_picker_search.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/picker.php');", $search);
	pg_assert_contains('pg_picker_search_rows($kind_name, $query, $user)', $search);

	$picker = file_picker_search_test_source('includes/fn/picker.php');

	pg_assert_contains('pg_recycle_bin_folder_ids(),', $picker, 'the bin is always left out');
	pg_assert_contains("get_folders_that_user_has_access_to(\$user['id'])", $picker, 'a User searches their folders only');
	pg_assert_contains("e(pg_picker_like_term(\$query))", $picker, 'the typed text is escaped');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_picker_search.php');", file_picker_search_test_source('functions.php'));
}

// The screens converted: Site Settings pictures, PEM file, short links.
function test_file_picker_screens_wired()
{
	$fediverse = file_picker_search_test_source('includes/settings/fediverse.php');

	pg_assert_contains("pg_file_picker_public_options(\$ap_stored['icon'], lang('(the organization logo)'))", $fediverse);
	pg_assert_contains("id=\"ap_icon\" class=\"form-control\"' . pg_file_picker_attributes('public_image') . '>", $fediverse);
	pg_assert_false(strpos($fediverse, 'SELECT name FROM files') !== false, 'the avatar no longer lists every picture');

	pg_assert_contains("\$seo_card[1] .= pg_fields_script();", file_picker_search_test_source('includes/settings/seo.php'));
	pg_assert_contains("((\$output_organization_logo_picker !== '') ? pg_fields_script() : '')", file_picker_search_test_source('includes/settings/general.php'));

	pg_assert_contains('id="ecommerce_first_data_global_gateway_pem_file_name" data-pg-combobox>', file_picker_search_test_source('includes/settings/commerce.php'), 'the short PEM list searches in the browser');
	pg_assert_contains('pg_stored_file_option($ecommerce_first_data_global_gateway_pem_file_name, pg_stored_file_exists(', file_picker_search_test_source('includes/settings/prep.php'), 'a stored PEM name stays chosen');

	$form = file_picker_search_test_source('includes/forms/short_link_form.php');

	pg_assert_contains("+ pg_file_picker_field_attributes('file')) . pg_fields_script()", $form, 'the short link file picker searches the server');
}
