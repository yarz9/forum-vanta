((window, document) =>
{
	'use strict'

	// ################################## NESTABLE HANDLER ###########################################

	XF.Nestable = XF.Element.newHandler({

		options: {
			rootClass: 'nestable-container',
			listClass: 'nestable-list',
			itemClass: 'nestable-item',
			handleClass: 'nestable-handle',
			dragClass: 'nestable-dragel',
			collapsedClass: 'nestable-collapsed',
			placeClass: 'nestable-placeholder',
			noDragClass: 'nestable-nodrag',
			emptyClass: 'nestable-empty',
			classSuffix: '',

			maxDepth: 10000,
			groupId: null,
			parentId: null,
			valueInput: '| input[type="hidden"]',
			valueFunction: 'asNestedSet',
		},

		valueInput: null,
		nestable: null,

		init ()
		{
			if (this.options.classSuffix)
			{
				for (let index in this.options)
				{
					if (index.indexOf('Class') >= 0)
					{
						this.options[index] += this.options.classSuffix
					}
				}
			}

			this.valueInput = XF.findRelativeIf(this.options.valueInput, this.target)
			if (!this.valueInput)
			{
				console.error('No value input found matching selector %s', this.options.valueInput)
				return false
			}

			if (this.options.groupId === null)
			{
				this.options.groupId = 0
			}
			if (this.options.parentId === null)
			{
				this.options.parentId = 0
			}

			const options = {
				rootClass: this.options.rootClass,
				listClass: this.options.listClass,
				itemClass: this.options.itemClass,
				handleClass: this.options.handleClass,
				dragClass: this.options.dragClass,
				collapsedClass: this.options.collapsedClass,
				placeClass: this.options.placeClass,
				noDragClass: this.options.noDragClass,
				emptyClass: this.options.emptyClass,

				expandBtnHTML: '<button type="button" class="nestable-button" data-action="expand">' + XF.Icon.getIcon('regular', 'plus-square') + '</button>',
				collapseBtnHTML: '<button type="button" class="nestable-button" data-action="collapse">' + XF.Icon.getIcon('regular', 'minus-square') + '</button>',

				maxDepth: this.options.maxDepth,
				group: this.options.groupId,
				parentID: this.options.parentId,
			}
			this.nestable = new XF.NestableList(this.target, options)

			XF.on(this.target, 'change', this.change.bind(this))
			this.change()
		},

		change (e)
		{
			this.valueInput.value = JSON.stringify(this.nestable[this.options.valueFunction]())
		},
	})

	XF.NestableList = XF.create({
		options: {
			listNodeName: 'ol',
			itemNodeName: 'li',
			parentID: 0,
			rootClass: 'dd',
			listClass: 'dd-list',
			itemClass: 'dd-item',
			dragClass: 'dd-dragel',
			handleClass: 'dd-handle',
			collapsedClass: 'dd-collapsed',
			placeClass: 'dd-placeholder',
			noDragClass: 'dd-nodrag',
			emptyClass: 'dd-empty',
			expandBtnHTML: '<button data-action="expand" type="button">Expand</button>',
			collapseBtnHTML: '<button data-action="collapse" type="button">Collapse</button>',
			group: 0,
			maxDepth: 5,
			threshold: 20,
		},

		target: null,

		placeEl: null,
		dragEl: null,
		dragRootEl: null,
		pointEl: null,

		dragDepth: 0,
		hasNewRoot: false,

		mouse: {},

		__construct (target, options)
		{
			this.target = target
			this.options = XF.extendObject({}, this.options, options)

			this.init()
		},

		init ()
		{
			this.reset()

			this.target.dataset.nestableGroup = this.options.group

			this.placeEl = XF.createElementFromString(`<div class="${ this.options.placeClass }"></div>`)
			const items = this.target.querySelectorAll(this.options.itemNodeName)

			items.forEach(item =>
			{
				this.setParent(item)
			})

			if (!items.length)
			{
				this.appendEmptyElement(this.target)
			}

			XF.onDelegated(this.target, 'click', 'button', e =>
			{
				if (this.dragEl)
				{
					return
				}

				const target = e.target.closest('button')
				const action = target.dataset.action
				const item = target.parentElement.closest(this.options.itemNodeName)

				if (action === 'collapse')
				{
					this.collapseItem(item)
				}
				if (action === 'expand')
				{
					this.expandItem(item)
				}
			})

			if (this.hasTouchEvents())
			{
				XF.on(this.target, 'touchstart', this.onStartEvent.bind(this))
				XF.on(window, 'touchmove', this.onMoveEvent.bind(this))
				XF.on(window, 'touchend', this.onEndEvent.bind(this))
				XF.on(window, 'touchcancel', this.onEndEvent.bind(this))
			}

			XF.on(this.target, 'mousedown', this.onStartEvent.bind(this))
			XF.on(window, 'mousemove', this.onMoveEvent.bind(this))
			XF.on(window, 'mouseup', this.onEndEvent.bind(this))
		},

		onStartEvent (e)
		{
			if (e.type === 'touchstart' || e.which === 1)
			{
				const isTouch = e.type !== 'mousedown'
				let handle = e.target

				if (!handle.classList.contains(this.options.handleClass))
				{
					if (handle.closest(`.${ this.options.noDragClass }`))
					{
						return
					}
					handle = handle.closest(`.${ this.options.handleClass }`)
				}

				if (!handle || this.dragEl || (!isTouch && e.button !== 0) || (isTouch && e.touches.length !== 1))
				{
					return
				}

				e.preventDefault()
				this.dragStart(isTouch ? e.touches[0] : e)
			}
		},

		onMoveEvent (e)
		{
			const isTouch = e.type != 'mousemove'
			if (this.dragEl)
			{
				e.preventDefault()
				this.dragMove(isTouch ? e.touches[0] : e)
			}
		},

		onEndEvent (e)
		{
			const isTouch = e.type != 'mouseup'
			if (this.dragEl)
			{
				e.preventDefault()
				this.dragStop(isTouch ? e.touches[0] : e)
			}
		},

		hasTouchEvents ()
		{
			return XF.Feature.has('touchevents')
		},

		serialize ()
		{
			const step = (level, depth) =>
			{
				const array = []

				const items = Array.from(level.children)
					.filter(child => child.matches(this.options.itemNodeName))
				items.forEach(li =>
				{
					const item = { ...li.dataset }

					const sub = Array.from(li.children)
						.filter(child => child.matches(this.options.listNodeName))
					if (sub.length)
					{
						item.children = step(sub, depth + 1)
					}

					array.push(item)
				})

				return array
			}

			return step(this.target.querySelector(this.options.listNodeName), 0)
		},

		asNestedSet ()
		{
			const depth = -1
			let ret = []
			let lft = 1

			const traverse = (item, depth, lft) =>
			{
				let rgt = lft + 1
				let id
				let pid

				const listNode = this.options.listNodeName
				const itemNode = this.options.itemNodeName

				const listNodeElement = item.querySelector(listNode)
				let childItems = []

				if (listNodeElement)
				{
					childItems = Array.from(listNodeElement.children).filter(child => child.matches(`${ itemNode }`))
				}

				if (childItems.length > 0)
				{
					depth++
					childItems.forEach(child =>
					{
						rgt = traverse(child, depth, rgt)
					})
					depth--
				}

				id = item.dataset.id
				const list = item.closest(`${ listNode }`)
				const closestItem = list.closest(`${ itemNode }`)
				if (closestItem)
				{
					pid = closestItem.dataset.id
				}
				else
				{
					pid = this.options.parentID
				}

				if (id)
				{
					ret.push({
						'id': id,
						'parent_id': pid,
						'depth': depth,
						'lft': lft,
						'rgt': rgt,
					})
				}

				lft = rgt + 1
				return lft
			}

			const listNode = this.target.querySelector(this.options.listNodeName)
			if (listNode)
			{
				const items = listNode.children
				Array.from(items).forEach(item =>
				{
					if (item.nodeName.toLowerCase() === this.options.itemNodeName)
					{
						lft = traverse(item, depth + 1, lft)
					}
				})
			}

			ret = ret.sort((a, b) => a.lft - b.lft)
			return ret
		},

		reset ()
		{
			this.mouse = {
				offsetX: 0,
				offsetY: 0,
				startX: 0,
				startY: 0,
				lastX: 0,
				lastY: 0,
				nowX: 0,
				nowY: 0,
				distX: 0,
				distY: 0,
				dirAx: 0,
				dirX: 0,
				dirY: 0,
				lastDirX: 0,
				lastDirY: 0,
				distAxX: 0,
				distAxY: 0,
				moving: false,
			}
			this.dragEl = null
			this.dragRootEl = null
			this.dragDepth = 0
			this.hasNewRoot = false
			this.pointEl = null
		},

		expandItem (li)
		{
			li.classList.remove(this.options.collapsedClass)
			li.querySelectorAll('[data-action="expand"]').forEach(el => XF.display(el, 'none'))
			li.querySelectorAll('[data-action="collapse"]').forEach(el => XF.display(el))
			li.querySelectorAll(this.options.listNodeName).forEach(el => XF.display(el))
		},

		collapseItem (li)
		{
			let lists = li.querySelectorAll(this.options.listNodeName)
			if (lists.length)
			{
				li.classList.add(this.options.collapsedClass)
				li.querySelectorAll('[data-action="collapse"]').forEach(el => XF.display(el, 'none'))
				li.querySelectorAll('[data-action="expand"]').forEach(el => XF.display(el))
				li.querySelectorAll(this.options.listNodeName).forEach(el => XF.display(el, 'none'))
			}
		},

		expandAll ()
		{
			let items = this.target.querySelectorAll(this.options.itemNodeName)
			items.forEach(item => this.expandItem(item))
		},

		collapseAll ()
		{
			let items = this.target.querySelectorAll(this.options.itemNodeName)
			items.forEach(item => this.collapseItem(item))
		},

		setParent (li)
		{
			if (li.querySelectorAll(this.options.listNodeName).length)
			{
				li.insertAdjacentHTML('afterbegin', this.options.expandBtnHTML)
				li.insertAdjacentHTML('afterbegin', this.options.collapseBtnHTML)
			}
			li.querySelectorAll('[data-action="expand"]').forEach(el => XF.display(el, 'none'))
		},

		unsetParent (li)
		{
			if (!li)
			{
				return
			}

			li.classList.remove(this.options.collapsedClass)
			li.querySelectorAll('[data-action]').forEach(el => el.remove())
			li.querySelectorAll(this.options.listNodeName).forEach(el => el.remove())
		},

		dragStart (e)
		{
			const target = e.target
			const dragItem = target.closest(this.options.itemNodeName)

			this.placeEl.style.height = `${ dragItem.offsetHeight }px`

			const offsetX = e.offsetX !== undefined ? e.offsetX : e.pageX - target.getBoundingClientRect().left
			const offsetY = e.offsetY !== undefined ? e.offsetY : e.pageY - target.getBoundingClientRect().top
			this.mouse = {
				offsetX: offsetX,
				offsetY: offsetY,
				startX: e.pageX,
				lastX: e.pageX,
				startY: e.pageY,
				lastY: e.pageY,
			}

			this.dragRootEl = this.target

			const width = dragItem.offsetWidth

			this.dragEl = XF.createElement(this.options.listNodeName, {
				className: `${this.options.listClass} ${this.options.dragClass}`,
				style: { width: `${width}px` },
				dataset: { dragWidth: width }
			})

			dragItem.insertAdjacentElement('afterend', this.placeEl)
			dragItem.parentNode.removeChild(dragItem)
			this.dragEl.append(dragItem)

			document.body.append(this.dragEl)

			let dragElCss = {
				left: e.pageX - this.mouse.offsetX,
				top: e.pageY - this.mouse.offsetY,
			}
			if (XF.isRtl())
			{
				dragElCss.left -= this.dragEl.dataset.dragWidth
			}
			this.dragEl.style.left = `${ dragElCss.left }px`
			this.dragEl.style.top = `${ dragElCss.top }px`

			// total depth of dragging item
			const items = this.dragEl.querySelectorAll(this.options.itemNodeName)
			this.dragDepth = Array.from(items).reduce((maxDepth, item) =>
			{
				let depth = Array.from(item.querySelectorAll(this.options.listNodeName)).length
				return depth > maxDepth ? depth : maxDepth
			}, 0)
		},

		dragStop (e)
		{
			const el = this.dragEl.querySelector(this.options.itemNodeName)
			el.parentNode.removeChild(el)
			this.placeEl.replaceWith(el)

			this.dragEl.remove()

			XF.trigger(this.target, 'change')

			if (this.hasNewRoot)
			{
				XF.trigger(this.dragRootEl, 'change')
			}
			this.reset()
		},

		dragMove (e)
		{
			let list
			let parent
			let prev
			let next
			let depth
			const opt = this.options
			const mouse = this.mouse

			const dragElCss = {
				left: e.pageX - mouse.offsetX,
				top: e.pageY - mouse.offsetY,
			}
			if (XF.isRtl())
			{
				dragElCss.left -= this.dragEl.dataset.dragWidth
			}
			this.dragEl.style.left = `${ dragElCss.left }px`
			this.dragEl.style.top = `${ dragElCss.top }px`

			mouse.lastX = mouse.nowX
			mouse.lastY = mouse.nowY
			mouse.nowX = e.pageX
			mouse.nowY = e.pageY
			mouse.distX = mouse.nowX - mouse.lastX
			mouse.distY = mouse.nowY - mouse.lastY
			mouse.lastDirX = mouse.dirX
			mouse.lastDirY = mouse.dirY
			mouse.dirX = mouse.distX === 0 ? 0 : mouse.distX > 0 ? 1 : -1
			mouse.dirY = mouse.distY === 0 ? 0 : mouse.distY > 0 ? 1 : -1
			let newAx = Math.abs(mouse.distX) > Math.abs(mouse.distY) ? 1 : 0

			if (!mouse.moving)
			{
				mouse.dirAx = newAx
				mouse.moving = true
				return
			}

			if (mouse.dirAx !== newAx)
			{
				mouse.distAxX = 0
				mouse.distAxY = 0
			}
			else
			{
				mouse.distAxX += Math.abs(mouse.distX)
				if (mouse.dirX !== 0 && mouse.dirX !== mouse.lastDirX)
				{
					mouse.distAxX = 0
				}
				mouse.distAxY += Math.abs(mouse.distY)
				if (mouse.dirY !== 0 && mouse.dirY !== mouse.lastDirY)
				{
					mouse.distAxY = 0
				}
			}
			mouse.dirAx = newAx

			if (mouse.dirAx && mouse.distAxX >= opt.threshold)
			{
				mouse.distAxX = 0
				prev = this.placeEl.previousElementSibling
				const isIncrease = XF.isRtl() ? (mouse.distX < 0) : (mouse.distX > 0)
				if (isIncrease && prev && !prev.classList.contains(opt.collapsedClass))
				{
					list = Array.from(prev.children).find(child => child.tagName.toLowerCase() === opt.listNodeName)
					let depth = 0
					let parent = this.placeEl.parentNode
					while (parent)
					{
						if (!(parent instanceof Element) || parent.tagName.toLowerCase() === opt.listNodeName)
						{
							depth++
						}
						parent = parent.parentNode
					}
					if (depth + this.dragDepth <= opt.maxDepth)
					{
						if (!list)
						{
							list = XF.createElement(opt.listNodeName, {
								className: opt.listClass
							}, prev)
							list.appendChild(this.placeEl)
							this.setParent(prev)
						}
						else
						{
							list = prev.querySelectorAll(opt.listNodeName + ':last-child')[0]
							list.appendChild(this.placeEl)
						}
					}
				}

				// decrease horizontal level
				const isDecrease = XF.isRtl() ? (mouse.distX > 0) : (mouse.distX < 0)
				if (isDecrease)
				{
					// we can't decrease a level if an item preceeds the current one
					let next = this.placeEl.nextElementSibling
					if (!next || next.tagName.toLowerCase() !== opt.itemNodeName)
					{
						let parent = this.placeEl.parentNode
						const closest = this.closest(this.placeEl, opt.itemNodeName)
						if (closest)
						{
							closest.after(this.placeEl)
						}
						if (!parent.hasChildNodes())
						{
							this.unsetParent(parent.parentNode)
						}
					}
				}
			}

			let isEmpty = false
			this.pointEl = document.elementFromPoint(e.pageX - document.body.scrollLeft, e.pageY - (window.scrollY || document.documentElement.scrollTop))
			if (this.pointEl && this.pointEl.classList.contains(opt.handleClass))
			{
				this.pointEl = this.pointEl.closest(opt.itemNodeName)
			}
			if (this.pointEl && this.pointEl.classList.contains(opt.emptyClass))
			{
				isEmpty = true
			}
			else if (!this.pointEl || !this.pointEl.classList.contains(opt.itemClass))
			{
				return
			}

			const pointElRoot = this.pointEl.closest('.' + opt.rootClass)
			const isNewRoot = this.dragRootEl.dataset.nestableId !== pointElRoot.dataset.nestableId

			if (!mouse.dirAx || isNewRoot || isEmpty)
			{
				if (isNewRoot && opt.group !== parseInt(pointElRoot.dataset.nestableGroup))
				{
					return
				}
				depth = this.dragDepth - 1 + this.countParents(this.pointEl, opt.listNodeName)
				if (depth > opt.maxDepth)
				{
					return
				}
				let before = e.pageY < (this.pointEl.offsetTop + this.pointEl.offsetHeight / 2)
				parent = this.placeEl.parentNode
				if (isEmpty)
				{
					list = XF.createElement(opt.listNodeName, {
						className: opt.listClass
					})
					list.appendChild(this.placeEl)
					this.pointEl.replaceWith(list)
				}
				else if (before)
				{
					this.pointEl.parentNode.insertBefore(this.placeEl, this.pointEl)
				}
				else
				{
					this.pointEl.parentNode.insertBefore(this.placeEl, this.pointEl.nextElementSibling)
				}
				if (!Array.from(parent.children).length)
				{
					this.unsetParent(parent.parentNode)
				}
				if (!this.dragRootEl.querySelector(opt.itemNodeName)
					&& !this.dragRootEl.querySelector('.' + opt.emptyClass)
				)
				{
					this.appendEmptyElement(this.dragRootEl)
				}

				this.dragRootEl = pointElRoot
				if (isNewRoot)
				{
					this.hasNewRoot = this.target !== this.dragRootEl
				}
			}
		},

		closest (element, selector)
		{
			while (element)
			{
				if (element.matches(selector))
				{
					return element
				}
				element = element.parentElement
			}
			return null
		},

		countParents (element, nodeName)
		{
			let count = 0
			while (element.parentNode)
			{
				element = element.parentNode
				if (element.nodeName.toLowerCase() === nodeName)
				{
					count++
				}
			}
			return count
		},

		appendEmptyElement (element)
		{
			element.append(XF.createElementFromString(`<div class="${ this.options.emptyClass }"></div>`))
		},
	})

	XF.Element.register('nestable', 'XF.Nestable')
})(window, document)
