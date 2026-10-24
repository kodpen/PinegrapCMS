<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link fields of form e-mails (^^email_preferences_url^^ and
 * ^^confirm_subscription_url^^): the preferences address builder
 * (includes/fn/mail.php), the fill and its context (includes/fn/custom_form.php)
 * and where custom_form.php fills them in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A valid address and a signature give the screen's address with id and sig.
function test_form_mail_links_url_build_valid()
{
	$url = pg_email_preferences_url_build('https://example.com/pinegrap', 'ayse@example.com', 'abc123');
	pg_assert_same(
		'https://example.com/pinegrap/email_preferences.php?id=' . urlencode(pg_email_preferences_id('ayse@example.com')) . '&sig=abc123',
		$url,
		'address with id and signature');
}

// No link that cannot work: an address without "@" or no signature (no ENCRYPTION_KEY) gives ''.
function test_form_mail_links_url_build_refuses()
{
	pg_assert_same('', pg_email_preferences_url_build('https://example.com/pinegrap', 'not an address', 'abc123'), 'no at sign');
	pg_assert_same('', pg_email_preferences_url_build('https://example.com/pinegrap', '', 'abc123'), 'empty address');
	pg_assert_same('', pg_email_preferences_url_build('https://example.com/pinegrap', 'ayse@example.com', ''), 'no signature');
}

// Every address mail goes to keeps its link (and a campaign its List-Unsubscribe
// header): no check stricter than validate_email_address() or than before.
function test_form_mail_links_url_build_accepts_loose_addresses()
{
	foreach (array('a..b@example.com', '.a@example.com', 'ayse@example.photography') as $address) {
		pg_assert_true(pg_email_preferences_url_build('https://example.com/pinegrap', $address, 'abc') !== '', $address);
	}
}

// The id is URL encoded: base64 may hold + / and =.
function test_form_mail_links_url_build_encodes_id()
{
	$address = 'a.b+c@example.com';
	$url = pg_email_preferences_url_build('https://example.com/pinegrap', $address, 'abc');
	$query = (string) parse_url($url, PHP_URL_QUERY);
	parse_str($query, $parts);
	pg_assert_same(pg_email_preferences_id($address), $parts['id'] ?? null, 'id decodes back');
	pg_assert_same($address, str_rot13(base64_decode($parts['id'] ?? '')), 'address decodes back');
	pg_assert_false(strpos($query, '+') !== false && strpos($query, '%2B') === false, 'no bare plus sign');
}

// In HTML the value is escaped; in plain text it is printed as it is.
function test_form_mail_links_fill_html_and_plain_text()
{
	$values = array('email_preferences_url' => 'https://example.com/p?id=x&sig=y', 'confirm_subscription_url' => '');

	pg_assert_same(
		'<a href="https://example.com/p?id=x&amp;sig=y">Preferences</a>',
		pg_cf_fill_mail_links('<a href="^^email_preferences_url^^">Preferences</a>', $values, 'html'),
		'html escaped');
	pg_assert_same(
		'Preferences: https://example.com/p?id=x&sig=y',
		pg_cf_fill_mail_links('Preferences: ^^email_preferences_url^^', $values, 'plain_text'),
		'plain text as it is');
}

// An empty value takes the other half of a [[...]] block; a bare token becomes ''.
function test_form_mail_links_fill_empty_value()
{
	$values = array('email_preferences_url' => '', 'confirm_subscription_url' => '');

	pg_assert_same('Hi. ', pg_cf_fill_mail_links('Hi. [[Confirm: ^^confirm_subscription_url^^]]', $values, 'plain_text'), 'block without else part removed');
	pg_assert_same('Hi. none', pg_cf_fill_mail_links('Hi. [[Confirm: ^^confirm_subscription_url^^||none]]', $values, 'plain_text'), 'else part');
	pg_assert_same('Link: ', pg_cf_fill_mail_links('Link: ^^email_preferences_url^^', array(), 'html'), 'no values: empty, never the token');

	$filled = array('email_preferences_url' => '', 'confirm_subscription_url' => 'https://example.com/c');
	pg_assert_same('Hi. Confirm: https://example.com/c', pg_cf_fill_mail_links('Hi. [[Confirm: ^^confirm_subscription_url^^]]', $filled, 'plain_text'), 'block kept with a value');
}

// Other fields are left for their own pass.
function test_form_mail_links_fill_leaves_other_fields()
{
	pg_assert_same(
		'^^full_name^^ ^^key_code^^',
		pg_cf_fill_mail_links('^^full_name^^ ^^key_code^^', array('email_preferences_url' => 'x'), 'plain_text'),
		'other fields untouched');
}

