<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names the theme screens store and open: prepare_file_name()
 * never leaves "..", pg_design_file_name_unsafe() refuses only names that
 * leave the files folder, pg_upload_file_missing() tells "no file chosen"
 * before the name is prepared, and the screens' redirects keep where the
 * user came from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A name with ".." was stored and the theme, CSS and design file screens
// then refused to open it, so it could be neither fixed nor deleted.
function test_theme_file_names_no_double_dot_stored()
{
	pg_assert_same('file._pg-test-evil.css', prepare_file_name('../pg-test-evil.css'), 'parent path');
	pg_assert_same('old.theme.css', prepare_file_name('old..theme.css'), 'two dots inside');
	pg_assert_same('a.css', prepare_file_name('a....css'), 'a run of dots');
	pg_assert_same('file.css', prepare_file_name('..css'), 'leading dots');

	// The length limit can cut the part before the extension right after a dot.
	$long = str_repeat('a', 95) . '.' . str_repeat('b', 10) . '.css';
	pg_assert_false(strpos(prepare_file_name($long), '..') !== false, 'the cut leaves no double dot');
}

function test_theme_file_names_unsafe_only_outside_the_folder()
{
	pg_assert_true(pg_design_file_name_unsafe(''), 'empty');
	pg_assert_true(pg_design_file_name_unsafe('.'), 'dot');
	pg_assert_true(pg_design_file_name_unsafe('..'), 'parent');
	pg_assert_true(pg_design_file_name_unsafe('../x.css'), 'slash');
	pg_assert_true(pg_design_file_name_unsafe('..\\x.css'), 'backslash');
	pg_assert_true(pg_design_file_name_unsafe("x\0.css"), 'nul');
	pg_assert_true(pg_design_file_name_unsafe(array('x.css')), 'array');
	pg_assert_false(pg_design_file_name_unsafe('file.._pg-test-evil.css'), 'a name stored with two dots opens');
	pg_assert_false(pg_design_file_name_unsafe('theme_smoothy[1].css'), 'plain name');
}

function test_theme_file_names_upload_missing()
{
	pg_assert_true(pg_upload_file_missing(null), 'no entry');
	pg_assert_true(pg_upload_file_missing(array('name' => '', 'error' => UPLOAD_ERR_NO_FILE)), 'nothing chosen');
	pg_assert_true(pg_upload_file_missing(array('name' => '  ', 'error' => UPLOAD_ERR_OK)), 'blank name');
	pg_assert_true(pg_upload_file_missing(array('name' => array('a.css'), 'error' => array(0))), 'array box');
	pg_assert_false(pg_upload_file_missing(array('name' => 'a.css', 'error' => UPLOAD_ERR_OK)), 'a file');
	pg_assert_false(pg_upload_file_missing(array('name' => 'a.css', 'error' => UPLOAD_ERR_INI_SIZE)), 'too big is not missing');
}

function test_theme_file_names_screens()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	foreach (array('edit_theme_file.php', 'edit_theme_css.php', 'edit_design_file.php') as $screen) {
		$source = file_get_contents($root . $screen);
		pg_assert_contains('pg_design_file_name_unsafe(', $source, $screen . ' gate');
		pg_assert_false(strpos($source, "'..') !== false") !== false, $screen . ' no plain double-dot refusal');
	}

	$css = file_get_contents($root . 'edit_theme_css.php');
	pg_assert_false(strpos($css, "'&send_to=' . urlencode(\$_POST['send_to'] ?? ''));") !== false, 'Save keeps from');
	pg_assert_contains("\$edit_theme_css_back = ", $css, 'one back target for Cancel and Save and Return');

	$add = file_get_contents($root . 'add_theme_file.php');
	pg_assert_contains("pg_upload_file_missing(\$_FILES['custom_css_file'] ?? null)", $add, 'missing file read before the name');
	$custom = substr($add, strpos($add, 'pg_upload_file_missing('));
	pg_assert_false(strpos($custom, 'output_error(lang(array(') !== false, 'taken upload name is a field error');
	pg_assert_contains("mark_error('custom_css_file', lang(array('string' => '{var:1} already exists.", $custom, 'on the file box');
	pg_assert_contains("lang('The Theme Type is not valid.')", $add, 'system refused on the server');
	pg_assert_contains('pg_file_name_saved_as_notice(', $add, 'upload says the new name');
	pg_assert_contains('pg_file_name_saved_as_notice(', file_get_contents($root . 'edit_theme_file.php'), 'edit says the new name');
}

function test_theme_file_names_saved_as_notice()
{
	pg_assert_same('', pg_file_name_saved_as_notice('a.css', 'a.css'), 'unchanged');
	pg_assert_contains('pg-test_Tema_gusiocI.css', pg_file_name_saved_as_notice('pg-test Tema ğüşıöçİ.css', 'pg-test_Tema_gusiocI.css'), 'changed');
	pg_assert_contains('&lt;b&gt;', pg_file_name_saved_as_notice('x', '<b>'), 'escaped');
}

// Sparse rules (a theme made from a short template, an upgrade) leave most
// keys unset; the CSS is built without a warning.
function test_theme_file_names_system_css_sparse_rules_quiet()
{
	require_once dirname(__DIR__) . '/pinegrap/generate_system_theme_css.php';
	$warnings = array();
	set_error_handler(function ($no, $message) use (&$warnings) {
		$warnings[] = $message;
		return true;
	});
	$css = generate_system_theme_css(array('site_wide' => array('base_object' => array('text' => array('font_family' => 'Arial')))));
	restore_error_handler();
	pg_assert_true(is_string($css), 'css built');
	pg_assert_same(array(), $warnings, 'no warnings');
}
