// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * grading.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-mod_grouptool-grading', function (Y) {
    var GRADINGNAME = 'moodle-mod_grouptool-grading';

    var Grading = function() {
        Grading.superclass.constructor.apply(this, arguments);
    };

    var SELECTORS = {
            GROUPSSELECT: 'select[name="filter"]',
            GROUPINGSSELECT: 'select[name="grouping"]'
        };

    Y.extend(Grading, Y.Base, {
        initializer: function() {
            M.mod_grouptool.grading.contextid = this.get('contextid');
            M.mod_grouptool.grading.lang = this.get('lang');
            Y.one(SELECTORS.GROUPINGSSELECT).on('change', M.mod_grouptool.grading.update_groups);
        }
    }, {
        NAME : GRADINGNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
            contextid : { 'value' : 0},
            lang : { 'value' : 'en'}
        } //Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          //Here you can declare default values or run functions on the parameter.
          //The param names must be the same as the ones declared
          //in the $PAGE->requires->yui_module call.

    });

    M.mod_grouptool = M.mod_grouptool || {};
    M.mod_grouptool.grading = M.mod_grouptool.grading || {};

    M.mod_grouptool.grading.update_groups = function() {
        var groupingid = 0;
        Y.one(SELECTORS.GROUPINGSSELECT).all("option").each( function() {
            // this = option from the select
            if (this.get('selected')) {
                groupingid  = this.get('value');
                if (groupingid < 0) {
                    groupingid = 0;
                }
            }
        });

        ajaxurl = M.cfg.wwwroot + '/mod/grouptool/groupinggroups_ajax.php?groupingid=' + groupingid +
                 '&lang=' + M.mod_grouptool.grading.lang + '&contextid=' + M.mod_grouptool.grading.contextid;

        var cfg = {
            method : 'get',
            context : this,
            on : {
                success: function(id, o) {
                    var options = '';
                    var data = Y.JSON.parse(o.responseText);
                    var oldsel = -1;
                    Y.one(SELECTORS.GROUPSSELECT).all("option").each( function() {
                        // This = option from the select!
                        if (this.get('selected')) {
                            oldsel  = this.get('value');
                        }
                    });
                    for (var i = 0; i < data.length; i++) {
                        if (Y.one(SELECTORS.GROUPSSELECT + ' option[value="' + data[i].id + '"]')
                            && Y.one(SELECTORS.GROUPSSELECT + ' option[value="' + data[i].id + '"]').get('selected')) {
                            options += "\n<option value=\"" + data[i].id + "\" selected=\"selected\">" + data[i].name + "</option>";
                        } else {
                            options += "\n<option value=\"" + data[i].id + "\">" + data[i].name + "</option>";
                        }
                    }
                    Y.one(SELECTORS.GROUPSSELECT).setHTML(options);
                    if (!Y.one(SELECTORS.GROUPSSELECT + ' option[value=\"' + oldsel + '\"]')) {
                        Y.one(SELECTORS.GROUPSSELECT + 'option[value=\"-1\"]').set('selected');
                    }
                },
                failure: function(id, o) {
                    if (M.cfg.developerdebug) {
                        alert(o);
                    }
                }
            }
        };

        Y.io(ajaxurl, cfg);
    };

    M.mod_grouptool.grading.init = function(config) {
        return new Grading(config);
    };

}, '@VERSION@', {
    "requires": ["node", "json", "io"]
});
