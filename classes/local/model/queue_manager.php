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

use core\exception\moodle_exception;
use core\message\message;
use core\output\html_writer;
use core_user;
use mod_grouptool\event\dequeuing_started;
use mod_grouptool\event\queue_entry_created;
use mod_grouptool\event\queue_entry_deleted;
use mod_grouptool\event\user_moved;
use mod_grouptool\exception\registration;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\local\grouptool_utils;
use stdClass;

/**
 * Class containing the logic for managing the queue of a grouptool instance
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_manager extends grouptool_instance {
    /**
     * Fills the group as much as possible with entries from the queue.
     * Usefull for group size changes or if someone is removed from the group or unregisters him-/herself
     *
     * @param int $agrpid Active group to fill
     * @return bool true if everything went fine!
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception|moodle_exception
     */
    public function fill_from_queue(int $agrpid): bool {
        global $DB, $CFG, $OUTPUT;

        $registrationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (empty($this->grouptool->usequeue)) {
            return true;
        }

        $groupdata = $groupmanager->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);

        if (empty($groupdata->queued)) {
            return true;
        }

        // TODO Move SQL to repository methods!
        $agrpids = $DB->get_fieldset_sql('SELECT id
                                            FROM {grouptool_agrps}
                                           WHERE grouptoolid = ?', [$this->grouptool->id]);
        [$agrpssql, $agrpsparam] = $DB->get_in_or_equal($agrpids);
        $sql = "SELECT queued.id, MAX(agrp.groupid) AS groupid, MAX(queued.agrpid) AS agrpid,
                       MAX(queued.userid) AS userid, MAX(queued.timestamp) AS timestamp,
                       (COUNT(DISTINCT reg.id) < ?) AS priority
                  FROM {grouptool_queued} queued
             LEFT JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
             LEFT JOIN {grouptool_registered} reg ON queued.userid = reg.userid
                                                     AND reg.agrpid " . $agrpssql . "
                 WHERE queued.agrpid = ?
              GROUP BY queued.id
              ORDER BY priority DESC, queued.timestamp ASC";
        $params = array_merge([$this->grouptool->choosemin], $agrpsparam, [$agrpid]);
        $records = $DB->get_records_sql($sql, $params);

        if (empty($records) || count($records) == 0) {
            return true;
        }

        $message = new stdClass();
        $message->groupname = $groupdata->name;

        foreach ($records as $record) {
            if (!empty($this->grouptool->usesize) && ($groupdata->grpsize <= count($groupdata->registered))) {
                return true;
            }
            $newrecord = clone $record;
            unset($newrecord->id);
            $newrecord->modified_by = $newrecord->userid;
            $newrecord->id = $DB->insert_record('grouptool_registered', $newrecord);
            $groupdata->registered[] = $newrecord;
            if (!empty($this->grouptool->immediatereg)) {
                groups_add_member($groupdata->id, $newrecord->userid);
            }
            $allowm = $this->grouptool->allowmultiple;
            $usrregcnt = $registrationmanager->get_user_reg_count($newrecord->userid);
            $max = $this->grouptool->choosemax;
            if (($allowm && ($usrregcnt >= $max)) || !$allowm) {
                $agrps = $groupmanager->get_active_groups(
                    false,
                    false,
                    0,
                    0,
                    0,
                    false
                );
                $agrpids = array_keys($agrps);
                [$sql, $params] = $DB->get_in_or_equal($agrpids);
                $records = $DB->get_records_sql(
                    "SELECT queued.*, agrp.groupid
                                                   FROM {grouptool_queued} queued
                                                   JOIN {grouptool_agrps} agrp ON queued.agrpid = agrp.id
                                                  WHERE userid = ? AND agrpid " . $sql,
                    array_merge([$newrecord->userid], $params)
                );
                $DB->delete_records_select(
                    'grouptool_queued',
                    ' userid = ? AND agrpid ' . $sql,
                    array_merge(
                        [$newrecord->userid],
                        $params
                    )
                );
                foreach ($records as $cur) {
                    // Trigger the event!
                    queue_entry_deleted::create_limit_violation($this->cm, $cur)->trigger();
                }
            }

            $context = (object)[
                'course' => $this->course,
                'courseurl' => $CFG->wwwroot . "/course/view.php?id=" . $this->course->id,
                'coursegrouptoolsurl' => $CFG->wwwroot . "/mod/grouptool/index.php?id=" . $this->course->id,
                'grouptoolurl' => $CFG->wwwroot . "/mod/grouptool/view.php?id=" . $this->cm->id,
                'grouptoolname' => format_string($this->grouptool->name, true),
                'groupname' => $groupdata->name,
                'message' => get_string('register_you_in_group_success', 'grouptool', (object)[
                    'groupname' => $groupdata->name,
                ]),
            ];

            $postsubject = $this->course->shortname . ': ' .
                get_string('modulenameplural', 'grouptool') . ': ' .
                format_string($this->grouptool->name, true);

            $messageuser = $DB->get_record('user', ['id' => $newrecord->userid]);
            $moodlemessage = new message();
            $userfrom = core_user::get_noreply_user();
            $moodlemessage->component = 'mod_grouptool';
            $moodlemessage->name = 'grouptool_moveupreg';
            $moodlemessage->courseid = $this->course->id;
            $moodlemessage->userfrom = $userfrom;
            $moodlemessage->userto = $messageuser;
            $moodlemessage->subject = $postsubject;
            $moodlemessage->fullmessage = get_string(
                'registrationnotification',
                'mod_grouptool',
                $context
            );
            $moodlemessage->fullmessageformat = FORMAT_HTML;
            $moodlemessage->fullmessagehtml =
                $OUTPUT->render_from_template('mod_grouptool/registrationnotification', $context);
            $moodlemessage->smallmessage = $context->message;
            $moodlemessage->notification = 1;
            $moodlemessage->contexturl = $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $this->cm->id;
            $moodlemessage->contexturlname = $this->grouptool->name;

            message_send($moodlemessage);
            $DB->delete_records('grouptool_queued', ['id' => $record->id]);

            // Trigger the event!
            // We fetched groupid above in SQL.
            user_moved::promotion_from_queue($this->cm, $record, $newrecord)->trigger();
        }

        return true;
    }

    /**
     * Add a queue entry for a certain user/agrp-combination.
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message prepared message object containing username and groupname or null
     * @return string status string
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
    public function add_queue_entry($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $groupdata = $groupmanager->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        // This method throws exceptions, if user is not able to be queued!
        $permissionmanager->can_be_queued($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->id = $DB->insert_record('grouptool_queued', $record);
        // Trigger the event!
        $record->groupid = $groupdata->id;
        queue_entry_created::create_direct($this->cm, $record)->trigger();
        if ($userid != $USER->id) {
            return get_string('queue_in_group_success', 'grouptool', $message);
        } else {
            return get_string('queue_you_in_group_success', 'grouptool', $message);
        }
    }

    /**
     * returns number of queue-entries for a particular user in a particular grouptool-instance
     *
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_user_queues_count($userid = 0) {
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
                                       FROM {grouptool_queued}
                                       WHERE userid = ? AND agrpid ' . $sql, $params);
    }

    /**
     * returns rank in queue for a particular user
     * if $data is an array uses array (like queue/reg-info returned by {@see get_active_groups()})
     * to determin rank otherwise if $data is an integer uses DB-query to get queue rank in
     * active group with id == $data
     *
     * @param int[]|int $data array with regs/queues for a group like returned by get_active_groups() or agrpid
     * @param int $userid user for whom data should be returned
     * @return int rank in queue/registration (registration only via $data-array)
     * @throws dml_exception
     */
    public function get_rank_in_queue($data = 0, $userid = 0) {
        global $DB, $USER;

        // TODO CHECK IF THIS STILL WORKS AS EXPECTED, BECAUSE METHOD WAS MOVED TO UTILS!
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (is_array($data)) { // It's the queue itself!
            uasort($data, [$this, "cmptimestamp"]);
            $i = 1;
            foreach ($data as $entry) {
                if ($entry->userid == $userid) {
                    return $i;
                } else {
                    $i++;
                }
            }
            return false;
        } else if (!empty($data)) { // It's an active-group-id, so we gotta get the queue data!
            $params = [
                'agrpid' => $data,
                'userid' => !empty($userid) ? $userid : $USER->id,
            ];
            // TODO MOVE SQL TO REPOSITORY!
            $sql = "SELECT count(b.id) AS rank
                      FROM {grouptool_queued} a
                INNER JOIN {grouptool_queued} b ON b.timestamp <= a.timestamp
                     WHERE a.agrpid = :agrpid AND a.userid = :userid";
        } else {
            return null;
        }

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * resolves queues by filling empty group places in defined order with students from the queue
     *
     * @todo there's a bug which prevents deletion of some queue entries, only happened on
     *       development system with admin-users account several times, whatch out for the future
     * @todo sometimes not every queue entry is resolved, happened unregularly on development system
     *       watch in production system
     *
     * @param bool $previewonly show only preview of actions
     * @return array ($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function resolve_queues($previewonly = false) {
        global $DB, $USER, $CFG, $OUTPUT;

        $registrationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $error = false;
        $returntext = '';
        $status = [];

        // Trigger event!
        dequeuing_started::create_from_object($this->cm)->trigger();

        $grouptool = $this->grouptool;
        $context = $this->context;

        require_capability('mod/grouptool:administrate_registration', $context);

        $agrps = $groupmanager->get_active_groups(
            false,
            false,
            0,
            0,
            0,
            false
        );
        if (empty($agrps)) {
            return [true, get_string('no_active_groups', 'grouptool')];
        }

        [$agrpsql, $params] = $DB->get_in_or_equal(array_keys($agrps), SQL_PARAMS_NAMED, 'reg');
        [$agrpsql2, $params2] = $DB->get_in_or_equal(array_keys($agrps), SQL_PARAMS_NAMED, 'queue');

        $agrpids = array_keys($agrps);
        [$agrpssql, $agrpsparam] = $DB->get_in_or_equal($agrpids);
        $agrpsfiltersql = " AND agrp.id " . $agrpssql;
        $agrpsfilterparams = array_merge([$grouptool->id], $agrpsparam);
        // Get queue-entries (sorted by timestamp)!
        if (!empty($grouptool->allowmultiple)) {
            $queuedsql = " WHERE queued.agrpid " . $agrpssql . " ";
            $queuedparams = array_merge($agrpsparam, $agrpsparam);

            $queueentries = $DB->get_records_sql(
                "
                      SELECT queued.id, MAX(queued.agrpid) AS agrpid, MAX(queued.userid) AS userid,
                             MAX(queued.timestamp) AS timestamp, (COUNT(DISTINCT reg.id) < ?) AS priority
                        FROM {grouptool_queued} queued
                   LEFT JOIN {grouptool_registered} reg ON queued.userid = reg.userid AND reg.agrpid " . $agrpssql .
                " AND reg.modified_by >= 0
                    " . $queuedsql . "
                    GROUP BY queued.id
                    ORDER BY priority DESC, queued.timestamp ASC",
                array_merge([$grouptool->choosemin], $queuedparams)
            );
        } else {
            $queuedsql = " WHERE queued.agrpid " . $agrpssql . " ";
            $queuedparams = $agrpsparam;
            $queueentries = $DB->get_records_sql(
                "SELECT *, '1' AS priority
                                                        FROM {grouptool_queued} queued" .
                $queuedsql .
                "ORDER BY timestamp ASC",
                $queuedparams
            );
        }
        $userregs = $DB->get_records_sql_menu('SELECT reg.userid, COUNT(DISTINCT reg.id)
                                                     FROM {grouptool_registered} reg
                                                    WHERE reg.agrpid ' . $agrpssql . ' AND modified_by >= 0
                                                 GROUP BY reg.userid', $agrpsparam);


        // Get group entries (sorted by sort-order)!
        $groupsdata = $DB->get_records_sql("
                SELECT agrp.id AS id, MAX(agrp.groupid) AS groupid, MAX(agrp.grpsize) AS grpsize,
                       COUNT(DISTINCT reg.id) AS registered
                  FROM {grouptool_agrps} agrp
             LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrp.id AND modified_by >= 0
                 WHERE agrp.grouptoolid = ?" . $agrpsfiltersql . "
              GROUP BY agrp.id
              ORDER BY agrp.sort_order ASC", $agrpsfilterparams);

        $i = 0;

        if (!empty($groupsdata) && !empty($queueentries)) {
            $fullnames = $DB->get_records_sql_menu("SELECT DISTINCT u.id, " . $DB->sql_fullname() . "
                                                  FROM {user} u
                                             LEFT JOIN {grouptool_queued} q ON q.userid = u.id AND q.agrpid " . $agrpsql2 . "
                                             LEFT JOIN {grouptool_registered} r ON r.userid = u.id AND r.agrpid " . $agrpsql . "
                                                 WHERE (r.id IS NOT NULL OR q.id IS NOT NULL)", $params + $params2);
            $planned = new stdClass();
            $curgroup = null;
            $maxregs = !empty($this->grouptool->allowmultiple) ? $this->grouptool->choosemax : 1;
            reset($groupsdata);
            $message = new stdClass();
            foreach ($queueentries as $queue) {
                // Get first non-full group!
                while (($curgroup == null) || ($curgroup->grpsize <= $curgroup->registered)) {
                    if ($curgroup === null) {
                        $curgroup = current($groupsdata);
                    } else {
                        $curgroup = next($groupsdata);
                    }
                    if ($curgroup === false) {
                        $error = true;
                        $username = $DB->get_field(
                            'user',
                            $DB->sql_fullname('firstname', 'lastname'),
                            ['id' => $queue->userid]
                        );
                        $returntext .= html_writer::tag('div', get_string(
                            'all_groups_full',
                            'grouptool',
                            $username
                        ), ['class' => 'error']);
                        return [$error, $returntext];
                    } else {
                        $tmpuseindividual = !empty($curgroup->grpsize);
                        $curgroup->grpsize = $tmpuseindividual ? $curgroup->grpsize : $grouptool->grpsize;
                        unset($tmpuseindividual);
                    }
                }

                if (!isset($planned->{$queue->userid})) {
                    $planned->{$queue->userid} = [];
                }

                // If user has got too many regs already!
                if (!empty($userregs[$queue->userid]) && ($userregs[$queue->userid] >= $maxregs)) {
                    $returntext .= html_writer::tag(
                        'div',
                        get_string('too_many_regs', 'grouptool'),
                        ['class' => 'error']
                    );
                    $error = true;
                    // Continue with next user/queue-entry!
                    continue;
                }

                while (
                    $DB->record_exists('grouptool_registered', [
                        'agrpid' => $curgroup->id,
                        'userid' => $queue->userid,
                    ])
                    || in_array($curgroup->id, $planned->{$queue->userid})
                    || $curgroup->registered >= $curgroup->grpsize
                ) {
                    $curgroup = next($groupsdata);
                    $i++;
                    if ($curgroup === false) {
                        break; // No group left for this user!
                    }
                }

                if ($curgroup !== false) {
                    // Register him or mark as planed!
                    if ($previewonly) {
                        // Move user and get feedback!
                        $curerror = 0;
                        try {
                            if ($queue->userid != $USER->id) {
                                $userdata = $DB->get_record('user', ['id' => $queue->userid]);
                                $message->username = fullname($userdata);
                            } else {
                                $message->username = fullname($USER);
                            }
                            $message->groupname = groups_get_group_name($curgroup->groupid);

                            $curtext = $permissionmanager->can_change_group($curgroup->id, $queue->userid, $message, $queue->agrpid, false);
                        } catch (registration $e) {
                            $curerror = 1;
                            $curtext = $e->getMessage();
                        }
                        if (!$curerror) {
                            $planned->{$queue->userid}[] = $curgroup->id;
                        }
                        $class = $curerror ? 'error' : 'success';
                        $data = new stdClass();
                        $data->userid = $queue->userid;
                        $data->user = $fullnames[$queue->userid];
                        $data->agrpid = $queue->agrpid;
                        $data->to_group = groups_get_group_name($curgroup->groupid);
                        $data->from_group = groups_get_group_name($groupsdata[$queue->agrpid]->groupid);
                        $data->current_text = $curtext;
                        $movetext = get_string('user_move_prev', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movetext, ['class' => $class]);
                        if (!isset($status[$queue->userid])) {
                            $status[$queue->userid] = new stdClass();
                        }
                        $status[$queue->userid]->error = $curerror;
                        $error = $error || $curerror;
                        $curgroup->registered++;
                    } else {
                        // Move user and get feedback!
                        $curerror = 0;
                        try {
                            $curtext = $registrationmanager->change_group($curgroup->id, $queue->userid, null, $queue->agrpid);
                        } catch (registration $e) {
                            $curerror = 1;
                            $curtext = $e->getMessage();
                        }
                        $class = $curerror ? 'error' : 'success';
                        $data = new stdClass();
                        $data->userid = $queue->userid;
                        $data->user = $fullnames[$queue->userid];
                        $data->agrpid = $queue->agrpid;
                        $data->to_group = groups_get_group_name($curgroup->groupid);
                        $data->from_group = groups_get_group_name($groupsdata[$queue->agrpid]->groupid);
                        $data->current_text = $curtext;
                        $movedtext = get_string('user_moved', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movedtext, ['class' => $class]);
                        $curgroup->registered++;
                        $error = $error || $curerror;
                        $attr = [
                            'id' => $queue->id,
                            'userid' => $queue->userid,
                            'agrpid' => $queue->agrpid,
                        ];
                        // Delete queue entry if successfull or print message!
                        $DB->delete_records('grouptool_queued', $attr);

                        // Log user moved!
                        $queue->groupid = $DB->get_field(
                            'grouptool_agrps',
                            'groupid',
                            ['id' => $queue->agrpid],
                            MUST_EXIST
                        );
                        $to = new stdClass();
                        $to->agrpid = $curgroup->id;
                        $to->userid = $queue->userid;
                        $to->groupid = $DB->get_field(
                            'grouptool_agrps',
                            'groupid',
                            ['id' => $curgroup->id],
                            MUST_EXIST
                        );
                        $to->id = $DB->get_field('grouptool_registered', 'id', [
                            'agrpid' => $to->agrpid,
                            'userid' => $to->userid,
                        ], MUST_EXIST);
                        user_moved::move($this->cm, $queue, $to)->trigger();

                        // Send message
                        $to->groupname = format_string(groups_get_group_name($to->agrpid));
                        $context = (object)[
                            'course' => $this->course,
                            'courseurl' => $CFG->wwwroot . "/course/view.php?id=" . $this->course->id,
                            'coursegrouptoolsurl' => $CFG->wwwroot . "/mod/grouptool/index.php?id=" . $this->course->id,
                            'grouptoolurl' => $CFG->wwwroot . "/mod/grouptool/view.php?id=" . $this->cm->id,
                            'grouptoolname' => format_string($this->grouptool->name, true),
                            'groupname' => $to->groupname,
                            'message' => get_string('register_you_in_group_success', 'grouptool', (object)[
                                'groupname' => $to->groupname,
                            ]),
                        ];

                        $postsubject = $this->course->shortname . ': ' .
                            get_string('modulenameplural', 'grouptool') . ': ' .
                            format_string($this->grouptool->name, true);
                        $messageuser = $DB->get_record('user', ['id' => $to->userid]);
                        $moodlemessage = new message();
                        $userfrom = core_user::get_noreply_user();
                        $moodlemessage->component = 'mod_grouptool';
                        $moodlemessage->name = 'grouptool_moveupreg';
                        $moodlemessage->courseid = $this->course->id;
                        $moodlemessage->userfrom = $userfrom;
                        $moodlemessage->userto = $messageuser;
                        $moodlemessage->subject = $postsubject;
                        $moodlemessage->fullmessage = get_string(
                            'registrationnotification',
                            'mod_grouptool',
                            $context
                        );
                        $moodlemessage->fullmessageformat = FORMAT_HTML;
                        $moodlemessage->fullmessagehtml =
                            $OUTPUT->render_from_template('mod_grouptool/registrationnotification', $context);
                        $moodlemessage->smallmessage = get_string(
                            'registrationnotification',
                            'mod_grouptool',
                            $context
                        );
                        $moodlemessage->notification = 1;
                        $moodlemessage->contexturl = $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $this->cm->id;
                        $moodlemessage->contexturlname = $this->grouptool->name;

                        message_send($moodlemessage);

                        if ($DB->record_exists('grouptool_queued', $attr)) {
                            $returntext .= "Could not delete!";
                        }
                    }
                }

                while ($i !== 0) {
                    $curgroup = prev($groupsdata);
                    $i--;
                }
            }
        }

        if (empty($returntext)) {
            $returntext = get_string('no_queues_to_resolve', 'grouptool');
            $error = false;
        }

        return [$error, $returntext];
    }

    /**
     * helperfunction compares to objects using a particular timestamp-property
     *
     * @param stdClass $a object containing timestamp property
     * @param stdClass $b object containing timestamp property
     * @return int 0 if equal, +1 if $a->timestamp > $b->timestamp or -1 if otherwise
     */
    public function cmptimestamp($a, $b) {
        if ($a->timestamp == $b->timestamp) {
            return 0;
        } else {
            return $a->timestamp > $b->timestamp ? 1 : -1;
        }
    }
}
