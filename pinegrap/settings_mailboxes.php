<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Mailboxes: the boxes whose e-mail is read in Communication
 * › Incoming e-mail. Opened by a holder of the Workspace settings right,
 * whatever the Workspace's switch (pg_settings_category_open(), gate
 * 'mail'). Everything the screen does lives in the shared flow and in the
 * two modules named after this key; a wrapper exists because the root of
 * the software holds the files a URL asks for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

define('PG_SETTINGS_KEY', 'mailboxes');

include('includes/settings/screen.php');
