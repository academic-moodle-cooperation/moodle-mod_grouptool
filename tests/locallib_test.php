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
 * @author    Hannes Laimer
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool;

use mod_grouptool\local\tests\grouptool;

defined('MOODLE_INTERNAL') || die();

// Make sure the code being tested is accessible.
global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/locallib.php'); // Include the code to test!

/**
 * This class contains the test cases for functions in locallib.
 * @group mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Hannes Laimer
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \mod_grouptool\local\tests\base {
    /*
     * The base test class already contains a setUp-method setting up a course including users and groups.
     */

    /**
     * Tests get_name method in locallib
     *
     * 1 Assertions
     *
     * @covers \mod_grouptool\grouptool::get_name
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_name(): void {
        $grouptool = $this->create_instance(['name' => 'GT01']);
        $this->assertEquals($grouptool->get_name(), 'GT01');
    }

    /**
     * Tests get_active_groups method in locallib
     *
     * 2 Assertions
     *
     * @covers \mod_grouptool\grouptool::get_active_groups
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_groups(): void {
        global $DB;
        $grouptool = $this->create_instance();

        $allagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', '');
        $activeagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', 'active=1');

        $DB->set_field('grouptool_agrps', 'active', 0, ['id' => $activeagrpsids[1]]);
        $DB->set_field('grouptool_agrps', 'active', 0, ['id' => $activeagrpsids[0]]);

        $activeagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', 'active=1');

        $this->assertEquals(count($grouptool->get_active_groups()), count($activeagrpsids));
        $this->assertEquals(count($grouptool->get_active_groups(false, false, 0, 0, 0, true, true)), count($allagrpsids));

        // TODO: test with set agrpid, groupid and groupingid as parameter in order to check if correct data is returned.
    }

    /**
     * Tests create_one_person_groups() in "create" mode (no preview, no grouping).
     *
     * Verifies:
     * - returns success
     * - creates one new Moodle group per provided user
     * - adds each user to their created group
     * - inserts grouptool_agrps and grouptool_registered records
     *
     * @covers \mod_grouptool\grouptool::create_one_person_groups
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_one_person_groups_creates_groups_and_memberships(): void {
        global $DB;

        $grouptool = $this->create_instance();

        // Use a small subset to keep the test fast and deterministic.
        $users = array_slice($this->students, 0, 3);
        $this->assertCount(3, $users);

        // Count groups before.
        $beforegroups = groups_get_all_groups($this->course->id);
        $beforecount = is_array($beforegroups) ? count($beforegroups) : 0;

        // Call private method via reflection.
        $ref = new \ReflectionClass($grouptool);
        $method = $ref->getMethod('create_one_person_groups');
        $method->setAccessible(true);

        $result = $method->invoke(
            $grouptool,
            $users,
            '[idnumber]',
            0,
            null,
            false,
            0
        );

        // Assert return format and success.
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertFalse($result[0]);
        $this->assertSame(get_string('groups_created', 'grouptool'), $result[1]);

        // Verify exactly 3 groups were added to the course.
        $aftergroups = groups_get_all_groups($this->course->id);
        $aftercount = is_array($aftergroups) ? count($aftergroups) : 0;
        $this->assertSame($beforecount + 3, $aftercount);

        // Verify each user is now member of exactly one *new* group created by this call.
        // We identify created groups via grouptool_agrps rows linked to this grouptool instance.
        $createdgroupids = $DB->get_fieldset_select(
            'grouptool_agrps',
            'groupid',
            'grouptoolid = ?',
            [$grouptool->get_grouptool()->id]
        );

        // Filter by groups that did not exist before.
        $beforegroupids = array_keys($beforegroups ?? []);
        $newgroupids = array_values(array_diff($createdgroupids, $beforegroupids));

        $this->assertCount(3, $newgroupids, 'Expected exactly 3 newly created group IDs.');

        foreach ($users as $user) {
            // Find the group among the new groups where this user is the only member.
            $found = false;

            foreach ($newgroupids as $gid) {
                $members = groups_get_members($gid);
                if (count($members) === 1 && isset($members[$user->id])) {
                    $found = true;

                    // Ensure grouptool_agrps exists for this group.
                    $agrp = $DB->get_record('grouptool_agrps', [
                        'grouptoolid' => $grouptool->get_grouptool()->id,
                        'groupid' => $gid,
                    ], '*', MUST_EXIST);

                    // Ensure grouptool_registered exists for this user+agrp.
                    $this->assertTrue($DB->record_exists('grouptool_registered', [
                        'userid' => $user->id,
                        'agrpid' => $agrp->id,
                    ]));

                    break;
                }
            }

            $this->assertTrue($found, 'User was not found as sole member of any newly created group.');
        }
    }
    /**
     * Tests groups_parse_name() replacements for tags, @ (letters) and # (numbers).
     *
     * @covers \mod_grouptool\grouptool::groups_parse_name
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_groups_parse_name_replacements(): void {
        $grouptool = $this->create_instance();

        // Access private method via reflection.
        $ref = new \ReflectionClass($grouptool);
        $method = $ref->getMethod('groups_parse_name');

        // Single member replaces tags with full values (or "no<tag>#") ---.
        $u = (object)[
            'firstname' => 'John',
            'lastname'  => 'Doe',
            'idnumber'  => 'ID-42',
            'username'  => 'jdoe',
        ];

        $scheme = 'G-[firstname]-[lastname]-[idnumber]-[username]';
        $parsed = $method->invoke($grouptool, $scheme, 0, $u, 0);
        $this->assertSame('G-John-Doe-ID-42-jdoe', $parsed);

        // No members: tags are removed (replaced with empty strings) ---.
        $scheme3 = 'X[firstname]Y[lastname]Z';
        $parsed3 = $method->invoke($grouptool, $scheme3, 0, null, 0);
        $this->assertSame('XYZ', $parsed3);

        // Array members: tag values become concatenated 3-char chunks with '-' ---.
        $m1 = (object)['firstname' => 'Michael', 'lastname' => 'Miller', 'idnumber' => 'ABCDEF', 'username' => 'michael'];
        $m2 = (object)['firstname' => 'Sarah', 'lastname' => 'Smith', 'idnumber' => 'XYZ', 'username' => 'sarah'];
        $scheme4 = '[firstname]-[lastname]-[idnumber]-[username]';
        $parsed4 = $method->invoke($grouptool, $scheme4, 0, [$m1, $m2], 0);

        $this->assertSame('Mic-Sar-Mil-Smi-ABC-XYZ-mic-sar', $parsed4);

        // ... '@' letter conversion (simple A,B,C...) ---.
        $scheme5 = 'G@';
        $this->assertSame('GA', $method->invoke($grouptool, $scheme5, 0, null, 0));
        $this->assertSame('GB', $method->invoke($grouptool, $scheme5, 1, null, 0));
        $this->assertSame('GC', $method->invoke($grouptool, $scheme5, 2, null, 0));

        // ... '#' numeric replacement (groupnumber + 1), with and without padding ---.
        $scheme6 = 'G#';
        $this->assertSame('G1', $method->invoke($grouptool, $scheme6, 0, null, 0));
        $this->assertSame('G2', $method->invoke($grouptool, $scheme6, 1, null, 0));

        $scheme7 = 'G#';
        $this->assertSame('G001', $method->invoke($grouptool, $scheme7, 0, null, 3));
        $this->assertSame('G010', $method->invoke($grouptool, $scheme7, 9, null, 3));

        // Combined '@' + '#' + tags ---.
        $scheme8 = 'Team-@-#-[username]';
        $parsed8 = $method->invoke($grouptool, $scheme8, 4, $u, 2);
        $this->assertSame('Team-E-05-jdoe', $parsed8);
    }
    /**
     * Tests add_missing_agrps(): it should add agrp entries for course groups missing in grouptool_agrps
     * and set newly added entries inactive.
     *
     * @covers \mod_grouptool\grouptool::add_missing_agrps
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_add_missing_agrps_adds_missing_and_sets_inactive(): void {
        global $DB;

        $grouptool = $this->create_instance();

        // Sanity: ensure the course has groups from base::setUp().
        $coursegroups = groups_get_all_groups($this->course->id, 0, 0, 'g.id');
        $this->assertNotEmpty($coursegroups);
        $coursegroupids = array_map('intval', array_keys($coursegroups));

        // Ensure there are some missing agrps by deleting agrps for two existing course groups.
        $groupidstoremove = array_slice($coursegroupids, 0, 2);
        $this->assertCount(2, $groupidstoremove);

        // Confirm agrps exist first (create_instance() sets them active for its created records).
        foreach ($groupidstoremove as $gid) {
            // If the test data doesn't have agrps for a given group, pick a different one.
            // But in typical grouptool setup, there should be an agrp for each initial group.
            if (
                !$DB->record_exists(
                    'grouptool_agrps',
                    [
                        'grouptoolid' => $grouptool->get_grouptool()->id,
                        'groupid' => $gid]
                )
            ) {
                // Create it so we can remove it and test re-adding.
                // We do it directly to avoid depending on other methods.
                $DB->insert_record('grouptool_agrps', (object)[
                    'grouptoolid' => $grouptool->get_grouptool()->id,
                    'groupid' => $gid,
                    'sort_order' => 999999,
                    'active' => 1,
                ]);
            }
        }

        // Remove them to create the "missing" situation.
        $DB->delete_records_list(
            'grouptool_agrps',
            'groupid',
            $groupidstoremove,
            ['grouptoolid' => $grouptool->get_grouptool()->id]
        );

        foreach ($groupidstoremove as $gid) {
            $this->assertFalse(
                $DB->record_exists('grouptool_agrps', ['grouptoolid' => $grouptool->get_grouptool()->id, 'groupid' => $gid]),
                'Precondition failed: agrp record still exists after deletion.'
            );
        }

        // Run the method under test.
        $grouptool->add_missing_agrps();

        // Now the missing ones must exist again, and must be inactive.
        foreach ($groupidstoremove as $gid) {
            $agrp = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $grouptool->get_grouptool()->id,
                'groupid' => $gid,
            ], '*', MUST_EXIST);

            $this->assertSame(0, (int)$agrp->active, 'Newly added agrp should be set inactive.');
        }

        // Also verify that existing agrps were not unintentionally changed:.
        // Pick one group id that wasn't removed and ensure its agrp is still active (given create_instance() sets them active).
        $untouched = array_values(array_diff($coursegroupids, $groupidstoremove));
        $this->assertNotEmpty($untouched);
        $untouchedgid = $untouched[0];

        if ($DB->record_exists('grouptool_agrps', ['grouptoolid' => $grouptool->get_grouptool()->id, 'groupid' => $untouchedgid])) {
            $untouchedagrp = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $grouptool->get_grouptool()->id,
                'groupid' => $untouchedgid,
            ]);
            // We don't force it to be active in *all* fixtures, but in this test setup it should be.
            $this->assertSame(1, (int)$untouchedagrp->active);
        }
    }
    /**
     * Unit test for add_agrp_entry().
     *
     * Verifies:
     * 1) If no agrp exists yet, it inserts a new record with correct fields.
     * 2) If an agrp already exists, it returns the existing id and (when allow_reg=1) forces active=1.
     *
     * Note: add_agrp_entry() is protected, so we call it via Reflection.
     *
     * @covers \mod_grouptool\grouptool::add_agrp_entry
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_add_agrp_entry_inserts_and_reuses_existing(): void {
        global $DB;

        $grouptool = $this->create_instance(['allow_reg' => 1]);
        // Create a fresh course group that definitely exists but has no agrp yet.
        $group = self::getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupid = (int)$group->id;

        // Make sure precondition: no agrp exists for this group+grouptool.
        $attr = ['grouptoolid' => $grouptool->get_grouptool()->id, 'groupid' => $groupid];
        $DB->delete_records('grouptool_agrps', $attr);
        $this->assertFalse($DB->record_exists('grouptool_agrps', $attr));

        // Call protected method via reflection.
        $ref = new \ReflectionClass($grouptool);
        $method = $ref->getMethod('add_agrp_entry');

        // Case 1: Insert new record.
        /** @var \stdClass $newagrp */
        $newagrp = $method->invoke($grouptool, $groupid);

        $this->assertInstanceOf(\stdClass::class, $newagrp);
        $this->assertNotEmpty($newagrp->id);

        $record = $DB->get_record('grouptool_agrps', ['id' => $newagrp->id], '*', MUST_EXIST);
        $this->assertSame($groupid, (int)$record->groupid);
        $this->assertSame($grouptool->get_grouptool()->id, $record->grouptoolid);
        $this->assertSame(999999, (int)$record->sort_order);
        $this->assertSame(1, (int)$record->active);

        // Case 2: Existing record path (should reuse same id, and force active=1 when allow_reg=1).
        // Set active to 0 artificially to verify add_agrp_entry forces it back to 1.
        $DB->set_field('grouptool_agrps', 'active', 0, ['id' => $record->id]);
        $this->assertSame(0, (int)$DB->get_field('grouptool_agrps', 'active', ['id' => $record->id]));

        /** @var \stdClass $existing */
        $existing = $method->invoke($grouptool, $groupid);

        $this->assertSame((int)$record->id, (int)$existing->id, 'Expected existing agrp id to be reused.');
        $this->assertSame(
            1,
            (int)$DB->get_field('grouptool_agrps', 'active', ['id' => $record->id]),
            'Expected active to be forced to 1 when allow_reg is enabled.'
        );
    }

    /**
     * Calls the private create_groups() method via reflection.
     *
     * This helper is used by PHPUnit tests to invoke the private
     * grouptool::create_groups() method without changing its visibility.
     *
     * @param \mod_grouptool\local\tests\grouptool $grouptool Grouptool instance under test
     * @param \stdClass $data Data object containing group creation settings
     *                        (usually coming from the administration form)
     * @param \stdClass[]|array $users List of users to be allocated to the created groups
     * @param int $userpergrp Number of users per group
     * @param int $numgrps Number of groups to create
     * @param bool $previewonly Whether to run in preview-only mode
     * @return array Result array: [0 => error(bool), 1 => message(string)]
     *
     * @throws \ReflectionException If the method cannot be reflected
     */
    private function call_create_groups_private(
        \mod_grouptool\local\tests\grouptool $grouptool,
        \stdClass $data,
        array $users,
        int $userpergrp,
        int $numgrps,
        bool $previewonly
    ): array {
        $ref = new \ReflectionClass($grouptool);
        $method = $ref->getMethod('create_groups');

        return $method->invoke($grouptool, $data, $users, $userpergrp, $numgrps, $previewonly);
    }

    /**
     * 1) Preview mode: should return an HTML table and not create any groups.
     *
     * @covers \mod_grouptool\grouptool::create_groups
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \ReflectionException
     */
    public function test_create_groups_preview_only_does_not_create_groups(): void {
        $grouptool = $this->create_instance();

        $users = array_slice($this->students, 0, 4);

        $data = (object)[
            'allocateby' => 'random',
            'seed' => 12345,
            'namingscheme' => 'T-#',
            'grouping' => 0,
            'groupingname' => '',
            'enablegroupmessaging' => 0,
            'mode' => GROUPTOOL_GROUPS_AMOUNT,
            'numberofgroups' => 2,
            'numberofmembers' => 0,
        ];

        $before = groups_get_all_groups($this->course->id);
        $beforecount = is_array($before) ? count($before) : 0;

        $result = $this->call_create_groups_private($grouptool, $data, $users, 2, 2, true);

        $this->assertIsArray($result);
        $this->assertFalse($result[0], 'Preview should not hard-fail in this setup.');
        $this->assertIsString($result[1]);
        $this->assertStringContainsString('<table', $result[1], 'Expected HTML table output in preview mode.');

        $after = groups_get_all_groups($this->course->id);
        $aftercount = is_array($after) ? count($after) : 0;

        $this->assertSame($beforecount, $aftercount, 'Preview mode must not create groups.');
    }

    /**
     * 2) Creation mode: creates groups and registers members + agrp entries.
     *
     * @covers \mod_grouptool\grouptool::create_groups
     * @covers \mod_grouptool\grouptool::add_agrp_entry
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception|\ReflectionException
     */
    public function test_create_groups_creates_groups_and_registers_members(): void {
        global $DB;

        $grouptool = $this->create_instance(['allow_reg' => 1]);

        // Use 6 users, 2 groups, 3 members each.
        $users = array_slice($this->students, 0, 6);

        $data = (object)[
            'allocateby' => 'no', // We'll rely on given $users order? Actually "no" means: do not allocate users.
            // For membership creation, allocateby must not be 'no'. Use random (seed fixed) for deterministic membership.
            'seed' => 222,
            'namingscheme' => 'GT-#',
            'grouping' => 0,
            'groupingname' => '',
            'enablegroupmessaging' => 0,
            'mode' => GROUPTOOL_GROUPS_AMOUNT,
            'numberofgroups' => 2,
            'numberofmembers' => 0,
        ];
        $data->allocateby = 'random';

        $beforegroups = groups_get_all_groups($this->course->id);
        $beforecount = is_array($beforegroups) ? count($beforegroups) : 0;

        $result = $this->call_create_groups_private($grouptool, $data, $users, 3, 2, false);

        $this->assertFalse($result[0]);
        $this->assertSame(get_string('groups_created', 'grouptool'), $result[1]);

        $aftergroups = groups_get_all_groups($this->course->id);
        $aftercount = is_array($aftergroups) ? count($aftergroups) : 0;
        $this->assertSame($beforecount + 2, $aftercount, 'Expected exactly 2 new Moodle groups.');

        // Identify newly created groups by excluding pre-existing ones.
        $beforeids = array_keys($beforegroups ?? []);
        $afterids = array_keys($aftergroups ?? []);
        $newgroupids = array_values(array_diff($afterids, $beforeids));
        $this->assertCount(2, $newgroupids);

        // Each new group should have an agrp entry and 3 members.
        foreach ($newgroupids as $gid) {
            $members = groups_get_members($gid);
            $this->assertCount(3, $members);

            $agrp = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $grouptool->get_grouptool()->id,
                'groupid' => $gid,
            ], '*', MUST_EXIST);

            // Ensure registered rows exist for each member.
            foreach ($members as $member) {
                $this->assertTrue($DB->record_exists('grouptool_registered', [
                    'userid' => $member->id,
                    'agrpid' => $agrp->id,
                ]));
            }
        }
    }

    /**
     * 3) Failure path: if a group name already exists, creation should fail and cleanup created groups.
     *
     * @covers \mod_grouptool\grouptool::create_groups
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception|\ReflectionException
     */
    public function test_create_groups_name_conflict_fails_and_cleans_up(): void {
        global $DB;

        $grouptool = $this->create_instance(['allow_reg' => 1]);

        // Pre-create a Moodle group with the name that will conflict with the first created one.
        // Naming scheme 'CONFLICT-#' => first group will be 'CONFLICT-1'.
        self::getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'CONFLICT-1',
        ]);

        $users = array_slice($this->students, 0, 4);

        $data = (object)[
            'allocateby' => 'random',
            'seed' => 999,
            'namingscheme' => 'CONFLICT-#',
            'grouping' => 0,
            'groupingname' => '',
            'enablegroupmessaging' => 0,
            'mode' => GROUPTOOL_GROUPS_AMOUNT,
            'numberofgroups' => 2,
            'numberofmembers' => 0,
        ];

        $beforegroups = groups_get_all_groups($this->course->id);
        $beforecount = is_array($beforegroups) ? count($beforegroups) : 0;

        $result = $this->call_create_groups_private($grouptool, $data, $users, 2, 2, false);

        // Should fail (because at least one group name exists).
        $this->assertTrue($result[0]);
        $this->assertIsString($result[1]);
        $this->assertNotSame(get_string('groups_created', 'grouptool'), $result[1]);

        // Verify cleanup: count of groups should not increase.
        $aftergroups = groups_get_all_groups($this->course->id);
        $aftercount = is_array($aftergroups) ? count($aftergroups) : 0;
        $this->assertSame($beforecount, $aftercount, 'On failure, created groups should be deleted (cleanup).');

        // Also ensure no agrp entries were left behind for newly created groups (best-effort).
        // Since groups should have been deleted, there should be no agrps referencing non-existing groups.
        $sql = "SELECT ga.id
              FROM {grouptool_agrps} ga LEFT JOIN {groups} g ON g.id = ga.groupid
             WHERE ga.grouptoolid = ? AND g.id IS NULL";
        $orphans = $DB->get_fieldset_sql($sql, [$grouptool->get_grouptool()->id]);
        $this->assertEmpty($orphans, 'Expected no orphaned agrp records after cleanup.');
    }

    /**
     * Unit test for create_fromto_groups(): creation mode (no preview).
     *
     * Verifies:
     * - creates the expected number of Moodle groups (from..to inclusive)
     * - creates a grouptool_agrps entry for each created group
     * - if numberofmembers is set, it writes grpsize on the new agrps and enables use_size on the instance
     *
     * Note: create_fromto_groups() is private, so we call it via Reflection.
     *
     * @covers \mod_grouptool\grouptool::create_fromto_groups
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \ReflectionException
     */
    public function test_create_fromto_groups_creates_groups_agrps_and_sets_size(): void {
        global $DB;

        // Ensure allow_reg + use_size initially false, so we can verify it becomes true.
        $grouptool = $this->create_instance(['use_size' => 0, 'grpsize' => 3]);

        $from = 1;
        $to = 3;

        $data = (object)[
            'from' => $from,
            'to' => $to,
            'digits' => 2,
            'namingscheme' => 'FT-#',
            'grouping' => 0,
            'groupingname' => '',
            'enablegroupmessaging' => 0,
            'numberofmembers' => 5,
        ];

        $beforegroups = groups_get_all_groups($this->course->id);
        $beforecount = is_array($beforegroups) ? count($beforegroups) : 0;

        // Call private method via reflection.
        $ref = new \ReflectionClass($grouptool);
        $method = $ref->getMethod('create_fromto_groups');

        $result = $method->invoke($grouptool, $data, false);

        // Assert success.
        $this->assertIsArray($result);
        $this->assertFalse($result[0]);
        $this->assertSame(get_string('groups_created', 'grouptool'), $result[1]);

        // Verify group count increased by 3.
        $aftergroups = groups_get_all_groups($this->course->id);
        $aftercount = is_array($aftergroups) ? count($aftergroups) : 0;
        $this->assertSame($beforecount + 3, $aftercount);

        // Verify the expected group names exist.
        $this->assertNotFalse(groups_get_group_by_name($this->course->id, 'FT-01'));
        $this->assertNotFalse(groups_get_group_by_name($this->course->id, 'FT-02'));
        $this->assertNotFalse(groups_get_group_by_name($this->course->id, 'FT-03'));

        // Verify each created group has an agrp entry and grpsize set to 5.
        foreach (['FT-01', 'FT-02', 'FT-03'] as $name) {
            $g = groups_get_group_by_name($this->course->id, $name);
            $this->assertNotEmpty($g);
            $agrp = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $grouptool->get_grouptool()->id,
                'groupid' => $g,
            ], '*', MUST_EXIST);

            $this->assertSame(5, (int)$agrp->grpsize);
        }

        // Verify use_size was enabled on the instance in DB.
        $instancerecord = $DB->get_record('grouptool', ['id' => $grouptool->get_grouptool()->id], '*', MUST_EXIST);
        $this->assertSame(1, (int)$instancerecord->use_size);
    }
    /**
     * 1) If use_queue is disabled, fill_from_queue() should do nothing and return true.
     *
     * @covers \mod_grouptool\grouptool::fill_from_queue
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_fill_from_queue_use_queue_disabled_returns_true(): void {
        $grouptool = $this->create_instance(['use_queue' => 0]);

        // Pick any existing agrpid.
        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $this->assertNotEmpty($agrps);
        $agrpid = (int)array_key_first($agrps);

        $this->assertTrue($grouptool->fill_from_queue($agrpid));
    }

    /**
     * 2) If queue has entries and group has free space, fill_from_queue() should:
     * - move user from queue to registered
     * - add user to Moodle group when immediate_reg is enabled
     * - delete the queue entry
     * - send a Moodle message to the user
     *
     * @covers \mod_grouptool\grouptool::fill_from_queue
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_fill_from_queue_moves_user_registers_adds_member_and_sends_message(): void {
        global $DB, $USER;

        $grouptool = $this->create_instance([
            'use_queue' => 1,
            'use_size' => 1,
            'grpsize' => 10,
            'immediate_reg' => 1,
            'allow_multiple' => 1,
            'choose_max' => 99,
        ]);

        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $this->assertNotEmpty($agrps);
        $agrpid = (int)array_key_first($agrps);

        $groupdata = $grouptool->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);
        $this->assertNotEmpty($groupdata);
        $groupid = (int)$groupdata->id;

        $userid = (int)$this->students[0]->id;

        $DB->delete_records('grouptool_registered', ['userid' => $userid, 'agrpid' => $agrpid]);

        groups_remove_member($groupid, $userid);

        $queue = (object)[
            'agrpid' => $agrpid,
            'userid' => $userid,
            'timestamp' => time() - 100,
            'modified_by' => (int)$USER->id,
        ];
        $queueid = $DB->insert_record('grouptool_queued', $queue, true);

        $sink = $this->redirectMessages();

        $ok = $grouptool->fill_from_queue($agrpid);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($ok);

        $this->assertFalse($DB->record_exists('grouptool_queued', ['id' => $queueid]));

        $this->assertTrue($DB->record_exists('grouptool_registered', [
            'userid' => $userid,
            'agrpid' => $agrpid,
        ]));

        $members = groups_get_members($groupid);
        $this->assertArrayHasKey($userid, $members);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('mod_grouptool', $msg->component);
        $this->assertSame('grouptool_moveupreg', $msg->eventtype);
        $this->assertSame($userid, (int)$msg->useridto);
    }

    /**
     * 3) If use_size is enabled and group is already full, fill_from_queue() should:
     * - not register the queued user
     * - not delete the queue entry
     * - not send a message
     *
     * @covers \mod_grouptool\grouptool::fill_from_queue
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_fill_from_queue_does_nothing_when_group_is_full_and_sends_no_message(): void {
        global $DB, $USER;

        $grouptool = $this->create_instance([
            'use_queue' => 1,
            'use_size' => 1,
            'grpsize' => 1,
            'immediate_reg' => 0,
            'allow_multiple' => 1,
            'choose_min' => 1,
            'choose_max' => 99,
        ]);

        $agrps = $grouptool->get_active_groups(false, false, 0, 0, 0, false);
        $this->assertNotEmpty($agrps);
        $agrpid = (int)array_key_first($agrps);

        $groupdata = $grouptool->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);
        $this->assertNotEmpty($groupdata);
        $groupid = (int)$groupdata->id;

        $existinguserid = (int)$this->students[1]->id;
        $DB->delete_records('grouptool_registered', ['userid' => $existinguserid, 'agrpid' => $agrpid]);

        $fullrecord = (object)[
            'groupid' => $groupid,
            'agrpid' => $agrpid,
            'userid' => $existinguserid,
            'timestamp' => time() - 200,
            'modified_by' => (int)$USER->id,
        ];
        $DB->insert_record('grouptool_registered', $fullrecord);

        $queueduserid = (int)$this->students[2]->id;
        $DB->delete_records('grouptool_registered', ['userid' => $queueduserid, 'agrpid' => $agrpid]);

        $queue = (object)[
            'agrpid' => $agrpid,
            'userid' => $queueduserid,
            'timestamp' => time() - 100,
            'modified_by' => (int)$USER->id,
        ];
        $queueid = $DB->insert_record('grouptool_queued', $queue, true);

        $sink = $this->redirectMessages();

        $ok = $grouptool->fill_from_queue($agrpid);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($ok);

        $this->assertTrue($DB->record_exists('grouptool_queued', ['id' => $queueid]));
        $this->assertFalse($DB->record_exists('grouptool_registered', [
            'userid' => $queueduserid,
            'agrpid' => $agrpid,
        ]));

        $this->assertCount(0, $messages);
    }
}
