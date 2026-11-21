<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail on its Mailboxes tab: the boxes whose e-mail is
 * read in Communication › Incoming e-mail. The tab opens for a holder of the
 * Workspace settings right, whatever the Workspace's switch (gate 'mail').
 * The old address of the category that became that tab
 * (pg_settings_moved_categories()); Bring in an .eml file posts here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

define('PG_SETTINGS_KEY', 'mailboxes');

include('includes/settings/screen.php');
