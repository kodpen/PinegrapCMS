<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Assistants and agents: links to the assistant cards still kept
 * on the Workspace Settings screen.
 *
 * One of the Workspace categories, opened by a holder of the Workspace
 * settings right (pg_settings_category_open()). Everything the screen does
 * lives in the shared flow and in the two modules named after this key; a
 * wrapper exists because the root of the software holds the files a URL asks
 * for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

define('PG_SETTINGS_KEY', 'workspace_assistants');

include('includes/settings/screen.php');
