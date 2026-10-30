<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Names a user typed or a stored record carries are escaped where the
 * panel draws them as HTML: the API application drawer's Owner line and
 * the iyzico protected currency list on Site Settings. Each row is a file,
 * one line of it and how many times that line occurs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_stored_names_are_escaped()
{
	$lines = array(
		array('api_settings.php', '" <b>" + esc(app.owner) + "</b> &middot; " + esc(app.created);', 1),
		array('includes/settings/prep.php', '\'<option value="\' . h($currency[\'code\']) . \'" selected="selected">\' . h($currency[\'code\']) . \' - \' . h($currency[\'name\']) . \'</option>\'', 1),
		array('includes/settings/prep.php', '\'<option value="\' . h($currency[\'code\']) . \'" >\' . h($currency[\'code\']) . \' - \' . h($currency[\'name\']) . \'</option>\'', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
