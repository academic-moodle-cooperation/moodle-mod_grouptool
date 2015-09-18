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
 * The mod_grouptool_group_graded event.
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

/**
 * The \mod_grouptool\group_graded class holds the logic for the event
 *
 * @package       mod_grouptool
 * @since         Moodle 2.7
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_graded extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grouptool';
    }

    /**
     * Convenience method to create event object from data when grading group with certain id
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $data grading data to log
     * @return \mod_grouptool\group_graded event object
     */
    public static function create_direct(\stdClass $cm, \stdClass $data) {
        // Trigger overview event.
        $data->type = 'group';
        $event = self::create(array(
            'objectid' => $cm->instance,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$data,
        ));
        return $event;
    }

    /**
     * Convenience method to create event object from data when grading group without knowledge of the groups id
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $data grading data to log
     * @return \mod_grouptool\group_graded event object
     */
    public static function create_without_groupid(\stdClass $cm, \stdClass $data) {
        // Trigger overview event.
        $data->type = 'users';
        $event = self::create(array(
            'objectid' => $cm->instance,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$data,
        ));
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        switch ($this->data['other']['type']) {
            case 'group':
                return "The user with id '".$this->userid."' group-graded group with id '".$this->data['other']['groupid'].
                       "' using ".$this->objecttable." with course module id '$this->contextinstanceid'.";
            break;
            case 'users':
                return "The user with id '".$this->userid."' group-graded the user(s) with id(s) '".
                       implode(', ', $this->data['other']['selected'])."' using ".$this->objecttable.
                       " with course module id '$this->contextinstanceid'.";
            break;
        }
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgroupgraded', 'grouptool');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->data['other']['type'] == 'users') {
            return new \moodle_url("/mod/$this->objecttable/view.php", array('id'            => $this->contextinstanceid,
                                                                             'tab'           => 'grading',
                                                                             'activity'      => $this->data['other']['cmtouse'],
                                                                             'refresh_table' => 1));
        }
        return new \moodle_url("/mod/$this->objecttable/view.php", array('id'            => $this->contextinstanceid,
                                                                         'tab'           => 'grading',
                                                                         'activity'      => $this->data['other']['cmtouse'],
                                                                         'groupid'       => $this->data['other']['groupid'],
                                                                         'refresh_table' => 1));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        if ($this->data['other']['type'] == 'users') {
            return array($this->courseid, 'grouptool', 'grade group',
                         "view.php?id=".$this->contextinstanceid."&tab=grading&activity=".$this->data['other']['cmtouse'].
                         "&refresh_table=1", "group-grade users ".implode(", ", $this->data['other']['selected']),
                         $this->contextinstanceid);
        }
        return array($this->courseid, 'grouptool', 'grade group',
                           "view.php?id=".$this->contextinstanceid."&tab=grading&activity=".$this->data['other']['cmtouse'].
                           "&groupid=".$this->data['other']['groupid']."&refresh_table=1",
                           'group-grade group '.$this->data['other']['groupid'], $this->contextinstanceid);
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
            throw new \coding_exception('The registration_created event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if ($this->data['other']['type'] == 'group' && empty($this->data['other']['groupid'])) {
            throw new \coding_exception('ID of group to be graded has to be specified!');
        }

        if ($this->data['other']['type'] == 'users' && empty($this->data['other']['selected'])) {
            throw new \coding_exception('Users to be graded have to be specified!');
        }

        if (empty($this->data['other']['cmtouse'])) {
            throw new \coding_exception('Coursemodule to be graded has to be specified!');
        }
    }
}