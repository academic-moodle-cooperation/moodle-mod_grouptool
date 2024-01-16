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
 * Base class with common logic for some unit tests.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local\tests;

use advanced_testcase;
use stdClass;
use mod_grouptool_generator;
use coding_exception;
use dml_exception;
use moodle_exception;
use required_capability_exception;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page!
}

// Make sure the code being tested is accessible.
global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/locallib.php'); // Include the code to test!

/**
 * This base class contains common logic for tests.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends advanced_testcase {
    /** Default number of students to create */
    const DEFAULT_STUDENT_COUNT = 10;
    /** Default number of teachers to create */
    const DEFAULT_TEACHER_COUNT = 2;
    /** Default number of editing teachers to create */
    const DEFAULT_EDITING_TEACHER_COUNT = 2;
    /** Number of timestamps to create */
    const DEFAULT_TIMESTAMP_COUNT = 6;
    /** Optional extra number of students to create */
    const EXTRA_STUDENT_COUNT = 40;
    /** Optional number of suspended students */
    const EXTRA_SUSPENDED_COUNT = 10;
    /** Optional extra number of teachers to create */
    const EXTRA_TEACHER_COUNT = 5;
    /** Optional extra number of editing teachers to create */
    const EXTRA_EDITING_TEACHER_COUNT = 5;
    /** Number of groups to create */
    const GROUP_COUNT = 10;

    /** @var stdClass $course New course created to hold the grouptools */
    protected $course = null;

    /** @var array $teachers List of DEFAULT_TEACHER_COUNT teachers in the course*/
    protected $teachers = null;

    /** @var array $editingteachers List of DEFAULT_EDITING_TEACHER_COUNT editing teachers in the course */
    protected $editingteachers = null;

    /** @var array $students List of DEFAULT_STUDENT_COUNT students in the course*/
    protected $students = null;

    /** @var array $extrateachers List of EXTRA_TEACHER_COUNT teachers in the course*/
    protected $extrateachers = null;

    /** @var array $extraeditingteachers List of EXTRA_EDITING_TEACHER_COUNT editing teachers in the course*/
    protected $extraeditingteachers = null;

    /** @var array $extrastudents List of EXTRA_STUDENT_COUNT students in the course*/
    protected $extrastudents = null;

    /** @var array $extrasuspendedstudents List of EXTRA_SUSPENDED_COUNT students in the course*/
    protected $extrasuspendedstudents = null;

    /** @var array $groups List of 10 groups in the course */
    protected $groups = null;

    /** @var array $timestamps List of 10 different timestamps */
    protected $timestamps = null;

    /** @var array $starts List of starting timestamps */
    protected $starts = null;

    /** @var array $stops List of stopping timestamps */
    protected $stops = null;

    /**
     * Setup function - we will create a course and add an grouptool instance to it.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function setUp():void {
        global $DB;

        $this->resetAfterTest(true);

        $this->course = self::getDataGenerator()->create_course();
        $this->teachers = [];
        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            array_push($this->teachers, self::getDataGenerator()->create_user());
        }

        $this->editingteachers = [];
        for ($i = 0; $i < self::DEFAULT_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->editingteachers, self::getDataGenerator()->create_user());
        }

        $this->students = [];
        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            array_push($this->students, self::getDataGenerator()->create_user());
        }

        $this->groups = [];
        for ($i = 0; $i < self::GROUP_COUNT; $i++) {
            array_push($this->groups, self::getDataGenerator()->create_group(['courseid' => $this->course->id]));
        }

        $this->timestamps = [];
        $this->starts = [];
        $this->stops = [];
        for ($i = 0; $i < self::DEFAULT_TIMESTAMP_COUNT; $i++) {
            $hour = rand(0, 23);
            $minute = rand(0, 60);
            $second = rand(0, 60);
            $month = rand(1, 12);
            $day = rand(0, 31);
            $year = rand(1980, date('Y'));
            // Steps of 5 minutes from 5 minute duration to 5 hours!
            $dur = rand(1, 60);
            array_push($this->timestamps, mktime(0, 0, 0, $month, $day, $year));
            array_push($this->starts, mktime($hour, $minute, $second, $month, $day, $year));
            array_push($this->stops, mktime($hour, $minute + (5 * $dur), $second, $month, $day, $year));
        }

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        foreach ($this->teachers as $i => $teacher) {
            self::getDataGenerator()->enrol_user($teacher->id,
                                                  $this->course->id,
                                                  $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        foreach ($this->editingteachers as $i => $editingteacher) {
            self::getDataGenerator()->enrol_user($editingteacher->id,
                                                  $this->course->id,
                                                  $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        foreach ($this->students as $i => $student) {
            self::getDataGenerator()->enrol_user($student->id,
                                                  $this->course->id,
                                                  $studentrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
        }

        self::setAdminUser();
    }

    /**
     * For tests that make sense to use a lot of data, create extra students/teachers.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function create_extra_users() {
        global $DB;
        $this->extrateachers = [];
        for ($i = 0; $i < self::EXTRA_TEACHER_COUNT; $i++) {
            array_push($this->extrateachers, self::getDataGenerator()->create_user());
        }

        $this->extraeditingteachers = [];
        for ($i = 0; $i < self::EXTRA_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->extraeditingteachers, self::getDataGenerator()->create_user());
        }

        $this->extrastudents = [];
        for ($i = 0; $i < self::EXTRA_STUDENT_COUNT; $i++) {
            array_push($this->extrastudents, self::getDataGenerator()->create_user());
        }

        $this->extrasuspendedstudents = [];
        for ($i = 0; $i < self::EXTRA_SUSPENDED_COUNT; $i++) {
            array_push($this->extrasuspendedstudents, self::getDataGenerator()->create_user());
        }

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        foreach ($this->extrateachers as $i => $teacher) {
            self::getDataGenerator()->enrol_user($teacher->id,
                                                  $this->course->id,
                                                  $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        foreach ($this->extraeditingteachers as $i => $editingteacher) {
            self::getDataGenerator()->enrol_user($editingteacher->id,
                                                  $this->course->id,
                                                  $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        foreach ($this->extrastudents as $i => $student) {
            self::getDataGenerator()->enrol_user($student->id,
                                                  $this->course->id,
                                                  $studentrole->id);
            if ($i < (self::EXTRA_STUDENT_COUNT / 2)) {
                groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
            }
        }

        foreach ($this->extrasuspendedstudents as $i => $suspendedstudent) {
            self::getDataGenerator()->enrol_user($suspendedstudent->id,
                                                  $this->course->id,
                                                  $studentrole->id, 'manual', 0, 0, ENROL_USER_SUSPENDED);
            if ($i < (self::EXTRA_SUSPENDED_COUNT / 2)) {
                groups_add_member($this->groups[$i % self::GROUP_COUNT], $suspendedstudent);
            }
        }
    }

    /**
     * Convenience function to create a testable instance of an assignment acc.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return grouptool Testable wrapper around the assign class.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function create_instance($params=[]) {
        global $DB;

        /** @var mod_grouptool_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_grouptool');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('grouptool', $instance->id);

        $DB->set_field('grouptool_agrps', 'active', 1, ['grouptoolid' => $instance->id]);

        return new grouptool($cm->id, $instance, $cm, $this->course);
    }

    /**
     * Get's all active groups indexed by active group ID as well as agrpids and prepares a message object!
     *
     * @param grouptool $grouptool The grouptool instance to fetch data for
     * @return array agrps, agrpids, message-object
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function get_agrps_and_prepare_message($grouptool) {
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        return [
                0 => $agrps,
                1 => $agrpids,
                2 => $message,
        ];
    }
}

