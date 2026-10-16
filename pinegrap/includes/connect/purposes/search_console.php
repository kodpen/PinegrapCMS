<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reading the site's Google Search Console figures, verifying the site and
 * submitting its sitemap (search_console.php; includes/fn/search_console.php).
 *
 * webmasters reads the figures, adds the property and submits the sitemap;
 * siteverification.verify_only verifies a site the account has not added yet
 * with a meta tag, and cannot read or change any other verification.
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
    'label'       => lang('Google Search Console'),
    'description' => lang('Shows which searches bring people to the site, verifies the site with Google and submits its sitemap.'),
    'owner'       => 'site',
    'scopes'      => array(
        'google' => array(
            'https://www.googleapis.com/auth/webmasters',
            'https://www.googleapis.com/auth/siteverification.verify_only',
        ),
    ),
);
