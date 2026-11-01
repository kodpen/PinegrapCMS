<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Background renderer: one in-process page render for the passes that draw
 * many pages in one process (the SEO structure pass, the accessibility pass,
 * the search index), outside any visitor's request.
 *
 * A pass renders hundreds of pages one after another. One page that cannot
 * be built standalone must cost that page, not the pass: whatever the render
 * throws (an Exception from output_error(), or an Error such as a call on
 * null or an undefined constant on PHP 8) is caught and returned as the
 * render's error, the output buffers the render opened and left open are
 * closed, and the request state the render borrowed is put back.
 *
 * Loaded by the passes, not by functions.php.
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
 * Run one render with borrowed request state and give back what it drew.
 *
 * While the render runs: each $get value is set in $_GET (as a string), the
 * session token is an empty placeholder when the session has none (the
 * renderer writes it into the inline script block), the rendering flag is up
 * so output_error() throws instead of printing a screen and exiting, and the
 * catalog item resolver forgets the previous page's item.
 *
 * Afterwards, whether the render returned or threw: $_GET keys are put back
 * or removed, a lent token is unset (initialize_token() makes a new one only
 * when the key is absent, and validate_token_field() rejects an empty one, so
 * a blank left behind would break every form of that session), the rendering
 * flag gets its previous value, and buffers above the caller's level are
 * discarded.
 *
 * @param callable $render Returns the page's HTML.
 * @param array $get Request parameters the render reads from the address.
 * @return array html, error ('' when the render returned)
 */
function pg_background_render($render, $get = array())
{
    $previous_get = array();

    foreach ((array) $get as $key => $value) {
        $previous_get[$key] = array_key_exists($key, $_GET) ? array(true, $_GET[$key]) : array(false, null);
        $_GET[$key] = (string) $value;
    }

    // The resolver memoises for the life of the process, which is right for a
    // request and wrong for a pass: without this every catalog detail page
    // after the first is drawn with the first one's product.
    if (function_exists('get_catalog_item_from_url')) {
        get_catalog_item_from_url(true);
    }

    $lent_token = !isset($_SESSION['software']['token']);

    if ($lent_token) {
        $_SESSION['software']['token'] = '';
    }

    $buffer_level = ob_get_level();
    $was_rendering = pg_seo_rendering();
    $html = '';
    $error = '';

    pg_seo_rendering(true);

    try {
        $html = (string) call_user_func($render);
    } catch (Throwable $e) {
        $error = pg_background_render_error_message($e);
    } finally {
        pg_seo_rendering($was_rendering);

        // A render that threw half way leaves the buffers it opened; the
        // next page's output would land in them, and the process's own
        // output after the pass would never reach its caller.
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }

        foreach ($previous_get as $key => $previous) {
            if ($previous[0]) {
                $_GET[$key] = $previous[1];
            } else {
                unset($_GET[$key]);
            }
        }

        if ($lent_token) {
            unset($_SESSION['software']['token']);
        }
    }

    return array('html' => $html, 'error' => $error);
}

/**
 * The message a failed render records. An Exception from output_error()
 * carries the screen's own text; an Error is a fault in the code, so its
 * file and line go with it for whoever reads the log.
 *
 * @param Throwable $e
 * @return string
 */
function pg_background_render_error_message($e)
{
    $message = trim((string) $e->getMessage());

    if ($e instanceof Error) {
        $message = get_class($e) . ': ' . $message . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }

    return ($message === '') ? get_class($e) : $message;
}
