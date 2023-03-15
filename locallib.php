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
 * Contains class mod_grouptool with most of grouptool's logic.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Hannes Laimer
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/lib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_grade.php');
require_once($CFG->libdir.'/pdflib.php');

/**
 * class containing most of the logic used in grouptool-module
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool {
    /** @var object */
    protected $cm;
    /** @var object */
    protected $course;
    /** @var object */
    protected $grouptool;
    /** @var object instance's context record */
    protected $context;

    /**
     * filter all groups
     */
    const FILTER_ALL = 0;
    /**
     * filter active groups
     */
    const FILTER_ACTIVE = 1;
    /**
     * filter inactive groups
     */
    const FILTER_INACTIVE = 2;

    /**
     * NAME_TAGS - the tags available for grouptool's group naming schemes
     */
    const NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

    /**
     * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
     */
    const HIDE_GROUPMEMBERS = GROUPTOOL_HIDE_GROUPMEMBERS;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
     */
    const SHOW_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
     */
    const SHOW_OWN_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_OWN_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
     */
    const SHOW_OWN_GROUPMEMBERS_AFTER_REG = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
    /**
     * SHOW_GROUPMEMBERS - show groupmembers no matter what...
     */
    const SHOW_GROUPMEMBERS = GROUPTOOL_SHOW_GROUPMEMBERS;

    /**
     * Constructor for the grouptool class
     *
     * If cmid is set create the cm, course, checkmark objects.
     *
     * @param int $cmid the current course module id - not set for new grouptools
     * @param stdClass $grouptool usually null, but if we have it we pass it to save db access
     * @param stdClass $cm usually null, but if we have it we pass it to save db access
     * @param stdClass $course usually null, but if we have it we pass it to save db access
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($cmid, $grouptool=null, $cm=null, $course=null) {
        global $DB;

        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }

        if (!empty($cm)) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('grouptool', $cmid)) {
            print_error('invalidcoursemodule');
        }
        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if (! $this->course = $DB->get_record('course', ['id' => $this->cm->course])) {
            print_error('invalidid', 'grouptool');
        }

        if ($grouptool) {
            $this->grouptool = $grouptool;
        } else if (! $this->grouptool = $DB->get_record('grouptool',
                                                        ['id' => $this->cm->instance])) {
            print_error('invalidid', 'grouptool');
        }

        $this->grouptool->cmidnumber = $this->cm->idnumber;
        $this->grouptool->course   = $this->course->id;

        /*
         * visibility handled by require_login() with $cm parameter
         * get current group only when really needed
         */
    }

    /**
     * Return the grouptools name
     *
     * @return string the name
     */
    public function get_name() {
        return $this->grouptool->name;
    }

    /**
     * Return Grouptool's settings
     *
     * @return object Grouptool's DB record
     */
    public function get_settings() {
        return $this->grouptool;
    }

    /**
     * Return Grouptool's multiple registrations settings
     *
     * @return array [allow_multiple, choose_min, choose_max]
     */
    public function get_reg_settings() {
        return [$this->grouptool->allow_multiple, $this->grouptool->choose_min, $this->grouptool->choose_max];
    }

    /**
     * Translates top level tabs.
     *
     * @param tabobject[] $tabs
     * @param string|null $tab
     */
    public static function translate_top_level_tabs(array $tabs, string $tab = null) {
        global $SESSION;

        // Now translate, if there's a top level tab chosen, which has just child-tabs!
        switch ($tab) {
            case 'users':
            case 'administration':
                if (!empty($tabs[$tab]->subtree)) {
                    $tab = key($tabs[$tab]->subtree);
                    $SESSION->mod_grouptool->currenttab = $tab;
                }
                break;
        }
    }

    /**
     * Print a message along with button choices for Continue/Cancel
     *
     * If a string or moodle_url is given instead of a single_button, method defaults to post.
     * If cancel=null only continue button is displayed!
     *
     * @param string $message The question to ask the user
     * @param single_button|moodle_url|string $continue The single_button component representing the
     *                                                  Continue answer. Can also be a moodle_url
     *                                                  or string URL
     * @param single_button|moodle_url|string $cancel   The single_button component representing the
     *                                                  Cancel answer. Can also be a moodle_url or
     *                                                  string URL
     * @return string HTML fragment
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function confirm($message, $continue, $cancel = null) {
        global $OUTPUT;
        if (!($continue instanceof single_button)) {
            if (is_string($continue)) {
                $url = new moodle_url($continue);
                $continue = new single_button($url, get_string('continue'), 'post', true);
            } else if ($continue instanceof moodle_url) {
                $continue = new single_button($continue, get_string('continue'), 'post', true);
            } else {
                throw new coding_exception('The continue param to grouptool::confirm() must be either a'.
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
                throw new coding_exception('The cancel param to grouptool::confirm() must be either a'.
                                           ' URL (string/moodle_url), single_button instance or null.');
            }
        }

        $output = $OUTPUT->box_start('generalbox modal modal-dialog modal-in-page show', 'notice');
        $output .= $OUTPUT->box_start('modal-content', 'modal-content');
        $output .= $OUTPUT->box_start('modal-header', 'modal-header');
        $output .= html_writer::tag('h4', get_string('confirm'));
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_start('modal-body', 'modal-body');
        $output .= html_writer::tag('p', $message);
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_start('modal-footer', 'modal-footer');
        $cancel = ($cancel != null) ? $OUTPUT->render($cancel) : "";
        $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel, ['class' => 'buttons']);
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_end();
        return $output;
    }

    /**
     * Parse a group name for characters to replace
     *
     * @param string $namescheme The scheme used for building group names
     * @param int $groupnumber The number of the group to be used in the parsed format string
     * @param stdClass|array $members optional object or array of objects containing data of members
     *                              for the tags to be replaced with
     * @param int $digits optional number of digits for from-to-group-creation
     * @return string the parsed format string
     */
    private function groups_parse_name($namescheme, $groupnumber, $members = null, $digits = 0) {

        $tags = ['firstname', 'lastname', 'idnumber', 'username'];
        $pregsearch = "#\[(".implode("|", $tags).")\]#";
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
                            $data[$key] = "no".$tag."#";
                        }
                    }
                } else {
                    foreach ($tags as $key => $tag) {

                        if (!empty($members->$tag)) {
                            $data[$key] = $members->$tag;
                        } else {
                            $data[$key] = "no".$tag."#";
                        }
                    }
                }
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[".$tag."]";
                }
                $namescheme = str_replace($tags, $data, $namescheme);
            } else {
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[".$tag."]";
                }
                $namescheme = str_replace($tags, "", $namescheme);
            }
        }

        if (strstr($namescheme, '@') !== false) { // Convert $groupnumber to a character series!
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

        if (strstr($namescheme, '#') !== false) {
            if ($digits != 0) {
                $format = '%0'.$digits.'d';
            } else {
                $format = '%d';
            }
            $namescheme = str_replace('#', sprintf($format, $groupnumber + 1), $namescheme);
        }
        return $namescheme;
    }

    /**
     *  Adds all missin agrp-entries for this instance!
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_missing_agrps() {
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
                list($addedsql, $addedparams) = $DB->get_in_or_equal($added);
                $DB->set_field_select('grouptool_agrps', 'active', 0, "id ".$addedsql, $addedparams);
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
    protected function add_agrp_entry($groupid) {
        global $DB;

        // Insert into agrp-table!
        $newagrp = new stdClass();
        $newagrp->groupid = $groupid;
        $newagrp->grouptoolid = $this->grouptool->id;
        $newagrp->sort_order = 999999;
        if ($this->grouptool->allow_reg == true) {
            $newagrp->active = 1;
        } else {
            $newagrp->active = 0;
        }
        $attr = [
                'grouptoolid' => $this->grouptool->id,
                'groupid'     => $groupid
        ];
        if (!$DB->record_exists('grouptool_agrps', $attr)) {
            $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp, true);
        } else {
            /* This is also the case if eventhandlers work properly
             * because group gets allready created in eventhandler
             */
            $newagrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
            if ($this->grouptool->allow_reg == true) {
                $DB->set_field('grouptool_agrps', 'active', 1, ['id' => $newagrp->id]);
            }
        }

        return $newagrp;
    }

    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @param stdClass $data data from administration-form with all settings for group creation
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
    private function create_groups($data, $users, $userpergrp, $numgrps, $previewonly = false) {
        global $DB, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        $namestouse = [];

        // Allocate members from the selected role to groups!
        $usercnt = count($users);
        if ($data->allocateby == 'random') {
            srand($data->seed);
            shuffle($users);
        }

        $groups = [];

        // Number of groups with userpergrp+1 for properly allocating the rest without messing up the sort order.
        $plusonegroupcount = ($usercnt / $numgrps) > $userpergrp ? $usercnt % $numgrps : 0;

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
        // Add another digit if result of log is an integer (it means that no of groups was 10,10,100,...)
        $digits = fmod($digitslog, 1.) === 0 ? $digitslog + 1 : ceil($digitslog);
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i]['name']    = $this->groups_parse_name(trim($data->namingscheme), $i,
                                                              $groups[$i]['members'], $digits);
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            if ($data->allocateby == 'no') {
                $table->head  = [get_string('groupscount', 'group', $numgrps)];
                $table->size  = ['100%'];
                $table->align = ['left'];
            } else {
                $table->head  = [
                        get_string('groupscount', 'group', $numgrps),
                        get_string('groupmembers', 'group'),
                        get_string('usercounttotal', 'group', $usercnt)
                ];
                $table->size  = ['20%', '70%', '10%'];
                $table->align = ['left', 'left', 'center'];
            }
            $table->data  = [];

            foreach ($groups as $group) {
                $line = [];
                if (groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $namestouse)) {
                    $error = true;
                    if (in_array($group['name'], $namestouse)) {
                        $line[] = '<span class="late">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="late">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
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
            $grouping = null;
            $createdgrouping = 0;
            $createdgroups = [];
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name     = trim($data->groupingname);
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
                    \mod_grouptool\event\group_creation_started::create_groupamount($this->cm, $data->namingscheme,
                                                                                    $data->numberofgroups, $groupingid)->trigger();
                break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    \mod_grouptool\event\group_creation_started::create_memberamount($this->cm, $data->namingscheme,
                                                                                     $data->numberofmembers,
                                                                                     $groupingid)->trigger();
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
                $newgroup->name     = $group['name'];
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
                            'agrpid' => $newagrp->id
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
                \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme, $numgrps,
                                                                         $groupingid)->trigger();
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
     * @param stdClass $data data from administration-form with all settings for group creation
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    private function create_fromto_groups($data, $previewonly = false) {
        global $DB, $OUTPUT;

        require_capability('mod/grouptool:create_groups', $this->context);

        $groups = [];

        // Every member is there, so we can parse the name!
        for ($i = clean_param($data->from, PARAM_INT); $i <= clean_param($data->to, PARAM_INT); $i++) {
            $groups[] = $this->groups_parse_name(trim($data->namingscheme), $i - 1, null,
                                                 clean_param($data->digits, PARAM_INT));
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head  = [
                    get_string('groupscount', 'group',
                                  (clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1))
            ];
            $table->size  = ['100%'];
            $table->align = ['left'];

            $table->data  = [];
            $createdgroups = [];
            foreach ($groups as $group) {
                $line = [];
                if (groups_get_group_by_name($this->course->id, $group) || in_array($group, $createdgroups)) {
                    $error = true;
                    if (in_array($group, $createdgroups)) {
                        $line[] = '<span class="late">'.
                                  get_string('nameschemenotunique', 'grouptool', $group).'</span>';
                    } else {
                        $line[] = '<span class="late">'.
                                  get_string('groupnameexists', 'group', $group).'</span>';
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
                    $grouping->name     = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group creation started event.
            $groupingid = !empty($grouping->id) ? $grouping->id : 0;
            \mod_grouptool\event\group_creation_started::create_fromto($this->cm, $data->namingscheme, $data->from,
                                                                       $data->to, $groupingid)->trigger();

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
                $newgroup->name     = $group;
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
                        0 => $failed,
                        1 => get_string('group_creation_failed', 'grouptool').html_writer::empty_tag('br').$error
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
                \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme,
                                                                         $numgrps, $groupingid)->trigger();
                return [0 => $failed, 1 => get_string('groups_created', 'grouptool')];
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
     * @param string $groupingname optional name for created grouping
     * @param bool $previewonly optional only show preview of created groups
     * @param int $enablegroupmessaging optional enable messaging within group (default: no)
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    private function create_one_person_groups($users, $namescheme = "[idnumber]", $grouping = 0, $groupingname = null,
                                              $previewonly = false, $enablegroupmessaging=0) {
        global $DB, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        // Allocate members from the selected role to groups!
        $usercnt = count($users);

        // Prepare group data!
        $groups = [];
        $i = 0;
        $digits = ceil(log10(count($users)));
        foreach ($users as $user) {
            $groups[$i] = [];
            $groups[$i]['name']   = $this->groups_parse_name(trim($namescheme), $i, $user,
                                                             $digits);
            $groups[$i]['member'] = $user;
            $i++;
        }

        if ($previewonly) {
            $error = false;
            $table = new html_table();
            $table->head  = [
                    get_string('groupscount', 'group', $usercnt),
                    get_string('groupmembers', 'group')
            ];
            $table->size  = ['30%', '70%'];
            $table->align = ['left', 'left'];

            $table->data  = [];
            $groupnames = [];
            foreach ($groups as $group) {
                $line = [];
                if (groups_get_group_by_name($this->course->id, $group['name'])
                     || in_array($group['name'], $groupnames)) {
                    $error = true;
                    if (in_array($group['name'], $groupnames)) {
                        $line[] = '<span class="late">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="late">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
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
                    $grouping->name     = trim($groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            \mod_grouptool\event\group_creation_started::create_person($this->cm, $namescheme, $groupingid)->trigger();

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
                $newgroup->name     = $group['name'];
                $newgroup->enablemessaging = $enablegroupmessaging == 1 ? 1 : null;
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $newagrp = new stdClass();
                $newagrp->groupid = $groupid;
                $newagrp->grouptoolid = $this->grouptool->id;
                $newagrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $newagrp->active = 1;
                } else {
                    $newagrp->active = 0;
                }
                if (!$DB->record_exists('grouptool_agrps', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid'     => $groupid
                ])) {
                    $newagrp->id = $DB->insert_record('grouptool_agrps', $newagrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $newagrp->id = $DB->get_field('grouptool_agrps', 'id', [
                            'grouptoolid' => $this->grouptool->id,
                            'groupid'     => $groupid
                    ]);
                    if ($this->grouptool->allow_reg == true) {
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
                        'agrpid' => $newagrp->id
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
                        0 => $failed,
                        1 => get_string('group_creation_failed', 'grouptool').html_writer::empty_tag('br').$error
                ];
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                \mod_grouptool\event\agrps_updated::create_groupcreation($this->cm, $namescheme,
                                                                         count($groups), $groupingid)->trigger();
                return [0 => $failed, 1 => get_string('groups_created', 'grouptool')];
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
     * @param int $courseid optional id of course to create for
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    private function create_group_groupings($courseid = null, $previewonly = false) {
        global $SESSION, $OUTPUT;

        require_capability('mod/grouptool:create_groupings', $this->context);

        // Create groupings!
        $created = [];
        $error = false;
        $return = "";

        $table = new html_table();
        $table->attributes['class'] = 'centeredblock';
        $table->head = [
                new html_table_cell(get_string('grouping', 'group')),
                new html_table_cell(get_string('info').'/'.
                                                       get_string('groups'))
        ];

        // Get all course-groups!
        if ($courseid == null) {
            if (isset($this->course->id)) {
                $courseid = $this->course->id;
            } else {
                print_error('coursemisconf');
            }
        }
        $groups = groups_get_all_groups($courseid);
        $ids = [];
        foreach ($groups as $group) {
            $row = [new html_table_cell($group->name)];
            $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
            if (empty($SESSION->grouptool->view_administration->use_all)
                     && !$active) {
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
                $cell = new html_table_cell($OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR));
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
                        $cell = new html_table_cell($OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR));
                        $row[] = $cell;
                        $error = true;
                    } else {
                        if ($previewonly) {
                            $content = $group->name;
                        } else {
                            $content = $OUTPUT->notification(get_string('grouping_creation_success', 'grouptool', $group->name),
                                                             \core\output\notification::NOTIFY_SUCCESS);
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
                    $cell = new html_table_cell($OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR));
                    $row[] = $cell;
                    $error = true;
                }
            }
            $table->data[] = new html_table_row($row);
            $return = html_writer::table($table);
        }
        if ($previewonly || ($error && !$previewonly)) { // Undo everything!
            foreach ($created as $groupingid) {
                $groupingsgroups = groups_get_all_groups($courseid, 0, $groupingid);
                foreach ($groupingsgroups as $group) {
                    groups_unassign_grouping($groupingid, $group->id);
                }
                groups_delete_grouping($groupingid);
            }
        } else if (!$previewonly) {
            // Trigger the event!
            \mod_grouptool\event\groupings_created::create_from_object($this->cm, $ids)->trigger();
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
     * @param string $name name for new grouping if $target = -1
     * @param bool $previewonly optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     * @throws coding_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    private function update_grouping($target, $name = null, $previewonly = false) {
        global $SESSION, $OUTPUT;
        $error = false;
        $return = "";

        require_capability('mod/grouptool:create_groupings', $this->context);

        if (isset($this->course->id)) {
            $courseid = $this->course->id;
        } else {
            $courseid = 0;
            print_error('coursemisconf');
        }

        if ($target == -1) {
            if (groups_get_grouping_by_name($courseid, $name)) {
                // Creation of grouping failed!
                if ($previewonly) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                return [0 => true, 1 => $OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR)];
            } else {
                if (empty($previewonly)) {
                    // Create grouping and set as target.
                    $grouping = new stdClass();
                    $grouping->name = $name;
                    $grouping->courseid = $courseid;
                    $target = groups_create_grouping($grouping);
                    $return = $OUTPUT->notification(get_string('grouping_creation_only_success',
                            'grouptool'), \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    $return = $OUTPUT->notification(get_string('grouping_creation_only_success_prev',
                            'grouptool'), \core\output\notification::NOTIFY_INFO);
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
                if (empty($SESSION->grouptool->view_administration->use_all)
                         && !$active) {
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
                    $return .= $OUTPUT->notification(get_string('grouping_assign_success_prev', 'grouptool').
                                                     html_writer::empty_tag('br').implode(', ', $success),
                            \core\output\notification::NOTIFY_INFO);
                }
                if ($error) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_error_prev', 'grouptool').
                                                     html_writer::empty_tag('br').implode(', ', $failure),
                            \core\output\notification::NOTIFY_ERROR);
                }
            } else {
                $return .= $OUTPUT->notification(get_string('grouping_assign_success', 'grouptool')
                        .html_writer::empty_tag('br')
                        .implode(', ', $success), \core\output\notification::NOTIFY_SUCCESS);
                if ($error) {
                    $return .= $OUTPUT->notification(get_string('grouping_assign_error', 'grouptool')
                            .html_writer::empty_tag('br')
                            .implode(', ', $failure), \core\output\notification::NOTIFY_ERROR);
                }
            }
        }
        if (!$previewonly) {
            // Trigger the event!
            \mod_grouptool\event\groupings_created::create_from_object($this->cm, $ids)->trigger();
        }
        return [0 => $error, 1 => $return];
    }

    /**
     * Outputs the content of the administration tab and manages actions taken in this tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_administration() {
        global $SESSION, $OUTPUT, $PAGE, $DB, $USER, $CFG;

        $output = $PAGE->get_renderer('mod_grouptool');

        // Repair possibly missing agrps...
        $this->add_missing_agrps();

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = [];
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }

        $filter = optional_param('filter', null, PARAM_INT);
        if ($filter !== null) {
            set_user_preference('mod_grouptool_group_filter', $filter, $USER->id);
        } else {
            $filter = get_user_preferences('mod_grouptool_group_filter', self::FILTER_ACTIVE, $USER->id);
        }

        $inactivetabs = [];

        $filtertabs['active'] = new tabobject(self::FILTER_ACTIVE,
                $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                '&amp;tab=group_admin&filter='.self::FILTER_ACTIVE,
                get_string('active', 'grouptool'),
                '',
                false);
        $filtertabs['inactive'] = new tabobject(self::FILTER_INACTIVE,
                $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                '&amp;tab=group_admin&filter='.self::FILTER_INACTIVE,
                get_string('inactive'),
                '',
                false);
        $filtertabs['all'] = new tabobject(self::FILTER_ALL,
                $CFG->wwwroot.'/mod/grouptool/view.php?id='.$id.
                '&amp;tab=group_admin&filter='.self::FILTER_ALL,
                get_string('all'),
                '',
                false);

        $bulkaction = optional_param('bulkaction', null, PARAM_ALPHA);
        $selected = optional_param_array('selected', [], PARAM_INT);
        $dialog = false;
        if ($bulkaction && $selected && optional_param('start_bulkaction', 0, PARAM_BOOL)) {
            switch ($bulkaction) {
                case 'activate':  // ...also via ajax bulk action?
                    // Activate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                        $DB->set_field_select("grouptool_agrps", "active", 1,
                                " grouptoolid = ? AND groupid ".$grpsql, array_merge([$this->cm->instance], $grpparams));
                    }
                    echo $OUTPUT->notification(get_string('activated_groups', 'grouptool'),
                            \core\output\notification::NOTIFY_SUCCESS);
                    break;
                case 'deactivate':  // ...also via ajax bulk action?
                    // Deactivate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        list($grpsql, $grpparams) = $DB->get_in_or_equal($groups);
                        $DB->set_field_select("grouptool_agrps", "active", 0,
                                " grouptoolid = ? AND groupid ".$grpsql, array_merge([$this->cm->instance], $grpparams));
                    }
                    echo $OUTPUT->notification(get_string('deactivated_groups', 'grouptool'),
                            \core\output\notification::NOTIFY_SUCCESS);
                    break;
                case 'delete': // ...also via ajax bulk action?
                    // Show confirmation dialogue!
                    if (optional_param('confirm', 0, PARAM_BOOL)) {
                        $groups = optional_param_array('selected', null, PARAM_INT);
                        $groups = $DB->get_records_list('groups', 'id', $groups);
                        foreach ($groups as $group) {
                            groups_delete_group($group);
                        }
                        echo $OUTPUT->notification(get_string('successfully_deleted_groups', 'grouptool'),
                                \core\output\notification::NOTIFY_SUCCESS);
                    } else {
                        $cancel = new moodle_url($PAGE->url, ['tab' => 'group_admin']);
                        $params = ['confirm' => 1, 'bulkaction' => 'delete', 'start_bulkaction' => 1];
                        $text = get_string('confirm_delete', 'grouptool').html_writer::start_tag('ul');
                        $groups = $DB->get_records_list('groups', 'id', $selected);
                        foreach ($selected as $select) {
                            $params['selected['.$select.']'] = $select;
                            $text .= html_writer::tag('li', $groups[$select]->name);
                        }
                        $text .= html_writer::end_tag('ul');
                        $continue = new moodle_url($cancel, $params);

                        echo $this->confirm($text, $continue, $cancel);
                        echo $OUTPUT->footer();
                        $dialog = true;
                    }
                    break;
                case 'grouping':
                    // Show grouping creation form!
                    $selected = optional_param_array('selected', [], PARAM_INT);
                    $mform = new \mod_grouptool\groupings_creation_form(null, [
                            'id'       => $id,
                            'selected' => $selected
                    ]);
                    $groups = $DB->get_records_list('groups', 'id', $selected);
                    if ($mform->is_cancelled()) {
                        $bulkaction = null;
                        $selected = [];
                    } else if ($fromform = $mform->get_data()) {
                        // Some groupings should be created...
                        if ($fromform->target == -2) { // One new grouping per group!
                            foreach ($groups as $group) {
                                $grouping = new stdClass();
                                if (!$grouping->id = groups_get_grouping_by_name($this->course->id, $group->name)) {
                                    $grouping = new stdClass();
                                    $grouping->courseid = $this->course->id;
                                    $grouping->name     = $group->name;
                                    $grouping->id = groups_create_grouping($grouping);
                                }
                                // Insert group!
                                groups_assign_grouping($grouping->id, $group->id);
                            }
                        } else if ($fromform->target == -1) { // One new grouping!
                            // Create grouping if it doesn't exist...
                            $grouping = new stdClass();
                            if (!$grouping->id = groups_get_grouping_by_name($this->course->id, $fromform->name)) {
                                $grouping = new stdClass();
                                $grouping->courseid = $this->course->id;
                                $grouping->name     = trim($fromform->name);
                                $grouping->id = groups_create_grouping($grouping);
                            }
                            // Insert groups!
                            foreach ($groups as $group) {
                                groups_assign_grouping($grouping->id, $group->id);
                            }
                        } else if ($fromform->target > 0) { // Existing Grouping!
                            $grouping = groups_get_grouping($fromform->target);
                            if ($grouping) {
                                foreach ($groups as $group) {
                                    groups_assign_grouping($grouping->id, $group->id);
                                }
                            }
                        }
                        // ...redirect to show sortlist again!
                        $url = new moodle_url('/mod/grouptool/view.php', [
                                'id' => $this->cm->id,
                                'tab' => 'group_admin',
                                'filter' => $filter
                        ]);
                        echo $OUTPUT->notification(get_string('groupings_created_and_groups_added',
                                'grouptool'),  \core\output\notification::NOTIFY_SUCCESS);
                    } else {
                        $mform->display();
                        $dialog = true;
                    }
                    break;
            }
        }

        if (!$dialog) {
            echo html_writer::tag('div', $OUTPUT->tabtree($filtertabs, $filter, $inactivetabs),
                    ['id' => 'filtertabs']);
        }

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroupings)) {
                require_capability('mod/grouptool:create_groupings', $this->context);
                $target = required_param('target', PARAM_INT);
                switch ($target) { // ...grpg_target | grpg_groupingname | use_all (0 sel | 1 all).
                    case 0: // Invalid - no action! TODO Add message!
                        $preview = '';
                        break;
                    case -2: // One grouping per group!
                        list(, $preview) = $this->create_group_groupings();
                        break;
                    case -1: // One new grouping for all!
                        list(, $preview) = $this->update_grouping($target, required_param('name', PARAM_ALPHANUMEXT));
                        break;
                    default:
                        list(, $preview) = $this->update_grouping($target);
                        break;
                }
                $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
                echo $OUTPUT->box($preview, 'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        if ($rename = optional_param('rename', 0, PARAM_INT)) {
            // Show Rename Form!
            $gform = new \mod_grouptool\group_rename_form(null, [
                    'id'       => $this->cm->id,
                    'instance' => $this->cm->instance,
                    'rename'   => $rename
            ]);
            if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
                $group = new stdClass();
                $group->id = $fromform->rename;
                $group->name = $fromform->name;
                $group->courseid = $fromform->courseid;
                groups_update_group($group);
            } else if (!$gform->is_cancelled()) {
                $data = new stdClass();
                $data->name = $DB->get_field('groups', 'name', ['id' => $rename]);
                $gform->set_data($data);
                $gform->display();
                echo $OUTPUT->footer();
                die;
            }
        }

        if ($resize = optional_param('resize', 0, PARAM_INT)) {
            // Show Resize Form!
            $gform = new \mod_grouptool\group_resize_form(null, [
                    'id'       => $this->cm->id,
                    'instance' => $this->cm->instance,
                    'resize'   => $resize
            ]);
            if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
                if (empty($fromform->size)) {
                    $DB->set_field('grouptool_agrps', 'grpsize', null, [
                            'groupid'     => $fromform->resize,
                            'grouptoolid' => $this->cm->instance
                    ]);
                } else {
                    $group = new stdClass();
                    $group->id = $DB->get_field('grouptool_agrps', 'id', [
                            'groupid' => $fromform->resize,
                            'grouptoolid' => $this->cm->instance
                    ]);
                    $group->grpsize = $fromform->size;
                    $DB->update_record('grouptool_agrps', $group);
                }
            } else if (!$gform->is_cancelled()) {
                $data = new stdClass();
                $data->size = $DB->get_field('grouptool_agrps', 'grpsize', [
                        'groupid'     => $resize,
                        'grouptoolid' => $this->cm->instance
                ]);
                $gform->set_data($data);
                $gform->display();
                echo $OUTPUT->footer();
                die;
            }
        }

        if ($delete = optional_param('delete', 0, PARAM_INT)) {
            if (!optional_param('confirm', 0, PARAM_BOOL)) {
                // Show Confirm!
                $cancel = new moodle_url($PAGE->url);
                $continue = new moodle_url($cancel, [
                        'confirm' => 1,
                        'delete'  => $delete
                ]);
                $cancel = new single_button($cancel, get_string('no'), 'post');
                $continue = new single_button($continue,
                                              get_string('yes'), 'post');
                $confirmtext = get_string('confirm_delete', 'grouptool');
                echo $this->confirm($confirmtext, $continue, $cancel);
                echo $OUTPUT->footer();
                die;
            } else {
                // Delete it!
                groups_delete_group($delete);
            }
        }

        if ($toggle = optional_param('toggle', 0, PARAM_INT)) {
            if (!empty($toggle)) {
                $conditions = ['grouptoolid' => $this->cm->instance, 'groupid' => $toggle];
                if (!$DB->record_exists('grouptool_agrps', $conditions)) {
                    echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_found', 'grouptool'),
                            \core\output\notification::NOTIFY_ERROR), 'generalbox');
                } else {
                    $record = $DB->get_record('grouptool_agrps', $conditions);
                    if (!empty($record->active)) {
                        $DB->set_field('grouptool_agrps', 'active', 0, $conditions);
                    } else {
                        $DB->set_field('grouptool_agrps', 'active', 1, $conditions);
                    }
                }
            }
        }

        if (!$dialog || !optional_param('start_bulkaction', 0, PARAM_BOOL)) {
            // Show form!
            $formaction = new moodle_url('/mod/grouptool/view.php', [
                    'id' => $this->cm->id,
                    'tab' => 'group_admin',
                    'filter' => $filter
            ]);
            $mform = new MoodleQuickForm('bulk', 'post', $formaction, '');

            $mform->addElement('hidden', 'sesskey');
            $mform->setDefault('sesskey', sesskey());

            $sortlist = new \mod_grouptool\output\sortlist($this->course->id, $this->cm, $filter);
            $sortlistcontroller = new \mod_grouptool\output\sortlist_controller($sortlist);
            $mform->addElement('html', $output->render($sortlistcontroller));
            $mform->addElement('html', $output->render($sortlist));

            $actions = [
                '' => get_string('choose', 'grouptool'),
                'activate' => get_string('setactive', 'grouptool'),
                'deactivate' => get_string('setinactive', 'grouptool')
            ];
            if (!($this->grouptool->ifgroupdeleted === GROUPTOOL_RECREATE_GROUP)
                    && !$DB->record_exists('grouptool', ['course' => $this->cm->course,
                                                         'ifgroupdeleted' => GROUPTOOL_RECREATE_GROUP])) {
                $actions['delete'] = get_string('delete');
            }
            $actions['grouping'] = get_string('createinsertgrouping', 'grouptool');

            $grp = [];
            $grp[] =& $mform->createElement('static', 'with_selection', '', get_string('with_selection',
                    'grouptool'));
            $grp[] =& $mform->createElement('select', 'bulkaction', '', $actions);
            $grp[] =& $mform->createElement('submit', 'start_bulkaction', get_string('start',
                    'grouptool'));
            $mform->addGroup($grp, 'actiongrp', '', ' ', false);
            $mform->disable_form_change_checker();

            $mform->display();

            switch ($filter) {
                case self::FILTER_ACTIVE:
                    $curfilter = 'active';
                break;
                case self::FILTER_INACTIVE:
                    $curfilter = 'inactive';
                break;
                default:
                case self::FILTER_ALL:
                    $curfilter = 'all';
                break;
            }

            $params = ['cmid'       => $this->cm->id,
                       'filter'     => $curfilter,
                       'filterall'  => GROUPTOOL_FILTER_ALL,
                       'globalsize' => $this->grouptool->grpsize,
                       'usesize'    => (bool)$this->grouptool->use_size];
            $PAGE->requires->js_call_amd('mod_grouptool/administration', 'initializer', $params);
        }
    }

    /**
     * Outputs the content of the creation tab and manages actions taken in this tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_creation() {
        global $SESSION, $OUTPUT;

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = [];
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroups)) {
                require_capability('mod/grouptool:create_groups', $this->context);
                // Create groups!
                $data = $SESSION->grouptool->view_administration;
                $error = false;
                $preview = '';
                // Display only active users if the option was selected or they do not have the capability to view suspended users.
                $onlyactive = !empty($data->includeonlyactiveenrol)
                    || !has_capability('moodle/course:viewsuspendedusers', $context);
                list($source, $orderby) = $this->view_creation_get_source_orderby($data);
                switch ($data->mode) {
                    case GROUPTOOL_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $source, $orderby, null, $onlyactive);
                        $usercnt = count($users);
                        $numgrps    = $data->numberofgroups;
                        $userpergrp = floor($usercnt / $numgrps);
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $source, $orderby, null, $onlyactive);
                        $usercnt = count($users);
                        $numgrps    = ceil($usercnt / $data->numberofmembers);
                        $userpergrp = $data->numberofmembers;
                        if (!empty($data->nosmallgroups) and $usercnt % $data->numberofmembers != 0) {
                            /*
                             *  If there would be one group with a small number of member
                             *  reduce the number of groups
                             */
                            $missing = $userpergrp * $numgrps - $usercnt;
                            if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                                // Spread the users from the last small group!
                                $numgrps--;
                                $userpergrp = floor($usercnt / $numgrps);
                            }
                        }
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_1_PERSON_GROUPS:
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $source, 'lastname ASC, firstname ASC',
                                                    null, $onlyactive);
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $prev) = $this->create_one_person_groups($users,
                                                                              $data->namingscheme,
                                                                              $data->grouping,
                                                                              $data->groupingname,
                                                                              false,
                                                                              $data->enablegroupmessaging);
                        $preview = $prev;
                        break;
                    case GROUPTOOL_N_M_GROUPS:
                        /* Shortcut here: create_fromto_groups does exactly what we want,
                         * with from = 1 and to = number of groups to create! */
                        $data->from = 1;
                        $data->to = $data->numberofgroups;
                        $data->digits = 1;
                    case GROUPTOOL_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $preview) = $this->create_fromto_groups($data);
                        break;
                }
                if (!$error && has_capability('mod/grouptool:administrate_groups', $this->context)) {
                    $linktext = '<i class="fa fa-long-arrow-right" aria-hidden="true"></i>' .
                            get_string('group_administration', 'grouptool');
                    $urlparams = [
                            'id' => $this->cm->id,
                            'tab' => 'group_admin'
                    ];
                    $preview .= html_writer::link(new moodle_url('/mod/grouptool/view.php', $urlparams), $linktext, [
                            'class' => 'ml-1'
                    ]);
                }
                $preview = $OUTPUT->notification($preview, $error ? \core\output\notification::NOTIFY_ERROR :
                        \core\output\notification::NOTIFY_SUCCESS);
                echo $OUTPUT->box(html_writer::tag('div', $preview, ['class' => 'centered']),
                                  'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        // Create the form-object!
        $showgrpsize = $this->grouptool->use_size;
        $mform = new \mod_grouptool\group_creation_form(null, [
                'id'           => $id,
                'roles'        => $rolenames,
                'show_grpsize' => $showgrpsize
        ]);
        unset($showgrpsize);

        if ($fromform = $mform->get_data()) {
            require_capability('mod/grouptool:create_groups', $this->context);
            // Save submitted data in session and show confirmation dialog!
            if (!isset($SESSION->grouptool)) {
                $SESSION->grouptool = new stdClass();
            }
            if (!isset($SESSION->grouptool->view_administration)) {
                $SESSION->grouptool->view_administration = new stdClass();
            }
            $SESSION->grouptool->view_administration = $fromform;
            $data = $SESSION->grouptool->view_administration;
            $preview = "";
            $error = false;
            list($source, $orderby) = $this->view_creation_get_source_orderby($data);
            $onlyactive = !empty($data->includeonlyactiveenrol)
                || !has_capability('moodle/course:viewsuspendedusers', $context);
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                          $source, $orderby, null, $onlyactive);
                    $usercnt = count($users);
                    $numgrps    = clean_param($data->numberofgroups, PARAM_INT);
                    $userpergrp = floor($usercnt / $numgrps);
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                  $numgrps, true);
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                          $source, $orderby, null, $onlyactive);
                    $usercnt = count($users);
                    $numgrps    = ceil($usercnt / $data->numberofmembers);
                    $userpergrp = clean_param($data->numberofmembers, PARAM_INT);
                    if (!empty($data->nosmallgroups) and $usercnt % clean_param($data->numberofmembers, PARAM_INT) != 0) {
                        /*
                         *  If there would be one group with a small number of member
                         *  reduce the number of groups
                         */
                        $missing = $userpergrp * $numgrps - $usercnt;
                        if ($missing > $userpergrp * (1 - GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                            // Spread the users from the last small group!
                            $numgrps--;
                            $userpergrp = floor($usercnt / $numgrps);
                        }
                    }
                    list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                  $numgrps, true);
                    break;
                case GROUPTOOL_1_PERSON_GROUPS:
                    $users = groups_get_potential_members($this->course->id, $data->roleid,
                            $source, 'lastname ASC, firstname ASC', null, $onlyactive);
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $prev) = $this->create_one_person_groups($users,
                                                                          $data->namingscheme,
                                                                          $data->grouping,
                                                                          $data->groupingname,
                                                                          true,
                                                                          $data->enablegroupmessaging);
                    $preview = $prev;
                    break;
                case GROUPTOOL_N_M_GROUPS:
                    /* Shortcut here: create_fromto_groups does exactly what we want,
                     * with from = 1 and to = number of groups to create! */
                    $data->from = 1;
                    $data->to = $data->numberofgroups;
                    $data->digits = 1;
                case GROUPTOOL_FROMTO_GROUPS:
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $preview) = $this->create_fromto_groups($data, true);
                    break;
            }
            $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
            $tab = required_param('tab', PARAM_ALPHANUMEXT);
            if ($error) {
                $text = get_string('create_groups_confirm_problem', 'grouptool');
                $url = new moodle_url("view.php?id=$id&tab=".$tab);
                $back = new single_button($url, get_string('back'), 'post');
                $confirmboxcontent = $this->confirm($text, $back);
            } else {
                $continue = "view.php?id=$id&tab=".$tab."&confirm=true";
                $cancel = "view.php?id=$id&tab=".$tab;
                $text = get_string('create_groups_confirm', 'grouptool');
                $confirmboxcontent = $this->confirm($text, $continue, $cancel);
            }
            echo $OUTPUT->heading(get_string('preview'), 2, 'centered').
                 $OUTPUT->box($preview, 'generalbox').
                 $confirmboxcontent;
        } else {
            $mform->display();
        }
    }

    /**
     * returns the source of potential users and order mode
     *
     * @param object $data data of creation view
     * @return array $source array of possible sources for potential users
     * @return string $orderby sql clause for ordering the list of potential users
     * @throws moodle_exception
     */
    private function view_creation_get_source_orderby($data) {

        $source = array();
        if ($data->cohortid) {
            $source['cohortid'] = $data->cohortid;
        }
        if ($data->selectfromgrouping) {
            $source['groupingid'] = $data->selectfromgrouping;
        }
        if ($data->selectfromgroup) {
            $source['groupid'] = $data->selectfromgroup;
        }
        $orderby = "";
        switch ($data->allocateby) {
            default:
                print_error('unknoworder');
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

        return array($source, $orderby);
    }

    /**
     * returns table used in group-grading form
     *
     * TODO use templates and load via AJAX (AMD core/fragment)
     *
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroupsonly limit source-grades to those given by current user
     * @param bool $incompleteonly show only groups which have not-graded members
     * @param int $filter GROUPTOOL_FILTER_ALL => all groups
     *                    GROUPTOOL_FILTER_NONCONFLICTING => groups with exactly 1 graded member
     *                    >0 => id of single group
     * @param int[] $selected array with ids of groups/users to copy grades to as keys (depends on filter)
     * @param int[] $missingsource optional array with ids of entries for whom no source has been selected
     *                             (just to display a clue to select a source)
     * @return string HTML Fragment containing checkbox-controller and dependencies
     * @throws coding_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    private function get_grading_table($activity, $mygroupsonly, $incompleteonly, $filter, $selected, $missingsource = []) {
        global $OUTPUT, $USER, $PAGE;

        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroupsonly) {
            require_capability('mod/grouptool:grade', $this->context);
        } else if (!has_capability('mod/grouptool:grade', $this->context)) {
            /*
             * if he want's to grad his own he needs either capability to grade all
             * or to grade his own at least
             */
            require_capability('mod/grouptool:grade_own_submission', $this->context);
        }

        $grouping = optional_param('grouping', null, PARAM_INT);

        $table = new html_table();

        if ($activity == 0) {
            return $OUTPUT->box($OUTPUT->notification(get_string('chooseactivity', 'grouptool'),
                    \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
        }

        // General table settings!
        $table->attributes['class'] .= ' table table-hover grading_gradingtable';
        $tablepostfix = "";
        $tablecolumns = [];
        $tableheaders = [];
        // Determine what mode we have to interpret the selected items the right way!
        if ($filter == GROUPTOOL_FILTER_ALL || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
            // Multiple groups?
            $tablecolumns = [
                    'select',
                    'name',
                    'gradeinfo'
            ];
            $button = html_writer::tag('button', get_string('copy', 'grouptool'), [
                    'name'  => 'copygrades',
                    'type'  => 'submit',
                    'value' => 'true',
                    'class' => 'btn btn-primary'
            ]);
            $buttontext = get_string('copy_refgrades_feedback', 'grouptool');
            $tableheaders = [
                    '',
                    get_string('name'),
                    get_string('reference_grade_feedback', 'grouptool')
            ];

            $groups = groups_get_all_groups($this->course->id, 0, $grouping);
            $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);

            foreach ($groups as $group) {
                $error = "";
                $groupmembers = groups_get_members($group->id);
                // Get grading info for all group members!
                $gradinginfo = grade_get_grades($this->course->id, 'mod', $cmtouse->modname,
                                                $cmtouse->instance, array_keys($groupmembers));
                $gradeinfo = [];
                if (in_array($group->id, $missingsource)) {
                    $error = ' error';
                    $gradeinfo[] = html_writer::tag('div', get_string('missing_source_selection',
                            'grouptool'));
                }

                $userwithgrades = [];
                foreach ($groupmembers as $key => $groupmember) {
                    if (!empty($gradinginfo->items[0]->grades[$groupmember->id]->dategraded)
                        && (!$mygroupsonly
                            || $gradinginfo->items[0]->grades[$groupmember->id]->usermodified == $USER->id)) {
                        $userwithgrades[] = $key;
                    }
                }
                if ((count($userwithgrades) != 1)
                        && ($filter == GROUPTOOL_FILTER_NONCONFLICTING)) {
                    /*
                     * skip groups with more than 1 grade and groups without grade
                     * if only nonconflicting should be reviewed
                     */
                    continue;
                }
                if ((count($userwithgrades) == count($groupmembers)) && ($incompleteonly == 1)) {
                    // Skip groups fully graded if it's wished!
                    continue;
                }
                foreach ($userwithgrades as $key) {
                    $finalgrade = $gradinginfo->items[0]->grades[$key];
                    if (!empty($finalgrade->dategraded)) {
                        $grademax = $gradinginfo->items[0]->grademax;
                        $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                                                        round($grademax, 2);
                        $radioattr = [
                                'name'  => 'source['.$group->id.']',
                                'value' => $groupmembers[$key]->id,
                                'type'  => 'radio',
                                'class' => 'form-check-input'
                        ];

                        if (count($userwithgrades) == 1) {
                            $radioattr['disabled'] = 'disabled';
                            $radioattr['checked'] = 'checked';
                            $gradeinfocont = html_writer::empty_tag('input', $radioattr);
                            unset($radioattr['disabled']);
                            $radioattr['type'] = 'hidden';
                            $gradeinfocont .= html_writer::empty_tag('input', $radioattr);
                        } else if (count($userwithgrades) > 1) {
                            $gradeinfocont = html_writer::empty_tag('input', $radioattr);
                        } else {
                            $gradeinfocont = '';
                        }
                        $gradeinfocont .= ' '.fullname($groupmembers[$key])." (".$finalgrade->formatted_grade;
                        if (strip_tags($finalgrade->str_feedback) != "") {
                            $gradeinfocont .= " ".shorten_text(strip_tags($finalgrade->str_feedback), 15);
                        }
                        $gradeinfocont .= ")";
                        $label = html_writer::tag('label', $gradeinfocont, [
                                'class' => 'form-check-label gradinginfo'.
                                                                                            $groupmembers[$key]->id
                        ]);
                        $gradeinfo[] = html_writer::tag('div', $label, ['class' => 'form-check']);
                    }
                }
                $selectattr = [
                        'type' => 'checkbox',
                        'name' => 'selected[]',
                        'value' => $group->id,
                        'class' => 'form-check-input'
                ];
                $checkboxcontroller = optional_param('select', '', PARAM_ALPHA);
                if ((count($groupmembers) <= 1) || count($userwithgrades) == 0) {
                    $selectattr['disabled'] = 'disabled';
                    unset($selectattr['checked']);
                } else if ($checkboxcontroller == 'all') {
                    $selectattr['checked'] = "checked";
                } else if ($checkboxcontroller == 'none') {
                    unset($selectattr['checked']);
                } else if (isset($selected[$group->id]) && $selected[$group->id] == 1) {
                    $selectattr['checked'] = "checked";
                }
                $checkbox = html_writer::tag('label', html_writer::empty_tag('input', $selectattr),
                                             ['class' => 'form-check-label']);

                $select = new html_table_cell(html_writer::tag('div', $checkbox, ['class' => 'form-check']));
                $name = new html_table_cell($group->name);
                if (empty($gradeinfo)) {
                    $gradeinfo = new html_table_cell(get_string('no_grades_present', 'grouptool'));
                } else {
                    $gradeinfo = new html_table_cell(implode("\n", $gradeinfo));
                }

                $row = new html_table_row([$select, $name, $gradeinfo]);
                $tmpclass = $row->attributes['class'];
                $row->attributes['class'] = isset($tmpclass) ? $tmpclass.$error : $tmpclass;
                unset($tmpclass);
                $data[] = $row;
            }
            $tablepostfix = html_writer::tag('div', $buttontext, ['class' => 'center centered']);
            $tablepostfix .= html_writer::tag('div', $button, ['class' => 'centered center']);

        } else if ($filter > 0) {    // Single group?
            $tablecolumns = [
                    'select',
                    'fullname',
                    'idnumber',
                    'grade',
                    'feedback',
                    'copybutton'
            ];
            $tableheaders = [
                    get_string('target', 'grouptool'),
                    get_string('fullname'),
                    get_string('idnumber'),
                    get_string('grade', 'grades'),
                    get_string('feedback'),
                    get_string('source', 'grouptool')
            ];

            $groupmembers = groups_get_members($filter);
            // Get grading info for all groupmembers!
            $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);
            $gradinginfo = grade_get_grades($this->course->id, 'mod', $cmtouse->modname,
                                             $cmtouse->instance, array_keys($groupmembers));
            if (isset($gradinginfo->items[0])) {
                foreach ($groupmembers as $groupmember) {
                    $row = [];
                    $finalgrade = $gradinginfo->items[0]->grades[$groupmember->id];
                    $grademax = $gradinginfo->items[0]->grademax;
                    $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                                                    round($grademax, 2);
                    $checkboxcontroller = optional_param('select', '', PARAM_ALPHA);
                    if ($checkboxcontroller == 'all') {
                        $checked = true;
                    } else if ($checkboxcontroller == 'none') {
                        $checked = false;
                    } else {
                        $checked = (isset($selected[$groupmember->id])
                                    && ($selected[$groupmember->id] == 1)) ? true : false;
                    }
                    $checkbox = html_writer::tag('label', html_writer::checkbox('selected[]', $groupmember->id,
                            $checked, '', ['class' => 'checkbox form-check-element']), ['class' => 'form-check-label']);

                    $row[] = new html_table_cell(html_writer::tag('div', $checkbox, ['class' => 'form-check']));
                    $row[] = html_writer::tag('div', fullname($groupmember), ['class' => 'fullname'.$groupmember->id]);
                    $row[] = html_writer::tag('div', $groupmember->idnumber, ['class' => 'idnumber'.$groupmember->id]);
                    $row[] = html_writer::tag('div', $finalgrade->formatted_grade, ['class' => 'grade'.$groupmember->id]);
                    $row[] = html_writer::tag('div', shorten_text(strip_tags($finalgrade->str_feedback), 15),
                                              ['class' => 'feedback'.$groupmember->id]);
                    if ($mygroupsonly && ($finalgrade->usermodified != $USER->id)) {
                        $row[] = html_writer::tag('div', get_string('not_graded_by_me', 'grouptool'));
                    } else {
                        $row[] = html_writer::tag('button',
                                                  get_string('copygrade', 'grouptool'),
                                                  [
                                                          'type'  => 'submit',
                                                          'name'  => 'source',
                                                          'value' => $groupmember->id,
                                                          'class' => 'btn btn-primary'
                                                  ]);
                    }
                    $data[] = $row;
                }
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_grades_present', 'grouptool'),
                        \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
            }
        } else {
            print_error('uknown filter-value');
        }

        if (empty($data)) {
            if ($filter == GROUPTOOL_FILTER_ALL) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_data_to_display', 'grouptool'),
                        \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
            } else if ($filter == GROUPTOOL_FILTER_NONCONFLICTING) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_conflictfree_to_display', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR), 'centered').
                $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_groupmembers_to_display', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR), 'centered').
                $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            }
        }

        $table->colclasses = $tablecolumns;
        // Instead of the strings an array of html_table_cells can be set as head!
        $table->head = $tableheaders;
        // Instead of the strings an array of html_table_cells can be used for the rows!
        $table->data = $data;
        $overwrite = optional_param('overwrite', 0, PARAM_BOOL);
        $grouping = optional_param('grouping', 0, PARAM_INT);
        $baseurl = new \moodle_url($PAGE->url, [
                'activity' => $activity,
                'mygroups_only' => $mygroupsonly,
                'incomplete_only' => $incompleteonly,
                'filter' => $filter,
                'overwrite' => $overwrite,
                'grouping' => $grouping
        ]);
        $selectallurl = new \moodle_url($baseurl, ['select' => 'all']);
        $selectnoneurl = new \moodle_url($baseurl, ['select' => 'none']);
        $links = get_string('select').' '.
                 \html_writer::link($selectallurl, get_string('all'), ['class' => 'select_all']).'/'.
                 \html_writer::link($selectnoneurl, get_string('none'), ['class' => 'select_none']);
        $checkboxcontroller = html_writer::tag('div', $links, ['class' => 'checkboxcontroller']);

        return $checkboxcontroller.html_writer::table($table).$tablepostfix;
    }

    /**
     * copies the grades from the source(s) to the target(s) for the selected activity
     *
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroupsonly limit source-grades to those given by current user
     * @param int[] $selected array with ids of groups/users to copy grades to as keys (depends on filter)
     * @param int[] $source optional array with ids of entries for whom no source has been selected
     *                      (just to display a clue to select a source)
     * @param bool $overwrite optional overwrite existing grades (std: false)
     * @param bool $previewonly optional just return preview data
     * @return array ($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    private function copy_grades($activity, $mygroupsonly, $selected, $source, $overwrite = false,
                                 $previewonly = false) {
        global $DB, $USER;
        $error = false;
        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroupsonly) {
            require_capability('mod/grouptool:grade', $this->context);
        } else if (!has_capability('mod/grouptool:grade', $this->context)) {
            /*
             * if he wants to grade his own (=submissions where he graded at least 1 group member)
             * he needs either capability to grade all or to grade his own at least
             */
            require_capability('mod/grouptool:grade_own_submission', $this->context);
        }

        $cmtouse = get_coursemodule_from_id('', $activity, $this->course->id);
        if (!$cmtouse) {
            return [true, get_string('couremodule_misconfigured')];
        }
        if ($previewonly) {
            $previewtable = new html_table();
            $previewtable->attributes['class'] = 'table table-hover grading_previewtable';
        } else {
            $previewtable = new stdClass();
        }
        $info = "";

        $gradeitems = grade_item::fetch_all([
                'itemtype'     => 'mod',
                'itemmodule'   => $cmtouse->modname,
                'iteminstance' => $cmtouse->instance
        ]);
        // TODO #3310 should we support multiple grade items per activity module soon?

        do {
            // Right now, we just work with the first grade item!
            $gradeitem = current($gradeitems);
        } while (!empty($gradeitem->itemnumber) && next($gradeitems));

        if (is_array($source)) { // Then we are in multigroup mode (filter = 0 || -1)!
            $sourceusers = $DB->get_records_list('user', 'id', $source);
            $groups = groups_get_all_groups($this->course->id);

            $previewtable->head = [
                    get_string('groups')." (".count($selected).")",
                    get_string('fullname'),
                    get_string('grade', 'grades'),
                    get_string('feedback')
            ];
            foreach ($selected as $group) {
                $groupinfo = "";
                $grouprows = [];

                $sourcegroup = is_array($source[$group]) ? $source[$group] : [$source[$group]];
                $sourcegrade = grade_grade::fetch_users_grades($gradeitem, $sourcegroup,
                                                                false);
                $sourcegrade = reset($sourcegrade);
                $sourcegrade->load_optional_fields();
                $origteacher = $DB->get_record('user', ['id' => $sourcegrade->usermodified]);
                $formattedgrade = round($sourcegrade->finalgrade, 2) .' / ' .
                                  round($gradeitem->grademax, 2);

                $groupmembers = groups_get_members($group);
                $targetgrades = grade_grade::fetch_users_grades($gradeitem,
                                                                 array_keys($groupmembers), true);
                $propertiestocopy = ['rawgrade', 'finalgrade', 'feedback', 'feedbackformat'];

                foreach ($targetgrades as $currentgrade) {

                    if ($currentgrade->id == $sourcegrade->id) {
                        continue;
                    }
                    if (!$overwrite && ($currentgrade->finalgrade != null)) {
                        if ($previewonly) {
                            $rowcells = [];
                            if (empty($grouprows)) {
                                $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                                        html_writer::empty_tag('br').
                                        "(".(count($groupmembers) - 1).")");
                            }
                            $fullname = fullname($groupmembers[$currentgrade->userid]);
                            $rowcells[] = new html_table_cell($fullname);
                            $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                            $cell->colspan = 2;
                            $rowcells[] = $cell;
                            $row = new html_table_row();
                            $row->cells = $rowcells;
                            if (empty($grouprows)) {
                                $row->attributes['class'] .= ' firstgrouprow';
                            }
                            $grouprows[] = $row;
                        }
                        continue;
                    }
                    $currentgrade->load_optional_fields();
                    foreach ($propertiestocopy as $property) {
                        $currentgrade->$property = $sourcegrade->$property;
                    }
                    $details = [
                            'student'  => fullname($sourceusers[$source[$group]]),
                            'teacher'  => fullname($origteacher),
                            'date'     => userdate($sourcegrade->get_dategraded(),
                                                            get_string('strftimedatetimeshort')),
                            'feedback' => $sourcegrade->feedback
                    ];
                    $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                                                                      'grouptool',
                                                                      $details),
                                                           $currentgrade->feedbackformat);
                    $currentgrade->usermodified = $USER->id;
                    if ($previewonly) {
                        $rowcells = [];
                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                                    html_writer::empty_tag('br').
                                    "(".count($groupmembers).")");
                        }
                        $fullname = fullname($groupmembers[$currentgrade->userid]);
                        $rowcells[] = new html_table_cell($fullname);
                        $rowcells[] = new html_table_cell($formattedgrade);
                        $rowcells[] = new html_table_cell($currentgrade->feedback);
                        $row = new html_table_row();
                        $row->cells = $rowcells;
                        if (empty($grouprows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $grouprows[] = $row;
                    } else {
                        if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                            $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                            $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                        }
                        if ($currentgrade->id) {
                            $noerror = $currentgrade->update();
                        } else {
                            $noerror = $currentgrade->insert();
                        }
                        $currentgrade->set_overridden(true, false);
                        $currentgrade->grade_item->force_regrading();
                        $fullname = fullname($groupmembers[$currentgrade->userid]);
                        if ($noerror) {
                            $groupinfo .= html_writer::tag('span',
                                                           '✓&nbsp;'.$fullname.
                                                           " (".$formattedgrade.")",
                                                           ['class' => 'notifysuccess']);
                        } else {
                            $error = true;
                            $groupinfo .= html_writer::tag('span',
                                                           '✗&nbsp;'.$fullname.
                                                           " (".$formattedgrade.")",
                                                           ['class' => 'notifyproblem']);
                        }
                    }
                }
                if ($previewonly) {
                    $grouprows[0]->cells[0]->rowspan = count($grouprows);
                    if (!is_array($previewtable->data)) {
                        $previewtable->data = [];
                    }
                    $previewtable->data = array_merge($previewtable->data, $grouprows);
                } else {
                    $grpinfo = "";
                    $grpinfo .= html_writer::tag('div', $groups[$group]->name." (".
                                                        count($groupmembers)."): ".$groupinfo);
                    $data = [
                            'student' => fullname($sourceusers[$source[$group]]),
                            'teacher' => fullname($origteacher),
                            'date'    => userdate($sourcegrade->get_dategraded(),
                                                        get_string('strftimedatetimeshort')),
                            'feedback' => $sourcegrade->feedback
                    ];
                    $temp = get_string('copied_grade_feedback', 'grouptool', $data);
                    $grpinfo .= html_writer::tag('div', $formattedgrade.html_writer::empty_tag('br').
                                                        format_text($temp,
                                                                    $sourcegrade->feedbackformat));
                    $info .= html_writer::tag('div', $grpinfo, ['class' => 'box1embottom']);
                    // Trigger the event!
                    $logdata = new stdClass();
                    $logdata->groupid = $group;
                    $logdata->cmtouse = $cmtouse->id;
                    \mod_grouptool\event\group_graded::create_direct($this->cm, $logdata)->trigger();
                }
            }
        } else {
            $sourceuser = $DB->get_record('user', ['id' => $source]);
            $targetusers = $DB->get_records_list('user', 'id', $selected);
            $sourcegrade = grade_grade::fetch_users_grades($gradeitem, [$source], false);
            $sourcegrade = reset($sourcegrade);
            $origteacher = $DB->get_record('user', ['id' => $sourcegrade->usermodified]);
            $formattedgrade = round($sourcegrade->finalgrade, 2).' / ' .
                               round($gradeitem->grademax, 2);
            $targetgrades = grade_grade::fetch_users_grades($gradeitem, $selected, true);
            $propertiestocopy = ['rawgrade', 'finalgrade', 'feedback', 'feedbackformat'];
            $nameinfo = "";
            $grouprows = [];
            if ($previewonly) {
                $count = in_array($source, $selected) ? count($selected) - 1 : count($selected);
                $previewtable->head = [
                        '', get_string('fullname')." (".$count.")",
                        get_string('grade', 'grades'), get_string('feedback')
                ];
                $previewtable->attributes['class'] = 'table table-hover grading_previewtable';
            } else {
                $info .= html_writer::start_tag('div');
            }

            foreach ($targetgrades as $currentgrade) {
                if ($currentgrade->id == $sourcegrade->id) {
                    continue;
                }
                if (!$overwrite && ($currentgrade->rawgrade != null)) {
                    if ($previewonly) {
                        $rowcells = [];
                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell(get_string('users'));
                        }
                        $fullname = fullname($targetusers[$currentgrade->userid]);
                        $rowcells[] = new html_table_cell($fullname);
                        $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                        $cell->colspan = 2;
                        $rowcells[] = $cell;
                        $row = new html_table_row();
                        $row->cells = $rowcells;
                        if (empty($grouprows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $grouprows[] = $row;
                    }
                    continue;
                }
                $currentgrade->load_optional_fields();
                foreach ($propertiestocopy as $property) {
                    $currentgrade->$property = $sourcegrade->$property;
                }

                $details = [
                        'student' => fullname($sourceuser),
                        'teacher' => fullname($origteacher),
                        'date' => userdate($sourcegrade->get_dategraded(),
                                                       get_string('strftimedatetimeshort')),
                        'feedback' => $sourcegrade->feedback
                ];
                $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                                                                  'grouptool',
                                                                  $details),
                                                       $currentgrade->feedbackformat);
                $currentgrade->usermodified   = $USER->id;
                if ($previewonly) {
                    $rowcells = [];
                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell(get_string('users'));
                    }
                    $fullname = fullname($targetusers[$currentgrade->userid]);
                    $rowcells[] = new html_table_cell($fullname);
                    $rowcells[] = new html_table_cell($formattedgrade);
                    $rowcells[] = new html_table_cell(format_text($currentgrade->feedback,
                                                                   $currentgrade->feedbackformat));
                    $row = new html_table_row();
                    $row->cells = $rowcells;
                    if (empty($grouprows)) {
                        $row->attributes['class'] .= ' firstgrouprow';
                    }
                    $grouprows[] = $row;
                } else {
                    if ($nameinfo != "") {
                        $nameinfo .= ", ";
                    }
                    if ($currentgrade->id) {
                        $noerror = $currentgrade->update();
                    } else {
                        $noerror = $currentgrade->insert();
                    }
                    $currentgrade->set_overridden(true, false);
                    $currentgrade->grade_item->force_regrading();
                    $fullname = fullname($targetusers[$currentgrade->userid]);
                    if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                        $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                        $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                    }
                    if ($noerror) {
                        $nameinfo .= html_writer::tag('span',
                                                       '✓&nbsp;'.$fullname,
                                                       ['class' => 'notifysuccess']);
                    } else {
                        $error = true;
                        $nameinfo .= html_writer::tag('span',
                                                       '✗&nbsp;'.$fullname,
                                                       ['class' => 'notifyproblem']);
                    }
                }
            }
            if ($previewonly) {
                $grouprows[0]->cells[0]->rowspan = count($grouprows);
                $previewtable->data = $grouprows;
            } else {
                $info .= $nameinfo.html_writer::end_tag('div');
                $details = [
                        'student' => fullname($sourceuser),
                        'teacher' => fullname($origteacher),
                        'date' => userdate($sourcegrade->get_dategraded(),
                                                    get_string('strftimedatetimeshort')),
                        'feedback' => $sourcegrade->feedback
                ];
                $info .= html_writer::tag('div', get_string('grade', 'grades').": ".
                                        $formattedgrade.html_writer::empty_tag('br').
                                        format_text(get_string('copied_grade_feedback', 'grouptool',
                                                               $details),
                                                    $sourcegrade->feedbackformat),
                                                    ['class' => 'gradeinfo']);
            }
            if (!$previewonly) {
                // Trigger the event!
                $logdata = new stdClass();
                $logdata->source = $source;
                $logdata->selected = $selected;
                $logdata->cmtouse = $cmtouse->id;
                \mod_grouptool\event\group_graded::create_without_groupid($this->cm, $logdata)->trigger();
            }
        }
        if ($previewonly) {
            return [
                    $error, html_writer::tag('div', html_writer::table($previewtable),
                                                  ['class' => 'centeredblock'])
            ];
        } else {
            return [$error, html_writer::tag('div', $info, ['class' => 'centeredblock'])];
        }
    }

    /**
     * view grading-tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_grading() {
        global $PAGE, $OUTPUT, $USER;

        if (!has_capability('mod/grouptool:grade', $this->context)
                && !has_capability('mod/groputool:grade_own_groups', $this->context)) {
            print_error('nopermissions');
            return;
        }

        $refreshtable = optional_param('refresh_table', 0, PARAM_BOOL);
        $activity = optional_param('activity', null, PARAM_INT); // This is the coursemodule-ID.

        // Show only groups with grades given by current user!
        $mygroupsonly = optional_param('mygroups_only', null, PARAM_BOOL);

        if (!has_capability('mod/grouptool:grade', $this->context)) {
            $mygroupsonly = 1;
        }

        if ($mygroupsonly != null) {
            set_user_preference('mod_grouptool_mygroups_only', $mygroupsonly, $USER->id);
        }

        // Show only groups with missing grades (groups with at least 1 not-graded member)!
        $incompleteonly = optional_param('incomplete_only', 0, PARAM_BOOL);

        $overwrite = optional_param('overwrite', 0, PARAM_BOOL);

        // Here -1 = nonconflicting, 0 = all     or groupid for certain group!
        $filter = optional_param('filter', GROUPTOOL_FILTER_NONCONFLICTING, PARAM_INT);
        // Steps: 0 = show, 1 = confirm, 2 = action!
        $step = optional_param('step', 0, PARAM_INT);
        if ($refreshtable) { // If it was just a refresh, reset step!
            $step = 0;
        }

        $grouping = optional_param('grouping', 0, PARAM_INT);

        if ($filter > 0) {
            if ($step == 2) {
                $source = optional_param('source', null, PARAM_INT);
                // Serialized data TODO: better PARAM_TYPE?
                $selected = optional_param('selected', null, PARAM_RAW);
                if (!empty($selected)) {
                    $selected = unserialize($selected);
                }
            } else {
                if ($refreshtable) {
                    // Otherwise we get problems here, if we refresh and change from multiple groups to a single group!
                    $source = null;
                } else {
                    $source = optional_param('source', null, PARAM_INT);
                }
                $selected = optional_param_array('selected', null, PARAM_INT);
                if (!empty($source) && !$refreshtable) {
                    $step = 1;
                }
            }
        } else {
            if ($step == 2) {
                $source = optional_param('source', null, PARAM_RAW);
                if (!empty($source)) {
                    $source = unserialize($source);
                }
                $selected = optional_param('selected', null, PARAM_RAW);
                if (!empty($selected)) {
                    $selected = unserialize($selected);
                }
            } else {
                $source = optional_param_array('source', [], PARAM_INT);
                $selected = optional_param_array('selected', [], PARAM_INT);
                $copygroups = optional_param('copygrades', 0, PARAM_BOOL);
                if ($copygroups && !$refreshtable) {
                    $step = 1;
                }
            }
        }
        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        if (($step == 2) && !$confirm) {
            $step = 0;
        }

        // Reset process if some evil hacker tried to do smth!
        if (!$confirm && (!data_submitted() || !confirm_sesskey())) {
            $step = 0;
        }

        if (!empty($mygroupsonly)) {
            $mygroupsonly = get_user_preferences('mod_grouptool_mygroups_only', 1, $USER->id);
        }

        $missingsource = [];

        if ($step == 1) {    // Show confirm message!

            if ($filter > 0) {
                // Single group mode!
                if (is_array($selected) && in_array($source, $selected)) {
                    foreach ($selected as $key => $cmp) {
                        if ($cmp == $source) {
                            unset($selected[$key]);
                        }
                    }
                }
                if (!empty($selected)) {
                    list(, $preview) = $this->copy_grades($activity, $mygroupsonly,
                                                          $selected, $source, $overwrite,
                                                          true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id, [
                            'tab'           => 'grading',
                            'confirm'       => 'true',
                            'sesskey'       => sesskey(),
                            'step'          => '2',
                            'activity'      => $activity,
                            'mygroups_only' => $mygroupsonly,
                            'overwrite'     => $overwrite,
                            'selected'      => serialize($selected),
                            'source'        => serialize($source)
                    ]);
                    $cancel = new moodle_url("view.php?id=".$this->cm->id, [
                            'tab'           => 'grading',
                            'confirm'       => 'false',
                            'sesskey'       => sesskey(),
                            'step'          => '2',
                            'activity'      => $activity,
                            'mygroups_only' => $mygroupsonly,
                            'overwrite'     => $overwrite,
                            'selected'      => serialize($selected),
                            'source'        => serialize($source)
                    ]);
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm', 'grouptool'),
                                        $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm', 'grouptool'), $continue,
                                        $cancel);
                    }
                } else {
                    $boxcontent = $OUTPUT->notification(get_string('no_target_selected', 'grouptool'),
                            \core\output\notification::NOTIFY_ERROR);
                    echo $OUTPUT->box($boxcontent, 'generalbox');
                    unset($boxcontent);
                    $step = 0;
                }

            } else if ($filter == GROUPTOOL_FILTER_ALL
                       || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
                // All or nonconflicting mode?
                foreach ($selected as $key => $grp) {
                    // If no grade is choosen add this group to missing-source-list!
                    if (empty($source[$grp])) {
                        $missingsource[] = $grp;
                    }
                }

                if (!empty($selected) && (count($missingsource) == 0)) {
                    list(, $preview) = $this->copy_grades($activity, $mygroupsonly,
                                                          $selected, $source, $overwrite,
                                                          true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id, [
                            'tab'           => 'grading',
                            'confirm'       => 'true',
                            'sesskey'       => sesskey(),
                            'activity'      => $activity,
                            'mygroups_only' => $mygroupsonly,
                            'overwrite'     => $overwrite,
                            'step'          => '2',
                            'selected'      => serialize($selected),
                            'source'        => serialize($source)
                    ]);
                    $cancel = new moodle_url("view.php?id=".$this->cm->id, [
                            'tab' => 'grading',
                            'confirm'       => 'false',
                            'sesskey'       => sesskey(),
                            'activity'      => $activity,
                            'mygroups_only' => $mygroupsonly,
                            'overwrite'     => $overwrite,
                            'step'          => '2',
                            'selected'      => serialize($selected),
                            'source'        => serialize($source)
                    ]);
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm', 'grouptool'),
                                        $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm', 'grouptool'), $continue,
                                        $cancel);
                    }
                } else {
                    if (empty($selected)) {
                        $boxcontent = $OUTPUT->notification(get_string('no_target_selected', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR);
                        echo $OUTPUT->box($boxcontent, 'generalbox');
                        unset($boxcontent);
                        $step = 0;
                    }
                    if (count($missingsource) != 0) {
                        $boxcontent = $OUTPUT->notification(get_string('sources_missing', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR);
                        echo $OUTPUT->box($boxcontent, 'generalbox');
                        unset($boxcontent);
                        $step = 0;
                    }
                }
            } else {
                print_error('wrong parameter');
            }
        }

        if ($step == 2) {    // Do action and continue with showing the form!
            // if there was an error?
            list($error, $info) = $this->copy_grades($activity, $mygroupsonly, $selected, $source,
                                                     $overwrite);
            if ($error) {
                $boxcontent = $OUTPUT->notification(get_string('copy_grades_errors', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR).$info;
                echo $OUTPUT->box($boxcontent, 'generalbox tumargin');
                unset($boxcontent);
            } else {
                $boxcontent = $OUTPUT->notification(get_string('copy_grades_success', 'grouptool'),
                                \core\output\notification::NOTIFY_SUCCESS).$info;
                echo $OUTPUT->box($boxcontent, 'generalbox tumargin');
                unset($boxcontent);
            }
        }

        if ($step != 1 || count($missingsource)) {    // Show form if step is either 0 or 2!

            // Prepare form content!
            if ($filter > 0) {
                $table = $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                    $filter, $selected);
            } else {
                $table = $this->get_grading_table($activity, $mygroupsonly, $incompleteonly,
                    $filter, $selected, $missingsource);
            }

            $formdata = ['id'             => $this->cm->id,
                         'course'         => $this->course,
                         'mygroupsonly'   => $mygroupsonly,
                         'incompleteonly' => $incompleteonly,
                         'overwrite'      => $overwrite,
                         'grouping'       => $grouping,
                         'filter'         => $filter,
                         'table'          => $table];
            $mform = new \mod_grouptool\grading_form($PAGE->url, $formdata, 'post', '', ['class' => 'mform',
                                                                                         'id'    => 'grading_form',
                                                                                         'name'  => 'grading_form']);

            $params = new stdClass();
            $params->lang = current_language();
            $params->contextid  = $this->context->id;
            $PAGE->requires->js_call_amd('mod_grouptool/grading', 'initializer', [$params]);

            $mform->display();
        }

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
     * @throws required_capability_exception
     */
    public function get_active_groups($includeregs=false, $includequeues=false, $agrpid=0, $groupid=0, $groupingid=0,
            $indexbygroup=true, $includeinactive = false, $ignoregtinstance = false) {
        global $DB;

        require_capability('mod/grouptool:view_groups', $this->context);

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
            if (false) {
                $sizesql = " ".$this->grouptool->grpsize." grpsize,";
            } else {
                $grouptoolgrpsize = get_config('mod_grouptool', 'grpsize');
                $grpsize = (!empty($this->grouptool->grpsize) ? $this->grouptool->grpsize : $grouptoolgrpsize);
                if (empty($grpsize)) {
                    $grpsize = 3;
                }
                $sizesql = " COALESCE(agrp.grpsize, ".$grpsize.") AS grpsize,";
            }
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

        $groupdata = null;
        if ($ignoregtinstance) {
            $groupdata = $DB->get_records_sql("
                   SELECT ".$idstring.", MAX(grp.name) AS name, MAX(grp.description) AS description,".$sizesql." MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE 1=1".$active.
                    $agrpidwhere.$groupidwhere.$groupingidwhere."
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        } else {
            $params['grouptoolid1'] = $params['grouptoolid'];
            $groupdata = $DB->get_records_sql("
                   SELECT ".$idstring.", MAX(grp.name) AS name, MAX(grp.description) AS description,".$sizesql." MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id AND agrp.grouptoolid = :grouptoolid
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE agrp.grouptoolid = :grouptoolid1 ".$active.
                    $agrpidwhere.$groupidwhere.$groupingidwhere."
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        }
        if (!empty($groupdata)) {
            foreach ($groupdata as $key => $group) {
                $groupingids = $DB->get_fieldset_select('groupings_groups',
                                                        'groupingid',
                                                        'groupid = ?',
                                                        [$group->id]);
                if (!empty($groupingids)) {
                    $groupdata[$key]->classes = implode(',', $groupingids);
                } else {
                    $groupdata[$key]->classes = '';
                }
            }

            if ((!empty($this->grouptool->use_size))
                    || ($this->grouptool->use_queue && $includequeues)
                    || ($includeregs)) {
                $keys = array_keys($groupdata);
                foreach ($keys as $key) {
                    $groupdata[$key]->queued = null;
                    if ($includequeues && $this->grouptool->use_queue) {
                        $attr = ['agrpid' => $groupdata[$key]->agrpid];
                        $groupdata[$key]->queued = (array)$DB->get_records('grouptool_queued', $attr);
                    }

                    $groupdata[$key]->registered = null;
                    if ($includeregs) {
                        $params = ['agrpid' => $groupdata[$key]->agrpid];
                        $where = "agrpid = :agrpid AND modified_by >= 0";
                        $groupdata[$key]->registered = $DB->get_records_select('grouptool_registered',
                                                                               $where, $params);
                        $params['modifierid'] = -1;
                        $where = "agrpid = :agrpid AND modified_by = :modifierid";
                        $groupdata[$key]->marked = $DB->get_records_select('grouptool_registered',
                                                                           $where, $params);
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
     * Fills the group as much as possible with entries from the queue.
     * Usefull for group size changes or if someone is removed from the group or unregisters him-/herself
     *
     * @param int $agrpid Active group to fill
     * @return bool true if everything went fine!
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function fill_from_queue($agrpid) {
        global $DB, $CFG, $OUTPUT;

        if (empty($this->grouptool->use_queue)) {
            return true;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);

        if (empty($groupdata->queued)) {
            return true;
        }

        $agrpids = $DB->get_fieldset_sql('SELECT id
                                            FROM {grouptool_agrps}
                                           WHERE grouptoolid = ?', [$this->grouptool->id]);
        list($agrpssql, $agrpsparam) = $DB->get_in_or_equal($agrpids);
        $sql = "SELECT queued.id, MAX(agrp.groupid) AS groupid, MAX(queued.agrpid) AS agrpid,
                       MAX(queued.userid) AS userid, MAX(queued.timestamp) AS timestamp,
                       (COUNT(DISTINCT reg.id) < ?) AS priority
                  FROM {grouptool_queued} queued
             LEFT JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
             LEFT JOIN {grouptool_registered} reg ON queued.userid = reg.userid
                                                     AND reg.agrpid ".$agrpssql."
                 WHERE queued.agrpid = ?
              GROUP BY queued.id
              ORDER BY priority DESC, queued.timestamp ASC";
        $params = array_merge([$this->grouptool->choose_min], $agrpsparam, [$agrpid]);
        $records = $DB->get_records_sql($sql, $params);

        if (empty($records) || count($records) == 0) {
            return true;
        }

        $message = new stdClass();
        $message->groupname = $groupdata->name;

        foreach ($records as $record) {
            if (!empty($this->grouptool->use_size) && ($groupdata->grpsize <= count($groupdata->registered))) {
                return true;
            }
            $newrecord = clone $record;
            unset($newrecord->id);
            $newrecord->modified_by = $newrecord->userid;
            $newrecord->id = $DB->insert_record('grouptool_registered', $newrecord);
            $groupdata->registered[] = $newrecord;
            if (!empty($this->grouptool->immediate_reg)) {
                groups_add_member($groupdata->id, $newrecord->userid);
            }
            $allowm = $this->grouptool->allow_multiple;
            $usrregcnt = $this->get_user_reg_count($newrecord->userid);
            $max = $this->grouptool->choose_max;
            if (($allowm && ( $usrregcnt >= $max) ) || !$allowm) {
                $agrps = $this->get_active_groups(false, false, 0, 0, 0,
                        false);
                $agrpids = array_keys($agrps);
                list($sql, $params) = $DB->get_in_or_equal($agrpids);
                $records = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                                   FROM {grouptool_queued} queued
                                                   JOIN {grouptool_agrps} agrp ON queued.agrpid = agrp.id
                                                  WHERE userid = ? AND agrpid ".$sql,
                                                array_merge([$newrecord->userid], $params));
                $DB->delete_records_select('grouptool_queued',
                                           ' userid = ? AND agrpid '.$sql,
                                           array_merge([$newrecord->userid],
                                                       $params));
                foreach ($records as $cur) {
                    // Trigger the event!
                    \mod_grouptool\event\queue_entry_deleted::create_limit_violation($this->cm, $cur)->trigger();
                }
            }

            $context = (object)[
                    'course' => $this->course,
                    'courseurl' => $CFG->wwwroot."/course/view.php?id=".$this->course->id,
                    'coursegrouptoolsurl' => $CFG->wwwroot."/mod/grouptool/index.php?id=".$this->course->id,
                    'grouptoolurl' => $CFG->wwwroot."/mod/grouptool/view.php?id=".$this->cm->id,
                    'grouptoolname' => format_string($this->grouptool->name, true),
                    'groupname' => $groupdata->name,
                    'message' => get_string('register_you_in_group_success', 'grouptool', (object)[
                        'groupname' => $groupdata->name
                    ]),
            ];

            $postsubject = $this->course->shortname.': '.get_string('modulenameplural', 'grouptool').': '.
                    format_string($this->grouptool->name, true);

            $messageuser = $DB->get_record('user', ['id' => $newrecord->userid]);
            $moodlemessage = new \core\message\message();
            $userfrom = core_user::get_noreply_user();
            $moodlemessage->component         = 'mod_grouptool';
            $moodlemessage->name              = 'grouptool_moveupreg';
            $moodlemessage->courseid          = $this->course->id;
            $moodlemessage->userfrom          = $userfrom;
            $moodlemessage->userto            = $messageuser;
            $moodlemessage->subject           = $postsubject;
            $moodlemessage->fullmessage       = get_string('registrationnotification', 'mod_grouptool',
                    $context);
            $moodlemessage->fullmessageformat = FORMAT_HTML;
            $moodlemessage->fullmessagehtml   = $OUTPUT->render_from_template('mod_grouptool/registrationnotification', $context);
            $moodlemessage->smallmessage      = $context->message;
            $moodlemessage->notification      = 1;
            $moodlemessage->contexturl        = $CFG->wwwroot.'/mod/grouptool/view.php?id='.$this->cm->id;
            $moodlemessage->contexturlname    = $this->grouptool->name;

            message_send($moodlemessage);
            $DB->delete_records('grouptool_queued', ['id' => $record->id]);

            // Trigger the event!
            // We fetched groupid above in SQL.
            \mod_grouptool\event\user_moved::promotion_from_queue($this->cm, $record, $newrecord)->trigger();
        }

        return true;
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
     * @throws \mod_grouptool\local\exception\notenoughregs If the user hasn't enough registrations!
     * @throws \mod_grouptool\local\exception\registration In any other case, where the user can't be unregistered!
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function unregister_from_agrp($agrpid, $userid=0, $previewonly=false, $force=false, $ignoregtinstance=false) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allow_reg
                    && (($this->grouptool->timedue == 0)
                         || (time() < $this->grouptool->timedue))
                    && ($this->grouptool->timeavailable < time()));

        if (!$force && !$regopen && !has_capability('mod/grouptool:register_students', $this->context)) {
            throw new \mod_grouptool\local\exception\registration('reg_not_open');
        }

        if (!$force && empty($this->grouptool->allow_unreg)) {
            throw new \mod_grouptool\local\exception\registration('unreg_not_allowed');
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', ['id' => $userid]);
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid, 0,
                0, true, true, $ignoregtinstance);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        $message->groupname = $groupdata->name;
        $message->userid = $userid;
        $agrpids = null;
        if ($ignoregtinstance) {
            $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', '');
        } else {
            $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ?", [$this->grouptool->id]);

        }
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('grouptool_registered',
                "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql, $params);
        $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($userregs + $userqueues <= $min) {
            if ($userid == $USER->id) {
                $text = 'you_have_too_less_regs';
            } else {
                $text = 'user_has_too_less_regs';
            }

            // Throw notenoughregs exception with custom description text!
            throw new \mod_grouptool\local\exception\notenoughregs($text, $message);
        }

        if ($this->get_rank_in_queue($groupdata->registered, $userid) != false) {
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
                    'userid' => $userid
            ]);
            $DB->delete_records('grouptool_registered', [
                    'agrpid' => $agrpid,
                    'userid' => $userid
            ]);
            if (!$force && !empty($this->grouptool->immediate_reg)) {
                groups_remove_member($groupdata->id, $userid);
            }
            foreach ($records as $data) {
                // Trigger the event!
                $data->groupid = $groupdata->id;
                \mod_grouptool\event\registration_deleted::create_direct($this->cm, $data)->trigger();
            }
            // Get next queued user and put him in the group (and delete queue entry)!
            if (!empty($this->grouptool->use_queue) && !empty($groupdata->queued)) {
                $this->fill_from_queue($agrpid);
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
        if ($this->get_rank_in_queue($groupdata->queued, $userid) != false) {
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
                    'userid' => $userid
            ]);
            foreach ($records as $cur) {
                // Trigger the Event!
                $cur->groupid = $groupdata->id;
                \mod_grouptool\event\queue_entry_deleted::create_direct($this->cm, $cur)->trigger();
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

        throw new \mod_grouptool\local\exception\registration($text);
    }

    /**
     * registers/queues a user in a certain active-group
     *
     * @param int $agrpid active-group-id to register/queue user to
     * @param int $userid user to register/queue
     * @param bool $previewonly optional don't act, just return a preview
     * @return string status message
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \mod_grouptool\local\exception\registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function register_in_agrp($agrpid, $userid=0, $previewonly=false) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $regopen = ($this->grouptool->allow_reg
                    && (($this->grouptool->timedue == 0)
                        || (time() < $this->grouptool->timedue))
                    && ($this->grouptool->timeavailable <= time()));

        if (!$regopen && !has_capability('mod/grouptool:register_students', $this->context)) {
            throw new \mod_grouptool\local\exception\registration('reg_not_open');
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', ['id' => $userid]);
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = current($groupdata);

        $message->groupname = $groupdata->name;
        $message->userid = $userid;

        if ($this->qualifies_for_groupchange($agrpid, $userid)) {
            if ($previewonly) {
                $return = $this->can_change_group($agrpid, $userid, $message);
            } else {
                $return = $this->change_group($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $this->convert_marks_to_regs($userid);
            }

            return $return;
        }

        try {
            // First we try to register the user!
            if ($previewonly) {
                $return = $this->can_be_registered($agrpid, $userid, $message);
            } else {
                $return = $this->add_registration($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $this->convert_marks_to_regs($userid);
                // Update completion state if submission is changed.
                $completion = new completion_info($this->course);
                if ($completion->is_enabled($this->cm) && $this->grouptool->completionregister) {
                    $completion->update_state($this->cm, COMPLETION_COMPLETE);
                }
            }

            return $return;
        } catch (\mod_grouptool\local\exception\exceedgroupsize $e) {
            if (!$this->grouptool->use_queue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            try {
                if ($previewonly) {
                    $return = $this->can_be_queued($agrpid, $userid, $message);
                } else {
                    $return = $this->add_queue_entry($agrpid, $userid, $message);
                    // If we can queue, we have to convert the other marks to registrations & queue entries!
                    $this->convert_marks_to_regs($userid);
                }

                return $return;
            } catch (\mod_grouptool\local\exception\notenoughregs $e) {
                /* The user has not enough registrations, queue entries or marks,
                 * so we try to mark the user! (Exceptions get handled above!) */
                if ($previewonly) {
                    list(, $return) = $this->can_be_marked($agrpid, $userid, $message);
                } else {
                    $return = $this->mark_for_reg($agrpid, $userid, $message);
                }

                return $return;
            }
        } catch (\mod_grouptool\local\exception\notenoughregs $e) {
            /* The user has not enough registrations, queue entries or marks,
             * so we try to mark the user! (Exceptions get handled above!) */
            if ($previewonly) {
                list(, $return) = $this->can_be_marked($agrpid, $userid, $message);
            } else {
                $return = $this->mark_for_reg($agrpid, $userid, $message);
            }

            return $return;
        }
    }

    /**
     * Check if user can change the group! Works different by returning 0 or 1!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @return bool whether or not user qualifies for a group change
     */
    protected function qualifies_for_groupchange($agrpid, $userid) {
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
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function check_reg_present($agrpid, $userid, $groupdata, $message) {
        global $USER;

        if ($this->grpmarked($agrpid, $userid)) {
            // Allready marked for registration!?!
            if ($userid != $USER->id) {
                throw new \mod_grouptool\local\exception\regpresent('already_marked', $message);
            } else {
                throw new \mod_grouptool\local\exception\regpresent('you_are_already_marked', $message);
            }
        }

        if (!empty($groupdata->registered) && $this->get_rank_in_queue($groupdata->registered, $userid) != false) {
            // We're sorry, but user's already registered in this group!
            if ($userid != $USER->id) {
                throw new \mod_grouptool\local\exception\regpresent('already_registered', $message);
            } else {
                throw new \mod_grouptool\local\exception\regpresent('you_are_already_registered', $message);
            }
        }

        if (!empty($groupdata->queued) && $this->get_rank_in_queue($groupdata->queued, $userid) != false) {
            // We're sorry, but user's already queued in this group!
            if ($userid != $USER->id) {
                throw new \mod_grouptool\local\exception\regpresent('already_queued', $message);
            } else {
                throw new \mod_grouptool\local\exception\regpresent('you_are_aleady_queued', $message);
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
     * @return string 'string' status message
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function can_change_group($agrpid, $userid, $message, $oldagrpid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        if (empty($this->grouptool->allow_unreg)) {
            throw new \mod_grouptool\local\exception\registration('unreg_not_allowed');
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        list($userregs, $userqueues, , , $max) = $this->check_users_regs_limits($userid, true);

        if (($oldagrpid === null)
                && !(($userqueues == 1 && $userregs == $max - 1) || ($userqueues + $userregs == 1 && $max == 1))) {
            // We can't determine a unique group to unreg the user from! He has to do it by manually!
            throw new \mod_grouptool\local\exception\registration('groupchange_from_non_unique_reg');
        }

        if ($this->grouptool->use_size && !empty($groupdata->registered)
                && (count($groupdata->registered) >= $groupdata->grpsize)) {
            if (!$this->grouptool->use_queue) {
                // We can't register the user nor queue the user!
                throw new \mod_grouptool\local\exception\exceedgroupsize();
            } else if (count($groupdata->queued) >= $this->grouptool->groups_queues_limit) {
                throw new \mod_grouptool\local\exception\exceedgroupqueuelimit();
            }

            if ($this->grouptool->users_queues_limit && ($userqueues >= $this->grouptool->users_queues_limit)
                    && ($userqueues != 1)) {
                // We can't queue him, due to exceeding his queue limit or not being able to determine which queue entry to unreg!
                throw new \mod_grouptool\local\exception\exceeduserqueuelimit();
            }
        }

        // We have no 'you'-version of the string here!
        return get_string('change_group_to', 'grouptool', $message);
    }

    /**
     * Changes group for certain user. This is only possible if unreg is allowed and we can determine which group to change!
     *
     * @param int $agrpid ID of active group to change to
     * @param int $userid (optional) ID of user to change group for or null ($USER->id is used).
     * @param stdClass $message (optional) prepared message object containing username and groupname or null.
     * @param int $oldagrpid (optional) ID of former active group
     * @return string success message
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function change_group($agrpid, $userid = null, $message = null, $oldagrpid = null) {
        global $DB, $USER;

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
            $groupdata = $this->get_active_groups(false, false, $agrpid);
            if (count($groupdata) != 1) {
                throw new \mod_grouptool\local\exception\registration('error_getting_data');
            }
            $groupdata = reset($groupdata);
            $message->groupname = $groupdata->name;
        }

        // Check if the user can be registered or queued with respect to max registrations being incremented by 1.
        $this->can_change_group($agrpid, $userid, $message, $oldagrpid);

        // Determine from which group to change and unregister from it!
        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ? AND active = 1",
                [$this->grouptool->id]);
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->get_records_select('grouptool_registered',
                "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql, $params);
        $userqueues = $DB->get_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
        if ($oldagrpid !== null) {
            $sql = "SELECT queued.*, agrp.groupid
                      FROM {grouptool_queued} queued
                      JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if ($queue = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'agrpid' => $oldagrpid
            ], IGNORE_MISSING)) {

                $DB->delete_records('grouptool_queued', ['id' => $queue->id]);
                // Trigger the event!
                \mod_grouptool\event\queue_entry_deleted::create_direct($this->cm, $queue);
                // Let other queued be promoted to registered status!
                $this->fill_from_queue($queue->agrpid);
            }
            $sql = "SELECT reg.*, agrp.groupid
                      FROM {grouptool_registered} reg
                      JOIN {grouptool_agrps} agrp ON agrp.id = reg.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if ($reg = $DB->get_record_sql($sql, [
                    'userid' => $userid,
                    'agrpid' => $oldagrpid
            ], IGNORE_MISSING)) {

                $DB->delete_records('grouptool_registered', ['id' => $reg->id]);
                if (!empty($this->grouptool->immediate_reg)) {
                    groups_remove_member($reg->groupid, $userid);
                }
                // Trigger the event!
                \mod_grouptool\event\registration_deleted::create_direct($this->cm, $reg);
                // Let other queued be promoted to registered status!
                $this->fill_from_queue($reg->agrpid);
            }
        } else if (count($userqueues) == 1) {
            // Delete his queue!
            $queues = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                              FROM {grouptool_queued} queued
                                              JOIN {grouptool_agrps} agrp ON agrp.id = queued.agrpid
                                              WHERE userid = ? AND agrpid ".$agrpsql, $params);
            $DB->delete_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
            foreach ($queues as $cur) {
                // Trigger the event!
                \mod_grouptool\event\queue_entry_deleted::create_direct($this->cm, $cur);

                // Let other queued be promoted to registered status!
                $this->fill_from_queue($cur->agrpid);
            }
        } else if (count($userregs) == 1) {
            $oldgrp = $DB->get_field_sql("SELECT agrp.groupid
                                            FROM {grouptool_registered} reg
                                            JOIN {grouptool_agrps} agrp ON agrp.id = reg.agrpid
                                           WHERE reg.userid = ? AND reg.agrpid ".$agrpsql,
                                         $params, MUST_EXIST);
            $reg = $DB->get_record_select('grouptool_registered', "userid = ? AND agrpid ".$agrpsql, $params,
                    '*', MUST_EXIST);
            $DB->delete_records_select('grouptool_registered', "userid = ? AND agrpid ".$agrpsql, $params);
            if (!empty($oldgrp) && !empty($this->grouptool->immediate_reg)) {
                groups_remove_member($oldgrp, $userid);
            }

            // Trigger the event!
            $reg->groupid = $oldgrp;
            \mod_grouptool\event\registration_deleted::create_direct($this->cm, $reg);

            // Let other queued be promoted to registered status!
            $this->fill_from_queue($reg->agrpid);
        } else {
            throw new \mod_grouptool\local\exception\registration(get_string('groupchange_from_non_unique_reg',
                    'grouptool'));
        }

        // Register him in the new group!
        try {
            // First we try to register the user!
            $return = $this->add_registration($agrpid, $userid, $message);
            // If we can register, we have to convert the other marks to registrations & queue entries!
            $this->convert_marks_to_regs($userid);

            return $return;
        } catch (\mod_grouptool\local\exception\exceedgroupsize $e) {
            if (!$this->grouptool->use_queue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            $return = $this->add_queue_entry($agrpid, $userid, $message);
            // If we can queue, we have to convert the other marks to registrations & queue entries!
            $this->convert_marks_to_regs($userid);

            return $return;
        }
    }

    /**
     * Checks if user has to many, too less registrations and return values!
     *
     * @param int $userid User's ID
     * @param bool $change (optional) true if check is used for group change!
     * @return array $userregs, $userqueues, $marks, $min, $max
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function check_users_regs_limits($userid, $change=false) {
        global $DB;

        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ? AND active = 1",
                [$this->grouptool->id]);
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('grouptool_registered',
                "modified_by >= 0 AND userid = ? AND agrpid ".$agrpsql, $params);
        $userqueues = $DB->count_records_select('grouptool_queued', "userid = ? AND agrpid ".$agrpsql, $params);
        $marks = $this->count_user_marks($userid);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;

        if ($change) {
            if ($min > ($marks + $userregs + $userqueues)) {
                throw new \mod_grouptool\local\exception\registration('too_many_registrations');
            }
            if ($max < ($marks + $userregs + $userqueues)) {
                throw new \mod_grouptool\local\exception\exceeduserreglimit();
            }
        } else {
            if ($min <= ($marks + $userregs + $userqueues)) {
                throw new \mod_grouptool\local\exception\registration('too_many_registrations');
            }
            if ($max <= ($marks + $userregs + $userqueues)) {
                throw new \mod_grouptool\local\exception\exceeduserreglimit();
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
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function can_be_marked($agrpid, $userid, $message) {
        global $USER;

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        $full = !empty($this->grouptool->groups_queues_limit)
                && (count($groupdata->queued) >= $this->grouptool->groups_queues_limit);
        if ($this->grouptool->use_size && (count($groupdata->registered) >= $groupdata->grpsize)
                && (!$this->grouptool->use_queue || $full)) {
            throw new \mod_grouptool\local\exception\exceedgroupsize();
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
     * Allocates a place in the group. Used in case there are not enough registrations by now.
     *
     * @param int $agrpid ID of active group to mark registration for.
     * @param int $userid (optional) ID of user to mark registration for or null ($USER->id is used).
     * @param stdClass $message (optional) prepared message object containing username and groupname or null.
     * @return string success message
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function mark_for_reg($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupdata = $this->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }

        $this->can_be_marked($agrpid, $userid, $message);

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
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function convert_marks_to_regs($userid) {
        global $DB, $USER;

        // Get user's marks!
        $usermarks = $this->get_user_marks($userid);

        $queues = 0;
        foreach ($usermarks as $cur) {
            if ($cur->type != 'reg') {
                $queues++;
            }
        }
        if (!empty($this->grouptool->users_queues_limit) && ($queues > $this->grouptool->users_queues_limit)) {
            throw new \mod_grouptool\local\exception\exceeduserqueuelimit();
        }

        foreach ($usermarks as $cur) {
            if ($cur->type == 'reg') {
                unset($cur->type);
                $cur->modified_by = $USER->id;
                $DB->update_record('grouptool_registered', $cur);
                if ($this->grouptool->immediate_reg) {
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
     * Check if user can be queued, else throw exception!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message (optional) prepared message object containing username and groupname or null
     * @return string status message
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function can_be_queued($agrpid, $userid = null, $message = null) {
        global $USER, $DB;

        // Shortcut if we don't use queues!
        if (!$this->grouptool->use_queue) {
            throw new \mod_grouptool\local\exception\exceedgroupsize();
        }

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
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

        if ($this->grouptool->users_queues_limit && (($queueswithmarks > $this->grouptool->users_queues_limit)
                || ($queues >= $this->grouptool->users_queues_limit))) {
            throw new \mod_grouptool\local\exception\exceeduserqueuelimit();
        }

        if ($this->grouptool->groups_queues_limit && (count($groupdata->queued) >= $this->grouptool->groups_queues_limit)) {
            throw new \mod_grouptool\local\exception\exceedgroupqueuelimit();
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $userregs = $this->get_user_reg_count($userid);
        $marks = $this->count_user_marks($userid);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($max <= ($marks + $userregs + $queues)) {
            throw new \mod_grouptool\local\exception\exceeduserreglimit();
        }
        if ($min > ($marks + $userregs + $queues + 1)) {
            // Not enough registrations/queues/marks!
            throw new \mod_grouptool\local\exception\notenoughregs();
        }

        if ($userid != $USER->id) {
            return get_string('queue_in_group', 'grouptool', $message);
        } else {
            return get_string('queue_you_in_group', 'grouptool', $message);
        }
    }

    /**
     * Add a queue entry for a certain user/agrp-combination.
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message prepared message object containing username and groupname or null
     * @return string status string
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function add_queue_entry($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupdata = $this->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        // This method throws exceptions, if user is not able to be queued!
        $this->can_be_queued($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->id = $DB->insert_record('grouptool_queued', $record);
        // Trigger the event!
        $record->groupid = $groupdata->id;
        \mod_grouptool\event\queue_entry_created::create_direct($this->cm, $record)->trigger();
        if ($userid != $USER->id) {
            return get_string('queue_in_group_success', 'grouptool', $message);
        } else {
            return get_string('queue_you_in_group_success', 'grouptool', $message);
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
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\notenoughregs
     */
    protected function check_can_be_registered($group, $userregs, $queues, $marks) {
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($this->grouptool->use_size && (count($group->registered) >= $group->grpsize)) {
            throw new \mod_grouptool\local\exception\exceedgroupsize();
        }
        if ($max <= ($marks + $userregs + $queues)) {
            throw new \mod_grouptool\local\exception\exceeduserreglimit();
        }
        if ($min > ($marks + $userregs + $queues + 1)) {
            // Not enough registrations/queues/marks!
            throw new \mod_grouptool\local\exception\notenoughregs();
        }
    }

    /**
     * Checks if user can be registered, else throw exception!
     *
     * @param int $agrpid ID of the active group
     * @param int $userid ID of user to queue or null (then $USER->id is used)
     * @param stdClass $message prepared message object containing username and groupname or null
     * @return string status message
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function can_be_registered($agrpid, $userid, $message) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
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

    /**
     * Add a registration for a certain user/agrp-combination.
     *
     * @param int $agrpid ID of the active group
     * @param int $userid (optional) ID of user to register or null (then $USER->id is used)
     * @param stdClass $message (optional) prepared message object containing username and groupname or null
     * @return string status message
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    protected function add_registration($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupdata = $this->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            throw new \mod_grouptool\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        /* This method throws exceptions if there is a problem */
        $this->can_be_registered($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->modified_by = $USER->id;
        $record->id = $DB->insert_record('grouptool_registered', $record);
        if ($this->grouptool->immediate_reg) {
            groups_add_member($groupdata->id, $userid);
        }
        // Trigger the event!
        $record->groupid = $groupdata->id;
        \mod_grouptool\event\registration_created::create_direct($this->cm, $record)->trigger();
        if ($userid != $USER->id) {
            return get_string('register_in_group_success', 'grouptool', $message);
        } else {
            return get_string('register_you_in_group_success', 'grouptool', $message);
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
    protected function get_user_queues_count($userid=0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = [];
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge([$userid], $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_queued}
                                       WHERE userid = ? AND agrpid '.$sql, $params);
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
    protected function get_user_reg_count($userid=0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = [];
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge([$userid], $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_registered}
                                       WHERE modified_by >= 0 AND userid = ? AND agrpid '.$sql, $params);
    }


    /**
     * checks the found userdata, and return error rows if no user was found or multiple were fund
     * @param array $userinfo data that was found
     * @param array $user the data given by the user
     * @param array $importfields the fields which were checked
     * @return array rows for the table, possibly empty if exactly one user was found
     * @throws coding_exception
     */
    private function check_userinfo($userinfo, $user, $importfields) {
        global $OUTPUT;
        $errorrows = [];
        if (empty($userinfo)) {
            $errorrows[0] = new html_table_row();
            $errorrows[0]->cells[] = new html_table_cell($OUTPUT->notification(
                get_string('user_not_found', 'grouptool', $user), \core\output\notification::NOTIFY_ERROR));
        } else if (count($userinfo) > 1) {
            foreach ($this->generate_multiple_users_table($userinfo, $importfields) as $tmprow) {
                $errorrows[] = $tmprow;
            }
        }
        return $errorrows;
    }

    /**
     * Searches users based on the information given and the fields to consider
     * @param array $importfields the fields to check
     * @param array $user the data for thse fields
     * @return array the found user/s
     * @throws dml_exception
     */
    private function find_userinfo($importfields, $user) {
        global $DB;
        $userinfo = [];
        foreach ($importfields as $field) {
            $sql = 'SELECT * FROM {user} WHERE '.$DB->sql_like($field, ':userpattern');
            $sql .= ' AND deleted = 0';
            $param = ['userpattern' => $user];

            $userinfo = $DB->get_records_sql($sql, $param);

            if (empty($userinfo)) {
                $param['userpattern'] = '%'.$user;
                $userinfo = $DB->get_records_sql($sql, $param);
            } else if (count($userinfo) == 1) {
                break;
            }

            if (empty($userinfo)) {
                $param['userpattern'] = $user.'%';
                $userinfo = $DB->get_records_sql($sql, $param);
            } else if (count($userinfo) == 1) {
                break;
            }

            if (empty($userinfo)) {
                $param['userpattern'] = '%'.$user.'%';
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
        $tmprows[0]->cells[$curkey] = new html_table_cell($OUTPUT->notification(get_string('found_multiple',
                'grouptool'),
            \core\output\notification::NOTIFY_ERROR));
        $tmprows[0]->cells[$curkey]->rowspan = count($tmprows);
        return $tmprows;
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
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function unregister($groups, $data, $unregfrommgroups = true, $previewonly = false, $unregfromallagrps = false) {
        global $DB, $OUTPUT;

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

        $unregistered = [];

        $agrp = [];
        $groupname = [];
        foreach ($groups as $group) {
            if ($unregfromallagrps) {
                $agrp[$group] = $DB->get_fieldset_select('grouptool_agrps', 'id', 'groupid = :groupid', [
                        'groupid' => $group]);
                $groupname[$group] = $DB->get_field('groups', 'name', [
                        'id' => $group
                ], IGNORE_MISSING);
            } else {
                $agrp[$group] = $DB->get_field('grouptool_agrps', 'id', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid' => $group
                ], IGNORE_MISSING);
                $groupname[$group] = $DB->get_field('groups', 'name', [
                        'id' => $group
                ], IGNORE_MISSING);

                if (!$DB->record_exists('grouptool_agrps', [
                        'grouptoolid' => $this->grouptool->id,
                        'groupid' => $group,
                        'active' => 1
                ])) {
                    $message .= $OUTPUT->notification(get_string('unregister_in_inactive_group_warning', 'grouptool',
                            $groupname[$group]), \core\output\notification::NOTIFY_ERROR);
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
        $followchangessetting = $DB->get_field('grouptool', 'ifmemberremoved', array('id' => $this->grouptool->id));
        foreach ($users as $user) {
            $userinfo = $this->find_userinfo($importfields, $user);
            $pbar->update($processed, $count, get_string('import_progress_search', 'grouptool').' '.$user);
            $row = new html_table_row();
            $errors = 0;
            foreach ($this->check_userinfo($userinfo, $user, $importfields) as $errorrow) {
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
                        $row->cells[] = new html_table_cell($OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR));
                    } else {
                        $text = get_string('user_is_deleted', 'grouptool', $userinfo);
                        $row->cells[] = new html_table_cell($OUTPUT->notification($text, \core\output\notification::NOTIFY_ERROR));
                    }
                    $error = true;
                    continue;
                }
                foreach ($groups as $group) {
                    $data = [
                        'id' => $userinfo->id,
                        'idnumber' => $userinfo->idnumber,
                        'fullname' => fullname($userinfo),
                        'groupname' => $groupname[$group]
                    ];
                    if (!$previewonly && $userinfo) {

                        $wasunregfrommgroup = false;
                        $wasunregfrommgtgroup = false;
                        $notinmgroup = false;

                        $pbar->update($processed, $count,
                            get_string('unregister_progress_unregister',
                                    'grouptool').' '.fullname($userinfo).'...');
                        list($insql, $inparams) = $DB->get_in_or_equal($agrp[$group], SQL_PARAMS_NAMED);
                        $inparams['userid'] = $data['id'];
                        $sqlreg = "SELECT * FROM {grouptool_registered} WHERE agrpid $insql AND userid=:userid";
                        $sqlqueue = "SELECT * FROM {grouptool_queued} WHERE agrpid $insql AND userid=:userid";
                        if ((!$DB->record_exists_sql($sqlreg, $inparams) &&
                            !$DB->record_exists_sql($sqlqueue, $inparams)) || $unregfrommgroups) {
                            if (groups_is_member($group, $data['id']) && $unregfrommgroups) {
                                groups_remove_member($group, $data['id']);
                                $wasunregfrommgroup = true;
                            } else {
                                $notinmgroup = true;
                            }
                        }
                        if ($followchangessetting && $DB->record_exists('groups_members', [
                                'groupid' => $group,
                                'userid' => $data['id']
                            ])) {
                            $DB->delete_records('groups_members', [
                                'groupid' => $group,
                                'userid' => $data['id']
                            ]);

                            $time = time();
                            $DB->set_field('groups', 'timemodified', $time, array('id' => $group));

                            cache_helper::invalidate_by_definition('core', 'user_group_groupings', array(),
                                    array($data['id']));

                            $context = context_course::instance($this->grouptool->course);
                            if ($conversation = \core_message\api::get_conversation_by_area('core_group',
                                                                        'groups', $group, $context->id)) {
                                \core_message\api::remove_members_from_conversation([$data['id']], $conversation->id);
                            }
                        }

                        if ($unregfromallagrps) {
                            if (is_array($agrp[$group])) {
                                foreach ($agrp[$group] as $agrpinst) {
                                    if ($DB->record_exists('grouptool_registered', [
                                                    'agrpid' => $agrpinst,
                                                    'userid' => $data['id']
                                            ]) ||
                                            $DB->record_exists('grouptool_queued', [
                                                    'agrpid' => $agrpinst,
                                                    'userid' => $data['id']
                                            ])) {
                                        $this->unregister_from_agrp($agrpinst, $userinfo->id, false, true, true);
                                    }
                                }
                            } else {
                                $this->unregister_from_agrp($agrp[$group], $userinfo->id, false, true);
                            }
                            $wasunregfrommgtgroup = true;
                        }
                        $unregistered[] = $userinfo->id;
                        if ($wasunregfrommgroup && !$wasunregfrommgtgroup) {
                            $row->cells[] = get_string('unregister_user_from_moodle_group', 'grouptool', $data);
                            $row->attributes['class'] = 'success';
                        } else if ($notinmgroup && !$wasunregfrommgtgroup) {
                            $row->cells[] = get_string('unregister_user_not_in_group', 'grouptool', $data);
                            $row->attributes['class'] = 'success';
                        } else {
                            $row->cells[] = get_string('unregister_user', 'grouptool', $data);
                            $row->attributes['class'] = 'success';
                        }

                    } else if ($userinfo) {
                        if (!$DB->record_exists_select('grouptool_registered', "agrpid = :agrpid AND userid = :userid",
                            ['agrpid' => $agrp[$group], 'userid' => $userinfo->id])) {
                            if (groups_is_member($group, $userinfo->id)) {
                                $cell = get_string('unregister_user_only_in_moodle_group',
                                        'grouptool', $data);
                                $row->cells[] = $cell;
                                $row->attributes['class'] = 'prevsuccess';
                            } else {
                                $cell = get_string('unregister_conflict_user_not_in_group', 'grouptool', $data);
                                $row->cells[] = $cell;
                                $row->attributes['class'] = 'prevconflict';
                            }
                        } else {
                            $row->cells[] = get_string('unregister_user_prev', 'grouptool', $data);
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
            $pbar->update($processed, $count, get_string('unregister_progress_completed', 'grouptool'));
        } else {
            $pbar->update($processed, $count, get_string('unregister_progress_preview_completed', 'grouptool'));
        }
        $message .= html_writer::table($prevtable);
        return [$error, $message];
    }




    /**
     * helperfunction compares to objects using a particular timestamp-property
     *
     * @param stdClass $a object containing timestamp property
     * @param stdClass $b object containing timestamp property
     * @return int 0 if equal, +1 if $a->timestamp > $b->timestamp or -1 if otherwise
     */
    private function cmptimestamp($a, $b) {
        if ($a->timestamp == $b->timestamp) {
            return 0;
        } else {
            return $a->timestamp > $b->timestamp ? 1 : -1;
        }
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
    private function get_rank_in_queue($data=0, $userid=0) {
        global $DB, $USER;

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
                    'userid' => !empty($userid) ? $userid : $USER->id
            ];
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
     * returns object with information about registrations/queues for each group
     * (optional with userdata)
     * if $user == 0 no userdata is returned
     * else if $user == null data about $USERs registrations/queues is added
     * else data about $userids registrations/queues is added
     *
     * @param int $userid id of user for whom data should be added
     *                    or 0 (=$USER) or null (=no userdata)
     * @return stdClass object containing information about active groups
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_registration_stats($userid=null) {
        global $USER, $DB;
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
            default:
                $groups = $this->get_active_groups(false, false);
                break;
            case 0:
                $groups = $this->get_active_groups();
                break;
        }

        foreach ($groups as $group) {
            $group = $this->get_active_groups(true, true, $group->agrpid, $group->id);
            $group = current($group);
            if ($this->grouptool->use_size) {
                $return->group_places += $group->grpsize;
            }
            $return->occupied_places += count($group->registered);
            if ($userid != 0) {
                $regrank = $this->get_rank_in_queue($group->registered, $userid);
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

                $queuerank = $this->get_rank_in_queue($group->queued, $userid);
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
        $return->free_places = ($this->grouptool->use_size) ? ($return->group_places - $return->occupied_places) : null;
        $return->users = count_enrolled_users($this->context, 'mod/grouptool:register');

        $agrps = $DB->get_records('grouptool_agrps', ['grouptoolid' => $this->cm->instance, 'active' => 1]);
        if (is_array($agrps) && count($agrps) >= 1) {
            $agrpids = array_keys($agrps);
            list($inorequal, $params) = $DB->get_in_or_equal($agrpids);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_registered}
                     WHERE modified_by >= 0 AND agrpid ".$inorequal;
            $return->reg_users = $DB->count_records_sql($sql, $params);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {grouptool_queued}
                     WHERE agrpid ".$inorequal;
            $return->queued_users = $DB->count_records_sql($sql, $params);
        } else {
            $return->reg_users = 0;
        }
        $return->notreg_users = $return->users - $return->reg_users;

        return $return;
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
        global $DB, $USER;
        $error = false;
        $returntext = "";
        $status = [];

        // Trigger event!
        \mod_grouptool\event\dequeuing_started::create_from_object($this->cm)->trigger();

        $grouptool = $this->grouptool;
        $context = $this->context;

        require_capability('mod/grouptool:register_students', $context);

        $agrps = $this->get_active_groups(false, false, 0, 0, 0,
                false);
        list($agrpsql, $params) = $DB->get_in_or_equal(array_keys($agrps), SQL_PARAMS_NAMED, 'reg');
        list($agrpsql2, $params2) = $DB->get_in_or_equal(array_keys($agrps), SQL_PARAMS_NAMED, 'queue');

        if (!empty($agrps)) {
            $agrpids = array_keys($agrps);
            list($agrpssql, $agrpsparam) = $DB->get_in_or_equal($agrpids);
            $agrpsfiltersql = " AND agrp.id ".$agrpssql;
            $agrpsfilterparams = array_merge([$grouptool->id], $agrpsparam);
            // Get queue-entries (sorted by timestamp)!
            if (!empty($grouptool->allow_multiple)) {
                $queuedsql = " WHERE queued.agrpid ".$agrpssql." ";
                $queuedparams = array_merge($agrpsparam, $agrpsparam);

                $queueentries = $DB->get_records_sql("
                      SELECT queued.id, MAX(queued.agrpid) AS agrpid, MAX(queued.userid) AS userid,
                             MAX(queued.timestamp) AS timestamp, (COUNT(DISTINCT reg.id) < ?) AS priority
                        FROM {grouptool_queued} queued
                   LEFT JOIN {grouptool_registered} reg ON queued.userid = reg.userid AND reg.agrpid ".$agrpssql.
                                                                                    " AND reg.modified_by >= 0
                    ".$queuedsql."
                    GROUP BY queued.id
                    ORDER BY priority DESC, queued.timestamp ASC",
                    array_merge([$grouptool->choose_min], $queuedparams));
            } else {
                $queuedsql = " WHERE queued.agrpid ".$agrpssql." ";
                $queuedparams = $agrpsparam;
                $queueentries = $DB->get_records_sql("SELECT *, '1' AS priority
                                                        FROM {grouptool_queued} queued".
                                                             $queuedsql.
                                                   "ORDER BY timestamp ASC",
                                                     $queuedparams);
            }
            $userregs = $DB->get_records_sql_menu('SELECT reg.userid, COUNT(DISTINCT reg.id)
                                                     FROM {grouptool_registered} reg
                                                    WHERE reg.agrpid '.$agrpssql.' AND modified_by >= 0
                                                 GROUP BY reg.userid', $agrpsparam);
        } else {
            return [true, get_string('no_active_groups', 'grouptool')];
        }

        // Get group entries (sorted by sort-order)!
        $groupsdata = $DB->get_records_sql("
                SELECT agrp.id AS id, MAX(agrp.groupid) AS groupid, MAX(agrp.grpsize) AS grpsize,
                       COUNT(DISTINCT reg.id) AS registered
                  FROM {grouptool_agrps} agrp
             LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrp.id AND modified_by >= 0
                 WHERE agrp.grouptoolid = ?".$agrpsfiltersql."
              GROUP BY agrp.id
              ORDER BY agrp.sort_order ASC", $agrpsfilterparams);

        $i = 0;

        if (!empty($groupsdata) && !empty($queueentries)) {
            $fullnames = $DB->get_records_sql_menu("SELECT DISTINCT u.id, ".$DB->sql_fullname()."
                                                  FROM {user} u
                                             LEFT JOIN {grouptool_queued} q ON q.userid = u.id AND q.agrpid ".$agrpsql2."
                                             LEFT JOIN {grouptool_registered} r ON r.userid = u.id AND r.agrpid ".$agrpsql."
                                                 WHERE (r.id IS NOT NULL OR q.id IS NOT NULL)", $params + $params2);
            $planned = new stdClass();
            $curgroup = null;
            $maxregs = !empty($this->grouptool->allow_multiple) ? $this->grouptool->choose_max : 1;
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
                        $username = $DB->get_field('user', $DB->sql_fullname('firstname', 'lastname'),
                                ['id' => $queue->userid]);
                        $returntext .= html_writer::tag('div', get_string('all_groups_full',
                                'grouptool', $username), ['class' => 'error']);
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

                // If user has got too many regs allready!
                if (!empty($userregs[$queue->userid]) && ($userregs[$queue->userid] >= $maxregs)) {
                    $returntext .= html_writer::tag('div', get_string('too_many_regs', 'grouptool'),
                                                    ['class' => 'error']);
                    $error = true;
                    // Continue with next user/queue-entry!
                    continue;
                }

                while ($DB->record_exists('grouptool_registered', [
                                'agrpid' => $curgroup->id,
                                'userid' => $queue->userid
                        ])
                       || in_array($curgroup->id, $planned->{$queue->userid})
                       || $curgroup->registered >= $curgroup->grpsize) {
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

                            $curtext = $this->can_change_group($curgroup->id, $queue->userid, $message, $queue->agrpid);
                        } catch (\mod_grouptool\local\exception\registration $e) {
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
                            $curtext = $this->change_group($curgroup->id, $queue->userid, null, $queue->agrpid);
                        } catch (\mod_grouptool\local\exception\registration $e) {
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
                                'id'     => $queue->id,
                                'userid' => $queue->userid,
                                'agrpid' => $queue->agrpid
                        ];
                        // Delete queue entry if successfull or print message!
                        $DB->delete_records('grouptool_queued', $attr);

                        // Log user moved!
                        $queue->groupid = $DB->get_field('grouptool_agrps', 'groupid', ['id' => $queue->agrpid],
                                MUST_EXIST);
                        $to = new stdClass();
                        $to->agrpid = $curgroup->id;
                        $to->userid = $queue->userid;
                        $to->groupid = $DB->get_field('grouptool_agrps', 'groupid', ['id' => $curgroup->id],
                                MUST_EXIST);
                        $to->id = $DB->get_field('grouptool_registered', 'id', [
                                'agrpid' => $to->agrpid,
                                'userid' => $to->userid
                        ], MUST_EXIST);
                        \mod_grouptool\event\user_moved::move($this->cm, $queue, $to)->trigger();

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
     * Return all marks for the specified user
     *
     * The marks are the registation entries before they become active
     * (i.e. if not enough groups have been chosen).
     *
     * @param int $userid (optional) User-ID for which the marks should be returned
     * @return stdClass[] Users marks
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_user_marks($userid=0) {
        global $DB, $USER, $OUTPUT;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $agrps = $DB->get_fieldset_select('grouptool_agrps', 'id',
                                          'grouptoolid = ?',
                                          [$this->cm->instance]);
        if (empty($agrps)) {
            return null;
        }
        list($agrpssql, $params) = $DB->get_in_or_equal($agrps);
        $params[] = $userid;

        $sql = 'SELECT reg.id, reg.agrpid, reg.userid, reg.timestamp,
                       agrp.groupid
                  FROM {grouptool_registered} reg
                  JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id
                 WHERE reg.agrpid '.$agrpssql.'
                   AND modified_by = -1
                   AND userid = ?';

        $marks = $DB->get_records_sql($sql, $params);
        foreach ($marks as $id => $cur) {
            $groupdata = $this->get_active_groups(true, true, $cur->agrpid);
            $groupdata = current($groupdata);

            if ($this->grouptool->use_size) {
                $notfull = empty($this->grouptool->groups_queues_limit)
                    || (count($groupdata->queued) < $this->grouptool->groups_queues_limit);
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    $marks[$id]->type = 'reg';
                } else if ($this->grouptool->use_queue && $notfull) {
                    $marks[$id]->type = 'queue';
                } else {
                    // Place occupied in the meanwhile, must look for another group!
                    $info = new stdClass();
                    $info->grpname = groups_get_group_name($cur->groupid);
                    $info->userid = $userid;
                    echo $OUTPUT->notification(get_string('already_occupied', 'grouptool', $info),
                            \core\output\notification::NOTIFY_ERROR);
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
    public function delete_user_marks($userid=0) {
        global $DB;

        $marks = $this->get_user_marks($userid);
        if (is_array($marks) && count($marks) > 0) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($marks));
            $select = 'id '.$select;
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
    public function count_user_marks($userid=0) {
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
    public function grpmarked($agrpid, $userid=0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        return $DB->record_exists('grouptool_registered',
                                  [
                                          'agrpid' => $agrpid,
                                          'userid' => $userid,
                                          'modified_by' => -1
                                  ]);
    }

    /**
     * Return true if the registration is open, false otherwise!
     *
     * @return bool true if reg is open, false otherwise
     */
    public function is_registration_open() {

        return ($this->grouptool->allow_reg && (($this->grouptool->timedue == 0) || (time() < $this->grouptool->timedue))
                && (time() > $this->grouptool->timeavailable));
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

        list($esql, $params) = get_enrolled_sql($this->context, 'mod/grouptool:register');

        $sql = "SELECT u.id
                  FROM {user} u
             LEFT JOIN ($esql) eu ON eu.id=u.id
                 WHERE u.deleted = 0 AND eu.id=u.id ";
        $users = $DB->get_records_sql($sql, $params);

        if (empty($users)) {
            return 0;
        }

        list($usql, $uparams) = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usr');

        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 1;

        if ($min == 0) {
            return 0;
        }

        $agrps = $this->get_active_groups(false, false, 0, 0, 0,
                false);
        $keys = array_keys($agrps);

        if (empty($keys)) {
            $keys = [-1];
        }
        list($agrpsql, $params) = $DB->get_in_or_equal($keys, SQL_PARAMS_NAMED, 'agrp');
        $params = array_merge($uparams, $params);
        $regs = $DB->get_records_sql_menu("SELECT u.id, count(r.id)
                                             FROM {user} u
                                        LEFT JOIN {grouptool_registered} r ON u.id = r.userid AND r.modified_by >= 0
                                                  AND r.agrpid ".$agrpsql."
                                            WHERE u.id ".$usql."
                                         GROUP BY u.id", $params);
        $queues = $DB->get_records_sql_menu("SELECT u.id, count(q.id)
                                               FROM {user} u
                                          LEFT JOIN {grouptool_queued} q ON u.id = q.userid AND q.agrpid ".$agrpsql."
                                              WHERE u.id ".$usql."
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
     * view selfregistration-tab
     *
     * @param string $outputcache Output already generated that can be added after the header to be generated
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_selfregistration($outputcache) {
        global $OUTPUT, $DB, $USER, $PAGE;

        // Include js for filters.
        $USER->ajax_updatable_user_prefs['mod_grouptool_hideoccupied'] = true;
        $params = new StdClass();
        $prefhideoccupied = get_user_preferences('mod_grouptool_hideoccupied', false);
        if ($prefhideoccupied === 'true') {
            $params->filterunoccupied = true;
        } else {
            $params->filterunoccupied = false;
        }
        $PAGE->requires->js_call_amd('mod_grouptool/filter', 'init', [$params]);

        $userid = $USER->id;
        $regopen = $this->is_registration_open();

        // Process submitted form!
        $error = false;
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed!
            $hideform = 0;
            $action = optional_param('action', 'reg', PARAM_ALPHA);
            $confirmmessage = '';
            if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Unregister user and get feedback!
                try {
                    $confirmmessage = $this->unregister_from_agrp($agrpid, $USER->id);
                } catch (\mod_grouptool\local\exception\registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'reg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Register user and get feedback!
                try {
                    $confirmmessage = $this->register_in_agrp($agrpid, $USER->id);
                } catch (\mod_grouptool\local\exception\registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                list($error, $confirmmessage) = $this->resolve_queues();
                if ($error == -1) {
                    $error = true;
                }
            }
            if ($error === true) {
                echo $OUTPUT->header() . $outputcache . $OUTPUT->notification($confirmmessage, \core\output\notification::NOTIFY_ERROR);
            } else {
                echo $OUTPUT->header() . $outputcache . $OUTPUT->notification($confirmmessage, \core\output\notification::NOTIFY_SUCCESS);
            }
        } else if (data_submitted() && confirm_sesskey()) {

            // Display confirm-dialog!
            $hideform = 1;
            $reg = optional_param_array('reg', null, PARAM_INT);
            $action = false;
            $agrpid = -1;
            if ($reg != null) {
                $agrpid = array_keys($reg);
                $agrpid = reset($agrpid);
                $action = 'reg';
            }
            $unreg = optional_param_array('unreg', null, PARAM_INT);
            if ($unreg != null) {
                $agrpid = array_keys($unreg);
                $agrpid = reset($agrpid);
                $action = 'unreg';
            }
            $resolvequeues = optional_param('resolve_queues', 0, PARAM_BOOL);
            if (!empty($resolvequeues)) {
                $action = 'resolvequeues';
            }

            $attr = [];
            if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                list($error, $confirmmessage) = $this->resolve_queues(true); // Try only!
            } else if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $attr['group'] = $agrpid;
                // Try only!
                try {
                    $confirmmessage = $this->unregister_from_agrp($agrpid, $USER->id, true);
                } catch (\mod_grouptool\local\exception\registration $e) {
                    $error = 1;
                    $confirmmessage = $e->getMessage();
                }
            } else {
                require_capability('mod/grouptool:register', $this->context);
                $action = 'reg';
                $attr['group'] = $agrpid;
                // Try only!
                try {
                    $confirmmessage = $this->register_in_agrp($agrpid, $USER->id, true);
                } catch (\mod_grouptool\local\exception\registration $e) {
                    $error = 1;
                    $confirmmessage = $e->getMessage();
                }
            }
            $attr['confirm'] = '1';
            $attr['action'] = $action;
            $attr['sesskey'] = sesskey();

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if (($error === true) && ($action != 'resolvequeues')) {
                $continue->remove_params('confirm', 'group');
                $continue = new single_button($continue, get_string('continue'), 'get');
                $cancel = null;
            }
            echo $OUTPUT->header() . $outputcache;
            echo $this->confirm($confirmmessage, $continue, $cancel);
        } else {
            $hideform = 0;
            echo $OUTPUT->header() . $outputcache;
        }

        if (empty($hideform)) {
            /*
             * we need a new moodle_url-Object because
             * $PAGE->url->param('sesskey', sesskey());
             * won't set sesskey param in $PAGE->url?!?
             */
            $url = new moodle_url($PAGE->url, ['sesskey' => sesskey()]);
            $mform = new MoodleQuickForm('registration_form', 'post', $url, '', ['id' => 'registration_form']);

            $regstat = $this->get_registration_stats($USER->id);

            if (!empty($this->grouptool->timedue) && (time() >= $this->grouptool->timedue) &&
                    has_capability('mod/grouptool:register_students', $this->context)) {
                if ($regstat->queued_users > 0) {
                    // Insert queue-resolving button!
                    $mform->addElement('header', 'resolveheader', get_string('resolve_queue_legend',
                            'grouptool'));
                    $mform->addElement('submit', 'resolve_queues', get_string('resolve_queue',
                            'grouptool'));
                }
            }
            if (has_capability('mod/grouptool:view_description', $this->context)) {

                $mform->addElement('header', 'generalinfo', get_string('general_information',
                        'grouptool'));
                $mform->setExpanded('generalinfo');

                if (!empty($this->grouptool->use_size)) {
                    $placestats = $regstat->group_places.'&nbsp;'.get_string('total', 'grouptool');
                } else {
                    $placestats = '∞&nbsp;'.get_string('total', 'grouptool');
                }
                if (($regstat->free_places != null) && !empty($this->grouptool->use_size)) {
                    $placestats .= ' / '.$regstat->free_places.'&nbsp;'.
                                    get_string('free', 'grouptool');
                } else {
                    $placestats .= ' / ∞&nbsp;'.get_string('free', 'grouptool');
                }
                if ($regstat->occupied_places != null) {
                    $placestats .= ' / '.$regstat->occupied_places.'&nbsp;'.
                                    get_string('occupied', 'grouptool');
                }
                $mform->addElement('static', 'group_places', get_string('group_places', 'grouptool'),
                        $placestats);
                $mform->addHelpButton('group_places', 'group_places', 'grouptool');

                $mform->addElement('static', 'number_of_students', get_string('number_of_students',
                        'grouptool'), $regstat->users);

                if (($this->grouptool->allow_multiple &&
                        (count($regstat->registered) < $this->grouptool->choose_min))
                        || (!$this->grouptool->allow_multiple && !count($regstat->registered))) {
                    if ($this->grouptool->allow_multiple) {
                        $missing = ($this->grouptool->choose_min - count($regstat->registered));
                        $stringlabel = ($missing > 1) ? 'registrations_missing' : 'registration_missing';
                    } else {
                        $missing = 1;
                        $stringlabel = 'registration_missing';
                    }
                    $missingtext = get_string($stringlabel, 'grouptool', $missing);
                } else {
                    $missingtext = "";
                }

                if (!empty($regstat->registered)) {
                    $regscumulative = [];
                    foreach ($regstat->registered as $registration) {
                        $regscumulative[] = $registration->grpname.' ('.$registration->rank.')';
                    }
                    $mform->addElement('static', 'registrations', get_string('registrations',
                            'grouptool'),
                                       html_writer::tag('div', $missingtext).implode(', ', $regscumulative));
                } else {
                    $mform->addElement('static', 'registrations', get_string('registrations',
                            'grouptool'),
                                       html_writer::tag('div', $missingtext).get_string('not_registered',
                                               'grouptool'));
                }

                if (!empty($regstat->queued)) {
                    $queuescumulative = [];
                    foreach ($regstat->queued as $queue) {
                        $queuescumulative[] = $queue->grpname.' ('.$queue->rank.')';
                    }
                    $mform->addElement('static', 'queues', get_string('queues', 'grouptool'),
                            implode(', ', $queuescumulative));
                }

                if (!empty($this->grouptool->allow_reg)) {
                    if (!empty($this->grouptool->allow_unreg)) {
                        $unregtext = get_string('allowed', 'grouptool');
                    } else {
                        $unregtext = get_string('not_permitted', 'grouptool');
                    }
                    $mform->addElement('static', 'unreg', get_string('unreg_is', 'grouptool'),
                            $unregtext);
                    if (!empty($this->grouptool->allow_multiple)) {
                        $minmaxtext = '';
                        if ($this->grouptool->choose_min && $this->grouptool->choose_max) {
                            $data = [
                                    'min' => $this->grouptool->choose_min,
                                    'max' => $this->grouptool->choose_max
                            ];
                            $minmaxtext = get_string('choose_min_max_text', 'grouptool', $data);
                        } else if ($this->grouptool->choose_min) {
                            $minmaxtext = get_string('choose_min_text', 'grouptool',
                                    $this->grouptool->choose_min);
                        } else if ($this->grouptool->choose_max) {
                            $minmaxtext = get_string('choose_max_text', 'grouptool',
                                    $this->grouptool->choose_max);
                        }
                        $mform->addElement('static', 'minmax', get_string('choose_minmax_title',
                                'grouptool'), $minmaxtext);
                    }

                    if (!empty($this->grouptool->use_queue)) {
                        $mform->addElement('static', 'queueing', get_string('queueing_is', 'grouptool'),
                            get_string('active', 'grouptool'));
                    }
                }
            }
            $groups = $this->get_active_groups(true, true);

            // Preperation for loop.
            $userregs = $this->get_user_reg_count($userid);
            $userqueues = $this->get_user_queues_count($userid);
            $usermarks = $this->count_user_marks($userid);
            $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
            $mform->addElement('header', 'groups', get_string('groups'));
            $mform->setExpanded('groups');
            // Checkbox control for only unoccupied groups filter.
            $mform->addElement('html', '<div><label class="form-check-inline">
                                                <input type="checkbox" name="filterunoccupied"
                                                id="filterunoccupied" class="form-check-input"> ' .
                                                get_string('filterunoccupied', 'grouptool') . '</label></div>');

            // Student view!
            if (has_capability("mod/grouptool:view_groups", $this->context)) {
                // Prepare formular-content for registration-action!
                foreach ($groups as $key => &$group) {
                    $registered = count($group->registered);
                    $grpsize = ($this->grouptool->use_size) ? $group->grpsize : "∞";

                    $grouphtml = html_writer::tag('span', get_string('registered', 'grouptool').
                                                          ": ".$registered."/".$grpsize,
                                                  ['class' => 'fillratio']);
                    if ($this->grouptool->use_queue) {
                        $queued = count($group->queued);
                        $grouphtml .= html_writer::tag('span', get_string('queued', 'grouptool').
                                                               " ".$queued,
                                                       ['class' => 'queued']);
                    }

                    // Could become a performance problem when groups fill up!
                    if (!empty($group->registered)) {
                        $regrank = $this->get_rank_in_queue($group->registered, $USER->id);
                    } else {
                        $regrank = false;
                    }
                    if (!empty($group->queued)) {
                        $queuerank = $this->get_rank_in_queue($group->queued, $USER->id);
                    } else {
                        $queuerank = false;
                    }

                    // We have to determine if we can show the members link!
                    $showmembers = $this->canshowmembers($group->agrpid, $regrank, $queuerank);
                    if ($showmembers) {
                        $grouphtml .= $this->render_members_link($group);
                    }

                    /* If we include inactive groups and there's someone registered in one of these,
                     * the label gets displayed incorrectly.
                     */

                    if (!empty($group->registered) && $this->is_registration_open()
                            && $this->get_rank_in_queue($group->registered, $userid) != false) {
                        // User is already registered --> unreg button!
                        if ($this->grouptool->allow_unreg && has_capability('mod/grouptool:register', $this->context)) {
                            $label = get_string('unreg', 'grouptool');
                            $buttonattr = [
                                    'type'  => 'submit',
                                    'name'  => 'unreg['.$group->agrpid.']',
                                    'value' => $group->agrpid,
                                    'class' => 'unregbutton btn btn-secondary'
                            ];
                            if ($regopen && ($userregs + $userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('registered_on_rank',
                                                                  'grouptool', $regrank),
                                                       ['class' => 'rank']);
                    } else if (!empty($group->queued) && $this->is_registration_open()
                        && $this->get_rank_in_queue($group->queued, $userid) != false) {
                        // We're sorry, but user's already queued in this group!
                        if ($this->grouptool->allow_unreg && has_capability('mod/grouptool:register', $this->context)) {
                            $label = get_string('unqueue', 'grouptool');
                            $buttonattr = [
                                    'type'  => 'submit',
                                    'name'  => 'unreg['.$group->agrpid.']',
                                    'value' => $group->agrpid,
                                    'class' => 'unregbutton btn btn-secondary'
                            ];
                            if ($regopen && ($userregs + $userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('queued_on_rank',
                                                                  'grouptool', $queuerank),
                                                       ['class' => 'rank']);
                    } else if ($this->grpmarked($group->agrpid)) {
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('grp_marked', 'grouptool'),
                                                       ['class' => 'rank']);
                    } else if ($this->is_registration_open() && $this->qualifies_for_groupchange($group->agrpid, $USER->id)
                            && has_capability('mod/grouptool:register', $this->context)) {
                        // Groupchange!
                        $label = get_string('change_group', 'grouptool');
                        if ($this->grouptool->use_size
                                && count($group->registered) >= $group->grpsize) {
                            $label .= ' (' . get_string('queue', 'grouptool') . ')';
                            $class = "btn-secondary";
                        } else {
                            $class = "btn-primary";
                        }
                        $buttonattr = [
                                'type'   => 'submit',
                                'name'  => 'reg['.$group->agrpid.']',
                                'value' => $group->agrpid,
                                'class' => 'regbutton btn '.$class
                        ];
                        $grouphtml .= html_writer::tag('button', $label, $buttonattr);

                    } else if ($this->is_registration_open()) {
                        $message = new stdClass();
                        $message->username = fullname($USER);
                        $message->groupname = $group->name;
                        $message->userid = $USER->id;

                        try {
                            try {
                                // Can be registered?
                                $this->check_can_be_registered($group, $userregs, $userqueues, $usermarks);

                                if (has_capability('mod/grouptool:register', $this->context)) {
                                    // Register button!
                                    $label = get_string('register', 'grouptool');
                                    $buttonattr = [
                                            'type' => 'submit',
                                            'name' => 'reg[' . $group->agrpid . ']',
                                            'value' => $group->agrpid,
                                            'class' => 'regbutton btn btn-primary'
                                    ];
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                }
                            } catch (\mod_grouptool\local\exception\exceedgroupsize $e) {
                                if (!$this->grouptool->use_queue) {
                                    throw new \mod_grouptool\local\exception\exceedgroupsize();
                                } else {
                                    if (has_capability('mod/grouptool:register', $this->context)) {
                                        // There's no place left in the group, so we try to queue the user!
                                        $this->can_be_queued($group->agrpid, $USER->id, $message);

                                        // Queue button!
                                        $label = get_string('queue', 'grouptool');
                                        $buttonattr = [
                                                'type' => 'submit',
                                                'name' => 'reg[' . $group->agrpid . ']',
                                                'value' => $group->agrpid,
                                                'class' => 'queuebutton btn btn-secondary'
                                        ];
                                        $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                    }
                                }
                            } catch (\mod_grouptool\local\exception\notenoughregs $e) {
                                /* The user has not enough registrations, queue entries or marks,
                                 * so we try to mark the user! (Exceptions get handled above!) */
                                list($queued, ) = $this->can_be_marked($group->agrpid, $USER->id, $message);
                                if (!$queued && has_capability('mod/grouptool:register', $this->context)) {
                                    // Register button!
                                    $label = get_string('register', 'grouptool');
                                    $buttonattr = [
                                            'type'  => 'submit',
                                            'name'  => 'reg['.$group->agrpid.']',
                                            'value' => $group->agrpid,
                                            'class' => 'regbutton btn btn-primary'
                                    ];
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                } else if (has_capability('mod/grouptool:register', $this->context)) {
                                    // Queue button!
                                    $label = get_string('queue', 'grouptool');
                                    $buttonattr = [
                                            'type'  => 'submit',
                                            'name'  => 'reg['.$group->agrpid.']',
                                            'value' => $group->agrpid,
                                            'class' => 'queuebutton btn btn-secondary'
                                    ];
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                }
                            }
                        } catch (\mod_grouptool\local\exception\exceedgroupqueuelimit $e) {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div', get_string('fullgroup', 'grouptool'),
                                    ['class' => 'rank']);
                        } catch (\mod_grouptool\local\exception\exceedgroupsize $e) {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div', get_string('fullgroup', 'grouptool'),
                                    ['class' => 'rank']);
                        } catch (\mod_grouptool\local\exception\exceeduserqueuelimit $e) {
                            // Too many queues!
                            $grouphtml .= html_writer::tag('div', get_string('max_queues_reached',
                                    'grouptool'),
                                                           ['class' => 'rank']);

                        } catch (\mod_grouptool\local\exception\exceeduserreglimit $e) {
                            $grouphtml .= html_writer::tag('div', get_string('max_regs_reached',
                                    'grouptool'),
                                                           ['class' => 'rank']);

                        } catch (\mod_grouptool\local\exception\registration $e) {
                            // No registration possible!
                            $grouphtml .= html_writer::tag('div', '', ['class' => 'rank']);
                        }
                    }

                    if (!empty($group->registered && $this->get_rank_in_queue($group->registered, $userid) != false)) {
                        // Find group conversation in order to display group message icon.
                        $coursecontext = context_course::instance($this->course->id);
                        $conversation = \core_message\api::get_conversation_by_area('core_group',
                            'groups', $group->id, $coursecontext->id);
                        if (!empty($conversation)) {
                            $grouphtml .= html_writer::link('#', $OUTPUT->pix_icon('t/message',
                                get_string('open_group_message', 'grouptool')),
                                ['id' => 'group-message-button', 'data-conversationid' => $conversation->id]);
                            self::messagegroup_requirejs();
                        }
                    }

                    $grouptext = html_writer::tag('h2', $group->name, ['class' => 'panel-title']);
                    $grouppicture = '';
                    if (get_config('mod_grouptool', 'show_add_info')) {
                        if (isset($group->description)) {
                            $grouptext .=
                                html_writer::tag('div', $group->description, ['class' => 'panel-desc']);
                        }

                        $groupobj = groups_get_group($group->id);
                        $pictureout = print_group_picture($groupobj, $this->course->id, true, true);
                        if (empty($pictureout)) {
                            $pictureurl = new moodle_url('/user/index.php',
                                ['id' => $this->course->id, 'group' => $group->id]);
                            $pictureobj = html_writer::img($OUTPUT->image_url('g/g1')->out(false),
                                $group->name, ['title' => $group->name]); // default image.
                            $pictureout = html_writer::link($pictureurl, $pictureobj);
                        }
                        if (isset($pictureout)) {
                            $grouppicture = html_writer::tag('div', $pictureout, ['class' => 'panel-picture']);
                        }
                    }
                    $grouptext = $grouptext . html_writer::tag('div', $grouphtml, ['class' => 'panel-body']);
                    $grouptext = html_writer::tag('div', $grouptext, ['class' => 'panel-text']);
                    $grouphtml = $grouppicture . $grouptext;

                    if ($regrank !== false) {
                        $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert-success');
                    } else if ($queuerank !== false) {
                        $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert-warning');
                    } else if (($this->grouptool->use_size) && ($registered >= $group->grpsize) && $regopen) {
                        $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert-error group-full');
                    } else {
                        $classes = 'generalbox group empty';
                        if (($this->grouptool->use_size) && ($registered >= $group->grpsize)) {
                            $classes .= ' group-full';
                        }
                        $grouphtml = $OUTPUT->box($grouphtml, $classes);
                    }
                    $mform->addElement('html', $grouphtml);
                }
            }

            if ($this->grouptool->show_members) {
                $params = new stdClass();
                $params->courseid = $this->grouptool->course;
                $params->showidnumber  = has_capability('mod/grouptool:view_regs_group_view', $this->context)
                        || has_capability('mod/grouptool:view_regs_course_view', $this->context);
                $helpicon = new help_icon('status', 'mod_grouptool');
                // Add the help-icon-data to the form element as data-attribute so we use less params for the JS-call!
                $mform->updateAttributes(['data-statushelp' => json_encode($helpicon->export_for_template($OUTPUT))]);
                // Require the JS to show group members (just once)!
                $PAGE->requires->js_call_amd('mod_grouptool/memberspopup', 'initializer', [$params]);
            }
            $mform->display();
        }
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

        if ($regrank === null
            || $queuerank === null) {
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

        switch($this->grouptool->show_members) {
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
     * Force enrol a user in this course as student to be able to import into group or register for group!
     *
     * @param int $userid ID of user to force enrol!
     * @throws coding_exception Thrown if smthg very unexpected happened (couldn't instantiate manual enrol instance or similar)
     * @throws dml_exception
     */
    protected function force_enrol_student($userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/enrol/manual/locallib.php');
        require_once($CFG->libdir.'/accesslib.php');
        if (!$enrolmanual = enrol_get_plugin('manual')) {
            throw new coding_exception(get_string('cant_enrol', 'grouptool'));
        }
        if (!$instance = $DB->get_record('enrol', [
                'courseid' => $this->course->id,
                'enrol'    => 'manual'
        ], '*', IGNORE_MISSING)) {
            if ($enrolmanual->add_default_instance($this->course)) {
                $instance = $DB->get_record('enrol', [
                        'courseid' => $this->course->id,
                        'enrol'    => 'manual'
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
     * import users into a certain moodle-group and enrole them if not allready enroled
     *
     * @param int[] $groups array of ids of groups to import into
     * @param stdClass $data from form in import tab (textfield with idnumbers and group-selection)
     * @param int[] $ignored which user ids to ignore when importing (used if conflicting users should be ignored)
     * @param bool $forceregistration Force registration in grouptool
     * @param bool $previewonly optional preview only, don't take any action
     * @return array ($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     */
    public function import($groups, $data, $ignored = [], $forceregistration = false, $previewonly = false) {
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
        $columns = $DB->get_columns('user');

        $agrp = [];
        foreach ($groups as $group) {
            $agrp[$group] = $DB->get_field('grouptool_agrps', 'id', [
                    'grouptoolid' => $this->grouptool->id,
                    'groupid'     => $group
            ], IGNORE_MISSING);
            if (!$DB->record_exists('grouptool_agrps', [
                    'grouptoolid' => $this->grouptool->id,
                    'groupid'     => $group,
                    'active'      => 1
            ])) {
                $message .= $OUTPUT->notification(get_string('import_in_inactive_group_warning', 'grouptool',
                                                             $groupinfo[$group]->name), \core\output\notification::NOTIFY_ERROR);
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
                        $message .= html_writer::tag('div', $OUTPUT->notification(get_string('overflowwarning',
                                'grouptool', $cur), \core\output\notification::NOTIFY_ERROR));
                    }
                } else {
                    if (($cur->regs + $usercnt) > $cur->globalsize && $previewonly) {
                        $message .= html_writer::tag('div', $OUTPUT->notification(get_string('overflowwarning',
                                'grouptool', $cur), \core\output\notification::NOTIFY_ERROR));
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
            $pbar->update($processed, $count, get_string('import_progress_search', 'grouptool').' '.$user);
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
                        $row->cells[] = new html_table_cell($OUTPUT->notification($e->getMessage(),
                                \core\output\notification::NOTIFY_ERROR));
                    } catch (Throwable $t) {
                        $row->cells[] = new html_table_cell($OUTPUT->notification($t->getMessage(),
                                \core\output\notification::NOTIFY_ERROR));
                    }
                }
                foreach ($groups as $group) {
                    $data = [
                            'id' => $userinfo->id,
                            'idnumber' => $userinfo->idnumber,
                            'fullname' => fullname($userinfo),
                            'groupname' => $groupinfo[$group]->name
                    ];
                    if (!$previewonly && $userinfo) {
                        $pbar->update($processed, $count, get_string('import_progress_import',
                                        'grouptool').' '.fullname($userinfo).'...');

                        if (in_array($userinfo->id, $ignored[$group])) {
                            // We ignore the user for this import in this group!
                            $cell = new html_table_cell(get_string('import_skipped', 'grouptool', $data));
                            $cell->attributes['class'] = 'info';
                            $row->cells[] = $cell;
                            continue;
                        }

                        if (!groups_add_member($group, $userinfo->id)) {
                            $error = true;
                            $notification = $OUTPUT->notification(get_string('import_user_problem', 'grouptool',
                                    $data), \core\output\notification::NOTIFY_ERROR);
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
                            $newgrpdata = $DB->get_record_sql('SELECT MAX(sort_order), MAX(grpsize)
                                                                 FROM {grouptool_agrps}
                                                               WHERE grouptoolid = ?',
                                                              [$this->grouptool->id]);
                            // Insert agrp-entry for this group (even if it's not active)!
                            $agrp[$group] = new stdClass();
                            $agrp[$group]->grouptoolid = $this->grouptool->id;
                            $agrp[$group]->groupid = $group;
                            $agrp[$group]->active = 0;
                            $agrp[$group]->sort_order = $newgrpdata->sortorder + 1;
                            $agrp[$group]->grpsize = $newgrpdata->grpsize;
                            $agrp[$group]->id = $DB->insert_record('grouptool_agrps', $agrp[$group]);
                            \mod_grouptool\event\agrp_created::create_from_object($this->cm, $agrp[$group])->trigger();
                            $notification = $OUTPUT->notification(get_string('import_in_inactive_group_rejected',
                                    'grouptool', $agrp[$group]), \core\output\notification::NOTIFY_ERROR);
                            $row->cells[] = $notification;
                            $row->attributes['class'] = 'error';
                            $agrp[$group] = $agrp[$group]->id;
                        } else if ($forceregistration && !empty($agrp[$group])
                                   && !$DB->record_exists_select('grouptool_registered',
                                                                 "modified_by >= 0 AND agrpid = :agrpid AND userid = :userid",
                                                                 ['agrpid' => $agrp[$group], 'userid' => $userinfo->id])) {
                            if ($reg = $DB->get_record('grouptool_registered', [
                                    'agrpid' => $agrp[$group],
                                    'userid' => $userinfo->id,
                                    'modified_by' => -1
                            ], IGNORE_MISSING)) {
                                // If user is marked, we register him right now!
                                $reg->modified_by = $USER->id;
                                $DB->update_record('grouptool_registered', $reg);
                                // TODO do we have to delete his marks and queues if theres enough registrations?
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

                            \mod_grouptool\event\user_imported::import_forced($this->cm, $reg->id, $agrp[$group],
                                                                              $group, $userinfo->id)->trigger();
                        } else {
                            // Delete every queue entry here!
                            $DB->delete_records('grouptool_queued', ['agrpid' => $agrp[$group], 'userid' => $userinfo->id]);

                            if (!$forceregistration) {
                                // Trigger the event!
                                \mod_grouptool\event\user_imported::import($this->cm, $group, $userinfo->id)->trigger();
                            }
                        }
                    } else if ($userinfo) {
                        if ($DB->record_exists_select('grouptool_queued', "agrpid = :agrpid AND userid = :userid",
                                                      ['agrpid' => $agrp[$group], 'userid' => $userinfo->id])) {
                            $options = [
                                    -1 => get_string('move_user', 'grouptool'),
                                    $userinfo->id => get_string('skip_user_import', 'grouptool')
                            ];
                            $cell = get_string('import_conflict_user_queued', 'grouptool', $data).
                                    html_writer::tag('div',
                                            html_writer::select($options, "ignored_{$group}[]", -1, false));
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
        // Update completion state if submission is changed
        $completion = new completion_info($this->course);
        if ($completion->is_enabled($this->cm) && $this->grouptool->completionregister) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }
        return [$error, $message];
    }

    /**
     * view import-tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function view_import() {
        global $PAGE, $OUTPUT;
        require_capability('mod/grouptool:register_students', $this->context);

        $id = $this->cm->id;
        $form = new \mod_grouptool\import_form(null, ['id' => $id]);

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $groups = required_param_array('group', PARAM_INT);
            $data = required_param('data', PARAM_NOTAGS);
            $forceregistration = optional_param('forceregistration', 0, PARAM_BOOL);
            $ignored = [];
            foreach ($groups as $group) {
                $ignored[$group] = optional_param_array("ignored_$group", [-1 => -1], PARAM_INT);
            }
            list($error, $message) = $this->import($groups, $data, $ignored, $forceregistration);

            if (!empty($error)) {
                $message = $OUTPUT->notification(get_string('ignored_not_found_users', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR).html_writer::empty_tag('br').$message;
            }
            echo html_writer::tag('div', $message, ['class' => 'centered']);
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            list($error, $confirmmessage) = $this->import($fromform->groups, $fromform->data, [],
                                                          $fromform->forceregistration, true);
            $formdata = [
                    'id'                => $id,
                    'groups'            => $fromform->groups,
                    'data'              => $fromform->data,
                    'forceregistration' => $fromform->forceregistration,
                    'confirmmessage'    => $confirmmessage
            ];
            // The form data will be fetched through required_param()! TODO gotta refactor this in the future!
            $confirmform = new \mod_grouptool\import_confirm_form($PAGE->url, $formdata);

            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered');
            if ($error) {
                echo $OUTPUT->notification(get_string('ignoring_not_found_users', 'grouptool'),
                        \core\output\notification::NOTIFY_ERROR);
            }

            $confirmform->display();

        } else {
            $form->display();
        }

    }

    /**
     * view unregister-tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function view_unregister() {
        global $PAGE, $OUTPUT;
        require_capability('mod/grouptool:unregister_students', $this->context);

        $id = $this->cm->id;
        $form = new \mod_grouptool\unregister_form(null, ['id' => $id]);

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $groups = required_param_array('group', PARAM_INT);
            $data = required_param('data', PARAM_NOTAGS);
            $unregfrommgroups = optional_param('unregfrommgroups', 1, PARAM_BOOL);
            $ignored = [];
            foreach ($groups as $group) {
                $ignored[$group] = optional_param_array("ignored_$group", [-1 => -1], PARAM_INT);
            }
            list($error, $message) = $this->unregister($groups, $data, true, false, $unregfrommgroups);

            if (!empty($error)) {
                $message = $OUTPUT->notification(get_string('ignored_not_found_users_unregister', 'grouptool'),
                                \core\output\notification::NOTIFY_ERROR).html_writer::empty_tag('br').$message;
            }
            echo html_writer::tag('div', $message, ['class' => 'centered']);
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            list($error, $confirmmessage) =
                $this->unregister($fromform->groups, $fromform->data, true,
                    true);
            $formdata = [
                'id'                => $id,
                'groups'            => $fromform->groups,
                'data'              => $fromform->data,
                'unregfrommgroups'  => $fromform->unregfrommgroups,
                'confirmmessage'    => $confirmmessage
            ];
            // The form data will be fetched through required_param()! TODO gotta refactor this in the future!
            $confirmform = new \mod_grouptool\unregister_confirm_form($PAGE->url, $formdata);

            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered');
            if ($error) {
                echo $OUTPUT->notification(get_string('ignoring_not_found_users', 'grouptool'),
                        \core\output\notification::NOTIFY_ERROR);
            }

            $confirmform->display();

        } else {
            $form->display();
        }

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
    public function group_overview_table($groupingid = 0, $groupid = 0, $onlydata = false, $includeinactive = false) {
        global $OUTPUT, $CFG, $DB;

        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $downloadurl = new moodle_url('/mod/grouptool/download.php', [
            'id'          => $this->cm->id,
            'groupingid'  => $groupingid,
            'groupid'     => $groupid,
            'orientation' => $orientation,
            'sesskey'     => sesskey(),
            'tab'         => 'overview',
            'inactive'    => $includeinactive
        ]);
        $return = [];

        // We just get an overview and fetch data later on a per group basis to save memory!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid,
                true, $includeinactive);
        $groupinfo = groups_get_all_groups($this->grouptool->course);
        $userinfo = [];
        $syncstatus = $this->get_sync_status();
        if (!$onlydata && count($agrps)) {
            // Global-downloadlinks!
            echo $this->get_download_links($downloadurl);
        }

        foreach ($agrps as $agrp) {
            // We give each group 30 seconds (minimum) and hope it doesn't time out because of no output in case of download!
            core_php_time_limit::raise(30);
            $groupdata = new stdClass();
            $groupdata->name = $groupinfo[$agrp->id]->name.($agrp->active ? '' : ' ('.get_string('inactive').')');

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
                    $size = !empty($this->grouptool->grpsize) ? $this->grouptool->grpsize : get_config('mod_grouptool',
                            'grpsize');
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
            $groupdata->useridentity = self::convert_associative_array_into_nested_index_array(self::get_useridentity_fields());

            $statushelp = new help_icon('status', 'mod_grouptool');
            if (!$onlydata) {
                $groupdata->statushelp = $statushelp->export_for_template($OUTPUT);
                // Format will be added in template!
                $groupdownloadurl = new moodle_url($downloadurl, ['groupid' => $groupinfo[$agrp->id]->id]);
                $groupdata->downloadurl = $groupdownloadurl->out(false);
            }

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = \core_user\fields::for_name()->get_required_fields();
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
                        $row['useridentityvalues'] = self::convert_associative_array_into_nested_index_array(
                                $this->get_namefields_useridentity($row, $userinfo[$curuser]));
                        $this->add_namefields_useridentity($row, $userinfo[$curuser]);
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
                        $row['useridentityvalues'] = self::convert_associative_array_into_nested_index_array(
                                $this->get_namefields_useridentity($row, $userinfo[$curuser]));
                        $this->add_namefields_useridentity($row, $userinfo[$curuser]);
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
                        $row['useridentityvalues'] = self::convert_associative_array_into_nested_index_array(
                                $this->get_namefields_useridentity($row, $userinfo[$curuser]));
                        $this->add_namefields_useridentity($row, $userinfo[$curuser]);
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
                    $fullname = fullname($userinfo[$curuser]);
                    $rank = $this->get_rank_in_queue($queuedlist, $curuser);

                    $row = [];
                    $row['userid'] = $curuser;
                    $row['rank'] = $rank;
                    $row['name'] = $fullname;
                    $row['useridentityvalues'] = self::convert_associative_array_into_nested_index_array(
                            $this->get_namefields_useridentity($row, $userinfo[$curuser]));
                    $this->add_namefields_useridentity($row, $userinfo[$curuser]);
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
            $boxcontent = $OUTPUT->notification(get_string('no_data_to_display', 'grouptool'),
                                                \core\output\notification::NOTIFY_ERROR);
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

    /**
     * Add additional user fields and useridentity fields to the row (at least adds idnumber and email to be displayed).
     *
     * @param mixed[] $row Associative array with table data for this user
     * @param stdClass $user the user's DB record
     */
    protected function add_namefields_useridentity(&$row, $user) {
        global $CFG;
        $namefields = \core_user\fields::for_name()->get_required_fields();
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
    protected function get_namefields_useridentity($row, $user) {
        global $CFG;
        $namefields = \core_user\fields::for_name()->get_required_fields();
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
     * Get showuseridentity itentifiers and their display text on the current instance
     *
     * @return array Identifiers in showuseridentity and their display names
     * @throws coding_exception
     */
    public static function get_useridentity_fields() {
        global $CFG;
        $useridentityfields = explode(',', $CFG->showuseridentity);

        // Set default values to idnumber and email in no showuseridentity setting is given.
        if (empty($useridentityfields)) {
            $useridentityfields = ['idnumber', 'email'];
        }

        $useridentity = [];
        foreach ($useridentityfields as $identifier) {
            $useridentity[$identifier] = \core_user\fields::get_display_name($identifier);
        }
        return $useridentity;
    }

    /**
     * Helper function to convert a given associative array into a nested index array so it can be iterated thorough by mustache.
     *
     * @param array $inarray Associative array that should be converted ($key => $value)
     * @return array Nested array in the format [['key' => $key, 'value' => $value]]
     */
    public static function convert_associative_array_into_nested_index_array($inarray) {
        $outarray = [];
        foreach ($inarray as $key => $value) {
            $outarray[] = ['key' => $key, 'value' => $value];
        }
        return $outarray;
    }

    /**
     * outputs generated pdf-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_pdf($groupid=0, $groupingid=0, $includeinactive=false) {
        $data = $this->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupid)) {
            $viewname = groups_get_group_name($groupid);
        } else {
            if (!empty($groupingid)) {
                $viewname = groups_get_grouping_name($groupingid);
            } else {
                $viewname = get_string('all').' '.get_string('groups');
            }
        }

        $pdf = new \mod_grouptool\pdf('overview', $coursename, $grouptoolname, $timeavailable, $timedue,
            $viewname);

        if (count($data) > 0) {

            foreach ($data as $group) {
                $groupname = $group->name;
                $groupinfo = get_string('total').' '.$group->total.' / '.
                             get_string('registered', 'grouptool').' '.$group->registered.' / '.
                             get_string('queued', 'grouptool').' '.$group->queued.' / '.
                             get_string('free', 'grouptool').' '.$group->free;
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = isset($group->mreg_data) ? $group->mreg_data : [];
                $pdf->add_grp_overview($groupname, $groupinfo, $regdata, $queuedata, $mregdata);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'B', 'L', false, 1, null, null,
                        true, 1, true, false, $pdf->getLastH(), 'M', true);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'T', 'L', false, 1, null, null,
                        true, 1, true, false, $pdf->getLastH(), 'M', true);
            }
            $pdf->SetFontSize(8);
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('status', 'grouptool'), '', 'L',
                    false, 1, null, null, true, 1, true, false, $pdf->getLastH(),
                    'M', true);
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $pdf->MultiCell(0, $pdf->getLastH(), strip_tags($legendline), '', 'L', false, 1,
                                null, null, true, 1, true, false, $pdf->getLastH(),
                        'M', true);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'), 'B',
                            'LRTB', false, 1, null, null, true, 1, true, false,
                    $pdf->getLastH(), 'M', true);
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').' '.get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.pdf");
        $pdf->Output($filename, 'D');
        exit();
    }

    /**
     * returns raw data for overview
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @return array|int|string raw data
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_raw($groupid=0, $groupingid=0, $includeinactive=false) {
        return $this->group_overview_table($groupid, $groupingid, true, $includeinactive);
    }

    /**
     * outputs generated txt-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_txt($groupid=0, $groupingid=0, $includeinactive=false) {
        ob_start();
        $lines = [];
        $groups = $this->group_overview_table($groupingid, $groupid, true, $includeinactive);
        if (count($groups) > 0) {
            $lines[] = "*** ".get_string('status', 'grouptool')."\n";
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $lines[] = "***\t".strip_tags($legendline);
            }
            $lines[] = "";

            foreach ($groups as $group) {
                $lines[] = $group->name;
                $lines[] = "\t".get_string('total').' '.$group->total." / ".
                                get_string('registered', 'grouptool').' '.$group->registered." / ".
                                get_string('queued', 'grouptool').' '.$group->queued." / ".
                                get_string('free', 'grouptool').' '.$group->free;
                if (isset($group->mreg_data)) {
                    $mregs = count($group->mreg_data);
                } else {
                    $mregs = 0;
                }
                if ($group->registered > 0) {
                    $lines[] = "\t".get_string('registrations', 'grouptool');
                    foreach ($group->reg_data as $reg) {
                        $lines[] = "\t\t".$reg['status']."\t".$reg['name'].
                                self::get_useridentity_values_for_txt($reg['useridentityvalues']);
                    }
                } else if ($mregs == 0) {
                    $lines[] = "\t\t--".get_string('no_registrations', 'grouptool')."--";
                }
                if ($mregs >= 1) {
                    foreach ($group->mreg_data as $mreg) {
                        $lines[] = "\t\t?\t".$mreg['name']."\t".
                                self::get_useridentity_values_for_txt($mreg['useridentityvalues']);
                    }
                }
                if ($group->queued > 0) {
                    $lines[] = "\t".get_string('queue', 'grouptool');
                    foreach ($group->queue_data as $queue) {
                        $lines[] = "\t\t".$queue['rank']."\t".$queue['name']."\t".
                                   self::get_useridentity_values_for_txt($queue['useridentityvalues']);
                    }
                } else {
                    $lines[] = "\t\t--".get_string('nobody_queued', 'grouptool')."--";
                }
                $lines[] = "";
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').'_'.get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.txt");
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header('Content-Disposition: attachment; filename="'.str_replace([' ', '"'], ['_', ''], $filename).
               '"; filename*="'.rawurlencode($filename).'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * Returns a ready to print string containing all given useridentity values separated by tabstops
     *
     * @param array $values array Values that should be separated
     * @return string
     */
    private static function get_useridentity_values_for_txt($values) {
        $outstring = '';
        foreach ($values as $value) {
            $outstring .= "\t".$value['value'];
        }
        return $outstring;
    }

    /**
     * Fill workbook (either XLSX or ODS) with data
     *
     * @param MoodleExcelWorkbook|MoodleODSWorkbook $workbook workbook to put data into
     * @param stdClass[] $groups which groups from whom to include data
     * @param string[] $collapsed array with collapsed columns
     * @throws coding_exception
     */
    private function overview_fill_workbook(&$workbook, $groups, $collapsed=[]) {
        global $CFG;
        if (count($groups) > 0) {

            $columnwidth = [7, 22, 14, 17]; // Unit: mm!

            $allgroupsworksheet = false;
            if (count($groups) > 1) {
                // General information? unused at the moment!
                $allgroupsworksheet = $workbook->add_worksheet(get_string('all'));
                // The standard column widths: 7 - 22 - 14 - 17!
                $allgroupsworksheet->set_column(0, 0, $columnwidth[0]);
                $allgroupsworksheet->set_column(1, 1, $columnwidth[1]);
                $allgroupsworksheet->set_column(2, 2, $columnwidth[2]);
                $allgroupsworksheet->set_column(3, 3, $columnwidth[3]);
            }

            $legendworksheet = $workbook->add_worksheet(get_string('status', 'grouptool').' '.
                                                        get_string('help'));
            $legendworksheet->write_string(0, 0, get_string('status', 'grouptool').' '.
                                                 get_string('help'));
            $line = 1;
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                if (strstr($legendline, "</span>")) {
                    $lineelements = explode("</span>", $legendline);
                    $legendworksheet->write_string($line, 0, strip_tags($lineelements[0]));
                    $legendworksheet->write_string($line, 1, strip_tags($lineelements[1]));
                    $line++;
                }
            }

            // Add content for all groups!
            $groupworksheets = [];

            // Prepare formats!
            $headlineprop = [
                    'size' => 14,
                    'bold' => 1,
                    'align' => 'center'
            ];
            $headlineformat = $workbook->add_format($headlineprop);
            $groupinfoprop1 = [
                    'size' => 10,
                    'bold' => 1,
                    'align' => 'left'
            ];
            $groupinfoprop2 = $groupinfoprop1;
            unset($groupinfoprop2['bold']);
            $groupinfoprop2['italic'] = true;
            $groupinfoprop2['align'] = 'right';
            $groupinfoformat1 = $workbook->add_format($groupinfoprop1);
            $groupinfoformat2 = $workbook->add_format($groupinfoprop2);
            $regheadprop = [
                    'size' => 10,
                    'align' => 'center',
                    'bold' => 1,
                    'bottom' => 2
            ];
            $regentryprop = [
                    'size' => 10,
                    'align' => 'left'
            ];
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regheadformat = $workbook->add_format($regheadprop);
            $regheadformat->set_right(1);
            $regheadlast = $workbook->add_format($regheadprop);

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_top(1);
            $regentryformat->set_bottom(0);
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_top(1);
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('center');
            $queueentryformat = $workbook->add_format($queueentryprop);
            $queueentryformat->set_right(1);
            $queueentryformat->set_top(1);
            $queueentryformat->set_bottom(false);
            $queueentrylast = $workbook->add_format($queueentryprop);
            $queueentrylast->set_top(1);
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('center');

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = \core_user\fields::for_name()->get_required_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = [
                    0               => 26,
                    'fullname'      => 26,
                    'firstname'     => 20,
                    'surname'       => 20,
                    'email'         => 35,
                    'registrations' => 47,
                    'queues_rank'   => 7.5,
                    'queues_grp'    => 47
            ]; // Unit: mm!

            // Start row for groups general sheet!
            $j = 0;
            $columncount = 1 + count($namefields);
            if (!empty($CFG->showuseridentity)) {
                $fields = explode(',', $CFG->showuseridentity);
                $columncount += count($fields);
            } else {
                $columncount += 2;
            }
            foreach ($groups as $key => $group) {
                // Add worksheet for each group!
                $groupworksheets[$key] = $workbook->add_worksheet($group->name);

                $groupname = $group->name;
                $groupinfo = [];
                $groupinfo[] = [get_string('total'), $group->total];
                $groupinfo[] = [get_string('registered', 'grouptool'), $group->registered];
                $groupinfo[] = [get_string('queued', 'grouptool'), $group->queued];
                $groupinfo[] = [get_string('free', 'grouptool'), $group->free];
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = isset($group->mreg_data) ? $group->mreg_data : [];
                // Groupname as headline!
                $groupworksheets[$key]->write_string(0, 0, $groupname, $headlineformat);
                $groupworksheets[$key]->merge_cells(0, 0, 0, $columncount - 1);
                if ($allgroupsworksheet !== false) {
                    $allgroupsworksheet->write_string($j, 0, $groupname, $headlineformat);
                    $allgroupsworksheet->merge_cells($j, 0, $j, $columncount - 1);
                }

                // Groupinfo on top!
                $groupworksheets[$key]->write_string(2, 0, $groupinfo[0][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(2, 0, 2, 1);
                $groupworksheets[$key]->write(2, 2, $groupinfo[0][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(3, 0, $groupinfo[1][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(3, 0, 3, 1);
                $groupworksheets[$key]->write(3, 2, $groupinfo[1][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(4, 0, $groupinfo[2][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(4, 0, 4, 1);
                $groupworksheets[$key]->write(4, 2, $groupinfo[2][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(5, 0, $groupinfo[3][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(5, 0, 5, 1);
                $groupworksheets[$key]->write(5, 2, $groupinfo[3][1], $groupinfoformat2);
                if ($allgroupsworksheet !== false) {
                    $allgroupsworksheet->write_string($j + 2, 0, $groupinfo[0][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 2, 0, $j + 2, 1);
                    $allgroupsworksheet->write($j + 2, 2, $groupinfo[0][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 3, 0, $groupinfo[1][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 3, 0, $j + 3, 1);
                    $allgroupsworksheet->write($j + 3, 2, $groupinfo[1][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 4, 0, $groupinfo[2][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 4, 0, $j + 4, 1);
                    $allgroupsworksheet->write($j + 4, 2, $groupinfo[2][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string($j + 5, 0, $groupinfo[3][0],
                                                      $groupinfoformat1);
                    $allgroupsworksheet->merge_cells($j + 5, 0, $j + 5, 1);
                    $allgroupsworksheet->write($j + 5, 2, $groupinfo[3][1], $groupinfoformat2);
                }

                // Registrations and queue headline!
                // First the headline!
                $k = 0;
                $groupworksheets[$key]->write_string(7, $k, get_string('status', 'grouptool'),
                                                      $regheadformat);
                $k++; // ...k = 1!

                // First we output every namefield from used by fullname in exact the defined order!
                foreach ($namefields as $namefield) {
                    $groupworksheets[$key]->write_string(7, $k, \core_user\fields::get_display_name($namefield), $regheadformat);
                    $hidden = in_array($namefield, $collapsed) ? true : false;
                    $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                    $k++;
                }
                // ...k = n!
                if (!empty($CFG->showuseridentity)) {
                    $fields = explode(',', $CFG->showuseridentity);
                    $curfieldcount = 1;
                    foreach ($fields as $field) {
                        if ($curfieldcount == count($fields)) {
                            $groupworksheets[$key]->write_string(7, $k, \core_user\fields::get_display_name($field), $regheadlast);
                        } else {
                            $groupworksheets[$key]->write_string(7, $k, \core_user\fields::get_display_name($field),
                                    $regheadformat);
                            $curfieldcount++;
                        }
                        $hidden = in_array($field, $collapsed) ? true : false;
                        $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                        $groupworksheets[$key]->set_column($k, $k, $columnwidth[$field], null, $hidden);
                        $k++; // ...k = n+x!
                    }
                } else {
                    $groupworksheets[$key]->write_string(7, $k, \core_user\fields::get_display_name('idnumber'),
                            $regheadformat);
                    $hidden = in_array('idnumber', $collapsed) ? true : false;
                    $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                    $k++; // ...k = n+1!

                    $groupworksheets[$key]->write_string(7, $k, \core_user\fields::get_display_name('email'), $regheadlast);
                    $hidden = in_array('email', $collapsed) ? true : false;
                    $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['email'], null, $hidden);
                    $k++; // ...k = n+2!
                }

                if ($allgroupsworksheet !== false) {
                    $k = 0;
                    $allgroupsworksheet->write_string($j + 7, $k, get_string('status', 'grouptool'),
                                                      $regheadformat);
                    $k++;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $allgroupsworksheet->write_string($j + 7, $k, \core_user\fields::get_display_name($namefield),
                                $regheadformat);
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k = n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        $curfieldcount = 1;
                        foreach ($fields as $field) {
                            if ($curfieldcount == count($fields)) {
                                $allgroupsworksheet->write_string($j + 7, $k, \core_user\fields::get_display_name($field),
                                        $regheadlast);
                            } else {
                                $allgroupsworksheet->write_string($j + 7, $k, \core_user\fields::get_display_name($field),
                                        $regheadformat);
                                $curfieldcount++;
                            }
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $allgroupsworksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $allgroupsworksheet->write_string($j + 7, $k, \core_user\fields::get_display_name('idnumber'),
                                $regheadformat);
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $k++; // ...k = n+1!

                        $allgroupsworksheet->write_string($j + 7, $k, \core_user\fields::get_display_name('email'), $regheadlast);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $k++; // ...k = n+2!
                    }
                }
                // Now the registrations!
                $i = 0;
                if (!empty($regdata)) {
                    foreach ($regdata as $reg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, $reg['status'],
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['status'],
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                } else if (count($mregdata) == 0) {
                    $groupworksheets[$key]->write_string(8 + $i, 0,
                                                         get_string('no_registrations',
                                                                    'grouptool'),
                                                         $noregentriesformat);
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($allgroupsworksheet !== false) {
                        $allgroupsworksheet->write_string($j + 8 + $i, 0,
                                                          get_string('no_registrations',
                                                                     'grouptool'),
                                                          $noregentriesformat);
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }

                if (count($mregdata) >= 1) {
                    foreach ($mregdata as $mreg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, '?',
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, '?',
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                }
                // Don't forget the queue!
                if (!empty($queuedata)) {
                    foreach ($queuedata as $queue) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(8 + $i, $k, $queue['rank'],
                                                             $regentryformat);
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k = n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k = n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['idnumber'], $regentryformat);
                            $k++; // ...k = n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['email'], $regentrylast);
                            $k++; // ...k = n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['rank'],
                                                              $regentryformat);
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k = n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k = n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['idnumber'], $regentryformat);
                                $k++; // ...k = n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['email'], $regentrylast);
                                $k++; // ...k = n+2!
                            }
                        }
                        $i++;
                    }
                } else {
                    $groupworksheets[$key]->write_string(8 + $i, 0,
                                                         get_string('nobody_queued', 'grouptool'),
                                                         $noqueueentriesformat);
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($allgroupsworksheet !== false) {
                        $allgroupsworksheet->write_string($j + 8 + $i, 0,
                                                          get_string('nobody_queued',
                                                                     'grouptool'),
                                                          $noqueueentriesformat);
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }
                $j += 9 + $i;    // One row space between groups!
            }

        }
    }

    /**
     * outputs generated ods-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_ods($groupid=0, $groupingid=0, $includeinactive=false) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('group').' '.get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.ods");
        $workbook = new MoodleODSWorkbook("-");

        $groups = $this->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * outputs generated xlsx-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_xlsx($groupid = 0, $groupingid = 0, $includeinactive=false) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_group_name($groupid).'_'.
                                       get_string('overview', 'grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_grouping_name($groupingid).'_'.
                                       get_string('overview', 'grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       get_string('group').' '.get_string('overview', 'grouptool'));
        }
        $filename = clean_filename("$filename.xlsx");
        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $groups = $this->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * get object containing informatino about syncronisation of active-groups with moodle-groups
     *
     * @param int $grouptoolid optional get stats for this grouptool-instance
     *                                  uses $this->instance if zero
     * @return array (global out of sync, array of objects with sync-status for each group)
     * @throws dml_exception
     */
    private function get_sync_status($grouptoolid = 0) {
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
     * push in grouptool registered users to moodle-groups
     *
     * @param int $groupid optional only for this group
     * @param int $groupingid optional only for this grouping
     * @param bool $previewonly optional get only the preview
     * @return array($error, $message)
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function push_registrations($groupid=0, $groupingid=0, $previewonly=false) {
        global $DB, $OUTPUT;

        // Trigger the event!
        \mod_grouptool\event\registration_push_started::create_from_object($this->cm)->trigger();

        $userinfo = get_enrolled_users($this->context);
        $return = [];
        // Get active groups filtered by groupid, grouping_id, grouptoolid!
        $agrps = $this->get_active_groups(true, false, 0, $groupid, $groupingid);
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
                                $this->force_enrol_student($reg->userid);
                            } catch (Exception $e) {
                                $return[] = $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
                            } catch (Throwable $t) {
                                $return[] = $OUTPUT->notification($t->getMessage(), \core\output\notification::NOTIFY_ERROR);
                            }
                        }
                        if (groups_add_member($groupid, $reg->userid)) {
                            $return[] = html_writer::tag('div', get_string('added_member', 'grouptool',
                                    $info), ['class' => 'notifysuccess']);
                        } else {
                            $return[] = html_writer::tag('div', get_string('could_not_add', 'grouptool',
                                    $info), ['class' => 'notifyproblem']);
                        }
                    } else {
                        $return[] = html_writer::tag('div', get_string('add_member', 'grouptool',
                                $info), ['class' => 'notifysuccess']);
                    }
                } else {
                    $return[] = html_writer::tag('div', get_string('already_member', 'grouptool',
                            $info), ['class' => 'ignored']);
                }
            }
        }
        switch (count($return)) {
            default:
                return [false, implode("<br />\n", $return)];
                break;
            case 1:
                return [false, current($return)];
                break;
            case 0:
                return [true, get_string('nothing_to_push', 'grouptool')];
                break;
        }

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
    private function render_members_link($group) {
        global $CFG, $DB;

        $output = get_string('show_members', 'grouptool');

        // Now create the link around it - we need https on loginhttps pages!
        $url = new moodle_url($CFG->httpswwwroot.'/mod/grouptool/showmembers.php', [
                'agrpid'    => $group->agrpid,
                'contextid' => $this->context->id
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

        $showidnumber = has_capability('mod/grouptool:view_regs_group_view', $this->context)
                        || has_capability('mod/grouptool:view_regs_course_view', $this->context);
        $userfields = \core_user\fields::for_name()->get_sql("", false, "", "", false)->selects;
        if ($showidnumber) {
            $fields = "id,idnumber,".$userfields;
        } else {
            $fields = "id,".$userfields;
        }
        // Cache needed user records right now!
        $users = $DB->get_records_list("user", 'id', $gtregs + $queued, null, $fields);

        $attributes['data-absregs'] = [];
        if (!empty($absregs)) {
            foreach ($absregs as $cur) {
                // These user records are fully fetched in $group->moodle_members!
                $attributes['data-absregs'][] = [
                        'idnumber' => $showidnumber ? $group->moodle_members[$cur]->idnumber : '',
                        'fullname' => fullname($group->moodle_members[$cur]),
                        'id'       => $cur
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
                        'id'       => $cur
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
                        'id'       => $cur
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
                        'id'       => $cur,
                        'rank'     => $this->get_rank_in_queue($queuedlist, $cur)
                ];
            }
        }
        $attributes['data-queued'] = json_encode($attributes['data-queued']);

        $output = html_writer::tag('a', $output, $attributes);

        // And finally wrap in a span!
        return html_writer::tag('span', $output, ['class' => 'showmembers memberstooltip']);
    }

    /**
     * view overview tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_overview() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $includeinactive = optional_param('inactive', 0, PARAM_BOOL);
        $url = new moodle_url($PAGE->url, [
                'sesskey'     => sesskey(),
                'groupid'     => $groupid,
                'groupingid'  => $groupingid,
                'orientation' => $orientation,
                'inactive'    => $includeinactive
        ]);

        // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed?!
            $hideform = 0;
            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                list($error, $message) = $this->push_registrations($groupid, $groupingid);
                if ($error) {
                    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);
                } else {
                    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_SUCCESS);
                }
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hideform = 1;

            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                // Try only!
                list($error, $message) = $this->push_registrations($groupid, $groupingid, true);
                $attr = [];
                $attr['confirm'] = 1;
                $attr['pushtomdl'] = 1;
                $attr['sesskey'] = sesskey();

                $continue = new moodle_url($PAGE->url, $attr);
                $cancel = new moodle_url($PAGE->url);

                if ($error) {
                    $continue->remove_params('confirm', 'group');
                    $continue = new single_button($continue, get_string('continue'), 'get');
                    $cancel = null;
                }
                echo $this->confirm($message, $continue, $cancel);
            } else {
                $hideform = 0;
            }
        } else {
            $hideform = 0;
        }

        if (!$hideform) {
            $groupingselect = $this->get_grouping_select($url, $groupingid);
            $groupselect = $this->get_groups_select($url, $groupingid, $groupid);
            $orientationselect = $this->get_orientation_select($url, $orientation);

            if ($includeinactive) {
                $inactivetext = get_string('inactivegroups_hide', 'grouptool');
                $inactiveurl = new moodle_url($url, ['inactive' => 0]);
            } else {
                $inactivetext = get_string('inactivegroups_show', 'grouptool');
                $inactiveurl = new moodle_url($url, ['inactive' => 1]);
            }

            $syncstatus = $this->get_sync_status();

            if ($syncstatus[0]) {
                /*
                 * Out of sync? --> show button to get registrations from grouptool to moodle
                 * (just register not already registered persons and let the others be)
                 */
                $url = new moodle_url($PAGE->url, ['pushtomdl' => 1, 'sesskey' => sesskey()]);
                $button = new single_button($url, get_string('updatemdlgrps', 'grouptool'), 'post',
                        true);
                echo $OUTPUT->box(html_writer::empty_tag('br').
                                  $OUTPUT->render($button).
                                  html_writer::empty_tag('br'), 'generalbox centered');
            }

            echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupingselect),
                                  ['class' => 'centered grouptool_overview_filter']).
                 html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupselect),
                                  ['class' => 'centered grouptool_overview_filter']).
                 html_writer::tag('div', get_string('orientation', 'grouptool').'&nbsp;'.
                                         $OUTPUT->render($orientationselect),
                                  ['class' => 'centered grouptool_overview_filter']).
                 html_writer::tag('div', html_writer::link($inactiveurl, $inactivetext),
                                  ['class' => 'centered grouptool_overview_filter']);

            // If we don't only get the data, the output happens directly per group!
            $this->group_overview_table($groupingid, $groupid, false, $includeinactive);
        }
    }

    /**
     * Returns a single select to change currently selected grouping.
     *
     * @param moodle_url $url Base URL to use
     * @param int $groupingid Currently active grouping ID or 0
     * @return single_select
     * @throws coding_exception
     */
    protected function get_grouping_select($url, $groupingid) {
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
    protected function get_groups_select($url, $groupingid, $groupid) {
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
            echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping', 'grouptool').
                                                    html_writer::empty_tag('br').
                                                    get_string('switched_to_all_groups', 'grouptool'),
                    \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
        }
        return new single_select($url, 'groupid', $options, $groupid, false);
    }

    /**
     * Returns a single select to change currently selected page-orientation.
     *
     * @param moodle_url $url Base URL to use
     * @param int $orientation Currently active orientation
     * @return single_select
     * @throws coding_exception
     */
    protected function get_orientation_select($url, $orientation) {
        static $options = null;

        if (!$options) {
            $options = [
                    0 => get_string('portrait', 'grouptool'),
                    1 => get_string('landscape', 'grouptool')
            ];
        }

        return new single_select($url, 'orientation', $options, $orientation, false);
    }

    /**
     * get information about particular users with their registrations/queues
     *
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group
     * @param int|array $userids optional get only this user(s)
     * @param stdClass[] $orderby array how data should be sorted (column as key and ASC/DESC as value)
     * @param bool $isdownloading Indicates if the function is called from a download, muting all output
     * @return stdClass[] array of objects records from DB with all necessary data
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_user_data($groupingid = 0, $groupid = 0, $userids = 0, $orderby = [], $isdownloading = false) {
        global $DB, $OUTPUT;

        // After which table-fields can we sort?
        $sortable = ['firstname', 'lastname'];
        // Add instance specific useridentity fields.
        $sortable = array_merge($sortable, array_keys(self::get_useridentity_fields()));

        // Indexed by agrpid!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid, false);
        $agrpids = array_keys($agrps);
        if (!empty($agrpids)) {
            list($agrpsql, $agrpparams) = $DB->get_in_or_equal($agrpids);
        } else {
            $agrpsql = '';
            $agrpparams = [];
            if (!$isdownloading) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('no_groups_to_display', 'grouptool'),
                 \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
            }
        }

        if (!empty($userids)) {
            if (!is_array($userids)) {
                $userids = [$userids];
            }
            list($usersql, $userparams) = $DB->get_in_or_equal($userids);
        } else {
            $usersql = ' LIKE *';
            $userparams = [];
        }

        $extrauserfields = \core_user\fields::for_identity($this->context)->get_sql('u');
        $mainuserfields = \core_user\fields::for_userpic()->including('idnumber', 'email')->get_sql('u',
                false, '', '', false)->selects;
        $orderbystring = "";
        if (!empty($orderby)) {
            foreach ($orderby as $field => $direction) {
                if (in_array($field, $sortable)) {
                    if ($orderbystring != "") {
                        $orderbystring .= ", ";
                    } else {
                        $orderbystring .= " ORDER BY";
                    }
                    $orderbystring .= " ".$field." ".
                                      ((!empty($direction) && $direction == 'ASC') ? 'ASC' : 'DESC');
                } else {
                    unset($orderby[$field]);
                }
            }
        }
        $extrauserfieldsselects = $extrauserfields->selects;
        $extrauserfieldsfrom = $extrauserfields->joins;
        $sql = "SELECT $mainuserfields $extrauserfieldsselects ".
               "FROM {user} u $extrauserfieldsfrom".
               "WHERE u.id ".$usersql.
               $orderbystring;
        $params = array_merge($extrauserfields->params, $userparams);
        // $params = array_merge($params, $extrauserfields->params);

        $data = $DB->get_records_sql($sql, $params);

        // Add reg and queue data...
        if (!empty($agrpsql)) {
            foreach ($data as &$cur) {
                $sql = "SELECT agrps.id
                          FROM {grouptool_registered} regs
                     LEFT JOIN {grouptool_agrps}      agrps ON regs.agrpid = agrps.id
                     LEFT JOIN {groups}               grps  ON agrps.groupid = grps.id
                         WHERE regs.modified_by >= 0
                               AND regs.userid = ?
                               AND regs.agrpid ".$agrpsql;
                $params = array_merge([$cur->id], $agrpparams);
                $cur->regs = $DB->get_fieldset_sql($sql, $params);
                $sql = "SELECT agrps.id
                          FROM {grouptool_queued} queued
                     LEFT JOIN {grouptool_agrps}  agrps ON queued.agrpid = agrps.id
                     LEFT JOIN {groups}           grps  ON agrps.groupid = grps.id
                         WHERE queued.userid = ?
                               AND queued.agrpid ".$agrpsql;
                $params = array_merge([$cur->id], $agrpparams);
                $cur->queued = $DB->get_fieldset_sql($sql, $params);
            }
        }

        return $data;
    }

    /**
     * Return picture indicating sort-direction if data is primarily sorted by this column
     * or empty string if not
     *
     * @param stdClass[] $orderby array containing current state of sorting
     * @param string $search columnname to print sortpic for
     * @return string html fragment with sort-pic or empty string
     */
    private function pic_if_sorted($orderby = [], $search = '') {
        global $OUTPUT;
        $keys = array_keys($orderby);
        if (reset($keys) == $search) {
            if ($orderby[$search] == 'ASC') {
                return $OUTPUT->pix_icon('t/up', 'sorted ASC');
            } else {
                return $OUTPUT->pix_icon('t/down', 'sorted DESC');
            }
        }

        return "";
    }

    /**
     * returns collapselink (= symbol to show column or column-name and symbol to hide column)
     *
     * @param string $search column-name to print link for
     * @param string[] $collapsed array with collapsed columns
     * @return string html-fragment with icon to show column or column header text with icon to hide
     *                              column
     * @throws moodle_exception
     */
    private function collapselink($search, $collapsed = []) {
        global $PAGE, $OUTPUT;
        if (in_array($search, $collapsed)) {
            $url = new moodle_url($PAGE->url, ['tshow' => $search]);
            $pic = $OUTPUT->pix_icon('t/switch_plus', 'show');
        } else {
            $url = new moodle_url($PAGE->url, ['thide' => $search]);
            $pic = $OUTPUT->pix_icon('t/switch_minus', 'hide');
        }
        return html_writer::tag('div', html_writer::link($url, $pic),
                                                         ['class' => 'collapselink']);
    }

    /**
     * Returns nice download links for all formats based on downloadurl and groupid
     *
     * @param moodle_url $downloadurl The base download URL to use
     * @param int $groupid (optional) ID of group to use for the download or 0 for all groups download
     * @return string HTML snippet with download links encapsulated in DIV
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_download_links($downloadurl, $groupid = 0) {
        if (has_capability('mod/grouptool:export', $this->context)) {
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
            $downloadlinks = html_writer::tag('span', $downloadtxt.":", ['class' => 'title']).'&nbsp;'.
                                                      html_writer::link($txturl, '.TXT').'&nbsp;'.
                                                      html_writer::link($xlsxurl, '.XLSX').'&nbsp;'.
                                                      html_writer::link($pdfurl, '.PDF').'&nbsp;'.
                                                      html_writer::link($odsurl, '.ODS');
            return html_writer::tag('div', $downloadlinks, ['class' => $class]);
        } else {
            return '';
        }
    }

    /**
     * get all data necessary for displaying/exporting userlist table
     *
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param bool $onlydata optional return object with raw data not html-fragment-string
     * @return stdClass[]|bool true if table is output or raw data as array of objects
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function userlist_table($groupingid = 0, $groupid = 0, $onlydata = false) {
        global $OUTPUT, $CFG, $DB, $PAGE, $SESSION;
        $useridentityfields = self::get_useridentity_fields();

        if (!isset($SESSION->mod_grouptool->userlist)) {
            $SESSION->mod_grouptool->userlist = new stdClass();
        }
        // Handles order direction!
        if (!isset($SESSION->mod_grouptool->userlist->orderby)) {
            $SESSION->mod_grouptool->userlist->orderby = [];
        }
        $orderby = $SESSION->mod_grouptool->userlist->orderby;
        if ($tsort = optional_param('tsort', 0, PARAM_ALPHANUM)) {
            $olddir = 'DESC';
            if (key_exists($tsort, $orderby)) {
                $olddir = $orderby[$tsort];
                unset($orderby[$tsort]);
            }
            // Insert as first element and rebuild!
            $oldorderby = array_keys($orderby);
            $oldorderdir = array_values($orderby);
            array_unshift($oldorderby, $tsort);
            array_unshift($oldorderdir, (($olddir == 'DESC') ? 'ASC' : 'DESC'));
            $orderby = array_combine($oldorderby, $oldorderdir);
            $SESSION->mod_grouptool->userlist->orderby = $orderby;
        }

        // Handles collapsed columns!
        if (!isset($SESSION->mod_grouptool->userlist->collapsed)) {
            $SESSION->mod_grouptool->userlist->collapsed = [];
        }
        $collapsed = $SESSION->mod_grouptool->userlist->collapsed;
        if ($thide = optional_param('thide', 0, PARAM_ALPHANUM)) {
            if (!in_array($thide, $collapsed)) {
                array_push($collapsed, $thide);
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }
        if ($tshow = optional_param('tshow', 0, PARAM_ALPHANUM)) {
            foreach ($collapsed as $key => $value) {
                if ($value == $tshow) {
                    unset($collapsed[$key]);
                }
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }

        $downloadurl = '';
        if (!$onlydata) {
            flush();
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/mod/grouptool/download.php',
                                          [
                                                  'id'          => $this->cm->id,
                                                  'groupingid'  => $groupingid,
                                                  'groupid'     => $groupid,
                                                  'orientation' => $orientation,
                                                  'sesskey'     => sesskey(),
                                                  'tab'         => 'userlist'
                                          ]);
        }

        // Get all ppl that are allowed to register!
        list($esql, $params) = get_enrolled_sql($this->context, 'mod/grouptool:register');

        $sql = "SELECT u.id
                  FROM {user} u
             LEFT JOIN ($esql) eu ON eu.id=u.id
                 WHERE u.deleted = 0 AND eu.id=u.id ";
        $groups = groups_get_all_groups($this->course->id, 0, $groupingid);
        if (!empty($groupingid) && !empty($groups)) {
            // Get all groupings groups!
            $ufields = $mainuserfields = \core_user\fields::for_userpic()->including(
                    'idnumber')->get_sql('u', false, '', '', false)->selects;
            $groupingusers = groups_get_grouping_members($groupingid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupingusers)) {
                $groupingusers = [];
            } else {
                $groupingusers = array_keys($groupingusers);
            }
            list($groupssql, $groupsparams) = $DB->get_in_or_equal(array_keys($groups));
            $groupingusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid ".$groupssql, $groupsparams);
            $groupingusers = array_merge($groupingusers, $groupingusers2);
            if (empty($groupingusers)) {
                $userssql = " = :groupingparam";
                $groupingparams = ['groupingparam' => -1];
            } else {
                list($userssql, $groupingparams) = $DB->get_in_or_equal($groupingusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupingparams);
        }
        if (!empty($groupid)) {
            // Same as with groupingid but just with 1 group!
            // Get all group members!
            $ufields = $mainuserfields = \core_user\fields::for_userpic()->including(
                    'idnumber')->get_sql('u', false, '', '', false)->selects;
            $groupusers = groups_get_members($groupid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupusers)) {
                $groupusers = [];
            } else {
                $groupusers = array_keys($groupusers);
            }
            $groupusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid = ?", [$groupid]);
            $groupusers = array_merge($groupusers, $groupusers2);
            if (empty($groupusers)) {
                $userssql = " = :groupparam";
                $groupparams = ['groupparam' => -1];
            } else {
                list($userssql, $groupparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupparams);
        }
        $users = $DB->get_records_sql($sql, $params);

        if (!$onlydata) {
            echo $this->get_download_links($downloadurl);
            flush();
        }

        if (!empty($users)) {
            $users = array_keys($users);
            $userdata = $this->get_user_data($groupingid, $groupid, $users, $orderby, $onlydata);
        } else {
            if (!$onlydata) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('no_users_to_display', 'grouptool'),
                        \core\output\notification::NOTIFY_ERROR), 'centered generalbox');
            } else {
                return get_string('no_users_to_display', 'grouptool');
            }
        }
        $groupinfo = $this->get_active_groups(false, false, 0, $groupid, $groupingid,
                false);

        // We create a dummy user-object to get the fullname-format!
        $dummy = new stdClass();
        $namefields = \core_user\fields::for_name()->get_required_fields();
        foreach ($namefields as $namefield) {
            $dummy->$namefield = $namefield;
        }
        $fullnameformat = fullname($dummy);
        // Now get the ones used in fullname in the correct order!
        $namefields = order_in_string($namefields, $fullnameformat);

        $head = [];
        $rows = [];

        if (!$onlydata) {
            echo html_writer::start_tag('table',
                                        ['class' => 'centeredblock userlist table table-striped table-hover table-condensed']);

            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', $this->collapselink('picture', $collapsed), ['class' => '']);
            flush();
            if (!in_array('fullname', $collapsed)) {
                $firstnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                  ['tsort' => 'firstname']),
                                                   get_string('firstname').
                                                   $this->pic_if_sorted($orderby, 'firstname'));
                $surnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                ['tsort' => 'lastname']),
                                                  get_string('lastname').
                                                  $this->pic_if_sorted($orderby, 'lastname'));
                $fullname = html_writer::tag('div', get_string('fullname').
                                                    html_writer::empty_tag('br').
                                                    $firstnamelink.'&nbsp;/&nbsp;'.$surnamelink);
                echo html_writer::tag('th', $fullname.$this->collapselink('fullname', $collapsed),
                        ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('fullname', $collapsed), ['class' => '']);
            }

            foreach ($useridentityfields as $identifier => $text) {
                if (!in_array($identifier, $collapsed)) {
                    $idnumberlink = html_writer::link(new moodle_url($PAGE->url,
                            ['tsort' => $identifier]),
                            $text.
                            $this->pic_if_sorted($orderby, $identifier));
                    echo html_writer::tag('th', $idnumberlink.$this->collapselink($identifier, $collapsed),
                            ['class' => '']);
                } else {
                    echo html_writer::tag('th', $this->collapselink($identifier, $collapsed), ['class' => '']);
                }
            }
            if (!in_array('registrations', $collapsed)) {
                $registrationslink = get_string('registrations', 'grouptool');
                echo html_writer::tag('th', $registrationslink.
                                            $this->collapselink('registrations', $collapsed), ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('registrations', $collapsed), ['class' => '']);
            }
            if (!in_array('queues', $collapsed)) {
                $queueslink = get_string('queues', 'grouptool').' ('.get_string('rank',
                                'grouptool').')';
                echo html_writer::tag('th', $queueslink.
                                            $this->collapselink('queues', $collapsed), ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('queues', $collapsed), ['class' => '']);
            }
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
        } else {
            $head = ['name'          => get_string('fullname')];
            foreach ($namefields as $namefield) {
                $head[$namefield] = \core_user\fields::get_display_name($namefield);
            }
            if (empty($CFG->showuseridentity)) {
                $head['idnumber'] = \core_user\fields::get_display_name('idnumber');
            } else {
                $fields = explode(',', $CFG->showuseridentity);
                foreach ($fields as $field) {
                    $head[$field] = \core_user\fields::get_display_name($field);
                }
            }
            $head['idnumber'] = \core_user\fields::get_display_name('idnumber');
            $head['email']         = \core_user\fields::get_display_name('email');
            $head['registrations'] = get_string('registrations', 'grouptool');
            $head['queues']        = get_string('queues', 'grouptool').' ('.get_string('rank',
                            'grouptool').')';
        }

        if (!$onlydata) {
            echo html_writer::start_tag('tbody');
        }
        if (!empty($userdata)) {
            core_php_time_limit::raise(5 * count($userdata));
            foreach ($userdata as $key => $user) {
                if (!$onlydata) {
                    echo html_writer::start_tag('tr', ['class' => '']);

                    $userlink = new moodle_url($CFG->wwwroot.'/user/view.php', [
                            'id'     => $user->id,
                            'course' => $this->course->id
                    ]);
                    if (!in_array('picture', $collapsed)) {
                        $picture = html_writer::link($userlink, $OUTPUT->user_picture($user));
                        echo html_writer::tag('td', $picture, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    if (!in_array('fullname', $collapsed)) {
                        $fullname = html_writer::link($userlink, fullname($user));
                        echo html_writer::tag('td', $fullname, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    // Print all activated useridentityvalue infos.
                    foreach ($useridentityfields as $identifier => $value) {
                        if (!in_array($identifier, $collapsed)) {
                            $identifier = strtolower($identifier);
                            $identityvalue = $user->$identifier;
                            echo html_writer::tag('td', $identityvalue, ['class' => '']);
                        } else {
                            $this->print_empty_cell();
                        }
                    }
                    if (!in_array('registrations', $collapsed)) {
                        if (!empty($user->regs)) {
                            $registrations = [];
                            foreach ($user->regs as $reg) {
                                $grouplink = new moodle_url($PAGE->url, [
                                        'tab'     => 'overview',
                                        'groupid' => $groupinfo[$reg]->id
                                ]);
                                $registrations[] = html_writer::link($grouplink, $groupinfo[$reg]->name);
                            }
                        } else {
                            $registrations = ['-'];
                        }
                        $registrations = implode(html_writer::empty_tag('br'), $registrations);
                        echo html_writer::tag('td', $registrations, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    if (!in_array('queues', $collapsed)) {
                        if (!empty($user->queued)) {
                            $queueentries = [];
                            foreach ($user->queued as $queue) {
                                $grouplink = new moodle_url($PAGE->url, [
                                        'tab'     => 'overview',
                                        'groupid' => $groupinfo[$queue]->id
                                ]);
                                $groupdata = $this->get_active_groups(false, true, $queue);
                                $groupdata = current($groupdata);
                                $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                                $groupdata = null;
                                unset($groupdata);
                                if (empty($rank)) {
                                    $rank = '*';
                                }
                                $queueentries[] = html_writer::link($grouplink, $groupinfo[$queue]->name." (#".$rank.")");
                            }
                        } else {
                            $queueentries = ['-'];
                        }
                        $queueentries = implode(html_writer::empty_tag('br'), $queueentries);
                        echo html_writer::tag('td', $queueentries, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    echo html_writer::end_tag('tr');
                    flush();
                    $picture = null;
                    unset($picture);
                    $fullname = null;
                    unset($fullname);
                    $idnumber = null;
                    unset($idnumber);
                    $email = null;
                    unset($email);
                    $registrations = null;
                    unset($registrations);
                    $queueentries = null;
                    unset($queueentries);
                } else {
                    $row = [];
                    $row['name'] = fullname($user);

                    foreach ($namefields as $namefield) {
                        $row[$namefield] = $user->$namefield;
                        $user->namefield = null;
                        unset($user->namefield);
                    }
                    $row['idnumber'] = $user->idnumber;
                    $row['email'] = $user->email;
                    if (empty($CFG->showuseridentity)) {
                        $row['idnumber'] = $user->idnumber;
                        $user->idnumber = null;
                        unset($user->idnumber);
                        $row['email'] = $user->email;
                        $user->email = null;
                        unset($user->email);
                    } else {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $field = strtolower($field);
                            $row[$field] = $user->$field;
                            $user->$field = null;
                            unset($user->$field);
                        }
                    }
                    if (!empty($user->regs)) {
                        $registrations = [];
                        foreach ($user->regs as $reg) {
                            $registrations[] = $groupinfo[$reg]->name;
                        }
                        $row['registrations'] = $registrations;
                    } else {
                        $row['registrations'] = [];
                    }
                    $user->regs = null;
                    unset($user->regs);
                    if (!empty($user->queued)) {
                        $queueentries = [];
                        foreach ($user->queued as $queue) {
                            $groupdata = $this->get_active_groups(false, true, $queue);
                            $groupdata = current($groupdata);
                            $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                            if (empty($rank)) {
                                $rank = '*';
                            }
                            $queueentries[] = [
                                    'rank' => $rank,
                                    'name' => $groupinfo[$queue]->name
                            ];
                        }
                        $row['queues'] = $queueentries;
                    } else {
                        $row['queues'] = [];
                    }
                    $user->queues = null;
                    unset($user->queues);
                    $rows[] = $row;
                    $row = null;
                    unset($row);
                }
            }
        }
        if (!$onlydata) {
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        } else {
            return array_merge([$head], $rows);
        }

        return true;
    }

    /**
     * Helper function used to print empty cells for hidden columns
     */
    private function print_empty_cell() {
        echo html_writer::tag('td', '', ['class' => '']);
    }

    /**
     * outputs generated pdf-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_pdf($groupid=0, $groupingid=0) {
        $data = $this->userlist_table($groupingid, $groupid, true);

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupingid) || !empty($groupid)) {
            $viewname = "";
            if (!empty($groupingid)) {
                $viewname .= groups_get_grouping_name($groupingid);
            } else {
                $viewname .= get_string('all');
            }
            if ($viewname != "") {
                $viewname .= " / ";
            }
            if (!empty($groupid)) {
                $viewname .= groups_get_group_name($groupid);
            } else {
                $viewname .= get_string('all');
            }
        } else {
            $viewname = get_string('all').' '.get_string('groups');
        }

        $pdf = new \mod_grouptool\pdf('userlist', $coursename, $grouptoolname, $timeavailable, $timedue,
            $viewname);

        if (count($data) > 1) {
            $user = reset($data);
            $name = $user['name'];
            $idnumber = $user['idnumber'];
            $email = $user['email'];
            $regdata = $user['registrations'];
            $queuedata = $user['queues'];
            $pdf->add_userdata($user, true);
            while (next($data)) {
                $user = current($data);
                $name = $user['name'];
                $idnumber = $user['idnumber'];
                $email = $user['email'];
                $regdata = $user['registrations'];
                $queuedata = $user['queues'];
                $pdf->add_userdata($user);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'),
                            'B', 'LRTB', false, 1, null, null, true, 1, true,
                    false, $pdf->getLastH(), 'M', true);
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                        get_string('userlist', 'grouptool');
        }
        $filename = clean_filename("$filename.pdf");

        $pdf->Output($filename, 'D');
        exit();
    }

    /**
     * returns data for userlist
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @return bool|object[] raw data
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_raw($groupid=0, $groupingid=0) {
        return $this->userlist_table($groupid, $groupingid, true);
    }

    /**
     * outputs generated txt-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_txt($groupid=0, $groupingid=0) {
        ob_start();

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;
        $useridentityfields = self::get_useridentity_fields();

        $lines = [];
        $users = $this->userlist_table($groupingid, $groupid, true);
        if (count($users) > 0) {
            foreach ($users as $key => $user) {
                if ($key == 0) { // Headline!
                    $lines[] = get_string('fullname')."\t".
                               self::get_useridentity_values_for_txt
                               (self::convert_associative_array_into_nested_index_array(self::get_useridentity_fields())) . "\t" .
                               get_string('registrations', 'grouptool')."\t".
                               get_string('queues', 'grouptool')." (".get_string('rank',
                                    'grouptool').")";
                } else {
                    $rows = max([1, count($user['registrations']), count($user['queues'])]);

                    for ($i = 0; $i < $rows; $i++) {
                        $line = "";
                        if ($i == 0) {
                            $line = $user['name'];
                            // Print all activated useridentityvalue infos.
                            foreach ($useridentityfields as $identifier => $value) {
                                if (!empty($user[$identifier])) {
                                    $line .= "\t" . $user[$identifier];
                                }
                            }
                        } else {
                            $line = "\t\t";
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('no_registrations', 'grouptool');
                        } else if (key_exists($i, $user['registrations'])) {
                            $line .= "\t".$user['registrations'][$i];
                        } else {
                            $line .= "\t";
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('nowhere_queued', 'grouptool');
                        } else if (key_exists($i, $user['queues'])) {
                            $line .= "\t".$user['queues'][$i]['name']."(".$user['queues'][$i]['rank'].")";
                        } else {
                            $line .= "\t";
                        }
                        $lines[] = $line;
                    }
                }
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                        get_string('userlist', 'grouptool');
        }
        $filename = clean_filename("$filename.txt");
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1!
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in past!
        header('Content-Disposition: attachment; filename="'.str_replace([' ', '"'], ['_', ''], $filename).'";'.
               ' filename*="'.rawurlencode($filename).'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * fills workbook (either XLSX or ODS) with data
     *
     * @param MoodleExcelWorkbook|MoodleODSWorkbook $workbook workbook to put data into
     * @param stdClass[] $data userdata with headline at index 0
     * @param string[] $collapsed (optional) currently collapsed columns
     * @throws coding_exception
     */
    protected function userlist_fill_workbook(&$workbook, $data, $collapsed=[]) {
        global $CFG;
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        if (count($data) > 0) {

            $worksheet = false;

            // Prepare formats!
            $headlineprop = [
                    'size' => 12,
                    'bold' => 1,
                    'HAlign' => 'center',
                    'bottom' => 2,
                    'VAlign' => 'vcenter'
            ];
            $headlineformat = $workbook->add_format($headlineprop);
            $headlineformat->set_right(1);
            $headlineformat->set_align('center');
            $headlineformat->set_align('vcenter');
            $headlinelast = $workbook->add_format($headlineprop);
            $headlinelast->set_align('center');
            $headlinelast->set_align('vcenter');
            $headlinelast->set_left(1);
            $headlinenb = $workbook->add_format($headlineprop);
            $headlinenb->set_align('center');
            $headlinenb->set_align('vcenter');
            unset($headlineprop['bottom']);
            $headlinenbb = $workbook->add_format($headlineprop);
            $headlinenbb->set_align('center');
            $headlinenbb->set_align('vcenter');

            $regentryprop = [
                    'size' => 10,
                    'align' => 'left'
            ];
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_align('vcenter');
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_align('vcenter');
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('left');
            $noregentriesformat->set_align('vcenter');
            $noregentriesformat->set_right(1);
            $queueentryformat = $workbook->add_format($queueentryprop);
            $queueentryformat->set_right(1);
            $queueentryformat->set_align('vcenter');
            $queueentrylast = $workbook->add_format($queueentryprop);
            $queueentrylast->set_align('vcenter');
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('left');
            $noqueueentriesformat->set_align('vcenter');

            // Start row for groups general sheet!
            $j = 0;

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = \core_user\fields::for_name()->get_required_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = [
                    0               => 26,
                    'fullname'      => 26,
                    'firstname'     => 20,
                    'surname'       => 20,
                    'email'         => 35,
                    'registrations' => 47,
                    'queues_grp'    => 47,
                    'queues_rank'   => 7.5
            ]; // Unit: mm!

            foreach ($data as $key => $user) {
                if ($worksheet === false && count($data) > 1) {
                    // General information? unused at the moment!
                    $worksheet = $workbook->add_worksheet(get_string('all'));
                }
                if ($key == 0) {
                    // Headline!
                    $k = 0;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name($namefield), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $worksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k = n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $worksheet->write_string($j, $k, \core_user\fields::get_display_name($field), $headlineformat);
                            $worksheet->write_blank($j + 1, $k, $headlineformat);
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $worksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $worksheet->merge_cells($j, $k, $j + 1, $k);
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name('idnumber'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $worksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name('email'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $worksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+2!
                    }
                    $worksheet->write_string($j, $k, $user['registrations'], $headlineformat);
                    $worksheet->write_blank($j + 1, $k, $headlineformat);
                    $hidden = in_array('registrations', $collapsed) ? true : false;
                    $tmp = $columnwidth['registrations'];
                    $columnwidth['registrations'] = empty($tmp) ? $columnwidth[0] : $tmp;
                    unset($tmp);
                    $worksheet->set_column($k, $k, $columnwidth['registrations'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j + 1, $k);
                    $k++; // ...k = n+3!
                    $worksheet->write_string($j, $k, $user['queues'], $headlinenbb);
                    $worksheet->write_blank($j, $k + 1, $headlinenbb);
                    $hidden = in_array('queues', $collapsed) ? true : false;
                    $columnwidth['queues_grp'] = empty($columnwidth['queues_grp']) ? $columnwidth[0] : $columnwidth['queues_grp'];
                    $worksheet->set_column($k, $k, $columnwidth['queues_grp'], null, $hidden);
                    $tmp = $columnwidth['queues_rank'];
                    $columnwidth['queues_rank'] = empty($tmp) ? $columnwidth[0] : $tmp;
                    unset($tmp);
                    $worksheet->set_column($k + 1, $k + 1, $columnwidth['queues_rank'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j, $k + 1);
                    $worksheet->write_string($j + 1, $k, get_string('group', 'group'), $headlinenb);
                    $worksheet->write_string($j + 1, $k + 1, get_string('rank', 'grouptool'),
                                             $headlinelast);
                    $k += 2; // ...k = n+5!
                    $rows = 2;
                } else {
                    $k = 0;
                    $rows = max([1, count($user['registrations']), count($user['queues'])]);

                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        if (empty($user[$namefield])) {
                            $user[$namefield] = '';
                        }
                        $worksheet->write_string($j, $k, $user[$namefield], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++;
                    }
                    // ...k = n!

                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            if (empty($user[$field])) {
                                $worksheet->write_blank($j, $k, $regentryformat);
                            } else {
                                $worksheet->write_string($j, $k, $user[$field], $regentryformat);
                            }
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, $user['idnumber'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, $user['email'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+2!
                    }

                    for ($i = 0; $i < $rows; $i++) {
                        if ($i != 0) {
                            for ($m = 0; $m < $k; $m++) {
                                // Write all the empty cells!
                                $worksheet->write_blank($j + $i, $m, $regentryformat);
                            }
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k, get_string('no_registrations',
                                                                       'grouptool'),
                                                     $noregentriesformat);
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                        } else if (key_exists($i, $user['registrations'])) {
                            $worksheet->write_string($j + $i, $k, $user['registrations'][$i],
                                                     $regentryformat);
                        } else {
                            $worksheet->write_blank($j + $i, $k, $regentryformat);
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k + 1, get_string('nowhere_queued',
                                                                       'grouptool'),
                                                     $noqueueentriesformat);
                            $worksheet->merge_cells($j, $k + 1, $j + $rows - 1, $k + 2);
                        } else if (key_exists($i, $user['queues'])) {
                            $worksheet->write_string($j + $i, $k + 1, $user['queues'][$i]['name'],
                                                     $queueentrylast);
                            $worksheet->write_number($j + $i, $k + 2, $user['queues'][$i]['rank'],
                                                     $queueentrylast);
                        } else {
                            $worksheet->write_blank($j + $i, $k + 1, $queueentrylast);
                            $worksheet->write_blank($j + $i, $k + 2, $queueentrylast);
                        }
                    }
                    $k += 3;
                }
                $j += $rows;    // We use 1 row space between groups!
            }
        }
    }

    /**
     * outputs generated ods-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param string[] $collapsed optional current array with collapsed columns
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_ods($groupid=0, $groupingid=0, $collapsed=[]) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleODSWorkbook("-");

        $data = $this->userlist_table($groupingid, $groupid, true);

        $this->userlist_fill_workbook($workbook, $data, $collapsed);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_group_name($groupid).'_'.get_string('userlist', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                    get_string('userlist', 'grouptool');
        }
        $filename = clean_filename("$filename.ods");

        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * outputs generated xlsx-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_xlsx($groupid = 0, $groupingid = 0) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = format_string($this->course->fullname, true, array('context' => context_module::instance($this->cm->id)));
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $data = $this->userlist_table($groupingid, $groupid, true);

        $this->userlist_fill_workbook($workbook, $data);

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_group_name($groupid).'_'.
                                       get_string('userlist', 'grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       groups_get_grouping_name($groupingid).'_'.
                                       get_string('userlist', 'grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                                       get_string('userlist', 'grouptool'));
        }
        $filename = clean_filename("$filename.xlsx");

        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * view userlist tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_userlist() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);

        $url = new moodle_url($PAGE->url, [
                'sesskey'     => sesskey(),
                'groupid'     => $groupid,
                'groupingid'  => $groupingid,
                'orientation' => $orientation
        ]);

        $groupings = groups_get_all_groupings($this->course->id);
        $options = [0 => get_string('all')];
        if (count($groupings)) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = $grouping->name;
            }
        }

        $groupingselect = $this->get_grouping_select($url, $groupingid);
        $groupselect = $this->get_groups_select($url, $groupingid, $groupid);
        $orientationselect = $this->get_orientation_select($url, $orientation);

        echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupingselect),
                              ['class' => 'centered grouptool_userlist_filter']).
             html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupselect),
                              ['class' => 'centered grouptool_userlist_filter']).
             html_writer::tag('div', get_string('orientation', 'grouptool').'&nbsp;'.
                                     $OUTPUT->render($orientationselect),
                              ['class' => 'centered grouptool_userlist_filter']);
        flush();
        $this->userlist_table($groupingid, $groupid);

    }
    /**
     * Requires the JS libraries for the message group button.
     *
     * @return void
     */
    public static function messagegroup_requirejs() {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }
        $PAGE->requires->js_call_amd('mod_grouptool/message_group_button', 'send',  array('#group-message-button'));
        $done = true;
    }

}

