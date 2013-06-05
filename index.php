<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints a list of all grouptool instances in the given course (via id)
 *
 * @package    mod
 * @subpackage grouptool
 * @copyright  2012 Hager Philipp
 * @since      Moodle 2.2.1+ (Build: 20120127)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT);   // Course.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

add_to_log($course->id, 'grouptool', 'view all', 'index.php?id='.$course->id, '');

$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/grouptool/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
    notice(get_string('nogrouptools', 'grouptool'), new moodle_url('/course/view.php',
                                                                   array('id' => $course->id)));
}

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head  = array(get_string('week'), get_string('name'), get_string('info'),
                          get_string('moduleintro'));
    $table->align = array('center', 'left', 'left', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array(get_string('topic'), get_string('name'), get_string('info'),
                          get_string('moduleintro'));
    $table->align = array('center', 'left', 'left', 'left', 'left');
} else {
    $table->head  = array(get_string('name'), get_string('info'), get_string('moduleintro'));
    $table->align = array('left', 'left', 'left', 'left');
}

foreach ($grouptools as $grouptool) {

    // Just some info.
    $context = context_module::instance($grouptool->coursemodule, MUST_EXIST);

    $strgrouptool = get_string('grouptool', 'grouptool');
    $strduedate = get_string('duedate', 'grouptool');
    $strduedateno = get_string('duedateno', 'grouptool');

    $str = "";
    if (has_capability('mod/grouptool:register', $context)
        || has_capability('mod/grouptool:view_registrations', $context)) {
        $attrib = array('title'=>$strgrouptool, 'href'=>$CFG->wwwroot.'/mod/grouptool/view.php?id='.
                                                        $grouptool->coursemodule);
        if ($grouptool->visible) {
            $attrib['class']='dimmed';
        }
        list($colorclass, $unused) = grouptool_display_lateness(time(), $grouptool->timedue);

        $attr = array('class'=>'info');
        if ($grouptool->timeavailable > time()) {
            $str .= html_writer::tag('div', get_string('availabledate', 'grouptool').': '.
                    html_writer::tag('span', userdate($grouptool->timeavailable)),
                    $attr);
        }
        if ($grouptool->timedue) {
            $str .= html_writer::tag('div', $strduedate.': '.
                                            html_writer::tag('span', userdate($grouptool->timedue),
                                                             array('class'=>(($colorclass=='late') ?
                                                                            ' late' : ''))),
                                     $attr);
        } else {
            $str .= html_writer::tag('div', $strduedateno, $attr);
        }
    }
    $details = '';
    if (has_capability('mod/grouptool:register', $context)
        || has_capability('mod/grouptool:view_registrations', $context)) {
        // It's similar to the student mymoodle output!
        $instance = new grouptool($grouptool->coursemodule, $grouptool);
        $userstats = $instance->get_registration_stats($USER->id);
    }

    if (has_capability('mod/grouptool:register', $context)) {
        if ($grouptool->allow_reg) {
            if (count($userstats->registered)) {
                $temp_str = "";
                foreach ($userstats->registered as $registration) {
                    list($colorclass, $text) = grouptool_display_lateness($registration->timestamp,
                                                                          $grouptool->timedue);
                    if ($temp_str != "") {
                        $temp_str .= '; ';
                    }
                    $temp_str .= html_writer::tag('span', $registration->grpname.' '.$text);
                }
                if (($grouptool->allow_multiple &&
                        (count($userstats->registered) < $grouptool->choose_min))
                        || (!$grouptool->allow_multiple && !count($userstats->registered))) {
                    if ($grouptool->allow_multiple) {
                        $missing = ($grouptool->choose_min-count($userstats->registered));
                        $string_label = ($missing > 1) ? 'registrations_missing'
                                                       : 'registration_missing';
                    } else {
                        $missing = 1;
                        $string_label = 'registration_missing';
                    }
                    $details .= html_writer::tag('div',
                            html_writer::tag('div',
                                    get_string($string_label, 'grouptool', $missing),
                                    array('class'=>$colorclass)).' '.
                            get_string('registrations', 'grouptool').': '.$temp_str,
                            array('class'=>'registered'));
                } else {
                    $details .= html_writer::tag('div',
                            get_string('registrations', 'grouptool').': '.$temp_str,
                            array('class'=>'registered'));
                }
            } else {
                if ($grouptool->allow_multiple) {
                    $missing = ($grouptool->choose_min-count($userstats->registered));
                    $string_label = ($missing > 1) ? 'registrations_missing'
                                                   : 'registration_missing';
                } else {
                    $missing = 1;
                    $string_label = 'registration_missing';
                }
                $details .= html_writer::tag('div',
                        html_writer::tag('div',
                                get_string($string_label, 'grouptool', $missing),
                                array('class'=>$colorclass)).
                        get_string('registrations', 'grouptool').': '.
                        get_string('not_registered', 'grouptool'),
                        array('class'=>'registered'));
            }
            if (count($userstats->queued)) {
                $temp_str = "";
                foreach ($userstats->queued as $queue) {
                    list($colorclass, $text) = grouptool_display_lateness($queue->timestamp,
                                                                          $grouptool->timedue);
                    if ($temp_str != "") {
                        $temp_str .= ", ";
                    }
                    $temp_str .= html_writer::tag('span', $queue->grpname.' ('.$queue->rank.')',
                                                  array('class'=>$colorclass));
                }
                $details .= html_writer::tag('div', get_string('queues', 'grouptool').': '.
                        $temp_str, array('class'=>'queued'));
            }
        }
    }

    if (has_capability('mod/grouptool:view_registrations', $context) && $grouptool->allow_reg) {
        $details .= html_writer::tag('div', get_string('global_userstats', 'grouptool', $userstats),
                array('class'=>'userstats'));

    }


    if (($grouptool->allow_reg && has_capability('mod/grouptool:view_registrations', $context))
            || has_capability('mod/grouptool:register', $context)) {
        $str .= html_writer::tag('div', $details, array('class'=>'details'));
        $str = html_writer::tag('div', $str, array('class'=>'grouptool overview'));
    }

    $info = $str;


    if (!$grouptool->visible) {
        $link = html_writer::link(
                new moodle_url('/mod/grouptool/view.php', array('id' => $grouptool->coursemodule)),
                format_string($grouptool->name, true),
                array('class' => 'dimmed'));
    } else {
        $link = html_writer::link(
                new moodle_url('/mod/grouptool/view.php', array('id' => $grouptool->coursemodule)),
                format_string($grouptool->name, true));
    }

    $intro = $grouptool->intro ? $grouptool->intro : "";

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = array($grouptool->section, $link, $info, $intro);
    } else {
        $table->data[] = array($link, $info, $intro);
    }
}

echo $OUTPUT->heading(get_string('modulenameplural', 'grouptool'), 2);
echo html_writer::table($table);
echo $OUTPUT->footer();
