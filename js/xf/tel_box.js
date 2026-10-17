((window, document) =>
{
	'use strict'

	// ################################## TEL BOX HANDLER ###########################################

	XF.TelBox = XF.Element.newHandler({
		options: {
			telInput: '.js-telInput',
			dialCode: '.js-dialCode',
			intlNumb: '.js-intlNumb',
		},

		iti: null,
		telInput: null,
		dialCode: null,
		intlNumb: null,

		init ()
		{
			const target = this.target
			const form = target.closest('form')
			const telInput = target.querySelector(this.options.telInput)
			const dialCode = target.querySelector(this.options.dialCode)
			const intlNumb = target.querySelector(this.options.intlNumb)

			if (!telInput)
			{
				console.error('No tel input found.')
				return
			}

			if (!dialCode)
			{
				console.error('No dial code hidden input found.')
				return
			}

			if (!intlNumb)
			{
				console.error('No international number hidden input found.')
				return
			}

			const iti = window.intlTelInput(
				telInput,
				this.getIntlTelInputOptions(),
			)

			XF.on(form, 'submit', this.beforeSubmit.bind(this))

			this.iti = iti
			this.telInput = telInput
			this.dialCode = dialCode
			this.intlNumb = intlNumb
		},

		getIntlTelInputOptions (telInput)
		{
			return {}
		},

		beforeSubmit (e)
		{
			e.preventDefault()

			const iti = this.iti
			const intlNumbEl = this.intlNumb
			const dialCodeEl = this.dialCode
			const intlNumb = iti.getNumber()
			const countryData = iti.getSelectedCountryData()

			intlNumbEl.value = intlNumb
			dialCodeEl.value = countryData['dialCode']
		},
	})

	XF.Element.register('tel-box', 'XF.TelBox')
})(window, document)
