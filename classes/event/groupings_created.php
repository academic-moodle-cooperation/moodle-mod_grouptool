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
 * The mod_grouptool_groupings_created event.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\groupings_created class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupings_created extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grouptool';
    }

    /**
     * Convenience method to create the event object from course module object and the created groups ids
     *
     * @param \stdClass $cm course module object
     * @param int[] $ids array of ids (integers)
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_from_object(\stdClass $cm, array $ids) {
        $event = self::create([
            'objectid' => $cm->instance,
            'context'  => \context_module::instance($cm->id),
            'other'    => $ids,
        ]);
        return $event;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php", ['id' => $this->contextinstanceid, 'tab' => 'overview']);
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
            'create groupings',
            'view.php?id='.$this->contextinstanceid.'&tab=overview',
            'create groupings for groups:'.implode("|", $this->data['other']),
            $this->contextinstanceid
        ];
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' used '{$this->objecttable}' with the " .
               "course module id '$this->contextinstanceid' to create groupings for groups with ids ".
               implode(', ', $this->data['other']).".";
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventgroupingscreated', 'grouptool');
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
            throw new \coding_exception('The overview_exported event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if (!is_array($this->data['other'])) {
            throw new \coding_exception('IDs of created groupings have to be specified!');
        }
    }
}
