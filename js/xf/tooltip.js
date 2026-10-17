((window, document) =>
{
	'use strict'

	// ################################## PREVIEW TOOLTIP ###########################################

	XF.PreviewTooltip = XF.Element.newHandler({
		options: {
			delay: 600,
			previewUrl: null,
		},

		trigger: null,
		tooltip: null,

		init ()
		{
			if (!this.options.previewUrl)
			{
				console.error('No preview URL')
				return
			}

			this.tooltip = new XF.TooltipElement(this.getContent.bind(this), {
				extraClass: 'tooltip--preview',
				html: true,
				loadRequired: true,
			})
			this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, {
				maintain: true,
				delayInLoading: this.options.delay,
				delayIn: this.options.delay,
			})

			this.trigger.init()
		},

		getContent (onContent)
		{
			const options = {
				skipDefault: true,
				skipError: true,
				global: false,
			}

			XF.ajax(
				'get',
				this.options.previewUrl,
				{},
				data => { this.loaded(data, onContent) },
				options,
			)
		},

		loaded (data, onContent)
		{
			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				onContent(html)
			})
		},
	})

	// ################################## SHARE TOOLTIP ###########################################

	XF.ShareTooltip = XF.Element.newHandler({
		options: {
			delay: 300,
			href: null,
			webShare: true,
		},

		trigger: null,
		tooltip: null,

		init ()
		{
			if (this.options.webShare)
			{
				const webShare = XF.Element.applyHandler(this.target, 'web-share', {
					fetch: this.options.href,
					url: this.target.getAttribute('href'),
					hideContainerEls: false,
				})
				if (webShare.isSupported())
				{
					return false
				}
			}

			this.tooltip = new XF.TooltipElement(this.getContent.bind(this), {
				extraClass: 'tooltip--share',
				html: true,
				loadRequired: true,
			})

			this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, {
				maintain: true,
				delayInLoading: this.options.delay,
				delayIn: this.options.delay,
				trigger: 'hover focus click',
				onShow: this.onShow.bind(this),
				onHide: this.onHide.bind(this),
			})

			this.trigger.init()
		},

		getContent (onContent)
		{
			let options = {
				skipDefault: true,
				skipError: true,
				global: false,
			}

			if (this.trigger.wasClickTriggered())
			{
				options.global = true
			}

			XF.ajax(
				'get',
				this.options.href,
				{},
				data => { this.loaded(data, onContent) },
				options,
			)
		},

		loaded (data, onContent)
		{
			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				onContent(html)
			})
		},

		onShow ()
		{
			let activeTooltip = XF.ShareTooltip.activeTooltip
			if (activeTooltip && activeTooltip !== this)
			{
				activeTooltip.hide()
			}

			XF.ShareTooltip.activeTooltip = this
		},

		onHide ()
		{
			if (XF.ShareTooltip.activeTooltip === this)
			{
				XF.ShareTooltip.activeTooltip = null
			}
		},

		show ()
		{
			this.trigger.show()
		},

		hide ()
		{
			this.trigger.hide()
		},
	})
	XF.ShareTooltip.activeTooltip = null

	XF.Element.register('preview-tooltip', 'XF.PreviewTooltip')
	XF.Element.register('share-tooltip', 'XF.ShareTooltip')
})(window, document)
