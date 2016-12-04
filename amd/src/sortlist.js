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
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * @module mod_grouptool/sortlist
  */
define(['jquery', 'jqueryui', 'core/config', 'core/str', 'core/url', 'core/log'], function($, jqui, config, str, murl, log) {

    /**
     * @constructor
     * @alias module:mod_grouptool/sortlist
     */
    var Sortlist = function() {

        this.contextid = 0;
        this.lang = 'en';

    };

    Sortlist.prototype.update_checkboxes = function(e, newstate) {

        var selector = '';

        // Get all selected groupids and construct selector!
        $('select[name="classes[]"] option:selected').each( function(idx, current) {
            if (selector !== '') {
                selector += ', ';
            }
            selector += '.class' + $(current).val();
        });
        var checkboxes = $(selector);

        e.preventDefault();

        switch (newstate) {
            case 'select': //check
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

    Sortlist.prototype.dragStartHandler = function(e, ui) {
        // Get our drag object!
        var helper = ui.helper;

        helper.find('.movedownbutton').css('visibility', 'visible');
        helper.find('.moveupbutton').css('visibility', 'visible');
    };

    Sortlist.prototype.dragEndHandler = function() {
        // Set the hidden fields containing the sort order new!
        var neworderparams = {};

        $('table.drag_list tr.draggable_item').find('.movedownbutton, .movelupbutton').css('visibility', 'visible');
        $('table.drag_list tr.draggable_item').first().find('.movelupbutton').css('visibility', 'hidden');
        $('table.drag_list tr.draggable_item').last().find('.moveldownbutton').css('visibility', 'hidden');

        $('table.drag_list tr.draggable_item td input.sort_order').each(function(index, current) {
            current = $(current);
            current.attr('value', index + 1);

            // Add new order to new order params!
            neworderparams[current.attr('name')] = current.val();

        });

        if (neworderparams !== '') {
            var contextid = $('.path-mod-grouptool .drag_list tbody').data('context');
            var infoNode = '';
            // Start AJAX Call to update order in DB!
            var cfg = {
                method: 'POST',
                url: config.wwwroot + "/mod/grouptool/editgroup_ajax.php",
                data: $.extend({ 'action': 'reorder', 'sesskey': M.cfg.sesskey, 'contextid': contextid},
                               neworderparams),
                headers: { 'X-Transaction': 'POST reorder groups'},
                dataType: 'json',
                beforeSend: function() {
                    if (infoNode !== '') {
                        infoNode.fadeOut(600).delay(600).remove();
                    }
                    log.info("Start AJAX Call to reorder groups", "grouptool");
                },
                complete: function() {
                    log.info("AJAX Call to reorder groups completed", "grouptool");
                },
                success: function(response, status) {
                    var tmpnode = '';
                    if (response.error) {
                        tmpnode = $("<div class=\"infonode alert-error\" style=\"display:none\">" + response.error + "</div>");
                        $('table.drag_list').before(tmpnode);
                        infoNode = $('div.infonode');
                        infoNode.fadeIn(600);
                        // Remove after 60 seconds automatically!
                        window.setTimeout(function() {
                            infoNode.fadeOut(600).delay(600).remove();
                        }, 60 * 1000);
                        log.info("AJAX Call to reorder groups successfull\nError ocured:" + response.error + "\n" + status,
                                 "grouptool");
                    } else {
                        tmpnode = $("<div class=\"infonode alert-success\" style=\"display:none\">" + response.message + "</div>");
                        $('table.drag_list').before(tmpnode);
                        infoNode = $('div.infonode');
                        infoNode.fadeIn(600);
                        window.setTimeout(function() {
                            infoNode.fadeOut(600).delay(600).remove();
                        }, 5 * 1000);
                        log.info("AJAX Call to reorder groups successfull\n" + response.message + "\n" + status, "grouptool");
                    }
                },
                error: function(jqXHR, status, error) {
                    // Show message!
                    log.error("AJAX Call to reorder groups failure\nStatus: " + status + "\nError:" + error, "grouptool");
                },
                end: function() {
                    log.info("AJAX Call to reorder groups ended", "grouptool");
                }
            };
            $.ajax(cfg);
        }
    };

    Sortlist.prototype.moveDown = function(e) { // Move the node 1 element down!
        // Swap sort-order-values!
        e.target = $(e.target);
        var this_order = e.target.closest('.draggable_item').find('.sort_order').val();
        var other_order = e.target.closest('.draggable_item').next('.draggable_item').find('.sort_order').val();

        // Stop the button from submitting!
        e.preventDefault();
        e.stopPropagation();

        var contextid = e.data.contextid;

        var valuefrom = e.target.closest('.draggable_item').next('.draggable_item').find('input[name="selected[]"]');
        // Start AJAX Call to update order in DB!
        var cfg = {
            method: 'POST',
            url: M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php",
            data: {
                'action': 'swap',
                'sesskey': config.sesskey,
                'contextid': contextid,
                'groupA': e.target.closest('.draggable_item').find('input[name="selected[]"]').val(),
                'groupB': valuefrom.val()
            },
            headers: { 'X-Transaction': 'POST reorder groups'},
            beforeSend: function() {
                log.info("Start AJAX Call to reorder groups", "grouptool");
            },
            complete: function() {
                log.info("AJAX Call to reorder groups completed", "grouptool");
            },
            success: function(response, status) {
                if (response.error) {
                    log.info("AJAX Call to reorder groups successfull\nError occured:" + response.error + "\n" + status,
                             "grouptool");
                } else {
                    log.info("AJAX Call to reorder groups successfull\n" + response.message + "\n" + status, "grouptool");
                }
            },
            failure: function(jqXHR, status, error) {
                // Show message!
                log.error("AJAX Call to reorder groups failure\nStatus: " + status + "\nError:" + error, "grouptool");
            },
            end: function() {
                log.info("AJAX Call to reorder groups ended", "grouptool");
            }
        };
        $.ajax(cfg);

        // Swap list-elements!
        var nodeA = e.target.closest('.draggable_item');
        var nodeB = e.target.closest('.draggable_item').next('.draggable_item');
        nodeB.after(nodeA.clone(true));
        nodeA.replaceWith(nodeB);

        e.target.closest('.draggable_item').find('input.sort_order').val(other_order);
        e.target.closest('.draggable_item').prev('.draggable_item').find('input.sort_order').val(this_order);
    };

    Sortlist.prototype.moveUp = function(e) { // Move the node 1 element up!
        // Swap sort-order-values!
        e.target = $(e.target);
        var this_order = e.target.closest('.draggable_item').find('.sort_order').val();
        var other_order = e.target.closest('.draggable_item').prev('.draggable_item').find('.sort_order').val();

        // Stop the button from submitting!
        e.preventDefault();
        e.stopPropagation();

        var contextid = e.data.contextid;
        var valuefrom = e.target.closest('.draggable_item').prev('.draggable_item').find('input[name="selected[]"]');
        // Start AJAX Call to update order in DB!
        var cfg = {
            method: 'POST',
            url: M.cfg.wwwroot + "/mod/grouptool/editgroup_ajax.php",
            data: {
                'action': 'swap',
                'sesskey': config.sesskey,
                'contextid': contextid,
                'groupA': e.target.closest('.draggable_item').find('input[name="selected[]"]').val(),
                'groupB': valuefrom.val()
            },
            headers: { 'X-Transaction': 'POST reorder groups'},
            beforeSend: function() {
                log.info("Start AJAX Call to reorder groups", "grouptool");
            },
            complete: function() {
                log.info("AJAX Call to reorder groups completed", "grouptool");
            },
            success: function(response, status) {
                if (response.error) {
                    log.error("AJAX Call to reorder groups successfull\nError ocured:" + response.error + "\n" + status,
                              "grouptool");
                } else {
                    log.info("AJAX Call to reorder groups successfull\n" + response.message + "\n" + status, "grouptool");
                }
            },
            failure: function(jqXHR, status, error) {
                // Show message!
                log.error("AJAX Call to reorder groups failure\nStatus: " + status + "\nErrortext:" + error, "grouptool");
            },
            end: function() {
                log.info("AJAX Call to reorder groups ended", "grouptool");
            }
        };

        $.ajax(cfg);

        e.target.closest('.draggable_item').find('input.sort_order').val(other_order);
        e.target.closest('.draggable_item').prev('.draggable_item').find('input.sort_order').val(this_order);

        // Swap list-elements!
        var nodeA = e.target.closest('.draggable_item');
        var nodeB = e.target.closest('.draggable_item').prev('.draggable_item');
        nodeA.before(nodeB.clone(true));
        nodeB.replaceWith(nodeA);
    };

    var instance = new Sortlist();

    instance.initializer = function(param) { // Parameter 'param' contains the parameter values!

        instance.contextid = param.contextid;
        instance.lang = param.lang;

        log.info('Initialize Grouptool sortlist', 'grouptool');
        $('.path-mod-grouptool .drag_list tbody').data('context', instance.contextid);
        $('.path-mod-grouptool .drag_list tbody').sortable({
            containment: '.drag_list tbody',
            cursor: 'move',
            delay: 150,
            handle: '.drag_image',
            items: ' .draggable_item',
            opacity: 0.5,
            helper: 'clone',
            axis: 'y',
            start: instance.dragStartHandler,
            stop: instance.dragEndHandler
        });
        // Enable the drag-symbols when JS is enabled :)!
        $('.path-mod-grouptool .drag_list .draggable_item .drag_image').removeClass('js_invisible');

        // Add JS-Eventhandler for each move-up/down-button-click (=images)!
        $('.path-mod-grouptool .buttons .movedownbutton').on('click', null, this, instance.moveDown);
        $('.path-mod-grouptool .buttons .moveupbutton').on('click', null, this, instance.moveUp);

        // Enhanced checkbox-controller functionality!
        var checkbox_controls_action = $('button[name="do_class_action"]');
        if (checkbox_controls_action) {
            checkbox_controls_action.on('click', function(e) {
                // Get the new state and continue!
                var newstate = '';
                $('input[name="class_action"]').each(function (idx, current) {
                    if ($(current).prop('checked') === true) {
                        newstate = $(current).val();
                        log.info('Update checkboxes \'' + newstate + '\'!');
                        instance.update_checkboxes(e, newstate);
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