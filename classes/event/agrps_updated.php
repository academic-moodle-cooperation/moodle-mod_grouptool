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
 * The mod_grouptool_agrps_updated event.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\agrps_updated class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agrps_updated extends \core\event\base {
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
     * Convenience method to create from course-module object
     *
     * @param \stdClass $cm course module object
     * @return \mod_grouptool\agrps_updated event object
     */
    public static function create_convenient(\stdClass $cm) {
        $event = self::create(array(
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
        ));
        return $event;
    }

    /**
     * Convenience method to create from course-module object and form data
     *
     * @param \stdClass $cm course module object
     * @param string $pattern pattern for group names
     * @param int $numgrps number of created groups
     * @param int|0 $groupingid optional id of grouping used for these groups (0 if not in grouping)
     * @return \mod_grouptool\agrps_updated event object
     */
    public static function create_groupcreation(\stdClass $cm, $pattern, $numgrps, $groupingid = 0) {
        $event = self::create(array(
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
            'other' => array('pattern' => $pattern,
                             'numgrps' => $numgrps,
                             'grouping' => $groupingid),
        ));
        return $event;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php",
                               array('id' => $this->contextinstanceid, 'tab' => 'overview'));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        if (!empty($this->data['other']['pattern'])) {
            return array($this->courseid, $this->objecttable, 'create groups',
                         'view.php?id=' . $this->contextinstanceid.'&tab=overview',
                         'create groups namescheme:'.$this->data['other']['pattern'].
                         ' numgroups:'.$this->data['other']['numgrps'],
                         $this->contextinstanceid);
        }
        return array($this->courseid,
                     $this->objecttable,
                     'update agrps',
                     'view.php?id='.$this->contextinstanceid.'&tab=overview',
                     $this->objectid,
                     $this->contextinstanceid);
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (!empty($this->data['other']['grouping'])) {
            $add = ' in grouping with id \''.$this->data['other']['grouping'].'\'';
        } else {
            $add = '';
        }
        if (!empty($this->data['other']['pattern'])) {
            if (!empty($add)) {
                $add .= ' affecting '.$this->data['other']['numgrps'].
                        ' groups (namepattern = \''.$this->data['other']['pattern'].'\')';
            } else {
                $add = ' affecting '.$this->data['other']['numgrps'].
                       ' groups (namepattern = \''.$this->data['other']['pattern'].'\')';
            }
        }

        return "The user with id '$this->userid' updated the active groups".
               "for '{$this->objecttable}' with the ".
               "course module id '$this->contextinstanceid'".$add.".";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventagrpsupdated', 'grouptool');
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
            throw new \coding_exception('The agrps_updated event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        // Make sure that all three are set if one of them ist set!
        if ((!empty($this->data['other']['pattern']) || !empty($this->data['other']['numgrps']))
             && (empty($this->data['other']['pattern']) || empty($this->data['other']['numgrps']))) {
            throw new \coding_exception('If any of pattern or numgrps are specified, every single one of them must be specified!');
        }
    }
}