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
 * Contains mod_grouptool's group rename form
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
 * class representing the moodleform used for renaming groups
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_rename_form extends \moodleform {
    /** @var \stdClass */
    private $course = null;

    /**
     * Definition of rename form
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

        $mform->addElement('hidden', 'instance');
        $mform->setDefault('instance', $this->_customdata['instance']);
        $mform->setType('instance', PARAM_INT);

        $cm = get_coursemodule_from_instance('grouptool', $this->_customdata['instance']);
        $course = $DB->get_record('course', ['id' => $cm->course]);
        $this->course = $course;

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'rename');
        $mform->setType('rename', PARAM_INT);
        $mform->setDefault('rename', $this->_customdata['rename']);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $grp = [];
        $grp[] = $mform->createElement('submit', 'submit', get_string('savechanges'));
        $grp[] = $mform->createElement('cancel');
        $mform->addGroup($grp, 'actionbuttons', '', [' '], false);
        $mform->setType('actionbuttons', PARAM_RAW);

    }

    /**
     * Validation for rename form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if (empty($data['name'])) {
            $errors['name'] = get_string('choose_group', 'grouptool');
        } else {
            $group = groups_get_group_by_name($this->course->id, $data['name']);
            $group = $DB->get_record('groups', ['id' => $group]);
            if (!empty($group) && ($group->id != $data['rename'])) {
                $errors['name'] = get_string('groupnameexists', 'group', $data['name']);
            }
        }

        return $errors;
    }
}
