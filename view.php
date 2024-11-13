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
 * Displays a particular instance of mod_grouptool
 *
 * Shows different tabs according to users capabilities
 * |-- administration: tools for creating groups, groupings
 * |                   and to choose for this instance active groups
 * |-- registration: tool to either import students into groups as teacher or register
 * |                 to a group by oneself as student if this is activated for the particular
 * |                 instance
 * |-- overview:     overview over the active coursegroups
 * |                 as well as the registered and queued students
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Hannes Laimer
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// TODO split tabs in different PHP files and define it only when really needed (=import-tab , needed for progress bar)!
// @codingStandardsIgnoreLine
if ((isset($_POST['tab']) && $_POST['tab'] === 'import') || (isset($_GET['tab']) && $_GET['tab'] === 'import')
    || (isset($_POST['tab']) && $_POST['tab'] === 'unregister') || (isset($_GET['tab']) && $_GET['tab'] === 'unregister')) {
    // @codingStandardsIgnoreLine
    define('NO_OUTPUT_BUFFERING', true);
// @codingStandardsIgnoreLine
}
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

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
$PAGE->set_url('/mod/grouptool/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($grouptool);
$PAGE->add_body_class('mediumwidth');


$instance = new mod_grouptool($cm->id, $grouptool, $cm, $course);

// Cache output so header can be generated after new completion infos are avaliable
$outputcache = '';

// Mark as viewed!
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Print tabs according to users capabilities!

$inactive = [];
$tabs = [];
$row = [];
$creategrps = has_capability('mod/grouptool:create_groups', $context);
$creategrpgs = has_capability('mod/grouptool:create_groupings', $context);
$admingrps = has_capability('mod/grouptool:administrate_groups', $context);

if (!isset($SESSION->mod_grouptool)) {
    $SESSION->mod_grouptool = new stdClass();
}

$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cm->id);

if (empty($cm->uservisible)) {
    $SESSION->mod_grouptool->currenttab = 'conditions_prevent_access';
    $tab = 'conditions_prevent_access';
    // TODO USE RIGHT CAPABILITIES HERE
} else if ($creategrps || $creategrpgs || $admingrps) {
    $tab = optional_param('tab', null, PARAM_ALPHAEXT);
    if ($tab) {
        $SESSION->mod_grouptool->currenttab = $tab;
    }else{
        $SESSION->mod_grouptool->currenttab = 'default';
    }

    if (!isset($SESSION->mod_grouptool->currenttab)
        || ($SESSION->mod_grouptool->currenttab == 'noaccess')
        || ($SESSION->mod_grouptool->currenttab == 'conditions_prevent_access')) {
        // Set standard-tab according to users capabilities!
        if (has_capability('mod/grouptool:create_groupings', $context)
            || has_capability('mod/grouptool:administrate_groups', $context)) {
            $SESSION->mod_grouptool->currenttab = 'group_admin';
        } else if (has_capability('mod/grouptool:create_groups', $context)) {
            $SESSION->mod_grouptool->currenttab = 'group_creation';
        } else if (has_capability('mod/grouptool:register_students', $context)
            || has_capability('mod/grouptool:register', $context)) {
            $SESSION->mod_grouptool->currenttab = 'selfregistration';
        }
    }
} else {
    $SESSION->mod_grouptool->currenttab = 'noaccess';
    $tab = 'noaccess';
}

$context = context_course::instance($course->id);
$PAGE->url->param('tab', $SESSION->mod_grouptool->currenttab);
$tab = $SESSION->mod_grouptool->currenttab; // Shortcut!


/* TRIGGER THE VIEW EVENT */
$event = \mod_grouptool\event\course_module_viewed::create([
    'objectid' => $cm->instance,
    'context' => context_module::instance($cm->id),
    'other' => [
        'tab' => $tab,
        'name' => $instance->get_name(),
    ],
]);
$event->add_record_snapshot('course', $course);
// In the next line you can use $PAGE->activityrecord if you have set it, or skip this line if you don't have a record.
$event->add_record_snapshot($PAGE->cm->modname, $grouptool);
$event->trigger();
/* END OF VIEW EVENT */

$node = $PAGE->secondarynav->find_active_node();
if ($node) {
    $node->make_inactive();

    switch ($tab){
        case 'default':
        case 'selfregistration':
        case 'noaccess':
        case 'condition_prevent_access':
            $node2 = $PAGE->secondarynav->find("modulepage", null);
            break;
        case 'group_admin':
        case 'group_creation':
            $node2 = $PAGE->secondarynav->find("mod_grouptool_administration", navigation_node::TYPE_SETTING);
            break;
        case 'overview':
        case 'import':
        case 'unregister_user':
            $node2 = $PAGE->secondarynav->find("mod_grouptool_registration", navigation_node::TYPE_SETTING);
            break;
        default:
            $node2 = false;
    }
    if($node2){
        $node2->make_active();
    }
}

if ($tab != 'selfregistration') {
    // Output starts here!
    echo $OUTPUT->header();
    echo $outputcache;
}

switch ($tab) {
    case 'default':
        break;
    case 'group_admin':
        $instance->view_administration();
        break;
    case 'overview':
        $instance->view_overview();
        break;
    case 'group_creation':
        $instance->view_creation();
        break;
    case 'selfregistration':
        // Send cached tab output so selfregistration can add the header once updated.
        $instance->view_selfregistration($outputcache);
        break;
    case 'import':
        $instance->view_import();
        break;
    case 'unregister':
        $instance->view_unregister();
        break;
    case 'noaccess':
        $notification = $OUTPUT->notification(get_string('noaccess', 'grouptool'), 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    case 'conditions_prevent_access':
        if ($cm->availableinfo) {
            // User cannot access the activity, but on the course page they will
            // see a link to it, greyed-out, with information (HTML format) from
            // $cm->availableinfo about why they can't access it.
            $text = "<br />" . format_text($cm->availableinfo, FORMAT_HTML);
        } else {
            // User cannot access the activity and they will not see it at all.
            $text = '';
        }
        $notification = $OUTPUT->notification(get_string('conditions_prevent_access', 'grouptool') . $text, 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    default:
        $notification = $OUTPUT->notification(get_string('incorrect_tab', 'grouptool'), 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
}

// Finish the page!
echo $OUTPUT->footer();
