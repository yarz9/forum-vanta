((window, document) =>
{
	'use strict'

	XF._baseInserterOptions = {
		after: null,
		append: null,
		before: null,
		prepend: null,
		replace: null,
		removeOldSelector: true,
		animateReplace: true,
		animateDisplay: null,
		scrollTarget: null,
		href: null,
		afterLoad: null,
	}

	XF.Inserter = XF.create({
		options: XF.extendObject(true, {}, XF._baseInserterOptions),

		target: null,
		href: null,
		loading: false,

		__construct (target, options)
		{
			this.target = target
			this.options = XF.extendObject(true, {}, this.options, options)

			const href = this.options.href || this.target.dataset['inserterHref'] || this.target.getAttribute('href')
			if (!href)
			{
				console.error('Target must have href')
				return
			}

			this.href = href
		},

		onEvent (e, extraData)
		{
			e.preventDefault()

			if (this.loading)
			{
				return
			}

			this.loading = true

			const replace = document.querySelectorAll(this.options.replace)
			if (replace.length)
			{
				replace.forEach(_replace => XF.Transition.addClassTransitioned(_replace, 'is-active'))
			}

			XF.ajax('get', this.href, extraData || {}, this.onLoad.bind(this))
				.finally(() =>
				{
					this.loading = false
				})
		},

		onLoad (data)
		{
			if (!data.html)
			{
				return
			}

			const options = this.options,
				scrollTarget = options.scrollTarget,
				afterLoad = options.afterLoad

			let scrollEl

			if (scrollTarget)
			{
				scrollEl = XF.findRelativeIf(scrollTarget, this.target)
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this._applyChange(html, options.after, this._applyAfter.bind(this))
				this._applyChange(html, options.append, this._applyAppend.bind(this))
				this._applyChange(html, options.before, this._applyBefore.bind(this))
				this._applyChange(html, options.prepend, this._applyPrepend.bind(this))
				this._applyChange(html, options.replace, this._applyReplace.bind(this))

				onComplete(true)

				if (afterLoad)
				{
					afterLoad(data)
				}

				return false // already run the necessary init on what was inserted
			})

			XF.layoutChange()

			if (scrollEl)
			{
				scrollEl.scrollIntoView(true)
			}
		},

		_applyChange (html, targets, applyFn)
		{
			if (!targets || !targets.length)
			{
				return
			}

			const selectors = targets.split(',')
			let selectorOld
			let selectorNew
			let oldEl
			let newEl

			for (let selector of selectors)
			{
				selector = selector.split(' with ')
				selectorOld = selector[0].trim()
				selectorNew = selector[1] ? selector[1].trim() : selectorOld

				if (selectorOld.length && selectorNew.length)
				{
					oldEl = document.querySelector(selectorOld)

					if (html.matches(selectorNew))
					{
						newEl = html
					}
					else
					{
						newEl = html.querySelector(selectorNew)
					}

					applyFn(selectorOld, oldEl, newEl)
				}
			}
		},

		_applyAfter (selectorOld, oldEl, newEl)
		{
			if (oldEl && newEl)
			{
				oldEl.after(newEl)
				XF.activate(newEl)

				this._removeOldSelector(selectorOld, oldEl)
			}
		},

		_applyAppend (selectorOld, oldEl, newEl)
		{
			if (oldEl && newEl)
			{
				XF.activate(newEl)
				const children = Array.from(newEl.children)
				children.forEach(child => oldEl.appendChild(child))
			}
		},

		_applyBefore (selectorOld, oldEl, newEl)
		{
			if (oldEl && newEl)
			{
				oldEl.after(newEl)
				XF.activate(newEl)

				this._removeOldSelector(selectorOld, oldEl)
			}
		},

		_applyPrepend (selectorOld, oldEl, newEl)
		{
			if (oldEl && newEl)
			{
				XF.activate(newEl)
				const children = Array.from(newEl.children)
				children.reverse().forEach(child => oldEl.prepend(child))
			}
		},

		_applyReplace (selectorOld, oldEl, newEl)
		{
			if (oldEl)
			{
				let animate = this.options.animateReplace
				if (XF.isIOS())
				{
					// workaround for bug #137959 - disable the animation to avoid overlay becoming unscrollable
					animate = false
				}

				if (newEl)
				{
					if (animate)
					{
						XF.display(newEl, 'none')
						newEl.style.opacity = 0
					}
					oldEl.insertAdjacentElement('afterend', newEl)
				}

				if (animate)
				{
					const complete = () =>
					{
						oldEl.remove()
						XF.layoutChange()

						if (newEl)
						{
							XF.activate(newEl)
						}

						XF.Animate.fadeDown(newEl, {
							speed: XF.config.speed.normal,
							complete: XF.layoutChange,
							display: this.options.animateDisplay,
						})
					}

					if (window.getComputedStyle(oldEl).display === 'none')
					{
						complete()
					}
					else
					{
						XF.Animate.fadeUp(oldEl, {
							complete: complete(),
						})
					}
				}
				else
				{
					oldEl.remove()

					if (newEl)
					{
						XF.activate(newEl)
					}
				}
			}
		},

		_removeOldSelector (selector, oldEl)
		{
			if (!this.options.removeOldSelector)
			{
				return
			}

			let match
			if ((match = selector.match(/^\.([a-z0-9_-]+)/i)))
			{
				oldEl.classList.remove(match[1])
			}
		},
	})

	XF.MenuBuilder = {
		actionBar (menu, target, handler)
		{
			const menuTarget = menu.querySelector('.js-menuBuilderTarget')
			target.closest('.actionBar-set').querySelectorAll('.actionBar-action--menuItem').forEach(item =>
			{
				const clonedItem = item.cloneNode(true)
				clonedItem.className = 'menu-linkRow'

				menuTarget.appendChild(clonedItem)
			})

			XF.activate(menuTarget)
		},

		dataList (menu, target, handler)
		{
			const menuTarget = menu.querySelector('.js-menuBuilderTarget')
			target.closest('.dataList-row').querySelectorAll('.dataList-cell--responsiveMenuItem').forEach(cell =>
			{
				const clonedChildren = Array.from(cell.cloneNode(true).children)
				clonedChildren.forEach(item =>
				{
					if (item.nodeName.toLowerCase() === 'a')
					{
						item.className = 'menu-linkRow'
					}
					else
					{
						let wrapper = XF.createElement('div', {
							className: 'menu-row',
						})
						wrapper.appendChild(item)
						item = wrapper
					}
					menuTarget.appendChild(item)
				})
			})

			XF.activate(menuTarget)
		},
	}

	XF.MenuWatcher = (() =>
	{
		let opened = [],
			outsideClicker = null,
			closing = false,
			docClickPrevented = false

		const preventDocClick = () =>
		{
			docClickPrevented = true
		}

		const allowDocClick = () =>
		{
			docClickPrevented = false
		}

		const docClick = e =>
		{
			if (!docClickPrevented)
			{
				closeUnrelated(e.target)
			}
		}

		const windowResize = e =>
		{
			opened.forEach(menu => XF.trigger(menu, 'menu:reposition'))
		}

		const onOpen = (menu, touchTriggered) =>
		{
			if (!outsideClicker)
			{
				outsideClicker = XF.createElement('div', {
					className: 'menuOutsideClicker',
				})
				outsideClicker.addEventListener('click', docClick)
				menu.parentNode.insertBefore(outsideClicker, menu)
			}

			if (!opened.length)
			{
				document.addEventListener('click', docClick)
				XF.on(window, 'resize', windowResize, { passive: true })

				if (touchTriggered)
				{
					outsideClicker.classList.add('is-active')
				}
			}

			opened.push(menu)
		}

		const onClose = menu =>
		{
			opened = opened.filter(openMenu => openMenu !== menu)

			if (!opened.length)
			{
				XF.off(document, 'click', docClick)
				XF.off(window, 'resize', windowResize)

				if (outsideClicker)
				{
					outsideClicker.classList.remove('is-active')
				}
			}

			closeUnrelated(menu)
		}

		const closeUnrelated = el =>
		{
			if (closing)
			{
				return
			}

			closing = true

			opened.forEach(menu =>
			{
				let trigger = XF.DataStore.get(menu, 'menu-trigger'),
					target = trigger ? trigger.target : null

				if (!menu.contains(el) && (!target || !target.contains(el)))
				{
					if (trigger)
					{
						trigger.close()
					}
				}
			})

			closing = false
		}

		const closeAll = () =>
		{
			closing = true
			opened.forEach(menu => XF.trigger(menu, 'menu:close'))
			closing = false
		}

		return {
			onOpen,
			onClose,
			closeAll,
			closeUnrelated,
			preventDocClick,
			allowDocClick,
		}
	})()

	XF.OffCanvasBuilder = {
		navigation (menu, handler)
		{
			document.body.append(menu)

			const entries = XF.createElementFromString('<ul class="offCanvasMenu-list" />')

			document.querySelectorAll('.js-offCanvasNavSource .p-navEl').forEach(el =>
			{
				let isSelected = el.classList.contains('is-selected'),
					link = el.querySelector('.p-navEl-link'),
					menu = el.querySelector('[data-menu]')

				if (el.dataset.hasChildren && !menu)
				{
					// menu has been moved, find it
					const firstMenuHandler = el.querySelector('[data-xf-click~="menu"]')
					const clickHandlers = XF.DataStore.get(firstMenuHandler, 'xf-click-handlers')
					if (clickHandlers && clickHandlers.menu)
					{
						menu = clickHandlers.menu.menu
					}
				}

				if (!link)
				{
					return
				}

				let linkContainer = XF.createElement('div', {
					className: 'offCanvasMenu-linkHolder',
				})
				let entry = document.createElement('li')

				let mainLink = link.cloneNode(true)
				mainLink.classList.remove(['p-navEl-link', 'p-navEl-link--menuTrigger', 'p-navEl-link--splitMenu'])
				mainLink.classList.add('offCanvasMenu-link')

				if (!mainLink.matches('a') && menu)
				{
					// the main link has no navigation, but has children
					// proxy clicks to the toggler to open the menu
					mainLink.setAttribute('data-xf-click', 'menu-proxy')
					mainLink.setAttribute('data-trigger', '< :up | .offCanvasMenu-link--splitToggle')
				}

				linkContainer.innerHTML = ''
				linkContainer.append(mainLink)

				if (isSelected)
				{
					entry.classList.add('is-selected')
					linkContainer.classList.add('is-selected')
				}

				entry.append(linkContainer)

				if (menu)
				{
					const splitToggle = XF.createElementFromString('<a class="offCanvasMenu-link offCanvasMenu-link--splitToggle"'
						+ ' data-xf-click="toggle" data-target="< :up :next" role="button" tabindex="0" />')

					if (isSelected)
					{
						splitToggle.classList.add('is-active')
					}
					linkContainer.appendChild(splitToggle)

					const childLinks = XF.createElementFromString('<ul class="offCanvasMenu-subList" />')
					if (isSelected)
					{
						childLinks.classList.add('is-active')
					}

					menu.querySelectorAll('.menu-linkRow').forEach(childLinkEl =>
					{
						let childEl = XF.createElement('li', {
							innerHTML: childLinkEl.cloneNode(true).outerHTML.replace('menu-linkRow', 'offCanvasMenu-link'),
						}, childLinks)
					})

					entry.appendChild(childLinks)
				}

				entries.appendChild(entry)
			})

			let addTarget = menu.querySelector('.js-offCanvasNavTarget').appendChild(entries)
			XF.activate(addTarget)
		},

		sideNav (menu, handler)
		{
			const content = menu.querySelector('.offCanvasMenu-content'),
				target = handler.target

			if (content && !content.querySelector('[data-menu-close]'))
			{
				let header = content.querySelector('.block-header')

				if (!header)
				{
					header = XF.createElementFromString('<div class="offCanvasMenu-header offCanvasMenu-header--separated offCanvasMenu-shown" />')
					header.innerHTML = ''
					header.append(...target.childNodes)
					content.prepend(header)
				}

				header.append(XF.createElementFromString('<a class="offCanvasMenu-closer" data-menu-close="true" role="button" tabindex="0" />'))
			}

			XF.on(window, 'resize', () =>
			{
				if (window.getComputedStyle(target).display === 'none')
				{
					XF.trigger(menu, 'off-canvas:close')
				}
			})
		},

		simple (menu, handler)
		{
			document.body.append(menu)
			const entries = XF.createElementFromString('<ul class="offCanvasMenu-list" />'),
				removeClasses = menu.dataset.ocmLinkRemoveClass

			document.querySelectorAll(menu.dataset.ocmLinkTarget).forEach(element =>
			{
				let linkContainer = XF.createElementFromString('<div class="offCanvasMenu-linkHolder" />'),
					mainLink = element.cloneNode(true),
					entry = XF.createElementFromString('<li />')

				mainLink.classList.add('offCanvasMenu-link')

				if (removeClasses)
				{
					mainLink.classList.remove(...removeClasses.split(' '))
				}

				linkContainer.appendChild(mainLink)
				entry.appendChild(linkContainer)
				entries.appendChild(entry)
			})

			const addTarget = menu.querySelector('.js-offCanvasTarget')
			addTarget.appendChild(entries)
			XF.activate(addTarget)
		},
	}

	XF.ToggleStorageDataInstance = XF.create({
		storage: null,

		dataCache: {},
		syncTimers: {},
		pruneTimers: {},

		__construct (storageObject)
		{
			this.storage = storageObject
		},

		getStorage ()
		{
			return this.storage
		},

		get (container, key, options)
		{
			if (!options)
			{
				options = {}
			}

			let allowExpired = true
			let touch = true

			if (XF.hasOwn(options, 'allowExpired'))
			{
				allowExpired = options.allowExpired
			}
			if (XF.hasOwn(options, 'touch'))
			{
				touch = options.touch
			}

			if (!this.dataCache[container])
			{
				this.dataCache[container] = this.storage.getJson(container)
			}

			const data = this.dataCache[container]

			if (!XF.hasOwn(data, key))
			{
				return null
			}

			const value = data[key]
			const now = Math.floor(Date.now() / 1000)

			if (!allowExpired && (value[0] + value[1]) < now)
			{
				delete this.dataCache[container][key]
				this.scheduleSync(container)

				return null
			}

			if (touch)
			{
				value[0] = now
				this.dataCache[container][key] = value
				this.scheduleSync(container)
			}

			return value[2]
		},

		set (container, key, value, expirySeconds)
		{
			if (!this.dataCache[container])
			{
				this.dataCache[container] = {}
			}

			if (!expirySeconds)
			{
				expirySeconds = 4 * 3600 // 4 hours
			}

			const now = Math.floor(Date.now() / 1000)
			this.dataCache[container][key] = [now, expirySeconds, value]
			this.scheduleSync(container)
		},

		remove (container, key)
		{
			if (!this.dataCache[container])
			{
				this.dataCache[container] = {}
			}

			delete this.dataCache[container][key]
			this.scheduleSync(container)
		},

		prune (container, immediate)
		{
			const timer = this.pruneTimers[container]
			const triggerPrune = () =>
			{
				clearTimeout(timer)
				this.pruneTimers[container] = null
				this.pruneInternal(container)
			}

			if (immediate)
			{
				triggerPrune()
			}
			else if (!timer)
			{
				this.pruneTimers[container] = setTimeout(triggerPrune, 100)
			}
		},

		pruneInternal (container)
		{
			if (!this.dataCache[container])
			{
				this.dataCache[container] = this.storage.getJson(container)
			}

			const cache = this.dataCache[container]
			let value
			const now = Math.floor(Date.now() / 1000)
			let updated = false

			for (let key of Object.keys(cache))
			{
				value = cache[key]
				if (value[0] + value[1] < now)
				{
					delete cache[key]
					updated = true
				}
			}

			if (updated)
			{
				this.dataCache[container] = cache
				this.scheduleSync(container)
			}
		},

		scheduleSync (container, immediate)
		{
			const timer = this.syncTimers[container]
			const triggerSync = () =>
			{
				clearTimeout(timer)
				this.syncTimers[container] = null
				this.syncToStorage(container)
			}

			if (immediate)
			{
				triggerSync()
			}
			else if (!timer)
			{
				this.syncTimers[container] = setTimeout(triggerSync, 100)
			}
		},

		syncToStorage (container)
		{
			if (!this.dataCache[container])
			{
				return
			}

			const writeValue = this.dataCache[container]
			if (XF.isEmptyObject(writeValue))
			{
				this.storage.remove(container)
			}
			else
			{
				if (this.storage.supportsExpiryDate())
				{
					const expires = XF.getFutureDate(1, 'year')
					this.storage.setJson(container, writeValue, expires)
				}
				else
				{
					this.storage.setJson(container, writeValue)
				}
			}
		},
	})

	XF.ToggleStorageData = (() =>
	{
		const instances = {
			local: new XF.ToggleStorageDataInstance(XF.LocalStorage),
			cookie: new XF.ToggleStorageDataInstance(XF.Cookie),
		}
		const defaultInstance = instances.local

		return {
			getInstance (type)
			{
				return instances[type]
			},
			get (container, key, options)
			{
				return defaultInstance.get(container, key, options)
			},
			set (container, key, value, expirySeconds)
			{
				return defaultInstance.set(container, key, value, expirySeconds)
			},
			remove (container, key)
			{
				return defaultInstance.remove(container, key)
			},
			prune (container, immediate)
			{
				return defaultInstance.prune(container, immediate)
			},
		}
	})()

	XF.TooltipElement = XF.create({
		options: {
			baseClass: 'tooltip',
			extraClass: 'tooltip--basic',
			html: false,
			inViewport: true,
			loadRequired: false,
			loadParams: null,
			placement: 'top',
		},

		content: null,
		tooltip: null,
		shown: false,
		shownFully: false,
		placement: null,
		positioner: null,
		loadRequired: false,
		loading: false,
		contentApplied: false,
		setupCallbacks: null,

		__construct (content, options, positioner)
		{
			this.setupCallbacks = [] // needs to be set here to be local

			this.options = XF.extendObject(true, {}, this.options, options)
			this.content = content
			this.loadRequired = this.options.loadRequired

			if (positioner)
			{
				this.setPositioner(positioner)
			}
		},

		setPositioner (positioner)
		{
			this.positioner = positioner
		},

		setLoadRequired (required)
		{
			this.loadRequired = required
		},

		addSetupCallback (callback)
		{
			if (this.tooltip)
			{
				// already setup, run now
				callback(this.tooltip)
			}
			else
			{
				this.setupCallbacks.push(callback)
			}
		},

		show ()
		{
			if (this.shown)
			{
				return
			}

			this.shown = true

			if (this.loadRequired)
			{
				this.loadContent()
				return
			}

			const tooltip = this.getTooltip()

			this.reposition()

			const id = XF.uniqueId(tooltip)
			XF.on(window, 'resize.tooltip-' + id, this.reposition.bind(this))

			XF.trigger(tooltip, 'tooltip:shown')

			XF.display(tooltip, 'none')

			XF.Animate.fadeIn(tooltip, {
				speed: XF.config.speed.fast,
				complete: () =>
				{
					this.shownFully = true
				},
			})
		},

		hide ()
		{
			if (!this.shown)
			{
				return
			}

			this.shown = false
			this.shownFully = false

			const tooltip = this.tooltip
			if (tooltip)
			{
				XF.Animate.fadeOut(tooltip, {
					speed: XF.config.speed.fast,
				})

				XF.trigger(tooltip, 'tooltip:hidden')

				const id = XF.uniqueId(tooltip)
				XF.off(window, 'resize.tooltip-' + id)
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
			if (this.tooltip)
			{
				this.tooltip.remove()
			}
		},

		isShown ()
		{
			return this.shown
		},

		isShownFully ()
		{
			return this.shown && this.shownFully
		},

		requiresLoad ()
		{
			return this.loadRequired
		},

		getPlacement ()
		{
			return XF.rtlFlipKeyword(this.options.placement)
		},

		reposition ()
		{
			const positioner = this.positioner

			if (!positioner)
			{
				console.error('No tooltip positioner')
				return
			}

			if (this.loadRequired)
			{
				return
			}

			let targetDims
			let forceInViewport = this.options.inViewport

			if (positioner instanceof Element)
			{
				targetDims = XF.dimensions(positioner, true)

				if (positioner.closest('.overlay'))
				{
					forceInViewport = true
				}
			}
			else if (typeof positioner[0] !== 'undefined' && typeof positioner[1] !== 'undefined')
			{
				// a single [x, y] point
				targetDims = {
					top: positioner[1],
					right: positioner[0],
					bottom: positioner[1],
					left: positioner[0],
				}
			}
			else if (typeof positioner.right !== 'undefined' && typeof positioner.bottom !== 'undefined')
			{
				// positioner is already t/r/b/l format
				targetDims = positioner
			}
			else
			{
				console.error('Positioner is not in correct format', positioner)
			}

			targetDims.width = targetDims.right - targetDims.left
			targetDims.height = targetDims.bottom - targetDims.top

			const docEl = document.documentElement
			const tooltip = this.getTooltip()
			let placement = this.getPlacement()
			const baseClass = this.options.baseClass
			const originalPlacement = placement
			let constraintDims

			if (forceInViewport)
			{
				const vwWidth = docEl.clientWidth
				const vwHeight = docEl.clientHeight
				const vwTop = docEl.scrollTop + XF.getStickyHeaderOffset()
				const vwLeft = docEl.scrollLeft

				constraintDims = {
					top: vwTop,
					left: vwLeft,
					right: vwLeft + vwWidth,
					bottom: vwTop + vwHeight,
					width: vwWidth,
					height: vwHeight,
				}
			}
			else
			{
				constraintDims = XF.dimensions(document.body)
			}

			if (this.placement)
			{
				tooltip.classList.remove(baseClass + '--' + this.placement)
			}

			tooltip.classList.add(baseClass + '--' + placement)
			tooltip.style.visibility = 'hidden'
			XF.display(tooltip)
			tooltip.style.top = ''
			tooltip.style.bottom = ''
			tooltip.style.left = ''
			tooltip.style.right = ''
			tooltip.style.paddingLeft = ''
			tooltip.style.paddingRight = ''
			tooltip.style.paddingTop = ''
			tooltip.style.paddingBottom = ''

			const tooltipWidth = tooltip.offsetWidth,
				tooltipHeight = tooltip.offsetHeight

			// can we fit this in the right position? if not, flip it
			// if still can't fit horizontally, go vertical
			if (placement == 'top' && targetDims.top - tooltipHeight < constraintDims.top)
			{
				placement = 'bottom'
			}
			else if (placement == 'bottom' && targetDims.bottom + tooltipHeight > constraintDims.bottom)
			{
				if (targetDims.top - tooltipHeight >= constraintDims.top)
				{
					// only flip this back to the top if we have space within the constraints
					placement = 'top'
				}
			}
			else if (placement == 'left' && targetDims.left - tooltipWidth < constraintDims.left)
			{
				if (targetDims.right + tooltipWidth > constraintDims.right)
				{
					if (targetDims.top - tooltipHeight < constraintDims.top)
					{
						placement = 'bottom'
					}
					else
					{
						placement = 'top'
					}
				}
				else
				{
					placement = 'right'
				}
			}
			else if (placement == 'right' && targetDims.right + tooltipWidth > constraintDims.right)
			{
				if (targetDims.left - tooltipWidth < constraintDims.left)
				{
					if (targetDims.top - tooltipHeight < constraintDims.top)
					{
						placement = 'bottom'
					}
					else
					{
						placement = 'top'
					}
				}
				else
				{
					placement = 'left'
				}
			}
			if (placement != originalPlacement)
			{
				tooltip.classList.remove(baseClass + '--' + originalPlacement)
				tooltip.classList.add(baseClass + '--' + placement)
			}

			// figure out how to place the edges
			const position = {
				top: '',
				right: '',
				bottom: '',
				left: '',
			}
			switch (placement)
			{
				case 'top':
					position.bottom = docEl.clientHeight - targetDims.top
					position.left = targetDims.left + targetDims.width / 2 - tooltipWidth / 2
					break

				case 'bottom':
					position.top = targetDims.bottom
					position.left = targetDims.left + targetDims.width / 2 - tooltipWidth / 2
					break

				case 'left':
					position.top = targetDims.top + targetDims.height / 2 - tooltipHeight / 2
					position.right = docEl.clientWidth - targetDims.left
					break

				case 'right':
				default:
					position.top = targetDims.top + targetDims.height / 2 - tooltipHeight / 2
					position.left = targetDims.right
			}

			for (let prop in position)
			{
				tooltip.style[prop] = position[prop] + 'px'
			}

			const tooltipDims = XF.dimensions(tooltip, true)
			const delta = {
				top: 0,
				left: 0,
			}
			const arrow = tooltip.querySelector('.' + baseClass + '-arrow')
			let tooltipShifted = null

			// Check to see if we're outside of the constraints on the opposite edge from our positioned side
			// and if we are, push us down to that position and move the arrow to be positioned nicely.
			// We will only move the top positioning when doing left/right and left using top/bottom.
			if (placement == 'left' || placement == 'right')
			{
				if (tooltipDims.top < constraintDims.top)
				{
					delta.top = constraintDims.top - tooltipDims.top
					tooltipShifted = 'down'
				}
				else if (tooltipDims.bottom > constraintDims.bottom)
				{
					delta.top = constraintDims.bottom - tooltipDims.bottom
					tooltipShifted = 'up'
				}

				arrow.style.left = ''
				arrow.style.top = (50 - (100 * delta.top / tooltipDims.top)) + '%'
			}
			else
			{
				if (tooltipDims.left < constraintDims.left)
				{
					delta.left = constraintDims.left - tooltipDims.left
					tooltipShifted = 'left'
				}
				else if (tooltipDims.left + tooltipWidth > constraintDims.right)
				{
					delta.left = constraintDims.right - (tooltipDims.left + tooltipWidth)
					tooltipShifted = 'right'
				}

				const arrowLeft = parseInt(tooltipWidth / 100 * (50 - (100 * delta.left / tooltipWidth)), 0),
					arrowRealLeft = arrowLeft + parseInt(window.getComputedStyle(arrow).marginLeft),
					arrowRealRight = arrowRealLeft + arrow.offsetWidth,
					tooltipLeftOffset = parseInt(tooltip.style.paddingLeft, 10),
					tooltipRightOffset = parseInt(tooltip.style.paddingRight, 10)
				let shiftDiff

				// detect if the arrow is going to spill out of the main container and adjust the container
				// padding to keep the width the same but to shift it
				if (arrowRealLeft < tooltipLeftOffset)
				{
					shiftDiff = tooltipLeftOffset - arrowRealLeft
					tooltip.style.paddingLeft = Math.max(0, tooltipLeftOffset - shiftDiff)
					tooltip.style.paddingRight = tooltipRightOffset + shiftDiff
				}
				else if (arrowRealRight > tooltipWidth - tooltipRightOffset)
				{
					shiftDiff = arrowRealRight - (tooltipWidth - tooltipRightOffset)
					tooltip.style.paddingLeft = tooltipRightOffset + shiftDiff
					tooltip.style.paddingRight = Math.max(0, tooltipRightOffset - shiftDiff)
				}

				arrow.style.top = ''
				arrow.style.left = arrowLeft + 'px'
			}

			if (delta.left)
			{
				tooltip.style.left = (parseInt(position.left) + delta.left) + 'px'
			}
			else if (delta.top)
			{
				tooltip.style.top = (parseInt(position.top) + delta.top) + 'px'
			}

			this.placement = placement

			if (this.shown && !this.loadRequired)
			{
				tooltip.style.visibility = ''
			}
		},

		attach ()
		{
			this.getTooltip()
		},

		getTooltip ()
		{
			if (!this.tooltip)
			{
				const tooltip = this.getTemplate()
				document.body.append(tooltip)
				this.tooltip = tooltip

				if (!this.loadRequired)
				{
					this.applyTooltipContent()
				}
			}

			return this.tooltip
		},

		setContent (content)
		{
			this.contentApplied = false
			this.content = content
			this.applyTooltipContent()
		},

		applyTooltipContent ()
		{
			if (this.contentApplied || this.loadRequired)
			{
				return false
			}

			const tooltip = this.getTooltip(),
				contentHolder = tooltip.querySelector('.' + this.options.baseClass + '-content')
			let content = this.content

			if (XF.isFunction(content))
			{
				content = content()
			}

			if (this.options.html)
			{
				contentHolder.innerHTML = ''

				if (content instanceof NodeList)
				{
					contentHolder.append(...content)
				}
				else if (content instanceof Node)
				{
					contentHolder.append(content)
				}
				else
				{
					contentHolder.innerHTML = content
				}

				const image = contentHolder.querySelector('img')

				if (image)
				{
					XF.on(image, 'load', this.reposition.bind(this))
				}
			}
			else
			{
				contentHolder.textContent = content
			}

			const setup = this.setupCallbacks
			for (const callback of setup)
			{
				callback(tooltip)
			}

			XF.activate(tooltip)

			this.contentApplied = true
			return true
		},

		loadContent ()
		{
			if (!this.loadRequired || this.loading)
			{
				return
			}

			const content = this.content

			const onLoad = newContent =>
			{
				this.content = newContent
				this.loadRequired = false
				this.loading = false
				this.applyTooltipContent()

				if (this.shown)
				{
					this.shown = false // make sure the show works
					this.show()
				}
			}

			if (!XF.isFunction(content))
			{
				onLoad('')
				return
			}

			this.loading = true
			content(onLoad, this.options.loadParams)
		},

		getTemplate ()
		{
			const extra = this.options.extraClass ? (' ' + this.options.extraClass) : '',
				baseClass = this.options.baseClass

			return XF.createElementFromString('<div class="' + baseClass + extra + '" role="tooltip">'
				+ '<div class="' + baseClass + '-arrow"></div>'
				+ '<div class="' + baseClass + '-content"></div>'
				+ '</div>')
		},
	})

	XF.TooltipTrigger = XF.create({
		options: {
			delayIn: 200,
			delayInLoading: 800,
			delayOut: 200,
			trigger: 'hover focus',
			maintain: false,
			clickHide: null,
			onShow: null,
			onHide: null,
		},

		target: null,
		tooltip: null,

		delayTimeout: null,
		delayTimeoutType: null,
		stopFocusBefore: null,
		clickTriggered: false,

		touchEnterTime: null,

		covers: null,

		__construct (target, tooltip, options)
		{
			this.options = XF.extendObject(true, {}, this.options, options)
			this.target = target
			this.tooltip = tooltip

			if (this.options.trigger == 'auto')
			{
				this.options.trigger = 'hover focus' + (target.matches('span') ? ' touchclick' : '')
			}

			tooltip.setPositioner(target)
			tooltip.addSetupCallback(this.onTooltipSetup.bind(this))

			const id = XF.uniqueId(target)
			XF.TooltipTrigger.cache[id] = this
		},

		init ()
		{
			let target = this.target,
				actOnClick = false,
				actOnTouchClick = false,
				supportsPointerEvents = XF.supportsPointerEvents(),
				pointerEnter = supportsPointerEvents ? 'pointerover' : 'mouseover',
				pointerLeave = supportsPointerEvents ? 'pointerout' : 'mouseout'

			if (this.options.clickHide === null)
			{
				this.options.clickHide = target.matches('a')
			}

			const triggers = this.options.trigger.split(' ')
			for (const trigger of triggers)
			{
				switch (trigger)
				{
					case 'hover':
						XF.on(target, pointerEnter + '.tooltip', this.mouseEnter.bind(this), { passive: true })
						XF.on(target, pointerLeave + '.tooltip', this.leave.bind(this), { passive: true })
						break

					case 'focus':
						XF.on(target, 'focusin.tooltip', this.focusEnter.bind(this))
						XF.on(target, 'focusout.tooltip', this.leave.bind(this))
						break

					case 'click':
						actOnClick = true

						XF.onPointer(target, 'click.tooltip', this.click.bind(this))
						XF.onPointer(target, 'auxclick.tooltip contextmenu.tooltip', () =>
						{
							this.cancelShow()
							this.stopFocusBefore = Date.now() + 2000
						})
						break

					case 'touchclick':
						actOnTouchClick = true
						XF.onPointer(target, 'click.tooltip', e =>
						{
							if (XF.isEventTouchTriggered(e))
							{
								this.click(e)
							}
						})
						break

					case 'touchhold':
					{
						actOnTouchClick = true

						const holdDuration = this.options.delayIn
						let holdTimer

						const touchHoldEvent = (e) =>
						{
							XF.DataStore.set(target, 'tooltip:taphold', true)
							if (XF.isEventTouchTriggered(e))
							{
								this.click(e)
							}
						}

						XF.onPointer(target, {
							'touchstart.tooltip': (e) =>
							{
								XF.DataStore.set(target, 'tooltip:touching', true)
								holdTimer = setTimeout(() => touchHoldEvent(e), holdDuration)
							},
							'touchend.tooltip': () =>
							{
								clearTimeout(holdTimer)
								setTimeout(() =>
								{
									XF.DataStore.remove(target, 'tooltip:touching')
								}, 50)
							},
							'touchmove.tooltip': () =>
							{
								clearTimeout(holdTimer)
							},
							'contextmenu.tooltip': (e) =>
							{
								if (XF.DataStore.get(target, 'tooltip:touching'))
								{
									e.preventDefault()
								}
							},
						}, null, { passive: true })

						break
					}
				}
			}

			if (actOnClick && actOnTouchClick)
			{
				console.error('Cannot have touchclick and click triggers')
			}

			if (!actOnClick && this.options.clickHide)
			{
				XF.onPointer(target, 'click.tooltip auxclick.tooltip contextmenu.tooltip', e =>
				{
					if (actOnTouchClick && XF.isEventTouchTriggered(e))
					{
						// other event already triggered
						return
					}

					this.hide()
					this.stopFocusBefore = Date.now() + 2000
				})
			}

			XF.on(target, 'tooltip:show', this.show.bind(this))
			XF.on(target, 'tooltip:hide', this.hide.bind(this))
			XF.on(target, 'tooltip:reposition', this.reposition.bind(this))
		},

		reposition ()
		{
			this.tooltip.reposition()
		},

		click (e)
		{
			if (e.button > 0 || e.ctrlKey || e.shiftKey || e.metaKey || e.altKey)
			{
				// non-primary clicks should prevent any hover behavior
				this.cancelShow()
				return
			}

			if (this.tooltip.isShown())
			{
				if (!this.tooltip.isShownFully())
				{
					// a click before the tooltip has finished animating or loading, so act as if the click triggered
					e.preventDefault()
					this.clickShow(e)
					return
				}

				this.hide()
			}
			else
			{
				e.preventDefault()
				this.clickShow(e)
			}
		},

		clickShow (e)
		{
			this.clickTriggered = true

			setTimeout(() =>
			{
				const covers = this.addCovers()

				if (XF.isEventTouchTriggered(e))
				{
					Array.from(covers).forEach(cover =>
					{
						cover.classList.add('is-active')
					})
				}
				else
				{
					const id = XF.uniqueId(this.target)
					XF.on(document, 'click.tooltip-' + id, this.docClick.bind(this))
				}
			}, 0)

			this.show()
		},

		addCovers ()
		{
			if (this.covers)
			{
				this.covers.forEach(cover => cover.remove())
			}

			const dimensions = XF.dimensions(this.target, true)
			const boxes = []

			// above
			boxes.push({
				top: 0,
				height: dimensions.top,
				left: 0,
				right: 0,
			})

			// left
			boxes.push({
				top: dimensions.top,
				height: dimensions.height,
				left: 0,
				width: dimensions.left,
			})

			// right
			boxes.push({
				top: dimensions.top,
				height: dimensions.height,
				left: dimensions.right,
				right: 0,
			})

			// below
			boxes.push({
				top: dimensions.bottom,
				height: document.documentElement.scrollHeight - dimensions.bottom,
				left: 0,
				right: 0,
			})

			const covers = []
			for (const box of boxes)
			{
				const boxEl = XF.createElement('div', {
					className: 'tooltipCover',
				})

				for (let prop in box)
				{
					boxEl.style[prop] = box[prop] + 'px'
					XF.on(boxEl, 'click', this.hide.bind(this))
				}

				covers.push(boxEl)
			}

			const tooltip = this.tooltip.getTooltip()
			covers.forEach(cover => tooltip.insertAdjacentElement('beforebegin', cover))

			this.covers = covers

			XF.setRelativeZIndex(covers, this.target)

			return covers
		},

		docClick (e)
		{
			let clicked,
				covers = this.covers,
				pageX = e.pageX,
				pageY = e.pageY

			if (!covers)
			{
				return
			}

			if (e.screenX == 0 && e.screenY == 0)
			{
				const dimensions = e.target.getBoundingClientRect()
				pageX = dimensions.left
				pageY = dimensions.top
			}

			covers.forEach(cover => cover.classList.add('is-active'))
			clicked = document.elementFromPoint(pageX - window.scrollX, pageY - window.scrollY)
			covers.forEach(cover => cover.classList.remove('is-active'))

			if (covers.includes(clicked))
			{
				this.hide()
			}
		},

		mouseEnter (e)
		{
			if (XF.isEventTouchTriggered(e))
			{
				// make touch tooltips only trigger on click
				this.touchEnterTime = e.timeStamp

				return
			}

			if (
				XF.browser.ios
				&& XF.supportsPointerEvents()
				&& e instanceof PointerEvent
				&& this.touchEnterTime
				&& this.touchEnterTime > e.timeStamp - 1000
			)
			{
				// iOS seems to trigger a touch-based pointer event immediately followed by a mouse-based
				// event, so we ignore the mouse event if it's within 1 second of the touch event
				return
			}

			this.enter()
		},

		focusEnter (e)
		{
			if (Date.now() - XF.pageDisplayTime < 100)
			{
				return
			}

			if (XF.isEventTouchTriggered(e))
			{
				// touch focus is likely a long press so don't trigger a tooltip for that
				// (make touch tooltips only trigger on click)
				return
			}

			// there are situations where a focus event happens and we don't want it to trigger a display
			if (!this.stopFocusBefore || Date.now() >= this.stopFocusBefore)
			{
				this.enter()
			}
		},

		enter ()
		{
			if (this.isShown() && this.clickTriggered)
			{
				// already shown by a click, don't trigger anything else
				return
			}

			this.clickTriggered = false

			const delay = this.tooltip.requiresLoad() ? this.options.delayInLoading : this.options.delayIn
			if (!delay)
			{
				this.show()
				return
			}

			if (this.delayTimeoutType !== 'enter')
			{
				this.resetDelayTimer()
			}

			if (!this.delayTimeoutType && !this.isShown())
			{
				this.delayTimeoutType = 'enter'

				this.delayTimeout = setTimeout(() =>
				{
					this.delayTimeoutType = null
					this.show()
				}, delay)
			}
		},

		leave ()
		{
			if (this.clickTriggered)
			{
				// when click toggled, only an explicit other action closes this
				return
			}

			const delay = this.options.delayOut
			if (!delay)
			{
				this.hide()
				return
			}

			if (this.delayTimeoutType !== 'leave')
			{
				this.resetDelayTimer()
			}

			if (!this.delayTimeoutType && this.isShown())
			{
				this.delayTimeoutType = 'leave'

				this.delayTimeout = setTimeout(() =>
				{
					this.delayTimeoutType = null
					this.hide()
				}, delay)
			}
		},

		show ()
		{
			const id = XF.uniqueId(this.target)
			XF.off(window, 'focus.tooltip-' + id)
			XF.on(window, 'focus.tooltip-' + id, e =>
			{
				this.stopFocusBefore = Date.now() + 250
			})

			XF.setRelativeZIndex(this.tooltip.getTooltip(), this.target)

			if (this.options.onShow)
			{
				const cb = this.options.onShow
				cb(this, this.tooltip)
			}

			this.tooltip.show()
		},

		cancelShow ()
		{
			if (this.delayTimeoutType === 'enter')
			{
				this.resetDelayTimer()
			}
			else if (!this.tooltip.isShownFully())
			{
				this.hide()
			}
		},

		hide ()
		{
			this.tooltip.hide()
			this.resetDelayTimer()
			this.clickTriggered = false

			if (this.covers)
			{
				this.covers.forEach(cover => cover.remove())
				this.covers = null
			}

			const id = XF.uniqueId(this.target)
			XF.off(document, 'click.tooltip-' + id)

			if (this.options.onHide)
			{
				const cb = this.options.onHide
				cb(this, this.tooltip)
			}
		},

		toggle ()
		{
			if (this.isShown())
			{
				this.hide()
			}
			else
			{
				this.show()
			}
		},

		isShown ()
		{
			return this.tooltip.isShown()
		},

		wasClickTriggered ()
		{
			return this.clickTriggered
		},

		resetDelayTimer ()
		{
			if (this.delayTimeoutType)
			{
				clearTimeout(this.delayTimeout)
				this.delayTimeoutType = null
			}
		},

		addMaintainElement (el)
		{
			if (XF.DataStore.get(el, 'tooltip-maintain'))
			{
				return
			}

			const triggers = this.options.trigger.split(' ')
			for (const trigger of triggers)
			{
				switch (trigger)
				{
					case 'hover':
						XF.on(el, 'mouseover.tooltip', this.enter.bind(this))
						XF.on(el, 'mouseout.tooltip', this.leave.bind(this))
						break

					case 'focus':
						XF.on(el, 'focusin.tooltip', this.enter.bind(this))
						XF.on(el, 'focusout.tooltip', this.leave.bind(this))
						break
				}
			}

			XF.DataStore.set(el, 'tooltip-maintain', true)
		},

		removeMaintainElement (el)
		{
			XF.off(el, '.tooltip')
			XF.DataStore.set(el, 'tooltip-maintain', false)
		},

		onTooltipSetup (tooltip)
		{
			if (this.options.maintain)
			{
				this.addMaintainElement(tooltip)

				XF.on(tooltip, 'menu:opened', e =>
				{
					this.addMaintainElement(e.menu)
				})
				XF.on(tooltip, 'menu:closed', e =>
				{
					this.removeMaintainElement(e.menu)
				})
			}
		},
	})
	XF.TooltipTrigger.cache = {}

	XF.TooltipOptions = {
		base: {
			// tooltip options
			baseClass: 'tooltip',
			extraClass: 'tooltip--basic',
			html: false,
			inViewport: true,
			placement: 'top',

			// trigger options
			clickHide: null,
			delayIn: 200,
			delayOut: 200,
			maintain: false,
			trigger: 'hover focus',
		},
		tooltip: [
			'baseClass',
			'extraClass',
			'html',
			'placement',
		],
		trigger: [
			'clickHide',
			'delayIn',
			'delayOut',
			'maintain',
			'trigger',
		],
		extract (keys, values)
		{
			const o = {}
			for (const key of keys)
			{
				o[key] = values[key]
			}

			return o
		},
		extractTooltip (values)
		{
			return this.extract(this.tooltip, values)
		},
		extractTrigger (values)
		{
			return this.extract(this.trigger, values)
		},
	}

	// ################################## AUTO COMPLETE ###########################################

	XF.AutoComplete = XF.Element.newHandler({
		loadTimer: null,
		loadVal: '',
		results: null,

		options: {
			single: false,
			multiple: ',', // multiple value joiner (used if single == true)
			acurl: '',
			minLength: 2, // min word length before lookup
			queryKey: 'q',
			extraFields: '',
			extraParams: {},
			jsonContainer: 'results',
			autosubmit: false,
		},

		abortController: null,

		init ()
		{
			const input = this.target

			if (this.options.autosubmit)
			{
				this.options.single = true
			}

			if (!this.options.acurl)
			{
				this.options.acurl = XF.getAutoCompleteUrl()
			}

			this.results = new XF.AutoCompleteResults({
				onInsert: this.addValue.bind(this),
			})

			input.setAttribute('autocomplete', 'off')

			XF.on(input, 'keydown', this.keydown.bind(this))
			XF.on(input, 'keyup', this.keyup.bind(this))
			XF.on(input, 'blur', this.blur.bind(this))
			XF.on(input, 'click', this.blur.bind(this))
			XF.on(input, 'paste', () => setTimeout(() =>
			{
				XF.trigger(input, 'keydown')
			}, 0))

			const form = input.closest('form')
			XF.on(form, 'submit', this.hideResults.bind(this))
		},

		keydown (e)
		{
			if (!this.results.isVisible())
			{
				return
			}

			const results = this.results
			const prevent = () =>
			{
				e.preventDefault()
				e.stopPropagation()
			}

			switch (e.key)
			{
				case 'ArrowDown':
					results.selectResult(1)
					return prevent()

				case 'ArrowUp':
					results.selectResult(-1)
					return prevent()

				case 'Escape':
					results.hideResults()
					return prevent()

				case 'Enter':
					results.insertSelectedResult(e)
					return prevent()
			}
		},

		keyup (e)
		{
			if (this.results.isVisible())
			{
				switch (e.key)
				{
					case 'ArrowDown':
					case 'ArrowUp':
					case 'Enter':
						return
				}
			}

			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer)
			}
			this.loadTimer = setTimeout(this.load.bind(this), 200)
		},

		blur (e)
		{
			clearTimeout(this.loadTimer)

			// timeout ensures that clicks still register
			setTimeout(this.hideResults.bind(this), 250)

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}
		},

		load ()
		{
			const lastLoad = this.loadVal,
				params = this.options.extraParams

			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer)
			}

			this.loadVal = this.getPartialValue()

			if (this.loadVal == '')
			{
				this.hideResults()
				return
			}

			if (this.loadVal == lastLoad)
			{
				return
			}

			if (this.loadVal.length < this.options.minLength)
			{
				return
			}

			params[this.options.queryKey] = this.loadVal

			if (this.options.extraFields != '')
			{
				const extraFields = document.querySelectorAll(this.options.extraFields)
				extraFields.forEach(field =>
				{
					params[field.name] = field.value
				})
			}

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable(
				'get',
				this.options.acurl,
				params,
				this.showResults.bind(this),
				{ error: false },
			)

			if (abortController)
			{
				this.abortController = abortController
			}
		},

		hideResults ()
		{
			this.results.hideResults()
		},

		showResults (results)
		{
			if (this.abortController)
			{
				this.abortController = null
			}

			if (this.options.jsonContainer && results)
			{
				results = results[this.options.jsonContainer]
			}

			this.results.showResults(this.getPartialValue(), results, this.target)
		},

		addValue (value)
		{
			if (this.options.single)
			{
				this.target.value = value
			}
			else
			{
				const values = this.getFullValues()
				if (value != '')
				{
					if (values.length)
					{
						value = ' ' + value
					}
					values.push(value + this.options.multiple + ' ')
				}
				this.target.value = values.join(this.options.multiple)
			}

			XF.trigger(this.target, 'change')

			XF.trigger(this.target, XF.customEvent('auto-complete:insert', {
				inserted: value.trim(),
				current: this.target.value,
			}))

			if (this.options.autosubmit)
			{
				const form = this.target.closest('form')
				if (XF.trigger(form, 'submit'))
				{
					form.submit()
				}
			}
			else
			{
				XF.autofocus(this.target)
			}
		},

		getFullValues ()
		{
			let val = this.target.value
			let splitPos = ''

			if (val == '')
			{
				return []
			}

			if (this.options.single)
			{
				return [val]
			}
			else
			{
				splitPos = val.lastIndexOf(this.options.multiple)
				if (splitPos == -1)
				{
					return []
				}
				else
				{
					val = val.substring(0, splitPos)
					return val.split(this.options.multiple)
				}
			}
		},

		getPartialValue ()
		{
			const val = this.target.value
			let splitPos

			if (this.options.single)
			{
				return val.trim()
			}
			else
			{
				splitPos = val.lastIndexOf(this.options.multiple)
				if (splitPos == -1)
				{
					return val.trim()
				}
				else
				{
					return val.substring(splitPos + this.options.multiple.length).trim()
				}
			}
		},
	})

	// ################################## HORIZONTAL SCROLLER HANDLER ###########################################

	XF.HScroller = XF.Element.newHandler({
		options: {
			scrollerClass: 'hScroller-scroll',
			actionClass: 'hScroller-action',
			autoScroll: '.tabs-tab.is-active',
		},

		scrollTarget: null,
		goStart: null,
		goEnd: null,

		init ()
		{
			const scrollTarget = this.target.querySelector('.' + this.options.scrollerClass)
			if (!scrollTarget)
			{
				console.error('no scroll target')
				return
			}

			this.scrollTarget = scrollTarget

			let x
			let y
			let dragged
			const ns = 'horizontalScroller'

			XF.on(scrollTarget, 'mousedown.' + ns, e =>
			{
				if (e.button)
				{
					// non-primary click
					return
				}

				x = e.clientX
				y = e.clientY
				dragged = false

				e.preventDefault()

				if (XF.isEventTouchTriggered(e))
				{
					// In touch browsers, we may have focus on an input which should have the keyboard showing.
					// When we trigger this and prevent the event, the focus is technically returned to the input,
					// which causes the soft keyboard to show again. In most cases, this isn't ideal, so blur
					// the input so we don't return focus.
					const focus = document.activeElement
					if (focus.matches('input, textarea, select, button'))
					{
						focus.blur()
					}
				}

				XF.on(window, 'mouseup.' + ns, e =>
				{
					XF.off(window, '.' + ns)

					if (dragged)
					{
						e.preventDefault()
					}
				})

				XF.on(window, 'mousemove.' + ns, e =>
				{
					const move = x - e.clientX
					if (move != 0)
					{
						if (this.move(move))
						{
							dragged = true
						}
						x = e.clientX
					}
				})
			})

			XF.on(scrollTarget, 'click.' + ns, e =>
			{
				if (dragged)
				{
					e.preventDefault()
					e.stopImmediatePropagation()
					dragged = false
				}
			})

			XF.on(scrollTarget, 'scroll.' + ns, this.updateScroll.bind(this), { passive: true })

			XF.on(scrollTarget, 'tab:click.' + ns, e =>
			{
				if (dragged)
				{
					e.preventDefault()
				}
			})

			const measure = XF.measureScrollBar(null, 'height')
			scrollTarget.classList.add('is-calculated')
			if (measure != 0)
			{
				scrollTarget.style.marginBottom = parseInt(window.getComputedStyle(scrollTarget).marginBottom, 10) - measure + 'px'
			}

			const actionClass = this.options.actionClass

			const goStart = XF.createElementFromString('<i class="' + actionClass + ' ' + actionClass + '--start" aria-hidden="true" />')
			XF.on(goStart, 'click', () => this.step(-1))
			scrollTarget.insertAdjacentElement('afterend', goStart)

			this.goStart = goStart

			const goEnd = XF.createElementFromString('<i class="' + actionClass + ' ' + actionClass + '--end" aria-hidden="true" />')
			XF.on(goEnd, 'click', () => this.step(1))
			scrollTarget.insertAdjacentElement('afterend', goEnd)

			this.goEnd = goEnd

			this.updateScroll()

			XF.on(document.body, 'xf:layout', this.updateScroll.bind(this))

			let resizeTimer
			XF.on(window, 'resize', () =>
			{
				if (resizeTimer)
				{
					clearTimeout(resizeTimer)
				}
				resizeTimer = setTimeout(this.updateScroll.bind(this), 100)
			})

			const autoScroll = scrollTarget.querySelector(this.options.autoScroll)
			if (autoScroll)
			{
				const ttWidth = this.target.clientWidth
				const dimensions = XF.dimensions(autoScroll)

				if (XF.isRtl())
				{
					if (dimensions.left < 80)
					{
						// This is a calculation to try to put the selected tab near the right edge.
						// -asRight gives a positive distance to scroll
						// + the full width displayed
						// - 50 to move it away from the right edge
						XF.normalizedScrollLeft(scrollTarget, -dimensions.right + ttWidth - 50)
					}
				}
				else
				{
					if (dimensions.right > ttWidth)
					{
						if (dimensions.right + 80 > ttWidth)
						{
							XF.normalizedScrollLeft(scrollTarget, dimensions.left - 50)
						}
						else
						{
							XF.normalizedScrollLeft(scrollTarget, dimensions.left - 80)
						}
					}
				}
			}
		},

		scrollToStart ()
		{
			this.scrollTo(0)
		},

		scrollToEnd ()
		{
			this.scrollTo(this.scrollTarget.scrollWidth)
		},

		scrollTo (action)
		{
			const target = this.scrollTarget
			const currentScroll = target.scrollLeft
			const scrollDistance = typeof action === 'number' ? action - currentScroll : Number(action.replace('+=', ''))
			let startTime = null

			const animateScroll = currentTime =>
			{
				if (!startTime)
				{
					startTime = currentTime
				}

				let progress = currentTime - startTime
				let newScrollPosition = currentScroll + (scrollDistance * progress / 150)

				target.scrollLeft = newScrollPosition

				if (progress < 150)
				{
					window.requestAnimationFrame(animateScroll)
				}
			}

			window.requestAnimationFrame(animateScroll)
		},

		move (amount)
		{
			const target = this.scrollTarget
			const left = XF.normalizedScrollLeft(target)

			if (XF.isRtl())
			{
				// Positive represents amount moved to right.
				// Since RTL scrolls the opposite way, need to account for that.
				amount *= -1
			}

			XF.normalizedScrollLeft(target, left + amount)

			return (XF.normalizedScrollLeft(target) !== left)
		},

		step (dir)
		{
			const scrollAmount = Math.max(125, Math.floor(this.scrollTarget.clientWidth * 0.25))
			let op = '+='

			switch (XF.scrollLeftType())
			{
				case 'inverted':
				case 'negative':
					op = '-='
			}

			this.scrollTo(op + (dir * scrollAmount))
		},

		updateScroll ()
		{
			const el = this.scrollTarget
			const left = XF.normalizedScrollLeft(el)
			const width = el.offsetWidth
			const scrollWidth = el.scrollWidth
			const startActive = (left > 0)
			const endActive = (width + left + 1 < scrollWidth)

			this.goStart.classList[startActive ? 'add' : 'remove']('is-active')
			this.goEnd.classList[endActive ? 'add' : 'remove']('is-active')
		},
	})

	XF.IconRenderer = XF.Element.newHandler({
		options: {
			inline: true,
			lazy: true,
		},

		icon: null,
		data: null,
		observer: null,

		init ()
		{
			if (this.target.nodeName !== 'I')
			{
				console.error(
					'Icon renderer must be applied to an <i> element: %o',
					this.target,
				)
				return
			}

			this.target.classList.add('fa--xf')
			this.target.innerHTML = '<svg></svg>'

			if (this.options.lazy)
			{
				this.setupObserver()
			}
			else
			{
				this.showIcon()
			}
		},

		setupObserver ()
		{
			this.observer = new IntersectionObserver(
				(entries) =>
				{
					const entry = entries[entries.length - 1]
					if (entry.isIntersecting)
					{
						this.showIcon()
					}
					else
					{
						this.hideIcon()
					}
				},
				{
					rootMargin: '100px',
				},
			)

			this.observer.observe(this.target)
		},

		async showIcon ()
		{
			const icon = await this.getIcon()
			this.target.innerHTML = icon.innerHTML
		},

		hideIcon ()
		{
			this.target.innerHTML = '<svg></svg>'
		},

		async getIcon ()
		{
			if (this.icon !== null)
			{
				return this.icon
			}

			const data = this.getData()

			let icon
			if (this.options.inline)
			{
				icon = await XF.Icon.getInlineIcon(
					data.variant,
					data.name,
					data.classes,
					data.title,
				)
			}
			else
			{
				icon = XF.Icon.getIcon(
					data.variant,
					data.name,
					data.classes,
					data.title,
				)
			}

			this.icon = XF.createElementFromString(icon)

			return this.icon
		},

		getData ()
		{
			if (this.data !== null)
			{
				return this.data
			}

			let variant = ''
			let name = ''

			for (const className of this.target.classList.values())
			{
				if (variant && name)
				{
					break
				}

				if (className.match(XF.Icon.ICON_CLASS_BLOCKLIST_REGEX))
				{
					continue
				}

				if (['fal', 'far', 'fas', 'fad', 'fab'].includes(className))
				{
					variant = XF.Icon.normalizeIconVariant(className)
					continue
				}

				if (className.match(XF.Icon.ICON_CLASS_REGEX))
				{
					name = XF.Icon.normalizeIconName(className)
					continue
				}
			}

			if (!variant)
			{
				variant = 'default'
			}

			if (!name)
			{
				throw new Error('No valid icon name class was found')
			}

			const classes = this.target.className

			const title = this.target.title
			this.target.removeAttribute('title')

			this.data = {
				variant,
				name,
				classes,
				title,
			}

			return this.data
		},
	})
	// ################################## INSTALL PROMPT HANDLER ###########################################

	XF.InstallPrompt = XF.Element.newHandler({
		options: {
			button: '| .js-installPromptButton',
			installTemplateIOS: '| .js-installTemplateIOS',
		},

		button: null,
		installTemplate: null,
		bipEvent: null,
		isIOS: false,

		init ()
		{
			this.button = XF.findRelativeIf(this.options.button, this.target)
			if (!this.button)
			{
				console.error('No install button found for %o', this.target)
				return
			}

			XF.on(window, 'beforeinstallprompt', this.beforeInstallPrompt.bind(this))
			XF.on(window, 'appinstalled', this.appInstalled.bind(this))

			if (XF.isIOS() && !XF.Feature.has('displaymodestandalone'))
			{
				this.initIOS()
			}

			XF.on(this.button, 'click', this.buttonClick.bind(this))
		},

		initIOS ()
		{
			this.isIOS = true
			XF.display(this.target)
		},

		beforeInstallPrompt (e)
		{
			e.preventDefault()
			this.bipEvent = e
			XF.display(this.target)
		},

		appInstalled (e)
		{
			XF.display(this.target, 'none')
		},

		buttonClick ()
		{
			if (this.isIOS)
			{
				const installTemplate = XF.findRelativeIf(this.options.installTemplateIOS, this.target)
				if (!installTemplate)
				{
					return
				}

				const fragment = installTemplate.content.cloneNode(true)
				const content = fragment.querySelector('.js-installTemplateContent')
				XF.overlayMessage(null, content)

				return
			}

			if (!this.bipEvent)
			{
				console.error('No beforeinstallprompt event was captured')
				return
			}

			this.bipEvent.prompt()
		},
	})

	// ################################## NUMBER BOX HANDLER ###########################################

	XF.NumberBox = XF.Element.newHandler({
		options: {
			textInput: '.js-numberBoxTextInput',
			buttonSmaller: false,
			step: null,
		},

		textInput: null,

		holdTimeout: null,
		holdInterval: null,

		init ()
		{
			const target = this.target
			const textInput = target.querySelector(this.options.textInput)

			if (!textInput)
			{
				console.error('Cannot initialize, no text input.')
				return
			}

			this.textInput = textInput

			target.classList.add('inputGroup--joined')

			let up = target.querySelector('.js-up')
			let down = target.querySelector('.js-down')

			if (!up)
			{
				up = this.createButton('up')
			}
			if (!down)
			{
				down = this.createButton('down')
			}

			this.setupButton(up, textInput)
			this.setupButton(down, up)
		},

		createButton (dir)
		{
			const button = XF.createElement('button', {
				type: 'button',
				tabIndex: -1,
				title: XF.phrases['number_button_' + dir] || dir,
				ariaLabel: XF.phrases['number_button_' + dir] || dir,
				dataset: { dir: dir },
				className: `inputGroup-text inputNumber-button inputNumber-button--${ dir } js-${ dir }`,
			})

			if (this.textInput.disabled || this.textInput.readOnly)
			{
				button.classList.add('is-disabled')
				button.disabled = true
			}

			if (this.options.buttonSmaller)
			{
				button.classList.add('inputNumber-button--smaller')
			}

			return button
		},

		setupButton (button, insertRef)
		{
			XF.on(button, 'focus', this.buttonFocus.bind(this))
			XF.on(button, 'click', this.buttonClick.bind(this))
			XF.on(button, 'mousedown', this.buttonMouseDown.bind(this))
			XF.on(button, 'touchstart', this.buttonMouseDown.bind(this), {
				passive: true,
			})
			XF.on(button, 'mouseleave', this.buttonMouseUp.bind(this))
			XF.on(button, 'mouseup', this.buttonMouseUp.bind(this))
			XF.on(button, 'touchend', this.buttonMouseUp.bind(this), {
				passive: true,
			})
			XF.on(button, 'touchend', e =>
			{
				e.preventDefault()

				button.click()
			})

			insertRef.insertAdjacentElement('afterend', button)
		},

		buttonFocus (e)
		{
			e.preventDefault()
			e.stopPropagation()
		},

		buttonClick (e)
		{
			this.step(e.target.dataset.dir)
		},

		step (dir)
		{
			const textInput = this.textInput
			const fnName = 'step' + dir.charAt(0).toUpperCase() + dir.slice(1)

			if (textInput.readonly)
			{
				return
			}

			if (textInput.value === '')
			{
				textInput.value = textInput.getAttribute('min') || 0
			}

			const stepAny = textInput.step === 'any'
			if (stepAny)
			{
				textInput.step = 1
			}

			textInput[fnName]()

			if (stepAny)
			{
				textInput.step = 'any'
			}

			XF.trigger(textInput, 'change')
			XF.trigger(textInput, 'input')
		},

		buttonMouseDown (e)
		{
			this.buttonMouseUp(e)

			this.holdTimeout = setTimeout(() =>
			{
				this.holdInterval = setInterval(() =>
				{
					this.step(e.target.dataset.dir)
				}, 75)
			}, 500)
		},

		buttonMouseUp (e)
		{
			clearTimeout(this.holdTimeout)
			clearInterval(this.holdInterval)
		},
	})

	// ################################## PAGE JUMP HANDLER ###########################################

	XF.PageJump = XF.Element.newHandler({
		options: {
			pageUrl: null,
			pageInput: '| .js-pageJumpPage',
			pageSubmit: '| .js-pageJumpGo',
			sentinel: '%page%',
		},

		input: null,

		init ()
		{
			if (!this.options.pageUrl)
			{
				console.error('No page-url provided to page jump')
				return
			}

			this.input = XF.findRelativeIf(this.options.pageInput, this.target)
			if (!this.input)
			{
				console.error('No input provided to page jump')
				return
			}

			XF.on(this.input, 'keyup', e =>
			{
				if (e.key === 'Enter')
				{
					e.preventDefault()
					this.go()
				}
			})

			let pageSubmit = XF.findRelativeIf(this.options.pageSubmit, this.target)
			XF.on(pageSubmit, 'click', e =>
			{
				e.preventDefault()
				this.go()
			})

			let menu = this.target.closest('.menu')
			XF.on(menu, 'menu:opened', () => this.shown())
		},

		shown ()
		{
			this.input.select()
		},

		go ()
		{
			let page = parseInt(this.input.value, 10)
			if (isNaN(page) || page < 1)
			{
				page = 1
			}

			const baseUrl = this.options.pageUrl
			const sentinel = this.options.sentinel
			let url = baseUrl.replace(sentinel, page)

			if (url === baseUrl)
			{
				url = baseUrl.replace(encodeURIComponent(sentinel), page)
			}

			XF.redirect(url)
		},
	})

	// ################################## QUICK SEARCH ###########################################

	XF.QuickSearch = XF.Element.newHandler({
		options: {
			select: '| .js-quickSearch-constraint',
		},

		select: null,

		init ()
		{
			this.select = XF.findRelativeIf(this.options.select, this.target)

			if (!this.select)
			{
				return
			}

			XF.on(this.select, 'change', this.updateSelectWidth.bind(this))
			this.updateSelectWidth()
		},

		updateSelectWidth ()
		{
			let selectProxy = XF.createElement('span', {
				className: `${ this.select.className } input--select`,
			})

			let selected = this.select.querySelector('option:checked')
			if (!selected)
			{
				selected = this.select.querySelector('option')
			}

			selectProxy.textContent = selected.textContent
			XF.display(selectProxy, 'inline')

			let positioner = XF.createElement('div', {
				style: {
					position: 'absolute',
					top: '-200px',
					visibility: 'hidden',
				},
			}, document.body)
			positioner.style[XF.isRtl() ? 'right' : 'left'] = '-9999px'
			positioner.appendChild(selectProxy)

			// give a little extra space just in case; potential iOS quirk without it
			this.select.style.width = `${ selectProxy.offsetWidth + 8 }px`
			this.select.style.flexGrow = 0
			this.select.style.flexShrink = 0

			document.body.removeChild(positioner)
		},
	})

	XF.SearchAutoComplete = XF.extend(XF.AutoComplete, {
		__backup: {
			'init': '_init',
			'keydown': '_keydown',
			'load': '_load',
		},

		form: null,

		init ()
		{
			if (!this.options.acurl)
			{
				console.error('No auto-complete URL was provided: %o', this.target)
				return
			}

			const form = this.target.closest('form')
			if (!form)
			{
				console.error('No form was found: %o', this.target)
				return
			}

			this.form = form

			this.results = new XF.SearchAutoCompleteResults({
				showDescriptions: this.options.showDescriptions,
			})

			this.target.setAttribute('autocomplete', 'off')

			XF.on(this.target, 'keydown', this.keydown.bind(this))
			XF.on(this.target, 'keyup', this.keyup.bind(this))
			XF.on(this.target, 'input', () => setTimeout(() =>
			{
				XF.trigger(this.target, 'keyup')
			}, 0))
		},

		keydown (e)
		{
			if (
				e.key === 'Enter' &&
				this.results.isVisible() &&
				this.results.selectedResult < 0
			)
			{
				return
			}

			this._keydown(e)
		},

		load ()
		{
			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer)
			}

			const value = this.getPartialValue()
			if (value === this.loadVal)
			{
				return
			}

			this.loadVal = value

			if (value === '')
			{
				this.hideResults()
				return
			}

			if (this.loadVal.length < this.options.minLength)
			{
				return
			}

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			const {
				_,
				abortController,
			} = XF.ajaxAbortable(
				'POST',
				this.options.acurl,
				this.form,
				this.showResults.bind(this),
				{
					skipDefault: true,
					skipError: true,
					global: false,
				},
			)

			if (abortController)
			{
				this.abortController = abortController
			}
		},
	})

	XF.SearchAutoCompleteResults = XF.extend(XF.AutoCompleteResults, {
		__backup: {
			'__construct': '___construct',
			'createResultItem': '_createResultItem',
			'getResultItemParams': '_getResultItemParams',
		},

		__construct (options)
		{
			options = XF.extendObject({
				onInsert: this.openResult.bind(this),
				displayTemplate: this.getDisplayTemplate(),
				wrapperClasses: 'autoCompleteList--fullWidth',
			}, options)

			this.___construct(options)
		},

		createResultItem (result, value)
		{
			const listItem = this._createResultItem(result, value)

			XF.on(listItem, 'auxclick', (e) =>
			{
				e.preventDefault()
				this.resultClick(e)
			})

			return listItem
		},

		getResultItemParams (result, value)
		{
			const params = this._getResultItemParams(result, value)

			params.desc = params.descPlain

			return params
		},

		highlightResultText (text, value)
		{
			const innerPattern = XF.regexQuote(XF.htmlspecialchars(value))
				.split(/\s+/)
				.join('|')
			const pattern = new RegExp(`\\b(${innerPattern})`, 'gi')

			return text.replace(pattern, match => `<strong>${ match }</strong>`)
		},

		prepareResults (target)
		{
			if (this.results.connected)
			{
				return
			}

			target.closest('.menu-row, li').append(this.results)
			this.selectResult(-1, true)
		},

		getDisplayTemplate ()
		{
			return `<div class="contentRow contentRow--alignMiddle" title="{{{textPlain}}}">
						{{#icon}}<div class="contentRow-figure">{{{icon}}}</div>{{/icon}}
						<div class="contentRow-main contentRow-main--close u-singleLine">
							<a href="{{{url}}}">{{{text}}}</a>
							<div class="contentRow-minor contentRow-minor--smaller">
								<ul class="listInline listInline--bullet u-singleLine">
									<li>{{{type}}}</li>
									{{#desc}}<li>{{{desc}}}</li>{{/desc}}
								</ul>
							</div>
						</div>
					</div>`
		},

		openResult (_, res, e)
		{
			const url = res.querySelector('a').getAttribute('href')
			if (!url)
			{
				return false
			}

			if (e instanceof PointerEvent)
			{
				if (
					(e.button === 0 && XF.isMac() ? e.metaKey : e.ctrlKey) ||
					e.button === 1
				)
				{
					window.open(url, '_blank')
					return false
				}

				if (e.button !== 0)
				{
					return false
				}
			}

			XF.redirect(url)
			return false
		},
	})

	// ################################## SHARE BUTTONS HANDLER ###########################################

	XF.ShareButtons = XF.Element.newHandler({
		options: {
			buttons: '.shareButtons-button',
			iconic: '.shareButtons--iconic',
			pageUrl: null,
			pageTitle: null,
			pageDesc: null,
			pageImage: null,
		},

		pageUrl: null,
		pageTitle: null,
		pageDesc: null,
		pageImage: null,

		init ()
		{
			const buttonSel = this.options.buttons
			const iconicClass = this.options.iconic
			const iconic = this.target.matches(iconicClass)

			XF.onDelegated(this.target, 'focus', buttonSel, this.focus.bind(this))
			XF.onDelegated(this.target, 'mouseover', buttonSel, this.focus.bind(this))
			XF.onDelegated(this.target, 'click', buttonSel, this.click.bind(this))

			Array.from(this.target.querySelectorAll(buttonSel)).forEach(el =>
			{
				if (iconic)
				{
					XF.Element.applyHandler(el, 'element-tooltip', {
						element: '> span',
					})
				}
				if (el.dataset.clipboard && navigator.clipboard)
				{
					el.classList.remove('is-hidden')
				}
			})
		},

		setupPageData ()
		{
			if (this.options.pageTitle && this.options.pageTitle.length)
			{
				this.pageTitle = this.options.pageTitle
			}
			else
			{
				this.pageTitle = document.querySelector('meta[property="og:title"]')?.content
				if (!this.pageTitle)
				{
					this.pageTitle = document.querySelector('title')?.textContent
				}
			}

			if (this.options.pageUrl && this.options.pageUrl.length)
			{
				this.pageUrl = this.options.pageUrl
			}
			else
			{
				let overlay = this.target.closest('.overlay')
				if (overlay)
				{
					this.pageUrl = overlay.dataset.url
				}

				if (!this.pageUrl)
				{
					this.pageUrl = document.querySelector('meta[property="og:url"]')?.content
				}
				if (!this.pageUrl)
				{
					this.pageUrl = window.location.href
				}
			}

			if (this.options.pageDesc && this.options.pageDesc.length)
			{
				this.pageDesc = this.options.pageDesc
			}
			else
			{
				this.pageDesc = document.querySelector('meta[property="og:description"]')?.content
				if (!this.pageDesc)
				{
					this.pageDesc = document.querySelector('meta[name=description]')?.content || ''
				}
			}

			if (this.options.pageImage && this.options.pageImage.length)
			{
				this.pageImage = this.options.pageImage
			}
			else
			{
				this.pageImage = document.querySelector('meta[property="og:image"]')?.content
				if (!this.pageImage)
				{
					this.pageImage = XF.config.publicMetadataLogoUrl || ''
				}
			}
		},

		focus (e)
		{
			const target = e.target.closest(this.options.buttons)

			if (target.dataset.initialized)
			{
				return
			}

			if (target.matches('.shareButtons-button--share'))
			{
				return
			}

			if (!this.pageUrl)
			{
				this.setupPageData()
			}

			let href = target.dataset.href
			if (!href)
			{
				if (target.dataset.clipboard)
				{
					// handled on click
					return
				}
				else
				{
					console.error('No data-href or data-clipboard on share button %o', e.currentTarget)
				}
			}

			href = href.replace('{url}', encodeURIComponent(this.pageUrl))
				.replace('{title}', encodeURIComponent(this.pageTitle))
				.replace('{desc}', encodeURIComponent(this.pageDesc))
				.replace('{image}', encodeURIComponent(this.pageImage))

			target.setAttribute('href', href)
			target.dataset.initialized = true
		},

		click (e)
		{
			const target = e.target.closest(this.options.buttons)
			const href = target.getAttribute('href')
			const popupWidth = target.dataset.popupWidth || 600
			const popupHeight = target.dataset.popupHeight || 400

			if (target.matches('.shareButtons-button--share'))
			{
				return
			}

			if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey)
			{
				return
			}

			if (target.dataset.clipboard)
			{
				e.preventDefault()

				const text = target.dataset.clipboard
					.replace('{url}', this.pageUrl)
					.replace('{title}', this.pageTitle)
					.replace('{desc}', this.pageDesc)
					.replace('{image}', this.pageImage)

				navigator.clipboard.writeText(text)
					.then(() => XF.flashMessage(XF.phrase('link_copied_to_clipboard'), 3000))
			}
			else if (href && href.match(/^https?:/i))
			{
				e.preventDefault()

				const popupLeft = (screen.width - popupWidth) / 2,
					popupTop = (screen.height - popupHeight) / 2

				window.open(
					href,
					'share',
					'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes'
					+ ',width=' + popupWidth + ',height=' + popupHeight
					+ ',left=' + popupLeft + ',top=' + popupTop,
				)
			}
		},
	})

	// ################################## STICKY ELEMENTS ###########################################

	XF.Sticky = XF.Element.newHandler({
		options: {
			stickyClass: 'is-sticky',
			stickyDisabledClass: 'is-sticky-disabled',
			minWindowHeight: 0,

			setCss: true,
			offsetTop: null,
		},

		active: null,
		offsetTop: null,

		init ()
		{
			if (this.options.offsetTop === null)
			{
				this.offsetTop = parseInt(window.getComputedStyle(this.target).top || 0, 10)
			}
			else
			{
				this.offsetTop = this.options.offsetTop
			}

			if (this.options.minWindowHeight === 0)
			{
				this.enable()
			}
			else
			{
				this.update()
				XF.on(window, 'resize.sticky-header', this.update.bind(this))
			}
		},

		update ()
		{
			const windowTooSmall = (document.documentElement.clientHeight < this.options.minWindowHeight)

			if (this.active && windowTooSmall)
			{
				this.disable()
			}
			else if (!this.active && !windowTooSmall)
			{
				this.enable()
			}
		},

		enable ()
		{
			this.active = true

			this.target.classList.remove(this.options.stickyDisabledClass)

			if (this.options.setCss)
			{
				this.target.style.position = 'sticky'
				this.target.style.top = this.offsetTop + 'px'
			}

			this.checkIsSticky()
			XF.on(window, 'scroll.sticky-header', this.checkIsSticky.bind(this), { passive: true })
		},

		disable ()
		{
			this.active = false

			this.target.classList.add(this.options.stickyDisabledClass)
			this.target.classList.remove(this.options.stickyClass)

			if (this.options.setCss)
			{
				this.target.style.position = 'static'
				this.target.style.top = 'auto'
			}

			XF.off(window, 'scroll.sticky-header')
		},

		checkIsSticky ()
		{
			const targetTop = Math.floor(this.target.getBoundingClientRect().top)
			const stickyTop = this.offsetTop

			if (targetTop < stickyTop || (targetTop === stickyTop && window.scrollY > 0))
			{
				this.target.classList.add(this.options.stickyClass)
				XF.layoutChange()
			}
			else
			{
				this.target.classList.remove(this.options.stickyClass)
				XF.layoutChange()
			}
		},
	})

	// ################################## STICKY HEADER ###########################################

	XF.StickyHeader = XF.extend(XF.Sticky, {
		__backup: {
			init: '_init',
		},

		options: XF.extendObject({}, XF.Sticky.prototype.options, {
			minWindowHeight: 251,
			setCss: false,
		}),

		init ()
		{
			this._init()

			XF.StickyHeader.cache.push(this)
		},
	})
	XF.StickyHeader.cache = []

	XF.StyleVariationInput = XF.Element.newHandler({
		options: {
			variationContainer: '< form | .js-variationContainer',
			variationInput: '[name="user[style_variation]"]',
		},

		variationContainer: null,

		init ()
		{
			const variationContainer = XF.findRelativeIf(
				this.options.variationContainer,
				this.target,
			)
			if (!variationContainer)
			{
				console.error('No variation container found for %o', this.target)
				return
			}

			this.variationContainer = variationContainer
			XF.on(this.target, 'change', this.change.bind(this))
		},

		change ()
		{
			const variationInput = this.variationContainer.querySelector(
				this.options.variationInput,
			)
			XF.ajax(
				'POST',
				XF.canonicalizeUrl('index.php?misc/style-variation-input'),
				{
					style_id: this.target.value,
					style_variation: variationInput.value,
				},
				this.handleResponse.bind(this),
			)
		},

		handleResponse (data)
		{
			XF.setupHtmlInsert(data.html, (html) =>
			{
				if (html instanceof HTMLInputElement)
				{
					XF.Animate.fadeUp(this.variationContainer, {
						complete: () =>
						{
							this.variationContainer.innerHTML = ''
							this.variationContainer.appendChild(html)
						},
					})
				}
				else
				{
					this.variationContainer.innerHTML = ''
					this.variationContainer.appendChild(html)
					XF.Animate.fadeDown(this.variationContainer)
				}
			})
		},
	})

	// ################################## BASIC TOOLTIP ###########################################

	XF.Tooltip = XF.Element.newHandler({
		options: XF.extendObject(true, {}, XF.TooltipOptions.base, {
			content: null,
		}),

		trigger: null,
		tooltip: null,

		init ()
		{
			const tooltipContent = this.getContent(),
				tooltipOptions = XF.TooltipOptions.extractTooltip(this.options),
				triggerOptions = XF.TooltipOptions.extractTrigger(this.options)

			this.tooltip = new XF.TooltipElement(tooltipContent, tooltipOptions)
			this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, triggerOptions)

			this.trigger.init()

			XF.on(this.target, 'tooltip:refresh', this.refresh.bind(this))
		},

		getContent ()
		{
			if (this.options.content)
			{
				return this.options.content
			}
			else if (this.target instanceof SVGElement)
			{
				const titleEl = this.target.querySelector('title')

				const title = this.target.getAttribute('data-original-title')
					?? titleEl?.textContent
					?? ''

				this.target.setAttribute('data-original-title', title)
				titleEl?.remove()

				return title
			}
			else
			{
				const title = this.target.getAttribute('data-original-title')
					?? this.target.getAttribute('title')
					?? ''

				this.target.setAttribute('data-original-title', title)
				this.target.removeAttribute('title')

				if (this.target.children.length === 0)
				{
					this.target.setAttribute('aria-label', title)
				}

				return title
			}
		},

		refresh ()
		{
			this.tooltip.setContent(this.getContent())
		},
	})

	// ############################## ELEMENT TOOLTIPS ###############################

	XF.ElementTooltip = XF.extend(XF.Tooltip, {
		__backup: {
			getContent: '_getContent',
			init: '_init',
		},

		options: XF.extendObject({}, XF.Tooltip.prototype.options, {
			element: null,
			showError: true,
			noTouch: true,
			shortcut: null,
		}),

		element: null,

		init ()
		{
			if (this.options.shortcut)
			{
				this.setupShortcut(this.options.shortcut)
			}

			if (this.options.noTouch && XF.Feature.has('touchevents'))
			{
				return
			}

			const element = this.options.element,
				showError = this.options.showError

			if (!element)
			{
				if (showError)
				{
					console.error('No element specified for the element tooltip')
				}
				return
			}

			let el = XF.findRelativeIf(element, this.target)
			if (el instanceof NodeList)
			{
				el = el[0]
			}
			if (!el)
			{
				if (showError)
				{
					console.error('Element tooltip could not find ' + element)
				}

				return
			}

			this.element = el
			this.target.removeAttribute('title')
			this.options.html = true
			this._init()
		},

		setupShortcut (shortcut)
		{
			if (shortcut == 'node-description')
			{
				if (!this.options.element)
				{
					this.options.element = '< .js-nodeMain | .js-nodeDescTooltip'
				}

				this.options.showError = false
				this.options.maintain = true
				this.options.placement = 'right'
				this.options.extraClass = 'tooltip--basic tooltip--description'
			}
		},

		getContent ()
		{
			return this.element.childNodes
		},
	})

	// ################################## MEMBER TOOLTIP ###########################################

	XF.MemberTooltipCache = {}

	XF.MemberTooltip = XF.Element.newHandler({
		options: {
			delay: 600,
		},

		trigger: null,
		tooltip: null,
		userId: null,

		init ()
		{
			this.userId = this.target.dataset.userId

			this.tooltip = new XF.TooltipElement(this.getContent.bind(this), {
				extraClass: 'tooltip--member',
				html: true,
				loadRequired: true,
			})

			this.trigger = new XF.TooltipTrigger(this.target, this.tooltip, {
				maintain: true,
				delayInLoading: this.options.delay,
				delayIn: this.options.delay,
				trigger: 'hover focus click',
				onShow: this.onShow.bind(this),
				onHide: this.onHide.bind(this),
			})

			this.trigger.init()
		},

		getContent (onContent)
		{
			const userId = this.userId,
				existing = XF.MemberTooltipCache[userId]

			if (existing)
			{
				const content = XF.createElementFromString(existing.trim())
				onContent(existing)
				return
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
				this.target.getAttribute('href'),
				{ tooltip: true },
				data => this.loaded(data, onContent),
				options,
			)
		},

		loaded (data, onContent)
		{
			if (!data.html)
			{
				return
			}

			const userId = this.userId

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				XF.MemberTooltipCache[userId] = data.html.content

				onContent(data.html.content)
			})
		},

		onShow ()
		{
			const activeTooltip = XF.MemberTooltip.activeTooltip
			if (activeTooltip && activeTooltip !== this)
			{
				activeTooltip.hide()
			}

			XF.MemberTooltip.activeTooltip = this
		},

		onHide ()
		{
			// it's possible for another show event to trigger so don't empty this if it isn't us
			if (XF.MemberTooltip.activeTooltip === this)
			{
				XF.MemberTooltip.activeTooltip = null
			}
		},

		show ()
		{
			this.trigger.show()
		},

		hide ()
		{
			this.trigger.hide()
		},
	})
	XF.MemberTooltip.activeTooltip = null

	// ################################## NOTICES ###########################################

	XF.Notices = XF.Element.newHandler({
		options: {
			type: 'block',
			target: '.js-notice',
			scrollInterval: 5,
		},

		notices: null,
		slider: null,
		dismissing: false,

		init ()
		{
			this.updateNoticeList()
			this.filter()
			if (!this.handleNoticeListChange())
			{
				return
			}

			XF.onDelegated(this.target, 'click', '.js-noticeDismiss', this.dismiss.bind(this))

			this.start()
		},

		updateNoticeList ()
		{
			this.notices = this.target.querySelectorAll(`${ this.options.target }`)
			if (this.options.type === 'scrolling')
			{
				this.target.style.overflow = 'hidden'
				this.notices.forEach(notice => notice.classList.add('f-carousel__slide'))
			}
			return this.notices
		},

		handleNoticeListChange ()
		{
			const length = this.notices.length

			if (!length)
			{
				if (this.slider)
				{
					this.slider.destroy()
					this.slider = null
				}

				this.target.remove()
			}
			else if (length === 1)
			{
				this.target.classList.remove('notices--isMulti')
			}

			return length
		},

		filter ()
		{
			const dismissed = this.getCookies()
			let modified = false

			this.notices.forEach(notice =>
			{
				const id = parseInt(notice.dataset.noticeId, 10)
				const visibility = notice.dataset.visibility

				if (dismissed)
				{
					if (id && dismissed.includes(id))
					{
						notice.remove()
						modified = true
					}
				}

				if (visibility)
				{
					if (window.getComputedStyle(notice).visibility === 'hidden')
					{
						notice.remove()
						modified = true
					}
					else
					{
						notice.classList.add('is-vis-processed')
					}
				}
			})

			if (modified)
			{
				this.updateNoticeList()
			}
		},

		start ()
		{
			const notices = this.notices
			const noticeType = this.options.type

			if (noticeType === 'floating')
			{
				notices.forEach(notice =>
				{
					const displayDuration = parseInt(notice.dataset.displayDuration, 10)
					const delayDuration = parseInt(notice.dataset.delayDuration, 10)
					const autoDismiss = notice.dataset.autoDismiss

					if (delayDuration)
					{
						setTimeout(() =>
						{
							this.displayFloating(notice, XF.config.speed.normal, displayDuration, autoDismiss)
						}, delayDuration)
					}
					else
					{
						this.displayFloating(notice, XF.config.speed.fast, displayDuration, autoDismiss)
					}
				})
			}
			else if (noticeType === 'scrolling' && this.notices.length > 1)
			{
				this.slider = new Carousel(this.target, {
					Autoplay: {
						showProgress: false,
						timeout: this.options.scrollInterval * 1000,
					},
					Dots: {
						minCount: 2,
					},
					Navigation: false,
					direction: XF.isRtl() ? 'rtl' : 'ltr',
					on: {
						ready: () =>
						{
							// otherwise dots navigation is hidden
							this.target.style.overflow = 'visible'
						},
					},
					l10n: XF.CarouselL10n(),
				}, { Autoplay })
			}
		},

		displayFloating (notice, speed, duration, autoDismiss)
		{
			XF.Animate.fadeDown(notice, {
				speed,
				complete ()
				{
					if (duration)
					{
						setTimeout(() =>
						{
							XF.Animate.fadeUp(notice)

							if (autoDismiss)
							{
								XF.trigger(notice.querySelector('a.js-noticeDismiss'), 'click')
							}
						}, duration)
					}
				},
			})
		},

		getCookies ()
		{
			if (XF.config.userId)
			{
				return
			}

			const cookieName = 'notice_dismiss'
			const cookieValue = XF.Cookie.get(cookieName)
			const cookieDismissed = cookieValue ? cookieValue.split(',') : []
			const values = []

			for (let id of cookieDismissed)
			{
				id = parseInt(id, 10)

				if (id === -1 && XF.Cookie.getConsentMode() === 'advanced')
				{
					continue
				}

				if (id)
				{
					values.push(id)
				}
			}

			return values
		},

		dismiss (e)
		{
			e.preventDefault()

			if (this.dismissing)
			{
				return
			}

			this.dismissing = true

			const target = e.target
			const notice = target.closest('.js-notice')
			const noticeId = parseInt(notice.dataset.noticeId, 10)
			const cookieName = 'notice_dismiss'
			const userId = XF.config.userId
			const dismissed = this.getCookies()

			if (!userId)
			{
				if (noticeId && dismissed.indexOf(noticeId) === -1)
				{
					dismissed.push(noticeId)
					dismissed.sort((a, b) => a - b)

					// expire notice cookies in one month
					let expiry = new Date()
					expiry.setUTCMonth(expiry.getUTCMonth() + 1)
					XF.Cookie.set(cookieName, dismissed.join(','), expiry)
				}
			}
			else
			{
				XF.ajax(
					'post',
					target.getAttribute('href'),
					{},
					null,
					{ skipDefault: true },
				)
			}

			this.removeNotice(notice)
			this.dismissing = false
		},

		removeNotice (notice)
		{
			const total = this.notices.length
			let current

			const removeSlide = () =>
			{
				this.slider.removeSlide(current)
				this.updateNoticeList()
				if (this.handleNoticeListChange())
				{
					this.slider.reInit()
				}
			}

			if (this.slider)
			{
				current = this.slider.page

				if (total > 1)
				{
					if (current >= this.slider.slides.length)
					{
						current = 1
					}
					setTimeout(removeSlide, 500)
				}
				else
				{
					removeSlide()
				}
			}
			else
			{
				XF.Animate.fadeUp(notice, {
					speed: XF.config.speed.fast,
					complete: () =>
					{
						notice.remove()
						this.updateNoticeList()
						this.handleNoticeListChange()
					},
				})
			}
		},
	})

	XF.CookieConsentForm = XF.Element.newHandler({
		options: {
			container: 'article.message',
		},

		embedHtml: null,
		containerElement: null,
		anchor: null,

		replace: false,

		init ()
		{
			this.containerElement = this.target.closest(this.options.container)
			if (this.containerElement)
			{
				this.anchor = this.containerElement.querySelector('.u-anchorTarget')
			}

			this.embedHtml = this.target.querySelector('.js-embedHtml')
			if (this.embedHtml)
			{
				this.replace = this.supportsTemplateElement()
			}

			XF.on(document, 'ajax-submit:response', this.response.bind(this))
			XF.on(document, 'cookie-consent:load', this.load.bind(this))
		},

		response ({ data })
		{
			if (data['unconsented_local_storage'])
			{
				XF.LocalStorage.removeMultiple(data['unconsented_local_storage'])
			}
			if (data['group_consent_state'])
			{
				for (let group in data['group_consent_state'])
				{
					let input = document.querySelector('.js-consent_' + group)
					input.checked = data['group_consent_state'][group]
				}
			}

			XF.trigger(document, 'cookie-consent:load', [this.target])
		},

		load ({ target })
		{
			if (!this.replace || !this.embedHtml)
			{
				return
			}

			this.target.replaceWith(this.embedHtml.content.cloneNode(true))

			if (this.target === target && this.anchor)
			{
				this.anchor.scrollIntoView()
			}

			XF.activate(this.containerElement)
		},

		supportsTemplateElement ()
		{
			return 'content' in document.createElement('template')
		},
	})

	XF.CookieConsentToggle = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFCookieConsentToggle',

		notice: null,

		init ()
		{
			const notice = document.querySelector('.notice[data-notice-id="-1"]')

			if (!notice)
			{
				console.error(`No cookie consent notice found for ${ this.target }`)
			}

			this.notice = notice
		},

		click (e)
		{
			e.preventDefault()

			if (window.getComputedStyle(this.notice).display !== 'none')
			{
				XF.Animate.slideUp(this.notice, {
					speed: XF.config.speed.normal,
					complete: XF.layoutChange,
				})
			}
			else
			{
				XF.Animate.slideDown(this.notice, {
					speed: XF.config.speed.normal,
					complete: XF.layoutChange,
				})
			}
		},
	})

	// ################################## TOUCH PROXY ELEMENTS ###########################################

	XF.TouchProxy = XF.Element.newHandler({
		options: {
			allowed: 'input, textarea, checkbox, a, label, [data-tp-clickable], [data-tp-primary]',
		},

		active: true,
		timer: null,

		proxy: null,

		init ()
		{
			if ('InputDeviceCapabilities' in window || 'sourceCapabilities' in UIEvent.prototype)
			{
				XF.on(this.target, 'click', e =>
				{
					if (!e.sourceCapabilities || !e.sourceCapabilities.firesTouchEvents)
					{
						return
					}
					this.handleTapEvent(e)
				})
			}
			else if (XF.Feature.has('touchevents'))
			{
				let moved = false

				XF.on(this.target, 'touchstart', () =>
				{
					moved = false
				}, { passive: true })

				XF.on(this.target, 'touchmove', () =>
				{
					moved = true
				}, { passive: true })

				XF.on(this.target, 'touchend', e =>
				{
					if (!moved)
					{
						this.handleTapEvent(e)
					}
				}, { passive: true })
			}
		},

		isClickable (clicked)
		{
			let closest = clicked.closest(this.options.allowed)
			return closest !== null && this.target.contains(closest)
		},

		handleTapEvent (e)
		{
			if (!this.getProxy())
			{
				return
			}

			if (this.active && !this.isClickable(e.target))
			{
				e.preventDefault()
				this.trigger()
			}
		},

		getProxy ()
		{
			if (!this.proxy)
			{
				let proxy = this.target.querySelector('[data-tp-primary]') || this.target.querySelector('a[href]')

				this.proxy = proxy
			}

			return this.proxy
		},

		trigger ()
		{
			let proxy = this.getProxy()
			if (!proxy)
			{
				return
			}

			if (this.timer)
			{
				clearTimeout(this.timer)
			}
			this.active = false

			proxy.click()

			this.timer = setTimeout(() =>
			{
				this.active = true
			}, 500)
		},
	})

	// ################################## WEB SHARE HANDLER ###########################################

	XF.WebShare = XF.Element.newHandler({
		options: {
			fetch: false,
			url: null,
			title: null,
			text: null,
			hide: null,
			hideContainerEls: true,
		},

		url: null,
		title: null,
		text: null,

		fetchUrl: null,

		init ()
		{
			if (!this.isSupported())
			{
				return
			}

			if (this.options.fetch)
			{
				this.fetchUrl = this.options.fetch
			}

			this.hideSpecified()
			this.hideContainerElements()
			this.setupShareData()

			this.target.classList.remove('is-hidden')
			XF.on(this.target, 'click', this.click.bind(this))
		},

		isSupported ()
		{
			const os = XF.browser.os

			return (
				'share' in navigator
				&& window.location.protocol == 'https:'
				&& (os == 'android' || os == 'ios')
			)
		},

		hideSpecified ()
		{
			if (!this.options.hide)
			{
				return
			}

			document.querySelectorAll(this.options.hide)
				?.forEach((hide) =>
				{
					hide.classList.add('is-hidden')
				})
		},

		hideContainerElements ()
		{
			if (!this.options.hideContainerEls)
			{
				return
			}

			let shareContainer = Array.from(this.target.closest('.block, .blockMessage'))

			if (shareContainer.length)
			{
				let shareButtons = shareContainer[0].querySelectorAll('.shareButtons')
				shareButtons.forEach(button => button.classList.remove('shareButtons--iconic'))

				let minorHeaders = shareContainer[0].querySelectorAll('.block-minorHeader')
				minorHeaders.forEach(header => XF.display(header, 'none'))

				let labelButtons = shareContainer[0].querySelectorAll('.shareButtons-label')
				labelButtons.forEach(label => XF.display(label, 'none'))
			}
		},

		setupShareData ()
		{
			if (!this.fetchUrl)
			{
				if (this.options.url)
				{
					this.url = this.options.url
				}
				else
				{
					this.url = document.querySelector('meta[property="og:url"]')?.content
					if (!this.url)
					{
						this.url = window.location.href
					}
				}

				if (this.options.title)
				{
					this.title = this.options.title
				}
				else
				{
					this.title = document.querySelector('meta[property="og:title"]')?.content
					if (!this.title)
					{
						this.title = document.querySelector('title')?.textContent
					}
				}

				if (this.options.text)
				{
					this.text = this.options.text
				}
				else
				{
					this.text = document.querySelector('meta[property="og:description"]')?.content
					if (!this.text)
					{
						this.text = document.querySelector('meta[name=description]')?.content || ''
					}
				}
			}
		},

		click (e)
		{
			e.preventDefault()

			if (this.fetchUrl)
			{
				XF.ajax(
					'get',
					this.fetchUrl,
					{ web_share: true },
					data =>
					{
						if (data.status === 'ok')
						{
							this.setShareOptions(data)
							this.share()
						}
						else
						{
							// some sort of error in the request so
							// redirect to the original URL passed in
							XF.redirect(this.options.url)
						}
					},
					{
						skipDefault: true,
						skipError: true,
						global: false,
					},
				)
			}
			else
			{
				this.share()
			}
		},

		share ()
		{
			navigator
				.share(this.getShareOptions())
				.catch((error) =>
				{
				})
		},

		setShareOptions (data)
		{
			this.url = data.contentUrl
			this.title = data.contentTitle
			this.text = data.contentDesc || data.contentTitle

			this.fetchUrl = null
		},

		getShareOptions ()
		{
			const shareOptions = {
				url: this.url,
				title: '',
				text: '',
			}

			if (this.title)
			{
				shareOptions.title = this.title
			}

			if (this.text)
			{
				shareOptions.text = this.text
			}
			else
			{
				shareOptions.text = shareOptions.title
			}

			return shareOptions
		},
	})

	// ############################### MENU CLICK HANDLER ##############################################

	XF.MenuClick = XF.Event.newHandler({
		eventNameSpace: 'XFMenuClick',
		options: {
			menu: null,
			targetOpenClass: 'is-menuOpen',
			openClass: 'is-active',
			completeClass: 'is-complete',
			zIndexRef: null,
			menuPosRef: null, // menu will be positioned with relation to this
			arrowPosRef: null, // arrow will be positioned with relation to this
			directionThreshold: 0.6, // if menu trigger is more than this amount of the page to the right,
			// align with right edge instead of left
		},

		menu: null,

		menuPosRef: null,
		menuRef: null,
		arrowPosRef: null,
		arrowRef: null,

		scrollFunction: null,
		isPotentiallyFixed: false,
		menuIsUp: false,

		menuWidth: 0,
		menuHeight: 0,

		init ()
		{
			if (this.options.menu)
			{
				this.menu = XF.findRelativeIf(this.options.menu, this.target)
			}
			if (!this.menu || !this.menu.length)
			{
				let sibling = this.target.nextElementSibling
				while (sibling)
				{
					if (sibling.hasAttribute('data-menu'))
					{
						this.menu = sibling
						break
					}
					sibling = sibling.nextElementSibling
				}
			}

			if (!this.menu)
			{
				console.error('No menu found for %o', this.target)
				return
			}

			this.menuPosRef = this.target
			this.arrowPosRef = this.target

			if (this.options.menuPosRef)
			{
				const menuPosRef = XF.findRelativeIf(this.options.menuPosRef, this.target)
				if (menuPosRef)
				{
					this.menuPosRef = menuPosRef

					if (this.options.arrowPosRef)
					{
						// only check for arrowPosRef if we have a menuPosRef,
						// and only allow it if it's a child of menuPosRef (or the same as menuPosRef)

						const arrowPosRef = XF.findRelativeIf(this.options.arrowPosRef, this.target)
						if (arrowPosRef.closest(menuPosRef))
						{
							this.arrowPosRef = arrowPosRef
						}
					}
				}
			}

			this.target.setAttribute('aria-controls', XF.uniqueId(this.menu))

			if (!this.menu.querySelector('.menu-arrow'))
			{
				const arrow = XF.createElement('span', {
					className: 'menu-arrow',
				})
				this.menu.insertBefore(arrow, this.menu.firstChild)
			}

			XF.DataStore.set(this.menu, 'menu-trigger', this)

			XF.on(this.menu, 'click', (e) =>
			{
				const selector = '[data-menu-closer]'
				const target = e.target.closest(selector)

				if (target)
				{
					this.close()
				}
			})
			XF.on(this.menu, 'menu:open', () => this.open(XF.Feature.has('touchevents')))
			XF.on(this.menu, 'menu:close', () => this.close())
			XF.on(this.menu, 'menu:reposition', () =>
			{
				if (this.isOpen())
				{
					this.reposition()
				}
			})
			XF.on(this.menu, 'keydown', this.keyboardEvent.bind(this))

			const tooltip = this.menu.closest('.tooltip')
			if (tooltip)
			{
				XF.on(tooltip, 'tooltip:hidden', this.close.bind(this))
			}

			const builder = this.menu.dataset.menuBuilder
			if (builder)
			{
				if (XF.MenuBuilder[builder])
				{
					XF.MenuBuilder[builder](this.menu, this.target, this)
				}
				else
				{
					console.error('No menu builder ' + builder + ' found')
				}
			}
		},

		click (e)
		{
			if (typeof e.originalEvent !== 'undefined')
			{
				// custom event call from XF.MenuProxy
				e = e.originalEvent
			}

			if ((e.ctrlKey || e.shiftKey || e.altKey) && this.target.getAttribute('href'))
			{
				// don't open the menu as the target will be opened elsewhere
				return
			}

			const touchTriggered = XF.isEventTouchTriggered(e)
			let preventDefault = true

			if (!touchTriggered && this.isOpen())
			{
				// allow second clicks to the menu trigger follow any link it has
				preventDefault = false
			}

			if (preventDefault)
			{
				e.preventDefault()
			}

			this.toggle(touchTriggered, XF.NavDeviceWatcher.isKeyboardNav())
		},

		isOpen ()
		{
			return this.target.classList.contains(this.options.targetOpenClass)
		},

		toggle (touchTriggered)
		{
			if (this.isOpen())
			{
				this.close()
			}
			else
			{
				this.open(touchTriggered)
			}
		},

		open (touchTriggered)
		{
			const menu = this.menu
			const target = this.target
			const menuPosRef = this.menuPosRef
			let minZIndex = 0

			if (this.isOpen() || menu.classList.contains('is-disabled'))
			{
				return
			}

			// ensure this is always at the end, should help sort potential ordering issues
			document.body.append(menu)

			this.updateMenuDimensions()
			this.updatePositionReferences()

			const isPotentiallyFixed = XF.hasFixableParent(this.target)
			let scrolling = null

			if (isPotentiallyFixed)
			{
				this.scrollFunction = () =>
				{
					if (XF.isHidden(target))
					{
						this.close()
					}
					else
					{
						this.repositionFixed(true)
					}
				}
				menu.classList.add('menu--potentialFixed')

				XF.on(window, 'scroll', this.scrollFunction)
			}

			if (this.options.zIndexRef)
			{
				const ref = XF.findRelativeIf(this.options.zIndexRef, target)
				if (ref)
				{
					minZIndex = XF.getElEffectiveZIndex(ref)
				}
			}

			XF.setRelativeZIndex(menu, target, 0, minZIndex)

			XF.MenuWatcher.onOpen(menu, touchTriggered)

			this.reposition()

			target.setAttribute('aria-expanded', 'true')
			XF.Transition.addClassTransitioned(target, this.options.targetOpenClass)

			menu.setAttribute('aria-hidden', 'false')
			XF.Transition.addClassTransitioned(menu, this.options.openClass, () =>
			{
				XF.Transition.addClassTransitioned(menu, this.options.completeClass)
			})
			XF.Transition.addClassTransitioned(menuPosRef, this.options.targetOpenClass)

			const event = XF.customEvent('menu:opened', { menu })
			XF.trigger(this.target, event)
			XF.trigger(menu, event)

			// focus menu content
			if (!XF.isIOS() || !this.isPotentiallyFixed)
			{
				// we can't do autofocusing in iOS when we might be in fixed menu as this won't trigger our full workaround

				XF.on(menu, 'menu:complete', () =>
				{
					setTimeout(() => XF.autoFocusWithin(menu, '[autofocus], [data-menu-autofocus]'), 10)
				})
			}

			const menuHref = menu.dataset.href
			if (menuHref)
			{
				if (XF.DataStore.get(menu, 'menu-loading'))
				{
					return
				}
				XF.DataStore.set(menu, 'menu-loading', true)

				const cacheResponse = menu.dataset.nocache ? false : true

				XF.ajax('get', menuHref, {}, data =>
				{
					if (cacheResponse)
					{
						delete menu.dataset.href
					}

					if (data.html)
					{
						XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
						{
							let loadTarget = menu.dataset.loadTarget
							let targetEl = menu

							if (loadTarget)
							{
								targetEl = menu.querySelector(loadTarget)
							}

							targetEl.innerHTML = ''
							if (XF.isCreatedContainer(html))
							{
								targetEl.append(...html.childNodes)
							}
							else
							{
								targetEl.append(html)
							}

							XF.trigger(this.target, XF.customEvent('menu:loaded', {
								html,
								menu,
							}))

							this.updateMenuDimensions()

							onComplete(false, targetEl)

							setTimeout(this.reposition.bind(this), 0)

							XF.trigger(this.target, XF.customEvent('menu:complete', { menu }))
							XF.trigger(menu, 'menu:complete')
						})
					}
				}, { cache: cacheResponse ? 'default' : 'reload' }).finally(() => XF.DataStore.set(menu, 'menu-loading', false))
			}
			else
			{
				XF.trigger(this.target, XF.customEvent('menu:complete', { menu }))
				XF.trigger(menu, 'menu:complete')
			}
		},

		reposition (force, forceAbsolute)
		{
			if (XF.DataStore.get(this.menu, 'ios-scroll-timeout') && !force)
			{
				return
			}

			this.updatePositionReferences()

			this.menu.style.visibility = 'hidden'
			XF.display(this.menu)
			this.menu.style.position = ''
			this.menu.style.top = ''
			this.menu.style.bottom = ''
			this.menu.style.left = ''
			this.menu.style.right = ''

			const viewport = XF.viewport(window)
			let menuCss = {}

			menuCss = this.getHorizontalPosition(viewport, menuCss)
			menuCss = this.getVerticalPosition(viewport, menuCss, forceAbsolute)
			menuCss.display = ''
			menuCss.visibility = ''

			for (let key in menuCss)
			{
				this.menu.style[key] = menuCss[key]
			}
		},

		repositionFixed (isScrolling)
		{
			const target = this.target,
				menu = this.menu

			if (isScrolling && XF.isIOS())
			{
				// iOS reports fixed/sticky offsets incorrectly while scrolling so we have no hope of repositioning this
				// correctly until those update. Hopefully the initial positioning will work; it won't when sticky
				// positioning switches between relative and fixed.
				// Relevant bug report: http://www.openradar.me/22872226
				let iOSScrollTimeout = XF.DataStore.get(menu, 'ios-scroll-timeout')

				clearTimeout(iOSScrollTimeout)
				iOSScrollTimeout = setTimeout(() =>
				{
					XF.DataStore.remove(menu, 'ios-scroll-timeout')
					this.reposition()
				}, 300)
				XF.DataStore.set(menu, 'ios-scroll-timeout', iOSScrollTimeout)

				return
			}

			this.updatePositionReferences()

			const menuCurrent = XF.DataStore.get(this.target, 'menu-h')
			let resetTimer = XF.DataStore.get(menu, 'menu-reset-timer')

			if (!menuCurrent || this.menuRef.left != menuCurrent[0] || this.menuRef.width != menuCurrent[1])
			{
				this.reposition()
				return
			}

			const computedCss = window.getComputedStyle(menu)
			const viewport = XF.viewport(window)
			const newMenuPosition = XF.hasFixedParent(this.target) ? 'fixed' : 'absolute'

			let menuCss = {
				top: parseInt(computedCss.top, 10),
			}

			this.menuIsUp = this.menu.classList.contains('menu--up')

			if (resetTimer)
			{
				clearTimeout(resetTimer)
			}

			if (newMenuPosition == 'fixed' && newMenuPosition != computedCss.position)
			{
				menuCss = {
					'transition-property': 'none',
					'transition-duration': '0s',
				}
				menuCss = this.getVerticalFixedPosition(viewport, menuCss)
			}
			else if (newMenuPosition == 'absolute')
			{
				menuCss = {
					'transition-property': 'none',
					'transition-duration': '0s',
				}
				menuCss = this.getVerticalAbsolutePosition(viewport, menuCss)
			}

			for (let key in menuCss)
			{
				menu.style[key] = menuCss[key]
			}

			menu.classList.toggle('menu--up', this.menuIsUp)

			resetTimer = setTimeout(() =>
			{
				menu.style.transitionProperty = ''
				menu.style.transitionDuration = ''
			}, 250)

			XF.DataStore.set(menu, 'menu-reset-timer', resetTimer)
		},

		getHorizontalPosition (viewport, menuCss)
		{
			let menuIsRight = false
			let deltaLeft = 0

			if (this.menuWidth > viewport.width)
			{
				// align menu to left viewport edge if menu is wider than viewport
				deltaLeft = this.menuRef.left - viewport.left
			}
			else if (this.menuRef.left + this.menuRef.width / 2 > viewport.width * this.options.directionThreshold)
			{
				// align menu to right of this.menuRef if this.menuRef center is viewportwidth/directionThreshold of the page width
				deltaLeft = 0 - this.menuWidth + this.menuRef.width
				menuIsRight = true
			}
			else if (this.menuRef.width > this.menuWidth)
			{
				// align menu with middle of the ref
				deltaLeft = Math.floor((this.menuRef.width - this.menuWidth) / 2)
			}

			// corrections to constrain to viewport, as much as possible, with 5px to spare
			deltaLeft = Math.min(deltaLeft, viewport.right - this.menuWidth - this.menuRef.left - 5)
			deltaLeft = Math.max(deltaLeft, viewport.left - this.menuRef.left + 5)

			// final calculation for menu left position
			menuCss.left = this.menuRef.left + deltaLeft + 'px'

			XF.DataStore.set(this.target, 'menu-h', [this.menuRef.left, this.menuRef.width, deltaLeft])

			this.menu.classList.toggle('menu--left', !menuIsRight)
			this.menu.classList.toggle('menu--right', menuIsRight)

			// don't allow the arrow to be moved outside of the menu
			const arrowOffset = Math.min(
				this.arrowRef.left - this.menuRef.left + this.arrowRef.width / 2 - deltaLeft,
				this.menuWidth - 20,
			)

			const menuArrow = this.menu.querySelector('.menu-arrow')
			if (menuArrow)
			{
				menuArrow.style.top = ''
				menuArrow.style.left = arrowOffset + 'px'
			}

			return menuCss
		},

		getVerticalPosition (viewport, menuCss, forceAbsolute)
		{
			this.menuIsUp = false

			if (!forceAbsolute && XF.hasFixedParent(this.target))
			{
				menuCss = this.getVerticalFixedPosition(viewport, menuCss)
			}
			else
			{
				menuCss = this.getVerticalAbsolutePosition(viewport, menuCss)
			}

			this.menu.classList.toggle('menu--up', this.menuIsUp)

			return menuCss
		},

		getVerticalFixedPosition (viewport, menuCss)
		{
			menuCss.top = Math.max(0, Math.round(this.menuRef.bottom) - viewport.top) - this.getTopShift() + 'px'
			menuCss.position = 'fixed'

			if (parseInt(menuCss.top) + this.menuHeight + viewport.top > viewport.bottom && this.menuRef.top - this.menuHeight > viewport.top) // fixed menu would overlap viewport bottom
			{
				menuCss.top = ''
				menuCss.bottom = viewport.bottom - this.menuRef.top + 5 + 'px'

				this.menuIsUp = true
			}
			else
			{
				this.menuIsUp = false
			}

			return menuCss
		},

		getVerticalAbsolutePosition (viewport, menuCss)
		{
			menuCss.top = this.menuRef.bottom - this.getTopShift() + 'px'
			menuCss.position = '' // this is in the CSS

			if (parseInt(menuCss.top, 10) + this.menuHeight > viewport.bottom && this.menuRef.top - this.menuHeight > viewport.top) // absolute menu would overlap viewport bottom
			{
				menuCss.top = ''
				menuCss.bottom = viewport.height - this.menuRef.top + 5 + 'px'

				this.menuIsUp = true
			}
			else
			{
				this.menuIsUp = false
			}

			return menuCss
		},

		getTopShift ()
		{
			return this.menu.classList.contains('menu--structural') ? parseInt(XF.config.borderSizeFeature, 10) : 0
		},

		updateMenuDimensions ()
		{
			let originalDisplay = this.menu.style.display
			let originalVisibility = this.menu.style.visibility

			XF.display(this.menu)
			this.menu.style.visibility = 'hidden'

			// eslint-disable-next-line @typescript-eslint/no-unused-expressions
			this.menu.clientWidth // force reflow

			this.menuWidth = this.menu.offsetWidth
			this.menuHeight = this.menu.offsetHeight

			XF.display(this.menu, originalDisplay)
			this.menu.style.visibility = originalVisibility

			return {
				menuWidth: this.menuWidth,
				menuHeight: this.menuHeight,
			}
		},

		updatePositionReferences ()
		{
			this.menuRef = XF.dimensions(this.menuPosRef, true)

			if (this.arrowPosRef == this.menuPosRef)
			{
				this.arrowRef = this.menuRef
			}
			else
			{
				this.arrowRef = XF.dimensions(this.arrowPosRef, true)
			}

			return {
				menuRef: this.menuRef,
				arrowRef: this.arrowRef,
			}
		},

		close ()
		{
			if (!this.isOpen())
			{
				return
			}

			const menu = this.menu

			this.target.setAttribute('aria-expanded', 'false')
			XF.Transition.removeClassTransitioned(this.target, this.options.targetOpenClass)

			menu.setAttribute('aria-hidden', true)
			menu.classList.remove(this.options.completeClass)
			XF.Transition.removeClassTransitioned(menu, this.options.openClass)

			XF.Transition.removeClassTransitioned(this.menuPosRef, this.options.targetOpenClass)

			XF.off(window, 'scroll', this.scrollFunction)

			XF.MenuWatcher.onClose(menu)

			const event = XF.customEvent('menu:closed', { menu })
			XF.trigger(this.target, event)
			XF.trigger(menu, event)
		},

		/**
		 * Allow up and down arrow keys to navigate between links in the menu
		 * @param e
		 * @returns {boolean}
		 */
		keyboardEvent (e)
		{
			if (e.key == 'ArrowUp' || e.key == 'ArrowDown')
			{
				if (XF.Keyboard.isShortcutAllowed(document.activeElement))
				{
					if (document.activeElement.closest('.menu') == this.menu)
					{
						const activeElement = document.activeElement
						const links = activeElement.closest('.menu').querySelectorAll('a')
						let newIndex = Array.from(links).indexOf(activeElement) + (e.key == 'ArrowUp' ? -1 : 1)

						if (newIndex < 0)
						{
							newIndex = links.length - 1
						}
						else if (newIndex >= links.length)
						{
							newIndex = 0
						}

						links[newIndex].focus()
						e.preventDefault()
						e.stopPropagation()
					}
				}
			}
		},
	})

	// ############################### OFF CANVAS CLICK HANDLER ##############################################

	XF.OffCanvasClick = XF.Event.newHandler({
		eventNameSpace: 'XFOffCanvasClick',
		options: {
			menu: null,
			openClass: 'is-active',
		},

		menu: null,

		init ()
		{
			if (this.options.menu)
			{
				this.menu = XF.findRelativeIf(this.options.menu, this.target)
			}
			if (!this.menu)
			{
				const allNextSiblings = Array.from(this.target.nextElementSibling)
				this.menu = allNextSiblings.find(el => el.dataset.menu !== undefined)
			}

			if (!this.menu)
			{
				console.error('No menu found for %o', this.target)
				return
			}

			XF.on(this.menu, 'click', e =>
			{
				if (e.target.matches('[data-menu-close]'))
				{
					this.closeTrigger(e)
				}
			})
			XF.on(this.menu, 'off-canvas:close', this.closeTrigger.bind(this))
			XF.on(this.menu, 'off-canvas:open', this.openTrigger.bind(this))

			const builder = this.menu.dataset.ocmBuilder
			if (builder)
			{
				if (XF.OffCanvasBuilder[builder])
				{
					XF.OffCanvasBuilder[builder](this.menu, this)
				}
				else
				{
					console.error('No off canvas builder ' + builder + ' found')
				}
			}
		},

		click (e)
		{
			e.preventDefault()

			this.toggle()
		},

		isOpen ()
		{
			return this.menu.classList.contains(this.options.openClass)
		},

		toggle ()
		{
			if (this.isOpen())
			{
				this.close()
			}
			else
			{
				this.open()
			}
		},

		openTrigger (e)
		{
			e.preventDefault()

			this.open()
		},

		open ()
		{
			if (this.isOpen())
			{
				return
			}

			const menu = this.menu

			this.addOcmClasses(menu)

			menu.setAttribute('aria-hidden', 'false')
			XF.trigger(menu, 'off-canvas:opening')

			XF.Transition.addClassTransitioned(menu, this.options.openClass, () => XF.trigger(menu, 'off-canvas:opened'))

			XF.Modal.open()
		},

		addOcmClasses (target)
		{
			const ocmClass = target.dataset.ocmClass
			if (ocmClass)
			{
				target.classList.add(...ocmClass.split(' '))
			}

			target.querySelectorAll('[data-ocm-class]').forEach((element) =>
			{
				element.classList.add(...element.dataset.ocmClass.split(' '))
			})
		},

		removeOcmClasses (target)
		{
			const ocmClass = target.dataset.ocmClass
			if (ocmClass)
			{
				target.classList.remove(...ocmClass.split(' '))
			}

			target.querySelectorAll('[data-ocm-class]').forEach((element) =>
			{
				element.classList.remove(...element.dataset.ocmClass.split(' '))
			})
		},

		closeTrigger (e)
		{
			e.preventDefault()

			this.close()
		},

		close (instant = false)
		{
			if (!this.isOpen())
			{
				return
			}

			const menu = this.menu

			menu.setAttribute('aria-hidden', 'true')
			XF.trigger(menu, 'off-canvas:closing')

			XF.Transition.removeClassTransitioned(menu, this.options.openClass, () =>
			{
				XF.trigger(menu, 'off-canvas:closed')
				this.removeOcmClasses(menu)
			}, instant)

			XF.Modal.close()
		},
	})

	// ############################### OVERLAY CLICK HANDLER ##############################################

	XF.OverlayClick = XF.Event.newHandler({
		eventNameSpace: 'XFOverlayClick',

		// NOTE: these attributes must be reflected in XF\Template\Templater::overlayClickOptions
		options: {
			cache: true,
			overlayConfig: {},
			forceFlashMessage: false,
			followRedirects: false,
			closeMenus: true,
		},

		overlay: null,
		loadUrl: null,

		loading: false,
		visible: false,

		init ()
		{
			const overlay = this.getOverlayHtml()
			if (overlay)
			{
				this.setupOverlay(new XF.Overlay(overlay, this.options.overlayConfig))
			}
			else
			{
				const loadUrl = this.getLoadUrl()
				if (!loadUrl)
				{
					throw new Error('Could not find an overlay for target')
				}
				this.loadUrl = loadUrl
			}

			if (this.options.closeMenus)
			{
				XF.MenuWatcher.closeAll()
			}
		},

		click (e)
		{
			e.preventDefault()

			this.toggle()
		},

		toggle ()
		{
			if (this.overlay)
			{
				this.overlay.toggle()
			}
			else
			{
				this.show()
			}
		},

		show ()
		{
			if (this.overlay)
			{
				this.overlay.show()
			}
			else
			{
				if (this.loading)
				{
					return
				}

				this.loading = true

				const options = {
					cache: this.options.cache,
					beforeShow: overlay =>
					{
						this.overlay = overlay
					},
					init: this.setupOverlay.bind(this),
				}
				let ajax

				if (this.options.followRedirects)
				{
					options['onRedirect'] = (data, overlayAjaxHandler) =>
					{
						if (this.options.forceFlashMessage)
						{
							XF.flashMessage(data.message, 1000, () => XF.redirect(data.redirect))
						}
						else
						{
							XF.redirect(data.redirect)
						}
					}
				}

				ajax = XF.loadOverlay(this.loadUrl, options, this.options.overlayConfig)

				if (ajax)
				{
					ajax.finally(() =>
					{
						setTimeout(() =>
						{
							this.loading = false
						}, 300)
					})
				}
				else
				{
					this.loading = false
				}
			}
		},

		hide ()
		{
			if (this.overlay)
			{
				this.overlay.hide()
			}
		},

		getOverlayHtml ()
		{
			const targetSelector = this.target.dataset.target
			let href
			let overlay

			if (targetSelector)
			{
				overlay = this.target.querySelector(targetSelector)
				if (!overlay)
				{
					overlay = document.querySelector(targetSelector)
				}
			}

			if (!overlay)
			{
				href = this.target.getAttribute('href')
				if (href && href.substr(0, 1) == '#')
				{
					overlay = document.querySelector(href)
				}
			}

			if (overlay && !overlay.matches('.overlay'))
			{
				overlay = XF.getOverlayHtml(overlay)
			}

			return overlay ? overlay : null
		},

		getLoadUrl ()
		{
			return this.target.dataset.href || this.target.getAttribute('href') || null
		},

		setupOverlay (overlay)
		{
			this.overlay = overlay

			overlay.on('overlay:shown', () =>
			{
				this.visible = true
			})

			overlay.on('overlay:hidden', () =>
			{
				this.visible = false
			})

			if (!this.options.cache && this.loadUrl)
			{
				overlay.on('overlay:hidden', () =>
				{
					this.overlay = null
				})
			}

			return this.overlay
		},
	})
	XF.OverlayClick.overlayCache = {}

	// ################################## SCROLL TO CLICK HANDLER ######################################

	XF.ScrollToClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFScrollToClick',
		options: {
			target: null, // specify a target to which to scroll, when href is not available
			silent: false, // if true and no scroll
			hash: null, // override history hash - off by default, use true to use target's ID or string for arbitrary hash value
			speed: 300, // scroll animation speed
		},

		scroll: null,

		init ()
		{
			let scroll
			const hash = this.options.hash
			const targetHref = this.target.getAttribute('href')

			if (this.options.target)
			{
				scroll = XF.findRelativeIf(this.options.target, this.target)
			}
			if (!scroll)
			{
				if (targetHref && targetHref.length && targetHref.charAt(0) == '#')
				{
					scroll = document.querySelector(targetHref)
				}
				else if (this.options.silent)
				{
					// don't let an error happen here, just silently ignore
					return
				}
			}

			if (!scroll)
			{
				console.error('No scroll target could be found')
				return
			}

			this.scroll = scroll

			if (hash === true || hash === 'true')
			{
				const id = scroll.getAttribute('id')
				this.options.hash = (id && id.length) ? id : null
			}
			else if (hash === false || hash === 'false')
			{
				this.options.hash = null
			}
		},

		click (e)
		{
			if (!this.scroll)
			{
				return
			}

			e.preventDefault()
			XF.smoothScroll(this.scroll, this.options.hash, this.options.speed)
		},
	})

	XF.StyleVariationClick = XF.Event.newHandler({
		eventType: 'click',
		eventNameSpace: 'XFStyleVariationClick',

		options: {
			variation: null,
		},

		init ()
		{
		},

		click (e)
		{
			e.preventDefault()
			XF.StyleVariation.updateVariation(this.options.variation)
		},
	})

	XF.Element.register('auto-complete', 'XF.AutoComplete')
	XF.Element.register('cookie-consent-form', 'XF.CookieConsentForm')
	XF.Element.register('h-scroller', 'XF.HScroller')
	XF.Element.register('icon', 'XF.IconRenderer')
	XF.Element.register('install-prompt', 'XF.InstallPrompt')
	XF.Element.register('notices', 'XF.Notices')
	XF.Element.register('number-box', 'XF.NumberBox')
	XF.Element.register('page-jump', 'XF.PageJump')
	XF.Element.register('quick-search', 'XF.QuickSearch')
	XF.Element.register('search-auto-complete', 'XF.SearchAutoComplete')
	XF.Element.register('share-buttons', 'XF.ShareButtons')
	XF.Element.register('sticky', 'XF.Sticky')
	XF.Element.register('sticky-header', 'XF.StickyHeader')
	XF.Element.register('style-variation-input', 'XF.StyleVariationInput')
	XF.Element.register('tooltip', 'XF.Tooltip')
	XF.Element.register('element-tooltip', 'XF.ElementTooltip')
	XF.Element.register('member-tooltip', 'XF.MemberTooltip')
	XF.Element.register('touch-proxy', 'XF.TouchProxy')
	XF.Element.register('web-share', 'XF.WebShare')

	XF.Event.register('click', 'cookie-consent-toggle', 'XF.CookieConsentToggle')
	XF.Event.register('click', 'menu', 'XF.MenuClick')
	XF.Event.register('click', 'off-canvas', 'XF.OffCanvasClick')
	XF.Event.register('click', 'overlay', 'XF.OverlayClick')
	XF.Event.register('click', 'scroll-to', 'XF.ScrollToClick')
	XF.Event.register('click', 'style-variation', 'XF.StyleVariationClick')
})(window, document)
