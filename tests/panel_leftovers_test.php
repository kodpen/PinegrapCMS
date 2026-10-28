<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for small panel fixes: the account menu offers Pair a device
 * under the same conditions as the pairing screen, close and back buttons
 * carry a translated name, and the Turkish starter site installs a Turkish
 * member ID label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_leftovers_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_leftovers_account_menu_pair_a_device()
{
	$source = panel_leftovers_source('includes/fn/output.php');

	$start = strpos($source, 'function pg_account_menu(');
	pg_assert_true($start !== false, 'pg_account_menu() not found');

	$end = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
	$row = strpos($body, "'/device_pairing.php'");

	pg_assert_true($row !== false, 'the account menu has no Pair a device row');
	pg_assert_true(strpos($body, "kind = 'device' AND status = 'active'") !== false, 'the row is not gated on an active device application');

	$rights = strpos($body, 'api_device_account_has_rights($user)');
	pg_assert_true(($rights !== false) && ($rights < $row), 'the row is not gated on api_device_account_has_rights()');
	pg_assert_true(strpos($body, "lang('Pair a device')") !== false, 'the row label is not translated');
}

function test_panel_leftovers_close_names_translated()
{
	foreach (array('get_page_content.php', 'install/index.php', 'includes/fn/output.php') as $path) {
		pg_assert_true(strpos(panel_leftovers_source($path), 'aria-label="Close"') === false, $path . ': an English close button name is left');
	}
}

function test_panel_leftovers_turkish_member_id_label()
{
	$sql = panel_leftovers_source('data/backups/turkish_default/sql.sql');
	$start = strpos($sql, 'INSERT INTO `config` VALUES');

	pg_assert_true($start !== false, 'no config row in the Turkish starter site');

	$line = substr($sql, $start, strpos($sql, "\n", $start) - $start);

	pg_assert_true(strpos($line, "'Member ID'") === false, 'the Turkish starter site installs the English member ID label');
	pg_assert_true(strpos($line, "'Üye Kimliği'") !== false, 'the Turkish starter site has no Turkish member ID label');
}
