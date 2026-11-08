<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its collection highlights: the staff page that
 * adds a highlight (the Collection Highlights form, a collection, with the
 * sample highlights written in when it is made), the collection page with
 * its department filter and the object's own page. Included after parts.php
 * and exhibitions.php in the same scope (it uses the list pieces of
 * exhibitions.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * A highlight is a record of the Collection Highlights form. Its department
 * is a pick list whose answers are the words parts.php gives, so a link
 * with ?department= (the filter row, the department cards, the footer, the
 * object page) filters the list by the words a visitor sees. The period is
 * words, never a year. Featured is the museum's own choice (office use
 * only): the collection page opens with the featured highlights above the
 * full list. The newest added comes first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$object_pages = array();

// The highlight cards of a list, in rows of three.
$object_grid = function () use ($widget_root, $row, $loop, $object_card) {
    return $widget_root(array($row(array($loop(array($object_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The collection: the featured highlights, the departments and every
// highlight, narrowed by department from the address.
$object_department_cols = array();
foreach (array_keys($departments) as $key) $object_department_cols[] = $department_card($key);
$object_pages[] = array(
    'key'              => 'collection',
    'name'             => lang('collection'),
    'folder'           => 'public',
    'title'            => lang('Collection Highlights'),
    'meta_description' => lang('Highlights of the museum\'s collection: paintings, sculpture, works on paper, decorative arts, photography and archaeology.'),
    'tree'             => $page(array(
        $page_header(lang('Collection Highlights'), lang('A few of the works we care for, from ancient coins to street photographs. Choose a department to narrow the list.')),
        // One work to look closer at, with its wall label and three details
        // (Museum: Look Closer).
        $look_closer('harbour', 'mz-collection-closer'),
        $el('section', 'py-5 bg-body-tertiary', lang('Featured'), array(
            $container(array(
                $section_header(lang('Do not miss'), lang('Highlights to see first')),
                $widget('collection_featured'),
            )),
        )),
        $el('section', 'py-5', lang('Departments'), array(
            $container(array(
                $section_header(lang('Departments'), lang('Six departments, one collection')),
                $rise($row($object_department_cols, '4')),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Collection'), array(
            $container(array(
                $mz_filter_bar('collection', lang('Department'), 'department', $department_titles),
                $widget('collection_list'),
            )),
        ), $searchable('mz-collection', lang('art, artworks, objects, paintings, sculpture, drawings, prints, ceramics, photographs, coins, archaeology'))),
    )),
);

// One highlight: its record.
$object_pages[] = array(
    'key'              => 'object',
    'name'             => lang('object'),
    'folder'           => 'public',
    'title'            => lang('Object'),
    'meta_description' => lang('A work from the museum\'s collection: who made it, when, what it is made of and where to see it.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('object_detail'),
    )),
);

// The staff page that adds a highlight. The form is the Collection
// Highlights collection; the sample highlights are written in when the
// form is made, so the lists do not open empty
// (pg_cf_seed_sample_records()).
$sample_objects = array();
foreach ($objects as $key => $o) {
    $sample_objects[] = array(
        'title'      => $o['title'],
        'department' => $object_department($key),
        'maker'      => $o['maker'],
        'period'     => $o['period'],
        'material'   => $o['material'],
        'gallery'    => $o['gallery'],
        'story'      => $o['story'],
        'photo_1'    => $photo('object-' . $key, 1200, 1200),
        'featured'   => $o['featured'] ? $mz_featured_choices[0] : $mz_featured_choices[1],
    );
}
$object_pages[] = array(
    'key'     => 'object_new',
    'name'    => lang('new-object'),
    'folder'  => 'private',
    'title'   => lang('New Highlight'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Collection Highlights'),
        'collection'           => 1,
        'confirmation_message' => lang('The highlight is published.'),
        'confirmation_page_id' => '{{tab:collection}}',
        'sample_records'       => $sample_objects,
        // The photo is the object's share image
        // (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Highlight'), lang('Add a work of the collection with its maker, period and photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('object_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$object_widgets = array(

    // The staff's Collection Highlights form: one work. Featured is the
    // museum's choice (office use only) and starts as No.
    'object_form' => array(
        'page'   => 'object_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The work')),
            $field('mz-object-title', lang('Title'), pg_cf_with_role($input('mz-object-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('mz-object-department', lang('Department'), pg_cf_with_role($select('mz-object-department', 'department', lang('Choose a department'), $department_titles), 'category'))), '6'),
                $col(array($field('mz-object-maker', lang('Artist or Maker'), $input('mz-object-maker', 'text', 'maker', 'off'), 'mb-3',
                    lang('A name, or words such as "An unknown surveyor".'))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('mz-object-period', lang('Period'), $input('mz-object-period', 'text', 'period', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $objects['harbour']['period']))), '6'),
                $col(array($field('mz-object-material', lang('Material'), $input('mz-object-material', 'text', 'material', 'off'))), '6'),
            ), '3'),
            $field('mz-object-gallery', lang('Where to see it'), $input('mz-object-gallery', 'text', 'gallery', 'off'), 'mb-3',
                sprintf(lang('A gallery, or %s.'), $objects['map']['gallery'])),
            $form_step(2, lang('Story')),
            $field('mz-object-story', lang('Story'), pg_cf_with_role($textarea('mz-object-story', 'story', 5), 'description'), 'mb-3',
                lang('Its first lines show on the cards of the lists.')),
            $form_step(3, lang('Photo')),
            $field('mz-object-photo-1', lang('Photo'), pg_cf_with_role($file('mz-object-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('mz-object-featured', lang('Featured'), $select('mz-object-featured', 'featured', '', $mz_featured_choices, false, $mz_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured highlight opens the collection page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The featured highlights, three at most.
    'collection_featured' => array(
        'page'   => 'collection',
        'slug'   => lang('form-list'),
        'config' => $mz_record_list('object_new', 'object', lang('No highlight is featured yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($mz_filter('featured', 'is equal to', $mz_featured_choices[0])),
        )),
        'tree'   => $object_grid(),
    ),

    // Every highlight, filtered by the address (department), the newest
    // first, with a search over the words of the highlights.
    'collection_list' => array(
        'page'   => 'collection',
        'slug'   => lang('form-list'),
        'config' => $mz_record_list('object_new', 'object', lang('No highlight in this department yet. Choose another, or see the whole collection.'), array(
            'items_per_page' => 12,
            'filters'        => array($mz_filter('department', 'is equal to', '', 'department')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'department', 'maker', 'period', 'material', 'gallery', 'story'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the collection: an artist, a material, a period...'),
        )),
        'tree'   => $object_grid(),
    ),
);

// One highlight: the department, the title, the maker, the photo and the
// story, and beside them its facts, the way to its department's other
// works and the opening hours.
$object_sample_key = 'harbour';
$object_sample = $objects[$object_sample_key];
$object_not_found = lang('This work could not be found. It may have gone on loan or been taken off the list.');
$object_widgets['object_detail'] = array(
    'page'   => 'object',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:object_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $object_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-bank2', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Work not found'), 'h3 fw-semibold mt-3'),
                $para($object_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('Collection Highlights'), '{{page:collection}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Object'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Collection Highlights'), '', lang('Link Text')),
                    ), array('href' => '{{page:collection}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'object-fit-contain', 'ratio ratio-1x1 bg-body-tertiary mb-4',
                            $photo('object-' . $object_sample_key, 1200, 1200), lang('Photo')),
                    ), '', '7'),
                    $col(array(
                        $span($object_department($object_sample_key), 'pmz-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('Department'), $bind('department')),
                        $heading('h1', $object_sample['title'], 'pmz-display display-6 mb-2', $bind('title')),
                        $para($object_sample['maker'], 'lead text-body-secondary mb-4', $bind('maker')),
                        $el('div', 'pg-field-value lh-lg mb-4', lang('Story'), array(), array_merge(array('text' => $object_sample['story']), $bind('story'))),
                        $object_facts($object_sample_key, 'list-unstyled small mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            // Opens the list on this work's department
                            // (?department=).
                            $link(lang('More from this department'), '#', 'btn btn-outline-secondary', array(),
                                $mz_page_link('collection', array('department' => 'department'))),
                            $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-primary'),
                        )),
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Visit Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Opening hours'), 'pmz-display h6 mb-3'),
                                $hours_list(),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $object_pages, 'widgets' => $object_widgets);
