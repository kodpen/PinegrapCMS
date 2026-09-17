<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the panel theme and the rich-text editor.
 *
 * One of the nine category screens the settings were split into on
 * 2026-09-12. Everything the screen does lives in the shared flow and in the
 * two modules named after this key; a wrapper exists because the root of the
 * software holds the files a URL asks for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

define('PG_SETTINGS_KEY', 'appearance');

include('includes/settings/screen.php');
