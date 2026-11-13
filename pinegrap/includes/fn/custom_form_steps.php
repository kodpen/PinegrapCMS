<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the steps of a designed form (pg_cf_*_step*).
//
// A "Step" break (a node bound to the form_step section) in a custom_form
// widget splits the controls after it into a <fieldset> of their own, shown
// one at a time with Back, Next and a progress line. It stays one <form> and
// one post: the server sees exactly what an unstepped form sends. A form
// without a break is not touched.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The sessionStorage key of a stepped form's browser draft: the form's page
 * id and the record kind ('form', or 'item' for a collection), '' when there
 * is no page. An unknown kind counts as 'form'.
 */
function pg_cf_draft_key($page_id, $kind = 'form')
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return '';
    if (!in_array($kind, array('form', 'item'), true)) $kind = 'form';
    return 'pg-cf-draft:' . $kind . ':' . $page_id;
}

// Is $node a step break (the palette's "Step", bound to section form_step)?
function pg_cf_is_step_break($node)
{
    return is_array($node)
        && isset($node['props']['_bindings']['section'])
        && $node['props']['_bindings']['section'] === 'form_step';
}

/**
 * A children list split at its step breaks, or null when it holds none.
 *
 * Returns array('head' => [...], 'steps' => [['title', 'class', 'children'],
 * ...]): `head` is what stands before the first break (an introduction, the
 * messages) and shows above every step; each break opens a step titled by
 * its text, its classes kept for the legend. A step with nothing in it (two
 * breaks in a row, a break at the end) is dropped.
 *
 * @param array $children
 * @return array|null
 */
function pg_cf_step_groups($children)
{
    if (!is_array($children)) return null;
    $has = false;
    foreach ($children as $c) {
        if (pg_cf_is_step_break($c)) {
            $has = true;
            break;
        }
    }
    if (!$has) return null;

    $head  = array();
    $steps = array();
    $cur   = null;
    foreach ($children as $c) {
        if (pg_cf_is_step_break($c)) {
            if ($cur !== null && !empty($cur['children'])) $steps[] = $cur;
            $cur = array(
                'title'    => trim(isset($c['props']['text']) && is_scalar($c['props']['text']) ? (string)$c['props']['text'] : ''),
                'class'    => trim(isset($c['props']['cssClass']) && is_scalar($c['props']['cssClass']) ? (string)$c['props']['cssClass'] : ''),
                'children' => array(),
            );
            continue;
        }
        if ($cur === null) $head[] = $c;
        else $cur['children'][] = $c;
    }
    if ($cur !== null && !empty($cur['children'])) $steps[] = $cur;

    return array('head' => $head, 'steps' => $steps);
}

/**
 * Groups the first container of $tree that holds step breaks into step
 * fieldsets, with the progress line before them and Back / Next in each.
 * Returns the number of steps (0: no break, the tree is left as it was).
 * With $draft_key (pg_cf_draft_key()) the answers are kept in the browser
 * through a reload and a "Start over" button clears them; without it (the
 * editor's Preview) nothing is kept.
 *
 * The progress line and the buttons are drawn hidden: the script shows them
 * and hides every step but the current one, so a browser without script
 * sees the whole form and can still send it. Breaks in a second container
 * are dropped, never shown as stray headings.
 */
