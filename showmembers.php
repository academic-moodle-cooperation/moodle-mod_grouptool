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
 * showmembers.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir .'/grouplib.php');

$agrpid = required_param('agrpid', PARAM_INT);

$group = $DB->get_record_sql('SELECT grp.id grpid, grp.name grpname, grp.courseid courseid,
                                     agrp.id agrpid, agrp.grpsize size,
                                     agrp.grouptoolid grouptoolid
                              FROM {grouptool_agrps} agrp
                                LEFT JOIN {groups} grp ON agrp.groupid = grp.id
                              WHERE agrp.id = ?', array($agrpid), MUST_EXIST);
$grouptool = $DB->get_record('grouptool', array('id' => $group->grouptoolid), '*', MUST_EXIST);

$PAGE->set_url('/mod/grouptool/showmembers.php');
$coursecontext = context_course::instance($group->courseid);
$PAGE->set_context($coursecontext);

$cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $group->courseid);
$context = context_module::instance($cm->id);

require_login($cm->course, true, $cm);

if (!$cm->uservisible) {
    if ($cm->availableinfo) {
        // User cannot access the activity, but on the course page they will
        // see a link to it, greyed-out, with information (HTML format) from
        // $cm->availableinfo about why they can't access it.
        $text = "<br />".format_text($cm->availableinfo, FORMAT_HTML);
    } else {
        // User cannot access the activity and they will not see it at all.
        $text = '';
    }
    $notification = $OUTPUT->notification(get_string('conditions_prevent_access', 'grouptool').$text, 'notifyproblem');
    echo $OUTPUT->box($notification, 'generalbox centered');
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();

echo $OUTPUT->heading($group->grpname, 2, 'showmembersheading');
if (!has_capability('mod/grouptool:view_regs_group_view', $context)
    && !has_capability('mod/grouptool:view_regs_course_view', $context)
    && !$grouptool->show_members) {
    echo html_writer::tag('div', get_string('not_allowed_to_show_members', 'grouptool'),
                          array('class' => 'reg'));
} else {
    echo $OUTPUT->heading(get_string('registrations', 'grouptool'), 3, 'showmembersheading');
    $moodlereg = groups_get_members($group->grpid, 'u.id');
    $userfieldssql = user_picture::fields('usr', array('idnumber'));
    // Get registrations but exclude all who are just marked for registration!
    $regsql = "SELECT $userfieldssql
                 FROM {grouptool_registered} reg
            LEFT JOIN {user} usr ON reg.userid = usr.id
                WHERE reg.agrpid = ? AND reg.modified_by >= 0
             ORDER BY timestamp ASC";
    if (!$regs = $DB->get_records_sql($regsql, array($agrpid))) {
        echo html_writer::tag('div', get_string('no_registrations', 'grouptool'),
                              array('class' => 'reg'));
    } else {
        echo html_writer::start_tag('ul');
        foreach ($regs as $user) {
            if (!in_array($user->id, $moodlereg)) {
                echo html_writer::tag('li', fullname($user).
                                            ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                      array('class' => 'registered'));
            } else {
                echo html_writer::tag('li', fullname($user).
                                            ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                      array('class' => 'moodlereg'));
            }
        }
        echo html_writer::end_tag('ul');
    }

    echo $OUTPUT->heading(get_string('queue', 'grouptool'), 3, 'showmembersheading queue');
    // Get queue but exclude all who are just marked not queued!
    $queuesql = "SELECT $userfieldssql
                 FROM {grouptool_queued} queue
                     LEFT JOIN {user} usr ON queue.userid = usr.id
                 WHERE queue.agrpid = ?
                 ORDER BY timestamp ASC";
    if (!$queue = $DB->get_records_sql($queuesql, array($agrpid))) {
        echo html_writer::tag('div', get_string('nobody_queued', 'grouptool'), array('class' => 'queue'));
    } else {
        echo html_writer::start_tag('ol');
        foreach ($queue as $user) {
            echo html_writer::tag('li', fullname($user).
                                        ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                  array('class' => 'queue'));
        }
        echo html_writer::end_tag('ol');
    }
}

echo $OUTPUT->footer();