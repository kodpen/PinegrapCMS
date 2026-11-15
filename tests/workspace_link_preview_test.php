<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link preview of a Workspace message
 * (includes/workspace/link_preview.php): the first web address of a text,
 * the address check and the cut of a page's fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/link_preview.php');

function test_workspace_link_preview_first_url()
{
	pg_assert_same('https://example.com/a', ws_link_preview_first_url('See https://example.com/a and https://example.org/b'), 'the first address');
	pg_assert_same('', ws_link_preview_first_url('no address here'), 'no address');
	pg_assert_same('', ws_link_preview_first_url('ftp://example.com/file'), 'only http and https');
	pg_assert_same('http://example.com/x?a=1', ws_link_preview_first_url('Look: http://example.com/x?a=1.'), 'a full stop is the sentence');
	pg_assert_same('https://example.com/b', ws_link_preview_first_url('(see https://example.com/b), done'), 'a bracket around the address');
	pg_assert_same('https://en.wikipedia.org/wiki/Ankara_(disambiguation)', ws_link_preview_first_url('https://en.wikipedia.org/wiki/Ankara_(disambiguation)'), 'a bracket of the address');
}

function test_workspace_link_preview_first_url_skips_code_and_tags()
{
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('run `curl https://example.com/a` then https://example.org/b'), 'inline code');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url("```\nhttps://example.com/a\n```\nhttps://example.org/b"), 'a code block');
	pg_assert_same('', ws_link_preview_first_url("```\nhttps://example.com/a"), 'an open code block');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('<#order:5> <@app:2> https://example.org/b'), 'tags are not addresses');
	pg_assert_same('', ws_link_preview_first_url('https://' . str_repeat('a', 2100) . '.com'), 'too long');
}
