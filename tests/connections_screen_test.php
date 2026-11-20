<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Connections screen's list (includes/fn/connections_screen.php): a
 * row's state read from stored facts, the order inside a group (needs
 * attention, connected, the rest), the search, the state filter and its
 * counts, the fold of a long group, and a row hidden from a user who may
 * not open its place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_screen_test_rows()
{
	return array(
		'a' => array('group' => 'payments', 'state' => 'not_connected', 'search' => array('PayTR', 'Payments')),
		'b' => array('group' => 'payments', 'state' => 'connected', 'search' => array('iyzico', 'Payments')),
		'c' => array('group' => 'email', 'state' => 'none', 'search' => array('VIES', 'Checks EU VAT numbers')),
		'd' => array('group' => 'payments', 'state' => 'attention', 'search' => array('Paraşüt', 'e-Fatura')),
		'e' => array('group' => 'payments', 'state' => 'off', 'search' => array('İYS', 'Marketing consent records')),
		'f' => array('group' => 'payments', 'state' => 'connected', 'search' => array('Stripe')),
	);
}

function test_connections_screen_order_puts_attention_then_connected_then_the_rest()
{
	$order = pg_connections_screen_order(connections_screen_test_rows(), array('email', 'payments', 'shipping'));

	pg_assert_same(array('email', 'payments'), array_keys($order), 'groups keep the given order, an empty group is left out');
	pg_assert_same(array('d', 'b', 'f', 'a', 'e'), $order['payments'], 'needs attention, connected (registry order), not connected, off');
	pg_assert_same(array('c'), $order['email'], 'a row with no state is listed');
}

function test_connections_screen_filter_and_counts()
{
	$rows = connections_screen_test_rows();

	pg_assert_same('not_connected', pg_connections_screen_filter_of('off'), 'off is counted as not connected');
	pg_assert_same('', pg_connections_screen_filter_of('none'), 'no state is under no filter');
	pg_assert_same(array('d'), array_keys(pg_connections_screen_filter($rows, 'attention')), 'the attention filter');
	pg_assert_same(array('a', 'e'), array_keys(pg_connections_screen_filter($rows, 'not_connected')), 'not connected holds the off row');
	pg_assert_same(6, count(pg_connections_screen_filter($rows, '')), 'All keeps every row');

	$counts = pg_connections_screen_counts($rows);

	pg_assert_same(array('all' => 6, 'attention' => 1, 'connected' => 2, 'not_connected' => 2, 'none' => 1), $counts, 'the counts');
	pg_assert_same($counts['all'], $counts['attention'] + $counts['connected'] + $counts['not_connected'] + $counts['none'], 'the counts add up');
}

function test_connections_screen_search()
{
	$rows = connections_screen_test_rows();

	pg_assert_same(array('d'), array_keys(pg_connections_screen_search($rows, 'parasut')), 'Turkish letters fold: parasut finds Paraşüt');
	pg_assert_same(array('e'), array_keys(pg_connections_screen_search($rows, 'iys')), 'a dotted capital İ is found by a plain i');
	pg_assert_same(array('e'), array_keys(pg_connections_screen_search($rows, 'İYS consent')), 'every word must match, in any text of the row');
	pg_assert_same(array('a', 'b'), array_keys(pg_connections_screen_search(array_slice($rows, 0, 2, true), 'PAYMENTS')), 'the group text is searched, any case');
	pg_assert_same(array(), pg_connections_screen_search($rows, 'zapier'), 'no row for a word nothing holds');
	pg_assert_same(6, count(pg_connections_screen_search($rows, '   ')), 'an empty query keeps every row');
}

