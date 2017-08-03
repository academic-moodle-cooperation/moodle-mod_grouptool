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
 * This file contains the moodle hooks for the grouptool module.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/definitions.php');

/*******************************************************************************
 * Moodle core API                                                             *
 *******************************************************************************/

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function grouptool_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;

        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_IDNUMBER:
        default:
            return false;
    }
}

/**
 * Saves a new instance of the grouptool into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $grouptool An object from the form in mod_form.php
 * @return int The id of the newly inserted grouptool record
 */
function grouptool_add_instance(stdClass $grouptool) {
    global $DB;

    $grouptool->timecreated = time();

    if (!isset($grouptool->use_size)) {
        $grouptool->use_size = 0;
    }
    if (!isset($grouptool->use_individual)) {
        $grouptool->use_individual = 0;
    }
    if (!isset($grouptool->use_queue)) {
        $grouptool->use_queue = 0;
    }
    if (!isset($grouptool->users_queues_limit)) {
        $grouptool->users_queues_limit = 0;
    }
    if (!isset($grouptool->groups_queues_limit)) {
        $grouptool->groups_queues_limit = 0;
    }
    if (!isset($grouptool->allow_multiple)) {
        $grouptool->allow_multiple = 0;
        $grouptool->choose_min = 0;
        $grouptool->choose_max = 1;
    } else {
        $grouptool->choose_min = clean_param($grouptool->choose_min, PARAM_INT);
        $grouptool->choose_max = clean_param($grouptool->choose_max, PARAM_INT);
    }

    $grouptool->grpsize = clean_param($grouptool->grpsize, PARAM_INT);

    $return = $DB->insert_record('grouptool', $grouptool);

    grouptool_refresh_events($grouptool->course, $return);

    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($grouptool->course));
    foreach ($coursegroups as $groupid) {
        if (!$DB->record_exists('grouptool_agrps', array('grouptoolid' => $return,
                                                         'groupid'     => $groupid))) {
            $record = new stdClass();
            $record->grouptoolid = $return;
            $record->groupid = $groupid;
            $record->sort_order = 9999999;
            $record->grpsize = $grouptool->grpsize;
            $record->active = 0;
            $DB->insert_record('grouptool_agrps', $record);
        }
    }

    return $return;
}

/**
 * Updates an instance of the grouptool in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $grouptool An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function grouptool_update_instance(stdClass $grouptool) {
    global $DB, $CFG;

    $grouptool->timemodified = time();
    $grouptool->id = $grouptool->instance;

    if (!isset($grouptool->use_size)) {
        $grouptool->use_size = 0;
    }
    if (!isset($grouptool->use_individual)) {
        $grouptool->use_individual = 0;
    }
    if (!isset($grouptool->use_queue)) {
        $queues = $DB->count_records_sql("SELECT COUNT(DISTINCT queues.id) AS count
                                            FROM {grouptool_agrps} agrps
                                       LEFT JOIN {grouptool_queued} queues ON queues.agrpid = agrps.id
                                           WHERE agrps.grouptoolid = ? AND agrps.active = 1", array($grouptool->instance));
        if (!empty($queues)) {
            $grouptool->use_queue = 1;
        } else {
            $grouptool->use_queue = 0;
            $grouptool->users_queues_limit = 0;
            $grouptool->groups_queues_limit = 0;
        }
    }
    if (!isset($grouptool->allow_multiple)) {
        $grouptool->allow_multiple = 0;
    }

    $grouptool->grpsize = clean_param($grouptool->grpsize, PARAM_INT);
    $grouptool->choose_min = clean_param($grouptool->choose_min, PARAM_INT);
    $grouptool->choose_max = clean_param($grouptool->choose_max, PARAM_INT);

    // Register students if immediate registration has been turned on!
    if ($grouptool->immediate_reg) {
        require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
        $instance = new mod_grouptool($grouptool->coursemodule, $grouptool);
        $instance->push_registrations();
    }

    grouptool_refresh_events($grouptool->course, $grouptool->instance);

    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($grouptool->course));
    foreach ($coursegroups as $groupid) {
        if (!$DB->record_exists('grouptool_agrps', array('grouptoolid' => $grouptool->instance,
                                                         'groupid'     => $groupid))) {
            $record = new stdClass();
            $record->grouptoolid = $grouptool->instance;
            $record->groupid = $groupid;
            $record->sort_order = 9999999;
            $record->grpsize = $grouptool->grpsize;
            $record->active = 0;
            $DB->insert_record('grouptool_agrps', $record);
        }
    }

    // We have to override the functions fetching of data, because it's not updated yet!
    grouptool_update_queues($grouptool);

    return $DB->update_record('grouptool', $grouptool);
}

/**
 * Make sure up-to-date events are created for all grouptool instances
 *
 * This standard function will check all instances of this module
 * and make sure there are up-to-date vents created for each of them.
 * If course = 0, then every grouptool event in the site is checked, else
 * only grouptool events belonging to the course specified are checked.
 * This function is used, in it's new format, by restore_refresh_events()
 *
 * @param int $course (optional) If zero then all Grouptools for all courses are covered
 * @param int $grouptoolid (optional) If zero then only course filter is active!
 *
 * @throws coding_exception
 *
 * @return bool Always returns true
 */
