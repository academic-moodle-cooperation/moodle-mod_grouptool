<?php
// This file is part of local_checkmarkreport for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the renderer class for mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Grouptools renderer class
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render a sortable list of groups with some additional controls
     *
     * @param \mod_grouptool\sortlist $sortlist Sortlist to render
     * @return string
     */
    public function render_sortlist(sortlist $sortlist) {
        global $PAGE, $OUTPUT;

        if (empty($sortlist->groups) || !is_array($sortlist->groups) || count($sortlist->groups) == 0) {
            return $this->get_no_groups_info($sortlist);
        }

        // Generate draggable items - each representing 1 group!
        $moveupstr = get_string('moveup', 'grouptool');
        $movedownstr = get_string('movedown', 'grouptool');
        $dragstr = get_string('drag', 'grouptool');
        $activestr = get_string('active');
        $inactivestr = get_string('inactive');
        $deletestr = get_string('delete');
        $renamestr = get_string('rename');
        $resizestr = get_string('resize', 'grouptool');
        $table = new \html_table();
        $table->data = array();
        $table->attributes['class'] .= ' table table-striped ';
        $table->head = array( 0 => '',
                              1 => '',
                              2 => get_string('name'));
        if (!empty($sortlist->usesize)) {
            $table->head[3] = get_string('size');
        }
        $groupstatus = get_string('groupstatus', 'grouptool').$OUTPUT->help_icon('groupstatus', 'grouptool');
        $table->head = $table->head + array(4 => $groupstatus,
                                            5 => '',
                                            6 => '',
                                            7 => '' );
        foreach ($sortlist->groups as $id => $group) {
            $row = array(); // Each group gets its own row!

            $classes = array('checkbox_status', 'class0');
            if (!empty($group->groupings) && (count($group->groupings) > 0)) {
                foreach (array_keys($group->groupings) as $groupingid) {
                    $classes[] .= 'class'.$groupingid;
                }
            }

            $chkboxattr = array(
                    'name'  => 'selected[]',
                    'type'  => 'checkbox',
                    'class' => implode(' ', $classes),
                    'value' => $id);
            if (!empty($sortlist->selected[$id])) {
                $chkboxattr['checked'] = 'checked';
            } else if (isset($chkboxattr['checked'])) {
                unset($chkboxattr['checked']);
            }

            $hiddenattr = array(
                    'name'  => 'order['.$id.']',
                    'type'  => 'hidden',
                    'value' => (!empty($group->order) ? $group->order : 999999),
                    'class' => 'sort_order');

            $moveupattr = array('src'   => $OUTPUT->pix_url('i/up'),
                                'alt'   => $moveupstr,
                                'title' => $moveupstr,
                                'name'  => 'moveup['.$id.']',
                                'class' => 'moveupbutton');
            $moveupurl = new \moodle_url($PAGE->url, array('moveup' => $id));
            $moveupbutton = \html_writer::link($moveupurl, \html_writer::empty_tag('img', $moveupattr), $moveupattr);
            $movedownattr = array('src'   => $OUTPUT->pix_url('i/down'),
                                  'alt'   => $movedownstr,
                                  'title' => $movedownstr,
                                  'name'  => 'movedown['.$id.']',
                                  'class' => 'movedownbutton');
            $movedownurl = new \moodle_url($PAGE->url, array('movedown' => $id));
            $movedownbutton = \html_writer::link($movedownurl, \html_writer::empty_tag('img', $movedownattr), $movedownattr);
            $dragbutton = \html_writer::empty_tag('img', array('src'   => $OUTPUT->pix_url('i/dragdrop'),
                                                               'alt'   => $dragstr,
                                                               'title' => $dragstr,
                                                               'class' => 'drag_image js_invisible'));
            $nameattr = array('name'  => 'name['.$id.']',
                              'type'  => 'hidden',
                              'value' => $group->name);
            $nameblock = \html_writer::tag('span', $group->name, array('class' => 'text')).
                         \html_writer::empty_tag('input', $nameattr);
            $renameattr = array('src'   => $OUTPUT->pix_url('t/editstring'),
                                'alt'   => $renamestr,
                                'title' => $renamestr,
                                'type'  => 'image',
                                'name'  => 'rename['.$id.']',
                                'class' => 'renamebutton');
            $renamebutton = \html_writer::link(new \moodle_url($PAGE->url, array('rename' => $id)),
                                              \html_writer::empty_tag('img', $renameattr),
                                              array('class' => $renameattr['class'], 'title' => $renamestr));
            $nameblock .= $renamebutton;

            $drag = new \html_table_cell($dragbutton);
            $drag->attributes['class'] = 'buttons';

            $deleteattr = array('src'   => $OUTPUT->pix_url('t/delete'),
                                'alt'   => $deletestr,
                                'title' => $deletestr,
                                'name'  => 'delete['.$id.']',
                                'class' => 'deletebutton',
                                'id'    => 'delete_'.$id);
            $deletebutton = \html_writer::link(new \moodle_url($PAGE->url, array('delete' => $id)),
                                              \html_writer::empty_tag('img', $deleteattr),
                                              array('class' => $deleteattr['class'], 'title' => $deletestr));

            $row = array( 0 => new \html_table_cell(\html_writer::empty_tag('input', $chkboxattr)),
                          1 => $drag,
                          2 => new \html_table_cell($nameblock.
                                                   \html_writer::empty_tag('input', $hiddenattr)));
            $row[0]->attributes['class'] = 'checkbox_container';
            $row[2]->attributes['class'] = 'grpname';

            if (!empty($sortlist->usesize)) {
                $sizeattr = array('name'  => 'size['.$id.']',
                                  'type'  => 'hidden',
                                  'value' => clean_param($group->size, PARAM_INT));
                $sizeblock = \html_writer::tag('span', $group->size, array('class' => 'text')).
                             \html_writer::empty_tag('input', $sizeattr);
                $resizeattr = array('src'   => $OUTPUT->pix_url('t/editstring'),
                                    'id'    => 'resize_'.$id,
                                    'alt'   => $resizestr,
                                    'title' => $resizestr,
                                    'type'  => 'image',
                                    'name'  => 'resize['.$id.']',
                                    'class' => 'resizebutton');
                $resizebutton = \html_writer::link(new \moodle_url($PAGE->url, array('resize' => $id)),
                                                  \html_writer::empty_tag('img', $resizeattr),
                                                  array('class' => $resizeattr['class'], 'title' => $resizestr));
                $sizeblock .= $resizebutton;

                $fieldcell = new \html_table_cell($sizeblock);
                $fieldcell->attributes['class'] = "size addfield";
                $row[] = $fieldcell;
            }

            if ($group->status) {
                $toggleattr = array('src'   => $OUTPUT->pix_url('active', 'mod_grouptool'),
                                    'alt'   => $activestr,
                                    'title' => $activestr,
                                    'name'  => 'toggle['.$id.']',
                                    'class' => 'active');
            } else {
                $toggleattr = array('src'   => $OUTPUT->pix_url('inactive', 'mod_grouptool'),
                                    'alt'   => $inactivestr,
                                    'title' => $inactivestr,
                                    'type'  => 'image',
                                    'name'  => 'toggle['.$id.']',
                                    'class' => 'inactive');
            }
            $linkattr = array('class' => 'togglebutton '.$toggleattr['class'],
                              'title' => $toggleattr['title']);
            $togglebutton = \html_writer::link(new \moodle_url($PAGE->url, array('toggle' => $id)),
                                              \html_writer::empty_tag('img', $toggleattr), $linkattr);
            unset($linkattr);
            $toggle = new \html_table_cell($togglebutton);
            $toggle->attributes['class'] = 'buttons';
            $row[] = $toggle;
            $moveup = new \html_table_cell($moveupbutton);
            $moveup->attributes['class'] = 'buttons';
            $row[] = $moveup;
            $movedown = new \html_table_cell($movedownbutton);
            $movedown->attributes['class'] = 'buttons';
            $row[] = $movedown;
            $delete = new \html_table_cell($deletebutton);
            $delete->attributes['class'] = 'buttons';
            $row[] = $delete;

            $row = new \html_table_row($row);
            $row->attributes['class'] = 'draggable_item';
            if (!$group->status) {
                $row->attributes['class'] .= ' dimmed_text';
            }

            if ($group->status == null) {
                $row->attributes['class'] .= ' missing_agrp';
            }

            $rows[] = $row;
        }

        $table->data = $rows;
        $table->attributes['class'] .= ' drag_list ';

        $controller = \html_writer::link(new \moodle_url($PAGE->url, array('class_action' => 'select',
                                                                           'do_class_action' => '1')),
                                        get_string('all'),
                                        array('class' => 'simple_select_all')).
                      '/'.
                      \html_writer::link(new \moodle_url($PAGE->url, array('class_action' => 'deselect',
                                                                           'do_class_action' => '1')),
                                        get_string('none'),
                                        array('class' => 'simple_select_none'));
        if ($sortlist->usesize) {
            $settingsurl = new \moodle_url('/course/modedit.php', array('update' => $sortlist->cm->id, 'return' => 1));
            $linkarg = array('class' => 'text-info text-right pull-right');
            $controller = \html_writer::tag('span', $controller, array('class' => 'text-left')).
                          \html_writer::link($settingsurl, get_string('individual_size_info', 'grouptool'), $linkarg);
            unset($linkarg);
        }

        $controller = \html_writer::tag('div', $controller);

        $tablehtml = $controller.\html_writer::table($table).$controller;

        if (count($sortlist->groups)) {
            $content = \html_writer::tag('div', $tablehtml, array('class' => 'drag_area'));
        } else {
            $content = $this->get_no_groups_info($sortlist);
        }

        $html = \html_writer::tag('div', $content, array('class' => 'fitem sortlist_container'));

        // Init JS!
        $context = \context_module::instance($sortlist->cm->id);

        $params = new \stdClass();
        $params->lang = current_language();
        $params->contextid  = $context->id;
        $PAGE->requires->js_call_amd('mod_grouptool/sortlist', 'initializer', array($params));

        return $html;
    }

    /**
     * Get message stating no groups are to be displayed
     *
     * @param \mod_grouptool\sortlist $sortlist Sortlist to render message for
     * @return string HTML snippet
     */
    protected function get_no_groups_info(sortlist $sortlist) {
        global $PAGE, $OUTPUT;

        switch ($sortlist->filter) {
            case \mod_grouptool::FILTER_ACTIVE:
                $url = new \moodle_url($PAGE->url, array('filter' => \mod_grouptool::FILTER_ALL));
                $message = get_string('nogroupsactive', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_INACTIVE:
                $url = new \moodle_url($PAGE->url, array('filter' => \mod_grouptool::FILTER_ALL));
                $message = get_string('nogroupsinactive', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_ALL:
                $url = new \moodle_url($PAGE->url, array('tab' => 'group_creation'));
                $message = get_string('nogroups', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupscreate', 'grouptool'));
                break;
            default:
                $message = var_dump($sortlist->filter);
        }
        return $OUTPUT->box($OUTPUT->notification($message, 'info'), 'generalbox', 'nogroupsinfo');
    }

    /**
     * Render a controller for the sortable list of groups
     *
     * @param \mod_grouptool\sortlist_controller $controller Sortlist controller to render
     * @return    string
     */
    protected function render_sortlist_controller(sortlist_controller $controller) {
        global $OUTPUT;

        $sortlist = $controller->sortlist;

        // Generate groupings-controls to select/deselect groupings!
        $checkboxcontroltitle = \html_writer::tag('label', get_string('checkbox_control_header', 'grouptool'),
                                                 array('for' => 'classes'));
        $helptext = $OUTPUT->render(new \help_icon('checkbox_control_header', 'grouptool'));
        $checkboxcontroltitle = \html_writer::tag('div', $checkboxcontroltitle.' '.$helptext,
                                                   array('class' => 'fitemtitle checkbox_controls_header'));

        $selectall = \html_writer::tag('span', get_string('select', 'grouptool'));
        $selectnone = \html_writer::tag('span', get_string('deselect', 'grouptool'));
        $inverseselection = \html_writer::tag('span', get_string('invert', 'grouptool'));

        // Static controlelements for all elements!
        $options = array(\html_writer::tag('option', get_string('all'), array('value' => '0')));

        if (!empty($sortlist->groupings) && is_array($sortlist->groupings)) {
            foreach ($sortlist->groupings as $groupingid => $grouping) {
                /*
                 * We have only non-empty groupings here, it should also work with empty ones but would make no sense.
                 * Maybe we use disabled options for all the empty groupings.
                 */
                $options[] = \html_writer::tag('option', $grouping, array('value' => $groupingid));
            }
        }

        $checkboxcontrols = $checkboxcontroltitle;

        // Add Radiobuttons and Go Button!
        $checkalllink = \html_writer::tag('span', \html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                                         'type'  => 'radio',
                                                                                         'id'    => 'select',
                                                                                         'value' => 'select',
                                                                                         'class' => 'select_all')).
                                                  \html_writer::tag('label', strip_tags($selectall), array('for' => 'select')),
                                                                    array('class' => 'nowrap'));
        $checknonelink = \html_writer::tag('span', \html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                                          'type'  => 'radio',
                                                                                          'id'    => 'deselect',
                                                                                          'value' => 'deselect',
                                                                                          'class' => 'select_none')).
                                                   \html_writer::tag('label', strip_tags($selectnone), array('for' => 'deselect')),
                                                                     array('class' => 'nowrap'));
        $checktogglelink = \html_writer::tag('span', \html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                                            'type'  => 'radio',
                                                                                            'id'    => 'toggle',
                                                                                            'value' => 'toggle',
                                                                                            'class' => 'toggle_selection')).
                                                     \html_writer::tag('label', strip_tags($inverseselection),
                                                                       array('for' => 'toggle')), array('class' => 'nowrap'));
        $submitbutton = \html_writer::tag('button', get_string('go'), array('name' => 'do_class_action',
                                                                            'value' => 'Go'));

        $attr = array('class' => 'felement');
        $selattr = array('name' => 'classes[]', 'multiple' => 'multiple');
        $checkboxcontrols .= \html_writer::tag('div', \html_writer::tag('select', implode("\n", $options), $selattr).$checkalllink.
                                                      $checknonelink.$checktogglelink.$submitbutton, $attr);
        return $checkboxcontrols;
    }
}