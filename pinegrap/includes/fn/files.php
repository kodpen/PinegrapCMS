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
        'ð' => 'd', 'Ð' => 'D', 'þ' => 'th', 'Þ' => 'TH');

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

// The last segment of a path, cut at the last "/" by hand, as basename() cuts
// it on Linux, trailing slashes ignored. For a name a person gave (an upload,
// an archive entry, a link) basename() itself is not used: before PHP 8.0 it
// reads the name in the server's locale, and in the "C" locale it drops the
// non-ASCII letters a segment starts with ("Özet.pdf" becomes "zet.pdf",
// "отчет.xlsx" becomes ".xlsx"). A caller that also takes "\" as a separator
// turns it into "/" first.
function pg_basename($path)
{
    $path = rtrim((string) $path, '/');
    $slash = strrpos($path, '/');

    return ($slash === false) ? $path : substr($path, $slash + 1);
}

// The last segment without its last extension, as pathinfo($path,
// PATHINFO_FILENAME) gives it from PHP 8.0: "Özet.tar.gz" is "Özet.tar",
// ".htaccess" is "". For a name a person gave, for the reason pg_basename()
// gives.
function pg_file_name_stem($path)
{
    $base = pg_basename($path);
    $dot = strrpos($base, '.');

    return ($dot === false) ? $base : substr($base, 0, $dot);
}

