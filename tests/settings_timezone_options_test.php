<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The time zone pick list of the settings screen (includes/settings/prep.php)
 * labels its "Server Default" and "Custom" options through lang(), so a
 * Turkish panel does not show them in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_settings_timezone_options_translated()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');

	pg_assert_false((bool) preg_match('/>\s*(Server Default|Custom):/', $source), 'prep.php: no English option label outside lang()');
	pg_assert_contains("lang(array('string' => 'Server Default: {var:1}', 'vars' => array(h(\$server_timezone_label))))", $source, 'prep.php: Server Default option');
	pg_assert_contains("lang(array('string' => 'Custom: {var:1}', 'vars' => array(h(\$timezone))))", $source, 'prep.php: Custom option');
}
