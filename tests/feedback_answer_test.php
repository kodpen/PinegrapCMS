<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a satisfaction survey's message and its answer page
 * (includes/fn/feedback_answer.php, feedback.php): the signed links, the
 * e-mail's buttons, the SMS text and kind, where an answer stands, what the
 * page takes from a post, the review invitation, the sender the job finds,
 * the channels it sends, the page's rules and its firewall allowance.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php';

function feedback_answer_test_survey($overrides = array())
{
	return array_merge(array(
		'id' => 3, 'kind' => PG_FEEDBACK_NPS, 'touchpoint' => 'booking', 'purpose' => 0, 'review_invite' => 1,
		'texts' => array(
			'question' => array('en' => 'How likely?', 'tr' => 'Ne kadar olası?'),
			'comment' => array('en' => 'Why?'),
			'extra' => array(
				array('type' => 'choice', 'label' => array('en' => 'Branch', 'tr' => 'Şube'), 'choices' => array('en' => array('North', 'South'), 'tr' => array('Kuzey', 'Güney'))),
				array('type' => 'text', 'label' => array('en' => 'Staff name'), 'choices' => array()),
			),
		),
	), $overrides);
}

function test_feedback_answer_links_are_signed_at_the_due_time()
{
	$key = str_repeat('k', 32);
	$links = pg_feedback_links('https://example.com/pinegrap/feedback.php', 41, 1800000000, PG_FEEDBACK_NPS, $key);

	pg_assert_same(11, count($links['scores']), 'a link per NPS score');
	pg_assert_same(5, count(pg_feedback_links('p', 41, 1800000000, PG_FEEDBACK_CSAT, $key)['scores']), 'a link per CSAT score');
	pg_assert_same($links['page'] . '&s=0', $links['scores'][0], 'the score in the link');

	$token = substr($links['page'], strpos($links['page'], '?t=') + 3);
	pg_assert_same(41, pg_feedback_token_check($token, 'answer', 1800000060, $key), 'the answer token names the row');
	pg_assert_same(pg_feedback_token_hash(pg_feedback_token_make('answer', 41, 1800000000, $key)), pg_feedback_token_hash($token), 'the hash the queue stored');

	$stop = substr($links['stop'], strpos($links['stop'], '?x=') + 3);
	pg_assert_same(41, pg_feedback_token_check($stop, 'stop', 1800000060, $key), 'the stop token names the row');
	pg_assert_same(0, pg_feedback_token_check($stop, 'answer', 1800000060, $key), 'a stop token never answers');
	pg_assert_same(array(), pg_feedback_links('p', 41, 1800000000, PG_FEEDBACK_NPS, ''), 'no key, no links');
}

function test_feedback_answer_mail_has_a_link_per_score_and_the_stop()
{
	$links = pg_feedback_links('https://example.com/feedback.php', 7, 1800000000, PG_FEEDBACK_CSAT, str_repeat('k', 32));
	$html = pg_feedback_mail_html(PG_FEEDBACK_CSAT, 'How <was> it?', 'Tell us', $links);

	pg_assert_same(6, substr_count($html, '<a '), 'five scores and the stop');
	pg_assert_contains(h($links['scores'][5]), $html, 'the score link escaped');
	pg_assert_contains(h($links['stop']), $html, 'Don\'t ask me again');
	pg_assert_contains('How &lt;was&gt; it?', $html, 'the question escaped');
	pg_assert_false(strpos($html, 'review') !== false, 'no review invitation in the message');
	pg_assert_false(strpos(pg_feedback_mail_html(PG_FEEDBACK_CSAT, 'Q', 'C'), '<a ') !== false, 'a preview leads nowhere');
}

function test_feedback_answer_sms_text()
{
	pg_assert_same('Shop: How was it? https://x/f?t=a', pg_feedback_sms_text('Shop', 'How was it?', 'https://x/f?t=a', false));
	pg_assert_same('How was it? https://x/f?t=a', pg_feedback_sms_text('', 'How was it?', 'https://x/f?t=a', false), 'no store name');
	pg_assert_contains(lang(array('string' => 'Reminder: {var:1}', 'vars' => array('How was it?'))), pg_feedback_sms_text('Shop', 'How was it?', 'u', true));
}

function test_feedback_answer_sms_kind_is_transactional()
{
	pg_assert_same(false, pg_sms_kinds()['feedback'], 'a service survey is no commercial message');
	pg_assert_true(isset(pg_sms_queue_list_kinds()['feedback']), 'labelled on the queue');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php');
	pg_assert_contains("((int) \$survey['purpose'] === 1) ? 'marketing' : 'feedback'", $source, 'a commercial survey goes as the commercial kind');
}

function test_feedback_answer_state()
{
	$now = 1800000000;

	pg_assert_same('open', pg_feedback_answer_state(array('status' => PG_FEEDBACK_SENT, 'answered_at' => 0), $now));
	pg_assert_same('changeable', pg_feedback_answer_state(array('status' => PG_FEEDBACK_ANSWERED, 'answered_at' => $now - 6 * 86400), $now), '6 days: still changed');
	pg_assert_same('answered', pg_feedback_answer_state(array('status' => PG_FEEDBACK_ANSWERED, 'answered_at' => $now - 8 * 86400), $now), '8 days: kept');

	foreach (array(PG_FEEDBACK_QUEUED, PG_FEEDBACK_CANCELLED, PG_FEEDBACK_NO_ANSWER) as $status) {
		pg_assert_same('closed', pg_feedback_answer_state(array('status' => $status, 'answered_at' => 0), $now), (string) $status);
	}
}

