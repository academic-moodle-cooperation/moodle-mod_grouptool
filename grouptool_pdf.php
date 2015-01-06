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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * mod_form.php
 * This class extends the moodle pdf class with a custom header and some helperfunctions for
 * proper data-output.
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../lib/pdflib.php');

define('NORMLINEHEIGHT', 12);

class grouptool_pdf extends pdf {
    /** @var string[] $header1 defines what's in the upper row of page-header **/
    protected $header1 = null;

    /** @var string[] $header2 defines what's in the lower row of page-header **/
    protected $header2 = null;

    /**
     * @var string[] $header numerical array of strings, used for storage of column-headers
     * each index corresponds to the index in {@link $data}, {@link $width} and {@link $align}
     */
    protected $header = array();

    /**
     * @var float[] $width numerical array of floats, used for storage of column-header-widths
     * each index corresponds to the index in {@link $data}, {@link $width} and {@link $align}
     * If index in $width is set to null the corresponding column-width gets calculated
     * automatically (the same calculated width is used for each of those columns)
     */
    protected $width = array();

    /**
     * @var char[] $align numerical array of chars, used for storage of column-header-alignment
     * each index corresponds to the index in {@link $data}, {@link $width} and {@link $align}
     * use 'C' for center, 'L' for left and 'R' for right
     */
    protected $align = array();

