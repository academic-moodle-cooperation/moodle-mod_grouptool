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
 * Unit tests for (some of) mod_grouptool's methods.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page!
}

// Make sure the code being tested is accessible.
global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/locallib.php'); // Include the code to test!

/**
 * This class contains the test cases for the formular validation.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouptool_registration_test extends advanced_testcase {
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

    /** @var stdClass $course New course created to hold the assignments */
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

    /**
     * Setup function - we will create a course and add an grouptool instance to it.
     */
    protected function setUp() {
        global $DB;

        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();
        $this->teachers = array();
        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            array_push($this->teachers, $this->getDataGenerator()->create_user());
        }

        $this->editingteachers = array();
        for ($i = 0; $i < self::DEFAULT_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->editingteachers, $this->getDataGenerator()->create_user());
        }

        $this->students = array();
        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            array_push($this->students, $this->getDataGenerator()->create_user());
        }

        $this->groups = array();
        for ($i = 0; $i < self::GROUP_COUNT; $i++) {
            array_push($this->groups, $this->getDataGenerator()->create_group(array('courseid' => $this->course->id)));
        }

        $this->timestamps = array();
        $this->starts = array();
        $this->stops = array();
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

        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        foreach ($this->teachers as $i => $teacher) {
            $this->getDataGenerator()->enrol_user($teacher->id,
                                                  $this->course->id,
                                                  $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        foreach ($this->editingteachers as $i => $editingteacher) {
            $this->getDataGenerator()->enrol_user($editingteacher->id,
                                                  $this->course->id,
                                                  $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        foreach ($this->students as $i => $student) {
            $this->getDataGenerator()->enrol_user($student->id,
                                                  $this->course->id,
                                                  $studentrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
        }

        $this->setAdminUser();
    }

    /**
     * For tests that make sense to use alot of data, create extra students/teachers.
     */
    protected function create_extra_users() {
        global $DB;
        $this->extrateachers = array();
        for ($i = 0; $i < self::EXTRA_TEACHER_COUNT; $i++) {
            array_push($this->extrateachers, $this->getDataGenerator()->create_user());
        }

        $this->extraeditingteachers = array();
        for ($i = 0; $i < self::EXTRA_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->extraeditingteachers, $this->getDataGenerator()->create_user());
        }

        $this->extrastudents = array();
        for ($i = 0; $i < self::EXTRA_STUDENT_COUNT; $i++) {
            array_push($this->extrastudents, $this->getDataGenerator()->create_user());
        }

        $this->extrasuspendedstudents = array();
        for ($i = 0; $i < self::EXTRA_SUSPENDED_COUNT; $i++) {
            array_push($this->extrasuspendedstudents, $this->getDataGenerator()->create_user());
        }

        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        foreach ($this->extrateachers as $i => $teacher) {
            $this->getDataGenerator()->enrol_user($teacher->id,
                                                  $this->course->id,
                                                  $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        foreach ($this->extraeditingteachers as $i => $editingteacher) {
            $this->getDataGenerator()->enrol_user($editingteacher->id,
                                                  $this->course->id,
                                                  $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        foreach ($this->extrastudents as $i => $student) {
            $this->getDataGenerator()->enrol_user($student->id,
                                                  $this->course->id,
                                                  $studentrole->id);
            if ($i < (self::EXTRA_STUDENT_COUNT / 2)) {
                groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
            }
        }

        foreach ($this->extrasuspendedstudents as $i => $suspendedstudent) {
            $this->getDataGenerator()->enrol_user($suspendedstudent->id,
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
     * @return testable_assign Testable wrapper around the assign class.
     */
    protected function create_instance($params=array()) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_grouptool');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('grouptool', $instance->id);
        $context = context_module::instance($cm->id);

        return new testable_grouptool($cm->id, $instance, $cm, $this->course);
    }

    /**
     * Tests basic creation of grouptool instance
     *
     * 2 Assertions
     */
    public function test_create_instance() {
        global $DB;

        $grouptool = $this->create_instance();
        $this->assertNotEmpty($grouptool);

        $this->assertTrue($DB->record_exists('grouptool_agrps', array('grouptoolid' => $grouptool->get_grouptool()->id)));
    }

    /**
     * Tests basic registration to a single group
     *
     * 9 Assertions
     */
    public function test_single() {
        global $DB;

        // Just a single registration per user, with groupsize = 2 and no queue!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_multiple' => 0,
                                                  'use_size' => 1,
                                                  'grpsize' => 2,
                                                  'use_queue' => 0,
                                                  'allow_unreg' => 0));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));

        $userid = $this->students[0]->id;
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        // Exercise SUT & Validate outcome!

        // Preview only!
        // Check if user 0 can be registrated in groups 0 and 1!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, true);
        $this->assertEquals(get_string('register_in_group', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, true);
        $this->assertEquals(get_string('register_in_group', 'grouptool', $message), $text);

        // Register user 0 in groups 0 and 1, first should work, second should fail with certain exception!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        try {
            $text = null;
            $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $this->assertInstanceOf('\mod_grouptool\local\exception\exceeduserreglimit', $e);
        }
        $this->assertEquals(null, $text);

        // Register user 1 in group 0 and user 2 in group 1!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[1]);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        // Register another user in the second group!
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[2]->id, false);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[2]);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Exceed group size in group 0 by trying to register user 3 as third member - first a preview, then the real try!
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[3]);
        $text = null;
        try {
            $text = $grouptool->register_in_agrp($agrpids[0], $this->students[3]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $this->assertInstanceOf('\mod_grouptool\local\exception\exceedgroupsize', $e);
        }
        $this->assertEquals('', $text);

        // Teardown fixture!
        $grouptool = null;
    }

    /**
     * Tests basic registration to a single group
     *
     * 6 Assertions
     */
    public function test_single_queue() {
        global $DB;

        // Just a single registration per user, with groupsize = 2 and queue!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_multiple' => 0,
                                                  'use_size' => 1,
                                                  'grpsize' => 2,
                                                  'use_queue' => 1,
                                                  'allow_unreg' => 0));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));

        $userid = $this->students[0]->id;
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[3]);
        $message->groupname = $agrps[$agrpids[0]]->name;
        // Prepare by registering users 0 and 1 in group 0 and queue user 2 in group 0!
        $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $grouptool->register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $grouptool->register_in_agrp($agrpids[0], $this->students[2]->id, false);

        // Exercise SUT & Validate outcome!

        // Preview only!
        // Check if user 3 can be queued in group 0!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[3]->id, true);
        $this->assertEquals(get_string('queue_in_group', 'grouptool', $message), $text);

        // Queue user 3 in group 0!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[3]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // User 3 can't be registered anymore anywhere else, due to user's registration limit!
        $text = null;
        try {
            $text = $grouptool->register_in_agrp($agrpids[1], $this->students[3]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $this->assertInstanceOf('\mod_grouptool\local\exception\exceeduserreglimit', $e);
        }
        $this->assertEquals('', $text);

        // User 4 can't be queued anymore, due to group's queue limit!
        $text = null;
        try {
            $text = $grouptool->register_in_agrp($agrpids[0], $this->students[4]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $this->assertInstanceOf('\mod_grouptool\local\exception\exceedgroupqueuelimit', $e);
        }
        $this->assertEquals('', $text);

        // Teardown fixture!
        $grouptool = null;
    }

    /**
     * Tests basic registration to multiple groups with queues
     *
     * 11 Assertions
     */
    public function test_multiple_queue() {
        global $DB;

        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_multiple' => 1,
                                                  'choose_min' => 2,
                                                  'choose_max' => 3,
                                                  'use_size' => 1,
                                                  'grpsize' => 2,
                                                  'use_queue' => 1,
                                                  'groups_queues_limit' => 1,
                                                  'users_queues_limit' => 1));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        // Exercise SUT & Validate outcome!

        // Allocate a place first and continue with registering user 0 in groups 0, 1 and 2!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $this->assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[2], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Repeat the procedure with user 1!
        $message->username = fullname($this->students[1]);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $this->assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[1]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[2], $this->students[1]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Mark user 2 for being queued in group 0 and register him in group 4!
        $text = null;
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[2]->id, false);
        $this->assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $text = null;
        $message->groupname = $agrps[$agrpids[4]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->register_in_agrp($agrpids[4], $this->students[2]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $text = null;

        // Now try to queue user 2 in group 1 too, exceeding user's queue limitation!
        try {
            $text = $grouptool->register_in_agrp($agrpids[1], $this->students[2]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $this->assertInstanceOf('\mod_grouptool\local\exception\exceeduserqueuelimit', $e);
        }
        $this->assertEquals('', $text);

        // Register user 2 in group 3 instead, which should work!
        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $grouptool->register_in_agrp($agrpids[3], $this->students[2]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Teardown fixture!
        $grouptool = null;
    }


    /**
     * Tests if no error will be wrongly displayed if everythings correct
     *
     * 13 Assertions
     */
    public function test_groupchange_single() {
        global $DB;

        // Single registration per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_unreg' => 1,
                                                  'allow_multiple' => 0,
                                                  'use_size' => 1,
                                                  'grpsize' => 1,
                                                  'use_queue' => 1,
                                                  'groups_queues_limit' => 1,
                                                  'users_queues_limit' => 1));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        // Prepare by registering users 0 and 1 in group 0!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[1]);
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // Exercise SUT!

        // Check possibility of and make a group change for students 0 and 1 to group 1!
        $message->username = fullname($this->students[0]);
        $grouptool->can_change_group($agrpids[1], $this->students[0]->id, $message);
        $this->assertTrue($grouptool->qualifies_for_groupchange($agrpids[1], $this->students[0]->id, $message));
        $message->username = fullname($this->students[1]);
        $grouptool->can_change_group($agrpids[1], $this->students[1]->id, $message);
        $this->assertTrue($grouptool->qualifies_for_groupchange($agrpids[1], $this->students[1]->id, $message));
        $message->username = fullname($this->students[2]);
        // Groupchange for student 2 fails, because he's neither registered nor queued or marked anywhere!
        try {
            $grouptool->can_change_group($agrpids[1], $this->students[2]->id, $message);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $text = $e->getMessage();
            $this->assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('groupchange_from_non_unique_reg', 'grouptool');
            $this->assertEquals($comptext, $text);
        }
        $this->assertFalse($grouptool->qualifies_for_groupchange($agrpids[1], $this->students[2]->id, $message));

        $message->groupname = $agrps[$agrpids[1]]->name;

        // Now check groupchanges by calling register_in_agrp() handling change detection automatically!
        $message->username = fullname($this->students[0]);
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, true);
        $this->assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[1]->id, true);
        $this->assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        // And move the users!
        $message->username = fullname($this->students[0]);
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[1]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        /* TODO: disallow unreg, registration present (marked, queued or registered),
         *       too many registrations, exceed group size, exceed user queue limit... */

        // Teardown fixture!
        $data = null;
        $grouptool = null;
    }

    /**
     * Tests if no error will be wrongly displayed if everythings correct
     *
     * 10 Assertions
     */
    public function test_groupchange_multiple() {
        global $DB;

        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_unreg' => 1,
                                                  'allow_multiple' => 1,
                                                  'choose_max' => 3,
                                                  'choose_min' => 2,
                                                  'use_size' => 1,
                                                  'grpsize' => 1,
                                                  'use_queue' => 1,
                                                  'groups_queues_limit' => 1,
                                                  'users_queues_limit' => 1));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        // Prepare by registering user 0 in groups 0 and 1 and user 2 in groups 2 and 3!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $this->assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->register_in_agrp($agrpids[2], $this->students[2]->id, false);
        $this->assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $grouptool->register_in_agrp($agrpids[3], $this->students[2]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Exercise SUT!

        // Try to change to group 4 with user 2, fails because we can't determine where to unreg the user!
        $message->groupname = $agrps[$agrpids[4]]->name;
        try {
            $grouptool->can_change_group($agrpids[4], $this->students[2]->id, $message);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $text = $e->getMessage();
            $this->assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('groupchange_from_non_unique_reg', 'grouptool');
            $this->assertEquals($comptext, $text);
        }
        $this->assertFalse($grouptool->qualifies_for_groupchange($agrpids[4], $this->students[2]->id, $message));

        // Now we give the method the param to know where to unregister user!
        $text = $grouptool->can_change_group($agrpids[4], $this->students[2]->id, $message, $agrpids[2]);
        $this->assertEquals(get_string('change_group_to', 'grouptool', $message), $text);
        $text = $grouptool->change_group($agrpids[4], $this->students[2]->id, $message, $agrpids[2]);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        /* TODO: disallow unreg, registration present (marked, queued or registered),
         *       too many registrations, exceed group size, exceed user queue limit... */

        // Teardown fixture!
        $data = null;
        $grouptool = null;
    }

    /**
     * Tests resolving of queues
     *
     * 11 Assertions
     */
    public function test_queue_resolving() {
        global $DB;

        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance(array('allow_reg' => 1,
                                                  'allow_multiple' => 1,
                                                  'choose_min' => 1,
                                                  'choose_max' => 3,
                                                  'use_size' => 1,
                                                  'grpsize' => 1,
                                                  'use_queue' => 1,
                                                  'groups_queues_limit' => 2,
                                                  'users_queues_limit' => 1));
        $DB->set_field('grouptool_agrps', 'active', 1, array('grouptoolid' => $grouptool->get_grouptool()->id));
        // Get all active groups indexed by active group ID!
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $agrpids = array_keys($agrps);
        $message = new stdClass();
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[0]]->name;

        // Exercise SUT & Validate outcome!

        // Register the users and queue them! Users 0,1,2 in group 0 and users 3,4,5 in group 1!
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[1]);
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[2]);
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[0], $this->students[2]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[3]);
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[3]->id, false);
        $this->assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[4]);
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[4]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[5]);
        $text = null;
        $text = $grouptool->register_in_agrp($agrpids[1], $this->students[5]->id, false);
        $this->assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // Now resolve queues - preview first!
        list($error, $message) = $grouptool->resolve_queues(true);
        if ($error) {
            echo $message;
        }
        $this->assertFalse($error);

        // Now do the work!
        list($error, $message) = $grouptool->resolve_queues();
        if ($error) {
            echo $message;
        }
        $this->assertFalse($error);

        // Now check if there are any entries left in the DB?!?
        $params = array($agrpids[0], $agrpids[1]);
        $this->assertFalse($DB->record_exists_select('grouptool_queued', "agrpid = ? OR agrpid = ?", $params));

        // Teardown fixture!
        $grouptool = null;
    }
}

