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
 * Contains class of checkboxcontroller with group(ings)-specific functionality
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Representation of a controller for use with sortlist!
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sortlist_controller implements \renderable {

    /** @var \mod_grouptool\output\sortlist Sortlist instance */
    public $sortlist = null;

    /**
     * Constructor
     *
     * @param \mod_grouptool\output\sortlist $sortlist Sortlist to be used without
     */
    public function __construct(sortlist &$sortlist) {
        global $SESSION;
        $this->sortlist = $sortlist;

        $classes = optional_param_array('classes', array(0), \PARAM_INT);
        $action = optional_param('class_action', 0, \PARAM_ALPHA);
        $gobutton = optional_param('do_class_action', 0, \PARAM_BOOL);

        if (!empty($gobutton) && ($classes != null) && (count($classes) != 0) && !empty($action)) {

            $groups = array();
            foreach ($classes as $groupingid) {
                $groups = array_merge($groups, groups_get_all_groups($this->sortlist->cm->course, 0, $groupingid));
            }

            foreach ($groups as $current) {
                switch ($action) {
                    case 'select':
                        $this->sortlist->selected[$current->id] = 1;
                        break;
                    case 'deselect':
                        $this->sortlist->selected[$current->id] = 0;
                        break;
                    case 'toggle':
                        $next = empty($this->sortlist->selected[$current->id]) ? 1 : 0;
                        $this->sortlist->selected[$current->id] = $next;
                        break;
                }
            }

            // Update SESSION!
            $SESSION->sortlist->selected = $this->sortlist->selected;
        }
    }
}