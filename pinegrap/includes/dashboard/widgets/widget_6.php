<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 6 - retired (Trending Content); the id answers with no content.
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

function pg_dashboard_widget_6($request, $user)
{
    // Retired. Its two lists moved into the cards they belong with: the most visited pages
    // into Visitor Summaries (5), the best sellers into Ecommerce Summary (3).
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
