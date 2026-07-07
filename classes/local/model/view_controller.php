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

use context_course;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use core\output\help_icon;
use core\output\html_writer;
use core\output\notification;
use core\output\single_button;
use core\output\single_select;
use core_message\api;
use dml_exception;
use mod_grouptool\exception\exceedgroupqueuelimit;
use mod_grouptool\exception\exceedgroupsize;
use mod_grouptool\exception\exceeduserqueuelimit;
use mod_grouptool\exception\exceeduserreglimit;
use mod_grouptool\exception\notenoughregs;
use mod_grouptool\exception\registration;
use mod_grouptool\form\group_creation_form;
use mod_grouptool\form\group_rename_form;
use mod_grouptool\form\group_resize_form;
use mod_grouptool\form\groupings_creation_form;
use mod_grouptool\form\import_confirm_form;
use mod_grouptool\form\import_form;
use mod_grouptool\form\unregister_confirm_form;
use mod_grouptool\form\unregister_form;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\local\grouptool_utils;
use mod_grouptool\output\sortlist;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Class containing the logic for the view-controller of the grouptool module
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_controller extends grouptool_instance {
    /**
     * Shows the starting page of the grouptool
     * @return void
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function view_starting_page(): void {
        global $OUTPUT, $USER, $CFG;

        $id = $this->cm->id;

        $registrationdetail = '';
        $queueplacedetails = '';
        $detailsregistration = '';

        $registationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        if (property_exists($this->grouptool, "allowreg") && $this->grouptool->allowreg == 1) {
            if ($registrationdetail != "") {
                $registrationdetail .= " <br> ";
            }
            $registrationdetail .= get_string('cfg_allow_reg', 'grouptool');
        }
        if (property_exists($this->grouptool, "allowunreg") && $this->grouptool->allowunreg == 1) {
            if ($registrationdetail != "") {
                $registrationdetail .= " <br> ";
            }
            $registrationdetail .= get_string('cfg_allow_unreg', 'grouptool');
        }
        if (property_exists($this->grouptool, "allow_mutltiple") && $this->grouptool->allow_mutltiple == 1) {
            if ($registrationdetail != "") {
                $registrationdetail .= " <br> ";
            }
            $registrationdetail .= get_string('cfg_allow_multiple', 'grouptool');
        }

        $regstats = $registationmanager->get_registration_stats($USER->id);
        $countactivegroups = count($groupmanager->get_active_groups());
        $groupplacedetails = $countactivegroups . " " . get_string('groups') . " / " .
            $regstats->group_places . " " . get_string('places', 'grouptool');
        $numberofusers = $regstats->users . " " . get_string('users');

        if ($countactivegroups < 1) {
            $groupplacedetails = get_string('no_active_groups', 'grouptool');
        }
        if (property_exists($this->grouptool, "usequeue") && $this->grouptool->usequeue == 1) {
            $queuing = true;
        } else {
            $queuing = false;
        }

        if ($queuing) {
            $queueplacedetails = get_string("active") . " / " .
                $regstats->queued_users . " " . get_string('places', 'grouptool');
        }

        // If regstration is open.
        $registrations = false;

        if (!empty($this->grouptool->timeavailable) && (time() >= $this->grouptool->timeavailable)) {
            $registrations = true;

            // Show how many are registered.
            if ($regstats->reg_users > 0) {
                $detailsregistration = $regstats->reg_users . " " .
                    get_string('registered', 'grouptool');
            }

            // If queuses are enabled show how many are in a queue.
            if ($queuing) {
                if ($regstats->queued_users > 0) {
                    if ($detailsregistration != "") {
                        $detailsregistration .= " <br> ";
                    }
                    $detailsregistration .= $regstats->queued_users . " " .
                        get_string('queued', 'grouptool');
                }
            }

            // Show how many are not registered yet.
            if ($regstats->users > 0) {
                if ($detailsregistration != "") {
                    $detailsregistration .= " <br> ";
                }
                $detailsregistration .= get_string(
                    'registrations_missing',
                    'grouptool',
                    $registationmanager->get_missing_registrations()
                );
            }
        }

        if ($detailsregistration == '') {
            $registrations = false;
        }

        $templatename = 'mod_grouptool/startingpage';
        $data = [
            'administrategroups' => has_capability(
                'mod/grouptool:administrate_groups',
                $this->context
            ),
            'previewselfregistration' => has_capability(
                'mod/grouptool:preview',
                $this->context
            ),
            'buttonadministratelink' => $CFG->wwwroot . '/mod/grouptool/administration.php?id=' . $id . '&tab=group_admin',
            'buttonpreviewlink' => $CFG->wwwroot . '/mod/grouptool/view.php?id=' . $id . '&tab=selfregistration',
            'queueing' => $queuing,
            'registrations' => $registrations,
            'registrationdetails' => $registrationdetail,
            'groupplacedetails' => $groupplacedetails,
            'queueplacedetails' => $queueplacedetails,
            'numberofusers' => $numberofusers,
            'detailsregistration' => $detailsregistration,
        ];

        echo $OUTPUT->render_from_template($templatename, $data);
    }

    /**
     * view self-registration-tab
     *
     * @param string $outputcache Output already generated that can be added after the header to be generated
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws \coding_exception
     */
    public function view_selfregistration(string $outputcache): void {
        global $OUTPUT, $USER, $PAGE;

        $registrationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $permissionmanager = new permission_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        // Include js for filters.
        $USER->ajax_updatable_user_prefs['mod_grouptool_hideoccupied'] = true;
        $params = new StdClass();

        if (get_user_preferences('mod_grouptool_hideoccupied', false) === 'true') {
            $params->filterunoccupied = true;
        } else {
            $params->filterunoccupied = false;
        }

        $PAGE->requires->js_call_amd('mod_grouptool/filter', 'init', [$params]);

        $userid = $USER->id;
        $regopen = $registrationmanager->is_registration_open();

        // Process submitted form!
        $error = false;

        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed!
            $hideform = false;
            $action = optional_param('action', 'reg', PARAM_ALPHA);
            $confirmmessage = '';
            if ($action == 'unreg') {
                if (!has_capability('mod/grouptool:preview', $this->context)) {
                    require_capability('mod/grouptool:register', $this->context);
                }

                $agrpid = required_param('group', PARAM_INT);

                // Unregister user and get feedback!
                try {
                    $confirmmessage = $registrationmanager->unregister_from_agrp($agrpid, $USER->id);
                } catch (registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'reg') {
                if (!has_capability('mod/grouptool:preview', $this->context)) {
                    require_capability('mod/grouptool:register', $this->context);
                }

                $agrpid = required_param('group', PARAM_INT);

                // Register user and get feedback!
                try {
                    $confirmmessage = $registrationmanager->register_in_agrp($agrpid, $USER->id);
                } catch (registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'resolvequeues') {
                // TODO remove from this page!
                require_capability('mod/grouptool:administrate_registration', $this->context);
                [$error, $confirmmessage] = $queuemanager->resolve_queues();
                if ($error == -1) {
                    $error = true;
                }
            }

            if ($error === true) {
                echo $OUTPUT->header() . $outputcache .
                    $OUTPUT->notification($confirmmessage, notification::NOTIFY_ERROR);
            } else {
                echo $OUTPUT->header() . $outputcache .
                    $OUTPUT->notification($confirmmessage, notification::NOTIFY_SUCCESS);
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hideform = true;
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
                require_capability('mod/grouptool:administrate_registration', $this->context);
                // TODO remove from this page!
                [$error, $confirmmessage] = $queuemanager->resolve_queues(true); // This is try only!
                if ($error == -1) {
                    $error = true;
                }
            } else if ($action == 'unreg') {
                if (!has_capability('mod/grouptool:preview', $this->context)) {
                    require_capability('mod/grouptool:register', $this->context);
                }
                $attr['group'] = $agrpid;
                // This is try only!
                try {
                    $confirmmessage = $registrationmanager->unregister_from_agrp($agrpid, $USER->id, true);
                } catch (registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else {
                if (!has_capability('mod/grouptool:preview', $this->context)) {
                    require_capability('mod/grouptool:register', $this->context);
                }
                $action = 'reg';
                $attr['group'] = $agrpid;
                // This is try only!
                try {
                    $confirmmessage = $registrationmanager->register_in_agrp($agrpid, $USER->id, true);
                } catch (registration $e) {
                    $error = 1;
                    $confirmmessage = $e->getMessage();
                }
            }
            $attr['confirm'] = '1';
            $attr['action'] = $action;
            $attr['sesskey'] = sesskey();
            $attr['tab'] = 'selfregistration';

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if (($error === true) && ($action != 'resolvequeues')) {
                $continue->remove_params('confirm', 'group');
                $continue = new single_button($continue, get_string('continue'), 'get');
                $cancel = null;
            }
            echo $OUTPUT->header() . $outputcache;
            echo $utils->confirm($confirmmessage, $continue, $cancel);
        } else {
            $hideform = false;
            echo $OUTPUT->header() . $outputcache;
        }

        if (!$hideform) {
            /*
             * we need a new moodle_url-Object because
             * $PAGE->url->param('sesskey', sesskey());
             * won't set sesskey param in $PAGE->url?!?
             */
            $url = new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'tab' => 'selfregistration']);
            // The back url is for the back button to return to the grouptool overview.
            $backurl = new moodle_url($PAGE->url);
            $mform = new MoodleQuickForm(
                'registration_form',
                'post',
                $url,
                '',
                ['id' => 'registration_form']
            );

            $regstat = $registrationmanager->get_registration_stats($USER->id);
            if (has_capability('mod/grouptool:administrate_registration', $this->context)) {
                // Add HTML button instead of a normal button to use a different URL than the form.
                $buttonarray = [];
                $buttonarray[] = $mform->createElement(
                    'html',
                    $OUTPUT->render_from_template(
                        'mod_grouptool/helpers/back_button',
                        ['back_url' => $backurl->out(false)]
                    )
                );
                $mform->addGroup($buttonarray, 'buttonar', '', [''], false);
            }
            if (
                !empty($this->grouptool->timedue) && (time() >= $this->grouptool->timedue) &&
                has_capability('mod/grouptool:administrate_registration', $this->context)
            ) {
                if ($regstat->queued_users > 0) {
                    // Insert queue-resolving button!
                    $mform->addElement('header', 'resolveheader', get_string(
                        'resolve_queue_legend',
                        'grouptool'
                    ));
                    $mform->addElement('submit', 'resolve_queues', get_string(
                        'resolve_queue',
                        'grouptool'
                    ));
                }
            }
            $mform->addElement('header', 'generalinfo', get_string(
                'general_information',
                'grouptool'
            ));
            $mform->setExpanded('generalinfo');

            if (!empty($this->grouptool->usesize)) {
                $placestats = $regstat->group_places . '&nbsp;' . get_string('total', 'grouptool');
            } else {
                $placestats = '∞&nbsp;' . get_string('total', 'grouptool');
            }
            if (($regstat->free_places != null) && !empty($this->grouptool->usesize)) {
                $placestats .= ' / ' . $regstat->free_places . '&nbsp;' .
                    get_string('free', 'grouptool');
            } else {
                $placestats .= ' / ∞&nbsp;' . get_string('free', 'grouptool');
            }
            if ($regstat->occupied_places != null) {
                $placestats .= ' / ' . $regstat->occupied_places . '&nbsp;' .
                    get_string('occupied', 'grouptool');
            }
            $mform->addElement(
                'static',
                'group_places',
                get_string('group_places', 'grouptool'),
                $placestats
            );
            $mform->addHelpButton('group_places', 'group_places', 'grouptool');

            $mform->addElement('static', 'number_of_students', get_string(
                'number_of_students',
                'grouptool'
            ), $regstat->users);

            if (
                ($this->grouptool->allowmultiple &&
                    (count($regstat->registered) < $this->grouptool->choosemin)) ||
                (!$this->grouptool->allowmultiple &&
                    !count($regstat->registered))
            ) {
                if ($this->grouptool->allowmultiple) {
                    $missing = ($this->grouptool->choosemin - count($regstat->registered));
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
                    $regscumulative[] = $registration->grpname . ' (' . $registration->rank . ')';
                }
                $mform->addElement(
                    'static',
                    'registrations',
                    get_string(
                        'registrations',
                        'grouptool'
                    ),
                    html_writer::tag('div', $missingtext) . implode(', ', $regscumulative)
                );
            } else {
                $mform->addElement(
                    'static',
                    'registrations',
                    get_string(
                        'registrations',
                        'grouptool'
                    ),
                    html_writer::tag('div', $missingtext) . get_string(
                        'not_registered',
                        'grouptool'
                    )
                );
            }

            if (!empty($regstat->queued)) {
                $queuescumulative = [];
                foreach ($regstat->queued as $queue) {
                    $queuescumulative[] = $queue->grpname . ' (' . $queue->rank . ')';
                }
                $mform->addElement(
                    'static',
                    'queues',
                    get_string('queues', 'grouptool'),
                    implode(', ', $queuescumulative)
                );
            }

            if (!empty($this->grouptool->allowreg)) {
                if (!empty($this->grouptool->allowunreg)) {
                    $unregtext = get_string('allowed', 'grouptool');
                } else {
                    $unregtext = get_string('not_permitted', 'grouptool');
                }
                $mform->addElement(
                    'static',
                    'unreg',
                    get_string('unreg_is', 'grouptool'),
                    $unregtext
                );
                if (!empty($this->grouptool->allowmultiple)) {
                    $minmaxtext = '';
                    if ($this->grouptool->choosemin && $this->grouptool->choosemax) {
                        $data = [
                            'min' => $this->grouptool->choosemin,
                            'max' => $this->grouptool->choosemax,
                        ];
                        $minmaxtext = get_string('choose_min_max_text', 'grouptool', $data);
                    } else if ($this->grouptool->choosemin) {
                        $minmaxtext = get_string(
                            'choose_min_text',
                            'grouptool',
                            $this->grouptool->choosemin
                        );
                    } else if ($this->grouptool->choosemax) {
                        $minmaxtext = get_string(
                            'choose_max_text',
                            'grouptool',
                            $this->grouptool->choosemax
                        );
                    }
                    $mform->addElement('static', 'minmax', get_string(
                        'choose_minmax_title',
                        'grouptool'
                    ), $minmaxtext);
                }

                if (!empty($this->grouptool->usequeue)) {
                    $mform->addElement(
                        'static',
                        'queueing',
                        get_string('queueing_is', 'grouptool'),
                        get_string('active', 'grouptool')
                    );
                }
            }

            $groups = $groupmanager->get_active_groups(true, true);

            // Preperation for loop.
            $userregs = $registrationmanager->get_user_reg_count($userid);
            $userqueues = $queuemanager->get_user_queues_count($userid);
            $usermarks = $utils->count_user_marks($userid);
            $min = $this->grouptool->allowmultiple ? $this->grouptool->choosemin : 0;
            $mform->addElement('header', 'groups', get_string('groups'));
            $mform->setExpanded('groups');
            // Checkbox control for only unoccupied groups filter.
            $mform->addElement('html', '<div><label class="form-check-inline">
                                                <input type="checkbox" name="filterunoccupied"
                                                id="filterunoccupied" class="form-check-input"> ' .
                get_string('filterunoccupied', 'grouptool') . '</label></div>');

            // Student view!
            // Prepare formular-content for registration-action!
            foreach ($groups as $key => &$group) {
                $registered = count($group->registered);
                $grpsize = ($this->grouptool->usesize) ? $group->grpsize : '∞';

                $grouphtml = html_writer::tag(
                    'span',
                    get_string('registered', 'grouptool') .
                    ": " . $registered . "/" . $grpsize,
                    ['class' => 'fillratio']
                );
                if ($this->grouptool->usequeue) {
                    $queued = count($group->queued);
                    $grouphtml .= html_writer::tag(
                        'span',
                        get_string('queued', 'grouptool') .
                        ' ' . $queued,
                        ['class' => 'queued']
                    );
                }

                // Could become a performance problem when groups fill up!
                if (!empty($group->registered)) {
                    $regrank = $queuemanager->get_rank_in_queue($group->registered, $USER->id);
                } else {
                    $regrank = false;
                }
                if (!empty($group->queued)) {
                    $queuerank = $queuemanager->get_rank_in_queue($group->queued, $USER->id);
                } else {
                    $queuerank = false;
                }

                // We have to determine if we can show the members link!
                $showmembers = $permissionmanager->canshowmembers($group->agrpid, $regrank, $queuerank);
                if ($showmembers) {
                    $grouphtml .= $utils->render_members_link($group);
                }

                /* If we include inactive groups and there's someone registered in one of these,
                 * the label gets displayed incorrectly.
                 */

                if (
                    !empty($group->registered) && $registrationmanager->is_registration_open()
                    && $queuemanager->get_rank_in_queue($group->registered, $userid)
                ) {
                    // User is already registered --> unreg button!
                    if (
                        $this->grouptool->allowunreg &&
                        (
                            has_capability('mod/grouptool:register', $this->context) ||
                            has_capability('mod/grouptool:preview', $this->context)
                        )
                    ) {
                        $label = get_string('unreg', 'grouptool');
                        $buttonattr = [
                            'type' => 'submit',
                            'name' => 'unreg[' . $group->agrpid . ']',
                            'value' => $group->agrpid,
                            'class' => 'unregbutton btn btn-secondary',
                        ];
                        if ($regopen && ($userregs + $userqueues > $min)) {
                            $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                        }
                    }
                    $grouphtml .= html_writer::tag(
                        'span',
                        get_string(
                            'registered_on_rank',
                            'grouptool',
                            $regrank
                        ),
                        ['class' => 'rank']
                    );
                } else if (
                    !empty($group->queued) && $registrationmanager->is_registration_open()
                    && $queuemanager->get_rank_in_queue($group->queued, $userid)
                ) {
                    // We're sorry, but user's already queued in this group!
                    if (
                        $this->grouptool->allowunreg &&
                        (
                            has_capability('mod/grouptool:register', $this->context) ||
                            has_capability('mod/grouptool:preview', $this->context)
                        )
                    ) {
                        $label = get_string('unqueue', 'grouptool');
                        $buttonattr = [
                            'type' => 'submit',
                            'name' => 'unreg[' . $group->agrpid . ']',
                            'value' => $group->agrpid,
                            'class' => 'unregbutton btn btn-secondary',
                        ];
                        if ($regopen && ($userregs + $userqueues > $min)) {
                            $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                        }
                    }
                    $grouphtml .= html_writer::tag(
                        'span',
                        get_string(
                            'queued_on_rank',
                            'grouptool',
                            $queuerank
                        ),
                        ['class' => 'rank']
                    );
                } else if ($utils->grpmarked($group->agrpid)) {
                    $grouphtml .= html_writer::tag(
                        'span',
                        get_string('grp_marked', 'grouptool'),
                        ['class' => 'rank']
                    );
                } else if (
                    $registrationmanager->is_registration_open() && $permissionmanager->qualifies_for_groupchange($group->agrpid, $USER->id)
                    && has_capability('mod/grouptool:register', $this->context)
                ) {
                    // Groupchange!
                    $label = get_string('change_group', 'grouptool');
                    if (
                        $this->grouptool->usesize
                        && count($group->registered) >= $group->grpsize
                    ) {
                        $label .= ' (' . get_string('queue', 'grouptool') . ')';
                        $class = "btn-secondary";
                    } else {
                        $class = "btn-primary";
                    }
                    $buttonattr = [
                        'type' => 'submit',
                        'name' => 'reg[' . $group->agrpid . ']',
                        'value' => $group->agrpid,
                        'class' => 'regbutton btn ' . $class,
                    ];
                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                } else if ($registrationmanager->is_registration_open()) {
                    $message = new stdClass();
                    $message->username = fullname($USER);
                    $message->groupname = $group->name;
                    $message->userid = $USER->id;

                    try {
                        try {
                            // Can be registered?
                            $permissionmanager->check_can_be_registered($group, $userregs, $userqueues, $usermarks);

                            if (
                                has_capability('mod/grouptool:register', $this->context)
                                || has_capability('mod/grouptool:preview', $this->context)
                            ) {
                                // Register button!
                                $label = get_string('register', 'grouptool');
                                $buttonattr = [
                                    'type' => 'submit',
                                    'name' => 'reg[' . $group->agrpid . ']',
                                    'value' => $group->agrpid,
                                    'class' => 'regbutton btn btn-primary',
                                ];
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        } catch (exceedgroupsize $e) {
                            if (!$this->grouptool->usequeue) {
                                throw new exceedgroupsize();
                            } else {
                                if (
                                    has_capability('mod/grouptool:register', $this->context) ||
                                    has_capability('mod/grouptool:preview', $this->context)
                                ) {
                                    // There's no place left in the group, so we try to queue the user!
                                    $permissionmanager->can_be_queued($group->agrpid, $USER->id, $message);

                                    // Queue button!
                                    $label = get_string('queue', 'grouptool');
                                    $buttonattr = [
                                        'type' => 'submit',
                                        'name' => 'reg[' . $group->agrpid . ']',
                                        'value' => $group->agrpid,
                                        'class' => 'queuebutton btn btn-secondary',
                                    ];
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                }
                            }
                        } catch (notenoughregs $e) {
                            /* The user has not enough registrations, queue entries or marks,
                             * so we try to mark the user! (Exceptions get handled above!) */
                            [$queued, ] = $permissionmanager->can_be_marked($group->agrpid, $USER->id, $message);
                            if (
                                !$queued &&
                                (
                                    has_capability('mod/grouptool:register', $this->context) ||
                                    has_capability('mod/grouptool:preview', $this->context)
                                )
                            ) {
                                // Register button!
                                $label = get_string('register', 'grouptool');
                                $buttonattr = [
                                    'type' => 'submit',
                                    'name' => 'reg[' . $group->agrpid . ']',
                                    'value' => $group->agrpid,
                                    'class' => 'regbutton btn btn-primary',
                                ];
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            } else if (
                                has_capability('mod/grouptool:register', $this->context) ||
                                has_capability('mod/grouptool:preview', $this->context)
                            ) {
                                // Queue button!
                                $label = get_string('queue', 'grouptool');
                                $buttonattr = [
                                    'type' => 'submit',
                                    'name' => 'reg[' . $group->agrpid . ']',
                                    'value' => $group->agrpid,
                                    'class' => 'queuebutton btn btn-secondary',
                                ];
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                    } catch (exceedgroupqueuelimit | exceedgroupsize $e) {
                        // Group is full!
                        $grouphtml .= html_writer::tag(
                            'div',
                            get_string('fullgroup', 'grouptool'),
                            ['class' => 'rank']
                        );
                    } catch (exceeduserqueuelimit $e) {
                        // Too many queues!
                        $grouphtml .= html_writer::tag(
                            'div',
                            get_string(
                                'max_queues_reached',
                                'grouptool'
                            ),
                            ['class' => 'rank']
                        );
                    } catch (exceeduserreglimit $e) {
                        $grouphtml .= html_writer::tag(
                            'div',
                            get_string(
                                'max_regs_reached',
                                'grouptool'
                            ),
                            ['class' => 'rank']
                        );
                    } catch (registration $e) {
                        // No registration possible!
                        $grouphtml .= html_writer::tag('div', '', ['class' => 'rank']);
                    }
                }

                $grouptext = $group->name;
                // Add conversation button if conditions are met.
                if (!empty($group->registered && $queuemanager->get_rank_in_queue($group->registered, $userid) != false)) {
                    // Find group conversation in order to display group message icon.
                    $coursecontext = context_course::instance($this->course->id);
                    $conversation = api::get_conversation_by_area(
                        'core_group',
                        'groups',
                        $group->id,
                        $coursecontext->id
                    );
                    // Check if converastion exists and if user is allowed to access it.
                    if (
                        !empty($conversation) &&
                        api::can_send_message_to_conversation($userid, $conversation->id)
                    ) {
                        $grouptext .= html_writer::link(
                            '#',
                            $OUTPUT->pix_icon(
                                't/message',
                                get_string('open_group_message', 'grouptool')
                            ),
                            ['id' => 'group-message-button', 'data-conversationid' => $conversation->id]
                        );
                        $utils->messagegroup_requirejs();
                    }
                }
                $grouptext = html_writer::tag('h2', $grouptext, ['class' => 'panel-title']);

                $grouppicture = '';
                if (get_config('mod_grouptool', 'show_add_info')) {
                    if (isset($group->description)) {
                        $grouptext .=
                            html_writer::tag('div', $group->description, ['class' => 'panel-desc']);
                    }

                    $groupobj = groups_get_group($group->id);
                    $pictureout = print_group_picture($groupobj, $this->course->id, true, true);
                    if (empty($pictureout)) {
                        $pictureurl = new moodle_url(
                            '/user/index.php',
                            ['id' => $this->course->id, 'group' => $group->id]
                        );
                        $pictureobj = html_writer::img(
                            $OUTPUT->image_url('g/g1')->out(false),
                            $group->name,
                            ['title' => $group->name]
                        ); // Default image.
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
                    $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert alert-success');
                } else if ($queuerank !== false) {
                    $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert alert-warning');
                } else if (($this->grouptool->usesize) && ($registered >= $group->grpsize) && $regopen) {
                    $grouphtml = $OUTPUT->box($grouphtml, 'generalbox group alert alert-danger group-full');
                } else {
                    $classes = 'generalbox group empty';
                    if (($this->grouptool->usesize) && ($registered >= $group->grpsize)) {
                        $classes .= ' group-full';
                    }
                    $grouphtml = $OUTPUT->box($grouphtml, $classes);
                }
                $mform->addElement('html', $grouphtml);
            }
            if ($this->grouptool->showmembers) {
                $params = new stdClass();
                $params->courseid = $this->course;
                $params->showidnumber = has_capability('mod/grouptool:view_regs_group_view', $this->context);
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
     * Outputs the content of the administration tab and manages actions taken in this tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_administration(): void {
        global $SESSION, $OUTPUT, $PAGE, $DB, $USER, $CFG;

        $queuemanager = new queue_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $output = $PAGE->get_renderer('mod_grouptool');

        // Repair possibly missing agrps...
        $groupmanager->add_missing_agrps();

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);

        // Get applicable roles!
        $filter = optional_param('filter', null, PARAM_INT);
        if ($filter !== null) {
            set_user_preference('mod_grouptool_group_filter', $filter, $USER->id);
        } else {
            $filter = get_user_preferences('mod_grouptool_group_filter', self::FILTER_ACTIVE, $USER->id);
        }

        // Adds Filter Selector.
        static $options = null;

        $url = new moodle_url($CFG->wwwroot . '/mod/grouptool/administration.php?id=' . $id . '&amp;tab=group_admin');
        if (!$options) {
            $options = [
                self::FILTER_ACTIVE => get_string('active', 'grouptool'),
                self::FILTER_INACTIVE => get_string('inactive'),
                self::FILTER_ALL => get_string('all'),
            ];
        }
        $groupings = groups_get_all_groupings($this->course->id);
        foreach ($groupings as $grouping) {
            $options[$grouping->id + 10] = $grouping->name;
        }

        $filerselect = new single_select($url, 'filter', $options, $filter);
        // Should this not be in a mustache template?
        $url = new moodle_url($CFG->wwwroot . '/mod/grouptool/administration.php?id=' . $id . '&tab=group_creation');
        echo $OUTPUT->heading(get_string('administration', 'mod_grouptool'));
        echo "<br>";
        echo html_writer::start_tag("div", ["class" => "container"]);
        echo html_writer::start_tag("div", ["class" => "row align-items-start"]);
        echo html_writer::start_tag("div", ["class" => "col-md-4"]);
        echo $OUTPUT->render($filerselect);
        echo html_writer::end_tag("div");
        echo html_writer::start_tag("div", ["class" => "col-md-4 offset-md-4"]);
        echo html_writer::start_tag("div", ["class" => "float-right"]);
        echo html_writer::tag('a', get_string('group_creation', 'grouptool'), ['href' => $url, 'class' => 'btn btn-primary']);
        echo html_writer::end_tag("div");
        echo html_writer::end_tag("div");
        echo html_writer::end_tag("div");
        echo "<br>";
        $bulkaction = optional_param('bulkaction', null, PARAM_ALPHA);
        $selected = optional_param_array('selected', [], PARAM_INT);
        $dialog = false;
        if ($bulkaction && $selected && optional_param('start_bulkaction', 0, PARAM_BOOL)) {
            switch ($bulkaction) {
                case 'activate':  // ...also via ajax bulk action?
                    // Activate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        [$grpsql, $grpparams] = $DB->get_in_or_equal($groups);
                        $DB->set_field_select(
                            "grouptool_agrps",
                            "active",
                            1,
                            " grouptoolid = ? AND groupid " . $grpsql,
                            array_merge([$this->cm->instance], $grpparams)
                        );
                    }
                    echo $OUTPUT->notification(
                        get_string('activated_groups', 'grouptool'),
                        notification::NOTIFY_SUCCESS
                    );
                    break;
                case 'deactivate':  // ...also via ajax bulk action?
                    // Deactivate now!
                    $groups = optional_param_array('selected', null, PARAM_INT);
                    if (!empty($groups)) {
                        [$grpsql, $grpparams] = $DB->get_in_or_equal($groups);
                        $DB->set_field_select(
                            "grouptool_agrps",
                            "active",
                            0,
                            " grouptoolid = ? AND groupid " . $grpsql,
                            array_merge([$this->cm->instance], $grpparams)
                        );
                    }
                    echo $OUTPUT->notification(
                        get_string('deactivated_groups', 'grouptool'),
                        notification::NOTIFY_SUCCESS
                    );
                    break;
                case 'delete': // ...also via ajax bulk action?
                    // Show confirmation dialogue!
                    if (optional_param('confirm', 0, PARAM_BOOL)) {
                        $groups = optional_param_array('selected', null, PARAM_INT);
                        $groups = $DB->get_records_list('groups', 'id', $groups);
                        foreach ($groups as $group) {
                            groups_delete_group($group);
                        }
                        echo $OUTPUT->notification(
                            get_string('successfully_deleted_groups', 'grouptool'),
                            notification::NOTIFY_SUCCESS
                        );
                    } else {
                        $cancel = new moodle_url($PAGE->url, ['tab' => 'group_admin']);
                        $params = ['confirm' => 1, 'bulkaction' => 'delete', 'start_bulkaction' => 1];
                        $text = get_string('confirm_delete', 'grouptool') . html_writer::start_tag('ul');
                        $groups = $DB->get_records_list('groups', 'id', $selected);
                        foreach ($selected as $select) {
                            $params['selected[' . $select . ']'] = $select;
                            $text .= html_writer::tag('li', $groups[$select]->name);
                        }
                        $text .= html_writer::end_tag('ul');
                        $continue = new moodle_url($cancel, $params);

                        echo $utils->confirm($text, $continue, $cancel);
                        $dialog = true;
                    }
                    break;
                case 'grouping':
                    // Show grouping creation form!
                    $selected = optional_param_array('selected', [], PARAM_INT);
                    $mform = new groupings_creation_form(null, [
                        'id' => $id,
                        'selected' => $selected,
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
                                    $grouping->name = $group->name;
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
                                $grouping->name = trim($fromform->name);
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
                        $url = new moodle_url('/mod/grouptool/administration.php', [
                            'id' => $this->cm->id,
                            'tab' => 'group_admin',
                            'filter' => $filter,
                        ]);
                        echo $OUTPUT->notification(get_string(
                            'groupings_created_and_groups_added',
                            'grouptool'
                        ), notification::NOTIFY_SUCCESS);
                    } else {
                        $mform->display();
                        $dialog = true;
                    }
                    break;
            }
        }

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroupings)) {
                require_capability('mod/grouptool:administrate_groups', $this->context);
                $target = required_param('target', PARAM_INT);
                switch ($target) { // ...grpg_target | grpg_groupingname | use_all (0 sel | 1 all).
                    case 0: // Invalid - no action! TODO Add message!
                        $preview = '';
                        break;
                    case -2: // One grouping per group!
                        [, $preview] = $groupmanager->create_group_groupings();
                        break;
                    case -1: // One new grouping for all!
                        [, $preview] = $groupmanager->update_grouping($target, required_param('name', PARAM_ALPHANUMEXT));
                        break;
                    default:
                        [, $preview] = $groupmanager->update_grouping($target);
                        break;
                }
                $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
                echo $OUTPUT->box($preview, 'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        if ($rename = optional_param('rename', 0, PARAM_INT)) {
            // Show Rename Form!
            $gform = new group_rename_form(null, [
                'id' => $this->cm->id,
                'instance' => $this->cm->instance,
                'rename' => $rename,
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
            $gform = new group_resize_form(null, [
                'id' => $this->cm->id,
                'instance' => $this->cm->instance,
                'resize' => $resize,
            ]);
            if (!$gform->is_cancelled() && $fromform = $gform->get_data()) {
                if (empty($fromform->size)) {
                    $DB->set_field('grouptool_agrps', 'grpsize', null, [
                        'groupid' => $fromform->resize,
                        'grouptoolid' => $this->cm->instance,
                    ]);
                } else {
                    $group = new stdClass();
                    $group->id = $DB->get_field('grouptool_agrps', 'id', [
                        'groupid' => $fromform->resize,
                        'grouptoolid' => $this->cm->instance,
                    ]);
                    $group->grpsize = $fromform->size;
                    $DB->update_record('grouptool_agrps', $group);
                }
                $queuemanager->fill_from_queue($fromform->resize);
            } else if (!$gform->is_cancelled()) {
                $data = new stdClass();
                $data->size = $DB->get_field('grouptool_agrps', 'grpsize', [
                    'groupid' => $resize,
                    'grouptoolid' => $this->cm->instance,
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
                    'delete' => $delete,
                ]);
                $cancel = new single_button($cancel, get_string('no'), 'post');
                $continue = new single_button(
                    $continue,
                    get_string('yes'),
                    'post'
                );
                $confirmtext = get_string('confirm_delete', 'grouptool');
                echo $utils->confirm($confirmtext, $continue, $cancel);
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
                    echo $OUTPUT->box($OUTPUT->notification(
                        get_string('group_not_found', 'grouptool'),
                        notification::NOTIFY_ERROR
                    ), 'generalbox');
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
            $formaction = new moodle_url('/mod/grouptool/administration.php', [
                'id' => $this->cm->id,
                'tab' => 'group_admin',
                'filter' => $filter,
            ]);
            $mform = new MoodleQuickForm('bulk', 'post', $formaction, '');

            $mform->addElement('hidden', 'sesskey');
            $mform->setDefault('sesskey', sesskey());

            $sortlist = new sortlist($this->course->id, $this->cm, $filter);
            $mform->addElement('html', $output->render($sortlist));

            $actions = [
                '' => get_string('choose', 'grouptool'),
                'activate' => get_string('setactive', 'grouptool'),
                'deactivate' => get_string('setinactive', 'grouptool'),
            ];
            if (
                !($this->grouptool->ifgroupdeleted === GROUPTOOL_RECREATE_GROUP)
                && !$DB->record_exists('grouptool', ['course' => $this->cm->course,
                    'ifgroupdeleted' => GROUPTOOL_RECREATE_GROUP, ])
            ) {
                $actions['delete'] = get_string('delete');
            }
            $actions['grouping'] = get_string('createinsertgrouping', 'grouptool');
            // Add the bulk action form on the left side of the page.
            $grp = [];
            $grp[] =& $mform->createElement('static', 'with_selection', '', get_string(
                'with_selection',
                'grouptool'
            ));
            $grp[] =& $mform->createElement('select', 'bulkaction', '', $actions);
            $grp[] =& $mform->createElement('submit', 'start_bulkaction', get_string(
                'start',
                'grouptool'
            ));
            $mform->addElement('html', '<br>');
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
            echo html_writer::end_tag("div");
            $params = ['cmid' => $this->cm->id,
                'filter' => $curfilter,
                'filterall' => GROUPTOOL_FILTER_ALL,
                'globalsize' => $this->grouptool->grpsize,
                'usesize' => (bool)$this->grouptool->usesize, ];
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
    public function view_creation(): void {
        global $SESSION, $OUTPUT;

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $id = $this->cm->id;
        $context = $this->context;

        $rolenames = [];
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }

        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->grouptool->view_administration->createGroups)) {
                require_capability('mod/grouptool:administrate_groups', $this->context);
                // Create groups!
                $data = $SESSION->grouptool->view_administration;
                $error = false;
                $preview = '';
                // Display only active users if the option was selected or they do not have the capability to view suspended users.
                $onlyactive = !empty($data->includeonlyactiveenrol)
                    || !has_capability('moodle/course:viewsuspendedusers', $context);
                [$source, $orderby] = $utils->view_creation_get_source_orderby($data);
                switch ($data->mode) {
                    case GROUPTOOL_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        $users = groups_get_potential_members(
                            $this->course->id,
                            $data->roleid,
                            $source,
                            $orderby,
                            null,
                            $onlyactive
                        );
                        $usercnt = count($users);
                        $numgrps = $data->numberofgroups;
                        $userpergrp = floor($usercnt / $numgrps);
                        [$error, $preview] = $groupmanager->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        $users = groups_get_potential_members(
                            $this->course->id,
                            $data->roleid,
                            $source,
                            $orderby,
                            null,
                            $onlyactive
                        );
                        $usercnt = count($users);
                        $numgrps = ceil($usercnt / $data->numberofmembers);
                        $userpergrp = $data->numberofmembers;
                        if (!empty($data->nosmallgroups) && $usercnt % $data->numberofmembers != 0) {
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
                        [$error, $preview] = $groupmanager->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_1_PERSON_GROUPS:
                        $users = groups_get_potential_members(
                            $this->course->id,
                            $data->roleid,
                            $source,
                            'lastname ASC, firstname ASC',
                            null,
                            $onlyactive
                        );
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        [$error, $prev] = $groupmanager->create_one_person_groups(
                            $users,
                            $data->namingscheme,
                            $data->grouping,
                            $data->groupingname,
                            false,
                            $data->enablegroupmessaging
                        );
                        $preview = $prev;
                        break;
                    case GROUPTOOL_N_M_GROUPS:
                        /* Shortcut here: create_fromto_groups does exactly what we want,
                         with from = 1 and to = number of groups to create! */
                        $data->from = 1;
                        $data->to = $data->numberofgroups;
                        $data->digits = 1;
                        // Go on to GROUPTOOL_FROMTO_GROUPS!
                    case GROUPTOOL_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        [$error, $preview] = $groupmanager->create_fromto_groups($data);
                        break;
                }
                if (!$error && has_capability('mod/grouptool:administrate_groups', $this->context)) {
                    $linktext = '<i class="fa fa-long-arrow-right" aria-hidden="true"></i>' .
                        get_string('group_administration', 'grouptool');
                    $urlparams = [
                        'id' => $this->cm->id,
                        'tab' => 'group_admin',
                    ];
                    $preview .= html_writer::link(new moodle_url('/mod/grouptool/view.php', $urlparams), $linktext, [
                        'class' => 'ml-1',
                    ]);
                }
                $preview = $OUTPUT->notification($preview, $error ? notification::NOTIFY_ERROR :
                    notification::NOTIFY_SUCCESS);
                echo $OUTPUT->box(
                    html_writer::tag('div', $preview, ['class' => 'centered']),
                    'generalbox'
                );
            }
            unset($SESSION->grouptool->view_administration);
        }

        // Create the form-object!
        $showgrpsize = $this->grouptool->usesize;
        $mform = new group_creation_form(null, [
            'id' => $id,
            'roles' => $rolenames,
            'show_grpsize' => $showgrpsize,
        ]);
        unset($showgrpsize);
        if ($mform->is_cancelled()) {
            // Go back to the administration tab!
            unset($SESSION->grouptool->view_administration);
            $this->view_administration();
        } else if ($fromform = $mform->get_data()) {
            require_capability('mod/grouptool:administrate_groups', $this->context);
            // Save submitted data in session and show confirmation dialog!
            if (!isset($SESSION->grouptool)) {
                $SESSION->grouptool = new stdClass();
            }
            $SESSION->grouptool->view_administration = $fromform;
            $data = $SESSION->grouptool->view_administration;
            $preview = "";
            $error = false;
            [$source, $orderby] = $utils->view_creation_get_source_orderby($data);
            $onlyactive = !empty($data->includeonlyactiveenrol)
                || !has_capability('moodle/course:viewsuspendedusers', $context);
            switch ($data->mode) {
                case GROUPTOOL_GROUPS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members(
                        $this->course->id,
                        $data->roleid,
                        $source,
                        $orderby,
                        null,
                        $onlyactive
                    );
                    $usercnt = count($users);
                    $numgrps = clean_param($data->numberofgroups, PARAM_INT);
                    $userpergrp = floor($usercnt / $numgrps);
                    [$error, $preview] = $groupmanager->create_groups(
                        $data,
                        $users,
                        $userpergrp,
                        $numgrps,
                        true
                    );
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    $users = groups_get_potential_members(
                        $this->course->id,
                        $data->roleid,
                        $source,
                        $orderby,
                        null,
                        $onlyactive
                    );
                    $usercnt = count($users);
                    $numgrps = ceil($usercnt / $data->numberofmembers);
                    $userpergrp = clean_param($data->numberofmembers, PARAM_INT);
                    if (!empty($data->nosmallgroups) && $usercnt % clean_param($data->numberofmembers, PARAM_INT) != 0) {
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
                    [$error, $preview] = $groupmanager->create_groups(
                        $data,
                        $users,
                        $userpergrp,
                        $numgrps,
                        true
                    );
                    break;
                case GROUPTOOL_1_PERSON_GROUPS:
                    $users = groups_get_potential_members(
                        $this->course->id,
                        $data->roleid,
                        $source,
                        'lastname ASC, firstname ASC',
                        null,
                        $onlyactive
                    );
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    [$error, $prev] = $groupmanager->create_one_person_groups(
                        $users,
                        $data->namingscheme,
                        $data->grouping,
                        $data->groupingname,
                        true,
                        $data->enablegroupmessaging
                    );
                    $preview = $prev;
                    break;
                case GROUPTOOL_N_M_GROUPS:
                    /* Shortcut here: create_fromto_groups does exactly what we want,
                    * with from = 1 and to = number of groups to create! */
                    $data->from = 1;
                    $data->to = $data->numberofgroups;
                    $data->digits = 1;
                    // Go to GROUPTOOL_FROMTO_GROUPS case!
                case GROUPTOOL_FROMTO_GROUPS:
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    [$error, $preview] = $groupmanager->create_fromto_groups($data, true);
                    break;
            }
            $preview = html_writer::tag('div', $preview, ['class' => 'centered']);
            $tab = required_param('tab', PARAM_ALPHANUMEXT);
            if ($error) {
                $text = get_string('create_groups_confirm_problem', 'grouptool');
                $url = new moodle_url("administration.php?id=$id&tab=" . $tab);
                $back = new single_button($url, get_string('back'), 'post');
                $confirmboxcontent = $utils->confirm($text, $back);
            } else {
                $continue = "administration.php?id=$id&tab=" . $tab . "&confirm=true";
                $cancel = "administration.php?id=$id&tab=" . $tab;
                $text = get_string('create_groups_confirm', 'grouptool');
                $confirmboxcontent = $utils->confirm($text, $continue, $cancel);
            }
            echo $OUTPUT->heading(get_string('preview'), 2, 'centered') .
                $OUTPUT->box($preview, 'generalbox') .
                $confirmboxcontent;
        } else {
            $mform->display();
        }
    }

    /**
     * view overview tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_overview(): void {
        global $PAGE, $OUTPUT;

        $registrationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $includeinactive = optional_param('inactive', 0, PARAM_BOOL);
        $url = new moodle_url($PAGE->url, [
            'tab' => 'overview',
            'sesskey' => sesskey(),
            'groupid' => $groupid,
            'groupingid' => $groupingid,
            'orientation' => $orientation,
            'inactive' => $includeinactive,
        ]);
        echo $OUTPUT->heading(get_string('registrations', 'mod_grouptool'));
        // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed?!
            $hideform = false;
            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                [$error, $message] = $registrationmanager->push_registrations($groupid, $groupingid);
                if ($error) {
                    echo $OUTPUT->notification($message, notification::NOTIFY_ERROR);
                } else {
                    echo $OUTPUT->notification($message, notification::NOTIFY_SUCCESS);
                }
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hideform = true;

            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                // This is try only!
                [$error, $message] = $registrationmanager->push_registrations($groupid, $groupingid, true);
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
                echo $utils->confirm($message, $continue, $cancel);
            } else {
                $hideform = false;
            }
        } else {
            $hideform = false;
        }

        if (!$hideform) {
            $groupingselect = $groupmanager->get_grouping_select($url, $groupingid);
            $groupselect = $groupmanager->get_groups_select($url, $groupingid, $groupid);
            $orientationselect = $utils->get_orientation_select($url, $orientation);

            if ($includeinactive) {
                $inactivetext = get_string('inactivegroups_hide', 'grouptool');
                $inactiveurl = new moodle_url($url, ['inactive' => 0]);
            } else {
                $inactivetext = get_string('inactivegroups_show', 'grouptool');
                $inactiveurl = new moodle_url($url, ['inactive' => 1]);
            }

            $syncstatus = $utils->get_sync_status();

            if ($syncstatus[0]) {
                /*
                 * Out of sync? --> show button to get registrations from grouptool to moodle
                 * (just register not already registered persons and let the others be)
                 */
                $url = new moodle_url($PAGE->url, ['tab' => 'overview', 'pushtomdl' => 1, 'sesskey' => sesskey()]);
                $button = new single_button(
                    $url,
                    get_string('updatemdlgrps', 'grouptool'),
                    'post',
                    'primary'
                );
                echo $OUTPUT->box(html_writer::empty_tag('br') .
                    $OUTPUT->render($button) .
                    html_writer::empty_tag('br'), 'generalbox centered');
            }
            $url = new moodle_url($PAGE->url, ['tab' => 'import']);
            $button = null;
            if (has_capability('mod/grouptool:administrate_deregistration', $this->context) && has_capability('mod/grouptool:administrate_registration', $this->context)) {
                $button = new single_button($url, get_string('manage_members', 'grouptool'));
            } else if (has_capability('mod/grouptool:administrate_registration', $this->context)) {
                $button = new single_button($url, get_string('import'));
            } else if (has_capability('mod/grouptool:administrate_deregistration', $this->context)) {
                $url = new moodle_url($PAGE->url, ['tab' => 'unregister']);
                $button = new single_button($url, get_string('unregister', 'grouptool'));
            }

            $queues = "";
            if ($button) {
                echo $OUTPUT->box(html_writer::empty_tag('br') .
                    $OUTPUT->render($button) . $queues .
                    html_writer::empty_tag('br'), 'generalbox');
            }
            echo "<br />";
            // If we don't only get the data, the output happens directly per group!
            $groupmanager->group_overview_table($groupingid, $groupid, false, $includeinactive);
            $select = html_writer::tag(
                'div',
                get_string('grouping', 'group') . '&nbsp;' .
                    $OUTPUT->render($groupingselect),
                ['class' => 'centered grouptool_overview_filter']
            ) .
                html_writer::tag(
                    'div',
                    get_string('group', 'group') . '&nbsp;' .
                    $OUTPUT->render($groupselect),
                    ['class' => 'centered grouptool_overview_filter']
                ) .
                html_writer::tag(
                    'div',
                    get_string('orientation', 'grouptool') . '&nbsp;' .
                    $OUTPUT->render($orientationselect),
                    ['class' => 'centered grouptool_overview_filter']
                ) .
                html_writer::tag(
                    'div',
                    html_writer::link($inactiveurl, $inactivetext),
                    ['class' => 'centered grouptool_overview_filter']
                );
            $data = [
                'containername' => 'grouptool_overview_filter',
                'heading' => get_string('options'),
                'content' => $select,
            ];
            $templatename = "grouptool/downloadoptions";
            echo $OUTPUT->render_from_template($templatename, $data);
        }
    }


    /**
     * view import-tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception|\moodle_exception
     */
    public function view_import(): void {
        global $PAGE, $OUTPUT;

        require_capability('mod/grouptool:administrate_registration', $this->context);

        $utils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $id = $this->cm->id;
        $form = new import_form(null, ['id' => $id]);

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $groups = required_param_array('groups', PARAM_INT);
            $data = required_param('data', PARAM_NOTAGS);
            $forceregistration = optional_param('forceregistration', 0, PARAM_BOOL);
            $ignored = [];
            foreach ($groups as $group) {
                $ignored[$group] = optional_param_array("ignored_$group", [-1 => -1], PARAM_INT);
            }
            [$error, $message] = $utils->import($groups, $data, $ignored, $forceregistration);

            if (!empty($error)) {
                $message = $OUTPUT->notification(
                    get_string('ignored_not_found_users', 'grouptool'),
                    notification::NOTIFY_ERROR
                ) . html_writer::empty_tag('br') . $message;
            }
            echo html_writer::tag('div', $message, ['class' => 'centered']);
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            [$error, $confirmmessage] = $utils->import(
                $fromform->groups,
                $fromform->data,
                [],
                $fromform->forceregistration,
                true
            );
            $formdata = [
                'id' => $id,
                'groups' => $fromform->groups,
                'data' => $fromform->data,
                'forceregistration' => $fromform->forceregistration,
                'confirmmessage' => $confirmmessage,
            ];
            // The form data will be fetched through required_param()! TODO gotta refactor this in the future!
            $confirmform = new import_confirm_form($PAGE->url, $formdata);

            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered');
            if ($error) {
                echo $OUTPUT->notification(
                    get_string('ignoring_not_found_users', 'grouptool'),
                    notification::NOTIFY_ERROR
                );
            }

            $confirmform->display();
        } else {
            $form->display();
        }
    }

    /**
     * view unregister-tab
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws notenoughregs
     * @throws registration
     * @throws required_capability_exception
     */
    public function view_unregister(): void {
        global $PAGE, $OUTPUT;
        require_capability('mod/grouptool:administrate_deregistration', $this->context);

        $registrationmanager = new registration_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $id = $this->cm->id;
        $form = new unregister_form(null, ['id' => $id]);

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $groups = required_param_array('groups', PARAM_INT);
            $data = required_param('data', PARAM_NOTAGS);
            $unregfrommgroups = optional_param('unregfrommgroups', 1, PARAM_BOOL);
            $ignored = [];
            foreach ($groups as $group) {
                $ignored[$group] = optional_param_array("ignored_$group", [-1 => -1], PARAM_INT);
            }
            [$error, $message] = $registrationmanager->unregister($groups, $data, true, false, $unregfrommgroups);

            if (!empty($error)) {
                $message = $OUTPUT->notification(
                    get_string('ignored_not_found_users_unregister', 'grouptool'),
                    notification::NOTIFY_ERROR
                ) . html_writer::empty_tag('br') . $message;
            }
            echo html_writer::tag('div', $message, ['class' => 'centered']);
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            [$error, $confirmmessage] =
                $registrationmanager->unregister(
                    $fromform->groups,
                    $fromform->data,
                    true,
                    true
                );
            $formdata = [
                'id' => $id,
                'groups' => $fromform->groups,
                'data' => $fromform->data,
                'unregfrommgroups' => $fromform->unregfrommgroups,
                'confirmmessage' => $confirmmessage,
            ];
            // The form data will be fetched through required_param()! TODO gotta refactor this in the future!
            $confirmform = new unregister_confirm_form($PAGE->url, $formdata);

            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered');
            if ($error) {
                echo $OUTPUT->notification(
                    get_string('ignoring_not_found_users', 'grouptool'),
                    notification::NOTIFY_ERROR
                );
            }

            $confirmform->display();
        } else {
            $form->display();
        }
    }
}
