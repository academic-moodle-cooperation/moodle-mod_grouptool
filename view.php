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
 * Displays a particular instance of mod_grouptool
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
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Hannes Laimer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
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
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

// Do we get course_module ID?
$id = optional_param('id', 0, PARAM_INT);
// Or do we get grouptool instance ID?
$g  = optional_param('g', 0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('grouptool', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $grouptool  = $DB->get_record('grouptool', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($g) {
    $grouptool  = $DB->get_record('grouptool', ['id' => $g], '*', MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $grouptool->course], '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('grouptool', $grouptool->id, $course->id, false, MUST_EXIST);
    $id = $cm->id;
} else {
    print_error('invalidcoursemodule');

}


require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// Print the page header!
$PAGE->set_url('/mod/grouptool/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($grouptool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($grouptool);

$instance = new mod_grouptool($cm->id, $grouptool, $cm, $course);

// Output starts here!
echo $OUTPUT->header();
// Print tabs according to users capabilities!

$inactive = [];
$tabs = [];
$row = [];
$creategrps = has_capability('mod/grouptool:create_groups', $context);
$creategrpgs = has_capability('mod/grouptool:create_groupings', $context);
$admingrps = has_capability('mod/grouptool:administrate_groups', $context);

if ($creategrps || $creategrpgs || $admingrps) {

    if ($creategrps && ($admingrps || $creategrpgs)) {
        $row['administration'] = new tabobject('administration',
                                               $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                               '&amp;tab=group_admin',
                                               get_string('administration', 'grouptool'),
                                               get_string('administration_alt', 'grouptool'),
                                               false);
        $row['administration']->subtree['group_admin'] = new tabobject('group_admin',
                                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                                                       '&amp;tab=group_admin',
                                                                       get_string('group_administration', 'grouptool'),
                                                                       get_string('group_administration_alt', 'grouptool'),
                                                                       false);
        $row['administration']->subtree['group_creation'] = new tabobject('group_creation',
                                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                                                       '&amp;tab=group_creation',
                                                                       get_string('group_creation', 'grouptool'),
                                                                       get_string('group_creation_alt', 'grouptool'),
                                                                       false);
    } else if ($creategrps) {
        $row['administration'] = new tabobject('administration',
                                               $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                               '&amp;tab=group_admin',
                                               get_string('administration', 'grouptool'),
                                               get_string('administration_alt', 'grouptool'),
                                               false);
        $row['administration']->subtree['group_creation'] = new tabobject('group_creation',
                                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                                                       '&amp;tab=group_creation',
                                                                       get_string('group_creation', 'grouptool'),
                                                                       get_string('group_creation_alt', 'grouptool'),
                                                                       false);
    } else if ($creategrpgs || $admingrps) {
        $row['administration'] = new tabobject('administration',
                                               $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                               '&amp;tab=group_admin',
                                               get_string('administration', 'grouptool'),
                                               get_string('administration_alt', 'grouptool'),
                                               false);
        $row['administration']->subtree['group_admin'] = new tabobject('group_admin',
                                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                                                       '&amp;tab=group_admin',
                                                                       get_string('group_administration', 'grouptool'),
                                                                       get_string('group_administration_alt', 'grouptool'),
                                                                       false);
    }
}
if (has_capability('mod/grouptool:grade', $context)
    || has_capability('mod/grouptool:grade_own_group', $context)) {
    $row['grading'] = new tabobject('grading',
                                    $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=grading',
                                    get_string('grading', 'grouptool'),
                                    get_string('grading_alt', 'grouptool'),
                                    false);
}
if (has_capability('mod/grouptool:register_students', $context)
        || has_capability('mod/grouptool:register', $context)
        || has_capability('mod/grouptool:view_own_registration', $context)
        || has_capability('mod/grouptool:view_groups', $context)) {
    $row['selfregistration'] = new tabobject('selfregistration',
                                             $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                                             '&amp;tab=selfregistration',
                                             get_string('selfregistration', 'grouptool'),
                                             get_string('selfregistration_alt', 'grouptool'),
                                             false);
}
if (has_capability('mod/grouptool:register_students', $context)
        || has_capability('mod/grouptool:unregister_students', $context)) {
    $importtab = new tabobject('import',
        $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $id . '&amp;tab=import',
        get_string('import', 'grouptool'),
        get_string('import_desc', 'grouptool'),
        false);
    $unregtab = new tabobject('unregister',
        $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $id . '&amp;tab=unregister',
        get_string('unregister', 'grouptool'),
        get_string('unregister_desc', 'grouptool'),
        false);
    if(has_capability('mod/grouptool:register_students', $context)
        && has_capability('mod/grouptool:unregister_students', $context)) { // can unregister and register
        $row['multiple'] = new tabobject('multiple',
            $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $id . '&amp;tab=import',
            get_string('multiple', 'grouptool'),
            get_string('multiple_desc', 'grouptool'),
            false);
        $row['multiple']->subtree['import'] = $importtab;
        $row['multiple']->subtree['unregister'] = $unregtab;
    } elseif (has_capability('mod/grouptool:register_students', $context)) { // can register
        $row['import'] = $importtab;
    } else { // can unregister
        $row['unregister'] = $unregtab;
    }
}
if (has_capability('mod/grouptool:view_regs_group_view', $context)
    && has_capability('mod/grouptool:view_regs_course_view', $context)) {
    $row['users'] = new tabobject('users',
                                  $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=overview',
                                  get_string('users_tab', 'grouptool'),
                                  get_string('users_tab_alt', 'grouptool'),
                                  false);
    $row['users']->subtree['overview'] = new tabobject('overview',
                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=overview',
                                                       get_string('overview_tab', 'grouptool'),
                                                       get_string('overview_tab_alt', 'grouptool'),
                                                       false);
    $row['users']->subtree['overview']->level = 2;
    $row['users']->subtree['userlist'] = new tabobject('userlist',
                                                       $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=userlist',
                                                       get_string('userlist_tab', 'grouptool'),
                                                       get_string('userlist_tab_alt', 'grouptool'),
                                                       false);
    $row['users']->subtree['userlist']->level = 2;
} else if (has_capability('mod/grouptool:view_regs_group_view', $context)) {
    $row['users'] = new tabobject('users',
                                  $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=overview',
                                  get_string('users_tab', 'grouptool'),
                                  get_string('users_tab_alt', 'grouptool'),
                                  false);
} else if (has_capability('mod/grouptool:view_regs_course_view', $context)) {
    $row['users'] = new tabobject('users',
                                  $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.'&amp;tab=userlist',
                                  get_string('users_tab', 'grouptool'),
                                  get_string('users_tab_alt', 'grouptool'),
                                  false);
}

if (!isset($SESSION->mod_grouptool)) {
    $SESSION->mod_grouptool = new stdClass();
}

// We won't iterate recursively over it right now, if we'll get more levels, we'll have to!
$availabletabs = array_keys($row);
foreach ($availabletabs as $cur) {
    if (!empty($row[$cur]->subtree)) {
        $availabletabs = array_merge($availabletabs, array_keys($row[$cur]->subtree));
    }
}

$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cm->id);
if (empty($cm->uservisible)) {
    $SESSION->mod_grouptool->currenttab = 'conditions_prevent_access';
    $tab = 'conditions_prevent_access';
} else if (count($row) > 1) {
    $tab = optional_param('tab', null, PARAM_ALPHAEXT);
    if ($tab) {
        $SESSION->mod_grouptool->currenttab = $tab;
    }

    if (!isset($SESSION->mod_grouptool->currenttab)
            || ($SESSION->mod_grouptool->currenttab == 'noaccess')
            || ($SESSION->mod_grouptool->currenttab == 'conditions_prevent_access')
            || !in_array($SESSION->mod_grouptool->currenttab, $availabletabs)) {
        // Set standard-tab according to users capabilities!
        if (has_capability('mod/grouptool:create_groupings', $context)
                || has_capability('mod/grouptool:administrate_groups', $context)) {
            $SESSION->mod_grouptool->currenttab = 'group_admin';
        } else if (has_capability('mod/grouptool:create_groups', $context)) {
            $SESSION->mod_grouptool->currenttab = 'group_creation';
        } else if (has_capability('mod/grouptool:register_students', $context)
                       || has_capability('mod/grouptool:register', $context)) {
            $SESSION->mod_grouptool->currenttab = 'selfregistration';
        } else {
            $SESSION->mod_grouptool->currenttab = current($availabletabs);
        }
    }

    mod_grouptool::translate_top_level_tabs($row, $tab);

    echo $OUTPUT->tabtree($row, $SESSION->mod_grouptool->currenttab, $inactive);
} else if (count($row) == 1) {
    $SESSION->mod_grouptool->currenttab = current($availabletabs);
    $tab = current($availabletabs);

    mod_grouptool::translate_top_level_tabs($row, $tab);
} else {
    $SESSION->mod_grouptool->currenttab = 'noaccess';
    $tab = 'noaccess';
}

$context = context_course::instance($course->id);
if (has_capability('moodle/course:managegroups', $context)) {
    // Print link to moodle groups!
    $url = new moodle_url('/group/index.php', ['id' => $course->id]);
    $grpslnk = html_writer::link($url,
                                 get_string('viewmoodlegroups', 'grouptool'));
    echo html_writer::tag('div', $grpslnk, ['class' => 'moodlegrpslnk']);
    echo html_writer::tag('div', '', ['class' => 'clearer']);
}

$PAGE->url->param('tab', $SESSION->mod_grouptool->currenttab);

$tab = $SESSION->mod_grouptool->currenttab; // Shortcut!

/* TRIGGER THE VIEW EVENT */
$event = \mod_grouptool\event\course_module_viewed::create([
        'objectid' => $cm->instance,
        'context'  => context_module::instance($cm->id),
        'other'    => [
        'tab' => $tab,
        'name' => $instance->get_name(),
        ],
]);
$event->add_record_snapshot('course', $course);
// In the next line you can use $PAGE->activityrecord if you have set it, or skip this line if you don't have a record.
$event->add_record_snapshot($PAGE->cm->modname, $grouptool);
$event->trigger();
/* END OF VIEW EVENT */

switch ($tab) {
    case 'group_admin':
        $instance->view_administration();
        break;
    case 'group_creation':
        $instance->view_creation();
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
    case 'unregister':
        $instance->view_unregister();
        break;
    case 'overview':
        $instance->view_overview();
        break;
    case 'userlist':
        $instance->view_userlist();
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
            $text = "<br />".format_text($cm->availableinfo, FORMAT_HTML);
        } else {
            // User cannot access the activity and they will not see it at all.
            $text = '';
        }
        $notification = $OUTPUT->notification(get_string('conditions_prevent_access', 'grouptool').$text, 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    default:
        $notification = $OUTPUT->notification(get_string('incorrect_tab', 'grouptool'), 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
}

// Finish the page!
echo $OUTPUT->footer();
