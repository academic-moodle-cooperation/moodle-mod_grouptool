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
 * The mod_grouptool_queue_entry_deleted event.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\queue_entry_deleted class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_entry_deleted extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'grouptool_queued';
    }

    /**
     * Convenience method to create event object if queue entry is deleted by observer/eventhandler
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $entrydata data of deleted queue entry
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_via_eventhandler(\stdClass $cm, \stdClass $entrydata) {
        $entrydata->source = 'event';
        $event = self::create([
            'objectid' => $entrydata->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$entrydata,
        ]);
        return $event;
    }

    /**
     * Convenience method to create event object if queue entry is deleted i.e. because user has enough registrations
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $entrydata data of deleted queue entry
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_limit_violation(\stdClass $cm, \stdClass $entrydata) {
        $entrydata->reason = 'registration limit violation';
        $event = self::create([
            'objectid' => $entrydata->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$entrydata,
        ]);
        return $event;
    }

    /**
     * Convenience method to create event object if queue entry is deleted by direct user action
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $entrydata data of deleted queue entry
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_direct(\stdClass $cm, \stdClass $entrydata) {
        $entrydata->source = null;
        $entrydata->reason = null;
        $event = self::create([
            'objectid' => $entrydata->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$entrydata,
        ]);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (!empty($this->data['other']['reason'])) {
            $reason = ' due to '.$this->data['other']['reason'];
        } else {
            $reason = '';
        }

        if (!empty($this->data['other']['source'])) {
            $source = ' by '.$this->data['other']['source'];
        } else {
            $source = '';
        }

        return "The queue entry for the user with id '".$this->data['other']['userid'].
               "' in agrp with id '".$this->data['other']['agrpid']."'".
               " in ".$this->objecttable." with course module id '$this->contextinstanceid' was deleted".$source.$reason;
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventqueueentrydeleted', 'grouptool');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php", [
            'id'      => $this->contextinstanceid,
            'tab'     => 'overview',
            'groupid' => $this->data['other']['groupid']
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
            throw new \coding_exception('The queue_entry_deleted event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        // ...groupid, agrpid, userid.
        if (empty($this->data['other']['groupid'])) {
            throw new \coding_exception('Groupid has to be specified!');
        }

        if (empty($this->data['other']['agrpid'])) {
            throw new \coding_exception('Active-Group-ID has to be specified!');
        }

        if (empty($this->data['other']['userid'])) {
            throw new \coding_exception('User-ID has to be specified!');
        }
    }
}
