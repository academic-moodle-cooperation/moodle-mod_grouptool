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
 * Definition of handling some core events
 *
 * @package       mod
 * @subpackage    grouptool
 * @copyright     2012 onwards Philipp Hager {@link e0803285@gmail.com}
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$handlers = array (
        // We get groupid, userid with this handler.
        'groups_member_added' => array (
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'group_add_member_handler',
                'schedule'         => 'instant'
        ),
        // We get groupid, userid with this handler.
        'groups_member_removed' => array (
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'group_remove_member_handler',
                'schedule'         => 'instant'
        ),
        // We get courseid, userid with this handler (user deleted from all coursegroups).
        'groups_members_removed' => array (
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'groups_remove_member_handler',
                'schedule'         => 'instant'
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.
        'groups_group_deleted' => array (
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'group_deleted_handler',
                'schedule'         => 'instant'
        ),
        // We get courseid (as plain integer) with this handler (delete all groups in a course).
        'groups_groups_deleted' => array(
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'groups_deleted_handler',
                'schedule'         => 'instant'
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.
        'groups_group_created' => array(
                'handlerfile'      => '/mod/grouptool/eventhandlers.php',
                'handlerfunction'  => 'group_created_handler',
                'schedule'         => 'instant'
        )

);
