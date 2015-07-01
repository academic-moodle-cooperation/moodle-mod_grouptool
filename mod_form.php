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

        global $CFG, $DB, $PAGE, $OUTPUT;
        $mform = $this->_form;
        $maxregs = 0;
        $queues = 0;
        if ($update = optional_param('update', 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id('grouptool', $update);
            $course = $DB->get_record('course', array('id' => $cm->course));
            $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance));
            $sql = '
  SELECT MAX(regcnt)
    FROM (SELECT COUNT(reg.id) regcnt
            FROM {grouptool_registered} reg
            JOIN {grouptool_agrps} agrps ON reg.agrpid = agrps.id
           WHERE agrps.grouptoolid = :grouptoolid
                 AND reg.modified_by >= 0
        GROUP BY reg.userid) regcnts';
            $params = array('grouptoolid' => $cm->instance);
            $maxregs = $DB->get_field_sql($sql, $params);
            $sql = '
      SELECT COUNT(queue.id) queue
        FROM {grouptool_queued} queue
        JOIN {grouptool_agrps} agrps ON queue.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
             AND agrps.active = 1';
            $params = array('grouptoolid' => $cm->instance);
            $queues = $DB->get_field_sql($sql, $params);
        } else if ($course = optional_param('course', 0, PARAM_INT)) {
            $course = $DB->get_record('course', array('id' => $course));
        } else {
            $course = 0;
        }

        $mform->addElement('hidden', 'max_regs', $maxregs);
        $mform->setType('max_regs', PARAM_INT);
        /* -------------------------------------------------------------------------------
         * Adding the "general" fieldset, where all the common settings are showed
         */
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field!
        $mform->addElement('text', 'name', get_string('grouptoolname', 'grouptool'),
                           array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'grouptoolname', 'grouptool');

        // Adding the standard "intro" and "introformat" fields!
        $this->add_intro_editor(get_config('mod_grouptool', 'requiremodintro'),
                                get_string('description', 'grouptool'));

        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', true);

        $name = get_string('availabledate', 'grouptool');
        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'timeavailable', $name, $options);
        $mform->addHelpButton('timeavailable', 'availabledate', 'grouptool');
        $mform->setDefault('timeavailable', time());

        $name = get_string('duedate', 'grouptool');
        $mform->addElement('date_time_selector', 'timedue', $name, array('optional' => true));
        $mform->addHelpButton('timedue', 'duedate', 'grouptool');
        $mform->setDefault('timedue', date('U', strtotime('+1week 23:55', time())));

        $name = get_string('alwaysshowdescription', 'grouptool');
        $mform->addElement('advcheckbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'grouptool');
        $mform->setDefault('alwaysshowdescription', 1);
        $mform->disabledif ('alwaysshowdescription', 'timeavailable[enabled]', 'notchecked');

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
        $allowreg = get_config('mod_grouptool', 'allow_reg');
        $mform->setDefault('allow_reg', (($allowreg !== false) ? $allowreg : 1));
        $mform->addHelpButton('allow_reg', 'allow_reg', 'grouptool');

        $mform->addElement('selectyesno', 'show_members', get_string('show_members', 'grouptool'));
        $showmembers = get_config('mod_grouptool', 'show_members');
        $mform->setDefault('show_members', (($showmembers !== false) ? $showmembers : 0));
        $mform->addHelpButton('show_members', 'show_members', 'grouptool');

        $mform->addElement('selectyesno', 'immediate_reg', get_string('immediate_reg',
                                                                      'grouptool'));
        $immediatereg = get_config('mod_grouptool', 'immediate_reg');
        $mform->setDefault('immediate_reg', (($immediatereg !== false) ? $immediatereg : 0));
        $mform->addHelpButton('immediate_reg', 'immediate_reg', 'grouptool');
        $mform->disabledif ('immediate_reg', 'allow_reg', 'equal', 1);

        $mform->addElement('selectyesno', 'allow_unreg', get_string('allow_unreg', 'grouptool'));
        $allowunreg = get_config('mod_grouptool', 'allow_unreg');
        $mform->setDefault('allow_unreg', (($allowunreg !== false) ? $allowunreg : 0));
        $mform->addHelpButton('allow_unreg', 'allow_unreg', 'grouptool');
        $mform->disabledif ('allow_unreg', 'allow_reg', 'equal', 1);

        $size = array();
        $size[] = $mform->createElement('text', 'grpsize', get_string('size', 'grouptool'),
                                        array('size' => '5'));
        $size[] = $mform->createElement('checkbox', 'use_size', '', get_string('use_size',
                                                                               'grouptool'));
        // We have to clean this params by ourselves afterwards otherwise we get problems with texts getting mapped to 0!
        $mform->setType('grpsize', PARAM_RAW);
        $grpsize = get_config('mod_grouptool', 'grpsize');
        $mform->setDefault('grpsize', (($grpsize !== false) ? $grpsize : 3));
        $mform->setType('use_size', PARAM_BOOL);
        $usesize = get_config('mod_grouptool', 'use_size');
        $mform->setDefault('use_size', (($usesize !== false) ? $usesize : 0));
        $mform->addGroup($size, 'size_grp', get_string('size', 'grouptool'), ' ', false);
        $mform->addHelpButton('size_grp', 'size_grp', 'grouptool');
        $mform->disabledif ('grpsize', 'use_size', 'notchecked');
        $mform->disabledif ('grpsize', 'allow_reg', 'equal', 1);

        $mform->addElement('checkbox', 'use_individual', get_string('use_individual', 'grouptool'));
        $mform->setType('use_individual', PARAM_BOOL);
        $useindividual = get_config('mod_grouptool', 'use_individual');
        $mform->setDefault('use_individual', (($useindividual !== false) ? $useindividual : 0));
        $mform->addHelpButton('use_individual', 'use_individual', 'grouptool');
        $mform->disabledif ('use_individual', 'allow_reg', 'equal', 1);
        $mform->disabledif ('use_individual', 'use_size', 'notchecked');

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
                                         array('size' => '3'));
        $queue[] = $mform->createElement('checkbox', 'use_queue', '',
                                         get_string('use_queue', 'grouptool'));
        if ($queues > 0) {
            $mform->addElement('html', $OUTPUT->notification(get_string('queuespresenterror', 'grouptool'), 'notifymessage'));
        }
        $mform->addGroup($queue, 'queue_grp',
                         get_string('queues_max', 'grouptool'), ' ', false);
        $mform->setType('use_queue', PARAM_BOOL);
        $usequeue = get_config('mod_grouptool', 'use_queue');
        $mform->setDefault('use_queue', (($usequeue !== false) ? $usequeue : 0));
        $mform->disabledif ('use_queue', 'allow_reg', 'equal', 1);
        $mform->setType('queues_max', PARAM_INT);
        $maxqueues = get_config('mod_grouptool', 'max_queues');
        $mform->setDefault('queues_max', (($maxqueues !== false) ? $maxqueues : 1));
        $mform->addHelpButton('queue_grp', 'queuesgrp', 'grouptool');
        if ($queues > 0) {
            $queue[1]->setPersistantFreeze(1);
            $queue[1]->setValue(1);
            $queue[1]->freeze();
        } else {
            $mform->disabledif ('queues_max', 'use_queue', 'notchecked');
        }
        $mform->disabledif ('queues_max', 'allow_reg', 'equal', 1);

        // Prevent user from unsetting if user is registered in multiple groups!
        $mform->addElement('checkbox', 'allow_multiple', get_string('allow_multiple', 'grouptool'));
        if ($maxregs > 1) {
            $mform->addElement('hidden', 'multreg', 1);
        } else {
            $mform->addElement('hidden', 'multreg', 0);
        }
        $mform->setType('multreg', PARAM_BOOL);
        $mform->setType('allow_multiple', PARAM_BOOL);
        $allowmultiple = get_config('mod_grouptool', 'allow_multiple');
        $mform->setDefault('allow_multiple', (($allowmultiple !== false) ? $allowmultiple : 0));
        $mform->addHelpButton('allow_multiple', 'allow_multiple', 'grouptool');
        $mform->disabledif ('allow_multiple', 'allow_reg', 'eq', 0);

        $mform->addElement('text', 'choose_min', get_string('choose_min', 'grouptool'),
                           array('size' => '3'));
        $mform->setType('choose_min', PARAM_INT);
        $choosemin = get_config('mod_grouptool', 'choose_min');
        $mform->setDefault('choose_min', (($choosemin !== false) ? $choosemin : 1));
        $mform->disabledif ('choose_min', 'allow_reg', 'eq', 0);

        $mform->addElement('text', 'choose_max', get_string('choose_max', 'grouptool'),
                           array('size' => '3'));
        $mform->setType('choose_max', PARAM_INT);
        $choosemax = get_config('mod_grouptool', 'choose_max');
        $mform->setDefault('choose_max', (($choosemax !== false) ? $choosemax : 1));
        $mform->disabledif ('choose_max', 'allow_reg', 'eq', 0);

        if ($maxregs > 1) {
            $mform->freeze('allow_multiple');
        } else {
            $mform->disabledif ('choose_max', 'allow_multiple', 'notchecked');
            $mform->disabledif ('choose_min', 'allow_multiple', 'notchecked');
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
        $ifmemberadded = get_config('mod_grouptool', 'ifmemberadded');
        $mform->setDefault('ifmemberadded', (($ifmemberadded !== false) ? $ifmemberadded : GROUPTOOL_IGNORE));

        $mform->addElement('select', 'ifmemberremoved', get_string('ifmemberremoved', 'grouptool'),
                           $options);
        $mform->setType('ifmemberremoved', PARAM_INT);
        $mform->addHelpButton('ifmemberremoved', 'ifmemberremoved', 'grouptool');
        $ifmemberremoved = get_config('mod_grouptool', 'ifmemberremoved');
        $mform->setDefault('ifmemberremoved', (($ifmemberremoved !== false) ? $ifmemberremoved : GROUPTOOL_IGNORE));

        $options = array( GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
                          GROUPTOOL_DELETE_REF => get_string('delete_reference', 'grouptool'));
        $mform->addElement('select', 'ifgroupdeleted', get_string('ifgroupdeleted', 'grouptool'),
                           $options);
        $mform->setType('ifgroupdeleted', PARAM_INT);
        $mform->addHelpButton('ifgroupdeleted', 'ifgroupdeleted', 'grouptool');
        $ifgroupdeleted = get_config('mod_grouptool', 'ifgroupdeleted');
        $mform->setDefault('ifgroupdeleted', (($ifgroupdeleted !== false) ? $ifgroupdeleted : GROUPTOOL_RECREATE_GROUP));

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
        $parenterrors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['timedue']) && ($data['timedue'] <= $data['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }

        if (!empty($data['use_size'])
            && (($data['grpsize'] <= 0) || !ctype_digit($data['grpsize']))
            && empty($data['use_individual'])) {
            $errors['size_grp'] = get_string('grpsizezeroerror', 'grouptool');
        }
        if (!empty($data['instance'])) {
            $sql = '
     SELECT MAX(regcnt)
        FROM (
      SELECT COUNT(reg.id) regcnt
        FROM {grouptool_registered} reg
        JOIN {grouptool_agrps} agrps ON reg.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
             AND reg.modified_by >= 0
    GROUP BY reg.agrpid) regcnts';
            $params = array('grouptoolid' => $data['instance']);
            $maxgrpregs = $DB->get_field_sql($sql, $params);
            $sql = '
      SELECT MAX(regcnt)
        FROM (SELECT COUNT(reg.id) regcnt
                FROM {grouptool_registered} reg
                JOIN {grouptool_agrps} agrps ON reg.agrpid = agrps.id
               WHERE agrps.grouptoolid = :grouptoolid
                     AND reg.modified_by >= 0
            GROUP BY reg.userid) regcnts';
            $params = array('grouptoolid' => $data['instance']);
            $maxuserregs = $DB->get_field_sql($sql, $params);
            $sql = '
      SELECT MIN(regcnt)
        FROM (SELECT COUNT(reg.id) regcnt
                FROM {grouptool_registered} reg
                JOIN {grouptool_agrps} agrps ON reg.agrpid = agrps.id
               WHERE agrps.grouptoolid = :grouptoolid
                     AND reg.modified_by >= 0
            GROUP BY reg.userid) regcnts
       WHERE regcnt > 0';
            $params = array('grouptoolid' => $data['instance']);
            $minuserregs = $DB->get_field_sql($sql, $params);
            $sql = '
      SELECT COUNT(queue.id) queue
        FROM {grouptool_queued} queue
        JOIN {grouptool_agrps} agrps ON queue.agrpid = agrps.id
       WHERE agrps.grouptoolid = :grouptoolid
             AND agrps.active = 1';
            $params = array('grouptoolid' => $data['instance']);
            $queues = $DB->get_field_sql($sql, $params);
        } else {
            $maxgrpregs = 0;
            $maxuserregs = 0;
            $minuserregs = 0;
            $queues = 0;
        }
        if (!empty($data['use_size']) && ($data['grpsize'] < $maxgrpregs)
            && empty($data['use_individual'])) {
            if (empty($errors['size_grp'])) {
                $errors['size_grp'] = get_string('toomanyregs', 'grouptool');
            } else {
                $errors['size_grp'] .= get_string('toomanyregs', 'grouptool');
            }
        }

        if (!empty($data['use_queue']) && ($data['queues_max'] <= 0)) {
            $errors['queues_max'] = get_string('queuesizeerror', 'grouptool');
        }

        if (array_key_exists('use_queue', $data) && empty($data['use_queue']) && ($queues > 0)) {
            $errors['queue_grp'] = get_string('queuespresenterror', 'grouptool');
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

        if (!empty($data['allow_multiple']) && ($data['choose_max'] < $maxuserregs)) {
            if (isset($errors['choose_max'])) {
                $errors['choose_max'] .= html_writer::empty_tag('br').
                                         get_string('toomanyregspresent', 'grouptool',
                                                    $maxuserregs);
            } else {
                $errors['choose_max'] = get_string('toomanyregspresent', 'grouptool',
                                                   $maxuserregs);
            }
        }

        if (!empty($data['allow_multiple']) && !empty($minuserregs)
            && ($data['choose_min'] > $minuserregs)) {
            if (isset($errors['choose_min'])) {
                $errors['choose_min'] .= html_writer::empty_tag('br').
                                         get_string('toolessregspresent', 'grouptool',
                                                    $minuserregs);
            } else {
                $errors['choose_min'] = get_string('toolessregspresent', 'grouptool',
                                                   $minuserregs);
            }
        }

        return array_merge($parenterrors, $errors);
    }
}