function test_connections_screen_hides_a_row_whose_place_the_user_may_not_open()
{
	$categories = array('commerce' => true);
	$tools = array('smtp_settings.php' => true);

	pg_assert_true(pg_connections_screen_may_see('#settings/commerce/pgset-payments', $categories, $tools, true), 'a card of an open category');
	pg_assert_false(pg_connections_screen_may_see('#settings/workspace_assistants/pgset-ws-agents', $categories, $tools, true), 'a card of a category the user may not open is hidden');
	pg_assert_true(pg_connections_screen_may_see('smtp_settings.php', $categories, $tools, true), 'a tool the user sees');
	pg_assert_false(pg_connections_screen_may_see('cloudflare.php', $categories, $tools, true), 'a tool missing from the user\'s tools is hidden');
	pg_assert_true(pg_connections_screen_may_see('search_console.php', $categories, $tools, true), 'the other screen for a manager');
	pg_assert_false(pg_connections_screen_may_see('search_console.php', $categories, $tools, false), 'and not below one');
}

function test_connections_screen_state_from_facts()
{
	$facts = array(
		'accounts' => array(
			array('provider' => 'google', 'purposes' => array('account', 'backup'), 'status' => 'reauth'),
			array('provider' => 'microsoft', 'purposes' => array('mail'), 'status' => 'active'),
		),
		'apps'     => array('mail_postmark' => true, 'sms_netgsm' => false),
		'config'   => array('ECOMMERCE_PAYMENT_GATEWAY' => 'Stripe PaymentIntents', 'MAILCHIMP' => ''),
		'readers'  => array('pg_label_ready(geliver)' => true),
		'edoc'     => 'parasut',
		'notify'   => array('slack' => true),
	);

	$state = function ($reader) use ($facts) {
		return pg_connections_screen_state(array('state' => $reader), $facts);
	};

	pg_assert_same('attention', $state(array('kind' => 'account', 'provider' => 'google', 'purpose' => 'backup', 'attention' => true)), 'an account marked reauth needs attention');
	pg_assert_same('connected', $state(array('kind' => 'account', 'provider' => 'microsoft', 'purpose' => 'mail', 'attention' => true)), 'an active account is connected');
	pg_assert_same('not_connected', $state(array('kind' => 'account', 'provider' => 'google', 'purpose' => 'mail')), 'no account for the purpose');
	pg_assert_same('connected', $state(array('kind' => 'app', 'key' => 'mail_postmark')), 'an enabled application');
	pg_assert_same('off', $state(array('kind' => 'app', 'key' => 'sms_netgsm')), 'a switched-off application');
	pg_assert_same('not_connected', $state(array('kind' => 'app', 'key' => 'sms_twilio')), 'no application');
	pg_assert_same('connected', $state(array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Stripe PaymentIntents')), 'the chosen gateway');
	pg_assert_same('not_connected', $state(array('kind' => 'config', 'names' => array('ECOMMERCE_PAYMENT_GATEWAY'), 'equals' => 'Iyzipay')), 'another gateway');
	pg_assert_same('not_connected', $state(array('kind' => 'config', 'names' => array('MAILCHIMP'))), 'an empty constant');
	pg_assert_same('connected', $state(array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('geliver'))), 'a reader answering yes');
	pg_assert_same('not_connected', $state(array('kind' => 'reader', 'function' => 'pg_label_ready', 'args' => array('aras'))), 'a reader not read');
	pg_assert_same('connected', $state(array('kind' => 'edoc', 'key' => 'parasut')), 'the active e-document provider');
	pg_assert_same('connected', $state(array('kind' => 'notify', 'key' => 'slack')), 'a stored chat target');
	pg_assert_same('none', pg_connections_screen_state(array('state' => null, 'no_state' => 'x'), $facts), 'a row without a reader shows no state');

	pg_assert_same('attention', pg_connections_screen_provider_state(array(array('status' => 'active'), array('status' => 'reauth')), 'ready'), 'a provider with a stopped account');
	pg_assert_same('connected', pg_connections_screen_provider_state(array(array('status' => 'active')), 'ready'), 'a provider with an account');
	pg_assert_same('off', pg_connections_screen_provider_state(array(), 'off'), 'a provider whose application is off');
	pg_assert_same('not_connected', pg_connections_screen_provider_state(array(), 'not_set_up'), 'a provider with nothing');
}

function test_connections_screen_every_registry_row_reads_a_state()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');

	$ranks = pg_connections_screen_state_ranks();

	foreach (pg_connections_registry() as $id => $row) {
		$state = pg_connections_screen_state($row, array());
		pg_assert_true(isset($ranks[$state]), $id . ': reads a known state with no facts');
		pg_assert_same($row['state'] === null, $state === 'none', $id . ': no state only for a row without a reader');
	}
}

