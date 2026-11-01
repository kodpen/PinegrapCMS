<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit rule registry: one row per concept a page audit judges (an image
 * without alt text, a skipped heading level, a page without a language), with
 * the code each engine reports it under. The SEO structure rules
 * (seo_structure.php), the accessibility report (includes/fn/a11y.php) and
 * the visual editor's checks (validateTree() in style_designer.src.js) name
 * the same concept in three ways; this table is where they meet, so a
 * severity, a WCAG criterion or a document-only flag is written once.
 *
 * pg_seo_structure_severities(), pg_seo_document_only_codes() and
 * pg_a11y_rules() read it. tools/check_audit_rules.php (CI) keeps it whole:
 * every editor rule id and seo: code in style_designer.src.js has a row, and
 * every code in a row has a checker.
 *
 * Loaded by seo_structure.php and includes/fn/a11y.php, not by functions.php.
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
 * Every concept, keyed by its name:
 *   seo       the SEO structure code ('' when SEO has no rule for it)
 *   a11y      the accessibility report code ('' when the report has none)
 *   severity  scope => error|warning|notice, for each scope with a code
 *   criterion the WCAG 2.2 success criterion and its level ('' without an a11y code)
 *   editor    the visual editor's rule ids that find it (one id may serve two
 *             concepts: empty_btn is both a link and a button name)
 *   document  the scopes in which it needs a whole page: no fragment is
 *             judged by it there (seo-structure-non-html-output.md; the SEO
 *             list is what the editor carries over from the stored pass)
 *   browser   only a browser can measure it in full (resolved CSS, layout);
 *             the server reads inline styles only
 *
 * The accessibility rows come first, in the order the report lists its checks.
 *
 * @return array<string,array>
 */
