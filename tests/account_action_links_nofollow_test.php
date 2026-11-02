<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the member widgets' action links: every link bound to a sign-in,
 * sign-up, password reset or sign-out address is stamped rel="nofollow" by
 * the server, with the words it already had, whatever the design gave it;
 * and the set-password screen's messages each link to a new e-mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function account_action_links_test_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function test_account_action_links_rel_words_kept_once()
{
	pg_assert_same('nofollow', pg_sw_rel_with_nofollow(''), 'empty');
	pg_assert_same('nofollow', pg_sw_rel_with_nofollow(null), 'not a string');
	pg_assert_same('noopener nofollow', pg_sw_rel_with_nofollow(' noopener  '), 'own word first');
	pg_assert_same('external NOFOLLOW', pg_sw_rel_with_nofollow('external NOFOLLOW'), 'nofollow not repeated');
	pg_assert_same('noopener nofollow', pg_sw_rel_with_nofollow('noopener noopener'), 'every word once');
}

// The login region's tree as the templates draw it: sign-in and sign-up
// links as content links, the account link as a page.
function test_account_action_links_member_links_stamped()
{
	$n = 'account_action_links_test_node';
	$tree = $n('root', array(), array(
		$n('semantic', array('tag' => 'div'), array(
			$n('content', array('contentType' => 'link', 'text' => 'Log In', 'href' => '#', '_bindings' => array('href' => '__login_url'))),
			$n('semantic', array('tag' => 'a', 'text' => 'Sign Up', '_attrs' => array(array('name' => 'rel', 'value' => 'noopener')),
				'_bindings' => array('href' => '__register_url'))),
			$n('component', array('componentType' => 'btn', 'btnElement' => 'a', 'variant' => 'link', 'text' => 'Forgot?', 'rel' => 'external',
				'_bindings' => array('href' => '__forgot_password_url'))),
			$n('content', array('contentType' => 'link', 'text' => 'My Account', 'href' => '#', '_bindings' => array('href' => '__my_account_url'))),
			$n('content', array('contentType' => 'link', 'text' => 'Gone', 'href' => '#', '_bindings' => array('href' => '__staff_url'))),
		)),
	));

	_pg_member_drop_empty_links($tree, array(
		'__login_url'           => '/login?send_to=%2Fmenu',
		'__register_url'        => '/register?send_to=%2Fmenu',
		'__forgot_password_url' => '/forgot',
		'__my_account_url'      => '/account',
		'__staff_url'           => '',
	));

	$links = $tree['children'][0]['children'];
	pg_assert_same(4, count($links), 'the empty staff link left');
	pg_assert_same('nofollow', $links[0]['props']['rel'], 'sign-in');
	pg_assert_same('noopener nofollow', $links[1]['props']['rel'], 'sign-up, its Attributes panel rel kept');
	pg_assert_same('external nofollow', $links[2]['props']['rel'], 'forgot password, its rel kept');
	pg_assert_false(isset($links[3]['props']['rel']), 'the account link is a page');

	$html = _render_tree_node($tree, 0, 0);
	preg_match_all('/<a [^>]*>/', $html, $m);
	pg_assert_same(4, count($m[0]), 'four links drawn');
	pg_assert_contains('rel="nofollow"', $m[0][0], 'sign-in drawn with nofollow');
	pg_assert_same(1, substr_count($m[0][1], 'rel='), 'sign-up rel printed once');
	pg_assert_contains('rel="noopener nofollow"', $m[0][1], 'sign-up drawn with both words');
	pg_assert_false(strpos($m[0][3], 'rel=') !== false, 'the account link drawn without rel');
}

// A link the widget has no value map for is still an action.
function test_account_action_links_stamp_without_value_map()
{
	$n = 'account_action_links_test_node';
	$tree = $n('root', array(), array(
		$n('content', array('contentType' => 'link', 'text' => 'Sign out', 'href' => '#', '_bindings' => array('href' => '__logout_url'))),
	));
	_pg_member_drop_empty_links($tree, array());
	pg_assert_same('nofollow', $tree['children'][0]['props']['rel'], 'sign-out');

	$tree = $n('root', array(), array($n('semantic', array('tag' => 'p'), array(
		$n('content', array('contentType' => 'link', 'text' => 'Sign in', 'href' => '#', '_bindings' => array('href' => '__login_url'))),
		$n('content', array('contentType' => 'link', 'text' => 'Shop', 'href' => '#', '_bindings' => array('href' => '__shop_url'))),
	))));
	pg_sw_stamp_nofollow($tree, array('__login_url'));
	pg_assert_same('nofollow', $tree['children'][0]['children'][0]['props']['rel'], 'nested sign-in');
	pg_assert_false(isset($tree['children'][0]['children'][1]['props']['rel']), 'shop left alone');
}

// The comments and wishlist widgets use the shared stamp, not a copy.
function test_account_action_links_one_stamp()
{
	$comments = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments.php');
	$wishlist = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_wishlist.php');
	pg_assert_contains("pg_sw_stamp_nofollow(\$tree, array('__login_url'))", $comments, 'comments');
	pg_assert_contains("pg_sw_stamp_nofollow(\$tree, array('__login_url'))", $wishlist, 'wishlist');

	// The login widget's section-drawn sign-up link.
	$widgets = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('class="pg-member-link pg-register-link" rel="nofollow"', $widgets, 'the section sign-up link');
}

function test_account_set_password_messages_link_to_a_new_email()
{
	$forgot = '/forgot-password?send_to=/a&b';
	foreach (array('missing', 'invalid', 'expired') as $reason) {
		$message = pg_set_password_token_message($reason, $forgot);
		pg_assert_contains('<a class="alert-link" href="/forgot-password?send_to=/a&amp;b">request a new email</a>', $message, $reason . ' links to a new e-mail, coloured by its alert');
	}
	pg_assert_contains('missing from the address', pg_set_password_token_message('missing', $forgot), 'missing');
	pg_assert_contains('has expired', pg_set_password_token_message('expired', $forgot), 'expired');
	pg_assert_contains('is not valid', pg_set_password_token_message('invalid', $forgot), 'invalid');
}
