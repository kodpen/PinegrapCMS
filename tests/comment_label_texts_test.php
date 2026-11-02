<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a page's comment label is stored and written: the English
 * default the Edit Page screen used to save counts as no label, so the
 * site's language word is shown; the administrator e-mail starts its
 * message line with the label's first letter in upper case.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_comment_label_the_old_english_default_counts_as_none()
{
	pg_assert_same('', pg_comment_label_stored(''), 'empty');
	pg_assert_same('', pg_comment_label_stored('Comment'), 'the default Edit Page saved');
	pg_assert_same('', pg_comment_label_stored(' Comment '), 'with spaces');
	pg_assert_same('Review', pg_comment_label_stored('Review'), 'a label of its own is kept');
	pg_assert_same('Yorum', pg_comment_label_stored('Yorum'), 'a label in another language is kept');
	pg_assert_same('comment', pg_comment_label_stored('comment'), 'only the exact default is dropped');
}

function test_comment_label_get_comment_label_reads_the_stored_label_through_the_helper()
{
	pg_assert_same(lang('Comment'), get_comment_label(array('label' => 'Comment')), 'the old default is the language word');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	pg_assert_true(strpos($source, "pg_comment_label_stored(\$properties['label'])") !== false, 'get_comment_label() reads the label through the helper');
}

function test_comment_label_edit_page_leaves_an_empty_label_empty()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php');

	pg_assert_false(strpos($source, "\$comments_label = 'Comment';") !== false, 'no English value put in the field');
	pg_assert_true(strpos($source, "placeholder=\"' . h(lang('Comment')) . '\"") !== false, 'the language word is the placeholder');
}

function test_comment_label_mail_line_starts_with_a_capital()
{
	pg_assert_same('Yanıt', pg_comment_label_line_start('yanıt', 'tr'), 'Turkish reply label');
	pg_assert_same('Yorum', pg_comment_label_line_start('Yorum', 'tr'), 'already capital');
	pg_assert_same('İleti', pg_comment_label_line_start('ileti', 'tr'), 'Turkish dotted i');
	pg_assert_same('Ileti', pg_comment_label_line_start('ileti', 'en'), 'dotted i outside Turkish');
	pg_assert_same('Çözüm', pg_comment_label_line_start('çözüm', 'tr'), 'a multibyte first letter');
	pg_assert_same('Reply', pg_comment_label_line_start('reply', 'en'), 'English');
	pg_assert_same('', pg_comment_label_line_start('', 'tr'), 'empty');
}

function test_comment_label_admin_mail_uses_the_capital_line_start()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	pg_assert_false(strpos($source, "\$comment_label . ': ' . \$message") !== false, 'the raw label no longer starts the line');
	pg_assert_same(2, substr_count($source, "pg_comment_label_line_start(\$comment_label, lang(array('info' => ''))) . ': ' . \$message"), 'both administrator e-mails');
}

function test_comment_label_awaiting_approval_status_starts_with_a_capital_in_turkish()
{
	$map = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/y.json'), true);
	$value = (string) ($map['Your {var:1} is awaiting approval'] ?? '');

	pg_assert_same('Gönderdiğiniz {var:1} onay bekliyor', $value, 'says it is the member\'s own, starts with a capital');
}
