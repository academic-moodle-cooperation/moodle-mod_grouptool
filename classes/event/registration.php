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
 * The mod_grouptool\registration event base class.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\registration class serves as common base for registration events
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class registration extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'grouptool_registered';
    }

    /**
     * Convenience method for events created via observer/eventhandler
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $regdata registration entries data
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_via_eventhandler(\stdClass $cm, \stdClass $regdata) {
        $regdata->source = 'event';
        $event = self::create([
            'objectid' => $regdata->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$regdata,
        ]);
        return $event;
    }

    /**
     * Convenience method for events created via direct user action
     *
     * @param \stdClass $cm course module object
     * @param \stdClass $regdata registration entries data
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_direct(\stdClass $cm, \stdClass $regdata) {
        $regdata->source = null;
        $event = self::create([
            'objectid' => $regdata->id,
            'context'  => \context_module::instance($cm->id),
            'other'    => (array)$regdata,
        ]);
        return $event;
    }

    /**
     * Returns description of what happened. Will be overwritten!
     *
     * @return string
     */
    public function get_description() {
        return '';
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventregistrationcreated', 'grouptool');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url("/mod/grouptool/view.php", ['id'      => $this->contextinstanceid,
                                                           'tab'     => 'overview',
                                                           'groupid' => $this->data['other']['groupid']]);
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return [
            $this->courseid,
            'grouptool',
            'register',
            "view.php?id=".$this->contextinstanceid."&tab=overview&groupid=".$this->data['other']['groupid'],
            'via event agrp='.$this->data['other']['agrpid'].' user='.$this->data['other']['userid'],
            $this->contextinstanceid];
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        // Make sure this class is never used without proper object details.
        if (empty($this->objectid) || empty($this->objecttable)) {
            throw new \coding_exception('The '.self::get_name().' event must define objectid and object table.');
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
