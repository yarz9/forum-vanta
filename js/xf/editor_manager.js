((window, document) =>
{
	'use strict'

	XF.EditorManager = XF.Element.newHandler({
		options: {
			dragListClass: '.js-dragList',
			commandTrayClass: '.js-dragList-commandTray',
		},

		lists: null,
		trayElements: [],
		listElements: [],
		isScrollable: true,
		dragula: null,
		cache: null,

		xfEditor: null,

		init ()
		{
			this.lists = this.target.querySelectorAll(this.options.dragListClass)
			this.lists.forEach(list => this.prepareList(list))

			this.cache = this.target.querySelector('.js-dragListValue')

			this.initDragula()

			const xfEditor = XF.Element.getHandler(document.querySelector('textarea[name=button_layout_preview_html]'), 'editor')
			if (xfEditor)
			{
				this.xfEditor = xfEditor
				XF.on(xfEditor.target, 'editor:init', this.rebuildValueCache.bind(this))
			}
			else
			{
				this.rebuildValueCache()
			}
		},

		prepareList (list)
		{
			if (list.matches(this.options.commandTrayClass))
			{
				this.trayElements.push(list)
			}
			else
			{
				this.listElements[this.listElements.length] = list // not using .push() because I want them in order

				const listId = this.getListId(list)

				this.getListOptions(listId).forEach(option =>
				{
					XF.on(option, 'change', () => this.updateList(list, true))
				})
			}

			this.updateList(list)
		},

		initDragula ()
		{
			// the following is code to workaround an issue which makes the
			// page scroll while dragging elements.
			document.addEventListener('touchmove', e =>
			{
				if (!this.isScrollable)
				{
					e.preventDefault()
				}
			}, { passive: false })

			const lists = this.listElements

			for (let i in this.trayElements)
			{
				lists.unshift(this.trayElements[i])
			}

			this.dragula = dragula(lists, {
				direction: 'horizontal',
				removeOnSpill: true,
				copy: (el, source) =>
				{
					return this.isTrayElement(source)
				},
				accepts: (el, target) =>
				{
					return !this.isTrayElement(target)
				},
				moves: (el, source, handle, sibling) =>
				{
					return !el.classList.contains('toolbar-addDropdown') && !el.classList.contains('fr-separator')
				},
			})

			this.dragula.on('drag', this.drag.bind(this))
			this.dragula.on('dragend', this.dragend.bind(this))
			this.dragula.on('drop', this.drop.bind(this))
			this.dragula.on('cancel', this.cancel.bind(this))
			this.dragula.on('remove', this.remove.bind(this))
			this.dragula.on('over', this.over.bind(this))
			this.dragula.on('out', this.out.bind(this))
		},

		drag (el, source)
		{
			this.isScrollable = false

			if (el.classList.contains('toolbar-separator') && !source.classList.contains('js-dragList-commandTray'))
			{
				const elNext = el.nextElementSibling
				if (elNext && elNext.classList.contains('fr-separator'))
				{
					elNext.remove()
				}
			}
		},

		dragend (el)
		{
			this.isScrollable = true
			document.querySelector('.js-dropTarget')?.remove()
		},

		drop (el, target, source, sibling)
		{
			const cmd = el.dataset.cmd

			if (el.classList.contains('toolbar-separator'))
			{
				this.appendSeparator(el)
			}
			else
			{
				if (el.nextElementSibling?.matches('.fr-separator'))
				{
					el.nextElementSibling.after(el)
				}
			}

			// if dragged from our dropdown tray, remove the menu click attr
			if (el.getAttribute('data-xf-click') === 'menu')
			{
				el.removeAttribute('data-xf-click')
			}

			if (!this.isTrayElement(source))
			{
				this.updateList(source)
			}
			if (!this.isTrayElement(target))
			{
				this.updateList(target)
			}

			this.rebuildValueCache()
		},

		cancel (el, container, source)
		{
			if (el.classList.contains('toolbar-separator') && !source.classList.contains('js-dragList-commandTray'))
			{
				this.appendSeparator(el)
			}
		},

		remove (el, container, source)
		{
			if (!this.isTrayElement(source))
			{
				XF.flashMessage(XF.phrase('button_removed'), 1500)
				this.updateList(source, true)
			}
		},

		over (el, container, source)
		{
		},

		out (el, container, source)
		{
		},

		getListId (list)
		{
			return list.id.substr(12) // js-toolbar--$id
		},

		getListOptions (listId)
		{
			return document.querySelector(`#js-toolbar-menu--${ listId }`)
				?.querySelectorAll('input, select') || []
		},

		getListOptionValues (listId)
		{
			const optionValues = {
				buttons: [],
			}

			this.getListOptions(listId).forEach(formEl =>
			{
				optionValues[formEl.name] = formEl.value
			})

			return optionValues
		},

		updateList (list, rebuild)
		{
			const listId = this.getListId(list)
			const options = this.getListOptionValues(listId)

			let classesToRemove = Array.from(list.classList)
				.filter(className => className.match(/toolbar-option--[^\s$]+/g))
				.join(' ')

			classesToRemove.split(' ').forEach(cls =>
			{
				if (cls.length)
				{
					list.classList.remove(cls)
				}
			})

			list.classList.add('toolbar-option--buttonsVisible-' + options.buttonsVisible)
			list.classList.add('toolbar-option--align-' + options.align)

			if (rebuild)
			{
				this.rebuildValueCache()
			}
		},

		rebuildValueCache (e)
		{
			const options = {}

			if (!this.cache)
			{
				return
			}

			Array.from(this.lists)
				.filter(list => !list.classList.contains(this.options.commandTrayClass.slice(1)))
				.forEach(list =>
				{
					const listId = this.getListId(list)
					const listValue = this.getListOptionValues(listId)

					Array.from(list.children).forEach(cmd =>
					{
						if (!cmd.dataset.cmd)
						{
							return
						}
						listValue.buttons.push(cmd.dataset.cmd)
					})

					options[listId] = listValue
				})

			this.cache.value = JSON.stringify(options)

			// do not update editor preview if triggered by init
			const isInitTriggered = (e && e.type === 'editor:init')
			if (!isInitTriggered)
			{
				this.updateEditorPreview(options)
			}
		},

		updateEditorPreview (options)
		{
			const xfEditor = this.xfEditor
			const editorToolbar = document.querySelector('.js-editorToolbars')
			let cmd

			if (xfEditor && editorToolbar)
			{
				editorToolbar.innerHTML = JSON.stringify({ toolbarButtons: options })

				if (xfEditor.ed.$tb.hasClass('fr-toolbar-open'))
				{
					cmd = xfEditor.ed.$tb.find('.fr-btn.fr-open').first().data('cmd')
					xfEditor.reInit({
						afterInit ()
						{
							xfEditor.ed.commands[cmd]()
						},
					})
				}
				else
				{
					xfEditor.reInit()
				}
			}
		},

		appendSeparator (el)
		{
			const sep = XF.createElementFromString(`<div class="fr-separator fr${ el.dataset.cmd }"></div>`)
			el.after(sep)
		},

		isTrayElement (el)
		{
			return this.trayElements.includes(el)
		},
	})

	XF.Element.register('editor-manager', 'XF.EditorManager')
})(window, document)
