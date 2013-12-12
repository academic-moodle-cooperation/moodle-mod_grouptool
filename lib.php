<?php
// This file is made for Moodle - http://moodle.org/
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
 * Library of interface functions and constants for module grouptool
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the grouptool specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package       mod_grouptool
 * @author        Philipp Hager (e0803285@gmail.com)
 * @copyright     2012 onwards TSC TU Vienna
 * @since         Moodle 2.2.1+ (Build: 20120127)
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    switch($feature) {
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_GROUPS:            return true;
        case FEATURE_GROUPINGS:         return true;
        case FEATURE_GROUPMEMBERSONLY:  return true;
        case FEATURE_BACKUP_MOODLE2:    return true;
        case FEATURE_IDNUMBER:          return false;
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
        case FEATURE_MOD_ARCHETYPE:     return MOD_ARCHETYPE_OTHER;

        default:                        return false;
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
 * @param object $grouptool An object from the form in mod_form.php
 * @param mod_grouptool_mod_form $mform
 * @return int The id of the newly inserted grouptool record
 */
function grouptool_add_instance(stdClass $grouptool, mod_grouptool_mod_form $mform = null) {
    global $DB, $CFG;

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
    if (!isset($grouptool->allow_multiple)) {
        $grouptool->allow_multiple = 0;
    }
    
    $grouptool->grpsize = clean_param($grouptool->grpsize, PARAM_INT);
    $grouptool->choose_min = clean_param($grouptool->choose_min, PARAM_INT);
    $grouptool->choose_max = clean_param($grouptool->choose_max, PARAM_INT);

    $return = $DB->insert_record('grouptool', $grouptool);
    add_to_log($grouptool->course,
               'grouptool', 'add',
               "view.php?g=".$return,
               '');

    require_once($CFG->dirroot.'/calendar/lib.php');
    $event = new stdClass;
    if ($grouptool->allow_reg) {
        $event->name = get_string('registration_period_start', 'grouptool').' '.$grouptool->name;
    } else {
        $event->name = $grouptool->name.' '.get_string('availabledate', 'grouptool');
    }
    $event->description  = format_module_intro('grouptool', $grouptool, $grouptool->coursemodule);
    $event->courseid     = $grouptool->course;
    $event->groupid      = 0;
    $event->userid       = 0;
    $event->modulename   = 'grouptool';
    $event->instance     = $return;
    // For activity module's events, this can be used to set the alternative text of the event icon.
    // Set it to 'pluginname' unless you have a better string.
    $event->eventtype    = 'availablefrom';
    if ($grouptool->timeavailable == 0) {
        $event->timestart = $grouptool->timecreated;
    } else {
        $event->timestart    = $grouptool->timeavailable;
    }
    $event->visible      = instance_is_visible('grouptool', $grouptool);
    $event->timeduration = 0;
    $dueevent = clone($event);
    calendar_event::create($event);

    if ($grouptool->timedue != 0) {
        unset($dueevent->id);
        if ($grouptool->allow_reg) {
            $dueevent->name = get_string('registration_period_end', 'grouptool').' '.$grouptool->name;
        } else {
            $dueevent->name = $grouptool->name.' '.get_string('duedate', 'grouptool');
        }
        $dueevent->timestart = $grouptool->timedue;
        $dueevent->eventtype = 'deadline';
        calendar_event::create($dueevent);
    }

    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($grouptool->course));
    foreach ($coursegroups as $groupid) {
        if(!$DB->record_exists('grouptool_agrps', array('grouptool_id' => $return,
                                                        'group_id'     => $groupid))) {
            $record = new stdClass();
            $record->grouptool_id = $return;
            $record->group_id = $groupid;
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
 * @param object $grouptool An object from the form in mod_form.php
 * @param mod_grouptool_mod_form $mform
 * @return boolean Success/Fail
 */
function grouptool_update_instance(stdClass $grouptool, mod_grouptool_mod_form $mform = null) {
    global $DB, $CFG;

    $grouptool->timemodified = time();
    $grouptool->id = $grouptool->instance;
    $cmid = $grouptool->coursemodule;

    if (!isset($grouptool->use_size)) {
        $grouptool->use_size = 0;
    }
    if (!isset($grouptool->use_individual)) {
        $grouptool->use_individual = 0;
    }
    if (!isset($grouptool->use_queue)) {
        $grouptool->use_queue = 0;
    }
    if (!isset($grouptool->allow_multiple)) {
        $grouptool->allow_multiple = 0;
    }

    $grouptool->grpsize = clean_param($grouptool->grpsize, PARAM_INT);
    $grouptool->choose_min = clean_param($grouptool->choose_min, PARAM_INT);
    $grouptool->choose_max = clean_param($grouptool->choose_max, PARAM_INT);

    add_to_log($grouptool->course,
            'grouptool', 'update',
            "view.php?id=".$grouptool->id,
            '');

    // Register students if immediate registration has been turned on!
    if ($grouptool->immediate_reg) {
        require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
        $instance = new grouptool($grouptool->coursemodule, $grouptool);
        $instance->push_registrations();
    }

    require_once($CFG->dirroot.'/calendar/lib.php');
    $event = new stdClass();
    if ($grouptool->allow_reg) {
        $event->name = get_string('registration_period_start', 'grouptool').' '.$grouptool->name;
    } else {
        $event->name = $grouptool->name.' '.get_string('availabledate', 'grouptool');
    }
    $event->description  = format_module_intro('grouptool', $grouptool, $grouptool->coursemodule);
    if (!empty($grouptool->timeavailable)) {
        $event->timestart = $grouptool->timeavailable;
    } else {
        $grouptool->timecreated = $DB->get_field('grouptool', 'timecreated',
                                                 array('id'=>$grouptool->id));
        $event->timestart = $grouptool->timecreated;
    }
    $event->visible      = instance_is_visible('grouptool', $grouptool);
    $event->timeduration = 0;

    if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'grouptool',
                                   'instance'=>$grouptool->id, 'eventtype'=>'availablefrom'))) {
        $calendarevent = calendar_event::load($event->id);
        $calendarevent->update($event, false);
    } else {
        $event->courseid     = $grouptool->course;
        $event->groupid      = 0;
        $event->userid       = 0;
        $event->modulename   = 'grouptool';
        $event->instance     = $grouptool->id;
        /*
         *  For activity module's events, this can be used to set the alternative text of the
         *  event icon. Set it to 'pluginname' unless you have a better string.
         */
        $event->eventtype    = 'availablefrom';

        calendar_event::create($event);
    }

    if (($grouptool->timedue != 0)) {
        unset($event->id);
        unset($calendarevent);
        if ($grouptool->allow_reg) {
            $event->name = get_string('registration_period_end', 'grouptool').' '.$grouptool->name;
        } else {
            $event->name = $grouptool->name.' '.get_string('duedate', 'grouptool');
        }
        $event->timestart = $grouptool->timedue;
        $event->eventtype    = 'deadline';
        /*
         *  For activity module's events, this can be used to set the alternative text of the
         *  event icon. Set it to 'pluginname' unless you have a better string.
         */
        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'grouptool',
                                        'instance'=>$grouptool->id, 'eventtype'=>'due'))) {

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            unset($event->id);
            $event->courseid = $grouptool->course;
            //we've got some permission issues with calendar_event::create() so we work around that
            $calev = new calendar_event($event);
            $calev->update($event, false);
        }

    } else if ($event->id = $DB->get_field('event', 'id', array('modulename' => 'grouptool',
                                                                'instance'   => $grouptool->id,
                                                                'eventtype'  => 'due'))) {
        $calendarevent = calendar_event::load($event->id);
        $calendarevent->delete(true);
    }
    
    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($grouptool->course));
    foreach ($coursegroups as $groupid) {
        if(!$DB->record_exists('grouptool_agrps', array('grouptool_id' => $grouptool->instance,
                                                        'group_id'     => $groupid))) {
            $record = new stdClass();
            $record->grouptool_id = $grouptool->instance;
            $record->group_id = $groupid;
            $record->sort_order = 9999999;
            $record->grpsize = $grouptool->grpsize;
            $record->active = 0;
            $DB->insert_record('grouptool_agrps', $record);
        }
    }

    //we have to override the functions fetching of data, because it's not updated yet
    grouptool_update_queues($grouptool);

    return $DB->update_record('grouptool', $grouptool);
}

