((window, document) =>
{
	'use strict'

	XF.OembedFetcher = XF.Element.newHandler({
		options: {
			provider: '',
			id: '',
		},

		init ()
		{
			if (this.options.provider && this.options.id)
			{
				XF.ajax('get', XF.canonicalizeUrl('oembed.php'), {
					'provider': this.options.provider,
					'id': this.options.id.replace(/#/, '{{_hash_}}'),
				}).then(data => this.handleResponse(data))
			}
		},

		handleResponse ({ data, response })
		{
			const retainScripts = response.headers.get('X-Oembed-Retain-Scripts')

			if (XF.hasOwn(data, 'html'))
			{
				this.insertOembedHtml(data, retainScripts === '1') // see oEmbed controller response
			}
			else if (data.type == 'photo')
			{
				this.insertOembedImage(data)
			}
			else if (XF.hasOwn(data, 'xf-oembed-error'))
			{
				this.oembedFetchError(data)
			}
		},

		insertOembedHtml (data, retainScripts)
		{
			if (data.html === undefined)
			{
				return false
			}

			const container = {
				content: data.html,
			}

			XF.setupHtmlInsert(container, (html, container, onComplete) =>
			{
				this.target.classList.add('bbOembed--loaded')
				this.target.replaceWith(html)

				onComplete(true, this.target)

				this.onComplete()
			}, retainScripts)
		},

		insertOembedImage (data)
		{
			const a = XF.createElement('a', {
				className: 'bbImage',
				dataset: { zoomTarget: 1 }
			})

			Object.entries(this.getImageLinkData(data)).forEach(([key, value]) =>
			{
				a.setAttribute(key, value)
			})

			const img = XF.createElement('img', {
				onload: this.onComplete,
				src: data.url
			}, a)

			this.target.innerHTML = ''
			this.target.appendChild(a)
		},

		oembedFetchError (data)
		{
			this.target.classList.add('bbOembed--failure')
			console.warn('Unable to fetch %s media id: %s', this.options.provider, this.options.id)
		},

		getImageLinkData (data)
		{
			const attributes = {
				rel: 'external',
				target: '_blank',
			}
			const properties = {
				'href': ['web_page', 'web_page_short_url', 'author_url'],
				'title': ['title'],
				'data-author': ['author_name'],
			}

			Object.entries(properties).forEach(([attr, props]) =>
			{
				props.some(prop =>
				{
					if (XF.hasOwn(data, prop))
					{
						attributes[attr] = data[prop]
						return true
					}
					return false
				})
			})

			return attributes
		},

		onComplete ()
		{
			const newState = {}
			newState[this.options.provider] = true
			XF.config.jsState = XF.applyJsState(XF.config.jsState, newState)
			XF.trigger(document, 'embed:loaded')
			XF.layoutChange()
		}
	})

	XF.TweetRenderer = XF.Element.newHandler({
		options: {
			tweetId: null,

			// see https://dev.twitter.com/web/javascript/creating-widgets
			lang: 'en',
			dnt: 'true',
			related: null,
			via: null,

			conversation: 'all',
			cards: 'visible',
			align: null,
			theme: 'light',
			linkColor: '#2b7bb9',
		},

		init ()
		{
			const tweetId = String(this.options.tweetId)

			if (window.twttr && tweetId.length)
			{
				twttr.ready(twttr =>
				{
					twttr.widgets.createTweet(tweetId, this.target, this.options)
						.then(() =>
						{
							this.target.querySelector('a').remove()
							XF.trigger(document, 'embed:loaded')
							XF.layoutChange()
						})
				})
			}
		},
	})

	XF.Element.register('oembed', 'XF.OembedFetcher')
	XF.Element.register('tweet', 'XF.TweetRenderer')
})(window, document)
