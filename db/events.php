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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * db/events.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array (
        array (
            'eventname'    => '\core\event\group_member_added',
            'callback'     => 'mod_grouptool_observer::group_member_added',
            'includefile'  => '/mod/grouptool/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get groupid, userid with this handler.

        // We get groupid, userid with this handler.
        array (
            'eventname'    => 'core\event\group_member_removed',
            'callback'     => 'mod_grouptool_observer::group_member_removed',
            'includefile'  => '/mod/grouptool/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),

        array (
            'eventname'    => 'core\event\group_deleted',
            'callback'     => 'mod_grouptool_observer::group_deleted',
            'includefile'  => '/mod/grouptool/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.
        

        array (
            'eventname'    => 'core\event\group_created',
            'callback'     => 'mod_grouptool_observer::group_created',
            'includefile'  => '/mod/grouptool/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.

);
