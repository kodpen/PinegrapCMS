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
	$attribute = 'window.location.href=\'' . h(escape_javascript(escape_url($send_to))) . '\'';
	$script = html_entity_decode($attribute, ENT_QUOTES, 'UTF-8');
	pg_assert_same("window.location.href='/x\\';alert(1);//'", $script);
}

function test_menu_items_back_button_escapes_for_javascript()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');
	pg_assert_contains("window.location.href=\\'' . h(escape_javascript(\$back_button_url)) . '\\'", $source);
}
