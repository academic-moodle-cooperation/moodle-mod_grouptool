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
 * The main grouptool configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

/**
 * Module instance settings form
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {

        global $CFG, $DB, $PAGE;
        $mform = $this->_form;

        if ($update = optional_param('update', 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id('grouptool', $update);
            $course = $DB->get_record('course', array('id'=>$cm->course));
        } else if ($course = optional_param('course', 0, PARAM_INT)) {
            $course = $DB->get_record('course', array('id'=>$course));
        } else {
            $course = 0;
        }

        /* -------------------------------------------------------------------------------
         * Adding the "general" fieldset, where all the common settings are showed
         */
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field!
        $mform->addElement('text', 'name', get_string('grouptoolname', 'grouptool'),
                           array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'grouptoolname', 'grouptool');

        // Adding the standard "intro" and "introformat" fields!
        $this->add_intro_editor();

        $mform->addElement('date_time_selector', 'timeavailable',
                           get_string('availabledate', 'grouptool'), array('optional'=>true));
        $mform->setDefault('timeavailable', strtotime('now', time()));
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'grouptool'),
                           array('optional'=>true));
        $mform->setDefault('timedue', date('U', strtotime('+1week 23:55', time())));

        /*
         * ---------------------------------------------------------
         */

        /* -------------------------------------------------------------------------------
         * Adding the "grouptool" fieldset, where all individual-settings are made
         * (except of active-groups)
         */
        $mform->addElement('header', 'grouptoolfieldset', get_string('grouptoolfieldset',
                                                                     'grouptool'));

        $mform->addElement('selectyesno', 'allow_reg', get_string('allow_reg', 'grouptool'));
        $mform->setDefault('allow_reg',
                           (isset($CFG->grouptool_allow_reg) ? $CFG->grouptool_allow_reg : 1));
        $mform->addHelpButton('allow_reg', 'allow_reg', 'grouptool');

        $mform->addElement('selectyesno', 'show_members', get_string('show_members', 'grouptool'));
        $mform->setDefault('show_members',
                           (!empty($CFG->grouptool_show_members) ? $CFG->grouptool_show_members
                                                                 : 0));
        $mform->addHelpButton('show_members', 'show_members', 'grouptool');

        $mform->addElement('selectyesno', 'immediate_reg', get_string('immediate_reg',
                                                                      'grouptool'));
        $mform->setDefault('immediate_reg',
                           (!empty($CFG->grouptool_immediate_reg) ? $CFG->grouptool_immediate_reg
                                                                  : 0));
        $mform->addHelpButton('immediate_reg', 'immediate_reg', 'grouptool');
        $mform->disabledIf('immediate_reg', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('immediate_reg');

        $mform->addElement('selectyesno', 'allow_unreg', get_string('allow_unreg', 'grouptool'));
        $mform->setDefault('allow_unreg',
                           (!empty($CFG->grouptool_allow_unreg) ? $CFG->grouptool_allow_unreg : 0));
        $mform->addHelpButton('allow_unreg', 'allow_unreg', 'grouptool');
        $mform->disabledIf('allow_unreg', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('allow_unreg');

        $size = array();
        $size[] = $mform->createElement('text', 'grpsize', get_string('size', 'grouptool'),
                                        array('size'=>'5'));
        $size[] = $mform->createElement('checkbox', 'use_size', '', get_string('use_size',
                                                                               'grouptool'));
        $mform->setType('grpsize', PARAM_INT);
        $mform->setDefault('grpsize',
                           (!empty($CFG->grouptool_grpsize) ? $CFG->grouptool_grpsize : 3));
        $mform->setType('use_size', PARAM_BOOL);
        $mform->setDefault('use_size',
                           (!empty($CFG->grouptool_use_size) ? $CFG->grouptool_use_size : 0));
        $mform->addGroup($size, 'size_grp', get_string('size', 'grouptool'), ' ', false);
        $mform->addHelpButton('size_grp', 'size_grp', 'grouptool');
        $mform->disabledIf('grpsize', 'use_size', 'notchecked');
        $mform->disabledIf('grpsize', 'allow_reg', 'equal', 1);

        $mform->addElement('checkbox', 'use_individual', get_string('use_individual', 'grouptool'));
        $mform->setType('use_individual', PARAM_BOOL);
        $mform->setDefault('use_individual',
                           (!empty($CFG->grouptool_use_individual) ? $CFG->grouptool_use_individual
                                                                   : 0));
        $mform->addHelpButton('use_individual', 'use_individual', 'grouptool');
        $mform->disabledIf('use_individual', 'allow_reg', 'equal', 1);
        $mform->disabledIf('use_individual', 'use_size', 'notchecked');

        $mform->addElement('checkbox', 'use_queue', get_string('use_queue', 'grouptool'));
        $mform->setType('use_queue', PARAM_BOOL);
        $mform->setDefault('use_queue',
                           (!empty($CFG->grouptool_use_queue) ? $CFG->grouptool_use_queue : 0));
        $mform->addHelpButton('use_queue', 'use_queue', 'grouptool');
        $mform->disabledIf('use_queue', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('use_queue');

        $mform->addElement('text', 'queues_max', get_string('queues_max', 'grouptool'),
                           array('size'=>'3'));
        $mform->setType('queues_max', PARAM_INT);
        $mform->setDefault('queues_max',
                           (!empty($CFG->grouptool_queues_max) ? $CFG->groutpoo_queues_max : 1));
        $mform->addHelpButton('queues_max', 'queues_max', 'grouptool');
        $mform->disabledIf('queues_max', 'use_queue', 'notchecked');
        $mform->disabledIf('queues_max', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('queues_max');

        $mform->addElement('checkbox', 'allow_multiple', get_string('allow_multiple', 'grouptool'));
        $mform->setType('allow_multiple', PARAM_BOOL);
        $mform->setDefault('allow_multiple',
                           (!empty($CFG->grouptool_allow_multiple) ? $CFG->grouptool_allow_multiple
                                                                   : 0));
        $mform->addHelpButton('allow_multiple', 'allow_multiple', 'grouptool');
        $mform->disabledIf('allow_multiple', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('allow_multiple');

        $mform->addElement('text', 'choose_min', get_string('choose_min', 'grouptool'),
                           array('size'=>'3'));
        $mform->setType('choose_min', PARAM_INT);
        $mform->setDefault('choose_min',
                           (!empty($CFG->grouptool_choose_min) ? $CFG->grouptool_choose_min : 1));
        $mform->disabledIf('choose_min', 'allow_multiple', 'notchecked');
        $mform->disabledIf('choose_min', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('choose_min');

        $mform->addElement('text', 'choose_max', get_string('choose_max', 'grouptool'),
                           array('size'=>'3'));
        $mform->setType('choose_max', PARAM_INT);
        $mform->setDefault('choose_max',
                           (!empty($CFG->grouptool_choose_max) ? $CFG->grouptool_choose_max : 1));
        $mform->disabledIf('choose_max', 'allow_multiple', 'notchecked');
        $mform->disabledIf('choose_max', 'allow_reg', 'equal', 1);
        $mform->setAdvanced('choose_max');

        /*
         * ---------------------------------------------------------
         */

        /* -------------------------------------------------------------------------------
         * Adding the "moodlesync" fieldset, where all settings influencing behaviour
         * if groups/groupmembers are added/deleted in moodle are made
         */
        $mform->addElement('header', 'moodlesync', get_string('moodlesync', 'grouptool'));
        $mform->addHelpButton('moodlesync', 'moodlesync', 'grouptool');

        $options = array( GROUPTOOL_IGNORE => get_string('ignorechanges', 'grouptool'),
                          GROUPTOOL_FOLLOW => get_string('followchanges', 'grouptool')
                          );

        $mform->addElement('select', 'ifmemberadded', get_string('ifmemberadded', 'grouptool'),
                           $options);
        $mform->setType('ifmemberadded', PARAM_INT);
        $mform->addHelpButton('ifmemberadded', 'ifmemberadded', 'grouptool');
        $mform->setDefault('ifmemberadded', (!empty($CFG->grouptool_ifmemberadded) ?
                                                 $CFG->grouptool_ifmemberadded :
                                                 GROUPTOOL_IGNORE));
        $mform->setAdvanced('ifmemberadded');

        $mform->addElement('select', 'ifmemberremoved', get_string('ifmemberremoved', 'grouptool'),
                           $options);
        $mform->setType('ifmemberremoved', PARAM_INT);
        $mform->addHelpButton('ifmemberremoved', 'ifmemberremoved', 'grouptool');
        $mform->setDefault('ifmemberremoved', (!empty($CFG->grouptool_ifmemberremoved) ?
                                                 $CFG->grouptool_ifmemberremoved :
                                                 GROUPTOOL_IGNORE));
        $mform->setAdvanced('ifmemberremoved');

        $options = array( GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
                          GROUPTOOL_DELETE_REF => get_string('delete_reference', 'grouptool'));
        $mform->addElement('select', 'ifgroupdeleted', get_string('ifgroupdeleted', 'grouptool'),
                           $options);
        $mform->setType('ifgroupdeleted', PARAM_INT);
        $mform->addHelpButton('ifgroupdeleted', 'ifgroupdeleted', 'grouptool');
        $mform->setDefault('ifgroupdeleted', (!empty($CFG->grouptool_ifgroupdeleted) ?
                                                 $CFG->grouptool_ifgroupdeleted :
                                                 GROUPTOOL_RECREATE_GROUP));
        $mform->setAdvanced('ifgroupdeleted');

        /*
         * ---------------------------------------------------------
         */

        /* ------------------------------------------------------------------------------
         * add standard elements, common to all modules
         */
        $this->standard_coursemodule_elements();
        /* ------------------------------------------------------------------------------
         * add standard buttons, common to all modules
         */
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $parent_errors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['timedue']) && ($data['timedue'] <= $data['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }

        if (!empty($data['use_size']) && ($data['grpsize'] <= 0) && empty($data['use_individual'])) {
            $errors['size_grp'] = get_string('grpsizezeroerror', 'grouptool');
        }

        if (!empty($data['use_queue']) && ($data['queues_max'] <= 0)) {
            $errors['queues_max'] = get_string('queuesizeerror', 'grouptool');
        }

        if (!empty($data['use_size']) && !empty($data['use_individual'])) {
            foreach ($data['grouplist'] as $group_id => $curgroup) {
                if (clean_param($curgroup['grpsize'], PARAM_INT) <= 0) {
                    if (!isset($errors['grouplist']) || ($errors['grouplist'] == '')) {
                        $errors['grouplist'] = get_string('grpsizezeroerror', 'grouptool').' '.$curgroup['name'];
                    } else {
                        $errors['grouplist'] .= ', '.$curgroup['name'];
                    }
                }
            }
        }

        if (!empty($data['allow_multiple']) && ($data['choose_min'] <= 0)) {
            $errors['choose_min'] = get_string('mustbeposint', 'grouptool');
        }

        if (!empty($data['allow_multiple']) && ($data['choose_max'] <= 0)) {
            $errors['choose_max'] = get_string('mustbeposint', 'grouptool');
        }

        if (!empty($data['allow_multiple']) && ($data['choose_min'] > $data['choose_max'])) {
            if (isset($errors['choose_max'])) {
                $errors['choose_max'] .= html_writer::empty_tag('br').get_string('mustbegtoeqmin', 'grouptool');
            } else {
                $errors['choose_max'] = get_string('mustbegtoeqmin', 'grouptool');
            }
        }

        return array_merge($parent_errors, $errors);
    }
}
