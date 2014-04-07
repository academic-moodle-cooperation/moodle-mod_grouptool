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
 * showmembers_ajax.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir .'/grouplib.php');

$agrpid = required_param('agrpid', PARAM_INT);
$contextid = required_param('contextid', PARAM_INT);
$lang   = optional_param('lang', 'en', PARAM_LANG);


// If session has expired and its an ajax request so we cant do a page redirect!
if (!isloggedin()) {
    $result = new stdClass();
    $result->error = get_string('sessionerroruser', 'error');
    echo json_encode($result);
    die();
}

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null; // Now we have a context object throw away the id from the user!
$PAGE->set_context($context);
$PAGE->set_url('/mod/grouptool/showmembers_ajax.php', array('contextid' => $context->id,
                                                            'agrpid'    => $agrpid));

$group = $DB->get_record_sql('SELECT grp.id as grpid, grp.name as grpname, grp.courseid as courseid,
                                     agrp.id as agrpid, agrp.grpsize as size,
                                     agrp.grouptoolid as grouptoolid
                              FROM {grouptool_agrps} AS agrp
                                LEFT JOIN {groups} AS grp ON agrp.groupid = grp.id
                              WHERE agrp.id = ?', array($agrpid), MUST_EXIST);
$grouptool = $DB->get_record('grouptool', array('id' => $group->grouptoolid), '*', MUST_EXIST);

$text = '';
if (!has_capability('mod/grouptool:view_registrations', $context)
    && !$grouptool->show_members) {
    $text .= html_writer::tag('div', get_string('not_allowed_to_show_members', 'grouptool'),
                              array('class' => 'reg'));
} else {
    $text .= $OUTPUT->heading(get_string('registrations', 'grouptool'), 3, 'showmembersheading');
    $moodlereg = groups_get_members($group->grpid, 'u.id');

    $regsql = "SELECT reg.userid as id, user.firstname as firstname, user.lastname as lastname,
                      user.idnumber as idnumber
               FROM {grouptool_registered} as reg
                   LEFT JOIN {user} as user ON reg.userid = user.id
               WHERE reg.agrpid = ?
               ORDER BY timestamp ASC";
    if (!$regs = $DB->get_records_sql($regsql, array($agrpid))) {
        $text .= html_writer::tag('div', get_string('no_registrations', 'grouptool'),
                                  array('class' => 'reg'));
    } else {
        $text .= html_writer::start_tag('ul');
        foreach ($regs as $user) {
            if (!in_array($user->id, $moodlereg)) {
                $text .= html_writer::tag('li', fullname($user).
                                                ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                          array('class' => 'registered'));
            } else {
                $text .= html_writer::tag('li', fullname($user).
                                                ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                          array('class' => 'moodlereg'));
            }
        }
        $text .= html_writer::end_tag('ul');
    }

    $text .= $OUTPUT->heading(get_string('queue', 'grouptool'), 3, 'showmembersheading queue');
    $queuesql = "SELECT queue.userid as id, user.firstname as firstname, user.lastname as lastname,
                        user.idnumber as idnumber
                 FROM {grouptool_queued} as queue
                     LEFT JOIN {user} as user ON queue.userid = user.id
                 WHERE queue.agrpid = ?
                 ORDER BY timestamp ASC";
    if (!$queue = $DB->get_records_sql($queuesql, array($agrpid))) {
        $text .= html_writer::tag('div', get_string('nobody_queued', 'grouptool'),
                                  array('class' => 'queue'));
    } else {
        $text .= html_writer::start_tag('ol');
        foreach ($queue as $user) {
            $text .= html_writer::tag('li', fullname($user).
                                            ' ('.(($user->idnumber == "") ? '-' : $user->idnumber).')',
                                      array('class' => 'queue'));
        }
        $text .= html_writer::end_tag('ol');
    }
}
$result = new stdClass();
$result->heading = $group->grpname;
$result->text = $text;
echo json_encode($result);
