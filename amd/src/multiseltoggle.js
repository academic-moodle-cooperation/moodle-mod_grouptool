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
 * Javascript for toggling between multi-selects and single-selects
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2018 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * @module mod_grouptool/multiseltoggle
  */
define(['jquery', 'core/str', 'core/log'], function($, str, log) {

    var PLUS = 'fa-plus'; // Alternatives: 'fa-plus-square' or 'fa-plus-square-o'!
    var MINUS = 'fa-minus'; // Alternatives: 'fa-minus-square' or 'fa-minus-square-o'!
    var COLOR = 'text-primary'; // Alternatives: 'text-info', 'text-muted', '', etc. (Every bootstrap text-color-class)!

    /**
     * @constructor
     * @alias module:mod_grouptool/multiseltoggle
     */
    var MultiSelToggle = function() {
        this.selectmultiple = '';
        this.selectsingle = '';
        this.el = {};
    };

    /**
     * Change the select field from multi select to single select or vice versa!
     *
     * @param {Event} e Event object
     */
    MultiSelToggle.prototype.toggle = function(e) {
        var x = e.currentTarget;
        if (instance.el.multiple) {
            // Remove multiple and set icon classes to [+]!
            instance.el.multiple = false;
            x.classList.replace(MINUS, PLUS);
            x.title = instance.selectmultiple;
        } else {
            // Add multiple and set icon classes to [-]!
            instance.el.multiple = true;
            x.classList.replace(PLUS, MINUS);
            x.title = instance.selectsingle;
        }
    };

    var instance = new MultiSelToggle();

    /**
     * Initializer
     *
     * @param {string|element} el Element selector to enable switch for.
     */
    instance.enable = function(el) {
        instance.el = el;
        var stringstofetch = [
            {'key': 'selectmultiple', 'component': 'mod_grouptool'},
            {'key': 'selectsingle', 'component': 'mod_grouptool'}
        ];
        str.get_strings(stringstofetch).done(function(s) {
            instance.selectmultiple = s[0];
            instance.selectsingle = s[1];
            log.info('Enable multiselect/singleselect switch for element (' + instance.el + ').', 'grouptool');
            if (typeof instance.el === 'string') {
                log.info('Get element by selector (' + instance.el + ')', 'grouptool');
                instance.el = document.querySelector(instance.el);
            }
            if (instance.el !== null && typeof instance.el === 'object') {
                var icon = document.createElement('i');
                icon.classList.add('fa');
                icon.classList.add('p-r-1');
                icon.classList.add(COLOR);
                if (instance.el.multiple) {
                    icon.title = instance.selectsingle;
                    icon.classList.add(MINUS);
                } else {
                    icon.title = instance.selectmultiple;
                    icon.classList.add(PLUS);
                }
                instance.el.parentNode.insertBefore(icon, instance.el.nextSibling);
                icon.style.cursor = 'pointer';
                icon.style.height = '100%';
                icon.style.paddingLeft = '2px';
                icon.addEventListener("click", instance.toggle);
            } else {
                log.error('Element to enable multiselect/singleselect switch was not found!', 'grouptool');
            }
        });
    };

    return instance;
});
