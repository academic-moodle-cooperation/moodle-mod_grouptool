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

/**
  * @module mod_grouptool/grading
  */
define(['jquery', 'core/config', 'core/str', 'core/log'],
       function($, config, str, log) {

    var Grading = function() {
        /** @access public **/
        this.contextid = 0;
        this.lang = '';

        this.SELECTORS = {
            GROUPINGSELECT: 'select[name=grouping]',
            GROUPSELECT: 'select[name=filter]'
        };
    };

    Grading.prototype.update_groups = function(e) {
        var groupingid = 0;

        log.info("Update groups!", "grouptool");

        groupingid = $(e.data.SELECTORS.GROUPINGSELECT).val();

        if (groupingid < 0) {
            groupingid = 0;
        }

        var contextid = e.data.contextid;

        var cfg = {
            method : 'get',
            url : config.wwwroot + '/mod/grouptool/groupinggroups_ajax.php',
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
                    var oldsel = -1;

                    oldsel = $(e.data.SELECTORS.GROUPSELECT).val();

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

    instance.initializer = function(params) {
        instance.contextid = params.contextid;
        $(this.SELECTORS.GROUPINGSELECT).on('change', null, this, instance.update_groups);
    };

    return instance;
});
