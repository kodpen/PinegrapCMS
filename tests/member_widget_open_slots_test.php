<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sign-in and sign-up widgets' opened slots: the strong
 * password rules a designer built print as designed while the site prints
 * rules (pg_member_keep_designed_rules()), the server's block otherwise; the
 * sign-in buttons' words (pg_signin_button_label()); the editor's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_member_rules_box($children)
{
	return array('type' => 'semantic', 'props' => array(
		'tag' => 'div', 'cssClass' => 'mb-2 pg-password-rules', '_bindings' => array('section' => 'password_rules'),
	), 'children' => $children);
}

function _test_member_rules_tree($box)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array($box)),
	));
}

function _test_member_rule($text)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'li', 'text' => $text), 'children' => array());
}

function test_member_designed_rules_print_as_designed()
{
	$tree = _test_member_rules_tree(_test_member_rules_box(array(_test_member_rule('Ten letters'), _test_member_rule('One digit'))));
	$rules = '<div class="pg-password-rules small text-muted">SERVER RULES</div>';
	pg_member_keep_designed_rules($tree, $rules);
	pg_cf_apply_section_bindings($tree, array('password_rules' => $rules));
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_true(strpos($html, 'Ten letters') !== false && strpos($html, 'One digit') !== false, 'the designed rules print');
	pg_assert_true(strpos($html, 'SERVER RULES') === false, 'the server block does not');
	pg_assert_true(strpos($html, 'mb-2 pg-password-rules') !== false, 'the box keeps its classes');
}

function test_member_designed_rules_hidden_when_off()
{
	$tree = _test_member_rules_tree(_test_member_rules_box(array(_test_member_rule('Ten letters'))));
	pg_member_keep_designed_rules($tree, '');
	pg_cf_apply_section_bindings($tree, array('password_rules' => ''));
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_true(strpos($html, 'Ten letters') === false && strpos($html, 'pg-password-rules') === false, 'setting off: box and rules gone');
}

function test_member_rules_box_without_children_unchanged()
{
	$tree = _test_member_rules_tree(_test_member_rules_box(array()));
	$copy = $tree;
	$rules = '<div class="pg-password-rules small text-muted">SERVER RULES</div>';
	pg_member_keep_designed_rules($tree, $rules);
	pg_assert_same($copy, $tree, 'an empty box is left to the section pass');
	pg_cf_apply_section_bindings($tree, array('password_rules' => $rules));
	pg_assert_true(strpos(_render_tree_node($tree, 0, 0), 'SERVER RULES') !== false, 'the server block, as before');
}

function test_member_signin_button_label()
{
	pg_assert_same(lang('Sign in with Google'), pg_signin_button_label('google'), 'Google');
	pg_assert_same(lang('Sign in with Apple'), pg_signin_button_label('apple'), 'Apple');
	pg_assert_same(lang('Sign in with Microsoft'), pg_signin_button_label('microsoft'), 'Microsoft');
}

function test_member_renderers_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$account = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_same(2, substr_count($widgets, 'pg_member_keep_designed_rules($tree, $rules_html);'), 'registration and membership');
	pg_assert_same(2, substr_count($account, 'pg_member_keep_designed_rules($tree, $rules_html);'), 'change and set password');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'signin_buttons'  => function_exists('pg_signin_canvas_sample') ? pg_signin_canvas_sample() : '',", $screen);
}

function test_member_editor_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("if (kind === 'login_form' && section === 'forgot_password_link') return ['__forgot_password_url'", $js);
	pg_assert_contains("if (kind === 'login_form' && section === 'register_link') return ['__register_url'", $js);
	pg_assert_contains("if (kind === 'registration' && section === 'login_link') return ['__login_url'", $js);
	pg_assert_contains("if (kind === 'membership' && section === 'login_link') return ['__login_url'", $js);
	pg_assert_contains('password_rules: 1 };', $js);
	pg_assert_contains('function _sdPasswordRulesStarter(bs) {', $js);
	pg_assert_contains("if (_sdMemberOpens(kind, section, node)) return _sdMemberOpenRow();", $js);
}
