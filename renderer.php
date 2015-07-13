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
 * A sortable list of course groups including some additional information and fields
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager (office@phager.at)
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Representation of single group with advanced fields!
 *
 * TODO: should we make these renderable with a nice standardised view?
 */
class activegroup {
    public $id;
    public $groupid;
    public $grouptoolid;

    public $name;
    public $size;
    public $order;
    public $groupings;
    public $status;

    public $selected;

    public function __construct($id, $groupid, $grouptoolid, $name, $size, $order, $status, $groupings=array(), $selected=0) {
        $this->id = $id;
        $this->groupid = $groupid;
        $this->grouptoolid = $grouptoolid;
        $this->name = $name;
        $this->size = $size;
        $this->order = $order;
        $this->groupings = $groupings;
        $this->status = $status;
        $this->selected = $selected;
    }

    public static function construct_from_obj($data) {
        return new activegroup($data->id, $data->groupid, $data->grouptoolid, $data->name,
                               $data->size, $data->order, $data->status, $data->groupings,
                               $data->selected);
    }

    public function get_by_groupid($groupid, $grouptoolid) {
        global $DB;

        $sql = "SELECT agrp.id id, agrp.grouptoolid grouptoolid, agrp.groupid groupid,
                       agrp.grpsize size, agrp.sort_order order, agrp.active status,
                       grp.name name, grouptool.use_size use_size, grouptool.grpsize globalsize,
                       grouptool.use_individual individualsize
                  FROM {grouptool_agrps} agrp
             LEFT JOIN {groups} grp ON agrp.groupid = grp.id
             LEFT JOIN {grouptool} grptl ON agrp.grouptoolid = grptl.id
                 WHERE agrp.groupid = ? AND agrp.grouptoolid = ?";

        $obj = $DB->get_record_sql($sql, array($groupid, $grouptoolid));

        if (empty($obj->use_size)) {
            $obj->size = null;
        } else if (empty($obj->individualsize)) {
            $obj->size = $obj->globalsize;
        }

        $obj->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                       FROM {groupings_groups}
                                                  LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                      WHERE groupid = ?", array($groupid));

        return $this->construct_from_obj($obj);
    }

    public function load_groupings() {
        $this->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                        FROM {groupings_groups}
                                                   LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                       WHERE groupid = ?", array($this->groupid));
    }
}

class sortlist implements renderable {

    public $tableclass = 'coloredrows';

    public $groupings = array();

    public $groups = array();

    public $globalsize = 0;

    public $usesize = 0;

    public $useindividual = 0;

    public $filter = null;

    public $cm = null;

