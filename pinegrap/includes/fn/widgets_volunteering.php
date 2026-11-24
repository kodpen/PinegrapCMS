<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The my_account widget's "volunteering" section: the signed-in member's
 * volunteer programmes (includes/fn/volunteer_page_view.php) in the site's
 * look, Bootstrap 5.3.8. Loaded by _pg_my_account_extras() when the member
 * has a volunteer profile.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_page_view.php');

/**
 * The classes of the site's look (the my account section). Pure.
 *
 * @return array
 */
function pg_volunteer_page_site_classes()
{
    return array(
        'box'    => 'card mb-4 pg-sw-volunteering',
        'body'   => 'card-body',
        'list'   => 'list-group mb-3',
        'item'   => 'list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2',
        'meta'   => 'small text-body-secondary',
        'pill'   => 'badge text-bg-',
        'tones'  => array('ok' => 'success', 'wait' => 'warning', 'info' => 'info', 'off' => 'secondary'),
        'quiet'  => 'btn btn-sm btn-outline-secondary',
        'fill'   => 'btn btn-sm btn-primary',
        'danger' => 'btn btn-sm btn-outline-danger',
    );
}

/**
 * The member's volunteer profiles as the volunteer's page sees them.
 *
 * @param array $ids pg_volunteer_account_ids()
 * @return array pg_volunteer_self_view() each
 */
function pg_volunteer_account_views(array $ids)
{
    $now = date('Y-m-d H:i:s');
    $views = array();

    foreach ($ids as $id) {
        $view = pg_volunteer_self_view($id, $now);

        if ($view !== null) {
            $views[] = $view;
        }
    }

    return $views;
}

/**
 * The my account "volunteering" section (the site's look): the member's
 * programmes, each button posting to volunteer.php, which sends the member
 * back to $back.
 *
 * @param array  $views pg_volunteer_account_views()
 * @param string $back  the page's own path
 * @return string HTML, '' without a programme
 */
function pg_volunteer_account_html(array $views, $back)
{
    return pg_volunteer_page_html($views, array(
        'action' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer.php',
        'hidden' => get_token_field() . '<input type="hidden" name="send_to" value="' . h($back) . '">',
    ), pg_volunteer_page_site_classes());
}

/**
 * A sign-up's state as one word: signed, offered, asked (offered and
 * another volunteer asked to take it), waiting (the waitlist) or swap
 * (waiting for the coordinator to approve taking an offered place). Pure.
 *
 * @param array $signup state, offered_at, swap_from; asked
 * @return string
 */
function pg_volunteer_signup_state(array $signup)
{
    if ((string) $signup['state'] === 'waiting') {
        return ((int) $signup['swap_from'] > 0) ? 'swap' : 'waiting';
    }

    if ((int) $signup['offered_at'] > 0) {
        return !empty($signup['asked']) ? 'asked' : 'offered';
    }

    return 'signed';
}

/**
 * The rows of the account.volunteering list, as pg_sw_list_publish('account')
 * files them: array('account.volunteering' => array($account_id => rows)),
 * one row per coming shift the member signed up or waits for, in every
 * programme, soonest first. Plain words, escaped once by the pass. The
 * buttons stay the section's own.
 *
 * @param int   $account_id
 * @param array $views pg_volunteer_account_views()
 * @return array
 */
function pg_sw_list_account_volunteering_rows($account_id, array $views)
{
    $tones = array('ok' => 'success', 'wait' => 'warning', 'info' => 'info');
    $rows = array();

    foreach ($views as $view) {
        foreach ($view['mine'] as $signup) {
            $shift = $signup['shift'];
            $start = strtotime($shift['start']);
            $label = pg_volunteer_signup_label($signup);
            $rows[] = array(
                'start' => (string) $shift['start'],
                'row'   => array(
                    '__volunteering_programme'   => (string) $view['programme']['name'],
                    '__volunteering_when'        => pg_volunteer_shift_when($shift),
                    '__volunteering_date'        => get_absolute_time(array('timestamp' => $start, 'type' => 'date', 'format' => 'plain_text')),
                    '__volunteering_time'        => date('H:i', $start) . '–' . date('H:i', strtotime($shift['end'])),
                    '__volunteering_place'       => (string) $shift['place'],
                    '__volunteering_role'        => pg_volunteer_role_name($view['programme'], $signup['role']),
                    '__volunteering_state'       => pg_volunteer_signup_state($signup),
                    '__volunteering_state_label' => (string) $label[0],
                    '__volunteering_tone'        => $tones[$label[1]],
                ),
            );
        }
    }

    // Stable: rows of the same start keep their programme's order.
    $order = array_keys($rows);
    array_multisort(array_column($rows, 'start'), SORT_ASC, SORT_STRING, $order, SORT_ASC, $rows);

    return array('account.volunteering' => array((int) $account_id => array_column($rows, 'row')));
}
