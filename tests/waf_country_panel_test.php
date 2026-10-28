<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which requests under the software directory the country
 * restriction's panel exemption covers (waf_country_panel_exempt() in
 * waf.php): the panel's sign-in screens for anyone, every other script only
 * for signed-in staff. The visitor scripts there (comments, forms,
 * registration, checkout) follow the country rule like the site's pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// The sign-in screens stay open, so staff abroad can still sign in.
function test_waf_country_panel_sign_in_screens_exempt()
{
	foreach (array('index.php', 'signin.php', 'mfa.php', 'passkey.php', 'sms_code.php', 'forgot_password.php', 'set_password.php', 'logout.php') as $script) {
		pg_assert_true(waf_country_panel_exempt($script, false), $script);
	}
}

// The visitor scripts of the software directory are no longer exempt.
function test_waf_country_panel_visitor_scripts_restricted()
{
	foreach (array('add_comment.php', 'custom_form.php', 'registration_entrance.php', 'express_order.php', 'submit_order.php', 'api.php', 'view_pages.php') as $script) {
		pg_assert_false(waf_country_panel_exempt($script, false), $script);
	}
}

// Signed-in staff keep every screen of the panel, api.php included.
function test_waf_country_panel_staff_exempt()
{
	foreach (array('view_pages.php', 'settings_firewall.php', 'api.php', 'add_comment.php') as $script) {
		pg_assert_true(waf_country_panel_exempt($script, true), $script);
	}
}
