<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Appearance screen writes: the panel theme and the rich-text editor.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}


    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            custom_css = '" . escape(post_value('custom_css')) . "',
            advanced_visual_effects = '" . escape(post_value('advanced_visual_effects')) . "',
            page_editor_version = '" . escape(post_value('page_editor_version')) . "',
            page_editor_font = '" . escape(post_value('page_editor_font')) . "',
            page_editor_font_size = '" . escape(post_value('page_editor_font_size')) . "',
            page_editor_font_style = '" . escape(post_value('page_editor_font_style')) . "',
            page_editor_font_color = '" . escape(post_value('page_editor_font_color')) . "',
            page_editor_background_color = '" . escape(post_value('page_editor_background_color')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");
