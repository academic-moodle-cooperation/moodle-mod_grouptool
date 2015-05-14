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
 * db/access.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
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

        'mod/grouptool:view_regs_group_view' => array(
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'legacy' => array(
                        'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'manager' => CAP_ALLOW
                )
        ),

        'mod/grouptool:view_regs_course_view' => array(
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

        'mod/grouptool:administrate_groups' => array(
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

