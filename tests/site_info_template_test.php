<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the System Informations page (includes/templates/si.php): the
 * server address line and the escaping of the server values it prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A web server that sets neither SERVER_ADDR nor LOCAL_ADDR (PHP's built-in
// server, some FastCGI setups) logged "Undefined array key" on every load:
// the address line read LOCAL_ADDR whenever SERVER_ADDR was missing.
function test_site_info_server_address_reads_only_a_set_variable()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/si.php');

	pg_assert_contains("h(\$_SERVER['SERVER_ADDR'] ?? (\$_SERVER['LOCAL_ADDR'] ?? ''))", $source, 'address line');
	pg_assert_false(strpos($source, "h(\$_SERVER['LOCAL_ADDR'])") !== false, 'no unchecked read of LOCAL_ADDR');
}

function test_site_info_disabled_functions_escaped()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/si.php');

	pg_assert_contains("h(ini_get('disable_functions'))", $source, 'disabled functions list');
}
