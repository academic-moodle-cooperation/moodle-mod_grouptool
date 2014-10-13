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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * eventhandlers.php
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
 * group_add_member_handler
 * event:       groups_member_added
 * schedule:    instant
 *
 * @param array groupid, userid
 * @global DB
 * @return bool true if success
 */
function group_add_member_handler($data) {
    global $DB;

    $sql = "SELECT DISTINCT grpt.id, grpt.ifmemberadded, grpt.course,
                            agrp.id as agrpid
            FROM {grouptool} as grpt
            JOIN {grouptool_agrps} AS agrp ON agrp.grouptoolid = grpt.id
            WHERE (agrp.groupid = ?) AND (agrp.active = ?) AND (grpt.ifmemberadded = ?)";
    $params = array($data->groupid, 1, GROUPTOOL_FOLLOW);
    if (! $grouptools = $DB->get_records_sql($sql, $params)) {
        return true;
    }

    $agrpssql = "SELECT agrps.grouptoolid AS grouptoolid, agrps.id AS id FROM {grouptool_agrps} AS agrps
    WHERE agrps.groupid = :groupid";
    $agrp = $DB->get_records_sql($agrpssql, array('groupid' => $data->groupid));

    $regsql = "SELECT reg.agrpid AS id
            FROM {grouptool_agrps} AS agrps
            INNER JOIN {grouptool_registered} as reg ON agrps.id = reg.agrpid
            WHERE agrps.groupid = :groupid AND reg.userid = :userid";
    $regs = $DB->get_records_sql($regsql, array('groupid' => $data->groupid,
                                                'userid'  => $data->userid));
    foreach ($grouptools as $grouptool) {
        if (!key_exists($grouptool->agrpid, $regs)) {
            $reg = new stdClass();
            $reg->agrpid = $agrp[$grouptool->id]->id;
            $reg->userid = $data->userid;
            $reg->timestamp = time();
            $reg->modified_by = 0; // There's no way we can get the teachers id!
            if (!$DB->record_exists('grouptool_registered', array('agrpid' => $reg->agrpid,
                                                                 'userid' => $reg->userid))) {
                $reg->id = $DB->insert_record('grouptool_registered', $reg);
                $reg->groupid = $data->groupid;
                $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course, false, MUST_EXIST);
                \mod_grouptool\event\registration_created::create_via_eventhandler($cm, $reg)->trigger();
            }
        }
    }
    return true;
}
/**
 * group_remove_member_handler
 * event:       groups_member_removed
 * schedule:    instant
 *
 * @param array groupid, userid
 * @global DB
 * @global COURSE
 * @return bool true if success
 */
