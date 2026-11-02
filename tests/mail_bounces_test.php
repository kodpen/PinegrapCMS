<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bounce record and the send gate (includes/fn/mail_bounces.php):
 * how an address is keyed, what a hard, soft, complaint or manual bounce does
 * to the record, when the address is blocked, what clearing it does, and how
 * the queue splits a message's recipients. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

// One row per address, whatever case or white space it was written with.
function test_mail_bounce_address_key()
{
	pg_assert_same('ann@example.com', pg_mail_bounce_address('  Ann@Example.COM '));
	pg_assert_same('', pg_mail_bounce_address(''));
	pg_assert_same('', pg_mail_bounce_address('not an address'));
	pg_assert_same('', pg_mail_bounce_address(array('a@example.com')));
	pg_assert_same('', pg_mail_bounce_address(str_repeat('a', 190) . '@example.com'), 'longer than the column');
}

function test_mail_bounce_unknown_kind_is_refused()
{
	pg_assert_same(null, pg_mail_bounce_next(null, 'later', 1000));
	pg_assert_same(null, pg_mail_bounce_next(null, '', 1000));
}

// A hard bounce, a complaint or a manual block suppresses at once.
function test_mail_bounce_hard_complaint_manual_suppress_at_once()
{
	foreach (array('hard', 'complaint', 'manual') as $kind) {
		$next = pg_mail_bounce_next(null, $kind, 1000);

		pg_assert_same($kind, $next['kind'], $kind);
		pg_assert_same(1, $next['suppressed'], $kind);
		pg_assert_same(1000, $next['first_at'], $kind);
		pg_assert_same(1000, $next['last_at'], $kind);
		pg_assert_true(pg_mail_bounce_blocks($next), $kind);
	}
}

// Soft bounces suppress on the third within 30 days.
function test_mail_bounce_soft_threshold()
{
	pg_assert_same(3, PG_MAIL_BOUNCE_SOFT_LIMIT);

	$row = pg_mail_bounce_next(null, 'soft', 1000);
	pg_assert_same(1, $row['soft_count']);
	pg_assert_same(0, $row['suppressed']);
	pg_assert_false(pg_mail_bounce_blocks($row));

	$row = pg_mail_bounce_next($row, 'soft', 2000);
	pg_assert_same(2, $row['soft_count']);
	pg_assert_same(1000, $row['first_at']);
	pg_assert_false(pg_mail_bounce_blocks($row));

	$row = pg_mail_bounce_next($row, 'soft', 3000);
	pg_assert_same(3, $row['soft_count']);
	pg_assert_same(1, $row['suppressed']);
	pg_assert_same('soft', $row['kind']);
	pg_assert_true(pg_mail_bounce_blocks($row));
}

// Soft bounces older than 30 days are forgotten by first_at.
function test_mail_bounce_soft_window_restarts()
{
	$row = pg_mail_bounce_next(null, 'soft', 1000);
	$row = pg_mail_bounce_next($row, 'soft', 2000);

	$row = pg_mail_bounce_next($row, 'soft', 1000 + PG_MAIL_BOUNCE_SOFT_WINDOW + 1);

	pg_assert_same(1, $row['soft_count']);
	pg_assert_same(1000 + PG_MAIL_BOUNCE_SOFT_WINDOW + 1, $row['first_at']);
	pg_assert_same(0, $row['suppressed']);
}

// A soft bounce after a hard one keeps the address suppressed as hard.
function test_mail_bounce_soft_never_lifts_a_hard_block()
{
	$row = pg_mail_bounce_next(null, 'hard', 1000);
	$row = pg_mail_bounce_next($row, 'soft', 2000);

	pg_assert_same('hard', $row['kind']);
	pg_assert_same(1, $row['suppressed']);
	pg_assert_same(2000, $row['last_at']);

	$row = pg_mail_bounce_next($row, 'complaint', 3000);
	pg_assert_same('complaint', $row['kind'], 'a complaint is the stronger record');
}

// Clearing lifts the block and forgets the soft count; a later soft bounce
// starts from one.
function test_mail_bounce_clear()
{
	$row = pg_mail_bounce_next(null, 'hard', 1000);

	$row = array_merge($row, pg_mail_bounce_cleared(5000));

	pg_assert_same(0, $row['suppressed']);
	pg_assert_same(0, $row['soft_count']);
	pg_assert_same(5000, $row['cleared_at']);
	pg_assert_false(pg_mail_bounce_blocks($row));

	$row = pg_mail_bounce_next($row, 'soft', 6000);
	pg_assert_same(1, $row['soft_count']);
	pg_assert_same(6000, $row['first_at']);
	pg_assert_same('soft', $row['kind']);
	pg_assert_false(pg_mail_bounce_blocks($row));
}

function test_mail_bounce_blocks_reads_the_stored_row()
{
	pg_assert_false(pg_mail_bounce_blocks(null));
	pg_assert_false(pg_mail_bounce_blocks(false));
	pg_assert_true(pg_mail_bounce_blocks(array('suppressed' => '1')));
	pg_assert_false(pg_mail_bounce_blocks(array('suppressed' => '0')));
}

// The queue keeps the recipients that may still be mailed; none left means
// the message is suppressed.
function test_mail_bounce_split_recipients()
{
	$blocked = array('dead@example.com' => true);

	pg_assert_same(array('ok@example.com'), pg_mail_bounce_split('ok@example.com', $blocked)['keep']);
	pg_assert_same(array(), pg_mail_bounce_split('Dead@Example.com', $blocked)['keep']);
	pg_assert_same(array('Dead@Example.com'), pg_mail_bounce_split('Dead@Example.com', $blocked)['blocked']);

	$split = pg_mail_bounce_split(array('ok@example.com', 'dead@example.com'), $blocked);
	pg_assert_same(array('ok@example.com'), $split['keep']);
	pg_assert_same(array('dead@example.com'), $split['blocked']);

	// What the gate cannot key (not an address) is left to email() to refuse.
	pg_assert_same(array('nobody'), pg_mail_bounce_split('nobody', $blocked)['keep']);
}

// The queue's addresses to look up: the keyable recipients of "to", once.
function test_mail_bounce_lookup_addresses()
{
	pg_assert_same(array('a@example.com'), pg_mail_bounce_lookup_addresses(array('A@example.com', 'a@example.com', 'x')));
	pg_assert_same(array(), pg_mail_bounce_lookup_addresses(''));
}

// A suppressed recipient is counted apart from the given-up ones.
function test_email_campaign_summary_counts_suppressed()
{
	$summary = pg_email_campaign_send_summary(10, 8, 2, 0, 2);

	pg_assert_false($summary['all_sent']);
	pg_assert_true($summary['complete']);
	pg_assert_contains('2 address(es) were skipped because they bounced or complained.', $summary['message']);
	pg_assert_false(strpos($summary['message'], 'will not be tried again') !== false, 'no given-up sentence when all failures are suppressed');

	$summary = pg_email_campaign_send_summary(10, 7, 3, 0, 1);
	pg_assert_contains('2 e-mail(s) could not be delivered', $summary['message']);
	pg_assert_contains('1 address(es) were skipped', $summary['message']);
}

// The stored marker of a suppressed campaign recipient: given up with no
// attempt made.
function test_email_campaign_suppressed_recipient_row()
{
	$set = pg_email_campaign_suppressed_set();

	pg_assert_same(1, $set['complete']);
	pg_assert_same(1, $set['failed']);
	pg_assert_same(0, $set['attempts']);
}