function grouptool_refresh_events($course = 0, $grouptoolid = 0) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    if ($grouptoolid == 0) {
        if ($course == 0) {
            $cond = array();
        } else {
            $cond = array('course' => $course);
        }
    } else {
        if ($course == 0) {
            $cond = array('id' => $grouptoolid);
        } else {
            $cond = array('id' => $grouptoolid, 'course' => $course);
        }
    }

    if (!$grouptools = $DB->get_records('grouptool', $cond)) {
        return true;
    }

    if ($grouptools) {
        foreach ($grouptools as $grouptool) {
            $cm = get_coursemodule_from_instance('grouptool', $grouptool->id);

            // Start with creating the event.
            $event = new stdClass();
            $event->modulename  = 'grouptool';
            $event->courseid = $grouptool->course;
            $event->groupid = 0;
            $event->userid  = 0;
            $event->instance  = $grouptool->id;
            $event->name = $grouptool->name;
            $event->type = CALENDAR_EVENT_TYPE_ACTION;

            if (!empty($grouptool->intro)) {
                if (!$cm) {
                    // Convert the links to pluginfile. It is a bit hacky but at this stage the files
                    // might not have been saved in the module area yet.
                    $intro = $grouptool->intro;
                    if ($draftid = file_get_submitted_draft_itemid('introeditor')) {
                        $intro = file_rewrite_urls_to_pluginfile($intro, $draftid);
                    }

                    // We need to remove the links to files as the calendar is not ready
                    // to support module events with file areas.
                    $intro = strip_pluginfile_content($intro);
                    $event->description = array('text' => $intro,
                                                'format' => $grouptool->introformat);
                } else {
                    $event->description = format_module_intro('grouptool', $grouptool, $cm->id);
                }
            }

            if ($grouptool->timedue) {
                $event->eventtype = GROUPTOOL_EVENT_TYPE_DUE;
                $event->name = $grouptool->name;

                $event->timestart = $grouptool->timedue;
                $event->timesort = $grouptool->timedue;
                $select = "modulename = :modulename
                           AND instance = :instance
                           AND eventtype = :eventtype
                           AND groupid = 0
                           AND courseid <> 0";
                $params = array('modulename' => 'grouptool', 'instance' => $grouptool->id, 'eventtype' => $event->eventtype);
                $event->id = $DB->get_field_select('event', 'id', $select, $params);

                // Now process the event.
                if ($event->id) {
                    $calendarevent = calendar_event::load($event->id);
                    $calendarevent->update($event, false);
                } else {
                    calendar_event::create($event, false);
                }
            } else {
                $DB->delete_records('event', array('modulename' => 'grouptool', 'instance' => $grouptool->id,
                        'eventtype' => GROUPTOOL_EVENT_TYPE_DUE));
            }
        }
    }
    return true;
}

/**
 * function looks through all the queues and moves users from queue to reg if there's place
 *
 * @param stdClass|int $grouptool grouptool object or grouptoolid
 */