// The editor's Link dialog puts http:// before the field; it is taken off.
function test_form_mail_links_fill_strips_link_dialog_scheme()
{
	$values = array('email_preferences_url' => 'https://example.com/p', 'confirm_subscription_url' => 'https://example.com/c');

	pg_assert_same(
		'<a href="https://example.com/p">P</a> <a href=\'https://example.com/c\'>C</a>',
		pg_cf_fill_mail_links('<a href="http://^^email_preferences_url^^">P</a> <a href=\'http://^^confirm_subscription_url^^\'>C</a>', $values, 'html'),
		'scheme removed');
}

// Without a context (the confirmation page, a watcher's copy) both links are ''.
function test_form_mail_links_values_without_context()
{
	pg_cf_mail_links_context(false);
	pg_assert_same(array('email_preferences_url' => '', 'confirm_subscription_url' => ''), pg_cf_mail_link_values(), 'no context');
}

// With a context and not suspended, the sender's copy gets both builders' links
// for the context's address, page and contact.
function test_form_mail_links_values_build_with_context()
{
	$seen = array();
	$values = pg_cf_mail_link_values_build(
		array('email_address' => 'ayse@example.com', 'contact_id' => 5, 'custom_form_page_id' => 3, 'form_id' => 9),
		false,
		function ($email_address) use (&$seen) { $seen[] = $email_address; return 'https://example.com/p?for=' . $email_address; },
		function ($page_id, $contact_id) use (&$seen) { $seen[] = array($page_id, $contact_id); return 'https://example.com/c/' . $page_id . '/' . $contact_id; });

	pg_assert_same(array(
		'email_preferences_url' => 'https://example.com/p?for=ayse@example.com',
		'confirm_subscription_url' => 'https://example.com/c/3/5',
	), $values, 'both links');
	pg_assert_same(array('ayse@example.com', array(3, 5)), $seen, 'builders called with the context');

	$never = function () { throw new Exception('builder called'); };
	pg_assert_same(array('email_preferences_url' => '', 'confirm_subscription_url' => ''), pg_cf_mail_link_values_build(null, false, $never, $never), 'no context: builders not called');
	pg_assert_same(array('email_preferences_url' => '', 'confirm_subscription_url' => ''), pg_cf_mail_link_values_build(array('email_address' => 'a@b.c'), true, $never, $never), 'suspended: builders not called');
}

// The administrator's e-mail is drawn under pg_tr_suspend(): no links even with a context.
function test_form_mail_links_values_suspended()
{
	pg_cf_mail_links_context(array('email_address' => 'ayse@example.com', 'contact_id' => 5, 'custom_form_page_id' => 3, 'form_id' => 9));
	pg_tr_suspend(true);
	$values = pg_cf_mail_link_values();
	pg_tr_suspend(false);
	pg_cf_mail_links_context(false);

	pg_assert_same(array('email_preferences_url' => '', 'confirm_subscription_url' => ''), $values, 'suspended');
	pg_assert_same(null, pg_cf_mail_links_context(), 'context cleared');
}

// Until a form can ask for a double opt-in there is no confirmation link.
function test_form_mail_links_confirm_url_empty()
{
	pg_assert_same('', pg_cf_confirm_subscription_url(3, 5), 'no double opt-in yet');
}

// A body is kept out of the failure log when it carries one of the links.
function test_form_mail_links_in()
{
	$values = array('email_preferences_url' => 'https://example.com/p', 'confirm_subscription_url' => '');
	pg_assert_true(pg_cf_mail_links_in('Visit https://example.com/p today', $values), 'link present');
	pg_assert_false(pg_cf_mail_links_in('Nothing here', $values), 'no link');
	pg_assert_false(pg_cf_mail_links_in('Anything', array('email_preferences_url' => '', 'confirm_subscription_url' => '')), 'empty values never match');
}

// Positions of $needles in $source, each searched from the one before it
// (false once one is missing): the order a sending path must keep.
function pg_test_form_mail_links_positions($source, $needles, $from = 0)
{
	$positions = array();
	foreach ($needles as $label => $needle) {
		$at = ($from === false) ? false : strpos($source, $needle, (int) $from);
		$positions[$label] = $at;
		$from = ($at === false) ? false : $at + 1;
	}
	return $positions;
}

