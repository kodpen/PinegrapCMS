<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the Width, Height, Delay and Frequency boxes of the auto
 * dialog Create and Edit screens (includes/templates/edit_auto_dialog.php):
 * they take no input mask, so what was typed reaches the save, which reads
 * thousands groups and refuses a sign, a decimal part or letters
 * (pg_auto_dialog_form_whole_number()). The integer mask dropped a typed "."
 * or "-": 1.5 hours was stored as 15 and -5 seconds as 5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');

function test_auto_dialog_number_boxes_are_not_masked()
{
	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');

	pg_assert_same(0, substr_count($template, 'data-inputmask'), 'no mask rewrites a typed value');

	foreach (pg_auto_dialog_form_number_limits() as $field => $max) {
		$at = strpos($template, "'id' => '" . $field . "'");
		pg_assert_true($at !== false, $field . ' is printed');

		preg_match("/'maxlength'=>'([0-9]+)'/", (string) substr($template, (int) $at, 400), $match);
		$grouped = number_format($max, 0, ',', '.');

		pg_assert_same(strlen($grouped), (int) ($match[1] ?? 0), $field . ': room for its largest value with separators (' . $grouped . ')');
		pg_assert_same((string) $max, pg_auto_dialog_form_whole_number($grouped, $max), $field . ': that value is read');
	}
}

// What the mask turned into another number now reaches the save, which
// refuses it with its message instead of storing a different value.
function test_auto_dialog_number_boxes_refuse_what_the_mask_rewrote()
{
	foreach (array('1.5', '-5', '-3', 'abc', '1,5') as $typed) {
		pg_assert_same(null, pg_auto_dialog_form_whole_number($typed, 999999), 'refused: ' . $typed);
	}
}
