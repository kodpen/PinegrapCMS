<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor notifications on the published page: a page whose HTML holds the
 * "Notify me" block's mark (data-pg-push) gets assets/js/pg_site_push.js and
 * its settings (the door, the site's worker and its scope, the public VAPID
 * key) while the "Visitor notifications" switch is on. The block carries no
 * script of its own and is hidden in its markup, so with the switch off it
 * draws nothing. The editor's Preview loads the same file from its twin row
 * in _SD_PAGE_SCRIPTS (assets/js/style_designer.src.js). The worker itself
 * is site_push_sw.php. Loaded by get_page_content.php only when the mark is
 * there (or an order's button "Notify me on this device"), and by
 * site_push_sw.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The block's mark as an attribute of its own (not data-pg-push-on and not
 * text): the Preview's row in _SD_PAGE_SCRIPTS uses the same expression.
 * Pure.
 *
 * @return string
 */
function pg_site_push_block_pattern()
{
    return '/\sdata-pg-push(?=[\s=>\/])/i';
}

/**
 * Whether a page's HTML holds a "Notify me" block. Pure.
 *
 * @param string $content page HTML
 * @return bool
 */
function pg_site_push_block_on_page($content)
{
    return stripos((string) $content, 'data-pg-push') !== false
        && (bool) preg_match(pg_site_push_block_pattern(), (string) $content);
}

/**
 * Whether a page's HTML holds the button "Notify me on this device" of an
 * order (pg_site_push_order_button_html()). Pure.
 *
 * @param string $content page HTML
 * @return bool
 */
function pg_site_push_order_on_page($content)
{
    return stripos((string) $content, 'data-pg-push-order') !== false
        && (bool) preg_match('/\sdata-pg-push-order=/i', (string) $content);
}

/**
 * The settings the block's script reads, from the site's path and software
 * directory. The scope is the site's root; the worker answers with
 * Service-Worker-Allowed for it. Pure.
 *
 * @param string $path      the site's path ("/", "/site/")
 * @param string $software  the software directory's name
 * @param string $key       the public VAPID key, base64url
 * @return array  door, worker, scope, key
 */
function pg_site_push_block_settings($path, $software, $key)
{
    $base = (string) $path . (string) $software . '/';

    return array(
        'door'   => $base . 'site_push.php',
        'worker' => $base . 'site_push_sw.php',
        'scope'  => (string) $path,
        'key'    => (string) $key,
    );
}

/**
 * The script and its settings before the page's last </body>, once, for a
 * page holding the block (pg_site_push_block_on_page()) or an order's button
 * (pg_site_push_order_on_page()). Nothing while the
 * switch is off or the site has no VAPID key: the block stays hidden.
 * Asked once every shared component and system widget is drawn in, so the
 * file follows the block wherever it was placed.
 *
 * @param string      $content  page HTML
 * @param array|null  $settings pg_site_push_block_settings(); null reads the
 *                              switch and builds them for this site
 * @param string|null $src      the script's address; null builds it from the
 *                              software directory and the file's time
 * @return string
 */
function pg_site_push_block_inject($content, $settings = null, $src = null)
{
    if (!pg_site_push_block_on_page($content) && !pg_site_push_order_on_page($content)) return $content;
    if (strpos($content, '/assets/js/pg_site_push.js') !== false) return $content;

    if ($settings === null) {
        if (!pg_site_push_enabled()) return $content;

        require_once(PG_FUNCTIONS_DIR . '/includes/push.php');
        $settings = pg_site_push_block_settings(PATH, SOFTWARE_DIRECTORY, pg_push_vapid_public_key());
    }

    if (empty($settings['key'])) return $content;

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_site_push.js?v='
             . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push.js');
    }
    $file = '<script>window.pgSitePushConfig = ' . encode_json($settings) . ';</script>' . "\n"
          . '<script src="' . h($src) . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}

/**
 * The line put before the worker's script: its settings as
 * self.pgSitePushWorker (the door, the site's name, the fixed text shown
 * when a message cannot be read, the site icon). Pure.
 *
 * @param string $door  the door's address
 * @param string $name  the site's name
 * @param string $text  the fixed text
 * @param string $icon  the site icon's address, or ''
 * @return string
 */
function pg_site_push_worker_prelude($door, $name, $text, $icon)
{
    $conf = array('door' => (string) $door, 'name' => (string) $name, 'text' => (string) $text);

    if ((string) $icon !== '') {
        $conf['icon'] = (string) $icon;
    }

    return 'self.pgSitePushWorker = ' . json_encode($conf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ";\n";
}