function grouptool_update_queues($grouptool = 0) {
    global $DB;

    // Update queues and move users from queue to reg if there's place!
    if (!is_object($grouptool)) {
        $grouptool = $DB->get_record('grouptool', array('id' => $grouptool), MUST_EXIST);
        $grouptool->instance = $grouptool->id;
    } else {
        $grouptool->instance = $grouptool->id;
    }

    if ($agrps = $DB->get_records('grouptool_agrps', array('grouptoolid' => $grouptool->instance))) {
        list($agrpsql, $params) = $DB->get_in_or_equal(array_keys($agrps));
        $groupregs = $DB->get_records_sql_menu('SELECT agrpid, COUNT(id)
                                                  FROM {grouptool_registered}
                                                 WHERE agrpid '.$agrpsql.' AND modified_by >= 0
                                              GROUP BY agrpid', $params);
        foreach ($agrps as $agrpid => $agrp) {
            $size = empty($grouptool->use_individual) || empty($agrp->grpsize) ? $grouptool->grpsize : $agrp->grpsize;
            $min = empty($grouptool->allow_multiple) ? 0 : $grouptool->choose_min;
            $max = empty($grouptool->allow_multiple) ? 1 : $grouptool->choose_max;
            // We use MAX to trick Postgres into thinking this is an full GROUP BY statement.
            $sql = "SELECT queued.id AS id, MAX(queued.agrpid) AS agrpid, MAX(queued.timestamp),
                           MAX(queued.userid) AS userid, (regs < ?) AS priority, MAX(reg.regs) AS regs
                      FROM {grouptool_queued} queued
                 LEFT JOIN (SELECT userid, COUNT(DISTINCT id) AS regs
                              FROM {grouptool_registered}
                             WHERE agrpid ".$agrpsql." AND modified_by >= 0
                          GROUP BY userid) reg ON queued.userid = reg.userid
                     WHERE queued.agrpid = ?
                  GROUP BY queued.id, priority
                  ORDER BY priority DESC, queued.timestamp ASC";

            if ($records = $DB->get_records_sql($sql, array_merge(array($min),
                                                                 $params, array($agrpid)))) {
                foreach ($records as $record) {
                    if (!empty($grouptool->use_size) && ($groupregs[$agrpid] >= $size)) {
                        // Group is full!
                        break;
                    }
                    if ($record->regs >= $max) {
                        // User got too many regs!
                        continue;
                    }
                    unset($record->id);
                    if (!$DB->record_exists('grouptool_registered', array('agrpid' => $agrpid,
                                                                          'userid' => $record->userid))) {
                        unset($record->priority);
                        unset($record->regs);
                        $record->modified_by = 0;
                        $DB->insert_record('grouptool_registered', $record);
                        if (!empty($grouptool->immediate_reg)) {
                            groups_add_member($agrp->groupid, $record->userid);
                        }
                    } else if ($mark = $DB->get_record('grouptool_registered', array('agrpid' => $agrpid,
                                                                                     'userid' => $record->userid,
                                                                                     'modified_by' => -1))) {
                        $mark->modified_by = 0;
                        $DB->update_record('grouptool_registered', $mark);
                        if (!empty($grouptool->immediate_reg)) {
                            groups_add_member($agrp->groupid, $record->userid);
                        }
                    }
                    $DB->delete_records('grouptool_queued', array('agrpid' => $agrpid,
                                                                  'userid' => $record->userid));
                    $groupregs[$agrpid]++;
                }
            }
        }
    }
}

/**
 * Removes an instance of the grouptool from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function grouptool_delete_instance($id) {
    global $DB;

    if (! $grouptool = $DB->get_record('grouptool', array('id' => $id))) {
        return false;
    }

    // Get all agrp-ids for this grouptool-instance!
    if ($DB->record_exists('grouptool_agrps', array('grouptoolid' => $id))) {
        $ids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptoolid = ?", array($id));

        /*
         * delete all entries in grouptool_agrps, grouptool_queued, grouptool_registered
         * with correct grouptoolid or agrps_id
         */
        if (is_array($ids)) {
            list($sql, $params) = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('grouptool_queued', "agrpid ".$sql, $params);
            $DB->delete_records_select('grouptool_registered', "agrpid ".$sql, $params);
            $DB->delete_records_select('grouptool_agrps', "id ".$sql, $params);
        }
    }

    $DB->delete_records('event', array('modulename' => 'grouptool', 'instance' => $grouptool->id));

    $DB->delete_records('grouptool', array('id' => $id));

    return true;
}

/**
 * Add a get_coursemodule_info function in case any grouptool type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|bool An object on information that the courses
 *                             will know about (most noticeably, an icon).
 */