/**
 * Test subclass that makes all the protected methods we want to test public.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_grouptool extends mod_grouptool {
    /**
     * Additional method to get grouptool record.
     *
     * @return stdClass Instances database record
     */
    public function get_grouptool() {
        return $this->grouptool;
    }

    /**
     * Override method to be available for testing!
     *
     * @param int $agrpid active-group-id to register/queue user to
     * @param int $userid user to register/queue
     * @param bool $previewonly optional don't act, just return a preview
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \mod_grouptool\local\exception\registration
     * @return string status message
     */
    public function register_in_agrp($agrpid, $userid=0, $previewonly=false) {
        try {
            return parent::register_in_agrp($agrpid, $userid, $previewonly);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
    }

    /**
     * Override method to be available for testing!
     *
     * @param int $agrpid active-group-id to unregister/unqueue user from
     * @param int $userid user to unregister/unqueue
     * @param bool $previewonly (optional) don't act, just return a preview
     * @throws \mod_grouptool\local\exception\notenoughregs If the user hasn't enough registrations!
     * @throws \mod_grouptool\local\exception\registration In any other case, where the user can't be unregistered!
     * @return string $message if everything went right
     */
    protected function unregister_from_agrp($agrpid, $userid=0, $previewonly=false) {
        try {
            return parent::unregister_from_agrp($agrpid, $userid, $previewonly);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
    }

    /**
     * Override method to be available for testing!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message (optional) cached data for the language strings
     * @return bool whether or not user qualifies for a group change
     */
    public function qualifies_for_groupchange($agrpid, $userid=0, $message=null) {
        try {
            return parent::qualifies_for_groupchange($agrpid, $userid, $message);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
    }

    /**
     * Override method to be available for testing!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message (optional) cached data for the language strings
     * @param int $oldagrpid (optional) ID of former active group
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @return array with 'boolean' if queued or not and 'string' status message
     */
    public function can_change_group($agrpid, $userid=0, $message=null, $oldagrpid = null) {
        try {
            return parent::can_change_group($agrpid, $userid, $message, $oldagrpid);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
    }

    /**
     * Override method to be available for testing!
     *
     * @param int $agrpid ID of active group to change to
     * @param int $userid (optional) ID of user to change group for or null ($USER->id is used).
     * @param stdClass $message (optional) prepared message object containing username and groupname or null.
     * @param int $oldagrpid (optional) ID of former active group
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @return string success message
     */
    public function change_group($agrpid, $userid = null, $message = null, $oldagrpid = null) {
        try {
            return parent::change_group($agrpid, $userid, $message, $oldagrpid);
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
    }
}

