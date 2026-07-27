<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
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

namespace mod_grouptool\domain;

use stdClass;

/**
 * Class grouptool_data_object
 *
 * Represents a grouptool activity instance including its course reference,
 * registration settings, group size settings, queue settings, and completion settings.
 *
 * @package     mod_grouptool
 * @author      Anne Kreppenhofer
 * @copyright   2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grouptool_data_object extends stdClass {
    /** @var int The database id of the grouptool instance. */
    public int $id;

    /** @var int The course id this grouptool belongs to. */
    public int $course;

    /** @var string The name of the grouptool instance. */
    public string $name;

    /** @var string The intro text of the grouptool instance. */
    public string $intro = '';

    /** @var int The format of the intro text. */
    public string|int $introformat = FORMAT_HTML;

    /** @var int Whether the description should always be shown. */
    public int $alwaysshowdescription = 0;

    /** @var int The timestamp when the grouptool was created. */
    public int $timecreated = 0;

    /** @var int The timestamp when the grouptool was last modified. */
    public int $timemodified = 0;

    /** @var int The due date timestamp for registrations. */
    public int $timedue = 0;

    /** @var int The timestamp from which the grouptool is available. */
    public int $timeavailable = 0;

    /** @var int Defines when group members should be shown. */
    public int $showmembers = 0;

    /** @var int Whether registrations are allowed. */
    public int $allowreg = 0;

    /** @var int Whether registrations are applied immediately. */
    public int $immediatereg = 0;

    /** @var int Whether users may unregister themselves. */
    public int $allowunreg = 0;

    /** @var int The default group size. */
    public int $grpsize = 0;

    /** @var int Whether group size limits are used. */
    public int $usesize = 0;

    /** @var int Whether queues are used. */
    public int $usequeue = 0;

    /** @var int The maximum number of queues a user may join. */
    public int $usersqueueslimit = 0;

    /** @var int The maximum queue size per group. */
    public int $groupsqueueslimit = 0;

    /** @var int Whether multiple registrations are allowed. */
    public int $allowmultiple = 0;

    /** @var int The minimum number of groups a user has to choose. */
    public int $choosemin = 0;

    /** @var int The maximum number of groups a user may choose. */
    public int $choosemax = 0;

    /** @var int Behaviour when a member is added to a group. */
    public int $ifmemberadded = 0;

    /** @var int Behaviour when a member is removed from a group. */
    public int $ifmemberremoved = 0;

    /** @var int Behaviour when a group is deleted. */
    public int $ifgroupdeleted = 0;

    /** @var int Whether completion requires registration. */
    public int $completionregister = 0;

    /**
     * Constructor for grouptool_info.
     *
     * Initializes the grouptool object with the provided database values.
     *
     * @param stdClass|array $values The input values to initialize the object.
     */
    public function __construct(stdClass|array $values) {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
                continue;
            }
            if ($key == 'use_size') {
                $this->usesize = $value;
            } else if ($key == 'show_members') {
                $this->showmembers = $value;
            } else if ($key == 'allow_reg') {
                $this->allowreg = $value;
            } else if ($key == 'immediate_reg') {
                $this->immediatereg = $value;
            } else if ($key == 'allow_unreg') {
                $this->allowunreg = $value;
            } else if ($key == 'use_queue') {
                $this->usequeue = $value;
            } else if ($key == 'users_queues_limit') {
                $this->usersqueueslimit = $value;
            } else if ($key == 'groups_queues_limit') {
                $this->groupsqueueslimit = $value;
            } else if ($key == 'allow_multiple') {
                $this->allowmultiple = $value;
            } else if ($key == 'choose_min') {
                $this->choosemin = $value;
            } else if ($key == 'choose_max') {
                $this->choosemax = $value;
            }
        }
    }
}
