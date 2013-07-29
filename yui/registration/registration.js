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
YUI.add('moodle-mod_grouptool-registration', function(Y) {
    var REGISTRATIONNAME = 'registration';
    var registration = function(Y) {
        registration.superclass.constructor.apply(this, arguments);
    }
    Y.extend(registration, Y.Base, {
        initializer : function(config) { //'config' contains the parameter values
            //gets called when it's going to be pluged in
        }

    }, {
        NAME : REGISTRATIONNAME, //module name is something mandatory.
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

    M.mod_grouptool.overlay_instance = null;
    M.mod_grouptool.add_overlay = function(properties) {
        this.Y = Y;
        properties.node = Y.one('#'+properties.id);
        if (properties.node) {
            properties.node.on('click', this.display_overlay, this, properties);
        }
    };
    M.mod_grouptool.display_overlay = function(event, args) {
        event.preventDefault();
        if (M.mod_grouptool.overlay_instance === null) {
            var Y = M.mod_grouptool.Y;
            Y.use('overlay', 'io-base', 'event-mouseenter', 'node', 'event-key', function(Y) {
                var members_overlay = {
                    contentlink : null,
                    overlay : null,
                    init : function() {

                        var closebtn = Y.Node.create('<a id="closeoverlay" href="#"><img  src="'+M.util.image_url('t/delete', 'moodle')+'" /></a>');
                        //Create an overlay from markup
                        this.overlay = new Y.Overlay({
                            headerContent: closebtn,
                            bodyContent: '',
                            id: 'memberspopupbox',
                            width:'400px',
                            centered : true,
                            visible : false,
                            constrain : true
                        });
                        this.overlay.render(Y.one(document.body));

                        closebtn.on('click', this.overlay.hide, this.overlay);

                        var boundingBox = this.overlay.get("boundingBox");

                        //  Hide the menu if the user clicks outside of its content
                        boundingBox.get("ownerDocument").on("mousedown", function (event) {
                            var oTarget = event.target;
                            var menuButton = Y.one("#"+args.id);

                            if (!oTarget.compareTo(menuButton) &&
                                !menuButton.contains(oTarget) &&
                                !oTarget.compareTo(boundingBox) &&
                                !boundingBox.contains(oTarget)) {
                                    this.overlay.hide();
                            }
                        }, this);

                        Y.on("key", this.close, closebtn , "down:13", this);
                            closebtn.on('click', this.close, this);
                    },

                    close : function(e) {
                        e.preventDefault();
                        this.contentlink.focus();
                        this.overlay.hide();
                    },

                    display : function(event, args) {
                        this.contentlink = args.node;
                        this.overlay.set('bodyContent', Y.Node.create('<img src="'+M.cfg.loadingicon+'" class="spinner" />'));

                        var fullurl = args.url;
                        if (!args.url.match(/https?:\/\//)) {
                            fullurl = M.cfg.wwwroot + args.url;
                        }

                        var ajaxurl = fullurl + '&ajax=1';

                        var cfg = {
                            method: 'get',
                            context : this,
                            on: {
                                success: function(id, o, node) {
                                    this.display_callback(o.responseText);
                                },
                                failure: function(id, o, node) {
                                    var debuginfo = o.statusText;
                                    if (M.cfg.developerdebug) {
                                        o.statusText += ' (' + ajaxurl + ')';
                                    }
                                    this.display_callback('bodyContent',debuginfo);
                                }
                            }
                        };

                        Y.io(ajaxurl, cfg);
                        this.overlay.show();

                        Y.one('#closeoverlay').focus();
                    },

                    display_callback : function(content) {
                        this.overlay.set('bodyContent', content);
                    },

                    hideContent : function() {
                        this.overlay.hide();
                    }
                };
                members_overlay.init();
                M.mod_grouptool.overlay_instance = members_overlay;
                M.mod_grouptool.overlay_instance.display(event, args);
            });
        } else {
            M.mod_grouptool.overlay_instance.display(event, args);
        }
    };

    M.mod_grouptool.init_registration = function(config) { //'config' contains the parameter values

        return new registration(config); //'config' contains the parameter values
    };
    //end of M.mod_grouptool.init_sortlist

  }, '0.0.1', {
      requires:['base',]
  });