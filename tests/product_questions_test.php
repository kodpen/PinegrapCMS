<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for product questions on the comments system
 * (includes/fn/product_questions.php): the kind a post stores, the widget's
 * setting, the kind filters, the thread split by kind, the moderation badge,
 * the answer text, the separate widget claim, the hidden kind field and the
 * wiring of add_comment.php, edit_comment.php and view_comments.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_questions_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_product_question_post_kind()
{
	// Only a product thread, after the step, posting kind question.
	pg_assert_same('comment', pg_product_question_post_kind('question', true, false, false, ''), 'before the columns');
	pg_assert_same('comment', pg_product_question_post_kind('question', false, true, false, ''), 'not a product thread');
	pg_assert_same('comment', pg_product_question_post_kind('', true, true, false, ''), 'nothing posted');
	pg_assert_same('comment', pg_product_question_post_kind(array('question'), true, true, false, ''), 'an array');
	pg_assert_same('comment', pg_product_question_post_kind('answer', true, true, true, 'question'), 'answer is never posted');

	pg_assert_same('question', pg_product_question_post_kind('question', true, true, false, ''), 'a visitor asks');
	pg_assert_same('question', pg_product_question_post_kind('question', true, true, true, ''), 'staff ask too');
	pg_assert_same('question', pg_product_question_post_kind('question', true, true, false, 'question'), 'a visitor under a question asks again');
	pg_assert_same('question', pg_product_question_post_kind('question', true, true, true, 'answer'), 'staff under an answer');
	pg_assert_same('answer', pg_product_question_post_kind('question', true, true, true, 'question'), 'staff answer a question');
	pg_assert_same('comment', pg_product_question_post_kind('question', true, true, true, 'comment'), 'a reply to a review stays a comment');
}

function test_product_question_widget_shows()
{
	pg_assert_same('questions', pg_product_question_widget_shows(array('shows' => 'questions')), 'questions');

	foreach (array(array(), array('shows' => 'comments'), array('shows' => 'Questions'), array('shows' => array('questions')), null, 'questions') as $cfg) {
		pg_assert_same('comments', pg_product_question_widget_shows($cfg), 'default: ' . var_export($cfg, true));
	}
}

function test_product_question_kind_sql()
{
	pg_assert_same(" AND (comments.kind IN ('question', 'answer'))", pg_product_question_kind_sql('questions', true), 'questions');
	pg_assert_same(" AND (comments.kind = 'comment')", pg_product_question_kind_sql('comments', true), 'comments');
	pg_assert_same('', pg_product_question_kind_sql('', true), 'none');
	pg_assert_same('', pg_product_question_kind_sql('questions', false), 'before the columns');

	pg_assert_same("(comments.kind = 'question')", pg_product_question_filter_sql('questions', true), 'every question');
	pg_assert_same(pg_product_question_unanswered_sql(), pg_product_question_filter_sql('unanswered', true), 'unanswered');
	pg_assert_true(strpos(pg_product_question_unanswered_sql(), "answers.answer_of = comments.id") !== false, 'unanswered reads the answers');
	pg_assert_same('', pg_product_question_filter_sql('unanswered', false), 'no filter before the columns');
	pg_assert_same('', pg_product_question_filter_sql('other', true), 'unknown filter');
}

function test_product_question_split()
{
	$rows = array(
		array('id' => '1', 'kind' => 'comment', 'message' => 'Great'),
		array('id' => '2', 'kind' => 'question', 'answer_of' => '0', 'message' => 'Size?'),
		array('id' => '3', 'kind' => 'question', 'answer_of' => '0', 'message' => 'Colour?'),
		array('id' => '4', 'kind' => 'answer', 'answer_of' => '2', 'message' => 'Fits large'),
		array('id' => '5', 'message' => 'Old comment'),
		array('id' => '6', 'kind' => 'answer', 'answer_of' => '99', 'message' => 'Orphan'),
		array('id' => '7', 'kind' => 'answer', 'answer_of' => '2', 'message' => 'Also'),
		'not a row',
	);

	$split = pg_product_question_split($rows);

	pg_assert_same(array('1', '5'), array_column($split['comments'], 'id'), 'comments in order, a row without kind is one');
	pg_assert_same(2, count($split['questions']), 'two questions');
	pg_assert_same('2', $split['questions'][0]['row']['id'], 'first question first');
	pg_assert_same(array('4', '7'), array_column($split['questions'][0]['answers'], 'id'), 'its answers in order');
	pg_assert_same(array(), $split['questions'][1]['answers'], 'second question unanswered');
	pg_assert_same(array('comments' => array(), 'questions' => array()), pg_product_question_split(null), 'no rows');
}

