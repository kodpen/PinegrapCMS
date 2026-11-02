<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product questions on the comments system: a visitor's question about a
 * product and the staff answer to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: product questions.
//
// A question is a comment of kind 'question' on a product's thread (the
// thread a product review sits on: item_type 'product', or a product group
// sold as one product with options). The staff answer is a comment of kind
// 'answer' on the same thread whose answer_of and parent_id name the
// question, so the threaded comments widget draws it under the question.
// Every other comment is kind 'comment'. A question needs no purchase, carries
// no stars and counts in no rating; its verified_order_id stays 0, so the
// verified-only review list never shows it.
//
// Nothing here does anything until the 11.0.148 columns exist.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether comments.kind and comments.answer_of are there (2026.11.0,
 * 11.0.148). False before the step has run, since files land before the
 * upgrade, and without a connection.
 *
 * @return bool
 */
function pg_product_questions_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('comments', 'kind') && pg_schema_has('comments', 'answer_of');
    }

    return $ready;
}

/**
 * The kind a post on add_comment.php stores. Pure.
 *
 * 'comment' unless the columns are there, the thread is a product's (the
 * review thread) and the form posted kind 'question'. Then staff answering a
 * question write its 'answer'; a reply to a review stays a 'comment'; any
 * other post is a 'question'.
 *
 * @param mixed  $posted         the posted kind
 * @param bool   $product_thread pg_review_gate_applies() for the thread
 * @param bool   $ready          pg_product_questions_ready()
 * @param bool   $staff          the writer may edit the page
 * @param string $parent_kind    the kind of the comment answered, '' for none
 * @return string 'comment', 'question' or 'answer'
 */
function pg_product_question_post_kind($posted, $product_thread, $ready, $staff, $parent_kind)
{
    if (!$ready || !$product_thread || !is_string($posted) || ($posted !== 'question')) {
        return 'comment';
    }

    if ($parent_kind === 'comment') {
        return 'comment';
    }

    if ($staff && ($parent_kind === 'question')) {
        return 'answer';
    }

    return 'question';
}

/**
 * What a comments widget shows, from its Display setting. Pure.
 *
 * @param mixed $cfg the widget's config
 * @return string 'comments' or 'questions'
 */
function pg_product_question_widget_shows($cfg)
{
    return (is_array($cfg) && isset($cfg['shows']) && ($cfg['shows'] === 'questions')) ? 'questions' : 'comments';
}

/**
 * The SQL filter of the kinds a thread read keeps. Pure.
 *
 * 'questions': the questions and their answers; 'comments': everything but
 * those; anything else, or before the columns: no filter.
 *
 * @param mixed $shows
 * @param bool  $ready
 * @return string
 */
function pg_product_question_kind_sql($shows, $ready)
{
    if (!$ready) {
        return '';
    }

    if ($shows === 'questions') {
        return " AND (comments.kind IN ('question', 'answer'))";
    }

    if ($shows === 'comments') {
        return " AND (comments.kind = 'comment')";
    }

    return '';
}

/**
 * The SQL condition of a question that has no answer yet, for a query on
 * comments. Pure.
 *
 * @return string
 */
function pg_product_question_unanswered_sql()
{
    return "(comments.kind = 'question') AND NOT EXISTS (SELECT 1 FROM comments AS answers WHERE (answers.answer_of = comments.id) AND (answers.kind = 'answer'))";
}

/**
 * The Comments screen's kind filter as SQL, '' for every comment. Pure.
 *
 * @param mixed $filter 'questions' (every question), 'unanswered'
 * @param bool  $ready
 * @return string
 */
function pg_product_question_filter_sql($filter, $ready)
{
    if (!$ready) {
        return '';
    }

    if ($filter === 'questions') {
        return "(comments.kind = 'question')";
    }

    if ($filter === 'unanswered') {
        return pg_product_question_unanswered_sql();
    }

    return '';
}

/**
 * The thread's rows split by kind. Pure.
 *
 * 'comments' keeps the rows of kind 'comment' (a row without a kind is one)
 * in the order read; 'questions' each question in the order read with its
 * answers (answer_of naming it) in the order read; an answer whose question
 * is not among the rows is left out.
 *
 * @param mixed $rows
 * @return array 'comments' (rows), 'questions' (list of array('row', 'answers'))
 */
function pg_product_question_split($rows)
{
    $comments = array();
    $questions = array();
    $answers = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $kind = (isset($row['kind']) && is_string($row['kind'])) ? $row['kind'] : 'comment';
        $id = _pg_comments_row_int($row, 'id');

        if ($kind === 'question') {
            if ($id > 0) {
                $questions[$id] = array('row' => $row, 'answers' => array());
            }
        } elseif ($kind === 'answer') {
            $answers[] = $row;
        } else {
            $comments[] = $row;
        }
    }

    foreach ($answers as $row) {
        $of = _pg_comments_row_int($row, 'answer_of');

        if (isset($questions[$of])) {
            $questions[$of]['answers'][] = $row;
        }
    }

    return array('comments' => $comments, 'questions' => array_values($questions));
}

