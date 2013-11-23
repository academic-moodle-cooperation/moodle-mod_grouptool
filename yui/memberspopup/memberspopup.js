YUI.add('moodle-mod_grouptool-memberspopup', function (Y, NAME) {

function MEMBERSPOPUP() {
    MEMBERSPOPUP.superclass.constructor.apply(this, arguments);
}

var SELECTORS = {
        CLICKABLELINKS: 'span.memberstooltip > a',
        FOOTER: 'div.moodle-dialogue-ft'
    },

    CSS = {
        ICON: 'icon',
        ICONPRE: 'icon-pre'
    },
    ATTRS = {};

// Set the modules base properties.
MEMBERSPOPUP.NAME = 'moodle-mod_grouptool-memberspopup';
MEMBERSPOPUP.ATTRS = ATTRS;

Y.extend(MEMBERSPOPUP, Y.Base, {
    panel: null,

    initializer: function() {
        Y.one('body').delegate('click', this.display_panel, SELECTORS.CLICKABLELINKS, this);
    },

    display_panel: function(e) {
        if (!this.panel) {
            this.panel = new M.core.tooltip({
                bodyhandler: this.set_body_content,
                footerhandler: this.set_footer,
                initialheadertext: M.util.get_string('loading', 'moodle'),
                initialfootertext: ''
            });
        }

        // Call the tooltip setup.
        this.panel.display_panel(e);
    }
});

M.mod_grouptool = M.mod_grouptool || {};
M.mod_grouptool.memberspopup = M.mod_grouptool.memberspopup || null;
M.mod_grouptool.init_memberspopup = M.mod_grouptool.init_memberspopup || function(config) {
    // Only set up a single instance of the memberspopup.
    if (!M.mod_grouptool.memberspopup) {
        M.mod_grouptool.memberspopup = new MEMBERSPOPUP(config);
    }
    return M.mod_grouptool.membespopup;
};

}, '@VERSION@', {"requires": ["moodle-core-tooltip"]});
