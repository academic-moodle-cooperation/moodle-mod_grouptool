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

use coding_exception;
use core\output\notification;
use core_php_time_limit;
use core_user\fields;
use dml_exception;
use help_icon;
use mod_grouptool\event\agrps_updated;
use mod_grouptool\event\group_creation_started;
use mod_grouptool\event\groupings_created;
use mod_grouptool\local\grouptool_instance;
use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use mod_grouptool\local\grouptool_utils;
use moodle_exception;
use moodle_url;
use required_capability_exception;
use single_select;
use stdClass;

class group_manager extends grouptool_instance {
    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @param stdClass|array  $data data from administration-form with all settings for group creation
     * @param stdClass[] $users which users to registrate in the created groups
     * @param int $userpergrp how many users should be registrated per group
     * @param int $numgrps how many groups should be created
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function create_groups(stdClass|array $data, array $users, int $userpergrp, int $numgrps, bool $previewonly = false): array {
        global $DB, $USER;

        require_capability('mod/grouptool:administrate_groups', $this->context);

        $namestouse = [];

        // Allocate members from the selected role to groups!
        $usercnt = count($users);
        if ($data->allocateby == 'random') {
            srand($data->seed);
            shuffle($users);
        }

        $groups = [];

        // Number of groups with userpergrp+1 for properly allocating the rest without messing up the sort order.
        if ($numgrps <= 0) {
            $plusonegroupcount = 0;
        } else {
            $plusonegroupcount = ($usercnt / $numgrps) > $userpergrp ? $usercnt % $numgrps : 0;
        }

        // Allocate the users - all groups equal count first!
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i] = [];
            $groups[$i]['members'] = [];
            if ($data->allocateby == 'no') {
                continue; // Do not allocate users!
            }
            // Adds one member more if group is in the pluse one range.
            $plusonegroup = $i < $plusonegroupcount ? 1 : 0;
            for ($j = 0; $j < ($userpergrp + $plusonegroup); $j++) {
                if (empty($users)) {
                    break 2;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Throw an error if there are still users left who have not been allocated.
        if ($data->allocateby != 'no' && !empty($users)) {
            throw new coding_exception('User to group accocation did not work properly. There are still remaining users');
        }
        // Every member is there, so we can parse the name!
        $digitslog = log10($numgrps);
        // Add another digit if result of log is an integer (it means that no of groups was 10,10,100,...).
        $digits = fmod($digitslog, 1.) === 0 ? $digitslog + 1 : ceil($digitslog);
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i]['name'] = $this->groups_parse_name(
                trim($data->namingscheme),
                $i,
                $groups[$i]['members'],
                $digits
            );
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            if ($data->allocateby == 'no') {
                $table->head = [get_string('groupscount', 'group', $numgrps)];
                $table->size = ['100%'];
                $table->align = ['left'];
            } else {
                $table->head = [
                    get_string('groupscount', 'group', $numgrps),
                    get_string('groupmembers', 'group'),
                    get_string('usercounttotal', 'group', $usercnt),
                ];
                $table->size = ['20%', '70%', '10%'];
                $table->align = ['left', 'left', 'center'];
            }
            $table->data = [];

            foreach ($groups as $group) {
                $line = [];
                if (groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $namestouse)) {
                    $error = true;
                    if (in_array($group['name'], $namestouse)) {
                        $line[] = '<span class="late">' .
                            get_string('nameschemenotunique', 'grouptool', $group['name']) . '</span>';
                    } else {
                        $line[] = '<span class="late">' .
                            get_string('groupnameexists', 'group', $group['name']) . '</span>';
                    }
                } else {
                    $line[] = $group['name'];
                    $namestouse[] = $group['name'];
                }
                if ($data->allocateby != 'no') {
                    $unames = [];
                    foreach ($group['members'] as $user) {
                        $unames[] = fullname($user);
                    }
                    $line[] = implode(', ', $unames);
                    $line[] = count($group['members']);
                }
                $table->data[] = $line;
            }
            return [0 => $error, 1 => html_writer::table($table)];
        } else {
            if ($numgrps <= 0) {
                $preview = get_string('nogroupscreated', 'grouptool');
                return [true, $preview];
            }
            $grouping = null;
            $createdgrouping = 0;
            $createdgroups = [];
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    group_creation_started::create_groupamount(
                        $this->cm,
                        $data->namingscheme,
                        $data->numberofgroups,
                        $groupingid
                    )->trigger();
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    group_creation_started::create_memberamount(
                        $this->cm,
                        $data->namingscheme,
                        $data->numberofmembers,
                        $groupingid
                    )->trigger();
                    break;
            }

            // Save the groups data!
            foreach ($groups as $group) {
                if (groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    continue;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name = $group['name'];
                $newgroup->enablemessaging = $data->enablegroupmessaging == 1 ? 1 : null;
                $groupid = groups_create_group($newgroup);
                $newagrp = $this->add_agrp_entry($groupid);
                $createdgroups[] = $groupid;
                foreach ($group['members'] as $user) {
                    groups_add_member($groupid, $user->id);
                    $usrreg = new stdClass();
                    $usrreg->userid = $user->id;
                    $usrreg->agrpid = $newagrp->id;
                    $usrreg->timestamp = time();
                    $usrreg->modified_by = $USER->id;
                    $attr = [
                        'userid' => $user->id,
                        'agrpid' => $newagrp->id,
                    ];
                    if (!$DB->record_exists('grouptool_registered', $attr)) {
                        $DB->insert_record('grouptool_registered', $usrreg);
                    } else {
                        $DB->set_field('grouptool_registered', 'modified_by', $USER->id, $attr);
                    }
                }
                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                agrps_updated::create_groupcreation(
                    $this->cm,
                    $data->namingscheme,
                    $numgrps,
                    $groupingid
                )->trigger();
            }
        }
        if (empty($failed)) {
            $preview = get_string('groups_created', 'grouptool');
        } else if (empty($preview)) {
            if (!empty($error)) {
                $preview = $error;
            } else {
                $preview = get_string('group_creation_failed', 'grouptool');
            }
        }

        return [$failed, $preview];
    }
    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance also used for creation of N groups with M members!
     *
     * @param stdClass|array $data data from administration-form with all settings for group creation
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function create_fromto_groups(stdClass|array $data, bool $previewonly = false): array {
        global $DB, $OUTPUT;

        require_capability('mod/grouptool:administrate_groups', $this->context);

        $groups = [];

        // Every member is there, so we can parse the name!
        for ($i = clean_param($data->from, PARAM_INT); $i <= clean_param($data->to, PARAM_INT); $i++) {
            $groups[] = $this->groups_parse_name(
                trim($data->namingscheme),
                $i - 1,
                null,
                clean_param($data->digits, PARAM_INT)
            );
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head = [
                get_string(
                    'groupscount',
                    'group',
                    (clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1)
                ),
            ];
            $table->size = ['100%'];
            $table->align = ['left'];

            $table->data = [];
            $createdgroups = [];
            foreach ($groups as $group) {
                $line = [];
                if (groups_get_group_by_name($this->course->id, $group) || in_array($group, $createdgroups)) {
                    $error = true;
                    if (in_array($group, $createdgroups)) {
                        $line[] = '<span class="late">' .
                            get_string('nameschemenotunique', 'grouptool', $group) . '</span>';
                    } else {
                        $line[] = '<span class="late">' .
                            get_string('groupnameexists', 'group', $group) . '</span>';
                    }
                } else {
                    $line[] = $group;
                    $createdgroups[] = $group;
                }

                $table->data[] = $line;
            }

            // Notification if activation of group size is imminent!
            if (empty($error) && !empty($data->numberofmembers)) {
                $a = new stdClass();
                $a->field = get_string('number_of_members', 'grouptool');
                $a->globalsize = $this->grouptool->grpsize;
                if ($data->numberofmembers != $this->grouptool->grpsize && !$this->grouptool->use_size) {
                    echo $OUTPUT->notification(get_string('groupsize_gets_enabled', 'grouptool', $a), 'info');
                }
            }

            return [0 => $error, 1 => html_writer::table($table)];
        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = [];
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group creation started event.
            $groupingid = !empty($grouping->id) ? $grouping->id : 0;
            group_creation_started::create_fromto(
                $this->cm,
                $data->namingscheme,
                $data->from,
                $data->to,
                $groupingid
            )->trigger();

            // Save the groups data!
            $error = '';
            foreach ($groups as $group) {
                if (groups_get_group_by_name($this->course->id, $group)) {
                    $error = get_string('groupnameexists', 'group', $group);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name = $group;
                $newgroup->enablemessaging = $data->enablegroupmessaging == 1 ? 1 : null;
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $newagrp = $this->add_agrp_entry($groupid);
                if (!empty($data->numberofmembers) && ($data->numberofmembers != $this->grouptool->grpsize)) {
                    $DB->set_field('grouptool_agrps', 'grpsize', $data->numberofmembers, ['id' => $newagrp->id]);
                }
                $createdgroups[] = $groupid;
                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
                return [
                    0 => true,
                    1 => get_string('group_creation_failed', 'grouptool') . html_writer::empty_tag('br') . $error,
                ];
            } else {
                // Activate group size if we already used it when creating groups!
                if (!empty($data->numberofmembers)) {
                    $this->grouptool->use_size = true;
                    $DB->update_record('grouptool', $this->grouptool);
                }

                $numgrps = clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1;
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                agrps_updated::create_groupcreation(
                    $this->cm,
                    $data->namingscheme,
                    $numgrps,
                    $groupingid
                )->trigger();
                return [0 => false, 1 => get_string('groups_created', 'grouptool')];
            }
        }
    }


    /**
     * Create a moodle group for each of the users in $users
     *
     * @param stdClass[] $users array of users-objects for which to create the groups
     * @param string $namescheme scheme determining how to name the created groups
     * @param int $grouping -1 => create new grouping,
     *                       0 => no grouping,
     *                      >0 => assign groups to grouping with that id
     * @param string|null $groupingname optional name for created grouping
     * @param bool $previewonly optional only show preview of created groups
     * @param int $enablegroupmessaging optional enable messaging within group (default: no)
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function create_one_person_groups(
        array $users,
        string $namescheme = "[idnumber]",
        int $grouping = 0,
        string|null $groupingname = null,
        bool $previewonly = false,
        int $enablegroupmessaging = 0
    ): array {
        global $DB, $USER;

        require_capability('mod/grouptool:administrate_groups', $this->context);

        // Allocate members from the selected role to groups!
        $usercnt = count($users);

        if ($usercnt === 0) {
            // Keep the original return contract: error + message.
            return [0 => true, 1 => get_string('nousersselected', 'grouptool')];
        }

        $digits = ceil(log10($usercnt));
        $namescheme = trim($namescheme);

        // Prepare group data!
        $groups = [];
        $i = 0;
        foreach ($users as $user) {
            if (!is_object($user) || empty($user->id)) {
                throw new coding_exception('Invalid user object in users array.');
            }
            $name = $this->groups_parse_name($namescheme, $i, $user, $digits);
            $groups[] = [
                'name' => $name,
                'member' => $user,
            ];
            $i++;
        }

        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head = [
                get_string('groupscount', 'group', $usercnt),
                get_string('groupmembers', 'group'),
            ];
            $table->size = ['30%', '70%'];
            $table->align = ['left', 'left'];

            $table->data = [];
            $groupnames = [];
            foreach ($groups as $group) {
                $line = [];
                if (
                    groups_get_group_by_name($this->course->id, $group['name'])
                    || in_array($group['name'], $groupnames)
                ) {
                    $error = true;
                    if (in_array($group['name'], $groupnames)) {
                        $line[] = '<span class="late">' .
                            get_string('nameschemenotunique', 'grouptool', $group['name']) . '</span>';
                    } else {
                        $line[] = '<span class="late">' .
                            get_string('groupnameexists', 'group', $group['name']) . '</span>';
                    }
                } else {
                    $groupnames[] = $group['name'];
                    $line[] = $group['name'];
                }
                $line[] = fullname($group['member']);

                $table->data[] = $line;
            }
            return [0 => $error, 1 => html_writer::table($table)];
        } else {
            $createdgrouping = null;
            $createdgroups = [];
            $failed = false;

            // Prepare grouping!
            if (!empty($grouping)) {
                if ($grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name = trim($groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            group_creation_started::create_person($this->cm, $namescheme, $groupingid)->trigger();

            // Save the groups data!
            $error = '';
            foreach ($groups as $group) {
                if (groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name = $group['name'];
                $newgroup->enablemessaging = $enablegroupmessaging == 1 ? 1 : null;
                $groupid = groups_create_group($newgroup);

                // Insert into agrp-table!
                $newagrp = new stdClass();
                $newagrp->groupid = $groupid;
                $newagrp->grouptoolid = $this->grouptool->id;
                $newagrp->sort_order = 999999;
                if ($this->grouptool->allow_reg) {
                    $newagrp->active = 1;
                } else {
                    $newagrp->active = 0;
                }
                if (
                    !$DB->record_exists('grouptool_agrps', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid' => $groupid,
                    ])
                ) {
                    $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $newagrp->id = $DB->get_field('grouptool_agrps', 'id', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid' => $groupid,
                    ]);
                    if ($this->grouptool->allow_reg) {
                        $DB->set_field('grouptool_agrps', 'active', 1, ['id' => $newagrp->id]);
                    }
                }
                $createdgroups[] = $groupid;
                groups_add_member($groupid, $group['member']->id);
                $usrreg = new stdClass();
                $usrreg->userid = $group['member']->id;
                $usrreg->agrpid = $newagrp->id;
                $usrreg->timestamp = time();
                $usrreg->modified_by = $USER->id;
                $attr = [
                    'userid' => $group['member']->id,
                    'agrpid' => $newagrp->id,
                ];
                if (!$DB->record_exists('grouptool_registered', $attr)) {
                    $DB->insert_record('grouptool_registered', $usrreg);
                } else {
                    $DB->set_field('grouptool_registered', 'modified_by', $USER->id, $attr);
                }

                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
                return [
                    0 => true,
                    1 => get_string('group_creation_failed', 'grouptool') . html_writer::empty_tag('br') . $error,
                ];
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                agrps_updated::create_groupcreation(
                    $this->cm,
                    $namescheme,
                    count($groups),
                    $groupingid
                )->trigger();
                return [0 => false, 1 => get_string('groups_created', 'grouptool')];
            }
        }
    }

    /**
     * Create a grouping for each selected groupmoodle-groups
     *
     * Uses $SESSION->grouptool->view_administration->use_all to determin if groupings for all
     * or just selected groups should be created and also uses
     * $SESSION->grouptool->view_administration->grouplist[$group->id]['active']
     * to determin which groups have been selected
     *
     * @param int|null $courseid optional id of course to create for
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function create_group_groupings(int $courseid = null, bool $previewonly = false): array {
        global $SESSION, $OUTPUT;

        require_capability('mod/grouptool:administrate_groups', $this->context);

        // Create groupings!
        $created = [];
        $error = false;
        $return = "";

        $table = new html_table();
        $table->attributes['class'] = 'centeredblock';
        $table->head = [
            new html_table_cell(get_string('grouping', 'group')),
            new html_table_cell(get_string('info') . '/' .
                get_string('groups')),
        ];

        // Get all course-groups!
        if ($courseid == null) {
            if (isset($this->course->id)) {
                $courseid = $this->course->id;
            } else {
                throw new moodle_exception('coursemisconf');
            }
        }
        $groups = groups_get_all_groups($courseid);
        $ids = [];
        foreach ($groups as $group) {
            $row = [new html_table_cell($group->name)];
            $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
            if (
                empty($SESSION->grouptool->view_administration->use_all)
                && !$active
            ) {
                continue;
            }
            $groupid = $group->id;
            if (groups_get_grouping_by_name($courseid, $group->name)) {
                // Creation of grouping failed!
                if ($previewonly) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                $cell = new html_table_cell($OUTPUT->notification($text, notification::NOTIFY_ERROR));
                $row[] = $cell;
                $error = true;
            } else {
                $ids[] = $group->id;
                $groupingid = groups_create_grouping($group);
                if ($groupingid) {
                    if (!groups_assign_grouping($groupingid, $groupid)) {
                        if ($previewonly) {
                            $text = get_string('group_assign_error_prev', 'grouptool');
                        } else {
                            $text = get_string('group_assign_error', 'grouptool');
                        }
                        $cell = new html_table_cell($OUTPUT->notification($text, notification::NOTIFY_ERROR));
                        $row[] = $cell;
                        $error = true;
                    } else {
                        if ($previewonly) {
                            $content = $group->name;
                        } else {
                            $content = $OUTPUT->notification(
                                get_string('grouping_creation_success', 'grouptool', $group->name),
                                notification::NOTIFY_SUCCESS
                            );
                        }
                        $cell = new html_table_cell($content);
                        $row[] = $cell;
                        $created[] = $groupingid;
                    }
                } else {
                    if ($previewonly) {
                        $text = get_string('grouping_creation_error_prev', 'grouptool');
                    } else {
                        $text = get_string('grouping_creation_error', 'grouptool');
                    }
                    $cell = new html_table_cell($OUTPUT->notification($text, notification::NOTIFY_ERROR));
                    $row[] = $cell;
                    $error = true;
                }
            }
            $table->data[] = new html_table_row($row);
            $return = html_writer::table($table);
        }
        if ($previewonly || $error) { // Undo everything!
            foreach ($created as $groupingid) {
                $groupingsgroups = groups_get_all_groups($courseid, 0, $groupingid);
                foreach ($groupingsgroups as $group) {
                    groups_unassign_grouping($groupingid, $group->id);
                }
                groups_delete_grouping($groupingid);
            }
        } else if (!$previewonly) {
            // Trigger the event!
            groupings_created::create_from_object($this->cm, $ids)->trigger();
        }
        return [0 => $error, 1 => $return];
    }
    /**
     * Create a grouping for all selected moodle-groups
     *
     * Uses $SESSION->grouptool->view_administration->use_all to determin if groupings for all
     * or just selected groups should be created and also uses
     * $SESSION->grouptool->view_administration->grouplist[$group->id]['active']
     * to determin which groups have been selected
     *
     * @param int $target -1 for new grouping or groupingid
     * @param string|null $name name for new grouping if $target = -1
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function update_grouping(int $target, ?string $name = null, bool $previewonly = false): array {
        global $SESSION, $OUTPUT;
        $error = false;
        $return = "";

        require_capability('mod/grouptool:administrate_groups', $this->context);

        if (isset($this->course->id)) {
            $courseid = $this->course->id;
        } else {
            $courseid = 0;
            throw new moodle_exception('coursemisconf');
        }

        if ($target == -1) {
            if (groups_get_grouping_by_name($courseid, $name)) {
                // Creation of grouping failed!
                if ($previewonly) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                return [0 => true, 1 => $OUTPUT->notification($text, notification::NOTIFY_ERROR)];
            } else {
                if (empty($previewonly)) {
                    // Create grouping and set as target.
                    $grouping = new stdClass();
                    $grouping->name = $name;
                    $grouping->courseid = $courseid;
                    $target = groups_create_grouping($grouping);
                    $return = $OUTPUT->notification(get_string(
                        'grouping_creation_only_success',
                        'grouptool'
                    ), notification::NOTIFY_SUCCESS);
                } else {
                    $return = $OUTPUT->notification(get_string(
                        'grouping_creation_only_success_prev',
                        'grouptool'
                    ), notification::NOTIFY_INFO);
                }
            }
        }

        $ids = [];
        if (!empty($target)) {
            $groups = groups_get_all_groups($courseid);
            $success = [];
            $failure = [];
            foreach ($groups as $group) {
                $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
                if (
                    empty($SESSION->grouptool->view_administration->use_all)
                    && !$active
                ) {
                    continue;
                }
                $groupid = $group->id;

                if (!groups_assign_grouping($target, $groupid)) {
                    $failure[] = $group->name;
                    $error = true;
                } else {
                    $success[] = $group->name;
                }
            }
            if ($previewonly) {
                if (!empty($success)) {
                    $return .= $OUTPUT->notification(
                        get_string('grouping_assign_success_prev', 'grouptool') .
                        html_writer::empty_tag('br') . implode(', ', $success),
                        notification::NOTIFY_INFO
                    );
                }
                if ($error) {
                    $return .= $OUTPUT->notification(
                        get_string('grouping_assign_error_prev', 'grouptool') .
                        html_writer::empty_tag('br') . implode(', ', $failure),
                        notification::NOTIFY_ERROR
                    );
                }
            } else {
                $return .= $OUTPUT->notification(get_string('grouping_assign_success', 'grouptool')
                    . html_writer::empty_tag('br')
                    . implode(', ', $success), notification::NOTIFY_SUCCESS);
                if ($error) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_error', 'grouptool')
                        . html_writer::empty_tag('br')
                        . implode(', ', $failure), notification::NOTIFY_ERROR);
                }
            }
        }
        if (!$previewonly) {
            // Trigger the event!
            groupings_created::create_from_object($this->cm, $ids)->trigger();
        }
        return [0 => $error, 1 => $return];
    }
    /**
     * Parse a group name for characters to replace
     *
     * @param string $namescheme The scheme used for building group names
     * @param int $groupnumber The number of the group to be used in the parsed format string
     * @param array|stdClass|null $members optional object or array of objects containing data of members
     *                              for the tags to be replaced with
     * @param int $digits optional number of digits for from-to-group-creation
     * @return string the parsed format string
     */
    private function groups_parse_name(string $namescheme, int $groupnumber, array|stdClass|null $members = null, int $digits = 0): string {

        $tags = ['firstname', 'lastname', 'idnumber', 'username'];
        $pregsearch = "#\[(" . implode("|", $tags) . ")\]#";
        if (preg_match($pregsearch, $namescheme) > 0) {
            if ($members != null) {
                $data = [];
                if (is_array($members)) {
                    foreach ($tags as $key => $tag) {
                        foreach ($members as $member) {
                            if (!empty($member->$tag)) {
                                if (isset($data[$key]) && $data[$key] != "") {
                                    $data[$key] .= "-";
                                } else if (!isset($data[$key])) {
                                    $data[$key] = "";
                                }

                                $data[$key] .= substr($member->$tag, 0, 3);
                            }
                        }
                        if (empty($data[$key])) {
                            $data[$key] = "no" . $tag . "#";
                        }
                    }
                } else {
                    foreach ($tags as $key => $tag) {
                        if (!empty($members->$tag)) {
                            $data[$key] = $members->$tag;
                        } else {
                            $data[$key] = "no" . $tag . "#";
                        }
                    }
                }
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[" . $tag . "]";
                }
                $namescheme = str_replace($tags, $data, $namescheme);
            } else {
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[" . $tag . "]";
                }
                $namescheme = str_replace($tags, "", $namescheme);
            }
        }

        if (str_contains($namescheme, '@')) { // Convert $groupnumber to a character series!
            if ($groupnumber > GROUPTOOL_BEP) {
                $nexttempnumber = $groupnumber;
                $string = "";
                $orda = ord('A');
                $ordz = ord('Z');
                do {
                    $tempnumber = $nexttempnumber;
                    $mod = ($tempnumber) % ($ordz - $orda + 1);
                    $letter = chr($orda + $mod);
                    $string .= $letter;
                    $nexttempnumber = floor(($tempnumber) / ($ordz - $orda + 1)) - 1;
                } while ($tempnumber >= ($ordz - $orda + 1));

                $namescheme = str_replace('@', strrev($string), $namescheme);
            } else {
                $letter = 'A';
                for ($i = 0; $i < $groupnumber; $i++) {
                    $letter++;
                }
                $namescheme = str_replace('@', $letter, $namescheme);
            }
        }

        if (str_contains($namescheme, '#')) {
            if ($digits != 0) {
                $format = '%0' . $digits . 'd';
            } else {
                $format = '%d';
            }
            $namescheme = str_replace('#', sprintf($format, $groupnumber + 1), $namescheme);
        }
        return $namescheme;
    }
    /**
     * gets data about active groups for this instance or all instances if ignoregtinstance is set
     *
     * @param bool $includeregs optional include registered users in returned object
     * @param bool $includequeues optional include queued users in returned object
     * @param int $agrpid optional filter by a single active-groupid from {grouptool_agrps}.id
     * @param int $groupid optional filter by a single group-id from {groups}.id
     * @param int $groupingid optional filter by a single grouping-id
     * @param bool $indexbygroup optional index returned array by {groups}.id
     *                                    instead of {grouptool_agrps}.id
     * @param bool $includeinactive optional include also inactive groups - despite the method being called get_active_groups()!
     * @param bool $ignoregtinstance If true gets active groups from all grouptool instances and not only from this instance
     * @return array of objects containing all necessary information about chosen active groups
     * @throws dml_exception
     */
    public function get_active_groups(
        bool $includeregs = false,
        bool $includequeues = false,
        int  $agrpid = 0,
        int  $groupid = 0,
        int  $groupingid = 0,
        bool $indexbygroup = true,
        bool $includeinactive = false,
        bool $ignoregtinstance = false
    ): array {
        global $DB;

        if (!$ignoregtinstance) {
            $params = ['grouptoolid' => $this->cm->instance];
        }
        if (!empty($agrpid)) {
            $agrpidwhere = " AND agrp.id = :agroup";
            $params['agroup'] = $agrpid;
        } else {
            $agrpidwhere = "";
        }
        if (!empty($groupid)) {
            $groupidwhere = " AND grp.id = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupidwhere = "";
        }
        if (!empty($groupingid)) {
            $groupingidwhere = " AND grpgs.id = :groupingid";
            $params['groupingid'] = $groupingid;
        } else {
            $groupingidwhere = "";
        }

        if (!empty($this->grouptool->use_size)) {
            $grouptoolgrpsize = get_config('mod_grouptool', 'grpsize');
            $grpsize = (!empty($this->grouptool->grpsize) ? $this->grouptool->grpsize : $grouptoolgrpsize);
            if (empty($grpsize)) {
                $grpsize = 3;
            }
            $sizesql = " COALESCE(agrp.grpsize, " . $grpsize . ") AS grpsize,";

        } else {
            $sizesql = "";
        }
        if ($indexbygroup) {
            $idstring = "grp.id AS id, agrp.id AS agrpid";
        } else {
            $idstring = "agrp.id AS agrpid, grp.id AS id";
        }

        if (!$includeinactive) {
            $active = " AND agrp.active = 1 ";
        } else {
            $active = "";
        }

        if ($ignoregtinstance) {
            $groupdata = $DB->get_records_sql("
                   SELECT " . $idstring . ", MAX(grp.name) AS name, MAX(grp.description) AS description," .
                $sizesql . " MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE 1=1" . $active .
                $agrpidwhere . $groupidwhere . $groupingidwhere . "
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        } else {
            $params['grouptoolid1'] = $params['grouptoolid'];
            $groupdata = $DB->get_records_sql("
                   SELECT " . $idstring . ", MAX(grp.name) AS name, MAX(grp.description) AS description," .
                $sizesql . " MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id AND agrp.grouptoolid = :grouptoolid
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE agrp.grouptoolid = :grouptoolid1 " . $active .
                $agrpidwhere . $groupidwhere . $groupingidwhere . "
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        }
        if (!empty($groupdata)) {
            foreach ($groupdata as $key => $group) {
                $groupingids = $DB->get_fieldset_select(
                    'groupings_groups',
                    'groupingid',
                    'groupid = ?',
                    [$group->id]
                );
                if (!empty($groupingids)) {
                    $group->classes = implode(',', $groupingids);
                } else {
                    $group->classes = '';
                }
            }

            if (
                (!empty($this->grouptool->use_size))
                || ($this->grouptool->use_queue && $includequeues)
                || ($includeregs)
            ) {
                $keys = array_keys($groupdata);
                foreach ($keys as $key) {
                    $groupdata[$key]->queued = null;
                    if ($includequeues && $this->grouptool->use_queue) {
                        $attr = ['agrpid' => $groupdata[$key]->agrpid];
                        $groupdata[$key]->queued = $DB->get_records('grouptool_queued', $attr);
                    }

                    $groupdata[$key]->registered = null;
                    if ($includeregs) {
                        $params = ['agrpid' => $groupdata[$key]->agrpid];
                        $where = "agrpid = :agrpid AND modified_by >= 0";
                        $groupdata[$key]->registered = $DB->get_records_select(
                            'grouptool_registered',
                            $where,
                            $params
                        );
                        $params['modifierid'] = -1;
                        $where = "agrpid = :agrpid AND modified_by = :modifierid";
                        $groupdata[$key]->marked = $DB->get_records_select(
                            'grouptool_registered',
                            $where,
                            $params
                        );
                        $groupdata[$key]->moodle_members = groups_get_members($groupdata[$key]->id);
                    }
                }
                unset($key);
            }
        } else {
            $groupdata = [];
        }

        return $groupdata;
    }
    /**
     *  Adds all missin agrp-entries for this instance!
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_missing_agrps(): void {
        global $DB;

        // Get all course's group-IDs!
        $groupids = groups_get_all_groups($this->course->id, 0, 0, 'g.id');
        $groupids = array_keys($groupids);

        // Get all group-IDs which have active group entries!
        $ok = $DB->get_fieldset_select('grouptool_agrps', "DISTINCT groupid", "grouptoolid = ?", [$this->grouptool->id]);
        $missing = array_diff($groupids, $ok);

        if (!empty($missing)) {
            $added = [];
            foreach ($missing as $cur) {
                $newgrp = $this->add_agrp_entry($cur);
                $added[] = $newgrp->id;
            }
            if (!empty($added)) {
                // Set them inactive!
                [$addedsql, $addedparams] = $DB->get_in_or_equal($added);
                $DB->set_field_select('grouptool_agrps', 'active', 0, "id " . $addedsql, $addedparams);
            }
        }
    }

    /**
     * Adds an agrp-entry for newly created group!
     *
     * @param int $groupid Group ID to add agrp entry for!
     * @return stdClass (new) agrp record
     * @throws dml_exception
     */
    protected function add_agrp_entry(int $groupid): stdClass {
        global $DB;

        // Insert into agrp-table!
        $newagrp = new stdClass();
        $newagrp->groupid = $groupid;
        $newagrp->grouptoolid = $this->grouptool->id;
        $newagrp->sort_order = 999999;
        if ($this->grouptool->allow_reg) {
            $newagrp->active = 1;
        } else {
            $newagrp->active = 0;
        }
        $attr = [
            'grouptoolid' => $this->grouptool->id,
            'groupid' => $groupid,
        ];
        if (!$DB->record_exists('grouptool_agrps', $attr)) {
            $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp);
        } else {
            /* This is also the case if eventhandlers work properly
             * because group gets already created in eventhandler
             */
            $newagrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
            if ($this->grouptool->allow_reg) {
                $DB->set_field('grouptool_agrps', 'active', 1, ['id' => $newagrp->id]);
            }
        }

        return $newagrp;
    }
    /**
     * Returns a single select to change currently selected grouping.
     *
     * @param moodle_url $url Base URL to use
     * @param int $groupingid Currently active grouping ID or 0
     * @return single_select
     * @throws coding_exception
     */
    public function get_grouping_select(moodle_url $url, int $groupingid): single_select {
        $groupings = groups_get_all_groupings($this->course->id);
        $options = [0 => get_string('all')];
        if (count($groupings)) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = $grouping->name;
            }
        }
        return new single_select($url, 'groupingid', $options, $groupingid, false);
    }

    /**
     * Returns a single select to change currently selected group.
     *
     * @param moodle_url $url Base URL to use
     * @param int $groupingid Currently active grouping ID or 0
     * @param int $groupid Currently active group ID or 0
     * @return single_select
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_groups_select(moodle_url $url, int $groupingid, int $groupid): single_select {
        global $OUTPUT;

        $groups = $this->get_active_groups(false, false, 0, 0, $groupingid);
        $options = [0 => get_string('all')];
        if (count($groups)) {
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
        }
        if (!key_exists($groupid, $options)) {
            $groupid = 0;
            $url->param('groupid', 0);
            echo $OUTPUT->box($OUTPUT->notification(
                get_string('group_not_in_grouping', 'grouptool') .
                html_writer::empty_tag('br') .
                get_string('switched_to_all_groups', 'grouptool'),
                notification::NOTIFY_ERROR
            ), 'generalbox centered');
        }
        return new single_select($url, 'groupid', $options, $groupid, false);
    }
    /**
     * get all data necessary for displaying/exporting group-overview table
     *
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param bool $onlydata optional return object with raw data not html-fragment-string
     * @param bool $includeinactive optional include inactive groups too!
     * @return array|int|string either html-fragment representing table or raw data as object
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function group_overview_table(int $groupingid = 0, int $groupid = 0, bool $onlydata = false, bool $includeinactive = false): int|array|string {
        global $OUTPUT, $CFG, $DB;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $downloadurl = new moodle_url('/mod/grouptool/download.php', [
            'id' => $this->cm->id,
            'groupingid' => $groupingid,
            'groupid' => $groupid,
            'orientation' => $orientation,
            'sesskey' => sesskey(),
            'tab' => 'overview',
            'inactive' => $includeinactive,
        ]);
        $return = [];

        // We just get an overview and fetch data later on a per group basis to save memory!
        $agrps = $this->get_active_groups(
            false,
            false,
            0,
            $groupid,
            $groupingid,
            true,
            $includeinactive
        );
        $groupinfo = groups_get_all_groups($this->grouptool->course);
        $userinfo = [];
        $syncstatus = $utils->get_sync_status();
        if (!$onlydata && count($agrps)) {
            // Echo the Global-downloadlinks!
            echo $utils->get_download_links($downloadurl, 0, $this->context);
        }

        foreach ($agrps as $agrp) {
            // We give each group 30 seconds (minimum) and hope it doesn't time out because of no output in case of download!
            core_php_time_limit::raise(30);
            $groupdata = new stdClass();
            $groupdata->name = $groupinfo[$agrp->id]->name . ($agrp->active ? '' : ' (' . get_string('inactive') . ')');

            // Get all registered userids!
            $select = " agrpid = ? AND modified_by >= 0 ";
            $registered = $DB->get_fieldset_select('grouptool_registered', 'userid', $select, [$agrp->agrpid]);
            // Get all moodle-group-member-ids!
            $select = " groupid = ? ";
            $members = $DB->get_fieldset_select('groups_members', 'userid', $select, [$agrp->id]);
            // Get all registered users with moodle-group-membership!
            $absregs = array_intersect($registered, $members);
            // Get all registered users without moodle-group-membership!
            $gtregs = array_diff($registered, $members);
            // Get all moodle-group-members without registration!
            $mdlregs = array_diff($members, $registered);
            // Get all queued users!
            $select = " agrpid = ? ";
            $queued = $DB->get_fieldset_select('grouptool_queued', 'userid', $select, [$agrp->agrpid]);

            // We give additional 1 second per registration/queue/moodle entry in this group!
            core_php_time_limit::raise(30 * (count($registered) + count($members) + count($queued)));

            if (!empty($this->grouptool->use_size)) {
                if (!empty($agrp->grpsize)) {
                    $size = $agrp->grpsize;
                    $free = $agrp->grpsize - count($registered);
                } else {
                    $size = !empty($this->grouptool->grpsize) ? $this->grouptool->grpsize : get_config(
                        'mod_grouptool',
                        'grpsize'
                    );
                    $free = ($size - count($registered));
                }
            } else {
                $size = "∞";
                $free = '∞';
            }

            $groupdata->queued = count($queued);
            $groupdata->registered = count($registered);
            $groupdata->total = $groupdata->registered + $groupdata->queued;
            $groupdata->free = $free;
            $groupdata->reg_data = [];
            $groupdata->queue_data = [];
            $groupdata->inactive = !$agrp->active;
            if ($agrp->active) {
                $groupdata->uptodate = $syncstatus[1][$agrp->agrpid]->status === GROUPTOOL_UPTODATE;
                $groupdata->outdated = $syncstatus[1][$agrp->agrpid]->status !== GROUPTOOL_UPTODATE;
            }
            // User-ID will be added in template!
            $groupdata->userlink = $CFG->wwwroot . '/user/view.php?course=' . $this->grouptool->course . '&id=';
            $groupdata->groupid = $groupinfo[$agrp->id]->id;
            $groupdata->formattxt = GROUPTOOL_TXT;
            $groupdata->formatpdf = GROUPTOOL_PDF;
            $groupdata->formatxlsx = GROUPTOOL_XLSX;
            $groupdata->formatods = GROUPTOOL_ODS;
            $groupdata->useridentity = $utils->convert_associative_array_into_nested_index_array($utils->get_useridentity_fields());

            $statushelp = new help_icon('status', 'mod_grouptool');
            $groupdata->downloadcapability = has_capability('mod/grouptool:export', $this->context);
            if (!$onlydata) {
                $groupdata->statushelp = $statushelp->export_for_template($OUTPUT);
                // Format will be added in template!
                $groupdownloadurl = new moodle_url($downloadurl, ['groupid' => $groupinfo[$agrp->id]->id]);
                $groupdata->downloadurl = $groupdownloadurl->out(false);
            }

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = fields::for_name()->get_required_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            if (count($registered) + count($members) >= 1) {
                if (count($absregs) >= 1) {
                    foreach ($absregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', ['id' => $curuser]);
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = [];
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        $row['useridentityvalues'] = $utils->convert_associative_array_into_nested_index_array(
                            $utils->get_namefields_useridentity($row, $userinfo[$curuser])
                        );
                        $utils->add_namefields_useridentity($row, $userinfo[$curuser]);
                        // We set those in any case, because PDF and TXT export needs them anyway!
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "✔";
                        $groupdata->reg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($gtregs) >= 1) {
                    foreach ($gtregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', ['id' => $curuser]);
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = [];
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        $row['useridentityvalues'] = $utils->convert_associative_array_into_nested_index_array(
                            $utils->get_namefields_useridentity($row, $userinfo[$curuser])
                        );
                        $utils->add_namefields_useridentity($row, $userinfo[$curuser]);
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "+";
                        $groupdata->reg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($mdlregs) >= 1) {
                    foreach ($mdlregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', ['id' => $curuser]);
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = [];
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        $row['useridentityvalues'] = $utils->convert_associative_array_into_nested_index_array(
                            $utils->get_namefields_useridentity($row, $userinfo[$curuser])
                        );
                        $utils->add_namefields_useridentity($row, $userinfo[$curuser]);
                        // We set those in any case, because PDF and TXT export needs them anyway!
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "?";
                        $groupdata->mreg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }
            }

            if (count($queued) >= 1) {
                $queuedlist = $DB->get_records('grouptool_queued', ['agrpid' => $agrp->agrpid], 'timestamp ASC');
                foreach ($queued as $curuser) {
                    if (!array_key_exists($curuser, $userinfo)) {
                        $userinfo[$curuser] = $DB->get_record('user', ['id' => $curuser]);
                    }
                    $fullname = fullname($userinfo[$curuser]->firstname);
                    $rank = $queuemanager->get_rank_in_queue($queuedlist, $curuser);

                    $row = [];
                    $row['userid'] = $curuser;
                    $row['rank'] = $rank;
                    $row['name'] = $fullname;
                    $row['useridentityvalues'] = $utils->convert_associative_array_into_nested_index_array(
                        $utils->get_namefields_useridentity($row, $userinfo[$curuser])
                    );
                    $utils->add_namefields_useridentity($row, $userinfo[$curuser]);
                    // We set those in any case, because PDF and TXT export needs them anyway!
                    $row['email'] = $userinfo[$curuser]->email;
                    $row['idnumber'] = $userinfo[$curuser]->idnumber;
                    $groupdata->queue_data[] = $row;
                }
            }
            if (!$onlydata) {
                echo $OUTPUT->render_from_template('mod_grouptool/overviewgroup', $groupdata);
            } else {
                $return[] = $groupdata;
            }
            $groupdata = null;
            unset($groupdata);
        }

        if (count($agrps) == 0) {
            $boxcontent = $OUTPUT->notification(
                get_string('no_data_to_display', 'grouptool'),
                notification::NOTIFY_ERROR
            );
            $return = $OUTPUT->box($boxcontent, 'generalbox centered');
            if (!$onlydata) {
                echo $return;
            }
        }
        if ($onlydata) {
            return $return;
        } else {
            return 0;
        }
    }
}
