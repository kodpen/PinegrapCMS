<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the commercial "joined a contact group" mail and the group's own
 * opt-in: a contact who unsubscribed from a subscription group gets no
 * commercial mail when put back into it, as a manual campaign to the group
 * already leaves them out; a missing opt_in row is subscribed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_auto_campaign_group_opt_out_rules()
{
	pg_assert_true(pg_auto_campaign_group_opted_out('1', '0'), 'opted out of a subscription group');
	pg_assert_false(pg_auto_campaign_group_opted_out('1', null), 'no opt_in row is subscribed');
	pg_assert_false(pg_auto_campaign_group_opted_out('1', '1'), 'opted in');
	pg_assert_false(pg_auto_campaign_group_opted_out('0', '0'), 'a group without e-mail subscription has no opt-out');
}

function test_auto_campaign_reads_the_group_opt_in_for_commercial_joined_mail()
{
	$source = pg_test_function_source('create_auto_email_campaigns');

	pg_assert_contains("\$action === 'contact_group_joined'", $source, 'the group is read for the joined action');
	pg_assert_contains('pg_auto_campaign_group_opted_out(', $source, 'its opt_in row decides');
	pg_assert_contains("\$email_campaign_profile['purpose'] == 'commercial' and \$group_opted_out", $source, 'only a commercial profile is skipped');
}
