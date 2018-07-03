<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
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
 * Privacy class for requesting user data.
 *
 * @package    mod_grouptool
 * @author     Philipp Hager
 * @copyright  2018 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouptool\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\metadata\provider as metadataprovider;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\plugin\provider as pluginprovider;
use \core_privacy\local\request\user_preference_provider as preference_provider;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\helper;

require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

/**
 * Privacy class for requesting user data.
 *
 * @package    mod_grouptool
 * @author     Philipp Hager
 * @copyright  2018 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider, pluginprovider, preference_provider {
    /**
     * Provides meta data that is stored about a user with mod_publication
     *
     * @param  collection $collection A collection of meta data items to be added to.
     * @return  collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $queued = [
                'agrpid' => 'privacy:metadata:agrpid',
                'userid' => 'privacy:metadata:userid',
                'timestamp' => 'privacy:metadata:timestamp'
        ];

        $registered = [
                'agrpid' => 'privacy:metadata:agrpid',
                'userid' => 'privacy:metadata:userid',
                'timestamp' => 'privacy:metadata:timestamp',
                'modified_by' => 'privacy:metadata:modified_by'
        ];

        $collection->add_database_table('grouptool_queued', $queued, 'privacy:metadata:queued');
        $collection->add_database_table('grouptool_registered', $registered, 'privacy:metadata:registered');

        $collection->add_user_preference('mod_grouptool_group_filter', 'privacy:metadata:mod_grouptool_group_filter');
        $collection->add_user_preference('mod_grouptool_mygroups_only', 'privacy:metadata:mod_grouptool_mygroups_only');

        // Link to subplugins.
        $collection->add_subsystem_link('core_enrol', [], 'privacy:metadata:enrolexplanation');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:gradesexplanation');
        $collection->add_subsystem_link('core_group', [], 'privacy:metadata:groupexplanation');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messageexplanation');

        return $collection;
    }

    /**
     * Returns all of the contexts that has information relating to the userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist an object with the contexts related to a userid.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $params = [
                'modulename' => 'grouptool',
                'contextlevel' => CONTEXT_MODULE,
                'queueuserid' => $userid,
                'reguserid' => $userid,
                'regmodifierid' => $userid
        ];

        $sql = "SELECT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {grouptool} g ON cm.instance = g.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
             LEFT JOIN {grouptool_agrps} agrp ON g.id = agrp.grouptoolid
             LEFT JOIN {grouptool_registered} r ON r.agrpid = agrp.id
             LEFT JOIN {grouptool_queued} q ON q.agrpid = agrp.id
                 WHERE r.userid = :reguserid OR q.userid = :queueuserid OR r.modified_by = :regmodifierid";
        $contextlist = new contextlist();

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Write out the user data filtered by contexts.
     *
     *
     * @param approved_contextlist $contextlist contexts that we are writing data out from.
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();

        if (empty($contexts)) {
            return;
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    c.id AS contextid,
                    g.*,
                    cm.id AS cmid
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {grouptool} g ON g.id = cm.instance
                 WHERE c.id {$contextsql}";

        // Keep a mapping of grouptoolid to contextid.
        $mappings = [];

        $grouptools = $DB->get_records_sql($sql, $contextparams);

        $user = $contextlist->get_user();

        foreach ($grouptools as $grouptool) {
            $context = \context_module::instance($grouptool->cmid);
            $mappings[$grouptool->id] = $grouptool->contextid;

            // Check that the context is a module context.
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $grouptooldata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            $cm = get_coursemodule_from_instance('grouptool', $grouptool->id);

            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $grouptool = new \mod_grouptool($cm->id, $grouptool, $cm, $course);

            writer::with_context($context)->export_data([], $grouptooldata);

            /* We don't differentiate between roles, if we have data about the user, we give it freely ;) - no sensible
             * information here! */

            static::export_user_preferences($user->id);
            static::export_regs($context, $grouptool, $user);
        }
    }

    /**
     * Stores the user preferences related to mod_publication.
     *
     * @param  int $userid The user ID that we want the preferences for.
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function export_user_preferences(int $userid) {
        $context = \context_system::instance();

        $preferences = [
            'mod_grouptool_group_filter',
            'mod_grouptool_mygroups_only'
        ];

        foreach ($preferences as $cur) {
            $value = get_user_preferences($cur, null, $userid);
            if ($value !== null) {
                writer::with_context($context)->export_user_preference('mod_grouptool', $cur, $value,
                        get_string('privacy:metadata:' . $cur, 'mod_grouptool'));
            }
        }
    }

    /**
     * Export overrides for this assignment.
     * TODO
     * @param  \context $context Context
     * @param  \mod_grouptool $grouptool The publication object.
     * @param  \stdClass $user The user object.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_regs(\context $context, \mod_grouptool $grouptool, \stdClass $user) {
        global $DB;

        // Get all active groups including inactive indexed by agrpid!
        $agrps = $DB->get_records_sql("SELECT agrp.*, g.name
                                         FROM {grouptool_agrps} agrp
                                         JOIN {groups} g ON g.id = agrp.groupid
                                         WHERE agrp.grouptoolid = :grouptoolid", ['grouptoolid' => $grouptool->get_settings()->id]);

        list($agrpssql, $agrpsparams) = $DB->get_in_or_equal(array_keys($agrps), SQL_PARAMS_NAMED);
        $sql = "userid = :userid AND agrpid ".$agrpssql;
        $queues = $DB->get_records_select('grouptool_queued', $sql, ['userid' => $user->id] + $agrpsparams);
        $sql = "(userid = :userid OR modified_by = :modifierid) AND agrpid ".$agrpssql;
        $regs = $DB->get_records_select('grouptool_registered', $sql,
                ['userid' => $user->id, 'modifierid' => $user->id] + $agrpsparams);

        $export = new \stdClass();
        $strmarked = get_string('grp_marked', 'grouptool');
        $strregistered = get_string('registered', 'grouptool');
        $strqueued = get_string('queued', 'grouptool');
        if (!empty($regs)) {
            foreach ($regs as $cur) {
                $tmp = [
                        'group' => $agrps[$cur->agrpid]->name,
                        'status' => ($cur->modified_by === -1) ? $strmarked : $strregistered,
                        'timestamp' => transform::datetime($cur->timestamp)
                ];
                if ($cur->userid != $user->id) {
                    if (!isset($export->modified)) {
                        $export->modified = [];
                    }
                    $export->modified[] = $tmp;
                } else {
                    if ($cur->modified_by !== -1) {
                        if (!isset($export->registrations)) {
                            $export->registrations = [];
                        }
                        $export->registrations[] = $tmp;
                    } else {
                        if (!isset($export->marks)) {
                            $export->marks = [];
                        }
                        $export->marks[] = $tmp;
                    }
                }
            }
        }
        if (!empty($queues)) {
            $export->queues = [];
            foreach ($queues as $cur) {
                $export->queues[] = [
                        'group' => $agrps[$cur->agrpid]->name,
                        'status' => $strqueued,
                        'timestamp' => transform::datetime($cur->timemodified)
                ];
            }
        }
        writer::with_context($context)->export_data([], $export);
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param \context $context The module context.
     * @throws \dml_exception
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_MODULE) {
            // Apparently we can't trust anything that comes via the context.
            // Go go mega query to find out it we have an assign context that matches an existing assignment.
            $sql = "SELECT g.id
                    FROM {grouptool} g
                    JOIN {course_modules} cm ON g.id = cm.instance AND g.course = cm.course
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextmodule
                    WHERE ctx.id = :contextid";
            $params = ['modulename' => 'grouptool', 'contextmodule' => CONTEXT_MODULE, 'contextid' => $context->id];
            $id = $DB->get_field_sql($sql, $params);
            // If we have a count over zero then we can proceed.
            if ($id > 0) {
                $agrps = $DB->get_fieldset_select('grouptool_agrps', 'id', 'grouptoolid = :grouptoolid', ['grouptoolid' => $id]);

                // Get all grouptool regs and queues to delete them!
                $DB->delete_records_list('grouptool_registered', 'agrpid', $agrps);
                $DB->delete_records_list('grouptool_queued', 'agrpid', $agrps);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        $contextids = $contextlist->get_contextids();

        if (empty($contextids) || $contextids === []) {
            return;
        }

        list($ctxsql, $ctxparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        // Apparently we can't trust anything that comes via the context.
        // Go go mega query to find out it we have an grouptool context that matches an existing grouptool.
        $sql = "SELECT ctx.id AS ctxid, g.*
                    FROM {grouptool} g
                    JOIN {course_modules} cm ON g.id = cm.instance AND g.course = cm.course
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextmodule
                    WHERE ctx.id ".$ctxsql;
        $params = ['modulename' => 'grouptool', 'contextmodule' => CONTEXT_MODULE];

        if (!$records = $DB->get_records_sql($sql, $params + $ctxparams)) {
            return;
        }
        $grouptoolids = [];
        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $grouptoolids[] = $records[$context->id]->id;
        }

        if (empty($grouptoolids)) {
            return;
        }

        list($select, $params) = $DB->get_in_or_equal($grouptoolids);
        $agrpids = $DB->get_fieldset_select('grouptool_agrps', 'id', 'grouptoolid '.$select, $params);

        if (empty($agrpids)) {
            return;
        }

        list($agrpssql, $agrpparams) = $DB->get_in_or_equal($agrpids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('grouptool_registered',
                "(userid = :userid OR modified_by = :modifierid) AND agrpid ".$agrpssql,
                $agrpparams + ['userid' => $user->id, 'modifierid' => $user->id]);
        $DB->delete_records_select('grouptool_queued', "userid = :userid AND agrpid ".$agrpssql,
                $agrpparams + ['userid' => $user->id]);
    }
}
