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
 * Webservice definition
 *
 * @package       mod_grouptool
 * @author        Philipp Hager
 * @copyright     2017 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$services = [
    'mod_grouptool_administrate_groups' => [
            'functions' => [
                'mod_grouptool_delete_group',
                'mod_grouptool_rename_group',
                'mod_grouptool_resize_group',
                'mod_grouptool_activate_group',
                'mod_grouptool_deactivate_group',
                'mod_grouptool_reorder_groups',
                'mod_grouptool_swap_groups'
            ],
            /* If set, the web service user need this capability to access
            * any function of this service. For example: 'some/capability:specified'. */
            'requiredcapability' => 'mod/grouptool:administrate_groups',
            /* If enabled, the Moodle administrator must link some user to this service
             * into the administration. */
            'restrictedusers' => 0,
            // If enabled, the service can be reachable on a default installation.
            'enabled' => 1,
    ]
];

$functions = [
        'mod_grouptool_delete_group' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'delete_group',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Delete a single group.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_rename_group' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'rename_group',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Rename a single group.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_resize_group' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'resize_group',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Change group size.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_activate_group' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'activate_group',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Set group to active for this grouptool instance.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_deactivate_group' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'deactivate_group',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Set group to inactive for this grouptool instance.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_reorder_groups' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'reorder_groups',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Set order for multiple groups.',
        'type'        => 'write',
        'ajax'        => true,
        ],
        'mod_grouptool_swap_groups' => [
        'classname'   => 'mod_grouptool_external',
        'methodname'  => 'swap_groups',
        'classpath'   => 'mod/grouptool/externallib.php',
        'description' => 'Swap positions of 2 groups.',
        'type'        => 'write',
        'ajax'        => true,
        ],
];
