<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This script can be run from the command line, in order to avoid client & server timeout issues
// for large imports.
// First param is URL.  Second param is tag.  Third param is follow depth.  All are required.
// Example: /usr/bin/php /path/to/livesite/import_design.php http://example.com tag 10
// Example with no tag: /usr/bin/php /path/to/livesite/import_design.php http://example.com '' 10

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

include('init.php');
require('url_to_absolute.php');
$command_line = '';
// If the script was run from the command line, then remember that.
if (!isset($_SERVER['HTTP_HOST'])) {

    $command_line = true;

} else {

    $user = validate_user();

    validate_area_access($user, 'designer');
}

include_once('liveform.class.php');
$liveform = new liveform('import_design');

$action = '';

if ($command_line) {

    $action = 'import';

    $url = $argv[1];
    $import_name = $argv[2];
    $max_link_level = $argv[3];

    $allowed_hostname = str_replace('www.', '', mb_strtolower(parse_url($url, PHP_URL_HOST)));

    // Create an array that will keep track of which URLs have already been scanned.
    $scanned_urls = array();

    $scanned_items = array();

    if (is_numeric($max_link_level) == false) {
        $max_link_level = 0;
    }

    $max_imported_items = 999999;

    $max_scan_level = 20;
    $number_of_items = 0;

    $result = scan(array('url' => $url));

    $_SESSION['software']['design']['import_design']['scanned_items'] = $scanned_items;
}

if (!empty($_REQUEST['submit_button'])) {
    $liveform->add_fields_to_session();
    $action = mb_strtolower($liveform->get_field_value('submit_button'));
}

if ($action and !$command_line) {

    $liveform->validate_required_field('url', lang('URL is required.'));
    $liveform->validate_required_field('max_link_level', lang('Follow Depth is required.'));

    $url = $liveform->get_field_value('url');
    $import_name = $liveform->get_field_value('import_name');

    // If the URL does not start with "http://" or "https://" then add
    // http:// to the front of it, in order to avoid an error.
    if ((mb_substr($url, 0, 7) != 'http://') && (mb_substr($url, 0, 8) != 'https://')) {
        $url = 'http://' . $url;
    }

    // If the action is scan and there is not already an error for the URL field,
    // then check if that URL is online.
    if (
        ($action == 'scan')
        && ($liveform->check_field_error('url') == false)
    ) {
        if (check_if_url_exists($url) == false) {
            $liveform->mark_error('url', lang('Sorry, we could not find a web page to start scanning at that URL. Please try a different URL.'));
        }
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/import_design.php');
    }

    $allowed_hostname = str_replace('www.', '', mb_strtolower(parse_url($url, PHP_URL_HOST)));
}