function grouptool_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = array('id' => $coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, timeavailable, intro, introformat';
    if (! $grouptool = $DB->get_record('grouptool', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $grouptool->name;
    if ($coursemodule->showdescription) {
        if ($grouptool->alwaysshowdescription || (time() > $grouptool->timeavailable)) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('grouptool', $grouptool, $coursemodule->id, false);
        } else {
            unset($result->content);
        }
    }
    return $result;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function grouptool_get_extra_capabilities() {
    return array('moodle/course:managegroups');
}

/*******************************************************************************
 * Navigation API                                                              *
 *******************************************************************************/
/**
 * Extends the global navigation tree by adding grouptool nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref Object representing the nav tree node of the grouptool mod instance
 * @param stdClass $course course object
 * @param stdClass $module module object
 * @param cm_info $cm course module info object
 */
function grouptool_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    if ($course->id != $module->course) {
        // Just so PHPMD won't complain about $course being here ;) These have to be equal all the time!
        return;
    }

    $context = context_module::instance($cm->id);
    $creategrps = has_capability('mod/grouptool:create_groups', $context);
    $creategrpgs = has_capability('mod/grouptool:create_groupings', $context);
    $admingrps = has_capability('mod/grouptool:administrate_groups', $context);

    if ($creategrps || $creategrpgs || $admingrps) {
        if ($creategrps && ($admingrps || $creategrpgs)) {
            $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'administration'));
            $admin = $navref->add(get_string('administration', 'grouptool'), $url);
            $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'group_admin'));
            $admin->add(get_string('group_administration', 'grouptool'), $url);
            $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'group_creation'));
            $admin->add(get_string('group_creation', 'grouptool'), $url);
        } else if ($creategrps) {
            $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'group_creation'));
            $navref->add(get_string('group_creation', 'grouptool'), $url);
        } else if ($creategrpgs || $admingrps) {
            $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'group_admin'));
            $navref->add(get_string('group_administration', 'grouptool'), $url);
        }
    }
    if (has_capability('mod/grouptool:grade', $context)
            || has_capability('mod/grouptool:grade_own_group', $context)) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'grading'));
        $navref->add(get_string('grading', 'grouptool'), $url);
    }

    $gt = $module;
    $regopen = ($gt->allow_reg && (($gt->timedue == 0) || (time() < $gt->timedue))
            && ($gt->timeavailable < time()));

    if (has_capability('mod/grouptool:register_students', $context)
            || ($regopen && has_capability('mod/grouptool:register', $context))) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'selfregistration'));
        $navref->add(get_string('selfregistration', 'grouptool'), $url);
    }

    if (has_capability('mod/grouptool:register_students', $context)) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'import'));
        $navref->add(get_string('import', 'grouptool'), $url);
    }
    if (has_capability('mod/grouptool:view_regs_course_view', $context)
            && has_capability('mod/grouptool:view_regs_group_view', $context)) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'overview'));
        $userstab = $navref->add(get_string('users_tab', 'grouptool'), $url);
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'overview'));
        $userstab->add(get_string('overview_tab', 'grouptool'), $url);
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'userlist'));
        $userstab->add(get_string('userlist_tab', 'grouptool'), $url);
    } else if (has_capability('mod/grouptool:view_regs_group_view', $context)) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'overview'));
        $navref->add(get_string('users_tab', 'grouptool'), $url);
    } else if (has_capability('mod/grouptool:view_regs_course_view', $context)) {
        $url = new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id, 'tab' => 'userlist'));
        $navref->add(get_string('users_tab', 'grouptool'), $url);
    }

    $navref->nodetype = navigation_node::NODETYPE_BRANCH;
}

/**
 * displays if submission was early enough or late...
 *
 * @param int $timesubmitted
 * @param int $timedue
 * @return array string color class, string html-fragment
 */
function grouptool_display_lateness($timesubmitted = null, $timedue = null) {
    if ($timesubmitted == null) {
        $timesubmitted = time();
    }
    $time = $timedue - $timesubmitted;
    if (empty($timedue)) {
        $colorclass = 'early';
        $timeremaining = ' ('.html_writer::tag('span', format_time($time),
                                               array('class' => 'early')).')';
    } else if ($time >= 7 * 24 * 60 * 60) { // More than 7 days?
        $colorclass = 'early';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class' => 'early')).')';
    } else if ($time >= 24 * 60 * 60) { // More than 1 day (less than 7 days)?
        $colorclass = 'soon';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class' => 'soon')).')';
    } else if ($time >= 0) { // In future but less than 1 day?
        $colorclass = 'today';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class' => 'today')).')';
    } else {
        $colorclass = 'late';
        $timeremaining = ' ('.html_writer::tag('span', get_string('late', 'grouptool',
                                               format_time($time)), array('class' => 'late')).')';
    }
    return array($colorclass, $timeremaining);
}

/**
 * prepare text for mymoodle-Page to be displayed
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param stdClass[] $courses
 * @param string[][] $htmlarray
 */
