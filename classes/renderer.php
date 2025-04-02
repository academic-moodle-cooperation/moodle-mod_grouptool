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

use html_writer;
use stdClass;

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
        global $DB;

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
            if (get_config('mod_grouptool', 'show_add_info')) {
                $groupobj = groups_get_group($id);
                $pictureout = print_group_picture($groupobj, $sortlist->cm->course, false, true, false);
                /*
                 * TODO - This is a hack to get the group picture to display.
                if (empty($pictureout)) {
                    $pictureurl = new \moodle_url('/user/index.php',
                        ['id' => $sortlist->cm->course, 'group' => $group->id]);
                    $pictureobj = html_writer::img($this->image_url('g/g1')->out(false),
                        $group->name, ['title' => $group->name]); // default image.
                    $pictureout = html_writer::link($pictureurl, $pictureobj);
                }
                */
                $sortlist->groups[$id]->grouppix = $pictureout;
            }
            $sortlist->groups[$id]->editurl = new \moodle_url('/group/group.php', [
                'courseid' => $this->page->course->id,
                'id' => $id,
            ]);
        }

        $context = new stdClass();
        $context->usesize = $sortlist->usesize;
        $context->pageurl = $this->page->url->out();
        $url = new \moodle_url('/course/modedit.php', ['update' => $sortlist->cm->id, 'return' => 1]);
        $context->courseediturl = $url->out();
        $statushelpicon = new \help_icon('groupstatus', 'grouptool');
        $movehelpicon = new \help_icon('move', 'grouptool');
        $sizehelpicon = new \help_icon('size', 'grouptool');
        $context->sizehelpicon = $sizehelpicon->export_for_template($this->output);
        $context->movehelpicon = $movehelpicon->export_for_template($this->output);
        $context->statushelpicon = $statushelpicon->export_for_template($this->output);
        $context->groups = array_values($sortlist->groups);

        if ($DB->record_exists('grouptool', ['course' => $sortlist->cm->course, 'ifgroupdeleted' => GROUPTOOL_RECREATE_GROUP])) {
            // Disable delete-Buttons if the group(s) would get recreated anyways...
            $context->nodeletion = true;
        }

        $html = $this->output->render_from_template('mod_grouptool/sortlist', $context);

        $this->page->requires->js_call_amd('mod_grouptool/sortlist', 'initializer', [$sortlist->cm->id]);

        return $html;
    }


    /**
     * Get message stating no groups are to be displayed
     *
     * @param sortlist $sortlist Sortlist to render message for
     * @return string HTML snippet
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function get_no_groups_info(sortlist $sortlist) {

        switch ($sortlist->filter) {
            case \mod_grouptool::FILTER_ACTIVE:
                $url = new \moodle_url($this->page->url, ['filter' => \mod_grouptool::FILTER_ALL, 'tab' => 'group_admin']);
                $message = get_string('nogroupsactive', 'grouptool') . ' ' .
                    \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_INACTIVE:
                $url = new \moodle_url($this->page->url, ['filter' => \mod_grouptool::FILTER_ALL, 'tab' => 'group_admin']);
                $message = get_string('nogroupsinactive', 'grouptool') . ' ' .
                    \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
                break;
            case \mod_grouptool::FILTER_ALL:
                $url = new \moodle_url($this->page->url, ['tab' => 'group_creation']);
                $message = get_string('nogroups', 'grouptool') . ' ' .
                    \html_writer::link($url, get_string('nogroupscreate', 'grouptool'));
                break;
            default:
                $url = new \moodle_url($this->page->url, ['filter' => \mod_grouptool::FILTER_ALL, 'tab' => 'group_admin']);
                $message = get_string('nogroupsgrouping', 'grouptool') . ' ' .
                    \html_writer::link($url, get_string('nogroupschoose', 'grouptool'));
        }
        return $this->output->box($this->output->notification($message, 'info'), 'generalbox', 'nogroupsinfo');
    }

}
