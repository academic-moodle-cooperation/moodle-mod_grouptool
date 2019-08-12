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
 * Capability definitions for mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
        'mod/grouptool:addinstance' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'legacy' => [
                'editingteacher' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW,
            ]
        ],

        'mod/grouptool:view_description' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'guest' => CAP_ALLOW,
                'student' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:view_own_registration' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'student' => CAP_ALLOW,
            ]
        ],

        'mod/grouptool:export' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:view_groups' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'student' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:view_regs_group_view' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:view_regs_course_view' => [
            'captype' => 'read',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:register' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'student' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:grade' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:grade_own_group' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:administrate_groups' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:create_groups' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:create_groupings' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:move_students' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:register_students' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],

        'mod/grouptool:unregister_students' => [
            'captype' => 'write',
            'contextlevel' => CONTEXT_MODULE,
            'legacy' => [
                'teacher' => CAP_ALLOW,
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            ]
        ],
];