/**
 * The label a moderation list shows beside a comment's name, '' for a
 * comment. Pure.
 *
 * @param mixed $kind
 * @param bool  $answered for a question: whether it has an answer
 * @return string
 */
function pg_product_question_badge($kind, $answered)
{
    if ($kind === 'question') {
        return $answered ? lang('Question, answered') : lang('Question, not answered');
    }

    if ($kind === 'answer') {
        return lang('Answer');
    }

    return '';
}

/**
 * The answer text staff typed: trimmed, at most 5000 characters. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_product_question_answer_text($value)
{
    if (!is_string($value)) {
        return '';
    }

    return mb_substr(trim($value), 0, 5000);
}

/**
 * How many questions wait for an answer, on pages the reader may edit when
 * $folder_ids is a list (null: every page). 0 before the columns.
 *
 * @param array|null $folder_ids
 * @return int
 */
function pg_product_questions_unanswered_count($folder_ids = null)
{
    if (!pg_product_questions_ready()) {
        return 0;
    }

    $where = pg_product_question_unanswered_sql();

    if (is_array($folder_ids)) {
        $ids = array();

        foreach ($folder_ids as $id) {
            if (is_scalar($id) && ((int) $id > 0)) {
                $ids[] = (int) $id;
            }
        }

        if (!$ids) {
            return 0;
        }

        $where .= " AND (page.page_folder IN (" . implode(',', array_unique($ids)) . "))";
    }

    return (int) db_value(
        "SELECT COUNT(*)
        FROM comments
        LEFT JOIN page ON page.page_id = comments.page_id
        WHERE " . $where);
}

/**
 * A comment's kind and, for a question, its answer's row (id, message,
 * published), read with one query each. array('kind' => 'comment',
 * 'answer' => null) before the columns or for no such comment.
 *
 * @param mixed $comment_id
 * @return array
 */
function pg_product_question_state($comment_id)
{
    $state = array('kind' => 'comment', 'answer' => null);
    $id = (is_scalar($comment_id) && !is_bool($comment_id)) ? (int) $comment_id : 0;

    if (($id <= 0) || !pg_product_questions_ready()) {
        return $state;
    }

    $kind = db_value("SELECT kind FROM comments WHERE id = '" . $id . "'");
    $state['kind'] = in_array($kind, array('question', 'answer'), true) ? $kind : 'comment';

    if ($state['kind'] === 'question') {
        $answer = db_item(
            "SELECT id, message, published
            FROM comments
            WHERE (answer_of = '" . $id . "') AND (kind = 'answer')
            ORDER BY id ASC
            LIMIT 1");
        $state['answer'] = is_array($answer) && $answer ? $answer : null;
    }

    return $state;
}

/**
 * Write, change or remove the staff answer to a question: a published
 * comment of kind 'answer' on the question's thread whose answer_of and
 * parent_id name it. An empty text removes the answer, so the question waits
 * again. Answers 'added', 'changed', 'removed' or '' when nothing was
 * written (not a question, before the columns, a failed query).
 *
 * @param mixed  $question_id
 * @param mixed  $text    pg_product_question_answer_text()
 * @param int    $user_id the member of staff
 * @param string $name    the name the answer shows
 * @return string
 */
function pg_product_question_answer_save($question_id, $text, $user_id, $name)
{
    $text = pg_product_question_answer_text($text);
    $id = (is_scalar($question_id) && !is_bool($question_id)) ? (int) $question_id : 0;

    if (($id <= 0) || !pg_product_questions_ready()) {
        return '';
    }

    $question = db_item("SELECT id, page_id, item_type, item_id, kind FROM comments WHERE id = '" . $id . "'");

    if (!is_array($question) || !$question || ($question['kind'] !== 'question')) {
        return '';
    }

    $state = pg_product_question_state($id);
    $answer = $state['answer'];

    if ($text === '') {
        if ($answer === null) {
            return '';
        }

        $done = db_q("DELETE FROM comments WHERE (id = ?) AND (kind = 'answer')", array((string) (int) $answer['id']));

        return ($done !== false) ? 'removed' : '';
    }

    if ($answer !== null) {
        if ((string) $answer['message'] === $text) {
            return '';
        }

        $done = db_q(
            "UPDATE comments
            SET message = ?, last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE (id = ?) AND (kind = 'answer')",
            array($text, (string) (int) $user_id, (string) (int) $answer['id']));

        return ($done !== false) ? 'changed' : '';
    }

    $done = db_q(
        "INSERT INTO comments (
            page_id, item_id, item_type, name, message, published,
            created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp,
            kind, answer_of" . (pg_comment_replies_ready() ? ", parent_id" : "") . ")
        VALUES (?, ?, ?, ?, ?, '1', ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP(), 'answer', ?" . (pg_comment_replies_ready() ? ", ?" : "") . ")",
        array_merge(
            array((string) $question['page_id'], (string) $question['item_id'], (string) $question['item_type'], (string) $name, $text,
                (string) (int) $user_id, (string) (int) $user_id, (string) $id),
            pg_comment_replies_ready() ? array((string) $id) : array()));

    return ($done !== false) ? 'added' : '';
}
