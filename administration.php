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

$inactivetabs = [];

// Adds Filter Selector
static $options = null;
$url = new moodle_url($CFG->wwwroot . '/mod/grouptool/administration.php?id=' . $id .
    '&amp;tab=group_admin');
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

                echo mod_grouptool::confirm($text, $continue, $cancel);
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
                $url = new moodle_url('/mod/grouptool/view.php', [
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
        // TODO CHANGE echo $this->confirm($confirmtext, $continue, $cancel);
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
    $params = ['cmid' => $cm->id,
        'filter' => $curfilter,
        'filterall' => GROUPTOOL_FILTER_ALL,
        'globalsize' => $this->grouptool->grpsize,
        'usesize' => (bool)$this->grouptool->use_size,];
    $PAGE->requires->js_call_amd('mod_grouptool/administration', 'initializer', $params);
     */
    echo $OUTPUT->footer();
}




