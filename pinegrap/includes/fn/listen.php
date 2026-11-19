<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Listen" block's door on the published page: a page whose HTML holds
 * the block's mark (data-pg-listen) gets assets/js/pg_listen.js and the
 * labels of its buttons in the page's language. The block carries no script
 * of its own; the editor's Preview loads the same file from its twin row in
 * _SD_PAGE_SCRIPTS (assets/js/style_designer.src.js). No request, file,
 * table, cookie or setting: a site that never places the block changes in
 * nothing. Loaded by get_page_content.php only when the mark is there.
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
 * The block's mark as an attribute of its own (not data-pg-listen-play and
 * not text): the Preview's row in _SD_PAGE_SCRIPTS uses the same expression.
 *
 * @return string
 */
function pg_listen_pattern()
{
    return '/\sdata-pg-listen(?=[\s=>\/])/i';
}

/**
 * Whether a page's HTML holds a "Listen" block.
 *
 * @param string $content page HTML
 * @return bool
 */
function pg_listen_on_page($content)
{
    return stripos((string)$content, 'data-pg-listen') !== false
        && (bool)preg_match(pg_listen_pattern(), (string)$content);
}

/**
 * The buttons' labels in the page's language, by their English key;
 * pg_listen.js falls back to the key.
 *
 * @return array
 */
function pg_listen_labels()
{
    return array(
        'Listen' => lang('Listen'),
        'Pause'  => lang('Pause'),
        'Resume' => lang('Resume'),
        'Stop'   => lang('Stop'),
    );
}

/**
 * The script and its labels before the page's last </body>, once, for a
 * page holding the block (pg_listen_on_page()). Asked once every shared
 * component and system widget is drawn in, so the file follows the block
 * wherever it was placed.
 *
 * @param string      $content page HTML
 * @param string|null $src     the script's address; null builds it from the
 *                             software directory and the file's time
 * @return string
 */
function pg_listen_inject($content, $src = null)
{
    if (!pg_listen_on_page($content)) return $content;
    if (strpos($content, '/assets/js/pg_listen.js') !== false) return $content;

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_listen.js?v='
             . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_listen.js');
    }
    $file = '<script>window.pgListenLabels = ' . encode_json(pg_listen_labels()) . ';</script>' . "\n"
          . '<script src="' . h($src) . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}
