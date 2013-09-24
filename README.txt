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
# FOR Moodle 2.5+
# ---------------------------------------------------------------

README.txt
v.2013081600


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
    Moodle <2.5 or later>

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
v 2013081600
--------------------------------------------------------------------------------
- minimum groups to choose is now a requirement:
  The registrations will be saved only if the minimum amount of groups to choose
  has been chosen. Furthermore will every unregistration from groups be
  prevented if the amount of registrations is under the lower limit.
- new iconset:
  Due to the change to SVG-icons, grouptool got a new activity icon and now uses
  the moodle action-icon-set as actionbuttons.
- it is now possible to show the description directly on the coursepage
- groupgrading now allows also to write the grade directly to assignment-
  and checkmark-instances (additionally to the standard writing to gradebook)
- block "recent activities" won't show private information anymore
- start of registration/end of registration events:
  these are now 2 separate events instead of a single one to prevent it from
  blocking all other events
- show groupmembers has got now it's own CSS copying moodle's design
- various bugfixes, layout improvements, language improvements