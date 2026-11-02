<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The My notes card: one plain-text box, at most 2,000 characters, saved as
 * the person types (dashboard_personal_save, includes/dashboard/v2/
 * personal.php) and kept in their layout.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');

function pg_dashboard_v2_card_notes($user, $period, $compare)
{
    return array('status' => 'success', 'data' => array(
        'text'   => pg_dashboard_v2_notes_clean(pg_dashboard_v2_personal($user, 'notes')),
        'most'   => PG_DASHBOARD_V2_MAX_NOTES,
        'locked' => pg_dashboard_v2_locked_for($user),
        'labels' => array(
            'label'   => lang('My notes'),
            'hint'    => lang('Only you see these notes.'),
            'saving'  => lang('Saving…'),
            'saved'   => lang('Saved'),
            'left'    => lang('{var:1} characters left'),
            'failed'  => lang('The change could not be saved.'),
        ),
    ));
}
