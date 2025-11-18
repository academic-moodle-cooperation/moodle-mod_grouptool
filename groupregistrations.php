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

/**
 * Displays a particular page of mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
if (((isset($_POST['tab']) && $_POST['tab'] === 'import') || (isset($_GET['tab']) && $_GET['tab'] === 'import')
    || (isset($_POST['tab']) && $_POST['tab'] === 'unregister') || (isset($_GET['tab']) && $_GET['tab'] === 'unregister'))
) {
    // @codingStandardsIgnoreLine
    define('NO_OUTPUT_BUFFERING', true);
// @codingStandardsIgnoreLine
}
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

defined('MOODLE_INTERNAL') || die();

global $SESSION, $OUTPUT, $CFG, $DB, $USER, $PAGE;


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
    throw new \moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// Hide intro if module not available yet and alwaysshowdescription is false.
if ($grouptool->alwaysshowdescription == 0 && time() < $grouptool->timeavailable) {
    $grouptool->intro = '';
}
// Configure the page header!
$PAGE->set_url('/mod/grouptool/groupregistrations.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($grouptool);
$PAGE->add_body_class('limitedwidth');

// Mark as viewed!
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Build site according to user capability.
$creategrps = has_capability('mod/grouptool:administrate_groups', $context);
$creategrpgs = has_capability('mod/grouptool:administrate_groups', $context);
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

$node2 = $PAGE->secondarynav->find("mod_grouptool_registration", navigation_node::TYPE_SETTING);
if ($node2) {
    $node2->make_active();
}
$instance = new mod_grouptool($cm->id, $grouptool, $cm, $course, $context);
$header = $OUTPUT->header();
echo $header;

$tab = optional_param('tab', null, PARAM_ALPHAEXT);
if (!($creategrps || $creategrpgs || $admingrps)) {
    $SESSION->mod_grouptool->currenttab = 'noaccess';
    $tab = 'noaccess';
}
$url = new moodle_url('/mod/grouptool/groupregistrations.php', ['id' => $cm->id]);
$options = [
    'import' => get_string('import'),
    'unregister' => get_string('unregister', 'grouptool'),
];

switch ($tab) {
    case 'import':
        if (has_capability('mod/grouptool:administrate_deregistration', $context)) {
            $select = new single_select($url, 'tab', $options, 'import', false);
            echo html_writer::tag('div', $OUTPUT->render($select), ['class' => 'grouptool_manage_user_select']) . '<br>';
        }
        $instance->view_import();
        break;
    case 'unregister':
        if (has_capability('mod/grouptool:administrate_registration', $context)) {
            $select = new single_select($url, 'tab', $options, 'unregister', false);
            echo html_writer::tag('div', $OUTPUT->render($select), ['class' => 'grouptool_manage_user_select']) . '<br>';
        }
        $instance->view_unregister();
        break;
    case 'noaccess':
        $notification = $OUTPUT->notification(get_string('noaccess', 'grouptool'), 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    default:
        $instance->view_overview();
}
echo $OUTPUT->footer();
