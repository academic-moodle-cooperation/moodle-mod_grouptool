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

namespace mod_grouptool\courseformat;

use cm_info;
use core\output\local\properties\text_align;
use core\output\renderer_helper;
use core\url;
use core_calendar\output\humandate;
use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use core_courseformat\output\local\overview\overviewaction;
use core_string_manager;
use mod_grouptool\dates;
use mod_grouptool\local\model\registration_manager;

/**
 * Grouptool overview integration.
 *
 * @package    mod_grouptool
 * @copyright  2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /** @var registration_manager */
    private registration_manager $registrationmanager;
    /** @var renderer_helper */
    protected readonly renderer_helper $rendererhelper;
    /** @var core_string_manager */
    protected readonly core_string_manager $stringmanager;
    /**
     * Constructor.
     *
     * @param cm_info $cm
     * @param renderer_helper $rendererhelper
     * @param core_string_manager $stringmanager
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct(
        cm_info $cm,
        renderer_helper $rendererhelper,
        core_string_manager $stringmanager,
    ) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

        parent::__construct($cm, $rendererhelper, $stringmanager);

        $this->registrationmanager = new registration_manager(
            $cm->id,
            null,
            $cm,
            $cm->get_course(),
            $cm->context,
        );
    }
    #[\Override]
    public function get_due_date_overview(): ?overviewitem {
        global $USER;

        $duedate = $this->registrationmanager->get_settings()->timedue;

        if (empty($duedate)) {
            return new overviewitem(
                name: get_string('duedate', 'grouptool'),
                value: null,
                content: '-',
            );
        }

        return new overviewitem(
            name: get_string('duedate', 'grouptool'),
            value: $duedate,
            content: userdate($duedate),
        );
    }
    #[\Override]
    public function get_actions_overview(): ?overviewitem {
        if (!has_capability('mod/grouptool:preview', $this->context)) {
            return null;
        }

        $name = get_string('view');

        $content = new overviewaction(
            url: new url('/mod/grouptool/view.php', ['id' => $this->cm->id]),
            text: $name,
        );

        return new overviewitem(
            name: get_string('actions'),
            value: $name,
            content: $content,
            textalign: text_align::CENTER,
        );
    }
    #[\Override]
    public function get_extra_overview_items(): array {
        return [
            'registrationoverview' => $this->get_extra_registration_overview(),
            'regstrationstatus' => $this->get_extra_registration_status(),
        ];
    }
    /**
     * Returns an overview item with the count of registered students for this activity
     * Checks if the user has the capability to view the registration overview and
     * then returns the count of registered students and total students.
     * @return overviewitem|null
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \required_capability_exception
     */
    private function get_extra_registration_overview(): ?overviewitem {
        global $USER;

        if (!has_capability('mod/grouptool:view_regs_group_view', $this->context)) {
            return null;
        }

        $registrations = $this->registrationmanager->get_registration_stats($USER->id);
        return new overviewitem(
            name: get_string('registeredstudents', 'grouptool'),
            value: true,
            content: get_string(
                'count_of_total',
                'core',
                ['count' => $registrations->reg_users, 'total' => $registrations->users]
            ),
        );
    }

    /**
     * Returns the registration status of the user for this activity
     * Checks if the user has the capability to register and then checks if the user is registered or not.
     * @return overviewitem|null
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \required_capability_exception
     */
    private function get_extra_registration_status(): ?overviewitem {
        global $USER;

        if (!has_capability('mod/grouptool:register', $this->context)) {
            return null;
        }

        $registrationcount = (int) $this->registrationmanager->get_user_reg_count($USER->id);

        if ($registrationcount > 0) {
            return new overviewitem(
                name: get_string('registrationstatus', 'grouptool'),
                value: true,
                content: get_string('registered', 'grouptool'),
            );
        }

        return new overviewitem(
            name: get_string('registrationstatus', 'grouptool'),
            value: false,
            content: get_string('registrationmissing', 'grouptool'),
            alertcount: 1,
            alertlabel: get_string(
                'registrationmissing',
                'grouptool',
            ),
        );
    }
}