function test_connections_screen_fold()
{
	pg_assert_same(2, pg_connections_screen_fold_at(array('connected', 'not_connected', 'not_connected', 'none')), 'at least two rows show');
	pg_assert_same(3, pg_connections_screen_fold_at(array('attention', 'connected', 'connected', 'not_connected', 'off')), 'every attention and connected row shows');
	pg_assert_same(3, pg_connections_screen_fold_at(array('not_connected', 'not_connected', 'off')), 'one row is never folded alone');
	pg_assert_same(1, pg_connections_screen_fold_at(array('none')), 'a one-row group');
}

function test_connections_screen_a_services_accounts_and_its_last_error()
{
	$accounts = array(
		array('id' => 1, 'provider' => 'google', 'purposes' => array('account', 'backup'), 'status' => 'active', 'last_error' => ''),
		array('id' => 2, 'provider' => 'google', 'purposes' => array('backup'), 'status' => 'reauth', 'last_error' => 'invalid_grant'),
		array('id' => 3, 'provider' => 'microsoft', 'purposes' => array('backup'), 'status' => 'reauth', 'last_error' => 'expired'),
		array('id' => 4, 'provider' => 'google', 'purposes' => array('mail'), 'status' => 'active', 'last_error' => 'old'),
	);
	$backup = array('kind' => 'account', 'provider' => 'google', 'purpose' => 'backup');

	pg_assert_same(array(1, 2), array_column(pg_connections_screen_accounts_for($backup, $accounts), 'id'), 'the provider\'s accounts for the purpose');
	pg_assert_same(array(), pg_connections_screen_accounts_for(array('kind' => 'app', 'key' => 'mail_postmark'), $accounts), 'a service with no account reader has no account');
	pg_assert_same(array(), pg_connections_screen_accounts_for(null, $accounts), 'nor does a row without a reader');

	pg_assert_same('invalid_grant', pg_connections_screen_last_error(pg_connections_screen_accounts_for($backup, $accounts)), 'the stopped account\'s error');
	pg_assert_same('old', pg_connections_screen_last_error(array($accounts[0], $accounts[3])), 'an error kept on a working account is still the last one on file');
	pg_assert_same('', pg_connections_screen_last_error(array($accounts[0])), 'no error on file');

	pg_assert_true(pg_connections_screen_keeps_errors($backup), 'an account reader keeps the last error');
	pg_assert_false(pg_connections_screen_keeps_errors(array('kind' => 'config', 'names' => array('X'))), 'a config reader keeps none, so the drawer has no last error line');
	pg_assert_false(pg_connections_screen_keeps_errors(null), 'nor a row without a reader');
}

function test_connections_screen_an_address_breaks_after_its_at()
{
	pg_assert_same('very.long.name@<wbr>example.com', pg_connections_screen_address_html('very.long.name@example.com'), 'the line may break after the @');
	pg_assert_same('a&amp;b@<wbr>x.com &lt;i&gt;', pg_connections_screen_address_html('a&b@x.com <i>'), 'the text is escaped first');
	pg_assert_same('Backup account', pg_connections_screen_address_html('Backup account'), 'a name without an @ is only escaped');
	pg_assert_same('very.long.name@example.com', strip_tags(pg_connections_screen_address_html('very.long.name@example.com')), 'copy and a screen reader get the one address');
}