function pg_cf_apply_steps(&$tree, $draft_key = '')
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return 0;

    $groups = pg_cf_step_groups($tree['children']);
    if ($groups === null) {
        foreach ($tree['children'] as $i => $child) {
            $n = pg_cf_apply_steps($tree['children'][$i], $draft_key);
            if ($n > 0) {
                // Only the first container is stepped.
                for ($j = $i + 1; $j < count($tree['children']); $j++) _pg_cf_drop_step_breaks($tree['children'][$j]);
                return $n;
            }
        }
        return 0;
    }

    foreach ($groups['steps'] as $k => $s) {
        foreach ($s['children'] as $m => $c) _pg_cf_drop_step_breaks($groups['steps'][$k]['children'][$m]);
    }

    $total = count($groups['steps']);
    $kids  = $groups['head'];
    if ($total === 0) { $tree['children'] = $kids; return 0; }

    if ($total > 1) {
        $kids[] = array('type' => 'content', 'children' => array(), 'props' => array(
            'contentType' => 'custom_html', 'html' => _pg_cf_steps_progress_html($groups['steps'], $draft_key)));
    }
    foreach ($groups['steps'] as $i => $s) {
        $inner = array();
        if ($s['title'] !== '') {
            $inner[] = array('type' => 'semantic', 'children' => array(), 'props' => array(
                'tag' => 'legend', 'cssClass' => ($s['class'] !== '' ? $s['class'] : 'h5'), 'text' => $s['title']));
        }
        $inner = array_merge($inner, $s['children']);
        if ($total > 1) {
            $inner[] = array('type' => 'content', 'children' => array(), 'props' => array(
                'contentType' => 'custom_html', 'html' => _pg_cf_steps_nav_html($i, $total)));
        }
        $kids[] = array('type' => 'semantic', 'children' => $inner, 'props' => array(
            'tag' => 'fieldset', 'cssClass' => 'pg-cf-step mb-3',
            '_attrs' => array(array('name' => 'data-pg-step', 'value' => (string)($i + 1)))));
    }
    $tree['children'] = $kids;
    return $total;
}

// Removes every step break below $node (a break inside a step's own
// container, or in a container after the stepped one).
function _pg_cf_drop_step_breaks(&$node)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) return;
    $kept = array();
    foreach ($node['children'] as $c) {
        if (pg_cf_is_step_break($c)) continue;
        _pg_cf_drop_step_breaks($c);
        $kept[] = $c;
    }
    $node['children'] = $kept;
}

// The progress line: the step titles as a list with aria-current on the
// shown one, and "Step 1 of 4" read out on every change.
function _pg_cf_steps_progress_html($steps, $draft_key = '')
{
    $total = count($steps);
    $count = lang(array('string' => 'Step {var:1} of {var:2}', 'vars' => array('%1', '%2')));
    $html  = '<nav class="pg-cf-progress mb-3" data-pg-step-progress hidden aria-label="' . h(lang('Form steps')) . '"'
           . ($draft_key !== '' ? ' data-pg-draft="' . h($draft_key) . '"' : '') . '>'
           . '<p class="small text-body-secondary mb-1" data-pg-step-count data-pg-step-text="' . h($count) . '" aria-live="polite">'
           . h(str_replace(array('%1', '%2'), array('1', (string)$total), $count)) . '</p>'
           . '<div class="progress mb-2" role="presentation"><div class="progress-bar" data-pg-step-bar style="width: '
           . round(100 / $total, 2) . '%"></div></div>'
           . '<ol class="list-inline small mb-0">';
    foreach ($steps as $i => $s) {
        $title = ($s['title'] !== '') ? $s['title'] : str_replace(array('%1', '%2'), array((string)($i + 1), (string)$total), $count);
        $html .= '<li class="list-inline-item' . ($i === 0 ? ' fw-semibold' : ' text-body-secondary') . '"'
               . ($i === 0 ? ' aria-current="step"' : '') . '>' . h($title) . '</li>';
    }
    $html .= '</ol>';
    // Shown by the script while a draft is kept.
    if ($draft_key !== '') {
        $html .= '<button type="button" class="btn btn-link btn-sm px-0 mt-1" data-pg-step-restart hidden>' . h(lang('Start over')) . '</button>';
    }
    return $html . '</nav>';
}

// Back and Next under a step; the last step has no Next, its own submit
// button sends the form.
function _pg_cf_steps_nav_html($index, $total)
{
    $html = '<div class="pg-cf-step-nav d-flex gap-2 mt-3" data-pg-step-nav hidden>';
    if ($index > 0) {
        $html .= '<button type="button" class="btn btn-outline-secondary" data-pg-step-back>' . h(lang('Back')) . '</button>';
    }
    if ($index < $total - 1) {
        $html .= '<button type="button" class="btn btn-primary ms-auto" data-pg-step-next>' . h(lang('Next')) . '</button>';
    }
    return $html . '</div>';
}

