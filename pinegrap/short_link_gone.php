<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Where a short link that no longer opens sends its visitor: a one-time link
 * that was used, or a timed one whose time is up (router.php). Said on the
 * site's own error page, with 410 Gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

output_error((($_GET['r'] ?? '') === 'used')
    ? lang('This link has already been used and no longer works.')
    : lang('This link has expired and no longer works.'), 410);
