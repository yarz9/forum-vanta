var XSIS = window.XSIS || {};

!function(window, document, _undefined)
{
    "use strict";

    XSIS.XSTooltipCopy = XF.Element.newHandler({
        options: {
            copyText: '',
            copy : false
        },

        tooltip: null,
        copy : null,
        init: function() {

            let target = this.target;

            this.tooltip = new XF.Tooltip(target)

            this.copy = new XF.CopyToClipboard(target, {
                copyText : this.options.copyText
            })

            if(this.options.copy)
            {
                this.tooltip.init()
                this.copy.init()
            }

        },
    });

    XSIS.registrationAutoRefresh = XF.Element.newHandler({
        options: {
            cooldown: 5,
            counter: '.js-autoRefreshCounter'
        },

        counter: null,

        init: function() {
            this.counter = this.target.querySelector(this.options.counter);

            this.updateCounter();
            this.refreshPage(this.options.cooldown * 1000);
        },

        updateCounter()
        {
            let t = this;
            let countdown = this.options.cooldown;

            setInterval(function(){
                countdown--;
                if (countdown > 0 && t.counter)
                {
                    t.counter.text(countdown);
                }
            }, 1000);
        },

        refreshPage: function (timeout)
        {
            setTimeout(function(){
                window.location.reload();
            }, timeout);
        }
    });
    XF.Element.register('registration-invites-auto-refresh', 'XSIS.registrationAutoRefresh');
    XF.Element.register('xs-tooltip-copy', 'XSIS.XSTooltipCopy');

}
(window, document);
