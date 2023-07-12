CHANGELOG
========

4.1.0 (2023-06-22)
------------------
* Moodle 4.1.0 compatible version
* [FEATURE] #7078 Show group pictures in the module
* [FEATURE] #7079 Show group descriptions in the module
* [FEATURE] #7081 Show group messages to group members in moodle messaging tool
* [FIXED] #7427 Show module description only if instance is available or alwaysshowdescription is true
* [FIXED] #7437 Fix vertical alignment in registration view
* [FIXED] #7634 Remove Zone.Identifier files from pix directory

4.0.2 (2022-10-09)
------------------
* [FIXED] #7363 Exchange hardcoded strings in groupmembers dialogue with dynamic ones [github#27]

4.0.1 (2022-09-28)
------------------
* [FIXED] #7301 Update activity completion status correctly after self registration
* [FIXED] #7306 Fix recalculation of grades after copying grades among groups [github#26]

4.0.0 (2022-07-20)
------------------
* [FIXED] #7220 Fix create group from group bugs
* [FIXED] #7269 Use only active users for group creation
* [FIXED] #7267 Prevent double completion display
* [UPDATE] #7201 Use icon in new style
* [FIXED] #7244 Use correct required and optional parameter order for PHP 8.0
* [FIXED] #7243 Fixed an error preventing download of .pdf or .txt exports
* [FIXED] #7241 Fixed an error showing when using participants list with custom fields

3.11.2 (2022-06-08)
------------------
* [FIXED] #7189 Fix binding of selectfromgrouping langstring
* [FIXED] #7136 Add a default option to only include active users when auto generating groups

3.11.1 (2022-02-09)
------------------
* [FEATURE] #7015 Allow creation of subgroups from groupings or other groups similar to core groups
* [FEATURE] #7040 Display activity completion on top of the landing page for students. Also an automatic completion
                  setting for registering to a given mount of groups was implemented
* [FEATURE] #7015 Show activity dates on top of the landing page like in moodle core activities
* [FEATURE] #7080 Add a link to the core group settings page in the administration overview
* [FIXED] #7056 Use enough zeros for padding when 10,100,1000,... groups are created

3.11.0 (2021-05-19)
------------------
* [FEATURE] #6855 Add support for showuseridentiy information to group and course view and their respective exports
* [FEATURE] #6951 Enable deregistration of users across moodle groups and other grouptool instances even if user is not
                  present in the given instance. "Force deregistration form grouptool" needs to be checked for this to work
* [FIXED] #6819 Fix wrong alphabetical allocation of group members occuring if groups cannot be filled up completely
* [FIXED] #6817 Fix students being enrolled to a course when using the import function when the operation is aborted
* [FIXED] #6858 Fix collapsing of columns in course view causing values to clip into the collapsed column
* [FIXED] #6949 Fix a warning appearing when attempting to copy grands in the "without conflicts" view

3.10.0 (2020-11-18)
------------------
* [FEATURE] #6774 Support for multilang format course names in export filenames [github pull #21 t-schroeder]

3.9.0 (2020-06-15)
------------------
* [FEATURE] #6009 Remove redundant individual group size setting. Now group sizes can be changed
                  without activating an additional setting
* [FEATURE] #6590 Add filter for only showing groups with free spots in the student group registation
* [FEATURE] #6343 Implement various behat tests for admin settings
* [FIXED] #5704 Fixed a bug that allowed non-numeric characters to be entered in certain fields in instance settings
* [FIXED] #5713 Improved readability and unified design and appearance of success messages
                and remove obsolete confirmation dialogues
* [FIXED] #6681 Fixed a bug ignoring a non-set queue limit when creating an instance and setting default limits instead

3.8.1 (2020-02-23)
------------------

* [FEATURE] #6582 fixed langstring in warning for inactive group when deregistrating
* [FEATURE] #6370 improved queue and multi registration section in the settings


3.8.0 (2020-01-15)
------------------