// If the user did not click the import button yet, then show intro or preview screen.
if ($action != 'import') {
    switch ($action) {
        case '':
            $output_method = 'get';

            $current_max_link_level = $liveform->get_field_value('max_link_level');
            if ($current_max_link_level === '' || $current_max_link_level === false || $current_max_link_level === null) {
                $current_max_link_level = '1';
            }

            $output_fields =
                '<div class="row">
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Import Settings') . '</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 my-2">
                                        <label for="url" class="form-label">' . lang('Website URL') . ' <span class="text-danger">*</span></label>
                                        ' . $liveform->output_field(array('type'=>'text','name'=>'url','id'=>'url','class'=>'form-control','placeholder'=>'https://www.example.com')) . '
                                        <div class="form-text text-muted">' . lang('Enter the full address of the website to import pages and files from.') . '</div>
                                    </div>
                                    <div class="col-12 col-md-6 my-2">
                                        <label for="import_name" class="form-label">' . lang('Tag') . ' <span class="text-muted fw-normal">(' . lang('optional') . ')</span></label>
                                        ' . $liveform->output_field(array('type'=>'text','name'=>'import_name','id'=>'import_name','class'=>'form-control','placeholder'=>lang('e.g. mysite'))) . '
                                        <div class="form-text text-muted">' . lang('Added to imported item names for easy identification.') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <label class="form-label d-block mb-2">' . lang('Scan Mode') . '</label>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="max_link_level" id="depth_0" value="0"' . ($current_max_link_level == '0' ? ' checked' : '') . '>
                                            <label class="form-check-label" for="depth_0">
                                                <strong>' . lang('This page only') . '</strong>
                                                <span class="text-muted d-block small">' . lang('Only the entered URL is scanned along with its assets (images, CSS, JS).') . '</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="max_link_level" id="depth_1" value="1"' . ($current_max_link_level != '0' ? ' checked' : '') . '>
                                            <label class="form-check-label" for="depth_1">
                                                <strong>' . lang('All linked content') . '</strong>
                                                <span class="text-muted d-block small">' . lang('All pages linked from the starting URL are also scanned.') . '</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';

            $output_buttons = '<button type="submit" name="submit_button" value="Scan" class="btn my-1  btn-primary" data-loading-content="' . lang(array('string'=>'Scanning') ) . '"><span class="material-icons me-2">search</span><span class="btn-text">' . lang(array('string'=>'Scan') ) . '</span></button>';

            break;

        case 'scan':
            $output_method = 'post';

            $output_fields =
                get_token_field() . '
                ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'url')) . '
                ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'import_name')) . '
                ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'max_link_level'));

            break;
    }

    echo
        pg_page_shell([
            'title'=> lang('Import My Site'),
            'extra classes'=>'design',
            'icon'=>'design',
            'heading'=>lang('Import My Site'),
            'cancel'=>true
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
            <form action="' . h($_SERVER['PHP_SELF']) . '" method="' . $output_method .'">
                ' . $output_fields;

    if ($action == 'scan') {
        // Force the web server to send output after every echo call
        // so the user can see the scan progress.
        ob_end_flush();

        echo
            '<div class="card my-4">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Scan Results') . '</div>
            <div class="p-3">';

        // set folder name to URL
        $folder_name = $url;
        $folder_name = str_replace('https://', '', $folder_name);
        $folder_name = str_replace('http://', '', $folder_name);

        // Add timestamp to folder name
        $folder_name .= ' ' . get_absolute_time(array('timestamp' => time(), 'format' => 'plain_text', 'timezone_type' => 'site'));

        // If the user supplied an import name, then add it to the folder name
        if ($import_name != '') {
            $folder_name .= '-' . $import_name;
        }

        $folder_name = get_unique_name(array(
            'name' => $folder_name,
            'type' => 'folder'));

        //echo '<div>Folder: Import My Site</div>';
        //echo '<div>Folder: ' . h($folder_name) . '</div>';

        echo '<div class="import-tree">'
            . '<div class="d-flex align-items-center gap-2 py-1"><i class="bi bi-folder2-open text-warning"></i><span class="small fw-medium">Import My Site</span></div>'
            . '<div class="ms-3 d-flex align-items-center gap-2 py-1"><i class="bi bi-folder2 text-warning"></i><span class="small text-secondary">' . h($folder_name) . '</span></div>'
            . '<div class="ms-4 ps-3 border-start">';
        flush();

        // Create an array that will keep track of which URLs have already been scanned.
        $scanned_urls = array();

        $scanned_items = array();

        $max_link_level = $liveform->get_field_value('max_link_level');

        if (is_numeric($max_link_level) == false) {
            $max_link_level = 0;
        }

        $max_imported_items = 2500;

        $max_scan_level = 20;
        $number_of_items = 0;

        $result = scan(array('url' => $url));

        // Store the scanned items in the session, so that we can import them
        // if the user decides to import.
        $_SESSION['software']['design']['import_design']['scanned_items'] = $scanned_items;

        echo '</div></div></div>'; // close border-start + import-tree + card p-3

        if ($result === false) {
            echo '<div class="card-footer"><div class="alert alert-danger mb-0"><strong>' . lang('Sorry, the scan could not be completed at this time.') . '</strong> ' . lang('Please try again later.') . '</div></div>';

            $output_buttons = '<button type="button" class="btn btn-primary" data-loading-content="' . lang(array('string'=>'Restarting') ) . '" onclick="window.location.href=\'import_design.php\'"><span class="material-icons me-2">refresh</span><span class="btn-text">' . lang(array('string'=>'Restart') ) . '</span></button>';
        } else {
            $output_max_imported_items_warning = '';

            if ($number_of_items >= $max_imported_items) {
                $output_max_imported_items_warning = '<br><strong>' . lang('Warning: The scan limit of {var:1} items has been reached.', array('vars' => number_format($max_imported_items))) . '</strong>';
            }

            echo '<div class="card-footer"><div class="alert alert-success mb-0"><strong>' . lang('Scan completed successfully.') . '</strong> ' . lang('Click Import to transfer items to your site, or Restart to adjust settings and scan again.') . $output_max_imported_items_warning . '</div></div>';

            $output_buttons = '
            <button type="submit" name="submit_button" value="Import" class="btn btn-primary" data-loading-content="' . lang(array('string'=>'Importing') ) . '"><span class="material-icons me-2">download</span><span class="btn-text">' . lang(array('string'=>'Import') ) . '</span></button>
            <button type="button" class="btn btn-warning" data-loading-content="' . lang(array('string'=>'Restarting') ) . '" onclick="window.location.href=\'import_design.php\'"><span class="material-icons me-2">refresh</span><span class="btn-text">' . lang(array('string'=>'Restart') ) . '</span></button>';
        }        


        echo '</div>';
    }

    echo
        '       <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                    <div class="container">
                        <div class=" btn-group flex-wrap justify-content-center">
                            ' . $output_buttons . '
                        </div>
                    </div>
                </nav>
            </form>
                </div>
            </div>
        </main>' .
        output_footer();

    $liveform->unmark_errors();
    $liveform->clear_notices();

// Otherwise, the user clicked import, so import items.
} else {

    if (!$command_line) {
        validate_token_field();
    }

    $scanned_items = ($_SESSION['software']['design']['import_design']['scanned_items'] ?? '');

    unset($_SESSION['software']['design']['import_design']['scanned_items']);

    // If there are no scanned items to import then add error
    // and forward user back to original screen.
    if (!$scanned_items) {
        $message = lang('Sorry, we could not find any items to import. Please try scanning again.');

        if ($command_line) {
            exit($message);
        } else {
            $liveform->mark_error('', $message);
            go(PATH . SOFTWARE_DIRECTORY . '/import_design.php');
        }
    }

    // Try to find an existing "Import My Site" folder.
    $import_my_site_folder_id = db_value("SELECT folder_id FROM folder WHERE folder_name = 'Import My Site'");

    // If an "Import My Site" folder was not found, then create it.
    if (!$import_my_site_folder_id) {
        $root_folder_id = db_value("SELECT folder_id FROM folder WHERE folder_parent = '0'");

        db(
            "INSERT INTO folder (
                folder_name,
                folder_parent,
                folder_level,
                folder_order,
                folder_user,
                folder_timestamp)
            VALUES (
                'Import My Site',
                '$root_folder_id',
                '1',
                '0',
                '" . USER_ID . "',
                UNIX_TIMESTAMP())");

        $import_my_site_folder_id = mysqli_insert_id(db::$con);
    }

    $import_my_site_folder_level = db_value("SELECT folder_level FROM folder WHERE folder_id = '$import_my_site_folder_id'");

    $folder_level = $import_my_site_folder_level + 1;

    // set folder name to URL
    $folder_name = $url;
    $folder_name = str_replace('https://', '', $folder_name);
    $folder_name = str_replace('http://', '', $folder_name);

    // Add timestamp to folder name
    $folder_name .= ' ' . get_absolute_time(array('timestamp' => time(), 'format' => 'plain_text', 'timezone_type' => 'site'));

    // If the user supplied an import name, then add it to the folder name
    if ($import_name != '') {
        $folder_name .= '-' . $import_name;
    }

    $folder_name = get_unique_name(array(
        'name' => $folder_name,
        'type' => 'folder'));
    
    // Create folder for this import.
    db(
        "INSERT INTO folder (
            folder_name,
            folder_parent,
            folder_level,
            folder_order,
            folder_user,
            folder_timestamp)
        VALUES (
            '" . escape($folder_name) . "',
            '$import_my_site_folder_id',
            '$folder_level',
            '0',
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    $folder_id = mysqli_insert_id(db::$con);

    // Create an array of imported items that we will use
    // to keep track of which scanned items were actually created.
    $imported_items = array();

    // Loop through the scanned items in order to create them.
    foreach ($scanned_items as $item) {

        $url = $item['url'];
        $base_url = $item['base_url'];
        $type = $item['type'];
        $name = $item['name'];
        $extension = $item['extension'];
        $design = $item['design'];

        $content = fetch_url_content($url);

        // If the download failed, then skip to the next item.
        if ($content === false) {
            continue;
        }

        if ($item['type'] == 'html') {

            // If this is a liveSite page that we are scanning, then remove
            // dynamic liveSite code that we don't want to import or scan.
            // This avoids the frontend JS, jquery files, and etc. from being imported.
            // We don't want to import them because they are dynamically outputted.
            $content = remove_dynamic_code_blocks($content);
            
            // If there is a base tag in the content, then get base URL from the tag.
            // We will need this in order to prepare URLs for images and etc.
            // Also we remove the base tag from the content, because we are going to change links.
            if (preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](.*?)["\'].*?>/is', $content, $match)) {
                $base_url = trim($match[1]);

                // Convert the base url to an absolute URL, in case it is relative.
                $base_url = resolve_url_for_scan($url, $base_url) ?: $url;

                $base_hostname = str_replace('www.', '', mb_strtolower(parse_url($base_url, PHP_URL_HOST)));

                // If the base hostname is the allowed hostname,
                // then remove base tag because we don't want to use that in this system.
                // We leave the base tag if the hostname is an external site,
                // because we are not going to change/crawl URLs for items in that case,
                // and we want the references to those items to still work after
                // this HTML is imported into this system.
                if ($base_hostname == $allowed_hostname) {
                    $content = preg_replace('/<\s*base\s+[^>]*href\s*=\s*["\'].*?["\'].*?>/is', '', $content);
                }
            }

            // Remove all meta charset tags from the HTML,
            // because we are going to add our own utf-8 charset tag,
            // so it is compatible with this system.
            $content = preg_replace('/<meta\s+[^>]*charset.*?>/i', '', $content);

            // Get the title for the page so we can update the page property later.
            preg_match('/<title\s*>(.*?)<\/title>/i', $content, $matches);

            $title = '';

            // If a title tag was found, then remember title and remove title tags.
            if ($matches[0] != '') {
                $title = unhtmlspecialchars(trim($matches[1]));

                // Remove all title tags from the HTML.
                $content = preg_replace('/<title>.*?<\/title>/i', '', $content);
            }

            // Get the meta description for the page so we can update the page property later.
            preg_match('/<meta\s+[^>]*name\s*=\s*["\']\s*description\s*["\'].*?>/i', $content, $matches);

            $meta_description = '';

            // If a meta description was found, then remember it and remove meta description tags.
            if ($matches[0] != '') {
                preg_match('/content\s*=\s*["\'](.*?)["\']/i', $matches[0], $matches);

                $meta_description = unhtmlspecialchars(trim($matches[1]));

                // Remove all meta description tags from the HTML.
                $content = preg_replace('/<meta\s+[^>]*name\s*=\s*["\']\s*description\s*["\'].*?>/i', '', $content);
            }

            // Get the meta keywords for the page so we can update the page property later.
            preg_match('/<meta\s+[^>]*name\s*=\s*["\']\s*keywords\s*["\'].*?>/i', $content, $matches);

            $meta_keywords = '';

            // If a meta keywords was found, then remember it and remove meta keywords tags.
            if ($matches[0] != '') {
                preg_match('/content\s*=\s*["\'](.*?)["\']/i', $matches[0], $matches);

                $meta_keywords = unhtmlspecialchars(trim($matches[1]));

                // Remove all meta keywords tags from the HTML.
                $content = preg_replace('/<meta\s+[^>]*name\s*=\s*["\']\s*keywords\s*["\'].*?>/i', '', $content);
            }

            // Now that we have removed all necessary title and meta tags,
            // let's add our own head content.
            $head_content =
                '    <meta charset="utf-8">' . "\n" .
                '    <title></title>' . "\n" .
                '    <meta_tags></meta_tags>';

            // If there is a head tag, then add the head content after that.
            if (preg_match('/<\bhead\b.*?>/i', $content) != 0) {
                $content = preg_replace('/(<\bhead\b.*?>)/i', '$1' . "\n" . $head_content, $content);
                
            // Otherwise, if there is an html tag, then add the head content after that.
            } else if (preg_match('/<html.*?>/i', $content) != 0) {
                $content = preg_replace('/(<html.*?>)/i', '$1' . "\n" . $head_content, $content);

            // Otherwise, if there is a doctype, then add the head content after that.
            } else if (preg_match('/<!doctype html.*?>/i', $content) != 0) {
                $content = preg_replace('/(<!doctype html.*?>)/i', '$1' . "\n" . $head_content, $content);
            }

            $page_name = get_unique_name(array(
                'name' => $name,
                'type' => 'page'));

            $name = $page_name;

            $style_name = get_unique_name(array(
                'name' => $name,
                'type' => 'style'));

            // Create style.
            db(
                "INSERT INTO style (
                    style_name,
                    style_type,
                    style_code,
                    style_user,
                    style_timestamp)
                VALUES (
                    '" . escape($style_name) . "',
                    'custom',
                    '" . escape($content) . "',
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP())");

            $style_id = mysqli_insert_id(db::$con);

            // Create the page. The keywords scraped out of the imported markup go into
            // the promote on keyword field, because that is the one a page still reads.
            db(
                "INSERT INTO page (
                    page_name,
                    page_folder,
                    page_style,
                    page_title,
                    page_meta_description,
                    page_search_keywords,
                    page_user,
                    page_timestamp,
                    comments_disallow_new_comment_message)
                VALUES (
                    '" . escape($page_name) . "',
                    '$folder_id',
                    '$style_id',
                    '" . escape($title) . "',
                    '" . escape($meta_description) . "',
                    '" . escape(merge_keyword_lists($meta_keywords, '')) . "',
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP(),
                    'We\'re sorry. New comments are no longer being accepted.')");

            $page_id = mysqli_insert_id(db::$con);

            $imported_items[$url] = array(
                'url' => $url,
                'base_url' => $base_url,
                'name' => $name,
                'type' => $type,
                'page_id' => $page_id,
                'style_id' => $style_id);

            if ($command_line) {
                echo 'Imported Item:' . "\n";
                print_r($imported_items[$url]);
                echo 'Imported Item Count: ' . count($imported_items) . "\n";

                // Add a 1 second delay in order to avoid firewalls/Cloudflare/WordFence detecting
                // and blocking this import.
                sleep(1);
            }

        // Otherwise this is a regular file, so create file.
        } else {

            $old_file_backed_up = false;

            $sql_folder_id = $folder_id;
            $sql_description = '';

            if ($design == true) {
                $sql_design = '1';
            } else {
                $sql_design = '0';
            }

            // If the user supplied a tag, then check to see if there is an existing
            // file with the name, because if there is, then we are going to backup
            // the old file and let this new file have the name.
            if ($import_name != '') {

                $old_file = db_item(
                    "SELECT
                        id,
                        folder AS folder_id,
                        description,
                        design
                    FROM files WHERE name = '" . e($name) . "'");

                // If an old file was found, then rename it.
                if ($old_file) {

                    // If there is an extension, then prepare backup name in a certain way.
                    if (mb_strpos($name, '.') !== false) {
                        $name_without_extension = mb_substr($name, 0, mb_strrpos($name, '.'));
                        $old_extension = mb_substr($name, mb_strrpos($name, '.') + 1);
                        $backup_name = $name_without_extension . '-backup.' . $old_extension;

                    // Otherwise there is not an extension, so prepare backup name differently.
                    } else {
                        $backup_name = $name . '-backup';
                    }

                    // Get a unique version of the backup name.
                    $backup_name = get_unique_name(array(
                        'name' => $backup_name,
                        'type' => 'file'));

                    // Update old file to have backup name in db.
                    db(
                        "UPDATE files 
                        SET 
                            name = '" . e($backup_name) . "',
                            user = '" . USER_ID . "',
                            timestamp = UNIX_TIMESTAMP()
                        WHERE id = '" . $old_file['id'] . "'");

                    // Update old file to have backup name on file system.
                    @rename(FILE_DIRECTORY_PATH . '/' . $name, FILE_DIRECTORY_PATH . '/' . $backup_name);

                    log_activity('file was renamed during import my site (' . $name . ' -> ' . $backup_name . ')');

                    $old_file_backed_up = true;

                    // Set the properties for the new file to the old file properties.
                    $sql_folder_id = $old_file['folder_id'];
                    $sql_description = $old_file['description'];
                    $sql_design = $old_file['design'];

                }

            }

            // If an old file was not backed up, then that means we need to a create
            // a new, unique name for this file.
            if (!$old_file_backed_up) {
                $name = get_unique_name(array(
                    'name' => $name,
                    'type' => 'file'));
            }

            @file_put_contents(FILE_DIRECTORY_PATH . '/' . $name, $content);

            db(
                "INSERT INTO files (
                    name,
                    folder,
                    description,
                    type,
                    size,
                    design,
                    user,
                    timestamp)
                VALUES (
                    '" . escape($name) . "',
                    '" . e($sql_folder_id) . "',
                    '" . e($sql_description) . "',
                    '" . escape($extension) . "',
                    '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $name)) . "',
                    '" . e($sql_design) . "',
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP())");

            $file_id = mysqli_insert_id(db::$con);

            $imported_items[$url] = array(
                'url' => $url,
                'base_url' => $base_url,
                'name' => $name,
                'type' => $type,
                'file_id' => $file_id);

            if ($command_line) {
                echo 'Imported Item:' . "\n";
                print_r($imported_items[$url]);
                echo 'Imported Item Count: ' . count($imported_items) . "\n";

                // Add a 1 second delay in order to avoid firewalls/Cloudflare/WordFence detecting
                // and blocking this import.
                sleep(1);
            }
        }
    }

    require('update_imported_items.php');
    update_imported_items($imported_items);

    if ($command_line) {
        exit('Complete');
    }

    $liveform->remove_form();

    $liveform_view_pages = new liveform('view_pages');
    $liveform_view_pages->add_notice(lang('All scanned items have been imported successfully.'));

    go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
}

function get_filetype_icon($extension) {
    $map = array(
        'aac'=>'bi-filetype-aac', 'ai'=>'bi-filetype-ai', 'bmp'=>'bi-filetype-bmp',
        'css'=>'bi-filetype-css', 'csv'=>'bi-filetype-csv', 'doc'=>'bi-filetype-doc',
        'docx'=>'bi-filetype-docx', 'exe'=>'bi-filetype-exe', 'gif'=>'bi-filetype-gif',
        'heic'=>'bi-filetype-heic', 'html'=>'bi-filetype-html', 'htm'=>'bi-filetype-html',
        'java'=>'bi-filetype-java', 'jpg'=>'bi-filetype-jpg', 'jpeg'=>'bi-filetype-jpg',
        'js'=>'bi-filetype-js', 'json'=>'bi-filetype-json', 'jsx'=>'bi-filetype-jsx',
        'mov'=>'bi-filetype-mov', 'mp3'=>'bi-filetype-mp3', 'mp4'=>'bi-filetype-mp4',
        'otf'=>'bi-filetype-otf', 'pdf'=>'bi-filetype-pdf', 'php'=>'bi-filetype-php',
        'ppt'=>'bi-filetype-ppt', 'pptx'=>'bi-filetype-pptx', 'psd'=>'bi-filetype-psd',
        'raw'=>'bi-filetype-raw', 'sass'=>'bi-filetype-sass', 'scss'=>'bi-filetype-scss',
        'sql'=>'bi-filetype-sql', 'svg'=>'bi-filetype-svg', 'tiff'=>'bi-filetype-tiff',
        'ttf'=>'bi-filetype-ttf', 'txt'=>'bi-filetype-txt', 'wav'=>'bi-filetype-wav',
        'woff'=>'bi-filetype-woff', 'woff2'=>'bi-filetype-woff', 'xls'=>'bi-filetype-xls',
        'xlsx'=>'bi-filetype-xlsx', 'xml'=>'bi-filetype-xml', 'yml'=>'bi-filetype-yml',
    );
    return isset($map[$extension]) ? $map[$extension] : 'bi-images';
}

function scan($properties) {

    global $command_line;
    global $import_name;
    global $scanned_urls;
    global $scanned_items;
    global $allowed_hostname;
    global $max_scan_level;
    global $max_link_level;
    global $max_imported_items;
    global $number_of_items;




    if(isset($properties['url'])){
        $url = trim($properties['url']);
    }
    if(isset($properties['base_url'])){
        $base_url = trim($properties['base_url']);
    }
    $scan_level = isset($properties['scan_level']) ? $properties['scan_level'] : 0;
    $link_level = isset($properties['link_level']) ? $properties['link_level'] : 0;





    if (isset($scan_level) && is_numeric($scan_level) == false) {
        $scan_level = 0;
    }

    if (isset($link_level) && is_numeric($link_level) == false) {
        $link_level = 0;
    }

    if ($number_of_items >= $max_imported_items) {
        return false;
    }

    // If the URL does not start with "http://" or "https://" then return false.
    // We do this for security reasons, because we call file_get_contents()
    // below and we don't want someone to be able to crawl a local file.
    if ((mb_substr($url, 0, 7) != 'http://') && (mb_substr($url, 0, 8) != 'https://')) {
        return false;
    }

    $hostname = str_replace('www.', '', mb_strtolower(parse_url($url, PHP_URL_HOST)));

    if ($hostname != $allowed_hostname) {
        return false;
    }

    // Remove bookmark from the URL, if one exists,
    // because we do not care about that, and it might cause issues
    // later when we try to find things by the URL.
    $url = strtok($url, '#');

    // Get the URL parts in order to just get the path and query string
    // in order to determine if the url has already been crawled.
    $url_parts = parse_url($url);

    $scanned_url = $url_parts['path'];

    if ($url_parts['query'] != '') {
        $scanned_url .= '?' . $url_parts['query'];
    }

    // If this URL has already been crawled, then don't crawl it again.
    if (isset($scanned_urls[$scanned_url]) && $scanned_urls[$scanned_url] == true) {
        return;
    }

    // Remember that this URL has now been crawled.
    $scanned_urls[$scanned_url] = true;

    // If this is not the first URL being crawled, and the URL is for the root of the site,
    // then don't crawl it.  We found that template sites often link back to the root
    // of their site and we don't normally want to crawl that root URL.
    if (
        ($scan_level > 0)
        &&
        (
            ($scanned_url == '')
            || ($scanned_url == '/')
        )
    ) {
        return false;
    }

    $extension = pathinfo($url_parts['path'], PATHINFO_EXTENSION);
    $extension_lowercase = mb_strtolower($extension);

    // For known binary file types, skip fetching during scan entirely.
    // The file will be downloaded on import. This avoids slow/blocked HEAD requests
    // and eliminates the double-request pattern (HEAD + GET) for every asset.
    $binary_extensions = array(
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff',
        'svg', 'ico', 'ttf', 'otf', 'eot', 'woff', 'woff2',
        'mp4', 'mp3', 'wav', 'ogg', 'avi', 'mov', 'wmv',
        'pdf', 'zip', 'rar', 'gz', 'tar', '7z', 'swf', 'flv',
    );

    $content = null;
    $content_type = '';

    if (in_array($extension_lowercase, $binary_extensions)) {
        // Known binary type — no HTTP request needed during scan phase
        $type = 'general';

    } else {
        // Text file or unknown extension (HTML page without .html) —
        // do a single GET request to get both content and Content-Type
        $response = fetch_url_response($url);

        if ($response === false) {
            return false;
        }

        $content = $response['content'];
        $raw_ct = $response['content_type'];

        $charset_position = mb_strpos($raw_ct, ';');
        if ($charset_position !== false) {
            $raw_ct = mb_substr($raw_ct, 0, $charset_position);
        }
        $content_type = mb_strtolower(trim($raw_ct));

        if (
            ($content_type == 'text/html')
            || ($content_type == 'application/xhtml+xml')
            || ($extension_lowercase == 'html')
            || ($extension_lowercase == 'htm')
            || ($extension_lowercase == 'xhtml')
        ) {
            $type = 'html';

        } else if (
            ($content_type == 'text/css')
            || ($extension_lowercase == 'css')
        ) {
            $type = 'css';

        } else if (
            ($content_type == 'application/javascript')
            || ($content_type == 'application/x-javascript')
            || ($content_type == 'text/javascript')
            || ($extension_lowercase == 'js')
        ) {
            $type = 'js';

        } else {
            $type = 'general';
        }
    }

    // If the type is HTML but the extension is for some other type of file,
    // then that means that something is probably wrong, so let's not import this URL.
    // This might happen if, for example, a CSS file references a PNG file,
    // but that PNG file does not exist and the server serves an HTML error page
    // without a correct 404 response code.
    if (
        ($type == 'html')
        &&
        (
            ($extension_lowercase == 'css')
            || ($extension_lowercase == 'js')
            || ($extension_lowercase == 'gif')
            || ($extension_lowercase == 'jpg')
            || ($extension_lowercase == 'jpeg')
            || ($extension_lowercase == 'png')
        )
    ) {
        return false;
    }

    $number_of_items++;

    // Prepare name for item as it will appear in our system.

    $name = basename($url_parts['path']);

    $name = rawurldecode($name);

    // If this is an HTML item then remove extension if one exists,
    // because we don't want the page or style name to have an extension.
    // We preserve all content before the first period, so this means
    // that multiple extensions will be removed (e.g. example.min.html -> example)
    if ($type == 'html') {
        $name = strtok($name, '.');
        $extension = '';
    }

    if ($name == '') {
        $name = 'home';
    }

    if ($url_parts['query'] != '') {
        // Sanitize query string for use in a file name: replace any character
        // that is not alphanumeric, dash, or underscore with a dash,
        // then collapse consecutive dashes and trim leading/trailing dashes.
        // This prevents characters like = from causing file creation failures.
        $query_for_name = preg_replace('/[^a-zA-Z0-9_]/', '-', $url_parts['query']);
        $query_for_name = preg_replace('/-+/', '-', $query_for_name);
        $query_for_name = trim($query_for_name, '-');

        // If there is an extension in the name,
        // then place the query string before the extension.
        if ($extension != '') {
            $extension_position = mb_strpos($name, '.');

            $name = mb_substr($name, 0, $extension_position) . '-' . $query_for_name . mb_substr($name, $extension_position);

        // Otherwise there is not an extension,
        // so just place the query string after the name.
        } else {
            $name .= '-' . $query_for_name;
        }
    }

    // If the user entered an import name, then add the import name to the item name.
    if ($import_name != '') {
        // If there is an extension in the name,
        // then place the import name before the extension.
        if ($extension != '') {
            $extension_position = mb_strpos($name, '.');

            $name = mb_substr($name, 0, $extension_position) . '-' . $import_name . mb_substr($name, $extension_position);

        // Otherwise there is not an extension,
        // so just place the import name after the name.
        } else {
            $name .= '-' . $import_name;
        }
    }

    $name = str_replace(' ', '-', $name);
    $name = str_replace('&', '-', $name);

    // If this is an HTML file then deal with that.
    if ($type == 'html') {

        // Note: we intentionally do NOT remove dynamic code blocks here during the scan phase,
        // because those blocks may contain CSS/JS/image links we want to discover.
        // Dynamic blocks are removed during the import phase when the content is saved.

        $base_url = '';

        // If there is a base tag in the content, then get base URL from the tag.
        // We will need this in order to prepare URLs for images and etc.
        // Also we remove the base tag from the content, because we are going to change links.
        if (preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](.*?)["\'].*?>/is', $content, $match)) {
            $base_url = trim($match[1]);

            // Convert the base url to an absolute URL, in case it is relative.
            $base_url = resolve_url_for_scan($url, $base_url) ?: $url;
        }

        // If a URL from a base tag was not found,
        // then set base URL to the page URL.
        if ($base_url == '') {
            $base_url = $url;
        }

        // Store a new name, before we create a unique version of the name,
        // so we can store the new name in the array and won't have to generate
        // it again during import.
        $new_name = $name;

        $page_name = get_unique_name(array(
            'name' => $name,
            'type' => 'page'));

        $name = $page_name;

        $style_name = get_unique_name(array(
            'name' => $name,
            'type' => 'style'));

        echo '<div class="tree-page-group mb-1">'
            . '<div class="d-flex gap-2 align-items-center py-1">'
            . '<span class="text-muted small" style="min-width:2rem;text-align:right">' . $number_of_items . '</span>'
            . '<i class="bi bi-window me-1 pages-color"></i>'
            . '<span class="" title="' . lang('Page Style') . ': ' . h($style_name) . '">' . h($page_name) . '</span>'
            . '</div>'
            . '<div class="tree-page-children ms-4 ps-3 border-start border-secondary-subtle">';
        flush();

        //echo '<div>Page:' . h($page_name) . '</div>';
        //echo '<div>Page Style:' . h($style_name) . '</div>';

        // Remember that we have now scanned this item.
        $scanned_items[$url] = array(
            'url' => $url,
            'base_url' => $base_url,
            'type' => $type,
            'name' => $new_name);

        if ($command_line) {
            echo 'Scanned Item:' . "\n";
            print_r($scanned_items[$url]);
            echo 'Scanned Item Count: ' . count($scanned_items) . "\n";

            // Add a 1 second delay in order to avoid firewalls/Cloudflare/WordFence detecting
            // and blocking this import.
            sleep(1);
        }

    // Otherwise this is some other type of file, so deal with that.
    } else {
        // If this is a CSS file, then set the base URL to the URL for the CSS file.
        if ($type == 'css') {
            $base_url = $url;

        // Otherwise if this is a JS file, then deal with base url differently.
        } else if ($type == 'js') {
            // Generally a base url is passed into this function from the parent HTML file
            // that included the js file, however if that did not happen,
            // then set the base url to the current URL of this JS file.
            if ($base_url == '') {
                $base_url = $url;
            }
        }

        $name = prepare_file_name($name);

        // Store a new name, before we create a unique version of the name,
        // so we can store the new name in the array and won't have to generate
        // it again during import.
        $new_name = $name;

        // If the user did not enter a tag, then get a unique name,
        // because for that situation we create a new unique name for files.
        if ($import_name == '') {
            $name = get_unique_name(array(
                'name' => $name,
                'type' => 'file'));
        }

        // If this is a design file then remember that for when we import the item,
        // and output the file name in the appropriate column.
        if (
            ($extension_lowercase == 'css')
            || ($extension_lowercase == 'js')
            || ($extension_lowercase == 'svg')
            || ($extension_lowercase == 'ttf')
            || ($extension_lowercase == 'otf')
            || ($extension_lowercase == 'eot')
            || ($extension_lowercase == 'woff')
            || ($extension_lowercase == 'woff2')
            || ($extension_lowercase == 'ico')
        ) {
            $design = true;

            echo '<div class="d-flex gap-2 align-items-center py-1"><span class="text-muted small" style="min-width:2rem;text-align:right">' . $number_of_items . '</span><i class="bi ' . get_filetype_icon($extension_lowercase) . ' me-1 design-color"></i><span class="badge">' . h($name) . '</span></div>';
            flush();

        } else {
            $design = false;

            echo '<div class="d-flex gap-2 align-items-center py-1"><span class="text-muted small" style="min-width:2rem;text-align:right">' . $number_of_items . '</span><i class="bi ' . get_filetype_icon($extension_lowercase) . ' me-1 files-color"></i><span class="badge">' . h($name) . '</span></div>';
            flush();
        }

        //echo '<div>File:' . h($name) . '</div>';

        // Remember that we have now scanned this item.
        $scanned_items[$url] = array(
            'url' => $url,
            'base_url' => $base_url,
            'type' => $type,
            'name' => $new_name,
            'extension' => $extension,
            'design' => $design);

        if ($command_line) {
            echo 'Scanned Item:' . "\n";
            print_r($scanned_items[$url]);
            echo 'Scanned Item Count: ' . count($scanned_items) . "\n";

            // Add a 1 second delay in order to avoid firewalls/Cloudflare/WordFence detecting
            // and blocking this import.
            sleep(1);
        }
    }

    // If we have not reached the max imported items yet,
    // and we have not reached the max crawl level yet,
    // and this is an HTML, CSS, or JS file, then crawl child items.
    if (
        ($number_of_items < $max_imported_items)
        && (isset($scan_level) && $scan_level < $max_scan_level)
        &&
        (
            ($type == 'html')
            || ($type == 'css')
            || ($type == 'js')
        )
    ) {
        $new_scan_level = $scan_level + 1;

        // If the type is html, then crawl various items in the HTML.
        if ($type == 'html') {


            preg_match_all('/<\s*img\s+[^>]*src\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                    'scan_level' => $new_scan_level));
            }

            preg_match_all('/<\s*link\s+[^>]*href\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                preg_match('/rel\s*=\s*["\'](.*?)["\']/is', $match[0], $match_rel);

                $rel = mb_strtolower(trim($match_rel[1]));

                // If the link tag is for a stylesheet or favicon,
                // then continue to crawl it.
                if (
                    ($rel == 'stylesheet')
                    || ($rel == 'alternate stylesheet')
                    || ($rel == 'icon')
                    || ($rel == 'shortcut icon')
                ) {
                    scan(array(
                        'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                        'scan_level' => $new_scan_level));
                }
            }

            preg_match_all('/<\s*script\s+[^>]*src\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                    'base_url' => $url,
                    'scan_level' => $new_scan_level));
            }

            preg_match_all('/<\s*source\s+[^>]*src\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                    'scan_level' => $new_scan_level));
            }

            preg_match_all('/<\s*embed\s+[^>]*src\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                    'scan_level' => $new_scan_level));
            }

            preg_match_all('/<\s*object\s+[^>]*data\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, unhtmlspecialchars(trim($match[1]))),
                    'scan_level' => $new_scan_level));
            }

            if (isset($link_level) && $link_level < $max_link_level) {
                $new_link_level = $link_level + 1;

                preg_match_all('/<\s*a\s+[^>]*href\s*=\s*["\'](.*?)["\'].*?>/is', $content, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $item_url = unhtmlspecialchars(trim($match[1]));

                    // If the anchor only contains a bookmark (e.g. #example),
                    // then don't scan this anchor.
                    if (mb_substr($item_url, 0, 1) == '#') {
                        continue;
                    }

                    $item_url = resolve_url_for_scan($base_url, $item_url);

                    scan(array(
                        'url' => $item_url,
                        'scan_level' => $new_scan_level,
                        'link_level' => $new_link_level));
                }
            }
        }

        // If the type is HTML or CSS then scan for CSS items.
        if (($type == 'html') || ($type == 'css')) {
            // Crawl all URL references first.  This includes import statements
            // that use "url" syntax, background images, and etc.

            preg_match_all('/url\(\s*["\']?(.*?)["\']?\s*\)/i', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, trim($match[1])),
                    'scan_level' => $new_scan_level));
            }

            // Crawl import statements that use @import "example.css" syntax without "url()".

            preg_match_all('/@import\s*["\'](.*?)["\']/i', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                scan(array(
                    'url' => resolve_url_for_scan($base_url, trim($match[1])),
                    'scan_level' => $new_scan_level));
            }
        }

        // If the type is HTML or JS then scan resources in the JS.
        // This means that we look for files in <script> tags, inline JS (e.g. onmouseover="example")
        // and in the JS files themselves.
        if (($type == 'html') || ($type == 'js')) {
            // We just look for any resource enclosed in quotes.
            // We have added support for an optional query string part (e.g. ?name=value)
            // that can appear after the file extension.
            preg_match_all('/["\']\s*([^"\']*\.(css|js|gif|jpg|jpeg|png)(\?.*?)?)\s*["\']/i', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $pass_base_url = '';

                // If this is a JS file, then pass the base URL
                // when we scan it, because paths in JS files are relative to the parent HTML.
                if (mb_strtolower($match[2]) == 'js') {
                    $pass_base_url = $base_url;
                }

                scan(array(
                    'url' => resolve_url_for_scan($base_url, trim($match[1])),
                    'base_url' => $pass_base_url,
                    'scan_level' => $new_scan_level));
            }
        }
    }

    // Close the page group wrapper opened when this page was output.
    if ($type == 'html') {
        echo '</div></div>'; // close tree-page-children + tree-page-group
        flush();
    }
}

