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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * adminform.class.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

/**
 * class representing the moodleform used in the administration tab
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_view_admin_form extends moodleform {

    /**
     * Variable containing reference to our sortlist, so we can alter current active entries afterwards
     */
    private $_sortlist = null;

    public function update_cur_active($curactive = null) {
        if (!empty($curactive) && is_array($curactive)) {
            $this->_sortlist->_options['curactive'] = $curactive;
        }
    }

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
        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $coursecontext = context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:administrate_groups', $this->context)
                || has_capability('mod/grouptool:create_groupings', $this->context)) {
            $mform->addElement('header', 'groupsettings', get_string('agroups', 'grouptool'));
            $mform->setExpanded('groupsettings');

            $filter = $this->_customdata['filter'];

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
                list($grpssql, $params) = $DB->get_in_or_equal($groups);
                if ($filter == mod_grouptool::FILTER_ACTIVE) {
                    $activefilter = ' AND active = 1 ';
                } else if ($filter == mod_grouptool::FILTER_INACTIVE) {
                    $activefilter = ' AND active = 0 ';
                } else {
                    $activefilter = '';
                }
                $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
                $params = array_merge(array($cm->instance), $params);
                $groupdata = (array)$DB->get_records_sql("
                        SELECT grp.id AS id, MAX(grp.name) AS name,
                               MAX(agrp.grpsize) AS grpsize, MAX(agrp.active) AS active, MAX(agrp.active) AS curactive,
                               MAX(agrp.sort_order) AS sort_order
                        FROM {groups} AS grp
                        LEFT JOIN {grouptool_agrps} as agrp
                             ON agrp.groupid = grp.id AND agrp.grouptoolid = ?
                        LEFT JOIN {groupings_groups}
                             ON {groupings_groups}.groupid = grp.id
                        LEFT JOIN {groupings} AS grpgs
                             ON {groupings_groups}.groupingid = grpgs.id
                        WHERE grp.id ".$grpssql.$activefilter."
                        GROUP BY grp.id
                        ORDER BY active DESC, sort_order ASC, name ASC", $params);
                /*
                 * convert to multidimensional array and replace comma separated string
                 * through array for each classes list
                 */
                $runningidx = 1;
                foreach ($groupdata as $key => $group) {
                    $groupdata[$key]->selected = 0;
                    $groupdata[$key]->sort_order = $runningidx;
                    $runningidx++;
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
                $grouptool_grpsize = get_config('mod_grouptool', 'grpsize');
                $options['add_fields']['grpsize']->stdvalue = $grouptool->grpsize ?
                                                              $grouptool->grpsize :
                                                              $grouptool_grpsize;
                if (!empty($this->_customdata['show_grpsize'])) {
                    $options['add_fields']['grpsize']->label = get_string('groupsize', 'grouptool');
                    $options['add_fields']['grpsize']->type = 'text';
                } else {
                    $options['add_fields']['grpsize']->label = '';
                    $options['add_fields']['grpsize']->type = 'hidden';
                }
                $options['add_fields']['grpsize']->attr = array('size' => '3');
                //Update active groups marked in form!
                $options['curactive'] = $DB->get_records_sql_menu("SELECT groupid, active
                                                                     FROM {grouptool_agrps}
                                                                    WHERE grouptoolid = ?",
                                                                  array($cm->instance));
                $options['all_string'] = get_string('all').' '.get_string('groups');
                $this->_sortlist = $mform->addElement('sortlist', 'grouplist', $options);
                $mform->setDefault('grouplist', $groupdata);

                // Add disabledIfs for all Groupsize-Fields!
                $usesize = $grouptool->use_size;
                $mform->addElement('hidden', 'use_size');
                $mform->setDefault('use_size', $usesize);
                $mform->setType('use_size', PARAM_BOOL);
                $useindividual = $grouptool->use_individual;
                $mform->addElement('hidden', 'use_individual');
                $mform->setDefault('use_individual', $useindividual);
                $mform->setType('use_individual', PARAM_BOOL);

                foreach ($groupdata as $key => $group) {
                    $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_size',
                                       'eq', 0);
                    $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_individual',
                                       'eq', 0);
                }
            } else {
                $this->_sortlist = $mform->addElement('sortlist', 'grouplist', array());
                $mform->setExpanded('groupsettings');
                $mform->setExpanded('groupingscreateHeader');
                $mform->setDefault('grouplist', null);
            }

            if (has_capability('mod/grouptool:administrate_groups', $this->context) && ($nogroups != 1)) {
                $mform->addElement('submit', 'updateActiveGroups', get_string('savechanges'));
            }
        }

        if (has_capability('mod/grouptool:create_groupings', $this->context)) {
            $mform->addElement('header', 'groupingscreateHeader', get_string('groupingscreation',
                                                                             'grouptool'));

            $coursegroups = groups_get_all_groups($course->id, null, null, "id");
            if (is_array($coursegroups) && !empty($coursegroups)) {
                $options = array(0 => get_string('selected', 'grouptool'), 1 => get_string('all'));
                $mform->addElement('select', 'use_all',
                                   get_string('use_all_or_chosen', 'grouptool'), $options);
                $mform->addHelpButton('use_all', 'use_all_or_chosen', 'grouptool');
                $mform->setType('use_all', PARAM_BOOL);

                $selectgroups = $mform->createElement('selectgroups', 'grpg_target', get_string('groupingselect', 'grouptool'));
                $options = array('0' => get_string('no'));
                if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                    $options['-1'] = get_string('onenewgrouping', 'grouptool');
                    $options['-2'] = get_string('onenewgroupingpergroup', 'grouptool');
                }
                $selectgroups->addOptGroup("", $options);
                if ($groupings = groups_get_all_groupings($course->id)) {
                    $options = array();
                    foreach ($groupings as $grouping) {
                        $options[$grouping->id] = strip_tags(format_string($grouping->name));
                    }
                    $selectgroups->addOptGroup("————————————————————————", $options);
                }
                $mform->addElement($selectgroups);
                $mform->addHelpButton('grpg_target', 'groupingselect', 'grouptool');
                if (has_capability('mod/grouptool:create_groupings', $this->context)) {
                    $mform->addElement('text', 'grpg_groupingname', get_string('groupingname', 'group'));
                    $mform->setType('grpg_groupingname', PARAM_MULTILANG);
                    $mform->disabledIf('grpg_groupingname', 'grpg_target', 'noteq', '-1');
                }

                $mform->addElement('submit', 'createGroupings', get_string('create_assign_groupings',
                                                                           'grouptool'));
            } else {
                $mform->addElement('static', html_writer::tag('div', get_string('sortlist_no_data',
                                                                              'grouptool')));
            }
        }
        switch($filter) {
                case mod_grouptool::FILTER_ACTIVE:
                    $curfilter = 'active';
                break;
                case mod_grouptool::FILTER_INACTIVE:
                    $curfilter = 'inactive';
                break;
                case mod_grouptool::FILTER_ALL:
                    $curfilter = 'all';
                break;
        }
        $PAGE->requires->yui_module('moodle-mod_grouptool-administration',
                                    'M.mod_grouptool.init_administration',
                                    array(array('lang'      => current_language(),
                                                'contextid' => $this->context->id,
                                                'filter'    => $curfilter)));
        $PAGE->requires->string_for_js('rename_failed','mod_grouptool');
        $PAGE->requires->string_for_js('confirm_delete', 'mod_grouptool');
        $PAGE->requires->strings_for_js(array('yes', 'no'), 'moodle');
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
        $parenterrors = parent::validation($data, $files);
        $errors = array();

        if (!empty($data['updateActiveGroups'])
           && (!empty($data['use_size']) && !empty($data['use_individual']))) {
            $sql = '
   SELECT agrps.groupid as id, COUNT(reg.id) as regcnt
     FROM {grouptool_agrps} as agrps
LEFT JOIN {grouptool_registered} as reg ON reg.agrpid = agrps.id AND reg.modified_by >= 0
    WHERE agrps.grouptoolid = :grouptoolid
 GROUP BY agrps.groupid';
            $cm = get_coursemodule_from_id('grouptool', $data['id']);
            $params = array('grouptoolid' => $cm->instance);
            $regs = $DB->get_records_sql_menu($sql, $params);
            $toomanyregs = '';
            foreach ($data['grouplist'] as $groupid => $curgroup) {
                if ((clean_param($curgroup['grpsize'], PARAM_INT) <= 0) || !ctype_digit($curgroup['grpsize'])) {
                    if (!isset($errors['grouplist']) || ($errors['grouplist'] == '')) {
                        $errors['grouplist'] = get_string('grpsizezeroerror', 'grouptool').' '.
                                               get_string('error_at', 'grouptool').' '.$curgroup['name'];
                    } else {
                        $errors['grouplist'] .= ', '.$curgroup['name'];
                    }
                } else if (!empty($regs[$groupid]) && $curgroup['grpsize'] < $regs[$groupid]) {
                    if (empty($toomanyregs)) {
                        $toomanyregs = get_string('toomanyregs', 'grouptool');
                    }
                }
            }
            if ($toomanyregs != '') {
                if (!isset($errors['grouplist']) || ($errors['grouplist'] == '')) {
                    $errors['grouplist'] = $toomanyregs;
                } else {
                    $errors['grouplist'] .= html_writer::empty_tag('br').$toomanyregs;
                }
            }
        }
        return array_merge($parenterrors, $errors);
    }
}

