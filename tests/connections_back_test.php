<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the return from a service's page of Connections to the Site
 * Settings card that led there: the card a lead names (from=), checked
 * against the categories the reader may open; the lead's address; the
 * address a write on the page goes back to; when the page returns by
 * itself; the two older parameters (backup_return, mail_return) read as
 * the one return; and every lead using it in the same tab.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/backup_cloud.php');

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function connections_back_test_categories()
{
	return array(
		'seo'         => array('label' => 'SEO', 'sections' => array('pgset-seo' => 'Search Engine Optimization', 'pgset-sitemap' => 'Sitemap')),
		'maintenance' => array('label' => 'Updates and maintenance', 'sections' => array('pgset-backup' => 'Backups')),
		'email'       => array('label' => 'E-mail', 'sections' => array('pgset-mail-system' => 'How e-mail leaves', 'pgset-ws-mail' => 'Mailboxes')),
	);
}

function connections_back_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_back_reads_a_known_card()
{
	$back = pg_connections_back('seo/pgset-seo', connections_back_test_categories());

	pg_assert_same('seo', $back['category']);
	pg_assert_same('pgset-seo', $back['card']);
	pg_assert_same('', $back['drawer']);
	pg_assert_same('Search Engine Optimization', $back['label'], 'the card\'s own title, for "Back to {card}"');
	pg_assert_same('seo/pgset-seo', $back['value']);

	$drawer = pg_connections_back('email/pgset-ws-mail/box-12', connections_back_test_categories());
	pg_assert_same('box-12', $drawer['drawer'], 'a drawer\'s key travels with its card');
	pg_assert_same('email/pgset-ws-mail/box-12', $drawer['value']);
}

function test_connections_back_drops_what_it_does_not_know()
{
	$categories = connections_back_test_categories();

	foreach (array(
		'', 'seo', 'seo/', 'seo/pgset-nothing', 'privacy/pgset-analytics', 'SEO/pgset-seo',
		'seo/pgset-seo/', 'seo/pgset-seo/a/b', '../seo/pgset-seo', 'seo/pgset-seo#x',
		'https://example.com/', '//example.com', 'settings_seo.php#pgset-seo', "seo/pgset-seo\n",
		'seo/pgset-seo/<b>', 'seo/' . str_repeat('a', 80),
	) as $asked) {
		pg_assert_same(null, pg_connections_back($asked, $categories), 'refused: ' . var_export($asked, true));
	}

	pg_assert_same(null, pg_connections_back(array('seo/pgset-seo'), $categories), 'a list is no card');
	pg_assert_same(null, pg_connections_back(null, $categories));
	pg_assert_same(null, pg_connections_back('seo/pgset-seo', array()), 'a category the reader may not open is no way back');
}

function test_connections_back_lead_url()
{
	pg_assert_same('connections.php?service=indexnow&from=seo%2Fpgset-seo', pg_connections_lead_url('indexnow', 'seo', 'pgset-seo'));
	pg_assert_same('connections.php?service=google&from=email%2Fpgset-ws-mail#connections-mail', pg_connections_lead_url('google', 'email', 'pgset-ws-mail', 'connections-mail'), 'the page\'s own anchor stays last');
	pg_assert_same('connections.php?service=mcp_deepwiki', pg_connections_lead_url('mcp_deepwiki', '', ''), 'no card, no return');

	// A lead is a path connect.php keeps unchanged, so the same address can
	// be the sign-in's return.
	$lead = pg_connections_lead_url('google_drive', 'maintenance', 'pgset-backup');
	pg_assert_same($lead, pg_connect_return_path($lead));
}

function test_connections_back_page_url()
{
	$back = pg_connections_back('maintenance/pgset-backup', connections_back_test_categories());

	pg_assert_same('connections.php?service=google_drive&from=maintenance%2Fpgset-backup', pg_connections_back_page_url('google_drive', $back));
	pg_assert_same('connections.php?service=google_drive', pg_connections_back_page_url('google_drive', null), 'reached from the list: as today');
}

