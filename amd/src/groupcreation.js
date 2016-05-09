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
 * groupcreation.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            FIELDSETCONTAINSADVANCED : 'fieldset.containsadvancedelements',
            DIVFITEMADVANCED : 'div.fitem.advanced',
            DIVFCONTAINER : 'div.fcontainer',
            MORELESSLINK : 'fieldset.containsadvancedelements .moreless-toggler',
            MORELESSLINKONLY : '.moreless-toggler',
            MODEINPUT: 'input[name=mode]:checked'
        };
        this.CSS = {
            SHOW : 'show',
            MORELESSACTIONS: 'moreless-actions',
            MORELESSTOGGLER : 'moreless-toggler',
            SHOWLESS : 'moreless-less'
        };
        this.fromtomode = -1;
    };

    Groupcreation.prototype.add_tag = function(e) {
        log.info('Add tag...', 'grouptool');

        var targetfield = $('input[name=namingscheme]');

        e.preventDefault();

        var nodeclass = $(e.target).attr('class');
        var classes = nodeclass.split(' ');

        var tag = '';

        for(var i = 0; i < classes.length; i++) {
            if (classes[i] !== 'tag') {
                if (classes[i] === 'number') {
                    tag = '#';
                } else if (classes[i] === 'alpha') {
                    tag = '@';
                } else {
                    tag = '[' + classes[i] + ']';
                }
            }
        }
        var content = targetfield.val();
        targetfield.val(content + tag);
        targetfield.attr('value', content + tag);
        targetfield.attr('defaultValue', content + tag);
    };

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
                }).fail( function(e) {
                    log.error('Failed getting string showless from form!' + e, 'grouptool');
                });
                morelesslink.addClass(e.data.CSS.SHOWLESS);
            }
        }
    };

    var instance = new Groupcreation();

    instance.initializer = function(params) {
        this.fromtomode = params.fromtomode;

        log.info('Initialise grouptool group creation js...', 'grouptool');
        // Add JS-Eventhandler for each tag!
        $('.tag').on('click', null, this, this.add_tag);
        $('.tag').css('cursor', 'pointer');

        $('input[name="mode"]').on('change', null, this, this.modechange);
    };

    return instance;
});