function grouptool_print_overview($courses, &$htmlarray) {
    global $CFG;

    debugging('The function grouptool_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    require_once($CFG->dirroot.'/mod/grouptool/locallib.php');

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return;
    }

    if (!$grouptools = get_all_instances_in_courses('grouptool', $courses)) {
        return;
    }

    foreach ($grouptools as $grouptool) {

        $context = context_module::instance($grouptool->coursemodule, MUST_EXIST);

        $strgrouptool = get_string('grouptool', 'grouptool');
        $strduedate = get_string('duedate', 'grouptool');
        $strduedateno = get_string('duedateno', 'grouptool');

        $str = "";
        if (has_capability('mod/grouptool:register', $context)
            || has_capability('mod/grouptool:view_regs_group_view', $context)
            || has_capability('mod/grouptool:view_regs_course_view', $context)) {
            $attrib = array('title' => $strgrouptool, 'href' => $CFG->wwwroot.
                                                                '/mod/grouptool/view.php?id='.
                                                                $grouptool->coursemodule);
            if (!$grouptool->visible
                || (($grouptool->timedue != 0) && ($grouptool->timedue <= time()))) {
                $attrib['class'] = 'dimmed';
            }
            list($cc, ) = grouptool_display_lateness(time(), $grouptool->timedue);
            $str .= html_writer::tag('div', $strgrouptool.': '.
                    html_writer::tag('a', $grouptool->name, $attrib),
                    array('class' => 'name'));
            $attr = array('class' => 'info');
            if ($grouptool->timeavailable > time()) {
                $ta = $grouptool->timeavailable;
                $str .= html_writer::tag('div', get_string('availabledate', 'grouptool').': '.
                                                html_writer::tag('span', userdate($ta)), $attr);
            }
            if ($grouptool->timedue) {
                $tagargs = array('class' => (($cc == 'late') ? ' late' : ''));
                $datesnippet = html_writer::tag('span', userdate($grouptool->timedue), $tagargs);
                $str .= html_writer::tag('div', $strduedate.': '. $datesnippet, $attr);
            } else {
                $str .= html_writer::tag('div', $strduedateno, $attr);
            }
        }
        $details = grouptool_get_user_reg_details($grouptool, $context);

        if (has_capability('mod/grouptool:view_regs_group_view', $context)
            || has_capability('mod/grouptool:view_regs_course_view', $context)
            || has_capability('mod/grouptool:register', $context)) {
            $str = html_writer::tag('div', $str.$details, array('class' => 'grouptool overview'));
            if (empty($htmlarray[$grouptool->course]['grouptool'])) {
                $htmlarray[$grouptool->course]['grouptool'] = $str;
            } else {
                $htmlarray[$grouptool->course]['grouptool'] .= $str;
            }
        }
    }
}

/**
 * Get a nice overview over user's registration details!
 *
 * @param stdClass $grouptool Grouptool DB record with additional coursemodule property set!
 * @param context $context Context instance
 * @return string HTML snippet with user's registration details
 */
