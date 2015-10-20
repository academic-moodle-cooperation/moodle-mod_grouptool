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
 * README.txt
 * @version       2015-01-14
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

# ---------------------------------------------------------------
# FOR Moodle 2.7+
# ---------------------------------------------------------------

Grouptool-Module
===============

OVERVIEW
================================================================================
    The Grouptool Module enhances the standard Moodle-Group-Functionality
    Main features are:

    *) Group creation from different user-roles
        - 1-Person-Groups
        - Groups with defined amount of members/groups
        - Group-Name-Patterns: consisting of [lastname], [firstame], [idnumber],
          [username], numerical index, alphabetical index and plain text
    *) Creation of a grouping for each group
    *) User self-registration
        - define active coursegroups and order per instance
        - queue system with max queue-places setting
        - max groupmembers for all or individual per group
        - multi-group registration (min/max groups to choose)
    *) Group grading - copy grade from one student to whole group
        - automatically for all/certain groups
        - for 1 group
        - choose which students grade to copy


REQUIREMENTS
================================================================================
    Moodle 2.8

INSTALLATION
================================================================================
   The zip-archive includes the same directory hierarchy as moodle
   So you only have to copy the files to the correspondent place.
   copy the folder grouptool.zip/mod/grouptool --> moodle/mod/grouptool
   The langfiles normaly can be left into the folder mod/grouptool/lang.
   All languages should be encoded with utf8.

    After it you have to run the admin-page of moodle
    http://your-moodle-site/admin) in your browser.
    You have to logged in as admin before.
    The installation process will be displayed on the screen.
    That's all.


CHANGELOG
================================================================================
v 2015071502
-------------------------
*) Improve coding/css/js style and docs
*) Cohort dropdown in group creation won't be shown if not necessary
*) Fix certain users only shown as registered in moodle group not in grouptool

v 2015071501
-------------------------
*) Small UI/UX improvements
*) Removed obsolete code
*) Use autoloading

v 2015071500
-------------------------
*) Improve functionality create groups tab
*) Properly deprecate strings
*) Improve layout of groups table in administration
   -) show full group names
   -) Improve/add functionality (bulk actions, single group actions)
*) Impove layout of checkboxcontroller (esp. for small screens)
*) Fix preview count of group creation
*) Move queue rank in txt/pdf download behind group name
*) Reduced memory usage in many parts of the module
   (now usable for > 10k users and many groups)
*) Add progress bar to import
*) Import into multiple groups at once

v 2015050401
-------------------------
*) Fix importfields using standard setting instead of set value
*) Use separate sub-tabs for group creation and administration

v 2015050400
-------------------------
*) Improve preview and status of import
*) Fix bug with wrong overflow warning during import
*) Add missing language strings
*) Fix navigation (AJAX Error, appearance with/without subbranches, etc)
*) Fix typo in SQL
*) Remove XLS support in exports (only XLSX/ODS/etc. now available)
*) Fix wrong queued ranks showed in course view table

v 2015042200
-------------------------
*) Fixed a renamed string identifier blocking language customisation
   if there was an old wrong spelled custom string

v 2015011400
-------------------------
*) Replace add_to_log calls through triggered events
*) Replace event handlers with new event observers
*) Remove unused cron-Method
*) Add Frankenstyle-prefix for global scope classes
*) Move plugin settings to config_plugins table
*) Improve english language file
*) Ensure support of PostgreSQL-DBs
*) New improved active groups layout for better usability
*) Combine Overview and Userlist in common tab participants (2 sub-tabs)
*) Support additional name fields and useridentity in XLS/XLSX/ODS-export
*) Add page numbers in PDF export
*) Better grouping creation features (add selected groups to new/existing grouping)
*) Grouping-filter in grading tab restricts accessible groups

*) Fixed some minor bugs and typos
