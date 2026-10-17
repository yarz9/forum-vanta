((window, document) =>
{
	'use strict'

	// ################################## SUBMIT CHANGE HANDLER ###########################################

	XF.SubmitClick = XF.Event.newHandler({
		eventNameSpace: 'XFSubmitClick',
		options: {
			target: null,
			container: null,
			timeout: 500,
			uncheckedValue: '0',
			disable: null,
		},

		input: null,
		form: null,

		init ()
		{
			let input = this.target

			if (input.matches('label'))
			{
				input = input.querySelector('input[type="radio"], input[type="checkbox"]')
				if (!input)
				{
					return
				}
			}

			this.input = input

			const form = input.closest('form')
			this.form = form ? form : null
		},

		click (e)
		{
			const input = this.input
			const form = this.form
			const target = this.options.target
			const container = this.options.container

			if (!input)
			{
				return
			}

			if (target)
			{
				const unchecked = this.options.uncheckedValue

				setTimeout(() =>
				{
					let data = {}

					if (container)
					{
						const inputs = input.closest(container).querySelectorAll('input, select, textarea')
						data = XF.Serializer.serializeArray(inputs)
					}
					else
					{
						data[input.getAttribute('name')] = input.checked ? input.value : unchecked
					}

					XF.ajax('POST', target, data)
				}, 0)
			}
			else if (form)
			{
				let timer = XF.DataStore.get(form, 'submit-click-timer')
				if (timer)
				{
					clearTimeout(timer)
				}

				XF.on(form, 'ajax-submit:complete', e =>
				{
					const {
						data,
						submitter,
					} = e

					if (data.errors)
					{
						// undo the checked status change
						input.checked = (input.checked ? '' : 'checked')
					}
					else
					{
						// toggle 'dataList-row--disabled' for the parent dataList-row, if there is one
						if (input.getAttribute('type') == 'checkbox' && input.closest('tr.dataList-row') !== null)
						{
							input.closest('tr.dataList-row').classList[(input.checked ? 'remove' : 'add')]('dataList-row--disabled')
						}
					}
				}, { once: true })

				timer = setTimeout(() => XF.trigger(form, 'submit'), this.options.timeout)

				XF.DataStore.set(form, 'submit-click-timer', timer)
			}
			else
			{
				console.error('No target or form to submit on click')
			}
		},
	})

	// ################################## AJAX FORM SUBMISSION ###########################################

	XF.AjaxSubmit = XF.Element.newHandler({
		options: {
			redirect: true,
			skipOverlayRedirect: false,
			forceFlashMessage: false,
			resetComplete: false,
			hideOverlay: true,
			disableSubmit: '.button, input[type="submit"], input[type="reset"], [data-disable-submit]',
			jsonName: null,
			jsonOptIn: null,
			replace: null,
			showReplacement: true,
		},

		submitPending: false,
		submitButton: null,

		init ()
		{
			const form = this.target

			if (!form.matches('form'))
			{
				console.error('%o is not a form', form)
				return
			}

			XF.on(form, 'submit', this.submit.bind(this))
			XF.on(form, 'draft:beforesave', this.draftCheck.bind(this))

			XF.onDelegated(form, 'click', 'input[type=submit], button:not([type]), button[type=submit]', this.submitButtonClicked.bind(this))
		},

		submit (e)
		{
			const submitButton = this.submitButton
			const form = this.target
			const isUploadForm = form.getAttribute('enctype') == 'multipart/form-data'

			if (isUploadForm)
			{
				if (this.options.jsonName)
				{
					// JSON encoding would try to encode the upload which will break it, so prevent submission and error.
					e.preventDefault()
					console.error('JSON serialized forms do not support the file upload-style enctype.')
					XF.alert(XF.phrase('oops_we_ran_into_some_problems_more_details_console'))
					return
				}

				if (!window.FormData)
				{
					// This is an upload type form and the browser cannot support AJAX submission for this.
					return
				}
			}

			if (this.submitButton && this.submitButton.dataset.preventAjax)
			{
				return
			}

			if (XF.debug.disableAjaxSubmit)
			{
				return
			}

			if (this.submitPending)
			{
				e?.preventDefault()
				return
			}

			const ajaxOptions = { skipDefault: true }
			if (isUploadForm)
			{
				ajaxOptions.timeout = 0
			}

			const submitBeforeEvent = XF.customEvent('ajax-submit:before', {
				form,
				handler: this,
				method: form.getAttribute('method') || 'get',
				action: form.getAttribute('action'),
				submitButton,
				preventSubmit: false,
				successCallback: this.submitResponse.bind(this),
				ajaxOptions,
			})

			XF.trigger(form, submitBeforeEvent)

			if (submitBeforeEvent.preventSubmit)
			{
				// preventing any submit
				e?.stopPropagation()
				e?.preventDefault()
				return
			}
			if (submitBeforeEvent.defaultPrevented)
			{
				// preventing ajax submission
				return
			}

			e?.preventDefault()

			// do this in a timeout to ensure that all other submit handlers run
			setTimeout(() =>
			{
				this.submitPending = true

				const formData = XF.getDefaultFormData(form, submitButton, this.options.jsonName, this.options.jsonOptIn)

				this.disableButtons()

				XF.ajax(
					submitBeforeEvent.method,
					submitBeforeEvent.action,
					formData,
					submitBeforeEvent.successCallback,
					submitBeforeEvent.ajaxOptions,
				).finally(() =>
				{
					this.submitButton = null

					// delay re-enable slightly to allow animation to potentially happen
					setTimeout(() =>
					{
						this.submitPending = false
						this.enableButtons()
					}, 300)

					XF.trigger(form, 'ajax-submit:always')
				})
			}, 0)
		},

		disableButtons ()
		{
			Array.from(this.target.querySelectorAll(this.options.disableSubmit)).forEach(button =>
			{
				button.disabled = true
			})
		},

		enableButtons ()
		{
			Array.from(this.target.querySelectorAll(this.options.disableSubmit)).forEach(button =>
			{
				button.disabled = false
			})
		},

		submitResponse (data, status, xhr)
		{
			if (typeof data != 'object')
			{
				XF.alert('Response was not JSON.')
				return
			}

			const form = this.target
			const submitButton = this.submitButton

			const submitResponseEvent = XF.customEvent('ajax-submit:response', {
				data,
			})

			XF.trigger(form, submitResponseEvent)

			if (submitResponseEvent.defaultPrevented)
			{
				return
			}

			const errorEvent = XF.customEvent('ajax-submit:error', {
				data,
			})
			let hasError = false
			let flashShown = false
			let doRedirect = data.redirect && this.options.redirect
			let overlay = form.closest('.overlay')

			if (!overlay || !this.options.hideOverlay)
			{
				overlay = null
			}

			if (doRedirect && this.options.skipOverlayRedirect && overlay)
			{
				doRedirect = false
			}

			if (submitButton && submitButton.getAttribute('data-ajax-redirect'))
			{
				doRedirect = XF.toBoolean(submitButton.dataset['ajaxRedirect'])
			}

			if (data.errorHtml)
			{
				XF.trigger(form, errorEvent)
				if (!errorEvent.defaultPrevented)
				{
					XF.setupHtmlInsert(data.errorHtml, (html, container) =>
					{
						const title = container.h1 || container.title || XF.phrase('oops_we_ran_into_some_problems')
						XF.overlayMessage(title, html)
					})
				}

				hasError = true
			}
			else if (data.errors)
			{
				XF.trigger(form, errorEvent)
				if (!errorEvent.defaultPrevented)
				{
					XF.alert(data.errors)
				}

				hasError = true
			}
			else if (data.exception)
			{
				XF.alert(data.exception)
			}
			else if (data.status == 'ok' && data.message)
			{
				if (doRedirect)
				{
					if (this.options.forceFlashMessage)
					{
						XF.flashMessage(data.message, 1000, () => XF.redirect(data.redirect))
						flashShown = true
					}
					else
					{
						XF.redirect(data.redirect)
					}
				}
				else
				{
					XF.flashMessage(data.message, 3000)
					flashShown = true
				}

				if (overlay)
				{
					XF.trigger(overlay, 'overlay:hide')
				}
			}
			else if (data.html)
			{
				XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
				{
					if (this.options.replace && this.doSubmitReplace(html, onComplete))
					{
						return false // handle on complete when finished
					}

					if (overlay)
					{
						XF.trigger(overlay, 'overlay:hide')
					}

					const childOverlay = XF.getOverlayHtml({
						html,
						title: container.h1 || container.title,
					})
					XF.showOverlay(childOverlay)
				})
			}
			else if (data.status == 'ok')
			{
				if (doRedirect)
				{
					XF.redirect(data.redirect)
				}

				if (overlay)
				{
					XF.trigger(overlay, 'overlay:hide')
				}
			}

			if (!flashShown && data.flashMessage)
			{
				XF.flashMessage(data.flashMessage, 3000)
			}

			// TODO: tie to individual fields?
			// if (data.errors && !errorEvent.defaultPrevented)
			// {
			// }

			const submitCompleteEvent = XF.customEvent('ajax-submit:complete', {
				data,
			})
			XF.trigger(form, submitCompleteEvent)
			if (submitCompleteEvent.defaultPrevented)
			{
				return
			}

			if (this.options.resetComplete && !hasError)
			{
				form.reset()
			}
		},

		doSubmitReplace (html, onComplete)
		{
			const replace = this.options.replace

			if (!replace)
			{
				return false
			}

			const parts = replace.split(' with ')
			const selectorOld = parts[0].trim()
			const selectorNew = parts[1] ? parts[1].trim() : selectorOld
			let oldEl
			let newEl

			if (selectorOld == 'self' || this.target.matches(selectorOld))
			{
				oldEl = this.target
			}
			else
			{
				oldEl = this.target.querySelector(selectorOld)
				if (!oldEl)
				{
					oldEl = document.querySelector(selectorOld)
				}
			}

			if (!oldEl)
			{
				console.error('Could not find old selector \'' + selectorOld + '\'')
				return false
			}

			if (html.matches(selectorNew))
			{
				newEl = html
			}
			else
			{
				newEl = html.querySelector(selectorNew)
			}

			if (!newEl)
			{
				console.error('Could not find new selector \'' + selectorNew + '\'')
				return false
			}

			if (this.options.showReplacement)
			{
				XF.display(newEl, 'none')
				oldEl.insertAdjacentElement('afterend', newEl)

				XF.Animate.fadeUp(oldEl, {
					speed: XF.config.speed.normal,
					complete ()
					{
						oldEl.remove()

						if (newEl)
						{
							XF.activate(newEl)
							onComplete(false)

							XF.Animate.fadeDown(newEl, {
								complete: XF.layoutChange,
							})
						}
					},
				})
			}
			else
			{
				oldEl.insertAdjacentElement('afterend', newEl)
				oldEl.remove()

				if (newEl)
				{
					XF.activate(newEl)
					onComplete(false)
				}
				XF.layoutChange()
			}

			return true
		},

		submitButtonClicked (e)
		{
			this.submitButton = e.target.closest('button')
		},

		draftCheck (e)
		{
			if (this.submitPending)
			{
				e.preventDefault()
			}
		},
	})

	// ################################## SUBMIT FORM ON CHANGE ###########################################

	XF.ChangeSubmit = XF.Element.newHandler({

		options: {
			watch: ':is(input, select, textarea)',
			submitDelay: 0,
		},

		formInitialized: false,

		hasChanges: false, // true if any form values have been changed
		hasPendingChanges: false, // true if we have not *attempted* a save since a change was made
		hasUnsavedChanges: false, // true if we have not *successfully* saved since a change was made

		clickOnSuccessfulSave: null,

		delayTimeout: null,

		init ()
		{
			XF.onDelegated(this.target, 'change', this.options.watch, this.change.bind(this))
			XF.onDelegated(this.target, 'focus', this.options.watch, () =>
			{
				this.clearSubmitTimeout()
			})
			XF.onDelegated(this.target, 'blur', this.options.watch, (e) =>
			{
				if (this.hasPendingChanges)
				{
					this.scheduleSubmit()
				}
			})
			XF.onDelegated(this.target, 'click', '[type=reset]', this.revert.bind(this))
			XF.on(this.target, 'submit', () =>
			{
				this.hasPendingChanges = false
			})
			XF.on(this.target, 'ajax-submit:complete', e =>
			{
				const { data } = e
				if (data.status === 'ok')
				{
					this.hasUnsavedChanges = false

					const clickElement = this.clickOnSuccessfulSave

					if (clickElement)
					{
						this.clickOnSuccessfulSave = null

						setTimeout(() =>
						{
							XF.trigger(clickElement, 'click')
						}, 100)
					}
				}
			})

			// this approach is taken to bind directly to the element as we need it to fire before other handlers
			const elementsToForceChangeSubmit = this.target.querySelectorAll('[data-force-change-submit]')
			elementsToForceChangeSubmit.forEach(element =>
			{
				XF.on(element, 'click', (e) =>
				{
					if (this.hasUnsavedChanges)
					{
						e.stopPropagation()
						e.stopImmediatePropagation()

						this.clickOnSuccessfulSave = e.target

						this.triggerSubmit()
					}
				})
			})
		},

		initForm ()
		{
			if (!this.formInitialized)
			{
				this.formInitialized = true

				XF.Element.applyHandler(this.target, 'ajax-submit', {
					redirect: false,
					forceFlashMessage: false,
				})

				// make double-sure...
				XF.Element.getHandler(this.target, 'ajax-submit').options['redirect'] = false
			}
		},

		change (e)
		{
			this.initForm()

			this.hasChanges = true
			this.hasUnsavedChanges = true
			this.clickOnSuccessfulSave = null

			if (this.validateGroup(e.target.dataset.group))
			{
				this.hasPendingChanges = true // we only set this here as this is used for blur scheduling
				this.scheduleSubmit()
			}
		},

		clearSubmitTimeout ()
		{
			if (this.delayTimeout)
			{
				clearTimeout(this.delayTimeout)
			}
			this.delayTimeout = null
		},

		scheduleSubmit ()
		{
			const delay = this.options.submitDelay

			if (delay > 0)
			{
				this.clearSubmitTimeout()
				this.delayTimeout = setTimeout(this.triggerSubmit.bind(this), delay)
			}
			else
			{
				this.triggerSubmit()
			}
		},

		triggerSubmit ()
		{
			this.clearSubmitTimeout()
			XF.trigger(this.target, 'submit')
		},

		validateGroup (group)
		{
			if (!group)
			{
				return true
			}

			let validated = true

			const groupElements = this.target.querySelectorAll(`[data-group='${ group }']`)
			groupElements.forEach(element =>
			{
				if (element.dataset.required && element.value === '')
				{
					validated = false
					return
				}
			})

			return validated
		},

		revert (e)
		{
			e.preventDefault()

			if (this.hasChanges)
			{
				this.hasChanges = false

				XF.trigger(this.target, 'reset')
				this.triggerSubmit()
			}
		},
	})

	// ################################## USER MENTIONER ###########################################

	XF.UserMentioner = XF.Element.newHandler({
		options: {},

		handler: null,

		init ()
		{
			this.handler = new XF.AutoCompleter(this.target, { url: XF.getAutoCompleteUrl() })
		},
	})

	// ################################## EMOJI COMPLETER ###########################################

	XF.EmojiCompleter = XF.Element.newHandler({
		options: {
			insertTemplate: '${text}',
			excludeSmilies: false,
			triggerString: ':',
			insertEmoji: false,
		},

		handler: null,

		init ()
		{
			if (!XF.config.shortcodeToEmoji)
			{
				return
			}

			let url = 'index.php?misc/find-emoji'
			if (this.options.excludeSmilies)
			{
				url += '&exclude_smilies=1'
			}
			if (this.options.insertEmoji)
			{
				url += '&insert_emoji=1'
			}

			const emojiHandlerOpts = {
				url: XF.canonicalizeUrl(url),
				at: this.options.triggerString,
				keepAt: false,
				insertMode: this.options.insertEmoji ? 'html' : 'text',
				displayTemplate: '<div class="contentRow">' +
					'<div class="contentRow-figure contentRow-figure--emoji">{{{icon}}}</div>' +
					'<div class="contentRow-main contentRow-main--close">{{{text}}}' +
					'<div class="contentRow-minor contentRow-minor--smaller">{{{desc}}}</div></div>' +
					'</div>',
				beforeInsert (value)
				{
					XF.logRecentEmojiUsage(value)

					return value
				},
			}

			if (this.options.insertEmoji)
			{
				emojiHandlerOpts.suffix = ''
			}

			this.handler = new XF.AutoCompleter(
				this.target,
				emojiHandlerOpts,
			)
		},
	})

	// ################################## AUTO SUBMIT ###########################################

	XF.AutoSubmit = XF.Element.newHandler({

		options: {
			hide: true,
			progress: true,
		},

		init ()
		{
			if (XF.trigger(this.target, 'submit'))
			{
				this.target.submit()
			}

			if (this.options.hide)
			{
				const submit = this.target.querySelector('button[type="submit"]')
				XF.display(submit, 'none')
			}
			if (this.options.progress)
			{
				XF.trigger(document, 'xf:action-start')
			}
		},
	})

	// ################################## CHECK ALL HANDLER ###########################################

	XF.CheckAll = XF.Element.newHandler({
		options: {
			container: '< form',
			match: 'input[type="checkbox"]',
		},

		container: null,
		updating: false,

		init ()
		{
			const container = XF.findRelativeIf(this.options.container, this.target, false)

			let containerEls = []

			if (container instanceof NodeList)
			{
				containerEls = Array.from(container)
			}
			else
			{
				containerEls.push(container)
			}

			this.container = containerEls

			containerEls.forEach(container =>
			{
				XF.onDelegated(container, 'click', this.options.match, e =>
				{
					if (this.updating)
					{
						return
					}

					const target = e.target
					if (target === this.target)
					{
						return
					}

					this.updateState()
				})
			})

			XF.on(this.target.closest('form'), 'selectplus:redrawSelected', this.updateState.bind(this))

			this.updateState()

			XF.on(this.target, 'click', this.click.bind(this))
		},

		click (e)
		{
			this.updating = true
			this.getCheckBoxes().forEach(checkbox =>
			{
				checkbox.checked = e.target.checked
				XF.trigger(checkbox, 'click')
			})
			this.updating = false
		},

		updateState ()
		{
			const checkboxes = this.getCheckBoxes()
			let allSelected = checkboxes.length > 0

			checkboxes.forEach(checkbox =>
			{
				if (!checkbox.checked)
				{
					allSelected = false
					return false
				}
			})

			this.target.checked = allSelected
		},

		getCheckBoxes ()
		{
			const checkBoxContainers = this.container
			const checkBoxes = []

			checkBoxContainers.forEach(container =>
			{
				const containerCheckBoxes = container.querySelectorAll(this.options.match)
				checkBoxes.push(...Array.from(containerCheckBoxes))
			})

			return checkBoxes.filter(checkbox => checkbox !== this.target && !checkbox.disabled)
		},
	})

	// ################################## SELECT PLUS HANDLER ###########################################

	XF.SelectPlus = XF.Element.newHandler({
		options: {
			// optional selector for checkboxes within the target
			spCheckbox: null,

			// checkbox ancestor that will receive .is-selected and .is-hover-selected classes
			spContainer: '.js-spContainer',

			// class to apply to the target when multi-selection is active
			activeClass: 'is-spActive',

			// class to apply to spContainers when the contained checkbox is checked
			checkedClass: 'is-spChecked',

			// class to apply to spContainers when the contained checkbox is part of a hovered potential selection
			hoverClass: 'is-spHovered',

			// URL to an action that will provide actionBar HTML
			spMultiBarUrl: null,

			// enable debug mode
			spDebug: true,
		},

		containers: null,
		checkboxes: null,

		multiBar: null,

		isActive: false,
		isShifted: false,

		lastSelected: null,
		lastEntered: null,

		init ()
		{
			this.checkboxes = this.target.querySelectorAll(this.options.spCheckbox ? this.options.spCheckbox : 'input[type="checkbox"]')

			this.containers = Array.from(this.checkboxes).map(checkbox => checkbox.closest(this.options.spContainer))

			this.debug(
				'init; containers: %o, checkboxes: %o',
				this.containers.length,
				this.checkboxes.length,
			)

			if (this.containers.length != this.checkboxes.length)
			{
				console.error('There must be an equal number of checkboxes and containers')
				return
			}

			Array.from(this.checkboxes).forEach(checkbox =>
			{
				XF.on(checkbox, 'click', this.checkboxClick.bind(this))

				const label = checkbox.closest('label')
				if (label)
				{
					XF.on(label, 'mouseenter', this.checkboxEnter.bind(this))
					XF.on(label, 'mouseleave', this.checkboxExit.bind(this))
				}
			})

			// TODO: check touch events?

			XF.on(document, 'keydown', this.keydown.bind(this), { passive: true })
			XF.on(document, 'keyup', this.keyup.bind(this), { passive: true })

			// This workaround prevents shift-selection from selecting label text
			// @see https://stackoverflow.com/questions/1527751/disable-text-selection-while-pressing-shift
			Array.from(this.containers).forEach(container =>
			{
				XF.on(container, 'mousedown', e =>
				{
					if (this.isActive && (e.ctrlKey || e.shiftKey))
					{
						container.onselectstart = () => false
						setTimeout(() =>
						{
							container.onselectstart = null
						}, 0)
					}
				})
			})

			// set initial states
			this.setActive()
			this.redrawSelected()
		},

		// Event handlers

		checkboxClick (e)
		{
			if (this.ignoreClick)
			{
				// so that we can run 'click' on shift-selected items without it mucking everything else up
				return
			}

			this.debug('checkboxClick; delegateTarget: %o', e.currentTarget)

			const index = Array.from(this.checkboxes).indexOf(e.currentTarget)

			if (e.currentTarget.checked && this.isShifted && this.lastSelected !== null)
			{
				this.ignoreClick = true

				const shiftItems = this.getShiftItems(this.checkboxes, index)
				const uncheckedItems = Array.from(shiftItems).filter(item => !item.checked)
				uncheckedItems.forEach(item => item.click())

				this.ignoreClick = false
			}
			else
			{
				this.lastSelected = e.currentTarget.checked ? index : null
			}

			this.setActive(e.currentTarget.checked)
			this.redrawSelected()
		},

		checkboxExit (e)
		{
			this.lastEntered = null
		},

		checkboxEnter (e)
		{
			if (this.isActive)
			{
				// get the index of the checkbox contained within the target <label>
				let checkbox = e.currentTarget.querySelector('input[type="checkbox"]')
				this.lastEntered = Array.from(this.checkboxes).indexOf(checkbox)

				if (this.isShifted)
				{
					this.redrawHover()
				}
			}
		},

		keydown (e)
		{
			if (e.key == 'Shift' && XF.Keyboard.isShortcutAllowed(document.activeElement))
			{
				this.isShifted = true
				this.redrawHover()
			}
		},

		keyup (e)
		{
			if (e.key == 'Shift' && this.isShifted)
			{
				this.isShifted = false
				this.redrawHover()
			}
		},

		// Methods

		getShiftItems (items, index)
		{
			if (index !== null && this.lastSelected !== null)
			{
				let slicedItems = Array.from(items).slice(Math.min(index, this.lastSelected), Math.max(index, this.lastSelected) + 1)

				this.debug('shiftItems:', slicedItems)

				return slicedItems
			}

			return []
		},

		setActive (forceActive)
		{
			const previouslyActive = this.isActive

			this.isActive = forceActive ? true : Array.from(this.checkboxes).some(checkbox => checkbox.checked)

			this.deployMultiBar()

			if (this.isActive != previouslyActive)
			{
				this.debug('setActive: %s', this.isActive)

				XF.trigger(this.target, this.isActive ? 'selectplus:activate' : 'selectplus:deactivate', {
					selectPlus: this,
				})

				XF.Transition.toggleClassTransitioned(this.target, this.options.activeClass, this.isActive)

				XF.Transition.toggleClassTransitioned(document.body, 'is-spDocTriggered', this.isActive)
			}
		},

		redrawSelected ()
		{
			XF.trigger(this.target, 'selectplus:redraw-selected', {
				selectPlus: this,
			})

			this.checkboxes.forEach((checkbox, i) =>
			{
				const newCheckState = checkbox.checked
				const container = this.containers[i]

				XF.Transition.toggleClassTransitioned(container, this.options.checkedClass, newCheckState)

				if (checkbox.dataset.checkState != newCheckState)
				{
					XF.trigger(container, 'selectplus:toggle-item', {
						selectPlus: this,
						checkState: newCheckState,
					})
				}

				checkbox.dataset.checkState = newCheckState
			})
		},

		redrawHover ()
		{
			XF.trigger(this.target, 'selectplus:redraw-hover', {
				selectPlus: this,
			})

			if (this.lastSelected !== null && this.lastEntered !== null && this.isShifted)
			{
				const hovered = this.getShiftItems(this.containers, this.lastEntered)

				this.debug('redrawHover: lastSelected: %s, lastEntered: %s', this.lastSelected, this.lastEntered)

				Array.from(this.containers).forEach(container =>
				{
					if (!hovered.includes(container))
					{
						container.classList.toggle(this.options.hoverClass)
					}
				})

				hovered.forEach(hover =>
				{
					XF.Transition.toggleClassTransitioned(hover, this.options.hoverClass, true)
				})
			}
			else
			{
				this.containers.forEach(container =>
				{
					XF.Transition.toggleClassTransitioned(container, this.options.hoverClass, false)
				})
			}
		},

		deployMultiBar ()
		{
			if (this.isActive && this.options.spMultiBarUrl)
			{
				XF.loadMultiBar(
					this.options.spMultiBarUrl,
					this.checkboxes,
					{
						cache: false,
						init (MultiBar)
						{
							if (this.MultiBar)
							{
								this.MultiBar.destroy()
							}
							this.MultiBar = MultiBar
						},
					},
					{ fastReplace: (this.MultiBar ? true : false) },
				)
			}
			else if (!this.active && this.MultiBar)
			{
				this.MultiBar.hide()
			}
		},

		debug (...args)
		{
			if (this.options.spDebug)
			{
				args[0] = 'SelectPlus:' + args[0]
				console.log(...args)
			}
		},
	})

	// ################################## DESC LOADER HANDLER ###########################################

	XF.DescLoader = XF.Element.newHandler({
		options: {
			descUrl: null,
		},

		container: null,
		changeTimer: null,
		abortController: null,

		init ()
		{
			if (!this.options.descUrl)
			{
				console.error('Element must have a data-desc-url value')
				return
			}

			const container = this.target.parentNode.querySelector('.js-descTarget')
			if (!container)
			{
				console.error('Target element must have a .js-descTarget sibling')
				return
			}
			this.container = container

			XF.on(this.target, 'change', this.change.bind(this))
		},

		change ()
		{
			if (this.changeTimer)
			{
				clearTimeout(this.changeTimer)
			}

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			this.changeTimer = setTimeout(this.onTimer.bind(this), 200)
		},

		onTimer ()
		{
			const value = this.target.value

			if (!value)
			{
				XF.Animate.fadeUp(this.container, {
					speed: XF.config.speed.fast,
				})
				return
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable('post', this.options.descUrl, { id: value }, this.onLoad.bind(this))

			if (abortController)
			{
				this.abortController = abortController
			}
		},

		onLoad (data)
		{
			const containerEl = this.container

			if (data.description)
			{
				XF.setupHtmlInsert(data.description, (html, container, onComplete) =>
				{
					XF.Animate.fadeUp(containerEl, {
						speed: XF.config.speed.fast,
						complete ()
						{
							containerEl.innerHTML = ''
							if (XF.isCreatedContainer(html))
							{
								containerEl.append(...html.childNodes)
							}
							else
							{
								containerEl.append(html)
							}

							XF.Animate.fadeDown(containerEl)
						},
					})
				})
			}
			else
			{
				XF.Animate.fadeUp(containerEl, {
					speed: XF.config.speed.fast,
				})
			}

			this.abortController = null
		},
	})

	// ################################## CONTROL DISABLER HANDLER ###########################################

	XF.Disabler = XF.Element.newHandler({
		options: {
			container: '< li | ul, ol, dl',
			controls: 'input, select, textarea, button, .js-attachmentUpload',
			hide: false,
			instant: false,
			optional: false,
			invert: false, // if true, system will disable on checked
			autofocus: true,
		},

		container: null,
		containers: [],

		init ()
		{
			const containers = XF.findRelativeIf(
				this.options.container,
				this.target,
				false
			)

			if (containers instanceof Element)
			{
				this.containers = [containers]
			}
			if (containers instanceof NodeList)
			{
				this.containers = Array.from(containers)
			}

			if (!this.containers.length)
			{
				if (!this.options.optional)
				{
					console.error('Could not find the disabler control container')
				}
			}

			const input = this.target
			const form = input.closest('form')

			if (form)
			{
				XF.on(form, 'reset', this.formReset.bind(this))
			}

			if (input.matches('[type="radio"]'))
			{
				const context = form || document.body
				const name = input.getAttribute('name')

				// radios only fire events for the element we click normally, so we need to know
				// when we move away from the value by firing every radio's handler for every click
				XF.onDelegated(context, 'click', `input[type="radio"][name="${ name }"]`, this.click.bind(this))
			}
			else if (input.matches('option'))
			{
				const select = input.closest('select')
				XF.on(select, 'change', e =>
				{
					const selectedOption = Array.from(select.options).find(option => option.selected)
					const handler = XF.Element.getHandler(selectedOption, 'disabler')

					if (selectedOption !== input && handler && handler.getOption('container') === this.options.container)
					{
						return
					}

					this.recalculate(false)
				})
			}
			else
			{
				XF.on(input, 'click', this.click.bind(this))
			}

			// this ensures that nested disablers are disabled properly
			XF.on(input, 'control:enabled', this.recalculateAfter.bind(this))
			XF.on(input, 'control:disabled', this.recalculateAfter.bind(this))

			// this ensures that dependent editors are initialised properly as disabled if needed
			this.containers.forEach(container =>
			{
				XF.on(container, 'editor:init', this.recalculateAfter.bind(this), { once: true })
			})

			this.recalculate(true)
		},

		click (e)
		{
			const noSelect = e.triggered || false
			this.recalculateAfter(false, noSelect)
		},

		formReset (e)
		{
			this.recalculateAfter(false, true)
		},

		recalculateAfter (init, noSelect)
		{
			setTimeout(() => this.recalculate(init, noSelect), 0)
		},

		recalculate (init, noSelect)
		{
			const input = this.target
			const speed = (init || this.options.instant) ? 0 : XF.config.speed.fast

			let enable
			if (input.disabled)
			{
				enable = false
			}
			else
			{
				let selected
				if (input.matches('option'))
				{
					const select = input.closest('select')
					selected = select.options[select.selectedIndex] === input
				}
				else
				{
					selected = input.checked
				}

				enable = this.options.invert ? !selected : selected
			}

			let select = container =>
			{
				if (noSelect || !this.options.autofocus)
				{
					return
				}
				Array.from(container.querySelectorAll('input:not([type=hidden], [type=file]), textarea, select, button')).filter(el => el !== input)[0]?.focus()
			}

			this.containers.forEach(container =>
			{
				const controls = Array.from(container.querySelectorAll(this.options.controls)).filter(el => el !== input)

				if (enable)
				{
					container.disabled = false
					container.classList.remove('is-disabled')

					controls.forEach(ctrl =>
					{
						ctrl.disabled = false
						ctrl.classList.remove('is-disabled')

						if (ctrl.tagName === 'SELECT' && ctrl.classList.contains('is-readonly'))
						{
							ctrl.disabled = true
						}

						XF.trigger(ctrl, 'control:enabled')
					})

					if (this.options.hide)
					{
						if (init)
						{
							XF.display(container)
						}
						else
						{
							XF.Animate.slideDown(container, {
								speed,
								complete ()
								{
									XF.layoutChange()
									select(container)
								},
							})
						}

						XF.trigger(container, 'toggle:shown')
						XF.layoutChange()
					}
					else if (!init)
					{
						select(container)
					}
				}
				else
				{
					if (this.options.hide)
					{
						if (init)
						{
							XF.display(container, 'none')
						}
						else
						{
							XF.Animate.slideUp(container, {
								speed,
								complete: XF.layoutChange,
							})
						}

						XF.trigger(container, 'toggle:hidden')
						XF.layoutChange()
					}

					container.disabled = true
					container.classList.add('is-disabled')

					controls.forEach(ctrl =>
					{
						ctrl.disabled = true
						ctrl.classList.add('is-disabled')

						XF.trigger(ctrl, 'control:disabled')

						let disabledVal = ctrl.dataset.disabled
						if (disabledVal !== null && typeof (disabledVal) !== 'undefined')
						{
							ctrl.value = disabledVal
						}
					})
				}
			})
		},
	})

	// ################################## FIELD ADDER ###########################################

	XF.FieldAdder = XF.Element.newHandler({

		options: {
			incrementFormat: null,
			formatCaret: true,
			removeClass: null,
			cloneInit: false,
			remaining: -1,
		},

		clone: null,
		cloned: false,
		created: false,

		init ()
		{
			// Clear the cached values of any child elements (except checkboxes)
			const elements = this.target.querySelectorAll('input:not([type=checkbox]), select, textarea')
			elements.forEach(element =>
			{
				if (element.tagName === 'SELECT')
				{
					const options = element.querySelectorAll('option')
					options.forEach(option =>
					{
						option.selected = option.defaultSelected
					})
				}
				else
				{
					const defaultValue = element.dataset.defaultValue || element.defaultValue || ''
					element.value = defaultValue
				}
			})

			if (this.options.cloneInit)
			{
				this.clone = this.target.cloneNode(true)
			}

			XF.on(this.target, 'keypress', this.handleEvent.bind(this))
			XF.on(this.target, 'change', this.handleEvent.bind(this))
			XF.on(this.target, 'paste', this.handleEvent.bind(this))
			XF.on(this.target, 'input', this.handleEvent.bind(this))
		},

		handleEvent (e)
		{
			if (e.target.readOnly || this.cloned)
			{
				return
			}

			this.cloned = true
			if (!this.clone)
			{
				this.clone = this.target.cloneNode(true)
			}

			XF.off(this.target, e.type)
			this.create()
		},

		create ()
		{
			if (this.created)
			{
				return
			}

			this.created = true

			if (this.options.remaining == 0)
			{
				return
			}

			const incrementFormat = this.options.incrementFormat
			const caret = (this.options.formatCaret ? '^' : '')

			if (this.options.incrementFormat)
			{
				const incrementRegex = new RegExp(caret + XF.regexQuote(incrementFormat).replace('\\{counter\\}', '(\\d+)'))
				let cloneInputs = this.clone.querySelectorAll('input, select, textarea')

				cloneInputs.forEach(input =>
				{
					let name = input.name
					name = name.replace(incrementRegex, (prefix, counter) =>
					{
						return incrementFormat.replace('{counter}', parseInt(counter, 10) + 1)
					})

					input.name = name
				})
			}

			if (this.options.remaining > 0)
			{
				this.clone.setAttribute('data-remaining', this.options.remaining - 1)
			}

			let cloneInputs = this.clone.querySelectorAll('input, select, textarea')
			cloneInputs.forEach(input =>
			{
				if (input.tagName === 'SELECT')
				{
					let options = input.querySelectorAll('option')
					options.forEach(option =>
					{
						option.selected = option.defaultSelected
					})
				}
				else if (typeof input.defaultValue === 'string')
				{
					input.value = input.defaultValue
				}
			})

			this.target.insertAdjacentElement('afterend', this.clone)

			if (this.options.removeClass)
			{
				this.target.classList.remove(...this.options.removeClass.split(' '))
			}

			XF.activate(this.clone)
			XF.layoutChange()
		},
	})

	// ################################## FORM SUBMIT ROWS ###########################################

	XF.FormSubmitRow = XF.Element.newHandler({
		options: {
			container: '.block-container',
			fixedChild: '.formSubmitRow-main',
			stickyClass: 'is-sticky',
			topOffset: 100,
			minWindowHeight: 281,
		},

		container: null,
		fixedParent: null,
		fixEl: null,
		fixElHeight: 0,
		winHeight: 0,
		containerTop: 0,
		containerBorderLeftWidth: 0,
		topOffset: 0,
		elBottom: 0,
		state: 'normal',
		windowTooSmall: false,

		init ()
		{
			if (!XF.config.enableFormSubmitSticky)
			{
				return
			}

			const target = this.target
			const container = target.closest(this.options.container)
			if (!container)
			{
				console.error('Cannot float submit row, no container')
				return
			}

			this.container = container

			this.topOffset = this.options.topOffset
			this.fixEl = target.querySelector(this.options.fixedChild)

			XF.on(window, 'scroll', this.onScroll.bind(this))
			XF.on(window, 'resize', this.recalcAndUpdate.bind(this))

			const fixedParent = XF.getFixedOffsetParent(target)
			if (!fixedParent.matches('html'))
			{
				this.fixedParent = fixedParent
				XF.on(fixedParent, 'scroll', this.onScroll.bind(this))
			}

			XF.on(document.body, 'xf:layout', this.recalcAndUpdate.bind(this))

			setTimeout(this.recalcAndUpdate.bind(this), 250)
		},

		recalc ()
		{
			const target = this.target

			this.winHeight = XF.windowHeight()
			this.elBottom = this.getTargetTop() + target.offsetHeight
			this.fixElHeight = this.fixEl.offsetHeight
			this.containerTop = XF.getFixedOffset(this.container).top
			this.containerBorderLeftWidth = parseInt(getComputedStyle(this.container).borderLeftWidth, 10)
		},

		recalcAndUpdate ()
		{
			this.state = 'normal' // need to force CSS updates
			this.resetTarget()
			this.recalc()
			this.update()
		},

		getTargetTop ()
		{
			const top = this.target.getBoundingClientRect().top

			if (this.fixedParent)
			{
				return top - this.fixedParent.getBoundingClientRect().top
			}
			else
			{
				return top + window.scrollY
			}
		},

		getScrollTop ()
		{
			if (this.fixedParent)
			{
				return this.fixedParent.scrollTop
			}
			else
			{
				return document.documentElement.scrollTop
			}
		},

		update ()
		{
			let winHeight = this.winHeight
			if (winHeight < this.options.minWindowHeight)
			{
				if (this.state != 'normal')
				{
					this.resetTarget()
					this.state = 'normal'
				}
				return
			}

			let containerOffset,
				bottomFixHeight = XF.NoticeWatcher.getBottomFixerNoticeHeight() || 0

			let isOverlay = this.container.closest('.overlay')
			if (isOverlay)
			{
				bottomFixHeight = 0
			}

			let screenBottom = this.getScrollTop() + winHeight - bottomFixHeight
			if (screenBottom >= this.elBottom)
			{
				// screen is past the end of the element, natural position
				if (this.state != 'normal')
				{
					this.resetTarget()
					this.state = 'normal'
				}
				return
			}

			let absoluteCutOff = this.containerTop + this.topOffset + this.fixElHeight

			if (screenBottom <= absoluteCutOff)
			{
				if (absoluteCutOff >= this.elBottom)
				{
					return
				}

				// screen is above container
				if (this.state != 'absolute')
				{
					containerOffset = this.container.getBoundingClientRect()

					let offsetParent
					if (this.state == 'stuck')
					{
						// when fixed, the offset parent is the HTML element
						offsetParent = this.fixEl.parentNode
						if (window.getComputedStyle(offsetParent).position == 'static')
						{
							offsetParent = offsetParent.offsetParent
						}
					}
					else
					{
						offsetParent = this.fixEl.offsetParent
					}
					let offsetParentOffset = offsetParent.getBoundingClientRect()

					this.fixEl.style.position = 'absolute'
					this.fixEl.style.top = `${ containerOffset.top - offsetParentOffset.top + this.topOffset }px`
					this.fixEl.style.right = 'auto'
					this.fixEl.style.bottom = 'auto'
					this.fixEl.style.left = `${ containerOffset.left - offsetParentOffset.left + this.containerBorderLeftWidth }px`
					this.fixEl.style.width = `${ this.container.clientWidth }px`

					this.setTargetSticky(true)
					this.state = 'absolute'
				}

				return
			}

			// screen ends within the container
			if (this.state != 'stuck')
			{
				containerOffset = this.container.getBoundingClientRect()

				this.fixEl.style.position = ''
				this.fixEl.style.top = ''
				this.fixEl.style.right = ''
				this.fixEl.style.bottom = `${ bottomFixHeight }px`
				this.fixEl.style.left = `${ containerOffset.left + this.containerBorderLeftWidth }px`
				this.fixEl.style.width = `${ this.container.clientWidth }px`

				this.setTargetSticky(true)
				this.state = 'stuck'
			}
		},

		resetTarget ()
		{
			this.fixEl.style.position = ''
			this.fixEl.style.top = ''
			this.fixEl.style.right = ''
			this.fixEl.style.bottom = ''
			this.fixEl.style.left = ''
			this.fixEl.style.width = ''
			this.setTargetSticky(false)
		},

		setTargetSticky (sticky)
		{
			this.target.classList.toggle(this.options.stickyClass, sticky)
			this.target.style.height = `${ this.fixEl.offsetHeight }px`
		},

		onScroll ()
		{
			this.update()
		},
	})

	// ################################## GUEST USERNAME HANDLER ###########################################

	XF.GuestUsername = XF.Element.newHandler({

		init ()
		{
			const input = this.target
			input.value = XF.LocalStorage.get('guestUsername')
			XF.on(input, 'keyup', this.change.bind(this))
		},

		change ()
		{
			const input = this.target
			if (input.value.length)
			{
				XF.LocalStorage.set('guestUsername', input.value, true)
			}
			else
			{
				XF.LocalStorage.remove('guestUsername')
			}
		},
	})

	// ################################## MIN LENGTH ###########################################

	XF.MinLength = XF.Element.newHandler({
		options: {
			minLength: 0,
			allowEmpty: false,
			disableSubmit: true,
			toggleTarget: null,
		},

		met: null,
		form: null,
		toggleTarget: null,

		init ()
		{
			this.form = this.target.closest('form')

			if (this.options.toggleTarget)
			{
				this.toggleTarget = XF.findRelativeIf(this.options.toggleTarget, this.target)
			}

			const f = () =>
			{
				setTimeout(this.checkLimits.bind(this), 0)
			}
			['change', 'keypress', 'keydown', 'paste'].forEach(event =>
			{
				XF.on(this.target, event, f)
			})

			if (!this.options.allowEmpty && this.options.minLength == 0)
			{
				this.options.minLength = 1
			}

			this.checkLimits()
		},

		checkLimits ()
		{
			const length = this.target.value.trim().length
			const options = this.options
			const met = (length >= options.minLength || (length == 0 && options.allowEmpty))

			if (met === this.met)
			{
				return
			}
			this.met = met

			if (met)
			{
				if (options.disableSubmit)
				{
					const submitButtons = this.form.querySelectorAll('[type="submit"]')
					submitButtons.forEach(button =>
					{
						button.disabled = false
						button.classList.remove('is-disabled')
					})
				}
				if (this.toggleTarget)
				{
					XF.display(this.toggleTarget, 'none')
				}
			}
			else
			{
				if (options.disableSubmit)
				{
					const submitButtons = this.form.querySelectorAll('[type="submit"]')
					submitButtons.forEach(button =>
					{
						button.disabled = true
						button.classList.add('is-disabled')
					})
				}
				if (this.toggleTarget)
				{
					XF.display(this.toggleTarget)
				}
			}
		},
	})

	// ################################## TEXTAREA HANDLER ###########################################

	XF.TextAreaHandler = XF.Element.newHandler({
		options: {
			autoSize: true,
			keySubmit: true,
			singleLine: null, // if 'next', focus next element on enter, otherwise submit on enter
		},

		initialized: false,

		init ()
		{
			if (this.options.autoSize)
			{
				if (this.target.scrollHeight)
				{
					this.setupAutoSize()
				}
				else
				{
					XF.on(this.target, 'focus', this.setupDelayed.bind(this), { once: true })
					XF.on(this.target, 'control:enabled', this.setupDelayed.bind(this), { once: true })
					XF.on(this.target, 'control:disabled', this.setupDelayed.bind(this), { once: true })

					XF.onWithin(this.target, 'toggle:shown overlay:shown tab:shown quick-edit:shown', this.setupDelayed.bind(this))
				}

				XF.on(this.target, 'autosize', this.update.bind(this))
			}

			if (this.options.keySubmit || this.options.singleLine)
			{
				XF.on(this.target, 'keydown', this.keySubmit.bind(this))
			}
		},

		setupAutoSize ()
		{
			if (this.initialized)
			{
				return
			}
			this.initialized = true

			autosize(this.target)

			XF.on(this.target, 'autosize:resized', () => XF.layoutChange())
		},

		setupDelayed ()
		{
			if (this.initialized)
			{
				this.update()
			}
			else
			{
				const init = () =>
				{
					this.setupAutoSize()
					XF.layoutChange()
				}

				if (this.target.scrollHeight)
				{
					init()
				}
				else
				{
					setTimeout(init, 100)
				}
			}
		},

		update ()
		{
			if (this.initialized)
			{
				autosize.update(this.target)
			}
			else
			{
				this.setupDelayed()
			}
		},

		keySubmit (e)
		{
			if (e.key == 'Enter')
			{
				if (this.options.singleLine || (this.options.keySubmit && (XF.isMac() ? e.metaKey : e.ctrlKey)))
				{
					const form = this.target.closest('form')

					switch (String(this.options.singleLine).toLowerCase())
					{
						case 'next':
							this.target.nextElementSibling.focus()
							break

						case 'blur':
							this.target.blur()
							break

						default:
							if (form && XF.trigger(form, 'submit'))
							{
								form.submit()
							}
					}

					e.preventDefault()
					e.stopPropagation()
				}
			}
		},
	})

	// ################################## PASSWORD HIDE/SHOW HANDLER ###########################################

	XF.PasswordHideShow = XF.Element.newHandler({
		options: {
			showText: null,
			hideText: null,
		},

		password: null,
		checkbox: null,
		label: null,

		init ()
		{
			this.password = this.target.querySelector('.js-password')

			const container = this.target.querySelector('.js-hideShowContainer')
			this.checkbox = container.querySelector('input[type="checkbox"]')
			this.label = container.querySelector('.iconic-label')

			XF.on(this.checkbox, 'change', this.toggle.bind(this))
		},

		toggle (e)
		{
			const checkbox = this.checkbox
			const password = this.password
			const label = this.label

			if (checkbox.checked)
			{
				password.setAttribute('type', 'text')
				label.textContent = this.options.hideText
			}
			else
			{
				password.setAttribute('type', 'password')
				label.textContent = this.options.showText
			}
		},
	})

	// ################################## FORM INPUT VALIDATION HANDLER ###############################################

	XF.InputValidator = XF.Element.newHandler({
		options: {
			delay: 500,
			onBlur: true,
			trim: true,
			validateEmpty: false,
			validationUrl: null,
			errorTarget: null,
		},

		timeout: null,
		errorElement: null,
		validatedValue: null,

		init ()
		{
			if (!this.options.validationUrl)
			{
				console.error('Element must have a data-validation-url value')
				return
			}

			let errorElement

			if (this.options.errorTarget)
			{
				errorElement = XF.findRelativeIf(this.options.errorTarget, this.target)
			}
			else
			{
				errorElement = this.target.parentNode.querySelector('.js-validationError')
			}

			if (!errorElement)
			{
				console.error('Unable to locate error element.')
				return
			}

			this.errorElement = errorElement

			if (this.options.delay)
			{
				XF.on(this.target, 'input', this.onInput.bind(this))
			}

			if (this.options.onBlur)
			{
				XF.on(this.target, 'blur', this.performValidation.bind(this))
			}
		},

		onInput (e)
		{
			if (this.timeout)
			{
				clearTimeout(this.timeout)
			}

			this.timeout = setTimeout(this.performValidation.bind(this), this.options.delay)
		},

		performValidation (e)
		{
			if (this.timeout)
			{
				clearTimeout(this.timeout)
				this.timeout = null
			}

			const value = this.getEffectiveInputValue()

			if (value === this.validatedValue)
			{
				// nothing has changed since the last check, so don't repeat
				return
			}

			if (value === '' && !this.options.validateEmpty)
			{
				const errorEl = this.errorElement
				XF.Transition.removeClassTransitioned(errorEl, 'is-active', () =>
				{
					errorEl.innerHTML = ''
				})
				return
			}

			this.validatedValue = value

			XF.ajax(
				'POST',
				XF.canonicalizeUrl(this.options.validationUrl),
				{
					field: this.target.getAttribute('name'),
					content: value,
				},
				this.handleResponse.bind(this),
			)
		},

		getEffectiveInputValue ()
		{
			let value = this.target.value
			if (this.options.trim)
			{
				value = value.trim()
			}

			return value
		},

		handleResponse (data)
		{
			if (data.validatedValue && data.validatedValue !== this.getEffectiveInputValue())
			{
				// the value we've checked on the server doesn't match the current value, so just disregard
				return
			}

			const inputErrors = data.inputErrors

			if (!data.inputValid && inputErrors)
			{
				let html
				if (typeof inputErrors == 'object')
				{
					if (inputErrors.length === 1)
					{
						html = inputErrors[0]
					}
					else
					{
						html = '<ul>'
						inputErrors.forEach(error =>
						{
							html += `<li>${ error }</li>`
						})
						html += '</ul>'
					}
				}
				else
				{
					html = inputErrors
				}

				this.errorElement.innerHTML = html
				XF.Transition.addClassTransitioned(this.errorElement, 'is-active')
			}
			else
			{
				if (!data.inputValid)
				{
					console.error('Data is not valid, but no errors')
				}

				const errorEl = this.errorElement
				XF.Transition.removeClassTransitioned(errorEl, 'is-active', () =>
				{
					errorEl.innerHTML = ''
				})
			}
		},
	})

	// ################################## CHECKBOXES DISABLE SELECT OPTIONS ###########################################
	// Using this, checkbox values correspond to <option> values in the <select> selected by this.options.select,
	// and if the checkbox is not checked, the corresponding <option> will be disabled

	XF.CheckboxSelectDisabler = XF.Element.newHandler({
		options: {
			select: null,
		},

		selects: null,
		checkboxes: null,

		init ()
		{
			this.selects = document.querySelectorAll(this.options.select)
			if (!this.selects || !this.selects.length)
			{
				console.warn('No select element(s) found using %s', this.options.select)
				return
			}

			this.checkboxes = this.target.querySelectorAll('input[type="checkbox"]')
			Array.from(this.checkboxes).forEach(checkbox =>
			{
				XF.on(checkbox, 'click', this.update.bind(this))
			})

			this.update()
		},

		update ()
		{
			const selects = this.selects
			const selectsChanged = []

			Array.from(this.checkboxes).forEach(checkbox =>
			{
				const cbChecked = checkbox.checked

				Array.from(selects).forEach(select =>
				{
					select.querySelectorAll(`option[value="${ checkbox.value }"]`).forEach(option =>
					{
						const optionSelected = option.selected
						const optionDisabled = option.disabled
						const select = option.closest('select')
						let changed = false

						if (optionDisabled === cbChecked)
						{
							option.disabled = !cbChecked
							changed = true
						}

						if (!cbChecked && optionSelected)
						{
							option.selected = false

							if (!select.getAttribute('multiple'))
							{
								select.querySelectorAll('option:enabled').selected = true
							}

							changed = true
						}

						if (changed)
						{
							selectsChanged.push(select)
						}
					})
				})
			})

			if (selectsChanged.length)
			{
				const uniqueSelects = [...new Set(selectsChanged)]
				uniqueSelects.forEach(select =>
				{
					XF.trigger(select, 'select:refresh')
				})
			}
		},
	})

	// ################################## ASSET UPLOAD HANDLER ###########################################

	XF.AssetUpload = XF.Element.newHandler({

		options: {
			asset: '',
			name: '',
			entity: '',
		},

		path: null,
		upload: null,

		init ()
		{
			this.path = this.target.querySelector('.js-assetPath')

			this.upload = this.target.querySelector('.js-uploadAsset')
			XF.on(this.upload, 'change', this.changeFile.bind(this))
		},

		changeFile (e)
		{
			if (e.target.value != '')
			{
				const formData = new FormData()
				formData.append('upload', e.target.files[0])
				formData.append('type', this.options.asset)
				formData.append('entity', this.options.entity)
				formData.append('name', this.options.name)

				XF.ajax(
					'post',
					XF.canonicalizeUrl('admin.php?assets/upload'),
					formData,
					this.ajaxResponse.bind(this),
				)
			}
		},

		ajaxResponse (data)
		{
			if (data.errors || data.exception)
			{
				return
			}

			if (data.path)
			{
				this.path.value = data.path
			}

			this.upload.value = ''
		},
	})

	// ################################## --- ###########################################

	XF.Event.register('click', 'submit', 'XF.SubmitClick')

	XF.Element.register('ajax-submit', 'XF.AjaxSubmit')
	XF.Element.register('user-mentioner', 'XF.UserMentioner')
	XF.Element.register('emoji-completer', 'XF.EmojiCompleter')
	XF.Element.register('auto-submit', 'XF.AutoSubmit')
	XF.Element.register('check-all', 'XF.CheckAll')
	XF.Element.register('select-plus', 'XF.SelectPlus')
	XF.Element.register('desc-loader', 'XF.DescLoader')
	XF.Element.register('disabler', 'XF.Disabler')
	XF.Element.register('field-adder', 'XF.FieldAdder')
	XF.Element.register('form-submit-row', 'XF.FormSubmitRow')
	XF.Element.register('guest-username', 'XF.GuestUsername')
	XF.Element.register('min-length', 'XF.MinLength')
	XF.Element.register('textarea-handler', 'XF.TextAreaHandler')
	XF.Element.register('checkbox-select-disabler', 'XF.CheckboxSelectDisabler')
	XF.Element.register('password-hide-show', 'XF.PasswordHideShow')
	XF.Element.register('change-submit', 'XF.ChangeSubmit')
	XF.Element.register('input-validator', 'XF.InputValidator')
	XF.Element.register('asset-upload', 'XF.AssetUpload')
})(window, document)