function grouptool_get_user_reg_details($grouptool, $context) {
    global $USER;

    $details = '';
    if (has_capability('mod/grouptool:register', $context)
        || has_capability('mod/grouptool:view_regs_course_view', $context)
        || has_capability('mod/grouptool:view_regs_group_view', $context)) {
        // It's similar to the student mymoodle output!
        $instance = new mod_grouptool($grouptool->coursemodule, $grouptool);
        $userstats = $instance->get_registration_stats($USER->id);
    } else {
        return '';
    }

    list($colorclass, ) = grouptool_display_lateness(time(), $grouptool->timedue);

    if (has_capability('mod/grouptool:register', $context)) {
        if ($grouptool->allow_reg) {
            if (count($userstats->registered)) {
                $tempstr = "";
                foreach ($userstats->registered as $registration) {
                    if ($tempstr != "") {
                        $tempstr .= '; ';
                    }
                    $tempstr .= html_writer::tag('span', $registration->grpname);
                }
                if (($grouptool->allow_multiple &&
                        (count($userstats->registered) < $grouptool->choose_min))
                        || (!$grouptool->allow_multiple && !count($userstats->registered))) {
                    if ($grouptool->allow_multiple) {
                        $missing = ($grouptool->choose_min - count($userstats->registered));
                        $stringlabel = ($missing > 1) ? 'registrations_missing' : 'registration_missing';
                    } else {
                        $missing = 1;
                        $stringlabel = 'registration_missing';
                    }
                    $details .= html_writer::tag('div',
                            html_writer::tag('div',
                                    get_string($stringlabel, 'grouptool', $missing),
                                    array('class' => $colorclass)).' '.
                            get_string('registrations', 'grouptool').': '.$tempstr,
                            array('class' => 'registered'));
                } else {
                    $details .= html_writer::tag('div',
                            get_string('registrations', 'grouptool').': '.$tempstr,
                            array('class' => 'registered'));
                }
            } else {
                if ($grouptool->allow_multiple) {
                    $missing = ($grouptool->choose_min - count($userstats->registered));
                    $stringlabel = ($missing > 1) ? 'registrations_missing' : 'registration_missing';
                } else {
                    $missing = 1;
                    $stringlabel = 'registration_missing';
                }
                $details .= html_writer::tag('div',
                        html_writer::tag('div',
                                get_string($stringlabel, 'grouptool', $missing),
                                array('class' => $colorclass)).
                        get_string('registrations', 'grouptool').': '.
                        get_string('not_registered', 'grouptool'),
                        array('class' => 'registered'));
            }
            if (count($userstats->queued)) {
                $tempstr = "";
                foreach ($userstats->queued as $queue) {
                    list($colorclass, ) = grouptool_display_lateness($queue->timestamp,
                                                                          $grouptool->timedue);
                    if ($tempstr != "") {
                        $tempstr .= ", ";
                    }
                    $tempstr .= html_writer::tag('span', $queue->grpname.' ('.$queue->rank.')',
                                                  array('class' => $colorclass));
                }
                $details .= html_writer::tag('div', get_string('queues', 'grouptool').': '.
                        $tempstr, array('class' => 'queued'));
            }
        }
    }

    if ((has_capability('mod/grouptool:view_regs_group_view', $context) || has_capability('mod/grouptool:view_regs_course_view',
                                                                                          $context))
            && $grouptool->allow_reg) {
        $details .= html_writer::tag('div', get_string('global_userstats', 'grouptool', $userstats), array('class' => 'userstats'));

    }

    return html_writer::tag('div', $details, array('class' => 'details'));
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified grouptool(s)
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function grouptool_reset_userdata($data) {
    global $CFG, $DB;

    if (!$DB->count_records('grouptool', array('course' => $data->courseid))) {
        return array(); // No grouptools present!
    }

    $componentstr = get_string('modulenameplural', 'grouptool');
    $status = array();

    $grouptoolids = $DB->get_fieldset_select('grouptool', 'id', 'course = ?',
                                              array($data->courseid));

    $agrps = $DB->get_records_list('grouptool_agrps', 'grouptoolid', $grouptoolids);

    if (!empty($data->reset_grouptool_transparent_unreg)) {
        require_once($CFG->dirroot.'/group/lib.php');
        $regdata = $DB->get_records_list('grouptool_registered', 'agrpid', array_keys($agrps));
        foreach ($regdata as $registration) {
            groups_remove_member($agrps[$registration->agrpid]->groupid, $registration->userid);
        }
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_transparent_unreg', 'grouptool'),
                          'error'        => false);
    }

    if (!empty($data->reset_grouptool_queues) || !empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_queued', 'agrpid', array_keys($agrps));
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_queues', 'grouptool'),
                          'error'        => false);
    }

    if (!empty($data->reset_grouptool_registrations) || !empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_registered', 'agrpid', array_keys($agrps));
        $status[] = array('component' => $componentstr,
                          'item'      => get_string('reset_registrations', 'grouptool'),
                          'error'     => false);
    }

    if (!empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_agrps', 'grouptoolid', $grouptoolids);
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_agrps', 'grouptool'),
                          'error'        => false);
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the grouptool.
 * @param MoodleQuickForm $mform form passed by reference
 */
function grouptool_reset_course_form_definition(MoodleQuickForm &$mform) {
    $mform->addElement('header', 'grouptoolheader', get_string('modulenameplural', 'grouptool'));
    $mform->addElement('advcheckbox', 'reset_grouptool_agrps',
                       get_string('reset_agrps', 'grouptool'));
    $mform->addHelpButton('reset_grouptool_agrps', 'reset_agrps', 'grouptool');
    $mform->addElement('advcheckbox', 'reset_grouptool_registrations',
                       get_string('reset_registrations', 'grouptool'));
    $mform->addHelpButton('reset_grouptool_registrations', 'reset_registrations', 'grouptool');
    $mform->disabledIf('reset_grouptool_registrations', 'reset_grouptool_agrps', 'checked');
    $mform->addElement('advcheckbox', 'reset_grouptool_queues',
                       get_string('reset_queues', 'grouptool'));
    $mform->addHelpButton('reset_grouptool_queues', 'reset_queues', 'grouptool');
    $mform->disabledIf('reset_grouptool_queues', 'reset_grouptool_agrps', 'checked');
    $mform->addElement('advcheckbox', 'reset_grouptool_transparent_unreg',
                       get_string('reset_transparent_unreg', 'grouptool'));
    $mform->addHelpButton('reset_grouptool_transparent_unreg', 'reset_transparent_unreg',
                          'grouptool');
}

/**
 * Course reset form defaults.
 */
function grouptool_reset_course_form_defaults() {
    return array('reset_grouptool_registrations'     => 1,
                 'reset_grouptool_queues'            => 1,
                 'reset_grouptool_agrps'             => 0,
                 'reset_grouptool_transparent_unreg' => 0);
}

