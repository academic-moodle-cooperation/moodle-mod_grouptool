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
 * showmembers_ajax.php
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
try {
    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->libdir .'/grouplib.php');
    require_once($CFG->dirroot.'/group/lib.php');
    require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

    $action = required_param('action', PARAM_ALPHANUMEXT);
    $contextid = required_param('contextid', PARAM_INT);
    $lang   = optional_param('lang', 'en', PARAM_LANG);

    // If session has expired and its an ajax request so we cant do a page redirect!
    if (!isloggedin()) {
        $result = new stdClass();
        $result->error = get_string('sessionerroruser', 'error');
        echo json_encode($result);
        die();
    }
    list($context, $course, $cm) = get_context_info_array($contextid);
    require_login($course, false, $cm);

    $contextid = null; // Now we have a context object throw away the id from the user!
    $PAGE->set_context($context);
    $PAGE->set_url('/mod/grouptool/editgroup_ajax.php', array('action' => $action,
                                                              'contextid' => $context->id,
                                                              'lang' => $lang));
    $result = new stdClass();
    $result->error = false;
    if ($action == 'test') {
        $result->message = "SUCCESS!";
        echo json_encode($result);
        die;
    }

    switch ($action) {
        case 'delete': // Delete Group...
            $groupid = required_param('groupid', PARAM_INT);
            require_capability('mod/grouptool:administrate_groups', $context);
            groups_delete_group($groupid);
            break;
        case 'rename': // Rename to...
            $groupid = required_param('groupid', PARAM_INT);
            require_capability('mod/grouptool:administrate_groups', $context);
            $name = required_param('name', PARAM_TEXT);
            $group = groups_get_group_by_name($course->id, $name);
            $group = $DB->get_record('groups', array('id' => $group));

            if (!empty($group) && ($group->id != $groupid)) {
                $result->error = get_string('groupnameexists', 'group', $name);
            } else {
                $group = new stdClass();
                $group->id = $groupid;
                $group->name = $name;
                $group->courseid = (int)$course->id;

                groups_update_group($group);
                if ($name != $DB->get_field('groups', 'name', array('id' => $groupid))) {
                    // Error happened...
                    $result->error = get_string('couldnt_rename_group', 'grouptool', $name);
                } else {
                    $result->message = get_string('renamed_group', 'grouptool', $name);
                }
            }
            break;
        case 'resize': // Resize to...
            $groupid = required_param('groupid', PARAM_INT);
            require_capability('mod/grouptool:administrate_groups', $context);
            $size = required_param('size', PARAM_TEXT);
            $sql = '
       SELECT COUNT(reg.id) regcnt
         FROM {grouptool_agrps} agrps
    LEFT JOIN {grouptool_registered} reg ON reg.agrpid = agrps.id AND reg.modified_by >= 0
        WHERE agrps.grouptoolid = :grouptoolid AND agrps.groupid = :groupid';
            $params = array('grouptoolid' => $cm->instance, 'groupid' => $groupid);
            $regs = $DB->count_records_sql($sql, $params);
            if (empty($size)) {
                // Disable individual size for this group!
                $DB->set_field('grouptool_agrps', 'grpsize', null, array('groupid' => $groupid, 'grouptoolid' => $cm->instance));
                $dbsize = $DB->get_field('grouptool_agrps', 'grpsize', array('groupid'    => $groupid,
                                                                               'grouptoolid' => $cm->instance));
                if (!empty($dbsize)) {
                    // Error happened...
                    $result->error = get_string('couldnt_resize_group', 'grouptool', $name);
                } else {
                    $result->message = get_string('resized_group', 'grouptool', $name);
                }
            } else if ((clean_param($size, PARAM_INT) < 0) || !ctype_digit($size)) {
                    $result->error = get_string('grpsizezeroerror', 'grouptool');
            } else if (!empty($regs) && $size < $regs) {
                $result->error = get_string('toomanyregs', 'grouptool');
            } else {
                $DB->set_field('grouptool_agrps', 'grpsize', $size,
                               array('groupid' => $groupid, 'grouptoolid' => $cm->instance));
                $DB->set_field('grouptool', 'use_individual', 1, array('id' => $cm->instance));
                $DB->set_field('grouptool', 'use_size', 1, array('id' => $cm->instance));
                if ($size != $DB->get_field('grouptool_agrps', 'grpsize', array('groupid'     => $groupid,
                                                                                'grouptoolid' => $cm->instance))) {
                    // Error happened...
                    $result->error = get_string('couldnt_resize_group', 'grouptool', $name);
                } else {
                    $result->message = get_string('resized_group', 'grouptool', $name);
                }
            }
            break;
        case 'activate':
            $groupid = required_param('groupid', PARAM_INT);
            $filter = required_param('filter', PARAM_INT);
            $DB->set_field('grouptool_agrps', 'active', 1, array('groupid' => $groupid, 'grouptoolid' => $cm->instance));
            if ($DB->get_field('grouptool_agrps', 'active', array('groupid' => $groupid, 'grouptoolid' => $cm->instance)) == 0) {
                $result->error = "Couldn't activate group ".$groupid." in grouptool ".$cm->instance."!";
            } else {
                $result->message = "Activated group ".$groupid." in grouptool ".$cm->instance."!";
            }
            $result->filtertabs = new stdClass();
            $result->filtertabs->current = $filter;
            $result->filtertabs->activestr = get_string('active', 'grouptool');
            $result->filtertabs->inactivestr = get_string('inactive');
            $result->filtertabs->allstr = get_string('all');
            $result->filtertabs->activeid = mod_grouptool::FILTER_ACTIVE;
            $result->filtertabs->inactiveid = mod_grouptool::FILTER_INACTIVE;
            $result->filtertabs->allid = mod_grouptool::FILTER_ALL;
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance, 'active' => 1))) {
                $activeurl = new moodle_url('/mod/grouptool/view.php',
                                            array('id'     => $cm->id,
                                                  'tab'    => 'group_admin',
                                                  'filter' => mod_grouptool::FILTER_ACTIVE));
                $result->filtertabs->active = $activeurl->out(false);
            } else {
                $result->filtertabs->active = '';
            }
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance, 'active' => 0))) {
                $inactiveurl = new moodle_url('/mod/grouptool/view.php',
                                              array('id'     => $cm->id,
                                                    'tab'    => 'group_admin',
                                                    'filter' => mod_grouptool::FILTER_INACTIVE));
                $result->filtertabs->inactive = $inactiveurl->out(false);
            } else {
                $result->filtertabs->inactive = '';
            }
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance))) {
                $allurl = new moodle_url('/mod/grouptool/view.php',
                                         array('id'     => $cm->id,
                                               'tab'    => 'group_admin',
                                               'filter' => mod_grouptool::FILTER_ALL));
                $result->filtertabs->all = $allurl->out(false);
            } else {
                $result->filtertabs->all = '';
            }

            break;
        case 'deactivate':
            $groupid = required_param('groupid', PARAM_INT);
            $filter = required_param('filter', PARAM_INT);
            $DB->set_field('grouptool_agrps', 'active', 0, array('groupid' => $groupid, 'grouptoolid' => $cm->instance));
            if ($DB->get_field('grouptool_agrps', 'active', array('groupid' => $groupid, 'grouptoolid' => $cm->instance)) == 1) {
                $result->error = "Couldn't deactivate group ".$groupid." in grouptool ".$cm->instance."!";
            } else {
                 $result->message = "Deactivated group ".$groupid." in grouptool ".$cm->instance."!";
            }

            $result->filtertabs = new stdClass();
            $result->filtertabs->current = $filter;
            $result->filtertabs->activestr = get_string('active', 'grouptool');
            $result->filtertabs->inactivestr = get_string('inactive');
            $result->filtertabs->allstr = get_string('all');
            $result->filtertabs->activeid = mod_grouptool::FILTER_ACTIVE;
            $result->filtertabs->inactiveid = mod_grouptool::FILTER_INACTIVE;
            $result->filtertabs->allid = mod_grouptool::FILTER_ALL;
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance, 'active' => 1))) {
                $activeurl = new moodle_url('/mod/grouptool/view.php',
                                            array('id'     => $cm->id,
                                                  'tab'    => 'group_admin',
                                                  'filter' => mod_grouptool::FILTER_ACTIVE));
                $result->filtertabs->active = $activeurl->out(false);
            } else {
                $result->filtertabs->active = '';
            }
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance, 'active' => 0))) {
                $inactiveurl = new moodle_url('/mod/grouptool/view.php',
                                              array('id'     => $cm->id,
                                                    'tab'    => 'group_admin',
                                                    'filter' => mod_grouptool::FILTER_INACTIVE));
                $result->filtertabs->inactive = $inactiveurl->out(false);
            } else {
                $result->filtertabs->inactive = '';
            }
            if ($DB->count_records('grouptool_agrps', array('grouptoolid' => $cm->instance))) {
                $allurl = new moodle_url('/mod/grouptool/view.php',
                                         array('id'     => $cm->id,
                                               'tab'    => 'group_admin',
                                               'filter' => mod_grouptool::FILTER_ALL));
                $result->filtertabs->all = $allurl->out(false);
            } else {
                $result->filtertabs->all = '';
            }
            break;
        case 'reorder': // Reorder groups...
            $data = required_param_array('order', PARAM_INT);
            $failed = array();
            $missing = array();
            foreach ($data as $groupid => $order) {
                if (!$DB->record_exists('grouptool_agrps', array('groupid' => $groupid, 'grouptoolid' => $cm->instance))) {
                    // Insert missing record!
                    $newrecord = new stdClass();
                    $newrecord->groupid = $groupid;
                    $newrecord->grouptoolid = $cm->instance;
                    $newrecord->active = 0;
                    $newrecord->sort_order = $order;
                    $DB->insert_record('grouptool_agrps', $newrecord);
                    $missing[] = "groupid ".$groupid;
                } else {
                    $DB->set_field('grouptool_agrps', 'sort_order', $order, array('groupid'     => $groupid,
                                                                                  'grouptoolid' => $cm->instance));
                    if (!$DB->record_exists('grouptool_agrps', array('groupid'     => $groupid,
                                                                     'grouptoolid' => $cm->instance,
                                                                     'sort_order'  => $order))) {
                        $failed[] = "groupid ".$groupid;
                    }
                }
            }
            if (count($failed)) {
                $result->error = "Failed to set order for:\n".implode(", ", $failed);
            } else if (count($inserted)) {
                $result->message = "Everything went OK, but we had to insert missing entries:\n".implode(", ", $missing);
            } else {
                $result->message = "Everything went OK!";
            }
            break;
        case 'swap':
            $groupa = required_param('groupA', PARAM_INT);
            $groupb = required_param('groupB', PARAM_INT);
            $groupaorder = $DB->get_field('grouptool_agrps', 'sort_order', array('groupid' => $groupa,
                                                                                 'grouptoolid' => $cm->instance));
            $groupborder = $DB->get_field('grouptool_agrps', 'sort_order', array('groupid' => $groupb,
                                                                                 'grouptoolid' => $cm->instance));
            $DB->set_field('grouptool_agrps', 'sort_order', $groupborder, array('groupid' => $groupa,
                                                                                'grouptoolid' => $cm->instance));
            $DB->set_field('grouptool_agrps', 'sort_order', $groupaorder, array('groupid' => $groupb,
                                                                                'grouptoolid' => $cm->instance));
            $result->message = "Swapped from GroupA(".$groupa."|".$groupaorder.") GroupB(".$groupb."|".$groupborder.") to GroupA(".
                               $groupa."|".$groupborder.") GroupB(".$groupb."|".$groupaorder.")!";
            break;
    }
} catch (Exception $e) {
    $result->error = 'Exception abgefangen: '.$e->getMessage()."\n";
}

echo json_encode($result);
