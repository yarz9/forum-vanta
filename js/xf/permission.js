((window, document) =>
{
	'use strict'

	XF.PermissionForm = XF.Element.newHandler({
		options: {
			form: null,
			filterInput: '.js-permissionFilterInput',
			rows: '.js-permission',
			rowLabel: '.formRow-label',
			groups: '.block-body',
			groupHeader: '.block-formSectionHeader',
			headerCollapser: '.collapseTrigger',
			quickSet: '.js-permissionQuickSet',
			permissionType: null,
		},

		form: null,
		groupEls: null,
		groups: {},

		filterEl: null,
		filterTimer: null,

		init ()
		{
			let target = this.target
			const options = this.options

			if (options.form)
			{
				target = XF.findRelativeIf(options.form, target)
			}
			this.form = target

			if (!options.permissionType)
			{
				console.error('No permission type specified. Must be global or content.')
			}

			const headerSel = options.groupHeader
			const rowSel = options.rows
			const groups = {}

			this.groupEls = target.querySelectorAll(options.groups)
			this.groupEls.forEach(group =>
			{
				const groupId = XF.uniqueId(group)
				const isModerator = parseInt(group.dataset.moderatorPermissions, 10) ? true : false

				let header
				let prevHeaderEl = group.previousElementSibling
				while (prevHeaderEl)
				{
					if (prevHeaderEl.matches(headerSel))
					{
						header = prevHeaderEl
						break
					}
					prevHeaderEl = prevHeaderEl.previousElementSibling
				}

				const rows = group.querySelectorAll(rowSel)

				groups[groupId] = {
					group,
					isModerator,
					header,
					rows,
				}
			})

			this.groups = groups

			this.filterEl = XF.findRelativeIf(options.filterInput, target)
			XF.on(this.filterEl, 'keyup', this.onKeyUp.bind(this))
			XF.on(this.filterEl, 'keypress', this.onKeyPress.bind(this))
			XF.on(this.filterEl, 'paste', this.onPaste.bind(this))

			// note that this can't use delegation as these are in menus which will get moved out when opened
			const quickset = target.querySelectorAll(options.quickSet)
			quickset.forEach(qs =>
			{
				XF.on(qs, 'click', () => this.triggerQuickSet(qs))
			})

			setTimeout(this.applyInitialState.bind(this), 0)
		},

		applyInitialState ()
		{
			const groups = this.groups
			let group
			let header
			let groupEl
			let hasValue

			for (const id of Object.keys(groups))
			{
				group = groups[id]

				header = group.header
				groupEl = group.group
				hasValue = false

				if (!header || !group.isModerator)
				{
					continue
				}

				group.rows.forEach(row =>
				{
					if (this.isRowValueSet(row))
					{
						hasValue = true
						return false
					}
				})

				this.setGroupExpandedState(groupEl, header, hasValue)
			}
		},

		setGroupExpandedState (group, header, isExpanded)
		{
			header.querySelector(this.options.headerCollapser).classList.toggle('is-active', isExpanded)
			group.classList.toggle('is-active', isExpanded)

			XF.layoutChange()
		},

		getRowValue (row)
		{
			const values = XF.Serializer.serializeArray(row.querySelectorAll('input, select'))

			let value = values[values.length - 1].value

			if (/^[0-9]+$/.test(value))
			{
				value = parseInt(value, 10)
			}

			return value
		},

		isValueSet (value)
		{
			if (typeof value == 'number')
			{
				return (value != 0)
			}
			else
			{
				switch (value)
				{
					case 'allow':
					case 'content_allow':
					case 'reset':
					case 'deny':
						return true

					default:
						return false
				}
			}
		},

		isRowValueSet (row)
		{
			return this.isValueSet(this.getRowValue(row))
		},

		// TODO: this code is lifted almost verbatim from filter.js. Look into reconciling the two.

		onKeyUp (e)
		{
			if (e.ctrlKey || e.metaKey)
			{
				return
			}

			switch (e.key)
			{
				case 'Tab':
				case 'Enter':
				case 'Shift':
				case 'Control':
				case 'Alt':
					break

				default:
					this.planFilter()
			}

			if (e.key != 'Enter')
			{
				this.planFilter()
			}
		},

		onKeyPress (e)
		{
			if (e.key == 'Enter')
			{
				e.preventDefault() // stop enter from submitting
				this.filter() // instant submit
			}
		},

		onPaste (e)
		{
			this.planFilter()
		},

		planFilter ()
		{
			if (this.filterTimer)
			{
				clearTimeout(this.filterTimer)
			}
			this.filterTimer = setTimeout(this.filter.bind(this), 250)
		},

		filter ()
		{
			if (this.filterTimer)
			{
				clearTimeout(this.filterTimer)
			}

			const text = this.filterEl.value
			let regex
			let regexHtml
			const groups = this.groups
			const rowLabel = this.options.rowLabel

			if (text.length)
			{
				regex = new RegExp(`(${ XF.regexQuote(text) })`, 'i')
				regexHtml = new RegExp(`(${ XF.regexQuote(XF.htmlspecialchars(text)) })`, 'i')
			}
			else
			{
				regex = false
				regexHtml = false
			}

			let hasAnySkipped = false

			for (const id of Object.keys(groups))
			{
				let hasGroupMatches = false
				let hasGroupSkipped = false
				const group = groups[id]

				group.rows.forEach(row =>
				{
					row.querySelectorAll('.textHighlight').forEach(text =>
					{
						const parent = text.parentNode
						while (text.firstChild)
						{
							parent.insertBefore(text.firstChild, text)
						}
						parent.removeChild(text)

						parent.normalize()
					})
				})

				group.rows.forEach(row =>
				{
					let matched = false

					if (regex)
					{
						const labelEl = row.querySelector(rowLabel)
						const label = labelEl.textContent
						if (regex.test(label))
						{
							matched = true

							const newValue = XF.htmlspecialchars(label).replace(
								regexHtml,
								'<span class="textHighlight textHighlight--attention">$1</span>',
							)
							labelEl.innerHTML = newValue
						}
					}
					else
					{
						matched = true
					}

					XF.display(row, matched ? '' : 'none')

					if (matched)
					{
						hasGroupMatches = true
					}
					else
					{
						hasGroupSkipped = true
						hasAnySkipped = true
					}
				})

				if (regex && !hasGroupMatches)
				{
					XF.display(group.group, 'none')
					XF.display(group.header, 'none')
				}
				else
				{
					XF.display(group.group)
					XF.display(group.header)

					const quickSet = group.group.querySelector('.formRow--permissionQuickSet')
					if (quickSet)
					{
						XF.display(quickSet, hasGroupSkipped ? 'none' : '')
					}

					if (regex)
					{
						this.setGroupExpandedState(group.group, group.header, true)
					}
				}
			}

			this.form.querySelector('.js-globalPermissionQuickSet').style.display = hasAnySkipped ? 'none' : ''

			XF.layoutChange()
		},

		triggerQuickSet (trigger)
		{
			const value = trigger.dataset.value
			const target = trigger.dataset.target
			let targetEl = null

			if (target && target.length)
			{
				targetEl = document.querySelector(target)
			}

			if (!targetEl)
			{
				targetEl = this.form
			}

			targetEl.querySelectorAll(this.options.rows).forEach(row => this.setRowValue(row, value))
		},

		setRowValue (row, value)
		{
			if (row.dataset.permissionType == 'flag')
			{
				const input = row.querySelector(`input[type=radio][value=${ value }]`)
				input.checked = true
				XF.trigger(input, XF.customEvent('click', { triggered: true }))
			}
			else
			{
				const intValue = (value == 'allow' || value == 'content_allow') ? -1 : 0

				row.querySelectorAll('input[type=radio]').forEach(radio =>
				{
					if (parseInt(radio.value, 10) == intValue)
					{
						radio.checked = true
						XF.trigger(radio, XF.customEvent('click', { triggered: true }))
						if (radio.dataset.xfInit)
						{
							row.querySelector('input[type=text], input[type=number]').value = intValue
						}
					}
				})
			}
		},
	})

	XF.PermissionChoice = XF.Element.newHandler({
		options: {
			inputSelector: 'input[type="radio"]',
			inputContainerSelector: 'li',
		},

		init ()
		{
			this.target.querySelectorAll(this.options.inputSelector).forEach(input =>
			{
				XF.on(input, 'click', () =>
				{
					XF.MenuWatcher.closeAll()
					setTimeout(() => this.update(), 0)
				})
			})

			this.update()
		},

		update ()
		{
			const inputContainerSelector = this.options.inputContainerSelector

			this.target.querySelectorAll(this.options.inputSelector).forEach(input =>
			{
				const container = input.closest(inputContainerSelector)
				container.classList.toggle('is-selected', input.checked)
			})
		},
	})

	XF.Element.register('permission-form', 'XF.PermissionForm')
	XF.Element.register('permission-choice', 'XF.PermissionChoice')
})(window, document)
