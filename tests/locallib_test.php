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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page!
}

// Make sure the code being tested is accessible.
global $CFG;
require_once($CFG->dirroot . '/mod/grouptool/locallib.php'); // Include the code to test!

/**
 * This class contains the test cases for functions in locallib.
 *
 * @package   mod_grouptool
 * @author    Hannes Laimer
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_locallib_test extends \mod_grouptool\local\tests\base {
    /*
     * The base test class already contains a setUp-method setting up a course including users and groups.
     */


    /**
     * Tests get_name method in locallib
     *
     * 1 Assertions
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_name() {
        $grouptool = $this->create_instance(['name' => 'GT01']);
        $this->assertEquals($grouptool->get_name(), 'GT01');
    }


    /**
     * Tests get_active_groups method in locallib
     *
     * 2 Assertions
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_groups() {
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

    public function test_groups_get_all_groups() {
        // TODO: wirite test for groups_get_all_groups().
    }
}

