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

namespace mod_grouptool\local\model;

use context_module;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use core_user\fields;
use dml_exception;
use mod_grouptool\local\grouptool_instance;
use mod_grouptool\local\grouptool_utils;
use mod_grouptool\pdf;
use MoodleExcelWorkbook;
use MoodleODSWorkbook;
use stdClass;


/**
 * Class containing the logic for exporting data from grouptool to XLSX or ODS files
 *
 * @package   mod_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2026 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_service extends grouptool_instance {
    /**
     * outputs generated txt-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_txt(int $groupid = 0, int $groupingid = 0, bool $includeinactive = false): void {
        ob_start();

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);
        $grouptoolutils = new grouptool_utils($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $lines = [];
        $groups = $groupmanager->group_overview_table($groupingid, $groupid, true, $includeinactive);
        if (count($groups) > 0) {
            $lines[] = "*** " . get_string('status', 'grouptool') . "\n";
            foreach (
                explode(
                    "</li>",
                    get_string('status_help', 'grouptool')
                ) as $legendline
            ) {
                $lines[] = "***\t" . strip_tags($legendline);
            }
            $lines[] = "";

            foreach ($groups as $group) {
                $lines[] = $group->name;
                $lines[] = "\t" . get_string('total') . ' ' . $group->total . " / " .
                    get_string('registered', 'grouptool') . ' ' . $group->registered . " / " .
                    get_string('queued', 'grouptool') . ' ' . $group->queued . " / " .
                    get_string('free', 'grouptool') . ' ' . $group->free;
                if (isset($group->mreg_data)) {
                    $mregs = count($group->mreg_data);
                } else {
                    $mregs = 0;
                }
                if ($group->registered > 0) {
                    $lines[] = "\t" . get_string('registrations', 'grouptool');
                    foreach ($group->reg_data as $reg) {
                        $lines[] = "\t\t" . $reg['status'] . "\t" . $reg['name'] .
                            $grouptoolutils->get_useridentity_values_for_txt($reg['useridentityvalues']);
                    }
                } else if ($mregs == 0) {
                    $lines[] = "\t\t--" . get_string('no_registrations', 'grouptool') . "--";
                }
                if ($mregs >= 1) {
                    foreach ($group->mreg_data as $mreg) {
                        $lines[] = "\t\t?\t" . $mreg['name'] . "\t" .
                            $grouptoolutils->get_useridentity_values_for_txt($mreg['useridentityvalues']);
                    }
                }
                if ($group->queued > 0) {
                    $lines[] = "\t" . get_string('queue', 'grouptool');
                    foreach ($group->queue_data as $queue) {
                        $lines[] = "\t\t" . $queue['rank'] . "\t" . $queue['name'] . "\t" .
                            $grouptoolutils->get_useridentity_values_for_txt($queue['useridentityvalues']);
                    }
                } else {
                    $lines[] = "\t\t--" . get_string('nobody_queued', 'grouptool') . "--";
                }
                $lines[] = "";
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid) . '_' . get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid) . '_' . get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('group') . '_' . get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.txt");
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header('Content-Disposition: attachment; filename="' . str_replace([' ', '"'], ['_', ''], $filename) .
            '"; filename*="' . rawurlencode($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }

    /**
     * outputs generated pdf-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_pdf(int $groupid = 0, int $groupingid = 0, bool $includeinactive = false): void {
        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $data = $groupmanager->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupid)) {
            $viewname = groups_get_group_name($groupid);
        } else {
            if (!empty($groupingid)) {
                $viewname = groups_get_grouping_name($groupingid);
            } else {
                $viewname = get_string('all') . ' ' . get_string('groups');
            }
        }

        $pdf = new pdf(
            'overview',
            $coursename,
            $grouptoolname,
            $timeavailable,
            $timedue,
            $viewname
        );

        if (count($data) > 0) {
            foreach ($data as $group) {
                $groupname = $group->name;
                $groupinfo = get_string('total') . ' ' . $group->total . ' / ' .
                    get_string('registered', 'grouptool') . ' ' . $group->registered . ' / ' .
                    get_string('queued', 'grouptool') . ' ' . $group->queued . ' / ' .
                    get_string('free', 'grouptool') . ' ' . $group->free;
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = $group->mreg_data ?? [];
                $pdf->add_grp_overview($groupname, $groupinfo, $regdata, $queuedata, $mregdata);
                $pdf->MultiCell(
                    0,
                    $pdf->getLastH(),
                    '',
                    'B',
                    'L',
                    false,
                    1,
                    null,
                    null,
                    true,
                    1,
                    true,
                    false,
                    $pdf->getLastH(),
                    'M',
                    true
                );
                $pdf->MultiCell(
                    0,
                    $pdf->getLastH(),
                    '',
                    'T',
                    'L',
                    false,
                    1,
                    null,
                    null,
                    true,
                    1,
                    true,
                    false,
                    $pdf->getLastH(),
                    'M',
                    true
                );
            }
            $pdf->SetFontSize(8);
            $pdf->MultiCell(
                0,
                $pdf->getLastH(),
                get_string('status', 'grouptool'),
                '',
                'L',
                false,
                1,
                null,
                null,
                true,
                1,
                true,
                false,
                $pdf->getLastH(),
                'M',
                true
            );
            foreach (explode("</li>", get_string('status_help', 'grouptool')) as $legendline) {
                $pdf->MultiCell(
                    0,
                    $pdf->getLastH(),
                    strip_tags($legendline),
                    '',
                    'L',
                    false,
                    1,
                    null,
                    null,
                    true,
                    1,
                    true,
                    false,
                    $pdf->getLastH(),
                    'M',
                    true
                );
            }
        } else {
            $pdf->MultiCell(
                0,
                $pdf->getLastH(),
                get_string('no_data_to_display', 'grouptool'),
                'B',
                'LRTB',
                false,
                1,
                null,
                null,
                true,
                1,
                true,
                false,
                $pdf->getLastH(),
                'M',
                true
            );
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid) . '_' . get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid) . '_' . get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('group') . ' ' . get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.pdf");
        $pdf->Output($filename, 'D');
        exit();
    }

    /**
     * outputs generated ods-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_ods(int $groupid = 0, int $groupingid = 0, bool $includeinactive = false): void {

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = format_string(
            $this->course->fullname,
            true,
            ['context' => context_module::instance($this->cm->id)]
        );

        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid) . '_' . get_string('overview', 'grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid) . '_' . get_string('overview', 'grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('group') . ' ' . get_string('overview', 'grouptool');
        }
        $filename = clean_filename("$filename.ods");
        $workbook = new MoodleODSWorkbook("-");

        $groups = $groupmanager->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename);
        $workbook->close();
    }

    /**
     * Fill workbook (either XLSX or ODS) with data
     *
     * @param MoodleExcelWorkbook|MoodleODSWorkbook $workbook workbook to put data into
     * @param stdClass[] $groups which groups from whom to include data
     * @param string[] $collapsed array with collapsed columns
     * @throws coding_exception
     */
    private function overview_fill_workbook(MoodleExcelWorkbook|MoodleODSWorkbook &$workbook, array $groups, array $collapsed = []): void {
        global $CFG;
        if (count($groups) > 0) {
            $columnwidth = [7, 22, 14, 17]; // Unit: mm!

            $allgroupsworksheet = false;
            if (count($groups) > 1) {
                // General information? unused at the moment!
                $allgroupsworksheet = $workbook->add_worksheet(get_string('all'));
                // The standard column widths: 7 - 22 - 14 - 17!
                $allgroupsworksheet->set_column(0, 0, $columnwidth[0]);
                $allgroupsworksheet->set_column(1, 1, $columnwidth[1]);
                $allgroupsworksheet->set_column(2, 2, $columnwidth[2]);
                $allgroupsworksheet->set_column(3, 3, $columnwidth[3]);
            }

            // Add content for all groups!
            $groupworksheets = [];

            // Prepare formats!
            $headlineprop = [
                'size' => 14,
                'bold' => 1,
                'align' => 'center',
            ];
            $headlineformat = $workbook->add_format($headlineprop);
            $groupinfoprop1 = [
                'size' => 10,
                'bold' => 1,
                'align' => 'left',
            ];
            $groupinfoprop2 = $groupinfoprop1;
            unset($groupinfoprop2['bold']);
            $groupinfoprop2['italic'] = true;
            $groupinfoprop2['align'] = 'right';
            $groupinfoformat1 = $workbook->add_format($groupinfoprop1);
            $groupinfoformat2 = $workbook->add_format($groupinfoprop2);
            $regheadprop = [
                'size' => 10,
                'align' => 'center',
                'bold' => 1,
                'bottom' => 2,
            ];
            $regentryprop = [
                'size' => 10,
                'align' => 'left',
            ];
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regheadformat = $workbook->add_format($regheadprop);
            $regheadformat->set_right(1);
            $regheadlast = $workbook->add_format($regheadprop);

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_top(1);
            $regentryformat->set_bottom(0);
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_top(1);
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('center');
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('center');

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = fields::for_name()->get_required_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = [
                0 => 26,
                'fullname' => 26,
                'firstname' => 20,
                'surname' => 20,
                'email' => 35,
                'registrations' => 47,
                'queues_rank' => 7.5,
                'queues_grp' => 47,
            ]; // Unit: mm!

            // Start row for groups general sheet!
            $j = 0;
            $columncount = 1 + count($namefields);
            if (!empty($CFG->showuseridentity)) {
                $fields = explode(',', $CFG->showuseridentity);
                $columncount += count($fields);
            } else {
                $columncount += 2;
            }
            foreach ($groups as $key => $group) {
                // Add worksheet for each group!
                $groupworksheets[$key] = $workbook->add_worksheet($group->name);

                $groupname = $group->name;
                $groupinfo = [];
                $groupinfo[] = [get_string('total'), $group->total];
                $groupinfo[] = [get_string('registered', 'grouptool'), $group->registered];
                $groupinfo[] = [get_string('queued', 'grouptool'), $group->queued];
                $groupinfo[] = [get_string('free', 'grouptool'), $group->free];
                $regdata = $group->reg_data;
                $queuedata = $group->queue_data;
                $mregdata = $group->mreg_data ?? [];
                // Groupname as headline!
                $groupworksheets[$key]->write_string(0, 0, $groupname, $headlineformat);
                $groupworksheets[$key]->merge_cells(0, 0, 0, $columncount - 1);
                if ($allgroupsworksheet !== false) {
                    $allgroupsworksheet->write_string($j, 0, $groupname, $headlineformat);
                    $allgroupsworksheet->merge_cells($j, 0, $j, $columncount - 1);
                }

                // Groupinfo on top!
                $groupworksheets[$key]->write_string(2, 0, $groupinfo[0][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(2, 0, 2, 1);
                $groupworksheets[$key]->write(2, 2, $groupinfo[0][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(3, 0, $groupinfo[1][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(3, 0, 3, 1);
                $groupworksheets[$key]->write(3, 2, $groupinfo[1][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(4, 0, $groupinfo[2][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(4, 0, 4, 1);
                $groupworksheets[$key]->write(4, 2, $groupinfo[2][1], $groupinfoformat2);

                $groupworksheets[$key]->write_string(5, 0, $groupinfo[3][0], $groupinfoformat1);
                $groupworksheets[$key]->merge_cells(5, 0, 5, 1);
                $groupworksheets[$key]->write(5, 2, $groupinfo[3][1], $groupinfoformat2);
                if ($allgroupsworksheet !== false) {
                    $allgroupsworksheet->write_string(
                        $j + 2,
                        0,
                        $groupinfo[0][0],
                        $groupinfoformat1
                    );
                    $allgroupsworksheet->merge_cells($j + 2, 0, $j + 2, 1);
                    $allgroupsworksheet->write($j + 2, 2, $groupinfo[0][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string(
                        $j + 3,
                        0,
                        $groupinfo[1][0],
                        $groupinfoformat1
                    );
                    $allgroupsworksheet->merge_cells($j + 3, 0, $j + 3, 1);
                    $allgroupsworksheet->write($j + 3, 2, $groupinfo[1][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string(
                        $j + 4,
                        0,
                        $groupinfo[2][0],
                        $groupinfoformat1
                    );
                    $allgroupsworksheet->merge_cells($j + 4, 0, $j + 4, 1);
                    $allgroupsworksheet->write($j + 4, 2, $groupinfo[2][1], $groupinfoformat2);

                    $allgroupsworksheet->write_string(
                        $j + 5,
                        0,
                        $groupinfo[3][0],
                        $groupinfoformat1
                    );
                    $allgroupsworksheet->merge_cells($j + 5, 0, $j + 5, 1);
                    $allgroupsworksheet->write($j + 5, 2, $groupinfo[3][1], $groupinfoformat2);
                }

                // Registrations and queue headline!
                // First the headline!
                $k = 0;
                $groupworksheets[$key]->write_string(
                    7,
                    $k,
                    get_string('status', 'grouptool'),
                    $regheadformat
                );
                $k++; // ...k is equal to 1!

                // First we output every namefield from used by fullname in exact the defined order!
                foreach ($namefields as $namefield) {
                    $groupworksheets[$key]->write_string(7, $k, fields::get_display_name($namefield), $regheadformat);
                    $hidden = in_array($namefield, $collapsed) ? true : false;
                    $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                    $k++;
                }
                // ...k is equal to n!
                if (!empty($CFG->showuseridentity)) {
                    $fields = explode(',', $CFG->showuseridentity);
                    $curfieldcount = 1;
                    foreach ($fields as $field) {
                        if ($curfieldcount == count($fields)) {
                            $groupworksheets[$key]->write_string(7, $k, fields::get_display_name($field), $regheadlast);
                        } else {
                            $groupworksheets[$key]->write_string(
                                7,
                                $k,
                                fields::get_display_name($field),
                                $regheadformat
                            );
                            $curfieldcount++;
                        }
                        $hidden = in_array($field, $collapsed) ? true : false;
                        $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                        $groupworksheets[$key]->set_column($k, $k, $columnwidth[$field], null, $hidden);
                        $k++; // ...k is equal to n+x!
                    }
                } else {
                    $groupworksheets[$key]->write_string(
                        7,
                        $k,
                        fields::get_display_name('idnumber'),
                        $regheadformat
                    );
                    $hidden = in_array('idnumber', $collapsed) ? true : false;
                    $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                    $k++; // ...k is equal to n+1!

                    $groupworksheets[$key]->write_string(7, $k, fields::get_display_name('email'), $regheadlast);
                    $hidden = in_array('email', $collapsed) ? true : false;
                    $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                    $groupworksheets[$key]->set_column($k, $k, $columnwidth['email'], null, $hidden);
                    $k++; // ...k is equal to n+2!
                }

                if ($allgroupsworksheet !== false) {
                    $k = 0;
                    $allgroupsworksheet->write_string(
                        $j + 7,
                        $k,
                        get_string('status', 'grouptool'),
                        $regheadformat
                    );
                    $k++;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $allgroupsworksheet->write_string(
                            $j + 7,
                            $k,
                            fields::get_display_name($namefield),
                            $regheadformat
                        );
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k is equal to n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        $curfieldcount = 1;
                        foreach ($fields as $field) {
                            if ($curfieldcount == count($fields)) {
                                $allgroupsworksheet->write_string(
                                    $j + 7,
                                    $k,
                                    fields::get_display_name($field),
                                    $regheadlast
                                );
                            } else {
                                $allgroupsworksheet->write_string(
                                    $j + 7,
                                    $k,
                                    fields::get_display_name($field),
                                    $regheadformat
                                );
                                $curfieldcount++;
                            }
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $allgroupsworksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $k++; // ...k is equal to n+x!
                        }
                    } else {
                        $allgroupsworksheet->write_string(
                            $j + 7,
                            $k,
                            fields::get_display_name('idnumber'),
                            $regheadformat
                        );
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $k++; // ...k is equal to n+1!

                        $allgroupsworksheet->write_string($j + 7, $k, fields::get_display_name('email'), $regheadlast);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $allgroupsworksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $k++; // ...k is equal to n+2!
                    }
                }
                // Now the registrations!
                $i = 0;
                if (!empty($regdata)) {
                    foreach ($regdata as $reg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(
                            8 + $i,
                            $k,
                            $reg['status'],
                            $regentryformat
                        );
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k is equal to n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $reg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k is equal to n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['idnumber'], $regentryformat);
                            $k++; // ...k is equal to n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $reg['email'], $regentrylast);
                            $k++; // ...k is equal to n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string(
                                $j + 8 + $i,
                                $k,
                                $reg['status'],
                                $regentryformat
                            );
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k is equal to n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k is equal to n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['idnumber'], $regentryformat);
                                $k++; // ...k is equal to n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $reg['email'], $regentrylast);
                                $k++; // ...k is equal to n+2!
                            }
                        }
                        $i++;
                    }
                } else if (count($mregdata) == 0) {
                    $groupworksheets[$key]->write_string(
                        8 + $i,
                        0,
                        get_string(
                            'no_registrations',
                            'grouptool'
                        ),
                        $noregentriesformat
                    );
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($allgroupsworksheet !== false) {
                        $allgroupsworksheet->write_string(
                            $j + 8 + $i,
                            0,
                            get_string(
                                'no_registrations',
                                'grouptool'
                            ),
                            $noregentriesformat
                        );
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }

                if (count($mregdata) >= 1) {
                    foreach ($mregdata as $mreg) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(
                            8 + $i,
                            $k,
                            '?',
                            $regentryformat
                        );
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k is equal to n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $mreg[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k is equal to n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['idnumber'], $regentryformat);
                            $k++; // ...k is equal to n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $mreg['email'], $regentrylast);
                            $k++; // ...k is equal to n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string(
                                $j + 8 + $i,
                                $k,
                                '?',
                                $regentryformat
                            );
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k is equal to n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k is equal to n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg['idnumber'], $regentryformat);
                                $k++; // ...k is equal to n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $mreg['email'], $regentrylast);
                                $k++; // ...k is equal to n+2!
                            }
                        }
                        $i++;
                    }
                }
                // Don't forget the queue!
                if (!empty($queuedata)) {
                    foreach ($queuedata as $queue) {
                        if ($i == 0) {
                            $regentryformat->set_top(2);
                        } else if ($i == 1) {
                            $regentryformat->set_top(1);
                        }
                        $k = 0;
                        $groupworksheets[$key]->write_string(
                            8 + $i,
                            $k,
                            $queue['rank'],
                            $regentryformat
                        );
                        $k++;
                        // First we output every namefield from used by fullname in exact the defined order!
                        foreach ($namefields as $namefield) {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$namefield], $regentryformat);
                            $k++;
                        }
                        // ...k is equal to n!
                        if (!empty($CFG->showuseridentity)) {
                            $fields = explode(',', $CFG->showuseridentity);
                            $curfieldcount = 1;
                            foreach ($fields as $field) {
                                if ($curfieldcount == count($fields)) {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentrylast);
                                } else {
                                    $groupworksheets[$key]->write_string(8 + $i, $k, $queue[$field], $regentryformat);
                                    $curfieldcount++;
                                }
                                $k++; // ...k is equal to n+x!
                            }
                        } else {
                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['idnumber'], $regentryformat);
                            $k++; // ...k is equal to n+1!

                            $groupworksheets[$key]->write_string(8 + $i, $k, $queue['email'], $regentrylast);
                            $k++; // ...k is equal to n+2!
                        }

                        if ($allgroupsworksheet !== false) {
                            $k = 0;
                            $allgroupsworksheet->write_string(
                                $j + 8 + $i,
                                $k,
                                $queue['rank'],
                                $regentryformat
                            );
                            $k++;
                            // First we output every namefield from used by fullname in exact the defined order!
                            foreach ($namefields as $namefield) {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$namefield], $regentryformat);
                                $k++;
                            }
                            // ...k is equal to n!
                            if (!empty($CFG->showuseridentity)) {
                                $fields = explode(',', $CFG->showuseridentity);
                                $curfieldcount = 1;
                                foreach ($fields as $field) {
                                    if ($curfieldcount == count($fields)) {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentrylast);
                                    } else {
                                        $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue[$field], $regentryformat);
                                        $curfieldcount++;
                                    }
                                    $k++; // ...k is equal to n+x!
                                }
                            } else {
                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['idnumber'], $regentryformat);
                                $k++; // ...k is equal to n+1!

                                $allgroupsworksheet->write_string($j + 8 + $i, $k, $queue['email'], $regentrylast);
                                $k++; // ...k is equal to n+2!
                            }
                        }
                        $i++;
                    }
                } else {
                    $groupworksheets[$key]->write_string(
                        8 + $i,
                        0,
                        get_string('nobody_queued', 'grouptool'),
                        $noqueueentriesformat
                    );
                    $groupworksheets[$key]->merge_cells(8 + $i, 0, 8 + $i, 3);
                    if ($allgroupsworksheet !== false) {
                        $allgroupsworksheet->write_string(
                            $j + 8 + $i,
                            0,
                            get_string(
                                'nobody_queued',
                                'grouptool'
                            ),
                            $noqueueentriesformat
                        );
                        $allgroupsworksheet->merge_cells($j + 8 + $i, 0, $j + 8 + $i, 3);
                    }
                    $i++;
                }
                $j += 9 + $i;    // One row space between groups!
            }
        }
    }

    /**
     * outputs generated xlsx-file for overview (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param bool $includeinactive optional include inactive groups too!
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_overview_xlsx(int $groupid = 0, int $groupingid = 0, bool $includeinactive = false): void {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $groupmanager = new group_manager($this->cm->id, $this->grouptool, $this->cm, $this->course, $this->context);

        $coursename = format_string(
            $this->course->fullname,
            true,
            ['context' => context_module::instance($this->cm->id)]
        );
        $grouptoolname = $this->grouptool->name;

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid) . '_' .
                get_string('overview', 'grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid) . '_' .
                get_string('overview', 'grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                get_string('group') . ' ' . get_string('overview', 'grouptool'));
        }
        $filename = clean_filename("$filename.xlsx");
        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $groups = $groupmanager->group_overview_table($groupingid, $groupid, true, $includeinactive);

        $this->overview_fill_workbook($workbook, $groups);

        $workbook->send($filename);
        $workbook->close();
    }
}
