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
 * Contains mod_grouptool's confirmation form for imports
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
    require_once($CFG->dirroot . '/mod/grouptool/lib.php');
}

/**
 * class representing the moodleform used in the import-tab to confirm import
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_confirm_form extends \moodleform {
    /** @var \context_module */
    private $context = null;
    /**
     * Definition of import form
     *
     * @throws \coding_exception
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'import');
        $mform->setType('tab', PARAM_TEXT);

        foreach ($this->_customdata['groups'] as $group) {
            $mform->addElement('hidden', "group[$group]");
            $mform->setDefault("group[$group]", $group);
            $mform->setType("group[$group]", PARAM_INT);
        }

        $mform->addElement('hidden', 'data');
        $mform->setDefault('data', $this->_customdata['data']);
        $mform->setType('data', PARAM_NOTAGS);

        $mform->addElement('hidden', 'forceregistration');
        $mform->setDefault('forceregistration', $this->_customdata['forceregistration']);
        $mform->setType('forceregistration', PARAM_BOOL);

        $mform->addElement('html', $this->_customdata['confirmmessage']);

        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'confirm', get_string('continue'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
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

