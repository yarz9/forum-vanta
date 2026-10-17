((window, document) =>
{
	'use strict'

	XF.FormFill = XF.Element.newHandler({
		options: {
			fillers: '.js-FormFiller',
			key: 'fill',
			action: null,
		},

		abortController: null,

		init ()
		{
			if (!this.target.matches('form'))
			{
				console.error('Target must be a form')
				return
			}

			if (!this.options.action)
			{
				this.options.action = this.target.getAttribute('action')
				if (this.options.action.includes('?'))
				{
					this.options.action += '&'
				}
				else
				{
					this.options.action += '?'
				}
				this.options.action += this.options.key + '=1'
			}

			if (!this.options.action)
			{
				console.error('Form filler requires an action option or attribute')
				return
			}

			XF.onDelegated(this.target, 'click', this.options.fillers, this.change.bind(this))
		},

		change ()
		{
			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable(
				'post',
				this.options.action,
				this.target,
				this.onSuccess.bind(this),
			)

			if (abortController)
			{
				this.abortController = abortController
			}
		},

		onSuccess (ajaxData)
		{
			if (!ajaxData.formValues)
			{
				return
			}

			const target = this.target

			Object.entries(ajaxData.formValues).forEach(([selector, value]) =>
			{
				const ctrl = target.querySelector(selector)
				if (ctrl)
				{
					if (['checkbox', 'radio'].includes(ctrl.type))
					{
						ctrl.checked = value ? true : false
						const clickEvent = XF.customEvent('click', {
							triggered: true,
						})
						XF.trigger(ctrl, clickEvent)

						const controlEvent = XF.customEvent(value ? 'control:enabled' : 'control:disabled')
						XF.trigger(ctrl, controlEvent)
					}
					else if (['SELECT', 'INPUT', 'TEXTAREA'].includes(ctrl.tagName))
					{
						ctrl.value = value
						if (ctrl.tagName === 'TEXTAREA')
						{
							const handler = XF.Element.getHandler(ctrl, 'textarea-handler')
							if (handler)
							{
								handler.update()
							}
						}
					}
				}
			})
		},
	})

	XF.Element.register('form-fill', 'XF.FormFill')
})(window, document)
