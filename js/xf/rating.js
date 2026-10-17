((window, document) =>
{
	'use strict'

	XF.Rating = XF.Element.newHandler({
		options: {
			theme: 'fontawesome-stars',
			initialRating: null,
			ratingHref: null,
			readonly: false,
			deselectable: false,
			showSelected: true,
		},

		ratingOverlay: null,

		barrating: null,
		widget: null,
		ratings: null,

		init ()
		{
			const target = this.target
			const options = this.options
			const initialRating = options.initialRating
			const showSelected = options.showSelected
			const readonly = options.readonly

			this.barrating = new XF.BarRating(target, {
				theme: options.theme,
				initialRating,
				readonly: readonly ? true : false,
				deselectable: options.deselectable ? true : false,
				showSelectedRating: showSelected ? true : false,
				onSelect: this.ratingSelected.bind(this),
			})

			let widget = target.nextElementSibling
			if (!widget || !widget.classList.contains('br-widget'))
			{
				widget = null
			}

			const ratings = widget.querySelectorAll('[data-rating-text]')

			this.widget = widget
			this.ratings = ratings

			if (initialRating)
			{
				target.value = initialRating
			}

			if (showSelected)
			{
				widget.classList.add('br-widget--withSelected')
			}

			if (!readonly)
			{
				const selectId = target.getAttribute('id')
				let labelledBy = null

				if (selectId)
				{
					const label = document.querySelector(`label[for="${ selectId }"]`)
					if (label)
					{
						labelledBy = XF.uniqueId(label)
					}
				}

				widget.setAttribute('role', 'radiogroup')
				widget.setAttribute('aria-labelledby', labelledBy)

				Array.from(ratings).forEach(rating =>
				{
					const checked = initialRating && rating.getAttribute('data-rating-value') == initialRating

					rating.setAttribute('role', 'radio')
					rating.setAttribute('aria-checked', checked ? 'true' : 'false')
					rating.setAttribute('aria-label', rating.getAttribute('data-rating-text'))
					rating.setAttribute('tabindex', checked ? 0 : -1)
				})

				if (!initialRating)
				{
					ratings[0].setAttribute('tabindex', 0)
				}

				Array.from(ratings).forEach(rating =>
				{
					XF.on(rating, 'keydown', e =>
					{
						let handled = false

						switch (e.keyCode)
						{
							case 37: // left
							case 38: // up
								handled = true
								this.keySelectPrevious()
								break

							case 39: // right
							case 40: // down
								handled = true
								this.keySelectNext()
								break
						}

						if (handled)
						{
							e.preventDefault()
							e.stopPropagation()
						}
					})
				})
			}
		},

		keySelect (refMethod)
		{
			const target = this.target
			const val = target.value
			const ref = target.querySelector(`option[value="${ val }"]`)
			let newEl

			if (refMethod === 'next')
			{
				newEl = ref.nextElementSibling
			}
			else // prev
			{
				newEl = ref.previousElementSibling
			}

			if (!newEl)
			{
				return
			}

			const newVal = newEl.value

			this.barrating.set(newVal)

			Array.from(this.ratings).forEach(rating =>
			{
				if (rating.matches(`[data-rating-value="${ newVal }"]`))
				{
					rating.focus()
				}
			})
		},

		keySelectPrevious ()
		{
			this.keySelect('prev')
		},

		keySelectNext ()
		{
			this.keySelect('next')
		},

		ratingSelected (value, text, event)
		{
			if (this.options.readonly)
			{
				return
			}

			Array.from(this.ratings).forEach(rating =>
			{
				rating.setAttribute('aria-checked', 'false')
				rating.setAttribute('tabindex', -1)

				if (value)
				{
					if (rating.matches(`[data-rating-value="${ value }"]`))
					{
						rating.setAttribute('aria-checked', 'true')
						rating.setAttribute('tabindex', 0)
					}
				}
			})

			if (!value)
			{
				this.ratings[0].setAttribute('tabindex', 0)
			}

			if (!this.options.ratingHref)
			{
				return
			}

			if (this.ratingOverlay)
			{
				this.ratingOverlay.destroy()
			}

			this.barrating.clear()

			XF.ajax('get', this.options.ratingHref, {
				rating: value,
			}, this.loadOverlay.bind(this))
		},

		loadOverlay (data)
		{
			if (data.html)
			{
				XF.setupHtmlInsert(data.html, (html, container) =>
				{
					const overlay = XF.getOverlayHtml({
						html,
						title: container.h1 || container.title,
					})
					this.ratingOverlay = XF.showOverlay(overlay)
				})
			}
		},
	})

	XF.BarRating = XF.create({
		options: {
			theme: '',
			initialRating: null,
			allowEmpty: null,
			emptyValue: '',
			showValues: false,
			showSelectedRating: true,
			deselectable: true,
			reverse: false,
			readonly: false,
			fastClicks: true,
			hoverState: true,
			silent: false,
			onSelect (value, text, event) {},
			onClear (value, text) {},
			onDestroy (value, text) {},
		},

		target: null,
		widget: null,

		__construct (target, options)
		{
			this.target = target
			this.options = XF.extendObject({}, this.options, options)

			this.init()
		},

		init ()
		{
			if (!this.target.matches('select'))
			{
				console.error('XF.BarRating must be provided with a select element.')
				return
			}

			this.show()
		},

		attachMouseEnterHandler (elements)
		{
			Array.from(elements).forEach(element =>
			{
				XF.on(element, 'mouseenter.barrating', () =>
				{
					this.resetStyle()

					element.classList.add('br-active')

					const nextOrPrev = this[this.nextAllorPreviousAll()](element)
					if (nextOrPrev.length)
					{
						Array.from(nextOrPrev).forEach(el => el.classList.add('br-active'))
					}

					this.showSelectedRating(element.getAttribute('data-rating-text'))
				})
			})
		},

		attachMouseLeaveHandler ()
		{
			const handler = () =>
			{
				this.showSelectedRating()
				this.applyStyle()
			}
			XF.on(this.widget, 'mouseleave.barrating', handler)
			XF.on(this.widget, 'blur.barrating', handler)
		},

		fastClicks (elements)
		{
			Array.from(elements).forEach(element =>
			{
				XF.on(element, 'touchstart.barrating', event =>
				{
					event.preventDefault()
					event.stopPropagation()

					element.click()
				})
			})
		},

		disableClicks (elements)
		{
			Array.from(elements).forEach(element =>
			{
				XF.on(element, 'click.barrating', event =>
				{
					event.preventDefault()
				})
			})
		},

		attachHandlers (elements)
		{
			// attach click event handler
			this.attachClickHandler(elements)

			if (this.options.hoverState)
			{
				// attach mouseenter event handler
				this.attachMouseEnterHandler(elements)

				// attach mouseleave event handler
				this.attachMouseLeaveHandler(elements)
			}
		},

		detachHandlers (elements)
		{
			Array.from(elements).forEach(element =>
			{
				XF.off(element, '.barrating')
			})
		},

		setupHandlers (readonly)
		{
			const elements = this.widget.querySelectorAll('a')

			this.fastClicks(elements)

			if (readonly)
			{
				this.detachHandlers(elements)
				this.disableClicks(elements)
			}
			else
			{
				this.attachHandlers(elements)
			}
		},

		buildWidget ()
		{
			const w = XF.createElement('div', {
				className: 'br-widget'
			})

			const options = Array.from(this.target.querySelectorAll('option'))
			options.forEach(option =>
			{
				const val = option.value
				if (val !== this.getData('emptyRatingValue'))
				{
					const text = option.textContent
					const html = option.dataset.html
					const a = XF.createElement('a', {
						href: '#',
						innerHTML: this.options.showValues ? text : '',
						dataset: {
							ratingValue: val,
							ratingText: text
						}
					}, w)
				}
			})

			if (this.options.showSelectedRating)
			{
				const currentRating = XF.createElement('div', {
					className: 'br-current-rating'
				}, w)
			}

			if (this.options.reverse)
			{
				w.classList.add('br-reverse')
			}

			if (this.options.readonly)
			{
				w.classList.add('br-readonly')
			}

			return w
		},

		show ()
		{
			if (this.getData())
			{
				return
			}

			this.wrapElement()

			this.saveDataOnElement()

			this.widget = this.buildWidget()
			this.target.after(this.widget)

			this.applyStyle()

			this.showSelectedRating()

			this.setupHandlers(this.options.readonly)

			// hide the select field
			XF.display(this.target, 'none')
		},

		readonly (state)
		{
			if (typeof state !== 'boolean' || this.getData('readOnly') == state)
			{
				return
			}

			this.setupHandlers(state)
			this.setData('readOnly', state)
			this.widget.classList.toggle('br-readonly')
		},

		applyStyle ()
		{
			const a = this.widget.querySelector('a[data-rating-value="' + this.ratingValue() + '"]')
			const initialRating = this.getData('userOptions').initialRating
			const baseValue = XF.isNumeric(this.ratingValue()) ? this.ratingValue() : 0
			const f = this.fraction(initialRating)
			let all, fractional

			this.resetStyle()

			// add classes
			if (a)
			{
				a.classList.add('br-selected', 'br-current')

				const nextOrPrev = this[this.nextAllorPreviousAll()](a)
				if (nextOrPrev.length)
				{
					Array.from(nextOrPrev).forEach(el => el.classList.add('br-selected'))
				}
			}

			if (!this.getData('ratingMade') && XF.isNumeric(initialRating))
			{
				if ((initialRating <= baseValue) || !f)
				{
					return
				}

				all = Array.from(this.widget.querySelectorAll('a'))
				fractional = a ?
					(this.getData('userOptions').reverse ? a.previousElementSibling : a.nextElementSibling) :
					(this.getData('userOptions').reverse ? all[all.length - 1] : all[0])

				fractional.classList.add('br-fractional')
				fractional.classList.add(`br-fractional-${ f }`)
			}
		},

		isDeselectable (element)
		{
			if (!this.getData('allowEmpty') || !this.getData('userOptions').deselectable)
			{
				return false
			}

			return (this.ratingValue() == element.getAttribute('data-rating-value'))
		},

		attachClickHandler (elements)
		{
			Array.from(elements).forEach(element =>
			{
				XF.on(element, 'click.barrating', event =>
				{
					event.preventDefault()

					const options = this.getData('userOptions')

					let value = element.getAttribute('data-rating-value')
					let text = element.getAttribute('data-rating-text')

					if (this.isDeselectable(element))
					{
						value = this.getData('emptyRatingValue')
						text = this.getData('emptyRatingText')
					}

					// remember selected rating
					this.setData('ratingValue', value)
					this.setData('ratingText', text)
					this.setData('ratingMade', true)

					this.setSelectFieldValue(value)
					this.showSelectedRating(text)

					this.applyStyle()

					// onSelect callback
					options.onSelect.call(
						this,
						this.ratingValue(),
						this.ratingText(),
						event,
					)

					return false
				})
			})
		},

		resetStyle ()
		{
			const anchors = this.widget.querySelectorAll('a')
			anchors.forEach(a =>
			{
				const classList = a.classList
				const classesToRemove = Array.from(classList).filter(className => className.match(/^br-/))

				classesToRemove.forEach(className => classList.remove(className))
			})
		},

		fraction (value)
		{
			return Math.round(((Math.floor(value * 10) / 10) % 1) * 100)
		},

		showSelectedRating (text)
		{
			// text undefined?
			text = text ? text : this.ratingText()

			// special case when the selected rating is defined as empty
			if (text == this.getData('emptyRatingText'))
			{
				text = ''
			}

			// update .br-current-rating div
			if (this.options.showSelectedRating)
			{
				this.target.parentNode.querySelector('.br-current-rating').textContent = text
			}
		},

		wrapElement ()
		{
			const classes = ['br-wrapper']

			if (this.options.theme !== '')
			{
				classes.push('br-theme-' + this.options.theme)
			}

			const wrapperDiv = document.createElement('div')
			wrapperDiv.classList.add(...classes)

			const parent = this.target.parentNode
			parent.insertBefore(wrapperDiv, this.target)
			wrapperDiv.appendChild(this.target)
		},

		unwrapElement ()
		{
			const target = this.target
			const parent = target.parentNode

			if (parent)
			{
				while (target.firstChild)
				{
					parent.insertBefore(target.firstChild, target)
				}

				parent.removeChild(target)
			}
		},

		nextAllorPreviousAll ()
		{
			if (this.getData('userOptions').reverse)
			{
				return 'nextAll'
			}
			else
			{
				return 'prevAll'
			}
		},

		nextAll (element)
		{
			const siblings = []
			let current = element.nextElementSibling

			while (current !== null)
			{
				siblings.push(current)
				current = current.nextElementSibling
			}

			return siblings
		},

		prevAll (element)
		{
			const siblings = []
			let current = element.previousElementSibling

			while (current !== null)
			{
				siblings.push(current)
				current = current.previousElementSibling
			}

			return siblings
		},

		getInitialOption ()
		{
			const initialRating = this.options.initialRating

			if (!initialRating)
			{
				return this.target.querySelector('option:checked')
			}

			return this.findOption(initialRating)
		},

		findOption (value)
		{
			if (XF.isNumeric(value))
			{
				value = Math.floor(value)
			}

			return this.target.querySelector(`option[value="${ value }"]`)
		},

		getEmptyOption ()
		{
			const emptyOpt = this.target.querySelector(`option[value="${ this.options.emptyValue }"]`)

			if (!emptyOpt && this.options.allowEmpty)
			{
				const newEmptyOpt = XF.createElement('option', {
					value: this.options.emptyValue
				})
				this.target.insertBefore(newEmptyOpt, this.target.firstChild)
				return newEmptyOpt
			}

			return emptyOpt
		},

		set (value)
		{
			const options = this.getData('userOptions')
			const option = this.target.querySelector(`option[value="${ value }"]`)

			if (!option)
			{
				return
			}

			// set data
			this.setData('ratingValue', value)
			this.setData('ratingText', option.textContent)
			this.setData('ratingMade', true)

			this.setSelectFieldValue(this.ratingValue())
			this.showSelectedRating(this.ratingText())

			this.applyStyle()

			// onSelect callback
			if (!options.silent)
			{
				options.onSelect.call(
					this,
					this.ratingValue(),
					this.ratingText(),
				)
			}
		},

		// set the value of the select field
		setSelectFieldValue (value)
		{
			// change selected option
			const option = this.findOption(value)
			if (option)
			{
				option.selected = true
			}

			XF.trigger(this.target, 'change')
		},

		// reset select field
		resetSelectField (value)
		{
			const options = this.target.querySelectorAll('option')
			Array.from(options).forEach(option =>
			{
				option.selected = option.defaultSelected
			})

			XF.trigger(this.target, 'change')
		},

		clear ()
		{
			const options = this.getData('userOptions')

			// restore original data
			this.setData('ratingValue', this.getData('originalRatingValue'))
			this.setData('ratingText', this.getData('originalRatingText'))
			this.setData('ratingMade', false)

			this.resetSelectField()
			this.showSelectedRating(this.ratingText())

			this.applyStyle()

			// onClear callback
			options.onClear.call(
				this,
				this.ratingValue(),
				this.ratingText(),
			)
		},

		destroy ()
		{
			const value = this.ratingValue()
			const text = this.ratingText()
			const options = this.getData('userOptions')

			// detach handlers
			this.detachHandlers(this.widget.querySelectorAll('a'))

			// remove widget
			this.widget.remove()

			// remove data
			this.removeDataOnElement()

			// unwrap the element
			this.unwrapElement()

			// show the element
			this.target.display = 'block'

			// onDestroy callback
			options.onDestroy.call(
				this,
				value,
				text,
			)
		},

		ratingText ()
		{
			return this.getData('ratingText')
		},

		ratingValue ()
		{
			return this.getData('ratingValue')
		},

		getData (key)
		{
			const data = XF.DataStore.get(this.target, 'barrating')

			if (typeof key !== 'undefined')
			{
				return data[key]
			}

			return data
		},

		setData (key, value)
		{
			if (value !== null && typeof value === 'object')
			{
				XF.DataStore.set(this.target, 'barrating', value)
			}
			else
			{
				const data = this.getData()
				data[key] = value

				XF.DataStore.set(this.target, 'barrating', data)
			}
		},

		saveDataOnElement ()
		{
			const opt = this.getInitialOption()
			const emptyOpt = this.getEmptyOption()

			const value = opt.value
			const text = opt.dataset.html ? opt.dataset.html : opt.textContent

			// if the allowEmpty option is not set let's check if empty option exists in the select field
			const allowEmpty = (this.options.allowEmpty !== null) ? this.options.allowEmpty : Boolean(emptyOpt)

			const emptyValue = emptyOpt ? emptyOpt.value : null
			const emptyText = emptyOpt ? emptyOpt.textContent : null

			this.setData(null, {
				userOptions: this.options,

				// initial rating based on the OPTION value
				ratingValue: value,
				ratingText: text,

				// rating will be restored by calling clear method
				originalRatingValue: value,
				originalRatingText: text,

				// allow empty ratings?
				allowEmpty,

				// rating value and text of the empty OPTION
				emptyRatingValue: emptyValue,
				emptyRatingText: emptyText,

				// read-only state
				readOnly: this.options.readonly,

				// did the user already select a rating?
				ratingMade: false,
			})
		},

		removeDataOnElement ()
		{
			XF.DataStore.remove(this.target, 'barrating')
		},
	})

	XF.Element.register('rating', 'XF.Rating')
})(window, document)
