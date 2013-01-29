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
 * Eventhandlers for module grouptool
 *
 *
 * @package       mod
 * @subpackage    grouptool
 * @copyright     2012 onwards Philipp Hager {@link e0803285@gmail.com}
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/grouptool/definitions.php');

/*
 * group_add_member_handler
 * event:       groups_member_added
 * schedule:    instant
 *
 *  @param array groupid, userid
 *  @global DB
 *  @return bool true if success
 */
function group_add_member_handler($data) {
    global $DB;

    $sql = "SELECT DISTINCT grpt.id, grpt.ifmemberadded, grpt.course,
                            agrp.id as agrpid
            FROM {grouptool} as grpt
            JOIN {grouptool_agrps} AS agrp ON agrp.grouptool_id = grpt.id
            WHERE (agrp.group_id = ?) AND (agrp.active = ?) AND (grpt.ifmemberadded = ?)";
    $params = array($data->groupid, 1, GROUPTOOL_FOLLOW);
    if (! $grouptools = $DB->get_records_sql($sql, $params)) {
        return true;
    }

    $regsql = "SELECT reg.agrp_id AS id
            FROM {grouptool_agrps} AS agrps
            INNER JOIN {grouptool_registered} as reg ON agrps.id = reg.agrp_id
            WHERE agrps.group_id = :groupid AND reg.user_id = :userid";
    $regs = $DB->get_records_sql($regsql, array('groupid' => $data->groupid,
                                                'userid'  => $data->userid));
    foreach ($grouptools as $grouptool) {
        if (!key_exists($grouptool->agrpid, $regs)) {
            $reg = new stdClass();
            $reg->agrp_id = $agrp[$grouptool->id]->id;
            $reg->user_id = $data->userid;
            $reg->timestamp = time();
            $reg->modified_by = 0; //theres no way we can get the teachers id
            $DB->insert_record('grouptool_registered', $reg);
            add_to_log($grouptool->course,
                       'grouptool', 'register',
                       "view.php?id=".$grouptool->id."&tab=overview&groupid=".$data->groupid,
                       'via event agrp='.$grouptool->agrpid.' user='.$data->userid);
        }
    }
    return true;
}
/*
 * group_remove_member_handler
 * event:       groups_member_removed
 * schedule:    instant
 *
 * @param array groupid, userid
 * @global DB
 * @global COURSE
 * @return bool true if success
 */
function group_remove_member_handler($data) {
    global $DB;

    $sql = "SELECT DISTINCT {grouptool}.id, {grouptool}.ifmemberremoved, {grouptool}.course
            FROM {grouptool}
                RIGHT JOIN {grouptool_agrps} AS agrp ON agrp.grouptool_id = {grouptool}.id
            WHERE agrp.group_id = ?";
    $params = array($data->groupid);
    if (! $grouptools = $DB->get_records_sql($sql, $params)) {
        return true;
    }
    $sql = "SELECT agrps.grouptool_id AS grouptoolid, agrps.id AS id FROM {grouptool_agrps} AS agrps
    WHERE agrps.group_id = :groupid";
    $agrp = $DB->get_records_sql($sql, array('groupid' => $data->groupid));
    foreach ($grouptools as $grouptool) {
        switch($grouptool->ifmemberremoved) {
            case GROUPTOOL_FOLLOW:
                $sql = "SELECT reg.id AS id FROM {grouptool_agrps} AS agrps
                INNER JOIN {grouptool_registered} AS reg ON agrps.id = reg.agrp_id
                WHERE reg.user_id = :userid
                        AND agrps.grouptool_id = :grouptoolid
                        AND agrps.group_id = :groupid";
                if ($regs = $DB->get_records_sql($sql,
                        array('grouptoolid' => $grouptool->id,
                              'userid'      => $data->userid,
                              'groupid'     => $data->groupid))) {
                    $DB->delete_records_list('grouptool_registered', 'id', array_keys($regs));
                    add_to_log($grouptool->course,
                            'grouptool', 'unregister',
                            "view.php?id=".$grouptool->id."&tab=overview&groupid=".$data->groupid,
                            'via event agrp='.$agrp[$grouptool->id]->id.' user='.$data->userid);
                }
                break;
            default:
            case GROUPTOOL_IGNORE:
                break;
        }

    }
    return true;
}

/*
 * groups_remove_member_handler
 * event:       groups_members_removed
 * schedule:    instant

 * @param array courseid, userid - user deleted from all coursegroups
 * @global CFG
 * @global DB
 * @return bool true if success
 */
function groups_remove_member_handler($data) {
    global $CFG, $DB;

    $course = $DB->get_record('course', array('id'=>$data->courseid));

    if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
        return true;
    }

    foreach ($grouptools as $cmid => $grouptool) {
        switch($grouptool->ifmemberremoved) {
            case GROUPTOOL_FOLLOW:
                $sql = "SELECT reg.id AS id FROM {grouptool_agrps} AS agrps
                        INNER JOIN {grouptool_registered} AS reg ON agrps.id = reg.agrp_id
                        WHERE reg.user_id = :userid AND agrps.grouptool_id = :grouptoolid";
                if ($regs = $DB->get_records_sql($sql,
                                                array('grouptoolid' => $grouptool->id,
                                                      'userid'      => $data->userid))) {

                    $DB->delete_records_list('grouptool_registered', 'id', array_keys($regs));
                    add_to_log($grouptool->course,
                            'course', 'unregister',
                            "view.php?id=".$data->courseid,
                            'via event course='.$data->courseid.' user='.$data->userid,
                            $cmid);
                }
                break;
            default:
            case GROUPTOOL_IGNORE:
                break;
        }

    }
    return true;
}


