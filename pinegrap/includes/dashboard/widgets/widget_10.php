<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 10 - retired (Contacts); the id answers with no content.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_10($request, $user)
{
    // Retired. Contacts are a kind in the Activity feed (7) now, under the same gate.
    //
    // The id is answered rather than removed for the upgrade window, as 24
    // is: a tab left open across the update still holds the old card and
    // asks for it, and an empty success clears its skeleton where an
    // unknown id would leave it spinning until reload. The id is not reused.
    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => '',
    );
}
