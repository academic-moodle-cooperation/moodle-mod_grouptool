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
 * A sortable list of course groups including some additional information and fields
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Representation a sortable collection of active groups with advanced fields!
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sortlist implements \renderable {
    /** @var string $tableclass CSS class for table */
    public $tableclass = 'coloredrows';
    /** @var \stdClass[] $groupings array of non-empty groupings of this course */
    public $groupings = [];
    /** @var \mod_grouptool\output\activegroup[] $groups array of activegroups */
    public $groups = [];
    /** @var int $globalsize active groups standard/global group size */
    public $globalsize = 0;
    /** @var bool $usesize whether or not to use group size */
    public $usesize = 0;
    /** @var bool $useindividual whether or not to use individual group size per activegroup */
    public $useindividual = 0;
    /** @var int $filter current filter (all/active/inactive) */
    public $filter = null;
    /** @var \stdClass $cm course module object */
    public $cm = null;

    /**
     * Constructor
     *
     * @param int $courseid ID of related course
     * @param \stdClass $cm course module object
     * @param int $filter optional current filter (active/inactive/all)
     */
    public function __construct($courseid, $cm, $filter=null) {
        global $SESSION, $DB, $OUTPUT;

        $this->filter = $filter;

        if ($moveup = optional_param('moveup', 0, PARAM_INT)) {
            // Move up!
            $a = $DB->get_record('grouptool_agrps', [
                'groupid' => $moveup,
                'grouptoolid' => $cm->instance
            ]);
            $b = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $a->grouptoolid,
                'sort_order' => ($a->sort_order - 1)
            ]);
            if (empty($a) || empty($b)) {
                echo $OUTPUT->notification(get_string('couldnt_move_up', 'grouptool'), 'error');
            } else {
                $DB->set_field('grouptool_agrps', 'sort_order', $a->sort_order, ['id' => $b->id]);
                $DB->set_field('grouptool_agrps', 'sort_order', $b->sort_order, ['id' => $a->id]);
            }
        }

        if ($movedown = optional_param('movedown', 0, PARAM_INT)) {
            // Move up!
            $a = $DB->get_record('grouptool_agrps', [
                'groupid' => $movedown,
                'grouptoolid' => $cm->instance
            ]);
            $b = $DB->get_record('grouptool_agrps', [
                'grouptoolid' => $a->grouptoolid,
                'sort_order' => ($a->sort_order + 1)
            ]);
            if (empty($a) || empty($b)) {
                echo $OUTPUT->notification(get_string('couldnt_move_down', 'grouptool'), 'error');
            } else {
                $DB->set_field('grouptool_agrps', 'sort_order', $a->sort_order, ['id' => $b->id]);
                $DB->set_field('grouptool_agrps', 'sort_order', $b->sort_order, ['id' => $a->id]);
            }
        }

        if ($courseid != null) {
            $this->loadgroups($courseid, $cm);
            $this->cm = $cm;
            $grouptool = $DB->get_record('grouptool', ['id' => $cm->instance]);
            $this->usesize = $grouptool->use_size;
            $this->useindividual = $grouptool->use_individual;
        }

        $this->selected = optional_param_array('selected', null, \PARAM_BOOL);
        if (!isset($SESSION->sortlist)) {
            $SESSION->sortlist = new \stdClass();
        }
        if (!isset($SESSION->sortlist->selected)) {
            $SESSION->sortlist->selected = [];
        }

        if ($this->selected == null) {
            $this->selected = $SESSION->sortlist->selected;
        } else {
            $SESSION->sortlist->selected = $this->selected;
        }
    }

    /**
     * Load the groups from DB
     *
     * @param int $courseid Course for whom to fetch the groups
     * @param \stdClass $cm course module object
     */
    public function loadgroups($courseid, $cm) {
        global $DB;

        $grouptool = $DB->get_record('grouptool', ['id' => $cm->instance]);
        // Prepare agrp-data!
        $coursegroups = groups_get_all_groups($courseid, null, null, "id");
        if (is_array($coursegroups) && !empty($coursegroups)) {
            $groups = [];
            foreach ($coursegroups as $group) {
                $groups[] = $group->id;
            }
            list($grpssql, $params) = $DB->get_in_or_equal($groups);

            if ($this->filter == \mod_grouptool::FILTER_ACTIVE) {
                $activefilter = ' AND active = 1 ';
            } else if ($this->filter == \mod_grouptool::FILTER_INACTIVE) {
                $activefilter = ' AND active = 0 ';
            } else {
                $activefilter = '';
            }
            if (!is_object($cm)) {
                $cm = get_coursemodule_from_id('grouptool', $cm);
            }
            $params = array_merge([$cm->instance], $params);
            $groupdata = (array)$DB->get_records_sql("
                    SELECT MAX(grp.id) AS groupid, MAX(agrp.id) AS id,
                           MAX(agrp.grouptoolid) AS grouptoolid,  MAX(grp.name) AS name,
                           MAX(agrp.grpsize) AS size, MAX(agrp.sort_order) AS sort_order,
                           MAX(agrp.active) AS status
                      FROM {groups} grp
                 LEFT JOIN {grouptool_agrps} agrp
                           ON agrp.groupid = grp.id AND agrp.grouptoolid = ?
                     WHERE grp.id ".$grpssql.$activefilter."
                  GROUP BY grp.id
                  ORDER BY status DESC, sort_order ASC, name ASC", $params);

            // Convert to multidimensional array and add groupings.
            $runningidx = 1;
            foreach ($groupdata as $key => $group) {
                $groupdata[$key] = $group;
                $groupdata[$key]->selected = 0;
                $groupdata[$key]->order = $runningidx;
                if ($group->status !== null) {
                    $groupdata[$key]->status = $group->status ? true : false;
                }
                $runningidx++;
                $groupdata[$key]->groupings = $DB->get_records_sql_menu("
                                                    SELECT DISTINCT groupingid, name
                                                      FROM {groupings_groups}
                                                 LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                     WHERE {groupings}.courseid = ? AND {groupings_groups}.groupid = ?",
                                                                        [$courseid, $group->groupid]);
            }
        }

        if (!empty($groupdata) && is_array($groupdata)) {
            $this->globalgrpsize = $grouptool->grpsize ? $grouptool->grpsize : get_config('mod_grouptool', 'grpsize');

            foreach ($groupdata as $key => $group) {
                if ($grouptool->use_size && (!$grouptool->use_individual || ($groupdata[$key]->size == null))) {
                    $groupdata[$key]->size = $this->globalgrpsize.'*';
                }

                // Convert to activegroup object!
                $groupdata[$key] = activegroup::construct_from_obj($group);
            }

            $this->groups = $groupdata;

            // Add groupings (only non-empty ones)...
            $this->groupings = $DB->get_records_sql_menu("
                    SELECT DISTINCT groupingid, name
                      FROM {groupings_groups}
                 LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                     WHERE {groupings}.courseid = ?
                  ORDER BY name ASC", [$courseid]);
        }
    }

    /**
     * Compare if two groups are in correct order
     *
     * @param \mod_grouptool\output\activegroup $a
     * @param \mod_grouptool\output\activegroup $b
     *
     * @return int -1 (a > b)| 0 (a == b)| 1 (a > b)
     */
    public function cmp($a, $b) {
        if ($a->order == $b->order) {
            return 0;
        } else {
            return $a->order > $b->order ? 1 : -1;
        }
    }

    /**
     * Update the element selected-state if corresponding params are set
     */
    public function _refresh_select_state() {
        global $COURSE;
        $action = optional_param('class_action', 0, \PARAM_ALPHA);
        $gobutton = optional_param('do_class_action', 0, \PARAM_BOOL);

        if (empty($gobutton)) {
            return;
        }

        if ( $groupings == null || count($groupings) == 0 ) {
            return;
        }

        if (!empty($action)) {
            $groups = [];
            foreach ($groupings as $groupingid) {
                $groups = array_merge($groups, groups_get_all_groups($COURSE->id, 0, $groupingid));
            }

            foreach ($groups as $current) {
                switch ($action) {
                    case 'select':
                        $sortlist->groups[$current->id]['selected'] = 1;
                        break;
                    case 'deselect':
                        $sortlist->groups[$current->id]['selected'] = 0;
                        break;
                    case 'toggle':
                        $next = !$sortlist->groups[$current->id]['selected'];
                        $sortlist->groups[$current->id]['selected'] = $next;
                        break;
                }
            }
        }
    }
}
