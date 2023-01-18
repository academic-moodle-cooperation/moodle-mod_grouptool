@mod @mod_grouptool @adminsetting @amc
Feature: Within a moodle instance, an administrator should be able to set the value for "Show group members" for the entire Moodle installation.
  In order to define the adminsettings of an grouptool.
  As an admin
  I need to default values for grouptool settings.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1|
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |

  @javascript
  Scenario: Switch as an admin to the adminsettings of the module grouptool and change the value of "Show group members" to "No". Then login as a teacher and add a new grouptool to a course and check whether the default value has changed.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Grouptool" in site administration
    And I set the field "Show group members" to "No"
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Grouptool" to section "1" and I fill the form with:
      | Grouptool name | Test grouptool name - No |
      | ID number | Test grouptool name - No |
      | Description | Add a grouptool to the current course |
    When I am on the "Test grouptool name - No" Activity page logged in as admin
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Show group members" matches value "No"

    Then I log out

  @javascript
  Scenario: Switch as an admin to the adminsettings of the module grouptool and change the value of "Show group members" to "All - after due date". Then login as a teacher and add a new grouptool to a course and check whether the default value has changed.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Grouptool" in site administration
    And I set the field "Show group members" to "All - after due date"
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Grouptool" to section "1" and I fill the form with:
      | Grouptool name | Test grouptool name - All - after due date |
      | ID number | Test grouptool name - All - after due date |
      | Description | Add a grouptool to the current course |
    When I am on the "Test grouptool name - All - after due date" Activity page logged in as admin
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Show group members" matches value "All - after due date"

    Then I log out

  @javascript
  Scenario: Switch as an admin to the adminsettings of the module grouptool and change the value of "Show group members" to "Own - after due date". Then login as a teacher and add a new grouptool to a course and check whether the default value has changed.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Grouptool" in site administration
    And I set the field "Show group members" to "Own - after due date"
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Grouptool" to section "1" and I fill the form with:
      | Grouptool name | Test grouptool name - Own - after due date |
      | Description | Add a grouptool to the current course |
      | ID number | Test grouptool name - Own - after due date |
    When I am on the "Test grouptool name - Own - after due date" Activity page logged in as admin
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Show group members" matches value "Own - after due date"

    Then I log out

  @javascript
  Scenario: Switch as an admin to the adminsettings of the module grouptool and change the value of "Show group members" to "Own - after registration". Then login as a teacher and add a new grouptool to a course and check whether the default value has changed.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Grouptool" in site administration
    And I set the field "Show group members" to "Own - after registration"
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Grouptool" to section "1" and I fill the form with:
      | Grouptool name | Test grouptool name - Own - after registration |
      | ID number | Test grouptool name - Own - after registration |
      | Description | Add a grouptool to the current course |
    When I am on the "Test grouptool name - Own - after registration" Activity page logged in as admin
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Show group members" matches value "Own - after registration"

    Then I log out

  @javascript
  Scenario: Switch as an admin to the adminsettings of the module grouptool and change the value of "Show group members" to "Yes". Then login as a teacher and add a new grouptool to a course and check whether the default value has changed.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Grouptool" in site administration
    And I set the field "Show group members" to "Yes"
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Grouptool" to section "1" and I fill the form with:
      | Grouptool name | Test grouptool name - Yes |
      | ID number | Test grouptool name - Yes |
      | Description | Add a grouptool to the current course |
    When I am on the "Test grouptool name - Yes" Activity page logged in as admin
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then the field "Show group members" matches value "Yes"
    Then I log out
