<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager short-link wizard's address check
 * (pg_short_link_read_request(), includes/explorer/short_links.php): an
 * address destination follows pg_short_link_url_problem(), the rule the
 * classic screens, the external API and the CSV import apply.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// Another scheme, a protocol-relative address or a backslash is refused on
// the url field; a web address or a path on this site is kept as given.
function test_explorer_short_link_url_follows_the_shared_rule()
{
	$read = function ($url) {
		return pg_short_link_read_request(array('destination_type' => 'url', 'url' => $url), null);
	};

	foreach (array('javascript:alert(1)', 'data:text/html,<b>x</b>', 'ftp://example.com/x', '//evil.example/login', '/\\evil.example', "https://example.com/\x7f") as $url) {
		$result = $read($url);
		pg_assert_same('url', $result['error'] ?? '', 'refused: ' . $url);
		pg_assert_same(pg_short_link_url_problem($url), $result['message'] ?? '', 'shared message: ' . $url);
	}

	foreach (array('https://example.com/a?b=1', 'http://example.com', '/about-us', 'blog/post') as $url) {
		$result = $read($url);
		pg_assert_false(isset($result['error']), 'kept: ' . $url);
		pg_assert_same(array('url' => $url), $result['columns'] ?? null, 'stored as given: ' . $url);
	}

	pg_assert_same('url', $read('')['error'] ?? '', 'empty refused');
	pg_assert_same('url', $read('https://example.com/a b')['error'] ?? '', 'space refused');
}
