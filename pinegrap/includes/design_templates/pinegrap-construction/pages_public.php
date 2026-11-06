<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", its public pages: home, the services
 * and one page per service, sectors, the process, safety and quality,
 * about, the team and the questions page. Included after parts.php in the
 * same scope (its builders and the firm's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($firm, $services, $sectors,
 * $projects, $process, $team, $safety_points, $accreditations, $why_us,
 * $figures, $testimonials), so a change made there reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

return array('pages' => $public_pages, 'widgets' => array());
