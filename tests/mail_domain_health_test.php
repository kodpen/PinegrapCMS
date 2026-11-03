<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sending-domain health card (includes/fn/mail_domain_health.php):
 * how SPF, DKIM and DMARC answers are judged on record samples, what text is
 * offered to publish, that a failed lookup is never red, and the bounce and
 * complaint rates against their limits. No DNS, no database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_domain_health.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ops_alerts.php');

const MAIL_HEALTH_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAx1y2';

// dns_get_record() gives a long record's strings apart in entries.
function test_mail_dns_text_joins_entries()
{
	pg_assert_same('v=DKIM1; p=abcdef', pg_mail_dns_text(array('txt' => 'v=DKIM1; p=abcdef', 'entries' => array('v=DKIM1; p=abc', 'def'))));
	pg_assert_same('v=spf1 -all', pg_mail_dns_text(array('txt' => ' v=spf1 -all ')));
	pg_assert_same('', pg_mail_dns_text(null));
}

function test_mail_spf_expected_by_transport()
{
	pg_assert_same(array('includes' => array('spf.mtasv.net'), 'host' => ''), pg_mail_spf_expected('postmark', 'smtp.example.com'));
	pg_assert_same(array('includes' => array(), 'host' => 'smtp.example.com'), pg_mail_spf_expected('smtp', ' SMTP.Example.com '));
	pg_assert_same(array('includes' => array(), 'host' => ''), pg_mail_spf_expected('smtp', 'localhost'));
	pg_assert_same(array('includes' => array(), 'host' => ''), pg_mail_spf_expected('smtp', '192.0.2.1'));
}

// A failed lookup says "could not check", never red.
function test_mail_dns_lookup_failure_is_unknown()
{
	pg_assert_same('unknown', pg_mail_spf_check(null, pg_mail_spf_expected('smtp', ''))['state']);
	pg_assert_same('unknown', pg_mail_dkim_check(null, 'p=' . MAIL_HEALTH_KEY, 'smtp')['state']);
	pg_assert_same('unknown', pg_mail_dmarc_check(null, 'example.com', 'a@example.com')['state']);
}

function test_mail_spf_missing_offers_a_record()
{
	$line = pg_mail_spf_check(array('google-site-verification=abc'), pg_mail_spf_expected('mailgun', ''));

	pg_assert_same('error', $line['state']);
	pg_assert_same('v=spf1 include:mailgun.org ~all', $line['copy']);

	$line = pg_mail_spf_check(array(), pg_mail_spf_expected('smtp', 'mail.example.com'));
	pg_assert_same('v=spf1 a:mail.example.com ~all', $line['copy']);

	pg_assert_same('v=spf1 mx ~all', pg_mail_spf_check(array(), pg_mail_spf_expected('smtp', ''))['copy']);
}

function test_mail_spf_covered_by_include_or_host()
{
	pg_assert_same('ok', pg_mail_spf_check(array('v=spf1 include:_spf.google.com include:spf.mtasv.net ~all'), pg_mail_spf_expected('postmark', ''))['state']);
	pg_assert_same('ok', pg_mail_spf_check(array('v=spf1 include:eu.mailgun.org -all'), pg_mail_spf_expected('mailgun', ''))['state'], 'a regional include');
	pg_assert_same('ok', pg_mail_spf_check(array('v=spf1 a:mail.example.com -all'), pg_mail_spf_expected('smtp', 'mail.example.com'))['state']);
	pg_assert_same('ok', pg_mail_spf_check(array('v=spf1 include:example.net -all'), pg_mail_spf_expected('smtp', 'smtp.example.net'))['state'], 'a domain above the host');
	pg_assert_same('present', pg_mail_spf_check(array('v=spf1 mx -all'), pg_mail_spf_expected('smtp', ''))['reason']);
}

function test_mail_spf_not_named_offers_the_include()
{
	$line = pg_mail_spf_check(array('v=spf1 include:_spf.google.com ~all'), pg_mail_spf_expected('brevo', ''));

	pg_assert_same('warning', $line['state']);
	pg_assert_same('not_named', $line['reason']);
	pg_assert_same('v=spf1 include:_spf.google.com include:spf.brevo.com ~all', $line['copy']);
	pg_assert_same('v=spf1 mx a:mail.example.com redirect=_spf.example.net', pg_mail_spf_add('v=spf1 mx redirect=_spf.example.net', 'a:mail.example.com'));
	pg_assert_same('v=spf1 mx include:spf.mtasv.net', pg_mail_spf_add('v=spf1 mx', 'include:spf.mtasv.net'));
}

function test_mail_spf_several_or_open_records()
{
	pg_assert_same('several', pg_mail_spf_check(array('v=spf1 mx -all', 'v=spf1 include:mailgun.org -all'), pg_mail_spf_expected('mailgun', ''))['reason']);
	pg_assert_same('error', pg_mail_spf_check(array('v=spf1 mx -all', 'v=spf1 -all'), pg_mail_spf_expected('smtp', ''))['state']);
	pg_assert_same('all_pass', pg_mail_spf_check(array('v=spf1 +all'), pg_mail_spf_expected('smtp', ''))['reason']);
	pg_assert_same('warning', pg_mail_spf_check(array('v=spf1 include:mailgun.org all'), pg_mail_spf_expected('mailgun', ''))['state']);
	pg_assert_same('error', pg_mail_spf_check(array('v=spf10 mx'), pg_mail_spf_expected('smtp', ''))['state'], 'not an SPF record');
}