// One sending path (custom_form.php, the notifications in includes/fn/mail.php,
// submit_order.php): the context is set behind the gate for the sender's own
// copy, the subject and plain text body take the links into the template
// before the submitted values, the HTML body gets the bare tokens only, the
// e-mail goes out, the context is cleared, and the administrator's e-mail
// gets empty links the same way.
function pg_test_form_mail_links_path($file, $gate, $needles)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

	$set = strpos($source, 'pg_cf_mail_links_context(array(');
	pg_assert_true($set !== false, $file . ': context set');

	if ($gate !== '') {
		$gate_at = strrpos(substr($source, 0, (int) $set), $gate);
		pg_assert_true($gate_at !== false && ($set - $gate_at) < 200, $file . ': context set behind the gate for the sender only');
	}

	$positions = pg_test_form_mail_links_positions($source, $needles, $set);
	foreach ($positions as $label => $at) {
		pg_assert_true($at !== false, $file . ': ' . $label . ' in order');
	}

	// A template fill (which evaluates [[...]] blocks) never runs on text that
	// already holds the submitted values.
	pg_assert_false(strpos($source, 'pg_cf_fill_mail_links($body') !== false, $file . ': no block fill on a drawn body');
	pg_assert_false(strpos($source, 'pg_cf_fill_mail_links($subject') !== false, $file . ': no block fill on a filled subject');
}

function test_form_mail_links_custom_form_order()
{
	pg_test_form_mail_links_path('custom_form.php', 'if ($to_email_address == $submitter_email_address)', array(
		'values'            => '$mail_link_values = pg_cf_mail_link_values();',
		'subject template'  => "\$subject = pg_cf_fill_mail_links(\$submitter_email_subject, \$mail_link_values, 'plain_text');",
		'subject values'    => '$subject = get_variable_submitted_form_data_for_content(',
		'plain body'        => "get_variable_submitted_form_data_for_content(\$_POST['page_id'], \$form_id, pg_cf_fill_mail_links(pg_cf_email_body_or_default(",
		'html page'         => '$body = get_page_content($submitter_email_page_id',
		'html tokens'       => "pg_cf_fill_mail_link_tokens(\$body, \$mail_link_values, 'html')",
		'send'              => 'email(array(',
		'clear'             => 'pg_cf_mail_links_context(false);',
		'admin subject'     => "\$administrator_email_subject = pg_cf_fill_mail_links(\$administrator_email_subject, array(), 'plain_text');",
		'admin subject values' => '$administrator_email_subject = get_variable_submitted_form_data_for_content(',
		'admin plain body'  => "pg_cf_fill_mail_links(pg_cf_email_body_or_default(\$_POST['page_id'], \$administrator_email_body), array(), 'plain_text')",
		'admin html page'   => '$body = get_page_content($administrator_email_page_id',
		'admin html tokens' => "pg_cf_fill_mail_link_tokens(\$body, array(), 'html')",
	));

	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/custom_form.php');
	pg_assert_contains("'subject' => \$subject,", $source, 'the filled copy of the subject is sent');
}

function test_form_mail_links_notifications_order()
{
	pg_test_form_mail_links_path('includes/fn/mail.php', '', array(
		'values'            => '$mail_link_values = pg_cf_mail_link_values();',
		'subject'           => "get_variable_submitted_form_data_for_content(\$custom_form['page_id'], \$form_id, pg_cf_fill_mail_links(\$custom_form['submitter_email_subject'], \$mail_link_values, 'plain_text')",
		'plain body'        => "pg_cf_fill_mail_links(pg_cf_email_body_or_default(\$custom_form['page_id'], \$custom_form['submitter_email_body'], true), \$mail_link_values, 'plain_text')",
		'html page'         => "\$body = get_page_content(\$custom_form['submitter_email_page_id']",
		'html tokens'       => "pg_cf_fill_mail_link_tokens(\$body, \$mail_link_values, 'html')",
		'send'              => 'email(array(',
		'clear'             => 'pg_cf_mail_links_context(false);',
		'admin subject'     => "pg_cf_fill_mail_links(\$custom_form['administrator_email_subject'], array(), 'plain_text')",
		'admin plain body'  => "pg_cf_fill_mail_links(pg_cf_email_body_or_default(\$custom_form['page_id'], \$custom_form['administrator_email_body']), array(), 'plain_text')",
		'admin html page'   => "\$body = get_page_content(\$custom_form['administrator_email_page_id']",
		'admin html tokens' => "pg_cf_fill_mail_link_tokens(\$body, array(), 'html')",
	));
}

