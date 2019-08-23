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
 * Contains mod_grouptool's unregister form
 *
 * @package   mod_grouptool
 * @author    Hannes Laimer
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

use mod_grouptool\output\sortlist;

defined('MOODLE_INTERNAL') || die();

// Global variable $CFG is always set, but with this little wrapper PHPStorm won't give wrong error messages!
if (isset($CFG)) {
    require_once($CFG->libdir . '/formslib.php');
    require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
    require_once($CFG->dirroot . '/mod/grouptool/lib.php');
}

/**
 * class representing the moodleform used in the unregister-tab
 *
 * @package   mod_grouptool
 * @author    Hannes Laimer
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unregister_form extends \moodleform {
    /** @var \context_module */
    private $context = null;

    /**
     * Definition of unregister form
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition() {
        global $DB;

        $mdlform = $this->_form;

        $mdlform->addElement('hidden', 'id');
        $mdlform->setDefault('id', $this->_customdata['id']);
        $mdlform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('grouptool', $this->_customdata['id']);
        $course = $DB->get_record('course', ['id' => $cm->course]);

        $mdlform->addElement('hidden', 'tab');
        $mdlform->setDefault('tab', 'unregister');
        $mdlform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/grouptool:unregister_students', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed
             */
            $mdlform->addElement('header', 'groupuser_unregister', get_string('groupuser_unregister',
                                                                        'grouptool'));

            $active = new sortlist($course->id, $cm, \mod_grouptool::FILTER_ACTIVE);
            $inactive = new sortlist($course->id, $cm, \mod_grouptool::FILTER_INACTIVE);

            $groups = $mdlform->createElement('selectgroups', 'groups', get_string('choose_targetgroup_unregister', 'grouptool'),
                null, ['size' => 15]);

            if (!empty($active->groups)) {
                $options = [];
                foreach ($active->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('activegroups', 'grouptool'), $options);
            }

            if (!empty($inactive->groups)) {
                $options = [];
                foreach ($inactive->groups as $grp) {
                    $options[$grp->groupid] = $grp->name;
                }
                $groups->addOptGroup(get_string('inactivegroups', 'grouptool'), $options);
            }
            $groups->setMultiple(true);
            $mdlform->addElement($groups);
            $mdlform->setType('groups', PARAM_INT);
            $mdlform->addRule('groups', null, 'required', null, 'client');

            $mdlform->addElement('textarea', 'data', get_string('userlist', 'grouptool'), [
                'wrap' => 'virtual',
                'rows' => '20',
                'cols' => '50'
            ]);
            $mdlform->addHelpButton('data', 'userlist', 'grouptool');
            $mdlform->addRule('data', null, 'required', null, 'client');
            $mdlform->addRule('data', null, 'required', null, 'server');

            $mdlform->addElement('advcheckbox', 'unregfrommgroups', '', get_string('unregfrommgroups', 'grouptool'));
            $mdlform->addHelpButton('unregfrommgroups', 'unregfrommgroups', 'grouptool');
            $mdlform->setDefault('unregfrommgroups', true);

            $mdlform->addElement('submit', 'submitbutton', get_string('unregisterbutton', 'grouptool'));
        }
    }

    /**
     * Validation for unregister form
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
        $errors = parent::validation($data, $files);
        if (empty($data['groups']) || ($data['groups'] == 'none')) {
            $errors['groups'] = get_string('choose_group', 'grouptool');
        }
        return $errors;
    }
}

