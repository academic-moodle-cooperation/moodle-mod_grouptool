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
 * mod_grouptool's restore tasks
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Because it exists (must)!
require_once($CFG->dirroot . '/mod/grouptool/backup/moodle2/restore_grouptool_stepslib.php');

/**
 * grouptool restore task that provides everything to perform one complete restore of the activity
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_grouptool_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity!
    }

    /**
     * Define (add) particular steps this activity can have
     *
     * @throws base_task_exception
     * @throws restore_step_exception
     */
    protected function define_my_steps() {
        // Grouptool only has one structure step!
        $this->add_step(new restore_grouptool_activity_structure_step('grouptool_structure',
                                                                      'grouptool.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('grouptool', ['intro'], 'grouptool');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('GROUPTOOLVIEWBYID', '/mod/grouptool/view.php?id=$1',
                                           'course_module');
        $rules[] = new restore_decode_rule('GROUPTOOLINDEX', '/mod/grouptool/index.php?id=$1',
                                           'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@see restore_logs_processor} when restoring
     * grouptool logs. It must return one array
     * of {@see restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = [];
        /*
         * @todo change to using standard-action-pattern add/delete/update/view
         * + additional info  ../../db/log.php
         */
        $rules[] = new restore_log_rule('grouptool', 'add', 'view.php?id={course_module}',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'update',
                                        'view.php?id={course_module}', '{grouptool}');

        $rules[] = new restore_log_rule('grouptool',
                                        'view administration',
                                        'view.php?id={course_module}&tab=administration',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool',
                                        'view grading',
                                        'view.php?id={course_module}&tab=grading',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'view registration',
                                        'view.php?id={course_module}&tab=registration',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'view import',
                                        'view.php?id={course_module}&tab=import', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'view overview',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'view userlist',
                                        'view.php?id={course_module}&tab=userlist', '{grouptool}');

        $rules[] = new restore_log_rule('grouptool', 'export',
                                        'download.php?id={course_module}&groupingid={grouping}'.
                                        '&groupid={group}&format=[format]', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'export', 'download.php?id={course_module}'.
                                        '&groupingid={grouping}&groupid={group}&format=[format]',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'export',
                                        'download.php?id={course_module}&groupingid={grouping}'.
                                        '&groupid={group}&format=[format]', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'export',
                                        'download.php?id={course_module}&groupingid={grouping}'.
                                        '&groupid={group}&format=[format]', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'export',
                                        'download.php?id={course_module}&groupingid={grouping}'.
                                        '&groupid={group}&format=[format]', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'export',
                                        'download.php?id={course_module}&groupingid={grouping}'.
                                        '&groupid={group}&format=[format]', '{grouptool}');

        $rules[] = new restore_log_rule('grouptool', 'register',
                                        'view.php?id={course_module}&tab=overview&'.
                                        'agrpid={grouptool_agrp}', '{user}');
        $rules[] = new restore_log_rule('grouptool', 'unregister',
                                        'view.php?id={course_module}&tab=overview&'.
                                        'agrpid={grouptool_agrp}', '{user}');
        $rules[] = new restore_log_rule('grouptool', 'resolve queue',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'import',
                                        'view.php?id={course_module}&tab=overview&groupid={group}',
                                        '{group}');
        $rules[] = new restore_log_rule('grouptool', 'push registrations',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');

        $rules[] = new restore_log_rule('grouptool', 'create groups',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'create groups',
                                        'view.php?id={course_module}&tab=overview&'.
                                        'groupingid={grouping}', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'create groupings',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'update agrps',
                                        'view.php?id={course_module}&tab=overview&groupid={group}',
                                        '{grouptool}');
        $rules[] = new restore_log_rule('grouptool', 'update agrps',
                                        'view.php?id={course_module}&tab=overview', '{grouptool}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@see restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@see restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('grouptool', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