// Whether a name, as the operator or the visitor gave it, is one the rule
// above refuses. Asked before prepare_file_name() so the refusal can name the
// file as it was sent; a dotfile counts, because a name with nothing in front
// of its extension is a hidden file, not a file called by its extension.
function pg_upload_name_blocked($name)
{
    $base = mb_strtolower(pg_basename(trim(str_replace('\\', '/', (string) $name))));

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

// The one file the panel never hands out: dkim.key, the private key that
// signs the site's mail. get_file.php never serves it, whatever folder its
// record sits in (older installations registered it in a public folder), so
// the file screens show no content for it, leave it out of the archives they
// build, and neither copy it nor rename it: an archive, a copy or a renamed
// file would be an ordinary file that get_file.php then serves. The mailer
// reads it by this name, so it stays where the SMTP settings screen wrote it. Other .key
// and .pub files are not affected.
function pg_file_withheld($name)
{
    return mb_strtolower(trim((string) $name)) === 'dkim.key';
}

// Whether this person may not give a file this name. The one such name is
// dkim.key: the mailer signs the site's mail with whatever file is called
// that (includes/fn/mail.php), and the SMTP settings screen offers its
// record's description as the TXT record to publish. Staff (roles 0-2) may
// add it; a basic user (role 3) and a visitor who is not signed in ($role
// null) may not. $name is the name as it will be stored, so a name that only
// becomes dkim.key on the way in ("dkım.key" after pg_ascii_file_name()) or
// that a case-insensitive file system keeps as the same file ("DKIM.KEY") is
// the same name.
function pg_file_name_reserved($name, $role)
{
    return pg_file_withheld($name) && (($role === null) || ((int) $role >= 3));
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
    // If file name is invalid, change file name.
    if ($file_name == '.htaccess') {
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
    // Windows drops the dots at the end of a file name, so "photo.jpg." would
    // be written over photo.jpg while its record kept a name of its own. The
    // length limit above can leave one too. Never empty here: a name that
    // started with a dot was given "file" in front of it.
    $file_name = rtrim($file_name, '.');
    // A basic user or a visitor may not add dkim.key (pg_file_name_reserved()).
    // Asked last, on the name as it is stored: the transliteration above turns
    // "dkım.key" into "dkim.key", and a case-insensitive disk keeps "DKIM.KEY"
    // as the same file. Without a session (a job, the external API) nobody
    // counts as staff.
    $role = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE')) ? USER_ROLE : null;
    if (pg_file_name_reserved($file_name, $role)) {
        $file_name = 'file';
    }
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
 * One engine, pg_process_image_file() in includes/image_engine.php. Everything
 * that recompresses or resizes an image goes through it: the optimize button,
 * the bulk optimize on the file list, the dashboard file manager widget, the
 * silent pass a product photo makes on its way in, and the responsive
 * derivatives get_file.php makes on request. pg_ini_bytes(), which the upload
 * limits below read, is in the same file because the engine's memory guard
 * needs it on the get_file.php path too.
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

/**
 * What to tell the person about a body PHP threw away for being over
 * post_max_size, naming the most one request may carry.
 *
 * @param int $post_max bytes, post_max_size (0: not known)
 * @return string
 */
function pg_upload_body_discarded_message($post_max)
{
    $sentence = lang('The request was too large for this server and arrived empty.');

    if ((int) $post_max <= 0) {
        return $sentence;
    }

    return $sentence . ' ' . lang(array(
        'string' => 'The file can be at most {var:1}.',
        'vars'   => array(convert_bytes_to_string((int) $post_max, 1))));
}

/**
 * Ends a form-POST screen whose body PHP threw away (pg_post_body_discarded())
 * with a 413 page that names the limit, instead of drawing the form again as
 * if nothing had been sent. Called right after init.php; never from an
 * endpoint whose body is JSON.
 */
function pg_upload_refuse_discarded_body()
{
    if (!pg_post_body_discarded()) {
        return;
    }

    $limits = pg_upload_limits();

    output_error(h(pg_upload_body_discarded_message($limits['post_max'])) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 413);
}

/**
 * What to tell the person about a file PHP did not take in whole, naming the
 * file and, for an oversize one, the limit.
 *
 * @param string $name     the file's name as it was sent
 * @param int    $error    PHP's upload error code
 * @param int    $file_max bytes, the largest single file the server takes
 * @return string '' for UPLOAD_ERR_OK
 */
function pg_upload_error_message($name, $error, $file_max)
{
    $error = (int) $error;

    if ($error === UPLOAD_ERR_OK) {
        return '';
    }

    if (in_array($error, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        return lang(array(
            'string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.',
            'vars'   => array($name, convert_bytes_to_string($file_max, 1))));
    }

    if ($error === UPLOAD_ERR_PARTIAL) {
        return lang(array(
            'string' => '{var:1} only arrived in part, so it was not saved. Please try again.',
            'vars'   => array($name)));
    }

    return lang(array(
        'string' => 'The server could not accept {var:1}. Please try again.',
        'vars'   => array($name)));
}

/**
 * The refusal for one $_FILES entry of a single-file box, read before the
 * file is opened: an oversize file arrives with an empty tmp_name, which
 * fopen() and copy() turn into an error page (PHP 8 throws on an empty path)
 * or a misleading "empty" or "not a valid zip" answer.
 *
 * @param array|null $file     the $_FILES entry
 * @param int        $file_max bytes, the largest single file the server takes
 * @return string '' when the file arrived or none was chosen (the screen's
 *                own "choose a file" answer covers that)
 */
function pg_upload_file_refusal($file, $file_max)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return '';
    }

    $error = (int) $file['error'];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    return pg_upload_error_message(pg_basename((string) ($file['name'] ?? '')), $error, $file_max);
}

/**
 * The sentence under a single-file box: the largest file it takes, the
 * screen's own limit when that is lower than the server's.
 *
 * @param int      $app_max  bytes, the screen's own limit (0: none)
 * @param int|null $file_max bytes, the server's limit (null: pg_upload_limits())
 * @return string '' when neither limits the file
 */
function pg_upload_limit_sentence($app_max = 0, $file_max = null)
{
    if ($file_max === null) {
        $limits = pg_upload_limits();
        $file_max = $limits['file_max'];
    }

    $max = (int) $file_max;
    $app_max = (int) $app_max;

    if (($app_max > 0) && (($max <= 0) || ($app_max < $max))) {
        $max = $app_max;
    }

    if ($max <= 0) {
        return '';
    }

    return lang(array(
        'string' => 'The file can be at most {var:1}.',
        'vars'   => array(convert_bytes_to_string($max, 1))));
}

/**
 * pg_upload_limit_sentence() as the hint line under a file box.
 *
 * @param int $app_max bytes, the screen's own limit (0: none)
 * @return string HTML, '' when there is no limit to state
 */
function pg_upload_limit_hint($app_max = 0)
{
    $sentence = pg_upload_limit_sentence($app_max);

    return ($sentence === '') ? '' : '<div class="form-text">' . h($sentence) . '</div>';
}

/**
 * Stores one file that arrived for the file library: the rules every file
 * of the Upload File screen goes through (add_file.php), whether the browser
 * sent it or the server downloaded it from the person's cloud storage
 * (includes/fn/cloud_file_picker.php).
 *
 * In order: a name the web server would run is refused, a file over the
 * biggest single upload this server accepts is refused, the name is
 * prepared and made unique, the bytes are copied in, a product photo is
 * processed (image_profile 'product'), and only then is the row written and
 * announced. Nothing is recorded for a file that is not on the disk.
 *
 * The caller has checked the token and the person's right to the folder,
 * and logs a refusal.
 *
 * @param string $source_path the file as it arrived (PHP's temporary upload, a download)
 * @param string $file_name   the name it was given, before prepare_file_name()
 * @param int    $file_size   its size in bytes
 * @param array  $options     folder, description, design (null when the
 *                            person may not set it), image_profile,
 *                            image_settings (pg_image_settings()), file_max
 *                            (0: no limit)
 * @return array ok, message (when refused), file (when stored: id, name,
 *               type, size, original_size, width, height, resized, too_small)
 */
function pg_file_store_upload($source_path, $file_name, $file_size, $options)
{
    $file_size = (int) $file_size;
    $file_max = isset($options['file_max']) ? (int) $options['file_max'] : 0;

    // The caller writes a refusal to the Site Log, so these rules run
    // without a database too (tests/cloud_file_picker_test.php).
    $refuse = function ($message) {
        return array('ok' => false, 'message' => $message);
    };

    // A name the web server would run or read as its own settings is
    // refused, not renamed: the operator should know it did not arrive.
    if (pg_upload_name_blocked($file_name)) {
        return $refuse(pg_upload_blocked_message($file_name));
    }

    // PHP enforces this itself for a browser upload; a downloaded file is
    // held to the same ceiling here.
    if (($file_max > 0) && ($file_size > $file_max)) {
        return $refuse(lang(array(
            'string' => '{var:1} is larger than {var:2}, the biggest single file this server accepts.',
            'vars'   => array($file_name, convert_bytes_to_string($file_max, 1)))));
    }

    $file_name = prepare_file_name($file_name);

    $file_name = get_unique_name(array(
        'name' => $file_name,
        'type' => 'file'));

    $array_file_extension = explode('.', $file_name);
    $file_extension = $array_file_extension[count($array_file_extension) - 1];

    $file_path = FILE_DIRECTORY_PATH . '/' . $file_name;

    // A failed copy used to be followed by an INSERT anyway, which is how a
    // row can point at a file that was never written.
    if (!@copy($source_path, $file_path)) {
        return $refuse(lang(array(
            'string' => 'The server could not accept {var:1}. Please try again.',
            'vars'   => array($file_name))));
    }

    $original_size = $file_size;
    $image_profile = isset($options['image_profile']) ? (string) $options['image_profile'] : '';
    $image_settings = isset($options['image_settings']) ? $options['image_settings'] : array();

    // Product photos are compressed on the way in, and scaled down when they
    // are over the ceiling. Nothing is refused and nothing is enlarged: an
    // image below the minimum is stored as it is and reported back so the
    // screen can say so.
    $image_report = null;
    $sql_image_1 = "";
    $sql_image_2 = "";

    if ($image_profile === 'product') {

        // A 24 megapixel photo takes a couple of seconds to resample, and the
        // picker sends a whole selection in one request. The default 30
        // seconds is a page that dies half way through the third file.
        @set_time_limit(120);

        $image_report = pg_process_image_file($file_path, array(
            'max_dimension' => $image_settings['product_max_dimension'],
            'min_dimension' => $image_settings['product_min_dimension'],
            'quality'       => $image_settings['resize_quality']));

        if ($image_report['status'] === 'success') {

            @clearstatcache(true, $file_path);
            $file_size = filesize($file_path);

            // Flagged optimized so the Files screen does not offer to do it
            // again: the badge there would promise a saving that has already
            // been taken, and pressing it would recompress an image that is
            // already at its target quality.
            $sql_image_1 = "optimized, image_width, image_height, optimization_percent,";
            $sql_image_2 =
                "'1',
                 '" . (int) $image_report['width'] . "',
                 '" . (int) $image_report['height'] . "',
                 '0',";

            if ($image_report['changed']) {
                log_activity(lang(array(
                    'string' => 'image ({var:1}) was optimized on upload ({var:2} -> {var:3})',
                    'vars'   => array(
                        $file_name,
                        convert_bytes_to_string($image_report['bytes_before']),
                        convert_bytes_to_string($image_report['bytes_after'])))));
            }
        }
    }

    $sql_design_1 = "";
    $sql_design_2 = "";

    // Only a designer or administrator sets the design property.
    if (isset($options['design']) && ($options['design'] !== null)) {
        $sql_design_1 = "design,";
        $sql_design_2 = "'" . escape($options['design']) . "',";
    }

    db(
        "INSERT INTO files (
            name,
            folder,
            description,
            type,
            size,
            " . $sql_image_1 . "
            " . $sql_design_1 . "
            user,
            timestamp)
        VALUES (
            '" . escape($file_name) . "',
            '" . escape($options['folder'] ?? '') . "',
            '" . escape($options['description'] ?? '') . "',
            '" . escape($file_extension) . "',
            '" . escape($file_size) . "',
            " . $sql_image_2 . "
            " . $sql_design_2 . "
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    $new_file_id = (int) mysqli_insert_id(db::$con);

    pg_audit_record('create', 'files', $new_file_id);

    log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $file_name)), $_SESSION['sessionusername']);

    // A file uploaded from the panel is as new to an integration as one
    // posted to the API, so it is announced the same way.
    pg_announce('file.created', array('id' => $new_file_id, 'name' => $file_name));

    return array('ok' => true, 'file' => array(
        'id'            => $new_file_id,
        'name'          => $file_name,
        'type'          => $file_extension,
        'size'          => $file_size,
        // What arrived, so the caller can add up the saving.
        'original_size' => $original_size,
        // Zero when the file was not measured, which is every upload that
        // did not ask for a profile. The caller reads too_small rather than
        // comparing these itself, so the minimum lives in one place.
        'width'         => $image_report ? (int) $image_report['width'] : 0,
        'height'        => $image_report ? (int) $image_report['height'] : 0,
        'resized'       => (bool) ($image_report && $image_report['resized']),
        'too_small'     => (bool) ($image_report && $image_report['too_small'])));
}

