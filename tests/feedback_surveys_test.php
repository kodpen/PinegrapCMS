<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the survey settings of the Surveys tab
 * (includes/fn/feedback_surveys.php): the starting points, a stored row
 * read back, the texts per language, the edit screen's input, the delay,
 * the list's words and the e-mail's body.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php';

function feedback_surveys_test_known()
{
	return array('users' => array(4, 9), 'channels' => array(12), 'languages' => array('tr', 'en'));
}

function test_feedback_surveys_start_is_off()
{
	foreach (array_keys(pg_feedback_touchpoints()) as $key) {
		$survey = pg_feedback_survey_start($key);
		pg_assert_same(0, $survey['enabled'], $key . ' starts off');
		pg_assert_same($key, $survey['touchpoint'], $key . ' keeps what it follows');
		pg_assert_same('email', $survey['channel'], $key . ' goes by e-mail');
	}

	pg_assert_same(1, pg_feedback_survey_start('periodic')['purpose'], 'the periodic NPS is commercial');
	pg_assert_same(PG_FEEDBACK_NPS, pg_feedback_survey_start('periodic')['kind'], 'the periodic one is an NPS');
	pg_assert_same(0, pg_feedback_survey_start('booking')['purpose'], 'after a booking: a service message');
	pg_assert_same(array('service' => 0), pg_feedback_survey_start('booking')['filter'], 'any service');
	pg_assert_same(null, pg_feedback_survey_start('newsletter'), 'an unknown start gives nothing');
}

function test_feedback_surveys_decode_fills_every_key()
{
	$survey = pg_feedback_survey_decode(array('id' => '7', 'name' => 'Visit', 'kind' => '2', 'touchpoint' => 'form', 'filter' => '{"form":5,"field":"8","value":"Done","stray":1}', 'texts' => null, 'enabled' => '1'));
	pg_assert_same(7, $survey['id'], 'the id as a number');
	pg_assert_same(array('form' => 5, 'field' => 8, 'value' => 'Done'), $survey['filter'], 'the form\'s keys only, typed');
	pg_assert_same(array('question' => array(), 'comment' => array(), 'extra' => array()), $survey['texts'], 'no texts: every key empty');
	pg_assert_same('booking', pg_feedback_survey_decode(array('touchpoint' => 'unknown'))['touchpoint'], 'an unknown touchpoint reads as a booking');
	pg_assert_same(array('every' => 6, 'active' => 12), pg_feedback_survey_decode(array('touchpoint' => 'periodic', 'filter' => 'not json'))['filter'], 'broken JSON gives the defaults');
}

function test_feedback_surveys_texts_decode()
{
	$texts = pg_feedback_texts_decode('{"question":{"tr":"Nasıldı?","en":5},"extra":[{"type":"choice","label":{"tr":"Şube"},"choices":{"tr":["A","B",3]}},{"type":"other"},{},{},"x"]}');
	pg_assert_same(array('tr' => 'Nasıldı?'), $texts['question'], 'a text that is no string is left out');
	pg_assert_same(3, count($texts['extra']), 'at most three extra questions');
	pg_assert_same(array('tr' => array('A', 'B')), $texts['extra'][0]['choices'], 'choices are strings');
	pg_assert_same('text', $texts['extra'][1]['type'], 'an unknown type is a short text');
	pg_assert_same('Nasıldı?', pg_feedback_text_in($texts['question'], 'en', 'tr'), 'a missing language falls back to the first');
	pg_assert_same('', pg_feedback_text_in(array('tr' => '  '), 'tr', 'tr'), 'blank is none');
}

function test_feedback_surveys_choices_split()
{
	pg_assert_same(array('Ankara', 'İzmir'), pg_feedback_choices_split(' Ankara, İzmir,,Ankara '), 'trimmed, each once');
	pg_assert_same(10, count(pg_feedback_choices_split(implode(',', range(1, 15)))), 'at most ten');
}

function test_feedback_surveys_delay_split()
{
	pg_assert_same(array('amount' => 2, 'unit' => 'days'), pg_feedback_delay_split(2880), 'whole days');
	pg_assert_same(array('amount' => 3, 'unit' => 'hours'), pg_feedback_delay_split(180), 'hours');
	pg_assert_same(array('amount' => 0, 'unit' => 'hours'), pg_feedback_delay_split(0), 'right after');
}