/**
 * The script of a stepped form, emitted once per page.
 *
 * At load it shows the progress and the buttons and hides every step but the
 * first, or the one holding a control the server refused (aria-invalid).
 * Next checks the shown step's own controls (a disabled one, hidden by a
 * rule, is skipped) and reports its failures in text beside each field (the
 * validation script's form.pgFieldErrors()); Back never checks. The
 * focus moves to the new step's first control. The history is not touched,
 * so the browser's back key leaves the page as it always did. On submit a
 * control that fails in another step brings that step forward first, so the
 * validation script can focus it.
 *
 * Browser draft (the progress line carries data-pg-draft): the answers and
 * the shown step are kept in sessionStorage under that key on every change
 * and restored at load, unless the server refilled a refused submission.
 * Never kept: file, password and hidden inputs (a signature's value, the
 * form's tokens), card fields (autocomplete cc-*), the CAPTCHA box and a
 * signature pad. A submission that goes (not prevented) and "Start over"
 * remove it. Off inside a same-site frame: the editor's canvas and its
 * prototype play.
 */
function pg_cf_steps_script()
{
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    return '<script>(function(){'
         . 'var bind=function(){Array.prototype.forEach.call(document.querySelectorAll(".pg-cf-form"),function(form){'
         . 'var steps=Array.prototype.filter.call(form.querySelectorAll("fieldset[data-pg-step]"),function(s){return s.closest("form")===form;});'
         . 'if(steps.length<2||form.getAttribute("data-pg-stepped"))return;form.setAttribute("data-pg-stepped","1");'
         . 'var prog=form.querySelector("[data-pg-step-progress]"),cur=0;'
         // The draft: key, store (null when sessionStorage is refused) and
         // the controls it may hold.
         . 'var key=prog?prog.getAttribute("data-pg-draft"):"",store=null,restart=prog?prog.querySelector("[data-pg-step-restart]"):null;'
         . 'var framed=false;try{framed=!!window.frameElement;}catch(x){}'
         . 'if(key&&!framed){try{store=window.sessionStorage;store.getItem(key);}catch(x){store=null;}}'
         . 'var keep=function(c){var t=(c.type||"").toLowerCase(),ac=(c.getAttribute("autocomplete")||"").toLowerCase();'
         . 'return !!c.name&&["file","password","hidden","submit","button","reset","image"].indexOf(t)===-1'
         . '&&ac.indexOf("cc-")!==0&&!c.closest(".captcha,[data-pg-signature]");};'
         . 'var save=function(){if(!store)return;var d={step:cur,v:{}};Array.prototype.forEach.call(form.elements,function(c){'
         . 'if(!keep(c))return;var l=d.v[c.name]||(d.v[c.name]=[]);'
         . 'if(c.type==="checkbox"||c.type==="radio"){if(c.checked)l.push(c.value);}'
         . 'else if(c.tagName==="SELECT"){Array.prototype.forEach.call(c.options,function(o){if(o.selected)l.push(o.value);});}'
         . 'else{l.push(c.value);}});'
         . 'try{store.setItem(key,JSON.stringify(d));}catch(x){}if(restart)restart.hidden=false;};'
         . 'var drop=function(){if(!store)return;try{store.removeItem(key);}catch(x){}if(restart)restart.hidden=true;};'
         . 'var load=function(){if(!store)return null;var d=null;try{d=JSON.parse(store.getItem(key)||"null");}catch(x){}'
         . 'if(!d||typeof d.v!=="object")return null;var seen={};'
         . 'Array.prototype.forEach.call(form.elements,function(c){if(!keep(c)||!Object.prototype.hasOwnProperty.call(d.v,c.name))return;'
         . 'var l=d.v[c.name];if(!Array.isArray(l))return;'
         . 'if(c.type==="checkbox"||c.type==="radio"){c.checked=l.indexOf(c.value)!==-1;}'
         . 'else if(c.tagName==="SELECT"){Array.prototype.forEach.call(c.options,function(o){o.selected=l.indexOf(o.value)!==-1;});}'
         . 'else{var n=seen[c.name]||0;seen[c.name]=n+1;if(n<l.length)c.value=l[n];}});'
         . 'if(restart)restart.hidden=false;form.dispatchEvent(new Event("change"));return d;};'
         . 'var usable=function(c){return !c.disabled&&c.type!=="hidden"&&!c.closest("[hidden]:not(fieldset[data-pg-step])");};'
         . 'var show=function(i,focus){cur=i;steps.forEach(function(s,k){s.hidden=(k!==i);});'
         . 'if(prog){var t=prog.querySelector("[data-pg-step-count]");'
         . 'if(t)t.textContent=t.getAttribute("data-pg-step-text").replace("%1",i+1).replace("%2",steps.length);'
         . 'var b=prog.querySelector("[data-pg-step-bar]");if(b)b.style.width=((i+1)*100/steps.length)+"%";'
         . 'Array.prototype.forEach.call(prog.querySelectorAll("li"),function(li,k){'
         . 'if(k===i){li.setAttribute("aria-current","step");}else{li.removeAttribute("aria-current");}'
         . 'li.classList.toggle("fw-semibold",k===i);li.classList.toggle("text-body-secondary",k!==i);});}'
         . 'if(focus){var first=Array.prototype.filter.call(steps[i].elements,usable)[0];'
         . 'if(first&&first.focus){first.focus();}else{var lg=steps[i].querySelector("legend");'
         . 'if(lg){lg.tabIndex=-1;lg.focus();}}}};'
         . 'var index=function(el){for(var k=0;k<steps.length;k++){if(steps[k].contains(el))return k;}return -1;};'
         . 'if(prog)prog.hidden=false;'
         . 'Array.prototype.forEach.call(form.querySelectorAll("[data-pg-step-nav]"),function(n){n.hidden=false;});'
         . 'form.addEventListener("click",function(e){var btn=e.target.closest("[data-pg-step-next],[data-pg-step-back]");'
         . 'if(!btn||!form.contains(btn))return;var k=index(btn);if(k<0)return;'
         . 'if(btn.hasAttribute("data-pg-step-back")){if(k>0){show(k-1,true);save();}return;}'
         . 'var bad=Array.prototype.filter.call(steps[k].elements,function(c){return usable(c)&&c.willValidate&&!c.checkValidity();})[0];'
         . 'steps[k].classList.add("was-validated");'
         // The step's refused fields say why in text, as on submit
         // (pg_cf_validation_script()); the browser's bubble without it.
         . 'if(bad){if(form.pgFieldErrors){form.pgFieldErrors(steps[k]);}else{bad.reportValidity();}if(bad.focus)bad.focus();return;}'
         . 'if(k<steps.length-1){show(k+1,true);save();}});'
         . 'form.addEventListener("submit",function(){var bad=Array.prototype.filter.call(form.elements,function(c){'
         . 'return !c.disabled&&c.willValidate&&!c.checkValidity();})[0];if(bad){var k=index(bad);if(k>=0&&k!==cur)show(k,false);}},true);'
         . 'form.addEventListener("submit",function(e){if(!e.defaultPrevented)drop();});'
         // The change event lets the rule scripts re-read the emptied
         // form; the draft goes after it, or the save listener writes it back.
         . 'if(restart)restart.addEventListener("click",function(){form.reset();'
         . 'steps.forEach(function(s){s.classList.remove("was-validated");});form.classList.remove("was-validated");'
         . 'form.dispatchEvent(new Event("change"));drop();show(0,true);});'
         . 'var refused=form.querySelector("[aria-invalid=true]"),draft=refused?null:load(),start=refused?index(refused):0;'
         . 'if(draft&&typeof draft.step==="number"&&draft.step>=0&&draft.step<steps.length)start=draft.step;'
         . 'show(start<0?0:start,false);'
         . 'form.addEventListener("input",save);form.addEventListener("change",save);'
         . '});};'
         . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bind);}else{bind();}'
         . '})();</script>';
}
