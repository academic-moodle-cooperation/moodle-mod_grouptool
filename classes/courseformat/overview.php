<?php
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
/**
 * Grouptool overview integration.
 *
 * @package    mod_grouptool
 * @copyright  2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /** @var \mod_grouptool $grouptool the grouptool instance. */
    private \mod_grouptool $grouptool;

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
        protected readonly renderer_helper $rendererhelper,
        protected readonly core_string_manager $stringmanager,
    ) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/grouptool/locallib.php');

        parent::__construct($cm, $rendererhelper, $stringmanager);

        $this->grouptool = new \mod_grouptool(
            $this->cm->id,
            null,
            $this->cm,
            $this->cm->get_course()
        );
    }
    #[\Override]
     public function get_due_date_overview(): ?overviewitem {
        global $USER;

        $duedate = $this->grouptool->get_settings()->timedue;

        if (empty($duedate)) {
            return new overviewitem(
                name: get_string('duedate','grouptool'),
                value: null,
                content: '-',
            );
        }

        return new overviewitem(
            name: get_string('duedate','grouptool'),
            value: $duedate,
            content: userdate($duedate),
        );
    }
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
     * Checks if the user has the capability to view the registration overview and then returns the count of registered students and total students.
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

        $registrations = $this->grouptool->get_registration_stats($USER->id);
        # TODO Add langsring for Rgeisered students
        return new overviewitem(
            name: 'Registered students',
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

        $registration = $this->grouptool->get_user_reg_count($USER->id);
        [,$min,]=$this->grouptool->get_reg_settings();

        if ($registration >= $min && $registration > 0) {
            return new overviewitem(
                name: get_string('registration_details', 'grouptool'),
                value: true,
                content:get_string('registered', 'grouptool')
            );
        }

        return new overviewitem(
            name: get_string('registration_details', 'grouptool'),
            value: true,
            content: get_string('not_registered', 'grouptool')
        );
    }



}