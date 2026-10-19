<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_defuse_php_tags() and the places that must call it: the
 * content-level merge in includes/designer_access.php and the raw keys of a
 * system widget's token map. get_page_content.php evaluates the assembled
 * page when PHP_REGIONS is on, so a PHP open tag written by a content-level
 * operator must never reach it intact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
require_once(PG_FUNCTIONS_DIR . '/url_to_absolute.php');

// The open tag is rewritten, the rest of the markup is left alone.
function test_php_tags_defuse_rewrites_open_tag()
{
	pg_assert_same('<p>&lt;?php echo 1; ?></p>', pg_defuse_php_tags('<p><?php echo 1; ?></p>'));
	pg_assert_same('a &lt;?= $x ?> b', pg_defuse_php_tags('a <?= $x ?> b'));
}

// Markup without an open tag comes back byte for byte, and null prints as nothing.
function test_php_tags_defuse_keeps_plain_markup()
{
	$html = '<div class="x">1 < 2 &amp; "q"</div>';
	pg_assert_same($html, pg_defuse_php_tags($html));
	pg_assert_same('', pg_defuse_php_tags(null));
}

// A content-level operator's text prop is defused by the restricted merge.
function test_php_tags_restricted_merge_defuses_text()
{
	$stored = array(
		'type' => 'section',
		'props' => array(),
		'children' => array(
			array('type' => 'text', 'props' => array('text' => 'Hello')),
		),
	);
	$submitted = $stored;
	$submitted['children'][0]['props']['text'] = 'Hi <?php system($_GET["c"]); ?>';

	$merged = pg_designer_merge_restricted_tree($stored, $submitted);

	pg_assert_same('Hi &lt;?php system($_GET["c"]); ?>', $merged['children'][0]['props']['text']);
}

// Inside an editable area the whole accepted subtree is defused, markers included.
function test_php_tags_restricted_merge_defuses_editable_subtree()
{
	$stored = array(
		'type' => 'section',
		'props' => array('_editable' => 1),
		'children' => array(),
	);
	$submitted = $stored;
	$submitted['children'][] = array(
		'type' => 'text',
		'props' => array('text' => '<!--pg-shared-ref:1--><?php phpinfo(); ?>'),
	);

	$merged = pg_designer_merge_restricted_tree($stored, $submitted);

	pg_assert_same('<!-- pg-shared-ref:1-->&lt;?php phpinfo(); ?>', $merged['children'][0]['props']['text']);
}

// A raw token value keeps its markup but loses its open tag; a plain one is escaped.
function test_php_tags_token_map_defuses_raw_keys()
{
	$values = pg_sw_escape_token_values(array(
		'__name'        => '24" <b>Monitor</b>',
		'__description' => '<p>Big</p><?php echo 1; ?>',
	), array('__event_notes'));

	pg_assert_same('24&quot; &lt;b&gt;Monitor&lt;/b&gt;', $values['__name']);
	pg_assert_same('<p>Big</p>&lt;?php echo 1; ?>', $values['__description']);
}

// A key named in $raw_extra is treated like the built-in raw keys.
function test_php_tags_token_map_defuses_raw_extra()
{
	$values = pg_sw_escape_token_values(array(
		'__event_notes' => '<i>x</i><?= 1 ?>',
	), array('__event_notes'));

	pg_assert_same('<i>x</i>&lt;?= 1 ?>', $values['__event_notes']);
}

// The site importer resolves relative addresses again: the host pattern was
// an invalid range under PCRE2 and every call returned false.
function test_php_tags_url_to_absolute_resolves()
{
	pg_assert_same('http://a.com/z.html?q=1', url_to_absolute('http://a.com/x/y.html', '../z.html?q=1'));
	pg_assert_same('https://www.site-x.com/dir/img/a.png', url_to_absolute('https://www.site-x.com/dir/', 'img/a.png'));
	pg_assert_same('http://cdn.b.com/f.js', url_to_absolute('http://a.com/x/', '//cdn.b.com/f.js'));
}
