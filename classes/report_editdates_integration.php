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
 * Contains report editdates integration
 *
 * @package   mod_grouptool
 * @author    Andreas Krieger
 * @copyright 2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;


require_once($CFG->dirroot.'/mod/grouptool/locallib.php');
/**
 * Class needed for report-editdates support
 *
 * @package       mod_grouptool
 * @author        Andreas Krieger
 * @copyright     2019 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouptool_report_editdates_integration
extends report_editdates_mod_date_extractor {

    /**
     * mod_grouptool_report_editdates_integration constructor.
     *
     * @param object $course the course
     */
    public function __construct($course) {
        parent::__construct($course, 'grouptool');
        parent::load_data();
    }

    /**
     * Gets settings
     *
     * @param cm_info $cm
     * @return array
     * @throws coding_exception
     */
    public function get_settings(cm_info $cm) {
        $grouptool = $this->mods[$cm->instance];

        return array(
                'timeavailable' => new report_editdates_date_setting(
                        get_string('availabledate', 'grouptool'),
                        $grouptool->timeavailable,
                        self::DATETIME, true, 5),
                'timedue' => new report_editdates_date_setting(
                        get_string('duedate', 'grouptool'),
                        $grouptool->timedue,
                        self::DATETIME, true, 5),
                );
    }

    /**
     * Validates the passed dates
     *
     * @param cm_info $cm
     * @param array $dates
     * @return array
     * @throws coding_exception
     */
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = array();
        if (!empty($dates['timedue']) && ($dates['timedue'] <= $dates['timeavailable'])) {
            $errors['timedue'] = get_string('determinismerror', 'grouptool');
        }

        return $errors;
    }

    /**
     * Saves the passed dates
     *
     * @param cm_info $cm
     * @param array $dates
     * @throws coding_exception
     * @throws dml_exception
     */
    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $COURSE, $CFG;

        $update = new stdClass();
        $update->id = $cm->instance;
        $update->timedue = $dates['timedue'];
        $update->timeavailable = $dates['timeavailable'];

        $result = $DB->update_record('grouptool', $update);

        grouptool_refresh_events(0, $cm->instance);
    }
}
