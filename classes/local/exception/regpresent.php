<?php
// This file is part of local_grouptool for Moodle - http://moodle.org/
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
 * Exception class,
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool\local\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception when the user is already registered/queued/marked for this group!
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regpresent extends registration {

    /**
     * Constructor
     *
     * @param string $text (optional) Text to be used
     * @param string $a (optional) Additional data used by language string
     * @throws \coding_exception
     */
    public function __construct($text = '', $a = null) {
        if ($text == '') {
            $text = get_string('already_registered', 'grouptool');
        }
        if ($a === null) {
            $a = new \stdClass;
            $a->username = '';
            $a->groupname = '';
        }

        parent::__construct($text, 'grouptool', $a);
    }
}