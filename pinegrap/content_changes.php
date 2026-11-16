<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Waiting content changes: the changes content editors saved on live pages
 * while "Content changes wait for approval" is on (includes/fn/content_proposals.php).
 * A designer compares each with the page, applies it, dismisses it with a
 * reason, or takes back one they applied. For the Designer role and above.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// An action, then back to the list with what it did.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    $id = (int) post_value('proposal_id');
    $action = (string) post_value('action');

    if ($action === 'apply') {
        $result = pg_content_proposals_apply($user, $id);
        $done = lang('The change was applied to the page.');
    } elseif ($action === 'dismiss') {
        $result = pg_content_proposals_dismiss($user, $id, (string) post_value('reason'));
        $done = lang('The change was dismissed.');
    } elseif ($action === 'revert') {
        $result = pg_content_proposals_revert($user, $id);
        $done = lang('The change was taken back.');
    } else {
        $result = array('ok' => false, 'error' => lang('Unknown action.'));
        $done = '';
    }

    $_SESSION['software']['content_changes_said'] = array('ok' => $result['ok'], 'text' => $result['ok'] ? $done : $result['error']);
    go(PATH . SOFTWARE_DIRECTORY . '/content_changes.php');
}

$said = isset($_SESSION['software']['content_changes_said']) ? $_SESSION['software']['content_changes_said'] : null;
unset($_SESSION['software']['content_changes_said']);

$tones = array(
    'pending'   => array('pg-tone-amber', lang('Waiting')),
    'stale'     => array('pg-tone-rose', lang('Page changed since')),
    'applied'   => array('pg-tone-teal', lang('Applied')),
    'reverted'  => array('pg-tone-blue', lang('Taken back')),
    'dismissed' => array('pg-tone-gray', lang('Dismissed')),
);

// One line of the comparison: what the page holds and what the editor saved.
$compare_line = function ($label, $was, $now) {
    $show = function ($value) {
        $value = is_scalar($value) || ($value === null) ? trim(strip_tags((string) $value)) : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        return ($value === '') ? '<span class="pg-text-meta">' . h(lang('(empty)')) . '</span>' : h(mb_strimwidth($value, 0, 400, '…'));
    };

    return '<tr><th scope="row" class="pg-text-2">' . h($label) . '</th><td>' . $show($was) . '</td><td>' . $show($now) . '</td></tr>';
};

// The page settings by the names the page settings panel gives them.
$field_labels = array(
    'page_title'            => lang('Title'),
    'page_meta_description' => lang('Description'),
    'page_search_keywords'  => lang('Keywords'),
);

$rows_html = '';
$now = time();

