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
// If not, see <http://www.gnu.org/licenses/>.

/**
 * enhancements.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
YUI.add('moodle-mod_grouptool-enhancements', function(Y) {
    var ENHANCEMENTSNAME = 'enhancements';
    var enhancements = function(Y) {
        enhancements.superclass.constructor.apply(this, arguments);
    }
    Y.extend(enhancements, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            //gets called when it's going to be pluged in
        }

    }, {
        NAME : ENHANCEMENTSNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
                 aparam : {}
        } // Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          // Here you can declare default values or run functions on the parameter.
          // The param names must be the same as the ones declared
          // in the $PAGE->requires->yui_module call.

    });
    //this line use existing name path if it exists, ortherwise create a new one.
    //This is to avoid to overwrite previously loaded module with same name.
    M.mod_grouptool = M.mod_grouptool || {};

    M.mod_grouptool.enhancements_sizevalupdate = function() {
        if(Y.one('input[name=use_individual]').get('checked') != 1) {
            Y.all('.grpsize input').set('value', Y.one('input[name=grpsize]').get('value'));
        }
    };

    M.mod_grouptool.enhancements_sizevisupdate = function() {
        if ((Y.one('input[name=use_size]').get('checked') == 1)
               && (Y.one('input[name=use_individual]').get('checked') == 1)) {
            Y.all('.grpsize').setStyle('display', this.stddisplay);
        } else {
           Y.all('.grpsize').setStyle('display', 'none');
        }
    };

    M.mod_grouptool.init_enhancements = function(config) { //'config' contains the parameter values

        //add JS-Eventhandlers to hide individual groupsize-fields if use_size
        //or use_individual are false
        Y.one('input[name=use_size]').on('change', M.mod_grouptool.enhancements_sizevisupdate);
        Y.one('input[name=use_individual]').on('change', M.mod_grouptool.enhancements_sizevisupdate);
        //add JS-Eventhandler to let groupsize-fields follow global-grpsize changes
        Y.one('input[name=grpsize]').on('change', M.mod_grouptool.enhancements_sizevalupdate);
        Y.one('input[name=use_individual]').on('change', M.mod_grouptool.enhancements_sizevalupdate);

        //save std table-cell-display-property
        this.stddisplay = Y.one('.grpsize').getStyle('display');

        M.mod_grouptool.enhancements_sizevisupdate();

        return new enhancements(config); //'config' contains the parameter values
    };
    //end of M.mod_grouptool.init_enhancements

  }, '0.0.1', {
      requires:['base']
  });