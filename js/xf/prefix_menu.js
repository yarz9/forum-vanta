((window, document) =>
{
	'use strict'

	// ################################## PREFIX INPUT HANDLER ###########################################

	XF.PrefixMenu = XF.Element.newHandler({

		options: {
			select: '< .js-prefixContainer | .js-prefixSelect',
			title: '< .js-prefixContainer | .js-titleInput',
			active: '.js-activePrefix',
			menu: '| [data-menu]',
			menuContent: '.js-prefixMenuContent',
			listenTo: '',
			href: '',
			helpHref: '',
			helpContainer: '< .formRow | .js-prefixHelp',
			helpSkipInitial: false,
		},

		select: null,
		active: null,
		title: null,
		menu: null,
		menuContent: null,
		template: null,
		initialPrefixId: 0,

		init ()
		{
			this.select = XF.findRelativeIf(this.options.select, this.target)
			if (!this.select)
			{
				console.error('No select matching %s', this.options.select)
				return
			}

			XF.on(this.select, 'control:enabled', this.toggleActive.bind(this))
			XF.on(this.select, 'control:disabled', this.toggleActive.bind(this))

			this.title = XF.findRelativeIf(this.options.title, this.target)

			this.active = this.target.querySelector(this.options.active)

			this.menu = XF.findRelativeIf(this.options.menu, this.target)

			this.menuContent = this.menu.querySelector(this.options.menuContent)
			XF.onDelegated(this.menuContent, 'click', '[data-prefix-id]', this.prefixClick.bind(this))

			this.template = this.menuContent.querySelector('script[type="text/template"]').innerHTML
			if (!this.template)
			{
				console.error('No template could be found')
				this.template = ''
			}

			if (this.options.href)
			{
				const listenTo = this.options.listenTo ? XF.findRelativeIf(this.options.listenTo, this.target) : null
				if (!listenTo)
				{
					console.error('Cannot load prefixes dynamically as no element set to listen to for changes')
				}
				else
				{
					XF.on(listenTo, 'change', this.loadPrefixes.bind(this))
				}
			}

			this.initMenu()

			const prefixId = parseInt(this.select.value, 10)
			if (prefixId)
			{
				this.initialPrefixId = prefixId
				this.selectPrefix(prefixId, true)
			}

			// reset prefix menu when form is reset
			const form = this.target.closest('form')
			XF.on(form, 'reset', this.reset.bind(this))
		},

		initMenu ()
		{
			const groups = []
			const ungrouped = []

			Array.from(this.select.childNodes).forEach(el =>
			{
				if (el.nodeType === Node.TEXT_NODE)
				{
					return
				}

				if (el.matches('optgroup'))
				{
					const prefixes = []
					Array.from(el.querySelectorAll('option')).forEach(opt =>
					{
						prefixes.push({
							prefix_id: opt.value,
							title: opt.textContent,
							css_class: opt.dataset.prefixClass,
						})
					})

					if (prefixes.length)
					{
						groups.push({
							title: el.getAttribute('label'),
							prefixes,
						})
					}
				}
				else
				{
					const value = el.value

					if (value === '0' || value === '-1')
					{
						// skip no/any
						return
					}
					else
					{
						ungrouped.push({
							prefix_id: value,
							title: el.textContent,
							css_class: el.dataset.prefixClass,
						})
					}
				}
			})

			if (ungrouped.length)
			{
				groups.push({
					title: null,
					prefixes: ungrouped,
				})
			}

			this.menuContent.innerHTML = Mustache.render(this.template, { groups })
		},

		reset ()
		{
			this.selectPrefix(this.initialPrefixId, true)
		},

		loadPrefixes (e)
		{
			XF.ajax('POST', this.options.href, {
				val: e.target.value,
				initial_prefix_id: this.initialPrefixId,
			}, this.loadSuccess.bind(this))
		},

		loadSuccess (data)
		{
			if (data.html)
			{
				const select = this.select
				XF.setupHtmlInsert(data.html, html =>
				{
					if (html.tagName === 'SELECT')
					{
						let val = select.value

						select.innerHTML = ''
						select.append(...Array.from(html.children))
						if (!select.querySelector(`option[value="${ val }"]`))
						{
							val = 0
						}

						this.initMenu()
						this.selectPrefix(val)
					}
				})
			}
		},

		toggleActive (e)
		{
			const select = e.target

			const textGroup = this.active.closest('.inputGroup-text')
			if (textGroup)
			{
				if (select.disabled)
				{
					textGroup.classList.add('inputGroup-text--disabled')
				}
				else
				{
					textGroup.classList.remove('inputGroup-text--disabled')
				}
			}
		},

		selectPrefix (id, isInitial)
		{
			id = parseInt(id, 10)

			const active = this.active
			const select = this.select
			let prefix = select.querySelector(`option[value="${ id }"]`)

			if (!prefix)
			{
				id = 0
				prefix = select.querySelector(`option[value="${ id }"]`)
			}

			let removeClass = active.dataset.prefixClass || ''
			let addClass = prefix.dataset.prefixClass || ''

			if (removeClass)
			{
				active.classList.remove(...removeClass.split(' '))
			}
			if (addClass)
			{
				active.classList.add(...addClass.split(' '))
			}

			active.dataset.prefixClass = addClass

			select.value = id
			active.textContent = prefix.textContent

			const showHelp = (
				this.options.helpHref
				&& prefix.dataset.hasHelp
				&& (!isInitial || !this.options.helpSkipInitial)
			)

			if (showHelp)
			{
				XF.ajax(
					'POST',
					this.options.helpHref,
					{ prefix_id: id },
					this.displayHelp.bind(this),
				)
			}
			else
			{
				const helpContainer = this.getHelpContainer()
				if (helpContainer)
				{
					helpContainer.innerHTML = ''
				}
			}

			XF.trigger(select, 'change')
		},

		getHelpContainer ()
		{
			return XF.findRelativeIf(this.options.helpContainer, this.target)
		},

		displayHelp (data)
		{
			const helpContainer = this.getHelpContainer()
			if (data.html && helpContainer)
			{
				XF.setupHtmlInsert(data.html, html =>
				{
					helpContainer.innerHTML = ''
					helpContainer.append(html)
				})
			}
		},

		prefixClick (e)
		{
			this.selectPrefix(e.target.dataset.prefixId)

			const menu = XF.DataStore.get(this.menu, 'menu-trigger')
			if (menu)
			{
				menu.close()
			}

			const title = this.title
			if (title.length)
			{
				title.focus()
			}
		},
	})

	// ################################## PREFIX LOADER HANDLER ###########################################

	XF.PrefixLoader = XF.Element.newHandler({

		options: {
			listenTo: '',
			initUpdate: true,
			href: '',
		},

		init ()
		{
			if (!this.target.matches('select'))
			{
				console.error('Must trigger on select')
				return
			}

			if (this.options.href)
			{
				const listenTo = this.options.listenTo ? XF.findRelativeIf(this.options.listenTo, this.target) : null
				if (!listenTo)
				{
					console.error('Cannot load prefixes dynamically as no element set to listen to for changes')
				}
				else
				{
					XF.on(listenTo, 'change', this.loadPrefixes.bind(this))

					if (this.options.initUpdate)
					{
						XF.trigger(listenTo, 'change')
					}
				}
			}
		},

		loadPrefixes (e)
		{
			XF.ajax('POST', this.options.href, {
				val: e.target.value,
			}, this.loadSuccess.bind(this))
		},

		loadSuccess (data)
		{
			if (data.html)
			{
				const select = this.target

				XF.setupHtmlInsert(data.html, html =>
				{
					const val = select.value

					if (html instanceof HTMLSelectElement)
					{
						select.innerHTML = ''
						select.append(...Array.from(html.children))

						let hasValue = false
						const options = select.querySelectorAll('option')
						options.forEach(option =>
						{
							if (option.getAttribute('value') === val)
							{
								select.value = val
								hasValue = true
							}
						})
						if (!hasValue)
						{
							select.value = options[0].getAttribute('value')
						}

						return false
					}
				})
			}
		},
	})

	XF.Element.register('prefix-menu', 'XF.PrefixMenu')
	XF.Element.register('prefix-loader', 'XF.PrefixLoader')
})(window, document)
