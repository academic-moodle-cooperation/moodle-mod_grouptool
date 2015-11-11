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
define(['jquery', 'core/yui', 'core/config', 'core/str', 'core/url', 'core/log'],
       function($, Y, config, str, murl, log) {

    /**
     * @constructor
     * @alias module:mod_grouptool/memberspopup
     */
    var Memberspopup = function() {
        this.SELECTORS = {
            CLICKABLELINKS: 'span.memberstooltip > a',
            FOOTER: 'div.moodle-dialogue-ft'
        };

        /** @access private */
        this.contextid = 0;

        this.panel = null;

        this.strings = {loading: 'Loading...'};
    };

    Memberspopup.prototype.showMembers = function(e) {

        // Get group id!
        /*
         * In the near future we will change to a data-attribute driven
         * AJAX call and use a hopefully soon to come moodle AMD module
         * allowing us to call the popup without YUI in a standard way.
         *
         * Until then we're wrapping some YUI and start transitioning
         * Moodle-style (see also lib/amd/notification.js)
         *
         * TODO: rewrite with proper moodle/jquery modules ASAP
         * var grpid = e.target.data('groupId');
         * var admin = e.data.admin;
         * var strings = e.data.strings;
         */
        var loading = this.strings.loading;
        // Here we are wrapping YUI. This allows us to start transitioning, but
        // wait for a good alternative without having inconsistent dialogues.
        Y.use('moodle-core-tooltip', function () {
            if (!this.panel) {
                this.panel = new M.core.tooltip({
                    bodyhandler: this.set_body_content,
                    footerhandler: this.set_footer,
                    initialheadertext: loading,
                    initialfootertext: ''
                });
            }

            // Call the tooltip setup.
            this.panel.display_panel(e);
        });
    };

    var instance = new Memberspopup();

    instance.initializer = function(params) {

        instance.contextid = params.contextid;

        str.get_string('loading', 'grouptool').done(function (s) {
            instance.strings = {loading: s};
        }).fail(function (e) {
            log.error('Error while retrieving strings: ' + e, "grouptool");
        });

        /*
         * Just another hint for the future implementation:
         *
         * $(instance.SELECTORS.CLICKABLELINKS).on('click', null, {strings: strings, admin: this}, instance.display_panel);
         */
        Y.one('body').delegate('click', instance.showMembers, instance.SELECTORS.CLICKABLELINKS, instance);
    };

    return instance;
});
