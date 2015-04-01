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
 * settings.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/grouptool/lib.php');
    require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
    // grouptool_requiremodintro
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/requiremodintro',
                                                    get_string('requiremodintro', 'admin'),
                                                    get_string('configrequiremodintro', 'admin'), 1));
    // grouptool_view_administration
    $settings->add(new admin_setting_heading('mod_grouptool/view_administration',
                                             get_string('cfg_admin_head', 'grouptool'),
                                             get_string('cfg_admin_head_info', 'grouptool')));

    // grouptool_name_scheme - Standard name scheme?
    $settings->add(new admin_setting_configtext('mod_grouptool/name_scheme',
                                                get_string('cfg_name_scheme', 'grouptool'),
                                                get_string('cfg_name_scheme_desc', 'grouptool'),
                                                get_string('group').' #'));

    /*-----------------------------------------------------------------*/
    $settings->add(new admin_setting_heading('mod_grouptool/instance',
                                             get_string('cfg_instance_head', 'grouptool'),
                                             get_string('cfg_instance_head_info', 'grouptool')));

    // grouptool_allow_reg - Enable selfregistration?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_reg',
                                                    get_string('cfg_allow_reg', 'grouptool'),
                                                    get_string('cfg_allow_reg_desc', 'grouptool'),
                                                    1, $yes = '1', $no = '0'));

    // grouptool_show_members - Show groupmembers?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/show_members',
                                                    get_string('cfg_show_members', 'grouptool'),
                                                    get_string('cfg_show_members_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_immediate_reg - Activate immediate registrations?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/immediate_reg',
                                                    get_string('cfg_immediate_reg', 'grouptool'),
                                                    get_string('cfg_immediate_reg_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_allow_unreg - Allow unregistration?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_unreg',
                                                    get_string('cfg_allow_unreg', 'grouptool'),
                                                    get_string('cfg_allow_unreg_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_grpsize - Standard groupsize?
    $groupsize = new admin_setting_configtext('mod_grouptool/grpsize',
                                              get_string('cfg_grpsize', 'grouptool'),
                                              get_string('cfg_grpsize_desc', 'grouptool'),
                                              '3', PARAM_INT);
    $settings->add($groupsize);

    // grouptool_use_size - Use groupsize?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/use_size',
                                                    get_string('cfg_use_size', 'grouptool'),
                                                    get_string('cfg_use_size_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_use_individual - Use individual size per group?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/use_individual',
                                                    get_string('cfg_use_individual', 'grouptool'),
                                                    get_string('cfg_use_individual_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_use_queue - Use queues?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/use_queue',
                                                    get_string('cfg_use_queue', 'grouptool'),
                                                    get_string('cfg_use_queue_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_max_queues - Max simultaneous queue-places?
    $maxqueues = new admin_setting_configtext('mod_grouptool/max_queues',
                                              get_string('cfg_max_queues', 'grouptool'),
                                              get_string('cfg_max_queues_desc', 'grouptool'),
                                              '1', PARAM_INT);
    $settings->add($maxqueues);

    // grouptool_allow_multiple - Multiple registrations?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_multiple',
                                                    get_string('cfg_allow_multiple', 'grouptool'),
                                                    get_string('cfg_allow_multiple_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_choose_min - Min groups to choose?
    $mingroups = new admin_setting_configtext('mod_grouptool/choose_min',
                                              get_string('cfg_choose_min', 'grouptool'),
                                              get_string('cfg_choose_min_desc', 'grouptool'),
                                              '1', PARAM_INT);
    $settings->add($mingroups);

    // grouptool_choose_max - Max groups to choose?
    $maxgroups = new admin_setting_configtext('mod_grouptool/choose_max',
                                              get_string('cfg_choose_max', 'grouptool'),
                                              get_string('cfg_choose_max_desc', 'grouptool'),
                                              '1', PARAM_INT);
    $settings->add($maxgroups);

    $settings->add(new admin_setting_heading('mod_grouptool/moodlesync',
                                             get_string('cfg_moodlesync_head', 'grouptool'),
                                             get_string('cfg_moodlesync_head_info', 'grouptool')));

    $options = array( GROUPTOOL_IGNORE => get_string('ignorechanges', 'grouptool'),
                      GROUPTOOL_FOLLOW => get_string('followchanges', 'grouptool'));

    // grouptool_ifmemberadded
    $settings->add(new admin_setting_configselect('mod_grouptool/ifmemberadded',
                                                  get_string('cfg_ifmemberadded', 'grouptool'),
                                                  get_string('cfg_ifmemberadded_desc', 'grouptool'),
                                                  GROUPTOOL_IGNORE,
                                                  $options));

    // grouptool_ifmemberremoved
    $settings->add(new admin_setting_configselect('mod_grouptool/ifmemberremoved',
                                                  get_string('cfg_ifmemberremoved', 'grouptool'),
                                                  get_string('cfg_ifmemberremoved_desc', 'grouptool'),
                                                  GROUPTOOL_IGNORE,
                                                  $options));

    $options = array( GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
                      GROUPTOOL_DELETE_REF     => get_string('delete_reference', 'grouptool'));

    // grouptool_ifgroupdeleted
    $settings->add(new admin_setting_configselect('mod_grouptool/ifgroupdeleted',
                                                  get_string('cfg_ifgroupdeleted', 'grouptool'),
                                                  get_string('cfg_ifgroupdeleted_desc', 'grouptool'),
                                                  GROUPTOOL_RECREATE_GROUP,
                                                  $options));

    $settings->add(new admin_setting_heading('mod_grouptool/addinstanceset',
                                             get_string('cfg_addinstanceset_head', 'grouptool'),
                                             get_string('cfg_addinstanceset_head_info', 'grouptool')));

    // grouptool_force_importreg
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/force_importreg',
                                                    get_string('cfg_force_importreg', 'grouptool'),
                                                    get_string('cfg_force_importreg_desc', 'grouptool'),
                                                    0, $yes = '1', $no = '0'));

    // grouptool_importfields
    $settings->add(new admin_setting_configtext('mod_grouptool/importfields',
                                                get_string('cfg_importfields', 'grouptool'),
                                                get_string('cfg_importfields_desc', 'grouptool'),
                                                'username,idnumber', "/^((?![^a-zA-Z,]).)*$/"));
}
