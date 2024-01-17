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
 * The mod_grouptool_user_moved event.
 *
 * This event is used when users are moved from 1 to another group or when they are promoted
 * from queue to normal registration status without themselves taking action
 * (as in events, deregistration of other users and dequeuing).
 * Moving students by their action (change group, etc.) is considered normal unqueue/unregistration and following registration.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\user_moved class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_moved extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grouptool_registered';
    }

    /**
     * Convenience method usable if user has been promoted/moved from the queue to regular registrations
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $from data from which queue entry the user has been moved
     * @param \stdClass $to data to which registration entry the user has been moved
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function promotion_from_queue(\stdClass $cm, \stdClass $from, \stdClass $to) {
        $event = self::create([
            'objectid' => $to->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => [
                'from' => (array)$from,
                'to' => (array)$to,
                'type' => 'promotion',
            ],
        ]);
        return $event;
    }

    /**
     * Convenience method usable if user has been moved from one group to another
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $from data from which queue entry the user has been moved
     * @param \stdClass $to data to which registration entry the user has been moved
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function move(\stdClass $cm, \stdClass $from, \stdClass $to) {
        $event = self::create([
            'objectid' => $to->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => [
                'from' => (array)$from,
                'to' => (array)$to,
                'type' => 'move',
            ],
        ]);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        switch ($this->data['other']['type']) {
            case 'promotion':
                return "The user with id '".$this->data['other']['to']['userid']."' was promoted".
                       " from the queue of active-group with id '".$this->data['other']['from']['agrpid']."' (group id '".
                       $this->data['other']['from']['groupid']."')".
                       " in ".$this->objecttable." with course module id '$this->contextinstanceid'";
            break;
            default:
            case 'move':
                return "The user with id '".$this->data['other']['to']['userid']."' was moved".
                       " from active-group with id '".$this->data['other']['from']['agrpid']."' (group id '".
                       $this->data['other']['from']['groupid']."')"." to active-group with id '".
                       $this->data['other']['to']['agrpid']."' (group id '".$this->data['other']['to']['groupid']."')".
                       " in ".$this->objecttable." with course module id '$this->contextinstanceid'";
            break;
        }
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventusermoved', 'grouptool');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php", [
            'id' => $this->contextinstanceid,
            'tab' => 'overview',
            'groupid' => $this->data['other']['to']['groupid'],
        ]);
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
        if (empty($this->objectid) || empty($this->objecttable)) {
            throw new \coding_exception('The user_moved event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if (empty($this->data['other']['from'])) {
            throw new \coding_exception('From has to be specified in event!');
        } else {
            if (empty($this->data['other']['from']['groupid'])) {
                throw new \coding_exception('Group-ID from \'from\' has to be specified in event!');
            }
            if (empty($this->data['other']['from']['agrpid'])) {
                throw new \coding_exception('Active Group-ID from \'from\' has to be specified in event!');
            }
            if (empty($this->data['other']['from']['userid'])) {
                throw new \coding_exception('User-ID from \'from\' has to be specified in event!');
            }
        }

        if (empty($this->data['other']['to'])) {
            throw new \coding_exception('To has to be specified in event!');
        } else {
            if (empty($this->data['other']['to']['groupid'])) {
                throw new \coding_exception('Group-ID from \'to\' has to be specified in event!');
            }
            if (empty($this->data['other']['to']['agrpid'])) {
                throw new \coding_exception('Active Group-ID from \'to\' has to be specified in event!');
            }
            if (empty($this->data['other']['to']['userid'])) {
                throw new \coding_exception('User-ID from \'to\' has to be specified in event!');
            }
        }
    }
}
