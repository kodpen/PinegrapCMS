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

include ('init.php');

$user = validate_user();
$file_name = '';
$send_to = '';
$object_type = '';
$object_id = '';

// init varibles to be used in the output
if(isset($_GET['file_name'])){
    $file_name = $_GET['file_name'];
}
if(isset($_GET['send_to'])){
    $send_to = ($_GET['send_to'] ?? '');
}
if(isset($_GET['object_type'])){
    $object_type = $_GET['object_type'];
}
if(isset($_GET['object_id'])){
    $object_id = $_GET['object_id'];
}


// if the form has not been submitted
if (!$_POST)
{

    // get file info
    $query = "SELECT
        name,
        folder,
        id,
        design,
        type
    FROM files
    WHERE name = '" . escape($file_name) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $file_name_extension = $row['type'];
    $original_file_name = $row['name'];
    $file_id = $row['id'];
    $folder_id = $row['folder'];
    $design = $row['design'];
    $type = $row['type'];

    // if the file does not exist, then output error
    if (mysqli_num_rows($result) == 0)
    {
        output_error(lang('The image cannot be updated because it no longer exists') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }


    $output_png_selected   = '';
    $output_webp_selected  = '';
    $output_jpg_selected   = '';
    $output_gif_selected   = '';
    switch ($file_name_extension)
    {
        case 'png':
        case 'PNG':
            $output_png_selected = 'selected="selected"';
        break;
        case 'webp':
            $output_webp_selected = 'selected="selected"';
        break;
        case 'gif':
            $output_gif_selected = 'selected="selected"';
        break;
        default:
            $output_jpg_selected = 'selected="selected"';
        break;
    }
    // if user does not have access to edit this file, or if it is a design file, then output error
    if (($user['role'] == 3) && ((check_edit_access($folder_id) == false) || ($file_design == 1)))
    {
        log_activity(lang('access denied to edit image with Image Editor because user does not have access to edit image'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $column_to_update = '';

    // if there is a column specified to update, then prepare it for the link
    if (isset($_GET['column_to_update']) && $_GET['column_to_update'] != '')
    {
        $column_to_update = '&column_to_update=' . h(urlencode($_GET['column_to_update']));
    }

    $image_location = PATH . $file_name;


    echo 
    output_header_secure(array('title'=>lang('Image Editor') . ' | Pintura | ' . h($file_name),'icon'=>'file')) . ' 
    <link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/image_editor/packages/doka/doka.css">
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/image_editor/packages/jquery_doka/doka.js"></script>
    <style>
        img {
            max-width: 100%;
        }
        .inline-editor{height:calc(100vh - 57px)}
    </style>
    <nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
        <div class="container-fluid">
            <span class="navbar-text me-auto text-nowrap overflow-hidden text-truncate" data-bs-content="' . h($file_name) . '" title="' . lang('Edit Image') . '">
                ' . h($file_name) . '
            </span>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                    <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                    <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                        <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="javascript: history.go(-1)" aria-label="Close">
                        <span class="bi bi-x-lg"></span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>
    <main class="p-0">
        <div class="inline-editor "></div>
        <img class="inline-result" style="display:none;" src="" alt=""  id="image" name="image"/>
        <script>
            var {
                createDefaultImageReader,
                createDefaultImageWriter,
                locale_en_gb,
                setPlugins,
                plugin_crop,
                plugin_crop_defaults,
                plugin_crop_locale_en_gb,
                plugin_resize,
                plugin_resize_defaults,
                plugin_resize_locale_en_gb,
                plugin_filter,
                plugin_filter_defaults,
                plugin_filter_locale_en_gb,
                plugin_finetune,
                plugin_finetune_defaults,
                plugin_finetune_locale_en_gb,
                plugin_decorate,
                plugin_decorate_defaults,
                plugin_decorate_locale_en_gb,
                component_shape_editor_locale_en_gb,
                plugin_sticker,
                plugin_sticker_defaults,
                plugin_sticker_locale_en_gb,
            } = $.fn.doka;
            setPlugins(plugin_crop, plugin_resize, plugin_filter, plugin_finetune, plugin_decorate, plugin_sticker);
            // Merge locale exports
            var ImageEditorLocale = Object.assign(
                {},
                locale_en_gb,
                plugin_crop_locale_en_gb,
                plugin_resize_locale_en_gb,
                plugin_finetune_locale_en_gb,
                plugin_filter_locale_en_gb,
                plugin_decorate_locale_en_gb,
                plugin_sticker_locale_en_gb,
                component_shape_editor_locale_en_gb
            );
            //Main
            ImageEditorLocale.labelButtonExport = "' . lang('Save') . '";
            ImageEditorLocale.labelButtonRevert = "' . lang('undo all') . '";
            ImageEditorLocale.labelButtonUndo = "' . lang('Undo') . '";
            ImageEditorLocale.labelButtonRedo = "' . lang('Redo') . '";
            ImageEditorLocale.cropLabelButtonRotateLeft = "' . lang('Rotate Left') . '";
            ImageEditorLocale.cropLabelButtonRotateRight = "' . lang('Rotate Right') . '";
            ImageEditorLocale.labelDefault = "' . lang('Default') . '";
            ImageEditorLocale.labelClose = "' . lang('Close') . '";
            ImageEditorLocale.labelEdit = "' . lang('Edit') . '";
            ImageEditorLocale.labelNone = "' . lang('None') . '";
            ImageEditorLocale.labelReset = "' . lang('Reset') . '";
            ImageEditorLocale.labelAuto = "' . lang('Auto') . '";
            ImageEditorLocale.statusLabelButtonClose = "' . lang('Close') . '";
            ImageEditorLocale.cropLabelButtonRecenter = "' . lang('Recenter') . '";
            ImageEditorLocale.cropLabelTabRotation = "' . lang('Rotation') . '";
            ImageEditorLocale.cropLabelTabZoom = "' . lang('Zoom') . '";
            ImageEditorLocale.cropLabelSelectPreset = "' . lang('Crop Shape') . '";
            ImageEditorLocale.cropLabelCropBoundary = "' . lang('Crop Boundary') . '";
            ImageEditorLocale.cropLabelCropBoundaryEdge = "' . lang('Resim merkezinden') . '";
            ImageEditorLocale.cropLabelCropBoundaryNone = "' . lang('None') . '";
            ImageEditorLocale.cropLabelButtonFlipHorizontal = "' . lang('Flip Horizontal') . '";
            ImageEditorLocale.cropLabelButtonFlipVertical = "' . lang('Flip Vertical') . '";
            ImageEditorLocale.labelSizeExtraLarge = "' . lang('Extra large') . '";
            ImageEditorLocale.labelSizeExtraSmall = "' . lang('Extra small') . '";
            ImageEditorLocale.labelSizeLarge = "' . lang('Large') . '";
            ImageEditorLocale.labelSizeMedium = "' . lang('Medium') . '";
            ImageEditorLocale.labelSizeMediumLarge = "' . lang('Medium large') . '";
            ImageEditorLocale.labelSizeMediumSmall = "' . lang('Medium small') . '";
            ImageEditorLocale.labelSizeSmall = "' . lang('Small') . '";

            //Tabs
            ImageEditorLocale.cropLabel = "' . lang('Crop') . '";
            ImageEditorLocale.filterLabel = "' . lang('Filter') . '";
            ImageEditorLocale.decorateLabel = "' . lang('Decorate') . '";
            ImageEditorLocale.finetuneLabel = "' . lang('Finetune') . '";
            ImageEditorLocale.resizeLabel = "' . lang('Resize') . '";
            ImageEditorLocale.stickerLabel = "' . lang('Sticker') . '";

            //Finetune
            ImageEditorLocale.finetuneLabelBrightness = "' . lang('Brightness') . '";
            ImageEditorLocale.finetuneLabelClarity = "' . lang('Clarity') . '";
            ImageEditorLocale.finetuneLabelContrast = "' . lang('Contrast') . '";
            ImageEditorLocale.finetuneLabelExposure = "' . lang('Exposure') . '";
            ImageEditorLocale.finetuneLabelGamma = "' . lang('Gamma') . '";
            ImageEditorLocale.finetuneLabelSaturation = "' . lang('Saturation') . '";
            ImageEditorLocale.finetuneLabelVignette = "' . lang('Vignette') . '";

            //Resize
            ImageEditorLocale.resizeLabelInputHeight = "' . lang('Height') . '";
            ImageEditorLocale.resizeLabelInputWidth = "' . lang('Width') . '";
            ImageEditorLocale.resizeTitleButtonMaintainAspectRatio = "' . lang('Maintain Aspect Ratio') . '";

            //Decorate
            ImageEditorLocale.shapeLabelButtonSelectSticker = "' . lang('Select Sticker') . '";
            ImageEditorLocale.shapeLabelInputCancel = "' . lang('Cancel') . '";
            ImageEditorLocale.shapeLabelInputConfirm = "' . lang('Confirm') . '";
            ImageEditorLocale.shapeLabelInputText = "' . lang('Edit text') . '";
            ImageEditorLocale.shapeLabelStrokeNone = "' . lang('Stroke None') . '";
            ImageEditorLocale.shapeLabelToolArrow = "' . lang('Arrow') . '";
            ImageEditorLocale.shapeLabelToolEllipse = "' . lang('Ellipse') . '";
            ImageEditorLocale.shapeLabelToolEraser = "' . lang('Eraser') . '";
            ImageEditorLocale.shapeLabelToolLine = "' . lang('Line') . '";
            ImageEditorLocale.shapeLabelToolPreset = "' . lang('Ön Ayar') . '";
            ImageEditorLocale.shapeLabelToolRectangle = "' . lang('Rectangle') . '";
            ImageEditorLocale.shapeLabelToolSharpie = "' . lang('Sharpie') . '";
            ImageEditorLocale.shapeLabelToolText = "' . lang('Text') . '";
            ImageEditorLocale.shapeTitleBackgroundColor = "' . lang('Fill Color') . '";
            ImageEditorLocale.shapeTitleButtonDuplicate = "' . lang('Duplicate') . '";
            ImageEditorLocale.shapeTitleButtonFlipHorizontal = "' . lang('Flip Horizontal') . '";
            ImageEditorLocale.shapeTitleButtonFlipVertical = "' . lang('Flip Vertical') . '";
            ImageEditorLocale.shapeTitleButtonMoveToFront = "' . lang('Move To Front') . '";
            ImageEditorLocale.shapeTitleButtonRemove = "' . lang('Remove') . '";
            ImageEditorLocale.shapeTitleColorTransparent = "' . lang('Transparent') . '";
            ImageEditorLocale.shapeTitleFontFamily = "' . lang('Font Family') . '";
            ImageEditorLocale.shapeTitleFontSize = "' . lang('Font Size') . '";
            ImageEditorLocale.shapeTitleLineDecorationArrow = "' . lang('Arrow') . '";
            ImageEditorLocale.shapeTitleLineDecorationArrowSolid = "' . lang('Solid Arrow') . '";
            ImageEditorLocale.shapeTitleLineDecorationBar = "' . lang('Bar') . '";
            ImageEditorLocale.shapeTitleLineDecorationCircle = "' . lang('Circle') . '";
            ImageEditorLocale.shapeTitleLineDecorationCircleSolid = "' . lang('Solid Circle') . '";
            ImageEditorLocale.shapeTitleLineDecorationSquare = "' . lang('Square') . '";
            ImageEditorLocale.shapeTitleLineDecorationSquareSolid = "' . lang('Solid Square') . '";
            ImageEditorLocale.shapeTitleLineEnd = "' . lang('Line End') . '";
            ImageEditorLocale.shapeTitleLineStart = "' . lang('Line Start') . '";
            ImageEditorLocale.shapeTitleStrokeColor = "' . lang('Stroke Color') . '";
            ImageEditorLocale.shapeTitleStrokeWidth = "' . lang('Stroke Width') . '";
            ImageEditorLocale.shapeTitleTextAlign = "' . lang('Text Align') . '";
            ImageEditorLocale.shapeTitleTextAlignCenter = "' . lang('Text Center Align') . '";
            ImageEditorLocale.shapeTitleTextAlignLeft = "' . lang('Text Left Align') . '";
            ImageEditorLocale.shapeTitleTextAlignRight = "' . lang('Text Right Align') . '";
            ImageEditorLocale.shapeTitleTextColor = "' . lang('Text Color') . '";
            // inline
            var editor = $(".inline-editor").doka({
                src: "' . $image_location . '",
                imageReader: createDefaultImageReader(),
                imageWriter: createDefaultImageWriter(),
                cropEnableInfoIndicator: true,
                cropEnableButtonRotateRight:true,
                cropEnableButtonToggleCropLimit:true,
                cropEnableButtonFlipVertical:true,
                cropImageSelectionCornerStyle: "hook",
                cropAutoCenterImageSelectionTimeout : 200,
                cropSelectPresetOptions: [
                    [
                        "'. lang('Crop') .'",
                        [
                            [undefined, "' . lang('Custom') . '"],
                            [1, "' . lang('Square') . '"],
                            [1.5, "' . lang('Landscape') . '"],
                            [0.7, "' . lang('Portrait') . '"],
                            [16 / 9, "16:9"],
                            [4 / 3, "4:3"],
                        ],
                    ],
                    [
                        "' . lang('Size') . '",
                        [
                            [[180, 180], "' . lang('Profile Picture') . '"],
                            [[1200, 600], "' . lang('Header Image') . '"],
                            [[800, 400], "' . lang('Timeline Photo') . '"],
                        ],
                    ],
                ],
                stickers: [
                    [    
                        "Emoji",
                        ["🎉", "😄", "👍", "👎", "🍕","😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","🙃","😉","😌","😍","🥰","😘","😗","😙","😚","😋","😛","😝","😜","🤪","🤨","🧐","🤓","😎","🤩","🥳","😏","😒","😞","😔","😟","😕","🙁","☹️","😣","😖","😫","😩","🥺","😢","😭","😤","😠","😡","🤬","🤯","😳","🥵","🥶","😱","😨","😰","😥","😓","🤗","🤔","🤭","🤫","🤥","😶","😐","😑","😬","🙄","😯","😦","😧","😮","😲","🥱","😴","🤤","😪","😵","🤐","🥴","🤢","🤮","🤧","😷","🤒","🤕","🤑","🤠","😈","👿","👹","👺","🤡","💩","👻","💀","☠️","👽","👾","🤖","🎃","😺","😸","😹","😻","😼","😽","🙀","😿","😾","🏳️","🏴","🏁","🚩","🏳️‍🌈","🏳️‍","🏴‍☠️","🔈","🔇","🔉","🔊","🔔","🔕","📣","📢","👁‍🗨","💬","💭","🗯","♠️","♣️","♥️","♦️","🃏","🎴","🀄️","🔣","ℹ️","🔤","🔡","🔠","🆖","🆗","🆙","🆒","🆕","🆓","0️⃣","1️⃣","2️⃣","3️⃣","4️⃣","5️⃣","6️⃣","7️⃣","8️⃣","9️⃣","🔟","🔢","#️⃣","*️⃣"],
                        // group properties
                        {},
                    ],
                ],
                filterFunctions: plugin_filter_defaults.filterFunctions,
                filterOptions: plugin_filter_defaults.filterOptions,
                finetuneControlConfiguration: plugin_finetune_defaults.finetuneControlConfiguration,
                finetuneOptions: plugin_finetune_defaults.finetuneOptions,
                decorateTools: plugin_decorate_defaults.decorateTools,
                decorateToolShapes: plugin_decorate_defaults.decorateToolShapes,
                decorateShapeControls: plugin_decorate_defaults.decorateShapeControls,
                locale: ImageEditorLocale,
            });
            $(".inline-editor").on("doka:process", (e) => {
                    var newImage = URL.createObjectURL(e.detail.dest);
                    $("#image").attr("src", newImage);
            });
            $("#image").on("load", function () {
                const fetchAsBlob = url => fetch(url).then(response => response.blob());
            
                const convertBlobToBase64 = blob => new Promise((resolve, reject) => {
                const reader = new FileReader;
                reader.onerror = reject;
                reader.onload = () => {
                    resolve(reader.result);
                };
                reader.readAsDataURL(blob);
            });
                
            fetchAsBlob($(this).attr("src")).then(convertBlobToBase64).then(
                function(value) {
                    var DATA = value.replace("data:image/jpeg;base64,","data:image/png;base64,");
                    $("input[name=image_file]").val(DATA);
                }
            );
            $("#confirm_modal.modal").modal("toggle");
        });
        </script>
        <div class="modal fade" id="confirm_modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">' . lang('Save Option') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="font-size:smaller;">
                        <h5>' . lang('Keep Original And Save') . '</h5>
                        <p>' . lang('Keep the original file with the same name, create a new file from the editing properties and save.') . '</p>
                        <h5>' . lang('Replace') . '</h5>
                        <p>' . lang('Overwrite the changes to the file. Do not create one more file.') . '</p>
                    </div>
                    <div class="modal-footer">
                        <form id="image_form" name="form" action="image_editor_edit.php" method="post">
                            ' . get_token_field() . '
                            <input type="hidden" name="file_id" value="' . $file_id . '" />
                            <input type="hidden" name="send_to" value="' . $send_to . '" />
                            <input type="hidden" name="object_type" value="' . $object_type . '" />
                            <input type="hidden" name="object_id" value="' . $object_id . '" />
                            <input type="hidden" name="image_file" value="" />
                            <input type="hidden" name="save_option" value="" />
                            <div class="d-flex justify-content-end">
                                <div class="input-group input-group-sm me-2">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="$(\'input[name=save_option]\').val(\'Keep and Save\');$(\'form#image_form\').submit();">' . lang('Keep Original And Save') . '</button>
                                    <select class="form-select" id="save_option_item_type" name="save_option_item_type">
                                        <option ' . $output_jpg_selected  . 'value="jpg">jpg</option>
                                        <option ' . $output_png_selected . 'value="png">png</option>
                                        <option ' . $output_webp_selected  . 'value="webp">webp</option>
                                        <option ' . $output_gif_selected  . 'value="webp">gif</option>

                                    </select>
                                </div>
                           
                                <button type="button" class="btn btn-sm btn-primary" onclick="$(\'input[name=save_option]\').val(\'Replace\');$(\'form#image_form\').submit();">' . lang('Replace') . '</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
                        
    </main>' . output_footer_secure();

}
else
{

    // get parameters from image editor
    $object_type = $_POST['object_type'];
    $object_id = $_POST['object_id'];
    $file_id = $_POST['file_id'];
    $send_to = $_POST['send_to'];
    $image_data = $_POST['image_file'];
    $error = false;

    // get file data from database
    $query = "SELECT
                        name,
                        folder,
                        design,
                        type
                    FROM files 
                    WHERE id = '" . escape($file_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $file_name = $row['name'];
    $original_file_name = $row['name'];
    $folder_id = $row['folder'];
    $design = $row['design'];


    $type = $row['type'];
    if ($_POST['save_option'] == 'Keep and Save'){
        $type = $_POST['save_option_item_type'];
    }
   

    /** Start Access Control Checks **/

    // if the user has a user role, then check access
    if ($user['role'] == 3)
    {
        // if user does not have access to edit this file or if it is a design file, then output error
        if ((check_edit_access($folder_id) == false) || ($design == 1))
        {
            log_activity(lang('access denied to save image from ImageEditor because user does not have access to edit image'), $_SESSION['sessionusername']);
            $error = true;
        }

        // do access control for various object types
        switch ($object_type)
        {
            case 'ad':
                // get this ad's name and ad region id
                $query = "SELECT name, ad_region_id FROM ads WHERE id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $name = $row['name'];
                $ad_region_id = $row['ad_region_id'];

                // if the user does not have access to the ad region that this ad is in, then log activity and output error
                if (in_array($ad_region_id, get_items_user_can_edit('ad_regions', $user['id'])) == false)
                {
                    log_activity(lang(array('string'=>'access denied to update ad content with image from ImageEditor because user does not have access to edit ad ({var:1})','vars'=>$name)), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'pregion':
                // A user might be editing images in an inline page region that does not exist yet,
                // because region has not been saved/created yet, so that is why we add this check.
                if ($object_id)
                {
                    // get the folder id from the page that this pregion is on
                    $query = "SELECT page.page_folder as pregion_folder_id
                                    FROM pregion
                                    LEFT JOIN page ON pregion.pregion_page = page.page_id
                                    WHERE pregion.pregion_id = '" . escape($object_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $pregion_folder_id = $row['pregion_folder_id'];

                    // if the user does not have edit access to the pregion's folder, then log activity and output error
                    if (check_edit_access($pregion_folder_id) == false)
                    {
                        log_activity(lang('access denied to update page region content with image from ImageEditor because user does not have access to edit folder that page region is in'), $_SESSION['sessionusername']);
                        $error = true;
                    }
                }

            break;

            case 'system_region_header':
                // get the folder id from the page that this system region header is on
                $query = "SELECT page_folder as system_region_header_folder_id
                                FROM page
                                WHERE page_id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $system_region_header_folder_id = $row['system_region_header_folder_id'];

                // if the user does not have edit access to the page's folder, then log activity and output error
                if (check_edit_access($system_region_header_folder_id) == false)
                {
                    log_activity(lang('access denied to update system region header content with image from ImageEditor because user does not have access to edit folder that the page is in'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'system_region_footer':
                // get the folder id from the page that this system region footer is on
                $query = "SELECT page_folder as system_region_footer_folder_id
                                FROM page
                                WHERE page_id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $system_region_footer_folder_id = $row['system_region_footer_folder_id'];

                // if the user does not have edit access to the page's folder, then log activity and output error
                if (check_edit_access($system_region_footer_folder_id) == false)
                {
                    log_activity(lang('access denied to update system region footer content with image from ImageEditor because user does not have access to edit folder that the page is in'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'cregion':
                // if user does not have access to this common region, then user does not have access to edit region, so log activity and output error
                if (in_array($object_id, get_items_user_can_edit('common_regions', $user['id'])) == false)
                {
                    log_activity(lang('access denied to update common region content with image from ImageEditor because user does not have access to edit common region'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'calendar_event':
                // if user does not have access to manage calendars or if they do not have access to edit this calendar event then log activity and output error
                if (($user['manage_calendars'] == false) || (validate_calendar_event_access($object_id) == false))
                {
                    log_activity(lang('access denied to update calendar event content with image from ImageEditor because user does not have access to edit calendar that the calendar event is in'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'product_group':
            case 'product':
                // if user does not have access to manage ecommerce then log activity and output error
                if ($user['manage_ecommerce'] == false)
                {
                    log_activity(lang('access denied to update product or product group with image from ImageEditor because user does not have access to commerce'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;

            case 'form_field':
                // get the folder id from the page that the custom form is in
                $query = "SELECT page.page_folder as form_field_folder_id
                                FROM form_fields
                                LEFT JOIN page ON form_fields.page_id = page.page_id
                                WHERE form_fields.id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $form_field_folder_id = $row['form_field_folder_id'];

                // if the user does not have edit access to the form field's folder, then log activity and output error
                if (check_edit_access($form_field_folder_id) == false)
                {
                    log_activity(lang('access denied to update form field content with image from ImageEditor because user does not have access to edit folder that custom form is in'), $_SESSION['sessionusername']);
                    $error = true;
                }

            break;
        }

        // if there was an error then output error
        if ($error == true)
        {
            output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // if there was an error accessing the file, then output error
    if ($image_data === false)
    {
        log_activity(lang(array('string'=>'image ({var:1}) could not be retrieved from Editor','vars'=>$file_name)), $_SESSION['sessionusername']);
        output_error(lang('We\'re sorry, we encountered a problem while retrieving your edited image from Editor. Your image was not updated on the website. Please try again later.'));
    }


    // if the file was not a GIF, then the image needs to be replaced
    // we don't want to replace GIF's because we always save a new copy as a PNG,
    // because the image editor does not support exporting as GIF.
    if (($_POST['save_option'] == 'Replace') && (mb_strtolower($type) != 'gif')) {

        // Strip base64 header for supported formats
        $image_data = preg_replace('/^data:image\/(jpg|jpeg|png|webp);base64,/', '', $image_data);
        $image_data = base64_decode($image_data);

        // delete the existing file. we have to do this in order to avoid permission errors in certain circumstances
        unlink(FILE_DIRECTORY_PATH . '/' . $file_name);

        // save the file
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
        fwrite($handle, $image_data);
        fclose($handle);

        // update image in database
        $query = "UPDATE files 
                    SET 
                        size = '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "',
                        optimized = '0',
                        timestamp = UNIX_TIMESTAMP(), 
                        user = '" . $user['id'] . "' 
                  WHERE id = '" . escape($file_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'file ({var:1}) was modified via Image Editor','vars'=>$file_name)), $_SESSION['sessionusername']);

    } else if (($_POST['save_option'] == 'Keep and Save') || (mb_strtolower($type) == 'gif')) {

        // Strip base64 header for supported formats
        $image_data = preg_replace('/^data:image\/(jpg|jpeg|png|webp);base64,/', '', $image_data);
        $image_data = base64_decode($image_data);

        // get file name with and without file extension
        $file_name_without_extension = mb_substr($file_name, 0, mb_strrpos($file_name, '.'));
        $file_extension = mb_substr($file_name, mb_strrpos($file_name, '.') + 1);

        // Normalize extension based on type
        switch (mb_strtolower($type)) {
            case 'jpg':
            case 'jpeg':
                $file_name = $file_name_without_extension . '.jpg';
                $file_extension = 'jpg';
                break;

            case 'png':
                $file_name = $file_name_without_extension . '.png';
                $file_extension = 'png';
                break;

            case 'gif':
                // GIF is not supported for saving, so always convert to PNG
                $file_name = $file_name_without_extension . '.png';
                $file_extension = 'png';
                break;

            case 'webp':
                $file_name = $file_name_without_extension . '.webp';
                $file_extension = 'webp';
                break;

            default:
                // fallback to jpg
                $file_name = $file_name_without_extension . '.jpg';
                $file_extension = 'jpg';
                break;
        }

        // Check if file name is already in use and change it if necessary.
        $file_name = get_unique_name(array(
            'name' => $file_name,
            'type' => 'file'
        ));

        // save the file
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
        fwrite($handle, $image_data);
        fclose($handle);

        // insert file data into files table
        $query = "INSERT INTO files (
                        name,
                        folder,
                        type,
                        size,
                        user,
                        design,
                        optimized,
                        timestamp) 
                  VALUES (
                        '" . escape($file_name) . "',
                        '" . escape($folder_id) . "',
                        '" . escape($file_extension) . "',
                        '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "',
                        '" . $user['id'] . "',
                        '0',
                        '0',
                        UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'file ({var:1}) was created via Image Editor','vars'=>$file_name)), $_SESSION['sessionusername']);
    }



    /** replace image in content with new dimensions and src value if necessary **/

    $column_to_update = '';

    // if there is a column to update, then set it so that it can be used later on in various places
    if ($_GET['column_to_update'])
    {
        $column_to_update = $_GET['column_to_update'];
    }

    switch ($object_type)
    {
        case 'ad':
            // get content
            $query = "SELECT content FROM ads WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['content'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE ads SET content = '" . escape($content) . "' WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'calendar_event':
            // get content
            $query = "SELECT full_description FROM calendar_events WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['full_description'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE calendar_events SET full_description = '" . escape($content) . "' WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'cregion':
            // get content
            $query = "SELECT cregion_content as content FROM cregion WHERE cregion_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['content'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE cregion SET cregion_content = '" . escape($content) . "' WHERE cregion_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'pregion':
            // A user might be editing images in an inline page region that does not exist yet,
            // because region has not been saved/created yet, so that is why we add this check.
            if ($object_id)
            {
                // get content
                $query = "SELECT pregion_content as content FROM pregion WHERE pregion_id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $content = $row['content'];

                $content = update_image_in_content($content, $original_file_name, $file_name);

                // update content in database
                $query = "UPDATE pregion SET pregion_content = '" . escape($content) . "' WHERE pregion_id = '" . escape($object_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }

        break;

        case 'system_region_header':
            // get content
            $query = "SELECT system_region_header as content FROM page WHERE page_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['content'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE page SET system_region_header = '" . escape($content) . "' WHERE page_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'system_region_footer':
            // get content
            $query = "SELECT system_region_footer as content FROM page WHERE page_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['content'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE page SET system_region_footer = '" . escape($content) . "' WHERE page_id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'product':
        case 'product_group':
            // set the sql table based on the object type
            if ($object_type == 'product')
            {
                $sql_table = 'products';
            }
            else
            {
                $sql_table = 'product_groups';
            }

            $sql_column = '';
            $content = '';

            // set column to update and get content
            switch ($column_to_update)
            {
                case 'image_name':
                    $sql_column = 'image_name';
                    $content = $file_name;
                break;

                case 'details':
                    $sql_column = 'details';

                    $query = "SELECT details FROM $sql_table WHERE id = '" . escape($object_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $content = update_image_in_content($row['details'], $original_file_name, $file_name);
                break;

                default:
                    $sql_column = 'full_description';

                    $query = "SELECT full_description FROM $sql_table WHERE id = '" . escape($object_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $content = update_image_in_content($row['full_description'], $original_file_name, $file_name);
                break;
            }

            // update content in database
            $query = "UPDATE $sql_table SET $sql_column = '" . escape($content) . "' WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;

        case 'form_field':
            // get content
            $query = "SELECT information FROM form_fields WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $content = $row['information'];

            $content = update_image_in_content($content, $original_file_name, $file_name);

            // update content in database
            $query = "UPDATE form_fields SET information = '" . escape($content) . "' WHERE id = '" . escape($object_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        break;
    }

    // if the image was replaced and it was not a GIF, then it has the same file name, so output code that will update the user's cache,
    // so that the user will see the new image and forward the user to the original page that they were at
    if ($_POST['save_option'] == 'Replace')
    {
        $output_rawurlencode_iframe = '';

        // if the file name is different when it is rawurlencoded, then we need to clear the cache for both the plain file name and the rawurlencoded file name
        // IE will show the old image if we do not do this, because sometimes a file is embedded with its plain name and sometimes with its rawurlencoded name
        if ($file_name != encode_url_path($file_name))
        {
            $output_rawurlencode_iframe = '<iframe id="image_rawurlencode" src="' . OUTPUT_PATH . h(encode_url_path($file_name)) . '" style="display: none"></iframe>';
        }

        print '<!DOCTYPE html>
                        <html lang="en">
                            <head>
                                <meta charset="utf-8">
                                ' . get_generator_meta_tag() . '
                                <script type="text/javascript">
                                    function init()
                                    {
                                        // reload the iframe with the image
                                        document.getElementById("image").contentWindow.location.reload(true);
            
                                        // if the rawurlencode iframe exists, then reload it
                                        if (document.getElementById("image_rawurlencode")) {
                                            document.getElementById("image_rawurlencode").contentWindow.location.reload(true);
                                        }
            
                                        // wait a little bit to make sure that the iframe(s) have reloaded and then send the user to the original page that they came from
                                        setTimeout("window.parent.location = \'' . URL_SCHEME . HOSTNAME . escape_javascript($send_to) . '\';", 1000);
                                    }
            
                                    window.onload = init; 
                                </script>
                            </head>
                            <body>
                                <iframe id="image" src="' . OUTPUT_PATH . h($file_name) . '" style="display: none"></iframe>
                                ' . $output_rawurlencode_iframe . '
                            </body>
                        </html>';

        // else the user chose to save a new copy or the image was a GIF, so the image has a new name,
        // so we don't need to update the cache, so forward user to the page that they came from
        
    }
    else
    {
        print '<!DOCTYPE html>
                        <html lang="en">
                            <head>
                                <meta charset="utf-8">
                                ' . get_generator_meta_tag() . '
                                <script type="text/javascript">
                                    function init()
                                    {
                                        window.parent.location = "' . URL_SCHEME . HOSTNAME . escape_javascript($send_to) . '";
                                    }
            
                                    window.onload = init; 
                                </script>
                            </head>
                            <body>
                            </body>
                        </html>';
    }

}