    public function __construct($courseid, $cm, $filter=null) {
        global $SESSION, $DB, $OUTPUT;

        $this->filter = $filter;

        if ($moveup = optional_param('moveup', 0, PARAM_INT)) {
            // Move up!
            $a = $DB->get_record('grouptool_agrps', array('groupid' => $moveup,
                                                          'grouptoolid' => $cm->instance));
            $b = $DB->get_record('grouptool_agrps', array('grouptoolid' => $a->grouptoolid,
                                                          'sort_order' => ($a->sort_order - 1)));
            if (empty($a) || empty($b)) {
                echo $OUTPUT->notification(get_string('couldnt_move_up', 'grouptool'), 'notifyproblem');
            } else {
                $DB->set_field('grouptool_agrps', 'sort_order', $a->sort_order, array('id' => $b->id));
                $DB->set_field('grouptool_agrps', 'sort_order', $b->sort_order, array('id' => $a->id));
            }
        }

        if ($movedown = optional_param('movedown', 0, PARAM_INT)) {
            // Move up!
            $a = $DB->get_record('grouptool_agrps', array('groupid' => $movedown,
                                                          'grouptoolid' => $cm->instance));
            $b = $DB->get_record('grouptool_agrps', array('grouptoolid' => $a->grouptoolid,
                                                          'sort_order' => ($a->sort_order + 1)));
            if (empty($a) || empty($b)) {
                echo $OUTPUT->notification(get_string('couldnt_move_down', 'grouptool'), 'notifyproblem');
            } else {
                $DB->set_field('grouptool_agrps', 'sort_order', $a->sort_order, array('id' => $b->id));
                $DB->set_field('grouptool_agrps', 'sort_order', $b->sort_order, array('id' => $a->id));
            }
        }

        if ($courseid != null) {
            $this->loadgroups($courseid, $cm);
            $this->cm = $cm;
            $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance));
            $this->usesize = $grouptool->use_size;
            $this->useindividual = $grouptool->use_individual;
        }

        $this->selected = optional_param_array('selected', null, PARAM_BOOL);
        if (!isset($SESSION->sortlist)) {
            $SESSION->sortlist = new stdClass();
        }
        if (!isset($SESSION->sortlist->selected)) {
            $SESSION->sortlist->selected = array();
        }

        if ($this->selected == null) {
            $this->selected = $SESSION->sortlist->selected;
        } else {
            $SESSION->sortlist->selected = $this->selected;
        }
    }

    public function loadgroups($courseid, $cm) {
        global $DB;

        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance));
        // Prepare agrp-data!
        $coursegroups = groups_get_all_groups($courseid, null, null, "id");
        if (is_array($coursegroups) && !empty($coursegroups)) {
            $groups = array();
            foreach ($coursegroups as $group) {
                $groups[] = $group->id;
            }
            list($grpssql, $params) = $DB->get_in_or_equal($groups);

            if ($this->filter == mod_grouptool::FILTER_ACTIVE) {
                $activefilter = ' AND active = 1 ';
            } else if ($this->filter == mod_grouptool::FILTER_INACTIVE) {
                $activefilter = ' AND active = 0 ';
            } else {
                $activefilter = '';
            }
            if (!is_object($cm)) {
                $cm = get_coursemodule_from_id('grouptool', $cm);
            }
            $params = array_merge(array($cm->instance), $params);
            $groupdata = (array)$DB->get_records_sql("
                    SELECT MAX(grp.id) groupid, MAX(agrp.id) id,
                           MAX(agrp.grouptoolid) grouptoolid,  MAX(grp.name) name,
                           MAX(agrp.grpsize) size, MAX(agrp.sort_order) 'order',
                           MAX(agrp.active) status
                    FROM {groups} grp
                    LEFT JOIN {grouptool_agrps} agrp
                         ON agrp.groupid = grp.id AND agrp.grouptoolid = ?
                    WHERE grp.id ".$grpssql.$activefilter."
                    GROUP BY grp.id
                    ORDER BY active DESC, sort_order ASC, name ASC", $params);

            // Convert to multidimensional array and add groupings.
            $runningidx = 1;
            foreach ($groupdata as $key => $group) {
                $groupdata[$key] = $group;
                $groupdata[$key]->selected = 0;
                $groupdata[$key]->sort_order = $runningidx;
                $runningidx++;
                $groupdata[$key]->groupings = $DB->get_records_sql_menu("
                                                    SELECT DISTINCT groupingid, name
                                                      FROM {groupings_groups}
                                                 LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                     WHERE {groupings}.courseid = ? AND {groupings_groups}.groupid = ?",
                                                                        array($courseid, $group->groupid));
            }
        }

        if (!empty($groupdata) && is_array($groupdata)) {
            $this->globalgrpsize = $grouptool->grpsize ?
                                   $grouptool->grpsize :
                                   get_config('mod_grouptool', 'grpsize');

            foreach ($groupdata as $key => $group) {
                if ($grouptool->use_size && (!$grouptool->use_individual || ($groupdata[$key]->size == null))) {
                    $groupdata[$key]->size = $this->globalgrpsize.'*';
                }

                // Convert to activegroup object!
                $groupdata[$key] = activegroup::construct_from_obj($group);
            }

            $this->groups = $groupdata;

            // Add groupings...
            $this->groupings = $DB->get_records_sql_menu("
                    SELECT DISTINCT groupingid, name
                      FROM {groupings_groups}
                 LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                     WHERE {groupings}.courseid = ?
                  ORDER BY name ASC", array($courseid));
        }
    }

    /**
     * compares if two groups are in correct order
     */
    public function cmp($element1, $element2) {
        if ($element1->order == $element2->order) {
            return 0;
        } else {
            return $element1->order > $element2->order ? 1 : -1;
        }
    }

    /**
     * updates the element selected-state if corresponding params are set
     */
    public function _refresh_select_state() {
        global $COURSE;
        $classes = optional_param_array('groupings', array(0), PARAM_INT);
        $action = optional_param('class_action', 0, PARAM_ALPHA);
        $gobutton = optional_param('do_class_action', 0, PARAM_BOOL);

        if (empty($gobutton)) {
            return;
        }

        if ( $groupings == null || count($groupings) == 0 ) {
            return;
        }

        if (!empty($action)) {
            $keys = array();

            $groups = array();
            foreach ($groupings as $groupingid) {
                $groups = array_merge($groups, groups_get_all_groups($COURSE->id, 0, $groupingid));
            }

            foreach ($groups as $current) {
                switch ($action) {
                    case 'select':
                        $sortlist->groups[$current->id]['selected'] = 1;
                        break;
                    case 'deselect':
                        $sortlist->groups[$current->id]['selected'] = 0;
                        break;
                    case 'toggle':
                        $next = !$sortlist->groups[$current->id]['selected'];
                        $sortlist->groups[$current->id]['selected'] = $next;
                        break;
                }
            }
        }
    }
}

