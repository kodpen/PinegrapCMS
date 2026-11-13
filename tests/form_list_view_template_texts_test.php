<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a form list view's built-in layouts (includes/templates/
 * form_list_view.php and form_list_view_system.php) print every text a
 * visitor reads through lang(), and that the Turkish starter site's theme
 * script labels a file field in Turkish (#9026).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_form_list_view_templates_translate_their_texts()
{
	foreach (array('form_list_view.php', 'form_list_view_system.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . $file);

		foreach (array(
			"lang('Remove Browse')",
			"lang('Remove Advanced Search')",
			"lang('Add Advanced Search')",
			"lang('Clear')",
			"lang('You may browse or search above to find results.')",
			"lang('You may browse above to find results.')",
			"lang('You may search above to find results.')",
			"lang('There are no results.')",
			"'Found {var:1} result{suffix:1}.'",
		) as $call) {
			pg_assert_contains($call, $source, $file);
		}

		foreach (array('title="Remove Browse"', 'title="Clear"', 'Advanced Search"', 'You may', 'There are no results.</', 'Found <?=') as $literal) {
			pg_assert_false(strpos(str_replace(array("lang('You may", "lang('There are no results.')"), '', $source), $literal) !== false, $file . ': no English literal ' . $literal);
		}
	}

	$custom = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/form_list_view.php');

	pg_assert_contains("lang('Previous')", $custom);
	pg_assert_contains("lang('Next')", $custom);
	pg_assert_contains("'Page {var:1} of {var:2}'", $custom);
}

function test_form_list_view_result_count_sentence()
{
	pg_assert_same('Found 1 result.', lang(array('string' => 'Found {var:1} result{suffix:1}.', 'vars' => array('1'), 'suffix' => '')));
	pg_assert_same('Found 2 results.', lang(array('string' => 'Found {var:1} result{suffix:1}.', 'vars' => array('2'), 'suffix' => 's')));
}

function test_turkish_starter_file_field_label()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/turkish_default/files/scripts.js');

	pg_assert_false(strpos($script, 'Attach a File') !== false, 'no English label on the Turkish starter site');
	pg_assert_contains('<i class="ti-clip"></i> Dosya Ekle</span>', $script);
}
