<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_grouptool\local;

use cm_info;
use coding_exception;
use context_module;
use dml_exception;
use mod_grouptool\domain\grouptool_data_object;
use moodle_exception;
use stdClass;

/**
 * Represents a grouptool activity instance with its related course module,
 * course, context and grouptool settings.
 *
 * @package   mod_grouptool
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouptool_instance {
    /** @var cm_info|stdClass The course module record. */
    protected cm_info|stdClass $cm;

    /** @var stdClass The course record. */
    protected stdClass $course;

    /** @var grouptool_data_object The grouptool database record. */
    protected grouptool_data_object $grouptool;

    /** @var context_module The module context. */
    protected context_module $context;

    /** @var grouptool_utils Utility helper for grouptool functionality. */
    protected grouptool_utils $grouptoolutils;
    /**
     * filter all groups
     */
    public const int FILTER_ALL = 0;
    /**
     * filter active groups
     */
    public const int FILTER_ACTIVE = 1;
    /**
     * filter inactive groups
     */
    public const int FILTER_INACTIVE = 2;

    /**
     * NAME_TAGS - the tags available for grouptool's group naming schemes
     */
    public const array NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

    /**
     * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
     */
    public const int HIDE_GROUPMEMBERS = GROUPTOOL_HIDE_GROUPMEMBERS;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
     */
    public const int SHOW_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
     */
    public const int SHOW_OWN_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_OWN_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
     */
    public const int SHOW_OWN_GROUPMEMBERS_AFTER_REG = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
    /**
     * SHOW_GROUPMEMBERS - show groupmembers no matter what...
     */
    public const int SHOW_GROUPMEMBERS = GROUPTOOL_SHOW_GROUPMEMBERS;

    /**
     * Constructor for the grouptool instance.
     *
     * Loads the course module, module context, course record and grouptool record.
     *
     * @param int $cmid The current course module id.
     * @param grouptool_data_object|null $grouptool Optional grouptool data object.
     * @param cm_info|stdClass|null $cm Optional course module object.
     * @param stdClass|null $course Optional course record.
     * @param context_module|null $context Optional module context.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function __construct(
        int $cmid,
        ?grouptool_data_object $grouptool = null,
        cm_info|stdClass|null $cm = null,
        ?stdClass $course = null,
        ?context_module $context = null
    ) {
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

        if (!empty($grouptool)) {
            $this->grouptool = $grouptool;
        } else if (
            !$this->grouptool = new grouptool_data_object($DB->get_record('grouptool', [
                'id' => $this->cm->instance,
            ]))
        ) {
            throw new moodle_exception('invalidid', 'grouptool');
        }
        $this->grouptool->course = $this->course->id;
    }

    /**
     * Return the grouptools name
     *
     * @return string the name
     */
    public function get_name(): string {
        return $this->grouptool->name;
    }

    /**
     * Return Grouptool's settings
     *
     * @return grouptool_data_object Grouptool's DB record
     */
    public function get_settings(): grouptool_data_object {
        return $this->grouptool;
    }

    /**
     * Return Grouptool's multiple registrations settings
     *
     * @return array [allow_multiple, choose_min, choose_max]
     */
    public function get_reg_settings(): array {
        return [$this->grouptool->allowmultiple, $this->grouptool->choosemin, $this->grouptool->choosemax];
    }

    /**
     * Return the course record
     *
     * @return stdClass The course record
     */
    public function get_course(): stdClass {
        return $this->course;
    }

    /**
     * Return the course module record
     *
     * @return cm_info|stdClass The course module record
     */
    public function get_cm(): cm_info|stdClass {
        return $this->cm;
    }

    /**
     * Return the module context
     *
     * @return context_module The module context
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Return the grouptool data object
     *
     * @return grouptool_data_object The grouptool data object
     */
    public function get_grouptool(): grouptool_data_object {
        return $this->grouptool;
    }
}
