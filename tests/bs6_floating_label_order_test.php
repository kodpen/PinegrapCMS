<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order of a floating label and its field: Bootstrap 6 floats
 * a `.form-floating` label only when it comes before the field
 * (`label:has(~ .form-control)`), so no panel file may put it after.
 * Site output (Bootstrap 5.3.8, label after the field) is left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/bs6_rules.php');

function test_bs6_floating_label_after_its_field_is_found()
{
	$after = '<div class="form-floating mb-3"><select class="form-control" id="a"></select><label for="a">A</label></div>';
	$field = "<div class=\"form-floating\">\n<?=\$liveform->output_field(array('type' => 'select'))?>\n<label for=\"b\">B</label>\n</div>";
	$js = "html += '<div class=\\\"form-floating\\\"><input class=\\\"form-control\\\"><label>C</label></div>';";

	// The offset is the wrapper's class attribute.
	pg_assert_same(array(5), bs6_floating_labels_after_control($after));
	pg_assert_same(array(5), bs6_floating_labels_after_control($field));
	pg_assert_same(1, count(bs6_floating_labels_after_control($js)));
}

function test_bs6_floating_label_before_its_field_passes()
{
	$before = '<div class="form-floating"><label for="a">A</label><select class="form-control" id="a"></select></div>';
	$no_label = '<div class="form-floating"><input class="form-control" placeholder="x"></div>';
	$other = '<div class="form-floating-x"><select></select><label>A</label></div>'
		. '<div class="form-floating"><label>B</label><input></div><div><input><label>C</label></div>';

	pg_assert_same(array(), bs6_floating_labels_after_control($before));
	pg_assert_same(array(), bs6_floating_labels_after_control($no_label));
	pg_assert_same(array(), bs6_floating_labels_after_control($other));
}

function test_bs6_floating_label_no_panel_file_puts_it_after()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$wrong = array();

	foreach (bs6_scope_files($root) as $path => $kind) {
		if (!bs6_codemod_converts($path)) {
			continue;
		}

		$source = (string) file_get_contents($root . '/' . $path);

		if (strpos($source, 'form-floating') === false) {
			continue;
		}

		bs6_current_path($path);
		$site_output = bs6_site_output_ranges($source);

		foreach (bs6_floating_labels_after_control($source) as $offset) {
			if (!($site_output && bs6_in_ranges($offset, $site_output))) {
				$wrong[] = $path . ':' . bs6_line_of($source, $offset);
			}
		}
	}

	bs6_current_path(false);

	pg_assert_same(array(), $wrong);
}
