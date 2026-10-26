<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how an application's tag (<@app:N>) is drawn
 * (includes/workspace/app_handles.php): the handle made from its own name,
 * the shape and the words it never takes, and what Claude's, Pinegrap AI's,
 * a deleted and an older application read as. Which application is which is
 * read from the database: a sandbox check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_handles.php');

function test_app_handle_is_lower_case_from_the_name()
{
	pg_assert_same('chatgpt', ws_app_handle('ChatGPT', 7));
	pg_assert_same('order-bot', ws_app_handle('  Order Bot  ', 7));
	pg_assert_same('stok-takip', ws_app_handle('Stok / Takip', 7), 'a run of other characters is one dash');
	pg_assert_same('n8n_flows', ws_app_handle('n8n_flows', 7));
}

function test_app_handle_folds_turkish_letters()
{
	pg_assert_same('siparis-isleyici', ws_app_handle('Sipariş İşleyici', 7));
	pg_assert_same('gunluk-ozet-cagri', ws_app_handle('Günlük Özet Çağrı', 7));
}

function test_app_handle_starts_with_a_letter()
{
	pg_assert_same('crm', ws_app_handle('123 CRM', 7));
	pg_assert_same('sync', ws_app_handle('-_sync-', 7), 'no dash or underscore at either end');
}

function test_app_handle_has_the_shape_of_an_mcp_alias()
{
	$long = ws_app_handle(str_repeat('abcdefghij', 5), 7);

	pg_assert_same(32, strlen($long));
	pg_assert_same('', ws_mcp_alias_shape_problem($long));

	pg_assert_same('ab-c', ws_app_handle('ab-' . str_repeat('-', 30) . 'c', 7));
	pg_assert_same('abcdefghijklmnopqrstuvwxyzabcde', ws_app_handle('abcdefghijklmnopqrstuvwxyzabcde-fgh', 7), 'not cut to end on a dash');
}

function test_app_handle_falls_back_when_the_name_gives_none()
{
	pg_assert_same('app-7', ws_app_handle('', 7));
	pg_assert_same('app-7', ws_app_handle('X', 7), 'one letter is too short');
	pg_assert_same('app-7', ws_app_handle('数据', 7), 'no Latin letter at all');
	pg_assert_same('app-12', ws_app_handle('---', 12));
}

function test_app_handle_never_takes_a_reserved_word()
{
	foreach (ws_mcp_reserved_aliases() as $word) {
		pg_assert_same('app-3', ws_app_handle(strtoupper($word), 3), $word);
	}

	pg_assert_same('app-3', ws_app_handle('Claude', 3), 'only Claude\'s own application reads @Claude');
}

function test_app_handle_never_takes_an_mcp_alias()
{
	pg_assert_same('app-4', ws_app_handle('GitHub', 4, array('github', 'linear')));
	pg_assert_same('github-app', ws_app_handle('GitHub App', 4, array('github')));
}

function test_app_tag_of_claude_and_pinegrap_ai()
{
	$claude = ws_app_tag(9, array('id' => 9, 'name' => 'My routine'), 9, 5);
	pg_assert_same('@Claude', $claude['label']);
	pg_assert_same('bi-stars', $claude['icon']);
	pg_assert_false($claude['gone']);

	$ai = ws_app_tag(5, array('id' => 5, 'name' => 'Pinegrap AI key'), 9, 5);
	pg_assert_same('@ai', $ai['label']);
	pg_assert_same('bi-cpu', $ai['icon']);

	// The linked application keeps its name even when its row is gone: the
	// connection still points at it.
	pg_assert_same('@Claude', ws_app_tag(9, null, 9, 5)['label']);
	pg_assert_same('@ai', ws_app_tag(5, null, 9, 5)['label']);
}

function test_app_tag_of_another_application_reads_its_own_name()
{
	$tag = ws_app_tag(12, array('id' => 12, 'name' => 'ChatGPT Bridge'), 9, 5);

	pg_assert_same('@chatgpt-bridge', $tag['label']);
	pg_assert_same('ChatGPT Bridge', $tag['meta']);
	pg_assert_true($tag['known']);
	pg_assert_false($tag['gone']);
}

function test_app_tag_of_an_application_claude_used_before()
{
	// No history of the link is kept: once Claude moved to application 9,
	// the tags of application 3 read as application 3 itself.
	$tag = ws_app_tag(3, array('id' => 3, 'name' => 'Claude routine'), 9, 0);

	pg_assert_same('@claude-routine', $tag['label']);
	pg_assert_same('Claude routine', $tag['meta']);

	pg_assert_same('@app-3', ws_app_tag(3, array('id' => 3, 'name' => 'Claude'), 9, 0)['label'], 'the bare name is reserved');
}

function test_app_tag_of_a_deleted_application()
{
	$tag = ws_app_tag(14, null, 9, 5);

	pg_assert_same('@app-14', $tag['label']);
	pg_assert_false($tag['known']);
	pg_assert_true($tag['gone']);
	pg_assert_same('bi-x-circle', $tag['icon']);
}

function test_app_tag_with_nothing_linked()
{
	pg_assert_same('@helper', ws_app_tag(4, array('id' => 4, 'name' => 'Helper'), 0, 0)['label'], 'no link makes no application Claude');
}
