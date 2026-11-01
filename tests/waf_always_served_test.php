<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the paths served whatever the bot policy and the country
 * restriction say (waf_path_is_always_served_for(), waf.php): robots.txt and
 * .well-known/ are answered by router.php alone, so a script reached with
 * either written after its name (PATH_INFO) is not exempt.
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

// The router's own answers stay exempt.
function test_waf_always_served_keeps_the_router_paths()
{
	pg_assert_true(waf_path_is_always_served_for('router.php', '/robots.txt'), 'robots.txt');
	pg_assert_true(waf_path_is_always_served_for('router.php', '/ROBOTS.TXT?x=1'), 'robots.txt in capitals with a query');
	pg_assert_true(waf_path_is_always_served_for('router.php', '/.well-known/acme-challenge/abc'), 'an ACME challenge');
	pg_assert_true(waf_path_is_always_served_for('router.php', '/.well-known/pinegrap.json'), 'the site document');
}

// Other paths the router serves are not exempt.
function test_waf_always_served_refuses_other_router_paths()
{
	pg_assert_false(waf_path_is_always_served_for('router.php', '/products'), 'a page');
	pg_assert_false(waf_path_is_always_served_for('router.php', '/x?u=/robots.txt'), 'robots.txt in the query');
	pg_assert_false(waf_path_is_always_served_for('router.php', ''), 'no path');
}

// A script named by the server runs whatever its PATH_INFO says.
function test_waf_always_served_refuses_a_script_with_path_info()
{
	pg_assert_false(waf_path_is_always_served_for('custom_form.php', '/pinegrap/custom_form.php/.well-known/x'), '.well-known after a form');
	pg_assert_false(waf_path_is_always_served_for('index.php', '/pinegrap/index.php/robots.txt'), 'robots.txt after the sign-in screen');
	pg_assert_false(waf_path_is_always_served_for('api.php', '/pinegrap/api.php/.well-known/'), '.well-known after api.php');
}
