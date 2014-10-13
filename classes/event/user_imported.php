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
 * The mod_grouptool_user_imported event.
 *
 * @package       mod_grouptool
 * @since         Moodle 2.7
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

class user_imported extends \core\event\base {
    /**
     * Init method.
     *
     * Please override this in extending class and specify objecttable.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grouptool_registered';
    }

    public static function import_forced(\stdClass $cm, $id, $agrp, $group, $user) {
        $event = \mod_grouptool\event\user_imported::create(array(
            'objectid' => $id,
            'context'  => \context_module::instance($cm->id),
            'other'    => array('agrp' => $agrp, 'group' => $group, 'user' => $user, 'type' => 'force'),
        ));
        return $event;
    }

    public static function import(\stdClass $cm, $group, $user) {
        $event = \mod_grouptool\event\user_imported::create(array(
            'objectid' => 0,
            'context'  => \context_module::instance($cm->id),
            'other'    => array('group' => $group, 'user' => $user, 'type' => ''),
        ));
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if ($this->data['other']['type'] == 'force') {
            $force = ' and a registration in active-group '.$this->data['other']['agrp'].' has been forced';
        } else {
            $force = '';
        }
        return "The user with id '".$this->data['other']['user']."' was imported".
               " in the group with id '".$this->data['other']['group']."'".$force.
               " in grouptool with course module id '$this->contextinstanceid' by user with id '$this->userid'";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventuserimported', 'grouptool');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php", array('id'      => $this->contextinstanceid,
                                                                         'tab'     => 'overview',
                                                                         'groupid' => $this->data['other']['group']));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        if ($this->data['other']['type'] == 'force') {
            return array($this->courseid, 'grouptool', 'import',
                               "view.php?id=".$this->contextinstanceid."&tab=overview&groupid=".$this->data['other']['group'],
                               'group='.$this->data['other']['group'].' agrp='.$this->data['other']['agrp'].' user='.$this->data['other']['user'], $this->contextinstanceid);
        } else {
            return array($this->courseid, 'grouptool', 'import',
                               "view.php?id=".$this->contextinstanceid."&tab=overview&groupid=".$this->data['other']['group'],
                               'group='.$this->data['other']['group'].' user='.$this->data['other']['user'], $this->contextinstanceid);
        }
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        // Make sure this class is never used without proper object details.
        if (($this->data['other']['type'] == 'force')
            && empty($this->objectid) || empty($this->objecttable)) {
            throw new \coding_exception('The user_imported event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if (empty($this->data['other']['group'])) {
            throw new \coding_exception('Group has to be specified!');
        }

        if ($this->data['other']['type'] == 'force' && empty($this->data['other']['agrp'])) {
            throw new \coding_exception('Active-Group has to be specified!');
        }

        if (empty($this->data['other']['user'])) {
            throw new \coding_exception('User has to be specified!');
        }
    }
}