((window, document) =>
{
	'use strict'

	XF.OffCanvasBuilder.acpNav = (menu, handler) =>
	{
		XF.on(menu, 'off-canvas:opening', () =>
		{
			menu.style.position = ''
		})
	}

	XF.AdminNav = XF.Element.newHandler({
		options: {
			topOffset: '.p-header',
			sectionTogglers: '.js-navSectionToggle',
			toggleTarget: '.p-nav-section',
			toggleSubTarget: '.p-nav-listSection',
			navTester: '| .js-navTester',
		},

		init ()
		{
			XF.onDelegated(this.target, 'click', this.options.sectionTogglers, this.togglerClick.bind(this))
		},

		isOffCanvas ()
		{
			const tester = XF.findRelativeIf(this.options.navTester, this.target)
			if (!tester)
			{
				return false
			}

			const val = window.getComputedStyle(tester).getPropertyValue('font-family').replace(/"/g, '')
			return (val == 'off-canvas')
		},

		togglerClick (e)
		{
			e.preventDefault()

			const target = e.target
			const parent = target.closest(this.options.toggleTarget)
			const subTarget = this.options.toggleSubTarget

			Array.from(parent.parentNode.children).filter(el => el !== parent).forEach(el =>
			{
				XF.Transition.removeClassTransitioned(el, 'is-active')
				Array.from(el.querySelectorAll(subTarget)).forEach(subEl => XF.Transition.removeClassTransitioned(subEl, 'is-active'))
			})

			XF.Transition.toggleClassTransitioned(parent, 'is-active', () => XF.layoutChange())
			Array.from(parent.querySelectorAll(subTarget)).forEach(el => XF.Transition.toggleClassTransitioned(el, 'is-active'))
		},
	})

	XF.AdminSearch = XF.Element.newHandler({
		options: {
			input: '| .js-adminSearchInput',
			results: '| .js-adminSearchResults',
			resultsWrapper: '| .js-adminSearchResultsWrapper',
			toggleClass: 'is-active',
		},

		input: null,
		results: null,
		resultsWrapper: null,
		abortcontroller: null,

		init ()
		{
			const form = this.target
			this.input = XF.findRelativeIf(this.options.input, form)
			this.results = XF.findRelativeIf(this.options.results, form)

			this.resultsWrapper = XF.findRelativeIf(this.options.resultsWrapper, form)

			XF.on(form, 'submit', this.submit.bind(this))
			XF.on(this.input, 'keydown', this.keyDown.bind(this))

			XF.watchInputChangeDelayed(this.input, () =>
			{
				XF.trigger(form, 'submit')
			})
		},

		submit (e)
		{
			e.preventDefault()

			const form = this.target
			const results = this.results
			const resultsWrapper = this.resultsWrapper
			const toggleClass = this.options.toggleClass

			if (!this.input.value.trim().length)
			{
				this.emptyResults()
				return
			}

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortcontroller = null
			}

			const {
				ajax,
				abortController,
			} = XF.ajaxAbortable('post', form.getAttribute('action'), form, data =>
			{
				if (data.html)
				{
					XF.setupHtmlInsert(data.html, (html, data, onComplete) =>
					{
						if (html)
						{
							results.innerHTML = ''
							results.append(html)
							onComplete()
							resultsWrapper.classList.add(toggleClass)
							XF.Transition.addClassTransitioned(results, toggleClass)

							const links = results.querySelectorAll('a')
							links.forEach(link =>
							{
								XF.on(link, 'mouseenter', () => link.classList.add('is-active'))
								XF.on(link, 'mouseleave', () => link.classList.remove('is-active'))
							})
						}
						else
						{
							this.emptyResults()
						}
					})
				}
			})

			if (abortController)
			{
				this.abortcontroller = abortController
			}
		},

		emptyResults ()
		{
			const results = this.results
			const resultsWrapper = this.resultsWrapper
			const toggleClass = this.options.toggleClass

			XF.Transition.removeClassTransitioned(results, toggleClass, () =>
			{
				results.innerHTML = ''
				resultsWrapper.classList.remove(toggleClass)
			})
		},

		keyDown (e)
		{
			switch (e.key)
			{
				case 'ArrowUp':
					this.menuNavigate(-1)
					e.stopPropagation()
					break

				case 'ArrowDown':
					this.menuNavigate(1)
					e.stopPropagation()
					break

				case 'Enter':
					if (this.menuSelect())
					{
						e.stopPropagation()
					}
					break
			}
		},

		menuNavigate (direction)
		{
			const links = Array.from(this.results.querySelectorAll('a'))
			const highlighted = links.find(link => link.classList.contains('is-active'))
			let newIndex = links.indexOf(highlighted) + direction

			links.forEach(link => link.classList.remove('is-active'))

			if (newIndex < 0)
			{
				newIndex = links.length - 1
			}
			else if (newIndex >= links.length)
			{
				newIndex = 0
			}

			links[newIndex].classList.add('is-active')
			links[newIndex].focus()

			this.input.focus()
		},

		menuSelect ()
		{
			const link = this.results.querySelector('a.is-active')

			if (link)
			{
				window.location = link.getAttribute('href')
				return true
			}
		},
	})

	XF.AdminToggleAdvanced = XF.Element.newHandler({
		options: {
			url: null,
			value: null,
		},

		init ()
		{
			if (document.documentElement.classList.contains('acp--simple-mode'))
			{
				this.unhideLinkedOption()
			}

			XF.on(this.target, 'click', this.click.bind(this))
		},

		unhideLinkedOption ()
		{
			const id = window.location.hash.replace(/[^a-zA-Z0-9_-]/g, '')
			if (!id)
			{
				return
			}

			const option = document.querySelector(`span#${ id }.u-anchorTarget`).nextElementSibling
			if (!option || !option.classList.contains('acp--advanced'))
			{
				return
			}

			XF.display(option)
			XF.layoutChange()
		},

		click (e)
		{
			let advanced

			if (this.options.value !== null)
			{
				advanced = this.options.value ? 1 : 0
			}
			else if (this.target.type === 'checkbox')
			{
				advanced = this.target.checked ? 1 : 0
			}
			else
			{
				console.error('Admin toggler must be a checkbox or provide a data-value')
				return
			}

			if (this.options.url)
			{
				XF.ajax('POST', this.options.url, { advanced })
			}

			const advancedModeToggles = document.querySelectorAll('.js-advancedModeToggle')
			advancedModeToggles.forEach(toggle =>
			{
				if (toggle.type === 'checkbox')
				{
					toggle.checked = Boolean(advanced)
				}
			})

			document.documentElement.classList.toggle('acp--advanced-mode', Boolean(advanced))
			document.documentElement.classList.toggle('acp--simple-mode', !advanced)

			XF.layoutChange()
		},
	})

	XF.AdminAssetEditor = XF.Element.newHandler({
		options: {},

		init ()
		{
			this.target.querySelectorAll('.js-assetModify').forEach(elem =>
			{
				XF.on(elem, 'click', this.modifyClick.bind(this))
			})
		},

		modifyClick (e)
		{
			const button = e.currentTarget
			const inputGroup = button.parentElement

			if (button.classList.contains('is-modify'))
			{
				this.enableEditing(inputGroup)
			}
			else if (button.classList.contains('is-revert'))
			{
				this.revertToParent(inputGroup)
			}
		},

		enableEditing (inputGroup)
		{
			const key = inputGroup.querySelector('.js-assetKey')
			const value = inputGroup.querySelector('.js-assetValue')
			const button = inputGroup.querySelector('.js-assetModify')

			key.disabled = false
			value.disabled = false

			if (!value.dataset.parentValue)
			{
				value.dataset.parentValue = value.value
			}

			button.classList.remove('is-modify')
			button.classList.add('is-revert')
		},

		revertToParent (inputGroup)
		{
			const key = inputGroup.querySelector('.js-assetKey')
			const value = inputGroup.querySelector('.js-assetValue')
			const button = inputGroup.querySelector('.js-assetModify')

			key.disabled = true
			value.disabled = true

			value.value = value.dataset.parentValue

			button.classList.remove('is-revert')
			button.classList.add('is-modify')
		},
	})

	XF.Element.register('admin-toggle-advanced', 'XF.AdminToggleAdvanced')

	XF.Element.register('admin-nav', 'XF.AdminNav')
	XF.Element.register('admin-search', 'XF.AdminSearch')
	XF.Element.register('admin-asset-editor', 'XF.AdminAssetEditor')
})(window, document)
