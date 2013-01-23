// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
YUI.add('moodle-mod_grouptool-administration', function(Y) {
    var ADMINISTRATIONNAME = 'administration';
    var administration = function(Y) {
        administration.superclass.constructor.apply(this, arguments);
    }
    Y.extend(administration, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            //gets called when it's going to be pluged in
        }

    }, {
        NAME : ADMINISTRATIONNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
                 aparam : {}
        } //Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          //Here you can declare default values or run functions on the parameter.
          //The param names must be the same as the ones declared
          //in the $PAGE->requires->yui_module call.

    });
    //this line use existing name path if it exists, ortherwise create a new one.
    //This is to avoid to overwrite previously loaded module with same name.
    M.mod_grouptool = M.mod_grouptool || {};

    M.mod_grouptool.administration_add_tag = function(e) {
        var targetfield = Y.one('input[name=namingscheme]');

        e.preventDefault();

        var nodeclass = e.target.getAttribute('class');
        var classes = nodeclass.split(' ');

        for(var i = 0; i < classes.length; i++) {
            if(classes[i] != 'tag') {
                if(classes[i] == 'number') {
                    tag = '#';
                } else if(classes[i] == 'alpha') {
                    tag = '@';
                } else {
                    tag = '['+classes[i]+']';
                }
            }
        }
        var content = targetfield.get('value');
        targetfield.set('value', content+tag);
        targetfield.set('defaultValue', content+tag);
    };

    M.mod_grouptool.administration_sizevalupdate = function() {
        if(Y.one('input[name=use_individual]').get('value') == 0) {
            Y.all('.grpsize').one('input').set('value', Y.one('input[name=grpsize]').get('value'));
        }
    };

    M.mod_grouptool.administration_sizevisupdate = function() {
        if((Y.one('input[name=use_size]').get('value') == 1)
              && (Y.one('input[name=use_individual]').get('value') == 1)) {
            Y.all('.grpsize').setStyle('display', 'block');
        } else {
            Y.all('.grpsize').setStyle('display', 'none');
        }
    };

    //'config' contains the parameter values
    M.mod_grouptool.init_administration = function(config) {

        //add JS-Eventhandler for each tag
        Y.all('.tag').on('click', M.mod_grouptool.administration_add_tag);

        //add JS-Eventhandlers to hide individual groupsize-fields
        //if use_size or use_individual are false
        Y.one('input[name=use_size]').on('change', M.mod_grouptool.administration_sizevisupdate);
        Y.one('input[name=use_individual]').on('change',
                                               M.mod_grouptool.administration_sizevisupdate);
        //add JS-Eventhandler to let groupsize-fields follow global-grpsize changes
        Y.one('input[name=grpsize]').on('change', M.mod_grouptool.administration_sizevalupdate);
        Y.one('input[name=use_individual]').on('change',
                                               M.mod_grouptool.administration_sizevalupdate);

        return new administration(config); //'config' contains the parameter values
    };
    //end of M.mod_grouptool.init_administration

  }, '0.0.1', {
      requires:['base','dd-constrain', 'dd-proxy', 'dd-drop', 'dd-scroll']
  });