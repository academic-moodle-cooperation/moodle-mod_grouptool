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
 * Contains class mod_grouptool with most of grouptool's logic.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @author    Hannes Laimer
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_grouptool\domain\grouptool_data_object;
use mod_grouptool\exception\exceedgroupsize;
use mod_grouptool\exception\exceeduserqueuelimit;
use mod_grouptool\exception\exceeduserreglimit;
use mod_grouptool\exception\registration;
use mod_grouptool\exception\regpresent;
use mod_grouptool\local\grouptool_utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/grouptool/definitions.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/grouptool/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/pdflib.php');

/**
 * class containing most of the logic used in grouptool-module
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool {
    /** @var object */
    protected object $cm;
    /** @var object */
    protected mixed $course;
    /** @var object instance's context record */
    protected object $context;
    /** @var grouptool_data_object */
    protected grouptool_data_object $grouptool;
    /** @var grouptool_utils instance of grouptool_utils for utility functions */
    protected grouptool_utils $grouptoolutils;

    /**
     * filter all groups
     */
    public const FILTER_ALL = 0;
    /**
     * filter active groups
     */
    public const FILTER_ACTIVE = 1;
    /**
     * filter inactive groups
     */
    public const FILTER_INACTIVE = 2;

    /**
     * NAME_TAGS - the tags available for grouptool's group naming schemes
     */
    public const NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

    /**
     * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
     */
    public const HIDE_GROUPMEMBERS = GROUPTOOL_HIDE_GROUPMEMBERS;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
     */
    public const SHOW_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
     */
    public const SHOW_OWN_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_OWN_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
     */
    public const SHOW_OWN_GROUPMEMBERS_AFTER_REG = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
    /**
     * SHOW_GROUPMEMBERS - show groupmembers no matter what...
     */
    public const SHOW_GROUPMEMBERS = GROUPTOOL_SHOW_GROUPMEMBERS;

    /**
     * Constructor for the grouptool class
     *
     * If cmid is set create the cm, course, checkmark objects.
     *
     * @param int $cmid the current course module id - not set for new grouptools
     * @param grouptool_data_object $grouptool usually null, but if we have it we pass it to save db access
     * @param stdClass $cm usually null, but if we have it we pass it to save db access
     * @param stdClass $course usually null, but if we have it we pass it to save db access
     * @param context_module $context
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($cmid, $grouptool, $cm = null, $course = null, $context = null) {
        global $DB;

        if (!empty($cm)) {
            $this->cm = $cm;
        } else if (!$this->cm = get_coursemodule_from_id('grouptool', $cmid)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        if (!empty($context)) {
            $this->context = $context;
        } else {
            $this->context = context_module::instance($this->cm->id);
        }

        if (!empty($course)) {
            $this->course = $course;
        } else if (!$this->course = $DB->get_record('course', ['id' => $this->cm->course])) {
            throw new moodle_exception('invalidid', 'grouptool');
        }

        if ($grouptool) {
            $this->grouptool =  new grouptool_data_object($grouptool);
        } else if (
            !$this->grouptool = new grouptool_data_object($DB->get_record(
                'grouptool',
                ['id' => $this->cm->instance]
            ))
        ) {
            throw new moodle_exception('invalidid', 'grouptool');
        }

        $this->grouptool->course = $this->course->id;
    }
}
