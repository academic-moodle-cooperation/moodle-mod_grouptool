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
 * Various constant definitions used by mod_grouptool, separated to not having to include big library files.
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();

/**
 * GROUPTOOL_N_M_GROUPS - group creation mode where N groups with a groupsize of M members are created
 */
define('GROUPTOOL_N_M_GROUPS', 4);

/**
 * GROUPTOOL_FROMTO_GROUPS - group creation mode where just groups with a starting and
 * ending number are created - no user allocation
 */
define('GROUPTOOL_FROMTO_GROUPS', 3);

/**
 * GROUPTOOL_GROUPS_AMOUNT - group creation mode where amount of groups is defined
 */
define('GROUPTOOL_GROUPS_AMOUNT', 1);

/**
 * GROUPTOOL_MEMBERS_AMOUNT - group creation mode where amount of groupmembers is defined
 */
define('GROUPTOOL_MEMBERS_AMOUNT', 2);

/**
 * GROUPTOOL_1_PERSON_GROUPS - group creation mode where a single group is created for each user
 */
define('GROUPTOOL_1_PERSON_GROUPS', 0);

/**
 * GROUPTOOL_AUTOGROUP_MIN_RATIO - means minimum member count is 70% in the smallest group
 */
define('GROUPTOOL_AUTOGROUP_MIN_RATIO', 0.7);

/**
 * GROUPTOOL_BEP - use new implementation of parsing groupnames with @ if current groups
 * number is larger than GROUPTOOL_BEP
 * new implementation is faster for large numbers
 * old style = linear - new style = estimated 15 instructions per stage --> 15 * log(x,25)
 * break even point estimated < 12 --> @30 we are on the secure side...
 */
define('GROUPTOOL_BEP', 30);

/**
 * IE_7_IS_DEAD - disable workarounds for IE7-problems?
 * still quite alive, so we need some hacks :(
 */
define('GROUPTOOL_IE7_IS_DEAD', 0);

/**
 * GROUPTOOL_FILTER_ALL - no filter at all...
 */
define('GROUPTOOL_FILTER_ALL', 0);

/**
 * GROUPTOOL_FILTER_NONCONFLICTING - Show just those groups, which have just 1 graded member
 * for this activity
 */
define('GROUPTOOL_FILTER_NONCONFLICTING', -1);

/**
 * GROUPTOOL_PDF - get PDF-File
 */
define('GROUPTOOL_PDF', 0);

/**
 * GROUPTOOL_TXT - get TXT-File
 */
define('GROUPTOOL_TXT', 1);

/**
 * GROUPTOOL_ODS - get ODS-File
 */
define('GROUPTOOL_ODS', 3);

/**
 * GROUPTOOL_XLSX - get XLSX-File
 */
define('GROUPTOOL_XLSX', 2);

/**
 * GROUPTOOL_RAW - get raw data - just for development
 */
define('GROUPTOOL_RAW', -1);

/**
 * GROUPTOOL_NL - Windows style newlines
 * otherwise we get problems with windows users and txt-files (UNIX \n, MAC \r)
 */
define('GROUPTOOL_NL', "\r\n");

/**
 * GROUPTOOL_OUTDATED - active group's registrations are not consistent with moodle-group's
 */
define('GROUPTOOL_OUTDATED', 0);

/**
 * GROUPTOOL_UPTODATE - active group's registrations are consistent with moodle-group's registrations
 */
define('GROUPTOOL_UPTODATE', 1);

/**
 * GROUPTOOL_FOLLOW - follow changes via eventhandler
 */
define('GROUPTOOL_FOLLOW', 1);

/**
 * GROUPTOOL_IGNORE - ignore changes
 */
define('GROUPTOOL_IGNORE', 0);

/**
 * GROUPTOOL_RECREATE_GROUP - recreate group just for use in grouptool
 */
define('GROUPTOOL_RECREATE_GROUP', 0);

/**
 * GROUPTOOL_DELETE_REF - delete all references in grouptool-instance
 */
define('GROUPTOOL_DELETE_REF', 1);

/**
 * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
 */
define('GROUPTOOL_HIDE_GROUPMEMBERS', 0);
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
 */
define('GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE', 2);
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
 */
define('GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE', 3);
/**
 * SHOW_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
 */
define('GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG', 4);
/**
 * SHOW_GROUPMEMBERS - show groupmembers no matter what...
 */
define('GROUPTOOL_SHOW_GROUPMEMBERS', 1);

/**
 * GROUPTOOL_EVENT_TYPE_DUE - event type for due date events
 */
define('GROUPTOOL_EVENT_TYPE_DUE', 'deadline');
/**
 * GROUPTOOL_EVENT_TYPE_AVAILABLEFROM - event type for available from events (not used anymore!)
 */
define('GROUPTOOL_EVENT_TYPE_AVAILABLEFROM', 'availablefrom');
