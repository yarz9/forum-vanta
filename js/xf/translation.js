((window, document) =>
{
	'use strict'

	XF.TranslateSubmit = XF.Element.newHandler({
		options: {},

		init ()
		{
			XF.on(this.target, 'ajax-submit:response', this.afterSubmit.bind(this))
		},

		afterSubmit (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			if (data.message)
			{
				XF.flashMessage(data.message, 2000)
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				XF.display(html, 'none')

				XF.Animate.fadeUp(this.target, {
					complete: () =>
					{
						this.target.replaceWith(html)
						onComplete(false, html)
						XF.Animate.fadeDown(html)
					},
				})

				return false
			})
		},
	})

	XF.Element.register('translate-submit', 'XF.TranslateSubmit')
})(window, document)
