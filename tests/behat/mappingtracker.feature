@block @block_mappingtracker
Feature: Track how activities are mapped to a course's competencies
  In order to know a course will collect enough evidence for each unit
  As a course builder
  I need to see which competencies are mapped, how many times, against targets

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Design    | DES      |
    And the following "core_competency > competencies" exist:
      | shortname         | idnumber | competencyframework |
      | Layouts unit      | U1       | DES                 |
      | Typography unit   | U2       | DES                 |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | U1         |
      | C1     | U2         |
    And the following "activities" exist:
      | activity | name           | course | idnumber |
      | assign   | Magazine task  | C1     | T1       |
    And the following "block_mappingtracker > mappings" exist:
      | activity | competency |
      | T1       | U1         |
    And the following "blocks" exist:
      | blockname     | contextlevel | reference | pagetypepattern | defaultregion |
      | mappingtracker | Course       | C1        | course-view-*   | side-pre      |

  Scenario: A manager sees the gaps and raises a target
    Given I am on the "Course 1" course page logged in as "admin"
    Then I should see "Mapping tracker"
    And I should see "0 of 1 met · 1 not mapped"
    And I should see "Typography unit" in the ".block_mappingtracker-gaps" "css_element"
    And I follow "Open full mapping"
    And I should see "Magazine task"
    And I set the field "Target for Layouts unit" to "2"
    And I press "Save"
    And I should see "Target saved"
    And I should see "1 of 2"
    And I should see "Set"

  Scenario: A non-editing teacher can see the mapping but not change targets
    Given I am on the "Course 1" course page logged in as "teacher1"
    Then I should see "Mapping tracker"
    And I follow "Open full mapping"
    And I should see "Magazine task"
    And "Save" "button" should not exist
