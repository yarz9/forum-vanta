((window, document) =>
{
	'use strict'

	// ################################## TOKEN INPUT HANDLER ###########################################

	XF.TokenInput = XF.Element.newHandler({

		options: {
			tokens: [','],
			minLength: 2,
			maxLength: 0,
			maxTokens: Infinity,
			acUrl: '',
			listData: {},
		},

		tagify: null,
		loadTimer: null,
		abortController: null,

		defaultWhitelist: [],

		init ()
		{
			let whitelist = []

			if (this.options.listData)
			{
				Object.keys(this.options.listData).forEach(key =>
				{
					whitelist.push(this.options.listData[key])
				})
			}

			this.defaultWhitelist = whitelist

			this.tagify = new Tagify(this.target, {
				whitelist,
				delimiters: this.options.tokens.join('|'),
				maxTags: this.options.maxTokens,
				originalInputValueFormat (values)
				{
					return values.map(item => item.value).join(', ')
				},
				templates: {
					tag (tagData)
					{
						const value = tagData.title || tagData.value
						const icon = tagData.iconHtml || ''

						return `
							<tag title="${ value }"
								contenteditable="false" spellcheck="false"
								class="${ this.settings.classNames.tag } ${ tagData.class || '' }"
								tabindex="${ this.settings.a11y.focusableTags ? 0 : -1 }"
								value="${ value }" id="${ value }" q="${ tagData.q }">
								<x title="" class="${ this.settings.classNames.tagX }" role="button" aria-label="remove tag"></x>
								<div>
									${ icon } <span class="${ this.settings.classNames.tagText }">${ value }</span>
								</div>
							</tag>
						`
					},
					dropdownItem (item)
					{
						let value = item.mappedValue || item.value
						const filterRegex = new RegExp('(' + XF.regexQuote(XF.htmlspecialchars(item.q)) + ')', 'i')
						value = value.replace(filterRegex, '<strong>$1</strong>')

						const icon = item.iconHtml || ''

						return `
							<div ${ this.getAttributes(item) }
                                class='${ this.settings.classNames.dropdownItem } ${ item.class ? item.class : '' }'
                                tabindex="0" role="option">
								${ icon ? icon + '&nbsp;' : '' }${ value }
							</div>
						`
					},
				},
				dropdown: {
					enabled: whitelist.length ? 0 : this.options.minLength,
					maxItems: Infinity,
					sortby: 'startsWith',
					highlightFirst: true,
				},
				texts: {
					empty: XF.phrase('tagify_empty'),
					exceed: XF.phrase('tagify_limit_reached'),
					pattern: XF.phrase('tagify_pattern_mismatch'),
					duplicate: XF.phrase('tagify_already_exists'),
					notAllowed: XF.phrase('tagify_not_allowed'),
				}
			})

			if (this.target.disabled)
			{
				this.tagify.setDisabled(true)
			}

			this.tagify.on('blur', this.onBlur.bind(this))
			this.tagify.on('input', this.onInput.bind(this))

			this.tagify.on('dropdown:select', () =>
			{
				XF.MenuWatcher.preventDocClick()
			})

			this.tagify.on('dropdown:hide', () =>
			{
				setTimeout(() =>
				{
					XF.MenuWatcher.allowDocClick()
				}, 0)
			})
		},

		onBlur (e)
		{
			if (this.defaultWhitelist)
			{
				this.tagify.whitelist = this.defaultWhitelist
			}
		},

		onInput (e)
		{
			if (!this.options.acUrl)
			{
				return
			}

			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer)
			}
			this.loadTimer = setTimeout(this.load.bind(this, e.detail.value), 200)
		},

		load (q)
		{
			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer)
			}

			if (q === '')
			{
				return
			}

			if (q.length < this.options.minLength)
			{
				return
			}

			if (this.abortController)
			{
				this.abortController.abort()
				this.abortController = null
			}

			this.tagify.loading(true)

			const { _, abortController, } = XF.ajaxAbortable(
				'get', this.options.acUrl, { q }, this.showResults.bind(this)
			)

			if (abortController)
			{
				this.abortController = abortController
			}
		},

		showResults ({ results, q })
		{
			this.tagify.whitelist = []
			this.tagify.loading(false)

			if (this.abortController)
			{
				this.abortController = null
			}

			results = this.prepareResults(results)

			this.tagify.whitelist.push(...results)
			this.tagify.dropdown.show.call(this.tagify, '')
		},

		prepareResults (results)
		{
			if (!results)
			{
				return []
			}

			return results.map(item =>
			{
				return {
					value: item.text,
					id: item.id,
					q: item.q,
					iconHtml: item.iconHtml,
				}
			})
		},
	})

	XF.Element.register('token-input', 'XF.TokenInput')
})(window, document)
