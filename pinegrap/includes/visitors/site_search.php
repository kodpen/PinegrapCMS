<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Search section of the Visitors overview: what visitors searched
 * for on the site over the overview's period, the terms that found nothing
 * and how often a result was clicked (site_search_daily), drawn by
 * pg_site_search_report() (includes/fn/site_search_stats.php). A manager
 * gets a button per term that found nothing to promote a result for it.
 * Nothing is drawn before the table exists (pg_site_search_stats_ready()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * The section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML; '' before the site search table exists
 */
function pg_visitors_section_site_search(array $period, array $user)
{
    if (!pg_site_search_stats_ready()) {
        return '';
    }

    $sections = pg_visitors_sections();

    // The Search Tuning card is a site setting: managers and up.
    $may_tune = ((int) $user['role'] < 3);

    return pg_visitors_section('site_search', $sections['site_search'], pg_site_search_report(pg_site_search_figures($period['from'], $period['to']), $may_tune));
}
