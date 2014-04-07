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
 * db/log.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;
/* It's not fully compatible with core this way so we should use standard style.
 * @todo using standard-action-pattern add/delete/update/view + additional info
 * If we don't, our filter-abilities in log-view are very restricted!
 */
$logs = array(
        array('module'   => 'grouptool',
              'action'   => 'add',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'update',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'delete',
              'mtable'   => 'grouptool',
              'field'    => 'name'),

        array('module'   => 'grouptool',
              'action'   => 'view administration',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'view grading',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'view registration',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'view import',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'view overview',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'view userlist',
              'mtable'   => 'grouptool',
              'field'    => 'name'),

        array('module'   => 'grouptool',
              'action'   => 'export',
              'mtable'   => 'grouptool',
              'field'    => 'name'),

        array('module'   => 'grouptool',
              'action'   => 'register',
              'mtable'   => 'user',
              'field'    => $DB->sql_concat('firstname', "' '" , 'lastname')),
        array('module'   => 'grouptool',
              'action'   => 'unregister',
              'mtable'   => 'user',
              'field'    => $DB->sql_concat('firstname', "' '" , 'lastname')),
        array('module'   => 'grouptool',
              'action'   => 'resolve queue',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'import',
              'mtable'   => 'group',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'push registrations',
              'mtable'   => 'grouptool',
              'field'    => 'name'),

        array('module'   => 'grouptool',
              'action'   => 'create groups',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'create groupings',
              'mtable'   => 'grouptool',
              'field'    => 'name'),
        array('module'   => 'grouptool',
              'action'   => 'update agrps',
              'mtable'   => 'grouptool',
              'field'    => 'name'),

        array('module'   => 'grouptool',
              'action'   => 'grade group',
              'mtable'   => 'group',
              'field'    => 'name')
);