* [BUG] #6495 removed bug which caused a debugging warning to pop up
* [FEATURE] #6358 added possibility to enable messaging in group-creation
* [BUG] #6258 Typos fixed
* [FEATURE] #5295 Deregister user the same way they can be added


3.7.0 (2019-07-23)
------------------

* [BUG] #6198 bug with group ordering fixed
* [BUG] #6199 bug in gourse view with empty grouping fixed
* [FEATURE] #6008 mode helptext splitted
* [FEATURE] #6128 report-editdates support added
* [CHANGED] #5571 calculation of total value change from size to queued + registered


3.6.0 (2019-01-20)
------------------

* Moodle 3.6 compatible version
* merged PR #11 from from germanvaleroelizondo/master fixing a typo in lang strings
* [FEATURE] #5731 added a first behat test adding a grouptool instance
* [FEATURE] #5752 add new core_userlist_provider methods to privacy provider
* [FEATURE] #5834 enable users to look at groups/registrations in frozen contexts and export them
* [FIXED] #5602 fix calculation for fast unit tests
* [FIXED] #5874 fix queue limits for users and groups not being deactivateable
* [FIXED] #5872 prevent users from reaching incorrect_tabs (per default, or otherwise)
* [CHANGED] #5705 standardized labels and strings
* [CHANGED] #5706 unify appearance of information in language strings
* [CHANGED] #5602 reorganized unit test
* [CHANGED] #5602 replace tab in feature file with spaces
* [CHANGED] #5602 remove leading slashes in namespace and use statements
* [UPDATE] #5602 update .travis.yml


3.5.1 (2018-08-28)
------------------

* [FIXED] #5716 fix XLSX and ODS exports missing some user identity fields data for registered users


3.5.0 (2018-07-01)
------------------

* [FEATURE] #5379 implemented privacy API, so we're GDPR conform
* [FEATURE] #5054 if groups are now recreated due to a grouptool's settings, the user gets notified via info-notification also we
*                 disabled the groups' deletion buttons as soon as there's a grouptool instance set to recreate the groups in the
*                 course
* [FIXED]   #5556 due to some missing column aliases, some registrations promoted from the queue have no correct timestamp, this is
*                 fixed for future entries, but current entries can't be fixed automatically
* [CHANGED] #5086 removed german lang strings from repository
* [CHANGED] #4764 added link to group administration in groups-created-success message
* [CHANGED] #4765 checkboxcontroller on group administration now has 'all' selected by default
* [CHANGED] #5512 checkboxcontroller now uses updated Bootstrap classes
* [CHANGED] #5085 lots of code-style improvements, much coding style improvements, unused vars, etc.
* [CHANGED] updated travis.yml once more


3.4.1 (2018-03-14)
------------------

* [FIXED] #5280 users were not promoted from queue to registered when certain ways of unregistering were used
* [FIXED] #5281 queues were not resolved, due to the code just calling the preview instead of doing anything
* [FIXED] #5226 fixed 2 german lang strings missing an 'n' at the end
* [CHANGED] #5282 messages about user movements when resolving queues were improved


3.4.0 (2018-01-10)
------------------

* Moodle 3.4 compatible version
* pin node version to 8.9 in travis.yml
* [UPDATE] #4845 fix some JS (coding style, etc.)
* [UPDATE] #4843 hide currently unused form elements instead of disabling them
* [FIXED] wrong color mentioned in help string
* [UPDATE] #4843 updated checkboxcontroller styling
* [FIXED] #5055 some typos in german lang strings (also in AMOS)
* [FIXED] #5057 disabled output buffering and compression on a page with progress bar
* [UPDATE] improve usability of self registration tab by expanding groups-fieldset by default


3.3.2 (2017-10-10)
------------------

* [FIXED] #4754 fixed problems with grouptool_refresh_events() callback due to parameters having been changed
                between Moodle 3.3.1 and 3.3.2
