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
 * Contains mod_grouptool's group creation form
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

use html_writer;

defined('MOODLE_INTERNAL') || die();

// Global variable $CFG is always set, but with this little wrapper PHPStorm won't give wrong error messages!
if (isset($CFG)) {
    require_once($CFG->libdir . '/formslib.php');
    require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
    require_once($CFG->dirroot . '/mod/grouptool/lib.php');
}

/**
 * class representing the moodleform used in the administration tab
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_creation_form extends \moodleform {
    /** @var \context_module */
    private $context = null;

    /**
     * @var \mod_grouptool\output\sortlist contains reference to our sortlist, so we can alter current active entries afterwards
     */
    private $_sortlist = null;

    /**
     * Update currently active sortlist elements
     *
     * @param bool[] $curactive currently active entries
     * @return void
     */
    public function update_cur_active($curactive = null) {
        if (!empty($curactive) && is_array($curactive)) {
            $this->_sortlist->_options['curactive'] = $curactive;
        }
    }

    /**
     * Definition of group creation form
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition() {
        global $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', ['id' => $cm->course]);
        $grouptool = $DB->get_record('grouptool', ['id' => $cm->instance], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_creation');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:create_groups', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed!
             */
            $mform->addElement('header', 'group_creation', get_string('groupcreation',
                                                                      'grouptool'));

            $options = [0 => get_string('all')];
            $options += $this->_customdata['roles'];
            $mform->addElement('select', 'roleid', get_string('selectfromrole', 'group'), $options);
            $student = get_archetype_roles('student');
            $student = reset($student);

            if ($student and array_key_exists($student->id, $options)) {
                $mform->setDefault('roleid', $student->id);
            }

            $canviewcohorts = has_capability('moodle/cohort:view', $this->context);
            if ($canviewcohorts) {
                $cohorts = cohort_get_available_cohorts($coursecontext, true, 0, 0);
                if (count($cohorts) != 0) {
                    $options = [0 => get_string('anycohort', 'cohort')];
                    foreach ($cohorts as $cohort) {
                         $options[$cohort->id] = $cohort->name;
                    }
                    $mform->addElement('select', 'cohortid', get_string('selectfromcohort',
                                                                        'grouptool'), $options);
                    $mform->setDefault('cohortid', '0');
                }
            } else {
                $cohorts = [];
            }

            if (!$canviewcohorts || (count($cohorts) == 0)) {
                $mform->addElement('hidden', 'cohortid');
                $mform->setType('cohortid', PARAM_INT);
                $mform->setConstant('cohortid', '0');
            }

            $mform->addElement('hidden', 'seed');
            $mform->setType('seed', PARAM_INT);
            global $OUTPUT;

            $radioarray = [];
            $selectionname = get_string('groupcreationmode', 'grouptool');
            $radioarray[0] = $mform->addElement('radio', 'mode', $selectionname, get_string('define_amount_groups', 'grouptool'),
                                                  GROUPTOOL_GROUPS_AMOUNT);
            $radioarray[1] = $mform->addElement('radio', 'mode', '', get_string('define_amount_members', 'grouptool'),
                                                  GROUPTOOL_MEMBERS_AMOUNT);
            $radioarray[2] = $mform->addElement('radio', 'mode', '', get_string('create_1_person_groups', 'grouptool'),
                                                  GROUPTOOL_1_PERSON_GROUPS);
            $radioarray[3] = $mform->addElement('radio', 'mode', '', get_string('create_fromto_groups', 'grouptool'),
                                                  GROUPTOOL_FROMTO_GROUPS);
            $radioarray[4] = $mform->addElement('radio', 'mode', '', get_string('create_n_m_groups', 'grouptool'),
                                                  GROUPTOOL_N_M_GROUPS);

            $radioarray[0]->_helpbutton = $OUTPUT->help_icon('define_amount_groups', 'grouptool', '');
            $radioarray[1]->_helpbutton = $OUTPUT->help_icon('define_amount_members', 'grouptool', '');
            $radioarray[2]->_helpbutton = $OUTPUT->help_icon('create_1_person_groups', 'grouptool', '');
            $radioarray[3]->_helpbutton = $OUTPUT->help_icon('create_fromto_groups', 'grouptool', '');
            $radioarray[4]->_helpbutton = $OUTPUT->help_icon('create_n_m_groups', 'grouptool', '');

            $mform->setDefault('mode', GROUPTOOL_GROUPS_AMOUNT);

            $mform->addElement('text', 'numberofgroups', get_string('number_of_groups', 'grouptool'), ['size' => '4']);
            $mform->hideIf('numberofgroups', 'mode', 'eq', GROUPTOOL_MEMBERS_AMOUNT);
            $mform->hideIf ('numberofgroups', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->hideIf ('numberofgroups', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            $mform->setType('numberofgroups', PARAM_INT);
            $mform->setDefault('numberofgroups', 2);

            $mform->addElement('text', 'numberofmembers', get_string('number_of_members', 'grouptool'), ['size' => '4']);
            $mform->hideIf('numberofmembers', 'mode', 'eq', GROUPTOOL_GROUPS_AMOUNT);
            $mform->hideIf ('numberofmembers', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->setType('numberofmembers', PARAM_INT);
            $mform->setDefault('numberofmembers', $grouptool->grpsize);

            $fromto = [];
            $fromto[] = $mform->createElement('text', 'from', get_string('from'));
            $mform->setDefault('from', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('from', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'to', get_string('to'));
            $mform->setDefault('to', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('to', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'digits', get_string('digits', 'grouptool'));
            $mform->setDefault('digits', 2);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('digits', PARAM_RAW);
            $fromtoglue = [
                ' '.\html_writer::tag('label', '-', ['for' => 'id_from']).' ',
                ' '.\html_writer::tag('label', get_string('digits', 'grouptool'), ['for' => 'id_digits']).' '
            ];
            $mform->addGroup($fromto, 'fromto', get_string('groupfromtodigits', 'grouptool'), $fromtoglue, false);
            $mform->hideIf ('fromto', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);

            $mform->addElement('checkbox', 'nosmallgroups', get_string('nosmallgroups', 'group'));
            $mform->addHelpButton('nosmallgroups', 'nosmallgroups', 'grouptool');
            $mform->hideIf ('nosmallgroups', 'mode', 'noteq', GROUPTOOL_MEMBERS_AMOUNT);
            $mform->hideIf ('nosmallgroups', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            $mform->hideIf ('nosmallgroups', 'mode', 'eq', GROUPTOOL_N_M_GROUPS);

            $options = [
                'no'        => get_string('noallocation', 'group'),
                'random'    => get_string('random', 'group'),
                'firstname' => get_string('byfirstname', 'group'),
                'lastname'  => get_string('bylastname', 'group'),
                'idnumber'  => get_string('byidnumber', 'group')
            ];
            $mform->addElement('select', 'allocateby', get_string('allocateby', 'group'), $options);
            if ($grouptool->allow_reg) {
                $mform->setDefault('allocateby', 'no');
            } else {
                $mform->setDefault('allocateby', 'random');
            }
            $mform->hideIf ('allocateby', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->hideIf ('allocateby', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            $mform->hideIf ('allocateby', 'mode', 'eq', GROUPTOOL_N_M_GROUPS);

            $tags = [];
            foreach (\mod_grouptool::NAME_TAGS as $tag) {
                $tags[] = html_writer::tag('span', $tag, ['class' => 'nametag', 'data-nametag' => $tag]);
            }

            $naminggrp = [];
            $naminggrp[] =& $mform->createElement('text', 'namingscheme', '', ['size' => '64']);
            $naminggrp[] =& $mform->createElement('static', 'tags', '', implode("", $tags));
            $namingstd = get_config('mod_grouptool', 'name_scheme');
            $namingstd = (!empty($namingstd) ? $namingstd : get_string('group', 'group').' #');
            $mform->setDefault('namingscheme', $namingstd);
            $mform->setType('namingscheme', PARAM_RAW);
            $mform->addGroup($naminggrp, 'naminggrp', get_string('namingscheme', 'grouptool'), ' ', false);
            $mform->addHelpButton('naminggrp', 'namingscheme', 'grouptool');
            // Init JS!
            $PAGE->requires->js_call_amd('mod_grouptool/groupcreation', 'initializer');

            $selectgroups = $mform->createElement('selectgroups', 'grouping', get_string('createingrouping', 'group'));

            $options = ['0' => get_string('no')];
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $options['-1'] = get_string('onenewgrouping', 'grouptool');
            }
            $selectgroups->addOptGroup("", $options);
            if ($groupings = groups_get_all_groupings($course->id)) {
                $options = [];
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = strip_tags(format_string($grouping->name));
                }
                $selectgroups->addOptGroup("————————————————————————", $options);
            }
            $mform->addElement($selectgroups);

            if ($groupings) {
                $mform->setDefault('grouping', '0');
            }
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $mform->addElement('text', 'groupingname', get_string('groupingname', 'group'));
                $mform->setType('groupingname', PARAM_TEXT);
                $mform->hideIf ('groupingname', 'grouping', 'noteq', '-1');
            }

            $mform->addElement('select', 'enablegroupmessaging', get_string('enablemessaging', 'group'),
                array(0 => get_string('no'), 1 => get_string('yes')));
            $mform->addHelpButton('enablegroupmessaging', 'enablemessaging', 'group');

            $mform->addElement('submit', 'createGroups', get_string('createGroups', 'grouptool'));
        }
    }

    /**
     * Validation for administration-form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $parenterrors = parent::validation($data, $files);
        $errors = [];
        if (!empty($data['createGroups']) && $data['grouping'] == "-1"
                && (empty($data['groupingname']) || $data['groupingname'] == "")) {
            $errors['groupingname'] = get_string('must_specify_groupingname', 'grouptool');
        }
        if (!empty($data['createGroups']) && in_array($data['mode'], [GROUPTOOL_GROUPS_AMOUNT, GROUPTOOL_N_M_GROUPS])
                && ($data['numberofgroups'] <= 0)) {
            $errors['numberofgroups'] = get_string('mustbeposint', 'grouptool');
        }
        if (!empty($data['createGroups'])) {
            switch($data['mode']) {
                case GROUPTOOL_N_M_GROUPS:
                case GROUPTOOL_FROMTO_GROUPS:
                    if ($data['numberofmembers'] < 0) {
                        $errors['numberofmembers'] = get_string('mustbegt0', 'grouptool');
                    }
                    break;
                case GROUPTOOL_MEMBERS_AMOUNT:
                    if ($data['numberofmembers'] <= 0) {
                        $errors['numberofmembers'] = get_string('mustbeposint', 'grouptool');
                    }
                    break;
            }
        }
        if (!empty($data['createGroups']) && ($data['mode'] == GROUPTOOL_FROMTO_GROUPS)) {
            if ($data['from'] > $data['to']) {
                $errors['fromto'] = get_string('fromgttoerror', 'grouptool');
            }
            if ((clean_param($data['from'], PARAM_INT) < 0) || !ctype_digit($data['from'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('from').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('from').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['to'], PARAM_INT) < 0) || !ctype_digit($data['to'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('to').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('to').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['digits'], PARAM_INT) < 0) || !ctype_digit($data['digits'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('digits', 'grouptool').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('digits', 'grouptool').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
        }

        return array_merge($parenterrors, $errors);
    }
}
