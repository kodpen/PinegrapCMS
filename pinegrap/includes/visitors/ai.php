<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The AI Visibility section of the Visitors overview: which AI assistants
 * and AI search indexers fetched the site's pages over the overview's
 * period, the visits their answers sent and the sitemap pages none of them
 * read, drawn by pg_visitor_ai_section() (includes/fn/visitor_insights.php).
 * Nothing is drawn before the AI fetch table exists
 * (pg_ai_visibility_ready()).
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
 * @return string HTML; '' before the AI fetch table exists
 */
function pg_visitors_section_ai(array $period, array $user)
{
    if (!pg_ai_visibility_ready()) {
        return '';
    }

    $sections = pg_visitors_sections();

    return pg_visitors_section('ai', $sections['ai'], pg_visitor_ai_section($period, $user));
}