function get_protocol_relative_url($url)
{
    if (mb_substr($url, 0, 7) == 'http://') {
        return mb_substr($url, 5);

    } else if (mb_substr($url, 0, 8) == 'https://') {
        return mb_substr($url, 6);

    } else {
        return $url;
    }
}

function resolve_url_for_scan($base, $relative)
{
    $relative = trim($relative);

    if ($relative === '') return false;
    if ($relative[0] === '#') return false;
    if (stripos($relative, 'javascript:') === 0) return false;
    if (stripos($relative, 'data:') === 0) return false;

    // Already absolute
    if (preg_match('/^https?:\/\//i', $relative)) return $relative;

    // Protocol-relative
    if (substr($relative, 0, 2) === '//') {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $relative;
    }

    $scheme   = parse_url($base, PHP_URL_SCHEME) ?: 'https';
    $host     = parse_url($base, PHP_URL_HOST) ?: '';
    $port     = parse_url($base, PHP_URL_PORT);
    $port_str = $port ? ':' . $port : '';

    // Root-relative
    if ($relative[0] === '/') {
        return $scheme . '://' . $host . $port_str . $relative;
    }

    // Relative path — resolve against base directory
    $base_path = parse_url($base, PHP_URL_PATH) ?: '/';
    $dir = substr($base_path, 0, (int)strrpos($base_path, '/') + 1);
    if ($dir === '') $dir = '/';

    $parts    = explode('/', $dir . $relative);
    $resolved = array();
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($resolved);
        } elseif ($part !== '.') {
            $resolved[] = $part;
        }
    }

    return $scheme . '://' . $host . $port_str . implode('/', $resolved);
}

