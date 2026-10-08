<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 24 - empty slot; the id is retired and answers with no content.
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

function pg_dashboard_widget_24($request, $user)
{

    // Empty slot. The activity summary that lived here counted
    // orders, forms and contacts over eight days -- twenty-four
    // COUNT queries per dashboard load -- and showed all three to
    // anyone holding manage_ecommerce, although the forms and
    // contacts widgets are gated on manage_forms and
    // manage_contacts. Each figure now heads the widget it belongs
    // to, behind that widget's own permission.
    //
    // The id is answered rather than removed because of the
    // upgrade window. welcome.php no longer prints a card for 2, so
    // a fresh page never asks -- but a tab left open across the
    // update still holds the old card and asks on its first run.
    // An unknown id is answered with 'Invalid widget id', and
    // the dashboard only replaces a placeholder on status success,
    // so that card would sit on its skeleton until reload. An empty
    // success clears it instead.
    $response = array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => '',
    );
    return $response;
}
