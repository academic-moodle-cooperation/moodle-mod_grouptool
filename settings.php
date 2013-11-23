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

/*
 * Admin-Settings for grouptool
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/grouptool/lib.php');
    require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

    $settings->add(new admin_setting_configcheckbox('grouptool_requiremodintro',
            get_string('requiremodintro', 'admin'),
            get_string('configrequiremodintro', 'admin'), 1));
    
    $settings->add(new admin_setting_heading('grouptool_view_administration',
            get_string('cfg_admin_head', 'grouptool'),
            get_string('cfg_admin_head_info', 'grouptool')));
    // Standard name scheme?
    $settings->add(new admin_setting_configtext('grouptool_name_scheme',
            get_string('cfg_name_scheme', 'grouptool'),
            get_string('cfg_name_scheme_desc', 'grouptool'),
            get_string('group').' #'));

    /*-----------------------------------------------------------------*/
    $settings->add(new admin_setting_heading('grouptool_instance',
            get_string('cfg_instance_head', 'grouptool'),
            get_string('cfg_instance_head_info', 'grouptool')));

    // Enable selfregistration?
    $settings->add(new admin_setting_configcheckbox('grouptool_allow_reg',
            get_string('cfg_allow_reg', 'grouptool'),
            get_string('cfg_allow_reg_desc', 'grouptool'),
            1, $yes='1', $no='0'));

    // Show groupmembers?
    $settings->add(new admin_setting_configcheckbox('grouptool_show_members',
            get_string('cfg_show_members', 'grouptool'),
            get_string('cfg_show_members_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Activate immediate registrations?
    $settings->add(new admin_setting_configcheckbox('grouptool_immediate_reg',
            get_string('cfg_immediate_reg', 'grouptool'),
            get_string('cfg_immediate_reg_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Allow unregistration?
    $settings->add(new admin_setting_configcheckbox('grouptool_allow_unreg',
            get_string('cfg_allow_unreg', 'grouptool'),
            get_string('cfg_allow_unreg_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Standard groupsize?
    $groupsize = new admin_setting_configtext('grouptool_grpsize',
            get_string('cfg_grpsize', 'grouptool'),
            get_string('cfg_grpsize_desc', 'grouptool'),
            '3', PARAM_INT);
    $settings->add($groupsize);

    // Use groupsize?
    $settings->add(new admin_setting_configcheckbox('grouptool_use_size',
            get_string('cfg_use_size', 'grouptool'),
            get_string('cfg_use_size_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Use individual size per group?
    $settings->add(new admin_setting_configcheckbox('grouptool_use_individual',
            get_string('cfg_use_individual', 'grouptool'),
            get_string('cfg_use_individual_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Use queues?
    $settings->add(new admin_setting_configcheckbox('grouptool_use_queue',
            get_string('cfg_use_queue', 'grouptool'),
            get_string('cfg_use_queue_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Max simultaneous queue-places?
    $maxqueues = new admin_setting_configtext('grouptool_max_queues',
            get_string('cfg_max_queues', 'grouptool'),
            get_string('cfg_max_queues_desc', 'grouptool'),
            '1', PARAM_INT);
    $settings->add($maxqueues);

    // Multiple registrations?
    $settings->add(new admin_setting_configcheckbox('grouptool_allow_multiple',
            get_string('cfg_allow_multiple', 'grouptool'),
            get_string('cfg_allow_multiple_desc', 'grouptool'),
            0, $yes='1', $no='0'));

    // Min groups to choose?
    $mingroups = new admin_setting_configtext('grouptool_choose_min',
            get_string('cfg_choose_min', 'grouptool'),
            get_string('cfg_choose_min_desc', 'grouptool'),
            '1', PARAM_INT);
    $settings->add($mingroups);
    
    // Max groups to choose?
    $maxgroups = new admin_setting_configtext('grouptool_choose_max',
            get_string('cfg_choose_max', 'grouptool'),
            get_string('cfg_choose_max_desc', 'grouptool'),
            '1', PARAM_INT);
    $settings->add($maxgroups);

    $settings->add(new admin_setting_heading('grouptool_moodlesync',
            get_string('cfg_moodlesync_head', 'grouptool'),
            get_string('cfg_moodlesync_head_info', 'grouptool')));

    $options = array( GROUPTOOL_IGNORE => get_string('ignorechanges', 'grouptool'),
                      GROUPTOOL_FOLLOW => get_string('followchanges', 'grouptool'));

    $settings->add(new admin_setting_configselect('grouptool_ifmemberadded',
            get_string('cfg_ifmemberadded', 'grouptool'),
            get_string('cfg_ifmemberadded_desc', 'grouptool'),
            GROUPTOOL_IGNORE,
            $options));

    $settings->add(new admin_setting_configselect('grouptool_ifmemberremoved',
            get_string('cfg_ifmemberremoved', 'grouptool'),
            get_string('cfg_ifmemberremoved_desc', 'grouptool'),
            GROUPTOOL_IGNORE,
            $options));

    $options = array( GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
                      GROUPTOOL_DELETE_REF     => get_string('delete_reference', 'grouptool'));

    $settings->add(new admin_setting_configselect('grouptool_ifgroupdeleted',
            get_string('cfg_ifgroupdeleted', 'grouptool'),
            get_string('cfg_ifgroupdeleted_desc', 'grouptool'),
            GROUPTOOL_RECREATE_GROUP,
            $options));

    $settings->add(new admin_setting_heading('grouptool_addinstanceset',
            get_string('cfg_addinstanceset_head', 'grouptool'),
            get_string('cfg_addinstanceset_head_info', 'grouptool')));

    $settings->add(new admin_setting_configcheckbox('grouptool_force_importreg',
            get_string('cfg_force_importreg', 'grouptool'),
            get_string('cfg_force_importreg_desc', 'grouptool'),
            0, $yes='1', $no='0'));
}
