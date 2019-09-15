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
class grouptool_registration_test extends \mod_grouptool\local\tests\base {
    /*
     * The base test class already contains a setUp-method setting up a course including users and groups.
     */


    /**
     * Tests basic creation of grouptool instance
     *
     * 2 Assertions
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_instance() {
        global $DB;

        $grouptool = $this->create_instance();
        self::assertNotEmpty($grouptool);

        self::assertTrue($DB->record_exists('grouptool_agrps', ['grouptoolid' => $grouptool->get_grouptool()->id]));
    }

    /**
     * Tests basic registration to a single group
     *
     * 9 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_single() {
        // Just a single registration per user, with groupsize = 2 and no queue!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_multiple' => 0,
                'use_size' => 1,
                'grpsize' => 2,
                'use_queue' => 0,
                'allow_unreg' => 0
        ]);

        // Get all active groups indexed by active group ID!
        list($agrps, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);

        // Exercise SUT & Validate outcome!

        // Preview only!
        // Check if user 0 can be registrated in groups 0 and 1!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, true);
        self::assertEquals(get_string('register_in_group', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, true);
        self::assertEquals(get_string('register_in_group', 'grouptool', $message), $text);

        // Register user 0 in groups 0 and 1, first should work, second should fail with certain exception!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $message->groupname = $agrps[$agrpids[0]]->name;
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        try {
            $text = null;
            $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            self::assertInstanceOf('\mod_grouptool\local\exception\exceeduserreglimit', $e);
        }
        self::assertEquals(null, $text);

        // Register user 1 in group 0 and user 2 in group 1!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[1]);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        // Register another user in the second group!
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[2]->id, false);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[2]);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Exceed group size in group 0 by trying to register user 3 as third member - first a preview, then the real try!
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[3]);
        $text = null;
        try {
            $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[3]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            self::assertInstanceOf('\mod_grouptool\local\exception\exceedgroupsize', $e);
        }
        self::assertEquals('', $text);

        // Teardown fixture!
        $grouptool = null;
    }

    /**
     * Tests basic registration to a single group
     *
     * 6 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_single_queue() {
        // Just a single registration per user, with groupsize = 2 and queue!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_multiple' => 0,
                'use_size' => 1,
                'grpsize' => 2,
                'use_queue' => 1,
                'allow_unreg' => 0
        ]);

        list(, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);
        $message->username = fullname($this->students[3]);

        // Prepare by registering users 0 and 1 in group 0 and queue user 2 in group 0!
        $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        $grouptool->testable_register_in_agrp($agrpids[0], $this->students[1]->id, false);
        $grouptool->testable_register_in_agrp($agrpids[0], $this->students[2]->id, false);

        // Exercise SUT & Validate outcome!

        // Preview only!
        // Check if user 3 can be queued in group 0!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[3]->id, true);
        self::assertEquals(get_string('queue_in_group', 'grouptool', $message), $text);

        // Queue user 3 in group 0!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[3]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // User 3 can't be registered anymore anywhere else, due to user's registration limit!
        $text = null;
        try {
            $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[3]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            self::assertInstanceOf('\mod_grouptool\local\exception\exceeduserreglimit', $e);
        }
        self::assertEquals('', $text);

        // User 4 can't be queued anymore, due to group's queue limit!
        $text = null;
        try {
            $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[4]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            self::assertInstanceOf('\mod_grouptool\local\exception\exceedgroupqueuelimit', $e);
        }
        self::assertEquals('', $text);

        // Teardown fixture!
        $grouptool = null;
    }

    /**
     * Tests basic registration to multiple groups with queues
     *
     * 11 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_multiple_queue() {
        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_multiple' => 1,
                'choose_min' => 2,
                'choose_max' => 3,
                'use_size' => 1,
                'grpsize' => 2,
                'use_queue' => 1,
                'groups_queues_limit' => 1,
                'users_queues_limit' => 1
        ]);
        list($agrps, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);

        // Exercise SUT & Validate outcome!
        // Allocate a place first and continue with registering user 0 in groups 0, 1 and 2!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[2], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Repeat the procedure with user 1!
        $message->username = fullname($this->students[1]);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[1]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[1]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[2], $this->students[1]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Mark user 2 for being queued in group 0 and register him in group 4!
        $text = null;
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[2]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $text = null;
        $message->groupname = $agrps[$agrpids[4]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->testable_register_in_agrp($agrpids[4], $this->students[2]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $text = null;

        // Now try to queue user 2 in group 1 too, exceeding user's queue limitation!
        try {
            $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[2]->id, false);
        } catch (\mod_grouptool\local\exception\registration $e) {
            self::assertInstanceOf('\mod_grouptool\local\exception\exceeduserqueuelimit', $e);
        }
        self::assertEquals('', $text);

        // Register user 2 in group 3 instead, which should work!
        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[3], $this->students[2]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Teardown fixture!
        $grouptool = null;
    }


    /**
     * Tests if no error will be wrongly displayed if everything's correct
     *
     * 13 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_groupchange_single() {
        // Single registration per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_unreg' => 1,
                'allow_multiple' => 0,
                'use_size' => 1,
                'grpsize' => 1,
                'use_queue' => 1,
                'groups_queues_limit' => 1,
                'users_queues_limit' => 1
        ]);
        // Get all active groups indexed by active group ID!
        list($agrps, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);

        // Prepare by registering users 0 and 1 in group 0!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[1]);
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[1]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // Exercise SUT!

        // Check possibility of and make a group change for students 0 and 1 to group 1!
        $message->username = fullname($this->students[0]);
        $grouptool->testable_can_change_group($agrpids[1], $this->students[0]->id, $message);
        self::assertTrue($grouptool->testable_qualifies_for_groupchange($agrpids[1], $this->students[0]->id));
        $message->username = fullname($this->students[1]);
        $grouptool->testable_can_change_group($agrpids[1], $this->students[1]->id, $message);
        self::assertTrue($grouptool->testable_qualifies_for_groupchange($agrpids[1], $this->students[1]->id));
        $message->username = fullname($this->students[2]);
        // Groupchange for student 2 fails, because he's neither registered nor queued or marked anywhere!
        try {
            $grouptool->testable_can_change_group($agrpids[1], $this->students[2]->id, $message);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('groupchange_from_non_unique_reg', 'grouptool');
            self::assertEquals($comptext, $text);
        }
        self::assertFalse($grouptool->testable_qualifies_for_groupchange($agrpids[1], $this->students[2]->id));

        $message->groupname = $agrps[$agrpids[1]]->name;

        // Now check groupchanges by calling register_in_agrp() handling change detection automatically!
        $message->username = fullname($this->students[0]);
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, true);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[1]->id, true);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        // And move the users!
        $message->username = fullname($this->students[0]);
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[1]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        /* TODO: disallow unreg, registration present (marked, queued or registered),
         *       too many registrations, exceed group size, exceed user queue limit... */

