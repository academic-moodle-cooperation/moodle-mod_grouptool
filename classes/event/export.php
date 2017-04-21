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
 * The mod_grouptool\export event base class.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The \mod_grouptool\export base class holds the common logic for the export events
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class export extends \core\event\base {
    /** @var string either 'overview' or 'userlist' used as tab-value and text and string identifier! */
    protected $exportsubject = '';

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'grouptool';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (!empty($this->data['other']['groupingid'])) {
            $add = ' for grouping with id \''.$this->data['other']['groupingid'].'\'';
        } else {
            $add = '';
        }
        if (!empty($this->data['other']['groupid'])) {
            if (!empty($add)) {
                $add .= ' and group with id \''.$this->data['other']['groupid'].'\'';
            } else {
                $add = ' for group with id \''.$this->data['other']['groupid'].'\'';
            }
        } else {
            if (empty($add)) {
                $add = '';
            }
        }
        return "The user with id '$this->userid' exported the {$this->exportsubject} for '{$this->objecttable}' with the " .
               "course module id '$this->contextinstanceid'".$add." as '".$this->data['other']['format_readable']."'.";
    }

    /**
     * Return localised event name. Will be overwritten!
     *
     * @return string
     */
    public static function get_name() {
        return '';
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/download.php", array('id' => $this->contextinstanceid,
                                                                             'tab' => $this->exportsubject,
                                                                             'format' => $this->data['other']['format']));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, $this->objecttable, 'export',
                     "download.php?id=".$this->contextinstanceid."&groupingid=".$this->data['other']['groupingid'].
                     "&groupid=".$this->data['other']['groupid']."&tab=".$this->exportsubject."&format=".
                     $this->data['other']['format'],
                     get_string($this->exportsubject, 'grouptool').' '.$this->data['other']['format_readable'],
                     $this->contextinstanceid);
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
            throw new \coding_exception('The '.$this->get_name().' event must define objectid and object table.');
        }
        // Make sure the context level is set to module.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }

        // ...format, format_readable, groupid, groupingid.
        if (!key_exists('format', $this->data['other'])) {
            throw new \coding_exception('Format has to be specified!');
        }

        if (!key_exists('groupid', $this->data['other'])) {
            throw new \coding_exception('Group-ID-Key missing!');
        }

        if (!key_exists('groupingid', $this->data['other'])) {
            throw new \coding_exception('Grouping-ID-Key missing!');
        }

        if (!key_exists('format_readable', $this->data['other']) || empty($this->data['other']['format_readable'])) {
            debugging('Missing or empty readable-export-format.', DEBUG_DEVELOPER);
        }
    }
}