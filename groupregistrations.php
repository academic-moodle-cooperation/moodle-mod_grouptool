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
 * Group registrations page for mod_grouptool.
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
}

require_once(__DIR__ . '/../../config.php');
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

global $SESSION, $OUTPUT, $CFG, $DB, $PAGE;

$id = optional_param('id', 0, PARAM_INT);
$g = optional_param('g', 0, PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);

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
    throw new moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Hide intro if the module is not available yet and the description should not always be shown.
if (empty($grouptool->alwaysshowdescription) && time() < $grouptool->timeavailable) {
    $grouptool->intro = '';
}

$PAGE->set_url('/mod/grouptool/groupregistrations.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($grouptool);
$PAGE->add_body_class('limitedwidth');

// Mark this activity as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$canviewregistrations = has_capability('mod/grouptool:view_regs_group_view', $context);

if (empty($SESSION->mod_grouptool)) {
    $SESSION->mod_grouptool = new stdClass();
}

$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cm->id);

// Mark the correct secondary navigation nodes as active.
if ($activenode = $PAGE->secondarynav->find_active_node()) {
    $activenode->make_inactive();
}

if ($registrationnode = $PAGE->secondarynav->find('mod_grouptool_registration', navigation_node::TYPE_SETTING)) {
    $registrationnode->make_active();
}


if ($mainnode = $PAGE->navigation->find('mod_grouptool_registration', null)) {
    $mainnode->make_active();

    if (($groupcreationnode = $mainnode->find('mod_grouptool_import', null)) && $tab === 'import') {
        $groupcreationnode->make_active();
    }
    if (($groupcreationnode = $mainnode->find('mod_grouptool_unregister', null)) && $tab === 'unregister') {
        $groupcreationnode->make_active();
    }
}

$instance = new mod_grouptool($cm->id, $grouptool, $cm, $course, $context);

if (!$canviewregistrations) {
    $SESSION->mod_grouptool->currenttab = 'noaccess';
    $tab = 'noaccess';
}

$url = new moodle_url('/mod/grouptool/groupregistrations.php', ['id' => $cm->id]);
$options = [
    'import' => get_string('import'),
    'unregister' => get_string('unregister', 'grouptool'),
];

echo $OUTPUT->header();

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
        echo $OUTPUT->box(
            $OUTPUT->notification(get_string('noaccess', 'grouptool'), 'error'),
            'generalbox centered'
        );
        break;

    default:
        $instance->view_overview();
        break;
}

echo $OUTPUT->footer();