function test_form_mail_links_submit_order_order()
{
	pg_test_form_mail_links_path('submit_order.php', 'if ($to_email_address == $billing_email_address)', array(
		'values'            => '$mail_link_values = pg_cf_mail_link_values();',
		'subject'           => "\$subject = get_variable_submitted_form_data_for_content(\$product['submit_form_custom_form_page_id'], \$submitted_form_id, pg_cf_fill_mail_links(\$submitter_email_subject, \$mail_link_values, 'plain_text')",
		'plain body'        => "pg_cf_fill_mail_links(pg_cf_email_body_or_default(\$product['submit_form_custom_form_page_id'], \$submitter_email_body, true), \$mail_link_values, 'plain_text')",
		'html page'         => '$body = get_page_content($submitter_email_page_id',
		'html tokens'       => "pg_cf_fill_mail_link_tokens(\$body, \$mail_link_values, 'html')",
		'send'              => 'email(array(',
		'clear'             => 'pg_cf_mail_links_context(false);',
		'admin subject'     => "\$administrator_email_subject = pg_cf_fill_mail_links(\$administrator_email_subject, array(), 'plain_text');",
		'admin subject values' => '$administrator_email_subject = get_variable_submitted_form_data_for_content(',
		'admin plain body'  => "pg_cf_fill_mail_links(pg_cf_email_body_or_default(\$product['submit_form_custom_form_page_id'], \$administrator_email_body), array(), 'plain_text')",
		'admin html page'   => '$body = get_page_content($administrator_email_page_id',
		'admin html tokens' => "pg_cf_fill_mail_link_tokens(\$body, array(), 'html')",
	));
}

// get_form_item_view.php fills the links in from the context, empty on the page.
function test_form_mail_links_item_view_fills()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_form_item_view.php');
	$layout = strpos($source, '$output = $layout;');
	$fill = strpos($source, "pg_cf_fill_mail_links(\$output, pg_cf_mail_link_values(), 'html')");
	$conditionals = strpos($source, "preg_match_all('/\\{if");

	pg_assert_true($layout !== false && $fill !== false && $fill > $layout, 'filled after the layout is taken');
	pg_assert_true($conditionals !== false && $fill < $conditionals, 'before the layout conditionals');
}

// The HTML body already holds the submitted values: only bare tokens are
// replaced, so a visitor's "[[x ^^email_preferences_url^^||" in one field and
// "]]" in another cannot hide what lies between them in the administrator's copy.
function test_form_mail_links_tokens_keep_visitor_brackets()
{
	$body = '<p>Message: [[x ^^email_preferences_url^^||</p><p>Secret admin note [[Phone: 555]]</p><p>Other: ]]</p>';

	pg_assert_same(
		'<p>Message: [[x ||</p><p>Secret admin note [[Phone: 555]]</p><p>Other: ]]</p>',
		pg_cf_fill_mail_link_tokens($body, array(), 'html'),
		'administrator copy keeps the text between the brackets');
	pg_assert_same(
		'<p>Message: [[x https://example.com/p?a=1&amp;b=2||</p><p>Secret admin note [[Phone: 555]]</p><p>Other: ]]</p>',
		pg_cf_fill_mail_link_tokens($body, array('email_preferences_url' => 'https://example.com/p?a=1&b=2'), 'html'),
		'sender copy: token replaced and escaped, nothing evaluated');
}

// Bare tokens: the Link dialog's http:// is taken off, other fields stay, plain text is not escaped.
function test_form_mail_links_tokens_bare()
{
	$values = array('email_preferences_url' => 'https://example.com/p', 'confirm_subscription_url' => '');

	pg_assert_same(
		'<a href="https://example.com/p">P</a> ^^full_name^^ ',
		pg_cf_fill_mail_link_tokens('<a href="http://^^email_preferences_url^^">P</a> ^^full_name^^ ^^confirm_subscription_url^^', $values, 'html'),
		'html');
	pg_assert_same('A&B https://example.com/p', pg_cf_fill_mail_link_tokens('A&B ^^email_preferences_url^^', $values, 'plain_text'), 'plain text');
}

// Plain text: the links are filled into the template first, then the submitted
// values go in (one replace_variables() pass, as
// get_variable_submitted_form_data_for_content() does), so brackets in the
// values reach the administrator as written.
function test_form_mail_links_template_first_keeps_visitor_brackets()
{
	$template = "Message: ^^message^^\nNote: ^^note^^\n[[Preferences: ^^email_preferences_url^^]]Tail";
	$filled = pg_cf_fill_mail_links($template, array(), 'plain_text');

	pg_assert_same("Message: ^^message^^\nNote: ^^note^^\nTail", $filled, 'template block resolved before the values');

	$out = replace_variables(array(
		'content' => $filled,
		'fields' => array(
			array('name' => 'message', 'data' => '[[x ^^email_preferences_url^^||', 'type' => ''),
			array('name' => 'note', 'data' => ']]', 'type' => '')),
		'format' => 'plain_text'));

	pg_assert_same("Message: [[x ^^email_preferences_url^^||\nNote: ]]\nTail", $out, 'values kept as written');
}
