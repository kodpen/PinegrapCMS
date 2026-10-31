<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the administrator notification add_comment.php sends about a
 * new comment: it goes through the mail queue, so the writer never waits on
 * the mail server, and the attachment values its body reads are set on every
 * post, so a comment without an attachment logs no undefined variable.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_admin_mail_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');
}

// $file_name and $file_size are set beside $file_id = 0, before the
// attachment branch, because the mail closure takes them in its use list.
function test_comment_admin_mail_attachment_defaults()
{
	$source = comment_admin_mail_test_source();
	$defaults = strpos($source, "\$file_id = 0;\n\$file_name = '';\n\$file_size = 0;");
	$branch = strpos($source, "if ((\$comments_allow_file_attachments == 1) && isset(\$_FILES['file'])");
	$closure = strpos($source, '$file_id, $file_name, $file_size,');
	pg_assert_true($defaults !== false, 'attachment defaults set together');
	pg_assert_true($branch !== false && $closure !== false, 'attachment branch and mail closure found');
	pg_assert_true($defaults < $branch && $branch < $closure, 'defaults come before the branch and the closure');
}
