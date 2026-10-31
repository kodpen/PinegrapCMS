<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reading the site's Google Analytics 4 figures for the dashboard
 * (includes/fn/google_analytics.php).
 *
 * analytics.readonly covers both the Admin API's account summaries, from
 * which the GA4 property is chosen, and the Data API's reports; it cannot
 * change anything in the account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'       => lang('Google Analytics'),
    'description' => lang('Shows the visitors, sessions and views Google Analytics counted on the dashboard.'),
    'owner'       => 'site',
    'scopes'      => array(
        'google' => array(
            'https://www.googleapis.com/auth/analytics.readonly',
        ),
    ),
);
