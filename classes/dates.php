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
 * Contains mod_grouptool's grading form
 *
 * @package   mod_grouptool
 * @author    Daniel Binder
 * @copyright 2021 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

use core\activity_dates;

/**
 * Class for fetching the important dates in mod_assign for a given module instance and a user.
 *
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {

    /**
     * Returns a list of important dates in mod_assign
     *
     * @return array
     */
    protected function get_dates(): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

        $course = get_course($this->cm->course);
        $grouptool = new \mod_grouptool($this->cm->id, null, $this->cm, $course);
        $grouptoolsettings = $grouptool->get_settings();

        $timeopen = $grouptoolsettings->timeavailable ?? null;
        $timedue = $grouptoolsettings->timedue ?? null;

        $dates = [];

        if ($timeopen) {
            $date = [
                'label' => get_string('availabledate', 'mod_grouptool') . ':',
                'timestamp' => (int) $timeopen,
            ];
            $dates[] = $date;
        }

        if ($timedue) {
            $date = [
                'label' => get_string('duedate', 'mod_grouptool') . ':',
                'timestamp' => (int) $timedue,
            ];
            $dates[] = $date;
        }
        return $dates;
    }
}
