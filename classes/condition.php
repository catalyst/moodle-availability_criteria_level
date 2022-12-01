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

use core_availability\info;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Condition on graded criterion level of current user.
 *
 * @package     availability_criteria_level
 * @author      Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /**
     * @var int Grade item ID
     */
    protected int $gradeitemid;
    /**
     * @var int Criterion ID
     */
    protected int $criterion;
    /**
     * @var int Criterion level ID
     */
    protected int $level;

    /**
     * Condition constructor.
     *
     * @param \stdClass $structure
     */
    public function __construct($structure) {
        if (isset($structure->gradeitemid) && is_int($structure->gradeitemid)) {
            $this->gradeitemid = $structure->gradeitemid;
        } else {
            throw new \coding_exception('Invalid ->gradeitemid for criteria condition');
        }
        if (isset($structure->criterion) && is_int($structure->criterion)) {
            $this->criterion = $structure->criterion;
        } else {
            throw new \coding_exception('Invalid ->criterion for criteria condition');
        }
        if (isset($structure->level) && is_int($structure->level)) {
            $this->level = $structure->level;
        } else {
            throw new \coding_exception('Invalid ->level for criteria condition');
        }
    }

    /**
     * Checks if the item is available, determined by whether the given user was awarded
     * the set level in a grade item criterion.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we are checking
     * @param bool $grabthelot
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, info $info, $grabthelot, $userid) {
        global $DB;

        $gradeitem = \grade_item::fetch(['id' => $this->gradeitemid]);
        $cm = get_coursemodule_from_instance($gradeitem->itemmodule, $gradeitem->iteminstance, $gradeitem->courseid);
        if ($cm == null) {
            return $not != false;
        }
        $context = \context_module::instance($cm->id);

        $criteria = $DB->get_record('gradingform_rubric_criteria', ['id' => $this->criterion]);
        if ($criteria == null) {
            return $not != false;
        }
        $level = $DB->get_record('gradingform_rubric_levels', ['id' => $this->level]);
        if ($level == null) {
            return $not != false;
        }

        $assign = new \assign($context, $cm, false);
        $usergrade = $assign->get_user_grade($userid, false);
        if (!$usergrade) {
            return $not != false;
        }

        $gradinginstance = $DB->get_record('grading_instances', array('definitionid' => $criteria->definitionid,
            'itemid' => $usergrade->id,
            'status' => \gradingform_instance::INSTANCE_STATUS_ACTIVE));
        if ($gradinginstance == null) {
            return $not != false;
        }

        $fillings = $DB->get_records('gradingform_rubric_fillings',
            array('instanceid' => $gradinginstance->id, 'criterionid' => $criteria->id)
        );

        foreach ($fillings as $filling) {
            if ($filling->levelid == $this->level) {
                return $not != true;
            }
        }

        return $not != false;
    }

    /**
     * Returns a string describing the restriction.
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we are checking
     * @return \lang_string|string
     */
    public function get_description($full, $not, info $info) {
        global $DB;

        $gradeitem = \grade_item::fetch(['id' => $this->gradeitemid]);
        $cm = get_coursemodule_from_instance($gradeitem->itemmodule, $gradeitem->iteminstance, $gradeitem->courseid);
        if ($cm == null) {
            return get_string('error_loading_requirements', 'availability_criteria_level');
        }

        $criteria = $DB->get_record('gradingform_rubric_criteria', ['id' => $this->criterion]);
        if ($criteria == null) {
            return get_string('error_loading_requirements', 'availability_criteria_level');
        }
        $level = $DB->get_record('gradingform_rubric_levels', ['id' => $this->level]);
        if ($level == null) {
            return get_string('error_loading_requirements', 'availability_criteria_level');
        }

        $inf = new \stdClass();
        $inf->activity = $cm->name;
        $inf->level = $level->definition;
        $inf->criteria = $criteria->description;
        return get_string(($not ? '_not' : '') . 'requires_criteria', 'availability_criteria_level', $inf);
    }

    /**
     * Returns the settings of this condition as a string for debugging.
     *
     * @return string
     */
    protected function get_debug_string() {
        return $this->gradeitemid . '#' . $this->criterion . '-' . $this->level;
    }

    /**
     * Saves condition settings to a structure object.
     *
     * @return \stdClass Structure object
     */
    public function save() {
        $result = new \stdClass();
        $result->type = 'criteria_level';
        $result->gradeitemid = $this->gradeitemid ?? null;
        $result->criterion = $this->criterion ?? null;
        $result->level = $this->level ?? null;
        return $result;
    }
}
