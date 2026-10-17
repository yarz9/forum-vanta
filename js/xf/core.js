((window, document) =>
{
	'use strict'

	// already loaded, don't load this twice
	if (XF.activate)
	{
		console.error('XF core has been double loaded')
		return
	}

	if (!XF.browser)
	{
		XF.browser = {
			browser: '',
			version: 0,
			os: '',
			osVersion: null,
		}
	}

	// ################################# BASE HELPERS ############################################
	XF.extendObject = (...args) =>
	{
		let deep = false
		let target = args[0] || {}
		let i = 1

		if (typeof target === 'boolean')
		{
			deep = target
			target = args[i] || {}
			i++
		}

		for (; i < args.length; i++)
		{
			if (!args[i])
			{
				continue
			}

			const source = args[i]

			Object.keys(source).forEach((key) =>
			{
				const srcValue = target[key]
				const copyValue = source[key]

				if (deep && copyValue && (typeof copyValue === 'object'))
				{
					if (Array.isArray(copyValue))
					{
						target[key] = srcValue && Array.isArray(srcValue) ? srcValue : []
					}
					else
					{
						target[key] = srcValue && typeof srcValue === 'object' ? srcValue : {}
					}

					target[key] = XF.extendObject(deep, target[key], copyValue)
				}
				else if (copyValue !== undefined)
				{
					target[key] = copyValue
				}
			})
		}

		return target
	}

	XF.createElement = (tagName, properties = {}, parent = null) =>
	{
		const element = document.createElement(tagName)

		Object.entries(properties).forEach(([property, value]) =>
		{
			if (typeof value === 'object')
			{
				Object.entries(value).forEach(([prop, val]) =>
				{
					switch (property)
					{
						case 'properties':
							return element[prop] = val
						case 'attributes':
							return element.setAttribute(prop, val)
						case 'dataset':
							return element.dataset[prop] = val
						case 'style':
							return element.style[prop] = val
						default:
							console.error('XF.createElement supports properties, attributes, dataset and style objects only.')
					}
				})
			}
			else
			{
				element[property] = value
			}
		})

		if (parent instanceof HTMLElement)
		{
			parent.appendChild(element)
		}

		return element
	}

	XF.createElementFromString = (value, parent = null) =>
	{
		const wrappedValue = `<body>${ value }</body>`
		const DOM = new window.DOMParser().parseFromString(wrappedValue, 'text/html').body

		let element

		if (DOM.children.length > 1)
		{
			const container = document.createElement('div')
			container.classList.add('js-createdContainer')
			container.append(...DOM.children)
			element = container
		}
		else
		{
			element = DOM.firstChild
		}

		if (parent instanceof Node)
		{
			parent.appendChild(element)
		}

		return element
	}

	XF.isCreatedContainer = element =>
	{
		return element instanceof Element && element.classList.contains('js-createdContainer')
	}

	XF.extendObject(XF, {
		config: {
			userId: null,
			enablePush: false,
			skipServiceWorkerRegistration: false,
			skipPushNotificationSubscription: false,
			skipPushNotificationCta: false,
			serviceWorkerPath: null,
			pushAppServerKey: null,
			csrf: document.querySelector('html').dataset.csrf,
			time: {
				now: 0,
				today: 0,
				todayDow: 0,
				tomorrow: 0,
				yesterday: 0,
				week: 0,
				month: 0,
				year: 0,
			},
			cookie: {
				path: '/',
				domain: '',
				prefix: 'xf_',
			},
			url: {
				fullBase: '/',
				basePath: '/',
				css: '',
				js: '',
				icon: '',
				keepAlive: '',
			},
			css: {},
			js: {},
			jsMt: {},
			fullJs: false,
			jsState: {},
			speed: {
				xxfast: 50,
				xfast: 100,
				fast: 200,
				normal: 400,
				slow: 600,
			},
			job: {
				manualUrl: '',
			},
			borderSizeFeature: '3px',
			fontAwesomeWeight: 'r',
			enableRtnProtect: true,
			enableFormSubmitSticky: true,
			visitorCounts: {
				conversations_unread: '0',
				alerts_unviewed: '0',
				total_unread: '0',
				title_count: false,
				icon_indicator: false,
			},
			uploadMaxFilesize: null,
			uploadMaxWidth: 0,
			uploadMaxHeight: 0,
			allowedVideoExtensions: [],
			allowedAudioExtensions: [],
			shortcodeToEmoji: true,
			publicMetadataLogoUrl: '',
			publicPushBadgeUrl: '',
		},

		debug: {
			disableAjaxSubmit: false,
		},

		counter: 1,

		pageDisplayTime: null,

		phrases: {},

		getApp: () => document.querySelector('html').dataset.app || null,

		isFunction: object => typeof object === 'function',

		isObject: object => object === Object(object),

		isPlainObject: object => Object.prototype.toString.call(object) === '[object Object]',

		isEmptyObject: object => Object.keys(object).length === 0,

		hasOwn: (object, key) => Object.prototype.hasOwnProperty.call(object, key),

		isNumeric: value => !isNaN(parseFloat(value)) && isFinite(value),

		isHidden: element =>
		{
			return window.getComputedStyle(element).display === 'none'
				|| element.offsetHeight === 0
				|| (element.type === 'hidden' && element.tagName.toLowerCase() === 'input')
		},

		toBoolean: value =>
		{
			switch (value)
			{
				case 'true':
				case 'yes':
				case 'on':
				case '1':
				case 1:
					return true

				default:
					return false
			}
		},

		toCamelCase: string => string.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase()),

		_scrollLeftType: null,

		scrollLeftType: () =>
		{
			if (XF._scrollLeftType !== null)
			{
				return XF._scrollLeftType
			}

			const isRtl = XF.isRtl()

			if (isRtl)
			{
				const tester = XF.createElementFromString('<div style="width: 80px; height: 40px; font-size: 30px; overflow: scroll; white-space: nowrap; word-wrap: normal; position: absolute; top: -1000px; visibility: hidden; pointer-events: none">MMMMMMMMMM</div>')
				document.body.append(tester)

				if (tester.scrollLeft > 0)
				{
					// max value at start, scrolls towards 0
					XF._scrollLeftType = 'inverted'
				}
				else
				{
					if (tester.scrollLeft === -1)
					{
						// 0 at start, scrolls towards negative values
						XF._scrollLeftType = 'negative'
					}
					else
					{
						XF._scrollLeftType = 'normal'
						// else normal: 0 at start, scrolls towards positive values
					}
				}

				tester.remove()
				return XF._scrollLeftType
			}

			XF._scrollLeftType = 'normal'

			return XF._scrollLeftType
		},

		/**
		 * An object to store all event handlers.
		 * @type {{[key: string]: Array<{handler: Function, options: Object}>}}
		 */
		eventHandlers: {},

		customEvent: (type, options) =>
		{
			return new XF_CustomEvent(type, options)
		},

		trigger: (element, eventObject, options = {}) =>
		{
			if (typeof eventObject === 'string')
			{
				eventObject = XF.customEvent(eventObject, options)
			}

			const namespacedEvent = eventObject.type
			const [event, namespace = 'default'] = namespacedEvent.split('.')

			if (!XF.eventHandlers[namespace] || !XF.eventHandlers[namespace][event])
			{
				return !(event.cancelable && event.defaultPrevented)
			}

			if (namespace === 'default')
			{
				return element.dispatchEvent(eventObject)
			}
			else
			{
				XF.eventHandlers[namespace][event].forEach(handlerData =>
				{
					if (element === handlerData.element)
					{
						const { handler, options } = handlerData

						if (options.once)
						{
							XF.off(element, namespacedEvent, handler, options)
						}

						handler.call(element, eventObject)

						if (options.passive && eventObject.defaultPrevented)
						{
							console.warn('preventDefault() was called on a namespaced passive event listener and this is not supported.')
						}
					}
				})

				return !(event.cancelable && event.defaultPrevented)
			}
		},

		/**
		 * Attach an event handler function for one or more events to the selected elements using event delegation.
		 * @param {EventTarget} element - The target on which to attach the event.
		 * @param {string} namespacedEvent - The namespaced event(s) string.
		 * @param {string} selector - The selector to match against for event delegation.
		 * @param {Function} handler - The function to execute when the event is triggered.
		 * @param {Object} [options={}] - Optional parameters to pass to addEventListener.
		 */
		onDelegated: (element, namespacedEvent, selector, handler, options = {}) =>
		{
			const delegatedHandler = event =>
			{
				const targetElement = event.target.closest(selector)
				if (targetElement && element.contains(targetElement))
				{
					handler.call(targetElement, event)
				}
			}

			delegatedHandler.originalHandler = handler

			XF.on(element, namespacedEvent, delegatedHandler, options)
		},

		/**
		 * Attach an event handler function for one or more events to the selected elements.
		 * @param {EventTarget} element - The target on which to attach the event.
		 * @param {string} namespacedEvent - The namespaced event(s) string.
		 * @param {Function} handler - The function to execute when the event is triggered.
		 * @param {Object} [options={}] - Optional parameters to pass to addEventListener.
		 */
		on: (element, namespacedEvents, handler, options = {}) =>
		{
			if (!element)
			{
				console.error('No element passed in to event.')
				return
			}

			namespacedEvents.split(' ').forEach(namespacedEvent =>
			{
				const [event, namespace = 'default'] = namespacedEvent.split('.')

				const handlerData = {
					element,
					handler,
					options,
				}

				if (!XF.eventHandlers[namespace])
				{
					XF.eventHandlers[namespace] = {}
				}

				if (!XF.eventHandlers[namespace][event])
				{
					XF.eventHandlers[namespace][event] = []
				}

				XF.eventHandlers[namespace][event].push(handlerData)

				element.addEventListener(event, handler, options)
			})
		},

		/**
		 * Remove an event handler.
		 * @param {EventTarget} element - The target from which to remove the event.
		 * @param {string} namespacedEvent - The namespaced event(s) string.
		 * @param {Function} [handler=null] - The function to remove. If omitted, all handlers for the event will be removed.
		 * @param {(Object|boolean)} [options=false] - Optional parameters to pass to removeEventListener.
		 */
		off: (element, namespacedEvent, handler = null, options = false) =>
		{
			const [event, namespace = 'default'] = namespacedEvent.split('.')

			if (!event && namespace)
			{
				// When namespace is provided without an event
				if (XF.eventHandlers[namespace])
				{
					Object.keys(XF.eventHandlers[namespace]).forEach(event =>
					{
						XF.eventHandlers[namespace][event].forEach(handlerData =>
						{
							element.removeEventListener(event, handlerData.handler, options)
						})
					})
					delete XF.eventHandlers[namespace]
				}
			}
			else if (XF.eventHandlers[namespace] && XF.eventHandlers[namespace][event])
			{
				XF.eventHandlers[namespace][event] = XF.eventHandlers[namespace][event].filter(handlerData =>
				{
					if (!handler || handler === handlerData.handler || handler === handlerData.handler.originalHandler)
					{
						element.removeEventListener(event, handlerData.handler, options)
						return false
					}
					return true
				})

				if (!XF.eventHandlers[namespace][event].length)
				{
					delete XF.eventHandlers[namespace][event]
				}
			}
		},

		onTransitionEnd: (el, duration, callback) =>
		{
			let called = false
			const f = (e, ...args) =>
			{
				if (e.target !== e.currentTarget)
				{
					return
				}

				if (called)
				{
					return
				}

				called = true
				XF.off(e.currentTarget, 'transitionend', f)
				return callback(e, ...args)
			}

			XF.on(el, 'transitionend', f)
			setTimeout(() =>
			{
				if (!called)
				{
					XF.trigger(el, 'transitionend')
				}
			}, duration + 10)
		},

		/**
		 * Allows an element to respond to an event fired by a parent element, such as the containing tab being un-hidden
		 *
		 * @param target - The target DOM element
		 * @param eventType - The type of event to listen for
		 * @param callback - The function to call when the event occurs
		 * @param once - True if you want this to execute once only
		 * @returns {target}
		 */
		onWithin: (target, eventType, callback, once = false) =>
		{
			const eventTypes = eventType.split(' ')

			eventTypes.forEach(event =>
			{
				const eventListenerFunc = e =>
				{
					if (e.target.contains(target))
					{
						if (once)
						{
							XF.off(document, event, eventListenerFunc)
						}

						callback(e)
					}
				}

				XF.on(document, event, eventListenerFunc)
			})

			return target
		},

		oneWithin: (target, eventType, callback) =>
		{
			return XF.onWithin(target, eventType, callback, true)
		},

		onPointer: (target, events, callback, options = {}) =>
		{
			if (XF.isPlainObject(events))
			{
				for (const k of Object.keys(events))
				{
					XF.onPointer(target, k, events[k], options)
				}
				return target
			}

			if (typeof events === 'string')
			{
				events = events.split(/\s+/)
			}

			const dataKey = 'data-xf-pointer-type'

			events.forEach(event => XF.on(target, event, e =>
			{
				e.xfPointerType = e.pointerType || target.getAttribute(dataKey) || ''
				callback(e)
			}, options))
			XF.on(target, 'pointerdown', e => target.setAttribute(dataKey, e.pointerType), { passive: true })

			return target
		},

		uniqueId: (el) =>
		{
			let id = el.getAttribute('id')

			if (!id)
			{
				id = 'js-XFUniqueId' + XF.getUniqueCounter()
				el.setAttribute('id', id)
			}

			return id
		},

		findExtended: (selector, baseElement) =>
		{
			const match = selector.match(/^<([^|]+)(\|([\s\S]+))?$/)
			if (typeof selector === 'string' && match)
			{
				let lookUp = match[1].trim()

				let innerMatch

				let i

				const relativeLookup = {
					up: 'parentElement',
					next: 'nextElementSibling',
					prev: 'previousElementSibling',
				}

				let move

				let newBase = baseElement

				do
				{
					innerMatch = lookUp.match(/^:(up|next|prev)(\((\d+)\))?/)
					if (innerMatch)
					{
						if (!innerMatch[2])
						{
							innerMatch[3] = 1
						}

						move = relativeLookup[innerMatch[1]]

						for (i = 0; i < innerMatch[3]; i++)
						{
							newBase = newBase[move]
							if (!newBase)
							{
								newBase = null
							}
						}

						lookUp = lookUp.substr(innerMatch[0].length).trim()
					}
				}
				while (innerMatch)

				if (lookUp.length)
				{
					newBase = newBase.closest(lookUp)
				}

				if (!newBase)
				{
					newBase = null
				}

				selector = match[2] ? match[3].trim() : ''

				if (newBase && selector.length)
				{
					return newBase.querySelectorAll(selector)
				}
				else
				{
					return newBase
				}
			}

			return baseElement.querySelectorAll(selector)
		},

		position: element =>
		{
			return {
				top: element.offsetTop,
				left: element.offsetLeft,
			}
		},

		dimensions: (element, outer, outerWithMargin) =>
		{
			const rect = element.getBoundingClientRect()
			const dimensions = {
				top: rect.top + window.scrollY,
				left: rect.left + window.scrollX,
			}

			if (outer)
			{
				dimensions.width = element.offsetWidth || rect.width
				dimensions.height = element.offsetHeight || rect.height

				if (outerWithMargin)
				{
					const style = window.getComputedStyle(element)
					dimensions.width += parseInt(style.marginLeft) + parseInt(style.marginRight)
					dimensions.height += parseInt(style.marginTop) + parseInt(style.marginBottom)
				}
			}
			else
			{
				dimensions.width = element.clientWidth || rect.width
				dimensions.height = element.clientHeight || rect.height
			}

			dimensions.right = dimensions.left + dimensions.width
			dimensions.bottom = dimensions.top + dimensions.height

			return dimensions
		},

		viewport: (element, outer, outerWithMargin) =>
		{
			if (element === window)
			{
				element = document.documentElement
			}

			const vp = {
				width: outer ? element.offsetWidth : element.clientWidth,
				height: outer ? element.offsetHeight : element.clientHeight,
				left: element.scrollLeft,
				top: element.scrollTop,
				right: 0,
				bottom: 0,
				docWidth: document.documentElement.scrollWidth,
				docHeight: document.documentElement.scrollHeight,
			}

			vp.bottom = vp.top + vp.height
			vp.right = vp.left + vp.width

			return vp
		},

		hasFixableParent: (element) =>
		{
			let fixableParent = false

			while (element.parentNode)
			{
				element = element.parentNode

				if (element instanceof Element)
				{
					const style = window.getComputedStyle(element)
					const position = style.getPropertyValue('position')

					switch (position)
					{
						case 'fixed':
						case 'sticky':
						case '-webkit-sticky':
							fixableParent = element
							break
					}

					if (fixableParent)
					{
						break
					}
				}
			}

			return fixableParent
		},

		hasFixedParent: (element) =>
		{
			let fixedParent = false

			while (element.parentNode)
			{
				element = element.parentNode

				if (element instanceof Element)
				{
					const style = window.getComputedStyle(element)
					const position = style.getPropertyValue('position')

					switch (position)
					{
						case 'fixed':
							fixedParent = element
							break
						case 'sticky':
						case '-webkit-sticky':
						{
							const elDimensions = XF.dimensions(element, true)
							const viewport = XF.viewport(window)
							const stickyTop = style.getPropertyValue('top')
							const stickyBottom = style.getPropertyValue('bottom')
							let edgeDiff

							if (stickyTop !== 'auto')
							{
								edgeDiff = (elDimensions.top - viewport.top) - parseInt(stickyTop, 10)
								if (Math.abs(edgeDiff) <= 0.5)
								{
									fixedParent = element
									break
								}
							}

							if (stickyBottom !== 'auto')
							{
								edgeDiff = (elDimensions.bottom - viewport.bottom) - parseInt(stickyBottom, 10)
								if (Math.abs(edgeDiff) <= 0.5)
								{
									fixedParent = element
									break
								}
							}
						}
					}

					if (fixedParent)
					{
						break
					}
				}
			}

			return fixedParent
		},

		autofocus: input =>
		{
			if (XF.isIOS())
			{
				if (!input.matches(':focus'))
				{
					input.classList.add('is-focused')
					XF.on(input, 'blur', () => input.classList.remove('is-focused'))
				}
			}
			else
			{
				input.focus()
			}

			return this
		},

		replaceSelectedText: (input, replacement) =>
		{
			let start = input.selectionStart
			let end = input.selectionEnd

			input.setRangeText(replacement, start, end, 'preserve')
			input.selectionStart = start + replacement.length
			input.selectionEnd = input.selectionStart
		},

		normalizedScrollLeft: (element, newLeft) =>
		{
			const type = XF.scrollLeftType()

			if (typeof newLeft !== 'undefined')
			{
				let newValue = newLeft
				switch (type)
				{
					case 'negative':
						newValue = newValue > 0 ? -newValue : 0
						break

					case 'inverted':
						newValue = element.scrollWidth - element.offsetWidth - newValue
						break

					// otherwise don't need to change
				}

				element.scrollLeft = newValue
				return
			}

			const scrollLeft = element.scrollLeft

			switch (type)
			{
				case 'negative':
					return scrollLeft < 0 ? -scrollLeft : 0

				case 'inverted':
				{
					const calc = element.scrollWidth - scrollLeft - element.offsetWidth
					return (calc < 0.5 ? 0 : calc) // avoid rounding issues
				}

				case 'normal':
				default:
					return scrollLeft
			}
		},

		/**
		 * Attempts to focus the next focusable element
		 *
		 * @param {HTMLElement} element - The element from which to start searching for the next focusable element.
		 * @return The next focusable element
		 */
		focusNext: (element) =>
		{
			const focusableSelectors = ['input:not([type="hidden"])', 'select', 'textarea', 'a', 'button']
			let focusableElements = Array.prototype.slice.call(document.querySelectorAll(focusableSelectors.join(',')))

			focusableElements = focusableElements.filter((el) =>
			{
				return el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0
			})

			const currentIndex = focusableElements.indexOf(element)
			const nextElement = focusableElements[currentIndex + 1] || null

			if (nextElement)
			{
				nextElement.focus()
			}

			return nextElement
		},

		getKeyboardInputs: () =>
		{
			return 'input:not([type=radio], [type=checkbox], [type=submit], [type=reset]), textarea'
		},

		onPageLoad: () =>
		{
			XF.trigger(document, 'xf:page-load-start')

			XF.NavDeviceWatcher.initialize()
			XF.ActionIndicator.initialize()
			XF.DynamicDate.initialize()
			XF.KeepAlive.initialize()
			XF.LinkWatcher.initLinkProxy()
			XF.LinkWatcher.initExternalWatcher()
			XF.ExpandableContent.watch()
			XF.ScrollButtons.initialize()
			XF.NavButtons.initialize()
			XF.KeyboardShortcuts.initialize()
			XF.FormInputValidation.initialize()
			XF.PWA.initialize()
			XF.Push.initialize()
			XF.IgnoreWatcher.initializeHash()
			XF.BrowserWarning.display()
			XF.BrowserWarning.hideJsWarning()
			XF.History.initialize()
			XF.PullToRefresh.initialize()
			XF.LazyHandlerLoader.initialize()

			XF.config.jsState = XF.applyJsState({}, XF.config.jsState)

			XF.activate(document)

			XF.on(document, 'ajax:complete', e =>
			{
				const {
					response,
					data,
					error,
				} = e

				if (!data)
				{
					return
				}

				if (data.visitor)
				{
					XF.updateVisitorCounts(data.visitor, true)
				}
			})

			XF.on(document, 'ajax:before-success', e =>
			{
				const { data } = e

				if (!data)
				{
					return
				}

				if (data && data.job)
				{
					const job = data.job
					if (job.manual)
					{
						XF.JobRunner.runManual(job.manual)
					}

					if (job.autoBlocking)
					{
						XF.JobRunner.runAutoBlocking(job.autoBlocking, job.autoBlockingMessage)
					}
					else if (job.auto)
					{
						setTimeout(XF.JobRunner.runAuto, 0)
					}
				}
			})

			XF.on(document, 'keyup', e =>
			{
				if (e.key === 'Enter')
				{
					const target = e.target
					if (target.matches('a:not([href])'))
					{
						target.click()
					}
				}
			})

			if (document.querySelector('html[data-run-jobs]'))
			{
				setTimeout(XF.JobRunner.runAuto, 100)
			}

			if (XF.config.visitorCounts)
			{
				XF.updateVisitorCountsOnLoad(XF.config.visitorCounts)
			}

			XF.CrossTab.on('visitorCounts', counts =>
			{
				XF.updateVisitorCounts(counts, false)
			})

			XF.pageLoadScrollFix()

			setTimeout(() =>
			{
				const first = document.querySelector('[data-load-auto-click]')
				if (first)
				{
					first.click()
				}
			}, 100)

			XF.trigger(document, 'xf:page-load-complete')
		},

		addExtraPhrases: el =>
		{
			const scripts = el.querySelectorAll('script.js-extraPhrases')
			scripts.forEach(script =>
			{
				let phrases

				try
				{
					phrases = JSON.parse(script.textContent) || {}
					XF.extendObject(XF.phrases, phrases)
				}
				catch (e)
				{
					console.error(e)
				}

				script.remove()
			})
		},

		phrase: (name, vars, fallback) =>
		{
			let phrase = XF.phrases[name]
			if (phrase && vars)
			{
				phrase = XF.stringTranslate(phrase, vars)
			}
			return phrase || fallback || name
		},

		_isRtl: null,

		isRtl: () =>
		{
			if (XF._isRtl === null)
			{
				const dir = document.querySelector('html').getAttribute('dir')
				XF._isRtl = (dir && dir.toUpperCase() === 'RTL')
			}
			return XF._isRtl
		},

		rtlFlipKeyword: keyword =>
		{
			if (!XF.isRtl())
			{
				return keyword
			}

			const lower = keyword.toLowerCase()
			switch (lower)
			{
				case 'left':
					return 'right'
				case 'right':
					return 'left'
				default:
					return keyword
			}
		},

		isMac: () => XF.browser.os === 'mac',

		isIOS: () => XF.browser.os === 'ios',

		log: (...args) =>
		{
			if (!console.log)
			{
				return
			}

			console.log(...args)
		},

		findRelativeIf: (selector, baseElement, single = true) =>
		{
			if (!selector)
			{
				throw new Error('No selector provided')
			}

			if (selector.endsWith('>'))
			{
				selector += ' *'
			}

			let els
			const match = selector.match(/^([<>|])/)
			if (match)
			{
				if (match[1] === '<')
				{
					els = XF.findExtended(selector, baseElement)

					if (single && els instanceof NodeList)
					{
						els = els.length >= 1 ? els[0] : null
					}
				}
				else
				{
					if (match[1] === '|')
					{
						selector = selector.substring(1)
					}
					else if (match[1] === '>')
					{
						selector = ':scope ' + selector
					}

					els = single
						? baseElement.querySelector(selector)
						: baseElement.querySelectorAll(selector)
				}
			}
			else
			{
				els = single
					? document.querySelector(selector)
					: document.querySelectorAll(selector)
			}

			return els
		},

		isElementVisible: el =>
		{
			const docEl = document.documentElement
			const rect = el.getBoundingClientRect()

			return (
				rect.top >= 0 &&
				rect.left >= 0 &&
				rect.bottom <= docEl.clientHeight &&
				rect.right <= docEl.clientWidth
			)
		},

		/**
		 * Simple function to be run whenever we change the page layout with JS,
		 * to trigger recalculation of JS-positioned elements
		 * such as sticky_kit items
		 */
		layoutChange: () =>
		{
			if (!XF._layoutChangeTriggered)
			{
				XF._layoutChangeTriggered = true
				setTimeout(() =>
				{
					XF._layoutChangeTriggered = false

					// Triggering custom events
					XF.trigger(document.body, 'xf:layout')
				}, 0)
			}
		},

		_layoutChangeTriggered: false,

		updateAvatars: (userId, newAvatars, includeEditor) =>
		{
			const avatarElements = document.querySelectorAll('.avatar')

			avatarElements.forEach(avatarContainer =>
			{
				const avatar = avatarContainer.querySelector('img, span')
				const classPrefix = 'avatar-u' + userId + '-'
				const update = avatarContainer.classList.contains('avatar--updateLink') ? avatarContainer.querySelector('.avatar-update') : null
				let newAvatar

				if (!includeEditor && avatar.classList.contains('js-croppedAvatar'))
				{
					return
				}

				if (avatar.className.startsWith(classPrefix))
				{
					if (avatar.classList.contains(classPrefix + 's'))
					{
						newAvatar = XF.createElementFromString(newAvatars['s'])
					}
					else if (avatar.classList.contains(classPrefix + 'm'))
					{
						newAvatar = XF.createElementFromString(newAvatars['m'])
					}
					else if (avatar.classList.contains(classPrefix + 'l'))
					{
						newAvatar = XF.createElementFromString(newAvatars['l'])
					}
					else if (avatar.classList.contains(classPrefix + 'o'))
					{
						newAvatar = XF.createElementFromString(newAvatars['o'])
					}
					else
					{
						return
					}

					avatarContainer.innerHTML = ''
					avatarContainer.append(...newAvatar.childNodes)

					if (newAvatar.classList.contains('avatar--default'))
					{
						avatarContainer.classList.add('avatar--default')

						if (newAvatar.classList.contains('avatar--default--dynamic'))
						{
							avatarContainer.classList.add('avatar--default--dynamic')
						}
						else if (newAvatar.classList.contains('avatar--default--text'))
						{
							avatarContainer.classList.add('avatar--default--text')
						}
						else if (newAvatar.classList.contains('avatar--default--image'))
						{
							avatarContainer.classList.add('avatar--default--image')
						}
					}
					else
					{
						avatarContainer.classList.remove('avatar--default', 'avatar--default--dynamic', 'avatar--default--text', 'avatar--default--image')
					}

					avatarContainer.setAttribute('style', newAvatar.getAttribute('style'))

					if (update)
					{
						avatarContainer.appendChild(update)
					}
				}
			})
		},

		updateVisitorCounts: (visitor, isForegroundUpdate, sourceTime) =>
		{
			if (!visitor || XF.getApp() !== 'public')
			{
				return
			}

			XF.badgeCounterUpdate(document.querySelectorAll('.js-badge--conversations'), visitor.conversations_unread)
			XF.badgeCounterUpdate(document.querySelectorAll('.js-badge--alerts'), visitor.alerts_unviewed)

			if (XF.config.visitorCounts['title_count'])
			{
				XF.pageTitleCounterUpdate(visitor.total_unread)
			}

			if (XF.config.visitorCounts['icon_indicator'])
			{
				XF.faviconUpdate(visitor.total_unread)
			}

			if (isForegroundUpdate)
			{
				XF.appBadgeUpdate(visitor.total_unread)

				XF.CrossTab.trigger('visitorCounts', visitor)

				XF.LocalStorage.setJson('visitorCounts', {
					time: sourceTime || (Math.floor(new Date().getTime() / 1000) - 1),
					conversations_unread: visitor.conversations_unread,
					alerts_unviewed: visitor.alerts_unviewed,
					total_unread: visitor.total_unread,
				})
			}

			// TODO: Stack alerts?
		},

		updateVisitorCountsOnLoad: (visitor) =>
		{
			const localLoadTime = XF.getLocalLoadTime()
			const cachedData = XF.LocalStorage.getJson('visitorCounts')

			if (cachedData && cachedData.time && cachedData.time > localLoadTime)
			{
				visitor.conversations_unread = cachedData.conversations_unread
				visitor.alerts_unviewed = cachedData.alerts_unviewed
				visitor.total_unread = cachedData.total_unread
			}

			XF.updateVisitorCounts(visitor, true, localLoadTime)
		},

		badgeCounterUpdate: (badges, newCount) =>
		{
			badges.forEach(badge =>
			{
				if (!badge)
				{
					return
				}

				badge.setAttribute('data-badge', newCount)

				if (String(newCount) !== '0')
				{
					badge.classList.add('badgeContainer--highlighted')
				}
				else
				{
					badge.classList.remove('badgeContainer--highlighted')
				}
			})
		},

		shouldCountBeShown: newCount =>
		{
			const newCountNormalized = parseInt(newCount.replace(/[,. ]/g, ''))
			return newCountNormalized > 0
		},

		pageTitleCache: '',

		pageTitleCounterUpdate: (newCount) =>
		{
			let pageTitle = document.title
			let newTitle

			if (XF.pageTitleCache === '')
			{
				XF.pageTitleCache = pageTitle
			}

			if (pageTitle !== XF.pageTitleCache && pageTitle.charAt(0) === '(')
			{
				pageTitle = XF.pageTitleCache
			}

			newTitle = (XF.shouldCountBeShown(newCount) ? '(' + newCount + ') ' : '') + pageTitle

			if (newTitle !== document.title)
			{
				document.title = newTitle
			}
		},

		favIconAlertShown: false,

		faviconUpdate: newCount =>
		{
			const shouldBeShown = XF.shouldCountBeShown(newCount)
			if (shouldBeShown === XF.favIconAlertShown)
			{
				return
			}

			const favicons = document.querySelectorAll('link[rel~="icon"]')

			if (!favicons.length)
			{
				// no favicons support
				return
			}

			XF.favIconAlertShown = shouldBeShown

			favicons.forEach(favicon =>
			{
				const href = favicon.getAttribute('href')
				const originalHrefKey = 'originalHref'
				const originalHref = favicon.dataset[originalHrefKey]

				if (XF.shouldCountBeShown(newCount))
				{
					if (!originalHref)
					{
						favicon.dataset[originalHrefKey] = href
					}

					const img = new Image()
					XF.on(img, 'load', () =>
					{
						const updatedFaviconUrl = XF.faviconDraw(img)

						if (updatedFaviconUrl)
						{
							favicon.setAttribute('href', updatedFaviconUrl)
						}
					})
					img.src = href
				}
				else
				{
					if (originalHref)
					{
						favicon.setAttribute('href', originalHref)
						delete favicon.dataset[originalHrefKey]
					}
				}
			})
		},

		faviconDraw: (image) =>
		{
			const w = image.naturalWidth
			const h = image.naturalHeight

			const canvas = XF.createElement('canvas', {
				width: w,
				height: h
			})

			const context = canvas.getContext('2d')

			const ratio = 32 / 6
			const radius = w / ratio
			const x = radius
			const y = radius
			const startAngle = 0
			const endAngle = Math.PI * 2
			const antiClockwise = false

			context.drawImage(image, 0, 0)
			context.beginPath()
			context.arc(x, y, radius, startAngle, endAngle, antiClockwise)
			context.fillStyle = '#E03030'
			context.fill()
			context.lineWidth = w / 16
			context.strokeStyle = '#EAEAEA'
			context.stroke()
			context.closePath()

			try
			{
				return canvas.toDataURL('image/png')
			}
			catch (e)
			{
				return null
			}
		},

		appBadgeUpdate: newCount =>
		{
			if (!('setAppBadge' in navigator))
			{
				return
			}

			if (navigator.webdriver)
			{
				return
			}

			if (navigator.userAgent.match(/Chrome-Lighthouse|Googlebot|AdsBot-Google|Mediapartners-Google/i))
			{
				return
			}

			newCount = parseInt(String(newCount).replace(/[,. ]/g, ''))
			navigator.setAppBadge(newCount)
		},

		/**
		 * Attempts to convert various HTML-BB codes back into BB code
		 *
		 * @param html
		 *
		 * @returns string
		 */
		unparseBbCode: html =>
		{
			const div = XF.createElement('div', {
				innerHTML: html || ''
			})

			// get rid of anything with this class
			div.querySelectorAll('.js-noSelectToQuote').forEach(el => el.remove())

			// handle b, i, u, s
			;['B', 'I', 'U', 'S'].forEach(tagName =>
			{
				div.querySelectorAll(tagName).forEach(el =>
				{
					el.outerHTML = `[${ tagName.toLowerCase() }]${ el.innerHTML }[/${ tagName.toLowerCase() }]`
				})
			})

			// handle quote tags as best we can
			div.querySelectorAll('.bbCodeBlock--quote').forEach(el =>
			{
				const quote = el.querySelector('.bbCodeBlock-expandContent')
				if (quote)
				{
					el.outerHTML = `<div>[QUOTE]${ quote.innerHTML }[/QUOTE]</div>`
				}
				else
				{
					quote.querySelector('.bbCodeBlock-expand').remove()
				}
			})

			// now for PHP, CODE and HTML
			div.querySelectorAll('.bbCodeBlock--code').forEach(el =>
			{
				const code = el.querySelector('.bbCodeCode code')
				if (!code)
				{
					return true
				}

				const cl = code.className, match = cl ? cl.match(/language-(\S+)/) : null,
					language = match ? match[1] : null

				code.removeAttribute('class')
				el.outerHTML = code.outerHTML
				code.setAttribute('data-language', language || 'none')
			})

			// handle [URL unfurl=true] tags
			div.querySelectorAll('.bbCodeBlock--unfurl').forEach(el =>
			{
				const url = el.dataset.url
				el.outerHTML = '[URL unfurl=true]' + url + '[/URL]'
			})

			// now alignment tags
			div.querySelectorAll('div[style*="text-align"]').forEach(el =>
			{
				const align = window.getComputedStyle(el).textAlign.toUpperCase()
				el.outerHTML = `[${ align }]${ el.innerHTML }[/${ align }]`
			})

			div.querySelectorAll('div[data-media-site-id][data-media-key], form[data-media-site-id][data-media-key]').forEach(el =>
			{
				const siteId = el.dataset.mediaSiteId
				const mediaKey = el.dataset.mediaKey

				if (!siteId || !mediaKey)
				{
					return true
				}

				el.outerHTML = `[MEDIA=${ siteId }]${ mediaKey }[/MEDIA]`
			})

			// and finally, spoilers...
			div.querySelectorAll('.bbCodeSpoiler').forEach(el =>
			{
				const button = el.querySelector('.bbCodeSpoiler-button')
				if (button)
				{
					const spoilerText = el.querySelector('.bbCodeSpoiler-content').innerHTML
					let spoilerTitle = ''
					const spoilerTitleEl = button.querySelector('.bbCodeSpoiler-button-title')

					if (spoilerTitleEl)
					{
						spoilerTitle = `="${ spoilerTitleEl.textContent }"`
					}

					el.outerHTML = `[SPOILER${ spoilerTitle }]${ spoilerText }[/SPOILER]`
				}
			})

			div.querySelectorAll('.bbCodeInlineSpoiler').forEach(el =>
			{
				const spoilerText = el.innerHTML
				el.outerHTML = `[ISPOILER]${ spoilerText }[/ISPOILER]`
			})

			return div.innerHTML
		},

		hideOverlays: () =>
		{
			Object.values(XF.Overlay.cache).forEach(overlay =>
			{
				overlay.hide()
			})
		},

		hideTooltips: () =>
		{
			Object.values(XF.TooltipTrigger.cache).forEach(trigger => trigger.hide())
		},

		hideParentOverlay: child =>
		{
			const overlayContainer = child.closest('.overlay-container')
			if (overlayContainer)
			{
				const overlay = XF.DataStore.get(overlayContainer, 'overlay')
				if (overlay)
				{
					overlay.hide()
				}
			}
		},

		getStickyHeaderOffset: () =>
		{
			let i, offset = 0

			for (i = 0; i < XF.StickyHeader.cache.length; i++)
			{
				const stickyHeader = XF.StickyHeader.cache[i]
				if (stickyHeader.target.classList.contains(stickyHeader.options.stickyClass))
				{
					offset += stickyHeader.target.offsetHeight
				}
			}

			return offset
		},

		loadedScripts: {},

		/**
		 * Given a URL, load it (if not already loaded)
		 * before running a callback function on success
		 *
		 * @param url
		 * @param successCallback
		 */
		loadScript: (url, successCallback) =>
		{
			if (XF.hasOwn(XF.loadedScripts, url))
			{
				return false
			}

			XF.loadedScripts[url] = true

			const script = XF.createElement('script', {
				src: url,
				onload: successCallback
			})

			document.head.appendChild(script)

			return true
		},

		/**
		 * Given an array of URLs, load them all (if not already loaded)
		 * before running a callback function on complete (success or error).
		 *
		 * In the absolute majority of browsers, this will execute the loaded scripts in the order provided.
		 *
		 * @param urls
		 * @param completeCallback
		 */
		loadScripts: (urls, completeCallback) =>
		{
			const firstScript = document.scripts[0]
			const useAsync = 'async' in firstScript
			const useReadyState = firstScript.readyState
			const head = document.head
			let toLoad = urls.length
			const pendingScripts = []

			const loaded = () =>
			{
				toLoad--
				if (toLoad === 0 && completeCallback)
				{
					completeCallback()
				}
			}

			const stateChange = () =>
			{
				let pendingScript
				while (pendingScripts[0] && pendingScripts[0].readyState === 'loaded')
				{
					pendingScript = pendingScripts.shift()
					pendingScript.onreadystatechange = null
					pendingScript.onerror = null
					head.appendChild(pendingScript)

					loaded()
				}
			}

			for (const url of urls)
			{
				if (XF.loadedScripts[url])
				{
					continue
				}

				XF.loadedScripts[url] = true

				if (useAsync)
				{
					const script = XF.createElement('script', {
						src: url,
						async: false,
						onload: loaded,
						onerror: loaded
					}, head)
				}
				else if (useReadyState)
				{
					const script = XF.createElement('script', {
						onreadystatechange: stateChange,
						onerror: () =>
						{
							script.onreadystatechange = null
							script.onerror = null
							loaded()
						},
						url: url
					})
					pendingScripts.push(script)
				}
				else
				{
					const xhr = new XMLHttpRequest()
					xhr.open('GET', url, true)
					xhr.onload = () =>
					{
						if (xhr.status >= 200 && xhr.status < 400)
						{
							const script = XF.createElement('script', {
								text: xhr.response
							}, head)
							loaded()
						}
						else
						{
							loaded()
						}
					}
					xhr.onerror = loaded
					xhr.send()
				}
			}

			if (!toLoad && completeCallback)
			{
				completeCallback()
			}
		},

		async ajax (method, url, data = {}, successCallback, options = {})
		{
			if (typeof data === 'function' && successCallback === undefined)
			{
				successCallback = data
				data = {}
			}

			if (data instanceof HTMLFormElement)
			{
				data = new FormData(data)
			}

			let useDefaultSuccess = true
			let useDefaultSuccessError = true
			let useError = true
			let global = true

			if (options.skipDefault)
			{
				useDefaultSuccess = false
				useDefaultSuccessError = false
				delete options.skipDefault
			}
			if (options.skipDefaultSuccessError)
			{
				useDefaultSuccessError = false
				delete options.skipDefaultSuccessError
			}
			if (options.skipDefaultSuccess)
			{
				useDefaultSuccess = false
				delete options.skipDefaultSuccess
			}
			if (options.skipError)
			{
				useError = false
				delete options.skipError
			}
			if (options.global !== undefined)
			{
				global = options.global ? true : false
				delete options.global
			}

			const onSuccess = (request, response, data) =>
			{
				XF.trigger(document, XF.customEvent('ajax:before-success', {
					request,
					response,
					status: response.status,
					data,
				}))

				if (useDefaultSuccessError && XF.defaultAjaxSuccessError(data, response.status, response, request))
				{
					return
				}

				if (useDefaultSuccess && XF.defaultAjaxSuccess(data, response.status, response, request))
				{
					return
				}

				if (successCallback)
				{
					successCallback(data, response.status, response, request)
				}
			}

			const onError = (request, response, data, error = null) =>
			{
				if (useError)
				{
					XF.defaultAjaxError(data, response?.status, response, request, error)
				}
			}

			const headers = {
				'X-Requested-With': 'XMLHttpRequest',
			}
			let body

			const dataType = options.dataType || 'json'
			delete options.dataType

			let parseResponse
			switch (dataType)
			{
				case 'html':
					parseResponse = response => response.text()
					headers['Accept'] = 'text/html'
					break

				case 'json':
					parseResponse = response => response.json()
					headers['Accept'] = 'application/json'
					break

				case 'xml':
					parseResponse = response => response.text()
					headers['Accept'] = 'application/xml'
					break

				default:
					throw new Error(`Unsupported dataType: ${dataType}`)
			}

			if (dataType !== 'json')
			{
				useDefaultSuccess = false
			}

			data = XF.dataPush(data, '_xfResponseType', dataType)
			data = XF.dataPush(data, '_xfWithData', 1)
			data = XF.dataPush(data, '_xfRequestUri', window.location.pathname + window.location.search)

			if (XF.config.csrf)
			{
				data = XF.dataPush(data, '_xfToken', XF.config.csrf)
			}

			if (method.toUpperCase() === 'GET')
			{
				url += (url.includes('?') ? '&' : '?') + new URLSearchParams(data).toString()
			}
			else
			{
				if (data instanceof FormData)
				{
					body = data
				}
				else if (Array.isArray(data))
				{
					body = XF.Serializer.serializeFormData(data)
					headers['Content-Type'] = 'application/x-www-form-urlencoded'
				}
				else
				{
					body = JSON.stringify(data)
					headers['Content-Type'] = 'application/json'
				}
			}

			const controller = new AbortController()
			const signal = controller.signal

			if (options.signal)
			{
				const signal = options.signal

				signal.addEventListener('abort', () =>
				{
					controller.abort()
				})
			}
			delete options.signal

			const defaultTimeout = method.toUpperCase() === 'GET'
				? 30000
				: 60000
			const timeoutId = setTimeout(
				() =>
				{
					controller.abort(new DOMException(
						'_XF_TIMEOUT',
						'AbortError'
					))
				},
				options.timeout || defaultTimeout
			)
			delete options.timeout

			const request = new Request(url, {
				method,
				headers,
				body,
				signal,
				...options,
			})

			XF.trigger(document, XF.customEvent('ajax:send', { request }))

			if (global)
			{
				XF.trigger(document, 'xf:action-start')
			}

			try
			{
				const response = await fetch(request)
				let data

				if (response.ok)
				{
					data = await parseResponse(response)
					onSuccess(request, response, data)
				}
				else
				{
					data = await response.text()

					let success = false
					try
					{
						data = JSON.parse(data)
						success = true
					}
					catch
					{
						// handled below
					}

					if (success)
					{
						onSuccess(request, response, data)
					}
					else
					{
						onError(request, response, data)
					}
				}

				XF.trigger(document, XF.customEvent('ajax:complete', {
					request,
					response,
					data,
				}))

				return { request, response, data }
			}
			catch (error)
			{
				onError(request, null, null, error)

				XF.trigger(document, XF.customEvent('ajax:complete', {
					request,
					error
				}))

				return { request, error }
			}
			finally
			{
				clearTimeout(timeoutId)

				if (global)
				{
					XF.trigger(document, 'xf:action-stop')
				}
			}
		},

		ajaxAbortable (method, url, data = {}, successCallback, options = {})
		{
			const controller = new AbortController()
			options.signal = controller.signal

			const ajax = XF.ajax(method, url, data, successCallback, options)

			return {
				ajax,
				controller,
			}
		},

		dataPush: (data, key, value) =>
		{
			if (!data || typeof data === 'string')
			{
				// data is empty, or a url string - &name=value
				data = String(data)
				data += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value)
			}
			else if (data[0] !== undefined)
			{
				// data is a numerically-keyed array of name/value pairs
				data.push({
					name: key,
					value,
				})
			}
			else if (data instanceof FormData)
			{
				// data is a FormData object
				data.append(key, value)
			}
			else
			{
				// data is an object with a single set of name & value properties
				data[key] = value
			}

			return data
		},

		defaultAjaxSuccessError: (data, status, response, request) =>
		{
			if (typeof data !== 'object')
			{
				XF.alert('Response was not JSON.')
				return true
			}

			if (data.html && data.html.templateErrors)
			{
				let templateErrorStr = 'Errors were triggered when rendering this template:'
				if (data.html.templateErrorDetails)
				{
					templateErrorStr += '\n* ' + data.html.templateErrorDetails.join('\n* ')
				}
				console.error(templateErrorStr)
			}

			if (data.errorHtml)
			{
				XF.setupHtmlInsert(data.errorHtml, (html, container) =>
				{
					const title = container.h1 || container.title || XF.phrase('oops_we_ran_into_some_problems')
					XF.overlayMessage(title, html)
				})
				return true
			}

			if (data.errors)
			{
				XF.alert(data.errors)
				return true
			}

			if (data.exception)
			{
				XF.alert(data.exception)
				return true
			}

			return false
		},

		defaultAjaxSuccess: (data, status, response, request) =>
		{
			if (data && data.status === 'ok' && data.message)
			{
				XF.flashMessage(data.message, 3000)
				// let the real callback still run
			}

			return false
		},

		defaultAjaxError: (data, status, response, request, error) =>
		{
			if (error instanceof DOMException && error.name === 'AbortError')
			{
				// aborted by controller
				if (error.message === '_XF_TIMEOUT')
				{
					// aborted due to timeout
					XF.alert(XF.phrase('server_did_not_respond_in_time_try_again'))
				}

				return
			}

			if (error instanceof Error)
			{
				// request malformed, network error, or unknown error
				throw error
			}

			console.error('Error:', data)
			XF.alert(XF.phrase('oops_we_ran_into_some_problems_more_details_console'))
		},

		activate: el =>
		{
			XF.addExtraPhrases(el)
			XF.IgnoreWatcher.refresh(el)
			XF.Element.initialize(el)
			XF.DynamicDate.refresh(el)
			XF.ExpandableContent.checkSizing(el)
			XF.UnfurlLoader.activateContainer(el)
			XF.KeyboardShortcuts.initializeElements(el)
			XF.FormInputValidation.initializeElements(el)
			XF.LazyHandlerLoader.loadLazyHandlers(el)

			if (window.FB)
			{
				const fbEl = el instanceof Document ? el.body : el
				setTimeout(() => FB.XFBML.parse(fbEl), 0)
			}

			XF.trigger(document, XF.customEvent('xf:reinit', { element: el }))
		},

		activateAll: els =>
		{
			Array.from(els).forEach(el => XF.activate(el))
		},

		getDefaultFormData: (form, submitButton, jsonName, jsonOptIn) =>
		{
			let formData
			let submitName

			if (submitButton && submitButton.hasAttribute('name'))
			{
				submitName = submitButton.getAttribute('name')
			}

			if (jsonName && form.getAttribute('enctype') === 'multipart/form-data')
			{
				console.error('JSON serialized forms do not support the file upload-style enctype.')
			}

			if (window.FormData && !jsonName)
			{
				formData = new FormData(form)
				if (submitName)
				{
					formData.append(submitName, submitButton.getAttribute('value') || submitName)
				}

				// note: this is to workaround a Safari/iOS bug which falls over on empty file inputs
				form.querySelectorAll('input[type="file"]').forEach(input =>
				{
					const files = input.files

					if (typeof files !== 'undefined' && files.length === 0)
					{
						try
						{
							formData.delete(input.getAttribute('name'))
						}
						catch (e)
						{
							// ignore
						}
					}
				})
			}
			else
			{
				if (jsonName)
				{
					const elements = form.elements
					let jsonOptInRegex
					const jsonEls = []
					const regularEls = []
					let inputs

					if (jsonOptIn)
					{
						if (typeof jsonOptIn === 'string')
						{
							jsonOptIn = jsonOptIn.split(',')
						}

						const jsonOptInRegexFields = []
						jsonOptIn.forEach((v, i) =>
						{
							if (typeof i === 'number')
							{
								jsonOptInRegexFields.push(XF.regexQuote(v.trim()))
							}
							else
							{
								jsonOptInRegexFields.push(XF.regexQuote(i.trim()))
							}
						})
						if (jsonOptInRegexFields.length)
						{
							jsonOptInRegex = new RegExp('^(' + jsonOptInRegexFields.join('|') + ')(\\[|$)')
						}
					}

					Array.from(elements).forEach(el =>
					{
						const name = el.name

						if (!name || name.substring(0, 3) === '_xf')
						{
							regularEls.push(el)
							return
						}

						if (!jsonOptInRegex || jsonOptInRegex.test(name))
						{
							jsonEls.push(el)
						}
						else
						{
							regularEls.push(el)
						}
					})

					formData = XF.Serializer.serializeArray(regularEls)
					inputs = XF.Serializer.serializeJSON(jsonEls)

					formData.unshift({
						name: jsonName,
						value: JSON.stringify(inputs),
					})
				}
				else
				{
					formData = XF.Serializer.serializeArray(form)
				}

				if (submitName)
				{
					formData.push({
						name: submitName,
						value: submitName,
					})
				}
			}

			return formData
		},

		scriptMatchRegex: /<script([^>]*)>([\s\S]*?)<\/script>/ig,

		/**
		 * Sets up the insertion of HTML content into a specified container.
		 *
		 * Note: The onReady callback will receive a single HTML Element named html.
		 * If the returned HTML from the Fetch API contains multiple top-level elements it will be wrapped in a <div class="js-createdContainer" /> element.
		 * This may change how you insert the returned HTML in your callback.
		 *
		 * @param {string|object} container - The container where the content will be inserted. If a string, it is assumed to be the HTML content to insert. If an object, it should contain a 'content' property with the HTML to insert.
		 * @param {HTMLElement|function} onReady - A function to call when the container is ready. If an HTMLElement, this element will be populated with the HTML content. The function takes three parameters: (element, container, onComplete).
		 * @param {boolean} [retainScripts=false] - If set to true, any scripts within the HTML content will be retained and executed. If false, scripts will be removed.
		 *
		 * @returns {void}
		 */
		setupHtmlInsert: (container, onReady, retainScripts) =>
		{
			if (typeof container === 'string')
			{
				container = { content: container }
			}

			if (typeof container != 'object' || !container.content)
			{
				console.error('Was not provided an object or HTML content')
				return
			}

			if (retainScripts && container.content && !container.js)
			{
				let content = container.content
				container.js = []

				let scriptUrlMatch
				while ((scriptUrlMatch = /<script[^>]*src="([^"]+)"[^>]*><\/script>/i.exec(content)))
				{
					container.js.push(scriptUrlMatch[1])
					content = content.substring(scriptUrlMatch.index + scriptUrlMatch[0].length)
				}
			}

			XF.Loader.load(container.js, container.css, () =>
			{
				let scriptRegexMatch
				const embeddedScripts = container.jsInline || []
				let html = container.content
				const isString = typeof html == 'string'

				if (container.cssInline)
				{
					for (const css of container.cssInline)
					{
						XF.createElement('style', {
							textContent: css
						}, document.head)
					}
				}

				if (isString)
				{
					let isJs, typeMatch

					html = html.trim()

					if (!retainScripts)
					{
						while ((scriptRegexMatch = XF.scriptMatchRegex.exec(html)))
						{
							isJs = false
							const typeMatch = scriptRegexMatch[1].match(/(^|\s)type=("|'|)([^"' ;]+)/)
							if (typeMatch)
							{
								switch (typeMatch[3].toLowerCase())
								{
									case 'text/javascript':
									case 'text/ecmascript':
									case 'application/javascript':
									case 'application/ecmascript':
										isJs = true
										break
								}
							}
							else
							{
								isJs = true
							}

							if (isJs)
							{
								embeddedScripts.push(scriptRegexMatch[2])
								html = html.replace(scriptRegexMatch[0], '')
							}
						}
					}
				}

				let element = XF.createElementFromString(html)

				if (!element)
				{
					return
				}

				// Remove <noscript> tags to ensure they never get parsed when not needed.
				const noscriptTags = element instanceof HTMLElement
					? element.querySelectorAll('noscript')
					: []
				noscriptTags.forEach(tag =>
				{
					tag.parentNode.removeChild(tag)
				})

				if (onReady instanceof HTMLElement)
				{
					const target = onReady
					onReady = h =>
					{
						target.innerHTML = ''
						target.append(h)
						XF.activate(target)
					}
				}
				if (typeof onReady !== 'function')
				{
					console.error('onReady was not a function')
					return
				}

				let onCompleteRun = false
				const onComplete = (skipActivate, newTarget) =>
				{
					if (onCompleteRun)
					{
						return
					}
					onCompleteRun = true

					for (const script of embeddedScripts)
					{
						eval(script)
					}

					if (container.jsState)
					{
						XF.config.jsState = XF.applyJsState(XF.config.jsState, container.jsState)
					}

					if (!skipActivate)
					{
						if (newTarget)
						{
							element = newTarget
						}

						if (element instanceof HTMLElement)
						{
							XF.activate(element)
						}
					}
				}

				const result = onReady(element, container, onComplete)
				if (result !== false)
				{
					onComplete()
				}
			})
		},

		alert: (message, messageType, title, onClose) =>
		{
			let messageHtml = message
			if (XF.isPlainObject(message))
			{
				messageHtml = '<ul>'
				Object.keys(message).forEach(k =>
				{
					messageHtml += '<li>' + message[k] + '</li>'
				})
				messageHtml += '</ul>'
				messageHtml = '<div class="blockMessage">' + messageHtml + '</div>'
			}

			if (!messageType)
			{
				messageType = 'error'
			}

			if (!title)
			{
				switch (messageType)
				{
					case 'error':
						title = XF.phrase('oops_we_ran_into_some_problems')
						break

					default:
						title = ''
				}
			}

			return XF.overlayMessage(title, messageHtml)
		},

		getOverlayHtml: content =>
		{
			let html
			let options = {
				dismissible: true,
				title: null,
				url: null,
			}

			if (typeof content === 'object' && content.constructor === Object)
			{
				options = {
					...options,
					...content
				}
				if (content.html)
				{
					content = content.html
				}
			}

			if (typeof content == 'string')
			{
				html = XF.createElementFromString(content)
			}
			else if (content instanceof HTMLElement)
			{
				html = content
			}
			else
			{
				throw new Error('Can only create an overlay with html provided as a string or DOM Element')
			}

			if (!html.classList.contains('overlay'))
			{
				let title = options.title
				if (!title)
				{
					const header = html.querySelector('.overlay-title')
					if (header)
					{
						title = header.textContent
						header.parentNode.removeChild(header)
					}
				}
				if (!title)
				{
					title = document.querySelector('title').textContent
				}

				const bodyInsert = html.querySelector('.overlay-content')
				if (bodyInsert)
				{
					html = bodyInsert
				}

				const overlay = XF.createElement('div', {
					className: 'overlay',
					tabIndex: '-1',
					dataset: { url: options.url }
				})

				const overlayTitle = XF.createElement('div', {
					className: 'overlay-title',
					innerHTML: title
				})

				if (options.dismissible)
				{
					const closer = XF.createElement('a', {
						className: 'overlay-titleCloser js-overlayClose',
						role: 'button',
						tabIndex: 0,
						ariaLabel: XF.phrase('close')
					})
					overlayTitle.prepend(closer)
				}

				const overlayContent = XF.createElement('div', {
					className: 'overlay-content'
				})
				if (XF.isCreatedContainer(html))
				{
					overlayContent.append(...html.childNodes)
				}
				else
				{
					overlayContent.append(html)
				}

				overlay.appendChild(overlayTitle)
				overlay.appendChild(overlayContent)

				html = overlay
			}

			document.body.appendChild(html)

			return html
		},

		createMultiBar: (url, callee, onSubmit, onCancel) =>
		{
		},

		getMultiBarHtml: content =>
		{
			let html
			let options = {
				dismissible: true,
				title: null,
			}

			if (typeof content === 'object' && content.constructor === Object)
			{
				options = XF.extendObject(options, content)
				if (content.html)
				{
					content = content.html
				}
			}

			if (typeof content == 'string')
			{
				html = XF.createElementFromString(content)
			}
			else if (content instanceof HTMLElement)
			{
				html = content
			}
			else
			{
				throw new Error('Can only create an action bar with html provided as a string or DOM Element')
			}

			const multiBar = XF.createElement('div', {
				className: 'multiBar',
				tabIndex: -1
			})

			const multiBarInner = XF.createElement('div', {
				className: 'multiBar-inner',
				innerHTML: '<span>Hello there.</span>'
			})
			multiBarInner.appendChild(html)

			multiBar.appendChild(multiBarInner)
			document.body.appendChild(multiBar)

			return multiBar
		},

		overlayMessage: (title, contentHtml) =>
		{
			let html
			const formattedSelector = '.block, .blockMessage'

			if (typeof contentHtml == 'string')
			{
				html = XF.createElementFromString(contentHtml)
				if (!(html instanceof HTMLElement))
				{
					const wrapper = XF.createElement('div', {
						innerText: html.textContent
					})
					html = wrapper
				}
			}
			else if (contentHtml instanceof HTMLElement)
			{
				html = contentHtml
			}
			else
			{
				throw new Error('Can only create an overlay with html provided as a string or DOM Element')
			}

			if (!html.matches(formattedSelector) && !html.querySelector(formattedSelector))
			{
				const blockMessage = XF.createElement('div', {
					className: 'blockMessage'
				})
				blockMessage.appendChild(html)
				html = blockMessage
			}

			html = XF.getOverlayHtml({
				title,
				html,
			})

			return XF.showOverlay(html, { role: 'alertdialog' })
		},

		flashMessage: (message, timeout, onClose) =>
		{
			const messageContent = XF.createElementFromString('<div class="flashMessage-content">' + message + '</div>')
			const flashMessageElement = XF.createElementFromString('<div class="flashMessage"></div>')
			flashMessageElement.appendChild(messageContent)
			document.body.appendChild(flashMessageElement)

			XF.Transition.addClassTransitioned(flashMessageElement, 'is-active')

			setTimeout(() =>
			{
				XF.Transition.removeClassTransitioned(flashMessageElement, 'is-active', () =>
				{
					flashMessageElement.parentNode.removeChild(flashMessageElement)
					if (onClose)
					{
						onClose()
					}
				})
			}, Math.max(500, timeout))
		},

		htmlspecialchars: string =>
		{
			return String(string)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
		},

		regexQuote: string =>
		{
			return (String(string)).replace(/([\\.+*?[^\]$(){}=!<>|:])/g, '\\$1')
		},

		stringTranslate: (string, pairs) =>
		{
			string = string.toString()
			for (const key of Object.keys(pairs))
			{
				const regex = new RegExp(XF.regexQuote(key, 'g'))
				string = string.replace(regex, pairs[key])
			}
			return string
		},

		stringHashCode: str =>
		{
			// adapted from http://stackoverflow.com/a/7616484/1480610
			let hash = 0, i, chr, len

			if (str.length === 0)
			{
				return hash
			}

			for (i = 0, len = str.length; i < len; i++)
			{
				chr = str.charCodeAt(i)
				hash = ((hash << 5) - hash) + chr
				hash |= 0
			}

			return hash
		},

		getUniqueCounter: () =>
		{
			const counter = XF.counter
			XF.counter++

			return counter
		},

		canonicalizeUrl: url =>
		{
			if (url.match(/^[a-z]+:/i))
			{
				return url
			}

			if (url.indexOf('/') === 0)
			{
				const fullPath = XF.config.url.fullBase
				const match = fullPath.match(/^([a-z]+:(\/\/)?[^/]+)\//i)
				if (match)
				{
					return match[1] + url
				}

				return url
			}

			return XF.config.url.fullBase + url
		},

		isRedirecting: false,

		redirect: url =>
		{
			XF.isRedirecting = true

			if (XF.JobRunner.isBlockingJobRunning())
			{
				XF.on(document, 'job:blocking-complete', () => XF.redirect(url), { once: true })
				return false
			}

			url = XF.canonicalizeUrl(url)

			const location = window.location

			if (url === location.href)
			{
				window.location.reload(true)
			}
			else
			{
				window.location = url

				const destParts = url.split('#')
				const srcParts = location.href.split('#')

				// on the same page except we changed the hash, because we're asking for a redirect,
				// we should explicitly reload
				if (destParts[1] && destParts[0] === srcParts[0])
				{
					window.location.reload(true)
				}
			}

			return true
		},

		getAutoCompleteUrl: () =>
		{
			if (XF.getApp() === 'admin')
			{
				return XF.canonicalizeUrl('admin.php?users/find')
			}
			else
			{
				return XF.canonicalizeUrl('index.php?members/find')
			}
		},

		applyDataOptions: (options, data, finalTrusted) =>
		{
			let output = {}, v, vType, setValue

			for (const i of Object.keys(options))
			{
				output[i] = options[i]

				if (XF.hasOwn(data, i))
				{
					v = data[i]
					vType = typeof v
					setValue = true

					switch (typeof output[i])
					{
						case 'object':
							if (vType === 'string')
							{
								// is this JSON? try to parse to an object
								try
								{
									let parsed = JSON.parse(v)
									v = parsed
								}
								catch (error)
								{
									// ignore
								}
							}
							break

						case 'string':
							if (vType !== 'string')
							{
								v = String(v)
							}
							break

						case 'number':
							if (vType !== 'number')
							{
								v = Number(v)
								if (isNaN(v))
								{
									setValue = false
								}
							}
							break

						case 'boolean':
							if (vType !== 'boolean')
							{
								v = XF.toBoolean(v)
							}
					}

					if (setValue)
					{
						output[i] = v
					}
				}
			}

			if (XF.isPlainObject(finalTrusted))
			{
				output = XF.extendObject(output, finalTrusted)
			}

			return output
		},

		watchInputChangeDelayed: (input, onChange, delay = 200) =>
		{
			let value = input.value
			let timeOut

			XF.on(input, 'input', (e) =>
			{
				clearTimeout(timeOut)
				timeOut = setTimeout(() =>
				{
					if (value !== input.value)
					{
						value = input.value
						onChange(e)
					}
				}, delay)
			})
		},

		insertIntoEditor: (container, html, text, notConstraints) =>
		{
			const htmlCallback = editor =>
			{
				editor.insertContent(html)
			}

			const textCallback = textarea =>
			{
				XF.insertIntoTextBox(textarea, text)
			}

			return XF.modifyEditorContent(container, htmlCallback, textCallback, notConstraints)
		},

		replaceEditorContent: (container, html, text, notConstraints) =>
		{
			const htmlCallback = (editor) =>
			{
				editor.replaceContent(html)
			}

			const textCallback = (textarea) =>
			{
				XF.replaceIntoTextBox(textarea, text)
			}

			return XF.modifyEditorContent(container, htmlCallback, textCallback, notConstraints)
		},

		clearEditorContent: (container, notConstraints) =>
		{
			const ret = XF.replaceEditorContent(container, '', '', notConstraints)

			XF.trigger(container, 'draft:sync')

			return ret
		},

		modifyEditorContent: (container, htmlCallback, textCallback, notConstraints) =>
		{
			const editor = XF.getEditorInContainer(container, notConstraints)
			if (!editor)
			{
				return false
			}

			if (editor.ed)
			{
				if (editor.isBbCodeView())
				{
					const textarea = editor.ed.bbCode.getTextArea()
					textCallback(textarea)
					XF.trigger(textarea, 'autosize')
				}
				else
				{
					htmlCallback(editor)
				}
				return true
			}

			if (editor instanceof HTMLElement && editor.nodeName === 'TEXTAREA')
			{
				textCallback(editor)
				XF.trigger(editor, 'autosize')
				return true
			}

			return false
		},

		getEditorInContainer: (container, notConstraints) =>
		{
			let editor

			if (container.classList.contains('js-editor'))
			{
				if (notConstraints && container.matches(notConstraints))
				{
					return null
				}

				editor = container
			}
			else
			{
				let editors = Array.from(container.querySelectorAll('.js-editor'))
				if (notConstraints)
				{
					editors = editors.filter(editor => !editor.matches(notConstraints))
				}

				if (!editors.length)
				{
					return null
				}

				editor = editors[0]
			}

			const handler = XF.Element.getHandler(editor, 'editor')
			if (handler)
			{
				return handler
			}

			if (editor.nodeName === 'TEXTAREA')
			{
				return editor
			}

			return null
		},

		focusEditor: (container, notConstraints) =>
		{
			const editor = XF.getEditorInContainer(container, notConstraints)
			if (!editor)
			{
				return false
			}

			// This part is assuming that XF.Editor is some class and editor is an instance of it.
			// The exact behavior might vary depending on the actual implementation of XF.Editor and the isInitialized method.
			if (XF.Editor && editor instanceof XF.Editor)
			{
				if (editor.isInitialized())
				{
					editor.scrollToCursor()
				}
				return true
			}

			if (editor instanceof HTMLElement && editor.tagName === 'TEXTAREA')
			{
				XF.autofocus(editor)
				return true
			}

			return false
		},

		insertIntoTextBox: (textBox, insert) =>
		{
			const scrollPos = textBox.scrollTop, startPos = textBox.selectionStart, endPos = textBox.selectionEnd,
				value = textBox.value, before = value.substring(0, startPos),
				after = value.substring(endPos, value.length)

			textBox.value = before + insert + after
			XF.trigger(textBox, 'autosize')
			textBox.selectionStart = startPos + insert.length
			textBox.selectionEnd = textBox.selectionStart
			textBox.scrollTop = scrollPos
			XF.autofocus(textBox)
		},

		replaceIntoTextBox: (textBox, insert) =>
		{
			textBox.value = insert
			XF.trigger(textBox, 'autosize')
		},

		isElementWithinDraftForm: (el) =>
		{
			const form = el.closest('form')
			return form !== null && form.hasAttribute('data-xf-init') && form.getAttribute('data-xf-init').includes('draft')
		},

		logRecentEmojiUsage: (shortname) =>
		{
			if (!XF.Cookie.isGroupConsented('optional'))
			{
				return []
			}

			shortname = shortname.trim()

			const limit = XF.Feature.has('hiddenscroll') ? 12 : 11 // bit arbitrary but basically a single row on full width displays
			const value = XF.Cookie.get('emoji_usage')
			let recent = value ? value.split(',') : []
			const exist = recent.indexOf(shortname)

			if (exist !== -1)
			{
				recent.splice(exist, 1)
			}

			recent.push(shortname)

			if (recent.length > limit)
			{
				recent = recent.reverse().slice(0, limit).reverse()
			}

			XF.Cookie.set('emoji_usage', recent.join(','), new Date(new Date().setFullYear(new Date().getFullYear() + 1)))

			XF.trigger(document, 'recent-emoji:logged')
		},

		getRecentEmojiUsage: () =>
		{
			const value = XF.Cookie.get('emoji_usage'), recent = value ? value.split(',') : []

			return recent.reverse()
		},

		getFixedOffsetParent: (el) =>
		{
			while (el && el.nodeType === 1)
			{
				const computedStyle = getComputedStyle(el)
				if (computedStyle.position === 'fixed')
				{
					return el
				}

				el = el.parentNode
			}

			return document.documentElement
		},

		getFixedOffset: (el) =>
		{
			const rect = el.getBoundingClientRect()
			const offsetParent = XF.getFixedOffsetParent(el)
			const parentRect = offsetParent.getBoundingClientRect()

			if (el.nodeName.toLowerCase() === 'html')
			{
				return {
					top: rect.top,
					left: rect.left,
				}
			}

			return {
				top: rect.top - parentRect.top,
				left: rect.left - parentRect.left,
			}
		},

		autoFocusWithin: (container, autoFocusSelector, fallback) =>
		{
			let focusEl = container.querySelector(autoFocusSelector || '[autofocus]')

			if (!focusEl)
			{
				if (!focusEl && XF.NavDeviceWatcher.isKeyboardNav())
				{
					const focusableEls = container.querySelectorAll('a, button, input, textarea, select, [tabindex]')
					for (const el of focusableEls)
					{
						if (
							el.offsetWidth &&
							el.offsetHeight &&
							!el.disabled &&
							!el.hasAttribute('data-no-auto-focus')
						)
						{
							focusEl = el
							break
						}
					}
				}
				if (!focusEl)
				{
					const form = container.querySelector('form:not([data-no-auto-focus])')
					if (form)
					{
						const formEls = form.querySelectorAll('input, textarea, select, button, .tagify__input')
						for (const el of formEls)
						{
							if (
								el.offsetWidth &&
								el.offsetHeight &&
								!el.disabled &&
								!el.classList.contains('select2-hidden-accessible')
							)
							{
								focusEl = el
								break
							}
						}
					}
				}
				if (!focusEl && fallback)
				{
					focusEl = fallback
				}
				if (!focusEl)
				{
					container.setAttribute('tabindex', '-1')
					focusEl = container
				}
			}

			// focusing will trigger a scroll, so we want to prevent that. We need to maintain all scroll
			// values and restore them after focusing.
			const scrolls = []
			let parent = focusEl.parentNode

			do
			{
				scrolls.push({
					el: parent,
					left: parent.scrollLeft,
					top: parent.scrollTop,
				})
			}
			while ((parent = parent.parentNode))

			XF.autofocus(focusEl)

			for (const scroll of scrolls)
			{
				const el = scroll.el

				if (el.scrollLeft !== scroll.left)
				{
					el.scrollLeft = scroll.left
				}
				if (el.scrollTop !== scroll.top)
				{
					el.scrollTop = scroll.top
				}
			}
		},

		display: (el, display = null) =>
		{
			if (display)
			{
				el.style.display = display
			}
			else
			{
				el.style.removeProperty('display')

				if (display === null) // this is intended to restore the default display, so long as it's not 'none'
				{
					if (!el.offsetWidth)
					{
						const computed = window.getComputedStyle(el)

						if (computed.display === 'none')
						{
							el.style.display = computed.getPropertyValue('--js-display')
						}
					}
				}
			}
		},

		bottomFix: el =>
		{
			const fixer = document.querySelector('.js-bottomFixTarget')
			if (fixer)
			{
				fixer.append(el)
			}
			else
			{
				el.style.position = 'fixed'
				el.style.bottom = '0'
				document.body.append(el)
			}
		},

		addFixedMessage: (el, extraAttrs) =>
		{
			let message, closeButton, innerMessage

			message = XF.createElement('div', {
				className: 'fixedMessageBar'
			})

			const innerDiv = XF.createElement('div', {
				className: 'fixedMessageBar-inner'
			}, message)

			innerMessage = XF.createElement('div', {
				className: 'fixedMessageBar-message',
				innerHTML: el
			}, innerDiv)

			closeButton = XF.createElement('a', {
				className: 'fixedMessageBar-close',
				role: 'button',
				tabIndex: 0,
				ariaLabel: XF.phrase('close'),
				dataset: { close: true }
			}, innerDiv)

			if (extraAttrs)
			{
				if (extraAttrs.class)
				{
					message.classList.add(...extraAttrs.class.split(' '))
					delete extraAttrs.class
				}
				for (const key of Object.keys(extraAttrs))
				{
					message.setAttribute(key, extraAttrs[key])
				}
			}

			XF.on(closeButton, 'click', () =>
			{
				message.classList.remove('is-active')
				XF.on(message, 'transitionend', () =>
				{
					message.parentNode.removeChild(message)
				})
			})

			XF.bottomFix(message)
			message.classList.add('is-active')
		},

		_measureScrollBar: null,

		measureScrollBar: (container, type) =>
		{
			type = (type === 'height' || type === 'h') ? 'h' : 'w'

			if (container || XF._measureScrollBar === null)
			{
				const measure = XF.createElement('div', {
					className: 'scrollMeasure'
				}, (container || document.body))

				const width = measure.offsetWidth - measure.clientWidth,
					height = measure.offsetHeight - measure.clientHeight, value = {
						w: width,
						h: height,
					}

				measure.parentNode.removeChild(measure)

				if (!container)
				{
					XF._measureScrollBar = value
				}

				return value[type]
			}
			else
			{
				return XF._measureScrollBar[type]
			}
		},

		windowHeight: () =>
		{
			if (XF.browser.ios || XF.browser.android)
			{
				// return the effective height, without any browser UI
				return window.innerHeight
			}
			else
			{
				return document.documentElement.clientHeight
			}
		},

		pageLoadScrollFix: () =>
		{
			// these browsers support native scroll anchoring
			if (XF.Feature.has('overflowanchor'))
			{
				return
			}

			if (!window.location.hash)
			{
				return
			}

			let isScrolled = false
			const onLoad = () =>
			{
				if (isScrolled)
				{
					return
				}

				const hash = window.location.hash.replace(/[^a-zA-Z0-9_-]/g, ''),
					match = hash ? document.getElementById(hash) : null

				if (match)
				{
					match.scrollIntoView(true)
				}
			}

			if (document.readyState === 'complete')
			{
				// load has already fired
				setTimeout(onLoad, 0)
			}
			else
			{
				setTimeout(() =>
				{
					XF.on(window, 'scroll', e =>
					{
						isScrolled = true
					}, { once: true })
				}, 100)

				XF.on(window, 'load', onLoad, { once: true })
			}
		},

		applyJsState: (currentState, additionalState) =>
		{
			currentState = currentState || {}

			if (!additionalState)
			{
				return currentState
			}

			for (const state of Object.keys(additionalState))
			{
				if (!currentState[state])
				{
					if (XF.hasOwn(XF.jsStates, state))
					{
						if (XF.jsStates[state]())
						{
							currentState[state] = true
						}
					}
				}
			}

			return currentState
		},

		jsStates: {
			facebook: () =>
			{
				return this.fbSdk()
			},

			fbSdk: () =>
			{
				const fbRoot = XF.createElement('div', {
					id: 'fb-root'
				}, document.body)

				window.fbAsyncInit = () =>
				{
					FB.init({
						version: 'v2.7',
						xfbml: true,
					})
				}

				XF.loadScript('https://connect.facebook.net/' + XF.getLocale() + '/sdk.js')

				return true
			},

			twitter: () =>
			{
				window.twttr = ((() =>
				{
					const t = window.twttr || {}

					XF.loadScript('https://platform.twitter.com/widgets.js', () =>
					{
						t._e = []
						t.ready = function(f)
						{
							t._e.push(f)
						}

						XF.on(document, 'embed:loaded', () =>
						{
							const tweets = document.querySelectorAll('blockquote.twitter-tweet')
							tweets.forEach(tweet => t.widgets.load(tweet))
						})
					})

					return t
				})())

				return true
			},

			flickr: () =>
			{
				XF.loadScript('https://embedr.flickr.com/assets/client-code.js')
				return true
			},

			instagram: () =>
			{
				XF.loadScript('https://www.instagram.com/embed.js')
				return true
			},

			tiktok: () =>
			{
				XF.loadScript('https://www.tiktok.com/embed.js', () =>
				{
					XF.on(document, 'embed:loaded', e =>
					{
						const tiktoksOnPage = Array.from(
							document.querySelectorAll('blockquote.tiktok-embed'),
						);
						if (typeof tiktokEmbed !== 'undefined' && tiktoksOnPage.length)
						{
							tiktokEmbed.lib.render(tiktoksOnPage);
						}
					})
				})
				return true
			},

			reddit: () =>
			{
				XF.loadScript('https://embed.reddit.com/widgets.js', () =>
				{
					XF.on(document, 'xf:reinit', e =>
					{
						if (window.rembeddit)
						{
							rembeddit.init()
						}
					})
				})

				return true
			},

			reddit_comment ()
			{
				return this.reddit()
			},

			imgur: () =>
			{
				const selector = 'blockquote.imgur-embed-pub'

				if (!window.imgurEmbed)
				{
					window.imgurEmbed = { tasks: document.querySelectorAll(selector).length }
				}

				XF.loadScript('//s.imgur.com/min/embed-controller.js', () =>
				{
					XF.on(document, 'xf:reinit', e =>
					{
						imgurEmbed.tasks += document.querySelectorAll(selector).length

						for (let i = 0; i < imgurEmbed.tasks; i++)
						{
							imgurEmbed.createIframe()
							imgurEmbed.tasks--
						}
					})
				})

				return true
			},

			pinterest: () =>
			{
				XF.loadScript('//assets.pinterest.com/js/pinit.js', () =>
				{
					XF.on(document, 'xf:reinit', e =>
					{
						PinUtils.build(e.target)
					})
				})

				return true
			},

			bluesky: () =>
			{
				XF.loadScript('https://embed.bsky.app/static/embed.js', () =>
				{
					XF.on(document, 'embed:loaded', () =>
					{
						window?.bluesky?.scan()
					})
				})

				return true
			},
		},

		getLocale: () =>
		{
			let locale = document.querySelector('html').getAttribute('lang').replace('-', '_')
			if (!locale)
			{
				locale = 'en_US'
			}

			return locale
		},

		supportsPointerEvents: () => ('PointerEvent' in window),

		isEventTouchTriggered (e)
		{
			if (e)
			{
				if (e.xfPointerType)
				{
					// this isn't normally exposed to click events, so we have a system to expose this without having
					// to manually implement full click emulation
					return (e.xfPointerType === 'touch')
				}

				const oe = e.originalEvent

				if (oe)
				{
					if (this.supportsPointerEvents() && oe instanceof PointerEvent)
					{
						return oe.pointerType === 'touch'
					}

					if (oe.sourceCapabilities)
					{
						return oe.sourceCapabilities.firesTouchEvents
					}
				}
			}

			return XF.Feature.has('touchevents')
		},

		getElEffectiveZIndex: reference =>
		{
			let maxZIndex = parseInt(window.getComputedStyle(reference).getPropertyValue('z-index'), 10) || 0

			while (reference.parentElement)
			{
				reference = reference.parentElement
				const zIndex = parseInt(window.getComputedStyle(reference).getPropertyValue('z-index'), 10)
				if (zIndex > maxZIndex)
				{
					maxZIndex = zIndex
				}
			}

			return maxZIndex
		},

		setRelativeZIndex (target, reference, offsetAmount, minZIndex)
		{
			if (!minZIndex)
			{
				minZIndex = 6 // make sure we go over the default editor stuff
			}

			let maxZIndex = this.getElEffectiveZIndex(reference)
			if (minZIndex && minZIndex > maxZIndex)
			{
				maxZIndex = minZIndex
			}

			if (offsetAmount === null || typeof offsetAmount === 'undefined')
			{
				offsetAmount = 0
			}

			const targets = Array.isArray(target) ? target : [target]
			const len = targets.length

			for (let i = 0; i < len; i++)
			{
				const _target = targets[i]

				if (maxZIndex || offsetAmount)
				{
					const dataKey = 'baseZIndex'
					if (!XF.DataStore.get(_target, dataKey))
					{
						XF.DataStore.set(_target, dataKey, parseInt(window.getComputedStyle(_target).getPropertyValue('z-index'), 10) || 0)
					}
					_target.style.zIndex = XF.DataStore.get(_target, dataKey) + offsetAmount + maxZIndex
				}
				else
				{
					_target.style.zIndex = ''
				}
			}
		},

		adjustHtmlForRte: content =>
		{
			content = content.replace(/<img[^>]+>/ig, match =>
			{
				if (match.match(/class="([^"]* )?smilie([ "])/))
				{
					const altMatch = match.match(/alt="([^"]+)"/)
					if (altMatch)
					{
						return altMatch[1]
					}
				}

				return match
			})

			content = content.replace(/([\w\W]|^)<a\s[^>]*data-user-id="\d+"\s+data-username="([^"]+)"[^>]*>([\w\W]+?)<\/a>/gi, (match, prefix, user, username) => prefix + (prefix === '@' ? '' : '@') + username.replace(/^@/, ''))

			content = content.replace(/(<img\s[^>]*)src="[^"]*"(\s[^>]*)data-url="([^"]+)"/gi, (match, prefix, suffix, source) => prefix + 'src="' + source + '"' + suffix)

			const tempElement = XF.createElement('div', {
				innerHTML: content
			})

			const blockquotes = tempElement.querySelectorAll('blockquote')
			blockquotes.forEach(quote =>
			{
				['attributes', 'quote', 'source'].forEach(attr =>
				{
					if (!quote.getAttribute('data-' + attr))
					{
						quote.removeAttribute('data-' + attr)
					}
				})

				const title = quote.querySelector('.bbCodeBlock-title')
				if (title)
				{
					quote.removeChild(title)
				}
			})

			content = tempElement.innerHTML

			return content
		},

		requestAnimationTimeout: (fn, delay = 0) =>
		{
			const rafFn = (cb) => window.setTimeout(cb, 1000 / 60)
			const raf = window.requestAnimationFrame || rafFn
			const start = Date.now()
			const data = {}

			const loop = () =>
			{
				if (Date.now() - start >= delay)
				{
					fn()
				}
				else
				{
					data.id = raf(loop)
				}
			}

			data.id = raf(loop)
			data.cancel = () =>
			{
				const caf = window.cancelAnimationFrame || window.clearTimeout
				caf(data.id)
			}

			return data
		},

		/**
		 * Returns a function replacing the default this object with the supplied context.
		 *
		 * @param fn
		 * @param context
		 * @param args
		 * @returns {undefined|Function}
		 */
		proxy: (fn, context, ...args) =>
		{
			let boundFn

			if (typeof context === 'string')
			{
				boundFn = fn[context].bind(fn)
			}
			else if (XF.isFunction(fn))
			{
				boundFn = fn.bind(context)
			}
			else
			{
				return undefined
			}

			return (...innerArgs) => boundFn(...args, ...innerArgs)
		},

		_localLoadTime: null,

		getLocalLoadTime: () =>
		{
			if (XF._localLoadTime)
			{
				return XF._localLoadTime
			}

			const loadCacheEl = document.querySelector('#_xfClientLoadTime')

			const loadCache = loadCacheEl.value.split(',')
			if (loadCache.length === 2 && parseInt(loadCache[1]) === XF.config.time.now)
			{
				XF._localLoadTime = parseInt(loadCache[0])

				return XF._localLoadTime
			}

			const navigationTiming = window.performance.getEntriesByType('navigation')[0]
			if (!navigationTiming)
			{
				XF._localLoadTime = Math.floor(new Date().getTime() / 1000) - 1
				loadCacheEl.value = XF._localLoadTime + ',' + XF.config.time.now

				return XF._localLoadTime
			}

			// avg between request and response is likely close to server start
			const loadTime = (navigationTiming.requestStart + navigationTiming.responseStart) / 2
			XF._localLoadTime = Math.floor((window.performance.timeOrigin + loadTime) / 1000)
			loadCacheEl.value = XF._localLoadTime + ',' + XF.config.time.now

			return XF._localLoadTime
		},

		getFutureDate: (amount, unit) =>
		{
			let length = 86400 * 1000 // a day

			switch (unit)
			{
				case 'year':
					length *= 365 // a year
					break

				case 'month':
					length *= 30 // a month
					break
			}

			length *= amount

			return new Date(Date.now() + length)
		},

		smoothScroll: (scrollTo, hash, speed, onlyIfNeeded) =>
		{
			let content
			let top

			if (scrollTo instanceof HTMLElement || typeof scrollTo === 'string')
			{
				content = (scrollTo instanceof HTMLElement) ? scrollTo : document.querySelector(scrollTo)

				if (content)
				{
					top = content.getBoundingClientRect().top + window.scrollY

					const scrollPadding = parseInt(getComputedStyle(document.documentElement).getPropertyValue('scroll-padding-top'), 10)
					if (!isNaN(scrollPadding))
					{
						top -= scrollPadding
					}
				}
				else
				{
					top = null
				}

				if (hash === true)
				{
					hash = content ? '#' + content.id : null
				}
			}
			else if (typeof scrollTo === 'number')
			{
				content = null
				top = scrollTo
			}

			if (top === null)
			{
				console.error('Invalid scroll position')
				return
			}

			if (top < 0)
			{
				top = 0
			}

			const pushHash = () =>
			{
				if (hash && 'pushState' in window.history)
				{
					window.history.pushState({}, '', window.location.toString().replace(/#.*$/, '') + hash)
				}
			}

			if (onlyIfNeeded)
			{
				const docEl = document.documentElement
				const windowTop = docEl.scrollTop
				const windowBottom = windowTop + docEl.clientHeight

				if (top >= windowTop && top <= windowBottom)
				{
					// already in the window, don't need to scroll
					pushHash()
					return
				}
			}

			try
			{
				pushHash()

				window.scrollTo({
					top,
					behavior: 'smooth',
				})
			}
			catch (e)
			{
				if (hash)
				{
					window.location.hash = hash
				}
			}
		},
	})

	XF.create = props =>
	{
		const fn = function (...args)
		{
			this.__construct(...args)
		}

		fn.prototype = Object.create(props)

		if (!fn.prototype.__construct)
		{
			fn.prototype.__construct = () =>
			{
			}
		}
		fn.prototype.constructor = fn

		return fn
	}

	XF.extend = (parent, extension) =>
	{
		const fn = function (...args)
		{
			this.__construct(...args)
		}
		let i

		fn.prototype = Object.create(parent.prototype)

		if (!fn.prototype.__construct)
		{
			fn.prototype.__construct = () =>
			{
			}
		}
		fn.prototype.constructor = fn

		if (typeof extension == 'object')
		{
			if (typeof extension.__backup == 'object')
			{
				const backup = extension.__backup
				for (i of Object.keys(backup))
				{
					if (fn.prototype[backup[i]])
					{
						throw new Error('Method ' + backup[i] + ' already exists on object. Aliases must be unique.')
					}
					fn.prototype[backup[i]] = fn.prototype[i]
				}

				delete extension.__backup
			}

			for (i of Object.keys(extension))
			{
				fn.prototype[i] = extension[i]
			}
		}

		return fn
	}

	XF.classToConstructor = className =>
	{
		let obj = window
		const parts = className.split('.')
		let i

		for (i = 0; i < parts.length; i++)
		{
			obj = obj[parts[i]]
		}

		if (typeof obj != 'function')
		{
			console.error('%s is not a function.', className)
			return false
		}

		return obj
	}

	XF.Cookie = {
		get (name)
		{
			const expr = new RegExp('(^| )' + XF.config.cookie.prefix + name + '=([^;]+)(;|$)')
			const cookie = expr.exec(document.cookie)

			if (cookie)
			{
				return decodeURIComponent(cookie[2])
			}
			else
			{
				return null
			}
		},

		getEncodedCookieValue (name, value, expires, samesite)
		{
			const c = XF.config.cookie

			return c.prefix + name + '=' + encodeURIComponent(value) + (expires === undefined ? '' : ';expires=' + expires.toUTCString()) + (c.path ? ';path=' + c.path : '') + (c.domain ? ';domain=' + c.domain : '') + (samesite ? ';samesite=' + samesite : '') + (c.secure ? ';secure' : '')
		},

		getEncodedCookieValueSize (name, value, expires, samesite)
		{
			return this.getEncodedCookieValue(name, value, expires, samesite).length
		},

		set (name, value, expires, samesite)
		{
			document.cookie = this.getEncodedCookieValue(name, value, expires, samesite)
		},

		getJson (name)
		{
			const data = this.get(name)
			if (!data)
			{
				return {}
			}

			try
			{
				return JSON.parse(data) || {}
			}
			catch (e)
			{
				return {}
			}
		},

		setJson (name, value, expires)
		{
			this.set(name, JSON.stringify(value), expires)
		},

		remove (name)
		{
			const c = XF.config.cookie

			document.cookie = c.prefix + name + '=' + (c.path ? '; path=' + c.path : '') + (c.domain ? '; domain=' + c.domain : '') + (c.secure ? '; secure' : '') + '; expires=Thu, 01-Jan-70 00:00:01 GMT'
		},

		removeMultiple (items)
		{
			for (const item of items)
			{
				this.remove(item)
			}
		},

		supportsExpiryDate ()
		{
			return true
		},

		getConsentMode ()
		{
			return XF.config.cookie.consentMode
		},

		isGroupConsented (group)
		{
			const cookieConfig = XF.config.cookie

			if (group === '_required')
			{
				return true
			}

			if (group === '_unknown')
			{
				return true
			}

			return cookieConfig.consented.includes(group)
		},

		isThirdPartyConsented (thirdParty)
		{
			return this.isGroupConsented('_third_party')
		},
	}

	XF.LocalStorage = {
		getKeyName (name)
		{
			return XF.config.cookie.prefix + name
		},

		get (name)
		{
			let value = null

			try
			{
				value = window.localStorage.getItem(this.getKeyName(name))
			}
			catch (e)
			{
				// ignore
			}

			if (value === null)
			{
				const localStorage = this.getFallbackValue()
				if (localStorage && XF.hasOwn(localStorage, name))
				{
					value = localStorage[name]
				}
			}

			return value
		},

		getJson (name)
		{
			const data = this.get(name)
			if (!data)
			{
				return {}
			}

			try
			{
				return JSON.parse(data) || {}
			}
			catch (e)
			{
				return {}
			}
		},

		set (name, value, allowFallback)
		{
			try
			{
				window.localStorage.setItem(this.getKeyName(name), value)
			}
			catch (e)
			{
				if (allowFallback)
				{
					const localStorage = this.getFallbackValue()
					localStorage[name] = value
					this.updateFallbackValue(localStorage)
				}
			}
		},

		setJson (name, value, allowFallback)
		{
			this.set(name, JSON.stringify(value), allowFallback)
		},

		remove (name)
		{
			try
			{
				window.localStorage.removeItem(this.getKeyName(name))
			}
			catch (e)
			{
				// ignore
			}

			const localStorage = this.getFallbackValue()
			if (localStorage && XF.hasOwn(localStorage, name))
			{
				delete localStorage[name]
				this.updateFallbackValue(localStorage)
			}
		},

		removeMultiple (items)
		{
			for (const item of items)
			{
				this.remove(item)
			}
		},

		getFallbackValue ()
		{
			let value = XF.Cookie.get('ls')
			if (value)
			{
				try
				{
					value = JSON.parse(value)
				}
				catch (e)
				{
					value = {}
				}
			}

			return value || {}
		},

		updateFallbackValue (newValue)
		{
			if (XF.isEmptyObject(newValue))
			{
				XF.Cookie.remove('ls')
			}
			else
			{
				XF.Cookie.set('ls', JSON.stringify(newValue))
			}
		},

		supportsExpiryDate ()
		{
			return false
		},
	}

	XF.Animate = (() =>
	{
		let enabled = true

		const enable = () =>
		{
			enabled = true
		}

		const disable = () =>
		{
			enabled = false
		}

		const easing = {
			linear: progress => progress,
			quadratic: progress => progress ** 2,
			swing: progress => 0.5 - Math.cos(progress * Math.PI) / 2,
			circ: progress => 1 - Math.sin(Math.acos(progress)),
			back: (progress, x) => (progress ** 2) * ((x + 1) * progress - x),
			bounce: progress =>
			{
				let a = 0
				let b = 1

				// eslint-disable-next-line no-constant-condition
				for (; 1; a += b, b /= 2)
				{
					if (progress >= (7 - 4 * a) / 11)
					{
						return -((11 - 6 * a - 11 * progress) / 4 ** 2) + (b ** 2)
					}
				}
			},
			elastic: (progress, x) => ((10 * (progress - 1)) ** 2) * Math.cos(20 * Math.PI * x / 3 * progress),
		}

		const animate = (element, options) =>
		{
			options = {
				speed: XF.config.speed.normal,
				delta: easing.swing,
				complete: () => {},
				start: () => {},
				finish: () => {},
				...options,
			}

			if (!enabled)
			{
				options.speed = 0
			}

			let data = options.start(element)
			if (data === false)
			{
				options.complete()
				return
			}
			if (data === undefined)
			{
				data = {}
			}

			const start = new Date()
			const animationFrame = () =>
			{
				const elapsed = new Date() - start
				const progress = options.speed > 0
					? Math.min(1, elapsed / options.speed)
					: 1
				const delta = options.delta(progress)

				data = {
					...data,
					...options.step(element, { ...data, delta })
				}

				if (progress === 1)
				{
					options.finish(element, data)
					setTimeout(options.complete, 0)
					return
				}

				XF.requestAnimationTimeout(animationFrame)
			}

			return XF.requestAnimationTimeout(animationFrame)
		}

		const fadeOut = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display === 'none')
				{
					return false
				}
			},
			step: (el, { delta }) =>
			{
				el.style.opacity = (1 - delta).toString()
			},
			finish: (el) =>
			{
				XF.display(el, 'none')
				el.style.opacity = ''
			},
		})

		const fadeIn = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display !== 'none')
				{
					return false
				}

				XF.display(el)
			},
			step: (el, { delta }) =>
			{
				el.style.opacity = delta.toString()
			},
			finish: (el) =>
			{
				el.style.opacity = ''
			},
		})

		const fadeTo = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				const initialOpacity = Number(window.getComputedStyle(el).opacity)
				const targetOpacity = options.opacity ?? 1
				if (initialOpacity === targetOpacity)
				{
					return false
				}

				const deltaOpacity = targetOpacity - initialOpacity
				XF.display(el)

				return { initialOpacity, deltaOpacity }
			},
			step: (el, { delta, initialOpacity, deltaOpacity }) =>
			{
				el.style.opacity = (initialOpacity + deltaOpacity * delta).toString()
			},
		})

		const slideUp = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display === 'none')
				{
					return false
				}

				const initialHeight = el.offsetHeight
				el.style.overflow = 'hidden'

				return { initialHeight }
			},
			step: (el, { delta, initialHeight }) =>
			{
				el.style.height = (initialHeight * (1 - delta)) + 'px'
			},
			finish: (el) =>
			{
				XF.display(el, 'none')
				el.style.height = ''
				el.style.overflow = ''
			},
		})

		const slideDown = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display !== 'none')
				{
					return false
				}

				XF.display(el)
				const targetHeight = el.offsetHeight
				el.style.overflow = 'hidden'

				return { targetHeight }
			},
			step: (el, { delta, targetHeight }) =>
			{
				el.style.height = (targetHeight * delta) + 'px'
			},
			finish: (el) =>
			{
				el.style.height = ''
				el.style.overflow = ''
			},
		})

		const fadeUp = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display === 'none')
				{
					return false
				}

				const initialHeight = el.offsetHeight
				el.style.overflow = 'hidden'

				return { initialHeight }
			},
			step: (el, { delta, initialHeight }) =>
			{
				el.style.height = (initialHeight * (1 - delta)) + 'px'
				el.style.opacity = (1 - delta).toString()
			},
			finish: (el) =>
			{
				XF.display(el, 'none')
				el.style.height = ''
				el.style.opacity = ''
				el.style.overflow = ''
			},
		})

		const fadeDown = (element, options = {}) => animate(element, {
			...options,
			start: (el) =>
			{
				if (window.getComputedStyle(el).display !== 'none')
				{
					return false
				}

				XF.display(el)
				const targetHeight = el.offsetHeight
				el.style.overflow = 'hidden'

				return { targetHeight }
			},
			step: (el, { delta, targetHeight }) =>
			{
				el.style.height = (targetHeight * delta) + 'px'
				el.style.opacity = delta.toString()
			},
			finish: (el) =>
			{
				el.style.height = ''
				el.style.opacity = ''
				el.style.overflow = ''
			},
		})

		return {
			enable,
			disable,
			easing,
			animate,
			fadeOut,
			fadeIn,
			fadeTo,
			slideUp,
			slideDown,
			fadeUp,
			fadeDown,
		}
	})()

	XF.Transition = (() =>
	{
		const getCssTransitionDuration = el =>
		{
			if (!el || !(el instanceof Element))
			{
				return 0
			}

			const durationCss = window.getComputedStyle(el).transitionDuration
			let duration = 0

			if (durationCss)
			{
				const regex = /^(\+|-|)([0-9]*\.[0-9]+|[0-9]+)(ms|s)/i
				const matches = regex.exec(durationCss)

				if (matches)
				{
					const sign = matches[1] === '-' ? -1 : 1
					const value = parseFloat(matches[2])
					const unit = matches[3].toLowerCase() === 'ms' ? 1 : 1000

					duration = sign * value * unit
				}
			}

			return duration
		}

		const getClassDiff = (el, checkClassList, getMissing) =>
		{
			const diff = []

			if (XF.isFunction(checkClassList))
			{
				checkClassList = checkClassList.call(el, 0, el.className)
			}

			const checkClasses = checkClassList.trim().split(/\s+/), classes = ' ' + el.className + ' '
			let present
			for (const checkClass of checkClasses)
			{
				present = (classes.indexOf(' ' + checkClass + ' ') >= 0)
				if ((present && !getMissing) || (!present && getMissing))
				{
					diff.push(checkClass)
				}
			}

			return diff
		}

		const mappedAttrs = {
			height: ['height', 'padding-top', 'padding-bottom', 'margin-top', 'margin-bottom', 'border-top-width', 'border-bottom-width'],
			width: ['width', 'padding-left', 'padding-right', 'margin-left', 'margin-right', 'border-right-width', 'border-left-width'],
		}

		const adjustClasses = (el, isAdding, className, onTransitionEnd, instant) =>
		{
			const duration = instant ? 0 : getCssTransitionDuration(el)
			const mainFunc = isAdding ? 'add' : 'remove'
			const inverseFunc = isAdding ? 'remove' : 'add'
			const getMissing = isAdding ? true : false
			const classes = getClassDiff(el, className, getMissing)
			const transitioningClass = 'is-transitioning'
			const transitionEndFakeCall = () =>
			{
				if (onTransitionEnd)
				{
					setTimeout(() =>
					{
						onTransitionEnd.call(el, XF.customEvent('transitionend'))
					}, 0)
				}
			}

			if (!classes.length)
			{
				transitionEndFakeCall()
				return
			}

			if (duration <= 0)
			{
				el.classList[mainFunc](...classes)
				transitionEndFakeCall()
				return
			}

			if (el.classList.contains(transitioningClass))
			{
				XF.trigger(el, 'transitionend')
			}

			el.classList.add(transitioningClass)

			if (window.getComputedStyle(el).getPropertyValue('transition-property').match(/(^|\s|,)-xf-(width|height)($|\s|,)/))
			{
				const attr = RegExp.$2
				const relatedAttrs = mappedAttrs[attr]

				const curComputed = window.getComputedStyle(el)
				const curCssValues = {}

				for (let prop of relatedAttrs)
				{
					curCssValues[prop] = curComputed.getPropertyValue(prop)
				}

				let curCssValue = curCssValues[attr]
				const storeCurStyle = 'transition.' + attr
				let curStyleValues = XF.DataStore.get(el, storeCurStyle)
				const style = el.style
				const previousTransition = style['transition'] || ''

				if (!curStyleValues)
				{
					curStyleValues = {}
					relatedAttrs.forEach(relatedAttr =>
					{
						curStyleValues[relatedAttr] = style[relatedAttr] || ''
					})
				}

				const getDimension = (el, attr) =>
				{
					const style = window.getComputedStyle(el)
					let dimension

					switch (attr)
					{
						case 'width':
							dimension = el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
							break

						case 'height':
							dimension = el.clientHeight - parseFloat(style.paddingTop || '0') - parseFloat(style.paddingBottom || '0')
							break
					}

					return dimension
				}

				if (getDimension(el, attr) === 0)
				{
					curCssValue = '0'

					for (let i of Object.keys(curCssValues))
					{
						curCssValues[i] = '0'
					}
				}

				XF.DataStore.set(el, storeCurStyle, curStyleValues)
				el.style.transition = 'none'
				el.classList[mainFunc](...classes)

				const newComputed = window.getComputedStyle(el)
				const newCssValues = {}

				for (let prop of relatedAttrs)
				{
					newCssValues[prop] = newComputed.getPropertyValue(prop)
				}

				let newCssValue = newCssValues[attr]

				if (getDimension(el, attr) === 0)
				{
					newCssValue = '0'
					for (let i of Object.keys(newCssValues))
					{
						newCssValues[i] = '0'
					}
				}

				el.classList[inverseFunc](...classes)

				if (curCssValue !== newCssValue)
				{
					let originalCallback = onTransitionEnd

					for (let prop of Object.keys(curCssValues))
					{
						el.style[prop] = curCssValues[prop]
					}

					// eslint-disable-next-line @typescript-eslint/no-unused-expressions
					el.offsetWidth // this is needed to force a redraw; must be before the transition restore line
					el.style.transition = previousTransition

					for (let prop of Object.keys(newCssValues))
					{
						el.style[prop] = newCssValues[prop]
					}

					onTransitionEnd = (...args) =>
					{
						el.style.cssText = XF.DataStore.get(el, storeCurStyle)
						XF.DataStore.remove(el, storeCurStyle)

						if (originalCallback)
						{
							originalCallback(...args)
						}
					}
				}
				else
				{
					el.style.transition = previousTransition
				}
			}

			XF.onTransitionEnd(el, duration, (...args) =>
			{
				el.classList.remove(transitioningClass)

				if (onTransitionEnd)
				{
					onTransitionEnd(...args)
				}
			})
			el.classList[mainFunc](className)
		}

		const addClassTransitioned = (element, className, onTransitionEnd, instant) =>
		{
			const els = Array.isArray(element) ? element : [element]
			const len = els.length
			for (let i = 0; i < len; i++)
			{
				adjustClasses(els[i], true, className, onTransitionEnd, instant)
			}

			return element
		}

		const removeClassTransitioned = (element, className, onTransitionEnd, instant) =>
		{
			const els = Array.isArray(element) ? element : [element]
			const len = els.length
			for (let i = 0; i < len; i++)
			{
				adjustClasses(els[i], false, className, onTransitionEnd, instant)
			}

			return element
		}

		const toggleClassTransitioned = (element, className, state, onTransitionEnd, instant) =>
		{
			if (typeof state !== 'boolean' && typeof onTransitionEnd === 'undefined')
			{
				onTransitionEnd = state
				state = null
			}

			const useState = (typeof state === 'boolean')
			const els = Array.isArray(element) ? element : [element]
			const len = els.length

			for (let i = 0; i < len; i++)
			{
				const el = els[i]
				let add

				if (useState)
				{
					add = state
				}
				else
				{
					add = el.classList.contains(className) ? false : true
				}

				adjustClasses(el, add, className, onTransitionEnd, instant)
			}

			return element
		}

		return {
			addClassTransitioned,
			removeClassTransitioned,
			toggleClassTransitioned,
		}
	})()

	XF.CrossTab = (() =>
	{
		const listeners = {}
		let listening = false
		const communicationKey = '__crossTab'

		let activeEvent

		const handleEvent = e =>
		{
			const expectedKey = XF.LocalStorage.getKeyName(communicationKey)
			if (e.key !== expectedKey)
			{
				return
			}

			let json

			try
			{
				json = JSON.parse(e.newValue)
			}
			catch (e)
			{
				return
			}

			if (!json || !json.event)
			{
				return
			}

			const event = json.event, data = json.data || null, activeListeners = listeners[event]

			if (!activeListeners)
			{
				return
			}

			activeEvent = event

			for (const listener of activeListeners)
			{
				listener(data)
			}

			activeEvent = null
		}

		return {
			on: (event, callback) =>
			{
				if (!listeners[event])
				{
					listeners[event] = []
				}

				listeners[event].push(callback)

				if (!listening)
				{
					listening = true
					XF.on(window, 'storage', handleEvent)
				}
			},

			trigger: (event, data, forceCall) =>
			{
				if (!forceCall && activeEvent && activeEvent === event)
				{
					// this is to help prevent infinite loops where the code that reacts to an event
					// is the same code that gets called by the event
					return
				}

				XF.LocalStorage.setJson(communicationKey, {
					event,
					data,
					_: new Date() + Math.random(), // forces the event to fire
				})
			},
		}
	})()

	XF.Breakpoint = (() =>
	{
		let val = null
		const sizes = ['narrow', 'medium', 'wide', 'full']

		const current = () =>
		{
			return val
		}

		const isNarrowerThan = (test) =>
		{
			for (const size of sizes)
			{
				if (test === size)
				{
					return false
				}

				if (val === size)
				{
					return true
				}
			}

			return false
		}

		const isAtOrNarrowerThan = (test) =>
		{
			return (val === test || isNarrowerThan(test))
		}

		const isWiderThan = (test) =>
		{
			let afterTest = false

			for (const size of sizes)
			{
				if (test === size)
				{
					afterTest = true
					continue
				}

				if (val === size)
				{
					return afterTest
				}
			}

			return false
		}

		const isAtOrWiderThan = (test) =>
		{
			return (val === test || isWiderThan(test))
		}

		const refresh = () =>
		{
			const newVal = window.getComputedStyle(document.querySelector('html'), ':after').getPropertyValue('content').replace(/"/g, '')

			if (val)
			{
				if (newVal !== val)
				{
					val = newVal
					XF.trigger(document, 'breakpoint:change')
				}
			}
			else
			{
				// initial load, don't trigger anything
				val = newVal
			}

			return val
		}

		refresh()
		XF.on(window, 'resize', refresh, { passive: true })

		return {
			current,
			refresh,
			isNarrowerThan,
			isAtOrNarrowerThan,
			isWiderThan,
			isAtOrWiderThan,
		}
	})()

	XF.JobRunner = (() =>
	{
		let manualRunning = false, manualOnlyIds = [], manualXhr, manualOverlay = null, autoBlockingRunning = 0,
			autoBlockingXhr, autoBlockingOverlay = null

		const runAuto = () =>
		{
			fetch(XF.canonicalizeUrl('job.php'), {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
				},
				cache: 'no-store',
			})
				.then(response =>
				{
					if (!response.ok)
					{
						throw new Error('Network response was not ok.')
					}

					return response.json()
				})
				.then(data =>
				{
					if (data && data.more)
					{
						setTimeout(runAuto, 100)
					}
				})
		}

		const runAutoBlocking = (onlyIds, message) =>
		{
			if (typeof onlyIds === 'number')
			{
				onlyIds = [onlyIds]
			}
			else if (!Array.isArray(onlyIds))
			{
				return
			}

			if (!onlyIds.length)
			{
				return
			}

			autoBlockingRunning++
			getAutoBlockingOverlay().show()

			if (!message)
			{
				message = XF.phrase('processing...')
			}
			document.querySelector('#xfAutoBlockingJobStatus').textContent = message

			runAutoBlockingRequest(onlyIds)
		}

		const runAutoBlockingRequest = onlyIds =>
		{
			const { ajax, controller } = XF.ajaxAbortable(
				'post',
				XF.canonicalizeUrl('job.php'),
				{ only_ids: onlyIds },
				data =>
				{
					if (data.more && data.ids && data.ids.length)
					{
						if (data.status)
						{
							document.querySelector('#xfAutoBlockingJobStatus').textContent = data.status
						}

						setTimeout(() =>
						{
							runAutoBlockingRequest(data.ids)
						}, 0)
					}
					else
					{
						stopAutoBlocking()
						if (data.moreAuto)
						{
							setTimeout(runAuto, 100)
						}
					}
				},
				{ skipDefault: true }
			)

			ajax.catch(stopAutoBlocking)
			autoBlockingXhr = controller
		}

		const stopAutoBlocking = () =>
		{
			if (autoBlockingOverlay)
			{
				autoBlockingOverlay.hide()
			}

			autoBlockingRunning--
			if (autoBlockingRunning < 0)
			{
				autoBlockingRunning = 0
			}

			if (autoBlockingRunning === 0)
			{
				XF.trigger(document, 'job:auto-blocking-complete')
				triggerBlockingComplete()
			}

			if (autoBlockingXhr)
			{
				autoBlockingXhr.abort()
			}
			autoBlockingXhr = null
		}

		const getAutoBlockingOverlay = () =>
		{
			if (!autoBlockingOverlay)
			{
				autoBlockingOverlay = getModalJobOverlay('xfAutoBlockingJobStatus')
			}
			return autoBlockingOverlay
		}

		const runManual = onlyId =>
		{
			const url = XF.config.job.manualUrl
			if (!url)
			{
				return
			}

			if (onlyId === null)
			{
				manualOnlyIds = null
			}
			else
			{
				manualOnlyIds = manualOnlyIds || []
				if (typeof onlyId === 'number')
				{
					manualOnlyIds.push(onlyId)
				}
				else if (Array.isArray(onlyId))
				{
					manualOnlyIds = [...manualOnlyIds, ...onlyId]
				}
			}

			if (manualRunning)
			{
				return
			}
			manualRunning = true

			getManualOverlay().show()

			const runJob = runOnlyId =>
			{
				const { ajax, controller } = XF.ajaxAbortable(
					'post',
					url,
					runOnlyId ? { only_id: runOnlyId } : null,
					data =>
					{
						if (data.jobRunner)
						{
							document.querySelector('#xfManualJobStatus').textContent = data.jobRunner.status || XF.phrase('processing...')

							setTimeout(() =>
							{
								runJob(runOnlyId)
							}, 0)
						}
						else
						{
							runNext()
						}
					},
					{ skipDefault: true }
				)

				ajax.catch(stopManual)
				manualXhr = controller
			}

			const runNext = () =>
			{
				if (Array.isArray(manualOnlyIds) && manualOnlyIds.length === 0)
				{
					stopManual()
				}
				else
				{
					runJob(manualOnlyIds ? manualOnlyIds.shift() : null)
				}
			}
			runNext()
		}

		const stopManual = () =>
		{
			if (manualOverlay)
			{
				manualOverlay.hide()
			}

			manualOnlyIds = []
			manualRunning = false
			XF.trigger(document, 'job:manual-complete')
			triggerBlockingComplete()

			if (manualXhr)
			{
				manualXhr.abort()
			}
			manualXhr = null
		}

		const getManualOverlay = () =>
		{
			if (!manualOverlay)
			{
				manualOverlay = getModalJobOverlay('xfManualJobStatus')
			}
			return manualOverlay
		}

		const getModalJobOverlay = statusId =>
		{
			const overlay = XF.getOverlayHtml({
				title: XF.phrase('processing...'),
				dismissible: false,
				html: `<div class="blockMessage"><span id="${ statusId }">${ XF.phrase('processing...') }</span></div>`,
			})
			return new XF.Overlay(overlay, {
				backdropClose: false,
				keyboard: false,
			})
		}

		const triggerBlockingComplete = () =>
		{
			if (!isBlockingJobRunning())
			{
				XF.trigger(document, 'job:blocking-complete')
			}
		}

		const isBlockingJobRunning = () =>
		{
			return manualRunning || autoBlockingRunning > 0
		}

		return {
			isBlockingJobRunning,
			runAuto,
			runAutoBlocking,
			runManual,
			stopManual,
			getManualOverlay,
		}
	})()

	XF.DataStore = (() =>
	{
		const _dataStore = new WeakMap()

		const set = (el, key, value) =>
		{
			if (!_dataStore.has(el))
			{
				_dataStore.set(el, {})
			}

			const data = _dataStore.get(el)
			data[key] = value
		}

		const get = (el, key) =>
		{
			const data = _dataStore.get(el)
			if (data)
			{
				return data[key]
			}

			return null
		}

		const remove = (el, key) =>
		{
			const data = _dataStore.get(el)
			if (data)
			{
				delete data[key]
			}
		}

		return {
			set,
			get,
			remove,
		}
	})()

	XF.Serializer = (() =>
	{
		const serializeArray = elements =>
		{
			const serializedArray = []
			let fields

			if (elements instanceof HTMLFormElement)
			{
				fields = Array.from(elements.elements)
			}
			else if (elements instanceof NodeList || elements instanceof HTMLCollection)
			{
				fields = Array.from(elements)
			}
			else if (elements instanceof Array)
			{
				fields = elements
			}
			else
			{
				throw new Error('serializeArray expects either a form element, a NodeList/HTMLCollection of inputs, or an array of inputs')
			}

			fields.forEach(field =>
			{
				if (field.name
					&& !field.disabled
					&& field.type !== 'file'
					&& field.type !== 'reset'
					&& field.type !== 'submit'
					&& field.type !== 'button'
				)
				{
					if (field.type === 'select-multiple')
					{
						Array.from(field.options).forEach(option =>
						{
							if (option.selected)
							{
								serializedArray.push({
									name: field.name,
									value: option.value,
								})
							}
						})
					}
					else if ((field.type !== 'checkbox' && field.type !== 'radio') || field.checked)
					{
						serializedArray.push({
							name: field.name,
							value: field.value,
						})
					}
				}
			})

			return serializedArray
		}

		const serializeFormData = data =>
		{
			return data.map(item => `${ encodeURIComponent(item.name) }=${ encodeURIComponent(item.value) }`).join('&')
		}

		const serializeJSON = elements =>
		{
			const serialized = {}

			XF.Serializer.serializeArray(elements).forEach(obj =>
			{
				const name = obj.name
				const value = obj.value

				const keys = splitInputNameIntoKeysArray(name)
				deepSet(serialized, keys, value)
			})

			return serialized
		}

		const deepSet = (serialized, keys, value) =>
		{
			if (serialized === undefined)
			{
				throw new Error('ArgumentError: param \'serialized\' expected to be an object or array, found undefined')
			}
			if (!keys || keys.length === 0)
			{
				throw new Error('ArgumentError: param \'keys\' expected to be an array with at least one element')
			}

			let key = keys[0]

			if (keys.length === 1)
			{
				if (key === '')
				{
					serialized.push(value)
				}
				else
				{
					serialized[key] = value
				}
			}
			else
			{
				const nextKey = keys[1]

				if (key === '')
				{
					const lastIdx = serialized.length - 1
					const lastVal = serialized[lastIdx]
					if (XF.isObject(lastVal) && (lastVal[nextKey] === undefined || keys.length > 2))
					{
						key = lastIdx
					}
					else
					{
						key = lastIdx + 1
					}
				}

				if (nextKey === '')
				{
					if (serialized[key] === undefined || !Array.isArray(serialized[key]))
					{
						serialized[key] = []
					}
				}
				else
				{
					if (serialized[key] === undefined || !XF.isObject(serialized[key]))
					{
						serialized[key] = {}
					}
				}

				const tail = keys.slice(1)
				deepSet(serialized[key], tail, value)
			}
		}

		const splitInputNameIntoKeysArray = name =>
		{
			let keys = name.split('[')
			keys = keys.map(key => key.replace(/\]/g, ''))

			if (keys[0] === '')
			{
				keys.shift()
			}

			return keys
		}

		return {
			serializeArray,
			serializeFormData,
			serializeJSON,
		}
	})()

	XF.Loader = (() =>
	{
		const loadedCss = XF.config.css
		const loadedJs = XF.config.js

		const load = (js = [], css = [], onComplete) =>
		{
			const loadJs = js.filter(jsFile => !XF.hasOwn(loadedJs, jsFile))
			const loadCss = css.filter(cssFile => !XF.hasOwn(loadedCss, cssFile))

			let totalRemaining = (loadJs.length ? 1 : 0) + (loadCss.length ? 1 : 0)
			const markFinished = () =>
			{
				totalRemaining--
				if (totalRemaining === 0 && onComplete)
				{
					onComplete()
				}
			}

			if (totalRemaining === 0)
			{
				if (onComplete)
				{
					onComplete()
				}
				return
			}

			if (loadJs.length)
			{
				XF.loadScripts(loadJs, () =>
				{
					loadJs.forEach(jsFile =>
					{
						loadedJs[jsFile] = true
					})
					markFinished()
				})
			}

			if (loadCss.length)
			{
				let cssUrl = XF.config.url.css
				if (cssUrl)
				{
					cssUrl = cssUrl.replace('__SENTINEL__', loadCss.join(','))

					fetch(cssUrl, {
						headers: {
							'Accept': 'text/css',
						},
					})
						.then(response =>
						{
							if (!response.ok)
							{
								throw new Error('Network response was not ok.')
							}

							return response.text()
						})
						.then(cssText =>
						{
							const baseHref = XF.config.url.basePath
							if (baseHref)
							{
								cssText = cssText.replace(/(url\(("|')?)([^"')]+)(("|')?\))/gi, (all, front, null1, url, back, null2) =>
								{
									if (!url.match(/^([a-z]+:|\/)/i))
									{
										url = baseHref + url
									}
									return front + url + back
								})
							}

							const style = XF.createElement('style', {
								textContent: cssText
							}, document.head)

							loadCss.forEach(stylesheet =>
							{
								loadedCss[stylesheet] = true
							})
							markFinished()
						})
				}
				else
				{
					console.error('No CSS URL so cannot dynamically load CSS')
					markFinished()
				}
			}
		}

		return {
			load,
			loadCss: (css, onComplete) => load([], css, onComplete),
			loadJs: (js, onComplete) => load(js, [], onComplete),
		}
	})()

	XF.LazyHandlerLoader = (() =>
	{
		const lazyHandlers = {}
		let activationTimer

		const initialize = () =>
		{
			register('xf/action.js', 'alerts-list')
			register('xf/action.js', 'bookmark-click')
			register('xf/action.js', 'bookmark-label-filter')
			register('xf/action.js', 'content-vote')
			register('xf/action.js', 'copy-to-clipboard')
			register('xf/action.js', 'draft')
			register('xf/action.js', 'draft-trigger')
			register('xf/action.js', 'focus-trigger')
			register('xf/action.js', 'poll-block')
			register('xf/action.js', 'preview')
			register('xf/action.js', 'push-cta')
			register('xf/action.js', 'push-toggle')
			register('xf/action.js', 'reaction')
			register('xf/action.js', 'share-input')
			register('xf/action.js', 'attribution', 'click')
			register('xf/action.js', 'like', 'click')
			register('xf/action.js', 'switch', 'click')
			register('xf/action.js', 'switch-overlay', 'click')
			register('xf/form.js', 'ajax-submit')
			register('xf/form.js', 'asset-upload')
			register('xf/form.js', 'auto-submit')
			register('xf/form.js', 'change-submit')
			register('xf/form.js', 'check-all')
			register('xf/form.js', 'checkbox-select-disabler')
			register('xf/form.js', 'desc-loader')
			register('xf/form.js', 'disabler')
			register('xf/form.js', 'emoji-completer')
			register('xf/form.js', 'field-adder')
			register('xf/form.js', 'form-submit-row')
			register('xf/form.js', 'guest-username')
			register('xf/form.js', 'input-validator')
			register('xf/form.js', 'min-length')
			register('xf/form.js', 'password-hide-show')
			register('xf/form.js', 'select-plus')
			register('xf/form.js', 'textarea-handler')
			register('xf/form.js', 'user-mentioner')
			register('xf/form.js', 'submit', 'click')
			register('xf/structure.js', 'focus-inserter')
			register('xf/structure.js', 'responsive-data-list')
			register('xf/structure.js', 'tabs')
			register('xf/structure.js', 'toggle-storage')
			register('xf/structure.js', 'touch-proxy')
			register('xf/structure.js', 'video-init')
			register('xf/structure.js', 'duplicator', 'click')
			register('xf/structure.js', 'inserter', 'click')
			register('xf/structure.js', 'menu-proxy', 'click')
			register('xf/structure.js', 'remover', 'click')
			register('xf/structure.js', 'shifter', 'click')
			register('xf/structure.js', 'toggle', 'click')
			register('xf/structure.js', 'comment-toggle', 'click')
			register('xf/structure.js', 'toggle-class', 'click')
			register('xf/tooltip.js', 'preview-tooltip')
			register('xf/tooltip.js', 'share-tooltip')
		}

		const register = (file, handler, type = 'init', minified = true) =>
		{
			const existingHandler = (type === 'init')
				? XF.Element.getObjectFromIdentifier(handler)
				: XF.Event.getObjectFromIdentifier(handler)
			if (existingHandler !== null)
			{
				// the handler has already been loaded and registered normally
				return
			}

			lazyHandlers[handler] = {
				file,
				type,
				minified,
				loading: false,
			}
		}

		const checkLazyRegistration = (handler) =>
		{
			if (handler in lazyHandlers)
			{
				delete lazyHandlers[handler]

				clearTimeout(activationTimer)
				activationTimer = setTimeout(() =>
				{
					XF.activate(document)
				}, 50)
			}
		}

		const loadLazyHandlers = (el) =>
		{
			const jsUrl = XF.config.url.js
			if (!jsUrl)
			{
				console.error('No JS URL so cannot lazy-load JS')
				return
			}

			const elementHandlers = getHandlersForElements(el)
			const unloadedHandlers = Object.keys(lazyHandlers).filter(handler =>
			{
				if (!elementHandlers.includes(handler))
				{
					return false
				}

				return lazyHandlers[handler].loading === false
			})
			if (!unloadedHandlers.length)
			{
				return
			}

			const files = unloadedHandlers
				.map(unloadedHandler =>
				{
					const handler = lazyHandlers[unloadedHandler]
					handler.loading = true

					const file = handler.minified && !XF.config.fullJs
						? handler.file.replace('.js', '.min.js')
						: handler.file
					return jsUrl.replace('__SENTINEL__', file) + '_mt=' + XF.config.jsMt[file] || ''
				})
				.filter((file, index, self) => self.indexOf(file) === index)

			XF.Loader.loadJs(files)
		}

		const getHandlersForElements = (el) =>
		{
			let elementHandlers = []

			// check if el is a NodeList or an array of elements
			if (el instanceof NodeList || Array.isArray(el))
			{
				el.forEach(elem =>
				{
					elementHandlers = [
						...elementHandlers,
						...getHandlersForElements(elem),
					]
				})

				return elementHandlers
			}

			const types = Object.keys(lazyHandlers)
				.map(handler => lazyHandlers[handler].type)
				.filter((type, index, self) => self.indexOf(type) === index)
			const selector = `[data-xf-${types.join('], [data-xf-')}]`

			const baseElement = el.nodeType === Node.DOCUMENT_NODE
				? el.documentElement
				: el

			if (baseElement.matches(selector))
			{
				elementHandlers = [
					...elementHandlers,
					...getHandlersForElement(baseElement, types),
				]
			}

			const foundElements = baseElement.querySelectorAll(selector)
			foundElements.forEach(foundElement =>
			{
				elementHandlers = [
					...elementHandlers,
					...getHandlersForElement(foundElement, types),
				]
			})

			return elementHandlers.filter((h, i, s) => s.indexOf(h) === i)
		}

		const getHandlersForElement = (el, types) =>
		{
			let elementHandlers = []

			for (const type of types)
			{
				const attribute = `data-xf-${type}`
				if (!el.hasAttribute(attribute))
				{
					continue
				}

				const handlers = el.getAttribute(attribute)
					.split(' ')
					.filter(h => h !== '')

				elementHandlers = [
					...elementHandlers,
					...handlers,
				]
			}

			return elementHandlers
		}

		return {
			initialize,
			register,
			checkLazyRegistration,
			loadLazyHandlers,
		}
	})()

	XF.ClassMapper = XF.create({
		_map: {},
		_toExtend: {},

		add (identifier, className)
		{
			this._map[identifier] = className
		},

		extend (identifier, extension)
		{
			let obj = this.getObjectFromIdentifier(identifier)
			if (obj)
			{
				obj = XF.extend(obj, extension)
				this._map[identifier] = obj
			}
			else
			{
				if (!this._toExtend[identifier])
				{
					this._toExtend[identifier] = []
				}
				this._toExtend[identifier].push(extension)
			}
		},

		getObjectFromIdentifier (identifier)
		{
			let record = this._map[identifier]
			const extensions = this._toExtend[identifier]

			if (!record)
			{
				return null
			}

			if (typeof record == 'string')
			{
				record = XF.classToConstructor(record)
				if (extensions)
				{
					for (const extension of extensions)
					{
						record = XF.extend(record, extension)
					}

					delete this._toExtend[identifier]
				}

				this._map[identifier] = record
			}

			return record
		},
	})

	XF.ActionIndicator = (() =>
	{
		let activeCounter = 0, indicator

		const initialize = () =>
		{
			XF.on(document, 'xf:action-start', show)
			XF.on(document, 'xf:action-stop', hide)
		}

		const show = () =>
		{
			activeCounter++
			if (activeCounter !== 1)
			{
				return
			}

			if (!indicator)
			{
				indicator = XF.createElement('span', {
					className: 'globalAction',
					innerHTML: '<span class="globalAction-bar"></span>' + '<span class="globalAction-block"><i></i><i></i><i></i></span>'
				}, document.body)
			}

			indicator.classList.add('is-active')
		}

		const hide = () =>
		{
			activeCounter--
			if (activeCounter > 0)
			{
				return
			}

			activeCounter = 0

			if (indicator)
			{
				indicator.classList.remove('is-active')
			}
		}

		return {
			initialize,
			show,
			hide,
		}
	})()

	XF.StyleVariation = (() =>
	{
		const getVariation = () =>
		{
			const variation = document.querySelector('html').dataset.variation
			if (variation)
			{
				return variation
			}

			if (
				window.matchMedia('(prefers-color-scheme: dark)').matches &&
				XF.config.style.dark
			)
			{
				return XF.config.style.dark
			}

			if (
				window.matchMedia('(prefers-color-scheme: light)').matches &&
				XF.config.style.light
			)
			{
				return XF.config.style.light
			}

			return 'default'
		}

		const getColorScheme = () =>
		{
			const colorScheme = document.querySelector('html').dataset.colorScheme
			if (colorScheme)
			{
				return colorScheme
			}

			if (
				window.matchMedia('(prefers-color-scheme: dark)').matches &&
				XF.config.style.dark
			)
			{
				return 'dark'
			}

			return XF.config.style.defaultColorScheme ?? 'light'
		}

		const root = document.documentElement
		let pending = false

		const updateVariation = (variation) =>
		{
			if (pending)
			{
				return
			}

			pending = true

			const url = document.querySelector('html').dataset.app === 'admin'
				? 'admin.php?account/style-variation'
				: 'index.php?misc/style-variation'
			const data = variation ? { variation } : { reset: 1 }

			XF.ajax(
				'GET',
				XF.canonicalizeUrl(url),
				data,
				handleResponse,
				{ skipDefault: true },
			)
		}

		const handleResponse = (data) =>
		{
			pending = false

			const { variation, colorScheme, icon, properties } = data
			const oldVariation = getVariation()
			const newVariation = variation

			setVariation(variation, colorScheme)
			setThemeColor(properties.metaThemeColor)
			setPictures(variation)

			updateMenu(icon)
			updateMenuSelection(variation)

			const variationEvent = XF.customEvent('xf:variation-change', {
				oldVariation,
				newVariation,
				data,
			})
			XF.trigger(document, variationEvent)
		}

		const setVariation = (variation, colorScheme) =>
		{
			if (variation)
			{
				root.setAttribute('data-variation', variation)
			}
			else
			{
				root.removeAttribute('data-variation')
			}

			if (colorScheme)
			{
				root.setAttribute('data-color-scheme', colorScheme)
			}
			else
			{
				root.removeAttribute('data-color-scheme')
			}
		}

		const setThemeColor = (themeColor) =>
		{
			if (!themeColor)
			{
				return
			}

			const head = document.querySelector('head')

			const themeColors = head.querySelectorAll('meta[name=theme-color]')
			themeColors.forEach(el => el.remove())

			if (typeof themeColor !== 'object')
			{
				XF.createElement(
					'meta',
					{
						name: 'theme-color',
						content: themeColor
					},
					head
				)
				return
			}

			for (const styleType in themeColor)
			{
				XF.createElement(
					'meta',
					{
						name: 'theme-color',
						media: `(prefers-color-scheme: ${styleType})`,
						content: themeColor[styleType]
					},
					head
				)
			}
		}

		const setPictures = (variation) =>
		{
			const pictures = document.querySelectorAll(
				'picture[data-variations]'
			)
			for (const picture of pictures)
			{
				setPicture(picture, variation)
			}
		}

		const setPicture = (picture, variation) =>
		{
			const container = XF.createElement('picture')

			const variations = JSON.parse(picture.dataset.variations)
			const img = picture.querySelector('img')
			const imgProps = {
				properties: {},
				attributes: {
					width: img.getAttribute('width'),
					height: img.getAttribute('height'),
					alt: img.getAttribute('alt'),
				}
			}

			if (variation !== '')
			{
				imgProps.properties.src = variations[variation][1]

				if (variations[variation][2])
				{
					imgProps.properties.srcset = `${variations[variation][2]} 2x`
				}
			}
			else
			{
				const defaultColorScheme = XF.config.style.defaultColorScheme
				const alternateColorScheme = defaultColorScheme === 'dark'
					? 'light'
					: 'dark'
				const defaultVariation = XF.config.style[defaultColorScheme]
				const alternateVariation = XF.config.style[alternateColorScheme]

				for (const [variation, densities] of Object.entries(variations))
				{
					if (variation === defaultVariation)
					{
						imgProps.properties.src = densities[1]

						if (densities[2])
						{
							imgProps.properties.srcset = `${densities[2]} 2x`
						}
					}
					else if (
						variation === alternateVariation &&
						(
							densities[1] !== variations[defaultVariation][1] ||
							densities[2] !== variations[defaultVariation][2]
						)
					)
					{
						const sourceEl = XF.createElement('source', {
							srcset: `${densities[1]}${ densities[2] ? `, ${densities[2]} 2x` : '' }`,
							media: `(prefers-color-scheme: ${alternateColorScheme})`,
						})
						container.prepend(sourceEl)
					}
				}
			}

			container.append(XF.createElement('img', imgProps))

			picture.innerHTML = ''
			picture.append(...container.childNodes)
		}

		const updateMenu = (icon) =>
		{
			const menus = document.querySelectorAll('.js-styleVariationsMenu')
			for (const menu of menus)
			{
				XF.trigger(menu, 'menu:close')
			}

			if (!icon)
			{
				return
			}

			const menuLinks = document.querySelectorAll('.js-styleVariationsLink')
			for (const menuLink of menuLinks)
			{
				const menuIcon = menuLink.querySelector('i.fa--xf')
				if (!menuIcon)
				{
					continue
				}

				const newIcon = XF.createElementFromString(
					XF.Icon.getIcon('default', icon)
				)
				menuIcon.replaceWith(newIcon)
			}
		}

		const updateMenuSelection = (variation) =>
		{
			const menus = document.querySelectorAll('.js-styleVariationsMenu')
			for (const menu of menus)
			{
				const rows = menu.querySelectorAll('.menu-linkRow')
				for (const row of rows)
				{
					if (row.dataset.variation === variation)
					{
						row.classList.add('is-selected')
					}
					else
					{
						row.classList.remove('is-selected')
					}
				}
			}
		}

		return {
			getVariation,
			getColorScheme,
			updateVariation,
		}
	})()

	XF.Icon = (() =>
	{
		const ICON_DATA_REGEX = /<svg [^>]*viewBox="(?<viewBox>[^"]+)"[^>]*>.*?(?:<defs>.*<\/defs>)?(?<icon>(?:<path [^>]*\/>)+)<\/svg>/i

		const ICON_CLASS_REGEX = /^fa-(?!-)(?<name>[a-z0-9-]+)$/i

		const ICON_CLASS_BLOCKLIST_REGEX = /^fa-(xs|sm|lg|\d+x|fw|ul|li|rotate-\d+|flip-(horizontal|vertical|both)|spin|pulse|border|pull-(left|right)|stack(-\dx)?|inverse)$/i

		/**
		 * For automatic icon analysis to work properly, the function call should
		 * be on a single line, and only strings literals should be passed for
		 * the variant and name arguments. To use the default variant, pass
		 * 'default' as the variant argument. For more complex use cases, use the
		 * icon analyzer code event or extend the analyzer.
		 */
		const getIcon = (variant, name, classes, title) =>
		{
			const url = getIconUrl(variant, name)
			const icon = `<svg xmlns="http://www.w3.org/2000/svg" role="img" ${ title ? '' : 'aria-hidden="true"' }>
					${ title ? `<title>${title}</title>` : '' }
					<use href="${url}"></use>
				</svg>`

			return wrapIcon(variant, name, classes, icon)
		}

		/**
		 * This should be used only when strictly necessary, as the icon will not
		 * be sprited.
		 */
		const getInlineIcon = async (variant, name, classes, title) =>
		{
			const url = getStandaloneIconUrl(variant, name)
			const response = await fetch(url, {
				headers: {
					'Accept': 'image/svg+xml',
				}
			})
			if (!response.ok)
			{
				throw new Error('Inline icon could not be fetched.')
			}

			const text = await response.text()
			const match = text.match(ICON_DATA_REGEX)
			if (!match)
			{
				throw new Error('Icon did not match expected format.')
			}

			const iconData = match.groups
			const icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${iconData['viewBox']}" role="img" ${ title ? '' : 'aria-hidden="true"' }>
					${ title ? `<title>${title}</title>` : '' }
					${iconData['icon']}
				</svg>`

			return wrapIcon(variant, name, classes, icon)
		}

		const wrapIcon = (variant, name, classes, inner) =>
		{
			classes = classes !== undefined ? classes : ''
			classes = `${getVariantClass(variant)} ${getIconClass(name)} ${classes}`

			return `<i class="fa--xf ${classes}">${inner}</i>`
		}

		const getIconUrl = (variant, name) =>
		{
			variant = normalizeIconVariant(variant)
			name = normalizeIconName(name)

			return XF.config.url.icon
				.replace('__VARIANT__', variant)
				.replace('__NAME__', name)
		}

		const getStandaloneIconUrl = (variant, name) =>
		{
			variant = normalizeIconVariant(variant)
			name = normalizeIconName(name)

			return XF.config.url.iconInline
				.replace('__VARIANT__', variant)
				.replace('__NAME__', name)
		}

		const getVariantClass = (variant) =>
		{
			let variantClass = 'far'

			variant = normalizeIconVariant(variant)
			switch (variant)
			{
				case 'light':
					variantClass = 'fal'
					break

				case 'regular':
					variantClass = 'far'
					break

				case 'solid':
					variantClass = 'fas'
					break

				case 'duotone':
					variantClass = 'fad'
					break

				case 'brands':
					variantClass = 'fab'
					break
			}

			return variantClass
		}

		const getIconClass = (name) =>
		{
			name = normalizeIconName(name)
			return `fa-${name}`
		}

		const normalizeIconVariant = (variant) =>
		{
			let normalizedVariant = 'regular'

			if (variant === 'default')
			{
				variant = XF.config.fontAwesomeWeight
			}

			switch (variant)
			{
				case 300:
				case 'l':
				case 'fal':
				case 'light':
					normalizedVariant = 'light'
					break

				case 400:
				case 'r':
				case 'far':
				case 'regular':
					normalizedVariant = 'regular'
					break

				case 900:
				case 's':
				case 'fas':
				case 'solid':
					normalizedVariant = 'solid'
					break

				case 'd':
				case 'fad':
				case 'duotone':
					normalizedVariant = 'duotone'
					break

				case 'b':
				case 'fab':
				case 'brands':
					normalizedVariant = 'brands'
					break
			}

			return normalizedVariant
		}

		const normalizeIconName = (name) =>
		{
			return name.replace(/^fa-/, '')
		}

		return {
			ICON_DATA_REGEX,
			ICON_CLASS_REGEX,
			ICON_CLASS_BLOCKLIST_REGEX,
			getIcon,
			getInlineIcon,
			getIconUrl,
			getStandaloneIconUrl,
			getVariantClass,
			getIconClass,
			normalizeIconVariant,
			normalizeIconName,
		}
	})()

	XF.DynamicDate = (() =>
	{
		let localLoadTime
		let serverLoadTime
		let todayStart
		let todayDow
		let yesterdayStart
		let tomorrowStart
		let weekStart
		let monthStart
		let yearStart
		let initialized = false
		let interval
		let futureInterval

		const startInterval = () =>
		{
			interval = setInterval(() =>
			{
				refresh(document)
			}, 20 * 1000)
		}

		const initialize = () =>
		{
			if (initialized)
			{
				return
			}
			initialized = true

			const time = XF.config.time

			localLoadTime = XF.getLocalLoadTime()
			serverLoadTime = time.now
			todayStart = time.today
			todayDow = time.todayDow
			yesterdayStart = time.yesterday
			tomorrowStart = time.tomorrow
			weekStart = time.week
			monthStart = time.month
			yearStart = time.year

			if (document.hidden !== undefined)
			{
				if (!document.hidden)
				{
					startInterval()
				}

				XF.on(document, 'visibilitychange', () =>
				{
					if (document.hidden)
					{
						clearInterval(interval)
					}
					else
					{
						startInterval()
						refresh(document)
					}
				})
			}
			else
			{
				startInterval()
			}
		}

		const refresh = root =>
		{
			if (!initialized)
			{
				initialize()
			}

			const els = root.querySelectorAll('time[data-timestamp]')
			const length = els.length
			const now = Math.floor(new Date().getTime() / 1000)
			const openLength = now - localLoadTime
			const todayStartObj = new Date()
			let el
			let interval
			let futureInterval
			let dynType
			let thisTime

			todayStartObj.setHours(0, 0, 0, 0)

			if (serverLoadTime + openLength > tomorrowStart)
			{
				// day has changed, need to adjust
				todayDow = todayStartObj.getDay()
				tomorrowStart = getRelativeTimestamp(todayStartObj, 1)
				todayStart = getRelativeTimestamp(todayStartObj, 0)
				yesterdayStart = getRelativeTimestamp(todayStartObj, -1)
				weekStart = getRelativeTimestamp(todayStartObj, -6)
			}

			for (let i = 0; i < length; i++)
			{
				el = els[i]
				thisTime = parseInt(el.getAttribute('data-timestamp'), 10)
				interval = (serverLoadTime - thisTime) + openLength
				dynType = el.xfDynType

				if (interval < -2)
				{
					// date in the future, note that -2 is a bit of fudging as times might be very close and our local
					// load time may not jive 100% with the server

					futureInterval = thisTime - (serverLoadTime + openLength)

					if (futureInterval < 60)
					{
						if (dynType !== 'futureMoment')
						{
							el.textContent = XF.phrase('in_a_moment')
							el.xfDynType = 'futureMoment'
						}
					}
					else if (futureInterval < 120)
					{
						if (dynType !== 'futureMinute')
						{
							el.textContent = XF.phrase('in_a_minute')
							el.xfDynType = 'futureMinute'
						}
					}
					else if (futureInterval < 3600)
					{
						const minutes = Math.floor(futureInterval / 60)
						if (dynType !== 'futureMinutes' + minutes)
						{
							el.textContent = XF.phrase('in_x_minutes', {
								'{minutes}': minutes,
							})
							el.xfDynType = 'futureMinutes' + minutes
						}
					}
					else if (thisTime < tomorrowStart)
					{
						if (dynType !== 'latertoday')
						{
							el.textContent = XF.phrase('later_today_at_x', {
								'{time}': el.getAttribute('data-time'),
							})
							el.xfDynType = 'latertoday'
						}
					}
					else if (thisTime < getRelativeTimestamp(todayStartObj, 2))
					{
						if (dynType !== 'tomorrow')
						{
							el.textContent = XF.phrase('tomorrow_at_x', {
								'{time}': el.getAttribute('data-time'),
							})
							el.xfDynType = 'tomorrow'
						}
					}
					else if (futureInterval < (7 * 86400)) // this doesn't account for DST shifts, but meh...
					{
						// no need to change anything
						el.xfDynType = 'future'
					}
					else
					{
						// after the next week
						if (el.getAttribute('data-full-date'))
						{
							el.textContent = XF.phrase('date_x_at_time_y', {
								'{date}': el.getAttribute('data-date'),
								'{time}': el.getAttribute('data-time'),
							})
						}
						else
						{
							el.textContent = el.getAttribute('data-date')
						}

						el.xfDynType = 'future'
					}
				}
				else if (interval <= 60)
				{
					if (dynType !== 'moment')
					{
						el.textContent = XF.phrase('a_moment_ago')
						el.xfDynType = 'moment'
					}
				}
				else if (interval <= 120)
				{
					if (dynType !== 'minute')
					{
						el.textContent = XF.phrase('one_minute_ago')
						el.xfDynType = 'minute'
					}
				}
				else if (interval < 3600)
				{
					const minutes = Math.floor(interval / 60)
					if (dynType !== 'minutes' + minutes)
					{
						el.textContent = XF.phrase('x_minutes_ago', {
							'{minutes}': minutes,
						})
						el.xfDynType = 'minutes' + minutes
					}
				}
				else if (thisTime >= todayStart)
				{
					if (dynType !== 'today')
					{
						el.textContent = XF.phrase('today_at_x', {
							'{time}': el.getAttribute('data-time'),
						})
						el.xfDynType = 'today'
					}
				}
				else if (thisTime >= yesterdayStart)
				{
					if (dynType !== 'yesterday')
					{
						el.textContent = XF.phrase('yesterday_at_x', {
							'{time}': el.getAttribute('data-time'),
						})
						el.xfDynType = 'yesterday'
					}
				}
				else if (thisTime >= weekStart)
				{
					if (dynType !== 'week')
					{
						el.textContent = XF.phrase('day_x_at_time_y', {
							'{day}': XF.phrase('day' + new Date(thisTime * 1000).getDay()),
							'{time}': el.getAttribute('data-time'),
						})
						el.xfDynType = 'week'
					}
				}
				else
				{
					if (dynType !== 'old')
					{
						if (el.getAttribute('data-full-date'))
						{
							el.textContent = XF.phrase('date_x_at_time_y', {
								'{date}': el.getAttribute('data-date'),
								'{time}': el.getAttribute('data-time'),
							})
						}
						else
						{
							el.textContent = el.getAttribute('data-date')
						}

						el.xfDynType = 'old'
					}
				}

				// short dates - don't bother updating short dates older than 30 days
				if (interval < 3600)
				{
					el.setAttribute('data-short', XF.phrase('short_date_x_minutes', {
						'{minutes}': Math.floor(interval / 60),
					}))
				}
				else if (interval < 86400)
				{
					el.setAttribute('data-short', XF.phrase('short_date_x_hours', {
						'{hours}': Math.floor(interval / 3600),
					}))
				}
				else if (interval < 86400 * 30)
				{
					el.setAttribute('data-short', XF.phrase('short_date_x_days', {
						'{days}': Math.floor(interval / 86400),
					}))
				}
			}
		}

		const getRelativeTimestamp = (srcDateObj, offsetDays) =>
		{
			const dateObj = new Date(srcDateObj.valueOf())

			return Math.floor(dateObj.setFullYear(srcDateObj.getFullYear(), srcDateObj.getMonth(), srcDateObj.getDate() + offsetDays) / 1000)
		}

		return {
			initialize,
			refresh,
		}
	})()

	XF.KeepAlive = (() =>
	{
		let url, crossTabEvent
		let initialized = false, baseTimerDelay = 50 * 60, // in seconds, 50 minutes
			jitterRange = 120
		let interval

		const initialize = () =>
		{
			if (initialized)
			{
				return
			}

			if (!XF.config.url.keepAlive || !XF.config.url.keepAlive.length)
			{
				return
			}
			initialized = true

			url = XF.config.url.keepAlive
			crossTabEvent = 'keepAlive' + XF.stringHashCode(url)

			resetTimer()

			XF.CrossTab.on(crossTabEvent, applyChanges)

			if (window.performance && window.performance.getEntriesByType)
			{
				const navigationEntry = window.performance.getEntriesByType('navigation')[0]

				if (navigationEntry)
				{
					const navType = navigationEntry.type

					if (navType === 'navigate' || navType === 'reload')
					{
						// navigate or reload, we have the most recent data from the server so pass that on
						XF.CrossTab.trigger(crossTabEvent, {
							csrf: XF.config.csrf,
							time: XF.config.time.now,
							user_id: XF.config.userId,
						})
					}
				}
			}

			if (!XF.Cookie.get('csrf'))
			{
				refresh()
			}
		}

		const resetTimer = () =>
		{
			const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min

			let delay = baseTimerDelay + rand(-jitterRange, jitterRange) // +/- jitter to prevent opened tabs sticking together
			if (delay < jitterRange)
			{
				delay = jitterRange
			}

			if (interval)
			{
				clearInterval(interval)
			}
			interval = setInterval(refresh, delay * 1000)

			// note that while this should be reset each time it's triggered, using an interval ensures
			// that it runs again even if there's an error
		}

		let offlineCount = 0, offlineDelayTimer

		const refresh = () =>
		{
			if (!initialized)
			{
				return
			}

			// if we're offline, delay testing by 30 seconds a few times. This tries to maintain the keep alive
			// when there are temporary network drops or if waking up from sleep and the network isn't ready yet.
			if (window.navigator.onLine === false)
			{
				offlineCount++

				if (offlineCount <= 5)
				{
					offlineDelayTimer = setTimeout(refresh, 30)
				}
			}

			offlineCount = 0
			clearTimeout(offlineDelayTimer)

			fetch(XF.canonicalizeUrl(url), {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({
					_xfResponseType: 'json',
					_xfToken: XF.config.csrf,
				}),
				cache: 'no-store',
			}).then(response =>
			{
				if (!response.ok)
				{
					throw new Error('Network response was not ok.')
				}

				return response.json()
			}).then(data =>
			{
				// noinspection JSIncompatibleTypesComparison
				if (data.status !== 'ok')
				{
					return
				}

				applyChanges(data)
				XF.CrossTab.trigger(crossTabEvent, data)
			})
		}

		const applyChanges = data =>
		{
			if (data.csrf)
			{
				XF.config.csrf = data.csrf
				document.querySelectorAll('input[name=_xfToken]').forEach(el => el.value = data.csrf)
			}

			if (typeof data.user_id !== 'undefined')
			{
				const activeChangeMessage = document.querySelector('.js-activeUserChangeMessage')

				if (data.user_id !== XF.config.userId && !activeChangeMessage)
				{
					XF.addFixedMessage(XF.phrase('active_user_changed_reload_page'), {
						class: 'js-activeUserChangeMessage',
					})
				}
				if (data.user_id === XF.config.userId && activeChangeMessage)
				{
					activeChangeMessage.remove()
				}
			}

			resetTimer()
		}

		return {
			initialize,
			refresh,
		}
	})()

	XF.History = (() =>
	{
		const windowHistory = window.history
		let activeState = windowHistory.state, activeUrl = window.location.href
		const handlers = []

		const initialize = () =>
		{
			XF.on(window, 'popstate', e =>
			{
				const state = e.state
				let handled = false

				for (const handler of handlers)
				{
					if (handler(state, activeState, activeUrl))
					{
						handled = true
					}
				}

				if (!handled && activeUrl.replace(/#.*$/, '') !== window.location.href.replace(/#.*$/, ''))
				{
					// nothing handled this and the URL changed, so this should probably have a different
					// document loaded so trigger a reload
					window.location.reload()
				}

				updateActiveState(state)
			})
		}

		const updateActiveState = (state) =>
		{
			activeState = state
			activeUrl = window.location.href
		}

		const store = (method, state, title, url) =>
		{
			windowHistory[method](state, title, url)
			updateActiveState(state)
		}

		return {
			initialize,
			handle: callback =>
			{
				handlers.push(callback)
			},
			push: (state, title, url) =>
			{
				store('pushState', state, title, url)
			},
			replace: (state, title, url) =>
			{
				store('replaceState', state, title, url)
			},
			go: delta =>
			{
				windowHistory.go(delta)
			},
		}
	})()

	XF.PullToRefresh = (() =>
	{
		const initialize = () =>
		{
			// by default utilise pull-to-refresh on iOS and only if display-mode == standalone and on the frontend
			if (!XF.isIOS() || !XF.Feature.has('displaymodestandalone') || XF.getApp() !== 'public')
			{
				return
			}

			XF.loadScript(XF.canonicalizeUrl('js/vendor/boxfactura/pulltorefresh.min.js'), () =>
			{
				PullToRefresh.init({
					classPrefix: 'iosRefresh-',
					distReload: 70,
					iconArrow: XF.Icon.getIcon('default', 'fa-arrow-down', 'fa-2x'),
					iconRefreshing: XF.Icon.getIcon('default', 'fa-spinner-third', 'fa-2x fa-spin'),
					instructionsPullToRefresh: XF.phrase('pull_down_to_refresh'),
					instructionsReleaseToRefresh: XF.phrase('release_to_refresh'),
					instructionsRefreshing: XF.phrase('refreshing'),
					onRefresh ()
					{
						window.location.reload()
					},
				})
			})
		}

		return {
			initialize,
		}
	})()

	// ################################## LINK PROXY WATCHER ###########################################

	XF.LinkWatcher = (() =>
	{
		const proxyInternals = false

		const proxyLinkClick = (e) =>
		{
			const link = e.currentTarget
			const proxyHref = link.getAttribute('data-proxy-href')
			const lastEvent = link.getAttribute('data-proxy-handler-last')

			if (!proxyHref)
			{
				return
			}

			// we may have a direct click event and a bubbled event. Ensure they don't both fire.
			if (lastEvent && lastEvent === e.timeStamp)
			{
				return
			}
			link.setAttribute('data-proxy-handler-last', e.timeStamp.toString())

			fetch(XF.canonicalizeUrl(proxyHref), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					_xfResponseType: 'json',
					referrer: window.location.href.replace(/#.*$/, ''),
				}),
				cache: 'no-store',
			})
				.catch(() =>
				{
				})
		}

		const initLinkProxy = () =>
		{
			let selector = 'a[data-proxy-href]'

			if (!proxyInternals)
			{
				selector += ':not(.link--internal)'
			}

			document.querySelectorAll(selector).forEach((link) =>
			{
				XF.on(link, 'click', proxyLinkClick)
				XF.on(link, 'focusin', (e) =>
				{
					// This approach is taken because middle click events do not bubble. This is a way of
					// getting the equivalent of event bubbling on middle clicks in Chrome.
					if (e.currentTarget.getAttribute('data-proxy-handler'))
					{
						return
					}

					e.currentTarget.setAttribute('data-proxy-handler', 'true')
					XF.on(e.currentTarget, 'click', proxyLinkClick)
				})
			})
		}

		const externalLinkClick = (e) =>
		{
			if (!XF.config.enableRtnProtect)
			{
				return
			}

			if (e.defaultPrevented)
			{
				return
			}

			const link = e.currentTarget
			let href = link.getAttribute('href')
			const lastEvent = link.getAttribute('data-blank-handler-last')
			if (!href)
			{
				return
			}

			if (href.match(/^[a-z]:/i) && !href.match(/^https?:/i))
			{
				// ignore canonical but non http(s) links
				return
			}

			if (link.matches('[data-fancybox]'))
			{
				// don't do anything here as the lightbox will take over (and this is a trusted link)
				return
			}

			// if noopener is supported and in use, then use that instead
			if (link.matches('[rel~=noopener]'))
			{
				const browser = XF.browser
				if ((browser.chrome && browser.version >= 49) || (browser.mozilla && browser.version >= 52) || (browser.safari && browser.version >= 11) // may be supported in some 10.x releases
				// Edge and IE don't support it yet
				)
				{
					return
				}
			}

			if (link.closest('[contenteditable=true]'))
			{
				return
			}

			href = XF.canonicalizeUrl(href)

			const regex = new RegExp('^[a-z]+://' + location.host + '(/|$|:)', 'i')
			if (regex.test(href))
			{
				// if the link is local, then don't do the special processing
				return
			}

			// we may have a direct click event and a bubbled event. Ensure they don't both fire.
			if (lastEvent && lastEvent === e.timeStamp)
			{
				return
			}

			link.setAttribute('data-blank-handler-last', e.timeStamp)

			const ua = navigator.userAgent, isOldIE = ua.indexOf('MSIE') !== -1,
				isSafari = ua.indexOf('Safari') !== -1 && ua.indexOf('Chrome') === -1,
				isGecko = ua.indexOf('Gecko/') !== -1

			if (e.shiftKey && isGecko)
			{
				// Firefox doesn't trigger when holding shift. If the code below runs, it will force
				// opening in a new tab instead of a new window, so stop. Note that Chrome still triggers here,
				// but it does open in a new window anyway so we run the normal code.
				return
			}
			if (isSafari && (e.shiftKey || e.altKey))
			{
				// this adds to reading list or downloads instead of opening a new tab
				return
			}
			if (isOldIE)
			{
				// IE has mitigations for this and this blocks referrers
				return
			}

			// now run the opener clearing

			if (isSafari)
			{
				// Safari doesn't work with the other approach
				// Concept from: https://github.com/danielstjules/blankshield
				let iframe, iframeDoc, script

				iframe = XF.createElement('iframe', {
					style: { display: 'none' }
				}, document.body)

				iframeDoc = iframe.contentDocument || iframe.contentWindow.document

				iframeDoc.__href = href // set this so we don't need to do an eval-type thing

				script = iframeDoc.createElement('script')
				script.textContent = 'window.opener=null;' + 'window.parent=null;window.top=null;window.frameElement=null;' + 'window.open(document.__href).opener = null;'

				iframeDoc.body.appendChild(script)
				iframe.remove()
			}
			else
			{
				// use this approach for the rest to maintain referrers when possible
				const w = window.open(href)

				try
				{
					w.opener = null
				}
				catch (e)
				{
					// this can potentially fail, don't want to break
				}
			}

			e.preventDefault()
		}

		const initExternalWatcher = () =>
		{
			const selector = 'a[target=_blank]'

			document.querySelectorAll(selector).forEach((link) =>
			{
				XF.on(link, 'click', externalLinkClick)
				XF.on(link, 'focusin', (e) =>
				{
					// This approach is taken because middle click events do not bubble. This is a way of
					// getting the equivalent of event bubbling on middle clicks in Chrome.
					if (e.currentTarget.getAttribute('data-blank-handler'))
					{
						return
					}

					e.currentTarget.setAttribute('data-blank-handler', 'true')
					XF.on(e.currentTarget, 'click', externalLinkClick)
				})
			})
		}

		return {
			initLinkProxy,
			initExternalWatcher,
		}
	})()

	// ################################## IGNORED CONTENT WATCHER ###########################################

	XF._IgnoredWatcher = XF.create({
		options: {
			container: 'body',
			ignored: '.is-ignored',
			link: '.js-showIgnored',
		},

		container: null,
		authors: [],
		shown: false,

		__construct (options)
		{
			this.options = XF.extendObject(true, {}, this.options, options || {})

			const container = document.querySelector(this.options.container)
			this.container = container

			this.updateState()

			XF.on(container, 'click', (e) =>
			{
				if (e.target.matches(this.options.link))
				{
					this.show()
				}
			})
		},

		refresh (el)
		{
			if (!this.container.contains(el))
			{
				// el is not in our search area
				return
			}

			if (this.shown)
			{
				// already showing, so apply that here as well
				this.show()
			}
			else
			{
				this.updateState()
			}
		},

		updateState ()
		{
			if (this.shown)
			{
				// already showing
				return
			}

			const ignored = this.getIgnored(), authors = []

			if (!ignored.length)
			{
				// nothing to do - assume hidden by default
				return
			}

			ignored.forEach(element =>
			{
				const author = element.dataset.author
				if (author && !authors.includes(author))
				{
					authors.push(author)
				}
			})

			if (authors.length)
			{
				const textReplace = { names: authors.join(', ') }

				this.getLinks().forEach(element =>
				{
					const title = element.getAttribute('title')
					if (title)
					{
						element.setAttribute('title', Mustache.render(title, textReplace))
						element.classList.remove('is-hidden')
					}
				})
			}
			else
			{
				this.getLinks().forEach(element =>
				{
					element.removeAttribute('title')
					element.classList.remove('is-hidden')
				})
			}
		},

		getIgnored ()
		{
			return Array.from(this.container.querySelectorAll(this.options.ignored))
		},

		getLinks ()
		{
			return Array.from(this.container.querySelectorAll(this.options.link))
		},

		show ()
		{
			this.shown = true
			this.getIgnored().forEach(element => element.classList.remove('is-ignored'))
			this.getLinks().forEach(element => element.classList.add('is-hidden'))
		},

		initializeHash ()
		{
			if (window.location.hash)
			{
				const cleanedHash = window.location.hash.replace(/[^\w_#-]/g, '')
				if (cleanedHash === '#')
				{
					return
				}

				const jump = document.getElementById(cleanedHash)
				const ignoredSel = this.options.ignored
				let ignored

				if (jump && jump.matches(ignoredSel))
				{
					ignored = jump
				}
				else if (jump)
				{
					ignored = jump.closest(ignoredSel)
				}

				if (ignored)
				{
					ignored.classList.remove('is-ignored')
					jump.scrollIntoView(true)
				}
			}
		},
	})

	XF.IgnoreWatcher = new XF._IgnoredWatcher()

	XF.BrowserWarning = (() =>
	{
		const display = () =>
		{
			let display = false

			if (XF.browser.msie)
			{
				display = true
			}
			else if (XF.browser.edge && parseInt(XF.browser.version) < 18)
			{
				display = true
			}

			const warning = document.querySelector('.js-browserWarning')

			if (!warning)
			{
				return
			}

			if (display)
			{
				XF.display(warning)
			}
			else
			{
				warning.remove()
			}
		}

		const hideJsWarning = () =>
		{
			const jsWarning = document.querySelector('.js-jsWarning')
			if (jsWarning)
			{
				jsWarning.remove()
			}
		}

		return {
			display,
			hideJsWarning,
		}
	})()

	// ################################ ACTION BAR HANDLER ##########################################

	XF.MultiBar = XF.create({
		options: {
			role: null,
			focusShow: false,
			className: '',
			fastReplace: false,
		},

		container: null,
		multiBar: null,
		shown: false,

		__construct (content, options)
		{
			this.options = XF.extendObject(true, {}, this.options, options || {})
			this.multiBar = XF.createElementFromString(content)
			this.multiBar.setAttribute('role', this.options.role || 'dialog')
			this.multiBar.setAttribute('aria-hidden', 'true')

			XF.on(this.multiBar, 'multibar:hide', this.hide.bind(this))
			XF.on(this.multiBar, 'multibar:show', this.show.bind(this))

			this.container = XF.createElementFromString('<div class="multiBar-container"></div>')
			this.container.append(this.multiBar)
			this.container.classList.add(...this.options.className.split(' '))
			XF.uniqueId(this.container)

			XF.DataStore.set(this.container, 'multibar', this)

			document.body.append(this.container)
			XF.activate(this.container)

			XF.MultiBar.cache[this.container.id] = this
		},

		show ()
		{
			if (this.shown)
			{
				return
			}

			this.shown = true
			this.multiBar.setAttribute('aria-hidden', 'false')

			const pageWrapper = document.querySelector('.p-pageWrapper')
			pageWrapper.classList.add('has-multiBar')

			if (this.options.fastReplace)
			{
				this.multiBar.style.transitionDuration = '0s'
			}

			document.body.append(this.container)
			XF.Transition.addClassTransitioned(this.multiBar, 'is-active', () =>
			{
				if (this.options.focusShow)
				{
					const autoFocusFallback = this.multiBar.querySelector('.js-multiBarClose')
					XF.autoFocusWithin(this.multiBar.find('.multiBar-content'), null, autoFocusFallback)
				}

				XF.trigger(this.container, 'multibar:shown')

				XF.layoutChange()
			})

			if (this.options.fastReplace)
			{
				this.multiBar.style.transitionDuration = ''
			}

			XF.trigger(this.container, 'multibar:showing')

			XF.layoutChange()
		},

		hide ()
		{
			if (!this.shown)
			{
				return
			}

			this.shown = false
			this.multiBar.setAttribute('aria-hidden', 'true')

			XF.Transition.removeClassTransitioned(this.multiBar, 'is-active', () =>
			{
				document.querySelector('.p-pageWrapper').classList.remove('has-multiBar')

				XF.trigger(this.container, 'multibar:hidden')

				XF.layoutChange()
			})

			XF.trigger(this.container, 'multibar:hiding')

			XF.layoutChange()
		},

		toggle (forceState)
		{
			const newState = (forceState === null ? !this.shown : forceState)
			if (newState)
			{
				this.show()
			}
			else
			{
				this.hide()
			}
		},

		destroy ()
		{
			const id = this.container.getAttribute('id')
			const cache = XF.MultiBar.cache

			this.container.remove()
			if (XF.hasOwn(cache, id))
			{
				delete cache[id]
			}
		},

		on (event, callback)
		{
			XF.on(this.container, event, callback)
		},

		getContainer ()
		{
			return this.container
		},

		getMultiBar ()
		{
			return this.multiBar
		},
	})
	XF.MultiBar.cache = {}

	XF.showMultiBar = (html, options) =>
	{
		const MultiBar = new XF.MultiBar(html, options)
		MultiBar.show()
		return MultiBar
	}

	XF.loadMultiBar = (url, data, options, multiBarOptions) =>
	{
		if (XF.isFunction(options))
		{
			options = { init: options }
		}

		options = XF.extendObject({
			cache: false,
			beforeShow: null,
			afterShow: null,
			onRedirect: null,
			init: null,
			show: true,
		}, options || {})

		const show = (MultiBar) =>
		{
			if (options.beforeShow)
			{
				const e = XF.customEvent(undefined)
				e.cancelable = true

				options.beforeShow(MultiBar, e)
				if (e.defaultPrevented)
				{
					return
				}
			}

			if (options.show)
			{
				MultiBar.show()
			}

			if (options.afterShow)
			{
				const e = XF.customEvent(undefined)
				e.cancelable = true

				options.afterShow(MultiBar, e)
				if (e.defaultPrevented)
				{
					return
				}
			}
		}

		if (options.cache && XF.loadMultiBar.cache[url])
		{
			show(XF.loadMultiBar.cache[url])
			return
		}

		const multiBarAjaxHandler = (data) =>
		{
			if (data.redirect)
			{
				if (options.onRedirect)
				{
					options.onRedirect(data, multiBarAjaxHandler)
				}
				else
				{
					XF.ajax('get', data.redirect, (data) =>
					{
						multiBarAjaxHandler(data)
					})
				}
			}

			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const MultiBar = new XF.MultiBar(XF.getMultiBarHtml({
					html,
					title: container.title || container.h1,
				}), multiBarOptions)

				if (options.init)
				{
					options.init(MultiBar)
				}

				if (!options.cache)
				{
					MultiBar.on('multibar:hidden', () =>
					{
						MultiBar.destroy()
					})
				}

				onComplete()

				if (options.cache)
				{
					XF.loadMultiBar.cache[url] = MultiBar
				}

				show(MultiBar)
			})
		}

		return XF.ajax('post', url, data, (data) =>
		{
			multiBarAjaxHandler(data)
		})
	}
	XF.loadMultiBar.cache = {}

	// ################################## OVERLAY HANDLER ###########################################

	XF.Overlay = XF.create({
		options: {
			backdropClose: true,
			escapeClose: true,
			focusShow: true,
			className: '',
		},

		container: null,
		overlay: null,
		shown: false,

		__construct (content, options)
		{
			this.options = XF.extendObject(true, {}, this.options, options || {})

			this.overlay = content
			this.overlay.setAttribute('role', this.options.role || 'dialog')
			this.overlay.setAttribute('aria-hidden', 'true')

			this.container = XF.createElementFromString('<div class="overlay-container"></div>')
			this.container.append(this.overlay)

			if (this.options.className)
			{
				this.container.classList.add(...this.options.className.split(' '))
			}

			XF.uniqueId(this.container)

			XF.DataStore.set(this.container, 'overlay', this)

			if (this.options.escapeClose)
			{
				XF.on(this.container, 'keydown.overlay', e =>
				{
					if (e.key === 'Escape')
					{
						this.hide()
					}
				})
			}

			if (this.options.backdropClose)
			{
				XF.on(this.container, 'mousedown', e =>
				{
					XF.DataStore.set(this.container, 'block-close', false)

					if (e.target !== this.container)
					{
						// click didn't target container so block closing.
						XF.DataStore.set(this.container, 'block-close', true)
					}
				})

				XF.on(this.container, 'click', e =>
				{
					if (e.target === this.container)
					{
						if (!XF.DataStore.get(this.container, 'block-close'))
						{
							this.hide()
						}
					}

					XF.DataStore.set(this.container, 'block-close', false)
				})
			}

			XF.onDelegated(this.container, 'click', '.js-overlayClose', () => this.hide())

			document.body.append(this.container)
			XF.activate(this.container)

			XF.Overlay.cache[this.container.getAttribute('id')] = this

			XF.on(this.overlay, 'overlay:hide', this.hide.bind(this))
			XF.on(this.overlay, 'overlay:show', this.show.bind(this))
		},

		show ()
		{
			if (this.shown)
			{
				return
			}

			this.shown = true

			this.overlay.setAttribute('aria-hidden', 'false')

			// reappending to the body ensures this is the last one, which should allow stacking
			document.body.append(this.container)
			XF.Transition.addClassTransitioned(this.container, 'is-active', () =>
			{
				if (this.options.focusShow)
				{
					const autoFocusFallback = this.overlay.querySelector('.js-overlayClose')
					XF.autoFocusWithin(this.overlay.querySelector('.overlay-content'), null, autoFocusFallback)
				}

				XF.trigger(this.container, 'overlay:shown')
				XF.layoutChange()
			})

			XF.trigger(this.container, 'overlay:showing')

			XF.ModalOverlay.open()
			XF.layoutChange()
		},

		hide ()
		{
			if (!this.shown)
			{
				return
			}

			this.shown = false

			this.overlay.setAttribute('aria-hidden', 'true')

			XF.Transition.removeClassTransitioned(this.container, 'is-active', () =>
			{
				XF.trigger(this.container, 'overlay:hidden')

				XF.ModalOverlay.close()
				XF.layoutChange()
			})

			XF.trigger(this.container, 'overlay:hiding')

			XF.layoutChange()
		},

		recalculate ()
		{
			if (this.shown)
			{
				XF.Modal.updateScrollbarPadding()
			}
		},

		toggle ()
		{
			if (this.shown)
			{
				this.hide()
			}
			else
			{
				this.show()
			}
		},

		destroy ()
		{
			const id = this.container.getAttribute('id'), cache = XF.Overlay.cache

			this.container.remove()
			if (XF.hasOwn(cache, id))
			{
				delete cache[id]
			}
		},

		on (event, callback)
		{
			XF.on(this.container, event, callback)
		},

		getContainer ()
		{
			return this.container
		},

		getOverlay ()
		{
			return this.overlay
		},
	})
	XF.Overlay.cache = {}

	XF.ModalOverlay = (() =>
	{
		let count = 0
		const body = document.body

		const open = () =>
		{
			XF.Modal.open()

			count++
			if (count == 1)
			{
				body.classList.add('is-modalOverlayOpen')
			}
		}

		const close = () =>
		{
			XF.Modal.close()

			if (count > 0)
			{
				count--
				if (count == 0)
				{
					body.classList.remove('is-modalOverlayOpen')
				}
			}
		}

		return {
			getOpenCount: () => count,
			open,
			close,
		}
	})()

	XF.Modal = (() =>
	{
		let count = 0
		const body = document.body
		const html = document.querySelector('html')

		const open = () =>
		{
			count++
			if (count == 1)
			{
				body.classList.add('is-modalOpen')
				updateScrollbarPadding()
			}
		}
		const close = () =>
		{
			if (count > 0)
			{
				count--
				if (count == 0)
				{
					body.classList.remove('is-modalOpen')
					updateScrollbarPadding()
				}
			}
		}

		const updateScrollbarPadding = () =>
		{
			let side = 'right'
			const value = body.classList.contains('is-modalOpen') ? XF.measureScrollBar() + 'px' : ''

			if (XF.isRtl())
			{
				// Chrome and Firefox keep the body scrollbar on the right but IE/Edge flips it
				if (!XF.browser.chrome && !XF.browser.mozilla)
				{
					side = 'left'
				}
			}

			html.style['margin-' + side] = value
		}

		return {
			getOpenCount: () => count,
			open,
			close,
			updateScrollbarPadding,
		}
	})()

	XF.showOverlay = (html, options) =>
	{
		const overlay = new XF.Overlay(html, options)
		overlay.show()
		return overlay
	}

	XF.loadOverlay = (url, options, overlayOptions) =>
	{
		if (XF.isFunction(options))
		{
			options = { init: options }
		}

		options = XF.extendObject({
			cache: false,
			beforeShow: null,
			afterShow: null,
			onRedirect: null,
			ajaxOptions: {},
			init: null,
			show: true,
		}, options || {})

		const show = overlay =>
		{
			if (options.beforeShow)
			{
				const e = XF.customEvent('overlay:before-show')

				options.beforeShow(overlay, e)
				if (e.defaultPrevented)
				{
					return
				}
			}

			if (options.show)
			{
				overlay.show()
			}

			if (options.afterShow)
			{
				const e = XF.customEvent('overlay:after-show')

				options.afterShow(overlay, e)
				if (e.defaultPrevented)
				{
					return
				}
			}
		}

		if (options.cache && XF.loadOverlay.cache[url])
		{
			show(XF.loadOverlay.cache[url])
			return
		}

		const overlayAjaxHandler = (data) =>
		{
			if (data.redirect)
			{
				if (options.onRedirect)
				{
					options.onRedirect(data, overlayAjaxHandler)
				}
				else
				{
					XF.ajax('get', data.redirect, data => overlayAjaxHandler(data))
				}
			}

			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const overlay = new XF.Overlay(XF.getOverlayHtml({
					html,
					title: container.title || container.h1,
					url: XF.canonicalizeUrl(url),
				}), overlayOptions)
				if (options.init)
				{
					options.init(overlay)
				}
				if (!options.cache)
				{
					overlay.on('overlay:hidden', () => overlay.destroy())
				}

				onComplete()

				if (options.cache)
				{
					XF.loadOverlay.cache[url] = overlay
				}

				show(overlay)
			})
		}

		return XF.ajax('get', url, {}, data =>
		{
			overlayAjaxHandler(data)
		}, options.ajaxOptions)
	}
	XF.loadOverlay.cache = {}

	// ################################## NAVIGATION DEVICE WATCHER ###########################################

	/**
	 * Allows querying of the current input device (mouse or keyboard) -- .isKeyboardNav()
	 * And sets a CSS class (has-pointer-nav) on <html> to allow styling based on current input
	 *
	 * @type {{initialize, toggle, isKeyboardNav}}
	 */
	XF.NavDeviceWatcher = (() =>
	{
		let isKeyboard = true

		const initialize = () =>
		{
			XF.on(document, 'mousedown', () => toggle(false), { passive: true })
			XF.on(document, 'keydown', (e) =>
			{
				switch (e.key)
				{
					case 'Tab':
					case 'Enter':
						toggle(true)
				}
			}, { passive: true })
		}

		const toggle = (toKeyboard) =>
		{
			if (toKeyboard != isKeyboard)
			{
				document.querySelector('html').classList.toggle('has-pointer-nav', !toKeyboard)

				isKeyboard = toKeyboard
			}
		}

		const isKeyboardNav = () => isKeyboard

		return {
			initialize,
			toggle,
			isKeyboardNav,
		}
	})()

	XF.ScrollButtons = (() =>
	{
		let hideTimer = null, pauseScrollWatch = false, upOnly = false, isShown = false,
			scrollTop = window.scrollY || document.documentElement.scrollTop, scrollDir = null,
			scrollTopDirChange = null, scrollTrigger, buttons = null

		const initialize = () =>
		{
			if (buttons && buttons.length)
			{
				// already initialized
				return false
			}

			buttons = document.querySelector('.js-scrollButtons')

			if (!buttons)
			{
				return false
			}

			if (buttons.dataset.triggerType === 'up')
			{
				upOnly = true
			}

			XF.on(buttons, 'mouseenter', enter)
			XF.on(buttons, 'focus', enter)
			XF.on(buttons, 'mouseleave', leave)
			XF.on(buttons, 'blur', leave)
			XF.on(buttons, 'click', click)

			XF.on(window, 'scroll', onScroll, { passive: true })

			return true
		}

		const onScroll = e =>
		{
			if (pauseScrollWatch)
			{
				return
			}

			const newScrollTop = window.scrollY || document.documentElement.scrollTop, oldScrollTop = scrollTop

			scrollTop = newScrollTop

			if (newScrollTop > oldScrollTop)
			{
				if (scrollDir != 'down')
				{
					scrollDir = 'down'
					scrollTopDirChange = oldScrollTop
				}
			}
			else if (newScrollTop < oldScrollTop)
			{
				if (scrollDir != 'up')
				{
					scrollDir = 'up'
					scrollTopDirChange = oldScrollTop
				}
			}
			else
			{
				// didn't scroll?
				return
			}

			if (upOnly)
			{
				// downward scroll or we're near the top anyway
				if (scrollDir !== 'up' || scrollTop < 100)
				{
					if (scrollTrigger)
					{
						scrollTrigger.cancel()
						scrollTrigger = null
					}
					return
				}

				// only trigger after scrolling up 30px to reduce false positives
				if (scrollTopDirChange - newScrollTop < 30)
				{
					return
				}
			}

			if (scrollTrigger)
			{
				// already about to be triggered
				return
			}

			// note that Chrome on Android can heavily throttle setTimeout, so use a requestAnimationFrame
			// alternative if possible to ensure this triggers when expected
			scrollTrigger = XF.requestAnimationTimeout(() =>
			{
				scrollTrigger = null

				show()
				startHideTimer()
			}, 200)
		}

		const show = () =>
		{
			if (!isShown)
			{
				XF.Transition.addClassTransitioned(buttons, 'is-active')
				isShown = true
			}
		}

		const hide = () =>
		{
			if (isShown)
			{
				XF.Transition.removeClassTransitioned(buttons, 'is-active')
				isShown = false
			}
		}

		const startHideTimer = () =>
		{
			clearHideTimer()

			hideTimer = setTimeout(() => hide(), 3000)
		}

		const clearHideTimer = () => clearTimeout(hideTimer)

		const enter = () =>
		{
			clearHideTimer()

			show()
		}

		const leave = () => clearHideTimer()

		const click = e =>
		{
			const target = e.target
			if (!target.matches('.button--scroll') || !target.closest('.button--scroll'))
			{
				return
			}

			pauseScrollWatch = true

			setTimeout(() =>
			{
				pauseScrollWatch = false
			}, 500)

			hide()
		}

		return {
			initialize,
			show,
			hide,
			startHideTimer,
			clearHideTimer,
		}
	})()

	XF.NavButtons = (() =>
	{
		let showTimer = null
		let pauseScrollWatch = false
		let isShown = false
		let scrollTrigger
		let buttons = null

		const initialize = () =>
		{
			// by default only init if display-mode == standalone
			if (!XF.Feature.has('displaymodestandalone'))
			{
				return
			}

			if (buttons)
			{
				// already initialized
				return false
			}

			buttons = document.querySelectorAll('.js-navButtons');
			if (!buttons)
			{
				return false
			}

			buttons.forEach(button =>
			{
				XF.on(button, 'mouseenter focus', enter)
				XF.on(button, 'mouseleave blur', leave)
				XF.on(button, 'click', click)
			})

			XF.on(window, 'scroll', onScroll, { passive: true })

			if (window.history.length > 1)
			{
				show()
			}

			return true
		}

		const onScroll = e =>
		{
			if (pauseScrollWatch)
			{
				return
			}

			// note that Chrome on Android can heavily throttle setTimeout,
			// so use a requestAnimationFrame alternative if possible to
			// ensure this triggers when expected
			scrollTrigger = XF.requestAnimationTimeout(() =>
			{
				scrollTrigger = null

				hide()
				startShowTimer()
			}, 200)
		}

		const show = () =>
		{
			if (!isShown)
			{
				buttons.forEach(button =>
				{
					XF.Transition.addClassTransitioned(button, 'is-active')
				})
				isShown = true
			}
		}

		const hide = () =>
		{
			if (isShown)
			{
				buttons.forEach(button =>
				{
					XF.Transition.removeClassTransitioned(button, 'is-active')
				})
				isShown = false
			}
		}

		const startShowTimer = () =>
		{
			clearShowTimer()

			showTimer = setTimeout(() => show(), 500)
		}

		const clearShowTimer = () =>
		{
			clearTimeout(showTimer)
		}

		const enter = () =>
		{
			clearShowTimer()
			show()
		}

		const leave = () =>
		{
			clearShowTimer()
		}

		const click = e =>
		{
			const target = e.target
			if (
				!target.classList.contains('button--scroll') &&
				!target.closest('.button--scroll')
			)
			{
				return
			}

			pauseScrollWatch = true

			setTimeout(() =>
			{
				pauseScrollWatch = false
			}, 500)

			window.history.back()
			hide()
		}

		return {
			initialize,
			show,
			hide,
			startShowTimer,
			clearShowTimer,
		}
	})();

	// ################################## KEYBOARD SHORTCUT HANDLER ###########################################

	/**
	 * Activates keyboard shortcuts for elements based on data-xf-key attributes
	 *
	 * @type {{initialize, initializeElements}}
	 */
	XF.KeyboardShortcuts = (() =>
	{
		const shortcuts = {}, Ctrl = 1, Alt = 2, Meta = 4,

			debug = false

		const initialize = () => XF.on(document.body, 'keyup', keyEvent, { passive: true })

		const initializeElements = root =>
		{
			if (Array.isArray(root))
			{
				root.forEach(r => initializeElements(r))
				return
			}

			if (root instanceof Element && root.matches('[data-xf-key]'))
			{
				initializeElement(root)
			}

			root.querySelectorAll('[data-xf-key]').forEach(r => initializeElement(r))

			if (debug)
			{
				console.info('Registered keyboard shortcuts: %o', shortcuts)
			}
		}

		const initializeElement = el =>
		{
			// accepts a shortcut key either as 'a', 'B', etc., or a charcode with a # prefix - '#97', '#56'
			const shortcut = String(el.dataset['xfKey']), key = shortcut.substr(shortcut.lastIndexOf('+') + 1),
				charCode = key[0] === '#' ? key.substr(1) : key.toUpperCase().charCodeAt(0),
				codeInfo = shortcut.toUpperCase().split('+'),
				modifierCode = getModifierCode(codeInfo.indexOf('CTRL') !== -1, codeInfo.indexOf('ALT') !== -1, codeInfo.indexOf('META') !== -1)

			if (modifierCode)
			{
				if (XF.Keyboard.isStandardKey(charCode))
				{
					shortcuts[charCode] = shortcuts[charCode] || {}
					shortcuts[charCode][modifierCode] = el

					if (debug)
					{
						console.info('Shortcut %c%s%c registered as %s + %s for %s', 'color:red;font-weight:bold;font-size:larger', shortcut, 'color:inherit;font-weight:inherit;font-size:inherit', charCode, modifierCode, el)
					}
				}
				else
				{
					console.warn('It is not possible to specify a keyboard shortcut using this key combination (%s)', shortcut)
				}
			}
			else
			{
				shortcuts[key] = el

				if (debug)
				{
					console.info('Shortcut %c%s%c registered as %s for %s', 'color:red;font-weight:bold;font-size:larger', shortcut, 'color:inherit;font-weight:inherit;font-size:inherit', key, el)
				}
			}
		}

		const keyEvent = e =>
		{
			switch (e.key)
			{
				case 'Escape':
					XF.MenuWatcher.closeAll() // close all menus
					XF.hideTooltips()
					return

				case 'Shift':
				case 'Control':
				case 'Alt':
				case 'Meta':
					return
			}

			if (!XF.Keyboard.isShortcutAllowed(document.activeElement))
			{
				return
			}

			if (debug)
			{
				console.log('KEYUP: key:%s, which:%s (charCode from key: %s), Decoded from e.which: %s%s%s%s', e.key, e.which, e.key.charCodeAt(0), (e.ctrlKey ? 'CTRL+' : ''), (e.altKey ? 'ALT+' : ''), (e.metaKey ? 'META+' : ''), String.fromCharCode(e.which))
			}

			if (XF.hasOwn(shortcuts, e.key) && getModifierCodeFromEvent(e) == 0) // try simple mapping first
			{
				if (fireShortcut(shortcuts[e.key]))
				{
					return
				}
			}

			if (XF.hasOwn(shortcuts, e.which)) // try complex mapping next
			{
				const modifierCode = getModifierCodeFromEvent(e)

				if (XF.hasOwn(shortcuts[e.which], modifierCode))
				{
					if (fireShortcut(shortcuts[e.which][modifierCode]))
					{
						return
					}
				}
			}
		}

		const fireShortcut = target =>
		{
			if (target)
			{
				XF.NavDeviceWatcher.toggle(true)

				if (!XF.isElementVisible(target))
				{
					target.scrollIntoView(true)
				}

				if (target.matches(XF.getKeyboardInputs()))
				{
					XF.autofocus(target)
				}
				else if (target.matches('a[href]'))
				{
					target.click()
				}
				else
				{
					target.click()
				}

				return true
			}

			return false
		}

		const getModifierCode = (CtrlKey, AltKey, MetaKey) =>
		{
			if (CtrlKey)
			{
				return Ctrl
			}
			else if (AltKey)
			{
				return Alt
			}
			else if (MetaKey)
			{
				return Meta
			}

			return 0
		}

		const getModifierCodeFromEvent = event => getModifierCode(event.ctrlKey, event.altKey, event.metaKey)

		return {
			initialize,
			initializeElements,
		}
	})()

	/**
	 * Collection of methods for working with the keyboard
	 */
	XF.Keyboard = {
		/**
		 * Determines whether a keyboard shortcut can be fired with the current activeElement
		 *
		 * @param object activeElement (usually document.activeElement)
		 *
		 * @returns {boolean}
		 */
		isShortcutAllowed: activeElement =>
		{
			switch (activeElement.tagName)
			{
				case 'TEXTAREA':
				case 'SELECT':
					return false

				case 'INPUT':
					switch (activeElement.type)
					{
						case 'checkbox':
						case 'radio':
						case 'submit':
						case 'reset':
							return true
						default:
							return false
					}

				case 'BODY':
					return true

				default:
					// active element can be different in IE bail out if the active element is a child of the editor
					if (XF.browser.msie)
					{
						if (activeElement.closest('.fr-element'))
						{
							return false
						}
					}
					return activeElement.contentEditable === 'true' ? false : true
			}
		},

		isStandardKey: charcode => (charcode >= 48 && charcode <= 90),
	}

	// ################################## FORM VALIDATION HANDLER ###########################################

	/**
	 * Sets up some custom behaviour on forms so that when invalid inputs are scrolled
	 * to they are not covered by fixed headers.
	 *
	 * @type {{initialize, initializeElements}}
	 */
	XF.FormInputValidation = (() =>
	{
		let forms = {}

		const initialize = () =>
		{
			forms = Array.from(document.querySelectorAll('form:not([novalidate])'))
			prepareForms()
		}

		const initializeElements = root =>
		{
			if (Array.isArray(root))
			{
				root.forEach(r => initializeElements(r))
				return
			}

			if (root instanceof Element && root.matches('form'))
			{
				prepareForm(root)
			}

			root.querySelectorAll('form').forEach(r => prepareForm(r))
		}

		const prepareForms = () =>
		{
			if (!forms.length)
			{
				return
			}

			forms.forEach(form => prepareForm(form))
		}

		const prepareForm = form =>
		{
			form.querySelectorAll('input, textarea, select, button').forEach(input =>
			{
				XF.on(input, 'invalid', event => onInvalidInput({
					event,
					form,
					input,
				}))
			})
		}

		const onInvalidInput = (data) =>
		{
			const {
				event,
				form,
				input,
			} = data

			const first = form.querySelector(':invalid')

			if (input === first)
			{
				if (XF.isElementVisible(input))
				{
					// element is already visible so skip
					return
				}

				const offset = 100
				const overlayContainer = form.closest('.overlay-container.is-active')

				if (overlayContainer)
				{
					const inputRect = input.getBoundingClientRect()
					const containerRect = overlayContainer.getBoundingClientRect()

					overlayContainer.scrollTop = inputRect.top - containerRect.top + overlayContainer.scrollTop - offset
				}
				else
				{
					// put the input 100px from the top of the screen
					input.scrollIntoView()
					window.scrollBy(0, -offset)
				}
			}
		}

		return {
			initialize,
			initializeElements,
		}
	})()

	// ################################## NOTICE WATCHER ###########################################

	XF.NoticeWatcher = (() =>
	{
		const getBottomFixerNoticeHeight = () =>
		{
			let noticeHeight = 0
			const bottomFixers = document.querySelectorAll('.js-bottomFixTarget .notices--bottom_fixer .js-notice')

			bottomFixers.forEach(notice =>
			{
				if (window.getComputedStyle(notice).display !== 'none')
				{
					noticeHeight += notice.offsetHeight
				}
			})

			return noticeHeight
		}

		return {
			getBottomFixerNoticeHeight,
		}
	})()

	// ############################### PWA HANDLER ################################

	XF.PWA = (() =>
	{
		let _isSupported = null
		let registration
		let showNavigationIndicator = true
		let navigationTimer = null

		const getSwUrl = () =>
		{
			let serviceWorkerPath = XF.config.serviceWorkerPath
			if (serviceWorkerPath === null)
			{
				serviceWorkerPath = 'service_worker.js'
			}

			if (serviceWorkerPath && serviceWorkerPath.length)
			{
				return XF.canonicalizeUrl(serviceWorkerPath)
			}
			else
			{
				return null
			}
		}

		const initialize = () =>
		{
			if (!XF.PWA.isSupported())
			{
				return
			}

			if (XF.config.skipServiceWorkerRegistration)
			{
				registration = new Promise((resolve, reject) =>
				{
					reject(new Error('Service worker registration has been skipped'))
				})
				return
			}

			registration = navigator.serviceWorker.register(getSwUrl())
			registration
				.then(reg =>
				{
					const skipMessage = reg.active ? false : true
					updateCacheIfNeeded(XF.config.cacheKey, skipMessage)
				})
			registration
				.catch(error =>
				{
					console.error('Service worker registration failed:', error)
				})

			// remove the old service worker if registered
			navigator.serviceWorker.getRegistrations()
				.then(allRegs =>
				{
					const oldScope = XF.canonicalizeUrl('js/xf/')

					for (const k in allRegs)
					{
						if (allRegs[k].scope == oldScope)
						{
							allRegs[k].unregister()
						}
					}
				})

			XF.on(navigator.serviceWorker, 'message', event =>
			{
				const message = event.data
				if (typeof message !== 'object' || message === null)
				{
					console.error('Invalid message:', message)
					return
				}

				receiveMessage(message.type, message.payload)
			})

			if (isRunning())
			{
				XF.on(window, 'beforeunload', beforeNavigation)
				XF.on(window, 'pageshow', afterNavigation)
				XF.on(document, 'click', (event) =>
				{
					if (event.target.matches('.js-skipPwaNavIndicator'))
					{
						inhibitNavigationIndicator(event)
					}
				})
			}
		}

		const isSupported = () =>
		{
			if (_isSupported === null)
			{
				_isSupported = !!('serviceWorker' in navigator && getSwUrl())
			}

			return _isSupported
		}

		const isRunning = () =>
		{
			return (navigator.standalone || window.matchMedia('(display-mode: standalone), (display-mode: minimal-ui)').matches)
		}

		const getRegistration = () => registration

		const beforeNavigation = () =>
		{
			if (!showNavigationIndicator)
			{
				return
			}

			XF.ActionIndicator.show()

			navigationTimer = setTimeout(() => XF.ActionIndicator.hide(), 30000)
		}

		const afterNavigation = (e) =>
		{
			if (!e.persisted)
			{
				return
			}

			XF.ActionIndicator.hide()
			clearTimeout(navigationTimer)
		}

		const inhibitNavigationIndicator = () =>
		{
			showNavigationIndicator = false

			setTimeout(() =>
			{
				showNavigationIndicator = true
			}, 2000)
		}

		const sendMessage = (type, payload) =>
		{
			if (!navigator.serviceWorker.controller)
			{
				console.error('There is no active service worker')
				return
			}

			if (typeof type !== 'string' || type === '')
			{
				console.error('Invalid message type:', type)
				return
			}

			if (typeof payload === 'undefined')
			{
				payload = {}
			}
			else if (typeof payload !== 'object' || payload === null)
			{
				console.error('Invalid message payload:', payload)
				return
			}

			navigator.serviceWorker.controller.postMessage({
				type,
				payload,
			})
		}

		const messageHandlers = {}

		const receiveMessage = (type, payload) =>
		{
			if (typeof type !== 'string' || type === '')
			{
				console.error('Invalid message type:', type)
				return
			}

			if (typeof payload !== 'object' || payload === null)
			{
				console.error('Invalid message payload:', payload)
				return
			}

			const handler = messageHandlers[type]
			if (typeof handler === 'undefined')
			{
				console.error('No handler available for message type:', type)
				return
			}

			handler(payload)
		}

		const updateCacheIfNeeded = (key, skipMessage) =>
		{
			const localKey = XF.LocalStorage.get('cacheKey')
			if (localKey === key)
			{
				return false
			}

			if (!skipMessage)
			{
				sendMessage('updateCache')
			}

			XF.LocalStorage.set('cacheKey', key, true)
			return true
		}

		return {
			initialize,
			isSupported,
			isRunning,
			inhibitNavigationIndicator,
			getRegistration,
			sendMessage,
		}
	})()

	// ################################## PUSH NOTIFICATION HANDLER ###########################################

	XF.Push = (() =>
	{
		const initialize = () =>
		{
			if (!XF.Push.isSupported())
			{
				return
			}

			if (XF.config.skipPushNotificationSubscription)
			{
				return
			}

			registerWorker()
		}

		const registerWorker = (onRegisterSuccess, onRegisterError) =>
		{
			XF.PWA.getRegistration()
				.then(() =>
				{
					getSubscription()

					if (onRegisterSuccess)
					{
						onRegisterSuccess()
					}
				})
				.catch(() =>
				{
					if (onRegisterError)
					{
						onRegisterError()
					}
				})
		}

		const getSubscription = () =>
		{
			XF.PWA.getRegistration()
				.then(registration => registration.pushManager.getSubscription())
				.then(subscription =>
				{
					XF.Push.isSubscribed = !(subscription === null)

					if (XF.Push.isSubscribed)
					{
						XF.trigger(document, 'push:init-subscribed')

						// If the browser is subscribed, but there is no userId then
						// we should unsubscribe to avoid leaking notifications to
						// unauthenticated users on a shared device.
						// If the server key doesn't match, then we should unsubscribe as we'd
						// need to resubscribe with the new key.
						if (XF.config.userId && isExpectedServerKey(subscription))
						{
							XF.Push.updateUserSubscription(subscription, 'update')
						}
						else
						{
							subscription.unsubscribe()
							XF.Push.updateUserSubscription(subscription, 'unsubscribe')
						}
					}
					else
					{
						XF.trigger(document, 'push:init-unsubscribed')
					}
				})
		}

		const getPushHistoryUserIds = () => XF.LocalStorage.getJson('push_history_user_ids') || {}

		const setPushHistoryUserIds = userIds =>
		{
			XF.LocalStorage.setJson('push_history_user_ids', userIds || {})
		}

		const hasUserPreviouslySubscribed = userId =>
		{
			const userIdHistory = XF.Push.getPushHistoryUserIds()
			return XF.hasOwn(userIdHistory, userId || XF.config.userId)
		}

		const addUserToPushHistory = userId =>
		{
			const userIdHistory = XF.Push.getPushHistoryUserIds()
			userIdHistory[userId || XF.config.userId] = true
			XF.Push.setPushHistoryUserIds(userIdHistory)
		}

		const removeUserFromPushHistory = userId =>
		{
			// also remove history entry as this is an explicit unsubscribe
			const userIdHistory = XF.Push.getPushHistoryUserIds()
			delete userIdHistory[userId || XF.config.userId]
			XF.Push.setPushHistoryUserIds(userIdHistory)
		}

		let cancellingSub = null

		const handleUnsubscribeAction = (onUnsubscribe, onUnsubscribeError) =>
		{
			if (!XF.Push.isSubscribed)
			{
				return
			}

			XF.PWA.getRegistration()
				.then(registration => registration.pushManager.getSubscription())
				.then(subscription =>
				{
					if (subscription)
					{
						cancellingSub = subscription
						return subscription.unsubscribe()
					}
				})
				.catch(error =>
				{
					console.error('Error unsubscribing', error)

					if (onUnsubscribeError)
					{
						onUnsubscribeError()
					}
				})
				.then(() =>
				{
					if (cancellingSub)
					{
						XF.Push.updateUserSubscription(cancellingSub, 'unsubscribe')
					}

					XF.Push.isSubscribed = false

					if (onUnsubscribe)
					{
						onUnsubscribe()
					}
				})
		}

		const handleSubscribeAction = (suppressNotification, onSubscribe, onSubscribeError) =>
		{
			if (XF.Push.isSubscribed)
			{
				return
			}

			Notification.requestPermission().then(result =>
			{
				if (result !== 'granted')
				{
					console.error('Permission was not granted')
					return
				}

				const applicationServerKey = XF.Push.base64ToUint8(XF.config.pushAppServerKey)

				XF.PWA.getRegistration().then(registration =>
				{
					registration.pushManager
						.subscribe({
							userVisibleOnly: true,
							applicationServerKey,
						})
						.then(subscription =>
						{
							XF.Push.updateUserSubscription(subscription, 'insert')
							XF.Push.isSubscribed = true

							const options = {
								body: XF.phrase('push_enable_notification_body'),
								dir: XF.isRtl() ? 'rtl' : 'ltr',
							}
							if (XF.config.publicMetadataLogoUrl)
							{
								options['icon'] = XF.config.publicMetadataLogoUrl
							}
							if (XF.config.publicPushBadgeUrl)
							{
								options['badge'] = XF.config.publicPushBadgeUrl
							}

							if (!suppressNotification)
							{
								registration.showNotification(XF.phrase('push_enable_notification_title'), options)
							}

							if (XF.config.userId)
							{
								XF.Push.addUserToPushHistory()
							}

							if (onSubscribe)
							{
								onSubscribe()
							}
						})
						.catch(error =>
						{
							console.error('Failed to subscribe the user: ', error)

							if (onSubscribeError)
							{
								onSubscribeError()
							}
						})
				})
			})
		}

		const handleToggleAction = (onUnsubscribe, onUnsubscribeError, onSubscribe, onSubscribeError) =>
		{
			if (XF.Push.isSubscribed)
			{
				XF.Push.handleUnsubscribeAction(onUnsubscribe, onUnsubscribeError)
			}
			else
			{
				XF.Push.handleSubscribeAction(false, onSubscribe, onSubscribeError)
			}
		}

		const updateUserSubscription = (subscription, type) =>
		{
			if (type === 'update' && XF.Cookie.get('push_subscription_updated'))
			{
				return
			}

			if (XF.getApp() !== 'public')
			{
				return
			}

			if (XF.getApp() !== 'public')
			{
				return
			}

			const key = subscription.getKey('p256dh')
			const token = subscription.getKey('auth')
			const encoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0]

			const data = {
				endpoint: subscription.endpoint,
				key: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : null,
				token: token ? btoa(String.fromCharCode.apply(null, new Uint8Array(token))) : null,
				encoding,
				unsubscribed: (type === 'unsubscribe') ? 1 : 0,
				_xfResponseType: 'json',
				_xfToken: XF.config.csrf,
			}

			fetch(XF.canonicalizeUrl('index.php?misc/update-push-subscription'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify(data),
				cache: 'no-store',
			}).then((response) =>
			{
				if (!response.ok)
				{
					throw new Error('Network response was not ok.')
				}

				return response.json()
			}).then(() =>
			{
				if (type === 'update')
				{
					XF.Cookie.set('push_subscription_updated', '1')
				}
			}).catch(error =>
			{
				console.error('Error:', error)
			})
		}

		const isSupported = () => (
			XF.PWA.isSupported()
			&& XF.config.enablePush
			&& XF.config.pushAppServerKey
			&& XF.getApp() === 'public'
			&& 'PushManager' in window
			&& 'Notification' in window
		)

		const base64ToUint8 = base64String =>
		{
			const padding = '='.repeat((4 - base64String.length % 4) % 4)
			const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')

			const rawData = window.atob(base64)
			const outputArray = new Uint8Array(rawData.length)

			for (let i = 0; i < rawData.length; ++i)
			{
				outputArray[i] = rawData.charCodeAt(i)
			}

			return outputArray
		}

		const isExpectedServerKey = input =>
		{
			if (input instanceof PushSubscription)
			{
				input = input.options.applicationServerKey
			}

			if (typeof input === 'string')
			{
				return (XF.config.pushAppServerKey === input)
			}

			if (input.buffer && input.BYTES_PER_ELEMENT)
			{
				// typed array -- not exposed directly to JS
				input = input.buffer
			}
			if (!(input instanceof ArrayBuffer))
			{
				throw new Error('input must be an array buffer or convertable to it')
			}

			const serverKey = base64ToUint8(XF.config.pushAppServerKey).buffer, length = serverKey.byteLength

			if (length !== input.byteLength)
			{
				return false
			}

			const serverKeyView = new DataView(serverKey), inputView = new DataView(input)

			for (let i = 0; i < length; i++)
			{
				if (serverKeyView.getUint8(i) !== inputView.getUint8(i))
				{
					return false
				}
			}

			return true
		}

		return {
			isSubscribed: null,
			initialize,
			registerWorker,
			getPushHistoryUserIds,
			setPushHistoryUserIds,
			hasUserPreviouslySubscribed,
			addUserToPushHistory,
			removeUserFromPushHistory,
			handleUnsubscribeAction,
			handleSubscribeAction,
			handleToggleAction,
			updateUserSubscription,
			isSupported,
			base64ToUint8,
			isExpectedServerKey,
		}
	})()

	// ################################## BB CODE EXPAND WATCHER ###########################################

	XF.ExpandableContent = (() =>
	{
		const containerSel = '.js-expandWatch'

		const watch = () =>
		{
			XF.onDelegated(document, 'click', '.js-expandLink', e =>
			{
				XF.Transition.addClassTransitioned(e.target.closest(containerSel), 'is-expanded', XF.layoutChange)
			})

			XF.on(window, 'resize', () => checkSizing(document), { passive: true })
			XF.on(document, 'embed:loaded', () => checkSizing(document))
		}

		const checkSizing = el =>
		{
			el.querySelectorAll(containerSel + ':not(.is-expanded)').forEach(container =>
			{
				const content = container.querySelector('.js-expandContent')

				if (!content)
				{
					return
				}

				let timer
				let delay = 0
				const check = () =>
				{
					const scroll = content.scrollHeight, offset = content.offsetHeight

					if (scroll == 0 || offset == 0)
					{
						if (delay > 2000)
						{
							return
						}
						if (timer)
						{
							clearTimeout(timer)
							delay += 200
						}
						timer = setTimeout(check, delay)
						return
					}

					if (scroll > offset + 1) // +1 resolves a Chrome rounding issue
					{
						container.classList.add('is-expandable')
					}
					else
					{
						container.classList.remove('is-expandable')
					}
				}

				check()

				if (!XF.DataStore.get(container, 'expand-check-triggered'))
				{
					XF.DataStore.set(container, 'expand-check-triggered', true)
					container.querySelectorAll('img').forEach(image => XF.on(image, 'load', check, { once: true }))

					if (window.MutationObserver)
					{
						let observer, mutationTimeout, allowMutationTrigger = true

						const mutationTrigger = () =>
						{
							allowMutationTrigger = false
							check()

							// prevent triggers for a little bit after this so we limit thrashing
							setTimeout(() =>
							{
								allowMutationTrigger = true
							}, 100)
						}

						observer = new MutationObserver(mutations =>
						{
							if (container.classList.contains('is-expanded'))
							{
								observer.disconnect()
								return
							}

							if (!allowMutationTrigger)
							{
								return
							}

							if (mutationTimeout)
							{
								clearTimeout(mutationTimeout)
							}
							mutationTimeout = setTimeout(mutationTrigger, 200)
						})
						observer.observe(container, {
							attributes: true,
							childList: true,
							subtree: true,
						})
					}
				}
			})
		}

		return {
			watch,
			checkSizing,
		}
	})()

	// ################################## UNFURL LOADER WATCHER ###########################################

	XF.UnfurlLoader = (() =>
	{
		let unfurlIds = []
		let pending = false
		let pendingIds = []

		const activateContainer = container =>
		{
			const unfurls = container.querySelectorAll('.js-unfurl')
			if (!unfurls.length)
			{
				return
			}

			unfurls.forEach(unfurl =>
			{
				if (
					unfurl.dataset.pending === 'false' ||
					XF.DataStore.get(unfurl, 'pending-seen')
				)
				{
					return
				}

				XF.DataStore.set(unfurl, 'pending-seen', true)

				const id = unfurl.dataset.resultId
				if (pending)
				{
					pendingIds.push(id)
				}
				else
				{
					unfurlIds.push(id)
				}
			})

			unfurl()
		}

		const unfurl = async () =>
		{
			if (!unfurlIds.length || pending)
			{
				return
			}

			/**
			 * @param {Response} response
			 */
			const bufferResponses = async response =>
			{
				let body = ''
				const reader = response.body.getReader()
				const decoder = new TextDecoder()

				while (true)
				{
					const { done, value } = await reader.read()
					if (done)
					{
						body
							.split('\n')
							.filter(r => r.length !== 0)
							.forEach(r =>
							{
								XF.UnfurlLoader.handleResponse(JSON.parse(r))
							})
						break
					}

					body += decoder.decode(value)
					if (!body.includes('\n'))
					{
						// no line break, keep buffering
						continue
					}

					const responses = body.split('\n')
					body = responses.pop()
					responses
						.filter(r => r.length !== 0)
						.forEach(r =>
						{
							XF.UnfurlLoader.handleResponse(JSON.parse(r))
						})
				}
			}

			pending = true

			try
			{
				const response = await fetch(
					XF.canonicalizeUrl('unfurl.php'),
					{
						method: 'POST',
						headers: {
							'Accept': 'application/json',
							'Content-Type': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
						body: JSON.stringify({ result_ids: unfurlIds }),
						cache: 'no-store',
					},
				)
				if (!response.ok)
				{
					throw new Error('Network response was not ok.')
				}

				bufferResponses(response)
			}
			finally
			{
				unfurlIds = []
				pending = false

				if (pendingIds)
				{
					unfurlIds = pendingIds
					pendingIds = []
					setTimeout(unfurl, 0)
				}
			}
		}

		const handleResponse = data =>
		{
			const unfurl = document.querySelector(
				`.js-unfurl[data-result-id="${data.result_id}"]`,
			)
			if (!unfurl)
			{
				return
			}

			if (data.success)
			{
				XF.setupHtmlInsert(data.html, html =>
				{
					unfurl.replaceWith(html)
				})
			}
			else
			{
				const link = unfurl.querySelector('.js-unfurl-title a')
				link.textContent = unfurl.dataset.url
				link.classList.add('bbCodePlainUnfurl')
				link.classList.remove('fauxBlockLink-blockLink')
				unfurl.replaceWith(link)
			}
		}

		return {
			activateContainer,
			unfurl,
			handleResponse,
		}
	})()

	// ############################### ELEMENT EVENT HANDLER SYSTEM ########################################

	/**
	 * This system allows elements to have a trigger event attached (eg: click, focus etc.),
	 * such that the code for handling the event is only attached at the time that the event
	 * is actually triggered, making for fast page initialization time.
	 */
	XF.Event = (() =>
	{
		const initElement = (target, eventType, e) =>
		{
			const handlerList = target.dataset[XF.toCamelCase('xf-' + eventType)].split(' ') || [],
				handlerObjects = XF.DataStore.get(target, 'xf-' + eventType + '-handlers') || {}

			let identifier, Obj, i

			for (i = 0; i < handlerList.length; i++)
			{
				identifier = handlerList[i]
				if (!identifier.length)
				{
					continue
				}

				if (!handlerObjects[identifier])
				{
					Obj = mapper.getObjectFromIdentifier(identifier)
					if (!Obj)
					{
						console.error('Could not find %s handler for %s', eventType, identifier)
						continue
					}

					const jsonOpts = JSON.parse(target.getAttribute(`data-xf-${ identifier }`) || '{}')
					handlerObjects[identifier] = new Obj(target, jsonOpts)
				}

				if (e && handlerObjects[identifier]._onEvent(e) === false)
				{
					break
				}
			}

			XF.DataStore.set(target, 'xf-' + eventType + '-handlers', handlerObjects)

			return handlerObjects
		}
		const mapper = new XF.ClassMapper()

		const eventsWatched = {}, pointerDataKey = 'xfPointerType'

		const watch = eventType =>
		{
			eventType = String(eventType).toLowerCase()

			const isValidTarget = (e, target) =>
			{
				if (!target)
				{
					target = e.currentTarget
				}

				if (!target || !target.getAttribute)
				{
					// not an element so can't have a handler
					return false
				}

				if (target.matches('a'))
				{
					// abort if the event has a modifier key
					if (e.ctrlKey || e.shiftKey || e.altKey || e.metaKey)
					{
						return false
					}

					// abort if the event is a middle or right-button click
					if (e.which > 1)
					{
						return false
					}
				}

				if (target.closest('[contenteditable=true]'))
				{
					return false
				}

				return true
			}

			if (!XF.hasOwn(eventsWatched, eventType))
			{
				eventsWatched[eventType] = true

				XF.on(document, eventType, e =>
				{
					const selector = `[data-xf-${ eventType }]`
					const target = e.target.closest(selector)

					if (target && isValidTarget(e, target))
					{
						const type = XF.DataStore.get(target, pointerDataKey)

						e.xfPointerType = e.pointerType || type || ''

						initElement(target, eventType, e)
					}
				})

				XF.on(document, 'pointerdown', e =>
				{
					const selector = `[data-xf-${ eventType }]`
					const target = e.target.closest(selector)

					if (target && isValidTarget(e, target))
					{
						XF.DataStore.set(target, pointerDataKey, e.pointerType)
					}
				}, { passive: true })
			}
		}

		const getElementHandler = (el, handlerName, eventType) =>
		{
			let handlers = XF.DataStore.get(`xf-${ eventType }-handlers`)

			if (!handlers)
			{
				handlers = XF.Event.initElement(el, eventType)
			}

			if (handlers && handlers[handlerName])
			{
				return handlers[handlerName]
			}
			else
			{
				return null
			}
		}

		const AbstractHandler = XF.create({
			initialized: false,
			eventType: 'click',
			eventNameSpace: null,
			target: null,
			options: {},

			__construct (target, options)
			{
				this.target = target
				this.options = XF.applyDataOptions(this.options, target.dataset, options)
				this.eventType = this.eventType.toLowerCase()

				if (!this.eventNameSpace)
				{
					throw new Error(`Please provide an eventNameSpace for your extended ${ this.eventType } handler class`)
				}

				this._init()
			},

			/**
			 * 'protected' wrapper function for init(),
			 *  containing Before/AfterInit events
			 */
			_init ()
			{
				let returnValue = false

				const beforeInitEvent = XF.customEvent(`xf-${ this.eventType }:before-init.${ this.eventNameSpace }`)
				XF.trigger(this.target, beforeInitEvent)

				if (!beforeInitEvent.defaultPrevented)
				{
					returnValue = this.init()

					const afterInitEvent = XF.customEvent(`xf-${ this.eventType }:after-init.${ this.eventNameSpace }`)
					XF.trigger(this.target, afterInitEvent)
				}

				this.initialized = true

				return returnValue
			},

			_onEvent (e)
			{
				let returnValue = null

				const beforeEvent = XF.customEvent(`xf-${ this.eventType }:before-${ this.eventType }.${ this.eventNameSpace }`)
				XF.trigger(this.target, beforeEvent)

				if (!beforeEvent.defaultPrevented)
				{
					if (typeof this[this.eventType] == 'function')
					{
						returnValue = this[this.eventType](e)
					}
					else if (typeof this.onEvent == 'function')
					{
						returnValue = this.onEvent(e)
					}
					else
					{
						console.error('You must provide a %1$s(e) method for your %1$s event handler', this.eventType, this.eventNameSpace)
						e.preventDefault()
						e.stopPropagation()
						return
					}

					const afterEvent = XF.customEvent(`xf-${ this.eventType }:after-${ this.eventType }.${ this.eventNameSpace }`)
					XF.trigger(this.target, afterEvent)
				}

				return null
			},

			// methods to be overridden by inheriting classes
			init ()
			{
				console.error('This is the abstract init method for XF.%s, which must be overridden.', this.eventType, this.eventNameSpace)
			},
		})

		return {
			watch,
			initElement,
			getElementHandler,
			register: (eventType, identifier, className) =>
			{
				XF.Event.watch(eventType)
				mapper.add(identifier, className)
				XF.LazyHandlerLoader.checkLazyRegistration(identifier)
			},
			extend: (identifier, extension) =>
			{
				mapper.extend(identifier, extension)
			},
			getObjectFromIdentifier: identifier => mapper.getObjectFromIdentifier(identifier),
			newHandler: extend => XF.extend(AbstractHandler, extend),
			AbstractHandler,
		}
	})()

	// ################################## ELEMENT HANDLER SYSTEM ##########################################

	/**
	 * This system allows elements with data-xf-init to be initialized at page load time
	 */
	XF.Element = (() =>
	{
		const mapper = new XF.ClassMapper()

		const applyHandler = (el, handlerId, options) =>
		{
			const handlers = XF.DataStore.get(el, 'xf-element-handlers') || {}
			if (handlers[handlerId])
			{
				return handlers[handlerId]
			}

			const Obj = mapper.getObjectFromIdentifier(handlerId)
			if (!Obj)
			{
				return null
			}

			const obj = new Obj(el, options || {})

			handlers[handlerId] = obj
			XF.DataStore.set(el, 'xf-element-handlers', handlers)

			obj.init()

			return obj
		}

		const getHandler = (el, handlerId) =>
		{
			let handlers = XF.DataStore.get(el, 'xf-element-handlers')
			if (!handlers)
			{
				initializeElement(el)
				handlers = XF.DataStore.get(el, 'xf-element-handlers')
			}

			if (handlers && handlers[handlerId])
			{
				return handlers[handlerId]
			}
			else
			{
				return null
			}
		}

		const initializeElement = el =>
		{
			if (!el || !el.getAttribute)
			{
				// not an element -- probably a text node
				return
			}

			const init = el.getAttribute('data-xf-init')
			if (!init)
			{
				return
			}

			let parts = init.split(' '), len = parts.length, handlerId
			for (let i = 0; i < len; i++)
			{
				handlerId = parts[i]
				if (!handlerId)
				{
					continue
				}

				const jsonOpts = JSON.parse(el.getAttribute(`data-xf-${ handlerId }`) || '{}')
				applyHandler(el, handlerId, jsonOpts)
			}
		}

		const initialize = root =>
		{
			if (root.nodeType === Node.ELEMENT_NODE && root.matches('[data-xf-init]'))
			{
				initializeElement(root)
			}

			// Then initialize all child elements
			let elements = root.querySelectorAll('[data-xf-init]')
			elements.forEach(initializeElement)
		}

		const AbstractHandler = XF.create({
			target: null,
			options: {},

			__construct (target, options)
			{
				this.target = target
				this.options = XF.applyDataOptions(this.options, target.dataset, options)
			},

			init ()
			{
				console.error('This is the abstract init method for XF.Element, which should be overridden.')
			},

			getOption (option)
			{
				return this.options[option]
			},
		})

		return {
			register: (identifier, className) =>
			{
				mapper.add(identifier, className)
				XF.LazyHandlerLoader.checkLazyRegistration(identifier)
			},
			extend: (identifier, extension) =>
			{
				mapper.extend(identifier, extension)
			},
			getObjectFromIdentifier: identifier => mapper.getObjectFromIdentifier(identifier),
			initialize,
			initializeElement,
			applyHandler,
			getHandler,
			newHandler: extend => XF.extend(AbstractHandler, extend),

			AbstractHandler,
		}
	})()

	XF.AutoCompleteResults = XF.create({
		selectedResult: 0,
		results: false,
		scrollWatchers: null,
		resultsVisible: false,
		resizeBound: false,
		headerHtml: null,
		options: {},

		__construct (options)
		{
			this.options = XF.extendObject({
				onInsert: null,
				clickAttacher: null,
				beforeInsert: null,
				insertMode: 'text',
				displayTemplate: '{{{icon}}}{{{text}}}',
				wrapperClasses: '',
			}, options)
		},

		isVisible ()
		{
			return this.resultsVisible
		},

		hideResults ()
		{
			this.resultsVisible = false

			if (this.results)
			{
				XF.display(this.results, 'none')
			}
			this.stopScrollWatching()
		},

		stopScrollWatching ()
		{
			if (this.scrollWatchers)
			{
				this.scrollWatchers.forEach(watcher => XF.off(watcher, 'scroll.autocomplete'))
				this.scrollWatchers = null
			}
		},

		addHeader (headerHtml)
		{
			this.headerHtml = headerHtml
		},

		showResults (value, results, target, positionData)
		{
			if (!results)
			{
				this.hideResults()
				return
			}

			if (!this.results)
			{
				this.results = this.createResultWrapper()
			}

			this.resultsVisible = false
			XF.display(this.results, 'none')

			this.results.innerHTML = ''
			for (const result of Object.values(results))
			{
				const resultItem = this.createResultItem(result, value)
				this.results.append(resultItem)
			}

			if (!this.results.children.length)
			{
				return
			}

			const header = this.createResultHeader()
			if (header)
			{
				this.results.prepend(header)
			}

			this.prepareResults(target, positionData)
			XF.display(this.results)
			this.resultsVisible = true
		},

		createResultWrapper ()
		{
			const wrapper = XF.createElement('ul', {
				className: 'autoCompleteList',
			})

			if (this.options.wrapperClasses)
			{
				wrapper.classList.add(...this.options.wrapperClasses.split(' '))
			}

			wrapper.setAttribute('role', 'listbox')

			return wrapper
		},

		createResultHeader ()
		{
			if (!this.headerHtml)
			{
				return null
			}

			return XF.createElement('li', {
				className: 'menu-header menu-header--small',
				unselectable: 'on',
				innerHTML: this.headerHtml
			})
		},

		createResultItem (result, value)
		{
			const listItem = XF.createElement('li', {
				unselectable: 'on',
				role: 'option',
				style: { cursor: 'pointer' }
			})

			listItem.innerHTML = Mustache.render(
				this.options.displayTemplate,
				this.getResultItemParams(result, value)
			)

			const textValue = typeof result === 'string' ? result : result.text
			XF.DataStore.set(listItem, 'insertText', textValue)
			XF.DataStore.set(listItem, 'insertHtml', result.html || '')

			XF.on(listItem, 'mouseenter', this.resultMouseEnter.bind(this))

			if (this.options.clickAttacher)
			{
				this.options.clickAttacher(listItem, this.resultClick.bind(this))
			}
			else
			{
				XF.on(listItem, 'click', this.resultClick.bind(this))
			}

			return listItem
		},

		getResultItemParams (result, value)
		{
			const params = {
				icon: '',
				text: '',
				desc: '',
				textPlain: '',
				descPlain: '',
			}

			let textValue
			if (typeof result === 'string')
			{
				textValue = result
				params.text = XF.htmlspecialchars(result)
			}
			else
			{
				textValue = result.text
				params.text = XF.htmlspecialchars(result.text)

				if (typeof result.desc !== 'undefined')
				{
					params.desc = XF.htmlspecialchars(result.desc)
				}

				if (typeof result.icon !== 'undefined')
				{
					params.icon = XF.createElement('img', {
						className: 'autoCompleteList-icon',
						src: XF.htmlspecialchars(result.icon),
					})
				}
				else if (typeof result.iconHtml !== 'undefined')
				{
					params.icon = XF.createElement('span', {
						className: 'autoCompleteList-icon',
						innerHTML: result.iconHtml,
					})
				}

				if (params.icon)
				{
					params.icon = params.icon.outerHTML
				}

				if (result.extraParams)
				{
					for (let [key, value] of Object.entries(result.extraParams))
					{
						const isHtml = key.match(/Html$/)
						key = isHtml ? key.replace(/Html$/, '') : key
						value = isHtml ? value : XF.htmlspecialchars(value)
						params[key] = value
					}
				}
			}

			params.textPlain = params.text
			params.descPlain = params.desc

			params.text = this.highlightResultText(params.text, value)
			params.desc = this.highlightResultText(params.desc, value)

			return params
		},

		highlightResultText (text, value)
		{
			const pattern = new RegExp(
				'(' + XF.regexQuote(XF.htmlspecialchars(value)) + ')',
				'i'
			)
			return text.replace(pattern, match => `<strong>${match}</strong>`)
		},

		prepareResults (target, positionData)
		{
			if (!this.results.connected)
			{
				document.body.append(this.results)
				XF.setRelativeZIndex(this.results, target, 1)
			}

			this.results.style.top = ''
			this.results.style.left = ''
			this.results.style.right = ''
			this.results.style.bottom = ''

			const setPositioning = (el, cssPosition) =>
			{
				if (XF.isFunction(cssPosition))
				{
					cssPosition = cssPosition(this.results, target)
				}

				if (!cssPosition)
				{
					const rect = target.getBoundingClientRect()
					const offset = {
						top: rect.top + window.scrollY,
						left: rect.left + window.scrollX,
					}

					cssPosition = {
						top: offset.top + target.offsetHeight + 'px',
						left: offset.left + 'px',
					}

					if (XF.isRtl())
					{
						cssPosition.right = (document.documentElement.clientWidth - offset.left - target.offsetWidth) + 'px'
						cssPosition.left = 'auto'
					}
				}

				for (let prop in cssPosition)
				{
					el.style[prop] = cssPosition[prop]
				}

				return cssPosition
			}

			// if this is in a scrollable area, watch anything scrollable
			this.stopScrollWatching()
			const scrollWatchers = []
			let parentElement = target.parentElement

			while (parentElement)
			{
				const style = window.getComputedStyle(parentElement)
				if (style.overflowX === 'scroll' || style.overflowX === 'auto')
				{
					scrollWatchers.push(parentElement)
				}
				parentElement = parentElement.parentElement
			}

			if (scrollWatchers.length)
			{
				scrollWatchers.forEach(element =>
				{
					XF.on(element, 'scroll.autocomplete', () =>
					{
						setPositioning(this.results, positionData)
					})
				})
				this.scrollWatchers = scrollWatchers
			}

			this.results.style.position = 'absolute'
			setPositioning(this.results, positionData)

			this.selectResult(0, true)
		},

		resultClick (e)
		{
			e.stopPropagation()

			const hide = this.insertResult(
				this.getResultText(e.currentTarget),
				e.currentTarget,
				e
			)

			if (hide)
			{
				this.hideResults()
			}
		},

		resultMouseEnter (e)
		{
			const currentIndex = Array.from(e.currentTarget.parentNode.children).indexOf(e.currentTarget)
			this.selectResult(currentIndex, true)
		},

		selectResult (shift, absolute)
		{
			if (!this.results)
			{
				return
			}

			if (absolute)
			{
				this.selectedResult = shift
			}
			else
			{
				this.selectedResult += shift
			}

			const sel = this.selectedResult
			const children = Array.from(this.results.children)

			children.forEach((child, i) =>
			{
				if (i == sel)
				{
					child.classList.add('is-selected')
				}
				else
				{
					child.classList.remove('is-selected')
				}
			})

			if (sel < 0 || sel >= children.length)
			{
				this.selectedResult = -1
			}
		},

		insertSelectedResult (e)
		{
			let res, ret = false

			if (!this.resultsVisible)
			{
				return false
			}

			let hide = true

			if (this.selectedResult >= 0)
			{
				res = this.results.children[this.selectedResult]
				if (res)
				{
					let resultText = this.getResultText(res)

					if (this.options.beforeInsert)
					{
						resultText = this.options.beforeInsert(resultText, res)
					}
					hide = this.insertResult(resultText, res, e)
					ret = true
				}
			}

			if (hide)
			{
				this.hideResults()
			}

			return ret
		},

		insertResult (value, res, e)
		{
			if (this.options.onInsert)
			{
				return this.options.onInsert(value, res, e) !== false
			}

			return true
		},

		getResultText (el)
		{
			let text

			switch (this.options.insertMode)
			{
				case 'text':
					text = XF.DataStore.get(el, 'insertText')
					break

				case 'html':
					text = XF.DataStore.get(el, 'insertHtml')
					break
			}

			return text
		},
	})

	XF.AutoCompleter = XF.create({
		options: {
			url: null,
			method: 'GET',
			idleWait: 200,
			minLength: 2,
			at: '@',
			keepAt: true,
			insertMode: 'text',
			displayTemplate: '{{{icon}}}{{{text}}}',
			beforeInsert: null,
			suffixEd: '\u00a0',
			suffix: ' ',
		},

		input: null,
		ed: null,

		results: null,
		visible: false,
		idleTimer: null,
		pendingQuery: '',

		__construct (input, options, editor)
		{
			this.options = XF.extendObject({}, this.options, options)
			this.input = input
			this.ed = editor

			if (!this.options.url)
			{
				console.error('No URL option passed in to XF.AutoCompleter.')
				return
			}

			if (typeof this.options.at != 'string' || this.options.at.length > 1)
			{
				console.error('The \'at\' option should be a single character string.')
			}

			this.init()
		},

		init ()
		{
			const resultOpts = {
				onInsert: result =>
				{
					this.insertResult(result)
				},
				beforeInsert: this.options.beforeInsert,
				insertMode: this.options.insertMode,
				displayTemplate: this.options.displayTemplate,
			}

			if (this.ed)
			{
				resultOpts['clickAttacher'] = (li, f) =>
				{
					const $li = this.ed.$(li)
					this.ed.events.bindClick($li, false, (e) =>
					{
						e.currentTarget = li
						f(e)
					})
				}
			}

			this.results = new XF.AutoCompleteResults(resultOpts)

			if (this.ed)
			{
				this.ed.events.on('keydown', this.keydown.bind(this), true)
				this.ed.events.on('keyup', this.keyup.bind(this), true)
				this.ed.events.on('click blur', this.blur.bind(this))

				XF.on(this.ed.$wp[0], 'scroll', this.blur.bind(this), { passive: true })
			}
			else
			{
				XF.on(this.input, 'keydown', this.keydown.bind(this))
				XF.on(this.input, 'keyup', this.keyup.bind(this))
				XF.on(this.input, 'click', this.blur.bind(this))
				XF.on(this.input, 'blur', this.blur.bind(this))
				XF.on(document, 'scroll', this.blur.bind(this), { passive: true })
			}
		},

		keydown (e)
		{
			if (!this.visible)
			{
				return
			}

			switch (e.key)
			{
				case 'ArrowDown':
					this.results.selectResult(1)
					e.preventDefault()
					e.stopPropagation()
					break

				case 'ArrowUp':
					this.results.selectResult(-1)
					e.preventDefault()
					e.stopPropagation()
					break

				case 'Escape':
					this.hide()
					e.preventDefault()
					e.stopPropagation()
					break

				case 'Enter':
				case 'Tab':
					if (this.visible)
					{
						this.results.insertSelectedResult(e)
						e.preventDefault()
						e.stopPropagation()
						return false
					}
					break
			}
		},

		keyup (e)
		{
			if (this.visible)
			{
				switch (e.key)
				{
					case 'ArrowDown':
					case 'ArrowUp':
					case 'Enter':
					case 'Tab': // tab
						return
				}
			}

			this.hide()

			if (this.idleTimer)
			{
				clearTimeout(this.idleTimer)
			}
			this.idleTimer = setTimeout(this.lookForMatch.bind(this), this.options.idleWait)
		},

		blur ()
		{
			if (!this.visible)
			{
				return
			}

			// timeout ensures that clicks still register
			setTimeout(this.hide.bind(this), 250)
		},

		lookForMatch ()
		{
			const match = this.getCurrentMatchInfo()
			if (match)
			{
				this.foundMatch(match.query)
			}
			else
			{
				this.hide()
			}
		},

		getCurrentMatchInfo ()
		{
			let selection, textNode, text

			if (this.ed)
			{
				selection = this.ed.selection.ranges(0)
				if (!selection || !selection.collapsed)
				{
					return null
				}

				const focus = selection.endContainer
				if (!focus || focus.nodeType !== Node.TEXT_NODE)
				{
					// expected to be a text node
					return null
				}

				textNode = focus
				text = focus.nodeValue.substring(0, selection.endOffset)
			}
			else
			{
				this.input.focus()

				selection = this.getSelection(this.input)

				if (!selection || selection.end <= 1)
				{
					return false
				}

				text = this.input.value.substring(0, selection.end)
			}

			const lastAt = text.lastIndexOf(this.options.at)
			if (lastAt === -1) // no 'at'
			{
				return null
			}

			if (lastAt === 0 || text.substr(lastAt - 1, 1).match(/(\s|[\](,]|--)/))
			{
				const afterAt = text.substr(lastAt + 1)
				if (!afterAt.match(/\s/) || afterAt.length <= 15)
				{
					return {
						text,
						textNode,
						start: lastAt,
						query: afterAt.replace(new RegExp(String.fromCharCode(160), 'g'), ' '),
						range: selection,
					}
				}
			}

			return null
		},

		getSelection (input)
		{
			const start = input.selectionStart
			const end = input.selectionEnd
			const length = end - start
			const text = input.value.substring(start, end)

			return {
				start,
				end,
				length,
				text,
			}
		},

		foundMatch (query)
		{
			if (this.pendingQuery === query)
			{
				return
			}

			this.pendingQuery = query

			if (query.length >= this.options.minLength && query.substr(0, 1) !== '[')
			{
				this.getPendingQueryOptions()
			}
		},

		getPendingQueryOptions ()
		{
			XF.ajax(this.options.method, this.options.url, { q: this.pendingQuery }, this.handlePendingQueryOptions.bind(this), {
				global: false,
				error: false,
			})
		},

		handlePendingQueryOptions (data)
		{
			const current = this.getCurrentMatchInfo()

			if (!data.q || !current || data.q !== current.query)
			{
				return
			}

			if (data.results && data.results.length)
			{
				this.show(data.q, data.results)
			}
			else
			{
				this.hide()
			}
		},

		insertResult (result)
		{
			this.hide()

			const matchInfo = this.getCurrentMatchInfo()
			if (!matchInfo)
			{
				return
			}

			const afterAtPos = matchInfo.start + 1, range = matchInfo.range

			if (this.ed)
			{
				this.ed.selection.save()

				XF.EditorHelpers.focus(this.ed)

				const node = matchInfo.textNode
				const text = node.nodeValue
				const suffix = this.options.suffixEd
				let insert

				const insertRef = node.splitText(this.options.keepAt ? afterAtPos : afterAtPos - 1)
				insertRef.textContent = text.substr(afterAtPos + matchInfo.query.length)

				if (this.options.insertMode === 'html')
				{
					insert = XF.createElementFromString(result + suffix)
				}
				else
				{
					insert = document.createTextNode(result + suffix)
				}

				insertRef.parentNode.insertBefore(insert, insertRef)

				node.parentNode.normalize()

				this.ed.selection.restore()
			}
			else
			{
				const input = this.input
				const insert = result + this.options.suffix
				XF.autofocus(input)

				if (afterAtPos !== -1)
				{
					input.selectionStart = matchInfo.start
					input.selectionEnd = range.end

					XF.replaceSelectedText(input, (this.options.keepAt ? this.options.at : '') + insert)
				}
			}
		},

		show (val, results)
		{
			const matchInfo = this.getCurrentMatchInfo()
			const input = this.input
			const inputDimensions = XF.dimensions(input)

			if (!matchInfo)
			{
				return
			}

			this.visible = true

			if (this.ed)
			{
				const range = matchInfo.range

				this.results.showResults(val, results, input, results =>
				{
					const startRange = range.cloneRange()

					// Set the range to start before the @ and cover it. This works around a problem where the @ is the
					// first character on the line and when the cursor is before it, it's on the previous line.
					startRange.setStart(matchInfo.textNode, matchInfo.start)
					startRange.setEnd(matchInfo.textNode, matchInfo.start + 1)

					const rect = startRange.getBoundingClientRect()

					return this.getResultPositionForSelection(rect.left, rect.bottom, range.getBoundingClientRect().left, results, inputDimensions)
				})
			}
			else
			{
				this.results.showResults(val, results, input, results =>
				{
					let div = document.createElement('div'),
						computedCss = window.getComputedStyle(input),
						applyCss = ''

					for (const name of computedCss)
					{
						applyCss += `${ name }: ${ computedCss.getPropertyValue(name) }; `
					}

					div.style.cssText = applyCss
					div.style.position = 'absolute'
					div.style.height = ''
					div.style.width = `${ input.offsetWidth }px`
					div.style.opacity = '0'
					div.style.top = '0'
					div.style.left = '-9999px'

					div.textContent = input.value
					document.body.appendChild(div)

					const testRange = document.createRange()

					testRange.setStart(div.firstChild, matchInfo.start)
					testRange.setEnd(div.firstChild, matchInfo.start + 1)

					let rect = testRange.getBoundingClientRect(), divDimensions = XF.dimensions(div), startLeft,
						startBottom, endLeft

					startLeft = inputDimensions.left + (rect.left - divDimensions.left)
					startBottom = inputDimensions.top + (rect.bottom - divDimensions.top)

					testRange.setStart(div.firstChild, matchInfo.start + 1 + matchInfo.query.length)
					testRange.setEnd(div.firstChild, matchInfo.start + 1 + matchInfo.query.length)
					rect = testRange.getBoundingClientRect()

					endLeft = inputDimensions.left + (rect.left - divDimensions.left)

					document.body.removeChild(div)

					return this.getResultPositionForSelection(startLeft, startBottom, endLeft, results, inputDimensions)
				})
			}
		},

		getResultPositionForSelection (startX, startY, endX, results, inputDimensions)
		{
			const resultsWidth = results.offsetWidth
			const targetTop = startY + window.scrollY + 3
			let targetLeft = startX

			if (targetLeft + resultsWidth > inputDimensions.right)
			{
				targetLeft = endX - resultsWidth
			}

			if (targetLeft < inputDimensions.left)
			{
				targetLeft = inputDimensions.left
			}

			return {
				top: `${ targetTop }px`,
				left: `${ targetLeft }px`,
			}
		},

		hide ()
		{
			if (this.visible)
			{
				this.visible = false
				this.results.hideResults()
			}
		},
	})

	class XF_CustomEvent extends Event
	{
		constructor (type, options = {})
		{
			if (options['bubbles'] === undefined)
			{
				options['bubbles'] = true
			}

			if (options['cancelable'] === undefined)
			{
				options['cancelable'] = true
			}

			super(type, options)

			delete options['bubbles']
			delete options['cancelable']

			for (const key in options)
			{
				this[key] = options[key]
			}
		}
	}

	XF.pageDisplayTime = Date.now()

	// defer onload callback until the config object is available
	XF.ready(XF.onPageLoad)

	XF.on(window, 'pageshow', () =>
	{
		if (!XF.pageDisplayTime || Date.now() > XF.pageDisplayTime)
		{
			XF.pageDisplayTime = Date.now()
		}
	})
})(window, document)
