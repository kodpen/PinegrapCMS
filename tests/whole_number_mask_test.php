<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel boxes of whole-number columns: they carry Inputmask's
 * "integer" alias. The "decimal" alias takes "." as its decimal point (and
 * turns a typed "," into it), so a Turkish "1.000" was saved as 1.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_whole_number_boxes_use_the_integer_mask()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$boxes = array(
		'includes/forms/ad_region_form.php' => array('transition_duration'),
		'edit_form_list_view.php' => array('maximum_number_of_results', 'maximum_number_of_results_per_page'),
		'includes/forms/calendar_event_form.php' => array('number_of_remaining_spots', 'number_of_initial_spots'),
	);

	foreach ($boxes as $file => $names) {
		$lines = file($root . $file);

		foreach ($names as $name) {
			$found = null;

			foreach ($lines as $line) {
				if (preg_match('/(name="|\'name\'\s*=>\s*\')' . preg_quote($name, '/') . '[\'"]/', $line)) {
					$found = $line;
					break;
				}
			}

			pg_assert_true($found !== null, $file . ' draws the ' . $name . ' box');
			pg_assert_true(
				strpos((string) $found, 'data-inputmask-alias="integer"') !== false
					|| preg_match("/'data-inputmask-alias'\s*=>\s*'integer'/", (string) $found) === 1,
				$file . ' ' . $name . ' box uses the integer mask');
		}
	}
}
