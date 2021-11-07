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
 * Javascript for sortable groups-list
 *
 * @module   mod_grouptool/sortlist
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * @module mod_grouptool/sortlist
  */
define(['jquery', 'jqueryui', 'core/ajax', 'core/templates', 'core/str', 'core/log', 'core/notification'], function($, jqui, ajax,
        templates, str, log, notif) {

    /**
     * @constructor
     * @alias module:mod_grouptool/sortlist
     */
    var Sortlist = function() {
        this.cmid = 0;
    };

    /**
     * Change the checked checkboxes due to action in checkbox-controller!
     *
     * @param {Event} e Event object
     * @param {string} newstate select|deselect|toggle
     */
    Sortlist.prototype.updateCheckboxes = function(e, newstate) {

        var selector = '';

        // Get all selected groupids and construct selector!
        $('select[name="classes[]"] option:selected').each(function(idx, current) {
            if (selector !== '') {
                selector += ', ';
            }
            selector += '.class' + $(current).val();
        });
        var checkboxes = $(selector);

        e.preventDefault();

        switch (newstate) {
            case 'select': // Check!
                checkboxes.prop('checked', true);
                break;
            case 'deselect': // Uncheck!
                checkboxes.prop('checked', false);
                break;
            case 'toggle':
                checkboxes.each(function(idx, current) {
                    if ($(current).prop('checked')) {
                        $(current).prop('checked', false);
                    } else {
                        $(current).prop('checked', true);
                    }
                });
                break;
            default:
                log.info('Undefined new checkbox state!', 'grouptool');
                break;
        }
    };

    /**
     * Start of dragging!
     *
     * @param {Event} e Event object
     * @param {object} ui Jquery UI instance
     */
    Sortlist.prototype.dragStartHandler = function(e, ui) {
        // Get our drag object!
        var helper = ui.helper;

        helper.find('a[data-movedown], a[data-moveup]').css('visibility', 'visible');
    };

    /**
     * End of drag!
     *
     * @param {Event} e Event object
     */
    Sortlist.prototype.dragEndHandler = function(e) {
        // Set the hidden fields containing the sort order new!
        var neworderparams = [];

        var sortlistEntries = $('.mod_grouptool_sortlist_body .mod_grouptool_sortlist_entry');
        sortlistEntries.find('a[data-movedown], a[data-moveup]').css('visibility', 'visible');
        sortlistEntries.first().find('a[data-moveup]').css('visibility', 'hidden');
        sortlistEntries.last().find('a[data-movedown]').css('visibility', 'hidden');

        sortlistEntries.each(function(index) {
            // Using attr here, to have updated values seen in the HTML too!
            $(this).attr('order', index + 1);
            $(this).find('input[name="order[' + $(this).data('id') + ']"]').val(index + 1);

            // Add new order to new order params!
            neworderparams.push({
                groupid: $(this).data('id'),
                order: index + 1
            });
        });

        if (neworderparams !== '') {
            var requests = ajax.call([{
                methodname: 'mod_grouptool_reorder_groups',
                args: {cmid: e.data.cmid, order: neworderparams},
                fail: notif.exception
            }]);
            requests[0].then(function(result) {
                var context = {
                    'message': '',
                    'extraclasses': 'infonode'
                };
                var template = 'core/notification_success';
                var autoFadeOut = 5 * 1000;

                if (result.error) {
                    template = 'core/notification_error';
                    context.message = result.error;
                    autoFadeOut = 60 * 1000;
                    log.info("AJAX Call to reorder groups successfull\nError ocured:" + result.error + "\n" + status, "grouptool");
                    templates.render(template, context).then(function(html) {
                        var infoNode = $(html);
                        infoNode.hide(0);
                        $('table.drag_list').before(infoNode);
                        infoNode.slideDown(600, function() {
                            window.setTimeout(function() {
                                infoNode.slideUp(600, function() {
                                    infoNode.remove();
                                });
                            }, autoFadeOut);
                        });

                        return this;
                    }).fail(notif.exception);
                } else {
                    context.message = result.message;
                    log.info("AJAX Call to reorder groups successfull\n" + result.message + "\n" + status, "grouptool");
                }


                return this;
            }).fail(notif.exception);
        }
    };

    /**
     * Move the element 1 position down!
     *
     * @param {Event} e Event object
     */
    Sortlist.prototype.moveDown = function(e) {
        // Swap sort-order-values!
        var target = $(e.target);
        var nodeA = target.closest('.mod_grouptool_sortlist_entry');
        var nodeB = target.closest('.mod_grouptool_sortlist_entry').next('.mod_grouptool_sortlist_entry');
        var thisOrder = nodeA.data('order');
        var otherOrder = nodeB.data('order');

        // Stop the button from submitting!
        e.preventDefault();
        e.stopPropagation();

        var requests = ajax.call([{
            methodname: 'mod_grouptool_swap_groups',
            args: {cmid: e.data.cmid, a: nodeA.data('id'), b: nodeB.data('id')},
            fail: notif.exception
        }]);
        requests[0].then(function(result) {
            if (result.error) {
                notif.exception(result.error);
            } else {
                // Swap list-elements!
                nodeB.after(nodeA.clone(true));
                nodeA.replaceWith(nodeB);

                nodeA.data('order', otherOrder);
                nodeA.find('input[name="order[' + nodeA.data('id') + ']"]').val(otherOrder);
                nodeB.data('order', thisOrder);
                nodeB.find('input[name="order[' + nodeB.data('id') + ']"]').val(thisOrder);
                log.info(result.message);
            }

            return this;
        }).fail(notif.exception);
    };

    /**
     * Move the element 1 position up!
     *
     * @param {Event} e Event object
     */
    Sortlist.prototype.moveUp = function(e) {
        // Swap sort-order-values!
        var target = $(e.target);
        var nodeA = target.closest('.mod_grouptool_sortlist_entry');
        var nodeB = target.closest('.mod_grouptool_sortlist_entry').prev('.mod_grouptool_sortlist_entry');

        var thisOrder = nodeA.data('order');
        var otherOrder = nodeB.data('order');

        // Stop the button from submitting!
        e.preventDefault();
        e.stopPropagation();

        var requests = ajax.call([{
            methodname: 'mod_grouptool_swap_groups',
            args: {cmid: e.data.cmid, a: nodeA.data('id'), b: nodeB.data('id')},
            fail: notif.exception
        }]);
        requests[0].then(function(result) {
            if (result.error) {
                notif.exception(result.error);
            } else {
                // Swap list-elements!
                nodeB.before(nodeA.clone(true));
                nodeA.replaceWith(nodeB);

                nodeA.data('order', otherOrder);
                nodeA.find('input[name="order[' + nodeA.data('id') + ']"]').val(otherOrder);
                nodeB.data('order', thisOrder);
                nodeB.find('input[name="order[' + nodeB.data('id') + ']"]').val(thisOrder);
                log.info(result.message);
            }

            return this;
        }).fail(notif.exception);
    };

    var instance = new Sortlist();

    /**
     * Initializer
     *
     * @param {int} cmid
     */
    instance.initializer = function(cmid) {

        instance.cmid = cmid;

        log.info('Initialize Grouptool sortlist', 'grouptool');
        $('.path-mod-grouptool .mod_grouptool_sortlist .mod_grouptool_sortlist_body').sortable({
            containment: '.mod_grouptool_sortlist .mod_grouptool_sortlist_body',
            cursor: 'move',
            delay: 150,
            handle: '[data-drag]',
            items: ' .mod_grouptool_sortlist_entry',
            opacity: 0.5,
            helper: 'clone',
            axis: 'y',
            start: instance.dragStartHandler,
            stop: function(e) {
                e.data = instance;
                instance.dragEndHandler(e);
            }
        });
        // Enable the drag-symbols when JS is enabled :)!
        var dragnodes = $('.path-mod-grouptool .mod_grouptool_sortlist tr[data-id] [data-drag]');
        $('.path-mod-grouptool .mod_grouptool_sortlist tr .js_invisible').removeClass('js_invisible');
        dragnodes.removeClass('js_invisible');
        dragnodes.css('cursor', 'pointer');

        // Add JS-Eventhandler for each move-up/down-button-click (=images)!
        var sortlistnode = $('.path-mod-grouptool .mod_grouptool_sortlist');
        sortlistnode.on('click', 'tr[data-id] a[data-movedown]', this, instance.moveDown);
        sortlistnode.on('click', 'tr[data-id] a[data-moveup]', this, instance.moveUp);

        // Enhanced checkbox-controller functionality!
        var checkboxControlsAction = $('button[name="do_class_action"]');
        if (checkboxControlsAction) {
            require(['mod_grouptool/multiseltoggle'], function(toggle) {
                var select = $('select[name="classes[]"]');
                toggle.enable(select.get()[0]);
            });
            checkboxControlsAction.on('click', function(e) {
                // Get the new state and continue!
                var newstate = '';
                $('input[name="class_action"]').each(function(idx, current) {
                    if ($(current).prop('checked') === true) {
                        newstate = $(current).val();
                        log.info('Update checkboxes \'' + newstate + '\'!');
                        instance.updateCheckboxes(e, newstate);
                    }
                });

            });
        } else {
            log.info('No sortlist controller found!', 'grouptool');
        }

        // Action button to select all!
        $('.simple_select_all').on('click', function(e) {
            log.info('Bind select-all handler!', 'grouptool');
            e.preventDefault();
            e.stopPropagation();

            $('.class0').prop('checked', true);
        });

        // Action button to select none!
        $('.simple_select_none').on('click', function(e) {
            log.info('Bind deselect-all handler!', 'grouptool');
            e.preventDefault();
            e.stopPropagation();

            $('.class0').prop('checked', false);
        });
    };

    return instance;
});