function test_connections_back_after_write()
{
	$url = 'https://example.com/pinegrap/connections.php';

	pg_assert_same($url . '?service=indexnow&from=seo%2Fpgset-seo&saved=1', pg_connections_back_after_write($url . '?service=indexnow', 'seo/pgset-seo', 'indexnow_save', false), 'a Save may return');
	pg_assert_same($url . '?service=indexnow&from=seo%2Fpgset-seo', pg_connections_back_after_write($url . '?service=indexnow', 'seo/pgset-seo', 'indexnow_save', true), 'a refused Save stays, with its way back');
	pg_assert_same($url . '?service=google&from=email%2Fpgset-ws-mail&saved=1#connections-signin', pg_connections_back_after_write($url . '?service=google#connections-signin', 'email/pgset-ws-mail', 'signin_google_save', false), 'the anchor stays last');

	foreach (array('disconnect', 'remove_app', 'ai_remove', 'notify_delete', 'mcp_disconnect', 'github_link_remove') as $action) {
		pg_assert_same($url . '?service=x&from=seo%2Fpgset-seo', pg_connections_back_after_write($url . '?service=x', 'seo/pgset-seo', $action, false), $action . ' stays on the page');
	}

	pg_assert_same($url . '?service=indexnow', pg_connections_back_after_write($url . '?service=indexnow', '', 'indexnow_save', false), 'no lead, as today');
	pg_assert_same($url . '?service=indexnow', pg_connections_back_after_write($url . '?service=indexnow', 'https://example.org/', 'indexnow_save', false), 'nothing that is no card');
	pg_assert_same($url, pg_connections_back_after_write($url, 'seo/pgset-seo', 'save_app', false), 'the list keeps no return');
	pg_assert_same($url . '#connections-google', pg_connections_back_after_write($url . '#connections-google', 'seo/pgset-seo', 'save_app', false));
}

function test_connections_back_due()
{
	$back = pg_connections_back('seo/pgset-seo', connections_back_test_categories());

	pg_assert_same(true, pg_connections_back_due($back, 'saved', 'connected'), 'a Save that leaves the service ready');
	pg_assert_same(false, pg_connections_back_due($back, 'saved', 'not_connected'), 'a Save that leaves something missing stays');
	pg_assert_same(false, pg_connections_back_due($back, 'saved', 'attention'));
	pg_assert_same(false, pg_connections_back_due($back, 'saved', 'off'));
	pg_assert_same(true, pg_connections_back_due($back, 'connected', 'not_connected'), 'a Connect that went through');
	pg_assert_same(false, pg_connections_back_due($back, '', 'connected'), 'only a visit');
	pg_assert_same(false, pg_connections_back_due(null, 'saved', 'connected'), 'reached from the list');
}

function test_connections_back_folds_the_old_parameters()
{
	pg_assert_same('maintenance/pgset-backup', pg_connections_back_legacy(array('backup_return' => '1'), 'google_drive'));
	pg_assert_same('maintenance/pgset-backup', pg_connections_back_legacy(array('backup_return' => '1'), 'dropbox'));
	pg_assert_same('', pg_connections_back_legacy(array('backup_return' => '1'), 'indexnow'), 'only a drive\'s page');
	pg_assert_same('', pg_connections_back_legacy(array('backup_return' => ''), 'onedrive'));
	pg_assert_same('email/pgset-ws-mail', pg_connections_back_legacy(array('mail_return' => 'settings_mailboxes.php#pgset-ws-mail'), 'google'), 'a category that became a tab names its category');
	pg_assert_same('email/pgset-mail-system', pg_connections_back_legacy(array('mail_return' => 'settings_email.php#pgset-mail-system'), 'google'));
	pg_assert_same('', pg_connections_back_legacy(array('mail_return' => 'settings_mailboxes.php'), 'google'), 'no card');
	pg_assert_same('', pg_connections_back_legacy(array('mail_return' => 'https://example.com/settings_mailboxes.php#pgset-ws-mail'), 'google'));
	pg_assert_same('', pg_connections_back_legacy(array('mail_return' => array('x')), 'google'));
	pg_assert_same('seo/pgset-seo', pg_connections_back_legacy(array('from' => 'seo/pgset-seo', 'backup_return' => '1'), 'google_drive'), 'from wins');
	pg_assert_same('', pg_connections_back_legacy(array(), 'google'));
}

function test_connections_back_destination()
{
	$categories = connections_back_test_categories();

	pg_assert_same('settings_maintenance.php?backup_connect=gdrive#pgset-backup', pg_connections_back_destination(pg_connections_back('maintenance/pgset-backup', $categories), 'google_drive'), 'Backups shows the destination chosen with the new account');
	pg_assert_same('', pg_connections_back_destination(pg_connections_back('seo/pgset-seo', $categories), 'indexnow'), 'any other card: pg_settings_link()');
	pg_assert_same('', pg_connections_back_destination(null, 'google_drive'));
}