    /**
     * set_overview_header_data() helper method to set the strings for page header for Overview PDF
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of grouptool instance
     * @param timestamp $timeavailable time since the checkmark is available
     * @param timemstamp $timedue time due to which students can submit
     * @param string $viewname the checkmark-modulename to view
     */
    public function set_overview_header_data($coursename='coursename', $grouptoolname='grouptoolname', $timeavailable=0, $timedue=0,
                                             $viewname='viewname') {
        $this->header1 = array();
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ?
                            get_string('availabledateno', 'grouptool') :
                            userdate($timeavailable);
        $this->header1[4] = get_string('groupoverview', 'grouptool');

        $this->header2 = array();
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'grouptool').":";
        $this->header2[3] = empty($timedue) ?
                            get_string('duedateno', 'grouptool') :
                            userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * set_userlist_header_data() helper method to set the strings for page header for UserList PDF
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of the grouptoolinstance
     * @param timestamp $timeavailable time since the checkmark is available
     * @param timemstamp $timedue time due to which students can submit
     * @param string $viewname the checkmark-modulename to view
     */
    public function set_userlist_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                             $viewname ) {
        $this->header1 = array();
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ?
                            get_string('availabledateno', 'grouptool') :
                            userdate($timeavailable);
        $this->header1[4] = get_string('userlist', 'grouptool');

        $this->header2 = array();
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'grouptool').":";
        $this->header2[3] = empty($timedue) ?
                            get_string('duedateno', 'grouptool') :
                            userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * set_header_data() helper method to set the right texts for page header
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of the grouptoolinstance
     * @param timestamp $timeavailable time since the checkmark is available
     * @param timemstamp $timedue time due to which students can submit
     * @param string $viewname the grouptool-modulename to view
     */
    public function set_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                    $viewname) {
        $this->header1 = array();
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ?
                            get_string('availabledateno', 'grouptool') :
                            userdate($timeavailable);
        $this->header1[4] = get_string('overview', 'grouptool');

        $this->header2 = array();
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'checkmark').":";
        $this->header1[3] = empty($timedue) ?
                            get_string('duedateno', 'grouptool') :
                            userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * Header() helper method to actually print the page header in the PDF
     */
    public function Header() {
        // Set font.
        $this->SetFont('', '');

        $pagewidth = $this->getPageWidth();
        $scale = $pagewidth / 200;
        $oldfontsize = $this->getFontSize();
        $this->setFontSize('10');

        // First row.
        $border = 0;
        $height = 7;
        $this->SetFont('', 'B');
        $this->MultiCell(15 * $scale, $height, $this->header1[0],
                         $border, 'L', 0, 0, null, null, true, 1, false, false, $height, 'M', true);
        $this->SetFont('', '');
        $this->MultiCell(41 * $scale, $height, $this->header1[1],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        // Spacer!
        $this->MultiCell(15 * $scale, $height, "",
                         $border, 'C', 0, 0, null, null, true, 1, false, false, $height, 'M', true);
        $this->SetFont('', 'B');
        $this->MultiCell(26 * $scale, $height, $this->header1[2],
                         $border, 'L', 0, 0, null, null, true, 1, false, false, $height, 'M', true);
        $this->SetFont('', '');
        $this->MultiCell(46 * $scale, $height, $this->header1[3],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        // Spacer!
        $this->MultiCell(15 * $scale, $height, "", $border, 'C', 0, 0, null, null, true, 1,
                         false, false, $height, 'M', true);
        $this->SetFont('', 'B');
        $this->MultiCell(0, $height, $this->header1[4],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->Ln();

        // Second row.

        $this->SetFont('', 'B');
        $this->MultiCell(15 * $scale, $height, $this->header2[0],
                         $border, 'L', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->SetFont('', '');
        $this->MultiCell(41 * $scale, $height, $this->header2[1],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        // Spacer!
        $this->MultiCell(15 * $scale, $height, "",
                         $border, 'C', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->SetFont('', 'B');
        $this->MultiCell(26 * $scale, $height, $this->header2[2],
                         $border, 'L', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->SetFont('', '');
        $this->MultiCell(46 * $scale, $height, $this->header2[3],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        // Spacer!
        $this->MultiCell(15 * $scale, $height, "",
                         $border, 'C', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->SetFont('', '');
        $this->MultiCell(/*31*$scale*/0, $height, $this->header2[4],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->Ln();
        $this->SetFontSize($oldfontsize);
    }

    /**
     * If showheaderfooter is selected
     * Displays the number and total number of pages in the footer
     */
    public function Footer(){
        // Set font.
        $this->SetFont('', '');

        // Position at 15 mm from bottom
        $this->SetY(-15);

        // Page number
        $this->Cell(0, 10, $this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    /**
     * add_grp_overview writes data about 1 group to pdf
     *
     * @param string $groupname
     * @param object $groupinfo some statistic data about the group
     * @param array $registration the users registered in grouptool-group
     * @param array $queue the queued users
     * @param array $moodlemembers the users registered in moodle-group
     *
     */
    public function add_grp_overview($groupname, $groupinfo, $registration=array(), $queue=array(),
                                     $moodlemembers = array()) {
        $scale = $this->getPageWidth() / 210;

        // Calculate height.
        $this->setFontSize(1.25 * NORMLINEHEIGHT);
        $bigheight = $this->getStringHeight(0, 'testtext');
        $this->setFontSize(1.0 * NORMLINEHEIGHT);
        $normalheight = $this->getStringHeight(0, 'testtext');

        $height = $bigheight + $normalheight + (count($registration) + count($queue) + 1) * $normalheight;

        // Move to next page if too high.
        $this->checkPageBreak($height);

        // Color and font restoration!
        $this->setDrawColor(0);
        $this->SetFillColor(0xe8, 0xe8, 0xe8);
        $this->SetTextColor(0);
        $this->SetFont('');

        // Insert groupname!
        $this->setFontSize(1.25 * NORMLINEHEIGHT);
        $this->MultiCell(0, $bigheight, $groupname, 0, 'L', false, 1, null, null, true, 1, true,
                         false, $bigheight, 'M', true);
        $this->ln();

        // Insert groupinfo!
        $this->setFontSize(1.0 * NORMLINEHEIGHT);
        $this->MultiCell(0, $normalheight, $groupinfo, 0, 'L', false, 1, null, null, true, 1,
                         true, false, $normalheight, 'M', true);
        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $this->ln();
        // Insert registrations & queue tables!
        if (count($registration)) {
            // Print table-header!
            $this->SetFont('', 'B');
            $this->Multicell(0.1 * $writewidth, $normalheight, get_string('status', 'grouptool'),
                             'RB', 'C', true, 0, null, null, true, 1, true, false, $normalheight,
                             'M', true);
            $this->Multicell(0.3 * $writewidth, $normalheight, get_string('fullname'), 'LRB', 'C',
                             true, 0, null, null, true, 1, true, false, $normalheight, 'M', true);
            $this->Multicell(0.2 * $writewidth, $normalheight, get_string('idnumber'), 'LRB', 'C',
                             true, 0, null, null, true, 1, true, false, $normalheight, 'M', true);
            $this->Multicell(0.4 * $writewidth, $normalheight, get_string('email'), 'LB', 'C', true,
                             1, null, null, true, 1, true, false, $normalheight, 'M', true);
            $fill = 0;
            $this->SetFillColor(0xe8, 0xe8, 0xe8);
            $this->SetFont('', '');
            foreach ($registration as $row) {
                    $this->Multicell(0.1 * $writewidth, $normalheight, $row['status'], 'TR', 'C',
                                     $fill, 0, null, null, true, 1, false, false, $normalheight,
                                     'M', true);
                    $this->Multicell(0.3 * $writewidth, $normalheight, $row['name'], 'TLR', 'L',
                                     $fill, 0, null, null, true, 1, false, false, $normalheight,
                                     'M', true);
                    $this->Multicell(0.2 * $writewidth, $normalheight, $row['idnumber'], 'TLR', 'L',
                                     $fill, 0, null, null, true, 1, false, false, $normalheight,
                                     'M', true);
                    $this->Multicell(0.4 * $writewidth, $normalheight, $row['email'], 'TL', 'L',
                                     $fill, 1, null, null, true, 1, false, false, $normalheight,
                                     'M', true);
                    $fill ^= 1;
            }
        } else if (count($moodlemembers) == 0) {
            $this->SetFont('', 'I');
            $this->MultiCell(0, $normalheight,
                             "--".get_string('no_registrations', 'grouptool')."--", 0, 'C', false,
                             1, null, null, true, 1, true, false, $normalheight, 'M', true);
            $this->SetFont('', '');
        }

        if (count($moodlemembers) >= 1) {
            if (count($registration) == 0) {
                $this->SetFont('', 'B');
                $this->Multicell(0.1 * $writewidth, $normalheight, get_string('status', 'grouptool'),
                                 'RB', 'C', true, 0, null, null, true, 1, true, false,
                                 $normalheight, 'M', true);
                $this->Multicell(0.3 * $writewidth, $normalheight, get_string('fullname'), 'LRB',
                                 'C', true, 0, null, null, true, 1, true, false, $normalheight,
                                 'M', true);
                $this->Multicell(0.2 * $writewidth, $normalheight, get_string('idnumber'), 'LRB',
                                 'C', true, 0, null, null, true, 1, true, false, $normalheight,
                                 'M', true);
                $this->Multicell(0.4 * $writewidth, $normalheight, get_string('email'), 'LB', 'C',
                                 true, 1, null, null, true, 1, true, false, $normalheight, 'M',
                                 true);
                $fill = 0;
                $this->SetFillColor(0xe8, 0xe8, 0xe8);
                $this->SetFont('', '');
            }
            foreach ($moodlemembers as $row) {
                $this->Multicell(0.1 * $writewidth, $normalheight, '?', 'TR', 'C', $fill, 0,
                                 null, null, true, 1, false, false, $normalheight, 'M', true);
                $this->Multicell(0.3 * $writewidth, $normalheight, $row['name'], 'TLR', 'L', $fill,
                                 0, null, null, true, 1, false, false, $normalheight, 'M', true);
                $this->Multicell(0.2 * $writewidth, $normalheight, $row['idnumber'], 'TLR', 'L',
                                 $fill, 0, null, null, true, 1, false, false, $normalheight, 'M',
                                 true);
                $this->Multicell(0.4 * $writewidth, $normalheight, $row['email'], 'TL', 'L', $fill,
                                 1, null, null, true, 1, false, false, $normalheight, 'M', true);
                $fill ^= 1;
            }
        }

        if (count($queue)) {
            $fill = !isset($fill) ? 0 : $fill;
            $this->SetFillColor(0xe8, 0xe8, 0xe8);
            $this->SetFont('', '');
            foreach ($queue as $row) {
                if ($fill) {
                    $this->SetFillColor(0xff, 0xff, 0x99);
                } else {
                    $this->SetFillColor(0xff, 0xcc, 0x99);
                }
                $this->Multicell(0.1 * $writewidth, $normalheight, $row['rank'], 'TR', 'C', true, 0,
                                 null, null, true, 1, false, false, $normalheight, 'M', true);
                $this->Multicell(0.3 * $writewidth, $normalheight, $row['name'], 'TLR', 'L', true, 0,
                                 null, null, true, 1, false, false, $normalheight, 'M', true);
                $this->Multicell(0.2 * $writewidth, $normalheight, $row['idnumber'], 'TLR', 'L',
                                 true, 0, null, null, true, 1, false, false, $normalheight, 'M',
                                 true);
                $this->Multicell(0.4 * $writewidth, $normalheight, $row['email'], 'TL', 'L', true, 1,
                                 null, null, true, 1, false, false, $normalheight, 'M', true);
                $fill ^= 1;
            }
        } else {
            $this->SetFont('', 'I');
            $this->MultiCell(0, $normalheight, "--".get_string('nobody_queued', 'grouptool')."--",
                             0, 'C', false, 1, null, null, true, 1, true, false, $normalheight,
                             'M', true);
            $this->SetFont('', '');
        }
    }

    /**
     * add_userdata helper method to write the data about 1 user in a row (also for table-header)
     *
     * @param string $name of user
     * @param string|integer $idnumber of user
     * @param string $email user's email
     * @param array $registrations
     * @param array $queues
     * @param bool $header if it's a header-row or not
     * @param bool $getheightonly return only the height of the row
     * @global $SESSION
     * @return void|int height of written row
     *
     */
    public function add_userdata($name, $idnumber, $email, $registrations, $queues, $header=false,
                                 $getheightonly=false) {
        global $SESSION;
        $scale = $this->getPageWidth() / 210;

        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];

        $this->setFontSize(1.0 * NORMLINEHEIGHT);

        // Get row-height!
        if (!$getheightonly) {
            $height = $this->add_userdata($name, $idnumber, $email, $registrations, $queues,
                                          $header, true);
            // Move to next page if too high!
            $this->checkPageBreak($height);

        } else {
            // Store current object!
            $this->startTransaction();

            // Store starting values!
            $starty = $this->GetY();

            $startpage = $this->getPage();
            $height = 0;
        }

        if ($header) {
            $borderf = "R";
            $border = "LR";
            $borderl = "L";
            $fill = 1;
            $this->SetFont('', 'B');
            $this->SetFillColor(0xe8, 0xe8, 0xe8);
        } else {
            $borderf = "TR";
            $border = "TLR";
            $borderl = "TL";
            $fill = 0;
            $this->SetFont('', '');
        }

        if (isset($SESSION->mod_grouptool->userlist->collapsed)) {
            $collapsed = $SESSION->mod_grouptool->userlist->collapsed;
        } else {
            $collapsed = array();
        }

        $basicwidths = array('fullname' => 0.225,
                             'idnumber' => 0.15,
                             'email' => 0.225,
                             'registrations' => 0.225,
                             'queues' => 0.175);
        $totalwidth = array_sum($basicwidths);
        $colapsedwidth = $totalwidth;
        foreach ($collapsed as $column) {
            $colapsedwidth -= $basicwidths[$column];
        }
        $widths = array();
        foreach ($basicwidths as $column => $width) {
            $widths[$column] = $basicwidths[$column] * ($totalwidth / $colapsedwidth) * $writewidth;
        }
        // Set the last column to stretch over the rest of the page!
        end($widths);
        $widths[key($widths)] = 0;

        if (!in_array('fullname', $collapsed)) {
            $this->Multicell($widths['fullname'], $height, $name, $borderf, 'L', $fill, 0, null,
                             null, true, 1, false, false, $height, 'M', true);
            if ($getheightonly) {
                $height = max(array($height, $this->getLastH()));
            }
        }
        if (!in_array('idnumber', $collapsed)) {
            $this->Multicell($widths['idnumber'], $height, $idnumber, $border, 'L', $fill, 0, null,
                             null, true, 1, false, false, $height, 'M', true);
            if ($getheightonly) {
                $height = max(array($height, $this->getLastH()));
            }
        }
        if (!in_array('email', $collapsed)) {
            $this->Multicell($widths['email'], $height, $email, $border, 'L', $fill, 0, null, null,
                             true, 1, false, false, $height, 'M', true);
            if ($getheightonly) {
                $height = max(array($height, $this->getLastH()));
            }
        }
        if (!in_array('registrations', $collapsed)) {
            if (!empty($registrations) && is_array($registrations)) {
                $registrationsstring = (count($registrations) > 1) ?
                                       implode("\n", $registrations) : $registrations[0];
                if ($getheightonly) {
                    $this->Multicell($widths['registrations'], $height, $registrationsstring,
                                     $border, 'L', $fill, 0, null, null, true, 1, false, false,
                                     $height, 'M', false);
                    $height = count($registrations) * max(array($height, $this->getLastH()));
                } else {
                    $this->Multicell($widths['registrations'], $height, $registrationsstring,
                                     $border, 'L', $fill, 0, null, null, true, 1, false, false,
                                     $height, 'M', true);
                }
            } else if ($header) {
                $this->SetFont('', 'B');
                $this->Multicell($widths['registrations'], $height, $registrations, $border, 'L',
                                $fill, 0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max(array($height, $this->getLastH()));
                }
                $this->SetFont('', '');
            } else {
                $this->SetFont('', 'I');
                $this->Multicell($widths['registrations'], $height,
                                 get_string('no_registrations', 'grouptool'), $border, 'L', $fill,
                                 0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max(array($height, $this->getLastH()));
                }
                $this->SetFont('', '');
            }
        }
        if (!in_array('queues', $collapsed)) {
            if (!empty($queues) && is_array($queues)) {
                $queuesstrings = array();
                foreach ($queues as $key => $queue) {
                    $queuesstrings[] = '('.$queue['rank'].') '.$queue['name'];
                }
                if ($getheightonly) {
                    $this->Multicell(0/*.2*$writewidth*/, $height, implode("\n", $queuesstrings),
                            $borderl, 'L', $fill, 0, null, null, true, 1, false, false, $height,
                            'M', false);
                    $height = count($queues) * max(array($height, $this->getLastH()));
                } else {
                    $this->Multicell(0/*.2*$writewidth*/, $height, implode("\n", $queuesstrings),
                            $borderl, 'L', $fill, 0, null, null, true, 1, false, false, $height,
                            'M', true);
                }
            } else if ($header) {
                $this->SetFont('', 'B');
                $this->Multicell(0/*.2*$writewidth*/, $height, $queues, $borderl, 'L', $fill,
                        0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max(array($height, $this->getLastH()));
                }
                $this->SetFont('', '');
            } else {
                $this->SetFont('', 'I');
                $this->Multicell(0/*.2*$writewidth*/, $height,
                                 get_string('nowhere_queued', 'grouptool'), $borderl, 'L', $fill,
                                 0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max(array($height, $this->getLastH()));
                }
                $this->SetFont('', '');
            }
        }
        $this->ln($height);
        if ($getheightonly) {
            // Restore previous object!
            $this->rollbackTransaction(true);
            return $height;
        }
    }

}
