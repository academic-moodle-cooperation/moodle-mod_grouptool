<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Generator file for mod_grouptool's PHPUnit tests
 *
 * @package   mod_grouptool
 * @category  test
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * grouptool module data generator class
 *
 * @package   mod_grouptool
 * @category  test
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_generator extends testing_module_generator {

    /**
     * Generator method creating a mod_grouptool instance.
     * @
     *
     * @param array|stdClass $record (optional) Named array containing instance settings
     * @param array $options (optional) general options for course module. Can be merged into $record
     * @return stdClass record from module-defined table with additional field cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        $timecreated = time();

        $defaultsettings = [
            'name' => 'Grouptool',
            'intro' => 'Introtext',
            'introformat' => 1,
            'alwaysshowdescription' => 1,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
            'timedue' => 0,
            'timeavailable' => $timecreated,
            'show_members' => 1,
            'allow_reg' => 1,
            'immediate_reg' => 1,
            'allow_unreg' => 1,
            'grpsize' => 3,
            'use_size' => 1,
            'use_queue' => 1,
            'limit_users_queues' => 1,
            'users_queues_limit' => 2,
            'limit_groups_queues' => 1,
            'groups_queues_limit' => 2,
            'allow_multiple' => 1,
            'choose_min' => 1,
            'choose_max' => 3,
            'ifmemberadded' => 1,
            'ifmemberremoved' => 1,
            'ifgroupdeleted' => 1,
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }
}
