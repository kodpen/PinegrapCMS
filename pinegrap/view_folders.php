<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The file manager: folders, pages and files on one full-width screen, the
// way a desktop file manager would show them — breadcrumb navigation, grid
// and list views, a lazy folder tree with its own context menu, drag & drop
// moving, copy & paste, inline renaming, folder access control and settings,
// inline previews, a recycle bin and a guarded recursive folder delete. All
// data comes from the "file_explorer" JSON sub-actions in api.php
// (view_folder_and_files_f.php); destructive page and folder deletes post to
// the proven edit_pages.php / edit_folder.php handlers instead of
// reimplementing their cleanup.
//
// It grew up at view_folder_and_files.php and then took over this address
// from the classic folder tree screen; the old address now redirects here.

include('init.php');
include_once('liveform.class.php');

$user = validate_user();

// This screen serves two areas out of one shell.  view_product_groups.php sets
// $explorer_area before including this file, which boots the same toolbar,
// tree, grid, preview and context menu into the store instead of the folder
// tree.  Splitting them into two copies of four thousand lines was the other
// option; every fix would then have had to be made twice.
$explorer_area = ((isset($explorer_area)) && ($explorer_area == 'catalog')) ? 'catalog' : 'files';

// Each area is gated on the rights that area is about.  The file manager asks
// for folder edit rights the way it always has.  The store is not part of the
// folder tree, so asking for folder rights there would shut out exactly the
// person "manage all commerce" was granted to.  Every other commerce screen --
// products, orders, duplicates -- gates on commerce rights alone; this one now
// does too.
if ($explorer_area == 'catalog') {
    validate_ecommerce_access($user);
} else {
    validate_area_access($user, 'user');
}

// This screen no longer links across to the other area at all -- neither from
// the sidebar nor from the menu at the top right -- so it no longer has to
// work out who may go there.  The left menu carries both entries and already
// draws each of them for the people who may open it.

// Backup download. data/backups sits outside the web root on purpose, so a
// backup can only leave through a screen that checks who is asking: manager
// area, valid token, and a path that resolves inside the backup directory.
if (isset($_GET['backup_download'])) {

    validate_area_access($user, 'manager');
    validate_token_field();

    require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');

    $backup_path = pg_explorer_backup_path($_GET['backup_download']);

    if (($backup_path == '') || (is_file($backup_path) == false) || (is_readable($backup_path) == false)) {
        output_error(lang('Sorry, the backup folder could not be found.'));
    }

    log_activity(lang(array('string' => '{var:1} was downloaded', 'vars' => basename($backup_path))), $_SESSION['sessionusername']);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backup_path) . '"');
    header('Content-Length: ' . filesize($backup_path));
    header('Pragma: no-cache');
    header('Expires: 0');

    $handle = fopen($backup_path, 'rb');

    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);
    }

    exit();
}

// Notices land in different session forms depending on which legacy handler
// processed the action, so this screen renders all three: its own (posts that
// pass from=view_folder_and_files), page deletes (view_pages) and folder
// deletes (view_folders).
$liveform = new liveform('view_folder_and_files');
$liveform_pages = new liveform('view_pages');
$liveform_folders = new liveform('view_folders');

// Deep link support: view_folders.php?folder=123 opens that folder;
// ?view=files opens the flat all-files view.
$initial_folder_id = isset($_GET['folder']) ? (int) $_GET['folder'] : 0;
$initial_group_id = isset($_GET['group']) ? (int) $_GET['group'] : 0;
$initial_mode = 'browse';

if ($explorer_area == 'catalog') {

    // ?view=products is the store's flat list, the same idea as the Files and
    // Pictures views: every product, whichever group it sits in. ?view=bin is
    // the recycle bin, which the screen already writes into the address bar --
    // without reading it back, refreshing while standing in the bin dropped the
    // operator out to the top group.
    $initial_mode = 'catalog';
    $initial_filter = '';

    if (isset($_GET['view'])) {

        if ($_GET['view'] == 'products') {
            $initial_filter = 'products';
        } elseif ($_GET['view'] == 'variants') {
            $initial_filter = 'variants';
        } elseif ($_GET['view'] == 'bin') {
            $initial_filter = 'recycled';
        }
    }

} else {

    if (isset($_GET['view']) && in_array($_GET['view'], array('files', 'images', 'pages'), true)) {
        $initial_mode = 'all';
    } elseif (isset($_GET['view']) && ($_GET['view'] == 'shared') && ($user['role'] <= 2)) {
        $initial_mode = 'shared';
    } elseif (isset($_GET['view']) && ($_GET['view'] == 'backups') && ($user['role'] <= 2)) {
        $initial_mode = 'backups';
    } elseif (isset($_GET['view']) && ($_GET['view'] == 'short_links')) {
        $initial_mode = 'short_links';
    }

    $initial_filter = (isset($_GET['view']) && in_array($_GET['view'], array('images', 'pages'), true)) ? $_GET['view'] : '';
}

// ?view=files&scope=documents narrows the Files view the way view_files.php's
// filter select did. Only the Files view takes one.
$file_scopes = array('documents', 'media', 'attachments', 'archived', 'public', 'guest', 'registration', 'membership', 'private');
$initial_scope = (($initial_mode == 'all') && ($initial_filter == '') && isset($_GET['scope']) && in_array($_GET['scope'], $file_scopes, true)) ? $_GET['scope'] : '';

// The left menu's two actions under Files: arrive with the upload window
// open, or with a new file already made and waiting for its name.
$initial_action = '';

if ($explorer_area != 'catalog') {
    if (isset($_GET['upload']) && ($_GET['upload'] == '1')) {
        $initial_action = 'upload';
    } elseif (isset($_GET['create']) && ($_GET['create'] == 'file')) {
        $initial_action = 'create_file';
    }
}

// A page's own panel, on the front end, sends the operator here to give that
// page a short address or to change the one it has: the window opens with the
// destination already chosen, or on the link itself.
$initial_short_link = 0;

if ($initial_mode == 'short_links') {

    if (isset($_GET['create']) && ($_GET['create'] == 'short_link')) {
        $initial_action = 'create_short_link';
        $initial_short_link = isset($_GET['page_id']) ? (int) $_GET['page_id'] : 0;

    } elseif (isset($_GET['edit'])) {
        $initial_action = 'edit_short_link';
        $initial_short_link = (int) $_GET['edit'];
    }
}

// Strings the client-side renderer needs. Keys stay English (tr.json rule).
$explorer_lang = array(
    'loading' => lang('Loading'),
    'request_failed' => lang('The request failed. Please try again.'),
    // Shown in the content area when a folder listing cannot be fetched --
    // a gateway or server timeout during a long bulk operation answers with
    // an HTML error page instead of JSON, so the grid says so and offers a
    // retry rather than silently keeping the folder that was already open.
    'load_failed' => lang('This folder could not be loaded. The connection or the server may have timed out during a long operation.'),
    'load_retry' => lang('Try again'),
    'empty_folder' => lang('This folder is a bit quiet.'),
    'name' => lang('Name'),
    'type' => lang('Type'),
    'size' => lang('Size'),
    'access_control' => lang('Access Control'),
    'last_modified' => lang('Last Modified'),
    'folder' => lang('Folder'),
    'folders' => lang('Folders'),
    'pictures' => lang('Pictures'),
    'all_files' => lang('All files'),
    'by_type' => lang('By type'),
    'by_access' => lang('By access'),
    'scope_documents' => lang('Documents'),
    'scope_media' => lang('Media'),
    'scope_attachments' => lang('Attachments'),
    'scope_archived' => lang('Archived files'),
    'page' => lang('Page'),
    'pages' => lang('Pages'),
    'file' => lang('File'),
    'files' => lang('Files'),
    'items' => lang('item(s)'),
    'selected' => lang('selected'),
    'open_folder' => lang('Open folder'),
    'open_in_new_tab' => lang('Open in new tab'),
    'edit' => lang('Edit'),
    'download' => lang('Download'),
    'cut' => lang('Cut'),
    'copy' => lang('Copy'),
    'paste' => lang('Paste'),
    'rename' => lang('Rename'),
    'duplicate' => lang('Duplicate'),
    'delete' => lang('Delete'),
    'new_folder' => lang('New Folder'),
    'upload_file' => lang('Upload Files or Folders'),
    'create_file' => lang('Create File'),
    'new_page' => lang('New Page'),
    'refresh' => lang('Refresh'),
    'select_all' => lang('Select All'),
    'deselect_all' => lang('Deselect All'),
    'access_permissions' => lang('Access Permissions'),
    'optimize_image' => lang('Optimize this image'),
    'image_editor' => lang('Image Editor'),
    'new_image' => lang('New image'),
    'new_image_name' => lang('New image'),
    'new_image_saved' => lang('{var:1} was created.'),
    'rotate_right' => lang('Rotate right'),
    'image_editor_missing' => lang('The image editor is not loaded on this screen.'),
    'saved_named' => lang('The {var:1} has been saved.'),
    'file_not_on_disk' => lang('The file is recorded here but is not on the disk.'),
    'file_too_large_to_edit' => lang('This file is larger than {var:1}, so its text is not shown here.'),
    'file_read_only' => lang('Shown for reference only. This file cannot be edited here.'),
    'save_copy_as' => lang('Save a copy as {var:1}'),
    'home_page' => lang('Homepage'),
    'archived' => lang('Archived'),
    'design_file' => lang('Design File'),
    'design_on' => lang('Mark as Design File'),
    'design_off' => lang('Remove Design File mark'),
    'new_file' => lang('New file'),
    'style' => lang('Style'),
    'public' => lang('Public'),
    'guest' => lang('Guest'),
    'registration' => lang('Registration'),
    'membership' => lang('Membership'),
    'private' => lang('Private'),
    'inherit' => lang('inherit'),
    'default' => lang('Default'),
    'confirm_delete_files' => lang('WARNING: Selected {var:1} will be permanently deleted.'),
    'files_word' => lang('files'),
    'pages_word' => lang('pages'),
    'mixed_delete' => lang('Please delete pages, files and folders separately.'),
    'delete_one_folder' => lang('Please delete folders one at a time.'),
    'clipboard_empty' => lang('The clipboard is empty.'),
    'clipboard_cut' => lang('{var:1} item(s) are ready to be moved.'),
    'clipboard_copy' => lang('{var:1} item(s) are ready to be copied.'),
    'cannot_paste_here' => lang('You do not have access to modify this folder.'),
    'uploading' => lang('Uploading'),
    'clear' => lang('Clear'),
    'drop_files_or_folders_here' => lang('Drag files or folders here'),
    'drop_files_here' => lang('Drag files here'),
    'drop_pictures_here' => lang('Drag pictures here'),
    'upload_picked_count' => lang('{var:1} file(s) selected, {var:2}'),
    'upload_over_limit' => lang('{var:1} of them are over the limit and will not be sent'),
    'upload_limit_note' => lang('Up to {var:1} per file, and no limit on how many files.'),
    'upload_folder_note' => lang('A dropped folder is made inside this one, with what was in it.'),
    'backup_upload_note' => lang('Archives and ordinary files only. Anything a web server would run or read as settings is refused, except .htaccess.'),
    'upload_not_allowed_here' => lang('not allowed here'),
    'upload_refused_count' => lang('{var:1} of them cannot go in a backup folder and will not be sent'),
    'upload_blocked_count' => lang('{var:1} of them are of a type that cannot be uploaded and will not be sent'),
    'upload_blocked_named' => lang('Not uploaded, files of that type are not allowed: {var:1}'),
    'leave_during_job' => lang('A job is still running on this screen. If you leave now it stops where it is.'),
    'upload_too_large' => lang('The file is too large. The maximum allowed size is {var:1}.'),
    'upload_done' => lang('{var:1} file(s) were uploaded.'),
    'drop_to_upload' => lang('Drop files here to upload them into this folder'),
    'drop_to_pick_folder' => lang('Drop files here to upload them. You will choose the folder next.'),
    'no_preview' => lang('No preview is available for this item.'),
    'preview' => lang('Preview'),
    'details' => lang('Details'),
    'description' => lang('Description'),
    'dimensions' => lang('Dimensions'),
    'modified_by' => lang('by {var:1}'),
    'view_access' => lang('View'),
    'edit_access' => lang('Edit'),
    'no_access' => lang('None'),
    'inherited_from_parent' => lang('Inherited from parent folder'),
    'inherited_from' => lang('Inherited from {var:1}'),
    'expiration_date' => lang('Expiration Date'),
    'save' => lang('Save'),
    'user_column' => lang('User'),
    'rights_column' => lang('Rights'),
    'no_basic_users' => lang('There are no users with a basic user role.'),
    'access_note' => lang('Access Control for all Pages and Files within this Folder'),
    'rights_note' => lang('Edit rights also apply to all folders within this folder.'),
    'disk_usage' => lang('Disk Usage'),
    'up' => lang('Up'),
    'root' => lang('My Folders'),
    'moved' => lang('{var:1} item(s) were moved.'),
    'pasted' => lang('{var:1} item(s) were pasted.'),
    'renamed' => lang('The item was renamed successfully.'),
    'optimized' => lang('Optimized'),
    'counts_label' => lang('{var:1} folder(s), {var:2} page(s), {var:3} file(s)'),
    'delete_folder_title' => lang('Delete Folder and Contents'),
    'delete_folder_summary' => lang('The folder {var:1} and everything inside it will be permanently deleted:'),
    'delete_folder_empty' => lang('The folder is empty.'),
    'type_name_to_confirm' => lang('Type the folder name to confirm.'),
    'name_does_not_match' => lang('The name does not match.'),
    'deleting' => lang('Deleting'),
    'delete_folder_done' => lang('The folder and its contents were deleted.'),
    'delete_folder_partial' => lang('Some items could not be deleted.'),
    'cancel' => lang('Cancel'),
    'classic_views' => lang('Classic views'),
    'recycle_bin' => lang('Recycle Bin'),
    'empty_bin' => lang('Empty Recycle Bin'),
    'restore' => lang('Restore'),
    'delete_permanently' => lang('Delete Permanently'),
    'bin_banner' => lang('Items here are deleted permanently after {var:1} days.'),
    'bin_confirm_empty' => lang('WARNING: {var:1} item(s) in the recycle bin will be permanently deleted.'),
    'days_left' => lang('{var:1} day(s) left'),
    'sort_by' => lang('Sort by'),
    'view_label' => lang('View'),
    'grid_view' => lang('Grid view'),
    'list_view' => lang('List view'),
    'ascending' => lang('Ascending'),
    'descending' => lang('Descending'),
    'open_containing_folder' => lang('Open containing folder'),
    'bin_folder_confirm' => lang('The folder {var:1} and everything inside it will be moved to the Recycle Bin.'),
    'bin_folders_confirm' => lang('{var:1} folder(s) and their contents will be moved to the Recycle Bin.'),
    // The bulk confirmation. Several items cannot be confirmed by typing a
    // name, so the operator types one agreed word instead.
    'delete_many_title' => lang('Delete Selected Items'),
    'delete_many_bin' => lang('The selected items will be moved to the Recycle Bin:'),
    'delete_many_hard' => lang('The selected items will be permanently deleted:'),
    'delete_many_folder_note' => lang('Everything inside the selected folders goes with them.'),
    'type_word_to_confirm' => lang('Type {var:1} to confirm.'),
    'confirm_word' => lang('CONFIRM'),
    'word_does_not_match' => lang('The word does not match.'),
    'folders_word' => lang('Folders'),
    'product_groups_word' => lang('Product Groups'),
    'products_word' => lang('Products'),
    'publish_item' => lang('Publish'),
    'unpublish_item' => lang('Unpublish'),
    'undo' => lang('Undo'),
    'redo' => lang('Redo'),
    'undone' => lang('The last action was undone.'),
    'redone' => lang('The action was redone.'),
    'nothing_to_undo' => lang('Nothing to undo.'),
    'nothing_to_redo' => lang('Nothing to redo.'),
    'optimize_all' => lang('Optimize all ({var:1})'),
    'optimizing' => lang('Optimizing images'),
    'optimized_done' => lang('{var:1} images were optimized'),
    'converted_done' => lang('{var:1} images were converted'),
    'move_to_bin' => lang('Move to Recycle Bin'),
    'delete_folder_title' => lang('Delete Folder and Contents'),
    'convert_webp' => lang('Convert to WebP'),
    'convert_webp_all' => lang('Convert all to WebP ({var:1})'),
    'webp_confirm' => lang('{var:1} image(s) will be converted to WebP. The file name changes to the .webp extension, and existing links on pages that point to the old address are not updated. Continue?'),
    'converting' => lang('Converting'),
    'folder_settings' => lang('Folder Settings'),
    'sorting' => lang('Sorting'),
    'sorting_note' => lang('Display order of this Folder to other Folders'),
    'archive' => lang('Archive'),
    'archive_note' => lang('Archive Folder for Pages and Files that are no longer being used'),
    'desktop_style' => lang('Desktop Page Style'),
    'mobile_style' => lang('Mobile Page Style'),
    'styles_note' => lang('Default Page Styles for Pages within this Folder'),
    'resize_optimize' => lang('Resize to {var:1} pixels and optimize'),
    'resize_optimize_all' => lang('Resize all to {var:1} pixels and optimize ({var:2})'),
    'desktop_style_short' => lang('Desktop Style'),
    'mobile_style_short' => lang('Mobile Style'),
    'impact' => lang('Impact'),
    'views_label' => lang('{var:1} views'),
    'features' => lang('Features'),
    'sitemap_label' => lang('Site Map'),
    'searchable_label' => lang('Searchable'),
    'comments_label' => lang('Comments'),
    'backups' => lang('Backups'),
    'backups_note' => lang('Website backups on the server. They are stored outside the web site, so they can only be downloaded from here.'),
    'backups_empty' => lang('There are no backups yet.'),
    // Short links: a name at the root of the site that stands for something
    // else. They sit under Pages because a page is what most of them point at.
    'short_links' => lang('Short Links'),
    'short_link' => lang('Short Link'),
    'short_links_empty' => lang('There are no short links yet.'),
    'short_links_count' => lang('{var:1} short link(s)'),
    'short_links_note' => lang('Names at the root of the site that stand for a page, a product, a file or an address somewhere else.'),
    'destination' => lang('Destination'),
    'flags' => lang('Marks'),
    'product_group_word' => lang('Product Group'),
    'destination_type' => lang('Destination Type'),
    'visit' => lang('Visit'),
    'create' => lang('Create'),
    'url_word' => lang('URL'),
    'catalog_page' => lang('Catalog Page'),
    'catalog_detail_page' => lang('Catalog Detail Page'),
    'tracking_code' => lang('Tracking Code'),
    'tracking_code_note' => lang('Added to the address as ?t= so this link can be told apart in the reports.'),
    'short_link_url_note' => lang('Enter URL that visitor should be redirected to'),
    'short_link_named' => lang('The short link has been created. Now give it the name people will type.'),
    'short_link_name_note' => lang('What people type after the address of the site. Renaming it changes the link; the old one stops working.'),
    'short_link_delete_confirm' => lang('{var:1} short link(s) will be deleted for good. Short links have no recycle bin.'),
    'select_one' => lang('Select'),
    'create_backup' => lang('Create Backup'),
    'backing_up' => lang('Backing up'),
    'backup_created' => lang('The backup is ready. Give it a name that says why it was taken.'),
    'backups_counts' => lang('{var:1} item(s)'),
    'compress_and_download' => lang('Compress and download'),
    'compressing' => lang('Compressing'),
    'extracting' => lang('Extracting'),
    'compress_zip' => lang('Compress into a zip ({var:1})'),
    'extract_here' => lang('Extract here'),
    'extract_confirm' => lang('{var:1} will be extracted into a new folder. File types that can run on a web site (php, htaccess, web.config and similar) are not extracted.'),
    'archive_name' => lang('archive'),
    'backup_delete_confirm' => lang('{var:1} item(s) will be deleted from the server for good. There is no recycle bin for backups. Continue?'),
    'permissions' => lang('Permissions'),
    'current_permissions' => lang('Current permissions'),
    'apply_to_contents' => lang('Apply to everything inside as well'),
    'permissions_note' => lang('755 for folders and 644 for files is the usual choice. Some servers do not allow this to be changed from a web page.'),
    'zip_unavailable' => lang('This server cannot create zip archives (the zip extension is missing).'),
    'actions' => lang('Action'),
    'shared_folders' => lang('Shared Folders'),
    'shared_note' => lang('Membership and private folders that were opened to specific users.'),
    'shared_empty' => lang('No folder has been opened to a specific user yet.'),
    'shared_counts' => lang('{var:1} shared folder(s), {var:2} person(s)'),
    'contact_column' => lang('Contact Person'),
    'no_contact' => lang('No contact record'),
    'people_count' => lang('{var:1} person(s)'),
    'noindex_label' => lang('Close to Search Engines (noindex)'),
    'nofollow_label' => lang('Do Not Follow Links on This Page (nofollow)'),
    'bulk_edit_pages' => lang('Bulk Edit Pages'),
    'bulk_edit_pages_menu' => lang('Bulk edit pages ({var:1})'),
    'bulk_edit_pages_count' => lang('{var:1} pages will be updated. Fields left on "Do not change" are not touched.'),
    'do_not_change' => lang('Do not change'),
    'bulk_sitemap_note' => lang('Include these pages in sitemap.xml so search engines can find them.'),
    'bulk_search_note' => lang('Allow these pages to appear in site search results.'),
    'bulk_comments_note' => lang('Allow visitors to comment on these pages.'),
    'bulk_noindex_note' => lang('Ask search engines not to index these pages.'),
    'bulk_nofollow_note' => lang('Ask search engines not to follow the links on these pages.'),
    'bulk_style_note' => lang('Only classic pages are restyled. A page the visual editor opens keeps its own style, because it carries its own layout.'),
    'bulk_edit_files' => lang('Bulk Edit Files'),
    'bulk_edit_files_menu' => lang('Bulk edit files ({var:1})'),
    'bulk_edit_files_count' => lang('{var:1} files will be updated. Fields left on "Do not change" are not touched.'),
    'bulk_move_to_folder' => lang('Move to folder'),
    'bulk_design_note' => lang('Design files are the theme\'s own assets: everyone sees them, only designers may change them.'),
    'bulk_description_mode' => lang('Description'),
    'bulk_description_set' => lang('Replace with the text below'),
    'bulk_description_clear' => lang('Clear'),
    'bulk_optimize_label' => lang('Optimize images'),
    'bulk_optimize_none' => lang('Do not optimize'),
    'bulk_optimize_only' => lang('Compress only, keep the original size'),
    'bulk_optimize_files' => lang('{var:1} of the selected files are images that can be optimized.'),
    'bulk_edit_products' => lang('Bulk Edit Products'),
    'bulk_edit_products_menu' => lang('Bulk edit products ({var:1})'),
    'bulk_edit_products_count' => lang('{var:1} products will be updated. Fields left on "Do not change" are not touched.'),
    'bulk_status_label' => lang('Status'),
    'bulk_publish' => lang('Publish'),
    'bulk_unpublish' => lang('Unpublish'),
    'bulk_price_label' => lang('Price'),
    'bulk_price_increase' => lang('Increase by an amount'),
    'bulk_price_decrease' => lang('Decrease by an amount'),
    'bulk_price_increase_percent' => lang('Increase by a percentage'),
    'bulk_price_decrease_percent' => lang('Decrease by a percentage'),
    'bulk_price_note' => lang('A product whose price the change would take to zero or below is left alone.'),
    'bulk_inventory_label' => lang('Track inventory'),
    'bulk_quantity_label' => lang('Inventory quantity'),
    'bulk_quantity_value' => lang('Set to this quantity'),
    'bulk_quantity_increase' => lang('Increase by'),
    'bulk_quantity_decrease' => lang('Decrease by'),
    'bulk_tax_label' => lang('Tax rate'),
    'bulk_tax_set' => lang('Set the tax rate to the value below'),
    'bulk_tax_zone' => lang('Follow the tax zone (clear the product rate)'),
    'bulk_zones_allow' => lang('Allow these shipping zones'),
    'bulk_zones_disallow' => lang('Remove these shipping zones'),
    'bulk_group_add' => lang('Add to product group'),
    'bulk_group_remove' => lang('Remove from product group'),
    'bulk_group_note' => lang('A product can be in several groups; removing it from one leaves the others alone.'),
    'bulk_barcodes' => lang('Assign a barcode to products that do not have one'),
    'amount' => lang('Amount'),
    'percentage' => lang('Percentage'),
    'seo_score_label' => lang('SEO Score'),
    'seo_not_ready' => lang('SEO score has not been calculated yet.'),
    'seo_detail' => lang('SEO Detail'),
    'yes' => lang('Yes'),
    'no' => lang('No'),
    'new_label' => lang('New'),

    // Catalog
    'store' => lang('Store'),
    'product_groups' => lang('Product Groups'),
    'all_products' => lang('All Products'),
    'all_variant_sets' => lang('All Variant Sets'),
    'variant_sets_count' => lang('{var:1} variant set(s)'),
    'short_description' => lang('Short Description'),
    'commerce_access' => lang('Commerce Management Rights'),
    'commerce_manage' => lang('Allow User to manage all commerce (i.e. products, shipping, tax, and orders)'),
    'commerce_reports' => lang('Allow User to manage all commerce reports (i.e. order reports & shipping report)'),
    'commerce_offline_payment' => lang('Allow User to set offline payment option for orders'),
    'commerce_access_note' => lang('These rights cover the whole store, not one product group. They are the same switches the user screen carries.'),
    'out_of_stock_in_group' => lang('{var:1} product(s) are out of stock'),
    'backorder_label' => lang('Backorder'),
    'product' => lang('Product'),
    'products' => lang('Products'),
    'category' => lang('Category'),
    'variant_set' => lang('Variant Set'),
    'catalog_root' => lang('Product Groups'),
    'catalog_counts' => lang('{var:1} group(s), {var:2} product(s)'),
    'catalog_totals' => lang('Catalog: {var:1} product(s) in {var:2} group(s)'),
    'catalog_empty' => lang('This group has nothing in it yet.'),
    'group_disabled' => lang('Not published'),
    // Not the shared 'Published' key: that one reads as the thing that was
    // published, and this cell is saying where the row is right now.
    'group_published' => lang('Live on the site'),
    'featured' => lang('Featured'),
    'display_type' => lang('Display Type'),
    'direct_products' => lang('Products in this group'),
    'deep_products' => lang('Including subgroups'),
    'price_range' => lang('Price range'),
    'in_stock' => lang('In stock'),
    'out_of_stock' => lang('Out of stock'),
    'not_tracked' => lang('Stock not tracked'),
    'member_of' => lang('In these groups'),
    'appears_on' => lang('Appears on these pages'),
    'appears_nowhere' => lang('No page shows this yet.'),
    'appears_loading' => lang('Looking for the pages…'),
    'open_in_catalog' => lang('Open in the catalog'),
    'file_manager' => lang('File Manager'),
    'folders_files' => lang('Folders and Files'),
    'classic_group_tree' => lang('Classic product group tree'),
    'price' => lang('Price'),
    'status' => lang('Status'),
    'quantity' => lang('Quantity'),
    'address' => lang('Address'),
    'new_product_group' => lang('New Product Group'),
    'new_product' => lang('New Product'),
    'catalog_bin' => lang('Recycle Bin'),
    'restore_item' => lang('Restore'),
    'remove_from_group' => lang('Remove from this group'),
    // Taking the catalog out as a file. The submenu names the two files
    // rather than saying "format", because that is what an operator is
    // choosing between: a spreadsheet or a plain list.
    'export_products' => lang('Export Products'),
    'export_selected' => lang('Export selected'),
    'export_csv' => lang('CSV file'),
    'export_excel' => lang('Excel file'),
    'remove_from_group_confirm' => lang('{var:1} will be taken out of this group. The product itself is not deleted and stays in its other groups.'),
    'bin_empty_note' => lang('Nothing here. Deleted groups and products wait here before they are removed for good.'),
    // The two values of product_groups.display_type, named the way the edit
    // screen names them. The raw enum was reaching the panel before this.
    'display_browse' => lang('Browse'),
    'display_select' => lang('Select'),
    // The column that holds a file's size holds a group's contents in the
    // store, where nothing has a size at all, so it is named for what is in it.
    'contents' => lang('Content'),
    // Said before a bulk delete, the way the folder screen says how many
    // folders are about to move. What a group takes with it is the part an
    // operator cannot see from the grid, so the sentence says it out loud.
    'catalog_bin_groups' => lang('{var:1} product group(s) and everything under them will be moved to the Recycle Bin. The products in them are not deleted and stay in their other groups.'),
    'catalog_bin_products' => lang('{var:1} product(s) will be moved to the Recycle Bin.'),
    'catalog_bin_mixed' => lang('{var:1} product group(s) with everything under them, and {var:2} product(s), will be moved to the Recycle Bin. The products in the groups are not deleted and stay in their other groups.'),
    'catalog_purge_confirm' => lang('{var:1} item(s) will be permanently deleted. This cannot be undone.'),
    // The selection strip along the bottom: what is picked, and what can be
    // done with it.
    'items_selected' => lang('{var:1} item(s) selected'),
    'clear_selection' => lang('Clear selection'),
    'more_actions' => lang('More actions'),
    // Picking a destination from a list rather than dragging onto it.
    'move_to' => lang('Move to...'),
    'copy_to' => lang('Copy to...'),
    'move_here' => lang('Move here'),
    'copy_here' => lang('Copy here'),
    'choose_folder' => lang('Choose a folder'),
    'choose_group' => lang('Choose a product group'),
    'you_are_here' => lang('You are here'),
    'filter_targets' => lang('Filter'),
    'no_target_match' => lang('Nothing matches what you typed.'),
    // In the store the two are different operations rather than two words for
    // one: moving takes the product out of the group it is in, adding leaves
    // it there as well.
    'move_into_group' => lang('Move into group'),
    'add_to_group' => lang('Add to group'),
    'add_to_group_note' => lang('The product stays in the groups it is already in.'),
    'move_into_group_note' => lang('The product is taken out of the group it is shown in.'),
    // Taking the address of something, in the three shapes it is asked for.
    'copy_address' => lang('Copy address'),
    'copy_full_url' => lang('Full address'),
    'copy_site_path' => lang('Site path'),
    'copy_as_html' => lang('As HTML'),
    // A product's name is its code, and a code is copied far more often than
    // it is read out: into a marketplace listing, an invoice, a supplier mail.
    'copy_name' => lang('Copy name'),
    'copied' => lang('Copied'),
    'copy_failed' => lang('The address could not be copied.'),
    'moved_to_bin_count' => lang('{var:1} item(s) moved to the Recycle Bin'),
    // The fallback when an endpoint answers without a sentence of its own.
    'updated' => lang('Updated'),
    // How large the tiles are drawn.
    // Finding out what would break before something is deleted.
    // Which columns the list draws.
    'columns' => lang('Columns'),
    'columns_reset' => lang('Show every column'),
    // The two short lists above the tree.
    'pinned' => lang('Pinned'),
    'recent' => lang('Recent'),
    'pin_here' => lang('Pin to the sidebar'),
    'unpin_here' => lang('Remove from the sidebar'),
    'where_used' => lang('Where is it used?'),
    'where_used_looking' => lang('Looking through the site...'),
    'where_used_none' => lang('Nothing on the site refers to this file.'),
    'where_used_count' => lang('Referred to in {var:1} place(s)'),
    'where_used_more' => lang('Only the first of them are listed.'),
    // Renaming a whole selection by a rule rather than one at a time.
    'bulk_rename' => lang('Bulk Rename'),
    'bulk_rename_menu' => lang('Rename {var:1} item(s)...'),
    'rename_mode' => lang('What to change'),
    'rename_mode_replace' => lang('Find and replace'),
    'rename_mode_affix' => lang('Add a prefix or a suffix'),
    'rename_mode_number' => lang('Number them in order'),
    'rename_find' => lang('Find'),
    'rename_replace' => lang('Replace with'),
    'rename_prefix' => lang('Prefix'),
    'rename_suffix' => lang('Suffix'),
    'rename_pattern' => lang('Pattern'),
    'rename_pattern_note' => lang('# is where the number goes: urun-### gives urun-001. {name} is the name it has now.'),
    'rename_pattern_default' => lang('photo-###'),
    'rename_start' => lang('Start at'),
    'rename_case' => lang('Match upper and lower case'),
    'rename_keep_extension' => lang('Keep the file extension'),
    'rename_preview' => lang('Preview'),
    'rename_unchanged' => lang('unchanged'),
    'rename_clash' => lang('the same name twice'),
    'rename_nothing' => lang('Nothing would change.'),
    'rename_apply' => lang('Rename {var:1} item(s)'),
    'rename_server_note' => lang('A name may still be adjusted when it is saved: what goes into an address is reduced to plain letters.'),
    'renaming' => lang('Renaming'),
    'rename_done' => lang('{var:1} item(s) renamed'),
    // Editing a figure in the list rather than opening the product.
    'edit_here' => lang('Edit here'),
    'edit_here_hint' => lang('Double-click to change it here'),
    'tile_size' => lang('Size'),
    'tile_small' => lang('Small'),
    'tile_medium' => lang('Medium'),
    'tile_large' => lang('Large'),
    'tile_xlarge' => lang('Extra large'),
    // Narrowing the listing by what things are rather than by their name.
    'filters' => lang('Filters'),
    'clear_filters' => lang('Clear filters'),
    'filters_on' => lang('{var:1} filter(s) on'),
    'filters_showing' => lang('{var:1} of {var:2} shown'),
    'filter_images' => lang('Pictures'),
    'filter_unoptimized' => lang('Not optimised'),
    'filter_large_files' => lang('Larger than 1 MB'),
    'filter_no_description' => lang('No description'),
    'filter_design' => lang('Design files'),
    'filter_archived' => lang('Archived items'),
    'filter_not_in_sitemap' => lang('Not in the sitemap'),
    'filter_low_seo' => lang('SEO score under 50'),
    'filter_out_of_stock' => lang('Out of stock'),
    'filter_unpublished' => lang('Not published'),
    'filter_no_image' => lang('No picture'),
    'filter_no_price' => lang('No price'),
    'filter_variant_sets' => lang('Variant sets'),
    'filter_nothing_matches' => lang('Nothing here matches the filters.'),
    'search_nothing_matches' => lang('Nothing here matches the search.'),
    // The full-screen look at one item, and the way out of it.
    'quick_look' => lang('Quick look'),
    'quick_look_hint' => lang('Space closes, the arrow keys walk the folder'),
    'previous_item' => lang('Previous'),
    'next_item' => lang('Next'),
    'close' => lang('Close'),
    'watch_the_tour' => lang('Watch the tour'));

// ── The guided tour ─────────────────────────────────────────────────────
//
// This screen grew a great deal in one release.  Someone who knew the old
// folder screen -- a tree that expanded, and little else -- opens this one and
// has to find out by poking at it that the tree now walks, that half of what
// they want is behind the right mouse button, and that a delete is no longer
// final.  The tour says those things once, on the first visit, and then stays
// out of the way behind the menu.
//
// The steps are here rather than in the engine because they need this screen's
// words and, for the one that points at the right-click menu, this screen's
// own functions.  The 'at' of each step is what the script matches on to find
// the control it belongs to.
//
// The two areas walk the same controls, but those controls do different things
// in each, so each area gets its own sentences instead of one set hedged to
// cover both.  Raise the number after the dot in the key when a tour's own
// words change, and everyone watches it once more.
$explorer_tour_key = ($explorer_area == 'catalog') ? 'explorer_catalog.3' : 'explorer_files.3';

$explorer_tour = ($explorer_area == 'catalog')
    ? array(
        array(
            'at' => 'tree',
            'title' => lang('The product group tree'),
            'text' => lang('Every product group in the store is here. Clicking one opens it now, where the old screen only expanded it. Right-click a group to publish or unpublish it, edit it, rename it or send it to the Recycle Bin, and drag one onto another to move it.')),
        array(
            'at' => 'tree_menu',
            'title' => lang('The product group menu'),
            'text' => lang('Right-clicking a product group in the tree opens this: open it, edit it, a new product group inside it, paste, rename, duplicate, publish or unpublish it, and delete -- which sends the group and everything under it to the Recycle Bin. The store root itself cannot be renamed or deleted, so those entries are switched off on it.')),
        array(
            'at' => 'new',
            'title' => lang('The New button'),
            'text' => lang('This adds a product group, or a product, inside the group you are standing in. In All Products and All Variant Sets it offers a product on its own, because there is no one group to add it to.')),
        array(
            'at' => 'search',
            'title' => lang('Search and view'),
            'text' => lang('This narrows what is on screen as you type, over the name and the short description both. The sliders beside it narrow by what things are instead: out of stock, not published, no picture, no price. The two buttons on the right switch between the grid and the list, and the grid draws its tiles in four sizes (right-click, View, or hold Ctrl and turn the wheel).')),
        array(
            'at' => 'all_row',
            'title' => lang('The whole store at once'),
            'text' => lang('All Products lists every product in the store wherever it sits, and All Variant Sets does the same for variant sets. This is the quickest way to find something when you cannot remember which group it is in.')),
        array(
            'at' => 'menu',
            'title' => lang('Right-click an item'),
            'text' => lang('Anything in the middle of the screen answers the right mouse button, and this is the menu it opens -- shown here on the group beside it. Publish and unpublish, edit, rename, cut, copy, paste and duplicate all live in it. A product also has Remove from this group, which takes it out of the group you are looking at without deleting it. Select several items first and the menu acts on all of them.')),
        array(
            'at' => 'selection',
            'title' => lang('What you have picked'),
            'text' => lang('Pick something and the bar along the bottom becomes what can be done with it: edit it, publish or unpublish it, move it into another group, delete it, and the rest behind the three dots. Move to... opens the group list with a search box, and asks the two things separately -- moving a product takes it out of the group you are looking at, adding leaves it there as well. Picking works from the keyboard too: the arrow keys walk the listing, Shift takes everything in between, and typing the first letters of a name jumps to it.')),
        array(
            'at' => 'bin',
            'title' => lang('The Recycle Bin'),
            'text' => lang('A deleted group or product is switched off and waits here instead of disappearing, and a group brings everything under it along. Restoring puts it back the way it was. Anything still here after 30 days is removed for good.')),
        array(
            'at' => 'more',
            'title' => lang('Everything else'),
            'text' => lang('The tree and the preview panel are switched on and off in this menu, and Commerce Management Rights lives here too. So does Watch the tour, if you would like to see this again.')))
    : array(
        array(
            'at' => 'tree',
            'title' => lang('The folder tree'),
            'text' => lang('Every folder on the site is here. Clicking one opens it now, where the old screen only expanded it. Right-click a folder for its own menu: a new folder, rename, access rules, settings and delete. Dragging one onto another moves it.')),
        array(
            'at' => 'tree_menu',
            'title' => lang('The folder menu'),
            'text' => lang('Right-clicking a folder in the tree opens this: open it, a new folder inside it, upload a file into it, paste, rename, folder settings, access rules, duplicate and delete. The site root itself cannot be renamed, moved or deleted, so those entries are switched off on it.')),
        array(
            'at' => 'new',
            'title' => lang('The New button'),
            'text' => lang('This adds things where you are standing. Inside a folder it offers a folder, a page and a file; in a view across everything it offers only what belongs there. You can also drop files, and whole folders, straight onto the screen.')),
        array(
            'at' => 'search',
            'title' => lang('Search and view'),
            'text' => lang('This narrows what is on screen as you type. The sliders beside it narrow by what things are instead -- pictures nobody has optimised, files with no description, pages missing from the sitemap -- and says along the top what it is hiding. The two buttons on the right switch between the grid and the list; the list sorts by name, size and date when you click a column heading, and the grid draws its tiles in four sizes (right-click, View, or hold Ctrl and turn the wheel).')),
        array(
            'at' => 'menu',
            'title' => lang('Right-click an item'),
            'text' => lang('Anything in the middle of the screen answers the right mouse button, and this is the menu it opens -- shown here on the folder beside it. Cut, copy, paste, duplicate, rename and delete all live in it, along with Copy address and the things that only fit one kind of item, such as optimising a picture or extracting an archive. Select several items first and the menu acts on all of them.')),
        array(
            'at' => 'selection',
            'title' => lang('What you have picked'),
            'text' => lang('Pick something and the bar along the bottom becomes what can be done with it: open it, move it, download it, delete it, and the rest behind the three dots. Move to... opens a list of every folder with a search box, so a folder four levels down needs no dragging at all. Picking works from the keyboard too: the arrow keys walk the listing, Shift takes everything in between, and typing the first letters of a name jumps to it.')),
        array(
            'at' => 'bin',
            'title' => lang('The Recycle Bin'),
            'text' => lang('Deleted folders, pages and files wait here instead of disappearing, and restoring one puts it back where it came from. Anything still here after 30 days is removed for good.')),
        array(
            'at' => 'more',
            'title' => lang('Everything else'),
            'text' => lang('The folder tree and the preview panel are switched on and off in this menu, and so are the views across everything -- which is how a phone reaches them. So is Watch the tour, if you would like to see this again.')));

echo
    pg_page_shell(
        ($explorer_area == 'catalog')
            ? array(
                'title' => lang('Catalog Manager'),
                'extra classes' => 'products file-manager',
                'icon' => 'store',
                'heading' => lang('Catalog Manager'),
                'heading_description' => lang('Browse and edit product groups, variant sets and products'),
                'tour' => $explorer_tour_key,
            )
            : array(
                'title' => lang('File Manager'),
                'extra classes' => 'folders files file-manager',
                'icon' => 'folder',
                'heading' => lang('File Manager'),
                'heading_description' => lang('Browse folders, files and access rules'),
                'tour' => $explorer_tour_key,
            )
    );
?>
<main id="content" class="container-fluid p-0">
<style>
/* App-shell styles for the combined explorer. Everything derives from
   Bootstrap theme variables so light and dark software themes both work;
   the access control colors come from backend.src.css. */
/* One pane serves both areas; each hides the rows that are not its own. */
#explorer_app[data-area="catalog"] .area-files { display: none !important; }
#explorer_app[data-area="files"] .area-catalog { display: none !important; }

/* A group or product that is not published reads as present but switched off,
   the same way an archived folder does. */
/* Unpublished, in the store. Half opacity alone reads as "loading" or
   "disabled control" rather than "not on the site", so the name and the icon
   say it in the colour the rest of the screen already uses for that state --
   the status column and the tile's own flag are the same red. */
.explorer-item.catalog-off .item-card,
.explorer-item.catalog-off .list-name { opacity: .75; }
.explorer-item.catalog-off .item-name,
.explorer-item.catalog-off .list-name,
.explorer-item.catalog-off .item-icon,
.explorer-item.catalog-off .list-icon { color: var(--bs-danger) !important; }
.explorer-item.catalog-off .item-thumb { outline: 2px solid var(--bs-danger); outline-offset: -2px; }
#explorer_tree_pane .tree-row.catalog-off .tree-label,
#explorer_tree_pane .tree-row.catalog-off > .bi { color: var(--bs-danger) !important; }

.tree-count { margin-left: auto; font-size: .72rem; opacity: .55; padding-left: .4rem; }

.catalog-chip { display: inline-block; border: 1px solid var(--bs-border-color); border-radius: 2rem;
  padding: 0 .45rem; font-size: .68rem; line-height: 1.5; opacity: .85; }

.prev-title { font-weight: 600; margin: .5rem 0 .1rem; }
.prev-kv { display: flex; justify-content: space-between; gap: .6rem; font-size: .8rem;
  padding: .2rem 0; border-bottom: 1px solid var(--bs-border-color-translucent); }
.prev-kv span:first-child { color: var(--bs-secondary-color); }
.prev-kv span:last-child { text-align: right; }
.prev-group { margin-top: .9rem; }
.prev-group-title { color: var(--bs-secondary-color); font-size: .68rem; letter-spacing: .05em;
  text-transform: uppercase; font-weight: 600; margin-bottom: .3rem; }
.prev-row { font-size: .8rem; padding: .12rem 0; }
.prev-row a { text-decoration: none; display: block; }

/* Edge to edge: the shell already frames the page, a second frame around
   the app just wastes pixels. */
#content { padding: 0 !important; }
#explorer_app { display: flex; flex-direction: column; height: calc(100vh - 150px); min-height: 480px; border: unset; border-radius: 0; background: var(--bs-body-bg); overflow: hidden; user-select: none; -webkit-user-select: none; }
/* The bar itself, the element that takes the slack and the search box all wear
   the house classes now (.pg-toolbar / .pg-toolbar-grow / .pg-toolbar-search in
   backend.src.css) -- this screen is where that pattern was first built, and it
   was the last one still carrying its own copy of it.

   What stays here is the one thing the shared rules cannot know: the order this
   screen's own controls take when the bar wraps. The shared block puts the path
   on line two (order 9) and the search box on line three (order 10), so
   everything below has to stay under nine. */
@media (max-width: 767.98px) {

    #new_menu_wrap { order: 1; }
    #explorer_transfer_wrap { order: 2; }
    #up_button { order: 3; }
    #explorer_filter_wrap { order: 4; }
    #file_scope_wrap { order: 5; }
    #explorer_view_group { order: 6; margin-left: auto; }
    #recycle_bin_button { order: 7; }
    #explorer_more_wrap { order: 8; }
}
#file_scope_menu .dropdown-item [data-role="check"] { opacity: 0; }
#file_scope_menu .dropdown-item.active [data-role="check"] { opacity: 1; }
#file_scope_menu .dropdown-item.active { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
#explorer_breadcrumb .breadcrumb { margin: 0; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
#explorer_breadcrumb .breadcrumb::-webkit-scrollbar { display: none; }
#explorer_breadcrumb .breadcrumb-item { white-space: nowrap; }
#explorer_breadcrumb .breadcrumb-item > a { cursor: pointer; text-decoration: none; padding: .15rem .45rem; border-radius: .5rem; }
#explorer_breadcrumb .breadcrumb-item > a:hover { background: var(--bs-secondary-bg); }
#explorer_breadcrumb .breadcrumb-item > a.drop-target { outline: 2px dashed var(--bs-primary); outline-offset: -2px; }
#explorer_main { display: flex; flex: 1 1 auto; min-height: 0; min-width: 0; max-width: 100%; overflow: hidden; }
#explorer_tree_pane { width: 250px; min-width: 250px; overflow: auto; border-right: 1px solid var(--bs-border-color-translucent); padding: .55rem .45rem; font-size: .8rem; }
#explorer_tree_pane.pane-hidden, #explorer_preview_pane.pane-hidden { display: none !important; }
#explorer_tree_pane ul { list-style: none; margin: 0 0 0 .62rem; padding-left: .55rem; border-left: 1px solid var(--bs-border-color-translucent); }
#explorer_tree_pane > ul { margin-left: 0; padding-left: 0; border-left: 0; }
#explorer_tree_pane .tree-row { display: flex; align-items: center; gap: .32rem; padding: .17rem .4rem; border-radius: .45rem; cursor: pointer; white-space: nowrap; line-height: 1.35; color: var(--bs-body-color); }
#explorer_tree_pane .tree-row:hover { background: var(--bs-secondary-bg); }
#explorer_tree_pane .tree-row.active { background: var(--bs-primary-bg-subtle); font-weight: 600; }
#explorer_tree_pane .tree-row.drop-target { outline: 2px dashed var(--bs-primary); outline-offset: -2px; }
#explorer_tree_pane .tree-toggle { width: .95rem; height: .95rem; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; color: var(--bs-secondary-color); font-size: .58rem; border-radius: .3rem; transition: transform .12s ease; }
#explorer_tree_pane .tree-toggle.bi-chevron-right:hover { background: var(--bs-tertiary-bg); }
#explorer_tree_pane .tree-toggle.open { transform: rotate(90deg); }
#explorer_tree_pane .tree-row > .bi-folder-fill, #explorer_tree_pane .tree-row > .bi-file-earmark-image { font-size: .92rem; line-height: 1; }
#explorer_tree_pane .tree-label { overflow: hidden; text-overflow: ellipsis; }
#explorer_tree_pane .tree-section-label { font-size: .62rem; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-tertiary-color); padding: .55rem .45rem .2rem; user-select: none; }
#explorer_tree_pane > .tree-row { margin-bottom: .06rem; }
#explorer_content { flex: 1 1 auto; min-width: 0; overflow: auto; position: relative; padding: .75rem; }
#explorer_preview_pane { width: 330px; min-width: 330px; overflow: auto; border-left: 1px solid var(--bs-border-color-translucent); padding: 1rem; }
#explorer_preview_pane iframe { width: 100%; height: 280px; border: 1px solid var(--bs-border-color-translucent); border-radius: .6rem; background: #fff; }
#explorer_preview_pane img.preview-media { max-width: 100%; max-height: 280px; border-radius: .6rem; }
#explorer_content::-webkit-scrollbar, #explorer_tree_pane::-webkit-scrollbar, #explorer_preview_pane::-webkit-scrollbar { width: 10px; height: 10px; }
#explorer_content::-webkit-scrollbar-thumb, #explorer_tree_pane::-webkit-scrollbar-thumb, #explorer_preview_pane::-webkit-scrollbar-thumb { background: var(--bs-secondary-bg); border-radius: 10px; border: 2px solid var(--bs-body-bg); }
#explorer_content::-webkit-scrollbar-thumb:hover, #explorer_tree_pane::-webkit-scrollbar-thumb:hover, #explorer_preview_pane::-webkit-scrollbar-thumb:hover { background: var(--bs-tertiary-color); }
/* Tile size is a setting rather than a constant: a folder of three hundred
   pictures wants them big enough to tell apart, and a folder of stylesheets
   wants as many names on screen as will fit. The three numbers move together
   -- column, picture box and glyph -- so a tile stays in proportion at every
   step instead of growing a large frame around a small icon. */
#explorer_app { --pg-tile: 136px; --pg-tile-thumb: 84px; --pg-tile-icon: 3rem; }
#explorer_grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(var(--pg-tile), 1fr)); gap: .45rem; }
.explorer-item { cursor: default; }
#explorer_grid .item-card { position: relative; border-radius: .8rem; padding: .55rem .4rem .5rem; text-align: center; transition: background .12s ease, box-shadow .12s ease, transform .12s ease; }
#explorer_grid .item-card:hover { background: var(--bs-secondary-bg); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.explorer-item.selected .item-card { background: var(--bs-primary-bg-subtle) !important; box-shadow: inset 0 0 0 2px rgba(var(--bs-primary-rgb), .55); }
tr.explorer-item.selected > td { background: var(--bs-primary-bg-subtle) !important; }
.explorer-item.cut-ghost { opacity: .45; }
/* Sold out, in the corner the tile leaves free. It stays above the selection
   circle rather than under it: the circle appears on hover and while selected,
   and a warning that disappears exactly when the operator reaches for the row
   is worse than one that overlaps it. */
.stock-dot { position: absolute; top: .28rem; left: .28rem; width: .55rem; height: .55rem; border-radius: 50%;
  background: var(--bs-danger); box-shadow: 0 0 0 2px var(--bs-body-bg); z-index: 3; pointer-events: none; }
#explorer_list_table .stock-dot { position: static; display: inline-block; box-shadow: none; margin-right: .3rem; vertical-align: middle; }
.sel-dot { position: absolute; top: .4rem; left: .4rem; width: 1.15rem; height: 1.15rem; border-radius: 50%; border: 2px solid var(--bs-tertiary-color); background: var(--bs-body-bg); display: none; align-items: center; justify-content: center; font-size: .65rem; color: #fff; cursor: pointer; z-index: 2; }
#explorer_grid .item-card:hover .sel-dot, .explorer-item.selected .sel-dot { display: flex; }
.explorer-item.selected .sel-dot { background: var(--bs-primary); border-color: var(--bs-primary); }
.item-thumb-area { height: var(--pg-tile-thumb); display: flex; align-items: center; justify-content: center; margin-bottom: .3rem; }
.explorer-item .item-icon { font-size: var(--pg-tile-icon); line-height: 1; position: relative; display: inline-block; }

/* A short link is drawn as what it is: a link, an arrow, and the thing it
   lands on. A link straight to an address off the site has nothing to point
   at, so it keeps the link glyph alone. */
.short-link-icon { display: inline-flex; align-items: center; justify-content: center; gap: .02em; line-height: 1; color: var(--bs-primary); }
.short-link-icon .sl-arrow { font-size: .68em; opacity: .55; margin: 0 -.08em; }
.short-link-icon .sl-target { color: var(--bs-body-color); opacity: .85; }
.explorer-item .item-icon.short-link-icon { font-size: calc(var(--pg-tile-icon) * .67); height: var(--pg-tile-icon); }
#explorer_list_table .list-icon.short-link-icon { font-size: 1.05rem; }
.explorer-item .item-thumb { width: 100%; max-width: calc(var(--pg-tile) - 20px); height: var(--pg-tile-thumb); object-fit: cover; border-radius: .55rem; border: 1px solid var(--bs-border-color-translucent); }
.explorer-item .thumb-wrap { position: relative; display: inline-block; max-width: 100%; }
/* Thumbnail loading state. A tile carries its address in data-src and gets a
   src only when its turn comes (see the thumbnail loader), so until then it is
   a real element with no source -- which shows nothing, not the browser's
   broken-image icon. A shimmer fills that box so a folder mid-load reads as
   loading rather than broken; it stops the moment the picture is in. */
.item-thumb.pg-lazy {
  background: linear-gradient(100deg, var(--bs-secondary-bg) 25%, var(--bs-tertiary-bg) 50%, var(--bs-secondary-bg) 75%);
  background-size: 200% 100%;
  animation: pgThumbShimmer 1.15s ease-in-out infinite;
}
/* An <img> with no source yet has no intrinsic width, and width:100% then
   resolves against a wrapper that has collapsed to it -- so the placeholder
   needs its own box while it waits. It reverts to the fluid rule above the
   moment the picture loads and pg-lazy comes off. */
.explorer-item .item-thumb.pg-lazy { width: 116px; height: 84px; }
#explorer_list_table .item-thumb.pg-lazy { width: 36px; height: 36px; }
.item-thumb.is-loaded { animation: none; }
.item-thumb.thumb-appear { animation: pgThumbAppear .24s ease-out; }
@keyframes pgThumbShimmer { 0% { background-position: 180% 0; } 100% { background-position: -180% 0; } }
@keyframes pgThumbAppear { from { opacity: .25; } to { opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
  .item-thumb.pg-lazy { animation: none; }
  .item-thumb.thumb-appear { animation: none; }
}
/* A picture that will not load becomes the file glyph in the same box, so a
   screen of missing files is never a screen of broken-image icons. */
.explorer-item .item-thumb-fallback {
  width: 100%; max-width: 116px; height: 84px; border-radius: .55rem;
  border: 1px solid var(--bs-border-color-translucent);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.7rem; color: var(--bs-secondary-color); background: var(--bs-secondary-bg);
}
#explorer_list_table .item-thumb-fallback { width: 36px; max-width: 36px; height: 36px; border-radius: .4rem; font-size: 1rem; }
/* The corner glyph says what a tile is. Over a photo a bare glyph is a smudge
   -- a text-shadow was not enough on a busy picture -- so it sits on a small
   round plate in the page colour, the way a shortcut arrow does. The plate is
   painted from the same variable Bootstrap's bg-body uses, in one rule, so
   every kind of tile gets it without four render paths repeating a class. */
.explorer-item .access-badge {
  position: absolute; right: -5px; bottom: 1px;
  display: inline-flex; align-items: center; justify-content: center;
  width: 1.5em; height: 1.5em; font-size: .85rem; line-height: 1;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color-translucent);
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, .22);
}
.explorer-item .thumb-wrap .access-badge { right: 2px; bottom: 2px; }
.explorer-item .item-name { font-size: .82rem; line-height: 1.25; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
/* The line the site actually prints, under the row's own name.
   A product group's name is the operator's handle for it and a product's name
   is its ID / SKU; neither reaches a visitor. The short description does, so it
   is shown as well -- and on a product it takes the reading weight, with the
   SKU kept above it as the small identifier it is. */
.explorer-item .item-subname { font-size: .72rem; line-height: 1.2; color: var(--bs-secondary-color); overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: .08rem; }
.explorer-item.has-subname[data-kind="product"] .item-name { font-size: .7rem; color: var(--bs-secondary-color); -webkit-line-clamp: 1; }
.explorer-item.has-subname[data-kind="product"] .item-subname { font-size: .82rem; color: var(--bs-body-color); }
#explorer_list_table .list-subname { display: block; font-size: .72rem; color: var(--bs-secondary-color); max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#explorer_list_table tr[data-kind="product"] .list-name { font-size: .78rem; color: var(--bs-secondary-color); }
.explorer-item .item-flags { font-size: .72rem; opacity: .8; min-height: 1em; margin-top: .15rem; }
.explorer-item.drop-target .item-card, tr.explorer-item.drop-target > td { outline: 2px dashed var(--bs-primary); outline-offset: -2px; }
#explorer_drag_badge { position: fixed; top: -300px; left: -300px; z-index: 10000; padding: .3rem .7rem; background: var(--bs-primary); color: #fff; font-size: .85rem; border-radius: .5rem; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: none; }
/* SEO score ring (toolbar drawing): bottom-left corner of a page tile,
   inline in list rows and in the preview header. */
.seo-ring { display: inline-flex; line-height: 0; vertical-align: middle; }
.seo-ring .seo-ring-track { stroke: var(--bs-border-color-translucent); }
#explorer_grid .item-card .seo-ring { position: absolute; left: .3rem; bottom: .3rem; z-index: 2; }

/* Catalog tiles are an icon over a label rather than a picture, so the ring
   would sit on the words. It moves to the corner the icon leaves free. */
#explorer_grid .explorer-item[data-kind="group"] .item-card .seo-ring,
#explorer_grid .explorer-item[data-kind="product"] .item-card .seo-ring {
  left: auto; bottom: auto; right: .3rem; top: .3rem;
}
#preview_seo_detail .border-light { border-color: var(--bs-border-color-translucent) !important; }
/* Shared folders report: one card per folder, people listed inside it. */
#shared_list .shared-group { border: 1px solid var(--bs-border-color-translucent); border-radius: .7rem; padding: .6rem .8rem; margin-bottom: .7rem; background: var(--bs-body-bg); }
#shared_list .shared-group-head { display: flex; align-items: center; flex-wrap: wrap; gap: .15rem .1rem; }
#shared_list .shared-group-path { font-size: .74rem; margin-bottom: .4rem; }
#shared_list table { font-size: .82rem; }
#shared_list th { font-weight: 600; color: var(--bs-secondary-color); border-bottom: 1px solid var(--bs-border-color-translucent); }
#shared_list td { border-bottom: 1px solid var(--bs-border-color-translucent); }
/* Laid out fixed, from a <colgroup>. That is what makes a dragged edge mean
   something and what stops eleven columns from squeezing a name down to one
   letter per line: every column has a width, and below their sum the pane
   scrolls sideways rather than crushing anything. */
#explorer_list_table { width: 100%; table-layout: fixed; }

/* A column marked pg-at-<bp> wants at least that breakpoint. It is dropped
   only in the band between the first breakpoint and its own: under 768px the
   table keeps everything and the pane scrolls sideways instead, which is the
   only way the detail reaches a phone at all -- see listBreakpointMet(), which
   decides the <colgroup> by the same rule and must stay in step with this. */
@media (min-width: 768px) and (max-width: 991.98px) {
    #explorer_list_table .pg-at-lg,
    #explorer_list_table .pg-at-xl,
    #explorer_list_table .pg-at-xxl { display: none; }
}
@media (min-width: 992px) and (max-width: 1199.98px) {
    #explorer_list_table .pg-at-xl,
    #explorer_list_table .pg-at-xxl { display: none; }
}
@media (min-width: 1200px) and (max-width: 1399.98px) {
    #explorer_list_table .pg-at-xxl { display: none; }
}

/* Everything in a cell stays on its line and ends in an ellipsis. The few
   places that need a second line -- the name, with a short description under
   it -- say so for themselves. */
#explorer_list_table td > .cell-clip,
#explorer_list_table td { overflow: hidden; text-overflow: ellipsis; }

#explorer_list_table td .cell-clip { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Headings: a label that clips, a caret when the list is ordered by it, and a
   grip on the edge. */
#explorer_list_table th { position: relative; user-select: none; }
#explorer_list_table th .th-label { display: inline-block; max-width: calc(100% - 1.4rem); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
#explorer_list_table th .th-caret { margin-left: .25rem; font-size: .7em; color: var(--bs-primary); }
#explorer_list_table th.th-sortable { cursor: pointer; }
#explorer_list_table th.th-sortable:hover { color: var(--bs-body-color); }

#explorer_list_table th .col-grip {
    position: absolute;
    top: 0;
    right: -3px;
    width: 7px;
    height: 100%;
    cursor: col-resize;
    z-index: 2;
}

#explorer_list_table th .col-grip:hover::after,
body.col-resizing #explorer_list_table th .col-grip::after {
    content: '';
    position: absolute;
    top: 15%;
    left: 3px;
    width: 2px;
    height: 70%;
    background: var(--bs-primary);
    border-radius: 2px;
}

body.col-resizing { cursor: col-resize; user-select: none; }

/* The small type words -- what kind of thing this is, whether it is published,
   who may see it. A badge reads at a glance and stops a long word from setting
   the width of its column. */
.list-badge {
    display: inline-block;
    max-width: 100%;
    padding: .1rem .45rem;
    border-radius: .35rem;
    font-size: .72rem;
    line-height: 1.5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: bottom;
    background: var(--bs-secondary-bg);
    color: var(--bs-secondary-color);
}

.list-badge.badge-on { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.list-badge.badge-off { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
#explorer_list_table th { white-space: nowrap; position: sticky; top: -0.8rem; background: var(--bs-body-bg); z-index: 1; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); border-bottom: 1px solid var(--bs-border-color-translucent); }
#explorer_list_table td { vertical-align: middle; border-bottom: 1px solid var(--bs-border-color-translucent); }
#explorer_list_table tbody tr:hover > td { background: var(--bs-secondary-bg); }
#explorer_list_table .item-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: .4rem; }
#explorer_list_table .list-icon { font-size: 1.45rem; position: relative; display: inline-block; }
#explorer_list_table .list-icon .access-badge { right: -6px; bottom: -3px; font-size: .62rem; }
#explorer_list_table .thumb-wrap { position: relative; display: inline-block; }
#explorer_list_table .thumb-wrap .access-badge { right: -4px; bottom: -3px; font-size: .62rem; }
/* One line, cut with an ellipsis. The cell already carries the whole name in
   its title attribute, so nothing is lost by not printing all of it. */
#explorer_list_table .list-name {
    display: block;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Renaming happens inside that same span, and a clipped box would clip the
   input with it. */
#explorer_list_table .list-name.is-renaming {
    display: block;
    max-width: none;
    overflow: visible;
    white-space: normal;
}


.rename-input { font-size: .82rem; }

/* A cell that can be changed in place. It stays text -- these figures are read
   far more often than they are written -- and says what it can do only when the
   pointer is on it, so a list of prices does not read as a list of form
   fields. */
#explorer_list_table .cell-editable { cursor: text; border-bottom: 1px dashed transparent; }
#explorer_list_table tr:hover .cell-editable { border-bottom-color: var(--bs-border-color); }
#explorer_list_table tr:hover .cell-editable::after { content: "\F4CB"; font-family: bootstrap-icons; font-size: .7em;
  margin-left: .35em; opacity: .5; vertical-align: middle; }
#explorer_list_table .cell-editable.is-editing { border-bottom-color: transparent; display: block; }
#explorer_list_table .cell-editable.is-editing::after { content: none; }
/* The second line truncates, and while it holds an input it must not. */
#explorer_list_table .list-subname.is-editing { white-space: normal; overflow: visible; max-width: none; }
/* A product with no storefront name: the dash is the gap, said quietly. */
#explorer_list_table .subname-empty { opacity: .45; }
#explorer_menu { position: fixed; z-index: 1080; display: none; min-width: 230px; border-radius: .75rem; box-shadow: 0 10px 30px rgba(0,0,0,.25); user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; }
#explorer_menu .dropdown-item { cursor: pointer; border-radius: .45rem; padding: .32rem .7rem; }
#explorer_menu .dropdown-item .bi { width: 1.35rem; display: inline-block; }
#explorer_menu { padding: .35rem; }
/* The upload window's drop area. Dashed rather than solid so it reads as a
   place to put something rather than a box that already holds something. */
#upload_dropzone {
    border: 2px dashed var(--bs-border-color);
    border-radius: .8rem;
    padding: 1.4rem 1rem;
    text-align: center;
    color: var(--bs-secondary-color);
    transition: border-color .15s ease, background-color .15s ease;
}

#upload_dropzone.is-over {
    border-color: var(--bs-primary);
    background: color-mix(in srgb, var(--bs-primary) 8%, transparent);
    color: var(--bs-primary);
}

#upload_picked {
    max-height: 190px;
    overflow-y: auto;
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: .6rem;
    padding: .5rem .7rem;
    font-size: .84rem;
}

#upload_picked .upload-row { display: flex; gap: .5rem; align-items: center; }

/* A fixed square whichever it is, so the names below each other line up. */
#upload_picked .upload-thumb,
#upload_picked .upload-mark {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    border-radius: .25rem;
}

#upload_picked .upload-thumb { object-fit: cover; background: var(--bs-secondary-bg); }

#upload_picked .upload-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    color: var(--bs-secondary-color);
}
#upload_picked .upload-row + .upload-row { margin-top: .2rem; }
#upload_picked .upload-path { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#upload_picked .upload-size { flex: 0 0 auto; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
#upload_picked .upload-row.is-too-big .upload-size { color: var(--bs-danger); }
#upload_picked .upload-row.is-blocked .upload-size { color: var(--bs-danger); }
#upload_picked .upload-row.is-blocked .upload-path { text-decoration: line-through; opacity: .7; }

/* The file window. The preview is a stage the media sits on rather than a box
   it fills: a tall picture and a wide one both keep their shape, and a
   transparent one shows its transparency against the checks. */
#file_edit_preview { display: flex; align-items: center; justify-content: center; min-height: 140px; max-height: 46vh; border-radius: .75rem; background: var(--bs-tertiary-bg); overflow: hidden; }
#file_edit_preview:empty { display: none; }
#file_edit_preview img { max-width: 100%; max-height: 46vh; object-fit: contain; background-color: #fff; background-image: linear-gradient(45deg, #e6e6e6 25%, transparent 25%), linear-gradient(-45deg, #e6e6e6 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e6e6e6 75%), linear-gradient(-45deg, transparent 75%, #e6e6e6 75%); background-size: 16px 16px; background-position: 0 0, 0 8px, 8px -8px, -8px 0; }
#file_edit_preview video { max-width: 100%; max-height: 46vh; }
#file_edit_preview audio { width: 100%; max-width: 560px; margin: 2rem 1rem; }
#file_edit_preview iframe { width: 100%; height: 46vh; border: 0; background: #fff; }
#file_edit_preview .file-edit-glyph { font-size: 4rem; line-height: 1; color: var(--bs-secondary-color); padding: 1.5rem 0; }
#file_edit_editor_wrap .CodeMirror { height: 52vh; border: 1px solid var(--bs-border-color-translucent); border-radius: .5rem; font-size: .85rem; }
#file_edit_facts span + span::before { content: '·'; margin: 0 .45rem; opacity: .6; }

#explorer_upload_overlay { position: absolute; inset: 0; z-index: 20; display: none; align-items: center; justify-content: center; background: color-mix(in srgb, var(--bs-primary) 10%, transparent); backdrop-filter: blur(2px); border: 2px dashed var(--bs-primary); border-radius: .8rem; pointer-events: none; }

/* Rubber-band selection. The band is a child of the scrolling content, so it
   lives in the same coordinate space as the items and scrolls with them
   instead of having to be repositioned on every scroll event. */
#explorer_marquee { position: absolute; z-index: 15; pointer-events: none; border: 1px solid rgba(var(--bs-primary-rgb), .85); background: rgba(var(--bs-primary-rgb), .14); border-radius: .15rem; }

#explorer_statusbar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; padding: .4rem .9rem; border-top: 1px solid var(--bs-border-color-translucent); font-size: .78rem; color: var(--bs-secondary-color); }

/* Quick look: one item, as large as the screen will draw it.
   Above the chat bubble (1080) on purpose -- while this is open it is the only
   thing on screen, and a launcher floating over a photograph is a launcher in
   the way. */
#explorer_quicklook { position: fixed; inset: 0; z-index: 1090; display: flex; flex-direction: column;
  background: color-mix(in srgb, var(--bs-body-bg) 92%, #000); }
#explorer_quicklook.d-none { display: none !important; }
#explorer_quicklook .ql-bar { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem;
  border-bottom: 1px solid var(--bs-border-color-translucent); }
#explorer_quicklook .ql-name { font-weight: 600; }
#explorer_quicklook .ql-meta { color: var(--bs-secondary-color); font-size: .8rem; }
#explorer_quicklook .ql-stage { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center;
  padding: 1rem; position: relative; }
#explorer_quicklook .ql-stage img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: .5rem; }
#explorer_quicklook .ql-stage iframe { width: 100%; height: 100%; border: 0; border-radius: .5rem; background: #fff; }
#explorer_quicklook .ql-stage video { max-width: 100%; max-height: 100%; border-radius: .5rem; }
#explorer_quicklook .ql-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
  width: 2.6rem; height: 2.6rem; border-radius: 50%; border: 0; display: flex; align-items: center; justify-content: center;
  background: var(--bs-secondary-bg); color: var(--bs-body-color); opacity: .75; font-size: 1.3rem; }
#explorer_quicklook .ql-nav:hover { opacity: 1; }
#explorer_quicklook .ql-nav[disabled] { opacity: .25; }
#explorer_quicklook .ql-prev { left: .75rem; }
#explorer_quicklook .ql-next { right: .75rem; }
#explorer_quicklook .ql-hint { text-align: center; color: var(--bs-secondary-color); font-size: .75rem; padding: 0 0 .6rem; }

/* The chat bubble is fixed to the bottom right corner and floats above
   everything (z-index 1080). The status bar's right-hand end runs underneath
   it: losing the last characters of a disk figure was untidy, but a button
   that cannot be pressed is a broken screen, so the bar keeps its distance
   wherever the launcher is drawn. 24px inset + 58px bubble, plus a gap. */
#explorer_app.has-chat-launcher #explorer_statusbar { padding-right: 5.75rem; }

/* The strip a selection turns the status bar into. The gap is its own because
   the bar's 1rem is for separate readings and these are one control group. */
#explorer_statusbar .selection-actions { display: flex; align-items: center; gap: .1rem; flex-wrap: wrap; }
#explorer_statusbar .selection-actions .dropdown-menu { z-index: 1075; }

/* Below the tablet width the labels come off and the icons carry the meaning
   the title attribute already spells out -- four labelled buttons and a count
   do not fit on a phone, and wrapping them pushes the grid up. */
@media (max-width: 767.98px) {
    #explorer_statusbar { gap: .5rem; }
    #explorer_statusbar .selection-action-label { display: none; }
}
#explorer_content .empty-note { opacity: .7; }
#explorer_menu li.has-submenu { position: relative; }
#explorer_menu .submenu { display: none; position: absolute; top: -0.35rem; left: 100%; min-width: 200px; border-radius: .75rem; box-shadow: 0 10px 30px rgba(0,0,0,.25); padding: .35rem; margin: 0; }
#explorer_menu li.has-submenu.open > .submenu { display: block; }
#explorer_menu li.has-submenu.submenu-left > .submenu { left: auto; right: 100%; }
#explorer_menu .dropdown-item .menu-check { width: 1.1rem; display: inline-block; }
#explorer_menu .dropdown-item .submenu-arrow { float: right; margin-top: .2rem; opacity: .6; }
.bin-badge { font-size: .66rem; }
.explorer-item, .tree-row { -webkit-touch-callout: none; }
@media (max-width: 991.98px) {
    #explorer_tree_pane, #explorer_preview_pane { display: none !important; }
    #explorer_app { height: calc(100vh - 120px); }
}
</style>

<?php echo $liveform->output_errors() . $liveform->get_warnings() . $liveform->output_notices(); ?>
<?php echo $liveform_pages->output_errors() . $liveform_pages->output_notices(); ?>
<?php echo $liveform_folders->output_errors() . $liveform_folders->output_notices(); ?>

<div id="explorer_app" class="my-2" data-area="<?php echo $explorer_area; ?>">
    <div id="explorer_toolbar" class="pg-toolbar">
        <div class="dropdown" id="new_menu_wrap">
            <button type="button" id="new_menu_button" class="btn btn-sm btn-primary rounded-pill px-3 no-popover" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"><span class="bi bi-plus-lg me-1"></span><?php echo lang('New'); ?></button>
            <ul class="dropdown-menu shadow" style="border-radius:.75rem;">
                <!-- Only ever shown in the backup folder, where nothing else
                     under New applies: the menu used to open there with four
                     greyed out lines and no way to take a backup at all, while
                     the only place to ask for one was the right-click menu. -->
                <li id="create_backup_item" class="d-none"><button type="button" class="dropdown-item" id="create_backup_button"><span class="bi bi-database-add me-2"></span><?php echo lang('Create Backup'); ?></button></li>
                <li id="new_folder_item"><button type="button" class="dropdown-item" id="new_folder_button"><span class="bi bi-folder-plus me-2"></span><?php echo lang('New Folder'); ?></button></li>
                <li id="upload_file_item"><button type="button" class="dropdown-item" id="upload_file_button"><span class="bi bi-upload me-2"></span><?php echo lang('Upload Files or Folders'); ?></button></li>
                <li id="create_file_item"><button type="button" class="dropdown-item" id="create_file_button"><span class="bi bi-file-earmark-plus me-2"></span><?php echo lang('Create File'); ?></button></li>
                <li id="new_image_item"><button type="button" class="dropdown-item" id="new_image_button"><span class="bi bi-easel me-2"></span><?php echo lang('New image'); ?></button></li>
                <li id="new_page_item" class="d-none"><a class="dropdown-item" id="new_page_button" href="add_page.php"><span class="bi bi-window-plus me-2"></span><?php echo lang('New Page'); ?></a></li>
                <li id="new_short_link_item" class="d-none"><button type="button" class="dropdown-item" id="new_short_link_button"><span class="bi bi-link-45deg me-2"></span><?php echo lang('Short Link'); ?></button></li>
            </ul>
        </div>
        <!-- Bringing things in and taking them out. One button rather than
             several, because what it offers depends on where you are standing
             and a toolbar with four transfer buttons on it would be mostly
             greyed out wherever you stood. -->
        <div class="dropdown" id="explorer_transfer_wrap">
            <button type="button" id="transfer_menu_button" class="btn btn-sm btn-ghost no-popover" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="<?php echo lang('Import / Export'); ?>"><span class="bi bi-arrow-down-up"></span></button>
            <ul class="dropdown-menu shadow" style="border-radius:.75rem;">
                <li id="import_zip_item"><button type="button" class="dropdown-item" id="import_zip_button"><span class="bi bi-file-earmark-zip me-2"></span><?php echo lang('Import ZIP File'); ?></button></li>
                <li id="import_design_zip_item"><button type="button" class="dropdown-item" id="import_design_zip_button"><span class="bi bi-magic me-2"></span><?php echo lang('Import ZIP for the Visual Page Editor'); ?></button></li>
                <li id="import_products_item"><a class="dropdown-item" id="import_products_button" href="import_products.php"><span class="bi bi-box-arrow-in-down me-2"></span><?php echo lang('Import Products'); ?></a></li>
                <li id="export_products_divider"><hr class="dropdown-divider" /></li>
                <li id="export_products_csv_item"><button type="button" class="dropdown-item" data-export-scope="all" data-export-format="csv"><span class="bi bi-filetype-csv me-2"></span><?php echo lang('Export Products'); ?> (CSV)</button></li>
                <li id="export_products_xlsx_item"><button type="button" class="dropdown-item" data-export-scope="all" data-export-format="xlsx"><span class="bi bi-file-earmark-excel me-2"></span><?php echo lang('Export Products'); ?> (Excel)</button></li>
            </ul>
        </div>
        <button type="button" id="up_button" class="btn btn-sm btn-ghost no-popover" title="<?php echo lang('Up'); ?>"><span class="bi bi-arrow-90deg-up"></span></button>
        <div id="explorer_breadcrumb" class="pg-toolbar-grow"></div>
        <div class="input-group input-group-sm rounded-pill pg-toolbar-search" id="explorer_search_wrap">
            <span class="input-group-text rounded-start-pill bi bi-search border-end-0"></span>
            <input type="search" id="filter_input" class="form-control rounded-end-pill border-start-0" placeholder="<?php echo lang('Search'); ?>" />
        </div>
        <!-- Narrowing by what things are, rather than by what they are called.
             The search box answers "where is the file I can name"; this answers
             "which of these still needs work" -- the pictures nobody optimised,
             the products with no photograph, the pages missing from the
             sitemap. Every test runs on the listing that is already on screen,
             so it costs a redraw and no request. -->
        <div class="dropdown" id="explorer_filter_wrap">
            <button type="button" id="explorer_filter_button" class="btn btn-sm btn-ghost no-popover position-relative" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="<?php echo lang('Filters'); ?>"><span class="bi bi-sliders"></span><span id="explorer_filter_count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-primary d-none" style="font-size:.6rem;"></span></button>
            <!-- Aligned to the button's left edge rather than its right: the
                 toolbar wraps on a narrow screen and this button can end up
                 near the left, where a right-aligned menu hangs off the side
                 of the screen with half its entries unreadable. -->
            <ul class="dropdown-menu shadow" id="explorer_filter_menu" style="border-radius:.75rem;"></ul>
        </div>
        <!-- The Files view, narrowed -- what view_files.php offered from a
             select. Only on that view: Pictures is a view of its own, and a
             folder is not something to narrow by type. -->
        <div class="dropdown d-none" id="file_scope_wrap">
            <button type="button" id="file_scope_button" class="btn btn-sm btn-ghost no-popover dropdown-toggle text-nowrap" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"><span class="bi bi-funnel me-1"></span><span id="file_scope_label"></span></button>
            <ul class="dropdown-menu shadow" id="file_scope_menu" style="border-radius:.75rem;">
                <li><button type="button" class="dropdown-item" data-scope=""><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('All files'); ?></button></li>
                <li><h6 class="dropdown-header"><?php echo lang('By type'); ?></h6></li>
                <li><button type="button" class="dropdown-item" data-scope="documents"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Documents'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="media"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Media'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="attachments"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Attachments'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="archived"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Archived files'); ?></button></li>
                <li><h6 class="dropdown-header"><?php echo lang('By access'); ?></h6></li>
                <li><button type="button" class="dropdown-item" data-scope="public"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Public'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="guest"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Guest'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="registration"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Registration'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="membership"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Membership'); ?></button></li>
                <li><button type="button" class="dropdown-item" data-scope="private"><span class="bi bi-check2 me-2" data-role="check"></span><?php echo lang('Private'); ?></button></li>
            </ul>
        </div>
        <div class="btn-group btn-group-sm" id="explorer_view_group" role="group">
            <button type="button" id="view_grid_button" class="btn btn-ghost no-popover" title="<?php echo lang('Grid view'); ?>"><span class="bi bi-grid-3x3-gap"></span></button>
            <button type="button" id="view_list_button" class="btn btn-ghost no-popover" title="<?php echo lang('List view'); ?>"><span class="bi bi-list-ul"></span></button>
        </div>
        <button type="button" id="recycle_bin_button" class="btn btn-sm btn-ghost no-popover position-relative d-none" title="<?php echo lang('Recycle Bin'); ?>"><span class="bi bi-trash3"></span><span id="recycle_bin_count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none" style="font-size:.6rem;"></span></button>
        <div class="dropdown" id="explorer_more_wrap">
            <button type="button" id="explorer_more_button" class="btn btn-sm btn-ghost no-popover" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"><span class="bi bi-three-dots-vertical"></span></button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="border-radius:.75rem;">
                <li class="d-none d-lg-block"><button type="button" class="dropdown-item" id="tree_toggle_button"><span class="bi bi-check2 me-2 opacity-0" data-role="check"></span><?php echo lang('Folder tree'); ?></button></li>
                <li class="d-none d-lg-block"><button type="button" class="dropdown-item" id="preview_toggle_button"><span class="bi bi-check2 me-2 opacity-0" data-role="check"></span><?php echo lang('Preview panel'); ?></button></li>
                <li class="d-none d-lg-block"><hr class="dropdown-divider" /></li>
                <!-- The same shortcuts the sidebar carries, for the screens that
                     do not show a sidebar: this menu is how a phone reaches the
                     views across everything. It has to belong to the area it is
                     opened in. Offering Files inside the store switched the grid
                     to files while the sidebar, the tree and the address bar all
                     stayed in the store -- a half-changed screen. Crossing to the
                     other area is a link, the way the sidebar's bridge row is. -->
                <!-- The way back. Under 992px the sidebar is gone and this menu
                     is the whole of the navigation, so the views across
                     everything cannot be the only things in it: an operator who
                     reached Pages from here looks for Folders here too, and the
                     root link in the breadcrumb is not where they were told to
                     look. Same for the store, where All Products has to lead
                     back to the groups. -->
                <li class="area-files"><button type="button" class="dropdown-item" id="folders_root_menu_item"><span class="bi bi-hdd me-2"></span><?php echo lang('My Folders'); ?></button></li>
                <li class="area-catalog"><button type="button" class="dropdown-item" id="catalog_root_menu_item"><span class="bi bi-boxes me-2"></span><?php echo lang('Product Groups'); ?></button></li>
                <li><hr class="dropdown-divider" /></li>
                <li class="area-files"><button type="button" class="dropdown-item" id="all_files_menu_item"><span class="bi bi-file-earmark-image me-2"></span><?php echo lang('Files'); ?></button></li>
                <li class="area-files"><button type="button" class="dropdown-item" id="all_images_menu_item"><span class="bi bi-images me-2"></span><?php echo lang('Pictures'); ?></button></li>
                <li class="area-files"><button type="button" class="dropdown-item" id="all_pages_menu_item"><span class="bi bi-window-stack me-2"></span><?php echo lang('Pages'); ?></button></li>
                <li class="area-files"><button type="button" class="dropdown-item" id="short_links_menu_item"><span class="bi bi-link-45deg me-2"></span><?php echo lang('Short Links'); ?></button></li>
                <?php if ($user['role'] <= 2) { ?>
                <li class="area-files"><button type="button" class="dropdown-item" id="shared_menu_item"><span class="bi bi-shield-lock me-2"></span><?php echo lang('Shared Folders'); ?></button></li>
                <li class="area-files"><button type="button" class="dropdown-item" id="backups_menu_item"><span class="bi bi-database me-2"></span><?php echo lang('Backups'); ?></button></li>
                <?php } ?>
                <li class="area-catalog"><button type="button" class="dropdown-item" id="all_products_menu_item"><span class="bi bi-box2-heart me-2"></span><?php echo lang('All Products'); ?></button></li>
                <li class="area-catalog"><button type="button" class="dropdown-item" id="all_variant_sets_menu_item"><span class="bi bi-collection-fill me-2"></span><?php echo lang('All Variant Sets'); ?></button></li>
                <?php if ($user['role'] <= 2) { ?>
                <li class="area-catalog"><button type="button" class="dropdown-item" id="commerce_access_menu_item"><span class="bi bi-shield-lock me-2"></span><?php echo lang('Commerce Management Rights'); ?></button></li>
                <?php } ?>
                <li><button type="button" class="dropdown-item" id="refresh_button"><span class="bi bi-arrow-clockwise me-2"></span><?php echo lang('Refresh'); ?></button></li>
                <li><button type="button" class="dropdown-item" id="tour_menu_item"><span class="bi bi-signpost-split me-2"></span><?php echo lang('Watch the tour'); ?></button></li>
                <li><hr class="dropdown-divider" /></li>
                <!-- No crossing to the other area from here either.  The
                     sidebar lost its bridge rows for reading as branches of
                     the tree they sat under; these read as one more view of
                     the screen you are already on, and are not.  The left menu
                     is where the software is navigated, and it lists both. -->
                <li><h6 class="dropdown-header"><?php echo lang('Classic views'); ?></h6></li>
                <!-- The classic files screen stays reachable from here for a
                     while longer: everything it did is on this screen now, but
                     the old screen is the yardstick while the new one settles.
                     It comes out of the left menu first and from here last. -->
                <li class="area-files"><a class="dropdown-item" href="view_files.php"><span class="bi bi-file-earmark-image me-2"></span><?php echo lang('Files'); ?></a></li>
                <li class="area-files"><a class="dropdown-item" href="view_pages.php"><span class="bi bi-window-stack me-2"></span><?php echo lang('Pages'); ?></a></li>
                <li class="area-catalog"><a class="dropdown-item" href="view_products.php"><span class="bi bi-box2-heart me-2"></span><?php echo lang('Products'); ?></a></li>
            </ul>
        </div>
    </div>
    <!-- What the listing is narrowed to, said out loud. A filter that is on and
         not visible is a folder that looks half empty for no reason anybody can
         see, so this strip is drawn only when there is something to say and is
         the first thing under the toolbar when there is. -->
    <div id="explorer_filters_bar" class="d-none align-items-center flex-wrap gap-2" style="padding:.4rem .9rem;border-bottom:1px solid var(--bs-border-color-translucent);font-size:.78rem;"></div>
    <div id="recycle_banner" class="d-none align-items-center flex-wrap gap-2" style="padding:.45rem .9rem;border-bottom:1px solid var(--bs-border-color-translucent);background:var(--bs-warning-bg-subtle);font-size:.82rem;">
        <span class="bi bi-trash3 text-warning-emphasis"></span>
        <span id="recycle_banner_text" class="text-warning-emphasis"></span>
        <button type="button" id="empty_bin_button" class="btn btn-sm btn-outline-danger ms-auto rounded-pill"><span class="bi bi-x-octagon me-1"></span><?php echo lang('Empty Recycle Bin'); ?></button>
    </div>
    <div id="explorer_main">
        <div id="explorer_tree_pane" class="d-none d-lg-block">
            <!-- Both areas draw the same pane and hide what does not belong to
                 them, rather than each rendering its own: highlightTree() reaches
                 for these rows by id on every load, and a missing one would be a
                 null. The store rows lead to the other screen; the operator stays
                 in the same interface either way. -->
            <div class="tree-row area-files" id="all_files_quick"><span class="tree-toggle"></span><span class="bi bi-file-earmark-image text-primary"></span><span class="tree-label"><?php echo lang('Files'); ?></span></div>
            <div class="tree-row area-files" id="all_images_quick"><span class="tree-toggle"></span><span class="bi bi-images text-primary"></span><span class="tree-label"><?php echo lang('Pictures'); ?></span></div>
            <div class="tree-row area-files" id="all_pages_quick"><span class="tree-toggle"></span><span class="bi bi-window-stack text-primary"></span><span class="tree-label"><?php echo lang('Pages'); ?></span></div>
            <div class="tree-row area-files" id="short_links_quick"><span class="tree-toggle"></span><span class="bi bi-link-45deg text-primary"></span><span class="tree-label"><?php echo lang('Short Links'); ?></span></div>
            <div class="tree-row area-catalog" id="all_products_quick"><span class="tree-toggle"></span><span class="bi bi-box2-heart text-primary"></span><span class="tree-label"><?php echo lang('All Products'); ?></span><span class="tree-count" id="all_products_count"></span></div>
            <!-- A variant set is a group whose display type is 'select': one
                 product in several forms rather than a category. view_products.php
                 lists them on their own screen, so the store does too. -->
            <div class="tree-row area-catalog" id="all_variant_sets_quick"><span class="tree-toggle"></span><span class="bi bi-collection-fill text-primary"></span><span class="tree-label"><?php echo lang('All Variant Sets'); ?></span><span class="tree-count" id="all_variant_sets_count"></span></div>

            <!-- Two short lists above the tree: the ones marked to keep, and
                 the ones just visited. Both are drawn only when they hold
                 something, so an operator who uses neither sees the sidebar
                 exactly as it was. -->
            <div id="explorer_pinned_wrap" class="d-none">
                <div class="tree-section-label"><?php echo lang('Pinned'); ?></div>
                <div id="explorer_pinned"></div>
            </div>
            <div id="explorer_recent_wrap" class="d-none">
                <div class="tree-section-label"><?php echo lang('Recent'); ?></div>
                <div id="explorer_recent"></div>
            </div>

            <div class="tree-section-label area-files"><?php echo lang('Folders'); ?></div>
            <div class="tree-section-label area-catalog"><?php echo lang('Product Groups'); ?></div>
            <ul id="explorer_tree_root"></ul>

            <?php if ($user['role'] <= 2) { ?>
            <div class="tree-section-label area-files" id="shared_section_label"><?php echo lang('Shared Items'); ?></div>
            <div class="tree-row area-files" id="shared_quick"><span class="tree-toggle"></span><span class="bi bi-shield-lock text-primary"></span><span class="tree-label"><?php echo lang('Shared Folders'); ?></span></div>
            <div class="tree-row area-files" id="backups_quick"><span class="tree-toggle"></span><span class="bi bi-database text-primary"></span><span class="tree-label"><?php echo lang('Backups'); ?></span></div>
            <?php } ?>

            <!-- The sidebar carries one tree and stops there. It used to end
                 with a row leading to the other area, and that row read as one
                 more branch of the tree it was sitting under: clicking it left
                 the screen entirely. Crossing between the file manager and the
                 store is a deliberate move and it lives in the menu at the top
                 right, where the other ways off this screen already are. -->
        </div>
        <div id="explorer_content">
            <div class="text-center my-5 empty-note"><span class="spinner-border spinner-border-sm me-2"></span><?php echo lang('Loading'); ?>...</div>
            <div id="explorer_upload_overlay"><div class="text-center fw-bold text-primary"><span class="bi bi-cloud-arrow-up fs-1 d-block"></span><?php echo lang('Drop files here to upload them into this folder'); ?></div></div>
        </div>
        <div id="explorer_preview_pane" class="d-none d-lg-block"></div>
    </div>
    <div id="explorer_statusbar"></div>
</div>

<!-- Right-click menu. Rebuilt for each target; positioned fixed. -->
<ul id="explorer_menu" class="dropdown-menu"></ul>

<!-- Asking for a product file.
     A form rather than a link: a selection of several thousand products does
     not fit in a query string, and a download still ought to carry a token. -->
<form id="export_products_form" action="export_products.php" method="post" target="_blank" class="d-none">
    <?php echo get_token_field(); ?>
    <input type="hidden" name="format" value="csv" />
    <input type="hidden" name="scope" value="all" />
    <input type="hidden" name="ids" value="" />
    <input type="hidden" name="group_ids" value="" />
</form>

<!-- Import ZIP.
     The form posts straight to import_zip.php, which is where the whole of
     that import lives: the folder it makes, the way it pulls a page out of an
     HTML file, the backup-and-inherit rule for a file whose name is already
     taken, and the pass that rewrites every reference between the imported
     items afterwards. None of that is repeated here. A file upload is a real
     form post in any case, so posting to it is also the plain way to do this.
     send_to brings the operator back to this screen when it is finished. -->
<div class="modal fade" id="import_zip_modal" tabindex="-1" aria-labelledby="import_zip_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" style="border-radius:.9rem;" action="import_zip.php" method="post" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="import_zip_modal_label"><span class="bi bi-file-earmark-zip me-2"></span><?php echo lang('Import ZIP File'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body">
                <?php echo get_token_field(); ?>
                <input type="hidden" name="send_to" id="import_zip_send_to" value="" />
                <p class="form-text mt-0"><?php echo lang('Import web pages and files from a ZIP file.'); ?></p>
                <div class="mb-3">
                    <label class="form-label" for="import_zip_file"><?php echo lang('Select .zip to Upload'); ?></label>
                    <input type="file" class="form-control" id="import_zip_file" name="file" accept=".zip,application/zip" required />
                </div>
                <div class="mb-1">
                    <label class="form-label" for="import_zip_name"><?php echo lang('Tag'); ?></label>
                    <input type="text" class="form-control" id="import_zip_name" name="import_name" maxlength="100" />
                    <div class="form-text"><?php echo lang('appended to imported items and filenames for easy reference, recommended, e.g. \'mysite\''); ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="submit" name="submit_import" value="Import" class="btn btn-primary"><span class="bi bi-upload me-1"></span><?php echo lang('Import'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Import ZIP for the Visual Page Editor.
     Posts to import_design_zip.php: the archive becomes a saved design (a
     style row plus one page per HTML file, all in Import/<project>/) and
     the browser lands in the editor on it. The plain Import ZIP above makes
     ordinary pages and files; this one makes a design. -->
<div class="modal fade" id="import_design_zip_modal" tabindex="-1" aria-labelledby="import_design_zip_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" style="border-radius:.9rem;" action="import_design_zip.php" method="post" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="import_design_zip_modal_label"><span class="bi bi-magic me-2"></span><?php echo lang('Import ZIP for the Visual Page Editor'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body">
                <?php echo get_token_field(); ?>
                <input type="hidden" name="send_to" id="import_design_zip_send_to" value="" />
                <p class="form-text mt-0"><?php echo lang('An HTML project (pages plus their CSS, JavaScript, images and fonts) becomes a design: every HTML file a page, the files under Import / project name. The editor opens on it when the import is done.'); ?></p>
                <div class="mb-3">
                    <label class="form-label" for="import_design_zip_file"><?php echo lang('Select .zip to Upload'); ?></label>
                    <input type="file" class="form-control" id="import_design_zip_file" name="file" accept=".zip,application/zip" required />
                </div>
                <div class="mb-1">
                    <label class="form-label" for="import_design_zip_project"><?php echo lang('Project name'); ?></label>
                    <input type="text" class="form-control" id="import_design_zip_project" name="project_name" maxlength="60" />
                    <div class="form-text"><?php echo lang('Defaults to the file name'); ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="submit" name="submit_import" value="Import" class="btn btn-primary"><span class="bi bi-upload me-1"></span><?php echo lang('Import'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- The short link wizard. Nothing is written until Create is pressed, so
     closing this at any point leaves no half-made link behind. -->
<!-- Upload.
     add_file.php is still the upload screen and view_files.php still goes
     there. This window is the file manager's own, because the manager already
     has an uploader: it walks a dropped folder, makes the folders as it goes
     and sends one file per request. What it did not have was anywhere to say
     which folder, what the description is, or what to do to a picture on the
     way in -- so it sent the operator to a different screen to say them, and
     that screen has its own uploader with its own failure modes. -->
<!-- The file window: what edit_file.php was, on this screen.
     A picture, a video, a sound or a PDF is shown; a text format is opened in
     the code editor; everything else gets its glyph. Under that, the four
     things every file has -- name, folder, description, and for a designer
     the design flag. The tools along the bottom are the actions the file's
     kind allows and nothing more. -->
<div class="modal fade" id="file_edit_modal" tabindex="-1" aria-labelledby="file_edit_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:.9rem;">
            <div class="modal-header">
                <h5 class="modal-title" id="file_edit_modal_label"><span class="bi bi-pencil-square me-2"></span><?php echo lang('Edit File'); ?></h5>
                <button type="button" class="btn-close" id="file_edit_close" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body">

                <div id="file_edit_preview"></div>

                <div id="file_edit_editor_wrap" class="d-none">
                    <textarea id="file_edit_code" class="d-none"></textarea>
                    <div class="form-text" id="file_edit_editor_note"></div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="file_edit_name"><?php echo lang('Name'); ?></label>
                        <input type="text" class="form-control" id="file_edit_name" autocomplete="off" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="file_edit_folder"><?php echo lang('Folder'); ?></label>
                        <select class="form-select" id="file_edit_folder"></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="file_edit_description"><?php echo lang('File Description / Photo Gallery Caption'); ?></label>
                        <textarea class="form-control" id="file_edit_description" rows="2"></textarea>
                    </div>
                    <div class="col-12<?php echo (($user['role'] <= 1) ? '' : ' d-none'); ?>" id="file_edit_design_wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="file_edit_design" />
                            <label class="form-check-label" for="file_edit_design"><?php echo lang('Design File'); ?></label>
                            <div class="form-text"><?php echo lang('Check if File is a Design File that is Managed by Site Designers'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="small text-secondary mt-3" id="file_edit_facts"></div>

                <div class="alert alert-danger mt-3 mb-0 d-none" id="file_edit_error"></div>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <div class="d-flex flex-wrap gap-2 me-auto" id="file_edit_tools"></div>
                <span class="text-warning-emphasis small d-none" id="file_edit_unsaved"><span class="bi bi-exclamation-triangle me-1"></span><?php echo lang('You have unsaved changes.'); ?></span>
                <button type="button" class="btn btn-outline-danger d-none" id="file_edit_discard"><?php echo lang('Close without saving'); ?></button>
                <button type="button" class="btn btn-secondary" id="file_edit_cancel"><?php echo lang('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="file_edit_save"><span class="bi bi-check2 me-1"></span><?php echo lang('Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="upload_modal" tabindex="-1" aria-labelledby="upload_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:.9rem;">
            <div class="modal-header">
                <h5 class="modal-title" id="upload_modal_label"><span class="bi bi-cloud-arrow-up me-2"></span><?php echo lang('Upload Files or Folders'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body">

                <!-- Two pickers behind one area. A drop takes whatever is
                     dropped; the links exist because a folder cannot be
                     reached from the ordinary file dialog, and a file cannot
                     be reached from the directory one. -->
                <div id="upload_dropzone">
                    <span class="bi bi-cloud-arrow-up d-block" style="font-size:2rem;"></span>
                    <div class="fw-semibold mt-1" id="upload_dropzone_title"><?php echo lang('Drag files or folders here'); ?></div>
                    <div class="mt-2 d-flex flex-wrap justify-content-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="upload_pick_files"><span class="bi bi-file-earmark me-1"></span><?php echo lang('Choose files'); ?></button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="upload_pick_images"><span class="bi bi-images me-1"></span><?php echo lang('Choose pictures'); ?></button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-none" id="upload_pick_zip"><span class="bi bi-file-earmark-zip me-1"></span><?php echo lang('Choose zip'); ?></button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="upload_pick_folder"><span class="bi bi-folder me-1"></span><?php echo lang('Choose folder'); ?></button>
                    </div>
                    <div class="form-text mt-2" id="upload_limit_note"></div>
                </div>

                <input type="file" id="upload_input_files" class="d-none" multiple />
                <!-- accept narrows the dialog to pictures, which on a phone is
                     what opens the photo library rather than the file browser. -->
                <input type="file" id="upload_input_images" class="d-none" accept="image/*" multiple />
                <input type="file" id="upload_input_folder" class="d-none" webkitdirectory directory multiple />
                <!-- The backup folder's own picker: what is brought back to it
                     is an archive, and a picture never is. -->
                <input type="file" id="upload_input_zip" class="d-none" accept=".zip,application/zip,application/x-zip-compressed" multiple />

                <div id="upload_picked" class="d-none mt-3"></div>

                <div class="row g-3 mt-0">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="upload_folder"><?php echo lang('Folder'); ?></label>
                        <select class="form-select" id="upload_folder"></select>
                        <!-- In the backup folder there is nothing to choose:
                             the upload lands where you are standing, and it is
                             shown rather than picked. -->
                        <input type="text" class="form-control d-none" id="upload_backup_path" readonly />
                        <div class="form-text" id="upload_folder_note"><?php echo lang('A dropped folder is made inside this one, with what was in it.'); ?></div>
                    </div>
                    <div class="col-12 col-md-6" id="upload_description_col">
                        <label class="form-label" for="upload_description"><?php echo lang('File Description / Photo Gallery Caption'); ?></label>
                        <textarea class="form-control" id="upload_description" rows="2"></textarea>
                        <div class="form-text"><?php echo lang('Given to every file in this upload.'); ?></div>
                    </div>
                </div>

                <div class="mt-3" id="upload_switches">
                    <div class="form-check form-switch<?php echo (($user['role'] <= 1) ? '' : ' d-none'); ?>">
                        <input class="form-check-input" type="checkbox" id="upload_design" />
                        <label class="form-check-label" for="upload_design"><?php echo lang('Design File'); ?></label>
                        <div class="form-text"><?php echo lang('Check if File is a Design File that is Managed by Site Designers'); ?></div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="upload_webp" />
                        <label class="form-check-label" for="upload_webp"><?php echo lang('Convert images to WebP'); ?></label>
                        <div class="form-text"><?php echo lang('The picture is re-encoded and the file takes the .webp name. Only pictures are touched.'); ?></div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="upload_optimize" />
                        <label class="form-check-label" for="upload_optimize"><?php echo lang('Optimize images'); ?></label>
                        <div class="form-text"><?php echo lang('The same size ceiling and quality the picture settings apply everywhere else.'); ?></div>
                    </div>
                </div>

                <div class="alert alert-danger mt-3 mb-0 d-none" id="upload_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="upload_start_button" disabled><span class="bi bi-cloud-arrow-up me-1"></span><?php echo lang('Upload'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="short_link_modal" tabindex="-1" aria-labelledby="short_link_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:.9rem;">
            <div class="modal-header">
                <h5 class="modal-title" id="short_link_modal_label"><span class="bi bi-link-45deg me-2"></span><?php echo lang('Short Link'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body" id="short_link_modal_body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="short_link_create_button"><span class="bi bi-plus-lg me-1"></span><?php echo lang('Create'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Access permissions panel for one folder. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="access_offcanvas" aria-labelledby="access_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="access_offcanvas_label"><span class="bi bi-shield-lock me-2"></span><?php echo lang('Access Permissions'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="access_offcanvas_body"></div>
</div>

<!-- Folder settings: the everyday knobs from edit_folder.php (order, archive,
     page styles) without leaving the manager. The full edit screen stays
     reachable from the context menu until the manager replaces it. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settings_offcanvas" aria-labelledby="settings_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="settings_offcanvas_label"><span class="bi bi-sliders me-2"></span><?php echo lang('Folder Settings'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="settings_offcanvas_body"></div>
</div>

<!-- Backup permissions: the one filesystem knob an operator actually needs
     out here, because a backup nobody can read or replace is not a backup. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="perm_offcanvas" aria-labelledby="perm_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="perm_offcanvas_label"><span class="bi bi-key me-2"></span><?php echo lang('Permissions'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="perm_offcanvas_body"></div>
</div>

<!-- Bulk page edit: the shared page switches (sitemap, search, comments,
     robots) and page styles applied to a whole selection at once. Every
     field defaults to "do not change". -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="bulk_pages_offcanvas" aria-labelledby="bulk_pages_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="bulk_pages_offcanvas_label"><span class="bi bi-ui-checks me-2"></span><?php echo lang('Bulk Edit Pages'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="bulk_pages_offcanvas_body"></div>
</div>

<!-- Bulk file edit: what edit_files.php did to a checked list -- move to a
     folder, the design flag, a description -- applied to the selection, plus
     the optimize run this screen already has. Every field defaults to
     "do not change". -->
<!-- Bulk rename.
     Renaming forty photographs one at a time is the job this screen kept
     sending people back to the keyboard for. Three rules cover nearly all of
     it -- find and replace, a prefix or suffix, and numbering -- and every one
     of them is shown against the real names before anything is written, because
     a rename is the one bulk operation whose mistake is invisible afterwards:
     the files are all still there, just called the wrong thing. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="bulk_rename_offcanvas" aria-labelledby="bulk_rename_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="bulk_rename_offcanvas_label"><span class="bi bi-input-cursor-text me-2"></span><?php echo lang('Bulk Rename'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="bulk_rename_offcanvas_body"></div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="bulk_files_offcanvas" aria-labelledby="bulk_files_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="bulk_files_offcanvas_label"><span class="bi bi-ui-checks me-2"></span><?php echo lang('Bulk Edit Files'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="bulk_files_offcanvas_body"></div>
</div>

<!-- Bulk product edit: what edit_products.php did to a checked list --
     status, price, stock, tax, shipping zones, barcodes -- applied to the
     selection, plus group membership, which is this screen's own idea of what
     a product belongs to. Every field defaults to "do not change". -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="bulk_products_offcanvas" aria-labelledby="bulk_products_offcanvas_label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="bulk_products_offcanvas_label"><span class="bi bi-ui-checks me-2"></span><?php echo lang('Bulk Edit Products'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo lang('Close'); ?>"></button>
    </div>
    <div class="offcanvas-body" id="bulk_products_offcanvas_body"></div>
</div>

<!-- Recursive folder delete: pre-flight summary + type-the-name confirmation.
     Execution reuses edit_pages.php (pages), the explorer API (files) and
     edit_folder.php (each emptied folder), so every safety rule stays where
     it has always lived. -->
<div class="modal fade" id="folder_delete_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 1rem;">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><span class="bi bi-trash me-2"></span><span id="folder_delete_title_text"><?php echo lang('Delete Folder and Contents'); ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body" id="folder_delete_body"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="button" class="btn btn-danger" id="folder_delete_confirm_button" disabled><span class="bi bi-trash me-2"></span><span id="folder_delete_button_text"><?php echo lang('Delete'); ?></span></button>
            </div>
        </div>
    </div>
</div>

<!-- Picking a destination from a list.
     Dragging is the quick way when both ends are on screen, and it is the only
     way there was: reaching a collapsed folder four levels down meant cutting,
     walking there and pasting. This window lists every folder the operator may
     write into, with a filter box, and hands the choice to the same code the
     drop handler runs. In the store it lists product groups and offers the two
     operations separately, because there they mean different things. -->
<div class="modal fade" id="transfer_modal" tabindex="-1" aria-labelledby="transfer_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 1rem;">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="transfer_modal_label"><span class="bi bi-folder-symlink me-2"></span><span id="transfer_modal_title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo lang('Close'); ?>"></button>
            </div>
            <div class="modal-body pt-0">
                <div id="transfer_summary" class="form-text mt-0 mb-2"></div>
                <div class="input-group input-group-sm rounded-pill mb-2">
                    <span class="input-group-text rounded-start-pill bi bi-search border-end-0"></span>
                    <input type="search" id="transfer_filter" class="form-control rounded-end-pill border-start-0" placeholder="<?php echo lang('Filter'); ?>" autocomplete="off" />
                </div>
                <div id="transfer_targets" class="list-group list-group-flush" style="max-height: 46vh; overflow-y: auto;"></div>
                <div id="transfer_note" class="form-text"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('Cancel'); ?></button>
                <button type="button" class="btn btn-outline-primary d-none" id="transfer_secondary_button" disabled></button>
                <button type="button" class="btn btn-primary" id="transfer_confirm_button" disabled></button>
            </div>
        </div>
    </div>
</div>

<!-- Quick look.
     The preview panel answers "what is this" beside the grid; this answers
     "let me actually look at it" without leaving the folder, and the arrow
     keys walk the listing under it -- which is how a folder of photographs is
     gone through. Not a Bootstrap modal: it takes the whole screen, carries
     its own keys, and must sit above the chat launcher. -->
<div id="explorer_quicklook" class="d-none" role="dialog" aria-modal="true">
    <div class="ql-bar">
        <span class="ql-name" id="quicklook_name"></span>
        <span class="ql-meta" id="quicklook_meta"></span>
        <span class="ms-auto d-flex align-items-center gap-1" id="quicklook_tools"></span>
        <button type="button" class="btn btn-sm btn-ghost no-popover" id="quicklook_close" title="<?php echo lang('Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="ql-stage" id="quicklook_stage">
        <button type="button" class="ql-nav ql-prev" id="quicklook_prev" title="<?php echo lang('Previous'); ?>"><i class="bi bi-chevron-left"></i></button>
        <div id="quicklook_media" class="d-flex align-items-center justify-content-center" style="max-width:100%;max-height:100%;"></div>
        <button type="button" class="ql-nav ql-next" id="quicklook_next" title="<?php echo lang('Next'); ?>"><i class="bi bi-chevron-right"></i></button>
    </div>
    <div class="ql-hint"><?php echo lang('Space closes, the arrow keys walk the folder'); ?></div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="explorer_toasts"></div>

<script type="text/javascript">
(function () {
    'use strict';

    var L = <?php echo encode_json($explorer_lang); ?>;

    var state = {
        area: '<?php echo $explorer_area; ?>',
        mode: '<?php echo $initial_mode; ?>',
        allFilter: '<?php echo $initial_filter; ?>',
        fileScope: '<?php echo $initial_scope; ?>',
        sharedGroups: [],
        backupPath: '',
        backupEntries: [],
        backupCrumbs: [],
        backupZip: true,
        backupTotal: '',
        folderId: <?php echo (int) $initial_folder_id; ?>,
        groupId: <?php echo (int) $initial_group_id; ?>,
        catalogCurrent: null,
        catalogCrumbs: [],
        viewType: 'grid',
        viewTypeExplicit: false,
        // Read back from the browser at boot; the server has no opinion about
        // how large one operator likes their tiles.
        tileSize: 'medium',
        // What the listing is narrowed to beyond the search box, as a set of
        // named tests. In memory only: a filter that came back after a reload
        // would be a folder that looks half empty for reasons nobody can see.
        filters: {},
        current: null,
        breadcrumb: [],
        items: [],
        ordered: [],
        selection: {},
        lastIndex: -1,
        // Where the keyboard is standing. Separate from lastIndex, which is
        // the far end of a shift-selection and stays put while the cursor
        // walks; they agree whenever a single row is picked.
        cursor: -1,
        clipboard: null,
        caps: {},
        sort: { key: 'name', dir: 'asc' },
        filter: '',
        pendingRename: null,
        diskUsage: '',
        progress: null,
        recycle: { available: false, folder_id: 0, retention_days: 0, inside: false, count: 0 },
        binEntries: {}
    };

    function insideBin() { return !!(state.recycle && state.recycle.inside); }

    var dragState = null;
    var dragPreviewHold = false;
    var menuActionAt = 0;
    var loadSeq = 0;
    // The two areas keep their own addresses, so a link, a refresh and the back
    // button all land where the operator was, and the left menu keeps the right
    // entry highlighted.
    var explorerArea = state.area;
    var explorerUrl = path + software_directory + ((explorerArea === 'catalog') ? '/view_product_groups.php' : '/view_folders.php');
    var explorerCatalogUrl = path + software_directory + '/view_product_groups.php';

    // Bootstrap Icons filetype glyphs (subset that exists in the shipped
    // icon font); anything unknown falls back to a plain file icon.
    var FILETYPE_ICONS = {
        aac: 'bi-filetype-aac', ai: 'bi-filetype-ai', bmp: 'bi-filetype-bmp', cs: 'bi-filetype-cs',
        css: 'bi-filetype-css', csv: 'bi-filetype-csv', doc: 'bi-filetype-doc', docx: 'bi-filetype-docx',
        exe: 'bi-filetype-exe', gif: 'bi-filetype-gif', heic: 'bi-filetype-heic', html: 'bi-filetype-html',
        java: 'bi-filetype-java', jpg: 'bi-filetype-jpg', jpeg: 'bi-filetype-jpg', js: 'bi-filetype-js',
        json: 'bi-filetype-json', key: 'bi-filetype-key', md: 'bi-filetype-md', mov: 'bi-filetype-mov',
        mp3: 'bi-filetype-mp3', mp4: 'bi-filetype-mp4', otf: 'bi-filetype-otf', pdf: 'bi-filetype-pdf',
        php: 'bi-filetype-php', png: 'bi-filetype-png', ppt: 'bi-filetype-ppt', pptx: 'bi-filetype-pptx',
        psd: 'bi-filetype-psd', py: 'bi-filetype-py', raw: 'bi-filetype-raw', rb: 'bi-filetype-rb',
        sass: 'bi-filetype-sass', scss: 'bi-filetype-scss', sh: 'bi-filetype-sh', sql: 'bi-filetype-sql',
        svg: 'bi-filetype-svg', tiff: 'bi-filetype-tiff', ttf: 'bi-filetype-ttf', txt: 'bi-filetype-txt',
        wav: 'bi-filetype-wav', woff: 'bi-filetype-woff', xls: 'bi-filetype-xls', xlsx: 'bi-filetype-xlsx',
        xml: 'bi-filetype-xml', yml: 'bi-filetype-yml', zip: 'bi-file-earmark-zip', webp: 'bi-file-earmark-image',
        avi: 'bi-file-earmark-play', webm: 'bi-file-earmark-play', mpg: 'bi-file-earmark-play', mpeg: 'bi-file-earmark-play',
        wmv: 'bi-file-earmark-play', wma: 'bi-file-earmark-music', ogg: 'bi-file-earmark-music'
    };

    var OPTIMIZABLE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp'];
    var WEBP_SOURCE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff'];

    // ── Small helpers ───────────────────────────────────────────────────

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Close a Bootstrap modal and mean it.
    //
    // Modal.hide() returns without doing anything while the window is still
    // fading in -- Bootstrap guards it with _isTransitioning. Every window on
    // this screen is closed by something that finished after it opened, so a
    // fast answer from the server lands inside that gap and the window stays
    // on top of a job that has already been done. It has been reported twice
    // as "it did nothing", once for the upload window and once for the delete
    // confirmation, which is why this is here rather than at either call.
    //
    // The class is the tell: hide() removes it at once when it is heard, so a
    // window still wearing it did not hear us, and is asked again the moment
    // it has finished arriving.
    function hideModal(modal, elementId) {

        if (!modal) { return; }

        var element = document.getElementById(elementId);

        modal.hide();

        if ((!element) || (!element.classList.contains('show'))) { return; }

        element.addEventListener('shown.bs.modal', function once() {
            element.removeEventListener('shown.bs.modal', once);
            modal.hide();
        });
    }

    function fmt(template, values) {
        var result = String(template);
        values.forEach(function (value, index) {
            result = result.split('{var:' + (index + 1) + '}').join(value);
        });
        return result;
    }

    function storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function storageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { }
    }

    // Putting text on the clipboard.
    //
    // navigator.clipboard exists only in a secure context, and a fair number
    // of the sites this software runs on are still served over http -- so the
    // old textarea and execCommand path is kept as the fallback rather than
    // the feature simply being missing there.
    function copyText(text, message) {

        function fallback() {

            var area = document.createElement('textarea');

            area.value = text;
            area.setAttribute('readonly', 'readonly');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();

            var ok = false;

            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }

            area.remove();
            toast(ok ? (message || L.copied) : L.copy_failed, ok);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                toast(message || L.copied);
            }, fallback);
            return;
        }

        fallback();
    }

    // The three shapes an address is asked for.
    //
    // The full one is for pasting into a browser or a message; the site path
    // is what page content carries, so it survives a move to another domain;
    // the HTML is the tag somebody was about to type by hand around it.
    function itemAddress(item, shape) {

        var raw = item.url || '';

        if (raw === '') { return ''; }

        if (shape === 'full') { return new URL(raw, window.location.href).href; }
        if (shape === 'path') { return raw; }

        if (item.is_image) {
            return '<img src="' + esc(raw) + '" alt="' + esc(item.description || '') + '"' +
                ((item.image_width > 0) ? (' width="' + item.image_width + '" height="' + item.image_height + '"') : '') + ' />';
        }

        return '<a href="' + esc(raw) + '">' + esc(item.name || raw) + '</a>';
    }

    function copyAddresses(items, shape) {

        var lines = items.map(function (item) { return itemAddress(item, shape); }).filter(function (line) { return line !== ''; });

        if (lines.length === 0) { toast(L.copy_failed, false); return; }

        copyText(lines.join('\n'), L.copied);
    }

    // A notice, and optionally the one thing to do about it.
    //
    // The third argument is { label, run }. An action makes the notice the
    // only place that offer appears, so it is given longer to be read: three
    // and a half seconds is enough to register "moved" and not enough to
    // decide it was the wrong folder.
    function toast(message, ok, action) {
        var container = document.getElementById('explorer_toasts');
        var element = document.createElement('div');
        element.className = 'toast align-items-center border-0 text-bg-' + (ok === false ? 'danger' : 'success');
        element.setAttribute('role', 'status');
        element.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
            (action ? '<button type="button" class="btn btn-sm btn-light my-auto me-1 text-nowrap" data-role="toast-action"></button>' : '') +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        element.querySelector('.toast-body').textContent = message;
        container.appendChild(element);
        var instance = new bootstrap.Toast(element, { delay: action ? 9000 : 3500 });

        if (action) {
            var actionButton = element.querySelector('[data-role="toast-action"]');
            actionButton.textContent = action.label;
            actionButton.addEventListener('click', function () {
                instance.hide();
                action.run();
            });
        }

        element.addEventListener('hidden.bs.toast', function () { element.remove(); });
        instance.show();
    }

    function api(payload, done, fail) {
        payload = Object.assign({ action: 'file_explorer', token: software_token }, payload);
        $.ajax({
            url: 'api.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (response) {
                // A server that refuses the request because of its size tells us what it can take,
                // and the screen keeps that number so the next file is checked before it is sent.
                if ((response) && (response.upload_limit_bytes)) {
                    state.caps.upload_max_bytes = response.upload_limit_bytes;
                    state.caps.upload_max_label = Math.round(response.upload_limit_bytes / (1024 * 1024)) + ' MB';
                }

                done && done(response || {});
            },
            error: function (xhr) {
                // A caller that works through a queue has to hear about this, otherwise the queue
                // stops half way and the screen sits there with a bar that never moves again.
                if (fail) { fail(xhr); return; }
                toast(L.request_failed, false);
            }
        });
    }

    function itemKey(item) { return item.kind + ':' + item.id; }

    function findItem(kind, id) {
        for (var i = 0; i < state.ordered.length; i++) {
            if (state.ordered[i].kind === kind && String(state.ordered[i].id) === String(id)) {
                return state.ordered[i];
            }
        }
        return null;
    }

    function selectedItems() {
        return state.ordered.filter(function (item) { return state.selection[itemKey(item)]; });
    }

    function accessLabel(type) {
        return L[type] || type;
    }

    function currentSendTo() {
        if (insideCatalog()) {

            // Back to the screen the operator left, flat lists included: adding
            // a product from All Products and landing on the top group instead
            // would lose their place.
            if (state.allFilter === 'products') { return explorerCatalogUrl + '?view=products'; }
            if (state.allFilter === 'variants') { return explorerCatalogUrl + '?view=variants'; }
            if (state.allFilter === 'recycled') { return explorerCatalogUrl + '?view=bin'; }

            return explorerCatalogUrl + (state.groupId > 0 ? ('?group=' + state.groupId) : '');
        }

        // Coming back from the short link editor has to land in the short link
        // list, not in whichever folder was open before it.
        if (insideShortLinks()) { return explorerUrl + '?view=short_links'; }

        // The same for the views across every file: whoever left from
        // Pictures, or from Files narrowed to documents, comes back there.
        if (state.mode === 'all') {
            return explorerUrl + '?view=' + (state.allFilter || 'files') +
                (((state.allFilter === '') && state.fileScope) ? ('&scope=' + encodeURIComponent(state.fileScope)) : '');
        }

        return explorerUrl + (state.folderId > 0 ? ('?folder=' + state.folderId) : '');
    }

    // ── Loading and rendering ───────────────────────────────────────────

    // A folder listing that could not be fetched, drawn where the grid would
    // be, with the one thing to do about it.
    //
    // A gateway or server timeout during a long bulk run -- Cloudflare's 524
    // after about a hundred seconds, an IIS/FastCGI activity timeout -- answers
    // with an HTML error page, not the JSON this screen expects. Without this
    // the screen kept whatever folder was already open and said nothing, so
    // every breadcrumb and tree click fired another request that failed the
    // same way and the operator was left with a screen only a full page reload
    // could clear. The retry re-runs the same load, so a folder comes back the
    // moment the server does, without losing the place in the tree.
    function renderLoadError(folderId, done, message) {
        var container = document.getElementById('explorer_content');

        if (container) {
            container.innerHTML = '<div class="text-center my-5 empty-note">' +
                '<span class="bi bi-wifi-off display-4 d-block mb-2"></span>' +
                esc(message || L.load_failed) +
                '<div class="mt-3"><button type="button" class="btn btn-outline-secondary btn-sm" data-role="load-retry">' +
                '<span class="bi bi-arrow-clockwise me-1"></span>' + esc(L.load_retry) + '</button></div></div>';

            var retry = container.querySelector('[data-role="load-retry"]');

            if (retry) {
                retry.addEventListener('click', function () { load(folderId, done); });
            }
        }

        renderStatusbar();
    }

    // The toolbar search narrows the folder it was typed in, and nothing
    // else: it is a way of finding a file in a long listing, not a filter
    // the operator switched on. It is let go the moment the listing shows a
    // different folder, the way a desktop file manager drops its search when
    // another folder is opened. Left in place it hid every folder of the
    // parent whose name did not contain the words typed two levels down, and
    // the grid read as an empty folder -- across the breadcrumb, across the
    // tree, until a full page reload started the screen over. A refresh of
    // the same folder (after an optimise run, a rename, a paste) keeps it:
    // the operator is still looking at the list they narrowed.
    function clearSearch() {
        state.filter = '';

        var input = document.getElementById('filter_input');

        if (input) { input.value = ''; }
    }

    // What makes one listing a different listing from the last: the mode
    // (a folder, a flat view, the store, backups, short links), the flat
    // view's own narrowing, the backup path and the folder or group asked
    // for. The same key twice in a row is a refresh and keeps the search.
    var lastListingKey = null;

    function listingKey(id) {
        return [state.mode, state.allFilter, state.fileScope, state.backupPath, id].join('|');
    }

    function load(folderId, done) {
        var seq = ++loadSeq;

        var key = listingKey(folderId);

        if (key !== lastListingKey) { clearSearch(); }

        lastListingKey = key;

        // The shared overview is a report, not a folder listing: its own
        // endpoint, its own rendering, and none of the folder chrome.
        if (state.mode === 'shared') {
            loadShared(seq, done);
            return;
        }

        if (state.mode === 'backups') {
            loadBackups(seq, done);
            return;
        }

        if (state.mode === 'short_links') {
            loadShortLinks(seq, done);
            return;
        }

        if (state.mode === 'catalog') {
            loadCatalog(seq, folderId, done);
            return;
        }

        var payload = { type: 'explorer_list' };

        if (state.mode === 'all') {
            payload.view_mode = 'all_files';

            if (state.allFilter !== '') { payload.file_filter = state.allFilter; }
            if ((state.allFilter === '') && (state.fileScope !== '')) { payload.file_scope = state.fileScope; }
        } else {
            payload.folder_id = folderId;
        }

        // Only send the view when the person picked one this session, so the
        // server-side remembered view wins on the first load.
        if (state.viewTypeExplicit) { payload.view_type = state.viewType; }

        api(payload, function (response) {
            // A newer navigation superseded this answer while it was in
            // flight; rendering it now would flip the screen back.
            if (seq !== loadSeq) { return; }

            // Not the JSON listing this screen asked for: a gateway or server
            // timeout answered with an HTML error page, which jQuery hands back
            // as a string. A response with no status field is a failed load,
            // not an empty folder -- show the error and a retry.
            if ((typeof response !== 'object') || (response === null) || (typeof response.status === 'undefined')) {
                renderLoadError(folderId, done, L.load_failed);
                return;
            }

            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                if (folderId > 0) { load(0, done); }
                return;
            }

            state.folderId = response.current.id;
            // The request named the folder as the operator did (0 for the
            // top); the answer names it as the server does. Remember the
            // latter, so a refresh of this folder is seen as the same listing.
            lastListingKey = listingKey(state.folderId);
            state.viewType = response.view_type;
            state.current = response.current;
            state.breadcrumb = response.breadcrumb || [];
            state.caps = response.capabilities || {};
            state.diskUsage = response.disk_usage || '';
            state.items = { folders: response.folders || [], pages: response.pages || [], files: response.files || [] };
            state.recycle = response.recycle || { available: false, folder_id: 0, retention_days: 0, inside: false, count: 0 };
            state.binEntries = response.bin_entries || {};
            state.selection = {};
            state.lastIndex = -1;

            var query = (state.mode === 'all') ? ('?view=' + (state.allFilter || 'files')) : (state.folderId > 0 ? ('?folder=' + state.folderId) : '');

            if ((state.mode === 'all') && (state.allFilter === '') && (state.fileScope !== '')) { query += '&scope=' + encodeURIComponent(state.fileScope); }

            window.history.replaceState(null, '', 'view_folders.php' + query);

            document.getElementById('new_page_item').classList.toggle('d-none', !state.caps.can_create_pages);
            dragPreviewHold = false;
            seoDetailCache = {};
            syncTreeToPath();
            renderRecycleChrome();
            updateCreateLinks();
            renderBreadcrumb();
            renderContent();
            renderPreview(null);
            renderStatusbar();
            syncViewButtons();

            if (state.pendingRename) {
                var pending = state.pendingRename;
                state.pendingRename = null;
                var item = findItem(pending.kind, pending.id);
                if (item) {
                    setSelection([item], item);
                    beginRename(item);
                }
            }

            if (done) { done(); }
        }, function (xhr) {
            // Transport failure or a non-2xx answer (the gateway/IIS
            // timeout case). Same persistent error and retry as a
            // non-JSON body, instead of a toast that leaves a dead screen.
            if (seq !== loadSeq) { return; }
            renderLoadError(folderId, done, L.load_failed);
        });
    }

    // ── Backups browser ─────────────────────────────────────────────────
    //
    // A window onto data/backups. Those files have no database records and
    // live outside the web root, so every action here is a filesystem
    // operation addressed by path - but they are drawn, sorted, searched and
    // selected by the same code as the rest of the manager, because to the
    // person using it a backup is just a folder.

    function enterBackups(path) {
        if (state.area === 'catalog') { return; }
        state.mode = 'backups';
        state.allFilter = '';
        state.backupPath = path || '';
        load(0, highlightTree);
    }

    function insideBackups() { return state.mode === 'backups'; }

    // Short links are a flat list with no folder above them: one name, one
    // destination. They ride the ordinary grid because the server dresses them
    // as files, the way a backup entry is dressed as one.
    function insideShortLinks() { return state.mode === 'short_links'; }

    // What the Recycle Bin calls this row.
    //
    // A short link travels dressed as a file so the grid can draw it, but the
    // bin keeps one restore record per kind and 'file' means a different table.
    // Asked here rather than at each call site, because getting it wrong
    // restores nothing and says nothing.
    function binKindOf(item) {
        return (item && item.short_link) ? 'short_link' : item.kind;
    }
    function insideCatalog() { return state.mode === 'catalog'; }

    function enterAllProducts() {
        if (state.area !== 'catalog') { return; }
        state.mode = 'catalog';
        state.allFilter = 'products';
        state.groupId = 0;
        load(0, highlightTree);
    }

    function enterAllVariantSets() {
        if (state.area !== 'catalog') { return; }
        state.mode = 'catalog';
        state.allFilter = 'variants';
        state.groupId = 0;
        load(0, highlightTree);
    }

    // Back to the top of the tree, from All Products, All Variant Sets or the
    // bin -- what the first crumb in the breadcrumb does, reachable from the
    // menu as well because on a phone that menu is the navigation.
    function enterCatalogRoot() {
        if (state.area !== 'catalog') { return; }
        state.mode = 'catalog';
        state.allFilter = '';
        state.groupId = 0;
        load(0, highlightTree);
    }

    // ── Catalog: bin, restore, and leaving a group ──────────────────────

    // Paste in the store.
    //
    // Cut a group and it changes parent; copy one and it is duplicated with
    // everything under it. Cut a product and its membership moves out of the
    // group it was listed in and into this one; copy it and this group simply
    // lists it too. The product itself is never duplicated -- the same item
    // appearing in several categories is what the catalog is for.
    function catalogPaste(targetGroupId) {

        if ((!state.clipboard) || (targetGroupId <= 0)) { return; }

        var payload = state.clipboard.items.map(function (entry) { return { kind: entry.kind, id: entry.id }; });
        var mode = state.clipboard.mode;
        var sourceGroupId = state.clipboard.sourceGroupId || 0;

        api({
            type: 'explorer_catalog_paste',
            target_group_id: targetGroupId,
            source_group_id: sourceGroupId,
            mode: mode,
            items: payload
        }, function (response) {

            toast(response.message || L.request_failed, response.status !== 'error');

            if (response.status === 'error') { return; }

            if ((response.undo_steps) && (response.undo_steps.length > 0)) {
                pushUndo({ undo: { type: 'catalog_steps', steps: response.undo_steps }, redo: null });
            }

            // A cut is spent once it lands; a copy stays on the clipboard so the
            // same thing can be listed in several groups in a row.
            if (mode === 'cut') { state.clipboard = null; }

            reload();
        });
    }

    // What a menu entry acts on.
    //
    // The right click has already put the clicked row into the selection, so
    // the selection is the answer whenever there is one. Reaching for the
    // clicked row instead deleted a single tile out of a grid the operator had
    // just selected whole -- the folder menu has always worked on the
    // selection, and so does this one.
    function catalogTargets(item) {

        var selected = selectedItems();

        if (selected.length > 0) { return selected; }

        return item ? [item] : [];
    }

    function catalogRecycle(items) {

        items = (items || []).filter(function (entry) {
            return ((entry) && ((entry.kind === 'group') || (entry.kind === 'product')));
        });

        if (items.length === 0) { return; }

        var groups = items.filter(function (entry) { return entry.kind === 'group'; });
        var products = items.filter(function (entry) { return entry.kind === 'product'; });

        // A single row goes without a question, the way a file does: undo puts
        // it straight back. More than one, or a group that still lists
        // products, is worth stopping for.
        var carriesProducts = groups.some(function (entry) {
            return ((entry.counts) && ((entry.counts.products_deep > 0) || (entry.counts.groups > 0)));
        });

        function send(payload, done) {

            api({ type: 'explorer_catalog_recycle', items: payload }, function (response) {

                if (done) { done(); }

                if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

                // The bin is a place, so the undo entry is the same shape the
                // file manager uses: one step back puts these rows exactly
                // where they were, with the published state they had.
                undoToast(fmt(L.moved_to_bin_count, [payload.length]), pushUndo({
                    undo: { type: 'catalog_restore', items: payload },
                    redo: { type: 'catalog_recycle', items: payload }
                }));

                reload();
            });
        }

        if ((items.length > 1) || carriesProducts) {
            openDeleteSelectionModal(items, 'bin', send);
            return;
        }

        send(items.map(function (entry) { return { kind: entry.kind, id: entry.id }; }));
    }

    function catalogRestore(items) {

        items = (items || []).filter(function (entry) {
            return ((entry) && ((entry.kind === 'group') || (entry.kind === 'product')));
        });

        if (items.length === 0) { return; }

        var payload = items.map(function (entry) { return { kind: entry.kind, id: entry.id }; });

        api({ type: 'explorer_catalog_restore', items: payload }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');
            if (response.status !== 'error') { reload(); }
        });
    }

    // Leaving a group is not deleting, and the confirmation says so in those
    // words -- an operator who reads "delete" here would expect the product to
    // be gone from the shop, which is the opposite of what happens.
    // The last step out of the store's bin. It goes through the same deletes
    // the edit screens use, so a group loses its images, attributes, short
    // links and tag clouds exactly as it would there. Products under a purged
    // group are left alone: binning the group never binned them.
    function catalogPurge(items, all) {

        var payload = (items || []).map(function (entry) { return { kind: entry.kind, id: entry.id }; });

        if (!all && (payload.length === 0)) { return; }

        function send(done) {

            api(all ? { type: 'explorer_catalog_purge', all: true } : { type: 'explorer_catalog_purge', items: payload }, function (response) {
                if (done) { done(); }
                toast(response.message || L.request_failed, response.status !== 'error');
                reload();
            });
        }

        // Emptying the whole bin has nothing to list, so it keeps the short
        // question; a picked set goes through the modal like every other
        // delete on this screen.
        if (all) {
            if (!window.confirm(fmt(L.bin_confirm_empty, [state.recycle.count]))) { return; }
            send();
            return;
        }

        openDeleteSelectionModal(items, 'purge', function (confirmed, done) { send(done); });
    }

    // Publish or unpublish what is selected.
    //
    // The clicked row decides the direction, the way the design flag does among
    // the files: a mixed selection then ends up all one way, which is what the
    // operator asked for by picking that row's entry.
    function catalogSetEnabled(items, enabled) {

        items = (items || []).filter(function (entry) {
            return ((entry) && ((entry.kind === 'group') || (entry.kind === 'product')));
        });

        if (items.length === 0) { return; }

        var payload = items.map(function (entry) { return { kind: entry.kind, id: entry.id }; });

        // What each row was, so one step back puts every one of them back the
        // way it was rather than all the same way.
        var previous = items.map(function (entry) {
            return { kind: entry.kind, id: entry.id, enabled: (entry.enabled !== false) };
        });

        api({ type: 'explorer_catalog_enable', items: payload, enabled: enabled }, function (response) {

            if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

            undoToast(response.message || L.updated, pushUndo({
                undo: { type: 'catalog_enable_restore', states: previous },
                redo: { type: 'catalog_enable', items: payload, enabled: enabled }
            }));

            reload();
        });
    }

    function catalogRemoveFromGroup(item) {

        if ((!item) || (item.kind !== 'product') || (state.groupId <= 0)) { return; }

        if (window.confirm(fmt(L.remove_from_group_confirm, [item.name])) === false) { return; }

        api({ type: 'explorer_catalog_membership_remove', product_id: item.id, group_id: state.groupId }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');
            if (response.status !== 'error') { reload(); }
        });
    }

    function enterCatalogBin() {
        state.mode = 'catalog';
        state.allFilter = 'recycled';
        state.groupId = 0;
        load(0, highlightTree);
    }

    function insideCatalogBin() { return (insideCatalog() && (state.allFilter === 'recycled')); }

    // A group is a folder you walk into; a variant set is one sellable thing
    // whose members are its variants. Same table, same operations, different
    // meaning on screen -- and the storefront treats them apart too, which is
    // why display_type is not a cosmetic setting.
    function isVariantSet(item) { return (item && item.catalog_role === 'variant_set'); }

    // The short description, when it says something the name does not.
    function catalogSubName(item) {

        if ((!item) || ((item.kind !== 'group') && (item.kind !== 'product'))) { return ''; }

        var text = String(item.short_description || '').trim();

        if ((text === '') || (text === String(item.name || '').trim())) { return ''; }

        return text;
    }

    // Sold out, the storefront's own rule: stock is tracked, none is left, and
    // back orders are off. A product on back order can still be ordered, so it
    // does not carry the mark. A group carries it when anything under it does,
    // counted by the server in the query it already runs for the tile.
    function outOfStock(item) {

        if (!item) { return false; }

        if (item.kind === 'product') { return (!!item.inventory) && (item.quantity <= 0) && (!item.backorder); }

        if (item.kind === 'group') { return ((item.out_of_stock || 0) > 0); }

        return false;
    }

    function stockDotHtml(item) {

        if (!outOfStock(item)) { return ''; }

        var title = (item.kind === 'group') ? fmt(L.out_of_stock_in_group, [item.out_of_stock]) : L.out_of_stock;

        return '<span class="stock-dot" title="' + esc(title) + '"></span>';
    }

    function moneyLabel(cents) {
        if ((cents === null) || (cents === undefined)) { return ''; }
        return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function priceRangeLabel(item) {
        if ((item.price_min === null) || (item.price_min === undefined)) { return ''; }
        if (item.price_min === item.price_max) { return moneyLabel(item.price_min); }
        return moneyLabel(item.price_min) + ' – ' + moneyLabel(item.price_max);
    }

    function loadBackups(seq, done) {
        var payload = { type: 'explorer_backups_list', path: state.backupPath || '' };

        if (state.viewTypeExplicit) { payload.view_type = state.viewType; }

        api(payload, function (response) {
            if (seq !== loadSeq) { return; }

            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                state.mode = 'browse';
                load(0, highlightTree);
                return;
            }

            // Dressed as ordinary folders and files by the server, so the
            // normal renderer, sorter, search box and selection all work on
            // them without knowing they came off the disk.
            state.folderId = 0;
            state.viewType = response.view_type;
            state.current = null;
            state.breadcrumb = [];
            state.items = { folders: response.folders || [], pages: [], files: response.files || [] };
            state.selection = {};
            state.lastIndex = -1;
            state.backupPath = response.path || '';

            // this view can be opened straight from a link, so it carries the limits with it
            if (response.capabilities) { state.caps = Object.assign({}, state.caps, response.capabilities); }
            state.backupCrumbs = response.breadcrumb || [];
            state.backupZip = !!response.zip_available;
            state.backupTotal = response.total_label || '';

            window.history.replaceState(null, '', 'view_folders.php?view=backups');

            updateCreateLinks();
            renderBreadcrumb();
            renderContent();
            renderPreview(null);
            renderStatusbar();
            syncViewButtons();
            highlightTree();

            if (done) { done(); }
        });
    }

    function backupDownloadUrl(path) {
        return 'view_folders.php?backup_download=' + encodeURIComponent(path) + '&token=' + encodeURIComponent(software_token);
    }

    // Every backup action is addressed by path, not by id.
    function backupAction(type, item, extra, done) {
        var payload = { type: type, path: item.path };

        if (extra) {
            Object.keys(extra).forEach(function (key) { payload[key] = extra[key]; });
        }

        api(payload, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');

            if (done) { done(response); }

            if (response.status !== 'error') { reload(); }
        });
    }

    function compressBackup(item) {
        toast(L.compressing + '...');

        api({ type: 'explorer_backup_zip', path: item.path }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');

            if (response.status === 'success') {
                // The archive is written next to the backup and the download
                // starts on its own, so the operator does not have to hunt
                // for the row that just appeared.
                window.location.href = backupDownloadUrl(response.path);
                window.setTimeout(reload, 1500);
            }
        });
    }

    function openPermissionsPanel(item) {
        var body = document.getElementById('perm_offcanvas_body');
        var isFolder = (item.kind === 'folder');
        var current = item.permissions || '';

        body.innerHTML =
            '<h6 class="text-break mb-1"><span class="bi ' + (isFolder ? 'bi-folder-fill text-warning' : 'bi-file-earmark') + ' me-2"></span>' + esc(item.name) + '</h6>' +
            '<div class="form-text mb-3">' + esc(L.current_permissions) + ': <code>' + esc(current) + '</code></div>' +
            '<div class="mb-3">' +
            '<label class="form-label" for="perm_folder_mode">' + esc(L.folders) + '</label>' +
            '<input type="text" id="perm_folder_mode" class="form-control" inputmode="numeric" maxlength="3" value="755" />' +
            '</div>' +
            '<div class="mb-3">' +
            '<label class="form-label" for="perm_file_mode">' + esc(L.files) + '</label>' +
            '<input type="text" id="perm_file_mode" class="form-control" inputmode="numeric" maxlength="3" value="644" />' +
            '</div>' +
            (isFolder
                ? ('<div class="form-check form-switch mb-3">' +
                    '<input class="form-check-input" type="checkbox" role="switch" id="perm_recursive" checked />' +
                    '<label class="form-check-label" for="perm_recursive">' + esc(L.apply_to_contents) + '</label>' +
                    '</div>')
                : '') +
            '<div class="form-text mb-3">' + esc(L.permissions_note) + '</div>' +
            '<div class="text-end"><button type="button" id="perm_save_button" class="btn btn-success"><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

        document.getElementById('perm_save_button').addEventListener('click', function () {
            var recursive = document.getElementById('perm_recursive');

            api({
                type: 'explorer_backup_chmod',
                path: item.path,
                folder_mode: document.getElementById('perm_folder_mode').value,
                file_mode: document.getElementById('perm_file_mode').value,
                recursive: (recursive && recursive.checked) ? '1' : '0'
            }, function (response) {
                toast(response.message || L.request_failed, response.status !== 'error');

                if (response.status === 'success') {
                    permOffcanvas.hide();
                    reload();
                }
            });
        });

        if (!permOffcanvas) {
            permOffcanvas = new bootstrap.Offcanvas(document.getElementById('perm_offcanvas'));
        }

        permOffcanvas.show();
    }

    // ── Shared folders overview ─────────────────────────────────────────
    //
    // Not a directory: a report of which closed folders were opened to which
    // people. It lives outside the tree on purpose - these folders are all
    // somewhere in the tree already, and showing them twice as if they were
    // places would invite somebody to file content "into" the report.

    // The catalog, through the same shell.
    //
    // Groups land in the folders bucket and products in the pages bucket, so
    // the sorter, the search box, selection, the clipboard and the renderer all
    // work on them without being told anything new -- the same trick the
    // backups view uses to feed this screen from the filesystem.
    function loadCatalog(seq, groupId, done) {

        var payload = { type: 'explorer_catalog_list' };

        if (state.allFilter === 'recycled') {
            payload.view_mode = 'recycled';
        } else if (state.allFilter === 'products') {
            payload.view_mode = 'all_products';
        } else if (state.allFilter === 'variants') {
            payload.view_mode = 'variant_sets';
        } else {
            payload.group_id = ((groupId === undefined) || (groupId === null)) ? (state.groupId || 0) : groupId;
        }

        api(payload, function (response) {
            if (seq !== loadSeq) { return; }

            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                if (done) { done(); }
                return;
            }

            state.groupId = response.group_id || 0;
            lastListingKey = listingKey(state.groupId);
            state.folderId = 0;
            state.catalogCurrent = response.current || null;
            state.catalogCrumbs = response.breadcrumb || [];
            state.catalogTotals = response.catalog_totals || { groups: 0, products: 0 };
            state.current = null;
            state.breadcrumb = [];
            state.items = { folders: response.groups || [], pages: response.products || [], files: [] };
            state.selection = {};
            state.lastIndex = -1;

            if (response.capabilities) { state.caps = Object.assign({}, state.caps, response.capabilities); }
            if (response.recycle) { state.recycle = response.recycle; }
            state.binEntries = response.bin_entries || {};

            var query = (state.allFilter === 'products')
                ? '?view=products'
                : ((state.allFilter === 'variants')
                    ? '?view=variants'
                    : ((state.allFilter === 'recycled') ? '?view=bin' : (state.groupId > 0 ? ('?group=' + state.groupId) : '')));
            window.history.replaceState(null, '', 'view_product_groups.php' + query);

            var countElement = document.getElementById('all_products_count');
            if (countElement) { countElement.textContent = state.catalogTotals.products || ''; }

            var variantCountElement = document.getElementById('all_variant_sets_count');
            if (variantCountElement) { variantCountElement.textContent = state.catalogTotals.variant_sets || ''; }

            updateCreateLinks();
            renderRecycleChrome();
            renderBreadcrumb();
            renderContent();
            renderPreview(null);
            renderStatusbar();
            syncViewButtons();
            syncTreeToPath();

            // A row that was just created is selected and put straight into its
            // name box. Without this a new group appears somewhere in a grid of
            // thirteen others called "New Product Group" and the operator has to
            // go looking for the thing they just made.
            if (state.pendingRename) {

                var pending = state.pendingRename;
                state.pendingRename = null;

                var created = findItem(pending.kind, pending.id);

                if (created) {
                    setSelection([created], created);
                    beginRename(created);
                }
            }

            if (done) { done(); }
        });
    }

    // Which pages actually show this group or product.
    //
    // A group can be the subject of a legacy catalog page and at the same time
    // be listed by a widget living in a page style that several pages share, so
    // there is no single address to send the operator to. The panel asks for
    // the list only when it is open, because both routes scan text columns.
    function loadCatalogPages(item, container) {

        if (!container) { return; }

        container.innerHTML = '<span class="text-body-secondary small">' + esc(L.appears_loading) + '</span>';

        api({ type: 'explorer_catalog_pages', item_kind: item.kind, item_id: item.id }, function (response) {

            // The panel may have moved on to another item while this was out.
            if (container.getAttribute('data-for') !== itemKey(item)) { return; }

            if ((response.status !== 'success') || (!response.pages) || (response.pages.length === 0)) {
                container.innerHTML = '<span class="text-body-secondary small">' + esc(L.appears_nowhere) + '</span>';
                return;
            }

            var html = '';

            response.pages.forEach(function (page) {
                html += '<div class="prev-row"><a href="edit_page.php?id=' + page.id + '" class="text-truncate">' +
                    '<span class="bi bi-window-stack me-1"></span>' + esc(page.name) + '</a></div>';
            });

            container.innerHTML = html;
        });
    }

    function loadShortLinks(seq, done) {

        var payload = { type: 'explorer_short_links_list' };

        if (state.viewTypeExplicit) { payload.view_type = state.viewType; }

        api(payload, function (response) {

            if (seq !== loadSeq) { return; }

            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                state.mode = 'browse';
                load(0, highlightTree);
                return;
            }

            state.folderId = 0;
            state.viewType = response.view_type;
            state.current = null;
            state.breadcrumb = [];

            // This view can be opened straight from a link, so it carries the
            // settings the renderer reads with it.
            if (response.capabilities) { state.caps = Object.assign({}, state.caps, response.capabilities); }
            if (response.recycle) { state.recycle = response.recycle; }

            state.items = { folders: [], pages: [], files: response.files || [] };
            state.selection = {};
            state.lastIndex = -1;

            window.history.replaceState(null, '', 'view_folders.php?view=short_links');

            updateCreateLinks();
            renderBreadcrumb();
            renderContent();
            renderPreview(null);
            renderStatusbar();
            renderRecycleChrome();
            syncViewButtons();
            highlightTree();

            // Named after the wizard finished, in place: the row exists by the
            // time this runs, so this is the first moment it can be renamed.
            if (state.pendingShortLinkRename) {

                var pending = state.pendingShortLinkRename;
                state.pendingShortLinkRename = null;

                var target = findItem('file', pending);

                if (target) {
                    setSelection([target], target);
                    beginRename(target);
                }
            }

            if (done) { done(); }
        });
    }

    function loadShared(seq, done) {
        api({ type: 'explorer_shared_list' }, function (response) {
            if (seq !== loadSeq) { return; }

            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                state.mode = 'browse';
                load(0, highlightTree);
                return;
            }

            state.folderId = 0;
            state.current = null;
            state.breadcrumb = [];
            state.items = { folders: [], pages: [], files: [] };
            state.ordered = [];
            state.selection = {};
            state.lastIndex = -1;
            state.sharedGroups = response.groups || [];

            window.history.replaceState(null, '', 'view_folders.php?view=shared');

            updateCreateLinks();
            renderBreadcrumb();
            renderShared();
            renderPreview(null);
            renderStatusbar();

            // The shared report draws its own markup rather than going through
            // renderContent(), which is where the filter button is hidden and
            // shown -- without this it would still be sitting in the toolbar,
            // left over from the view before, narrowing a list it knows nothing
            // about.
            renderFilterChrome();
            highlightTree();

            if (done) { done(); }
        });
    }

    function renderShared() {
        var container = document.getElementById('explorer_content');
        var groups = state.sharedGroups || [];

        if (groups.length === 0) {
            container.innerHTML = '<div class="text-center my-5 empty-note"><span class="bi bi-shield-lock fs-1 d-block mb-2"></span>' + esc(L.shared_empty) + '</div>' +
                '<div id="explorer_upload_overlay"></div>';
            return;
        }

        var html = '<div id="shared_list">' +
            '<p class="empty-note px-1 pt-1" style="font-size:.8rem;">' + esc(L.shared_note) + '</p>';

        groups.forEach(function (group) {
            var rows = '';

            group.grants.forEach(function (grant) {
                var rightsLabel = (grant.rights === 2) ? L.edit_access : L.view_access;
                var rightsClass = (grant.rights === 2) ? 'text-bg-primary' : 'text-bg-secondary';
                var expiry = '';

                if (grant.expiration_date) {
                    expiry = '<span class="badge ' + (grant.expired ? 'text-bg-danger' : 'text-bg-warning') + ' ms-2">' +
                        '<span class="bi bi-hourglass-split me-1"></span>' + esc(grant.expiration_date) + '</span>';
                }

                // The contact is the person behind the account; without one
                // the row still has to name somebody, so the username stands
                // on its own rather than leaving an empty column.
                var contact = grant.contact_name
                    ? ('<a class="link-secondary text-decoration-none" href="edit_contact.php?id=' + grant.contact_id + '">' +
                        '<span class="bi bi-person-lines-fill me-1"></span>' + esc(grant.contact_name) + '</a>')
                    : '<span class="empty-note">' + esc(L.no_contact) + '</span>';

                rows += '<tr>' +
                    '<td class="py-1"><a class="link-body-emphasis text-decoration-none" href="edit_user.php?id=' + grant.user_id + '">' +
                    '<span class="bi bi-person-badge me-1"></span>' + esc(grant.username) + '</a></td>' +
                    '<td class="py-1">' + contact + '</td>' +
                    '<td class="py-1"><span class="badge ' + rightsClass + '">' + esc(rightsLabel) + '</span>' + expiry + '</td>' +
                    '</tr>';
            });

            html += '<div class="shared-group">' +
                '<div class="shared-group-head">' +
                '<span class="bi bi-folder-fill me-2 ' + group.access_control_type + '"></span>' +
                '<button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold shared-open" data-folder-id="' + group.id + '">' + esc(group.name) + '</button>' +
                '<span class="badge ms-2 ' + group.access_control_type + '" style="background:var(--bs-secondary-bg);">' +
                '<span class="bi ' + group.access_icon + ' me-1"></span>' + esc(accessLabel(group.access_control_type)) + '</span>' +
                (group.archived ? '<span class="badge text-bg-secondary ms-2"><span class="bi bi-archive me-1"></span>' + esc(L.archived) + '</span>' : '') +
                '<span class="badge text-bg-light ms-2">' + esc(fmt(L.people_count, [group.grants.length])) + '</span>' +
                (group.can_edit ? '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto shared-access" data-folder-id="' + group.id + '"><span class="bi bi-shield-lock me-1"></span>' + esc(L.access_permissions) + '</button>' : '') +
                '</div>' +
                '<div class="shared-group-path empty-note">' + esc(group.path) + '</div>' +
                '<table class="table table-sm mb-0"><thead><tr>' +
                '<th>' + esc(L.user_column) + '</th>' +
                '<th>' + esc(L.contact_column) + '</th>' +
                '<th>' + esc(L.rights_column) + '</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>' +
                '</div>';
        });

        html += '</div><div id="explorer_upload_overlay"></div>';
        container.innerHTML = html;

        container.querySelectorAll('.shared-open').forEach(function (button) {
            button.addEventListener('click', function () {
                exitAllFiles(parseInt(button.getAttribute('data-folder-id'), 10));
            });
        });

        container.querySelectorAll('.shared-access').forEach(function (button) {
            button.addEventListener('click', function () {
                openAccessPanel({ id: parseInt(button.getAttribute('data-folder-id'), 10) });
            });
        });
    }

    function reload(done) {
        load(insideCatalog() ? state.groupId : state.folderId, done);
    }

    // Expansion follows navigation: exactly the folders on the current path
    // stay open, so stepping back up folds what was opened on the way down.
    function syncTreeToPath() {
        if (insideCatalog()) {
            treeExpanded = {};
            (state.catalogCrumbs || []).forEach(function (crumb) { treeExpanded[crumb.id] = true; });
            saveTreeExpanded();
            loadTreeChildren(treeRootList, 0, highlightTree);
            return;
        }

        if (state.mode !== 'all') {
            treeExpanded = {};
            state.breadcrumb.forEach(function (crumb) { treeExpanded[crumb.id] = true; });
            saveTreeExpanded();
        }

        loadTreeChildren(treeRootList, 0, highlightTree);
    }

    function canWriteCurrent() {
        return !!(state.current && state.current.id > 0 && state.current.can_edit && !insideBin() && !insideShared() && !insideBackups());
    }

    // Where a paste can land.
    //
    // Among files that is a folder the operator may write to. The store has no
    // folder row to carry a permission: standing inside a group is what makes
    // it a target, and neither the flat product list nor the bin is one.
    function canPasteHere() {

        if (insideCatalog()) { return ((state.groupId > 0) && (state.allFilter === '')); }

        return canWriteCurrent();
    }

    // What the "New" menu can offer where we are standing.
    //
    // Inside a folder it is the whole menu, as long as the folder can be written to. Files,
    // Images and Pages are views across every folder rather than folders of their own, so
    // there they offer what fits what is listed and nothing else: an upload wherever files or
    // images are shown, a blank file only among files (a text file is not an image), a page
    // only among pages. Uploading and adding a page both open a screen of their own that asks
    // for the folder, and a file created from these views goes to the top folder, which is
    // the one every page and folder can reach.
    //
    // Shared, Backups and the bin take nothing: nothing in this menu belongs in any of them.
    function newActions() {

        // A short link belongs to no folder, so it is not one of the four
        // below and is offered wherever making one makes sense: among the
        // short links themselves, and among the pages -- which is where
        // somebody is standing when they think "this page needs a short
        // address". A plain user is not offered it, because the server
        // refuses the create for that role.
        var shortLink = (state.caps.role < 3) && (insideShortLinks() || ((state.mode === 'all') && (state.allFilter === 'pages')));

        // The backup folder creates none of the four, and two things nothing
        // else does: a backup, and a file put there by hand -- an archive
        // brought back from another host, or the .htaccess that keeps the
        // folder off the web. A backup is only ever taken at the top: one
        // inside another is not a backup of the site, and createBackup()
        // refuses it anyway.
        if (insideBackups()) {
            return {
                folder: false, file: false, page: false, short_link: false, image: false,
                upload: true,
                backup: ((state.backupPath || '') === '')
            };
        }

        if (insideBin() || insideShared() || insideShortLinks()) {
            return { folder: false, upload: false, file: false, page: false, image: false, short_link: shortLink };
        }

        // In the store the two halves of the menu are a new group and a new
        // product. There is nothing to upload into a group and nothing to
        // create as a file, and the bin is a place to take things out of.
        //
        // A new group needs a parent, so it is offered only while standing in
        // one. A product does not: the screen that creates it asks which groups
        // it belongs to, so All Products and All Variant Sets keep offering it
        // -- those are exactly the screens an operator is on when they think
        // "another product".
        if (insideCatalog()) {

            var inGroup = ((state.groupId > 0) && (state.allFilter === ''));
            var flatList = ((state.allFilter === 'products') || (state.allFilter === 'variants'));

            return { folder: inGroup, upload: false, file: false, image: false, page: (inGroup || flatList) };
        }

        if (state.mode === 'all') {

            var listingImages = (state.allFilter === 'images');
            var listingPages  = (state.allFilter === 'pages');

            return {
                folder: false,
                upload: !listingPages,
                file:   (!listingPages && !listingImages),
                // A picture drawn here lands as a picture, so unlike a new
                // text file it is at home in the Pictures view as well: the
                // only list it would not show up in is the one of pages.
                image:  !listingPages,
                page:   (listingPages && !!state.caps.can_create_pages),
                short_link: shortLink
            };
        }

        var writable = canWriteCurrent();

        return {
            folder: writable,
            upload: writable,
            file:   writable,
            image:  writable,
            page:   (writable && !!state.caps.can_create_pages),
            short_link: shortLink
        };
    }

    // What the transfer button offers where you are standing.
    //
    // Importing a ZIP brings in pages, styles and files, so it belongs to the
    // folder side and to the role that owns the design: import_zip.php has
    // always asked for a designer at its own door, and an entry that leads to
    // "access denied" is worse than no entry.
    function transferActions() {

        var siteSide = (!insideCatalog() && !insideBackups() && !insideShortLinks() && !insideShared() && !insideBin());

        // The catalog pair asks for nothing beyond standing in the store: the
        // screen itself is behind validate_ecommerce_access(), and so are
        // import_products.php and export_products.php, so anybody who can see
        // this menu here can already use both. The bin is left out -- what is
        // in it is on its way out and is not a catalog to export.
        var catalogSide = (insideCatalog() && !insideCatalogBin());

        return {
            import_zip: (siteSide && !!state.caps.is_designer),
            import_products: catalogSide,
            export_products: catalogSide
        };
    }

    function updateTransferMenu() {

        var actions = transferActions();
        var any = actions.import_zip || actions.import_products || actions.export_products;

        document.getElementById('import_zip_item').classList.toggle('d-none', !actions.import_zip);
        // Same places the plain ZIP import is offered.
        document.getElementById('import_design_zip_item').classList.toggle('d-none', !actions.import_zip);
        document.getElementById('import_products_item').classList.toggle('d-none', !actions.import_products);
        document.getElementById('export_products_divider').classList.toggle('d-none', !actions.export_products);
        document.getElementById('export_products_csv_item').classList.toggle('d-none', !actions.export_products);
        document.getElementById('export_products_xlsx_item').classList.toggle('d-none', !actions.export_products);

        var wrap = document.getElementById('explorer_transfer_wrap');
        var button = document.getElementById('transfer_menu_button');

        // Nothing on offer here, so the button stands down rather than opening
        // an empty menu.
        wrap.classList.toggle('d-none', !any);
        button.disabled = !any;
    }

    // Asking export_products.php for a file.
    //
    // A form post rather than a link: a selection of several thousand products
    // does not fit in a query string, and a download still carries the token.
    // The response is a file, so the page it is aimed at never paints -- the
    // browser hands the file over and the explorer stays where it was.
    function submitProductExport(format, ids, groupIds) {

        var form = document.getElementById('export_products_form');

        form.querySelector('input[name="format"]').value = (format === 'xlsx') ? 'xlsx' : 'csv';
        form.querySelector('input[name="scope"]').value = ((ids && ids.length) || (groupIds && groupIds.length)) ? 'selected' : 'all';
        form.querySelector('input[name="ids"]').value = (ids || []).join(',');
        form.querySelector('input[name="group_ids"]').value = (groupIds || []).join(',');

        form.submit();
    }

    // The rows a right-click export acts on, split the way the endpoint reads
    // them: products by id, groups by id and expanded to their whole branch
    // there rather than here.
    function productExportTargets(item) {

        var ids = [];
        var groupIds = [];

        catalogTargets(item).forEach(function (entry) {

            if (!entry) { return; }

            if (entry.kind === 'product') { ids.push(entry.id); }
            else if (entry.kind === 'group') { groupIds.push(entry.id); }
        });

        return { ids: ids, groups: groupIds };
    }

    // Where a blank file goes. In a folder, that folder; in a view across folders, the top one.
    function createFileFolderId() {
        return (state.mode === 'all') ? (state.caps.root_folder_id || 0) : state.folderId;
    }

    function renderRecycleChrome() {
        var binButton = document.getElementById('recycle_bin_button');
        var binCount = document.getElementById('recycle_bin_count');
        var banner = document.getElementById('recycle_banner');

        // Every area uses the same button in the same corner. It is the bin of
        // this screen either way; only what it holds differs.
        var inBin = insideCatalog() ? insideCatalogBin() : insideBin();

        var showButton = insideCatalog()
            ? (state.recycle.available && state.caps.role < 3)
            : (state.recycle.available && state.recycle.folder_id > 0 && state.caps.role < 3);

        binButton.classList.toggle('d-none', !showButton);
        binButton.classList.toggle('active', inBin);

        if (state.recycle.count > 0) {
            binCount.textContent = state.recycle.count;
            binCount.classList.remove('d-none');
        } else {
            binCount.classList.add('d-none');
        }

        banner.classList.toggle('d-none', !inBin);
        banner.classList.toggle('d-flex', inBin);

        if (inBin) {
            // Both bins empty themselves after the same number of days, so
            // both say the same sentence.
            document.getElementById('recycle_banner_text').textContent = fmt(L.bin_banner, [state.recycle.retention_days]);
        }
    }

    function updateCreateLinks() {
        var sendTo = currentSendTo();

        updateTransferMenu();

        // The importer returns here rather than to the page list when the file
        // manager is the screen that sent it.
        document.getElementById('import_zip_send_to').value = sendTo;
        document.getElementById('import_design_zip_send_to').value = sendTo;
        document.getElementById('new_page_button').href = (insideCatalog() ? 'add_product.php?send_to=' : 'add_page.php?send_to=') + encodeURIComponent(sendTo);
        // New Page belongs with the other three: everything under "New" needs a folder to
        // create into, and Files / Images / Pages, Shared and Backups are views across
        // folders rather than a folder of their own.  Left out of this list it stayed the one
        // live item in a menu where nothing else could be clicked, and in Backups it offered
        // to make a page in a place that holds no pages at all.
        var actions = newActions();

        // The store has no files in it, so those two entries are taken out of
        // the menu rather than shown greyed for ever. New Page is the reverse:
        // it starts hidden because a role may not create pages, and in the
        // store it is the New Product entry, which every catalog role has.
        var catalogArea = insideCatalog();
        var backupArea = insideBackups();

        document.getElementById('upload_file_item').classList.toggle('d-none', catalogArea);
        document.getElementById('create_file_item').classList.toggle('d-none', catalogArea || backupArea);

        // Taking a backup is offered only where backups live.
        document.getElementById('create_backup_item').classList.toggle('d-none', !backupArea);

        // Among the short links the menu holds one entry, and it is not one of
        // the four above: nothing here lives in a folder. Among the pages the
        // entry joins them rather than replacing them.
        var shortLinkArea = insideShortLinks();

        document.getElementById('new_short_link_item').classList.toggle('d-none', !actions.short_link);
        // Nothing here lives in a folder among the short links, and a folder
        // made in the backup directory would be a folder the site knows
        // nothing about.
        document.getElementById('new_folder_button').parentNode.classList.toggle('d-none', shortLinkArea || backupArea);

        if (shortLinkArea) {
            document.getElementById('new_page_item').classList.add('d-none');
            document.getElementById('upload_file_item').classList.add('d-none');
            document.getElementById('create_file_item').classList.add('d-none');
        }

        if (catalogArea) { document.getElementById('new_page_item').classList.remove('d-none'); }
        if (backupArea) { document.getElementById('new_page_item').classList.add('d-none'); }

        // The same two buttons, named for what they make here.
        var newFolderLabel = document.querySelector('#new_folder_button');
        var newPageLabel = document.querySelector('#new_page_button');

        if (newFolderLabel) {
            newFolderLabel.lastChild.textContent = insideCatalog() ? L.new_product_group : L.new_folder;
        }

        if (newPageLabel) {
            newPageLabel.lastChild.textContent = insideCatalog() ? L.new_product : L.new_page;
        }

        document.getElementById('new_folder_button').classList.toggle('disabled', !actions.folder);
        document.getElementById('create_file_button').classList.toggle('disabled', !actions.file);
        document.getElementById('new_image_button').classList.toggle('disabled', !actions.image);

        // Upload opens a window on this screen, so it is a real button and takes a real
        // disabled attribute -- which the keyboard honours by itself.
        document.getElementById('upload_file_button').disabled = !actions.upload;

        // New Page is still a link, and the "disabled" class only stops the mouse: it works
        // by switching pointer events off, which the keyboard never goes through.  A greyed
        // out link would still open if it were tabbed to and entered, so it is taken out of
        // the tab order as well.
        var newPageLink = document.getElementById('new_page_button');

        newPageLink.classList.toggle('disabled', !actions.page);
        newPageLink.setAttribute('aria-disabled', actions.page ? 'false' : 'true');

        if (actions.page) { newPageLink.removeAttribute('tabindex'); } else { newPageLink.setAttribute('tabindex', '-1'); }

        // With nothing on offer the button itself goes quiet rather than opening a menu of
        // four greyed out lines. Disabling the button is also what stops the dropdown: that
        // is Bootstrap's own rule for a disabled toggle.
        document.getElementById('create_backup_button').classList.toggle('disabled', !actions.backup);

        var anyAction = actions.folder || actions.upload || actions.file || actions.image || actions.page || actions.short_link || actions.backup;
        var newButton = document.getElementById('new_menu_button');

        newButton.disabled = !anyAction;
        newButton.classList.toggle('disabled', !anyAction);
    }

    function renderBreadcrumb() {
        if (state.mode === 'catalog') {

            var catalogCrumbs = '<li class="breadcrumb-item"><a data-catalog-id="0" class="link-secondary"><span class="bi bi-boxes me-1"></span>' + esc(L.catalog_root) + '</a></li>';

            if (state.allFilter === 'products') {

                catalogCrumbs += '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page">' + esc(L.all_products) + '</li>';

            } else if (state.allFilter === 'variants') {

                catalogCrumbs += '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page"><span class="bi bi-collection-fill me-1"></span>' + esc(L.all_variant_sets) + '</li>';

            } else if (state.allFilter === 'recycled') {

                catalogCrumbs += '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page"><span class="bi bi-trash me-1"></span>' + esc(L.recycle_bin) + '</li>';

            } else {

                (state.catalogCrumbs || []).forEach(function (crumb, index) {
                    var last = (index === (state.catalogCrumbs.length - 1));
                    catalogCrumbs += last
                        ? ('<li class="breadcrumb-item active text-body fw-semibold" aria-current="page">' + esc(crumb.name) + '</li>')
                        : ('<li class="breadcrumb-item"><a data-catalog-id="' + crumb.id + '" class="link-secondary">' + esc(crumb.name) + '</a></li>');
                });
            }

            var catalogBar = document.getElementById('explorer_breadcrumb');
            catalogBar.innerHTML = '<nav><ol class="breadcrumb">' + catalogCrumbs + '</ol></nav>';

            catalogBar.querySelectorAll('[data-catalog-id]').forEach(function (element) {
                element.addEventListener('click', function () {
                    state.allFilter = '';
                    load(parseInt(element.getAttribute('data-catalog-id'), 10) || 0, highlightTree);
                });
            });

            return;
        }

        if (state.mode === 'backups') {
            var backupCrumbs = '<li class="breadcrumb-item"><a data-folder-id="0" class="link-secondary"><span class="bi bi-hdd me-1"></span>' + esc(L.root) + '</a></li>' +
                '<li class="breadcrumb-item"><button type="button" class="btn btn-link p-0 link-secondary text-decoration-none backup-crumb" data-path=""><span class="bi bi-database me-1"></span>' + esc(L.backups) + '</button></li>';

            (state.backupCrumbs || []).forEach(function (crumb, index) {
                var last = (index === (state.backupCrumbs.length - 1));
                backupCrumbs += last
                    ? ('<li class="breadcrumb-item active text-body fw-semibold" aria-current="page">' + esc(crumb.name) + '</li>')
                    : ('<li class="breadcrumb-item"><button type="button" class="btn btn-link p-0 link-secondary text-decoration-none backup-crumb" data-path="' + esc(crumb.path) + '">' + esc(crumb.name) + '</button></li>');
            });

            var bar = document.getElementById('explorer_breadcrumb');
            bar.innerHTML = '<nav><ol class="breadcrumb">' + backupCrumbs + '</ol></nav>';

            bar.querySelectorAll('.backup-crumb').forEach(function (button) {
                button.addEventListener('click', function () { enterBackups(button.getAttribute('data-path')); });
            });

            return;
        }

        if (state.mode === 'short_links') {
            document.getElementById('explorer_breadcrumb').innerHTML =
                '<nav><ol class="breadcrumb">' +
                '<li class="breadcrumb-item"><a data-folder-id="0" class="link-secondary"><span class="bi bi-hdd me-1"></span>' + esc(L.root) + '</a></li>' +
                '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page"><span class="bi bi-link-45deg me-1"></span>' + esc(L.short_links) + '</li>' +
                '</ol></nav>';
            return;
        }

        if (state.mode === 'shared') {
            document.getElementById('explorer_breadcrumb').innerHTML =
                '<nav><ol class="breadcrumb">' +
                '<li class="breadcrumb-item"><a data-folder-id="0" class="link-secondary"><span class="bi bi-hdd me-1"></span>' + esc(L.root) + '</a></li>' +
                '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page"><span class="bi bi-shield-lock me-1"></span>' + esc(L.shared_folders) + '</li>' +
                '</ol></nav>';
            return;
        }

        if (state.mode === 'all') {
            var allIcon = { images: 'bi-images', pages: 'bi-window-stack' }[state.allFilter] || 'bi-file-earmark-image';
            var allName = { images: L.pictures, pages: L.pages }[state.allFilter] || (state.fileScope ? fileScopeLabel(state.fileScope) : L.files);

            document.getElementById('explorer_breadcrumb').innerHTML =
                '<nav><ol class="breadcrumb">' +
                '<li class="breadcrumb-item"><a data-folder-id="0" class="link-secondary"><span class="bi bi-hdd me-1"></span>' + esc(L.root) + '</a></li>' +
                '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page"><span class="bi ' + allIcon + ' me-1"></span>' + esc(allName) + '</li>' +
                '</ol></nav>';
            return;
        }

        var crumbs = [{ id: 0, name: L.root, icon: 'bi-hdd' }];

        state.breadcrumb.forEach(function (crumb) {
            crumbs.push({ id: crumb.id, name: crumb.name });
        });

        // Deep paths collapse the middle into a "..." menu, the way current
        // desktop file managers keep the bar on one line.
        var collapsed = [];

        if (crumbs.length > 4) {
            collapsed = crumbs.splice(1, crumbs.length - 3);
        }

        var html = '<nav><ol class="breadcrumb">';

        crumbs.forEach(function (crumb, index) {
            var last = (index === crumbs.length - 1);
            var icon = crumb.icon ? '<span class="bi ' + crumb.icon + ' me-1"></span>' : '';

            if (last && crumb.id !== 0) {
                html += '<li class="breadcrumb-item active text-body fw-semibold" aria-current="page">' + icon + esc(crumb.name) + '</li>';
            } else {
                html += '<li class="breadcrumb-item"><a data-folder-id="' + crumb.id + '" class="link-secondary" title="' + esc(crumb.name) + '">' + icon + esc(crumb.name) + '</a></li>';
            }

            if ((index === 0) && (collapsed.length > 0)) {
                var menu = '';
                collapsed.forEach(function (middle) {
                    menu += '<li><a class="dropdown-item" data-folder-id="' + middle.id + '"><span class="bi bi-folder me-2"></span>' + esc(middle.name) + '</a></li>';
                });
                html += '<li class="breadcrumb-item dropdown"><a class="link-secondary" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" role="button">&hellip;</a><ul class="dropdown-menu shadow" style="border-radius:.75rem;">' + menu + '</ul></li>';
            }
        });

        html += '</ol></nav>';
        document.getElementById('explorer_breadcrumb').innerHTML = html;
    }

    function sortItems(list) {
        var key = state.sort.key;
        var dir = state.sort.dir === 'desc' ? -1 : 1;

        return list.slice().sort(function (a, b) {
            var va, vb;
            if (key === 'size') { va = a.size || 0; vb = b.size || 0; }
            else if (key === 'timestamp') { va = a.timestamp || 0; vb = b.timestamp || 0; }
            else if (key === 'type') { va = (a.type || '').toLowerCase(); vb = (b.type || '').toLowerCase(); }
            // Keys the table has and the menu does not: a heading can be
            // clicked for any column that holds something worth ordering by.
            else if (key === 'folder') { va = (a.folder_name || '').toLowerCase(); vb = (b.folder_name || '').toLowerCase(); }
            else if (key === 'access') { va = (a.access_control_type || '').toLowerCase(); vb = (b.access_control_type || '').toLowerCase(); }
            else if (key === 'enabled') { va = (a.enabled === false) ? 0 : 1; vb = (b.enabled === false) ? 0 : 1; }
            else if (key === 'quantity') { va = (a.kind === 'product') ? (a.quantity || 0) : ((a.counts && a.counts.products_deep) || 0); vb = (b.kind === 'product') ? (b.quantity || 0) : ((b.counts && b.counts.products_deep) || 0); }
            else if (key === 'impact') { va = ((a.impact === null || a.impact === undefined) ? -1 : a.impact); vb = ((b.impact === null || b.impact === undefined) ? -1 : b.impact); }
            else if (key === 'seo') { va = ((a.seo && a.seo.score) || -1); vb = ((b.seo && b.seo.score) || -1); }
            // A group has no price of its own, so it sorts by the cheapest
            // thing under it -- the same figure its tile already shows.
            else if (key === 'price') { va = (a.kind === 'group') ? (a.price_min || 0) : (a.price || 0); vb = (b.kind === 'group') ? (b.price_min || 0) : (b.price || 0); }
            else { va = (a.name || '').toLowerCase(); vb = (b.name || '').toLowerCase(); }
            if (va < vb) { return -1 * dir; }
            if (va > vb) { return 1 * dir; }
            return 0;
        });
    }

    // ── Narrowing by what things are ────────────────────────────────────
    //
    // Each entry is one test over a row that is already on screen. They are
    // combined with AND: every filter switched on narrows further, which is
    // the only reading that stays predictable once there are two of them.
    //
    // 'kinds' says what a filter is about, and one rule covers the rest: a row
    // of a kind the filter names is tested, a row of any other kind is hidden,
    // and a container -- a folder, a product group -- is kept whatever the
    // filter is about. The last part is not an exception for its own sake:
    // the pictures somebody is hunting for are usually one level down, and a
    // filter that swept the folders away would take the way to them with it.
    var FILTER_DEFS = [
        { key: 'images', area: 'files', group: 'files', kinds: ['file'], label: function () { return L.filter_images; },
            test: function (item) { return !!item.is_image; } },
        { key: 'unoptimized', area: 'files', group: 'files', kinds: ['file'], label: function () { return L.filter_unoptimized; },
            test: function (item) { return item.is_image && (!item.optimized); } },
        { key: 'large', area: 'files', group: 'files', kinds: ['file'], label: function () { return L.filter_large_files; },
            test: function (item) { return (item.size || 0) > 1048576; } },
        { key: 'no_description', area: 'files', group: 'files', kinds: ['file'], label: function () { return L.filter_no_description; },
            test: function (item) { return String(item.description || '').trim() === ''; } },
        { key: 'design', area: 'files', group: 'files', kinds: ['file'], label: function () { return L.filter_design; },
            test: function (item) { return !!item.design; } },
        { key: 'not_in_sitemap', area: 'files', group: 'pages', kinds: ['page'], label: function () { return L.filter_not_in_sitemap; },
            test: function (item) { return !item.sitemap; } },
        { key: 'low_seo', area: 'files', group: 'pages', kinds: ['page'], label: function () { return L.filter_low_seo; },
            test: function (item) { return (!!item.seo) && item.seo.scored && (item.seo.score < 50); } },
        { key: 'archived', area: 'files', group: 'any', kinds: ['file', 'page', 'folder'], label: function () { return L.filter_archived; },
            test: function (item) { return !!item.archived; } },

        // A group is out of stock when something under it is, which is how
        // the tile already reads -- so here the filter is about both kinds.
        { key: 'out_of_stock', area: 'catalog', group: 'any', kinds: ['product', 'group'], label: function () { return L.filter_out_of_stock; },
            test: function (item) { return (item.kind === 'product') ? outOfStock(item) : ((item.out_of_stock || 0) > 0); } },
        { key: 'unpublished', area: 'catalog', group: 'any', kinds: ['product', 'group'], label: function () { return L.filter_unpublished; },
            test: function (item) { return item.enabled === false; } },
        { key: 'low_seo_catalog', area: 'catalog', group: 'any', kinds: ['product', 'group'], label: function () { return L.filter_low_seo; },
            test: function (item) { return (!!item.seo) && item.seo.scored && (item.seo.score < 50); } },
        { key: 'no_image', area: 'catalog', group: 'products', kinds: ['product'], label: function () { return L.filter_no_image; },
            test: function (item) { return String(item.image_name || '') === ''; } },
        { key: 'no_price', area: 'catalog', group: 'products', kinds: ['product'], label: function () { return L.filter_no_price; },
            test: function (item) { return !((item.price || 0) > 0); } },
        { key: 'variant_sets', area: 'catalog', group: 'groups', kinds: ['group'], label: function () { return L.filter_variant_sets; },
            test: function (item) { return isVariantSet(item); } }
    ];

    function filterKeeps(def, item) {

        if (def.kinds.indexOf(item.kind) !== -1) { return def.test(item); }

        return ((item.kind === 'folder') || (item.kind === 'group'));
    }

    // The backup browser lists files off the disk and the shared report lists
    // rights; neither carries the fields these tests read.
    function filtersAvailable() {
        return (!insideBackups()) && (!insideShared());
    }

    function activeFilters() {

        if (!filtersAvailable()) { return []; }

        var area = insideCatalog() ? 'catalog' : 'files';

        return FILTER_DEFS.filter(function (def) { return (def.area === area) && !!state.filters[def.key]; });
    }

    function toggleFilter(key) {

        if (state.filters[key]) { delete state.filters[key]; } else { state.filters[key] = true; }

        renderFilterChrome();
        renderContent();
        applySelectionClasses();
        renderStatusbar();
    }

    function clearFilters() {

        state.filters = {};

        renderFilterChrome();
        renderContent();
        applySelectionClasses();
        renderStatusbar();
    }

    function renderFilterChrome() {

        var wrap = document.getElementById('explorer_filter_wrap');
        var badge = document.getElementById('explorer_filter_count');
        var bar = document.getElementById('explorer_filters_bar');
        var active = activeFilters();

        wrap.classList.toggle('d-none', !filtersAvailable());
        badge.classList.toggle('d-none', active.length === 0);
        badge.textContent = String(active.length);

        // The menu is rebuilt rather than ticked in place: which filters exist
        // depends on the area, and the areas share one shell.
        var area = insideCatalog() ? 'catalog' : 'files';
        // The tests that apply to anything come first and unlabelled; a group
        // header would be naming a kind they are not about, and put last they
        // read as belonging to whichever heading they happen to sit under.
        var groups = insideCatalog()
            ? [['any', ''], ['products', L.products_word], ['groups', L.product_groups_word]]
            : [['any', ''], ['files', L.files], ['pages', L.pages]];

        var menu = '';

        groups.forEach(function (group) {

            var defs = FILTER_DEFS.filter(function (def) { return (def.area === area) && (def.group === group[0]); });

            if (defs.length === 0) { return; }

            if (group[1] !== '') { menu += '<li><h6 class="dropdown-header">' + esc(group[1]) + '</h6></li>'; }

            defs.forEach(function (def) {
                menu += '<li><button type="button" class="dropdown-item" data-filter="' + esc(def.key) + '">' +
                    '<i class="bi bi-check2 me-2' + (state.filters[def.key] ? '' : ' opacity-0') + '"></i>' + esc(def.label()) + '</button></li>';
            });
        });

        if (active.length > 0) {
            menu += '<li><hr class="dropdown-divider" /></li>' +
                '<li><button type="button" class="dropdown-item" data-filter-clear="1"><i class="bi bi-x-lg me-2"></i>' + esc(L.clear_filters) + '</button></li>';
        }

        document.getElementById('explorer_filter_menu').innerHTML = menu;

        if (active.length === 0) {
            bar.classList.add('d-none');
            bar.classList.remove('d-flex');
            bar.innerHTML = '';
            return;
        }

        var total = state.items.folders.length + state.items.pages.length + state.items.files.length;

        var chips = '<span class="text-body-secondary"><i class="bi bi-sliders me-1"></i>' +
            esc(fmt(L.filters_on, [active.length])) + ' · ' + esc(fmt(L.filters_showing, [state.ordered.length, total])) + '</span>';

        active.forEach(function (def) {
            chips += '<button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 no-popover" data-filter="' + esc(def.key) + '">' +
                esc(def.label()) + '<i class="bi bi-x ms-1"></i></button>';
        });

        chips += '<button type="button" class="btn btn-sm btn-ghost no-popover ms-auto" data-filter-clear="1">' + esc(L.clear_filters) + '</button>';

        bar.innerHTML = chips;
        bar.classList.remove('d-none');
        bar.classList.add('d-flex');
    }

    function visibleItems() {
        var filter = state.filter.toLowerCase();
        var narrowing = activeFilters();

        function match(item) {

            for (var index = 0; index < narrowing.length; index++) {
                if (!filterKeeps(narrowing[index], item)) { return false; }
            }

            if (!filter) { return true; }
            return (item.name || '').toLowerCase().indexOf(filter) !== -1
                || (item.description || '').toLowerCase().indexOf(filter) !== -1
                || (item.short_description || '').toLowerCase().indexOf(filter) !== -1;
        }

        return sortItems(state.items.folders.filter(match))
            .concat(sortItems(state.items.pages.filter(match)))
            .concat(sortItems(state.items.files.filter(match)));
    }

    // Whether a row in the table may carry a product picture.
    //
    // A tile in the grid is a picture by nature -- that is what a grid is for.
    // A table row is not, and on a shop with ten thousand products every row
    // carrying an <img> is ten thousand requests and ten thousand decodes for
    // a thumbnail forty pixels wide.
    //
    // "Show Product Images in Tables" in the eCommerce settings, the same
    // switch view_products.php has always read. It says products, so it is
    // asked about products: a file or a page thumbnail in the file manager is
    // not what it was written for and is not covered by it.
    function productImagesOn() {
        return (state.caps.show_product_images !== false);
    }

    // After an edit that kept the file name, the picture on disk has changed
    // and its address has not -- so a reload draws the copy the browser is
    // already holding. A stamp per file id is what makes the tile and the
    // preview ask for it again; it lives here rather than on the item because
    // the item is rebuilt from the server on every listing.
    var imageVersions = {};

    function imageUrl(item) {

        if ((item.kind !== 'file') || (!item.id)) { return item.url; }

        // The stamp above only knows about edits made on this screen, in this
        // visit. Everything else -- an edit made yesterday, in another tab, or
        // before the page was last reloaded -- has to come from the file's own
        // modified time, which the listing carries.
        //
        // Without it the browser never asks: get_file.php sends
        // "Cache-Control: public, max-age=604800", so for a week the address
        // is answered from the copy the browser is holding. The tile then
        // shows the picture as it was, and -- worse -- the image editor, which
        // opens the same address, loads those same old bytes and writes them
        // back over the new ones, so the operator's work disappears one edit
        // at a time.
        var stamp = imageVersions[item.id] || item.timestamp;

        if (!stamp) { return item.url; }

        return item.url + ((item.url.indexOf('?') === -1) ? '?' : '&') + 'v=' + stamp;
    }

    // A thumbnail that loads on demand rather than the moment it is drawn.
    //
    // The address goes in data-src, not src, so the browser does not fetch it
    // until armThumbLoader() hands it over -- a few at a time, and only as the
    // tile nears the viewport. A folder of three hundred pictures is otherwise
    // three hundred requests at once, each one a database-backed get_file.php
    // call holding a connection for its whole transfer, which is what took the
    // storefront's connection pool down on a busy site.
    function lazyThumb(src, cls) {
        return '<img draggable="false" class="item-thumb pg-lazy' + (cls ? (' ' + cls) : '') +
            '" data-src="' + esc(src) + '" alt="" />';
    }

    // How many thumbnail requests may be in the air at once. The cap, not the
    // folder size, is what the server sees: a folder of one picture and a
    // folder of a thousand both ask for at most this many at a time, and a
    // second look is served by the browser's own cache for nothing.
    var THUMB_MAX_INFLIGHT = 6;
    var thumbQueue = [];
    var thumbInFlight = 0;
    var thumbObserver = null;

    function thumbPump() {
        while ((thumbInFlight < THUMB_MAX_INFLIGHT) && (thumbQueue.length > 0)) {
            var img = thumbQueue.shift();

            // A tile the render replaced while it waited is skipped rather than
            // fetched into nothing.
            if (!img || !img.isConnected || !img.getAttribute('data-src')) { continue; }

            thumbInFlight++;
            thumbStart(img);
        }
    }

    function thumbStart(img) {
        var src = img.getAttribute('data-src');
        img.removeAttribute('data-src');

        function done() {
            thumbInFlight--;
            thumbPump();
        }

        img.addEventListener('load', function () {
            img.classList.remove('pg-lazy');
            img.classList.add('is-loaded', 'thumb-appear');
            done();
        }, { once: true });

        img.addEventListener('error', function () {
            thumbBroken(img);
            done();
        }, { once: true });

        img.src = src;
    }

    // A picture that will not load becomes the file glyph in the same box, so a
    // screen of missing files is never a screen of the browser's broken-image
    // icons. The wrapper keeps its access badge.
    function thumbBroken(img) {
        var glyph = document.createElement('span');
        glyph.className = 'item-thumb-fallback bi bi-file-earmark-image';

        if (img.parentNode) { img.parentNode.insertBefore(glyph, img); }
        img.remove();
    }

    function ensureThumbObserver() {
        if (thumbObserver || (typeof IntersectionObserver === 'undefined')) { return; }

        var root = document.getElementById('explorer_content');

        // rootMargin starts a tile loading before it is quite on screen, so a
        // steady scroll meets pictures already there rather than a column of
        // shimmer that fills in behind the scrollbar.
        thumbObserver = new IntersectionObserver(function (entries) {

            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }

                thumbObserver.unobserve(entry.target);

                if (entry.target.getAttribute('data-src')) { thumbQueue.push(entry.target); }
            });

            thumbPump();

        }, { root: root || null, rootMargin: '320px 0px', threshold: 0.01 });
    }

    // Run after every render: hand each fresh tile to the observer. Where
    // IntersectionObserver is missing, every tile still goes through the same
    // in-flight cap, so the burst is bounded even without the visibility part.
    function armThumbLoader() {
        var pending = document.querySelectorAll('#explorer_content img.pg-lazy[data-src]');

        if (typeof IntersectionObserver === 'undefined') {
            Array.prototype.forEach.call(pending, function (img) { thumbQueue.push(img); });
            thumbPump();
            return;
        }

        ensureThumbObserver();
        Array.prototype.forEach.call(pending, function (img) { thumbObserver.observe(img); });
    }

    function itemIconHtml(item, big) {
        var sizeClass = big ? 'item-icon' : 'list-icon';

        if (item.short_link) { return shortLinkIconHtml(item, big); }

        var catalogRow = ((item.kind === 'group') || (item.kind === 'product'));
        var withPicture = (big || !catalogRow || productImagesOn());

        // Catalog rows carry no access control type, so they are drawn before
        // the badge below is built out of one.
        if (item.kind === 'group') {

            var groupIcon = isVariantSet(item) ? 'bi-collection-fill' : 'bi-boxes';

            // A category that has a picture should show it: that picture is what
            // the visitor sees on the site, and which groups have one and which
            // do not is otherwise invisible from here. The small glyph in the
            // corner keeps it readable as a group rather than a product.
            if (item.image_name && withPicture) {
                return '<span class="thumb-wrap">' +
                    lazyThumb(path + item.image_name) +
                    '<span class="access-badge bi ' + groupIcon + ' text-primary"></span></span>';
            }

            return '<span class="' + sizeClass + ' bi ' + groupIcon + ' text-primary"></span>';
        }

        if (item.kind === 'product') {

            // With a picture on it a product tile looked like any other picture
            // in the grid. It carries the same corner glyph a group does, so
            // the two are told apart at a glance.
            if (item.image_name && withPicture) {
                return '<span class="thumb-wrap">' +
                    lazyThumb(path + item.image_name) +
                    '<span class="access-badge bi bi-box2-heart text-body-secondary"></span></span>';
            }

            return '<span class="' + sizeClass + ' bi bi-box2-heart text-body-secondary"></span>';
        }

        var badge = '<span class="access-badge bi ' + item.access_icon + ' ' + item.access_control_type + '"></span>';

        if (item.kind === 'folder') {
            return '<span class="' + sizeClass + ' bi bi-folder-fill ' + item.access_control_type + '">' + badge + '</span>';
        }

        if (item.kind === 'page') {
            var icon = item.home ? 'bi-house-door-fill' : 'bi-window-fullscreen';
            return '<span class="' + sizeClass + ' bi ' + icon + ' ' + item.access_control_type + '">' + badge + '</span>';
        }

        if (item.is_image && withPicture) {
            // draggable="false" keeps the browser from starting its own image
            // drag (whose URL payload would navigate the tab on a missed
            // drop) — the tile itself is the draggable thing.
            return '<span class="thumb-wrap ' + item.access_control_type + '">' +
                lazyThumb(imageUrl(item)) + badge + '</span>';
        }

        var typeIcon = FILETYPE_ICONS[item.type] || 'bi-file-earmark';
        return '<span class="' + sizeClass + ' bi ' + typeIcon + ' ' + item.access_control_type + '">' + badge + '</span>';
    }

    // Score ring for pages — the same drawing the editor toolbar uses
    // (pg_seo_page_toolbar): pathLength normalises the circumference to 100
    // so the dash array is the percentage itself. The band hex comes from
    // the server, so the ring here cannot drift from the other screens.
    function seoChipHtml(item, size) {
        // Groups and products are scored the same way pages are, and the ring is
        // the same drawing, so it is gated on the payload rather than the kind.
        if (!item.seo || !item.seo.scored) { return ''; }

        size = size || 26;

        var scored = item.seo.scored;
        var score = scored ? item.seo.score : 0;
        var hex = scored ? item.seo.hex : '#adb5bd';
        var title = scored ? (L.seo_score_label + ': ' + score + '/100') : L.seo_not_ready;

        if (scored && item.seo.labels && item.seo.labels.length > 0) {
            title += ' — ' + item.seo.labels.join(' · ');
        }

        return '<span class="seo-ring" title="' + esc(title) + '">' +
            '<svg width="' + size + '" height="' + size + '" viewBox="0 0 36 36" aria-hidden="true">' +
            '<circle class="seo-ring-track" cx="18" cy="18" r="15.5" fill="none" stroke-width="3.6"></circle>' +
            '<circle cx="18" cy="18" r="15.5" fill="none" stroke="' + esc(hex) + '" stroke-width="3.6" stroke-linecap="round"' +
            ' pathLength="100" stroke-dasharray="' + (scored ? score : 0) + ' 100" transform="rotate(-90 18 18)"></circle>' +
            '<text x="18" y="19.5" text-anchor="middle" dominant-baseline="middle" font-size="13.5" font-weight="700" fill="' + esc(hex) + '"' +
            ' font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif">' + (scored ? score : '–') + '</text>' +
            '</svg></span>';
    }

    // How long this row has left in the bin. Both areas draw it, so it lives
    // on its own rather than at the bottom of the flag list -- the store
    // returns from that list early, which is how the badge went missing from
    // the store's bin the first time.
    function binCountdownHtml(item) {

        if (!insideBin() && !insideCatalogBin()) { return ''; }

        var entry = state.binEntries[itemKey(item)];

        if (!entry) { return ''; }

        var title = fmt(L.days_left, [entry.days_left]) + (entry.origin_name ? ' \u2014 ' + entry.origin_name : '');

        return '<span class="badge text-bg-warning bin-badge" title="' + esc(title) + '"><span class="bi bi-hourglass-split"></span> ' + entry.days_left + '</span>';
    }

    function itemFlagsHtml(item) {
        var flags = [];

        if ((item.kind === 'group') || (item.kind === 'product')) {

            if (item.enabled === false) {
                flags.push('<span class="bi bi-slash-circle text-warning" title="' + esc(L.group_disabled) + '"></span>');
            }

            if (item.featured) {
                flags.push('<span class="bi bi-star-fill text-warning" title="' + esc(L.featured) + '"></span>');
            }

            // A product in more than one group is the difference between this
            // screen and a folder tree, so it is said on the tile rather than
            // left for the operator to discover when a "move" changes two places.
            if ((item.kind === 'product') && (item.group_count > 1)) {
                flags.push('<span class="catalog-chip" title="' + esc(L.member_of) + '"><span class="bi bi-diagram-3 me-1"></span>' + item.group_count + '</span>');
            }

            if ((item.kind === 'product') && outOfStock(item)) {
                flags.push('<span class="catalog-chip text-danger" title="' + esc(L.out_of_stock) + '">0</span>');
            }

            var countdown = binCountdownHtml(item);

            if (countdown !== '') { flags.push(countdown); }

            return flags.join(' ');
        }

        if (item.kind === 'page' && item.home) { flags.push('<span class="bi bi-house text-success" title="' + esc(L.home_page) + '"></span>'); }
        if (item.archived) { flags.push('<span class="bi bi-archive" title="' + esc(L.archived) + '"></span>'); }
        if (item.kind === 'file' && item.design) { flags.push('<span class="bi bi-palette2" title="' + esc(L.design_file) + '"></span>'); }
        if (item.kind === 'file' && item.optimized) { flags.push('<span class="bi bi-check2-circle text-success" title="' + esc(L.optimized) + '"></span>'); }
        if (item.style_name) { flags.push('<span class="bi bi-palette" title="' + esc(L.style + ': ' + item.style_name) + '"></span>'); }
        if (item.kind === 'file' && item.timestamp && (Date.now() / 1000 - item.timestamp) < 900) {
            flags.push('<span class="bi bi-clock-history" title="' + esc(L.new_file) + '"></span>');
        }

        var binCountdown = binCountdownHtml(item);

        if (binCountdown !== '') { flags.push(binCountdown); }

        return flags.join(' ');
    }

    var SHORT_LINK_ICONS = {
        page: 'bi-window-fullscreen',
        product_group: 'bi-boxes',
        product: 'bi-box2-heart',
        file: 'bi-file-earmark',
        url: ''
    };

    function shortLinkTypeLabel(type) {
        return ({
            page: L.page,
            product_group: L.product_group_word,
            product: L.product,
            url: L.url_word,
            file: L.file
        })[type] || String(type || '');
    }

    function shortLinkIconHtml(item, big) {

        var sizeClass = big ? 'item-icon' : 'list-icon';
        var target = SHORT_LINK_ICONS[item.destination_type];

        // An address off the site is not one of our things, so there is
        // nothing to draw on the far end of the arrow.
        if (!target) {
            return '<span class="' + sizeClass + ' short-link-icon"><span class="bi bi-link-45deg"></span></span>';
        }

        return '<span class="' + sizeClass + ' short-link-icon" title="' + esc(shortLinkTypeLabel(item.destination_type)) + '">' +
            '<span class="bi bi-link-45deg"></span>' +
            '<span class="bi bi-arrow-right-short sl-arrow"></span>' +
            '<span class="bi ' + target + ' sl-target"></span></span>';
    }

    function itemTypeLabel(item) {
        if (item.short_link) { return shortLinkTypeLabel(item.destination_type); }
        if (item.kind === 'group') { return isVariantSet(item) ? L.variant_set : L.category; }
        if (item.kind === 'product') { return L.product; }
        if (item.kind === 'folder') { return L.folder; }
        if (item.kind === 'page') { return L.page + (item.type && item.type !== 'standard' ? ' (' + item.type + ')' : ''); }
        return (item.type || '').toUpperCase();
    }

    function renderContent() {
        var container = document.getElementById('explorer_content');
        var cursorItem = state.ordered[state.cursor];

        state.ordered = visibleItems();

        // The listing is rebuilt from scratch on every draw -- a reload, a
        // filter, a change of sort order -- so the cursor is re-found by which
        // row it was on rather than by the number it had. A reload also builds
        // fresh objects, so the match is on the key rather than on identity.
        // Narrowing the list out from under the cursor drops it rather than
        // leaving it standing on a stranger.
        state.cursor = -1;

        if (cursorItem) {

            var cursorKey = itemKey(cursorItem);

            state.ordered.forEach(function (item, index) {
                if ((state.cursor === -1) && (itemKey(item) === cursorKey)) { state.cursor = index; }
            });
        }

        var html = '';

        if (state.ordered.length === 0) {

            // An empty bin is not an empty group and must not say it is: what
            // the operator needs to read there is what the bin is for.
            var emptyIcon = insideShortLinks() ? 'bi-link-45deg'
                : (insideCatalogBin() ? 'bi-trash3' : (insideCatalog() ? 'bi-boxes' : 'bi-folder2-open'));
            var emptyText = insideShortLinks() ? L.short_links_empty
                : (insideCatalogBin() ? L.bin_empty_note : (insideCatalog() ? L.catalog_empty : L.empty_folder));

            // "This folder is empty" is not true when a filter emptied it, and
            // sending somebody to look for files that are sitting right there
            // behind a switch they forgot is the whole hazard of having filters.
            if ((activeFilters().length > 0) && ((state.items.folders.length + state.items.pages.length + state.items.files.length) > 0)) {
                emptyIcon = 'bi-sliders';
                emptyText = L.filter_nothing_matches;
            }

            // The same for the search box: a folder with content and a word
            // nothing in it matches is not an empty folder, and saying so is
            // what sends the operator to the box that has to be cleared. The
            // filters keep their own sentence when both are in play.
            else if ((state.filter !== '') && ((state.items.folders.length + state.items.pages.length + state.items.files.length) > 0)) {
                emptyIcon = 'bi-search';
                emptyText = L.search_nothing_matches;
            }

            html = '<div class="text-center my-5 empty-note"><span class="bi ' + emptyIcon + ' display-4 d-block mb-2 ' +
                esc((state.current && !insideCatalog()) ? state.current.access_control_type : '') + '"></span>' +
                esc(emptyText) + '</div>';
        } else if (state.viewType === 'grid') {
            html = '<div id="explorer_grid">';
            state.ordered.forEach(function (item, index) {
                var classes = itemClasses(item);
                html += '<div class="explorer-item' + classes + (catalogSubName(item) !== '' ? ' has-subname' : '') + '" draggable="true" data-kind="' + item.kind + '" data-id="' + item.id + '" data-index="' + index + '">' +
                    '<div class="item-card" title="' + esc(item.name + (catalogSubName(item) !== '' ? ' — ' + catalogSubName(item) : '')) + '">' +
                    '<span class="sel-dot" data-role="sel"><span class="bi bi-check-lg"></span></span>' +
                    stockDotHtml(item) +
                    seoChipHtml(item) +
                    '<div class="item-thumb-area">' + itemIconHtml(item, true) + '</div>' +
                    '<div class="item-name" data-role="name">' + esc(item.name) + '</div>' +
                    (catalogSubName(item) !== '' ? ('<div class="item-subname">' + esc(catalogSubName(item)) + '</div>') : '') +
                    '<div class="item-flags">' + itemFlagsHtml(item) + '</div>' +
                    '</div></div>';
            });
            html += '</div>';
        } else {
            // Backups have no site columns to show, and the catalog has its own.
            var siteColumns = !insideBackups() && !insideCatalog();
            var catalogColumns = insideCatalog();

            var columns = listColumns();
            var sortCaret = (state.sort.dir === 'desc') ? 'bi-caret-down-fill' : 'bi-caret-up-fill';

            html = '<table id="explorer_list_table"><colgroup>';

            // Only the columns this viewport actually shows get a <col>. A
            // narrow screen hides the cells of the rest with d-none, which
            // takes them out of the table altogether -- but a <col> declares a
            // column whether any cell lands in it or not. Declaring all eleven
            // on a phone left the three visible cells sitting in the first
            // three slots, wearing the wrong widths, with the other eight
            // columns stretching off the side as empty white space.
            columns.forEach(function (column) {
                if (!listBreakpointMet(column.at)) { return; }
                html += '<col data-col-id="' + column.id + '" style="width:' + column.w + 'px;" />';
            });

            html += '</colgroup><thead><tr>';

            columns.forEach(function (column) {

                var cls = (column.cls ? (column.cls + ' ') : '') + (column.at ? ('pg-at-' + column.at) : '');
                var sorted = (column.key && (state.sort.key === column.key));

                html += '<th class="' + cls + (column.key ? ' th-sortable' : '') + '"' +
                    (column.key ? (' data-sort-key="' + column.key + '"') : '') + '>' +
                    '<span class="th-label" title="' + esc(column.label || '') + '">' + esc(column.label || '') + '</span>' +
                    (sorted ? ('<span class="bi ' + sortCaret + ' th-caret"></span>') : '') +
                    ((column.id !== 'icon') ? ('<span class="col-grip" data-col-id="' + column.id + '"></span>') : '') +
                    '</th>';
            });

            html += '</tr></thead><tbody>';

            state.ordered.forEach(function (item, index) {

                html += '<tr class="explorer-item' + itemClasses(item) + '" draggable="true" data-kind="' + item.kind + '" data-id="' + item.id + '" data-index="' + index + '">';

                columns.forEach(function (column) {

                    var cls = 'py-1 ' + (column.cls ? (column.cls + ' ') : '') + (column.at ? ('pg-at-' + column.at) : '');

                    html += '<td class="' + cls + '" data-col-id="' + column.id + '">' + column.cell(item) + '</td>';
                });

                html += '</tr>';
            });

            html += '</tbody></table>';
        }

        // A flat view spans every folder, so a drop there cannot promise "this
        // folder": the window that opens on it asks which one.
        html += '<div id="explorer_upload_overlay"><div class="text-center fw-bold text-primary"><span class="bi bi-cloud-arrow-up fs-1 d-block"></span>' + esc((state.mode === 'all') ? L.drop_to_pick_folder : L.drop_to_upload) + '</div></div>';
        container.innerHTML = html;

        // The tiles just drawn carry their address in data-src; hand them to
        // the loader so they fetch a few at a time as they come into view.
        armThumbLoader();

        var listTable = document.getElementById('explorer_list_table');

        if (listTable) {

            syncListMinWidth(listTable);
            attachColumnResize(listTable);

            listTable.querySelectorAll('th.th-sortable').forEach(function (heading) {

                heading.addEventListener('click', function (event) {

                    // The grip belongs to the edge, not to the heading: a drag
                    // that ends on the heading must not also sort it.
                    if (event.target.classList.contains('col-grip')) { return; }

                    sortByColumn(heading.getAttribute('data-sort-key'));
                });
            });
        }

        // Last, because the chip strip says how many rows came through
        // the filters and that number is only known once they have run.
        renderFilterChrome();
    }

    // ── The list view's columns ─────────────────────────────────────────
    //
    // One description per column, and the header, the widths and the cells are
    // all read off it. They used to be written out twice -- once as <th>, once
    // as <td> -- and the two drifted apart every time a column was added.
    //
    //   id     what the saved width is filed under
    //   key    the sort key, when this column can be sorted by
    //   at     the breakpoint below which the column is not drawn
    //   w      its width in pixels before anyone drags it
    //   cell   what goes in it
    //
    // The table is laid out fixed, from a <colgroup>: that is what makes a
    // dragged edge mean something, and what stops eleven columns from
    // squeezing a name down to one letter per line.

    var LIST_BREAKPOINTS = { md: 768, lg: 992, xl: 1200, xxl: 1400 };

    // Whether a column that asks for a minimum width gets one.
    //
    // Below the first breakpoint the answer is always yes: a phone keeps every
    // column and scrolls the table sideways instead of trimming it. There is
    // nowhere else for the detail to go at that width -- the tree and preview
    // panes are hidden under 992px too -- and a name that does not fit is
    // already cut with an ellipsis rather than breaking the row.
    //
    // Between the breakpoints the table still trims, because there the panes
    // are back and the window can simply be widened.
    function listBreakpointMet(at) {
        if (!at) { return true; }
        if (!window.matchMedia('(min-width: ' + LIST_BREAKPOINTS.md + 'px)').matches) { return true; }
        return window.matchMedia('(min-width: ' + LIST_BREAKPOINTS[at] + 'px)').matches;
    }

    // The <colgroup> is built for the width the page was rendered at, so a
    // viewport that crosses a breakpoint has to redraw the table: the columns
    // that just appeared would otherwise take their width from whichever <col>
    // happened to sit in their slot. Watching the queries rather than every
    // resize event means one redraw per crossing instead of one per pixel.
    function watchListBreakpoints() {

        Object.keys(LIST_BREAKPOINTS).forEach(function (name) {

            var query = window.matchMedia('(min-width: ' + LIST_BREAKPOINTS[name] + 'px)');

            var redraw = function () {
                // Only when a list is what is actually on screen -- the grid
                // needs no redraw, and the shared screen is not renderContent's
                // to replace.
                if (!document.getElementById('explorer_list_table')) { return; }
                renderContent();
                applySelectionClasses();
            };

            if (query.addEventListener) { query.addEventListener('change', redraw); }
            else if (query.addListener) { query.addListener(redraw); }
        });
    }

    function listColumnWidths() {
        try { return JSON.parse(storageGet('pg_explorer_columns') || '{}') || {}; } catch (e) { return {}; }
    }

    function saveListColumnWidth(id, width) {
        var widths = listColumnWidths();
        widths[id] = Math.round(width);
        storageSet('pg_explorer_columns', JSON.stringify(widths));
    }

    function listColumnsAll() {

        var catalogColumns = insideCatalog();
        var siteColumns = !insideBackups() && !insideCatalog() && !insideShortLinks();
        var shortLinkColumns = insideShortLinks();
        var columns = [];

        columns.push({ id: 'icon', w: 46, cls: 'text-center', cell: function (item) { return itemIconHtml(item, false); } });

        columns.push({
            id: 'name', key: 'name', label: L.name, w: 260,
            cell: function (item) {
                return stockDotHtml(item) +
                    '<span class="list-name" data-role="name" title="' + esc(item.name) + '">' + esc(item.name) + '</span>' +
                    catalogSubNameHtml(item);
            }
        });

        // The marks that say something about the state of the row: the score
        // ring, the design flag, the home flag, the bin countdown. They used to
        // ride inside the name cell and were the reason a name had no room --
        // and a mark nobody can line up with the mark on the row above is not
        // doing its job either.
        columns.push({
            id: 'flags', key: 'seo', label: L.flags, at: 'md', w: 92, cls: 'text-center text-nowrap',
            cell: function (item) { return seoChipHtml(item, 20) + ' <span class="item-flags">' + itemFlagsHtml(item) + '</span>'; }
        });

        columns.push({
            id: 'type', key: 'type', label: L.type, at: 'md', w: 120,
            cell: function (item) { return '<span class="list-badge">' + esc(itemTypeLabel(item)) + '</span>'; }
        });

        columns.push({
            id: 'size', key: 'size', label: (catalogColumns ? L.contents : (shortLinkColumns ? L.destination : L.size)), at: 'md', w: 140,
            cell: function (item) {

                if (item.short_link) { return '<span class="cell-clip" title="' + esc(item.destination || '') + '">' + esc(item.destination || '') + '</span>'; }
                if (item.kind === 'group') { return esc(fmt(L.catalog_counts, [item.counts.groups, item.counts.products_deep])); }
                if (item.kind === 'file') { return esc(item.size_label || ''); }
                if ((item.kind === 'folder') && (!item.backup)) { return esc(fmt(L.counts_label, [item.counts.folders, item.counts.pages, item.counts.files])); }

                return '';
            }
        });

        if (state.mode === 'all') {
            columns.push({
                id: 'folder', key: 'folder', label: L.folder, w: 150,
                cell: function (item) {
                    return '<span class="bi bi-folder me-1 ' + esc(item.access_control_type || '') + '"></span>' +
                        '<span class="cell-clip" title="' + esc(item.folder_name || '') + '">' + esc(item.folder_name || '') + '</span>';
                }
            });
        }

        if (catalogColumns) {

            columns.push({
                id: 'price', key: 'price', label: L.price, at: 'md', w: 120, cls: 'text-nowrap',
                cell: function (item) {
                    if (item.kind !== 'product') { return esc(priceRangeLabel(item)); }
                    return editableCellHtml(item, 'price', moneyLabel(item.price));
                }
            });

            columns.push({
                id: 'quantity', key: 'quantity', label: L.quantity, at: 'lg', w: 96,
                cell: function (item) {
                    if (item.kind !== 'product') { return esc(String(item.counts.products_deep)); }
                    if (!item.inventory) { return esc(L.not_tracked); }
                    return editableCellHtml(item, 'quantity', String(item.quantity));
                }
            });

            columns.push({
                id: 'status', key: 'enabled', label: L.status, at: 'md', w: 116,
                cell: function (item) {
                    return (item.enabled === false)
                        ? ('<span class="list-badge badge-off">' + esc(L.group_disabled) + '</span>')
                        : ('<span class="list-badge badge-on">' + esc(L.group_published) + '</span>');
                }
            });
        }

        if (siteColumns) {

            columns.push({ id: 'style_desktop', label: L.desktop_style_short, at: 'xl', w: 130, cls: 'small',
                cell: function (item) { return ((item.kind === 'folder') || (item.kind === 'page')) ? styleCellHtml(item.style_desktop) : ''; } });

            columns.push({ id: 'style_mobile', label: L.mobile_style_short, at: 'xxl', w: 130, cls: 'small',
                cell: function (item) { return ((item.kind === 'folder') || (item.kind === 'page')) ? styleCellHtml(item.style_mobile) : ''; } });

            columns.push({ id: 'impact', key: 'impact', label: L.impact, at: 'lg', w: 92, cls: 'text-center',
                cell: function (item) { return impactCellHtml(item); } });

            columns.push({ id: 'features', label: L.features, at: 'lg', w: 108, cls: 'text-center text-nowrap',
                cell: function (item) { return pageFeatureIcons(item); } });

            columns.push({ id: 'access', key: 'access', label: L.access_control, at: 'xl', w: 132,
                cell: function (item) { return '<span class="list-badge ' + item.access_control_type + '">' + esc(accessLabel(item.access_control_type)) + '</span>'; } });
        }

        columns.push({
            id: 'modified', key: 'timestamp', label: L.last_modified, at: 'lg', w: 170, cls: 'text-nowrap',
            cell: function (item) { return (item.modified || '') + (item.username ? ' ' + fmt(L.modified_by, [esc(item.username)]) : ''); }
        });

        var saved = listColumnWidths();

        columns.forEach(function (column) {
            if (saved[column.id] > 40) { column.w = saved[column.id]; }
        });

        return columns;
    }

    // ── Which columns are drawn ─────────────────────────────────────
    //
    // Eleven columns arrive by viewport width alone, and half of them are
    // about things a given operator never touches -- a shop has no use for
    // the two style columns, and somebody sorting files has none for the
    // SEO ring. The choice is theirs and it is kept per area: the store and
    // the file manager share a shell but not a set of columns.
    //
    // The icon and the name are not on the list. A row has to say which row
    // it is.
    function columnHiddenKey() {
        return 'pg_explorer_cols_hidden_' + state.area;
    }

    function columnsHidden() {

        try {
            var list = JSON.parse(storageGet(columnHiddenKey()) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function columnToggle(id) {

        var hidden = columnsHidden();
        var without = hidden.filter(function (each) { return each !== id; });

        if (without.length === hidden.length) { without.push(id); }

        storageSet(columnHiddenKey(), JSON.stringify(without));
        renderContent();
        applySelectionClasses();
    }

    function columnsReset() {
        storageSet(columnHiddenKey(), '[]');
        renderContent();
        applySelectionClasses();
    }

    function listColumns() {

        var hidden = columnsHidden();

        return listColumnsAll().filter(function (column) {
            return (column.id === 'icon') || (column.id === 'name') || (hidden.indexOf(column.id) === -1);
        });
    }

    // Only the columns this viewport could show are offered: a tick that
    // changes nothing because the screen is too narrow for that column is a
    // control that lies about what it does.
    function columnMenu() {

        var hidden = columnsHidden();
        var html = '';

        listColumnsAll().forEach(function (column) {

            if ((column.id === 'icon') || (column.id === 'name')) { return; }
            if (!listBreakpointMet(column.at)) { return; }

            html += menuCheckItem(column.label || column.id, 'col_' + column.id, hidden.indexOf(column.id) === -1);
        });

        if (hidden.length > 0) {
            html += menuDivider() + menuItem('bi-arrow-counterclockwise', L.columns_reset, 'columns_reset');
        }

        return html;
    }

    // Clicking a heading sorts by it, and clicking the same one again turns the
    // order around -- the way every list in every file manager has worked for
    // thirty years.
    //
    // It writes into the same state.sort the menu reads, so the two agree
    // wherever they overlap. Where they do not -- a table column the menu does
    // not offer, such as the folder or the access rule -- the menu simply shows
    // none of its entries ticked, which is true: the list is sorted by
    // something that is not on that menu.
    function sortByColumn(key) {

        if (!key) { return; }

        if (state.sort.key === key) {
            state.sort.dir = (state.sort.dir === 'asc') ? 'desc' : 'asc';
        } else {
            state.sort.key = key;
            state.sort.dir = 'asc';
        }

        renderContent();
        applySelectionClasses();
    }

    // Dragging the edge of a heading. The width is written onto the <col>, so
    // one number moves the whole column, and it is remembered per column for
    // the next visit.
    function attachColumnResize(table) {

        table.querySelectorAll('.col-grip').forEach(function (grip) {

            grip.addEventListener('mousedown', function (event) {

                event.preventDefault();
                event.stopPropagation();

                var id = grip.getAttribute('data-col-id');
                var col = table.querySelector('col[data-col-id="' + id + '"]');

                if (!col) { return; }

                var startX = event.clientX;
                var startWidth = parseInt(col.style.width, 10) || 100;

                function move(moveEvent) {
                    var width = Math.max(48, startWidth + (moveEvent.clientX - startX));
                    col.style.width = width + 'px';
                }

                function up() {
                    document.removeEventListener('mousemove', move);
                    document.removeEventListener('mouseup', up);
                    document.body.classList.remove('col-resizing');
                    saveListColumnWidth(id, parseInt(col.style.width, 10) || 100);
                    syncListMinWidth(table);
                }

                document.body.classList.add('col-resizing');
                document.addEventListener('mousemove', move);
                document.addEventListener('mouseup', up);
            });

            // A double click on the edge puts that column back to its default.
            grip.addEventListener('dblclick', function (event) {

                event.preventDefault();
                event.stopPropagation();

                var widths = listColumnWidths();
                delete widths[grip.getAttribute('data-col-id')];
                storageSet('pg_explorer_columns', JSON.stringify(widths));
                renderContent();
                applySelectionClasses();
            });
        });
    }

    // The width below which the table stops being readable: the sum of the
    // columns actually on screen at this size. Under it the content pane
    // scrolls sideways instead of crushing everything.
    function syncListMinWidth(table) {

        var total = 0;

        table.querySelectorAll('col[data-col-id]').forEach(function (col) {
            total += parseInt(col.style.width, 10) || 100;
        });

        table.style.minWidth = total + 'px';
    }

    function itemClasses(item) {
        var classes = '';
        if (state.selection[itemKey(item)]) { classes += ' selected'; }
        if (item.archived) { classes += ' archived'; }
        if (((item.kind === 'group') || (item.kind === 'product')) && (item.enabled === false)) { classes += ' catalog-off'; }
        if (item.kind === 'file' && item.design) { classes += ' design'; }
        if (state.clipboard && state.clipboard.mode === 'cut' && state.clipboard.keys[itemKey(item)]) { classes += ' cut-ghost'; }
        return classes;
    }

    // A long job runs file by file, so the status bar carries a small bar next to the disk figure
    // that says what is running and how far along it is.  Without it the screen looks idle while
    // twenty images are being rewritten.
    function progressHtml() {
        var progress = state.progress;

        if (!progress) { return ''; }

        var bar = '';

        if (progress.total > 0) {
            var percent = Math.max(4, Math.round((progress.done / progress.total) * 100));

            bar = '<div class="progress" style="width:110px;height:6px;"><div class="progress-bar" style="width:' + percent + '%"></div></div>' +
                  '<span class="font-monospace">' + progress.done + '/' + progress.total + '</span>';
        } else {
            bar = '<div class="progress" style="width:110px;height:6px;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>';
        }

        return '<span class="d-flex align-items-center gap-2 ms-auto text-body-secondary">' +
               '<span>' + esc(progress.label) + '</span>' + bar + '</span>';
    }

    // the disk figure gives up its own push when the progress strip is there, so the two sit side
    // by side at the right end instead of drifting apart
    function pushRightClass() {
        return state.progress ? '' : 'ms-auto';
    }

    function setProgress(label, done, total) {
        state.progress = { label: label, done: done, total: total };
        renderStatusbar();
    }

    function clearProgress() {
        state.progress = null;
        renderStatusbar();
    }

    // Leaving in the middle of a job.
    //
    // Every long job on this screen -- an upload, an optimise or webp run, a
    // backup, a compress, an extract -- draws the same progress bar, so the
    // one flag behind it is also the answer to "is anything in flight". The
    // work is a queue driven from this page: close the tab and the queue stops
    // where it is, with some files done and the rest not. Asked only while
    // that is true, so a finished job leaves without a word.
    //
    // The text is written out for the browsers that still show it; every
    // current one ignores it and shows its own wording, but returning a string
    // is what makes the prompt appear at all.
    window.addEventListener('beforeunload', function (event) {

        if (!state.progress) { return; }

        event.preventDefault();
        event.returnValue = L.leave_during_job;

        return L.leave_during_job;
    });

    // ── The selection strip ─────────────────────────────────────────────
    //
    // What is picked out, and the things to do with it, along the bottom of
    // the screen. Until now every one of these lived behind the right mouse
    // button alone: on a touch screen that is a long press onto a menu, and on
    // a desktop it is a menu somebody has to already know is there.
    //
    // Nothing here is a second implementation. Every button hands its name to
    // runMenuAction(), the same function the context menu calls, so the two
    // can never drift apart and a new action reaches both at once.

    function runSelectionAction(action) {

        var selected = selectedItems();
        var first = selected[0];

        // runMenuAction() reads its target off the menu element, which is how
        // a right click tells it which row was under the pointer. A strip
        // button has no pointer target, so the first picked row stands in --
        // the same row a right click would have made the selection.
        menuElement.setAttribute('data-target-source', 'content');
        menuElement.setAttribute('data-target-kind', first ? first.kind : '');
        menuElement.setAttribute('data-target-id', first ? first.id : '');

        runMenuAction(action);
    }

    // The buttons a selection is offered, in the order they are wanted.
    // Everything past the fourth goes into the overflow menu -- four is what
    // fits beside a count on a narrow screen, and a strip of eleven buttons is
    // a menu that forgot to close.
    function selectionActions(selected) {

        var single = (selected.length === 1);
        var first = selected[0];
        var actions = [];

        if (insideCatalogBin() || insideBin()) {
            actions.push({ icon: 'bi-arrow-counterclockwise', label: L.restore, action: insideCatalogBin() ? 'catalog_restore' : 'restore' });
            actions.push({ icon: 'bi-trash', label: L.delete_permanently, action: insideCatalogBin() ? 'catalog_purge' : 'delete_permanent' });
            return actions;
        }

        if (insideCatalog()) {

            var allProducts = selected.every(function (each) { return each.kind === 'product'; });
            var anyOff = selected.some(function (each) { return each.enabled === false; });

            actions.push({ icon: 'bi-pencil', label: L.edit, action: 'catalog_edit', off: !single });
            actions.push({ icon: 'bi-eye', label: L.quick_look, action: 'quick_look', off: !single || !quickLookable(first) });
            actions.push(anyOff
                ? { icon: 'bi-eye', label: L.publish_item, action: 'catalog_publish' }
                : { icon: 'bi-eye-slash', label: L.unpublish_item, action: 'catalog_unpublish' });
            actions.push({ icon: 'bi-folder-symlink', label: transferMenuLabel(), action: 'move_to' });
            actions.push({ icon: 'bi-trash', label: L.delete, action: 'catalog_recycle' });
            actions.push({ icon: 'bi-copy', label: L.duplicate, action: 'duplicate', off: !single || !itemDuplicable(first) });
            actions.push({ icon: 'bi-clipboard', label: L.copy_name, action: 'copy_name' });
            actions.push({ icon: 'bi-input-cursor', label: L.bulk_rename, action: 'bulk_rename', off: single || (bulkRenameTargets().length === 0) });
            actions.push({ icon: 'bi-ui-checks', label: L.bulk_edit_products, action: 'bulk_products', off: !allProducts });
            actions.push({ icon: 'bi-filetype-csv', label: L.export_csv, action: 'catalog_export_csv' });
            actions.push({ icon: 'bi-file-earmark-excel', label: L.export_excel, action: 'catalog_export_xlsx' });

            if (allProducts && (state.groupId > 0) && (state.allFilter === '')) {
                actions.push({ icon: 'bi-box-arrow-left', label: L.remove_from_group, action: 'catalog_membership_remove', off: !single });
            }

            return actions;
        }

        if (insideShortLinks()) {
            actions.push({ icon: 'bi-box-arrow-up-right', label: L.visit, action: 'short_link_visit', off: !single });
            actions.push({ icon: 'bi-pencil', label: L.edit, action: 'short_link_edit', off: !single });
            actions.push({ icon: 'bi-files', label: L.duplicate, action: 'short_link_duplicate' });
            actions.push({ icon: 'bi-trash', label: L.delete, action: 'short_link_delete' });
            return actions;
        }

        if (insideBackups()) {
            actions.push({ icon: 'bi-download', label: L.download, action: 'backup_download', off: !single || (first.kind === 'folder') });
            actions.push({ icon: 'bi-box-arrow-up-right', label: L.open_folder, action: 'backup_open', off: !single || (first.kind !== 'folder') });
            actions.push({ icon: 'bi-file-earmark-zip', label: L.compress_and_download, action: 'backup_zip', off: !single || (first.kind !== 'folder') || !state.backupZip });
            actions.push({ icon: 'bi-trash', label: L.delete, action: 'backup_delete' });
            actions.push({ icon: 'bi-key', label: L.permissions, action: 'backup_permissions', off: !single });
            actions.push({ icon: 'bi-input-cursor-text', label: L.rename, action: 'backup_rename', off: !single });
            actions.push({ icon: 'bi-copy', label: L.duplicate, action: 'backup_copy', off: !single });
            return actions;
        }

        var allPages = selected.every(function (each) { return each.kind === 'page'; });
        var allFiles = selected.every(function (each) { return each.kind === 'file'; });
        var addressable = selected.every(function (each) { return (each.kind === 'file') || (each.kind === 'page'); });

        var compressible = selected.filter(function (each) {
            return ((each.kind === 'file') || (each.kind === 'folder')) && each.can_edit;
        });

        var optimizable = selected.filter(function (each) {
            return each.can_edit && !each.optimized && OPTIMIZABLE_TYPES.indexOf(each.type) !== -1;
        });

        actions.push({ icon: 'bi-box-arrow-up-right', label: (first && first.kind === 'folder') ? L.open_folder : L.open_in_new_tab, action: 'open', off: !single });
        actions.push({ icon: 'bi-eye', label: L.quick_look, action: 'quick_look', off: !single || !quickLookable(first) });
        actions.push({ icon: 'bi-folder-symlink', label: L.move_to, action: 'move_to', off: !itemsMovable(selected) });
        actions.push({ icon: 'bi-file-earmark-arrow-down', label: L.download, action: 'download', off: !single || (first.kind !== 'file') });
        actions.push({ icon: 'bi-trash', label: L.delete, action: 'delete', off: !itemsDeletable(selected) });
        actions.push({ icon: 'bi-link-45deg', label: L.copy_address, action: 'copy_url_full', off: !addressable });
        actions.push({ icon: 'bi-folder-plus', label: L.copy_to, action: 'copy_to', off: !itemsCopyable(selected) });
        actions.push({ icon: 'bi-scissors', label: L.cut, action: 'cut', off: !itemsMovable(selected) });
        actions.push({ icon: 'bi-copy', label: L.copy, action: 'copy', off: !itemsCopyable(selected) });
        actions.push({ icon: 'bi-input-cursor-text', label: L.rename, action: 'rename', off: !single || !itemRenamable(first) });
        actions.push({ icon: 'bi-input-cursor', label: L.bulk_rename, action: 'bulk_rename', off: single || (bulkRenameTargets().length === 0) });
        actions.push({ icon: 'bi-file-earmark-zip', label: fmt(L.compress_zip, [compressible.length]), action: 'zip_create', off: (compressible.length === 0) || !canWriteCurrent() });

        if (allPages) {
            actions.push({ icon: 'bi-ui-checks', label: L.bulk_edit_pages, action: 'bulk_pages' });
        }

        if (allFiles) {
            actions.push({ icon: 'bi-ui-checks', label: L.bulk_edit_files, action: 'bulk_files' });
            actions.push({ icon: 'bi-fast-forward-circle', label: fmt(L.optimize_all, [optimizable.length]), action: 'bulk_optimize', off: optimizable.length === 0 });
        }

        return actions;
    }

    function selectionBarHtml(selected) {

        if ((selected.length === 0) || insideShared()) { return ''; }

        var actions = selectionActions(selected).filter(function (entry) { return !!entry; });
        var primary = actions.slice(0, 4);
        var overflow = actions.slice(4);

        // What is on the clipboard is still worth saying while something is
        // picked out -- cutting or copying leaves the selection standing, so
        // this strip is what is on screen at the moment it happens.
        var clipboardNote = state.clipboard
            ? ('<span class="text-warning-emphasis text-nowrap">' +
                esc(fmt(state.clipboard.mode === 'cut' ? L.clipboard_cut : L.clipboard_copy, [state.clipboard.items.length])) + '</span>')
            : '';

        var html = '<span class="text-primary text-nowrap">' + esc(fmt(L.items_selected, [selected.length])) + '</span>' +
            '<button type="button" class="btn btn-sm btn-ghost no-popover py-0 px-1" data-role="clear-selection" title="' + esc(L.clear_selection) + '"><i class="bi bi-x-lg"></i></button>' +
            clipboardNote +
            progressHtml() +
            '<span class="selection-actions ms-auto">';

        primary.forEach(function (entry) {
            html += '<button type="button" class="btn btn-sm btn-ghost no-popover text-nowrap" data-selection-action="' + esc(entry.action) + '"' +
                (entry.off ? ' disabled' : '') + ' title="' + esc(entry.label) + '">' +
                '<i class="bi ' + entry.icon + '"></i><span class="selection-action-label ms-1">' + esc(entry.label) + '</span></button>';
        });

        if (overflow.length > 0) {

            html += '<span class="dropdown dropup">' +
                '<button type="button" class="btn btn-sm btn-ghost no-popover" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="' + esc(L.more_actions) + '"><i class="bi bi-three-dots-vertical"></i></button>' +
                '<ul class="dropdown-menu dropdown-menu-end shadow" style="border-radius:.75rem;">';

            overflow.forEach(function (entry) {
                html += '<li><button type="button" class="dropdown-item' + (entry.off ? ' disabled' : '') + '" data-selection-action="' + esc(entry.action) + '"' +
                    (entry.off ? ' disabled' : '') + '><i class="bi ' + entry.icon + ' me-2"></i>' + esc(entry.label) + '</button></li>';
            });

            html += '</ul></span>';
        }

        return html + '</span>';
    }

    function renderStatusbar() {

        // A selection has more to say than a count does, so while there is one
        // the strip belongs to it. The counts come back the moment it is let
        // go, which is also what the × does.
        var picked = selectedItems();
        var pickedBar = selectionBarHtml(picked);

        if (pickedBar !== '') {
            document.getElementById('explorer_statusbar').innerHTML = pickedBar;
            return;
        }

        if (state.mode === 'catalog') {

            var catalogSelected = selectedItems();
            var totals = state.catalogTotals || { groups: 0, products: 0 };

            var catalogCount = (state.allFilter === 'variants')
                ? fmt(L.variant_sets_count, [state.items.folders.length])
                : fmt(L.catalog_counts, [state.items.folders.length, state.items.pages.length]);

            document.getElementById('explorer_statusbar').innerHTML =
                '<span>' + esc(catalogCount) + '</span>' +
                ((catalogSelected.length > 0) ? ('<span class="text-primary">' + esc(catalogSelected.length + ' ' + L.items + ' ' + L.selected) + '</span>') : '') +
                progressHtml() +
                '<span class="' + pushRightClass() + '">' + esc(fmt(L.catalog_totals, [totals.products, totals.groups])) + '</span>';
            return;
        }

        if (state.mode === 'backups') {
            var entries = state.backupEntries || [];

            document.getElementById('explorer_statusbar').innerHTML =
                '<span>' + esc(fmt(L.backups_counts, [entries.length])) + '</span>' +
                progressHtml() +
                '<span class="' + pushRightClass() + '">' + esc(L.disk_usage) + ': ' + esc(state.backupTotal) + '</span>';
            return;
        }

        if (state.mode === 'short_links') {

            var shortLinkSelected = selectedItems();

            var shortLinkClipboard = ((state.clipboard) && (state.clipboard.area === 'short_links'))
                ? ('<span>' + esc(fmt(L.clipboard_copy, [state.clipboard.items.length])) + '</span>') : '';

            document.getElementById('explorer_statusbar').innerHTML =
                '<span>' + esc(fmt(L.short_links_count, [state.items.files.length])) + '</span>' +
                ((shortLinkSelected.length > 0) ? ('<span class="text-primary">' + esc(shortLinkSelected.length + ' ' + L.items + ' ' + L.selected) + '</span>') : '') +
                shortLinkClipboard +
                progressHtml();
            return;
        }

        if (state.mode === 'shared') {
            var groups = state.sharedGroups || [];
            var people = 0;

            groups.forEach(function (group) { people += group.grants.length; });

            // No disk figure here: the report is about rights, not bytes.
            document.getElementById('explorer_statusbar').innerHTML =
                '<span>' + esc(fmt(L.shared_counts, [groups.length, people])) + '</span>' +
                progressHtml();
            return;
        }

        var counts = fmt(L.counts_label, [state.items.folders.length, state.items.pages.length, state.items.files.length]);
        var selected = selectedItems();
        var selectedInfo = selected.length > 0 ? (selected.length + ' ' + L.items + ' ' + L.selected) : '';
        var clipboardInfo = '';

        if (state.clipboard) {
            clipboardInfo = fmt(state.clipboard.mode === 'cut' ? L.clipboard_cut : L.clipboard_copy, [state.clipboard.items.length]);
        }

        document.getElementById('explorer_statusbar').innerHTML =
            '<span>' + esc(counts) + '</span>' +
            (selectedInfo ? '<span class="text-primary">' + esc(selectedInfo) + '</span>' : '') +
            (clipboardInfo ? '<span class="text-warning-emphasis">' + esc(clipboardInfo) + '</span>' : '') +
            progressHtml() +
            '<span class="' + pushRightClass() + '">' + esc(L.disk_usage) + ': ' + esc(state.diskUsage) + '</span>';
    }

    // ── How big the tiles are ───────────────────────────────────────────
    //
    // A folder of three hundred photographs and a folder of stylesheets want
    // opposite things from the same grid: one wants the picture big enough to
    // recognise, the other wants as many names on screen as will fit. The four
    // steps move the column, the picture box and the glyph together, so a tile
    // stays in proportion rather than growing a large frame around a small icon.
    var TILE_SIZES = [
        { key: 'small', tile: '104px', thumb: '62px', icon: '2.2rem' },
        { key: 'medium', tile: '136px', thumb: '84px', icon: '3rem' },
        { key: 'large', tile: '184px', thumb: '118px', icon: '4rem' },
        { key: 'xlarge', tile: '248px', thumb: '168px', icon: '5.4rem' }
    ];

    function tileSizeLabel(key) {
        if (key === 'small') { return L.tile_small; }
        if (key === 'large') { return L.tile_large; }
        if (key === 'xlarge') { return L.tile_xlarge; }
        return L.tile_medium;
    }

    function tileSizeIndex() {

        for (var index = 0; index < TILE_SIZES.length; index++) {
            if (TILE_SIZES[index].key === state.tileSize) { return index; }
        }

        return 1;
    }

    function applyTileSize() {

        var size = TILE_SIZES[tileSizeIndex()];
        var app = document.getElementById('explorer_app');

        app.style.setProperty('--pg-tile', size.tile);
        app.style.setProperty('--pg-tile-thumb', size.thumb);
        app.style.setProperty('--pg-tile-icon', size.icon);
    }

    function setTileSize(key) {

        state.tileSize = key;
        storageSet('pg_explorer_tile', key);
        applyTileSize();

        // The grid measures its own columns for the arrow keys, and the
        // thumbnail loader hands out pictures as tiles near the viewport --
        // both read sizes that have just changed.
        armThumbLoader();
    }

    function stepTileSize(delta) {

        var next = Math.max(0, Math.min(TILE_SIZES.length - 1, tileSizeIndex() + delta));

        if (TILE_SIZES[next].key === state.tileSize) { return; }

        setTileSize(TILE_SIZES[next].key);
    }

    // Offered inside the View submenu, under the two that choose between the
    // grid and the list -- and only in the grid, because that is the only
    // place a tile has a size.
    // The list's own half of the View submenu. Only there: the grid has no
    // columns, and offering the choice where it cannot apply is the same
    // lie as offering a column the viewport will not draw.
    function columnSubmenu() {

        if (state.viewType !== 'list') { return ''; }

        var inner = columnMenu();

        return (inner === '') ? '' : (menuDivider() + menuSubmenu('bi-layout-three-columns', L.columns, inner));
    }

    function tileSizeMenu() {

        if (state.viewType !== 'grid') { return ''; }

        var html = menuDivider();

        TILE_SIZES.forEach(function (size) {
            html += menuCheckItem(tileSizeLabel(size.key), 'tile_' + size.key, state.tileSize === size.key);
        });

        return html;
    }

    function syncViewButtons() {
        document.getElementById('view_grid_button').classList.toggle('active', state.viewType === 'grid');
        document.getElementById('view_list_button').classList.toggle('active', state.viewType === 'list');

        // Every mode's loader comes through here, which makes it the one
        // place that can hide the narrowing button on the views it does not
        // belong to.
        syncFileScope();
    }

    // ── Selection ───────────────────────────────────────────────────────

    function setSelection(items, anchorItem) {
        state.selection = {};
        items.forEach(function (item) { state.selection[itemKey(item)] = true; });
        if (anchorItem) { state.lastIndex = state.ordered.indexOf(anchorItem); }
        applySelectionClasses();
        renderStatusbar();
        renderPreview(items.length === 1 ? items[0] : null);
    }

    function applySelectionClasses() {
        document.querySelectorAll('#explorer_content .explorer-item').forEach(function (element) {
            var key = element.getAttribute('data-kind') + ':' + element.getAttribute('data-id');
            element.classList.toggle('selected', !!state.selection[key]);
        });
    }

    function toggleItemSelection(item) {
        var key = itemKey(item);
        if (state.selection[key]) { delete state.selection[key]; } else { state.selection[key] = true; }
        state.lastIndex = state.ordered.indexOf(item);
        state.cursor = state.lastIndex;
        applySelectionClasses();
        renderStatusbar();
        var selected = selectedItems();
        renderPreview(selected.length === 1 ? selected[0] : null);
    }

    // Everything between the anchor and here. Shared by the shift-click and the
    // shift-arrow, so the two can never disagree about what a range is.
    function selectRange(anchorIndex, index, additive) {

        var from = Math.min(anchorIndex, index);
        var to = Math.max(anchorIndex, index);

        if (!additive) { state.selection = {}; }

        state.ordered.slice(from, to + 1).forEach(function (rangeItem) { state.selection[itemKey(rangeItem)] = true; });
    }

    function handleItemClick(item, event) {
        var index = state.ordered.indexOf(item);

        state.cursor = index;

        if (event.shiftKey && state.lastIndex >= 0) {
            selectRange(state.lastIndex, index, (event.ctrlKey || event.metaKey));
        } else if (event.ctrlKey || event.metaKey) {
            toggleItemSelection(item);
            return;
        } else {
            state.selection = {};
            state.selection[itemKey(item)] = true;
            state.lastIndex = index;
        }

        applySelectionClasses();
        renderStatusbar();
        var selected = selectedItems();
        renderPreview(selected.length === 1 ? selected[0] : null);
    }

    // ── Walking the listing from the keyboard ───────────────────────────
    //
    // A file manager that can only be driven with a mouse is half a file
    // manager: picking twelve pictures out of three hundred, or stepping down a
    // folder looking at each preview in turn, is arrow-key work everywhere else
    // and was pointer work here.

    // How many tiles sit on a row. The grid is laid out with
    // repeat(auto-fill, ...), and the computed value of grid-template-columns
    // is the resolved track list rather than the shorthand, so counting the
    // tracks is counting the columns. A table is one item per row.
    function gridColumns() {

        var grid = document.getElementById('explorer_grid');

        if (!grid) { return 1; }

        var tracks = window.getComputedStyle(grid).gridTemplateColumns;

        if ((!tracks) || (tracks === 'none')) { return 1; }

        return Math.max(1, tracks.split(' ').filter(function (track) { return track !== ''; }).length);
    }

    function cursorElement() {

        var item = state.ordered[state.cursor];

        if (!item) { return null; }

        return document.querySelector('#explorer_content .explorer-item[data-kind="' + item.kind + '"][data-id="' + item.id + '"]');
    }

    function revealCursor() {

        var element = cursorElement();

        if (element && element.scrollIntoView) { element.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    }

    // Put the cursor on a row and say what that does to the selection.
    function moveCursorTo(index, extend) {

        if (state.ordered.length === 0) { return; }

        index = Math.max(0, Math.min(state.ordered.length - 1, index));

        var item = state.ordered[index];

        state.cursor = index;

        if (extend && (state.lastIndex >= 0)) {
            selectRange(state.lastIndex, index, false);
        } else {
            state.selection = {};
            state.selection[itemKey(item)] = true;
            state.lastIndex = index;
        }

        applySelectionClasses();
        renderStatusbar();

        var selected = selectedItems();

        renderPreview(selected.length === 1 ? selected[0] : null);
        revealCursor();
    }

    function moveCursorBy(step, extend) {

        // Nothing picked yet: the first press lands on the first row rather
        // than a row's worth into the grid, which is what every list does.
        if (state.cursor < 0) {
            moveCursorTo((step > 0) ? 0 : (state.ordered.length - 1), extend);
            return;
        }

        moveCursorTo(state.cursor + step, extend);
    }

    // Typing letters jumps to the row that starts with them.
    //
    // The comparison is Turkish-aware: toLocaleLowerCase('tr') is what folds
    // İ onto i and I onto ı, and an ASCII fold would send someone typing "İs"
    // past every name they were aiming at.
    var typeAheadBuffer = '';
    var typeAheadAt = 0;

    function typeAheadJump(character) {

        var now = Date.now();

        typeAheadBuffer = ((now - typeAheadAt) > 900) ? character : (typeAheadBuffer + character);
        typeAheadAt = now;

        var needle = typeAheadBuffer.toLocaleLowerCase('tr');
        var count = state.ordered.length;

        if (count === 0) { return; }

        // One repeated letter walks the rows that begin with it; anything else
        // stays put while the word is still being typed.
        var repeated = (typeAheadBuffer.length > 1) && (typeAheadBuffer.split('').every(function (each) { return each === typeAheadBuffer.charAt(0); }));

        if (repeated) { needle = typeAheadBuffer.charAt(0).toLocaleLowerCase('tr'); }

        var start = (state.cursor >= 0) ? state.cursor : -1;

        // A letter on its own, or the same letter again, walks to the next
        // match. A word being typed out has to be allowed to match the row it
        // is already on -- "a" lands on avatar-1.png and "av" must not step
        // off it onto avatar-2.png.
        var scanFrom = (repeated || (typeAheadBuffer.length === 1)) ? start : (start - 1);

        if (scanFrom < -1) { scanFrom = -1; }

        function scan(test) {

            for (var step = 1; step <= count; step++) {

                var index = (scanFrom + step + count * 2) % count;
                var name = (state.ordered[index].name || '').toLocaleLowerCase('tr');

                if (test(name)) { return index; }
            }

            return -1;
        }

        // Starting with it is what was meant; containing it is better than
        // doing nothing when a file is named "01_kapak.jpg".
        var found = scan(function (name) { return name.indexOf(needle) === 0; });

        if (found === -1) { found = scan(function (name) { return name.indexOf(needle) !== -1; }); }

        if (found !== -1) { moveCursorTo(found, false); }
    }

    // Walking into a group.
    //
    // All Products, All Variant Sets and the bin are views across the whole
    // catalog rather than places a group lives, so stepping into a group steps
    // out of them first -- the listing builds its request from that filter, so
    // without this the group id would be dropped and the flat list reloaded.
    // From the bin there is nothing to step into: the row is deleted, and
    // opening it as a live group would say otherwise.
    function openCatalogGroup(item) {

        if (insideCatalogBin()) { return; }

        if (state.allFilter !== '') { state.allFilter = ''; }

        load(item.id, highlightTree);
    }

    function openItem(item) {
        if (!item) { return; }

        // A short link is an address, so opening it means following it.
        if (item.short_link) { window.open(item.url, '_blank'); return; }

        if (item.backup) {
            if (item.kind === 'folder') { enterBackups(item.path); } else { window.location.href = backupDownloadUrl(item.path); }
            return;
        }

        if (item.kind === 'group') { openCatalogGroup(item); return; }
        if (item.kind === 'product') { window.location.href = item.edit_url + '&send_to=' + encodeURIComponent(currentSendTo()); return; }
        if (item.kind === 'folder') { load(item.id, highlightTree); return; }
        window.open(item.url, '_blank');
    }

    // Double click follows the platform decision: folders enter, pages open
    // their own page (that is where page content is edited), files open the
    // file edit screen when allowed.
    function activateItem(item) {
        if (!item) { return; }

        // Double click opens the editor rather than following the link: the
        // link itself is one click away in the menu, and a double click that
        // navigated away from the list would be a trap.
        if (item.short_link) { openShortLinkWizard(item); return; }

        if (item.backup) { openItem(item); return; }

        // A group is walked into; a product opens the screen that edits it.
        // Neither gets a "view on site" here: a product group can be published
        // through several pages at once, so the preview panel lists them and
        // lets the operator pick instead of guessing one.
        if (item.kind === 'group') { openCatalogGroup(item); return; }
        if (item.kind === 'product') { window.location.href = item.edit_url + '&send_to=' + encodeURIComponent(currentSendTo()); return; }

        if (item.kind === 'folder') { load(item.id, highlightTree); return; }
        if (item.kind === 'page') { window.open(item.url, '_blank'); return; }

        // A file opens in the file window, on this screen; one you may only
        // look at opens as itself.
        if (item.can_edit) {
            openFileEditor(item);
        } else {
            window.open(item.url, '_blank');
        }
    }

    // ── Preview panel ───────────────────────────────────────────────────

    // The panel for a group or a product.
    //
    // No "view on site" button here on purpose: the same group can be published
    // through a legacy catalog page and through a widget that lives in a page
    // style several pages share, so a single link would be a guess. The pages
    // are listed instead and the operator picks. Likewise a product may belong
    // to several groups at once, so its groups are listed rather than summed
    // into one path.
    function renderCatalogPreview(item, pane) {

        var rows = '';

        function row(label, value) {
            if ((value === '') || (value === null) || (value === undefined)) { return; }
            rows += '<div class="prev-kv"><span>' + esc(label) + '</span><span>' + value + '</span></div>';
        }

        var head = '<div class="text-center my-3"><span class="bi ' +
            ((item.kind === 'product')
                ? 'bi-box2-heart'
                : (isVariantSet(item) ? 'bi-collection-fill' : 'bi-boxes')) +
            ' display-4 text-primary"></span></div>';

        if ((item.kind === 'product') && item.image_name) {
            head = '<img class="preview-media" draggable="false" src="' + esc(path + item.image_name) + '" alt="" />';
        }

        // First, because on the site this is the row's name.
        if (String(item.short_description || '').trim() !== '') {
            row(L.short_description, esc(item.short_description));
        }

        row(L.type, esc(itemTypeLabel(item)));
        row(L.status, (item.enabled === false)
            ? ('<span class="text-warning-emphasis">' + esc(L.group_disabled) + '</span>')
            : ('<span class="text-success">' + esc(L.group_published) + '</span>'));

        if (item.kind === 'group') {
            row(L.display_type, esc((item.display_type === 'select') ? L.display_select : L.display_browse));
            row(L.direct_products, item.counts.products);
            row(L.deep_products, item.counts.products_deep);
            row(L.price_range, esc(priceRangeLabel(item)));

            if (outOfStock(item)) {
                row(L.out_of_stock, '<span class="text-danger">' + esc(String(item.out_of_stock)) + '</span>');
            }

        } else {
            row(L.price, esc(moneyLabel(item.price)));

            // Nothing left but back orders are on: the shelf is empty and the
            // product is still orderable. Saying only "out of stock" there
            // sends the operator to restock something that is still selling.
            row(L.quantity, item.inventory
                ? ((item.quantity > 0)
                    ? esc(String(item.quantity))
                    : (item.backorder
                        ? ('<span class="text-warning-emphasis">' + esc(L.backorder_label) + '</span>')
                        : ('<span class="text-danger">' + esc(L.out_of_stock) + '</span>')))
                : esc(L.not_tracked));
        }

        if (item.address_name) { row(L.address, esc(item.address_name)); }
        if (item.modified) { row(L.last_modified, esc(item.modified)); }

        var groups = '';

        if ((item.kind === 'product') && (item.groups) && (item.groups.length > 0)) {

            groups = '<div class="prev-group"><div class="prev-group-title">' + esc(L.member_of) + '</div>';

            item.groups.forEach(function (group) {
                groups += '<div class="prev-row"><a href="#" data-catalog-jump="' + group.id + '" class="text-truncate">' +
                    '<span class="bi ' + ((group.catalog_role === 'variant_set') ? 'bi-collection-fill' : 'bi-boxes') + ' me-1"></span>' +
                    esc(group.name) + '</a></div>';
            });

            groups += '</div>';
        }

        var seo = '';

        // The same checklist the Products screen opens in its panel, not just
        // the ring: a score on its own says something is wrong without saying
        // what, and the operator is already looking at the record.
        if (item.seo && item.seo.scored) {
            seo = '<div class="prev-group"><div class="prev-group-title">' + esc(L.seo_score_label) + '</div>' +
                '<div class="text-center my-2">' + seoChipHtml(item, 46) + '</div>' +
                '<div id="preview_seo_detail"><div class="text-center my-2"><span class="spinner-border spinner-border-sm text-secondary"></span></div></div></div>';
        }

        pane.innerHTML =
            head +
            '<div class="prev-title">' + esc(item.name) + '</div>' +
            rows +
            groups +
            seo +
            '<div class="prev-group"><div class="prev-group-title">' + esc(L.appears_on) + '</div>' +
            '<div id="catalog_pages_list" data-for="' + esc(itemKey(item)) + '"></div></div>' +
            '<div class="d-grid gap-2 mt-3">' +
            '<a class="btn btn-sm btn-outline-secondary" href="' + esc(item.edit_url) + '&send_to=' + encodeURIComponent(currentSendTo()) + '">' +
            '<span class="bi bi-pencil me-1"></span>' + esc(L.edit) + '</a></div>';

        if (item.seo && item.seo.scored) {
            queueSeoDetail(item.kind, item.id);
        }

        pane.querySelectorAll('[data-catalog-jump]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                state.allFilter = '';
                load(parseInt(element.getAttribute('data-catalog-jump'), 10) || 0, highlightTree);
            });
        });

        loadCatalogPages(item, document.getElementById('catalog_pages_list'));
    }

    // ── Quick look ──────────────────────────────────────────────────────
    //
    // The preview panel is a description; this is the thing itself, as large
    // as the screen will draw it, with the folder still underneath so the
    // arrow keys can walk it. Space opens it and Space closes it -- the same
    // key twice, because that is how every file manager that has one works.

    var quickLookItem = null;

    function quickLookable(item) {

        if ((!item) || item.backup || item.short_link) { return false; }

        return ((item.kind === 'file') || (item.kind === 'page') || (item.kind === 'product') || (item.kind === 'group'));
    }

    function quickLookMediaHtml(item) {

        if ((item.kind === 'product') || (item.kind === 'group')) {

            return item.image_name
                ? ('<img draggable="false" src="' + esc(path + item.image_name) + '" alt="" />')
                : ('<div class="text-center empty-note"><i class="bi ' + ((item.kind === 'group') ? 'bi-boxes' : 'bi-box2-heart') + ' display-1 d-block mb-2"></i>' + esc(L.no_preview) + '</div>');
        }

        if (item.kind === 'page') { return '<iframe loading="lazy" src="' + esc(item.url) + '" referrerpolicy="no-referrer"></iframe>'; }
        if (item.is_image) { return '<img draggable="false" src="' + esc(imageUrl(item)) + '" alt="" />'; }
        if (item.type === 'pdf') { return '<iframe loading="lazy" src="' + esc(item.url) + '"></iframe>'; }
        if (PREVIEW_VIDEO_TYPES.indexOf(item.type) !== -1) { return '<video controls autoplay preload="metadata" src="' + esc(item.url) + '"></video>'; }
        if (PREVIEW_AUDIO_TYPES.indexOf(item.type) !== -1) { return '<audio controls autoplay preload="metadata" src="' + esc(item.url) + '" style="width:min(32rem,80vw);"></audio>'; }

        return '<div class="text-center empty-note"><i class="bi ' + (FILETYPE_ICONS[item.type] || 'bi-file-earmark') +
            ' display-1 d-block mb-2"></i>' + esc(L.no_preview) + '</div>';
    }

    function quickLookMeta(item) {

        if (item.kind === 'product') { return moneyLabel(item.price); }
        if (item.kind === 'group') { return fmt(L.catalog_counts, [item.counts.groups, item.counts.products_deep]); }

        if (item.kind === 'file') {
            return (item.size_label || '') + ((item.is_image && (item.image_width > 0))
                ? (' · ' + item.image_width + ' × ' + item.image_height + ' px') : '');
        }

        return itemTypeLabel(item);
    }

    function renderQuickLook() {

        var item = quickLookItem;

        if (!item) { return; }

        document.getElementById('quicklook_name').textContent = item.name || '';
        document.getElementById('quicklook_meta').textContent = quickLookMeta(item);
        document.getElementById('quicklook_media').innerHTML = quickLookMediaHtml(item);

        // The few things worth doing while looking at it. Editing goes through
        // the same door the panel and the menu use, so there is one file window
        // rather than a second one that drifts.
        var tools = '';

        if ((item.kind === 'file') || (item.kind === 'page')) {
            tools += '<button type="button" class="btn btn-sm btn-ghost no-popover" data-ql="copy" title="' + esc(L.copy_address) + '"><i class="bi bi-link-45deg"></i></button>';
        }

        if ((item.kind === 'file') && item.can_edit) {
            tools += '<button type="button" class="btn btn-sm btn-ghost no-popover" data-ql="edit" title="' + esc(L.edit) + '"><i class="bi bi-pencil-square"></i></button>';
            tools += '<a class="btn btn-sm btn-ghost no-popover" href="' + esc(item.url) + '" download title="' + esc(L.download) + '"><i class="bi bi-download"></i></a>';
        }

        if ((item.kind === 'product') || (item.kind === 'group')) {
            tools += '<a class="btn btn-sm btn-ghost no-popover" href="' + esc(item.edit_url) + '&send_to=' + encodeURIComponent(currentSendTo()) + '" title="' + esc(L.edit) + '"><i class="bi bi-pencil-square"></i></a>';
        }

        if (item.url) {
            tools += '<a class="btn btn-sm btn-ghost no-popover" href="' + esc(item.url) + '" target="_blank" rel="noopener" title="' + esc(L.open_in_new_tab) + '"><i class="bi bi-box-arrow-up-right"></i></a>';
        }

        document.getElementById('quicklook_tools').innerHTML = tools;

        var at = state.ordered.indexOf(item);

        document.getElementById('quicklook_prev').disabled = (at <= 0);
        document.getElementById('quicklook_next').disabled = ((at < 0) || (at >= (state.ordered.length - 1)));
    }

    function openQuickLook(item) {

        if (!quickLookable(item)) { return; }

        quickLookItem = item;
        document.getElementById('explorer_quicklook').classList.remove('d-none');
        renderQuickLook();
    }

    function closeQuickLook() {

        quickLookItem = null;

        // Emptied rather than just hidden: a video left in the markup keeps
        // playing behind a screen nobody can see.
        document.getElementById('quicklook_media').innerHTML = '';
        document.getElementById('explorer_quicklook').classList.add('d-none');
    }

    function quickLookIsOpen() {
        return !document.getElementById('explorer_quicklook').classList.contains('d-none');
    }

    // Walking the folder from inside the overlay. The selection follows, so
    // closing it leaves the operator standing on what they were looking at.
    function stepQuickLook(delta) {

        if (!quickLookItem) { return; }

        var at = state.ordered.indexOf(quickLookItem);

        if (at < 0) { return; }

        for (var next = at + delta; (next >= 0) && (next < state.ordered.length); next += delta) {

            if (!quickLookable(state.ordered[next])) { continue; }

            moveCursorTo(next, false);
            quickLookItem = state.ordered[next];
            renderQuickLook();
            return;
        }
    }

    // ── Where a file is used ────────────────────────────────────────────
    //
    // The answer is drawn where the question was asked -- in the panel, under
    // the file it is about -- rather than in a window that has to be closed
    // again before the operator can act on what it said.
    function loadFileUsage(item, container) {

        if (!container) { return; }

        container.innerHTML = '<div class="text-center small text-body-secondary py-2">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>' + esc(L.where_used_looking) + '</div>';

        api({ type: 'explorer_file_usage', file_id: item.id }, function (response) {

            // The panel may have moved on to another file while this was out.
            if (!container.isConnected) { return; }

            if (response.status !== 'success') {
                container.innerHTML = '<div class="small text-danger">' + esc(response.message || L.request_failed) + '</div>';
                return;
            }

            if (!response.total) {
                container.innerHTML = '<div class="small text-body-secondary"><i class="bi bi-check2 me-1"></i>' + esc(L.where_used_none) + '</div>';
                return;
            }

            var html = '<div class="prev-group-title mb-1">' + esc(fmt(L.where_used_count, [response.total])) + '</div>';

            (response.groups || []).forEach(function (group) {

                html += '<div class="prev-group-title" style="margin-top:.5rem;">' + esc(group.label) + '</div>';

                (group.items || []).forEach(function (entry) {

                    // Not everything found has a screen of its own: a shared
                    // component is edited inside whichever design uses it, and
                    // a setting is a value rather than a record. Those are
                    // named without a link rather than given one that goes
                    // nowhere.
                    var label = esc(entry.name) + (entry.note ? (' <span class="text-body-secondary">· ' + esc(entry.note) + '</span>') : '');

                    html += '<div class="prev-row text-truncate">' +
                        (entry.url ? ('<a href="' + esc(entry.url) + '">' + label + '</a>') : label) +
                        '</div>';
                });
            });

            if (response.truncated) {
                html += '<div class="form-text mt-1">' + esc(L.where_used_more) + '</div>';
            }

            container.innerHTML = html;
        });
    }

    function renderPreview(item) {
        // While a drag is in flight the pane must not change: injecting an
        // iframe or image mid-drag stalls the renderer on some machines.
        if (dragPreviewHold) { return; }

        var pane = document.getElementById('explorer_preview_pane');

        if (!item) {
            pane.innerHTML = '<div class="text-center empty-note my-5"><span class="bi bi-eye fs-1 d-block mb-2"></span>' + esc(L.preview) + '</div>';
            return;
        }

        if ((item.kind === 'group') || (item.kind === 'product')) {
            renderCatalogPreview(item, pane);
            return;
        }

        // A short link has nothing to preview -- it is an address, not a thing
        // with content. What is worth showing is where it lands, and the two
        // ways of getting there: follow it, or open the screen that changes it.
        if (item.short_link) {

            var shortLinkRows =
                '<tr><th>' + esc(L.name) + '</th><td class="text-break">' + esc(item.name) + '</td></tr>' +
                '<tr><th>' + esc(L.destination_type) + '</th><td>' + esc(shortLinkTypeLabel(item.destination_type)) + '</td></tr>' +
                '<tr><th>' + esc(L.destination) + '</th><td class="text-break">' + esc(item.destination || '') + '</td></tr>' +
                (item.tracking_code ? ('<tr><th>' + esc(L.tracking_code) + '</th><td><code>' + esc(item.tracking_code) + '</code></td></tr>') : '') +
                (item.modified ? ('<tr><th>' + esc(L.last_modified) + '</th><td>' + item.modified + (item.username ? ' ' + fmt(L.modified_by, [esc(item.username)]) : '') + '</td></tr>') : '');

            pane.innerHTML =
                '<div class="text-center my-3">' + shortLinkIconHtml(item, true) + '</div>' +
                '<div class="text-center mb-3"><a class="btn btn-sm btn-outline-primary" href="' + esc(item.url) + '" target="_blank" rel="noopener">' +
                    '<span class="bi bi-box-arrow-up-right me-1"></span>' + esc(L.visit) + '</a> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary" data-role="short-link-edit">' +
                    '<span class="bi bi-pencil me-1"></span>' + esc(L.edit) + '</button></div>' +
                '<table class="table table-sm"><tbody>' + shortLinkRows + '</tbody></table>';

            var shortLinkEditButton = pane.querySelector('[data-role="short-link-edit"]');

            if (shortLinkEditButton) {
                shortLinkEditButton.addEventListener('click', function () { openShortLinkWizard(item); });
            }

            return;
        }

        var media = '';

        if (item.backup) {
            media = (item.kind === 'folder')
                ? ('<div class="text-center my-3"><span class="bi bi-folder-fill display-4 text-warning"></span></div>')
                : ('<div class="text-center my-3"><span class="bi bi-file-earmark-zip display-4 d-block mb-2"></span>' +
                    '<a class="btn btn-sm btn-outline-primary" href="' + esc(backupDownloadUrl(item.path)) + '"><span class="bi bi-download me-1"></span>' + esc(L.download) + '</a></div>');

        } else if (item.kind === 'page') {
            media = '<iframe loading="lazy" src="' + esc(item.url) + '" referrerpolicy="no-referrer"></iframe>';
        } else if (item.kind === 'file' && item.is_image) {
            media = '<a href="' + esc(imageUrl(item)) + '" target="_blank" draggable="false"><img class="preview-media" draggable="false" src="' + esc(imageUrl(item)) + '" alt="" /></a>';
        } else if (item.kind === 'file' && item.type === 'pdf') {
            media = '<iframe loading="lazy" src="' + esc(item.url) + '"></iframe>';
        } else if (item.kind === 'file' && PREVIEW_VIDEO_TYPES.indexOf(item.type) !== -1) {
            media = '<video controls preload="metadata" style="width:100%;border-radius:.6rem;" src="' + esc(item.url) + '"></video>';
        } else if (item.kind === 'file' && PREVIEW_AUDIO_TYPES.indexOf(item.type) !== -1) {
            media = '<audio controls preload="metadata" style="width:100%;" src="' + esc(item.url) + '"></audio>';
        } else if (item.kind === 'folder') {
            media = '<div class="text-center my-3"><span class="bi bi-folder-fill display-4 ' + item.access_control_type + '"></span></div>';
        } else {
            media = '<div class="text-center empty-note my-3"><span class="bi ' + (FILETYPE_ICONS[item.type] || 'bi-file-earmark') + ' fs-1 d-block mb-2"></span>' + esc(L.no_preview) + '</div>';
        }

        var rows = '';

        rows += '<tr><th>' + esc(L.name) + '</th><td class="text-break">' + esc(item.name) + '</td></tr>';
        rows += '<tr><th>' + esc(L.type) + '</th><td>' + esc(itemTypeLabel(item)) + '</td></tr>';

        if (item.kind === 'file') {
            rows += '<tr><th>' + esc(L.size) + '</th><td>' + esc(item.size_label || '') + '</td></tr>';
            if (item.is_image && item.image_width > 0) {
                rows += '<tr><th>' + esc(L.dimensions) + '</th><td>' + item.image_width + ' × ' + item.image_height + ' px</td></tr>';
            }
            if (item.description) {
                rows += '<tr><th>' + esc(L.description) + '</th><td class="text-break">' + esc(item.description) + '</td></tr>';
            }
        }

        if (item.kind === 'folder' && !item.backup) {
            rows += '<tr><th>' + esc(L.size) + '</th><td>' + esc(fmt(L.counts_label, [item.counts.folders, item.counts.pages, item.counts.files])) + '</td></tr>';
        }

        if (item.backup) {
            rows += '<tr><th>' + esc(L.folder) + '</th><td class="text-break">' + esc(item.path) + '</td></tr>';
            rows += '<tr><th>' + esc(L.permissions) + '</th><td><code>' + esc(item.permissions || '') + '</code></td></tr>';
        }

        if (item.kind === 'file' && item.folder_name) {
            rows += '<tr><th>' + esc(L.folder) + '</th><td>' + esc(item.folder_name) + '</td></tr>';
        }

        if (!item.backup) {
            rows += '<tr><th>' + esc(L.access_control) + '</th><td><span class="' + item.access_control_type + '"><span class="bi ' + item.access_icon + ' me-1"></span>' + esc(accessLabel(item.access_control_type)) + '</span></td></tr>';
        }

        // Folder and page details: styles, order and archive state, the way
        // the edit screens report them but without leaving the manager.
        if (((item.kind === 'folder') || (item.kind === 'page')) && !item.backup) {
            if (item.kind === 'folder') {
                rows += '<tr><th>' + esc(L.sorting) + '</th><td>' + esc(String(item.order)) + '</td></tr>';
            }

            rows += '<tr><th>' + esc(L.archive) + '</th><td>' + esc(item.archived ? L.yes : L.no) + '</td></tr>';
            rows += '<tr><th>' + esc(L.desktop_style) + '</th><td>' + styleCellHtml(item.style_desktop) + '</td></tr>';
            rows += '<tr><th>' + esc(L.mobile_style) + '</th><td>' + styleCellHtml(item.style_mobile) + '</td></tr>';
        }

        if ((item.kind === 'page') && !item.backup) {
            rows += '<tr><th>' + esc(L.impact) + '</th><td>' + impactCellHtml(item) + '</td></tr>';
            rows += '<tr><th>' + esc(L.sitemap_label) + '</th><td>' + esc(item.sitemap ? L.yes : L.no) + '</td></tr>';
            rows += '<tr><th>' + esc(L.searchable_label) + '</th><td>' + esc(item.searchable ? L.yes : L.no) + '</td></tr>';
            rows += '<tr><th>' + esc(L.comments_label) + '</th><td>' + esc(item.comments ? L.yes : L.no) + '</td></tr>';
        }

        if (item.modified) {
            rows += '<tr><th>' + esc(L.last_modified) + '</th><td>' + (item.modified || '') + (item.username ? ' ' + fmt(L.modified_by, [esc(item.username)]) : '') + '</td></tr>';
        }

        var seoSection = '';

        if (item.kind === 'page' && item.seo) {
            var chip = seoChipHtml(item, 34);
            var flagNotes = (item.seo.scored && item.seo.labels && item.seo.labels.length > 0)
                ? '<div class="form-text mt-0 mb-2">' + esc(item.seo.labels.join(' · ')) + '</div>' : '';

            seoSection =
                '<h6 class="text-uppercase text-secondary fw-bold mt-3" style="font-size:.72rem;letter-spacing:.05em;">' + esc(L.seo_detail) + '</h6>' +
                '<div class="d-flex align-items-center gap-2 mb-1">' + chip +
                (item.seo.scored ? '<span class="small text-body-secondary">' + esc(L.seo_score_label) + ': ' + item.seo.score + '/100</span>'
                    : '<span class="small text-body-secondary">' + esc(L.seo_not_ready) + '</span>') +
                '</div>' + flagNotes +
                '<div id="preview_seo_detail"><div class="text-center my-2"><span class="spinner-border spinner-border-sm text-secondary"></span></div></div>';
        }

        // The file window, from here as well as from the menu and the double
        // click -- the panel is where somebody is looking when they decide
        // the description is wrong.
        var editButton = ((item.kind === 'file') && item.can_edit && !item.backup && !item.short_link)
            ? '<div class="d-grid mb-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-role="file-edit">' +
                '<span class="bi bi-pencil-square me-1"></span>' + esc(L.edit) + '</button></div>'
            : '';

        // The three things somebody does with what they are looking at, next
        // to the thing itself. The panel is where the decision is made, and
        // until now the only way to act on it was to go back and right-click
        // the tile.
        var quickTools = '';

        if (((item.kind === 'file') || (item.kind === 'page')) && !item.backup && !item.short_link) {

            quickTools =
                '<div class="btn-group btn-group-sm w-100 mb-3">' +
                '<button type="button" class="btn btn-outline-secondary no-popover" data-role="copy-address" title="' + esc(L.copy_address) + '"><i class="bi bi-link-45deg"></i></button>' +
                ((item.kind === 'file')
                    ? ('<a class="btn btn-outline-secondary no-popover" href="' + esc(item.url) + '" download title="' + esc(L.download) + '"><i class="bi bi-download"></i></a>')
                    : '') +
                '<a class="btn btn-outline-secondary no-popover" href="' + esc(item.url) + '" target="_blank" rel="noopener" title="' + esc(L.open_in_new_tab) + '"><i class="bi bi-box-arrow-up-right"></i></a>' +
                '</div>';
        }

        // "Can I delete this?" -- the question the panel could not answer, and
        // the way it was answered instead was by deleting the file and waiting
        // to see what broke. Behind a button rather than loaded with the panel:
        // it walks several text columns without an index, which is fine for a
        // press and would not be for every click on a grid.
        var usageBlock = ((item.kind === 'file') && !item.backup && !item.short_link)
            ? ('<div id="preview_usage" class="mb-3"><button type="button" class="btn btn-sm btn-outline-secondary w-100 no-popover" data-role="where-used">' +
                '<i class="bi bi-diagram-3 me-1"></i>' + esc(L.where_used) + '</button></div>')
            : '';

        pane.innerHTML =
            '<div class="mb-2">' + media + '</div>' +
            quickTools +
            editButton +
            usageBlock +
            '<h6 class="text-uppercase text-secondary fw-bold" style="font-size:.72rem;letter-spacing:.05em;">' + esc(L.details) + '</h6>' +
            '<table class="table table-sm"><tbody>' + rows + '</tbody></table>' +
            seoSection;

        var fileEditButton = pane.querySelector('[data-role="file-edit"]');

        if (fileEditButton) {
            fileEditButton.addEventListener('click', function () { openFileEditor(item); });
        }

        var copyAddressButton = pane.querySelector('[data-role="copy-address"]');

        if (copyAddressButton) {
            copyAddressButton.addEventListener('click', function () { copyAddresses([item], 'full'); });
        }

        var usageButton = pane.querySelector('[data-role="where-used"]');

        if (usageButton) {
            usageButton.addEventListener('click', function () { loadFileUsage(item, pane.querySelector('#preview_usage')); });
        }

        if (item.kind === 'page' && item.seo) {
            queueSeoDetail(item.kind, item.id);
        }
    }

    function styleCellHtml(info) {
        if (!info) { return ''; }

        var name = info.name || L.default;

        return esc(name) + (info.inherited ? ' <span class="text-body-secondary">(' + esc(L.inherit) + ')</span>' : '');
    }

    // The Impact figure the Pages screen shows: how much fixing this page
    // would matter, weighed by its last month of traffic. Null means "cannot
    // say" (never scored, or nobody visited) — printed as a dash, exactly
    // like pg_seo_render_impact().
    function impactCellHtml(item) {
        if (item.kind !== 'page') { return ''; }

        if (item.impact === null || item.impact === undefined) {
            return '<span class="text-body-secondary">–</span>';
        }

        return '<span class="fw-semibold">' + item.impact + '</span>' +
            '<div class="small text-body-secondary">' + esc(fmt(L.views_label, [item.views])) + '</div>';
    }

    // Sitemap / search / comments as three small marks, colored when on.
    function pageFeatureIcons(item) {
        if (item.kind !== 'page') { return ''; }

        function mark(icon, on, label) {
            return '<span class="bi ' + icon + ' mx-1 ' + (on ? 'text-success' : 'opacity-25') + '" title="' +
                esc(label + ': ' + (on ? L.yes : L.no)) + '"></span>';
        }

        return mark('bi-diagram-3', item.sitemap, L.sitemap_label) +
            mark('bi-search', item.searchable, L.searchable_label) +
            mark('bi-chat-dots', item.comments, L.comments_label);
    }

    // The full SEO checklist comes from the same fragment the list screens
    // open in their offcanvas. Fetching it recalculates the record, so the
    // request waits out quick selection changes and the answer is kept for
    // the rest of the visit.
    var seoDetailCache = {};
    var seoDetailTimer = null;

    // get_seo_analysis.php scores three kinds of record; the grid calls two of
    // them by different names.
    var SEO_RECORD_TYPES = { page: 'page', product: 'product', group: 'product_group' };

    function queueSeoDetail(kind, recordId) {

        if (seoDetailTimer) { window.clearTimeout(seoDetailTimer); seoDetailTimer = null; }

        var container = document.getElementById('preview_seo_detail');
        var recordType = SEO_RECORD_TYPES[kind];

        if ((!container) || (!recordType)) { return; }

        var cacheKey = kind + ':' + recordId;

        if (seoDetailCache[cacheKey]) {
            container.innerHTML = seoDetailCache[cacheKey];
            return;
        }

        seoDetailTimer = window.setTimeout(function () {
            seoDetailTimer = null;

            $.ajax({
                url: 'get_seo_analysis.php?type=' + recordType + '&id=' + recordId + '&fragment=checklist&token=' + encodeURIComponent(software_token),
                type: 'GET',
                dataType: 'html'
            }).done(function (html) {
                seoDetailCache[cacheKey] = html;

                // Only paint if this record is still the one being previewed.
                var target = document.getElementById('preview_seo_detail');
                var selected = selectedItems();

                if (target && selected.length === 1 && selected[0].kind === kind && String(selected[0].id) === String(recordId)) {
                    target.innerHTML = html;
                }
            }).fail(function () {
                var target = document.getElementById('preview_seo_detail');
                if (target) { target.innerHTML = '<div class="form-text">' + esc(L.request_failed) + '</div>'; }
            });
        }, 600);
    }

    // The checklist's own "refresh score" / "analyze HTML" buttons — the same
    // delegated handler the list screens emit (pg_seo_render_run_script),
    // repeated here because an injected fragment does not bring its script.
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.pg-seo-run');

        if (!trigger) { return; }

        event.preventDefault();

        var wrap = trigger.closest('.pg_seo_panel_wrap');

        if (!wrap || trigger.disabled) { return; }

        var buttons = wrap.querySelectorAll('.pg-seo-run');
        Array.prototype.forEach.call(buttons, function (button) { button.disabled = true; });
        trigger.classList.add('disabled');

        fetch(trigger.getAttribute('data-seo-url'), { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                // The stored copy is stale the moment a re-run lands.
                seoDetailCache = {};

                var holder = document.createElement('div');
                holder.innerHTML = html;
                var fresh = holder.querySelector('.pg_seo_panel_wrap');

                if (fresh) { wrap.replaceWith(fresh); } else { wrap.innerHTML = html; }
            })
            .catch(function () {
                Array.prototype.forEach.call(buttons, function (button) { button.disabled = false; });
                trigger.classList.remove('disabled');
            });
    });

    // ── Context menu (content items, background, tree nodes) ────────────

    var menuElement = document.getElementById('explorer_menu');

    function hideMenu() { menuElement.style.display = 'none'; }

    function menuItem(icon, label, action, disabled) {
        return '<li><button type="button" class="dropdown-item' + (disabled ? ' disabled' : '') + '" data-menu-action="' + action + '">' +
            '<span class="bi ' + icon + ' me-2"></span>' + esc(label) + '</button></li>';
    }

    function menuDivider() { return '<li><hr class="dropdown-divider" /></li>'; }

    function menuCheckItem(label, action, checked, disabled) {
        return '<li><button type="button" class="dropdown-item' + (disabled ? ' disabled' : '') + '" data-menu-action="' + action + '">' +
            '<span class="menu-check bi ' + (checked ? 'bi-check2' : '') + '"></span>' + esc(label) + '</button></li>';
    }

    function menuSubmenu(icon, label, innerHtml, disabled) {
        return '<li class="has-submenu"><button type="button" class="dropdown-item' + (disabled ? ' disabled' : '') + '" data-submenu="1">' +
            '<span class="bi ' + icon + ' me-2"></span>' + esc(label) +
            '<span class="submenu-arrow bi bi-chevron-right"></span></button>' +
            '<ul class="submenu dropdown-menu">' + innerHtml + '</ul></li>';
    }

    function positionMenu(event) {
        menuElement.style.display = 'block';
        var x = Math.min(event.clientX, window.innerWidth - menuElement.offsetWidth - 8);
        var y = Math.min(event.clientY, window.innerHeight - menuElement.offsetHeight - 8);
        menuElement.style.left = Math.max(0, x) + 'px';
        menuElement.style.top = Math.max(0, y) + 'px';
    }

    function showMenu(event, item) {
        var html = '';
        var selected = selectedItems();
        var writable = canWriteCurrent();

        // The store offers only what actually works on a group or a product.
        // Falling through to the folder menu handed an operator Cut, Duplicate,
        // Compress and Delete for a product group -- every one of them aimed at
        // the folder tables with an id that means something else there, and a
        // group id can collide with a real folder id. A menu that looks alive
        // and is not is the worse half of that: this one is short and true.
        if (insideCatalog()) {

            var catalogMenu = '';
            var catalogMulti = multiSelected(selected);
            var catalogPasteOff = (!state.clipboard) || (state.clipboard.area !== 'catalog') || (!canPasteHere());

            if (item) {

                if (insideCatalogBin()) {

                    catalogMenu += menuItem('bi-arrow-counterclockwise', L.restore_item, 'catalog_restore');
                    catalogMenu += menuItem('bi-trash', L.delete_permanently, 'catalog_purge', selected.length === 0);
                    catalogMenu += menuDivider();

                } else {

                    if (item.kind === 'group') {
                        catalogMenu += menuItem('bi-box-arrow-up-right', L.open_folder, 'catalog_open', catalogMulti);
                        catalogMenu += menuItem(isPinned(item.id) ? 'bi-pin-angle-fill' : 'bi-pin-angle',
                            isPinned(item.id) ? L.unpin_here : L.pin_here, 'pin_toggle', catalogMulti);
                    }

                    catalogMenu += menuItem('bi-pencil', L.edit, 'catalog_edit', catalogMulti);
                    catalogMenu += menuItem('bi-eye', L.quick_look + ' (Space)', 'quick_look', catalogMulti || !quickLookable(item));

                    // The three figures a shop changes all day, without the
                    // product screen. Only on a product: a group has no price
                    // and no stock of its own.
                    if ((!catalogMulti) && (item.kind === 'product') && (state.viewType === 'list')) {
                        catalogMenu += menuSubmenu('bi-pencil-square', L.edit_here,
                            menuItem('bi-tag', L.price, 'quick_edit_price', !quickEditReachable(item, 'price')) +
                            menuItem('bi-box-seam', L.quantity, 'quick_edit_quantity', !quickEditReachable(item, 'quantity')) +
                            menuItem('bi-card-text', L.short_description, 'quick_edit_short', !quickEditReachable(item, 'short_description')));
                    }
                    catalogMenu += (item.enabled === false)
                        ? menuItem('bi-eye', L.publish_item, 'catalog_publish')
                        : menuItem('bi-eye-slash', L.unpublish_item, 'catalog_unpublish');
                    catalogMenu += menuItem('bi-input-cursor-text', L.rename + ' (F2)', 'rename', catalogMulti || !itemRenamable(item));

                    if (catalogMulti) {
                        catalogMenu += menuItem('bi-input-cursor', fmt(L.bulk_rename_menu, [bulkRenameTargets().length]), 'bulk_rename', bulkRenameTargets().length === 0);
                    }
                    catalogMenu += menuDivider();
                    catalogMenu += menuItem('bi-scissors', L.cut, 'cut');
                    catalogMenu += menuItem('bi-copy', L.copy, 'copy');

                    // Paste into the group under the pointer, without having to
                    // walk into it first -- the same shortcut a folder offers.
                    if (item.kind === 'group') {
                        catalogMenu += menuItem('bi-clipboard-check', L.paste, 'paste_into', catalogMulti || (!state.clipboard) || (state.clipboard.area !== 'catalog'));
                    } else {
                        catalogMenu += menuItem('bi-clipboard-check', L.paste, 'paste', catalogPasteOff);
                    }

                    // One window for both, because in the store they are two
                    // different results rather than one with a modifier key:
                    // moving takes the product out of the group it is shown in,
                    // adding leaves it there as well.
                    catalogMenu += menuItem('bi-folder-symlink', transferMenuLabel(), 'move_to');
                    catalogMenu += menuItem('bi-clipboard', L.copy_name, 'copy_name');

                    catalogMenu += menuItem('bi-copy', L.duplicate, 'duplicate', catalogMulti || !itemDuplicable(item));
                    catalogMenu += menuDivider();

                    // Taking the picked rows out as a file. A product stands
                    // for itself and a group for everything under it, so this
                    // is offered on both -- and on a mixed selection, which is
                    // the union of the two.
                    catalogMenu += menuSubmenu('bi-box-arrow-up', L.export_selected,
                        menuItem('bi-filetype-csv', L.export_csv, 'catalog_export_csv') +
                        menuItem('bi-file-earmark-excel', L.export_excel, 'catalog_export_xlsx'));

                    catalogMenu += menuDivider();

                    // Bulk edit for a selection that is all products. Groups
                    // are left out on purpose: a group has no price, no stock
                    // and no barcode, and a panel that silently skipped half
                    // the selection would be worse than not being offered.
                    if (catalogMulti && selected.every(function (each) { return each.kind === 'product'; })) {
                        catalogMenu += menuItem('bi-ui-checks', fmt(L.bulk_edit_products_menu, [selected.length]), 'bulk_products');
                        catalogMenu += menuDivider();
                    }

                    // Taking a product out of the group it is shown in is not a
                    // delete and must not read like one: the product keeps
                    // selling and keeps its other groups. Only offered where
                    // there is a group to leave -- not in the flat list.
                    if ((item.kind === 'product') && (state.groupId > 0) && (state.allFilter === '')) {
                        catalogMenu += menuItem('bi-box-arrow-left', L.remove_from_group, 'catalog_membership_remove', catalogMulti);
                    }

                    catalogMenu += menuItem('bi-trash', L.delete, 'catalog_recycle');
                    catalogMenu += menuDivider();
                }
            }

            if (!item) {

                // How the grid is drawn and how it is ordered belong to the
                // screen, not to what is listed on it, so the store carries the
                // same two submenus as the folders do. Size is left out: a
                // group has none, and price is what an operator sorts a catalog
                // by instead.
                catalogMenu += menuSubmenu('bi-grid-3x3-gap', L.view_label,
                    menuCheckItem(L.grid_view, 'view_grid', state.viewType === 'grid') +
                    menuCheckItem(L.list_view, 'view_list', state.viewType === 'list') +
                    tileSizeMenu() + columnSubmenu());

                catalogMenu += menuSubmenu('bi-sort-alpha-down', L.sort_by,
                    menuCheckItem(L.name, 'sort_name', state.sort.key === 'name') +
                    menuCheckItem(L.type, 'sort_type', state.sort.key === 'type') +
                    menuCheckItem(L.price, 'sort_price', state.sort.key === 'price') +
                    menuCheckItem(L.last_modified, 'sort_timestamp', state.sort.key === 'timestamp') +
                    menuDivider() +
                    menuCheckItem(L.ascending, 'sort_asc', state.sort.dir === 'asc') +
                    menuCheckItem(L.descending, 'sort_desc', state.sort.dir === 'desc'));

                catalogMenu += menuDivider();

                if (insideCatalogBin()) {

                    catalogMenu += menuItem('bi-x-octagon', L.empty_bin, 'catalog_purge_all', state.recycle.count === 0);
                    catalogMenu += menuDivider();

                } else {

                    var catalogActions = newActions();

                    catalogMenu += menuSubmenu('bi-plus-lg', L.new_label,
                        menuItem('bi-boxes', L.new_product_group, 'new_folder', !catalogActions.folder) +
                        menuItem('bi-box2-heart', L.new_product, 'new_page_link', !catalogActions.page),
                        !catalogActions.folder && !catalogActions.page);

                    catalogMenu += menuItem('bi-clipboard-check', L.paste, 'paste', catalogPasteOff);

                    // The same file the toolbar offers, for the operator who
                    // works from the right button rather than the top of the
                    // screen. Nothing is picked out here, so it is the whole
                    // catalog.
                    catalogMenu += menuSubmenu('bi-box-arrow-up', L.export_products,
                        menuItem('bi-filetype-csv', L.export_csv, 'catalog_export_all_csv') +
                        menuItem('bi-file-earmark-excel', L.export_excel, 'catalog_export_all_xlsx'));

                    catalogMenu += menuDivider();
                }
            }

            catalogMenu += menuItem('bi-arrow-clockwise', L.refresh, 'refresh');

            // Select all is offered wherever there is a list to select, the
            // clicked row included: a right click sets the selection to that
            // one row, and taking the whole grid from there is the quickest
            // way to act on everything in the group.
            var catalogAllSelected = (state.ordered.length > 0) && (selected.length === state.ordered.length);

            catalogMenu += menuItem(catalogAllSelected ? 'bi-square' : 'bi-check2-square', catalogAllSelected ? L.deselect_all : L.select_all, 'select_all_toggle', state.ordered.length === 0);

            menuElement.innerHTML = catalogMenu;
            menuElement.setAttribute('data-target-source', 'content');
            menuElement.setAttribute('data-target-kind', item ? item.kind : '');
            menuElement.setAttribute('data-target-id', item ? item.id : '');
            positionMenu(event);
            return;
        }

        // The shared report has nothing to act on; the backup browser has
        // disk entries, which can do a few things but not the database ones.
        if (insideShared()) {
            menuElement.innerHTML = menuItem('bi-arrow-clockwise', L.refresh, 'refresh');
            menuElement.setAttribute('data-target-source', 'content');
            menuElement.setAttribute('data-target-kind', '');
            menuElement.setAttribute('data-target-id', '');
            positionMenu(event);
            return;
        }

        // Short links answer to a menu of their own. Everything on the folder
        // menu below is aimed at the folder tables, and a short link is not in
        // them: it has no folder to be moved into, nothing to compress, and no
        // bin to be restored from.
        if (insideShortLinks()) {

            var shortLinkMenu = '';
            var shortLinkMulti = (selected.length > 1);
            var shortLinkPasteOff = ((!state.clipboard) || (state.clipboard.area !== 'short_links'));

            if (item) {

                shortLinkMenu += menuItem('bi-box-arrow-up-right', L.visit, 'short_link_visit', shortLinkMulti);
                shortLinkMenu += menuItem('bi-pencil', L.edit, 'short_link_edit', shortLinkMulti);
                shortLinkMenu += menuItem('bi-input-cursor-text', L.rename + ' (F2)', 'rename', shortLinkMulti);
                shortLinkMenu += menuDivider();
                shortLinkMenu += menuItem('bi-copy', L.copy, 'short_link_copy');
                shortLinkMenu += menuItem('bi-clipboard-check', L.paste, 'short_link_paste', shortLinkPasteOff);
                shortLinkMenu += menuItem('bi-files', L.duplicate, 'short_link_duplicate');
                shortLinkMenu += menuDivider();
                shortLinkMenu += menuItem('bi-trash', L.delete, 'short_link_delete', selected.length === 0);

            } else {

                shortLinkMenu += menuSubmenu('bi-plus-lg', L.new_label, menuItem('bi-link-45deg', L.short_link, 'new_short_link'));
                shortLinkMenu += menuDivider();

                shortLinkMenu += menuSubmenu('bi-grid-3x3-gap', L.view_label,
                    menuCheckItem(L.grid_view, 'view_grid', state.viewType === 'grid') +
                    menuCheckItem(L.list_view, 'view_list', state.viewType === 'list') +
                    tileSizeMenu() + columnSubmenu());

                shortLinkMenu += menuSubmenu('bi-sort-alpha-down', L.sort_by,
                    menuCheckItem(L.name, 'sort_name', state.sort.key === 'name') +
                    menuCheckItem(L.last_modified, 'sort_timestamp', state.sort.key === 'timestamp') +
                    menuDivider() +
                    menuCheckItem(L.ascending, 'sort_asc', state.sort.dir === 'asc') +
                    menuCheckItem(L.descending, 'sort_desc', state.sort.dir === 'desc'));

                shortLinkMenu += menuDivider();
                shortLinkMenu += menuItem('bi-clipboard-check', L.paste, 'short_link_paste', shortLinkPasteOff);
                shortLinkMenu += menuItem('bi-arrow-clockwise', L.refresh, 'refresh');
                shortLinkMenu += menuItem(
                    ((state.ordered.length > 0) && (selected.length === state.ordered.length)) ? 'bi-square' : 'bi-check2-square',
                    ((state.ordered.length > 0) && (selected.length === state.ordered.length)) ? L.deselect_all : L.select_all,
                    'select_all_toggle', state.ordered.length === 0);
            }

            menuElement.innerHTML = shortLinkMenu;
            menuElement.setAttribute('data-target-source', 'content');
            menuElement.setAttribute('data-target-kind', item ? item.kind : '');
            menuElement.setAttribute('data-target-id', item ? item.id : '');
            positionMenu(event);
            return;
        }

        if (insideBackups()) {
            var backupMenu = '';

            // Taking a backup belongs at the top of this menu whether or not
            // the pointer was over an entry, because it is about the folder
            // rather than about anything in it.  Only at the top level: the
            // folders further in are the insides of one backup, and there is
            // nothing to take a backup of down there.
            if ((state.backupPath || '') === '') {
                backupMenu += menuItem('bi-database-add', L.create_backup, 'backup_create');
            }

            // Putting a file here is about the folder too, and it works at
            // every level: an archive belongs at the top, a replaced file
            // belongs inside the backup it came from.
            backupMenu += menuItem('bi-upload', L.upload_file, 'upload_link');
            backupMenu += menuDivider();

            if (item) {
                if (item.kind === 'folder') {
                    backupMenu += menuItem('bi-box-arrow-up-right', L.open_folder, 'backup_open', multiSelected(selected));
                    backupMenu += menuItem('bi-file-earmark-zip', L.compress_and_download, 'backup_zip', multiSelected(selected) || !state.backupZip);
                } else {
                    backupMenu += menuItem('bi-download', L.download, 'backup_download', multiSelected(selected));

                    if (item.type === 'zip') {
                        backupMenu += menuItem('bi-box-arrow-down', L.extract_here, 'backup_extract', multiSelected(selected) || !state.backupZip);
                    }
                }

                backupMenu += menuDivider();
                backupMenu += menuItem('bi-key', L.permissions, 'backup_permissions', multiSelected(selected));
                backupMenu += menuItem('bi-input-cursor-text', L.rename + ' (F2)', 'backup_rename', multiSelected(selected));
                backupMenu += menuItem('bi-copy', L.duplicate, 'backup_copy', multiSelected(selected));
                backupMenu += menuDivider();
                backupMenu += menuItem('bi-trash', L.delete, 'backup_delete', selected.length === 0);
            } else {
                var allBackupsSelected = (state.ordered.length > 0) && (selected.length === state.ordered.length);

                backupMenu += menuSubmenu('bi-grid-3x3-gap', L.view_label,
                    menuCheckItem(L.grid_view, 'view_grid', state.viewType === 'grid') +
                    menuCheckItem(L.list_view, 'view_list', state.viewType === 'list') +
                    tileSizeMenu() + columnSubmenu());

                backupMenu += menuSubmenu('bi-sort-alpha-down', L.sort_by,
                    menuCheckItem(L.name, 'sort_name', state.sort.key === 'name') +
                    menuCheckItem(L.size, 'sort_size', state.sort.key === 'size') +
                    menuCheckItem(L.last_modified, 'sort_timestamp', state.sort.key === 'timestamp') +
                    menuDivider() +
                    menuCheckItem(L.ascending, 'sort_asc', state.sort.dir === 'asc') +
                    menuCheckItem(L.descending, 'sort_desc', state.sort.dir === 'desc'));

                backupMenu += menuDivider();
                backupMenu += menuItem('bi-arrow-clockwise', L.refresh, 'refresh');
                backupMenu += menuItem(allBackupsSelected ? 'bi-square' : 'bi-check2-square', allBackupsSelected ? L.deselect_all : L.select_all, 'select_all_toggle', state.ordered.length === 0);
            }

            menuElement.innerHTML = backupMenu;
            menuElement.setAttribute('data-target-source', 'content');
            menuElement.setAttribute('data-target-kind', item ? item.kind : '');
            menuElement.setAttribute('data-target-id', item ? item.id : '');
            positionMenu(event);
            return;
        }

        if (!item) {
            var allSelected = (state.ordered.length > 0) && (selected.length === state.ordered.length);

            var viewMenu =
                menuCheckItem(L.grid_view, 'view_grid', state.viewType === 'grid') +
                menuCheckItem(L.list_view, 'view_list', state.viewType === 'list') +
                tileSizeMenu() + columnSubmenu();

            var sortMenu =
                menuCheckItem(L.name, 'sort_name', state.sort.key === 'name') +
                menuCheckItem(L.type, 'sort_type', state.sort.key === 'type') +
                menuCheckItem(L.size, 'sort_size', state.sort.key === 'size') +
                menuCheckItem(L.last_modified, 'sort_timestamp', state.sort.key === 'timestamp') +
                menuDivider() +
                menuCheckItem(L.ascending, 'sort_asc', state.sort.dir === 'asc') +
                menuCheckItem(L.descending, 'sort_desc', state.sort.dir === 'desc');

            html += menuSubmenu('bi-grid-3x3-gap', L.view_label, viewMenu);
            html += menuSubmenu('bi-sort-alpha-down', L.sort_by, sortMenu);
            html += menuItem('bi-arrow-clockwise', L.refresh, 'refresh');
            html += menuDivider();

            if (insideBin()) {
                html += menuItem(allSelected ? 'bi-square' : 'bi-check2-square', allSelected ? L.deselect_all : L.select_all, 'select_all_toggle', state.ordered.length === 0);
                html += menuDivider();
                html += menuItem('bi-x-octagon', L.empty_bin, 'empty_bin', state.recycle.count === 0);
            } else {
                var actions = newActions();

                var newMenu =
                    menuItem('bi-folder-plus', L.new_folder, 'new_folder', !actions.folder) +
                    menuItem('bi-upload', L.upload_file, 'upload_link', !actions.upload) +
                    menuItem('bi-file-earmark-plus', L.create_file, 'create_file_link', !actions.file) +
                    menuItem('bi-easel', L.new_image, 'new_image_link', !actions.image) +
                    (state.caps.can_create_pages ? menuItem('bi-window-plus', L.new_page, 'new_page_link', !actions.page) : '') +
                    (actions.short_link ? menuItem('bi-link-45deg', L.short_link, 'new_short_link') : '');

                var anyAction = actions.folder || actions.upload || actions.file || actions.page || actions.short_link;

                html += menuSubmenu('bi-plus-lg', L.new_label, newMenu, !anyAction);
                html += menuDivider();
                html += menuItem('bi-clipboard-check', L.paste, 'paste', !state.clipboard || !writable);
                html += menuItem(allSelected ? 'bi-square' : 'bi-check2-square', allSelected ? L.deselect_all : L.select_all, 'select_all_toggle', state.ordered.length === 0);
            }
        } else if (insideBin()) {

            // Items sitting in the bin: restore or delete for good.
            html += menuItem('bi-arrow-counterclockwise', L.restore, 'restore');
            html += menuItem('bi-trash', L.delete_permanently, 'delete_permanent', selected.length === 0);

            // A binned short link has nothing to open: its address stopped
            // answering the moment it was deleted, which is what the bin means.
            if (!item.short_link) {
                html += menuDivider();
                html += menuItem('bi-box-arrow-up-right', item.kind === 'folder' ? L.open_folder : L.open_in_new_tab, 'open', selected.length > 1);
            }
        } else {
            var multi = selected.length > 1;

            html += menuItem('bi-box-arrow-up-right', item.kind === 'folder' ? L.open_folder : L.open_in_new_tab, 'open', multi);
            html += menuItem('bi-eye', L.quick_look + ' (Space)', 'quick_look', multi || !quickLookable(item));

            if (item.kind === 'file') {
                html += menuItem('bi-pencil', L.edit, 'edit', multi || !item.can_edit);
                if (state.mode === 'all') {
                    html += menuItem('bi-folder2-open', L.open_containing_folder, 'open_containing', multi);
                }
                html += menuItem('bi-diagram-3', L.where_used, 'where_used', multi);
                html += menuItem('bi-file-earmark-arrow-down', L.download, 'download', multi);
                if (item.is_image && item.can_edit) {
                    html += menuItem('bi-brush', L.image_editor, 'image_editor', multi);
                    if (!item.optimized && OPTIMIZABLE_TYPES.indexOf(item.type) !== -1) {
                        html += menuItem('bi-fast-forward-circle', L.optimize_image, 'optimize', multi);
                    }
                    // Offered whatever the optimized flag says: compressed
                    // but still oversized is the case this one exists for.
                    if (itemResizable(item)) {
                        html += menuItem('bi-arrows-angle-contract', fmt(L.resize_optimize, [state.caps.resize_target]), 'resize', multi);
                    }
                    if (WEBP_SOURCE_TYPES.indexOf(item.type) !== -1) {
                        html += menuItem('bi-arrow-repeat', L.convert_webp, 'webp', multi);
                    }
                }

                // The design flag is a designer decision, so only designers
                // are offered it; the clicked file decides the direction.
                if (state.caps.is_designer) {
                    html += menuItem('bi-palette2', item.design ? L.design_off : L.design_on, item.design ? 'design_off' : 'design_on');
                }
            }

            if (item.kind === 'page') {
                html += menuItem('bi-pencil', L.edit, 'edit', multi);
                if (state.mode === 'all') {
                    html += menuItem('bi-folder2-open', L.open_containing_folder, 'open_containing', multi);
                }
            }

            if (item.kind === 'folder') {
                html += menuItem('bi-pencil', L.edit, 'edit', multi || !item.can_edit);
                html += menuItem('bi-sliders', L.folder_settings, 'folder_settings', multi || !item.can_edit);
                html += menuItem('bi-shield-lock', L.access_permissions, 'access', multi || !item.can_edit);
                html += menuItem(isPinned(item.id) ? 'bi-pin-angle-fill' : 'bi-pin-angle',
                    isPinned(item.id) ? L.unpin_here : L.pin_here, 'pin_toggle', multi);
            }

            // The address of the thing under the pointer, in the shape the
            // operator is about to paste it into. A file's address is the one
            // question this screen could not answer at all, and the answer was
            // a trip to the classic files screen.
            if ((item.kind === 'file') || (item.kind === 'page')) {
                html += menuSubmenu('bi-link-45deg', L.copy_address,
                    menuItem('bi-globe2', L.copy_full_url, 'copy_url_full') +
                    menuItem('bi-slash-lg', L.copy_site_path, 'copy_url_path') +
                    menuItem('bi-code-slash', L.copy_as_html, 'copy_url_html'));
            }

            html += menuDivider();
            html += menuItem('bi-scissors', L.cut, 'cut', !itemsMovable(selected));
            html += menuItem('bi-copy', L.copy, 'copy', !itemsCopyable(selected));

            // Picking the destination instead of dragging onto it. Cut and
            // paste reach the same place in two steps and a walk; these do it
            // in one, which is what a folder the operator cannot see needs.
            html += menuItem('bi-folder-symlink', L.move_to, 'move_to', !itemsMovable(selected));
            html += menuItem('bi-folder-plus', L.copy_to, 'copy_to', !itemsCopyable(selected));

            if (item.kind === 'folder') {
                html += menuItem('bi-clipboard-check', L.paste, 'paste_into', !state.clipboard || multi || !item.can_edit);
            }

            html += menuItem('bi-input-cursor-text', L.rename + ' (F2)', 'rename', multi || !itemRenamable(item));

            // Offered only on a selection: for one row the inline rename is
            // quicker than a panel, and a rule over one name is a rule
            // nobody needed to write.
            if (multi) {
                html += menuItem('bi-input-cursor', fmt(L.bulk_rename_menu, [bulkRenameTargets().length]), 'bulk_rename', bulkRenameTargets().length === 0);
            }
            html += menuItem('bi-copy', L.duplicate, 'duplicate', multi || !itemDuplicable(item));

            // Archives. Compressing needs something with bytes behind it, so
            // a selection of pages alone is not offered it.
            var compressible = selected.filter(function (each) {
                return ((each.kind === 'file') || (each.kind === 'folder')) && each.can_edit;
            });

            html += menuDivider();
            html += menuItem('bi-file-earmark-zip', fmt(L.compress_zip, [compressible.length]), 'zip_create', (compressible.length === 0) || !writable);

            if (!multi && (item.kind === 'file') && (item.type === 'zip') && item.can_edit) {
                html += menuItem('bi-box-arrow-down', L.extract_here, 'zip_extract');
            }

            // Bulk actions for a multi-page selection.
            if (multi && selected.every(function (each) { return each.kind === 'page'; })) {
                var editablePages = selected.filter(function (each) { return each.can_edit !== false; });
                html += menuDivider();
                html += menuItem('bi-ui-checks', fmt(L.bulk_edit_pages_menu, [editablePages.length]), 'bulk_pages', editablePages.length === 0);
            }

            // Bulk actions for a multi-file selection.
            if (multi && selected.every(function (each) { return each.kind === 'file'; })) {
                var optimizable = selected.filter(function (each) {
                    return each.can_edit && !each.optimized && OPTIMIZABLE_TYPES.indexOf(each.type) !== -1;
                });
                var convertible = selected.filter(function (each) {
                    return each.can_edit && WEBP_SOURCE_TYPES.indexOf(each.type) !== -1;
                });
                var resizable = selected.filter(function (each) {
                    return each.can_edit && itemResizable(each);
                });
                var editableFiles = selected.filter(function (each) { return each.can_edit; });
                html += menuDivider();
                html += menuItem('bi-ui-checks', fmt(L.bulk_edit_files_menu, [editableFiles.length]), 'bulk_files', editableFiles.length === 0);
                html += menuItem('bi-fast-forward-circle', fmt(L.optimize_all, [optimizable.length]), 'bulk_optimize', optimizable.length === 0);
                html += menuItem('bi-arrows-angle-contract', fmt(L.resize_optimize_all, [state.caps.resize_target, resizable.length]), 'bulk_resize', resizable.length === 0);
                html += menuItem('bi-arrow-repeat', fmt(L.convert_webp_all, [convertible.length]), 'bulk_webp', convertible.length === 0);
            }

            html += menuDivider();
            html += menuItem('bi-trash', L.delete, 'delete', !itemsDeletable(selected));
        }

        menuElement.innerHTML = html;
        menuElement.setAttribute('data-target-source', 'content');
        menuElement.setAttribute('data-target-kind', item ? item.kind : '');
        menuElement.setAttribute('data-target-id', item ? item.id : '');
        positionMenu(event);
    }

    // The tree gets its own menu: every entry acts on the node, wherever the
    // content area currently stands.
    function showTreeMenu(event, node) {
        var html = '';

        // Every entry below acts on the folder tables. In the store the node is
        // a product group, so it gets the two that do work here rather than a
        // list aimed at tables this row does not live in.
        if (insideCatalog()) {

            var treePasteOff = (!state.clipboard) || (state.clipboard.area !== 'catalog');

            menuElement.innerHTML =
                menuItem('bi-box-arrow-up-right', L.open_folder, 'tree_open') +
                menuItem(isPinned(node.id) ? 'bi-pin-angle-fill' : 'bi-pin-angle',
                    isPinned(node.id) ? L.unpin_here : L.pin_here, 'tree_pin_toggle', node.isRoot) +
                // Open and Edit are the two things done to the row itself, so
                // they sit together at the top; what follows makes or moves
                // something else.
                menuItem('bi-pencil', L.edit, 'tree_catalog_edit') +
                menuItem('bi-boxes', L.new_product_group, 'tree_catalog_new_group') +
                menuDivider() +
                menuItem('bi-clipboard-check', L.paste, 'tree_paste', treePasteOff) +
                menuItem('bi-input-cursor-text', L.rename, 'tree_rename', node.isRoot) +
                menuItem('bi-copy', L.duplicate, 'tree_catalog_duplicate', node.isRoot) +
                (node.enabled
                    ? menuItem('bi-eye-slash', L.unpublish_item, 'tree_catalog_unpublish', node.isRoot)
                    : menuItem('bi-eye', L.publish_item, 'tree_catalog_publish', node.isRoot)) +
                menuDivider() +
                // Only the root is refused. A group with groups under it goes to
                // the bin with all of them, the way a folder does, and comes
                // back the same way.
                menuItem('bi-trash', L.delete, 'tree_catalog_recycle', node.isRoot) +
                menuDivider() +
                menuItem('bi-arrow-clockwise', L.refresh, 'refresh');

            menuElement.setAttribute('data-target-source', 'tree');
            menuElement.setAttribute('data-target-kind', 'group');
            menuElement.setAttribute('data-target-id', node.id);
            positionMenu(event);
            return;
        }

        html += menuItem('bi-box-arrow-up-right', L.open_folder, 'tree_open');
        html += menuItem(isPinned(node.id) ? 'bi-pin-angle-fill' : 'bi-pin-angle',
            isPinned(node.id) ? L.unpin_here : L.pin_here, 'tree_pin_toggle', node.isRoot);
        html += menuItem('bi-folder-plus', L.new_folder, 'tree_new_folder', !node.canEdit);
        html += menuItem('bi-upload', L.upload_file, 'tree_upload', !node.canEdit);
        html += menuDivider();
        html += menuItem('bi-clipboard-check', L.paste, 'tree_paste', !state.clipboard || !node.canEdit);
        html += menuItem('bi-input-cursor-text', L.rename, 'tree_rename', !node.canEdit || node.isRoot);
        html += menuItem('bi-sliders', L.folder_settings, 'tree_settings', !node.canEdit || node.isRoot);
        html += menuItem('bi-shield-lock', L.access_permissions, 'tree_access', !node.canEdit);
        html += menuItem('bi-copy', L.duplicate, 'tree_duplicate', !node.canEdit || node.isRoot);
        html += menuDivider();
        html += menuItem('bi-trash', L.delete, 'tree_delete', !node.canEdit || node.isRoot);

        menuElement.innerHTML = html;
        menuElement.setAttribute('data-target-source', 'tree');
        menuElement.setAttribute('data-target-kind', 'folder');
        menuElement.setAttribute('data-target-id', node.id);
        positionMenu(event);
    }

    // The resize button follows view_files.php: shown while the image is
    // wider or taller than the configured trigger, optimized or not.
    function itemResizable(item) {
        return item.kind === 'file' && item.is_image && item.can_edit &&
            OPTIMIZABLE_TYPES.indexOf(item.type) !== -1 &&
            item.image_width > 0 &&
            Math.max(item.image_width, item.image_height) > (state.caps.resize_trigger || 0);
    }

    function multiSelected(selected) { return selected.length > 1; }

    function itemRenamable(item) {
        if (insideCatalog()) { return (!insideCatalogBin()) && ((item.kind === 'group') || (item.kind === 'product')); }

        if (item.kind === 'file') { return !!item.can_edit; }
        if (item.kind === 'folder') { return !!item.can_edit; }
        return canWriteCurrent();
    }

    function itemDuplicable(item) {
        // Copying a group makes a second group holding the same products.
        // Copying a product would only add it to a group it is already in,
        // which is nothing, so it is not offered.
        if (insideCatalog()) { return ((!insideCatalogBin()) && (item.kind === 'group')); }

        if (item.kind === 'folder') { return !!item.can_edit; }
        if (item.kind === 'page') { return !!state.caps.can_create_pages; }
        return !!item.can_edit;
    }

    function itemsMovable(items) {
        if (items.length === 0) { return false; }
        return items.every(function (item) {
            if (item.kind === 'file') { return item.can_edit; }
            if (item.kind === 'folder') { return item.can_edit && !item.is_root; }
            return true;
        });
    }

    function itemsCopyable(items) {
        if (items.length === 0) { return false; }
        return items.every(function (item) {
            if (item.kind === 'folder') { return item.can_edit && !item.is_root; }
            if (item.kind === 'file') { return item.can_edit; }
            return state.caps.can_create_pages;
        });
    }

    function itemsDeletable(items) {
        if (items.length === 0) { return false; }
        return items.every(function (item) {
            if (item.kind === 'file') { return item.can_edit; }
            if (item.kind === 'folder') { return item.can_edit; }
            return state.caps.can_delete_pages;
        });
    }

    // What a tree row says about itself. Read in one place: the row is turned
    // into a node twice -- once when the menu opens and once when an entry in
    // it runs -- and the two reading it differently is how the menu ended up
    // offering Publish for a group that was already published.
    function treeNodeFromRow(row) {

        if (!row) { return null; }

        return {
            id: parseInt(row.getAttribute('data-folder-id'), 10),
            name: row.getAttribute('data-name') || '',
            parentId: parseInt(row.getAttribute('data-parent-id'), 10) || 0,
            canEdit: row.getAttribute('data-can-edit') === '1',
            isRoot: row.getAttribute('data-is-root') === '1',
            empty: row.getAttribute('data-empty') === '1',
            // Folder rows carry no such flag, so they read as published; the
            // entry that uses it is only drawn in the store anyway.
            enabled: row.getAttribute('data-enabled') !== '0',
            hasChildren: row.getAttribute('data-has-children') === '1',
            row: row
        };
    }

    function treeNodeFromMenu() {
        var id = parseInt(menuElement.getAttribute('data-target-id'), 10);
        return treeNodeFromRow(document.querySelector('#explorer_tree_pane .tree-row[data-folder-id="' + id + '"]'));
    }

    function runMenuAction(action) {
        menuActionAt = Date.now();
        var source = menuElement.getAttribute('data-target-source');
        var kind = menuElement.getAttribute('data-target-kind');
        var id = menuElement.getAttribute('data-target-id');
        var item = (source === 'content' && kind) ? findItem(kind, id) : null;
        var node = (source === 'tree') ? treeNodeFromMenu() : null;
        hideMenu();

        // Column names are not known when this switch is written -- the set
        // depends on the area and the viewport -- so they are answered here
        // rather than in a default that would have to guess what else fell
        // through to it.
        if (action.indexOf('col_') === 0) { columnToggle(action.slice(4)); return; }

        switch (action) {
            case 'catalog_open': activateItem(item); break;
            case 'catalog_publish': catalogSetEnabled(catalogTargets(item), true); break;
            case 'catalog_unpublish': catalogSetEnabled(catalogTargets(item), false); break;
            case 'tree_catalog_publish':
                if (node) { catalogSetEnabled([{ kind: 'group', id: node.id, enabled: false }], true); }
                break;
            case 'tree_catalog_unpublish':
                if (node) { catalogSetEnabled([{ kind: 'group', id: node.id, enabled: true }], false); }
                break;
            case 'catalog_recycle': catalogRecycle(catalogTargets(item)); break;
            case 'catalog_export_csv':
            case 'catalog_export_xlsx':
                var exportTargets = productExportTargets(item);
                if ((exportTargets.ids.length === 0) && (exportTargets.groups.length === 0)) { break; }
                submitProductExport((action === 'catalog_export_xlsx') ? 'xlsx' : 'csv', exportTargets.ids, exportTargets.groups);
                break;
            case 'catalog_export_all_csv': submitProductExport('csv', [], []); break;
            case 'catalog_export_all_xlsx': submitProductExport('xlsx', [], []); break;
            case 'catalog_restore': catalogRestore(catalogTargets(item)); break;
            case 'catalog_purge': catalogPurge(catalogTargets(item)); break;
            case 'catalog_purge_all': catalogPurge([], true); break;
            case 'catalog_membership_remove': catalogRemoveFromGroup(item); break;
            case 'tree_catalog_recycle':
                if (node) { catalogRecycle([{ kind: 'group', id: node.id, name: node.name }]); }
                break;
            case 'catalog_edit':
                if (item) { window.location.href = item.edit_url + '&send_to=' + encodeURIComponent(currentSendTo()); }
                break;
            case 'new_folder': createFolder(); break;
            case 'upload_link': openUploadModal(); break;
            case 'create_file_link': createFile(); break;
            case 'new_image_link': createBlankImage(); break;
            case 'new_page_link': window.location.href = document.getElementById('new_page_button').href; break;
            case 'select_all_toggle':
                if (selectedItems().length === state.ordered.length) { setSelection([]); } else { setSelection(state.ordered.slice()); }
                break;
            case 'refresh': reload(); break;
            case 'view_grid': state.viewType = 'grid'; state.viewTypeExplicit = true; reload(); break;
            case 'view_list': state.viewType = 'list'; state.viewTypeExplicit = true; reload(); break;
            case 'columns_reset': columnsReset(); break;
            case 'tile_small': setTileSize('small'); break;
            case 'tile_medium': setTileSize('medium'); break;
            case 'tile_large': setTileSize('large'); break;
            case 'tile_xlarge': setTileSize('xlarge'); break;
            case 'sort_name': case 'sort_type': case 'sort_size': case 'sort_price': case 'sort_timestamp':
                state.sort.key = action.substring(5);
                renderContent();
                applySelectionClasses();
                break;
            case 'sort_asc': state.sort.dir = 'asc'; renderContent(); applySelectionClasses(); break;
            case 'sort_desc': state.sort.dir = 'desc'; renderContent(); applySelectionClasses(); break;
            case 'restore': restoreSelection(); break;
            case 'delete_permanent': permanentDeleteSelection(); break;
            case 'empty_bin': emptyRecycleBin(); break;
            case 'open': openItem(item); break;
            case 'quick_look': if (item) { openQuickLook(item); } break;
            // Answered in the panel, which is opened first if it is not on
            // screen: the report is about the file that is being looked at.
            case 'pin_toggle': if (item) { togglePin(item.id, item.name); } break;
            case 'tree_pin_toggle': if (node) { togglePin(node.id, node.name); } break;
            case 'where_used':
                if (item) {
                    setSelection([item], item);
                    window.requestAnimationFrame(function () {
                        var pane = document.getElementById('explorer_preview_pane');
                        pane.classList.remove('pane-hidden');
                        syncPaneButtons();
                        loadFileUsage(item, pane.querySelector('#preview_usage'));
                    });
                }
                break;
            case 'quick_edit_price': if (item) { quickEditFromMenu(item, 'price'); } break;
            case 'quick_edit_quantity': if (item) { quickEditFromMenu(item, 'quantity'); } break;
            case 'quick_edit_short': if (item) { quickEditFromMenu(item, 'short_description'); } break;
            case 'open_containing': if (item && item.folder_id) { exitAllFiles(item.folder_id); } break;
            case 'edit':
                if (!item) { break; }
                if (item.kind === 'folder') { window.location.href = 'edit_folder.php?id=' + item.id + '&send_to=' + encodeURIComponent(currentSendTo()); }
                else if (item.kind === 'file') { openFileEditor(item); }
                else { window.location.href = item.edit_url + '&send_to=' + encodeURIComponent(currentSendTo()); }
                break;
            case 'download':
                if (item) {
                    var link = document.createElement('a');
                    link.href = item.url;
                    link.download = item.name;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }
                break;
            case 'image_editor':
                if (item) { openImageEditorFor(item); }
                break;
            case 'optimize':
                // The same run the multi-select uses, for one file: it used
                // to leave for optimize.php and come back through the files
                // screen, which is gone.
                if (item) { runBulkOptimize([{ id: item.id }], 'optimize'); }
                break;
            case 'resize':
                if (item) { runBulkOptimize([{ id: item.id }], 'resize'); }
                break;
            case 'bulk_resize':
                runBulkOptimize(selectedItems().filter(itemResizable).map(function (each) { return { id: each.id }; }), 'resize');
                break;
            case 'access': if (item) { openAccessPanel(item); } break;
            case 'folder_settings': if (item) { openFolderSettings(item.id); } break;
            case 'webp':
                if (item) { runWebpConvert([{ id: item.id }]); }
                break;
            case 'bulk_webp':
                runWebpConvert(selectedItems().filter(function (each) {
                    return each.kind === 'file' && each.can_edit && WEBP_SOURCE_TYPES.indexOf(each.type) !== -1;
                }).map(function (each) { return { id: each.id }; }));
                break;
            case 'bulk_pages':
                openBulkPagesPanel(selectedItems().filter(function (each) { return each.kind === 'page'; }));
                break;
            case 'bulk_files':
                openBulkFilesPanel(selectedItems().filter(function (each) { return (each.kind === 'file') && each.can_edit; }));
                break;
            case 'bulk_products':
                // catalogTargets() rather than the selection alone: the store's
                // rule is "the selection, or the row that was clicked", and
                // every other entry on that menu follows it.
                openBulkProductsPanel(catalogTargets(item).filter(function (each) { return each.kind === 'product'; }));
                break;
            case 'new_short_link': openShortLinkWizard(); break;
            case 'short_link_visit': if (item) { window.open(item.url, '_blank'); } break;
            case 'short_link_edit': if (item) { openShortLinkWizard(item); } break;
            // catalogTargets() is "the selection, or the row that was clicked":
            // the same rule the store uses. Written out longhand here it read
            // `selected`, which this function does not have -- so copy,
            // duplicate and delete all threw before they did anything.
            case 'short_link_copy': copyShortLinks(catalogTargets(item)); break;
            case 'short_link_paste': pasteShortLinks(); break;
            case 'short_link_duplicate': duplicateShortLinks(catalogTargets(item)); break;
            case 'short_link_delete': deleteShortLinks(catalogTargets(item)); break;
            case 'backup_create': createBackup(); break;
            case 'backup_open': if (item) { enterBackups(item.path); } break;
            case 'backup_download': if (item) { window.location.href = backupDownloadUrl(item.path); } break;
            case 'backup_zip': if (item) { compressBackup(item); } break;
            case 'backup_rename': if (item) { beginBackupRename(item); } break;
            case 'backup_copy': if (item) { backupAction('explorer_backup_copy', item); } break;
            case 'backup_permissions': if (item) { openPermissionsPanel(item); } break;
            case 'backup_extract':
                if (item) {
                    toast(L.extracting + '...');
                    backupAction('explorer_backup_extract', item);
                }
                break;
            case 'backup_delete':
                (function () {
                    var targets = selectedItems();

                    if (targets.length === 0) { return; }

                    // No bin out here: the recycle bin is a folder inside the
                    // site and these files are not in the site.
                    if (!window.confirm(fmt(L.backup_delete_confirm, [targets.length]))) { return; }

                    var remaining = targets.length;

                    targets.forEach(function (target) {
                        api({ type: 'explorer_backup_delete', path: target.path }, function (response) {
                            remaining--;

                            if (response.status === 'error') { toast(response.message || L.request_failed, false); }
                            if (remaining === 0) { reload(); }
                        });
                    });
                })();
                break;
            case 'zip_create':
                (function () {
                    var parts = selectedItems().filter(function (each) {
                        return ((each.kind === 'file') || (each.kind === 'folder')) && each.can_edit;
                    });

                    if (parts.length === 0) { return; }

                    // One item names the archive after itself, several after
                    // the folder they are sitting in - what a desktop does.
                    var suggested = (parts.length === 1)
                        ? parts[0].name.replace(/\.[^.]+$/, '')
                        : (state.current ? state.current.name : L.archive_name);

                    toast(L.compressing + '...');

                    api({
                        type: 'explorer_zip_create',
                        target_folder_id: state.folderId,
                        name: suggested,
                        items: parts.map(function (each) { return { kind: each.kind, id: each.id }; })
                    }, function (response) {
                        toast(response.message || L.request_failed, response.status !== 'error');

                        if ((response.status !== 'error') && response.created && response.created.length > 0) {
                            if (state.recycle.available) {
                                pushUndo({ undo: { type: 'bin', items: response.created }, redo: null });
                            }

                            // The archive lands among everything else in the
                            // folder, so it is selected and put straight into
                            // rename: the operator names it while they still
                            // know which one it is.
                            state.pendingRename = { kind: 'file', id: response.created[0].id };
                        }

                        reload();
                    });
                })();
                break;
            case 'zip_extract':
                if (item) {
                    if (!window.confirm(fmt(L.extract_confirm, [item.name]))) { break; }

                    toast(L.extracting + '...');

                    api({ type: 'explorer_zip_extract', item_id: item.id }, function (response) {
                        toast(response.message || L.request_failed, response.status !== 'error');

                        if ((response.status !== 'error') && response.created && response.created.length > 0 && state.recycle.available) {
                            pushUndo({ undo: { type: 'bin', items: response.created }, redo: null });
                        }

                        reload();
                    });
                }
                break;
            case 'design_on':
            case 'design_off':
                (function () {
                    var files = selectedItems().filter(function (each) { return each.kind === 'file'; });

                    if (files.length === 0) { return; }

                    api({
                        type: 'explorer_files_design',
                        design: (action === 'design_on') ? '1' : '0',
                        items: files.map(function (each) { return { id: each.id }; })
                    }, function (response) {
                        toast(response.message || L.request_failed, response.status !== 'error');
                        reload();
                    });
                })();
                break;
            case 'cut': clipboardSet('cut'); break;
            case 'copy': clipboardSet('copy'); break;
            case 'paste': clipboardPaste(insideCatalog() ? state.groupId : state.folderId); break;
            case 'paste_into': if (item) { clipboardPaste(item.id); } break;
            // The destination window works on what is picked out, and a right
            // click has already made the row under the pointer the selection.
            case 'move_to': openTransferDialog(transferSourceItems(), false); break;
            case 'copy_to': openTransferDialog(transferSourceItems(), true); break;
            case 'copy_url_full': copyAddresses(selectedItems(), 'full'); break;
            case 'copy_url_path': copyAddresses(selectedItems(), 'path'); break;
            case 'copy_url_html': copyAddresses(selectedItems(), 'html'); break;
            case 'copy_name':
                var namesToCopy = selectedItems().map(function (each) { return each.name || ''; }).filter(function (each) { return each !== ''; });
                if (namesToCopy.length > 0) { copyText(namesToCopy.join('\n'), L.copied); }
                break;
            case 'rename': if (item) { beginRename(item); } break;
            case 'bulk_rename': openBulkRenamePanel(); break;
            case 'duplicate': if (item) { duplicateItem(item); } break;
            case 'delete': deleteSelection(); break;
            case 'bulk_optimize':
                runBulkOptimize(selectedItems().filter(function (each) {
                    return each.kind === 'file' && each.can_edit && !each.optimized && OPTIMIZABLE_TYPES.indexOf(each.type) !== -1;
                }).map(function (each) { return { id: each.id }; }), 'optimize');
                break;

            case 'tree_open': if (node) { load(node.id, highlightTree); } break;

            // The tree row carries the group id, which is all the edit screen
            // needs -- the group does not have to be in the listing for this.
            case 'tree_catalog_edit':
                if (node) { window.location.href = 'edit_product_group.php?id=' + node.id + '&send_to=' + encodeURIComponent(currentSendTo()); }
                break;
            case 'tree_new_folder': if (node) { createFolderInTree(node); } break;
            case 'tree_catalog_new_group': if (node) { createGroupInTree(node); } break;
            case 'tree_catalog_duplicate':
                if (node) {
                    api({
                        type: 'explorer_catalog_paste',
                        mode: 'copy',
                        target_group_id: (node.parentId || 0),
                        items: [{ kind: 'group', id: node.id }]
                    }, function (response) {
                        toast(response.message || L.request_failed, response.status !== 'error');
                        reload();
                    });
                }
                break;
            case 'tree_upload':
                if (node) {
                    openUploadModal();
                    // Right-clicking a folder in the tree means that folder,
                    // not the one the grid happens to be showing.
                    document.getElementById('upload_folder').value = String(node.id);
                }
                break;
            case 'tree_paste': if (node) { clipboardPaste(node.id); } break;
            case 'tree_rename': if (node) { beginTreeRename(node); } break;
            case 'tree_settings': if (node) { openFolderSettings(node.id); } break;
            case 'tree_access': if (node) { openAccessPanel({ id: node.id }); } break;
            case 'tree_duplicate':
                if (node) {
                    api({ type: 'explorer_paste', target_folder_id: node.parentId, items: [{ kind: 'folder', id: node.id }] }, function (response) {
                        toast(response.message || L.request_failed, response.status !== 'error');

                        if ((response.status !== 'error') && response.created && response.created.length > 0 && state.recycle.available) {
                            pushUndo({ undo: { type: 'bin', items: response.created }, redo: null });
                        }

                        reload();
                    });
                }
                break;
            case 'tree_delete':
                if (node) {
                    if (state.recycle.available && !insideBin()) {
                        // A folder that still has content asks for its name
                        // first; an empty one just goes (undo covers it).
                        if (!node.empty) { openDeleteFolderModal(node.id, 'bin'); break; }
                        api({ type: 'explorer_recycle_delete', items: [{ kind: 'folder', id: node.id }] }, function (response) {
                            toast(response.message || L.request_failed, response.status !== 'error');

                            if (response.status !== 'error') {
                                pushUndo({
                                    undo: { type: 'restore', items: [{ kind: 'folder', id: node.id }] },
                                    redo: { type: 'bin', items: [{ kind: 'folder', id: node.id }] }
                                });
                            }

                            if (String(node.id) === String(state.folderId)) { load(0, highlightTree); } else { reload(); }
                        });
                    } else {
                        openDeleteFolderModal(node.id);
                    }
                }
                break;
        }
    }

    // ── Undo / redo ─────────────────────────────────────────────────────
    //
    // Each entry describes how to reverse an operation ("undo") and how to
    // apply it again ("redo"). Everything runs through the same API calls
    // the original action used, so all server-side access checks apply to
    // undo and redo exactly as they did the first time.

    var undoStack = [];
    var redoStack = [];

    function pushUndo(entry) {
        undoStack.push(entry);
        if (undoStack.length > 20) { undoStack.shift(); }
        redoStack = [];
        return entry;
    }

    // Saying what happened and offering to take it back, in one notice.
    //
    // The button does not call undoLast(): by the time somebody reaches for it
    // they may have moved a second thing, and undoing the top of the stack
    // would then undo the wrong one. The entry itself is remembered and looked
    // up by identity, so the offer either takes back what the notice named or
    // does nothing at all.
    function undoEntry(entry) {

        var at = undoStack.indexOf(entry);

        if (at === -1) { toast(L.nothing_to_undo, false); return; }

        undoStack.splice(at, 1);

        runOperation(entry.undo, function () {
            if (entry.redo) { redoStack.push(entry); }
            toast(L.undone);
            reload();
        });
    }

    function undoToast(message, entry) {
        toast(message, true, (entry && entry.undo) ? { label: L.undo, run: function () { undoEntry(entry); } } : null);
    }

    function runOperation(op, done) {
        if (!op) { done && done(); return; }

        if (op.type === 'move-groups') {
            var groups = op.groups.slice();

            (function next() {
                var group = groups.shift();
                if (!group) { done && done(); return; }
                api({ type: 'explorer_move', target_folder_id: group.target, items: group.items }, function () { next(); });
            })();
            return;
        }

        // The catalog has its own pair: the flag and the published state travel
        // together, so it cannot ride on the folder bin's endpoints.
        // The server hands back the steps that put a paste back, expressed with
        // the endpoints that already exist, and they are replayed in order.
        if (op.type === 'catalog_steps') {

            var steps = (op.steps || []).slice();

            (function next() {

                var step = steps.shift();

                if (!step) { done && done(); return; }

                if (step.type === 'paste') {
                    api({
                        type: 'explorer_catalog_paste',
                        target_group_id: step.target_group_id,
                        source_group_id: step.source_group_id,
                        mode: step.mode,
                        items: step.items
                    }, function () { next(); });
                    return;
                }

                if (step.type === 'recycle') {
                    api({ type: 'explorer_catalog_recycle', items: step.items }, function () { next(); });
                    return;
                }

                if (step.type === 'membership_remove') {
                    api({ type: 'explorer_catalog_membership_remove', product_id: step.product_id, group_id: step.group_id }, function () { next(); });
                    return;
                }

                next();
            })();

            return;
        }

        if (op.type === 'catalog_rename') {
            api({ type: 'explorer_catalog_rename', item_kind: op.kind, item_id: op.id, name: op.name }, function () { done && done(); });
            return;
        }

        if (op.type === 'catalog_enable') { api({ type: 'explorer_catalog_enable', items: op.items, enabled: op.enabled }, function () { done && done(); }); return; }
        if (op.type === 'catalog_field') { api({ type: 'explorer_catalog_quick_edit', item_id: op.id, field: op.field, value: op.value }, function () { done && done(); }); return; }

        // A batch rename is undone one name at a time through the same
        // endpoint that made it, in the reverse order: two rows may have
        // swapped names, and putting the second back first frees the name
        // the first one is waiting for.
        if (op.type === 'rename-many') {

            var renameSteps = (op.items || []).slice();

            (function nextRename() {

                var step = renameSteps.shift();

                if (!step) { done && done(); return; }

                api({ type: op.endpoint, item_kind: step.kind, item_id: step.id, name: step.name }, function () { nextRename(); });
            })();

            return;
        }

        // Back the way they were: at most two calls, one for the rows that were
        // published and one for the rows that were not.
        if (op.type === 'catalog_enable_restore') {

            var wasOn = op.states.filter(function (entry) { return entry.enabled; }).map(function (entry) { return { kind: entry.kind, id: entry.id }; });
            var wasOff = op.states.filter(function (entry) { return !entry.enabled; }).map(function (entry) { return { kind: entry.kind, id: entry.id }; });

            var steps = [];

            if (wasOn.length > 0) { steps.push({ items: wasOn, enabled: true }); }
            if (wasOff.length > 0) { steps.push({ items: wasOff, enabled: false }); }

            (function next() {

                var step = steps.shift();

                if (!step) { done && done(); return; }

                api({ type: 'explorer_catalog_enable', items: step.items, enabled: step.enabled }, function () { next(); });
            })();

            return;
        }

        if (op.type === 'catalog_restore') { api({ type: 'explorer_catalog_restore', items: op.items }, function () { done && done(); }); return; }
        if (op.type === 'catalog_recycle') { api({ type: 'explorer_catalog_recycle', items: op.items }, function () { done && done(); }); return; }

        if (op.type === 'restore') { api({ type: 'explorer_recycle_restore', items: op.items }, function () { done && done(); }); return; }
        if (op.type === 'bin') { api({ type: 'explorer_recycle_delete', items: op.items }, function () { done && done(); }); return; }
        if (op.type === 'rename') { api({ type: 'explorer_rename', item_kind: op.kind, item_id: op.id, name: op.name }, function () { done && done(); }); return; }

        done && done();
    }

    function undoLast() {
        var entry = undoStack.pop();

        if (!entry) { toast(L.nothing_to_undo, false); return; }

        runOperation(entry.undo, function () {
            if (entry.redo) { redoStack.push(entry); }
            toast(L.undone);
            reload();
        });
    }

    function redoLast() {
        var entry = redoStack.pop();

        if (!entry) { toast(L.nothing_to_redo, false); return; }

        runOperation(entry.redo, function () {
            undoStack.push(entry);
            toast(L.redone);
            reload();
        });
    }

    // Group items by the folder they were in when the operation ran, so the
    // inverse move can put every item back where it came from.
    function moveUndoGroups(items) {
        var groups = {};

        items.forEach(function (item) {
            var source = item.source_folder_id;
            if (!source || source <= 0) { return; }
            if (!groups[source]) { groups[source] = []; }
            groups[source].push({ kind: item.kind, id: item.id });
        });

        return Object.keys(groups).map(function (key) {
            return { target: parseInt(key, 10), items: groups[key] };
        });
    }

    function sourceFolderOf(item) {
        if (state.mode === 'all' && item.folder_id) { return item.folder_id; }
        return state.folderId;
    }

    // What the entry that opens the destination window should be called here.
    //
    // In the flat catalog lists there is no group to be taken out of, so the
    // window offers the add alone and the entry has to say so: an operator who
    // presses "Move to" and is handed "Add to group" has been told the wrong
    // thing about what is going to happen.
    function transferMenuLabel() {

        if (!insideCatalog()) { return L.move_to; }

        return ((state.groupId > 0) && (state.allFilter === '')) ? L.move_to : L.add_to_group;
    }

    // The selection in the shape a transfer wants it: the folder each item is
    // in today rides along, which is what lets an undo put a mixed selection
    // back folder by folder rather than piling it into one.
    function transferSourceItems() {
        return selectedItems().map(function (item) {
            return { kind: item.kind, id: item.id, name: item.name, source_folder_id: sourceFolderOf(item) };
        });
    }

    // Putting a set of items somewhere else.
    //
    // Dropping onto a folder and picking a destination from the "Move to"
    // window are the same operation asked for two different ways, so they run
    // the same code: the same endpoints, the same refusals, the same undo
    // entry. The items carry source_folder_id where they have one, which is
    // what lets a move be put back folder by folder.
    function transferItemsTo(targetId, items, copy, done) {

        function finish() { if (done) { done(); } }

        // In the store a drop is the same operation the clipboard performs, so
        // it goes through the same endpoint rather than a second one that could
        // drift from it.
        if (insideCatalog()) {

            api({
                type: 'explorer_catalog_paste',
                target_group_id: targetId,
                source_group_id: state.groupId || 0,
                mode: copy ? 'copy' : 'cut',
                items: items.map(function (each) { return { kind: each.kind, id: each.id }; })
            }, function (response) {

                if (response.status === 'error') { toast(response.message || L.request_failed, false); finish(); return; }

                var entry = ((response.undo_steps) && (response.undo_steps.length > 0))
                    ? pushUndo({ undo: { type: 'catalog_steps', steps: response.undo_steps }, redo: null })
                    : null;

                undoToast(response.message || L.moved, entry);
                reload();
                finish();
            });

            return;
        }

        // Dropping something onto the folder it is already in is a no-op.
        if (!copy && (targetId === state.folderId) && items.every(function (each) { return each.kind !== 'folder'; })) {
            var allHere = items.every(function (each) { return !!findItem(each.kind, each.id); });
            if (allHere) { finish(); return; }
        }

        if (copy) {

            api({ type: 'explorer_paste', target_folder_id: targetId, items: items }, function (response) {

                if (response.status === 'error') { toast(response.message || L.request_failed, false); finish(); return; }

                var entry = (response.created && (response.created.length > 0) && state.recycle.available)
                    ? pushUndo({ undo: { type: 'bin', items: response.created }, redo: null })
                    : null;

                undoToast(response.message || L.pasted, entry);
                reload();
                finish();
            });

            return;
        }

        var plainItems = items.map(function (each) { return { kind: each.kind, id: each.id }; });

        api({ type: 'explorer_move', target_folder_id: targetId, items: plainItems }, function (response) {

            if (response.status === 'error') { toast(response.message || L.request_failed, false); finish(); return; }

            var groups = moveUndoGroups(items);

            var entry = (groups.length > 0)
                ? pushUndo({
                    undo: { type: 'move-groups', groups: groups },
                    redo: { type: 'move-groups', groups: [{ target: targetId, items: plainItems }] }
                })
                : null;

            undoToast(response.message || L.moved, entry);
            reload();
            finish();
        });
    }

    // ── Choosing a destination ──────────────────────────────────────────

    var transferModal = null;
    var transferState = null;

    // Every place the selection may be put, in tree order with a depth.
    //
    // Both endpoints already exist and are already the authority on who may
    // write where -- explorer_folder_options runs check_edit_access on each
    // row, and the store's group list is the one the bulk panel uses. Asking
    // them rather than deriving a list from what happens to be on screen is
    // what makes a folder four levels down reachable at all.
    function loadTransferTargets(done) {

        if (insideCatalog()) {
            api({ type: 'explorer_bulk_product_options' }, function (response) {
                done((response.status === 'success') ? (response.groups || []) : null, response.message);
            });
            return;
        }

        api({ type: 'explorer_folder_options' }, function (response) {
            done((response.status === 'success') ? (response.folders || []) : null, response.message);
        });
    }

    // Destinations the operation would be refused at, worked out before the
    // list is drawn rather than after the request comes back.
    //
    // A folder cannot be moved into itself or into anything under it, and the
    // list arrives in tree order -- a row's subtree is everything that follows
    // it with a greater depth -- so the block is a single pass.
    function transferBlockedIds(targets, items, copy) {

        var blocked = {};

        var containerKind = insideCatalog() ? 'group' : 'folder';

        items.forEach(function (item) {

            if (item.kind !== containerKind) { return; }

            var at = -1;

            targets.forEach(function (target, index) {
                if ((at === -1) && (String(target.id) === String(item.id))) { at = index; }
            });

            if (at === -1) { return; }

            blocked[targets[at].id] = true;

            for (var index = at + 1; index < targets.length; index++) {
                if (targets[index].depth <= targets[at].depth) { break; }
                blocked[targets[index].id] = true;
            }
        });

        // Moving something into the place it already is does nothing. Copying
        // there is a duplicate, which is a real request, so it stays open.
        if (!copy) {
            blocked[insideCatalog() ? (state.groupId || 0) : state.folderId] = true;
        }

        return blocked;
    }

    function renderTransferTargets() {

        if (!transferState) { return; }

        var container = document.getElementById('transfer_targets');
        var filter = (document.getElementById('transfer_filter').value || '').trim().toLocaleLowerCase('tr');
        var here = insideCatalog() ? (state.groupId || 0) : state.folderId;
        var html = '';
        var shown = 0;

        transferState.targets.forEach(function (target) {

            if (filter && ((target.name || '').toLocaleLowerCase('tr').indexOf(filter) === -1)) { return; }

            var blocked = !!transferState.blocked[target.id];
            var indent = 'padding-left:' + (0.75 + (Math.min(target.depth, 8) * 0.9)) + 'rem;';

            shown++;

            html += '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2' +
                (blocked ? ' disabled' : '') +
                ((String(target.id) === String(transferState.picked)) ? ' active' : '') +
                '" style="' + indent + '" data-target-id="' + target.id + '"' + (blocked ? ' disabled' : '') + '>' +
                '<i class="bi ' + (insideCatalog() ? 'bi-boxes' : 'bi-folder-fill') + '"></i>' +
                '<span class="text-truncate">' + esc(target.name) + '</span>' +
                (target.archived ? ('<span class="badge text-bg-secondary ms-1">' + esc(L.archived) + '</span>') : '') +
                ((String(target.id) === String(here)) ? ('<span class="ms-auto small text-body-secondary">' + esc(L.you_are_here) + '</span>') : '') +
                '</button>';
        });

        container.innerHTML = (shown > 0)
            ? html
            : ('<div class="text-center text-body-secondary small py-3">' + esc(L.no_target_match) + '</div>');

        syncTransferButtons();
    }

    function syncTransferButtons() {

        var picked = (transferState && transferState.picked);
        var ready = ((picked !== null) && (picked !== undefined));

        document.getElementById('transfer_confirm_button').disabled = !ready;
        document.getElementById('transfer_secondary_button').disabled = !ready;
    }

    function openTransferDialog(items, copy) {

        if ((!items) || (items.length === 0)) { return; }

        var catalog = insideCatalog();
        var title = document.getElementById('transfer_modal_title');
        var confirmButton = document.getElementById('transfer_confirm_button');
        var secondaryButton = document.getElementById('transfer_secondary_button');
        var container = document.getElementById('transfer_targets');

        // A product lives in as many groups as it was put in, so in the store
        // moving and adding end somewhere different and both are offered by
        // name -- but only where both are true:
        //
        //   A group is not a member of anything, it sits somewhere. Moving one
        //   reparents it; the copy the same endpoint would make is a deep
        //   duplicate of the whole subtree, which is not what "add to group"
        //   says, so a selection with a group in it is offered the move alone.
        //
        //   In the flat lists there is no group to be taken out of, so a move
        //   would do exactly what the add does. Offering both would be two
        //   buttons for one result.
        var allProducts = catalog && items.every(function (each) { return each.kind === 'product'; });
        var hasSourceGroup = catalog && (state.groupId > 0) && (state.allFilter === '');
        var offerAdd = allProducts;
        var offerMove = (!catalog) || (!allProducts) || hasSourceGroup;

        transferState = { items: items, copy: !!copy, targets: [], blocked: {}, picked: null };

        title.textContent = catalog ? L.choose_group : L.choose_folder;
        document.getElementById('transfer_summary').textContent = fmt(L.items_selected, [items.length]);
        document.getElementById('transfer_filter').value = '';

        // Whichever buttons are being shown, said in the same order, because a
        // single sentence under two buttons reads as belonging to the one that
        // was pressed.
        var notes = '';

        if (offerAdd) {
            notes += '<div><i class="bi bi-plus-circle me-1"></i>' + esc(L.add_to_group) + ': ' + esc(L.add_to_group_note) + '</div>';
        }

        if (offerMove && catalog && allProducts) {
            notes += '<div><i class="bi bi-arrow-right-circle me-1"></i>' + esc(L.move_into_group) + ': ' + esc(L.move_into_group_note) + '</div>';
        }

        document.getElementById('transfer_note').innerHTML = notes;
        container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-secondary"></span></div>';

        confirmButton.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>' +
            esc(catalog ? (offerMove ? L.move_into_group : L.add_to_group) : (copy ? L.copy_here : L.move_here));
        secondaryButton.innerHTML = '<i class="bi bi-plus-circle me-1"></i>' + esc(L.add_to_group);
        secondaryButton.classList.toggle('d-none', !(offerAdd && offerMove));
        confirmButton.disabled = true;
        secondaryButton.disabled = true;

        // The main button is the move where one is offered; where it is not,
        // it is the add, and pressing it must not send a cut that the server
        // would quietly turn into one anyway.
        transferState.confirmCopy = catalog ? (!offerMove) : !!copy;

        if (!transferModal) { transferModal = new bootstrap.Modal(document.getElementById('transfer_modal')); }

        transferModal.show();

        loadTransferTargets(function (targets, message) {

            if (!transferState) { return; }

            if (!targets) {
                container.innerHTML = '';
                toast(message || L.request_failed, false);
                hideModal(transferModal, 'transfer_modal');
                return;
            }

            transferState.targets = targets;
            transferState.blocked = transferBlockedIds(targets, items, !!copy);
            renderTransferTargets();
        });
    }

    function runTransferDialog(copy) {

        if ((!transferState) || (transferState.picked === null)) { return; }

        var items = transferState.items;
        var target = transferState.picked;

        hideModal(transferModal, 'transfer_modal');
        transferState = null;

        transferItemsTo(target, items, copy);
    }

    // ── Clipboard ───────────────────────────────────────────────────────

    function clipboardSet(mode) {

        if (insideCatalogBin()) { return; }

        var items = selectedItems();

        if (items.length === 0) { return; }

        var sourceSnapshot = items.map(function (item) {
            return { kind: item.kind, id: item.id, name: item.name, source_folder_id: sourceFolderOf(item) };
        });

        var keys = {};
        items.forEach(function (item) { keys[itemKey(item)] = true; });

        state.clipboard = {
            mode: mode,
            area: insideCatalog() ? 'catalog' : 'files',
            sourceGroupId: state.groupId || 0,
            keys: keys,
            items: sourceSnapshot.filter(function (item) {
                return items.some(function (kept) { return kept.kind === item.kind && kept.id === item.id; });
            })
        };

        renderContent();
        applySelectionClasses();
        renderStatusbar();
        toast(fmt(mode === 'cut' ? L.clipboard_cut : L.clipboard_copy, [state.clipboard.items.length]));
    }

    function clipboardPaste(targetFolderId) {

        if (!state.clipboard) { toast(L.clipboard_empty, false); return; }

        // The store pastes through its own endpoint, and its two kinds mean
        // different things: a group changes parent or is duplicated, while a
        // product changes which groups list it. Nothing crosses between the
        // two areas -- a clipboard filled among folders has nothing to say to
        // a product group.
        if (insideCatalog()) {

            if (state.clipboard.area !== 'catalog') { toast(L.clipboard_empty, false); return; }

            catalogPaste(targetFolderId);
            return;
        }

        if (state.clipboard.area === 'catalog') { toast(L.clipboard_empty, false); return; }

        var payload = state.clipboard.items.map(function (item) { return { kind: item.kind, id: item.id }; });

        if (state.clipboard.mode === 'cut') {
            var movedItems = state.clipboard.items.slice();

            api({ type: 'explorer_move', target_folder_id: targetFolderId, items: payload }, function (response) {

                if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

                var groups = moveUndoGroups(movedItems);

                var entry = (groups.length > 0)
                    ? pushUndo({
                        undo: { type: 'move-groups', groups: groups },
                        redo: { type: 'move-groups', groups: [{ target: targetFolderId, items: payload }] }
                    })
                    : null;

                undoToast(response.message || L.moved, entry);
                state.clipboard = null;
                reload();
            });
        } else {
            api({ type: 'explorer_paste', target_folder_id: targetFolderId, items: payload }, function (response) {

                if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

                var entry = (response.created && (response.created.length > 0) && state.recycle.available)
                    ? pushUndo({ undo: { type: 'bin', items: response.created }, redo: null })
                    : null;

                undoToast(response.message || L.pasted, entry);
                reload();
            });
        }
    }

    function duplicateItem(item) {

        // A group is copied next to itself, the way a folder is: the copy
        // endpoint is the store's own, because a group id means nothing in
        // the folder tables.
        if (insideCatalog()) {

            api({
                type: 'explorer_catalog_paste',
                mode: 'copy',
                target_group_id: (item.parent_id || state.groupId || 0),
                items: [{ kind: item.kind, id: item.id }]
            }, function (response) {
                toast(response.message || L.request_failed, response.status !== 'error');
                reload();
            });

            return;
        }

        // A copy belongs beside the original, not beside wherever the operator
        // happens to be standing. The two are the same thing inside a folder,
        // and not the same thing at all in the Files, Pictures and Pages views:
        // those list every folder at once, so there is no folder being stood in
        // and state.folderId is zero there. A duplicate asked for from one of
        // those lists therefore arrived with no destination and came back as
        // "Invalid request." Every row carries the folder it lives in, which is
        // the only answer that is right in both places.
        var target = (item.kind === 'folder') ? item.parent_id : (item.folder_id || state.folderId);

        api({ type: 'explorer_paste', target_folder_id: target, items: [{ kind: item.kind, id: item.id }] }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');

            if ((response.status !== 'error') && response.created && response.created.length > 0 && state.recycle.available) {
                pushUndo({ undo: { type: 'bin', items: response.created }, redo: null });
            }

            reload();
        });
    }

    // ── Taking a backup ─────────────────────────────────────────────────
    //
    // Seven server steps rather than one request.  A large site would run past
    // the time limit in a single call, so the work is cut into pieces and each
    // piece hands the next the name the first one settled on.  The name is
    // left empty here: the server then makes one out of the host and the
    // minute, exactly as the Backups screen does.
    //
    // It goes through the same action that screen posts to, so there is one
    // implementation of taking a backup and not a second one living in here.
    var BACKUP_STEPS = [
        { step: 'create_backup_folder', share: 15 },
        { step: 'create_mysql_dumb', share: 30 },
        { step: 'clear_files_and_layouts', share: 45 },
        { step: 'move_files', share: 60 },
        { step: 'move_layouts', share: 75 },
        { step: 'create_htaccess_and_config', share: 90 },
        { step: 'check', share: 100 }
    ];

    var backupRunning = false;

    function createBackup() {

        // Two at once would write into the same folder from two directions.
        if ((!insideBackups()) || ((state.backupPath || '') !== '') || (backupRunning)) { return; }

        var name = '';
        var at = 0;

        backupRunning = true;
        setProgress(L.backing_up, 0, 100);

        function step() {

            if (at >= BACKUP_STEPS.length) {

                backupRunning = false;
                clearProgress();
                toast(L.backup_created);

                // Straight into renaming it.  The name the server gives a
                // backup says when it was taken and nothing about why, and the
                // moment somebody knows why is this one -- a week later,
                // looking at eight folders named after eight timestamps, nobody
                // does.
                reload(function () { beginRenameByName(name); });

                return;
            }

            var current = BACKUP_STEPS[at];

            $.ajax({
                url: 'api.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'software_backup',
                    token: software_token,
                    step: current.step,
                    backup_name: name
                }),
                success: function (response) {

                    response = response || {};

                    if (response.status !== 'success') {
                        backupRunning = false;
                        clearProgress();
                        toast(response.message || L.request_failed, false);
                        return;
                    }

                    // The first step is the one that decides the name; the rest
                    // repeat it back, and every step has to be told it.
                    name = response.backup_name || name;

                    setProgress(L.backing_up, current.share, 100);

                    at++;
                    step();
                },
                error: function () {
                    backupRunning = false;
                    clearProgress();
                    toast(L.request_failed, false);
                }
            });
        }

        step();
    }

    // The freshly written folder, found by the name the server settled on --
    // there is no id to hold on to across a reload here, because a backup is a
    // folder on disk and its name is its identity.
    function beginRenameByName(name) {

        if (!name) { return; }

        var target = null;

        (state.ordered || []).forEach(function (candidate) {

            if ((!target) && (String(candidate.name) === String(name))) { target = candidate; }
        });

        if (!target) { return; }

        setSelection([target], target);
        beginRename(target);
    }

    // ── Short links ─────────────────────────────────────────────────────
    //
    // A short link belongs to no folder, so the folder verbs do not apply to
    // it: there is nowhere to move one into and nothing to move it out of.
    // Copy and paste therefore mean here what duplicate means -- a second link
    // to the same place, under a free name -- and that is one action on the
    // server rather than two that would have to agree with each other.

    var importZipModal = null;
    var shortLinkModal = null;
    var shortLinkOptions = null;
    var shortLinkSaving = false;

    // The row the window is open on, when it was opened to change one rather
    // than to make one. Null means it is making a new link.
    var shortLinkEditing = null;

    function shortLinkIds(items) {
        return (items || [])
            .filter(function (entry) { return (entry && entry.short_link); })
            .map(function (entry) { return { id: entry.id }; });
    }

    function copyShortLinks(items) {

        var payload = shortLinkIds(items);

        if (payload.length === 0) { return; }

        state.clipboard = { mode: 'copy', area: 'short_links', items: payload };
        renderStatusbar();
        toast(fmt(L.clipboard_copy, [payload.length]));
    }

    function pasteShortLinks() {

        if ((!state.clipboard) || (state.clipboard.area !== 'short_links')) { return; }

        runShortLinkDuplicate(state.clipboard.items);
    }

    function duplicateShortLinks(items) {

        var payload = shortLinkIds(items);

        if (payload.length === 0) { return; }

        runShortLinkDuplicate(payload);
    }

    function runShortLinkDuplicate(payload) {

        api({ type: 'explorer_short_link_duplicate', items: payload }, function (response) {

            toast(response.message || L.request_failed, response.status !== 'error');

            if (response.status === 'error') { return; }

            // Into renaming the first copy, the same way a new one is named:
            // "name[1]" says which link it came from and nothing about what it
            // is for.
            reload(function () {

                var made = (response.names || [])[0];

                if (!made) { return; }

                var target = null;

                (state.ordered || []).forEach(function (candidate) {
                    if ((!target) && (String(candidate.name) === String(made))) { target = candidate; }
                });

                if (target) {
                    setSelection([target], target);
                    beginRename(target);
                }
            });
        });
    }

    function deleteShortLinks(items) {

        var payload = shortLinkIds(items);

        if (payload.length === 0) { return; }

        // Once there is a bin to catch them this is an ordinary delete and asks
        // an ordinary question. Before the upgrade it is still the end of the
        // link, so it keeps the window that makes you type the word.
        openDeleteSelectionModal(items, state.recycle.available ? 'bin' : 'hard', function (confirmed, done) {

            api({ type: 'explorer_short_link_delete', items: payload }, function (response) {

                if (done) { done(); }

                toast(response.message || L.request_failed, response.status !== 'error');

                if (response.status !== 'error') { reload(); }
            });
        });
    }

    function beginShortLinkRename(item) {

        var element = document.querySelector('#explorer_content .explorer-item[data-kind="' + item.kind + '"][data-id="' + item.id + '"] [data-role="name"]');

        if (!element) { return; }

        startInlineRename(element, item.name, false, function (value, restore) {

            api({ type: 'explorer_short_link_rename', item_id: item.id, name: value }, function (response) {

                toast(response.message || L.request_failed, response.status !== 'error');

                if (response.status === 'error') { restore(); return; }

                reload();
            });
        });
    }

    // ── The wizard ──────────────────────────────────────────────────────
    //
    // It settles where the link goes. Nothing is written while it is open, so
    // closing it at any point leaves nothing behind -- there is no half-made
    // row to clean up because there is no row until Create is pressed.
    //
    // The name is settled after, in place, the way a new folder is named. The
    // server hands back a free placeholder and the screen walks straight into
    // renaming it: the moment somebody knows what to call a link is the moment
    // they have just said where it points.

    function shortLinkSelectHtml(id, label, list, note) {

        var html = '<div class="mb-3">' +
            '<label class="form-label" for="' + id + '">' + esc(label) + '</label>' +
            '<select class="form-select" id="' + id + '">' +
            '<option value="">- ' + esc(L.select_one) + ' -</option>';

        (list || []).forEach(function (option) {
            html += '<option value="' + esc(option.v) + '">' + esc(option.t) + '</option>';
        });

        html += '</select>';

        if (note) { html += '<div class="form-text">' + esc(note) + '</div>'; }

        return html + '</div>';
    }

    function renderShortLinkFields() {

        var type = document.getElementById('sl_type').value;
        var fields = document.getElementById('sl_fields');
        var options = shortLinkOptions || {};
        var html = '';

        switch (type) {

            case 'page':
                html = shortLinkSelectHtml('sl_page', L.page, options.page);
                break;

            case 'product_group':
                // One of the two, never both: the group is shown either on a
                // catalog page or on a catalog detail page.
                html = shortLinkSelectHtml('sl_catalog_page', L.catalog_page, options.catalog_page) +
                    shortLinkSelectHtml('sl_catalog_detail_page', L.catalog_detail_page, options.catalog_detail_page) +
                    shortLinkSelectHtml('sl_product_group', L.product_group_word, options.product_group);
                break;

            case 'product':
                html = shortLinkSelectHtml('sl_catalog_detail_page', L.catalog_detail_page, options.catalog_detail_page) +
                    shortLinkSelectHtml('sl_product', L.product, options.product);
                break;

            case 'url':
                html = '<div class="mb-3">' +
                    '<label class="form-label" for="sl_url">' + esc(L.url_word) + '</label>' +
                    '<input type="text" class="form-control" id="sl_url" maxlength="255" placeholder="' + esc(L.short_link_url_note) + '" />' +
                    '</div>';
                break;

            case 'file':
                html = shortLinkSelectHtml('sl_file', L.file, options.file);
                break;
        }

        fields.innerHTML = html;

        // A product group is shown either on a catalog page or on a catalog
        // detail page, never on both, so picking in one list empties the
        // other. The classic add screen does the same thing; doing it here
        // means the error about picking both can no longer be reached.
        var catalogPick = document.getElementById('sl_catalog_page');
        var detailPick = document.getElementById('sl_catalog_detail_page');

        if ((catalogPick) && (detailPick)) {

            catalogPick.addEventListener('change', function () {
                if (catalogPick.value !== '') { detailPick.value = ''; }
            });

            detailPick.addEventListener('change', function () {
                if (detailPick.value !== '') { catalogPick.value = ''; }
            });
        }

        // The tracking code rides in the address as ?t=, which only means
        // something where the visitor lands on a page of ours.
        var tracked = ((type === 'page') || (type === 'product_group') || (type === 'product'));

        document.getElementById('sl_tracking_wrap').classList.toggle('d-none', !tracked);
        document.getElementById('short_link_create_button').disabled = (type === '');
    }

    // Which of the two page pick lists an existing product group link was made
    // with. The row stores one page id and not which list it came from, so it
    // is found by looking for the id in each.
    function shortLinkPageField(pageId) {

        var inCatalog = ((shortLinkOptions.catalog_page) || []).some(function (option) { return String(option.v) === String(pageId); });

        return inCatalog ? 'sl_catalog_page' : 'sl_catalog_detail_page';
    }

    function fillShortLinkWizard(item) {

        var typeSelect = document.getElementById('sl_type');

        typeSelect.value = item.destination_type;
        renderShortLinkFields();

        function set(id, value) {
            var element = document.getElementById(id);
            if (element) { element.value = value; }
        }

        switch (item.destination_type) {

            case 'page': set('sl_page', item.page_id); break;

            case 'product_group':
                set(shortLinkPageField(item.page_id), item.page_id);
                set('sl_product_group', item.product_group_id);
                break;

            case 'product':
                set('sl_catalog_detail_page', item.page_id);
                set('sl_product', item.product_id);
                break;

            case 'url': set('sl_url', item.link_url); break;
            case 'file': set('sl_file', item.file_id); break;
        }

        set('sl_tracking', item.tracking_code || '');
    }

    // Called with a row it changes that row; called with nothing it makes a
    // new one. Same window, same fields, same reading of them on the server --
    // a second screen for editing would be a second place for the rules to
    // drift.
    function openShortLinkWizard(item, seedPage) {

        shortLinkEditing = (item && item.short_link) ? item : null;
        seedPage = parseInt(seedPage, 10) || 0;

        function draw() {

            var body = document.getElementById('short_link_modal_body');

            // Only when there is a row. A new link has no name yet -- the
            // server gives it a free placeholder and the screen walks straight
            // into renaming it in the list, which is where somebody who has
            // just said where a link points wants to be. An existing one is
            // the other way round: its name is the first thing about it, and
            // an operator looking for "rename" opens this window before
            // reaching for the right button.
            var nameHtml = shortLinkEditing
                ? ('<div class="mb-3">' +
                        '<label class="form-label" for="sl_name">' + esc(L.name) + '</label>' +
                        '<input type="text" class="form-control" id="sl_name" maxlength="100" value="' + esc(shortLinkEditing.name || '') + '" />' +
                        '<div class="form-text">' + esc(L.short_link_name_note) + '</div>' +
                    '</div>')
                : '';

            body.innerHTML =
                '<p class="form-text mt-0">' + esc(L.short_links_note) + '</p>' +
                nameHtml +
                '<div class="mb-3">' +
                    '<label class="form-label" for="sl_type">' + esc(L.destination_type) + '</label>' +
                    '<select class="form-select" id="sl_type">' +
                        '<option value="">- ' + esc(L.select_one) + ' -</option>' +
                        '<option value="page">' + esc(L.page) + '</option>' +
                        '<option value="product_group">' + esc(L.product_group_word) + '</option>' +
                        '<option value="product">' + esc(L.product) + '</option>' +
                        '<option value="file">' + esc(L.file) + '</option>' +
                        '<option value="url">' + esc(L.url_word) + '</option>' +
                    '</select>' +
                '</div>' +
                '<div id="sl_fields"></div>' +
                '<div class="mb-1 d-none" id="sl_tracking_wrap">' +
                    '<label class="form-label" for="sl_tracking">' + esc(L.tracking_code) + '</label>' +
                    '<input type="text" class="form-control" id="sl_tracking" maxlength="100" />' +
                    '<div class="form-text">' + esc(L.tracking_code_note) + '</div>' +
                '</div>' +
                '<div class="alert alert-danger mt-3 mb-0 d-none" id="sl_error"></div>';

            document.getElementById('sl_type').addEventListener('change', renderShortLinkFields);

            document.getElementById('short_link_modal_label').innerHTML =
                '<span class="bi bi-link-45deg me-2"></span>' +
                esc(shortLinkEditing ? (L.edit + ' — ' + shortLinkEditing.name) : L.short_link);

            document.getElementById('short_link_create_button').innerHTML =
                shortLinkEditing
                    ? ('<span class="bi bi-check-lg me-1"></span>' + esc(L.save))
                    : ('<span class="bi bi-plus-lg me-1"></span>' + esc(L.create));

            if (shortLinkEditing) {

                fillShortLinkWizard(shortLinkEditing);

            } else {

                renderShortLinkFields();

                // Opened from a page's panel, which already knows where the
                // link should point. The type is set first and the fields
                // redrawn: the page list only exists once the destination is
                // a page.
                if (seedPage) {

                    document.getElementById('sl_type').value = 'page';
                    renderShortLinkFields();

                    var seeded = document.getElementById('sl_page');

                    if (seeded) { seeded.value = seedPage; }
                }
            }

            if (!shortLinkModal) {
                shortLinkModal = new bootstrap.Modal(document.getElementById('short_link_modal'));
            }

            shortLinkModal.show();
        }

        // The lists come from the server once and are kept for the session:
        // they are the same lists the classic add screen draws.
        if (shortLinkOptions) { draw(); return; }

        api({ type: 'explorer_short_link_options' }, function (response) {

            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            shortLinkOptions = response.options || {};
            draw();
        });
    }

    function submitShortLinkWizard() {

        if (shortLinkSaving) { return; }

        var value = function (id) {
            var element = document.getElementById(id);
            return element ? element.value : '';
        };

        var destinationType = value('sl_type');

        if (destinationType === '') { return; }

        var payload = {
            type: shortLinkEditing ? 'explorer_short_link_update' : 'explorer_short_link_create',
            item_id: shortLinkEditing ? shortLinkEditing.id : 0,
            destination_type: destinationType,
            tracking_code: value('sl_tracking'),
            name: shortLinkEditing ? value('sl_name') : 'yeni_kisa_link'
        };

        // Only what this type actually uses. Sending every field on every save
        // meant a value left in a list the chosen type does not read could
        // still reach the server -- which is how "pick one of the two, not
        // both" came back at somebody who had picked one.
        switch (destinationType) {

            case 'page':
                payload.page_id = value('sl_page');
                break;

            case 'product_group':
                payload.catalog_page_id = value('sl_catalog_page');
                payload.catalog_detail_page_id = value('sl_catalog_detail_page');
                payload.product_group_id = value('sl_product_group');
                break;

            case 'product':
                payload.catalog_detail_page_id = value('sl_catalog_detail_page');
                payload.product_id = value('sl_product');
                break;

            case 'url':
                payload.url = value('sl_url');
                break;

            case 'file':
                payload.file_id = value('sl_file');
                break;
        }

        var error = document.getElementById('sl_error');
        var button = document.getElementById('short_link_create_button');

        shortLinkSaving = true;
        button.disabled = true;

        api(payload, function (response) {

            shortLinkSaving = false;
            button.disabled = false;

            if (response.status !== 'success') {

                error.textContent = response.message || L.request_failed;
                error.classList.remove('d-none');

                // The field the server objected to, so the eye goes there
                // rather than hunting down the form.
                var fieldMap = {
                    name: 'sl_name',
                    page_id: 'sl_page',
                    catalog_page_id: 'sl_catalog_page',
                    catalog_detail_page_id: 'sl_catalog_detail_page',
                    product_group_id: 'sl_product_group',
                    product_id: 'sl_product',
                    url: 'sl_url',
                    file_id: 'sl_file',
                    destination_type: 'sl_type'
                };

                var focus = document.getElementById(fieldMap[response.field] || '');

                if (focus) { focus.focus(); }

                return;
            }

            error.classList.add('d-none');

            hideModal(shortLinkModal, 'short_link_modal');

            // A link that already had a name keeps it; only a new one is
            // walked into renaming, because only a new one is called
            // yeni_kisa_link.
            if (shortLinkEditing) {

                shortLinkEditing = null;
                toast(response.message || L.request_failed);
                reload();

                return;
            }

            state.pendingShortLinkRename = response.id;

            toast(L.short_link_named);

            // Made from the pages view, the new link is not on this screen at
            // all -- and it is about to be renamed, which has to happen where
            // it can be seen. So the screen goes to where it now lives.
            if (insideShortLinks()) { reload(); } else { enterShortLinks(); }
        });
    }

    function beginBackupRename(item) {
        var element = document.querySelector('#explorer_content .explorer-item[data-kind="' + item.kind + '"][data-id="' + item.id + '"] [data-role="name"]');

        if (!element) { return; }

        startInlineRename(element, item.name, item.kind === 'file', function (value, restore) {
            backupAction('explorer_backup_rename', item, { name: value }, function (response) {
                if (response.status === 'error') { restore(); }
            });
        });
    }

    // ── Rename (content area) ───────────────────────────────────────────

    // ── Renaming a selection by a rule ──────────────────────────────────
    //
    // Forty photographs called DSC_0041.JPG is the job this screen kept sending
    // people back to the keyboard for. Three rules cover nearly all of it, and
    // every one of them is drawn against the real names before anything is
    // written: a rename is the one bulk operation whose mistake is invisible
    // afterwards -- the files are all still there, just called the wrong thing.

    var bulkRenameOffcanvas = null;
    var bulkRenameItems = [];

    function bulkRenameTargets() {
        return selectedItems().filter(function (item) {
            return (!item.backup) && (!item.short_link) && itemRenamable(item);
        });
    }

    // The extension is not part of the name anybody means to change, so it is
    // held back while the rule runs and put back afterwards. Only files carry
    // one -- a page or a product group called "2026.Plan" is called that.
    function bulkRenameSplit(item, keepExtension) {

        var name = String(item.name || '');

        if ((!keepExtension) || (item.kind !== 'file')) { return { base: name, ext: '' }; }

        var dot = name.lastIndexOf('.');

        if (dot <= 0) { return { base: name, ext: '' }; }

        return { base: name.slice(0, dot), ext: name.slice(dot) };
    }

    // Literal, not a regular expression: an operator typing a dot means a dot,
    // and a find box that quietly accepts patterns is a find box that one day
    // matches everything.
    function bulkRenameReplace(text, find, replace, caseSensitive) {

        if (find === '') { return text; }

        if (caseSensitive) { return text.split(find).join(replace); }

        var haystack = text.toLocaleLowerCase('tr');
        var needle = find.toLocaleLowerCase('tr');
        var out = '';
        var at = 0;

        while (true) {

            var found = haystack.indexOf(needle, at);

            if (found === -1) { out += text.slice(at); break; }

            out += text.slice(at, found) + replace;
            at = found + needle.length;
        }

        return out;
    }

    function bulkRenameField(id) {
        var element = document.getElementById(id);
        return element ? element.value : '';
    }

    function bulkRenameChecked(id) {
        var element = document.getElementById(id);
        return element ? element.checked : false;
    }

    function bulkRenamePlan() {

        var mode = bulkRenameField('rename_mode');
        var keepExtension = bulkRenameChecked('rename_keep_extension');
        var caseSensitive = bulkRenameChecked('rename_case');
        var start = parseInt(bulkRenameField('rename_start'), 10);
        var pattern = bulkRenameField('rename_pattern');
        var plan = [];
        var taken = {};

        if (isNaN(start)) { start = 1; }

        bulkRenameItems.forEach(function (item, index) {

            var parts = bulkRenameSplit(item, keepExtension);
            var base = parts.base;

            if (mode === 'replace') {
                base = bulkRenameReplace(base, bulkRenameField('rename_find'), bulkRenameField('rename_replace'), caseSensitive);

            } else if (mode === 'affix') {
                base = bulkRenameField('rename_prefix') + base + bulkRenameField('rename_suffix');

            } else {

                var number = start + index;

                base = String(pattern)
                    .replace(/#+/g, function (hashes) {
                        var digits = String(number);
                        while (digits.length < hashes.length) { digits = '0' + digits; }
                        return digits;
                    })
                    .split('{name}').join(parts.base);
            }

            var name = (base + parts.ext).trim();

            // Two rows asking for one name is the mistake this preview exists
            // to catch: the server would take the first and refuse the second,
            // and the operator would be left working out which.
            var key = name.toLocaleLowerCase('tr');
            var clash = (name !== '') && (taken[key] === true);

            if (name !== '') { taken[key] = true; }

            plan.push({
                item: item,
                name: name,
                unchanged: ((name === '') || (name === item.name)),
                clash: clash
            });
        });

        return plan;
    }

    function bulkRenameRefresh() {

        var mode = bulkRenameField('rename_mode');
        var plan = bulkRenamePlan();
        var doable = plan.filter(function (row) { return (!row.unchanged) && (!row.clash); });

        ['replace', 'affix', 'number'].forEach(function (name) {
            var block = document.getElementById('rename_block_' + name);
            if (block) { block.classList.toggle('d-none', mode !== name); }
        });

        var html = '';

        plan.forEach(function (row) {

            var note = row.clash ? L.rename_clash : (row.unchanged ? L.rename_unchanged : '');

            html += '<div class="d-flex align-items-baseline gap-2 py-1 border-bottom small' +
                (row.unchanged ? ' opacity-50' : '') + '">' +
                '<span class="text-truncate" style="flex:1 1 0;">' + esc(row.item.name) + '</span>' +
                '<i class="bi bi-arrow-right text-body-secondary"></i>' +
                '<span class="text-truncate' + (row.clash ? ' text-danger' : '') + '" style="flex:1 1 0;">' +
                esc(row.unchanged ? row.item.name : row.name) + '</span>' +
                (note ? ('<span class="text-body-secondary text-nowrap">' + esc(note) + '</span>') : '') +
                '</div>';
        });

        document.getElementById('rename_preview_list').innerHTML = html;

        var apply = document.getElementById('rename_apply_button');

        apply.disabled = (doable.length === 0);
        apply.innerHTML = '<i class="bi bi-input-cursor-text me-1"></i>' +
            esc((doable.length === 0) ? L.rename_nothing : fmt(L.rename_apply, [doable.length]));
    }

    function runBulkRename() {

        var queue = bulkRenamePlan().filter(function (row) { return (!row.unchanged) && (!row.clash); });
        var total = queue.length;

        if (total === 0) { return; }

        var endpoint = insideCatalog() ? 'explorer_catalog_rename' : 'explorer_rename';
        var back = [];
        var errors = [];
        var done = 0;

        hideModal(bulkRenameOffcanvas, 'bulk_rename_offcanvas');
        setProgress(L.renaming, 0, total);

        // One at a time: the rename endpoint takes one item, and a name that is
        // refused has to be reported as itself rather than as one of three.
        (function next() {

            var row = queue.shift();

            if (!row) {

                clearProgress();

                var entry = (back.length > 0)
                    ? pushUndo({ undo: { type: 'rename-many', endpoint: endpoint, items: back }, redo: null })
                    : null;

                if (errors.length > 0) {
                    toast(errors.slice(0, 3).join(' '), false);
                } else {
                    undoToast(fmt(L.rename_done, [done]), entry);
                }

                reload();
                return;
            }

            api({ type: endpoint, item_kind: row.item.kind, item_id: row.item.id, name: row.name }, function (response) {

                if (response.status === 'success') {
                    done++;
                    // Pushed in front, so undoing walks back the way it came.
                    back.unshift({ kind: row.item.kind, id: row.item.id, name: row.item.name });
                } else if (response.message) {
                    errors.push(response.message);
                }

                setProgress(L.renaming, total - queue.length, total);
                next();

            }, function () {
                errors.push(L.request_failed);
                setProgress(L.renaming, total - queue.length, total);
                next();
            });
        })();
    }

    function openBulkRenamePanel() {

        bulkRenameItems = bulkRenameTargets();

        if (bulkRenameItems.length === 0) { return; }

        var anyFile = bulkRenameItems.some(function (item) { return item.kind === 'file'; });

        document.getElementById('bulk_rename_offcanvas_body').innerHTML =
            '<div class="mb-3">' +
            '<label class="form-label" for="rename_mode">' + esc(L.rename_mode) + '</label>' +
            '<select class="form-select form-select-sm" id="rename_mode">' +
            '<option value="replace">' + esc(L.rename_mode_replace) + '</option>' +
            '<option value="affix">' + esc(L.rename_mode_affix) + '</option>' +
            '<option value="number">' + esc(L.rename_mode_number) + '</option>' +
            '</select></div>' +

            '<div id="rename_block_replace">' +
            '<div class="mb-2"><label class="form-label" for="rename_find">' + esc(L.rename_find) + '</label>' +
            '<input type="text" class="form-control form-control-sm" id="rename_find" /></div>' +
            '<div class="mb-2"><label class="form-label" for="rename_replace">' + esc(L.rename_replace) + '</label>' +
            '<input type="text" class="form-control form-control-sm" id="rename_replace" /></div>' +
            '<div class="form-check form-switch mb-3">' +
            '<input class="form-check-input" type="checkbox" role="switch" id="rename_case" />' +
            '<label class="form-check-label" for="rename_case">' + esc(L.rename_case) + '</label></div>' +
            '</div>' +

            '<div id="rename_block_affix" class="d-none">' +
            '<div class="mb-2"><label class="form-label" for="rename_prefix">' + esc(L.rename_prefix) + '</label>' +
            '<input type="text" class="form-control form-control-sm" id="rename_prefix" /></div>' +
            '<div class="mb-3"><label class="form-label" for="rename_suffix">' + esc(L.rename_suffix) + '</label>' +
            '<input type="text" class="form-control form-control-sm" id="rename_suffix" /></div>' +
            '</div>' +

            '<div id="rename_block_number" class="d-none">' +
            '<div class="mb-2"><label class="form-label" for="rename_pattern">' + esc(L.rename_pattern) + '</label>' +
            '<input type="text" class="form-control form-control-sm" id="rename_pattern" value="' + esc(L.rename_pattern_default) + '" />' +
            '<div class="form-text">' + esc(L.rename_pattern_note) + '</div></div>' +
            '<div class="mb-3" style="max-width:9rem;"><label class="form-label" for="rename_start">' + esc(L.rename_start) + '</label>' +
            '<input type="number" class="form-control form-control-sm" id="rename_start" value="1" min="0" /></div>' +
            '</div>' +

            (anyFile
                ? ('<div class="form-check form-switch mb-3">' +
                    '<input class="form-check-input" type="checkbox" role="switch" id="rename_keep_extension" checked />' +
                    '<label class="form-check-label" for="rename_keep_extension">' + esc(L.rename_keep_extension) + '</label></div>')
                : '') +

            '<h6 class="text-uppercase text-secondary fw-bold" style="font-size:.72rem;letter-spacing:.05em;">' + esc(L.rename_preview) + '</h6>' +
            '<div id="rename_preview_list" class="mb-2" style="max-height:40vh;overflow-y:auto;"></div>' +
            '<div class="form-text mb-3">' + esc(L.rename_server_note) + '</div>' +

            '<div class="d-grid"><button type="button" class="btn btn-primary btn-sm" id="rename_apply_button"></button></div>';

        var body = document.getElementById('bulk_rename_offcanvas_body');

        // Every control redraws the preview, so the operator is reading the
        // result rather than imagining it.
        body.addEventListener('input', bulkRenameRefresh);
        body.addEventListener('change', bulkRenameRefresh);
        document.getElementById('rename_apply_button').addEventListener('click', runBulkRename);

        bulkRenameRefresh();

        if (!bulkRenameOffcanvas) { bulkRenameOffcanvas = new bootstrap.Offcanvas(document.getElementById('bulk_rename_offcanvas')); }

        bulkRenameOffcanvas.show();
    }

    function beginRename(item) {
        if (item && item.short_link) { beginShortLinkRename(item); return; }
        if (item && item.backup) { beginBackupRename(item); return; }

        if (!itemRenamable(item)) { return; }

        var element = document.querySelector('#explorer_content .explorer-item[data-kind="' + item.kind + '"][data-id="' + item.id + '"] [data-role="name"]');

        if (!element) { return; }

        startInlineRename(element, item.name, item.kind === 'file', function (value, done) {

            // Groups and products rename through their own endpoint: the folder
            // one writes to the folder tables, and the id would mean something
            // else there.
            var renameType = insideCatalog() ? 'explorer_catalog_rename' : 'explorer_rename';

            api({ type: renameType, item_kind: item.kind, item_id: item.id, name: value }, function (response) {
                if (response.status === 'success') {
                    var renameOp = insideCatalog() ? 'catalog_rename' : 'rename';

                    pushUndo({
                        undo: { type: renameOp, kind: item.kind, id: item.id, name: item.name },
                        redo: { type: renameOp, kind: item.kind, id: item.id, name: response.name || value }
                    });
                    toast(L.renamed);
                    reload();
                } else {
                    toast(response.message || L.request_failed, false);
                    done();
                }
            });
        });
    }

    // Shared inline rename widget: swaps the label for an input, selects the
    // base name, commits on Enter or blur, cancels on Escape.
    // ── Editing a figure where it is read ───────────────────────────────
    //
    // Price, stock and the name the storefront prints, changed in the list
    // without opening anything. The bulk panel is for a selection and the
    // product screen for everything else; neither is the right amount of
    // ceremony for "this one is two lira more than it says".
    //
    // Three fields and no more, and the same three the endpoint accepts. A
    // general "edit any column here" would have to carry every rule the
    // product screen carries -- addresses, variant attributes, tax, zones --
    // or quietly skip them, and quietly skipping them is how a catalog drifts.

    function quickEditable(item, field) {

        if ((!insideCatalog()) || insideCatalogBin()) { return false; }
        if ((!item) || (item.kind !== 'product')) { return false; }

        // A number written against a product that does not count its stock is
        // a number nobody will read; the cell says so instead.
        if (field === 'quantity') { return !!item.inventory; }

        return true;
    }

    function quickEditValue(item, field) {

        // Grouping off: the operator is editing the number, and a thousands
        // separator in the box is a character the parser would choke on.
        if (field === 'price') {
            return (item.price / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: false });
        }

        if (field === 'quantity') { return String(item.quantity || 0); }

        return String(item.short_description || '');
    }

    // The same three as the cell draws them.
    function quickEditShown(item, field) {

        if (field === 'price') { return moneyLabel(item.price); }
        if (field === 'quantity') { return String(item.quantity || 0); }

        var text = catalogSubName(item);

        return (text !== '') ? text : '—';
    }

    // Wraps a cell's text so a double click can find it. Not a button and not
    // an input: the cell is read far more often than it is written, so it
    // stays text and says what it can do on hover.
    function editableCellHtml(item, field, text, extraClass) {

        if (!quickEditable(item, field)) { return esc(text); }

        return '<span class="cell-editable' + (extraClass ? (' ' + extraClass) : '') + '" data-edit="' + esc(field) + '"' +
            ' title="' + esc(L.edit_here_hint) + '">' + esc(text) + '</span>';
    }

    // The storefront's name for the row, under its code.
    //
    // A product that has none still gets the line, as a dash: it is the one
    // place the gap is visible at a glance, and without it there would be
    // nothing to double-click on exactly the rows that need the edit most.
    // Only in the store's list -- the file manager's name cell has no second
    // line and nothing to put on one.
    // Written out rather than going through editableCellHtml(): the editable
    // element has to BE the line that truncates, not sit inside it, or the
    // input it turns into is clipped by its own wrapper.
    function catalogSubNameHtml(item) {

        var text = catalogSubName(item);

        if (!quickEditable(item, 'short_description')) {
            return (text !== '') ? ('<span class="list-subname">' + esc(text) + '</span>') : '';
        }

        return '<span class="list-subname cell-editable' + ((text === '') ? ' subname-empty' : '') + '"' +
            ' data-edit="short_description" title="' + esc(L.edit_here_hint) + '">' +
            esc((text !== '') ? text : '—') + '</span>';
    }

    // Whether the cell this field lives in is actually on screen. The menu
    // asks before offering the entry: the grid has no cells at all, and the
    // stock column comes off below the large breakpoint.
    function quickEditReachable(item, field) {

        if ((!quickEditable(item, field)) || (state.viewType !== 'list')) { return false; }
        if (field === 'price') { return listBreakpointMet('md'); }
        if (field === 'quantity') { return listBreakpointMet('lg'); }

        return true;
    }

    // The rename editor next door is about a name: it refuses an empty value
    // and knows about file extensions. This one is about a value, where empty
    // is a legitimate answer for a short description, so the two stay apart
    // rather than meeting in one function with flags.
    // 'shown' is what the cell reads when nobody is editing it, which is not
    // the same string as the one that goes in the box: a price is displayed
    // grouped and localised and edited plain. Cancelling has to put back what
    // was there, not what was being typed over.
    function startCellEdit(element, original, shown, numeric, commit) {

        element.classList.add('is-editing');
        element.innerHTML = '<input type="text" class="form-control form-control-sm rename-input"' +
            (numeric ? ' inputmode="decimal"' : '') + ' value="' + esc(original) + '" />';

        var input = element.querySelector('input');

        input.focus();
        input.select();

        var finished = false;

        function restore() {
            element.classList.remove('is-editing');
            element.textContent = shown;
        }

        function finish(ok) {

            if (finished) { return; }
            finished = true;

            var value = input.value.trim();

            if ((!ok) || (value === original)) { restore(); return; }

            commit(value, restore);
        }

        input.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Enter') { event.preventDefault(); finish(true); }
            if (event.key === 'Escape') { event.preventDefault(); finish(false); }
        });

        input.addEventListener('blur', function () { finish(true); });
        input.addEventListener('click', function (event) { event.stopPropagation(); });
        input.addEventListener('dblclick', function (event) { event.stopPropagation(); });
    }

    function beginQuickEdit(item, field, element) {

        if ((!quickEditable(item, field)) || (!element) || element.querySelector('input')) { return; }

        var original = quickEditValue(item, field);

        startCellEdit(element, original, quickEditShown(item, field), (field !== 'short_description'), function (value, restore) {

            api({ type: 'explorer_catalog_quick_edit', item_id: item.id, field: field, value: value }, function (response) {

                if (response.status !== 'success') { toast(response.message || L.request_failed, false); restore(); return; }

                undoToast(response.message || L.updated, pushUndo({
                    undo: { type: 'catalog_field', id: item.id, field: field, value: original },
                    redo: { type: 'catalog_field', id: item.id, field: field, value: value }
                }));

                reload();
            });
        });
    }

    // The menu route to the same editor, for the operator who works from the
    // right button rather than by double-clicking.
    function quickEditFromMenu(item, field) {

        if (!quickEditable(item, field)) { return; }

        var row = document.querySelector('#explorer_content .explorer-item[data-kind="product"][data-id="' + item.id + '"]');
        var element = row ? row.querySelector('[data-edit="' + field + '"]') : null;

        if (element) { beginQuickEdit(item, field, element); }
    }

    function startInlineRename(element, original, selectBaseOnly, commit) {
        // The list truncates this span; while it holds an input it must not.
        element.classList.add('is-renaming');
        element.innerHTML = '<input type="text" class="form-control form-control-sm rename-input" value="' + esc(original) + '" />';
        var input = element.querySelector('input');
        input.focus();

        var dot = original.lastIndexOf('.');
        if (selectBaseOnly && dot > 0) { input.setSelectionRange(0, dot); } else { input.select(); }

        var finished = false;

        function restore() {
            element.classList.remove('is-renaming');
            element.textContent = original;
        }

        function finish(ok) {
            if (finished) { return; }
            finished = true;

            var value = input.value.trim();

            if (!ok || value === '' || value === original) {
                restore();
                return;
            }

            commit(value, restore);
        }

        input.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Enter') { event.preventDefault(); finish(true); }
            if (event.key === 'Escape') { event.preventDefault(); finish(false); }
        });
        input.addEventListener('blur', function () { finish(true); });
        input.addEventListener('click', function (event) { event.stopPropagation(); });
        input.addEventListener('dblclick', function (event) { event.stopPropagation(); });
    }

    // ── Create folder ───────────────────────────────────────────────────

    function createFolder() {

        // Same gesture, same result: a row appears, named in place, and the
        // rest of what a group carries is one click away on its edit screen.
        if (insideCatalog()) {

            if (!newActions().folder) { return; }

            api({ type: 'explorer_catalog_create_group', group_id: state.groupId, name: L.new_product_group }, function (response) {

                if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

                state.pendingRename = { kind: 'group', id: response.group.id };
                reload();
            });

            return;
        }

        if (!canWriteCurrent()) { return; }

        api({ type: 'explorer_create_folder', folder_id: state.folderId, name: L.new_folder }, function (response) {
            if (response.status === 'success') {
                if (state.recycle.available) {
                    pushUndo({ undo: { type: 'bin', items: [{ kind: 'folder', id: response.folder.id }] }, redo: null });
                }
                state.pendingRename = { kind: 'folder', id: response.folder.id };
                reload();
            } else {
                toast(response.message || L.request_failed, false);
            }
        });
    }

    // Creates an empty text file and lets the person name it in place, the
    // same flow a new folder gets. Editing afterwards goes to edit_file.php
    // through the item's context menu or a double click.
    function createFile() {

        var folderId = createFileFolderId();

        if ((!newActions().file) || (folderId <= 0)) { return; }

        api({ type: 'explorer_create_file', folder_id: folderId, name: L.new_file + '.txt' }, function (response) {
            if (response.status === 'success') {
                if (state.recycle.available) {
                    pushUndo({ undo: { type: 'bin', items: [{ kind: 'file', id: response.file.id }] }, redo: null });
                }
                state.pendingRename = { kind: 'file', id: response.file.id };
                reload();
            } else {
                toast(response.message || L.request_failed, false);
            }
        });
    }

    // ── A picture drawn from nothing ────────────────────────────────────
    //
    // "New image" opens the picture editor on a blank canvas instead of on a
    // file, so a banner, a placeholder or a quick mock-up can be made here
    // rather than somewhere else and uploaded. A thousand square is a working
    // surface rather than a guess at a final size: every one of the editor's
    // tools resizes and crops, and starting square means neither dimension is
    // the one that has to be undone.
    //
    // There is nothing to replace, so the window is opened with Replace taken
    // out -- the footer then offers the format and one Save, which is the
    // whole decision. The endpoint is told the folder instead of a file id,
    // which is how it knows to make a row rather than write over one.
    var BLANK_IMAGE_SIZE = 1000;

    function createBlankImage() {

        var folderId = createFileFolderId();

        if ((!newActions().image) || (folderId <= 0)) { return; }

        loadAssets('image_editor', function () {

            if (typeof window.openImageEditor !== 'function') {
                toast(L.image_editor_missing, false);
                return;
            }

            var canvas = document.createElement('canvas');
            canvas.width = BLANK_IMAGE_SIZE;
            canvas.height = BLANK_IMAGE_SIZE;

            var context = canvas.getContext('2d');

            // White rather than transparent: the canvas is what the operator
            // draws on, and two of the three formats on offer cannot keep
            // transparency -- a jpg saved from a transparent canvas comes back
            // black, which reads as the tool having broken.
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, BLANK_IMAGE_SIZE, BLANK_IMAGE_SIZE);

            var blank;

            try { blank = canvas.toDataURL('image/png'); }
            catch (e) { toast(L.request_failed, false); return; }

            window.openImageEditor({
                src: blank,
                fileName: L.new_image_name + '.png',
                folderId: folderId,
                token: software_token,
                saveUrl: 'image_editor_save.php',
                allowReplace: false,
                // Nothing on disk yet, so there is no format to keep: the
                // footer asks for one outright instead of offering "the same
                // one", which here would mean the same as what.
                newFile: true,
                saveLabel: L.save,
                onSaved: function (response) {

                    response = response || {};

                    toast(fmt(L.new_image_saved, [response.name || '']), true);

                    // Somewhere other than where the operator is standing --
                    // the flat views create into the top folder -- so the
                    // screen goes to where the picture actually landed.
                    if (folderId !== state.folderId) { exitAllFiles(folderId); } else { reload(); }
                }
            });
        });
    }

    // ── Delete (selection in the content area) ──────────────────────────

    function deleteSelection() {
        var selected = selectedItems();

        if (selected.length === 0) { return; }

        // The Delete key means the same thing here as the menu item does, and
        // must not fall through to the folder tables.
        if (insideCatalog()) {
            // Inside the bin the delete key means delete for good, the same as
            // it does among the folders.
            if (insideCatalogBin()) { catalogPurge(selected); } else { catalogRecycle(selected); }
            return;
        }

        // Short links have no bin behind them either, and the same warning is
        // the right one whichever way the delete was asked for.
        if (insideShortLinks()) { deleteShortLinks(selected); return; }

        // Backup entries are files on disk with no bin behind them, so the
        // keyboard follows the same path the menu does.
        if (insideBackups()) { runMenuAction('backup_delete'); return; }

        if (!itemsDeletable(selected)) { return; }

        // Inside the bin the delete key means delete for good.
        if (insideBin()) {
            permanentDeleteSelection();
            return;
        }

        var payload = selected.map(function (item) { return { kind: item.kind, id: item.id }; });

        // With the bin installed, deleting just moves things there — any mix
        // of kinds, fully reversible. A folder that still has content asks
        // once before the whole subtree moves.
        if (state.recycle.available) {
            var fullFolders = selected.filter(function (item) { return item.kind === 'folder' && !item.empty; });

            // A lone non-empty folder asks for its own name to be typed.
            if (fullFolders.length === 1 && selected.length === 1) {
                openDeleteFolderModal(fullFolders[0].id, 'bin');
                return;
            }

            // Anything else -- several items, or a folder among them -- goes
            // through the same modal with one word typed instead of a name.
            // This is the delete most worth stopping for, and it used to be the
            // one that got the plain browser box.
            if ((selected.length > 1) || (fullFolders.length > 0)) {

                openDeleteSelectionModal(selected, 'bin', function (items, done) {

                    api({ type: 'explorer_recycle_delete', items: items }, function (response) {

                        done();

                        if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

                        undoToast(fmt(L.moved_to_bin_count, [items.length]),
                            pushUndo({ undo: { type: 'restore', items: items }, redo: { type: 'bin', items: items } }));

                        reload();
                    });
                });

                return;
            }

            api({ type: 'explorer_recycle_delete', items: payload }, function (response) {

                if (response.status === 'error') { toast(response.message || L.request_failed, false); return; }

                undoToast(fmt(L.moved_to_bin_count, [payload.length]),
                    pushUndo({ undo: { type: 'restore', items: payload }, redo: { type: 'bin', items: payload } }));

                reload();
            });
            return;
        }

        // No bin schema yet: permanent deletion with the old guard rails.
        var files = selected.filter(function (item) { return item.kind === 'file'; });
        var pages = selected.filter(function (item) { return item.kind === 'page'; });
        var folders = selected.filter(function (item) { return item.kind === 'folder'; });

        // Without the bin this is permanent, and a folder is deleted one at a
        // time so each one can be named. Files and pages have no such rule.
        if (folders.length > 0 && (files.length > 0 || pages.length > 0)) { toast(L.mixed_delete, false); return; }
        if (folders.length > 1) { toast(L.delete_one_folder, false); return; }

        if (folders.length === 1) {
            openDeleteFolderModal(folders[0].id);
            return;
        }

        openDeleteSelectionModal(selected, 'hard', function (items, done) {

            api({ type: 'explorer_hard_delete', items: items }, function (response) {
                done();
                toast(response.message || L.request_failed, response.status !== 'error');
                reload();
            });
        });
    }

    function permanentDeleteSelection() {
        var selected = selectedItems();

        if (selected.length === 0) { return; }

        var folders = selected.filter(function (item) { return item.kind === 'folder'; });

        if (folders.length > 0 && selected.length > 1) { toast(L.delete_one_folder, false); return; }

        if (folders.length === 1) {
            openDeleteFolderModal(folders[0].id);
            return;
        }

        openDeleteSelectionModal(selected, 'purge', function (items, done) {

            api({ type: 'explorer_hard_delete', items: items }, function (response) {
                done();
                toast(response.message || L.request_failed, response.status !== 'error');
                reload();
            });
        });
    }

    function restoreSelection() {
        var selected = selectedItems();

        if (selected.length === 0) { return; }

        var payload = selected.map(function (item) { return { kind: binKindOf(item), id: item.id }; });

        api({ type: 'explorer_recycle_restore', items: payload }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');

            if (response.status !== 'error') {
                pushUndo({ undo: { type: 'bin', items: payload }, redo: { type: 'restore', items: payload } });
            }

            reload();
        });
    }

    function emptyRecycleBin() {
        if (state.recycle.count === 0) { return; }

        if (!window.confirm(fmt(L.bin_confirm_empty, [state.recycle.count]))) { return; }

        api({ type: 'explorer_hard_delete', all: true }, function (response) {
            toast(response.message || L.request_failed, response.status !== 'error');
            reload();
        });
    }

    // ── Recursive folder delete (pre-flight + typed confirmation) ───────

    var deleteModal = null;
    var deletePlan = null;

    // mode 'hard' deletes for good, mode 'bin' moves the subtree to the
    // Recycle Bin. Both ask for the folder name — a subtree is too much to
    // lose (or misplace) over a slipped click.
    function openDeleteFolderModal(folderId, mode) {
        mode = mode || 'hard';

        api({ type: 'explorer_delete_check', item_folder_id: folderId }, function (response) {
            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                return;
            }

            deletePlan = response;
            deletePlan.mode = mode;

            var body = document.getElementById('folder_delete_body');
            var confirmButton = document.getElementById('folder_delete_confirm_button');
            var counts = response.counts;

            // The pre-flight blockers describe permanent deletion. Moving to
            // the bin keeps every record, so they do not apply there (the
            // server still re-checks the move itself).
            var blocked = (mode === 'hard') && (response.blockers && response.blockers.length > 0);

            document.getElementById('folder_delete_title_text').textContent = (mode === 'bin') ? L.move_to_bin : L.delete_folder_title;
            document.getElementById('folder_delete_button_text').textContent = (mode === 'bin') ? L.move_to_bin : L.delete;

            var summary = (mode === 'bin') ? L.bin_folder_confirm : L.delete_folder_summary;
            var html = '<p class="mb-2">' + fmt(esc(summary), ['<strong>' + esc(response.folder.name) + '</strong>']) + '</p>';

            if ((counts.folders + counts.pages + counts.files) === 0) {
                html += '<p class="empty-note">' + esc(L.delete_folder_empty) + '</p>';
            } else {
                html += '<div class="d-flex gap-3 mb-3">' +
                    '<span class="badge text-bg-secondary"><span class="bi bi-folder me-1"></span>' + counts.folders + ' ' + esc(L.folders) + '</span>' +
                    '<span class="badge text-bg-secondary"><span class="bi bi-window me-1"></span>' + counts.pages + ' ' + esc(L.pages) + '</span>' +
                    '<span class="badge text-bg-secondary"><span class="bi bi-file-earmark me-1"></span>' + counts.files + ' ' + esc(L.files) + '</span>' +
                    '</div>';
            }

            if (blocked) {
                html += '<div class="alert alert-warning mb-0">';
                response.blockers.forEach(function (blocker) {
                    html += '<p class="mb-1"><span class="bi bi-exclamation-triangle me-2"></span>' + esc(blocker) + '</p>';
                });
                html += '</div>';
                confirmButton.disabled = true;
            } else {
                html += '<label class="form-label">' + esc(L.type_name_to_confirm) + '</label>' +
                    '<input type="text" id="folder_delete_name_input" class="form-control" autocomplete="off" placeholder="' + esc(response.folder.name) + '" />' +
                    '<div class="form-text text-danger d-none" id="folder_delete_name_error">' + esc(L.name_does_not_match) + '</div>';
                confirmButton.disabled = true;
            }

            html += '<div id="folder_delete_progress" class="d-none mt-3"><div class="progress" style="height:6px;"><div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width:100%"></div></div><p class="mt-2 mb-0 empty-note">' + esc(L.deleting) + '...</p></div>';

            body.innerHTML = html;

            var nameInput = document.getElementById('folder_delete_name_input');

            if (nameInput) {
                nameInput.addEventListener('input', function () {
                    confirmButton.disabled = (nameInput.value.trim() !== response.folder.name);
                });
                nameInput.addEventListener('keydown', function (event) {
                    if ((event.key === 'Enter') && !confirmButton.disabled) { confirmButton.click(); }
                });
            }

            if (!deleteModal) {
                deleteModal = new bootstrap.Modal(document.getElementById('folder_delete_modal'));
            }

            deleteModal.show();
        });
    }

    // The confirmation for a selection.
    //
    // A single folder is confirmed by typing its name; several items have no
    // one name to type, and that is exactly the delete worth stopping for --
    // it used to be the plain browser confirm box while the careful one was
    // reserved for the smaller case. Same modal, same shape, one agreed word
    // instead of a name.
    function openDeleteSelectionModal(selected, mode, run) {

        // One badge per kind that is actually in the selection, store rows
        // included -- the same modal serves the folders and the catalog, so
        // neither area gets the plain browser box the other was spared.
        // A short link arrives dressed as a file, so it has to be counted
        // before files are, or the badge would call it one.
        var kinds = [
            { icon: 'bi-link-45deg', label: L.short_links, match: function (item) { return !!item.short_link; } },
            { icon: 'bi-folder', label: L.folders_word, match: function (item) { return item.kind === 'folder'; } },
            { icon: 'bi-window', label: L.pages, match: function (item) { return item.kind === 'page'; } },
            { icon: 'bi-file-earmark', label: L.files, match: function (item) { return (item.kind === 'file') && (!item.short_link); } },
            { icon: 'bi-boxes', label: L.product_groups_word, match: function (item) { return item.kind === 'group'; } },
            { icon: 'bi-box2-heart', label: L.products_word, match: function (item) { return item.kind === 'product'; } }
        ];

        var folderCount = selected.filter(function (item) { return item.kind === 'folder'; }).length;

        deletePlan = { mode: mode, run: run, selection: selected.map(function (item) { return { kind: binKindOf(item), id: item.id }; }) };

        var body = document.getElementById('folder_delete_body');
        var confirmButton = document.getElementById('folder_delete_confirm_button');

        document.getElementById('folder_delete_title_text').textContent = (mode === 'bin') ? L.move_to_bin : L.delete_many_title;
        document.getElementById('folder_delete_button_text').textContent = (mode === 'bin') ? L.move_to_bin : L.delete;

        var html = '<p class="mb-2">' + esc((mode === 'bin') ? L.delete_many_bin : L.delete_many_hard) + '</p>';

        html += '<div class="d-flex gap-3 mb-3 flex-wrap">';

        kinds.forEach(function (entry) {

            var count = selected.filter(entry.match).length;

            if (count > 0) {
                html += '<span class="badge text-bg-secondary"><span class="bi ' + entry.icon + ' me-1"></span>' + count + ' ' + esc(entry.label) + '</span>';
            }
        });

        html += '</div>';

        // Said out loud, because the badge above counts the folders the
        // operator picked and not what is standing inside them.
        if (folderCount > 0) {
            html += '<p class="empty-note mb-3">' + esc(L.delete_many_folder_note) + '</p>';
        }

        // Typing the word is for a deletion that takes something away without
        // warning. Emptying the Recycle Bin is not that: everything in it was
        // deleted once already, on purpose, and is sitting in the place whose
        // whole job is to hold things on their way out. Asking twice adds a
        // ceremony without adding anything the operator did not already know.
        if (mode !== 'purge') {

            html += '<label class="form-label">' + esc(fmt(L.type_word_to_confirm, [L.confirm_word])) + '</label>' +
                '<input type="text" id="folder_delete_name_input" class="form-control" autocomplete="off" placeholder="' + esc(L.confirm_word) + '" />' +
                '<div class="form-text text-danger d-none" id="folder_delete_name_error">' + esc(L.word_does_not_match) + '</div>';
        }

        html += '<div id="folder_delete_progress" class="d-none mt-3"><div class="progress" style="height:6px;"><div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width:100%"></div></div><p class="mt-2 mb-0 empty-note">' + esc(L.deleting) + '...</p></div>';

        body.innerHTML = html;
        confirmButton.disabled = (mode !== 'purge');

        var wordInput = document.getElementById('folder_delete_name_input');

        if (wordInput) {

            wordInput.addEventListener('input', function () {
                confirmButton.disabled = (wordInput.value.trim().toLocaleUpperCase() !== L.confirm_word.toLocaleUpperCase());
            });

            wordInput.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter') && !confirmButton.disabled) { confirmButton.click(); }
            });
        }

        if (!deleteModal) {
            deleteModal = new bootstrap.Modal(document.getElementById('folder_delete_modal'));
        }

        deleteModal.show();
    }

    // What the modal's own button does with a selection.
    function runSelectionDelete(plan) {

        var confirmButton = document.getElementById('folder_delete_confirm_button');
        var wordInput = document.getElementById('folder_delete_name_input');

        // No field means the mode does not ask for one; the check is on the
        // field's answer, not on its absence.
        if ((wordInput) && (wordInput.value.trim().toLocaleUpperCase() !== L.confirm_word.toLocaleUpperCase())) {
            var wordError = document.getElementById('folder_delete_name_error');
            if (wordError) { wordError.classList.remove('d-none'); }
            return;
        }

        confirmButton.disabled = true;

        if (wordInput) { wordInput.disabled = true; }

        document.getElementById('folder_delete_progress').classList.remove('d-none');

        // The modal only collects the confirmation. What a delete means differs
        // by area -- bin or for good, folder tables or catalog tables -- so the
        // caller that opened it is the one that carries it out.
        plan.run(plan.selection, function () {
            hideModal(deleteModal, 'folder_delete_modal');
            deletePlan = null;
        });
    }

    function runFolderDelete() {
        if (!deletePlan) { return; }

        var plan = deletePlan;

        // One modal, two jobs: a whole selection has no single folder to name.
        if (plan.selection) { runSelectionDelete(plan); return; }
        var confirmButton = document.getElementById('folder_delete_confirm_button');
        var nameInput = document.getElementById('folder_delete_name_input');

        if (!nameInput || nameInput.value.trim() !== plan.folder.name) {
            var errorNote = document.getElementById('folder_delete_name_error');
            if (errorNote) { errorNote.classList.remove('d-none'); }
            return;
        }

        confirmButton.disabled = true;
        nameInput.disabled = true;
        document.getElementById('folder_delete_progress').classList.remove('d-none');

        var request = (plan.mode === 'bin')
            ? { type: 'explorer_recycle_delete', items: [{ kind: 'folder', id: plan.folder.id }] }
            : { type: 'explorer_hard_delete', items: [{ kind: 'folder', id: plan.folder.id }] };

        api(request, function (response) {
            hideModal(deleteModal, 'folder_delete_modal');
            deletePlan = null;

            var deletedSetHasCurrent = plan.folders_bottom_up.some(function (entry) {
                return String(entry.id) === String(state.folderId);
            });

            if (plan.mode === 'bin') {
                toast(response.message || L.request_failed, response.status !== 'error');

                if (response.status !== 'error') {
                    pushUndo({
                        undo: { type: 'restore', items: [{ kind: 'folder', id: plan.folder.id }] },
                        redo: { type: 'bin', items: [{ kind: 'folder', id: plan.folder.id }] }
                    });
                }
            } else if (response.status === 'success') {
                toast(L.delete_folder_done);
            } else {
                toast(response.message || L.delete_folder_partial, false);
            }

            // When the folder the user is looking at (or one of its parents)
            // just went away, land on the deleted folder's parent.
            if (deletedSetHasCurrent) {
                load(plan.folder.parent_id, highlightTree);
            } else {
                reload();
            }
        });
    }

    document.getElementById('folder_delete_confirm_button').addEventListener('click', runFolderDelete);

    // ── Drag & drop (internal move/copy + uploads from the computer) ────

    function dragHasType(event, type) {
        return !!(event.dataTransfer && event.dataTransfer.types && (Array.prototype.indexOf.call(event.dataTransfer.types, type) !== -1));
    }

    // What is being dragged is read off the data store, not off dragState. A
    // drag that starts here always carries its own marker; dragState is only
    // the fallback for a drag with no readable type list at all. When the tile
    // a drag started on is re-rendered before dragend can bubble up from it,
    // dragState is left behind -- and with it in charge, every file dragged in
    // from the computer after that was taken for a move and quietly dropped.
    function isInternalDrag(event) {
        if (dragHasType(event, 'application/x-pinegrap-items')) { return true; }
        if (dragHasType(event, 'Files')) { return false; }
        return !!dragState;
    }

    function isFileDrag(event) {
        return dragHasType(event, 'Files') && !isInternalDrag(event);
    }

    // Where files dragged in from the computer can land from here.
    //
    //   'folder'  -- the folder on the screen, written straight away
    //   'backups' -- the backup directory, likewise
    //   'window'  -- a flat view (Files, Pictures): there is no folder in front
    //                of you, so the upload window opens with the drop already
    //                in it and asks which folder, as the Upload button does
    //   'denied'  -- a folder you may look at but not write to
    //   null      -- nowhere: the bin, the shared list, the store, the short
    //                links, the pages list
    function fileDropTarget() {
        if (insideBackups()) { return 'backups'; }
        if (insideCatalog() || insideShortLinks() || insideShared() || insideBin()) { return null; }
        if (state.mode === 'all') { return newActions().upload ? 'window' : null; }
        return canWriteCurrent() ? 'folder' : 'denied';
    }

    function attachDragHandlers() {
        var content = document.getElementById('explorer_content');

        content.addEventListener('dragstart', function (event) {
            var element = event.target.closest('.explorer-item');
            if (!element) { return; }

            // Backup entries are files on disk with no record to move.
            if (insideBackups()) { event.preventDefault(); return; }

            // The bin is a place things are taken out of, not moved around in.
            if (insideCatalogBin()) { event.preventDefault(); return; }

            var item = findItem(element.getAttribute('data-kind'), element.getAttribute('data-id'));
            if (!item) { return; }

            // While the browser is capturing the drag image and entering the
            // native drag loop, the page must hold still: restyling the
            // dragged tile or loading a preview here has frozen the tab on
            // Windows. Selection is therefore updated a tick later, and the
            // preview pane stays untouched until the drag ends.
            dragPreviewHold = true;

            var items;

            if (state.selection[itemKey(item)]) {
                items = selectedItems();
            } else {
                items = [item];
                window.setTimeout(function () { if (dragState) { setSelection([item], item); } }, 0);
            }

            items = items.filter(function (selectedItem) {
                if (selectedItem.kind === 'file') { return selectedItem.can_edit; }
                if (selectedItem.kind === 'folder') { return selectedItem.can_edit && !selectedItem.is_root; }
                return true;
            });

            if (items.length === 0) { dragPreviewHold = false; event.preventDefault(); return; }

            dragState = items.map(function (dragItem) { return { kind: dragItem.kind, id: dragItem.id, source_folder_id: sourceFolderOf(dragItem) }; });
            event.dataTransfer.effectAllowed = 'copyMove';
            // Drop whatever payload the browser put in on its own (a drag
            // started on a thumbnail carries the image URL, and dropping
            // that outside a target would navigate the whole tab to it).
            try { event.dataTransfer.clearData(); } catch (e) { }
            try { event.dataTransfer.setData('application/x-pinegrap-items', JSON.stringify(dragState)); } catch (e) { }
            try { event.dataTransfer.setData('text/plain', items.map(function (dragItem) { return dragItem.name; }).join(', ')); } catch (e) { }

            // A small static badge as the drag image, so the browser never
            // has to rasterize the live tile (mid-hover, mid-transition).
            try {
                var badge = document.createElement('div');
                badge.id = 'explorer_drag_badge';
                badge.textContent = (items.length > 1) ? (items.length + ' ' + L.items) : item.name;
                document.body.appendChild(badge);
                event.dataTransfer.setDragImage(badge, 14, 14);
                window.setTimeout(function () { if (badge.parentNode) { badge.parentNode.removeChild(badge); } }, 0);
            } catch (e) { }
        });

        content.addEventListener('dragend', function () {
            dragState = null;
            dragPreviewHold = false;
            clearDropTargets();
            hideUploadOverlay();

            var selected = selectedItems();
            renderPreview(selected.length === 1 ? selected[0] : null);
        });

        function overContent(event) {
            return !!(event.target && event.target.closest && event.target.closest('#explorer_content'));
        }

        // dragenter is cancelled as well as dragover: the specification lets
        // a browser hand the whole drag to the body when the first dragenter
        // is not, and then the drop never reaches the element it fell on.
        ['dragenter', 'dragover'].forEach(function (name) {
            document.addEventListener(name, function (event) {
                if (!isFileDrag(event)) { return; }

                event.preventDefault();

                if (name !== 'dragover') { return; }

                var target = overContent(event) ? fileDropTarget() : null;

                // The cursor says what letting go will do: a copy where the
                // drop can land, the no-drop sign where nothing could take it.
                // A folder that cannot be written keeps the copy cursor and
                // explains itself on the drop instead.
                try { event.dataTransfer.dropEffect = (target === null) ? 'none' : 'copy'; } catch (e) { }

                if (target && (target !== 'denied')) { showUploadOverlay(); } else { hideUploadOverlay(); }
            });
        });

        document.addEventListener('drop', function (event) {
            if (!isFileDrag(event)) { return; }

            event.preventDefault();
            hideUploadOverlay();

            // Whatever a move left behind is over now: the browser is holding
            // files from the computer, not tiles from this screen.
            dragState = null;

            if (!overContent(event)) { return; }

            var target = fileDropTarget();

            if (target === 'backups') {
                // A backup directory takes whole folders, so the drop is
                // walked rather than read as a flat file list.
                collectDroppedEntries(event.dataTransfer).then(uploadToBackups);
                return;
            }

            if (target === 'window') {
                // The window is opened before the walk finishes so it is on
                // the screen the moment the files are let go; they arrive in
                // it as the browser hands them over.
                openUploadModal();
                collectDroppedEntries(event.dataTransfer).then(addUploadEntries);
                return;
            }

            if (target === 'denied') { toast(L.cannot_paste_here, false); return; }

            if (target === null) { return; }

            // A dropped folder is not in dataTransfer.files -- it arrives
            // there as a single zero-length entry with the folder's name,
            // which FileReader then fails on ("assets: request failed"). The
            // entry API is what actually walks it, and it is what the backup
            // browser has always used.
            collectDroppedEntries(event.dataTransfer).then(uploadEntries);
        });

        document.addEventListener('dragleave', function (event) {
            if (event.target === document.documentElement || !event.relatedTarget) { hideUploadOverlay(); }
        });

        // Internal move targets: folder items, breadcrumb links, tree rows.
        // The dragover is always cancelled — dropEffect "none" outside a
        // target keeps the no-drop cursor while making sure the browser
        // never applies its default drop action (navigation) anywhere.
        document.addEventListener('dragover', function (event) {
            if (!isInternalDrag(event)) { return; }

            var target = dropTargetFolder(event);
            clearDropTargets();

            event.preventDefault();

            if (target) {
                event.dataTransfer.dropEffect = (event.ctrlKey || event.altKey) ? 'copy' : 'move';
                target.element.classList.add('drop-target');
            } else {
                event.dataTransfer.dropEffect = 'none';
            }
        });

        document.addEventListener('drop', function (event) {
            if (!isInternalDrag(event)) { return; }

            event.preventDefault();

            var target = dropTargetFolder(event);
            clearDropTargets();

            if (!target || !dragState) { dragState = null; return; }

            var items = dragState;
            dragState = null;

            transferItemsTo(target.id, items, (event.ctrlKey || event.altKey));
        });

        // Safety net: whatever is dragged over the admin (an image out of the
        // preview panel, a link from another window), releasing it must never
        // make the browser navigate this tab. Inputs are left alone so text
        // can still be dropped into the search box.
        function editableDropTarget(event) {
            return event.target && event.target.closest && !!event.target.closest('input, textarea, [contenteditable="true"]');
        }

        document.addEventListener('dragover', function (event) {
            if (!editableDropTarget(event)) { event.preventDefault(); }
        });

        document.addEventListener('drop', function (event) {
            if (!editableDropTarget(event)) { event.preventDefault(); }
        });
    }

    function dropTargetFolder(event) {
        var selector = insideCatalog()
            ? '.explorer-item[data-kind="group"], #explorer_breadcrumb a[data-catalog-id], #explorer_tree_pane .tree-row[data-folder-id]'
            : '.explorer-item[data-kind="folder"], #explorer_breadcrumb a[data-folder-id], #explorer_tree_pane .tree-row[data-folder-id]';

        var element = event.target.closest && event.target.closest(selector);

        if (!element) { return null; }

        var id;

        if (element.hasAttribute('data-folder-id')) {
            id = parseInt(element.getAttribute('data-folder-id'), 10);
        } else if (element.hasAttribute('data-catalog-id')) {
            id = parseInt(element.getAttribute('data-catalog-id'), 10);
        } else {
            id = parseInt(element.getAttribute('data-id'), 10);
        }

        if (!id || id <= 0) { return null; }

        // Never offer a dragged folder or group itself as its own target.
        if (dragState && dragState.some(function (dragItem) {
            return ((dragItem.kind === 'folder') || (dragItem.kind === 'group')) && (String(dragItem.id) === String(id));
        })) {
            return null;
        }

        return { id: id, element: element };
    }

    function clearDropTargets() {
        document.querySelectorAll('.drop-target').forEach(function (element) { element.classList.remove('drop-target'); });
    }

    function showUploadOverlay() {
        var overlay = document.getElementById('explorer_upload_overlay');
        var target = fileDropTarget();
        if (overlay && target && (target !== 'denied')) { overlay.style.display = 'flex'; }
    }

    function hideUploadOverlay() {
        var overlay = document.getElementById('explorer_upload_overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    // ── Uploads ─────────────────────────────────────────────────────────

    // Walk a drop that may contain folders.
    //
    // dataTransfer.files flattens a dropped folder to nothing useful - the
    // directory arrives as a zero-length entry with no children - so the
    // entry API is used when the browser offers it, and the plain file list
    // is the fallback. Returns [{ relative, file }].
    function collectDroppedEntries(dataTransfer) {
        var items = dataTransfer.items;

        // The plain list is copied out now, while the drop event is still
        // being dispatched. The data store closes once the handler returns,
        // and after that dataTransfer.files reads as empty -- so a fallback
        // that went back to it from inside a callback found nothing there,
        // and a drop the entry API could not read ended in silence.
        var plain = Array.prototype.slice.call(dataTransfer.files || []).map(function (file) {
            return { relative: file.name, file: file };
        });

        if (!items || !items.length || !items[0].webkitGetAsEntry) {
            return Promise.resolve(plain);
        }

        var roots = [];

        for (var index = 0; index < items.length; index++) {
            var entry = items[index].webkitGetAsEntry();
            if (entry) { roots.push(entry); }
        }

        function readEntry(entry, prefix) {
            if (entry.isFile) {
                return new Promise(function (resolve) {
                    entry.file(function (file) {
                        resolve([{ relative: prefix + entry.name, file: file }]);
                    }, function () {
                        // The entry would not open, but the same file may
                        // still be in the plain list under its own name.
                        resolve(plain.filter(function (candidate) { return (prefix === '') && (candidate.file.name === entry.name); }));
                    });
                });
            }

            if (!entry.isDirectory) { return Promise.resolve([]); }

            var reader = entry.createReader();

            // readEntries() hands back at most a hundred children per call,
            // so it is called until it returns nothing.
            function readBatch(collected) {
                return new Promise(function (resolve) {
                    reader.readEntries(function (batch) {
                        if (!batch.length) { resolve(collected); return; }
                        resolve(readBatch(collected.concat(Array.prototype.slice.call(batch))));
                    }, function () { resolve(collected); });
                });
            }

            return readBatch([]).then(function (children) {
                return Promise.all(children.map(function (child) {
                    return readEntry(child, prefix + entry.name + '/');
                })).then(function (lists) {
                    return [].concat.apply([], lists);
                });
            });
        }

        return Promise.all(roots.map(function (entry) { return readEntry(entry, ''); })).then(function (lists) {

            var walked = [].concat.apply([], lists);

            // The entry API is there but gave nothing back, while the plain
            // list has files in it: take the plain list rather than dropping
            // the operator's files on the floor.
            if ((walked.length === 0) && (plain.length > 0)) { return plain; }

            return walked;
        });
    }

    // Upload a walked drop into the backup directory, one file at a time so
    // a large folder does not have to be held in memory all at once.
    function uploadToBackups(entries) {
        if (!entries || entries.length === 0) { return; }

        var maxBytes = state.caps.upload_max_bytes || 0;
        var uploaded = 0;
        var failed = 0;
        var queue = entries.slice();

        setProgress(L.uploading, 0, entries.length);

        function next() {
            var entry = queue.shift();

            if (!entry) {
                clearProgress();
                if (uploaded > 0) { toast(fmt(L.upload_done, [uploaded])); }
                reload();
                return;
            }

            setProgress(L.uploading, uploaded + failed, entries.length);

            if (maxBytes > 0 && entry.file.size > maxBytes) {
                toast(entry.file.name + ': ' + fmt(L.upload_too_large, [state.caps.upload_max_label || '']), false);
                failed++;
                next();
                return;
            }

            var reader = new FileReader();

            reader.onload = function () {
                api({
                    type: 'explorer_backup_upload',
                    path: state.backupPath || '',
                    relative: entry.relative,
                    data: reader.result
                }, function (response) {
                    if (response.status === 'success') { uploaded++; } else { failed++; toast(response.message || L.request_failed, false); }
                    next();
                }, function () {
                    // A request that never came back still has to move the
                    // queue on, or the bar sits at the same number for good.
                    failed++;
                    toast(entry.file.name + ': ' + L.request_failed, false);
                    next();
                });
            };

            reader.onerror = function () { failed++; next(); };
            reader.readAsDataURL(entry.file);
        }

        next();
    }

    // A plain file list is a walk with no folders in it.
    function uploadFiles(fileList) {

        uploadEntries(Array.prototype.slice.call(fileList || []).map(function (file) {
            return { relative: file.name, file: file };
        }));
    }

    // Upload a walked drop into the current folder, keeping the shape it was
    // dropped in: every directory in a path becomes a folder here before the
    // file inside it is sent. One file at a time, so a large folder is never
    // held in memory all at once.
    //
    // A folder is created rather than merged into one of the same name -- that
    // is what explorer_create_folder does with a name already in use, and it is
    // the choice that cannot quietly overwrite what is already on the site.
    function uploadEntries(entries, options) {

        // A drag onto the screen has no window open and therefore no answers:
        // it lands where you are standing, with nothing written on it and
        // nothing done to it. The window fills these in.
        options = options || {};

        var intoFolderId = (options.folderId !== undefined) ? options.folderId : state.folderId;

        if ((options.folderId === undefined) && (!canWriteCurrent())) { toast(L.cannot_paste_here, false); return; }

        entries = (entries || []).filter(function (entry) { return (entry && entry.file); });

        // A name the server would run or read as its own settings is not
        // sent at all. Said here, by name, because a drop has no list on the
        // screen to mark the file in -- the server refuses it too, but only
        // after the bytes have travelled.
        var blockedNames = [];

        entries = entries.filter(function (entry) {
            if (uploadNameBlocked(entry.relative || entry.file.name)) { blockedNames.push(entry.file.name); return false; }
            return true;
        });

        if (blockedNames.length > 0) {
            toast(fmt(L.upload_blocked_named, [blockedNames.slice(0, 3).join(', ') + (blockedNames.length > 3 ? ' +' + (blockedNames.length - 3) : '')]), false);
        }

        if (entries.length === 0) { return; }

        var maxBytes = state.caps.upload_max_bytes || 0;
        var uploaded = 0;
        var uploadedIds = [];
        var rootFolderIds = [];
        var folderIds = { '': intoFolderId };
        var queue = entries.slice();

        // The folder a relative path lives in, created on the way down and
        // remembered, so a hundred files in one directory make one folder.
        function folderFor(path, done) {

            if (folderIds[path] !== undefined) { done(folderIds[path]); return; }

            var cut = path.lastIndexOf('/');
            var parentPath = (cut === -1) ? '' : path.slice(0, cut);
            var name = (cut === -1) ? path : path.slice(cut + 1);

            folderFor(parentPath, function (parentId) {

                if (parentId === null) { folderIds[path] = null; done(null); return; }

                api({ type: 'explorer_create_folder', folder_id: parentId, name: name }, function (response) {

                    if ((response.status !== 'success') || (!response.folder)) {
                        toast(response.message || L.request_failed, false);
                        folderIds[path] = null;
                        done(null);
                        return;
                    }

                    folderIds[path] = response.folder.id;

                    if (parentPath === '') { rootFolderIds.push(response.folder.id); }

                    done(response.folder.id);
                }, function () {
                    toast(name + ': ' + L.request_failed, false);
                    folderIds[path] = null;
                    done(null);
                });
            });
        }

        function next() {

            var entry = queue.shift();

            if (!entry) {
                clearProgress();

                if (uploaded > 0) {

                    // Undo takes back the folders that were made and the files
                    // that landed loose beside them; a file inside a new folder
                    // goes with the folder.
                    var undoItems = rootFolderIds.map(function (id) { return { kind: 'folder', id: id }; })
                        .concat(uploadedIds.map(function (id) { return { kind: 'file', id: id }; }));

                    if (undoItems.length > 0 && state.recycle.available) {
                        pushUndo({ undo: { type: 'bin', items: undoItems }, redo: null });
                    }

                    toast(fmt(L.upload_done, [uploaded]));

                    // Uploaded somewhere other than where you are standing:
                    // the screen goes there, because a file you cannot see is
                    // indistinguishable from one that did not arrive.
                    if (intoFolderId !== state.folderId) { exitAllFiles(intoFolderId); } else { reload(); }
                }
                return;
            }

            setProgress(L.uploading, uploaded, entries.length);

            var file = entry.file;

            if (maxBytes > 0 && file.size > maxBytes) {
                toast(file.name + ': ' + fmt(L.upload_too_large, [state.caps.upload_max_label || '']), false);
                next();
                return;
            }

            var relative = String(entry.relative || file.name);
            var cut = relative.lastIndexOf('/');
            var directory = (cut === -1) ? '' : relative.slice(0, cut);

            folderFor(directory, function (folderId) {

                if (folderId === null) { next(); return; }

                var reader = new FileReader();

                reader.onload = function () {
                    api({
                        type: 'explorer_upload',
                        target_folder_id: folderId,
                        name: file.name,
                        description: options.description || '',
                        design: !!options.design,
                        to_webp: !!options.toWebp,
                        optimize: !!options.optimize,
                        data: reader.result
                    }, function (response) {

                        if (response.status === 'success') {
                            uploaded++;
                            // Only a file dropped straight into this folder is
                            // listed for undo on its own; the rest are inside a
                            // folder that is already on the list.
                            if ((directory === '') && response.file && response.file.id) { uploadedIds.push(response.file.id); }
                        } else {
                            toast(response.message || L.request_failed, false);
                        }

                        next();
                    }, function () {
                        // A request that never came back still has to move
                        // the queue on, or the bar sits at the same number
                        // for good.
                        toast(file.name + ': ' + L.request_failed, false);
                        next();
                    });
                };

                reader.onerror = function () { toast(file.name + ': ' + L.request_failed, false); next(); };
                reader.readAsDataURL(file);
            });
        }

        setProgress(L.uploading, 0, entries.length);
        next();
    }

    // ── The upload window ───────────────────────────────────────────────
    //
    // It collects a selection and some answers, then hands both to
    // uploadEntries() above -- the same walk, the same one-file-per-request
    // send, the same undo. Nothing is uploaded while the window is open, so
    // closing it leaves nothing behind.

    var uploadModal = null;
    var uploadPicked = [];
    var uploadFolderReady = false;

    // Sizes in the list. Not convert_bytes_to_string(): that one is on the
    // server, and this list is built before anything is sent.
    function uploadSizeLabel(bytes) {

        if (bytes >= (1024 * 1024 * 1024)) { return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB'; }
        if (bytes >= (1024 * 1024)) { return (bytes / (1024 * 1024)).toFixed(1) + ' MB'; }
        if (bytes >= 1024) { return Math.round(bytes / 1024) + ' KB'; }

        return bytes + ' B';
    }

    // What goes at the front of a row: the picture itself when it is one, and
    // the glyph for its kind when it is not.
    //
    // A picture is shown from a blob URL rather than read into a data URL. The
    // file is already on the machine; reading a hundred of them into memory to
    // draw a twenty-eight pixel square is work nobody asked for, and the whole
    // point of this uploader is not holding the selection in memory at once.
    // Each URL is released when the row it belongs to goes.
    function uploadMarkHtml(entry) {

        if (String(entry.file.type || '').indexOf('image/') === 0) {

            if (!entry.preview) { entry.preview = URL.createObjectURL(entry.file); }

            return '<img class="upload-thumb" src="' + esc(entry.preview) + '" alt="" loading="lazy" />';
        }

        var extension = String(entry.file.name || '').split('.').pop().toLowerCase();

        return '<span class="upload-mark bi ' + (FILETYPE_ICONS[extension] || 'bi-file-earmark') + '"></span>';
    }

    // A blob URL stays alive until it is let go, and the browser keeps the file
    // behind it. Cleared whenever the selection is.
    function releaseUploadPreviews(entries) {

        (entries || []).forEach(function (entry) {

            if (entry.preview) {
                URL.revokeObjectURL(entry.preview);
                entry.preview = null;
            }
        });
    }

    // Adds to what is already picked rather than replacing it, so a folder and
    // a few loose files can be sent together. The same path twice is the same
    // file twice -- picking a folder again after changing your mind about it
    // should not send everything in it a second time.
    function addUploadEntries(entries) {

        var seen = {};

        uploadPicked.forEach(function (entry) { seen[entry.relative] = true; });

        (entries || []).forEach(function (entry) {

            if ((!entry) || (!entry.file) || (seen[entry.relative])) { return; }

            seen[entry.relative] = true;
            uploadPicked.push(entry);
        });

        renderUploadPicked();
    }

    // Whether the server will refuse this file for its name.
    //
    // The lists come from the server with the listing rather than being
    // written out again here: one rule, two places that read it, no chance of
    // the window promising something the upload then refuses. Asked early so a
    // refusal is visible while the file can still be taken out of the list.
    //
    // The same rule everywhere, with the backup folder's one exception: the
    // .htaccess that keeps that folder off the web may be put back by hand.
    function uploadNameBlocked(relative) {

        var base = String(relative || '').split('/').pop().toLowerCase();
        var backupArea = insideBackups();
        var allowed = backupArea ? (state.caps.backup_allowed_names || []) : [];
        var names = backupArea ? (state.caps.backup_blocked_names || []) : (state.caps.blocked_names || []);
        var blocked = backupArea ? (state.caps.backup_blocked_extensions || []) : (state.caps.blocked_extensions || []);

        if (allowed.indexOf(base) !== -1) { return false; }
        if (base === '') { return true; }

        // A dotfile has no name in front of its extension, which is the shape
        // the server refuses outright -- .htaccess above is the one exception.
        if (base.charAt(0) === '.') { return true; }
        if (names.indexOf(base) !== -1) { return true; }

        // Every extension in the name, not only the last: "shell.php.jpg" is
        // served as PHP by a misconfigured Apache.
        var parts = base.split('.');

        parts.shift();

        for (var i = 0; i < parts.length; i++) {
            if (blocked.indexOf(parts[i]) !== -1) { return true; }
        }

        return false;
    }

    function renderUploadPicked() {

        var box = document.getElementById('upload_picked');
        var button = document.getElementById('upload_start_button');
        var maxBytes = state.caps.upload_max_bytes || 0;

        if (uploadPicked.length === 0) {
            box.classList.add('d-none');
            box.innerHTML = '';
            button.disabled = true;
            return;
        }

        if (!uploadFolderReady) { button.disabled = true; }

        var backupArea = insideBackups();
        var total = 0;
        var oversize = 0;
        var refused = 0;
        var html = '';

        uploadPicked.forEach(function (entry) {

            var tooBig = ((maxBytes > 0) && (entry.file.size > maxBytes));
            var blocked = uploadNameBlocked(entry.relative);

            if (blocked) { refused++; }
            if (tooBig) { oversize++; }
            if ((!tooBig) && (!blocked)) { total += entry.file.size; }

            html += '<div class="upload-row' + (tooBig ? ' is-too-big' : '') + (blocked ? ' is-blocked' : '') + '">' +
                uploadMarkHtml(entry) +
                '<span class="upload-path" title="' + esc(entry.relative) + '">' + esc(entry.relative) + '</span>' +
                '<span class="upload-size">' + esc(blocked ? L.upload_not_allowed_here : uploadSizeLabel(entry.file.size)) + '</span>' +
                '</div>';
        });

        var heading = fmt(L.upload_picked_count, [uploadPicked.length, uploadSizeLabel(total)]);

        // Said before the upload rather than after: a file the server will
        // refuse is worth knowing about while it can still be taken out.
        if (oversize > 0) {
            heading += ' — ' + fmt(L.upload_over_limit, [oversize]);
        }

        if (refused > 0) {
            heading += ' — ' + fmt(backupArea ? L.upload_refused_count : L.upload_blocked_count, [refused]);
        }

        box.innerHTML = '<div class="d-flex align-items-baseline gap-2 mb-1">' +
            '<span class="fw-semibold flex-grow-1">' + esc(heading) + '</span>' +
            '<button type="button" class="btn btn-sm btn-link p-0" id="upload_clear">' + esc(L.clear) + '</button>' +
            '</div>' + html;

        box.classList.remove('d-none');

        document.getElementById('upload_clear').addEventListener('click', function () {
            releaseUploadPreviews(uploadPicked);
            uploadPicked = [];
            renderUploadPicked();
        });

        // Nothing left to send once the oversized and the refused are taken
        // out -- and they cannot both count the same file, so the sum is a
        // ceiling rather than an exact figure. Comparing against the count
        // rather than the sum keeps a file that is both from disabling nothing.
        var sendable = uploadPicked.filter(function (entry) {
            if ((maxBytes > 0) && (entry.file.size > maxBytes)) { return false; }
            if (uploadNameBlocked(entry.relative)) { return false; }
            return true;
        }).length;

        button.disabled = ((sendable === 0) || (!uploadFolderReady));
    }

    // The folder list, fresh every time the window opens.
    //
    // Rendered into the page once at load, it went stale the moment anybody
    // made a folder: the new folder was not among the options, so setting the
    // select to it did nothing and the browser left the first option showing.
    // Standing in a folder you had just made, the window offered to upload
    // into the top of the site.
    // Fills a <select> with the folders this operator may write into, fresh
    // from the server, and picks one. Shared by the upload window and the
    // file window: the list is the same list, and the reason it is fetched
    // each time is the same too -- rendered into the page once at load it
    // went stale the moment anybody made a folder.
    function loadFolderOptionsInto(select, preselectId, done) {

        select.innerHTML = '<option value="">' + esc(L.loading) + '</option>';
        select.disabled = true;

        api({ type: 'explorer_folder_options' }, function (response) {

            select.disabled = false;

            if ((response.status !== 'success') || (!response.folders)) {
                select.innerHTML = '';
                toast(response.message || L.request_failed, false);
                done(false);
                return;
            }

            var html = '';

            response.folders.forEach(function (folder) {

                var indent = '';

                for (var level = 0; level < folder.depth; level++) { indent += '&nbsp;&nbsp;'; }

                html += '<option value="' + folder.id + '">' + indent +
                    esc(folder.name + (folder.archived ? (' [' + L.archived + ']') : '')) + '</option>';
            });

            select.innerHTML = html;
            select.value = String(preselectId);

            // The folder you are in is not always one you may write into --
            // a read-only folder is browsable and not writable -- so a target
            // that is not on the list falls back to the first one that is.
            if (select.selectedIndex === -1) { select.selectedIndex = 0; }

            done(true);
        });
    }

    function loadUploadFolders(preselectId, done) {

        uploadFolderReady = false;

        loadFolderOptionsInto(document.getElementById('upload_folder'), preselectId, function (ok) {

            if (ok) {
                uploadFolderReady = true;
                renderUploadPicked();
            }

            if (done) { done(); }
        });
    }

    // Which of the three pickers this screen has any use for.
    //
    // The two flat views list what is in every folder at once, so there is no
    // folder in front of you to build a tree into -- a folder picked here would
    // be made somewhere the view does not show. And the picture view lists only
    // pictures: a file chosen there would upload correctly and then not appear,
    // which reads as the upload having failed.
    //
    // Dropping is left alone. A drop is explicit about what it carries, and
    // narrowing what somebody has already dragged in would be second-guessing
    // them.
    function updateUploadPickers() {

        var backupArea = insideBackups();
        var flatView = (state.mode === 'all');
        var listingImages = ((!backupArea) && flatView && (state.allFilter === 'images'));

        document.getElementById('upload_pick_files').classList.toggle('d-none', listingImages);
        document.getElementById('upload_pick_folder').classList.toggle('d-none', (!backupArea) && flatView);

        // A picture picker in the backup folder offers the one thing that is
        // never brought back to it; an archive picker is what belongs there.
        document.getElementById('upload_pick_images').classList.toggle('d-none', backupArea);
        document.getElementById('upload_pick_zip').classList.toggle('d-none', !backupArea);

        document.getElementById('upload_dropzone_title').textContent = listingImages
            ? L.drop_pictures_here
            : ((flatView && !backupArea) ? L.drop_files_here : L.drop_files_or_folders_here);
    }

    function openUploadModal() {

        releaseUploadPreviews(uploadPicked);
        uploadPicked = [];

        updateUploadPickers();

        // The backup folder is not one of the site's folders and has no record
        // behind what lands in it: there is nothing to choose, nothing to
        // describe, and no picture to convert or shrink. The destination is
        // shown instead of picked, and everything that does not apply is taken
        // out rather than left there doing nothing.
        var backupArea = insideBackups();
        var backupPath = (state.backupPath || '');

        document.getElementById('upload_folder').classList.toggle('d-none', backupArea);
        document.getElementById('upload_backup_path').classList.toggle('d-none', !backupArea);
        document.getElementById('upload_description_col').classList.toggle('d-none', backupArea);
        document.getElementById('upload_switches').classList.toggle('d-none', backupArea);
        document.getElementById('upload_folder_note').textContent = backupArea
            ? L.backup_upload_note
            : L.upload_folder_note;

        if (backupArea) {

            uploadFolderReady = true;
            document.getElementById('upload_backup_path').value = L.backups + (backupPath ? (' / ' + backupPath) : '');

        } else {

            // Where you are standing, and in a view across folders the top one --
            // the rule add_file.php has always used, kept because an upload from a
            // view that spans everything has no folder of its own to land in.
            loadUploadFolders(createFileFolderId());
        }

        document.getElementById('upload_description').value = '';
        document.getElementById('upload_error').classList.add('d-none');

        // The three switches are remembered per browser: somebody who converts
        // to webp does it every time, and somebody who does not never wants to
        // find it switched on.
        ['design', 'webp', 'optimize'].forEach(function (key) {
            document.getElementById('upload_' + key).checked = (storageGet('pg_upload_' + key) === '1');
        });

        document.getElementById('upload_limit_note').textContent = (state.caps.upload_max_label)
            ? fmt(L.upload_limit_note, [state.caps.upload_max_label])
            : '';

        renderUploadPicked();

        // Only the two buttons close it. A click that lands beside the window
        // is nearly always a miss, and it was throwing away a selection that
        // had just been dragged in.
        if (!uploadModal) {
            uploadModal = new bootstrap.Modal(document.getElementById('upload_modal'), {
                backdrop: 'static',
                keyboard: false
            });
        }

        uploadModal.show();
    }

    function startUpload() {

        if (uploadPicked.length === 0) { return; }

        // The backup folder has its own sender: real files on disk, addressed
        // by path, with no folder record to write and no picture work to do.
        if (insideBackups()) {

            var backupEntries = uploadPicked.filter(function (entry) { return !uploadNameBlocked(entry.relative); });

            releaseUploadPreviews(uploadPicked);
            uploadPicked = [];
            renderUploadPicked();
            hideModal(uploadModal, 'upload_modal');

            uploadToBackups(backupEntries);
            return;
        }

        var options = {
            folderId: parseInt(document.getElementById('upload_folder').value, 10) || 0,
            description: document.getElementById('upload_description').value,
            design: document.getElementById('upload_design').checked,
            toWebp: document.getElementById('upload_webp').checked,
            optimize: document.getElementById('upload_optimize').checked
        };

        storageSet('pg_upload_design', options.design ? '1' : '0');
        storageSet('pg_upload_webp', options.toWebp ? '1' : '0');
        storageSet('pg_upload_optimize', options.optimize ? '1' : '0');

        // What the list already marked as refused stays behind; the server
        // would say no to it anyway, and said so in the list first.
        var entries = uploadPicked.filter(function (entry) { return !uploadNameBlocked(entry.relative); });

        releaseUploadPreviews(uploadPicked);
        uploadPicked = [];

        // Emptied as well as released: a revoked blob address left sitting in
        // the markup is a broken picture waiting for the next time the window
        // is opened.
        renderUploadPicked();

        // Closed before the sending starts: the progress bar is on the screen
        // behind it, which is also where the files are about to appear.
        hideModal(uploadModal, 'upload_modal');

        uploadEntries(entries, options);
    }

    // ── Folder tree sidebar ─────────────────────────────────────────────

    var treeRootList = document.getElementById('explorer_tree_root');
    var treeExpanded = {};

    try { treeExpanded = JSON.parse(storageGet('pg_explorer_tree') || '{}') || {}; } catch (e) { treeExpanded = {}; }

    function saveTreeExpanded() { storageSet('pg_explorer_tree', JSON.stringify(treeExpanded)); }

    function loadTreeChildren(listElement, nodeId, done) {

        if (insideCatalog()) { loadCatalogTreeChildren(listElement, nodeId, done); return; }

        api({ type: 'explorer_tree', node_id: nodeId }, function (response) {
            if (response.status !== 'success') { if (done) { done(); } return; }

            listElement.innerHTML = '';

            response.children.forEach(function (node) {
                var li = document.createElement('li');
                var expanded = !!treeExpanded[node.id];

                li.innerHTML =
                    '<div class="tree-row' + ((state.mode !== 'all' && node.id === state.folderId) ? ' active' : '') + '" data-folder-id="' + node.id + '"' +
                    ' data-name="' + esc(node.name) + '" data-parent-id="' + node.parent_id + '" data-can-edit="' + (node.can_edit ? '1' : '0') + '" data-is-root="' + (node.is_root ? '1' : '0') + '" data-empty="' + (node.empty ? '1' : '0') + '">' +
                    '<span class="tree-toggle bi ' + (node.has_children ? ('bi-chevron-right' + (expanded ? ' open' : '')) : '') + '"></span>' +
                    '<span class="bi bi-folder-fill ' + node.access_control_type + '"></span>' +
                    '<span class="tree-label' + (node.archived ? ' archived' : '') + '" data-role="tree-name" title="' + esc(node.name) + '">' + esc(node.name) + '</span>' +
                    '</div><ul style="display:' + (expanded ? 'block' : 'none') + '"></ul>';

                listElement.appendChild(li);

                if (expanded && node.has_children) {
                    loadTreeChildren(li.querySelector('ul'), node.id);
                }
            });

            if (done) { done(); }
        });
    }

    // Same shape as loadTreeChildren, against the catalog. The row keeps the
    // data-folder-id attribute the rest of the tree code reads, so highlighting,
    // expansion and the tree context menu need no second implementation.
    function loadCatalogTreeChildren(listElement, nodeId, done) {

        api({ type: 'explorer_catalog_tree', node_id: nodeId }, function (response) {

            if (response.status !== 'success') { if (done) { done(); } return; }

            listElement.innerHTML = '';

            response.children.forEach(function (node) {

                var li = document.createElement('li');
                var expanded = !!treeExpanded[node.id];
                var icon = (node.catalog_role === 'variant_set') ? 'bi-collection-fill' : 'bi-boxes';

                li.innerHTML =
                    '<div class="tree-row' + ((node.id === state.groupId) ? ' active' : '') + (node.enabled ? '' : ' catalog-off') + '" data-folder-id="' + node.id + '"' +
                    ' data-name="' + esc(node.name) + '" data-parent-id="' + node.parent_id + '" data-can-edit="0" data-is-root="' + (node.is_root ? '1' : '0') + '" data-empty="' + (node.empty ? '1' : '0') + '" data-has-children="' + (node.has_children ? '1' : '0') + '" data-enabled="' + (node.enabled ? '1' : '0') + '">' +
                    '<span class="tree-toggle bi ' + (node.has_children ? ('bi-chevron-right' + (expanded ? ' open' : '')) : '') + '"></span>' +
                    '<span class="bi ' + icon + ' text-primary"></span>' +
                    '<span class="tree-label' + (node.enabled ? '' : ' archived') + '" data-role="tree-name" title="' + esc(node.name) + '">' + esc(node.name) + '</span>' +
                    ((node.count > 0) ? ('<span class="tree-count">' + node.count + '</span>') : '') +
                    '</div><ul style="display:' + (expanded ? 'block' : 'none') + '"></ul>';

                listElement.appendChild(li);

                if (expanded && node.has_children) {
                    loadCatalogTreeChildren(li.querySelector('ul'), node.id);
                }
            });

            if (done) { done(); }
        });
    }

    // ── Shortcuts above the tree ────────────────────────────────────────
    //
    // The tree remembers which branches were left open, which gets an operator
    // most of the way back. What it does not give is one click to a folder from
    // wherever they happen to be standing, and on a real site that walk is
    // three or four levels every time.
    //
    // Both lists live in this browser and nowhere else: whether the folders one
    // person works in should follow them to another machine is a question worth
    // a column in the user table, and the answer here was no.

    var RECENT_LIMIT = 5;

    function shortcutKey(name) {
        return 'pg_explorer_' + name + '_' + state.area;
    }

    function shortcutRead(name) {

        try {
            var list = JSON.parse(storageGet(shortcutKey(name)) || '[]');
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function shortcutWrite(name, list) {
        storageSet(shortcutKey(name), JSON.stringify(list));
    }

    // Where the screen is standing, if that is a place worth remembering. The
    // flat lists, the bin, the backup browser and the shared report are views
    // rather than folders -- they are one click away in the sidebar already.
    function shortcutHere() {

        if (insideCatalog()) {

            if ((state.allFilter !== '') || !(state.groupId > 0)) { return null; }

            return { id: state.groupId, name: (state.catalogCurrent && state.catalogCurrent.name) ? state.catalogCurrent.name : '' };
        }

        if ((state.mode !== 'browse') || !(state.folderId > 0)) { return null; }

        return { id: state.folderId, name: (state.current && state.current.name) ? state.current.name : '' };
    }

    function recordRecent() {

        var here = shortcutHere();

        if ((!here) || (here.name === '')) { return; }

        // A pinned folder never enters this list. It is visited often by
        // definition, so it would sit at the front for good and hold one of
        // five places against every other folder -- while already being one
        // row further up the sidebar.
        if (isPinned(here.id)) { return; }

        var list = shortcutRead('recent').filter(function (entry) { return String(entry.id) !== String(here.id); });

        list.unshift(here);
        shortcutWrite('recent', list.slice(0, RECENT_LIMIT));
        renderShortcuts();
    }

    function isPinned(id) {
        return shortcutRead('pinned').some(function (entry) { return String(entry.id) === String(id); });
    }

    function togglePin(id, name) {

        var list = shortcutRead('pinned');
        var without = list.filter(function (entry) { return String(entry.id) !== String(id); });

        var pinning = (without.length === list.length);

        if (pinning) { without.push({ id: id, name: name }); }

        shortcutWrite('pinned', without);

        // Pinning takes the folder out of the recent list, where it would
        // otherwise be repeating the row above it.
        if (pinning) {
            shortcutWrite('recent', shortcutRead('recent').filter(function (entry) { return String(entry.id) !== String(id); }));
        }

        renderShortcuts();
    }

    function renderShortcuts() {

        var here = insideCatalog() ? state.groupId : state.folderId;
        var live = insideCatalog() ? (state.allFilter === '') : (state.mode === 'browse');
        var icon = insideCatalog() ? 'bi-boxes' : 'bi-folder-fill';

        [['pinned', 'explorer_pinned'], ['recent', 'explorer_recent']].forEach(function (pair) {

            // Trimmed on the way out as well as on the way in, so a list
            // written by an older build is drawn at today's length rather
            // than waiting for the next visit to shorten it.
            var list = shortcutRead(pair[0]);

            if (pair[0] === 'recent') { list = list.slice(0, RECENT_LIMIT); }
            var container = document.getElementById(pair[1]);
            var wrap = document.getElementById(pair[1] + '_wrap');
            var html = '';

            list.forEach(function (entry) {

                html += '<div class="tree-row' + ((live && (String(entry.id) === String(here))) ? ' active' : '') +
                    '" data-shortcut-id="' + entry.id + '" title="' + esc(entry.name) + '">' +
                    '<span class="tree-toggle"></span><span class="bi ' + icon + ' text-primary"></span>' +
                    '<span class="tree-label">' + esc(entry.name) + '</span></div>';
            });

            container.innerHTML = html;
            wrap.classList.toggle('d-none', list.length === 0);
        });
    }

    function highlightTree() {
        var activeId = insideCatalog() ? state.groupId : state.folderId;
        // The flat lists and the bin are views across the whole catalog, so no
        // node in the tree is the place they are showing.
        var treeLive = insideCatalog() ? (state.allFilter === '') : (state.mode !== 'all');

        document.querySelectorAll('#explorer_tree_pane .tree-row[data-folder-id]').forEach(function (element) {
            element.classList.toggle('active', treeLive && String(element.getAttribute('data-folder-id')) === String(activeId));
        });

        var productsRow = document.getElementById('all_products_quick');
        if (productsRow) { productsRow.classList.toggle('active', insideCatalog() && (state.allFilter === 'products')); }

        var variantsRow = document.getElementById('all_variant_sets_quick');
        if (variantsRow) { variantsRow.classList.toggle('active', insideCatalog() && (state.allFilter === 'variants')); }

        document.getElementById('all_files_quick').classList.toggle('active', (state.mode === 'all') && (state.allFilter === ''));
        document.getElementById('all_images_quick').classList.toggle('active', (state.mode === 'all') && (state.allFilter === 'images'));
        document.getElementById('all_pages_quick').classList.toggle('active', (state.mode === 'all') && (state.allFilter === 'pages'));
        document.getElementById('short_links_quick').classList.toggle('active', state.mode === 'short_links');

        var sharedRow = document.getElementById('shared_quick');
        if (sharedRow) { sharedRow.classList.toggle('active', state.mode === 'shared'); }

        var backupsRow = document.getElementById('backups_quick');
        if (backupsRow) { backupsRow.classList.toggle('active', state.mode === 'backups'); }

        // Every way of arriving somewhere ends here, which makes this the
        // one place the two lists can be kept true without threading a call
        // through six loaders.
        recordRecent();
        renderShortcuts();
    }

    // The views across everything belong to one area each.
    //
    // Switching mode is not the same as changing screens: the sidebar, the
    // tree and the address bar are drawn for the area this page was opened in
    // and do not follow. Crossing over is a link to the other screen, so these
    // refuse rather than leave a half-changed page behind.
    function enterAllFiles(filter, scope) {
        if (state.area === 'catalog') { return; }
        state.mode = 'all';
        state.allFilter = ((filter === 'images') || (filter === 'pages')) ? filter : '';
        // A narrowing belongs to the Files view alone, and picking the view
        // again from the menu is a way of asking for all of it.
        state.fileScope = ((state.allFilter === '') && scope) ? scope : '';
        load(0, highlightTree);
    }

    // The label a narrowing wears: on the button, in the breadcrumb, in the
    // window title.
    function fileScopeLabel(scope) {
        if (!scope) { return L.all_files; }
        return L['scope_' + scope] || L[scope] || scope;
    }

    // The narrowing button is a fact about the Files view and nothing else,
    // so it is drawn from state on every load rather than left where the last
    // view put it.
    function syncFileScope() {

        var wrap = document.getElementById('file_scope_wrap');

        if (!wrap) { return; }

        var showing = ((state.mode === 'all') && (state.allFilter === ''));

        wrap.classList.toggle('d-none', !showing);

        if (!showing) { return; }

        document.getElementById('file_scope_label').textContent = fileScopeLabel(state.fileScope);

        wrap.querySelectorAll('[data-scope]').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-scope') === (state.fileScope || ''));
        });
    }

    function enterShared() {
        if (state.area === 'catalog') { return; }
        state.mode = 'shared';
        state.allFilter = '';
        load(0, highlightTree);
    }

    function enterShortLinks() {
        if (state.area === 'catalog') { return; }
        state.mode = 'short_links';
        state.allFilter = '';
        load(0, highlightTree);
    }

    function exitAllFiles(folderId) {
        state.mode = 'browse';
        state.allFilter = '';
        state.fileScope = '';
        load(folderId, highlightTree);
    }

    function insideShared() { return state.mode === 'shared'; }

    function beginTreeRename(node) {
        var label = node.row.querySelector('[data-role="tree-name"]');
        if (!label) { return; }

        // The tree row is a product group in the store and a folder among the
        // files. Same box, same keys, two different tables behind it.
        var renameType = insideCatalog() ? 'explorer_catalog_rename' : 'explorer_rename';
        var renameKind = insideCatalog() ? 'group' : 'folder';
        var undoType = insideCatalog() ? 'catalog_rename' : 'rename';

        startInlineRename(label, node.name, false, function (value, restore) {
            api({ type: renameType, item_kind: renameKind, item_id: node.id, name: value }, function (response) {
                if (response.status === 'success') {
                    pushUndo({
                        undo: { type: undoType, kind: renameKind, id: node.id, name: node.name },
                        redo: { type: undoType, kind: renameKind, id: node.id, name: response.name || value }
                    });
                    toast(L.renamed);
                    reload();
                } else {
                    toast(response.message || L.request_failed, false);
                    restore();
                }
            });
        });
    }

    // The tree's own "New Product Group", so a group can be added anywhere in
    // the catalog without first walking the grid into its parent.
    function createGroupInTree(node) {

        api({ type: 'explorer_catalog_create_group', group_id: node.id, name: L.new_product_group }, function (response) {

            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            treeExpanded[node.id] = true;
            saveTreeExpanded();

            var childList = node.row.parentElement.querySelector('ul');
            var toggle = node.row.querySelector('.tree-toggle');

            toggle.classList.add('bi-chevron-right', 'open');
            childList.style.display = 'block';

            loadCatalogTreeChildren(childList, node.id, function () {

                var newRow = childList.querySelector('.tree-row[data-folder-id="' + response.group.id + '"]');

                if (newRow) {
                    beginTreeRename({ id: response.group.id, name: response.group.name, row: newRow });
                }
            });
        });
    }

    function createFolderInTree(node) {
        api({ type: 'explorer_create_folder', folder_id: node.id, name: L.new_folder }, function (response) {
            if (response.status !== 'success') {
                toast(response.message || L.request_failed, false);
                return;
            }

            // Expand the parent, refresh its children and rename in place.
            treeExpanded[node.id] = true;
            saveTreeExpanded();

            var childList = node.row.parentElement.querySelector('ul');
            var toggle = node.row.querySelector('.tree-toggle');
            toggle.classList.add('bi-chevron-right', 'open');
            childList.style.display = 'block';

            loadTreeChildren(childList, node.id, function () {
                var newRow = childList.querySelector('.tree-row[data-folder-id="' + response.folder.id + '"]');
                if (newRow) {
                    beginTreeRename({
                        id: response.folder.id,
                        name: response.folder.name,
                        row: newRow
                    });
                }
            });
        });
    }

    document.getElementById('explorer_tree_pane').addEventListener('click', function (event) {
        var toggle = event.target.closest('.tree-toggle');
        var row = event.target.closest('.tree-row');

        // Only real folder rows: the quick entries above the tree share the
        // row styling but handle their own clicks.
        if (!row || !row.hasAttribute('data-folder-id') || event.target.closest('input')) { return; }

        var nodeId = parseInt(row.getAttribute('data-folder-id'), 10);
        var childList = row.parentElement.querySelector('ul');

        if (toggle && toggle.classList.contains('bi-chevron-right') && !toggle.classList.contains('open')) {
            toggle.classList.add('open');
            childList.style.display = 'block';
            treeExpanded[nodeId] = true;
            saveTreeExpanded();
            loadTreeChildren(childList, nodeId);
            return;
        }

        if (toggle && toggle.classList.contains('open')) {
            toggle.classList.remove('open');
            childList.style.display = 'none';
            delete treeExpanded[nodeId];
            saveTreeExpanded();
            return;
        }

        // In the store the tree carries groups, not folders, and clicking one
        // must stay in the store: exitAllFiles() would drop the mode back to
        // the folder browser and load a folder with the group's id.
        if (insideCatalog()) {
            state.allFilter = '';
            load(nodeId, highlightTree);
            return;
        }

        exitAllFiles(nodeId);
    });

    // The two shortcut lists navigate the same way the tree does, and carry
    // their own attribute rather than data-folder-id: the handler above and
    // highlightTree() both reach for that one, and a shortcut is not a node of
    // the tree -- it has no children to expand and no place in it.
    document.getElementById('explorer_tree_pane').addEventListener('click', function (event) {

        var row = event.target.closest('[data-shortcut-id]');

        if (!row) { return; }

        var shortcutId = parseInt(row.getAttribute('data-shortcut-id'), 10);

        if (insideCatalog()) {
            state.allFilter = '';
            load(shortcutId, highlightTree);
            return;
        }

        exitAllFiles(shortcutId);
    });

    // The bridge rows are plain navigation on purpose. Each area keeps its own
    // address so the left menu highlights the right entry and the back button
    // does what it looks like it should; the shell is the same either way, so
    // crossing over does not feel like leaving the screen.
    var variantsQuick = document.getElementById('all_variant_sets_quick');
    if (variantsQuick) { variantsQuick.addEventListener('click', function () { enterAllVariantSets(); }); }

    // The same two, from the toolbar menu -- that menu is the only way to
    // reach them on a screen too narrow for the sidebar.
    var productsMenuItem = document.getElementById('all_products_menu_item');
    if (productsMenuItem) { productsMenuItem.addEventListener('click', function () { enterAllProducts(); }); }

    var variantsMenuItem = document.getElementById('all_variant_sets_menu_item');
    if (variantsMenuItem) { variantsMenuItem.addEventListener('click', function () { enterAllVariantSets(); }); }

    var commerceAccessItem = document.getElementById('commerce_access_menu_item');
    if (commerceAccessItem) { commerceAccessItem.addEventListener('click', function () { openCommerceAccessPanel(); }); }

    var productsQuick = document.getElementById('all_products_quick');
    if (productsQuick) { productsQuick.addEventListener('click', function () { enterAllProducts(); }); }


    var foldersRootItem = document.getElementById('folders_root_menu_item');
    if (foldersRootItem) { foldersRootItem.addEventListener('click', function () { exitAllFiles(0); }); }

    var catalogRootItem = document.getElementById('catalog_root_menu_item');
    if (catalogRootItem) { catalogRootItem.addEventListener('click', function () { enterCatalogRoot(); }); }

    document.getElementById('file_scope_menu').addEventListener('click', function (event) {

        var button = event.target.closest('[data-scope]');

        if (!button) { return; }

        enterAllFiles('', button.getAttribute('data-scope'));
    });

    document.getElementById('all_files_quick').addEventListener('click', function () { enterAllFiles(); });
    document.getElementById('all_files_menu_item').addEventListener('click', function () { enterAllFiles(); });
    document.getElementById('all_images_quick').addEventListener('click', function () { enterAllFiles('images'); });
    document.getElementById('all_images_menu_item').addEventListener('click', function () { enterAllFiles('images'); });
    document.getElementById('all_pages_quick').addEventListener('click', function () { enterAllFiles('pages'); });
    document.getElementById('all_pages_menu_item').addEventListener('click', function () { enterAllFiles('pages'); });
    document.getElementById('short_links_quick').addEventListener('click', function () { enterShortLinks(); });
    document.getElementById('short_links_menu_item').addEventListener('click', function () { enterShortLinks(); });
    document.getElementById('new_short_link_button').addEventListener('click', function () { openShortLinkWizard(); });

    document.getElementById('create_backup_button').addEventListener('click', function () { createBackup(); });

    document.getElementById('import_zip_button').addEventListener('click', function () {

        if (!importZipModal) { importZipModal = new bootstrap.Modal(document.getElementById('import_zip_modal')); }

        importZipModal.show();
    });

    var importDesignZipModal = null;
    document.getElementById('import_design_zip_button').addEventListener('click', function () {

        if (!importDesignZipModal) { importDesignZipModal = new bootstrap.Modal(document.getElementById('import_design_zip_modal')); }

        importDesignZipModal.show();
    });

    // ── The upload window's own handlers ────────────────────────────────

    (function () {

        var zone = document.getElementById('upload_dropzone');
        var pickFiles = document.getElementById('upload_input_files');
        var pickImages = document.getElementById('upload_input_images');
        var pickZip = document.getElementById('upload_input_zip');
        var pickFolder = document.getElementById('upload_input_folder');

        document.getElementById('upload_pick_files').addEventListener('click', function () { pickFiles.click(); });
        document.getElementById('upload_pick_images').addEventListener('click', function () { pickImages.click(); });
        document.getElementById('upload_pick_zip').addEventListener('click', function () { pickZip.click(); });

        // The whole area answers a double click, which is what the old upload
        // screen did and what a box with a dashed border looks like it should.
        zone.addEventListener('dblclick', function (event) {

            if (event.target.closest('button')) { return; }

            // Whichever of them is on offer here.
            var filesButton = document.getElementById('upload_pick_files');

            if (!filesButton.classList.contains('d-none')) { pickFiles.click(); }
            else if (!document.getElementById('upload_pick_images').classList.contains('d-none')) { pickImages.click(); }
            else { pickZip.click(); }
        });
        document.getElementById('upload_pick_folder').addEventListener('click', function () { pickFolder.click(); });

        // The three flat pickers differ only in what the dialog offers, so they
        // are read the same way.
        [pickFiles, pickImages, pickZip].forEach(function (input) {

            input.addEventListener('change', function () {

                addUploadEntries(Array.prototype.slice.call(this.files || []).map(function (file) {
                    return { relative: file.name, file: file };
                }));

                // Cleared so picking the same file again still counts as a change.
                this.value = '';
            });
        });

        // A directory picker reports each file's path inside the chosen folder,
        // which is the same shape collectDroppedEntries() returns -- so the
        // folder is rebuilt on the site either way.
        pickFolder.addEventListener('change', function () {

            addUploadEntries(Array.prototype.slice.call(this.files || []).map(function (file) {
                return { relative: file.webkitRelativePath || file.name, file: file };
            }));

            this.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function (event) {
                if (!isFileDrag(event)) { return; }
                event.preventDefault();
                event.stopPropagation();
                zone.classList.add('is-over');
            });
        });

        ['dragleave', 'dragend'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.remove('is-over'); });
        });

        zone.addEventListener('drop', function (event) {

            if (!isFileDrag(event)) { return; }

            event.preventDefault();
            event.stopPropagation();
            zone.classList.remove('is-over');

            collectDroppedEntries(event.dataTransfer).then(addUploadEntries);
        });

        document.getElementById('upload_start_button').addEventListener('click', startUpload);
        document.getElementById('upload_file_button').addEventListener('click', openUploadModal);
    })();

    // Both toolbar entries ask for the whole catalog: the toolbar is not where
    // a selection lives. Picking rows out is the right button's job.
    ['export_products_csv_item', 'export_products_xlsx_item'].forEach(function (id) {

        document.getElementById(id).querySelector('button').addEventListener('click', function () {
            submitProductExport(this.getAttribute('data-export-format'), [], []);
        });
    });
    document.getElementById('short_link_create_button').addEventListener('click', function () { submitShortLinkWizard(); });

    // Only drawn for managers and above; the server refuses the request for
    // anybody else anyway.
    if (document.getElementById('shared_quick')) {
        document.getElementById('shared_quick').addEventListener('click', enterShared);
        document.getElementById('shared_menu_item').addEventListener('click', enterShared);
        document.getElementById('backups_quick').addEventListener('click', function () { enterBackups(''); });
        document.getElementById('backups_menu_item').addEventListener('click', function () { enterBackups(''); });
    }

    document.getElementById('explorer_tree_pane').addEventListener('contextmenu', function (event) {
        var row = event.target.closest('.tree-row');
        if (!row || !row.hasAttribute('data-folder-id') || event.target.closest('input')) { return; }

        event.preventDefault();

        if ((Date.now() - lastLongPressAt) < 700) { return; }

        showTreeMenu(event, treeNodeFromRow(row));
    });

    // ── Access permissions panel ────────────────────────────────────────

    var accessOffcanvas = null;
    var settingsOffcanvas = null;
    var bulkPagesOffcanvas = null;
    var bulkFilesOffcanvas = null;
    var bulkProductsOffcanvas = null;
    var permOffcanvas = null;

    // Who may run the store.
    //
    // The store's answer to the folder access panel, with one difference that
    // decides its whole shape: a folder's rights belong to that folder, while
    // commerce rights belong to the user and cover everything. So this is one
    // panel for the area rather than one per product group -- a per-group
    // switch would promise a separation the storefront does not enforce.
    function openCommerceAccessPanel() {

        api({ type: 'explorer_catalog_access_get' }, function (response) {

            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('access_offcanvas_body');

            document.getElementById('access_offcanvas_label').innerHTML =
                '<span class="bi bi-shield-lock me-2"></span>' + esc(L.commerce_access);

            var html = '<p class="form-text mt-0">' + esc(L.commerce_access_note) + '</p>';

            if ((!response.can_manage_users) || (response.users.length === 0)) {

                html += '<p class="empty-note">' + esc(L.no_basic_users) + '</p>';
                body.innerHTML = html;

            } else {

                html += '<div id="commerce_access_list">';

                response.users.forEach(function (userRow) {

                    html += '<div class="border-bottom pb-2 mb-2" data-user-id="' + userRow.id + '">' +
                        '<div class="fw-semibold text-break mb-1">' + esc(userRow.username) + '</div>' +
                        switchRow('manage', userRow.id, L.commerce_manage, userRow.manage) +
                        switchRow('reports', userRow.id, L.commerce_reports, userRow.reports) +
                        (response.offline_payment ? switchRow('offline_payment', userRow.id, L.commerce_offline_payment, userRow.offline_payment) : '') +
                        '</div>';
                });

                html += '</div><div class="d-grid mt-3"><button type="button" class="btn btn-primary" id="commerce_access_save_button">' + esc(L.save) + '</button></div>';

                body.innerHTML = html;

                document.getElementById('commerce_access_save_button').addEventListener('click', function () {

                    var payload = [];

                    body.querySelectorAll('[data-user-id]').forEach(function (row) {

                        payload.push({
                            id: parseInt(row.getAttribute('data-user-id'), 10),
                            manage: row.querySelector('[data-right="manage"]').checked,
                            reports: row.querySelector('[data-right="reports"]').checked,
                            offline_payment: (row.querySelector('[data-right="offline_payment"]') || { checked: false }).checked
                        });
                    });

                    api({ type: 'explorer_catalog_access_set', users: payload }, function (saveResponse) {
                        toast(saveResponse.message || L.request_failed, saveResponse.status !== 'error');
                        if (saveResponse.status !== 'error') { accessOffcanvas.hide(); }
                    });
                });
            }

            if (!accessOffcanvas) {
                accessOffcanvas = new bootstrap.Offcanvas(document.getElementById('access_offcanvas'));
            }

            accessOffcanvas.show();
        });
    }

    function switchRow(right, userId, label, checked) {

        var id = 'commerce_' + right + '_' + userId;

        return '<div class="form-check form-switch">' +
            '<input class="form-check-input" type="checkbox" role="switch" id="' + id + '" data-right="' + right + '"' + (checked ? ' checked' : '') + ' />' +
            '<label class="form-check-label small" for="' + id + '">' + esc(label) + '</label></div>';
    }

    function openAccessPanel(item) {
        api({ type: 'explorer_folder_access_get', item_folder_id: item.id }, function (response) {
            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('access_offcanvas_body');

            // The two panels share one offcanvas, so each sets its own title.
            document.getElementById('access_offcanvas_label').innerHTML =
                '<span class="bi bi-shield-lock me-2"></span>' + esc(L.access_permissions);
            var folder = response.folder;

            var options = '';
            var types = ['', 'public', 'guest', 'registration', 'membership', 'private'];

            types.forEach(function (type) {
                var label = type === '' ? (L.default + ' (' + L.inherit + ')') : accessLabel(type);
                options += '<option value="' + type + '" class="' + type + '"' + (folder.own_access_control_type === type ? ' selected' : '') + '>' + esc(label) + '</option>';
            });

            var inheritNote = '';

            if (folder.own_access_control_type === '') {
                inheritNote = '<div class="form-text"><span class="' + folder.access_control_type + '">' + esc(accessLabel(folder.access_control_type)) + '</span> — ' +
                    esc(folder.inherited_from ? fmt(L.inherited_from, [folder.inherited_from]) : L.inherited_from_parent) + '</div>';
            }

            var usersHtml = '';

            if (response.can_manage_users) {
                if (response.users.length === 0) {
                    usersHtml = '<p class="empty-note">' + esc(L.no_basic_users) + '</p>';
                } else {
                    usersHtml = '<table class="table table-sm align-middle"><thead><tr><th>' + esc(L.user_column) + '</th><th>' + esc(L.rights_column) + '</th><th></th></tr></thead><tbody>';

                    response.users.forEach(function (userRow) {
                        var inherited = '';

                        if (userRow.inherited_rights > 0) {
                            inherited = '<span class="badge text-bg-secondary" title="' + esc(L.inherited_from_parent) + '">' +
                                esc(userRow.inherited_rights === 2 ? L.edit_access : L.view_access) + ' • ' + esc(L.inherit) + '</span>';
                        }

                        usersHtml += '<tr data-user-id="' + userRow.id + '">' +
                            '<td class="text-break">' + esc(userRow.username) + '<div>' + inherited + '</div></td>' +
                            '<td><select class="form-select form-select-sm access-rights-select">' +
                            '<option value="0"' + (userRow.rights === 0 ? ' selected' : '') + '>' + esc(L.no_access) + '</option>' +
                            '<option value="1"' + (userRow.rights === 1 ? ' selected' : '') + '>' + esc(L.view_access) + '</option>' +
                            '<option value="2"' + (userRow.rights === 2 ? ' selected' : '') + '>' + esc(L.edit_access) + '</option>' +
                            '</select></td>' +
                            '<td><input type="date" class="form-control form-control-sm access-expiration" title="' + esc(L.expiration_date) + '" value="' + esc(userRow.expiration_date || '') + '"' + (userRow.rights === 1 ? '' : ' style="display:none"') + ' /></td>' +
                            '</tr>';
                    });

                    usersHtml += '</tbody></table><div class="form-text">' + esc(L.rights_note) + '</div>';
                }
            }

            body.innerHTML =
                '<h6 class="text-break mb-3"><span class="bi bi-folder-fill me-2 ' + folder.access_control_type + '"></span>' + esc(folder.name) + '</h6>' +
                '<div class="mb-3">' +
                '<label class="form-label">' + esc(L.access_control) + '</label>' +
                '<select id="access_type_select" class="form-select">' + options + '</select>' +
                inheritNote +
                '<div class="form-text">' + esc(L.access_note) + '</div>' +
                '</div>' +
                (usersHtml ? '<hr /><h6>' + esc(L.access_permissions) + '</h6>' + usersHtml : '') +
                '<div class="mt-3 text-end"><button type="button" id="access_save_button" class="btn btn-success"><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

            body.querySelectorAll('.access-rights-select').forEach(function (select) {
                select.addEventListener('change', function () {
                    var expiration = select.closest('tr').querySelector('.access-expiration');
                    expiration.style.display = (select.value === '1') ? '' : 'none';
                });
            });

            document.getElementById('access_save_button').addEventListener('click', function () {
                var payload = {
                    type: 'explorer_folder_access_set',
                    item_folder_id: folder.id,
                    access_control_type: document.getElementById('access_type_select').value
                };

                if (response.can_manage_users && response.users.length > 0) {
                    payload.user_rights = [];
                    body.querySelectorAll('tr[data-user-id]').forEach(function (row) {
                        payload.user_rights.push({
                            user_id: parseInt(row.getAttribute('data-user-id'), 10),
                            rights: parseInt(row.querySelector('.access-rights-select').value, 10),
                            expiration_date: row.querySelector('.access-expiration').value || ''
                        });
                    });
                }

                api(payload, function (saveResponse) {
                    toast(saveResponse.message || L.request_failed, saveResponse.status === 'success');
                    if (saveResponse.status === 'success') {
                        accessOffcanvas.hide();
                        reload();
                    }
                });
            });

            if (!accessOffcanvas) {
                accessOffcanvas = new bootstrap.Offcanvas(document.getElementById('access_offcanvas'));
            }

            accessOffcanvas.show();
        });
    }

    // ── Folder settings panel (order, archive, page styles) ─────────────

    function openFolderSettings(folderId) {
        api({ type: 'explorer_folder_settings_get', item_folder_id: folderId }, function (response) {
            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('settings_offcanvas_body');
            var folder = response.folder;

            // The style dropdowns arrive as ready-made option lists from the
            // same helpers edit_folder.php uses, inherited-style labels and
            // all. Only managers and above get them, like on that screen.
            var stylesHtml = '';

            if (response.styles) {
                stylesHtml =
                    '<hr />' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="settings_style_select">' + esc(L.desktop_style) + '</label>' +
                    '<select id="settings_style_select" class="form-select">' + response.styles.desktop + '</select>' +
                    '</div>' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="settings_mobile_style_select">' + esc(L.mobile_style) + '</label>' +
                    '<select id="settings_mobile_style_select" class="form-select">' + response.styles.mobile + '</select>' +
                    '<div class="form-text">' + esc(L.styles_note) + '</div>' +
                    '</div>';
            }

            body.innerHTML =
                '<h6 class="text-break mb-3"><span class="bi bi-folder-fill me-2 ' + folder.access_control_type + '"></span>' + esc(folder.name) + '</h6>' +
                '<div class="mb-3">' +
                '<label class="form-label" for="settings_order_input">' + esc(L.sorting) + '</label>' +
                '<input type="number" id="settings_order_input" class="form-control" inputmode="numeric" value="' + esc(String(folder.order)) + '" />' +
                '<div class="form-text">' + esc(L.sorting_note) + '</div>' +
                '</div>' +
                '<div class="form-check form-switch mb-3">' +
                '<input class="form-check-input" type="checkbox" role="switch" id="settings_archived_input"' + (folder.archived ? ' checked' : '') + ' />' +
                '<label class="form-check-label" for="settings_archived_input">' + esc(L.archive) + '</label>' +
                '<div class="form-text">' + esc(L.archive_note) + '</div>' +
                '</div>' +
                stylesHtml +
                '<div class="mt-3 text-end"><button type="button" id="settings_save_button" class="btn btn-success"><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

            document.getElementById('settings_save_button').addEventListener('click', function () {
                var payload = {
                    type: 'explorer_folder_settings_set',
                    item_folder_id: folder.id,
                    order: document.getElementById('settings_order_input').value,
                    archived: document.getElementById('settings_archived_input').checked ? '1' : ''
                };

                if (response.styles) {
                    payload.style = document.getElementById('settings_style_select').value;
                    payload.mobile_style_id = document.getElementById('settings_mobile_style_select').value;
                }

                api(payload, function (saveResponse) {
                    toast(saveResponse.message || L.request_failed, saveResponse.status === 'success');
                    if (saveResponse.status === 'success') {
                        settingsOffcanvas.hide();
                        reload();
                    }
                });
            });

            if (!settingsOffcanvas) {
                settingsOffcanvas = new bootstrap.Offcanvas(document.getElementById('settings_offcanvas'));
            }

            settingsOffcanvas.show();
        });
    }

    // ── WebP conversion ─────────────────────────────────────────────────
    //
    // The name is the address, so the .webp extension means a new address:
    // the confirm says so, and there is deliberately no undo entry — the
    // original encoding cannot be brought back from the converted file.

    // ── Bulk page edit ──────────────────────────────────────────────────
    //
    // The shared page switches and the two style fields, applied to a whole
    // selection. Every control starts on "do not change" so an untouched
    // field is never written; the panel sends only what was actually set.

    function openBulkPagesPanel(pages) {
        if (pages.length === 0) { return; }

        api({ type: 'explorer_bulk_page_options' }, function (response) {
            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('bulk_pages_offcanvas_body');

            function tristate(id, label, note) {
                return '<div class="mb-3">' +
                    '<label class="form-label" for="' + id + '">' + esc(label) + '</label>' +
                    '<select id="' + id + '" class="form-select bulk-field" data-field="' + id.replace('bulk_', '') + '">' +
                    '<option value="">' + esc(L.do_not_change) + '</option>' +
                    '<option value="1">' + esc(L.yes) + '</option>' +
                    '<option value="0">' + esc(L.no) + '</option>' +
                    '</select>' +
                    (note ? '<div class="form-text">' + esc(note) + '</div>' : '') +
                    '</div>';
            }

            var html =
                '<p class="mb-3">' + esc(fmt(L.bulk_edit_pages_count, [pages.length])) + '</p>' +
                tristate('bulk_sitemap', L.sitemap_label, L.bulk_sitemap_note) +
                tristate('bulk_search', L.searchable_label, L.bulk_search_note) +
                tristate('bulk_comments', L.comments_label, L.bulk_comments_note);

            if (response.noindex_ready) {
                html +=
                    '<hr />' +
                    tristate('bulk_noindex', L.noindex_label, L.bulk_noindex_note) +
                    tristate('bulk_nofollow', L.nofollow_label, L.bulk_nofollow_note);
            }

            if (response.styles) {
                html +=
                    '<hr />' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="bulk_style">' + esc(L.desktop_style) + '</label>' +
                    '<select id="bulk_style" class="form-select bulk-field" data-field="style">' +
                    '<option value="">' + esc(L.do_not_change) + '</option>' + response.styles.desktop +
                    '</select></div>' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="bulk_mobile_style_id">' + esc(L.mobile_style) + '</label>' +
                    '<select id="bulk_mobile_style_id" class="form-select bulk-field" data-field="mobile_style_id">' +
                    '<option value="">' + esc(L.do_not_change) + '</option>' + response.styles.mobile +
                    '</select>' +
                    // Said before the save, not after: a page the visual
                    // editor opens carries its own layout tree, and the style
                    // it points at owns the assets that tree was built
                    // against, so the two are not exchangeable underneath it.
                    '<div class="form-text">' + esc(L.bulk_style_note) + '</div>' +
                    '</div>';
            }

            html += '<div class="mt-3 text-end"><button type="button" id="bulk_pages_save_button" class="btn btn-success" disabled><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

            body.innerHTML = html;

            var saveButton = document.getElementById('bulk_pages_save_button');

            // Nothing chosen yet means nothing to write; the button stays
            // out of reach until at least one field says something.
            function collect() {
                var set = {};

                body.querySelectorAll('.bulk-field').forEach(function (field) {
                    if (field.value !== '') { set[field.getAttribute('data-field')] = field.value; }
                });

                return set;
            }

            body.querySelectorAll('.bulk-field').forEach(function (field) {
                field.addEventListener('change', function () {
                    saveButton.disabled = (Object.keys(collect()).length === 0);
                });
            });

            saveButton.addEventListener('click', function () {
                var set = collect();

                if (Object.keys(set).length === 0) { return; }

                saveButton.disabled = true;

                api({
                    type: 'explorer_pages_bulk_edit',
                    items: pages.map(function (page) { return { id: page.id }; }),
                    set: set
                }, function (saveResponse) {
                    toast(saveResponse.message || L.request_failed, saveResponse.status !== 'error');

                    if (saveResponse.status !== 'error') {
                        bulkPagesOffcanvas.hide();
                        reload();
                    } else {
                        saveButton.disabled = false;
                    }
                });
            });

            if (!bulkPagesOffcanvas) {
                bulkPagesOffcanvas = new bootstrap.Offcanvas(document.getElementById('bulk_pages_offcanvas'));
            }

            bulkPagesOffcanvas.show();
        });
    }

    // ── The three-state field the bulk panels are built from ────────────
    //
    // Every control starts on "do not change" so an untouched field is never
    // written, and the panel sends only what was actually set. The three
    // panels share the shape rather than each writing their own selects.

    function bulkSelect(id, label, options, note) {

        var html = '<div class="mb-3">' +
            '<label class="form-label" for="' + id + '">' + esc(label) + '</label>' +
            '<select id="' + id + '" class="form-select bulk-field" data-field="' + esc(id.replace('bulk_', '')) + '">' +
            '<option value="">' + esc(L.do_not_change) + '</option>';

        options.forEach(function (option) {
            html += '<option value="' + esc(option[0]) + '">' + esc(option[1]) + '</option>';
        });

        return html + '</select>' + (note ? '<div class="form-text">' + esc(note) + '</div>' : '') + '</div>';
    }

    function bulkYesNo(id, label, note) {
        return bulkSelect(id, label, [['1', L.yes], ['0', L.no]], note);
    }

    // A value that only means something once its own select says so: the
    // amount beside "increase by", the text beside "replace with". Hidden
    // until then, because a box with nothing to attach itself to is a
    // question the operator cannot answer.
    function bulkCompanion(id, inner) {
        return '<div class="mb-3 d-none" id="' + id + '">' + inner + '</div>';
    }

    // Reads the panel: every field that says something, by name. The
    // companions above are read by their own ids rather than as fields,
    // because they qualify a choice instead of being one.
    function bulkCollect(body) {

        var set = {};

        body.querySelectorAll('.bulk-field').forEach(function (field) {

            if (field.type === 'checkbox') {
                if (field.checked) { set[field.getAttribute('data-field')] = '1'; }
                return;
            }

            if (field.value !== '') { set[field.getAttribute('data-field')] = field.value; }
        });

        return set;
    }

    // ── Bulk file edit ──────────────────────────────────────────────────
    //
    // What edit_files.php did to a checked list: move to a folder, the design
    // flag, and -- new here, because the file window has it and a selection
    // had no way to reach it -- the description. Optimizing runs afterwards
    // through the same endpoint the menu uses, so the progress bar and the
    // per-file report are the ones the operator already knows.

    function openBulkFilesPanel(files) {
        if (files.length === 0) { return; }

        api({ type: 'explorer_bulk_file_options' }, function (response) {
            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('bulk_files_offcanvas_body');

            // Only the pictures among them can be optimized, and only the ones
            // that have not been already -- the same rule the menu applies, so
            // the two entries never disagree about the count.
            var optimizable = files.filter(function (each) {
                return each.can_edit && !each.optimized && (OPTIMIZABLE_TYPES.indexOf(each.type) !== -1);
            });

            var resizable = files.filter(function (each) {
                return each.can_edit && itemResizable(each);
            });

            var html =
                '<p class="mb-3">' + esc(fmt(L.bulk_edit_files_count, [files.length])) + '</p>' +
                '<div class="mb-3">' +
                '<label class="form-label" for="bulk_folder_id">' + esc(L.bulk_move_to_folder) + '</label>' +
                '<select id="bulk_folder_id" class="form-select bulk-field" data-field="folder_id"></select>' +
                '</div>';

            if (response.is_designer) {
                html += bulkYesNo('bulk_design', L.design_file, L.bulk_design_note);
            }

            html +=
                bulkSelect('bulk_description_mode', L.bulk_description_mode, [
                    ['set', L.bulk_description_set],
                    ['clear', L.bulk_description_clear]
                ]) +
                bulkCompanion('bulk_description_wrap',
                    '<textarea id="bulk_description" class="form-control" rows="3"></textarea>');

            // Optimizing is an action rather than a field: it is not written
            // with the rest and it is not undone by setting it back.
            if ((optimizable.length > 0) || (resizable.length > 0)) {
                html += '<hr />' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="bulk_optimize_mode">' + esc(L.bulk_optimize_label) + '</label>' +
                    '<select id="bulk_optimize_mode" class="form-select">' +
                    '<option value="">' + esc(L.bulk_optimize_none) + '</option>' +
                    (optimizable.length > 0 ? '<option value="optimize">' + esc(L.bulk_optimize_only) + '</option>' : '') +
                    (resizable.length > 0 ? '<option value="resize">' + esc(fmt(L.resize_optimize, [state.caps.resize_target])) + '</option>' : '') +
                    '</select>' +
                    '<div class="form-text">' + esc(fmt(L.bulk_optimize_files, [optimizable.length])) + '</div>' +
                    '</div>';
            }

            html += '<div class="mt-3 text-end"><button type="button" id="bulk_files_save_button" class="btn btn-success" disabled><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

            body.innerHTML = html;

            var saveButton = document.getElementById('bulk_files_save_button');
            var folderSelect = document.getElementById('bulk_folder_id');
            var optimizeSelect = document.getElementById('bulk_optimize_mode');

            function refreshSaveState() {
                var set = bulkCollect(body);
                var wantsOptimize = (optimizeSelect && (optimizeSelect.value !== ''));

                // "Replace with the text below" on its own writes nothing
                // until there is text; clearing is a complete instruction.
                document.getElementById('bulk_description_wrap').classList.toggle('d-none', document.getElementById('bulk_description_mode').value !== 'set');

                saveButton.disabled = ((Object.keys(set).length === 0) && !wantsOptimize);
            }

            body.addEventListener('change', refreshSaveState);

            // The folder list arrives with no blank at the top -- it is built
            // for pickers that must choose one -- so the "leave them where
            // they are" option is put in front of it here.
            loadFolderOptionsInto(folderSelect, 0, function () {
                folderSelect.insertAdjacentHTML('afterbegin', '<option value="">' + esc(L.do_not_change) + '</option>');
                folderSelect.value = '';
            });

            saveButton.addEventListener('click', function () {

                var set = bulkCollect(body);
                var mode = optimizeSelect ? optimizeSelect.value : '';

                if (set.description_mode === 'set') { set.description = document.getElementById('bulk_description').value; }

                saveButton.disabled = true;

                // Optimizing after the write rather than before it: a file
                // that has just moved is optimized where it landed, and a run
                // that fails half way has not also lost the folder change.
                function runOptimize() {

                    if (bulkFilesOffcanvas) { bulkFilesOffcanvas.hide(); }

                    if (mode === '') { reload(); return; }

                    var targets = ((mode === 'resize') ? resizable : optimizable).map(function (each) { return { id: each.id }; });

                    if (targets.length === 0) { reload(); return; }

                    runBulkOptimize(targets, mode);
                }

                if (Object.keys(set).length === 0) { runOptimize(); return; }

                api({
                    type: 'explorer_files_bulk_edit',
                    items: files.map(function (file) { return { id: file.id }; }),
                    set: set
                }, function (saveResponse) {

                    if (saveResponse.status === 'error') {
                        toast(saveResponse.message || L.request_failed, false);
                        saveButton.disabled = false;
                        return;
                    }

                    toast(saveResponse.message, true);
                    runOptimize();
                });
            });

            if (!bulkFilesOffcanvas) {
                bulkFilesOffcanvas = new bootstrap.Offcanvas(document.getElementById('bulk_files_offcanvas'));
            }

            bulkFilesOffcanvas.show();
        });
    }

    // ── Bulk product edit ───────────────────────────────────────────────
    //
    // What edit_products.php did to a checked list -- status, price, stock,
    // tax, shipping zones and barcodes -- plus group membership, which that
    // screen had no reason to offer and this one does: standing in a group is
    // how a product is found here.
    //
    // Which fields appear depends on the installation: no shipping means no
    // zones, no barcode feature means no barcode line. The server answers
    // that, so the panel never offers a control that writes nowhere.

    function openBulkProductsPanel(products) {
        if (products.length === 0) { return; }

        api({ type: 'explorer_bulk_product_options' }, function (response) {
            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var body = document.getElementById('bulk_products_offcanvas_body');

            function groupOptions() {

                var html = '';

                (response.groups || []).forEach(function (group) {

                    var indent = '';

                    for (var level = 0; level < group.depth; level++) { indent += '&nbsp;&nbsp;'; }

                    html += '<option value="' + group.id + '">' + indent + esc(group.name) + '</option>';
                });

                return html;
            }

            function zoneChecks(name, label) {

                var html = '<div class="mb-3"><label class="form-label">' + esc(label) + '</label>';

                (response.zones || []).forEach(function (zone) {
                    html += '<div class="form-check">' +
                        '<input class="form-check-input bulk-zone" type="checkbox" value="' + zone.id + '" id="' + name + '_' + zone.id + '" data-zone-list="' + name + '" />' +
                        '<label class="form-check-label" for="' + name + '_' + zone.id + '">' + esc(zone.name) + '</label>' +
                        '</div>';
                });

                return html + '</div>';
            }

            var currency = response.currency_symbol || '';

            var html =
                '<p class="mb-3">' + esc(fmt(L.bulk_edit_products_count, [products.length])) + '</p>' +
                bulkSelect('bulk_enabled', L.bulk_status_label, [['1', L.bulk_publish], ['0', L.bulk_unpublish]]) +
                '<hr />' +
                bulkSelect('bulk_price_method', L.bulk_price_label, [
                    ['increase', L.bulk_price_increase + ' (' + currency + ')'],
                    ['decrease', L.bulk_price_decrease + ' (' + currency + ')'],
                    ['increase_percent', L.bulk_price_increase_percent + ' (%)'],
                    ['decrease_percent', L.bulk_price_decrease_percent + ' (%)']
                ], L.bulk_price_note) +
                bulkCompanion('bulk_price_wrap',
                    '<label class="form-label" for="bulk_price_value">' + esc(L.amount) + '</label>' +
                    '<input type="text" inputmode="decimal" id="bulk_price_value" class="form-control" />') +
                '<hr />' +
                bulkSelect('bulk_inventory', L.bulk_inventory_label, [['1', L.yes], ['0', L.no]]) +
                bulkSelect('bulk_quantity_mode', L.bulk_quantity_label, [
                    ['value', L.bulk_quantity_value],
                    ['increase', L.bulk_quantity_increase],
                    ['decrease', L.bulk_quantity_decrease]
                ]) +
                bulkCompanion('bulk_quantity_wrap',
                    '<label class="form-label" for="bulk_quantity_value">' + esc(L.quantity) + '</label>' +
                    '<input type="number" step="1" min="0" id="bulk_quantity_value" class="form-control" />') +
                '<hr />' +
                bulkSelect('bulk_tax_method', L.bulk_tax_label, [
                    ['set', L.bulk_tax_set],
                    ['zone', L.bulk_tax_zone]
                ]) +
                bulkCompanion('bulk_tax_wrap',
                    '<label class="form-label" for="bulk_tax_value">' + esc(L.bulk_tax_label) + ' (%)</label>' +
                    '<input type="text" inputmode="decimal" id="bulk_tax_value" class="form-control" />');

            if (response.shipping && response.zones && response.zones.length > 0) {
                html += '<hr />' + zoneChecks('allow_zones', L.bulk_zones_allow) + zoneChecks('disallow_zones', L.bulk_zones_disallow);
            }

            if (response.groups && response.groups.length > 0) {
                html += '<hr />' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="bulk_add_group">' + esc(L.bulk_group_add) + '</label>' +
                    '<select id="bulk_add_group" class="form-select bulk-field" data-field="add_group">' +
                    '<option value="">' + esc(L.do_not_change) + '</option>' + groupOptions() +
                    '</select></div>' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="bulk_remove_group">' + esc(L.bulk_group_remove) + '</label>' +
                    '<select id="bulk_remove_group" class="form-select bulk-field" data-field="remove_group">' +
                    '<option value="">' + esc(L.do_not_change) + '</option>' + groupOptions() +
                    '</select>' +
                    '<div class="form-text">' + esc(L.bulk_group_note) + '</div>' +
                    '</div>';
            }

            if (response.barcode_ready) {
                html += '<hr /><div class="form-check form-switch mb-3">' +
                    '<input class="form-check-input bulk-field" type="checkbox" id="bulk_assign_barcodes" data-field="assign_barcodes" />' +
                    '<label class="form-check-label" for="bulk_assign_barcodes">' + esc(L.bulk_barcodes) + '</label>' +
                    '</div>';
            }

            html += '<div class="mt-3 text-end"><button type="button" id="bulk_products_save_button" class="btn btn-success" disabled><span class="bi bi-save me-2"></span>' + esc(L.save) + '</button></div>';

            body.innerHTML = html;

            var saveButton = document.getElementById('bulk_products_save_button');

            function zoneValues(name) {

                var values = [];

                body.querySelectorAll('[data-zone-list="' + name + '"]').forEach(function (box) {
                    if (box.checked) { values.push(box.value); }
                });

                return values;
            }

            function collect() {

                var set = bulkCollect(body);

                if (set.price_method) { set.price_value = document.getElementById('bulk_price_value').value; }
                if (set.quantity_mode) { set.quantity_value = document.getElementById('bulk_quantity_value').value; }
                if (set.tax_method === 'set') { set.tax_value = document.getElementById('bulk_tax_value').value; }

                var allow = zoneValues('allow_zones');
                var disallow = zoneValues('disallow_zones');

                if (allow.length > 0) { set.allow_zones = allow; }
                if (disallow.length > 0) { set.disallow_zones = disallow; }

                return set;
            }

            body.addEventListener('change', function () {

                document.getElementById('bulk_price_wrap').classList.toggle('d-none', document.getElementById('bulk_price_method').value === '');
                document.getElementById('bulk_quantity_wrap').classList.toggle('d-none', document.getElementById('bulk_quantity_mode').value === '');
                document.getElementById('bulk_tax_wrap').classList.toggle('d-none', document.getElementById('bulk_tax_method').value !== 'set');

                saveButton.disabled = (Object.keys(collect()).length === 0);
            });

            saveButton.addEventListener('click', function () {

                var set = collect();

                if (Object.keys(set).length === 0) { return; }

                saveButton.disabled = true;

                api({
                    type: 'explorer_products_bulk_edit',
                    items: products.map(function (product) { return { id: product.id }; }),
                    set: set
                }, function (saveResponse) {

                    toast(saveResponse.message || L.request_failed, saveResponse.status !== 'error');

                    if (saveResponse.status === 'error') { saveButton.disabled = false; return; }

                    if (bulkProductsOffcanvas) { bulkProductsOffcanvas.hide(); }
                    reload();
                });
            });

            if (!bulkProductsOffcanvas) {
                bulkProductsOffcanvas = new bootstrap.Offcanvas(document.getElementById('bulk_products_offcanvas'));
            }

            bulkProductsOffcanvas.show();
        });
    }

    // Optimize or resize a set of files through the same endpoint.
    // The images are handled a few at a time instead of all in one request.  The screen can then
    // show how far along the work is, and a long list does not sit in a single request that a busy
    // server might cut off half way.
    function runInBatches(payload, items, countField, label, doneText) {
        var queue = items.slice();
        var total = items.length;
        var handled = 0;
        var errors = [];
        var failures = 0;
        var report = '';

        setProgress(label, 0, total);

        function next() {
            if (queue.length === 0) {
                clearProgress();
                // A run over one file gets that file's own account of itself
                // when the server gave one -- the sizes before and after --
                // rather than a count of one.
                var done = ((total === 1) && report) ? report : fmt(doneText, [handled]);
                toast(errors.length > 0 ? errors.join(' ') : done, errors.length === 0);
                reload();
                return;
            }

            var batch = queue.splice(0, 3);

            api(Object.assign({}, payload, { items: batch }), function (response) {
                handled += (response[countField] || 0);

                if ((response.status === 'success') && response.message) { report = response.message; }

                if (response.errors && response.errors.length > 0) {
                    errors = errors.concat(response.errors);
                } else if ((response.status === 'error') && (response.message)) {
                    errors.push(response.message);
                    failures++;
                } else {
                    failures = 0;
                }

                setProgress(label, total - queue.length, total);
                next();
            }, function (xhr) {
                errors.push(L.request_failed + (xhr && xhr.status ? ' (' + xhr.status + ')' : ''));
                failures++;

                // Three refusals in a row means the server is not going to manage the rest either.
                if (failures >= 3) { queue = []; }

                setProgress(label, total - queue.length, total);
                next();
            });
        }

        next();
    }

    function runBulkOptimize(items, mode) {
        if (items.length === 0) { return; }

        runInBatches({ type: 'explorer_optimize', mode: mode }, items, 'optimized', L.optimizing, L.optimized_done);
    }

    function runWebpConvert(items) {
        if (items.length === 0) { return; }

        if (!window.confirm(fmt(L.webp_confirm, [items.length]))) { return; }

        runInBatches({ type: 'explorer_webp' }, items, 'converted', L.converting, L.converted_done);
    }

    // The picture editor, in a window on this screen.
    //
    // It used to leave for image_editor_edit.php and come back through
    // send_to: a page out and a page back for a crop, with the selection, the
    // scroll position and the folder you were standing in all rebuilt from
    // the address bar afterwards. This is the same editor the visual designer
    // opens -- one tool, one save endpoint -- and none of that is lost.
    //
    // Addressed by id: image_editor_save.php looks a name up across the whole
    // files table, and two folders can hold the same name.
    function openImageEditorFor(item, onSaved) {

        loadAssets('image_editor', function () {

            if (typeof window.openImageEditor !== 'function') {
                toast(L.image_editor_missing, false);
                return;
            }

            window.openImageEditor({
                src: imageUrl(item),
                fileId: item.id,
                fileName: item.name,
                token: software_token,
                saveUrl: 'image_editor_save.php',
                onSaved: function (response) {

                    response = response || {};

                    // Saved over itself: the address is unchanged, the pixels
                    // are not, and the browser is holding the old ones.
                    if (response.mode !== 'copy') { imageVersions[item.id] = Date.now(); }

                    toast(fmt(L.saved_named, [response.name || item.name]), true);

                    if (onSaved) { onSaved(response); } else { reload(); }
                }
            });
        });
    }

    // -- Heavy tools, fetched the first time somebody reaches for them ----
    //
    // The picture editor and the code editor between them weigh close to a
    // megabyte of script, and most visits to this screen open neither. The
    // tags are the ones the designer puts in its head, from the same two PHP
    // functions, so the list of files lives in one place; here they are read
    // out of that markup and appended one at a time, each script waiting for
    // the one before it -- the order the head would have loaded them in.
    var assetBundles = {
        image_editor: <?php echo encode_json(get_image_editor_includes()); ?>,
        code_editor: <?php echo encode_json(get_codemirror_includes()); ?>
    };

    // false: not asked for yet; an array: being fetched, these are waiting;
    // true: on the page.
    var assetsLoaded = {};

    function loadAssets(bundle, done) {

        if (assetsLoaded[bundle] === true) { done(); return; }

        if (Array.isArray(assetsLoaded[bundle])) { assetsLoaded[bundle].push(done); return; }

        assetsLoaded[bundle] = [done];

        var holder = document.createElement('div');
        holder.innerHTML = assetBundles[bundle] || '';

        var nodes = Array.prototype.slice.call(holder.querySelectorAll('link, script'));

        (function step() {

            var node = nodes.shift();

            if (!node) {
                var waiting = assetsLoaded[bundle];
                assetsLoaded[bundle] = true;
                waiting.forEach(function (callback) { callback(); });
                return;
            }

            if (node.tagName === 'LINK') {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = node.getAttribute('href');
                document.head.appendChild(link);
                step();
                return;
            }

            var script = document.createElement('script');

            if (node.getAttribute('src')) {
                script.src = node.getAttribute('src');
                script.onload = step;
                // A file that fails to arrive must not hold the rest hostage:
                // the tool's own availability check says what is missing.
                script.onerror = step;
                document.head.appendChild(script);
            } else {
                script.textContent = node.textContent;
                document.head.appendChild(script);
                step();
            }
        })();
    }

    // ── The file window ─────────────────────────────────────────────────
    //
    // What edit_file.php was, without leaving the screen. The server hands
    // over the file's details and, for a text format, its text; the window
    // shows the media or opens the code editor, lets the four fields be
    // changed, and sends everything back in one request. The tools along the
    // bottom are the actions the file's kind allows.

    var PREVIEW_VIDEO_TYPES = ['mp4', 'webm', 'mov', 'ogg', 'ogv', 'm4v'];
    var PREVIEW_AUDIO_TYPES = ['mp3', 'wav', 'wma', 'aac', 'oga', 'm4a', 'flac', 'opus'];

    // What the server's image engine can turn: the same list explorer_rotate
    // checks, so the button is never offered for a file the request refuses.
    var ROTATABLE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'];

    var fileEditModal = null;
    var fileEdit = null;

    // Bootstrap drops a show() that arrives while the window is still fading
    // out, so a file opened within a blink of closing the last one would be
    // rendered and never shown -- and then wiped by the fade's own clean-up.
    // The fade is tracked, and a show that lands inside it waits for its end.
    var fileEditHiding = false;
    var fileEditPending = false;

    function fileEditKind(file) {
        if (file.is_image) { return 'image'; }
        if (file.type === 'pdf') { return 'pdf'; }
        if (PREVIEW_VIDEO_TYPES.indexOf(file.type) !== -1) { return 'video'; }
        if (PREVIEW_AUDIO_TYPES.indexOf(file.type) !== -1) { return 'audio'; }
        if (file.viewable) { return 'text'; }
        return 'other';
    }

    function openFileEditor(item) {

        if ((!item) || (item.kind !== 'file') || item.backup || item.short_link) { return; }

        // Nothing to change on a file you may only look at; the file itself
        // is still one click away.
        if (!item.can_edit) { window.open(item.url, '_blank'); return; }

        api({ type: 'explorer_file_get', id: item.id }, function (response) {

            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            var file = response.file;
            var kind = fileEditKind(file);

            // The code editor is fetched only for a text file, and the window
            // waits for it: an empty box that fills in a moment later reads
            // as the file having nothing in it.
            if ((kind === 'text') && (file.content !== null)) {
                loadAssets('code_editor', function () { showFileEditor(item, file, kind); });
            } else {
                showFileEditor(item, file, kind);
            }
        });
    }

    function filePreviewHtml(file, kind) {

        if (!file.on_disk) {
            return '<div class="text-center empty-note"><span class="bi bi-file-earmark-x file-edit-glyph d-block"></span>' + esc(L.file_not_on_disk) + '</div>';
        }

        switch (kind) {
            case 'image':
                return '<img src="' + esc(imageUrl(file)) + '" alt="" draggable="false" />';
            case 'video':
                return '<video controls preload="metadata" src="' + esc(file.url) + '"></video>';
            case 'audio':
                return '<audio controls preload="metadata" src="' + esc(file.url) + '"></audio>';
            case 'pdf':
                return '<iframe loading="lazy" src="' + esc(file.url) + '"></iframe>';
            case 'text':
                // The editor is the preview.
                return '';
        }

        return '<span class="bi ' + (FILETYPE_ICONS[file.type] || 'bi-file-earmark') + ' file-edit-glyph"></span>';
    }

    function fileEditToolsHtml(file, kind) {

        var html = '';

        function tool(action, icon, label, extra) {
            return '<button type="button" class="btn btn-sm btn-outline-secondary" data-file-tool="' + action + '"' + (extra || '') + '>' +
                '<span class="bi ' + icon + ' me-1"></span>' + esc(label) + '</button>';
        }

        if ((kind === 'image') && file.on_disk && (file.type !== 'svg')) {
            html += tool('image_editor', 'bi-brush', L.image_editor);
        }

        // A quarter turn to the right, with no editor to open: a photo that
        // came off a phone lying on its side is the whole reason. The glyph
        // alone, as asked -- the word is in the tooltip and for screen
        // readers.
        if ((kind === 'image') && file.on_disk && (ROTATABLE_TYPES.indexOf(file.type) !== -1)) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary" data-file-tool="rotate" title="' + esc(L.rotate_right) + '" aria-label="' + esc(L.rotate_right) + '">' +
                '<span class="bi bi-arrow-clockwise"></span></button>';
        }

        // A drawing becomes a picture here: the file is drawn onto a canvas
        // at its own size and saved beside itself through the image editor's
        // endpoint, which already knows how to write a copy.
        if ((file.type === 'svg') && file.on_disk) {
            ['png', 'jpg', 'webp'].forEach(function (type) {
                html += tool('rasterize', 'bi-file-earmark-image', fmt(L.save_copy_as, [type.toUpperCase()]), ' data-type="' + type + '"');
            });
        }

        html += tool('open', 'bi-box-arrow-up-right', L.open_in_new_tab);
        html += tool('download', 'bi-download', L.download);

        return html;
    }

    // The line under the fields: kind, size, pixels, when and by whom. Drawn
    // again after anything that changes the file behind the window.
    function renderFileEditFacts(file) {

        var facts = [];

        facts.push('<span>' + esc(itemTypeLabel(file)) + '</span>');
        facts.push('<span>' + esc(file.size_label || '') + '</span>');

        if (file.is_image && (file.image_width > 0)) {
            facts.push('<span>' + file.image_width + ' × ' + file.image_height + ' px</span>');
        }

        if (file.modified) {
            facts.push('<span>' + file.modified + (file.username ? ' ' + fmt(L.modified_by, [esc(file.username)]) : '') + '</span>');
        }

        document.getElementById('file_edit_facts').innerHTML = facts.join('');
    }

    // A quarter turn to the right, done on the server and shown back here.
    //
    // The window stays open with the fields as they were: a turn is not an
    // edit of the name or the description, so nothing typed so far is lost
    // and nothing is saved on the operator's behalf. Only what the turn
    // changed -- the pixels, the size, the edges -- is refreshed.
    function rotateFileRight(button) {

        if (!fileEdit || !fileEdit.file || fileEdit.rotating) { return; }

        var file = fileEdit.file;

        fileEdit.rotating = true;

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }

        function settle() {

            if (fileEdit) { fileEdit.rotating = false; }

            if (button) {
                button.disabled = false;
                button.innerHTML = '<span class="bi bi-arrow-clockwise"></span>';
            }
        }

        api({ type: 'explorer_rotate', id: file.id }, function (response) {

            settle();

            if (response.status !== 'success') { toast(response.message || L.request_failed, false); return; }

            // Still the same window on the same file, unless it was closed
            // or swapped while the server was turning the picture.
            if (!fileEdit || !fileEdit.file || (fileEdit.file.id !== file.id)) { reload(); return; }

            // The fresh row over the old one, keeping what the listing does
            // not carry (whether it is on disk, the editor's fields).
            if (response.file) {
                Object.keys(response.file).forEach(function (key) { file[key] = response.file[key]; });
            }

            // A new stamp, or every <img> with this address keeps showing
            // the picture the way it was.
            imageVersions[file.id] = Date.now();

            var preview = document.getElementById('file_edit_preview');
            if (preview) { preview.innerHTML = filePreviewHtml(file, 'image'); }

            renderFileEditFacts(file);

            if (response.message) { toast(response.message, true); }
            reload();
        }, function () {
            settle();
            toast(L.request_failed, false);
        });
    }

    function disposeFileEditor() {

        if (fileEdit && fileEdit.editor) {
            // The refresh addon has to be stopped by name; toTextArea() leaves it
            // polling a wrapper that is no longer in the document.
            try { fileEdit.editor.setOption('autoRefresh', false); } catch (e) { }
            try { fileEdit.editor.toTextArea(); } catch (e) { }
        }

        var preview = document.getElementById('file_edit_preview');

        // Media left in a hidden window keeps playing, or keeps a decoder
        // open; the next open builds its own.
        preview.innerHTML = '';

        fileEdit = null;
    }

    function showFileEditor(item, file, kind) {

        disposeFileEditor();

        fileEdit = { item: item, file: file, kind: kind, editor: null, baseline: null };

        document.getElementById('file_edit_preview').innerHTML = filePreviewHtml(file, kind);

        var editorWrap = document.getElementById('file_edit_editor_wrap');
        var note = document.getElementById('file_edit_editor_note');
        var textarea = document.getElementById('file_edit_code');

        editorWrap.classList.toggle('d-none', kind !== 'text');
        note.textContent = '';
        textarea.value = '';

        if (kind === 'text') {

            if (!file.on_disk) {
                note.textContent = L.file_not_on_disk;
            } else if (file.content_too_large) {
                note.textContent = fmt(L.file_too_large_to_edit, [file.editor_max_label]);
            } else if (typeof CodeMirror === 'undefined') {
                // The editor did not arrive; the text is still there to read
                // and change in the plain box.
                textarea.classList.remove('d-none');
                textarea.rows = 18;
                textarea.className = 'form-control font-monospace';
                textarea.value = file.content || '';
                textarea.readOnly = !file.editable;
            } else {
                textarea.value = file.content || '';
                fileEdit.editor = CodeMirror.fromTextArea(textarea, {
                    mode: file.editor_mode || 'text/plain',
                    lineNumbers: true,
                    indentUnit: 4,
                    matchTags: { bothTags: true },
                    autoCloseTags: true,
                    autoCloseBrackets: true,
                    autoRefresh: true,
                    styleActiveLine: true,
                    lineWrapping: false,
                    lint: file.editable,
                    gutters: ['CodeMirror-lint-markers'],
                    readOnly: file.editable ? false : 'nocursor',
                    theme: (document.documentElement.getAttribute('data-bs-theme') === 'dark') ? 'pastel-on-dark' : 'default',
                    extraKeys: {
                        'F11': function (cm) { cm.setOption('fullScreen', !cm.getOption('fullScreen')); },
                        'Esc': function (cm) { if (cm.getOption('fullScreen')) { cm.setOption('fullScreen', false); } },
                        'Ctrl-Space': 'autocomplete',
                        'Ctrl-S': function () { saveFileEditor(); },
                        'Cmd-S': function () { saveFileEditor(); }
                    }
                });

                if (!file.editable) { note.textContent = L.file_read_only; }
            }
        }

        document.getElementById('file_edit_name').value = file.name;
        document.getElementById('file_edit_description').value = file.description || '';
        document.getElementById('file_edit_design').checked = !!file.design;
        document.getElementById('file_edit_error').classList.add('d-none');
        document.getElementById('file_edit_tools').innerHTML = fileEditToolsHtml(file, kind);

        renderFileEditFacts(file);

        loadFolderOptionsInto(document.getElementById('file_edit_folder'), file.folder_id, function () {
            // The baseline is taken once the folder list is in, so the select
            // settling on its value does not read as a change.
            if (fileEdit) { fileEdit.baseline = fileEditSnapshot(); }
        });

        showFileEditUnsaved(false);

        var modalElement = document.getElementById('file_edit_modal');

        if (!fileEditModal) {

            // Only the buttons close it: a click beside the window while
            // halfway through an edit must not throw the edit away.
            fileEditModal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });

            modalElement.addEventListener('shown.bs.modal', function () {
                if (fileEdit && fileEdit.editor) { fileEdit.editor.refresh(); fileEdit.editor.focus(); }
            });

            modalElement.addEventListener('hide.bs.modal', function () { fileEditHiding = true; });

            modalElement.addEventListener('hidden.bs.modal', function () {

                fileEditHiding = false;

                // A file was opened while this one was fading out: it is
                // rendered and waiting, so this is its cue rather than the
                // moment to clear the window.
                if (fileEditPending) {
                    fileEditPending = false;
                    fileEditModal.show();
                    return;
                }

                disposeFileEditor();
            });
        }

        if (modalElement.classList.contains('show')) { return; }

        if (fileEditHiding) { fileEditPending = true; return; }

        fileEditModal.show();
    }

    // What the window would send, as one string: comparing two of these is
    // how "has anything changed" is answered.
    function fileEditSnapshot() {

        if (!fileEdit) { return ''; }

        return JSON.stringify({
            name: document.getElementById('file_edit_name').value,
            folder: document.getElementById('file_edit_folder').value,
            description: document.getElementById('file_edit_description').value,
            design: document.getElementById('file_edit_design').checked,
            content: fileEdit.editor ? fileEdit.editor.getValue() : document.getElementById('file_edit_code').value
        });
    }

    function fileEditDirty() {
        return !!(fileEdit && (fileEdit.baseline !== null) && (fileEditSnapshot() !== fileEdit.baseline));
    }

    function showFileEditUnsaved(on) {
        document.getElementById('file_edit_unsaved').classList.toggle('d-none', !on);
        document.getElementById('file_edit_discard').classList.toggle('d-none', !on);
    }

    // Cancel and the cross ask this. With nothing changed the window simply
    // closes; with something changed it stays, says so, and offers the one
    // button that closes it anyway.
    function requestCloseFileEditor() {

        if (fileEditDirty()) { showFileEditUnsaved(true); return; }

        hideModal(fileEditModal, 'file_edit_modal');
    }

    function saveFileEditor() {

        if (!fileEdit) { return; }

        var file = fileEdit.file;
        var error = document.getElementById('file_edit_error');
        var button = document.getElementById('file_edit_save');

        var payload = {
            type: 'explorer_file_save',
            id: file.id,
            name: document.getElementById('file_edit_name').value,
            folder_id: parseInt(document.getElementById('file_edit_folder').value, 10) || file.folder_id,
            description: document.getElementById('file_edit_description').value,
            design: document.getElementById('file_edit_design').checked ? '1' : '0'
        };

        // Text goes back only when the editor was allowed to change it; a
        // window on a picture, or on a file too large to show, sends none.
        if (file.editable && file.on_disk && !file.content_too_large) {
            payload.content = fileEdit.editor ? fileEdit.editor.getValue() : document.getElementById('file_edit_code').value;
        }

        error.classList.add('d-none');
        button.disabled = true;

        api(payload, function (response) {

            button.disabled = false;

            if (response.status !== 'success') {
                error.textContent = response.message || L.request_failed;
                error.classList.remove('d-none');
                return;
            }

            // A drawing edited as text is a different picture now, under the
            // same address.
            if ((file.type === 'svg') && (payload.content !== undefined)) { imageVersions[file.id] = Date.now(); }

            // Closed without the unsaved question: what was typed is saved.
            if (fileEdit) { fileEdit.baseline = fileEditSnapshot(); }

            toast(response.message || fmt(L.saved_named, [payload.name]), true);
            hideModal(fileEditModal, 'file_edit_modal');
            reload();
        }, function () {
            button.disabled = false;
            error.textContent = L.request_failed;
            error.classList.remove('d-none');
        });
    }

    // An SVG drawn onto a canvas and saved beside itself as a picture.
    //
    // The endpoint is the image editor's: it takes a data URL and a type and
    // writes a copy next to the source, which is exactly this. A drawing
    // with no size of its own gets the size the row remembers, or a sensible
    // square; a JPG gets white behind it, since it has no transparency to
    // keep.
    function rasterizeSvg(file, type) {

        var picture = new Image();

        picture.onload = function () {

            var width = picture.naturalWidth || file.image_width || 1024;
            var height = picture.naturalHeight || file.image_height || 1024;
            var longest = Math.max(width, height);

            if (longest > 4096) { width = Math.round(width * 4096 / longest); height = Math.round(height * 4096 / longest); }

            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            var context = canvas.getContext('2d');

            if (type === 'jpg') { context.fillStyle = '#ffffff'; context.fillRect(0, 0, width, height); }

            context.drawImage(picture, 0, 0, width, height);

            var mime = { jpg: 'image/jpeg', png: 'image/png', webp: 'image/webp' }[type];
            var data;

            try { data = canvas.toDataURL(mime, 0.92); } catch (e) { toast(L.request_failed, false); return; }

            fetch('image_editor_save.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ token: software_token, file_id: file.id, mode: 'copy', type: type, data: data })
            }).then(function (response) { return response.json(); }).then(function (result) {

                if (!result || (result.status !== 'success')) { toast((result && result.message) || L.request_failed, false); return; }

                toast(fmt(L.saved_named, [result.name]), true);
                reload();

            }).catch(function () { toast(L.request_failed, false); });
        };

        picture.onerror = function () { toast(L.request_failed, false); };
        picture.src = imageUrl(file);
    }

    document.getElementById('file_edit_save').addEventListener('click', saveFileEditor);
    document.getElementById('file_edit_cancel').addEventListener('click', requestCloseFileEditor);
    document.getElementById('file_edit_close').addEventListener('click', requestCloseFileEditor);
    document.getElementById('file_edit_discard').addEventListener('click', function () { hideModal(fileEditModal, 'file_edit_modal'); });

    // The unsaved notice steps back the moment the change is undone.
    ['input', 'change'].forEach(function (name) {
        document.getElementById('file_edit_modal').addEventListener(name, function () {
            if (fileEdit && !fileEditDirty()) { showFileEditUnsaved(false); }
        });
    });

    document.getElementById('file_edit_tools').addEventListener('click', function (event) {

        var button = event.target.closest('[data-file-tool]');

        if ((!button) || (!fileEdit)) { return; }

        var file = fileEdit.file;

        switch (button.getAttribute('data-file-tool')) {

            case 'image_editor':
                openImageEditorFor(file, function () {
                    // Back in this window with the new pixels, and the
                    // listing behind it brought up to date.
                    var preview = document.getElementById('file_edit_preview');
                    if (preview) { preview.innerHTML = filePreviewHtml(file, 'image'); }
                    reload();
                });
                break;

            case 'rasterize':
                rasterizeSvg(file, button.getAttribute('data-type'));
                break;

            case 'rotate':
                rotateFileRight(button);
                break;

            case 'open':
                window.open(file.url, '_blank');
                break;

            case 'download':
                var link = document.createElement('a');
                link.href = file.url;
                link.download = file.name;
                document.body.appendChild(link);
                link.click();
                link.remove();
                break;
        }
    });

    // ── Event wiring ────────────────────────────────────────────────────

    var content = document.getElementById('explorer_content');

    // -- Rubber-band selection ------------------------------------------
    //
    // A drag that starts on empty content draws a band and selects whatever
    // it covers, the way a desktop file manager does. Three things keep it
    // cheap in a view holding a few thousand tiles:
    //
    //   - every item rectangle is measured once, when the drag begins, into
    //     one flat numeric array. A frame is then pure arithmetic: no
    //     getBoundingClientRect, no layout the browser has to recompute.
    //   - those rectangles are stored in the content pane's own scrolled
    //     coordinates, so scrolling mid-drag (including the auto-scroll at
    //     the edges) shifts one number instead of forcing a re-measure.
    //   - a frame reads the scroll offset once and writes afterwards. Reading
    //     anything after the band has moved would make the browser lay the
    //     pane out again, every frame, which is the whole cost of the drag.
    //
    // Classes are written only for the items whose state actually changed,
    // and the preview pane is left alone until the button comes up: loading
    // a preview per frame is what would stall the drag.

    var marqueeState = null;
    var marqueeEndedAt = 0;
    var marqueeSwallowClick = false;
    var MARQUEE_THRESHOLD = 4;   // px of travel before a click becomes a drag
    var MARQUEE_EDGE = 42;       // px of pane edge that auto-scrolls
    var MARQUEE_SPEED = 24;      // px per frame at the very edge

    // Client coordinates to the pane's scrolled coordinates, clamped so the
    // band can never reach outside the content and grow the scroll area.
    function marqueePoint(clientX, clientY, scrollLeft, scrollTop) {
        var box = marqueeState.box;
        return {
            x: Math.min(Math.max(clientX - box.left + scrollLeft, 0), box.scrollWidth),
            y: Math.min(Math.max(clientY - box.top + scrollTop, 0), box.scrollHeight)
        };
    }

    // Measured before the drag writes anything to the page: a class added
    // first would invalidate style for every tile and make this pay for the
    // recalculation as well.
    function marqueeMeasure() {
        var box = marqueeState.box;
        var elements = content.querySelectorAll('.explorer-item');
        var count = elements.length;
        var bounds = new Float64Array(count * 4);
        var indexes = new Int32Array(count);
        var keys = new Array(count);

        // Everything the frames need to know about the pane, read here so
        // they never have to ask for it again.
        box.scrollWidth = content.scrollWidth;
        box.scrollHeight = content.scrollHeight;
        box.maxScrollTop = Math.max(0, content.scrollHeight - content.clientHeight);

        var offsetX = content.scrollLeft - box.left;
        var offsetY = content.scrollTop - box.top;

        for (var i = 0; i < count; i++) {
            var element = elements[i];
            var rect = element.getBoundingClientRect();

            bounds[i * 4] = rect.left + offsetX;
            bounds[i * 4 + 1] = rect.top + offsetY;
            bounds[i * 4 + 2] = rect.right + offsetX;
            bounds[i * 4 + 3] = rect.bottom + offsetY;

            keys[i] = element.getAttribute('data-kind') + ':' + element.getAttribute('data-id');
            indexes[i] = parseInt(element.getAttribute('data-index'), 10) || 0;
        }

        marqueeState.elements = elements;
        marqueeState.bounds = bounds;
        marqueeState.keys = keys;
        marqueeState.indexes = indexes;
        marqueeState.hit = new Uint8Array(count);
    }

    function marqueeFrame() {
        if (!marqueeState) { return; }

        marqueeState.frame = window.requestAnimationFrame(marqueeFrame);

        // A listing that reloads mid-drag replaces the pane's contents, taking
        // the band and every tile this frame is holding on to with it.
        if (!marqueeState.band.isConnected) { marqueeStop(false); return; }

        var box = marqueeState.box;

        // The one read of the frame. Everything below only writes.
        var scrollLeft = content.scrollLeft;
        var scrollTop = content.scrollTop;

        // Holding the pointer in the edge strip scrolls the pane, so a band
        // can reach past what is on screen.
        var pastTop = marqueeState.clientY - (box.top + MARQUEE_EDGE);
        var pastBottom = marqueeState.clientY - (box.top + box.height - MARQUEE_EDGE);

        if (pastTop < 0) {
            scrollTop = Math.max(0, scrollTop - Math.min(MARQUEE_SPEED, Math.ceil(-pastTop / 2)));
            content.scrollTop = scrollTop;
        } else if (pastBottom > 0) {
            scrollTop = Math.min(box.maxScrollTop, scrollTop + Math.min(MARQUEE_SPEED, Math.ceil(pastBottom / 2)));
            content.scrollTop = scrollTop;
        }

        var current = marqueePoint(marqueeState.clientX, marqueeState.clientY, scrollLeft, scrollTop);
        var left = Math.min(marqueeState.originX, current.x);
        var top = Math.min(marqueeState.originY, current.y);
        var right = Math.max(marqueeState.originX, current.x);
        var bottom = Math.max(marqueeState.originY, current.y);

        var band = marqueeState.band;

        if ((left !== marqueeState.lastLeft) || (top !== marqueeState.lastTop) ||
            (right !== marqueeState.lastRight) || (bottom !== marqueeState.lastBottom)) {

            band.style.left = left + 'px';
            band.style.top = top + 'px';
            band.style.width = (right - left) + 'px';
            band.style.height = (bottom - top) + 'px';

            marqueeState.lastLeft = left;
            marqueeState.lastTop = top;
            marqueeState.lastRight = right;
            marqueeState.lastBottom = bottom;

        } else {
            // A pointer that has not moved covers the same tiles it did last
            // frame, so there is nothing left to do.
            return;
        }

        var bounds = marqueeState.bounds;
        var keys = marqueeState.keys;
        var hit = marqueeState.hit;
        var elements = marqueeState.elements;
        var base = marqueeState.base;
        var changed = false;
        var anchor = -1;

        for (var i = 0; i < keys.length; i++) {
            var offset = i * 4;
            var inside = ((bounds[offset] < right) && (bounds[offset + 2] > left) &&
                (bounds[offset + 1] < bottom) && (bounds[offset + 3] > top)) ? 1 : 0;

            if (inside && (anchor < 0)) { anchor = marqueeState.indexes[i]; }
            if (inside === hit[i]) { continue; }

            hit[i] = inside;
            changed = true;

            if (inside) {
                state.selection[keys[i]] = true;
                elements[i].classList.add('selected');
            } else if (!base[keys[i]]) {
                // What the band had picked up before it shrank back off the
                // item -- unless the operator started from a selection they
                // are adding to, which stays put.
                delete state.selection[keys[i]];
                elements[i].classList.remove('selected');
            }
        }

        if (changed) {
            if (anchor >= 0) { state.lastIndex = anchor; }
            renderStatusbar();
        }
    }

    function marqueeStop(cancel) {
        if (!marqueeState) { return; }

        window.cancelAnimationFrame(marqueeState.frame);

        if (marqueeState.band && marqueeState.band.parentNode) {
            marqueeState.band.parentNode.removeChild(marqueeState.band);
        }

        if (cancel) {
            state.selection = {};
            Object.keys(marqueeState.base).forEach(function (key) { state.selection[key] = true; });
            applySelectionClasses();
        }

        marqueeState = null;

        // The click the browser sends after the button comes up would land on
        // empty content and wipe what the band just selected. See the content
        // click handler below.
        marqueeEndedAt = Date.now();
        marqueeSwallowClick = true;

        renderStatusbar();

        var selected = selectedItems();
        renderPreview(selected.length === 1 ? selected[0] : null);
    }

    // The button was let go outside the window, or the window lost focus with
    // it still down: either way no mouseup is coming and the band has to be
    // taken down on the evidence available.
    function marqueeAbandon() {
        if (!marqueeState) { return; }
        if (marqueeState.armed) { marqueeStop(false); return; }
        marqueeState = null;
    }

    content.addEventListener('mousedown', function (event) {

        // Left button only, and only on empty space: a press on a tile is the
        // start of a drag, and the column grips stop the event themselves.
        if (event.button !== 0) { return; }
        if (event.target.closest('.explorer-item')) { return; }
        if (event.target.closest('input, textarea, select, button, a, label')) { return; }

        // Nothing to band-select in an empty folder or on the shared screen.
        if (!content.querySelector('.explorer-item')) { return; }

        if (marqueeState) { marqueeStop(true); }

        var rect = content.getBoundingClientRect();

        marqueeState = {
            box: {
                left: rect.left + content.clientLeft,
                top: rect.top + content.clientTop,
                height: content.clientHeight,
                scrollWidth: 0,
                scrollHeight: 0,
                maxScrollTop: 0
            },
            startX: event.clientX,
            startY: event.clientY,
            clientX: event.clientX,
            clientY: event.clientY,
            // Windows adds to the selection with either modifier held.
            additive: (event.ctrlKey || event.metaKey || event.shiftKey),
            armed: false,
            frame: 0,
            band: null,
            base: {},
            originX: 0,
            originY: 0,
            lastLeft: -1, lastTop: -1, lastRight: -1, lastBottom: -1
        };
    });

    document.addEventListener('mousemove', function (event) {
        if (!marqueeState) { return; }

        if (event.buttons === 0) { marqueeAbandon(); return; }

        marqueeState.clientX = event.clientX;
        marqueeState.clientY = event.clientY;

        if (marqueeState.armed) { return; }

        var dx = event.clientX - marqueeState.startX;
        var dy = event.clientY - marqueeState.startY;

        // Under the threshold the press is still a click on empty space, which
        // clears the selection -- so nothing is drawn and nothing is selected.
        if ((dx * dx + dy * dy) < (MARQUEE_THRESHOLD * MARQUEE_THRESHOLD)) { return; }

        marqueeState.armed = true;

        // Read the page before writing to it.
        marqueeMeasure();

        var origin = marqueePoint(marqueeState.startX, marqueeState.startY, content.scrollLeft, content.scrollTop);
        marqueeState.originX = origin.x;
        marqueeState.originY = origin.y;

        if (marqueeState.additive) {
            Object.keys(state.selection).forEach(function (key) { marqueeState.base[key] = true; });
        } else {
            state.selection = {};
            // Only the tiles that are actually lit, rather than every tile in
            // the pane.
            content.querySelectorAll('.explorer-item.selected').forEach(function (element) {
                element.classList.remove('selected');
            });
        }

        // A band started next to a label would otherwise drag a text selection
        // along with it. selectstart is cancelled for as long as the button is
        // down; this drops whatever the first few pixels already took.
        try { window.getSelection().removeAllRanges(); } catch (e) { }

        var band = document.createElement('div');
        band.id = 'explorer_marquee';
        content.appendChild(band);
        marqueeState.band = band;

        marqueeState.frame = window.requestAnimationFrame(marqueeFrame);
    });

    document.addEventListener('mouseup', function () {
        if (!marqueeState) { return; }

        // A press that never crossed the threshold leaves the click alone.
        if (!marqueeState.armed) { marqueeState = null; return; }

        marqueeStop(false);
    });

    document.addEventListener('selectstart', function (event) {
        if (marqueeState) { event.preventDefault(); }
    });

    document.addEventListener('keydown', function (event) {
        if (marqueeState && (event.key === 'Escape')) { marqueeStop(true); }
    });

    window.addEventListener('blur', marqueeAbandon);

    content.addEventListener('click', function (event) {

        // The click that closes a band drag, and only that one: the next
        // click is the operator's own and must still clear the selection.
        // The time limit covers the release that lands outside the pane,
        // which sends no click here at all.
        if (marqueeSwallowClick) {
            marqueeSwallowClick = false;
            if ((Date.now() - marqueeEndedAt) < 600) { return; }
        }

        var element = event.target.closest('.explorer-item');

        if (!element) {
            // Mobile browsers can replay the tap that picked a menu item at
            // the same spot once the menu is gone; that ghost click lands on
            // empty content and must not wipe what the action just selected.
            if ((Date.now() - menuActionAt) < 600) { return; }
            setSelection([]);
            return;
        }

        var item = findItem(element.getAttribute('data-kind'), element.getAttribute('data-id'));
        if (!item) { return; }

        // The hover check bubble toggles without needing the keyboard.
        if (event.target.closest('[data-role="sel"]')) {
            toggleItemSelection(item);
            return;
        }

        handleItemClick(item, event);
    });

    content.addEventListener('dblclick', function (event) {
        var element = event.target.closest('.explorer-item');
        if (!element) { return; }
        var item = findItem(element.getAttribute('data-kind'), element.getAttribute('data-id'));
        if (!item) { return; }

        // A cell that can be edited takes the double click; the row keeps
        // it everywhere else, which is where it has always opened things.
        var cell = event.target.closest('[data-edit]');

        if (cell) {
            event.stopPropagation();
            beginQuickEdit(item, cell.getAttribute('data-edit'), cell);
            return;
        }

        activateItem(item);
    });

    content.addEventListener('contextmenu', function (event) {
        event.preventDefault();

        if ((Date.now() - lastLongPressAt) < 700) { return; }

        // Right-clicking the headings is asking about the headings. The rest
        // of this menu is about rows, and there are none up there.
        if (event.target.closest('#explorer_list_table thead')) {

            var headerMenu = columnMenu();

            if (headerMenu !== '') {
                menuElement.innerHTML = headerMenu;
                menuElement.setAttribute('data-target-source', 'content');
                menuElement.setAttribute('data-target-kind', '');
                menuElement.setAttribute('data-target-id', '');
                positionMenu(event);
            }

            return;
        }

        var element = event.target.closest('.explorer-item');
        var item = element ? findItem(element.getAttribute('data-kind'), element.getAttribute('data-id')) : null;

        if (item && !state.selection[itemKey(item)]) { setSelection([item], item); }

        showMenu(event, item);
    });

    menuElement.addEventListener('click', function (event) {
        var submenuParent = event.target.closest('[data-submenu]');

        if (submenuParent) {
            var li = submenuParent.parentElement;

            menuElement.querySelectorAll('li.has-submenu.open').forEach(function (other) {
                if (other !== li) { other.classList.remove('open'); }
            });

            li.classList.toggle('open');

            // Flip left when the flyout would leave the viewport.
            var flyout = li.querySelector('.submenu');
            if (flyout && li.classList.contains('open')) {
                li.classList.remove('submenu-left');
                var rect = flyout.getBoundingClientRect();
                if (rect.right > window.innerWidth - 8) { li.classList.add('submenu-left'); }
            }
            return;
        }

        var button = event.target.closest('[data-menu-action]');
        if (button && !button.classList.contains('disabled')) { runMenuAction(button.getAttribute('data-menu-action')); }
    });

    menuElement.addEventListener('mouseover', function (event) {
        var li = event.target.closest('li.has-submenu');

        menuElement.querySelectorAll('li.has-submenu.open').forEach(function (other) {
            if (other !== li) { other.classList.remove('open'); }
        });

        if (li && !li.classList.contains('open')) {
            li.classList.add('open');
            var flyout = li.querySelector('.submenu');
            if (flyout) {
                li.classList.remove('submenu-left');
                var rect = flyout.getBoundingClientRect();
                if (rect.right > window.innerWidth - 8) { li.classList.add('submenu-left'); }
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('#explorer_menu')) { hideMenu(); }
    });

    document.addEventListener('scroll', hideMenu, true);

    document.getElementById('explorer_breadcrumb').addEventListener('click', function (event) {
        var link = event.target.closest('a[data-folder-id]');
        if (link) { exitAllFiles(parseInt(link.getAttribute('data-folder-id'), 10)); }
    });

    document.getElementById('up_button').addEventListener('click', function () {

        // The store climbs its own trail. All Products and the bin are views
        // across the whole catalog rather than a place inside it, so from
        // either of them "up" is the way back out to the top group.
        if (insideCatalog()) {

            if (state.allFilter !== '') { state.allFilter = ''; load(0, highlightTree); return; }

            var crumbs = state.catalogCrumbs || [];

            if (crumbs.length > 1) { load(crumbs[crumbs.length - 2].id, highlightTree); }
            else if (crumbs.length === 1) { load(0, highlightTree); }

            return;
        }

        if (state.mode === 'all') { exitAllFiles(0); return; }
        if (state.breadcrumb.length > 1) {
            load(state.breadcrumb[state.breadcrumb.length - 2].id, highlightTree);
        } else if (state.breadcrumb.length === 1 && state.caps.role === 3) {
            load(0, highlightTree);
        }
    });

    document.getElementById('refresh_button').addEventListener('click', function () { reload(); });
    document.getElementById('empty_bin_button').addEventListener('click', function () {
        if (insideCatalog()) { catalogPurge([], true); return; }
        emptyRecycleBin();
    });

    document.getElementById('recycle_bin_button').addEventListener('click', function () {

        if (insideCatalog()) {
            if (insideCatalogBin()) { state.allFilter = ''; load(state.groupId || 0, highlightTree); } else { enterCatalogBin(); }
            return;
        }

        if (state.recycle.folder_id > 0) {
            state.mode = 'browse';
            if (insideBin()) { load(0, highlightTree); } else { load(state.recycle.folder_id, highlightTree); }
        }
    });
    document.getElementById('new_folder_button').addEventListener('click', createFolder);
    document.getElementById('create_file_button').addEventListener('click', createFile);
    document.getElementById('new_image_button').addEventListener('click', createBlankImage);
    document.getElementById('view_grid_button').addEventListener('click', function () { state.viewType = 'grid'; state.viewTypeExplicit = true; reload(); });
    document.getElementById('view_list_button').addEventListener('click', function () { state.viewType = 'list'; state.viewTypeExplicit = true; reload(); });

    document.getElementById('filter_input').addEventListener('input', function () {
        state.filter = this.value.trim();
        renderContent();
        applySelectionClasses();
        renderStatusbar();
    });

    // The selection strip is redrawn on every selection change, so its buttons
    // are reached through the bar rather than bound to each one.
    document.getElementById('explorer_statusbar').addEventListener('click', function (event) {

        if (event.target.closest('[data-role="clear-selection"]')) {
            setSelection([]);
            return;
        }

        var actionButton = event.target.closest('[data-selection-action]');

        if ((!actionButton) || actionButton.disabled) { return; }

        runSelectionAction(actionButton.getAttribute('data-selection-action'));
    });

    // The filter menu and the chip strip are both rebuilt whenever the set
    // changes, so both are reached through their container.
    ['explorer_filter_menu', 'explorer_filters_bar'].forEach(function (id) {

        document.getElementById(id).addEventListener('click', function (event) {

            if (event.target.closest('[data-filter-clear]')) { clearFilters(); return; }

            var button = event.target.closest('[data-filter]');

            if (button) { toggleFilter(button.getAttribute('data-filter')); }
        });
    });

    // Toolbar menus are placed by the stylesheet rather than by Popper
    // (data-bs-display="static" on every one of them, so an open menu is not
    // torn out of a bar that scrolls), and nothing in that arrangement pulls a
    // menu back when it would open past the edge of the screen.
    //
    // Which of its neighbours are drawn decides where a button sits -- the
    // narrowing button moves a hundred and twenty pixels to the right the
    // moment the file-type button beside it is hidden, which is every view
    // except Files -- so neither edge is the right one to anchor to. The menu
    // is measured once it is up and pulled inside.
    function clampToolbarMenu(menu) {

        var wrap = menu.closest('.dropdown');

        if (!wrap) { return; }

        // Cleared first so the measurement is of where the stylesheet puts it,
        // not of where this function put it the last time it opened.
        menu.style.left = '';
        menu.style.right = '';

        var margin = 8;
        var natural = menu.getBoundingClientRect().left;
        var room = window.innerWidth - menu.offsetWidth - margin;
        var clamped = Math.max(margin, Math.min(natural, room));

        if (Math.abs(clamped - natural) < 1) { return; }

        menu.style.right = 'auto';
        menu.style.left = (clamped - wrap.getBoundingClientRect().left) + 'px';
    }

    // On shown rather than on show: until Bootstrap has added its class the
    // menu is display:none, and a hidden element has no width to measure.
    document.getElementById('explorer_toolbar').addEventListener('shown.bs.dropdown', function (event) {

        var wrap = event.target.closest('.dropdown');
        var menu = wrap ? wrap.querySelector('.dropdown-menu') : null;

        if (menu) { clampToolbarMenu(menu); }
    });

    document.getElementById('quicklook_close').addEventListener('click', closeQuickLook);
    document.getElementById('quicklook_prev').addEventListener('click', function () { stepQuickLook(-1); });
    document.getElementById('quicklook_next').addEventListener('click', function () { stepQuickLook(1); });

    // Clicking the empty space around the picture closes it, the way a
    // lightbox does. The picture itself and the buttons do not: a click that
    // lands on what somebody is looking at should not take it away.
    document.getElementById('quicklook_stage').addEventListener('click', function (event) {
        if (event.target === this) { closeQuickLook(); }
    });

    document.getElementById('quicklook_tools').addEventListener('click', function (event) {

        var button = event.target.closest('[data-ql]');

        if ((!button) || (!quickLookItem)) { return; }

        if (button.getAttribute('data-ql') === 'copy') { copyAddresses([quickLookItem], 'full'); return; }

        if (button.getAttribute('data-ql') === 'edit') {
            var editing = quickLookItem;
            closeQuickLook();
            openFileEditor(editing);
        }
    });

    document.getElementById('transfer_filter').addEventListener('input', renderTransferTargets);

    document.getElementById('transfer_targets').addEventListener('click', function (event) {

        var row = event.target.closest('[data-target-id]');

        if ((!row) || (!transferState) || row.disabled) { return; }

        transferState.picked = parseInt(row.getAttribute('data-target-id'), 10);
        renderTransferTargets();
    });

    document.getElementById('transfer_confirm_button').addEventListener('click', function () {
        runTransferDialog(!!(transferState && transferState.confirmCopy));
    });

    document.getElementById('transfer_secondary_button').addEventListener('click', function () { runTransferDialog(true); });

    // Side panels: remembered per browser, restored explicitly in both
    // directions so markup defaults and the stored value can never disagree.
    function applyPaneState(id, storageKey, defaultOpen) {
        var stored = storageGet(storageKey);
        var open = (stored === null) ? defaultOpen : (stored === '1');
        document.getElementById(id).classList.toggle('pane-hidden', !open);
    }

    function togglePane(id, storageKey) {
        var pane = document.getElementById(id);
        pane.classList.toggle('pane-hidden');
        storageSet(storageKey, pane.classList.contains('pane-hidden') ? '0' : '1');
        syncPaneButtons();
    }

    function syncPaneButtons() {
        var treeOpen = !document.getElementById('explorer_tree_pane').classList.contains('pane-hidden');
        var previewOpen = !document.getElementById('explorer_preview_pane').classList.contains('pane-hidden');
        document.querySelector('#tree_toggle_button [data-role="check"]').classList.toggle('opacity-0', !treeOpen);
        document.querySelector('#preview_toggle_button [data-role="check"]').classList.toggle('opacity-0', !previewOpen);
    }

    document.getElementById('tree_toggle_button').addEventListener('click', function () { togglePane('explorer_tree_pane', 'pg_explorer_tree_open'); });
    document.getElementById('preview_toggle_button').addEventListener('click', function () { togglePane('explorer_preview_pane', 'pg_explorer_preview_open'); });

    // Keyboard shortcuts follow desktop file manager conventions.
    document.addEventListener('keydown', function (event) {
        // A key event raised on the document itself rather than on an element
        // -- which is what a programmatic dispatch produces -- has no closest()
        // to ask, and reading it would end the handler before any shortcut ran.
        if (event.target && event.target.closest && event.target.closest('input, textarea, select, [contenteditable]')) { return; }

        // Any window that is open owns the keyboard. Naming the two that
        // existed meant every window added afterwards quietly let Delete and
        // the arrow keys through to the grid behind it.
        if (document.querySelector('.modal.show, .offcanvas.show')) { return; }

        var key = event.key;
        var selected = selectedItems();

        // While the overlay is up it owns the keyboard: the grid is still
        // behind it, and Delete reaching a listing nobody can see would be a
        // delete nobody meant.
        if (quickLookIsOpen()) {

            if ((key === 'Escape') || (key === ' ')) { event.preventDefault(); closeQuickLook(); return; }
            if (key === 'ArrowRight') { event.preventDefault(); stepQuickLook(1); return; }
            if (key === 'ArrowLeft') { event.preventDefault(); stepQuickLook(-1); return; }

            event.preventDefault();
            return;
        }

        if ((key === ' ') && (!event.ctrlKey) && (!event.metaKey) && (!event.shiftKey) && (selected.length === 1)) {
            event.preventDefault();
            openQuickLook(selected[0]);
            return;
        }

        if (key === 'F2' && selected.length === 1) { event.preventDefault(); beginRename(selected[0]); return; }
        if (key === 'Delete' && selected.length > 0) { event.preventDefault(); deleteSelection(); return; }
        if (key === 'Escape') { hideMenu(); setSelection([]); state.cursor = -1; return; }
        if (key === 'Backspace') { event.preventDefault(); document.getElementById('up_button').click(); return; }
        if (key === 'Enter' && selected.length === 1) { event.preventDefault(); activateItem(selected[0]); return; }

        // Walking the listing. The grid steps a whole row at a time up and
        // down; the table has one item per row, so there the sideways keys
        // have nothing to move along and are left alone.
        if (state.ordered.length > 0) {

            var columns = (state.viewType === 'grid') ? gridColumns() : 1;

            if (key === 'ArrowDown') { event.preventDefault(); moveCursorBy(columns, event.shiftKey); return; }
            if (key === 'ArrowUp') { event.preventDefault(); moveCursorBy(-columns, event.shiftKey); return; }

            if ((key === 'ArrowRight') && (columns > 1)) { event.preventDefault(); moveCursorBy(1, event.shiftKey); return; }
            if ((key === 'ArrowLeft') && (columns > 1)) { event.preventDefault(); moveCursorBy(-1, event.shiftKey); return; }

            if (key === 'Home') { event.preventDefault(); moveCursorTo(0, event.shiftKey); return; }
            if (key === 'End') { event.preventDefault(); moveCursorTo(state.ordered.length - 1, event.shiftKey); return; }

            // Adding one row to what is already picked without losing it.
            // Space on its own is the quick look, which is why this one
            // carries a modifier.
            if ((key === ' ') && (event.ctrlKey || event.metaKey) && (state.cursor >= 0)) {
                event.preventDefault();
                toggleItemSelection(state.ordered[state.cursor]);
                revealCursor();
                return;
            }
        }

        if ((event.ctrlKey || event.metaKey) && (key === 'z' || key === 'Z') && event.shiftKey) { event.preventDefault(); redoLast(); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'z' || key === 'Z')) { event.preventDefault(); undoLast(); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'y' || key === 'Y')) { event.preventDefault(); redoLast(); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'a' || key === 'A')) { event.preventDefault(); setSelection(state.ordered.slice()); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'c' || key === 'C') && selected.length > 0) { event.preventDefault(); clipboardSet('copy'); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'x' || key === 'X') && selected.length > 0) { event.preventDefault(); clipboardSet('cut'); return; }
        if ((event.ctrlKey || event.metaKey) && (key === 'v' || key === 'V')) { event.preventDefault(); if (canPasteHere()) { clipboardPaste(insideCatalog() ? state.groupId : state.folderId); } return; }

        // Tile size, the way every viewer does it. The browser's own zoom is
        // on the same keys, so these have to say they were handled.
        if ((event.ctrlKey || event.metaKey) && (state.viewType === 'grid')) {

            if ((key === '+') || (key === '=')) { event.preventDefault(); stepTileSize(1); return; }
            if ((key === '-') || (key === '_')) { event.preventDefault(); stepTileSize(-1); return; }
            if (key === '0') { event.preventDefault(); setTileSize('medium'); return; }
        }

        // Everything above has had its say; a plain printable character left
        // over is somebody spelling out the name of the row they want. Tested
        // last so that no shortcut can ever be swallowed by it.
        if ((key.length === 1) && (!event.ctrlKey) && (!event.metaKey) && (!event.altKey) && (key !== ' ')) {
            event.preventDefault();
            typeAheadJump(key);
        }
    });

    // Touch devices: a long press opens the same context menus. iOS never
    // fires contextmenu; Android fires it on its own — the timestamp guard
    // keeps the two paths from opening the menu twice.
    var lastLongPressAt = 0;

    function attachLongPress(container, resolve) {
        var timer = null;
        var startX = 0;
        var startY = 0;
        var startTarget = null;

        container.addEventListener('touchstart', function (event) {
            if (event.touches.length !== 1) { return; }

            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
            startTarget = event.target;

            timer = window.setTimeout(function () {
                timer = null;
                lastLongPressAt = Date.now();
                resolve({
                    clientX: startX,
                    clientY: startY,
                    target: startTarget,
                    preventDefault: function () { }
                });
            }, 550);
        }, { passive: true });

        container.addEventListener('touchmove', function (event) {
            if (timer === null) { return; }
            var dx = event.touches[0].clientX - startX;
            var dy = event.touches[0].clientY - startY;
            if ((dx * dx + dy * dy) > 100) { window.clearTimeout(timer); timer = null; }
        }, { passive: true });

        ['touchend', 'touchcancel'].forEach(function (name) {
            container.addEventListener(name, function (event) {
                if (timer !== null) { window.clearTimeout(timer); timer = null; }

                // The tap that ends a long press must not click through to
                // the page (it would close the menu it just opened).
                if ((Date.now() - lastLongPressAt) < 700) { event.preventDefault(); }
            });
        });
    }

    attachLongPress(content, function (fakeEvent) {
        var element = fakeEvent.target.closest && fakeEvent.target.closest('.explorer-item');
        var item = element ? findItem(element.getAttribute('data-kind'), element.getAttribute('data-id')) : null;

        if (item && !state.selection[itemKey(item)]) { setSelection([item], item); }

        showMenu(fakeEvent, item);
    });

    attachLongPress(document.getElementById('explorer_tree_pane'), function (fakeEvent) {
        var row = fakeEvent.target.closest && fakeEvent.target.closest('.tree-row');
        if (!row || !row.hasAttribute('data-folder-id')) { return; }

        showTreeMenu(fakeEvent, {
            id: parseInt(row.getAttribute('data-folder-id'), 10),
            name: row.getAttribute('data-name') || '',
            parentId: parseInt(row.getAttribute('data-parent-id'), 10) || 0,
            canEdit: row.getAttribute('data-can-edit') === '1',
            isRoot: row.getAttribute('data-is-root') === '1',
            empty: row.getAttribute('data-empty') === '1',
            row: row
        });
    });

    // ── Guided tour ─────────────────────────────────────────────────────
    //
    // pg_tour.js draws it and knows nothing about this screen; what follows is
    // the half that has to: which control each step belongs to, and the one
    // step that has to make something happen before it has anything to point
    // at.  A step whose control is not on the screen -- the tree on a phone,
    // the bin button on a site that has not had the recycle bin upgrade -- is
    // passed over by the engine rather than left ringing empty space.

    function tourAnchor(at) {

        switch (at) {

            case 'tree': return { target: 'explorer_tree_pane', placement: 'right', padding: 4, radius: 14 };
            case 'new': return { target: 'new_menu_button', placement: 'bottom' };
            case 'search': return { target: 'filter_input', placement: 'bottom' };
            case 'all_row': return { target: 'all_products_quick', placement: 'right', padding: 4, radius: 8 };
            case 'selection': return { target: 'explorer_statusbar', placement: 'top', padding: 4, radius: 10 };
            case 'bin': return { target: 'recycle_bin_button', placement: 'bottom' };
            case 'more': return { target: 'explorer_more_button', placement: 'bottom' };

            // The two menu steps get their targets from the opener instead of
            // from an id, because what they ring is a pair: the thing that was
            // right-clicked and the menu that came out of it.  A ring around
            // the menu alone leaves the reader holding the effect with the
            // cause dimmed out beside it, which is the one thing the step is
            // there to join up.
            case 'menu': return { placement: 'right', padding: 8 };
            case 'tree_menu': return { placement: 'right', padding: 8 };
        }

        return {};
    }

    // The two menu steps open the real menu themselves, because describing a
    // menu is a poor substitute for seeing the one you will actually get.  An
    // opened menu is only shown, never used: the tour's own layer sits over
    // everything and swallows the click.
    //
    // A menu that could not be opened leaves the step with no target, and the
    // engine moves past it rather than ringing an empty corner.

    // Where the item is a whole thing rather than a leaf -- a folder, a product
    // group -- the menu is at its longest and says the most, so that is the one
    // the step looks for first.
    // Put the thing where it will still be once the menu has been measured
    // against it.  Opening first and scrolling afterwards pulls the two apart:
    // the menu is placed at fixed coordinates and does not follow.  The scroll
    // is instant on purpose -- a smooth one is still moving when the menu is
    // placed.
    function tourBringIntoView(element) {

        try {
            element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
        } catch (error) {
            element.scrollIntoView();
        }
    }

    function tourGridSample() {

        var wanted = insideCatalog() ? 'group' : 'folder';

        return content.querySelector('.explorer-item[data-kind="' + wanted + '"]')
            || content.querySelector('.explorer-item');
    }

    // What the menu steps are currently ringing: the item, and its menu.
    var tourMenuTargets = [];

    // Whether the screen has anything on it yet.  The engine reads this before
    // opening this screen's tour by itself.
    var tourContentReady = false;

    function tourCloseMenu() {
        tourMenuTargets = [];
        hideMenu();
    }

    // The selection strip only exists while something is picked out, so the
    // step that rings it picks the same sample row the menu step uses, and
    // lets it go again afterwards rather than leaving the reader holding a
    // selection they did not make.
    function tourPickSample(done) {

        var element = tourGridSample();
        var item = element ? findItem(element.getAttribute('data-kind'), element.getAttribute('data-id')) : null;

        if (!item) { done(); return; }

        setSelection([item], item);
        tourBringIntoView(element);

        // One frame, so the strip is measured after it has been drawn.
        window.requestAnimationFrame(function () { done(); });
    }

    function tourDropSample() {
        setSelection([]);
    }

    function tourOpenSampleMenu(done) {

        tourMenuTargets = [];

        var element = tourGridSample();
        var item = element ? findItem(element.getAttribute('data-kind'), element.getAttribute('data-id')) : null;

        if (!item) { done(); return; }

        setSelection([item], item);
        tourBringIntoView(element);

        window.requestAnimationFrame(function () {

            var rect = element.getBoundingClientRect();

            showMenu({
                clientX: Math.round(rect.left + Math.min(48, rect.width / 2)),
                clientY: Math.round(rect.top + Math.min(28, rect.height / 2))
            }, item);

            tourMenuTargets = [element, menuElement];

            // One more frame, so the menu is measured where it has just been
            // put rather than where it was before.
            window.requestAnimationFrame(function () { done(); });
        });
    }

    // The tree carries its own menu, and not a short version of the grid's:
    // access rules and folder settings are only here, and in the store so is
    // adding a product group inside this one.  It is opened on the first row
    // that is not the root, because almost everything is switched off on the
    // root and a menu of greyed-out words teaches nobody anything.
    function tourOpenTreeMenu(done) {

        tourMenuTargets = [];

        var pane = document.getElementById('explorer_tree_pane');
        var rows = pane ? pane.querySelectorAll('.tree-row[data-folder-id]') : [];
        var chosen = null;

        for (var i = 0; i < rows.length; i++) {

            if (rows[i].getAttribute('data-is-root') === '1') { continue; }
            if (!rows[i].getClientRects().length) { continue; }

            chosen = rows[i];
            break;
        }

        // Nothing but the root -- a brand new site.  Its menu is mostly shut,
        // but it is still this menu, and showing it beats skipping the step.
        if ((!chosen) && (rows.length) && (rows[0].getClientRects().length)) { chosen = rows[0]; }

        var node = treeNodeFromRow(chosen);

        if (!node) { done(); return; }

        tourBringIntoView(chosen);

        window.requestAnimationFrame(function () {

            var rect = chosen.getBoundingClientRect();

            showTreeMenu({
                clientX: Math.round(rect.left + Math.min(90, rect.width * 0.6)),
                clientY: Math.round(rect.bottom - 4)
            }, node);

            tourMenuTargets = [chosen, menuElement];

            window.requestAnimationFrame(function () { done(); });
        });
    }

    function tourSetup() {

        if ((!window.PgTour) || (!window.PG_TOUR)) { return; }

        var copy = <?php echo encode_json($explorer_tour); ?>;

        var steps = copy.map(function (entry) {

            var step = tourAnchor(entry.at);

            // A step that names an id is looked up when the step runs rather
            // than now: a control may be switched on between the tour being
            // set up and the tour being watched.  The two menu steps name no
            // id at all and take their targets from their opener instead.
            if (step.target) {

                var id = step.target;
                step.target = function () { return document.getElementById(id); };
            }

            step.title = entry.title;
            step.text = entry.text;

            if (entry.at === 'menu') {
                step.before = tourOpenSampleMenu;
                step.after = tourCloseMenu;
                step.target = function () { return tourMenuTargets; };
            }

            if (entry.at === 'selection') {

                step.before = tourPickSample;
                step.after = tourDropSample;

                // An empty folder has nothing to pick, so the bar has nothing
                // to say and the step would ring a row of counts.
                step.skipIf = function () { return state.ordered.length === 0; };
            }

            if (entry.at === 'tree_menu') {
                step.before = tourOpenTreeMenu;
                step.after = tourCloseMenu;
                step.target = function () { return tourMenuTargets; };

                // No tree on a phone, so no tree menu to show either.  Asked
                // before anything is opened, which saves opening a menu only to
                // close it again.
                step.skipIf = function () {

                    var pane = document.getElementById('explorer_tree_pane');

                    return ((!pane) || (!pane.getClientRects().length));
                };
            }

            return step;
        });

        PgTour.register({
            key: PG_TOUR.key,
            seen: PG_TOUR.seen,
            saveToken: PG_TOUR.token,
            labels: PG_TOUR.labels,
            steps: steps,

            // The grid and the tree arrive over the network.  The header asks
            // the engine to start as soon as the page is parsed, which is
            // sooner than that, so the engine is told to hold this one back
            // until there is something on the screen to ring.
            ready: function () { return tourContentReady; },
        });

        var item = document.getElementById('tour_menu_item');

        // By key: more than one tour is registered on this page.  The frame of
        // the software has its own, played from the menu at the top right, and
        // a bare start() would run that one from here instead of this screen's.
        if (item) { item.addEventListener('click', function () { PgTour.start(PG_TOUR.key); }); }
    }

    // ── Boot ────────────────────────────────────────────────────────────

    applyPaneState('explorer_tree_pane', 'pg_explorer_tree_open', true);
    applyPaneState('explorer_preview_pane', 'pg_explorer_preview_open', true);
    syncPaneButtons();

    // Tile size, as this browser last left it. Read through the same list the
    // menu uses, so a value from an older build that no longer names a size
    // falls back rather than writing nonsense into the custom properties.
    (function () {

        var stored = storageGet('pg_explorer_tile');

        if (stored && TILE_SIZES.some(function (size) { return size.key === stored; })) { state.tileSize = stored; }

        applyTileSize();
    })();

    // Ctrl and the wheel, the way a viewer resizes anything. Not passive: the
    // point is to take the gesture off the browser's own page zoom, and a
    // passive listener is not allowed to.
    document.getElementById('explorer_content').addEventListener('wheel', function (event) {

        if ((!event.ctrlKey) && (!event.metaKey)) { return; }
        if (state.viewType !== 'grid') { return; }

        event.preventDefault();
        stepTileSize((event.deltaY < 0) ? 1 : -1);

    }, { passive: false });

    // Live chat draws its bubble over the bottom right corner, and that is
    // where the status bar now keeps its buttons.
    //
    // The question waits for the document: this script is inline, so it runs
    // while the page is still being parsed and the launcher is printed further
    // down with the footer. Asking now always answers "no chat here".
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('pg-chat-root')) {
            document.getElementById('explorer_app').classList.add('has-chat-launcher');
        }
    });

    tourSetup();

    attachDragHandlers();
    watchListBreakpoints();

    // The tour opens itself only once the grid and the tree have arrived. A
    // tour that starts against a loading screen rings empty boxes and its
    // right-click step has nothing to right-click.
    load(insideCatalog() ? state.groupId : state.folderId, function () {

        loadTreeChildren(treeRootList, 0, function () {

            highlightTree();

            tourContentReady = true;

            if (window.PgTour) { PgTour.autoStart(); }
        });

        // Asked for by the left menu, and done once: the address is rewritten
        // by the listing, so a refresh does not ask again.
        var initialAction = '<?php echo $initial_action; ?>';
        var initialShortLink = <?php echo (int) $initial_short_link; ?>;

        if ((initialAction === 'upload') && newActions().upload) { openUploadModal(); }
        if ((initialAction === 'create_file') && newActions().file) { createFile(); }

        // Sent here by a page's panel on the front end.
        if ((initialAction === 'create_short_link') && newActions().short_link) {
            openShortLinkWizard(null, initialShortLink);
        }

        // The listing has already run by this point, so the link is in hand
        // and the window can open on it rather than asking the server again.
        if (initialAction === 'edit_short_link') {

            var wanted = (state.items.files || []).filter(function (row) {
                return row.short_link && (parseInt(row.id, 10) === initialShortLink);
            })[0];

            if (wanted) { openShortLinkWizard(wanted); }
        }
    });
})();
</script>
</main>
<?php
echo output_footer();

// The messages above were rendered, so their session forms are cleared the
// same way the classic screens clear their own.
$liveform->remove_form('view_folder_and_files');
$liveform_pages->remove_form('view_pages');
$liveform_folders->remove_form('view_folders');
?>
