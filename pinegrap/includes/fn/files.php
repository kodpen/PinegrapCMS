<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: File names, upload limits and blocked uploads.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// Create function that will allow us to get a unique name for an item
// in order to prevent multiple items from having the same name.
// Properties:
// name: the name of the item that you are attempting to use.
// type: the type of item (e.g. common region)
function get_unique_name($properties)
{
    $name = $properties['name'];
    $type = $properties['type'];
    // Determine if name is available in different ways depending on the type.
    switch ($type) {
        // For pages and files, we use a special function because
        // we need to make sure there are no name space conflicts in web root.
        case 'page':
        case 'file':
            if (
                check_name_availability(array(
                    'name' => $name
                )) == true
            ) {
                return $name;
            }
            break;
        case 'common_region':
        case 'designer_region':
            if (db_value("SELECT COUNT(*) FROM cregion WHERE cregion_name = '" . escape($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'folder':
            if (db_value("SELECT COUNT(*) FROM folder WHERE folder_name = '" . escape($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'menu':
            if (db_value("SELECT COUNT(*) FROM menus WHERE name = '" . e($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'style':
            if (db_value("SELECT COUNT(*) FROM style WHERE style_name = '" . escape($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'product':
            if (db_value("SELECT COUNT(*) FROM products WHERE name = '" . escape($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'product_group':
            if (db_value("SELECT COUNT(*) FROM product_groups WHERE name = '" . escape($name) . "'") == 0) {
                return $name;
            }
            break;
        case 'email_campaign_profile':
            if (db_value("SELECT COUNT(*) FROM email_campaign_profiles WHERE name = '" . e($name) . "'") == 0) {
                return $name;
            }
            break;
    }
    // If we have gotten here, then the name is already in use,
    // so prepare new name differently based on the item type.
    switch ($type) {
        default:
            // If there is already a bracket area on the end of the name,
            // then just increase number in bracket.
            if (preg_match('/(.*?)\[(\d+)\]$/', $name, $matches) == 1) {
                $new_name = $matches[1] . '[' . ($matches[2] + 1) . ']';
                // Otherwise there is not already a bracket area on the end of the name,
                // so add bracket area with a 1.
            } else {
                $new_name = $name . '[1]';
            }
            break;
        case 'file':
            $position_of_last_period = mb_strrpos($name, '.');
            $extension = '';
            // If there is a period in the file name, then continue to get file extension.
            if ($position_of_last_period !== false) {
                $extension = mb_substr($name, $position_of_last_period + 1);
            }
            // If the file name has an extension and it is a normal size,
            // then add unique suffix to the part of the file name before the extension.
            if (($extension != '') && (mb_strlen($extension) < 6)) {
                $name_without_extension = mb_substr($name, 0, $position_of_last_period);
                // If there is already a unique suffix on the end of the name,
                // then get name without unique suffix and prepare new unique suffix.
                if (preg_match('/(.*?)\[(\d+)\]$/', $name_without_extension, $matches) == 1) {
                    $name_without_unique_suffix = $matches[1];
                    $unique_suffix = '[' . ($matches[2] + 1) . ']';
                    // Otherwise there is not already a unique suffix on the end of the name,
                    // so prepare name without unique suffix and prepare unique suffix with a 1.
                } else {
                    $name_without_unique_suffix = $name_without_extension;
                    $unique_suffix = '[1]';
                }
                $new_name = $name_without_unique_suffix . $unique_suffix . '.' . $extension;
                // If the new file name is greater than 100 characters,
                // then reduce the part of the file name before the unique suffix.
                if (mb_strlen($new_name) > 100) {
                    // Calculate a new length for the part of the file name before the unique suffix
                    // based on the total 100 character limit, unique suffix, period, and extension size.
                    $new_length_of_name_without_unique_suffix = 100 - mb_strlen($unique_suffix) - 1 - mb_strlen($extension);
                    // Reduce the size of the part of the file name before the unique suffix.
                    $name_without_unique_suffix = mb_substr($name_without_unique_suffix, 0, $new_length_of_name_without_unique_suffix);
                    $new_name = $name_without_unique_suffix . $unique_suffix . '.' . $extension;
                }
                // Otherwise the file name does not have an extension or it has a weird one,
                // so add unique suffix to the end of the entire file name.
            } else {
                // If there is already a unique suffix on the end of the name,
                // then get name without unique suffix and prepare new unique suffix.
                if (preg_match('/(.*?)\[(\d+)\]$/', $name, $matches) == 1) {
                    $name_without_unique_suffix = $matches[1];
                    $unique_suffix = '[' . ($matches[2] + 1) . ']';
                    // Otherwise there is not already a unique suffix on the end of the name,
                    // so prepare name without unique suffix and prepare unique suffix with a 1.
                } else {
                    $name_without_unique_suffix = $name;
                    $unique_suffix = '[1]';
                }
                $new_name = $name_without_unique_suffix . $unique_suffix;
                // If the new file name is greater than 100 characters,
                // then reduce the part of the file name before the unique suffix.
                if (mb_strlen($new_name) > 100) {
                    // Calculate a new length for the part of the file name before the unique suffix
                    // based on the total 100 character limit and unique suffix.
                    $new_length_of_name_without_unique_suffix = 100 - mb_strlen($unique_suffix);
                    // Reduce the size of the part of the file name before the unique suffix.
                    $name_without_unique_suffix = mb_substr($name_without_unique_suffix, 0, $new_length_of_name_without_unique_suffix);
                    $new_name = $name_without_unique_suffix . $unique_suffix;
                }
            }
            break;
        // For menus use a dash instead of brackets because we don't allow brackets in menu names
        case 'menu':
            // If there is already a dash area on the end of the name (e.g. example-1), then just
            // increase number after dash.
            if (preg_match('/(.*?)-(\d+)$/', $name, $matches) == 1) {
                $new_name = $matches[1] . '-' . ($matches[2] + 1);
                // Otherwise there is not already a dash area on the end of the name, so add dash area
                // with a 1.
            } else {
                $new_name = $name . '-1';
            }
            break;
    }
    // Use recursion to check if this new name is unique
    // and get a different name if necessary.
    return get_unique_name(array(
        'name' => $new_name,
        'type' => $type
    ));
}
// Reduce a string to characters that survive a round trip through a URL.
//
// A file whose name is not ASCII cannot be opened on an IIS install. Measured
// on one: the browser requests the percent-encoded UTF-8 path correctly, but
// PHP is handed REQUEST_URI already decoded and re-encoded in the server's ANSI
// code page, so "görüntüsü" arrives as the single bytes f6/fc rather than the
// UTF-8 pairs c3b6/c3bc that were stored. router.php looks the file up with
// WHERE name = <those bytes> and finds nothing, and the visitor gets the site's
// 404 page. rawurldecode() cannot help — there is nothing left to decode.
//
// Nothing in the request path can be fixed from here, so the name is kept
// inside the character set that has no such problem. Turkish is mapped first:
// a generic accent-stripper turns ı into i but also turns İ into I and leaves
// ş and ğ to be replaced wholesale, which produces names nobody recognises.
//
// Files stored before this are untouched and keep working if they already did.
function pg_ascii_file_name($value)
{
    $map = array(
        // Turkish
        'ı' => 'i', 'İ' => 'I', 'ş' => 's', 'Ş' => 'S', 'ğ' => 'g', 'Ğ' => 'G',
        'ü' => 'u', 'Ü' => 'U', 'ö' => 'o', 'Ö' => 'O', 'ç' => 'c', 'Ç' => 'C',
        // Latin-1 and common Latin Extended
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Į' => 'I',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ō' => 'o', 'ø' => 'o', 'ő' => 'o',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ō' => 'O', 'Ø' => 'O', 'Ő' => 'O',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ū' => 'U', 'Ů' => 'U', 'Ű' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ń' => 'n', 'Ń' => 'N', 'ň' => 'n', 'Ň' => 'N',
        'ý' => 'y', 'Ý' => 'Y', 'ÿ' => 'y', 'ž' => 'z', 'Ž' => 'Z', 'ź' => 'z', 'Ź' => 'Z', 'ż' => 'z', 'Ż' => 'Z',
        'š' => 's', 'Š' => 'S', 'ś' => 's', 'Ś' => 'S', 'ř' => 'r', 'Ř' => 'R',
        'ď' => 'd', 'Ď' => 'D', 'ť' => 't', 'Ť' => 'T', 'ł' => 'l', 'Ł' => 'L',
        'ć' => 'c', 'Ć' => 'C', 'č' => 'c', 'Č' => 'C',
        'æ' => 'ae', 'Æ' => 'AE', 'œ' => 'oe', 'Œ' => 'OE', 'ß' => 'ss',
        'å' => 'a', 'ð' => 'd', 'Ð' => 'D', 'þ' => 'th', 'Þ' => 'TH');

    $value = strtr($value, $map);

    // Whatever is left outside the set — other alphabets, emoji, punctuation
    // that means something in a URL — becomes an underscore. A run of them
    // collapses, so a name written entirely in another script does not turn
    // into forty underscores.
    //
    // Square brackets are IN the set, and that is not an oversight. They are
    // the software's own suffix for a name that is taken — get_unique_name()
    // produces "photo[1].png" — and the release files are named the same way:
    // pinegrap_hash_referance[2026.4.1].json. Stripping them renamed those on
    // upload, so the update mechanism asked for a file that no longer existed
    // under that name and every integrity check failed to fetch its reference.
    // They are safe: measured, /avatar-2[2].png answers 200.
    $value = preg_replace('/[^A-Za-z0-9._\-\[\]]+/', '_', $value);
    $value = preg_replace('/_{2,}/', '_', $value);
    $value = trim($value, '_');

    return $value;
}

// ── What an uploaded file may never be called ──────────────────────────────
//
// One rule for every door a file comes in through: the file manager, the
// classic upload screen, the editors' pickers, the public forms, the zip and
// site importers and the API. A name is refused when the web server would run
// it or read it as its own settings -- .php under every name a handler has
// been mapped to, the scripts other servers run, what Windows executes,
// .htaccess, web.config and the like.
//
// Every extension in the name has to pass, not only the last one: a
// misconfigured Apache serves "shell.php.jpg" as PHP. The comparison is on
// the lower-cased name, so "Shell.PHP" is the same file as "shell.php".
//
// The lists live here rather than in the file manager because the file
// manager is only one of the doors. Uploading in the backup folder and
// extracting an archive add a couple of names of their own on top of these.
function pg_blocked_upload_names()
{
    return array('web.config', '.htaccess', '.htpasswd', '.user.ini', 'php.ini');
}

function pg_blocked_upload_extensions()
{
    return array(
        // PHP, under every name a handler has been mapped to
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'phtml', 'phtm', 'phar', 'pht', 'phpt', 'hphp', 'ctp',
        // The server's own settings
        'htaccess', 'htpasswd', 'config', 'ini', 'inc', 'conf',
        // Scripts other servers run
        'cgi', 'fcgi', 'pl', 'py', 'pyc', 'rb', 'sh', 'bash', 'zsh', 'ksh', 'csh',
        'jsp', 'jspx', 'jsw', 'jsv', 'jspf', 'asp', 'aspx', 'asa', 'asax', 'ascx', 'ashx', 'asmx', 'axd', 'cshtml', 'vbhtml',
        'cfm', 'cfc', 'cfml', 'shtml', 'shtm', 'stm',
        // What Windows runs when it is opened
        'exe', 'com', 'bat', 'cmd', 'msi', 'msp', 'mst', 'dll', 'so', 'jar', 'scr', 'pif', 'cpl', 'msc', 'hta',
        'vbs', 'vbe', 'ws', 'wsf', 'wsh', 'ps1', 'psm1', 'psd1', 'reg', 'lnk');
}

// Whether a name, as the operator or the visitor gave it, is one the rule
// above refuses. Asked before prepare_file_name() so the refusal can name the
// file as it was sent; a dotfile counts, because a name with nothing in front
// of its extension is a hidden file, not a file called by its extension.
function pg_upload_name_blocked($name)
{
    $base = mb_strtolower(basename(trim(str_replace('\\', '/', (string) $name))));

    if (($base == '') or (mb_substr($base, 0, 1) == '.')) {
        return true;
    }

    if (in_array($base, pg_blocked_upload_names(), true)) {
        return true;
    }

    $blocked = pg_blocked_upload_extensions();
    $parts = explode('.', $base);

    array_shift($parts);

    foreach ($parts as $part) {
        if (in_array($part, $blocked, true)) {
            return true;
        }
    }

    return false;
}

// The one sentence every door says when it refuses such a file.
function pg_upload_blocked_message($name = '')
{
    if ((string) $name != '') {
        return lang(array('string' => 'Sorry, {var:1} was not accepted. Files of that type cannot be uploaded here.', 'vars' => $name));
    }

    return lang('Sorry, files of that type cannot be uploaded here.');
}

// The floor under the doors. Every door refuses such a file with a message
// of its own first; this is for the door that forgot to ask, so a name the
// rule refuses still never reaches the disk under that name. The dot in
// front of each refused extension becomes an underscore -- "shell.php.jpg"
// is stored as "shell_php.jpg", "web.config" as "web_config" -- so the file
// keeps its content and loses its teeth. Called from prepare_file_name(), so
// it applies wherever that does.
function pg_upload_neutralize_name($file_name)
{
    $file_name = (string) $file_name;
    $lower = mb_strtolower($file_name);

    if (in_array($lower, pg_blocked_upload_names(), true)) {
        return str_replace('.', '_', $file_name);
    }

    if (mb_strpos($file_name, '.') === false) {
        return $file_name;
    }

    $blocked = pg_blocked_upload_extensions();
    $parts = explode('.', $file_name);
    $result = array_shift($parts);

    foreach ($parts as $part) {
        $result .= (in_array(mb_strtolower($part), $blocked, true) ? '_' : '.') . $part;
    }

    return $result;
}

// Create a function that is used in various areas where files are added,
// in order to reduce the file name length (if necessary) and replace special characters.
function prepare_file_name($file_name)
{
    $file_name = trim($file_name);
    // If file name is invalid, change file name.  We don't allow a basic user to add a "dkim.key"
    // file, because that file is used to sign emails for DKIM.
    if ($file_name == '.htaccess' or ($file_name == 'dkim.key' and (!USER_LOGGED_IN or USER_ROLE == 3))) {
        $file_name = 'file';
    }
    // Done before the length check below so that the 100 character limit is
    // measured against the name that actually gets stored.
    $file_name = pg_ascii_file_name($file_name);
    // A name written entirely in another script leaves nothing in front of the
    // extension. ".png" is not a file called png — it is a hidden file with no
    // name, and every such upload would be asking for the same one.
    if (($file_name === '') or ($file_name === '.') or (mb_substr($file_name, 0, 1) === '.')) {
        $file_name = 'file' . $file_name;
    }
    // A name the web server would run or read as its own settings is
    // defused here whichever door it came through; see
    // pg_upload_neutralize_name() for the rule and the shape it leaves.
    $file_name = pg_upload_neutralize_name($file_name);
    // If the file name is longer than 100 characters, then reduce the length of the file name.
    // We have to do this because the database limits the file name to 100 characters,
    // and also we don't want huge file names because they break UI elements.
    if (mb_strlen($file_name) > 100) {
        $position_of_last_period = mb_strrpos($file_name, '.');
        $file_extension = '';
        // If there is a period in the file name, then continue to get file extension.
        if ($position_of_last_period !== false) {
            $file_extension = mb_substr($file_name, $position_of_last_period + 1);
        }
        // If the file name has an extension and it is a normal size,
        // then reduce the length of the part of the file name before the extension.
        if (($file_extension != '') && (mb_strlen($file_extension) < 6)) {
            $file_name_without_extension = mb_substr($file_name, 0, $position_of_last_period);
            // Calculate a new length for the part of the file name before the extension
            // based on the total 100 character limit, period, and extension size.
            $new_length_of_file_name_without_extension = 100 - 1 - mb_strlen($file_extension);
            // Reduce the size of the part of the file name before the extension.
            $new_file_name_without_extension = mb_substr($file_name_without_extension, 0, $new_length_of_file_name_without_extension);
            // Prepare the new file name.
            $file_name = $new_file_name_without_extension . '.' . $file_extension;
            // Otherwise the file name does not have an extension or it has a weird one,
            // so just reduce the length of the whole file name.
        } else {
            $file_name = mb_substr($file_name, 0, 100);
        }
    }
    // Replace certain special characters with an underscore in order to avoid a bug
    // where someone cannot access a file with those characters.  mod_rewrite has a [B] flag
    // that should allow us to use these special characters but it was recently added in Apache 2.2.7
    // and was buggy until 2.2.12, so we have decided not to use it and just ban the characters for now.
    $file_name = str_replace(' ', '_', $file_name);
    $file_name = str_replace('&', '_', $file_name);
    $file_name = str_replace('+', '_', $file_name);
    $file_name = str_replace('#', '_', $file_name);
    return $file_name;
}
// Create a function that is used in order to determine if a page type
// requires that "from=control_panel" be added to links to the page so that
// errors about missing information are not outputted when an editor is simply
// trying to edit the page (e.g. avoids missing reference code error for form item view)
function check_if_page_type_requires_from_control_panel($page_type)
{
    switch ($page_type) {
        case 'view order':
        case 'custom form confirmation':
        case 'custom form':
        case 'calendar event view':
        case 'catalog detail':
        case 'shipping address and arrival':
        case 'shipping method':
        case 'logout':
            return true;
            break;
        default:
            return false;
            break;
    }
}
// Used in order to determine if a certain page type supports a system/custom layout selection.
function check_if_page_type_supports_layout($page_type)
{
    switch ($page_type) {
        case 'billing information':
        case 'catalog':
        case 'catalog detail':
        case 'change password':
        case 'set password':
        case 'custom form':
        case 'email preferences':
        case 'express order':
        case 'forgot password':
        case 'form item view':
        case 'form list view':
        case 'login':
        case 'membership entrance':
        case 'my account':
        case 'my account profile':
        case 'view order':
        case 'order form':
        case 'order preview':
        case 'order receipt':
        case 'photo gallery':
        case 'registration entrance':
        case 'search results':
        case 'shipping address and arrival':
        case 'shipping method':
        case 'shopping cart':
        case 'update address book':
            return true;
            break;
        default:
            return false;
            break;
    }
}


/**
 * Get the first public root folder ID.
 *
 * This function queries the `folders` table and returns the `folder_id`
 * of the first record that matches:
 *   - folder_parent = 0
 *   - folder_level = 0
 *   - folder_access_control_type = 'public'
 *
 * If no matching record is found, the function returns 0.
 *
 * @return int The folder_id if found, otherwise 0
 */
function getPublicRootFolderId()
{
    // Run the query to fetch one matching folder
    $folder_id = db("
        SELECT folder_id
        FROM folder
        WHERE folder_parent = 0
          AND folder_level = 0
          AND folder_access_control_type = 'public'
        LIMIT 1
    ");
    // Check if a result exists
    if ($folder_id) {
        return (int) $folder_id;
    }

    // Return 0 if no record found
    return 0;
}

/* ---------------------------------------------------------------------------
 * Image processing
 *
 * One engine, pg_process_image_file(). Everything that recompresses or resizes
 * an image goes through it: the optimize button, the bulk optimize on the file
 * list, the dashboard file manager widget, and the silent pass a product photo
 * makes on its way in.
 *
 * Two separate jobs, and the difference is the whole point of the pair of
 * buttons on the file screens:
 *
 *   optimize  strips metadata and recompresses at the same pixel size. The
 *             picture keeps its dimensions, so nothing that already points at
 *             it changes shape. This is what the software has always done and
 *             its output is unchanged.
 *
 *   resize    scales the longest edge down to a ceiling first, aspect ratio
 *             kept, and then compresses. This is the only thing that helps
 *             with an 8 MB camera photo: compression alone takes such a file
 *             from 8 MB to about 6, because the pixels are the weight.
 *
 * Resizing is never implied. A caller that does not ask for it gets the first
 * job, which is why the optimize button on the file screens cannot silently
 * change the size of a design asset somebody placed at exact dimensions.
 * ------------------------------------------------------------------------- */

/**
 * A php.ini shorthand size ("8M", "128K", "1G") as bytes.
 *
 * ini_get() hands back the string the operator typed, and comparing that to a
 * file size compares "8M" to 14543599 — which in PHP is 8 against 14543599,
 * so the check passes and the upload fails anyway.
 *
 * @param string $value
 * @return int bytes; 0 for an unset value or an explicit no-limit (-1 or 0)
 */
function pg_ini_bytes($value)
{
    $value = trim((string) $value);

    if (($value === '') || ($value === '-1') || ($value === '0')) {
        return 0;
    }

    $bytes = (int) $value;
    $unit  = strtolower(substr($value, -1));

    if ($unit === 'g') {
        $bytes *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $bytes *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $bytes *= 1024;
    }

    return max(0, $bytes);
}


/**
 * What this server will actually accept in one upload.
 *
 * Three separate ceilings, and an upload has to clear all of them:
 *
 *   post_max_size       the whole request. Exceeding it is the worst of the
 *                       three, because PHP throws the body away before any
 *                       script runs — see pg_post_body_discarded().
 *   upload_max_filesize one file. A file over it arrives with an error code
 *                       and an empty temporary name.
 *   max_file_uploads    how many files one request may carry. Files past the
 *                       limit are dropped without an error code at all.
 *
 * request_max subtracts an allowance for the multipart envelope — the
 * boundaries, the field names, the token — because a request built to exactly
 * post_max_size is a request that is over it.
 *
 * @return array post_max, file_max, max_files, request_max (0 = no limit)
 */
/**
 * How much memory one large upload request is allowed to claim.
 *
 * The file manager sends a file base64 encoded inside a json body, and that body is read whole
 * and then parsed, so two copies of it are alive at the peak.  On a 128 MB memory_limit that
 * puts the biggest workable file at about 35 MB - not because the server could not carry more,
 * but because the default allowance is sized for ordinary page requests, not for one carrying a
 * file.  api.php lifts the limit for exactly those requests, up to the ceiling below, and this
 * function is what both the lift and the advertised limit agree on.
 *
 * The server setting is a floor and is never lowered.  Set UPLOAD_MEMORY_CEILING in
 * data/config.php (for example '1G') to allow larger uploads still.
 *
 * @return int bytes, or 0 when memory_limit is unlimited
 */
function pg_upload_memory_ceiling()
{
    $configured = pg_ini_bytes(@ini_get('memory_limit'));

    // Unlimited stays unlimited.
    if ($configured === 0) {
        return 0;
    }

    $ceiling = defined('UPLOAD_MEMORY_CEILING') ? pg_ini_bytes(UPLOAD_MEMORY_CEILING) : (512 * 1024 * 1024);

    if ($ceiling === 0) {
        return 0;
    }

    return max($ceiling, $configured);
}


function pg_upload_limits()
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $post_max  = pg_ini_bytes(@ini_get('post_max_size'));
    $file_max  = pg_ini_bytes(@ini_get('upload_max_filesize'));
    $max_files = (int) @ini_get('max_file_uploads');

    // A file cannot be bigger than the request that carries it, whatever the
    // two settings say individually.
    if (($post_max > 0) && (($file_max === 0) || ($file_max > $post_max))) {
        $file_max = $post_max;
    }

    $request_max = ($post_max > 0) ? max(0, $post_max - (64 * 1024)) : 0;

    // The file manager sends the file base64 encoded inside a json request, not as a form.  Most
    // servers still hand that body to the script even when it is over post_max_size, because that
    // setting governs the form parser, and a json body is read straight from php://input.  So the
    // limit that really applies here is memory.  On a server that does throw the body away, api.php
    // answers with the size it can take and the screen lowers its own limit to match, so nobody is
    // left guessing.
    //
    // Two copies of the body are alive at the peak: php://input is read whole, and json_decode
    // builds its own copy of the payload while it parses the text it was handed.  The file itself
    // is no longer among them - it is decoded to disk in chunks - which is what the flat quarter of
    // the memory limit used to be paying for.  Base64 costs about 1.37 bytes per byte of file, so
    // two copies come to 2.74; 2.9 leaves room for the json envelope around the payload.
    // Not ini_get('memory_limit'): what matters is the room an upload request will have once
    // api.php has lifted the limit for it, and this listing request has had no reason to lift
    // anything.  Reporting the un-lifted figure would cap the screen at a size the upload path
    // is not actually held to.
    $memory_max = pg_upload_memory_ceiling();

    $json_max = 0;

    if ($memory_max > 0) {

        // What this request has already spent.  Loading the software is nearly all of it and an
        // upload request starts from the same place, so it is a fair measure of what the file will
        // not get - and it adapts to a server we have never seen instead of assuming one.
        $memory_used = memory_get_peak_usage(true);

        // Inside an upload the peak above is not only the software: api.php has already read the
        // body and json_decode has already copied the payload out of it, so two copies of the
        // file are counted in there.  Take them back out.  Left in, the allowance falls as the
        // file grows and the upload is refused for being the size that was measured.
        if (isset($GLOBALS['pg_request_body_bytes'])) {

            $memory_used = max(0, $memory_used - (2 * (int) $GLOBALS['pg_request_body_bytes']));

        }

        // Headroom for what the upload does around the payload: the folder checks, the insert, the
        // response, and whatever the allocator rounds up on the way.
        $memory_reserve = max(8 * 1024 * 1024, (int) floor($memory_max * 0.1));

        $memory_free = $memory_max - $memory_used - $memory_reserve;

        $json_max = ($memory_free > 0) ? (int) floor($memory_free / 2.9) : 0;
    }

    // what a strict server would accept in one request, base64 included
    $json_post_max = ($request_max > 0) ? (int) floor($request_max * 0.74) : 0;

    $cached = array(
        'post_max'    => $post_max,
        'file_max'    => $file_max,
        'json_max'      => $json_max,
        'json_post_max' => $json_post_max,
        'memory_max'    => $memory_max,
        'max_files'   => max(0, $max_files),
        'request_max' => $request_max);

    return $cached;
}


/**
 * Whether PHP threw this request's body away before the script started.
 *
 * When a POST is larger than post_max_size, PHP discards the whole body at
 * request startup: $_POST and $_FILES arrive empty, the only trace is a
 * warning in the error log, and the script has no way to tell this apart from
 * a fresh visit — so an upload screen renders its form again, with a 200
 * status, and the browser reports success. That is how "it says it uploaded
 * and nothing happened" happens.
 *
 * Content-Length is what gives it away: the browser sent a body, and none of
 * it is here.
 *
 * @return bool
 */
function pg_post_body_discarded()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }

    if (!empty($_POST) || !empty($_FILES)) {
        return false;
    }

    return ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0);
}
