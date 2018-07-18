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
 * Contains mod_grouptool's grading form
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->dirroot.'/mod/grouptool/lib.php');

/**
 * class representing the moodleform used in the grading-tab
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_form extends \moodleform {
    /** @var \context_module context object */
    protected $context = null;

    /**
     * Definition of import form
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'grading');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('header', 'filterslegend', get_string('filters_legend', 'grouptool'));

        $label = get_string('grading_activity_title', 'grouptool');
        $activityselect = $mform->createElement('selectgroups', 'activity', $label, null);
        if ($modinfo = get_fast_modinfo($this->_customdata['course'])) {
            $sections = $modinfo->get_sections();
            foreach ($sections as $curnumber => $sectionmodules) {
                $activities = [];
                $sectiontext = course_get_format($this->_customdata['course'])->get_section_name($curnumber);

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
                    if (\core_text::strlen($name) > 55) {
                        $name = \core_text::substr($name, 0, 50)."...";
                    }
                    if (!$mod->visible) {
                        $name = "(".$name.")";
                    }
                    $activities["$curcmid"] = $name;
                }
                $activityselect->addOptGroup($sectiontext, $activities);
            }
        }
        $mform->addElement($activityselect);

        $mform->addElement('advcheckbox', 'mygroups_only', null, get_string('mygroups_only_label', 'grouptool'),
                ['group' => '1'], [0, 1]);
        $mform->setDefault('mygroups_only', $this->_customdata['mygroupsonly']);
        if (!has_capability('mod/grouptool:grade', $this->context)) {
            $mform->setConstant('mygroups_only', 1);
            $mform->freeze('mygroups_only');
        }

        $mform->addElement('advcheckbox', 'incomplete_only', null, get_string('incomplete_only_label', 'grouptool'),
                ['group' => '1'], [0, 1]);
        $mform->setDefault('incomplete_only', $this->_customdata['incompleteonly']);

        $mform->addElement('advcheckbox', 'overwrite', null, get_string('overwrite_label', 'grouptool'),
                ['group' => '1'], [0, 1]);
        $mform->setDefault('overwrite', $this->_customdata['overwrite']);

        $groupings = groups_get_all_groupings($this->_customdata['course']->id);
        $options = ["0" => get_string('all')];
        foreach ($groupings as $currentgrouping) {
            $options[$currentgrouping->id] = $currentgrouping->name;
        }
        $mform->addElement('select', 'grouping', get_string('grading_grouping_select_title', 'grouptool'), $options);
        $mform->setDefault('grouping', $this->_customdata['grouping']);

        $options = [
            "-1" => get_string('nonconflicting', 'grouptool'),
            "0"  => get_string('all')
        ];
        $groups = groups_get_all_groups($this->_customdata['course']->id, null, $this->_customdata['grouping'], 'g.id, g.name');
        foreach ($groups as $key => $group) {
            $membercount = $DB->count_records('groups_members', ['groupid' => $group->id]);
            if ($membercount == 0) {
                continue;
            }
            $options[$key] = $group->name.' ('.$membercount.')';
        }
        $mform->addElement('select', 'filter', get_string('grading_filter_select_title', 'grouptool'), $options);
        $mform->addHelpButton('filter', 'grading_filter_select_title', 'grouptool');
        $mform->setDefault('filter', $this->_customdata['filter']);

        $mform->addElement('submit', 'refresh_table', get_string('refresh_table_button', 'grouptool'));

        $mform->addElement('header', 'gradingtable', get_string('groupselection', 'grouptool'));
        $mform->addHelpButton('gradingtable', 'groupselection', 'grouptool');

        $mform->addElement('html', $this->_customdata['table']);

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

        return $errors;
    }
}

