((window, document) =>
{
	'use strict'

	XF.PasswordStrength = XF.Element.newHandler({
		options: {},

		password: null,
		meter: null,
		meterText: null,

		language: {},

		init ()
		{
			this.password = this.target.querySelector('.js-password')
			this.meter = this.target.querySelector('.js-strengthMeter')
			this.meterText = this.target.querySelector('.js-strengthText')

			const langEl = document.querySelector('.js-zxcvbnLanguage')
			if (langEl)
			{
				this.language = JSON.parse(langEl.innerHTML) || {}
			}
			else
			{
				this.language = {}
			}

			XF.on(this.password, 'input', this.input.bind(this))
		},

		input ()
		{
			const password = this.password.value
			const result = zxcvbn(password)
			const score = result.score
			let value
			let message = result.feedback.warning || ''

			// note: the messages in this file are translated elsewhere

			if (password)
			{
				value = (score + 1) * 20

				if (score >= 4)
				{
					message = 'This is a very strong password'
				}
				else if (score >= 3)
				{
					message = 'This is a reasonably strong password'
				}
				else if (!message)
				{
					message = 'The chosen password could be stronger'
				}
			}
			else
			{
				message = 'Entering a password is required'
				value = 0
			}

			this.meter.value = value
			this.meterText.textContent = this.language[message] || ''
		},
	})

	XF.Element.register('password-strength', 'XF.PasswordStrength')
})(window, document)