function pg_audit_rules()
{
    static $rules = null;

    if ($rules !== null) {
        return $rules;
    }

    $rows = array(
        // Concepts the accessibility report checks, in its checklist order.
        'page_title'        => array('seo' => array('title_tag_missing', 'error'), 'a11y' => array('a11y_page_title', 'error', '2.4.2', 'A'), 'document' => array('seo', 'a11y')),
        'html_lang'         => array('seo' => array('html_lang_missing', 'warning'), 'a11y' => array('a11y_html_lang', 'error', '3.1.1', 'A'), 'document' => array('seo', 'a11y')),
        'main_landmark'     => array('seo' => array('main_missing', 'notice'), 'a11y' => array('a11y_main_missing', 'warning', '2.4.1', 'A'), 'editor' => array('main_missing'), 'document' => array('seo', 'a11y')),
        'heading_order'     => array('seo' => array('heading_level_skip', 'warning'), 'a11y' => array('a11y_heading_order', 'warning', '1.3.1', 'A'), 'editor' => array('heading_skip'), 'document' => array('seo')),
        'img_alt'           => array('seo' => array('img_no_alt', 'error'), 'a11y' => array('a11y_img_alt', 'error', '1.1.1', 'A'), 'editor' => array('img_alt')),
        'form_label'        => array('seo' => array('input_no_label', 'warning'), 'a11y' => array('a11y_form_label', 'error', '1.3.1', 'A'), 'editor' => array('input_no_label')),
        'idref_broken'      => array('a11y' => array('a11y_idref_broken', 'warning', '1.3.1', 'A')),
        'error_association' => array('a11y' => array('a11y_error_association', 'warning', '3.3.1', 'A')),
        'link_name'         => array('seo' => array('link_empty', 'error'), 'a11y' => array('a11y_link_name', 'error', '2.4.4', 'A'), 'editor' => array('empty_btn')),
        'link_generic'      => array('seo' => array('link_generic_anchor', 'notice'), 'a11y' => array('a11y_link_generic', 'notice', '2.4.4', 'A'), 'editor' => array('link_generic')),
        'button_name'       => array('seo' => array('button_empty', 'warning'), 'a11y' => array('a11y_button_name', 'error', '4.1.2', 'A'), 'editor' => array('empty_btn')),
        'frame_title'       => array('seo' => array('iframe_no_title', 'notice'), 'a11y' => array('a11y_frame_title', 'error', '4.1.2', 'A')),
        'media_autoplay'    => array('a11y' => array('a11y_media_autoplay', 'warning', '1.4.2', 'A')),
        'contrast'          => array('a11y' => array('a11y_contrast', 'warning', '1.4.3', 'AA'), 'editor' => array('low_contrast'), 'browser' => true),
        'target_size'       => array('a11y' => array('a11y_target_size', 'notice', '2.5.8', 'AA'), 'editor' => array('target_size'), 'browser' => true),

        // SEO only. Document level tags: the page template never emitted the
        // tag, a different failure from an empty field.
        'title_tag_multiple'           => array('seo' => array('title_tag_multiple', 'warning'), 'document' => array('seo')),
        'meta_description_tag_missing' => array('seo' => array('meta_description_tag_missing', 'error'), 'document' => array('seo')),
        'canonical_missing'            => array('seo' => array('canonical_missing', 'warning')),
        'canonical_multiple'           => array('seo' => array('canonical_multiple', 'warning')),
        'noindex_but_in_sitemap'       => array('seo' => array('noindex_but_in_sitemap', 'error')),
        'viewport_missing'             => array('seo' => array('viewport_missing', 'notice')),
        'og_incomplete'                => array('seo' => array('og_incomplete', 'notice'), 'document' => array('seo')),

        // Headings
        'h1_missing'             => array('seo' => array('h1_missing', 'error'), 'editor' => array('h1_missing'), 'document' => array('seo')),
        'h1_multiple'            => array('seo' => array('h1_multiple', 'warning'), 'editor' => array('h1_multiple'), 'document' => array('seo')),
        'h1_empty'               => array('seo' => array('h1_empty', 'error')),
        'h1_equals_title'        => array('seo' => array('h1_equals_title', 'notice')),
        'h1_too_long'            => array('seo' => array('h1_too_long', 'notice')),
        'heading_starts_at_h2'   => array('seo' => array('heading_starts_at_h2', 'warning'), 'editor' => array('heading_starts_at_h2'), 'document' => array('seo')),
        'heading_empty'          => array('seo' => array('heading_empty', 'warning'), 'editor' => array('heading_empty')),
        'heading_is_paragraph'   => array('seo' => array('heading_is_paragraph', 'notice')),
        'heading_duplicate_text' => array('seo' => array('heading_duplicate_text', 'notice'), 'document' => array('seo')),
        // Set by the page analyzer rather than by the rule pass: it needs to
        // know which layer the heading came from.
        'h1_only_in_template'    => array('seo' => array('h1_only_in_template', 'warning')),

        // Text
        'p_contains_block'   => array('seo' => array('p_contains_block', 'error')),
        'p_empty'            => array('seo' => array('p_empty', 'notice')),
        'br_as_paragraph'    => array('seo' => array('br_as_paragraph', 'notice')),
        'presentational_tag' => array('seo' => array('presentational_tag', 'warning'), 'editor' => array('presentational_tag')),
        'bold_italic_tag'    => array('seo' => array('bold_italic_tag', 'notice')),
        'inline_style_heavy' => array('seo' => array('inline_style_heavy', 'notice')),

        // Lists and tables
        'list_invalid_child' => array('seo' => array('list_invalid_child', 'error')),
        'table_no_header'    => array('seo' => array('table_no_header', 'notice')),

        // Images
        'img_alt_is_filename' => array('seo' => array('img_alt_is_filename', 'warning'), 'editor' => array('img_alt_filename')),
        'img_no_dimensions'   => array('seo' => array('img_no_dimensions', 'notice')),
        'img_no_lazy'         => array('seo' => array('img_no_lazy', 'notice')),

        // Links
        'render_failed'          => array('seo' => array('render_failed', 'notice')),
        'link_empty_href'        => array('seo' => array('link_empty_href', 'warning')),
        'link_blank_no_noopener' => array('seo' => array('link_blank_no_noopener', 'warning'), 'editor' => array('link_blank_noopener')),

        // Landmarks
        'main_multiple'    => array('seo' => array('main_multiple', 'warning'), 'editor' => array('main_multiple')),
        'landmark_missing' => array('seo' => array('landmark_missing', 'notice'), 'editor' => array('landmark_missing')),

        // Content volume
        'thin_content'   => array('seo' => array('thin_content', 'warning'), 'document' => array('seo')),
        'low_text_ratio' => array('seo' => array('low_text_ratio', 'notice')),

        // Structured data
        'jsonld_invalid'            => array('seo' => array('jsonld_invalid', 'error')),
        'jsonld_missing_type'       => array('seo' => array('jsonld_missing_type', 'warning')),
        'jsonld_missing'            => array('seo' => array('jsonld_missing', 'notice'), 'document' => array('seo')),
        'product_schema_incomplete' => array('seo' => array('product_schema_incomplete', 'notice')),

        // Only the visual editor checks these: they read the editor's tree
        // (ids, nesting, forms and their system widget bindings).
        'dup_id'             => array('editor' => array('dup_id')),
        'nested_interactive' => array('editor' => array('nested_interactive')),
        'form_no_submit'     => array('editor' => array('form_no_submit')),
        'form_no_action'     => array('editor' => array('form_no_action')),
        'cf_field_unnamed'   => array('editor' => array('cf_field_unnamed')),
        'redundant_entry'    => array('editor' => array('redundant_entry')),
    );

    $rules = array();

    foreach ($rows as $concept => $row) {
        $rule = array(
            'seo'       => '',
            'a11y'      => '',
            'severity'  => array(),
            'criterion' => '',
            'level'     => '',
            'editor'    => isset($row['editor']) ? $row['editor'] : array(),
            'document'  => isset($row['document']) ? $row['document'] : array(),
            'browser'   => !empty($row['browser']),
        );

        if (isset($row['seo'])) {
            $rule['seo'] = $row['seo'][0];
            $rule['severity']['seo'] = $row['seo'][1];
        }

        if (isset($row['a11y'])) {
            $rule['a11y'] = $row['a11y'][0];
            $rule['severity']['a11y'] = $row['a11y'][1];
            $rule['criterion'] = $row['a11y'][2];
            $rule['level'] = $row['a11y'][3];
        }

        $rules[$concept] = $rule;
    }

    return $rules;
}

/**
 * The concept a scope's code belongs to, '' when no row has it.
 *
 * @param string $scope 'seo' or 'a11y'
 * @param string $code
 * @return string
 */
function pg_audit_rule_concept($scope, $code)
{
    foreach (pg_audit_rules() as $concept => $rule) {
        if (isset($rule[$scope]) && is_string($rule[$scope]) && $rule[$scope] !== '' && $rule[$scope] === $code) {
            return $concept;
        }
    }

    return '';
}

/**
 * The concepts an editor rule id finds, in registry order.
 *
 * @param string $rule_id
 * @return string[]
 */
function pg_audit_rule_concepts_of_editor($rule_id)
{
    $concepts = array();

    foreach (pg_audit_rules() as $concept => $rule) {
        if (in_array($rule_id, $rule['editor'], true)) {
            $concepts[] = $concept;
        }
    }

    return $concepts;
}
