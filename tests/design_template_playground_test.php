<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Playground design template (includes/design_templates/
 * pinegrap-playground.php): its comment threads are drawn by the Comments
 * widget (a blog post's comments, open to any visitor with a typed name
 * and the CAPTCHA, and a conversation's replies), and both comment e-mails
 * draw their comment with the widget's card before the System region.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/template_threads.php');

// The Playground's threads: widget key => its page, the record view above
// it, its options, Reply and Share, the follow boxes.
function _test_tpl_playground_threads()
{
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0)),
	);
}

// No fixed comments block is left; the widget kinds the Playground ships;
// each page keeps its comment settings.
function test_design_template_playground_comment_widgets()
{
	$tpl = _tpl_threads_tpl('pinegrap-playground');
	pg_assert_same('1.1.1', $tpl['version'], 'the version moved');
	_tpl_threads_check_no_fixed_block('pinegrap-playground', array(
		'conversation_replies' => 'comments', 'email_comment_card' => 'comments', 'email_reply_card' => 'comments', 'post_comments' => 'comments',
	));
	$blog = _tpl_threads_page($tpl, 'blog_post')['comments'];
	pg_assert_same(lang('Comment'), $blog['label'], 'the blog: its label');
	pg_assert_false($blog['login'], 'the blog: any visitor may comment');
	$conversation = _tpl_threads_page($tpl, 'conversation')['comments'];
	pg_assert_same(lang('reply'), $conversation['label'], 'the conversation: its label');
	pg_assert_true($conversation['login'], 'the conversation: signed in only');
	pg_assert_same('submitter_only', $tpl['widgets']['conversation']['config']['access_control'], 'the conversation is its sender\'s own');
}

function test_design_template_playground_comment_threads()
{
	foreach (_test_tpl_playground_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-playground', $key, $t);
		_tpl_threads_check_draws('pinegrap-playground', $key, $t);
	}
	// Each thread sits on the card the page gave the fixed block, under its
	// heading.
	$tpl = _tpl_threads_tpl('pinegrap-playground');
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $key) {
		$cards = 0;
		foreach (_tpl_threads_nodes(_tpl_threads_page($tpl, $page)['tree']) as $n) {
			if (isset($n['props']['_label']) && $n['props']['_label'] === lang('Comments Card')) {
				$cards++;
				pg_assert_same(array($key), _tpl_threads_order($n), $page . ': the widget on the card');
			}
		}
		pg_assert_same(1, $cards, $page . ': one card');
	}
}

// The blog's thread for a visitor who is not signed in: the page lets them
// write, so the form shows with the box for their name and the CAPTCHA, and
// no sign-in link.
function test_design_template_playground_anonymous_comment()
{
	$tpl = _tpl_threads_tpl('pinegrap-playground');
	$w = $tpl['widgets']['post_comments'];
	$data = _tpl_threads_data($w['config'], 'out');
	$opts = pg_comments_widget_options($w['config']);
	$flags = pg_comments_widget_thread_flags(array(
		'allow_new' => true, 'signed_in' => false, 'require_login' => false, 'count' => 4,
		'featured_mode' => $opts['featured'], 'has_featured' => true, 'staff' => false, 'name_mode' => 'typed',
		'rating_on' => false, 'files_on' => false, 'watching_on' => true, 'pages' => 1, 'manage_watchers' => false,
	));
	pg_assert_true($flags['can_comment'] && $flags['name_is_typed'] && !$flags['must_sign_in'], 'a visitor may write and types a name');
	$data['thread_flags'] = $flags;
	$data['thread_values']['__login_url'] = '';
	$data['form']['can_comment'] = true;
	$data['form']['hidden_html'] = pg_comments_form_hidden_html('', '/post', 5, 'submitted_form', 3, 0);
	$data['form']['state']['name_mode'] = 'typed';
	$data['form']['state']['captcha_on'] = true;
	$data['sections']['comment_captcha'] = '<div class="pg-test-captcha">What is 2 + 3?</div>';

	$html = _tpl_threads_draw($w['tree'], $data);
	pg_assert_contains('<form id="software_add_comment"', $html, 'the form');
	pg_assert_contains('name="name"', $html, 'the box for the visitor\'s name');
	pg_assert_contains('name="message"', $html, 'the message box');
	pg_assert_contains('pg-test-captcha', $html, 'the CAPTCHA');
	pg_assert_false(strpos($html, '/login?send_to'), 'no sign-in link');
	pg_assert_false(strpos($html, '^^'), 'no token left');
}

// Both comment e-mails: the blog's comment e-mail and the conversation's
// reply e-mail, each with its card right before its System region.
function test_design_template_playground_comment_emails()
{
	_tpl_threads_check_email_card('pinegrap-playground', 'email_comment_card', 'email_comment', array('blog_post'));
	_tpl_threads_check_email_card('pinegrap-playground', 'email_reply_card', 'email_reply', array('conversation'));
}
