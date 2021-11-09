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
 * Backup tasks for mod_grouptool
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Because it exists (must)!
require_once($CFG->dirroot . '/mod/grouptool/backup/moodle2/backup_grouptool_stepslib.php');

/**
 * grouptool backup task that provides everything to perform one complete backup of the activity
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_grouptool_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity!
    }

    /**
     * Define (add) particular steps this activity can have
     *
     * @throws base_task_exception
     * @throws backup_step_exception
     */
    protected function define_my_steps() {
        // Grouptool only has one structure step!
        $this->add_step(new backup_grouptool_activity_structure_step('grouptool_structure',
                'grouptool.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     *
     * @param string $content URL to be modified
     * @return string modified URL
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of grouptools.
        $search = "/(".$base."\/mod\/grouptool\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@GROUPTOOLINDEX*$2@$', $content);
        // Link to grouptool view by moduleid.
        $search = "/(".$base."\/mod\/grouptool\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@GROUPTOOLVIEWBYID*$2@$', $content);

        return $content;
    }
}
