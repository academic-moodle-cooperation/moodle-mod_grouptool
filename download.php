<?php
// This file is part of Moodle - http://moodle.org/
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
 * Serves download-files
 *
 * @package       mod
 * @subpackage    grouptool
 * @copyright     2012 onwards Philipp Hager {@link e0803285@gmail.com}
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('grouptool', $cmid);
$context = context_module::instance($cmid);
$PAGE->set_context($context);
$url = new moodle_url($CFG->wwwroot.'/mod/grouptool/download.php', array('id'=>$cmid));
$PAGE->set_url($url);
$instance = new grouptool($cmid);

require_login($cm->course, true, $cm);
require_capability('mod/grouptool:view_registrations', $context);
require_capability('mod/grouptool:export', $context);

$groupingid = optional_param('groupingid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$PAGE->url->param('groupingid', $groupingid);
$PAGE->url->param('groupid', $groupid);

// Tab determines which table to download (userlist or group overview)!
switch (required_param('tab', PARAM_ALPHA)) {
    case 'overview':
        $PAGE->url->param('tab', 'overview');
        switch(required_param('format', PARAM_INT)) {
            case GROUPTOOL_PDF:
                $PAGE->url->param('format', GROUPTOOL_PDF);
                add_to_log($cm->course,
                           'grouptool', 'export',
                           "download.php?id=".$cmid."&groupingid=".$groupingid.
                           "&groupid=".$groupid."&tab=overview&format=".GROUPTOOL_PDF,
                           get_string('overview', 'grouptool').' PDF',
                           $cm->id);
                echo $instance->download_overview_pdf($groupid, $groupingid);
                break;
            case GROUPTOOL_TXT:
                $PAGE->url->param('format', GROUPTOOL_TXT);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=overview&format=".GROUPTOOL_TXT,
                        get_string('overview', 'grouptool').' TXT',
                        $cm->id);
                echo $instance->download_overview_txt($groupid, $groupingid);
                break;
            case GROUPTOOL_XLS:
                $PAGE->url->param('format', GROUPTOOL_XLS);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=overview&format=".GROUPTOOL_XLS,
                        get_string('overview', 'grouptool').' XLS',
                        $cm->id);
                echo $instance->download_overview_xls($groupid, $groupingid);
                break;
            case GROUPTOOL_ODS:
                $PAGE->url->param('format', GROUPTOOL_ODS);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=overview&format=".GROUPTOOL_ODS,
                        get_string('overview', 'grouptool').' ODS',
                        $cm->id);
                echo $instance->download_overview_ods($groupid, $groupingid);
                break;
            default:

                break;
        }
        break;
    case 'userlist':
        $PAGE->url->param('tab', 'userlist');
        switch(required_param('format', PARAM_INT)) {
            case GROUPTOOL_PDF:
                $PAGE->url->param('format', GROUPTOOL_PDF);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=userlist&format=".GROUPTOOL_PDF,
                        get_string('userlist', 'grouptool').' PDF',
                        $cm->id);
                echo $instance->download_userlist_pdf($groupid, $groupingid);
                break;
            case GROUPTOOL_TXT:
                $PAGE->url->param('format', GROUPTOOL_TXT);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=userlist&format=".GROUPTOOL_TXT,
                        get_string('userlist', 'grouptool').' TXT',
                        $cm->id);
                echo $instance->download_userlist_txt($groupid, $groupingid);
                break;
            case GROUPTOOL_XLS:
                $PAGE->url->param('format', GROUPTOOL_XLS);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=userlist&format=".GROUPTOOL_XLS,
                        get_string('userlist', 'grouptool').' XLS',
                        $cm->id);
                echo $instance->download_userlist_xls($groupid, $groupingid);
                break;
            case GROUPTOOL_ODS:
                $PAGE->url->param('format', GROUPTOOL_ODS);
                add_to_log($cm->course,
                        'grouptool', 'export',
                        "download.php?id=".$cmid."&groupingid=".$groupingid.
                        "&groupid=".$groupid."&tab=userlist&format=".GROUPTOOL_ODS,
                        get_string('userlist', 'grouptool').' ODS',
                        $cm->id);
                echo $instance->download_userlist_ods($groupid, $groupingid);
                break;
            default:

                break;
        }
        break;
}
