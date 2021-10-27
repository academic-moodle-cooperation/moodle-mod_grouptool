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
 * Extension of activity_custom_completion for enabling activity completion on grouptool registration change
 *
 * @package   mod_grouptool
 * @author    Daniel Binder, based on the work of Simey Lameze
 * @copyright 2021 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_grouptool\completion;

use core_completion\activity_custom_completion;

/**
 * Extension of activity_custom_completion for enabling activity completion on grouptool submit
 *
 * @package   mod_grouptool
 * @author    Daniel Binder, based on the work of Simey Lameze
 * @copyright 2021 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $CFG, $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $cm = $this->cm;

        require_once($CFG->dirroot . '/mod/grouptool/locallib.php');
                // Get grouptool details
        $grouptool = $DB->get_record('grouptool', array('id' => $cm->instance), '*', MUST_EXIST);

        // If completion option is enabled, evaluate it and return true/false
        if($grouptool->completionregister) {
            $status = $grouptool->completionregister <= $DB->get_field_sql("
             SELECT COUNT(DISTINCT a.id)
             FROM {grouptool_registered} r
                 INNER JOIN {grouptool_agrps} a ON a.id=r.agrpid
             WHERE
                 r.userid=:userid AND a.grouptoolid=:grouptoolid",
                            array('userid'=>$userid,'grouptoolid'=>$grouptool->id));
            return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        } else {
            // Completion option is not enabled so just return $type
            return COMPLETION_INCOMPLETE;
        }
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionregister'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
    $completionregistrations = $this->cm->customdata['customcompletionrules']['completionregister'] ?? 0;
        return [
            'completionregister' => get_string('completiondetail:register', 'grouptool', $completionregistrations)
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionregister',
        ];
    }
}
