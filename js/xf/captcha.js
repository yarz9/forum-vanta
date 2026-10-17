((window, document) =>
{
	'use strict'

	XF.KeyCaptcha = XF.Element.newHandler({
		options: {
			user: null,
			session: null,
			sign: null,
			sign2: null,
		},

		form: null,
		code: null,

		init ()
		{
			this.form = this.target.closest('form')
			XF.uniqueId(this.form)

			this.code = this.form.querySelector('input[name=keycaptcha_code]')
			XF.uniqueId(this.code)

			this.load()
			XF.on(this.form, 'ajax-submit:error', this.reload.bind(this))
			XF.on(this.form, 'ajax-submit:always', this.reload.bind(this))
		},

		load ()
		{
			if (window.s_s_c_onload)
			{
				this.create()
			}
			else
			{
				window.s_s_c_user_id = this.options.user
				window.s_s_c_session_id = this.options.session
				window.s_s_c_captcha_field_id = this.code.getAttribute('id')
				window.s_s_c_submit_button_id = 'sbutton-#-r'
				window.s_s_c_web_server_sign = this.options.sign
				window.s_s_c_web_server_sign2 = this.options.sign2
				document.s_s_c_element = this.form
				document.s_s_c_debugmode = 1

				const div = document.querySelector('#div_for_keycaptcha')
				if (!div)
				{
					document.body.append(XF.createElementFromString('<div id="div_for_keycaptcha"></div>'))
				}

				XF.loadScript('https://backs.keycaptcha.com/swfs/cap.js')
			}
		},

		create ()
		{
			window.s_s_c_onload(this.form.getAttribute('id'), this.code.getAttribute('id'), 'sbutton-#-r')
		},

		reload (e)
		{
			if (!window.s_s_c_onload)
			{
				return
			}

			if (!e.target.matches('form'))
			{
				e.preventDefault()
			}
			this.load()
		},
	})

	XF.ReCaptcha = XF.Element.newHandler({

		options: {
			sitekey: null,
			invisible: null,
		},

		reCaptchaTarget: null,

		reCaptchaId: null,
		invisibleValidated: false,
		reloading: false,

		init ()
		{
			if (!this.options.sitekey)
			{
				return
			}

			const form = this.target.closest('form')

			if (this.options.invisible)
			{
				const reCaptchaTarget = document.createElement('div')
				const formRow = this.target.closest('.formRow')

				XF.display(formRow, 'none')
				formRow.insertAdjacentElement('afterend', reCaptchaTarget)

				this.reCaptchaTarget = reCaptchaTarget

				XF.on(form, 'ajax-submit:before', this.beforeSubmit.bind(this))
			}
			else
			{
				this.reCaptchaTarget = this.target
			}

			XF.on(form, 'ajax-submit:error', this.reload.bind(this))
			XF.on(form, 'ajax-submit:always', this.reload.bind(this))

			if (window.grecaptcha)
			{
				this.create()
			}
			else
			{
				XF.ReCaptcha.Callbacks.push(this.create.bind(this))

				XF.loadScript('https://www.recaptcha.net/recaptcha/api.js?onload=XFReCaptchaCallback&render=explicit')
			}
		},

		create ()
		{
			if (!window.grecaptcha)
			{
				return
			}

			const options = {
				sitekey: this.options.sitekey,
				theme: XF.StyleVariation.getColorScheme(),
			}
			if (this.options.invisible)
			{
				options.size = 'invisible'
				options.callback = this.complete.bind(this)
			}
			this.reCaptchaId = grecaptcha.render(this.reCaptchaTarget, options)
		},

		beforeSubmit (e)
		{
			if (!this.invisibleValidated)
			{
				e.preventDefault()
				e.preventSubmit = true

				grecaptcha.execute()
			}
		},

		complete ()
		{
			this.invisibleValidated = true
			const form = this.target.closest('form')
			XF.trigger(form, 'submit')
		},

		reload ()
		{
			if (!window.grecaptcha || this.reCaptchaId === null || this.reloading)
			{
				return
			}

			this.reloading = true

			setTimeout(() =>
			{
				grecaptcha.reset(this.reCaptchaId)
				this.reloading = false
				this.invisibleValidated = false
			}, 50)
		},
	})
	XF.ReCaptcha.Callbacks = []
	window.XFReCaptchaCallback = () =>
	{
		for (const callback of XF.ReCaptcha.Callbacks)
		{
			callback()
		}
	}

	XF.Turnstile = XF.Element.newHandler({

		options: {
			sitekey: null,
			action: '',
		},

		turnstileTarget: null,

		turnstileId: null,
		reloading: false,

		init ()
		{
			if (!this.options.sitekey)
			{
				return
			}

			const form = this.target.closest('form')

			this.turnstileTarget = this.target

			XF.on(form, 'ajax-submit:error', this.reload.bind(this))
			XF.on(form, 'ajax-submit:always', this.reload.bind(this))

			if (window.turnstile)
			{
				this.create()
			}
			else
			{
				XF.Turnstile.Callbacks.push(this.create.bind(this))

				XF.loadScript('https://challenges.cloudflare.com/turnstile/v0/api.js?onload=XFTurnstileCaptchaCallback')
			}
		},

		create ()
		{
			if (!window.turnstile)
			{
				return
			}

			const options = {
				sitekey: this.options.sitekey,
				theme: XF.StyleVariation.getColorScheme(),
				action: this.options.action,
			}
			this.turnstileId = window.turnstile.render(this.turnstileTarget, options)
		},

		complete ()
		{
			const form = this.target.closest('form')
			XF.trigger(form, 'submit')
		},

		reload ()
		{
			if (!window.turnstile || this.turnstileId === null || this.reloading)
			{
				return
			}

			this.reloading = true

			setTimeout(() =>
			{
				window.turnstile.reset(this.turnstileId)
				this.reloading = false
			}, 50)
		},
	})
	XF.Turnstile.Callbacks = []
	window.XFTurnstileCaptchaCallback = () =>
	{
		for (const callback of XF.Turnstile.Callbacks)
		{
			callback()
		}
	}

	XF.HCaptcha = XF.Element.newHandler({
		options: {
			sitekey: null,
			invisible: null,
		},

		hCaptchaTarget: null,

		hCaptchaId: null,
		invisibleValidated: false,
		reloading: false,

		init ()
		{
			if (!this.options.sitekey)
			{
				return
			}

			const form = this.target.closest('form')

			XF.on(form, 'ajax-submit:error', this.reload.bind(this))
			XF.on(form, 'ajax-submit:always', this.reload.bind(this))

			if (this.options.invisible)
			{
				const hCaptchaTarget = document.createElement('div')
				const formRow = this.target.closest('.formRow')

				XF.display(formRow, 'none')
				formRow.insertAdjacentElement('afterend', hCaptchaTarget)

				this.hCaptchaTarget = hCaptchaTarget

				XF.on(form, 'ajax-submit:before', this.beforeSubmit.bind(this))
			}
			else
			{
				this.hCaptchaTarget = this.target
			}

			if (window.hcaptcha)
			{
				this.create()
			}
			else
			{
				XF.HCaptcha.Callbacks.push(this.create.bind(this))

				XF.loadScript('https://hcaptcha.com/1/api.js?onload=XFHCaptchaCallback&render=explicit')
			}
		},

		create ()
		{
			if (!window.hcaptcha)
			{
				return
			}

			const options = {
				sitekey: this.options.sitekey,
				theme: XF.StyleVariation.getColorScheme(),
			}
			if (this.options.invisible)
			{
				options.size = 'invisible'
				options.callback = this.complete.bind(this)
			}
			this.hCaptchaId = window.hcaptcha.render(this.hCaptchaTarget, options)
		},

		beforeSubmit (e)
		{
			if (!this.invisibleValidated)
			{
				e.preventDefault()
				e.preventSubmit = true

				window.hcaptcha.execute(this.hCaptchaId)
			}
		},

		complete ()
		{
			this.invisibleValidated = true
			const form = this.target.closest('form')
			XF.trigger(form, 'submit')
		},

		reload ()
		{
			if (!window.hcaptcha || this.hCaptchaId === null || this.reloading)
			{
				return
			}

			this.reloading = true

			setTimeout(() =>
			{
				window.hcaptcha.reset(this.hCaptchaId)
				this.reloading = false
				this.invisibleValidated = false
			}, 50)
		},
	})
	XF.HCaptcha.Callbacks = []
	window.XFHCaptchaCallback = () =>
	{
		for (const callback of XF.HCaptcha.Callbacks)
		{
			callback()
		}
	}

	XF.QaCaptcha = XF.Element.newHandler({

		options: {
			url: null,
		},

		reloading: false,

		init ()
		{
			if (!this.options.url)
			{
				return
			}

			const form = this.target.closest('form')

			XF.on(form, 'ajax-submit:error', this.reload.bind(this))
			XF.on(form, 'ajax-submit:always', this.reload.bind(this))
		},

		reload ()
		{
			if (this.reloading)
			{
				return
			}

			this.reloading = true

			XF.ajax('get', this.options.url, this.show.bind(this))
		},

		show (data)
		{
			const target = this.target

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				XF.display(html, 'none')
				target.after(html)

				XF.Animate.fadeUp(target, {
					speed: XF.config.speed.fast,
					complete ()
					{
						XF.Animate.fadeDown(html, {
							speed: XF.config.speed.fast,
						})
						target.remove()
					},
				})

				this.reloading = false
				onComplete(false, html)
			})
		},
	})

	// ################################## GUEST CAPTCHA HANDLER ###########################################

	XF.GuestCaptcha = XF.Element.newHandler({

		options: {
			url: 'index.php?misc/captcha&with_row=1',
			captchaContext: '',
			target: '.js-captchaContainer',
			skip: '[name=more_options]',
		},

		captchaContainer: null,

		initialized: false,

		init ()
		{
			const form = this.target
			this.captchaContainer = form.querySelector(this.options.target)

			XF.on(form, 'focusin', this.initializeCaptcha.bind(this))
			XF.on(form, 'submit', this.submit.bind(this))
			XF.on(form, 'ajax-submit:before', this.submit.bind(this))
		},

		initializeCaptcha (e)
		{
			const activeElement = document.activeElement

			if (this.initialized || activeElement.matches(this.options.skip))
			{
				return
			}

			const rowType = this.captchaContainer.dataset.rowType || ''

			XF.ajax(
				'get',
				XF.canonicalizeUrl(this.options.url),
				{
					row_type: rowType,
					context: this.options.captchaContext,
				},
				this.showCaptcha.bind(this),
			)

			this.initialized = true
		},

		showCaptcha (data)
		{
			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this.captchaContainer.replaceWith(html)

				onComplete()
			})
		},

		submit (e)
		{
			if (!this.initialized)
			{
				const activeElement = document.activeElement

				if (!activeElement.matches(this.options.skip))
				{
					e.preventDefault()
					e.stopPropagation()
				}
			}
		},
	})

	XF.Element.register('key-captcha', 'XF.KeyCaptcha')
	XF.Element.register('re-captcha', 'XF.ReCaptcha')
	XF.Element.register('turnstile', 'XF.Turnstile')
	XF.Element.register('h-captcha', 'XF.HCaptcha')
	XF.Element.register('qa-captcha', 'XF.QaCaptcha')

	XF.Element.register('guest-captcha', 'XF.GuestCaptcha')
})(window, document)
