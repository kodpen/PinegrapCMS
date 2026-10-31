<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the title a device push banner carries
 * (pg_panel_push_title() in includes/panel/notifications.php): the bell
 * receives its titles as escaped markup, the operating system's banner
 * shows text, so the title is flattened before it is sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The file only defines the panel actions; its gate is the dispatcher's.
if (!defined('PG_PANEL_ACTIONS')) {
	define('PG_PANEL_ACTIONS', true);
}
require_once(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');

function test_push_title_decodes_an_escaped_title()
{
	pg_assert_same("Ar-Ge & Tasar'm", pg_panel_push_title('Ar-Ge &amp; Tasar&#039;m'));
	pg_assert_same("Ali'nin mesajı", pg_panel_push_title('Ali&#039;nin mesajı'));
	pg_assert_same('"Q4" <draft>', pg_panel_push_title('&quot;Q4&quot; &lt;draft&gt;'));
}

function test_push_title_drops_markup_and_trims()
{
	pg_assert_same('Low stock: Mug', pg_panel_push_title('  <strong>Low stock:</strong> Mug '));
	pg_assert_same('', pg_panel_push_title(''));
}

// The pending list sends the flattened title, not the bell's markup.
function test_push_pending_sends_the_flattened_title()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');

	pg_assert_true(strpos($source, "'title'     => pg_panel_push_title(\$display['title'])") !== false, 'push item title');
	pg_assert_false(strpos($source, "'title'     => \$display['title']"), 'escaped title sent as is');
}
