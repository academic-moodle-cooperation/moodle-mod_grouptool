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
 * Utility class for mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\local;

use completion_info;
use core\context;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\html_writer;
use core\output\notification;
use core\output\single_button;
use core_php_time_limit;
use core_table\output\html_table_cell;
use core_table\output\html_table_row;
use core_user\fields;
use dml_exception;
use Exception;
use html_table;
use mod_grouptool\event\agrp_created;
use mod_grouptool\event\user_imported;
use mod_grouptool\exception\exceedgroupsize;
use mod_grouptool\exception\exceeduserqueuelimit;
use mod_grouptool\exception\exceeduserreglimit;
use mod_grouptool\exception\registration;
use mod_grouptool\exception\regpresent;
use mod_grouptool\local\model\group_manager;
use mod_grouptool\local\model\permission_manager;
use mod_grouptool\local\model\queue_manager;
use moodle_url;
use progress_bar;
use required_capability_exception;
use single_select;
use stdClass;
use Throwable;

/**
 * Class grouptool_utils
 *
 * @package mod_grouptool
 */
class grouptool_utils extends grouptool_instance {
    /**
     * Print a message along with button choices for Continue/Cancel
     *
     * If a string or moodle_url is given instead of a single_button, method defaults to post.
     * If cancel=null only continue button is displayed!
     *
     * @param string $message The question to ask the user
     * @param moodle_url|single_button|string $continue The single_button component representing the
     *                                                  Continue answer. Can also be a moodle_url
     *                                                  or string URL
     * @param moodle_url|single_button|string|null $cancel The single_button component representing the
     *                                                  Cancel answer. Can also be a moodle_url or
     *                                                  string URL
     * @return string HTML fragment
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function confirm(
        string $message,
        moodle_url|single_button|string $continue,
        moodle_url|single_button|string|null $cancel = null
    ): string {
        global $OUTPUT;
        if (!($continue instanceof single_button)) {
            if (is_string($continue)) {
                $url = new moodle_url($continue);
                $continue = new single_button($url, get_string('continue'), 'post', 'primary');
            } else if ($continue instanceof moodle_url) {
                $continue = new single_button($continue, get_string('continue'), 'post', 'primary');
            } else {
                throw new coding_exception('The continue param to grouptool::confirm() must be either a' .
                    ' URL (string/moodle_url) or a single_button instance.');
            }
        }

        if (!($cancel instanceof single_button)) {
            if (is_string($cancel)) {
                $cancel = new single_button(new moodle_url($cancel), get_string('cancel'), 'get');
            } else if ($cancel instanceof moodle_url) {
                $cancel = new single_button($cancel, get_string('cancel'), 'get');
            } else if ($cancel == null) {
                $cancel = null;
            } else {
                throw new coding_exception('The cancel param to grouptool::confirm() must be either a' .
                    ' URL (string/moodle_url), single_button instance or null.');
            }
        }
        $data = [
            'message' => $message,
            'continuebutton' => $OUTPUT->render($continue),
            'cancelbutton' => $cancel ? $OUTPUT->render($cancel) : null,
        ];

        return $OUTPUT->render_from_template('mod_grouptool/confirm', $data);
    }

    /**
     * Requires the JS libraries for the message group button.
     *
     * @return void
     */
    public static function messagegroup_requirejs(): void {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }
        $PAGE->requires->js_call_amd(
            'mod_grouptool/message_group_button',
            'send',
            ['#group-message-button']
        );
        $done = true;
    }

    /**
     * Helper function used to print empty cells for hidden columns
     * @return void
     */
    public static function print_empty_cell(): void {
        echo html_writer::tag('td', '', ['class' => '']);
    }

    /**
     * Get showuseridentity itentifiers and their display text on the current instance
     *
     * @return array Identifiers in showuseridentity and their display names
     * @throws coding_exception
     */
    public static function get_useridentity_fields(): array {
        global $CFG;
        $useridentityfields = explode(',', $CFG->showuseridentity);

        // Set default values to idnumber and email in no showuseridentity setting is given.
        if (empty($useridentityfields)) {
            $useridentityfields = ['idnumber', 'email'];
        }

        $useridentity = [];
        foreach ($useridentityfields as $identifier) {
            $useridentity[$identifier] = fields::get_display_name($identifier);
        }
        return $useridentity;
    }

    /**
     * Helper function to convert a given associative array into
     * a nested index array so it can be iterated thorough by mustache.
     *
     * @param array $inarray Associative array that should be converted ($key => $value)
     * @return array Nested array in the format [['key' => $key, 'value' => $value]]
     */
    public static function convert_associative_array_into_nested_index_array(array $inarray): array {
        $outarray = [];
        foreach ($inarray as $key => $value) {
            $outarray[] = ['key' => $key, 'value' => $value];
        }
        return $outarray;
    }

    /**
     * Returns a ready to print string containing all given useridentity values separated by tabstops
     *
     * @param array $values array Values that should be separated
     * @return string
     */
    public static function get_useridentity_values_for_txt(array $values): string {
        $outstring = '';
        foreach ($values as $value) {
            $outstring .= "\t" . $value['value'];
        }
        return $outstring;
    }

    /**
     * Returns a single select to change currently selected page-orientation.
     *
     * @param moodle_url $url Base URL to use
     * @param int $orientation Currently active orientation
     * @return single_select
     * @throws coding_exception
     */
    public static function get_orientation_select(moodle_url $url, int $orientation): single_select {
        static $options = null;

        if (!$options) {
            $options = [
                0 => get_string('portrait', 'grouptool'),
                1 => get_string('landscape', 'grouptool'),
            ];
        }

        return new single_select($url, 'orientation', $options, $orientation, false);
    }

    /**
     * Returns nice download links for all formats based on downloadurl and groupid
     *
     * @param moodle_url $downloadurl The base download URL to use
     * @param int $groupid (optional) ID of group to use for the download or 0 for all groups download
     * @param context|null $context (optional) The context to check for export capability
     * @return string HTML snippet with download links encapsulated in DIV
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function get_download_links(moodle_url $downloadurl, int $groupid = 0, ?context $context = null): string {
        if (has_capability('mod/grouptool:export', $context)) {
            $class = 'download';
            if ($groupid) {
                $downloadurl = new moodle_url($downloadurl, ['groupid' => $groupid]);
                $downloadtxt = get_string('download');
            } else {
                $downloadtxt = get_string('downloadall');
                $class .= ' all';
            }
            $txturl = new moodle_url($downloadurl, ['format' => GROUPTOOL_TXT]);
            $xlsxurl = new moodle_url($downloadurl, ['format' => GROUPTOOL_XLSX]);
            $pdfurl = new moodle_url($downloadurl, ['format' => GROUPTOOL_PDF]);
            $odsurl = new moodle_url($downloadurl, ['format' => GROUPTOOL_ODS]);
            $downloadlinks = html_writer::tag('span', $downloadtxt . ":", ['class' => 'title']) . '&nbsp;' .
                html_writer::link($txturl, '.TXT') . '&nbsp;' .
                html_writer::link($xlsxurl, '.XLSX') . '&nbsp;' .
                html_writer::link($pdfurl, '.PDF') . '&nbsp;' .
                html_writer::link($odsurl, '.ODS');
            return html_writer::tag('div', $downloadlinks, ['class' => $class]);
        } else {
            return '';
        }
    }

    /**
     * Allocates a place in the group. Used in case there are not enough registrations by now.
     *
     * @param int $agrpid ID of active group to mark registration for.
     * @param int $userid (optional) ID of user to mark registration for or null ($USER->id is used).
     * @param stdClass $message (optional) prepared message object containing username and groupname or null.
     * @return string success message
     * @throws exceeduserreglimit
     * @throws registration
     * @throws regpresent
     * @throws exceedgroupsize
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function mark_for_reg($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $groupdata = $groupmanager->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new registration('error_getting_data');
        }

        $permissionmanager->can_be_marked($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->modified_by = -1;
        $DB->insert_record('grouptool_registered', $record);
        if ($userid != $USER->id) {
            return get_string('place_allocated_in_group_success', 'grouptool', $message);
        } else {
            return get_string('your_place_allocated_in_group_success', 'grouptool', $message);
        }
    }

    /**
     * Silently converts all of user's marks to registrations and queue entries or throws exception!
     *
     * @param int $userid (optional) ID of user to mark registration for or null ($USER->id is used).
     * @throws exceeduserqueuelimit
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function convert_marks_to_regs(int $userid): void {
        global $DB, $USER;

        // Get user's marks!
        $usermarks = $this->get_user_marks($userid);

        $queues = 0;
        foreach ($usermarks as $cur) {
            if ($cur->type != 'reg') {
                $queues++;
            }
        }
        if (!empty($this->grouptool->usersqueueslimit) && ($queues > $this->grouptool->usersqueueslimit)) {
            throw new exceeduserqueuelimit();
        }

        foreach ($usermarks as $cur) {
            if ($cur->type == 'reg') {
                unset($cur->type);
                $cur->modified_by = $USER->id;
                $DB->update_record('grouptool_registered', $cur);
                if ($this->grouptool->immediatereg) {
                    groups_add_member($cur->groupid, $userid);
                }
            } else {
                unset($cur->type);
                $DB->insert_record('grouptool_queued', $cur);
                $DB->delete_records('grouptool_registered', ['id' => $cur->id]);
            }
        }
        $this->delete_user_marks($userid);
    }

    /**
     * Return all marks for the specified user
     *
     * The marks are the registration entries before they become active
     * (i.e. if not enough groups have been chosen).
     *
     * @param int $userid (optional) User-ID for which the marks should be returned
     * @return array|null Users marks
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws dml_exception
     */
    public function get_user_marks(int $userid = 0): ?array {
        global $DB, $USER, $OUTPUT;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $agrps = $DB->get_fieldset_select(
            'grouptool_agrps',
            'id',
            'grouptoolid = ?',
            [$this->cm->instance]
        );
        if (empty($agrps)) {
            return null;
        }
        [$agrpssql, $params] = $DB->get_in_or_equal($agrps);
        $params[] = $userid;

        $sql = 'SELECT reg.id, reg.agrpid, reg.userid, reg.timestamp,
                       agrp.groupid
                  FROM {grouptool_registered} reg
                  JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id
                 WHERE reg.agrpid ' . $agrpssql . '
                   AND modified_by = -1
                   AND userid = ?';

        $marks = $DB->get_records_sql($sql, $params);
        foreach ($marks as $id => $cur) {
            $groupdata = $groupmanager->get_active_groups(true, true, $cur->agrpid);
            $groupdata = current($groupdata);

            if ($this->grouptool->usesize) {
                $notfull = empty($this->grouptool->groupsqueueslimit)
                    || (count($groupdata->queued) < $this->grouptool->groupsqueueslimit);
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    $cur->type = 'reg';
                } else if ($this->grouptool->usequeue && $notfull) {
                    $cur->type = 'queue';
                } else {
                    // Place occupied in the meanwhile, must look for another group!
                    $info = new stdClass();
                    $info->grpname = groups_get_group_name($cur->groupid);
                    $info->userid = $userid;
                    echo $OUTPUT->notification(
                        get_string('already_occupied', 'grouptool', $info),
                        notification::NOTIFY_ERROR
                    );
                    $DB->delete_records('grouptool_registered', ['id' => $id]);
                    unset($marks[$id]);
                }
            } else {
                $marks[$id]->type = 'reg';
            }
        }

        return $marks;
    }

    /**
     * Delete users marks
     *
     * @param int $userid (optional) User for whom the marks should be deleted
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function delete_user_marks($userid = 0) {
        global $DB;

        $marks = $this->get_user_marks($userid);
        if (is_array($marks) && count($marks) > 0) {
            [$select, $params] = $DB->get_in_or_equal(array_keys($marks));
            $select = 'id ' . $select;
            $DB->delete_records_select('grouptool_registered', $select, $params);
        }
    }

    /**
     * Count users marks
     *
     * @param int $userid (optional) User for whom the marks should be counted
     * @return int amount of users marks
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function count_user_marks($userid = 0) {
        $marks = $this->get_user_marks($userid);
        if (empty($marks)) {
            return 0;
        }
        return count($marks);
    }

    /**
     * Return if a group is already marked by a user
     *
     * @param int $agrpid activegroup id which should be checked
     * @param int $userid (optional) User for whom the group should be checked
     * @return bool true if marked
     * @throws dml_exception
     */
    public function grpmarked($agrpid, $userid = 0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        return $DB->record_exists(
            'grouptool_registered',
            [
                'agrpid' => $agrpid,
                'userid' => $userid,
                'modified_by' => -1,
            ]
        );
    }

    /**
     * import users into a certain moodle-group and enrole them if not allready enroled
     *
     * @param int[] $groups array of ids of groups to import into
     * @param stdClass|string $data from form in import tab (textfield with idnumbers and group-selection)
     * @param int[] $ignored which user ids to ignore when importing (used if conflicting users should be ignored)
     * @param bool $forceregistration Force registration in grouptool
     * @param bool $previewonly optional preview only, don't take any action
     * @return array ($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     */
    public function import(array $groups, stdClass|string $data, array $ignored = [], $forceregistration = false, $previewonly = false): array {
        global $DB, $OUTPUT, $USER;

        $message = "";
        $error = false;
        $users = preg_split("/[ ,;\t\n\r]+/", $data);
        // Prevent selection of all users if one of the above defined characters are in the beginning!
        foreach ($users as $key => $user) {
            if (empty($user)) {
                unset($users[$key]);
            }
        }
        $groupinfo = [];
        foreach ($groups as $group) {
            $groupinfo[$group] = groups_get_group($group);
        }
        $imported = [];
        $agrp = [];
        foreach ($groups as $group) {
            $agrp[$group] = $DB->get_field('grouptool_agrps', 'id', [
                'grouptoolid' => $this->grouptool->id,
                'groupid' => $group,
            ], IGNORE_MISSING);
            if (
                !$DB->record_exists('grouptool_agrps', [
                    'grouptoolid' => $this->grouptool->id,
                    'groupid' => $group,
                    'active' => 1,
                ])
            ) {
                $message .= $OUTPUT->notification(get_string(
                    'import_in_inactive_group_warning',
                    'grouptool',
                    $groupinfo[$group]->name
                ), notification::NOTIFY_ERROR);
            }
            // We use MAX to trick Postgres into thinking this is a full GROUP BY statement!
            $sql = '     SELECT agrps.id AS id, MAX(agrps.groupid) AS grpid, COUNT(regs.id) AS regs,
                                MAX(grptl.grpsize) AS globalsize, MAX(agrps.grpsize) AS size,
                                MAX(grptl.name) AS instancename
                           FROM {grouptool_agrps} agrps
                           JOIN {grouptool} grptl ON agrps.grouptoolid = grptl.id
                      LEFT JOIN {grouptool_registered} regs ON agrps.id = regs.agrpid AND regs.modified_by >= 0
                          WHERE agrps.groupid = :grpid
                            AND grptl.use_size = 1
                            AND agrps.active = 1
                       GROUP BY agrps.id
                       ';
            $agrps = $DB->get_records_sql($sql, ['grpid' => $group]);
            $usercnt = count($users);
            foreach ($agrps as $cur) {
                if (!empty($cur->size)) {
                    if (($cur->regs + $usercnt) > $cur->size && $previewonly) {
                        $message .= html_writer::tag('div', $OUTPUT->notification(get_string(
                            'overflowwarning',
                            'grouptool',
                            $cur
                        ), notification::NOTIFY_ERROR));
                    }
                } else {
                    if (($cur->regs + $usercnt) > $cur->globalsize && $previewonly) {
                        $message .= html_writer::tag('div', $OUTPUT->notification(get_string(
                            'overflowwarning',
                            'grouptool',
                            $cur
                        ), notification::NOTIFY_ERROR));
                    }
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
        $prevtable->id = 'importpreview';
        $prevtable->head = [get_string('fullname')];
        foreach ($importfields as $field) {
            $prevtable->head[] = get_string($field);
        }
        $prevtable->head[] = get_string('status');
        $prevtable->data = [];

        $pbar = new progress_bar('checkmarkimportprogress', 500, true);
        $count = count($users);
        $processed = 0;
        $pbar->update($processed, $count, get_string('import_progress_start', 'grouptool'));
        core_php_time_limit::raise(count($users) * 5);
        raise_memory_limit(MEMORY_HUGE);
        foreach ($users as $user) {
            $pbar->update($processed, $count, get_string('import_progress_search', 'grouptool') . ' ' . $user);
            $userinfo = $this->find_userinfo($importfields, $user);
            $row = new html_table_row();
            $errorrows = $this->check_userinfo($userinfo, $user, $importfields);
            if (!empty($errorrows)) {
                foreach ($errorrows as $r) {
                    $prevtable->data[] = $r;
                }
                $error = true;
            } else {
                $userinfo = reset($userinfo);
                $row->cells = [new html_table_cell(fullname($userinfo))];
                foreach ($importfields as $curfield) {
                    $row->cells[] = new html_table_cell(empty($userinfo->$curfield) ? '' : $userinfo->$curfield);
                }
                if (!is_enrolled($this->context, $userinfo->id) && !$previewonly) {
                    // We have to catch deleted users now, give a message and continue!
                    if (!empty($userinfo->deleted)) {
                        $userinfo->fullname = fullname($userinfo);
                        $text = get_string('user_is_deleted', 'grouptool', $userinfo);
                        $row->cells[] = new html_table_cell($OUTPUT->notification($text, 'error'));
                        $error = true;
                        continue;
                    }
                    /*
                     * if user's not enrolled already we force manual enrollment in course,
                     * so we can add the user to the group
                     */
                    try {
                        $this->force_enrol_student($userinfo->id);
                    } catch (Exception $e) {
                        $row->cells[] = new html_table_cell($OUTPUT->notification(
                            $e->getMessage(),
                            notification::NOTIFY_ERROR
                        ));
                    } catch (Throwable $t) {
                        $row->cells[] = new html_table_cell($OUTPUT->notification(
                            $t->getMessage(),
                            notification::NOTIFY_ERROR
                        ));
                    }
                }
                foreach ($groups as $group) {
                    $data = [
                        'id' => $userinfo->id,
                        'idnumber' => $userinfo->idnumber,
                        'fullname' => fullname($userinfo),
                        'groupname' => $groupinfo[$group]->name,
                    ];
                    if (!$previewonly && $userinfo) {
                        $pbar->update($processed, $count, get_string(
                            'import_progress_import',
                            'grouptool'
                        ) . ' ' . fullname($userinfo) . '...');

                        if (in_array($userinfo->id, $ignored[$group])) {
                            // We ignore the user for this import in this group!
                            $cell = new html_table_cell(get_string('import_skipped', 'grouptool', $data));
                            $cell->attributes['class'] = 'info';
                            $row->cells[] = $cell;
                            continue;
                        }

                        if (!groups_add_member($group, $userinfo->id)) {
                            $error = true;
                            $notification = $OUTPUT->notification(get_string(
                                'import_user_problem',
                                'grouptool',
                                $data
                            ), notification::NOTIFY_ERROR);
                            $row->cells[] = new html_table_cell($notification);
                            $row->attributes['class'] = 'error';
                        } else {
                            $imported[] = $userinfo->id;
                            $row->cells[] = get_string('import_user', 'grouptool', $data);
                            $row->attributes['class'] = 'success';
                        }
                        if ($forceregistration && empty($agrp[$group])) {
                            /* Registering in an non active Grouptool-group would cause problems
                             * with incorrectly labeled buttons under certain circumstances.
                             * We removed the automatic creation and registration in this newly inserted inactive group.
                             * In no case, there should be a missing agrp entry anyway.
                             */
                            $newgrpdata = $DB->get_record_sql(
                                'SELECT MAX(sort_order), MAX(grpsize)
                                                                 FROM {grouptool_agrps}
                                                               WHERE grouptoolid = ?',
                                [$this->grouptool->id]
                            );
                            // Insert agrp-entry for this group (even if it's not active)!
                            $agrp[$group] = new stdClass();
                            $agrp[$group]->grouptoolid = $this->grouptool->id;
                            $agrp[$group]->groupid = $group;
                            $agrp[$group]->active = 0;
                            $agrp[$group]->sort_order = $newgrpdata->sortorder + 1;
                            $agrp[$group]->grpsize = $newgrpdata->grpsize;
                            $agrp[$group]->id = $DB->insert_record('grouptool_agrps', $agrp[$group]);
                            agrp_created::create_from_object($this->cm, $agrp[$group])->trigger();
                            $notification = $OUTPUT->notification(get_string(
                                'import_in_inactive_group_rejected',
                                'grouptool',
                                $agrp[$group]
                            ), notification::NOTIFY_ERROR);
                            $row->cells[] = $notification;
                            $row->attributes['class'] = 'error';
                            $agrp[$group] = $agrp[$group]->id;
                        } else if (
                            $forceregistration && !empty($agrp[$group])
                            && !$DB->record_exists_select(
                                'grouptool_registered',
                                "modified_by >= 0 AND agrpid = :agrpid AND userid = :userid",
                                ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]
                            )
                        ) {
                            if (
                                $reg = $DB->get_record('grouptool_registered', [
                                    'agrpid' => $agrp[$group],
                                    'userid' => $userinfo->id,
                                    'modified_by' => -1,
                                ], IGNORE_MISSING)
                            ) {
                                // If user is marked, we register him right now!
                                $reg->modified_by = $USER->id;
                                $DB->update_record('grouptool_registered', $reg);
                                // Do we have to delete his marks and queues if theres enough registrations?
                            } else {
                                $reg = new stdClass();
                                $reg->agrpid = $agrp[$group];
                                $reg->userid = $userinfo->id;
                                $reg->timestamp = time();
                                $reg->modified_by = $USER->id;
                                // We don't need to log creation of registration, because we log import as whole!
                                $reg->id = $DB->insert_record('grouptool_registered', $reg);
                            }

                            // Delete every queue entry here!
                            $DB->delete_records('grouptool_queued', ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]);

                            user_imported::import_forced(
                                $this->cm,
                                $reg->id,
                                $agrp[$group],
                                $group,
                                $userinfo->id
                            )->trigger();
                        } else {
                            // Delete every queue entry here!
                            $DB->delete_records('grouptool_queued', ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]);

                            if (!$forceregistration) {
                                // Trigger the event!
                                user_imported::import($this->cm, $group, $userinfo->id)->trigger();
                            }
                        }
                    } else if ($userinfo) {
                        if (
                            $DB->record_exists_select(
                                'grouptool_queued',
                                "agrpid = :agrpid AND userid = :userid",
                                ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]
                            )
                        ) {
                            $options = [
                                -1 => get_string('move_user', 'grouptool'),
                                $userinfo->id => get_string('skip_user_import', 'grouptool'),
                            ];
                            $cell = get_string('import_conflict_user_queued', 'grouptool', $data) .
                                html_writer::tag(
                                    'div',
                                    html_writer::select($options, "ignored_{$group}[]", -1, false)
                                );
                            $row->cells[] = $cell;
                            $row->attributes['class'] = 'prevconflict';
                        } else {
                            $row->cells[] = get_string('import_user_prev', 'grouptool', $data);
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
            $pbar->update($processed, $count, get_string('import_progress_completed', 'grouptool'));
        } else {
            $pbar->update($processed, $count, get_string('import_progress_preview_completed', 'grouptool'));
        }
        $message .= html_writer::table($prevtable);
        // Update completion state if submission is changed.
        $completion = new completion_info($this->course);
        if ($completion->is_enabled($this->cm) && $this->grouptool->completionregister) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }
        return [$error, $message];
    }

    /**
     * Searches users based on the information given and the fields to consider
     * @param array $importfields the fields to check
     * @param array|string $user the data for thse fields
     * @return array the found user/s
     * @throws dml_exception
     */
    public function find_userinfo(array $importfields, array|string $user): array {
        global $DB;
        $userinfo = [];
        foreach ($importfields as $field) {
            $sql = 'SELECT * FROM {user} WHERE ' . $DB->sql_like($field, ':userpattern');
            $sql .= ' AND deleted = 0';
            $param = ['userpattern' => $user];

            $userinfo = $DB->get_records_sql($sql, $param);

            if (empty($userinfo)) {
                $param['userpattern'] = '%' . $user;
                $userinfo = $DB->get_records_sql($sql, $param);
            } else if (count($userinfo) == 1) {
                break;
            }

            if (empty($userinfo)) {
                $param['userpattern'] = $user . '%';
                $userinfo = $DB->get_records_sql($sql, $param);
            } else if (count($userinfo) == 1) {
                break;
            }

            if (empty($userinfo)) {
                $param['userpattern'] = '%' . $user . '%';
                $userinfo = $DB->get_records_sql($sql, $param);
            } else if (count($userinfo) == 1) {
                break;
            }

            if (!empty($userinfo) && count($userinfo) == 1) {
                break;
            }
        }
        return $userinfo;
    }

    /**
     * checks the found userdata, and return error rows if no user was found or multiple were fund
     * @param array $userinfo data that was found
     * @param array|string $user the data given by the user
     * @param array $importfields the fields which were checked
     * @return array rows for the table, possibly empty if exactly one user was found
     * @throws \coding_exception
     * @throws coding_exception
     */
    public function check_userinfo(array $userinfo, array|string $user, array $importfields): array {
        global $OUTPUT;

        $errorrows = [];
        if (empty($userinfo)) {
            $errorrows[0] = new html_table_row();
            $errorrows[0]->cells[] = new html_table_cell($OUTPUT->notification(
                get_string('user_not_found', 'grouptool', $user),
                notification::NOTIFY_ERROR
            ));
        } else if (count($userinfo) > 1) {
            foreach ($this->generate_multiple_users_table($userinfo, $importfields) as $tmprow) {
                $errorrows[] = $tmprow;
            }
        }
        return $errorrows;
    }

    /**
     * Generates the table with information about the users that were found multiple times
     * @param array $userinfo the users which were found
     * @param array $importfields the based on which those users were found
     * @return array table rows
     * @throws coding_exception
     */
    private function generate_multiple_users_table($userinfo, $importfields) {
        global $OUTPUT;
        $tmprows = [];
        foreach ($userinfo as $currentuser) {
            $tmprow = new html_table_row();
            $tmprow->cells = [];
            $tmprow->cells[] = new html_table_cell(fullname($currentuser));
            foreach ($importfields as $curfield) {
                $tmprow->cells[] = new html_table_cell($currentuser->$curfield);
            }
            $tmprows[] = $tmprow;
        }
        $curkey = count($tmprows[0]->cells);
        $tmprows[0]->cells[$curkey] = new html_table_cell($OUTPUT->notification(
            get_string(
                'found_multiple',
                'grouptool'
            ),
            notification::NOTIFY_ERROR
        ));
        $tmprows[0]->cells[$curkey]->rowspan = count($tmprows);
        return $tmprows;
    }

    /**
     * Force enrol a user in this course as student to be able to import into group or register for group!
     *
     * @param int $userid ID of user to force enrol!
     * @throws coding_exception Thrown if smthg very unexpected happened (couldn't instantiate manual enrol instance or similar)
     * @throws dml_exception
     */
    public function force_enrol_student($userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/manual/locallib.php');
        require_once($CFG->libdir . '/accesslib.php');

        if (!$enrolmanual = enrol_get_plugin('manual')) {
            throw new coding_exception(get_string('cant_enrol', 'grouptool'));
        }
        if (
            !$instance = $DB->get_record('enrol', [
                'courseid' => $this->course->id,
                'enrol' => 'manual',
            ], '*', IGNORE_MISSING)
        ) {
            if ($enrolmanual->add_default_instance($this->course)) {
                $instance = $DB->get_record('enrol', [
                    'courseid' => $this->course->id,
                    'enrol' => 'manual',
                ], '*', MUST_EXIST);
            }
        }
        if ($instance != false) {
            $archroles = get_archetype_roles('student');
            $archrole = array_shift($archroles);
            $enrolmanual->enrol_user($instance, $userid, $archrole->id, time());
        } else {
            throw new coding_exception(get_string('cant_enrol', 'grouptool'));
        }
    }

    /**
     * Add additional user fields and useridentity fields to the row (at least adds idnumber and email to be displayed).
     *
     * @param mixed[] $row Associative array with table data for this user
     * @param stdClass $user the user's DB record
     * @throws coding_exception
     */
    public function add_namefields_useridentity(array &$row, stdClass $user): void {
        global $CFG;
        $namefields = fields::for_name()->get_required_fields();
        foreach ($namefields as $namefield) {
            if (!empty($user->$namefield)) {
                $row[$namefield] = $user->$namefield;
            } else {
                $row[$namefield] = '';
            }
        }
        if (empty($CFG->showuseridentity)) {
            if (!empty($user->idnumber)) {
                $row['idnumber'] = $user->idnumber;
            } else {
                $row['idnumber'] = '-';
            }
            if (!empty($user->email)) {
                $row['email'] = $user->email;
            } else {
                $row['email'] = '-';
            }
        } else {
            $fields = explode(',', $CFG->showuseridentity);
            foreach ($fields as $field) {
                if (!empty($user->$field)) {
                    $row[$field] = $user->$field;
                } else {
                    $row[$field] = '';
                }
            }
        }
    }

    /**
     * Get additional user fields and useridentity fields to the row (at least adds idnumber and email to be displayed).
     *
     * @param mixed[] $row Associative array with table data for this user
     * @param stdClass $user the user's DB record
     * @return array
     */
    public function get_namefields_useridentity($row, $user) {
        global $CFG;
        $namefields = fields::for_name()->get_required_fields();
        foreach ($namefields as $namefield) {
            if (!empty($user->$namefield)) {
                $row[$namefield] = $user->$namefield;
            } else {
                $row[$namefield] = '';
            }
        }
        $useridentityvalues = [];
        if (empty($CFG->showuseridentity)) {
            if (!empty($user->idnumber)) {
                $useridentityvalues['idnumber'] = ['key' => 'idnumber', 'value' => $user->idnumber];
            } else {
                $useridentityvalues['idnumber'] = ['key' => 'idnumber', 'value' => '-'];
            }
            if (!empty($user->email)) {
                $useridentityvalues['email'] = ['key' => 'email', 'value' => $user->email];
            } else {
                $useridentityvalues['email'] = ['key' => 'email', 'value' => '-'];
            }
        } else {
            $fields = explode(',', $CFG->showuseridentity);
            foreach ($fields as $field) {
                if (!empty($user->$field)) {
                    $useridentityvalues[$field] = $user->$field;
                } else {
                    $useridentityvalues[$field] = '';
                }
            }
            return $useridentityvalues;
        }
    }

    /**
     * get object containing informatino about syncronisation of active-groups with moodle-groups
     *
     * @param int $grouptoolid optional get stats for this grouptool-instance
     *                                  uses $this->instance if zero
     * @return array (global out of sync, array of objects with sync-status for each group)
     * @throws dml_exception
     */
    public function get_sync_status($grouptoolid = 0) {
        global $DB;
        $outofsync = false;

        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }

        // We use MAX to trick postgres into thinking this is a full group_by statement!
        $sql = "SELECT agrps.id AS agrpid, MAX(agrps.groupid) AS groupid,
                       COUNT(DISTINCT reg.userid) AS grptoolregs,
                       COUNT(DISTINCT mreg.userid) AS mdlregs
                  FROM {grouptool_agrps} agrps
             LEFT JOIN {grouptool_registered} reg ON agrps.id = reg.agrpid AND reg.modified_by >= 0
             LEFT JOIN {groups_members} mreg ON agrps.groupid = mreg.groupid
                                             AND reg.userid = mreg.userid
                  WHERE agrps.active = 1 AND agrps.grouptoolid = ?
               GROUP BY agrps.id";
        $return = $DB->get_records_sql($sql, [$grouptoolid]);

        foreach ($return as $key => $group) {
            $return[$key]->status = ($group->grptoolregs > $group->mdlregs) ? GROUPTOOL_OUTDATED : GROUPTOOL_UPTODATE;
            $outofsync |= ($return[$key]->status == GROUPTOOL_OUTDATED);
        }
        return [$outofsync, $return];
    }

    /**
     * Render link for Member-List
     *
     * @param stdClass $group active group object, for which the members should be displayed
     * @return string HTML fragment
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_members_link($group) {
        global $CFG, $DB;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $output = get_string('show_members', 'grouptool');

        // Now create the link around it - we need https on loginhttps pages!
        $url = new moodle_url($CFG->httpswwwroot . '/mod/grouptool/showmembers.php', [
            'agrpid' => $group->agrpid,
            'contextid' => $this->context->id,
        ]);

        $attributes = ['href' => $url, 'title' => get_string('show_members', 'grouptool')];
        $id = html_writer::random_id('showmembers');
        $attributes['id'] = $id;
        $attributes['data-name'] = $group->name;
        // Add data attributes for JS!
        $registered = [];
        if (!empty($group->registered)) {
            foreach ($group->registered as $cur) {
                $registered[] = $cur->userid;
            }
        }
        $members = array_keys($group->moodle_members);
        $queued = [];
        if (!empty($group->queued)) {
            foreach ($group->queued as $cur) {
                $queued[$cur->userid] = $cur->userid;
            }
        }
        // Get all registered users with moodle-group-membership!
        $absregs = array_intersect($registered, $members);
        $absregs = array_combine($absregs, $absregs);
        // Get all registered users without moodle-group-membership!
        $gtregs = array_diff($registered, $members);
        $gtregs = array_combine($gtregs, $gtregs);
        // Get all moodle-group-members without registration!
        $mdlregs = array_diff($members, $registered);
        $mdlregs = array_combine($mdlregs, $mdlregs);

        $showidnumber = has_capability('mod/grouptool:view_regs_group_view', $this->context);
        $userfields = fields::for_name()->get_sql(
            "",
            false,
            '',
            '',
            false
        )->selects;
        if ($showidnumber) {
            $fields = "id,idnumber," . $userfields;
        } else {
            $fields = "id," . $userfields;
        }
        // Cache needed user records right now!
        $users = $DB->get_records_list('user', 'id', $gtregs + $queued, null, $fields);

        $attributes['data-absregs'] = [];
        if (!empty($absregs)) {
            foreach ($absregs as $cur) {
                // These user records are fully fetched in $group->moodle_members!
                $attributes['data-absregs'][] = [
                    'idnumber' => $showidnumber ? $group->moodle_members[$cur]->idnumber : '',
                    'fullname' => fullname($group->moodle_members[$cur]),
                    'id' => $cur,
                ];
            }
        }
        $attributes['data-absregs'] = json_encode($attributes['data-absregs']);

        $attributes['data-gtregs'] = [];
        if (!empty($gtregs)) {
            foreach ($gtregs as $cur) {
                $attributes['data-gtregs'][] = [
                    'idnumber' => $showidnumber ? $users[$cur]->idnumber : '',
                    'fullname' => fullname($users[$cur]),
                    'id' => $cur,
                ];
            }
        }
        $attributes['data-gtregs'] = json_encode($attributes['data-gtregs']);

        $attributes['data-mregs'] = [];
        if (!empty($mdlregs)) {
            foreach ($mdlregs as $cur) {
                $attributes['data-mregs'][] = [
                    'idnumber' => $showidnumber ? $group->moodle_members[$cur]->idnumber : '',
                    'fullname' => fullname($group->moodle_members[$cur]),
                    'id' => $cur,
                ];
            }
        }
        $attributes['data-mregs'] = json_encode($attributes['data-mregs']);

        $attributes['data-queued'] = [];
        if (!empty($queued)) {
            $queuedlist = $DB->get_records('grouptool_queued', ['agrpid' => $group->agrpid], 'timestamp ASC');
            foreach ($queued as $cur) {
                $attributes['data-queued'][] = [
                    'idnumber' => $showidnumber ? $users[$cur]->idnumber : '',
                    'fullname' => fullname($users[$cur]),
                    'id' => $cur,
                    'rank' => $queuemanager->get_rank_in_queue($queuedlist, $cur),
                ];
            }
        }
        $attributes['data-queued'] = json_encode($attributes['data-queued']);

        $output = html_writer::tag('a', $output, $attributes);

        // And finally wrap in a span!
        return html_writer::tag('span', $output, ['class' => 'showmembers memberstooltip']);
    }

    /**
     * returns the source of potential users and order mode
     *
     * @param object $data data of creation view
     * @return array $source array of possible sources for potential users
     * @return string $orderby sql clause for ordering the list of potential users
     * @throws moodle_exception
     */
    public function view_creation_get_source_orderby($data) {

        $source = [];
        if ($data->cohortid) {
            $source['cohortid'] = $data->cohortid;
        }
        if ($data->selectfromgrouping) {
            $source['groupingid'] = $data->selectfromgrouping;
        }
        if ($data->selectfromgroup) {
            $source['groupid'] = $data->selectfromgroup;
        }

        switch ($data->allocateby) {
            default:
                throw new moodle_exception('unknoworder');
            case 'no':
            case 'random':
            case 'lastname':
                $orderby = 'lastname, firstname, idnumber';
                break;
            case 'firstname':
                $orderby = 'firstname, lastname, idnumber';
                break;
            case 'idnumber':
                $orderby = 'idnumber, lastname, firstname';
                break;
        }

        return [$source, $orderby];
    }
}
