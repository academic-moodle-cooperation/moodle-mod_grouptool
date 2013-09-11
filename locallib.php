<?php
// This file is made for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal library of functions for module grouptool
 *
 * All the grouptool specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * class representing the moodleform used in the administration tab
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_admin_form extends moodleform {
    /**
     * Definition of administration form
     *
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     */
    protected function definition() {

        global $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = context_module::instance($this->_customdata['id']);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'administration');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:create_groups', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed!
             */
            $mform->addElement('header', 'group_creation', get_string('groupcreation',
                                                                      'grouptool'));

            $options = array(0=>get_string('all'));
            $options += $this->_customdata['roles'];
            $mform->addElement('select', 'roleid', get_string('selectfromrole', 'group'), $options);
            $student = get_archetype_roles('student');
            $student = reset($student);

            if ($student and array_key_exists($student->id, $options)) {
                $mform->setDefault('roleid', $student->id);
            }

            $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
            $course = $DB->get_record('course', array('id'=>$cm->course));
            $grouptool = $DB->get_record('grouptool', array('id'=>$cm->instance), '*', MUST_EXIST);
            $context = context_course::instance($cm->course);

            if (has_capability('moodle/cohort:view', $context)) {
                $options = cohort_get_visible_list($course);
                if ($options) {
                    $options = array(0=>get_string('anycohort', 'cohort')) + $options;
                    $mform->addElement('select', 'cohortid', get_string('selectfromcohort',
                                                                        'grouptool'), $options);
                    $mform->setDefault('cohortid', '0');
                } else {
                    $mform->addElement('hidden', 'cohortid');
                    $mform->setType('cohortid', PARAM_INT);
                    $mform->setConstant('cohortid', '0');
                }
            } else {
                $mform->addElement('hidden', 'cohortid');
                $mform->setType('cohortid', PARAM_INT);
                $mform->setConstant('cohortid', '0');
            }

            $mform->addElement('hidden', 'seed');
            $mform->setType('seed', PARAM_INT);

            $radioarray=array();
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('define_amount_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_GROUPS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('define_amount_members',
                                                                       'grouptool'),
                                                            GROUPTOOL_MEMBERS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('create_1_person_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_1_PERSON_GROUPS);
            $radioarray[] = $mform->createElement('radio', 'mode', '',
                                                            get_string('create_fromto_groups',
                                                                       'grouptool'),
                                                            GROUPTOOL_FROMTO_GROUPS);
            $mform->addGroup($radioarray, 'modearray',
                             get_string('groupcreationmode', 'grouptool'),
                             html_writer::empty_tag('br'), false);
            $mform->setDefault('mode', GROUPTOOL_GROUPS_AMOUNT);
            $mform->addHelpButton('modearray', 'groupcreationmode', 'grouptool');

            $mform->addElement('text', 'amount', get_string('group_or_member_count', 'grouptool'),
                               array('size'=>'4'));
            $mform->disabledIf('amount', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->disabledIf('amount', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            //We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0
            $mform->setType('amount', PARAM_RAW);
            $mform->setDefault('amount', 2);

            $fromto = array();
            $fromto[] = $mform->createElement('text', 'from', get_string('from'));
            $mform->setDefault('from', 0);
            //We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0
            $mform->setType('from', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'to', get_string('to'));
            $mform->setDefault('to', 0);
            //We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0
            $mform->setType('to', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'digits', get_string('digits', 'grouptool'));
            $mform->setDefault('digits', 2);
            //We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0
            $mform->setType('digits', PARAM_RAW);
            $mform->addGroup($fromto, 'fromto', get_string('groupfromtodigits', 'grouptool'),
                             array(' - ', ' '.get_string('digits', 'grouptool').' '), false);
            $mform->disabledIf('from', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->disabledIf('to', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->disabledIf('digits', 'mode', 'noteq', GROUPTOOL_FROMTO_GROUPS);
            $mform->setAdvanced('fromto');

            $mform->addElement('checkbox', 'nosmallgroups', get_string('nosmallgroups', 'group'));
            $mform->addHelpButton('nosmallgroups', 'nosmallgroups', 'grouptool');
            $mform->disabledIf('nosmallgroups', 'mode', 'noteq', GROUPTOOL_MEMBERS_AMOUNT);
            $mform->disabledIf('nosmallgroups', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);
            $mform->setAdvanced('nosmallgroups');

            $options = array('no'        => get_string('noallocation', 'group'),
                    'random'    => get_string('random', 'group'),
                    'firstname' => get_string('byfirstname', 'group'),
                    'lastname'  => get_string('bylastname', 'group'),
                    'idnumber'  => get_string('byidnumber', 'group'));
            $mform->addElement('select', 'allocateby', get_string('allocateby', 'group'), $options);
            if ($grouptool->allow_reg) {
                $mform->setDefault('allocateby', 'no');
            } else {
                $mform->setDefault('allocateby', 'random');
            }
            $mform->disabledIf('allocateby', 'mode', 'eq', GROUPTOOL_1_PERSON_GROUPS);
            $mform->disabledIf('allocateby', 'mode', 'eq', GROUPTOOL_FROMTO_GROUPS);

            $mform->addElement('text', 'namingscheme', get_string('namingscheme', 'grouptool'),
                               array('size'=>'64'));
            $namingstd = (isset($CFG->grouptool_name_scheme) ? $CFG->grouptool_name_scheme
                                                             : get_string('group', 'group').' #');
            $mform->setDefault('namingscheme', $namingstd);
            $mform->setType('namingscheme', PARAM_RAW);

            $mform->addElement('static', 'tags', get_string('tags', 'grouptool'),
                               get_string('name_scheme_tags', 'grouptool'));
            $mform->addHelpButton('tags', 'tags', 'grouptool');
            // Init JS!
            $PAGE->requires->string_for_js('showmore', 'form');
            $PAGE->requires->string_for_js('showless', 'form');
            $PAGE->requires->yui_module('moodle-mod_grouptool-administration',
                    'M.mod_grouptool.init_administration',
                    array(array('fromto_mode'=>GROUPTOOL_FROMTO_GROUPS)));

            $options = array('0' => get_string('no'));
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $options['-1'] = get_string('newgrouping', 'group');
            }
            if ($groupings = groups_get_all_groupings($course->id)) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = strip_tags(format_string($grouping->name));
                }
            }
            $mform->addElement('select', 'grouping', get_string('createingrouping', 'group'),
                               $options);
            if ($groupings) {
                $mform->setDefault('grouping', '0');
            }
            if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                $mform->addElement('text', 'groupingname', get_string('groupingname', 'group'));
                $mform->setType('groupingname', PARAM_MULTILANG);
                $mform->disabledIf('groupingname', 'grouping', 'noteq', '-1');
            }

            $mform->addElement('submit', 'createGroups', get_string('createGroups', 'grouptool'));
        }

        if (has_capability('mod/grouptool:create_groupings', $this->context)) {
            $mform->addElement('header', 'groupingscreateHeader', get_string('groupingscreation',
                                                                             'grouptool'));

            $mform->addElement('html', get_string('groupingscreatedesc', 'grouptool'));
            $coursegroups = groups_get_all_groups($course->id, null, null, "id");
            if (is_array($coursegroups) && !empty($coursegroups)) {
                $options = array(0 => get_string('selected', 'grouptool'), 1 => get_string('all'));
                $mform->addElement('select', 'use_all',
                                   get_string('use_all_or_chosen', 'grouptool'), $options);
                $mform->addHelpButton('use_all', 'use_all_or_chosen', 'grouptool');
                $mform->setType('use_all', PARAM_BOOL);

                $mform->addElement('submit', 'createGroupings', get_string('createGroupings',
                                                                           'grouptool'));
            } else {
                $mform->addElement('static', html_writer::tag('div', get_string('sortlist_no_data',
                                                                              'grouptool')));
            }

        }

        if (has_capability('mod/grouptool:create_groups', $this->context)
                || has_capability('mod/grouptool:create_groupings', $this->context)) {
            $mform->addElement('header', 'groupsettings', get_string('agroups', 'grouptool'));

            // Register our custom form control.
            $nogroups = 0;
            MoodleQuickForm::registerElementType('sortlist',
                    "$CFG->dirroot/mod/grouptool/sortlist.php",
                    'MoodleQuickForm_sortlist');
            // Prepare agrp-data!
            $coursegroups = groups_get_all_groups($course->id, null, null, "id");
            if (is_array($coursegroups) && !empty($coursegroups)) {
                $groups = array();
                foreach ($coursegroups as $group) {
                    $groups[] = $group->id;
                }
                list($grps_sql, $params) = $DB->get_in_or_equal($groups);
                $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
                $params = array_merge(array($cm->instance), $params);
                $groupdata = (array)$DB->get_records_sql("
                        SELECT grp.id AS id, grp.name AS name,
                               agrp.grpsize AS grpsize, agrp.active AS active,
                               agrp.sort_order AS sort_order
                        FROM {groups} AS grp
                        LEFT JOIN {grouptool_agrps} as agrp
                             ON agrp.group_id = grp.id AND agrp.grouptool_id = ?
                        LEFT JOIN {groupings_groups}
                             ON {groupings_groups}.groupid = grp.id
                        LEFT JOIN {groupings} AS grpgs
                             ON {groupings_groups}.groupingid = grpgs.id
                        WHERE grp.id ".$grps_sql."
                        GROUP BY grp.id
                        ORDER BY sort_order ASC, name ASC", $params);
                /*
                 * convert to multidimensional array and replace comma separated string
                 * through array for each classes list
                 */
                $running_index = 1;
                foreach ($groupdata as $key => $group) {
                    $groupdata[$key]->sort_order = $running_index;
                    $running_index++;
                    $groupdata[$key] = (array)$group;
                    $pattern = "#[^a-zA-Z0-9]#";
                    $replace = "";
                    $groupdata[$key]['classes'] = $DB->get_fieldset_select('groupings_groups',
                                                                           'groupingid',
                                                                           ' groupid = ?',
                                                                           array($key));
                    foreach ($groupdata[$key]['classes'] as $classkey => $class) {
                        $groupdata[$key]['classes'][$classkey] = 'class'.$class;
                    }
                }
            } else {
                $nogroups = 1;
            }

            if ($nogroups != 1) {
                $options = array();
                $options['classes'] = groups_get_all_groupings($course->id);
                $options['add_fields'] = array();
                $options['add_fields']['grpsize'] = new stdClass();
                $options['add_fields']['grpsize']->name = 'grpsize';
                $options['add_fields']['grpsize']->stdvalue = $grouptool->grpsize ?
                                                              $grouptool->grpsize :
                                                              $CFG->grouptool_grpsize;
                if (!empty($this->_customdata['show_grpsize'])) {
                    $options['add_fields']['grpsize']->label = get_string('groupsize', 'grouptool');
                    $options['add_fields']['grpsize']->type = 'text';
                } else {
                    $options['add_fields']['grpsize']->label = '';
                    $options['add_fields']['grpsize']->type = 'hidden';
                }
                $options['add_fields']['grpsize']->attr = array('size' => '3');
                $options['all_string'] = get_string('all').' '.get_string('groups');
                $mform->addElement('sortlist', 'grouplist', $options);
                $mform->setDefault('grouplist', $groupdata);
                // Add disabledIfs for all Groupsize-Fields!
                $use_size = $grouptool->use_size;
                $mform->addElement('hidden', 'use_size');
                $mform->setDefault('use_size', $use_size);
                $mform->setType('use_size', PARAM_BOOL);
                $use_individual = $grouptool->use_individual;
                $mform->addElement('hidden', 'use_individual');
                $mform->setDefault('use_individual', $use_individual);
                $mform->setType('use_individual', PARAM_BOOL);

                foreach ($groupdata as $key => $group) {
                    $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_size',
                                       'eq', 0);
                    $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_individual',
                                       'eq', 0);
                }
            } else {
                $mform->addElement('sortlist', 'grouplist', array());
                $mform->setExpanded('groupsettings');
                $mform->setExpanded('groupingscreateHeader');
                $mform->setDefault('grouplist', null);
            }

            if (has_capability('mod/grouptool:create_groups', $this->context) && ($nogroups != 1)) {
                $mform->addElement('submit', 'updateActiveGroups', get_string('savechanges'));
            }
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
     */
    public function validation($data, $files) {
        global $DB;
        $parent_errors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['createGroups']) && $data['grouping'] == "-1"
                 && (empty($data['groupingname']) || $data['groupingname'] == "")) {
            $errors['groupingname'] = get_string('must_specify_groupingname', 'grouptool');
        }
        if (!empty($data['createGroups'])
            && ((clean_param($data['amount'], PARAM_INT) <= 0) || !ctype_digit($data['amount']))
            && (($data['mode'] == GROUPTOOL_GROUPS_AMOUNT) || ($data['mode'] == GROUPTOOL_MEMBERS_AMOUNT))) {
            $errors['amount'] = get_string('mustbeposint', 'grouptool');
        }
        if (!empty($data['createGroups'])
            && ($data['mode'] == GROUPTOOL_FROMTO_GROUPS)) {
            if ($data['from'] > $data['to']) {
                $errors['fromto'] = get_string('fromgttoerror', 'grouptool');
            }
            if ((clean_param($data['from'], PARAM_INT) < 0) || !ctype_digit($data['from'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('from').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('from').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['to'], PARAM_INT) < 0) || !ctype_digit($data['to'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('to').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('to').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
            if ((clean_param($data['digits'], PARAM_INT) < 0) || !ctype_digit($data['digits'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= html_writer::empty_tag('br').
                                         get_string('digits', 'grouptool').': '.
                                         get_string('mustbegt0', 'grouptool');
                } else {
                    $errors['fromto'] = get_string('digits', 'grouptool').': '.
                                        get_string('mustbegt0', 'grouptool');
                }
            }
        }
        if (!empty($data['updateActiveGroups'])
           && (!empty($data['use_size']) && !empty($data['use_individual']))) {
            $sql = '
   SELECT agrps.group_id as id, COUNT(reg.id) as regcnt
     FROM {grouptool_agrps} as agrps
LEFT JOIN {grouptool_registered} as reg ON reg.agrp_id = agrps.id
    WHERE agrps.grouptool_id = :grouptoolid
 GROUP BY agrps.group_id';
            $cm = get_coursemodule_from_id('grouptool', $data['id']);
            $params = array('grouptoolid' => $cm->instance);
            $regs = $DB->get_records_sql_menu($sql, $params);
            $toomanyregs = '';
            foreach ($data['grouplist'] as $group_id => $curgroup) {
                if ((clean_param($curgroup['grpsize'], PARAM_INT) <= 0) || !ctype_digit($curgroup['grpsize'])) {
                    if (!isset($errors['grouplist']) || ($errors['grouplist'] == '')) {
                        $errors['grouplist'] = get_string('grpsizezeroerror', 'grouptool').' '.
                                               get_string('error_at', 'grouptool').' '.$curgroup['name'];
                    } else {
                        $errors['grouplist'] .= ', '.$curgroup['name'];
                    }
                } else if (!empty($regs[$group_id]) && $curgroup['grpsize'] < $regs[$group_id]) {
                    if (empty($toomanyregs)) {
                        $toomanyregs = get_string('toomanyregs', 'grouptool');
                    }
                }
            }
            if($toomanyregs != '') {
                if (!isset($errors['grouplist']) || ($errors['grouplist'] == '')) {
                    $errors['grouplist'] = $toomanyregs;
                } else {
                    $errors['grouplist'] .= html_writer::empty_tag('br').$toomanyregs;
                }
            }
        }
        return array_merge($parent_errors, $errors);
    }
}

/**
 * class representing the moodleform used in the import-tab
 *
 * @package       mod
 * @subpackage    grouptool
 * @copyright     2012 onwards Philipp Hager {@link e0803285@gmail.com}
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_import_form extends moodleform {
    /**
     * Definition of import form
     *
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     */
    protected function definition() {

        global $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id'=>$cm->course));

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'import');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:register_students', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed
             */
            $mform->addElement('header', 'groupuser_import', get_string('groupuser_import',
                                                                        'grouptool'));

            $grps = groups_get_all_groups($course->id);
            $options = array('none'=>get_string('choose'));
            foreach ($grps as $grp) {
                $options[$grp->id] = $grp->name;
            }
            $mform->addElement('select', 'group', get_string('choose_targetgroup', 'grouptool'),
                               $options);
            $mform->setType('group', PARAM_INT);
            $mform->addRule('group', null, 'required', null, 'client');

            $mform->addElement('textarea', 'data', get_string('userlist', 'grouptool'),
                    array('wrap'=>'virtual',
                            'rows'=>'20',
                            'cols'=>'50'));
            $mform->addHelpButton('data', 'userlist', 'grouptool');
            $mform->addRule('data', null, 'required', null, 'client');
            $mform->addRule('data', null, 'required', null, 'server');

            $mform->addElement('advcheckbox', 'forceregistration', '', '&nbsp;'.get_string('forceregistration', 'grouptool'));
            $mform->addHelpButton('forceregistration', 'forceregistration', 'grouptool');
            
            $mform->addElement('submit', 'submitbutton', get_string('importbutton', 'grouptool'));
        }
    }

    /**
     * Validation for import form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['group']) || ($data['group'] == 'none')) {
            $errors['group'] = get_string('choose_group', 'grouptool');
        }
        return $errors;
    }
}

/**
 * class containing most of the logic used in grouptool-module
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouptool {
    /** @var object */
    private $cm;
    /** @var object */
    private $course;
    /** @var object */
    private $grouptool;
    /** @var string */
    private $strgrouptool;
    /** @var string */
    private $strgrouptools;
    /** @var string */
    private $strlastmodified;
    /** @var string */
    private $pagetitle;
    /** @var bool */
    private $usehtmleditor;
    /** @var not really used, stores return from editors_get_preferred_format() */
    private $defaultformat;
    /** @var object instance's context record */
    private $context;

    /**
     * Constructor for the grouptool class
     *
     * If cmid is set create the cm, course, checkmark objects.
     *
     * @global object $DB
     * @param int $cmid the current course module id - not set for new grouptools
     * @param object $grouptool usually null, but if we have it we pass it to save db access
     * @param object $cm usually null, but if we have it we pass it to save db access
     * @param object $course usually null, but if we have it we pass it to save db access
     */
    public function __construct($cmid='staticonly', $grouptool=null, $cm=null, $course=null) {
        global $DB;

        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }

        global $CFG;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('grouptool', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('invalidid', 'grouptool');
        }

        if ($grouptool) {
            $this->grouptool = $grouptool;
        } else if (! $this->grouptool = $DB->get_record('grouptool',
                                                        array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'grouptool');
        }

        $this->grouptool->cmidnumber = $this->cm->idnumber;
        $this->grouptool->course   = $this->course->id;

        $this->strgrouptool = get_string('modulename', 'grouptool');
        $this->strgrouptools = get_string('modulenameplural', 'grouptool');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strgrouptool.': '.
                                      format_string($this->grouptool->name, true));

        /*
         * visibility handled by require_login() with $cm parameter
         * get current group only when really needed
         */

        // Set up things for a HTML editor if it's needed!
        $this->defaultformat = editors_get_preferred_format();
    }

    public function get_name() {
        return $this->grouptool->name;
    }

    /**
     * Print a message along with button choices for Continue/Cancel
     *
     * If a string or moodle_url is given instead of a single_button, method defaults to post.
     * If cancel=null only continue button is displayed!
     *
     * @global object $OUTPUT
     * @param string $message The question to ask the user
     * @param single_button|moodle_url|string $continue The single_button component representing the
     *                                                  Continue answer. Can also be a moodle_url
     *                                                  or string URL
     * @param single_button|moodle_url|string $cancel   The single_button component representing the
     *                                                  Cancel answer. Can also be a moodle_url or
     *                                                  string URL
     * @return string HTML fragment
     */
    public function confirm($message, $continue, $cancel = null) {
        global $OUTPUT;
        if (!($continue instanceof single_button)) {
            if (is_string($continue)) {
                $url = new moodle_url($continue);
                $continue = new single_button($url, get_string('continue'), 'post');
            } else if ($continue instanceof moodle_url) {
                $continue = new single_button($continue, get_string('continue'), 'post');
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

        $output = $OUTPUT->box_start('generalbox', 'notice');
        $output .= html_writer::tag('p', $message);
        $cancel =  ($cancel != null) ? $OUTPUT->render($cancel) : "";
        $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel,
                                    array('class' => 'buttons'));
        $output .= $OUTPUT->box_end();
        return $output;
    }

    /**
     * Parse a group name for characters to replace
     *
     * @param string $name_scheme The scheme used for building group names
     * @param int $groupnumber The number of the group to be used in the parsed format string
     * @param object|array $members optional object or array of objects containing data of members
     *                              for the tags to be replaced with
     * @return string the parsed format string
     */
    private function groups_parse_name($name_scheme, $groupnumber, $members = null, $digits = 0) {

        $tags = array('firstname', 'lastname', 'idnumber', 'username');
        $preg_search = "#\[(".implode("|", $tags).")\]#";
        if (preg_match($preg_search, $name_scheme) > 0) {
            if ($members != null) {
                $data = array();
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
                $name_scheme = str_replace($tags, $data, $name_scheme);
            } else {
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[".$tag."]";
                }
                $name_scheme = str_replace($tags, "", $name_scheme);
            }
        }

        if (strstr($name_scheme, '@') !== false) { // Convert $groupnumber to a character series!
            if ($groupnumber > GROUPTOOL_BEP) {
                $next_tempnumber = $groupnumber;
                $string = "";
                $orda = ord('A');
                $ordz = ord('Z');
                $return = "";
                do {
                    $tempnumber = $next_tempnumber;
                    $mod = ($tempnumber) % ($ordz - $orda+1);
                    $letter = chr($orda + $mod);
                    $string .= $letter;
                    $next_tempnumber = floor(($tempnumber)/($ordz - $orda +1))-1;
                } while ($tempnumber >= ($ordz - $orda+1));

                $name_scheme = str_replace('@', strrev($string), $name_scheme);
            } else {
                $letter = 'A';
                for ($i=0; $i<$groupnumber; $i++) {
                    $letter++;
                }
                $name_scheme = str_replace('@', $letter, $name_scheme);
            }

        }

        if (strstr($name_scheme, '#') !== false) {
            if ($digits != 0) {
                $format = '%0'.$digits.'d';
            } else {
                $format = '%d';
            }
            $name_scheme = str_replace('#', sprintf($format, $groupnumber+1), $name_scheme);
        }
        return $name_scheme;
    }

    /**
     * Update active group settings for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @param object $grouplist List of groups as returned by sortlist-Element
     * @param int $grouptool_id optinoal ID of the instance to update for
     * @return true if successfull
     */
    private function update_active_groups($grouplist, $grouptool_id = null) {
        global $DB, $PAGE;

        require_capability('mod/grouptool:create_groups', $this->context);
        if ($grouptool_id == null) {
            $grouptool_id = $this->grouptool->id;
        }

        if (!empty($grouplist) && is_array($grouplist)) {
            $agrpids = $DB->get_records('grouptool_agrps', array('grouptool_id' => $grouptool_id),
                                        '', 'group_id, id');
            // Update grouptools additional group-data!
            foreach ($grouplist as $groupid => $groupdata) {
                $dataobj = new stdClass();
                $dataobj->grouptool_id = $grouptool_id;
                $dataobj->group_id = $groupid;
                $dataobj->sort_order = $groupdata['sort_order'];
                if (isset($groupdata['grpsize'])) {
                    $dataobj->grpsize = $groupdata['grpsize'];
                }
                $dataobj->active = $groupdata['active'];
                if (key_exists($groupid, $agrpids)) {
                    $dataobj->id = $agrpids[$groupid]->id;
                    $DB->update_record('grouptool_agrps', $dataobj);
                } else {
                    $dataobj->id = $DB->insert_record('grouptool_agrps', $dataobj, true);
                }
            }

            grouptool_update_queues($this->grouptool);

            add_to_log($this->grouptool->course, 'grouptool', 'update agrps',
                    "view.php?id=".$this->grouptool->id."&tab=overview",
                    'via form', $this->cm->id);
        }
        return true;
    }

    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param object $data data from administration-form with all settings for group creation
     * @param array $users which users to registrate in the created groups
     * @param int $userpergrp how many users should be registrated per group
     * @param int $numgrps how many groups should be created
     * @param bool $only_preview optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_groups($data, $users, $userpergrp, $numgrps, $only_preview = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        $names_to_use = array();

        // Allocate members from the selected role to groups!
        $usercnt = count($users);
        switch ($data->allocateby) {
            case 'no':
            case 'random':
            case 'lastname':
                $orderby = 'lastname, firstname';
                break;
            case 'firstname':
                $orderby = 'firstname, lastname';
                break;
            case 'idnumber':
                $orderby = 'idnumber';
                break;
            default:
                print_error('unknoworder');
        }

        if ($data->allocateby == 'random') {
            srand($data->seed);
            shuffle($users);
        }

        $groups = array();

        // Allocate the users - all groups equal count first!
        for ($i=0; $i<$numgrps; $i++) {
            $groups[$i] = array();
            $groups[$i]['members'] = array();
            if ($data->allocateby == 'no') {
                continue; // Do not allocate users!
            }
            for ($j=0; $j<$userpergrp; $j++) {
                if (empty($users)) {
                    break 2;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Now distribute the rest!
        if ($data->allocateby != 'no') {
            for ($i=0; $i<$numgrps; $i++) {
                if (empty($users)) {
                    break 1;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Every member is there, so we can parse the name!
        $digits = ceil(log10($numgrps));
        for ($i=0; $i<$numgrps; $i++) {
            $groups[$i]['name']    = $this->groups_parse_name(trim($data->namingscheme), $i,
                                                              $groups[$i]['members'], $digits);
        }
        if ($only_preview) {
            $error = false;
            $table = new html_table();
            if ($data->allocateby == 'no') {
                $table->head  = array(get_string('groupscount', 'group', $numgrps));
                $table->size  = array('100%');
                $table->align = array('left');
                $table->width = '40%';
            } else {
                $table->head  = array(get_string('groupscount', 'group', $numgrps),
                                      get_string('groupmembers', 'group'),
                                      get_string('usercounttotal', 'group', $usercnt));
                $table->size  = array('20%', '70%', '10%');
                $table->align = array('left', 'left', 'center');
                $table->width = '90%';
            }
            $table->data  = array();

            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $names_to_use)) {
                    $error = true;
                    if(in_array($group['name'], $names_to_use)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
                    }
                } else {
                    $line[] = $group['name'];
                    $names_to_use[] = $group['name'];
                }
                if ($data->allocateby != 'no') {
                    $unames = array();
                    foreach ($group['members'] as $user) {
                        $unames[] = fullname($user, true);
                    }
                    $line[] = implode(', ', $unames);
                    $line[] = count($group['members']);
                }
                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = array();
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

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (@groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    continue;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group['name'];
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $new_agrp = new stdClass();
                $new_agrp->group_id = $groupid;
                $new_agrp->grouptool_id = $this->grouptool->id;
                $new_agrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $new_agrp->active = 1;
                } else {
                    $new_agrp->active = 0;
                }
                $attr = array('grouptool_id' => $this->grouptool->id,
                              'group_id'     => $groupid);
                if (!$DB->record_exists('grouptool_agrps', $attr)) {
                    $new_agrp->id = $DB->insert_record('grouptool_agrps', $new_agrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $new_agrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id'=>$new_agrp->id));
                    }
                }
                $createdgroups[] = $groupid;
                foreach ($group['members'] as $user) {
                    groups_add_member($groupid, $user->id);
                    $usrreg = new stdClass();
                    $usrreg->user_id = $user->id;
                    $usrreg->agrp_id = $new_agrp->id;
                    $usrreg->timestamp = time();
                    $usrreg->modified_by = $USER->id;
                    $attr = array('user_id' => $user->id,
                                  'agrp_id' => $new_agrp->id);
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
                if ($grouping) {
                    add_to_log($this->grouptool->course,
                            'grouptool', 'create groups',
                            "view.php?id=".$this->grouptool->id."&tab=overview&groupingid=".
                            $grouping->id,
                            'create groups in grouping:'.$grouping->name.
                            ' namescheme:'.$data->namingscheme.' allocate-by:'.$data->allocateby.
                            ' numgroups:'.$numgrps.' user/grp:'.$userpergrp);
                } else {
                    add_to_log($this->grouptool->course,
                            'grouptool', 'create groups',
                            "view.php?id=".$this->grouptool->id."&tab=overview",
                            'create groups namescheme:'.$data->namingscheme.
                            ' allocate-by:'.$data->allocateby.' numgroups:'.$numgrps.
                            ' user/grp:'.$userpergrp);
                }
            }
        }
    }

    /**
     * Create moodle-groups and also create non-active entries for the created groups
     * for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param object $data data from administration-form with all settings for group creation
     * @param bool $only_preview optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_fromto_groups($data, $only_preview = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        $groups = array();

        // Every member is there, so we can parse the name!
        for ($i=clean_param($data->from, PARAM_INT); $i<=clean_param($data->to, PARAM_INT); $i++) {
            $groups[] = $this->groups_parse_name(trim($data->namingscheme), $i-1, null, clean_param($data->digits, PARAM_INT));
        }
        if ($only_preview) {
            $error = false;
            $table = new html_table();
            $table->head  = array(get_string('groupscount', 'group',
                                  (clean_param($data->to, PARAM_INT)-clean_param($data->from, PARAM_INT))));
            $table->size  = array('100%');
            $table->align = array('left');
            $table->width = '40%';

            $table->data  = array();
            $createdgroups = array();
            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group) || in_array($group, $createdgroups)) {
                    $error = true;
                    if(in_array($group, $createdgroups)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group).'</span>';
                    }
                } else {
                    $line[] = $group;
                    $createdgroups[] = $group;
                }

                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = array();
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

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (groups_get_group_by_name($this->course->id, $group)) {
                    $error = get_string('groupnameexists', 'group', $group);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group;
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $new_agrp = new stdClass();
                $new_agrp->group_id = $groupid;
                $new_agrp->grouptool_id = $this->grouptool->id;
                $new_agrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $new_agrp->active = 1;
                } else {
                    $new_agrp->active = 0;
                }
                $attr = array('grouptool_id' => $this->grouptool->id,
                              'group_id'     => $groupid);
                if (!$DB->record_exists('grouptool_agrps', $attr)) {
                    $new_agrp->id = $DB->insert_record('grouptool_agrps', $new_agrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $new_agrp->id = $DB->get_field('grouptool_agrps', 'id', $attr);
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id'=>$new_agrp->id));
                    }
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
            } else {
                $numgrps = clean_param($data->to, PARAM_INT) - clean_param($data->from, PARAM_INT) + 1;
                if ($grouping) {
                    add_to_log($this->grouptool->course,
                            'grouptool', 'create groups',
                            "view.php?id=".$this->grouptool->id."&tab=overview&groupingid=".
                            $grouping->id,
                            'create groups in grouping:'.$grouping->name.
                            ' namescheme:'.$data->namingscheme.' allocate-by:'.$data->allocateby.
                            ' numgroups:'.$numgrps.' from:'.clean_param($data->from, PARAM_INT).' to:'.clean_param($data->to, PARAM_INT));
                } else {
                    add_to_log($this->grouptool->course,
                            'grouptool', 'create groups',
                            "view.php?id=".$this->grouptool->id."&tab=overview",
                            'create groups namescheme:'.$data->namingscheme.
                            ' allocate-by:'.$data->allocateby.
                            ' numgroups:'.$numgrps.' from:'.clean_param($data->from, PARAM_INT).' to:'.clean_param($data->to, PARAM_INT));
                }
            }
        }
    }

    /**
     * Create a moodle group for each of the users in $users
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $USER
     * @param array $users array of users-objects for which to create the groups
     * @param array $name_scheme scheme determining how to name the created groups
     * @param int $grouping -1 => create new grouping,
     *                       0 => no grouping,
     *                      >0 => assign groups to grouping with that id
     * @param string $groupingname optional name for created grouping
     * @param bool $only_preview optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_one_person_groups($users, $name_scheme = "[idnumber]", $grouping = 0,
                                              $groupingname = null, $only_preview = false) {
        global $DB, $PAGE, $USER;

        require_capability('mod/grouptool:create_groups', $this->context);

        // Allocate members from the selected role to groups!
        $usercnt = count($users);

        // Prepare group data!
        $groups = array();
        $i=0;
        $digits = ceil(log10(count($users)));
        foreach ($users as $user) {
            $groups[$i] = array();
            $groups[$i]['name']   = $this->groups_parse_name(trim($name_scheme), $i, $user,
                                                             $digits);
            $groups[$i]['member'] = $user;
            $i++;
        }

        if ($only_preview) {
            $error = false;
            $table = new html_table();
            $table->head  = array(get_string('groupscount', 'group', $usercnt),
                                  get_string('groupmembers', 'group'));
            $table->size  = array('30%', '70%');
            $table->align = array('left', 'left');
            $table->width = '90%';

            $table->data  = array();
            $groupnames = array();
            foreach ($groups as $group) {
                $line = array();
                if (groups_get_group_by_name($this->course->id, $group['name'])
                     || in_array($group['name'], $groupnames)) {
                    $error = true;
                    if(in_array($group['name'], $groupnames)) {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('nameschemenotunique', 'grouptool', $group['name']).'</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">'.
                                  get_string('groupnameexists', 'group', $group['name']).'</span>';
                    }
                } else {
                    $groupnames[] = $group['name'];
                    $line[] = $group['name'];
                }
                $line[] = fullname($group['member'], true);

                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));

        } else {
            $newgrouping = null;
            $createdgrouping = null;
            $createdgroups = array();
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

            // Save the groups data!
            foreach ($groups as $key => $group) {
                if (groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    break;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name     = $group['name'];
                $groupid = groups_create_group($newgroup);
                // Insert into agrp-table!
                $new_agrp = new stdClass();
                $new_agrp->group_id = $groupid;
                $new_agrp->grouptool_id = $this->grouptool->id;
                $new_agrp->sort_order = 999999;
                if ($this->grouptool->allow_reg == true) {
                    $new_agrp->active = 1;
                } else {
                    $new_agrp->active = 0;
                }
                if (!$DB->record_exists('grouptool_agrps',
                                        array('grouptool_id' => $this->grouptool->id,
                                              'group_id'     => $groupid))) {
                    $new_agrp->id = $DB->insert_record('grouptool_agrps', $new_agrp, true);
                } else {
                    /* This is also the case if eventhandlers work properly
                     * because group gets allready created in eventhandler
                     */
                    $new_agrp->id = $DB->get_field('grouptool_agrps', 'id',
                                                   array('grouptool_id' => $this->grouptool->id,
                                                         'group_id'     => $groupid));
                    if ($this->grouptool->allow_reg == true) {
                        $DB->set_field('grouptool_agrps', 'active', 1, array('id'=>$new_agrp->id));
                    }
                }
                $createdgroups[] = $groupid;
                groups_add_member($groupid, $group['member']->id);
                $usrreg = new stdClass();
                $usrreg->user_id = $group['member']->id;
                $usrreg->agrp_id = $new_agrp->id;
                $usrreg->timestamp = time();
                $usrreg->modified_by = $USER->id;
                $attr = array('user_id' => $group['member']->id,
                              'agrp_id' => $new_agrp->id);
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
            } else {
                if ($grouping) {
                    add_to_log($this->grouptool->course,
                              'grouptool', 'create groups',
                              "view.php?id=".$this->grouptool->id."&tab=overview&groupingid=".
                               $grouping->id,
                              'create 1-person-groups in grouping:'.$grouping->name.
                              ' namescheme:'.$name_scheme, $this->cm->id);
                } else {
                    add_to_log($this->grouptool->course,
                              'grouptool', 'create groups',
                              "view.php?id=".$this->grouptool->id."&tab=overview",
                              'create 1-person-groups namescheme:'.$name_scheme,
                              $this->cm->id);
                }
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
     * @global object $SESSION
     * @global object $PAGE
     * @global object $OUTPUT
     * @param int $courseid optional id of course to create for
     * @param bool $preview_only optional only show preview of created groups
     * @return array ( 0 => error, 1 => message )
     */
    private function create_group_groupings($courseid = null, $preview_only = false) {
        global $SESSION, $PAGE, $OUTPUT;

        require_capability('mod/grouptool:create_groupings', $this->context);

        // Create groupings!
        $created = array();
        $error = false;
        $return = "";

        $table = new html_table();
        $table->attributes['class'] = 'centeredblock';
        $table->head = array(new html_table_cell(get_string('grouping', 'group')),
                                   new html_table_cell(get_string('info').'/'.
                                                       get_string('groups')));

        // Get all course-groups!
        if ($courseid == null) {
            if (isset($this->course->id)) {
                $courseid = $this->course->id;
            } else {
                print_error('coursemisconf');
            }
        }
        $groups = groups_get_all_groups($courseid);
        $ids = array();
        foreach ($groups as $group) {
            $row = array(new html_table_cell($group->name));
            $active = $SESSION->grouptool->view_administration->grouplist[$group->id]['active'];
            if (empty($SESSION->grouptool->view_administration->use_all)
                     && !$active) {
                continue;
            }
            $groupid = $group->id;
            if (groups_get_grouping_by_name($courseid, $group->name)) {
                // Creation of grouping failed!
                if ($preview_only) {
                    $text = get_string('grouping_exists_error_prev', 'grouptool');
                } else {
                    $text = get_string('grouping_exists_error', 'grouptool');
                }
                $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                $row[] = $cell;
                $error = true;
            } else {
                $ids[] = $group->id;
                $groupingid = groups_create_grouping($group);
                if ($groupingid) {
                    if (!groups_assign_grouping($groupingid, $groupid)) {
                        if ($preview_only) {
                            $text = get_string('group_assign_error_prev', 'grouptool');
                        } else {
                            $text = get_string('group_assign_error', 'grouptool');
                        }
                        $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                        $row[] = $cell;
                        $error = true;
                    } else {
                        if ($preview_only) {
                            $content = $group->name;
                        } else {
                            $content = $OUTPUT->notification(get_string('grouping_creation_success',
                                                                        'grouptool', $group->name),
                                                             'notifysuccess');
                        }
                        $cell = new html_table_cell($content);
                        $row[] = $cell;
                        $created[] = $groupingid;
                    }
                } else {
                    if ($preview_only) {
                        $text = get_string('grouping_creation_error_prev', 'grouptool');
                    } else {
                        $text = get_string('grouping_creation_error', 'grouptool');
                    }
                    $cell = new html_table_cell($OUTPUT->notification($text, 'notifyproblem'));
                    $row[] = $cell;
                    $error = true;
                }
            }
            $table->data[] = new html_table_row($row);
            $return = html_writer::table($table);
        }
        if ($preview_only || ($error && !$preview_only)) { // Undo everything!
            foreach ($created as $grouping_id) {
                $groupings_groups = groups_get_all_groups($courseid, 0, $grouping_id);
                foreach ($groupings_groups as $group) {
                    groups_unassign_grouping($grouping_id, $group->id);
                }
                groups_delete_grouping($grouping_id);
            }
        } else if (!$preview_only) {
            add_to_log($this->grouptool->course,
                       'grouptool', 'create groupings',
                       "view.php?id=".$this->grouptool->id."&tab=overview",
                       'create groupings for groups:'.implode("|", $ids));
        }
        return array(0 => $error, 1 => $return);
    }

    /**
     * Outputs the content of the administration tab and manages actions taken in this tab
     *
     * @global object $SESSION
     * @global object $OUTPUT
     * @global object $PAGE
     */
    public function view_administration() {
        global $SESSION, $OUTPUT, $PAGE;

        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = array();
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
                switch ($data->mode) {
                    case GROUPTOOL_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = $data->amount;
                        $userpergrp = floor($usercnt/$numgrps);
                        $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = ceil($usercnt/$data->amount);
                        $userpergrp = $data->amount;
                        if (!empty($data->nosmallgroups) and $usercnt % $data->amount != 0) {
                            /*
                             *  If there would be one group with a small number of member
                             *  reduce the number of groups
                             */
                            $missing = $userpergrp * $numgrps - $usercnt;
                            if ($missing > $userpergrp * (1-GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                                // Spread the users from the last small group!
                                $numgrps--;
                                $userpergrp = floor($usercnt/$numgrps);
                            }
                        }
                        $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case GROUPTOOL_1_PERSON_GROUPS:
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid);
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $prev) = $this->create_one_person_groups($users,
                                                                              $data->namingscheme,
                                                                              $data->grouping,
                                                                              $data->groupingname);
                        $preview = $prev;
                        break;
                    case GROUPTOOL_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $preview) = $this->create_fromto_groups($data);
                        break;
                }
            }
            if (isset($SESSION->grouptool->view_administration->createGroupings)) {
                require_capability('mod/grouptool:create_groupings', $this->context);
                list($error, $preview) = $this->create_group_groupings();
                $preview = html_writer::tag('div', $preview, array('class'=>'centered'));
                echo $OUTPUT->box($preview, 'generalbox');
            }
            unset($SESSION->grouptool->view_administration);
        }

        // Create the form-object!
        $mform = new view_admin_form(null,
                                     array('id'           => $id,
                                           'roles'        => $rolenames,
                                           'show_grpsize' => ($this->grouptool->use_size
                                                             && $this->grouptool->use_individual)));

        if ($fromform = $mform->get_data()) {
            if (isset($fromform->createGroups)) {
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
                switch ($data->mode) {
                    case GROUPTOOL_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = clean_param($data->amount, PARAM_INT);
                        $userpergrp = floor($usercnt/$numgrps);
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                      $numgrps, true);
                        break;
                    case GROUPTOOL_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps    = ceil($usercnt/$data->amount);
                        $userpergrp = clean_param($data->amount, PARAM_INT);
                        if (!empty($data->nosmallgroups) and $usercnt % clean_param($data->amount, PARAM_INT) != 0) {
                            /*
                             *  If there would be one group with a small number of member
                             *  reduce the number of groups
                             */
                            $missing = $userpergrp * $numgrps - $usercnt;
                            if ($missing > $userpergrp * (1-GROUPTOOL_AUTOGROUP_MIN_RATIO)) {
                                // Spread the users from the last small group!
                                $numgrps--;
                                $userpergrp = floor($usercnt/$numgrps);
                            }
                        }
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp,
                                                                      $numgrps, true);
                        break;
                    case GROUPTOOL_1_PERSON_GROUPS:
                        $users = groups_get_potential_members($this->course->id, $data->roleid,
                                                              $data->cohortid);
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $prev) = $this->create_one_person_groups($users,
                                                                              $data->namingscheme,
                                                                              $data->grouping,
                                                                              $data->groupingname,
                                                                              true);
                        $preview = $prev;
                        break;
                    case GROUPTOOL_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $preview) = $this->create_fromto_groups($data, true);
                        break;
                }
                $preview = html_writer::tag('div', $preview, array('class'=>'centered'));
                if ($error) {
                    $text = get_string('create_groups_confirm_problem', 'grouptool');
                    $url = new moodle_url("view.php?id=$id&tab=administration");
                    $back = new single_button($url, get_string('back'), 'post');
                    $confirmboxcontent =  $this->confirm($text, $back);
                } else {
                    $continue = "view.php?id=$id&tab=administration&confirm=true";
                    $cancel = "view.php?id=$id&tab=administration";
                    $text = get_string('create_groups_confirm', 'grouptool');
                    $confirmboxcontent =  $this->confirm($text, $continue, $cancel);
                }
                echo $OUTPUT->heading(get_string('preview'), 2, 'centered').
                     $OUTPUT->box($preview, 'generalbox').
                     $confirmboxcontent;

            } else if (isset($fromform->createGroupings)) {
                require_capability('mod/grouptool:create_groupings', $this->context);
                // Save submitted data in session and show confirmation dialog!
                if (!isset($SESSION->grouptool)) {
                    $SESSION->grouptool = new stdClass();
                }
                $SESSION->grouptool->view_administration = $fromform;
                list($error, $preview) = $this->create_group_groupings(null, true);
                $preview = html_writer::tag('div', $preview, array('class'=>'centered'));
                $continue = "view.php?id=$id&tab=administration&confirm=1";
                $cancel = "view.php?id=$id&tab=administration";
                if ($error) {
                    $confirmtext = get_string('create_groupings_confirm_problem', 'grouptool');
                    $confirmboxcontent = $this->confirm($confirmtext, $cancel);
                } else {
                    $confirmtext = get_string('create_groupings_confirm', 'grouptool');
                    $confirmboxcontent =  $this->confirm($confirmtext, $continue, $cancel);
                }
                echo $OUTPUT->heading(get_string('preview'), 2, 'centered').
                     $OUTPUT->box($preview, 'generalbox').
                     $confirmboxcontent;

            } else if (isset($fromform->updateActiveGroups)) {
                if (has_capability('mod/grouptool:create_groupings', $this->context)
                        || has_capability('mod/grouptool:create_groups', $this->context)) {
                    // Update active-groups data!
                    if ($this->update_active_groups($fromform->grouplist, $this->cm->instance)) {
                        echo $OUTPUT->notification(get_string('update_grouplist_success',
                                                   'grouptool'), 'notifysuccess');
                    } else {
                        echo $OUTPUT->notification(get_string('update_grouplist_failure',
                                                  'grouptool'), 'notifyproblem');
                    }
                    $mform->display();
                }
            } else {
                $mform->display();
            }

        } else {
            $mform->display();
        }
    }

    /**
     * returns a checkboxcontroller (like in moodleform - just without form)
     *
     * @global object $CFG
     * @param int $groupid ID of checkboxgroup to control (--> checkbox has class "checkboxgroupX")
     * @param string $text optional text to output before submitlink
     * @param array $attributes optional array of attributes for submitlink
     * @param bool $originalvalue optional state of checkboxes @ first load
     * @return string HTML Fragment containing html table with necessary data/elements or message
     */
    public function add_checkbox_controller($groupid, $text = null, $attributes = null,
                                            $originalvalue = 0) {
        global $CFG;

        // Set the default text if none was specified!
        if (empty($text)) {
            $text = get_string('selectallornone', 'form');
        }

        $select_value = optional_param('checkbox_controller'. $groupid, null, PARAM_INT);

        if ($select_value == 0 || is_null($select_value)) {
            $new_select_value = 1;
        } else {
            $new_select_value = 0;
        }

        $attr = array('type'  => 'hidden',
                      'name'  => 'checkbox_controller'.$groupid,
                      'value' => $new_select_value);
        $hiddenstate = html_writer::empty_tag('input', $attr);

        $checkbox_controller_name = 'nosubmit_checkbox_controller' . $groupid;

        // Prepare Javascript for submit element!
        $js = "\n//<![CDATA[\n";
        if (!defined('HTML_QUICKFORM_CHECKBOXCONTROLLER_EXISTS')) {
            $js .= <<<EOS
function html_quickform_toggle_checkboxes(group) {
    var checkboxes = document.getElementsByClassName('checkboxgroup' + group);
    var newvalue = false;
    var global = eval('html_quickform_checkboxgroup' + group + ';');
    if (global == 1) {
        eval('html_quickform_checkboxgroup' + group + ' = 0;');
        newvalue = '';
    } else {
        eval('html_quickform_checkboxgroup' + group + ' = 1;');
        newvalue = 'checked';
    }

    for (i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = newvalue;
    }
}
EOS;
            define('HTML_QUICKFORM_CHECKBOXCONTROLLER_EXISTS', true);
        }
        $js .= "\nvar html_quickform_checkboxgroup$groupid=$originalvalue;\n";

        $js .= "//]]>\n";

        require_once("$CFG->libdir/form/submitlink.php");
        $submitlink = new MoodleQuickForm_submitlink($checkbox_controller_name, $text, $attributes);
        $submitlink->_js = $js;
        $submitlink->_onclick = "html_quickform_toggle_checkboxes($groupid); return false;";
        return $hiddenstate."<div>".$submitlink->toHTML()."</div>";
    }

    /**
     * returns table used in group-grading form
     *
     * @global object $OUTPUT
     * @global object $USER
     * @global object $PAGE
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroups_only limit source-grades to those given by current user
     * @param bool $incomplete_only show only groups which have not-graded members
     * @param int $filter GROUPTOOL_FILTER_ALL => all groups
     *                    GROUPTOOL_FILTER_NONCONFLICTING => groups with exactly 1 graded member
     *                    >0 => id of single group
     * @param array $selected array with ids of groups/users to copy grades to as keys
     *                        (depends on filter)
     * @param array $missingsource optional array with ids of entries for whom no source has been
     *                                      selected (just to display a clue to select a source)
     * @return string HTML Fragment containing checkbox-controller and dependencies
     */
    private function get_grading_table($activity, $mygroups_only, $incomplete_only, $filter,
                                       $selected, $missingsource = array()) {
        global $OUTPUT, $USER, $PAGE;

        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroups_only) {
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

        $title = html_writer::tag('div', get_string('groupselection', 'grouptool').
                                         $OUTPUT->help_icon('groupselection', 'grouptool'),
                                  array('class' => 'groupselectiontitle'));

        if ($activity == 0) {
            return $title.
                   $OUTPUT->box($OUTPUT->notification(get_string('chooseactivity', 'grouptool'),
                                                      'notifyproblem'), 'generalbox centered');
        }

        // General table settings!
        $table->attributes['class'] .= ' coloredrows grading_gradingtable';
        $tablepostfix = "";
        // Determin what mode we have to interpret the selected items the right way!
        if ($filter == GROUPTOOL_FILTER_ALL || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
            // Multiple groups?
            $tablecolumns = array('select',
                    'name',
                    'gradeinfo');
            $button = html_writer::tag('button', get_string('copy', 'grouptool'),
                    array('name'=>'copygrades',
                            'type'=>'submit',
                            'value'=>'true'));
            $buttontext = get_string('copy_refgrades_feedback', 'grouptool');
            $tableheaders = array('',
                    get_string('name'),
                    get_string('reference_grade_feedback', 'grouptool'));

            $groups = groups_get_all_groups($this->course->id, 0, $grouping);
            $cm_to_use = get_coursemodule_from_id('', $activity, $this->course->id);

            foreach ($groups as $group) {
                $error = "";
                $cells = array();
                $groupmembers = groups_get_members($group->id);
                // Get grading info for all groupmembers!
                $grading_info = grade_get_grades($this->course->id, 'mod', $cm_to_use->modname,
                                                 $cm_to_use->instance, array_keys($groupmembers));
                $gradeinfo = array();
                if (in_array($group->id, $missingsource)) {
                    $error = ' error';
                    $gradeinfo[] = html_writer::tag('div', get_string('missing_source_selection',
                                                                      'grouptool'));
                }

                $user_with_grades = array();
                foreach ($groupmembers as $key => $groupmember) {
                    if (!empty($grading_info->items[0]->grades[$groupmember->id]->dategraded)
                        && (!$mygroups_only
                            || $grading_info->items[0]->grades[$groupmember->id]->usermodified == $USER->id)) {
                        $user_with_grades[] = $key;
                    }
                }
                if ((count($user_with_grades) != 1)
                        && ($filter == GROUPTOOL_FILTER_NONCONFLICTING)) {
                    /*
                     * skip groups with more than 1 grade and groups without grade
                     * if only nonconflicting should be reviewed
                     */
                    continue;
                }
                if ((count($user_with_grades) == count($groupmembers)) && ($incomplete_only == 1)) {
                    // Skip groups fully graded if it's wished!
                    continue;
                }
                foreach ($user_with_grades as $key) {
                    $final_grade = $grading_info->items[0]->grades[$key];
                    if (!empty($final_grade->dategraded)) {
                        $grademax = $grading_info->items[0]->grademax;
                        $final_grade->formatted_grade = round($final_grade->grade, 2) .' / ' .
                                                        round($grademax, 2);
                        $radio_attr = array(
                                'name' => 'source['.$group->id.']',
                                'value' => $groupmembers[$key]->id,
                                'type' => 'radio');
                        if (isset($source[$group->id])
                                && $source[$group->id] == $groupmembers[$key]->id) {
                            $radio_attr['selected'] = 'selected';
                        }
                        if (count($user_with_grades) == 1) {
                            $radio_attr['type'] = 'hidden';
                        }
                        $gradeinfocont = ((count($user_with_grades) >= 1) ?
                                            html_writer::empty_tag('input', $radio_attr) : "").
                                            fullname($groupmembers[$key])." (".
                                            $final_grade->formatted_grade;
                        if (strip_tags($final_grade->str_feedback) != "") {
                            $gradeinfocont .= " ".
                                              shorten_text(strip_tags($final_grade->str_feedback),
                                                           15);
                        }
                        $gradeinfocont .= ")";
                        $gradeinfo[] = html_writer::tag('div', $gradeinfocont,
                                                        array('class'=>'gradinginfo'.
                                                                       $groupmembers[$key]->id));
                    }
                }
                $select_attr = array(
                        'type' => 'checkbox',
                        'name' => 'selected[]',
                        'value' => $group->id,
                        'class' => 'checkboxgroup1');
                if ((count($groupmembers) <= 1) || count($user_with_grades) == 0) {
                    $select_attr['disabled'] = 'disabled';
                    unset($select_attr['checked']);
                } else if (isset($selected[$group->id]) && $selected[$group->id] == 1) {
                    $select_attr['checked'] = "checked";
                }
                $select = new html_table_cell(html_writer::empty_tag('input', $select_attr));
                $name = new html_table_cell($group->name);
                if (empty($gradeinfo)) {
                    $gradeinfo = new html_table_cell(get_string('no_grades_present', 'grouptool'));
                } else {
                    $gradeinfo = new html_table_cell(implode("\n", $gradeinfo));
                }

                $row = new html_table_row(array($select, $name, $gradeinfo));
                $row->attributes['class'] = isset($row->attributes['class']) ?
                                            $row->attributes['class'].$error :
                                            $row->attributes['class'];
                $data[] = $row;
            }
            $tablepostfix = html_writer::tag('div', $buttontext, array('class'=>'center centered'));
            $tablepostfix .= html_writer::tag('div', $button, array('class'=>'centered center'));

        } else if ($filter > 0) {    // Single group?
            $tablecolumns = array('select',
                    'fullname',
                    'idnumber',
                    'grade',
                    'feedback',
                    'copybutton');
            $tableheaders = array(get_string('target', 'grouptool'),
                    get_string('fullname'),
                    get_string('idnumber'),
                    get_string('grade'),
                    get_string('feedback'),
                    get_string('source', 'grouptool'));

            $groupmembers = groups_get_members($filter);
            // Get grading info for all groupmembers!
            $cm_to_use = get_coursemodule_from_id('', $activity, $this->course->id);
            $grading_info = grade_get_grades($this->course->id, 'mod', $cm_to_use->modname,
                                             $cm_to_use->instance, array_keys($groupmembers));
            if (isset($grading_info->items[0])) {
                foreach ($groupmembers as $groupmember) {
                    $row = array();
                    $final_grade = $grading_info->items[0]->grades[$groupmember->id];
                    $grademax = $grading_info->items[0]->grademax;
                    $final_grade->formatted_grade = round($final_grade->grade, 2) .' / ' .
                                                    round($grademax, 2);
                    $checked = (isset($selected[$groupmember->id])
                                && ($selected[$groupmember->id] == 1)) ? true : false;
                    $row[] = html_writer::checkbox('selected[]', $groupmember->id, $checked, '',
                                                   array('class'=>'checkbox checkboxgroup1'));
                    $row[] = html_writer::tag('div',
                                              fullname($groupmember,
                                                       has_capability('moodle/site:viewfullnames',
                                                        $this->context)),
                                              array('class'=>'fullname'.$groupmember->id));
                    $row[] = html_writer::tag('div', $groupmember->idnumber,
                                              array('class'=>'idnumber'.$groupmember->id));
                    $row[] = html_writer::tag('div', $final_grade->formatted_grade,
                                              array('class'=>'grade'.$groupmember->id));
                    $row[] = html_writer::tag('div',
                                              shorten_text(strip_tags($final_grade->str_feedback),
                                                           15),
                                              array('class'=>'feedback'.$groupmember->id));
                    if ($mygroups_only && ($final_grade->usermodified != $USER->id)) {
                        $row[] = html_writer::tag('div', get_string('not_graded_by_me',
                                                                    'grouptool'));
                    } else {
                        if (GROUPTOOL_IE7_IS_DEAD) {
                            $row[] = html_writer::tag('button',
                                                      get_string('copygrade', 'grouptool'),
                                                      array('type'  => 'submit',
                                                            'name'  => 'source',
                                                            'value' => $groupmember->id));
                        } else {
                            $attr = array('type'  => 'submit',
                                          'name'  => 'source['.$groupmember->id.']',
                                          'value' => get_string('copygrade', 'grouptool'));
                            $row[] = html_writer::empty_tag('input', $attr);
                        }
                    }
                    $data[] = $row;
                }
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_grades_present',
                                                                     'grouptool'),
                                                          'notifyproblem'),
                                    'generalbox centered');
            }
        } else {
            print_error('uknown filter-value');
        }

        if (empty($data)) {
            if ($filter == GROUPTOOL_FILTER_ALL) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_data_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'generalbox centered');
            } else if ($filter == GROUPTOOL_FILTER_NONCONFLICTING) {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_conflictfree_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'centered').
                $this->get_grading_table($activity, $mygroups_only, $incomplete_only,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            } else {
                return $OUTPUT->box($OUTPUT->notification(get_string('no_groupmembers_to_display',
                                                                     'grouptool'), 'notifyproblem'),
                                    'centered').
                $this->get_grading_table($activity, $mygroups_only, $incomplete_only,
                                         GROUPTOOL_FILTER_ALL, $selected, $missingsource);
            }
        }

        $table->colclasses = $tablecolumns;
        // Instead of the strings an array of html_table_cells can be set as head!
        $table->head = $tableheaders;
        // Instead of the strings an array of html_table_cells can be used for the rows!
        $table->data = $data;

        $return = $title.
                  html_writer::tag('div', $this->add_checkbox_controller(1, null, null, 0),
                                   array('class' => 'checkboxcontroller')).
                  html_writer::table($table).$tablepostfix;
        $return = html_writer::tag('div',
                                   html_writer::tag('div', $return,
                                                    array('class'=>'fieldsetsimulation')),
                                   array('class'=>'clearfix'));
        return $return;
    }

    /**
     * copies the grades from the source(s) to the target(s) for the selected activity
     *
     * @global object $DB
     * @global object $USER
     * @global object $PAGE
     * @param int $activity ID of activity to get/set grades from/for
     * @param bool $mygroups_only limit source-grades to those given by current user
     * @param array $selected array with ids of groups/users to copy grades to as keys
     *                        (depends on filter)
     * @param array $source optional array with ids of entries for whom no source has been
     *                                      selected (just to display a clue to select a source)
     * @param bool $overwrite optional overwrite existing grades (std: false)
     * @param bool $preview_only optional just return preview data
     * @return array ($error, $message)
     */
    private function copy_grades($activity, $mygroups_only, $selected, $source, $overwrite = false,
                                 $preview_only = false) {
        global $DB, $USER, $PAGE;
        $error = false;
        // If he want's to grade all he needs the corresponding capability!
        if (!$mygroups_only) {
            require_capability('mod/grouptool:grade', $this->context);
        } else if (!has_capability('mod/grouptool:grade', $this->context)) {
            /*
             * if he want's to grade his own (=submissions where he graded at least 1 group member)
             * he needs either capability to grade all or to grade his own at least
             */
            require_capability('mod/grouptool:grade_own_submission', $this->context);
        }

        $cm_to_use = get_coursemodule_from_id('', $activity, $this->course->id);
        if (!$cm_to_use) {
            return array(true, get_string('couremodule_misconfigured'));
        }
        if ($preview_only) {
            $previewtable = new html_table();
            $previewtable->attributes['class'] = 'coloredrows grading_previewtable';
        } else {
            $info = "";
        }

        $grade_item = grade_item::fetch(array('itemtype'    => 'mod',
                                              'itemmodule'  => $cm_to_use->modname,
                                              'iteminstance'=> $cm_to_use->instance));

        if (is_array($source)) { // Then we are in multigroup mode (filter = 0 || -1)!
            $sourceusers = $DB->get_records_list('user', 'id', $source);
            $groups = groups_get_all_groups($this->course->id);
            if (!isset($previewtable)) {
                $previewtable = new stdClass();
            }
            $previewtable->head = array(get_string('groups')." (".count($selected).")",
                                        get_string('fullname'),
                                        get_string('grade'),
                                        get_string('feedback'));
            foreach ($selected as $group) {
                if ($preview_only) {
                    $group_rows = array();
                } else {
                    $groupinfo = "";
                }
                $source_grade = grade_grade::fetch_users_grades($grade_item, $source[$group],
                                                                false);
                $source_grade = reset($source_grade);
                $source_grade->load_optional_fields();
                $orig_teacher = $DB->get_record('user', array('id'=>$source_grade->usermodified));
                $formatted_grade = round($source_grade->finalgrade, 2) .' / ' .
                                   round($grade_item->grademax, 2);

                $groupmembers = groups_get_members($group);
                $target_grades = grade_grade::fetch_users_grades($grade_item,
                                                                 array_keys($groupmembers), true);
                $properties_to_copy = array('rawgrade', 'finalgrade', 'feedback', 'feedbackformat');

                foreach ($target_grades as $current_grade) {

                    if ($current_grade->id == $source_grade->id) {
                        continue;
                    }
                    if (!$overwrite && ($current_grade->finalgrade != null)) {
                        if ($preview_only) {
                            $row_cells = array();
                            if (empty($group_rows)) {
                                $row_cells[] = new html_table_cell($groups[$group]->name."\n".
                                        html_writer::empty_tag('br').
                                        "(".(count($groupmembers)-1).")");
                            }
                            $fullname = fullname($groupmembers[$current_grade->userid]);
                            $row_cells[] = new html_table_cell($fullname);
                            $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                            $cell->colspan = 2;
                            $row_cells[] = $cell;
                            $row = new html_table_row();
                            $row->cells = $row_cells;
                            if (empty($group_rows)) {
                                $row->attributes['class'] .= ' firstgrouprow';
                            }
                            $group_rows[] = $row;
                        }
                        continue;
                    }
                    $current_grade->load_optional_fields();
                    foreach ($properties_to_copy as $property) {
                        $current_grade->$property = $source_grade->$property;
                    }
                    $details = array('student'  => fullname($sourceusers[$source[$group]]),
                                     'teacher'  => fullname($orig_teacher),
                                     'date'     => userdate($source_grade->get_dategraded(),
                                                            get_string('strftimedatetimeshort')),
                                     'feedback' => $source_grade->feedback);
                    $current_grade->feedback = format_text(get_string('copied_grade_feedback',
                                                                      'grouptool',
                                                                      $details),
                                                           $current_grade->feedbackformat);
                    $current_grade->usermodified = $USER->id;
                    if ($preview_only) {
                        $row_cells = array();
                        if (empty($group_rows)) {
                            $row_cells[] = new html_table_cell($groups[$group]->name."\n".
                                    html_writer::empty_tag('br').
                                    "(".count($groupmembers).")");
                        }
                        $fullname = fullname($groupmembers[$current_grade->userid]);
                        $row_cells[] = new html_table_cell($fullname);
                        $row_cells[] = new html_table_cell($formatted_grade);
                        $row_cells[] = new html_table_cell($current_grade->feedback);
                        $row = new html_table_row();
                        $row->cells = $row_cells;
                        if (empty($group_rows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $group_rows[] = $row;
                    } else {
                        if (function_exists ('grouptool_copy_'.$cm_to_use->modname.'_grades')) {
                            $copyfunction = 'grouptool_copy_'.$cm_to_use->modname.'_grades';
                            $copyfunction($cm_to_use->instance, $source_grade->userid, $current_grade->userid);
                        }
                        if ($current_grade->id) {
                            $noerror = $current_grade->update();
                        } else {
                            $noerror = $current_grade->insert();
                        }
                        $current_grade->set_overridden(true, false);
                        $fullname = fullname($groupmembers[$current_grade->userid]);
                        if ($noerror) {
                            $groupinfo .= html_writer::tag('span',
                                                           '✓&nbsp;'.$fullname.
                                                           " (".$formatted_grade.")",
                                                           array('class'=>'notifysuccess'));
                        } else {
                            $error = true;
                            $groupinfo .= html_writer::tag('span',
                                                           '✗&nbsp;'.$fullname.
                                                           " (".$formatted_grade.")",
                                                           array('class'=>'notifyproblem'));
                        }
                    }
                }
                if ($preview_only) {
                    $group_rows[0]->cells[0]->rowspan = count($group_rows);
                    if (!is_array($previewtable->data)) {
                        $previewtable->data = array();
                    }
                    $previewtable->data = array_merge($previewtable->data, $group_rows);
                } else {
                    $grpinfo = "";
                    $grpinfo .= html_writer::tag('div', $groups[$group]->name." (".
                                                        count($groupmembers)."): ".$groupinfo);
                    $data = array('student' => fullname($sourceusers[$source[$group]]),
                                  'teacher' => fullname($orig_teacher),
                                  'date'    => userdate($source_grade->get_dategraded(),
                                                        get_string('strftimedatetimeshort')),
                                  'feedback' => $source_grade->feedback);
                    $temp = get_string('copied_grade_feedback', 'grouptool', $data);
                    $grpinfo .= html_writer::tag('div', $formatted_grade.html_writer::empty_tag('br').
                                                        format_text($temp,
                                                                    $source_grade->feedbackformat));
                    $info .= html_writer::tag('div', $grpinfo, array('class'=>'box1embottom'));
                    add_to_log($this->grouptool->course,
                              'grouptool', 'grade group',
                              "view.php?id=".$this->grouptool->id."&tab=grading&activity=".
                              $cm_to_use->instance."&groupid=".$group."&refresh_table=1",
                              'group-grade group='.$group);

                }
            }
        } else {
            $sourceuser = $DB->get_record('user', array('id' => $source));
            $targetusers = $DB->get_records_list('user', 'id', $selected);
            $source_grade = grade_grade::fetch_users_grades($grade_item, $source, false);
            $source_grade = reset($source_grade);
            $orig_teacher = $DB->get_record('user', array('id'=>$source_grade->usermodified));
            $formatted_grade = round($source_grade->finalgrade, 2) .' / ' .
                               round($grade_item->grademax, 2);
            $target_grades = grade_grade::fetch_users_grades($grade_item, $selected, true);
            $properties_to_copy = array('rawgrade', 'finalgrade', 'feedback', 'feedbackformat');
            if ($preview_only) {
                $group_rows = array();
                $count = in_array($source, $selected) ? count($selected)-1 : count($selected);
                $previewtable->head = array('', get_string('fullname')." (".$count.")",
                        get_string('grade'), get_string('feedback'));
                $previewtable->attributes['class'] = 'coloredrows grading_previewtable';
            } else {
                $info .=html_writer::start_tag('div');
                $nameinfo = "";
            }

            foreach ($target_grades as $current_grade) {
                if ($current_grade->id == $source_grade->id) {
                    continue;
                }
                if (!$overwrite && ($current_grade->rawgrade != null)) {
                    if ($preview_only) {
                        $row_cells = array();
                        if (empty($group_rows)) {
                            $row_cells[] = new html_table_cell(get_string('users'));
                        }
                        $fullname = fullname($targetusers[$current_grade->userid]);
                        $row_cells[] = new html_table_cell($fullname);
                        $cell = new html_table_cell(get_string('skipped', 'grouptool'));
                        $cell->colspan = 2;
                        $row_cells[] = $cell;
                        $row = new html_table_row();
                        $row->cells = $row_cells;
                        if (empty($group_rows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $group_rows[] = $row;
                    }
                    continue;
                }
                $current_grade->load_optional_fields();
                foreach ($properties_to_copy as $property) {
                    $current_grade->$property = $source_grade->$property;
                }

                $details = array('student' => fullname($sourceuser),
                                    'teacher' => fullname($orig_teacher),
                                    'date' => userdate($source_grade->get_dategraded(),
                                                       get_string('strftimedatetimeshort')),
                                    'feedback' => $source_grade->feedback);
                $current_grade->feedback = format_text(get_string('copied_grade_feedback',
                                                                  'grouptool',
                                                                  $details),
                                                       $current_grade->feedbackformat);
                $current_grade->usermodified   = $USER->id;
                if ($preview_only) {
                    $row_cells = array();
                    if (empty($group_rows)) {
                        $row_cells[] = new html_table_cell(get_string('users'));
                    }
                    $fullname = fullname($targetusers[$current_grade->userid]);
                    $row_cells[] = new html_table_cell($fullname);
                    $row_cells[] = new html_table_cell($formatted_grade);
                    $row_cells[] = new html_table_cell(format_text($current_grade->feedback,
                                                                   $current_grade->feedbackformat));
                    $row = new html_table_row();
                    $row->cells = $row_cells;
                    if (empty($group_rows)) {
                        $row->attributes['class'] .= ' firstgrouprow';
                    }
                    $group_rows[] = $row;
                } else {
                    if ($nameinfo != "") {
                        $nameinfo .= ", ";
                    }
                    if ($current_grade->id) {
                        $noerror = $current_grade->update();
                    } else {
                        $noerror = $current_grade->insert();
                    }
                    $current_grade->set_overridden(true, false);
                    $fullname = fullname($targetusers[$current_grade->userid]);
                    if (function_exists ('grouptool_copy_'.$cm_to_use->modname.'_grades')) {
                        $copyfunction = 'grouptool_copy_'.$cm_to_use->modname.'_grades';
                        $copyfunction($cm_to_use->instance, $source_grade->userid, $current_grade->userid);
                    }
                    if ($noerror) {
                        $nameinfo .= html_writer::tag('span',
                                                       '✓&nbsp;'.$fullname,
                                                       array('class'=>'notifysuccess'));
                    } else {
                        $error = true;
                        $nameinfo .= html_writer::tag('span',
                                                       '✗&nbsp;'.$fullname,
                                                       array('class'=>'notifyproblem'));
                    }
                }
            }
            if ($preview_only) {
                $group_rows[0]->cells[0]->rowspan = count($group_rows);
                $previewtable->data = $group_rows;
            } else {
                $info .= $nameinfo.html_writer::end_tag('div');
                $details = array('student' => fullname($sourceuser),
                                 'teacher' => fullname($orig_teacher),
                                 'date' => userdate($source_grade->get_dategraded(),
                                                    get_string('strftimedatetimeshort')),
                                 'feedback' => $source_grade->feedback);
                $info .= html_writer::tag('div', get_string('grade').": ".
                                        $formatted_grade.html_writer::empty_tag('br').
                                        format_text(get_string('copied_grade_feedback', 'grouptool',
                                                               $details),
                                                    $source_grade->feedbackformat),
                                                    array('class'=>'gradeinfo'));
            }
            if (!$preview_only) {
                add_to_log($this->grouptool->course,
                        'grouptool', 'grade group',
                        "view.php?id=".$this->grouptool->id."&tab=grading&activity=".
                        $cm_to_use->instance."&refresh_table=>1",
                        'group-grade single group users='.implode('|', $selected));
            }
        }
        if ($preview_only) {
            return array($error, html_writer::tag('div', html_writer::table($previewtable),
                                                  array('class'=>'centeredblock')));
        } else {
            return array($error, html_writer::tag('div', $info, array('class'=>'centeredblock')));
        }
    }

    /**
     * view grading-tab
     *
     * @global object $SESSION
     * @global object $PAGE
     * @global object $CFG
     * @global object $OUTPUT
     * @global object $USER
     */
    public function view_grading() {
        global $SESSION, $PAGE, $CFG, $OUTPUT, $USER, $DB;

        if (!has_capability('mod/grouptool:grade', $this->context)
                && !has_capability('mod/groputool:grade_own_groups', $this->context)) {
            print_error('nopermissions');
            return;
        }

        $refresh_table = optional_param('refresh_table', 0, PARAM_BOOL);
        $activity = optional_param('activity', null, PARAM_INT); // This is the coursemodule-ID.

        // Show only groups with grades given by current user!
        $mygroups_only = optional_param('mygroups_only', null, PARAM_BOOL);

        if (!has_capability('mod/grouptool:grade', $this->context)) {
            $mygroups_only = 1;
        }

        if ($mygroups_only != null) {
            set_user_preference('mygroups_only', $mygroups_only, $USER->id);
        }

        // Show only groups with missing grades (groups with at least 1 not-graded member)!
        $incomplete_only = optional_param('incomplete_only', 0, PARAM_BOOL);

        $overwrite = optional_param('overwrite', 0, PARAM_BOOL);

        // Here -1 = nonconflicting, 0 = all     or groupid for certain group!
        $filter = optional_param('filter', GROUPTOOL_FILTER_NONCONFLICTING, PARAM_INT);
        // Steps: 0 = show, 1 = confirm, 2 = action!
        $step = optional_param('step', 0, PARAM_INT);
        if ($refresh_table) { // If it was just a refresh, reset step!
            $step = 0;
        }

        $grouping = optional_param('grouping', 0, PARAM_INT);

        if ($filter > 0) {
            if ($step == 2) {
                $source = optional_param('source', null, PARAM_INT);
                // Serialized data @todo better PARAM_TYP?
                $selected = optional_param('selected', null, PARAM_RAW);
                if (!empty($selected)) {
                    $selected = unserialize($selected);
                }
            } else {
                if (GROUPTOOL_IE7_IS_DEAD) {
                    $source = optional_param('source', null, PARAM_INT);
                } else {
                    /*
                     * compatibility for MSIE 7 (<button></button>)
                     * we use a little trick here:
                     * instead of using value attribute (which gets not submitted in MSIE 7)
                     * we use the arrays key to submit the value (=user-id)
                     */
                    $source = optional_param_array("source", array(), PARAM_TEXT);
                    $source = array_keys($source);
                    $source = reset($source);
                }
                $selected = optional_param_array('selected', null, PARAM_INT);
                if (!empty($source) && !$refresh_table) {
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
                $source = optional_param_array('source', array(), PARAM_INT);
                $selected = optional_param_array('selected', array(), PARAM_INT);
                $copygroups = optional_param('copygrades', 0, PARAM_BOOL);
                if ($copygroups && !$refresh_table) {
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
            $refresh_table = false;
            $step = 0;
        }

        if (!empty($mygroups_only)) {
            $mygroups_only = get_user_preferences('mygroups_only', 1, $USER->id);
        }

        $missingsource = array();

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
                    list($error, $preview) = $this->copy_grades($activity, $mygroups_only,
                                                                $selected, $source, $overwrite,
                                                                true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id, array('tab'=>'grading',
                            'confirm'=>'true',
                            'sesskey'=>sesskey(),
                            'step'=>'2',
                            'activity'=>$activity,
                            'mygroups_only'=>$mygroups_only,
                            'overwrite'=>$overwrite,
                            'selected'=>serialize($selected),
                            'source'=>serialize($source)));
                    $cancel = new moodle_url("view.php?id=".$this->cm->id, array('tab'=>'grading',
                            'confirm'=>'false',
                            'sesskey'=>sesskey(),
                            'step'=>'2',
                            'activity'=>$activity,
                            'mygroups_only'=>$mygroups_only,
                            'overwrite'=>$overwrite,
                            'selected'=>serialize($selected),
                            'source'=>serialize($source)));
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm',
                                                       'grouptool'), $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm',
                                                       'grouptool'), $continue, $cancel);
                    }
                } else {
                    echo $OUTPUT->box($OUTPUT->notification(get_string('no_target_selected',
                                                                       'grouptool'),
                                                            'notifyproblem'), 'generalbox');
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
                    list($error, $preview) = $this->copy_grades($activity, $mygroups_only,
                                                                $selected, $source, $overwrite,
                                                                true);
                    $continue = new moodle_url("view.php?id=".$this->cm->id,
                                               array('tab'          => 'grading',
                                                     'confirm'      => 'true',
                                                     'sesskey'      => sesskey(),
                                                     'activity'     => $activity,
                                                     'mygroups_only'=> $mygroups_only,
                                                     'overwrite'    => $overwrite,
                                                     'step'         => '2',
                                                     'selected'     => serialize($selected),
                                                     'source'       => serialize($source)));
                    $cancel = new moodle_url("view.php?id=".$this->cm->id, array('tab'=>'grading',
                            'confirm'=>'false',
                            'sesskey'=>sesskey(),
                            'activity'=>$activity,
                            'mygroups_only'=>$mygroups_only,
                            'overwrite'=>$overwrite,
                            'step'=>'2',
                            'selected'=>serialize($selected),
                            'source'=>serialize($source)));
                    $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                    if ($overwrite) {
                        echo $preview.$this->confirm(get_string('copy_grades_overwrite_confirm',
                                                                  'grouptool'), $continue, $cancel);
                    } else {
                        echo $preview.$this->confirm(get_string('copy_grades_confirm',
                                                                  'grouptool'), $continue, $cancel);
                    }
                } else {
                    if (empty($selected)) {
                        echo $OUTPUT->box($OUTPUT->notification(get_string('no_target_selected',
                                                                           'grouptool'),
                                                                'notifyproblem'), 'generalbox');
                        $step = 0;
                    }
                    if (count($missingsource) != 0) {
                        echo $OUTPUT->box($OUTPUT->notification(get_string('sources_missing',
                                                                           'grouptool'),
                                                                'notifyproblem'), 'generalbox');
                        $step = 0;
                    }
                }
            } else {
                print_error('wrong parameter');
            }
        }

        if ($step == 2) {    // Do action and continue with showing the form!
            // if there was an error?
            list($error, $info) = $this->copy_grades($activity, $mygroups_only, $selected, $source,
                                                     $overwrite);
            if ($error) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('copy_grades_errors',
                                                                   'grouptool'),
                                                        'notifyproblem').$info, 'generalbox tumargin');
            } else {
                echo $OUTPUT->box($OUTPUT->notification(get_string('copy_grades_success',
                                                                   'grouptool'),
                                                        'notifysuccess').$info, 'generalbox tumargin');
            }
        }

        if ($step != 1 || count($missingsource)) {    // Show form if step is either 0 or 2!

            // Prepare form content!
            $activity_title = get_string('grading_activity_title', 'grouptool');

            if ($modinfo = get_fast_modinfo($this->course)) {
                $section = 0;
                $sectionsinfo = $modinfo->get_section_info_all();
                $sections = $modinfo->get_sections();
                foreach ($sections as $curnumber => $sectionmodules) {
                    $sectiontext = '--- '.
                                   course_get_format($this->course)->get_section_name($curnumber).
                                   ' ---';
                    $activities["section/$curnumber"] = $sectiontext;

                    foreach ($sectionmodules as $curcmid) {
                        $mod = $modinfo->get_cm($curcmid);
                        if ($mod->modname == "label") {
                            continue;
                        }

                        if (file_exists($CFG->dirroot . '/mod/'.$mod->modname.'/lib.php')) {
                            require_once($CFG->dirroot . '/mod/'.$mod->modname.'/lib.php');
                            $supportfn = $mod->modname."_supports";
                            if (function_exists($supportfn)) {
                                if ($supportfn(FEATURE_GRADE_HAS_GRADE) !== true) {
                                    continue;
                                }
                            }
                        }

                        $name = strip_tags(format_string($mod->name, true));
                        if (textlib::strlen($name) > 55) {
                            $name = textlib::substr($name, 0, 50)."...";
                        }
                        if (!$mod->visible) {
                            $name = "(".$name.")";
                        }
                        $activities["$curcmid"] = $name;
                    }
                }
            }

            $hidden_elements = html_writer::empty_tag('input', array('type'  => 'hidden',
                                                                     'name'  => 'sesskey',
                                                                     'value' => sesskey()));
            $activity_element = html_writer::select($activities, "activity", $activity);;
            $activity_select =     html_writer::start_tag('div', array('class'=>'fitem')).
                                   html_writer::tag('div', $activity_title,
                                                    array('class'=>'fitemtitle')).
                                   html_writer::tag('div', $activity_element,
                                                    array('class'=>'felement')).
                                   html_writer::end_tag('div');

            $mygroups_only_title = "";
            if (!has_capability('mod/grouptool:grade', $this->context)) {
                $attr['disabled'] = 'disabled';
                $mygroups_only_element = html_writer::checkbox('mygroups_only', 1, $mygroups_only,
                                                               get_string('mygroups_only_label',
                                                                          'grouptool'), $attr);
                $attributes['type']    = 'hidden';
                $attributes['value']   = 1;
                $attributes['name']    = 'mygroups_only';
                $mygroups_only_element .= html_writer::empty_tag('input', $attributes);
            } else {
                $mygroups_only_element = html_writer::checkbox('mygroups_only', 1, $mygroups_only,
                                                               get_string('mygroups_only_label',
                                                                          'grouptool'));
            }
            $mygroups_only_chkbox =    html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', ($mygroups_only_title != "" ? $mygroups_only_title : "&nbsp;"),
                             array('class'=>'fitemtitle')).
            html_writer::tag('div', $mygroups_only_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $incomplete_only_title = "";
            $incomplete_only_element = html_writer::checkbox('incomplete_only', 1, $incomplete_only,
                                                             get_string('incomplete_only_label',
                                                                        'grouptool'));
            $incomplete_only_chkbox =   html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', ($incomplete_only_title != "" ? $incomplete_only_title
                                                                  : "&nbsp;"),
                             array('class'=>'fitemtitle')).
            html_writer::tag('div', $incomplete_only_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $overwrite_title = "";
            $overwrite_element = html_writer::checkbox('overwrite', 1, $overwrite,
                                                       get_string('overwrite_label', 'grouptool'));
            $overwrite_chkbox =    html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', ($overwrite_title != "" ? $overwrite_title : "&nbsp;"),
                             array('class'=>'fitemtitle')).
            html_writer::tag('div', $overwrite_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $filter_title = get_string('grading_filter_select_title', 'grouptool').
                            $OUTPUT->help_icon('grading_filter_select_title', 'grouptool');
            $options = array("-1" => get_string('nonconflicting', 'grouptool'),
                             "0"  => get_string('all'));
            $groups = groups_get_all_groups($this->course->id, null, null, 'id, name');
            foreach ($groups as $key => $group) {
                $membercount = $DB->count_records('groups_members', array('groupid'=>$group->id));
                if ($membercount == 0) {
                    continue;
                }
                $options[$key] = $group->name.' ('.$membercount.')';
            }

            $filter_element = html_writer::select($options, 'filter', $filter, false);
            $filter_select =    html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', $filter_title, array('class'=>'fitemtitle')).
            html_writer::tag('div', $filter_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $grouping_title = get_string('grading_grouping_select_title', 'grouptool');
            $groupings = groups_get_all_groupings($this->course->id);
            $options = array();
            foreach ($groupings as $currentgrouping) {
                $options[$currentgrouping->id] = $currentgrouping->name;
            }
            $grouping_element = html_writer::select($options, 'grouping', $grouping,
                                                    get_string('disabled', 'grouptool'));
            $grouping_select =  html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', $grouping_title, array('class'=>'fitemtitle')).
            html_writer::tag('div', $grouping_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $refresh_title = "";
            $refresh_element = html_writer::tag('button', get_string('refresh_table_button',
                                                                     'grouptool'),
                                                array('type'  => 'submit',
                                                      'name'  => 'refresh_table',
                                                      'value' => 'true'));
            $refresh_button =    html_writer::start_tag('div', array('class'=>'fitem')).
            html_writer::tag('div', ($refresh_title != "" ? $refresh_title : "&nbsp;"),
                             array('class'=>'fitemtitle')).
            html_writer::tag('div', $refresh_element, array('class'=>'felement')).
            html_writer::end_tag('div');

            $legend = html_writer::tag('legend', get_string('filters_legend', 'grouptool'));
            $filterelements = html_writer::tag('fieldset',
                    $legend.
                    $activity_select.
                    $mygroups_only_chkbox.
                    $incomplete_only_chkbox.
                    $overwrite_chkbox.
                    $filter_select.
                    $grouping_select.
                    $refresh_button,

                    array('class'=>'clearfix'));
            if ($filter > 0) {
                $tablehtml = $this->get_grading_table($activity, $mygroups_only, $incomplete_only,
                                                      $filter, $selected);
            } else {
                $tablehtml = $this->get_grading_table($activity, $mygroups_only, $incomplete_only,
                                                      $filter, $selected, $missingsource);
            }

            $formcontent = html_writer::tag('div', $hidden_elements.$filterelements.$tablehtml,
                                            array('class'=>'clearfix'));

            $formattr = array(
                    'method' => 'post',
                    'action' => $PAGE->url,
                    'name'   => 'grading_form',
                    'class'  => 'mform');
            // Print form!
            echo html_writer::tag('form', $formcontent, $formattr);
        }

    }

    /**
     * gets data about active groups for this instance
     *
     * @global object $DB
     * @global object $PAGE
     * @param bool $include_regs optional include registered users in returned object
     * @param bool $include_queues optional include queued users in returned object
     * @param int $agrpid optional filter by a single active-groupid from {grouptool_agrps}.id
     * @param int $groupid optional filter by a single group-id from {groups}.id
     * @param int $groupingid optional filter by a single grouping-id
     * @param bool $indexbygroup optional index returned array by {groups}.id
     *                                    instead of {grouptool_agrps}.id
     * @return array of objects containing all necessary information about chosen active groups
     */
    private function get_active_groups($include_regs=false, $include_queues=false, $agrpid=0,
                                       $groupid=0, $groupingid=0, $indexbygroup=true) {
        global $DB, $PAGE, $CFG;

        require_capability('mod/grouptool:view_groups', $this->context);

        $params = array('grouptoolid'=>$this->cm->instance);

        if (!empty($agrpid)) {
            $agrpid_where = " AND agrp.id = :agroup";
            $params['agroup'] = $agrpid;
        } else {
            $agrpid_where = "";
        }
        if (!empty($groupid)) {
            $groupid_where = " AND grp.id = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupid_where = "";
        }
        if (!empty($groupingid)) {
            $groupingid_where = " AND grpgs.id = :groupingid";
            $params['groupingid'] = $groupingid;
        } else {
            $groupingid_where = "";
        }

        if (!empty($this->grouptool->use_size)) {
            if (empty($this->grouptool->use_individual)) {
                $size_sql = " ".$this->grouptool->grpsize." AS grpsize,";
            } else {
                $grpsize = (!empty($this->grouptool->grpsize) ? $this->grouptool->grpsize
                                                              : $CFG->grouptool_grpsize);
                if (empty($grpsize)) {
                    $grpsize = 3;
                }
                $size_sql = " COALESCE(agrp.grpsize, ".$grpsize.") AS grpsize,";
            }
        } else {
            $size_sql = "";
        }
        if ($indexbygroup) {
            $idstring = "grp.id as id, agrp.id as agrp_id";
        } else {
            $idstring = "agrp.id as agrp_id, grp.id as id";
        }
        $groupdata = $DB->get_records_sql("
                SELECT ".$idstring.", grp.name AS name,".$size_sql." agrp.sort_order AS sort_order
                FROM {groups} AS grp LEFT JOIN {grouptool_agrps} as agrp ON agrp.group_id = grp.id
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} AS grpgs ON {groupings_groups}.groupingid = grpgs.id
                WHERE agrp.grouptool_id = :grouptoolid AND agrp.active = 1".
                     $agrpid_where.$groupid_where.$groupingid_where."
                GROUP BY grp.id
                ORDER BY sort_order ASC, name ASC", $params);
        foreach ($groupdata as $key => $group) {
            $groupingids = $DB->get_fieldset_select('groupings_groups',
                                                    'groupingid',
                                                    'groupid = ?',
                                                    array($group->id));
            if(!empty($groupingids)) {
                $groupdata[$key]->classes = implode(',', $groupingids);
            } else {
                $groupdata[$key]->classes = '';
            }
        }

        if ((!empty($this->grouptool->use_size) && !$this->grouptool->use_individual)
                || ($this->grouptool->use_queue && $include_queues)
                || ($include_regs)) {

            foreach ($groupdata as $key => $currentgroup) {

                $groupdata[$key]->queued = null;
                if ($include_queues && $this->grouptool->use_queue) {
                    $attr = array('agrp_id'=>$currentgroup->agrp_id);
                    $groupdata[$key]->queued = (array)$DB->get_records('grouptool_queued', $attr);
                }

                $groupdata[$key]->registered = null;
                if ($include_regs) {
                    $attr = array('agrp_id'=>$currentgroup->agrp_id);
                    $groupdata[$key]->registered = (array)$DB->get_records('grouptool_registered',
                                                                           $attr);
                    $groupdata[$key]->moodle_members = groups_get_members($currentgroup->id);
                }
            }
        }

        return $groupdata;
    }

    /**
     * unregisters/unqueues a user from a certain active-group
     *
     * @global object $USER
     * @global object $PAGE
     * @global object $DB
     * @global object $CFG
     * @param int $agrpid active-group-id to unregister/unqueue user from
     * @param int $userid user to unregister/unqueue
     * @param bool $preview_only optional don't act, just return a preview
     * @return array ($error, $message)
     */
    private function unregister_from_agrp($agrpid=0, $userid=0, $preview_only=false) {
        global $USER, $PAGE, $DB, $CFG;

        if (empty($agrpid)) {
            print_error('missing_param', null, $PAGE->url);
        }

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $reg_open = ($this->grouptool->allow_reg
                && (($this->grouptool->timedue == 0)
                        || (time() < $this->grouptool->timedue)));

        if (!$reg_open && !has_capability('mod/grouptool:register_students', $this->context)) {
            return array(true, get_string('reg_not_open', 'grouptool'));
        }

        if (empty($this->grouptool->allow_unreg)) {
            return array(true, get_string('unreg_not_allowed', 'grouptool'));
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', array('id'=>$userid));
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        $groupdata = reset($groupdata);
        $message->groupname = $groupdata->name;

        $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptool_id = ?", array($this->grouptool->id));
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('grouptool_registered', "user_id = ? AND agrp_id ".$agrpsql, $params);
        $userqueues = $DB->count_records_select('grouptool_queued', "user_id = ? AND agrp_id ".$agrpsql, $params);
        $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
        $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
        if ($userregs+$userqueues <= $min) {
            if ($userid == $USER->id) {
                return array(true, get_string('you_have_too_less_regs', 'grouptool', $message));
            } else {
                return array(true, get_string('user_has_too_less_regs', 'grouptool', $message));
            }
        }
        
        if ($groupdata) {
            if ($this->get_rank_in_queue($groupdata->registered, $userid) != false) {
                // He is registered!
                if ($preview_only) {
                    if ($userid == $USER->id) {
                        return array(false, get_string('unreg_you_from_group', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unreg_from_group', 'grouptool',
                                                       $message));
                    }
                } else {
                    $DB->delete_records('grouptool_registered', array('agrp_id' => $agrpid,
                                                                      'user_id' => $userid));
                    if (!empty($this->grouptool->immediate_reg)) {
                        groups_remove_member($groupdata->id, $userid);
                    }
                    // Get next queued user and put him in the group (and delete queue entry)!
                    if (!empty($this->grouptool->use_queue) && !empty($groupdata->queued)) {
                        $sql = "SELECT *
                        FROM {grouptool_queued}
                        WHERE agrp_id = ?
                        ORDER BY timestamp ASC
                        LIMIT 1";
                        $record = $DB->get_record_sql($sql, array($agrpid));
                        $new_record = clone $record;
                        unset($new_record->id);
                        $new_record->modified_by = $new_record->user_id;
                        $DB->insert_record('grouptool_registered', $new_record);
                        if (!empty($this->grouptool->immediate_reg)) {
                            groups_add_member($groupdata->id, $new_record->user_id);
                        }
                        $allow_m = $this->grouptool->allow_multiple;
                        $usrregcnt = $this->get_user_reg_count(0, $new_record->user_id);
                        $max = $this->grouptool->choose_max;
                        if (($allow_m && ( $usrregcnt >= $max) ) || !$allow_m) {
                            $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                            $agrpids = array_keys($agrps);
                            list($sql, $params) = $DB->get_in_or_equal($agrpids);
                            $DB->delete_records_select('grouptool_queued',
                                                       ' user_id = ? AND agrp_id '.$sql,
                                                       array_merge(array($new_record->user_id),
                                                                   $params));
                        }

                        $strgrouptools = get_string("modulenameplural", "grouptool");
                        $strgrouptool  = get_string("modulename", "grouptool");

                        $postsubject = $this->course->shortname.': '.$strgrouptools.': '.
                                       format_string($this->grouptool->name, true);
                        $posttext  = $this->course->shortname.' -> '.$strgrouptools.' -> '.
                                     format_string($this->grouptool->name, true)."\n";
                        $posttext .= "----------------------------------------------------------\n";
                        $posttext .= get_string("register_you_in_group_successmail",
                                                "grouptool", $message)."\n";
                        $posttext .= "----------------------------------------------------------\n";
                        $usermailformat = $DB->get_field('user', 'mailformat',
                                                         array('id'=>$new_record->user_id));
                        if ($usermailformat == 1) {  // HTML!
                            $posthtml = "<p><font face=\"sans-serif\">";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/course/view.php?id=".
                                        $this->course->id."\">".$this->course->shortname."</a> ->";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/index.php?id=".
                                        $this->course->id."\">".$strgrouptools."</a> ->";
                            $posthtml = "<a href=\"".$CFG->wwwroot."/mod/grouptool/view.php?id=".
                                        $this->cm->id."\">".format_string($this->grouptool->name,
                                                                          true)."</a></font></p>";
                            $posthtml .= "<hr /><font face=\"sans-serif\">";
                            $posthtml .= "<p>".get_string("register_you_in_group_successmailhtml",
                                                          "grouptool", $message)."</p>";
                            $posthtml .= "</font><hr />";
                        } else {
                            $posthtml = "";
                        }

                        $messageuser = $DB->get_record('user', array('id'=>$new_record->user_id));
                        $eventdata = new stdClass();
                        $eventdata->modulename       = 'grouptool';
                        $eventdata->userfrom         = $messageuser;
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
                                                      $this->cm->id;
                        $eventdata->contexturlname  = $this->grouptool->name;

                        message_send($eventdata);
                        $DB->delete_records('grouptool_queued', array('id'=>$record->id));
                    }
                    add_to_log($this->grouptool->course,
                            'grouptool', 'unregister',
                            "view.php?id=".$this->grouptool->id."&tab=overview&agrpid=".$agrpid,
                            'unregister user:'.$userid.' from agrpid:'.$agrpid);
                    if ($userid == $USER->id) {
                        return array(false, get_string('unreg_you_from_group_success', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unreg_from_group_success', 'grouptool',
                                                       $message));
                    }
                }
            } else if ($this->get_rank_in_queue($groupdata->queued, $userid) != false) {
                // He is queued!
                if ($preview_only) {
                    if ($userid == $USER->id) {
                        return array(false, get_string('unqueue_you_from_group', 'grouptool',
                                                       $message));
                    } else {
                        return array(false, get_string('unqueue_from_group', 'grouptool',
                                                       $message));
                    }
                } else {
                    $DB->delete_records('grouptool_queued', array('agrp_id'=>$agrpid,
                            'user_id'=>$userid));
                    add_to_log($this->grouptool->course,
                            'grouptool', 'unregister',
                            "view.php?id=".$this->grouptool->id."&tab=overview&agrpid=".$agrpid,
                            'unqueue user:'.$userid.' from agrpid:'.$agrpid);
                    if ($userid == $USER->id) {
                        return array(false, get_string('unqueue_you_from_group_success',
                                                       'grouptool', $message));
                    } else {
                        return array(false, get_string('unqueue_from_group_success', 'grouptool',
                                                       $message));
                    }
                }
            } else {
                if ($userid == $USER->id) {
                    return array(true, get_string('you_are_not_in_queue_or_registered', 'grouptool',
                                                  $message));
                } else {
                    return array(true, get_string('not_in_queue_or_registered', 'grouptool',
                                                  $message));
                }
            }
        } else {
            return array(true, get_string('error_getting_data', 'grouptool'));
        }
    }

    /**
     * registers/queues a user in a certain active-group
     *
     * @global object $USER
     * @global object $PAGE
     * @global object $DB
     * @param int $agrpid active-group-id to register/queue user to
     * @param int $userid user to register/queue
     * @param bool $preview_only optional don't act, just return a preview
     * @return array ($error, $message)
     */
    private function register_in_agrp($agrpid=0, $userid=0, $preview_only=false) {
        global $USER, $PAGE, $DB, $SESSION;

        $grouptool = $this->grouptool;

        if (empty($agrpid)) {
            print_error('missing_param', null, $PAGE->url);
        }

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/grouptool:register', $this->context);
        }

        $reg_open = ($this->grouptool->allow_reg
                && (($this->grouptool->timedue == 0)
                        || (time() < $this->grouptool->timedue)));

        if (!$reg_open && !has_capability('mod/grouptool:register_students', $this->context)) {
            return array(true, get_string('reg_not_open', 'grouptool'));
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', array('id'=>$userid));
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) == 1) {
            $groupdata = current($groupdata);
            $message->groupname = $groupdata->name;
            $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptool_id = ?", array($grouptool->id));
            list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
            array_unshift($params, $userid);
            $userregs = $DB->count_records_select('grouptool_registered', "user_id = ? AND agrp_id ".$agrpsql, $params);
            $userqueues = $DB->count_records_select('grouptool_queued', "user_id = ? AND agrp_id ".$agrpsql, $params);
            $max = $grouptool->allow_multiple ? $grouptool->choose_max : 1;
            $min = $grouptool->allow_multiple ? $grouptool->choose_min : 0;
            if (!empty($groupdata->registered)
                && $this->get_rank_in_queue($groupdata->registered, $userid) != false) {
                // We're sorry, but user's already registered in this group!
                if ($userid != $USER->id) {
                    return array(true, get_string('already_registered', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_already_registered', 'grouptool',
                                                  $message));
                }
            }

            if (!empty($groupdata->queued)
                && $this->get_rank_in_queue($groupdata->queued, $userid) != false) {
                // We're sorry, but user's already queued in this group!
                if ($userid != $USER->id) {
                    return array(true, get_string('already_queued', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_aleady_queued', 'grouptol', $message));
                }
            }

            if (isset($SESSION->grouptool->marks) && $this->grpmarked($groupdata->agrp_id)) {
                //allready marked for registration
                if ($userid != $USER->id) {
                    return array(true, get_string('already_marked', 'grouptool', $message));
                } else {
                    return array(true, get_string('you_are_already_marked', 'grouptool', $message));
                }
            }

            if (($userqueues == 1 && $userregs == $max-1) || ($userqueues+$userregs == 1 && $max == 1)) {
                //groupchange!
                if(empty($grouptool->allow_unreg)) {
                    return array(true, get_string('unreg_not_allowed', 'grouptool'));
                }

                if ($preview_only) {
                    if (!$this->grouptool->use_size
                        || (count($groupdata->registered) < $groupdata->grpsize)
                        || ($this->grouptool->use_queue
                            && $userqueues < $this->grouptool->queues_max)) {
                        return array(-1,
                                     get_string('change_group_to', 'grouptool', $message));
                    } else if (!$this->grouptool->use_queue) {
                        //group is full!
                        if ($userid != $USER->id) {
                            return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                        } else {
                            return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                        }
                    } else if ($userqueues >= $this->grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                } else if (!$this->grouptool->use_size
                           || (count($groupdata->registered) < $groupdata->grpsize)
                           || ($this->grouptool->use_queue
                               && $userqueues-1 < $this->grouptool->queues_max)) {
                    $record = new stdClass();
                    $record->agrp_id = $agrpid;
                    $record->user_id = $userid;
                    $record->timestamp = time();
                    $record->modified_by = $USER->id;
                    if($userqueues == 1) {
                        //delete his queue
                        $DB->delete_records_select('grouptool_queued',
                                                   "user_id = ? AND agrp_id ".$agrpsql, $params);
                    } else if ($userregs == 1) {
                        $oldgrp = $DB->get_field_sql("SELECT agrp.group_id
                                                        FROM {grouptool_registered} as reg
                                                        JOIN {grouptool_agrps} as agrp ON agrp.id = reg.agrp_id
                                                       WHERE reg.user_id = ? AND reg.agrp_id ".$agrpsql,
                                                     $params, MUST_EXIST);
                        $DB->delete_records_select('grouptool_registered',
                                                   "user_id = ? AND agrp_id ".$agrpsql, $params);
                        if(!empty($oldgrp) && !empty($this->grouptool->immediate_reg)) {
                            groups_remove_member($oldgrp, $userid);
                        } else if (empty($oldgrp)) {
                            //error, old group not found ?!?
                        }
                    }
                    if (!$this->grouptool->use_size
                        || (count($groupdata->registered) < $groupdata->grpsize)) {
                        $DB->insert_record('grouptool_registered', $record);
                        if ($this->grouptool->immediate_reg) {
                            groups_add_member($groupdata->id, $userid);
                        }
                        add_to_log($this->grouptool->course,
                                'grouptool', 'add registration',
                                "view.php?id=".$this->grouptool->id.
                                "&tab=overview&agrpid=".$agrpid,
                                'register user:'.$userid.' in agrpid:'.$agrpid);
                    } else if ($this->grouptool->use_queue
                               && $userqueues-1 < $this->grouptool->queues_max) {
                        $DB->insert_record('grouptool_queued', $record);
                        add_to_log($this->grouptool->course,
                                   'grouptool', 'add queue',
                                   "view.php?id=".$this->grouptool->id.
                                   "&tab=overview&agrpid=".$agrpid,
                                   'queue user:'.$userid.' in agrpid:'.$agrpid);
                    } else if (!$this->grouptool->use_queue) {
                        //group is full!
                        if ($userid != $USER->id) {
                            return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                        } else {
                            return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                        }
                    } else  if ($userqueues-1 >= $this->grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                    if ($userid != $USER->id) {
                        return array(-1,
                                     get_string('change_group_to_success', 'grouptool',
                                                $message));
                    } else {
                        return array(-1,
                                     get_string('you_change_group_to_success', 'grouptool',
                                                $message));
                    }
                }
            }

            if ($userregs+$userqueues >= $max) {
                return array(1, get_string('too_many_regs', 'grouptool'));
            }
            $marks = isset($SESSION->grouptool->marks) ? count($SESSION->grouptool->marks) : 0;
            if ($grouptool->use_size) {
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    //register
                    if ($preview_only) {
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('register_in_group', 'grouptool', $message));
                        } else {
                            return array(false,
                                         get_string('register_you_in_group', 'grouptool',
                                                    $message));
                        }
                    } else if ($this->grouptool->allow_multiple
                               && ($this->grouptool->choose_min > ($marks+1+$userregs+$userqueues))) {
                        //cache data until enough registrations are made
                        if(!isset($SESSION->grouptool->marks)) {
                            $SESSION->grouptool->marks = array();
                        }
                        $record = new stdClass();
                        $record->agrp_id = $agrpid;
                        $record->grp_id = $groupdata->id;
                        $record->user_id = $userid;
                        $record->timestamp = time();
                        $record->modified_by = $USER->id;
                        $record->type = 'reg';
                        $SESSION->grouptool->marks[] = $record;
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('your_place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        }
                    } else {
                        if ($this->grouptool->allow_multiple) {
                            //enough registrations have been made, save them
                            if(isset($SESSION->grouptool->marks)) {
                                foreach ($SESSION->grouptool->marks as $cur) {
                                    if ($cur->type == 'reg') {
                                        unset($cur->type);
                                        $DB->insert_record('grouptool_registered', $cur);
                                        if ($this->grouptool->immediate_reg) {
                                            groups_add_member($cur->grp_id, $cur->user_id);
                                        }
                                    } else {
                                        unset($cur->type);
                                        $DB->insert_record('grouptool_queued', $cur);
                                    }
                                }
                                unset($SESSION->grouptool->marks);
                            }
                        }
                        $record = new stdClass();
                        $record->agrp_id = $agrpid;
                        $record->user_id = $userid;
                        $record->timestamp = time();
                        $record->modified_by = $USER->id;
                        $DB->insert_record('grouptool_registered', $record);
                        add_to_log($this->grouptool->course,
                                'grouptool', 'add registration',
                                "view.php?id=".$this->grouptool->id."&tab=overview&agrpid=".$agrpid,
                                'register user:'.$userid.' in agrpid:'.$agrpid);
                        if ($this->grouptool->immediate_reg) {
                            groups_add_member($groupdata->id, $userid);
                        }

                        $regcnt = $this->get_user_reg_count(0, $userid);
                        if (($this->grouptool->allow_multiple
                            && ($regcnt >= $this->grouptool->choose_max))
                            || !$this->grouptool->allow_multiple) {
                            $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                            if(count($agrps) > 0) {
                                $agrpids = array_keys($agrps);
                                list($sql, $params) = $DB->get_in_or_equal($agrpids);
                                $DB->delete_records_select('grouptool_queued',
                                                           ' user_id = ? AND agrp_id '.$sql,
                                                           array_merge(array($userid), $params));
                            }
                        }
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('register_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('register_you_in_group_success', 'grouptool',
                                                    $message));
                        }
                    }
                } else if ($grouptool->use_queue) {
                    //try to queue
                    if($userqueues >= $grouptool->queues_max) {
                        if ($userid != $USER->id) {
                            return array(1, get_string('too_many_queue_places', 'grouptool'));
                        } else {
                            return array(1, get_string('you_have_too_many_queue_places', 'grouptool'));
                        }
                    }
                    $marks = isset($SESSION->grouptool->marks) ? count($SESSION->grouptool->marks) : 0;
                    if ($preview_only) {
                        if ($userid != $USER->id) {
                            return array(-1,
                                         get_string('queue_in_group', 'grouptool', $message));
                        } else {
                            return array(-1,
                                         get_string('queue_you_in_group', 'grouptool',
                                                    $message));
                        }
                    } else if ($this->grouptool->allow_multiple
                               && ($this->grouptool->choose_min > ($marks+1+$userregs+$userqueues))) {
                        //cache data until enough registrations are made
                        if(!isset($SESSION->grouptool->marks)) {
                            $SESSION->grouptool->marks = array();
                        }
                        $record = new stdClass();
                        $record->agrp_id = $agrpid;
                        $record->grp_id = $groupdata->id;
                        $record->user_id = $userid;
                        $record->timestamp = time();
                        $record->modified_by = $USER->id;
                        $record->type = 'queue';
                        $SESSION->grouptool->marks[] = $record;
                        if ($userid != $USER->id) {
                            return array(false,
                                         get_string('place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(false,
                                         get_string('your_place_allocated_in_group_success', 'grouptool',
                                                    $message));
                        }
                    } else {
                        if ($this->grouptool->allow_multiple) {
                            if(isset($SESSION->grouptool->marks)) {
                                //enough registrations have been made, save them
                                foreach ($SESSION->grouptool->marks as $cur) {
                                    if ($cur->type == 'reg') {
                                        unset($cur->type);
                                        $DB->insert_record('grouptool_registered', $cur);
                                        if ($this->grouptool->immediate_reg) {
                                            groups_add_member($cur->grp_id, $cur->user_id);
                                        }
                                    } else {
                                        unset($cur->type);
                                        $DB->insert_record('grouptool_queued', $cur);
                                    }
                                }
                                unset($SESSION->grouptool->marks);
                            }
                        }
                        $record = new stdClass();
                        $record->agrp_id = $agrpid;
                        $record->user_id = $userid;
                        $record->timestamp = time();
                        $DB->insert_record('grouptool_queued', $record);
                        add_to_log($this->grouptool->course,
                                'grouptool', 'register',
                                "view.php?id=".$this->grouptool->id.
                                "&tab=overview&agrpid=".$agrpid,
                                'queue user:'.$userid.' in agrpid:'.$agrpid);
                        if ($userid != $USER->id) {
                            return array(-1,
                                         get_string('queue_in_group_success', 'grouptool',
                                                    $message));
                        } else {
                            return array(-1,
                                         get_string('queue_you_in_group_success', 'grouptool',
                                                    $message));
                        }
                    }

                } else {
                    //group is full!
                    if ($userid != $USER->id) {
                        return array(1, get_string('reg_in_full_group', 'grouptool', $message));
                    } else {
                        return array(1, get_string('reg_you_in_full_group', 'grouptool', $message));
                    }
                }
            } else {
                //register him 
                if ($preview_only) {
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('register_in_group', 'grouptool', $message));
                    } else {
                        return array(false,
                                     get_string('register_you_in_group', 'grouptool',
                                                $message));
                    }
                } else if ($this->grouptool->allow_multiple
                           && ($this->grouptool->choose_min > ($marks+1+$userregs+$userqueues))) {
                    //cache data until enough registrations are made
                    if(!isset($SESSION->grouptool->marks)) {
                        $SESSION->grouptool->marks = array();
                    }
                    $record = new stdClass();
                    $record->agrp_id = $agrpid;
                    $record->grp_id = $groupdata->id;
                    $record->user_id = $userid;
                    $record->timestamp = time();
                    $record->modified_by = $USER->id;
                    $record->type = 'reg';
                    $SESSION->grouptool->marks[] = $record;
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('place_allocated_in_group_success', 'grouptool',
                                                $message));
                    } else {
                        return array(false,
                                     get_string('your_place_allocated_in_group_success', 'grouptool',
                                                $message));
                    }
                } else {
                    if ($this->grouptool->allow_multiple) {
                        //enough registrations have been made, save them
                        if(isset($SESSION->grouptool->marks)) {
                            foreach ($SESSION->grouptool->marks as $cur) {
                                if ($cur->type == 'reg') {
                                    unset($cur->type);
                                    $DB->insert_record('grouptool_registered', $cur);
                                    if ($this->grouptool->immediate_reg) {
                                        groups_add_member($cur->grp_id, $cur->user_id);
                                    }
                                } else {
                                    unset($cur->type);
                                    $DB->insert_record('grouptool_queued', $cur);
                                }
                            }
                            unset($SESSION->grouptool->marks);
                        }
                    }
                    $record = new stdClass();
                    $record->agrp_id = $agrpid;
                    $record->user_id = $userid;
                    $record->timestamp = time();
                    $record->modified_by = $USER->id;
                    $DB->insert_record('grouptool_registered', $record);
                    add_to_log($this->grouptool->course,
                            'grouptool', 'add registration',
                            "view.php?id=".$this->grouptool->id."&tab=overview&agrpid=".$agrpid,
                            'register user:'.$userid.' in agrpid:'.$agrpid);
                    if ($this->grouptool->immediate_reg) {
                        groups_add_member($groupdata->id, $userid);
                    }

                    $regcnt = $this->get_user_reg_count(0, $userid);
                    if (($this->grouptool->allow_multiple
                        && ($regcnt >= $this->grouptool->choose_max))
                        || !$this->grouptool->allow_multiple) {
                        $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);
                        if(count($agrps) > 0) {
                            $agrpids = array_keys($agrps);
                            list($sql, $params) = $DB->get_in_or_equal($agrpids);
                            $DB->delete_records_select('grouptool_queued',
                                                       ' user_id = ? AND agrp_id '.$sql,
                                                       array_merge(array($userid), $params));
                        }
                    }
                    if ($userid != $USER->id) {
                        return array(false,
                                     get_string('register_in_group_success', 'grouptool',
                                                $message));
                    } else {
                        return array(false,
                                     get_string('register_you_in_group_success', 'grouptool',
                                                $message));
                    }
                }
            }
        } else {
            return array(true, get_string('error_getting_data', 'grouptool'));
        }
    }

    /**
     * returns number of queue-entries for a particular user in a particular grouptool-instance
     *
     * @global object $DB
     * @global object $USER
     * @param int $grouptoolid optional stats from which grouptool-instance should be obtained?
     *                                  uses this->grouptool->id if zero
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     */
    private function get_user_queues_count($grouptoolid=0, $userid=0) {
        global $DB, $USER;
        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrp_id;
        }
        if(count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_queued}
                                       WHERE user_id = ? AND agrp_id '.$sql, $params);
    }

    /**
     * returns number of reg-entries for a particular user in a particular grouptool-instance
     *
     * @global object $DB
     * @global object $USER
     * @param int $grouptoolid optional stats from which grouptool-instance should be obtained?
     *                                  uses this->grouptool->id if zero
     * @param int $userid optional user for whom stats should be obtained? uses $USER->id if zero
     * @return int count of queues in specified instance for specified user
     */
    private function get_user_reg_count($grouptoolid=0, $userid=0) {
        global $DB, $USER;
        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrp_id;
        }
        if(count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {grouptool_registered}
                                       WHERE user_id = ? AND agrp_id '.$sql, $params);
    }

    /**
     * helperfunction compares to objects using a particular timestamp-property
     *
     * @param object $a object containing timestamp property
     * @param object $b object containing timestamp property
     * @return int 0 if equal, +1 if $a->timestamp > $b->timestamp or -1 if otherwise
     */
    private function cmptimestamp($a, $b) {
        if ($a->timestamp == $b->timestamp) {
            return 0;
        } else {
            return $a->timestamp > $b->timestamp ? +1 : -1;
        }
    }

    /**
     * returns rank in queue for a particular user
     * if $data is an array uses array (like queue/reg-info returned by {@link get_active_groups()})
     * to determin rank otherwise if $data is an integer uses DB-query to get queue rank in
     * active group with id == $data
     *
     * @global object $DB
     * @param array|int $data array with regs/queues for a group like returned
     *                        by get_active_groups() or agrpid
     * @param int $userid user for whom data should be returned
     * @return int rank in queue/registration (registration only via $data-array)
     */
    private function get_rank_in_queue($data=0, $userid=0) {
        global $DB;
        if (is_array($data)) { // It's the queue itself!
            uasort($data, array(&$this, "cmptimestamp"));
            $i=1;
            foreach ($data as $entry) {
                if ($entry->user_id == $userid) {
                    return $i;
                } else {
                    $i++;
                }
            }
            return false;
        } else if (!empty($data)) { // It's an active-group-id, so we gotta get the queue data!
            $params = array('agrpid' => $data,
                    'userid' => !empty($userid) ? $userid : $USER->id);
            $sql = "SELECT count(b.id) as rank
            FROM {grouptool_queued} as a
            INNER JOIN {grouptool_queued} as b
            ON b.timestamp <= a.timestamp
            WHERE a.agrp_id = :agrpid AND a.user_id = :userid";
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
     * @global object $USER
     * @global object $DB
     * @param int $userid id of user for whom data should be added
     *                    or 0 (=$USER) or null (=no userdata)
     * @return object object containing information about active groups
     */
    public function get_registration_stats($userid=null) {
        global $USER, $DB;
        $return = new stdClass();
        $return->group_places = 0;
        $return->free_places = 0;
        $return->occupied_places = 0;
        $return->users = 0;
        $return->registered = array();
        $return->queued = array();
        $return->queued_users = 0;
        $return->reg_users = 0;

        switch ($userid) {
            case null:
                $userid = $USER->id;
            default:
                $groups = $this->get_active_groups(true, true);
                break;
            case 0:
                $groups = $this->get_active_groups();
        }

        foreach ($groups as $group) {
            if ($this->grouptool->use_size) {
                $return->group_places += $group->grpsize;
            }
            $return->occupied_places += count($group->registered);
            if ($userid != 0) {
                $reg_rank = $this->get_rank_in_queue($group->registered, $userid);
                if (!empty($reg_rank)) {
                    $reg_data = new stdClass();
                    $reg_data->rank = $reg_rank;
                    $reg_data->grpname = $group->name;
                    $reg_data->agrp_id = $group->agrp_id;
                    reset($group->registered);
                    do {
                        $current = current($group->registered);
                        $reg_data->timestamp = $current->timestamp;
                        next($group->registered);
                    } while ($current->user_id != $userid);
                    $reg_data->id = $group->id;
                    $return->registered[] = $reg_data;
                }

                $queue_rank = $this->get_rank_in_queue($group->queued, $userid);
                if (!empty($queue_rank)) {
                    $queue_data = new stdClass();
                    $queue_data->rank = $queue_rank;
                    $queue_data->grpname = $group->name;
                    $queue_data->agrp_id = $group->agrp_id;
                    reset($group->queued);
                    do {
                        $current = current($group->queued);
                        $queue_data->timestamp = $current->timestamp;
                        next($group->queued);
                    } while ($current->user_id != $userid);
                    $queue_data->id = $group->id;
                    $return->queued[] = $queue_data;
                }
            }
        }
        $return->free_places = ($this->grouptool->use_size) ?
                                   ($return->group_places - $return->occupied_places) :
                                   null;
        $return->users = count_enrolled_users($this->context, 'mod/grouptool:register');

        $agrps = $DB->get_records('grouptool_agrps', array('grouptool_id'=>$this->cm->instance));
        if (is_array($agrps) && count($agrps)>=1) {
            $agrpids = array_keys($agrps);
            list($inorequal, $params) = $DB->get_in_or_equal($agrpids);
            $sql = "SELECT count(DISTINCT user_id)
            FROM {grouptool_registered}
            WHERE agrp_id ".$inorequal;
            $return->reg_users = $DB->count_records_sql($sql, $params);
            $sql = "SELECT count(DISTINCT user_id)
            FROM {grouptool_queued}
            WHERE agrp_id ".$inorequal;
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
     * @global object $OUTPUT
     * @global object $DB
     * @global object $USER
     * @param string $mode optional, without function, reserved for optional later implementation
     *                     (random/alphabetical/... distribution, etc)
     * @param bool $preview_only show only preview of actions
     * @return array ($error, $message)
     */
    public function resolve_queues($mode = 'sortorder', $preview_only = false) {
        global $OUTPUT, $DB, $USER;
        $error = false;
        $returntext = "";

        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
            $grouptool = $this->grouptool;
            $context = $this->context;
        } else {
            $cmid = get_coursemodule_from_instance('grouptool', $grouptoolid);
            $grouptool = $DB->get_record('grouptool', array('id'=>$grouptoolid), '*', MUST_EXIST);
            $context = context_module::instance($cmid->id);
        }

        require_capability('mod/grouptool:register_students', $context);

        $agrps = $this->get_active_groups(false, false, 0, 0, 0, false);

        if (!empty($agrps)) {
            $agrpids = array_keys($agrps);
            list($agrpssql, $agrpsparam) = $DB->get_in_or_equal($agrpids);
            $agrps_sql = " AND agrp.id ".$agrpssql;
            $agrps_params = array_merge(array($grouptool->id), $agrpsparam);
            // Get queue-entries (sorted by timestamp)!
            if (!empty($grouptool->allow_multiple)) {
                $queued_sql = " WHERE (reg.agrp_id ".$agrpssql." OR queued.agrp_id ".$agrpssql.") ";
                $queued_params = array_merge($agrpsparam, $agrpsparam);
                $queue_entries = $DB->get_records_sql("
                    SELECT queued.*, (COUNT(DISTINCT reg.id) < ?) as priority
                    FROM {grouptool_queued} AS queued
                    JOIN {grouptool_registered} AS reg ON queued.user_id = reg.user_id
                    ".$queued_sql."
                   GROUP BY queued.id
                    ORDER BY priority DESC, queued.timestamp ASC",
                    array_merge(array($grouptool->choose_min), $queued_params));
            } else {
                $queued_sql = " WHERE queued.agrp_id ".$agrpssql." ";
                $queued_params = $agrpsparam;
                $queue_entries = $DB->get_records_sql("SELECT *, '1' as priority
                                                       FROM {grouptool_queued} as queued".
                                                       $queued_sql.
                                                      "ORDER BY 'timestamp' ASC",
                                                      $queued_params);
            }
        } else {
            return array(true, get_string('no_active_groups', 'grouptool'));
        }

        // Get group entries (sorted by sort-order)!
        $groupsdata = $DB->get_records_sql("SELECT agrp.*,
                COUNT(DISTINCT reg.id) as registered
                FROM {grouptool_agrps} as agrp
                LEFT JOIN {grouptool_registered} as reg ON reg.agrp_id = agrp.id
                WHERE agrp.grouptool_id = ?".$agrps_sql."
                GROUP BY agrp.id
                ORDER BY agrp.sort_order ASC", $agrps_params);

        if (!empty($groupsdata) && !empty($queue_entries)) {
            // Get first group in row!
            reset($groupsdata);
            $cur_group = current($groupsdata);
            $cur_group->grpsize = ($grouptool->use_individual && !empty($cur_group->size)) ?
                                   $cur_group->grpsize :
                                   $grouptool->grpsize;
            // For each db-entry!
            $planned = new stdClass();
            foreach ($queue_entries as $queue) {
                if (!isset($planned->{$queue->user_id})) {
                    $planned->{$queue->user_id} = array();
                }
                if (!empty($cur_group)) {
                    while ((!empty($grouptool->use_size) && !empty($cur_group->grpsize))
                            && ($cur_group->grpsize <= $cur_group->registered)) {
                        // Groups full --> next!
                        $cur_group = next($groupsdata);
                        if (empty($cur_group)) {
                            $error = true;
                            $returntext .= html_writer::tag('div',
                                                            get_string('all_groups_full',
                                                                       'grouptool',
                                                                       $queue->user_id),
                                                            array('class'=>'error'));
                        }
                        $cur_group->grpsize = ($grouptool->use_individual
                                                   && !empty($cur_group->grpsize)) ?
                                               $cur_group->grpsize :
                                               $grouptool->grpsize;
                    }
                } else {
                    $error = true;
                    $returntext .= html_writer::tag('div',
                                                    get_string('all_groups_full', 'grouptool',
                                                               $queue->user_id),
                                                    array('class'=>'error'));
                }
                if (!empty($cur_group)) {
                    // There is a group so register in this group!
                    if ($preview_only) {
                        $i = 0;
                        list($cur_error, $cur_text) = $this->register_in_agrp($cur_group->id,
                                                                              $queue->user_id,
                                                                              true);
                        if (in_array($cur_group->id, $planned->{$queue->user_id})) {
                            $cur_error = true;
                        }
                        while ($cur_error != 0) {
                            $i++;
                            $cur_group = next($groupsdata);
                            if (!$cur_group) {
                                $returntext .= html_writer::tag('div',
                                                                get_string('all_groups_full',
                                                                           'grouptool',
                                                                           $queue->user_id),
                                                                array('class'=>'error'));
                                return array(true, $returntext);
                            }
                            list($cur_error, $cur_text) = $this->register_in_agrp($cur_group->id,
                                                                                  $queue->user_id,
                                                                                  true);
                            if (in_array($cur_group->id, $planned->{$queue->user_id})) {
                                $cur_error = true;
                            }
                        }
                        $planned->{$queue->user_id}[] = $cur_group->id;
                        $class = $cur_error ? 'error': 'success';
                        $data = new stdClass();
                        $data->user_id = $queue->user_id;
                        $data->agrp_id = $queue->agrp_id;
                        $data->current_grp = $cur_group->id;
                        $data->current_text = $cur_text;
                        $movetext = get_string('user_move_prev', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movetext, array('class'=>$class));
                        if (!isset($status[$queue->user_id])) {
                            $status[$queue->user_id] = new stdClass();
                        }
                        $status[$queue->user_id]->error = false || $cur_error;
                        $cur_group->registered++;
                        for ($j=0; $j<$i; $j++) {
                            $cur_group = prev($groupsdata);
                        }
                        $error = $error || $cur_error;
                    } else {
                        $i = 0;
                        list($cur_error, $cur_text) = $this->register_in_agrp($cur_group->id,
                                                                              $queue->user_id);
                        while ($cur_error != 0) {
                            $i++;
                            $cur_group = next($groupsdata);
                            list($cur_error, $cur_text) = $this->register_in_agrp($cur_group->id,
                                                                                  $queue->user_id);
                        }
                        $class = $cur_error ? 'error': 'success';
                        $data = new stdClass();
                        $data->user_id = $queue->user_id;
                        $data->agrp_id = $queue->agrp_id;
                        $data->current_grp = $cur_group->id;
                        $data->current_text = $cur_text;
                        $movedtext = get_string('user_moved', 'grouptool', $data);
                        $returntext .= html_writer::tag('div', $movedtext, array('class'=>$class));
                        $cur_group->registered++;
                        $error = $error || $cur_error;
                        $attr = array('id'      => $queue->id,
                                      'user_id' => $queue->user_id,
                                      'agrp_id' => $queue->agrp_id);
                        $DB->delete_records('grouptool_queued', $attr);
                        if ($DB->record_exists('grouptool_queued', $attr)) {
                            $returntext .= "Could not delete!";
                        }
                    }
                }
            }
        }

        if (empty($returntext)) {
            $returntext = get_string('no_queues_to_resolve', 'grouptool');
            $error = false;
        }
        add_to_log($grouptool->course,
                'grouptool', 'resolve queue',
                "view.php?id=".$grouptool->id."&tab=overview",
                'resolve queue');
        return array($error, $returntext);
    }

    public function grpmarked($agrp_id, $userid=0) {
        global $SESSION;
        if(!isset($SESSION->grouptool)) {
            $SESSION->grouptool = new stdClass();
        }
        if(!isset($SESSION->grouptool->marks) || !is_array($SESSION->grouptool->marks)) {
            return false;
        }
        foreach($SESSION->grouptool->marks as $cur) {
            if($cur->agrp_id == $agrp_id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * view selfregistration-tab
     *
     * @global object $OUTPUT
     * @global object  $DB
     * @global object  $CFG
     * @global object  $USER
     * @global object  $PAGE
     */
    public function view_selfregistration() {
        global $OUTPUT, $DB, $CFG, $USER, $PAGE, $SESSION;

        $userid = $USER->id;

        $reg_open = ($this->grouptool->allow_reg
                        && (($this->grouptool->timedue == 0)
                                || (time() < $this->grouptool->timedue))
                        && (($this->grouptool->timeavailable == 0)
                                || (time() > $this->grouptool->timeavailable)));
        // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed!
            $hide_form = 0;
            $action = optional_param('action', 'reg', PARAM_ALPHA);
            if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                list($error, $message) = $this->unregister_from_agrp($agrpid, $USER->id);
            } else if ($action == 'reg') {
                require_capability('mod/grouptool:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Register user and get feedback!
                list($error, $message) = $this->register_in_agrp($agrpid, $USER->id);
            } else if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                $mode = optional_param('mode', 'std', PARAM_ALPHA);
                list($error, $message) = $this->resolve_queues($mode);
                if ($error == -1) {
                    $error = true;
                }
            }
            if ($error === true) {
                echo $OUTPUT->notification($message, 'notifyproblem');
            } else {
                echo $OUTPUT->notification($message, 'notifysuccess');
            }
        } else if (data_submitted() && confirm_sesskey()) {

            // Display confirm-dialog!
            $hide_form = 1;
            $reg = optional_param_array('reg', null, PARAM_INT);
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

            $attr = array();
            if ($action == 'resolvequeues') {
                require_capability('mod/grouptool:register_students', $this->context);
                $mode = optional_param('mode', 'random', PARAM_ALPHA);
                list($error, $confirmmessage) = $this->resolve_queues($mode, true); // Try only!
            } else if ($action == 'unreg') {
                require_capability('mod/grouptool:register', $this->context);
                $attr['group'] = $agrpid;
                // Try only!
                list($error, $confirmmessage) = $this->unregister_from_agrp($agrpid,
                                                                            $USER->id, true);
            } else {
                require_capability('mod/grouptool:register', $this->context);
                $action = 'reg';
                $attr['group'] = $agrpid;
                // Try only!
                list($error, $confirmmessage) = $this->register_in_agrp($agrpid,
                                                                        $USER->id, true);
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
            echo $this->confirm($confirmmessage, $continue, $cancel);
        } else {
            $hide_form = 0;
        }

        if (empty($hide_form)) {
            // Show information.
            // General information first!
            $general_information = "";

            $reg_stat = $this->get_registration_stats($USER->id);
            $formcontent = "";
            if (!empty($this->grouptool->timedue) && (time() >= $this->grouptool->timedue) &&
                    has_capability('mod/grouptool:register_students', $this->context)) {
                if ($reg_stat->queued_users > 0) {
                    // Insert queue-resolving button!
                    $attr = array(
                            'type'=>'submit',
                            'name'=>'resolve_queues',
                            'value'=>'1');
                    $resolve_queue_button = html_writer::tag('button',
                            get_string('resolve_queue', 'grouptool'),
                            $attr);
                    $resolve_queue = html_writer::tag('div',
                                                      get_string('resolve_queue_title',
                                                                 'grouptool'),
                                                      array('class' => 'fitemtitle')).
                                     html_writer::tag('div', $resolve_queue_button,
                                                      array('class'=>'felement'));
                    $resolve_queue = html_writer::tag('div', $resolve_queue,
                                                      array('class'=>'fitem'));
                    $resolve_queue_legend = html_writer::tag('legend',
                                                             get_string('resolve_queue_legend',
                                                                        'grouptool'));
                    $formcontent .= html_writer::tag('fieldset', $resolve_queue_legend.
                            html_writer::tag('div', $resolve_queue,
                                    array('class'=>'fcontainer')),
                            array('class'=>'clearfix'));
                }
            }

            if (!empty($this->grouptool->use_size)) {
                $place_stats = $reg_stat->group_places.'&nbsp;'.get_string('total', 'grouptool');
            } else {
                $place_stats = '∞&nbsp;'.get_string('total', 'grouptool');
            }
            if (($reg_stat->free_places != null) && !empty($this->grouptool->use_size)) {
                $place_stats .= ' / '.$reg_stat->free_places.'&nbsp;'.
                                get_string('free', 'grouptool');
            } else {
                $place_stats .= ' / ∞&nbsp;'.get_string('free', 'grouptool');
            }
            if ($reg_stat->occupied_places != null) {
                $place_stats .= ' / '.$reg_stat->occupied_places.'&nbsp;'.
                                get_string('occupied', 'grouptool');
            }
            $registration_info = html_writer::tag('div', get_string('group_places', 'grouptool').
                                                         $OUTPUT->help_icon('group_places',
                                                                            'grouptool'),
                    array('class'=>'fitemtitle')).
                    html_writer::tag('div', $place_stats,
                            array('class'=>'felement'));
            $general_information .= html_writer::tag('div', $registration_info,
                                                     array('class'=>'fitem'));

            $registration_info = html_writer::tag('div', get_string('number_of_students',
                                                                    'grouptool'),
                                                  array('class'=>'fitemtitle')).
                                 html_writer::tag('div', $reg_stat->users,
                                                  array('class'=>'felement'));
            $general_information .= html_writer::tag('div', $registration_info,
                                                     array('class'=>'fitem'));

            if (($this->grouptool->allow_multiple &&
                    (count($reg_stat->registered) < $this->grouptool->choose_min))
                    || (!$this->grouptool->allow_multiple && !count($reg_stat->registered))) {
                if ($this->grouptool->allow_multiple) {
                    $missing = ($this->grouptool->choose_min-count($reg_stat->registered));
                    $string_label = ($missing > 1) ? 'registrations_missing'
                                                   : 'registration_missing';
                } else {
                    $missing = 1;
                    $string_label = 'registration_missing';
                }
                $missingtext = get_string($string_label, 'grouptool', $missing);
            } else {
                $missingtext = "";
            }

            if (!empty($reg_stat->registered)) {
                foreach ($reg_stat->registered as $registration) {
                    if (empty($registrations_cumulative)) {
                        $registrations_cumulative = $registration->grpname.
                                                    ' ('.$registration->rank.')';
                    } else {
                        $registrations_cumulative .= ', '.$registration->grpname.
                                                     ' ('.$registration->rank.')';
                    }
                }
                $registration_info = html_writer::tag('div', get_string('registrations',
                                                                        'grouptool'),
                                                      array('class'=>'fitemtitle')).
                                     html_writer::tag('div', html_writer::tag('div', $missingtext).
                                                             $registrations_cumulative,
                                                      array('class'=>'felement'));
                $general_information .= html_writer::tag('div', $registration_info,
                                                         array('class'=>'fitem'));
            } else {
                $registration_info = html_writer::tag('div', get_string('registrations',
                                                                        'grouptool'),
                                                      array('class'=>'fitemtitle')).
                                     html_writer::tag('div', html_writer::tag('div', $missingtext).
                                                             get_string('not_registered',
                                                                        'grouptool'),
                                                      array('class'=>'felement'));
                $general_information .= html_writer::tag('div', $registration_info,
                                                         array('class'=>'fitem'));
            }

            if (!empty($reg_stat->queued)) {
                foreach ($reg_stat->queued as $queue) {
                    if (empty($queues_cumulative)) {
                        $queues_cumulative = $queue->grpname.' ('.$queue->rank.')';
                    } else {
                        $queues_cumulative .= ', '.$queue->grpname.' ('.$queue->rank.')';
                    }
                }

                $registration_info = html_writer::tag('div', get_string('queues', 'grouptool'),
                        array('class'=>'fitemtitle')).
                        html_writer::tag('div', $queues_cumulative,
                                array('class'=>'felement'));
                $general_information .= html_writer::tag('div', $registration_info,
                                                         array('class'=>'fitem'));
            }

            if (!empty($this->grouptool->timeavailable)) {
                $timeavailable = html_writer::tag('div', get_string('availabledate', 'grouptool'),
                                                  array('class'=>'fitemtitle')).
                                 html_writer::tag('div',
                                                  userdate($this->grouptool->timeavailable,
                                                           get_string('strftimedatetime')),
                                                  array('class'=>'felement'));
                $general_information .= html_writer::tag('div', $timeavailable,
                                                         array('class'=>'fitem'));
            }

            $timedue = html_writer::tag('div', get_string('registrationdue', 'grouptool'),
                                        array('class'=>'fitemtitle'));
            if (!empty($this->grouptool->timedue)) {
                $timedue .= html_writer::tag('div',
                                             userdate($this->grouptool->timedue,
                                                      get_string('strftimedatetime')),
                                             array('class'=>'felement'));
            } else {
                $timedue .= html_writer::tag('div', get_string('noregistrationdue', 'grouptool'),
                                             array('class'=>'felement'));
            }
            $general_information .= html_writer::tag('div', $timedue, array('class'=>'fitem'));

            if (!empty($this->grouptool->allow_unreg)) {
                $general_information .= html_writer::tag('div', html_writer::tag('div',
                        get_string('unreg_is', 'grouptool'),
                        array('class'=>'fitemtitle')).
                        html_writer::tag('div',
                                get_string('allowed', 'grouptool'),
                                array('class'=>'felement')),
                        array('class'=>'fitem'));
            } else {
                $general_information .= html_writer::tag('div',
                                                         html_writer::tag('div',
                                                                          get_string('unreg_is',
                                                                                     'grouptool'),
                                                                          array('class'=>'fitemtitle')).
                                                         html_writer::tag('div',
                                                                          get_string('not_permitted',
                                                                                     'grouptool'),
                                                                          array('class'=>'felement')),
                                                         array('class'=>'fitem'));
            }

            if (!empty($this->grouptool->allow_multiple)) {
                $minmaxtitle = html_writer::tag('div',
                                                get_string('choose_minmax_title', 'grouptool'),
                                                array('class' => 'fitemtitle'));
                if ($this->grouptool->choose_min && $this->grouptool->choose_max) {
                    $data = array('min' => $this->grouptool->choose_min,
                                  'max' => $this->grouptool->choose_max);
                    $minmaxtext = html_writer::tag('div',
                                                   get_string('choose_min_max_text', 'grouptool',
                                                              $data),
                                                   array('class' => 'felement'));
                    $class = ' choose_min choose_max';
                } else if ($this->grouptool->choose_min) {
                    $minmaxtext = html_writer::tag('div',
                                                    get_string('choose_min_text', 'grouptool',
                                                               $this->grouptool->choose_min),
                                                    array('class '=> 'felement'));
                    $class = ' choose_min';
                } else if ($this->grouptool->choose_max) {
                    $minmaxtext = html_writer::tag('div',
                                                    get_string('choose_max_text', 'grouptool',
                                                               $this->grouptool->choose_max),
                                                    array('class' => 'felement'));
                    $class = ' choose_max';
                }
                $general_information .= html_writer::tag('div',
                                                         $minmaxtitle.$minmaxtext,
                                                         array('class'=>'fitem '.$class));
            }

            if (!empty($this->grouptool->use_queue)) {
                $general_information .= html_writer::tag('div', html_writer::tag('div',
                        get_string('queueing_is', 'grouptool'),
                        array('class'=>'fitemtitle')).
                        html_writer::tag('div',
                                get_string('active', 'grouptool'),
                                array('class'=>'felement')),
                        array('class'=>'fitem'));
            }

            $general_info_legend = html_writer::tag('legend', get_string('general_information',
                                                                         'grouptool'));
            if (has_capability('mod/grouptool:view_description', $this->context)) {
                $formcontent .= html_writer::tag('fieldset', $general_info_legend.
                        html_writer::tag('div', $general_information,
                                array('class'=>'fcontainer')),
                        array('class'=>'clearfix'));

                // Intro-text if set!
                if ($this->grouptool->intro) {
                    // Conditions to show the intro can change to look for own settings or whatever?
                    $intro = format_module_intro('grouptool', $this->grouptool, $this->cm->id);
                    $formcontent .= html_writer::tag('fieldset', html_writer::tag('legend',
                            get_string('intro', 'grouptool')).
                            html_writer::tag('div', $intro,
                                    array('class'=>'fcontainer')),
                            array('class'=>'clearfix'));
                }
            }

            $groups = $this->get_active_groups(true, true);

            // Student view!
            if (has_capability("mod/grouptool:view_groups", $this->context)) {
                // Prepare formular-content for registration-action!
                foreach ($groups as $key => $group) {
                    $registered = count($group->registered);
                    $grpsize = ($this->grouptool->use_size) ? $group->grpsize : "∞";
                    $grouphtml = html_writer::tag('span', get_string('registered', 'grouptool').
                                                          ": ".$registered."/".$grpsize,
                                                  array('class'=>'fillratio'));
                    if ($this->grouptool->use_queue) {
                        $queued = count($group->queued);
                        $grouphtml .= html_writer::tag('span', get_string('queued', 'grouptool').
                                                               " ".$queued,
                                                       array('class'=>'queued'));
                    }
                    if ($this->grouptool->show_members) {
                        $grouphtml .= $this->render_members_link($group->agrp_id, $group->name);
                    }
                    if (!empty($group->registered)) {
                        $reg_rank = $this->get_rank_in_queue($group->registered, $USER->id);
                    } else {
                        $reg_rank = false;
                    }
                    if (!empty($group->queued)) {
                        $queue_rank = $this->get_rank_in_queue($group->queued, $USER->id);
                    } else {
                        $queue_rank = false;
                    }
                    $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptool_id = ?", array($this->grouptool->id));
                    list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
                    array_unshift($params, $userid);
                    $userregs = $DB->count_records_select('grouptool_registered', "user_id = ? AND agrp_id ".$agrpsql, $params);
                    $userqueues = $DB->count_records_select('grouptool_queued', "user_id = ? AND agrp_id ".$agrpsql, $params);
                    $max = $this->grouptool->allow_multiple ? $this->grouptool->choose_max : 1;
                    $min = $this->grouptool->allow_multiple ? $this->grouptool->choose_min : 0;
                    if (!empty($group->registered)
                        && $this->get_rank_in_queue($group->registered, $userid) != false) {
                        // User is allready registered --> unreg button!
                        if ($this->grouptool->allow_unreg) {
                            $label = get_string('unreg', 'grouptool');
                            $button_attr = array('type'=>'submit',
                                    'name'=>'unreg['.$group->agrp_id.']',
                                    'value'=>$group->agrp_id,
                                    'class'=>'unregbutton');
                            if ($reg_open && ($userregs+$userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $button_attr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('registered_on_rank',
                                                                  'grouptool', $reg_rank),
                                                       array('class'=>'rank'));
                    } else if (!empty($group->queued)
                        && $this->get_rank_in_queue($group->queued, $userid) != false) {
                        // We're sorry, but user's already queued in this group!
                        if ($this->grouptool->allow_unreg) {
                            $label = get_string('unqueue', 'grouptool');
                            $button_attr = array('type'=>'submit',
                                    'name'=>'unreg['.$group->agrp_id.']',
                                    'value'=>$group->agrp_id,
                                    'class'=>'unregbutton');
                            if ($reg_open && ($userregs+$userqueues > $min)) {
                                $grouphtml .= html_writer::tag('button', $label, $button_attr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('queued_on_rank',
                                                                  'grouptool', $queue_rank),
                                                       array('class'=>'rank'));
                    } else if ($this->grpmarked($group->agrp_id)) {
                        $grouphtml .= html_writer::tag('span',
                                                       get_string('grp_marked', 'grouptool'),
                                                       array('class'=>'rank'));
                    } else if ($this->grouptool->allow_unreg
                               && (($userqueues == 1 && $userregs == $max-1)
                                   || ($userregs+$userqueues == 1 && $max == 1))) {
                        if (!$this->grouptool->use_size
                            || (count($group->registered) < $group->grpsize)
                            || ($this->grouptool->use_queue
                                && (count($group->registered) >= $group->grpsize)
                                && $userqueues < $this->grouptool->queues_max)) {
                            //groupchange!
                            $label = get_string('change_group', 'grouptool');
                            if ($this->grouptool->use_size 
                                && count($group->registered) >= $group->grpsize) {
                                    $label .= ' ('.get_string('queue', 'grouptool').')';
                            }
                            $button_attr = array('type'=>'submit',
                                                 'name'=>'reg['.$group->agrp_id.']',
                                                 'value'=>$group->agrp_id,
                                                 'class'=>'regbutton');
                            $grouphtml .= html_writer::tag('button', $label, $button_attr);
                        } else if ($this->grouptool->use_queue
                                   && (count($group->registered) >= $group->grpsize)
                                   && $userqueues >= $this->grouptool->queues_max) {
                            //too many queues
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('max_queues_reached',
                                                                      'grouptool'),
                                                                      array('class'=>'rank'));
                        } else {
                            //group is full!
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('fullgroup',
                                                                      'grouptool'),
                                                                      array('class'=>'rank'));
                        }
                    } else if ($userregs+$userqueues < $max) {
                        if (!$this->grouptool->use_size || (count($group->registered) < $group->grpsize)) {
                            //register button
                            $label = get_string('register', 'grouptool');
                            $button_attr = array('type'=>'submit',
                                                 'name'=>'reg['.$group->agrp_id.']',
                                                 'value'=>$group->agrp_id,
                                                 'class'=>'regbutton');
                            $grouphtml .= html_writer::tag('button', $label, $button_attr);
                        } else if ($this->grouptool->use_queue) {
                            if($userqueues < $this->grouptool->queues_max) {
                                //queue button
                                $label = get_string('queue', 'grouptool');
                                $button_attr = array('type'=>'submit',
                                        'name'=>'reg['.$group->agrp_id.']',
                                        'value'=>$group->agrp_id,
                                        'class'=>'queuebutton');
                                $grouphtml .= html_writer::tag('button', $label,
                                                               $button_attr);
                            } else {
                                //too many queues
                                $grouphtml .= html_writer::tag('div',
                                                               get_string('max_queues_reached',
                                                                          'grouptool'),
                                                                          array('class'=>'rank'));
                            }
                        } else {
                            //group is full!
                            $grouphtml .= html_writer::tag('div',
                                                           get_string('fullgroup',
                                                                      'grouptool'),
                                                                      array('class'=>'rank'));
                        }
                    } else {
                        $grouphtml .= html_writer::tag('div',
                                                       get_string('max_regs_reached',
                                                                  'grouptool'),
                                                                  array('class'=>'rank'));
                    }
                    $status = "";
                    if ($reg_rank !== false) {
                        $status = 'registered';
                    } else if ($queue_rank !== false) {
                        $status = 'queued';
                    } else if (($this->grouptool->use_size) && ($registered >= $group->grpsize)) {
                        $status = 'full';
                    } else {
                        $status = 'empty';
                    }
                    $formcontent .= html_writer::tag('fieldset',
                                                     html_writer::tag('legend',
                                                                      $group->name,
                                                                      array('class'=>'groupname')).
                                                     html_writer::tag('div',
                                                                      $grouphtml,
                                                                      array('class'=>'fcontainer clearfix')),
                                                     array('class'=>'clearfix group '.$status));
                }
            }

            /*
             * we need a new moodle_url-Object because
             * $PAGE->url->param('sesskey', sesskey());
             * won't set sesskey param in $PAGE->url?!?
             */
            $url = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
            $formcontent = html_writer::tag('div', html_writer::input_hidden_params($url).
                                                   $formcontent, array('class'=>'clearfix'));
            $formattr = array(
                    'method' => 'post',
                    'action' => $url->out_omit_querystring(),
                    'id'   => 'registration_form',
                    'class'  => 'mform');
            echo html_writer::tag('form', $formcontent, $formattr);
        }
    }

    /**
     * import users into a certain moodle-group and enrole them if not allready enroled
     *
     * @global object $DB
     * @global object $OUTPUT
     * @global object $CFG
     * @global object $PAGE
     * @param int $group id of group to import into
     * @param object $data from form in import tab (textfield with idnumbers and group-selection)
     * @param bool $preview_only optional preview only, don't take any action
     * @return array ($error, $message)
     */
    public function import($group, $data, $forceregistration = false, $preview_only = false) {
        global $DB, $OUTPUT, $CFG, $PAGE;

        $message = "";
        $error = false;
        $users = preg_split("/[ ,;\t\n\r]+/", $data);
        //prevent selection of all users if one of the above defined characters are in the beginning
        foreach($users as $key => $user) {
            if(empty($user)) {
                unset($users[$key]);
            }
        }
        $groupinfo = groups_get_group($group);
        $imported = array();
        $columns = $DB->get_columns('user');
        if (empty($field) || !key_exists($field, $columns)) {
            $field = 'idnumber';
        }
        $agrp = $DB->get_field('grouptool_agrps', 'id', array('grouptool_id'=>$this->grouptool->id,
                                                              'group_id' => $group), IGNORE_MISSING);
        if(!$DB->record_exists('grouptool_agrps', array('grouptool_id' => $this->grouptool->id,
                                                        'group_id' => $group,
                                                        'active' => 1))) {
            $message .= $OUTPUT->notification(get_string('import_in_inactive_group_warning',
                                                         'grouptool', $groupinfo->name),
                                              array('notifyproblem'));
        }
        $sql = '     SELECT agrps.id as id, agrps.group_id as grpid, COUNT(regs.id) as regs,
                            grptl.use_individual as indi, grptl.grpsize as globalsize, agrps.grpsize as size,
                            grptl.name as instancename
                       FROM {grouptool_agrps} as agrps
                       JOIN {grouptool} as grptl ON agrps.grouptool_id = grptl.id
                  LEFT JOIN {grouptool_registered} as regs ON agrps.id = regs.agrp_id
                      WHERE agrps.group_id = :grpid
                        AND grptl.use_size = 1
                   GROUP BY agrps.id
                   ';
        $agrps = $DB->get_records_sql($sql, array('grpid'=>$group));
        $usercnt = count($users);
        foreach($agrps as $cur) {
            if($cur->indi) {
                if($cur->regs+$usercnt > $cur->size) {
                    $message .= html_writer::tag('div',
                                                 $OUTPUT->notification(get_string('overflowwarning',
                                                                                  'grouptool', $cur),
                                                                       'notifyproblem'));
                }
            } else {
                if($cur->regs+$usercnt > $cur->globalsize) {
                    $message .= html_writer::tag('div',
                                                 $OUTPUT->notification(get_string('overflowwarning',
                                                                                  'grouptool', $cur),
                                                                       'notifyproblem'));
                }
            }
        }
        foreach ($users as $user) {
            $sql = 'SELECT * FROM {user} WHERE '.$DB->sql_like($field, ':userpattern');
            $userinfo = $DB->get_records_sql($sql, array('userpattern'=>'%'.$user));

            if (empty($userinfo)) {
                $message .= html_writer::tag('div',
                                             $OUTPUT->notification(get_string('user_not_found',
                                                                              'grouptool', $user),
                                                                   'notifyproblem'));
                $error = true;
            } else if (count($userinfo) > 1) {
                foreach ($userinfo as $current_user) {
                    if (empty($text)) {
                        $text = get_string('found_multiple', 'grouptool').' '.
                                fullname($current_user).' ('.$current_user->idnumber.')';
                    } else {
                        $text .= ', '.fullname($current_user).' ('.$current_user->idnumber.')';
                    }
                }
                $message .= html_writer::tag('div', $OUTPUT->notification($text, 'notifyproblem'));
                $error = true;
            } else {
                $userinfo = reset($userinfo);
                if (!is_enrolled($this->context, $userinfo->id)) {
                    /*
                     * if user's not enrolled already we force manual enrollment in course,
                     * so we can add the user to the group
                     */
                    require_once($CFG->dirroot.'/enrol/manual/locallib.php');
                    require_once($CFG->libdir.'/accesslib.php');
                    if (!$enrol_manual = enrol_get_plugin('manual')) {
                        throw new coding_exception('Can not instantiate enrol_manual');
                    }
                    if (!$instance = $DB->get_record('enrol', array('courseid'=>$this->course->id,
                                                                    'enrol'=>'manual'),
                                                     '*', IGNORE_MISSING)) {
                        if ($instanceid = $enrol_manual->add_default_instance($this->course)) {
                            $instance = $DB->get_record('enrol',
                                                        array('courseid' => $this->course->id,
                                                              'enrol'    => 'manual'), '*',
                                                        MUST_EXIST);
                        }
                    }
                    if ($instance != false) {
                        $archroles = get_archetype_roles('student');
                        $archrole = array_shift($archroles);
                        $enrol_manual->enrol_user($instance, $userinfo->id, $archrole->id, time());
                    } else {
                        $message .= html_writer::tag('div',
                                                     $OUTPUT->notification(get_string('cant_enrol',
                                                                                      'grouptool'),
                                                     'notifyproblem'));
                    }
                }
                $data = array(
                        'id' => $userinfo->id,
                        'idnumber' => $userinfo->idnumber,
                        'fullname' => fullname($userinfo),
                        'groupname' => $groupinfo->name);
                if (!$preview_only && $userinfo) {
                    $attr = array('class'=>'notifysuccess');
                    if (!groups_add_member($group, $userinfo->id)) {
                        $error = true;
                        $notifiication = $OUTPUT->notification(get_string('import_user_problem',
                                                                          'grouptool', $data),
                                                               'notifyproblem');
                        $message .= html_writer::tag('div', $notification,
                                                     array('class'=>'error'));
                    } else {
                        $imported[] = $userinfo->id;
                        $message .= html_writer::tag('div', get_string('import_user', 'grouptool',
                                                                       $data), $attr);
                    }
                    if ($forceregistration && empty($agrp)) {
                        $newgrpdata = $DB->get_record_sql('SELECT MAX(sort_order), MAX(grpsize)
                                                           FROM grouptool_agrps
                                                           WHERE grouptool_id = ?',
                                                          array($this->grouptool->id));
                        //insert agrp-entry for this group (even if it's not active)
                        $agrp = $DB->insert_record('grouptool_agrps', array('grouptool_id' => $this->grouptool->id,
                                                                    'group_id' => $group,
                                                                    'active' => 0,
                                                                    'sort_order' => $newgrpdata->sort_order+1,
                                                                    'grpsize' => $newgrpdata->grpsize));
                    }
                    if ($forceregistration && !empty($agrp)) {
                        $this->register_in_agrp($agrp, $userinfo->id);
                    }
                } else if ($userinfo) {
                    $attr = array('class'=>'prevsuccess');
                    $message .= html_writer::tag('div', get_string('import_user_prev', 'grouptool',
                                                                   $data), $attr);
                }
            }
        }
        if (!$preview_only) {
            add_to_log($this->grouptool->course,
                    'grouptool', 'import',
                    "view.php?id=".$this->grouptool->id."&tab=overview&groupid=".$group,
                    'import users:'.implode("|", $imported).' in group:'.$group);
        }
        return array($error, $message);
    }

    /**
     * view import-tab
     *
     * @global object $OUTPUT
     * @global object  $DB
     * @global object  $CFG
     * @global object  $USER
     * @global object  $PAGE
     */
    public function view_import() {
        global $PAGE, $OUTPUT;
        require_capability('mod/grouptool:register_students', $this->context);

        $id = $this->cm->id;
        $form = new view_import_form(null, array('id' => $id));

        if (optional_param('confirm', 0, PARAM_BOOL)) {
            $group = required_param('group', PARAM_INT);
            $data = required_param('data', PARAM_RAW);
            $forceregistration = optional_param('forceregistration', 0, PARAM_BOOL);
            if (!empty($data)) {
                $data = unserialize($data);
            }
            list($error, $message) = $this->import($group, $data, $forceregistration);

            if (!empty($error)) {
                $message = $OUTPUT->notification(get_string('ignored_not_found_users', 'grouptool'),
                        'notifyproblem').
                        html_writer::empty_tag('br').
                        $message;
            }
            echo $OUTPUT->box($message, 'generalbox centered');
        }

        if ($fromform = $form->get_data()) {
            // Display confirm message - so we "try" only!
            list($error, $confirmmessage) = $this->import($fromform->group, $fromform->data,
                                                          $fromform->forceregistration, true);

            $attr = array(
                    'confirm'           => '1',
                    'group'             => $fromform->group,
                    'data'              => serialize($fromform->data),
                    'forceregistration' => $fromform->forceregistration);

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if ($error) {
                $confirmmessage = $OUTPUT->notification(get_string('ignoring_not_found_users',
                                                                   'grouptool'),
                                                        'notifyproblem').
                                  html_writer::empty_tag('br').
                                  $confirmmessage;
            }
            echo $OUTPUT->heading(get_string('preview', 'grouptool'), 2, 'centered').
                 $this->confirm($confirmmessage, $continue, $cancel);

        } else {
            $form->display();
        }

    }

    /**
     * get all data necessary for displaying/exporting group-overview table
     *
     * @global object $OUTPUT
     * @global object $CFG
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param bool $data_only optional return object with raw data not html-fragment-string
     * @return string|object either html-fragment representing table or raw data as object
     */
    public function group_overview_table($groupingid = 0, $groupid = 0, $data_only = false) {
        global $OUTPUT, $CFG, $DB;
        if (!$data_only) {
            $return = "";
            $downloadurl = new moodle_url('/mod/grouptool/download.php', array('id'=>$this->cm->id,
                    'groupingid'=>$groupingid,
                    'groupid'=>$groupid,
                    'sesskey'=>sesskey(),
                    'tab'=>'overview'));
        } else {
            $return = array();
        }

        $agrps = $this->get_active_groups(true, true, 0, $groupid, $groupingid);
        $groupids = array_keys($agrps);
        $groupinfo = groups_get_all_groups($this->grouptool->course);
        $userinfo = get_enrolled_users($this->context);
        $sync_status = $this->get_sync_status();
        $context = context_module::instance($this->cm->id);
        if ((!$data_only && count($agrps)) && has_capability('mod/grouptool:export', $context)) {
            // Global-downloadlinks!
            $txturl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_TXT));
            $xlsurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_XLS));
            $pdfurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_PDF));
            $odsurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_ODS));
            $downloadlinks = html_writer::tag('span', get_string('downloadall').":",
                                              array('class'=>'title')).'&nbsp;'.
                             html_writer::link($txturl, '.TXT').'&nbsp;'.
                             html_writer::link($xlsurl, '.XLS').'&nbsp;'.
                             html_writer::link($pdfurl, '.PDF').'&nbsp;'.
                             html_writer::link($odsurl, '.ODS');
            $return .= html_writer::tag('div', $downloadlinks, array('class'=>'download all'));
        }
        foreach ($agrps as $agrp) {
            if (!$data_only) {
                $groupdata = "";
                $groupinfos = $OUTPUT->heading($groupinfo[$agrp->id]->name, 3);
            } else {
                $groupdata = new stdClass();
                $groupdata->name = $groupinfo[$agrp->id]->name;
            }

            if (!empty($this->grouptool->use_size)) {
                if (!empty($this->grouptool->use_individual) && !empty($agrp->grpsize)) {
                    $size = $agrp->grpsize;
                    $free = $agrp->grpsize - count($agrp->registered);
                } else {
                    $size = !empty($this->grouptool->grpsize) ?
                    $this->grouptool->grpsize :
                    $CFG->grouptool_grpsize;
                    $free = ($size - count($agrp->registered));

                }
            } else {
                $size = "∞";
                $free = '∞';
            }
            if (!$data_only) {
                $groupinfos .= html_writer::tag('span', get_string('total', 'grouptool').' '.$size,
                        array('class'=>'groupsize'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('registered', 'grouptool').
                                                              ' '.count($agrp->registered),
                                                      array('class'=>'registered'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('queued', 'grouptool').' '.
                                                              count($agrp->queued),
                                                      array('class'=>'queued'));
                $groupinfos .= ' / '.html_writer::tag('span', get_string('free', 'grouptool').' '.
                                                              $free, array('class'=>'free'));

                $groupdata .= html_writer::tag('div', $groupinfos, array('class'=>'groupinfo'));

                $table = new html_table();
                $table->attributes['class'] = 'overviewtable';
                $table->align = array('center', null, null, null);
                $headcells = array();
                $headcells[] = new html_table_cell(get_string('status', 'grouptool').
                                                   $OUTPUT->help_icon('status', 'grouptool'));
                $headcells[] = new html_table_cell(get_string('fullname'));
                $headcells[] = new html_table_cell(get_string('idnumber'));
                $headcells[] = new html_table_cell(get_string('email'));
                $table->head = $headcells;
                $rows = array();
            } else {
                $groupdata->total = $size;
                $groupdata->registered = count($agrp->registered);
                $groupdata->queued = count($agrp->queued);
                $groupdata->free = $free;
                $groupdata->reg_data = array();
                $groupdata->queue_data = array();
            }

            if (count($agrp->registered) >= 1) {
                foreach ($agrp->registered as $reg_entry) {
                    if(!array_key_exists($reg_entry->user_id, $userinfo)) {
                        $userinfo[$reg_entry->user_id] = $DB->get_record('user', array('id'=>$reg_entry->user_id));
                    }
                    if (!$data_only) {
                        $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                $reg_entry->user_id.'&course='.$this->course->id,
                                'title' => fullname($userinfo[$reg_entry->user_id]));
                        $userlink = html_writer::tag('a', fullname($userinfo[$reg_entry->user_id]),
                                                     $userlinkattr);
                        $userlink = new html_table_cell($userlink);
                        if (!empty($userinfo[$reg_entry->user_id]->idnumber)) {
                            $idnumber = html_writer::tag('span',
                                                         $userinfo[$reg_entry->user_id]->idnumber,
                                                         array('class'=>'idnumber'));
                        } else {
                            $idnumber = html_writer::tag('span', '-', array('class'=>'idnumber'));
                        }
                        $idnumber = new html_table_cell($idnumber);
                        if (!empty($userinfo[$reg_entry->user_id]->email)) {
                            $email = html_writer::tag('span', $userinfo[$reg_entry->user_id]->email,
                                                      array('class'=>'email'));
                        } else {
                            $email = html_writer::tag('span', '-', array('class'=>'email'));
                        }
                        $email = new html_table_cell($email);
                        if (key_exists($reg_entry->user_id, $agrp->moodle_members)) {
                            $status = new html_table_cell("✔");
                        } else {
                            $status = new html_table_cell("+");
                        }
                        $rows[] = new html_table_row(array($status, $userlink, $idnumber, $email));
                    } else {
                        $row = array();
                        $row['name'] = fullname($userinfo[$reg_entry->user_id]);
                        if (!empty($userinfo[$reg_entry->user_id]->idnumber)) {
                            $row['idnumber'] = $userinfo[$reg_entry->user_id]->idnumber;
                        } else {
                            $row['idnumber'] = '-';
                        }
                        if (!empty($userinfo[$reg_entry->user_id]->email)) {
                            $row['email'] = $userinfo[$reg_entry->user_id]->email;
                        } else {
                            $row['email'] = '-';
                        }
                        if (key_exists($reg_entry->user_id, $agrp->moodle_members)) {
                            $row['status'] = "✔";
                        } else {
                            $row['status'] = "+";
                        }
                        $groupdata->reg_data[] = $row;
                    }
                }
            } else if (count($agrp->moodle_members) == 0) {
                if (!$data_only) {
                    $cell = new html_table_cell(get_string('no_registrations', 'grouptool'));
                    $cell->attributes['class'] = 'no_registrations';
                    $cell->colspan = count($headcells);
                    $rows[] = new html_table_row(array($cell));
                }
            }

            if (count($agrp->moodle_members) >= 1) {
                foreach ($agrp->moodle_members as $memberid => $member) {
                    if(!array_key_exists($memberid, $userinfo)) {
                        $userinfo[$memberid] = $DB->get_record('user', array('id'=>$memberid));
                    }
                    if ((count($agrp->registered) >= 1)
                             && $this->get_rank_in_queue($agrp->registered, $memberid)) {
                        continue;
                    } else {
                        if (!$data_only) {
                            $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                    $memberid.'&course='.$this->course->id,
                                    'title' => fullname($userinfo[$memberid]));
                            $userlink = html_writer::tag('a', fullname($userinfo[$memberid]),
                                                         $userlinkattr);
                            $userlink = new html_table_cell($userlink);
                            if (!empty($userinfo[$memberid]->idnumber)) {
                                $idnumber = html_writer::tag('span', $userinfo[$memberid]->idnumber,
                                                             array('class'=>'idnumber'));
                            } else {
                                $idnumber = html_writer::tag('span', '-',
                                                             array('class'=>'idnumber'));
                            }
                            $idnumber = new html_table_cell($idnumber);
                            if (!empty($userinfo[$memberid]->email)) {
                                $email = html_writer::tag('span', $userinfo[$memberid]->email,
                                                          array('class'=>'email'));
                            } else {
                                $email = html_writer::tag('span', '-', array('class'=>'email'));
                            }
                            $email = new html_table_cell($email);
                            $status = new html_table_cell("?");
                            $rows[] = new html_table_row(array($status, $userlink, $idnumber,
                                                               $email));
                        } else {
                            $row = array();
                            $row['name'] = fullname($userinfo[$memberid]);
                            if (!empty($userinfo[$memberid]->idnumber)) {
                                $row['idnumber'] = $userinfo[$memberid]->idnumber;
                            } else {
                                $row['idnumber'] = '-';
                            }
                            if (!empty($userinfo[$memberid]->email)) {
                                $row['email'] = $userinfo[$memberid]->email;
                            } else {
                                $row['email'] = '-';
                            }
                            $groupdata->mreg_data[] = $row;
                        }
                    }
                }
            }

            if (count($agrp->queued) >= 1) {
                foreach ($agrp->queued as $queue_entry) {
                    if(!array_key_exists($queue_entry->user_id, $userinfo)) {
                        $userinfo[$queue_entry->user_id] = $DB->get_record('user', array('id'=>$queue_entry->user_id));
                    }
                    $queue_entry->rank = $this->get_rank_in_queue($agrp->queued,
                                                                  $queue_entry->user_id);
                    if (!$data_only) {
                        $rank = new html_table_cell($queue_entry->rank);
                        $rank->attributes['class'] = 'rank';
                        $userlinkattr = array('href' => $CFG->wwwroot.'/user/view.php?id='.
                                $queue_entry->user_id.'&course='.$this->course->id,
                                'title' => fullname($userinfo[$queue_entry->user_id]));
                        $userlink = html_writer::tag('a',
                                                     fullname($userinfo[$queue_entry->user_id]),
                                                     $userlinkattr);
                        $userlink = new html_table_cell($userlink);
                        $userlink->attributes['class'] = 'userlink';
                        $idnumber = new html_table_cell($userinfo[$queue_entry->user_id]->idnumber);
                        $idnumber->attributes['class'] = 'idnumber';
                        $email = new html_table_cell($userinfo[$queue_entry->user_id]->email);
                        $email->attributes['class'] = 'email';
                        $row = new html_table_row(array($rank, $userlink, $idnumber, $email));
                        $row->attributes['class'] = 'queueentry';
                        $rows[] = $row;
                    } else {
                        $row = array();
                        $row['rank'] = $queue_entry->rank;
                        $row['name'] = fullname($userinfo[$queue_entry->user_id]);
                        if (!empty($userinfo[$queue_entry->user_id]->idnumber)) {
                            $row['idnumber'] = $userinfo[$queue_entry->user_id]->idnumber;
                        } else {
                            $row['idnumber'] = '-';
                        }
                        if (!empty($userinfo[$queue_entry->user_id]->email)) {
                            $row['email'] = $userinfo[$queue_entry->user_id]->email;
                        } else {
                            $row['email'] = '-';
                        }
                        $groupdata->queue_data[] = $row;

                    }
                }
            } else {
                if (!$data_only) {
                    $cell = new html_table_cell(get_string('nobody_queued', 'grouptool'));
                    $cell->attributes['class'] = 'no_queues';
                    $cell->colspan = count($headcells);
                    $row = new html_table_row(array($cell));
                    $row->attributes['class'] = 'queueentry queue';
                    $rows[] = $row;
                }
            }
            if (!$data_only) {
                $table->data = $rows;
                $groupdata .= html_writer::table($table);
                // Group-downloadlinks!
                if (((count($agrp->queued) > 0) || (count($agrp->registered) > 0))
                    && has_capability('mod/grouptool:export', $context)) {
                    $urltxt = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_TXT));
                    $urlxls = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_XLS));
                    $urlpdf = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_PDF));
                    $urlods = new moodle_url($downloadurl,
                                             array('groupid' => $groupinfo[$agrp->id]->id,
                                                   'format'  => GROUPTOOL_ODS));

                    $downloadlinks = html_writer::tag('span', get_string('download').":",
                                                      array('class'=>'title')).'&nbsp;'.
                                     html_writer::link($urltxt, '.TXT').'&nbsp;'.
                                     html_writer::link($urlxls, '.XLS').'&nbsp;'.
                                     html_writer::link($urlpdf, '.PDF').'&nbsp;'.
                                     html_writer::link($urlods, '.ODS');
                    $groupdata .= html_writer::tag('div', $downloadlinks,
                                                   array('class'=>'download group'));
                }
                if ($sync_status[1][$agrp->agrp_id]->status == GROUPTOOL_UPTODATE) {
                    $return .= $OUTPUT->box($groupdata, 'generalbox groupcontainer uptodate');
                } else {
                    $return .= $OUTPUT->box($groupdata, 'generalbox groupcontainer outdated');
                }
            } else {
                $return[] = $groupdata;
            }
        }

        if (count($agrps) == 0) {
            $box_content = $OUTPUT->notification(get_string('no_data_to_display', 'grouptool'),
                                                 'notifyproblem');
            $return .= $OUTPUT->box($box_content, 'generalbox centered');
        }
        return $return;
    }

    /**
     * outputs generated pdf-file for overview (forces download)
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_pdf($groupid=0, $groupingid=0) {
        global $USER;
        require_once('./grouptool_pdf.php');

        $data = $this->group_overview_table($groupingid, $groupid, true);

        $pdf = new grouptool_pdf();

        // Set orientation (P/L)!
        $pdf->setPageOrientation("P");

        // Set document information!
        $pdf->SetCreator('TUWEL');
        $pdf->SetAuthor($USER->firstname . " " . $USER->lastname);

        $coursename = $this->course->fullname;
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

        $pdf->set_overview_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                    $viewname);

        // Set header/footer!
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);

        $textsize = optional_param('textsize', 1, PARAM_INT);
        switch ($textsize){
            case "0":
                $pdf->SetFontSize(8);
                break;
            case "1":
                $pdf->SetFontSize(10);
                break;
            case "2":
                $pdf->SetFontSize(12);
                break;
        }

        // Set margins!
        if (1) {
            $pdf->SetMargins(10, 30, 10); // Left Top Right.
        } else {
            $pdf->SetMargins(10, 10, 10);
        }
        // Set default monospaced font!
        $pdf->SetDefaultMonospacedFont(/*PDF_FONT_MONOSPACED*/'freeserif');

        // Set margins!
        $pdf->SetHeaderMargin(7);

        // Set auto page breaks!
        $pdf->SetAutoPageBreak(true, /*PDF_MARGIN_BOTTOM*/10);

        // Set image scale factor
        $pdf->setImageScale(/*PDF_IMAGE_SCALE_RATIO*/1);

        /*
         * ---------------------------------------------------------
         */

        // Set font!
        $pdf->SetFont('freeserif', '');
        $pdf->addPage('P', 'A4', false, false);
        if (count($data) > 0) {

            foreach ($data as $group) {
                $groupname = $group->name;
                $groupinfo = get_string('total').' '.$group->total.' / '.
                             get_string('registered', 'grouptool').' '.$group->registered.' / '.
                             get_string('queued', 'grouptool').' '.$group->queued.' / '.
                             get_string('free', 'grouptool').' '.$group->free;
                $reg_data = $group->reg_data;
                $queue_data = $group->queue_data;
                $mreg_data = $group->mreg_data;
                $pdf->add_grp_overview($groupname, $groupinfo, $reg_data, $queue_data, $mreg_data);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'B', 'L', false, 1, null, null, true, 1,
                                true, false, $pdf->getLastH(), 'M', true);
                $pdf->MultiCell(0, $pdf->getLastH(), '', 'T', 'L', false, 1, null, null, true, 1,
                                true, false, $pdf->getLastH(), 'M', true);
            }
            $pdf->SetFontSize(8);
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('status', 'grouptool'), '', 'L', false,
                            1, null, null, true, 1, true, false, $pdf->getLastH(), 'M', true);
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $pdf->MultiCell(0, $pdf->getLastH(), strip_tags($legendline), '', 'L', false, 1,
                                null, null, true, 1, true, false, $pdf->getLastH(), 'M', true);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'), 'B',
                            'LRTB', false, 1, null, null, true, 1, true, false, $pdf->getLastH(),
                            'M', true);
        }

        ob_clean();
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
        $pdf->Output($filename.'.pdf', 'D');
        exit();
    }

    /**
     * returns raw data for overview
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @return object raw data
     */
    public function download_overview_raw($groupid=0, $groupingid=0) {
        return $this->group_overview_table($groupid, $groupingid, true);
    }

    /**
     * outputs generated txt-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_txt($groupid=0, $groupingid=0) {
        ob_start();
        $return = "";
        $lines = array();
        $groups = $this->group_overview_table($groupingid, $groupid, true);
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
                if ($group->registered > 0) {
                    $lines[] = "\t".get_string('registrations', 'grouptool');
                    foreach ($group->reg_data as $reg) {
                        $lines[] = "\t\t".$reg['status']."\t".$reg['name']."\t".$reg['idnumber'].
                                   "\t".$reg['email'];
                    }
                } else if (count($group->mreg_data) == 0) {
                    $lines[] = "\t\t--".get_string('no_registrations', 'grouptool')."--";
                }
                if (count($group->mreg_data) >= 1) {
                    foreach ($group->mreg_data as $mreg) {
                        $lines[] = "\t\t?\t".$mreg['name']."\t".$mreg['idnumber']."\t".
                                   $mreg['email'];
                    }
                }
                if ($group->queued > 0) {
                    $lines[] = "\t".get_string('queue', 'grouptool');
                    foreach ($group->queue_data as $queue) {
                        $lines[] = "\t\t".$queue['rank']."\t".$queue['name']."\t".
                                   $queue['idnumber']."\t".$queue['email'];
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

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

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
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header('Content-Disposition: attachment; filename="'.$filename.'.txt"; filename*="'.
               rawurlencode($filename).'.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * fills workbook (either XLS or ODS) with data
     *
     * @param MoodleExcelWorkbook $workbook workbook to put data into
     * @param array $groups which groups from whom to include data
     */
    private function overview_fill_workbook(&$workbook, $groups) {
        if (count($groups) > 0) {
            if (is_a($workbook, 'MoodleExcelWorkbook')) {
                $column_width = array( 7, 22, 14, 17); // Unit: mm!
            } else {
                $column_width = array(54, 159, 103, 124); // Unit: px!
            }
            if (count($groups)>1) {
                // General information? unused at the moment!
                $all_groups_worksheet =& $workbook->add_worksheet(get_string('all'));
                // The standard column widths: 7 - 22 - 14 - 17!
                $all_groups_worksheet->set_column(0, 0, $column_width[0]);
                $all_groups_worksheet->set_column(1, 1, $column_width[1]);
                $all_groups_worksheet->set_column(2, 2, $column_width[2]);
                $all_groups_worksheet->set_column(3, 3, $column_width[3]);
                $general_sheet = true;
            } else {
                $general_sheet = false;
            }

            $legend_worksheet =& $workbook->add_worksheet(get_string('status', 'grouptool').' '.
                                                          get_string('help'));
            $legend_worksheet->write_string(0, 0, get_string('status', 'grouptool').' '.
                                                  get_string('help'));
            $line = 1;
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                if (strstr($legendline, "</span>")) {
                    $lineelements = explode("</span>", $legendline);
                    $legend_worksheet->write_string($line, 0, strip_tags($lineelements[0]));
                    $legend_worksheet->write_string($line, 1, strip_tags($lineelements[1]));
                    $line++;
                }
            }

            // Add content for all groups!
            $group_worksheets = array();

            // Prepare formats!
            $headline_prop = array(    'size' => 14,
                    'bold' => 1,
                    'align' => 'center');
            $headline_format =& $workbook->add_format($headline_prop);
            $groupinfo_prop1 = array(  'size' => 10,
                    'bold' => 1,
                    'align' => 'left');
            $groupinfo_prop2 = $groupinfo_prop1;
            unset($groupinfo_prop2['bold']);
            $groupinfo_prop2['italic'] = true;
            $groupinfo_prop2['align'] = 'right';
            $groupinfo_format1 =& $workbook->add_format($groupinfo_prop1);
            $groupinfo_format2 =& $workbook->add_format($groupinfo_prop2);
            $reg_head_prop = array(    'size' => 10,
                    'align' => 'center',
                    'bold' => 1,
                    'bottom' => 2);
            $reg_entry_prop = array(   'size' => 10,
                    'align' => 'left');
            $queue_entry_prop = $reg_entry_prop;
            $queue_entry_prop['italic'] = true;
            $queue_entry_prop['color'] = 'grey';

            $reg_head_format =& $workbook->add_format($reg_head_prop);
            $reg_head_format->set_right(1);
            $reg_head_last =& $workbook->add_format($reg_head_prop);

            $reg_entry_format =& $workbook->add_format($reg_entry_prop);
            $reg_entry_format->set_right(1);
            $reg_entry_format->set_top(1);
            $reg_entry_format->set_bottom(0);
            $reg_entry_last =& $workbook->add_format($reg_entry_prop);
            $reg_entry_last->set_top(1);
            $no_reg_entries_format =& $workbook->add_format($reg_entry_prop);
            $no_reg_entries_format->set_align('center');
            $queue_entry_format =& $workbook->add_format($queue_entry_prop);
            $queue_entry_format->set_right(1);
            $queue_entry_format->set_top(1);
            $queue_entry_format->set_bottom(false);
            $queue_entry_last =& $workbook->add_format($queue_entry_prop);
            $queue_entry_last->set_top(1);
            $no_queue_entries_format =& $workbook->add_format($queue_entry_prop);
            $no_queue_entries_format->set_align('center');

            // Start row for groups general sheet!
            $j = 0;
            foreach ($groups as $key => $group) {
                // Add worksheet for each group!
                $group_worksheets[$key] =& $workbook->add_worksheet($group->name);

                // The standard-column-widths: 7 - 22 - 14 - 17!
                $group_worksheets[$key]->set_column(0, 0, $column_width[0]);
                $group_worksheets[$key]->set_column(1, 1, $column_width[1]);
                $group_worksheets[$key]->set_column(2, 2, $column_width[2]);
                $group_worksheets[$key]->set_column(3, 3, $column_width[3]);

                $groupname = $group->name;
                $groupinfo = array();
                $groupinfo[] = array(get_string('total'), $group->total);
                $groupinfo[] = array(get_string('registered', 'grouptool'), $group->registered);
                $groupinfo[] = array(get_string('queued', 'grouptool'), $group->queued);
                $groupinfo[] = array(get_string('free', 'grouptool'), $group->free);
                $reg_data = $group->reg_data;
                $queue_data = $group->queue_data;
                $mreg_data = isset($group->mreg_data) ? $group->mreg_data : array();
                // Groupname as headline!
                $group_worksheets[$key]->write_string(0, 0, $groupname, $headline_format);
                $group_worksheets[$key]->merge_cells(0, 0, 0, 3);
                if ($general_sheet) {
                    $all_groups_worksheet->write_string($j, 0, $groupname, $headline_format);
                    $all_groups_worksheet->merge_cells($j, 0, $j, 3);
                }

                // Groupinfo on top!
                $group_worksheets[$key]->write_string(2, 0, $groupinfo[0][0], $groupinfo_format1);
                $group_worksheets[$key]->merge_cells(2, 0, 2, 1);
                $group_worksheets[$key]->write(2, 2, $groupinfo[0][1], $groupinfo_format2);

                $group_worksheets[$key]->write_string(3, 0, $groupinfo[1][0], $groupinfo_format1);
                $group_worksheets[$key]->merge_cells(3, 0, 3, 1);
                $group_worksheets[$key]->write(3, 2, $groupinfo[1][1], $groupinfo_format2);

                $group_worksheets[$key]->write_string(4, 0, $groupinfo[2][0], $groupinfo_format1);
                $group_worksheets[$key]->merge_cells(4, 0, 4, 1);
                $group_worksheets[$key]->write(4, 2, $groupinfo[2][1], $groupinfo_format2);

                $group_worksheets[$key]->write_string(5, 0, $groupinfo[3][0], $groupinfo_format1);
                $group_worksheets[$key]->merge_cells(5, 0, 5, 1);
                $group_worksheets[$key]->write(5, 2, $groupinfo[3][1], $groupinfo_format2);
                if ($general_sheet) {
                    $all_groups_worksheet->write_string($j+2, 0, $groupinfo[0][0],
                                                        $groupinfo_format1);
                    $all_groups_worksheet->merge_cells($j+2, 0, $j+2, 1);
                    $all_groups_worksheet->write($j+2, 2, $groupinfo[0][1], $groupinfo_format2);

                    $all_groups_worksheet->write_string($j+3, 0, $groupinfo[1][0],
                                                        $groupinfo_format1);
                    $all_groups_worksheet->merge_cells($j+3, 0, $j+3, 1);
                    $all_groups_worksheet->write($j+3, 2, $groupinfo[1][1], $groupinfo_format2);

                    $all_groups_worksheet->write_string($j+4, 0, $groupinfo[2][0],
                                                        $groupinfo_format1);
                    $all_groups_worksheet->merge_cells($j+4, 0, $j+4, 1);
                    $all_groups_worksheet->write($j+4, 2, $groupinfo[2][1], $groupinfo_format2);

                    $all_groups_worksheet->write_string($j+5, 0, $groupinfo[3][0],
                                                        $groupinfo_format1);
                    $all_groups_worksheet->merge_cells($j+5, 0, $j+5, 1);
                    $all_groups_worksheet->write($j+5, 2, $groupinfo[3][1], $groupinfo_format2);
                }

                // Registrations and queue headline!
                // First the headline!
                $group_worksheets[$key]->write_string(7, 0, get_string('status', 'grouptool'),
                                                      $reg_head_format);
                $group_worksheets[$key]->write_string(7, 1, get_string('fullname'),
                                                      $reg_head_format);
                $group_worksheets[$key]->write_string(7, 2, get_string('idnumber'),
                                                      $reg_head_format);
                $group_worksheets[$key]->write_string(7, 3, get_string('email'), $reg_head_last);
                if ($general_sheet) {
                    $all_groups_worksheet->write_string($j+7, 0, get_string('status', 'grouptool'),
                                                        $reg_head_format);
                    $all_groups_worksheet->write_string($j+7, 1, get_string('fullname'),
                                                        $reg_head_format);
                    $all_groups_worksheet->write_string($j+7, 2, get_string('idnumber'),
                                                        $reg_head_format);
                    $all_groups_worksheet->write_string($j+7, 3, get_string('email'),
                                                        $reg_head_last);
                }

                // Now the registrations!
                $i = 0;
                if (!empty($reg_data)) {
                    foreach ($reg_data as $reg) {
                        if ($i==0) {
                            $reg_entry_format->set_top(2);
                        } else if ($i == 1) {
                            $reg_entry_format->set_top(1);
                        }
                        $group_worksheets[$key]->write_string(8+$i, 0, $reg['status'],
                                                              $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 1, $reg['name'],
                                                              $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 2, $reg['idnumber'],
                                                              $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 3, $reg['email'],
                                                              $reg_entry_last);
                        if ($general_sheet) {
                            $all_groups_worksheet->write_string($j+8+$i, 0, $reg['status'],
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 1, $reg['name'],
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 2, $reg['idnumber'],
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 3, $reg['email'],
                                                                $reg_entry_last);
                        }
                        $i++;
                    }
                } else if (count($mreg_data) == 0) {
                    $group_worksheets[$key]->write_string(8+$i, 0,
                                                          get_string('no_registrations',
                                                                     'grouptool'),
                                                          $no_reg_entries_format);
                    $group_worksheets[$key]->merge_cells(8+$i, 0, 8+$i, 3);
                    if ($general_sheet) {
                        $all_groups_worksheet->write_string($j+8+$i, 0,
                                                            get_string('no_registrations',
                                                                       'grouptool'),
                                                            $no_reg_entries_format);
                        $all_groups_worksheet->merge_cells($j+8+$i, 0, $j+8+$i, 3);
                    }
                    $i++;
                }

                if (count($mreg_data) >= 1) {
                    foreach ($mreg_data as $mreg) {
                        if ($i==0) {
                            $reg_entry_format->set_top(2);
                        } else if ($i == 1) {
                            $reg_entry_format->set_top(1);
                        }
                        $group_worksheets[$key]->write_string(8+$i, 0, '?', $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 1, $mreg['name'],
                                                              $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 2, $mreg['idnumber'],
                                                              $reg_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 3, $mreg['email'],
                                                              $reg_entry_last);
                        if ($general_sheet) {
                            $all_groups_worksheet->write_string($j+8+$i, 0, '?',
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 1, $mreg['name'],
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 2, $mreg['idnumber'],
                                                                $reg_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 3, $mreg['email'],
                                                                $reg_entry_last);
                        }
                        $i++;
                    }
                }
                // Don't forget the queue!
                if (!empty($queue_data)) {
                    foreach ($queue_data as $queue) {
                        if ($i==0) {
                            $reg_entry_format->set_top(2);
                        } else if ($i == 1) {
                            $reg_entry_format->set_top(1);
                        }
                        $group_worksheets[$key]->write(8+$i, 0, $queue['rank'],
                                                       $queue_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 1, $queue['name'],
                                                              $queue_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 2, $queue['idnumber'],
                                                              $queue_entry_format);
                        $group_worksheets[$key]->write_string(8+$i, 3, $queue['email'],
                                                              $queue_entry_last);
                        if ($general_sheet) {
                            $all_groups_worksheet->write_string($j+8+$i, 0, $queue['rank'],
                                                                $queue_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 1, $queue['name'],
                                                                $queue_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 2, $queue['idnumber'],
                                                                $queue_entry_format);
                            $all_groups_worksheet->write_string($j+8+$i, 3, $queue['email'],
                                                                $queue_entry_last);
                        }
                        $i++;
                    }
                } else {
                    $group_worksheets[$key]->write_string(8+$i, 0,
                                                          get_string('nobody_queued', 'grouptool'),
                                                          $no_queue_entries_format);
                    $group_worksheets[$key]->merge_cells(8+$i, 0, 8+$i, 3);
                    if ($general_sheet) {
                        $all_groups_worksheet->write_string($j+8+$i, 0,
                                                            get_string('nobody_queued',
                                                                       'grouptool'),
                                                            $no_queue_entries_format);
                        $all_groups_worksheet->merge_cells($j+8+$i, 0, $j+8+$i, 3);
                    }
                    $i++;
                }
                $j += 9+$i;    // 1 row space between groups!
            }

        }
    }

    /**
     * outputs generated ods-file for overview (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_ods($groupid=0, $groupingid=0) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

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
        $workbook = new MoodleODSWorkbook("-");

        $groups = $this->group_overview_table($groupingid, $groupid, true);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename.'.ods');
        $workbook->close();
    }

    /**
     * outputs generated xls-file for overview (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     */
    public function download_overview_xls($groupid = 0, $groupingid = 0) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

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
        $workbook = new MoodleExcelWorkbook("-");

        $groups = $this->group_overview_table($groupingid, $groupid, true);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename.'.xls');
        $workbook->close();
    }

    /**
     * get object containing informatino about syncronisation of active-groups with moodle-groups
     *
     * @global object $DB
     * @param int $grouptoolid optional get stats for this grouptool-instance
     *                                  uses $this->instance if zero
     * @return array (global out of sync, array of objects with sync-status for each group)
     */
    private function get_sync_status($grouptoolid = 0) {
        global $DB;
        $out_of_sync = false;

        if (empty($grouptoolid)) {
            $grouptoolid = $this->grouptool->id;
        }

        $sql = "SELECT agrps.id AS agrp_id, agrps.group_id AS group_id,
                       COUNT(DISTINCT reg.user_id) AS grptoolregs,
                       COUNT(DISTINCT mreg.userid) AS mdlregs
                FROM {grouptool_agrps} as agrps
                    LEFT JOIN {grouptool_registered} as reg ON agrps.id = reg.agrp_id
                    LEFT JOIN {groups_members} as mreg ON agrps.group_id = mreg.groupid
                                                       AND reg.user_id = mreg.userid
                WHERE agrps.active = 1 AND agrps.grouptool_id = ?
                GROUP BY agrps.id ASC";
        $return = $DB->get_records_sql($sql, array($grouptoolid));

        foreach ($return as $key => $group) {
            $return[$key]->status = ($group->grptoolregs > $group->mdlregs) ? GROUPTOOL_OUTDATED
                                                                            : GROUPTOOL_UPTODATE;
            $out_of_sync |= ($return[$key]->status == GROUPTOOL_OUTDATED);
        }
        return array($out_of_sync, $return);
    }

    /**
     * push in grouptool registered users to moodle-groups
     *
     * @global object $DB
     * @param int $groupid optional only for this group
     * @param int $groupingid optional only for this grouping
     * @param bool $previewonly optional get only the preview
     * @return array($error, $message)
     */
    public function push_registrations($groupid=0, $groupingid=0, $previewonly=false) {
        global $DB;
        $userinfo = get_enrolled_users($this->context);
        $return = array();
        // Get active groups filtered by group_id, grouping_id, grouptoolid!
        $agrps = $this->get_active_groups(true, false, 0, $groupid, $groupingid);
        foreach ($agrps as $groupid => $agrp) {
            foreach ($agrp->registered as $reg) {
                $info = new stdClass();
                $info->fullname = fullname($userinfo[$reg->user_id]);
                $info->group = $agrp->name;
                if (!groups_is_member($groupid, $reg->user_id)) {
                    // Add to group if is not already!
                    if (!$previewonly) {
                        if (groups_add_member($groupid, $reg->user_id)) {
                            $return[] = html_writer::tag('div',
                                                         get_string('added_member', 'grouptool',
                                                                    $info),
                                                         array('class'=>'notifysuccess'));
                        } else {
                            $return[] = html_writer::tag('div',
                                                         get_string('could_not_add', 'grouptool',
                                                                    $info),
                                                         array('class'=>'notifysuccess'));
                        }
                    } else {
                        $return[] = html_writer::tag('div', get_string('add_member', 'grouptool',
                                                                       $info),
                                                     array('class'=>'notifysuccess'));
                    }
                } else {
                    $return[] = html_writer::tag('div', get_string('already_member', 'grouptool',
                                                                   $info),
                                                 array('class'=>'ignored'));
                }
            }
        }
        switch (count($return)) {
            default:
                add_to_log($this->grouptool->course,
                        'grouptool', 'push registrations',
                        "view.php?id=".$this->grouptool->id."&tab=overview",
                        'push registrations');
                return array(false, implode("<br />\n", $return));
                break;
            case 1:
                add_to_log($this->grouptool->course,
                        'grouptool', 'push registrations',
                        "view.php?id=".$this->grouptool->id."&tab=overview",
                        'push registrations');
                return array(false, current($return));
                break;
            case 0:
                return array(true, get_string('nothing_to_push', 'grouptool'));
                break;
        }

    }

    /**
     * Render link for Member-List
     *
     * @global object $CFG
     * @global object $PAGE
     * @param int $agrpid active group id, for which the members should be displayed
     * @param string $groupname name of the group
     * @return string HTML fragment
     */
    private function render_members_link($agrpid, $groupname) {
        global $CFG, $PAGE;

        $output = get_string('show_members', 'grouptool');

        // Now create the link around it - we need https on loginhttps pages!
        $url = new moodle_url($CFG->httpswwwroot.'/mod/grouptool/showmembers.php',
                              array('agrpid'  => $agrpid,
                                    'lang'    => current_language()));

        $attributes = array('href'=>$url, 'title'=>get_string('show_members', 'grouptool'));
        $id = html_writer::random_id('showmembers');
        $attributes['id'] = $id;
        $output = html_writer::tag('a', $output, $attributes);

        $PAGE->requires->yui_module('moodle-mod_grouptool-registration',
                                    'M.mod_grouptool.add_overlay',
                                    array(array('id'=>$id, 'url'=>$url->out(false),
                                                'groupname'=>$groupname)));

        // And finally wrap in a span!
        return html_writer::tag('span', $output, array('class' => 'showmembers'));
    }

    /**
     * view overview tab
     *
     * @global object $PAGE
     * @global object $OUTPUT
     */
    public function view_overview() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $url = new moodle_url($PAGE->url, array('sesskey'=>sesskey(),
                                                'groupid'=>$groupid,
                                                'groupingid'=>$groupingid));

            // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed?!
            $hide_form = 0;
            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                list($error, $message) = $this->push_registrations($groupid, $groupingid);
            }
            if ($error) {
                echo $OUTPUT->notification($message, 'notifyproblem');
            } else {
                echo $OUTPUT->notification($message, 'notifysuccess');
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hide_form = 1;

            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                // Try only!
                list($error, $message) = $this->push_registrations($groupid, $groupingid, true);
                $attr = array();
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
                $hide_form = 0;
            }
        } else {
            $hide_form = 0;
        }

        if (!$hide_form) {
            $groupings = groups_get_all_groupings($this->course->id);
            $options = array(0=>get_string('all'));
            if (count($groupings)) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = $grouping->name;
                }
            }
            $groupingselect = new single_select($url, 'groupingid', $options, $groupingid, false);

            $groups = $this->get_active_groups(true, true, 0, 0, $groupingid);
            $options = array(0=>get_string('all'));
            if (count($groups)) {
                foreach ($groups as $group) {
                    $options[$group->id] = $group->name;
                }
            }
            if (!key_exists($groupid, $options)) {
                $groupid = 0;
                $url->param('groupid', 0);
                echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping',
                                                                   'grouptool').
                                                        html_writer::empty_tag('br').
                                                        get_string('switched_to_all_groups',
                                                                   'grouptool'),
                                                        'notifyproblem'), 'generalbox centered');
            }
            $groupselect = new single_select($url, 'groupid', $options, $groupid, false);

            $sync_status = $this->get_sync_status();

            if ($sync_status[0]) {
                /*
                 * Out of sync? --> show button to get registrations from grouptool to moodle
                 * (just register not already registered persons and let the others be)
                 */
                $url = new moodle_url($PAGE->url, array('pushtomdl'=>1, 'sesskey'=>sesskey()));
                $button = new single_button($url, get_string('updatemdlgrps', 'grouptool'));
                echo $OUTPUT->box(html_writer::empty_tag('br').
                                  $OUTPUT->render($button).
                                  html_writer::empty_tag('br'), 'generalbox centered');
            }

            echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupingselect),
                                  array('class'=>'centered grouptool_overview_filter')).
                 html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                         $OUTPUT->render($groupselect),
                                  array('class'=>'centered grouptool_overview_filter'));

            echo $this->group_overview_table($groupingid, $groupid);
        }
    }

    /**
     * get information about particular users with their registrations/queues
     *
     * @global object $DB
     * @global object $PAGE
     * @global object $OUTPUT
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group
     * @param int|array $userids optional get only this user(s)
     * @param array $orderby array how data should be sorted (column as key and ASC/DESC as value)
     * @return array of objects records from DB with all necessary data
     */
    public function get_user_data($groupingid = 0, $groupid = 0, $userids = 0, $orderby = array()) {
        global $DB, $PAGE, $OUTPUT;

        // After which table-fields can we sort?
        $sortable = array('firstname', 'lastname', 'idnumber', 'email');

        $return = new stdClass();

        // Indexed by agrp_id!
        $agrps = $this->get_active_groups(true, true, 0, $groupid, $groupingid, false);
        $agrpids = array_keys($agrps);
        if (!empty($agrpids)) {
            list($agrpsql, $agrpparams) = $DB->get_in_or_equal($agrpids);
        } else {
             $agrpsql = '';
             $agrpparams = array();
             echo $OUTPUT->box($OUTPUT->notification(get_string('no_groups_to_display',
                                                                'grouptool'),
                                                     'notifyproblem'),
                               'generalbox centered');
        }

        if (!empty($userids)) {
            if (!is_array($userids)) {
                $userids = array($userids);
            }
            list($usersql, $userparams) = $DB->get_in_or_equal($userids);
        } else {
            $usersql = ' LIKE *';
            $userparams = array();
        }
        $ufields = user_picture::fields('u', array('idnumber'));
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
                                      ((!empty($direction) && $direction == 'ASC')  ? 'ASC'
                                                                                    : 'DESC');
                } else {
                    unset($orderby[$field]);
                }
            }
        }

        if (!empty($agrpsql)) {
            if (key_exists("regs", $orderby)) {
                $reg_order = "ORDER BY grps.name ".($orderby['regs'] ? 'ASC' : 'DESC');
            } else {
                $reg_order = "";
            }
            if (key_exists("queues", $orderby)) {
                $queue_order = "ORDER BY grps.name ".($orderby['queues'] ? 'ASC' : 'DESC');
            } else {
                $queue_order = "";
            }
            $sqljoin = " LEFT JOIN {grouptool_registered} AS reg ON u.id = reg.user_id
                                                                 AND reg.agrp_id ".$agrpsql.
                       " LEFT JOIN {grouptool_queued} AS queue ON u.id = queue.user_id
                                                               AND queue.agrp_id ".$agrpsql.
                       " LEFT JOIN {grouptool_agrps} AS agrps ON queue.agrp_id = agrps.id
                                                              OR reg.agrp_id = agrps.id
                         LEFT JOIN {groups} AS grps ON agrps.group_id = grps.id";
        } else {
            $sqljoin = "";
        }
        $sqljoinreg =
                   (!empty($agrpsql) ?  : "");
        $sql = "SELECT $ufields".
               (!empty($agrpsql) ? ", GROUP_CONCAT(DISTINCT reg.agrp_id SEPARATOR ',') as regs"
                                 : ", null as regs").
               (!empty($agrpsql) ? ", GROUP_CONCAT(DISTINCT queue.agrp_id SEPARATOR ',') as queues"
                                 : ", null as queues").
              // Just to have a good name for sorting!
              (!empty($agrpsql) ? ", grps.name AS grpname" : "" ).
              " FROM {user} AS u".
                $sqljoin.
              " WHERE u.id ".$usersql.
              " GROUP BY u.id".
               $orderbystring;
        $params = array_merge($agrpparams, $agrpparams, $userparams);

        $data = $DB->get_records_sql($sql, $params);
        return $data;
    }

    /**
     * returns picture indicating sort-direction if data is primarily sorted by this column
     * or empty string if not
     *
     * @global object $OUTPUT
     * @param array $oderby array containing current state of sorting
     * @param string $search columnname to print sortpic for
     * @return string html fragment with sort-pic or empty string
     */
    private function pic_if_sorted($orderby = array(), $search) {
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
     * @global object $PAGE
     * @global object $OUTPUT
     * @param array $collapsed array with collapsed columns
     * @param string $search column-name to print link for
     * @return string html-fragment with icon to show column or column header text with icon to hide
     *                              column
     */
    private function collapselink($collapsed = array(), $search) {
        global $PAGE, $OUTPUT;
        if (in_array($search, $collapsed)) {
            $url = new moodle_url($PAGE->url, array('tshow'=>$search));
            $pic = $OUTPUT->pix_icon('t/switch_plus', 'show');
        } else {
            $url = new moodle_url($PAGE->url, array('thide'=>$search));
            $pic = $OUTPUT->pix_icon('t/switch_minus', 'hide');
        }
        return html_writer::tag('div', html_writer::link($url, $pic),
                                                         array('class'=>'collapselink'));
    }

    /**
     * get all data necessary for displaying/exporting userlist table
     *
     * @global object $OUTPUT
     * @global object $CFG
     * @global object $DB
     * @global object $PAGE
     * @global object $SESSION
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     * @param bool $data_only optional return object with raw data not html-fragment-string
     * @return string|object either html-fragment representing table or raw data as object
     */
    public function userlist_table($groupingid = 0, $groupid = 0, $orderby = array(),
                                   $collapsed = array(), $data_only = false) {
        global $OUTPUT, $CFG, $DB, $PAGE, $SESSION;
        $return = "";

        $context = context_module::instance($this->cm->id);
        // Handles order direction!
        if (isset($SESSION->mod_grouptool->userlist->orderby)) {
            $orderby = $SESSION->mod_grouptool->userlist->orderby;
        } else {
            $orderby = array();
        }
        if ($tsort = optional_param('tsort', 0, PARAM_ALPHA)) {
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
        if (isset($SESSION->mod_grouptool->userlist->collapsed)) {
            $collapsed = $SESSION->mod_grouptool->userlist->collapsed;
        } else {
            $collapsed = array();
        }
        if ($thide = optional_param('thide', 0, PARAM_ALPHA)) {
            if (!in_array($thide, $collapsed)) {
                array_push($collapsed, $thide);
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }
        if ($tshow = optional_param('tshow', 0, PARAM_ALPHA)) {
            foreach ($collapsed as $key => $value) {
                if ($value == $tshow) {
                    unset($collapsed[$key]);
                }
            }
            $SESSION->mod_grouptool->userlist->collapsed = $collapsed;
        }

        if (!$data_only) {
            $return = "";
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/mod/grouptool/download.php', array('id'=>$this->cm->id,
                    'groupingid'=>$groupingid,
                    'groupid'=>$groupid,
                    'orientation'=>$orientation,
                    'sesskey'=>sesskey(),
                    'tab'=>'userlist'));
        } else {
            $return = array();
        }

        // Get all ppl that are allowed to register!
        list($esql, $params) = get_enrolled_sql($this->context, 'mod/grouptool:register');

        $sql = "SELECT u.id FROM {user} u ".
               "LEFT JOIN ($esql) eu ON eu.id=u.id ".
               "WHERE u.deleted = 0 AND eu.id=u.id ";
        $users = $DB->get_records_sql($sql, $params);

        if (!empty($users)) {
            $users = array_keys($users);
            $userdata = $this->get_user_data($groupingid, $groupid, $users, $orderby);
        } else {
            if (!$data_only) {
                $return .= $OUTPUT->box($OUTPUT->notification(get_string('no_users_to_display',
                                                                         'grouptool'),
                                                              'notifyproblem'),
                                        'centered generalbox');
                return $return;
            } else {
                return get_string('no_users_to_display', 'grouptool');
            }
        }
        $groupinfo = $this->get_active_groups(false, false, 0, $groupid, $groupingid, false);

        if (!$data_only) {
            if (has_capability('mod/grouptool:export', $context)) {
                $txturl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_TXT));
                $xlsurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_XLS));
                $pdfurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_PDF));
                $odsurl = new moodle_url($downloadurl, array('format'=>GROUPTOOL_ODS));
                $downloadlinks = html_writer::tag('span', get_string('downloadall').":",
                                                  array('class'=>'title')).'&nbsp;'.
                        html_writer::link($txturl, '.TXT').'&nbsp;'.
                        html_writer::link($xlsurl, '.XLS').'&nbsp;'.
                        html_writer::link($pdfurl, '.PDF').'&nbsp;'.
                        html_writer::link($odsurl, '.ODS');
                $return .= html_writer::tag('div', $downloadlinks, array('class'=>'download all'));
            }

            $table = new html_table();
            $table->attributes['class'] = 'centeredblock userlist';

            $picture = new html_table_cell($this->collapselink($collapsed, 'picture'));
            if (!in_array('fullname', $collapsed)) {
                $firstnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                  array('tsort'=>'firstname')),
                                                   get_string('firstname').
                                                   $this->pic_if_sorted($orderby, 'firstname'));
                $surnamelink = html_writer::link(new moodle_url($PAGE->url,
                                                                array('tsort'=>'lastname')),
                                                  get_string('lastname').
                                                  $this->pic_if_sorted($orderby, 'lastname'));
                $fullname = html_writer::tag('div', get_string('fullname').
                                                    html_writer::empty_tag('br').
                                                    $firstnamelink.'&nbsp;/&nbsp;'.$surnamelink);
                $fullname = new html_table_cell($fullname.$this->collapselink($collapsed,
                                                                             'fullname'));
            } else {
                $fullname = new html_table_cell($this->collapselink($collapsed, 'fullname'));
            }
            if (!in_array('idnumber', $collapsed)) {
                $idnumberlink = html_writer::link(new moodle_url($PAGE->url,
                                                                 array('tsort'=>'idnumber')),
                                                  get_string('idnumber').
                                                  $this->pic_if_sorted($orderby, 'idnumber'));
                $idnumber = new html_table_cell($idnumberlink.$this->collapselink($collapsed,
                                                                                  'idnumber'));
            } else {
                $idnumber = new html_table_cell($this->collapselink($collapsed, 'idnumber'));
            }
            if (!in_array('email', $collapsed)) {
                $emaillink = html_writer::link(new moodle_url($PAGE->url, array('tsort'=>'email')),
                                                  get_string('email').
                                                  $this->pic_if_sorted($orderby, 'email'));
                $email = new html_table_cell($emaillink.$this->collapselink($collapsed, 'email'));
            } else {
                $email = new html_table_cell($this->collapselink($collapsed, 'email'));
            }
            if (!in_array('registrations', $collapsed)) {
                $registrationslink = get_string('registrations', 'grouptool');
                $registrations = new html_table_cell($registrationslink.
                                                     $this->collapselink($collapsed,
                                                                         'registrations'));
            } else {
                $registrations = new html_table_cell($this->collapselink($collapsed,
                                                                         'registrations'));
            }
            if (!in_array('queues', $collapsed)) {
                $queueslink = get_string('queues', 'grouptool');
                $queues = new html_table_cell($queueslink.
                                              $this->collapselink($collapsed, 'queues'));
            } else {
                $queues = new html_table_cell($this->collapselink($collapsed, 'queues'));
            }
            $table->head = array($picture, $fullname, $idnumber, $email, $registrations, $queues);
        } else {
            $head = array('name'          => get_string('fullname'),
                          'idnumber'      => get_string('idnumber'),
                          'email'         => get_string('email'),
                          'registrations' => get_string('registrations', 'grouptool'),
                          'queues'        => get_string('queues', 'grouptool'));
        }
        $rows = array();
        if(!empty($userdata)) {
            foreach ($userdata as $user) {
                if (!$data_only) {
                    $userlink = new moodle_url($CFG->wwwroot.'/user/view.php',
                                               array('id'     => $user->id,
                                                     'course' => $this->course->id));
                    if (!in_array('picture', $collapsed)) {
                        $picture = html_writer::link($userlink, $OUTPUT->user_picture($user));
                    } else {
                        $picture = "";
                    }
                    if (!in_array('fullname', $collapsed)) {
                        $fullname = html_writer::link($userlink, fullname($user));
                    } else {
                        $fullname = "";
                    }
                    if (!in_array('idnumber', $collapsed)) {
                        $idnumber = $user->idnumber;
                    } else {
                        $idnumber = "";
                    }
                    if (!in_array('email', $collapsed)) {
                        $email = $user->email;
                    } else {
                        $email = "";
                    }
                    if (!in_array('registrations', $collapsed)) {
                        if (!empty($user->regs)) {
                            $regs = explode(',', $user->regs);
                            $registrations = array();
                            foreach ($regs as $reg) {
                                $grouplink = new moodle_url($PAGE->url,
                                                            array('tab'     => 'overview',
                                                                  'groupid' => $groupinfo[$reg]->id));
                                $registrations[] = html_writer::link($grouplink,
                                                                     $groupinfo[$reg]->name);
                            }
                        } else {
                            $registrations = array('-');
                        }
                        $registrations = implode(html_writer::empty_tag('br'), $registrations);
                    } else {
                        $registrations = "";
                    }
                    if (!in_array('queues', $collapsed)) {
                        if (!empty($user->queues)) {
                            $queues = explode(',', $user->queues);
                            $queueentries = array();
                            foreach ($queues as $queue) {
                                $grouplink = new moodle_url($PAGE->url,
                                                            array('tab'     => 'overview',
                                                                  'groupid' => $groupinfo[$queue]->id));
                                $rank = $this->get_rank_in_queue($queue, $user->id);
                                if (empty($rank)) {
                                    $rank = '*';
                                }
                                $queueentries[] = html_writer::link($grouplink,
                                                                    "(".$rank.") ".
                                                                    $groupinfo[$queue]->name);
                            }
                        } else {
                            $queueentries = array('-');
                        }
                        $queueentries = implode(html_writer::empty_tag('br'), $queueentries);
                    } else {
                        $queueentries = "";
                    }
                    $rows[] = array($picture, $fullname, $idnumber, $email, $registrations,
                                    $queueentries);
                } else {
                    $fullname = fullname($user);
                    $idnumber = $user->idnumber;
                    $email = $user->email;
                    if (!empty($user->regs)) {
                        $regs = explode(',', $user->regs);
                        $registrations = array();
                        foreach ($regs as $reg) {
                            $registrations[] = $groupinfo[$reg]->name;
                        }
                    } else {
                        $registrations = array();
                    }
                    if (!empty($user->queues)) {
                        $queues = explode(',', $user->queues);
                        $queueentries = array();
                        foreach ($queues as $queue) {
                            $rank = $this->get_rank_in_queue($queue, $user->id);
                            if (empty($rank)) {
                                $rank = '*';
                            }
                            $queueentries[] = array('rank'=>$rank, 'name'=>$groupinfo[$queue]->name);
                        }
                    } else {
                        $queueentries = array();
                    }
                    $rows[] = array('name'          => $fullname,
                                    'idnumber'      => $idnumber,
                                    'email'         => $email,
                                    'registrations' => $registrations,
                                    'queues'        => $queueentries);
                }
            }
        }
        if (!$data_only) {
            $table->data = $rows;
            return $return.html_writer::table($table);
        } else {
            return array_merge(array($head), $rows);
        }
    }

    /**
     * outputs generated pdf-file for userlist (forces download)
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_pdf($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        global $USER;
        require_once('./grouptool_pdf.php');

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $pdf = new grouptool_pdf();

        // Set orientation (P/L)!
        $orientation = (optional_param('orientation', 0, PARAM_BOOL) == 0) ? 'P' : 'L';
        $pdf->setPageOrientation($orientation);

        // Set document information!
        $pdf->SetCreator('TUWEL');
        $pdf->SetAuthor(fullname($USER));

        $coursename = $this->course->fullname;
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

        $pdf->set_userlist_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                    $viewname);

        // Set header/footer!
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);

        $textsize = optional_param('textsize', 1, PARAM_INT);
        switch ($textsize){
            case "0":
                $pdf->SetFontSize(8);
                break;
            case "1":
                $pdf->SetFontSize(10);
                break;
            case "2":
                $pdf->SetFontSize(12);
                break;
        }

        // Set margins!
        if (1) {
            $pdf->SetMargins(10, 30, 10); // Left Top Right!
        } else {
            $pdf->SetMargins(10, 10, 10);
        }
        // Set default monospaced font!
        $pdf->SetDefaultMonospacedFont(/*PDF_FONT_MONOSPACED*/'freeserif');

        // Set margins!
        $pdf->SetHeaderMargin(7);

        // Set auto page breaks!
        $pdf->SetAutoPageBreak(true, /*PDF_MARGIN_BOTTOM*/10);

        // Set image scale factor!
        $pdf->setImageScale(/*PDF_IMAGE_SCALE_RATIO*/1);

        /*
         * ---------------------------------------------------------
         */

        // Set font!
        $pdf->SetFont('freeserif', '');
        $pdf->addPage($orientation, 'A4', false, false);
        if (count($data) > 1) {
            $user = reset($data);
            $name = $user['name'];
            $idnumber = $user['idnumber'];
            $email = $user['email'];
            $reg_data = $user['registrations'];
            $queue_data = $user['queues'];
            $pdf->add_userdata($name, $idnumber, $email, $reg_data, $queue_data, true);
            while (next($data)) {
                $user = current($data);
                $name = $user['name'];
                $idnumber = $user['idnumber'];
                $email = $user['email'];
                $reg_data = $user['registrations'];
                $queue_data = $user['queues'];
                $pdf->add_userdata($name, $idnumber, $email, $reg_data, $queue_data);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'grouptool'),
                            'B', 'LRTB', false, 1, null, null, true, 1, true, false,
                            $pdf->getLastH(), 'M', true);
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

        $pdf->Output($filename.'.pdf', 'D');
        exit();
    }

    /**
     * returns data for userlist
     *
     * @global object $USER
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     * @return object raw data
     */
    public function download_userlist_raw($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        return $this->userlist_table($groupid, $groupingid, $orderby, $collapsed, true);
    }

    /**
     * outputs generated txt-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_txt($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        ob_start();

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        $return = "";
        $lines = array();
        $users = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);
        if (count($users) > 0) {
            foreach ($users as $key => $user) {
                if ($key == 0) { // Headline!
                    $lines[] = get_string('fullname')."\t".
                               get_string('idnumber')."\t".
                               get_string('email')."\t".
                               get_string('registrations', 'grouptool')."\t".
                               get_string('queues', 'grouptool').' '.
                               get_string('rank', 'grouptool')."\t".
                               get_string('queues', 'grouptool');
                } else {
                    $rows = max(array(1, count($user['registrations']), count($user['queues'])));

                    for ($i=0; $i<$rows; $i++) {
                        $line = "";
                        if ($i==0) {
                            $line = $user['name']."\t".$user['idnumber']."\t".$user['email'];
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
                            $line .= "\t\t".get_string('nowhere_queued', 'grouptool');
                        } else if (key_exists($i, $user['queues'])) {
                            $line .= "\t".$user['queues'][$i]['rank'];
                            $line .= "\t".$user['queues'][$i]['name'];
                        } else {
                            $line .= "\t\t";
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
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1!
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in past!
        header('Content-Disposition: attachment; filename="'.$filename.'.txt";'.
               ' filename*="'.rawurlencode($filename).'.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * fills workbook (either XLS or ODS) with data
     *
     * @global object $SESSION
     * @param MoodleExcelWorkbook $workbook workbook to put data into
     * @param array $data userdata with headline at index 0
     * @param array $orderby current sort-array
     * @param array $collapsed current collapsed columns
     */
    private function userlist_fill_workbook(&$workbook, $data=array(), $orderby=array(),
                                            $collapsed=array()) {
        global $SESSION;
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        if (count($data) > 0) {
            if (is_a($workbook, 'MoodleExcelWorkbook')) {
                $column_width = array(26.71, 15.29, 29.86, 47, 7.29, 47); // Unit: mm!
            } else {
                $column_width = array(192, 112, 214, 334, 56, 334); // Unit: px!
            }
            if (count($data)>1) {
                // General information? unused at the moment!
                $worksheet =& $workbook->add_worksheet(get_string('all'));
                if (is_a($worksheet, 'Moodle_Excel_Worksheet')) {
                    if ($orientation) {
                        $worksheet->pear_excel_worksheet->setLandscape();
                    } else {
                        $worksheet->pear_excel_worksheet->setPortrait();
                    }
                }

                // Standard column widths: 7 - 22 - 14 - 17!
                $hidden = in_array('fullname', $collapsed) ? true : false;
                $worksheet->set_column(0, 0, $column_width[0], null, $hidden);
                $hidden = in_array('fullname', $collapsed) ? true : false;
                $worksheet->set_column(1, 1, $column_width[1], null, $hidden);
                $hidden = in_array('fullname', $collapsed) ? true : false;
                $worksheet->set_column(2, 2, $column_width[2], null, $hidden);
                $hidden = in_array('fullname', $collapsed) ? true : false;
                $worksheet->set_column(3, 3, $column_width[3], null, $hidden);
                $hidden = in_array('fullname', $collapsed) ? true : false;
                $worksheet->set_column(4, 4, $column_width[4], null, $hidden);
                $worksheet->set_column(5, 5, $column_width[5], null, $hidden);
            }

            // Prepare formats!
            $headline_prop = array(    'size' => 12,
                    'bold' => 1,
                    'HAlign' => 'center',
                    'bottom' => 2,
                    'VAlign' => 'vcenter');
            $headline_format =& $workbook->add_format($headline_prop);
            $headline_format->set_right(1);
            $headline_format->set_align('center');
            $headline_format->set_align('vcenter');
            $headline_last =& $workbook->add_format($headline_prop);
            $headline_last->set_align('center');
            $headline_last->set_align('vcenter');
            $headline_last->set_left(1);
            $headline_nb =& $workbook->add_format($headline_prop);
            $headline_nb->set_align('center');
            $headline_nb->set_align('vcenter');
            unset($headline_prop['bottom']);
            $headline_nbb =& $workbook->add_format($headline_prop);
            $headline_nbb->set_align('center');
            $headline_nbb->set_align('vcenter');

            $reg_entry_prop = array(   'size' => 10,
                    'align' => 'left');
            $queue_entry_prop = $reg_entry_prop;
            $queue_entry_prop['italic'] = true;
            $queue_entry_prop['color'] = 'grey';

            $reg_entry_format =& $workbook->add_format($reg_entry_prop);
            $reg_entry_format->set_right(1);
            $reg_entry_format->set_align('vcenter');
            $reg_entry_last =& $workbook->add_format($reg_entry_prop);
            $reg_entry_last->set_align('vcenter');
            $no_reg_entries_format =& $workbook->add_format($reg_entry_prop);
            $no_reg_entries_format->set_align('center');
            $no_reg_entries_format->set_align('vcenter');
            $no_reg_entries_format->set_right(1);
            $queue_entry_format =& $workbook->add_format($queue_entry_prop);
            $queue_entry_format->set_right(1);
            $queue_entry_format->set_align('vcenter');
            $queue_entry_last =& $workbook->add_format($queue_entry_prop);
            $queue_entry_last->set_align('vcenter');
            $no_queue_entries_format =& $workbook->add_format($queue_entry_prop);
            $no_queue_entries_format->set_align('center');
            $no_queue_entries_format->set_align('vcenter');

            // Start row for groups general sheet!
            $j = 0;
            foreach ($data as $key => $user) {
                if ($key == 0) {
                    // Headline!
                    $worksheet->write_string($j, 0, $user['name'], $headline_format);
                    $worksheet->write_blank($j+1, 0, $headline_format);
                    $worksheet->merge_cells($j, 0, $j+1, 0);
                    $worksheet->write_string($j, 1, $user['idnumber'], $headline_format);
                    $worksheet->write_blank($j+1, 1, $headline_format);
                    $worksheet->merge_cells($j, 1, $j+1, 1);
                    $worksheet->write_string($j, 2, $user['email'], $headline_format);
                    $worksheet->write_blank($j+1, 2, $headline_format);
                    $worksheet->merge_cells($j, 2, $j+1, 2);
                    $worksheet->write_string($j, 3, $user['registrations'], $headline_format);
                    $worksheet->write_blank($j+1, 3, $headline_format);
                    $worksheet->merge_cells($j, 3, $j+1, 3);
                    $worksheet->write_string($j, 4, $user['queues'], $headline_nbb);
                    $worksheet->write_blank($j, 5, $headline_nbb);
                    $worksheet->merge_cells($j, 4, $j, 5);
                    $worksheet->write_string($j+1, 4, get_string('rank', 'grouptool'),
                                             $headline_last);
                    $worksheet->write_string($j+1, 5, get_string('group', 'group'), $headline_nb);
                    $rows = 2;
                } else {

                    $rows = max(array(1, count($user['registrations']), count($user['queues'])));

                    $worksheet->write_string($j, 0, $user['name'], $reg_entry_format);
                    if ($rows > 1) {
                        $worksheet->merge_cells($j, 0, $j+$rows-1, 0);
                    }

                    $worksheet->write_string($j, 1, $user['idnumber'], $reg_entry_format);
                    if ($rows > 1) {
                        $worksheet->merge_cells($j, 1, $j+$rows-1, 1);
                    }

                    $worksheet->write_string($j, 2, $user['email'], $reg_entry_format);
                    if ($rows > 1) {
                        $worksheet->merge_cells($j, 2, $j+$rows-1, 2);
                    }

                    for ($i=0; $i<$rows; $i++) {
                        if ($i!=0) {
                            $worksheet->write_blank($j+$i, 0, $reg_entry_format);
                            $worksheet->write_blank($j+$i, 1, $reg_entry_format);
                            $worksheet->write_blank($j+$i, 2, $reg_entry_format);
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, 3, get_string('no_registrations',
                                                                       'grouptool'),
                                                     $no_reg_entries_format);
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, 3, $j+$rows-1, 3);
                            }
                        } else if (key_exists($i, $user['registrations'])) {
                            $worksheet->write_string($j+$i, 3, $user['registrations'][$i],
                                                     $reg_entry_format);
                        } else {
                            $worksheet->write_blank($j+$i, 3, $reg_entry_format);
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, 4, get_string('nowhere_queued',
                                                                       'grouptool'),
                                                     $no_queue_entries_format);
                            $worksheet->merge_cells($j, 4, $j+$rows-1, 5);
                        } else if (key_exists($i, $user['queues'])) {
                            $worksheet->write_number($j+$i, 4, $user['queues'][$i]['rank'],
                                                     $queue_entry_last);
                            $worksheet->write_string($j+$i, 5, $user['queues'][$i]['name'],
                                                     $queue_entry_last);
                        } else {
                            $worksheet->write_blank($j+$i, 4, $queue_entry_last);
                            $worksheet->write_blank($j+$i, 5, $queue_entry_last);
                        }
                    }
                }
                $j += $rows;    // We use 1 row space between groups!
            }
        }
    }

    /**
     * outputs generated ods-file for userlist (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_ods($groupid=0, $groupingid=0, $orderby=array(),
                                          $collapsed=array()) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        $workbook = new MoodleODSWorkbook("-");

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $this->userlist_fill_workbook($workbook, $data, $orderby, $collapsed);

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

        $workbook->send($filename.'.ods');
        $workbook->close();
    }

    /**
     * outputs generated xls-file for userlist (forces download)
     *
     * @global object $CFG
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param array $orderby optional current order-by array
     * @param array $collapsed optional current array with collapsed columns
     */
    public function download_userlist_xls($groupid = 0, $groupingid = 0, $orderby=array(),
                                          $collapsed=array()) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = $this->course->fullname;
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        $workbook = new MoodleExcelWorkbook("-");

        $data = $this->userlist_table($groupingid, $groupid, $orderby, $collapsed, true);

        $this->userlist_fill_workbook($workbook, $data);

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

        $workbook->send($filename.'.xls');
        $workbook->close();
    }

    /**
     * view userlist tab
     *
     * @global object $PAGE
     * @global object $OUTPUT
     */
    public function view_userlist() {
        global $PAGE, $OUTPUT;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);

        $url = new moodle_url($PAGE->url, array('sesskey'=>sesskey(),
                                                'groupid'=>$groupid,
                                                'groupingid'=>$groupingid,
                                                'orientation'=>$orientation));

        $groupings = groups_get_all_groupings($this->course->id);
        $options = array(0=>get_string('all'));
        if (count($groupings)) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = $grouping->name;
            }
        }
        $groupingselect = new single_select($url, 'groupingid', $options, $groupingid, false);

        $groups = $this->get_active_groups(true, true, 0, 0, $groupingid);
        $options = array(0=>get_string('all'));
        if (count($groups)) {
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
        }
        if (!key_exists($groupid, $options)) {
            $groupid = 0;
            $url->param('groupid', 0);
            echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping',
                                                               'grouptool').
                                                    html_writer::empty_tag('br').
                                                    get_string('switched_to_all_groups',
                                                               'grouptool'),
                                                    'notifyproblem'), 'generalbox centered');
        }
        $groupselect = new single_select($url, 'groupid', $options, $groupid, false);

        $options = array(0 => get_string('portrait', 'grouptool'),
                         1 => get_string('landscape', 'grouptool'));
        $orientationselect = new single_select($url, 'orientation', $options, $orientation, false);

        echo html_writer::tag('div', get_string('grouping', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupingselect),
                              array('class'=>'centered grouptool_userlist_filter')).
             html_writer::tag('div', get_string('group', 'group').'&nbsp;'.
                                     $OUTPUT->render($groupselect),
                              array('class'=>'centered grouptool_userlist_filter')).
             html_writer::tag('div', get_string('orientation', 'grouptool').'&nbsp;'.
                                     $OUTPUT->render($orientationselect),
                              array('class'=>'centered grouptool_userlist_filter'));

        echo $this->userlist_table($groupingid, $groupid);

    }

}
