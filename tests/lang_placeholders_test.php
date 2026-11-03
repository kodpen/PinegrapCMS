<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * lang()'s {var:N} and {suffix} placeholders (includes/fn/lang.php): each
 * placeholder takes its own value once, and a value is never searched again
 * for placeholders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The ordinary forms keep working.
function test_lang_placeholders_basic()
{
	pg_assert_same('Hello World', lang(array('string' => 'Hello {var:1}', 'vars' => array('World'), 'language' => 'en')));
	pg_assert_same('Count: 5', lang(array('string' => 'Count: {var:1}', 'vars' => 5, 'language' => 'en')), 'a scalar is the first value');
	pg_assert_same('Welcome John Doe', lang(array('string' => 'Welcome {var:1|c}', 'vars' => array('john doe'), 'language' => 'en')));
	pg_assert_same('A B c', lang(array('string' => '{var|1|u} {var:2|u} {var:3|l}', 'vars' => array('a', 'b', 'C'), 'language' => 'en')));
	pg_assert_same('X here', lang(array('string' => '{var|u} here', 'vars' => array('x'), 'language' => 'en')), 'the {var|u} shorthand');
	pg_assert_same('3 files', lang(array('string' => '{var:1} file{suffix}', 'vars' => array(3), 'suffix' => 's', 'language' => 'en')));
	pg_assert_same('a-b', lang(array('string' => '{suffix:1}-{suffix:2}', 'suffix' => array('a', 'b'), 'language' => 'en')));
	pg_assert_same('{var:2} left', lang(array('string' => '{var:2} left', 'vars' => array('x'), 'language' => 'en')), 'a placeholder with no value stays');
}

// A value that holds placeholder text (a page or product name a visitor
// typed) is printed as it is, not filled with the next value.
function test_lang_placeholders_value_is_not_rescanned()
{
	pg_assert_same(
		'Page {var:2} moved to Docs',
		lang(array('string' => 'Page {var:1} moved to {var:2}', 'vars' => array('{var:2}', 'Docs'), 'language' => 'en'))
	);
	pg_assert_same(
		'Name a{suffix}s',
		lang(array('string' => 'Name {var:1}{suffix}', 'vars' => array('a{suffix}'), 'suffix' => 's', 'language' => 'en'))
	);
}

// A modifier changes only its own placeholder: the plain {var:1} beside a
// {var:1|c} keeps the value as given.
function test_lang_placeholders_modifier_stays_local()
{
	pg_assert_same(
		'My Page was added to my page',
		lang(array('string' => '{var:1|c} was added to {var:1}', 'vars' => array('my page'), 'language' => 'en'))
	);
	pg_assert_same(
		'AB ab',
		lang(array('string' => '{var:1|u} {var:1|l}', 'vars' => array('aB'), 'language' => 'en')),
		'two modifiers of one value'
	);
}
