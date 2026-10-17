;(function (window)
{
	'use strict'

	if (typeof window.XFExtEmbed !== 'undefined')
	{
		return
	}

	window.XFExtEmbed = {
		debug: true,

		init ()
		{
			this.detectEmbeds()
			this.listenForNewEmbeds()
		},

		detectEmbeds ()
		{
			const embeds = document.querySelectorAll('.js-xf-embed')

			for (const embed of embeds)
			{
				const url = embed.getAttribute('data-url')
				const content = embed.getAttribute('data-content')

				this.createIframe(embed, url, content)
			}
		},

		createIframe (embed, url, content)
		{
			let iframe = document.createElement('iframe')
			iframe.src = url + '/embed.php?content=' + content
			iframe.setAttribute('data-url', url)
			iframe.setAttribute('data-content', content)
			iframe.setAttribute('data-xf-embed-loaded', '1')
			iframe.setAttribute('width', window.getComputedStyle(embed).width)
			iframe.setAttribute('frameborder', '0')

			this.setIframeHeight(iframe)

			window.addEventListener('resize', this.setIframeHeight.bind(this, iframe))

			embed.parentNode.replaceChild(iframe, embed)
		},

		setIframeHeight (iframe)
		{
			const messageId = Math.random().toString(36).substring(2, 7)

			const listener = function (event)
			{
				if (event.data.messageId === messageId)
				{
					if (XFExtEmbed.debug)
					{
						console.log('receiving message %s for content %s (height received: %dpx)', messageId, iframe.getAttribute('data-content'), event.data.height)
					}

					iframe.height = event.data.height + 'px'
					window.removeEventListener('message', listener)
				}
			}
			window.addEventListener('message', listener)

			iframe.addEventListener('load', function ()
			{
				if (XFExtEmbed.debug)
				{
					console.log('requesting message %s for content %s', messageId, iframe.getAttribute('data-content'))
				}

				this.contentWindow.postMessage({
					messageId: messageId,
					type: 'getHeight',
				}, '*')
			})
		},

		listenForNewEmbeds ()
		{
			const observer = new MutationObserver(function (mutations)
			{
				mutations.forEach(function (mutation)
				{
					const newEmbeds = mutation.addedNodes
					for (const embed of newEmbeds)
					{
						if (embed.classList && embed.classList.contains('js-xf-embed'))
						{
							const url = embed.getAttribute('data-url')
							const content = embed.getAttribute('data-content')
							this.createIframe(embed, url, content)
						}
					}
				}.bind(this))
			}.bind(this))

			observer.observe(document, {
				childList: true,
				subtree: true,
			})
		},
	}

	window.XFExtEmbed.init()
})(window)
