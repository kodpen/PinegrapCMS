<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The account widgets' starter trees (style_designer.src.js) write the card
 * title as the page's h1: a template's sign-in, sign-up, forgot password,
 * set password, change password, membership and log-out pages have no other
 * heading, so an h4 title left them without one. The h4 class keeps the size.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_account_page_titles_are_h1()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	$titles = array('Log In', 'Forgot Password', 'Create Your Account', 'Membership Entrance',
		'Log Out', 'You Have Logged Out', 'Change Password', 'Set a New Password');

	foreach ($titles as $title) {
		$found = preg_match_all("/contentType: 'heading', tag: '(h[1-6])',\\s*text: _sdT\\('"
			. preg_quote($title, '/') . "'\\), cssClass: '([^']*card-title[^']*)'/", $js, $m);

		pg_assert_same(1, $found, $title . ': one heading node');
		pg_assert_same('h1', $m[1][0], $title . ': tag');
		pg_assert_same(true, in_array('h4', explode(' ', $m[2][0]), true), $title . ': h4 size class');
	}
}