/*
 * group_deleted_handler
 * event:       groups_group_deleted
 * schedule:    instant
 *
 *  @param array id, courseid, name, description, timecreated, timemodified, picture
 *  @global CFG
 *  @global DB
 *  @return bool true if success
 */
function group_deleted_handler($data) {
    global $CFG, $DB;

    $course = $DB->get_record('course', array('id'=>$data->courseid), '*', MUST_EXIST);

    if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
        return true;
    }

    $group_recreated = false;
    $agrpids = array();
    foreach ($grouptools as $cmid => $grouptool) {
        switch($grouptool->ifgroupdeleted) {
            default:
            case GROUPTOOL_RECREATE_GROUP:
                if (!$group_recreated) {
                    $new_id = $DB->insert_record('groups', $data, true);
                    if ($new_id !== false) {
                        //delete auto-inserted agrp
                        if ($DB->record_exists('grouptool_agrps', array('group_id'=>$new_id))) {
                            $DB->delete_records('grouptool_agrps', array('group_id'=>$new_id));
                        }
                        //update reference
                        if ($DB->record_exists('grouptool_agrps', array('group_id'=>$data->id))) {
                            $DB->set_field('grouptool_agrps', 'group_id', $new_id,
                                           array('group_id'=>$data->id));
                        }
                        $group_recreated = true;
                        add_to_log($data->courseid,
                                   'course', 'create recreate grouptool group',
                                   "view.php?id=".$data->courseid,
                                   'via event course='.$data->courseid.' grp='.$data->id);
                    } else {
                        print_error('error', 'moodle');
                        return false;
                        $agrpids = array_merge($agrpids,
                                               $DB->get_fieldset_select('grouptool_agrps', 'id',
                                                                        "group_id = ?",
                                                                        array($data->id)));
                    }
                }
                break;
            case GROUPTOOL_DELETE_REF:
                if ($agrpid = $DB->get_field('grouptool_agrps', 'id',
                                             array('group_id'     => $data->id,
                                                   'grouptool_id' => $grouptool->id))) {
                    $agrpids[] = $agrpid;
                }
                break;
        }
    }
    if (count($agrpids) > 0) {
        $DB->delete_records_list('grouptool_registered', 'agrp_id', $agrpids);
        $DB->delete_records_list('grouptool_queued', 'agrp_id', $agrpids);
        $DB->delete_records_list('grouptool_agrps', 'id', $agrpids);
        add_to_log($data->courseid,
                'course', 'delete grouptool references',
                "view.php?id=".$data->courseid,
                'via event course='.$data->courseid.' grp='.$data->id.' agrps='.
                implode('|', $agrpids));
    }

    return true;
}

/*
 * groups_deleted_handler
 * event:       groups_groups_deleted
 * schedule:    instant
 *
 * @param int courseid
 * @global DB
 * @return bool true if success
 */
function groups_deleted_handler($courseid) {
    global $DB;

    //delete all active-groups from grouptool including all connected registrations and queues
    $grouptool_ids = $DB->get_records_list('grouptool', 'course', array($courseid), 'id ASC', 'id');
    $agrps = $DB->get_records_list('grouptool_agrps', 'grouptool_id', $grouptool_ids);
    //@todo logging!
    $DB->delete_records_list('grouptool_queued', 'agrp_id', array_keys($agrps));

    $DB->delete_records_list('grouptool_registered', 'agrp_id', array_keys($agrps));

    $DB->delete_records_list('grouptool_agrps', 'grouptool_id', $grouptool_ids);
    add_to_log($courseid,
            'course', 'delete grouptool references',
            "view.php?id=".$courseid,
            'via event for all coursegroups course='.$courseid.' agrps='.
            implode('|', array_keys($agrps)));
    return true;
}

/*
 * group_created_handler
 * event:       groups_group_created
 * schedule:    instant
 *
 * @param  array id, courseid, name, description, timecreated, timemodified, picture
 * @global DB
 * @return bool true if success
 */
function group_created_handler($data) {
    global $DB;

    $course = $DB->get_record('course', array('id'=>$data->courseid));

    if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
        return true;
    }
    $sortorder = $DB->get_records_sql("SELECT agrp.grouptool_id, MAX(agrp.sort_order) AS max
                                          FROM {grouptool_agrps} AS agrp
                                          GROUP BY agrp.grouptool_id");
    foreach ($grouptools as $cmid => $grouptool) {
        $new_agrp = new StdClass();
        $new_agrp->grouptool_id = $grouptool->id;
        $new_agrp->group_id = $data->id;
        $new_agrp->sort_order = $sortorder[$grouptool->id]->max+1;
        if (!$DB->record_exists('grouptool_agrps', array('grouptool_id' => $grouptool->id,
                                                         'group_id'     => $data->id))) {
            $new_agrp->id = $DB->insert_record('grouptool_agrps', $new_agrp);
            add_to_log($data->courseid, 'grouptool', 'update agrps',
                       "view.php?id=".$grouptool->id."&tab=overview&groupid=".$data->id,
                       'via event course='.$data->courseid.' agrp='.$new_agrp->id, $cmid);
        }
    }
    return true;
}