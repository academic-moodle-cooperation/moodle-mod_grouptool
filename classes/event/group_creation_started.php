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
 * The mod_grouptool_group_creation_started event.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\group_creation_started class holds the logic for the event
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_creation_started extends \core\event\base {
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
     * Convenience method to create event object when creating groups by amount
     *
     * @param \stdClass $cm course module object
     * @param string $pattern name scheme used when creating groups
     * @param int $amount number of created groups
     * @param int|0 $grouping optional id of grouping used for created groups (0 if no grouping used)
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_groupamount(\stdClass $cm, $pattern, $amount, $grouping = 0) {
        $event = self::create([
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
            'other' => [
                'mode'     => 'groups_amount',
                'pattern'  => $pattern,
                'amount'   => $amount,
                'grouping' => $grouping
            ],
        ]);
        return $event;
    }

    /**
     * Convenience method to create event object when creating groups by amount of groupmembers
     *
     * @param \stdClass $cm course module object
     * @param string $pattern name scheme used when creating groups
     * @param int $amount number of created groups
     * @param int|0 $grouping optional id of grouping used for created groups (0 if no grouping used)
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_memberamount(\stdClass $cm, $pattern, $amount, $grouping = 0) {
        $event = self::create([
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
            'other' => [
                'mode'     => 'members_amount',
                'pattern'  => $pattern,
                'amount'   => $amount,
                'grouping' => $grouping
            ],
        ]);
        return $event;
    }

    /**
     * Convenience method to create event object when creating groups from X to Y
     *
     * @param \stdClass $cm course module object
     * @param string $pattern name scheme used when creating groups
     * @param int $from lowest index for created groups
     * @param int $to highest index for created groups
     * @param int|0 $grouping optional id of grouping used for created groups (0 if no grouping used)
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_fromto(\stdClass $cm, $pattern, $from, $to, $grouping = 0) {
        $event = self::create([
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
            'other' => [
                'mode'     => 'fromto',
                'pattern'  => $pattern,
                'from'     => $from,
                'to'         => $to,
                'grouping' => $grouping
            ],
        ]);
        return $event;
    }

    /**
     * Convenience method to create event object when creating 1-person-groups
     *
     * @param \stdClass $cm course module object
     * @param string $pattern name scheme used when creating groups
     * @param int|0 $grouping optional id of grouping used for created groups (0 if no grouping used)
     * @return \core\event\base event object
     * @throws \coding_exception
     */
    public static function create_person(\stdClass $cm, $pattern, $grouping = 0) {
        $event = self::create([
            'objectid' => $cm->instance,
            'context' => \context_module::instance($cm->id),
            'other' => [
                'mode'     => '1-person-groups',
                'pattern'  => $pattern,
                'grouping' => $grouping
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
        if (!empty($this->data['other']['grouping'])) {
            $add = ' and added these to the grouping with id \''.$this->data['other']['grouping'].'\'';
        } else {
            $add = '';
        }
        switch ($this->data['other']['mode']) {
            case 'groups_amount':
                return "The user with id '$this->userid' started creating groups via '{$this->objecttable}' with the " .
                       "course module id '$this->contextinstanceid' by defining the amount of groups (".
                       $this->data['other']['amount'].") using '".$this->data['other']['pattern']."' as pattern for the groupnames".
                       $add.".";
            break;
            case 'members_amount':
                return "The user with id '$this->userid' started creating groups via '{$this->objecttable}' with the " .
                       "course module id '$this->contextinstanceid' by defining the amount of members per group (".
                       $this->data['other']['amount'].") using '".$this->data['other']['pattern']."' as pattern for the groupnames".
                       $add.".";
            break;
            case 'fromto':
                return "The user with id '$this->userid' started creating groups via '{$this->objecttable}' with the " .
                       "course module id '$this->contextinstanceid' by defining values to start (".$this->data['other']['from'].
                       ") and stop (".$this->data['other']['to'].") using '".$this->data['other']['pattern'].
                       "' as pattern for the groupnames".$add.".";
            break;
            case '1-person-groups':
                return "The user with id '$this->userid' started creating 1-person-groups for each user via '".$this->objecttable.
                       "' with the course module id '$this->contextinstanceid' using '".$this->data['other']['pattern'].
                       "' as pattern for the groupnames".$add.".";
            break;
        }
        return '';
    }

    /**
     * Return localised event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('eventgroupcreationstarted', 'grouptool');
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
        return null;
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
            throw new \coding_exception('The group_creation_started event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        if (empty($this->data['other']['pattern'])) {
            throw new \coding_exception('Namepattern/Namingscheme has to be specified!');
        }
        switch ($this->data['other']['mode']) {
            case 'fromto':
                if (empty($this->data['other']['from'])) {
                    throw new \coding_exception('Lower limit has to be specified!');
                }
                if (empty($this->data['other']['to'])) {
                    throw new \coding_exception('Upper limit has to be specified!');
                }
            break;
            case 'members amount':
            case 'groups amount':
                if (empty($this->data['other']['amount'])) {
                    throw new \coding_exception('Amount of groups/members has to be specified!');
                }
            break;
        }
    }
}
