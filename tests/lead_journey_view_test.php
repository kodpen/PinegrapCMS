<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey as staff read it
 * (includes/fn/lead_journey_view.php): which journey rows stand for an act,
 * a visit's pages, the merge by time, the day groups, the first touch, the
 * sentences, a row's markup; and the pins: the contact screen's card, the
 * Workspace card's row, the general job's trim, the erase and export.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey_view.php');

function test_lead_journey_view_row_kinds()
{
	pg_assert_same('newsletter', pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_TIE, 'detail' => 'newsletter')));
	pg_assert_same('campaign_click', pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_TIE, 'detail' => 'campaign_click')));
	pg_assert_same('download', pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_DOWNLOAD, 'detail' => 'price-list.pdf')));
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_TIE, 'detail' => 'form')), 'a form has its own row');
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_TIE, 'detail' => 'order')), 'so has an order');
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => PG_LEAD_JOURNEY_TIE, 'detail' => 'sign_in')), 'a sign-in is no row');
	pg_assert_same(null, pg_lead_journey_row_kind(array('kind' => 9)), 'an unknown kind');

	foreach (pg_lead_journey_kinds() as $kind => $spec) {
		pg_assert_true(in_array($spec[2], array('', 'visits', 'forms', 'orders'), true), $kind . ' falls under a filter');
	}
}

function test_lead_journey_view_visit_pages()
{
	$pages = pg_lead_journey_visit_pages(json_encode(array(array('/', 40, 100), array('/pricing', 150, 140), array('', 5, 290), array('/contact', 0, 290))));

	pg_assert_same(3, $pages['count'], 'an empty page is left out');
	pg_assert_same(3, $pages['minutes'], '190 seconds round to 3 minutes');
	pg_assert_same(array('/pricing', 150), $pages['pages'][1]);
	pg_assert_same(array('detail', 30, 'product', 12), pg_lead_journey_visit_pages(array(array('detail', 30, 100, 'product', 12)))['pages'][0], 'the catalogue item stays on the page');
	pg_assert_same(0, pg_lead_journey_visit_pages(null)['count'], 'no pages');
	pg_assert_same(0, pg_lead_journey_visit_pages('broken')['count'], 'broken JSON');
}

function test_lead_journey_view_merge_newest_first()
{
	$rows = pg_lead_journey_merge(array(
		array('kind' => 'visit', 'id' => 1, 'at' => 100),
		array('kind' => 'order', 'id' => 7, 'at' => 300),
		array('kind' => 'form', 'id' => 3, 'at' => 300),
		array('kind' => 'visit', 'id' => 2, 'at' => 300),
		array('kind' => 'unknown', 'id' => 4, 'at' => 500),
		array('kind' => 'form', 'id' => 5, 'at' => 0),
	));

	pg_assert_same(array('visit:2', 'form:3', 'order:7', 'visit:1'), array_map(function ($row) {
		return $row['kind'] . ':' . $row['id'];
	}, $rows), 'by time, then the kinds\' order; unknown kinds and rows without a time left out');
}

function test_lead_journey_view_days_and_first_touch()
{
	$day = mktime(12, 0, 0, 11, 20, 2026);
	$days = pg_lead_journey_days(array(
		array('kind' => 'visit', 'at' => $day + 3600),
		array('kind' => 'form', 'at' => $day),
		array('kind' => 'visit', 'at' => $day - 86400),
	));

	pg_assert_same(2, count($days), 'a day a group');
	pg_assert_same('2026-11-20', $days[0]['day']);
	pg_assert_same(2, count($days[0]['rows']));
	pg_assert_same('2026-11-19', $days[1]['day']);

	$first = pg_lead_journey_first_touch(array(
		array('started_at' => 500, 'channel' => 'direct', 'campaign' => '', 'landing_page' => '/'),
		array('started_at' => 200, 'channel' => 'paid', 'campaign' => 'spring', 'landing_page' => '/offer'),
		array('started_at' => 0, 'channel' => 'email'),
	));

	pg_assert_same(array('channel' => 'paid', 'campaign' => 'spring', 'landing_page' => '/offer', 'at' => 200), $first, 'the earliest visit');
	pg_assert_same(null, pg_lead_journey_first_touch(array()), 'no visit');
}

