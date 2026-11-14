<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Send a test to me" (includes/fn/campaign_test_send.php): the
 * addresses a test goes to, its subject, the sample photos pointed out, and
 * the panel on the campaign's content card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_campaign_test_addresses()
{
	$out = pg_campaign_test_addresses("a@example.com, b@example.com;\nme@example.com A@EXAMPLE.COM", 'me@example.com');
	pg_assert_same(array('me@example.com', 'a@example.com', 'b@example.com'), $out['addresses'], 'one\'s own first, each once');
	pg_assert_same('', $out['error'], 'may be sent');

	pg_assert_same(array('me@example.com'), pg_campaign_test_addresses('', 'me@example.com')['addresses'], 'to oneself only');
	pg_assert_same(6, count(pg_campaign_test_addresses('1@x.com 2@x.com 3@x.com 4@x.com 5@x.com', 'me@example.com')['addresses']), 'oneself and five more');

	$six = pg_campaign_test_addresses('1@x.com 2@x.com 3@x.com 4@x.com 5@x.com 6@x.com', 'me@example.com');
	pg_assert_same(array(), $six['addresses'], 'six more: none');
	pg_assert_contains('5', $six['error'], 'the limit named');

	$bad = pg_campaign_test_addresses('a@example.com, nope', 'me@example.com');
	pg_assert_same(array(), $bad['addresses'], 'a wrong address stops it');
	pg_assert_contains('nope', $bad['error'], 'the wrong address named');

	pg_assert_same(array('a@example.com'), pg_campaign_test_addresses('a@example.com', '')['addresses'], 'no own address: the typed ones');
	pg_assert_true(pg_campaign_test_addresses('', '')['error'] !== '', 'nowhere to send');
	pg_assert_same(5, pg_campaign_test_max_addresses(), 'five more addresses');
	pg_assert_same(10, pg_campaign_test_hourly_limit(), 'ten tests an hour per user');
}

function test_campaign_test_subject_and_sample_photos()
{
	pg_assert_same(lang(array('string' => '[Test] {var:1}', 'vars' => array('Spring sale'))), pg_campaign_test_subject('Spring sale'), 'the subject marked');
	pg_assert_contains('Spring sale', pg_campaign_test_subject('Spring sale'), 'the subject kept');

	$html = '<img src="https://picsum.photos/seed/a/600/300" alt=""><img alt="" src=\'https://placehold.co/320x96\'>'
		. '<img src="https://shop.example/a.jpg"><a href="https://picsum.photos/">x</a>';
	pg_assert_same(2, pg_campaign_test_sample_photos($html), 'the stand-in pictures only, not a link');
	pg_assert_same(0, pg_campaign_test_sample_photos('<img src="https://cdn.example/picsum.photos.jpg">'), 'a name that only looks like one');
}

function test_campaign_test_panel_on_the_card()
{
	$card = pg_campaign_content_card(array('mode' => 'edit', 'kind' => 'design', 'test' => array('own' => 'me@example.com', 'samples' => 2)));
	pg_assert_contains('name="send_test"', $card, 'the button posts the form');
	pg_assert_contains('name="test_addresses"', $card, 'the other addresses');
	pg_assert_contains('me@example.com', $card, 'one\'s own address shown');
	pg_assert_contains(h(lang('Send a test to me')), $card, 'its words');
	pg_assert_contains('pg-campaign-samples', $card, 'the sample photo warning');

	$clean = pg_campaign_content_card(array('mode' => 'edit', 'kind' => 'html', 'test' => array('own' => 'me@example.com', 'samples' => 0)));
	pg_assert_false(strpos($clean, 'pg-campaign-samples') !== false, 'no warning without sample photos');
	pg_assert_contains('name="send_test"', $clean, 'pasted HTML is tested too');

	pg_assert_false(strpos(pg_campaign_content_card(array('mode' => 'add')), 'send_test') !== false, 'nothing stored yet on create: no test');
	pg_assert_false(strpos(pg_campaign_content_card(array('mode' => 'read', 'test' => array('own' => 'a@b.co', 'samples' => 0))), 'send_test') !== false, 'a sent campaign: no test');
}
