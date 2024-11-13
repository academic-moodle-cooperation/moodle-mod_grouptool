<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
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
global $SESSION, $OUTPUT, $CFG, $DB, $USER, $PAGE;

/**
 * Displays a particular page of mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/grouptool/locallib.php');
require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/grouptool/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/pdflib.php');

const FILTER_ALL = 0;
/**
 * filter active groups
 */
const FILTER_ACTIVE = 1;
/**
 * filter inactive groups
 */
const FILTER_INACTIVE = 2;

/**
 * NAME_TAGS - the tags available for grouptool's group naming schemes
 */
const NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

/**
 * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
 */
const HIDE_GROUPMEMBERS = GROUPTOOL_HIDE_GROUPMEMBERS;
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
 */
const SHOW_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE;
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
 */
const SHOW_OWN_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
/**
 * SHOW_OWN_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
 */
const SHOW_OWN_GROUPMEMBERS_AFTER_REG = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
/**
 * SHOW_GROUPMEMBERS - show groupmembers no matter what...
 */
const SHOW_GROUPMEMBERS = GROUPTOOL_SHOW_GROUPMEMBERS;

// TODO fix all TODOs in this file
// TODO Delete all this, from methods or find a better way to use the old class
// TODO file clean up

// Do we get course_module ID?
$id = optional_param('id', 0, PARAM_INT);
// Or do we get grouptool instance ID?
$g = optional_param('g', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('grouptool', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $grouptool = $DB->get_record('grouptool', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($g) {
    $grouptool = $DB->get_record('grouptool', ['id' => $g], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $grouptool->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $course->id, false, MUST_EXIST);
    $id = $cm->id;
} else {
    print_error('invalidcoursemodule');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// Hide intro if module not available yet and alwaysshowdescription is false.
if ($grouptool->alwaysshowdescription == 0 && time() < $grouptool->timeavailable) {
    $grouptool->intro = '';
}
// Configure the page header!
$PAGE->set_url('/mod/grouptool/administration.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($grouptool);
$PAGE->add_body_class('mediumwidth');

// Mark as viewed!
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Build site according to user capability
$creategrps = has_capability('mod/grouptool:create_groups', $context);
$creategrpgs = has_capability('mod/grouptool:create_groupings', $context);
$admingrps = has_capability('mod/grouptool:administrate_groups', $context);

if (!isset($SESSION->mod_grouptool)) {
    $SESSION->mod_grouptool = new stdClass();
}

$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cm->id);

$node = $PAGE->secondarynav->find_active_node();
if ($node) {
    $node->make_inactive();
}

$node2 = $PAGE->secondarynav->find("mod_grouptool_administration", navigation_node::TYPE_SETTING);
if ($node2) {
    $node2->make_active();
}

echo $OUTPUT->header();

$output = $PAGE->get_renderer('mod_grouptool');

// Repair possibly missing agrps...
// TODO add function in some way
// $this->add_missing_agrps();

$id = $cm->id;

// Get applicable roles!
$rolenames = [];
if ($roles = get_profile_roles($context)) {
    foreach ($roles as $role) {
        $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
    }
}

$filter = optional_param('filter', null, PARAM_INT);
if ($filter !== null) {
    set_user_preference('mod_grouptool_group_filter', $filter, $USER->id);
} else {
    $filter = get_user_preferences('mod_grouptool_group_filter', FILTER_ACTIVE, $USER->id);
}

$tab = optional_param('tab', null, PARAM_ALPHAEXT);
switch ($tab){
    case 'group_creation':
        view_creation($id,$cm,$course,$context,$filter,$output);
        break;
    default:
        view_adminsatration($id,$cm,$course,$context,$filter,$output);
}
echo $OUTPUT->footer();

/**
 * Print a message along with button choices for Continue/Cancel
 *
 * If a string or moodle_url is given instead of a single_button, method defaults to post.
 * If cancel=null only continue button is displayed!
 *
 * @param string $message The question to ask the user
 * @param single_button|moodle_url|string $continue The single_button component representing the
 *                                                  Continue answer. Can also be a moodle_url
 *                                                  or string URL
 * @param single_button|moodle_url|string $cancel The single_button component representing the
 *                                                  Cancel answer. Can also be a moodle_url or
 *                                                  string URL
 * @return string HTML fragment
 * @throws coding_exception
 * @throws moodle_exception
 */
 function confirm($message, $continue, $cancel = null) {
    global $OUTPUT;
    if (!($continue instanceof single_button)) {
        if (is_string($continue)) {
            $url = new moodle_url($continue);
            $continue = new single_button($url, get_string('continue'), 'post', 'primary');
        } else if ($continue instanceof moodle_url) {
            $continue = new single_button($continue, get_string('continue'), 'post', 'primary');
        } else {
            throw new coding_exception('The continue param to grouptool::confirm() must be either a' .
                ' URL (string/moodle_url) or a single_button instance.');
        }
    }

    if (!($cancel instanceof single_button)) {
        if (is_string($cancel)) {
            $cancel = new single_button(new moodle_url($cancel), get_string('cancel'), 'get');
        } else if ($cancel instanceof moodle_url) {
            $cancel = new single_button($cancel, get_string('cancel'), 'get');
        } else if ($cancel == null) {
            $cancel = null;
        } else {
            throw new coding_exception('The cancel param to grouptool::confirm() must be either a' .
                ' URL (string/moodle_url), single_button instance or null.');
        }
    }

    $output = $OUTPUT->box_start('generalbox modal modal-dialog modal-in-page show', 'notice');
    $output .= $OUTPUT->box_start('modal-content', 'modal-content');
    $output .= $OUTPUT->box_start('modal-header', 'modal-header');
    $output .= html_writer::tag('h4', get_string('confirm'));
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_start('modal-body', 'modal-body');
    $output .= html_writer::tag('p', $message);
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_start('modal-footer', 'modal-footer');
    $cancel = ($cancel != null) ? $OUTPUT->render($cancel) : "";
    $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel, ['class' => 'buttons']);
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_end();
    return $output;
}

function view_adminsatration($id,$cm,$course,$context,$filter,$output){
    global $CFG,$OUTPUT,$DB;


    $inactivetabs = [];

// Adds Filter Selector
    static $options = null;
    $url = new moodle_url($CFG->wwwroot . '/mod/grouptool/administration.php?id=' . $id . '&amp;tab=group_admin');
    if (!$options) {
        $options = [
            FILTER_ACTIVE => get_string('active', 'grouptool'),
            FILTER_INACTIVE => get_string('inactive'),
            FILTER_ALL => get_string('all'),
        ];
    }
    $param = optional_param('filter', FILTER_ALL, PARAM_INT);
    $filerselect = new single_select($url, 'filter', $options, $param, false);
    echo $OUTPUT->render($filerselect);

    $url = new moodle_url($CFG->wwwroot . '/mod/grouptool/view.php?id=' . $id . '&tab=group_creation');
    $button = $OUTPUT->single_button($url, get_string('group_creation','grouptool'));
    echo $button;


    $bulkaction = optional_param('bulkaction', null, PARAM_ALPHA);
    $selected = optional_param_array('selected', [], PARAM_INT);
    $dialog = false;


    if ($bulkaction && $selected && optional_param('start_bulkaction', 0, PARAM_BOOL)) {
        switch ($bulkaction) {
            case 'activate':  // ...also via ajax bulk action?
                // Activate now!
                $groups = optional_param_array('selected', null, PARAM_INT);
                if (!empty($groups)) {
                    list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                    $DB->set_field_select("grouptool_agrps", "active", 1,
                        " grouptoolid = ? AND groupid " . $grpsql, array_merge([$cm->instance], $grpparams));
                }
                echo $OUTPUT->notification(get_string('activated_groups', 'grouptool'),
                    \core\output\notification::NOTIFY_SUCCESS);
                break;
            case 'deactivate':  // ...also via ajax bulk action?
                // Deactivate now!
                $groups = optional_param_array('selected', null, PARAM_INT);
                if (!empty($groups)) {
                    list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                    $DB->set_field_select("grouptool_agrps", "active", 0,
                        " grouptoolid = ? AND groupid " . $grpsql, array_merge([$cm->instance], $grpparams));
                }
                echo $OUTPUT->notification(get_string('deactivated_groups', 'grouptool'),
                    \core\output\notification::NOTIFY_SUCCESS);
                break;
            case 'delete': // ...also via ajax bulk action?
                // Show confirmation dialogue!
                if (optional_param('confirm', 0, PARAM_BOOL)) {
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    $groups = $DB->get_records_list('groups', 'id', $groups);
                    foreach ($groups as $group) {
                        groups_delete_group($group);
                    }
                    echo $OUTPUT->notification(get_string('successfully_deleted_groups', 'grouptool'),
                        \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    $cancel = new moodle_url($PAGE->url, ['tab' => 'group_admin']);
                    $params = ['confirm' => 1, 'bulkaction' => 'delete', 'start_bulkaction' => 1];
                    $text = get_string('confirm_delete', 'grouptool') . html_writer::start_tag('ul');
                    $groups = $DB->get_records_list('groups', 'id', $selected);
                    foreach ($selected as $select) {
                        $params['selected[' . $select . ']'] = $select;
                        $text .= html_writer::tag('li', $groups[$select]->name);
                    }
                    $text .= html_writer::end_tag('ul');
                    $continue = new moodle_url($cancel, $params);

                    echo confirm($text, $continue, $cancel);
                    echo $OUTPUT->footer();
                    $dialog = true;
                }
                break;
            case 'grouping':
                // Show grouping creation form!
                $selected = optional_param_array('selected', [], PARAM_INT);
                $mform = new \mod_grouptool\groupings_creation_form(null, [
                    'id' => $id,
                    'selected' => $selected,
                ]);
                $groups = $DB->get_records_list('groups', 'id', $selected);
                if ($mform->is_cancelled()) {
                    $bulkaction = null;
                    $selected = [];
                } else if ($fromform = $mform->get_data()) {
                    // Some groupings should be created...
                    if ($fromform->target == -2) { // One new grouping per group!
                        foreach ($groups as $group) {
                            $grouping = new stdClass();
                            if (!$grouping->id = groups_get_grouping_by_name($course->id, $group->name)) {
                                $grouping = new stdClass();
                                $grouping->courseid = $course->id;
                                $grouping->name = $group->name;
                                $grouping->id = groups_create_grouping($grouping);
                            }
                            // Insert group!
                            groups_assign_grouping($grouping->id, $group->id);
                        }
                    } else if ($fromform->target == -1) { // One new grouping!
                        // Create grouping if it doesn't exist...
                        $grouping = new stdClass();
                        if (!$grouping->id = groups_get_grouping_by_name($course->id, $fromform->name)) {
                            $grouping = new stdClass();
                            $grouping->courseid = $course->id;
                            $grouping->name = trim($fromform->name);
                            $grouping->id = groups_create_grouping($grouping);
                        }
                        // Insert groups!
                        foreach ($groups as $group) {
                            groups_assign_grouping($grouping->id, $group->id);
                        }
                    } else if ($fromform->target > 0) { // Existing Grouping!
                        $grouping = groups_get_grouping($fromform->target);
                        if ($grouping) {
                            foreach ($groups as $group) {
                                groups_assign_grouping($grouping->id, $group->id);
                            }
                        }
                    }
                    // ...redirect to show sortlist again!
                    $url = new moodle_url('/mod/grouptool/administration.php', [
                        'id' => $cm->id,
                        'tab' => 'group_admin',
                        'filter' => $filter,
                    ]);
                    echo $OUTPUT->notification(get_string('groupings_created_and_groups_added',
                        'grouptool'), \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    $mform->display();
                    $dialog = true;
                }
                break;
        }
    }


// Check if everything has been confirmed, so we can finally start working!
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        if (isset($SESSION->grouptool->view_administration->createGroupings)) {
            require_capability('mod/grouptool:create_groupings', $context);
            $target = required_param('target', PARAM_INT);
            switch ($target) { // ...grpg_target | grpg_groupingname | use_all (0 sel | 1 all).
                case 0: // Invalid - no action! TODO Add message!
                    $preview = '';
                    break;
                case -2: // One grouping per group!
                    // TODO CHANGE list(, $preview) = $this->create_group_groupings();
                    break;
                case -1: // One new grouping for all!
                    // TODO CHANGE list(, $preview) = $this->update_grouping($target, required_param('name', PARAM_ALPHANUMEXT));
                    break;
                default:
                    // TODO CHANGE list(, $preview) = $this->update_grouping($target);
                    break;
            }
            $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
            echo $OUTPUT->box($preview, 'generalbox');
        }
        unset($SESSION->grouptool->view_administration);
    }

    if ($rename = optional_param('rename', 0, PARAM_INT)) {
        // Show Rename Form!
        $gform = new \mod_grouptool\group_rename_form(null, [
            'id' => $cm->id,
            'instance' => $cm->instance,
            'rename' => $rename,
        ]);
        if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
            $group = new stdClass();
            $group->id = $fromform->rename;
            $group->name = $fromform->name;
            $group->courseid = $fromform->courseid;
            groups_update_group($group);
        } else if (!$gform->is_cancelled()) {
            $data = new stdClass();
            $data->name = $DB->get_field('groups', 'name', ['id' => $rename]);
            $gform->set_data($data);
            $gform->display();
            echo $OUTPUT->footer();
            die;
        }
    }

    if ($resize = optional_param('resize', 0, PARAM_INT)) {
        // Show Resize Form!
        $gform = new \mod_grouptool\group_resize_form(null, [
            'id' => $cm->id,
            'instance' => $cm->instance,
            'resize' => $resize,
        ]);
        if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
            if (empty($fromform->size)) {
                $DB->set_field('grouptool_agrps', 'grpsize', null, [
                    'groupid' => $fromform->resize,
                    'grouptoolid' => $cm->instance,
                ]);
            } else {
                $group = new stdClass();
                $group->id = $DB->get_field('grouptool_agrps', 'id', [
                    'groupid' => $fromform->resize,
                    'grouptoolid' => $cm->instance,
                ]);
                $group->grpsize = $fromform->size;
                $DB->update_record('grouptool_agrps', $group);
            }
        } else if (!$gform->is_cancelled()) {
            $data = new stdClass();
            $data->size = $DB->get_field('grouptool_agrps', 'grpsize', [
                'groupid' => $resize,
                'grouptoolid' => $cm->instance,
            ]);
            $gform->set_data($data);
            $gform->display();
            echo $OUTPUT->footer();
            die;
        }
    }

    if ($delete = optional_param('delete', 0, PARAM_INT)) {
        if (!optional_param('confirm', 0, PARAM_BOOL)) {
            // Show Confirm!
            $cancel = new moodle_url($PAGE->url);
            $continue = new moodle_url($cancel, [
                'confirm' => 1,
                'delete' => $delete,
            ]);
            $cancel = new single_button($cancel, get_string('no'), 'post');
            $continue = new single_button($continue,
                get_string('yes'), 'post');
            $confirmtext = get_string('confirm_delete', 'grouptool');
            echo confirm($confirmtext, $continue, $cancel);
            echo $OUTPUT->footer();
            die;
        } else {
            // Delete it!
            groups_delete_group($delete);
        }
    }

    if ($toggle = optional_param('toggle', 0, PARAM_INT)) {
        if (!empty($toggle)) {
            $conditions = ['grouptoolid' => $cm->instance, 'groupid' => $toggle];
            if (!$DB->record_exists('grouptool_agrps', $conditions)) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_found', 'grouptool'),
                    \core\output\notification::NOTIFY_ERROR), 'generalbox');
            } else {
                $record = $DB->get_record('grouptool_agrps', $conditions);
                if (!empty($record->active)) {
                    $DB->set_field('grouptool_agrps', 'active', 0, $conditions);
                } else {
                    $DB->set_field('grouptool_agrps', 'active', 1, $conditions);
                }
            }
        }
    }

    if (!$dialog || !optional_param('start_bulkaction', 0, PARAM_BOOL)) {
        // Show form!
        $formaction = new moodle_url('/mod/grouptool/view.php', [
            'id' => $cm->id,
            'tab' => 'group_admin',
            'filter' => $filter,
        ]);
        $mform = new MoodleQuickForm('bulk', 'post', $formaction, '');

        $mform->addElement('hidden', 'sesskey');
        $mform->setDefault('sesskey', sesskey());

        $sortlist = new \mod_grouptool\output\sortlist($course->id, $cm, $filter);
        $sortlistcontroller = new \mod_grouptool\output\sortlist_controller($sortlist);
        $mform->addElement('html', $output->render($sortlistcontroller));
        $mform->addElement('html', $output->render($sortlist));

        $actions = [
            '' => get_string('choose', 'grouptool'),
            'activate' => get_string('setactive', 'grouptool'),
            'deactivate' => get_string('setinactive', 'grouptool'),
        ];
        /*
         * TODO CHANGE
        if (!($this->grouptool->ifgroupdeleted === GROUPTOOL_RECREATE_GROUP)
            && !$DB->record_exists('grouptool', ['course' => $cm->course,
                'ifgroupdeleted' => GROUPTOOL_RECREATE_GROUP,])) {
            $actions['delete'] = get_string('delete');
        }
        */
        $actions['grouping'] = get_string('createinsertgrouping', 'grouptool');

        $grp = [];
        $grp[] =& $mform->createElement('static', 'with_selection', '', get_string('with_selection',
            'grouptool'));
        $grp[] =& $mform->createElement('select', 'bulkaction', '', $actions);
        $grp[] =& $mform->createElement('submit', 'start_bulkaction', get_string('start',
            'grouptool'));
        $mform->addGroup($grp, 'actiongrp', '', ' ', false);
        $mform->disable_form_change_checker();

        $mform->display();

        switch ($filter) {
            case FILTER_ACTIVE:
                $curfilter = 'active';
                break;
            case FILTER_INACTIVE:
                $curfilter = 'inactive';
                break;
            default:
            case FILTER_ALL:
                $curfilter = 'all';
                break;
        }
        /*
         * TODO USE
        $params = ['cmid' => $cm->id,
            'filter' => $curfilter,
            'filterall' => GROUPTOOL_FILTER_ALL,
            'globalsize' => $this->grouptool->grpsize,
            'usesize' => (bool)$this->grouptool->use_size,];
        $PAGE->requires->js_call_amd('mod_grouptool/administration', 'initializer', $params);
         */
    }
}
/**
 * Outputs the content of the creation tab and manages actions taken in this tab
 *
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
 function view_creation($id,$cm,$course,$context,$filter,$output) {
    global $SESSION, $OUTPUT;

    $id = $cm->id;
    $context = context_course::instance($course->id);
    // Get applicable roles!
    $rolenames = [];
    if ($roles = get_profile_roles($context)) {
        foreach ($roles as $role) {
            $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
        }
    }

    // Check if everything has been confirmed, so we can finally start working!
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        if (isset($SESSION->grouptool->view_administration->createGroups)) {
            require_capability('mod/grouptool:create_groups', $context);
            // Create groups!
            $data = $SESSION->grouptool->view_administration;
            $error = false;
            $preview = '';
            // Display only active users if the option was selected or they do not have the capability to view suspended users.
            $onlyactive = !empty($data->includeonlyactiveenrol)
                || !has_capability('moodle/course:viewsuspendedusers', $context);
            list($source, $orderby) = view_creation_get_source_orderby($data);
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members($course->id, $data->roleid,
                        $source, $orderby, null, $onlyactive);
                    $usercnt = count($users);
                    $numgrps = $data->numberofgroups;
                    $userpergrp = floor($usercnt / $numgrps);
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members($course->id, $data->roleid,
                        $source, $orderby, null, $onlyactive);
                    $usercnt = count($users);
                    $numgrps = ceil($usercnt / $data->numberofmembers);
                    $userpergrp = $data->numberofmembers;
                    if (!empty($data->nosmallgroups) && $usercnt % $data->numberofmembers != 0) {
                        /*
                         *  If there would be one group with a small number of member
                         *  reduce the number of groups
                         */
                        $missing = $userpergrp * $numgrps - $usercnt;
                        if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                            // Spread the users from the last small group!
                            $numgrps--;
                            $userpergrp = floor($usercnt / $numgrps);
                        }
                    }
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                    break;
                case GROUPTOOL_1_PERSON_GROUPS:
                    $users = groups_get_potential_members($course->id, $data->roleid,
                        $source, 'lastname ASC, firstname ASC',
                        null, $onlyactive);
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $prev) = $this->create_one_person_groups($users,
                        $data->namingscheme,
                        $data->grouping,
                        $data->groupingname,
                        false,
                        $data->enablegroupmessaging);
                    $preview = $prev;
                    break;
                case GROUPTOOL_N_M_GROUPS:
                    /* Shortcut here: create_fromto_groups does exactly what we want,
                     * with from = 1 and to = number of groups to create! */
                    $data->from = 1;
                    $data->to = $data->numberofgroups;
                    $data->digits = 1;
                case GROUPTOOL_FROMTO_GROUPS:
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $preview) = $this->create_fromto_groups($data);
                    break;
            }
            if (!$error && has_capability('mod/grouptool:administrate_groups', $this->context)) {
                $linktext = '<i class="fa fa-long-arrow-right" aria-hidden="true"></i>' .
                    get_string('group_administration', 'grouptool');
                $urlparams = [
                    'id' => $cm->id,
                    'tab' => 'group_admin',
                ];
                $preview .= html_writer::link(new moodle_url('/mod/grouptool/view.php', $urlparams), $linktext, [
                    'class' => 'ml-1',
                ]);
            }
            $preview = $OUTPUT->notification($preview, $error ? \core\output\notification::NOTIFY_ERROR :
                \core\output\notification::NOTIFY_SUCCESS);
            echo $OUTPUT->box(html_writer::tag('div', $preview, ['class' => 'centered']),
                'generalbox');
        }
        unset($SESSION->grouptool->view_administration);
    }

    // Create the form-object!
    $showgrpsize = $grouptool->use_size;
    $mform = new \mod_grouptool\group_creation_form(null, [
        'id' => $id,
        'roles' => $rolenames,
        'show_grpsize' => $showgrpsize,
    ]);
    unset($showgrpsize);

    if ($fromform = $mform->get_data()) {
        require_capability('mod/grouptool:create_groups', $context);
        // Save submitted data in session and show confirmation dialog!
        if (!isset($SESSION->grouptool)) {
            $SESSION->grouptool = new stdClass();
        }
        if (!isset($SESSION->grouptool->view_administration)) {
            $SESSION->grouptool->view_administration = new stdClass();
        }
        $SESSION->grouptool->view_administration = $fromform;
        $data = $SESSION->grouptool->view_administration;
        $preview = "";
        $error = false;
        list($source, $orderby) = view_creation_get_source_orderby($data);
        $onlyactive = !empty($data->includeonlyactiveenrol)
            || !has_capability('moodle/course:viewsuspendedusers', $context);
        switch ($data->mode) {
            case GROUPTOOL_GROUPS_AMOUNT:
                // Allocate members from the selected role to groups!
                $users = groups_get_potential_members($course->id, $data->roleid,
                    $source, $orderby, null, $onlyactive);
                $usercnt = count($users);
                $numgrps = clean_param($data->numberofgroups, PARAM_INT);
                $userpergrp = floor($usercnt / $numgrps);
                list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                    $numgrps, true);
                break;
            case GROUPTOOL_MEMBERS_AMOUNT:
                // Allocate members from the selected role to groups!
                $users = groups_get_potential_members($course->id, $data->roleid,
                    $source, $orderby, null, $onlyactive);
                $usercnt = count($users);
                $numgrps = ceil($usercnt / $data->numberofmembers);
                $userpergrp = clean_param($data->numberofmembers, PARAM_INT);
                if (!empty($data->nosmallgroups) && $usercnt % clean_param($data->numberofmembers, PARAM_INT) != 0) {
                    /*
                     *  If there would be one group with a small number of member
                     *  reduce the number of groups
                     */
                    $missing = $userpergrp * $numgrps - $usercnt;
                    if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                        // Spread the users from the last small group!
                        $numgrps--;
                        $userpergrp = floor($usercnt / $numgrps);
                    }
                }
                list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                    $numgrps, true);
                break;
            case GROUPTOOL_1_PERSON_GROUPS:
                $users = groups_get_potential_members($course->id, $data->roleid,
                    $source, 'lastname ASC, firstname ASC', null, $onlyactive);
                if (!isset($data->groupingname)) {
                    $data->groupingname = null;
                }
                list($error, $prev) = $this->create_one_person_groups($users,
                    $data->namingscheme,
                    $data->grouping,
                    $data->groupingname,
                    true,
                    $data->enablegroupmessaging);
                $preview = $prev;
                break;
            case GROUPTOOL_N_M_GROUPS:
                /* Shortcut here: create_fromto_groups does exactly what we want,
                 * with from = 1 and to = number of groups to create! */
                $data->from = 1;
                $data->to = $data->numberofgroups;
                $data->digits = 1;
            case GROUPTOOL_FROMTO_GROUPS:
                if (!isset($data->groupingname)) {
                    $data->groupingname = null;
                }
                list($error, $preview) = $this->create_fromto_groups($data, true);
                break;
        }
        $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
        $tab = required_param('tab', PARAM_ALPHANUMEXT);
        if ($error) {
            $text = get_string('create_groups_confirm_problem', 'grouptool');
            $url = new moodle_url("view.php?id=$id&tab=" . $tab);
            $back = new single_button($url, get_string('back'), 'post');
            $confirmboxcontent = confirm($text, $back);
        } else {
            $continue = "view.php?id=$id&tab=" . $tab . "&confirm=true";
            $cancel = "view.php?id=$id&tab=" . $tab;
            $text = get_string('create_groups_confirm', 'grouptool');
            $confirmboxcontent = confirm($text, $continue, $cancel);
        }
        echo $OUTPUT->heading(get_string('preview'), 2, 'centered') .
            $OUTPUT->box($preview, 'generalbox') .
            $confirmboxcontent;
    } else {
        $mform->display();
    }
}