/**
 * An address that reads one entry of a zip archive no further than $limit
 * bytes, for XMLReader::open() and fopen() wherever
 * 'zip://' . $archive . '#' . $entry would go.
 *
 * Give the entry's own size (pg_zip_entry_size()): an honest entry is read
 * whole, one that inflates past what the archive declares for it fails at
 * the first byte too many (includes/zip_stream_cap.php), and
 * pg_zip_entry_overran() says so once the read is done. Without the limit a
 * stream reads such an entry to its end before libzip compares the sizes.
 *
 * @param string $archive the zip on disk
 * @param string $entry   the entry's name inside it
 * @param int    $limit   bytes the read may pass
 * @return string
 */
function pg_zip_entry_uri($archive, $entry, $limit)
{
    static $registered = false;

    if (!$registered) {
        require_once(PG_FUNCTIONS_DIR . '/includes/zip_stream_cap.php');
        $registered = in_array('pg_zip_cap.*', stream_get_filters(), true) || stream_filter_register('pg_zip_cap.*', 'pg_zip_stream_cap');
    }

    pg_zip_stream_cap::$overran = false;

    return 'php://filter/read=pg_zip_cap.' . max(0, (int) $limit) . '/resource=zip://' . $archive . '#' . $entry;
}

/**
 * Did the stream behind the last pg_zip_entry_uri() go past its limit?
 * Asked once its read is over; the next address clears the answer.
 *
 * @return bool
 */
