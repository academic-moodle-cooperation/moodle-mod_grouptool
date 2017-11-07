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
 * JS handling insertion of tags for group names and displaying advanced elements if changed group creation mode requires some
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_grouptool/groupcreation
 */
define(['jquery', 'core/config', 'core/str', 'core/log'], function($, config, str, log) {
    /**
     * @contructor
     * @alias module:mod_grouptool/groupcreation
     */
    var Groupcreation = function() {
        this.SELECTORS = {
            FIELDSETCONTAINSADVANCED: 'fieldset.containsadvancedelements',
            DIVFITEMADVANCED: 'div.fitem.advanced',
            DIVFCONTAINER: 'div.fcontainer',
            MORELESSLINK: 'fieldset.containsadvancedelements .moreless-toggler',
            MORELESSLINKONLY: '.moreless-toggler',
            MODEINPUT: 'input[name=mode]:checked'
        };
        this.CSS = {
            SHOW: 'show',
            MORELESSACTIONS: 'moreless-actions',
            MORELESSTOGGLER: 'moreless-toggler',
            SHOWLESS: 'moreless-less'
        };
    };

    /**
     * Adds a tag
     *
     * @param {Event} e Event object
     */
    Groupcreation.prototype.addTag = function(e) {
        log.info('Add tag...', 'grouptool');

        var targetfield = $('input[name=namingscheme]');

        e.preventDefault();

        var node = $(e.target);

        var tag = node.data('nametag');

        var content = targetfield.val();
        var caretPos = targetfield[0].selectionStart;

        targetfield.val(content.substring(0, caretPos) + tag + content.substring(caretPos));

        // And now restore focus and caret position!
        targetfield.focus();
        var postpos = caretPos + tag.length;
        targetfield[0].selectionStart = postpos;
        targetfield[0].selectionEnd = postpos;
    };

    /**
     * Makes advanced fields visible according to certain mode changes
     *
     * @param e
     */
    Groupcreation.prototype.modechange = function(e) {
        log.info('Modechange!', 'grouptool');
        e.target = $(e.target);
        var fieldset = e.target.closest(e.data.SELECTORS.FIELDSETCONTAINSADVANCED);

        var modevalue = $(e.data.SELECTORS.MODEINPUT).val();
        if ((parseInt(modevalue) === parseInt(e.data.fromtomode))
            && !fieldset.find(e.data.SELECTORS.DIVFITEMADVANCED).hasClass(e.data.CSS.SHOW)) {
            log.info('Make advanced fields visible!', 'grouptool');
            // Toggle collapsed class.
            $(e.data.SELECTORS.DIVFITEMADVANCED).toggleClass(e.data.CSS.SHOW);
            var morelesslink = $(e.data.SELECTORS.MORELESSLINKONLY);

            // Get corresponding hidden variable.
            var statuselement = $('input[name=mform_showmore_' + fieldset.get('id') + ']');
            // Invert it and change the link text.
            if (statuselement.val() === '0') {
                statuselement.val(1);
                str.get_string('showless', 'form').done(function(s) {
                    morelesslink.html(s);
                }).fail(function(e) {
                    log.error('Failed getting string showless from form!' + e, 'grouptool');
                });
                morelesslink.addClass(e.data.CSS.SHOWLESS);
            }
        }
    };

    var instance = new Groupcreation();

    /**
     * AMD initializer
     *
     * @param {object} params
     */
    instance.initializer = function(params) {
        this.fromtomode = params.fromtomode;

        log.info('Initialise grouptool group creation js...', 'grouptool');
        // Add JS-Eventhandler for each tag!
        var nametag = $('[data-nametag]');
        nametag.on('click', null, this, this.addTag);
        nametag.css('cursor', 'pointer');

        $('input[name="mode"]').on('change', null, this, this.modechange);
    };

    return instance;
});
