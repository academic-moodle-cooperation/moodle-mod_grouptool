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
 * administration.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-mod_grouptool-administration', function(Y) {
    var ADMINISTRATIONNAME = 'moodle-mod_grouptool-administration';
    var fromto_mode = {};
    var administration = function(Y) {
        administration.superclass.constructor.apply(this, arguments);
    }

    var SELECTORS = {
            FIELDSETCONTAINSADVANCED : 'fieldset.containsadvancedelements',
            DIVFITEMADVANCED : 'div.fitem.advanced',
            DIVFCONTAINER : 'div.fcontainer',
            MORELESSLINK : 'fieldset.containsadvancedelements .moreless-toggler',
            MORELESSLINKONLY : '.moreless-toggler'
        },
        CSS = {
            SHOW : 'show',
            MORELESSACTIONS: 'moreless-actions',
            MORELESSTOGGLER : 'moreless-toggler',
            SHOWLESS : 'moreless-less'
        },
        WRAPPERS = {
            FITEM : '<div class="fitem"></div>',
            FELEMENT : '<div class="felement"></div>'
        },
        ATTRS = {
            contextid : { 'value' : 0 },
            lang : { 'value' : 'en' },
            globalsize : { 'value' : 3 },
        };

    //this line use existing name path if it exists, ortherwise create a new one.
    //This is to avoid to overwrite previously loaded module with same name.
    M.mod_grouptool = M.mod_grouptool || {};

    Y.extend(administration, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            M.mod_grouptool.contextid = config.contextid;
            M.mod_grouptool.lang = config.lang;
            M.mod_grouptool.filter = config.filter;
            M.mod_grouptool.filterid = config.filterid;
            M.mod_grouptool.globalsize = config.globalsize;
            Y.log('Initalize Grouptool group administration', "info",  "grouptool");
            Y.all('a.renamebutton').on('click', M.mod_grouptool.renamegroup);
            Y.all('a.resizebutton').on('click', M.mod_grouptool.resizegroup);
            Y.all('a.deletebutton').on('click', M.mod_grouptool.deletegroup);
            Y.all('a.togglebutton').on('click', M.mod_grouptool.togglegroup);

        }

    }, {
        NAME : ADMINISTRATIONNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
                 fromto_mode : {}
        } //Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          //Here you can declare default values or run functions on the parameter.
          //The param names must be the same as the ones declared
          //in the $PAGE->requires->yui_module call.

    });

    M.mod_grouptool.renamegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        var grpid = e.target.getAttribute('name').replace ( /[^\d]/g, '' );
        e.target = e.target.ancestor('a');

        var field = e.target.previous('input[type=hidden]');
        var text = e.target.previous('span.text');
        var button = e.target;
        var infoNode = '';
        text.hide('fadeOut');
        button.hide('fadeOut');
        field.hide('fadeOut');
        field.setAttribute('type', 'text');
        field.show('fadeIn');
        field.focus();
        field.select();
        field.on('key', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // TODO start AJAX Call and process Response!
            var url = M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php";
            var contextid = M.mod_grouptool.contextid;
            var lang = M.mod_grouptool.lang;
            var cfg = {
                method: 'POST',
                data: 'action=rename&groupid=' + grpid + '&name=' + field.get('value') + '&sesskey=' + M.cfg.sesskey +
                      '&contextid=' + contextid,
                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                on: {
                    start: function(id, args) {
                        if (infoNode) {
                            infoNode.hide('fadeOut');
                            infoNode.remove();
                        }
                        Y.log("Start AJAX Call to rename group " + grpid, "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to rename group " + grpid + " completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            var tmpnode = Y.Node.create("<div class=\"infonode alert-error\" style=\"display:none\">" +
                                          response.error + "</div>");
                            infoNode = text.insertBefore(tmpnode, text);
                            infoNode.show('fadeIn');
                            // Remove after 60 seconds automatically!
                            Y.later(60 * 1000, infoNode, function() {
                                if (infoNode) {
                                    infoNode.hide('fadeOut');
                                    infoNode.remove();
                                }
                            });
                        } else {
                            var tmpnode = Y.Node.create("<div class=\"infonode alert-success\" style=\"display:none\">" +
                                          response.message + "</div>");
                            infoNode = text.insertBefore(tmpnode, text);
                            infoNode.show('fadeIn');
                            text.setHTML(field.get('value'));
                            field.hide('fadeOut');
                            field.setAttribute('value', field.get('value'));
                            field.setAttribute('type', 'hidden');
                            text.show('fadeIn');
                            button.show('fadeIn');
                            field.detach();
                            Y.later(5 * 1000, infoNode, function() {
                                infoNode.hide('fadeOut');
                                infoNode.remove();
                            });
                        }
                        Y.log("AJAX Call to rename group " + grpid + " successfull", "success", "grouptool");
                    },
                    failure: function(id, o, args) {
                        // Show message
                        response = Y.JSON.parse(o.responseText);
                        var tmpnode = Y.Node.create("<div class=\"infonode alert-error\" style=\"display:none\">" +
                                      response.message + "</div>");
                        infoNode = text.insertBefore(tmpnode, text);
                        infoNode.show('fadeIn');
                        Y.log("AJAX Call to rename group " + grpid + " failure", "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to rename group " + grpid + " ended", "info", "grouptool");
                    }
                },
                timeout: 60000,
            };

            Y.io(url, cfg);

        }, 'enter');
        field.on('key', function(e) {
            e.preventDefault();
            e.stopPropagation();

            field.hide('fadeOut');
            text.hide();
            field.setAttribute('type', 'hidden');
            field.setAttribute('value', text.getHTML());
            field.set('value', text.getHTML());
            text.show('fadeIn');
            button.show('fadeIn');
            if (infoNode) {
                infoNode.hide('fadeOut');
                infoNode.remove();
            }
            field.detach();
        }, 'esc');
    };

    M.mod_grouptool.resizegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        var grpid = e.target.getAttribute('name').replace ( /[^\d]/g, '' );
        e.target = e.target.ancestor('a');

        var field = e.target.previous('input[type=hidden]');
        var text = e.target.previous('span.text');
        var button = e.target;
        var infoNode = '';
        text.hide('fadeOut');
        button.hide('fadeOut');
        field.hide('fadeOut');
        field.setAttribute('type', 'text');
        var tmpnode = Y.Node.create("<div class=\"infonode alert-info\" style=\"display:none\">" +
                                    M.util.get_string('ajax_edit_size_help', 'mod_grouptool') + "</div>");
        var helpNode = text.insertBefore(tmpnode, text);
        helpNode.show('fadeIn');
        field.show('fadeIn');
        field.focus();
        field.select();
        field.on('key', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // TODO start AJAX Call and process Response!
            var url = M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php";
            var contextid = M.mod_grouptool.contextid;
            var lang = M.mod_grouptool.lang;
            var cfg = {
                method: 'POST',
                data: 'action=resize&groupid=' + grpid + '&size=' + field.get('value') + '&sesskey=' + M.cfg.sesskey +
                      '&contextid=' + contextid,
                headers: { 'X-Transaction': 'POST resize group ' + grpid},
                on: {
                    start: function(id, args) {
                        if (infoNode) {
                            infoNode.hide('fadeOut');
                            infoNode.remove();
                        }
                        if (helpNode) {
                            helpNode.hide('fadeOut');
                            helpNode.remove();
                        }
                        Y.log("Start AJAX Call to resize group " + grpid, "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to resize group " + grpid + " completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            var tmpnode = Y.Node.create("<div class=\"infonode alert-error\" style=\"display:none\">" +
                                                        response.error + "</div>");
                            infoNode = text.insertBefore(tmpnode, text);
                            infoNode.show('fadeIn');
                            // Remove after 60 seconds automatically!
                            Y.later(60 * 1000, infoNode, function() {
                                if (infoNode) {
                                    infoNode.hide('fadeOut');
                                    infoNode.remove();
                                }
                            });
                        } else {
                            var tmpnode = Y.Node.create("<div class=\"infonode alert-success\" style=\"display:none\">" +
                                                        response.message + "</div>");
                            infoNode = text.insertBefore(tmpnode, text);
                            infoNode.show('fadeIn');
                            var newvalue = field.get('value');
                            if (newvalue == '') {
                                text.setHTML(M.mod_grouptool.globalsize + '*');
                            } else {
                                text.setHTML(newvalue);
                            }
                            field.hide('fadeOut');
                            field.setAttribute('value', field.get('value'));
                            field.setAttribute('type', 'hidden');
                            text.show('fadeIn');
                            button.show('fadeIn');
                            field.detach();
                            Y.later(5 * 1000, infoNode, function() {
                                infoNode.hide('fadeOut');
                                infoNode.remove();
                            });
                        }
                        Y.log("AJAX Call to resize group " + grpid + " successfull", "success", "grouptool");
                    },
                    failure: function(id, o, args) {
                        // Show message
                        response = Y.JSON.parse(o.responseText);
                        infoNode = text.insertBefore(Y.Node.create("<div class=\"infonode alert-error\" style=\"display:none\">" +
                                                                   response.message + "</div>"), text);
                            infoNode.show('fadeIn');
                        Y.log("AJAX Call to resize group " + grpid + " failure", "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to resize group " + grpid + " ended", "info", "grouptool");
                    }
                },
                timeout: 60000,
            };

            Y.io(url, cfg);

        }, 'enter');
        field.on('key', function(e) {
            e.preventDefault();
            e.stopPropagation();

            field.hide('fadeOut');
            text.hide();
            field.setAttribute('type', 'hidden');
            field.setAttribute('value', text.getHTML());
            field.set('value', text.getHTML());
            text.show('fadeIn');
            button.show('fadeIn');
            if (infoNode) {
                infoNode.hide('fadeOut');
                infoNode.remove();
            }
            if (helpNode) {
                helpNode.hide('fadeOut');
                helpNode.remove();
            }
            field.detach();
        }, 'esc');
    }

    M.mod_grouptool.togglegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();
        Y.log('TOGGLE GROUP ' + grpid, "info", "grouptool");
        var grpid = e.target.getAttribute('name').replace ( /[^\d]/g, '' );
        if (e.target.hasClass('active')) {
            // Set inactive (via AJAX Request)!
            Y.log('DEACTIVATE GROUP ' + grpid + '!', "info", "grouptool");

            var url = M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php";
            var contextid = M.mod_grouptool.contextid;
            var lang = M.mod_grouptool.lang;
            Y.log(url + '?action=deactivate&groupid=' + grpid + '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid +
                      '&filter=' + M.mod_grouptool.filterid, "info", "grouptool");
            var cfg = {
                method: 'POST',
                data: 'action=deactivate&groupid=' + grpid + '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid +
                      '&filter=' + M.mod_grouptool.filterid,
                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                on: {
                    start: function(id, args) {
                        Y.log("Start AJAX Call to deactivate group " + grpid + "\n" +
                              url + "?action=deactivate&groupid=" + grpid + "&sesskey=" + M.cfg.sesskey + "&contextid=" + contextid,
                              "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to deactivate group " + grpid + " completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            Y.log("AJAX Call to deactivate group " + grpid + " successfull but error occured:\n" + response.error,
                                  "success", "grouptool");
                        } else {
                            if (M.mod_grouptool.filter == 'active') {
                                e.target.ancestor('tr').hide('fadeOut', null, function(){
                                    e.target.ancestor('tr').remove();
                                    if (response.noentriesmessage != '') {
                                        Y.one('div.sortlist_container').hide('fadeOut', null, function() {
                                            Y.one('div.sortlist_container').setHTML(response.noentriesmessage);
                                            Y.one('div.sortlist_container').show('fadeIn');
                                        });
                                    }
                                });
                            } else {
                                // Else set URL and alt of new image!
                                e.target.ancestor('tr').hide('fadeOut', null, function() {
                                        e.target.ancestor('tr').addClass('dimmed_text');
                                        e.target.replaceClass('active', 'inactive');
                                        e.target.setAttribute('src', M.util.image_url('t/stop'));
                                        e.target.setAttribute('alt', M.util.get_string('inactive', 'mod_grouptool'));
                                        e.target.ancestor('tr').show('fadeIn')});
                                // And add class dimmed_text to row!
                            }
                            Y.log("AJAX Call to deactivate group " + grpid + " successfull\n" + response.message,
                                  "success", "grouptool");
                        }
                    },
                    failure: function(id, o, args) {
                        // Show message
                        Y.log("AJAX Call to deactivate group " + grpid + " failure", "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to deactivate group " + grpid + " ended", "info", "grouptool");
                    }
                },
                timeout: 60000,
            };

            Y.io(url, cfg);
        } else if (e.target.hasClass('inactive')) {
            // Set active!

            // Set active (via AJAX Request)!
            Y.log('ACTIVATE GROUP ' + grpid + '!', "info", "grouptool");

            var url = M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php";
            var contextid = M.mod_grouptool.contextid;
            var lang = M.mod_grouptool.lang;
            Y.log(url + '?action=deactivate&groupid=' + grpid + '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid +
                      '&filter=' + M.mod_grouptool.filterid, "info", "grouptool");
            var cfg = {
                method: 'POST',
                data: 'action=activate&groupid=' + grpid + '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid +
                      '&filter=' + M.mod_grouptool.filterid,
                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                on: {
                    start: function(id, args) {
                        Y.log("Start AJAX Call to activate group " + grpid + "\n" +
                              url + '?action=deactivate&groupid=' + grpid + '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid,
                              "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to activate group " + grpid + " completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            Y.log("AJAX Call to activate group " + grpid + " successfull but error occured:\n" + response.error,
                                  "success", "grouptool");
                        } else {
                            if (M.mod_grouptool.filter == 'inactive') {
                                // If showing only active remove from list!
                                e.target.ancestor('tr').hide('fadeOut', null, function() {
                                    e.target.ancestor('tr').remove();
                                    if (response.noentriesmessage != '') {
                                        Y.one('div.sortlist_container').hide('fadeOut', null, function() {
                                            Y.one('div.sortlist_container').setHTML(response.noentriesmessage);
                                            Y.one('div.sortlist_container').show('fadeIn');
                                        });
                                    }
                                });
                            } else {
                                // Else set URL and alt of new image!
                                e.target.ancestor('tr').hide('fadeOut', null, function() {
                                        e.target.ancestor('tr').removeClass('dimmed_text');
                                        e.target.replaceClass('inactive', 'active');
                                        e.target.setAttribute('src', M.util.image_url('t/go'));
                                        e.target.setAttribute('alt', M.util.get_string('active', 'mod_grouptool'));
                                        // TODO move node in list to correct position?
                                        e.target.ancestor('tr').show('fadeIn')});
                            }
                            Y.log("AJAX Call to activate group " + grpid + " successfull\n" + response.message,
                                  "success", "grouptool");
                        }
                    },
                    failure: function(id, o, args) {
                        // Show message
                        Y.log("AJAX Call to activate group " + grpid + " failure", "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to activate group " + grpid + " ended", "info", "grouptool");
                    }
                },
                timeout: 60000,
            };

            Y.io(url, cfg);
        } else {
            // Error!
            Y.log('Group with id ' + grpid + ' must have either class "active" or "inactive"!', "error", "grouptool");
        }
    }

    M.mod_grouptool.deletegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        var grpid = e.target.getAttribute('name').replace ( /[^\d]/g, '' );
        M.util.show_confirm_dialog(e, {'message':       M.util.get_string('confirm_delete', 'mod_grouptool'),
                                       'continuelabel': M.util.get_string('yes', 'moodle'),
                                       'cancellabel':   M.util.get_string('no', 'moodle'),
                                       'callbackargs':  [grpid],
                                       'callback':      function(grpid) {
                                                            if (!grpid) { Y.log('No Group ID!', 'info', 'grouptool'); return; }

                                                            Y.log('DELTE GROUP ' + grpid + '!', "info", "grouptool");

                                                            var infoNode = '';
                                                            var url = M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php";
                                                            var contextid = M.mod_grouptool.contextid;
                                                            var lang = M.mod_grouptool.lang;
                                                            var cfg = {
                                                                method: 'POST',
                                                                data: 'action=delete&groupid=' + grpid +
                                                                      '&sesskey=' + M.cfg.sesskey + '&contextid=' + contextid,
                                                                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                                                                on: {
                                                                    start: function(id, args) {
                                                                        Y.log("Start AJAX Call to delete group " + grpid,
                                                                              "info", "grouptool");
                                                                    },
                                                                    complete: function(id, args) {
                                                                        Y.log("AJAX Call to delete group " + grpid + " completed",
                                                                              "info", "grouptool");
                                                                    },
                                                                    success: function(id, o, args) {
                                                                        response = Y.JSON.parse(o.responseText);
                                                                        if (!response.error) {
                                                                            // Success, remove the corresponding table entry...
                                                                            Y.one('#delete_' + grpid).ancestor('tr').hide('fadeOut');
                                                                            Y.one('#delete_' + grpid).ancestor('tr').remove();
                                                                        }
                                                                        Y.log("AJAX Call to delete group " + grpid + " successfull",
                                                                              "success", "grouptool");
                                                                    },
                                                                    failure: function(id, o, args) {
                                                                        // Show message
                                                                        Y.log("AJAX Call to rename group " + grpid + " failure",
                                                                              "error", "grouptool");
                                                                    },
                                                                    end: function(id, args) {
                                                                        Y.log("AJAX Call to rename group " + grpid + " ended",
                                                                              "info", "grouptool");
                                                                    }
                                                                },
                                                                timeout: 60000,
                                                            };

                                                            Y.io(url, cfg);
                                                    },});
    }

    //'config' contains the parameter values
    M.mod_grouptool.init_administration = function(params) {
        return new administration(params); //'params' contains the parameter values
    };
    //end of M.mod_grouptool.init_administration

}, '0.0.1', {
    requires:['base', 'node', 'event-key', 'transition', 'anim']
});