function pg_zip_entry_overran()
{
    return class_exists('pg_zip_stream_cap', false) && pg_zip_stream_cap::$overran;
}

/**
 * How large an entry of a zip archive says it unpacks, in bytes, or -1 when
 * the archive cannot be opened or holds no such entry.
 *
 * @param string $archive
 * @param string $entry
 * @return int
 */
function pg_zip_entry_size($archive, $entry)
{
    if (!class_exists('ZipArchive')) {
        return -1;
    }

    $zip = new ZipArchive();

    if ($zip->open((string) $archive) !== true) {
        return -1;
    }

    $stat = $zip->statName((string) $entry);
    $zip->close();

    return ($stat === false) ? -1 : (int) $stat['size'];
}

/**
 * One entry of an open zip archive, whole, as a string. Null when the archive
 * has no such entry, when the entry declares more than $limit bytes, or when
 * it does not unpack to exactly the size and checksum it declares.
 *
 * The declared size is what the archive's directory says, and what the
 * ceilings in front of a read add up (pg_office_open(),
 * pg_zip_import_open()). getFromIndex() is asked for one byte more than
 * that: an honest entry returns its size, one that inflates further returns
 * the extra byte and is refused, so an entry that declares a kilobyte and
 * inflates to gigabytes costs a kilobyte and one byte. An entry that holds
 * less than it declares comes back short and is refused as well, and so is
 * one that declares nothing over data that inflates to something, or over a
 * stream that cannot be inflated. A size past PHP_INT_MAX (negative on a
 * 32-bit build) is too large. The checksum is compared here, so a damaged
 * entry is refused the same way on every PHP version (getFromIndex()
 * compares it itself only in recent releases).
 *
 * @param ZipArchive $zip
 * @param string|int $entry the entry's name, or its index
 * @param int|float  $limit the most the entry may declare, in bytes
 * @param string     $error '' when the entry came back; 'missing', 'large'
 *                          or 'damaged' when null did
 * @return string|null
 */
