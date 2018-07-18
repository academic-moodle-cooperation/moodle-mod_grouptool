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
 * Contains mod_grouptool's groupings creation form
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

defined('MOODLE_INTERNAL') || die();

// $CFG is always set, but with this little wrapper PHPStorm won't give wrong error messages!
if (isset($CFG)) {
    require_once($CFG->libdir . '/formslib.php');
    require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
    require_once($CFG->dirroot . '/mod/grouptool/locallib.php');
}

/**
 * class representing the moodleform used in the administration tab
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupings_creation_form extends \moodleform {
    /** @var \context_module */
    protected $context = null;

    /**
     * Definition of administration form
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);
        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', ['id' => $cm->course]);

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

        $mform->addElement('hidden', 'start_bulkaction');
        $mform->setDefault('start_bulkaction', 1);
        $mform->setType('start_bulkaction', PARAM_BOOL);

        $groupingel = $mform->createElement('selectgroups', 'target', get_string('groupingselect', 'grouptool'));
        $options = ['' => get_string('choose', 'grouptool')];
        $options['-1'] = get_string('onenewgrouping', 'grouptool');
        $options['-2'] = get_string('onenewgroupingpergroup', 'grouptool');
        $groupingel->addOptGroup("", $options);
        if ($groupings = groups_get_all_groupings($course->id)) {
            $options = [];
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = strip_tags(format_string($grouping->name));
            }
            $groupingel->addOptGroup("————————————————————————", $options);
        }
        $mform->addElement($groupingel);
        $mform->addHelpButton('target', 'groupingselect', 'grouptool');

        $mform->addElement('text', 'name', get_string('groupingname', 'group'));
        $mform->setType('name', PARAM_TEXT);
        $mform->hideIf ('name', 'target', 'noteq', '-1');

        $grp = [];
        $grp[] = $mform->createElement('submit', 'createGroupings', get_string('create_assign_groupings',
                                                                               'grouptool'));
        $grp[] = $mform->createElement('cancel');
        $mform->addGroup($grp, 'actionbuttons', '', [' '], false);
        $mform->setType('actionbuttons', PARAM_RAW);
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

        if (($data['target'] == -1) && empty($data['name'])) {
            $errors['name'] = get_string('required');
        }
        if (($data['target'] == -1) && groups_get_grouping_by_name($data['courseid'], $data['name'])) {
            $errors['name'] = get_string('groupingnameexists', 'group', $data['name']);
        }

        return array_merge($parenterrors, $errors);
    }
}
