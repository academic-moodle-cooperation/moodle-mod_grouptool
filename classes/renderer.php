<?php
// This file is part of local_checkmarkreport for Moodle - http://moodle.org/
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
 * This file contains the renderer class for mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;

use \stdClass as stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Grouptools renderer class
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render a sortable list of groups with some additional controls
     *
     * @param sortlist $sortlist Sortlist to render
     * @return bool|string
     * @throws \moodle_exception
     */
    public function render_sortlist(sortlist $sortlist) {
        global $PAGE, $OUTPUT;

        if (empty($sortlist->groups) || !is_array($sortlist->groups) || count($sortlist->groups) == 0) {
            return $this->get_no_groups_info($sortlist);
        }

        foreach ($sortlist->groups as $id => $group) {
            $sortlist->groups[$id]->checked = !empty($sortlist->selected[$id]);
            $sortlist->groups[$id]->missing = ($group->status == null);
            // Ensure the keys are right!
            if (!empty($group->groupings)) {
                $sortlist->groups[$id]->groupingids = array_keys($group->groupings);
            } else {
                $sortlist->groups[$id]->groupingids = [];
            }
            $sortlist->groups[$id]->id = $id;
            $sortlist->groups[$id]->order = !empty($group->order) ? $group->order : 999999;
        }

        $context = new stdClass();
        $context->usesize = $sortlist->usesize;
        $context->pageurl = $PAGE->url->out();
        $url = new \moodle_url('/course/modedit.php', ['update' => $sortlist->cm->id, 'return' => 1]);
        $context->courseediturl = $url->out();
        $statushelpicon = new \help_icon('groupstatus', 'grouptool');
        $context->statushelpicon = $statushelpicon->export_for_template($OUTPUT);
        $context->groups = array_values($sortlist->groups);

        $html = $OUTPUT->render_from_template('mod_grouptool/sortlist', $context);

        $PAGE->requires->js_call_amd('mod_grouptool/sortlist', 'initializer', [$sortlist->cm->id]);

        return $html;
    }

    /**
     * Get message stating no groups are to be displayed
     *
     * @param \mod_grouptool\sortlist $sortlist Sortlist to render message for
     * @return string HTML snippet
     */
    protected function get_no_groups_info(sortlist $sortlist) {
        global $PAGE, $OUTPUT;

        switch ($sortlist->filter) {
            case \mod_grouptool::FILTER_ACTIVE:
                $url = new \moodle_url($PAGE->url, ['filter' => \mod_grouptool::FILTER_ALL]);
                $message = get_string('nogroupsactive', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_INACTIVE:
                $url = new \moodle_url($PAGE->url, ['filter' => \mod_grouptool::FILTER_ALL]);
                $message = get_string('nogroupsinactive', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_ALL:
                $url = new \moodle_url($PAGE->url, ['tab' => 'group_creation']);
                $message = get_string('nogroups', 'grouptool').' '.
                           \html_writer::link($url, get_string('nogroupscreate', 'grouptool'));
                break;
            default:
                $message = var_dump($sortlist->filter);
        }
        return $OUTPUT->box($OUTPUT->notification($message, 'info'), 'generalbox', 'nogroupsinfo');
    }

    /**
     * Render a controller for the sortable list of groups
     *
     * @param \mod_grouptool\sortlist_controller $controller Sortlist controller to render
     * @return    string
     */
    protected function render_sortlist_controller(sortlist_controller $controller) {
        global $OUTPUT;

        $sortlist = $controller->sortlist;

        // Generate groupings-controls to select/deselect groupings!
        $context = new stdClass();

        $helpicon = new stdClass();
        $helpicon->title = get_string('checkbox_control_header', 'grouptool');
        $helpicon->text = get_string('checkbox_control_header_help', 'grouptool');
        $context->helpicon = $helpicon;

        $options = [];
        if (!empty($sortlist->groupings) && is_array($sortlist->groupings)) {
            foreach ($sortlist->groupings as $groupingid => $grouping) {
                /*
                 * We have only non-empty groupings here, it should also work with empty ones but would make no sense.
                 * Maybe we use disabled options for all the empty groupings.
                 */
                $options[] = ['id' => $groupingid, 'name' => $grouping];
            }
        }
        $context->options = $options;

        return $OUTPUT->render_from_template("mod_grouptool/checkboxcontroller", $context);
    }
}
