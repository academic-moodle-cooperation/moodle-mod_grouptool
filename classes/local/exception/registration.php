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
 * General exception during the registration/queue/marking process!
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration extends \moodle_exception {

    /**
     * Constructor
     *
     * @param string $description (optional) exception description string identifier
     * @param string $module (optional) location of the string definition
     * @param mixed $a (optional) additional data used in the language string
     */
    public function __construct($description = 'general_registration_exception', $module='grouptool', $a = null) {

        parent::__construct($description, $module, '', $a);
    }
}