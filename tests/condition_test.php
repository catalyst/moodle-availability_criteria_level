<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace availability_criteria_level;

/**
 * Unit tests for criteria level condition.
 *
 * @package     availability_criteria_level
 * @author      Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_test extends \advanced_testcase {

    /**
     * Setup to ensure that fixtures are loaded.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
    }

    /**
     * Load required classes.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Tests constructing and using criteria_level condition as part of a tree.
     *
     * @covers \availability_criteria_level\condition::is_available()
     */
    public function test_in_tree() {
        global $DB, $USER;

        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $params['course'] = $course->id;
        $params['assignfeedback_file_enabled'] = 0;
        $params['assignfeedback_comments_enabled'] = 0;
        $assigninstance = $generator->get_plugin_generator('mod_assign')->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $assigninstance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $assign = new \assign($context, $cm, $course);

        $student1 = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student1->id, $course->id, $studentrole->id);

        // Create advanced grading data.
        // Create grading area.
        $gradingarea = array(
            'contextid' => $context->id,
            'component' => 'mod_assign',
            'areaname' => 'submissions',
            'activemethod' => 'rubric'
        );
        $areaid = $DB->insert_record('grading_areas', $gradingarea);

        // Create a rubric grading definition.
        $rubricdefinition = array(
            'areaid' => $areaid,
            'method' => 'rubric',
            'name' => 'test',
            'status' => 20,
            'copiedfromid' => 1,
            'timecreated' => 1,
            'usercreated' => $USER->id,
            'timemodified' => 1,
            'usermodified' => $USER->id,
            'timecopied' => 0
        );
        $definitionid = $DB->insert_record('grading_definitions', $rubricdefinition);

        // Create a criterion with a level.
        $rubriccriteria = array(
            'definitionid' => $definitionid,
            'sortorder' => 1,
            'description' => 'Demonstrate an understanding of disease control',
            'descriptionformat' => 0
        );
        $criterionid = $DB->insert_record('gradingform_rubric_criteria', $rubriccriteria);
        $rubriclevel1 = array(
            'criterionid' => $criterionid,
            'score' => 50,
            'definition' => 'pass',
            'definitionformat' => 0
        );
        $rubriclevel2 = array(
            'criterionid' => $criterionid,
            'score' => 100,
            'definition' => 'excellent',
            'definitionformat' => 0
        );
        $levelid1 = $DB->insert_record('gradingform_rubric_levels', $rubriclevel1);
        $DB->insert_record('gradingform_rubric_levels', $rubriclevel2);

        $gradeitem = $DB->get_record('grade_items', ['courseid' => $course->id, 'iteminstance' => $assigninstance->id]);

        $info = new \core_availability\mock_info($course, $student1->id);

        $structure = (object) [
            'op' => '|',
            'show' => true,
            'c' => [
                (object) [
                    'type' => 'criteria_level',
                    'gradeitemid' => (int) $gradeitem->id,
                    'criterion' => (int) $criterionid,
                    'level' => (int) $levelid1,
                ]
            ]
        ];
        $tree = new \core_availability\tree($structure);

        // Student should not have access, no grade has been assigned.
        $result = $tree->check_available(false, $info, true, $student1->id);
        $this->assertFalse($result->is_available());

        // Create the filling.
        $student1filling = array(
            'criterionid' => $criterionid,
            'levelid' => $levelid1,
            'remark' => 'well done you passed',
            'remarkformat' => 0
        );

        $student1criteria = array(array('criterionid' => $criterionid, 'fillings' => array($student1filling)));
        $student1advancedgradingdata = array('rubric' => array('criteria' => $student1criteria));

        $feedbackpluginparams = array();
        $feedbackpluginparams['files_filemanager'] = 0;
        $feedbackeditorparams = array('text' => '', 'format' => 1);
        $feedbackpluginparams['assignfeedbackcomments_editor'] = $feedbackeditorparams;

        $gradedata = (object) $feedbackpluginparams;
        $gradedata->addattempt = true;
        $gradedata->attemptnumber = -1;
        $gradedata->workflowstate = 'released';
        $gradedata->applytoall = false;
        $gradedata->grade = 0;

        if (!empty($student1advancedgradingdata)) {
            $advancedgrading = array();
            $criteria = reset($student1advancedgradingdata);
            foreach ($criteria as $key => $criterion) {
                $details = array();
                foreach ($criterion as $value) {
                    foreach ($value['fillings'] as $filling) {
                        $details[$value['criterionid']] = $filling;
                    }
                }
                $advancedgrading[$key] = $details;
            }
            $gradedata->advancedgrading = $advancedgrading;
        }

        $assign->save_grade($student1->id, $gradedata);

        // Student should now have access.
        $result = $tree->check_available(false, $info, true, $student1->id);
        $this->assertTrue($result->is_available());
    }

    /**
     * Tests the save() function.
     *
     * @covers \availability_criteria_level\condition::save()
     */
    public function test_save() {
        $structure = (object) ['gradeitemid' => 2, 'criterion' => 3, 'level' => 4];
        $cond = new condition($structure);
        $structure->type = 'criteria_level';
        $this->assertEquals($structure, $cond->save());
    }

}
