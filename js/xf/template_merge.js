((window, document) =>
{
	'use strict'

	XF.TemplateMerger = XF.Element.newHandler({
		options: {
			resolveButton: '.js-resolveButton',
			chosenElement: '.diffList-line--u, .diffList-conflict.is-resolved .is-chosen',
		},

		buttons: [],
		submitButtons: [],

		init ()
		{
			const form = this.target
			const buttons = form.querySelectorAll(this.options.resolveButton)
			this.buttons = Array.from(buttons)

			if (buttons.length)
			{
				const submitButtons = form.querySelectorAll('button[type="submit"]')
				submitButtons.forEach(submit =>
				{
					submit.classList.add('is-disabled')
					submit.disabled = true
				})
				this.submitButtons = Array.from(submitButtons)

				XF.onDelegated(form, 'click', this.options.resolveButton, this.resolveClick.bind(this))
			}

			XF.onDelegated(form, 'click', this.options.chosenElement, this.chosenClick.bind(this))
		},

		resolveClick (e)
		{
			const button = e.target.matches(this.options.resolveButton)
				? e.target
				: e.target.closest(this.options.resolveButton)

			const container = button.closest('.js-conflictContainer')
			const hidden = container.querySelector('.js-mergedInput')
			const target = container.querySelectorAll(button.dataset.target)
			const firstTarget = target[0]

			const selectedInput = []
			target.forEach(input =>
			{
				const hidden = input.querySelector('input[type=hidden]')
				if (hidden)
				{
					selectedInput.push(hidden)
				}
			})
			if (selectedInput.length === 0)
			{
				hidden.name = ''
			}
			else if (selectedInput.length === 1)
			{
				hidden.value = selectedInput[0].value
			}
			else
			{
				const val = []
				selectedInput.forEach(input => val.push(input.value))
				hidden.value = val.join('\n')
			}

			for (const child of container.children)
			{
				child.style.display = 'none'
			}
			container.classList.add('is-resolved')

			firstTarget.classList.add('is-chosen')
			firstTarget.style.display = ''

			if (hidden)
			{
				firstTarget.innerHTML = `<span>${ XF.htmlspecialchars(hidden.value) }</span><br>`
			}

			const visibleButtons = [...this.buttons].filter(button =>
			{
				return button.closest('.diffList-resolve').style.display !== 'none'
			})
			if (!visibleButtons.length)
			{
				this.submitButtons.forEach(submit =>
				{
					submit.classList.remove('is-disabled')
					submit.disabled = false
				})
			}

			if (target.length > 1)
			{
				XF.trigger(firstTarget, 'click')
			}
		},

		chosenClick (e)
		{
			const target = e.target.matches(this.options.chosenElement)
				? e.target
				: e.target.closest(this.options.chosenElement)

			const html = target.querySelector('span')

			if (!html)
			{
				return // nothing to edit
			}

			let input = target.querySelector('input[type=hidden]')

			if (!input || !input.name)
			{
				input = target
					.closest('.js-conflictContainer')
					.querySelector('.js-mergedInput')
			}
			if (!input)
			{
				return
			}

			html.style.display = 'none'

			let textarea = target.querySelector('textarea')
			if (!textarea)
			{
				textarea = document.createElement('textarea')
				textarea.className = 'input'
				textarea.rows = 1
				textarea.value = input.value
				target.appendChild(textarea)

				XF.Element.applyHandler(textarea, 'textarea-handler')

				XF.on(textarea, 'blur', () =>
				{
					input.value = textarea.value
					html.innerHTML = `${ XF.htmlspecialchars(textarea.value) }`
					html.style.display = ''
					textarea.style.display = 'none'
				})
			}

			textarea.style.display = ''
			textarea.focus()
		},
	})

	XF.Element.register('template-merger', 'XF.TemplateMerger')
})(window, document)
