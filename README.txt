# ---------------------------------------------------------------
# This software is provided under the GNU General Public License
# http://www.gnu.org/licenses/gpl.html
# with Copyright © 2012 onwards
#
# Dipl.-Ing. Andreas Hruska
# andreas.hruska@tuwien.ac.at
# 
# Dipl.-Ing. Mag. rer.soc.oec. Katarzyna Potocka
# katarzyna.potocka@tuwien.ac.at
# 
# Vienna University of Technology
# Teaching Support Center
# Gußhausstraße 28/E015
# 1040 Wien
# http://tsc.tuwien.ac.at/
# ---------------------------------------------------------------
# FOR Moodle 2.2.1+
# ---------------------------------------------------------------

README.txt
v.2012-05-22


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
    Moodle <2.2.1 or later>

INSTALLATION 
================================================================================
   The zip-archive includes the same directory hierarchy as moodle
   So you only have to copy the files to the correspondent place.
   copy the folder grouptool.zip/mod/grouptool --> moodle/mod/grouptool
   The langfiles normaly can be left into the folder mod/grouptool/lang.
   All languages should be encoded with utf8.

    After it you have to run the admin-page of moodle (http://your-moodle-site/admin)
    in your browser. You have to loged in as admin before.
    The installation process will be displayed on the screen.
    That's all.


CHANGELOG
================================================================================
