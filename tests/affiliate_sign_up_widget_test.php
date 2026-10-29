<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Affiliate Sign Up Form system widget
 * (includes/fn/widgets_affiliate_sign_up.php): the page row its settings
 * write, the values the form opens with, its flags and the places the kind
 * is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_affiliate_sign_up_widget_page_row_changes()
{
	$row = array('terms_page_id' => '4', 'submit_button_label' => 'Apply', 'next_page_id' => '9');
	pg_assert_same(array(), pg_aff_page_row_changes($row, array()), 'a widget never set keeps the row');
	pg_assert_same(array(), pg_aff_page_row_changes($row, array('terms_page_id' => 0, 'next_page_id' => '')), 'zero means the page\'s own setting');
	pg_assert_same(array('next_page_id' => 12), pg_aff_page_row_changes($row, array('next_page_id' => '12', 'submit_button_label' => ' Apply ')), 'only what changes');
	pg_assert_same(array('submit_button_label' => str_repeat('x', 50)), pg_aff_page_row_changes(null, array('submit_button_label' => str_repeat('x', 60))), 'a label is kept to 50 characters');
}

function test_affiliate_sign_up_widget_prefill()
{
	$v = pg_aff_prefill(array('first_name' => 'Ada', 'business_address_1' => 'Main St 1', 'business_country' => 'TR',
		'business_phone' => '123', 'company' => 'Ada Ltd', 'website' => ''));
	pg_assert_same('Ada', $v['first_name'], 'the contact\'s name');
	pg_assert_same('Main St 1', $v['address_1'], 'the business address');
	pg_assert_same('TR', $v['country'], 'the business country');
	pg_assert_same('Ada Ltd', $v['affiliate_name'], 'the company as the affiliate name');
	pg_assert_same('http://', $v['affiliate_website'], 'an empty website opens as on the classic page');
	pg_assert_same('', $v['affiliate_code'], 'the code is the visitor\'s to choose');
	$blank = pg_aff_prefill(null);
	pg_assert_same('', $blank['email_address'], 'no contact, blank fields');
	pg_assert_same(14, count($blank), 'every control of the form');
}

function test_affiliate_sign_up_widget_flags()
{
	pg_assert_same(array('has_terms' => true, 'no_terms' => false), pg_aff_flags(true), 'a terms page');
	pg_assert_same(array('has_terms' => false, 'no_terms' => true), pg_aff_flags(false), 'none');
}

function test_affiliate_sign_up_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['affiliate_sign_up_form']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('affiliate_sign_up_form', $map['affiliate sign up form'], 'stands in for the classic type');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_affiliate_sign_up_form(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'affiliate_sign_up_form'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'affiliate_sign_up_form': {", $js, 'has a starter tree');
	pg_assert_contains("affiliate_sign_up_form: SW_AFFILIATE_SIGN_UP_TOKEN_GROUPS", $js, 'has a token palette');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_affiliate_sign_up.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'affiliate_sign_up_form')", $source, 'Messages named after the classic liveform');
}

// A sign-up widget's confirmation message: plain text escaped once, its line
// breaks kept; empty, missing or not text keeps the default sentence.
function test_affiliate_sign_up_widget_confirmation_notice()
{
	$default = 'Thank you. Your affiliate application has been sent.';
	pg_assert_same("We got it &lt;b&gt;<br>\nThanks", pg_member_confirmation_notice(array('confirmation_message' => "  We got it <b>\r\nThanks "), $default));
	pg_assert_same($default, pg_member_confirmation_notice(array('confirmation_message' => '  '), $default), 'blank');
	pg_assert_same($default, pg_member_confirmation_notice(array('confirmation_message' => array('x')), $default), 'not text');
	pg_assert_same($default, pg_member_confirmation_notice(null, $default), 'no widget');
	pg_assert_same(null, pg_member_widget_cfg(array(1), 'registration'), 'an id that is not a number');
	pg_assert_same(null, pg_member_widget_cfg('0', 'registration'), 'no id');
}

// The handlers read the posting widget's message only when the visitor stays
// on the designed page; the affiliate widget names itself in its post.
function test_affiliate_sign_up_widget_confirmation_wiring()
{
	foreach (array('registration_entrance.php' => "'registration'", 'membership_entrance.php' => "'membership'", 'affiliate_sign_up_form.php' => "'affiliate_sign_up_form'") as $file => $type) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains("pg_member_widget_cfg(\$_POST['pg_widget_id'] ?? 0, " . $type . ')', $source, $file);
		pg_assert_contains('pg_member_confirmation_notice(', $source, $file);
	}
	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_affiliate_sign_up.php');
	pg_assert_contains("'pg_widget_id'    => \$widget_id,", $widget, 'the affiliate form names its widget');
	pg_assert_contains("(string)\$lf->get_field_value('screen') === 'confirm'", $widget, 'the confirmation screen');
	pg_assert_contains('_pg_member_drop_all_controls($tree);', $widget, 'the design stays, its controls go');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_same(3, substr_count($js, '_swConfirmRow(sid, _cfg, _sdT('), 'the setting on all three widgets');
	pg_assert_contains('data-sw-cfg="confirmation_message" data-sw-cfg-max="2000"', $js, 'a message longer than a label');
	pg_assert_contains("\$confirmed->assign_field_value('screen', 'confirm');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/affiliate_sign_up_form.php'), 'the handler asks for it');
}
