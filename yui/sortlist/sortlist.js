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
 * sortlist.js
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-mod_grouptool-sortlist', function(Y) {
    var SORTLISTNAME = 'sortlist';
    var sortlist = function(Y) {
        sortlist.superclass.constructor.apply(this, arguments);
    }
    M.mod_grouptool = M.mod_grouptool || {}; //this line use existing name path if it exists, ortherwise create a new one.
                                             //This is to avoid to overwrite previously loaded module with same name.
    M.mod_grouptool.sortlist = M.mod_grouptool.sortlist || {};
    Y.extend(sortlist, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            //gets called when it's going to be pluged in
            M.mod_grouptool.sortlist.contextid = config.contextid;
            M.mod_grouptool.sortlist.lang = config.lang;
        }

    }, {
        NAME : SORTLISTNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
            contextid : { 'value' : 0},
            lang : { 'value' : 'en'}
        } // Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          // Here you can declare default values or run functions on the parameter.
          // The param names must be the same as the ones declared
          // in the $PAGE->requires->yui_module call.

    });

    M.mod_grouptool.sortlist_update_checkboxes = function(e, newstate) {

        var selector = '';

        // Get all selected groupids and construct selector!
        Y.all('select[name="classes[]"] option').each( function() {
            // this = option from the select
            if (this.get('selected') == true) {
                if (selector != '') {
                    selector += ', ';
                }
                selector += '.class' + this.get('value');
            }
        });
        var checkboxes = Y.all(selector);

        e.preventDefault();

        switch(newstate) {
            case 'select': //check
                checkboxes.set('checked', 'checked');
                break;
            case 'deselect': //uncheck
                checkboxes.set('checked', '');
                break;
            case 'toggle':
                checkboxes.each(function(current, index, nodelist) {
                    if(current.get('checked')) {
                        current.set('checked', '');
                    } else {
                        current.set('checked', 'checked');
                    }
                })
                break;
            default:
                break;
        }
    }

    M.mod_grouptool.init_sortlist = function(config) { //'config' contains the parameter values
        //Listen for all drop:over events
        //Y.DD.DDM._debugShim = true;

        //enable the drag-symbols when JS is enabled :)
        Y.all('.drag_list .draggable_item .drag_image').removeClass('js_invisible');

        Y.DD.DDM.on('drop:over', function(e) {
            //Get a reference to our drag and drop nodes
            var drag = e.drag.get('node'),
                drop = e.drop.get('node');

            //Are we dropping on a li node?
            if (drop.get('tagName').toLowerCase() === 'tr') {
                //Are we not going up?
                if (!goingUp) {
                    drop = drop.next('tr.draggable_item');
                }
                //Add the node to this list
                e.drop.get('node').get('parentNode').insertBefore(drag, drop);
                //Set the new parentScroll on the nodescroll plugin
                e.drag.nodescroll.set('parentScroll', e.drop.get('node').get('parentNode'));
                //Resize this nodes shim, so we can drop on it later.
                e.drop.sizeShim();
            }
        });
        //Listen for all drag:drag events
        Y.DD.DDM.on('drag:drag', function(e) {
            //Get the last y point
            var y = e.target.lastXY[1];
            //is it greater than the lastY var?
            if (y < lastY) {
                //We are going up
                goingUp = true;
            } else {
                //We are going down.
                goingUp = false;
            }
            //Cache for next check
            lastY = y;
            Y.DD.DDM.syncActiveShims(true);
        });
        //Listen for all drag:start events
        Y.DD.DDM.on('drag:start', function(e) {
            //Get our drag object
            var drag = e.target;
            //Set some styles/classes here
            drag.get('node').addClass('draggable_item');
            drag.get('node').one('.movedownbutton').setStyle('visibility', 'visible');
            drag.get('node').one('.moveupbutton').setStyle('visibility', 'visible');
            drag.get('node').setStyle('opacity', '.25');
            drag.get('dragNode').addClass('draggable_item dragnode');
            var innerHTML = '<table class="'+drag.get('node').ancestor('table').getAttribute('class')+"\">\n";
            drag.get('node').all('td').each(function(current, index, nodelist) {
                innerHTML += '<td class="'+current.getAttribute('class')+'">'+current.get('innerHTML')+"</td>\n";
            }, null);
            innerHTML += "\n</table>";
            drag.get('dragNode').set('innerHTML', innerHTML);
            drag.get('dragNode').setStyles({
                opacity: '.5', //Make hovered div invisible till I found a better solution.
                align:'center',
                borderColor: drag.get('node').getStyle('borderColor'),
                backgroundColor: drag.get('node').getStyle('backgroundColor')
            });
            drag.get('dragNode').one('.movedownbutton').setStyle('visibility', 'visible');
            drag.get('dragNode').one('.moveupbutton').setStyle('visibility', 'visible');
        });
        //Listen for a drag:end events
        Y.DD.DDM.on('drag:end', function(e) {
            var drag = e.target;
            //Put our styles back
            drag.get('node').setStyles({
                visibility: '',
                opacity: '1'
            });
            // TODO start AJAX Call and process Response!
            //set the hidden fields containing the sort order new
            var neworderparams='';
            Y.all('table.drag_list tr.draggable_item td input.sort_order').each(function(current, index, nodelist) {
                current.setAttribute('value', index+1);
                if(index == 0) {
                    if (current.ancestor('tr.draggable_item')) {
                        current.ancestor('tr.draggable_item').one('.moveupbutton').setStyle('visibility', 'hidden');
                    }
                } else if(index == nodelist.size() - 1) {
                    if (current.ancestor('tr.draggable_item')) {
                        current.ancestor('tr.draggable_item').one('.movedownbutton').setStyle('visibility', 'hidden');
                    }
                } else {
                    if (current.ancestor('tr.draggable_item')) {
                        current.ancestor('tr.draggable_item').all('.movedownbutton, .moveupbutton').setStyle('visibility', 'visible');
                    }
                }

                // Add new order to new order params!
                if (neworderparams == '') {
                    neworderparams = current.getAttribute('name')+'='+current.getAttribute('value');
                } else {
                    neworderparams += '&'+current.getAttribute('name')+'='+current.getAttribute('value');
                }
            });
            if (neworderparams != '') {
                var contextid = M.mod_grouptool.sortlist.contextid;
                var lang = M.mod_grouptool.sortlist.lang;
                var url = M.cfg.wwwroot+"/mod/grouptool/editgroup_ajax.php";//?action=reorder&sesskey="+M.cfg.sesskey+"&contextid="+contextid;//+"&"+neworderparams;
                var infoNode='';
                // Start AJAX Call to update order in DB!
                var cfg = {
                    method: 'POST',
                    data: 'action=reorder&sesskey='+M.cfg.sesskey+'&contextid='+contextid+'&'+neworderparams,
                    headers: { 'X-Transaction': 'POST reorder groups'},
                    on: {
                        start: function(id, args) {
                            if (infoNode != '') {
                                infoNode.hide('fadeOut');
                                infoNode.remove();
                            }
                            Y.log("Start AJAX Call to reorder groups", "info", "grouptool");
                        },
                        complete: function(id, args) {
                            Y.log("AJAX Call to reorder groups completed", "info", "grouptool");
                        },
                        success: function(id, o, args) {
                            response = Y.JSON.parse(o.responseText);
                            if (response.error) {
                                infoNode = Y.one('table.drag_list').insertBefore(Y.Node.create("<div class=\"infonode alert-error\" style=\"display:none\">"+response.error+"</div>"), Y.one('table.drag_list'));
                                infoNode.show('fadeIn');
                                // Remove after 60 seconds automatically!
                                Y.later(60*1000,infoNode,function() { if (infoNode) {infoNode.hide('fadeOut'); infoNode.remove();}});
                                Y.log("AJAX Call to reorder groups successfull\nError ocured:"+response.error, "success", "grouptool");
                            } else {
                                infoNode = Y.one('table.drag_list').insertBefore(Y.Node.create("<div class=\"infonode alert-success\" style=\"display:none\">"+response.message+"</div>"), Y.one('table.drag_list'));
                                infoNode.show('fadeIn');
                                Y.later(5*1000,infoNode,function() { infoNode.hide('fadeOut'); infoNode.remove();});
                                Y.log("AJAX Call to reorder groups successfull\n"+response.message, "success", "grouptool");
                            }
                            //Y.log("AJAX Call to reorder groups successfull", "success", "grouptool");
                        },
                        failure: function(id, o, args) {
                            // Show message
                            Y.log("AJAX Call to reorder groups failure\nStatus: "+o.status+"\nStatustext:"+o.statusText, "error", "grouptool");
                        },
                        end: function(id, args) {
                            Y.log("AJAX Call to reorder groups ended", "info", "grouptool");
                        }
                    }
                };
                Y.io(url, cfg);
            }
        });
        //Listen for all drag:drophit events
        Y.DD.DDM.on('drag:drophit', function(e) {
            var drop = e.drop.get('node'),
                drag = e.drag.get('node');

            //if we are not on an tr, we must have been dropped on a tbody
            if (drop.get('tagName').toLowerCase() !== 'tr') {
                if (!drop.contains(drag)) {
                    drop.appendChild(drag);
                    //Set the new parentScroll on the nodescroll plugin
                    e.drag.nodescroll.set('parentScroll', e.drop.get('node'));
                }
            }
        });

        //Static Vars
        var goingUp = false, lastY = 0;

        //Get the list of tr's in the lists and make them draggable
        var lis = Y.all('.drag_list tr');
        lis.each(function(v, k) {
            //v.plug(Y.Plugin.Drag);
            //Now you can only drag it from the x in the corner
            //v.dd.addHandle('h2').

            var dd = new Y.DD.Drag({
                node: v,
                //Make it Drop target and pass this config to the Drop constructor
                target: {
                    padding: '0 0 0 20'
                }
            }).plug(Y.Plugin.DDProxy, {
                //Don't move the node at the end of the drag
                moveOnEnd: false
            }).plug(Y.Plugin.DDConstrained, {
                //Keep it inside the .drag_list
                constrain2node: '.drag_list'
            }).plug(Y.Plugin.DDNodeScroll, {
                node: v.get('parentNode')
            });
            dd.addHandle('img.drag_image');

        });

        //add JS-Eventhandler for each move-up/down-button-click (=images)
        Y.all('.buttons .movedownbutton').on('click', function(e) { //move the node 1 element down
            //swap sort-order-values
            var this_order = e.target.ancestor('.draggable_item').one('.sort_order').get('value');
            var other_order = e.target.ancestor('.draggable_item').next('.draggable_item').one('.sort_order').get('value');

            // Stop the button from submitting
            e.preventDefault();
            e.stopPropagation();

            var contextid = M.mod_grouptool.sortlist.contextid;
            var lang = M.mod_grouptool.sortlist.lang;
            var url = M.cfg.wwwroot+"/mod/grouptool/editgroup_ajax.php";
            // Start AJAX Call to update order in DB!
            var cfg = {
                method: 'POST',
                data: 'action=swap&sesskey='+M.cfg.sesskey+'&contextid='+contextid+
                      '&groupA='+e.target.ancestor('.draggable_item').one('input[name="selected[]"]').getAttribute('value')+
                      '&groupB='+e.target.ancestor('.draggable_item').next('.draggable_item').one('input[name="selected[]"]').getAttribute('value'),
                headers: { 'X-Transaction': 'POST reorder groups'},
                on: {
                    start: function(id, args) {
                        Y.log("Start AJAX Call to reorder groups", "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to reorder groups completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            Y.log("AJAX Call to reorder groups successfull\nError ocured:"+response.error, "success", "grouptool");
                        } else {
                            Y.log("AJAX Call to reorder groups successfull\n"+response.message, "success", "grouptool");
                        }
                    },
                    failure: function(id, o, args) {
                        // Show message
                        Y.log("AJAX Call to reorder groups failure\nStatus: "+o.status+"\nStatustext:"+o.statusText, "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to reorder groups ended", "info", "grouptool");
                    }
                }
            };
            Y.io(url, cfg);

            //swap list-elements
            e.target.ancestor('.draggable_item').swap(e.target.ancestor('.draggable_item').next('.draggable_item'));

            if(e.target.ancestor('.draggable_item').previous('.draggable_item') == null) { //first list-element? ==> hide move-up-link
                e.target.ancestor('.draggable_item').next('.draggable_item').all('.moveupbutton').setStyle('visibility', 'visible');
                e.target.ancestor('.draggable_item').all('.moveupbutton').setStyle('visibility', 'hidden');
            }
            if(e.target.ancestor('.draggable_item').next('.draggable_item') == null) { //is it the last list-element? ==> hide move-down-link
                e.target.ancestor('.draggable_item').all('.movedownbutton').setStyle('visibility', 'hidden');
                e.target.ancestor('.draggable_item').previous('.draggable_item').all('.movedownbutton').setStyle('visibility', 'visible');
            }

            e.target.ancestor('.draggable_item').one('input.sort_order').set('value', other_order);
            e.target.ancestor('.draggable_item').previous('.draggable_item').one('input.sort_order').set('value', this_order);
        });
        Y.all('.buttons .moveupbutton').on('click', function(e) { //move the node 1 element up
            //swap sort-order-values
            var this_order = e.target.ancestor('.draggable_item').one('.sort_order').get('value');
            var other_order = e.target.ancestor('.draggable_item').previous('.draggable_item').one('.sort_order').get('value');

            // Stop the button from submitting
            e.preventDefault();
            e.stopPropagation();

            var contextid = M.mod_grouptool.sortlist.contextid;
            var lang = M.mod_grouptool.sortlist.lang;
            var url = M.cfg.wwwroot+"/mod/grouptool/editgroup_ajax.php";
            // Start AJAX Call to update order in DB!
            var cfg = {
                method: 'POST',
                data: 'action=swap&sesskey='+M.cfg.sesskey+'&contextid='+contextid+
                      '&groupA='+e.target.ancestor('.draggable_item').one('input[name="selected[]"]').getAttribute('value')+
                      '&groupB='+e.target.ancestor('.draggable_item').previous('.draggable_item').one('input[name="selected[]"]').getAttribute('value'),
                headers: { 'X-Transaction': 'POST reorder groups'},
                on: {
                    start: function(id, args) {
                        Y.log("Start AJAX Call to reorder groups", "info", "grouptool");
                    },
                    complete: function(id, args) {
                        Y.log("AJAX Call to reorder groups completed", "info", "grouptool");
                    },
                    success: function(id, o, args) {
                        response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                            Y.log("AJAX Call to reorder groups successfull\nError ocured:"+response.error, "success", "grouptool");
                        } else {
                            Y.log("AJAX Call to reorder groups successfull\n"+response.message, "success", "grouptool");
                        }
                    },
                    failure: function(id, o, args) {
                        // Show message
                        Y.log("AJAX Call to reorder groups failure\nStatus: "+o.status+"\nStatustext:"+o.statusText, "error", "grouptool");
                    },
                    end: function(id, args) {
                        Y.log("AJAX Call to reorder groups ended", "info", "grouptool");
                    }
                }
            };
            Y.io(url, cfg);

            if(e.target.ancestor('.draggable_item').next('.draggable_item') == null) { //is it the last list-element? ==> show move-down-link
                e.target.ancestor('.draggable_item').all('.movedownbutton').setStyle('visibility', 'visible');
                e.target.ancestor('.draggable_item').previous('.draggable_item').all('.movedownbutton').setStyle('visibility', 'hidden');
            }
            if(e.target.ancestor('.draggable_item').previous('.draggable_item').previous('.draggable_item') == null) { //will it be the first list-element? ==> hide move-up-link
                e.target.ancestor('.draggable_item').all('.moveupbutton').setStyle('visibility', 'hidden');
                e.target.ancestor('.draggable_item').previous('.draggable_item').all('.moveupbutton').setStyle('visibility', 'visible');
            }

            e.target.ancestor('.draggable_item').one('input.sort_order').set('value', other_order);
            e.target.ancestor('.draggable_item').previous('.draggable_item').one('input.sort_order').set('value', this_order);
            //swap list-elements
            e.target.ancestor('.draggable_item').swap(e.target.ancestor('.draggable_item').previous('.draggable_item'));
        });

        //Create simple targets for the lists.
        var uls = Y.all('.drag_list');
        uls.each(function(v, k) {
            var tar = new Y.DD.Drop({
                node: v
            });
        });

        var checkbox_controls_action = Y.one('.sortlist_container .felement [name="do_class_action"]');
        if (checkbox_controls_action) {
            checkbox_controls_action.on('click', function(e) {
                // Get the new state and continue!
                var newstate = '';
                Y.all('input[name = "class_action"]').each(function (current, bla, nodelist) {
                    if (current.get('checked') == true) {
                        newstate = current.get('value');
                    }
                });
                M.mod_grouptool.sortlist_update_checkboxes(e, newstate);
            });
        }

        Y.one('.simple_select_all').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            Y.all('.class0').set('checked', 'checked');
        });

        Y.one('.simple_select_none').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            Y.all('.class0').set('checked', '');
        });

        return new sortlist(config); //'config' contains the parameter values
    };
    //end of M.mod_grouptool.init_sortlist
  }, '0.0.1', {
      requires:['base','dd-constrain', 'dd-proxy', 'dd-drop', 'dd-scroll', 'io-base']
  });