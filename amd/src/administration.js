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
 * Javascript for group administration (rename, resize, toggle and deletion)
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * @module mod_grouptool/administration
  */
define(['jquery', 'core/templates', 'core/ajax', 'core/str', 'core/url', 'core/notification', 'core/log'], function($, templates,
        ajax, str, murl, notif, log) {

    /**
     * @constructor
     * @alias module:mod_grouptool/administration
     */
    var Administration = function() {
        this.cmid = 0;
        this.filter = null;
        this.filterall = null;
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

        var context = {};
        var requests = [];

        // Get group id!
        e.target = $(e.target).closest('[data-rename]');
        var node = e.target.closest('tr');
        var grpid = node.data('id');

        var button = e.target;
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
        field.on('keydown', null, e.data, function(e) {
            if (e.which === 13 || e.which === 27) {
                e.preventDefault();
                e.stopPropagation();
            }

            if (e.which === 13) { // Enter!
                requests = ajax.call([{
                    methodname: 'mod_grouptool_rename_group',
                    args: {cmid: e.data.cmid, groupid: grpid, name: field.val()},
                    fail: notif.exception
                }]);
                if (infoNode) {
                    infoNode.fadeOut(600);
                    infoNode.remove();
                }
                requests[0].then(function(result) {
                    if (result.error) {
                        context = {
                            'message': result.error
                        };
                        templates.render('core/notification_error', context).then(function(html) {
                            infoNode = $(html);
                            infoNode.hide();
                            node.find('.grpname div').prepend(infoNode);
                            infoNode.fadeIn(600);
                            // Remove after 60 seconds automatically!
                            window.setTimeout(function () {
                                infoNode.fadeOut(600, function () {
                                    infoNode.remove();
                                });
                            }, 60 * 1000);
                        }).fail(notif.exception);
                    } else {
                        context = {
                            'message': result.message
                        };
                        templates.render('core/notification_success', context).then(function(html) {
                            infoNode = $(html);
                            infoNode.hide();
                            node.find('.grpname div').prepend(infoNode);
                            infoNode.fadeIn(600);
                            text.html(field.val());
                            field.fadeOut(600, function() {
                                field.attr('value', field.val());
                                field.attr('type', 'hidden');
                                text.fadeIn(600);
                                button.fadeIn(600);
                            });
                            field.off('keydown');
                            window.setTimeout(function() { infoNode.fadeOut(600, function() { infoNode.remove(); }); }, 5 * 1000);
                        }).fail(notif.exception);

                        log.info("AJAX Call to rename group " + grpid + " successfull\n" + status, "grouptool");
                    }
                });

            } else if (e.which === 27) { // Escape!
                field.fadeOut(600, function() {
                    text.hide();
                    field.attr('type', 'hidden');
                    field.val(text.html());
                    field.attr('value', text.html());
                    text.fadeIn(600);
                    button.fadeIn(600);
                });
                if (infoNode) {
                    infoNode.fadeOut(600, function() { infoNode.remove(); });
                }
                field.unbind('key');
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
        e.target = $(e.target).closest('[data-resize]');
        var node = e.target.closest('tr');
        var grpid = node.data('id');

        var cmid = e.data.instance.cmid;

        var strings = e.data.strings;
        var globalsize = e.data.globalsize;

        var button = e.target;
        var field = button.prevAll('input[type=hidden]');
        var text = button.prevAll('span.text');
        var infoNode = '';
        var helpNode = '';

        var context = {};
        var requests = [];

        button.fadeOut(600);
        field.fadeOut(600);
        text.fadeOut(600, function() {
            field.attr('type', 'text');
            field.fadeIn(600);
            field.focus();
            field.select();
            var context = {
                'message': strings.resizehelp
            };
            templates.render('core/notification_info', context).then(function(html) {
                helpNode = $(html);
                helpNode.hide();
                node.find('.size div').prepend(helpNode);
                helpNode.fadeIn(600);
            }).fail(notif.exception);
        });
        field.on('keydown', null, null, function(e) {
            if (e.which === 13 || e.which === 27) {
                e.preventDefault();
                e.stopPropagation();
            }

            if (e.which === 13) { // Enter!
                requests = ajax.call([{
                    methodname: 'mod_grouptool_resize_group',
                    args: {cmid: parseInt(cmid), groupid: grpid, size: parseInt(field.val())},
                    fail: notif.exception
                }]);
                if (infoNode) {
                    infoNode.fadeOut(600);
                    infoNode.remove();
                }
                if (helpNode) {
                    helpNode.fadeOut(600);
                    helpNode.remove();
                }
                requests[0].then(function(result) {
                    if (result.error) {
                        context = {
                            'message': result.error
                        };
                        templates.render('core/notification_error', context).then(function(html) {
                            infoNode = $(html);
                            infoNode.hide();
                            node.find('.size div').prepend(infoNode);
                            infoNode.fadeIn(600);
                            // Remove after 60 seconds automatically!
                            window.setTimeout(function () {
                                infoNode.fadeOut(600, function () {
                                    infoNode.remove();
                                });
                            }, 60 * 1000);
                        }).fail(notif.exception);
                    } else {
                        context = {
                            'message': result.message
                        };
                        templates.render('core/notification_success', context).then(function(html) {
                            infoNode = $(html);
                            infoNode.hide();
                            node.find('.size div').prepend(infoNode);
                            infoNode.fadeIn(600);
                            var newvalue = field.val();
                            if (newvalue === '') {
                                text.html(globalsize + '*');
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
                            window.setTimeout(function() { infoNode.fadeOut(600, function() { infoNode.remove(); }); }, 5 * 1000);
                        }).fail(notif.exception);
                    }
                    log.info("AJAX Call to resize group " + grpid + " successfull\n" + status, "grouptool");
                });

            } else if (e.which === 27) { // Escape!
                field.fadeOut(600, function() {
                    text.hide();
                    field.attr('type', 'hidden');
                    field.attr('value', text.html());
                    field.val(text.html());
                    text.fadeIn(600);
                    button.fadeIn(600);
                });
                if (infoNode) {
                    infoNode.fadeOut(600, function() { infoNode.remove(); });
                }
                if (helpNode) {
                    helpNode.fadeOut(600, function() { infoNode.remove(); });
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

        var requests = [];
        var context = {};

        e.target = $(e.target).closest("[data-toggle]");

        var node = e.target.closest('tr');

        var grpid = node.data('id');

        log.info('TOGGLE GROUP ' + grpid, "grouptool");

        var status = node.data('status');

        if (status === 1 || status === true) {
            // Set inactive (via AJAX Request)!
            log.info('DEACTIVATE GROUP ' + grpid + '!', "grouptool");

            requests = ajax.call([{
                methodname: 'mod_grouptool_deactivate_group',
                args: {cmid: e.data.cmid, groupid: grpid},
                fail: notif.exception
            }]);
            requests[0].then(function(result) {
                if (result.error) {
                    var text = "AJAX Call to deactivate group " + grpid + " successfull but error occured:\n";
                    log.info(text + result.error + "\n" + status, "grouptool");
                } else {
                    if (e.data.filter === 'active') {
                        node.find('td div').slideUp(600).promise().done(function() {
                            node.remove();
                            if (!$('div.sortlist_container tr').length) {
                                /* TODO: instead we could just switch to filter all via JS/AJAX i.e. render mustache template
                                 * for all groups sortlist! */
                                var stringstofetch = [{'key': 'nogroupsactive', 'component': 'mod_grouptool'},
                                                      {'key': 'nogroupschoose', 'component': 'mod_grouptool'}];
                                str.get_strings(stringstofetch).done(function (s) {
                                    var url = murl.relativeUrl('/mod/grouptool/view.php', {
                                        'id': e.data.cmid,
                                        'tab': 'group_administration',
                                        'filter': e.data.filterall
                                    });
                                    var link = "<a href=\"" + url + "\">" + s[2] + "</a>";
                                    var context = {
                                        'message': s[0] + link
                                    };
                                    var sortlistcontainer = $('div.sortlist_container');
                                    sortlistcontainer.fadeOut(600, function() {
                                        templates.render('core/notification_info', context).then(function(html) {
                                            sortlistcontainer.html(html);
                                            sortlistcontainer.fadeIn(600);
                                        }).fail(notif.exception);
                                    });
                                }).fail(notif.exception);
                            }
                        });
                    } else {
                        // Render the element again and display it!
                        context = {
                            "status": false,
                            "missing": false,
                            "groupings": e.target.closest('tr').data('groupings'),
                            "id": grpid,
                            "checked": e.target.closest('tr').find('input[type=checkbox]').prop('checked'),
                            "name": e.target.closest('tr').data('name'),
                            "pageurl": murl.relativeUrl("/mod/grouptool/view.php", { 'id': e.data.cmid,
                                'tab': 'administration'}),
                            "order": e.target.closest('tr').data('order'),
                            "usesize": !!e.data.usesize,
                            "size": e.target.closest('tr').data('size')
                        };
                        var templatepromise = templates.render('mod_grouptool/sortlist_entry', context);
                        // This will call the function to load and render our template.
                        node.toggleClass('slidup');
                        node.find('td div').slideUp(600).promise().done(function() {
                            templatepromise.then(function (html) {
                                var firstinactive = node.parents('table').find('tr[data-status=0], tr[data-status=false]').first();
                                var lastactive = node.parents('table').find('tr[data-status=1], tr[data-status=true]').last();
                                if (firstinactive.length) {
                                    node.detach();
                                    node.insertBefore(firstinactive);
                                } else if (lastactive.length) {
                                    node.detach();
                                    node.insertAfter(lastactive);
                                }
                                var newnode = $(html);
                                newnode.addClass('slidup');
                                newnode.find('td div').slideUp(0);
                                node.replaceWith(newnode);
                                newnode.find('[data-drag]').removeClass('js_invisible').css('cursor', 'pointer');
                                newnode.toggleClass('slidup');
                                newnode.find('td div').slideDown(600);
                                node = newnode;
                            }).fail(notif.exception);
                        });
                    }
                    log.info("AJAX Call to deactivate group " + grpid + " successfull\n" + result.message + "\n" + status,
                        "grouptool");
                }
            });
        } else if (status === 0 || status === false) {
            // Set active (via AJAX Request)!
            log.info('ACTIVATE GROUP ' + grpid + '!', "grouptool");

            requests = ajax.call([{
                methodname: 'mod_grouptool_activate_group',
                args: {cmid: e.data.cmid, groupid: grpid},
                fail: notif.exception
            }]);
            requests[0].then(function(result) {
                if (result.error) {
                    var text = "AJAX Call to activate group " + grpid + " successfull but error occured:\n";
                    log.info(text + result.error + "\n" + status, "grouptool");
                } else {
                    if (e.data.filter === 'inactive') {
                        // If showing only inactive remove from list!
                        node.find('td div').slideUp(600).promise().done(function() {
                            node.remove();
                            if (!$('div.sortlist_container tr').length) {
                                /* TODO: instead we could just switch to filter all via JS/AJAX i.e. render mustache template
                                 * for all groups sortlist! */
                                var stringstofetch = [
                                    {'key': 'nogroupsinactive', 'component': 'mod_grouptool'},
                                    {'key': 'nogroupschoose', 'component': 'mod_grouptool'}
                                ];
                                str.get_strings(stringstofetch).done(function (s) {
                                    var url = murl.relativeUrl('/mod/grouptool/view.php', {
                                        'id': e.data.cmid,
                                        'tab': 'group_administration',
                                        'filter': e.data.filterall
                                    });
                                    var link = "<a href=\"" + url + "\">" + s[2] + "</a>";
                                    context = {
                                        'message': s[0] + link
                                    };
                                    var sortlistcontainer = $('div.sortlist_container');
                                    sortlistcontainer.fadeOut(600, function() {
                                        templates.render('core/notification_info', context).then(function(html) {
                                            sortlistcontainer.html(html);
                                            sortlistcontainer.fadeIn(600);
                                        }).fail(notif.exception);
                                    });
                                }).fail(notif.exception);
                            }
                        });
                    } else {
                        // Replace sortlist entry!
                        // This will call the function to load and render our template.
                        context = {
                            "status": true,
                            "missing": false,
                            "groupings": node.data('groupings'),
                            "id": grpid,
                            "checked": node.find('input[type=checkbox]').prop('checked'),
                            "name": node.data('name'),
                            "pageurl": murl.relativeUrl("/mod/grouptool/view.php", { 'id': e.data.cmid,
                                'tab': 'administration'}),
                            "order": node.data('order'),
                            "usesize": !!e.data.usesize,
                            "size": node.data('size')
                        };
                        var templatepromise = templates.render('mod_grouptool/sortlist_entry', context);
                        node.toggleClass('slidup');
                        e.target.closest('tr').find('td div').slideUp(600).promise().done(function() {
                            templatepromise.then(function (html) {
                                var firstinactive = node.parents('table').find('tr[data-status=0], tr[data-status=false]').first();
                                var lastactive = node.parents('table').find('tr[data-status=1], tr[data-status=true]').last();
                                if (lastactive.length) {
                                    node.detach();
                                    node.insertAfter(lastactive);
                                } else if (firstinactive.length) {
                                    node.detach();
                                    node.insertBefore(firstinactive);
                                }
                                var newnode = $(html);
                                newnode.addClass('slidup');
                                newnode.find('td div').slideUp(0);
                                node.replaceWith(newnode);
                                newnode.find('[data-drag]').removeClass('js_invisible').css('cursor', 'pointer');
                                newnode.toggleClass('slidup');
                                newnode.find('td div').slideDown(600);
                                node = newnode;
                            }).fail(notif.exception);
                        });
                    }
                    log.info("AJAX Call to activate group " + grpid + " successfull\n" + result.message, "grouptool");
                }
            });
        } else {
            // Error!
            log.error('Group with id ' + grpid + ' must have either status 1 or 0!', "grouptool");
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
        e.target = $(e.target).closest('[data-delete]');

        var node = e.target.closest('tr');

        var grpid = node.data('id');

        var strings = e.data.strings;

        notif.confirm(strings.title, strings.confirm, strings.yes, strings.no, function() {
            if (!grpid) {
                log.info('No Group ID!', 'grouptool');
                return;
            }

            log.info('DELTE GROUP ' + grpid + '!', "grouptool");

            var requests = ajax.call([{
                methodname: 'mod_grouptool_delete_group',
                args: {cmid: e.data.cmid, groupid: grpid},
                fail: notif.exception
            }]);
            requests[0].then(function(result) {
                if (!result.error) {
                    // Success, remove the corresponding table entry...
                    node.find('td div').slideUp(600).promise().done(function() { node.remove(); });
                } else {
                    notif.exception(result.error);
                }
                log.info("AJAX Call to delete group " + grpid + " successfull\n" + status, "grouptool");
            });
        });
    };

    var instance = new Administration();

    /**
     * Initialize the JS (save params and attach event handler).
     * @access public
     * @return void
     */
    instance.initializer = function(cmid, filter, filterid, filterall, globalsize, usesize) {

        this.cmid = cmid;
        this.filter = filter;
        this.filterid = filterid;
        this.filterall = filterall;
        this.globalsize = globalsize;
        this.usesize = usesize;

        log.info('Initalize Grouptool group administration', "grouptool");
        $('.path-mod-grouptool').on('click', 'tr[data-id] a[data-rename]', this, this.renamegroup);
        log.debug("Init edit size button", "grouptool");
        str.get_strings([{key: 'ajax_edit_size_help', component: 'mod_grouptool'}]).done(function(s) {
            var strings = { resizehelp: s[0] };
            log.debug("String successfully retrieved: " + s, "grouptool");
            var resizedata = {instance: instance, strings: strings, globalsize: instance.globalsize};
            $('.path-mod-grouptool').on('click', 'tr[data-id] a[data-resize]', resizedata, instance.resizegroup);
        }).fail(function(ex) {
            log.error("Error while retrieving string: " + ex, "grouptool");
        });
        var stringstofetch = [
            {key: 'confirm_delete_title', component: 'mod_grouptool'},
            {key: 'confirm_delete', component: 'mod_grouptool'},
            {key: 'yes', component: 'moodle'},
            {key: 'no', component: 'moodle'}
        ];
        str.get_strings(stringstofetch).done(function(s) {
            log.info("Strings successfully retrieved: " + s, "grouptool");
            var strings = { title: s[0], confirm: s[1], yes: s[2], no: s[3] };
            $('.path-mod-grouptool .mod_grouptool_sortlist').on('click', 'tr[data-id] a[data-delete]',
                    {cmid: instance.cmid, strings: strings}, instance.deletegroup);
        }).fail(function(ex) {
            log.error("Error while retrieving strings: " + ex, "grouptool");
        });
        $('.path-mod-grouptool .mod_grouptool_sortlist').on('click', 'tr[data-id] a[data-toggle]', this, this.togglegroup);
    };

    return instance;
});