* [CHANGED] #4682 updated travis.yml to use moodle-plugin-ci version 2 and run behat tests in firefox and chrome
* fixed overall coding style
* added and improved some PHPDoc and JSDoc comments
* added a missing plugin-upgrade-savepoint
* fixed templates HTML and example JSON
* made mustache tests in travis optional so https://github.com/moodlerooms/moodle-plugin-ci/issues/62 won't break the build


3.3.1 (2017-08-16)
------------------

* [FIXED] #4663 fix failing CLI upgrades due to non disabled capability checks when updating calendar events


3.3.0 (2017-08-10)
------------------

* Moodle 3.3 compatible version
* [FEATURE] #4076 support for FontAwesome icons
* [FEATURE] #4416 action events guiding users what has to be done (also deprecated grouptool_print_overview())
* [FIXED] #4574 fixed a bug where multiple grouptool instances with different settings for handling deleted groups caused the groups
                to be not manageable anymore if they got restored by another instance
* [FIXED] #4574 fixed copied grades not being fully shown immediately in mod_assign
* [FIXED] #4655 removed class "course-content" from HTML body causing wrong styles for nodes with class "current"
* [CHANGED] #4570 hide self registration info's if self registration is deactivated
* [CHANGED] #4575 group size activation warning will be only displayed if group size isn't already active
* [CHANGED] #4435 AJAX uses now Moodle's webservices/external functions API
* [CHANGED] #4285 improve code structure and style
* many other small to medium improvements


3.2.0 (2017-01-25)
------------------

* Moodle 3.2 compatible version
* [FEATURE] #3091 Add per group limitation for queue places
* [FEATURE] #3091 PHPUnit tests for registration methods
* [CHANGED] #3091 Rewrite of register_in_agrp-method and unregister_from_agrp-method
* [CHANGED] #3833 Use new AMD modal-module and mustache-template for members-pop-up

3.1.2 (2016-06-17)
------------------

* [FEATURE] #3594 Make name and description searchable
* [FEATURE] #3177 It's now possible to include inactive groups in course view and user view tables
* [FEATURE] #3783 New group creation mode "N groups of size M" enables users to create N groups with groupsize M
* [FIXED] #3316 Unqueue users if they're registered via import or event observer (moodle group administration)


3.1.1 (2016-06-17)
------------------

* [FIXED] #3825 Fix users not being promoted from queue after others unregister


3.1.0 (2016-06-17)
------------------

* Moodle 3.1 compatible version
* [CHANGED] #3058 Replaced legacy notification classes with current ones
* [CHANGED] #3058 Use only gradeitems with itemnumber 0 or NULL for group grading -
  multi-grade-item-support following in the future
* [FIXED] #3058 Broken sortlist JS (advanced checkbox controller not working)
* [REMOVED] #3058 Deprecated gif icon
* [CHANGED] #3328 Fix warning when importing in inactive group
* [CHANGED] #3299 Replaced custom form with moodleform in self registration
* [FIXED] #3326 Fixed JS in group administration not showing error messages due to removing them immediately
* [CHANGED] #3327 Send messages to users if they get promoted from queue due to unregistration or changed groupsize
* [FIXED] #3327 Fix users not being promoted from queue if groupsize changes individually
* other small bug fixes and improvements (whitespace fixes, removed unused and commented out code)


3.0.0 (2016-05-07)
------------------

* Moodle 3.0 compatible version
* PHP 7 compatibility
* [FIXED] #3263 Bug causing different behavior when changing group sizes with/without JavaScript
* [REMOVED] #3130 Unused .gitignore file


2.9.0 (2016-01-12)
------------------

* Moodle 2.9 compatible version
* [FEATURE] #2781 Hide ID-Number for students in show members popup
* [FEATURE] #2832 Exchange place of group and rank for queues in userlist xlsx and ods export
* [FEATURE] #2832 Align 'no registrations' and 'no queues' messages in XLSX and ODS left
* [FEATURE] #2831 Move download links to an easier distinguish position in overview
* [FEATURE] #2844 Improve design of self registration tab
* [FEATURE] #2783 Enhance show members setting with finer options (all, all after due, own after due,
  own after reg, none)
