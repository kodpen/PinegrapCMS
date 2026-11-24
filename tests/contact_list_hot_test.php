<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contacts list's Hot Contacts view
 * (includes/fn/contact_list.php): its condition, columns and order, the
 * strongest reason, the empty line and its place on the list table driver.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_score.php');

function test_contact_list_hot_view_reads_the_hot_flag()
{
	$view = pg_contact_list_view('hot_contacts');

	pg_assert_same(' INNER JOIN contact_scores ON contact_scores.contact_id = contacts.id', $view['join'], 'the scores joined by the view');
	pg_assert_same('(contact_scores.hot = 1)', $view['condition'], 'only hot contacts');
	pg_assert_same(null, $view['membership_label'], 'no membership view');
	pg_assert_false(pg_contact_list_hot_ready(), 'no database in the test run');
	pg_assert_false(array_key_exists('hot_contacts', pg_contact_list_filters(0)), 'not offered before the scores are kept');
}

function test_contact_list_hot_columns()
{
	$columns = pg_list_table_columns(pg_contact_list_columns('hot_contacts', false, true));

	pg_assert_same(array('picture', 'contact', 'score', 'reason', 'last_visit', 'actions'), array_keys($columns));
	pg_assert_same('contact_scores.interest', $columns['score']['sort']);
	pg_assert_same('contact_scores.last_visit_at', $columns['last_visit']['sort']);
	pg_assert_false($columns['reason']['sortable'], 'the reason is no column of the table');
	pg_assert_true(isset(pg_contact_list_columns('hot_contacts', true, true)['groups']), 'the groups follow their toggle');
}

function test_contact_list_hot_order_and_select()
{
	$sort = pg_contact_list_sort('hot_contacts');

	pg_assert_same(array(array('sort' => 'score', 'order' => 'desc'), array('sort' => 'last_visit', 'order' => 'desc')), $sort['sort_levels'], 'the highest first, then the latest visit');
	pg_assert_contains('contact_scores.interest desc', strtolower($sort['order_by']));
	pg_assert_contains('contacts.id asc', $sort['order_by'], 'the id keeps the pages apart');

	$select = pg_contact_list_select('hot_contacts', true);
	pg_assert_contains('contact_scores.interest as lead_interest', $select);
	pg_assert_contains('contact_scores.reasons as lead_reasons', $select);
	pg_assert_contains('contact_scores.last_visit_at as lead_last_visit', $select);
	pg_assert_false(strpos(pg_contact_list_select('all_my_contacts', true), 'lead_reasons') !== false, 'the other views read no reasons');
}

function test_contact_list_hot_reason()
{
	$reasons = json_encode(array(
		'interest' => array(array('kind' => 'form', 'points' => 10), array('kind' => 'pricing_page', 'points' => 24.5)),
		'fit' => array(array('kind' => 'company_domain', 'points' => 40)),
	));

	pg_assert_same(pg_lead_score_reason_text('pricing_page'), pg_contact_list_hot_reason($reasons), 'the interest reason with the most points');
	pg_assert_same(pg_lead_score_reason_text('company_domain'), pg_contact_list_hot_reason(json_encode(array('interest' => array(), 'fit' => array(array('kind' => 'company_domain', 'points' => 40))))), 'else the fit reason');
	pg_assert_same('', pg_contact_list_hot_reason('not json'));
	pg_assert_same('', pg_contact_list_hot_reason(json_encode(array('interest' => array(array('kind' => 'unknown', 'points' => 9))))), 'an unknown kind says nothing');
}

function test_contact_list_hot_empty_line()
{
	pg_assert_contains(lang('No contact is hot today.'), pg_contact_list_hot_empty(false));
	pg_assert_false(strpos(pg_contact_list_hot_empty(false), '<a') !== false, 'no settings link for a user');
	pg_assert_contains('href="settings_privacy.php#pgset-interest-score"', pg_contact_list_hot_empty(true), 'the threshold\'s setting for a manager');

	$source = pg_list_table_source(array('list' => 'view_contacts', 'columns' => array('name' => array('heading' => 'Name')), 'empty' => pg_contact_list_hot_empty(true)));
	$html = pg_list_table_html($source, array('rows' => array(), 'next' => null, 'total' => 0), 'Hot');
	pg_assert_contains('<p class="pg-list-table-empty fg-secondary">' . pg_contact_list_hot_empty(true) . '</p>', $html, 'the list\'s own empty line');

	$plain = pg_list_table_source(array('list' => 'view_contacts', 'columns' => array('name' => array('heading' => 'Name'))));
	pg_assert_contains(h(lang('No records found.')), pg_list_table_html($plain, array('rows' => array(), 'next' => null, 'total' => 0), 'Any'), 'other lists keep theirs');
}

function test_contact_list_hot_alert_opens_the_view()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ops_alerts.php');

	pg_assert_contains("'url'   => 'view_contacts.php?filter=hot_contacts'", $source, 'the bell link opens the Hot Contacts view');
}