class sortlist_controller implements renderable {

    public $sortlist = null;

    public function __construct(sortlist &$sortlist) {
        global $SESSION;
        $this->sortlist = $sortlist;

        $classes = optional_param_array('classes', array(0), PARAM_INT);
        $action = optional_param('class_action', 0, PARAM_ALPHA);
        $gobutton = optional_param('do_class_action', 0, PARAM_BOOL);

        if (!empty($gobutton) && ($classes != null)
            && (count($classes) != 0) && !empty($action)) {
            $keys = array();

            $groups = array();
            foreach ($classes as $groupingid) {
                $groups = array_merge($groups, groups_get_all_groups($this->sortlist->cm->course, 0, $groupingid));
            }

            foreach ($groups as $current) {
                switch ($action) {
                    case 'select':
                        $this->sortlist->selected[$current->id] = 1;
                        break;
                    case 'deselect':
                        $this->sortlist->selected[$current->id] = 0;
                        break;
                    case 'toggle':
                        $next = empty($this->sortlist->selected[$current->id]) ? 1 : 0;
                        $this->sortlist->selected[$current->id] = $next;
                        break;
                }
            }

            // Update SESSION!
            $SESSION->sortlist->selected = $this->sortlist->selected;
        }
    }
}

class mod_grouptool_renderer extends plugin_renderer_base {