* [CHANGED] #2469 Rewrite JS to AMD and replace YUI with JQuery where safely possible
* [FIXED] #2829 Improve alignment of texts on registration page
* [FIXED] #2834 Improve text describing non individual group sizes
* [FIXED] #2825 Deadline calendar events being duplicated instead of updated for each instance edit
* [FIXED] #2818 Add some missing lang strings
* [FIXED] #2694 Replace "Studierende" with "Teilnehmer/innen" and "Lehrende" with "Trainer/innen" in german
  language file
* [FIXED] Link's URLs if grouptool is called via g parameter in view.php
* [FIXED] Fixed some smaller bugs and improve coding style and documentation
* [REMOVED] #2838 Remove include deleted users option in import
* [REMOVED] Language string never used anywhere
* [REMOVED] #2824 Group mode setting and support for groups/groupings, we used it just for the access
  restrictions in the past but they can (and should) now be realised via conditional access settings
  (have to be enabled for the Moodle instance first)


2.8.0 (2015-07-15)
------------------

* Moodle 2.8 compatible version
* [FEATURE] #2303 Improve functionality create groups tab
* [FEATURE] #2383 Add progress bar to import
* [FEATURE] #2311 Import into multiple groups at once
* [FEATURE] Improve layout of groups table in administration
   - show full group names
   - Improve/add functionality (bulk actions, single group actions)
* [FEATURE] #2301 Use separate sub-tabs for group creation and administration
* [FIXED] #2675 Cohort dropdown in group creation won't be shown if not necessary
* [FIXED] #2734 Certain users only shown as registered in moodle group not in grouptool
* [FIXED] #2355 Impove layout of checkboxcontroller (esp. for small screens)
* [FIXED] #2355 Small UI/UX improvements
* [FIXED] #2677 Use autoloading
* [FIXED] #22289 Preview count of group creation
* [FIXED] #2394, #2396 Move queue rank in txt/pdf download behind group name
* [FIXED] #2279, #2547 Reduced memory usage in many parts of the module (now usable for > 10k users
  and many groups)
* [FIXED] #2357 Importfields using standard setting instead of set value
* [FIXED] Properly deprecate strings
* [FIXED] #2357 Improve preview and status of import
* [FIXED] #2837 Wrong overflow warning during import
* [FIXED] #2389 Add missing language strings
* [FIXED] #2391 Navigation (AJAX Error, appearance with/without subbranches, etc)
* [FIXED] #2392 Typo in SQL
* [FIXED] #2393 Fix wrong queued ranks showed in course view table
* [REMOVED] #2395 XLS support in exports (only XLSX/ODS/etc. now available)
* [REMOVED] #2680 Obsolete code
* Improve coding/css/js style and docs


2.7 (2015-04-22)
----------------

* First release for Moodle 2.7
* [FEATURE] #2140 New improved active groups layout for better usability
* [FEATURE] #2138 Combine Overview and Userlist in common tab participants (2 sub-tabs)
* [FEATURE] #2138 Support additional name fields and useridentity in XLS/XLSX/ODS-export
* [FEATURE] #1923 Add page numbers in PDF export
* [FEATURE] #2147 Better grouping creation features (add selected groups to new/existing grouping)
* [FEATURE] #2139 Grouping-filter in grading tab restricts accessible groups
* [CHANGED] #1914 Replace add_to_log calls through triggered events
* [CHANGED] #1976 Replace event handlers with new event observers
* [FIXED] #2082 Add Frankenstyle-prefix for global scope classes
* [FIXED] #2083 Move plugin settings to config_plugins table
* [FIXED] #2084 Improve english language file
* [FIXED] #2085 Ensure support of PostgreSQL-DBs
* [FIXED] A renamed string identifier blocking language customisation if there was an old wrong
  spelled custom string
* [FIXED] Some minor bugs and typos
* [REMOVED] Unused cron-Method
