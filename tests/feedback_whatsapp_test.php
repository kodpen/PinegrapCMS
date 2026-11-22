<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a satisfaction survey's WhatsApp message
 * (includes/fn/feedback_whatsapp.php): the stored choice, the values that
 * fill a template, when a choice can be sent, what the "Sent by" card's post
 * gives, the choice kept in the texts JSON, the pass's channel condition,
 * the filled components and where it is wired. No database and no call to
 * Meta.
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

function feedback_whatsapp_test_offered()
{
	return pg_whatsapp_message_offered(array(array(
		'name' => 'survey_ask', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL',
		'components' => array(
			array('type' => 'BODY', 'text' => '{{1}}: {{2}}'),
			array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'URL', 'text' => 'Answer', 'url' => 'https://shop.example/feedback.php?t={{1}}'))),
		),
	)));
}

function test_feedback_whatsapp_normalize()
{
	pg_assert_same(array('template' => '', 'vars' => array()), pg_feedback_whatsapp_normalize(null));
	pg_assert_same(array('template' => 'survey_ask|tr', 'vars' => array('body:1' => 'store')),
		pg_feedback_whatsapp_normalize(array('template' => ' survey_ask|tr ', 'vars' => array('body:1' => 'store', 'body:2' => 'phone', 'button:0' => 3))), 'unknown values dropped');
	pg_assert_same(array('template' => '', 'vars' => array()), pg_feedback_whatsapp_normalize(array('vars' => array('body:1' => 'store'))), 'no template, no values');
	pg_assert_same('survey_ask|tr', pg_feedback_whatsapp_from_texts('{"question":{},"whatsapp":{"template":"survey_ask|tr","vars":{}}}')['template']);
	pg_assert_same('', pg_feedback_whatsapp_from_texts(null)['template']);
}

function test_feedback_whatsapp_values()
{
	$values = pg_feedback_whatsapp_values('Shop', 'How likely?', 'https://shop.example/feedback.php?t=3f.abc.sig');
	pg_assert_same(array('store' => 'Shop', 'question' => 'How likely?', 'link' => 'https://shop.example/feedback.php?t=3f.abc.sig', 'code' => '3f.abc.sig'), $values);
	pg_assert_same('', pg_feedback_whatsapp_values('Shop', 'Q', '')['code'], 'no link, no code');
}

function test_feedback_whatsapp_choice_ready()
{
	$offered = feedback_whatsapp_test_offered();
	$full = array('template' => 'survey_ask|tr', 'vars' => array('body:1' => 'store', 'body:2' => 'question', 'button:0' => 'code'));

	pg_assert_true(pg_feedback_whatsapp_choice_ready($full, $offered));
	pg_assert_false(pg_feedback_whatsapp_choice_ready(array('template' => '', 'vars' => array()), $offered), 'no template: the rows wait');
	pg_assert_false(pg_feedback_whatsapp_choice_ready(array('template' => 'gone|tr', 'vars' => array()), $offered), 'not approved: the rows wait');
	pg_assert_false(pg_feedback_whatsapp_choice_ready(array('template' => 'survey_ask|tr', 'vars' => array('body:1' => 'store')), $offered), 'a variable unfilled');
}

function test_feedback_whatsapp_input()
{
	$offered = feedback_whatsapp_test_offered();
	$none = pg_feedback_whatsapp_normalize(null);
	$var = function ($position) {
		return pg_whatsapp_choice_var_name(PG_FEEDBACK_WA_PREFIX, 'survey_ask|tr', $position);
	};
	$post = array('fs_wa_template' => 'survey_ask|tr', $var(0) => 'store', $var(1) => 'question', $var(2) => 'code');

	$input = pg_feedback_whatsapp_input($post, 'whatsapp', $none, $offered);
	pg_assert_same(array(), $input['errors']);
	pg_assert_same(array('template' => 'survey_ask|tr', 'vars' => array('body:1' => 'store', 'body:2' => 'question', 'button:0' => 'code')), $input['choice']);

	// A WhatsApp survey with its template but a variable left empty.
	unset($post[$var(2)]);
	pg_assert_same(1, count(pg_feedback_whatsapp_input($post, 'whatsapp', $none, $offered)['errors']), 'every variable filled');

	// By e-mail the half-made choice is kept without a complaint.
	pg_assert_same(array(), pg_feedback_whatsapp_input($post, 'email', $none, $offered)['errors']);

	// No template, or one no longer approved: saved, its rows wait.
	pg_assert_same(array(), pg_feedback_whatsapp_input(array(), 'whatsapp', $none, $offered)['errors']);
	$before = array('template' => 'gone|tr', 'vars' => array('body:1' => 'store'));
	$kept = pg_feedback_whatsapp_input(array('fs_wa_template' => 'gone|tr'), 'whatsapp', $before, $offered);
	pg_assert_same(array(), $kept['errors']);
	pg_assert_same($before, $kept['choice'], 'kept as stored');

	// A value the card does not offer.
	$post[$var(2)] = 'phone';
	pg_assert_same(1, count(pg_feedback_whatsapp_input($post, 'whatsapp', $none, $offered)['errors']));
}

