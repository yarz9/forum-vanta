((window, document) =>
{
	'use strict'

	XF.ThreadEditForm = XF.Element.newHandler({
		options: {
			itemSelector: null,
		},

		item: null,
		inlineEdit: null,

		init ()
		{
			this.item = document.querySelector(this.options.itemSelector)
			if (!this.item)
			{
				return
			}

			XF.on(this.target, 'ajax-submit:before', this.beforeSubmit.bind(this))
			XF.on(this.target, 'ajax-submit:response', this.afterSubmit.bind(this))

			this.inlineEdit = XF.createElementFromString('<input type="hidden" name="_xfInlineEdit" value="1" />')
		},

		beforeSubmit ()
		{
			this.target.appendChild(this.inlineEdit)
		},

		afterSubmit (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			if (data.message)
			{
				XF.flashMessage(data.message, 3000)
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				this.item.innerHTML = ''
				this.item.append(...html.childNodes)
				onComplete(false, html)
			})

			XF.hideParentOverlay(this.target)
		},
	})

	// ################################## QUICK THREAD HANDLER ###########################################

	XF.QuickThread = XF.Element.newHandler({
		options: {
			focusActivate: '.js-titleInput',
			focusActivateTarget: '.js-quickThreadFields',
			focusActivateHref: null,
			insertTarget: '.js-threadList',
			replaceTarget: '.js-emptyThreadList',
		},

		xfInserter: null,
		activated: false,
		loading: false,

		init ()
		{
			const focusActivate = document.querySelector(this.options.focusActivate)
			if (focusActivate)
			{
				this.xfInserter = new XF.Inserter(focusActivate, {
					href: this.options.focusActivateHref,
					replace: this.options.focusActivateTarget,
					afterLoad: () =>
					{
						setTimeout(() =>
						{
							this.activated = true
							XF.trigger(this.target, 'draft:sync')
						}, 500)
					},
				})

				XF.on(focusActivate, 'focus', e =>
				{
					const replace = document.querySelector(this.options.focusActivateTarget)
					if (!replace.hasChildNodes())
					{
						const extraData = {}
						const prefixSelect = this.target.querySelector('.js-prefixSelect')

						if (prefixSelect)
						{
							extraData.prefix_id = prefixSelect.value
						}

						this.xfInserter.onEvent(e, extraData)
					}
				})
			}

			XF.on(this.target, 'ajax-submit:response', this.afterSubmit.bind(this))
			XF.on(this.target, 'reset', this.reset.bind(this))

			XF.on(this.target, 'draft:beforesave', e =>
			{
				if (!this.activated)
				{
					e.preventDefault()
				}
			})
		},

		afterSubmit (e)
		{
			const { data } = e

			if (this.loading)
			{
				return
			}

			this.loading = true

			if (data.errors || data.exception)
			{
				this.loading = false
				return
			}

			e.preventDefault()

			if (data.redirect)
			{
				XF.redirect(data.redirect)
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				XF.hideTooltips()
				XF.display(html, 'none')

				const insertTarget = document.querySelector(this.options.insertTarget)
				if (this.options.direction === 'asc')
				{
					insertTarget.append(html)
				}
				else
				{
					insertTarget.prepend(html)
				}

				const replaceTarget = document.querySelector(this.options.replaceTarget)
				if (replaceTarget)
				{
					replaceTarget.parentNode.replaceChild(html, replaceTarget)
				}

				this.reset(null, () =>
				{
					XF.Animate.fadeDown(html, {
						display: 'table',
					})

					const blockContainer = this.target.closest('.block-container')
					const scrollTo = blockContainer.getBoundingClientRect().top + window.scrollY - 60
					XF.smoothScroll(scrollTo, false, null, true)

					this.loading = false
				})

				onComplete()
			})
		},

		reset (e, onComplete)
		{
			const fat = document.querySelector(this.options.focusActivateTarget)

			XF.hideTooltips()

			XF.Animate.fadeUp(fat, {
				complete: () =>
				{
					this.activated = false

					fat.innerHTML = ''

					if (!e || e.type != 'reset')
					{
						this.target.reset()
					}

					if (typeof onComplete == 'function')
					{
						onComplete()
					}
				},
			})
		},
	})

	XF.Element.register('thread-edit-form', 'XF.ThreadEditForm')
	XF.Element.register('quick-thread', 'XF.QuickThread')
})(window, document)
