((window, document) =>
{
	'use strict'

	XF.CommentLoader = XF.Event.newHandler({
		eventNameSpace: 'XFCommentLoaderClick',

		options: {
			container: null,
			target: null,
			href: null,
		},

		loaderTarget: null,
		container: null,
		href: null,
		loading: false,

		init ()
		{
			const containerSelector = this.options.container
			const container = containerSelector ? this.target.closest(containerSelector) : this.target

			this.container = container

			const targetSelector = this.options.target
			const target = targetSelector ? XF.findRelativeIf(targetSelector, this.container) : container

			if (target)
			{
				this.loaderTarget = target
			}
			else
			{
				console.error('No loader target for', this.target)
				return
			}

			this.href = this.options.href || this.target.getAttribute('href')

			if (!this.href)
			{
				console.error('No href for', this.target)
			}
		},

		click (e)
		{
			e.preventDefault()

			if (this.loading)
			{
				return
			}

			this.loading = true

			XF.ajax('get', this.href, null)
				.then(response =>
				{
					const { data } = response

					if (data.html)
					{
						XF.setupHtmlInsert(data.html, html =>
						{
							this.loaderTarget.after(html)
							this.container.remove()
						})
					}
				})
				.finally(() => { this.loading = false })
		},
	})

	XF.Event.register('click', 'comment-loader', 'XF.CommentLoader')
})(window, document)
