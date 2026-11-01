<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Search and audits screen's pure helpers
 * (includes/fn/search_audits.php): an engine's schedule state, the progress
 * share, the dispatch list one switch writes, and the piece of a translated
 * log line the screen looks the line up by.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_audits.php');

// A held lock is a run going, whether or not the job is switched on: a Run
// now or a crontab run takes the same lock.
function test_search_audits_status_running()
{
	pg_assert_same('running', pg_search_audits_status(1000, 86400, true, 1500, 900)['state']);
	pg_assert_same('running', pg_search_audits_status(1000, 86400, false, 1500, 900)['state'], 'off but running');
	pg_assert_same('due', pg_search_audits_status(1000, 60, true, 1000, 900)['state'], 'expired lock');
}

// Off: no next run is shown.
function test_search_audits_status_off()
{
	$status = pg_search_audits_status(1000, 86400, false, 0, 900);

	pg_assert_same('off', $status['state']);
	pg_assert_same(0, $status['next']);
}

// Due once the last tick plus the interval has come; never ran is due now.
// An unfinished pass moved its tick back (pg_cron_due_again()), so it reads
// due a minute later.
function test_search_audits_status_due_and_scheduled()
{
	$scheduled = pg_search_audits_status(1000, 86400, true, 0, 900);
	pg_assert_same('scheduled', $scheduled['state']);
	pg_assert_same(87300, $scheduled['next']);

	pg_assert_same('due', pg_search_audits_status(100000, 86400, true, 0, 900)['state'], 'past');
	pg_assert_same('due', pg_search_audits_status(1000, 86400, true, 0, 0)['state'], 'never ran');

	// due again in a minute: last_run_at = now - interval + 60
	$again = pg_search_audits_status(100000, 86400, true, 0, 100000 - 86400 + 60);
	pg_assert_same('scheduled', $again['state']);
	pg_assert_same(100060, $again['next']);
}

function test_search_audits_percent()
{
	pg_assert_same(null, pg_search_audits_percent(0, 0), 'nothing to count');
	pg_assert_same(0, pg_search_audits_percent(0, 4));
	pg_assert_same(50, pg_search_audits_percent(2, 4));
	pg_assert_same(33, pg_search_audits_percent(1, 3), 'rounded down');
	pg_assert_same(100, pg_search_audits_percent(9, 4), 'capped');
}

// The switch keeps the other jobs, writes catalogue order, and lets no name
// through that is not a dispatchable job.
function test_search_audits_switch_list()
{
	$jobs = array('email_campaign_job', 'update_search_index', 'seo_score_job', 'seo_a11y_job');

	pg_assert_same('email_campaign_job,update_search_index,seo_a11y_job', pg_search_audits_switch_list('seo_a11y_job, email_campaign_job', 'update_search_index', true, $jobs), 'on');
	pg_assert_same('email_campaign_job', pg_search_audits_switch_list('email_campaign_job,seo_a11y_job', 'seo_a11y_job', false, $jobs), 'off');
	pg_assert_same('seo_score_job', pg_search_audits_switch_list('', 'seo_score_job', true, $jobs), 'empty list');
	pg_assert_same('seo_score_job', pg_search_audits_switch_list('../evil,seo_score_job', 'nonsense', true, $jobs), 'unknown names dropped');
	pg_assert_same('update_search_index', pg_search_audits_switch_list('update_search_index', 'update_search_index', true, $jobs), 'already on');
}

// The longest literal piece, whichever end the translation put the values.
function test_search_audits_log_needle()
{
	$m = "\x01";

	pg_assert_same('The search index could not read', pg_search_audits_log_needle('The search index could not read ' . $m . ' item(s): ' . $m, $m));
	pg_assert_same('sayfa işlenemedi, erişilebilirlik denetimi', pg_search_audits_log_needle($m . ' sayfa işlenemedi, erişilebilirlik denetimi', $m), 'value first');
	pg_assert_same('', pg_search_audits_log_needle($m . ' x ' . $m, $m), 'too short to tell apart');
}
