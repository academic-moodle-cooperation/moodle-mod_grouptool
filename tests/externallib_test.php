<?php

use mod_grouptool\local\tests\base;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/grouptool/externallib.php');

/**
 * External mod grouptool functions unit tests
 */
class mod_grouptool_external_testcase extends externallib_advanced_testcase {
    
    /**
     * Test if the user only gets grouptool for enrolled courses
     */
    public function test_get_grouptools_by_courses() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course([
            'fullname' => 'PHPUnitTestCourse1',
            'summary' => 'Test course for automated php unit tests',
            'summaryformat' => FORMAT_HTML
        ]);

        $this->getDataGenerator()->enrol_user($user->id, $course1->id);

        $course2 = $this->getDataGenerator()->create_course([
            'fullname' => 'PHPUnitTestCourse2',
            'summary' => 'Test course for automated php unit tests',
            'summaryformat' => FORMAT_HTML
        ]);

        $this->getDataGenerator()->enrol_user($user->id, $course2->id);

        $course3 = $this->getDataGenerator()->create_course([
            'fullname' => 'PHPUnitTestCourse3',
            'summary' => 'Test course for automated php unit tests',
            'summaryformat' => FORMAT_HTML
        ]);

        $grouptool1 = self::getDataGenerator()->create_module('grouptool', [
            'course' => $course1->id,
            'name' => 'Grouptool Module 1',
            'intro' => 'Grouptool module for automated php unit tests',
            'introformat' => FORMAT_HTML,
        ]);

        $grouptool2 = self::getDataGenerator()->create_module('grouptool', [
            'course' => $course2->id,
            'name' => 'Grouptool Module 2',
            'intro' => 'Grouptool module for automated php unit tests',
            'introformat' => FORMAT_HTML,
        ]);

        $grouptool3 = self::getDataGenerator()->create_module('grouptool', [
            'course' => $course3->id,
            'name' => 'Grouptool Module 3',
            'intro' => 'Grouptool module for automated php unit tests',
            'introformat' => FORMAT_HTML,
        ]);

        $this->setUser($user);

        $result = mod_grouptool_external::get_grouptools_by_courses([]);

        // user is enrolled only in course1 and course2, so the third grouptool module in course3 should not be included
        $this->assertEquals(2, count($result->grouptools));
    }


    /**
     * Test if the user gets a valid grouptool from the endpoint
     */
    public function test_get_grouptool() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'PHPUnitTestCourse',
            'summary' => 'Test course for automated php unit tests',
            'summaryformat' => FORMAT_HTML
        ]);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $grouptool = self::getDataGenerator()->create_module('grouptool', [
            'course' => $course->id,
            'name' => 'Grouptool Module',
            'intro' => 'Grouptool module for automated php unit tests',
            'introformat' => FORMAT_HTML,
        ]);

        $this->setUser($user);

        $result = mod_grouptool_external::get_grouptool($grouptool->id);

        // grouptool name should be equal to 'Grouptool Module'
        $this->assertEquals('Grouptool Module', $result->grouptool->name);

        // Course id in grouptool should be equal to the id of the course
        $this->assertEquals($course->id, $result->grouptool->course);
    }


    /**
     * Test if the user gets an exception when the grouptool is hidden in the course
     */
    public function test_get_grouptool_hidden() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'PHPUnitTestCourse',
            'summary' => 'Test course for automated php unit tests',
            'summaryformat' => FORMAT_HTML
        ]);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $grouptool = self::getDataGenerator()->create_module('grouptool', [
            'course' => $course->id,
            'name' => 'Hidden Grouptool Module',
            'intro' => 'Grouptool module for automated php unit tests',
            'introformat' => FORMAT_HTML,
            'visible' => 0,
        ]);

        $this->setUser($user);

        // Test should throw require_login_exception
        $this->expectException(require_login_exception::class);

        $result = mod_grouptool_external::get_grouptool($grouptool->id);

    }

}