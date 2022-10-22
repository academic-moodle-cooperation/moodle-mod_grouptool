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
 * Admin-settings used by mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/grouptool/lib.php');
    require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

    // Administration header!
    $settings->add(new admin_setting_heading('mod_grouptool/view_administration', get_string('cfg_admin_head', 'grouptool'),
            get_string('cfg_admin_head_info', 'grouptool')));

    // Standard name scheme?
    $settings->add(new admin_setting_configtext('mod_grouptool/name_scheme', get_string('cfg_name_scheme', 'grouptool'),
            get_string('cfg_name_scheme_desc', 'grouptool'), get_string('group').' #'));

    // Instance settings header!
    $settings->add(new admin_setting_heading('mod_grouptool/instance', get_string('cfg_instance_head', 'grouptool'),
            get_string('cfg_instance_head_info', 'grouptool')));

    // Enable selfregistration?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_reg', get_string('cfg_allow_reg', 'grouptool'),
            get_string('cfg_allow_reg_desc', 'grouptool'), 1));

    // Show groupmembers?
    $options = [
        GROUPTOOL_SHOW_GROUPMEMBERS               => get_string('yes'),
        GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE     => get_string('showafterdue', 'grouptool'),
        GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE => get_string('showownafterdue', 'grouptool'),
        GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG => get_string('showownafterreg', 'grouptool'),
        GROUPTOOL_HIDE_GROUPMEMBERS               => get_string('no')
    ];
    $settings->add(new admin_setting_configselect('mod_grouptool/show_members', get_string('cfg_show_members', 'grouptool'),
            get_string('cfg_show_members_desc', 'grouptool'), GROUPTOOL_HIDE_GROUPMEMBERS, $options));

    // Activate immediate registrations?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/immediate_reg', get_string('cfg_immediate_reg', 'grouptool'),
            get_string('cfg_immediate_reg_desc', 'grouptool'), 0));

    // Allow unregistration?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_unreg', get_string('cfg_allow_unreg', 'grouptool'),
            get_string('cfg_allow_unreg_desc', 'grouptool'), 0));

    // Standard groupsize?
    $groupsize = new admin_setting_configtext('mod_grouptool/grpsize', get_string('cfg_grpsize', 'grouptool'),
            get_string('cfg_grpsize_desc', 'grouptool'), '3', PARAM_INT);
    $settings->add($groupsize);

    // Use groupsize?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/use_size', get_string('cfg_use_size', 'grouptool'),
            get_string('cfg_use_size_desc', 'grouptool'), 0));

    // Use queues?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/use_queue', get_string('cfg_use_queue', 'grouptool'),
            get_string('cfg_use_queue_desc', 'grouptool'), 0));

    // Max simultaneous queue-places?
    $maxqueues = new admin_setting_configtext('mod_grouptool/users_queues_limit', get_string('users_queues_limit', 'grouptool'),
            get_string('cfg_users_queues_limit_desc', 'grouptool'), '1', PARAM_INT);
    $settings->add($maxqueues);

    $maxqueues = new admin_setting_configtext('mod_grouptool/groups_queues_limit',
            get_string('groups_queues_limit', 'grouptool'), get_string('cfg_groups_queues_limit_desc', 'grouptool'), '0',
            PARAM_INT);
    $settings->add($maxqueues);

    // Multiple registrations?
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/allow_multiple', get_string('cfg_allow_multiple', 'grouptool'),
            get_string('cfg_allow_multiple_desc', 'grouptool'), 0));

    // Min groups to choose?
    $mingroups = new admin_setting_configtext('mod_grouptool/choose_min', get_string('cfg_choose_min', 'grouptool'),
            get_string('cfg_choose_min_desc', 'grouptool'), '1', PARAM_INT);
    $settings->add($mingroups);

    // Max groups to choose?
    $maxgroups = new admin_setting_configtext('mod_grouptool/choose_max', get_string('cfg_choose_max', 'grouptool'),
            get_string('cfg_choose_max_desc', 'grouptool'), '1', PARAM_INT);
    $settings->add($maxgroups);

    $settings->add(new admin_setting_heading('mod_grouptool/moodlesync', get_string('cfg_moodlesync_head', 'grouptool'),
            get_string('cfg_moodlesync_head_info', 'grouptool')));

    $options = [
        GROUPTOOL_IGNORE => get_string('ignorechanges', 'grouptool'),
        GROUPTOOL_FOLLOW => get_string('followchanges', 'grouptool')
    ];

    $settings->add(new admin_setting_configselect('mod_grouptool/ifmemberadded', get_string('cfg_ifmemberadded', 'grouptool'),
            get_string('cfg_ifmemberadded_desc', 'grouptool'), GROUPTOOL_IGNORE, $options));

    $settings->add(new admin_setting_configselect('mod_grouptool/ifmemberremoved', get_string('cfg_ifmemberremoved', 'grouptool'),
            get_string('cfg_ifmemberremoved_desc', 'grouptool'), GROUPTOOL_IGNORE, $options));

    $options = [
        GROUPTOOL_RECREATE_GROUP => get_string('recreate_group', 'grouptool'),
        GROUPTOOL_DELETE_REF     => get_string('delete_reference', 'grouptool')
    ];

    $settings->add(new admin_setting_configselect('mod_grouptool/ifgroupdeleted', get_string('cfg_ifgroupdeleted', 'grouptool'),
            get_string('cfg_ifgroupdeleted_desc', 'grouptool'), GROUPTOOL_RECREATE_GROUP, $options));

    $settings->add(new admin_setting_heading('mod_grouptool/addinstanceset', get_string('cfg_addinstanceset_head', 'grouptool'),
            get_string('cfg_addinstanceset_head_info', 'grouptool')));

    $settings->add(new admin_setting_configtext('mod_grouptool/importfields', get_string('cfg_importfields', 'grouptool'),
        get_string('cfg_importfields_desc', 'grouptool'), 'username,idnumber', "/^((?![^a-zA-Z,]).)*$/"));

    $settings->add(new admin_setting_configcheckbox('mod_grouptool/force_importreg', get_string('cfg_force_importreg', 'grouptool'),
            get_string('cfg_force_importreg_desc', 'grouptool'), 0));

    $settings->add(new admin_setting_configcheckbox('mod_grouptool/force_dereg', get_string('cfg_force_dereg', 'grouptool'),
        get_string('cfg_force_dereg_desc', 'grouptool'), 0));
    $settings->add(new admin_setting_configcheckbox('mod_grouptool/show_add_info', get_string('cfg_show_add_info', 'grouptool'),
        get_string('cfg_show_add_info_desc', 'grouptool'), 0));
}
