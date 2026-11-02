<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The back button of view_menu_items.php writes send_to into an onclick
 * handler. The browser decodes the attribute before the script runs, so h()
 * alone hands a quote back to JavaScript; the address is escaped for
 * JavaScript first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The handler as the browser runs it: the attribute value decoded.
function test_menu_items_back_button_quote_stays_in_the_string()
{
	$send_to = "/x';alert(1);//";
	$attribute = 'window.location.href=\'' . h(escape_javascript($send_to)) . '\'';
	$script = html_entity_decode($attribute, ENT_QUOTES, 'UTF-8');
	pg_assert_same("window.location.href='/x\\';alert(1);//'", $script);
}

function test_menu_items_back_button_escapes_for_javascript()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');
	pg_assert_contains("window.location.href=\\'' . h(escape_javascript(\$back_button_url)) . '\\'", $source);
}

// Only a path on this site is followed; without send_to the menu list opens.
function test_menu_items_back_button_stays_on_the_site()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');
	pg_assert_contains("\$back_button_url = pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/view_menus.php');", $source);

	$saved = $_REQUEST;
	foreach (array('https://evil.example.com/', '//evil.example.com', '') as $send_to) {
		$_REQUEST = array('send_to' => $send_to);
		pg_assert_same('/pinegrap/view_menus.php', pg_send_to_url('/pinegrap/view_menus.php'), 'refused: ' . $send_to);
	}
	$_REQUEST = $saved;
}