function group_remove_member_handler($data) {
    global $DB, $CFG;

    $sql = "SELECT DISTINCT {grouptool}.id, {grouptool}.ifmemberremoved, {grouptool}.course,
                            {grouptool}.use_queue, {grouptool}.immediate_reg, {grouptool}.allow_multiple,
                            {grouptool}.choose_max, {grouptool}.name
                       FROM {grouptool}
                 RIGHT JOIN {grouptool_agrps} AS agrp ON agrp.grouptoolid = {grouptool}.id
                      WHERE agrp.groupid = ?";
    $params = array($data->groupid);
    if (! $grouptools = $DB->get_records_sql($sql, $params)) {
        return true;
    }
    $sql = "SELECT agrps.grouptoolid AS grouptoolid, agrps.id AS id FROM {grouptool_agrps} AS agrps
            WHERE agrps.groupid = :groupid";
    $agrp = $DB->get_records_sql($sql, array('groupid' => $data->groupid));
    foreach ($grouptools as $grouptool) {
        switch($grouptool->ifmemberremoved) {
            case GROUPTOOL_FOLLOW:
                $sql = "SELECT reg.id AS id, reg.agrpid AS agrpid, reg.userid as userid, agrps.groupid FROM {grouptool_agrps} AS agrps
                 INNER JOIN {grouptool_registered} AS reg ON agrps.id = reg.agrpid
                      WHERE reg.userid = :userid
                        AND agrps.grouptoolid = :grouptoolid
                        AND agrps.groupid = :groupid";
                if ($regs = $DB->get_records_sql($sql,
                                                 array('grouptoolid' => $grouptool->id,
                                                       'userid'      => $data->userid,
                                                       'groupid'     => $data->groupid))) {
                    $DB->delete_records_list('grouptool_registered', 'id', array_keys($regs));
                    foreach($regs as $reg) {
                        /* Trigger event */
                        $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $grouptool->course, false, MUST_EXIST);
                        \mod_grouptool\event\registration_deleted::create_via_eventhandler($cm, $reg)->trigger();
                    }

                    // Get next queued user and put him in the group (and delete queue entry)!
                    if (!empty($grouptool->use_queue)) {
                        $agrpids = $DB->get_fieldset_sql('SELECT id
                                                            FROM {grouptool_agrps}
                                                           WHERE grouptoolid = ?', array($grouptool->id));
                        list($agrpssql, $agrpsparam) = $DB->get_in_or_equal($agrpids);
                        $sql = "SELECT queued.*, (COUNT(DISTINCT reg.id) < ?) as priority
                                  FROM {grouptool_queued} AS queued
                             LEFT JOIN {grouptool_registered} AS reg ON queued.userid = reg.userid
                                                                     AND reg.agrpid ".$agrpssql."
                                 WHERE queued.agrpid = ?
                              GROUP BY queued.id
                              ORDER BY priority DESC, timestamp ASC
                                 LIMIT 1";
                        $params = array_merge(array($grouptool->choose_max),
                                              $agrpsparam,
                                              array($agrp[$grouptool->id]->id));
                        $record = $DB->get_record_sql($sql, $params);
                        if (is_object($record)) {
                            $newrecord = clone $record;
                            unset($newrecord->id);
                            $newrecord->modified_by = $newrecord->userid;
                            $newrecord->id = $DB->insert_record('grouptool_registered', $newrecord);
                            if (!empty($grouptool->immediate_reg)) {
                                groups_add_member($data->groupid, $newrecord->userid);
                            }
                            /* Trigger event */
                            // We got the cm above already!
                            $newrecord->groupid = $data->groupid;
                            $record->groupid = $data->groupid;
                            \mod_grouptool\event\user_moved::promotion_from_queue($cm, $record, $newrecord)->trigger();

                            $allowm = $grouptool->allow_multiple;
                            $agrps = $DB->get_fieldset_sql("SELECT id
                                                            FROM {grouptool_agrps} as agrps
                                                            WHERE agrps.grouptoolid = :grptlid",
                                                            array('grptlid' => $grouptool->id));
                            list($sql, $params) = $DB->get_in_or_equal($agrps);
                            $usrregcnt = $DB->count_records_select('grouptool_registered',
                                                                   ' userid = ?
                                                                    AND agrpid '.$sql,
                                                                   array_merge(array($newrecord->userid), $params));
                            $max = $grouptool->choose_max;

                            // Get belonging course!
                            $course = $DB->get_record('course', array('id' => $grouptool->course));
                            // Get CM!
                            $cm = get_coursemodule_from_instance('grouptool', $grouptool->id, $course->id);
                            $message = new stdClass();
                            $userdata = $DB->get_record('user', array('id' => $newrecord->userid));
                            $message->username = fullname($userdata);
                            $groupdata = $DB->get_record('grouptool_agrps', array('id' => $agrp[$grouptool->id]->id));
                            $groupdata->name = $DB->get_field('groups', 'name', array('id' => $groupdata->groupid));
                            $message->groupname = $groupdata->name;

                            $strgrouptools = get_string("modulenameplural", "grouptool");
                            $strgrouptool  = get_string("modulename", "grouptool");
                            $postsubject = $course->shortname.': '.$strgrouptools.': '.
                                           format_string($grouptool->name, true);
                            $posttext  = $course->shortname.' -> '.$strgrouptools.' -> '.
                                         format_string($grouptool->name, true)."\n";
                            $posttext .= "----------------------------------------------------------\n";
                            $posttext .= get_string("register_you_in_group_successmail",
                                                    "grouptool", $message)."\n";
                            $posttext .= "----------------------------------------------------------\n";
                            $usermailformat = $DB->get_field('user', 'mailformat',
                                                             array('id' => $newrecord->userid));
                            if ($usermailformat == 1) {  // HTML!
                                $posthtml = "<p><font face=\"sans-serif\">";
                                $posthtml = "<a href=\"".$CFG->wwwroot."/course/view.php?id=".
                                            $course->id."\">".$course->shortname."</a> ->";
                                $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/index.php?id=".
                                            $course->id."\">".$strgrouptools."</a> ->";
                                $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/view.php?id=".
                                            $cm->id."\">".format_string($grouptool->name,
                                                                              true)."</a></font></p>";
                                $posthtml .= "<hr /><font face=\"sans-serif\">";
                                $posthtml .= "<p>".get_string("register_you_in_group_successmailhtml",
                                                              "grouptool", $message)."</p>";
                                $posthtml .= "</font><hr />";
                            } else {
                                $posthtml = "";
                            }
                            $messageuser = $DB->get_record('user', array('id' => $newrecord->userid));
                            $eventdata = new stdClass();
                            $eventdata->modulename       = 'grouptool';
                            $userfrom = core_user::get_noreply_user();
                            $eventdata->userfrom         = $userfrom;
                            $eventdata->userto           = $messageuser;
                            $eventdata->subject          = $postsubject;
                            $eventdata->fullmessage      = $posttext;
                            $eventdata->fullmessageformat = FORMAT_PLAIN;
                            $eventdata->fullmessagehtml  = $posthtml;
                            $eventdata->smallmessage     = get_string('register_you_in_group_success',
                                                                      'grouptool', $message);
                            $eventdata->name            = 'grouptool_moveupreg';
                            $eventdata->component       = 'mod_grouptool';
                            $eventdata->notification    = 1;
                            $eventdata->contexturl      = $CFG->wwwroot.'/mod/grouptool/view.php?id='.
                                                          $cm->id;
                            $eventdata->contexturlname  = $grouptool->name;
                            message_send($eventdata);

                            if (($allowm && ($usrregcnt >= $max)) || !$allowm) {
                                // Get all queue entries and trigger queue_entry_deleted events for each!
                                $queue_entries = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                                                         FROM {grouptool_queued} as queued
                                                                         JOIN {grouptool_agrps} as agrp ON queued.agrpid = agrp.id
                                                                        WHERE userid = ? AND agrpid ".$sql,
                                                                      array_merge(array($newrecord->userid), $params));
                                $DB->delete_records_select('grouptool_queued',
                                                           ' userid = ? AND agrpid '.$sql,
                                                           array_merge(array($newrecord->userid),
                                                                       $params));
                                foreach($queue_entries as $cur_queue_entry) {
                                    /* Trigger event */
                                    // We got the cm above already!
                                    \mod_grouptool\event\queue_entry_deleted::create_via_eventhandler($cm, $cur_queue_entry)->trigger();
                                }
                            } else {
                                $queue_entries = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                                                         FROM {grouptool_queued} as queued
                                                                         JOIN {grouptool_agrps} as agrp ON queued.agrpid = agrp.id
                                                                        WHERE userid = :userid AND agrpid = :agrpid",
                                                                      array('userid' => $newrecord->userid,
                                                                            'agrpid' => $agrp[$grouptool->id]->id));
                                $DB->delete_records('grouptool_queued', array('userid' => $newrecord->userid,
                                                                              'agrpid' => $agrp[$grouptool->id]->id));
                                foreach($queue_entries as $cur_queue_entry) {
                                    /* Trigger event */
                                    // We got the cm above already!
                                    \mod_grouptool\event\queue_entry_deleted::create_via_eventhandler($cm, $cur_queue_entry)->trigger();
                                }
                            }
                        }
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
 * group_deleted_handler
 * event:       groups_group_deleted
 * schedule:    instant
 *
 * @param array id, courseid, name, description, timecreated, timemodified, picture
 * @global CFG
 * @global DB
 * @return bool true if success
 */
function group_deleted_handler($data) {
    global $CFG, $DB;

    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);

    if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
        return true;
    }

    $grouprecreated = false;
    $agrpids = array();
    foreach ($grouptools as $grouptool) {
        $cmid = $grouptool->coursemodule;
        switch($grouptool->ifgroupdeleted) {
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
                        $logdata = new stdClass();
                        $logdata->cmid = $cmid;
                        $logdata->groupid = $data->id;
                        $logdata->newid = $newid;
                        $logdata->courseid = $data->courseid;
                        \mod_grouptool\event\group_recreated::create_from_object($logdata);

                        if ($grouptool->immediate_reg) {
                            require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
                            $instance = new grouptool($cmid, $grouptool);
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
                        $instance = new grouptool($cmid, $grouptool);
                        $instance->push_registrations();
                    }
                }
                break;
            case GROUPTOOL_DELETE_REF:
                if ($agrpid = $DB->get_field('grouptool_agrps', 'id',
                                             array('groupid'     => $data->id,
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
        foreach($regs as $cur) {
            if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                $cms[$agrps[$cur->agrpid]->grouptoolid] = get_contextmodule_from_instance('grouptool', $agrps[$cur->agrpid]->grouptoolid);
            }
            $cur->groupid = $agrps[$cur->agrpid]->groupid;
            \mod_grouptool\event\registration_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
        }
        $queues = $DB->get_records_list('grouptool_queued', 'agrpid', $agrpids);
        $DB->delete_records_list('grouptool_queued', 'agrpid', $agrpids);
        foreach($queues as $cur) {
            if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                $cms[$agrps[$cur->agrpid]->grouptoolid] = get_contextmodule_from_instance('grouptool', $agrps[$cur->agrpid]->grouptoolid);
            }
            //Trigger event!
            $cur->groupid = $agrps[$cur->agrpid]->groupid;
            \mod_grouptool\event\queue_entry_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
        }
        $DB->delete_records_list('grouptool_agrps', 'id', $agrpids);
        foreach($agrps as $cur) {
            if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                $cms[$cur->grouptoolid] = get_contextmodule_from_instance('grouptool', $cur->grouptoolid);
            }
            // Trigger event!
            $logdata = new stdClass();
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
 * group_created_handler
 * event:       groups_group_created
 * schedule:    instant
 *
 * @param  array id, courseid, name, description, timecreated, timemodified, picture
 * @global DB
 * @return bool true if success
 */
function group_created_handler($data) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $data->courseid));

    if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
        return true;
    }
    $sortorder = $DB->get_records_sql("SELECT agrp.grouptoolid, MAX(agrp.sort_order) AS max
                                          FROM {grouptool_agrps} AS agrp
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