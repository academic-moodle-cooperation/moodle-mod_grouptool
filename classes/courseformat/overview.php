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

class overview extends activityoverviewbase {
    private \mod_grouptool $grouptool;

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
            'registrtaionstatus' => $this->get_extra_registration_overview(),
        ];
    }
    /**
     *
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
                ['count' => $registrations->occupied_places, 'total' => $registrations->users]
            ),
        );
    }



}