function test_connections_back_leads_use_the_helper()
{
	// Every lead from a card names its card; none lands on the list or opens
	// a new tab.
	$leads = array(
		'includes/settings/seo.php'               => "pg_connections_lead_url('indexnow', 'seo', 'pgset-seo')",
		'includes/settings/privacy.php'           => "pg_connections_lead_url('google_analytics', 'privacy', 'pgset-analytics')",
		'includes/settings/languages.php'         => "pg_connections_lead_url('google_translate', 'languages', 'pgset-translation-engines')",
		'includes/settings/mail_sending.php'      => "pg_connections_lead_url('mailchimp', 'email', 'pgset-mail-campaign')",
		'includes/fn/backup_cloud.php'            => "pg_connections_lead_url(pg_backup_cloud_types()[\$type]['service'], 'maintenance', 'pgset-backup')",
		'includes/workspace/mail_settings.php'    => "pg_connections_lead_url((\$provider === 'google') ? 'google' : 'microsoft', 'email', 'pgset-ws-mail', 'connections-mail')",
		'includes/workspace/mcp_catalogue.php'    => "pg_connections_lead_url('mcp_' . \$key, 'workspace_assistants', 'pgset-ws-mcp')",
		'includes/workspace/claude_setup.php'     => "pg_connections_lead_url('claude', 'workspace_assistants', 'pgset-ws-claude')",
		'includes/fn/sms.php'                     => "pg_connections_lead_url((string) \$provider, 'contact', 'pgset-sms')",
		'includes/fn/whatsapp.php'                => "pg_connections_lead_url('whatsapp', 'contact', 'pgset-whatsapp')",
		'includes/iys/settings.php'               => "pg_connections_lead_url('iys', 'contact', 'pgset-iys')",
		'includes/fn/google_reviews.php'          => "pg_connections_lead_url('google_reviews', 'api', 'pgset-google-reviews')",
		'includes/fn/merchant_center.php'         => "pg_connections_lead_url('merchant_center', 'commerce', 'pgset-feeds')",
		'includes/fn/notify_channels_page.php'    => "pg_connections_lead_url(\$kind, 'maintenance', 'pgset-chat-notify')",
		'includes/fn/mail_settings.php'           => "'email', 'pgset-mail-system', 'connections-details')",
		'includes/fn/payments.php'                => "pg_connections_lead_url((string) \$id, 'commerce', \$card)",
		'includes/fn/carrier_labels.php'          => "pg_payment_gateway_connections_url(\$key, 'pgset-shipping')",
		'includes/fn/carrier_api.php'             => "pg_payment_gateway_connections_url(\$carrier, 'pgset-shipping')",
		'includes/fn/address_autocomplete.php'    => "pg_connections_lead_url((string) \$provider, 'commerce', 'pgset-address-autocomplete')",
		'includes/fn/ecommerce.php'               => "pg_connections_lead_url((string) \$code, 'accounting', 'pgset-invoice')",
		'includes/fn/captcha.php'                 => "pg_connections_lead_url(\$key, 'security', 'pgset-captcha')",
		'includes/settings/security.php'          => "pg_connections_lead_url('signin_apple', 'security', 'pgset-signin')",
		'includes/fn/ai_connections.php'          => "pg_connections_lead_url(\$service, (\$card !== '') ? pg_ai_connection_category() : '', \$card)",
		'includes/workspace/agents_settings.php'  => "\$own, 'pgset-ws-agents')",
		'includes/workspace/voice.php'            => "'pgset-ws-voice')",
		'includes/workspace/github_links.php'     => "pg_connections_lead_url('github', 'workspace_channels', 'pgset-ws-github', 'connections-repositories')",
	);

	foreach ($leads as $file => $call) {
		pg_assert_contains($call, connections_back_test_source($file), $file);
	}

	$mcp = connections_back_test_source('includes/workspace/mcp_catalogue.php');
	pg_assert_false(strpos(substr($mcp, strpos($mcp, 'function ws_mcp_connections_url'), 2500), 'target="_blank"') !== false, 'MCP servers: the same tab');

	pg_assert_false(strpos(connections_back_test_source('includes/fn/mail_settings.php'), 'href="connections.php"') !== false, 'E-mail sending: the provider\'s page, not the list');
	pg_assert_false(strpos(connections_back_test_source('includes/settings/maintenance.php'), "'connections.php')) . '\">' . h(lang('Connected accounts'))") !== false, 'Backups: no lead to the list');
	pg_assert_false(strpos(connections_back_test_source('includes/fn/backup_cloud.php'), 'backup_return') !== false, 'the one return');
	pg_assert_false(strpos(connections_back_test_source('includes/workspace/mail_settings.php'), 'mail_return=') !== false, 'the one return');
}

function test_connections_back_page_wiring()
{
	$page = connections_back_test_source('connections.php');

	pg_assert_contains("pg_connections_back(pg_connections_back_legacy(\$_GET, \$page_id), \$open_category_list)", $page, 'the page reads its return under the reader\'s gates');
	pg_assert_contains("'back'", $page, '"Back to {card}" beside the breadcrumb');
	pg_assert_contains("lang(array('string' => 'Back to {var:1}'", $page);
	pg_assert_contains('pg_connections_back_after_write(', $page, 'a write keeps the return');
	pg_assert_contains('connections_from', $page, 'every form of the page carries it');

	$shell = connections_back_test_source('includes/fn/output.php');
	pg_assert_contains("\$properties['back']", $shell, 'the shell draws the way back');
}
