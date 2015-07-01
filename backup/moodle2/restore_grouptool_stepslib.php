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
 * restore_grouptool_stepslib.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one grouptool activity
 */
class restore_grouptool_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $grouptool = new restore_path_element('grouptool', '/activity/grouptool');
        $paths[] = $grouptool;
        $agrp = new restore_path_element('grouptool_agrp', '/activity/grouptool/agrps/agrp');
        $paths[] = $agrp;

        if ($userinfo) {
            $registration = new restore_path_element('agrp_registration',
                                                     '/activity/grouptool/agrps/agrp/registrations'.
                                                     '/registration');
            $paths[] = $registration;
            $queue = new restore_path_element('agrp_queue',
                                              '/activity/grouptool/agrps/agrp/queues/queue');
            $paths[] = $queue;
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_grouptool($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timedue = $this->apply_date_offset($data->timedue);
        $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = time();

        // Due to an old bug it can happen that these settings haven't been backed up!
        if (!isset($data->ifmemberadded)) {
            $data->ifmemberadded = get_config('mod_grouptool', 'ifmemberadded');
        }
        if (!isset($data->ifmemberremoved)) {
            $data->ifmemberremoved = get_config('mod_grouptool', 'ifmemberremoved');
        }
        if (!isset($data->ifgroupdeleted)) {
            $data->ifgroupdeleted = get_config('mod_grouptool', 'ifgroupdeleted');
        }

        // Insert the checkmark record!
        $newitemid = $DB->insert_record('grouptool', $data);
        // Immediately after inserting "activity" record, call this!
        $this->apply_activity_instance($newitemid);
    }

    protected function process_grouptool_agrp($data) {
        global $DB, $OUTPUT;

        $data = (object)$data;
        $oldid = $data->id;

        if (isset($data->group_id)) {
            $data->groupid = $data->group_id;
            unset($data->group_id);
        }

        $data->grouptoolid = $this->get_new_parentid('grouptool');
        $old = $data->groupid;
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        if ($data->groupid === false) {
            $message = "Couldn't find mapping id for group with former id-# ".$old.
                       " so we have to skip it.".html_writer::empty_tag('br').
                       "The group was ".($data->active ? 'active' : 'inactive')." in this instance";
            debugging($message);
        } else {
            $newitemid = $DB->insert_record('grouptool_agrps', $data);
            $this->set_mapping('grouptool_agrp', $oldid, $newitemid);
        }
    }

    protected function process_agrp_registration($data) {
        global $DB, $OUTPUT;

        $data = (object)$data;
        $oldid = $data->id;

        if (isset($data->user_id)) {
            $data->userid = $data->user_id;
            unset($data->user_id);
        }
        if (isset($data->agrp_id)) {
            $data->agrpid = $data->agrp_id;
            unset($data->agrpid);
        }

        $oldagrp = $data->agrpid;
        $data->agrpid = $this->get_new_parentid('grouptool_agrp');
        $old = $data->userid;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->modified_by = $this->get_mappingid('user', $data->modified_by);

        if ($data->agrpid === false) {

            $message = "Couldn't find mapping id for agrp with former id-# ".$oldagrp." so we have to skip it.";
            debugging($message);
        } else if ($data->userid === false) {
            $message = "Couldn't find mapping id for user with former id-# ".$old." so we have to skip it.";
            debugging($message);
        } else {
            $newitemid = $DB->insert_record('grouptool_registered', $data);
            $this->set_mapping('agrp_registration', $oldid, $newitemid);
        }
    }

    protected function process_agrp_queue($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        if (isset($data->user_id)) {
            $data->userid = $data->user_id;
            unset($data->user_id);
        }
        if (isset($data->agrp_id)) {
            $data->agrpid = $data->agrp_id;
            unset($data->agrpid);
        }

        $oldagrp = $data->agrpid;
        $data->agrpid = $this->get_new_parentid('grouptool_agrp');
        $old = $data->userid;
        $data->userid = $this->get_mappingid('user', $data->userid);

        if ($data->agrpid === false) {
            $message = "Couldn't find mapping id for agrp with former id-# ".$oldagrp." so we have to skip it.";
            debugging($message);
        } else if ($data->userid === false) {
            $message = "Couldn't find mapping id for user with former id-# ".$old." so we have to skip it.";
            debugging($message);
        } else {
            $newitemid = $DB->insert_record('grouptool_queued', $data);
            $this->set_mapping('agrp_queue', $oldid, $newitemid);
        }
    }

    protected function after_execute() {
        // Add grouptool related files, no need to match by itemname (jst internally handled context)!
        $this->add_related_files('mod_grouptool', 'intro', null);
    }
}
