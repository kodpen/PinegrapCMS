<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Fediverse: the site's account, what it publishes, its
 * followers and blocks, and the delivery log.
 *
 * One of the category screens the settings are split into. Everything the
 * screen does lives in the shared flow and in the two modules named after
 * this key; a wrapper exists because the root of the software holds the
 * files a URL asks for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

define('PG_SETTINGS_KEY', 'fediverse');

include('includes/settings/screen.php');
