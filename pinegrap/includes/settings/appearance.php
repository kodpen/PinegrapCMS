<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Appearance cards: the panel theme and the rich-text editor.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and <key>.save.php writes what they edit. The variables they read
 * are prepared by includes/settings/prep.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// ── Theme ──
$pg_settings_cards[] = '
                        <div id="pgset-theme" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Theme') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $advanced_visual_effects_checked . ' class="form-check-input" type="checkbox" id="advanced_visual_effects" name="advanced_visual_effects"/>
                                                <label class="form-check-label" for="advanced_visual_effects">' . lang('Enable Transparent Acrylic Effect') . '<br/><span class="form-text">' . lang('If the device you are using is weak in hardware, you can disable this setting to get a faster interface.') . '</span></label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">' . lang('Custom CSS') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="custom_css" id="custom_css" rows="25" cols="60" wrap="off">' . $custom_css . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'custom_css', 'code_type' => 'css')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';

// ── Rich-text Editor ──
$pg_settings_cards[] = '
                        <div id="pgset-editor" class="pg-set-card">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Rich-text Editor') . '
                                </div>
                                <div class="card-body">
                                   <div class="row gy-3">
                                        <div class="col-12 ">
                                            <label class="form-label">'. lang('Editor Version') . '</label>
                                            <div class="form-check">
                                                <input value="latest" class="form-check-input" type="radio" id="page_editor_version_latest" name="page_editor_version" ' . $page_editor_version_latest_checked . '>
                                                <label class="form-check-label" for="page_editor_version_latest">'. lang('Latest') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="previous" class="form-check-input" type="radio" id="page_editor_version_previous" name="page_editor_version" ' . $page_editor_version_previous_checked . '>
                                                <label class="form-check-label" for="page_editor_version_previous">'. lang('Previous') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_checked . ' class="form-check-input" type="checkbox" id="page_editor_font" name="page_editor_font"/>
                                                <label class="form-check-label" for="page_editor_font">' . lang('Font Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_size_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_size" name="page_editor_font_size"/>
                                                <label class="form-check-label" for="page_editor_font_size">' . lang('Font Size Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_style_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_style" name="page_editor_font_style"/>
                                                <label class="form-check-label" for="page_editor_font_style">' . lang('Font Style Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_color_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_color" name="page_editor_font_color"/>
                                                <label class="form-check-label" for="page_editor_font_color">' . lang('Font Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_background_color_checked . ' class="form-check-input" type="checkbox" id="page_editor_background_color" name="page_editor_background_color"/>
                                                <label class="form-check-label" for="page_editor_background_color">' . lang('Background Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="spell_check_engine_info" class="form-label">' . lang('Spell Checker Engine') . '</label>
                                            <input class="form-control disabled" readonly="true" id="spell_check_engine_info" value="' . $spell_checker_engine_info['name'] . '"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