/**
 * Copy Assign Grades from one user to another user (in assign_grade table)
 *
 * @param int $id Assignment ID
 * @param int $fromid User ID from whom will be copied
 * @param int $toid User ID to whom will be copied
 */
function grouptool_copy_assign_grades($id, $fromid, $toid) {
    global $DB, $CFG;

    $source = $DB->get_records('assign_grades', array('assignment' => $id, 'userid' => $fromid), 'id DESC', '*', 0, 1);
    if (!is_array($toid)) {
        $toid = array($toid);
    }
    $source = reset($source);
    $user = $DB->get_record('user', array('id' => $source->userid));
    $grader = $DB->get_record('user', array('id' => $source->grader));
    // Get corresponding feedback!
    $feedbackcomment = $DB->get_record('assignfeedback_comments', array('assignment' => $id,
                                                                        'grade'      => $source->id));
    $feedbackfile = $DB->get_record('assignfeedback_file', array('assignment' => $id,
                                                                 'grade'      => $source->id));
    foreach ($toid as $curid) {
        $record = clone $source;
        $record->userid = $curid;
        unset($record->id);
        if ($record->id = $DB->get_field('assign_grades', 'id', array('assignment'    => $id,
                                                                      'userid'        => $curid,
                                                                      'attemptnumber' => $source->attemptnumber))) {
            $DB->update_record('assign_grades', $record);
            if ($feedbackcomment) {
                $newfeedbackcomment = clone $feedbackcomment;
                unset($newfeedbackcomment->id);
                $newfeedbackcomment->grade = $record->id;
                $newfeedbackcomment->assignment = $id;
                $details = array('student'  => fullname($user),
                                 'teacher'  => fullname($grader),
                                 'date'     => userdate($source->timemodified,
                                                        get_string('strftimedatetimeshort')),
                                 'feedback' => $newfeedbackcomment->commenttext);
                $newfeedbackcomment->commenttext = format_text(get_string('copied_grade_feedback',
                                                                          'grouptool',
                                                                          $details),
                                                               $newfeedbackcomment->commentformat);
                if ($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', array('assignment' => $id,
                                                                                                    'grade'      => $record->id))) {
                    $DB->update_record('assignfeedback_comments', $newfeedbackcomment);
                } else {
                    $DB->insert_record('assignfeedback_comments', $newfeedbackcomment);
                }
            }
            if ($feedbackfile) {
                $newfeedbackfile = clone $feedbackfile;
                unset($newfeedbackfile->id);
                $newfeedbackfile->grade = $record->id;
                $newfeedbackfile->assignment = $id;
                if ($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', array('assignment' => $id,
                                                                                             'grade'      => $record->id))) {
                    $DB->update_record('assignfeedback_file', $newfeedbackfile);
                } else {
                    $DB->insert_record('assignfeedback_file', $newfeedbackfile);
                }
            }
        } else {
            $gradeid = $DB->insert_record('assign_grades', $record);
            if ($feedbackcomment) {
                $newfeedbackcomment = clone $feedbackcomment;
                unset($newfeedbackcomment->id);
                $newfeedbackcomment->grade = $gradeid;
                $newfeedbackcomment->assignment = $id;
                $details = array('student'  => fullname($user),
                                 'teacher'  => fullname($grader),
                                 'date'     => userdate($source->timemodified,
                                                        get_string('strftimedatetimeshort')),
                                 'feedback' => $newfeedbackcomment->commenttext);
                $newfeedbackcomment->commenttext = format_text(get_string('copied_grade_feedback',
                                                                          'grouptool',
                                                                          $details),
                                                               $newfeedbackcomment->commentformat);
                if ($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', array('assignment' => $id,
                                                                                                    'grade'      => $gradeid))) {
                    $DB->update_record('assignfeedback_comments', $newfeedbackcomment);
                } else {
                    $DB->insert_record('assignfeedback_comments', $newfeedbackcomment);
                }
            }
            if ($feedbackfile) {
                $newfeedbackfile = clone $feedbackfile;
                unset($newfeedbackfile->id);
                $newfeedbackfile->grade = $gradeid;
                $newfeedbackfile->assignment = $id;
                if ($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', array('assignment' => $id,
                                                                                             'grade'      => $gradeid))) {
                    $DB->update_record('assignfeedback_file', $newfeedbackfile);
                } else {
                    $DB->insert_record('assignfeedback_file', $newfeedbackfile);
                }
            }
        }

        // User must have an assign_submission record, or the grade wont be displayed properly!
        if (!$DB->record_exists('assign_submission', array('assignment' => $id, 'userid' => $curid))) {
            require_once($CFG->dirroot.'/mod/assign/locallib.php');
            $rec = new stdClass();
            $rec->assignment = $id;
            $rec->userid = $curid;
            $rec->timecreated = time();
            $rec->timemodified = $rec->timecreated;
            $rec->groupid = 0;
            $rec->attemptnumber = 0;
            $rec->latest = 1;
            $rec->status = ASSIGN_SUBMISSION_STATUS_NEW;
            $DB->insert_record('assign_submission', $rec);
        }
    }
}