function test_lead_journey_view_sentences()
{
	pg_assert_same('Visited 6 pages, 4 minutes', pg_lead_journey_sentence(array('kind' => 'visit', 'count' => 6, 'minutes' => 4)));
	pg_assert_same('Visited 1 page', pg_lead_journey_sentence(array('kind' => 'visit', 'count' => 1, 'minutes' => 0)));
	pg_assert_same('Sent the form Contact us', pg_lead_journey_sentence(array('kind' => 'form', 'name' => 'Contact us')));
	pg_assert_same('Subscribed to the newsletter', pg_lead_journey_sentence(array('kind' => 'newsletter')));
	pg_assert_same('Opened a campaign e-mail', pg_lead_journey_sentence(array('kind' => 'campaign_click', 'name' => '')));
	pg_assert_same('Placed order 1044', pg_lead_journey_sentence(array('kind' => 'order', 'name' => '1044')));
	pg_assert_same('Completed the course Pottery', pg_lead_journey_sentence(array('kind' => 'course', 'name' => 'Pottery', 'complete' => true)));
}

function test_lead_journey_view_row_markup()
{
	$row = array('kind' => 'visit', 'icon' => 'bi-globe2', 'tone' => 'blue', 'filter' => 'visits', 'text' => 'Visited 2 pages', 'time_text' => '10:00',
		'day' => '2026-11-20', 'day_text' => '20 Nov', 'url' => '', 'amount_text' => '', 'pages' => array(array('page' => '/<b>', 'time_text' => '40 s')));
	$html = pg_lead_journey_row_html($row, true);

	pg_assert_contains('pg-icon-tile pg-tone-blue', $html, 'an icon tile by kind');
	pg_assert_contains('data-lead-filter="visits"', $html, 'the filter it falls under');
	pg_assert_contains(' hidden>', $html, 'hidden until "Show earlier"');
	pg_assert_contains('/&lt;b&gt;', $html, 'a page is escaped');
	pg_assert_contains('data-lead-toggle', $html, 'a visit opens to its pages');
	pg_assert_false(strpos(pg_lead_journey_row_html(array('pages' => array()) + $row), 'data-lead-toggle') !== false, 'no pages, no toggle');
}

function test_lead_journey_view_pins()
{
	$contact = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact.php');
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey_view.php');
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/customer.php');
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	pg_assert_contains('pg_lead_journey_contact_card($id, $user)', $contact, 'the card on the contact screen');
	pg_assert_contains('pg_lead_journey_contact_is_staff($contact_id)', $view, 'a contact with panel work shows no journey');
	pg_assert_contains("lang('All')", $view, 'the filter segments');
	pg_assert_contains("lang('Show earlier')", $view);
	pg_assert_contains('No visits are tied to this contact', $view, 'one grey line without visits');
	pg_assert_false(strpos($view, 'pg-button-fill') !== false, 'no filled button: the screen\'s Save stays the one');
	pg_assert_contains("\$card['journey'] = ", $card, 'the folded row on the Workspace card');
	pg_assert_contains('ws_customer_contacts_open($viewer', $card, 'only for a contact the reader may open');
	pg_assert_contains('pg_lead_trim_run(time())', $job, 'the general job trims the keep period');

	$holders = pg_contact_holders();
	$erase = pg_privacy_erase_tables();

	foreach (array('lead_browsers', 'lead_visits', 'contact_journey', 'contact_scores') as $table) {
		pg_assert_same('find', $holders[$table]['erase'], $table . ' is erased with the person');
		pg_assert_same('delete', $erase[$table]['action'], $table . ' rows are deleted');
		pg_assert_true(isset(pg_privacy_inventory()[$table]), $table . ' is in the inventory');
	}

	foreach (array('journey_visits', 'journey', 'interest_score') as $section) {
		pg_assert_true(array_key_exists($section, pg_privacy_export_sections()), $section . ' is exported');
	}
}