function test_mail_dkim_key_from_zone_line_and_record()
{
	$zone = 'mail._domainkey.example.com IN TXT "v=DKIM1; k=rsa; p=' . MAIL_HEALTH_KEY . '"';

	pg_assert_same(MAIL_HEALTH_KEY, pg_mail_dkim_key($zone));
	pg_assert_same(MAIL_HEALTH_KEY, pg_mail_dkim_key('v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOC AQ8AMIIBCgKCAQEAx1y2'));
	pg_assert_same('', pg_mail_dkim_key('v=DKIM1; k=rsa'));
}

function test_mail_dkim_check_states()
{
	$zone = 'mail._domainkey.example.com IN TXT "v=DKIM1; k=rsa; p=' . MAIL_HEALTH_KEY . '"';

	pg_assert_same('match', pg_mail_dkim_check(array('v=DKIM1; k=rsa; p=' . MAIL_HEALTH_KEY), $zone, 'smtp')['reason']);
	pg_assert_same('ok', pg_mail_dkim_check(array('v=DKIM1; p=OTHER', 'v=DKIM1; p=' . MAIL_HEALTH_KEY), $zone, 'smtp')['state']);

	$line = pg_mail_dkim_check(array('v=DKIM1; k=rsa; p=OLDKEY'), $zone, 'smtp');
	pg_assert_same('error', $line['state']);
	pg_assert_same('other_key', $line['reason']);
	pg_assert_same($zone, $line['copy']);

	pg_assert_same('none', pg_mail_dkim_check(array(), $zone, 'smtp')['reason']);
	pg_assert_same('error', pg_mail_dkim_check(array(), $zone, 'smtp')['state']);
	pg_assert_same('warning', pg_mail_dkim_check(array(), '', 'smtp')['state'], 'no key made');
	pg_assert_same('service', pg_mail_dkim_check(array(), $zone, 'postmark')['reason'], 'the service signs');
}

function test_mail_dmarc_check_states()
{
	$line = pg_mail_dmarc_check(array('v=spf1 -all'), 'example.com', 'postmaster@example.com');
	pg_assert_same('error', $line['state']);
	pg_assert_same('_dmarc.example.com IN TXT "v=DMARC1; p=none; rua=mailto:postmaster@example.com"', $line['copy']);
	pg_assert_same('_dmarc.example.com IN TXT "v=DMARC1; p=none"', pg_mail_dmarc_check(array(), 'example.com', '')['copy']);

	$line = pg_mail_dmarc_check(array('v=DMARC1; p=none; rua=mailto:d@example.com'), 'example.com', '');
	pg_assert_same('warning', $line['state']);
	pg_assert_same('none', $line['policy']);

	$line = pg_mail_dmarc_check(array('v=DMARC1;p=Reject;pct=100'), 'example.com', '');
	pg_assert_same('ok', $line['state']);
	pg_assert_same('reject', $line['policy']);

	pg_assert_same('invalid', pg_mail_dmarc_check(array('v=DMARC1; rua=mailto:d@example.com'), 'example.com', '')['reason']);
	pg_assert_same('several', pg_mail_dmarc_check(array('v=DMARC1; p=none', 'v=DMARC1; p=reject'), 'example.com', '')['reason']);
}

function test_mail_reputation_rates_limits()
{
	$rates = pg_mail_reputation_rates(1000, 50, 3);
	pg_assert_same('ok', $rates['state'], 'exactly at the limits is not over');
	pg_assert_same(array(), $rates['over']);

	$rates = pg_mail_reputation_rates(1000, 51, 4);
	pg_assert_same('error', $rates['state']);
	pg_assert_same(array('bounces', 'complaints'), $rates['over']);

	pg_assert_same(array('complaints'), pg_mail_reputation_rates(1000, 0, 4)['over']);
}

// Too few sends: no judgement and so no alert.
function test_mail_reputation_rates_too_few_sends()
{
	$rates = pg_mail_reputation_rates(10, 5, 1);

	pg_assert_same(false, $rates['judged']);
	pg_assert_same('unknown', $rates['state']);
	pg_assert_same(array(), $rates['over']);
	pg_assert_same(0.0, pg_mail_reputation_rates(0, 0, 0)['bounce_rate']);
}

function test_mail_reputation_percent()
{
	pg_assert_same('5 %', pg_mail_reputation_percent(0.05));
	pg_assert_same('0.3 %', pg_mail_reputation_percent(0.003));
	pg_assert_same('2.35 %', pg_mail_reputation_percent(0.0235));
	pg_assert_same('0 %', pg_mail_reputation_percent(0));
}

function test_mail_sending_domain()
{
	pg_assert_same('mail.example.org', pg_mail_sending_domain(' Mail.Example.org ', 'info@example.com'));
	pg_assert_same('shop.example.net', pg_mail_sending_domain('example.com', 'info@shop.example.net'), 'the config sample');
	pg_assert_same('shop.example.net', pg_mail_sending_domain('', 'info@Shop.Example.net'));
	pg_assert_same('', pg_mail_sending_domain('', ''));
}

// The alert code is in the catalogue and its check runs with the others.
function test_mail_reputation_alert_is_registered()
{
	$codes = pg_ops_alert_codes();

	pg_assert_true(isset($codes['mail_reputation']));
	pg_assert_same('pg_ops_alerts_check_mail_reputation', pg_ops_alert_sources()['mail_reputation']);
	pg_assert_same(array('Bounces 6 % (limit 5 %)'), pg_mail_reputation_alert_parts(pg_mail_reputation_rates(1000, 60, 0)));
}
