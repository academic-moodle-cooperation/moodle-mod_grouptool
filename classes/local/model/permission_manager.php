<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_grouptool\local\model;

use core\exception\coding_exception;
use core\exception\required_capability_exception;
use dml_exception;
use mod_grouptool\exception\exceedgroupqueuelimit;
use mod_grouptool\exception\exceedgroupsize;
use mod_grouptool\exception\exceeduserqueuelimit;
use mod_grouptool\exception\exceeduserreglimit;
use mod_grouptool\exception\notenoughregs;
use mod_grouptool\exception\registration;
use mod_grouptool\exception\regpresent;
use mod_grouptool\local\grouptool_instance;
use stdClass;

class permission_manager extends grouptool_instance {
    /**
     * Check if user can change the group! Works different by returning 0 or 1!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @return bool whether or not user qualifies for a group change
     */
    public function qualifies_for_groupchange($agrpid, $userid) {
        // Not really used here, but at least empty values needed by can_change_group()!
        $message = new stdClass();
        $message->username = '';
        $message->groupname = '';

        try {
            $this->can_change_group($agrpid, $userid, $message);
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }
    /**
     * Check if user is already registered, queued or marked for registration, throw exception in that case!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $groupdata Object with group info
     * @param stdClass $message (optional) cached data for the language strings
     * @throws regpresent
     * @throws dml_exception
     */
    protected function check_reg_present($agrpid, $userid, $groupdata, $message) {
        global $USER;

        if ($this->grpmarked($agrpid, $userid)) {
            // Allready marked for registration!?!
            if ($userid != $USER->id) {
                throw new regpresent('already_marked', $message);
            } else {
                throw new regpresent('you_are_already_marked', $message);
            }
        }

        if (!empty($groupdata->registered) && $this->get_rank_in_queue($groupdata->registered, $userid) != false) {
            // We're sorry, but user's already registered in this group!
            if ($userid != $USER->id) {
                throw new regpresent('already_registered', $message);
            } else {
                throw new regpresent('you_are_already_registered', $message);
            }
        }

        if (!empty($groupdata->queued) && $this->get_rank_in_queue($groupdata->queued, $userid) != false) {
            // We're sorry, but user's already queued in this group!
            if ($userid != $USER->id) {
                throw new regpresent('already_queued', $message);
            } else {
                throw new regpresent('you_are_already_queued', $message);
            }
        }
    }


    /**
     * Check if user can change the group! Works different by returning 0 or 1!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message cached data for the language strings
     * @param int $oldagrpid (optional) ID of former active group
     * @param bool $useunreg (optional) whether to use unregistration or not if it is activated or not
     * @return string 'string' status message
     * @throws exceedgroupqueuelimit
     * @throws exceeduserreglimit
     * @throws exceeduserqueuelimit
     * @throws registration
     * @throws regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function can_change_group($agrpid, $userid, $message, $oldagrpid = null, $useunreg = true) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);
        if ($useunreg && empty($this->grouptool->allow_unreg)) {
            throw new registration('unreg_not_allowed');
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        [$userregs, $userqueues, , , $max] = $this->check_users_regs_limits($userid, true);

        if (
            ($oldagrpid === null)
            && !(($userqueues == 1 && $userregs == $max - 1) || ($userqueues + $userregs == 1 && $max == 1))
        ) {
            // We can't determine a unique group to unreg the user from! He has to do it by manually!
            throw new registration('groupchange_from_non_unique_reg');
        }

        if (
            $this->grouptool->use_size && !empty($groupdata->registered)
            && (count($groupdata->registered) >= $groupdata->grpsize)
        ) {
            if (!$this->grouptool->use_queue) {
                // We can't register the user nor queue the user!
                throw new exceedgroupsize();
            } else if (count($groupdata->queued) >= $this->grouptool->groups_queues_limit) {
                throw new exceedgroupqueuelimit();
            }

            if (
                $this->grouptool->users_queues_limit && ($userqueues >= $this->grouptool->users_queues_limit)
                && ($userqueues != 1)
            ) {
                // We can't queue him, due to exceeding his queue limit or not being able to determine which queue entry to unreg!
                throw new exceeduserqueuelimit();
            }
        }

        // We have no 'you'-version of the string here!
        return get_string('change_group_to', 'grouptool', $message);
    }
    /**
     * Returns whether or not a user should be able to see the members of this active group.
     * Either if regrank or queuerank are not set, agrp has to be set!
     *
     * @param int|object $agrp Active group's DB ID or active group object
     * @param int|bool $regrank The registration rank in this active group
     *                          (false if not registered or null if it has to be determined for the current user)
     * @param int|bool $queuerank The queue rank in this active group
     *                            (false if not queued or null if it has to be determined for the current user)
     * @return bool true if user can show, false if not!
     * @throws coding_exception
     * @throws dml_exception
     */
    public function canshowmembers($agrp = null, $regrank = null, $queuerank = null) {
        global $DB, $USER;

        if (
            $regrank === null
            || $queuerank === null
        ) {
            if (is_numeric($agrp)) {
                $agrpid = $agrp;
            } else if (is_object($agrp) && isset($agrp->id)) {
                $agrpid = $agrp->id;
            } else {
                throw new coding_exception('$agrp has to be the active group ID or an object containing $agrp->id');
            }

            if ($regrank === null) {
                $regrank = $DB->record_exists('grouptool_registered', ['userid' => $USER->id, 'agrpid' => $agrpid]);
            }

            if ($queuerank === null) {
                $queuerank = $DB->record_exists('grouptool_queued', ['userid' => $USER->id, 'agrpid' => $agrpid]);
            }
        }

        switch ($this->grouptool->show_members) {
            case self::SHOW_GROUPMEMBERS:
                $showmembers = true;
                break;
            case self::SHOW_GROUPMEMBERS_AFTER_DUE:
                $showmembers = (time() > $this->grouptool->timedue);
                break;
            case self::SHOW_OWN_GROUPMEMBERS_AFTER_REG:
                $showmembers = ($regrank !== false) || ($queuerank !== false);
                break;
            case self::SHOW_OWN_GROUPMEMBERS_AFTER_DUE:
                $showmembers = (time() > $this->grouptool->timedue)
                    && (($regrank !== false) || ($queuerank !== false));
                break;
            default:
            case self::HIDE_GROUPMEMBERS:
                $showmembers = false;
                break;
        }

        return $showmembers;
    }
    /**
     * Checks if user has to many, too less registrations and return values!
     *
     * @param int $userid User's ID
     * @param bool $change (optional) true if check is used for group change!
     * @return array $userregs, $userqueues, $marks, $min, $max
     * @throws exceeduserreglimit
     * @throws registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function check_users_regs_limits($userid, $change = false) {
        global $DB;

        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select(
            'grouptool_agrps',
            'id',
            "grouptoolid = ? AND active = 1",
            [$this->grouptool->id]
        );
        [$agrpsql, $params] = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select(
            'grouptool_registered',
            "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql,
            $params
        );
        $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid " . $agrpsql, $params);
        $marks = $this->count_user_marks($userid);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;

        if ($change) {
            if ($min > ($marks + $userregs + $userqueues)) {
                throw new registration('too_many_registrations');
            }
            if ($max < ($marks + $userregs + $userqueues)) {
                throw new exceeduserreglimit();
            }
        } else {
            if ($min <= ($marks + $userregs + $userqueues)) {
                throw new registration('too_many_registrations');
            }
            if ($max <= ($marks + $userregs + $userqueues)) {
                throw new exceeduserreglimit();
            }
        }

        return [$userregs, $userqueues, $marks, $min, $max];
    }

    /**
     * Check if user can be marked for registration, else throw exception!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message cached data for the language strings
     * @return array (queued, string) status message
     * @throws exceeduserreglimit
     * @throws registration
     * @throws regpresent
     * @throws exceedgroupsize
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function can_be_marked(int $agrpid, int $userid, stdClass $message) {
        global $USER;

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        $full = !empty($this->grouptool->groups_queues_limit)
            && (count($groupdata->queued) >= $this->grouptool->groups_queues_limit);
        if (
            $this->grouptool->use_size && (count($groupdata->registered) >= $groupdata->grpsize)
            && (!$this->grouptool->use_queue || $full)
        ) {
            throw new exceedgroupsize();
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        $this->check_users_regs_limits($userid);

        if ($this->grouptool->use_size && (count($groupdata->registered) >= $groupdata->grpsize)) {
            if ($userid != $USER->id) {
                return [1, get_string('queue_in_group', 'grouptool', $message)];
            } else {
                return [1, get_string('queue_you_in_group', 'grouptool', $message)];
            }
        } else {
            if ($userid != $USER->id) {
                return [0, get_string('register_in_group', 'grouptool', $message)];
            } else {
                return [0, get_string('register_you_in_group', 'grouptool', $message)];
            }
        }
    }
    /**
     * Check if user can be queued, else throw exception!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message (optional) prepared message object containing username and groupname or null
     * @return string status message
     * @throws exceedgroupqueuelimit
     * @throws exceeduserqueuelimit
     * @throws exceeduserreglimit
     * @throws exceedgroupsize
     * @throws notenoughregs
     * @throws registration
     * @throws regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function can_be_queued($agrpid, $userid = null, $message = null) {
        global $USER, $DB;

        // Shortcut if we don't use queues!
        if (!$this->grouptool->use_queue) {
            throw new exceedgroupsize();
        }

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        if ($message === null) {
            $message = new stdClass();
            if ($userid != $USER->id) {
                $userdata = $DB->get_record('user', ['id' => $userid]);
                $message->username = fullname($userdata);
            } else {
                $message->username = fullname($USER);
            }
            $message->groupname = $groupdata->name;
        }

        /* Get user's marks and also check if enough (queue) places are available,
         * otherwise display an info and remove marked entry. */
        $usermarks = $this->get_user_marks($userid);

        $queues = $this->get_user_queues_count($userid);
        $queueswithmarks = $queues;
        foreach ($usermarks as $cur) {
            if ($cur->type != 'reg') {
                $queueswithmarks++;
            }
        }

        if (
            $this->grouptool->users_queues_limit && (($queueswithmarks > $this->grouptool->users_queues_limit)
                || ($queues >= $this->grouptool->users_queues_limit))
        ) {
            throw new exceeduserqueuelimit();
        }

        if ($this->grouptool->groups_queues_limit && (count($groupdata->queued) >= $this->grouptool->groups_queues_limit)) {
            throw new exceedgroupqueuelimit();
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $userregs = $this->get_user_reg_count($userid);
        $marks = $this->count_user_marks($userid);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($max <= ($marks + $userregs + $queues)) {
            throw new exceeduserreglimit();
        }
        if ($min > ($marks + $userregs + $queues + 1)) {
            // Not enough registrations/queues/marks!
            throw new notenoughregs();
        }

        if ($userid != $USER->id) {
            return get_string('queue_in_group', 'grouptool', $message);
        } else {
            return get_string('queue_you_in_group', 'grouptool', $message);
        }
    }
    /**
     *
     * Checks if a given count of userregs, queues and marks matches the limits for a given group
     *
     * @param stdClass $group Group which should be checked against the counts
     * @param int $userregs Count of group registrations of a user
     * @param int $queues Count of queue registrations of a user
     * @param int $marks Count of marks (inactive registrations) of a user
     * @throws exceedgroupsize
     * @throws exceeduserreglimit
     * @throws notenoughregs
     */
    public function check_can_be_registered($group, $userregs, $queues, $marks) {
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($this->grouptool->use_size && (count($group->registered) >= $group->grpsize)) {
            throw new exceedgroupsize();
        }
        if ($max <= ($marks + $userregs + $queues)) {
            throw new exceeduserreglimit();
        }
        if ($min > ($marks + $userregs + $queues + 1)) {
            // Not enough registrations/queues/marks!
            throw new notenoughregs();
        }
    }

    /**
     * Checks if user can be registered, else throw exception!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message prepared message object containing username and groupname or null
     * @return string status message
     * @throws exceedgroupsize
     * @throws exceeduserreglimit
     * @throws notenoughregs
     * @throws registration
     * @throws regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function can_be_registered($agrpid, $userid, $message) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        // Check if enough (queue) places are available, otherwise display an info and remove marked entry.
        $userregs = $this->get_user_reg_count($userid);
        $queues = $this->get_user_queues_count($userid);
        $marks = $this->count_user_marks($userid);

        $this->check_can_be_registered($groupdata, $userregs, $queues, $marks);

        if ($userid != $USER->id) {
            return get_string('register_in_group', 'grouptool', $message);
        } else {
            return get_string('register_you_in_group', 'grouptool', $message);
        }
    }
}