/**
 * returns the source of potential users and order mode
 *
 * @param object $data data of creation view
 * @return array $source array of possible sources for potential users
 * @return string $orderby sql clause for ordering the list of potential users
 * @throws moodle_exception
 */
function view_creation_get_source_orderby($data) {

    $source = [];
    if ($data->cohortid) {
        $source['cohortid'] = $data->cohortid;
    }
    if ($data->selectfromgrouping) {
        $source['groupingid'] = $data->selectfromgrouping;
    }
    if ($data->selectfromgroup) {
        $source['groupid'] = $data->selectfromgroup;
    }
    $orderby = "";
    switch ($data->allocateby) {
        default:
            print_error('unknoworder');
        case 'no':
        case 'random':
        case 'lastname':
            $orderby = 'lastname, firstname, idnumber';
            break;
        case 'firstname':
            $orderby = 'firstname, lastname, idnumber';
            break;
        case 'idnumber':
            $orderby = 'idnumber, lastname, firstname';
            break;
    }

    return [$source, $orderby];
}
/**
 * Create moodle-groups and also create non-active entries for the created groups
 * for this instance
 *
 * @param stdClass $data data from administration-form with all settings for group creation
 * @param stdClass[] $users which users to registrate in the created groups
 * @param int $userpergrp how many users should be registrated per group
 * @param int $numgrps how many groups should be created
 * @param bool $previewonly optional only show preview of created groups
 * @return array ( 0 => error, 1 => message )
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function create_groups($data, $users, $userpergrp, $numgrps,$cm,$context, $previewonly = false) {
    global $DB, $USER;

    require_capability('mod/grouptool:create_groups', $context);

    $namestouse = [];

    // Allocate members from the selected role to groups!
    $usercnt = count($users);
    if ($data->allocateby == 'random') {
        srand($data->seed);
        shuffle($users);
    }

    $groups = [];

    // Number of groups with userpergrp+1 for properly allocating the rest without messing up the sort order.
    $plusonegroupcount = ($usercnt / $numgrps) > $userpergrp ? $usercnt % $numgrps : 0;

    // Allocate the users - all groups equal count first!
    for ($i = 0; $i < $numgrps; $i++) {
        $groups[$i] = [];
        $groups[$i]['members'] = [];
        if ($data->allocateby == 'no') {
            continue; // Do not allocate users!
        }
        // Adds one member more if group is in the pluse one range.
        $plusonegroup = $i < $plusonegroupcount ? 1 : 0;
        for ($j = 0; $j < ($userpergrp + $plusonegroup); $j++) {
            if (empty($users)) {
                break 2;
            }
            $user = array_shift($users);
            $groups[$i]['members'][$user->id] = $user;
        }
    }
    // Throw an error if there are still users left who have not been allocated.
    if ($data->allocateby != 'no' && !empty($users)) {
        throw new coding_exception('User to group accocation did not work properly. There are still remaining users');
    }
    // Every member is there, so we can parse the name!
    $digitslog = log10($numgrps);
    // Add another digit if result of log is an integer (it means that no of groups was 10,10,100,...)
    $digits = fmod($digitslog, 1.) === 0 ? $digitslog + 1 : ceil($digitslog);
    for ($i = 0; $i < $numgrps; $i++) {
        $groups[$i]['name'] = $this->groups_parse_name(trim($data->namingscheme), $i,
            $groups[$i]['members'], $digits);
    }
    if ($previewonly) {
        $error = false;
        $table = new html_table();
        if ($data->allocateby == 'no') {
            $table->head = [get_string('groupscount', 'group', $numgrps)];
            $table->size = ['100%'];
            $table->align = ['left'];
        } else {
            $table->head = [
                get_string('groupscount', 'group', $numgrps),
                get_string('groupmembers', 'group'),
                get_string('usercounttotal', 'group', $usercnt),
            ];
            $table->size = ['20%', '70%', '10%'];
            $table->align = ['left', 'left', 'center'];
        }
        $table->data = [];

        foreach ($groups as $group) {
            $line = [];
            if (groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $namestouse)) {
                $error = true;
                if (in_array($group['name'], $namestouse)) {
                    $line[] = '<span class="late">' .
                        get_string('nameschemenotunique', 'grouptool', $group['name']) . '</span>';
                } else {
                    $line[] = '<span class="late">' .
                        get_string('groupnameexists', 'group', $group['name']) . '</span>';
                }
            } else {
                $line[] = $group['name'];
                $namestouse[] = $group['name'];
            }
            if ($data->allocateby != 'no') {
                $unames = [];
                foreach ($group['members'] as $user) {
                    $unames[] = fullname($user);
                }
                $line[] = implode(', ', $unames);
                $line[] = count($group['members']);
            }
            $table->data[] = $line;
        }
        return [0 => $error, 1 => html_writer::table($table)];

    } else {
        $grouping = null;
        $createdgrouping = 0;
        $createdgroups = [];
        $failed = false;

        // Prepare grouping!
        if (!empty($data->grouping)) {
            if ($data->grouping < 0) {
                $grouping = new stdClass();
                $grouping->courseid = $this->course->id;
                $grouping->name = trim($data->groupingname);
                $grouping->id = groups_create_grouping($grouping);
                $createdgrouping = $grouping->id;
            } else {
                $grouping = groups_get_grouping($data->grouping);
            }
        }

        // Trigger group_creation_started event.
        $groupingid = !empty($grouping) ? $grouping->id : 0;
        switch ($data->mode) {
            case GROUPTOOL_GROUPS_AMOUNT:
                \mod_grouptool\event\group_creation_started::create_groupamount($this->cm, $data->namingscheme,
                    $data->numberofgroups, $groupingid)->trigger();
                break;
            case GROUPTOOL_MEMBERS_AMOUNT:
                \mod_grouptool\event\group_creation_started::create_memberamount($this->cm, $data->namingscheme,
                    $data->numberofmembers,
                    $groupingid)->trigger();
                break;
        }

        // Save the groups data!
        foreach ($groups as $group) {
            if (groups_get_group_by_name($this->course->id, $group['name'])) {
                $error = get_string('groupnameexists', 'group', $group['name']);
                $failed = true;
                continue;
            }
            $newgroup = new stdClass();
            $newgroup->courseid = $this->course->id;
            $newgroup->name = $group['name'];
            $newgroup->enablemessaging = $data->enablegroupmessaging == 1 ? 1 : null;
            $groupid = groups_create_group($newgroup);
            $newagrp = $this->add_agrp_entry($groupid);
            $createdgroups[] = $groupid;
            foreach ($group['members'] as $user) {
                groups_add_member($groupid, $user->id);
                $usrreg = new stdClass();
                $usrreg->userid = $user->id;
                $usrreg->agrpid = $newagrp->id;
                $usrreg->timestamp = time();
                $usrreg->modified_by = $USER->id;
                $attr = [
                    'userid' => $user->id,
                    'agrpid' => $newagrp->id,
                ];
                if (!$DB->record_exists('grouptool_registered', $attr)) {
                    $DB->insert_record('grouptool_registered', $usrreg);
                } else {
                    $DB->set_field('grouptool_registered', 'modified_by', $USER->id, $attr);
                }
            }
            if ($grouping) {
                groups_assign_grouping($grouping->id, $groupid);
            }
        }

        if ($failed) {
            foreach ($createdgroups as $groupid) {
                groups_delete_group($groupid);
            }
            if ($createdgrouping) {
                groups_delete_grouping($createdgrouping);
            }
        } else {
            // Trigger agrps updated via groupcreation event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme, $numgrps,
                $groupingid)->trigger();
        }
    }
    if (empty($failed)) {
        $preview = get_string('groups_created', 'grouptool');
    } else if (empty($preview)) {
        if (!empty($error)) {
            $preview = $error;
        } else {
            $preview = get_string('group_creation_failed', 'grouptool');
        }
    }

    return [$failed, $preview];
}