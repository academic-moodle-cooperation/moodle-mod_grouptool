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
 * Unit tests for mod_grouptool's privacy provider.
 *
 * @package    mod_grouptool
 * @copyright  2019 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @author     Philipp Hager <philipp.hager@tuwien.ac.at>
 * @author     Anne Kreppenhofer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local\tests;

use coding_exception;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\privacy\provider;
use moodle_exception;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_grouptool's privacy provider.
 *
 * @group mod_grouptool
 *
 * @package    mod_grouptool
 * @copyright  2019 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_test extends base {
    /**
     * Test that getting the contexts for a user works.
     *
     * @covers \mod_grouptool\privacy\provider::get_contexts_for_userid
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $course1 = $this->course;
        $course2 = self::getDataGenerator()->create_course();

        $user1 = $this->students[0];
        $user2 = $this->students[1];

        self::getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');

        $gt1 = $this->create_instance([
            'course' => $course1,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt2 = $this->create_instance([
            'course' => $course1,
            'use_queue' => 1,
            'use_size' => 1,
            'grpsize' => 1,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt3 = $this->create_instance([
            'course' => $course2,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $usercontextids = [
            $gt1->get_context()->id,
            $gt2->get_context()->id,
        ];

        [, $agrpids1, ] = $this->get_agrps_and_prepare_message($gt1);
        [, $agrpids2, ] = $this->get_agrps_and_prepare_message($gt2);
        [, $agrpids3, ] = $this->get_agrps_and_prepare_message($gt3);

        $this->insert_registration_record($gt1, $agrpids1[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt2, $agrpids2[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt3, $agrpids3[0], $user1->id, $user1->id);

        $this->insert_registration_record($gt1, $agrpids1[0], $user2->id, $user2->id);
        $this->insert_registration_record($gt2, $agrpids2[0], $user2->id, $user2->id);

        $contextlist = provider::get_contexts_for_userid($user2->id);

        self::assertEquals(count($usercontextids), count($contextlist->get_contextids()));
        self::assertEmpty(array_diff($usercontextids, $contextlist->get_contextids()));
    }

    /**
     * Test returning a list of user IDs related to a context.
     *
     * @covers \mod_grouptool\privacy\provider::get_users_in_context
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_get_users_in_context(): void {
        $course = $this->course;

        $user1 = $this->students[0];
        $user2 = $this->students[1];
        $user3 = $this->students[2];
        $user4 = $this->editingteachers[0];
        $user5 = $this->students[3];
        $user6 = $this->students[4];

        $gt1 = $this->create_instance([
            'course' => $course,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt2 = $this->create_instance([
            'course' => $course,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $context1 = $gt1->get_context();
        $context2 = $gt2->get_context();

        [, $agrpids1] = $this->get_agrps_and_prepare_message($gt1);
        [, $agrpids2] = $this->get_agrps_and_prepare_message($gt2);

        $this->insert_registration_record($gt1, $agrpids1[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt2, $agrpids2[0], $user1->id, $user1->id);

        $this->insert_registration_record($gt1, $agrpids1[0], $user2->id, $user2->id);
        $this->insert_registration_record($gt2, $agrpids2[0], $user2->id, $user2->id);

        $this->insert_registration_record($gt2, $agrpids2[0], $user3->id, $user3->id);

        // User 4 should be related to gt1 only through modified_by.
        $this->insert_registration_record($gt1, $agrpids1[0], $user3->id, $user4->id);

        $this->insert_registration_record($gt2, $agrpids2[1], $user5->id, $user5->id);

        $userlist1 = new userlist($context1, 'grouptool');
        provider::get_users_in_context($userlist1);
        $userids1 = $userlist1->get_userids();

        self::assertTrue(in_array($user1->id, $userids1));
        self::assertTrue(in_array($user2->id, $userids1));
        self::assertTrue(in_array($user3->id, $userids1));
        self::assertTrue(in_array($user4->id, $userids1));
        self::assertFalse(in_array($user5->id, $userids1));
        self::assertFalse(in_array($user6->id, $userids1));

        $userlist2 = new userlist($context2, 'grouptool');
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
     * Test that data for a registered student can be exported.
     *
     * @covers \mod_grouptool\privacy\provider::export_user_data
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_export_user_data_student(): void {
        $user = $this->students[0];

        $grouptool = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 1,
            'use_size' => 1,
            'grpsize' => 1,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        [, $agrpids] = $this->get_agrps_and_prepare_message($grouptool);

        $this->insert_registration_record($grouptool, $agrpids[0], $user->id, $user->id);
        $this->insert_queue_entry($agrpids[1], $user->id);

        $contextlist = new approved_contextlist(
            $user,
            'grouptool',
            [
                $grouptool->get_context()->id,
            ]
        );

        provider::export_user_data($contextlist);

        $writer = writer::with_context($grouptool->get_context());

        self::assertTrue($writer->has_any_data());
    }

    /**
     * Test that data for a teacher can be exported.
     *
     * @covers \mod_grouptool\privacy\provider::export_user_data
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_export_user_data_teacher(): void {
        $teacher = $this->editingteachers[0];

        $grouptool = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        [, $agrpids] = $this->get_agrps_and_prepare_message($grouptool);

        $this->insert_registration_record($grouptool, $agrpids[0], $teacher->id, $teacher->id);

        $contextlist = new approved_contextlist(
            $teacher,
            'grouptool',
            [
                $grouptool->get_context()->id,
            ]
        );

        provider::export_user_data($contextlist);

        $writer = writer::with_context($grouptool->get_context());

        self::assertTrue($writer->has_any_data());
    }

    /**
     * Test deleting all user data for one context.
     *
     * @covers \mod_grouptool\privacy\provider::delete_data_for_all_users_in_context
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $user1 = $this->students[0];
        $user2 = $this->students[1];

        $gt1 = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 1,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt2 = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 1,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        [, $agrpids1] = $this->get_agrps_and_prepare_message($gt1);
        [, $agrpids2] = $this->get_agrps_and_prepare_message($gt2);

        $this->insert_registration_record($gt1, $agrpids1[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt1, $agrpids1[1], $user2->id, $user2->id);
        $this->insert_queue_entry($agrpids1[2], $user1->id);

        $this->insert_registration_record($gt2, $agrpids2[0], $user1->id, $user1->id);
        $this->insert_queue_entry($agrpids2[1], $user2->id);

        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user2->id));

        provider::delete_data_for_all_users_in_context($gt1->get_context());

        self::assertFalse($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertFalse($this->has_user_data_for_grouptool($gt1, $user2->id));

        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user2->id));
    }

    /**
     * Test deleting all user data for one approved user.
     *
     * @covers \mod_grouptool\privacy\provider::delete_data_for_user
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_delete_data_for_user(): void {
        $user1 = $this->students[0];
        $user2 = $this->students[1];

        $gt1 = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 1,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt2 = $this->create_instance([
            'course' => $this->course,
            'use_queue' => 1,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        [, $agrpids1] = $this->get_agrps_and_prepare_message($gt1);
        [, $agrpids2] = $this->get_agrps_and_prepare_message($gt2);

        $this->insert_registration_record($gt1, $agrpids1[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt1, $agrpids1[1], $user2->id, $user2->id);
        $this->insert_queue_entry($agrpids1[2], $user1->id);

        $this->insert_registration_record($gt2, $agrpids2[0], $user1->id, $user1->id);
        $this->insert_queue_entry($agrpids2[1], $user1->id);

        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user1->id));

        $contextlist = new approved_contextlist(
            $user1,
            'grouptool',
            [
                $gt1->get_context()->id,
            ]
        );

        provider::delete_data_for_user($contextlist);

        self::assertFalse($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user1->id));

        $contextlist = new approved_contextlist(
            $user1,
            'grouptool',
            [
                $gt2->get_context()->id,
            ]
        );

        provider::delete_data_for_user($contextlist);

        self::assertFalse($this->has_user_data_for_grouptool($gt2, $user1->id));
    }

    /**
     * A test for deleting all user data for a bunch of users.
     *
     * @covers \mod_grouptool\privacy\provider::delete_data_for_users
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $course1 = $this->course;
        $course2 = self::getDataGenerator()->create_course();

        $user1 = $this->students[0];
        $user2 = $this->students[1];
        $user3 = $this->students[2];

        self::getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user3->id, $course1->id, 'student');

        self::getDataGenerator()->enrol_user($user1->id, $course2->id, 'student');
        self::getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');
        self::getDataGenerator()->enrol_user($user3->id, $course2->id, 'student');

        $gt1 = $this->create_instance([
            'course' => $course1,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt2 = $this->create_instance([
            'course' => $course1,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        $gt3 = $this->create_instance([
            'course' => $course2,
            'use_queue' => 0,
            'use_size' => 0,
            'ifmemberadded' => 0,
            'ifmemberremoved' => 0,
            'ifgroupdeleted' => 0,
        ]);

        [, $agrpids1] = $this->get_agrps_and_prepare_message($gt1);
        [, $agrpids2] = $this->get_agrps_and_prepare_message($gt2);
        [, $agrpids3] = $this->get_agrps_and_prepare_message($gt3);

        $this->insert_registration_record($gt1, $agrpids1[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt2, $agrpids2[0], $user1->id, $user1->id);
        $this->insert_registration_record($gt3, $agrpids3[0], $user1->id, $user1->id);

        $this->insert_registration_record($gt1, $agrpids1[1], $user2->id, $user2->id);
        $this->insert_registration_record($gt2, $agrpids2[1], $user2->id, $user2->id);
        $this->insert_registration_record($gt3, $agrpids3[1], $user2->id, $user2->id);

        $this->insert_registration_record($gt1, $agrpids1[2], $user3->id, $user3->id);
        $this->insert_registration_record($gt2, $agrpids2[2], $user3->id, $user3->id);
        $this->insert_registration_record($gt3, $agrpids3[2], $user3->id, $user3->id);

        $this->insert_queue_entry($agrpids1[3], $user1->id);
        $this->insert_queue_entry($agrpids1[4], $user2->id);
        $this->insert_queue_entry($agrpids2[3], $user1->id);
        $this->insert_queue_entry($agrpids3[3], $user1->id);

        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user3->id));

        $approveduserlist = new approved_userlist(
            $gt1->get_context(),
            'grouptool',
            [
                $user1->id,
                $user2->id,
            ]
        );

        provider::delete_data_for_users($approveduserlist);

        self::assertFalse($this->has_user_data_for_grouptool($gt1, $user1->id));
        self::assertFalse($this->has_user_data_for_grouptool($gt1, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt1, $user3->id));

        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user1->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt2, $user2->id));
        self::assertTrue($this->has_user_data_for_grouptool($gt3, $user1->id));

        $gt1agrpids = $DB->get_fieldset_select(
            'grouptool_agrps',
            'id',
            'grouptoolid = ?',
            [$gt1->get_grouptool()->id]
        );

        [$insql, $params] = $DB->get_in_or_equal($gt1agrpids);

        self::assertFalse($DB->record_exists_select(
            'grouptool_registered',
            'userid IN (?, ?) AND agrpid ' . $insql,
            array_merge([$user1->id, $user2->id], $params)
        ));

        self::assertFalse($DB->record_exists_select(
            'grouptool_queued',
            'userid IN (?, ?) AND agrpid ' . $insql,
            array_merge([$user1->id, $user2->id], $params)
        ));
    }

    /**
     * Inserts a registration record with an explicit modifier.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @param int $agrpid The active group id.
     * @param int $userid The registered user id.
     * @param int $modifiedby The modifier user id.
     * @return int The created registration id.
     * @throws dml_exception
     */
    private function insert_registration_record(
        grouptool_instance $grouptool,
        int $agrpid,
        int $userid,
        int $modifiedby
    ): int {
        global $DB;

        $record = (object)[
            'agrpid' => $agrpid,
            'userid' => $userid,
            'timestamp' => time(),
            'modified_by' => $modifiedby,
        ];

        $id = (int)$DB->insert_record('grouptool_registered', $record, true);

        $groupid = $DB->get_field('grouptool_agrps', 'groupid', ['id' => $agrpid], MUST_EXIST);

        if (!groups_is_member($groupid, $userid)) {
            groups_add_member($groupid, $userid);
        }

        return $id;
    }

    /**
     * Checks if a user still has registration or queue data in a grouptool instance.
     *
     * @param grouptool_instance $grouptool The grouptool instance.
     * @param int $userid The user id.
     * @return bool Whether data exists.
     * @throws dml_exception
     */
    private function has_user_data_for_grouptool(grouptool_instance $grouptool, int $userid): bool {
        global $DB;

        $agrpids = $DB->get_fieldset_select(
            'grouptool_agrps',
            'id',
            'grouptoolid = ?',
            [$grouptool->get_grouptool()->id]
        );

        if (empty($agrpids)) {
            return false;
        }

        [$insql, $params] = $DB->get_in_or_equal($agrpids);

        return $DB->record_exists_select(
            'grouptool_registered',
            '(userid = ? OR modified_by = ?) AND agrpid ' . $insql,
            array_merge([$userid, $userid], $params)
        ) || $DB->record_exists_select(
            'grouptool_queued',
            '(userid = ?) AND agrpid ' . $insql,
            array_merge([$userid, $userid], $params)
        );
    }
}