    /**
     * Returns the input field in HTML
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function render_sortlist(sortlist $sortlist) {
        global $CFG, $PAGE, $OUTPUT;

        if (empty($sortlist->groups) || !is_array($sortlist->groups) || count($sortlist->groups) == 0) {
            return $OUTPUT->box($OUTPUT->notification(get_string('sortlist_no_data', 'grouptool'),
                                                      'notifymessage'),
                                'generalbox');
        }

        // Generate draggable items - each representing 1 group!
        $dragableitems = "";
        $showmembersstr = get_string('show_members', 'grouptool');
        $moveupstr = get_string('moveup', 'grouptool');
        $movedownstr = get_string('movedown', 'grouptool');
        $dragstr = get_string('drag', 'grouptool');
        $activestr = get_string('active');
        $inactivestr = get_string('inactive');
        $deletestr = get_string('delete');
        $renamestr = get_string('rename');
        $resizestr = get_string('resize', 'grouptool');
        $groupsizestr = get_string('size', 'grouptool');
        reset($sortlist->groups);
        $firstkey = key($sortlist->groups);
        end($sortlist->groups);
        $lastkey = key($sortlist->groups);
        reset($sortlist->groups);
        $table = new html_table();
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
                foreach ($group->groupings as $groupingid => $grouping) {
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

            $showmemberslink = html_writer::tag('a', $showmembersstr,
                                                array('href' => 'somewhere',
                                                      'title' => $showmembersstr));
            $moveupattr = array('src'   => $OUTPUT->pix_url('i/up'),
                                'alt'   => $moveupstr,
                                'title' => $moveupstr,
                                'name'  => 'moveup['.$id.']',
                                'class' => 'moveupbutton');
            $moveupurl = new moodle_url($PAGE->url, array('moveup' => $id));
            $moveupbutton = html_writer::link($moveupurl, html_writer::empty_tag('img', $moveupattr), $moveupattr);
            $movedownattr = array('src'   => $OUTPUT->pix_url('i/down'),
                                  'alt'   => $movedownstr,
                                  'title' => $movedownstr,
                                  'name'  => 'movedown['.$id.']',
                                  'class' => 'movedownbutton');
            $movedownurl = new moodle_url($PAGE->url, array('movedown' => $id));
            $movedownbutton = html_writer::link($movedownurl, html_writer::empty_tag('img', $movedownattr), $movedownattr);
            $dragbutton = html_writer::empty_tag('img',
                                                 array('src'   => $OUTPUT->pix_url('i/dragdrop'),
                                                       'alt'   => $dragstr,
                                                       'title' => $dragstr,
                                                       'class' => 'drag_image js_invisible'));
            $nameattr = array('name'  => 'name['.$id.']',
                              'type'  => 'hidden',
                              'value' => $group->name);
            $nameblock = html_writer::tag('span', $group->name, array('class' => 'text')).
                         html_writer::empty_tag('input', $nameattr);
            $renameattr = array('src'   => $OUTPUT->pix_url('t/editstring'),
                                'alt'   => $renamestr,
                                'title' => $renamestr,
                                'type'  => 'image',
                                'name'  => 'rename['.$id.']',
                                'class' => 'renamebutton');
            $renamebutton = html_writer::link(new moodle_url($PAGE->url, array('rename' => $id)),
                                              html_writer::empty_tag('img', $renameattr),
                                              array('class' => $renameattr['class'], 'title' => $renamestr));
            $nameblock .= $renamebutton;

            $drag = new html_table_cell($dragbutton);
            $drag->attributes['class'] = 'buttons';

            $deleteattr = array('src'   => $OUTPUT->pix_url('t/delete'),
                                'alt'   => $deletestr,
                                'title' => $deletestr,
                                'name'  => 'delete['.$id.']',
                                'class' => 'deletebutton',
                                'id'    => 'delete_'.$id);
            $deletebutton = html_writer::link(new moodle_url($PAGE->url, array('delete' => $id)),
                                              html_writer::empty_tag('img', $deleteattr),
                                              array('class' => $deleteattr['class'], 'title' => $deletestr));

            $row = array( 0 => new html_table_cell(html_writer::empty_tag('input', $chkboxattr)),
                          1 => $drag,
                          2 => new html_table_cell($nameblock.
                                                   html_writer::empty_tag('input', $hiddenattr)));
            $row[0]->attributes['class'] = 'checkbox_container';
            $row[2]->attributes['class'] = 'grpname';

            if (!empty($sortlist->usesize)) {
                $sizeattr = array('name'  => 'size['.$id.']',
                                  'type'  => 'hidden',
                                  'value' => clean_param($group->size, PARAM_INT));
                $sizeblock = html_writer::tag('span', $group->size, array('class' => 'text')).
                             html_writer::empty_tag('input', $sizeattr);
                $resizeattr = array('src'   => $OUTPUT->pix_url('t/editstring'),
                                    'id'    => 'resize_'.$id,
                                    'alt'   => $resizestr,
                                    'title' => $resizestr,
                                    'type'  => 'image',
                                    'name'  => 'resize['.$id.']',
                                    'class' => 'resizebutton');
                $resizebutton = html_writer::link(new moodle_url($PAGE->url, array('resize' => $id)),
                                                  html_writer::empty_tag('img', $resizeattr),
                                                  array('class' => $resizeattr['class'], 'title' => $resizestr));
                $sizeblock .= $resizebutton;

                $fieldcell = new html_table_cell($sizeblock);
                $fieldcell->attributes['class'] = "size addfield";
                $row[] = $fieldcell;
            }

            if ($group->status) {
                $toggleattr = array('src'   => $OUTPUT->pix_url('t/go'),
                                    'alt'   => $activestr,
                                    'title' => $activestr,
                                    'name'  => 'toggle['.$id.']',
                                    'class' => 'active');
            } else {
                $toggleattr = array('src'   => $OUTPUT->pix_url('t/stop'),
                                    'alt'   => $inactivestr,
                                    'title' => $inactivestr,
                                    'type'  => 'image',
                                    'name'  => 'toggle['.$id.']',
                                    'class' => 'inactive');
            }
            $togglebutton = html_writer::link(new moodle_url($PAGE->url, array('toggle' => $id)),
                                              html_writer::empty_tag('img', $toggleattr),
                                              array('class' => 'togglebutton '.$toggleattr['class'],
                                                    'title' => $toggleattr['title']));
            $toggle = new html_table_cell($togglebutton);
            $toggle->attributes['class'] = 'buttons';
            $row[] = $toggle;
            $moveup = new html_table_cell($moveupbutton);
            $moveup->attributes['class'] = 'buttons';
            $row[] = $moveup;
            $movedown = new html_table_cell($movedownbutton);
            $movedown->attributes['class'] = 'buttons';
            $row[] = $movedown;
            $delete = new html_table_cell($deletebutton);
            $delete->attributes['class'] = 'buttons';
            $row[] = $delete;

            $row = new html_table_row($row);
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

        $controller = html_writer::link(new moodle_url($PAGE->url,
                                                       array('class_action' => 'select',
                                                             'do_class_action' => '1')),
                                        get_string('all'),
                                        array('class' => 'simple_select_all')).
                      '/'.
                      html_writer::link(new moodle_url($PAGE->url,
                                                       array('class_action' => 'deselect',
                                                             'do_class_action' => '1')),
                                        get_string('none'),
                                        array('class' => 'simple_select_none'));
        if ($sortlist->usesize) {
            $controller = html_writer::tag('span', $controller, array('class' => 'text-left')).
                          html_writer::tag('span', get_string('individual_size_info', 'grouptool'),
                                           array('class' => 'text-info text-right pull-right'));
        }

        $controller = html_writer::tag('div', $controller);

        $tablehtml = $controller.html_writer::table($table).$controller;

        $content = html_writer::tag('div', $tablehtml, array('class' => 'drag_area'));

        $html = html_writer::tag('div', $content, array('class' => 'fitem sortlist_container'));

        // Init JS!
        $context = context_module::instance($sortlist->cm->id);
        $PAGE->requires->yui_module('moodle-mod_grouptool-sortlist',
                                    'M.mod_grouptool.init_sortlist',
                                    array(array('lang'      => current_language(),
                                                'contextid' => $context->id)));

        return $html;
    }

    protected function render_sortlist_controller(sortlist_controller $controller) {
        global $OUTPUT, $DB;

        $sortlist = $controller->sortlist;

        // Generate groupings-controls to select/deselect groupings!
        $checkboxcontroltitle = html_writer::tag('label', get_string('checkbox_control_header', 'grouptool'),
                                                 array('for' => 'classes'));
        $helptext = $OUTPUT->render(new help_icon('checkbox_control_header', 'grouptool'));
        $checkboxcontroltitle = html_writer::tag('div', $checkboxcontroltitle.' '.$helptext,
                                                   array('class' => 'fitemtitle checkbox_controls_header'));

        $selectall = html_writer::tag('span', get_string('select', 'grouptool'));
        $selectnone = html_writer::tag('span', get_string('deselect', 'grouptool'));
        $inverseselection = html_writer::tag('span', get_string('invert', 'grouptool'));
        $checkboxcontrolelements = array();

        // Static controlelements for all elements!
        $options = array(html_writer::tag('option', get_string('all'), array('value' => '0')));

        if (!empty($sortlist->groupings) && is_array($sortlist->groupings)) {
            foreach ($sortlist->groupings as $groupingid => $grouping) {
                if ($DB->count_records('groupings_groups', array('groupingid' => $groupingid)) != 0) {
                    $options[] = html_writer::tag('option', $grouping, array('value' => $groupingid));
                } else {
                    // Disable empty groupings!
                    $options[] = html_writer::tag('option', $grouping, array('value' => $groupingid, 'disabled' => 'disabled'));
                }
            }
        }

        $checkboxcontrols = $checkboxcontroltitle;

        // Add Radiobuttons and Go Button TODO replace single buttons with radiobuttons + go-button!
        $checkalllink = html_writer::tag('span',
                                         html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                               'type'  => 'radio',
                                                                               'id'    => 'select',
                                                                               'value' => 'select',
                                                                               'class' => 'select_all')).
                                         html_writer::tag('label', strip_tags($selectall), array('for' => 'select')),
                                         array('class' => 'nowrap'));
        $checknonelink = html_writer::tag('span',
                                          html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                                'type'  => 'radio',
                                                                                'id'    => 'deselect',
                                                                                'value' => 'deselect',
                                                                                'class' => 'select_none')).
                                          html_writer::tag('label', strip_tags($selectnone), array('for' => 'deselect')),
                                          array('class' => 'nowrap'));
        $checktogglelink = html_writer::tag('span',
                                            html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                                  'type'  => 'radio',
                                                                                  'id'    => 'toggle',
                                                                                  'value' => 'toggle',
                                                                                  'class' => 'toggle_selection')).
                                            html_writer::tag('label', strip_tags($inverseselection), array('for' => 'toggle')),
                                            array('class' => 'nowrap'));
        $submitbutton = html_writer::tag('button', get_string('go'),
                                         array('name' => 'do_class_action',
                                               'value' => 'Go'));

        $attr = array('class' => 'felement');
        $checkboxcontrols .= html_writer::tag('div',
                                              html_writer::tag('select', implode("\n", $options),
                                                               array('name' => 'classes[]',
                                                                     'multiple' => 'multiple')).
                                              $checkalllink.$checknonelink.
                                              $checktogglelink.$submitbutton,
                                              $attr);
        return $checkboxcontrols;
    }
}