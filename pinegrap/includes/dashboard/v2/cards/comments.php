<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Comment moderation card: the comments waiting for approval as
 * bubbles (who, what, on which page, when) with an Approve button that
 * opens the comment where it is published, and the count of the whole
 * queue. The queue is widget 25's (pg_dashboard_waiting_comments()), so
 * both dashboards count the same comments for the same person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * A stored comment body as a one-line preview: tags dropped, entities
 * read, spaces folded, cut at $max characters with an ellipsis.
 *
 * @param string $html
 * @param int    $max
 * @return string
 */
function pg_dashboard_v2_comment_preview($html, $max = 90)
{
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    if (mb_strlen($text) > $max) {
        $text = rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    return $text;
}

/**
 * Up to two initials for a name, upper-cased; '?' for an empty one.
 *
 * @param string $name
 * @return string
 */
function pg_dashboard_v2_initials($name)
{
    $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';

    foreach (array_slice($words, 0, 2) as $word) {
        $out .= mb_substr($word, 0, 1);
    }

    return ($out === '') ? '?' : mb_strtoupper($out);
}

function pg_dashboard_v2_card_comments($user, $period, $compare)
{
    if (((int) $user['role'] >= 3) && !no_acl_check($user['id'])) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_25.php');

    $waiting = pg_dashboard_waiting_comments($user, 3);
    $back = PATH . SOFTWARE_DIRECTORY . '/view_comments.php';
    $rows = array();

    foreach ($waiting['items'] as $item) {
        $name = pg_comment_display_name($item['name']);
        $where = ((string) $item['page_name'] !== '') ? (string) $item['page_name'] : lang('Unknown');

        $rows[] = array(
            'id'       => (int) $item['id'],
            'initials' => pg_dashboard_v2_initials($name),
            'name'     => $name,
            'text'     => pg_dashboard_v2_comment_preview($item['message']),
            'rating'   => min(5, max(0, (int) $item['rating'])),
            'note'     => $where . ' · ' . get_relative_time(array('timestamp' => (int) $item['created_timestamp'], 'format' => 'plain_text')),
            'url'      => 'edit_comment.php?id=' . (int) $item['id'] . '&send_to=' . rawurlencode($back),
        );
    }

    $total = (int) $waiting['total'];
    $more = $total - count($rows);

    return array('status' => 'success', 'data' => array(
        'count'   => $total,
        'title'   => lang('Waiting for approval'),
        'rows'    => $rows,
        'approve' => lang('Approve'),
        'rating'  => lang('{var:1} out of 5'),
        'more'    => ($more > 0) ? lang(array('string' => '{var:1} more waiting', 'vars' => pg_format_number($more, 0))) : '',
        'empty'   => ($total === 0) ? lang('No comments are waiting for approval.') : '',
        'url'     => 'view_comments.php',
        'link'    => lang('All comments'),
    ));
}
