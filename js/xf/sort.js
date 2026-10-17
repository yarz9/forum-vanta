((window, document) =>
{
	'use strict'

	// ################################## NESTABLE HANDLER ###########################################

	XF.ListSorter = XF.Element.newHandler({
		options: {
			dragParent: null,
			dragHandle: null,
			undraggable: '.is-undraggable',
			direction: 'vertical',
			submitOnDrop: false,
		},

		drake: null,

		init ()
		{
			if (this.options.dragParent)
			{
				XF.on(window, 'listSorterDuplication', this.drakeSetup.bind(this))
			}

			XF.onDelegated(this.target, 'touchmove', this.options.dragHandle, e => e.preventDefault())

			this.drakeSetup()
		},

		drakeSetup ()
		{
			if (this.drake)
			{
				this.drake.destroy()
			}

			const dragContainer = this.options.dragParent
				? this.target.querySelector(this.options.dragParent)
				: [this.target]

			this.drake = dragula(
				dragContainer,
				{
					moves: this.isMoveable.bind(this),
					accepts: this.isValidTarget.bind(this),
					direction: this.options.direction,
				},
			)

			if (this.options.submitOnDrop)
			{
				const form = dragContainer.closest('form')
				if (form)
				{
					this.drake.on('drop', e =>
					{
						if (XF.trigger(form, 'submit'))
						{
							form.submit()
						}
					})
				}
			}
		},

		isMoveable (el, source, handle, sibling)
		{
			const handleIs = this.options.dragHandle
			const undraggableIs = this.options.undraggable

			if (handleIs)
			{
				if (!handle.closest(handleIs))
				{
					return false
				}
			}
			if (undraggableIs)
			{
				if (el.closest(undraggableIs))
				{
					return false
				}
			}

			return true
		},

		isValidTarget (el, target, source, sibling)
		{
			let before = !sibling
				? Array.from(this.target.children).slice(-1)[0]
				: sibling.previousElementSibling

			while (before)
			{
				if (before.classList.contains('js-blockDragafter'))
				{
					return false
				}

				before = before.previousElementSibling
			}

			if (sibling)
			{
				let after = sibling

				while (after)
				{
					if (after.classList.contains('js-blockDragbefore'))
					{
						return false
					}

					after = after.nextElementSibling
				}
			}

			return true
		},
	})

	XF.Element.register('list-sorter', 'XF.ListSorter')
})(window, document)
