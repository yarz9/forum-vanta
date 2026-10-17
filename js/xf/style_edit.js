((window, document) =>
{
	'use strict'

	XF.StylePropertiesUpdate = XF.Element.newHandler({
		init ()
		{
			XF.on(
				this.target,
				'ajax-submit:response',
				this.ajaxSubmitResponse.bind(this)
			)
		},

		ajaxSubmitResponse ({ data })
		{
			const { properties } = data
			if (!properties)
			{
				return
			}

			for (const [propertyName, property] of Object.entries(properties))
			{
				this.updateProperty(propertyName, property)
			}
		},

		updateProperty (name, values)
		{
			const row = this.target.querySelector(`.js-property--${name}`)
			if (!row)
			{
				return
			}

			const revertCheck = row.querySelector(
				'input[name="properties_revert[]"]'
			)
			if (revertCheck && revertCheck.checked)
			{
				revertCheck.checked = false
				XF.trigger(revertCheck, 'click')
			}

			const hint = row.querySelector('span.formRow-hint--customState')
			if (hint)
			{
				hint.classList.remove(
					'cssCustomHighlight--custom',
					'cssCustomHighlight--inherited',
					'cssCustomHighlight--added'
				)
				hint.classList.add(
					`cssCustomHighlight--${values.customizationState}`
				)
			}

			this.updatePropertyValues(row, name, values.value)
		},

		updatePropertyValues (row, name, values)
		{
			const inputs = row.querySelectorAll(`[name^="properties[${name}]"]`)
			const inputNameRegex = new RegExp(`^properties\\[${name}\\]`)
			for (const input of inputs.values())
			{
				const subName = input.name
					.replace(inputNameRegex, '')
					.replace(/^\[/, '')
					.replace(/\]$/, '')
				const value = subName ? values[subName] : values
				this.setInputValue(input, value)
			}
		},

		setInputValue (input, value)
		{
			if (
				input instanceof HTMLInputElement &&
				['radio', 'checkbox'].includes(input.type)
			)
			{
				input.checked = String(value) === input.value
			}
			else
			{
				input.value = value !== undefined ? value : ''

				// if (input.tagName === 'TEXTAREA')
				// {
				// 	const editor = input.parentNode.querySelector('.CodeMirror');
				// 	const instance = editor.CodeMirror;
				// 	instance.getDoc().setValue(value);
				// }
			}

			XF.trigger(input, 'input')
		},
	})

	XF.Element.register('style-properties-update', 'XF.StylePropertiesUpdate')
})(window, document)