foreach (pg_content_proposals_list() as $row) {
    if (!pg_content_proposals_listed($row, $now)) {
        continue;
    }

    $status = (string) $row['status'];
    $tone = isset($tones[$status]) ? $tones[$status] : array('pg-tone-gray', $status);
    $ops = json_decode((string) $row['ops'], true);
    $steps = json_decode((string) $row['steps'], true);
    $lines = '';

    foreach (is_array($ops) ? $ops : array() as $op) {
        $name = (string) ($op['op'] ?? '');

        if ($name === 'text') {
            $lines .= $compare_line(lang('Text'), $op['was'], $op['text']);
        } elseif ($name === 'src') {
            $lines .= $compare_line(lang('Picture'), $op['was'], $op['src']);
        } elseif ($name === 'note') {
            $lines .= $compare_line(lang('Note'), $op['was'], $op['note']);
        } elseif ($name === 'area') {
            $lines .= $compare_line(lang('Editable area'), lang('As on the site'), lang('Rewritten by the editor'));
        } elseif ($name === 'tree') {
            $lines .= $compare_line(lang('Page'), lang('As on the site'), lang('Rewritten by the editor'));
        } elseif ($name === 'fields') {
            foreach ((array) $op['fields'] as $key => $value) {
                $lines .= $compare_line($field_labels[$key] ?? $key, $op['was'][$key] ?? '', $value);
            }
        }
    }

    $form = function ($action, $label, $class, $extra = '') use ($row) {
        return '<form method="post" class="d-flex flex-wrap align-items-center gap-2">' . get_token_field()
            . '<input type="hidden" name="proposal_id" value="' . (int) $row['id'] . '"/>'
            . '<input type="hidden" name="action" value="' . h($action) . '"/>' . $extra
            . '<button type="submit" class="pg-button ' . $class . ' pg-button-sm">' . h($label) . '</button></form>';
    };

    $actions = '';

    if ($status === 'pending') {
        $actions .= $form('apply', lang('Apply'), 'pg-button-fill');
    }

    if (in_array($status, array('pending', 'stale'), true)) {
        $actions .= $form('dismiss', lang('Dismiss'), 'pg-button-quiet',
            '<input type="text" name="reason" class="pg-input" maxlength="' . PG_CONTENT_PROPOSALS_REASON_MAX . '" placeholder="' . h(lang('Reason (optional)')) . '" aria-label="' . h(lang('Reason')) . '"/>');
    }

    if (($status === 'applied') && ((int) $row['decided_by'] === (int) $user['id'])) {
        $actions .= $form('revert', lang('Take back'), 'pg-button-ghost');
    }

    $note = '';

    if (($status === 'dismissed') && !empty($steps['reason'])) {
        $note = '<div class="pg-list-row-text">' . h(lang(array('string' => 'Reason: {var:1}', 'vars' => array((string) $steps['reason'])))) . '</div>';
    } elseif ($status === 'stale') {
        $note = '<div class="pg-list-row-text">' . h(lang('The page was changed after this change was saved; it can only be dismissed.')) . '</div>';
    }

    $page_link = ((int) $row['page_style'] > 0)
        ? '<a href="edit_system_style.php?id=' . (int) $row['page_style'] . '&amp;page=' . (int) $row['page_id'] . '">' . h((string) $row['page_name']) . '</a>'
        : h((string) $row['page_name']);

    $rows_html .= '<div class="pg-list-row is-read"><span class="pg-initials" aria-hidden="true">' . h(mb_strtoupper(mb_substr((string) $row['editor_name'], 0, 2))) . '</span><div class="pg-list-row-main">'
        . '<div class="pg-list-row-head"><span class="pg-list-row-title">' . $page_link . ' <span class="pg-list-row-title-verb">'
        . h(lang(array('string' => 'by {var:1}', 'vars' => array((string) $row['editor_name'])))) . '</span></span>'
        . '<span class="pg-pill ' . $tone[0] . '">' . h($tone[1]) . '</span>'
        . '<span class="pg-list-row-time">' . get_relative_time(array('timestamp' => (int) $row['created_at'])) . '</span></div>'
        . $note
        . '<details><summary class="pg-text-2">' . h(lang('Compare')) . '</summary>'
        . '<div class="overflow-auto"><table class="table"><thead><tr><th scope="col"></th><th scope="col">' . h(lang('On the site')) . '</th><th scope="col">' . h(lang('Proposed')) . '</th></tr></thead><tbody>' . $lines . '</tbody></table></div></details>'
        . (($actions !== '') ? '<div class="pg-list-row-actions d-flex flex-wrap gap-2">' . $actions . '</div>' : '')
        . '</div></div>';
}

if ($rows_html === '') {
    $rows_html = '<p class="pg-text-2">' . h(lang('No content change is waiting.')) . '</p>';
}

echo
pg_page_shell(array(
        'title'               => lang('Waiting Content Changes'),
        'icon'                => 'page',
        'help'                => 'visual-page-editor#editor',
        'heading'             => lang('Waiting Content Changes'),
        'heading_description' => lang('Changes Managers and Users saved on pages already on the site. Visitors see them once they are applied.'),
    )) . '
<main id="content" class="container">
    ' . ($said ? '<div class="alert ' . ($said['ok'] ? 'theme-success' : 'theme-danger') . '" role="status">' . h($said['text']) . '</div>' : '') . '
    <div class="pg-card">' . $rows_html . '</div>
</main>
' .
output_footer();
