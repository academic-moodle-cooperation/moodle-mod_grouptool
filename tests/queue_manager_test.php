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
 * Unit tests for mod_grouptool queue_manager class.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Anne Kreppenhofer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local\model;

use coding_exception;
use dml_exception;
use mod_grouptool\local\tests\base;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for queue_manager.
 *
 * @group mod_grouptool
 *
 * @covers \mod_grouptool\local\model\queue_manager
 */
final class queue_manager_test extends base {
    /**
     * If use_queue is disabled, fill_from_queue() should do nothing and return true.
     *
     * @covers \mod_grouptool\local\model\queue_manager::fill_from_queue
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_fill_from_queue_use_queue_disabled_returns_true(): void {
        $grouptool = $this->create_instance(['use_queue' => 0]);

        $groupmanager = $this->create_group_manager($grouptool);
        $queuemanager = $this->create_queue_manager($grouptool);

        $agrps = $groupmanager->get_active_groups(false, false, 0, 0, 0, false);
        $this->assertNotEmpty($agrps);

        $agrpid = (int)array_key_first($agrps);

        $this->assertTrue($queuemanager->fill_from_queue($agrpid));
    }
}
