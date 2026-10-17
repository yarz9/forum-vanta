((window, document) =>
{
	'use strict'

	XF.Filter = XF.Element.newHandler({
		options: {
			inputEl: '| .js-filterInput',
			prefixEl: '| .js-filterPrefix',
			clearEl: '| .js-filterClear',
			countEl: '.js-filterCount',
			totalsEl: '.js-displayTotals',
			searchTarget: null,
			searchRow: null,
			searchRowGroup: null,
			searchLimit: '',
			key: '',
			ajax: null,
			noResultsFormat: '<div class="js-filterNoResults">%s</div>',
		},

		storageContainer: 'filter',
		storageCutOff: 3600, // 1 hour
		storageKey: null,

		input: null,
		prefix: null,
		clear: null,
		count: null,
		displayTotals: null,
		search: null,
		noResults: null,
		ajaxRows: null,

		updateTimer: null,
		abortController: null,
		xhrFilter: null,

		init ()
		{
			const target = this.target

			if (this.options.searchTarget)
			{
				this.search = XF.findRelativeIf(this.options.searchTarget, target)
			}
			if (!this.search)
			{
				this.search = target.nextElementSibling
			}
			if (!this.search)
			{
				this.search = XF.findExtended('< .block | .dataList', target)[0]
			}

			if (this.search.matches('.dataList') && !this.options.searchRow)
			{
				this.options.searchRow = '.dataList-row:not(.dataList-row--header):not(.dataList-row--subSection):not(.dataList-row--footer)'
				this.options.searchRowGroup = '.dataList-rowGroup'
				this.options.searchLimit = '.dataList-cell:not(.dataList-cell--action):not(.dataList-cell--noSearch)'
				this.options.noResultsFormat = '<tbody><tr class="js-filterNoResults dataList-row dataList-row--note dataList-row--noHover is-hidden"><td class="dataList-cell" colspan="50">%s</td></tr></tbody>'
			}

			this.input = XF.findRelativeIf(this.options.inputEl, target)
			this.prefix = XF.findRelativeIf(this.options.prefixEl, target)
			this.clear = XF.findRelativeIf(this.options.clearEl, target)
			this.count = XF.findRelativeIf(this.options.countEl, target.closest('form, .block'))
			this.displayTotals = XF.findRelativeIf(this.options.totalsEl, target.closest('form .block'))

			XF.on(this.input, 'keyup', this.onKeyUp.bind(this))
			XF.on(this.input, 'keypress', this.onKeyPress.bind(this))
			XF.on(this.input, 'paste', this.onPaste.bind(this))

			XF.on(this.prefix, 'change', this.onPrefixChange.bind(this))
			XF.on(this.clear, 'click', this.onClearFilter.bind(this))

			this.storageKey = this.options.key
			if (!this.storageKey.length)
			{
				const form = target.closest('form')
				if (form)
				{
					this.storageKey = form.getAttribute('action')
				}
			}

			const existing = this._getStoredValue()
			if (existing)
			{
				this.input.value = existing.filter
				this.prefix.checked = existing.prefix
			}

			if (this.input.value.length)
			{
				// this will trigger an update of the stored key
				this.update()
			}

			this._cleanUpStorage()
		},

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
					this.planUpdate()
			}

			if (e.key != 'Enter')
			{
				this.planUpdate()
			}
		},

		onKeyPress (e)
		{
			if (e.key == 'Enter')
			{
				e.preventDefault() // stop enter from submitting
				this.update() // instant submit
			}
		},

		onPaste (e)
		{
			this.planUpdate()
		},

		onPrefixChange (e)
		{
			this.update()
		},

		onClearFilter (e)
		{
			if (!this.clear.matches('.is-disabled'))
			{
				this.input.value = ''

				this.prefix.checked = false
				this.update()
			}
		},

		planUpdate ()
		{
			if (this.updateTimer)
			{
				clearTimeout(this.updateTimer)
			}
			this.updateTimer = setTimeout(this.update.bind(this), 250)
		},

		update ()
		{
			if (this.updateTimer)
			{
				clearTimeout(this.updateTimer)
			}

			this.filter(this.input.value, this.prefix.checked ? true : false)
		},

		_getSearchRows (container)
		{
			if (!container)
			{
				container = this.search
			}

			let rows = container.querySelectorAll(this.options.searchRow)

			if (this.noResults)
			{
				rows = Array.from(rows).filter(row => row !== this.noResults)
			}

			return rows
		},

		filter (text, prefix)
		{
			this._updateStoredValue(text, prefix)
			this._toggleFilterHide(text.length > 0)

			if (this.options.ajax)
			{
				this._filterAjax(text, prefix)
			}
			else
			{
				const matched = this._applyFilter(this._getSearchRows(), text, prefix)
				this._toggleNoResults(matched == 0)
			}
		},

		_filterAjax (text, prefix)
		{
			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			if (!text.length)
			{
				this._clearAjaxRows()
				const matched = this._applyFilter(this._getSearchRows(), text, prefix)
				this._toggleNoResults(matched == 0)
			}
			else
			{
				const params = Object.entries({
					text,
					prefix: prefix ? 1 : 0,
				}).map(([key, value]) => `_xfFilter[${key}]=${encodeURIComponent(value)}`).join('&')

				const ajaxUrl = this.options.ajax + '&' + params

				this.xhrFilter = {
					text,
					prefix,
				}
				const {
					ajax,
					abortController,
				} = XF.ajaxAbortable('GET', ajaxUrl, this._filterAjaxResponse.bind(this))

				if (abortController)
				{
					this.abortController = abortController
				}
			}
		},

		_filterAjaxResponse (result)
		{
			XF.setupHtmlInsert(result.html, (results, container, onComplete) =>
			{
				this.abortController = null
				this._clearAjaxRows()

				const rows = results.querySelectorAll(this.options.searchRow)
				const filter = this.xhrFilter

				const existingRows = this._getSearchRows()
				existingRows.forEach(row => row.classList.add('is-hidden'))

				this._applyRowGroupLimit()
				this._toggleNoResults(rows.length === 0)

				if (rows.length)
				{
					this._appendRows(rows)
					XF.activateAll(rows)
					this.ajaxRows = rows

					this._applyFilter(rows, filter.text, filter.prefix)
				}
				else
				{
					XF.layoutChange()
				}

				this.xhrFilter = null

				onComplete(true)
				return false
			})

		},

		_applyFilter (rows, text, prefix)
		{
			const searchLimit = this.options.searchLimit
			let matched = 0, regex, regexHtml

			if (text.length)
			{
				regex = new RegExp((prefix ? '^' : '') + '(' + XF.regexQuote(text) + ')', 'i')
				regexHtml = new RegExp((prefix ? '^' : '') + '(' + XF.regexQuote(XF.htmlspecialchars(text)) + ')', 'ig')
			}
			else
			{
				regex = false
				regexHtml = false
			}

			rows.forEach(row =>
			{
				let thisMatched = false
				const targets = searchLimit ? row.querySelectorAll(searchLimit) : [row]

				targets.forEach(target =>
				{
					const matches = target.querySelectorAll('span.is-match')
					matches.forEach(match =>
					{
						const parent = match.parentNode
						while (match.firstChild)
						{
							parent.insertBefore(match.firstChild, match)
						}
						parent.removeChild(match)
						parent.normalize()
					})

					if (!regex || row.classList.contains('js-filterForceShow'))
					{
						thisMatched = true
					}
					else
					{
						if (this._searchFilter(target, regex, regexHtml))
						{
							thisMatched = true
						}
					}
				})

				if (thisMatched)
				{
					row.classList.remove('is-hidden')
					if (!row.classList.contains('js-filterForceShow'))
					{
						matched++
					}
				}
				else
				{
					row.classList.add('is-hidden')
				}
			})

			this._applyRowGroupLimit(text.length === 0)
			this.updateDisplayTotals(matched)

			XF.layoutChange()

			return matched
		},

		_applyRowGroupLimit (forceShow)
		{
			const searchRowGroup = this.options.searchRowGroup
			const searchRow = this.options.searchRow

			if (searchRowGroup)
			{
				const groups = this.search.querySelectorAll(searchRowGroup)

				groups.forEach(group =>
				{
					const rows = Array.from(group.querySelectorAll(searchRow)).filter(row => !row.classList.contains('is-hidden'))

					if (forceShow || rows.length)
					{
						group.classList.remove('is-hidden')
					}
					else
					{
						group.classList.add('is-hidden')
					}
				})
			}
		},

		_searchFilter (node, regex, regexHtml)
		{
			let matched = false

			if (node.nodeType === Node.TEXT_NODE)
			{
				if (regex.test(node.data))
				{
					matched = true
					const newValue = XF.createElementFromString(
						'<span>' + XF.htmlspecialchars(node.data).replace(regexHtml, '<span class="is-match">$1</span>') + '</span>'
					)
					node.parentNode.replaceChild(newValue, node)
				}
			}
			else
			{
				const children = Array.from(node.childNodes)
				for (let i = children.length - 1; i >= 0; i--)
				{
					if (this._searchFilter(children[i], regex, regexHtml))
					{
						matched = true
					}
				}
			}

			return matched
		},

		_clearAjaxRows ()
		{
			if (this.ajaxRows)
			{
				this.ajaxRows.forEach(row => row.remove())
				this.ajaxRows = null
			}
		},

		_toggleFilterHide (show)
		{
			this.clear.classList.toggle('is-disabled', !show)

			document.querySelectorAll('.js-filterHide').forEach(el =>
			{
				XF.display(el, show ? 'none' : '')
			})
		},

		_toggleNoResults (show)
		{
			if (show)
			{
				// show no results
				this.getNoResultsRow().classList.remove('is-hidden')
				this.updateDisplayTotals(0)
			}
			else
			{
				// hide no results
				if (this.noResults)
				{
					this.noResults.classList.add('is-hidden')
				}
			}
		},

		updateDisplayTotals (count)
		{
			if (this.count)
			{
				this.count.textContent = count
			}

			if (this.displayTotals)
			{
				this.displayTotals.dataset.count = count

				let phrase = ''
				const total = this.displayTotals.dataset.total

				if (count < 1)
				{
					// no results
					phrase = XF.phrases.no_items_to_display
				}
				else if (count === total)
				{
					// all results
					phrase = XF.phrases.showing_all_items
				}
				else
				{
					// showing of total
					phrase = XF.phrases.showing_x_of_y_items
				}

				this.displayTotals.textContent = XF.stringTranslate(phrase, {
					'{count}': count.toLocaleString(),
					'{total}': total.toLocaleString(),
				})
			}
		},

		getNoResultsRow ()
		{
			if (this.noResults)
			{
				return this.noResults
			}

			const noResultsHtml = this.options.noResultsFormat.replace('%s', XF.phrase('no_items_matched_your_filter'))

			// wrap in <table> tags to make the DOM "valid"
			const noResults = XF.createElementFromString('<table>' + noResultsHtml.trim() + '</table>').firstChild
			this.noResults = noResults

			const searchRow = '.js-filterNoResults'
			this._appendRows([noResults])

			if (noResults.matches(searchRow))
			{
				this.noResults = noResults
			}
			else
			{
				this.noResults = noResults.querySelector(searchRow)
			}

			return this.noResults
		},

		_appendRows (rows)
		{
			const existingRows = this.search.querySelectorAll(this.options.searchRow)
			const lastRow = existingRows.length
				? existingRows[existingRows.length - 1]
				: null
			let lastRowContainer = null
			const searchRowGroup = this.options.searchRowGroup

			if (lastRow && searchRowGroup)
			{
				lastRowContainer = lastRow.closest(searchRowGroup)
			}

			if (!lastRowContainer && lastRow && lastRow.matches('tr'))
			{
				lastRowContainer = lastRow.closest('tbody')
			}

			if (!lastRowContainer && lastRow)
			{
				lastRowContainer = lastRow
			}

			if (lastRowContainer)
			{
				Array.from(rows)
					.reverse()
					.forEach((row) =>
					{
						lastRowContainer.insertAdjacentElement('afterend', row)
					})
			}
			else
			{
				this.search.append(...rows)
			}
		},

		_getStoredValue ()
		{
			if (!this.storageKey)
			{
				return null
			}

			const data = this._readFromStorage()
			if (data[this.storageKey])
			{
				const record = data[this.storageKey]
				const tsSaved = record.saved || 0
				const tsNow = Math.floor(new Date().getTime() / 1000)

				if (tsSaved + this.storageCutOff >= tsNow)
				{
					return {
						filter: record.filter || '',
						prefix: record.prefix || false,
					}
				}
			}

			return null
		},

		_updateStoredValue (val, prefix)
		{
			if (!this.storageKey)
			{
				return
			}

			const data = this._readFromStorage()

			if (!val.length)
			{
				if (data[this.storageKey])
				{
					delete data[this.storageKey]
				}
			}
			else
			{
				data[this.storageKey] = {
					filter: val,
					prefix: prefix ? true : false,
					saved: Math.floor(new Date().getTime() / 1000),
				}
			}

			this._writeToStorage(data)
		},

		_cleanUpStorage ()
		{
			if (!this.storageKey)
			{
				return
			}

			const data = this._readFromStorage()
			let updated = false
			const tsCutoff = Math.floor(new Date().getTime() / 1000) - this.storageCutOff

			for (const k of Object.keys(data))
			{
				if ((data[k].saved || 0) < tsCutoff)
				{
					delete data[k]
					updated = true
				}
			}

			if (updated)
			{
				this._writeToStorage(data)
			}
		},

		_readFromStorage ()
		{
			return XF.LocalStorage.getJson(this.storageContainer)
		},

		_writeToStorage (data)
		{
			if (Object.keys(data).length === 0)
			{
				XF.LocalStorage.remove(this.storageContainer)
			}
			else
			{
				XF.LocalStorage.setJson(this.storageContainer, data, true)
			}
		},
	})

	XF.PrefixGrabber = XF.Event.newHandler({
		eventNameSpace: 'XFPrefixGrabberClick',

		options: {
			filterElement: '[data-xf-init~=filter]',
		},

		filterHandler: null,

		init ()
		{
			this.filterHandler = XF.Element.getHandler(document.querySelector(this.options.filterElement), 'filter')
			if (!(this.filterHandler instanceof XF.Filter))
			{
				console.warn('PrefixGrabber did not find an element with an XF.Filter handler')
				return false
			}
		},

		click (e)
		{
			if (this.filterHandler.prefix.checked)
			{
				const prefix = this.filterHandler.input.value
				let href

				if (prefix.length)
				{
					href = this.target.getAttribute('href')
					href = `${ href }${ href.indexOf('?') === -1 ? '?' : '&' }prefix=${ prefix }`

					this.target.setAttribute('href', href)
				}
			}
		},
	})

	XF.Element.register('filter', 'XF.Filter')
	XF.Event.register('click', 'prefix-grabber', 'XF.PrefixGrabber')
})(window, document)
