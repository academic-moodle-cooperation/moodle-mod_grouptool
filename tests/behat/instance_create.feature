@mod @mod_grouptool @adding
Feature: In a course, a teacher should be able to add a new grouptool
    In order to add a new grouptool
    As a teacher
    I need to be able to add a new grouptool and save it.

  @javascript
  Scenario: Add a grouptool instance
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@teacher.com |
      | student1 | Student | 1 | student1@students.com |
	And the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 0|
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    When I add a "Grouptool" to section "2" and I fill the form with:
      | Grouptool name | Add a grouptool to the current course |
      | Description | Add a grouptool to the current course (Description) |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Add a grouptool to the current course"
    And I expand all fieldsets
    Then I should see "Add a grouptool to the current course (Description)"