function test_feedback_whatsapp_kept_in_the_texts()
{
	$known = array('users' => array(), 'channels' => array(), 'languages' => array('en'), 'wa_offered' => feedback_whatsapp_test_offered());
	$var = function ($position) {
		return pg_whatsapp_choice_var_name(PG_FEEDBACK_WA_PREFIX, 'survey_ask|tr', $position);
	};
	$post = array('name' => 'Visit', 'channel' => 'whatsapp', 'fs_wa_template' => 'survey_ask|tr', $var(0) => 'store', $var(1) => 'question', $var(2) => 'code');
	$survey = pg_feedback_survey_input($post, $known, 'booking')['survey'];
	$columns = pg_feedback_survey_columns($survey);
	$decoded = pg_feedback_survey_decode(array_merge($columns, array('id' => 4)));

	pg_assert_same($survey['whatsapp'], $decoded['whatsapp'], 'the choice comes back from the texts JSON');
	pg_assert_same(array('question', 'comment', 'extra'), array_keys($decoded['texts']), 'the texts keep their own keys');

	$columns = pg_feedback_survey_columns(pg_feedback_survey_start('booking'));
	pg_assert_false(strpos($columns['texts'], 'whatsapp'), 'no template, nothing stored');
	pg_assert_same('', pg_feedback_survey_start('booking')['whatsapp']['template']);
}

function test_feedback_whatsapp_due_channel_sql()
{
	pg_assert_same("channel IN ('email', 'sms')", pg_feedback_due_channel_sql(array()), 'no WhatsApp survey ready: its rows wait');
	pg_assert_same("(channel IN ('email', 'sms') OR ((channel = 'whatsapp') AND (survey_id IN (4, 9))))", pg_feedback_due_channel_sql(array('4', 9, 4, 0)));
}

function test_feedback_whatsapp_components()
{
	$offered = feedback_whatsapp_test_offered();
	$template = array('status' => 'APPROVED', 'parameter_format' => 'POSITIONAL', 'components' => array(
		array('type' => 'BODY', 'text' => '{{1}}: {{2}}'),
		array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'URL', 'text' => 'Answer', 'url' => 'https://shop.example/feedback.php?t={{1}}'))),
	));
	$vars = array('body:1' => 'store', 'body:2' => 'question', 'button:0' => 'code');
	$filled = pg_whatsapp_message_components($template, $vars, pg_feedback_whatsapp_values('Shop', 'How likely?', 'https://shop.example/feedback.php?t=3f.abc.sig'));

	pg_assert_true($filled['ok']);
	pg_assert_same('Shop', $filled['components'][0]['parameters'][0]['text']);
	pg_assert_same('How likely?', $filled['components'][0]['parameters'][1]['text']);
	pg_assert_same('3f.abc.sig', $filled['components'][1]['parameters'][0]['text'], 'the link code ends the button link');
	pg_assert_true(isset($offered['survey_ask|tr']));
}

function test_feedback_whatsapp_wiring()
{
	$answer = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php');
	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_whatsapp.php');
	$sends = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_sends.php');
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/edit_feedback_survey.php');

	pg_assert_contains('pg_feedback_whatsapp_send($row, $survey, $store, $question, $links[\'page\']', $answer, 'the sender builds the WhatsApp message');
	pg_assert_contains("'kind'       => 'feedback'", $module, 'queued as kind feedback');
	pg_assert_contains("'reference'  => 'feedback:' . (int) \$row['id'] . (\$reminder ? ':r' : '')", $module, 'one message per row and reminder');
	pg_assert_contains('pg_whatsapp_enqueue(', $module, 'only queued');
	pg_assert_false(strpos($module, 'pg_whatsapp_cloud_send('), 'never sent from here');
	pg_assert_contains('pg_feedback_due_channel_sql(pg_feedback_whatsapp_ready_surveys())', $sends, 'the pass takes only ready WhatsApp surveys');
	pg_assert_contains('pg_feedback_whatsapp_card_html($fs[\'whatsapp\']', $screen, 'the template on the Sent by card');
}
