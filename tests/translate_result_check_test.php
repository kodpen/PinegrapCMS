<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_result_check() in includes/translate/engines.php: an answer
 * that is the source word for word is marked for a look, unless nothing in
 * the source was there to translate - a brand name the glossary keeps as it
 * is ("keep" terms, pg_tr_keep_terms()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');

// A text that is only a kept term comes back as it is and is not suspicious.
function test_translate_result_check_kept_term_alone_is_not_suspicious()
{
	$keep = array(array('Pinegrap', false));

	pg_assert_same(array('ok' => true, 'suspicious' => false, 'error' => ''),
		pg_tr_result_check('Pinegrap', 'Pinegrap', 'text', $keep), 'a kept brand name');
	pg_assert_same(array('ok' => true, 'suspicious' => false, 'error' => ''),
		pg_tr_result_check('<b>Pinegrap</b>', '<b>Pinegrap</b>', 'inline', $keep), 'inside an inline tag');
	pg_assert_same(array('ok' => true, 'suspicious' => false, 'error' => ''),
		pg_tr_result_check('Pinegrap CMS', 'Pinegrap CMS', 'text', array(array('Pinegrap CMS', true))), 'a case-sensitive term');
}

// Words around the kept term are still there to translate: an unchanged
// answer stays suspicious, and so does any answer without the glossary.
function test_translate_result_check_unchanged_words_stay_suspicious()
{
	$keep = array(array('Pinegrap', false));

	pg_assert_true(pg_tr_result_check('Pinegrap ile yapıldı', 'Pinegrap ile yapıldı', 'text', $keep)['suspicious'], 'words beside the term');
	pg_assert_true(pg_tr_result_check('Pinegrap', 'Pinegrap', 'text')['suspicious'], 'no glossary given');
	pg_assert_false(pg_tr_result_check('Pinegrap ile yapıldı', 'Made with Pinegrap', 'text', $keep)['suspicious'], 'translated');
}
