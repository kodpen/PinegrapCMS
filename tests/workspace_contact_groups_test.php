<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact-group rule in the Workspace (includes/workspace/
 * refs.php): a person with the user role and the contacts right opens only
 * the contacts filed in a contact group given to them, as on the contacts
 * screens (validate_contact_access()) and in the external API
 * (pg_contact_owner_sql()). Staff open every contact; nobody without the
 * contacts right opens one. Tags, the record search and the customer summary
 * follow the same rule. No database: the query is read from source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The body of a function with its comment lines left out, so a test reads
// the calls and not the comments that name them.
function workspace_contact_groups_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// One case of the ws_ref_search() switch, up to its break.
function workspace_contact_groups_test_search_case($type)
{
	$source = workspace_contact_groups_test_source('ws_ref_search');
	$start = strpos($source, "case '" . $type . "':");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, 'break;', $start);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

// Staff open every contact, the user role without the contacts right none;
// neither asks the database. Ids are cleaned once: numbers, no zero, no
// repeat, in the order given.
function test_ws_viewer_contacts_open_without_a_query()
{
	pg_assert_true(function_exists('ws_viewer_contacts_open'), 'the rule has one function');

	if (!function_exists('ws_viewer_contacts_open')) {
		return;
	}

	pg_assert_same(array(12, 5), ws_viewer_contacts_open(array('id' => 7, 'role' => 2, 'contacts' => true), array(12, '5', 0, 12, -3)), 'a manager opens every contact');
	pg_assert_same(array(12), ws_viewer_contacts_open(array('id' => 7, 'role' => 0, 'contacts' => false), array(12)), 'an administrator too');
	pg_assert_same(array(), ws_viewer_contacts_open(array('id' => 7, 'role' => 3, 'contacts' => false), array(12, 5)), 'the user role without the contacts right opens none');
	pg_assert_same(array(), ws_viewer_contacts_open(array('id' => 7, 'role' => 3, 'contacts' => true), array(0, -1)), 'no contact, no query');
}

// For the user role the contacts are those in a group given to the person:
// the same subquery the contacts screens and the external API use.
function test_ws_viewer_contacts_open_asks_the_groups()
{
	if (!function_exists('ws_viewer_contacts_open')) {
		pg_assert_true(false, 'ws_viewer_contacts_open() exists');

		return;
	}

	$source = workspace_contact_groups_test_source('ws_viewer_contacts_open');

	pg_assert_contains('pg_contact_owner_sql(', $source, 'the groups the contact is filed in and the groups given to the reader');
	pg_assert_contains("\$viewer['id']", $source, 'for the person reading');
}

// A contact tag names the contact only for a reader who may open it; one
// outside the reader's groups is drawn as for a reader without the contacts
// right: no name, no address, no link.
function test_ws_refs_resolve_contact_follows_the_groups()
{
	$source = workspace_contact_groups_test_source('ws_refs_resolve');
	$start = strpos($source, "if (!empty(\$ids['contact']))");

	pg_assert_true($start !== false, 'ws_refs_resolve() has a contact branch');

	if ($start === false) {
		return;
	}

	$branch = substr($source, $start, 1400);

	pg_assert_contains('ws_viewer_contacts_open(', $branch, 'the contact branch asks which contacts the reader may open');
	pg_assert_true(strpos($branch, 'ws_viewer_contacts_open(') < strpos($branch, "edit_contact.php"), 'before it names one');
}

// The record search finds, for the user role, only contacts in the person's
// groups, so a picker or a search never lists a name the reader may not open.
function test_ws_ref_search_contact_follows_the_groups()
{
	$case = workspace_contact_groups_test_search_case('contact');

	pg_assert_true($case !== '', 'ws_ref_search() has a contact case');
	pg_assert_contains('pg_contact_owner_sql(', $case, 'the search asks the groups for the user role');
	pg_assert_contains("\$viewer['role']", $case, 'and only for the user role');
}

// The customer summary keeps using the same rule, through the one function.
function test_ws_customer_contacts_open_uses_the_rule()
{
	if (!function_exists('ws_viewer_contacts_open')) {
		pg_assert_true(false, 'ws_viewer_contacts_open() exists');

		return;
	}

	pg_assert_contains('ws_viewer_contacts_open(', workspace_contact_groups_test_source('ws_customer_contacts_open'), 'the summary narrows its contacts with the same rule');
	pg_assert_same(array(12, 5), ws_customer_contacts_open(array('id' => 7, 'role' => 2, 'contacts' => true), array(12, '5', 0, 12)), 'staff still open every contact');
}
