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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * mod_form.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
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
        $max_regs = 0;
        if ($update = optional_param('update', 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id('grouptool', $update);
            $course = $DB->get_record('course', array('id'=>$cm->course));
            $grouptool = $DB->get_record('grouptool', array('id'=>$cm->instance));
            $sql = '
 SELECT MAX(regcnt)
    FROM (
  SELECT COUNT(reg.id) as regcnt
    FROM {grouptool_registered} as reg
    JOIN {grouptool_agrps} as agrps ON reg.agrpid = agrps.id
   WHERE agrps.grouptoolid = :grouptoolid
GROUP BY reg.userid) as regcnts';
            $params = array('grouptoolid' => $cm->instance);
            $max_regs = $DB->get_field_sql($sql, $params);
        } else if ($course = optional_param('course', 0, PARAM_INT)) {
            $course = $DB->get_record('course', array('id'=>$course));
        } else {
            $course = 0;
        }

        $mform->addElement('hidden', 'max_regs', $max_regs);
        $mform->setType('max_regs', PARAM_INT);
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
        $this->add_intro_editor($CFG->grouptool_requiremodintro,
                                get_string('description', 'grouptool'));

                                
        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', true);

        $name = get_string('availabledate', 'grouptool');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'timeavailable', $name, $options);
        $mform->addHelpButton('timeavailable', 'availabledate', 'grouptool');
        $mform->setDefault('timeavailable', time());

        $name = get_string('duedate', 'grouptool');
        $mform->addElement('date_time_selector', 'timedue', $name, array('optional'=>true));
        $mform->addHelpButton('timedue', 'duedate', 'grouptool');
        $mform->setDefault('timedue', date('U', strtotime('+1week 23:55', time())));

        $name = get_string('alwaysshowdescription', 'grouptool');
        $mform->addElement('advcheckbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'grouptool');
        $mform->setDefault('alwaysshowdescription', 1);
        $mform->disabledIf('alwaysshowdescription', 'timeavailable[enabled]', 'notchecked');

        /*
         * ---------------------------------------------------------
         */

        /* -------------------------------------------------------------------------------
         * Adding the "grouptool" fieldset, where all individual-settings are made
         * (except of active-groups)
         */
        $mform->addElement('header', 'grouptoolfieldset', get_string('grouptoolfieldset',
                                                                     'grouptool'));
        $mform->setExpanded('grouptoolfieldset');

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

        $mform->addElement('selectyesno', 'allow_unreg', get_string('allow_unreg', 'grouptool'));
        $mform->setDefault('allow_unreg',
                           (!empty($CFG->grouptool_allow_unreg) ? $CFG->grouptool_allow_unreg : 0));
        $mform->addHelpButton('allow_unreg', 'allow_unreg', 'grouptool');
        $mform->disabledIf('allow_unreg', 'allow_reg', 'equal', 1);

        $size = array();
        $size[] = $mform->createElement('text', 'grpsize', get_string('size', 'grouptool'),
                                        array('size'=>'5'));
        $size[] = $mform->createElement('checkbox', 'use_size', '', get_string('use_size',
                                                                               'grouptool'));
        //We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0
        $mform->setType('grpsize', PARAM_RAW);
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


        /*
         * ---------------------------------------------------------------------
         */

        /* ---------------------------------------------------------------------
         * Adding the queue and multiple registrations fieldset,
         * where all settings related to queues and multiple registrations
         * are made (except of active-groups)
         */
        $mform->addElement('header', 'queue_and_multiple_reg',
                           get_string('queue_and_multiple_reg_title', 'grouptool'));
        
        $queue = array();
        $queue[] = $mform->createElement('text', 'queues_max',
                                         get_string('queues_max', 'grouptool'),
                                         array('size'=>'3'));
        $queue[] = $mform->createElement('checkbox', 'use_queue', '',
                                         get_string('use_queue', 'grouptool'));
        $mform->addGroup($queue, 'queue_grp',
                         get_string('queues_max', 'grouptool'), ' ', false);
        $mform->setType('use_queue', PARAM_BOOL);
        $mform->setDefault('use_queue',
                           (!empty($CFG->grouptool_use_queue) ? $CFG->grouptool_use_queue : 0));
        $mform->disabledIf('use_queue', 'allow_reg', 'equal', 1);
        $mform->setType('queues_max', PARAM_INT);
        $mform->setDefault('queues_max',
                           (!empty($CFG->grouptool_queues_max) ? $CFG->groutpoo_queues_max : 1));
        $mform->addHelpButton('queue_grp', 'queuesgrp', 'grouptool');
        $mform->disabledIf('queues_max', 'use_queue', 'notchecked');
        $mform->disabledIf('queues_max', 'allow_reg', 'equal', 1);

        //prevent user from unsetting if user is registered in multiple groups
        $mform->addElement('checkbox', 'allow_multiple', get_string('allow_multiple', 'grouptool'));
        if($max_regs > 1) {
            $mform->addElement('hidden', 'multreg', 1);
        } else {
            $mform->addElement('hidden', 'multreg', 0);
        }
        $mform->setType('multreg', PARAM_BOOL);
        $mform->setType('allow_multiple', PARAM_BOOL);
        $allowmultipledefault = (!empty($CFG->grouptool_allow_multiple) ? $CFG->grouptool_allow_multiple
                                                                        : 0);
        $mform->setDefault('allow_multiple', $allowmultipledefault);
        $mform->addHelpButton('allow_multiple', 'allow_multiple', 'grouptool');
        $mform->disabledIf('allow_multiple', 'allow_reg', 'eq', 0);

        $mform->addElement('text', 'choose_min', get_string('choose_min', 'grouptool'),
                           array('size'=>'3'));
        $mform->setType('choose_min', PARAM_INT);
        $mform->setDefault('choose_min',
                           (!empty($CFG->grouptool_choose_min) ? $CFG->grouptool_choose_min : 1));
        $mform->disabledIf('choose_min', 'allow_reg', 'eq', 0);

        $mform->addElement('text', 'choose_max', get_string('choose_max', 'grouptool'),
                           array('size'=>'3'));
        $mform->setType('choose_max', PARAM_INT);
        $mform->setDefault('choose_max',
                           (!empty($CFG->grouptool_choose_max) ? $CFG->grouptool_choose_max : 1));
        $mform->disabledIf('choose_max', 'allow_reg', 'eq', 0);

        if($max_regs > 1) {
            //$mform->setConstant('allow_multiple', $allowmultipledefault);
            $mform->freeze('allow_multiple');
        } else {
            $mform->disabledIf('choose_max', 'allow_multiple', 'notchecked');
            $mform->disabledIf('choose_min', 'allow_multiple', 'notchecked');
        }
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

        $mform->addElement('select', 'ifmemberremoved', get_string('ifmemberremoved', 'grouptool'),
                           $options);
        $mform->setType('ifmemberremoved', PARAM_INT);
        $mform->addHelpButton('ifmemberremoved', 'ifmemberremoved', 'grouptool');
        $mform->setDefault('ifmemberremoved', (!empty($CFG->grouptool_ifmemberremoved) ?
                                                 $CFG->grouptool_ifmemberremoved :
                                                 GROUPTOOL_IGNORE));

        $options = array( GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
                          GROUPTOOL_DELETE_REF => get_string('delete_reference', 'grouptool'));
        $mform->addElement('select', 'ifgroupdeleted', get_string('ifgroupdeleted', 'grouptool'),
                           $options);
        $mform->setType('ifgroupdeleted', PARAM_INT);
        $mform->addHelpButton('ifgroupdeleted', 'ifgroupdeleted', 'grouptool');
        $mform->setDefault('ifgroupdeleted', (!empty($CFG->grouptool_ifgroupdeleted) ?
                                                 $CFG->grouptool_ifgroupdeleted :
                                                 GROUPTOOL_RECREATE_GROUP));

        /*
         * ---------------------------------------------------------
         */

        /* ------------------------------------------------------------------------------
         * add standard elements, common to all modules
         */
        $this->standard_coursemodule_elements();
        
        $mform->setConstant('groupmode', VISIBLEGROUPS);
        $mform->freeze('groupmode');
        
        /* ------------------------------------------------------------------------------
         * add standard buttons, common to all modules
         */
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $DB;
        $parent_errors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['timedue']) && ($data['timedue'] <= $data['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }

        if (!empty($data['use_size'])
            && (($data['grpsize'] <= 0) || !ctype_digit($data['grpsize']))
            && empty($data['use_individual'])) {
            $errors['size_grp'] = get_string('grpsizezeroerror', 'grouptool');
        }
        if(!empty($data['instance'])) {
            $sql = '
     SELECT MAX(regcnt)
        FROM (
      SELECT COUNT(reg.id) as regcnt
        FROM {grouptool_registered} as reg
        JOIN {grouptool_agrps} as agrps ON reg.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
    GROUP BY reg.agrpid) as regcnts';
            $params = array('grouptoolid' => $data['instance']);
            $max_grp_regs = $DB->get_field_sql($sql, $params);
            $sql = '
     SELECT MAX(regcnt)
        FROM (
      SELECT COUNT(reg.id) as regcnt
        FROM {grouptool_registered} as reg
        JOIN {grouptool_agrps} as agrps ON reg.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
    GROUP BY reg.userid) as regcnts';
            $params = array('grouptoolid' => $data['instance']);
            $max_user_regs = $DB->get_field_sql($sql, $params);
            $sql = '
     SELECT MIN(regcnt)
        FROM (
      SELECT COUNT(reg.id) as regcnt
        FROM {grouptool_registered} as reg
        JOIN {grouptool_agrps} as agrps ON reg.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
    GROUP BY reg.userid) as regcnts
       WHERE regcnt > 0';
            $params = array('grouptoolid' => $data['instance']);
            $min_user_regs = $DB->get_field_sql($sql, $params);
            $sql = '
      SELECT COUNT(queue.id) as queue
        FROM {grouptool_queued} as queue
        JOIN {grouptool_agrps} as agrps ON queue.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid';
            $params = array('grouptoolid' => $data['instance']);
            $queues = $DB->get_field_sql($sql, $params);
        } else {
            $max_grp_regs = 0;
            $max_user_regs = 0;
            $min_user_regs = 0;
            $queues = 0;
        }
        if (!empty($data['use_size']) && ($data['grpsize'] < $max_grp_regs)
            && empty($data['use_individual'])) {
            if(empty($errors['size_grp'])) {
                $errors['size_grp'] = get_string('toomanyregs', 'grouptool');
            } else {
                $errors['size_grp'] .= get_string('toomanyregs', 'grouptool');
            }
        }

        if($queues && empty($data['use_queue']) && empty($data['warningconfirm'])) {
//            $errors['use_queue'] = get_string('queuespresent', 'grouptool');
        }

        if (!empty($data['use_queue']) && ($data['queues_max'] <= 0)) {
            $errors['queues_max'] = get_string('queuesizeerror', 'grouptool');
        }

        if (!empty($data['allow_multiple']) && ($data['choose_min'] <= 0)) {
            $errors['choose_min'] = get_string('mustbeposint', 'grouptool');
        }

        if (!empty($data['allow_multiple']) && ($data['choose_max'] <= 0)) {
            $errors['choose_max'] = get_string('mustbeposint', 'grouptool');
        }

        if (!empty($data['allow_multiple']) && ($data['choose_min'] > $data['choose_max'])) {
            if (isset($errors['choose_max'])) {
                $errors['choose_max'] .= html_writer::empty_tag('br').
                                         get_string('mustbegtoeqmin', 'grouptool');
            } else {
                $errors['choose_max'] = get_string('mustbegtoeqmin', 'grouptool');
            }
        }
        
        if (!empty($data['allow_multiple']) && ($data['choose_max'] < $max_user_regs)) {
            if (isset($errors['choose_max'])) {
                $errors['choose_max'] .= html_writer::empty_tag('br').
                                         get_string('toomanyregspresent', 'grouptool',
                                                    $max_user_regs);
            } else {
                $errors['choose_max'] = get_string('toomanyregspresent', 'grouptool',
                                                   $max_user_regs);
            }
        }

        if (!empty($data['allow_multiple']) && !empty($min_user_regs)
            && ($data['choose_min'] > $min_user_regs)) {
            if (isset($errors['choose_min'])) {
                $errors['choose_min'] .= html_writer::empty_tag('br').
                                         get_string('toolessregspresent', 'grouptool',
                                                    $min_user_regs);
            } else {
                $errors['choose_min'] = get_string('toolessregspresent', 'grouptool',
                                                   $min_user_regs);
            }
        }

        return array_merge($parent_errors, $errors);
    }
}
