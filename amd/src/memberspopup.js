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
 * Javascript handling pop-over displaying group members.
 *
 * @module   mod_grouptool/memberspopup
 * @author   Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/modal',
    'core/templates',
    'core/url',
    'core/str',
    'core/log'
], function($, Modal, Templates, Url, Str, Log) {

    Log.info('Loading groupmembers JS...', 'mod_grouptool');

    /**
     * Constructor.
     *
     * @constructor
     * @alias module:mod_grouptool/memberspopup
     */
    var Memberspopup = function() {
        this.showidnumber = false;
        this.courseid = '';
        this.modal = null;
        this.modalpromise = null;
    };

    var instance = new Memberspopup();

    /**
     * Initializes the JS module.
     *
     * @param {Object} config Configuration object.
     * @param {Boolean} config.showidnumber Whether idnumber should be shown.
     * @param {Number|String} config.courseid Course id.
     */
    instance.initializer = function(config) {
        instance.showidnumber = config.showidnumber;
        instance.courseid = config.courseid;

        Log.info('Initialize groupmembers JS!', 'mod_grouptool');

        if (!instance.modalpromise) {
            instance.modalpromise = Modal.create({
                body: '...'
            });
        }

        Str.get_string('groupmembers').done(function(groupmembersstring) {
            Log.info('Done loading strings...', 'mod_grouptool');

            instance.modalpromise.then(function(modal) {
                Log.info('Done preparing modal...', 'mod_grouptool');

                instance.modal = modal;

                $('#registration_form').on('click', 'span.memberstooltip > a', function(e) {
                    e.stopPropagation();
                    e.preventDefault();

                    var element = $(e.currentTarget);
                    var statushelp = element.parents('form').data('statushelp');

                    var absregs = [];
                    var gtregs = [];
                    var mregs = [];
                    var queued = [];

                    try {
                        absregs = element.data('absregs') || [];
                    } catch (ex) {
                        absregs = [];
                    }

                    try {
                        gtregs = element.data('gtregs') || [];
                    } catch (ex) {
                        gtregs = [];
                    }

                    try {
                        mregs = element.data('mregs') || [];
                    } catch (ex) {
                        mregs = [];
                    }

                    try {
                        queued = element.data('queued') || [];
                    } catch (ex) {
                        queued = [];
                    }

                    var name = groupmembersstring;

                    try {
                        if (element.data('name')) {
                            name = groupmembersstring + ': ' + element.data('name');
                        }
                    } catch (ex) {
                        name = groupmembersstring;
                    }

                    var context = {
                        courseid: instance.courseid,
                        showidnumber: instance.showidnumber,
                        profileurl: Url.relativeUrl('/user/view.php?course=' + instance.courseid + '&id='),
                        statushelp: statushelp,
                        absregs: absregs,
                        gtregs: gtregs,
                        mregs: mregs,
                        queued: queued
                    };

                    Templates.render('mod_grouptool/groupmembers', context)
                        .then(function(source) {
                            instance.modal.setTitle(name);
                            instance.modal.setBody(source);
                            instance.modal.show();

                            return source;
                        })
                        .catch(function(ex) {
                            Log.error('Error rendering groupmembers template: ' + ex, 'mod_grouptool');

                            if (ex && ex.message) {
                                instance.modal.setBody(ex.message);
                            } else {
                                instance.modal.setBody(String(ex));
                            }

                            instance.modal.show();
                        });
                });

                return modal;
            }).catch(function(ex) {
                Log.error('Error preparing modal: ' + ex, 'mod_grouptool');
            });
        }).fail(function(ex) {
            Log.error('Error getting strings: ' + ex, 'mod_grouptool');
        });
    };

    return instance;
});