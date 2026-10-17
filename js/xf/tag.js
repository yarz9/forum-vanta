((window, document) =>
{
	'use strict'

	// ################################## TOKEN INPUT HANDLER ###########################################

	XF.Tagger = XF.Element.newHandler({

		options: {
			tagList: null,
		},

		tagContainer: null,
		inlineEdit: null,

		init ()
		{
			if (!this.options.tagList)
			{
				return
			}

			this.tagContainer = document.querySelector(`dl.tagList.${ this.options.tagList }`)?.querySelector('.js-tagList')
			if (!this.tagContainer)
			{
				console.warn('No tag container was found for %s', this.options.tagList)
				return
			}

			XF.on(this.target, 'ajax-submit:before', this.beforeSubmit.bind(this))
			XF.on(this.target, 'ajax-submit:response', this.afterSubmit.bind(this))

			this.inlineEdit = XF.createElementFromString('<input type="hidden" name="_xfInlineEdit" value="1" />')
		},

		beforeSubmit ()
		{
			this.target.append(this.inlineEdit)
		},

		afterSubmit (e)
		{
			const { data, submitter } = e

			if (data.errors || data.exception)
			{
				return
			}

			if (XF.hasOwn(data, 'html'))
			{
				const content = XF.createElementFromString(data.html.content.trim())
				this.updateTagList(content.querySelector('.js-tagList'))
			}

			XF.hideParentOverlay(this.target)
		},

		updateTagList (newContent)
		{
			this.tagContainer.innerHTML = ''
			this.tagContainer.append(...newContent.children)
		},
	})

	XF.Element.register('tagger', 'XF.Tagger')
})(window, document)
