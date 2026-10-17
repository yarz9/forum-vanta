((window, document) =>
{
	'use strict'

	XF.Message = XF.Message || {}

	XF.Message.insertMessages = (dataHtml, messageContainer, ascending, onInsert) =>
	{
		XF.setupHtmlInsert(dataHtml, (html, container, onComplete) =>
		{
			const noMessages = messageContainer.querySelector('.js-replyNoMessages')

			if (noMessages)
			{
				XF.Animate.fadeUp(noMessages)
			}

			const messages = XF.isCreatedContainer(html)
				? Array.from(html.childNodes)
				: [html]

			messages.forEach(message =>
			{
				if (!message.tagName)
				{
					return
				}

				XF.Message.insertMessage(message, messageContainer, ascending)
			})

			if (onInsert)
			{
				onInsert(html)
			}
		})
	}

	XF.Message.insertMessage = (message, messageContainer, ascending) =>
	{
		const firstChild = messageContainer.firstElementChild

		XF.display(message, 'none')

		if (firstChild && firstChild.matches('form') && !ascending)
		{
			firstChild.after(message)
		}
		else if (!ascending)
		{
			messageContainer.prepend(message)
		}
		else
		{
			messageContainer.append(message)
		}
		XF.Animate.fadeDown(message)

		XF.activate(message)
	}

	// ################################## MESSAGE LOADER HANDLER ###########################################

	XF.MessageLoaderClick = XF.Event.newHandler({
		eventNameSpace: 'XFMessageLoaderClick',
		options: {
			href: null,
			messagesContainer: '< .js-replyNewMessageContainer',
			selfContainer: '.message',
			ascending: true,
		},

		loading: false,

		init ()
		{
			if (!this.options.href)
			{
				this.options.href = this.target.getAttribute('href')
				if (!this.options.href)
				{
					console.error('Must be initialized with a data-href or href attribute.')
				}
			}
		},

		click (e)
		{
			e.preventDefault()

			if (this.loading)
			{
				return
			}

			XF.ajax('GET', this.options.href, {}, this.loaded.bind(this))
				.finally(() =>
				{
					this.loading = false
				})
		},

		loaded (data)
		{
			if (!data.html)
			{
				return
			}

			const container = XF.findRelativeIf(this.options.messagesContainer, this.target)
			XF.Message.insertMessages(data.html, container, this.options.ascending)

			const selfMessage = this.target.closest(this.options.selfContainer)
			XF.Animate.fadeUp(selfMessage, {
				complete: () => selfMessage.remove(),
			})

			if (data.lastDate)
			{
				const lastDate = document.querySelector('.js-quickReply input[name="last_date"]')
				if (lastDate)
				{
					lastDate.value = data.lastDate
				}
			}
		},
	})

	// ################################## QUICK EDIT HANDLER ###########################################

	XF.QuickEditClick = XF.Event.newHandler({
		eventNameSpace: 'XFQuickEdit',

		options: {
			editorTarget: null,
			editContainer: '.js-editContainer',
			href: null,
			noInlineMod: 0,
		},

		editorTarget: null,
		editForm: null,

		href: null,
		loading: false,

		init ()
		{
			const edTarget = this.options.editorTarget

			if (!edTarget)
			{
				console.error('No quick edit editorTarget specified')
				return
			}

			this.editorTarget = XF.findRelativeIf(edTarget, this.target)
			if (!this.editorTarget)
			{
				console.error('No quick edit target found')
				return
			}

			this.href = this.options.href || this.target.getAttribute('href')
			if (!this.href)
			{
				console.error('No edit URL specified.')
			}
		},

		click (e)
		{
			if (!this.editorTarget || !this.href)
			{
				return
			}

			e.preventDefault()

			if (this.loading)
			{
				return
			}

			this.loading = true

			const data = {}
			if (this.options.noInlineMod)
			{
				data['_xfNoInlineMod'] = true
			}

			XF.ajax('GET', this.href, data, this.handleAjax.bind(this), { skipDefaultSuccessError: true })
		},

		handleAjax (data)
		{
			const editorTarget = this.editorTarget

			if (data.errors)
			{
				this.loading = false
				XF.alert(data.errors)
				return
			}

			XF.setupHtmlInsert(data.html, (html, container) =>
			{
				XF.display(html, 'none')
				editorTarget.after(html)
				XF.activate(html)
				this.editForm = html

				XF.on(html, 'ajax-submit:response', this.editSubmit.bind(this))
				const cancel = html.querySelector('.js-cancelButton')
				XF.on(cancel, 'click', this.cancelClick.bind(this))

				const hidden = html.querySelector('input[type=hidden]')
				const inlineEdit = XF.createElementFromString('<input type="hidden" name="_xfInlineEdit" value="1" />')
				hidden.after(inlineEdit)

				XF.Animate.fadeUp(editorTarget, {
					complete: () =>
					{
						editorTarget.parentNode.classList.add('is-editing')

						XF.Animate.fadeDown(html, {
							complete: () =>
							{
								XF.trigger(html, 'quick-edit:shown')

								const editContainer = html.querySelector(this.options.editContainer)
								if (editContainer && !XF.isElementVisible(editContainer))
								{
									editContainer.scrollIntoView(true)
								}

								this.loading = false
							},
						})
					},
				})

				XF.trigger(html, 'quick-edit:show')
			})
		},

		editSubmit (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			if (data.message)
			{
				XF.flashMessage(data.message, 3000)
			}

			const editorTarget = this.editorTarget

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				let target = this.options.editorTarget
				target = target.replace(/<|\|/g, '').replace(/#[a-zA-Z0-9_-]+\s*/, '')

				const message = html.querySelector(target)

				XF.display(message, 'none')

				const parent = editorTarget.parentNode
				parent.replaceChild(message, editorTarget)

				this.editorTarget = message
				XF.activate(message)

				this.stopEditing(false, () =>
				{
					XF.Animate.fadeDown(message)

					XF.trigger(this.editForm, XF.customEvent('quickedit:editcomplete', data))
				})
			})
		},

		cancelClick (e)
		{
			this.stopEditing(true)
		},

		stopEditing (showMessage, onComplete)
		{
			const editorTarget = this.editorTarget
			const editForm = this.editForm

			const finish = () =>
			{
				editorTarget.parentNode.classList.remove('is-editing')

				if (showMessage)
				{
					XF.Animate.fadeDown(editorTarget)
				}

				if (onComplete)
				{
					onComplete()
				}

				editForm.remove()
				this.editForm = null
			}

			if (editForm)
			{
				XF.Animate.fadeUp(editForm, {
					complete: finish,
				})
			}
			else
			{
				finish()
			}
		},
	})

	// ################################## QUOTE HANDLER ###########################################

	XF.QuoteClick = XF.Event.newHandler({
		eventNameSpace: 'XFQuoteClick',
		options: {
			quoteHref: null,
			editor: '.js-quickReply .js-editor',
		},

		init ()
		{
			if (!this.options.quoteHref)
			{
				console.error('Must be initialized with a data-quote-href attribute.')
			}
		},

		click (e)
		{
			const editor = XF.findRelativeIf(this.options.editor, this.target)

			XF.trigger(editor.closest('form'), XF.customEvent('preview:hide', {
				quoteClick: this,
			}))

			const qr = editor.closest('.js-quickReply')

			if (!editor || !qr)
			{
				return
			}

			e.preventDefault()

			const href = this.options.quoteHref
			const selectToQuote = e.target.closest('.tooltip--selectToQuote')
			const quoteHtml = XF.unparseBbCode(XF.DataStore.get(selectToQuote, 'quote-html'))

			XF.ajax('POST', href, { quoteHtml }, this.handleAjax.bind(this), { skipDefaultSuccess: true })

			XF.trigger(e.target, 's2q:click')

			window.scrollTo({ top: qr.getBoundingClientRect().top - XF.getStickyHeaderOffset() })

			XF.focusEditor(editor)
		},

		handleAjax (data)
		{
			const editor = XF.findRelativeIf(this.options.editor, this.target)
			XF.insertIntoEditor(editor, data.quoteHtml, data.quote)
		},
	})

	// ################################## SOLUTION EDIT HANDLER ###########################################

	XF.SolutionEditClick = XF.extend(XF.SwitchClick, {
		applyResponseActions (data)
		{
			this.applyActionsTo(this.target, data)
		},

		applyActionsTo (target, data)
		{
			let match, replaceId

			if (data.switchKey)
			{
				match = data.switchKey.match(/^replaced:(\d+)$/)
				if (match)
				{
					replaceId = parseInt(match[1], 10)
					data.switchKey = 'marked' // mark this post as the solution
				}
			}

			XF.handleSwitchResponse(target, data, this.options.redirect)

			// TODO: the selectors below this could do with being more flexible

			const message = target.closest('.message')

			if (data.switchKey == 'marked')
			{
				message.classList.add('message--solution')
			}
			else if (data.switchKey == 'removed')
			{
				message.classList.remove('message--solution')

				const rect = message.getBoundingClientRect()
				const originalTopPos = rect.top
				const originalScrollTop = document.documentElement.scrollTop

				document.querySelector('#js-solutionHighlightBlock')?.remove()

				const diff = rect.top - originalTopPos
				if (diff)
				{
					document.documentElement.scrollTop = originalScrollTop + diff
				}
			}

			if (replaceId)
			{
				const replacedControl = document.querySelector(`#js-post-${ replaceId } .js-solutionControl`)
				if (replacedControl)
				{
					this.applyActionsTo(replacedControl, { switchKey: 'removed' })
				}
			}
		},
	})

	// ################################## MULTI QUOTE HANDLER ###########################################

	XF.MultiQuote = XF.Element.newHandler({
		options: {
			href: '',
			messageSelector: '',
			addMessage: '',
			removeMessage: '',
			storageKey: '',
		},

		mqStorage: null,
		mqOverlay: null,

		removing: false,
		quoting: false,

		init ()
		{
			this.initButton()
			this.initControls()

			XF.CrossTab.on('mqChange', data =>
			{
				if (data.storageKey !== this.options.storageKey)
				{
					return
				}

				const messageId = data.messageId

				switch (data.action)
				{
					case 'added':
						this.selectMqControl(messageId)
						break

					case 'removed':
						this.deselectMqControl(messageId)
						break

					case 'refresh':
						// the code below will handle this
						break
				}

				this.refreshMqData()
				this.updateButtonState()
			})
		},

		initButton ()
		{
			this.mqStorage = XF.LocalStorage.getJson(this.options.storageKey)
			if (this.hasQuotesStored())
			{
				this.target.classList.remove('is-hidden')
			}

			XF.on(this.target, 'click', this.buttonClick.bind(this))
		},

		buttonClick (e)
		{
			e.preventDefault()
			if (!this.options.href)
			{
				console.error('Multi-quote button must have a data-href attribute set to display selected quotes')
				return false
			}

			XF.ajax('post', this.options.href, {
				quotes: XF.LocalStorage.get(this.options.storageKey),
			}, this.loadOverlay.bind(this))
		},

		loadOverlay (data)
		{
			if (data.html)
			{
				XF.setupHtmlInsert(data.html, (html, container) =>
				{
					const overlayEl = XF.getOverlayHtml({
						html,
						title: container.h1 || container.title,
					})
					XF.onDelegated(overlayEl, 'click', '.js-removeMessage', this.removeMessage.bind(this))
					XF.onDelegated(overlayEl, 'click', '.js-quoteMessages', this.quoteMessages.bind(this))
					this.mqOverlay = XF.showOverlay(overlayEl)
				})
			}
		},

		removeMessage (e)
		{
			e.preventDefault()

			if (this.removing)
			{
				return
			}

			this.removing = true

			const item = e.target.closest('.nestable-item')
			const messageId = item.dataset.id
			const overlay = this.mqOverlay

			this.removeFromMultiQuote(messageId)

			XF.Animate.fadeUp(item, {
				speed: XF.config.speed.fast,
				complete ()
				{
					item.remove()
				},
			})

			if (!this.hasQuotesStored())
			{
				overlay.hide()
			}

			this.removing = false
		},

		quoteMessages (e)
		{
			e.preventDefault()

			if (this.quoting)
			{
				return
			}

			this.quoting = true

			const overlay = this.mqOverlay
			const overlayEl = overlay.getOverlay()
			const toInsert = JSON.parse(overlayEl.querySelector('input[name="message_ids"]')?.value || [])
			const multiQuotes = this.mqStorage

			for (const i of Object.keys(toInsert))
			{
				if (!XF.hasOwn(toInsert[i], 'id'))
				{
					continue
				}

				const id = toInsert[i]['id']
				const parts = id.split('-')
				const messageId = parts[0]
				const key = parts[1]

				if (!this.isValidQuote(multiQuotes[messageId], key))
				{
					continue
				}

				let value = multiQuotes[messageId][key]
				if (value !== true)
				{
					value = XF.unparseBbCode(value)
				}
				toInsert[i]['value'] = value
			}

			overlay.hide()

			XF.ajax('post', this.options.href, {
				insert: toInsert,
				quotes: XF.LocalStorage.get(this.options.storageKey),
			}, this.insertMessages.bind(this)).finally(() =>
			{
				this.quoting = false
			})
		},

		isValidQuote (quote, key)
		{
			if (quote != undefined)
			{
				if (XF.hasOwn(quote, key))
				{
					if (quote[key] === true || typeof quote[key] == 'string')
					{
						return true
					}
				}
			}

			return false
		},

		insertMessages (data)
		{
			let editor = XF.findRelativeIf('< form | .js-editor', this.target)
			if (!editor)
			{
				editor = document.querySelector('.js-editor').parentNode
			}

			Object.entries(data).forEach(([key, quoteObj]) =>
			{
				if (!XF.isNumeric(key))
				{
					return
				}

				if (!XF.hasOwn(quoteObj, 'quote') || !XF.hasOwn(quoteObj, 'quoteHtml'))
				{
					return true
				}

				if (key > 0)
				{
					// we want an extra line break between inserted quotes
					quoteObj.quoteHtml = '<p></p>' + quoteObj.quoteHtml
					quoteObj.quote = '\n' + quoteObj.quote
				}

				XF.insertIntoEditor(editor, quoteObj.quoteHtml, quoteObj.quote)
			})

			for (const messageId in this.mqStorage)
			{
				this.removeFromMultiQuote(messageId)
			}
		},

		initControls ()
		{
			const messagesSel = '.tooltip--selectToQuote, ' + this.options.messageSelector
			const messages = document.querySelectorAll(messagesSel)
			const controls = []

			messages.forEach(message =>
			{
				const multiQuoteElements = message.querySelectorAll('.js-multiQuote')
				controls.push(...multiQuoteElements)
			})

			XF.onDelegated(document, 'click', messagesSel, this.controlClick.bind(this))

			controls.forEach(control =>
			{
				const messageId = control.dataset.messageId

				if (XF.hasOwn(this.mqStorage, messageId))
				{
					control.classList.add('is-selected')
					control.dataset.mqAction = 'remove'
				}
			})
		},

		controlClick (e)
		{
			if (!e.target.matches('.js-multiQuote'))
			{
				return
			}

			e.preventDefault()

			const target = e.target
			const action = target.dataset.mqAction
			const messageId = target.dataset.messageId

			switch (action)
			{
				case 'add':
					this.addToMultiQuote(messageId)
					XF.flashMessage(this.options.addMessage, 3000)
					break

				case 'remove':
					this.removeFromMultiQuote(messageId)
					XF.flashMessage(this.options.removeMessage, 3000)
					break
			}

			XF.trigger(target, 's2q:click')
		},

		addToMultiQuote (messageId)
		{
			const mqControl = document.querySelector(`.js-multiQuote[data-message-id="${ messageId }"]`)
			const selectToQuote = document.querySelector('.tooltip--selectToQuote')
			const quoteHtml = XF.unparseBbCode(XF.DataStore.get(selectToQuote, 'quote-html'))

			this.refreshMqData()

			if (!this.hasQuotesStored())
			{
				this.mqStorage = {}
				this.mqStorage[messageId] = []
			}
			else
			{
				if (!this.mqStorage[messageId])
				{
					this.mqStorage[messageId] = []
				}
			}

			if (selectToQuote)
			{
				this.mqStorage[messageId].push(quoteHtml)
			}
			else
			{
				this.mqStorage[messageId].push(true) // true == quoting the full message
			}
			this.updateMultiQuote()

			this.selectMqControl(messageId)
			this.triggerCrossTabEvent('added', messageId)
		},

		removeFromMultiQuote (messageId)
		{
			const quoteInfo = String(messageId).match(/^(\d+)-(\d+)$/)

			this.refreshMqData()

			if (quoteInfo)
			{
				messageId = quoteInfo[1]

				delete this.mqStorage[messageId][quoteInfo[2]]

				if (!this.getQuoteStoreCount(this.mqStorage[messageId]))
				{
					delete this.mqStorage[messageId]
				}
			}
			else
			{
				delete this.mqStorage[messageId]
			}

			this.updateMultiQuote()

			if (!this.mqStorage[messageId])
			{
				this.deselectMqControl(messageId)
				this.triggerCrossTabEvent('removed', messageId)
			}
		},

		selectMqControl (messageId)
		{
			const mqControl = document.querySelector('.js-multiQuote[data-message-id="' + messageId + '"]')

			if (mqControl)
			{
				mqControl.classList.add('is-selected')
				mqControl.dataset.mqAction = 'remove'
			}
		},

		deselectMqControl (messageId)
		{
			const mqControl = document.querySelector('.js-multiQuote[data-message-id="' + messageId + '"]')

			if (mqControl)
			{
				mqControl.classList.remove('is-selected')
				mqControl.dataset.mqAction = 'add'
			}
		},

		getQuoteStoreCount (quoteStore)
		{
			let length = 0

			for (const i of Object.keys(quoteStore))
			{
				if (quoteStore[i] == true || typeof quoteStore[i] == 'string')
				{
					length++
				}
			}

			return length
		},

		updateMultiQuote ()
		{
			XF.LocalStorage.setJson(this.options.storageKey, this.mqStorage, true)
			this.updateButtonState()
		},

		updateButtonState ()
		{
			if (!this.hasQuotesStored())
			{
				this.target.classList.add('is-hidden')
			}
			else
			{
				this.target.classList.remove('is-hidden')
			}
		},

		refreshMqData ()
		{
			this.mqStorage = XF.LocalStorage.getJson(this.options.storageKey)
		},

		hasQuotesStored ()
		{
			return this.mqStorage && !XF.isEmptyObject(this.mqStorage)
		},

		triggerCrossTabEvent (action, messageId, data)
		{
			data = data || {}
			data.storageKey = this.options.storageKey
			data.action = action
			data.messageId = messageId

			XF.CrossTab.trigger('mqChange', data)
		},
	})

	// ################################## SELECT TO QUOTE HANDLER ###########################################

	XF.SelectToQuote = XF.Element.newHandler({
		options: {
			messageSelector: '',
		},

		quickReply: null,

		timeout: null,
		processing: false,
		triggerEvent: null,
		isMouseDown: false,
		tooltip: null,
		tooltipId: null,

		init ()
		{
			if (!window.getSelection)
			{
				return
			}

			if (!this.options.messageSelector)
			{
				console.error('No messageSelector')
				return
			}

			this.quickReply = document.querySelector('.js-quickReply .js-editor')?.parentNode
			if (!this.quickReply)
			{
				return
			}

			XF.on(this.target, 'mousedown', this.mouseDown.bind(this))
			XF.on(this.target, 'pointerdown', this.mouseDown.bind(this), { passive: true })
			XF.on(this.target, 'mouseup', this.mouseUp.bind(this))
			XF.on(this.target, 'pointerup', this.mouseUp.bind(this))

			XF.on(document, 'selectionchange', this.selectionChange.bind(this))
		},

		mouseDown (e)
		{
			// store event so we can detect later if it originates from touch
			this.triggerEvent = e

			if (e.type == 'mousedown')
			{
				this.isMouseDown = true
			}
		},

		mouseUp ()
		{
			this.isMouseDown = false
			this.trigger()
		},

		selectionChange ()
		{
			if (!this.isMouseDown)
			{
				this.trigger()
			}
		},

		trigger ()
		{
			if (!this.timeout && !this.processing)
			{
				this.timeout = setTimeout(this.handleSelection.bind(this), 100)
			}
		},

		handleSelection ()
		{
			this.processing = true
			this.timeout = null

			const selection = window.getSelection()
			const selectionContainer = this.getValidSelectionContainer(selection)

			if (selectionContainer)
			{
				this.showQuoteButton(selectionContainer, selection)
			}
			else
			{
				this.hideQuoteButton()
			}

			setTimeout(() =>
			{
				this.processing = false
			}, 0)
		},

		getValidSelectionContainer (selection)
		{
			if (selection.isCollapsed || !selection.rangeCount)
			{
				return null
			}

			const range = selection.getRangeAt(0)
			this.adjustRange(range)

			if (!range.toString().trim().length)
			{
				if (!range.cloneContents().querySelectorAll('img').length)
				{
					return null
				}
			}

			const commonAncestor = range.commonAncestorContainer instanceof Element
				? range.commonAncestorContainer
				: range.commonAncestorContainer.parentElement
			const container = commonAncestor.closest('.js-selectToQuote')
			if (!container)
			{
				return null
			}

			if (!this.target.contains(container))
			{
				return null
			}

			const message = container.closest(this.options.messageSelector)
			if (!message.querySelector('.actionBar-action[data-xf-click="quote"]'))
			{
				return null
			}

			const startContainer = range.startContainer.parentElement
			const endContainer = range.endContainer.parentElement
			if (
				startContainer.closest('.bbCodeBlock--quote, .js-noSelectToQuote') ||
				endContainer.closest('.bbCodeBlock--quote, .js-noSelectToQuote')
			)
			{
				return null
			}

			return container
		},

		adjustRange (range)
		{
			let changed = false
			let isQuote = false
			let end = range.endContainer
			const start = range.startContainer
			const startEl = start.nodeType === Node.TEXT_NODE ? start.parentNode : start

			if (range.endOffset == 0)
			{
				if (end.nodeType == 3 && !end.previousSibling)
				{
					// text node with nothing before it, move up
					end = end.parentNode
				}
				isQuote = Boolean(end.closest('.bbCodeBlock--quote'))
			}

			if (isQuote)
			{
				const quote = end.closest('.bbCodeBlock--quote')
				if (quote)
				{
					range.setEndBefore(quote)
					changed = true
				}
			}

			if (startEl.closest('.embed'))
			{
				const embed = startEl.closest('.embed')
				if (embed)
				{
					range.setStart(embed, 0)
					range.setEndAfter(embed)
					changed = true
				}
			}

			if (changed)
			{
				const sel = window.getSelection()
				sel.removeAllRanges()
				sel.addRange(range)
			}
		},

		showQuoteButton (selectionContainer, selection)
		{
			const id = XF.uniqueId(selectionContainer)
			if (!this.tooltip || this.tooltipId !== id)
			{
				this.hideQuoteButton()
				this.createButton(selectionContainer, id)
			}

			const tooltip = this.tooltip.getTooltip()
			XF.DataStore.set(tooltip, 'quote-html', this.getSelectionHtml(selection))

			const offset = this.getButtonPositionMarker(selection)
			let touchTriggered = false

			if (this.triggerEvent)
			{
				touchTriggered = XF.isEventTouchTriggered(this.triggerEvent)
			}

			// if touch triggered then the browser may have large selection handles which might
			// obscure our tooltip - if we detect touch then try to offset that
			if (touchTriggered)
			{
				offset.top += 10
			}

			this.tooltip.setPositioner([offset.left, offset.top])

			if (this.tooltip.isShown())
			{
				this.tooltip.reposition()
			}
			else
			{
				this.tooltip.show()
			}

			tooltip.classList.add('tooltip--selectToQuote')
		},

		getButtonPositionMarker (selection)
		{
			// get absolute position of end of selection - or maybe focusNode
			// and position the quote button immediately next to the highlight
			let el
			let range
			let bounds

			el = XF.createElementFromString('<span></span>')
			el.textContent = '\u200B'

			range = selection.getRangeAt(0).cloneRange()
			bounds = range.getBoundingClientRect ? range.getBoundingClientRect() : null
			range.collapse(false)
			range.insertNode(el)

			let changed
			let moves = 0

			do
			{
				changed = false
				moves++

				if (el.parentNode && el.parentNode.className == 'js-selectToQuoteEnd')
				{
					// highlight after the marker to ensure that triple click works
					el.parentNode.before(el)

					changed = true
				}
				if (el.previousSibling && el.previousSibling.nodeType == 3 && el.previousSibling.textContent.trim().length == 0)
				{
					// highlight after an empty text block
					el.previousSibling.before(el)

					changed = true
				}
				if (el.parentNode && el.parentNode.tagName == 'LI' && !el.previousSibling)
				{
					// highlight at the beginning of a list item, move to previous item if possible
					const li = el.parentNode
					const prevLi = li.previousElementSibling

					if (prevLi !== null)
					{
						// move el to inside the last li
						prevLi.appendChild(el)

						changed = true
					}
					else if (li.parentNode)
					{
						// first list item, move before the list
						li.parentNode.before(el)

						changed = true
					}
				}
				if (el.parentNode && !el.previousSibling && ['DIV', 'BLOCKQUOTE', 'PRE'].includes(el.parentNode.tagName))
				{
					el.parentNode.before(el)

					changed = true
				}
				if (el.previousSibling && ['OL', 'UL'].includes(el.previousSibling.tagName))
				{
					// immediately after a list, position at end of last LI
					const previousSibling = el.previousSibling
					if (previousSibling && previousSibling.nodeType === Node.ELEMENT_NODE)
					{
						const liElements = previousSibling.querySelectorAll('li')
						const lastLiElement = liElements[liElements.length - 1]
						if (lastLiElement)
						{
							lastLiElement.appendChild(el)
						}
					}

					changed = true
				}
				if (el.previousSibling && ['DIV', 'BLOCKQUOTE', 'PRE'].includes(el.previousSibling.tagName))
				{
					// highlight immediately after a block causes weird positioning
					const previousSibling = el.previousElementSibling
					if (previousSibling)
					{
						previousSibling.appendChild(el)
					}

					changed = true
				}
				if (el.previousSibling && el.previousSibling.tagName == 'BR')
				{
					// highlight immediately after a line break causes weird positioning
					el.previousSibling.before(el)

					changed = true
				}
			}
			while (changed && moves < 5)

			const elRect = el.getBoundingClientRect()
			const elOffset = XF.dimensions(el)
			const height = elOffset.height

			let parent = el.parentElement
			const body = document.body

			// if we're in a scrollable element, find the right edge of that element and don't position beyond it
			while (parent !== body)
			{
				const parentStyles = window.getComputedStyle(parent)
				const overflowX = parentStyles.overflowX

				if (overflowX === 'hidden' || overflowX === 'scroll' || overflowX === 'auto')
				{
					const parentOffset = parent.getBoundingClientRect()
					const left = parentOffset.left
					const right = left + parent.offsetWidth

					if (elOffset.left < left)
					{
						elOffset.left = left
					}
					if (right < elOffset.left)
					{
						elOffset.left = right
					}
				}
				parent = parent.parentElement
			}

			el.remove()

			parent.normalize() // recombine text nodes for accurate text rendering

			if (bounds && !XF.isRtl())
			{
				if (elOffset.left - bounds.left > 32)
				{
					elOffset.left -= 16
				}
			}

			elOffset.top += height

			return elOffset
		},

		createButton (selectionContainer, id)
		{
			const message = selectionContainer.closest(this.options.messageSelector)
			const tooltip = XF.createElementFromString('<span></span>')

			const mqButton = message.querySelector('.actionBar-action.js-multiQuote')?.cloneNode(true)
			if (mqButton)
			{
				mqButton.setAttribute('title', '')
				mqButton.classList.remove('is-selected')
				mqButton.dataset.mqAction = 'add'

				mqButton.style.marginLeft = '0'
				mqButton.style.background = 'transparent'

				XF.on(mqButton, 's2q:click', this.buttonClicked.bind(this))

				tooltip.appendChild(mqButton)
				tooltip.appendChild(document.createTextNode(' | '))
			}

			const quoteButton = message.querySelector('.actionBar-action[data-xf-click="quote"]')
			quoteButton.setAttribute('title', '')

			const clonedQuoteButton = quoteButton.cloneNode(true)
			clonedQuoteButton.style.marginLeft = '0'

			XF.on(clonedQuoteButton, 's2q:click', this.buttonClicked.bind(this))

			tooltip.appendChild(clonedQuoteButton)

			this.tooltip = new XF.TooltipElement(tooltip, {
				html: true,
				placement: 'bottom',
			})
			this.tooltipId = id
		},

		buttonClicked ()
		{
			const s = window.getSelection()
			if (!s.isCollapsed)
			{
				s.collapse(s.getRangeAt(0).commonAncestorContainer, 0)
				this.hideQuoteButton()
			}
		},

		hideQuoteButton ()
		{
			const tooltip = this.tooltip

			if (tooltip)
			{
				tooltip.destroy()
				this.tooltip = null
			}
		},

		getSelectionHtml (selection)
		{
			const el = document.createElement('div')
			let len
			let contents

			for (let i = 0, len = selection.rangeCount; i < len; i++)
			{
				contents = selection.getRangeAt(i).cloneContents()

				this.groupIncompleteTableSegment(contents, 'td, th', 'tr', 'TR')
				this.groupIncompleteTableSegment(contents, 'tr', 'table, tbody, thead, tfoot', 'TABLE')
				this.groupIncompleteTableSegment(contents, 'tbody, thead, tfoot', 'table', 'TABLE')

				el.appendChild(contents)
			}

			return this.prepareSelectionHtml(el.innerHTML)
		},

		groupIncompleteTableSegment (fragment, segmentMatch, parentMatch, containerNodeName)
		{
			const matches = fragment.querySelectorAll(segmentMatch)
			let node
			let entries
			let container

			for (let node of matches)
			{
				if (!node.parentNode.matches(parentMatch))
				{
					entries = [node]
					while ((node = node.nextSibling))
					{
						if (node.matches(segmentMatch))
						{
							entries.push(node)
						}
						else
						{
							break
						}
					}

					container = document.createElement(containerNodeName)
					entries[0].parentNode.insertBefore(container, entries[0])

					for (const entry of entries)
					{
						container.appendChild(entry)
					}
				}
			}
		},

		prepareSelectionHtml (html)
		{
			return XF.adjustHtmlForRte(html)
		},
	})

	// ################################## QUICK REPLY HANDLER ###########################################

	XF.QuickReply = XF.Element.newHandler({

		options: {
			messageContainer: '',
			ascending: true,
			submitHide: null,
		},

		init ()
		{
			XF.on(this.target, 'ajax-submit:before', this.beforeSubmit.bind(this))
			XF.on(this.target, 'ajax-submit:response', this.afterSubmit.bind(this))
			XF.on(this.target, 'draft:complete', this.onDraft.bind(this))
		},

		beforeSubmit (config)
		{
			const button = config.submitButton

			if (button && button.getAttribute('name') == 'more_options')
			{
				config.preventDefault()
			}
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
				return
			}

			const lastDate = this.target.querySelector('input[name="last_date"]')
			if (lastDate)
			{
				lastDate.value = data.lastDate
			}

			this.getMessagesContainer().querySelector('.js-newMessagesIndicator')?.remove()

			this.insertMessages(data.html)

			XF.clearEditorContent(this.target)

			const editor = XF.getEditorInContainer(this.target)
			if (editor && editor.ed)
			{
				editor.blur()
			}

			const target = this.target
			const options = this.options

			XF.trigger(target, XF.customEvent('preview:hide', { quickReply: this }))
			XF.trigger(target, 'attachment-manager:reset')

			if (options.submitHide)
			{
				const submitHide = XF.findRelativeIf(options.submitHide, this.target)
				if (submitHide)
				{
					XF.display(submitHide, 'none')
				}
			}
		},

		insertMessages (dataHtml)
		{
			XF.Message.insertMessages(
				dataHtml,
				this.getMessagesContainer(),
				this.options.ascending,
				messages =>
				{
					const message = messages[0]
					if (message)
					{
						const dims = XF.dimensions(message)
						const docEl = document.documentElement
						const windowTop = docEl.scrollTop
						const windowBottom = windowTop + docEl.clientHeight

						if (dims.top < windowTop + 50 || dims.top > windowBottom)
						{
							XF.smoothScroll(Math.max(0, dims.top - 60), false, 200)
						}
					}
				},
			)
		},

		getMessagesContainer ()
		{
			const containerOption = this.options.messageContainer
			if (containerOption)
			{
				return XF.findRelativeIf(containerOption, this.target)
			}
			else
			{
				return document.querySelector('.js-replyNewMessageContainer')
			}
		},

		onDraft (e)
		{
			const { data } = e
			if (data.hasNew && data.html)
			{
				if (data.lastDate && data.lastDate > 0)
				{
					const lastDate = document.querySelector('.js-quickReply input[name="last_date"]')
					if (lastDate && parseInt(lastDate.value, 10) > data.lastDate)
					{
						return
					}
				}

				if (this.getMessagesContainer().querySelector('.js-newMessagesIndicator'))
				{
					return
				}

				// structured like a message
				this.insertMessages(data.html)
			}
		},
	})

	// ################################## POST EDIT HANDLER ######################

	XF.PostEdit = XF.Element.newHandler({

		init ()
		{
			XF.on(this.target, 'quickedit:editcomplete', this.editComplete.bind(this))
		},

		editComplete (data)
		{
			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const threadChanges = data.threadChanges || {}

				if (threadChanges.title)
				{
					document.querySelector('h1.p-title-value').innerHTML = container.h1
					document.querySelector('title').innerHTML = container.title

					// This effectively runs twice, but we do need the title to be correct if updating this way.
					if (XF.config.visitorCounts['title_count'] && data.visitor)
					{
						XF.pageTitleCache = container.title
						XF.pageTitleCounterUpdate(data.visitor.total_unread)
					}
				}

				if (threadChanges.customFields)
				{
					const newThreadStatusField = html.closest('.js-threadStatusField')
					const threadStatusField = XF.findRelativeIf('< .block--messages | .js-threadStatusField', this.target)

					if (newThreadStatusField && threadStatusField)
					{
						XF.Animate.fadeUp(threadStatusField, {
							speed: XF.config.speed.fast,
							complete ()
							{
								threadStatusField.parentNode.replaceChild(newThreadStatusField, threadStatusField)
								XF.Animate.fadeDown(threadStatusField, {
									speed: XF.config.speed.fast,
								})
							},
						})
					}
				}
				else
				{
					html.querySelector('.js-threadStatusField').remove()
				}
			})
		},
	})

	XF.Event.register('click', 'message-loader', 'XF.MessageLoaderClick')
	XF.Event.register('click', 'quick-edit', 'XF.QuickEditClick')
	XF.Event.register('click', 'quote', 'XF.QuoteClick')
	XF.Event.register('click', 'solution-edit', 'XF.SolutionEditClick')

	XF.Element.register('multi-quote', 'XF.MultiQuote')
	XF.Element.register('select-to-quote', 'XF.SelectToQuote')
	XF.Element.register('quick-reply', 'XF.QuickReply')
	XF.Element.register('post-edit', 'XF.PostEdit')
})(window, document)
