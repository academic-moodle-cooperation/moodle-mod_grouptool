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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * view.php
 * Prints a particular instance of grouptool
 *
 * Shows different tabs according to users capabilities
 * |-- administration: tools for creating groups, groupings
 * |                   and to choose for this instance active groups
 * |-- grading: tool to copy grades from one groupmember to either
 * |                   *) all others (for 1 or more groups) or
 * |                   *) selected others (only available for 1 group at a time)
 * |-- registration: tool to either import students into groups as teacher or register
 * |                 to a group by oneself as student if this is activated for the particular
 * |                 instance
 * |-- overview:     overview over the active coursegroups
 * |                 as well as the registered and queued students
 * |-- userlist:     view/export lists of students including their registrations
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

// Do we get course_module ID?
$id = optional_param('id', 0, PARAM_INT);
// Or do we get grouptool instance ID?
$g  = optional_param('g', 0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('grouptool', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $grouptool  = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($g) {
    $grouptool  = $DB->get_record('grouptool', array('id' => $g), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $grouptool->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('grouptool', $grouptool->id, $course->id, false,
                                                 MUST_EXIST);
} else {
    print_error('invalidcoursemodule');

}


require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
// Print the page header!
$PAGE->set_url('/mod/grouptool/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));

$instance = new grouptool($cm->id, $grouptool, $cm, $course);

// Output starts here!
echo $OUTPUT->header();

// Groupmode?
$gmok = true;
if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
    $gmok = $gmok && (groups_has_membership($cm) || !$cm->groupmembersonly);
}

// Print tabs according to users capabilities!
$inactive = null;
$activetwo = null;
$tabs = array();
$row = array();
$available_tabs = array();
if (has_capability('mod/grouptool:create_groups', $context)
    || has_capability('mod/grouptool:create_groupings', $context)
    || has_capability('mod/grouptool:register_students', $context)) {
    $row[] = new tabobject('administration',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                           '&amp;tab=administration',
                           get_string('administration', 'grouptool'),
                           get_string('administration_alt', 'grouptool'),
                           false);
    $available_tabs[] = 'administration';
}
if (has_capability('mod/grouptool:grade', $context)
    || has_capability('mod/grouptool:grade_own_group', $context)) {
    $row[] = new tabobject('grading',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=grading',
                           get_string('grading', 'grouptool'),
                           get_string('grading_alt', 'grouptool'),
                           false);
    $available_tabs[] = 'grading';
}
if (has_capability('mod/grouptool:register_students', $context)
        || ($gmok && has_capability('mod/grouptool:register', $context))) {
    $row[] = new tabobject('selfregistration',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                           '&amp;tab=selfregistration',
                           get_string('selfregistration', 'grouptool'),
                           get_string('selfregistration_alt', 'grouptool'),
                           false);
    $available_tabs[] = 'selfregistration';
}
if (has_capability('mod/grouptool:register_students', $context)) {
    $row[] = new tabobject('import',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=import',
                           get_string('import', 'grouptool'),
                           get_string('import_desc', 'grouptool'),
                           false);
    $available_tabs[] = 'import';
}
if (has_capability('mod/grouptool:view_registrations', $context)) {
    $row[] = new tabobject('overview',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=overview',
                           get_string('overview', 'grouptool'),
                           get_string('overview_alt', 'grouptool'),
                           false);
    $available_tabs[] = 'overview';
}
if (has_capability('mod/grouptool:view_registrations', $context)) {
    $row[] = new tabobject('userlist',
                           $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=userlist',
                           get_string('userlist', 'grouptool'),
                           get_string('userlist_alt', 'grouptool'),
                           false);
    $available_tabs[] = 'userlist';
}
if (!isset($SESSION->mod_grouptool)) {
    $SESSION->mod_grouptool = new stdClass();
}
if (count($row) > 1) {
    $tab = optional_param('tab', null, PARAM_ALPHAEXT);
    if ($tab) {
        $SESSION->mod_grouptool->currenttab = $tab;
    }

    if (!isset($SESSION->mod_grouptool->currenttab)) {
        // Set standard-tab according to users capabilities!
        if (has_capability('mod/grouptool:create_groups', $context)
                || has_capability('mod/grouptool:create_groupings', $context)
                || has_capability('mod/grouptool:register_students', $context)) {
            $SESSION->mod_grouptool->currenttab = 'administration';
        } else if (has_capability('mod/grouptool:register_students', $context)
                       || ($gmok && has_capability('mod/grouptool:register', $context))) {
            $SESSION->mod_grouptool->currenttab = 'selfregistration';
        } else {
            $SESSION->mod_grouptool->currenttab = current($available_tabs);
        }
    }
    $tabs[] = $row;

    echo print_tabs($tabs, $SESSION->mod_grouptool->currenttab, $inactive, $activetwo, true);
} else if (count($row) == 1) {
    $SESSION->mod_grouptool->currenttab = current($available_tabs);
    $tab = current($available_tabs);
} else {
    $SESSION->mod_grouptool->currenttab = 'noaccess';
    $tab = 'noaccess';
}

$context = context_course::instance($course->id);
if (has_capability('moodle/course:managegroups', $context)) {
    // Print link to moodle groups
    $url = new moodle_url('/group/index.php', array('id'=>$course->id));
    $grpslnk = html_writer::link($url,
                                 get_string('viewmoodlegroups', 'grouptool'));
    echo html_writer::tag('div', $grpslnk, array('class'=>'moodlegrpslnk'));
    echo html_writer::tag('div', '', array('class'=>'clearer'));
}

$PAGE->url->param('tab', $SESSION->mod_grouptool->currenttab);

$tab = $SESSION->mod_grouptool->currenttab; // Shortcut!
add_to_log($course->id, 'grouptool', 'view '.$tab, "view.php?id={$id}&tab={$tab}",
           $instance->get_name(), $id);

switch($tab) {
    case 'administration':
        $instance->view_administration();
        break;
    case 'grading':
        $instance->view_grading();
        break;
    case 'selfregistration':
        $instance->view_selfregistration();
        break;
    case 'import':
        $instance->view_import();
        break;
    case 'overview':
        $instance->view_overview();
        break;
    case 'userlist':
        $instance->view_userlist();
        break;
    case 'noaccess':
        $notification = $OUTPUT->notification(get_string('noaccess', 'grouptool'), 'notifyproblem');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    default:
        $notification = $OUTPUT->notification(get_string('incorrect_tab', 'grouptool'),
                                              'notifyproblem');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
}

// Finish the page!
echo $OUTPUT->footer();
