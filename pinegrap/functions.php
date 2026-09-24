<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The rest of this file moved to includes/fn/ on 2026-09-12: one module per
// subject, loaded here in a fixed order, every function exactly as it was. A
// 3 MB file could not be opened in an FTP editor, read in a diff, or uploaded
// without the site being down for the length of the upload; a module can.
//
// PG_FUNCTIONS_DIR is this directory - the software directory. In this file
// dirname(__FILE__) meant the same thing; in a module it would mean the
// module's own folder, so the modules say PG_FUNCTIONS_DIR wherever the code
// used to say dirname(__FILE__) or __DIR__. New code under includes/fn/ does
// the same.
//
// Order matters only for what is not a function: core.php carries the
// array_key_last() polyfill, mail.php the PHPMailer includes and its two
// "use" aliases (file-scoped, so they no longer reach the rest of the code),
// tour.php its version constant, content.php the image id list.
if (!defined('PG_FUNCTIONS_DIR')) {
    define('PG_FUNCTIONS_DIR', dirname(__FILE__));
}
// The sign-in primitives (token verify / revoke, user row load) are shared
// with get_file.php, which never loads this file - see the header of:
require_once(PG_FUNCTIONS_DIR . '/includes/authentication.php');
// The rules that belong in the web root's web.config / .htaccess. Shared with
// install/index.php, which requires this file: the installer writes them and
// the System Status check looks for them, from one list.
require_once(PG_FUNCTIONS_DIR . '/includes/server_config.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/forms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/calendar.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/editor.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/contacts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/signature.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/cron.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/currency_rates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/tour.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/system_status.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/image.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/events.php');