function remove_dynamic_code_blocks($content)
{
    $start_marker = '<!-- Start liveSite dynamic code -->';
    $end_marker = '<!-- End liveSite dynamic code -->';

    while (true) {
        $start_pos = strpos($content, $start_marker);
        if ($start_pos === false) {
            break;
        }
        $end_pos = strpos($content, $end_marker, $start_pos);
        if ($end_pos === false) {
            break;
        }
        $content = substr($content, 0, $start_pos) . substr($content, $end_pos + strlen($end_marker));
    }

    return $content;
}

function fetch_url_response($url)
{
    if (!function_exists('curl_init')) {
        $content = @file_get_contents($url, false, get_http_stream_context());
        if ($content === false) {
            return false;
        }
        // Try to extract Content-Type from $http_response_header
        $content_type = '';
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $content_type = trim(substr($header, 13));
                }
            }
        }
        return array('content' => $content, 'content_type' => $content_type);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept all encodings (gzip, deflate) and auto-decompress
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($content === false || $http_code >= 400) {
        return false;
    }

    return array('content' => $content, 'content_type' => $content_type !== null ? $content_type : '');
}

function fetch_url_content($url)
{
    $response = fetch_url_response($url);
    if ($response === false) {
        return false;
    }
    return $response['content'];
}

function get_http_stream_context()
{
    return stream_context_create(array(
        'http' => array(
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'follow_location' => 1,
            'timeout' => 30,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    ));
}

function check_if_url_exists($url)
{
    if (!function_exists('curl_init')) {
        // The third parameter (context) was only added in PHP 7.1.
        if (version_compare(PHP_VERSION, '7.1.0', '>=')) {
            $headers = @get_headers($url, 1, get_http_stream_context());
        } else {
            $headers = @get_headers($url, 1);
        }
        if ($headers == false) {
            return false;
        }
        $response_parts = explode(' ', $headers[0]);
        $response_code = $response_parts[1];
        $first_char = mb_substr($response_code, 0, 1);
        if ($first_char == '2' || $first_char == '3') {
            return $headers;
        }
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // If HEAD is not supported, retry with GET
    if ($http_code == 405) {
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 400) {
        return array('Content-Type' => $content_type !== null ? $content_type : '');
    }

    return false;
}
?>