function test_product_question_badge_and_text()
{
	pg_assert_same('', pg_product_question_badge('comment', false), 'a comment has none');
	pg_assert_same('Question, answered', pg_product_question_badge('question', true), 'answered');
	pg_assert_same('Question, not answered', pg_product_question_badge('question', false), 'waiting');
	pg_assert_same('Answer', pg_product_question_badge('answer', false), 'answer');

	pg_assert_same('Yes', pg_product_question_answer_text("  Yes \n"), 'trimmed');
	pg_assert_same('', pg_product_question_answer_text(array('x')), 'an array');
	pg_assert_same(5000, mb_strlen(pg_product_question_answer_text(str_repeat('ş', 6000))), 'capped at 5000 characters');
}

// The questions widget claims apart from the comments widget, and only the
// comments claim stands the classic block down.
function test_product_question_widget_claim()
{
	$old = pg_comments_widget_pages(array());

	pg_assert_true(pg_comments_widget_claim(7, 'questions'), 'questions claimed');
	pg_assert_false(pg_comments_widget_drawn(7), 'questions alone leave the classic block');
	pg_assert_false(pg_comments_widget_claim(7, 'questions'), 'a second questions widget draws nothing');
	pg_assert_true(pg_comments_widget_claim(7), 'the reviews widget still draws');
	pg_assert_true(pg_comments_widget_drawn(7), 'the comments claim counts');
	pg_assert_false(pg_comments_widget_claim('7'), 'a second comments widget draws nothing');

	pg_comments_widget_pages($old);
}

function test_product_question_hidden_kind()
{
	$plain = pg_comments_form_hidden_html('', '/p', 5, 'product', 9, 0);
	$asked = pg_comments_form_hidden_html('', '/p', 5, 'product', 9, 0, 'question');

	pg_assert_true(strpos($plain, 'name="kind"') === false, 'no kind on the comments form');
	pg_assert_true(strpos($asked, '<input type="hidden" name="kind" value="question">') !== false, 'the questions form posts its kind');
	pg_assert_same(0, strpos($asked, $plain), 'the other fields unchanged');
}

