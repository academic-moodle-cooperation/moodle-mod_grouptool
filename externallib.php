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
 * mod_grouptool external file
 *
 * @package       mod_grouptool
 * @author        Philipp Hager
 * @copyright     2017 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_grouptool\local\tests\grouptool;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir .'/grouplib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot . "/mod/grouptool/locallib.php");

/**
 * Grouptool's external class containing all external functions!
 *
 * @package       mod_grouptool
 * @author        Philipp Hager
 * @copyright     2017 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_group_parameters() {
        return new external_function_parameters(
            [
                    'cmid'    => new external_value(PARAM_INT, 'course module id'),
                    'groupid' => new external_value(PARAM_INT, 'group id')
            ]
        );
    }

    /**
     * Delete a single group
     *
     * @param int $cmid course module ID
     * @param int $groupid group ID
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function delete_group($cmid, $groupid) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::delete_group_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        groups_delete_group($params['groupid']);

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_group_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function rename_group_parameters() {
        // Function onlinetextpreview_parameters() always return an external_function_parameters().
        // The external_function_parameters constructor expects an array of external_description.
        return new external_function_parameters(
            [
                    'cmid'    => new external_value(PARAM_INT, 'course module id'),
                    'groupid' => new external_value(PARAM_INT, 'group id'),
                    'name'    => new external_value(PARAM_TEXT, 'new name')
            ]
        );
    }

    /**
     * Set a new group name for a group
     *
     * @param int $cmid course module ID
     * @param int $groupid group ID
     * @param string $name the new name for the group
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function rename_group($cmid, $groupid, $name) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::rename_group_parameters(), [
                'cmid' => $cmid, 'groupid' => $groupid,
                'name' => $name
        ]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $group = groups_get_group_by_name($course->id, $params['name']);
        $group = $DB->get_record('groups', ['id' => $group]);

        if (!empty($group) && ($group->id != $params['groupid'])) {
            $result->error = get_string('groupnameexists', 'group', $params['name']);
        } else {
            $group = new stdClass();
            $group->id = $params['groupid'];
            $group->name = $params['name'];
            $group->courseid = (int)$course->id;

            groups_update_group($group);
            if ($params['name'] != $DB->get_field('groups', 'name', ['id' => $params['groupid']])) {
                // Error happened...
                $result->error = get_string('couldnt_rename_group', 'grouptool', $params['name']);
            } else {
                $result->message = get_string('renamed_group', 'grouptool', $params['name']);
            }
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function rename_group_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function resize_group_parameters() {
        return new external_function_parameters(
            [
                    'cmid'    => new external_value(PARAM_INT, 'course module id'),
                    'groupid' => new external_value(PARAM_INT, 'group id'),
                    'size'    => new external_value(PARAM_TEXT, 'size or 0')
            ]
        );
    }

    /**
     * Set a new group size value for a group and this instance
     *
     * @param int $cmid course module ID
     * @param int $groupid group ID
     * @param int $size the new group size
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function resize_group($cmid, $groupid, $size) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::resize_group_parameters(), [
                'cmid' => $cmid, 'groupid' => $groupid,
                'size' => $size
        ]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $sql = 'SELECT COUNT(reg.id) AS regcnt
                  FROM {grouptool_agrps} agrps
             LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrps.id AND reg.modified_by >= 0
                 WHERE agrps.grouptoolid = :grouptoolid AND agrps.groupid = :groupid';
        $sqlparams = ['grouptoolid' => $cm->instance, 'groupid' => $params['groupid']];
        $regs = $DB->count_records_sql($sql, $sqlparams);
        if (empty($params['size']) && $params['size'] != '0') {
            // Disable individual size for this group!
            $DB->set_field('grouptool_agrps', 'grpsize', null, [
                    'groupid' => $params['groupid'],
                    'grouptoolid' => $cm->instance
            ]);
            $dbsize = $DB->get_field('grouptool_agrps', 'grpsize', [
                    'groupid'    => $params['groupid'],
                    'grouptoolid' => $cm->instance
            ]);
            if (!empty($dbsize)) {
                // Error happened...
                $result->error = get_string('couldnt_resize_group', 'grouptool', $params['size']);
            } else {
                $result->message = get_string('resized_group', 'grouptool', $params['size']);
            }
        } else if (preg_match('/[1-9]\d*/', clean_param($params['size'], PARAM_INT)) == 0) {
            $result->error = get_string('grpsizezeroerror', 'grouptool');
        } else if (!empty($regs) && $params['size'] < $regs) {
            $result->error = get_string('toomanyregs', 'grouptool');
        } else {
            $DB->set_field('grouptool_agrps', 'grpsize', clean_param($params['size'], PARAM_INT),
                ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]);
            $DB->set_field('grouptool', 'use_size', 1, ['id' => $cm->instance]);
            if ($params['size'] != $DB->get_field('grouptool_agrps', 'grpsize', [
                            'groupid'     => $params['groupid'],
                            'grouptoolid' => $cm->instance
                    ])) {
                // Error happened...
                $result->error = get_string('couldnt_resize_group', 'grouptool', $params['size']);
            } else {
                $result->message = get_string('resized_group', 'grouptool', $params['size']);
            }
        }
        $agrpid = $DB->get_field('grouptool_agrps', 'id', ['grouptoolid' => $cm->instance, 'groupid' => $params['groupid']]);
        $grouptoolrec = $DB->get_record('grouptool', ['id' => $cm->instance]);
        if (!empty($grouptoolrec->use_queue)) {
            $grouptool = new mod_grouptool($cm->id, $grouptoolrec, $cm, $course);
            $grouptool->fill_from_queue($agrpid);
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function resize_group_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function activate_group_parameters() {
        return new external_function_parameters(
            [
                    'cmid'    => new external_value(PARAM_INT, 'course module id'),
                    'groupid' => new external_value(PARAM_INT, 'group id')
            ]
        );
    }

    /**
     * Activate a single group for this instance
     *
     * @param int $cmid course module ID
     * @param int $groupid group ID
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function activate_group($cmid, $groupid) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::activate_group_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $DB->set_field('grouptool_agrps', 'active', 1, ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]);
        if ($DB->get_field('grouptool_agrps', 'active',
                           ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]) == 0) {
            $a = new stdClass();
            $a->groupid = $params['groupid'];
            $a->grouptoolid = $cm->instance;
            $result->error = get_string('error_activating_group', 'grouptool', $a);
        } else {
            $result->message = get_string('activated_group', 'grouptool');
        }

        // TODO no entries message ?!?

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function activate_group_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function deactivate_group_parameters() {
        return new external_function_parameters(
            [
                    'cmid'    => new external_value(PARAM_INT, 'course module id'),
                    'groupid' => new external_value(PARAM_INT, 'group id')
            ]
        );
    }

    /**
     * Deactivate a group for a certain instance
     *
     * @param int $cmid course module ID
     * @param int $groupid group ID
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function deactivate_group($cmid, $groupid) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::deactivate_group_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $DB->set_field('grouptool_agrps', 'active', 0, ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]);
        if ($DB->get_field('grouptool_agrps', 'active',
                           ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]) == 1) {
            $a = new stdClass();
            $a->groupid = $params['groupid'];
            $a->grouptoolid = $cm->instance;
            $result->error = get_string('error_deactivating_group', 'grouptool', $a);
        } else {
            $result->message = get_string('deactivated_group', 'grouptool');
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function deactivate_group_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function reorder_groups_parameters() {
        return new external_function_parameters(
            [
                    'cmid'  => new external_value(PARAM_INT, 'course module id'),
                    'order' => new external_multiple_structure(
                      new external_single_structure([
                          'groupid' => new external_value(PARAM_INT, 'group id'),
                          'order'   => new external_value(PARAM_INT, 'order')
                      ])
                  )
            ]
        );
    }

    /**
     * Reorder multiple groups with given data!
     *
     * @param int $cmid course module ID
     * @param stdClass[] $order array of objects containing groupid and order-number!
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function reorder_groups($cmid, $order) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::reorder_groups_parameters(), ['cmid' => $cmid, 'order' => $order]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $missing = [];
        $failed = [];

        foreach ($params['order'] as $cur) {
            if (!$DB->record_exists('grouptool_agrps', ['groupid' => $cur['groupid'], 'grouptoolid' => $cm->instance])) {
                // Insert missing record!
                $newrecord = new stdClass();
                $newrecord->groupid = $cur['groupid'];
                $newrecord->grouptoolid = $cm->instance;
                $newrecord->active = 0;
                $newrecord->sort_order = $cur['order'];
                $DB->insert_record('grouptool_agrps', $newrecord);
                $missing[] = "groupid ".$cur['groupid'];
            } else {
                $DB->set_field('grouptool_agrps', 'sort_order', $cur['order'], [
                        'groupid'     => $cur['groupid'],
                        'grouptoolid' => $cm->instance
                ]);
                if (!$DB->record_exists('grouptool_agrps', [
                        'groupid' => $cur['groupid'],
                        'grouptoolid' => $cm->instance,
                        'sort_order'  => $cur['order']
                ])) {
                    $failed[] = "groupid ".$cur['groupid'];
                }
            }
        }
        if (count($failed)) {
            $result->error = get_string('error_saving_new_order', 'grouptool', implode(", ", $failed));
        } else if (count($missing)) {
            $result->message = get_string('changes_saved', 'grouptool');
            $result->inserted = $missing;
        } else {
            $result->message = get_string('changes_saved', 'grouptool');
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function reorder_groups_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function swap_groups_parameters() {
        return new external_function_parameters(
            [
                    'cmid' => new external_value(PARAM_INT, 'course module id'),
                    'a'    => new external_value(PARAM_INT, 'group A id'),
                    'b'    => new external_value(PARAM_INT, 'group B id')
            ]
        );
    }

    /**
     * Swap positions of 2 groups...
     *
     * @param int $cmid course module ID
     * @param int $a group ID of first group
     * @param int $b group ID of second group
     * @return stdClass containing possible error message and (return)message
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function swap_groups($cmid, $a, $b) {
        global $DB;

        $result = new stdClass();
        $result->error = false;

        // Parameters validation!
        $params = self::validate_parameters(self::swap_groups_parameters(), ['cmid' => $cmid, 'a' => $a, 'b' => $b]);

        $cm = get_coursemodule_from_id('grouptool', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/grouptool:administrate_groups', $context);
        require_login($course, true, $cm);

        $aorder = $DB->get_field('grouptool_agrps', 'sort_order', [
                'groupid'     => $a,
                'grouptoolid' => $cm->instance
        ]);
        $border = $DB->get_field('grouptool_agrps', 'sort_order', [
                'groupid'     => $b,
                'grouptoolid' => $cm->instance
        ]);
        $DB->set_field('grouptool_agrps', 'sort_order', $border, [
                'groupid'     => $a,
                'grouptoolid' => $cm->instance
        ]);
        $DB->set_field('grouptool_agrps', 'sort_order', $aorder, [
                'groupid'     => $b,
                'grouptoolid' => $cm->instance
        ]);
        // This will only be displayed in the developer console, so we can hardcode the string here!
        $data = new stdClass();
        $data->groupa = $a;
        $data->groupb = $b;
        $data->aorder = $aorder;
        $data->border = $border;
        $result->message = get_string('swapped_groups', 'grouptool', $a);

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function swap_groups_returns() {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'either false, or error message', VALUE_DEFAULT, false),
            'message' => new external_value(PARAM_RAW, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Returns description of the get_grouptools_by_courses parameters
     * @return external_function_parameters
     */
    public static function get_grouptools_by_courses_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'), 'Array of course ids (all enrolled courses if empty array)', VALUE_DEFAULT, []
            ),
        ]);
    }

    /**
     * Returns description of the get_grouptools_by_courses result value
     * @return external_single_structure
     */
    public static function get_grouptools_by_courses_returns() {
        return new external_single_structure([
            'grouptools' => new external_multiple_structure(self::grouptool_structure(), 'All grouptools for the given courses'),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Get all grouptools for the courses with the given ids. If the ids are empty all grouptools from all
     * user-enrolled courses are returned.
     *
     * @param $courseids array the ids of the courses to get grouptools for (all user enrolled courses if empty array)
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function get_grouptools_by_courses($courseids) {

        $params = self::validate_parameters(self::get_grouptools_by_courses_parameters(), [
            'courseids' => $courseids
        ]);

        $rgrouptools = [];
        $warnings = [];

        $mycourses = new stdClass();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the grouptools in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $grouptool_instances = get_all_instances_in_courses("grouptool", $courses);
            foreach ($grouptool_instances as $grouptool_instance) {

                $grouptool = new grouptool($grouptool_instance->coursemodule);
                $rgrouptools[] = self::export_grouptool($grouptool);
            }
        }

        $result = new stdClass();
        $result->grouptools = $rgrouptools;
        $result->warnings = $warnings;
        return $result;
    }

    /**
     * Returns description of the get_grouptool parameters
     * @return external_function_parameters
     */
    public static function get_grouptool_parameters() {
        return new external_function_parameters([
            'grouptoolid' => new external_value(PARAM_INT, 'The id of the grouptool'),
        ]);
    }

    /**
     * Returns description of the get_grouptool result value
     * @return external_single_structure
     */
    public static function get_grouptool_returns() {
        return new external_single_structure([
            'grouptool' => self::grouptool_structure(),
        ]);
    }

    /**
     * Returns the grouptool for the given id
     *
     * @param $grouptoolid
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function get_grouptool($grouptoolid) {
        $params = self::validate_parameters(self::get_grouptool_parameters(), ['grouptoolid' => $grouptoolid]);

        $cm = get_coursemodule_from_instance('grouptool', $params['grouptoolid'], 0, false, MUST_EXIST);
        $grouptool = new mod_grouptool($cm->id);

        $context = context_module::instance($grouptool->get_course_module()->id);
        require_capability('mod/grouptool:view_description', $context);
        require_capability('mod/grouptool:view_own_registration', $context);
        require_capability('mod/grouptool:view_groups', $context);
        self::validate_context($context);

        $result = new stdClass();
        $result->grouptool = self::export_grouptool($grouptool);
        return $result;
    }

    /**
     * Returns description of the register parameters
     * @return external_single_structure
     */
    public static function register_parameters() {
        return new external_function_parameters([
            'grouptoolid' => new external_value(PARAM_INT, 'grouptool id'),
            'id' => new external_value(PARAM_INT, 'group id'),
        ]);
    }

    /**
     * Returns description of the register result values
     * @return external_single_structure
     */
    public static function register_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_RAW, "Message whether the registration was successful"),
        ]);
    }

    /**
     * Registers the current user in the given group of the given grouptool.
     * 
     * @param $grouptoolid
     * @param $id
     * @return object
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceedgroupsize
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public static function register($grouptoolid, $id) {
        $params = self::validate_parameters(self::register_parameters(), ['grouptoolid' => $grouptoolid,'id' => $id]);

        $cm = get_coursemodule_from_instance('grouptool', $params['grouptoolid'], 0, false, MUST_EXIST);
        $grouptool = new mod_grouptool($cm->id);

        $context = context_module::instance($grouptool->get_course_module()->id);
        require_capability('mod/grouptool:view_description', $context);
        require_capability('mod/grouptool:view_own_registration', $context);
        require_capability('mod/grouptool:view_groups', $context);
        self::validate_context($context);

        $result = new stdClass();
        $result->message = $grouptool->register_in_agrp($params['id']);
        return $result;
    }

    /**
     * Returns description of the deregister parameters
     * @return external_single_structure
     */
    public static function deregister_parameters() {
        return new external_function_parameters([
            'grouptoolid' => new external_value(PARAM_INT, 'grouptool id'),
            'id' => new external_value(PARAM_INT, 'group id'),
        ]);
    }

    /**
     * Returns description of the deregister result values
     * @return external_single_structure
     */
    public static function deregister_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_RAW, "Message whether the un-registration was successful"),
        ]);
    }

    /**
     * Deregisters the current user from the given group of the given grouptool.
     *
     * @param $grouptoolid
     * @param $id
     * @return object
     * @throws \mod_grouptool\local\exception\notenoughregs
     * @throws \mod_grouptool\local\exception\registration
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public static function deregister($grouptoolid, $id) {
        $params = self::validate_parameters(self::deregister_parameters(), ['grouptoolid' => $grouptoolid,'id' => $id]);

        $cm = get_coursemodule_from_instance('grouptool', $params['grouptoolid'], 0, false, MUST_EXIST);
        $grouptool = new mod_grouptool($cm->id);

        $context = context_module::instance($grouptool->get_course_module()->id);
        require_capability('mod/grouptool:view_description', $context);
        require_capability('mod/grouptool:view_own_registration', $context);
        require_capability('mod/grouptool:view_groups', $context);
        self::validate_context($context);

        $result = new stdClass();
        $result->message = $grouptool->unregister_from_agrp($params['id']);
        return $result;
    }

    /**
     * Returns description of the change_group_parameters parameters
     * @return external_single_structure
     */
    public static function change_group_parameters() {
        return new external_function_parameters([
            'grouptoolid' => new external_value(PARAM_INT, 'grouptool id'),
            'id' => new external_value(PARAM_INT, 'the id of the group where you want to be'),
        ]);
    }

    /**
     * Returns description of the change_group_parameters result values
     * @return external_single_structure
     */
    public static function change_group_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_RAW, "Message whether the change of group was successful"),
        ]);
    }

    /**
     * Changes the group for the current user to the group with the given id
     *
     * @param $grouptoolid
     * @param $id
     * @return object
     * @throws \mod_grouptool\local\exception\exceedgroupqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserqueuelimit
     * @throws \mod_grouptool\local\exception\exceeduserreglimit
     * @throws \mod_grouptool\local\exception\registration
     * @throws \mod_grouptool\local\exception\regpresent
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public static function change_group($grouptoolid, $id) {
        $params = self::validate_parameters(self::change_group_parameters(), ['grouptoolid' => $grouptoolid,'id' => $id]);

        $cm = get_coursemodule_from_instance('grouptool', $params['grouptoolid'], 0, false, MUST_EXIST);
        $grouptool = new mod_grouptool($cm->id);

        $context = context_module::instance($grouptool->get_course_module()->id);
        require_capability('mod/grouptool:view_description', $context);
        require_capability('mod/grouptool:view_own_registration', $context);
        require_capability('mod/grouptool:view_groups', $context);
        self::validate_context($context);

        $result = new stdClass();
        $result->message = $grouptool->change_group($params['id']);
        return $result;
    }

    /**
     * Returns description of the get_registration_status_parameters parameters
     * @return external_single_structure
     */
    public static function get_registration_status_parameters() {
        return new external_function_parameters([
            'grouptoolid' => new external_value(PARAM_INT, 'grouptool id'),
        ]);
    }

    /**
     * Returns description of the get_registration_status_returns result values
     * @return external_single_structure
     */
    public static function get_registration_status_returns() {
        return new external_single_structure([
            'user_registrations' => self::user_registration_status_structure(),
        ]);
    }

    /**
     * Gets the registration status for the current user in the given grouptool.
     * 
     * @param $grouptoolid
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function get_registration_status($grouptoolid) {
        $params = self::validate_parameters(self::get_registration_status_parameters(), ['grouptoolid' => $grouptoolid,]);

        $cm = get_coursemodule_from_instance('grouptool', $params['grouptoolid'], 0, false, MUST_EXIST);
        $grouptool = new mod_grouptool($cm->id);

        $context = context_module::instance($grouptool->get_course_module()->id);
        require_capability('mod/grouptool:view_description', $context);
        require_capability('mod/grouptool:view_own_registration', $context);
        require_capability('mod/grouptool:view_groups', $context);
        self::validate_context($context);

        $result = new stdClass();
        $result->user_registrations = self::export_user_registrations($grouptool->get_registration_stats(0));

        return $result;
    }

    /**
     * Description of the grouptool structure in result values
     * @return external_single_structure
     */
    public static function grouptool_structure() {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'grouptool id'),
                'instance' => new external_value(PARAM_INT, 'grouptool instance id'),
                'course' => new external_value(PARAM_INT, 'course id the grouptool belongs to'),
                'name' => new external_value(PARAM_TEXT, 'grouptool name'),
                'intro' => new external_value(PARAM_RAW, 'intro/description of the grouptool'),
                'introformat' => new external_value(PARAM_INT, 'intro format'),
                'can_enter' => new external_value(PARAM_INT, 'Is the user allowed to enter a group in this grouptool'),
                'can_leave' => new external_value(PARAM_INT, 'Is the user allowed to leave a group in this grouptool'),
                'group_size' => new external_value(PARAM_INT, 'Size of the groups'),
                'groups' => new external_multiple_structure(self::group_structure(), 'Groups of this grouptool'),
                'user_registrations' => self::user_registration_status_structure(),
            ], 'grouptool information'
        );
    }

    /**
     * Description of the user registration status structure in result values
     * @return external_single_structure
     */
    public static function user_registration_status_structure() {
        return new external_single_structure(
            [
                'registered' => new external_multiple_structure(self::status_group_structure(), 'Groups where the current user is registered'),
                'queued' => new external_multiple_structure(self::status_group_structure(), 'Groups where the current user is queued'),
            ], 'Groups where the current user is registered or queued in'
        );
    }

    /**
     * Description of the status group structure in result values
     * @return external_single_structure
     */
    public static function status_group_structure() {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'group id'),
                'name' => new external_value(PARAM_TEXT, 'group name'),
                'rank' => new external_value(PARAM_TEXT, 'rank of registration or queue in this group', VALUE_OPTIONAL),
            ], 'status group information'
        );
    }

    /**
     * Description of the group structure in result values
     * @return external_single_structure
     */
    public static function group_structure() {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'group id'),
                'name' => new external_value(PARAM_TEXT, 'group name'),
                'group_size' => new external_value(PARAM_INT, 'Size of this group'),
                'rank' => new external_value(PARAM_TEXT, 'rank of registration or queue in this group', VALUE_OPTIONAL),
                'registered' => new external_value(PARAM_INT, 'number of users registered for this group'),
                'queued' => new external_value(PARAM_INT, 'number of users queued for this group'),
                'registered_members' => new external_multiple_structure(self::group_member_structure(), 'registered members of the group if visible for current user', VALUE_OPTIONAL),
                'queued_members' => new external_multiple_structure(self::group_member_structure(), 'queued members of the group if visible for current user', VALUE_OPTIONAL),
            ], 'group information'
        );
    }

    /**
     * Description of the group member structure in result values
     * @return external_single_structure
     */
    public static function group_member_structure() {
        return new external_single_structure(
            [
                'user' => new external_value(PARAM_INT, 'user id'),
            ], 'group member information'
        );
    }

    /**
     * Converts the given grouptool to match the grouptool structure for result values
     *
     * @param $grouptool mod_grouptool  The grouptool to be exported
     * @return stdClass                 The exported grouptool
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public static function export_grouptool($grouptool) {
        $result_grouptool = new stdClass();

        $result_grouptool->id = $grouptool->get_course_module()->id;
        $result_grouptool->instance = $grouptool->get_course_module()->instance;
        $result_grouptool->course = $grouptool->get_course_module()->course;
        $result_grouptool->name = $grouptool->get_grouptool()->name;
        $result_grouptool->intro = $grouptool->get_grouptool()->intro;
        $result_grouptool->introformat = $grouptool->get_grouptool()->introformat;
        $result_grouptool->can_enter = $grouptool->get_grouptool()->allow_reg;
        $result_grouptool->can_leave = $grouptool->get_grouptool()->allow_unreg;
        $result_grouptool->group_size = $grouptool->get_grouptool()->grpsize;
        $result_grouptool->groups = self::export_groups($grouptool, $grouptool->get_active_groups(true, true));
        $result_grouptool->user_registrations = self::export_user_registrations($grouptool->get_registration_stats(0));

        return $result_grouptool;
    }

    /**
     * Exports the given groups to match the group structure for result values.
     *
     * @param $grouptool mod_grouptool  The grouptool of which the groups are part of
     * @param $groups array             The groups to be exported
     * @return array                    The exported groups
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function export_groups($grouptool, $groups) {
        $result_groups = [];

        foreach ($groups as $group_id => $group) {
            $result_group = new stdClass();

            $result_group->id = $group->agrpid;
            $result_group->name = $group->name;
            $result_group->group_size = $group->grpsize;
            $result_group->registered = sizeof($group->registered);
            $result_group->queued = sizeof($group->queued);

            if (!empty($group->rank)) {
                $result_group->rank = $group->rank;
            }

            if ($grouptool->canshowmembers($group->agrpid)) {
                $result_registered_members = [];
                foreach ($group->registered as $id => $registered_member) {
                    $result_registered_member = new stdClass();

                    $result_registered_member->user = $registered_member->userid;
                    
                    $result_registered_members[] = $result_registered_member;
                }

                $result_group->registered_members = $result_registered_members;

                $result_queued_members = [];
                foreach ($group->queued as $id => $queued_member) {
                    $result_queued_member = new stdClass();

                    $result_queued_member->user = $queued_member->userid;

                    $result_queued_members[] = $result_queued_member;
                }

                $result_group->queued_members = $result_queued_members;
            }

            $result_groups[] = $result_group;
        }

        return $result_groups;
    }

    /**
     * Exports the given user registration to match the user registration structure in result values
     *
     * @param $user_registrations object   The registrations of the current user to be exported
     * @return object                      The exported user registrations
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public static function export_user_registrations($user_registrations) {
        $result_registrations = new stdClass();

        $result_registrations->registered = self::export_status_groups($user_registrations->registered);
        $result_registrations->queued = self::export_status_groups($user_registrations->queued);

        return $result_registrations;
    }

    /**
     * Exports the given groups to match the status group structure for result values.
     *
     * @param $groups array             The groups to be exported
     * @return array                    The exported groups
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function export_status_groups($groups) {
        $result_groups = [];

        foreach ($groups as $group_id => $group) {
            $result_group = new stdClass();

            $result_group->id = $group->agrpid;
            $result_group->name = $group->grpname;

            if (!empty($group->rank)) {
                $result_group->rank = $group->rank;
            }

            $result_groups[] = $result_group;
        }

        return $result_groups;
    }
}
