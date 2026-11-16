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

    $id = isset($_POST['proposal_id']) ? (int) $_POST['proposal_id'] : 0;
    $action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';
    $reason = (isset($_POST['reason']) && is_string($_POST['reason'])) ? $_POST['reason'] : '';

    if ($action === 'apply') {
        $result = pg_content_proposals_apply($user, $id);
        $done = lang('The change was applied to the page.');
    } elseif ($action === 'dismiss') {
        $result = pg_content_proposals_dismiss($user, $id, $reason);
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

// The page settings by the names the page settings panel gives them.
$field_labels = array(
    'page_title'            => lang('Title'),
    'page_meta_description' => lang('Description'),
    'page_search_keywords'  => lang('Keywords'),
);

// A value as the comparison shows it; a picture of the site's own files
// with its small image beside the address.
$show = function ($value, $picture = false) {
    $text = (is_scalar($value) || ($value === null)) ? trim(strip_tags((string) $value)) : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

    if ($text === '') {
        return '<span class="pg-text-meta">' . h(lang('(empty)')) . '</span>';
    }

    $thumb = ($picture && pg_content_proposals_local_picture($text))
        ? '<img src="' . h($text) . '" alt="" height="64" loading="lazy" class="d-block mb-1 rounded">' : '';

    return $thumb . '<span class="text-break">' . h(mb_strimwidth($text, 0, 600, '…')) . '</span>';
};

// One change of the comparison: its kind, what the site shows, what is
// proposed. Side by side from md up, one under the other on a phone.
$compare_line = function ($label, $was, $now, $picture = false) use ($show) {
    return '<div class="row g-2 py-2 border-top">'
        . '<div class="col-12 md:col-2 pg-text-2"><strong>' . h($label) . '</strong></div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('On the site')) . '</div>' . $show($was, $picture) . '</div>'
        . '<div class="col-12 md:col-5"><div class="pg-text-meta">' . h(lang('Proposed')) . '</div>' . $show($now, $picture) . '</div>'
        . '</div>';
};

$form = function ($row, $action, $label, $class) {
    return '<form method="post">' . get_token_field()
        . '<input type="hidden" name="proposal_id" value="' . (int) $row['id'] . '"/>'
        . '<input type="hidden" name="action" value="' . h($action) . '"/>'
        . '<button type="submit" class="pg-button ' . $class . ' pg-button-sm">' . h($label) . '</button></form>';
};

// Dismiss opens its own reason field, so the field reads as Dismiss's.
$dismiss = function ($row) {
    $field = 'reason_' . (int) $row['id'];

    return '<details class="flex-grow-1"><summary class="pg-button pg-button-quiet pg-button-sm d-inline-flex">' . h(lang('Dismiss')) . '</summary>'
        . '<form method="post" class="mt-2">' . get_token_field()
        . '<input type="hidden" name="proposal_id" value="' . (int) $row['id'] . '"/><input type="hidden" name="action" value="dismiss"/>'
        . '<label class="pg-field-label" for="' . $field . '">' . h(lang('Reason')) . '</label>'
        . '<input type="text" name="reason" id="' . $field . '" class="pg-input" maxlength="' . PG_CONTENT_PROPOSALS_REASON_MAX . '" aria-describedby="' . $field . '_help"/>'
        . '<div class="pg-text-meta" id="' . $field . '_help">' . h(lang(array('string' => 'Optional, at most {var:1} characters.', 'vars' => array(PG_CONTENT_PROPOSALS_REASON_MAX)))) . '</div>'
        . '<button type="submit" class="pg-button pg-button-danger pg-button-sm mt-2">' . h(lang('Dismiss the change')) . '</button></form></details>';
};

$rows_html = '';

foreach (pg_content_proposals_order(pg_content_proposals_list(), time()) as $row) {
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
            $lines .= $compare_line(lang('Picture'), $op['was'], $op['src'], true);
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

    $actions = '';

    if ($status === 'pending') {
        $actions .= $form($row, 'apply', lang('Apply'), 'pg-button-fill');
    }

    if (in_array($status, array('pending', 'stale'), true)) {
        $actions .= $dismiss($row);
    }

    if (($status === 'applied') && ((int) $row['decided_by'] === (int) $user['id'])) {
        $actions .= $form($row, 'revert', lang('Take back the change'), 'pg-button-quiet');
    }

    $note = '';
    $decider = (string) $row['decider_name'];

    if ($status === 'stale') {
        $note = lang('The page was changed after this change was saved; it can only be dismissed.');
    } elseif (($status === 'applied') && ((int) $row['decided_by'] === (int) $user['id'])) {
        // The one who applied it has the button; nobody else may take it back.
        $note = lang(array('string' => 'Applied by {var:1}.', 'vars' => array($decider)));
    } elseif ($status === 'applied') {
        $note = lang(array('string' => 'Applied by {var:1}. Only they can take it back.', 'vars' => array($decider)));
    } elseif ($status === 'reverted') {
        $note = lang(array('string' => 'Applied and taken back by {var:1}.', 'vars' => array($decider)));
    } elseif ($status === 'dismissed') {
        $note = !empty($steps['reason'])
            ? lang(array('string' => 'Dismissed by {var:1}: {var:2}', 'vars' => array($decider, (string) $steps['reason'])))
            : lang(array('string' => 'Dismissed by {var:1}.', 'vars' => array($decider)));
    }

    $page_link = ((int) $row['page_style'] > 0)
        ? '<a href="edit_system_style.php?id=' . (int) $row['page_style'] . '&amp;page=' . (int) $row['page_id'] . '">' . h((string) $row['page_name']) . '</a>'
        : h((string) $row['page_name']);

    $rows_html .= '<div class="pg-list-row is-read"><span class="pg-initials" aria-hidden="true">' . h(mb_strtoupper(mb_substr((string) $row['editor_name'], 0, 2))) . '</span><div class="pg-list-row-main">'
        . '<div class="pg-list-row-title text-break">' . $page_link . ' <span class="pg-list-row-title-verb">' . h(lang(array('string' => 'by {var:1}', 'vars' => array((string) $row['editor_name'])))) . '</span></div>'
        . '<div class="d-flex flex-wrap align-items-center gap-2"><span class="pg-pill ' . $tone[0] . '">' . h($tone[1]) . '</span>'
        . '<span class="pg-text-meta">' . get_relative_time(array('timestamp' => (int) $row['created_at'])) . '</span>'
        . '<span class="pg-text-meta">' . h(lang(array('string' => '{var:1} changes', 'vars' => array(is_array($ops) ? count($ops) : 0)))) . '</span></div>'
        . (($note !== '') ? '<div class="pg-list-row-text">' . h($note) . '</div>' : '')
        . '<details><summary class="pg-text-2">' . h(lang('Compare')) . '</summary><div class="mt-2">' . $lines . '</div></details>'
        . (($actions !== '') ? '<div class="pg-list-row-actions d-flex flex-wrap align-items-start gap-2">' . $actions . '</div>' : '')
        . '</div></div>';
}

if ($rows_html === '') {
    $rows_html = '<p class="pg-text-2 p-4 mb-0">' . h(lang('No content change is waiting.')) . '</p>';
}

// Changes already waiting stay here when the switch is turned off; new
// saves go live again.
$off = pg_content_proposals_on() ? '' : '<div class="alert theme-info" role="status">'
    . h(lang('"Content changes wait for approval" is off, so new saves go live at once. Changes that were already waiting stay here.'))
    . ' <a href="settings_features.php#pgset-content-approval">' . h(lang('Site Settings')) . '</a></div>';

echo
pg_page_shell(array(
        'title'               => lang('Waiting Content Changes'),
        'icon'                => 'page',
        'help'                => 'visual-page-editor#editor',
        'heading'             => lang('Waiting Content Changes'),
        'heading_description' => lang('Changes that wait for a designer.'),
    )) . '
<main id="content" class="container">
    <h2 class="pg-title-screen md:d-none">' . h(lang('Waiting Content Changes')) . '</h2>
    ' . ($said ? '<div class="alert ' . ($said['ok'] ? 'theme-success' : 'theme-danger') . '" role="status">' . h($said['text']) . '</div>' : '') . $off . '
    <div class="pg-card">' . $rows_html . '</div>
</main>
' .
output_footer();
