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
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * @module mod_grouptool/administration
  */
define(['jquery', 'core/config', 'core/str', 'core/url', 'core/notification', 'core/log'],
       function($, config, str, murl, notif, log) {

    /**
     * @constructor
     * @alias module:mod_grouptool/administration
     */
    var Administration = function() {
        /** @access private */
        this.contextid = 0;
        /** @access private */
        this.lang = 'en';
        /** @access private */
        this.filter = null;
        /** @access private */
        this.filterid = null;
        /** @access private */
        this.globalsize = 3;
    };

     /**
      * Rename 1 moodle group via AJAX request.
      * @access private
      * @return void
      */
    Administration.prototype.renamegroup = function(e) {
        log.info('Rename Group!', 'grouptool');
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        e.target = $(e.target);
        var grpid = e.target.attr('name').replace ( /[^\d]/g, '' );

        var button = e.target.closest('a');
        var field = button.prevAll('input[type=hidden]');
        var text = button.prevAll('span.text');
        var infoNode = '';
        button.fadeOut(600);
        field.fadeOut(600);
        text.fadeOut(600, function() {
            field.attr('type', 'text');
            field.fadeIn(600);
            field.focus();
            field.select();
        });
        field.on('keydown', function(e) {
            if (e.which === 13 || e.which === 27) {
                e.preventDefault();
                e.stopPropagation();
            }

            if (e.which === 13) { // Enter
                var cfg = {
                    method: 'POST',
                    url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                    data: {
                            'action': 'rename',
                            'groupid': grpid,
                            'name': field.val(),
                            'sesskey': config.sesskey,
                            'contextid': e.data.contextid
                           },
                    headers: { 'X-Transaction': 'POST rename group ' + grpid},
                    dataType: 'json',
                    beforeSend: function() {
                            if (infoNode) {
                                infoNode.fadeOut(600).delay(600).remove();
                            }
                            log.info("Start AJAX Call to rename group " + grpid, "grouptool");
                        },
                    complete: function() {
                        log.info("AJAX Call to rename group " + grpid + " completed", "grouptool");
                    },
                    success: function(response, status) {
                        if (response.error) {
                            text.before("<div class=\"infonode alert-error\" style=\"display:none\">" + response.error + "</div>");
                            infoNode = $("div.infonode");
                            infoNode.fadeIn(600);
                            // Remove after 60 seconds automatically!
                            window.setTimeout(function() { infoNode.fadeOut(600).delay(600).remove();}, 60*1000);
                        } else {
                            text.before("<div class=\"infonode alert-success\" style=\"display:none\">" +
                                        response.message + "</div>");
                            infoNode = $("div.infonode");
                            infoNode.fadeIn(600);
                            text.html(field.val());
                            field.fadeOut(600, function() {
                                field.attr('value', field.val());
                                field.attr('type', 'hidden');
                                text.fadeIn(600);
                                button.fadeIn(600);
                            });
                            field.off('keydown');
                            window.setTimeout(function() { infoNode.fadeOut(600).delay(600).remove();}, 5*1000);
                        }
                        log.info("AJAX Call to rename group " + grpid + " successfull\n" + status, "grouptool");
                    },
                    error: function(jqXHR, status, error) {
                        // Show message
                        var tmpnode = $("<div class=\"infonode alert-error\" style=\"display:none\">" + status + "<br />" +
                                    "<span class=\"small\">" + error + "</span></div>");
                        infoNode = text.before(tmpnode);
                        infoNode.fadeIn(600);
                        log.error("AJAX Call to rename group " + grpid + " failure", "grouptool");
                    },
                    end: function() {
                        log.info("AJAX Call to rename group " + grpid + " ended", "grouptool");
                    },
                    statusCode: {
                        404: function() {
                            log.error("404: URL not found!", "grouptool");
                        },
                        500: function() {
                            log.error("500: Internal server error!", "grouptool");
                        }
                    },
                    timeout: 60000,
                };

                $.ajax(cfg);
            } else if (e.which === 27) { // Escape
                field.fadeOut(600, function() {
                    text.hide();
                    field.attr('type', 'hidden');
                    field.val(text.html());
                    field.attr('value', text.html());
                    text.fadeIn(600);
                    button.fadeIn(600);
                });
                if (infoNode) {
                    infoNode.fadeOut(600).delay(600).remove();
                }
                field.off('keydown');
            }
        });
    };

    /**
      * Change maximum group size of 1 moodle group via AJAX request.
      * @access private
      * @return void
      */
    Administration.prototype.resizegroup = function(e) {
        log.info('Resize Group!', 'grouptool');
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        e.target = $(e.target);
        var grpid = e.target.attr('name').replace ( /[^\d]/g, '' );

        var strings = e.data.strings;
        var admin = e.data.admin;

        var button = e.target.closest('a');
        var field = button.prevAll('input[type=hidden]');
        var text = button.prevAll('span.text');
        var infoNode = '';
        var helpNode = '';
        button.fadeOut(600);
        field.fadeOut(600);
        text.fadeOut(600, function() {
            field.attr('type', 'text');
            field.fadeIn(600);
            field.focus();
            field.select();
            var tmpnode = $("<div class=\"helpnode alert-info\" style=\"display:none\">" +
                            strings.resizehelp + "</div>");
            text.before(tmpnode);
            helpNode = $('div.helpnode');
            helpNode.fadeIn(600);
        });
        field.on('keydown', function(e) {
            if (e.which === 13 || e.which === 27) {
                e.preventDefault();
                e.stopPropagation();
            }

            if (e.which === 13) { // Enter
                // TODO start AJAX Call and process Response!
                var cfg = {
                    method: 'POST',
                    url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                    data: {
                            'action': 'resize',
                            'groupid': grpid,
                            'size': field.val(),
                            'sesskey': config.sesskey,
                            'contextid': admin.contextid
                    },
                    headers: { 'X-Transaction': 'POST resize group ' + grpid},
                    dataType: 'json',
                    beforeSend: function() {
                        if (infoNode) {
                            infoNode.fadeOut(600);
                            infoNode.remove();
                        }
                        if (helpNode) {
                            helpNode.fadeOut(600);
                            helpNode.remove();
                        }
                        log.info("Start AJAX Call to resize group " + grpid, "grouptool");
                    },
                    complete: function() {
                        log.info("AJAX Call to resize group " + grpid + " completed", "grouptool");
                    },
                    success: function(response, status) {
                        var tmpnode = '';
                        if (response.error) {
                            tmpnode = $("<div class=\"infonode alert-error\" style=\"display:none\">" + response.error + "</div>");
                            text.before(tmpnode);
                            infoNode = $('div.infonode');
                            infoNode.fadeIn(600);
                            // Remove after 60 seconds automatically!
                            window.setTimeout(infoNode.fadeOut(600).delay(600).remove(), 60 * 1000);
                        } else {
                            tmpnode = $("<div class=\"infonode alert-success\" style=\"display:none\">" +
                                        response.message + "</div>");
                            text.before(tmpnode);
                            infoNode = $('div.infonode');
                            infoNode.fadeIn(600);
                            var newvalue = field.val();
                            if (newvalue === '') {
                                text.html(admin.globalsize + '*');
                            } else {
                                text.html(newvalue);
                            }
                            field.fadeOut(600, function() {
                                field.attr('value', field.val());
                                field.attr('type', 'hidden');
                                text.fadeIn(600);
                                button.fadeIn(600);
                                field.off('keydown');
                            });
                            window.setTimeout(function() { infoNode.fadeOut(600).delay(600).remove(); }, 5 * 1000);
                        }
                        log.info("AJAX Call to resize group " + grpid + " successfull\n" + status, "grouptool");
                    },
                    error: function(jqXHR, status, error) {
                        // Show message
                        jqXHR = null;
                        var tmpnode = $("<div class=\"infonode alert-error\" style=\"display:none\">" + status + "</div>");
                        infoNode = text.before(tmpnode);
                        infoNode.fadeIn(600);
                        log.error("AJAX Call to resize group " + grpid + " failure" + "\n" + error, "grouptool");
                    },
                    end: function() {
                        log.info("AJAX Call to resize group " + grpid + " ended", "grouptool");
                    },
                    statusCode: {
                        404: function() {
                            log.error("404: URL not found!", "grouptool");
                        },
                        500: function() {
                            log.error("500: Internal server error!", "grouptool");
                        }
                    },
                    timeout: 60000,
                };

                $.ajax(cfg);

            } else if (e.which === 27) {
                e.preventDefault();
                e.stopPropagation();

                field.fadeOut(600, function() {
                    text.hide();
                    field.attr('type', 'hidden');
                    field.attr('value', text.html());
                    field.val(text.html());
                    text.fadeIn(600);
                    button.fadeIn(600);
                });
                if (infoNode) {
                    infoNode.fadeOut(600).delay(600).remove();
                }
                if (helpNode) {
                    helpNode.fadeOut(600).delay(600).remove();
                }
                field.unbind('key');
            }
        });
    };

    /**
      * Toggle 1 group (active/inactive) and change symbol or remove the HTML node respectively.
      * @access private
      * @return void
      */
    Administration.prototype.togglegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();

        e.target = $(e.target);
        var grpid = e.target.attr('name').replace ( /[^\d]/g, '' );

        log.info('TOGGLE GROUP ' + grpid, "grouptool");

        var cfg;

        if (e.target.hasClass('active')) {
            // Set inactive (via AJAX Request)!
            log.info('DEACTIVATE GROUP ' + grpid + '!', "grouptool");

            cfg = {
                method: 'POST',
                url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                data: {
                        'action': 'deactivate',
                        'groupid': grpid,
                        'sesskey': config.sesskey,
                        'contextid': e.data.contextid,
                        'filter': e.data.filterid,
                      },
                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                dataType: 'json',
                beforeSend: function() {
                    log.info("Start AJAX Call to deactivate group " + grpid + "\n" +
                             cfg.url + "?action=deactivate&groupid=" + grpid + "&sesskey=" + config.sesskey +
                             "&contextid=" + e.data.contextid, "grouptool");
                },
                complete: function() {
                    log.info("AJAX Call to deactivate group " + grpid + " completed", "grouptool");
                },
                success: function(response, status) {
                    var inactivestr = str.get_string('inactive', 'mod_grouptool');
                    if (response.error) {
                        log.info("AJAX Call to deactivate group " + grpid + " successfull but error occured:\n" +
                                 response.error + "\n" + status, "grouptool");
                    } else {
                        if (e.data.filter === 'active') {
                            e.target.closest('tr').fadeOut(600, function() {
                                e.target.closest('tr').remove();
                                if (response.noentriesmessage !== '') {
                                    $('div.sortlist_container').fadeOut(600, function() {
                                        $('div.sortlist_container').html(response.noentriesmessage);
                                        $('div.sortlist_container').fadeIn(600);
                                    });
                                }
                            });
                        } else {
                            // Else set URL and alt of new image!
                            e.target.closest('tr').fadeOut('fadeOut', function() {
                                e.target.closest('tr').addClass('dimmed_text');
                                $(e.target).removeClass('active').addClass('inactive');
                                e.target.attr('src', murl.imageUrl('inactive', 'mod_grouptool'));
                                inactivestr.done(function (s) {
                                    e.target.attr('alt', s);
                                }).fail(function (ex) {
                                    log.error("Error retrieving string: " + ex, "grouptool");
                                });
                                e.target.closest('tr').fadeIn(600);
                            });
                            // And add class dimmed_text to row!
                        }
                        log.info("AJAX Call to deactivate group " + grpid + " successfull\n" + response.message + "\n" + status,
                                 "grouptool");
                    }
                },
                error: function() {
                    // Show message
                    log.error("AJAX Call to deactivate group " + grpid + " failure", "grouptool");
                },
                end: function() {
                    log.info("AJAX Call to deactivate group " + grpid + " ended", "grouptool");
                },
                timeout: 60000,
            };

            $.ajax(cfg);

        } else if (e.target.hasClass('inactive')) {

            // Set active (via AJAX Request)!
            log.info('ACTIVATE GROUP ' + grpid + '!', "grouptool");

            cfg = {
                method: 'POST',
                url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                data: {
                        'action': 'activate',
                        'groupid': grpid,
                        'sesskey': config.sesskey,
                        'contextid': e.data.contextid,
                        'filter': e.data.filterid
                },
                headers: { 'X-Transaction': 'POST rename group ' + grpid},
                beforeSend: function() {
                    log.info('Start AJAX Call to activate group ' + grpid + "\n" + cfg.url + '?action=deactivate' +
                             '&groupid=' + grpid + '&sesskey=' + config.sesskey +
                             '&contextid=' + e.data.contextid, "grouptool");
                },
                complete: function() {
                    log.info("AJAX Call to activate group " + grpid + " completed", "grouptool");
                },
                success: function(response, status) {
                    var activestr = str.get_string('active', 'mod_grouptool');
                    if (response.error) {
                        log.info("AJAX Call to activate group " + grpid + " successfull but error occured:\n" +
                                 response.error + "\n" + status, "grouptool");
                    } else {
                        if (e.data.filter === 'inactive') {
                            // If showing only active remove from list!
                            e.target.closest('tr').fadeOut(600, function() {
                                e.target.closest('tr').remove();
                                if (response.noentriesmessage !== '') {
                                    $('div.sortlist_container').fadeOut(600, function() {
                                        $('div.sortlist_container').html(response.noentriesmessage);
                                        $('div.sortlist_container').fadeIn(600);
                                    });
                                }
                            });
                        } else {
                            // Else set URL and alt of new image!
                            e.target.closest('tr').fadeOut(600, function() {
                                e.target.closest('tr').removeClass('dimmed_text');
                                e.target.removeClass('inactive').addClass('active');
                                e.target.attr('src', murl.imageUrl('active', 'mod_grouptool'));
                                activestr.done(function (s) {
                                    e.target.attr('alt', s);
                                }).fail(function (ex) {
                                    log.error('Error while retrieving string: ' + ex, "grouptool");
                                });
                                // TODO move node in list to correct position?
                                e.target.closest('tr').fadeIn(600);
                            });
                        }
                        log.info("AJAX Call to activate group " + grpid + " successfull\n" + response.message, "grouptool");
                    }
                },
                error: function(jqXHR, status, error) {
                    // Show message
                    log.error("AJAX Call to activate group " + grpid + " failure\n" + status + "\n" + error, "grouptool");
                },
                end: function() {
                    log.info("AJAX Call to activate group " + grpid + " ended", "info", "grouptool");
                },
                statusCode: {
                    404: function() {
                        log.error("404: URL not found!", "grouptool");
                    },
                    500: function() {
                        log.error("500: Internal server error!", "grouptool");
                    }
                },
                timeout: 60000,
            };

            $.ajax(cfg);

        } else {
            // Error!
            log.error('Group with id ' + grpid + ' must have either class "active" or "inactive"!', "grouptool");
        }
    };

    /**
      * Delete one moodle group via AJAX request.
      * @access private
      * @return void
      */
    Administration.prototype.deletegroup = function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Get group id!
        e.target = $(e.target);
        var grpid = e.target.attr('name').replace( /[^\d]/g, '' );
        var admin = e.data.admin;
        var strings = e.data.strings;

        notif.confirm(strings.title, strings.confirm, strings.yes, strings.no,
                      function() {
                          if (!grpid) {
                              log.info('No Group ID!', 'grouptool');
                              return;
                          }

                          log.info('DELTE GROUP ' + grpid + '!', "grouptool");

                          var cfg = {
                              method: 'POST',
                              url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                              data: {
                                      action: 'delete',
                                      groupid: grpid,
                                      sesskey: config.sesskey,
                                      contextid: admin.contextid
                              },
                              headers: { 'X-Transaction': 'POST rename group ' + grpid},
                              beforeSend: function() {
                                  log.info("Start AJAX Call to delete group " + grpid,
                                           "grouptool");
                              },
                              complete: function() {
                                  log.info("AJAX Call to delete group " + grpid + " completed",
                                           "grouptool");
                              },
                              success: function(response, status) {
                                  if (!response.error) {
                                      // Success, remove the corresponding table entry...
                                      $('#delete_' + grpid).closest('tr').fadeOut(600)
                                          .delay(600).remove();
                                  }
                                  log.info("AJAX Call to delete group " + grpid + " successfull\n" + status, "grouptool");
                              },
                              error: function() {
                                  // Show message
                                  log.error("AJAX Call to rename group " + grpid + " failure", "grouptool");
                              },
                              end: function() {
                                  log.info("AJAX Call to rename group " + grpid + " ended", "grouptool");
                              },
                              statusCode: {
                                  404: function() {
                                      log.error("404: URL not found!", "grouptool");
                                  },
                                  500: function() {
                                      log.error("500: Internal server error!", "grouptool");
                                  }
                              },
                              timeout: 60000,
                          };

                          $.ajax(cfg);
                      });
    };

    var instance = new Administration();

    /**
      * Initialize the JS (save params and attach event handler).
      * @access public
      * @return void
      */
    instance.initializer = function(params) { //'config' contains the parameter values

        /** @access private */
        this.contextid = params.contextid;

        /** @access private */
        this.lang = params.lang;
        /** @access private */
        this.filter = params.filter;
        /** @access private */
        this.filterid = params.filterid;
        /** @access private */
        this.globalsize = params.globalsize;

        log.info('Initalize Grouptool group administration', "grouptool");
        $('.path-mod-grouptool a.renamebutton').on('click', null, this, this.renamegroup);
        log.debug("Init edit size button", "grouptool");
        str.get_strings([{key: 'ajax_edit_size_help', component: 'mod_grouptool'}]).done(function(s) {
            var strings = { resizehelp: s[0] };
            log.debug("String successfully retrieved: " + s, "grouptool");
            $('.path-mod-grouptool a.resizebutton').on('click', null, {strings: strings, admin: instance}, instance.resizegroup);
        }).fail(function(ex) {
            log.error("Error while retrieving string: " + ex, "grouptool");
        });

        str.get_strings([{key: 'confirm_delete_title', component: 'mod_grouptool'},
                         {key: 'confirm_delete', component: 'mod_grouptool'},
                         {key: 'yes', component: 'moodle'},
                         {key: 'no', component: 'moodle'}]).done(function(s) {
            log.info("Strings successfully retrieved: " + s, "grouptool");
            var strings = { title: s[0], confirm: s[1], yes: s[2], no: s[3] };
            $('.path-mod-grouptool a.deletebutton').on('click', null, {strings: strings, admin: instance}, instance.deletegroup);
        }).fail(function(ex) {
            log.error("Error while retrieving strings: " + ex, "grouptool");
        });
        $('.path-mod-grouptool a.togglebutton').on('click', null, this, this.togglegroup);
    };

    return instance;
});