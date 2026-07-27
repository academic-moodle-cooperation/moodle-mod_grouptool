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
 * Unit tests for mod_grouptool grouptool_instance class.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Anne Kreppenhofer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local;

use mod_grouptool\local\tests\base;

/**
 * Tests for grouptool_instance.
 *
 * @group mod_grouptool
 *
 * @covers \mod_grouptool\local\grouptool_instance
 */
final class grouptool_instance_test extends base {
    /**
     * Tests basic creation of a grouptool instance.
     *
     * @covers \mod_grouptool\local\grouptool_instance::__construct
     */
    public function test_create_instance(): void {
        global $DB;

        $grouptool = $this->create_instance();

        $this->assertNotEmpty($grouptool);
        $this->assertTrue($DB->record_exists('grouptool_agrps', [
            'grouptoolid' => $grouptool->get_grouptool()->id,
        ]));
    }

    /**
     * Tests get_name().
     *
     * @covers \mod_grouptool\local\grouptool_instance::get_name
     */
    public function test_get_name(): void {
        $grouptool = $this->create_instance(['name' => 'GT01']);

        $this->assertSame('GT01', $grouptool->get_name());
    }

    /**
     * Tests the basic getters.
     *
     * @covers \mod_grouptool\local\grouptool_instance::get_settings
     * @covers \mod_grouptool\local\grouptool_instance::get_course
     * @covers \mod_grouptool\local\grouptool_instance::get_cm
     * @covers \mod_grouptool\local\grouptool_instance::get_context
     * @covers \mod_grouptool\local\grouptool_instance::get_grouptool
     */
    public function test_getters_return_expected_objects(): void {
        $grouptool = $this->create_instance();

        $this->assertSame($this->course->id, $grouptool->get_course()->id);
        $this->assertSame((int)$grouptool->get_cm()->instance, $grouptool->get_grouptool()->id);
        $this->assertSame($grouptool->get_grouptool(), $grouptool->get_settings());
        $this->assertSame($grouptool->get_cm()->id, $grouptool->get_context()->instanceid);
    }
}