function test_feedback_answer_input()
{
	$survey = feedback_answer_test_survey();

	pg_assert_same(null, pg_feedback_answer_input(array(), $survey, 'en', 'en'), 'no score');
	pg_assert_same(null, pg_feedback_answer_input(array('score' => '11'), $survey, 'en', 'en'), 'out of range');
	pg_assert_same(null, pg_feedback_answer_input(array('score' => array('5')), $survey, 'en', 'en'), 'not a value');
	pg_assert_same(0, pg_feedback_answer_input(array('score' => '0'), $survey, 'en', 'en')['score'], '0 is an NPS answer');

	$answer = pg_feedback_answer_input(array(
		'score' => '9', 'comment' => "  Good\r\nservice\x07  ", 'extra' => array('Güney', str_repeat('a', 400)),
	), $survey, 'tr', 'en');

	pg_assert_same("Good\nservice", $answer['comment'], 'trimmed, control characters dropped');
	pg_assert_same(array('question' => 'Branch', 'answer' => 'South'), $answer['answers'][0], 'a choice kept in the first language');
	pg_assert_same(PG_FEEDBACK_SHORT_MAX, mb_strlen($answer['answers'][1]['answer']), 'a short text cut');

	$other = pg_feedback_answer_input(array('score' => '9', 'extra' => array('West', '')), $survey, 'en', 'en');
	pg_assert_same(array(), $other['answers'], 'a choice not offered and an empty text are left out');
	pg_assert_same(PG_FEEDBACK_COMMENT_MAX, mb_strlen(pg_feedback_answer_input(array('score' => '9', 'comment' => str_repeat('ç', 2500)), $survey, 'en', 'en')['comment']));
}

function test_feedback_answer_review_invitation_is_the_same_for_every_score()
{
	$survey = feedback_answer_test_survey();

	pg_assert_same('https://search.google.com/local/writereview?placeid=ChIJ%2Bab', pg_feedback_review_url($survey, 'ChIJ+ab', ''));
	pg_assert_same('', pg_feedback_review_url($survey, '', ''), 'no place set');
	pg_assert_same('', pg_feedback_review_url(feedback_answer_test_survey(array('review_invite' => 0)), 'ChIJ', ''), 'switched off');
	pg_assert_same('https://x/products/cup#software_add_comment', pg_feedback_review_url(feedback_answer_test_survey(array('touchpoint' => 'delivery')), 'ChIJ', 'https://x/products/cup#software_add_comment'), 'a delivery: the product');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/feedback.php');
	pg_assert_contains('pg_feedback_review_url_of($fb_survey, $fb_row)', $page, 'the invitation is given no score');
}

function test_feedback_answer_texts_in()
{
	$survey = feedback_answer_test_survey();

	pg_assert_same(array('question' => 'Ne kadar olası?', 'comment' => 'Why?'), pg_feedback_texts_in($survey, 'tr', 'en'), 'own language, else the first');
	pg_assert_same(pg_feedback_default_texts(PG_FEEDBACK_NPS)['question'], pg_feedback_texts_in(feedback_answer_test_survey(array('texts' => pg_feedback_texts_decode(null))), 'en', 'en')['question'], 'the default');
}

function test_feedback_answer_job_finds_the_sender()
{
	pg_assert_same('pg_feedback_message_send', pg_feedback_sender());
	pg_assert_same(array('email', 'sms'), pg_feedback_message_channels(), 'WhatsApp waits for its message');

	$sends = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_sends.php');
	pg_assert_contains('AND (channel IN (" . $channels . "))', $sends, 'only the built channels are sent');
}

function test_feedback_answer_page_rules()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/feedback.php');

	foreach (array("header('Cache-Control: no-store, private');", "header('X-Robots-Tag: noindex, nofollow');", "header('Referrer-Policy: no-referrer');", "header('X-Frame-Options: DENY');") as $header) {
		pg_assert_contains($header, $page);
	}

	pg_assert_contains("if (\$fb_post) {\n    validate_token_field();\n}", $page, 'every form post checks the token');
	pg_assert_contains("hash_equals(\$fb_session, \$_POST['token'])", $page, 'the score write checks the token itself');
	pg_assert_same(3, substr_count($page, 'pg_feedback_answer_write(') + substr_count($page, 'pg_feedback_stop('), 'two answer writes and the stop');
	pg_assert_same(0, preg_match('/\$_GET\[[^\]]+\][^;]*pg_feedback_(answer_write|stop)\(/', $page), 'a GET writes nothing');

	foreach (array("\$fb_action === 'score'", "\$fb_action === 'send'", "\$fb_action === 'stop'") as $action) {
		pg_assert_contains($action, $page, 'only from a post: ' . $action);
	}

	pg_assert_contains("\$fb_action = (\$fb_post && ", $page, 'an action is read from a post only');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_sensitive_scripts()');
	pg_assert_true(strpos(substr($waf, $start, strpos($waf, "\n}\n", $start) - $start), "'feedback.php'") !== false, 'counted on the sensitive allowance');
}

function test_feedback_answer_write_keeps_the_first_answer_time()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php');

	pg_assert_contains("answered_at = IF(answered_at = 0, '\" . (int) \$now . \"', answered_at)", $source);
	pg_assert_contains("\$where = \$score_only ? \"(status = \" . PG_FEEDBACK_SENT . \")\"", $source, 'the page\'s own write never overwrites an answer');
	pg_assert_contains('PG_FEEDBACK_CHANGE_DAYS * 86400', $source);
}