function pg_zip_entry_contents($zip, $entry, $limit, &$error)
{
    $error = '';
    $stat = is_int($entry) ? $zip->statIndex($entry) : $zip->statName((string) $entry);

    if (($stat === false) || !isset($stat['size'], $stat['index'], $stat['crc'])) {
        $error = 'missing';

        return null;
    }

    if (((int) $stat['size'] < 0) || ((float) $stat['size'] > (float) $limit)) {
        $error = 'large';

        return null;
    }

    $size = (int) $stat['size'];
    $data = '';

    // An entry that declares nothing is asked for one byte through its
    // stream, as getFromIndex() answers it with '' without reading it up to
    // PHP 8.3 and in older 8.4 and 8.5 releases. An empty file gives none,
    // whatever its compressed stream looks like (zlib writes 2 bytes, Go's
    // archive/zip 5, a sync-flushing writer 7, bzip2 14), and an entry that
    // hides data behind a size of nothing gives some.
    if (($size === 0) && isset($stat['comp_size']) && ((int) $stat['comp_size'] !== 0)) {

        $stream = method_exists($zip, 'getStreamIndex')
            ? $zip->getStreamIndex((int) $stat['index'])
            : $zip->getStream((string) $stat['name']);

        if (!is_resource($stream)) {
            $error = 'damaged';

            return null;
        }

        // A stream that cannot be inflated, or that ends on another checksum
        // than the one declared, warns. fread() returns false for it from
        // PHP 7.4 on but '' before, as it does at the end of an empty file,
        // so the warning is what tells the two apart.
        $failed = false;

        set_error_handler(function () use (&$failed) {
            $failed = true;

            return true;
        });

        $byte = fread($stream, 1);
        restore_error_handler();
        fclose($stream);

        if ($failed || ($byte !== '')) {
            $error = 'damaged';

            return null;
        }

    } elseif ($size > 0) {

        // Recent releases (PHP 8.4.26, 8.5.12) read past the entry's last
        // byte so that libzip compares its checksum, and warn and return
        // false when it does not match; older ones return the data. The
        // checksum is compared below on every version, without the warning.
        $data = @$zip->getFromIndex((int) $stat['index'], $size + 1);

        if (!is_string($data) || (strlen($data) !== $size)) {
            $error = 'damaged';

            return null;
        }
    }

    if (hash('crc32b', $data) !== sprintf('%08x', $stat['crc'] & 0xFFFFFFFF)) {
        $error = 'damaged';

        return null;
    }

    return $data;
}

/**
 * A zip that is taken apart entry by entry, each entry read whole into
 * memory and written out (Import ZIP File, the designer's HTML / ZIP
 * import): the most entries it may hold, the most one entry may unpack to,
 * and the most all of them may unpack to together, in bytes. The entry and
 * byte totals are the file manager's extraction ceilings.
 */
define('PG_ZIP_IMPORT_MAX_ENTRIES', 10000);
define('PG_ZIP_IMPORT_MAX_ENTRY', 64 * 1024 * 1024);
define('PG_ZIP_IMPORT_MAX_BYTES', 2147483648);

/**
 * Opens a zip that is to be imported entry by entry, and measures it from
 * its directory before a byte of it is inflated: more than
 * PG_ZIP_IMPORT_MAX_ENTRIES entries, more than PG_ZIP_IMPORT_MAX_BYTES
 * declared, or more than the disk has room for, and it is refused whole.
 * A single entry past PG_ZIP_IMPORT_MAX_ENTRY is left to the caller, which
 * may not need it; pg_zip_entry_contents() refuses to read it.
 *
 * @param string $path
 * @param string $error a sentence for the operator when null comes back
 * @return ZipArchive|null
 */
