<?php
// This file is part of Moodle - http://moodle.org/
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
 * @package    mod
 * @subpackage grouptool
 * @copyright  2012 Philipp Hager <e0803285@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

/**
 * Module instance settings form
 *
 * @package    mod
 * @subpackage grouptool
 * @copyright  2012 Philipp Hager <e0803285@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {

        global $CFG, $COURSE, $DB, $PAGE;
        $mform = $this->_form;

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
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

        // Adding the standard "intro" and "introformat" fields
        $this->add_intro_editor();

        $mform->addElement('date_time_selector', 'timeavailable',
                           get_string('availabledate', 'grouptool'), array('optional'=>true));
        $mform->setDefault('timeavailable', strtotime('now', time()));
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'grouptool'),
                           array('optional'=>true));
        $mform->setDefault('timedue', date('U', strtotime('+1week 23:55', time())));

        //-------------------------------------------------------------------------------

        //-------------------------------------------------------------------------------
        // Adding the "grouptool" fieldset, where all individual-settings are made
        // (except of active-groups)
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
        //-------------------------------------------------------------------------------

        //-------------------------------------------------------------------------------
        // Adding the "moodlesync" fieldset, where all settings influencing behaviour
        // if groups/groupmembers are added/deleted in moodle are made
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

        //-------------------------------------------------------------------------------

        //-------------------------------------------------------------------------------
        // Adding the "active groups" fieldset, where all the group-settings are made
        $mform->addElement('header', 'agroups', get_string('agroups', 'grouptool'));

        /***************************************
         * INSERT CUSTOM ELEMENT HERE
        **************************************/
        // Register our custom form control
        $nogroups = 0;
        MoodleQuickForm::registerElementType('sortlist',
                "$CFG->dirroot/mod/grouptool/sortlist.php",
                'MoodleQuickForm_sortlist');
        //get groupdata
        $coursegroups = groups_get_all_groups($COURSE->id, null, null, "id");
        if (is_array($coursegroups) && !empty($coursegroups)) {
            $groups = array();
            foreach ($coursegroups as $group) {
                $groups[] = $group->id;
            }
            list($grps_sql, $params) = $DB->get_in_or_equal($groups);
            $groupdata = (array)$DB->get_records_sql("
                  SELECT grp.id AS id, grp.name AS name, agrp.grpsize AS grpsize,
                         agrp.active AS active, agrp.sort_order AS sort_order,
                         GROUP_CONCAT(grpgs.id SEPARATOR ',') AS classes
                  FROM {groups} AS grp LEFT JOIN {grouptool_agrps} as agrp ON agrp.group_id = grp.id
                      LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                      LEFT JOIN {groupings} AS grpgs ON {groupings_groups}.groupingid = grpgs.id
                  WHERE grp.id ".$grps_sql."
                  GROUP BY grp.id
                  ORDER BY sort_order ASC, name ASC", $params);
            // convert to multidimensional array and replace comma separated string through array
            //  for each classes list
            $running_index = 1;
            foreach ($groupdata as $key => $group) {
                //if ($groupdata[$key]->sort_order == NULL) {
                $groupdata[$key]->sort_order = $running_index;
                //}
                $running_index++;
                $groupdata[$key] = (array)$group;
                $groupdata[$key]['classes'] = explode(",", $groupdata[$key]['classes']);
                foreach ($groupdata[$key]['classes'] as $classkey => $class) {
                    $groupdata[$key]['classes'][$classkey] = 'class'.$class;
                }
            }
        } else {
            $nogroups = 1;
        }
        if ($nogroups != 1) {
            $options = array();
            $options['classes'] = groups_get_all_groupings($COURSE->id);
            $options['add_fields'] = array();
            $options['add_fields']['grpsize'] = new stdClass();
            $options['add_fields']['grpsize']->name = 'grpsize';
            if (isset($CFG->grouptool_groupsize)) {
                $options['add_fields']['grpsize']->stdvalue = $CFG->grouptool_grpsize;
            } else {
                $options['add_fields']['grpsize']->stdvalue = '3';
            }
            $options['add_fields']['grpsize']->label = get_string('groupsize', 'grouptool');
            $options['add_fields']['grpsize']->type = 'text';
            $options['add_fields']['grpsize']->attr = array('size' => '3');
            $options['all_string'] = get_string('all').' '.get_string('groups');
            $mform->addElement('sortlist', 'grouplist', $options);
            $mform->setDefault('grouplist', $groupdata);
            //add disabledIfs for all Groupsize-Fields

            foreach ($groupdata as $key => $group) {
                $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_size',
                                   'notchecked');
                $mform->disabledIf('grouplist['.$group['id'].'][grpsize]', 'use_individual',
                                   'notchecked');
            }
        } else {
            $mform->addElement('sortlist', 'grouplist', array());
            $mform->setDefault('grouplist', null);
        }

        /*init enhancements JS*/
        $PAGE->requires->yui_module('moodle-mod_grouptool-enhancements',
                'M.mod_grouptool.init_enhancements',
                null);

        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $parent_errors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['timedue']) && ($data['timedue'] <= $data['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }
        return array_merge($parent_errors, $errors);
    }
}
