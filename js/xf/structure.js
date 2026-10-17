((window, document) =>
{
	'use strict'

	// ################################## INSERTER HANDLER ###########################################

	XF.InserterClick = XF.Event.newHandler({
		eventNameSpace: 'XFInserterClick',
		options: XF.extendObject(true, {}, XF._baseInserterOptions),

		inserter: null,

		init ()
		{
			this.inserter = new XF.Inserter(this.target, this.options)
		},

		click (e)
		{
			this.inserter.onEvent(e)
		},
	})

	XF.InserterFocus = XF.Element.newHandler({
		options: XF.extendObject(true, {}, XF._baseInserterOptions),

		inserter: null,

		init ()
		{
			this.inserter = new XF.Inserter(this.target, this.options)
			XF.on(this.target, 'focus', this.inserter.onEvent.bind(this), { once: true })
		},
	})

	XF.MenuProxy = XF.Event.newHandler({
		eventNameSpace: 'XFMenuProxy',
		options: {
			trigger: null,
		},

		trigger: null,

		init ()
		{
			this.trigger = XF.findRelativeIf(this.options.trigger, this.target)
			if (!this.trigger)
			{
				throw new Error('Specified menu trigger not found')
			}
		},

		click (e)
		{
			setTimeout(() =>
			{
				XF.trigger(this.trigger, XF.customEvent('click', { originalEvent: e }))
			}, 0)
		},
	})

	// ################################## RESPONSIVE DATE LIST ###########################################

	XF.ResponsiveDataList = XF.Element.newHandler({
		options: {
			headerRow: '.dataList-row--header',
			headerCells: 'th, td',
			rows: '.dataList-row:not(.dataList-row--subSection, .dataList-row--header)',
			rowCells: 'td',
			triggerWidth: 'narrow',
		},

		headerRow: null,
		headerText: [],
		rows: null,
		isResponsive: false,

		init ()
		{
			const headerRow = this.target.querySelector(this.options.headerRow)

			if (headerRow)
			{
				const headerText = []

				headerRow.querySelectorAll(this.options.headerCells).forEach(cell =>
				{
					const text = cell.textContent
					const colspan = parseInt(cell.getAttribute('colspan'), 10)
					headerText.push(text.trim())

					if (colspan > 1)
					{
						for (let i = 1; i < colspan; i++)
						{
							headerText.push('')
						}
					}
				})

				this.headerRow = headerRow
				this.headerText = headerText
			}

			this.rows = this.target.querySelectorAll(this.options.rows)
			this.process()

			XF.on(document, 'breakpoint:change', this.process.bind(this))
		},

		process ()
		{
			const triggerable = XF.Breakpoint.isAtOrNarrowerThan(this.options.triggerWidth)

			if ((triggerable && this.isResponsive) || (!triggerable && !this.isResponsive))
			{
				// no action needed
				return
			}

			if (triggerable)
			{
				this.apply()
			}
			else
			{
				this.remove()
			}
		},

		apply ()
		{
			Array.from(this.rows).forEach(row => this.processRow(row, true))

			this.target.classList.add('dataList--responsive')

			if (this.headerRow)
			{
				this.headerRow.classList.add('dataList-row--headerResponsive')
			}

			this.isResponsive = true
		},

		remove ()
		{
			Array.from(this.rows).forEach(row => this.processRow(row, false))

			this.target.classList.remove('dataList--responsive')

			if (this.headerRow)
			{
				this.headerRow.classList.remove('dataList-row--headerResponsive')
			}

			this.isResponsive = false
		},

		processRow (row, apply)
		{
			let i = 0
			const headerText = this.headerText

			Array.from(row.querySelectorAll(this.options.rowCells)).forEach(cell =>
			{
				if (apply)
				{
					const cellHeaderText = headerText[i]
					if (cellHeaderText && cellHeaderText.length && !cell.dataset.hideLabel)
					{
						cell.setAttribute('data-cell-label', cellHeaderText)
					}
					else
					{
						cell.removeAttribute('data-cell-label')
					}
				}
				else
				{
					cell.removeAttribute('data-cell-label')
				}

				i++
			})
		},
	})

	// ################################## TABS HANDLER ###########################################

	XF.Tabs = XF.Element.newHandler({
		options: {
			tabs: '.tabs-tab',
			panes: null,
			activeClass: 'is-active',
			state: null,
			preventDefault: true, // set to false to allow tab clicks to allow events to bubble
		},

		initial: 0,

		tabs: null,
		panes: null,

		activeTab: null,
		activePane: null,

		init ()
		{
			let container = this.target
			let tabs, panes

			tabs = this.tabs = container.querySelectorAll(this.options.tabs)
			if (this.options.panes)
			{
				panes = XF.findRelativeIf(this.options.panes, container)
			}
			else
			{
				panes = container.nextElementSibling
			}

			if (panes.matches('ol, ul'))
			{
				let children = Array.from(panes.children)
				panes = children.filter(child => child.tagName.toLowerCase() === 'li')
			}

			this.panes = panes

			if (tabs.length != panes.length)
			{
				console.error('Tabs and panes contain different totals: %d tabs, %d panes', tabs.length, panes.length)
				console.error('Tabs: %o, Panes: %o', tabs, panes)
				return
			}

			for (let i = 0; i < tabs.length; i++)
			{
				if (tabs[i].classList.contains(this.options.activeClass))
				{
					this.initial = i
					break
				}
			}

			tabs.forEach(tab =>
			{
				XF.on(tab, 'click', this.tabClick.bind(this))
			})

			XF.on(window, 'hashchange', this.onHashChange.bind(this))
			XF.on(window, 'popstate', this.onPopState.bind(this))

			this.reactToHash()
		},

		getSelectorFromHash ()
		{
			let selector = ''
			if (window.location.hash.length > 1)
			{
				const hash = window.location.hash.replace(/[^a-zA-Z0-9_-]/g, '')
				if (hash && hash.length)
				{
					selector = '#' + hash
				}
			}
			return selector
		},

		reactToHash ()
		{
			const selector = this.getSelectorFromHash()

			if (selector)
			{
				this.activateTarget(selector)
			}
			else
			{
				this.activateTab(this.initial)
			}
		},

		onHashChange (e)
		{
			this.reactToHash()
		},

		onPopState (e)
		{
			const state = e.state

			if (state && state.id)
			{
				this.activateTarget('#' + state.id, false)
			}
			else if (state && state.offset)
			{
				this.activateTab(state.offset)
			}
			else
			{
				this.activateTab(this.initial)
			}
		},

		activateTarget (selector)
		{
			const tabs = this.tabs
			let selectorValid = false
			let found = false

			if (selector)
			{
				try
				{
					// For a tab to be selected via a selector, the selector has to exist and the selector has to be valid.
					selectorValid = document.querySelector(selector) || false
				}
				catch (e)
				{
					selectorValid = false
				}

				if (selectorValid)
				{
					for (let i = 0; i < tabs.length; i++)
					{
						if (tabs[i].matches(selector))
						{
							this.activateTab(i)
							found = true
						}
					}
				}
			}

			if (!found)
			{
				this.activateTab(this.initial)
			}
		},

		activateTab (offset)
		{
			const tab = this.tabs[offset]
			const pane = this.panes[offset]
			const activeClass = this.options.activeClass

			if (!tab || !pane)
			{
				console.error('Selected invalid tab ' + offset)
				return
			}

			// deactivate active other tab
			this.tabs.forEach(tab =>
			{
				if (tab.classList.contains(activeClass))
				{
					tab.classList.remove(activeClass)
					tab.setAttribute('aria-selected', 'false')
					XF.trigger(tab, 'tab:hidden')
				}
			})

			this.panes.forEach(pane =>
			{
				if (pane.classList.contains(activeClass))
				{
					pane.classList.remove(activeClass)
					pane.setAttribute('aria-expanded', 'false')
					XF.trigger(pane, 'tab:hidden')
				}
			})

			// activate tab
			tab.classList.add(activeClass)
			tab.setAttribute('aria-selected', 'true')
			XF.trigger(tab, 'tab:shown')

			pane.classList.add(activeClass)
			pane.setAttribute('aria-expanded', 'true')
			XF.trigger(pane, 'tab:shown')

			XF.layoutChange()

			if (pane.dataset.href)
			{
				if (XF.DataStore.get(pane, 'tab-loading'))
				{
					return
				}
				XF.DataStore.set(pane, 'tab-loading', true)

				XF.ajax('get', pane.dataset.href, {}, data =>
				{
					pane.dataset.href = ''
					if (data.html)
					{
						const loadTarget = pane.dataset.loadTarget
						const sourceSelector = pane.dataset.sourceSelector

						if (sourceSelector)
						{
							const contentEl = XF.createElementFromString(data.html['content'].trim())
							data.html['content'] = contentEl.querySelector(sourceSelector).outerHTML
						}

						if (loadTarget)
						{
							XF.setupHtmlInsert(data.html, pane.querySelector(loadTarget))
						}
						else
						{
							XF.setupHtmlInsert(data.html, pane)
						}
					}
				}).finally(() => XF.DataStore.set(pane, 'tab-loading', false))
			}
		},

		tabClick (e)
		{
			const clickedElement = e.currentTarget
			const index = Array.prototype.indexOf.call(this.tabs, clickedElement)

			if (index == -1)
			{
				console.error('Did not find clicked element (%o) in tabs', clickedElement)
				return
			}

			const tab = this.tabs[index]

			const event = XF.customEvent('tab:click')
			XF.trigger(tab, event)

			if (event.defaultPrevented)
			{
				return
			}

			if (this.options.preventDefault)
			{
				e.preventDefault()
			}

			if (this.options.state)
			{
				let href = window.location.href.split('#')[0]
				let state = {}

				if (tab.id)
				{
					href = href + '#' + tab.id
					state = {
						id: tab.id,
					}
				}
				else
				{
					state = {
						offset: index,
					}
				}

				switch (this.options.state)
				{
					case 'replace':
						window.history.replaceState(state, '', href)
						break

					case 'push':
						window.history.pushState(state, '', href)
						break
				}
			}

			this.activateTab(index)
		},
	})

	XF.ToggleClick = XF.Event.newHandler({
		eventNameSpace: 'XFToggleClick',
		options: {
			target: null,
			container: null,
			hide: null,
			activeClass: 'is-active',
			activateParent: null,
			scrollTo: null,
		},

		toggleTarget: null,
		toggleParent: null,
		toggleUrl: null,
		ajaxLoaded: false,
		loading: false,

		init ()
		{
			this.toggleTarget = XF.getToggleTarget(this.options.target, this.target)
			if (!this.toggleTarget)
			{
				return false
			}

			if (this.options.activateParent)
			{
				this.toggleParent = this.target.parentNode
			}

			this.toggleUrl = this.getToggleUrl()
		},

		click (e)
		{
			e.preventDefault()

			if (this.toggleTarget)
			{
				this.toggle()
			}
		},

		isVisible ()
		{
			return this.toggleTarget.classList.contains(this.options.activeClass)
		},

		isTransitioning ()
		{
			return this.toggleTarget.classList.contains('is-transitioning')
		},

		toggle ()
		{
			if (this.isVisible())
			{
				this.hide()
			}
			else
			{
				this.show()
			}

			this.target.blur()
		},

		load ()
		{
			const href = this.toggleUrl

			if (!href || this.loading)
			{
				return
			}

			this.loading = true

			XF.ajax('get', href, data =>
			{
				if (data.html)
				{
					XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
					{
						const loadSelector = this.toggleTarget.dataset.loadSelector
						if (loadSelector)
						{
							const newHtml = html.querySelector(loadSelector)
							if (newHtml)
							{
								html = newHtml
							}
						}

						this.ajaxLoaded = true
						this.toggleTarget.append(html)
						XF.activate(html)

						onComplete(true)

						this.show()

						return false
					})
				}
			}).finally(() =>
			{
				this.ajaxLoaded = true
				this.loading = false
			})
		},

		hide (instant)
		{
			if (!this.isVisible() || this.isTransitioning())
			{
				return
			}

			const activeClass = this.options.activeClass

			if (this.toggleParent)
			{
				XF.Transition.removeClassTransitioned(this.toggleParent, activeClass, this.inactiveTransitionComplete, instant)
			}
			if (this.toggleTarget)
			{
				XF.Transition.removeClassTransitioned(this.toggleTarget, activeClass, this.inactiveTransitionComplete, instant)
			}
			XF.Transition.removeClassTransitioned(this.target, activeClass, this.inactiveTransitionComplete, instant)
		},

		show (instant = false)
		{
			if (this.isVisible() || this.isTransitioning())
			{
				return
			}

			if (Array.from(this.getOtherToggles()).filter(el => el.classList.contains('is-transitioning')).length)
			{
				return
			}

			if (this.toggleUrl && !this.ajaxLoaded)
			{
				this.load()
				return
			}

			this.closeOthers()

			const activeClass = this.options.activeClass
			if (this.toggleParent)
			{
				XF.Transition.addClassTransitioned(this.toggleParent, activeClass, this.activeTransitionComplete, instant)
			}
			if (this.toggleTarget)
			{
				XF.Transition.addClassTransitioned(this.toggleTarget, activeClass, this.activeTransitionComplete, instant)
			}
			XF.Transition.addClassTransitioned(this.target, activeClass, this.activeTransitionComplete, instant)

			this.hideSpecified()
			this.scrollTo()

			XF.autoFocusWithin(this.toggleTarget, '[autofocus], [data-toggle-autofocus]')
		},

		activeTransitionComplete (e)
		{
			if (e.currentTarget)
			{
				XF.trigger(e.currentTarget, 'toggle:shown')
				XF.layoutChange()
			}
		},

		inactiveTransitionComplete (e)
		{
			if (e.currentTarget)
			{
				XF.trigger(e.currentTarget, 'toggle:hidden')
				XF.layoutChange()
			}
		},

		closeOthers ()
		{
			this.getOtherToggles().forEach(toggle =>
			{
				let handlers = XF.DataStore.get(toggle, 'xf-click-handlers')

				if (!handlers)
				{
					handlers = XF.Event.initElement(this, 'click')
				}

				if (handlers && handlers.toggle)
				{
					handlers.toggle.hide(true)
				}
			})
		},

		hideSpecified ()
		{
			const hide = document.querySelector(this.options.hide)
			if (hide)
			{
				XF.display(hide, 'none')
			}
		},

		scrollTo ()
		{
			if (this.options.scrollTo)
			{
				const toggleTarget = this.toggleTarget,
					topOffset = toggleTarget.getBoundingClientRect().top + window.scrollY,
					height = toggleTarget.offsetHeight,
					windowHeight = document.documentElement.clientHeight

				let offset

				if (height < windowHeight)
				{
					offset = topOffset - ((windowHeight / 2) - (height / 2))
				}
				else
				{
					offset = topOffset
				}

				XF.smoothScroll(offset)
			}
		},

		getToggleUrl ()
		{
			const toggleTarget = this.toggleTarget
			const url = toggleTarget.dataset.href

			if (toggleTarget && url)
			{
				return url == 'trigger-href' ? this.target.getAttribute('href') : url
			}

			return null
		},

		getContainer ()
		{
			if (this.options.container)
			{
				let container = this.target.closest(this.options.container)
				if (!container)
				{
					console.error('Container parent not found: ' + this.options.container)
					return null
				}
				else
				{
					return container
				}
			}

			return null
		},

		getOtherToggles ()
		{
			const container = this.getContainer()
			if (container)
			{
				return Array.from(container.querySelectorAll('[data-xf-click~=toggle]'))
					.filter(element => element !== this.target)
			}
			else
			{
				return []
			}
		},
	})

	XF.getToggleTarget = (optionTarget, thisTarget) =>
	{
		const target = optionTarget ? XF.findRelativeIf(optionTarget, thisTarget) : thisTarget.nextElementSibling

		if (!target)
		{
			throw new Error('No toggle target for %o', thisTarget)
		}

		return target
	}

	XF.CommentToggleClick = XF.extend(XF.ToggleClick, {
		__backup: {
			show: '_show',
		},

		show ()
		{
			this._show()

			const editorPlaceholder = this.toggleTarget.querySelector('[data-xf-click~="editor-placeholder"]')
			if (editorPlaceholder)
			{
				editorPlaceholder.click()
			}
		},
	})

	XF.ToggleStorage = XF.Element.newHandler({
		options: {
			storageType: 'local',
			storageContainer: 'toggle',
			storageKey: null,
			storageExpiry: 86400, // seconds, 1 day.

			target: null,
			container: null,
			hide: null,
			activeClass: 'is-active',
			activateParent: null,
		},

		targetId: null,
		storage: null,

		init ()
		{
			const container = this.options.storageContainer
			if (!container)
			{
				throw new Error('Storage container not specified for ToggleStorage handler')
			}

			const key = this.options.storageKey
			if (!key)
			{
				throw new Error('Storage key not specified for ToggleStorage handler')
			}

			this.storage = XF.ToggleStorageData.getInstance(this.options.storageType)
			if (!this.storage)
			{
				throw new Error('Invalid storage type ' + this.options.storageType)
			}

			const toggleValue = this.storage.get(container, key, {
				allowExpired: false,
				touch: false,
			})
			if (toggleValue !== null)
			{
				const toggleTarget = XF.getToggleTarget(this.options.target, this.target)
				if (toggleTarget)
				{
					const activeClass = this.options.activeClass

					this.target.classList.toggle(activeClass, toggleValue)
					toggleTarget.classList.toggle(activeClass, toggleValue)
				}
			}

			this.storage.prune(container)

			XF.on(this.target, 'xf-click:after-click.XFToggleClick', this.updateStorage.bind(this))
		},

		updateStorage (e)
		{
			const options = this.options

			this.storage.set(
				options.storageContainer,
				options.storageKey,
				this.target.classList.contains(options.activeClass),
				options.storageExpiry,
			)
		},
	})

	XF.ToggleClassClick = XF.Event.newHandler({
		eventNameSpace: 'XFToggleClassClick',
		options: {
			class: null,
		},

		init ()
		{
		},

		click (e)
		{
			if (!this.options.class)
			{
				return
			}

			this.toggle()
		},

		toggle ()
		{
			this.target.classList.toggle(this.options.class)
		},
	})

	// ################################## VIDEO ELEMENTS ###########################################

	XF.VideoInit = XF.Element.newHandler({
		options: {},

		video: null,
		loaded: false,

		/**
		 * This workaround loads the first frame of a video into a canvas element
		 * to workaround the fact that iOS doesn't do that until the video actually
		 * starts playing. This enables us to not worry about thumbnails / posters for videos.
		 */
		init ()
		{
			if (!XF.isIOS())
			{
				return
			}

			this.video = this.target.cloneNode(true)
			this.video.load()

			XF.on(this.video, 'loadeddata', this.hasLoaded.bind(this))
			XF.on(this.video, 'seeked', this.hasSeeked.bind(this))
		},

		hasLoaded ()
		{
			if (this.loaded)
			{
				return
			}

			this.loaded = true
			this.video.currentTime = 0
		},

		hasSeeked ()
		{
			const width = this.target.offsetWidth
			const height = this.target.offsetHeight
			const canvas = XF.createElement('canvas', {
				width: width,
				height: height
			})
			const context = canvas.getContext('2d')

			context.drawImage(this.video, 0, 0, width, height)

			if (!canvas)
			{
				return
			}

			canvas.toBlob(blob =>
			{
				if (!blob)
				{
					return
				}

				const url = URL.createObjectURL(blob)
				this.target.setAttribute('poster', url)
			})
		},
	})

	// ################################## ELEMENTS SWAPPER ###########################################

	XF.ShifterClick = XF.Event.newHandler({
		eventNameSpace: 'XFShifterClick',
		options: {
			selector: null,
			dir: 'up',
		},

		element: null,

		init ()
		{
			this.element = this.target.closest(this.options.selector)
		},

		click (e)
		{
			if (this.options.dir == 'down')
			{
				const nextElement = this.element.nextElementSibling
				if (nextElement)
				{
					nextElement.insertAdjacentElement('afterend', this.element)
				}
			}
			else
			{
				const previousElement = this.element.previousElementSibling
				if (previousElement)
				{
					previousElement.insertAdjacentElement('beforebegin', this.element)
				}
			}
		},
	})

	// ################################## ELEMENTS REMOVER ###########################################

	XF.RemoverClick = XF.Event.newHandler({
		eventNameSpace: 'XFRemoverClick',
		options: {
			selector: null,
		},

		targetElement: null,

		init ()
		{
			this.targetElement = XF.findRelativeIf(this.options.selector, this.target)
		},

		click (e)
		{
			if (this.targetElement)
			{
				this.targetElement.remove()
				XF.layoutChange()
			}
		},
	})

	// ################################## ELEMENTS DUPLICATOR ###########################################

	XF.DuplicatorClick = XF.Event.newHandler({
		eventNameSpace: 'XFDuplicatorClick',
		options: {
			selector: null,
		},

		targetElement: null,

		init ()
		{
			this.targetElement = XF.findRelativeIf(this.options.selector, this.target)
		},

		click (e)
		{
			if (this.targetElement)
			{
				const duplicate = this.targetElement.cloneNode(true)
				this.targetElement.after(duplicate)

				XF.layoutChange()

				if (this.targetElement.closest('[data-xf-init~=list-sorter]'))
				{
					XF.trigger(window, XF.customEvent('listSorterDuplication', {
						duplicate,
					}))
				}
			}
		},
	})

	XF.Event.register('click', 'inserter', 'XF.InserterClick')
	XF.Event.register('click', 'menu-proxy', 'XF.MenuProxy')
	XF.Event.register('click', 'toggle', 'XF.ToggleClick')
	XF.Event.register('click', 'comment-toggle', 'XF.CommentToggleClick')
	XF.Event.register('click', 'toggle-class', 'XF.ToggleClassClick')
	XF.Event.register('click', 'shifter', 'XF.ShifterClick')
	XF.Event.register('click', 'remover', 'XF.RemoverClick')
	XF.Event.register('click', 'duplicator', 'XF.DuplicatorClick')
	//
	XF.Element.register('focus-inserter', 'XF.InserterFocus')
	XF.Element.register('responsive-data-list', 'XF.ResponsiveDataList')
	XF.Element.register('tabs', 'XF.Tabs')
	XF.Element.register('toggle-storage', 'XF.ToggleStorage')
	XF.Element.register('video-init', 'XF.VideoInit')

	XF.on(document, 'xf:page-load-complete', () =>
	{
		const hash = window.location.hash.replace(/[^a-zA-Z0-9_-]/g, '')
		if (!hash)
		{
			return
		}

		const match = hash ? document.querySelector('#' + hash) : null
		if (match)
		{
			const toggleWrapper = match.closest('[data-toggle-wrapper]')
			if (toggleWrapper)
			{
				const toggler = toggleWrapper.querySelector('[data-xf-click~="toggle"]')
				if (toggler)
				{
					const toggleHandler = XF.Event.getElementHandler(toggler, 'toggle', 'click')
					if (toggleHandler)
					{
						toggleHandler.show(true)
					}
				}
			}
		}
	})
})(window, document)
