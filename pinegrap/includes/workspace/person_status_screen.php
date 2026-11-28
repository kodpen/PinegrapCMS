<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The screens of a person's own status (includes/fn/person_status.php):
 * what workspace.js needs to draw the Status dialog, the mark beside a name,
 * the person card's line and the composer's line, and the row of the panel's
 * account menu that opens the dialog. Only a team member sees a status; a
 * guest and the guest page never.
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
 * The icon and tone of each kind, in the order the kinds are offered. Pure.
 *
 * @return array kind => array(icon, tone)
 */
function ws_person_status_looks()
{
    return array(
        'available' => array('bi-check-circle', 'teal'),
        'busy'      => array('bi-dash-circle', 'rose'),
        'meeting'   => array('bi-people', 'blue'),
        'out'       => array('bi-door-open', 'amber'),
        'sleeping'  => array('bi-moon', 'violet'),
        'leave'     => array('bi-airplane', 'gray'),
    );
}

/**
 * The ends the dialog's "Clear after" offers, as [value, label] pairs, in
 * the order of pg_person_status_ends() but for 'at' last. Pure.
 *
 * @return array[]
 */
function ws_person_status_end_options()
{
    return array(
        array('none', lang('Do not clear')),
        array('30m', lang('30 minutes')),
        array('1h', lang('1 hour')),
        array('4h', lang('4 hours')),
        array('today', lang('Today')),
        array('morning', lang('Until morning')),
        array('at', lang('Choose a time…')),
    );
}

/**
 * The kinds as the script draws them: kind, label, icon, tone and the end
 * the kind offers first. Pure.
 *
 * @return array[]
 */
function ws_person_status_kinds_js()
{
    $looks = ws_person_status_looks();
    $out = array();

    foreach (pg_person_status_kinds() as $kind => $label) {
        $out[] = array(
            'kind'  => $kind,
            'label' => $label,
            'icon'  => $looks[$kind][0] ?? 'bi-circle',
            'tone'  => $looks[$kind][1] ?? 'gray',
            'end'   => pg_person_status_default_end($kind),
        );
    }

    return $out;
}

/**
 * The status block of the script's settings: null for anyone who is not a
 * team member and before the column exists (nothing is drawn then).
 *
 * @param array $viewer ws_viewer()
 * @return array|null kinds, ends, max, mine
 */
function ws_person_status_js_config($viewer)
{
    if (empty($viewer['member']) || !function_exists('ws_person_status_ready') || !ws_person_status_ready()) {
        return null;
    }

    return array(
        'kinds' => ws_person_status_kinds_js(),
        'ends'  => ws_person_status_end_options(),
        'max'   => PG_PERSON_STATUS_NOTE_MAX,
        'mine'  => ws_person_status_get((int) $viewer['id']),
    );
}

/**
 * The texts of the status screens in workspace.js.
 *
 * @return array key => text
 */
function ws_person_status_js_strings()
{
    return array(
        'ps_title'       => lang('Status'),
        'ps_kinds'       => lang('Your status'),
        'ps_note'        => lang('Note'),
        'ps_note_hint'   => lang('What should people know?'),
        'ps_clear_after' => lang('Clear after'),
        'ps_at'          => lang('Date and time'),
        'ps_clear'       => lang('Clear status'),
        'ps_cancel'      => lang('Cancel'),
        'ps_save'        => lang('Save'),
        'ps_set'         => lang('Set a status'),
        'ps_change'      => lang('Change'),
        'ps_saved'       => lang('Your status is saved.'),
        'ps_cleared'     => lang('Your status is cleared.'),
        'ps_more'        => ws_js_template('and {var:1} more', 1),
    );
}

/**
 * The account menu's row of the status, above My Account: "Set a status",
 * or the status itself (icon, label, its end) once one is set. It is a link
 * to the channel screen, which opens the dialog (?panel=status); on a screen
 * that runs workspace.js the dialog opens in place. '' for anyone who is not
 * a team member, with the Workspace off, or before the column exists.
 *
 * @param array  $workspace ws_rights() of the signed-in user, or null
 * @param int    $user_id
 * @param string $software_url
 * @param string $extra      the row's attributes beyond the address
 * @return string
 */
function ws_person_status_menu_row($workspace, $user_id, $software_url, $extra = '')
{
    if (empty($workspace['member']) || !function_exists('ws_person_status_ready') || !function_exists('ws_enabled') || !ws_enabled() || !ws_person_status_ready()) {
        return '';
    }

    return ws_person_status_menu_markup(ws_person_status_get((int) $user_id), $software_url . '/workspace.php?panel=status', $extra);
}

/**
 * The markup of the account menu's status row. Pure.
 *
 * @param array|null $status pg_person_status_present()
 * @param string     $href
 * @param string     $extra
 * @return string
 */
function ws_person_status_menu_markup($status, $href, $extra = '')
{
    $looks = ws_person_status_looks();

    if (is_array($status) && isset($looks[$status['kind'] ?? ''])) {
        $look = $looks[$status['kind']];
        $words = trim((string) $status['label'] . ' ' . (string) ($status['until_words'] ?? ''));
        $icon = '<i class="bi ' . $look[0] . ' ws-person-status-ink pg-tone-' . $look[1] . '" aria-hidden="true"></i>';
        $text = h($words);
    } else {
        $icon = '<i class="bi bi-emoji-smile" aria-hidden="true"></i>';
        $text = h(lang('Set a status'));
    }

    return '
                            <li><a class="menu-item" href="' . h($href) . '" data-pg-person-status' . $extra . '>' . $icon . '<span class="pg-um-text">' . $text . '</span></a></li>';
}
