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
YUI.add('moodle-mod_grouptool-sortlist', function(Y) {
    var SORTLISTNAME = 'sortlist';
    var sortlist = function(Y) {
        sortlist.superclass.constructor.apply(this, arguments);
    }
    Y.extend(sortlist, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            //gets called when it's going to be pluged in
        }

    }, {
        NAME : SORTLISTNAME, //module name is something mandatory.
                                //It should be in lower case without space
                                //as YUI use it for name space sometimes.
        ATTRS : {
                 aparam : {}
        } // Attributs are the parameters sent when the $PAGE->requires->yui_module calls the module.
          // Here you can declare default values or run functions on the parameter.
          // The param names must be the same as the ones declared
          // in the $PAGE->requires->yui_module call.

    });
    M.mod_grouptool = M.mod_grouptool || {}; //this line use existing name path if it exists, ortherwise create a new one.
                                                 //This is to avoid to overwrite previously loaded module with same name.

    M.mod_grouptool.sortlist_update_checkboxes = function(e, classname, newstate) {
        var checkboxes = Y.all('input.'+classname);

        e.preventDefault();

        switch(newstate) {
            case 'checked': //check
                checkboxes.set('checked', 'checked');
                break;
            case 'unchecked': //uncheck
                checkboxes.set('checked', '');
                break;
            default: //toggle by default
                checkboxes.each(function(current, index, nodelist) {
                    if(current.get('checked')) {
                        current.set('checked', '');
                    } else {
                        current.set('checked', 'checked');
                    }
                })
                break;
        }
    }

    M.mod_grouptool.init_sortlist = function(config) { //'config' contains the parameter values
        //Listen for all drop:over events
        //Y.DD.DDM._debugShim = true;

        //enable the drag-symbols when JS is enabled :)
        Y.all('ul.drag_list li.draggable_item .drag_image').removeClass('js_invisible');

        Y.DD.DDM.on('drop:over', function(e) {
            //Get a reference to our drag and drop nodes
            var drag = e.drag.get('node'),
                drop = e.drop.get('node');

            //Are we dropping on a li node?
            if (drop.get('tagName').toLowerCase() === 'li') {
                //Are we not going up?
                if (!goingUp) {
                    drop = drop.next('li.draggable_item');
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
            drag.get('dragNode').addClass('draggable_item');
            drag.get('dragNode').set('innerHTML', drag.get('node').get('innerHTML'));
            drag.get('dragNode').setStyles({
                opacity: '.5',
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
            //set the hidden fields containing the sort order new
            Y.all('ul.drag_list li input.sort_order').each(function(current, index, nodelist) {
                current.setAttribute('value', index);
                if(index == 0) {
                    current.ancestor('li.draggable_item').one('.moveupbutton').setStyle('visibility', 'hidden');
                } else if(index == nodelist.size()-1) {
                    current.ancestor('li.draggable_item').one('.movedownbutton').setStyle('visibility', 'hidden');
                } else {
                    current.ancestor('li.draggable_item').all('.movedownbutton, .moveupbutton').setStyle('visibility', 'visible');
                }

                });
        });
        //Listen for all drag:drophit events
        Y.DD.DDM.on('drag:drophit', function(e) {
            var drop = e.drop.get('node'),
                drag = e.drag.get('node');

            //if we are not on an li, we must have been dropped on a ul
            if (drop.get('tagName').toLowerCase() !== 'li') {
                if (!drop.contains(drag)) {
                    drop.appendChild(drag);
                    //Set the new parentScroll on the nodescroll plugin
                    e.drag.nodescroll.set('parentScroll', e.drop.get('node'));
                }
            }
        });

        //Static Vars
        var goingUp = false, lastY = 0;

        //Get the list of li's in the lists and make them draggable
        var lis = Y.all('.drag_list li');
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
            var other_order = e.target.ancestor('.draggable_item').next('li.draggable_item').one('.sort_order').get('value');

            // Stop the button from submitting
            e.preventDefault();

            if(e.target.ancestor('.draggable_item').previous('li.draggable_item') == null) { //first list-element? ==> hide move-up-link
                e.target.ancestor('.draggable_item').one('.moveupbutton').setStyle('visibility', 'visible');
                e.target.ancestor('.draggable_item').next('li.draggable_item').one('.moveupbutton').setStyle('visibility', 'hidden');
            }
            if(e.target.ancestor('.draggable_item').next('li.draggable_item').next('li.draggable_item') == null) { //will it be the last list-element? ==> hide move-down-link
                e.target.ancestor('.draggable_item').one('.movedownbutton').setStyle('visibility', 'hidden');
                e.target.ancestor('.draggable_item').next('li.draggable_item').one('.movedownbutton').setStyle('visibility', 'visible');
            }

            e.target.ancestor('.draggable_item').one('.sort_order').set('value', other_order);
            e.target.ancestor('.draggable_item').next('li.draggable_item').one('.sort_order').set('value', this_order);
            //swap list-elements
            e.target.ancestor('.draggable_item').swap(e.target.ancestor('.draggable_item').next('li.draggable_item'));
        });
        Y.all('.buttons .moveupbutton').on('click', function(e) { //move the node 1 element up
            //swap sort-order-values
            var this_order = e.target.ancestor('.draggable_item').one('.sort_order').get('value');
            var other_order = e.target.ancestor('.draggable_item').previous('li.draggable_item').one('.sort_order').get('value');

            // Stop the button from submitting
            e.preventDefault();

            if(e.target.ancestor('.draggable_item').next('li.draggable_item') == null) { //is it the last list-element? ==> show move-down-link
                e.target.ancestor('.draggable_item').one('.movedownbutton').setStyle('visibility', 'visible');
                e.target.ancestor('.draggable_item').previous('li.draggable_item').one('.movedownbutton').setStyle('visibility', 'hidden');
            }
            if(e.target.ancestor('.draggable_item').previous('li.draggable_item').previous('li.draggable_item') == null) { //will it be the first list-element? ==> hide move-up-link
                e.target.ancestor('.draggable_item').one('.moveupbutton').setStyle('visibility', 'hidden');
                e.target.ancestor('.draggable_item').previous('li.draggable_item').one('.moveupbutton').setStyle('visibility', 'visible');
            }

            e.target.ancestor('.draggable_item').one('.sort_order').set('value', other_order);
            e.target.ancestor('.draggable_item').previous('li.draggable_item').one('.sort_order').set('value', this_order);
            //swap list-elements
            e.target.ancestor('.draggable_item').swap(e.target.ancestor('.draggable_item').previous('li.draggable_item'));

        });

        //Create simple targets for the lists.
        var uls = Y.all('.drag_list ul');
        uls.each(function(v, k) {
            var tar = new Y.DD.Drop({
                node: v
            });
        });

        var checkbox_controls = Y.all('.checkbox_controls .checkbox_control');
        checkbox_controls.each(function(current, index, nodelist) {
            var classes = nodelist.item(index).getAttribute('class').split(" ");

            for(var i = 0; i < classes.length; i++) {
                if(classes[i] != 'checkbox_control') {
                    current.one('.select_all').on('click', M.mod_grouptool.sortlist_update_checkboxes,  null, classes[i], 'checked');
                    current.one('.select_none').on('click', M.mod_grouptool.sortlist_update_checkboxes,  null, classes[i], 'unchecked');
                    current.one('.toggle_selection').on('click', M.mod_grouptool.sortlist_update_checkboxes,  null, classes[i], null);
                }
            }
        });

        return new sortlist(config); //'config' contains the parameter values
    };
    //end of M.mod_grouptool.init_sortlist
  }, '0.0.1', {
      requires:['base','dd-constrain', 'dd-proxy', 'dd-drop', 'dd-scroll']
  });