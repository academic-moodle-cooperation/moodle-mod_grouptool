<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or later.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for mod_grouptool registration handling.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Anne Kreppenhofer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool;

use coding_exception;
use dml_exception;
use mod_grouptool\local\tests\base;
use moodle_exception;
use required_capability_exception;
use Throwable;

defined('MOODLE_INTERNAL') || die();

/**
 * This class contains the test cases for registration handling.
 *
 * @group mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registration_test extends base {
    /**
     * Tests basic creation of grouptool instance.
     *
     * @covers \mod_grouptool\local\grouptool_instance::__construct
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_instance(): void {
        global $DB;

        $grouptool = $this->create_instance();

        self::assertNotEmpty($grouptool);
        self::assertTrue($DB->record_exists('grouptool_agrps', [
            'grouptoolid' => $grouptool->get_grouptool()->id,
        ]));
    }

    /**
     * Tests basic registration to a single group.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_single(): void {
        // Create a grouptool where only one registration per user is allowed.
        $grouptool = $this->create_instance([
            'allow_reg' => 1,
            'allow_multiple' => 0,
            'use_size' => 1,
            'grpsize' => 2,
            'use_queue' => 0,
            'allow_unreg' => 0,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);

        [$agrps, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);

        // Preview registration for student 0 in group 0.
        // Preview mode must not write anything to the database.
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id, true);
        self::assertEquals(get_string('register_in_group', 'grouptool', $message), $text);

        // Preview registration for student 0 in another group.
        // This should still be possible because no real registration happened yet.
        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id, true);
        self::assertEquals(get_string('register_in_group', 'grouptool', $message), $text);

        // Register student 0 in group 0 for real.
        $message->groupname = $agrps[$agrpids[0]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Student 0 already has one registration.
        // Since allow_multiple is disabled, another registration must fail.
        $text = null;
        try {
            $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\exceeduserreglimit::class, $e);
        }
        self::assertEquals(null, $text);

        // Register student 1 in group 0.
        // Group 0 now has two members and is full afterwards.
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[1]);
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[1]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Register student 2 in group 1.
        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[2]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[2]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        // Try to register student 3 into group 0.
        // This must fail because group 0 already reached grpsize = 2.
        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[3]);

        $curcount= $registrationmanager->get_group_registrations_count($agrpids[0]);

        if ($curcount >= $grouptool->get_grouptool()->grpsize) {
            self::assertTrue(true);
        } else {
            self::fail('Group size: '.$curcount.' is not as expected: '.$grouptool->get_grouptool()->grpsize);
        }
        $text = null;
        try {
            $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[3]->id);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\exceedgroupsize::class, $e);
        }
        self::assertEquals(null, $text);
    }

    /**
     * Tests basic registration to a single group with queues.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_single_queue(): void {
        $grouptool = $this->create_instance([
            'allow_reg' => 1,
            'allow_multiple' => 0,
            'use_size' => 1,
            'grpsize' => 2,
            'use_queue' => 1,
            'allow_unreg' => 0,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);

        [, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);
        $message->username = fullname($this->students[3]);

        $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        $registrationmanager->register_in_agrp($agrpids[0], $this->students[1]->id);
        $registrationmanager->register_in_agrp($agrpids[0], $this->students[2]->id);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[3]->id, true);
        self::assertEquals(get_string('queue_in_group', 'grouptool', $message), $text);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[3]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $text = '';
        try {
            $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[3]->id);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\exceeduserreglimit::class, $e);
        }
        self::assertEquals('', $text);

        try {
            $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[4]->id);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\exceedgroupqueuelimit::class, $e);
        }
        self::assertEquals('', $text);
    }

    /**
     * Tests registration to multiple groups with queues.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_multiple_queue(): void {
        $grouptool = $this->create_instance([
            'allow_reg' => 1,
            'allow_multiple' => 1,
            'choose_min' => 2,
            'choose_max' => 3,
            'use_size' => 1,
            'grpsize' => 2,
            'use_queue' => 1,
            'groups_queues_limit' => 1,
            'users_queues_limit' => 1,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);

        [$agrps, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[2], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $message->groupname = $agrps[$agrpids[0]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[1]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[1]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[2]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[2], $this->students[1]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[0]]->name;
        $message->username = fullname($this->students[2]);
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[2]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[4]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[4], $this->students[2]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $text = '';
        try {
            $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[2]->id);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\exceeduserqueuelimit::class, $e);
        }
        self::assertEquals('', $text);

        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[3], $this->students[2]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);
    }

    /**
     * Tests group change for single registration mode.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     * @covers \mod_grouptool\local\model\registration_manager::can_change_group
     * @covers \mod_grouptool\local\model\registration_manager::qualifies_for_groupchange
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_groupchange_single(): void {
        $grouptool = $this->create_instance([
            'allow_reg' => 1,
            'allow_unreg' => 1,
            'allow_multiple' => 0,
            'use_size' => 1,
            'grpsize' => 1,
            'use_queue' => 1,
            'groups_queues_limit' => 1,
            'users_queues_limit' => 1,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);
        $permissionmanager = $this->create_permission_manager($grouptool);

        [$agrps, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[1]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[0]);
        $permissionmanager->can_change_group($agrpids[1], $this->students[0]->id, $message);
        self::assertTrue($permissionmanager->qualifies_for_groupchange($agrpids[1], $this->students[0]->id));

        $message->username = fullname($this->students[1]);
        $permissionmanager->can_change_group($agrpids[1], $this->students[1]->id, $message);
        self::assertTrue($permissionmanager->qualifies_for_groupchange($agrpids[1], $this->students[1]->id));

        $message->username = fullname($this->students[2]);

        try {
            $permissionmanager->can_change_group($agrpids[1], $this->students[2]->id, $message);
        } catch (exception\registration $e) {
            self::assertInstanceOf(exception\registration::class, $e);
            self::assertEquals(get_string('groupchange_from_non_unique_reg', 'grouptool'), $e->getMessage());
        }

        self::assertFalse($permissionmanager->qualifies_for_groupchange($agrpids[1], $this->students[2]->id));

        $message->groupname = $agrps[$agrpids[1]]->name;

        $message->username = fullname($this->students[0]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id, true);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[1]->id, true);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        $message->username = fullname($this->students[0]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[1]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);
    }

    /**
     * Tests group change for multiple registration mode.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     * @covers \mod_grouptool\local\model\registration_manager::can_change_group
     * @covers \mod_grouptool\local\model\registration_manager::change_group
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_groupchange_multiple(): void {
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
            'users_queues_limit' => 1,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);

        [$agrps, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[2]]->name;
        $message->username = fullname($this->students[2]);
        $text = $registrationmanager->register_in_agrp($agrpids[2], $this->students[2]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[3]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[3], $this->students[2]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[4]]->name;
        $message->username = fullname($this->students[3]);
        $text = $registrationmanager->register_in_agrp($agrpids[4], $this->students[3]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[5]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[5], $this->students[3]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[4]);
        $message->groupname = $agrps[$agrpids[4]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[4], $this->students[4]->id);
        self::assertEquals(get_string('place_allocated_in_group_success', 'grouptool', $message), $text);

        $thrown = false;
        try {
            $registrationmanager->register_in_agrp($agrpids[4], $this->students[4]->id);
        } catch (exception\regpresent $e) {
            $thrown = true;
            self::assertInstanceOf(exception\regpresent::class, $e);
            self::assertEquals(get_string('already_marked', 'grouptool', $message), $e->getMessage());
        }
        self::assertTrue($thrown);

        $message->username = fullname($this->students[4]);
        $message->groupname = $agrps[$agrpids[5]]->name;
        $text = $registrationmanager->register_in_agrp($agrpids[5], $this->students[4]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $thrown = false;
        $message->username = fullname($this->students[0]);
        $message->groupname = $agrps[$agrpids[4]]->name;

        try {
            $registrationmanager->register_in_agrp($agrpids[4], $this->students[0]->id);
        } catch (exception\exceedgroupqueuelimit $e) {
            $thrown = true;
            self::assertInstanceOf(exception\exceedgroupqueuelimit::class, $e);
            self::assertEquals(get_string('exceedgroupqueuelimit', 'grouptool', $message), $e->getMessage());
        }
        self::assertTrue($thrown);

        $grouptool->get_grouptool()->groups_queues_limit = 2;

        $thrown = false;
        $message->username = fullname($this->students[4]);

        try {
            $registrationmanager->register_in_agrp($agrpids[4], $this->students[4]->id);
        } catch (exception\regpresent $e) {
            $thrown = true;
            self::assertInstanceOf(exception\regpresent::class, $e);
            self::assertEquals(get_string('already_queued', 'grouptool', $message), $e->getMessage());
        }
        self::assertTrue($thrown);

        $grouptool->get_grouptool()->groups_queues_limit = 1;

        $thrown = false;
        $message->username = fullname($this->students[3]);

        try {
            $registrationmanager->register_in_agrp($agrpids[4], $this->students[3]->id);
        } catch (exception\regpresent $e) {
            $thrown = true;
            self::assertInstanceOf(exception\registration::class, $e);
            self::assertEquals(get_string('already_registered', 'grouptool', $message), $e->getMessage());
        }
        self::assertTrue($thrown);

        $thrown = false;
        $message->groupname = $agrps[$agrpids[4]]->name;
        $permissionmanager = $this->create_permission_manager($grouptool);
        try {
            $permissionmanager->can_change_group($agrpids[4], $this->students[2]->id, $message);
        } catch (exception\registration $e) {
            $thrown = true;
            self::assertInstanceOf(exception\registration::class, $e);
            self::assertEquals(get_string('groupchange_from_non_unique_reg', 'grouptool'), $e->getMessage());
        }

        self::assertTrue($thrown);
        self::assertFalse($permissionmanager->qualifies_for_groupchange($agrpids[4], $this->students[2]->id));

        $message->groupname = $agrps[$agrpids[6]]->name;
        $message->username = fullname($this->students[2]);

        $text = $permissionmanager->can_change_group($agrpids[6], $this->students[2]->id, $message, $agrpids[2]);
        self::assertEquals(get_string('change_group_to', 'grouptool', $message), $text);

        $text = $registrationmanager->change_group($agrpids[6], $this->students[2]->id, $message, $agrpids[2]);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $grouptool->get_grouptool()->allowunreg = 0;

        $thrown = false;
        try {
            $permissionmanager->can_change_group($agrpids[2], $this->students[2]->id, $message, $agrpids[2]);
        } catch (exception\registration $e) {
            $thrown = true;
            self::assertInstanceOf(exception\registration::class, $e);
            self::assertEquals(get_string('unreg_not_allowed', 'grouptool'), $e->getMessage());
        }

        self::assertTrue($thrown);
    }

    /**
     * Tests resolving of queues.
     *
     * @covers \mod_grouptool\local\model\registration_manager::register_in_agrp
     * @covers \mod_grouptool\local\model\queue_manager::resolve_queues
     *
     * @throws Throwable
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function test_queue_resolving(): void {
        global $DB;

        $grouptool = $this->create_instance([
            'allow_reg' => 1,
            'allow_multiple' => 1,
            'choose_min' => 1,
            'choose_max' => 3,
            'use_size' => 1,
            'grpsize' => 1,
            'use_queue' => 1,
            'groups_queues_limit' => 2,
            'users_queues_limit' => 1,
        ]);

        $registrationmanager = $this->create_registration_manager($grouptool);
        $queuemanager = $this->create_queue_manager($grouptool);

        [$agrps, $agrpids, $message] = $this->get_agrps_and_prepare_message($grouptool);

        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[0]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[1]);
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[1]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[2]);
        $text = $registrationmanager->register_in_agrp($agrpids[0], $this->students[2]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->groupname = $agrps[$agrpids[1]]->name;
        $message->username = fullname($this->students[3]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[3]->id);
        self::assertEquals(get_string('register_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[4]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[4]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        $message->username = fullname($this->students[5]);
        $text = $registrationmanager->register_in_agrp($agrpids[1], $this->students[5]->id);
        self::assertEquals(get_string('queue_in_group_success', 'grouptool', $message), $text);

        [$error, $previewmessage] = $queuemanager->resolve_queues(true);
        self::assertFalse($error, $previewmessage);

        [$error, $workmessage] = $queuemanager->resolve_queues();
        self::assertFalse($error, $workmessage);

        self::assertFalse(
            $DB->record_exists_select(
                'grouptool_queued',
                'agrpid = ? OR agrpid = ?',
                [$agrpids[0], $agrpids[1]]
            )
        );
    }
}
