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
 * class representing the moodleform used when resizing groups
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.8
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_resize_form extends \moodleform {
    /**
     * Definition of resize form
     */
    protected function definition() {

        global $CFG, $PAGE, $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'instance');
        $mform->setDefault('instance', $this->_customdata['instance']);
        $mform->setType('instance', PARAM_INT);

        $context = \context_module::instance($this->_customdata['id']);
        $cm = get_coursemodule_from_instance('grouptool', $this->_customdata['instance']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $this->course = $course;
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);
        $group = $DB->get_record('groups', array('id' => $this->_customdata['resize']));
        $coursecontext = \context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_admin');
        $mform->setType('tab', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'resize');
        $mform->setType('resize', PARAM_INT);
        $mform->setDefault('resize', $this->_customdata['resize']);

        $mform->addElement('hidden', 'name');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $group->name);

        $mform->addElement('text', 'size', get_string('size'));
        $mform->setType('size', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setDefault('courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $grp = array();
        $grp[] = $mform->createElement('submit', 'submit', get_string('savechanges'));
        $grp[] = $mform->createElement('cancel');
        $mform->addGroup($grp, 'actionbuttons', '', array(' '), false);
        $mform->setType('actionbuttons', PARAM_RAW);
    }

    /**
     * Validation for resize form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = parent::validation($data, $files);
        $sql = '
   SELECT COUNT(reg.id) AS regcnt
     FROM {grouptool_agrps} agrps
LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrps.id AND reg.modified_by >= 0
    WHERE agrps.grouptoolid = :grouptoolid AND agrps.groupid = :groupid';
        $params = array('grouptoolid' => $data['instance'], 'groupid' => $data['resize']);
        $regs = $DB->count_records_sql($sql, $params);
        if (($data['size'] != '') && (clean_param($data['size'], PARAM_INT) <= 0)) {
                $errors['size'] = get_string('grpsizezeroerror', 'grouptool');
        } else if (!empty($regs) && $data['size'] < $regs) {
            $errors['size'] = get_string('toomanyregs', 'grouptool');
        } else {
            $DB->set_field('grouptool_agrps', 'grpsize', $data['size'],
                           array('groupid' => $data['resize'], 'grouptoolid' => $data['instance']));
            if ($data['size'] != $DB->get_field('grouptool_agrps', 'grpsize', array('groupid'     => $data['resize'],
                                                                                    'grouptoolid' => $data['instance']))) {
                // Error happened...
                $errors['size'] = get_string('couldnt_resize_group', 'grouptool', $data['size']);
            }
        }

        return $errors;
    }
}