function pg_zip_import_open($path, &$error)
{
    $error = '';

    if (!class_exists('ZipArchive')) {
        $error = lang('Zip support is not available on this server, so the archive cannot be extracted.');

        return null;
    }

    $zip = new ZipArchive();

    // 22 bytes is the shortest zip (its end record alone); an empty file
    // would otherwise open as an empty archive. An archive with no entries
    // is refused the same way.
    if (!is_file((string) $path) || (filesize((string) $path) < 22) || (@$zip->open((string) $path) !== true)) {
        $error = lang('The zip file cannot be extracted, because it is not a valid zip file.');

        return null;
    }

    if ($zip->numFiles < 1) {
        $zip->close();
        $error = lang('The zip file cannot be extracted, because it is not a valid zip file.');

        return null;
    }

    if ($zip->numFiles > PG_ZIP_IMPORT_MAX_ENTRIES) {
        $zip->close();
        $error = lang('Sorry, that archive is too large to extract here.');

        return null;
    }

    $total = 0.0;

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $stat = $zip->statIndex($index);

        // A size that cannot be read cannot be measured: refused, like a
        // damaged directory in the file manager.
        if (($stat === false) || !isset($stat['size'])) {
            $zip->close();
            $error = lang('The archive could not be opened. It might be damaged.');

            return null;
        }

        // Negative on a 32-bit build: past PHP_INT_MAX, so past the ceiling.
        $total += ((int) $stat['size'] < 0) ? (float) PG_ZIP_IMPORT_MAX_BYTES + 1 : (float) $stat['size'];
    }

    if ($total > PG_ZIP_IMPORT_MAX_BYTES) {
        $zip->close();
        $error = lang('Sorry, that archive is too large to extract here.');

        return null;
    }

    if (!pg_explorer_disk_has_room($total)) {
        $zip->close();
        $error = lang('There is not enough free disk space to extract the archive.');

        return null;
    }

    return $zip;
}

/**
 * An entry's name as a path: "." segments and doubled slashes dropped, ".."
 * resolved and never above the top, no leading slash. A directory keeps its
 * trailing slash. ("./css//a.css" and "x/../css/a.css" are "css/a.css".)
 *
 * @param string $name
 * @return string
 */
function pg_zip_entry_path($name)
{
    $name = (string) $name;
    $out = array();

    foreach (explode('/', $name) as $segment) {

        if (($segment === '') || ($segment === '.')) {
            continue;
        }

        if ($segment === '..') {
            array_pop($out);
            continue;
        }

        $out[] = $segment;
    }

    $path = implode('/', $out);

    return (($path !== '') && (substr($name, -1) === '/')) ? $path . '/' : $path;
}

/**
 * Whether the disk the data folder sits on has room for $bytes plus a
 * margin. Hosts that switch disk_free_space() off answer "unknown", which is
 * not a refusal.
 *
 * @param int|float $bytes
 * @return bool
 */
function pg_explorer_disk_has_room($bytes)
{
    if (!function_exists('disk_free_space')) {
        return true;
    }

    $free = @disk_free_space(PG_FUNCTIONS_DIR . '/data');

    return ($free === false) || ((float) $free >= ((float) $bytes + 67108864));
}

/**
 * Whether any of these `files` rows is a document the ERP module keeps.
 *
 * Issued invoices, delivery notes, accepted e-documents and e-mailed
 * reconciliation letters are kept as ordinary `files` rows with no folder and
 * files.erp_doc_type naming the document (includes/erp/archive.php). They are
 * read-only everywhere a file is changed by id: an issued document keeps its
 * number and its PDF, and a request that names one by id - typed by hand or
 * left over from another screen - must not rename, move, replace or delete
 * it. Installs from before the column existed have no such rows.
 *
 * @param array $file_ids
 * @return bool
 */
function pg_files_include_erp_document($file_ids)
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('files', 'erp_doc_type');
    }

    if (!$ready) {
        return false;
    }

    $ids = array();

    foreach ((array) $file_ids as $file_id) {
        $file_id = (int) $file_id;

        if ($file_id > 0) {
            $ids[$file_id] = $file_id;
        }
    }

    if (count($ids) == 0) {
        return false;
    }

    return ((int) db_value("SELECT COUNT(*) FROM files WHERE id IN (" . implode(',', $ids) . ") AND erp_doc_type <> ''") > 0);
}

// The width of the name columns the File and Catalog Manager and the folder
// screens write: folder.folder_name, page.page_name, products.name,
// product_groups.name and product_groups.short_description are varchar(100).
function pg_name_max_length()
{
    return 100;
}

// True when a name is longer than its column. The session is not strict, so
// a longer value would be cut without an error; the limit is in characters,
// as a utf8mb4 varchar's is.
function pg_name_too_long($name, $limit = null)
{
    if ($limit === null) {
        $limit = pg_name_max_length();
    }

    return mb_strlen((string) $name, 'UTF-8') > (int) $limit;
}
