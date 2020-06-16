Grouptool Module
================

This file is part of the mod_grouptool plugin for Moodle - <http://moodle.org/>

*Author:*    Philipp Hager, Hannes Laimer

*Copyright:* 2014 [Academic Moodle Cooperation](http://www.academic-moodle-cooperation.org)

*License:*   [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html)


Description
-----------

The Grouptool module enhances the functionality of Moodle default groups. Two of the additional
features are the possibility for students to enrol in groups with waiting lists on their own, and
the transfer of grades within groups.

The grouptool module features the following functionalities:

* automatic creation of groups with simultaneous enrolment of participants (optional)
  - 1-person groups
  - groups with pre-defined numbers of persons or groups
  - groups with certain pre-defined group names, consisting of [lastname], [firstname], [idnumber],
    [usernumber], numbers, alphabetical indexes, and pure text

* simultaneous creation of groupings for each group created

* self-enrolment of participants in exisiting groups:
  - activate the groups and specify their order for each grouptool instance
  - use a waiting list system with optional limitation of the number of participants
  - define the maximum number allowed per group or for all available groups
  - allow each participant to enrol in several groups (min./max.)

* group grades - transfer awarded grades to other group participants
  - automatically for all groups or just for some
  - select the participant from whom the grade is to be transferred


Example
-------

Create groups and allow students to form groups of no more than five members each to work on a
joint project during the semester.


Requirements
------------

The plugin is available for Moodle 2.5+. This version is for Moodle 3.9.0.


Installation
------------

* Copy the module code directly to the mod/grouptool directory.

* Log into Moodle as administrator.

* Open the administration area (http://your-moodle-site/admin) to start the installation
  automatically.


Admin Settings
--------------

As an administrator you can set the default values instance-wide on the settings page for
administrators in the grouptool module:

* default naming scheme (text field)
* allow self-enrolment (checkbox)
* show group members (drop down)
* immediate enrolment (checkbox)
* allow unenrolment (checkbox)
* general default group size (text field)
* limited group size (checkbox)
* define different group sizes (checkbox)
* use waiting lists (checkbox)
* maximum number of waiting lists a participant can be on at the same time (text field)
* maximum number of waiting list entrys a group can have at the same time (text field)
* multiple enrolments (checkbox)
* minimum number of groups to be selected (text field)
* maximum number of groups to be selected (text field)
* sync behaviour:
  - when adding a group member (drop down)
  - when deleting a group member (drop down)
  - when deleting a group (drop down)
* import settings
  - force registration
  - fields to identify user


Documentation
-------------

You can find a cheat sheet for the plugin on the [AMC
website](http://www.academic-moodle-cooperation.org/en/modules/grouptool/) and a video tutorial in
german only in the [AMC YouTube Channel](https://www.youtube.com/c/AMCAcademicMoodleCooperation).


Bug Reports / Support
---------------------

We try our best to deliver bug-free plugins, but we can not test the plugin for every platform,
database, PHP and Moodle version. If you find any bug please report it on
[GitHub](https://github.com/academic-moodle-cooperation/moodle-mod_grouptool/issues). Please
provide a detailed bug description, including the plugin and Moodle version and, if applicable, a
screenshot.

You may also file a request for enhancement on GitHub. If we consider the request generally useful
and if it can be implemented with reasonable effort we might implement it in a future version.

You may also post general questions on the plugin on GitHub, but note that we do not have the
resources to provide detailed support.


License
-------

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

The plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License with Moodle. If not, see
<http://www.gnu.org/licenses/>.


Good luck and have fun!
