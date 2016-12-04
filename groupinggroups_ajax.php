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
 * Sends groups belonging to a grouping to JS updating select-box-content
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir .'/grouplib.php');

$groupingid = required_param('groupingid', PARAM_INT);
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
$PAGE->set_url('/mod/grouptool/groupinggroups_ajax.php', array('contextid' => $context->id,
                                                               'groupingid'    => $groupingid));

// Get groupings groups.
$nonconf = new stdClass();
$nonconf->id = -1;
$nonconf->name = get_string('nonconflicting', 'grouptool');
$all = new stdClass();
$all->id = 0;
$all->name = get_string('all');
$options = array($nonconf, $all);
$groups = groups_get_all_groups($course->id, null, $groupingid, 'g.id, g.name');
foreach ($groups as $key => $group) {
    $membercount = $DB->count_records('groups_members', array('groupid' => $group->id));
    if ($membercount == 0) {
        continue;
    }
    $option = new stdClass();
    $option->id = $key;
    $option->name = $group->name.' ('.$membercount.')';
    $options[] = $option;
}

echo json_encode($options);
