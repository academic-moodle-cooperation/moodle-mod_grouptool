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
 * observer.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

/**
 * mod_grouptool_observer handles events due to changes in moodle core which affect grouptool
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @since         Moodle 2.8+
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_observer {
    /**
     * group_member_added
     *
     * @param \core\event\group_member_added $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_member_added(\core\event\group_member_added $event) {
        global $DB;

        $sql = "SELECT DISTINCT grpt.id, grpt.ifmemberadded, grpt.course,
                                agrp.id agrpid
                FROM {grouptool} grpt
                JOIN {grouptool_agrps} agrp ON agrp.grouptoolid = grpt.id
                WHERE (agrp.groupid = ?) AND (agrp.active = ?) AND (grpt.ifmemberadded = ?)";
        $params = array($event->objectid, 1, GROUPTOOL_FOLLOW);
        if (! $grouptools = $DB->get_records_sql($sql, $params)) {
            return true;
        }

        $agrpssql = "SELECT agrps.grouptoolid AS grouptoolid, agrps.id AS id FROM {grouptool_agrps} agrps
        WHERE agrps.groupid = :groupid";
        $agrp = $DB->get_records_sql($agrpssql, array('groupid' => $event->objectid));

        $regsql = "SELECT reg.agrpid AS id
                     FROM {grouptool_agrps} agrps
               INNER JOIN {grouptool_registered} reg ON agrps.id = reg.agrpid
                    WHERE reg.modified_by >= 0 AND agrps.groupid = :groupid AND reg.userid = :userid";
        $regs = $DB->get_records_sql($regsql, array('groupid' => $event->objectid,
                                                    'userid'  => $event->relateduserid));
        $markssql = "SELECT reg.agrpid, reg.id, reg.userid, reg.timestamp
                       FROM {grouptool_agrps} agrps
                 INNER JOIN {grouptool_registered} reg ON agrps.id = reg.agrpid
                      WHERE reg.modified_by = -1 AND agrps.groupid = :groupid AND reg.userid = :userid";
        $marks = $DB->get_records_sql($markssql, array('groupid' => $event->objectid,
                                                       'userid'  => $event->relateduserid));
        foreach ($grouptools as $grouptool) {
            if (!key_exists($grouptool->agrpid, $regs)) {
                $reg = new stdClass();
                $reg->agrpid = $agrp[$grouptool->id]->id;
                $reg->userid = $event->relateduserid;
                $reg->timestamp = time();
                $reg->modified_by = 0; // There's no way we can get the teachers id!
                if (!$DB->record_exists('grouptool_registered', array('agrpid' => $reg->agrpid,
                                                                     'userid' => $reg->userid))) {
                    $reg->id = $DB->insert_record('grouptool_registered', $reg);
                    $reg->groupid = $event->objectid;
                    $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course, false, MUST_EXIST);
                    \mod_grouptool\event\registration_created::create_via_eventhandler($cm, $reg)->trigger();
                }
            } else if (key_exists($grouptool->agrpid, $marks)) {
                $record = $marks[$grouptool->agrpid];
                $record->modified_by = 0;
                $DB->update_record('grouptool_registered', $record);
                $reg->groupid = $event->objectid;
                $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course, false, MUST_EXIST);
                \mod_grouptool\event\registration_created::create_via_eventhandler($cm, $record)->trigger();
            }
        }
        return true;
    }

    /**
     * group_remove_member_handler
     * event:       groups_member_removed
     * schedule:    instant
     *
     * @param \core\event\group_member_removed $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
        global $DB, $CFG;

        $sql = "SELECT DISTINCT {grouptool}.id, {grouptool}.ifmemberremoved, {grouptool}.course,
                                {grouptool}.use_queue, {grouptool}.immediate_reg, {grouptool}.allow_multiple,
                                {grouptool}.choose_max, {grouptool}.choose_min, {grouptool}.grpsize,
                                {grouptool}.name, {grouptool}.use_size, {grouptool}.use_individual
                           FROM {grouptool}
                     RIGHT JOIN {grouptool_agrps} agrp ON agrp.grouptoolid = {grouptool}.id
                          WHERE agrp.groupid = ?";
        $params = array($event->objectid);
        if (! $grouptools = $DB->get_records_sql($sql, $params)) {
            return true;
        }
        $sql = "SELECT agrps.grouptoolid grouptoolid, agrps.id id
                  FROM {grouptool_agrps} agrps
                 WHERE agrps.groupid = :groupid";
        $agrp = $DB->get_records_sql($sql, array('groupid' => $event->objectid));
        foreach ($grouptools as $grouptool) {
            switch ($grouptool->ifmemberremoved) {
                case GROUPTOOL_FOLLOW:
                    $sql = "SELECT reg.id AS id, reg.agrpid AS agrpid, reg.userid AS userid, agrps.groupid
                              FROM {grouptool_agrps} agrps
                        INNER JOIN {grouptool_registered} reg ON agrps.id = reg.agrpid
                             WHERE reg.userid = :userid
                                   AND agrps.grouptoolid = :grouptoolid
                                   AND agrps.groupid = :groupid";
                    if ($regs = $DB->get_records_sql($sql, array('grouptoolid' => $grouptool->id,
                                                                 'userid'      => $event->relateduserid,
                                                                 'groupid'     => $event->objectid))) {
                        $DB->delete_records_list('grouptool_registered', 'id', array_keys($regs));
                        foreach ($regs as $reg) {
                            // Trigger event!
                            $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course, false,
                                                                 MUST_EXIST);
                            \mod_grouptool\event\registration_deleted::create_via_eventhandler($cm, $reg)->trigger();
                        }

                        // Get next queued user and put him in the group (and delete queue entry)!
                        if (!empty($grouptool->use_queue)) {
                            // We include it right here, because we want to have it slim!
                            require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
                            $cm = get_coursemodule_from_instance('grouptool', $grouptool->id);
                            $instance = new \mod_grouptool($cm->id, $grouptool, $cm);

                            $instance->fill_from_queue($agrp[$grouptool->id]->id);
                        }
                    }
                    break;
                default:
                case GROUPTOOL_IGNORE:
                    break;
            }
        }
        return true;
    }

    /**
     * group_deleted
     *
     * @param \core\event\group_deleted $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        global $CFG, $DB;

        $data = $event->get_record_snapshot('groups', $event->objectid);
        $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);

        if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
            return true;
        }

        $grouprecreated = false;
        $agrpids = array();
        foreach ($grouptools as $grouptool) {
            $cmid = $grouptool->coursemodule;
            switch ($grouptool->ifgroupdeleted) {
                default:
                case GROUPTOOL_RECREATE_GROUP:
                    if (!$grouprecreated) {
                        $newid = $DB->insert_record('groups', $data, true);
                        if ($newid !== false) {
                            // Delete auto-inserted agrp.
                            if ($DB->record_exists('grouptool_agrps', array('groupid' => $newid))) {
                                $DB->delete_records('grouptool_agrps', array('groupid' => $newid));
                            }
                            // Update reference.
                            if ($DB->record_exists('grouptool_agrps', array('groupid' => $data->id))) {
                                $DB->set_field('grouptool_agrps', 'groupid', $newid,
                                               array('groupid' => $data->id));
                            }
                            // Trigger event!
                            $logdata = array('cmid'     => $cmid,
                                             'groupid'  => $data->id,
                                             'newid'    => $newid,
                                             'courseid' => $data->courseid);
                            \mod_grouptool\event\group_recreated::create_from_object($logdata)->trigger();

                            if ($grouptool->immediate_reg) {
                                require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
                                $instance = new mod_grouptool($cmid, $grouptool);
                                $instance->push_registrations();
                            }
                            $grouprecreated = true;
                        } else {
                            print_error('error', 'moodle');
                            return false;
                        }
                    } else {
                        if ($grouptool->immediate_reg) {
                            require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
                            $instance = new mod_grouptool($cmid, $grouptool);
                            $instance->push_registrations();
                        }
                    }
                    break;
                case GROUPTOOL_DELETE_REF:
                    if ($agrpid = $DB->get_field('grouptool_agrps', 'id', array('groupid'     => $data->id,
                                                                                'grouptoolid' => $grouptool->id))) {
                        $agrpids[] = $agrpid;
                    }
                    break;
            }
        }
        if (count($agrpids) > 0) {
            $agrps = $DB->get_records_list('grouptool_agrps', 'id', $agrpids);
            $cms = array();
            $regs = $DB->get_records_list('grouptool_registered', 'agrpid', $agrpids);
            $DB->delete_records_list('grouptool_registered', 'agrpid', $agrpids);
            foreach ($regs as $cur) {
                if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                    $cms[$agrps[$cur->agrpid]->grouptoolid] = get_coursemodule_from_instance('grouptool',
                                                                                             $agrps[$cur->agrpid]->grouptoolid);
                }
                $cur->groupid = $agrps[$cur->agrpid]->groupid;
                \mod_grouptool\event\registration_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
            }
            $queues = $DB->get_records_list('grouptool_queued', 'agrpid', $agrpids);
            $DB->delete_records_list('grouptool_queued', 'agrpid', $agrpids);
            foreach ($queues as $cur) {
                if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                    $cms[$agrps[$cur->agrpid]->grouptoolid] = get_coursemodule_from_instance('grouptool',
                                                                                             $agrps[$cur->agrpid]->grouptoolid);
                }
                // Trigger event!
                $cur->groupid = $agrps[$cur->agrpid]->groupid;
                \mod_grouptool\event\queue_entry_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
            }
            $DB->delete_records_list('grouptool_agrps', 'id', $agrpids);
            foreach ($agrps as $cur) {
                if (empty($cms[$cur->grouptoolid])) {
                    $cms[$cur->grouptoolid] = get_coursemodule_from_instance('grouptool', $cur->grouptoolid);
                }
                // Trigger event!
                $logdata = new stdClass();
                $logdata->id = $cur->id;
                $logdata->cmid = $cms[$cur->grouptoolid]->id;
                $logdata->groupid = $cur->groupid;
                $logdata->agrpid = $cur->id;
                $logdata->courseid = $data->courseid;
                \mod_grouptool\event\agrp_deleted::create_from_object($logdata);
            }
        }

        return true;
    }

    /**
     * group_created
     *
     * @param  \core\event\group_created $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_created(\core\event\group_created $event) {
        global $DB;

        $data = $event->get_record_snapshot('groups', $event->objectid);
        $course = $DB->get_record('course', array('id' => $data->courseid));

        if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
            return true;
        }
        $sortorder = $DB->get_records_sql("SELECT agrp.grouptoolid, MAX(agrp.sort_order) AS max
                                             FROM {grouptool_agrps} agrp
                                         GROUP BY agrp.grouptoolid");
        foreach ($grouptools as $grouptool) {
            $newagrp = new StdClass();
            $newagrp->grouptoolid = $grouptool->id;
            $newagrp->groupid = $data->id;
            if (!array_key_exists($grouptool->id, $sortorder)) {
                $newagrp->sort_order = 1;
            } else {
                $newagrp->sort_order = $sortorder[$grouptool->id]->max + 1;
            }
            $newagrp->active = 0;
            if (!$DB->record_exists('grouptool_agrps', array('grouptoolid' => $grouptool->id,
                                                             'groupid'     => $data->id))) {
                $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp);
                // Trigger event!
                $cm = get_coursemodule_from_instance('grouptool', $grouptool->id);
                \mod_grouptool\event\agrp_created::create_from_object($cm, $newagrp)->trigger();
            }
        }
        return true;
    }
}