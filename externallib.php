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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
                /** @noinspection SpellCheckingInspection */
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
        if (empty($params['size'])) {
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
                /** @noinspection SpellCheckingInspection */
                $result->error = get_string('couldnt_resize_group', 'grouptool', $params['size']);
            } else {
                $result->message = get_string('resized_group', 'grouptool', $params['size']);
            }
        } else if ((clean_param($params['size'], PARAM_INT) < 0) || !ctype_digit($params['size'])) {
            $result->error = get_string('grpsizezeroerror', 'grouptool');
        } else if (!empty($regs) && $params['size'] < $regs) {
            $result->error = get_string('toomanyregs', 'grouptool');
        } else {
            $DB->set_field('grouptool_agrps', 'grpsize', $params['size'],
                ['groupid' => $params['groupid'], 'grouptoolid' => $cm->instance]);
            $DB->set_field('grouptool', 'use_individual', 1, ['id' => $cm->instance]);
            $DB->set_field('grouptool', 'use_size', 1, ['id' => $cm->instance]);
            if ($params['size'] != $DB->get_field('grouptool_agrps', 'grpsize', [
                            'groupid'     => $params['groupid'],
                            'grouptoolid' => $cm->instance
                    ])) {
                // Error happened...
                /** @noinspection SpellCheckingInspection */
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
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
            'message' => new external_value(PARAM_TEXT, 'Returning message', VALUE_DEFAULT, '')
        ]);
    }
}
