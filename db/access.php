<?php
// This file is made for Moodle - http://moodle.org/
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
 * Capability definitions for the grouptool module
 *
 * The capabilities are loaded into the database table when the module is
 * installed or updated. Whenever the capability definitions are updated,
 * the module version number should be bumped up.
 *
 * The system has four possible values for a capability:
 * CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
 *
 * It is important that capability names are unique. The naming convention
 * for capabilities that are specific to modules and blocks is as follows:
 *   [mod/block]/<plugin_name>:<capabilityname>
 *
 * component_name should be the same as the directory name of the mod or block.
 *
 * Core moodle capabilities are defined thus:
 *    moodle/<capabilityclass>:<capabilityname>
 *
 * Examples: mod/forum:viewpost
 *           block/recent_activity:view
 *           moodle/site:deleteuser
 *
 * The variable name for the capability definitions array is $capabilities
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
// We haven't done this before so: @todo comment what's each capability is about!
        'mod/grouptool:addinstance' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
                'legacy' => array(
                        'editingteacher' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW,
                )
        ),

        'mod/grouptool:view_description' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'guest' => CAP_ALLOW,
                        'student' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:view_own_registration' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'student' => CAP_ALLOW,
                )
        ),

        'mod/grouptool:export' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),


        'mod/grouptool:view_groups' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'student' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:view_registrations' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:register' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'student' => CAP_ALLOW
                )
        ),

        'mod/grouptool:grade' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:grade_own_group' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:create_groups' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:create_groupings' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:move_students' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:register_students' => array(
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),
);

