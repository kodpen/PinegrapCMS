<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Connections list opening a service's card straight away
 * when the service's own page would only show its state.
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

function test_connections_list_page_here_names_pages_with_their_own_settings()
{
	foreach (array('indexnow', 'google_analytics', 'google_translate', 'whatsapp', 'signin_apple', 'turnstile', 'slack', 'claude', 'figma', 'google_reviews', 'stripe', 'parasut', 'xero') as $id) {
		pg_assert_true(pg_connections_screen_page_here($id, array()), $id . ': its settings are on its page');
	}

	pg_assert_true(pg_connections_screen_page_here('openai', array('openai')), 'a page named by the caller (an AI provider, an ad service)');

	foreach (array('vies', 'dbip', 'signin_google', 'google_calendar', 'n11', 'cloudflare', 'gmail') as $id) {
		pg_assert_false(pg_connections_screen_page_here($id, array()), $id . ': its settings stay on a card or a screen');
	}
}

function test_connections_list_opens_card_only_for_a_status_only_page()
{
	$card = 'settings_security.php#pgset-signin';

	pg_assert_true(pg_connections_screen_opens_card('signin_google', array('status', 'uses'), false, $card), 'status and uses only: the card');
	pg_assert_true(pg_connections_screen_opens_card('n11', array('status', 'uses'), false, 'marketplace_settings.php'), 'a screen of its own opens too');

	pg_assert_false(pg_connections_screen_opens_card('indexnow', array('status', 'details', 'uses'), true, $card), 'a page with its own settings stays');
	pg_assert_false(pg_connections_screen_opens_card('indexnow', array('status', 'uses'), true, $card), 'a page of its own settings the viewer may not change stays');
	pg_assert_false(pg_connections_screen_opens_card('google_drive', array('status', 'backups', 'uses'), false, $card), 'a drive that connects its account here stays');
	pg_assert_false(pg_connections_screen_opens_card('google_calendar', array('status', 'app', 'uses'), false, $card), 'a page asking for the missing application stays');
	pg_assert_false(pg_connections_screen_opens_card('google', array('status', 'uses'), false, $card), 'a provider\'s page stays');
	pg_assert_false(pg_connections_screen_opens_card('github', array('status', 'uses'), false, $card), 'GitHub\'s page stays');
	pg_assert_false(pg_connections_screen_opens_card('onenote', array('status', 'uses'), false, 'connections.php'), 'a place on the Connections list itself stays on the page');
	pg_assert_false(pg_connections_screen_opens_card('vies', array('status', 'uses'), false, ''), 'no place: the page');
	pg_assert_false(pg_connections_screen_opens_card('vies', array('status', 'uses'), false, 'https://example.com/x'), 'never an outside address');
	pg_assert_false(pg_connections_screen_opens_card('vies', array('status', 'uses'), false, '//example.com/x'), 'never a protocol-relative address');
}
