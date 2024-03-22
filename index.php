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
 * Displays a list of mod_grouptool-instances in a specific course
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT);   // Course.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

/* TRIGGER THE VIEW ALL EVENT */
$event = \mod_grouptool\event\course_module_instance_list_viewed::create([
    'context' => context_course::instance($course->id),
]);
$event->trigger();
/* END OF VIEW ALL EVENT */

$coursecontext = context_course::instance($course->id);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/grouptool/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
    notice(get_string('nogrouptools', 'grouptool'), new moodle_url('/course/view.php',
                                                                   ['id' => $course->id]));
}

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head  = [
            get_string('week'), get_string('name'), get_string('info'),
            get_string('moduleintro'),
    ];
    $table->align = ['center', 'left', 'left', 'left'];
} else if ($course->format == 'topics') {
    $table->head  = [
            get_string('topic'), get_string('name'), get_string('info'),
            get_string('moduleintro'),
    ];
    $table->align = ['center', 'left', 'left', 'left', 'left'];
} else {
    $table->head  = [get_string('name'), get_string('info'), get_string('moduleintro')];
    $table->align = ['left', 'left', 'left', 'left'];
}

foreach ($grouptools as $grouptool) {

    // Just some info.
    $context = context_module::instance($grouptool->coursemodule, MUST_EXIST);

    $strgrouptool = get_string('grouptool', 'grouptool');
    $strduedate = get_string('duedate', 'grouptool');
    $strduedateno = get_string('duedateno', 'grouptool');

    $str = "";
    if (has_capability('mod/grouptool:register', $context)
        || has_capability('mod/grouptool:view_regs_course_view', $context)
        || has_capability('mod/grouptool:view_regs_group_view', $context)) {
        $attrib = [
                'title' => $strgrouptool,
                'href'  => $CFG->wwwroot.'/mod/grouptool/view.php?id='.$grouptool->coursemodule,
        ];
        if ($grouptool->visible) {
            $attrib['class'] = 'dimmed';
        }
        list($colorclass, $unused) = grouptool_display_lateness(time(), $grouptool->timedue);

        $attr = ['class' => 'info'];
        if ($grouptool->timeavailable > time()) {
            $str .= html_writer::tag('div', get_string('availabledate', 'grouptool').': '.
                    html_writer::tag('span', userdate($grouptool->timeavailable)),
                    $attr);
        }
        if ($grouptool->timedue) {
            $str .= html_writer::tag('div', $strduedate.': '.
                                            html_writer::tag('span', userdate($grouptool->timedue),
                                                             ['class' => (($colorclass == 'late') ? ' late' : '')]),
                                     $attr);
        } else {
            $str .= html_writer::tag('div', $strduedateno, $attr);
        }
    }

    $details = grouptool_get_user_reg_details($grouptool, $context);

    if (($grouptool->allow_reg
            && (has_capability('mod/grouptool:view_regs_group_view', $context)
            || has_capability('mod/grouptool:view_regs_course_view', $context)))
        || has_capability('mod/grouptool:register', $context)) {
        $str = html_writer::tag('div', $str.$details, ['class' => 'grouptool overview']);
    }

    $info = $str;

    if (!$grouptool->visible) {
        $link = html_writer::link(
                new moodle_url('/mod/grouptool/view.php', ['id' => $grouptool->coursemodule]),
                format_string($grouptool->name, true),
                ['class' => 'dimmed']);
    } else {
        $link = html_writer::link(
                new moodle_url('/mod/grouptool/view.php', ['id' => $grouptool->coursemodule]),
                format_string($grouptool->name, true));
    }

    if ($grouptool->alwaysshowdescription || (time() > $grouptool->timeavailable)) {
        $intro = $grouptool->intro ? $grouptool->intro : "";
    } else {
        $intro = '';
    }

    if ($course->format == 'weeks' || $course->format == 'topics') {
        $table->data[] = [$grouptool->section, $link, $info, $intro];
    } else {
        $table->data[] = [$link, $info, $intro];
    }
}

echo $OUTPUT->heading(get_string('modulenameplural', 'grouptool'), 2);
echo html_writer::table($table);
echo $OUTPUT->footer();