function test_feedback_surveys_input_reads_the_form()
{
	$post = array(
		'name' => '  Visit  ', 'kind' => '2', 'channel' => 'sms', 'purpose' => '1', 'enabled' => '1', 'review_invite' => '1',
		'fallback_user_id' => '9', 'escalation_user_id' => '77', 'ws_channel_id' => '12',
		'filter' => array('service' => '3', 'stray' => 'x'), 'delay_amount' => '2', 'delay_unit' => 'days',
		'question' => array('tr' => 'Nasıldı?', 'en' => '', 'de' => 'Wie?'), 'comment' => array('tr' => 'Neden?'),
		'extra' => array(
			array('type' => 'choice', 'label' => array('tr' => 'Şube', 'en' => 'Branch'), 'choices' => array('tr' => 'Kadıköy, Beşiktaş', 'en' => 'East')),
			array('type' => 'text', 'label' => array('tr' => '', 'en' => 'Only English')),
		),
	);
	$input = pg_feedback_survey_input($post, feedback_surveys_test_known(), 'booking');
	$survey = $input['survey'];

	pg_assert_same(array(), $input['errors'], 'nothing wrong');
	pg_assert_same('Visit', $survey['name'], 'the name trimmed');
	pg_assert_same(PG_FEEDBACK_CSAT, $survey['kind'], 'CSAT');
	pg_assert_same('sms', $survey['channel'], 'by SMS');
	pg_assert_same(1, $survey['purpose'], 'commercial when asked');
	pg_assert_same(1, $survey['enabled'], 'switched on');
	pg_assert_same(9, $survey['fallback_user_id'], 'a user the screen offered');
	pg_assert_same(0, $survey['escalation_user_id'], 'a user it did not offer is none');
	pg_assert_same(12, $survey['ws_channel_id'], 'the channel');
	pg_assert_same(array('service' => 3), $survey['filter'], 'only the booking\'s key');
	pg_assert_same(2880, $survey['delay_minutes'], 'two days');
	pg_assert_same(array('tr' => 'Nasıldı?'), $survey['texts']['question'], 'empty and unknown languages are left out');
	pg_assert_same(1, count($survey['texts']['extra']), 'a question without the first language\'s label is dropped');
	pg_assert_same(array('tr' => array('Kadıköy', 'Beşiktaş'), 'en' => array('East')), $survey['texts']['extra'][0]['choices'], 'choices per language');
}

function test_feedback_surveys_input_refuses()
{
	$known = feedback_surveys_test_known();
	$errors = pg_feedback_survey_input(array('name' => ''), $known, 'booking')['errors'];
	pg_assert_same(1, count($errors), 'a survey needs a name');

	$errors = pg_feedback_survey_input(array('name' => 'X', 'delay_amount' => '91', 'delay_unit' => 'days'), $known, 'booking')['errors'];
	pg_assert_same(1, count($errors), 'at most 90 days after');

	$errors = pg_feedback_survey_input(array('name' => 'X', 'filter' => array('form' => '5', 'field' => '8', 'value' => ' ')), $known, 'form')['errors'];
	pg_assert_same(1, count($errors), 'a choice field needs its value');

	$errors = pg_feedback_survey_input(array('name' => 'X', 'extra' => array(array('type' => 'choice', 'label' => array('tr' => 'Şube'), 'choices' => array('tr' => 'Tek')))), $known, 'booking')['errors'];
	pg_assert_same(1, count($errors), 'a choice question needs two choices');

	$periodic = pg_feedback_survey_input(array('name' => 'X', 'purpose' => '0', 'delay_amount' => '5', 'filter' => array('every' => '0', 'active' => '12')), $known, 'periodic');
	pg_assert_same(1, count($periodic['errors']), 'every 0 months is refused');
	pg_assert_same(1, $periodic['survey']['purpose'], 'the periodic one stays commercial');
	pg_assert_same(0, $periodic['survey']['delay_minutes'], 'the periodic one has no delay');
}

function test_feedback_surveys_columns_round_trip()
{
	$survey = pg_feedback_survey_input(array('name' => 'Visit', 'filter' => array('form' => '5', 'field' => '0'), 'question' => array('tr' => 'Nasıldı?')), feedback_surveys_test_known(), 'form')['survey'];
	$columns = pg_feedback_survey_columns($survey);
	pg_assert_same(0, $columns['enabled'], 'off unless switched on');
	$back = pg_feedback_survey_decode($columns);
	pg_assert_same($survey['filter'], $back['filter'], 'the filter comes back');
	pg_assert_same($survey['texts'], $back['texts'], 'the texts come back');
	pg_assert_same(null, pg_feedback_survey_columns(pg_feedback_survey_start('delivery'))['filter'], 'a delivery has no filter');
}

function test_feedback_surveys_sent_after_words()
{
	pg_assert_same('2 hours after a booking', pg_feedback_sent_after_words(pg_feedback_survey_start('booking')), 'hours');
	pg_assert_same('2 days after a delivery', pg_feedback_sent_after_words(pg_feedback_survey_start('delivery')), 'days');
	pg_assert_same('Every 6 months', pg_feedback_sent_after_words(pg_feedback_survey_start('periodic')), 'the periodic one');
	$mail = pg_feedback_survey_start('mail');
	$mail['delay_minutes'] = 0;
	pg_assert_same('Right after a mail conversation', pg_feedback_sent_after_words($mail), 'no delay');
	pg_assert_same('2 hours after a queue visit', pg_feedback_sent_after_words(pg_feedback_survey_start('queue')), 'a queue visit');
	pg_assert_same(array('place' => 0), pg_feedback_survey_start('queue')['filter'], 'a queue survey filters by place');
}

function test_feedback_surveys_mail_has_every_score()
{
	$html = pg_feedback_mail_html(PG_FEEDBACK_NPS, 'How <likely>?', 'Why?');
	pg_assert_contains('How &lt;likely&gt;?', $html, 'the question escaped');
	pg_assert_same(11, substr_count($html, '<td'), 'eleven NPS buttons');
	pg_assert_same(5, substr_count(pg_feedback_mail_html(PG_FEEDBACK_CSAT, 'Q', 'C'), '<td'), 'five CSAT buttons');
	pg_assert_false(strpos($html, '<a ') !== false, 'a preview leads nowhere');
}