/*
 ******************** CALENDAR API AND SIMILAR FUNCTIONS FOR GROUPTOOLS ***********************
 */

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle. For example,
 * the ASSIGN_EVENT_TYPE_GRADINGDUE event will not be shown to students on their calendar, and
 * ASSIGN_EVENT_TYPE_DUE events will not be shown to teachers.
 *
 * @param calendar_event $event
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 */
function mod_grouptool_core_calendar_is_event_visible(calendar_event $event) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['grouptool'][$event->instance];
    $context = context_module::instance($cm->id);

    $grouptool = new mod_grouptool($cm->id, null, $cm, null);

    $managesregs = has_capability('mod/grouptool:register_students', $context) || has_capability('mod/grouptool:move_students',
                                                                                                 $context);

    if ($event->eventtype == GROUPTOOL_EVENT_TYPE_DUE) {
        return ((has_capability('mod/grouptool:register', $context) && $grouptool->is_registration_open())
                || ($managesregs && ($grouptool->get_missing_registrations() >= 1 || $grouptool->is_registration_open())));
    }

    return false;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_grouptool_core_calendar_provide_event_action(calendar_event $event, \core_calendar\action_factory $factory) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['grouptool'][$event->instance];
    $context = context_module::instance($cm->id);

    $grouptool = new mod_grouptool($cm->id, null, $cm, null);

    $managesregs = has_capability('mod/grouptool:register_students', $context) || has_capability('mod/grouptool:move_students',
                                                                                                 $context);
    $isopen = $grouptool->is_registration_open();

    $url = new \moodle_url('/mod/grouptool/view.php', [
        'id' => $cm->id
    ]);

    $actionable = false;
    // Item count can't be 0 for the event to be displayed, but now we use it to count the real items!
    $itemcount = -1;
    $label = '';

    if (!$managesregs && has_capability('mod/grouptool:register', $context)) {
        $userstats = $grouptool->get_registration_stats($USER->id);
        list($allowmultiple, $choosemin, ) = $grouptool->get_reg_settings();
        if ($allowmultiple) {
            $itemcount = ($choosemin - count($userstats->registered));
            $label = get_string(($itemcount > 1) ? 'register' : 'register', 'grouptool');
        } else {
            $itemcount = !empty($userstats->registered) ? 0 : 1;
            $label = get_string('register', 'grouptool');
        }
        if ($itemcount <= 0) {
            $label = get_string('view_registrations', 'grouptool');
            $itemcount = -1;
        }
        // Clickable if registration is open and registrations are missing or enough registrations are made!
        $actionable = ($isopen && ($itemcount > 0)) || ($itemcount <= 0);
    } else if ($managesregs) {
        $missing = $grouptool->get_missing_registrations();
        $itemcount = ($missing > 0) ? $missing : 0;
        if ($missing > 1) {
            $label = get_string('myoverview_registrations_missing', 'grouptool');
        } else if ($missing == 1) {
            $label = get_string('myoverview_registrations_missing', 'grouptool');
        } else {
            $label = get_string('view');
            $itemcount = -1;
        }
        $url = new moodle_url($url, array('tab' => 'overview'));
        $actionable = true;
    }

    return $factory->create_instance($label, $url, $itemcount, $actionable);
}

/**
 * Callback function that determines whether an action event should be showing its item count
 * based on the event type and the item count.
 *
 * @param calendar_event $event The calendar event.
 * @param int $itemcount The item count associated with the action event.
 * @return bool
 */
function mod_grouptool_core_calendar_event_action_shows_item_count(calendar_event $event, $itemcount = 0) {
    // List of event types where the action event's item count should be shown.
    $showitemcountfor = [
        GROUPTOOL_EVENT_TYPE_DUE
    ];
    // For mod_grouptool, item count should be shown if the event type is 'due' and there is one or more items.
    return in_array($event->eventtype, $showitemcountfor) && $itemcount > 0;
}

/**
 * Map icons for font-awesome themes.
 *
 * @return string[] Mapping array with font awesome classes indexed by image names
 */
function mod_grouptool_get_fontawesome_icon_map() {
    return [
        'mod_grouptool:active' => 'fa-circle text-success',
        'mod_grouptool:inactive' => 'fa-circle'
    ];
}
