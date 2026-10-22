<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the BlogPosting author of a record page in includes/fn/seo.php:
 * pg_submitted_form_author_name() names the sending account's contact and
 * never its login username, and pg_build_blogposting_jsonld() prints no
 * person when that name is blank.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The lines of a function, to read which columns it queries.
function seo_author_name_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A login username is half of a credential: the author name comes from the
// contact only, so an account without a contact name gives '' and the
// structured data names the site instead.
function test_seo_author_name_never_reads_the_username()
{
	$source = seo_author_name_test_function_source('pg_submitted_form_author_name');

	pg_assert_contains('FROM contacts', $source, 'the contact is read');
	pg_assert_false(strpos($source, 'user_username') !== false, 'the login username is not read');
	pg_assert_false(preg_match('/\bFROM\s+user\b/i', $source) === 1, 'the user table is not queried');
}

// A blank author gives no Person: the site's Organization (when the site has
// a title) or no author at all.
function test_seo_author_name_blank_author_is_not_a_person()
{
	foreach (array('', '   ', null) as $author) {
		$data = pg_build_blogposting_jsonld(array('headline' => 'Post', 'url' => 'https://example.com/post', 'author' => $author));
		$type = isset($data['author']['@type']) ? $data['author']['@type'] : '';
		pg_assert_false($type === 'Person', 'blank author ' . var_export($author, true));
	}

	$data = pg_build_blogposting_jsonld(array('headline' => 'Post', 'url' => 'https://example.com/post', 'author' => ' Ada Lovelace '));
	pg_assert_same(array('@type' => 'Person', 'name' => 'Ada Lovelace'), $data['author'], 'a contact name is the person');
}
