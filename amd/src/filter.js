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
 * Javascript for sortable groups-list
 *
 * @module   mod_grouptool/filter
 * @author    Daniel Binder
 * @copyright 2020 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_grouptool/filter
 */
define(['jquery', 'core/config', 'core/log'], function($, config, log) {

    var Filter = function() {
        this.baseurl = config.wwwroot + '/lib/ajax/setuserpref.php';
    };

    Filter.prototype.toogleUnoccupiedFilter = function (updatePrefs = true) {
        $('.group-full').toggle();
        if (updatePrefs) {
            this.setUserPreference();
        }
    };

    Filter.prototype.setUserPreference = function () {
        var name = 'mod_grouptool_hideoccupied';
        var value = false;
        if ($('#filterunoccupied').prop('checked') === true) {
            value = true;
        }
        var cfg = {
            method: 'get',
            url: this.baseurl,
            data: {
                'sesskey': config.sesskey,
                'pref': encodeURI(name),
                'value': encodeURI(value)
            },
            beforeSend: function() {
                log.info('set user preference ' + name + ': ' + value, 'grouptool');
            },
            success: function() {
                log.info('set user preference OK', 'grouptool');
            },
            error: function() {
                log.error('set user preference FAILED', 'grouptool');
            }
        };
        $.ajax(cfg);
    };

    return {
        /**
         * Initializer
         *
         * @param {object} params
         */
        init: function(params) {
            var instance = new Filter();
            $('#filterunoccupied').change(function() {
                instance.toogleUnoccupiedFilter();
            });
            log.info(params);
            log.info(params.filterunoccupied);
            if(params.filterunoccupied) {
                $('#filterunoccupied').prop('checked', true);
                instance.toogleUnoccupiedFilter(false);
            }
        }
    };
});