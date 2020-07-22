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
 * Unit Tests for mod/grouptool's privacy providers!
 *
 * @package    mod_grouptool
 * @copyright  2019 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @author Philipp Hager <philipp.hager@tuwien.ac.at> strongly based on mod_assign's privacy unit tests!
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local\tests;

use \mod_grouptool\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

/**
 * Unit Tests for mod/grouptool's privacy providers! TODO: finish these unit tests here!
 * @group mod_grouptool
 *
 * @copyright  2019 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @author Philipp Hager <philipp.hager@tuwien.ac.at> strongly based on mod_assign's privacy unit tests!
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_privacy_testcase extends base {
    /*
     * The base test class already contains a setUp-method setting up a course including users and groups.
     */

    /**
     * Test that getting the contexts for a user works.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $course1 = $this->course;
        $course2 = self::getDataGenerator()->create_course();

        $user1 = $this->students[0];
        self::getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        // Need a second user to create queue entries.
        $user2 = $this->students[1];;
        self::getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');

        // Create multiple grouptool instances.
        // Grouptool without queues.
        $gt1 = $this->create_instance([
                'course' => $course1,
                'use_queue' => 0,
                'use_size' => 0,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);
        // Grouptool with queues.
        $gt2 = $this->create_instance([
                'course' => $course1,
                'use_queue' => 1,
                'use_size' => 1,
                'grpsize' => 1,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);
        // Grouptool instance two in a different course that the user is not enrolled in.
        $gt3 = $this->create_instance([
                'course' => $course2,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);

        // The user will be in these contexts.
        $usercontextids = [
            $gt1->get_context()->id,
            $gt2->get_context()->id
        ];

        // Get all active groups indexed by active group ID!
        list(, $agrpids, ) = $this->get_agrps_and_prepare_message($gt1);
        list(, $agrpids2, ) = $this->get_agrps_and_prepare_message($gt2);
        list(, $agrpids3, ) = $this->get_agrps_and_prepare_message($gt3);

        self::setUser($user1);
        $gt1->testable_register_in_agrp($agrpids[0]);
        $gt2->testable_register_in_agrp($agrpids2[0]);
        $gt3->testable_register_in_agrp($agrpids3[0]);

        self::setUser($user2);
        $gt1->testable_register_in_agrp($agrpids[0]);
        $gt2->testable_register_in_agrp($agrpids2[0]);

        $contextlist = provider::get_contexts_for_userid($user2->id);
        self::assertEquals(count($usercontextids), count($contextlist->get_contextids()));
        // There should be no difference between the contexts.
        self::assertEmpty(array_diff($usercontextids, $contextlist->get_contextids()));
    }

    /**
     * Test returning a list of user IDs related to a context.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();

        $course = $this->course;

        // Registered in gt1 & gt2!
        $user1 = $this->students[0];
        // Registered in gt1 and queued in gt2!
        $user2 = $this->students[1];
        // Registered in gt1 & queued in gt2!
        $user3 = $this->students[2];
        // User 4 registers user3 in gt1.
        $user4 = $this->editingteachers[0];
        // Only queued in gt2!
        $user5 = $this->students[3];
        // This user has no entries and should not show up.
        $user6 = $this->students[4];

        $gt1 = $gt1 = $this->create_instance([
                'course' => $course,
                'use_queue' => 0,
                'use_size' => 0,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);
        $gt2 = $this->create_instance([
                'course' => $course,
                'use_queue' => 0,
                'use_size' => 0,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);

        $context = $gt1->get_context();
        $context2 = $gt2->get_context();

        // Get all active groups indexed by active group ID!
        list(, $agrpids, $message) = $this->get_agrps_and_prepare_message($gt1);
        list(, $agrpids2, ) = $this->get_agrps_and_prepare_message($gt2);

        self::setUser($user1);
        $gt1->testable_register_in_agrp($agrpids[0]);
        $gt2->testable_register_in_agrp($agrpids2[0]);

        self::setUser($user2);
        $gt1->testable_register_in_agrp($agrpids[0]);
        $gt2->testable_register_in_agrp($agrpids2[0]);

        self::setUser($user3);
        $gt2->testable_register_in_agrp($agrpids2[0]);

        self::setUser($user4);
        $gt1->testable_add_registration($agrpids[0], $user3->id, $message);

        self::setUser($user5);
        $gt2->testable_register_in_agrp($agrpids2[1]);

        $userlist = new \core_privacy\local\request\userlist($context, 'grouptool');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        self::assertTrue(in_array($user1->id, $userids));
        self::assertTrue(in_array($user2->id, $userids));
        self::assertTrue(in_array($user3->id, $userids));
        self::assertTrue(in_array($user4->id, $userids));
        self::assertFalse(in_array($user5->id, $userids));
        self::assertFalse(in_array($user6->id, $userids));

        $userlist2 = new \core_privacy\local\request\userlist($context2, 'grouptool');
        provider::get_users_in_context($userlist2);
        $userids2 = $userlist2->get_userids();
        self::assertTrue(in_array($user1->id, $userids2));
        self::assertTrue(in_array($user2->id, $userids2));
        self::assertTrue(in_array($user3->id, $userids2));
        self::assertFalse(in_array($user4->id, $userids2));
        self::assertTrue(in_array($user5->id, $userids2));
        self::assertFalse(in_array($user6->id, $userids2));
    }

    /**
     * Test that a student with multiple submissions and grades is returned with the correct data.
     */
    public function test_export_user_data_student() {
        // Stop here and mark this test as incomplete.
        self::markTestIncomplete(
                'This test has not been implemented yet.'
        );
    }

    /**
     * Tests the data returned for a teacher.
     */
    public function test_export_user_data_teacher() {
        // Stop here and mark this test as incomplete.
        self::markTestIncomplete(
                'This test has not been implemented yet.'
        );
    }

    /**
     * A test for deleting all user data for a given context.
     */
    public function test_delete_data_for_all_users_in_context() {
        // Stop here and mark this test as incomplete.
        self::markTestIncomplete(
                'This test has not been implemented yet.'
        );
    }

    /**
     * A test for deleting all user data for one user.
     */
    public function test_delete_data_for_user() {
        // Stop here and mark this test as incomplete.
        self::markTestIncomplete(
                'This test has not been implemented yet.'
        );
    }

    /**
     * A test for deleting all user data for a bunch of users.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_delete_data_for_users() {
        global $DB;

        $course1 = $this->course;
        $course2 = self::getDataGenerator()->create_course();

        $user1 = $this->students[0];
        self::getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        // Need a second user to create queue entries.
        $user2 = $this->students[1];
        self::getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');
        $user3 = $this->students[2];
        self::getDataGenerator()->enrol_user($user3->id, $course2->id, 'student');
        $user4 = $this->editingteachers[0];
        self::getDataGenerator()->enrol_user($user3->id, $course2->id, 'editingteacher');

        // Create multiple grouptool instances.
        // Grouptool without queues.
        $gt1 = $this->create_instance([
                'course' => $course1,
                'use_queue' => 0,
                'use_size' => 0,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);
        // Grouptool with queues.
        $gt2 = $this->create_instance([
                'course' => $course1,
                'use_queue' => 1,
                'use_size' => 1,
                'grpsize' => 1,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);
        // Grouptool instance three in a different course that the user is not enrolled in.
        $gt3 = $this->create_instance([
                'course' => $course2,
                'ifmemberadded' => 0,
                'ifmemberremoved' => 0,
                'ifgroupdeleted' => 0
        ]);

        // Get all active groups indexed by active group ID!
        list(, $agrpids, $message) = $this->get_agrps_and_prepare_message($gt1);
        list(, $agrpids2, ) = $this->get_agrps_and_prepare_message($gt2);
        list(, $agrpids3, $message3) = $this->get_agrps_and_prepare_message($gt3);

        self::setUser($user1);
        // All are registrations!
        $gt1->testable_register_in_agrp($agrpids[0]);
        $gt2->testable_register_in_agrp($agrpids2[0]);
        $gt3->testable_register_in_agrp($agrpids3[0]);

        self::setUser($user2);
        // This is a registration!
        $gt1->testable_register_in_agrp($agrpids[0]);
        // This is a queue entry!
        $gt2->testable_register_in_agrp($agrpids2[0]);

        self::setUser($user4);
        // Two registrations!
        $gt1->testable_add_registration($agrpids[0], $user3->id, $message);
        $gt1->testable_add_registration($agrpids[1], $user3->id, $message);
        // This one is a queue entry!
        $gt2->testable_add_queue_entry($agrpids2[0], $user3->id, $message);
        // These are registrations!
        $gt3->testable_add_registration($agrpids3[0], $user3->id, $message3);
        $gt3->testable_add_registration($agrpids3[1], $user3->id, $message3);

        // Check registration data is in place.
        $data = $DB->get_records('grouptool_registered');
        // We should have 3 entries for user 1 and one entry for user 2.
        self::assertCount(8, $data);
        $usercounts = [
            $user1->id => 0,
            $user2->id => 0,
            $user3->id => 0
        ];
        foreach ($data as $datum) {
            $usercounts[$datum->userid]++;
        }
        self::assertEquals(3, $usercounts[$user1->id]);
        self::assertEquals(1, $usercounts[$user2->id]);
        self::assertEquals(4, $usercounts[$user3->id]);

        $data = $DB->get_records('grouptool_queued');
        // We should only have one entry for user 2 and user 3 each.
        self::assertCount(2, $data);
        $usercounts = [
                $user2->id => 0,
                $user3->id => 0
        ];
        foreach ($data as $datum) {
            $usercounts[$datum->userid]++;
        }
        self::assertEquals(1, $usercounts[$user2->id]);
        self::assertEquals(1, $usercounts[$user3->id]);

        // Delete data for user 4 in gt1 and gt2!
        $userlist = new \core_privacy\local\request\approved_userlist($gt1->get_context(), 'grouptool', [$user4->id]);
        provider::delete_data_for_users($userlist);
        $userlist = new \core_privacy\local\request\approved_userlist($gt2->get_context(), 'grouptool', [$user4->id]);
        provider::delete_data_for_users($userlist);

        $data = $DB->get_records('grouptool_registered');
        // No change here!
        self::assertCount(8, $data);

        $data = $DB->get_records('grouptool_queued');
        // No change here!
        self::assertCount(2, $data);

        // Delete data for users 1 and 2 in gt1!
        $userlist = new \core_privacy\local\request\approved_userlist($gt1->get_context(), 'grouptool', [$user1->id, $user2->id]);
        provider::delete_data_for_users($userlist);

        $data = $DB->get_records('grouptool_registered');
        // 2 have to be gone!
        self::assertCount(6, $data);

        $data = $DB->get_records('grouptool_queued');
        // No change here!
        self::assertCount(2, $data);

        $userlist = new \core_privacy\local\request\approved_userlist($gt2->get_context(), 'grouptool', [$user3->id, $user4->id]);
        provider::delete_data_for_users($userlist);

        $data = $DB->get_records('grouptool_registered');
        // No change here!
        self::assertCount(6, $data);

        $data = $DB->get_records('grouptool_queued');
        // Only the entry for user 3 is gone!
        self::assertCount(1, $data);

        $userlist = new \core_privacy\local\request\approved_userlist($gt2->get_context(), 'grouptool', [$user1->id, $user2->id]);
        provider::delete_data_for_users($userlist);

        $data = $DB->get_records('grouptool_registered');
        // No change here!
        self::assertCount(5, $data);

        $data = $DB->get_records('grouptool_queued');
        // Only the entry for user 3 is gone!
        self::assertCount(0, $data);

        $userlist = new \core_privacy\local\request\approved_userlist($gt1->get_context(), 'grouptool',
                [$user1->id, $user2->id, $user3->id]);
        provider::delete_data_for_users($userlist);
        $userlist = new \core_privacy\local\request\approved_userlist($gt2->get_context(), 'grouptool',
                [$user1->id, $user2->id, $user3->id]);
        provider::delete_data_for_users($userlist);
        $userlist = new \core_privacy\local\request\approved_userlist($gt3->get_context(), 'grouptool',
                [$user1->id, $user2->id, $user3->id]);
        provider::delete_data_for_users($userlist);

        // Everything should be gone!
        $data = $DB->get_records('grouptool_registered');
        // No change here!
        self::assertCount(0, $data);

        $data = $DB->get_records('grouptool_queued');
        // Only the entry for user 3 is gone!
        self::assertCount(0, $data);
    }
}
