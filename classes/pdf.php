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
 * Extending the moodle pdf class with a custom header and some helperfunctions for proper data-output.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_grouptool;

use context_course;
use mod_grouptool\local\tests\grouptool;

defined('MOODLE_INTERNAL') || die();

require_once('../../lib/pdflib.php');

/**
 * Extended pdf class with convenience methods for outputting Grouptool pdfs
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf extends \pdf {
    /** int NORMLINEHEIGHT = 12 */
    const NORMLINEHEIGHT = 12;

    /** @var string[] $header1 defines what's in the upper row of page-header **/
    protected $header1 = null;

    /** @var string[] $header2 defines what's in the lower row of page-header **/
    protected $header2 = null;

    /**
     * @var string[] $header numerical array of strings, used for storage of column-headers
     * each index corresponds to the index in {@see $data}, {@see $width} and {@see $align}
     */
    protected $header = [];

    /**
     * @var float[] $width numerical array of floats, used for storage of column-header-widths
     * each index corresponds to the index in {@see $data}, {@see $width} and {@see $align}
     * If index in $width is set to null the corresponding column-width gets calculated
     * automatically (the same calculated width is used for each of those columns)
     */
    protected $width = [];

    /**
     * @var [] $align numerical array of chars, used for storage of column-header-alignment
     * each index corresponds to the index in {@see $data}, {@see $width} and {@see $align}
     * use 'C' for center, 'L' for left and 'R' for right
     */
    protected $align = [];

    /** @var int|null used to calculate heights of text-blocks */
    protected $normalheight = null;

    /** @var int|null used to calculate heights of text-blocks */
    protected $bigheight = null;

    /** @var array Holds all instance specific useridentityfields*/
    protected $useridentityfields = null;

    /**
     * Class constructor
     *
     * Enhances moodle's pdf class by adding calculated values for text-height. {@inheritDoc}
     *
     * @param string $orientation page orientation
     * @param string $unit User measure unit
     * @param string $format The format used for pages
     * @param bool $unicode TRUE means that the input text is unicode (default = true)
     * @param string $encoding Charset encoding (used only when converting back html entities); default is UTF-8.
     * @throws \coding_exception
     */
    public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8') {
        global $SITE, $USER;

        parent::__construct($orientation, $unit, $format, $unicode, $encoding);

        $this->useridentityfields = grouptool::get_useridentity_fields();
        $this->setFontSubsetting(false);

        // Set orientation (P/L)!
        $orientation = (optional_param('orientation', 0, PARAM_BOOL) == 0) ? 'P' : 'L';
        $this->setPageOrientation($orientation);

        // Set document information!
        $this->SetCreator(format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID)]).' | '.
                get_string('pluginname', 'grouptool'));
        $this->SetAuthor(fullname($USER));

        // Set header/footer!
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);

        $textsize = optional_param('textsize', 1, PARAM_INT);
        switch ($textsize){
            case "0":
                $this->SetFontSize(8);
                break;
            case "1":
                $this->SetFontSize(10);
                break;
            case "2":
                $this->SetFontSize(12);
                break;
        }

        // Set default monospaced font!
        $this->SetDefaultMonospacedFont(/*PDF_FONT_MONOSPACED*/'freeserif');

        // Set auto page breaks!
        $this->SetAutoPageBreak(true, /*PDF_MARGIN_BOTTOM*/10);

        // Set image scale factor
        $this->setImageScale(/*PDF_IMAGE_SCALE_RATIO*/1);

        /*
         * ---------------------------------------------------------
         */

        // Set font!
        $this->SetFont('freeserif', '');

        // Set margins!
        $this->setHeaderMargin(7);
        $this->setFooterMargin(7);
        $this->SetMargins(10, 30, 10, true); // Left Top Right.

        // Calculate height.
        $this->SetFontSize(1.25 * self::NORMLINEHEIGHT);
        $this->bigheight = $this->getStringHeight(0, 'testtext');
        $this->SetFontSize(1.0 * self::NORMLINEHEIGHT);
        $this->normalheight = $this->getStringHeight(0, 'testtext');

        $this->AddPage($orientation, 'A4', false, false);
    }

    /**
     * set_overview_header_data() helper method to set the strings for page header for Overview PDF
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of grouptool instance
     * @param int $timeavailable time since the checkmark is available
     * @param int $timedue time due to which students can submit
     * @param string $viewname the checkmark-modulename to view
     * @throws \coding_exception
     */
    public function set_overview_header_data($coursename='coursename', $grouptoolname='grouptoolname', $timeavailable=0, $timedue=0,
                                             $viewname='viewname') {
        $this->header1 = [];
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ? get_string('availabledateno', 'grouptool') : userdate($timeavailable);
        $this->header1[4] = get_string('groupoverview', 'grouptool');

        $this->header2 = [];
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'grouptool').":";
        $this->header2[3] = empty($timedue) ? get_string('duedateno', 'grouptool') : userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * set_userlist_header_data() helper method to set the strings for page header for UserList PDF
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of the grouptoolinstance
     * @param int $timeavailable time since the checkmark is available
     * @param int $timedue time due to which students can submit
     * @param string $viewname the checkmark-modulename to view
     * @throws \coding_exception
     */
    public function set_userlist_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                             $viewname ) {
        $this->header1 = [];
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ? get_string('availabledateno', 'grouptool') : userdate($timeavailable);
        $this->header1[4] = get_string('userlist', 'grouptool');

        $this->header2 = [];
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'grouptool').":";
        $this->header2[3] = empty($timedue) ? get_string('duedateno', 'grouptool') : userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * set_header_data() helper method to set the right texts for page header
     *
     * @param string $coursename the name of the course
     * @param string $grouptoolname name of the grouptoolinstance
     * @param int $timeavailable time since the checkmark is available
     * @param int $timedue time due to which students can submit
     * @param string $viewname the grouptool-modulename to view
     * @throws \coding_exception
     */
    public function set_header_data($coursename, $grouptoolname, $timeavailable, $timedue,
                                    $viewname) {
        $this->header1 = [];
        $this->header1[0] = get_string('course').":";
        $this->header1[1] = $coursename;
        $this->header1[2] = get_string('availabledate', 'grouptool').":";
        $this->header1[3] = empty($timeavailable) ? get_string('availabledateno', 'grouptool') : userdate($timeavailable);
        $this->header1[4] = get_string('overview', 'grouptool');

        $this->header2 = [];
        $this->header2[0] = get_string('modulename', 'grouptool').":";
        $this->header2[1] = $grouptoolname;
        $this->header2[2] = get_string('duedate', 'grouptool').":";
        $this->header1[3] = empty($timedue) ? get_string('duedateno', 'grouptool') : userdate($timedue);
        $this->header2[4] = $viewname;
    }

    /**
     * Header() helper method to actually print the page header in the PDF
     */
    public function header() {
        // Set font.
        $this->SetFont('', '');

        $pagewidth = $this->getPageWidth();
        $scale = $pagewidth / 200;
        $oldfontsize = $this->getFontSize();
        $this->SetFontSize('10');

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
        $this->MultiCell(0, $height, $this->header2[4],
                         $border, 'R', 0, 0, null, null, true, 1, false, false, $height, 'M', true);

        $this->Ln();
        $this->SetFontSize($oldfontsize);
    }

    /**
     * If showheaderfooter is selected
     * Displays the number and total number of pages in the footer
     */
    public function footer() {
        // Set font.
        $this->SetFont('', '');

        // Position at 15 mm from bottom.
        $this->SetY(-15);

        // Page number.
        $this->Cell(0, 10, $this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    /**
     * add_grp_overview writes data about 1 group to pdf
     *
     * @param string $groupname
     * @param string $groupinfo some statistic data about the group
     * @param \stdClass[] $registration the users registered in grouptool-group
     * @param \stdClass[] $queue the queued users
     * @param \stdClass[] $moodlemembers the users registered in moodle-group
     * @throws \coding_exception
     */
    public function add_grp_overview($groupname, $groupinfo, $registration=[], $queue=[], $moodlemembers = []) {
        $fill = 0;

        // Calculate height.
        $bigheight = $this->bigheight;
        $normalheight = $this->normalheight;

        $height = $bigheight + $normalheight + (count($registration) + count($queue) + 1) * $normalheight;

        // Move to next page if too high.
        $this->checkPageBreak($height);

        // Color and font restoration!
        $this->SetDrawColor(0);
        $this->SetFillColor(0xe8, 0xe8, 0xe8);
        $this->SetTextColor(0);
        $this->SetFont('');

        // Insert groupname!
        $this->SetFontSize(1.25 * self::NORMLINEHEIGHT);
        $this->MultiCell(0, $bigheight, $groupname, 0, 'L', false, 1, null, null, true, 1, true,
                         false, $bigheight, 'M', true);
        $this->Ln();

        // Insert groupinfo!
        $this->SetFontSize(1.0 * self::NORMLINEHEIGHT);
        $this->MultiCell(0, $normalheight, $groupinfo, 0, 'L', false, 1, null, null, true, 1,
                         true, false, $normalheight, 'M', true);
        $this->Ln();
        // Insert registrations & queue tables!
        if (count($registration)) {
            $this->add_overview_table_header();
            foreach ($registration as $row) {
                $this->add_overview_row($row, $fill);
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
                $this->add_overview_table_header();
            }
            foreach ($moodlemembers as $row) {
                $row['status'] = '?';
                $this->add_overview_row($row, $fill);
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
                $this->add_overview_row(row, $fill, 1);
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
     * @return float|int
     * @throws \coding_exception
     */
    private function calculate_identitycolumn_width($fieldwidth = 0.6, $emailscalefactor = 2) {
        $identityfields = $this->useridentityfields;

        if (empty($identityfields) || isset($identityfields['email'])) {
            $basewidth = $fieldwidth / count($identityfields);
            $emailcolumn = $basewidth * $emailscalefactor;
            // Return scaled basewidth.
            return $basewidth * $fieldwidth / ($basewidth * (count($identityfields) - 1) + $emailcolumn);
        }
        return $fieldwidth / count($identityfields);
    }

    /**
     * Writes the table header for overview tables to the PDF
     *
     * @throws \coding_exception
     */
    private function add_overview_table_header() {
        // Print table-header!
        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $normalheight = $this->normalheight;
        $identityfields = $this->useridentityfields;
        $identitycolumnwidth = self::calculate_identitycolumn_width();

        $this->SetFont('', 'B');
        $this->MultiCell(0.1 * $writewidth, $normalheight, get_string('status', 'grouptool'),
                         'RB', 'C', true, 0, null, null, true, 1, true, false, $normalheight,
                         'M', true);
        $this->MultiCell(0.3 * $writewidth, $normalheight, get_string('fullname'), 'LRB', 'C',
                         true, 0, null, null, true, 1, true, false, $normalheight, 'M', true);

        $colcount = 0;
        $identityfieldscount = count($identityfields);
        foreach ($identityfields as $key => $value) {
            $border = 'LRB';
            $ln = 0;
            if (++$colcount == $identityfieldscount) {
                $border = 'LB';
                $ln = 1;
            }
            if ($key == 'email') {
                $this->MultiCell($identitycolumnwidth * 2 * $writewidth, $normalheight, get_string('email'), $border, 'C', true,
                        $ln, null, null, true, 1, true, false, $normalheight, 'M', true);
            } else {
                $this->MultiCell($identitycolumnwidth * $writewidth, $normalheight, get_string($key), $border, 'C',
                        true, $ln, null, null, true, 1, true, false, $normalheight, 'M', true);
            }
        }

        $this->SetFillColor(0xe8, 0xe8, 0xe8);
        $this->SetFont('', '');
    }

    /**
     * Adds a single row of group overview entry data to the PDF
     *
     * @param string $status column content
     * @param string $name column content
     * @param string $idnumber column content
     * @param string $email column content
     * @param bool $fill whether or not this row's cells will contain a background color (gets toggled afterwards)!
     * @param bool $forcefill force cell background color ($fill gets toggled anyways)!
     */
    private function add_overview_row_old($status, $name, $idnumber, $email, &$fill, $forcefill = false) {
        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $normalheight = $this->normalheight;

        $this->MultiCell(0.1 * $writewidth, $normalheight, $status, 'TR', 'C', $fill || $forcefill, 0, null, null, true,
                         1, true, false, $normalheight, 'M', true);
        $this->MultiCell(0.3 * $writewidth, $normalheight, $name, 'TLR', 'L', $fill || $forcefill, 0, null, null, true,
                         1, true, false, $normalheight, 'M', true);
        $this->MultiCell(0.2 * $writewidth, $normalheight, $idnumber, 'TLR', 'L', $fill || $forcefill, 0, null, null, true,
                         1, true, false, $normalheight, 'M', true);
        $this->MultiCell(0.4 * $writewidth, $normalheight, $email, 'TL', 'L', $fill || $forcefill, 1, null, null, true,
                         1, true, false, $normalheight, 'M', true);
        $fill ^= 1;
    }

    private function add_overview_row($row, &$fill, $forcefill = false) {
        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $normalheight = $this->normalheight;
        $identityfields = $this->useridentityfields;
        $identitycolumnwidth = self::calculate_identitycolumn_width();

        $this->MultiCell(0.1 * $writewidth, $normalheight, $row['status'], 'TR', 'C', $fill || $forcefill, 0, null, null, true,
                1, true, false, $normalheight, 'M', true);
        $this->MultiCell(0.3 * $writewidth, $normalheight, $row['name'], 'TLR', 'L', $fill || $forcefill, 0, null, null, true,
                1, true, false, $normalheight, 'M', true);

        $colcount = 0;
        $identityfieldscount = count($identityfields);
        foreach ($identityfields as $key => $value) {
            $border = 'TLR';
            $ln = 0;
            if (++$colcount == $identityfieldscount) {
                $border = 'TL';
                $ln = 1;
            }
            if ($key == 'email') {
                $this->MultiCell($identitycolumnwidth * 2 * $writewidth, $normalheight, $row['email'], $border, 'C',
                        $fill || $forcefill, $ln, null, null, true, 1, true, false,
                        $normalheight, 'M', true);
            } else {
                $this->MultiCell($identitycolumnwidth * $writewidth, $normalheight, $row[$key], $border, 'C',
                        $fill || $forcefill, $ln, null, null, true, 1, true, false,
                        $normalheight, 'M', true);
            }
        }

        $this->SetFillColor(0xe8, 0xe8, 0xe8);
        $this->SetFont('', '');
        $fill ^= 1;
    }

    /**
     * add_userdata helper method to write the data about 1 user in a row (also for table-header)
     *
     * @param array $row Array containing all data for a single row
     * @param bool $header if it's a header-row or not
     * @param bool $getheightonly return only the height of the row
     * @return int height of written row
     * @throws \coding_exception
     */
    public function add_userdata($row, $header=false, $getheightonly=false) {
        global $SESSION;

        $margins = $this->getMargins();
        $writewidth = $this->getPageWidth() - $margins['left'] - $margins['right'];
        $identityfields = $this->useridentityfields;

        $this->SetFontSize(1.0 * self::NORMLINEHEIGHT);

        // Get row-height!
        if (!$getheightonly) {
            $height = $this->add_userdata($row, $header, true);
            // Move to next page if too high!
            $this->checkPageBreak($height);

        } else {
            // Store current object!
            $this->startTransaction();

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
            $collapsed = [];
        }
        // Todo: Consider caching result of this calculation as it is the same for each row in one export.
        $basicwidths = [
                'fullname' => 0.225,
                'identity' => $this->calculate_identitycolumn_width(0.375, 1.5),
                'email' => $this->calculate_identitycolumn_width(0.375, 1.5) * 1.5,
                'registrations' => 0.225,
                'queues' => 0.175
        ];
        $totalwidth = array_sum($basicwidths);
        $colapsedwidth = $totalwidth;
        foreach ($collapsed as $column) {
            if (array_key_exists($column, $basicwidths)) {
                $colapsedwidth -= $basicwidths[$column];
            }
        }
        $widths = [];
        foreach ($basicwidths as $column => $width) {
            $widths[$column] = $width * ($totalwidth / $colapsedwidth) * $writewidth;
        }
        // Set the last column to stretch over the rest of the page!
        end($widths);
        $widths[key($widths)] = 0;

        if (!in_array('fullname', $collapsed)) {
            $this->MultiCell($widths['fullname'], $height, $row['name'], $borderf, 'L', $fill, 0, null,
                             null, true, 1, false, false, $height, 'M', true);
            if ($getheightonly) {
                $height = max([$height, $this->getLastH()]);
            }
        }

        foreach ($identityfields as $key => $value) {
            $curwidth = $widths['identity'];
            if ($key == 'email') {
                $curwidth = $widths['email'];
            }
            if (!in_array($key, $collapsed)) {
                $this->MultiCell($curwidth, $height, $row[$key], $border, 'L', $fill,
                        0, null, null, true, 1, false, false, $height,
                        'M', true);
            }
        }

        if (!in_array('registrations', $collapsed)) {
            if (!empty($row['registrations']) && is_array($row['registrations'])) {
                $registrationsstring = (count($row['registrations']) > 1) ?
                        implode("\n", $row['registrations']) : $row['registrations'][0];
                if ($getheightonly) {
                    $this->MultiCell($widths['registrations'], $height, $registrationsstring,
                                     $border, 'L', $fill, 0, null, null, true, 1, false, false,
                                     $height, 'M', false);
                    $height = count($row['registrations']) * max([$height, $this->getLastH()]);
                } else {
                    $this->MultiCell($widths['registrations'], $height, $registrationsstring,
                                     $border, 'L', $fill, 0, null, null, true, 1, false, false,
                                     $height, 'M', true);
                }
            } else if ($header) {
                $this->SetFont('', 'B');
                $this->MultiCell($widths['registrations'], $height, $row['registrations'], $border, 'L',
                                $fill, 0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max([$height, $this->getLastH()]);
                }
                $this->SetFont('', '');
            } else {
                $this->SetFont('', 'I');
                $this->MultiCell($widths['registrations'], $height,
                                 get_string('no_registrations', 'grouptool'), $border, 'L', $fill,
                                 0, null, null, true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max([$height, $this->getLastH()]);
                }
                $this->SetFont('', '');
            }
        }
        if (!in_array('queues', $collapsed)) {
            if (!empty($row['queues']) && is_array($row['queues'])) {
                $queuesstrings = [];
                foreach ($row['queues'] as $queue) {
                    $queuesstrings[] = $queue['name'].' (#'.$queue['rank'].')';
                }
                if ($getheightonly) {
                    $this->MultiCell(0, $height, implode("\n", $queuesstrings), $borderl,
                                     'L', $fill, 0, null, null, true, 1, false, false, $height,
                                     'M', false);
                    $height = count($row['queues']) * max([$height, $this->getLastH()]);
                } else {
                    $this->MultiCell(0, $height, implode("\n", $queuesstrings), $borderl,
                                     'L', $fill, 0, null, null, true, 1, false, false, $height,
                                     'M', true);
                }
            } else if ($header) {
                $this->SetFont('', 'B');
                $this->MultiCell(0, $height, $row['queues'], $borderl, 'L', $fill, 0, null, null,
                                 true, 1, false, false, $height, 'M', true);
                if ($getheightonly) {
                    $height = max([$height, $this->getLastH()]);
                }
                $this->SetFont('', '');
            } else {
                $this->SetFont('', 'I');
                $this->MultiCell(0, $height, get_string('nowhere_queued', 'grouptool'), $borderl,
                                 'L', $fill, 0, null, null, true, 1, false, false, $height,
                                 'M', true);
                if ($getheightonly) {
                    $height = max([$height, $this->getLastH()]);
                }
                $this->SetFont('', '');
            }
        }
        $this->Ln($height);
        if ($getheightonly) {
            // Restore previous object!
            $this->rollbackTransaction(true);
        }
        return $height;
    }

}
