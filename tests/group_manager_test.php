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
 * Unit tests for mod_grouptool group_manager class.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Anne Kreppenhofer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local\model;

use mod_grouptool\local\tests\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for group_manager.
 *
 * @group mod_grouptool
 *
 * @covers \mod_grouptool\local\model\group_manager
 */
final class group_manager_test extends base {
    /**
     * Tests get_active_groups().
     *
     * @covers \mod_grouptool\local\model\group_manager::get_active_groups
     */
    public function test_get_active_groups(): void {
        global $DB;

        $grouptool = $this->create_instance();
        $groupmanager = $this->create_group_manager($grouptool);

        $allagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', '');
        $activeagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', 'active = 1');

        $DB->set_field('grouptool_agrps', 'active', 0, ['id' => $activeagrpsids[1]]);
        $DB->set_field('grouptool_agrps', 'active', 0, ['id' => $activeagrpsids[0]]);

        $activeagrpsids = $DB->get_fieldset_select('grouptool_agrps', 'id', 'active = 1');

        $this->assertCount(count($activeagrpsids), $groupmanager->get_active_groups());
        $this->assertCount(
            count($allagrpsids),
            $groupmanager->get_active_groups(false, false, 0, 0, 0, true, true)
        );
    }

    /**
     * Tests create_groups() in preview mode.
     *
     * @covers \mod_grouptool\local\model\group_manager::create_groups
     */
    public function test_create_groups_preview_only_does_not_create_groups(): void {
        $grouptool = $this->create_instance();
        $groupmanager = $this->create_group_manager($grouptool);

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

        $result = $groupmanager->create_groups($data, $users, 2, 2, true);

        $this->assertIsArray($result);
        $this->assertFalse($result[0]);
        $this->assertIsString($result[1]);
        $this->assertStringContainsString('<table', $result[1]);

        $after = groups_get_all_groups($this->course->id);
        $aftercount = is_array($after) ? count($after) : 0;

        $this->assertSame($beforecount, $aftercount);
    }
}