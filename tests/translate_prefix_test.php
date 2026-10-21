<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_tr_prefixes_settle() in includes/translate/store.php: the
 * address prefix each target language is saved with. The router matches the
 * prefix in lower case only, so a language whose code has capitals (pt-BR,
 * zh-CN) falls back to its code lower-cased, and two languages never share
 * one prefix.
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

// An emptied, malformed or taken prefix falls back to the code, lower-cased.
function test_translate_prefix_falls_back_to_the_lower_cased_code()
{
	pg_assert_same(array(array('pt-BR' => 'pt-br'), array()), pg_tr_prefixes_settle(array('pt-BR' => '')), 'empty');
	pg_assert_same(array(array('zh-CN' => 'zh-cn'), array()), pg_tr_prefixes_settle(array('zh-CN' => 'çin/')), 'malformed');
	pg_assert_same(array(array('en' => 'en', 'zh-TW' => 'zh-tw'), array()), pg_tr_prefixes_settle(array('en' => 'en', 'zh-TW' => 'en')), 'taken');
}

// A valid prefix is kept, lower-cased.
function test_translate_prefix_keeps_a_valid_prefix()
{
	pg_assert_same(array(array('pt-BR' => 'br', 'en' => 'en-us'), array()), pg_tr_prefixes_settle(array('pt-BR' => 'br', 'en' => ' EN-US ')), 'kept');
}

// When the fallback is taken as well, the language is a conflict.
function test_translate_prefix_reports_a_conflict()
{
	pg_assert_same(array(array('en' => 'de'), array('de')), pg_tr_prefixes_settle(array('en' => 'de', 'de' => '')), 'code taken by another language');
	pg_assert_same(array(array('en' => 'pt-br'), array('pt-BR')), pg_tr_prefixes_settle(array('en' => 'pt-br', 'pt-BR' => '')), 'lower-cased code taken');
}
