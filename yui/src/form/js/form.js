/**
 * JavaScript for form editing criterion conditions.
 *
 * @module moodle-availability_criteria_level-form
 */
M.availability_criteria_level = M.availability_criteria_level || {};

/**
 * @class M.availability_criteria_level.form
 * @extends M.core_availability.plugin
 */
M.availability_criteria_level.form = Y.Object(M.core_availability.plugin);

/**
 * scales available for selection (alphabetical order).
 *
 * @property criterions
 * @type Array
 */
M.availability_criteria_level.form.gradeitems = null;

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} gradeitems Array of objects
 */
M.availability_criteria_level.form.initInner = function(gradeitems) {
    this.gradeitems = gradeitems;
};

M.availability_criteria_level.form.getNode = function(json) {
    // Create HTML structure for grade item selection.
    var html = '<label class="form-group"><span class="p-r-1">Grade Item</span> ' +
        '<span class="availability-criterion">' +
        '<select name="gradeitemid" class="custom-select"><option value="0">' +
        M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.gradeitems.length; i++) {
        var grade = this.gradeitems[i];
        // String has already been escaped using format_string.
        html += '<option value="' + grade.id + '">' + grade.name + '</option>';
    }
    html += '</select></span></label>';
    // Structure for criterion.
    html += '<label><span class="p-r-1">' + M.util.get_string('choosecriteria', 'availability_criteria_level') + '</span> ' +
        '<span class="availability-criteria_level">' +
        '<select name="criterion" class="custom-select">' +
        '<option value="choose">' + M.util.get_string('choosedots', 'moodle') + '</option></select></span></label>';
    // Structure for level
    html += '<label><span class="p-r-1">' + M.util.get_string('chooselevel', 'availability_criteria_level') + '</span> ' +
        '<span class="availability-criteria_level">' +
        '<select name="level" class="custom-select">' +
        '<option value="choose">' + M.util.get_string('choosedots', 'moodle') + '</option></select></span></label>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    // Set initial values (leave default 'choose' if creating afresh).
    if (json.creating === undefined) {
        if (json.gradeitemid !== undefined &&
            node.one('select[name=gradeitemid] > option[value=' + json.gradeitemid + ']')) {
            node.one('select[name=gradeitemid]').set('value', '' + json.gradeitemid);
            M.availability_criteria_level.fillCriterion(node);
        } else if (json.gradeitemid === undefined) {
            node.one('select[name=gradeitemid]').set('value', 'choose');
        }
        if (json.criterion !== undefined &&
            node.one('select[name=criterion] > option[value=' + json.criterion + ']')) {
            node.one('select[name=criterion]').set('value', '' + json.criterion);
            M.availability_criteria_level.fillLevels(node);
        } else if (json.criterion === undefined) {
            node.one('select[name=criterion]').set('value', 'choose');
        }
        if (json.level !== undefined &&
            node.one('select[name=level] > option[value=' + json.level + ']')) {
            node.one('select[name=level').set('value', '' + json.level);
        } else if (json.level === undefined) {
            node.one('select[name=level]').set('value', 'choose');
        }
    }

    // Add event handlers (first time only).
    if (!M.availability_criteria_level.form.addedEvents) {
        M.availability_criteria_level.form.addedEvents = true;
        node.one('select[name=gradeitemid]').delegate('change', function() {
            M.availability_criteria_level.fillCriterion(node);
            M.core_availability.form.update();
        }, '.availability_criteria_level select');

        node.one('select[name=criterion]').delegate('change', function() {
            M.availability_criteria_level.fillLevels(node);
            M.core_availability.form.update();
        }, '.availability_criteria_level select');

        node.one('select[name=level]').delegate('change', function() {
            M.core_availability.form.update();
        }, '.availability_criteria_level select');
    }

    return node;
};

M.availability_criteria_level.form.fillValue = function(value, node) {
    var selected = node.one('select[name=gradeitemid]').get('value');
    if (selected === 'choose') {
        value.gradeitemid = 'choose';
    } else {
        value.gradeitemid = parseInt(selected, 10);
    }
    var selectedcriteria = node.one('select[name=criterion]').get('value');
    if (selectedcriteria === 'choose') {
        value.criterion = '';
    } else {
        value.criterion = parseInt(selectedcriteria, 10);
    }
    var selectedlevel = node.one('select[name=level]').get('value');
    if (selectedlevel === 'choose') {
        value.level = '';
    } else {
        value.level = parseInt(selectedlevel, 10);
    }
};

M.availability_criteria_level.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);

    if ((value.gradeitemid && value.gradeitemid === 'choose') ||
        (value.criterion && value.criterion === 'choose') || (value.level && value.level === 'choose')) {
        errors.push('availability_criteria_level:error_selectcriterion');
    }
};

M.availability_criteria_level.fillCriterion = function(node) {
    var selected = node.one('select[name=gradeitemid]').get('value');
    if (selected !== 'choose') {
        var gradeitemid = parseInt(selected, 10);
        var finalgradeitem = null;
        for (var i = 0; i < M.availability_criteria_level.form.gradeitems.length; i++) {
            var gradeitem = M.availability_criteria_level.form.gradeitems[i];
            if (gradeitem.id == gradeitemid) {
                finalgradeitem = gradeitem;
                break;
            }
        }
        if (finalgradeitem !== null) {
            var criterionselect = node.one('select[name=criterion]');
            var domnode = criterionselect.getDOMNode();

            for (var j = domnode.options.length - 1; j >= 0; j--) {
                domnode.remove(j);
            }

            var chooseoption = document.createElement('option');
            chooseoption.value = 'choose';
            chooseoption.text = M.util.get_string('choosedots', 'moodle');
            domnode.add(chooseoption);

            for (var k = 0; k < finalgradeitem.criteria.length; k++) {
                var criterion = finalgradeitem.criteria[k];
                var option = document.createElement('option');
                option.value = criterion.id;
                option.text = criterion.description;
                domnode.add(option);
            }
        }
    }
};

M.availability_criteria_level.fillLevels = function(node) {
    var criteriaselected = node.one('select[name=criterion]').get('value');
    if (criteriaselected !== 'choose') {
        var gradeitemid = parseInt(node.one('select[name=gradeitemid]').get('value'), 10);
        var gradeitem = null;
        for (var i = 0; i < M.availability_criteria_level.form.gradeitems.length; i++) {
            var gi = M.availability_criteria_level.form.gradeitems[i];
            if (gi.id == gradeitemid) {
                gradeitem = gi;
                break;
            }
        }

        var criteria = null;
        for (var k = 0; k < gradeitem.criteria.length; k++) {
            if (gradeitem.criteria[k].id == parseInt(criteriaselected, 10)) {
                criteria = gradeitem.criteria[k];
            }
        }

        var levelselect = node.one('select[name=level]');
        var domnode = levelselect.getDOMNode();
        for (var j = domnode.options.length - 1; j >= 0; j--) {
            domnode.remove(j);
        }

        var chooseoption = document.createElement('option');
        chooseoption.value = 'choose';
        chooseoption.text = M.util.get_string('choosedots', 'moodle');
        domnode.add(chooseoption);

        for (var l = 0; l < criteria.levels.length; l++) {
            var level = criteria.levels[l];
            var option = document.createElement('option');
            option.value = level.id;
            option.text = level.definition;
            domnode.add(option);
        }
    }
};