        // Teardown fixture!
        $data = null;
        $grouptool = null;
    }

    /**
     * Tests if no error will be wrongly displayed if everything's correct
     *
     * 10 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_groupchange_multiple() {
        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_unreg' => 1,
                'allow_multiple' => 1,
                'choose_max' => 3,
                'choose_min' => 2,
                'use_size' => 1,
                'grpsize' => 1,
                'use_queue' => 1,
                'groups_queues_limit' => 1,
                'users_queues_limit' => 1
        ]);
        list($agrps, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);

        // Prepare by registering user 0 in groups 0 and 1 and user 2 in groups 2 and 3!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[2]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->testable_register_in_agrp($agrpids[2], $this->students[2]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);
        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[3], $this->students[2]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // User 4 will be marked for reg in group 5!
        $message->groupname = $agrps[$agrpids[4]]->name;
        $message->username = fullname($this->students[3]);
        $text = $grouptool->testable_register_in_agrp($agrpids[4], $this->students[3]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        // User 4 will be registered in group 6!
        $message->groupname = $agrps[$agrpids[5]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[5], $this->students[3]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // User 5 will be marked for reg in group 5!
        $message->username = fullname($this->students[4]);
        $message->groupname = $agrps[$agrpids[4]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[4], $this->students[4]->id, false);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        // User 5 can't be marked twice for registration in group 5!
        $thrown = true;
        try {
            $grouptool->testable_register_in_agrp($agrpids[4], $this->students[4]->id, false);
        } catch (\mod_grouptool\local\exception\regpresent $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\regpresent', $e);
            $comptext = get_string('already_marked', 'grouptool', $message);
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);

        // User 5 will be queued in group 6!
        $message->username = fullname($this->students[4]);
        $message->groupname = $agrps[$agrpids[5]]->name;
        $text = $grouptool->testable_register_in_agrp($agrpids[5], $this->students[4]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // We are not allowed to queue user 1 for group 5 since queue is already full(user4 is reg and user5 is queued)!
        $thrown = false;
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[4]]->name;
        try {
            $grouptool->testable_register_in_agrp($agrpids[4], $this->students[0]->id, false);
        } catch (\mod_grouptool\local\exception\exceedgroupqueuelimit $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\exceedgroupqueuelimit', $e);
            $comptext = get_string('exceedgroupqueuelimit', 'grouptool', $message);
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);

        // Set group queue size to 2 to check if the correct exception is thrown!
        $grouptool->get_grouptool()->groups_queues_limit = 2;

        // We are not allowed to queue user 5 for group 5 since user 5 is already in queue!
        $thrown = false;
        $message->username = fullname($this->students[4]);
        try {
            $grouptool->testable_register_in_agrp($agrpids[4], $this->students[4]->id, false);
        } catch (\mod_grouptool\local\exception\regpresent $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\regpresent', $e);
            $comptext = get_string('already_queued', 'grouptool', $message);
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);
        $grouptool->get_grouptool()->groups_queues_limit = 1;

        // We are not allowed to register user 4 twice in group 5!
        $thrown = false;
        try {
            $message->username = fullname($this->students[3]);
            $grouptool->testable_register_in_agrp($agrpids[4], $this->students[3]->id, false);
        } catch (\mod_grouptool\local\exception\regpresent $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('already_registered', 'grouptool', $message);
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);

        // Exercise SUT!

        // Try to change to group 4 with user 2, fails because we can't determine where to unreg the user!
        $thrown = false;
        $message->groupname = $agrps[$agrpids[4]]->name;
        try {
            $grouptool->testable_can_change_group($agrpids[4], $this->students[2]->id, $message);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('groupchange_from_non_unique_reg', 'grouptool');
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);
        self::assertFalse($grouptool->testable_qualifies_for_groupchange($agrpids[4], $this->students[2]->id));

        // Now we give the method the param to know where to unregister user!
        $message->groupname = $agrps[$agrpids[6]]->name;
        $message->username = fullname($this->students[2]);
        $text = $grouptool->testable_can_change_group($agrpids[6], $this->students[2]->id, $message, $agrpids[2]);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);
        $text = $grouptool->testable_change_group($agrpids[6], $this->students[2]->id, $message, $agrpids[2]);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Disable unregistration in order to check if correct exception is thrown!
        $grouptool->get_grouptool()->allow_unreg = 0;

        // We can't change the group since unreg is disabled!
        $thrown = false;
        try {
            $grouptool->testable_can_change_group($agrpids[2], $this->students[2]->id, $message, $agrpids[2]);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $thrown = true;
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('unreg_not_allowed', 'grouptool');
            self::assertEquals($comptext, $text);
        }
        self::assertTrue($thrown);

        $grouptool->get_grouptool()->allow_unreg = 1;

        try {
            $grouptool->testable_can_change_group($agrpids[2], $this->students[2]->id, $message, $agrpids[2]);
        } catch (\mod_grouptool\local\exception\registration $e) {
            $text = $e->getMessage();
            self::assertInstanceOf('\mod_grouptool\local\exception\registration', $e);
            $comptext = get_string('unreg_not_allowed', 'grouptool');
            self::assertEquals($comptext, $text);
        }

        /* TODO: too many registrations, exceed group size, exceed user queue limit... */

        // Teardown fixture!
        $data = null;
        $grouptool = null;
    }

    /**
     * Tests resolving of queues
     *
     * 11 Assertions
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_queue_resolving() {
        global $DB;

        // Multiple registrations per user, with groupsize = 2 and queue (limited to 1 place per user and 1 place per group)!
        $grouptool = $this->create_instance([
                'allow_reg' => 1,
                'allow_multiple' => 1,
                'choose_min' => 1,
                'choose_max' => 3,
                'use_size' => 1,
                'grpsize' => 1,
                'use_queue' => 1,
                'groups_queues_limit' => 2,
                'users_queues_limit' => 1
        ]);
        list($agrps, $agrpids, $message) = $this->get_agrps_and_prepare_message($grouptool);

        // Exercise SUT & Validate outcome!

        // Register the users and queue them! Users 0,1,2 in group 0 and users 3,4,5 in group 1!
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[0]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[1]);
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[1]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[2]);
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[0], $this->students[2]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[3]);
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[3]->id, false);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[4]);
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[4]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);
        $message->username = fullname($this->students[5]);
        $text = null;
        $text = $grouptool->testable_register_in_agrp($agrpids[1], $this->students[5]->id, false);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        // Now resolve queues - preview first!
        list($error, $message) = $grouptool->resolve_queues(true);
        if ($error) {
            echo $message;
        }
        self::assertFalse($error);

        // Now do the work!
        list($error, $message) = $grouptool->resolve_queues();
        if ($error) {
            echo $message;
        }
        self::assertFalse($error);

        // Now check if there are any entries left in the DB?!?
        $params = [$agrpids[0], $agrpids[1]];
        self::assertFalse($DB->record_exists_select('grouptool_queued', "agrpid = ? OR agrpid = ?", $params));

        // Teardown fixture!
        $grouptool = null;
    }
}