/**
 * class representing the moodleform used in the administration tab
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_groupings_creation_form extends moodleform {

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
        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $coursecontext = context_course::instance($cm->course);

        foreach ($this->_customdata['selected'] as $select) {
            $mform->addElement('hidden', 'selected['.$select.']');
            $mform->setDefault('selected['.$select.']', $select);
            $mform->setType('selected['.$select.']', PARAM_INT);
        }
        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'bulkaction');
        $mform->setDefault('bulkaction', 'grouping');
        $mform->setType('bulkaction', PARAM_TEXT);

        $groupingel = $mform->createElement('selectgroups', 'target', get_string('groupingselect', 'grouptool'));
        $options = array('' => get_string('choose'));
        $options['-1'] = get_string('onenewgrouping', 'grouptool');
        $options['-2'] = get_string('onenewgroupingpergroup', 'grouptool');
        $groupingel->addOptGroup("", $options);
        if ($groupings = groups_get_all_groupings($course->id)) {
            $options = array();
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = strip_tags(format_string($grouping->name));
            }
            $groupingel->addOptGroup("————————————————————————", $options);
        }
        $mform->addElement($groupingel);
        $mform->addHelpButton('target', 'groupingselect', 'grouptool');

        $mform->addElement('text', 'name', get_string('groupingname', 'group'));
        $mform->setType('name', PARAM_MULTILANG);
        $mform->disabledIf('name', 'target', 'noteq', '-1');

        $mform->addElement('submit', 'createGroupings', get_string('create_assign_groupings',
                                                                   'grouptool'));
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
        $parenterrors = parent::validation($data, $files);
        $errors = array();

        if (($data['target'] == -1) && empty($data['name'])) {
            $errors['name'] = get_string('required');
        }
        if (($data['target'] == -1) && groups_get_grouping_by_name($data['courseid'], $data['name'])) {
            $errors['name'] = get_string('groupingnameexists', 'group', $data['name']);
        }

        return array_merge($parenterrors, $errors);
    }
}