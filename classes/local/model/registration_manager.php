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

namespace mod_grouptool\local\model;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/group/lib.php');

use cache_helper;
use completion_info;
use context_course;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use core\output\notification;
use core_message\api;
use core_php_time_limit;
use dml_exception;
use Exception;
use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use mod_grouptool\event\queue_entry_deleted;
use mod_grouptool\event\registration_created;
use mod_grouptool\event\registration_deleted;
use mod_grouptool\event\registration_push_started;
use mod_grouptool\exception\exceedgroupqueuelimit;
use mod_grouptool\exception\exceedgroupsize;
use mod_grouptool\exception\exceeduserqueuelimit;
use mod_grouptool\exception\exceeduserreglimit;
use mod_grouptool\exception\notenoughregs;
use mod_grouptool\exception\registration;
use mod_grouptool\exception\regpresent;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\local\grouptool_utils;
use progress_bar;
use stdClass;
use Throwable;

/**
 * Class containing the logic for registering and unregistering users in grouptool
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration_manager extends grouptool_instance {
    /**
     * Return true if the registration is open, false otherwise!
     *
     * @return bool true if reg is open, false otherwise
     */
    public function is_registration_open() {
        return ($this->grouptool->allowreg && (($this->grouptool->timedue == 0) || (time() < $this->grouptool->timedue))
            && (time() > $this->grouptool->timeavailable));
    }

    /**
     * registers/queues a user in a certain active-group
     *
     * @param int $agrpid active-group-id to register/queue user to
     * @param int $userid user to register/queue
     * @param bool $previewonly optional don't act, just return a preview
     * @return string status message
     * @throws exceedgroupqueuelimit
     * @throws exceeduserqueuelimit
     * @throws exceeduserreglimit
     * @throws exceedgroupsize
     * @throws regpresent
     * @throws registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function register_in_agrp($agrpid, $userid = 0, $previewonly = false): string {
        global $USER, $DB;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allowreg
            && (($this->grouptool->timedue == 0)
                || (time() < $this->grouptool->timedue))
            && ($this->grouptool->timeavailable <= time()));

        if (!$regopen && !has_capability('mod/grouptool:administrate_registration', $this->context)) {
            throw new registration('reg_not_open');
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', ['id' => $userid]);
            $message->username = fullname($userdata);
        }
        $groupdata = $groupmanager->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = current($groupdata);

        $message->groupname = $groupdata->name;
        $message->userid = $userid;

        if ($permissionmanager->qualifies_for_groupchange($agrpid, $userid)) {
            if ($previewonly) {
                $return = $permissionmanager->can_change_group($agrpid, $userid, $message);
            } else {
                $return = $this->change_group($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $utils->convert_marks_to_regs($userid);
            }

            return $return;
        }

        try {
            // First we try to register the user!
            if ($previewonly) {
                $return = $permissionmanager->can_be_registered($agrpid, $userid, $message);
            } else {
                $return = $this->add_registration($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $utils->convert_marks_to_regs($userid);
                // Update completion state if submission is changed.
                $completion = new completion_info($this->course);
                if ($completion->is_enabled($this->cm) && $this->grouptool->completionregister) {
                    $completion->update_state($this->cm, COMPLETION_COMPLETE, $userid);
                }
            }

            return $return;
        } catch (exceedgroupsize $e) {
            if (!$this->grouptool->usequeue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            try {
                if ($previewonly) {
                    $return = $permissionmanager->can_be_queued($agrpid, $userid, $message);
                } else {
                    $return = $queuemanager->add_queue_entry($agrpid, $userid, $message);
                    // If we can queue, we have to convert the other marks to registrations & queue entries!
                    $utils->convert_marks_to_regs($userid);
                }

                return $return;
            } catch (notenoughregs) {
                /* The user has not enough registrations, queue entries or marks,
                 * so we try to mark the user! (Exceptions get handled above!) */
                if ($previewonly) {
                    [, $return] = $permissionmanager->can_be_marked($agrpid, $userid, $message);
                } else {
                    $return = $utils->mark_for_reg($agrpid, $userid, $message);
                }

                return $return;
            }
        } catch (notenoughregs) {
            /* The user has not enough registrations, queue entries or marks,
             * so we try to mark the user! (Exceptions get handled above!) */
            if ($previewonly) {
                [, $return] = $permissionmanager->can_be_marked($agrpid, $userid, $message);
            } else {
                $return = $utils->mark_for_reg($agrpid, $userid, $message);
            }

            return $return;
        }
    }

    /**
     * unregisters/unqueues a user from a certain active-group or throw an exception
     *
     * @param int $agrpid active-group-id to unregister/unqueue user from
     * @param int $userid user to unregister/unqueue
     * @param bool $previewonly (optional) don't act, just return a preview
     * @param bool $force (optional) ignore setting for allowing deregistration (needed for multi-deregistration)
     * @param bool $ignoregtinstance If true unregister/unqueue a user from a given group regardless of this grouptool instance
     * @return string $message if everything went right
     * @throws notenoughregs If the user hasn't enough registrations!
     * @throws registration In any other case, where the user can't be unregistered!
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public function unregister_from_agrp(
        $agrpid,
        $userid = 0,
        $previewonly = false,
        $force = false,
        $ignoregtinstance = false
    ) {
        global $USER, $DB;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allowreg
            && (($this->grouptool->timedue == 0)
                || (time() < $this->grouptool->timedue))
            && ($this->grouptool->timeavailable < time()));

        if (!$force && !$regopen && !has_capability('mod/grouptool:administrate_registration', $this->context)) {
            throw new registration('reg_not_open');
        }

        if (!$force && empty($this->grouptool->allowunreg)) {
            throw new registration('unreg_not_allowed');
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', ['id' => $userid]);
            $message->username = fullname($userdata);
        }
        $groupdata = $groupmanager->get_active_groups(
            true,
            true,
            $agrpid,
            0,
            0,
            true,
            true,
            $ignoregtinstance
        );

        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        $message->groupname = $groupdata->name;
        $message->userid = $userid;
        $agrpids = null;
        if ($ignoregtinstance) {
            $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', '');
        } else {
            $agrpids = $DB->get_fieldset_select(
                'grouptool_agrps',
                'id',
                "grouptoolid = ?",
                [$this->grouptool->id]
            );
        }
        [$agrpsql, $params] = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select(
            'grouptool_registered',
            "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql,
            $params
        );
        $userqueues = $DB->count_records_select(
            'grouptool_queued',
            "userid = ? AND agrpid " . $agrpsql,
            $params
        );
        $min = $this->grouptool->allowmultiple ? $this->grouptool->choosemin : 0;
        if ($userregs + $userqueues <= $min) {
            if ($userid == $USER->id) {
                $text = 'you_have_too_less_regs';
            } else {
                $text = 'user_has_too_less_regs';
            }

            // Throw notenoughregs exception with custom description text!
            throw new notenoughregs($text, $message);
        }

        if ($queuemanager->get_rank_in_queue($groupdata->registered, $userid)) {
            // He is registered!
            if ($previewonly) {
                // Shortcut here, everything seems to be fine, enough for now!
                if ($userid == $USER->id) {
                    return get_string('unreg_you_from_group', 'grouptool', $message);
                } else {
                    return get_string('unreg_from_group', 'grouptool', $message);
                }
            }

            $records = $DB->get_records('grouptool_registered', [
                'agrpid' => $agrpid,
                'userid' => $userid,
            ]);
            $DB->delete_records('grouptool_registered', [
                'agrpid' => $agrpid,
                'userid' => $userid,
            ]);
            if (!$force && !empty($this->grouptool->immediatereg)) {
                groups_remove_member($groupdata->id, $userid);
            }
            foreach ($records as $data) {
                // Trigger the event!
                $data->groupid = $groupdata->id;
                registration_deleted::create_direct($this->cm, $data)->trigger();
            }
            // Get next queued user and put him in the group (and delete queue entry)!
            if (!empty($this->grouptool->usequeue) && !empty($groupdata->queued)) {
                $queuemanager->fill_from_queue($agrpid);
            }
            // Update completion state.
            $completion = new completion_info($this->course);
            if ($completion->is_enabled($this->cm) && $this->grouptool->completionregister) {
                $completion->update_state($this->cm, COMPLETION_INCOMPLETE, $userid);
            }
            if ($userid == $USER->id) {
                return get_string('unreg_you_from_group_success', 'grouptool', $message);
            } else {
                return get_string('unreg_from_group_success', 'grouptool', $message);
            }
        }
        if ($queuemanager->get_rank_in_queue($groupdata->queued, $userid)) {
            // He is queued!
            if ($previewonly) {
                // Shortcut here, everything seems to be fine, enough for now!
                if ($userid == $USER->id) {
                    return get_string('unqueue_you_from_group', 'grouptool', $message);
                } else {
                    return get_string('unqueue_from_group', 'grouptool', $message);
                }
            }

            $records = $DB->get_records('grouptool_queued', ['agrpid' => $agrpid, 'userid' => $userid]);
            $DB->delete_records('grouptool_queued', [
                'agrpid' => $agrpid,
                'userid' => $userid,
            ]);
            foreach ($records as $cur) {
                // Trigger the Event!
                $cur->groupid = $groupdata->id;
                queue_entry_deleted::create_direct($this->cm, $cur)->trigger();
            }
            if ($userid == $USER->id) {
                return get_string('unqueue_you_from_group_success', 'grouptool', $message);
            } else {
                return get_string('unqueue_from_group_success', 'grouptool', $message);
            }
        }

        // If we got here, the user was neither registered nor queued!
        if ($userid == $USER->id) {
            $text = get_string('you_are_not_in_queue_or_registered', 'grouptool', $message);
        } else {
            $text = get_string('not_in_queue_or_registered', 'grouptool', $message);
        }

        throw new registration($text);
    }


    /**
     * Changes group for certain user. This is only possible if unreg is allowed and we can determine which group to change!
     *
     * @param int $agrpid ID of active group to change to
     * @param int|null $userid (optional) ID of user to change group for or null ($USER->id is used).
     * @param stdClass|null $message (optional) prepared message object containing username and groupname or null.
     * @param int|null $oldagrpid (optional) ID of former active group
     * @return string success message
     * @throws exceedgroupqueuelimit
     * @throws exceeduserreglimit
     * @throws exceeduserqueuelimit
     * @throws registration
     * @throws regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public function change_group(int $agrpid, ?int $userid = null, ?stdClass $message = null, ?int $oldagrpid = null): string {
        global $DB, $USER;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($message === null) {
            $message = new stdClass();
            if ($userid != $USER->id) {
                $userdata = $DB->get_record('user', ['id' => $userid]);
                $message->username = fullname($userdata);
            } else {
                $message->username = fullname($USER);
            }
            $groupdata = $groupmanager->get_active_groups(false, false, $agrpid);
            if (count($groupdata) != 1) {
                throw new registration('error_getting_data');
            }
            $groupdata = reset($groupdata);
            $message->groupname = $groupdata->name;
        }

        // Check if the user can be registered or queued with respect to max registrations being incremented by 1.
        $permissionmanager->can_change_group($agrpid, $userid, $message, $oldagrpid, false);

        // Determine from which group to change and unregister from it!
        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select(
            'grouptool_agrps',
            'id',
            "grouptoolid = ? AND active = 1",
            [$this->grouptool->id]
        );
        [$agrpsql, $params] = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->get_records_select(
            'grouptool_registered',
            "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql,
            $params
        );
        $userqueues = $DB->get_records_select('grouptool_queued', "userid = ? AND agrpid " . $agrpsql, $params);
        if ($oldagrpid !== null) {
            $sql = "SELECT queued.*, agrp.groupid
                      FROM {grouptool_queued} queued
                      JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if (
                $queue = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'agrpid' => $oldagrpid,
                ], IGNORE_MISSING)
            ) {
                $DB->delete_records('grouptool_queued', ['id' => $queue->id]);
                // Trigger the event!
                queue_entry_deleted::create_direct($this->cm, $queue);
                // Let other queued be promoted to registered status!
                $queuemanager->fill_from_queue($queue->agrpid);
            }
            $sql = "SELECT reg.*, agrp.groupid
                      FROM {grouptool_registered} reg
                      JOIN {grouptool_agrps} agrp ON agrp.id = reg.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if (
                $reg = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'agrpid' => $oldagrpid,
                ], IGNORE_MISSING)
            ) {
                $DB->delete_records('grouptool_registered', ['id' => $reg->id]);
                if (!empty($this->grouptool->immediatereg)) {
                    groups_remove_member($reg->groupid, $userid);
                }
                // Trigger the event!
                registration_deleted::create_direct($this->cm, $reg);
                // Let other queued be promoted to registered status!
                $queuemanager->fill_from_queue($reg->agrpid);
            }
        } else if (count($userqueues) == 1) {
            // Delete his queue!
            $queues = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                              FROM {grouptool_queued} queued
                                              JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                                              WHERE userid = ? AND agrpid " . $agrpsql, $params);
            $DB->delete_records_select('grouptool_queued', "userid = ? AND agrpid " . $agrpsql, $params);
            foreach ($queues as $cur) {
                // Trigger the event!
                queue_entry_deleted::create_direct($this->cm, $cur);

                // Let other queued be promoted to registered status!
                $queuemanager->fill_from_queue($cur->agrpid);
            }
        } else if (count($userregs) == 1) {
            $oldgrp = $DB->get_field_sql(
                "SELECT agrp.groupid
                                            FROM {grouptool_registered} reg
                                            JOIN {grouptool_agrps} agrp ON agrp.id = reg.agrpid
                                           WHERE reg.userid = ? AND reg.agrpid " . $agrpsql,
                $params,
                MUST_EXIST
            );
            $reg = $DB->get_record_select(
                'grouptool_registered',
                "userid = ? AND agrpid " . $agrpsql,
                $params,
                '*',
                MUST_EXIST
            );
            $DB->delete_records_select('grouptool_registered', "userid = ? AND agrpid " . $agrpsql, $params);
            if (!empty($oldgrp) && !empty($this->grouptool->immediatereg)) {
                groups_remove_member($oldgrp, $userid);
            }

            // Trigger the event!
            $reg->groupid = $oldgrp;
            registration_deleted::create_direct($this->cm, $reg);

            // Let other queued be promoted to registered status!
            $queuemanager->fill_from_queue($reg->agrpid);
        } else {
            throw new registration(get_string(
                'groupchange_from_non_unique_reg',
                'grouptool'
            ));
        }

        // Register him in the new group!
        try {
            // First we try to register the user!
            $return = $this->add_registration($agrpid, $userid, $message);
            // If we can register, we have to convert the other marks to registrations & queue entries!
            $utils->convert_marks_to_regs($userid);

            return $return;
        } catch (exceedgroupsize $e) {
            if (!$this->grouptool->usequeue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            $return = $queuemanager->add_queue_entry($agrpid, $userid, $message);
            // If we can queue, we have to convert the other marks to registrations & queue entries!
            $utils->convert_marks_to_regs($userid);

            return $return;
        }
    }

    /**
     * Add a registration for a certain user/agrp-combination.
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to register or null (then $USER->id is used)
     * @param stdClass $message (optional) prepared message object containing username and groupname or null
     * @return string status message
     * @throws exceedgroupsize
     * @throws exceeduserreglimit
     * @throws notenoughregs
     * @throws registration
     * @throws regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     * @throws \moodle_exception
     */
    protected function add_registration($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $groupdata = $groupmanager->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        /* This method throws exceptions if there is a problem */
        $permissionmanager->can_be_registered($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->modified_by = $USER->id;
        $record->id = $DB->insert_record('grouptool_registered', $record);
        if ($this->grouptool->immediatereg) {
            groups_add_member($groupdata->id, $userid);
        }
        // Trigger the event!
        $record->groupid = $groupdata->id;
        registration_created::create_direct($this->cm, $record)->trigger();
        if ($userid != $USER->id) {
            return get_string('register_in_group_success', 'grouptool', $message);
        } else {
            return get_string('register_you_in_group_success', 'grouptool', $message);
        }
    }

    /**
     * returns number of reg-entries for a particular user in a particular grouptool-instance
     *
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_user_reg_count($userid = 0) {
        global $DB, $USER;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $groupmanager->get_active_groups();
        $keys = [];
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        [$sql, $params] = $DB->get_in_or_equal($keys);
        $params = array_merge([$userid], $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_registered}
                                       WHERE modified_by >= 0 AND userid = ? AND agrpid ' . $sql, $params);
    }

    /**
     * Unregisters users from groups according to the passed parameters
     *
     * @param array $groups the groups from which to unreg.
     * @param string $data data that identifies the users
     * @param bool $unregfrommgroups also unreg. from moodle groups
     * @param bool $previewonly only preview
     * @param bool $unregfromallagrps If true unregisters users from all occurrences of the given groups in any grouptool instance
     * @return array
     * @throws notenoughregs
     * @throws registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function unregister($groups, $data, $unregfrommgroups = true, $previewonly = false, $unregfromallagrps = false) {
        global $DB, $OUTPUT;

        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $message = "";
        $error = false;
        $users = preg_split("/[ ,;\t\n\r]+/", $data);
        // Prevent selection of all users if one of the above defined characters are in the beginning!
        foreach ($users as $key => $user) {
            if (empty($user)) {
                unset($users[$key]);
            }
        }

        $agrp = [];
        $groupname = [];
        foreach ($groups as $group) {
            if ($unregfromallagrps) {
                $agrp[$group] = $DB->get_fieldset_select('grouptool_agrps', 'id', 'groupid = :groupid', [
                    'groupid' => $group,
                ]);
                $groupname[$group] = $DB->get_field('groups', 'name', [
                    'id' => $group,
                ], IGNORE_MISSING);
            } else {
                $agrp[$group] = $DB->get_field('grouptool_agrps', 'id', [
                    'grouptoolid' => $this->grouptool->id,
                    'groupid' => $group,
                ], IGNORE_MISSING);
                $groupname[$group] = $DB->get_field('groups', 'name', [
                    'id' => $group,
                ], IGNORE_MISSING);

                if (
                    !$DB->record_exists('grouptool_agrps', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid' => $group,
                        'active' => 1,
                    ])
                ) {
                    $message .= $OUTPUT->notification(get_string(
                        'unregister_in_inactive_group_warning',
                        'grouptool',
                        $groupname[$group]
                    ), notification::NOTIFY_ERROR);
                }
            }
        }
        if (false !== ($gtimportfields = get_config('mod_grouptool', 'importfields'))) {
            $importfields = explode(',', $gtimportfields);
        } else {
            $importfields = ['username', 'idnumber'];
        }
        $prevtable = new html_table();
        $prevtable->attributes['class'] = 'importpreview table table-striped table-hover';
        $prevtable->id = 'unregisterpreview';
        $prevtable->head = [get_string('fullname')];
        foreach ($importfields as $field) {
            $prevtable->head[] = get_string($field);
        }
        $prevtable->head[] = get_string('status');
        $prevtable->data = [];
        $pbar = new progress_bar('unregisterprogress', 500, true);
        $count = count($users);
        $processed = 0;
        $pbar->update($processed, $count, get_string('unregister_progress_start', 'grouptool'));
        core_php_time_limit::raise(count($users) * 5);
        raise_memory_limit(MEMORY_HUGE);
        $followchangessetting = $DB->get_field('grouptool', 'ifmemberremoved', ['id' => $this->grouptool->id]);
        foreach ($users as $user) {
            $userinfo = $utils->find_userinfo($importfields, $user);
            $pbar->update($processed, $count, get_string('import_progress_search', 'grouptool') . ' ' . $user);
            $row = new html_table_row();
            $errors = 0;
            foreach ($utils->check_userinfo($userinfo, $user, $importfields) as $errorrow) {
                $prevtable->data[] = $errorrow;
                $errors++;
                $error = true;
            }
            if ($errors == 0) {
                $userinfo = reset($userinfo);
                $row->cells = [new html_table_cell(fullname($userinfo))];
                foreach ($importfields as $curfield) {
                    $row->cells[] = new html_table_cell(empty($userinfo->$curfield) ? '' : $userinfo->$curfield);
                }
                if (!is_enrolled($this->context, $userinfo->id)) {
                    $userinfo->fullname = fullname($userinfo);
                    if (empty($userinfo->deleted)) {
                        $text = get_string('user_is_not_enrolled', 'grouptool', $userinfo);
                        $row->cells[] = new html_table_cell(
                            $OUTPUT->notification($text, notification::NOTIFY_ERROR)
                        );
                    } else {
                        $text = get_string('user_is_deleted', 'grouptool', $userinfo);
                        $row->cells[] = new html_table_cell(
                            $OUTPUT->notification($text, notification::NOTIFY_ERROR)
                        );
                    }
                    $error = true;
                    continue;
                }
                foreach ($groups as $group) {
                    $data = [
                        'id' => $userinfo->id,
                        'idnumber' => $userinfo->idnumber,
                        'fullname' => fullname($userinfo),
                        'groupname' => $groupname[$group],
                    ];
                    if (!$previewonly && $userinfo) {
                        $wasunregfrommgroup = false;
                        $wasunregfrommgtgroup = false;
                        $notinmgroup = false;

                        $pbar->update(
                            $processed,
                            $count,
                            get_string(
                                'unregister_progress_unregister',
                                'grouptool'
                            ) . ' ' . fullname($userinfo) . '...'
                        );
                        [$insql, $inparams] = $DB->get_in_or_equal($agrp[$group], SQL_PARAMS_NAMED);
                        $inparams['userid'] = $data['id'];
                        $sqlreg = "SELECT * FROM {grouptool_registered} WHERE agrpid $insql AND userid=:userid";
                        $sqlqueue = "SELECT * FROM {grouptool_queued} WHERE agrpid $insql AND userid=:userid";
                        if (
                            (!$DB->record_exists_sql($sqlreg, $inparams) &&
                                !$DB->record_exists_sql($sqlqueue, $inparams)) || $unregfrommgroups
                        ) {
                            if (groups_is_member($group, $data['id']) && $unregfrommgroups) {
                                groups_remove_member($group, $data['id']);
                                $wasunregfrommgroup = true;
                            } else {
                                $notinmgroup = true;
                            }
                        }
                        if (
                            $followchangessetting && $DB->record_exists('groups_members', [
                                'groupid' => $group,
                                'userid' => $data['id'],
                            ])
                        ) {
                            $DB->delete_records('groups_members', [
                                'groupid' => $group,
                                'userid' => $data['id'],
                            ]);

                            $time = time();
                            $DB->set_field('groups', 'timemodified', $time, ['id' => $group]);

                            cache_helper::invalidate_by_definition(
                                'core',
                                'user_group_groupings',
                                [],
                                [$data['id']]
                            );

                            $context = context_course::instance($this->grouptool->course);
                            if (
                                $conversation = api::get_conversation_by_area(
                                    'core_group',
                                    'groups',
                                    $group,
                                    $context->id
                                )
                            ) {
                                api::remove_members_from_conversation([$data['id']], $conversation->id);
                            }
                        }

                        if ($unregfromallagrps) {
                            if (is_array($agrp[$group])) {
                                foreach ($agrp[$group] as $agrpinst) {
                                    if (
                                        $DB->record_exists('grouptool_registered', [
                                            'agrpid' => $agrpinst,
                                            'userid' => $data['id'],
                                        ]) ||
                                        $DB->record_exists('grouptool_queued', [
                                            'agrpid' => $agrpinst,
                                            'userid' => $data['id'],
                                        ])
                                    ) {
                                        $this->unregister_from_agrp(
                                            $agrpinst,
                                            $userinfo->id,
                                            false,
                                            true,
                                            true
                                        );
                                    }
                                }
                            } else {
                                $this->unregister_from_agrp($agrp[$group], $userinfo->id, false, true);
                            }
                            $wasunregfrommgtgroup = true;
                        }
                        if ($wasunregfrommgroup && !$wasunregfrommgtgroup) {
                            $row->cells[] = get_string(
                                'unregister_user_from_moodle_group',
                                'grouptool',
                                $data
                            );
                            $row->attributes['class'] = 'success';
                        } else if ($notinmgroup && !$wasunregfrommgtgroup) {
                            $row->cells[] = get_string(
                                'unregister_user_not_in_group',
                                'grouptool',
                                $data
                            );
                            $row->attributes['class'] = 'success';
                        } else {
                            $row->cells[] = get_string('unregister_user', 'grouptool', $data);
                            $row->attributes['class'] = 'success';
                        }
                    } else if ($userinfo) {
                        if (
                            !$DB->record_exists_select(
                                'grouptool_registered',
                                "agrpid = :agrpid AND userid = :userid",
                                ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]
                            )
                        ) {
                            if (groups_is_member($group, $userinfo->id)) {
                                $cell = get_string(
                                    'unregister_user_only_in_moodle_group',
                                    'grouptool',
                                    $data
                                );
                                $row->cells[] = $cell;
                                $row->attributes['class'] = 'prevsuccess';
                            } else {
                                $cell = get_string(
                                    'unregister_conflict_user_not_in_group',
                                    'grouptool',
                                    $data
                                );
                                $row->cells[] = $cell;
                                $row->attributes['class'] = 'prevconflict';
                            }
                        } else {
                            $row->cells[] = get_string(
                                'unregister_user_prev',
                                'grouptool',
                                $data
                            );
                            $row->attributes['class'] = 'prevsuccess';
                        }
                    }
                }
            }
            $prevtable->data[] = $row;
            unset($row);
            $processed++;
        }
        $processed++;
        if (!$previewonly) {
            $pbar->update($processed, $count, get_string(
                'unregister_progress_completed',
                'grouptool'
            ));
        } else {
            $pbar->update($processed, $count, get_string(
                'unregister_progress_preview_completed',
                'grouptool'
            ));
        }
        $message .= html_writer::table($prevtable);
        return [$error, $message];
    }

    /**
     * push in grouptool registered users to moodle-groups
     *
     * @param int $groupid optional only for this group
     * @param int $groupingid optional only for this grouping
     * @param bool $previewonly optional get only the preview
     * @return array($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public function push_registrations(int $groupid = 0, int $groupingid = 0, bool $previewonly = false) {
        global $DB, $OUTPUT;

        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        // Trigger the event!
        registration_push_started::create_from_object($this->cm)->trigger();

        $userinfo = get_enrolled_users($this->context);
        $return = [];
        // Get active groups filtered by groupid, grouping_id, grouptoolid!
        $agrps = $groupmanager->get_active_groups(true, false, 0, $groupid, $groupingid);
        foreach ($agrps as $groupid => $agrp) {
            foreach ($agrp->registered as $reg) {
                $info = new stdClass();
                if (!key_exists($reg->userid, $userinfo)) {
                    $userinfo[$reg->userid] = $DB->get_record('user', ['id' => $reg->userid]);
                }
                $info->username = fullname($userinfo[$reg->userid]);
                $info->groupname = $agrp->name;
                if (!groups_is_member($groupid, $reg->userid)) {
                    // Add to group if is not already!
                    if (!$previewonly) {
                        if (!is_enrolled($this->context, $reg->userid)) {
                            /*
                             * if user's not enrolled already we force manual enrollment in course,
                             * so we can add the user to the group
                             */
                            try {
                                $utils->force_enrol_student($reg->userid);
                            } catch (Exception $e) {
                                $return[] = $OUTPUT->notification($e->getMessage(), notification::NOTIFY_ERROR);
                            } catch (Throwable $t) {
                                $return[] = $OUTPUT->notification($t->getMessage(), notification::NOTIFY_ERROR);
                            }
                        }
                        if (groups_add_member($groupid, $reg->userid)) {
                            $return[] = html_writer::tag('div', get_string(
                                'added_member',
                                'grouptool',
                                $info
                            ), ['class' => 'notifysuccess']);
                        } else {
                            $return[] = html_writer::tag('div', get_string(
                                'could_not_add',
                                'grouptool',
                                $info
                            ), ['class' => 'notifyproblem']);
                        }
                    } else {
                        $return[] = html_writer::tag('div', get_string(
                            'add_member',
                            'grouptool',
                            $info
                        ), ['class' => 'notifysuccess']);
                    }
                } else {
                    $return[] = html_writer::tag('div', get_string(
                        'already_member',
                        'grouptool',
                        $info
                    ), ['class' => 'ignored']);
                }
            }
        }
        switch (count($return)) {
            default:
                return [false, implode("<br />\n", $return)];
            case 1:
                return [false, current($return)];
            case 0:
                return [true, get_string('nothing_to_push', 'grouptool')];
        }
    }

    /**
     * Returns the amount of registrations missing in this grouptool instance.
     *
     * @return int amount of missing registrations (includes queues!)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_missing_registrations() {
        global $DB;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        [$esql, $params] = get_enrolled_sql($this->context, 'mod/grouptool:register');

        $sql = "SELECT u.id
                  FROM {user} u
             LEFT JOIN ($esql) eu ON eu.id=u.id
                 WHERE u.deleted = 0 AND eu.id=u.id ";
        $users = $DB->get_records_sql($sql, $params);

        if (empty($users)) {
            return 0;
        }

        [$usql, $uparams] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usr');

        $min = $this->grouptool->allowmultiple ? $this->grouptool->choosemin : 1;

        if ($min == 0) {
            return 0;
        }

        $agrps = $groupmanager->get_active_groups(
            false,
            false,
            0,
            0,
            0,
            false
        );
        $keys = array_keys($agrps);

        if (empty($keys)) {
            $keys = [-1];
        }
        [$agrpsql, $params] = $DB->get_in_or_equal($keys, SQL_PARAMS_NAMED, 'agrp');
        $params = array_merge($uparams, $params);
        $regs = $DB->get_records_sql_menu("SELECT u.id, count(r.id)
                                             FROM {user} u
                                        LEFT JOIN {grouptool_registered} r ON u.id = r.userid AND r.modified_by >= 0
                                                  AND r.agrpid " . $agrpsql . "
                                            WHERE u.id " . $usql . "
                                         GROUP BY u.id", $params);
        $queues = $DB->get_records_sql_menu("SELECT u.id, count(q.id)
                                               FROM {user} u
                                          LEFT JOIN {grouptool_queued} q ON u.id = q.userid AND q.agrpid " . $agrpsql . "
                                              WHERE u.id " . $usql . "
                                           GROUP BY u.id", $params);

        $missing = 0;
        foreach ($users as $user) {
            $userregs = $regs[$user->id] + $queues[$user->id];
            if ($userregs < $min) {
                $missing += $min - $userregs;
            }
        }

        return $missing;
    }

    /**
     * returns object with information about registrations/queues for each group
     * (optional with userdata)
     * if $user == 0 no userdata is returned
     * else if $user == null data about $USERs registrations/queues is added
     * else data about $userids registrations/queues is added
     *
     * @param int|null $userid id of user for whom data should be added
     *                    or 0 (=$USER) or null (=no userdata)
     * @return stdClass object containing information about active groups
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public function get_registration_stats(?int $userid = null): stdClass {
        global $USER, $DB;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $return = new stdClass();
        $return->group_places = 0;
        $return->free_places = 0;
        $return->occupied_places = 0;
        $return->users = 0;
        $return->registered = [];
        $return->queued = [];
        $return->queued_users = 0;
        $return->reg_users = 0;

        switch ($userid) {
            case null:
                $userid = $USER->id;
                // No user data, set id and go to default case.
            default:
                $groups = $groupmanager->get_active_groups(false, false);
                break;
            case 0:
                $groups = $groupmanager->get_active_groups();
                break;
        }

        foreach ($groups as $group) {
            $group = $groupmanager->get_active_groups(true, true, $group->agrpid, $group->id);
            $group = current($group);
            if ($this->grouptool->usesize) {
                $return->group_places += $group->grpsize;
            }
            $return->occupied_places += count($group->registered);
            if ($userid != 0) {
                $regrank = $queuemanager->get_rank_in_queue($group->registered, $userid);
                if (!empty($regrank)) {
                    $regdata = new stdClass();
                    $regdata->rank = $regrank;
                    $regdata->grpname = $group->name;
                    $regdata->agrpid = $group->agrpid;
                    reset($group->registered);
                    do {
                        $current = current($group->registered);
                        $regdata->timestamp = $current->timestamp;
                        next($group->registered);
                    } while ($current->userid != $userid);
                    $regdata->id = $group->id;
                    $return->registered[] = $regdata;
                }

                $queuerank = $queuemanager->get_rank_in_queue($group->queued, $userid);
                if (!empty($queuerank)) {
                    $queuedata = new stdClass();
                    $queuedata->rank = $queuerank;
                    $queuedata->grpname = $group->name;
                    $queuedata->agrpid = $group->agrpid;
                    reset($group->queued);
                    do {
                        $current = current($group->queued);
                        $queuedata->timestamp = $current->timestamp;
                        next($group->queued);
                    } while ($current->userid != $userid);
                    $queuedata->id = $group->id;
                    $return->queued[] = $queuedata;
                }
            }
        }
        $return->free_places = ($this->grouptool->usesize) ? ($return->group_places - $return->occupied_places) : null;
        $return->users = count_enrolled_users($this->context, 'mod/grouptool:register');

        $agrps = $DB->get_records('grouptool_agrps', ['grouptoolid' => $this->cm->instance, 'active' => 1]);
        if (is_array($agrps) && count($agrps) >= 1) {
            $agrpids = array_keys($agrps);
            [$inorequal, $params] = $DB->get_in_or_equal($agrpids);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_registered}
                     WHERE modified_by >= 0 AND agrpid " . $inorequal;
            $return->reg_users = $DB->count_records_sql($sql, $params);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_queued}
                     WHERE agrpid " . $inorequal;
            $return->queued_users = $DB->count_records_sql($sql, $params);
        } else {
            $return->reg_users = 0;
        }
        $return->notreg_users = $return->users - $return->reg_users;

        return $return;
    }

    /**
     * Returns the amount of registrations for a particular active group.
     *
     * @param int $agrpid ID of the active group
     * @return int amount of registrations (includes queues!)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_group_registrations_count(int $agrpid) {
        global $DB;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $agrp = $groupmanager->get_active_groups(false, false, $agrpid);
        if (count($agrp) != 1) {
            throw new registration('error_getting_data');
        }
        return $DB->count_records('grouptool_registered', ['agrpid' => $agrpid]) +
            $DB->count_records('grouptool_queued', ['agrpid' => $agrpid]);
    }
}
