// Put this file in path/to/plugin/amd/src
// You can call it anything you like

define(['jquery', 'core/custom_interaction_events', 'core_message/message_drawer_helper', 'core/templates'],
    function($, CustomEvents, MessageDrawerHelper, Templates) {
        var SELECTORS = {
            MESSAGE_TEXTAREA: '[data-region="send-message-txt"]',
            MESSAGE_USER_BUTTON: '#message-group-button',
            MESSAGE_JUMP: '[data-region="jumpto"]'
        };

        var TEMPLATES = {
            CONTENT: 'core_message/message_jumpto'
        };

        /**
         * Returns the conversation id, 0 if none.
         *
         * @param {object} element jQuery object for the button
         * @return {int}
         */
        var getConversationId = function (element) {
            return parseInt(element.attr('data-conversationid'));
        };

        /**
         * Handles opening the messaging drawer to send a
         * message to a given user.
         *
         * @method enhance
         * @param {object} element jQuery object for the button
         */
        var send = (element) => {
            window.console.log(element);
            element = $(element);

            var args = {
                conversationid: getConversationId(element),
                buttonid: $(element).attr('id'),
            };
            window.console.log(element);
            window.console.log(args);

            Templates.render(TEMPLATES.CONTENT, {})
                .then(function (html) {
                    element.after(html);
                })
                .then(function () {
                    $(SELECTORS.MESSAGE_USER_BUTTON).next().focus(function () {
                        $(SELECTORS.MESSAGE_TEXTAREA).focus();
                    });
                });

            CustomEvents.define(element, [CustomEvents.events.activate]);
            element.on(CustomEvents.events.activate, function (e, data) {
                if ($(e.target).hasClass('active')) {
                    MessageDrawerHelper.hide();
                    $(SELECTORS.MESSAGE_USER_BUTTON).next().attr('tabindex', -1);
                } else {
                    $(SELECTORS.MESSAGE_USER_BUTTON).next().attr('tabindex', 0);
                    if (args.conversationid) {
                        MessageDrawerHelper.showConversation(args);
                    } else {
                        window.console.warn("Group conversation does not exist");
                    }
                }
                $(e.target).focus();
                $(e.target).toggleClass('active');
                e.preventDefault();
                data.originalEvent.preventDefault();
            });
            window.console.log("mod_grouptool: Send was executed");
        };
        return /** @alias module:core_message/message_user_button */ {
            send: send
        };
    });