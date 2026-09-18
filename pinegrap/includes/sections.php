<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Section rail
// ─────────────────────────────────────────────────────────────────────────────
//
// The long screens -- settings, the product builder, three dozen add/edit forms
// -- put a list of their sections beside the form. This file is the whole of
// that: the slot the shell drops on the page, and the builder a screen uses
// when it wants to name its own sections instead of letting the browser read
// them off the cards.
//
// It lives here rather than in functions.php because functions.php is loaded by
// all 308 entry points and only a handful of them draw a rail. The shell pulls
// this in from inside the branch that renders the slot, so a screen without a
// rail never parses a line of it.
//
// Two ways in, one rail out:
//
//   'section_nav' => true                     the browser reads the cards
//   'section_nav' => pg_section_nav($map)     the screen says what its sections are
//
// Anything a screen can say about its rail is said through pg_section_nav. The
// slot has no options of its own, so there is one place to look when the rail
// on some screen does not behave.

/**
 * Build the rail for a screen that names its own sections.
 *
 * $sections is either flat --
 *
 *     array('pg_pb_sec_images' => array(lang('Images'), 'bi-images'), ...)
 *
 * -- or grouped, one level deep, when the list is long enough that headings
 * help more than they cost:
 *
 *     array('Security' => array('pgset-waf' => array(lang('Firewall'), 'bi-shield-check'), ...))
 *
 * The keys are the ids the sections already carry on the page. They are the
 * scroll targets, the #hash deep links and what the spy matches against, so a
 * section that is renamed or added is one line here and nothing else.
 *
 * Options:
 *   steps    array   jump points inside a section, id => label. Sub-links, not
 *                    sections: the rail marks the section they belong to.
 *   switch   array   section id => the switch whose name gates its steps, for
 *                    steps that live inside a collapse. Hidden until it is on.
 *   order    bool    emit the reading order as CSS, generated from this same
 *                    map. Only for screens whose sections are laid out by
 *                    order rather than by DOM position.
 *   panes    string  selector for the element the sections live in.
 *   reorder  bool    let the rail move the sections into its own order.
 *   label    string  aria-label for the nav.
 *   icon     string  glyph on the toggle, below the split.
 *
 * @return array ready to hand to pg_page_shell as 'section_nav'.
 */
function pg_section_nav($sections, $options = array())
{
    if (!is_array($sections) || count($sections) === 0) {
        return array();
    }

    if (!is_array($options)) {
        $options = array();
    }

    $steps  = (isset($options['steps'])  && is_array($options['steps']))  ? $options['steps']  : array();
    $switch = (isset($options['switch']) && is_array($options['switch'])) ? $options['switch'] : array();

    // Grouped or flat, told apart by the shape of the first entry rather than
    // by a flag: a group holds sections, a section holds a label and a glyph.
    $first   = reset($sections);
    $grouped = (is_array($first) && count($first) > 0 && is_array(reset($first)));

    // One loop for both. A flat list is a single unnamed group, and an unnamed
    // group draws no heading.
    $groups = $grouped ? $sections : array('' => $sections);

    $output_nav   = '';
    $output_order = '';
    $order        = 0;

    foreach ($groups as $group_label => $group_items) {

        if (!is_array($group_items)) {
            continue;
        }

        if ($group_label !== '') {
            $output_nav .= '<div class="pg-settings-group">' . h(lang($group_label)) . '</div>';
        }

        foreach ($group_items as $section_id => $section) {

            $section_label = is_array($section) ? (isset($section[0]) ? $section[0] : $section_id) : $section;
            $section_icon  = (is_array($section) && isset($section[1])) ? $section[1] : 'bi-dot';

            $order++;
            $output_order .= '#' . $section_id . '{order:' . $order . '}';

            $output_nav .= '
                        <a class="pg-settings-link" href="#' . h($section_id) . '" data-pgset="' . h($section_id) . '">
                            <i class="bi ' . h($section_icon) . '" aria-hidden="true"></i><span>' . h($section_label) . '</span>
                        </a>';

            if (!isset($steps[$section_id]) || !is_array($steps[$section_id])) {
                continue;
            }

            $output_steps = '';

            foreach ($steps[$section_id] as $step_id => $step_label) {
                $output_steps .= '
                            <a class="pg-settings-link pg-settings-sublink" href="#' . h($step_id) . '" data-pgsub="' . h($step_id) . '">' . h($step_label) . '</a>';
            }

            $step_switch = isset($switch[$section_id])
                ? ' data-pgswitch="' . h($switch[$section_id]) . '" hidden'
                : '';

            $output_nav .= '
                        <div class="pg-settings-subs"' . $step_switch . '>' . $output_steps . '
                        </div>';
        }
    }

    // The reading order, written from the same map the rail is built from. It
    // was once a list in the stylesheet and the two drifted the first time a
    // section was added: the new ones defaulted to order 0 and climbed above
    // the first. One source or none.
    //
    // It only has to hold until the rail moves the elements themselves; what it
    // buys is that the page is already in the right order in the first frame.
    if (!empty($options['order'])) {
        $output_nav = '<style>' . $output_order . '</style>' . $output_nav;
    }

    return array(
        'links'   => $output_nav,
        'panes'   => isset($options['panes'])   ? (string) $options['panes'] : '',
        'reorder' => !empty($options['reorder']),
        'label'   => isset($options['label'])   ? (string) $options['label'] : lang('Sections'),
        'icon'    => isset($options['icon'])    ? (string) $options['icon']  : 'bi-list-nested',
    );
}

/**
 * The slot the shell drops on the page.
 *
 * Rendered inside .software-content and moved into <main> by initSectionRail,
 * which also wraps whatever the screen drew in .pg-page-body so the stylesheet
 * can put the two side by side from xl up. Below that the rail is a panel under
 * the header and the screen is laid out exactly as it was.
 *
 * Empty until then: the links either come in already built, or the browser
 * reads them off the cards. Either way the markup around them is this one.
 *
 * @param mixed $section_nav true, a links string, or a pg_section_nav array.
 * @return string
 */
function pg_section_nav_slot($section_nav)
{
    $nav = array(
        'links'   => '',
        'panes'   => '',
        'reorder' => false,
        'label'   => lang('Sections'),
        'icon'    => 'bi-list-nested',
    );

    if (is_array($section_nav)) {
        $nav = array_merge($nav, $section_nav);
    } elseif (is_string($section_nav)) {
        $nav['links'] = $section_nav;
    }

    $attributes = '';

    if ($nav['panes'] !== '') {
        $attributes .= ' data-pgpanes="' . h($nav['panes']) . '"';
    }

    if (!empty($nav['reorder'])) {
        $attributes .= ' data-pgreorder="1"';
    }

    // The button is only ever seen where the rail becomes a panel. It shows the
    // section you are in, not a menu glyph: on a form with twelve sections
    // "where am I" is asked more often than "what else is there", and one
    // control can answer both.
    return '
        <div class="pg-settings-menu" id="pg_section_rail" hidden>
            <button type="button" class="pg-settings-toggle d-xl-none" aria-expanded="false" aria-controls="pg_section_nav">
                <i class="bi pg-settings-toggle-icon ' . h($nav['icon']) . '" aria-hidden="true"></i>
                <span class="pg-settings-toggle-text">' . h(lang('Sections')) . '</span>
                <i class="bi bi-chevron-down pg-settings-toggle-chev" aria-hidden="true"></i>
            </button>
            <nav class="pg-settings-nav" id="pg_section_nav"' . $attributes . ' aria-label="' . h($nav['label']) . '">' . $nav['links'] . '
            </nav>
        </div>';
}
