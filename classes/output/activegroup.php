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
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager (office@phager.at)
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;
 
defined('MOODLE_INTERNAL') || die();

/**
 * Representation of single group with advanced fields!
 *
 * TODO: should we make these renderable with a nice standardised view?
 */
class activegroup {
    public $id;
    public $groupid;
    public $grouptoolid;

    public $name;
    public $size;
    public $order;
    public $groupings;
    public $status;

    public $selected;

    public function __construct($id, $groupid, $grouptoolid, $name, $size, $order, $status, $groupings=array(), $selected=0) {
        $this->id = $id;
        $this->groupid = $groupid;
        $this->grouptoolid = $grouptoolid;
        $this->name = $name;
        $this->size = $size;
        $this->order = $order;
        $this->groupings = $groupings;
        $this->status = $status;
        $this->selected = $selected;
    }

    public static function construct_from_obj($data) {
        return new activegroup($data->id, $data->groupid, $data->grouptoolid, $data->name,
                               $data->size, $data->order, $data->status, $data->groupings,
                               $data->selected);
    }

    public function get_by_groupid($groupid, $grouptoolid) {
        global $DB;

        $sql = "SELECT agrp.id AS id, agrp.grouptoolid AS grouptoolid, agrp.groupid AS groupid,
                       agrp.grpsize AS size, agrp.sort_order AS order, agrp.active AS status,
                       grp.name AS name, grouptool.use_size AS use_size, grouptool.grpsize AS globalsize,
                       grouptool.use_individual AS individualsize
                  FROM {grouptool_agrps} agrp
             LEFT JOIN {groups} grp ON agrp.groupid = grp.id
             LEFT JOIN {grouptool} grptl ON agrp.grouptoolid = grptl.id
                 WHERE agrp.groupid = ? AND agrp.grouptoolid = ?";

        $obj = $DB->get_record_sql($sql, array($groupid, $grouptoolid));

        if (empty($obj->use_size)) {
            $obj->size = null;
        } else if (empty($obj->individualsize)) {
            $obj->size = $obj->globalsize;
        }

        $obj->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                       FROM {groupings_groups}
                                                  LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                      WHERE groupid = ?", array($groupid));

        return $this->construct_from_obj($obj);
    }

    public function load_groupings() {
        $this->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                        FROM {groupings_groups}
                                                   LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                       WHERE groupid = ?", array($this->groupid));
    }
}