function test_connections_screen_a_row_has_one_action()
{
	pg_assert_same('connect', pg_connections_screen_action('not_connected'), 'nothing set up: Connect');

	foreach (array('attention', 'connected', 'off', 'none') as $state) {
		pg_assert_same('manage', pg_connections_screen_action($state), $state . ': Manage');
	}
}

function test_connections_screen_a_services_page()
{
	$rows = array(
		'stripe'          => array('kind' => 'service'),
		'provider_google' => array('kind' => 'provider'),
	);

	pg_assert_same('connections.php?service=stripe', pg_connections_screen_page_url('stripe'), 'the page\'s address');
	pg_assert_same('stripe', pg_connections_screen_page_id('stripe', $rows), 'a service the user sees has a page');
	pg_assert_same('', pg_connections_screen_page_id('provider_google', $rows), 'an account provider keeps its drawer');
	pg_assert_same('', pg_connections_screen_page_id('paypal', $rows), 'a service the user may not see has none');
	pg_assert_same('', pg_connections_screen_page_id(array('stripe'), $rows), 'a non-string parameter is refused');
	pg_assert_same('', pg_connections_screen_page_id('Stripe<', $rows), 'so is anything but a registry key');
}

// The seven mail routes are one row on the list, E-mail sending's: in the
// first route's place, the most urgent of their states, found by each
// route's name, counted once by the filter. Mailchimp keeps its row.
function test_connections_screen_folds_the_mail_routes()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');

	pg_assert_same(array('postmark', 'mailgun', 'brevo', 'resend', 'ses', 'gmail', 'microsoft_mail'), pg_connections_screen_mail_routes());

	$registry = pg_connections_registry();

	foreach (pg_connections_screen_mail_routes() as $id) {
		pg_assert_true(isset($registry[$id]) && ($registry[$id]['group'] === 'email'), $id . ' is a mail row of the registry');
		pg_assert_same(0, strpos($registry[$id]['place'], '#settings/mail_sending/'), $id . ' is set up in E-mail sending');
	}

	pg_assert_same('#settings/mail_sending/pgset-mailchimp', $registry['mailchimp']['place'], 'Mailchimp is set up in E-mail sending too');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');

	$rows = array(
		'stripe' => array('name' => 'Stripe', 'group' => 'payments', 'state' => 'connected', 'search' => array('Stripe')),
		'postmark' => array('name' => 'Postmark', 'group' => 'email', 'state' => 'not_connected', 'search' => array('Postmark')),
		'gmail' => array('name' => 'Gmail', 'group' => 'email', 'state' => 'connected', 'search' => array('Gmail')),
		'microsoft_mail' => array('name' => 'Microsoft 365 mail', 'group' => 'email', 'state' => 'attention', 'search' => array('Microsoft 365 mail')),
		'mailchimp' => array('name' => 'Mailchimp', 'group' => 'email', 'state' => 'not_connected', 'search' => array('Mailchimp')),
	);

	$folded = pg_connections_screen_fold_mail($rows, array('kind' => 'settings', 'name' => 'E-mail sending', 'group' => 'email', 'uses' => '', 'url' => 'settings_mail_sending.php', 'search' => array('E-mail sending')));

	pg_assert_same(array('stripe', 'mail_sending', 'mailchimp'), array_keys($folded), 'one row in the first route\'s place');
	pg_assert_same('attention', $folded['mail_sending']['state'], 'the most urgent state');
	pg_assert_same('Postmark, Gmail, Microsoft 365 mail', $folded['mail_sending']['uses'], 'the routes named under it');
	pg_assert_same(array('mail_sending'), array_keys(pg_connections_screen_search($folded, 'gmail')), 'a route\'s name finds the row');
	pg_assert_same(3, pg_connections_screen_counts($folded)['all'], 'the filter counts rows');
	pg_assert_same('connect', pg_connections_screen_action('not_connected'));

	unset($rows['postmark'], $rows['gmail'], $rows['microsoft_mail']);
	pg_assert_same($rows, pg_connections_screen_fold_mail($rows, array('name' => 'E-mail sending')), 'no route, no row');
}
