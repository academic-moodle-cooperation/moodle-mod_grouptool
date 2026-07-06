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

namespace mod_grouptool\local\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use coding_exception;
use context_module;
use dml_exception;
use mod_grouptool\domain\grouptool_data_object;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\local\model\group_manager;
use mod_grouptool\local\model\permission_manager;
use mod_grouptool\local\model\queue_manager;
use mod_grouptool\local\model\registration_manager;
use mod_grouptool_generator;
use moodle_exception;
use stdClass;

global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Base class with common logic for some unit tests.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Anne Kreppenhofer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends advanced_testcase {
    /** Default number of students to create. */
    protected const int DEFAULT_STUDENT_COUNT = 10;

    /** Default number of teachers to create. */
    protected const int DEFAULT_TEACHER_COUNT = 2;

    /** Default number of editing teachers to create. */
    protected const int DEFAULT_EDITING_TEACHER_COUNT = 2;

    /** Number of timestamps to create. */
    protected const int DEFAULT_TIMESTAMP_COUNT = 6;

    /** Optional extra number of students to create. */
    protected const int EXTRA_STUDENT_COUNT = 40;

    /** Optional number of suspended students. */
    protected const int EXTRA_SUSPENDED_COUNT = 10;

    /** Optional extra number of teachers to create. */
    protected const int EXTRA_TEACHER_COUNT = 5;

    /** Optional extra number of editing teachers to create. */
    protected const int EXTRA_EDITING_TEACHER_COUNT = 5;

    /** Number of groups to create. */
    protected const int GROUP_COUNT = 10;

    /** @var stdClass Course used by the tests. */
    protected stdClass $course;

    /** @var stdClass[] Teachers in the course. */
    protected array $teachers = [];

    /** @var stdClass[] Editing teachers in the course. */
    protected array $editingteachers = [];

    /** @var stdClass[] Students in the course. */
    protected array $students = [];

    /** @var stdClass[] Extra teachers in the course. */
    protected array $extrateachers = [];

    /** @var stdClass[] Extra editing teachers in the course. */
    protected array $extraeditingteachers = [];

    /** @var stdClass[] Extra students in the course. */
    protected array $extrastudents = [];

    /** @var stdClass[] Extra suspended students in the course. */
    protected array $extrasuspendedstudents = [];

    /** @var stdClass[] Groups in the course. */
    protected array $groups = [];

    /** @var int[] Test timestamps. */
    protected array $timestamps = [];

    /** @var int[] Test start timestamps. */
    protected array $starts = [];

    /** @var int[] Test stop timestamps. */
    protected array $stops = [];

    /**
     * Creates a course, users and Moodle groups for the tests.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $this->course = self::getDataGenerator()->create_course();

        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            $this->teachers[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::DEFAULT_EDITING_TEACHER_COUNT; $i++) {
            $this->editingteachers[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            $this->students[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::GROUP_COUNT; $i++) {
            $this->groups[] = self::getDataGenerator()->create_group([
                'courseid' => $this->course->id,
            ]);
        }

        for ($i = 0; $i < self::DEFAULT_TIMESTAMP_COUNT; $i++) {
            $hour = rand(0, 23);
            $minute = rand(0, 60);
            $second = rand(0, 60);
            $month = rand(1, 12);
            $day = rand(1, 28);
            $year = rand(1980, (int)date('Y'));
            $duration = rand(1, 60);

            $this->timestamps[] = mktime(0, 0, 0, $month, $day, $year);
            $this->starts[] = mktime($hour, $minute, $second, $month, $day, $year);
            $this->stops[] = mktime($hour, $minute + (5 * $duration), $second, $month, $day, $year);
        }

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        foreach ($this->teachers as $i => $teacher) {
            self::getDataGenerator()->enrol_user(
                $teacher->id,
                $this->course->id,
                $teacherrole->id
            );
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        foreach ($this->editingteachers as $i => $editingteacher) {
            self::getDataGenerator()->enrol_user(
                $editingteacher->id,
                $this->course->id,
                $editingteacherrole->id
            );
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        foreach ($this->students as $i => $student) {
            self::getDataGenerator()->enrol_user(
                $student->id,
                $this->course->id,
                $studentrole->id
            );
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
        }

        self::setAdminUser();
    }

    /**
     * Creates additional users for larger tests.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function create_extra_users(): void {
        global $DB;

        for ($i = 0; $i < self::EXTRA_TEACHER_COUNT; $i++) {
            $this->extrateachers[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::EXTRA_EDITING_TEACHER_COUNT; $i++) {
            $this->extraeditingteachers[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::EXTRA_STUDENT_COUNT; $i++) {
            $this->extrastudents[] = self::getDataGenerator()->create_user();
        }

        for ($i = 0; $i < self::EXTRA_SUSPENDED_COUNT; $i++) {
            $this->extrasuspendedstudents[] = self::getDataGenerator()->create_user();
        }

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        foreach ($this->extrateachers as $i => $teacher) {
            self::getDataGenerator()->enrol_user(
                $teacher->id,
                $this->course->id,
                $teacherrole->id
            );
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        foreach ($this->extraeditingteachers as $i => $editingteacher) {
            self::getDataGenerator()->enrol_user(
                $editingteacher->id,
                $this->course->id,
                $editingteacherrole->id
            );
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        foreach ($this->extrastudents as $i => $student) {
            self::getDataGenerator()->enrol_user(
                $student->id,
                $this->course->id,
                $studentrole->id
            );

            if ($i < (self::EXTRA_STUDENT_COUNT / 2)) {
                groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
            }
        }

        foreach ($this->extrasuspendedstudents as $i => $student) {
            self::getDataGenerator()->enrol_user(
                $student->id,
                $this->course->id,
                $studentrole->id,
                'manual',
                0,
                0,
                ENROL_USER_SUSPENDED
            );

            if ($i < (self::EXTRA_SUSPENDED_COUNT / 2)) {
                groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
            }
        }
    }

    /**
     * Creates a grouptool instance object for tests.
     *
     * @param array $params Instance parameters.
     * @return grouptool_instance Grouptool instance.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function create_instance(array $params = []): grouptool_instance {
        global $DB;

        /** @var mod_grouptool_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('mod_grouptool');

        $params['course'] = $this->course->id;
        $instance = new grouptool_data_object($generator->create_instance($params));
        $cm = get_coursemodule_from_instance('grouptool', $instance->id);

        $DB->set_field('grouptool_agrps', 'active', 1, [
            'grouptoolid' => $instance->id,
        ]);

        return new grouptool_instance(
            $cm->id,
            $instance,
            $cm,
            $this->course,
            context_module::instance($cm->id)
        );
    }

    /**
     * Creates a group manager for a grouptool instance.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @return group_manager The group manager.
     */
    protected function create_group_manager(grouptool_instance $grouptool): group_manager {
        return new group_manager(
            $grouptool->get_cm()->id,
            $grouptool->get_grouptool(),
            $grouptool->get_cm(),
            $grouptool->get_course(),
            $grouptool->get_context()
        );
    }

    /**
     * Creates a registration manager for a grouptool instance.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @return registration_manager The registration manager.
     */
    protected function create_registration_manager(grouptool_instance $grouptool): registration_manager {
        return new registration_manager(
            $grouptool->get_cm()->id,
            $grouptool->get_grouptool(),
            $grouptool->get_cm(),
            $grouptool->get_course(),
            $grouptool->get_context()
        );
    }

    /**
     * Creates a queue manager for a grouptool instance.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @return queue_manager The queue manager.
     */
    protected function create_queue_manager(grouptool_instance $grouptool): queue_manager {
        return new queue_manager(
            $grouptool->get_cm()->id,
            $grouptool->get_grouptool(),
            $grouptool->get_cm(),
            $grouptool->get_course(),
            $grouptool->get_context()
        );
    }

    /**
     * Creates a permission manager for a grouptool instance.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @return permission_manager The permission manager.
     */
    protected function create_permission_manager(grouptool_instance $grouptool): permission_manager {
        return new permission_manager(
            $grouptool->get_cm()->id,
            $grouptool->get_grouptool(),
            $grouptool->get_cm(),
            $grouptool->get_course(),
            $grouptool->get_context()
        );
    }

    /**
     * Gets all active groups indexed by active group id and prepares a message object.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @return array{0: array, 1: array, 2: stdClass} Agrps, agrp ids and message object.
     * @throws dml_exception
     */
    protected function get_agrps_and_prepare_message(grouptool_instance $grouptool): array {
        $groupmanager = $this->create_group_manager($grouptool);

        $agrps = $groupmanager->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);

        $message = new stdClass();
        $message->username = fullname($this->students[0]);

        if (!empty($agrpids)) {
            $message->groupname = $agrps[$agrpids[0]]->name;
        } else {
            $message->groupname = '';
        }

        return [
            0 => $agrps,
            1 => $agrpids,
            2 => $message,
        ];
    }

    /**
     * Inserts one queue entry for a user into an active group.
     *
     * @param int $agrpid The active group id.
     * @param int $userid The user id.
     * @return int Inserted queue record id.
     * @throws dml_exception
     */
    protected function insert_queue_entry(int $agrpid, int $userid): int {
        global $DB, $USER;

        $queue = (object)[
            'agrpid' => $agrpid,
            'userid' => $userid,
            'timestamp' => time() - 100,
            'modified_by' => (int)$USER->id,
        ];

        return (int)$DB->insert_record('grouptool_queued', $queue);
    }
}
