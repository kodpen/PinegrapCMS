<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Clinic design template (includes/design_templates/
 * pinegrap-clinic.php): its comment threads are drawn by the Comments
 * widget (the post's comments, the messages of an appointment, a
 * conversation's replies, the reply e-mail's card) and its Google Reviews
 * widget stands beside the sample words of the home page. The parts the
 * Clinic shares with the Cafe are tested in design_template_cafe_test.php.
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

// The Clinic's threads: widget key => its page, the record view above it,
// its options, Reply and Share, the follow boxes.
function _test_tpl_clinic_threads()
{
	return array(
		'post_comments' => array('page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20)),
		'appointment_messages' => array('page' => 'appointment_view', 'view' => 'appointment_detail', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0)),
		'conversation_replies' => array('page' => 'conversation', 'view' => 'conversation', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0)),
	);
}

// No fixed comments block is left; the widget kinds the Clinic ships.
function test_design_template_clinic_comment_widgets()
{
	$tpl = _tpl_threads_tpl('pinegrap-clinic');
	pg_assert_same('1.1.2', $tpl['version'], 'the version moved');
	_tpl_threads_check_no_fixed_block('pinegrap-clinic', array(
		'appointment_messages' => 'comments', 'conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments',
	));
	pg_assert_same('google_reviews', pg_sw_widget_kind($tpl['widgets']['google_reviews']['config']), 'and a Google Reviews widget');
	// Each thread keeps its page's comment settings: signed-in writers,
	// published at once, its label and its e-mail.
	$labels = array('blog_post' => lang('Comment'), 'appointment_view' => lang('Message'), 'conversation' => lang('reply'));
	foreach ($labels as $key => $label) {
		$c = _tpl_threads_page($tpl, $key)['comments'];
		pg_assert_same($label, $c['label'], $key . ': its label');
		pg_assert_true($c['login'] && $c['auto_publish'], $key . ': signed in, published at once');
	}
}

function test_design_template_clinic_comment_threads()
{
	foreach (_test_tpl_clinic_threads() as $key => $t) {
		_tpl_threads_check_thread('pinegrap-clinic', $key, $t);
		_tpl_threads_check_draws('pinegrap-clinic', $key, $t);
	}
	// An appointment's messages are as private as the appointment: the view
	// above them shows it to its patient only.
	$tpl = _tpl_threads_tpl('pinegrap-clinic');
	foreach (array('appointment_detail', 'conversation') as $view) {
		pg_assert_same('submitter_only', $tpl['widgets'][$view]['config']['access_control'], $view . ': the patient\'s own');
	}
}

function test_design_template_clinic_comment_email()
{
	_tpl_threads_check_email_card('pinegrap-clinic', 'email_comment', 'email_reply', array('blog_post', 'appointment_view', 'conversation'));
}

function test_design_template_clinic_google_reviews()
{
	_tpl_threads_check_google_reviews('pinegrap-clinic', 'google_reviews', 'home', lang('Testimonials'));
	// The sample words stay, before the widget.
	$tpl = _tpl_threads_tpl('pinegrap-clinic');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	$sample = strpos($home, lang('“The heart check-up was done in one morning, and the results were in my account the same day.”'));
	$widget = strpos($home, '"templateWidget":"google_reviews"');
	pg_assert_true($sample !== false && $widget !== false && $sample < $widget, 'the sample words stay, the widget after them');
}
