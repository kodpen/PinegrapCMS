<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The events of prototype review: prototype.approved and
 * prototype.changes_requested for a guest's "Approve this page" or "Request
 * changes" (pg_proto_verdict_take()), and prototype.commented for a guest's
 * comment through the comment door (prototype_comment.php). A designer's
 * answer is announced by nothing.
 *
 * A guest's name, note and comment are text from outside the team: they
 * travel as data, with no tag or markup (pg_proto_comment_task_words()), so a
 * flow that writes them into a channel or a task cannot mention a person or
 * draw a record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A guest's words as an event or a read route carries them: no tag, no
 * markup, no block start (pg_proto_comment_task_words()); a name on one
 * line. Pure.
 *
 * @param string $text
 * @param bool   $multiline
 * @return string
 */
function pg_proto_event_words($text, $multiline)
{
    $words = pg_proto_comment_task_words($text);

    return $multiline ? $words : trim(preg_replace('/\s+/u', ' ', $words));
}

/**
 * A time as an event carries it: UTC, ISO 8601; null for none. Pure.
 *
 * @param int $unix
 * @return string|null
 */
function pg_proto_event_time($unix)
{
    return ((int) $unix > 0) ? gmdate('Y-m-d\TH:i:s\Z', (int) $unix) : null;
}

/**
 * A verdict's payload (api_webhook_event_payloads_site()). Pure.
 *
 * @param array  $row       the saved prototype_verdicts row
 * @param string $page_name
 * @return array
 */
function pg_proto_verdict_event_payload($row, $page_name)
{
    return array(
        'id'         => (int) $row['id'],
        'design_id'  => (int) $row['style_id'],
        'page_id'    => (int) $row['page_id'],
        'page_name'  => (string) $page_name,
        'link_id'    => (int) $row['link_id'],
        'verdict'    => ((string) $row['verdict'] === 'approved') ? 'approved' : 'changes_requested',
        'name'       => pg_proto_event_words($row['author_name'], false),
        'note'       => pg_proto_event_words($row['note'], true),
        'created_at' => pg_proto_event_time($row['created_at']),
    );
}

/**
 * Announces a saved verdict: to the webhook subscribers and the Workspace's
 * flows (pg_announce(), silent when neither is there).
 *
 * @param array  $row       the saved prototype_verdicts row
 * @param string $page_name
 * @return void
 */
function pg_proto_verdict_announce($row, $page_name)
{
    if (!is_array($row) || !function_exists('pg_announce')) {
        return;
    }

    $approved = ((string) $row['verdict'] === 'approved');

    pg_announce($approved ? 'prototype.approved' : 'prototype.changes_requested', pg_proto_verdict_event_payload($row, $page_name));
}

/**
 * Whether a saved comment is announced: only a guest's, never a designer's
 * answer. Pure.
 *
 * @param array|null $row prototype_comments row
 * @return bool
 */
function pg_proto_comment_event_due($row)
{
    return is_array($row) && ((int) $row['id'] > 0) && ((int) $row['user_id'] === 0);
}

/**
 * A guest comment's payload (api_webhook_event_payloads_site()). Pure.
 *
 * @param array  $row       the saved prototype_comments row
 * @param string $page_name
 * @return array
 */
function pg_proto_comment_event_payload($row, $page_name)
{
    return array(
        'id'         => (int) $row['id'],
        'design_id'  => (int) $row['style_id'],
        'page_id'    => (int) $row['page_id'],
        'page_name'  => (string) $page_name,
        'link_id'    => (int) $row['link_id'],
        'parent_id'  => (int) $row['parent_id'],
        'node'       => (string) $row['node_id'],
        'name'       => pg_proto_event_words($row['author_name'], false),
        'text'       => pg_proto_event_words($row['body'], true),
        'created_at' => pg_proto_event_time($row['created_at']),
    );
}

/**
 * Announces a guest's saved comment, a new thread or an answer in one.
 *
 * @param array $row the saved prototype_comments row
 * @return void
 */
function pg_proto_comment_announce($row)
{
    if (!pg_proto_comment_event_due($row) || !function_exists('pg_announce')) {
        return;
    }

    $page = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $row['page_id'] . "' LIMIT 1");

    pg_announce('prototype.commented', pg_proto_comment_event_payload($row, $page));
}
