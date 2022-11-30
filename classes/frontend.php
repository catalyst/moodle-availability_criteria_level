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
 * Front-end class.
 *
 * @package     availability_criteria_level
 * @author      Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    protected function get_javascript_strings() {
        return array('title', 'choosecriteria', 'chooselevel');
    }

    protected function get_javascript_init_params($course, \cm_info $cm = null,
        \section_info $section = null) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $gradeoptions = array();
        $items = \grade_item::fetch_all(array('courseid' => $course->id));
        // For some reason the fetch_all things return null if none.
        $items = $items ? $items : array();
        foreach ($items as $id => $item) {
            // Don't include the grade item if it's linked with a module that is being deleted.
            if (course_module_instance_pending_deletion($item->courseid, $item->itemmodule, $item->iteminstance)) {
                continue;
            }
            if ($cm && $cm->instance == $item->iteminstance
                && $cm->modname == $item->itemmodule
                && $item->itemtype == 'mod') {
                continue;
            }

            $context = $item->get_context();
            $area = $DB->get_record('grading_areas', array('contextid' => $context->id));
            if ($area == null) {
                continue;
            }
            $definition = $DB->get_record('grading_definitions', array('areaid' => $area->id, 'method' => $area->activemethod));
            if ($definition == null) {
                continue;
            }

            $criteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $definition->id]);
            $levelsexist = false;
            foreach ($criteria as $criterion) {
                $levels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $criterion->id]);
                if (!empty($levels)) {
                    $levelsexist = true;
                }
                $criterion->levels = array_values($levels);
            }
            if (!$levelsexist) {
                continue;
            }

            $criteria = array_values($criteria);

            $gradeoptions[$id] = array('id' => $id, 'name' => $item->get_name(true), 'criteria' => $criteria);
        }
        asort($gradeoptions);

        // Change to JS array format and return.
        $gradeitems = array();
        foreach ($gradeoptions as $obj) {
            $gradeitems[] = (object) $obj;
        }

        return [$gradeitems];
    }
}