function test_product_question_wiring()
{
	$add = product_questions_test_source('add_comment.php');
	pg_assert_true(strpos($add, "'parent_id', 'kind', 'rating'") !== false, 'kind is a single value');
	pg_assert_true(strpos($add, "if (\$comment_kind !== 'comment') {\n    \$is_product_review = false;") !== false, 'a question skips the purchase gate');
	pg_assert_true(strpos($add, "(\$comment_kind === 'answer') ? (int) \$parent_id : 0") !== false, 'an answer names its question');

	$edit = product_questions_test_source('edit_comment.php');
	pg_assert_true(strpos($edit, 'pg_product_question_answer_save($comment_id') !== false, 'the answer is saved');
	pg_assert_true(strpos($edit, "&& (\$comment_kind === 'comment');") !== false, 'no store reply on a question');

	$list = product_questions_test_source('view_comments.php');
	pg_assert_true(strpos($list, 'pg_product_question_filter_sql($question_filter, $questions_ready)') !== false, 'the list filters');

	$widget = product_questions_test_source('includes/fn/widgets_comments.php');
	pg_assert_true(strpos($widget, "\$read_options['kind'] = 'questions';") !== false, 'the widget reads questions');
	pg_assert_true(strpos($widget, "\$questions ? pg_comments_widget_claim(\$page_id, 'questions') : pg_comments_widget_claim(\$page_id)") !== false, 'the widget claims by part');

	$migration = product_questions_test_source('includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($migration, "upgrade_2026_11_0_product_questions();") !== false, 'the step is called');
	pg_assert_true(strpos($migration, "ENUM('comment','question','answer') NOT NULL DEFAULT 'comment'") !== false, 'the kind column');
}

function test_product_question_answer_mail_text()
{
	$text = pg_product_question_answer_mail_text(' Blue shirt ', " Size? \n", 'Fits large', 'https://example.com/shop/blue-shirt?comment=4#c-4');

	pg_assert_same('Your question about Blue shirt was answered', $text['subject'], 'the subject names the product');
	pg_assert_same("Your question:\nSize?\n\nAnswer:\nFits large\n\nSee it on the product page:\nhttps://example.com/shop/blue-shirt?comment=4#c-4\n", $text['body'], 'the body quotes both and links');
	pg_assert_same('Your question was answered', pg_product_question_answer_mail_text('', 'Q', 'A', '')['subject'], 'no product name');
	pg_assert_true(strpos(pg_product_question_answer_mail_text('', 'Q', 'A', '')['body'], 'product page') === false, 'no link line without a link');
}

function test_product_question_answer_mail_due()
{
	$question = array('published' => '1', 'created_user_id' => '7');
	$answer = array('published' => '1', 'created_user_id' => '2');

	pg_assert_true(pg_product_question_answer_mail_due($question, $answer, 'a@example.com'), 'a member asked, staff answered');
	pg_assert_false(pg_product_question_answer_mail_due(array('created_user_id' => '0') + $question, $answer, 'a@example.com'), 'a guest left no address');
	pg_assert_false(pg_product_question_answer_mail_due($question, $answer, ''), 'no address');
	pg_assert_false(pg_product_question_answer_mail_due($question, array('created_user_id' => '7') + $answer, 'a@example.com'), 'not the one who answered');
	pg_assert_false(pg_product_question_answer_mail_due(array('published' => '0') + $question, $answer, 'a@example.com'), 'a held question');
	pg_assert_false(pg_product_question_answer_mail_due($question, array('published' => '0') + $answer, 'a@example.com'), 'a held answer');
	pg_assert_false(pg_product_question_answer_mail_due($question, null, 'a@example.com'), 'no answer');
}

function test_product_question_jsonld_items()
{
	$rows = array(
		array('question' => ' Size? ', 'question_name' => 'Ali', 'question_at' => '1700000000', 'answer' => 'Fits large', 'answer_name' => '', 'answer_at' => '1700003600'),
		array('question' => 'Colour?', 'answer' => ''),
		array('question' => '', 'answer' => 'Orphan'),
		'not a row',
	);
	$items = pg_product_question_jsonld_items($rows);

	pg_assert_same(1, count($items), 'only answered questions');
	pg_assert_same('Question', $items[0]['@type'], 'a Question');
	pg_assert_same('Size?', $items[0]['name'], 'trimmed');
	pg_assert_same(1, $items[0]['answerCount'], 'one answer');
	pg_assert_same(array('@type' => 'Person', 'name' => 'Ali'), $items[0]['author'], 'the asker');
	pg_assert_same(gmdate('c', 1700000000), $items[0]['dateCreated'], 'when asked');
	pg_assert_same('Answer', $items[0]['acceptedAnswer']['@type'], 'its answer');
	pg_assert_same('Anonymous', $items[0]['acceptedAnswer']['author']['name'], 'no name reads Anonymous');
	pg_assert_same(2, count(pg_product_question_jsonld_items(array_fill(0, 5, $rows[0]), 2)), 'capped');
	pg_assert_same(array(), pg_product_question_jsonld_items(null), 'no rows');
}

function test_product_question_jsonld_in_product_markup()
{
	$item = array('@type' => 'Question', 'name' => 'Size?');
	$catalog = product_questions_test_source('includes/fn/widgets_catalog.php');
	pg_assert_true(strpos($catalog, "\$data['subjectOf'] = array_values(\$questions);") !== false, 'the Product carries its questions');

	$page = product_questions_test_source('get_page_content.php');
	pg_assert_true(strpos($page, 'pg_product_question_markup_shown($page_id, $_pg_sw_product_id) ? pg_product_question_jsonld($page_id, $_pg_sw_product_id) : array()') !== false, 'only while the page shows them');
	pg_assert_same('Question', $item['@type'], 'item shape');
}

function test_product_question_census_kind()
{
	$census = product_questions_test_source('includes/fn/widgets_comments.php');
	pg_assert_true(strpos($census, "\$kind = 'comments_questions';") !== false, 'a questions widget is no second comments widget');

	$facts = pg_comments_page_census_facts(array('refs' => array(4 => 1, 5 => 1), 'blocks' => 0), array(
		4 => array('type' => 'comments', 'census' => array('refs' => array(), 'blocks' => 0)),
		5 => array('type' => 'comments_questions', 'census' => array('refs' => array(), 'blocks' => 0)),
	));
	pg_assert_same(1, $facts['widgets'], 'reviews and questions widgets: one thread widget');
}

function test_product_question_api_kind()
{
	pg_assert_same('question', api_comment_kind(array('kind' => 'question')), 'question');
	pg_assert_same('answer', api_comment_kind(array('kind' => 'answer')), 'answer');
	pg_assert_same('comment', api_comment_kind(array('kind' => 'other')), 'unknown');
	pg_assert_same('comment', api_comment_kind(array()), 'before the step');

	$products = product_questions_test_source('includes/api/resources/products.php');
	pg_assert_true(strpos($products, "'kind'      => 'comment',") !== false, 'reviews leave questions out');
}

function test_product_question_mail_wiring()
{
	$edit = product_questions_test_source('edit_comment.php');
	pg_assert_true(strpos($edit, "if (\$answer_saved === 'added') {\n                pg_product_question_answer_mail(\$comment_id);") !== false, 'the Answer box mails once');

	$add = product_questions_test_source('add_comment.php');
	pg_assert_true(strpos($add, "if ((\$comment_kind === 'answer') && (\$published == '1') && (\$comment_id > 0)) {\n    pg_product_question_answer_mail(\$parent_id);") !== false, 'a published answer from the page mails');
}

// A reviews widget and a questions widget on one product page each keep
// their own form state, Messages and anchor, so a refused question comes
// back in the questions form and a reply lands in the form that posts it.
function test_product_question_form_names()
{
	pg_assert_same(array('state' => 'add_question', 'anchor' => 'software_add_question'), pg_product_question_form_names('question'), 'a question form');
	pg_assert_same(array('state' => 'add_comment', 'anchor' => 'software_add_comment'), pg_product_question_form_names(''), 'a comment form');
	pg_assert_same(array('state' => 'add_comment', 'anchor' => 'software_add_comment'), pg_product_question_form_names(array('question')), 'an array');
	pg_assert_same(array('state' => 'add_comment', 'anchor' => 'software_add_comment'), pg_product_question_form_names(null), 'nothing posted');
}

function test_product_question_reply_fits()
{
	pg_assert_true(pg_product_question_reply_fits(true, 'question'), 'the questions form answers a question');
	pg_assert_false(pg_product_question_reply_fits(true, 'comment'), 'never a review');
	pg_assert_false(pg_product_question_reply_fits(true, 'answer'), 'never an answer');
	pg_assert_true(pg_product_question_reply_fits(false, 'comment'), 'the reviews form answers a review');
	pg_assert_false(pg_product_question_reply_fits(false, 'question'), 'never a question');
	pg_assert_false(pg_product_question_reply_fits(false, 'answer'), 'never an answer');
}

function test_product_question_form_wiring()
{
	$add = product_questions_test_source('add_comment.php');
	pg_assert_true(strpos($add, "\$form_names = pg_product_question_form_names(\$_POST['kind'] ?? '');\n\$liveform = new liveform(\$form_names['state'], \$_POST['page_id'] ?? 0);") !== false, 'a question is kept apart from a comment');
	pg_assert_false(strpos($add, "'#software_add_comment'"), 'every refusal goes back to the form that posted');
	pg_assert_same(3, substr_count($add, "'#' . \$form_names['anchor']"), 'the three refusals');

	$module = product_questions_test_source('includes/fn/widgets_comments.php');
	$start = strpos($module, 'function _render_system_widget_comments(');
	$renderer = ($start === false) ? '' : substr($module, $start);
	pg_assert_true(strpos($renderer, "\$form_names = pg_product_question_form_names(\$questions ? 'question' : '');") !== false, 'the widget picks its names');
	pg_assert_true(strpos($renderer, "new liveform(\$form_names['state'], \$page_id)") !== false, 'reads its own state');
	pg_assert_true(strpos($renderer, "_pg_inject_messages_node(\$tree, \$form_names['state']);") !== false, 'prints its own errors');
	pg_assert_true(strpos($renderer, "pg_product_question_reply_fits(\$questions, pg_product_question_state(\$reply_parent['id'])['kind'])") !== false, 'answers only a comment of its kind');
	pg_assert_false(strpos($renderer, "liveform('add_comment'"), 'no shared state');

	foreach (array('frontend.src.js', 'frontend.min.js') as $file) {
		$js = product_questions_test_source($file);
		pg_assert_true((strpos($js, '.has("form[data-pg-comment-form]")') !== false) || (strpos($js, ".has('form[data-pg-comment-form]')") !== false), $file . ': the reply fills the form of its own widget');
	}
}
