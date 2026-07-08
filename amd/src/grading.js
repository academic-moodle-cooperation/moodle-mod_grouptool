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
 * JS handling select box content in group grading tab
 *
 * @module   mod_grouptool/grading
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_grouptool/grading
 */
define(['jquery', 'core/config', 'core/str', 'core/log'], function($, config, str, log) {

    /**
     * @constructor
     */
    var Grading = function() {
        this.contextid = 0;
        this.lang = '';

        this.SELECTORS = {
            GROUPINGSELECT: 'select[name=grouping]',
            GROUPSELECT: 'select[name=filter]'
        };
    };

    /**
     * Updates the groups according to selected groupings
     *
     * @param {Event} e Event object
     */
    Grading.prototype.updateGroups = function(e) {
        var groupingid = 0;

        log.info("Update groups!", "grouptool");

        groupingid = $(e.data.SELECTORS.GROUPINGSELECT).val();

        if (groupingid < 0) {
            groupingid = 0;
        }

        var contextid = e.data.contextid;

        var cfg = {
            method: 'get',
            url: config.wwwroot + '/mod/grouptool/groupinggroups_ajax.php',
            data: {
                'groupingid': groupingid,
                'lang': config.lang,
                'contextid': contextid
            },
            dataType: 'json',
            beforeSend: function() {
                log.info("Request groups for grouping " + groupingid, "grouptool");
            },
            success: function(data) {

                if (!data.error) {
                    var options = '';
                    var oldsel = $(e.data.SELECTORS.GROUPSELECT).val();

                    for (var i = 0; i < data.length; i++) {
                        if ($(e.data.SELECTORS.GROUPSELECT).filter(' option[value="' + data[i].id + '"]')
                            && $(e.data.SELECTORS.GROUPSELECT).filter(' option[value="' + data[i].id + '"]').get('selected')) {
                            options += "\n<option value=\"" + data[i].id + "\" selected=\"selected\">" + data[i].name + "</option>";
                        } else {
                            options += "\n<option value=\"" + data[i].id + "\">" + data[i].name + "</option>";
                        }
                    }
                    $(e.data.SELECTORS.GROUPSELECT).html(options);
                    $(e.data.SELECTORS.GROUPSELECT).val(oldsel);
                } else {
                    log.error(data.error, "grouptool");
                }
            },
            error: function(jqXHR, error) {
                log.error(error, "grouptool");
            }
        };

        $.ajax(cfg);
    };

    var instance = new Grading();

    /**
     * Initializer
     *
     * @param {object} params
     */
    instance.initializer = function(params) {
        instance.contextid = params.contextid;
        $(this.SELECTORS.GROUPINGSELECT).on('change', null, this, instance.updateGroups);

        var form = $('#grading_form');
        var selects = form.find('input[name="selected[]"]');

        form.on('click', '.checkboxcontroller a.select_all', function(e) {
            e.stopPropagation();
            e.preventDefault();

            // Select all!
            selects.prop('checked', true);
        });
        form.on('click', '.checkboxcontroller a.select_none', function(e) {
            e.stopPropagation();
            e.preventDefault();

            // Deselect all!
            selects.prop('checked', false);
        });
    };

    return instance;
});
