((window, document) =>
{
	'use strict'

	XF.WebAuthn = XF.Element.newHandler({
		options: {
			startButton: '.js-webauthnStart',
			verifying: null,
			type: 'create',
			rpName: '',
			rpId: location.hostname,
			userId: 0,
			userName: '',
			userDisplayName: '',
			userVerification: 'discouraged',
			residentKey: 'preferred',
			existingCredentials: [],
			authAttachment: null,
			timeout: 60000,
			attestation: 'none',
			autotrigger: false,
			autosubmit: false,
		},
		target: null,
		form: null,
		challenge: null,

		init ()
		{
			this.form = this.target.closest('form')
			this.challenge = this.form.querySelector('input[name="webauthn_challenge"]').value

			if (this.options.autotrigger)
			{
				this.initWebAuthn()
			}

			let startButton = this.target.querySelector(this.options.startButton)
			if (startButton)
			{
				startButton.addEventListener('click', (e) =>
				{
					this.initWebAuthn()
				})
			}
		},

		initWebAuthn ()
		{
			if (this.options.type === 'create')
			{
				this.typeCreate()
			}
			else if (this.options.type === 'get')
			{
				this.typeGet()
			}
		},

		typeCreate ()
		{
			let submitButton = this.form.querySelector('.formSubmitRow button')
			submitButton.classList.add('is-disabled')
			submitButton.disabled = true

			const target = this.target
			const config = this.options

			XF.WebAuthnProcess.create({
				challenge: this.challenge,
				target,
				config,
				registerFn: this.registerCredentials.bind(this),
			})
		},

		typeGet ()
		{
			let submitButton = this.form.querySelector('.formSubmitRow button')
			if (submitButton !== null)
			{
				submitButton.classList.add('is-disabled')
				submitButton.disabled = true
			}

			const target = this.target
			const config = this.options
			config.allow = []

			this.options.existingCredentials.forEach(id =>
			{
				config.allow.push({
					'id': Uint8Array.from(atob(id), c => c.charCodeAt(0)),
					'type': 'public-key',
				})
			})

			XF.WebAuthnProcess.get({
				challenge: this.challenge,
				target,
				config,
				registerFn: this.registerCredentials.bind(this),
			})
		},

		registerCredentials (payload)
		{
			this.form.querySelector('input[name="webauthn_payload"]').value = JSON.stringify(payload)

			let submitButton = this.form.querySelector('.formSubmitRow button')
			if (submitButton !== null)
			{
				submitButton.classList.remove('is-disabled')
				submitButton.disabled = false
			}
		},
	})

	XF.WebAuthnClick = XF.Event.newHandler({
		eventNameSpace: 'XFWebAuthnClick',

		options: {
			rpName: '',
			rpId: location.hostname,
			userId: 0,
			userName: '',
			userDisplayName: '',
			userVerification: 'discouraged',
			residentKey: 'preferred',
			existingCredentials: [],
			authAttachment: null,
			timeout: 60000,
			attestation: 'none',
			autotrigger: false,
			autosubmit: false,
		},

		processing: false,
		challenge: null,

		init ()
		{
			this.target.classList.add('is-disabled')
		},

		click (e)
		{
			e.preventDefault()

			if (this.processing)
			{
				return
			}

			this.processing = true

			this.initSetup()
		},

		initSetup ()
		{
			const target = this.target

			XF.ajax('get', this.target.href, ({ challenge, rpName, userId, userName, userDisplayName, }) =>
			{
				this.challenge = challenge

				const config = { ...this.options, rpName, userId, userName, userDisplayName }

				XF.WebAuthnProcess.create({
					challenge,
					target,
					config,
					registerFn: this.registerCredentials.bind(this),
				})
			})
		},

		registerCredentials (payload)
		{
			XF.ajax('post', XF.canonicalizeUrl((XF.getApp() === 'admin' ? 'admin.php?' : 'index.php?') + 'account/passkey/add'), {
				webauthn_payload: JSON.stringify(payload),
				webauthn_challenge: this.challenge,
				user_verification: this.options.userVerification,
			}, this.onSuccess.bind(this), { skipDefaultSuccess: true },)
		},

		onSuccess (data)
		{
			this.processing = false
			this.target.classList.remove('is-disabled')

			if (data.status === 'ok' && data.redirect)
			{
				if (data.message)
				{
					XF.flashMessage(data.message, 1000, () => XF.redirect(data.redirect))
				}
				else
				{
					XF.redirect(data.redirect)
				}
			}
			else
			{
				XF.alert('Unexpected response')
			}
		}
	})

	XF.WebAuthnProcess = (() =>
	{
		const create = ({ challenge, target, config, registerFn, }) =>
		{
			config.userId = config.userId.toString()

			let options = {
				publicKey: {
					challenge: Uint8Array.from(challenge, c => c.charCodeAt(0)),
					rp: {
						name: config.rpName,
						id: config.rpId,
					},
					user: {
						id: Uint8Array.from(config.userId, c => c.charCodeAt(0)),
						name: config.userName,
						displayName: config.userDisplayName,
					},
					pubKeyCredParams: [
						{
							alg: -7,
							type: 'public-key',
						},
						{
							alg: -8,
							type: 'public-key',
						},
						{
							alg: -257,
							type: 'public-key',
						}
					],
					authenticatorSelection: {
						userVerification: config.userVerification,
						residentKey: config.residentKey,
					},
					timeout: config.timeout,
					attestation: config.attestation,
				},
			}

			if (config.authAttachment)
			{
				options.publicKey.authenticatorSelection.authenticatorAttachment = config.authAttachment
			}

			if (Array.isArray(config.existingCredentials))
			{
				let exclude = []
				config.existingCredentials.forEach(function (id)
				{
					exclude.push({
						'id': Uint8Array.from(atob(id), c => c.charCodeAt(0)),
						'type': 'public-key',
					})
				})
				options.publicKey.excludeCredentials = exclude
			}

			navigator.credentials.create(options)
				.then((res) =>
				{
					const attestationObject = res.response.attestationObject

					registerFn({
						clientDataJSON: res.response.clientDataJSON ? btoa(String.fromCharCode(...new Uint8Array(res.response.clientDataJSON))) : null,
						attestationObject: attestationObject ? btoa(String.fromCharCode(...new Uint8Array(attestationObject))) : null,
					})
				})
				.then(() =>
				{
					if (config.autosubmit)
					{
						setTimeout(() =>
						{
							const form = target.closest('form')
							if (XF.trigger(form, 'submit'))
							{
								form.submit()
							}
						}, 500)
					}
				})
				.catch(console.error)
		}

		const get = ({ challenge, target, config, registerFn }) =>
		{
			let options = {
				publicKey: {
					challenge: Uint8Array.from(challenge, c => c.charCodeAt(0)),
					allowCredentials: config.allow,
					userVerification: config.userVerification,
					timeout: config.timeout,
				},
			}

			navigator.credentials.get(options)
				.then((res) =>
				{
					if (config.startButton)
					{
						target.querySelector(config.startButton).classList.add('is-disabled')
						target.querySelector(config.startButton).disabled = true
						target.querySelector(config.startButton).textContent = config.verifying
					}

					registerFn({
						id: btoa(String.fromCharCode(...new Uint8Array(res.rawId))),
						authenticatorData: res.response.authenticatorData ? btoa(String.fromCharCode(...new Uint8Array(res.response.authenticatorData))) : null,
						clientDataJSON: res.response.clientDataJSON ? btoa(String.fromCharCode(...new Uint8Array(res.response.clientDataJSON))) : null,
						signature: res.response.signature ? btoa(String.fromCharCode(...new Uint8Array(res.response.signature))) : null,
					})
				})
				.then(() =>
				{
					if (config.autosubmit)
					{
						setTimeout(() =>
						{
							const form = target.closest('form')
							if (XF.trigger(form, 'submit'))
							{
								form.submit()
							}
						}, 500)
					}
				})
				.catch(console.error)
		}

		return {
			create,
			get,
		}
	})()

	XF.Element.register('webauthn', 'XF.WebAuthn')
	XF.Event.register('click', 'webauthn-click', 'XF.WebAuthnClick')
})(window, document)
