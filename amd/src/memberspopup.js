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
 * memberspopup.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 /**
  * @module mod_grouptool/memberspopup
  */
define(['jquery', 'core/modal_factory', 'core/templates', 'core/url', 'core/str', 'core/log'], function($, ModalFactory, templates,
                                                                                                        url, str, log) {

    /**
     * @constructor
     * @alias module:mod_grouptool/memberspopup
     */
    var Memberspopup = function() {
        this.showidnumber = false;
        this.courseid = '';
    };

    var instance = new Memberspopup();

    instance.initializer = function(config) {

        instance.showidnumber = config.showidnumber;
        instance.courseid = config.courseid;

        log.info('Initialize groupmembers JS!', 'mod_grouptool');

        if (!instance.modal) {
            instance.modalpromise = ModalFactory.create({
                type: ModalFactory.types.MODAL,
                body: '...'
            });
        }

        str.get_string('groupmembers').done(function(s) {
            log.info('Done loading strings...', 'mod_grouptool');
            instance.modalpromise.done(function(modal) {
                log.info('Done preparing modal...', 'mod_grouptool');
                instance.modal = modal;
                $('#registration_form').on('click', 'span.memberstooltip > a', null, function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var element = $( e.target );

                    var statushelp = element.parents('form').data('statushelp');

                    var absregs;
                    try {
                        absregs = element.data('absregs');
                    } catch (ex) {
                        absregs = [];
                    }

                    var gtregs;
                    try {
                        gtregs = element.data('gtregs');
                    } catch (ex) {
                        gtregs = [];
                    }

                    var mregs;
                    try {
                        mregs = element.data('mregs');
                    } catch (ex) {
                        mregs = [];
                    }

                    var queued;
                    try {
                        queued = element.data('queued');
                    } catch (ex) {
                        queued = [];
                    }

                    var name;
                    try {
                        name = s + ': ' + element.data('name');
                    } catch (ex) {
                        name = s;
                    }

                    var context = {
                        courseid: instance.courseid,
                        showidnumber: instance.showidnumber,
                        profileurl: url.relativeUrl("/user/view.php?course=" + instance.courseid + "&id="),
                        statushelp: statushelp,
                        absregs: absregs,
                        gtregs: gtregs,
                        mregs: mregs,
                        queued: queued
                    };

                    // This will call the function to load and render our template.
                    var promise = templates.render('mod_grouptool/groupmembers', context);

                    // How we deal with promise objects is by adding callbacks.
                    promise.done(function(source) {
                        // Here eventually I have my compiled template, and any javascript that it generated.
                        instance.modal.setTitle(name);
                        instance.modal.setBody(source);
                        instance.modal.show();
                    }).fail(function(ex) {
                        // Deal with this exception (I recommend core/notify exception function for this).
                        instance.modal.setBody(ex.message);
                        instance.modal.show();
                    });
                });
            });
        }).fail(function(ex) {
            log.error("Error getting strings: " + ex, "mod_grouptool");
        });
    };

    return instance;
});
