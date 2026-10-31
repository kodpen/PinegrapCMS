<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Find & Replace card of mass_edit.php: a refused apply
 * (empty Find, no field ticked, invalid pattern) comes back with what was
 * typed and ticked, and the Title and Description defaults only show on a
 * first draw.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_mass_edit_find_replace_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/mass_edit.php');
}

function test_mass_edit_find_replace_fields_are_liveform_fields()
{
	$source = _test_mass_edit_find_replace_source();

	foreach (array("'name'=>'find_text'", "'name'=>'replace_text'", "'name'=>'use_regex'") as $name) {
		pg_assert_contains('$liveform->output_field(array(' . "'type'=>", $source, 'fields are drawn through liveform');
		pg_assert_true(strpos($source, $name) !== false, $name . ' is a liveform field');
	}
	pg_assert_same(3, substr_count($source, "'name'=>'fr_fields[]'"), 'the three field boxes are liveform fields');
	pg_assert_false((bool) preg_match('/<input[^>]*name="(find_text|replace_text|use_regex|fr_fields\[\])"/', $source), 'no hand-written Find & Replace input is left');
	pg_assert_true((bool) preg_match("/'name'=>'find_text'.*'required'=>'required'\\)\\) \\. '\\n/", $source), 'Find is required in the browser');
}

function test_mass_edit_find_replace_defaults_only_on_first_draw()
{
	$source = _test_mass_edit_find_replace_source();

	pg_assert_contains('$fr_defaults = $liveform->prefill() ? array(\'checked\' => \'checked\') : array();', $source, 'defaults depend on prefill()');
	pg_assert_same(2, substr_count($source, "\$fr_defaults + array('type'=>'checkbox', 'name'=>'fr_fields[]'"), 'Title and Description are the defaults');
	pg_assert_true((bool) preg_match("/output_field\\(array\\('type'=>'checkbox', 'name'=>'fr_fields\\[\\]', 'id'=>'fr_page_name'/", $source), 'Page Name has no default');
}

function test_mass_edit_find_replace_refusals_keep_the_post()
{
	$source = _test_mass_edit_find_replace_source();
	$start = strpos($source, "\$_POST['action'] === 'find_replace'");
	pg_assert_true($start !== false, 'the Find & Replace branch');
	$branch = substr($source, (int) $start, (int) strpos($source, 'exit();', (int) $start) - (int) $start);

	pg_assert_same(3, substr_count($branch, '$liveform->mark_error('), 'three refusals');
	pg_assert_same(1, substr_count($branch, "\$liveform->add_fields_to_session(array('trim' => false));"), 'the post is kept once, untrimmed');
	pg_assert_contains("if (\$liveform->check_form_errors() == true) {\n            // A refused apply comes back with what was typed and ticked, kept\n            // untrimmed because spaces count in a pattern.\n            \$liveform->add_fields_to_session(array('trim' => false));\n        }\n        header('Location: ", $branch, 'only a refused apply keeps the post, right before the redirect');
}
