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
 * import_form.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->dirroot.'/mod/grouptool/lib.php');

/**
 * class representing the moodleform used in the import-tab
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @since         Moodle 2.8+
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Definition of import form
     */
    protected function definition() {

        global $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id' => $cm->course));

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'import');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:register_students', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed
             */
            $mform->addElement('header', 'groupuser_import', get_string('groupuser_import',
                                                                        'grouptool'));

            $active = new \mod_grouptool\output\sortlist($course->id, $cm, \mod_grouptool::FILTER_ACTIVE);
            $inactive = new \mod_grouptool\output\sortlist($course->id, $cm, \mod_grouptool::FILTER_INACTIVE);

            $groups = $mform->createElement('selectgroups', 'group', get_string('choose_targetgroup', 'grouptool'), null,
                                            array('size' => 15));

            if (!empty($active->groups)) {
                $options = array();
                foreach ($active->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('activegroups', 'grouptool'), $options);
            }

            if (!empty($inactive->groups)) {
                $options = array();
                foreach ($inactive->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('inactivegroups', 'grouptool'), $options);
            }
            $groups->setMultiple(true);
            $mform->addElement($groups);
            $mform->setType('group', PARAM_INT);
            $mform->addRule('group', null, 'required', null, 'client');

            $mform->addElement('textarea', 'data', get_string('userlist', 'grouptool'),
                    array('wrap' => 'virtual',
                          'rows' => '20',
                          'cols' => '50'));
            $mform->addHelpButton('data', 'userlist', 'grouptool');
            $mform->addRule('data', null, 'required', null, 'client');
            $mform->addRule('data', null, 'required', null, 'server');

            $mform->addElement('advcheckbox', 'forceregistration', '', '&nbsp;'.get_string('forceregistration', 'grouptool'));
            $mform->addHelpButton('forceregistration', 'forceregistration', 'grouptool');
            if ($forceimportreg = get_config('mod_grouptool', 'force_importreg')) {
                $mform->setDefault('forceregistration', $forceimportreg);
            }

            $mform->addElement('advcheckbox', 'includedeleted', '', get_string('includedeleted', 'grouptool'));
            $mform->addHelpButton('includedeleted', 'includedeleted', 'grouptool');
            $mform->setAdvanced('includedeleted');
            $mform->setDefault('includedeleted', 0);

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

