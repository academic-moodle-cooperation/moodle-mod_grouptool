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
 * Non-AJAX-Version of showing group's members. Fallback for JS using showmembers_ajax.php
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir .'/grouplib.php');
require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

$agrpid = required_param('agrpid', PARAM_INT);

$grouptoolid = $DB->get_field_sql('SELECT agrp.grouptoolid AS grouptoolid
                                     FROM {grouptool_agrps} agrp
                                    WHERE agrp.id = ?', [$agrpid], MUST_EXIST);
$grouptool = $DB->get_record('grouptool', ['id' => $grouptoolid], '*', MUST_EXIST);

$PAGE->set_url('/mod/grouptool/showmembers.php');
$coursecontext = context_course::instance($grouptool->course);
$PAGE->set_context($coursecontext);

$cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course);
$context = context_module::instance($cm->id);

require_login($cm->course, true, $cm);

echo $OUTPUT->header();

$grouptool = new mod_grouptool($cm->id, $grouptool, $cm);

if (!has_capability('mod/grouptool:view_regs_group_view', $context)
    && !has_capability('mod/grouptool:view_regs_course_view', $context)
    && !$grouptool->canshowmembers($agrpid)) {
    echo html_writer::tag('div', get_string('not_allowed_to_show_members', 'grouptool'),
                          ['class' => 'reg']);
} else {

    $showidnumber = has_capability('mod/grouptool:view_regs_group_view', $context)
                    || has_capability('mod/grouptool:view_regs_course_view', $context);

    $group = $grouptool->get_active_groups(true, true, $agrpid);
    $group = current($group);

    echo $OUTPUT->heading($group->name, 2, 'showmembersheading');

    // Add data attributes for JS!
    $registered = [];
    if (!empty($group->registered)) {
        foreach ($group->registered as $cur) {
            $registered[] = $cur->userid;
        }
    }
    $members = array_keys($group->moodle_members);
    $queued = [];
    if (!empty($group->queued)) {
        foreach ($group->queued as $cur) {
            $queued[$cur->userid] = $cur->userid;
        }
    }
    // Get all registered users with moodle-group-membership!
    $absregs = array_intersect($registered, $members);
    $absregs = array_combine($absregs, $absregs);
    // Get all registered users without moodle-group-membership!
    $gtregs = array_diff($registered, $members);
    $gtregs = array_combine($gtregs, $gtregs);
    // Get all moodle-group-members without registration!
    $mdlregs = array_diff($members, $registered);
    $mdlregs = array_combine($mdlregs, $mdlregs);

    $context = new stdClass();
    $context->courseid = $cm->course;
    $context->showidnumber = $showidnumber;
    $context->profileurl = $CFG->wwwroot . '/user/view.php?course='.$cm->course.'&id=';
    $helpicon = new help_icon('status', 'mod_grouptool');
    $context->statushelp = $helpicon->export_for_template($OUTPUT);
    $context->name = $group->name;

    // Cache needed user records right now!
    $userfields = get_all_user_name_fields(true);
    if ($showidnumber) {
        $fields = "id,idnumber,".$userfields;
    } else {
        $fields = "id,".$userfields;
    }
    $users = $DB->get_records_list("user", 'id', $gtregs + $queued, null, $fields);

    $context->absregs = [];
    if (!empty($absregs)) {
        foreach ($absregs as $cur) {
            // These user records are fully fetched in $group->moodle_members!
            $context->absregs[] = [
                    'idnumber' => $showidnumber ? $group->moodle_members[$cur]->idnumber : '',
                    'fullname' => fullname($group->moodle_members[$cur]),
                    'id'       => $cur
            ];
        }
    }

    $context->gtregs = [];
    if (!empty($gtregs)) {
        foreach ($gtregs as $cur) {
            $context->gtregs[] = [
                    'idnumber' => $showidnumber ? $users[$cur]->idnumber : '',
                    'fullname' => fullname($users[$cur]),
                    'id'       => $cur
            ];
        }
    }

    $context->mregs = [];
    if (!empty($mdlregs)) {
        foreach ($mdlregs as $cur) {
            $context->mregs[] = [
                    'idnumber' => $showidnumber ? $group->moodle_members[$cur]->idnumber : '',
                    'fullname' => fullname($group->moodle_members[$cur]),
                    'id'       => $cur
            ];
        }
    }

    $context->queued = [];
    if (!empty($queued)) {
        $queuedlist = $DB->get_records('grouptool_queued', ['agrpid' => $group->agrpid], 'timestamp ASC');
        foreach ($queued as $cur) {
            $context->queued[] = [
                    'idnumber' => $showidnumber ? $users[$cur]->idnumber : '',
                    'fullname' => fullname($users[$cur]),
                    'id'       => $cur,
                    'rank'     => $this->get_rank_in_queue($queuedlist, $cur)
            ];
        }
    }

    // This will call the function to load and render our template.
    $class = 'col-12 offset-0 col-xl-8 offset-xl-2';
    echo $OUTPUT->box($OUTPUT->render_from_template('mod_grouptool/groupmembers', $context), $class);
}

echo $OUTPUT->footer();