/**
 * function looks through all the queues and moves users from queue to reg if theres place
 *
 * @param object|int grouptool or grouptoolid
 */
function grouptool_update_queues($grouptool = 0) {
    global $DB;
    
    //update queues and move users from queue to reg if there's place
    if(!is_object($grouptool)) {
        $grouptool = $DB->get_records('grouptool', array('id'=>$grouptool), MUST_EXIST);
    } else {
        $grouptool->instance = $grouptool->id;
    }

    if ($agrps = $DB->get_records('grouptool_agrps', array('grouptool_id' => $grouptool->instance))) {
        list($agrpsql, $params) = $DB->get_in_or_equal(array_keys($agrps));
        $groupregs = $DB->get_records_sql_menu('SELECT agrp_id, COUNT(id)
                                                  FROM {grouptool_registered}
                                                 WHERE agrp_id '.$agrpsql.
                                             'GROUP BY agrp_id', $params);
        foreach($agrps as $agrpid => $agrp) {
            $size = empty($grouptool->use_individual) || empty($agrp->grpsize) ?
                                                           $grouptool->grpsize :
                                                           $agrp->grpsize;
            $min = empty($grouptool->allow_multiple) ? 0 : $grouptool->choose_min;
            $max = empty($grouptool->allow_multiple) ? 1 : $grouptool->choose_max;
            $sql = "SELECT queued.id as id, queued.agrp_id as agrp_id, queued.timestamp as timestamp, queued.user_id as user_id, (regs < ?) as priority, reg.regs as regs
                                  FROM {grouptool_queued} AS queued
                             LEFT JOIN (SELECT user_id, COUNT(DISTINCT id) as regs
                                         FROM {grouptool_registered}
                                        WHERE agrp_id ".$agrpsql."
                                     GROUP BY user_id) AS reg ON queued.user_id = reg.user_id
                                 WHERE queued.agrp_id = ?
                              GROUP BY queued.id
                              ORDER BY priority DESC, 'timestamp' ASC";

            if($records = $DB->get_records_sql($sql, array_merge(array($min),
                                                                 $params, array($agrpid)))) {
                foreach ($records as $id => $record) {
                    if(!empty($grouptool->use_size) && ($groupregs[$agrpid] >= $size)) {
                        // Group is full!
                        break;
                    }
                    if($record->regs >= $max) {
                        // User got too many regs!
                        continue;
                    }
                    unset($record->id);
                    if(!$DB->record_exists('grouptool_registered', array('agrp_id' => $agrpid,
                                                                         'user_id' => $record->user_id))) {
                        unset($record->priority);
                        unset($record->regs);
                        $record->modified_by = 0;
                        $DB->insert_record('grouptool_registered', $record);
                        if (!empty($grouptool->immediate_reg)) {
                            groups_add_member($agrp->group_id, $record->user_id);
                        }
                    }
                    $DB->delete_records('grouptool_queued', array('agrp_id' => $agrpid,
                                                                  'user_id' => $record->user_id));
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
    global $DB, $CFG;

    if (! $grouptool = $DB->get_record('grouptool', array('id' => $id))) {
        return false;
    }

    add_to_log($grouptool->course,
            'course', 'delete',
            "view.php?id=".$grouptool->course,
            '');

    // Get all agrp-ids for this grouptool-instance!
    if ($DB->record_exists('grouptool_agrps', array('grouptool_id' => $id))) {
        $ids = $DB->get_fieldset_select('grouptool_agrps', 'id', "grouptool_id = ?", array($id));

        /*
         * delete all entries in grouptool_agrps, grouptool_queued, grouptool_registered
         * with correct grouptool_id or agrps_id
         */
        if (is_array($ids)) {
            list($sql, $params) = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('grouptool_queued', "agrp_id ".$sql, $params);
            $DB->delete_records_select('grouptool_registered', "agrp_id ".$sql, $params);
            $DB->delete_records_select('grouptool_agrps', "id ".$sql, $params);
        }
    }
    if (!isset($event)) {
        $event = new stdClass();
    }
    while ($event->id = $DB->get_field('event', 'id', array('modulename' => 'grouptool',
                                                            'instance'   => $grouptool->id))) {
        require_once($CFG->dirroot.'/calendar/lib.php');
        $calendarevent = calendar_event::load($event->id);
        $calendarevent->delete(true);
    }

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
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function grouptool_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = array('id'=>$coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, timeavailable, intro, introformat';
    if (! $grouptool = $DB->get_record('grouptool', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $grouptool->name;
    if ($coursemodule->showdescription) {
        if ($grouptool->alwaysshowdescription || time() > $grouptool->allowregistrationsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('grouptool', $grouptool, $coursemodule->id, false);
        }
    }
    return $result;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @todo do we need this here?
 *
 * @return stdClass|null
 */
function grouptool_user_outline($course, $user, $mod, $grouptool) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @todo do we need this here?
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $grouptool the module instance record
 * @return void, is supposed to echp directly
 */
function grouptool_user_complete($course, $user, $mod, $grouptool) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in grouptool activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function grouptool_print_recent_activity($course, $viewfullnames, $timestart) {
    $return = false;
    return $return;  // True if anything was printed, otherwise false!
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link grouptool_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function grouptool_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid,
                                           $userid=0, $groupid=0) {
    return;
}

/**
 * Prints single activity item prepared by {@link grouptool_get_recent_mod_activity()}
 *
 * @return void
 */
function grouptool_print_recent_mod_activity($activity, $courseid, $detail, $modnames,
                                             $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * we don't need any cron right now ^^
 *
 * @return boolean
 **/
function grouptool_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function grouptool_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames');
}

/*******************************************************************************
 * Navigation API                                                              *
 *******************************************************************************/
/**
 * Extends the global navigation tree by adding grouptool nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node
 *                                of the grouptool module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function grouptool_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module,
                                     cm_info $cm) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (has_capability('mod/grouptool:create_groups', $context)
            || has_capability('mod/grouptool:create_groupings', $context)
            || has_capability('mod/grouptool:register_students', $context)) {
        $navref->add(get_string('administration', 'grouptool'),
                     new moodle_url('/mod/grouptool/view.php', array('id' => $cm->id,
                                                                     'tab'=> 'administration')));
    }
    if (has_capability('mod/grouptool:grade', $context)
            || has_capability('mod/grouptool:grade_own_group', $context)) {
        $navref->add(get_string('grading', 'grouptool'),
                new moodle_url('/mod/grouptool/view.php', array('id'=>$cm->id, 'tab'=>'grading')));
    }
    // Groupmode?
    $gmok = true;
    if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        $gmok = $gmok && groups_has_membership($cm);
    }

    if (has_capability('mod/grouptool:register_students', $context)
       || ($gmok && has_capability('mod/grouptool:register', $context))) {
        $navref->add(get_string('selfregistration', 'grouptool'),
                new moodle_url('/mod/grouptool/view.php', array('id'  => $cm->id,
                                                                'tab' => 'selfregistration')));
    }
    if (has_capability('mod/grouptool:register_students', $context)) {
        $navref->add(get_string('import', 'grouptool'),
                new moodle_url('/mod/grouptool/view.php', array('id'=>$cm->id, 'tab'=>'import')));
    }
    if (has_capability('mod/grouptool:view_registrations', $context)) {
        $navref->add(get_string('overview', 'grouptool'),
                new moodle_url('/mod/grouptool/view.php', array('id'=>$cm->id, 'tab'=>'overview')));
    }
    if (has_capability('mod/grouptool:view_registrations', $context)) {
        $navref->add(get_string('userlist', 'grouptool'),
                new moodle_url('/mod/grouptool/view.php', array('id'=>$cm->id, 'tab'=>'userlist')));
    }
}

/**
 * Extends the settings navigation with the grouptool settings
 *
 * This function is called when the context for the page is a grouptool module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $grouptoolnode {@link navigation_node}
 */
function grouptool_extend_settings_navigation(settings_navigation $settingsnav,
                                              navigation_node $grouptoolnode=null) {
}

/**
 * displays if submission was early enough or late...
 *
 * @param timestamp $timesubmitted
 * @param timestamp $timedue
 * @return array string colorclass, string html-fragment
 */
function grouptool_display_lateness($timesubmitted = null, $timedue = null) {
    if ($timesubmitted == null) {
        $timesubmitted = time();
    }
    $time = $timedue - $timesubmitted;
    if (empty($timedue)) {
        $colorclass = 'early';
        $timeremaining = ' ('.html_writer::tag('span', format_time($time),
                                               array('class'=>'early')).')';
    } else if ($time >= 7*24*60*60) { // More than 7 days?
        $colorclass = 'early';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class'=>'early')).')';
    } else if ($time >= 24*60*60) { // More than 1 day (less than 7 days)?
        $colorclass = 'soon';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class'=>'soon')).')';
    } else if ($time >= 0) { // In future but less than 1 day?
        $colorclass = 'today';
        $timeremaining = ' ('.html_writer::tag('span', get_string('early', 'grouptool',
                                                                  format_time($time)),
                                               array('class'=>'today')).')';
    } else {
        $colorclass = 'late';
        $timeremaining = ' ('.html_writer::tag('span', get_string('late', 'grouptool',
                                               format_time($time)), array('class'=>'late')).')';
    }
    return array($colorclass, $timeremaining);
}

/**
 * prepares text for mymoodle-Page to be displayed
 * @param $courses
 * @param $htmlarray
 */
function grouptool_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

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
                || has_capability('mod/grouptool:view_registrations', $context)) {
            $attrib = array('title'=>$strgrouptool, 'href'=>$CFG->wwwroot.
                                                            '/mod/grouptool/view.php?id='.
                                                            $grouptool->coursemodule);
            if (!$grouptool->visible
                || (($grouptool->timedue != 0) && ($grouptool->timedue <= time()))) {
                $attrib['class']='dimmed';
            }
            list($cc, $nused) = grouptool_display_lateness(time(), $grouptool->timedue);
            $str .= html_writer::tag('div', $strgrouptool.': '.
                    html_writer::tag('a', $grouptool->name, $attrib),
                    array('class'=>'name'));
            $attr = array('class'=>'info');
            if ($grouptool->timeavailable > time()) {
                $ta = $grouptool->timeavailable;
                $str .= html_writer::tag('div', get_string('availabledate', 'grouptool').': '.
                                                html_writer::tag('span', userdate($ta)), $attr);
            }
            if ($grouptool->timedue) {
                $str .= html_writer::tag('div', $strduedate.': '.
                                                html_writer::tag('span',
                                                                 userdate($grouptool->timedue),
                                                                 array('class' => (($cc=='late')?
                                                                                     ' late':''))),
                                         $attr);
            } else {
                $str .= html_writer::tag('div', $strduedateno, $attr);
            }
        }
        $details = '';
        if (has_capability('mod/grouptool:register', $context)
                || has_capability('mod/grouptool:view_registrations', $context)) {
            $instance = new grouptool($grouptool->coursemodule, $grouptool);
            $userstats = $instance->get_registration_stats($USER->id);
        }

        if (has_capability('mod/grouptool:register', $context) && $grouptool->allow_reg) {
            if (count($userstats->registered)) {
                $temp_str = "";
                foreach ($userstats->registered as $registration) {
                    $ts = $registration->timestamp;
                    list($colorclass, $text) = grouptool_display_lateness($ts,
                                                                          $grouptool->timedue);
                    if ($temp_str != "") {
                        $temp_str .= '; ';
                    }
                    $temp_str .= html_writer::tag('span', $registration->grpname);
                }
                if (($grouptool->allow_multiple &&
                        (count($userstats->registered) < $grouptool->choose_min))
                        || (!$grouptool->allow_multiple && !count($userstats->registered))) {
                    if ($grouptool->allow_multiple) {
                        $missing = ($grouptool->choose_min-count($userstats->registered));
                        $string_label = ($missing > 1) ? 'registrations_missing'
                                                       : 'registration_missing';
                    } else {
                        $missing = 1;
                        $string_label = 'registration_missing';
                    }
                    $details .= html_writer::tag('div',
                            html_writer::tag('div',
                                    get_string($string_label, 'grouptool', $missing)).' '.
                            get_string('registrations', 'grouptool').': '.$temp_str,
                            array('class'=>'registered'));
                } else {
                    $details .= html_writer::tag('div',
                            get_string('registrations', 'grouptool').': '.$temp_str,
                            array('class'=>'registered'));
                }
            } else {
                if ($grouptool->allow_multiple) {
                    $missing = $grouptool->choose_min;
                    $string_label = ($missing > 1) ? 'registrations_missing'
                                                   : 'registration_missing';
                } else {
                    $missing = 1;
                    $string_label = 'registration_missing';
                }
                $details .= html_writer::tag('div',
                        html_writer::tag('div',
                                get_string($string_label, 'grouptool', $missing)).
                        get_string('registrations', 'grouptool').': '.
                        get_string('not_registered', 'grouptool'),
                        array('class'=>'registered'));
            }
            if (count($userstats->queued)) {
                $temp_str = "";
                foreach ($userstats->queued as $queue) {
                    list($colorclass, $text) = grouptool_display_lateness($queue->timestamp,
                                                                          $grouptool->timedue);
                    if ($temp_str != "") {
                        $temp_str .= ", ";
                    }
                    $temp_str .= html_writer::tag('span', $queue->grpname.' ('.$queue->rank.')',
                                                  array('class'=>$colorclass));
                }
                $details .= html_writer::tag('div', get_string('queues', 'grouptool').': '.
                        $temp_str, array('class'=>'queued'));
            }
        }

        if (has_capability('mod/grouptool:view_registrations', $context) && $grouptool->allow_reg) {
            $details .= html_writer::tag('div', get_string('global_userstats', 'grouptool',
                                                           $userstats),
                                         array('class'=>'userstats'));

        }

        if ((has_capability('mod/grouptool:view_registrations', $context)
                                      || has_capability('mod/grouptool:register', $context))) {
            if($grouptool->allow_reg) {
                $str .= html_writer::tag('div', $details, array('class'=>'details'));
            }
            $str = html_writer::tag('div', $str, array('class'=>'grouptool overview'));
            if (empty($htmlarray[$grouptool->course]['grouptool'])) {
                $htmlarray[$grouptool->course]['grouptool'] = $str;
            } else {
                $htmlarray[$grouptool->course]['grouptool'] .= $str;
            }
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified grouptool(s)
 * and clean up any related data.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function grouptool_reset_userdata($data) {
    global $CFG, $DB;

    if (!$DB->count_records('grouptool', array('course'=>$data->courseid))) {
        return array(); // No grouptools present!
    }

    $componentstr = get_string('modulenameplural', 'grouptool');
    $status = array();

    $grouptool_ids = $DB->get_fieldset_select('grouptool', 'id', 'course = ?',
                                              array($data->courseid));

    $agrps = $DB->get_records_list('grouptool_agrps', 'grouptool_id', $grouptool_ids);

    if (!empty($data->reset_grouptool_transparent_unreg)) {
        require_once($CFG->dirroot.'/group/lib.php');
        $reg_data = $DB->get_records_list('grouptool_registered', 'agrp_id', array_keys($agrps));
        foreach ($reg_data as $registration) {
            groups_remove_member($agrps[$registration->agrp_id]->group_id, $registration->user_id);
        }
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_transparent_unreg', 'grouptool'),
                          'error'        => false);
    }

    if (!empty($data->reset_grouptool_queues) || !empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_queued', 'agrp_id', array_keys($agrps));
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_queues', 'grouptool'),
                          'error'        => false);
    }

    if (!empty($data->reset_grouptool_registrations) || !empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_registered', 'agrp_id', array_keys($agrps));
        $status[] = array('component' => $componentstr,
                          'item'      => get_string('reset_registrations', 'grouptool'),
                          'error'     => false);
    }

    if (!empty($data->reset_grouptool_agrps)) {
        $DB->delete_records_list('grouptool_agrps', 'grouptool_id', $grouptool_ids);
        $status[] = array('component'    => $componentstr,
                          'item'         => get_string('reset_agrps', 'grouptool'),
                          'error'        => false);
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the grouptool.
 * @param $mform form passed by reference
 */
function grouptool_reset_course_form_definition(&$mform) {
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
function grouptool_reset_course_form_defaults($course) {
    return array('reset_grouptool_registrations'    => 1,
                 'reset_grouptool_queues'           => 1,
                 'reset_grouptool_agrps'            => 0,
                 'reset_grouptool_transparent_unreg'=> 0);
}

/**
 * Copy Assign Grades from one user to another user (in assign_grade table)
 */
function grouptool_copy_assign_grades($id, $fromid, $toid) {
    global $DB;
    
    $source = $DB->get_records('assign_grades', array('assignment'=>$id, 'userid'=>$fromid),
                               'id DESC', '*', 0, 1, MUST_EXIST);
    if(!is_array($toid)) {
        $toid = array($toid);
    }
    $source = reset($source);
    $user = $DB->get_record('user', array('id'=>$source->userid));
    $grader = $DB->get_record('user', array('id'=>$source->grader));
    //get corresponding feedback
    $feedbackcomment = $DB->get_record('assignfeedback_comments', array('assignment'=>$id, 'grade'=>$source->id));
    $feedbackfile = $DB->get_record('assignfeedback_file', array('assignment'=>$id, 'grade'=>$source->id));
    foreach ($toid as $cur_id) {
        $record = clone $source;
        $record->userid = $cur_id;
        unset($record->id);
        if($record->id = $DB->get_field('assign_grades', 'id', array('assignment'=>$id, 'userid'=>$cur_id,
                                                                     'attemptnumber'=>$source->attemptnumber))) {
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
                if($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', array('assignment'=>$id, 'grade'=>$record->id))) {
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
                if($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', array('assignment'=>$id, 'grade'=>$record->id))) {
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
                if($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', array('assignment'=>$id, 'grade'=>$gradeid))) {
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
                if($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', array('assignment'=>$id, 'grade'=>$gradeid))) {
                    $DB->update_record('assignfeedback_file', $newfeedbackfile);
                } else {
                    $DB->insert_record('assignfeedback_file', $newfeedbackfile);
                }
            }
        }
    }
}
