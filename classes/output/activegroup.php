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
 * Contains activegroup class, representing a single group with additional data.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Representation of single group with additional data!
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activegroup {
    /** @var int $id active groups ID */
    public $id;
    /** @var int $groupid active groups related group ID */
    public $groupid;
    /** @var int $grouptoolid active groups related grouptool ID */
    public $grouptoolid;

    /** @var string $name active groups name */
    public $name;
    /** @var int $size active groups group-size */
    public $size;
    /** @var int $order active groups sort order */
    public $order;
    /** @var int[] $groupings active groups related groupings */
    public $groupings;
    /** @var bool $status active groups status (active/inactive) */
    public $status;

    /** @var bool $selected active groups selection status (selected or not) */
    public $selected;

    /**
     * Constructor for activegroup
     *
     * @param int $id active groups id
     * @param int $groupid active groups group id
     * @param int $grouptoolid active groups grouptool id
     * @param string $name active groups name
     * @param int $size active groups group-size
     * @param int $order active groups sort order
     * @param bool $status active groups status (1-active/0-inactive)
     * @param int[] $groupings (optional) array of active groups grouping ids
     * @param bool $selected (optional) selection status of active group (selected or not)
     */
    public function __construct($id, $groupid, $grouptoolid, $name, $size, $order, $status, $groupings=[], $selected=false) {
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

    /**
     * Convenience method using data in object
     *
     * @param \stdClass $data Object containing all necessary data
     * @return \mod_grouptool\output\activegroup active group object
     */
    public static function construct_from_obj($data) {
        return new activegroup($data->id, $data->groupid, $data->grouptoolid, $data->name,
                               $data->size, $data->order, $data->status, $data->groupings,
                               $data->selected);
    }

    /**
     * Convenience method using group and grouptoolid to fetch it from DB
     *
     * @param int $groupid ID of related moodle-group
     * @param int $grouptoolid ID of related grouptool instance
     * @return \mod_grouptool\output\activegroup active group object
     * @throws \dml_exception
     */
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

        $obj = $DB->get_record_sql($sql, [$groupid, $grouptoolid]);

        if (empty($obj->use_size)) {
            $obj->size = null;
        } else if (empty($obj->individualsize)) {
            $obj->size = $obj->globalsize;
        }

        $obj->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                       FROM {groupings_groups}
                                                  LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                      WHERE groupid = ?", [$groupid]);

        return $this->construct_from_obj($obj);
    }

    /**
     * Load active groups related groupings
     *
     * @return void
     * @throws \dml_exception
     */
    public function load_groupings() {
        global $DB;

        $this->groupings = $DB->get_records_sql_menu("SELECT groupingid, name
                                                        FROM {groupings_groups}
                                                   LEFT JOIN {groupings} ON {groupings_groups}.groupingid = {groupings}.id
                                                       WHERE groupid = ?", [$this->groupid]);
    }
}
