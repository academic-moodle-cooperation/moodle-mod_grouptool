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
 * Upgrade code for install
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute grouptool upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_grouptool_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    /*
     * And upgrade begins here. For each one, you'll need one
     * block of code similar to the next one. Please, delete
     * this comment lines once this file start handling proper
     * upgrade code.
     */

    /*
     * if ($oldversion < YYYYMMDD00) { //New version in version.php
     *
     * }
     */
    if ($oldversion < 2012061300) {

        // Define field active to be added to grouptool_agrps.
        $table = new xmldb_table('grouptool_agrps');
        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0',
                                 'max_members');

        // Conditionally launch add field active.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012061300, 'grouptool');
    }

    if ($oldversion < 2012062200) {

        // Define field use_size to be added to grouptool.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('use_size', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                                 null, '0', 'choose_max');

        // Conditionally launch add field use_size.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012062200, 'grouptool');
    }

    if ($oldversion < 2012062500) {

        // Rename field max_members on table grouptool_agrps to size.
        $table = new xmldb_table('grouptool_agrps');
        $field = new xmldb_field('max_members', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null,
                                 null, null, 'sort_order');

        // Launch rename field size.
        $dbman->rename_field($table, $field, 'size');

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012062500, 'grouptool');
    }

    if ($oldversion < 2012071000) {
        $pbar = new progress_bar('checkmarkupgradegrades', 500, true);
        $count = 13;
        $pbar->update(1, $count, "Rename grouptool->max_members to grouptool->grpsize...");
        // Rename field max_members on table grouptool_agrps to size.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('max_members', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null,
                                 null, null, 'allow_unreg');
        // Launch rename field max_members --> grpsize.
        $dbman->rename_field($table, $field, 'grpsize');
        $pbar->update(1, $count, "Rename grouptool->max_members to grouptool_grpsize...finished");
        $pbar->update(2, $count, "Rename grouptool_agrps->size to grouptool_agrps->grpsize...");
        $table = new xmldb_table('grouptool_agrps');
        $field = new xmldb_field('size', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null,
                                 'sort_order');
        // Launch rename field size --> grpsize.
        $dbman->rename_field($table, $field, 'grpsize');
        $pbar->update(2, $count, "Rename grouptool_agrps->size ".
                                 "to grouptool_agrps->grpsize...finished");

        $pbar->update(3, $count, "drop key agrp_id...");
        // Define key agrp_id (foreign) to be dropped form grouptool_registered.
        $table = new xmldb_table('grouptool_registered');
        $key = new xmldb_key('agrp_id', XMLDB_KEY_FOREIGN, array('agrp_id'), 'grouptool_agrps',
                             array('id'));
        // Launch drop key agrp_id.
        $dbman->drop_key($table, $key);

        $pbar->update(4, $count, "drop index agrp_id-user_id...");
        // Define index agrp_id-user_id (unique) to be dropped form grouptool_registered.
        $index = new xmldb_index('agrp_id-user_id', XMLDB_INDEX_UNIQUE,
                                 array('agrp_id', 'user_id'));
        // Conditionally launch drop index agrp_id-user_id.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $pbar->update(5, $count,
                      "rename field grouptool_registered->agroup_id ".
                      "to grouptool_registered->agrp_id...");
        // Rename field agroup_id on table grouptool_registered to agrp_id.
        $field = new xmldb_field('agroup_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'id');
        // Launch rename field agrp_id.
        $dbman->rename_field($table, $field, 'agrp_id');

        $pbar->update(6, $count, "restore altered key agrp_id...");
        // Define key agrp_id (foreign) to be added to grouptool_registered.
        $key = new xmldb_key('agrp_id', XMLDB_KEY_FOREIGN, array('agrp_id'), 'grouptool_agrps',
                             array('id'));
        // Launch add key agrp_id.
        $dbman->add_key($table, $key);

        $pbar->update(7, $count, "restore altered index agrp_id-user_id...");
        // Define index agrp_id-user_id (unique) to be added to grouptool_registered.
        $index = new xmldb_index('agrp_id-user_id', XMLDB_INDEX_UNIQUE,
                                 array('agrp_id', 'user_id'));
        // Conditionally launch add index agrp_id-user_id.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $pbar->update(8, $count, "drop key agrp_id...");
        // Define key agrp_id (foreign) to be dropped form grouptool_registered.
        $table = new xmldb_table('grouptool_queued');
        $key = new xmldb_key('agrp_id', XMLDB_KEY_FOREIGN, array('agrp_id'), 'grouptool_agrps',
                             array('id'));
        // Launch drop key agrp_id.
        $dbman->drop_key($table, $key);

        $pbar->update(9, $count, "drop index agrp_id-user_id...");
        // Define index agrp_id-user_id (unique) to be dropped form grouptool_registered.
        $index = new xmldb_index('agrp_id-user_id', XMLDB_INDEX_UNIQUE,
                                 array('agrp_id', 'user_id'));
        // Conditionally launch drop index agrp_id-user_id.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $pbar->update(10, $count,
                      "rename field grouptool_queued->agroup_id to grouptool_queued->agrp_id...");
        // Rename field agroup_id on table grouptool_registered to agrp_id.
        $field = new xmldb_field('agroup_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'id');
        // Launch rename field agrp_id.
        $dbman->rename_field($table, $field, 'agrp_id');

        $pbar->update(11, $count, "restore altered key agrp_id...");
        // Define key agrp_id (foreign) to be added to grouptool_registered.
        $key = new xmldb_key('agrp_id', XMLDB_KEY_FOREIGN, array('agrp_id'), 'grouptool_agrps',
                             array('id'));
        // Launch add key agrp_id.
        $dbman->add_key($table, $key);

        $pbar->update(12, $count, "restore altered index agrp_id-user_id...");
        // Define index agrp_id-user_id (unique) to be added to grouptool_registered.
        $index = new xmldb_index('agrp_id-user_id', XMLDB_INDEX_UNIQUE,
                                 array('agrp_id', 'user_id'));
        // Conditionally launch add index agrp_id-user_id.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $pbar->update(13, $count, "finished!");

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012071000, 'grouptool');
    }

    if ($oldversion < 2012071001) {

        // Define field use_size to be added to grouptool.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('use_size', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                                 null, '0', 'grpsize');

        // Conditionally launch add field use_size.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012071001, 'grouptool');
    }

    if ($oldversion < 2012072201) {
        // We made just changes in grouptols capabilities.
        upgrade_mod_savepoint(true, 2012072201, 'grouptool');
    }

    if ($oldversion < 2012072202) {

        // Define field active to be added to grouptool_agrps.
        $table = new xmldb_table('grouptool_agrps');
        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL,
                                 null, '0', 'grpsize');

        // Conditionally launch add field active.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012072202, 'grouptool');
    }

    if ($oldversion < 2012072900) {

        // Define field ifmemberadded to be added to grouptool.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('ifmemberadded', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'choose_max');

        // Conditionally launch add field ifmemberadded.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field ifmemberremoved to be added to grouptool.
        $field = new xmldb_field('ifmemberremoved', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'ifmemberadded');

        // Conditionally launch add field ifmemberremoved.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field ifgroupdeleted to be added to grouptool.
        $field = new xmldb_field('ifgroupdeleted', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED,
                                 XMLDB_NOTNULL, null, '0', 'ifmemberremoved');

        // Conditionally launch add field ifgroupdeleted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached!
        upgrade_mod_savepoint(true, 2012072900, 'grouptool');
    }

    if ($oldversion < 2013112300) {

        // Define field alwaysshowdescription to be added to grouptool.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('alwaysshowdescription', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field alwaysshowdescription.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2013112300, 'grouptool');
    }

    if ($oldversion < 2013112700) {
        // Rename fields in grouptool_agrps!

        // Define key grouptool_id (foreign) to be dropped form grouptool_agrps!
        $table = new xmldb_table('grouptool_agrps');
        $key = new xmldb_key('grouptool_id', XMLDB_KEY_FOREIGN, array('grouptool_id'), 'grouptool', array('id'));
        // Launch drop key grouptool_id.
        $dbman->drop_key($table, $key);
        $key = new xmldb_key('group_id', XMLDB_KEY_FOREIGN, array('group_id'), 'groups', array('id'));
        // Launch drop key group_id.
        $dbman->drop_key($table, $key);
        $index = new xmldb_index('grouptool-group', XMLDB_INDEX_UNIQUE, array('grouptool_id', 'group_id'));
        // Conditionally launch drop index grouptool-group.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $field = new xmldb_field('grouptool_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        // Launch rename field grouptool_id.
        $dbman->rename_field($table, $field, 'grouptoolid');
        $field = new xmldb_field('group_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'grouptool_id');
        // Launch rename field group_id.
        $dbman->rename_field($table, $field, 'groupid');

        // Restore keys and index!
        $key = new xmldb_key('grouptoolid', XMLDB_KEY_FOREIGN, array('grouptoolid'), 'grouptool', array('id'));
        // Launch add key grouptoolid.
        $dbman->add_key($table, $key);
        $key = new xmldb_key('groupid', XMLDB_KEY_FOREIGN, array('groupid'), 'groups', array('id'));
        // Launch add key groupid.
        $dbman->add_key($table, $key);
        $index = new xmldb_index('grouptool-group', XMLDB_INDEX_UNIQUE, array('grouptoolid', 'groupid'));
        // Conditionally launch add index grouptool-group.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2013112700, 'grouptool');
    }

    // The following code has code of 2 upgrade steps compressed to a foreach!
    $tables = array(2013112701 => new xmldb_table('grouptool_registered'),
                    2013112702 => new xmldb_table('grouptool_queued'));
    foreach ($tables as $vers => $table) {
        if ($oldversion < vers) {
            // Define key agrp_id (foreign) to be dropped form grouptool_queued.
            $key = new xmldb_key('agrp_id', XMLDB_KEY_FOREIGN, array('agrp_id'), 'grouptool_agrps', array('id'));
            // Launch drop key agrp_id.
            $dbman->drop_key($table, $key);
            // Define key user_id (foreign) to be dropped form grouptool_registered.
            $key = new xmldb_key('user_id', XMLDB_KEY_FOREIGN, array('user_id'), 'user', array('id'));
            // Launch drop key user_id.
            $dbman->drop_key($table, $key);
             // Define index agrp_id-user_id (unique) to be dropped form grouptool_registered.
            $index = new xmldb_index('agrp_id-user_id', XMLDB_INDEX_UNIQUE, array('agrp_id', 'user_id'));
            // Conditionally launch drop index agrp_id-user_id.
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            $field = new xmldb_field('agrp_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            // Launch rename field agrp_id.
            $dbman->rename_field($table, $field, 'agrpid');
            $field = new xmldb_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'grouptool_id');
            // Launch rename field user_id.
            $dbman->rename_field($table, $field, 'userid');

            // Restore keys and index!
            $key = new xmldb_key('agrpid', XMLDB_KEY_FOREIGN, array('agrpid'), 'grouptool_agrps', array('id'));
            // Launch add key agrpid.
            $dbman->add_key($table, $key);
            $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
            // Launch add key userid.
            $dbman->add_key($table, $key);
            $index = new xmldb_index('agrpid-userid', XMLDB_INDEX_UNIQUE, array('agrpid', 'userid'));
            // Conditionally launch add index agrpiduserid.
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }

            upgrade_mod_savepoint(true, $vers, 'grouptool');
        }
    }

    if ($oldversion < 2014031900) {

        // Define field alwaysshowdescription to be added to grouptool.
        $table = new xmldb_table('grouptool');
        $field = new xmldb_field('alwaysshowdescription', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field alwaysshowdescription.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2014031900, 'grouptool');
    }

    if ($oldversion < 2014090800) {
        // We have to have it set for the following upgrade steps!
        if (!isset($CFG->grouptool_importfields)) {
            set_config('grouptool_importfields', 'username,idnumber');
        }
        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2014090800, 'grouptool');
    }

    if ($oldversion < 2014110703) {
        // Move module settings from config table to config_plugins!
        $settingsnames = array('requiremodintro', 'name_scheme', 'allow_reg',
                               'show_members', 'immediate_reg', 'allow_unreg',
                               'grpsize', 'use_size', 'use_individual', 'use_queue',
                               'max_queues', 'allow_multiple', 'choose_min', 'choose_max',
                               'ifmemberadded', 'ifmemberremoved', 'ifgroupdeleted',
                               'force_importreg', 'importfields');
        // Check if everything is all right!
        foreach ($settingsnames as $key => $cur) {
            $name = 'grouptool_'.$cur;
            if (!isset($CFG->$name)) {
                unset($settingsnames[$key]);
                echo "Can't find setting for '".$name."'. It will be ignored. Please check the setting after the upgrade!".
                     html_writer::empty_tag('br')."<br />";
                continue;
            }
            if ($DB->count_records('config', array('name' => $name)) != 1) {
                unset($settingsnames[$key]);
                echo "Can't select setting for '".$name.
                     "' uniquely in the DB. It will be ignored. Please check the setting after the upgrade!".
                     html_writer::empty_tag('br')."<br />";
                continue;
                throw new coding_exception("'$name' could not be uniquely selected in DB!");
            }
        }
        foreach ($settingsnames as $cur) {
            $name = 'grouptool_'.$cur;
            set_config($cur, $CFG->$name, 'mod_grouptool');
            if (get_config('mod_grouptool', $cur) !== false) {
                $DB->delete_records('config', array('name' => $name));
            } else {
                throw new coding_exception("'$name' could not be properly migrated, because of some coding error.");
            }
        }

        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2014110703, 'grouptool');
    }

    if ($oldversion < 2015042200) {
        // Fix a misspelled - and already corrected - string identifier blocking language customisations.
        $DB->set_field_select('tool_customlang', 'stringid', 'create_assign_groupings', $DB->sql_like('stringid', ':stringid'),
                              array('stringid' => 'create_assign_Groupings'));

        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2015042200, 'grouptool');
    }

    // Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this!

    if ($oldversion < 2017012500) {

        $table = new xmldb_table('grouptool');

        // Rename field queues_max on table grouptool to NEWNAMEGOESHERE.
        $field = new xmldb_field('queues_max', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'use_queue');
        // Launch rename field queues_max.
        $dbman->rename_field($table, $field, 'users_queues_limit');

        // Changing nullability of field users_queues_limit on table grouptool to not null.
        $field = new xmldb_field('users_queues_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'use_queue');
        // Launch change of default for field users_queues_limit.
        $dbman->change_field_default($table, $field);
        // Launch change of nullability for field users_queues_limit.
        $dbman->change_field_notnull($table, $field);

        // Define field groups_queues_limit to be added to grouptool.
        $field = new xmldb_field('groups_queues_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
                                 'users_queues_limit');
        // Conditionally launch add field groups_queues_limit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Grouptool savepoint reached.
        upgrade_mod_savepoint(true, 2017012500, 'grouptool');
    }

    // Final return of upgrade result (true, all went good) to Moodle.
    return true;
}
