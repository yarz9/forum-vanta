((window, document) =>
{
	'use strict'

	XF.InlineMod = XF.Element.newHandler({
		options: {
			type: null,
			href: null,
			searchTarget: null,
			cookieBase: 'inlinemod',
			cookieSizeLimit: 1024 * 3, // 3KB
			toggle: 'input[type=checkbox].js-inlineModToggle',
			toggleContainer: '.js-inlineModContainer',
			containerClass: 'is-mod-selected',
			actionTrigger: '.js-inlineModTrigger',
			counter: '.js-inlineModCounter',
			viewport: 'body',
		},

		abortController: null,

		cookie: null,
		action: null,
		xhr: null,
		searchTarget: null,

		init ()
		{
			if (!this.options.type)
			{
				console.error('No inline mod type specified')
				return
			}

			if (!this.options.href)
			{
				console.error('No inline mod href specified')
			}

			const searchTarget = this.options.searchTarget
			let searchTargetEl

			if (searchTarget === '*')
			{
				searchTargetEl = document
			}
			else if (searchTarget && searchTarget.length)
			{
				searchTargetEl = XF.findRelativeIf(searchTarget, this.target)
				if (!searchTargetEl)
				{
					console.error('Search target %s not found, falling back to children', searchTarget)
					searchTargetEl = this.target
				}
			}
			else
			{
				searchTargetEl = this.target
			}

			this.searchTarget = searchTargetEl
			this.cookie = this.options.cookieBase + '_' + this.options.type

			XF.onDelegated(searchTargetEl, 'click', this.options.toggle, this.onToggle.bind(this))
			XF.onDelegated(searchTargetEl, 'click', this.options.actionTrigger, this.onActionTrigger.bind(this))

			const cookie = this.getCookieValue()
			this._initialLoad(cookie)
			this._updateCounter(cookie.length)

			// timeout is so we don't listen immediately as this event is fired shortly after this is setup
			setTimeout(() =>
			{
				XF.on(document, 'xf:reinit', e =>
				{
					const { element } = e
					// if the element we're activating is placed within the inline mod area and contains an
					// inline mod checkbox, we need to recalculate our status to account for new highlighting
					if (this.searchTarget.contains(element) && element.querySelector(this.options.toggle))
					{
						this.recalculateFromCookie()
					}
				})
			}, 0)
		},

		_initialLoad (checked)
		{
			const toggles = this.getToggles()

			// Firefox seems to retain the checkbox state after reload. We need to clear any existing checkbox states.
			Array.from(toggles).forEach(toggle =>
			{
				toggle.checked = false
			})

			const map = {}

			if (checked.length)
			{
				Array.from(toggles).forEach(toggle =>
				{
					map[toggle.value] = toggle
				})

				const length = checked.length
				let id

				for (let i = 0; i < length; i++)
				{
					id = checked[i]
					if (map[id])
					{
						map[id].checked = true
						this.toggleContainer(map[id], true)
					}
				}
			}
		},

		getToggles ()
		{
			return this.searchTarget.querySelectorAll(this.options.toggle)
		},

		recalculateFromCookie ()
		{
			const values = this.getCookieValue()
			const length = values.length
			const checked = {}

			for (let i = 0; i < length; i++)
			{
				checked[values[i]] = true
			}

			Array.from(this.getToggles()).forEach(toggle =>
			{
				const thisState = toggle.checked
				const expectedState = checked[toggle.value] ? true : false

				if (thisState && !expectedState)
				{
					toggle.checked = false
					this.toggleContainer(toggle, false)
				}
				else if (!thisState && expectedState)
				{
					toggle.checked = true
					this.toggleContainer(toggle, true)
				}
			})
		},

		deselect ()
		{
			this.setCookieValue([])
			this.recalculateFromCookie()
			this.hideBar()
		},

		selectAll ()
		{
			let cookie = this.getCookieValue()

			Array.from(this.getToggles()).forEach(toggle =>
			{
				const id = parseInt(toggle.value, 10)
				if (!cookie.includes(id))
				{
					const lastValue = this.getCookieValue()
					cookie.push(id)

					const cookieValueSize = XF.Cookie.getEncodedCookieValueSize(
						this.cookie,
						cookie.join(','),
					)
					if (cookieValueSize > this.options.cookieSizeLimit)
					{
						cookie = lastValue
						XF.flashMessage(XF.phrase('you_have_exceeded_maximum_number_of_selectable_items'), 3000)
						return false
					}
					else
					{
						this.setCookieValue(cookie)
					}
				}
			})

			this.recalculateFromCookie()

			return cookie
		},

		deselectPage ()
		{
			const existingCookie = this.getCookieValue()
			const newCookie = []
			const pageIds = []

			Array.from(this.getToggles()).forEach(toggle =>
			{
				pageIds.push(parseInt(toggle.value, 10))
			})

			for (const cookie of existingCookie)
			{
				if (!pageIds.includes(cookie))
				{
					newCookie.push(cookie)
				}
			}

			this.setCookieValue(newCookie)
			this.recalculateFromCookie()

			if (newCookie.length)
			{
				this.loadBar()
			}
			else
			{
				this.hideBar()
			}

			return newCookie
		},

		onToggle (e)
		{
			const check = e.target
			const selected = check.checked
			const originalValue = this.getCookieValue()
			const cookie = this.toggleSelectedInCookie(check.value, selected)

			if (cookie.length !== originalValue.length)
			{
				this.toggleContainer(check, selected)
			}
			else
			{
				check.checked = false
			}

			if (cookie.length)
			{
				this.loadBar()
			}
			else
			{
				this.hideBar()
			}
		},

		onActionTrigger (e)
		{
			e.preventDefault()

			this.loadBar()
		},

		loadBar (onLoad)
		{
			// put this in a timeout to handle JS setting multiple toggles
			// in a single action, and firing click actions for each one

			if (this.loadTimeout)
			{
				clearTimeout(this.loadTimeout)
			}

			this.loadTimeout = setTimeout(() =>
			{
				if (this.abortController)
				{
					this.abortController.abort()
					this.abortController = null
				}

				const {
					ajax,
					abortController,
				} = XF.ajaxAbortable(
					'GET',
					this.options.href,
					{ type: this.options.type },
					result => this._showBar(result, onLoad),
				)
				if (abortController)
				{
					this.abortController = abortController
				}
			}, 10)
		},

		_showBar (result, onLoad)
		{
			this.abortController = null

			if (!result.html)
			{
				return
			}

			XF.setupHtmlInsert(result.html, (html, container, onComplete) =>
			{
				let fastReplace = false

				if (this.bar)
				{
					fastReplace = true
					this.bar.remove()
					this.bar = null
				}

				this._setupBar(html)
				this.bar = html
				XF.bottomFix(html)

				if (XF.browser.ios)
				{
					// iOS has a quirk with this bar being fixed. If you open the select and click "go" before
					// blurring the select, the blur will happen and the click will actually register on whatever
					// was under the go button (rather than the button itself). To workaround this, we add an invisible
					// cover over the screen (not the mod bar) whenever the select is focused.
					const cover = XF.createElementFromString('<div class="inlineModBarCover"></div>')
					const bar = this.bar
					const action = bar.querySelector('.js-inlineModAction')

					XF.on(cover, 'click', () => action.blur())
					XF.on(action, 'focus', () => bar.parentNode.insertBefore(cover, bar))
					XF.on(action, 'blur', () => cover.remove())
				}

				if (fastReplace)
				{
					html.style.transitionDuration = '0s'
				}

				XF.Transition.addClassTransitioned(html, 'is-active')

				if (fastReplace)
				{
					setTimeout(() =>
					{
						html.style.transitionDuration = ''
					}, 0)
				}

				if (onLoad)
				{
					onLoad(html)
				}
			})
		},

		_setupBar (bar)
		{
			XF.onDelegated(bar, 'click', 'button[type="submit"]', this.submit.bind(this))
			XF.onDelegated(bar, 'click', '.js-inlineModClose', this.hideBar.bind(this))
			XF.onDelegated(bar, 'click', '.js-inlineModSelectAll', this.onSelectAllClick.bind(this))

			// check the 'select all' checkbox if all toggles are checked
			const toggles = Array.from(this.getToggles())
			const checkedToggles = toggles.filter(toggle => toggle.checked)

			if (toggles.length === checkedToggles.length)
			{
				const selectAllCheckbox = bar.querySelector('input[type=checkbox].js-inlineModSelectAll')
				selectAllCheckbox.checked = true
			}
		},

		onSelectAllClick (e)
		{
			const el = e.target

			if (el.checked)
			{
				const cookie = this.selectAll()
				if (cookie.length)
				{
					const check = bar =>
					{
						bar.querySelector('input[type=checkbox].js-inlineModSelectAll').checked = true
					}
					this.loadBar(check)
				}
				else
				{
					this.deselect()
				}
			}
			else
			{
				this.deselectPage()
			}
		},

		submit ()
		{
			if (!this.bar)
			{
				return
			}

			const actionEl = this.bar.querySelector('.js-inlineModAction')
			if (!actionEl)
			{
				console.error('No action selector found.')
				return
			}

			const action = actionEl.value
			if (!action)
			{
				// do nothing
				return
			}
			else if (action == 'deselect')
			{
				this.deselect()
			}
			else
			{
				XF.ajax(
					'POST',
					this.options.href,
					{
						type: this.options.type,
						action,
					},
					result => this._handleSubmitResponse(result),
					{ skipDefaultSuccess: true },
				)
			}
		},

		_handleSubmitResponse (data)
		{
			if (data.html)
			{
				XF.setupHtmlInsert(data.html, (html, container) =>
				{
					const overlay = XF.getOverlayHtml({
						html,
						title: container.h1 || container.title,
					})
					XF.showOverlay(overlay)
				})
			}
			else if (data.status == 'ok' && data.redirect)
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

			this.hideBar()
		},

		hideBar ()
		{
			if (this.bar)
			{
				XF.Transition.removeClassTransitioned(this.bar, 'is-active', () =>
				{
					if (this.bar)
					{
						this.bar.remove()
					}
					this.bar = null
				})
			}
		},

		_updateCounter (total)
		{
			const actionTrigger = this.searchTarget.querySelector(this.options.actionTrigger)
			if (!actionTrigger)
			{
				return
			}

			let toggleEl = actionTrigger.querySelector('.inlineModButton')

			if (!toggleEl)
			{
				toggleEl = actionTrigger
			}

			toggleEl.classList.toggle('is-mod-active', total > 0)

			this.searchTarget.querySelector(this.options.counter).textContent = total
		},

		toggleContainer (toggle, selected)
		{
			const toggleContainer = toggle.closest(this.options.toggleContainer)
			if (toggleContainer)
			{
				const method = selected ? 'add' : 'remove'
				toggleContainer.classList[method](this.options.containerClass)
			}
		},

		toggleSelectedInCookie (id, selected)
		{
			id = parseInt(id, 10)

			let value = this.getCookieValue()
			const originalValue = this.getCookieValue()
			const index = value.indexOf(id)
			let changed = false

			if (selected)
			{
				if (index < 0)
				{
					value.push(id)

					const cookieValueSize = XF.Cookie.getEncodedCookieValueSize(
						this.cookie,
						value.join(','),
					)
					if (cookieValueSize > this.options.cookieSizeLimit)
					{
						value = originalValue
						changed = false
						XF.flashMessage(XF.phrase('you_have_exceeded_maximum_number_of_selectable_items'), 3000)
					}
					else
					{
						changed = true
					}
				}
			}
			else
			{
				if (index >= 0)
				{
					value.splice(index, 1)
					changed = true
				}
			}

			if (changed)
			{
				return this.setCookieValue(value)
			}
			else
			{
				return value
			}
		},

		getCookieValue ()
		{
			const value = XF.Cookie.get(this.cookie)
			if (!value)
			{
				return []
			}

			const parts = value.split(',')
			const length = parts.length

			for (let i = 0; i < length; i++)
			{
				parts[i] = parseInt(parts[i], 10)
			}

			return parts
		},

		setCookieValue (ids)
		{
			if (!ids.length)
			{
				XF.Cookie.remove(this.cookie)
			}
			else
			{
				ids.sort((a, b) => (a - b))
				XF.Cookie.set(this.cookie, ids.join(','))
			}

			this._updateCounter(ids.length)

			return ids
		},
	})

	XF.Element.register('inline-mod', 'XF.InlineMod')
})(window, document)
