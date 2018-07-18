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
 * The mod_grouptool_registration_deleted event.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\registration_deleted class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration_deleted extends registration {
    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The registration for the user with id '".$this->data['other']['userid'].
               "' in agrp with id '".$this->data['other']['agrpid']."' (= group with id '".$this->data['other']['groupid']."')".
               " in ".$this->objecttable." with course module id '$this->contextinstanceid' has been deleted".
               (!empty($this->data['other']['source']) ? ' by '.$this->data['other']['source'] : '');
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventregistrationdeleted', 'grouptool');
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return [
            $this->courseid,
            $this->objecttable,
            'unregister',
            "view.php?id=".$this->contextinstanceid."&tab=overview&groupid=".$this->data['other']['groupid'],
            'agrp='.$this->data['other']['agrpid'].' user='.$this->data['other']['userid'],
            $this->contextinstanceid
        ];
    }
}
