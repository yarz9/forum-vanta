((window, document) =>
{
	'use strict'

	XF.PaymentProviderContainer = XF.Element.newHandler({

		options: {},

		init ()
		{
			if (!this.target.matches('form'))
			{
				console.error('%o is not a form', this.target)
				return
			}

			XF.on(this.target, 'ajax-submit:response', this.submitResponse.bind(this))
		},

		submitResponse (event)
		{
			const { data } = event

			if (data.providerHtml)
			{
				event.preventDefault()

				const replyContainer = this.target.parentNode.querySelector(
					`.js-paymentProviderReply-${ data.purchasableTypeId }${ data.purchasableId }`,
				)

				XF.setupHtmlInsert(data.providerHtml, (html, container) =>
				{
					replyContainer.innerHTML = html
				})
			}
		},
	})

	XF.BraintreePaymentForm = XF.Element.newHandler({

		options: {
			clientToken: null,
			formStyles: '.js-formStyles',
		},

		abortController: null,

		init ()
		{
			XF.on(this.target, 'submit', this.submit.bind(this))

			const urls = [
				'https://js.braintreegateway.com/web/3.19.0/js/client.min.js',
				'https://js.braintreegateway.com/web/3.19.0/js/hosted-fields.min.js',
			]
			XF.loadScripts(urls, this.postInit.bind(this))

			const overlayContainer = this.target.closest('.overlay-container')
			const overlay = XF.DataStore.get(overlayContainer, 'overlay')
			if (overlay)
			{
				overlay.on('overlay:hidden', () => overlay.destroy())
			}
		},

		postInit ()
		{
			if (!this.options.clientToken)
			{
				console.error('Form must contain a data-client-token attribute.')
				return
			}

			const styleData = this.target.querySelector(this.options.formStyles) || {}
			const styles = styleData ? JSON.parse(styleData.innerHTML) : {}
			const variation = XF.StyleVariation.getVariation()
			const style = styles[variation]
			const options = {
				authorization: this.options.clientToken,
			}

			braintree.client.create(options, (clientErr, clientInstance) =>
			{
				if (clientErr)
				{
					XF.alert(clientErr.message)
					return
				}

				const options = {
					client: clientInstance,
					styles: style,
					fields: {
						number: {
							selector: '#card-number',
							placeholder: '1234 1234 1234 1234',
						},
						expirationDate: {
							selector: '#card-expiry',
							placeholder: 'MM / YY',
						},
						cvv: {
							selector: '#card-cvv',
							placeholder: 'CVC',
						},
					},
				}
				braintree.hostedFields.create(options, (hostedFieldsErr, hostedFieldsInstance) =>
				{
					if (hostedFieldsErr)
					{
						XF.alert(hostedFieldsErr.message)
						return
					}

					const fields = hostedFieldsInstance._fields
					for (const key of Object.keys(fields))
					{
						const elem = fields[key]['containerElement']
						elem.classList.contains('is-disabled')
					}

					hostedFieldsInstance.on('cardTypeChange', e =>
					{
						const brand = (e.cards.length === 1 ? e.cards[0].type : 'unknown')
						const brandClasses = {
							'visa': 'fa-cc-visa',
							'master-card': 'fa-cc-mastercard',
							'american-express': 'fa-cc-amex',
							'discover': 'fa-cc-discover',
							'diners-club': 'fa-cc-diners',
							'jcb': 'fa-cc-jcb',
							'unionpay': 'fa-credit-card-alt',
							'maestro': 'fa-credit-card-alt',
							'unknown': 'fa-credit-card-alt',
						}

						if (brand)
						{
							const brandIconElement = document.querySelector('#brand-icon')
							let faClass = 'fa-credit-card-alt'

							if (brand in brandClasses)
							{
								faClass = brandClasses[brand]
							}

							brandIconElement.setAttribute('class', '')
							brandIconElement.classList.add('fa')
							brandIconElement.classList.add('fa-lg')
							brandIconElement.classList.add(faClass)
						}
					})

					const form = this.target
					XF.on(form, 'submit', e =>
					{
						e.preventDefault()

						hostedFieldsInstance.tokenize((tokenizeErr, payload) =>
						{
							if (tokenizeErr)
							{
								let message = tokenizeErr.message
								const invalidKeys = tokenizeErr.details.invalidFieldKeys
								if (invalidKeys)
								{
									message += ` (${ invalidKeys.join(', ') })`
								}

								XF.alert(message)
								return
							}

							this.response(payload)
						})
					})
				})
			})
		},

		submit (e)
		{
			e.preventDefault()
			e.stopPropagation()
		},

		response (object)
		{
			if (this.abortController)
			{
				this.abortController.abort()
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable('post', this.target.getAttribute('action'), object, this.complete.bind(this), { skipDefaultSuccess: true })
			if (abortController)
			{
				this.abortController = abortController
			}
		},

		complete (data)
		{
			this.abortController = null

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}
		},
	})

	XF.BraintreeApplePayForm = XF.Element.newHandler({

		options: {
			clientToken: null,
			currencyCode: '',
			boardTitle: '',
			title: '',
			amount: '',
		},

		abortController: null,

		init ()
		{
			const urls = [
				'https://js.braintreegateway.com/web/3.19.0/js/client.min.js',
				'https://js.braintreegateway.com/web/3.19.0/js/apple-pay.min.js',
			]
			XF.loadScripts(urls, this.postInit.bind(this))
		},

		postInit ()
		{
			if (!this.options.clientToken)
			{
				console.error('Form must contain a data-client-token attribute.')
				return
			}

			let canMakePayments = false
			if (window.ApplePaySession && ApplePaySession.canMakePayments())
			{
				canMakePayments = true
			}

			if (!canMakePayments)
			{
				return
			}

			braintree.client.create({ authorization: this.options.clientToken }, (clientErr, clientInstance) =>
			{
				if (clientErr)
				{
					XF.alert(clientErr.message)
					return
				}

				braintree.applePay.create({ client: clientInstance }, (applePayErr, applePayInstance) =>
				{
					if (applePayErr)
					{
						XF.alert(applePayErr.message)
						return
					}

					const promise = ApplePaySession.canMakePaymentsWithActiveCard(applePayInstance.merchantIdentifier)
					promise.then(canMakePaymentsWithActiveCard =>
					{
						if (!canMakePaymentsWithActiveCard)
						{
							console.warn('No Apple Pay card available')
							return
						}

						this.target.classList.remove('u-hidden')

						const form = this.target
						const submit = form.querySelector('.js-applePayButton')

						XF.on(submit, 'click', () =>
						{
							const paymentRequest = applePayInstance.createPaymentRequest({
								total: {
									label: this.options.title,
									amount: this.options.amount,
								},
							})

							const session = new ApplePaySession(2, paymentRequest)
							session.onvalidatemerchant = e =>
							{
								applePayInstance.performValidation({
									validationURL: e.validationURL,
									displayName: this.options.boardTitle,
								}, (validationErr, merchantSession) =>
								{
									if (validationErr)
									{
										XF.alert(validationErr.message)
										session.abort()
										return
									}
									session.completeMerchantValidation(merchantSession)
								})
							}

							session.onpaymentauthorized = e =>
							{
								applePayInstance.tokenize({ token: e.payment.token }, (tokenizeErr, payload) =>
								{
									if (tokenizeErr)
									{
										XF.alert(tokenizeErr.message)
										session.completePayment(ApplePaySession.STATUS_FAILURE)
										return
									}
									session.completePayment(ApplePaySession.STATUS_SUCCESS)

									this.response(payload)
								})
							}

							session.begin()
						})
					})
				})
			})
		},

		response (object)
		{
			if (this.abortController)
			{
				this.abortController.abort()
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable('post', this.target.getAttribute('action'), object, this.complete.bind(this), { skipDefaultSuccess: true })
			if (abortController)
			{
				this.abortController = abortController
			}
		},

		complete (data)
		{
			this.abortController = null

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}
		},
	})

	XF.BraintreePayPalForm = XF.Element.newHandler({

		options: {
			clientToken: null,
			paypalButton: '#paypal-button',
			testPayments: false,
		},

		abortController: null,

		init ()
		{
			const urls = [
				'https://www.paypalobjects.com/api/checkout.js',
				'https://js.braintreegateway.com/web/3.19.0/js/client.min.js',
				'https://js.braintreegateway.com/web/3.19.0/js/paypal-checkout.min.js',
				'https://js.braintreegateway.com/web/3.19.0/js/data-collector.min.js',
			]
			XF.loadScripts(urls, this.postInit.bind(this))
		},

		postInit ()
		{
			if (!this.options.clientToken)
			{
				console.error('Form must contain a data-client-token attribute.')
				return
			}

			const options = {
				authorization: this.options.clientToken,
			}

			braintree.client.create(options, (clientErr, clientInstance) =>
			{
				if (clientErr)
				{
					XF.alert(clientErr.message)
					return
				}

				braintree.paypalCheckout.create({ client: clientInstance }, (paypalCheckoutErr, paypalCheckoutInstance) =>
				{
					if (paypalCheckoutErr)
					{
						XF.alert(paypalCheckoutErr.message)
						return
					}

					paypal.Button.render({
						env: this.options.testPayments ? 'sandbox' : 'production',

						payment ()
						{
							return paypalCheckoutInstance.createPayment({
								flow: 'vault',
								enableShippingAddress: false,
							})
						},

						onAuthorize (data, actions)
						{
							return paypalCheckoutInstance.tokenizePayment(data).then(payload =>
							{
								this.response(payload)
							})
						},

						onCancel (data)
						{
							console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2))
						},

						onError (err)
						{
							XF.alert(err.message)
						},
					}, this.options.paypalButton)
				})
			})
		},

		response (object)
		{
			if (this.abortController)
			{
				this.abortController.abort()
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable('post', this.target.getAttribute('action'), object, this.complete.bind(this), { skipDefaultSuccess: true })
			if (abortController)
			{
				this.abortController = abortController
			}
		},

		complete (data)
		{
			this.abortController = null

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}
		},
	})

	XF.Element.register('payment-provider-container', 'XF.PaymentProviderContainer')

	XF.Element.register('braintree-payment-form', 'XF.BraintreePaymentForm')
	XF.Element.register('braintree-apple-pay-form', 'XF.BraintreeApplePayForm')
	XF.Element.register('braintree-paypal-form', 'XF.BraintreePayPalForm')
})(window, document)
