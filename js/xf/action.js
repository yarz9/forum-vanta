((window, document) =>
{
	'use strict'

	// ################################## ATTRIBUTION HANDLER ###########################################

	XF.AttributionClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFAttributionClick',
		options: {
			contentSelector: null,
		},

		init ()
		{
		},

		click (e)
		{
			const hash = this.options.contentSelector
			const content = document.querySelector(hash)

			if (content)
			{
				e.preventDefault()
				XF.smoothScroll(content, hash, XF.config.speed.normal)
			}
		},
	})

	// ################################## LIKE HANDLER ###########################################

	XF.LikeClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFLikeClick',
		options: {
			likeList: null,
			container: null,
		},

		processing: false,
		container: null,

		init ()
		{
			if (this.options.container)
			{
				this.container = XF.findRelativeIf(this.options.container, this.target)
			}
		},

		click (e)
		{
			e.preventDefault()

			if (this.processing)
			{
				return
			}
			this.processing = true

			const href = this.target.getAttribute('href')

			XF.ajax('POST', href, {}, this.handleAjax.bind(this), { skipDefaultSuccess: true })
				.finally(() =>
				{
					setTimeout(() =>
					{
						this.processing = false
					}, 250)
				})
		},

		handleAjax (data)
		{
			const target = this.target

			XF.trigger(target, XF.customEvent(`xf-${ this.eventType }:before-handleAjax.${ this.eventNameSpace }`, { data }))

			if (data.addClass)
			{
				target.classList.add(data.addClass)
			}
			if (data.removeClass)
			{
				target.classList.remove(data.removeClass)
			}
			if (data.text)
			{
				let label = target.querySelector('.label')
				if (!label)
				{
					label = target
				}
				label.textContent = data.text
			}

			if (XF.hasOwn(data, 'isLiked'))
			{
				target.classList.add('is-liked', data.isLiked)
				if (this.container)
				{
					this.container.classList.toggle('is-liked', data.isLiked)
				}
			}

			const likeList = this.options.likeList ? XF.findRelativeIf(this.options.likeList, target) : null

			if (typeof data.html !== 'undefined' && likeList)
			{
				if (data.html.content)
				{
					XF.setupHtmlInsert(data.html, (html, container) =>
					{
						likeList.innerHTML = ''
						likeList.append(...html.childNodes)
						XF.Transition.addClassTransitioned(likeList, 'is-active')
					})
				}
				else
				{
					XF.Transition.removeClassTransitioned(likeList, 'is-active', () =>
					{
						while (likeList.firstChild)
						{
							likeList.removeChild(likeList.firstChild)
						}
					})
				}
			}

			XF.trigger(target, XF.customEvent(`xf-${ this.eventType }:after-handleAjax.${ this.eventNameSpace }`, { data }))
		},
	})

	// ################################## SWITCH HANDLER ###########################################

	XF.handleSwitchResponse = (target, data, allowRedirect) =>
	{
		let syncTitleAttr = false
		if (data.switchKey)
		{
			let switchActions = target.dataset[XF.toCamelCase('sk-' + data.switchKey)]

			if (switchActions)
			{
				let match, value
				while ((match = switchActions.match(/(\s*,)?\s*(addClass|removeClass|titleAttr):([^,]+)(,|$)/)))
				{
					switchActions = switchActions.substring(match[0].length)

					value = match[3].trim()
					if (value.length)
					{
						switch (match[2])
						{
							case 'addClass':
								target.classList.add(value)
								break
							case 'removeClass':
								target.classList.remove(value)
								break
							case 'titleAttr':
								syncTitleAttr = (value == 'sync')
								break
						}
					}
				}

				switchActions = switchActions.trim()

				if (switchActions.length && !data.text)
				{
					data.text = switchActions
				}
			}
		}

		if (data.addClass)
		{
			target.classList.add(data.addClass)
		}
		if (data.removeClass)
		{
			target.classList.remove(data.removeClass)
		}

		if (data.text)
		{
			let label = target.querySelector(target.dataset.label)
			if (!label)
			{
				label = target
			}
			label.textContent = data.text

			if (syncTitleAttr)
			{
				target.setAttribute('title', data.text)
				target.removeAttribute('data-original-title')
				XF.trigger(target, 'tooltip:refresh')
			}
		}

		if (data.message)
		{
			const doRedirect = (allowRedirect && data.redirect),
				flashLength = doRedirect ? 1000 : 3000

			XF.flashMessage(data.message, flashLength, () =>
			{
				if (doRedirect)
				{
					XF.redirect(data.redirect)
				}
			})
		}
	}

	XF.SwitchClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFSwitchClick',
		options: {
			redirect: false,
			overlayOnHtml: true,
			label: '.js-label',
		},

		processing: false,
		overlay: null,

		init ()
		{
			this.target.dataset.label = this.options.label
		},

		click (e)
		{
			e.preventDefault()

			if (this.processing)
			{
				return
			}
			this.processing = true

			const href = this.target.getAttribute('href')

			XF.ajax('POST', href, {}, this.handleAjax.bind(this), { skipDefaultSuccess: true })
				.finally(() =>
				{
					setTimeout(() =>
					{
						this.processing = false
					}, 250)
				})
		},

		handleAjax (data)
		{
			const target = this.target
			const event = XF.customEvent('switchclick:complete', { data })

			XF.trigger(target, event)

			if (event.defaultPrevented)
			{
				return
			}

			if (data.html && data.html.content && this.options.overlayOnHtml)
			{
				XF.setupHtmlInsert(data.html, (html, container) =>
				{
					if (this.overlay)
					{
						this.overlay.hide()
					}

					const overlay = XF.getOverlayHtml({
						html,
						title: container.h1 || container.title,
					})

					XF.on(overlay.querySelector('form'), 'ajax-submit:response', this.handleOverlayResponse.bind(this))

					this.overlay = XF.showOverlay(overlay)
				})
				return
			}

			this.applyResponseActions(data)

			if (this.overlay)
			{
				this.overlay.hide()
				this.overlay = null
			}
		},

		handleOverlayResponse (e)
		{
			const data = e.data

			if (data.status == 'ok')
			{
				e.preventDefault()

				this.handleAjax(data)
			}
		},

		applyResponseActions (data)
		{
			XF.handleSwitchResponse(this.target, data, this.options.redirect)
		},
	})

	XF.SwitchOverlayClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFSwitchOverlayClick',
		options: {
			redirect: false,
		},

		overlay: null,

		init ()
		{
		},

		click (e)
		{
			e.preventDefault()

			if (this.overlay)
			{
				this.overlay.show()
				return
			}

			const href = this.target.getAttribute('href')

			XF.loadOverlay(href, {
				cache: false,
				init: this.setupOverlay.bind(this),
			})
		},

		setupOverlay (overlay)
		{
			this.overlay = overlay

			const form = overlay.getOverlay().querySelector('form')

			XF.on(form, 'ajax-submit:response', this.handleOverlaySubmit.bind(this))

			overlay.on('overlay:hidden', () =>
			{
				this.overlay = null
			})

			return overlay
		},

		handleOverlaySubmit (e)
		{
			const { data } = e
			if (data.status == 'ok')
			{
				e.preventDefault()

				const overlay = this.overlay
				if (overlay)
				{
					overlay.hide()
				}

				XF.handleSwitchResponse(this.target, data, this.options.redirect)
			}
		},
	})

	// ################################## ALERTS LIST HANDLER ###########################################

	XF.AlertsList = XF.Element.newHandler({
		options: {},

		processing: false,

		init ()
		{
			const markAllRead = XF.findRelativeIf('< .menu-content | .js-alertsMarkRead', this.target)
			if (markAllRead)
			{
				XF.on(markAllRead, 'click', this.markAllReadClick.bind(this))
			}

			const alertToggles = this.target.querySelectorAll('.js-alertToggle')
			alertToggles.forEach(toggle =>
			{
				XF.on(toggle, 'click', this.markReadClick.bind(this))
			})
		},

		_makeAjaxRequest (url, successCallback, requestData)
		{
			if (this.processing)
			{
				return
			}
			this.processing = true

			XF.ajax('POST', url, requestData || {}, successCallback, { skipDefaultSuccess: true })
				.finally(() =>
				{
					setTimeout(() =>
					{
						this.processing = false
					}, 250)
				})
		},

		markAllReadClick (e)
		{
			e.preventDefault()
			this._makeAjaxRequest(e.target.getAttribute('href'), this.handleMarkAllReadAjax.bind(this))
		},

		markReadClick (e)
		{
			e.preventDefault()

			const link = e.currentTarget
			const alert = link.closest('.js-alert')
			const isUnread = alert.classList.contains('is-unread')
			const alertId = alert.dataset.alertId

			this._makeAjaxRequest(
				link.getAttribute('href'),
				this.handleMarkReadAjax.bind(this, alertId),
				{ unread: isUnread ? 0 : 1 },
			)
		},

		handleMarkAllReadAjax (data)
		{
			if (data.message)
			{
				XF.flashMessage(data.message, 3000)
			}

			const alerts = this.target.querySelectorAll('.js-alert')
			alerts.forEach(alert =>
			{
				this.toggleReadStatus(alert, false)
			})
		},

		handleMarkReadAjax (alertId, data)
		{
			if (data.message)
			{
				XF.flashMessage(data.message, 3000)
			}

			const alert = this.target.querySelector('.js-alert[data-alert-id="' + alertId + '"]')
			this.toggleReadStatus(alert, true)
		},

		toggleReadStatus (alert, canMarkUnread)
		{
			const wasUnread = alert.classList.contains('is-unread')
			const toggle = alert.querySelector('.js-alertToggle')
			const tooltip = XF.Element.getHandler(toggle, 'tooltip')
			let phrase = toggle.getAttribute('data-content')

			if (wasUnread)
			{
				alert.classList.remove('is-unread')
				phrase = toggle.getAttribute('data-unread')
			}
			else if (canMarkUnread)
			{
				alert.classList.add('is-unread')
				phrase = toggle.getAttribute('data-read')
			}

			tooltip.tooltip.setContent(phrase)
		},
	})

	// ################################## DRAFT HANDLER ###########################################

	XF.Draft = XF.Element.newHandler({
		options: {
			draftAutosave: 60,
			draftName: 'message',
			draftUrl: null,

			saveButton: '.js-saveDraft',
			deleteButton: '.js-deleteDraft',
			actionIndicator: '.draftStatus',
		},

		lastActionContent: null,
		autoSaveRunning: false,

		init ()
		{
			if (!this.options.draftUrl)
			{
				console.error('No draft URL specified.')
				return
			}

			XF.onDelegated(this.target, this.options.saveButton, 'click', e =>
			{
				e.preventDefault()
				this.triggerSave()
			})

			XF.onDelegated(this.target, this.options.deleteButton, 'click', e =>
			{
				e.preventDefault()
				this.triggerDelete()
			})

			const proxySync = this.syncState.bind(this)

			// set the default value and check it after other JS loads
			this.syncState()
			setTimeout(proxySync, 500)

			XF.on(this.target, 'draft:sync', proxySync)

			setInterval(this.triggerSave.bind(this), this.options.draftAutosave * 1000)
		},

		triggerSave ()
		{
			if (XF.isRedirecting)
			{
				// we're unloading the page, don't try to save any longer
				return
			}

			const event = XF.customEvent('draft:beforesave')

			XF.trigger(this.target, event)
			if (event.defaultPrevented)
			{
				return
			}

			this._executeDraftAction(this.getSaveData())
		},

		triggerDelete ()
		{
			// prevent re-saving the content until it's changed
			const encodedData = new URLSearchParams(this.getSaveData())
			const content = encodedData.toString()
			this.lastActionContent = content

			this._sendDraftAction({ delete: 1 })
		},

		_executeDraftAction (data)
		{
			const encodedData = new URLSearchParams(data)
			const content = encodedData.toString()
			if (content == this.lastActionContent)
			{
				return
			}
			if (this.autoSaveRunning)
			{
				return false
			}

			this.lastActionContent = content
			this._sendDraftAction(data)
		},

		_sendDraftAction (data)
		{
			this.autoSaveRunning = true

			return XF.ajax(
				'post',
				this.options.draftUrl,
				data,
				this.completeAction.bind(this),
				{
					skipDefault: true,
					skipError: true,
					global: false,
				},
			).finally(() =>
			{
				this.autoSaveRunning = false
			})
		},

		completeAction (data)
		{
			const event = XF.customEvent('draft:complete', { data })
			XF.trigger(this.target, event)
			if (event.defaultPrevented || data.draft.saved === false)
			{
				return
			}

			const complete = this.target.querySelector(this.options.actionIndicator)
			complete.classList.remove('is-active')
			complete.textContent = data.complete()
			complete.classList.add('is-active')

			setTimeout(() => complete.classList.remove('is-active'), 2000)
		},

		syncState ()
		{
			const encodedData = new URLSearchParams(this.getSaveData())
			const content = encodedData.toString()
			this.lastActionContent = content
		},

		getSaveData ()
		{
			const target = this.target

			XF.trigger(target, 'draft:beforesync')

			const data = new FormData(target)
			data.delete('_xfToken')
			return data
		},
	})

	// ################################## DRAFT TRIGGER ###########################################

	XF.DraftTrigger = XF.Element.newHandler({
		options: {
			delay: 2500,
		},

		draftHandler: null,
		timer: null,

		init ()
		{
			if (!XF.isElementWithinDraftForm(this.target))
			{
				return
			}

			const form = this.target.closest('form')
			this.draftHandler = XF.Element.getHandler(form, 'draft')

			if (!this.draftHandler)
			{
				return
			}

			XF.on(this.target, 'keyup', this.keyup.bind(this))
		},

		keyup (e)
		{
			clearTimeout(this.timer)

			this.timer = setTimeout(() => this.draftHandler.triggerSave(), this.options.delay)
		},
	})

	// ################################## FOCUS TRIGGER HANDLER ###########################################

	XF.FocusTrigger = XF.Element.newHandler({
		options: {
			display: null,
			activeClass: 'is-active',
		},

		init ()
		{
			if (this.target.hasAttribute('autofocus'))
			{
				this.trigger()
			}
			else
			{
				XF.on(this.target, 'focusin', this.trigger.bind(this), { once: true })
			}
		},

		trigger ()
		{
			const display = this.options.display
			if (display)
			{
				const displayEl = XF.findRelativeIf(display, this.target)
				if (displayEl)
				{
					XF.Transition.addClassTransitioned(displayEl, this.options.activeClass, () => displayEl.scrollIntoView())
				}
			}
		},
	})

	// ################################## POLL BLOCK HANDLER ###########################################

	XF.PollBlock = XF.Element.newHandler({
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

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				XF.display(html, 'none')
				this.target.insertAdjacentElement('afterend', html)

				XF.Animate.fadeUp(this.target, {
					speed: XF.config.speed.normal,
					complete: () =>
					{
						this.target.remove()
						XF.Animate.fadeDown(html)
					},
				})

				onComplete(false, html)
			})
		},
	})

	// ################################## PREVIEW HANDLER ###########################################

	XF.Preview = XF.Element.newHandler({
		options: {
			previewUrl: null,
			previewButton: 'button.js-previewButton',
		},

		previewing: null,

		init ()
		{
			const form = this.target
			const button = XF.findRelativeIf(this.options.previewButton, form)

			if (!this.options.previewUrl)
			{
				console.warn('Preview form has no data-preview-url: %o', form)
				return
			}

			if (!button)
			{
				console.warn('Preview form has no preview button: %o', form)
				return
			}

			XF.on(button, 'click', this.preview.bind(this))
		},

		preview (e)
		{
			e.preventDefault()

			if (this.previewing)
			{
				return false
			}
			this.previewing = true

			const draftHandler = XF.Element.getHandler(this.target, 'draft')
			if (draftHandler)
			{
				draftHandler.triggerSave()
			}

			XF.ajax('post', this.options.previewUrl, this.target, data =>
			{
				if (data.html)
				{
					XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
					{
						XF.overlayMessage(container.title, html)
					})
				}
			}).finally(() =>
			{
				this.previewing = false
			})
		},
	})

	// ################################## SHARE INPUT HANDLER ###########################################

	XF.ShareInput = XF.Element.newHandler({
		options: {
			button: '.js-shareButton',
			input: '.js-shareInput',
			successText: '',
		},

		button: null,
		input: null,

		init ()
		{
			this.button = this.target.querySelector(this.options.button)
			this.input = this.target.querySelector(this.options.input)

			if (navigator.clipboard)
			{
				this.button.classList.remove('is-hidden')
			}

			XF.on(this.button, 'click', this.buttonClick.bind(this))
			XF.on(this.input, 'click', this.inputClick.bind(this))
		},

		buttonClick (e)
		{
			navigator.clipboard.writeText(this.input.value)
				.then(() =>
				{
					XF.flashMessage(this.options.successText ? this.options.successText : XF.phrase('text_copied_to_clipboard'), 3000)
				})
		},

		inputClick (e)
		{
			this.input.select()
		},
	})

	// ################################## COPY TO CLIPBOARD HANDLER ###########################################

	XF.CopyToClipboard = XF.Element.newHandler({
		options: {
			copyText: '',
			copyTarget: '',
			success: '',
		},

		copyText: null,

		init ()
		{
			if (navigator.clipboard)
			{
				this.target.classList.remove('is-hidden')
			}

			if (this.options.copyText)
			{
				this.copyText = this.options.copyText
			}
			else if (this.options.copyTarget)
			{
				const target = document.querySelector(this.options.copyTarget)

				if (target.matches('input[type="text"], textarea'))
				{
					this.copyText = target.value
				}
				else
				{
					this.copyText = target.textContent
				}
			}

			if (!this.copyText)
			{
				console.error('No text to copy to clipboard')
				return
			}

			XF.on(this.target, 'click', this.click.bind(this))
		},

		click ()
		{
			navigator.clipboard.writeText(this.copyText)
				.then(() =>
				{
					if (this.options.success)
					{
						XF.flashMessage(this.options.success, 3000)
					}
					else
					{
						let flashText = XF.phrase('text_copied_to_clipboard')

						if (this.copyText.match(/^[a-z0-9-]+:\/\/[^\s"<>{}`]+$/i))
						{
							flashText = XF.phrase('link_copied_to_clipboard')
						}
						XF.flashMessage(flashText, 3000)
					}
				})
		},
	})

	// ################################## PUSH NOTIFICATION TOGGLE HANDLER ###########################################

	XF.PushToggle = XF.Element.newHandler({
		options: {},

		isSubscribed: false,
		cancellingSub: null,

		init ()
		{
			if (!XF.Push.isSupported())
			{
				this.updateButton(XF.phrase('push_not_supported_label'), false)
				console.error('XF.Push.isSupported() returned false')
				return
			}

			if (Notification.permission === 'denied')
			{
				this.updateButton(XF.phrase('push_blocked_label'), false)
				console.error('Notification.permission === denied')
				return
			}

			this.registerWorker()
		},

		registerWorker ()
		{
			const onRegisterSuccess = () =>
			{
				XF.on(this.target, 'click', this.buttonClick.bind(this))

				XF.on(document, 'push:init-subscribed', () => this.updateButton(XF.phrase('push_disable_label'), true))

				XF.on(document, 'push:init-unsubscribed', () => this.updateButton(XF.phrase('push_enable_label'), true))
			}
			const onRegisterError = () =>
			{
				this.updateButton(XF.phrase('push_not_supported_label'), false)
				console.error('navigator.serviceWorker.register threw an error.')
			}
			XF.Push.registerWorker(onRegisterSuccess, onRegisterError)
		},

		buttonClick (e)
		{
			const onUnsubscribe = () =>
			{
				this.updateButton(XF.phrase('push_enable_label'), true)

				// dismiss the push CTA for the current session
				// after push has just been explicitly disabled.
				XF.Cookie.set('push_notice_dismiss', '1')

				if (XF.config.userId)
				{
					// also remove history entry as this is an explicit unsubscribe
					XF.Push.removeUserFromPushHistory()
				}
			}
			const onSubscribe = () => this.updateButton(XF.phrase('push_disable_label'), true)
			const onSubscribeError = () => this.updateButton(XF.phrase('push_not_supported_label'), false)
			XF.Push.handleToggleAction(onUnsubscribe, false, onSubscribe, onSubscribeError)
		},

		updateButton (phrase, enable)
		{
			this.target.querySelector('.button-text').textContent = phrase
			if (enable)
			{
				this.target.classList.remove('is-disabled')
			}
			else
			{
				this.target.classList.add('is-disabled')
			}
		},
	})

	XF.PushCta = XF.Element.newHandler({
		options: {},

		init ()
		{
			if (XF.config.skipPushNotificationCta)
			{
				return
			}

			if (!XF.Push.isSupported())
			{
				return
			}

			if (Notification.permission === 'denied')
			{
				return
			}

			this.registerWorker()
		},

		registerWorker ()
		{
			const onRegisterSuccess = () =>
			{
				XF.on(document, 'push:init-unsubscribed', () =>
				{
					if (XF.Push.hasUserPreviouslySubscribed())
					{
						try
						{
							XF.Push.handleSubscribeAction(true)
						}
						catch (e)
						{
							XF.Push.removeUserFromPushHistory()
						}
					}
					else
					{
						if (this.getDismissCookie())
						{
							return
						}

						const pushContainer = this.target.closest('.js-enablePushContainer')

						XF.Animate.fadeDown(pushContainer, {
							speed: XF.config.speed.slow,
							complete: this.initLinks.bind(this),
						})
					}
				})
			}
			XF.Push.registerWorker(onRegisterSuccess)
		},

		initLinks ()
		{
			const target = this.target

			Array.from(target.querySelectorAll('.js-enablePushLink')).forEach(element =>
			{
				XF.on(element, 'click', this.linkClick.bind(this))
			})

			Array.from(target.parentElement.querySelectorAll('.js-enablePushDismiss')).forEach(element =>
			{
				XF.on(element, 'click', this.dismissClick.bind(this))
			})
		},

		linkClick (e)
		{
			e.preventDefault()

			this.hidePushContainer()
			this.setDismissCookie(true, 12 * 3600 * 1000) // 12 hours - it's possible the browser may not allow the setup to complete

			XF.Push.handleSubscribeAction(false)
		},

		dismissClick (e)
		{
			e.preventDefault()

			XF.display(e.currentTarget,  'none')

			const enablePushContainer = this.target.closest('.js-enablePushContainer')
			enablePushContainer.classList.add('notice--accent')
			enablePushContainer.classList.remove('notice--primary')

			const initialMessage = this.target.querySelector('.js-initialMessage')
			XF.display(initialMessage, 'none')

			const dismissMessage = this.target.querySelector('.js-dismissMessage')
			XF.display(dismissMessage)

			const dismissTemp = dismissMessage.querySelector('.js-dismissTemp')
			XF.on(dismissTemp, 'click', this.dismissTemp.bind(this))

			const dismissPerm = dismissMessage.querySelector('.js-dismissPerm')
			XF.on(dismissPerm, 'click', this.dismissPerm.bind(this))
		},

		dismissTemp (e)
		{
			e.preventDefault()

			this.hidePushContainer()

			this.setDismissCookie(false)
		},

		dismissPerm (e)
		{
			e.preventDefault()

			this.hidePushContainer()

			this.setDismissCookie(true)
		},

		setDismissCookie (perm, permLength)
		{
			if (perm) // 10 years should do it
			{
				if (!permLength)
				{
					permLength = (86400 * 1000) * 365 * 10 // ~10 years
				}

				XF.Cookie.set(
					'push_notice_dismiss',
					'1',
					new Date(Date.now() + permLength),
				)
			}
			else
			{
				XF.Cookie.set(
					'push_notice_dismiss',
					'1',
				)
			}
		},

		getDismissCookie ()
		{
			return XF.Cookie.get('push_notice_dismiss')
		},

		hidePushContainer ()
		{
			const pushContainer = this.target.closest('.js-enablePushContainer')

			XF.Animate.fadeUp(pushContainer, {
				speed: XF.config.speed.fast,
			})
		},
	})

	XF.Reaction = XF.Element.newHandler({
		options: {
			delay: 200,
			reactionList: null,
		},

		tooltipHtml: null,
		trigger: null,
		tooltip: null,
		href: null,

		loading: false,

		init ()
		{
			if (!this.target.matches('a') || !this.target.getAttribute('href'))
			{
				// no href so can't do anything
				return
			}

			this.href = this.target.getAttribute('href')

			// check if we have a tooltip template. if we do not then it
			// likely means that all reactions (except like) are disabled
			// so there's little point in displaying it.
			const tooltipTemplate = document.querySelector('#xfReactTooltipTemplate')
			if (tooltipTemplate)
			{
				this.tooltipHtml = XF.createElementFromString(tooltipTemplate.innerHTML.trim())

				this.tooltip = new XF.TooltipElement(this.getContent.bind(this), {
					extraClass: 'tooltip--reaction',
					html: true,
				})
				this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, {
					maintain: true,
					delayIn: this.options.delay,
					trigger: 'hover focus touchhold',
					onShow: this.onShow.bind(this),
					onHide: this.onHide.bind(this),
				})
				this.trigger.init()
			}

			XF.on(this.target, 'click', this.actionClick.bind(this))
		},

		getContent ()
		{
			let href = this.href
			href = href.replace(/(\?|&)reaction_id=[^&]*(&|$)/, '$1reaction_id=')

			Array.from(this.tooltipHtml.querySelectorAll('.reaction')).forEach(reaction =>
			{
				const reactionId = reaction.dataset.reactionId
				reaction.href = reactionId ? href + parseInt(reactionId, 10) : false
			})

			Array.from(this.tooltipHtml.querySelectorAll('[data-xf-init~="tooltip"]')).forEach(tooltip =>
			{
				tooltip.dataset.delayIn = 50
				tooltip.dataset.delayOut = 50
			})

			XF.onDelegated(this.tooltipHtml, 'click', '.reaction', this.actionClick.bind(this))

			return this.tooltipHtml
		},

		onShow ()
		{
			const activeTooltip = XF.Reaction.activeTooltip
			if (activeTooltip && activeTooltip !== this)
			{
				activeTooltip.hide()
			}

			XF.Reaction.activeTooltip = this
		},

		onHide ()
		{
			// it's possible for another show event to trigger so don't empty this if it isn't us
			if (XF.Reaction.activeTooltip === this)
			{
				XF.Reaction.activeTooltip = null
			}

			XF.DataStore.remove(this.target, 'tooltip:taphold')
		},

		show ()
		{
			if (this.trigger)
			{
				this.trigger.show()
			}
		},

		hide ()
		{
			if (this.trigger)
			{
				this.trigger.hide()
			}
		},

		actionClick (e)
		{
			e.preventDefault()

			if (XF.DataStore.get(this.target, 'tooltip:taphold') && this.target === e.currentTarget)
			{
				// click originated from taphold event
				XF.DataStore.remove(this.target, 'tooltip:taphold')
				return
			}

			if (this.loading)
			{
				return
			}
			this.loading = true

			const target = e.target.closest('.reaction')

			XF.ajax(
				'post',
				target.getAttribute('href'),
				this.actionComplete.bind(this),
			).finally(() =>
			{
				setTimeout(() =>
				{
					this.loading = false
				}, 250)
			})
		},

		actionComplete (data)
		{
			if (!data.html)
			{
				return
			}

			const target = this.target
			let oldReactionId = target.getAttribute('data-reaction-id')
			const newReactionId = data.reactionId
			const linkReactionId = data.linkReactionId

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this.hide()

				const reaction = html.querySelector('.js-reaction')
				const reactionText = html.querySelector('.js-reactionText')
				const originalReaction = target.querySelector('.js-reaction')
				const originalReactionText = target.querySelector('.js-reactionText')
				const originalHref = target.getAttribute('href')
				let newHref

				if (linkReactionId)
				{
					newHref = originalHref.replace(/(\?|&)reaction_id=\d+(?=&|$)/, '$1reaction_id=' + linkReactionId)
					target.setAttribute('href', newHref)
				}

				if (newReactionId)
				{
					target.classList.add('has-reaction')
					target.classList.remove('reaction--imageHidden')
					if (!oldReactionId)
					{
						// always remove reaction--1 (like) as that is the default state
						oldReactionId = 1
					}
					target.classList.remove('reaction--' + oldReactionId)
					target.classList.add('reaction--' + newReactionId)
					target.setAttribute('data-reaction-id', newReactionId)
				}
				else
				{
					target.classList.remove('has-reaction')
					target.classList.add('reaction--imageHidden')
					if (oldReactionId)
					{
						target.classList.remove('reaction--' + oldReactionId)
						target.classList.add('reaction--' + html.getAttribute('data-reaction-id'))
						target.setAttribute('data-reaction-id', 0)
					}
				}

				originalReaction.parentNode.replaceChild(reaction, originalReaction)
				if (originalReactionText && reactionText)
				{
					originalReactionText.parentNode.replaceChild(reactionText, originalReactionText)
				}
			})

			const reactionList = this.options.reactionList ? XF.findRelativeIf(this.options.reactionList, target) : null
			if (typeof data.reactionList !== 'undefined' && reactionList)
			{
				if (data.reactionList.content)
				{
					XF.setupHtmlInsert(data.reactionList, (html, container) =>
					{
						reactionList.innerHTML = ''
						reactionList.append(...html.childNodes)
						XF.Transition.addClassTransitioned(reactionList, 'is-active')
					})
				}
				else
				{
					XF.Transition.removeClassTransitioned(reactionList, 'is-active', () =>
					{
						while (reactionList.firstChild)
						{
							reactionList.removeChild(reactionList.firstChild)
						}
					})
				}
			}
		},
	})
	XF.Reaction.activeTooltip = null

	XF.BookmarkClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFBookmarkClick',

		processing: false,

		href: null,
		tooltip: null,
		trigger: null,
		tooltipHtml: null,
		clickE: null,

		init ()
		{
			this.href = this.target.getAttribute('href')

			this.tooltip = new XF.TooltipElement(this.getTooltipContent.bind(this), {
				extraClass: 'tooltip--bookmark',
				html: true,
				loadRequired: true,
			})
			this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, {
				maintain: true,
				trigger: '',
			})
			this.trigger.init()
		},

		click (e)
		{
			if (e.button > 0 || e.ctrlKey || e.shiftKey || e.metaKey || e.altKey)
			{
				return
			}

			e.preventDefault()

			this.clickE = e

			if (this.target.classList.contains('is-bookmarked'))
			{
				this.trigger.clickShow(e)
			}
			else
			{
				if (this.processing)
				{
					return
				}
				this.processing = true

				XF.ajax('POST', this.href, { tooltip: 1 }, this.handleSwitchClick.bind(this), { skipDefaultSuccess: true })
					.finally(() =>
					{
						setTimeout(() =>
						{
							this.processing = false
						}, 250)
					})
			}
		},

		handleSwitchClick (data)
		{
			const onReady = () =>
			{
				XF.handleSwitchResponse(this.target, data)
				this.trigger.clickShow(this.clickE)
			}

			if (data.html)
			{
				XF.setupHtmlInsert(data.html, (html, data, onComplete) =>
				{
					if (this.tooltip.requiresLoad())
					{
						this.tooltipHtml = html
						this.tooltip.setLoadRequired(false)
					}
					onReady()
				})
			}
			else
			{
				onReady()
			}
		},

		getTooltipContent (onContent)
		{
			if (this.tooltipHtml && !this.tooltip.requiresLoad())
			{
				this.initializeTooltip(this.tooltipHtml)

				return this.tooltipHtml
			}

			const options = {
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
				this.href,
				{ tooltip: 1 },
				data => this.tooltipLoaded(data, onContent),
				options,
			)
		},

		tooltipLoaded (data, onContent)
		{
			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this.initializeTooltip(html)
				onContent(html)
			})
		},

		initializeTooltip (html)
		{
			const form = html.querySelector('form')
			XF.on(form, 'ajax-submit:response', this.handleOverlaySubmit.bind(this))
		},

		handleOverlaySubmit (e)
		{
			const { data } = e

			if (data.status == 'ok')
			{
				e.preventDefault()

				if (this.trigger)
				{
					this.trigger.hide()
				}

				XF.handleSwitchResponse(this.target, data)

				if (data.switchKey == 'bookmarkremoved')
				{
					const form = e.target
					form.reset()
				}
			}
		},
	})

	XF.BookmarkLabelFilter = XF.Element.newHandler({
		options: {
			target: null,
			showAllLinkTarget: null,
		},

		loading: false,
		filterTarget: null,
		filterLabelInput: null,
		showAllLinkTarget: null,

		tagify: null,

		init ()
		{
			this.filterTarget = XF.findRelativeIf(this.options.target, this.target)
			if (!this.filterTarget)
			{
				console.error('No filter target found.')
				return
			}

			if (this.options.showAllLinkTarget)
			{
				this.showAllLinkTarget = XF.findRelativeIf(this.options.showAllLinkTarget, this.target)
			}

			this.filterLabelInput = this.target.querySelector('.js-labelFilter')
			const tokenInput = XF.Element.getHandler(this.filterLabelInput, 'token-input')

			this.tagify = tokenInput.tagify

			this.tagify.on('remove', this.loadResults.bind(this))
			this.tagify.on('add', this.loadResults.bind(this))
		},

		loadResults (e)
		{
			if (this.loading)
			{
				return
			}

			this.loading = true

			let label = ''
			if (e.type === 'add')
			{
				label = e.detail.data.value
			}

			XF.ajax('get', XF.canonicalizeUrl('index.php?account/bookmarks-popup'), { label }, data =>
			{
				if (data.html)
				{
					if (this.showAllLinkTarget && data.showAllUrl)
					{
						this.showAllLinkTarget.setAttribute('href', data.showAllUrl)
					}

					XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
					{
						this.tagify.dropdown.hide()
						const originalMenuScroller = this.filterTarget.querySelector('.menu-scroller')
						const newMenuScroller = html.querySelector('.menu-scroller')

						originalMenuScroller.innerHTML = ''
						originalMenuScroller.append(...newMenuScroller.childNodes)
					})
				}
			}).finally(() =>
			{
				this.loading = false
			})
		},
	})

	// ################################## CONTENT VOTE HANDLER ###########################################

	XF.ContentVote = XF.Element.newHandler({
		options: {
			contentId: null,
		},

		processing: false,

		init ()
		{
			XF.onDelegated(this.target, 'click', '[data-vote]', this.voteClick.bind(this))
		},

		voteClick (e)
		{
			e.preventDefault()

			const link = e.target

			if (link.classList.contains('is-disabled'))
			{
				return
			}

			if (this.processing)
			{
				return
			}

			this.processing = true

			const href = link.getAttribute('href')

			XF.ajax(
				'POST',
				href,
				{},
				this.handleAjax.bind(this),
				{ skipDefaultSuccess: false },
			).finally(() =>
			{
				setTimeout(() =>
				{
					this.processing = false
				}, 250)
			})
		},

		handleAjax (data)
		{
			this.updateData(data)

			if (this.options.contentId)
			{
				const sharedVotes = document.querySelectorAll(`.js-contentVote[data-content-id="${ this.options.contentId }"]`)
				const target = this.target

				sharedVotes.forEach(el =>
				{
					if (target === el)
					{
						// don't need to do anything on itself
						return
					}

					if (el.matches('[data-xf-init~="content-vote"]'))
					{
						XF.Element.getHandler(el, 'content-vote').updateData(data)
					}
					else
					{
						// this is a content vote display, but not interactive
						this.updateDisplay(el, data)
					}
				})
			}
		},

		updateData (data)
		{
			this.updateDisplay(this.target, data)
		},

		updateDisplay (target, data)
		{
			const voteCount = target.querySelector('.js-voteCount')

			const currentVote = target.querySelector('.is-voted')
			if (currentVote)
			{
				currentVote.classList.remove('is-voted')
			}

			if (data.vote)
			{
				target.querySelector('[data-vote="' + data.vote + '"]').classList.add('is-voted')
				target.classList.add('is-voted')
			}
			else
			{
				target.classList.remove('is-voted')
			}

			XF.Animate.fadeOut(voteCount, {
				speed: XF.config.speed.fast,
				complete ()
				{
					voteCount.setAttribute('data-score', data.voteScore)
					voteCount.textContent = data.voteScoreShort
					if (data.voteScore > 0)
					{
						voteCount.classList.remove('is-negative')
						voteCount.classList.add('is-positive')
					}
					else if (data.voteScore < 0)
					{
						voteCount.classList.remove('is-positive')
						voteCount.classList.add('is-negative')
					}
					else
					{
						voteCount.classList.remove('is-positive')
						voteCount.classList.remove('is-negative')
					}

					XF.Animate.fadeIn(voteCount, { speed: XF.config.speed.fast })
				},
			})
		},
	})

	XF.Event.register('click', 'attribution', 'XF.AttributionClick')
	XF.Event.register('click', 'like', 'XF.LikeClick')
	XF.Event.register('click', 'switch', 'XF.SwitchClick')
	XF.Event.register('click', 'switch-overlay', 'XF.SwitchOverlayClick')

	XF.Element.register('alerts-list', 'XF.AlertsList')
	XF.Element.register('draft', 'XF.Draft')
	XF.Element.register('draft-trigger', 'XF.DraftTrigger')
	XF.Element.register('focus-trigger', 'XF.FocusTrigger')
	XF.Element.register('poll-block', 'XF.PollBlock')
	XF.Element.register('preview', 'XF.Preview')
	XF.Element.register('share-input', 'XF.ShareInput')
	XF.Element.register('copy-to-clipboard', 'XF.CopyToClipboard')
	XF.Element.register('push-toggle', 'XF.PushToggle')
	XF.Element.register('push-cta', 'XF.PushCta')
	XF.Element.register('reaction', 'XF.Reaction')
	XF.Element.register('bookmark-click', 'XF.BookmarkClick')
	XF.Element.register('bookmark-label-filter', 'XF.BookmarkLabelFilter')
	XF.Element.register('content-vote', 'XF.ContentVote')
})(window, document)
